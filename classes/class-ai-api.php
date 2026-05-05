<?php
/**
 * Plugin AI API functions.
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mind AI API class.
 */
class Mind_AI_API {
	/**
	 * Buffer for streaming response.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Last time the buffer was sent.
	 *
	 * @var int
	 */
	private $last_send_time = 0;

	/**
	 * Buffer threshold.
	 *
	 * @var int
	 */
	private const BUFFER_THRESHOLD = 150;

	/**
	 * Minimum send interval.
	 *
	 * @var float
	 */
	private const MIN_SEND_INTERVAL = 0.05;

	/**
	 * The single class instance.
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Main Instance
	 * Ensures only one instance of this class exists in memory at any one time.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Get connected model.
	 *
	 * @return array|bool
	 */
	public function get_connected_model() {
		$settings = get_option( 'mind_settings', array() );
		$ai_model = $settings['ai_model'] ?? '';
		$result   = false;

		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return $result;
		}

		if ( $ai_model ) {
			$provider = self::get_model_provider( $ai_model );

			if ( 'openai' === $provider && self::is_connector_connected( 'openai' ) ) {
				$result = [
					'provider' => 'openai',
					'name'     => $ai_model,
					'key'      => self::get_connector_api_key( 'openai' ),
				];
			} elseif ( 'anthropic' === $provider && self::is_connector_connected( 'anthropic' ) ) {
				$result = [
					'provider' => 'anthropic',
					'name'     => $ai_model,
					'key'      => self::get_connector_api_key( 'anthropic' ),
				];
			}
		}

