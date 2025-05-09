<?php
/**
 * Plugin Name:       AI Mind
 * Description:       AI Page Builder based on Anthropic and OpenAI. Build, design, improve, rewrite your page sections and blocks.
 * Requires at least: 6.5
 * Requires PHP:      7.2
 * Version:           0.3.0
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
	define( 'MIND_VERSION', '0.3.0' );
}

/**
 * Mind Class
 */
class Mind {
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

		// hooks.
		add_action( 'init', [ $this, 'init_hook' ] );
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
		// Welcome Page Flag.
		set_transient( '_mind_welcome_screen_activation_redirect', true, 30 );
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
