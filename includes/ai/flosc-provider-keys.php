<?php
/**
 * Storing one AI provider's key, model, and tuning.
 *
 * Pulled out of the AJAX handler so the rule can be stated once and tested:
 * a value is written into the flow settings row the Settings page reads, it
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

if ( ! function_exists( 'flosc_store_provider_model' ) ) {
	/**
	 * Save one provider's model id onto one flow.
	 *
	 * Same rule as the key: it lands in the row the Settings page reads, it
	 * replaces only its own provider's model, and nothing else on the flow
	 * moves. Picking a model from the fetched list is worth nothing if the
	 * pick does not survive the click.
	 *
	 * @param string $ivr      IVR filename identifying the flow.
	 * @param string $provider FLOSC provider slug.
	 * @param string $model    Model id to store.
	 * @return array{option:string,setting:string,model:string}|WP_Error
	 */
	function flosc_store_provider_model( $ivr, $provider, $model ) {
		$provider = sanitize_key( (string) $provider );
		$ivr      = basename( (string) $ivr );
		$model    = trim( (string) $model );

		$map = array(
			'anthropic' => 'ai_anthropic_model',
			'openai'    => 'ai_openai_model',
			'xai'       => 'ai_xai_model',
			'gemini'    => 'ai_gemini_model',
		);

		if ( ! isset( $map[ $provider ] ) ) {
			return new WP_Error( 'flosc_model_provider', __( 'Unknown AI provider.', 'flosc' ) );
		}

		if ( '' === sanitize_key( pathinfo( $ivr, PATHINFO_FILENAME ) ) ) {
			return new WP_Error( 'flosc_model_no_flow', __( 'No flow was selected, so there is nowhere to save this model.', 'flosc' ) );
		}

		if ( '' === $model ) {
			return new WP_Error( 'flosc_model_empty', __( 'Choose or type a model id before saving.', 'flosc' ) );
		}

		// Deliberately permissive. Providers invent id shapes constantly and
		// FLOSC must never refuse one that works. Only what an id cannot be is
		// rejected: whitespace inside it, control characters, absurd length.
		if ( strlen( $model ) > 200 || preg_match( '/[\s\x00-\x1F\x7F]/', $model ) ) {
			return new WP_Error( 'flosc_model_shape', __( 'That does not look like a model id.', 'flosc' ) );
		}

		$option = function_exists( 'flosc_resolve_flow_option_key_for_ivr' )
			? flosc_resolve_flow_option_key_for_ivr( $ivr )
			: 'flosc_flow_' . sanitize_key( pathinfo( $ivr, PATHINFO_FILENAME ) );

		$settings = get_option( $option, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings[ $map[ $provider ] ] = $model;

		update_option( $option, $settings, false );

		if ( function_exists( 'flosc_bust_flow_option_rows_cache' ) ) {
			flosc_bust_flow_option_rows_cache();
		}

		return array(
			'option'  => $option,
			'setting' => $map[ $provider ],
			'model'   => $model,
		);
	}
}

if ( ! function_exists( 'flosc_mts_utc' ) ) {
	/**
	 * The Michel Time Stamp, in UTC, to the millisecond.
	 *
	 * YYYYy-MMm-DDd-UTC-HHh-MMm-SSs-MMMms — for example
	 * 2026y-08m-30d-UTC-10h-48m-31s-472ms. Sorts correctly as text, carries its
	 * own units so no reader has to guess which number is the month, and names
	 * its zone so a stamp read in Riga and a stamp read in California mean the
	 * same instant.
	 *
	 * The server stamps its own writes. A browser clock can be wrong by hours,
	 * and "saved at" is a claim about when the database was written, which only
	 * the machine that wrote it can make.
	 *
	 * @param float|null $when Unix timestamp with fraction. Defaults to now.
	 * @return string
	 */
	function flosc_mts_utc( $when = null ) {
		$when = ( null === $when ) ? microtime( true ) : (float) $when;
		$secs = (int) floor( $when );
		// Rounded, not truncated: a float carrying .472 lands a hair under it,
		// and floor would report 471. Clamped so a rounded 1000 cannot print a
		// millisecond that belongs to the next second.
		$ms = min( 999, (int) round( ( $when - $secs ) * 1000 ) );

		return gmdate( 'Y\y-m\m-d\d-\U\T\C-H\h-i\m-s\s-', $secs ) . sprintf( '%03dms', $ms );
	}
}

