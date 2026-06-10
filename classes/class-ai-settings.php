<?php
/**
 * Plugin AI settings functions.
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mind AI Settings class.
 */
class Mind_AI_Settings {
	/**
	 * Get connected model for runtime API requests.
	 *
	 * @return array{provider: string, name: string}|false
	 */
	public static function get_connected_model() {
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
	 * Get saved provider and model selection from options.
	 *
	 * @return array{provider: string, model: string}
	 */
	public static function get_saved_selection() {
		$settings = get_option( MIND_SETTINGS_OPTION, array() );

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

		if ( ! self::can_resolve_runtime_model( $provider_id, $model_name ) ) {
			return false;
		}

		return array(
			'provider'  => $provider_id,
			'name'      => $model_name,
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
		$runtime_name = (string) $preferred_model[1];

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
			if ( ! self::can_resolve_runtime_model( $provider_id, $model['name'] ) ) {
				continue;
			}

			return array(
				'provider'  => $provider_id,
				'name'      => $model['name'],
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
}
