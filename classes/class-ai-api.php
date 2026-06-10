<?php
/**
 * Plugin AI API functions.
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mind AI API class.
 */
class Mind_AI_API {
	/**
	 * Buffer for provider streaming response.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Buffer for partial provider SSE lines.
	 *
	 * @var string
	 */
	private $provider_stream_buffer = '';

	/**
	 * Last time the buffer was sent.
	 *
	 * @var float
	 */
	private $last_send_time = 0;

	/**
	 * Whether the provider stream has already sent the final event.
	 *
	 * @var bool
	 */
	private $stream_done = false;

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
	 * The single class instance.
	 *
	 * @var null
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
	 * Initialize the class.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Get connected model for runtime API requests.
	 *
	 * @return array{provider: string, name: string}|false
	 */
	public function get_connected_model() {
		$resolved = self::resolve_model_selection();

		if ( ! $resolved ) {
			return false;
		}

		return array(
			'provider' => $resolved['provider'],
			'name'     => $resolved['name'],
		);
	}

	/**
	 * Migrate legacy slot-based settings to provider + model fields.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return array
	 */
	public static function migrate_legacy_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		if ( ! array_key_exists( 'ai_provider', $settings ) && ! empty( $settings['ai_model'] ) ) {
			$settings['ai_provider'] = self::infer_provider_from_model( (string) $settings['ai_model'] );
		}

		if ( ! array_key_exists( 'ai_provider', $settings ) ) {
			$settings['ai_provider'] = '';
		}

