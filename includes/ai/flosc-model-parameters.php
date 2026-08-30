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
	 * the provider rules on it, and its answer comes back in the connection
	 * test. That is what keeps this list from going stale in a way that costs
	 * anything: when a provider ships a parameter tomorrow, it works in FLOSC
	 * tomorrow, and only the note beside it is missing. For that case the panel
	 * offers to ask the configured model itself what the parameter is.
	 *
	 * Fields:
	 *   what      one paragraph, in the operator's language, not the spec's.
	 *   range     what a sane value looks like.
	 *   providers who takes it, in prose, including the exceptions.
	 *   measured  true when FLOSC has watched a live API accept or refuse it.
	 *   applies   provider slugs to list it under. Documentation again: a
	 *             parameter absent here can still be typed and sent.
	 *   example   the line clicking it writes into the request.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function flosc_model_parameter_reference() {
		return array(
			'max_tokens'        => array(
				'what'      => __( 'The longest reply you will pay for. The model stops there whether or not it was finished, so too low truncates mid-sentence.', 'flosc' ),
				'range'     => __( 'whole number, up to the model\'s own ceiling', 'flosc' ),
				'providers' => __( 'Every provider. This is the Max Tokens field above; naming it here overrides that field.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'anthropic', 'openai', 'xai', 'gemini' ),
				'example'   => 'max_tokens: 1200',
			),
			'temperature'       => array(
				'what'      => __( 'Flattens or sharpens the odds across the next word. Low keeps the safest choice; high lets unlikely words through. 0 is the same answer every time.', 'flosc' ),
				'range'     => '0.0 – 2.0',
				'providers' => __( 'OpenAI, xAI, Gemini. Anthropic only on older models — Sonnet 5, Opus 5, Fable 5, Opus 4.8 and 4.7 refuse it.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'anthropic', 'openai', 'xai', 'gemini' ),
				'example'   => 'temperature: 0.7',
			),
			'top_p'             => array(
				'what'      => __( 'Nucleus sampling. Considers only the smallest set of words whose odds add up to this share, and ignores the rest. Use this or temperature, not both.', 'flosc' ),
				'range'     => '0.0 – 1.0',
				'providers' => __( 'OpenAI, xAI, Gemini, and Anthropic on Sonnet 4.5. Sonnet 5 refuses it.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'anthropic', 'openai', 'xai', 'gemini' ),
				'example'   => 'top_p: 0.9',
			),
			'top_k'             => array(
				'what'      => __( 'Considers only the k most likely next words. A blunter cut than top_p.', 'flosc' ),
				'range'     => __( 'whole number, commonly 20 – 100', 'flosc' ),
				'providers' => __( 'Anthropic on Sonnet 4.5 and Gemini. Sonnet 5 refuses it.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'anthropic', 'gemini' ),
				'example'   => 'top_k: 40',
			),
			'stop_sequences'    => array(
				'what'      => __( 'Text that ends the reply the moment it appears. Useful for keeping a bot from writing the visitor\'s next line.', 'flosc' ),
				'range'     => __( 'list of strings', 'flosc' ),
				'providers' => __( 'Anthropic, on every model tested.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'anthropic' ),
				'example'   => 'stop_sequences: ["User:"]',
			),
			'thinking'          => array(
				'what'      => __( 'Claude\'s extended reasoning. Adaptive lets the model decide how long to think. Costs output tokens, so raise Max Tokens with it.', 'flosc' ),
				'range'     => '{"type":"adaptive"}',
				'providers' => __( 'Anthropic\'s newer models. Sonnet 4.5 refuses adaptive.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'anthropic' ),
				'example'   => 'thinking: {"type":"adaptive"}',
			),
			'stop'              => array(
				'what'      => __( 'Same idea as stop_sequences, under the name the OpenAI-shaped APIs use.', 'flosc' ),
				'range'     => __( 'string or list of strings', 'flosc' ),
				'providers' => __( 'OpenAI, xAI.', 'flosc' ),
				'measured'  => false,
				'applies'   => array( 'openai', 'xai' ),
				'example'   => 'stop: ["User:"]',
			),
			'presence_penalty'  => array(
				'what'      => __( 'Penalises a word for having appeared at all, pushing the model onto new subjects. Raise it when a bot circles the same topic.', 'flosc' ),
				'range'     => '-2.0 – 2.0',
				'providers' => __( 'OpenAI, xAI. Not an Anthropic parameter — Anthropic answers "Extra inputs are not permitted".', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'openai', 'xai' ),
				'example'   => 'presence_penalty: 0.5',
			),
			'frequency_penalty' => array(
				'what'      => __( 'Penalises a word further each time it is reused. Raise it when a bot leans on the same phrases.', 'flosc' ),
				'range'     => '-2.0 – 2.0',
				'providers' => __( 'OpenAI, xAI. Not an Anthropic parameter.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'openai', 'xai' ),
				'example'   => 'frequency_penalty: 0.3',
			),
			'seed'              => array(
				'what'      => __( 'Fixes the random draw so the same prompt returns the same reply. For testing, not for visitors.', 'flosc' ),
				'range'     => __( 'any whole number', 'flosc' ),
				'providers' => __( 'OpenAI, xAI. Not an Anthropic parameter.', 'flosc' ),
				'measured'  => true,
				'applies'   => array( 'openai', 'xai' ),
				'example'   => 'seed: 42',
			),
			'response_format'   => array(
				'what'      => __( 'Forces the reply into a shape, usually JSON. FLOSC expects prose in chat, so this will likely break the bubble.', 'flosc' ),
				'range'     => '{"type":"json_object"}',
				'providers' => __( 'OpenAI.', 'flosc' ),
				'measured'  => false,
				'applies'   => array( 'openai' ),
				'example'   => 'response_format: {"type":"json_object"}',
			),
			'logit_bias'        => array(
				'what'      => __( 'Pushes named tokens up or down by id. Precise, and easy to get wrong.', 'flosc' ),
				'range'     => '{"50256": -100}',
				'providers' => __( 'OpenAI.', 'flosc' ),
				'measured'  => false,
				'applies'   => array( 'openai' ),
				'example'   => 'logit_bias: {"50256": -100}',
			),
			'n'                 => array(
				'what'      => __( 'Asks for several completions at once. FLOSC shows one and you pay for all of them.', 'flosc' ),
				'range'     => __( 'whole number', 'flosc' ),
				'providers' => __( 'OpenAI.', 'flosc' ),
				'measured'  => false,
				'applies'   => array( 'openai' ),
				'example'   => 'n: 1',
			),
			'user'              => array(
				'what'      => __( 'An end-user label the provider records for abuse tracing. Do not put anything identifying here.', 'flosc' ),
				'range'     => __( 'string', 'flosc' ),
				'providers' => __( 'OpenAI, xAI.', 'flosc' ),
				'measured'  => false,
				'applies'   => array( 'openai', 'xai' ),
				'example'   => 'user: flosc-visitor',
			),
			'generationConfig'  => array(
				'what'      => __( 'Gemini nests its sampling inside this rather than at the top level.', 'flosc' ),
				'range'     => '{"temperature":0.4,"topP":0.95}',
				'providers' => __( 'Gemini.', 'flosc' ),
				'measured'  => false,
				'applies'   => array( 'gemini' ),
				'example'   => 'generationConfig: {"temperature":0.4,"topP":0.95}',
			),
			'safetySettings'    => array(
				'what'      => __( 'Gemini\'s content thresholds per harm category.', 'flosc' ),
				'range'     => '[{"category":"...","threshold":"..."}]',
				'providers' => __( 'Gemini.', 'flosc' ),
				'measured'  => false,
				'applies'   => array( 'gemini' ),
				'example'   => 'safetySettings: [{"category":"HARM_CATEGORY_HARASSMENT","threshold":"BLOCK_ONLY_HIGH"}]',
			),
		);
	}
}

if ( ! function_exists( 'flosc_model_parameters_for_provider' ) ) {
	/**
	 * The reference rows worth showing for one provider.
	 *
	 * A filter over documentation, not over what can be sent. An unknown
	 * provider gets the whole list rather than an empty one — better to show
	 * everything FLOSC knows than to imply a provider takes nothing.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return array<string,array<string,mixed>>
	 */
	function flosc_model_parameters_for_provider( $provider ) {
		$provider = sanitize_key( (string) $provider );
		$all      = flosc_model_parameter_reference();

		if ( '' === $provider ) {
			return $all;
		}

		$rows = array();

		foreach ( $all as $name => $ref ) {
			$applies = isset( $ref['applies'] ) ? (array) $ref['applies'] : array();

			if ( ! $applies || in_array( $provider, $applies, true ) ) {
				$rows[ $name ] = $ref;
			}
		}

		return $rows ? $rows : $all;
	}
}

if ( ! function_exists( 'flosc_model_parameter_recipes' ) ) {
	/**
	 * Whole parameter sets that do a named job, for a provider.
	 *
	 * A parameter on its own asks the operator to work out what to combine it
	 * with. These are the combinations, each with the reason it exists, so a
	 * setup that takes an afternoon of reading can be arrived at in one click
	 * and then edited.
	 *
	 * Every recipe here is composed only of parameters FLOSC has watched that
	 * provider accept. A provider nobody has measured gets none, which is the
	 * honest answer rather than a plausible-looking guess.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return array<int,array<string,string>> name, why, params.
	 */
	function flosc_model_parameter_recipes( $provider ) {
		$recipes = array(
			'anthropic' => array(
				array(
					'name'   => __( 'Tight and factual', 'flosc' ),
					'why'    => __( 'Narrows the word choice so the bot stays on what it was told. For a flow that quotes prices, hours or policy.', 'flosc' ),
					'params' => "top_p: 0.7\nmax_tokens: 800",
					'models' => __( 'Sonnet 4.5. Sonnet 5 and newer refuse top_p.', 'flosc' ),
				),
				array(
					'name'   => __( 'Never writes the visitor\'s line', 'flosc' ),
					'why'    => __( 'Cuts the reply the moment the model starts inventing the other half of the conversation. The classic chat-bubble fix.', 'flosc' ),
					'params' => "stop_sequences: [\"User:\", \"Visitor:\", \"Human:\"]",
					'models' => __( 'Every Anthropic model tested.', 'flosc' ),
				),
				array(
					'name'   => __( 'Thinks before it answers', 'flosc' ),
					'why'    => __( 'Lets Claude reason at length before replying. Slower and dearer, and worth it where the answer has to be worked out rather than recalled.', 'flosc' ),
					'params' => "thinking: {\"type\":\"adaptive\"}\nmax_tokens: 4000",
					'models' => __( 'Sonnet 5, Opus 5 and newer. Sonnet 4.5 refuses adaptive thinking.', 'flosc' ),
				),
			),
			'openai'    => array(
				array(
					'name'   => __( 'Tight and factual', 'flosc' ),
					'why'    => __( 'Low randomness for a flow that has to keep saying the same true thing.', 'flosc' ),
					'params' => "temperature: 0.2\nmax_tokens: 800",
					'models' => '',
				),
				array(
					'name'   => __( 'Stops repeating itself', 'flosc' ),
					'why'    => __( 'For a bot that circles the same phrases across a long conversation.', 'flosc' ),
					'params' => "frequency_penalty: 0.4\npresence_penalty: 0.3",
					'models' => '',
				),
				array(
					'name'   => __( 'Same answer every time', 'flosc' ),
					'why'    => __( 'For testing a flow, so a change you see is a change you made. Not for visitors.', 'flosc' ),
					'params' => "temperature: 0\nseed: 42",
					'models' => '',
				),
			),
			'xai'       => array(
				array(
					'name'   => __( 'Tight and factual', 'flosc' ),
					'why'    => __( 'Low randomness for a flow that has to keep saying the same true thing.', 'flosc' ),
					'params' => "temperature: 0.2\nmax_tokens: 800",
					'models' => '',
				),
				array(
					'name'   => __( 'Same answer every time', 'flosc' ),
					'why'    => __( 'For testing a flow, so a change you see is a change you made.', 'flosc' ),
					'params' => "temperature: 0\nseed: 42",
					'models' => '',
				),
			),
			'gemini'    => array(
				array(
					'name'   => __( 'Tight and factual', 'flosc' ),
					'why'    => __( 'Gemini keeps its sampling inside generationConfig rather than at the top level, so a whole block is set at once.', 'flosc' ),
					'params' => "generationConfig: {\"temperature\":0.2,\"topP\":0.8}",
					'models' => '',
				),
			),
		);

		$provider = sanitize_key( (string) $provider );

		return isset( $recipes[ $provider ] ) ? $recipes[ $provider ] : array();
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

if ( ! function_exists( 'flosc_build_model_parameter_preview' ) ) {
	/**
	 * The request as it stands, built from the fields plus anything extra.
	 *
	 * Same idea as the personality preview: the controls above compose it, and
	 * an operator who wants something the controls cannot express edits it
	 * directly. Reading it should answer "what will FLOSC actually send?"
	 * without opening a network tab.
	 *
	 * @param string              $provider FLOSC provider slug.
	 * @param array<string,mixed> $settings Flow settings.
	 * @return string YAML-style lines.
	 */
	function flosc_build_model_parameter_preview( $provider, $settings ) {
		$provider = sanitize_key( (string) $provider );
		$lines    = array();

		$temperature = trim( (string) ( $settings[ 'ai_temperature' ] ?? '' ) );
		$max_tokens  = trim( (string) ( $settings[ 'ai_max_tokens' ] ?? '' ) );

		// Temperature is only part of the request where the provider takes it.
		$skips_temperature = function_exists( 'flosc_provider_rejects_tuning' )
			&& flosc_provider_rejects_tuning( $provider, 'temperature' );

		if ( '' !== $temperature && ! $skips_temperature ) {
			$lines[] = 'temperature: ' . $temperature;
		}

		if ( '' !== $max_tokens ) {
			$lines[] = 'max_tokens: ' . $max_tokens;
		}

		$extra = flosc_parse_model_parameters( (string) ( $settings[ 'ai_' . $provider . '_params' ] ?? '' ) );

		if ( ! is_wp_error( $extra ) ) {
			foreach ( $extra as $key => $value ) {
				$lines[] = $key . ': ' . ( is_scalar( $value ) || null === $value
					? var_export( $value, true )
					: wp_json_encode( $value ) );
			}
		}

		return implode( "\n", $lines );
	}
}

if ( ! function_exists( 'flosc_reconcile_model_parameters' ) ) {
	/**
	 * Fold what the operator wrote back into the fields it names.
	 *
	 * The parameter text wins, so after a save the controls above must show
	 * what it says — otherwise the page displays one number and sends another,
	 * which is the confusion this whole arrangement exists to end.
	 *
	 * temperature and max_tokens move out of the text and into their fields.
	 * Everything else stays in the text, because no field represents it.
	 *
	 * @param array<string,mixed> $settings Flow settings being saved.
	 * @param string              $provider FLOSC provider slug.
	 * @return array<string,mixed>
	 */
	function flosc_reconcile_model_parameters( $settings, $provider ) {
		$provider = sanitize_key( (string) $provider );
		$key      = 'ai_' . $provider . '_params';

		if ( ! isset( $settings[ $key ] ) ) {
			return $settings;
		}

		$parsed = flosc_parse_model_parameters( (string) $settings[ $key ] );

		if ( is_wp_error( $parsed ) ) {
			// Leave what they typed exactly as typed, so the error they see on
			// the page is about the text in front of them.
			return $settings;
		}

		$owned = array(
			'temperature' => 'ai_temperature',
			'max_tokens'  => 'ai_max_tokens',
		);

		foreach ( $owned as $param => $field ) {
			if ( ! array_key_exists( $param, $parsed ) ) {
				continue;
			}

			$value = $parsed[ $param ];

			if ( is_scalar( $value ) ) {
				$settings[ $field ] = (string) $value;
			}

			unset( $parsed[ $param ] );
		}

		// Write the remainder back in the notation it came in.
		$lines = array();

		foreach ( $parsed as $param => $value ) {
			$lines[] = $param . ': ' . ( is_scalar( $value ) || null === $value
				? var_export( $value, true )
				: wp_json_encode( $value ) );
		}

		$settings[ $key ] = implode( "\n", $lines );

		return $settings;
	}
}
