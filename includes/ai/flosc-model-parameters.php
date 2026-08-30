<?php
/**
 * Extra model parameters, written by the operator.
 *
 * FLOSC ships two tuning fields. Providers ship dozens, they differ per model,
 * and they change without notice — measured against one Anthropic key on
 * 2026-08-30, Sonnet 4.5 accepts temperature, top_p and top_k while Sonnet 5
 * rejects all three and accepts thinking instead. No fixed set of inputs can
 * track that, so the operator gets to name parameters FLOSC has never heard of.
 *
 * What FLOSC owes them in return is not silence. It parses what they wrote
 * before storing it, so a typo is caught at the point of typing, and it passes
 * the provider's own refusal back verbatim rather than swallowing it. FLOSC
 * does not judge whether a parameter is real; the provider does that, and it
 * is the only thing qualified to.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_parse_model_parameters' ) ) {
	/**
	 * Read operator-written parameters into an array.
	 *
	 * Two shapes are accepted, because both are things people actually type:
	 * a JSON object, or one "key: value" per line. Values keep their type —
	 * 0.3 stays a number, true stays a boolean, a JSON object stays an object —
	 * since a provider that wants a number will refuse the string "0.3".
	 *
	 * @param string $raw What the operator typed.
	 * @return array<string,mixed>|WP_Error
	 */
	function flosc_parse_model_parameters( $raw ) {
		$raw = trim( (string) $raw );

		if ( '' === $raw ) {
			return array();
		}

		if ( strlen( $raw ) > 8192 ) {
			return new WP_Error( 'flosc_params_long', __( 'That is longer than any set of model parameters needs to be.', 'flosc' ) );
		}

		// A JSON object, pasted from a provider's own documentation.
		if ( '{' === $raw[0] ) {
			$decoded = json_decode( $raw, true );

			if ( ! is_array( $decoded ) ) {
				return new WP_Error(
					'flosc_params_json',
					sprintf(
						/* translators: %s: the JSON parser's own complaint. */
						__( 'That is not valid JSON: %s', 'flosc' ),
						json_last_error_msg()
					)
				);
			}

			return flosc_validate_model_parameter_keys( $decoded );
		}

		// One key: value per line.
		$out = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || 0 === strpos( $line, '#' ) || 0 === strpos( $line, '//' ) ) {
				continue;
			}

			$parts = explode( ':', $line, 2 );

			if ( count( $parts ) !== 2 ) {
				return new WP_Error(
					'flosc_params_line',
					sprintf(
						/* translators: %s: the line as typed. */
						__( 'This line is not "name: value" — %s', 'flosc' ),
						$line
					)
				);
			}

			$key   = trim( $parts[0] );
			$value = trim( $parts[1] );
			$value = trim( $value, ',' );

			if ( '' === $key ) {
				return new WP_Error( 'flosc_params_key', __( 'A parameter with no name cannot be sent.', 'flosc' ) );
			}

			$out[ $key ] = flosc_coerce_model_parameter_value( $value );
		}

		return flosc_validate_model_parameter_keys( $out );
	}
}

if ( ! function_exists( 'flosc_coerce_model_parameter_value' ) ) {
	/**
	 * Give a typed value the type the provider expects.
	 *
	 * @param string $value Raw value as typed.
	 * @return mixed
	 */
	function flosc_coerce_model_parameter_value( $value ) {
		$value = trim( (string) $value );
		$value = trim( $value, '"\'' ) === $value ? $value : trim( $value, '"\'' );

		if ( '' === $value ) {
			return '';
		}

		$lower = strtolower( $value );

		if ( 'true' === $lower ) {
			return true;
		}

		if ( 'false' === $lower ) {
			return false;
		}

		if ( 'null' === $lower ) {
			return null;
		}

		// Objects and arrays, so nested shapes like thinking survive.
		if ( '{' === $value[0] || '[' === $value[0] ) {
			$decoded = json_decode( $value, true );

			if ( null !== $decoded ) {
				return $decoded;
			}
		}

		if ( is_numeric( $value ) ) {
			return ( (string) (int) $value === $value ) ? (int) $value : (float) $value;
		}

		return $value;
	}
}

if ( ! function_exists( 'flosc_validate_model_parameter_keys' ) ) {
	/**
	 * Refuse the few things that are never a parameter name, and nothing else.
	 *
	 * FLOSC deliberately does not keep a list of allowed parameters. Providers
	 * add them constantly and a list would be wrong within weeks — which is the
	 * failure this whole field exists to avoid. What is blocked is the handful
	 * of keys FLOSC itself owns, because letting them be overwritten here would
	 * silently contradict the fields above.
	 *
	 * @param array<string,mixed> $params Parsed parameters.
	 * @return array<string,mixed>|WP_Error
	 */
	function flosc_validate_model_parameter_keys( $params ) {
		$reserved = array( 'model', 'messages', 'system', 'stream', 'tools', 'max_tokens' );

		foreach ( $params as $key => $unused ) {
			if ( ! is_string( $key ) || '' === trim( $key ) ) {
				return new WP_Error( 'flosc_params_key', __( 'A parameter with no name cannot be sent.', 'flosc' ) );
			}

			if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
				return new WP_Error(
					'flosc_params_name',
					sprintf(
						/* translators: %s: the offending parameter name. */
						__( '"%s" is not shaped like a parameter name.', 'flosc' ),
						$key
					)
				);
			}

			if ( in_array( strtolower( $key ), $reserved, true ) ) {
				return new WP_Error(
					'flosc_params_reserved',
					sprintf(
						/* translators: %s: the reserved parameter name. */
						__( '%s is set by FLOSC from the fields above, so it cannot be overridden here.', 'flosc' ),
						$key
					)
				);
			}
		}

		return $params;
	}
}

if ( ! function_exists( 'flosc_get_model_parameters' ) ) {
	/**
	 * The stored parameters for one provider on the current flow.
	 *
	 * Anything unparseable is treated as absent rather than sent, because a
	 * broken parameter set must not take a working bot down with it.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return array<string,mixed>
	 */
	function flosc_get_model_parameters( $provider ) {
		$provider = sanitize_key( (string) $provider );

		if ( ! function_exists( 'flosc_get_setting' ) ) {
			return array();
		}

		$parsed = flosc_parse_model_parameters( (string) flosc_get_setting( 'ai_' . $provider . '_params', '' ) );

		return is_wp_error( $parsed ) ? array() : $parsed;
	}
}
