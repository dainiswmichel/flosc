<?php
/**
 * floscAvailableProviders — install-scoped AI credentials available to any floscFlow.
 *
 * Product model:
 * - Keys configured here (or promoted from a flow save) are AVAILABLE install-wide.
 * - Each floscFlow attaches via floscFlowAiPolicy (ai_provider, models, chain order).
 * - Secrets never go into portable Settings YAML.
 *
 * Option key: flosc_available_providers
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_available_providers_option_key' ) ) {
	/**
	 * @return string
	 */
	function flosc_available_providers_option_key() {
		return 'flosc_available_providers';
	}
}

if ( ! function_exists( 'flosc_available_provider_slugs' ) ) {
	/**
	 * Provider ids that may hold an API key in the install pool.
	 *
	 * @return array<int,string>
	 */
	function flosc_available_provider_slugs() {
		return array( 'anthropic', 'openai', 'xai', 'assemblyai' );
	}
}

if ( ! function_exists( 'flosc_available_providers_flow_key_map' ) ) {
	/**
	 * Map provider slug → flow bag key for the secret.
	 *
	 * @return array<string,string>
	 */
	function flosc_available_providers_flow_key_map() {
		return array(
			'anthropic'  => 'anthropic_api_key',
			'openai'     => 'openai_api_key',
			'xai'        => 'xai_api_key',
			'assemblyai' => 'assemblyai_api_key',
		);
	}
}

if ( ! function_exists( 'flosc_available_providers_get_all' ) ) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function flosc_available_providers_get_all() {
		$raw = get_option( flosc_available_providers_option_key(), array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$out = array();
		foreach ( flosc_available_provider_slugs() as $slug ) {
			$row = isset( $raw[ $slug ] ) && is_array( $raw[ $slug ] ) ? $raw[ $slug ] : array();
			$out[ $slug ] = array(
				'api_key'    => isset( $row['api_key'] ) ? (string) $row['api_key'] : '',
				'label'      => isset( $row['label'] ) ? (string) $row['label'] : '',
				'updated_at' => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
			);
		}
		return $out;
	}
}

if ( ! function_exists( 'flosc_available_providers_save_all' ) ) {
	/**
	 * @param array<string,array<string,mixed>> $providers Full map.
	 * @return void
	 */
	function flosc_available_providers_save_all( $providers ) {
		if ( ! is_array( $providers ) ) {
			return;
		}
		$clean = array();
		foreach ( flosc_available_provider_slugs() as $slug ) {
			$row = isset( $providers[ $slug ] ) && is_array( $providers[ $slug ] ) ? $providers[ $slug ] : array();
			$key = isset( $row['api_key'] ) ? (string) $row['api_key'] : '';
			// Preserve existing secret when empty submit (password field blank).
			if ( $key === '' && isset( $row['keep_existing'] ) && $row['keep_existing'] ) {
				$existing = flosc_available_providers_get_all();
				$key      = (string) ( $existing[ $slug ]['api_key'] ?? '' );
			}
			$clean[ $slug ] = array(
				'api_key'    => $key,
				'label'      => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				'updated_at' => $key !== '' ? current_time( 'mysql' ) : (string) ( $row['updated_at'] ?? '' ),
			);
		}
		update_option( flosc_available_providers_option_key(), $clean, false );
	}
}

if ( ! function_exists( 'flosc_available_providers_set_key' ) ) {
	/**
	 * @param string $provider Provider slug.
	 * @param string $api_key  Secret (empty clears).
	 * @return void
	 */
	function flosc_available_providers_set_key( $provider, $api_key ) {
		$provider = sanitize_key( (string) $provider );
		if ( ! in_array( $provider, flosc_available_provider_slugs(), true ) ) {
			return;
		}
		$all = flosc_available_providers_get_all();
		$all[ $provider ]['api_key']    = (string) $api_key;
		$all[ $provider ]['updated_at'] = $api_key !== '' ? current_time( 'mysql' ) : '';
		flosc_available_providers_save_all( $all );
	}
}

