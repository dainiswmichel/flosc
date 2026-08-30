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
 * Each request below follows the provider's own published reference, checked
 * rather than recalled:
 *
 *   Anthropic  GET /v1/models        headers x-api-key + anthropic-version,
 *                                    limit 1..1000, pages via has_more/last_id
 *                                    → { data: [ { id, display_name } ] }
 *                                    platform.claude.com/docs/en/api/models-list
 *   OpenAI     GET /v1/models        Bearer auth, no pagination
 *                                    → { object: "list", data: [ { id } ] }
 *                                    github.com/openai/openai-openapi openapi.yaml
 *   Gemini     GET /v1beta/models    header x-goog-api-key, pageSize max 1000,
 *                                    pages via nextPageToken
 *                                    → { models: [ { name: "models/x",
 *                                         displayName, supportedGenerationMethods } ] }
 *                                    generativelanguage.googleapis.com discovery doc
 *   xAI        GET /v1/language-models   Bearer auth, no pagination
 *                                    → { models: [ { id, aliases } ] }
 *                                    docs.x.ai rest-api-reference/inference/models
 *                                    Not /v1/models: that one lists image and
 *                                    video generation models alongside the chat
 *                                    ones, and offering grok-imagine-image as a
 *                                    conversation model would be a lie.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many pages to walk before giving up. No provider comes close to this.
 */
if ( ! defined( 'FLOSC_MODEL_CATALOG_MAX_PAGES' ) ) {
	define( 'FLOSC_MODEL_CATALOG_MAX_PAGES', 10 );
}

if ( ! function_exists( 'flosc_model_catalog_request' ) ) {
	/**
	 * Build one page request for a provider's model list.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $api_key  The key to authenticate with.
	 * @param string $cursor   Page cursor from the previous page, or ''.
	 * @return array{url:string,args:array<string,mixed>}|null
	 */
	function flosc_model_catalog_request( $provider, $api_key, $cursor = '' ) {
		$provider = sanitize_key( (string) $provider );
		$api_key  = (string) $api_key;
		$cursor   = (string) $cursor;

		switch ( $provider ) {
			case 'anthropic':
				$query = array( 'limit' => 1000 );

				if ( '' !== $cursor ) {
					$query['after_id'] = $cursor;
				}

				return array(
					'url'  => add_query_arg( $query, 'https://api.anthropic.com/v1/models' ),
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
					'url'  => 'https://api.x.ai/v1/language-models',
					'args' => array( 'headers' => array( 'Authorization' => 'Bearer ' . $api_key ) ),
				);

			case 'gemini':
				$query = array( 'pageSize' => 1000 );

				if ( '' !== $cursor ) {
					$query['pageToken'] = $cursor;
				}

				return array(
					'url'  => add_query_arg( $query, 'https://generativelanguage.googleapis.com/v1beta/models' ),
					'args' => array( 'headers' => array( 'x-goog-api-key' => $api_key ) ),
				);
		}

		return null;
	}
}

if ( ! function_exists( 'flosc_model_catalog_page' ) ) {
	/**
	 * Read one page of models out of whichever shape the provider answered with.
	 *
	 * Gemini's list is not only chat models — it carries embedding, TTS and
	 * tuned entries too, and the provider says which is which in
	 * supportedGenerationMethods. An id that cannot generateContent would only
	 * fail later, so it is dropped here.
	 *
	 * @param string              $provider FLOSC provider slug.
	 * @param array<string,mixed> $body     Decoded response body.
	 * @return array{models:array<int,array{id:string,label:string}>,cursor:string}
	 */
	function flosc_model_catalog_page( $provider, $body ) {
		$provider = sanitize_key( (string) $provider );
		$models   = array();
		$cursor   = '';

		if ( ! is_array( $body ) ) {
			return array(
				'models' => $models,
				'cursor' => $cursor,
			);
		}

		if ( 'gemini' === $provider ) {
			$rows = isset( $body['models'] ) && is_array( $body['models'] ) ? $body['models'] : array();

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$methods = isset( $row['supportedGenerationMethods'] ) && is_array( $row['supportedGenerationMethods'] )
					? $row['supportedGenerationMethods']
					: array();

				if ( ! in_array( 'generateContent', $methods, true ) ) {
					continue;
				}

				// Ids are resource names: "models/gemini-2.5-flash".
				$id = (string) ( $row['name'] ?? '' );

				if ( 0 === strpos( $id, 'models/' ) ) {
					$id = substr( $id, strlen( 'models/' ) );
				}

				$id = trim( $id );

				if ( '' === $id ) {
					continue;
				}

				$models[] = array(
					'id'    => $id,
					'label' => trim( (string) ( $row['displayName'] ?? '' ) ),
				);
			}

			$cursor = trim( (string) ( $body['nextPageToken'] ?? '' ) );

			return array(
				'models' => $models,
				'cursor' => $cursor,
			);
		}

		if ( 'xai' === $provider ) {
			$rows = isset( $body['models'] ) && is_array( $body['models'] ) ? $body['models'] : array();

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$id = trim( (string) ( $row['id'] ?? '' ) );

				if ( '' === $id ) {
					continue;
				}

				$models[] = array(
					'id'    => $id,
					'label' => '',
				);

				// xAI documents aliases as ids the model field accepts. An alias
				// survives the next version turning over, so it is worth offering.
				$aliases = isset( $row['aliases'] ) && is_array( $row['aliases'] ) ? $row['aliases'] : array();

				foreach ( $aliases as $alias ) {
					$alias = trim( (string) $alias );

					if ( '' === $alias ) {
						continue;
					}

					$models[] = array(
						'id'    => $alias,
						/* translators: %s: the model id this alias points at. */
						'label' => sprintf( __( 'alias for %s', 'flosc' ), $id ),
					);
				}
			}

			// xAI does not page this endpoint.
			return array(
				'models' => $models,
				'cursor' => '',
			);
		}

		// Anthropic and OpenAI: { data: [ { id } ] }.
		$rows = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = trim( (string) ( $row['id'] ?? '' ) );

			if ( '' === $id ) {
				continue;
			}

			$models[] = array(
				'id'    => $id,
				'label' => trim( (string) ( $row['display_name'] ?? '' ) ),
			);
		}

		// Anthropic pages with has_more + last_id; OpenAI does not page.
		if ( 'anthropic' === $provider && ! empty( $body['has_more'] ) ) {
			$cursor = trim( (string) ( $body['last_id'] ?? '' ) );
		}

		return array(
			'models' => $models,
			'cursor' => $cursor,
		);
	}
}

