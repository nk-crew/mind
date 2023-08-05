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
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );

		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
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
			[ $this, 'print_admin_page' ],
			// phpcs:ignore
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( mind()->plugin_path . 'assets/images/admin-icon.svg' ) ),
			'58.7'
		);

		add_submenu_page(
			'mind',
			'',
			esc_html__( 'Welcome', 'mind' ),
			'manage_options',
			'mind'
		);
		add_submenu_page(
			'mind',
			'',
			esc_html__( 'Settings', 'mind' ),
			'manage_options',
			'admin.php?page=mind&sub_page=settings'
		);
	}

	/**
	 * Print admin page.
	 */
	public function print_admin_page() {
		?>
		<div class="mind-admin-root"></div>
		<?php
	}

	/**
	 * Add page class to body.
	 *
	 * @param string $classes - body classes.
	 */
	public function admin_body_class( $classes ) {
		$screen = get_current_screen();

		if ( 'toplevel_page_mind' !== $screen->id ) {
			return $classes;
		}

		$page_name = 'welcome';

        // phpcs:ignore
		if ( isset( $_GET['sub_page'] ) && $_GET['sub_page'] ) {
			// phpcs:ignore
			$page_name = $_GET['sub_page'];
		}

		$classes .= ' mind-admin-page mind-admin-page-' . esc_attr( $page_name );

		return $classes;
	}
}

new Mind_Admin();