if ( ! function_exists( 'flosc_available_providers_has_key' ) ) {
	/**
	 * @param string $provider Provider slug.
	 * @return bool
	 */
	function flosc_available_providers_has_key( $provider ) {
		$all = flosc_available_providers_get_all();
		$provider = sanitize_key( (string) $provider );
		return $provider !== '' && ! empty( $all[ $provider ]['api_key'] );
	}
}

if ( ! function_exists( 'flosc_available_providers_promote_from_flow' ) ) {
	/**
	 * When a flow saves a non-empty key, copy it into the install pool so it is
	 * available to other flows (floscAvailableProviders product rule).
	 *
	 * @param array<string,mixed> $flow_settings Flow bag after save.
	 * @return void
	 */
	function flosc_available_providers_promote_from_flow( $flow_settings ) {
		if ( ! is_array( $flow_settings ) ) {
			return;
		}
		$map = flosc_available_providers_flow_key_map();
		foreach ( $map as $provider => $flow_key ) {
			$val = isset( $flow_settings[ $flow_key ] ) ? trim( (string) $flow_settings[ $flow_key ] ) : '';
			if ( $val !== '' ) {
				flosc_available_providers_set_key( $provider, $val );
			}
		}
	}
}

if ( ! function_exists( 'flosc_get_provider_api_key' ) ) {
	/**
	 * Resolve API key for a provider: flow bag first, then floscAvailableProviders.
	 *
	 * @param string      $provider Provider slug (anthropic|openai|xai|assemblyai).
	 * @param string|null $flow_id  Optional flow stem for flosc_get_setting.
	 * @return string
	 */
	function flosc_get_provider_api_key( $provider, $flow_id = null ) {
		$provider = sanitize_key( (string) $provider );
		$map      = flosc_available_providers_flow_key_map();
		$flow_key = $map[ $provider ] ?? '';
		$from_flow = '';
		if ( $flow_key !== '' && function_exists( 'flosc_get_setting' ) ) {
			$from_flow = trim( (string) flosc_get_setting( $flow_key, '', $flow_id ) );
		}
		if ( $from_flow !== '' ) {
			return $from_flow;
		}
		$all = flosc_available_providers_get_all();
		return isset( $all[ $provider ]['api_key'] ) ? trim( (string) $all[ $provider ]['api_key'] ) : '';
	}
}

if ( ! function_exists( 'flosc_admin_save_available_providers' ) ) {
	/**
	 * admin-post.php?action=flosc_save_available_providers
	 *
	 * @return void
	 */
	function flosc_admin_save_available_providers() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AI providers.', 'flosc' ) );
		}
		check_admin_referer( 'flosc_save_available_providers' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$posted = isset( $_POST['avail_api_key'] ) && is_array( $_POST['avail_api_key'] ) ? wp_unslash( $_POST['avail_api_key'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$clear  = isset( $_POST['avail_clear'] ) && is_array( $_POST['avail_clear'] ) ? wp_unslash( $_POST['avail_clear'] ) : array();

		$all = flosc_available_providers_get_all();
		foreach ( flosc_available_provider_slugs() as $slug ) {
			if ( ! empty( $clear[ $slug ] ) ) {
				$all[ $slug ]['api_key']    = '';
				$all[ $slug ]['updated_at'] = '';
				continue;
			}
			$new = isset( $posted[ $slug ] ) ? trim( (string) $posted[ $slug ] ) : '';
			if ( $new !== '' ) {
				$all[ $slug ]['api_key']    = $new;
				$all[ $slug ]['updated_at'] = current_time( 'mysql' );
			}
			// blank password field = keep existing
		}
		flosc_available_providers_save_all( $all );

		set_transient(
			'flosc_ai_all_notice_' . get_current_user_id(),
			array(
				'message' => __( 'Available providers saved.', 'flosc' ),
				'type'    => 'success',
			),
			60
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ivr = isset( $_POST['flosc_return_ivr'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['flosc_return_ivr'] ) ) : '';
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'flosc-settings',
					'tab'  => 'ai',
					'view' => 'all',
					'ivr'  => $ivr,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
	add_action( 'admin_post_flosc_save_available_providers', 'flosc_admin_save_available_providers' );
}