		return $settings;
	}

	/**
	 * Get saved provider and model selection from options.
	 *
	 * @return array{provider: string, model: string}
	 */
	public static function get_saved_selection() {
		$settings = get_option( 'mind_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array(
			'provider' => isset( $settings['ai_provider'] ) ? (string) $settings['ai_provider'] : '',
			'model'    => isset( $settings['ai_model'] ) ? (string) $settings['ai_model'] : '',
		);
	}

	/**
	 * Preferred text models used when provider/model are set to Default.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function get_preferred_models_for_text_generation() {
		$preferred_models = array(
			array( 'anthropic', 'claude-sonnet-4-6' ),
			array( 'google', 'gemini-3-flash-preview' ),
			array( 'google', 'gemini-2.5-flash' ),
			array( 'openai', 'gpt-5.4-mini' ),
			array( 'openai', 'gpt-4.1-mini' ),
		);

		/**
		 * Filters the preferred models Mind uses when Default is selected.
		 *
		 * @param array<int, array{0: string, 1: string}> $preferred_models Preferred provider/model pairs.
		 */
		return (array) apply_filters( 'mind_preferred_text_models', $preferred_models );
	}

	/**
	 * Get registered AI provider connectors.
	 *
	 * @return array<string, array>
	 */
	public static function get_ai_connectors() {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return array();
		}

		$connectors = array();

		foreach ( (array) wp_get_connectors() as $connector_id => $data ) {
			if ( ! is_string( $connector_id ) || ! is_array( $data ) ) {
				continue;
			}

			$connector_type = $data['type'] ?? '';

			if ( 'ai_provider' !== $connector_type && ! self::is_provider_registered( $connector_id ) ) {
				continue;
			}

			$connectors[ $connector_id ] = $data;
		}

		return $connectors;
	}

	/**
	 * Get provider and model options for the settings UI.
	 *
	 * @return array
	 */
	public static function get_settings_options() {
		$resolved_default = self::resolve_model_selection( '', '' );
		$providers        = array(
			array(
				'id'    => '',
				'title' => self::format_default_option_title(
					$resolved_default ? self::get_provider_title( $resolved_default['provider'] ) : ''
				),
			),
		);
		$models           = array(
			'' => array(
				array(
					'id'    => '',
					'title' => self::format_default_option_title(
						$resolved_default ? self::format_model_title( $resolved_default['name'] ) : ''
					),
				),
			),
		);

		foreach ( self::get_connected_providers() as $provider ) {
			$providers[]       = array(
				'id'    => $provider['id'],
				'title' => $provider['title'],
			);
			$resolved_provider = self::resolve_provider_default_model( $provider['id'] );
			$provider_models   = array(
				array(
					'id'    => '',
					'title' => self::format_default_option_title(
						$resolved_provider ? self::format_model_title( $resolved_provider['name'] ) : ''
					),
				),
			);

			foreach ( $provider['models'] as $model ) {
				$provider_models[] = array(
					'id'    => $model['name'],
					'title' => $model['title'],
				);
			}

			$models[ $provider['id'] ] = $provider_models;
		}

		return array(
			'providers' => $providers,
			'models'    => $models,
		);
	}

	/**
	 * Format a Default option label with the resolved value in parentheses.
	 *
	 * @param string $resolved_title Resolved provider or model title.
	 *
	 * @return string
	 */
	private static function format_default_option_title( $resolved_title ) {
		$resolved_title = is_string( $resolved_title ) ? trim( $resolved_title ) : '';

		if ( '' === $resolved_title ) {
			return __( 'Default', 'mind' );
		}

		return sprintf(
			/* translators: %s: resolved provider or model title. */
			__( 'Default (%s)', 'mind' ),
			$resolved_title
		);
	}

	/**
	 * Check whether a saved provider/model pair can be used for runtime requests.
	 *
	 * @param string $provider_id Provider ID.
	 * @param string $model_name Model ID.
	 *
	 * @return bool
	 */
	public static function is_valid_selection( $provider_id, $model_name ) {
		$provider_id = is_string( $provider_id ) ? $provider_id : '';
		$model_name  = is_string( $model_name ) ? $model_name : '';

		if ( '' === $provider_id && '' === $model_name ) {
			return true;
		}

		return false !== self::resolve_model_selection( $provider_id, $model_name );
	}

	/**
	 * Get explicit setup state for editor and admin UI.
	 *
	 * @return array
	 */
	public static function get_setup_state() {
		$selection      = self::get_saved_selection();
		$resolved       = self::resolve_model_selection();
		$has_providers  = self::has_any_connected_provider();
		$uses_default   = '' === $selection['provider'] && '' === $selection['model'];
		$is_explicit    = ! $uses_default;
		$approvals_page = 'tools.php?page=ai-connector-approval';

		return array(
			'connected'               => false !== $resolved,
			'hasValidSelectedModel'   => false !== $resolved,
			'needsProviderConnection' => ! $has_providers,
			'needsModelSelection'     => $has_providers && $is_explicit && false === $resolved,
			'usesDefault'             => $uses_default,
			'selectedProvider'        => $selection['provider'],
			'selectedModelName'       => $selection['model'],
			'resolvedProvider'        => $resolved['provider'] ?? '',
			'resolvedModelName'       => $resolved['name'] ?? '',
			'resolvedProviderTitle'   => $resolved ? self::get_provider_title( $resolved['provider'] ) : '',
			'resolvedModelTitle'      => $resolved ? self::format_model_title( $resolved['name'] ) : '',
			'canManageConnectors'     => current_user_can( 'manage_options' ),
			'connectorApprovalsURL'   => admin_url( $approvals_page ),
		);
	}

	/**
	 * Resolve the runtime provider/model for the current or supplied selection.
	 *
	 * @param string|null $provider_id Provider ID.
	 * @param string|null $model_name Model ID.
	 *
	 * @return array{provider: string, name: string, isDefault: bool}|false
	 */
	public static function resolve_model_selection( $provider_id = null, $model_name = null ) {
		$selection = self::get_saved_selection();

		if ( null === $provider_id ) {
			$provider_id = $selection['provider'];
		}

		if ( null === $model_name ) {
			$model_name = $selection['model'];
		}

		$provider_id = is_string( $provider_id ) ? $provider_id : '';
		$model_name  = is_string( $model_name ) ? $model_name : '';

		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return false;
		}

		if ( '' === $provider_id && '' === $model_name ) {
			return self::resolve_default_model();
		}

		if ( '' !== $provider_id && '' === $model_name ) {
			return self::resolve_provider_default_model( $provider_id );
		}

		if ( '' === $provider_id && '' !== $model_name ) {
			$runtime_name = self::get_runtime_model_name( $model_name );

			foreach ( self::get_connected_providers() as $provider ) {
				foreach ( $provider['models'] as $model ) {
					if ( $model['name'] !== $model_name && self::get_runtime_model_name( $model['name'] ) !== $runtime_name ) {
						continue;
					}

					$resolved = self::resolve_model_selection( $provider['id'], $model['name'] );

					if ( $resolved ) {
						return $resolved;
					}
				}
			}

			$inferred_provider = self::infer_provider_from_model( $model_name );

			if ( $inferred_provider ) {
				return self::resolve_model_selection( $inferred_provider, $model_name );
			}

			return false;
		}

		$runtime_name = self::get_runtime_model_name( $model_name );

		if ( ! self::can_resolve_runtime_model( $provider_id, $runtime_name ) ) {
			return false;
		}

		return array(
			'provider'  => $provider_id,
			'name'      => $runtime_name,
			'isDefault' => false,
		);
	}

	/**
	 * Resolve the default preferred model from connected providers.
	 *
	 * @return array{provider: string, name: string, isDefault: bool}|false
	 */
	private static function resolve_default_model() {
		foreach ( self::get_preferred_models_for_text_generation() as $preferred_model ) {
			$resolved = self::resolve_preferred_model_pair( $preferred_model );

			if ( $resolved ) {
				return $resolved;
			}
		}

		foreach ( self::get_connected_providers() as $provider ) {
			$resolved = self::resolve_provider_default_model( $provider['id'] );

			if ( $resolved ) {
				return $resolved;
			}
		}

		return false;
	}

	/**
	 * Resolve a preferred provider/model pair when available.
	 *
	 * @param mixed $preferred_model Preferred provider/model pair.
	 *
	 * @return array{provider: string, name: string, isDefault: bool}|false
	 */
	private static function resolve_preferred_model_pair( $preferred_model ) {
		if ( ! is_array( $preferred_model ) || count( $preferred_model ) < 2 ) {
			return false;
		}

		$provider_id  = (string) $preferred_model[0];
		$runtime_name = self::get_runtime_model_name( (string) $preferred_model[1] );

		if ( ! self::can_resolve_runtime_model( $provider_id, $runtime_name ) ) {
			return false;
		}

		return array(
			'provider'  => $provider_id,
			'name'      => $runtime_name,
			'isDefault' => true,
		);
	}

	/**
	 * Resolve the default model for a specific provider.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return array{provider: string, name: string, isDefault: bool}|false
	 */
	private static function resolve_provider_default_model( $provider_id ) {
		$provider_id = is_string( $provider_id ) ? $provider_id : '';

		if ( '' === $provider_id || ! self::is_connector_connected( $provider_id ) ) {
			return false;
		}

		foreach ( self::get_preferred_models_for_text_generation() as $preferred_model ) {
			if ( ! is_array( $preferred_model ) || count( $preferred_model ) < 2 ) {
				continue;
			}

			if ( $preferred_model[0] !== $provider_id ) {
				continue;
			}

			$resolved = self::resolve_preferred_model_pair( $preferred_model );

			if ( $resolved ) {
				return $resolved;
			}
		}

		return self::resolve_first_available_model( $provider_id );
	}

	/**
	 * Resolve the first runtime-available text model for a provider.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return array{provider: string, name: string, isDefault: bool}|false
	 */
	private static function resolve_first_available_model( $provider_id ) {
		foreach ( self::get_provider_models( $provider_id ) as $model ) {
			$runtime_name = self::get_runtime_model_name( $model['name'] );

			if ( ! self::can_resolve_runtime_model( $provider_id, $runtime_name ) ) {
				continue;
			}

			return array(
				'provider'  => $provider_id,
				'name'      => $runtime_name,
				'isDefault' => true,
			);
		}

		return false;
	}

	/**
	 * Check whether any AI provider connector is configured.
	 *
	 * @return bool
	 */
	public static function has_any_connected_provider() {
		if ( ! empty( self::get_connected_providers() ) ) {
			return true;
		}

		foreach ( self::get_registered_provider_ids() as $provider_id ) {
			if ( self::is_connector_connected( $provider_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get connected providers and their models for the settings UI.
	 *
	 * @return array<int, array{id: string, title: string, models: array}>
	 */
	public static function get_connected_providers() {
		$providers = array();
		$seen      = array();

		foreach ( self::get_ai_connectors() as $connector_id => $connector_data ) {
			if ( ! self::is_connector_connected( $connector_id ) ) {
				continue;
			}

			$models = self::get_provider_models( $connector_id );

			if ( empty( $models ) ) {
				continue;
			}

			$seen[ $connector_id ] = true;
			$providers[]           = array(
				'id'     => $connector_id,
				'title'  => self::get_provider_title( $connector_id, $connector_data ),
				'models' => $models,
			);
		}

		foreach ( self::get_registered_provider_ids() as $provider_id ) {
			if ( isset( $seen[ $provider_id ] ) || ! self::is_connector_connected( $provider_id ) ) {
				continue;
			}

			$models = self::get_provider_models( $provider_id );

			if ( empty( $models ) ) {
				continue;
			}

			$providers[] = array(
				'id'     => $provider_id,
				'title'  => self::get_provider_title( $provider_id ),
				'models' => $models,
			);
		}

		return $providers;
	}

	/**
	 * Get provider IDs registered in the WordPress AI Client.
	 *
	 * @return string[]
	 */
	private static function get_registered_provider_ids() {
		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return array();
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			if ( method_exists( $registry, 'getRegisteredProviderIds' ) ) {
				return array_map( 'strval', $registry->getRegisteredProviderIds() );
			}
		} catch ( Throwable $e ) {
			unset( $e );
		}

		return array();
	}

	/**
	 * Get a human-readable provider title.
	 *
	 * @param string     $provider_id Provider ID.
	 * @param array|null $connector_data Connector metadata.
	 *
	 * @return string
	 */
	private static function get_provider_title( $provider_id, $connector_data = null ) {
		if ( is_array( $connector_data ) ) {
			foreach ( array( 'title', 'label', 'name' ) as $key ) {
				if ( ! empty( $connector_data[ $key ] ) && is_string( $connector_data[ $key ] ) ) {
					return $connector_data[ $key ];
				}
			}
		}

		if ( function_exists( 'wp_get_connector' ) ) {
			$connector = wp_get_connector( $provider_id );

			if ( is_array( $connector ) ) {
				foreach ( array( 'title', 'label', 'name' ) as $key ) {
					if ( ! empty( $connector[ $key ] ) && is_string( $connector[ $key ] ) ) {
						return $connector[ $key ];
					}
				}
			}
		}

		return self::format_model_title( $provider_id );
	}

	/**
	 * Infer provider ID from a legacy model name.
	 *
	 * @param string $model_name Model ID.
	 *
	 * @return string
	 */
	private static function infer_provider_from_model( $model_name ) {
		if ( 0 === strpos( $model_name, 'claude-' ) ) {
			return 'anthropic';
		}

		if ( 0 === strpos( $model_name, 'gpt-' ) ) {
			return 'openai';
		}

		if ( 0 === strpos( $model_name, 'gemini-' ) ) {
			return 'google';
		}

		return '';
	}

	/**
	 * Check whether a model can be resolved by the provider registry.
	 *
	 * @param string $provider_id Provider ID.
	 * @param string $model_name Runtime model name.
	 *
	 * @return bool
	 */
	private static function can_resolve_runtime_model( $provider_id, $model_name ) {
		if ( ! $provider_id || ! $model_name || ! self::is_connector_connected( $provider_id ) ) {
			return false;
		}

		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return false;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			$registry->getProviderModel( $provider_id, $model_name );

			return true;
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Resolve a saved model name to the provider runtime model ID.
	 *
	 * @param string $model_name Saved model name.
	 *
	 * @return string
	 */
	private static function get_runtime_model_name( $model_name ) {
		$model_name = is_string( $model_name ) ? $model_name : '';

		if ( '' === $model_name ) {
			return '';
		}

		$legacy_model_names = array(
			'claude-3-7-sonnet' => 'claude-sonnet-3-7',
			'claude-3-7-haiku'  => 'claude-haiku-3-7',
		);

		return $legacy_model_names[ $model_name ] ?? $model_name;
	}

	/**
	 * Get AI Client models for a provider.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return array
	 */
	private static function get_provider_models( $provider_id ) {
		if (
			! self::is_connector_connected( $provider_id ) ||
			! class_exists( '\WordPress\AiClient\AiClient' )
		) {
			return array();
		}

		try {
			$registry       = \WordPress\AiClient\AiClient::defaultRegistry();
			$provider_class = $registry->getProviderClassName( $provider_id );
			$model_dir      = $provider_class::modelMetadataDirectory();
			$models         = array();

			foreach ( $model_dir->listModelMetadata() as $model_metadata ) {
				$model = self::normalize_model_metadata( $model_metadata, $provider_id );

				if ( $model && self::model_supports_text_generation( $model_metadata ) ) {
					$models[] = $model;
				}
			}

			return $models;
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/**
	 * Normalize AI Client model metadata for the admin UI.
	 *
	 * @param object $model_metadata Model metadata.
	 * @param string $provider_id Provider ID.
	 *
	 * @return array|null
	 */
	private static function normalize_model_metadata( $model_metadata, $provider_id ) {
		if ( ! is_object( $model_metadata ) || ! method_exists( $model_metadata, 'getId' ) ) {
			return null;
		}

		$model_id   = (string) $model_metadata->getId();
		$model_name = method_exists( $model_metadata, 'getName' ) ? (string) $model_metadata->getName() : $model_id;
		$deprecated = self::get_model_deprecation_data( $model_metadata );

		return array(
			'name'             => $model_id,
			'title'            => self::format_model_title( $model_name ),
			'provider'         => $provider_id,
			'available'        => true,
			'runtimeAvailable' => true,
			'deprecated'       => $deprecated['deprecated'],
			'deprecationDate'  => $deprecated['date'],
		);
	}

	/**
	 * Check if model supports text generation.
	 *
	 * @param object $model_metadata Model metadata.
	 *
	 * @return bool
	 */
	private static function model_supports_text_generation( $model_metadata ) {
		if ( ! method_exists( $model_metadata, 'getSupportedCapabilities' ) ) {
			return true;
		}

		foreach ( $model_metadata->getSupportedCapabilities() as $capability ) {
			$value = '';

			if (
				is_object( $capability ) &&
				method_exists( $capability, 'isTextGeneration' ) &&
				$capability->isTextGeneration()
			) {
				return true;
			}

			if ( is_object( $capability ) ) {
				try {
					$value = $capability->value;
				} catch ( Throwable $e ) {
					$value = '';
				}
			} elseif ( is_string( $capability ) ) {
				$value = $capability;
			}

			if ( 'text_generation' === $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Format model title.
	 *
	 * @param string $model_name Model name.
	 *
	 * @return string
	 */
	private static function format_model_title( $model_name ) {
		$model_name = preg_replace( '/-[0-9]{8}$/', '', $model_name );
		$model_name = preg_replace( '/(?<=\d)-(?=\d)/', '.', $model_name );
		$model_name = str_replace( array( '-', '_' ), ' ', $model_name );
		$model_name = preg_replace( '/\bgpt\b/i', 'GPT', $model_name );
		$model_name = ucwords( $model_name );
		$model_name = preg_replace( '/\bGpt\b/', 'GPT', $model_name );

		return $model_name;
	}

	/**
	 * Extract future deprecation metadata if the AI Client exposes it.
	 *
	 * @param object $model_metadata Model metadata.
	 *
	 * @return array
	 */
	private static function get_model_deprecation_data( $model_metadata ) {
		$deprecated = false;
		$date       = '';
		$data       = method_exists( $model_metadata, 'toArray' ) ? (array) $model_metadata->toArray() : array();

		foreach ( array( 'deprecated', 'isDeprecated' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$deprecated = (bool) $data[ $key ];
			}
		}

		if ( method_exists( $model_metadata, 'isDeprecated' ) ) {
			$deprecated = (bool) $model_metadata->isDeprecated();
		}

		foreach ( array( 'deprecationDate', 'deprecation_date', 'sunsetDate', 'sunset_date', 'retirementDate', 'retirement_date' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$date = self::format_model_deprecation_date( $data[ $key ] );
				break;
			}
		}

		foreach ( array( 'getDeprecationDate', 'getSunsetDate', 'getRetirementDate' ) as $method ) {
			if ( method_exists( $model_metadata, $method ) ) {
				$date_value = $model_metadata->$method();

				if ( $date_value ) {
					$date = self::format_model_deprecation_date( $date_value );
					break;
				}
			}
		}

		return array(
			'deprecated' => $deprecated,
			'date'       => $date,
		);
	}

	/**
	 * Format model deprecation date.
	 *
	 * @param mixed $date Date value.
	 *
	 * @return string
	 */
	private static function format_model_deprecation_date( $date ) {
		if ( $date instanceof DateTimeInterface ) {
			return $date->format( 'Y-m-d' );
		}

		return is_scalar( $date ) ? (string) $date : '';
	}

	/**
	 * Get connector authentication config.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return array
	 */
	private static function get_connector_authentication( $provider_id ) {
		if ( ! function_exists( 'wp_get_connector' ) ) {
			return array();
		}

		$connector = wp_get_connector( $provider_id );

		if ( ! is_array( $connector ) || empty( $connector['authentication'] ) || ! is_array( $connector['authentication'] ) ) {
			return array();
		}

		return $connector['authentication'];
	}

	/**
	 * Get connector API key.
	 *
	 * @since 0.4.0
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return string
	 */
	public static function get_connector_api_key( $provider_id ) {
		$authentication = self::get_connector_authentication( $provider_id );

		if (
			empty( $authentication ) ||
			! isset( $authentication['method'] ) ||
			'api_key' !== $authentication['method'] ||
			empty( $authentication['setting_name'] )
		) {
			return '';
		}

		if ( ! empty( $authentication['env_var_name'] ) ) {
			$env_value = getenv( $authentication['env_var_name'] );

			if ( false !== $env_value && '' !== $env_value ) {
				return (string) $env_value;
			}
		}

		if ( ! empty( $authentication['constant_name'] ) && defined( $authentication['constant_name'] ) ) {
			$constant_value = constant( $authentication['constant_name'] );

			if ( is_string( $constant_value ) && '' !== $constant_value ) {
				return $constant_value;
			}
		}

		return (string) get_option( $authentication['setting_name'], '' );
	}

	/**
	 * Check if provider is registered in the WordPress AI Client.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return bool
	 */
	public static function is_provider_registered( $provider_id ) {
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return false;
		}

		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return false;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			return $registry->hasProvider( $provider_id );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if provider is available and has a configured API key.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return bool
	 */
	public static function is_connector_connected( $provider_id ) {
		if ( ! self::is_provider_registered( $provider_id ) ) {
			return false;
		}

		if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
			try {
				$registry = \WordPress\AiClient\AiClient::defaultRegistry();

				if ( $registry->hasProvider( $provider_id ) ) {
					return $registry->isProviderConfigured( $provider_id );
				}
			} catch ( Exception $e ) {
				// Fall back to local connector credential resolution below.
				unset( $e );
			}
		}

		return '' !== self::get_connector_api_key( $provider_id );
	}

	/**
	 * Send request to API.
	 *
	 * @param string $request request text.
	 * @param string $selected_blocks selected blocks context.
	 * @param string $page_blocks page blocks context.
	 * @param string $page_context page context.
	 *
	 * @return mixed
	 */
	public function request( $request, $selected_blocks = '', $page_blocks = '', $page_context = '' ) {
		// Set headers for streaming.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		ob_implicit_flush( true );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		if ( ! $request ) {
			$this->send_stream_error( 'no_request', __( 'Provide request to receive AI response.', 'mind' ) );
			exit;
		}

		$connected_model = $this->get_connected_model();

		if ( ! $connected_model ) {
			$this->send_stream_error(
				'no_model_connected',
				__(
					'Mind could not resolve an AI provider and model. Connect a provider in WordPress Connectors or choose one in Mind settings.',
					'mind'
				)
			);
			exit;
		}

		$messages = $this->prepare_messages( $request, $selected_blocks, $page_blocks, $page_context );

		$this->request_ai_client( $connected_model, $messages );

		exit;
	}

	/**
	 * Prepare messages for request.
	 *
	 * @param string $user_query user query.
	 * @param string $selected_blocks selected blocks context.
	 * @param string $page_blocks page blocks context.
	 * @param string $page_context page context.
	 */
	public function prepare_messages( $user_query, $selected_blocks, $page_blocks, $page_context ) {
		$user_query = '<user_query>' . $user_query . '</user_query>';

		if ( $selected_blocks ) {
			$user_query .= "\n";
			$user_query .= '<selected_blocks_context>' . $selected_blocks . '</selected_blocks_context>';
		}
		if ( $page_blocks ) {
			$user_query .= "\n";
			$user_query .= '<page_blocks_context>' . $page_blocks . '</page_blocks_context>';
		}
		if ( $page_context ) {
			$user_query .= "\n";
			$user_query .= '<page_context>' . $page_context . '</page_context>';
		}

		return [
			[
				'role'    => 'system',
				'content' => Mind_Prompts::get_system_prompt(),
			],
			[
				'role'    => 'user',
				'content' => $user_query,
			],
		];
	}

	/**
	 * Execute the prompt through the WordPress AI Client and preserve the current SSE contract.
	 *
	 * @param array $model Connected model data.
	 * @param array $messages Prepared prompt messages.
	 *
	 * @return void
	 */
	private function request_ai_client( $model, $messages ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->send_stream_error( 'ai_client_unavailable', __( 'The WordPress AI Client is not available in this environment.', 'mind' ) );
			return;
		}

		$prompt_messages = $this->get_ai_client_messages( $messages );

		if ( is_wp_error( $prompt_messages ) ) {
			$this->send_stream_error( $prompt_messages->get_error_code(), $prompt_messages->get_error_message() );
			return;
		}

		$builder = wp_ai_client_prompt( $prompt_messages );

		try {
			$registry    = \WordPress\AiClient\AiClient::defaultRegistry();
			$exact_model = $registry->getProviderModel( $model['provider'], $model['name'] );
		} catch ( Exception $e ) {
			$this->send_stream_error( 'model_resolution_error', $e->getMessage() );
			return;
		}

		if ( ! empty( $messages[0]['role'] ) && 'system' === $messages[0]['role'] && ! empty( $messages[0]['content'] ) ) {
			$builder->using_system_instruction( $messages[0]['content'] );
		}

		$builder
			->using_model( $exact_model )
			->using_max_tokens( 8192 )
			->using_temperature( 0.7 );

		if ( $this->request_ai_client_stream( $model, $messages, $registry ) ) {
			return;
		}

		$content = $builder->generate_text();

		if ( is_wp_error( $content ) ) {
			$this->send_stream_error( $content->get_error_code(), $content->get_error_message() );
			return;
		}

		if ( '' === trim( $content ) ) {
			$this->send_stream_error( 'empty_ai_response', __( 'The AI Client returned an empty response.', 'mind' ) );
			return;
		}

		$this->send_text_as_stream( $content );
		$this->send_stream_done();
	}

	/**
	 * Stream through provider APIs using credentials from WordPress Connectors.
	 *
	 * @param array  $model Connected model data.
	 * @param array  $messages Prepared prompt messages.
	 * @param object $registry AI Client provider registry.
	 *
	 * @return bool Whether the request was handled.
	 */
	private function request_ai_client_stream( $model, $messages, $registry ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return false;
		}

		$api_key = $this->get_provider_api_key( $model['provider'], $registry );

		if ( ! $api_key ) {
			return false;
		}

		$this->reset_stream_state();

		if ( 'openai' === $model['provider'] ) {
			$this->request_openai_stream( $model, $messages, $api_key );
			return true;
		}

		if ( 'anthropic' === $model['provider'] ) {
			$this->request_anthropic_stream( $model, $messages, $api_key );
			return true;
		}

		return false;
	}

	/**
	 * Get provider API key from the WordPress AI Client registry.
	 *
	 * @param string $provider_id Provider ID.
	 * @param object $registry AI Client provider registry.
	 *
	 * @return string
	 */
	private function get_provider_api_key( $provider_id, $registry ) {
		if ( ! method_exists( $registry, 'getProviderRequestAuthentication' ) ) {
			return self::get_connector_api_key( $provider_id );
		}

		try {
			$authentication = $registry->getProviderRequestAuthentication( $provider_id );
		} catch ( Throwable $e ) {
			return self::get_connector_api_key( $provider_id );
		}

		if ( ! $authentication || ! method_exists( $authentication, 'getApiKey' ) ) {
			return self::get_connector_api_key( $provider_id );
		}

		$api_key = (string) $authentication->getApiKey();

		return $api_key ? $api_key : self::get_connector_api_key( $provider_id );
	}

	/**
	 * Reset streaming state before a provider request.
	 *
	 * @return void
	 */
	private function reset_stream_state() {
		$this->buffer                 = '';
		$this->provider_stream_buffer = '';
		$this->last_send_time         = microtime( true );
		$this->stream_done            = false;
	}

	/**
	 * Request OpenAI-compatible streaming API.
	 *
	 * @param array  $model Connected model data.
	 * @param array  $messages Prepared prompt messages.
	 * @param string $api_key Provider API key.
	 *
	 * @return void
	 */
	private function request_openai_stream( $model, $messages, $api_key ) {
		$body = array(
			'model'       => $model['name'],
			'stream'      => true,
			'max_tokens'  => 8192,
			'temperature' => 0.7,
			'messages'    => $messages,
		);

		/* phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close */
		$ch = curl_init( 'https://api.openai.com/v1/chat/completions' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $api_key,
			)
		);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) {
				$this->process_openai_stream_chunk( $data );
				return strlen( $data );
			}
		);

		curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			$this->send_stream_error( 'curl_error', curl_error( $ch ) );
		} elseif ( ! $this->stream_done ) {
			$this->send_buffered_chunk();
			$this->send_stream_done();
		}

		curl_close( $ch );
		/* phpcs:enable */
	}

	/**
	 * Request Anthropic streaming API.
	 *
	 * @param array  $model Connected model data.
	 * @param array  $messages Prepared prompt messages.
	 * @param string $api_key Provider API key.
	 *
	 * @return void
	 */
	private function request_anthropic_stream( $model, $messages, $api_key ) {
		$anthropic_messages = $this->convert_to_anthropic_messages( $messages );
		$body               = array(
			'model'       => $model['name'],
			'max_tokens'  => 8192,
			'temperature' => 0.7,
			'system'      => $anthropic_messages['system'],
			'messages'    => $anthropic_messages['messages'],
			'stream'      => true,
		);

		/* phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close */
		$ch = curl_init( 'https://api.anthropic.com/v1/messages' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'x-api-key: ' . $api_key,
				'anthropic-version: 2023-06-01',
			)
		);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) {
				$this->process_anthropic_stream_chunk( $data );
				return strlen( $data );
			}
		);

		curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			$this->send_stream_error( 'curl_error', curl_error( $ch ) );
		} elseif ( ! $this->stream_done ) {
			$this->send_buffered_chunk();
			$this->send_stream_done();
		}

		curl_close( $ch );
		/* phpcs:enable */
	}

	/**
	 * Convert OpenAI messages format to Anthropic format.
	 *
	 * @param array $openai_messages OpenAI-style messages.
	 *
	 * @return array
	 */
	private function convert_to_anthropic_messages( $openai_messages ) {
		$system   = array();
		$messages = array();

		foreach ( $openai_messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$system[] = array(
					'type' => 'text',
					'text' => $message['content'],
				);
			} else {
				$messages[] = array(
					'role'    => 'assistant' === $message['role'] ? 'assistant' : 'user',
					'content' => $message['content'],
				);
			}
		}

		return array(
			'system'   => $system,
			'messages' => $messages,
		);
	}

	/**
	 * Process streaming chunk from OpenAI.
	 *
	 * @param string $chunk Chunk of provider data.
	 *
	 * @return void
	 */
	private function process_openai_stream_chunk( $chunk ) {
		$this->process_provider_stream_lines(
			$chunk,
			function ( $json_data ) {
				if ( '[DONE]' === $json_data ) {
					$this->send_buffered_chunk();
					$this->send_stream_done();
					return;
				}

				$data = json_decode( $json_data, true );

				if ( isset( $data['error']['message'] ) ) {
					$this->send_stream_error( 'openai_error', $data['error']['message'] );
					return;
				}

				if ( isset( $data['choices'][0]['delta']['content'] ) ) {
					$this->buffer_provider_content( $data['choices'][0]['delta']['content'] );
				}
			}
		);
	}

	/**
	 * Process streaming chunk from Anthropic.
	 *
	 * @param string $chunk Chunk of provider data.
	 *
	 * @return void
	 */
	private function process_anthropic_stream_chunk( $chunk ) {
		$this->process_provider_stream_lines(
			$chunk,
			function ( $json_data ) {
				$data = json_decode( $json_data, true );

				if ( isset( $data['error']['message'] ) ) {
					$this->send_stream_error( 'anthropic_error', $data['error']['message'] );
					return;
				}

				if ( isset( $data['type'] ) && 'content_block_delta' === $data['type'] && isset( $data['delta']['text'] ) ) {
					$this->buffer_provider_content( $data['delta']['text'] );
				} elseif ( isset( $data['type'] ) && 'message_stop' === $data['type'] ) {
					$this->send_buffered_chunk();
					$this->send_stream_done();
				}
			}
		);
	}

	/**
	 * Process provider SSE lines with buffering for partial chunks.
	 *
	 * @param string   $chunk Chunk of provider data.
	 * @param callable $callback Callback for each JSON payload.
	 *
	 * @return void
	 */
	private function process_provider_stream_lines( $chunk, $callback ) {
		$this->provider_stream_buffer .= $chunk;
		$lines                         = explode( "\n", $this->provider_stream_buffer );
		$this->provider_stream_buffer  = array_pop( $lines );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$json_data = 0 === strpos( $line, 'data: ' ) ? trim( substr( $line, 6 ) ) : $line;

			if ( '[DONE]' !== $json_data && '{' !== substr( $json_data, 0, 1 ) ) {
				continue;
			}

			if ( '' === $json_data ) {
				continue;
			}

			$callback( $json_data );
		}
	}

	/**
	 * Buffer provider content and flush it periodically.
	 *
	 * @param string $content Content delta.
	 *
	 * @return void
	 */
	private function buffer_provider_content( $content ) {
		if (
			false !== strpos( $content, '```json' ) ||
			false !== strpos( $content, '```' )
		) {
			$this->send_buffered_chunk();
			$this->send_stream_chunk( array( 'content' => $content ) );
			$this->last_send_time = microtime( true );
			return;
		}

		$this->buffer .= $content;

		if (
			strlen( $this->buffer ) >= self::BUFFER_THRESHOLD ||
			microtime( true ) - $this->last_send_time >= self::MIN_SEND_INTERVAL ||
			false !== strpos( $this->buffer, "\n" )
		) {
			$this->send_buffered_chunk();
		}
	}

	/**
	 * Send buffered provider content.
	 *
	 * @return void
	 */
	private function send_buffered_chunk() {
		if ( '' === $this->buffer ) {
			return;
		}

		$this->send_stream_chunk( array( 'content' => $this->buffer ) );
		$this->buffer         = '';
		$this->last_send_time = microtime( true );
	}

	/**
	 * Send non-streaming content in smaller SSE chunks.
	 *
	 * @param string $content AI response content.
	 *
	 * @return void
	 */
	private function send_text_as_stream( $content ) {
		if ( function_exists( 'mb_strcut' ) ) {
			$content_length = strlen( $content );

			for ( $offset = 0; $offset < $content_length; $offset += strlen( $chunk ) ) {
				$chunk = mb_strcut( $content, $offset, self::BUFFER_THRESHOLD, 'UTF-8' );

				if ( '' === $chunk ) {
					$chunk = substr( $content, $offset, self::BUFFER_THRESHOLD );
				}

				$this->send_stream_chunk( array( 'content' => $chunk ) );
			}

			return;
		}

		foreach ( $this->get_utf8_safe_chunks( $content ) as $chunk ) {
			$this->send_stream_chunk( array( 'content' => $chunk ) );
		}
	}

	/**
	 * Split UTF-8 content into SSE-safe chunks without relying on mbstring.
	 *
	 * @param string $content UTF-8 content.
	 *
	 * @return array
	 */
	private function get_utf8_safe_chunks( $content ) {
		if ( '' === $content ) {
			return array();
		}

		if ( ! preg_match_all( '/./us', $content, $matches ) ) {
			return str_split( $content, self::BUFFER_THRESHOLD );
		}

		$chunks        = array();
		$current_chunk = '';
		$current_bytes = 0;

		foreach ( $matches[0] as $character ) {
			$character_bytes = strlen( $character );

			if ( '' !== $current_chunk && $current_bytes + $character_bytes > self::BUFFER_THRESHOLD ) {
				$chunks[]      = $current_chunk;
				$current_chunk = '';
				$current_bytes = 0;
			}

			$current_chunk .= $character;
			$current_bytes += $character_bytes;
		}

		if ( '' !== $current_chunk ) {
			$chunks[] = $current_chunk;
		}

		return $chunks;
	}

	/**
	 * Send final stream event.
	 *
	 * @return void
	 */
	private function send_stream_done() {
		if ( $this->stream_done ) {
			return;
		}

		$this->stream_done = true;
		$this->send_stream_chunk( array( 'done' => true ) );
	}

	/**
	 * Convert prompt messages to the shape accepted by wp_ai_client_prompt().
	 *
	 * @param array $messages Prompt messages.
	 *
	 * @return array|WP_Error
	 */
	private function get_ai_client_messages( $messages ) {
		$prompt_messages = array();

		foreach ( $messages as $message ) {
			if ( empty( $message['content'] ) || empty( $message['role'] ) || 'system' === $message['role'] ) {
				continue;
			}

			try {
				$prompt_messages[] = \WordPress\AiClient\Messages\DTO\Message::fromArray(
					array(
						'role'  => 'assistant' === $message['role'] ? 'model' : 'user',
						'parts' => array(
							array(
								'type' => 'text',
								'text' => $message['content'],
							),
						),
					)
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'prompt_message_conversion_error', $e->getMessage() );
			}
		}

		return $prompt_messages;
	}

	/**
	 * Send stream chunk
	 *
	 * @param array $data - data to send.
	 */
	private function send_stream_chunk( $data ) {
		$encoded_data = wp_json_encode( $data );

		if ( false === $encoded_data ) {
			$encoded_data = wp_json_encode(
				array(
					'error'   => true,
					'code'    => 'stream_encoding_error',
					'message' => 'Unable to encode stream payload.',
				)
			);

			if ( false === $encoded_data ) {
				return;
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE payload is encoded via wp_json_encode().
		echo 'data: ' . $encoded_data . "\n\n";

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
}
