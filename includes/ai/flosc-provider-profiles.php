<?php
/**
 * What each AI provider's API is shaped like.
 *
 * FLOSC is developed against Anthropic because that is the provider whose
 * behaviour can be measured here directly. The danger in that is writing
 * Anthropic into the logic — "if anthropic, do this" — until every other
 * provider is an afterthought bolted to the side of it.
 *
 * So provider differences live here as data. Anthropic is one filled-in row,
 * not a branch. Supporting another provider fully means measuring it and
 * filling its row; it does not mean editing the code that reads these rows.
 *
 * Every value below is either measured against the live API or taken from the
 * provider's published reference. A field left empty means "not measured yet",
 * which is different from "the provider does not do this" — and the code that
 * reads it must treat the two the same way: do nothing rather than assume.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_provider_api_profile' ) ) {
	/**
	 * The API shape for one provider.
	 *
	 * Fields:
	 *   model_detail_url    sprintf template taking the model id, or '' when the
	 *                       provider publishes no per-model description FLOSC
	 *                       can read.
	 *   rejects_tuning      request parameters the provider refuses. FLOSC omits
	 *                       these rather than sending them and failing.
	 *   tuning_note         why, in one line, for the operator.
	 *   example_params      a few parameters that provider is known to take,
	 *                       shown as placeholder text. Examples only — FLOSC
	 *                       sends whatever is typed and the provider rules on
	 *                       it, so this list being incomplete costs nothing.
	 *   docs_url            where that provider documents its own request body,
	 *                       for the parameter FLOSC has no note on. A link the
	 *                       operator follows; FLOSC never fetches it.
	 *   param_doc_url       sprintf template taking a parameter name, landing on
	 *                       that provider's entry for it rather than the top of
	 *                       the page. Filled in only where the anchor scheme has
	 *                       been read off the live page; '' everywhere else, and
	 *                       the caller falls back to docs_url rather than
	 *                       inventing an anchor that would land nowhere.
	 *   model_parameter_notes  measured per-model differences, keyed by the
	 *                       leading part of a model id. Parameters differ
	 *                       between two models of the same provider more often
	 *                       than between providers, and only measurement can
	 *                       say so — an empty list means nobody has measured
	 *                       that provider's models here, not that its models
	 *                       are all alike.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return array<string,mixed>|null Null when FLOSC knows nothing about it.
	 */
	function flosc_provider_api_profile( $provider ) {
		$profiles = array(
			'anthropic' => array(
				// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- model metadata, not a prompt: this reads one model's context window, maximum reply length and capabilities so the admin screen can show them. wp_ai_client_prompt() sends prompts and cannot describe a model. Requested only when an administrator clicks "Describe this model"; declared in readme.txt External Services.
				'model_detail_url' => 'https://api.anthropic.com/v1/models/%s',
				// Measured 2026-08-30 against a live key: of the ten models it
				// lists, Opus 5, Sonnet 5, Fable 5, Opus 4.8 and Opus 4.7 answer
				// 400 "`temperature` is deprecated for this model", and all ten
				// answer 200 without it.
				'rejects_tuning'   => array( 'temperature' ),
				// Measured 2026-08-30 on claude-sonnet-4-5-20250929: each of
				// temperature and top_p is accepted alone; the pair is 400
				// "`temperature` and `top_p` cannot both be specified".
				'sampling_exclusive' => array( 'temperature', 'top_p' ),
				'tuning_note'      => __( 'Anthropic has deprecated temperature on its newer models, so FLOSC leaves sampling to Claude. Temperature and top_p cannot be sent together.', 'flosc' ),
				// Measured against a live key on 2026-08-30: Sonnet 4.5 takes
				// top_p and top_k, Sonnet 5 refuses them and takes thinking,
				// stop_sequences works on both.
				'example_params'   => "top_p: 0.9\ntop_k: 40\nstop_sequences: [\"User:\"]\nthinking: {\"type\":\"adaptive\"}",
				'docs_url'         => 'https://platform.claude.com/docs/en/api/messages/create',
				// Read off that page on 2026-08-30: every top-level body
				// parameter it documents is anchored #create.<name>, all
				// nineteen of them, so a parameter added later is reachable by
				// the same template rather than by another edit here.
				'param_doc_url'    => 'https://platform.claude.com/docs/en/api/messages/create#create.%s',
				// Measured 2026-08-30 against a live key, one request per
				// parameter per model, reading the 200 or the 400 back.
				'model_parameter_notes' => array(
					'claude-sonnet-4-5' => array(
						'accepts' => array( 'temperature', 'top_p', 'top_k', 'stop_sequences' ),
						'refuses' => array( 'thinking' ),
						// Each accepts-entry is true alone. The pair is not.
						'exclusive' => array( array( 'temperature', 'top_p' ) ),
					),
					'claude-sonnet-5'   => array(
						'accepts' => array( 'stop_sequences', 'thinking' ),
						'refuses' => array( 'temperature', 'top_p', 'top_k' ),
					),
					// The temperature 400 was seen on each of these; nothing
					// else has been tried on them, so nothing else is claimed.
					'claude-opus-5'     => array(
						'accepts' => array(),
						'refuses' => array( 'temperature' ),
					),
					'claude-fable-5'    => array(
						'accepts' => array(),
						'refuses' => array( 'temperature' ),
					),
					'claude-opus-4-8'   => array(
						'accepts' => array(),
						'refuses' => array( 'temperature' ),
					),
					'claude-opus-4-7'   => array(
						'accepts' => array(),
						'refuses' => array( 'temperature' ),
					),
				),
			),
			'openai'    => array(
				// OpenAI's spec documents no per-model capability endpoint of
				// this kind, and nothing here has measured its tuning limits.
				'model_detail_url' => '',
				'rejects_tuning'   => array(),
				'tuning_note'      => '',
				'example_params'   => "top_p: 0.9\npresence_penalty: 0.5\nfrequency_penalty: 0.3\nseed: 42",
				'docs_url'         => 'https://platform.openai.com/docs/api-reference/chat/create',
				// Anchor scheme not read off the live page, so no per-parameter
				// link is offered and the reader is sent to the page itself.
				'param_doc_url'    => '',
				'model_parameter_notes' => array(),
			),
			'xai'       => array(
				// /v1/language-models/{id} exists per xAI's reference but has
				// not been measured here, so FLOSC does not call it yet.
				'model_detail_url' => '',
				'rejects_tuning'   => array(),
				'tuning_note'      => '',
				'example_params'   => "top_p: 0.9\npresence_penalty: 0.0\nfrequency_penalty: 0.0\nseed: 12345",
				'docs_url'         => 'https://docs.x.ai/docs/api-reference',
				// Anchor scheme not read off the live page, so no per-parameter
				// link is offered and the reader is sent to the page itself.
				'param_doc_url'    => '',
				'model_parameter_notes' => array(),
			),
			'gemini'    => array(
				// GET /v1beta/models/{model} exists per Google's reference but
				// has not been measured here.
				'model_detail_url' => '',
				'rejects_tuning'   => array(),
				'tuning_note'      => '',
				// Gemini nests sampling inside generationConfig rather than
				// putting it at the top level.
				'example_params'   => "generationConfig: {\"temperature\":0.4,\"topP\":0.95}",
				'docs_url'         => 'https://ai.google.dev/api/generate-content',
				// Anchor scheme not read off the live page, so no per-parameter
				// link is offered and the reader is sent to the page itself.
				'param_doc_url'    => '',
				'model_parameter_notes' => array(),
			),
		);

		$provider = sanitize_key( (string) $provider );

		return isset( $profiles[ $provider ] ) ? $profiles[ $provider ] : null;
	}
}

