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
		add_action( 'admin_init', [ $this, 'redirect_to_welcome_screen' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );

		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
	}

	/**
	 * Redirect to Welcome page after activation.
	 */
	public function redirect_to_welcome_screen() {
		// Bail if no activation redirect.
		if ( ! get_transient( '_mind_welcome_screen_activation_redirect' ) ) {
			return;
		}

		// Delete the redirect transient.
		delete_transient( '_mind_welcome_screen_activation_redirect' );

		// Bail if activating from network, or bulk.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Redirect to welcome page.
		wp_safe_redirect( admin_url( 'admin.php?page=mind&is_first_loading=1' ) );
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
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
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
		add_submenu_page(
			'mind',
			'',
			esc_html__( 'Discussions', 'mind' ),
			'manage_options',
			'https://github.com/nk-crew/mind/discussions'
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

		$classes .= ' mind-admin-page';

		// Sub page.
		$page_name = 'welcome';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['sub_page'] ) && $_GET['sub_page'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$page_name = esc_attr( sanitize_text_field( $_GET['sub_page'] ) );
		}

		$classes .= ' mind-admin-page-' . $page_name;

		// Is first loading after plugin activation redirect.
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['is_first_loading'] ) ) {
			$classes .= ' mind-admin-first-loading';
		}

		return $classes;
	}
}

new Mind_Admin();
