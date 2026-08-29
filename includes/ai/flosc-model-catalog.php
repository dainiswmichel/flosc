<?php
/**
 * Ask an AI provider which models the API key can actually use.
 *
 * FLOSC used to ship hardcoded lists of model ids. Those drift — providers
 * release models faster than any plugin updates — and a stale list is worse
 * than no list, because an id it does not contain cannot even be saved.
 *
 * Two different lists matter here and only one of them works:
 *
 *   what the provider account can see   — this file asks the provider
 *   what the installed plugin carries   — FLOSC_WP_AI_Client::plugin_can_use_model()
 *
 * A model can be live at the provider and absent from the installed WordPress
 * AI Provider plugin's catalog, which is exactly the case that reads as a bad
 * API key and is not one. So every id is returned with a flag saying whether
 * this site can really use it, and the admin sees the difference.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_model_catalog_endpoint' ) ) {
	/**
	 * Where each provider lists its models, and how it wants to be asked.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $api_key  The key to authenticate with.
	 * @return array{url:string,args:array<string,mixed>}|null
	 */
	function flosc_model_catalog_endpoint( $provider, $api_key ) {
		$provider = sanitize_key( (string) $provider );
		$api_key  = (string) $api_key;

		switch ( $provider ) {
			case 'anthropic':
				return array(
					'url'  => 'https://api.anthropic.com/v1/models?limit=100',
					'args' => array(
						'headers' => array(
							'x-api-key'         => $api_key,
							'anthropic-version' => '2023-06-01',
						),
					),
				);

			case 'openai':
				return array(
					'url'  => 'https://api.openai.com/v1/models',
					'args' => array( 'headers' => array( 'Authorization' => 'Bearer ' . $api_key ) ),
				);

			case 'xai':
				return array(
					'url'  => 'https://api.x.ai/v1/models',
					'args' => array( 'headers' => array( 'Authorization' => 'Bearer ' . $api_key ) ),
				);

			case 'gemini':
				return array(
					'url'  => 'https://generativelanguage.googleapis.com/v1beta/models',
					'args' => array( 'headers' => array( 'x-goog-api-key' => $api_key ) ),
				);
		}

		return null;
	}
}

if ( ! function_exists( 'flosc_model_catalog_parse' ) ) {
	/**
	 * Pull model ids out of whichever shape the provider answered with.
	 *
	 * Anthropic and the OpenAI-compatible providers return { data: [ { id } ] };
	 * Gemini returns { models: [ { name: "models/x" } ] }.
	 *
	 * @param array<string,mixed> $body Decoded response body.
	 * @return array<int,array{id:string,label:string}>
	 */
	function flosc_model_catalog_parse( $body ) {
		$out = array();

		if ( ! is_array( $body ) ) {
			return $out;
		}

		$rows = array();

		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			$rows = $body['data'];
		} elseif ( isset( $body['models'] ) && is_array( $body['models'] ) ) {
			$rows = $body['models'];
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = (string) ( $row['id'] ?? $row['name'] ?? '' );

			// Gemini prefixes its ids with "models/".
			if ( 0 === strpos( $id, 'models/' ) ) {
				$id = substr( $id, strlen( 'models/' ) );
			}

			$id = trim( $id );

			if ( '' === $id ) {
				continue;
			}

			$out[] = array(
				'id'    => $id,
				'label' => trim( (string) ( $row['display_name'] ?? $row['displayName'] ?? '' ) ),
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a['id'], $b['id'] );
			}
		);

		return $out;
	}
}

if ( ! function_exists( 'flosc_fetch_model_catalog' ) ) {
	/**
	 * Ask a provider for its model list using the key saved for this flow.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $api_key  The saved key.
	 * @return array{models:array<int,array<string,mixed>>}|WP_Error
	 */
	function flosc_fetch_model_catalog( $provider, $api_key ) {
		$provider = sanitize_key( (string) $provider );

		if ( '' === (string) $api_key ) {
			return new WP_Error(
				'flosc_models_no_key',
				__( 'Save an API key for this provider first — the list comes from the provider, using that key.', 'flosc' )
			);
		}

		$endpoint = flosc_model_catalog_endpoint( $provider, (string) $api_key );

		if ( null === $endpoint ) {
			return new WP_Error(
				'flosc_models_unsupported',
				__( 'This provider does not publish a model list FLOSC can read.', 'flosc' )
			);
		}

		$args            = $endpoint['args'];
		$args['timeout'] = 15;

		$response = wp_remote_get( $endpoint['url'], $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$detail = '';

			if ( is_array( $body ) ) {
				$detail = (string) ( $body['error']['message'] ?? $body['message'] ?? '' );
			}

			return new WP_Error(
				'flosc_models_http_' . $code,
				sprintf(
					/* translators: 1: HTTP status code, 2: the provider's own error text. */
					__( 'The provider answered %1$d. %2$s', 'flosc' ),
					$code,
					$detail
				)
			);
		}

		$models = flosc_model_catalog_parse( $body );

		if ( empty( $models ) ) {
			return new WP_Error(
				'flosc_models_empty',
				__( 'The provider returned no models for this key.', 'flosc' )
			);
		}

		// Mark the ones the installed provider plugin can actually pin. An id
		// the plugin does not carry cannot be used here, however live it is at
		// the provider.
		$checkable = class_exists( 'FLOSC_WP_AI_Client' )
			&& method_exists( 'FLOSC_WP_AI_Client', 'plugin_can_use_model' )
			&& FLOSC_WP_AI_Client::uses_official_plugin( $provider );

		foreach ( $models as $index => $model ) {
			$models[ $index ]['usable'] = $checkable
				? FLOSC_WP_AI_Client::plugin_can_use_model( $provider, $model['id'] )
				: true;
		}

		return array(
			'models'   => $models,
			'checked'  => $checkable,
			'provider' => $provider,
		);
	}
}