if ( ! function_exists( 'flosc_provider_rejects_tuning' ) ) {
	/**
	 * Whether a provider refuses a given request parameter.
	 *
	 * Unknown provider, or a parameter nobody has measured, answers false —
	 * FLOSC sends what it was configured to send rather than silently dropping
	 * a setting on a guess.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $param    Parameter name, e.g. 'temperature'.
	 * @return bool
	 */
	function flosc_provider_rejects_tuning( $provider, $param ) {
		$profile = flosc_provider_api_profile( $provider );

		if ( null === $profile ) {
			return false;
		}

		return in_array( (string) $param, (array) $profile['rejects_tuning'], true );
	}
}

if ( ! function_exists( 'flosc_provider_model_detail_url' ) ) {
	/**
	 * Where to ask a provider about one model, or '' when FLOSC cannot.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $model    Model id.
	 * @return string
	 */
	function flosc_provider_model_detail_url( $provider, $model ) {
		$profile = flosc_provider_api_profile( $provider );
		$model   = trim( (string) $model );

		if ( null === $profile || '' === $profile['model_detail_url'] || '' === $model ) {
			return '';
		}

		return sprintf( $profile['model_detail_url'], rawurlencode( $model ) );
	}
}

if ( ! function_exists( 'flosc_provider_docs_url' ) ) {
	/**
	 * Where the provider documents its own request body.
	 *
	 * For the parameter FLOSC has no note on — which is every parameter shipped
	 * after this file was last edited. FLOSC never fetches this; the operator
	 * opens it.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return string URL, or '' when FLOSC has none for that provider.
	 */
	function flosc_provider_docs_url( $provider ) {
		$profile = flosc_provider_api_profile( $provider );

		return null === $profile ? '' : (string) ( $profile['docs_url'] ?? '' );
	}
}

