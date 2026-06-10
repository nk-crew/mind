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

		// Request AI API.
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
	 * Get permissions for AI API request.
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
			$current_settings = Mind::get_supported_settings( get_option( MIND_SETTINGS_OPTION, array() ) );
			$settings         = [];

			$ai_provider = isset( $new_settings['ai_provider'] )
				? sanitize_text_field( $new_settings['ai_provider'] )
				: ( $current_settings['ai_provider'] ?? '' );
			$ai_model    = isset( $new_settings['ai_model'] )
				? sanitize_text_field( $new_settings['ai_model'] )
				: ( $current_settings['ai_model'] ?? '' );

			if ( isset( $new_settings['ai_provider'] ) || isset( $new_settings['ai_model'] ) ) {
				if ( ! Mind_AI_Settings::is_valid_selection( $ai_provider, $ai_model ) ) {
					return $this->error( 'invalid_ai_model', __( 'The selected AI provider and model are not available in the current WordPress AI provider configuration.', 'mind' ), true );
				}

				$settings['ai_provider'] = $ai_provider;
				$settings['ai_model']    = $ai_model;
			}

			if ( $settings ) {
				update_option( MIND_SETTINGS_OPTION, array_merge( $current_settings, $settings ) );
			}
		}

		return $this->success( true );
	}

	/**
	 * Send request to AI API.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function request_ai( WP_REST_Request $req ) {
		$request         = $req->get_param( 'request' ) ?? '';
		$selected_blocks = $req->get_param( 'selected_blocks' ) ?? '';
		$page_blocks     = $req->get_param( 'page_blocks' ) ?? '';
		$page_context    = $req->get_param( 'page_context' ) ?? '';

		( new Mind_AI_Request() )->handle( $request, $selected_blocks, $page_blocks, $page_context );
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
