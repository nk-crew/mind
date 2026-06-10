<?php
/**
 * Plugin AI stream functions.
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mind AI Stream class.
 */
class Mind_AI_Stream {
	/**
	 * Buffer for provider streaming response.
	 *
	 * @var string
	 */
	private $buffer = '';
	/**
	 * Buffer for partial provider SSE lines.
	 *
	 * @var string
	 */
	private $provider_stream_buffer = '';
	/**
	 * Last time the buffer was sent.
	 *
	 * @var float
	 */
	private $last_send_time = 0;
	/**
	 * Whether the provider stream has already sent the final event.
	 *
	 * @var bool
	 */
	private $stream_done = false;
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
	 * Execute the prompt through the WordPress AI Client and preserve the current SSE contract.
	 *
	 * @param array $model Connected model data.
	 * @param array $messages Prepared prompt messages.
	 *
	 * @return void
	 */
	public function execute( $model, $messages ) {
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

		if ( $this->request_provider_stream( $model, $messages, $registry ) ) {
			return;
		}

		// ai_gateway must use the curl streaming path; wp_ai_client hits the v3
		// language-model endpoint which does not stream and often times out.
		if ( 'ai_gateway' === $model['provider'] ) {
			$this->send_stream_error(
				'ai_gateway_stream_unavailable',
				__(
					'Mind could not stream through the Vercel AI Gateway. Check your AI Gateway API key in Settings → Connectors.',
					'mind'
				)
			);
			return;
		}

		$content = $builder->generate_text();

		if ( is_wp_error( $content ) ) {
			$this->send_stream_error( $content->get_error_code(), $content->get_error_message() );
			return;
		}

		if ( '' === trim( $content ) ) {
			$this->send_stream_error( 'empty_ai_response', __( 'The AI Client returned an empty response.', 'mind' ) );
			return;
		}

		$this->send_text_as_stream( $content );
		$this->send_stream_done();
	}
	/**
	 * Stream through provider APIs using credentials from WordPress Connectors.
	 *
	 * @param array  $model Connected model data.
	 * @param array  $messages Prepared prompt messages.
	 * @param object $registry AI Client provider registry.
	 *
	 * @return bool Whether the request was handled.
	 */
	private function request_provider_stream( $model, $messages, $registry ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return false;
		}

		$api_key = $this->get_provider_api_key( $model['provider'], $registry );

		if ( ! $api_key ) {
			return false;
		}

		$this->reset_stream_state();

		if ( 'openai' === $model['provider'] ) {
			$this->request_openai_stream( $model, $messages, $api_key );
			return true;
		}

		if ( 'anthropic' === $model['provider'] ) {
			$this->request_anthropic_stream( $model, $messages, $api_key );
			return true;
		}

		if ( 'ai_gateway' === $model['provider'] ) {
			$this->request_ai_gateway_stream( $model, $messages, $api_key, $registry );
			return true;
		}

