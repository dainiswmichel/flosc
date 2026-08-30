<?php
/**
 * Storing one AI provider API key.
 *
 * Pulled out of the AJAX handler so the rule can be stated once and tested:
 * a key is written into the flow settings row the Settings page reads, it
 * replaces only its own provider's entry, and everything else in that row —
 * the other providers' keys above all — is left exactly as it was.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_provider_key_is_plausible' ) ) {
	/**
	 * Whether a pasted string could be an API key at all.
	 *
	 * Not a format check — providers change key shapes and FLOSC must never be
	 * the reason a valid new key is refused. This rejects only what no key can
	 * be: empty, absurdly long, or carrying control characters, which is what a
	 * bad paste looks like.
	 *
	 * @param string $api_key Pasted value.
	 * @return true|WP_Error
	 */
	function flosc_provider_key_is_plausible( $api_key ) {
		$api_key = (string) $api_key;

		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'flosc_key_empty', __( 'Paste an API key before saving.', 'flosc' ) );
		}

		if ( strlen( $api_key ) > 4096 ) {
			return new WP_Error( 'flosc_key_long', __( 'That is too long to be an API key.', 'flosc' ) );
		}

		if ( preg_match( '/[\x00-\x1F\x7F]/', $api_key ) ) {
			return new WP_Error( 'flosc_key_control_chars', __( 'That API key contains characters an API key cannot contain.', 'flosc' ) );
		}

		return true;
	}
}

if ( ! function_exists( 'flosc_store_provider_api_key' ) ) {
	/**
	 * Save one provider's key onto one flow.
	 *
	 * @param string $ivr      IVR filename identifying the flow.
	 * @param string $provider FLOSC provider slug.
	 * @param string $api_key  The key to store.
	 * @return array{option:string,setting:string,suffix:string}|WP_Error
	 */
	function flosc_store_provider_api_key( $ivr, $provider, $api_key ) {
		$provider = sanitize_key( (string) $provider );
		$ivr      = basename( (string) $ivr );
		$api_key  = trim( (string) $api_key );

		$map = function_exists( 'flosc_available_providers_flow_key_map' )
			? flosc_available_providers_flow_key_map()
			: array();

		if ( ! isset( $map[ $provider ] ) ) {
			return new WP_Error( 'flosc_key_provider', __( 'Unknown AI provider.', 'flosc' ) );
		}

		if ( '' === sanitize_key( pathinfo( $ivr, PATHINFO_FILENAME ) ) ) {
			return new WP_Error( 'flosc_key_no_flow', __( 'No flow was selected, so there is nowhere to save this key.', 'flosc' ) );
		}

		$plausible = flosc_provider_key_is_plausible( $api_key );

		if ( is_wp_error( $plausible ) ) {
			return $plausible;
		}

		// The row the Settings page reads. A plain flosc_flow_<stem> key is not
		// always that row on an install carrying legacy duplicates, and a key
		// written to a row nothing reads is indistinguishable from a key that
		// never saved.
		$option = function_exists( 'flosc_resolve_flow_option_key_for_ivr' )
			? flosc_resolve_flow_option_key_for_ivr( $ivr )
			: 'flosc_flow_' . sanitize_key( pathinfo( $ivr, PATHINFO_FILENAME ) );

		$settings = get_option( $option, array() );
		$settings = is_array( $settings ) ? $settings : array();

		// Only this provider's entry changes. Every other key on the flow —
		// including the other providers' — is carried through untouched, so
		// saving a second key never costs the first.
		$settings[ $map[ $provider ] ] = $api_key;

		update_option( $option, $settings, false );

		if ( function_exists( 'flosc_bust_flow_option_rows_cache' ) ) {
			flosc_bust_flow_option_rows_cache();
		}

		return array(
			'option'  => $option,
			'setting' => $map[ $provider ],
			'suffix'  => strlen( $api_key ) >= 4 ? substr( $api_key, -4 ) : '',
		);
	}
}
