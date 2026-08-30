<?php
/**
 * Ask an AI provider which models the API key can actually use.
 *
 * FLOSC used to ship hardcoded lists of model ids. Those drift — providers
 * release models faster than any plugin updates — and a stale list is worse
 * than no list, because an id it does not contain cannot even be saved.
 *
 * The list this returns is what the provider says the key can use. That is the
 * whole claim. Whether a given id then runs on this site is settled by making
 * the call — the connection test does exactly that — not by FLOSC inspecting a
 * provider plugin's registry and guessing on its behalf.
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
	 * @return array{models:array<int,array<string,mixed>>,provider:string}|WP_Error
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

				// Anthropic refuses an identity-linked key that does not name a
				// workspace, and refuses it for chat as well as for this list —
				// so the operator is not one header away from working, they are
				// using a kind of key the provider plugin cannot drive at all.
				// Say that, rather than relaying a status code.
				if ( false !== stripos( $detail, 'anthropic-workspace-id' ) ) {
					return new WP_Error(
						'flosc_models_workspace_required',
						__( 'This Anthropic key is set to "All workspaces", and Anthropic then requires a workspace id on every request. The AI Provider for Anthropic plugin cannot send one, so this key cannot run chat here no matter how FLOSC is configured. Make a new key in the Anthropic Console and set Scope to a single workspace — the dialog will say "This key only works in the ... workspace" — then paste that one. The key list shows the same thing in its Workspace column: anything other than "All workspaces" works. Who the key was created for makes no difference.', 'flosc' )
					);
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

		return array(
			'models'   => $models,
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
			// Proven to resolve through AI Provider for Anthropic on a real
			// install. A default is only worth what the installed provider
			// plugin can actually pin — a newer id that the plugin cannot
			// resolve turns a working connection into a failing one, which is
			// exactly what changing this to claude-sonnet-5 did.
			'anthropic' => 'claude-sonnet-4-5-20250929',
			'openai'    => 'gpt-5.4-mini',
			'xai'       => 'grok-4.6',
			'gemini'    => 'gemini-3.7-flash',
		);

		return (string) ( $defaults[ sanitize_key( (string) $provider ) ] ?? '' );
	}
}

if ( ! function_exists( 'flosc_fetch_model_details' ) ) {
	/**
	 * Ask the provider to describe one model.
	 *
	 * Anthropic publishes a per-model endpoint carrying the real context
	 * window, the real maximum output, and a capability tree. FLOSC used to
	 * cap Max Tokens at a hardcoded 4096, a number belonging to no model —
	 * Sonnet 5 allows 128,000 and Haiku 4.5 allows 64,000. Reading the limit
	 * from the model beats inventing one.
	 *
	 * What this cannot answer: sampling. There is no temperature entry in the
	 * capability tree, so whether a model accepts temperature is only knowable
	 * by making a request. Do not present this as a complete settings list.
	 *
	 * @param string $provider FLOSC provider slug.
	 * @param string $api_key  The saved key.
	 * @param string $model    Model id to describe.
	 * @return array<string,mixed>|WP_Error
	 */
	function flosc_fetch_model_details( $provider, $api_key, $model ) {
		$provider = sanitize_key( (string) $provider );
		$model    = trim( (string) $model );

		if ( 'anthropic' !== $provider ) {
			return new WP_Error(
				'flosc_model_details_unsupported',
				__( 'Only Anthropic publishes a per-model description FLOSC can read.', 'flosc' )
			);
		}

		if ( '' === (string) $api_key ) {
			return new WP_Error( 'flosc_model_details_no_key', __( 'Save an API key first — the description comes from the provider.', 'flosc' ) );
		}

		if ( '' === $model ) {
			return new WP_Error( 'flosc_model_details_no_model', __( 'Choose a model first.', 'flosc' ) );
		}

		$response = wp_remote_get(
			'https://api.anthropic.com/v1/models/' . rawurlencode( $model ),
			array(
				'timeout' => 15,
				'headers' => array(
					'x-api-key'         => (string) $api_key,
					'anthropic-version' => '2023-06-01',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			$detail = is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '';

			return new WP_Error(
				'flosc_model_details_http_' . $code,
				sprintf(
					/* translators: 1: HTTP status code, 2: the provider's own error text. */
					__( 'The provider answered %1$d. %2$s', 'flosc' ),
					$code,
					$detail
				)
			);
		}

		return flosc_model_details_summarise( $body );
	}
}

if ( ! function_exists( 'flosc_model_details_summarise' ) ) {
	/**
	 * Turn a model description into the few facts an operator acts on.
	 *
	 * @param array<string,mixed> $body Decoded model object.
	 * @return array<string,mixed>
	 */
	function flosc_model_details_summarise( $body ) {
		$caps = isset( $body['capabilities'] ) && is_array( $body['capabilities'] ) ? $body['capabilities'] : array();

		$supported = static function ( $node ) {
			return is_array( $node ) && ! empty( $node['supported'] );
		};

		$features = array();

		if ( $supported( $caps['image_input'] ?? null ) ) {
			$features[] = __( 'reads images', 'flosc' );
		}

		if ( $supported( $caps['pdf_input'] ?? null ) ) {
			$features[] = __( 'reads PDFs', 'flosc' );
		}

		if ( $supported( $caps['structured_outputs'] ?? null ) ) {
			$features[] = __( 'structured output', 'flosc' );
		}

		if ( $supported( $caps['citations'] ?? null ) ) {
			$features[] = __( 'citations', 'flosc' );
		}

		if ( $supported( $caps['code_execution'] ?? null ) ) {
			$features[] = __( 'code execution', 'flosc' );
		}

		if ( $supported( $caps['thinking'] ?? null ) ) {
			$types = isset( $caps['thinking']['types'] ) && is_array( $caps['thinking']['types'] ) ? $caps['thinking']['types'] : array();
			$names = array();

			foreach ( array( 'adaptive', 'enabled' ) as $type ) {
				if ( $supported( $types[ $type ] ?? null ) ) {
					$names[] = $type;
				}
			}

			$features[] = empty( $names )
				? __( 'thinking', 'flosc' )
				: sprintf( /* translators: %s: comma separated thinking types. */ __( 'thinking (%s)', 'flosc' ), implode( ', ', $names ) );
		}

		$levels = array();

		if ( $supported( $caps['effort'] ?? null ) ) {
			foreach ( array( 'low', 'medium', 'high', 'xhigh', 'max' ) as $level ) {
				if ( $supported( $caps['effort'][ $level ] ?? null ) ) {
					$levels[] = $level;
				}
			}
		}

		return array(
			'id'               => (string) ( $body['id'] ?? '' ),
			'display_name'     => (string) ( $body['display_name'] ?? '' ),
			'max_input_tokens' => (int) ( $body['max_input_tokens'] ?? 0 ),
			'max_tokens'       => (int) ( $body['max_tokens'] ?? 0 ),
			'features'         => $features,
			'effort_levels'    => $levels,
		);
	}
}
