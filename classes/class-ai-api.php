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
	 * The same function placed in /utils/is-ai-connected/
	 *
	 * @return array|bool
	 */
	public function get_connected_model() {
		$settings = get_option( 'mind_settings', array() );
		$ai_model = $settings['ai_model'] ?? '';
		$result   = false;

		if ( $ai_model ) {
			if ( 'gpt-4o' === $ai_model || 'gpt-4o-mini' === $ai_model ) {
				if ( ! empty( $settings['openai_api_key'] ) ) {
					$result = [
						'name' => $ai_model,
						'key'  => $settings['openai_api_key'],
					];
				}
			} elseif ( ! empty( $settings['anthropic_api_key'] ) ) {
				$result = [
					'name' => 'claude-3-5-haiku' === $ai_model ? 'claude-3-5-haiku' : 'claude-3-5-sonnet',
					'key'  => $settings['anthropic_api_key'],
				];
			}
		}

		return $result;
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
			$this->send_stream_error( 'no_model_connected', __( 'Select an AI model and provide API key in the plugin settings.', 'mind' ) );
			exit;
		}

		$messages = $this->prepare_messages( $request, $selected_blocks, $page_blocks, $page_context );

		if ( 'gpt-4o' === $connected_model['name'] || 'gpt-4o-mini' === $connected_model['name'] ) {
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
		$model_name         = $model['name'];

		if ( 'claude-3-5-haiku' === $model['name'] ) {
			$model_name = 'claude-3-5-haiku-20241022';
		} else {
			$model_name = 'claude-3-5-sonnet-20241022';
		}

		$body = [
			'model'      => $model_name,
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
