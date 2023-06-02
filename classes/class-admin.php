<?php
/**
 * Plugin admin functions.
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mind Admin class.
 */
class Mind_Admin {
	/**
	 * Mind_Admin constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );
	}

	/**
	 * Register admin menu.
	 *
	 * Add new Mind Settings admin menu.
	 */
	public function register_admin_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			esc_html__( 'Mind', 'mind' ),
			esc_html__( 'Mind', 'mind' ),
			'manage_options',
			'mind',
			array( 'Mind_Settings', 'print_settings_page' ),
			// phpcs:ignore
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( mind()->plugin_path . 'assets/images/admin-icon.svg' ) ),
			'58.7'
		);
	}
}

new Mind_Admin();
