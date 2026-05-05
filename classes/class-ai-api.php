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
		$settings            = get_option( 'mind_settings', array() );
		$selected_model_name = $settings['ai_model'] ?? '';
		$selected_model      = self::get_selected_model_state( $selected_model_name );

		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return false;
		}

		if ( ! $selected_model['runtimeAvailable'] ) {
			return false;
		}

		return array(
			'provider' => $selected_model['provider'],
			'name'     => $selected_model['name'],
		);
	}

	/**
	 * Get AI model slots for settings UI.
	 *
	 * @return array
	 */
	public static function get_model_slots() {
		$settings        = get_option( 'mind_settings', array() );
		$selected_model  = $settings['ai_model'] ?? '';
		$selected_state  = self::get_selected_model_state( $selected_model );
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
			static function ( $slot ) use ( $provider_models, $selected_model, $selected_state ) {
				$models        = $provider_models[ $slot['provider'] ];
				$current_model = self::find_slot_model( $models, $slot['provider'], $slot['family'] );
				$selected_slot = self::get_model_family( $selected_model ) === $slot['family'];
				$selected_item = null;

				if ( $selected_slot && $selected_model ) {
					$selected_item = $selected_state['selectedModel'];

					if ( $selected_item ) {
						$selected_item['runtimeAvailable'] = $selected_state['runtimeAvailable'];
					}

					if ( $selected_state['currentModel'] && $selected_state['provider'] === $slot['provider'] && $selected_state['family'] === $slot['family'] ) {
						$current_model = $selected_state['currentModel'];
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
	 * Check whether a saved model name can be used for runtime requests.
	 *
	 * @param string $model_name Saved model name.
	 *
	 * @return bool
	 */
	public static function is_valid_selected_model( $model_name ) {
		$selected_model = self::get_selected_model_state( $model_name );

		return $selected_model['runtimeAvailable'];
	}

	/**
	 * Get explicit setup state for editor and admin UI.
	 *
	 * @return array
	 */
	public static function get_setup_state() {
		$settings                = get_option( 'mind_settings', array() );
		$selected_model_name     = $settings['ai_model'] ?? '';
		$selected_model          = self::get_selected_model_state( $selected_model_name );
		$has_provider_connection = self::is_connector_connected( 'openai' ) || self::is_connector_connected( 'anthropic' );

		return array(
			'connected'               => $selected_model['runtimeAvailable'],
			'hasValidSelectedModel'   => $selected_model['runtimeAvailable'],
			'needsProviderConnection' => ! $has_provider_connection,
			'needsModelSelection'     => $has_provider_connection && ! $selected_model['runtimeAvailable'],
			'selectedModelName'       => $selected_model_name,
			'selectedProvider'        => $selected_model['provider'],
			'canManageConnectors'     => current_user_can( 'manage_options' ),
		);
	}

	/**
	 * Resolve the saved model name against the current provider state.
	 *
	 * @param string $model_name Saved model name.
	 *
	 * @return array
	 */
	private static function get_selected_model_state( $model_name ) {
		$model_name      = is_string( $model_name ) ? $model_name : '';
		$provider_id     = self::get_model_provider( $model_name );
		$family          = self::get_model_family( $model_name );
		$registered      = $provider_id ? self::is_provider_registered( $provider_id ) : false;
		$connected       = $provider_id ? self::is_connector_connected( $provider_id ) : false;
		$provider_models = ( $provider_id && $connected ) ? self::get_provider_models( $provider_id ) : array();
		$current_model   = ( $provider_id && $family ) ? self::find_slot_model( $provider_models, $provider_id, $family ) : null;
		$selected_model  = null;

		if ( $model_name && $provider_id && $family ) {
			$selected_model = self::find_model_by_name( $provider_models, $model_name );

			if ( ! $selected_model ) {
				$selected_model = self::create_legacy_model_data( $model_name, $provider_id, $family );
			}
		}

		return array(
			'name'             => $model_name,
			'provider'         => $provider_id,
			'family'           => $family,
			'registered'       => $registered,
			'connected'        => $connected,
			'currentModel'     => $current_model,
			'selectedModel'    => $selected_model,
			'runtimeAvailable' => self::can_resolve_runtime_model( $provider_id, $model_name ),
		);
	}

	/**
	 * Check whether the exact saved model can be resolved by the provider registry.
	 *
	 * @param string $provider_id Provider ID.
	 * @param string $model_name Saved model name.
	 *
	 * @return bool
	 */
	private static function can_resolve_runtime_model( $provider_id, $model_name ) {
		if ( ! $provider_id || ! $model_name || ! self::is_connector_connected( $provider_id ) ) {
			return false;
		}

		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return false;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			$registry->getProviderModel( $provider_id, $model_name );

			return true;
		} catch ( Throwable $e ) {
			return false;
		}
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

		$model_id   = (string) $model_metadata->getId();
		$model_name = method_exists( $model_metadata, 'getName' ) ? (string) $model_metadata->getName() : $model_id;
		$deprecated = self::get_model_deprecation_data( $model_metadata );

		return array(
			'name'             => $model_id,
			'title'            => self::format_model_title( $model_name ),
			'provider'         => $provider_id,
			'family'           => self::get_model_family( $model_id ),
			'canonicalName'    => self::get_model_canonical_name( $model_id ),
			'available'        => true,
			'runtimeAvailable' => true,
			'deprecated'       => $deprecated['deprecated'],
			'deprecationDate'  => $deprecated['date'],
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
			'name'             => $model_name,
			'title'            => self::format_model_title( $model_name ),
			'provider'         => $provider_id,
			'family'           => $family,
			'canonicalName'    => self::get_model_canonical_name( $model_name ),
			'available'        => false,
			'runtimeAvailable' => false,
			'deprecated'       => false,
			'deprecationDate'  => '',
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
	 * Get connector authentication config.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return array
	 */
	private static function get_connector_authentication( $provider_id ) {
		if ( ! function_exists( 'wp_get_connector' ) ) {
			return array();
		}

		$connector = wp_get_connector( $provider_id );

		if ( ! is_array( $connector ) || empty( $connector['authentication'] ) || ! is_array( $connector['authentication'] ) ) {
			return array();
		}

		return $connector['authentication'];
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
		$authentication = self::get_connector_authentication( $provider_id );

		if (
			empty( $authentication ) ||
			! isset( $authentication['method'] ) ||
			'api_key' !== $authentication['method'] ||
			empty( $authentication['setting_name'] )
		) {
			return '';
		}

		if ( ! empty( $authentication['env_var_name'] ) ) {
			$env_value = getenv( $authentication['env_var_name'] );

			if ( false !== $env_value && '' !== $env_value ) {
				return (string) $env_value;
			}
		}

		if ( ! empty( $authentication['constant_name'] ) && defined( $authentication['constant_name'] ) ) {
			$constant_value = constant( $authentication['constant_name'] );

			if ( is_string( $constant_value ) && '' !== $constant_value ) {
				return $constant_value;
			}
		}

		return (string) get_option( $authentication['setting_name'], '' );
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
		if ( ! self::is_provider_registered( $provider_id ) ) {
			return false;
		}

		if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
			try {
				$registry = \WordPress\AiClient\AiClient::defaultRegistry();

				if ( $registry->hasProvider( $provider_id ) ) {
					return $registry->isProviderConfigured( $provider_id );
				}
			} catch ( Exception $e ) {
				// Fall back to local connector credential resolution below.
				unset( $e );
			}
		}

		return '' !== self::get_connector_api_key( $provider_id );
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

		$this->request_ai_client( $connected_model, $messages );

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
	 * Execute the prompt through the WordPress AI Client and preserve the current SSE contract.
	 *
	 * @param array $model Connected model data.
	 * @param array $messages Prepared prompt messages.
	 *
	 * @return void
	 */
	private function request_ai_client( $model, $messages ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->send_stream_error( 'ai_client_unavailable', __( 'The WordPress AI Client is not available in this environment.', 'mind' ) );
			return;
		}

		$prompt_messages = $this->get_ai_client_messages( $messages );

		if ( is_wp_error( $prompt_messages ) ) {
			$this->send_stream_error( $prompt_messages->get_error_code(), $prompt_messages->get_error_message() );
			return;
		}

		$builder = wp_ai_client_prompt( $prompt_messages );

		try {
			$registry    = \WordPress\AiClient\AiClient::defaultRegistry();
			$exact_model = $registry->getProviderModel( $model['provider'], $model['name'] );
		} catch ( Exception $e ) {
			$this->send_stream_error( 'model_resolution_error', $e->getMessage() );
			return;
		}

		if ( ! empty( $messages[0]['role'] ) && 'system' === $messages[0]['role'] && ! empty( $messages[0]['content'] ) ) {
			$builder->using_system_instruction( $messages[0]['content'] );
		}

		$builder
			->using_model( $exact_model )
			->using_max_tokens( 8192 )
			->using_temperature( 0.7 );

		$content = $builder->generate_text();

		if ( is_wp_error( $content ) ) {
			$this->send_stream_error( $content->get_error_code(), $content->get_error_message() );
			return;
		}

		if ( '' === trim( $content ) ) {
			$this->send_stream_error( 'empty_ai_response', __( 'The AI Client returned an empty response.', 'mind' ) );
			return;
		}

		$this->send_stream_chunk( [ 'content' => $content ] );
		$this->send_stream_chunk( [ 'done' => true ] );
	}

	/**
	 * Convert legacy message format to the shape accepted by wp_ai_client_prompt().
	 *
	 * @param array $messages Legacy prompt messages.
	 *
	 * @return array|WP_Error
	 */
	private function get_ai_client_messages( $messages ) {
		$prompt_messages = array();

		foreach ( $messages as $message ) {
			if ( empty( $message['content'] ) || empty( $message['role'] ) || 'system' === $message['role'] ) {
				continue;
			}

			try {
				$prompt_messages[] = \WordPress\AiClient\Messages\DTO\Message::fromArray(
					array(
						'role'  => 'assistant' === $message['role'] ? 'model' : 'user',
						'parts' => array(
							array(
								'type' => 'text',
								'text' => $message['content'],
							),
						),
					)
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'prompt_message_conversion_error', $e->getMessage() );
			}
		}

		return $prompt_messages;
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
