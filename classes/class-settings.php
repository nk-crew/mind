<?php
/**
 * Plugin Settings
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once mind()->plugin_path . 'vendors/class-settings-api.php';

/**
 * Mind Settings Class
 */
class Mind_Settings {
	/**
	 * Settings API instance
	 *
	 * @var object
	 */
	public static $settings_api;

	/**
	 * Cached settings fields. We call settings fields method a lot of times to get default values.
	 * So, for performance reasons we need to cache the output.
	 *
	 * @var object
	 */
	public static $cached_settings_fields;

	/**
	 * Mind_Settings constructor.
	 */
	public function __construct() {
		self::init_actions();
	}

	/**
	 * Get Option Value
	 *
	 * @param string $option - option name.
	 * @param string $section - section name.
	 *
	 * @return bool|string
	 */
	public static function get_option( $option, $section ) {
		$options = get_option( $section );
		$result  = '';

		if ( isset( $options[ $option ] ) ) {
			$result = $options[ $option ];
		} else {
			// find default.
			$fields = self::get_settings_fields();

			if ( isset( $fields[ $section ] ) && is_array( $fields[ $section ] ) ) {
				foreach ( $fields[ $section ] as $field_data ) {
					if ( $option === $field_data['name'] && isset( $field_data['default'] ) ) {
						$result = $field_data['default'];
					}
				}
			}
		}

		return 'off' === $result ? false : ( 'on' === $result ? true : $result );
	}

	/**
	 * Update Option Value
	 *
	 * @param string $option - option name.
	 * @param string $section - section name.
	 * @param string $value - new option value.
	 */
	public static function update_option( $option, $section, $value ) {
		$options = get_option( $section );

		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$options[ $option ] = $value;

		update_option( $section, $options );
	}

	/**
	 * Init actions
	 */
	public static function init_actions() {
		self::$settings_api = new Mind_Settings_API();

		add_action( 'admin_init', [ __CLASS__, 'admin_init' ] );
	}

	/**
	 * Initialize the settings
	 *
	 * @return void
	 */
	public static function admin_init() {
		// set the settings.
		self::$settings_api->set_sections( self::get_settings_sections() );
		self::$settings_api->set_fields( self::get_settings_fields() );

		// initialize settings.
		self::$settings_api->admin_init();
	}

	/**
	 * Plugin settings sections
	 *
	 * @return array
	 */
	public static function get_settings_sections() {
		$sections = [
			[
				'id'    => 'mind_general',
				'title' => esc_html__( 'General', 'mind' ),
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>',
			],
		];

		return apply_filters( 'mind_settings_sections', $sections );
	}

	/**
	 * Returns all the settings fields
	 *
	 * @return array settings fields
	 */
	public static function get_settings_fields() {
		if ( ! empty( self::$cached_settings_fields ) ) {
			return self::$cached_settings_fields;
		}

		// retrieve openai key from the DB.
		// we can't use Mind Settings API as it will result a stack trace error.
		$openai_key       = false;
		$general_settings = get_option( 'mind_general' );
		if ( isset( $general_settings['openai_key'] ) ) {
			$openai_key = $general_settings['openai_key'];
		}

		$settings_fields = [
			'mind_general' => [
				[
					'name'    => 'openai_key',
					'label'   => esc_html__( 'OpenAI API Key', 'mind' ),
					'desc'    => esc_html__( 'This setting is required, since our plugin works with OpenAI.', 'mind' ) . ' <a href="https://platform.openai.com/account/api-keys" target="_blank">Create API key</a>',
					'type'    => $openai_key ? 'password' : 'text',
					'default' => '',
				],
			],
		];

		self::$cached_settings_fields = apply_filters( 'mind_settings_fields', $settings_fields );

		return self::$cached_settings_fields;
	}

	/**
	 * The plugin page handler
	 *
	 * @return void
	 */
	public static function print_settings_page() {
		self::$settings_api->admin_enqueue_scripts();

		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Settings', 'mind' ) . '</h2>';

		self::$settings_api->show_navigation();
		self::$settings_api->show_forms();

		echo '</div>';

		?>
		<script>
			(function( $ ) {
				// Don't allow adding input number values that > then max attribute and < min attribute.
				$('form').on('input', '[type="number"]', function(e) {
					var current = parseFloat( this.value );
					var min = parseFloat(this.min);
					var max = parseFloat(this.max);

					if ('' !== this.value) {
						if (!Number.isNaN(min) && current < min) {
							this.value = min;
						}
						if (!Number.isNaN(max) && current > max) {
							this.value = max;
						}
					}
				});

				<?php if ( ! class_exists( 'Mind_Pro' ) ) : ?>
					// disable pro inputs.
					$('.mind-settings-control-pro').find('input, textarea').attr('disabled', 'disabled');
				<?php endif; ?>
			})(jQuery);
		</script>
		<?php
	}
}

new Mind_Settings();
