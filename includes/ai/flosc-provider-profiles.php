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
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return array<string,mixed>|null Null when FLOSC knows nothing about it.
	 */
	function flosc_provider_api_profile( $provider ) {
		$profiles = array(
			'anthropic' => array(
				'model_detail_url' => 'https://api.anthropic.com/v1/models/%s',
				// Measured 2026-08-30 against a live key: of the ten models it
				// lists, Opus 5, Sonnet 5, Fable 5, Opus 4.8 and Opus 4.7 answer
				// 400 "`temperature` is deprecated for this model", and all ten
				// answer 200 without it.
				'rejects_tuning'   => array( 'temperature' ),
				'tuning_note'      => __( 'Anthropic has deprecated temperature on its newer models, so FLOSC leaves sampling to Claude.', 'flosc' ),
				// Measured against a live key on 2026-08-30: Sonnet 4.5 takes
				// top_p and top_k, Sonnet 5 refuses them and takes thinking,
				// stop_sequences works on both.
				'example_params'   => "top_p: 0.9\ntop_k: 40\nstop_sequences: [\"User:\"]\nthinking: {\"type\":\"adaptive\"}",
			),
			'openai'    => array(
				// OpenAI's spec documents no per-model capability endpoint of
				// this kind, and nothing here has measured its tuning limits.
				'model_detail_url' => '',
				'rejects_tuning'   => array(),
				'tuning_note'      => '',
				'example_params'   => "top_p: 0.9\npresence_penalty: 0.5\nfrequency_penalty: 0.3\nseed: 42",
			),
			'xai'       => array(
				// /v1/language-models/{id} exists per xAI's reference but has
				// not been measured here, so FLOSC does not call it yet.
				'model_detail_url' => '',
				'rejects_tuning'   => array(),
				'tuning_note'      => '',
				'example_params'   => "top_p: 0.9\npresence_penalty: 0.0\nfrequency_penalty: 0.0\nseed: 12345",
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
