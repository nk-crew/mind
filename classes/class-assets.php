<?php
/**
 * Plugin assets functions.
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mind Assets class.
 */
class Mind_Assets {
	/**
	 * Mind_Assets constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Loads the asset file for the given script or style.
	 * Returns a default if the asset file is not found.
	 *
	 * @param string $filepath The name of the file without the extension.
	 *
	 * @return array The asset file contents.
	 */
	public function get_asset_file( $filepath ) {
		$asset_path = mind()->plugin_path . $filepath . '.asset.php';

		if ( file_exists( $asset_path ) ) {
			return include $asset_path;
		}

		return [
			'dependencies' => [],
			'version'      => MIND_VERSION,
		];
	}

	/**
	 * Enqueue editor assets
	 */
	public function enqueue_block_editor_assets() {
		$settings = get_option( 'mind_settings', array() );

		$openai_key = $settings['openai_api_key'] ?? '';
		$asset_data = $this->get_asset_file( 'build/editor' );

		wp_enqueue_script(
			'mind-editor',
			mind()->plugin_url . 'build/editor.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		wp_localize_script(
			'mind-editor',
			'mindData',
			[
				'connected'       => ! ! $openai_key,
				'settingsPageURL' => admin_url( 'admin.php?page=mind&sub_page=settings' ),
			]
		);

		wp_enqueue_style(
			'mind-editor',
			mind()->plugin_url . 'build/style-editor.css',
			[],
			$asset_data['version']
		);
	}


	/**
	 * Enqueue admin pages assets.
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		if ( 'toplevel_page_mind' !== $screen->id ) {
			return;
		}

		$asset_data = $this->get_asset_file( 'build/admin' );

		wp_enqueue_script(
			'mind-admin',
			mind()->plugin_url . 'build/admin.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		wp_localize_script(
			'mind-admin',
			'mindAdminData',
			[
				'settings' => get_option( 'mind_settings', array() ),
			]
		);

		wp_enqueue_style(
			'mind-admin',
			mind()->plugin_url . 'build/style-admin.css',
			[],
			$asset_data['version']
		);
	}
}

new Mind_Assets();