if ( ! function_exists( 'flosc_store_model_tuning' ) ) {
	/**
	 * Store Step 2b for one flow: temperature, max tokens, and the request.
	 *
	 * The page-wide Save at the foot of Settings already writes these. This is
	 * the same write reachable from where they are typed, because a control
	 * whose Save is a screen away is a control operators stop trusting: they
	 * change a number, see nothing happen, and conclude it did not take.
	 *
	 * The parameter text is validated before anything is written. A request
	 * that will not parse is refused with the line that broke it, and the
	 * stored tuning is left exactly as it was — a half-saved request is worse
	 * than an unsaved one. What survives is then reconciled the same way the
	 * page-wide save reconciles it, so the fields and the text agree afterwards.
	 *
	 * @param string              $ivr      Flow file the tuning belongs to.
	 * @param string              $provider FLOSC provider slug.
	 * @param array<string,mixed> $tuning   temperature, max_tokens, params. A
	 *                                      key left out is left alone.
	 * @return array<string,mixed>|WP_Error The values as stored.
	 */
	function flosc_store_model_tuning( $ivr, $provider, $tuning ) {
		$provider = sanitize_key( (string) $provider );
		$ivr      = basename( (string) $ivr );
		$tuning   = is_array( $tuning ) ? $tuning : array();

		if ( ! in_array( $provider, array( 'anthropic', 'openai', 'xai', 'gemini' ), true ) ) {
			return new WP_Error( 'flosc_tuning_provider', __( 'Pick an AI provider before saving its tuning.', 'flosc' ) );
		}

		if ( '' === sanitize_key( pathinfo( $ivr, PATHINFO_FILENAME ) ) ) {
			return new WP_Error( 'flosc_tuning_no_flow', __( 'No flow was selected, so there is nowhere to save this tuning.', 'flosc' ) );
		}

		$params_key = 'ai_' . $provider . '_params';
		$writes     = array();

		if ( array_key_exists( 'params', $tuning ) ) {
			$raw = (string) $tuning['params'];

			// Refused before anything is written, and refused in the operator's
			// own words: flosc_parse_model_parameters names the line it could
			// not read.
			if ( function_exists( 'flosc_parse_model_parameters' ) ) {
				$parsed = flosc_parse_model_parameters( $raw );

				if ( is_wp_error( $parsed ) ) {
					return $parsed;
				}
			}

			// Plain text, one parameter per line. sanitize_textarea_field keeps
			// the newlines that are the format and strips what has no business
			// in a request body.
			$writes[ $params_key ] = function_exists( 'sanitize_textarea_field' )
				? sanitize_textarea_field( $raw )
				: $raw;
		}

		if ( array_key_exists( 'temperature', $tuning ) ) {
			$temperature = trim( (string) $tuning['temperature'] );

			if ( '' !== $temperature && ! is_numeric( $temperature ) ) {
				return new WP_Error( 'flosc_tuning_temperature', __( 'Temperature has to be a number, or empty to leave sampling to the model.', 'flosc' ) );
			}

			$writes['ai_temperature'] = $temperature;
		}

		if ( array_key_exists( 'max_tokens', $tuning ) ) {
			$max_tokens = trim( (string) $tuning['max_tokens'] );

			if ( '' !== $max_tokens && ( ! ctype_digit( $max_tokens ) || 0 === (int) $max_tokens ) ) {
				return new WP_Error( 'flosc_tuning_max_tokens', __( 'Max Tokens has to be a whole number above zero, or empty to use the model\'s own default.', 'flosc' ) );
			}

			$writes['ai_max_tokens'] = $max_tokens;
		}

		if ( ! $writes ) {
			return new WP_Error( 'flosc_tuning_nothing', __( 'There was nothing to save.', 'flosc' ) );
		}

		$option = function_exists( 'flosc_resolve_flow_option_key_for_ivr' )
			? flosc_resolve_flow_option_key_for_ivr( $ivr )
			: 'flosc_flow_' . sanitize_key( pathinfo( $ivr, PATHINFO_FILENAME ) );

		$settings = get_option( $option, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$settings = array_merge( $settings, $writes );

		// The same fold the page-wide save performs: a temperature or a
		// max_tokens named in the request moves into its own field, so the
		// controls never display one number while the request carries another.
		if ( function_exists( 'flosc_reconcile_model_parameters' ) ) {
			$settings = flosc_reconcile_model_parameters( $settings, $provider );
		}

		update_option( $option, $settings, false );

		if ( function_exists( 'flosc_bust_flow_option_rows_cache' ) ) {
			flosc_bust_flow_option_rows_cache();
		}

		return array(
			'option'      => $option,
			'provider'    => $provider,
			'saved_at'    => flosc_mts_utc(),
			'temperature' => (string) ( $settings['ai_temperature'] ?? '' ),
			'max_tokens'  => (string) ( $settings['ai_max_tokens'] ?? '' ),
			'params'      => (string) ( $settings[ $params_key ] ?? '' ),
			'preview'     => function_exists( 'flosc_build_model_parameter_preview' )
				? flosc_build_model_parameter_preview( $provider, $settings )
				: '',
		);
	}
}