if ( ! function_exists( 'flosc_fetch_model_catalog' ) ) {
	/**
	 * Ask a provider for its model list using the key saved for this flow.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $api_key  The saved key.
	 * @return array{models:array<int,array<string,mixed>>,checked:bool,provider:string}|WP_Error
	 */
	function flosc_fetch_model_catalog( $provider, $api_key ) {
		$provider = sanitize_key( (string) $provider );

		if ( '' === (string) $api_key ) {
			return new WP_Error(
				'flosc_models_no_key',
				__( 'Save an API key for this provider first — the list comes from the provider, using that key.', 'flosc' )
			);
		}

		if ( null === flosc_model_catalog_request( $provider, (string) $api_key ) ) {
			return new WP_Error(
				'flosc_models_unsupported',
				__( 'This provider does not publish a model list FLOSC can read.', 'flosc' )
			);
		}

		$models = array();
		$seen   = array();
		$cursor = '';

		for ( $page = 0; $page < FLOSC_MODEL_CATALOG_MAX_PAGES; $page++ ) {
			$request = flosc_model_catalog_request( $provider, (string) $api_key, $cursor );

			if ( null === $request ) {
				break;
			}

			$args            = $request['args'];
			$args['timeout'] = 15;

			$response = wp_remote_get( $request['url'], $args );

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

			$parsed = flosc_model_catalog_page( $provider, $body );

			foreach ( $parsed['models'] as $model ) {
				if ( isset( $seen[ $model['id'] ] ) ) {
					continue;
				}

				$seen[ $model['id'] ] = true;
				$models[]             = $model;
			}

			$cursor = (string) $parsed['cursor'];

			if ( '' === $cursor ) {
				break;
			}
		}

		if ( empty( $models ) ) {
			return new WP_Error(
				'flosc_models_empty',
				__( 'The provider returned no models for this key.', 'flosc' )
			);
		}

		usort(
			$models,
			static function ( $a, $b ) {
				return strcmp( $a['id'], $b['id'] );
			}
		);

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

if ( ! function_exists( 'flosc_default_model' ) ) {
	/**
	 * The model id FLOSC uses for a provider when the operator has not chosen one.
	 *
	 * These used to be written out by hand in six different files, which is how
	 * FLOSC ended up shipping ids their providers had already retired. A default
	 * is a fact about the outside world, so it lives in one place and every
	 * caller reads it from here.
	 *
	 * Current as of the references checked in this file's header. When one goes
	 * stale the operator is not stranded: the model field says so by name and
	 * "Fetch models this key can use" lists what the key can actually run.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @return string Empty when the provider has no default (IVR, or unknown).
	 */
	function flosc_default_model( $provider ) {
		$defaults = array(
			'anthropic' => 'claude-sonnet-5',
			'openai'    => 'gpt-5.4-mini',
			'xai'       => 'grok-4.6',
			'gemini'    => 'gemini-3.7-flash',
		);

		return (string) ( $defaults[ sanitize_key( (string) $provider ) ] ?? '' );
	}
}