if ( ! function_exists( 'flosc_provider_model_parameter_note' ) ) {
	/**
	 * What has been measured about one model's parameters.
	 *
	 * Matched on the leading part of the model id, because providers date their
	 * ids — claude-sonnet-4-5-20250929 is the model the note about
	 * claude-sonnet-4-5 was measured on. The longest matching prefix wins, so a
	 * note about a specific dated build beats a note about its family.
	 *
	 * An unmeasured model answers with empty lists. The caller must show that
	 * as "not measured", never as "accepts nothing".
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $model    Model id.
	 * @return array{accepts:array<int,string>,refuses:array<int,string>,matched:string}
	 */
	function flosc_provider_model_parameter_note( $provider, $model ) {
		$empty   = array(
			'accepts' => array(),
			'refuses' => array(),
			'matched' => '',
		);
		$profile = flosc_provider_api_profile( $provider );
		$model   = trim( (string) $model );

		if ( null === $profile || '' === $model ) {
			return $empty;
		}

		$notes = isset( $profile['model_parameter_notes'] ) ? (array) $profile['model_parameter_notes'] : array();
		$best  = $empty;

		foreach ( $notes as $prefix => $note ) {
			$prefix = (string) $prefix;

			if ( 0 !== strpos( $model, $prefix ) ) {
				continue;
			}

			if ( strlen( $prefix ) <= strlen( $best['matched'] ) ) {
				continue;
			}

			$best = array(
				'accepts' => array_values( (array) ( $note['accepts'] ?? array() ) ),
				'refuses' => array_values( (array) ( $note['refuses'] ?? array() ) ),
				'matched' => $prefix,
			);
		}

		return $best;
	}
}

if ( ! function_exists( 'flosc_provider_param_doc_url' ) ) {
	/**
	 * Where the provider explains one parameter, in the provider's own words.
	 *
	 * FLOSC's note beside a parameter is a paraphrase written for an operator.
	 * It is not the authority and should never be mistaken for it, so every
	 * note carries the way out to the reference it was written from.
	 *
	 * Falls back to the provider's request reference where the anchor scheme
	 * has not been read off the live page — a page the reader has to scan is
	 * still the right page, and an invented anchor lands nowhere and reads as
	 * a broken link.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $param    Parameter name.
	 * @return string URL, or '' when FLOSC has nowhere to send them.
	 */
	function flosc_provider_param_doc_url( $provider, $param ) {
		$profile = flosc_provider_api_profile( $provider );
		$param   = trim( (string) $param );

		if ( null === $profile ) {
			return '';
		}

		$template = (string) ( $profile['param_doc_url'] ?? '' );

		if ( '' === $template || '' === $param ) {
			return (string) ( $profile['docs_url'] ?? '' );
		}

		return sprintf( $template, rawurlencode( $param ) );
	}
}

if ( ! function_exists( 'flosc_model_rejects_tuning' ) ) {
	/**
	 * Whether this model refuses a parameter — model first, provider second.
	 *
	 * Capability belongs to the model wherever a model has been measured.
	 * Anthropic's Sonnet 4.5 accepts temperature and its Sonnet 5 refuses it,
	 * so "does Anthropic take temperature" is the wrong question and answering
	 * it provider-wide suppressed a setting that works.
	 *
	 * The order is: what was measured on this model, then what was measured on
	 * the provider, then no. An unmeasured parameter on an unmeasured model is
	 * sent as configured — FLOSC does not refuse on a guess, it lets the
	 * provider answer and reports what it said.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $model    Model id, may be empty.
	 * @param string $param    Parameter name, e.g. 'temperature'.
	 * @return bool
	 */
	function flosc_model_rejects_tuning( $provider, $model, $param ) {
		$param = (string) $param;
		$note  = flosc_provider_model_parameter_note( $provider, $model );

		if ( '' !== $note['matched'] ) {
			if ( in_array( $param, $note['accepts'], true ) ) {
				return false;
			}

			if ( in_array( $param, $note['refuses'], true ) ) {
				return true;
			}
		}

		return flosc_provider_rejects_tuning( $provider, $param );
	}
}

if ( ! function_exists( 'flosc_sampling_conflicts_with_applied' ) ) {
	/**
	 * Whether adding $param would 400 because a sibling sampling control is already on the request.
	 *
	 * Anthropic Sonnet 4.5 accepts temperature and accepts top_p; it refuses
	 * both on the same request. The first-class temperature field is applied
	 * first, so this is how top_p from the request is held back rather than
	 * sent and failed.
	 *
	 * @param string        $provider FLOSC provider slug.
	 * @param string        $param    Parameter about to be applied.
	 * @param array<int,string> $already_applied Names already on the builder.
	 * @return bool
	 */
	function flosc_sampling_conflicts_with_applied( $provider, $param, $already_applied ) {
		$param    = (string) $param;
		$applied  = is_array( $already_applied ) ? $already_applied : array();
		$profile  = flosc_provider_api_profile( $provider );
		$group    = ( null !== $profile && isset( $profile['sampling_exclusive'] ) )
			? (array) $profile['sampling_exclusive']
			: array();

		if ( count( $group ) < 2 || ! in_array( $param, $group, true ) ) {
			return false;
		}

		foreach ( $group as $other ) {
			if ( (string) $other !== $param && in_array( (string) $other, $applied, true ) ) {
				return true;
			}
		}

		return false;
	}
}
