<?php
/**
 * Rest API functions
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mind_Rest
 */
class Mind_Rest extends WP_REST_Controller {
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
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mind/v';

	/**
	 * Version.
	 *
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Mind_Rest constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		// Update Settings.
		register_rest_route(
			$namespace,
			'/update_settings/',
			[
				'methods'             => [ 'POST' ],
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'update_settings_permission' ],
			]
		);

		// Request OpenAI API.
		register_rest_route(
			$namespace,
			'/request_ai/',
			[
				'methods'             => [ 'GET', 'POST' ],
				'callback'            => [ $this, 'request_ai' ],
				'permission_callback' => [ $this, 'request_ai_permission' ],
			]
		);
	}

	/**
	 * Get edit options permissions.
	 *
	 * @return bool
	 */
	public function update_settings_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'user_dont_have_permission', __( 'User don\'t have permissions to change options.', 'mind' ), true );
		}

		return true;
	}

	/**
	 * Get permissions for OpenAI api request.
	 *
	 * @return bool
	 */
	public function request_ai_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $this->error( 'user_dont_have_permission', __( 'You don\'t have permissions to request Mind API.', 'mind' ), true );
		}

		return true;
	}

	/**
	 * Update Settings.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function update_settings( WP_REST_Request $req ) {
		$new_settings = $req->get_param( 'settings' );

		if ( is_array( $new_settings ) ) {
			$current_settings = get_option( 'mind_settings', [] );
			update_option( 'mind_settings', array_merge( $current_settings, $new_settings ) );
		}

		return $this->success( true );
	}

	/**
	 * Prepare messages for request.
	 *
	 * @param string $user_query user query.
	 * @param string $context context.
	 */
	public function prepare_messages( $user_query, $context ) {
		$messages = [];

		$messages[] = [
			'role'    => 'system',
			'content' => Mind_Prompts::get_system_prompt( $user_query, $context ),
		];

		// Optional blocks JSON context.
		if ( $context ) {
			$messages[] = [
				'role'    => 'user',
				'content' => '<context>' . $context . '</context>',
			];
		}

		// User Query.
		$messages[] = [
			'role'    => 'user',
			'content' => '<user_query>' . $user_query . '</user_query>',
		];

		return $messages;
	}

	/**
	 * Request OpenAI API.
	 *
	 * @param array $messages messages.
	 */
	public function request_open_ai( $messages ) {
		$settings   = get_option( 'mind_settings', array() );
		$openai_key = $settings['openai_api_key'] ?? '';

		if ( ! $openai_key ) {
			$this->send_stream_error( 'no_openai_key_found', __( 'Provide OpenAI key in the plugin settings.', 'mind' ) );
			exit;
		}

		$body = [
			'model'       => 'gpt-4o',
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
				'Authorization: Bearer ' . $openai_key,
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

				$this->process_stream_chunk( $data );

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
	 * Send request to OpenAI.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function request_ai( WP_REST_Request $req ) {
		// Set headers for streaming.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		ob_implicit_flush( true );
		ob_end_flush();

		$request = $req->get_param( 'request' ) ?? '';
		$context = $req->get_param( 'context' ) ?? '';

		if ( ! $request ) {
			$this->send_stream_error( 'no_request', __( 'Provide request to receive AI response.', 'mind' ) );
			exit;
		}

		$messages = $this->prepare_messages( $request, $context );

		$this->request_open_ai( $messages );

		exit;
	}

	/**
	 * Build base string
	 *
	 * @param string $base_uri - url.
	 * @param string $method - method.
	 * @param array  $params - params.
	 *
	 * @return string
	 */
	private function build_base_string( $base_uri, $method, $params ) {
		$r = [];
		ksort( $params );
		foreach ( $params as $key => $value ) {
			$r[] = "$key=" . rawurlencode( $value );
		}
		return $method . '&' . rawurlencode( $base_uri ) . '&' . rawurlencode( implode( '&', $r ) );
	}

	/**
	 * Process streaming chunk from OpenAI
	 *
	 * @param string $chunk - chunk of data.
	 */
	private function process_stream_chunk( $chunk ) {
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

	/**
	 * Success rest.
	 *
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function success( $response ) {
		return new WP_REST_Response(
			[
				'success'  => true,
				'response' => $response,
			],
			200
		);
	}

	/**
	 * Error rest.
	 *
	 * @param mixed   $code       error code.
	 * @param mixed   $response   response data.
	 * @param boolean $true_error use true error response to stop the code processing.
	 * @return mixed
	 */
	public function error( $code, $response, $true_error = false ) {
		if ( $true_error ) {
			return new WP_Error( $code, $response, [ 'status' => 401 ] );
		}

		return new WP_REST_Response(
			[
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			],
			401
		);
	}
}
new Mind_Rest();
