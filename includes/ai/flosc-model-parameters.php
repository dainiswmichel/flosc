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
	 * Refuse only what would make the request malformed.
	 *
	 * The parameter set is the payload. Temperature and Max Tokens above are a
	 * convenience for writing into it, not owners of it — so naming one of them
	 * here overrides the field, which is what an operator typing a payload
	 * expects. FLOSC shows that the override happened rather than pretending
	 * the field still rules.
	 *
	 * FLOSC keeps no list of allowed parameters; a list would be wrong within
	 * weeks, which is the failure this field exists to avoid. What is refused
	 * is only what FLOSC must assemble for the request to be a request at all:
	 * the conversation itself, and the streaming mode its parser depends on.
	 *
	 * @param array<string,mixed> $params Parsed parameters.
	 * @return array<string,mixed>|WP_Error
	 */
	function flosc_validate_model_parameter_keys( $params ) {
		// Not "FLOSC owns these" — "the request stops working without these".
		$structural = array( 'messages', 'contents', 'stream' );

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

			if ( in_array( strtolower( $key ), $structural, true ) ) {
				return new WP_Error(
					'flosc_params_structural',
					sprintf(
						/* translators: %s: the parameter name. */
						__( 'FLOSC builds %s from the conversation itself, and the reply cannot be read without it. Every other parameter is yours to set.', 'flosc' ),
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

if ( ! function_exists( 'flosc_model_parameter_reference' ) ) {
	/**
	 * What each parameter does, for the operator typing it.
	 *
	 * Documentation, never a gate. A name absent from this list is still sent —
	 * the provider rules on it. This exists so an operator does not have to
	 * leave the page to find out what top_p means, or which providers take it.
	 *
	 * "measured" marks a claim verified against a live API from this codebase
	 * rather than read from a reference. Where the two disagree, measurement
	 * wins: several published lists put presence_penalty and seed under
	 * Anthropic, and Anthropic answers "Extra inputs are not permitted".
	 *
	 * @return array<string,array{what:string,range:string,providers:string,measured:bool}>
	 */
	function flosc_model_parameter_reference() {
		return array(
			'temperature'       => array(
				'what'      => __( 'Flattens or sharpens the odds across the next word. Low keeps the safest choice; high lets unlikely words through. 0 is the same answer every time.', 'flosc' ),
				'range'     => '0.0 – 2.0',
				'providers' => __( 'OpenAI, xAI, Gemini. Anthropic only on older models — Sonnet 5, Opus 5, Fable 5, Opus 4.8 and 4.7 refuse it.', 'flosc' ),
				'measured'  => true,
			),
			'top_p'             => array(
				'what'      => __( 'Nucleus sampling. Considers only the smallest set of words whose odds add up to this share, and ignores the rest. Use this or temperature, not both.', 'flosc' ),
				'range'     => '0.0 – 1.0',
				'providers' => __( 'OpenAI, xAI, Gemini, and Anthropic on Sonnet 4.5. Sonnet 5 refuses it.', 'flosc' ),
				'measured'  => true,
			),
			'top_k'             => array(
				'what'      => __( 'Considers only the k most likely next words. A blunter cut than top_p.', 'flosc' ),
				'range'     => __( 'whole number, commonly 20 – 100', 'flosc' ),
				'providers' => __( 'Anthropic on Sonnet 4.5 and Gemini. Sonnet 5 refuses it.', 'flosc' ),
				'measured'  => true,
			),
			'stop_sequences'    => array(
				'what'      => __( 'Text that ends the reply the moment it appears. Useful for keeping a bot from writing the visitor\'s next line.', 'flosc' ),
				'range'     => __( 'list of strings', 'flosc' ),
				'providers' => __( 'Anthropic, on every model tested.', 'flosc' ),
				'measured'  => true,
			),
			'thinking'          => array(
				'what'      => __( 'Claude\'s extended reasoning. Adaptive lets the model decide how long to think. Costs output tokens, so raise Max Tokens with it.', 'flosc' ),
				'range'     => '{"type":"adaptive"}',
				'providers' => __( 'Anthropic\'s newer models. Sonnet 4.5 refuses adaptive.', 'flosc' ),
				'measured'  => true,
			),
			'stop'              => array(
				'what'      => __( 'Same idea as stop_sequences, under the name the OpenAI-shaped APIs use.', 'flosc' ),
				'range'     => __( 'string or list of strings', 'flosc' ),
				'providers' => __( 'OpenAI, xAI.', 'flosc' ),
				'measured'  => false,
			),
			'presence_penalty'  => array(
				'what'      => __( 'Penalises a word for having appeared at all, pushing the model onto new subjects. Raise it when a bot circles the same topic.', 'flosc' ),
				'range'     => '-2.0 – 2.0',
				'providers' => __( 'OpenAI, xAI. Not an Anthropic parameter — Anthropic answers "Extra inputs are not permitted".', 'flosc' ),
				'measured'  => true,
			),
			'frequency_penalty' => array(
				'what'      => __( 'Penalises a word further each time it is reused. Raise it when a bot leans on the same phrases.', 'flosc' ),
				'range'     => '-2.0 – 2.0',
				'providers' => __( 'OpenAI, xAI. Not an Anthropic parameter.', 'flosc' ),
				'measured'  => true,
			),
			'seed'              => array(
				'what'      => __( 'Fixes the random draw so the same prompt returns the same reply. For testing, not for visitors.', 'flosc' ),
				'range'     => __( 'any whole number', 'flosc' ),
				'providers' => __( 'OpenAI, xAI. Not an Anthropic parameter.', 'flosc' ),
				'measured'  => true,
			),
			'response_format'   => array(
				'what'      => __( 'Forces the reply into a shape, usually JSON. FLOSC expects prose in chat, so this will likely break the bubble.', 'flosc' ),
				'range'     => '{"type":"json_object"}',
				'providers' => __( 'OpenAI.', 'flosc' ),
				'measured'  => false,
			),
			'logit_bias'        => array(
				'what'      => __( 'Pushes named tokens up or down by id. Precise, and easy to get wrong.', 'flosc' ),
				'range'     => '{"50256": -100}',
				'providers' => __( 'OpenAI.', 'flosc' ),
				'measured'  => false,
			),
			'n'                 => array(
				'what'      => __( 'Asks for several completions at once. FLOSC shows one and you pay for all of them.', 'flosc' ),
				'range'     => __( 'whole number', 'flosc' ),
				'providers' => __( 'OpenAI.', 'flosc' ),
				'measured'  => false,
			),
			'user'              => array(
				'what'      => __( 'An end-user label the provider records for abuse tracing. Do not put anything identifying here.', 'flosc' ),
				'range'     => __( 'string', 'flosc' ),
				'providers' => __( 'OpenAI, xAI.', 'flosc' ),
				'measured'  => false,
			),
			'generationConfig'  => array(
				'what'      => __( 'Gemini nests its sampling inside this rather than at the top level.', 'flosc' ),
				'range'     => '{"temperature":0.4,"topP":0.95}',
				'providers' => __( 'Gemini.', 'flosc' ),
				'measured'  => false,
			),
			'safetySettings'    => array(
				'what'      => __( 'Gemini\'s content thresholds per harm category.', 'flosc' ),
				'range'     => '[{"category":"...","threshold":"..."}]',
				'providers' => __( 'Gemini.', 'flosc' ),
				'measured'  => false,
			),
		);
	}
}

if ( ! function_exists( 'flosc_model_parameters_overriding' ) ) {
	/**
	 * Which of the visible tuning fields the parameter text is overriding.
	 *
	 * The fields above and this box write into the same payload, so one can
	 * quietly replace the other. An operator is owed the word "overridden"
	 * rather than a number on screen that is not the number being sent.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return array<string,mixed> Field key => value the parameters will send.
	 */
	function flosc_model_parameters_overriding( $provider ) {
		$params = flosc_get_model_parameters( $provider );
		$fields = array( 'temperature', 'max_tokens', 'model' );
		$out    = array();

		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $params ) ) {
				$out[ $field ] = $params[ $field ];
			}
		}

		return $out;
	}
}