		return false;
	}

	/**
	 * Format a model ID for the Vercel AI Gateway OpenAI-compatible API.
	 *
	 * Gateway models use provider/model (e.g. anthropic/claude-sonnet-4-6).
	 *
	 * @param array  $model Connected model data.
	 * @param object $registry AI Client provider registry.
	 *
	 * @return string
	 */
	private function format_ai_gateway_model_id( $model, $registry ) {
		$model_name = isset( $model['name'] ) ? (string) $model['name'] : '';

		if ( '' === $model_name ) {
			return '';
		}

		if ( false !== strpos( $model_name, '/' ) ) {
			return $model_name;
		}

		try {
			$exact_model = $registry->getProviderModel( $model['provider'], $model_name );

			if ( is_object( $exact_model ) && method_exists( $exact_model, 'getId' ) ) {
				$runtime_id = (string) $exact_model->getId();

				if ( false !== strpos( $runtime_id, '/' ) ) {
					return $runtime_id;
				}
			}
		} catch ( Throwable $e ) {
			unset( $e );
		}

		if ( 0 === strpos( $model_name, 'claude-' ) ) {
			return 'anthropic/' . $model_name;
		}

		if ( 0 === strpos( $model_name, 'gpt-' ) ) {
			return 'openai/' . $model_name;
		}

		if ( 0 === strpos( $model_name, 'gemini-' ) ) {
			return 'google/' . $model_name;
		}

		return $model_name;
	}

	/**
	 * Stream through the Vercel AI Gateway (OpenAI-compatible chat completions).
	 *
	 * @param array  $model Connected model data.
	 * @param array  $messages Prepared prompt messages.
	 * @param string $api_key Gateway API key.
	 * @param object $registry AI Client provider registry.
	 *
	 * @return void
	 */
	private function request_ai_gateway_stream( $model, $messages, $api_key, $registry ) {
		$gateway_model = $this->format_ai_gateway_model_id( $model, $registry );

		if ( '' === $gateway_model ) {
			$this->send_stream_error( 'ai_gateway_model_error', __( 'Could not resolve an AI Gateway model ID.', 'mind' ) );
			return;
		}

		$this->request_chat_completions_stream(
			'https://ai-gateway.vercel.sh/v1/chat/completions',
			$gateway_model,
			$messages,
			$api_key
		);
	}
	/**
	 * Get provider API key from the WordPress AI Client registry.
	 *
	 * @param string $provider_id Provider ID.
	 * @param object $registry AI Client provider registry.
	 *
	 * @return string
	 */
	private function get_provider_api_key( $provider_id, $registry ) {
		if ( ! method_exists( $registry, 'getProviderRequestAuthentication' ) ) {
			return Mind_AI_Settings::get_connector_api_key( $provider_id );
		}

		try {
			$authentication = $registry->getProviderRequestAuthentication( $provider_id );
		} catch ( Throwable $e ) {
			return Mind_AI_Settings::get_connector_api_key( $provider_id );
		}

		if ( ! $authentication || ! method_exists( $authentication, 'getApiKey' ) ) {
			return Mind_AI_Settings::get_connector_api_key( $provider_id );
		}

		$api_key = (string) $authentication->getApiKey();

		return $api_key ? $api_key : Mind_AI_Settings::get_connector_api_key( $provider_id );
	}
	/**
	 * Reset streaming state before a provider request.
	 *
	 * @return void
	 */
	private function reset_stream_state() {
		$this->buffer                 = '';
		$this->provider_stream_buffer = '';
		$this->last_send_time         = microtime( true );
		$this->stream_done            = false;
	}
	/**
	 * Request OpenAI-compatible streaming API.
	 *
	 * @param array  $model Connected model data.
	 * @param array  $messages Prepared prompt messages.
	 * @param string $api_key Provider API key.
	 *
	 * @return void
	 */
	private function request_openai_stream( $model, $messages, $api_key ) {
		$this->request_chat_completions_stream(
			'https://api.openai.com/v1/chat/completions',
			$model['name'],
			$messages,
			$api_key
		);
	}

	/**
	 * Stream via an OpenAI-compatible chat completions endpoint.
	 *
	 * @param string $endpoint API endpoint URL.
	 * @param string $model_id Model identifier for the request body.
	 * @param array  $messages Prepared prompt messages.
	 * @param string $api_key Bearer token.
	 *
	 * @return void
	 */
	private function request_chat_completions_stream( $endpoint, $model_id, $messages, $api_key ) {
		$body = array(
			'model'       => $model_id,
			'stream'      => true,
			'max_tokens'  => 8192,
			'temperature' => 0.7,
			'messages'    => $messages,
		);

		/* phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close */
		$ch = curl_init( $endpoint );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $api_key,
			)
		);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) {
				$this->process_openai_stream_chunk( $data );
				return strlen( $data );
			}
		);

		curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			$this->send_stream_error( 'curl_error', curl_error( $ch ) );
		} elseif ( ! $this->stream_done ) {
			$this->send_buffered_chunk();
			$this->send_stream_done();
		}

		curl_close( $ch );
		/* phpcs:enable */
	}
	/**
	 * Request Anthropic streaming API.
	 *
	 * @param array  $model Connected model data.
	 * @param array  $messages Prepared prompt messages.
	 * @param string $api_key Provider API key.
	 *
	 * @return void
	 */
	private function request_anthropic_stream( $model, $messages, $api_key ) {
		$anthropic_messages = $this->convert_to_anthropic_messages( $messages );
		$body               = array(
			'model'       => $model['name'],
			'max_tokens'  => 8192,
			'temperature' => 0.7,
			'system'      => $anthropic_messages['system'],
			'messages'    => $anthropic_messages['messages'],
			'stream'      => true,
		);

		/* phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close */
		$ch = curl_init( 'https://api.anthropic.com/v1/messages' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'x-api-key: ' . $api_key,
				'anthropic-version: 2023-06-01',
			)
		);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) {
				$this->process_anthropic_stream_chunk( $data );
				return strlen( $data );
			}
		);

		curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			$this->send_stream_error( 'curl_error', curl_error( $ch ) );
		} elseif ( ! $this->stream_done ) {
			$this->send_buffered_chunk();
			$this->send_stream_done();
		}

		curl_close( $ch );
		/* phpcs:enable */
	}
	/**
	 * Convert OpenAI messages format to Anthropic format.
	 *
	 * @param array $openai_messages OpenAI-style messages.
	 *
	 * @return array
	 */
	private function convert_to_anthropic_messages( $openai_messages ) {
		$system   = array();
		$messages = array();

		foreach ( $openai_messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$system[] = array(
					'type' => 'text',
					'text' => $message['content'],
				);
			} else {
				$messages[] = array(
					'role'    => 'assistant' === $message['role'] ? 'assistant' : 'user',
					'content' => $message['content'],
				);
			}
		}

		return array(
			'system'   => $system,
			'messages' => $messages,
		);
	}
	/**
	 * Process streaming chunk from OpenAI.
	 *
	 * @param string $chunk Chunk of provider data.
	 *
	 * @return void
	 */
	private function process_openai_stream_chunk( $chunk ) {
		$this->process_provider_stream_lines(
			$chunk,
			function ( $json_data ) {
				if ( '[DONE]' === $json_data ) {
					$this->send_buffered_chunk();
					$this->send_stream_done();
					return;
				}

				$data = json_decode( $json_data, true );

				if ( isset( $data['error']['message'] ) ) {
					$this->send_stream_error( 'openai_error', $data['error']['message'] );
					return;
				}

				if ( isset( $data['choices'][0]['delta']['content'] ) ) {
					$this->buffer_provider_content( $data['choices'][0]['delta']['content'] );
				}
			}
		);
	}
	/**
	 * Process streaming chunk from Anthropic.
	 *
	 * @param string $chunk Chunk of provider data.
	 *
	 * @return void
	 */
	private function process_anthropic_stream_chunk( $chunk ) {
		$this->process_provider_stream_lines(
			$chunk,
			function ( $json_data ) {
				$data = json_decode( $json_data, true );

				if ( isset( $data['error']['message'] ) ) {
					$this->send_stream_error( 'anthropic_error', $data['error']['message'] );
					return;
				}

				if ( isset( $data['type'] ) && 'content_block_delta' === $data['type'] && isset( $data['delta']['text'] ) ) {
					$this->buffer_provider_content( $data['delta']['text'] );
				} elseif ( isset( $data['type'] ) && 'message_stop' === $data['type'] ) {
					$this->send_buffered_chunk();
					$this->send_stream_done();
				}
			}
		);
	}
	/**
	 * Process provider SSE lines with buffering for partial chunks.
	 *
	 * @param string   $chunk Chunk of provider data.
	 * @param callable $callback Callback for each JSON payload.
	 *
	 * @return void
	 */
	private function process_provider_stream_lines( $chunk, $callback ) {
		$this->provider_stream_buffer .= $chunk;
		$lines                         = explode( "\n", $this->provider_stream_buffer );
		$this->provider_stream_buffer  = array_pop( $lines );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$json_data = 0 === strpos( $line, 'data: ' ) ? trim( substr( $line, 6 ) ) : $line;

			if ( '[DONE]' !== $json_data && '{' !== substr( $json_data, 0, 1 ) ) {
				continue;
			}

			if ( '' === $json_data ) {
				continue;
			}

			$callback( $json_data );
		}
	}
	/**
	 * Buffer provider content and flush it periodically.
	 *
	 * @param string $content Content delta.
	 *
	 * @return void
	 */
	private function buffer_provider_content( $content ) {
		if (
			false !== strpos( $content, '```json' ) ||
			false !== strpos( $content, '```' )
		) {
			$this->send_buffered_chunk();
			$this->send_stream_chunk( array( 'content' => $content ) );
			$this->last_send_time = microtime( true );
			return;
		}

		$this->buffer .= $content;

		if (
			strlen( $this->buffer ) >= self::BUFFER_THRESHOLD ||
			microtime( true ) - $this->last_send_time >= self::MIN_SEND_INTERVAL ||
			false !== strpos( $this->buffer, "\n" )
		) {
			$this->send_buffered_chunk();
		}
	}
	/**
	 * Send buffered provider content.
	 *
	 * @return void
	 */
	private function send_buffered_chunk() {
		if ( '' === $this->buffer ) {
			return;
		}

		$this->send_stream_chunk( array( 'content' => $this->buffer ) );
		$this->buffer         = '';
		$this->last_send_time = microtime( true );
	}
	/**
	 * Send non-streaming content in smaller SSE chunks.
	 *
	 * @param string $content AI response content.
	 *
	 * @return void
	 */
	private function send_text_as_stream( $content ) {
		if ( function_exists( 'mb_strcut' ) ) {
			$content_length = strlen( $content );

			for ( $offset = 0; $offset < $content_length; $offset += strlen( $chunk ) ) {
				$chunk = mb_strcut( $content, $offset, self::BUFFER_THRESHOLD, 'UTF-8' );

				if ( '' === $chunk ) {
					$chunk = substr( $content, $offset, self::BUFFER_THRESHOLD );
				}

				$this->send_stream_chunk( array( 'content' => $chunk ) );
			}

			return;
		}

		foreach ( $this->get_utf8_safe_chunks( $content ) as $chunk ) {
			$this->send_stream_chunk( array( 'content' => $chunk ) );
		}
	}
	/**
	 * Split UTF-8 content into SSE-safe chunks without relying on mbstring.
	 *
	 * @param string $content UTF-8 content.
	 *
	 * @return array
	 */
	private function get_utf8_safe_chunks( $content ) {
		if ( '' === $content ) {
			return array();
		}

		if ( ! preg_match_all( '/./us', $content, $matches ) ) {
			return str_split( $content, self::BUFFER_THRESHOLD );
		}

		$chunks        = array();
		$current_chunk = '';
		$current_bytes = 0;

		foreach ( $matches[0] as $character ) {
			$character_bytes = strlen( $character );

			if ( '' !== $current_chunk && $current_bytes + $character_bytes > self::BUFFER_THRESHOLD ) {
				$chunks[]      = $current_chunk;
				$current_chunk = '';
				$current_bytes = 0;
			}

			$current_chunk .= $character;
			$current_bytes += $character_bytes;
		}

		if ( '' !== $current_chunk ) {
			$chunks[] = $current_chunk;
		}

		return $chunks;
	}
	/**
	 * Send final stream event.
	 *
	 * @return void
	 */
	private function send_stream_done() {
		if ( $this->stream_done ) {
			return;
		}

		$this->stream_done = true;
		$this->send_stream_chunk( array( 'done' => true ) );
	}
	/**
	 * Convert prompt messages to the shape accepted by wp_ai_client_prompt().
	 *
	 * @param array $messages Prompt messages.
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
		$encoded_data = wp_json_encode( $data );

		if ( false === $encoded_data ) {
			$encoded_data = wp_json_encode(
				array(
					'error'   => true,
					'code'    => 'stream_encoding_error',
					'message' => 'Unable to encode stream payload.',
				)
			);

			if ( false === $encoded_data ) {
				return;
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE payload is encoded via wp_json_encode().
		echo 'data: ' . $encoded_data . "\n\n";

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
	public function send_stream_error( $code, $message ) {
		$this->send_stream_chunk(
			[
				'error'   => true,
				'code'    => $code,
				'message' => $message,
			]
		);
	}
}
