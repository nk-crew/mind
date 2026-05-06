<?php
/**
 * Plugin Name:       AI Mind
 * Description:       AI Page Builder powered by Anthropic and OpenAI. Build, design, improve, and rewrite your page sections and blocks.
 * Requires at least: 7.0
 * Requires PHP:      7.2
 * Version:           0.4.0
 * Plugin URI:        https://www.wp-mind.com/
 * Author:            Mind Team
 * Author URI:        https://www.wp-mind.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mind
 *
 * @package           mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MIND_VERSION' ) ) {
	define( 'MIND_VERSION', '0.4.0' );
}

if ( ! defined( 'MIND_SETTINGS_OPTION' ) ) {
	define( 'MIND_SETTINGS_OPTION', 'mind_settings' );
}

if ( ! defined( 'MIND_DB_VERSION_OPTION' ) ) {
	define( 'MIND_DB_VERSION_OPTION', 'mind_db_version' );
}

/**
 * Mind Class
 */
class Mind {
	/**
	 * Settings keys still persisted by the plugin UI.
	 *
	 * @var string[]
	 */
	private const SUPPORTED_SETTINGS_KEYS = array( 'ai_model' );

	/**
	 * Legacy credential keys kept only for cleanup.
	 *
	 * @var string[]
	 */
	private const LEGACY_SECRET_KEYS = array( 'openai_api_key', 'anthropic_api_key' );

	/**
	 * The single class instance.
	 *
	 * @var $instance
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
	 * Path to the plugin directory
	 *
	 * @var $plugin_path
	 */
	public $plugin_path;

	/**
	 * URL to the plugin directory
	 *
	 * @var $plugin_url
	 */
	public $plugin_url;

	/**
	 * Mind constructor.
	 */
	public function __construct() {
		/* We do nothing here! */
	}

	/**
	 * Init options
	 */
	public function init() {
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_url  = plugin_dir_url( __FILE__ );

		// include helper files.
		$this->include_dependencies();
		$this->maybe_upgrade();

		// hooks.
		add_action( 'init', [ $this, 'init_hook' ] );
	}

	/**
	 * Keep stored settings in sync with the current plugin schema.
	 *
	 * @return void
	 */
	private function maybe_upgrade() {
		$db_version = get_option( MIND_DB_VERSION_OPTION, '' );

		if ( version_compare( (string) $db_version, MIND_VERSION, '>=' ) ) {
			return;
		}

		$settings = get_option( MIND_SETTINGS_OPTION, array() );

		if ( is_array( $settings ) ) {
			$clean_settings = self::remove_legacy_secret_settings( $settings );

			if ( $clean_settings !== $settings ) {
				update_option( MIND_SETTINGS_OPTION, $clean_settings );
			}
		}

		update_option( MIND_DB_VERSION_OPTION, MIND_VERSION );
	}

	/**
	 * Include dependencies
	 */
	private function include_dependencies() {
		require_once $this->plugin_path . 'classes/class-prompts.php';
		require_once $this->plugin_path . 'classes/class-ai-api.php';
		require_once $this->plugin_path . 'classes/class-admin.php';
		require_once $this->plugin_path . 'classes/class-assets.php';
		require_once $this->plugin_path . 'classes/class-rest.php';
	}

	/**
	 * Init Hook
	 */
	public function init_hook() {
		// load textdomain.
		load_plugin_textdomain( 'mind', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Activation Hook
	 */
	public function activation_hook() {
		$this->maybe_upgrade();

		// Welcome Page Flag.
		set_transient( '_mind_welcome_screen_activation_redirect', true, 30 );
	}

	/**
	 * Get settings that are still part of the public plugin contract.
	 *
	 * @param mixed $settings Raw settings option value.
	 *
	 * @return array
	 */
	public static function get_supported_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		return array_intersect_key( $settings, array_flip( self::SUPPORTED_SETTINGS_KEYS ) );
	}

	/**
	 * Remove stale credential keys left from pre-connector versions.
	 *
	 * @param mixed $settings Raw settings option value.
	 *
	 * @return array
	 */
	public static function remove_legacy_secret_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		foreach ( self::LEGACY_SECRET_KEYS as $legacy_key ) {
			unset( $settings[ $legacy_key ] );
		}

		return $settings;
	}

	/**
	 * Deactivation Hook
	 */
	public function deactivation_hook() {
		// Nothing here yet.
	}
}

/**
 * Function works with the Mind class instance
 *
 * @return object Mind
 */
function mind() {
	return Mind::instance();
}
add_action( 'plugins_loaded', 'mind' );

register_activation_hook( __FILE__, [ mind(), 'activation_hook' ] );
register_deactivation_hook( __FILE__, [ mind(), 'deactivation_hook' ] );