		return $result;
	}

	/**
	 * Get AI model slots for settings UI.
	 *
	 * @return array
	 */
	public static function get_model_slots() {
		$settings       = get_option( 'mind_settings', array() );
		$selected_model = $settings['ai_model'] ?? '';
		$provider_models = array(
			'anthropic' => self::get_provider_models( 'anthropic' ),
			'openai'    => self::get_provider_models( 'openai' ),
		);
		$slot_configs    = array(
			array(
				'family'      => 'sonnet',
				'provider'    => 'anthropic',
				'title'       => __( 'Claude Sonnet', 'mind' ),
				'description' => __( 'Best quality and recommended', 'mind' ),
			),
			array(
				'family'      => 'haiku',
				'provider'    => 'anthropic',
				'title'       => __( 'Claude Haiku', 'mind' ),
				'description' => __( 'Fast and accurate', 'mind' ),
			),
			array(
				'family'      => 'gpt',
				'provider'    => 'openai',
				'title'       => __( 'GPT', 'mind' ),
				'description' => __( 'Quick and reliable', 'mind' ),
			),
			array(
				'family'      => 'gpt-mini',
				'provider'    => 'openai',
				'title'       => __( 'GPT mini', 'mind' ),
				'description' => __( 'Basic and fastest', 'mind' ),
			),
		);

		return array_map(
			static function ( $slot ) use ( $provider_models, $selected_model ) {
				$models         = $provider_models[ $slot['provider'] ];
				$current_model  = self::find_slot_model( $models, $slot['provider'], $slot['family'] );
				$selected_slot  = self::get_model_family( $selected_model ) === $slot['family'];
				$selected_item  = null;

				if (
					$selected_slot &&
					$selected_model &&
					( ! $current_model || $current_model['name'] !== $selected_model )
				) {
					if ( $current_model && self::are_model_names_equivalent( $current_model['name'], $selected_model ) ) {
						$selected_item         = $current_model;
						$selected_item['name'] = $selected_model;
					} else {
						$selected_item = self::find_model_by_name( $models, $selected_model );
					}

					if ( ! $selected_item ) {
						$selected_item = self::create_legacy_model_data(
							$selected_model,
							$slot['provider'],
							$slot['family']
						);
					}
				}

				return array_merge(
					$slot,
					array(
						'model'         => $current_model,
						'selectedModel' => $selected_item,
						'registered'    => self::is_provider_registered( $slot['provider'] ),
						'connected'     => self::is_connector_connected( $slot['provider'] ),
					)
				);
			},
			$slot_configs
		);
	}

	/**
	 * Get AI Client models for a provider.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return array
	 */
	private static function get_provider_models( $provider_id ) {
		if (
			! self::is_connector_connected( $provider_id ) ||
			! class_exists( '\WordPress\AiClient\AiClient' )
		) {
			return array();
		}

		try {
			$registry       = \WordPress\AiClient\AiClient::defaultRegistry();
			$provider_class = $registry->getProviderClassName( $provider_id );
			$model_dir      = $provider_class::modelMetadataDirectory();
			$models         = array();

			foreach ( $model_dir->listModelMetadata() as $model_metadata ) {
				$model = self::normalize_model_metadata( $model_metadata, $provider_id );

				if ( $model && self::model_supports_text_generation( $model_metadata ) ) {
					$models[] = $model;
				}
			}

			return $models;
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/**
	 * Normalize AI Client model metadata for the admin UI.
	 *
	 * @param object $model_metadata Model metadata.
	 * @param string $provider_id Provider ID.
	 *
	 * @return array|null
	 */
	private static function normalize_model_metadata( $model_metadata, $provider_id ) {
		if ( ! is_object( $model_metadata ) || ! method_exists( $model_metadata, 'getId' ) ) {
			return null;
		}

		$model_id    = (string) $model_metadata->getId();
		$model_name  = method_exists( $model_metadata, 'getName' ) ? (string) $model_metadata->getName() : $model_id;
		$deprecated = self::get_model_deprecation_data( $model_metadata );

		return array(
			'name'            => $model_id,
			'title'           => self::format_model_title( $model_name ),
			'provider'        => $provider_id,
			'family'          => self::get_model_family( $model_id ),
			'canonicalName'   => self::get_model_canonical_name( $model_id ),
			'available'       => true,
			'deprecated'      => $deprecated['deprecated'],
			'deprecationDate' => $deprecated['date'],
		);
	}

	/**
	 * Check if model supports text generation.
	 *
	 * @param object $model_metadata Model metadata.
	 *
	 * @return bool
	 */
	private static function model_supports_text_generation( $model_metadata ) {
		if ( ! method_exists( $model_metadata, 'getSupportedCapabilities' ) ) {
			return true;
		}

		foreach ( $model_metadata->getSupportedCapabilities() as $capability ) {
			$value = '';

			if (
				is_object( $capability ) &&
				method_exists( $capability, 'isTextGeneration' ) &&
				$capability->isTextGeneration()
			) {
				return true;
			}

			if ( is_object( $capability ) ) {
				try {
					$value = $capability->value;
				} catch ( Throwable $e ) {
					$value = '';
				}
			} elseif ( is_string( $capability ) ) {
				$value = $capability;
			}

			if ( 'text_generation' === $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the first available model for a slot.
	 *
	 * @param array  $models Models.
	 * @param string $provider_id Provider ID.
	 * @param string $family Model family.
	 *
	 * @return array|null
	 */
	private static function find_slot_model( $models, $provider_id, $family ) {
		foreach ( $models as $model ) {
			if (
				$model['provider'] === $provider_id &&
				$model['family'] === $family
			) {
				return $model;
			}
		}

		return null;
	}

	/**
	 * Find a normalized model by name.
	 *
	 * @param array  $models Models.
	 * @param string $model_name Model name.
	 *
	 * @return array|null
	 */
	private static function find_model_by_name( $models, $model_name ) {
		foreach ( $models as $model ) {
			if ( $model['name'] === $model_name ) {
				return $model;
			}
		}

		return null;
	}

	/**
	 * Create model data for a saved model that is not in the current provider list.
	 *
	 * @param string $model_name Model name.
	 * @param string $provider_id Provider ID.
	 * @param string $family Model family.
	 *
	 * @return array
	 */
	private static function create_legacy_model_data( $model_name, $provider_id, $family ) {
		return array(
			'name'            => $model_name,
			'title'           => self::format_model_title( $model_name ),
			'provider'        => $provider_id,
			'family'          => $family,
			'canonicalName'   => self::get_model_canonical_name( $model_name ),
			'available'       => false,
			'deprecated'      => false,
			'deprecationDate' => '',
		);
	}

	/**
	 * Get model provider from model name.
	 *
	 * @param string $model_name Model name.
	 *
	 * @return string
	 */
	private static function get_model_provider( $model_name ) {
		if ( 0 === strpos( $model_name, 'claude-' ) ) {
			return 'anthropic';
		}

		if ( 0 === strpos( $model_name, 'gpt-' ) ) {
			return 'openai';
		}

		return '';
	}

	/**
	 * Get model family from model name.
	 *
	 * @param string $model_name Model name.
	 *
	 * @return string
	 */
	private static function get_model_family( $model_name ) {
		if ( false !== strpos( $model_name, 'sonnet' ) ) {
			return 'sonnet';
		}

		if ( false !== strpos( $model_name, 'haiku' ) ) {
			return 'haiku';
		}

		if ( 0 === strpos( $model_name, 'gpt-' ) ) {
			return false !== strpos( $model_name, 'mini' ) ? 'gpt-mini' : 'gpt';
		}

		return '';
	}

	/**
	 * Check if two model names point to the same display model.
	 *
	 * @param string $first_model First model name.
	 * @param string $second_model Second model name.
	 *
	 * @return bool
	 */
	private static function are_model_names_equivalent( $first_model, $second_model ) {
		return self::get_model_canonical_name( $first_model ) === self::get_model_canonical_name( $second_model );
	}

	/**
	 * Get canonical model name for comparing provider aliases.
	 *
	 * @param string $model_name Model name.
	 *
	 * @return string
	 */
	private static function get_model_canonical_name( $model_name ) {
		$model_name = strtolower( (string) $model_name );

		return preg_replace( '/-[0-9]{8}$/', '', $model_name );
	}

	/**
	 * Format model title.
	 *
	 * @param string $model_name Model name.
	 *
	 * @return string
	 */
	private static function format_model_title( $model_name ) {
		$model_name = preg_replace( '/-[0-9]{8}$/', '', $model_name );
		$model_name = preg_replace( '/(?<=\d)-(?=\d)/', '.', $model_name );
		$model_name = str_replace( array( '-', '_' ), ' ', $model_name );
		$model_name = preg_replace( '/\bgpt\b/i', 'GPT', $model_name );
		$model_name = ucwords( $model_name );
		$model_name = preg_replace( '/\bGpt\b/', 'GPT', $model_name );

		return $model_name;
	}

	/**
	 * Extract future deprecation metadata if the AI Client exposes it.
	 *
	 * @param object $model_metadata Model metadata.
	 *
	 * @return array
	 */
	private static function get_model_deprecation_data( $model_metadata ) {
		$deprecated = false;
		$date       = '';
		$data       = method_exists( $model_metadata, 'toArray' ) ? (array) $model_metadata->toArray() : array();

		foreach ( array( 'deprecated', 'isDeprecated' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$deprecated = (bool) $data[ $key ];
			}
		}

		if ( method_exists( $model_metadata, 'isDeprecated' ) ) {
			$deprecated = (bool) $model_metadata->isDeprecated();
		}

		foreach ( array( 'deprecationDate', 'deprecation_date', 'sunsetDate', 'sunset_date', 'retirementDate', 'retirement_date' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$date = self::format_model_deprecation_date( $data[ $key ] );
				break;
			}
		}

		foreach ( array( 'getDeprecationDate', 'getSunsetDate', 'getRetirementDate' ) as $method ) {
			if ( method_exists( $model_metadata, $method ) ) {
				$date_value = $model_metadata->$method();

				if ( $date_value ) {
					$date = self::format_model_deprecation_date( $date_value );
					break;
				}
			}
		}

		return array(
			'deprecated' => $deprecated,
			'date'       => $date,
		);
	}

	/**
	 * Format model deprecation date.
	 *
	 * @param mixed $date Date value.
	 *
	 * @return string
	 */
	private static function format_model_deprecation_date( $date ) {
		if ( $date instanceof DateTimeInterface ) {
			return $date->format( 'Y-m-d' );
		}

		return is_scalar( $date ) ? (string) $date : '';
	}

	/**
	 * Get connector API key.
	 *
	 * @since 0.4.0
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return string
	 */
	public static function get_connector_api_key( $provider_id ) {
		if ( ! function_exists( 'wp_get_connector' ) ) {
			return '';
		}

		$connector = wp_get_connector( $provider_id );
		if (
			! is_array( $connector ) ||
			! isset( $connector['authentication']['method'] ) ||
			'api_key' !== $connector['authentication']['method'] ||
			empty( $connector['authentication']['setting_name'] )
		) {
			return '';
		}

		return (string) get_option( $connector['authentication']['setting_name'], '' );
	}

	/**
	 * Check if provider is registered in the WordPress AI Client.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return bool
	 */
	public static function is_provider_registered( $provider_id ) {
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return false;
		}

		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return false;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			return $registry->hasProvider( $provider_id );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if provider is available and has a configured API key.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return bool
	 */
	public static function is_connector_connected( $provider_id ) {
		return self::is_provider_registered( $provider_id ) && '' !== self::get_connector_api_key( $provider_id );
	}

	/**
	 * Send request to API.
	 *
	 * @param string $request request text.
	 * @param string $selected_blocks selected blocks context.
	 * @param string $page_blocks page blocks context.
	 * @param string $page_context page context.
	 *
	 * @return mixed
	 */
	public function request( $request, $selected_blocks = '', $page_blocks = '', $page_context = '' ) {
		// Set headers for streaming.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		ob_implicit_flush( true );
		ob_end_flush();

		if ( ! $request ) {
			$this->send_stream_error( 'no_request', __( 'Provide request to receive AI response.', 'mind' ) );
			exit;
		}

		$connected_model = $this->get_connected_model();

		if ( ! $connected_model ) {
			$this->send_stream_error( 'no_model_connected', __( 'Select an AI model and connect its API key in WordPress Connectors.', 'mind' ) );
			exit;
		}

		$messages = $this->prepare_messages( $request, $selected_blocks, $page_blocks, $page_context );

		if ( $connected_model['provider'] === 'openai' ) {
			$this->request_open_ai( $connected_model, $messages );
		} else {
			$this->request_anthropic( $connected_model, $messages );
		}

		exit;
	}

	/**
	 * Prepare messages for request.
	 *
	 * @param string $user_query user query.
	 * @param string $selected_blocks selected blocks context.
	 * @param string $page_blocks page blocks context.
	 * @param string $page_context page context.
	 */
	public function prepare_messages( $user_query, $selected_blocks, $page_blocks, $page_context ) {
		$user_query = '<user_query>' . $user_query . '</user_query>';

		if ( $selected_blocks ) {
			$user_query .= "\n";
			$user_query .= '<selected_blocks_context>' . $selected_blocks . '</selected_blocks_context>';
		}
		if ( $page_blocks ) {
			$user_query .= "\n";
			$user_query .= '<page_blocks_context>' . $page_blocks . '</page_blocks_context>';
		}
		if ( $page_context ) {
			$user_query .= "\n";
			$user_query .= '<page_context>' . $page_context . '</page_context>';
		}

		return [
			[
				'role'    => 'system',
				'content' => Mind_Prompts::get_system_prompt(),
			],
			[
				'role'    => 'user',
				'content' => $user_query,
			],
		];
	}

	/**
	 * Convert OpenAI messages format to Anthropic format.
	 *
	 * @param array $openai_messages Array of messages in OpenAI format.
	 * @return array Messages in Anthropic format
	 */
	public function convert_to_anthropic_messages( $openai_messages ) {
		$system   = [];
		$messages = [];

		foreach ( $openai_messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$allow_cache = strlen( $message['content'] ) > 2100;

				// Convert system message.
				$system[] = array_merge(
					array(
						'type' => 'text',
						'text' => $message['content'],
					),
					$allow_cache ? array(
						'cache_control' => [ 'type' => 'ephemeral' ],
					) : array()
				);
			} else {
				// Convert user/assistant messages.
				$messages[] = [
					'role'    => 'assistant' === $message['role'] ? 'assistant' : 'user',
					'content' => $message['content'],
				];
			}
		}

		return array(
			'system'   => $system,
			'messages' => $messages,
		);
	}

	/**
	 * Request Anthropic API.
	 *
	 * @param array $model model.
	 * @param array $messages messages.
	 */
	public function request_anthropic( $model, $messages ) {
		$anthropic_messages = $this->convert_to_anthropic_messages( $messages );
		$anthropic_version  = '2023-06-01';

		$body = [
			'model'      => $model['name'],
			'max_tokens' => 8192,
			'system'     => $anthropic_messages['system'],
			'messages'   => $anthropic_messages['messages'],
			'stream'     => true,
		];

		/* phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close */

		$ch = curl_init( 'https://api.anthropic.com/v1/messages' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			[
				'Content-Type: application/json',
				'x-api-key: ' . $model['key'],
				'anthropic-version: ' . $anthropic_version,
			]
		);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) {
				// Response with error message.
				if ( $data && strpos( $data, '{"type":"error","error":{' ) !== false ) {
					$error_data = json_decode( $data, true );

					if ( isset( $error_data['error']['message'] ) ) {
						$this->send_stream_error( 'anthropic_error', $error_data['error']['message'] );
					}

					return strlen( $data );
				}

				$this->process_anthropic_stream_chunk( $data );

				return strlen( $data );
			}
		);

		curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			$this->send_stream_error( 'curl_error', curl_error( $ch ) );
		}

		curl_close( $ch );
	}

	/**
	 * Request OpenAI API.
	 *
	 * @param array $model model.
	 * @param array $messages messages.
	 */
	public function request_open_ai( $model, $messages ) {
		$body = [
			'model'       => $model['name'],
			'stream'      => true,
			'top_p'       => 0.9,
			'temperature' => 0.7,
			'messages'    => $messages,
		];

		/* phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close */

		$ch = curl_init( 'https://api.openai.com/v1/chat/completions' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $model['key'],
			]
		);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) {
				// Response with error message.
				if ( $data && strpos( $data, "{\n    \"error\": {\n        \"message\":" ) !== false ) {
					$error_data = json_decode( $data, true );

					if ( isset( $error_data['error']['message'] ) ) {
						$this->send_stream_error( 'openai_error', $error_data['error']['message'] );
					}

					return strlen( $data );
				}

				$this->process_openai_stream_chunk( $data );

				return strlen( $data );
			}
		);

		curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			$this->send_stream_error( 'curl_error', curl_error( $ch ) );
		}

		curl_close( $ch );
	}

	/**
	 * Process streaming chunk from OpenAI
	 *
	 * @param string $chunk - chunk of data.
	 */
	private function process_openai_stream_chunk( $chunk ) {
		$lines = explode( "\n", $chunk );

		foreach ( $lines as $line ) {
			if ( strlen( trim( $line ) ) === 0 ) {
				continue;
			}

			if ( strpos( $line, 'data: ' ) === 0 ) {
				$json_data = trim( substr( $line, 6 ) );

				if ( '[DONE]' === $json_data ) {
					if ( ! empty( $this->buffer ) ) {
						$this->send_buffered_chunk();
					}
					$this->send_stream_chunk( [ 'done' => true ] );
					return;
				}

				try {
					$data = json_decode( $json_data, true );

					if ( isset( $data['choices'][0]['delta']['content'] ) ) {
						$content = $data['choices'][0]['delta']['content'];

						// Send immediately for JSON markers.
						if ( strpos( $content, '```json' ) !== false ||
							strpos( $content, '```' ) !== false ) {
							if ( ! empty( $this->buffer ) ) {
								$this->send_buffered_chunk();
							}
							$this->send_stream_chunk( [ 'content' => $content ] );
							$this->last_send_time = microtime( true );
							continue;
						}

						$this->buffer        .= $content;
						$current_time         = microtime( true );
						$time_since_last_send = $current_time - $this->last_send_time;

						if ( strlen( $this->buffer ) >= self::BUFFER_THRESHOLD ||
							$time_since_last_send >= self::MIN_SEND_INTERVAL ||
							strpos( $this->buffer, "\n" ) !== false ) {
							$this->send_buffered_chunk();
						}
					}
				} catch ( Exception $e ) {
					$this->send_stream_error( 'json_error', $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Process streaming chunk from Anthropic
	 *
	 * @param string $chunk - chunk of data.
	 */
	private function process_anthropic_stream_chunk( $chunk ) {
		$lines = explode( "\n", $chunk );

		foreach ( $lines as $line ) {
			if ( strlen( trim( $line ) ) === 0 ) {
				continue;
			}

			// Remove "data: " prefix if exists.
			if ( strpos( $line, 'data: ' ) === 0 ) {
				$json_data = trim( substr( $line, 6 ) );
			} else {
				$json_data = trim( $line );
			}

			// Skip empty events.
			if ( '' === $json_data ) {
				continue;
			}

			try {
				$data = json_decode( $json_data, true );

				if ( isset( $data['type'] ) ) {
					if ( 'content_block_delta' === $data['type'] && isset( $data['delta']['text'] ) ) {
						$content = $data['delta']['text'];

						// Send immediately for JSON markers.
						if (
							strpos( $content, '```json' ) !== false ||
							strpos( $content, '```' ) !== false
						) {
							if ( ! empty( $this->buffer ) ) {
								$this->send_buffered_chunk();
							}

							$this->send_stream_chunk( [ 'content' => $content ] );
							$this->last_send_time = microtime( true );
						} else {
							$this->buffer .= $content;
							$current_time  = microtime( true );

							$time_since_last_send = $current_time - $this->last_send_time;

							if (
								strlen( $this->buffer ) >= self::BUFFER_THRESHOLD ||
								$time_since_last_send >= self::MIN_SEND_INTERVAL ||
								strpos( $this->buffer, "\n" ) !== false
							) {
								$this->send_buffered_chunk();
							}
						}
					} elseif ( 'message_stop' === $data['type'] ) {
						if ( ! empty( $this->buffer ) ) {
							$this->send_buffered_chunk();
						}

						$this->send_stream_chunk( [ 'done' => true ] );

						return;
					}
				}
			} catch ( Exception $e ) {
				$this->send_stream_error( 'json_error', $e->getMessage() );
			}
		}
	}


	/**
	 * Send buffered chunk
	 */
	private function send_buffered_chunk() {
		if ( empty( $this->buffer ) ) {
			return;
		}

		$this->send_stream_chunk(
			[
				'content' => $this->buffer,
			]
		);

		$this->buffer         = '';
		$this->last_send_time = microtime( true );
	}

	/**
	 * Send stream chunk
	 *
	 * @param array $data - data to send.
	 */
	private function send_stream_chunk( $data ) {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";

		if ( ob_get_level() > 0 ) {
			ob_flush();
		}

		flush();
	}

	/**
	 * Send stream error
	 *
	 * @param string $code - error code.
	 * @param string $message - error message.
	 */
	private function send_stream_error( $code, $message ) {
		$this->send_stream_chunk(
			[
				'error'   => true,
				'code'    => $code,
				'message' => $message,
			]
		);
	}
}
