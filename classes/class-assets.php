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
		$asset_data = $this->get_asset_file( 'build/index' );

		wp_enqueue_script(
			'mind-editor',
			mind()->plugin_url . 'build/index.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		wp_enqueue_style(
			'mind-editor',
			mind()->plugin_url . 'build/style-index.css',
			[],
			$asset_data['version']
		);
	}
}

new Mind_Assets();
