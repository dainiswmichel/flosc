<?php
/**
 * floscAvailableProviders — install-scoped AI credentials available to any floscFlow.
 *
 * Product model:
 * - Keys configured here (or promoted from a flow save) are AVAILABLE install-wide.
 * - Each floscFlow attaches via floscFlowAiPolicy (ai_provider, models, chain order).
 * - Secrets never go into portable Settings YAML.
 *
 * Option key: flosc_available_providers
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_available_providers_option_key' ) ) {
	/**
	 * @return string
	 */
	function flosc_available_providers_option_key() {
		return 'flosc_available_providers';
	}
}

if ( ! function_exists( 'flosc_available_provider_slugs' ) ) {
	/**
	 * Provider ids that may hold an API key in the install pool.
	 *
	 * @return array<int,string>
	 */
	function flosc_available_provider_slugs() {
		return array( 'anthropic', 'openai', 'xai', 'gemini', 'assemblyai' );
	}
}

if ( ! function_exists( 'flosc_chat_provider_slugs' ) ) {
	/**
	 * Providers that send the compiled personality profile as chat system text.
	 * AssemblyAI is STT only and is not in this list.
	 *
	 * @return array<int,string>
	 */
	function flosc_chat_provider_slugs() {
		return array( 'anthropic', 'openai', 'xai', 'gemini' );
	}
}

if ( ! function_exists( 'flosc_install_provider_catalog' ) ) {
	/**
	 * Install-pool providers shown on All Flows / This flow key rows.
	 *
	 * @return array<string,array<string,string>>
	 */
	function flosc_install_provider_catalog() {
		return array(
			'anthropic'  => array(
				'label' => __( 'Anthropic', 'flosc' ),
				'kind'  => 'chat',
				'hint'  => __( 'Chat — Claude API keys via AI Provider for Anthropic. Personality field: system.', 'flosc' ),
				'url'   => 'https://console.anthropic.com/settings/keys',
			),
			'openai'     => array(
				'label' => __( 'OpenAI', 'flosc' ),
				'kind'  => 'chat',
				'hint'  => __( 'Chat — OpenAI API keys via AI Provider for OpenAI. Personality field: system / instructions. Whisper STT stays a FLOSC hop.', 'flosc' ),
				'url'   => 'https://platform.openai.com/api-keys',
			),
			'xai'        => array(
				'label' => __( 'xAI', 'flosc' ),
				'kind'  => 'chat',
				'hint'  => __( 'Chat — Grok API keys. Personality field: system.', 'flosc' ),
				'url'   => 'https://console.x.ai/',
			),
			'gemini'     => array(
				'label' => __( 'Gemini', 'flosc' ),
				'kind'  => 'chat',
				'hint'  => __( 'Chat — Google AI Studio keys via AI Provider for Google. Personality field: systemInstruction.', 'flosc' ),
				'url'   => 'https://aistudio.google.com/apikey',
			),
			'assemblyai' => array(
				'label' => __( 'AssemblyAI', 'flosc' ),
				'kind'  => 'stt',
				'hint'  => __( 'Speech-to-text only (audio quizzes — not a chat personality).', 'flosc' ),
				'url'   => 'https://www.assemblyai.com/app/account',
			),
		);
	}
}

if ( ! function_exists( 'flosc_chat_provider_labels' ) ) {
	/**
	 * Primary-provider select labels, including IVR.
	 *
	 * @return array<string,string>
	 */
	function flosc_chat_provider_labels() {
		return array(
			'ivr'       => __( 'IVR / Scripted only', 'flosc' ),
			'anthropic' => __( 'Anthropic Claude', 'flosc' ),
			'openai'    => __( 'OpenAI', 'flosc' ),
			'xai'       => __( 'xAI Grok', 'flosc' ),
			'gemini'    => __( 'Google Gemini', 'flosc' ),
		);
	}
}

if ( ! function_exists( 'flosc_personality_pack_catalog' ) ) {
	/**
	 * APIs the compiled personality profile is mapped for (same genome, different field).
	 * FLOSC HTTP chat uses anthropic, openai, xai, gemini. The rest are pack shapes.
	 *
	 * @return array<string,array<string,string>>
	 */
	function flosc_personality_pack_catalog() {
		return array(
			'anthropic'      => array(
				'label' => __( 'Anthropic', 'flosc' ),
				'field' => 'system',
				'api'   => 'POST /v1/messages',
			),
			'openai'         => array(
				'label' => __( 'OpenAI', 'flosc' ),
				'field' => 'instructions / messages[].role=system',
				'api'   => 'POST /v1/chat/completions',
			),
			'xai'            => array(
				'label' => __( 'xAI', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /v1/chat/completions',
			),
			'gemini'         => array(
				'label' => __( 'Gemini', 'flosc' ),
				'field' => 'systemInstruction',
				'api'   => 'POST /v1beta/models/{model}:generateContent',
			),
			'mistral'        => array(
				'label' => __( 'Mistral', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /v1/chat/completions',
			),
			'cohere'         => array(
				'label' => __( 'Cohere', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /v2/chat',
			),
			'meta_together'  => array(
				'label' => __( 'Together (Meta)', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /v1/chat/completions',
			),
			'meta_fireworks' => array(
				'label' => __( 'Fireworks (Meta)', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /v1/chat/completions',
			),
			'aws_bedrock'    => array(
				'label' => __( 'AWS Bedrock', 'flosc' ),
				'field' => 'system',
				'api'   => 'POST /model/{modelId}/invoke (Bedrock runtime)',
			),
			'azure_openai'   => array(
				'label' => __( 'Azure OpenAI', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /openai/deployments/{deployment}/chat/completions',
			),
			'openrouter'     => array(
				'label' => __( 'OpenRouter', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /api/v1/chat/completions',
			),
			'perplexity'     => array(
				'label' => __( 'Perplexity', 'flosc' ),
				'field' => 'messages[].role=system',
				'api'   => 'POST /chat/completions',
			),
		);
	}
}

if ( ! function_exists( 'flosc_personality_pack_label_list' ) ) {
	/**
	 * @return string
	 */
	function flosc_personality_pack_label_list() {
		$labels = array();
		foreach ( flosc_personality_pack_catalog() as $row ) {
			$labels[] = (string) $row['label'];
		}
		return implode( ', ', $labels );
	}
}

if ( ! function_exists( 'flosc_provider_intricacies_mts' ) ) {
	/**
	 * Michel Time Stamp for the provider-intricacies snapshot (calendar day).
	 *
	 * @return string
	 */
	function flosc_provider_intricacies_mts() {
		return '26_08m_20d';
	}
}

if ( ! function_exists( 'flosc_provider_intricacies' ) ) {
	/**
	 * What each API currently wants for the same compiled personality.
	 * Vintage: flosc_provider_intricacies_mts(). Re-date when a vendor changes the pocket.
	 *
	 * @return array{live:array<string,array<string,string>>,packs:array<string,array<string,string>>}
	 */
	function flosc_provider_intricacies() {
		return array(
			'live'  => array(
				'anthropic' => array(
					'label'  => __( 'Anthropic Claude', 'flosc' ),
					'wants'  => __( 'Top-level system string. Not a message. Messages are only user/assistant (and tool). max_tokens is required. FLOSC does not send temperature on this hop — Claude then uses its default. Prompt cache likes a stable system block; keep the compiled profile identical across turns.', 'flosc' ),
					'path'   => __( 'POST /v1/messages · personality → system', 'flosc' ),
					'models' => __( 'FLOSC default claude-sonnet-4-5-20250929. Vendor line as of this MTS also includes Claude Opus 5 / Fable 5 families — pick a current ID if a slug 404s.', 'flosc' ),
				),
				'openai'    => array(
					'label'  => __( 'OpenAI', 'flosc' ),
					'wants'  => __( 'Chat Completions: first messages item with role system. Responses API (off in FLOSC unless enabled): top-level instructions. previous_response_id does not carry instructions — resend the profile on each Responses call, or stay on Chat Completions (FLOSC default). Sampling belongs beside the soul, not inside it.', 'flosc' ),
					'path'   => __( 'POST /v1/chat/completions · personality → messages.system (Responses path uses instructions)', 'flosc' ),
					'models' => __( 'FLOSC default gpt-4o-mini. Newer GPT-5-class IDs exist on some accounts; save a current ID if the default is retired.', 'flosc' ),
				),
				'xai'       => array(
					'label'  => __( 'xAI Grok', 'flosc' ),
					'wants'  => __( 'Chat Completions (OpenAI-shaped): messages role system. Vendor docs also show a Responses-style input system item — FLOSC talks Chat Completions. Retired Grok-2 slugs 404; remap to a live ID.', 'flosc' ),
					'path'   => __( 'POST /v1/chat/completions · personality → messages.system', 'flosc' ),
					'models' => __( 'FLOSC default grok-4.5. Vendor flagship on this MTS is grok-4.6 — switch on This flow if your key is on 4.6 only.', 'flosc' ),
				),
				'gemini'    => array(
					'label'  => __( 'Google Gemini', 'flosc' ),
					'wants'  => __( 'REST generateContent: systemInstruction parts text (text only). Conversation is contents with roles user and model — never assistant. First content must be user. Key in x-goog-api-key. Temperature lives in generationConfig, not in the personality.', 'flosc' ),
					'path'   => __( 'POST generateContent · personality → systemInstruction', 'flosc' ),
					'models' => __( 'FLOSC default gemini-2.5-flash. 2.5 Pro / Flash-Lite also listed. Gemini 3.x uses thinking_level; do not stuff chain-of-thought into the soul to fake it.', 'flosc' ),
				),
				'ivr'       => array(
					'label' => __( 'IVR (scripted)', 'flosc' ),
					'wants' => __( 'Nothing from the model. No system field. Personality is stored, not spoken, until you attach a chat API.', 'flosc' ),
					'path'  => __( 'Local IVR only', 'flosc' ),
				),
			),
			'packs' => array(
				'mistral'        => array(
					'label' => __( 'Mistral', 'flosc' ),
					'wants' => __( 'Chat Completions: optional first message role system. Same genome as FLOSC OpenAI-shape. Personality traits belong there; do not hide them only in the latest user turn.', 'flosc' ),
				),
				'cohere'         => array(
					'label' => __( 'Cohere v2', 'flosc' ),
					'wants' => __( 'messages role system as the first item (replaces v1 preamble). System outranks later roles. Compatibility API may want role developer instead of system — native v2 wants system.', 'flosc' ),
				),
				'meta_together'  => array(
					'label' => __( 'Together (Meta)', 'flosc' ),
					'wants' => __( 'OpenAI-compatible Chat Completions. First system message. Same profile text.', 'flosc' ),
				),
				'meta_fireworks' => array(
					'label' => __( 'Fireworks (Meta)', 'flosc' ),
					'wants' => __( 'OpenAI-compatible Chat Completions. First system message. Same profile text.', 'flosc' ),
				),
				'aws_bedrock'    => array(
					'label' => __( 'AWS Bedrock (Claude)', 'flosc' ),
					'wants' => __( 'Claude-on-Bedrock Messages: top-level system string, same as Anthropic. Invoke URL is regional. This is a pack shape in FLOSC, not a live Bedrock hop.', 'flosc' ),
				),
				'azure_openai'   => array(
					'label' => __( 'Azure OpenAI', 'flosc' ),
					'wants' => __( 'Chat Completions on a deployment URL. Role system for most models; some reasoning SKUs prefer developer. Sampling still stays off the soul.', 'flosc' ),
				),
				'openrouter'     => array(
					'label' => __( 'OpenRouter', 'flosc' ),
					'wants' => __( 'OpenAI-compatible Chat Completions. First system message. Router picks an upstream model; the genome stays yours.', 'flosc' ),
				),
				'perplexity'     => array(
					'label' => __( 'Perplexity Sonar', 'flosc' ),
					'wants' => __( 'OpenAI-shaped messages role system for tone and grounding rules only. Search is seeded from the user message, not the system profile. Sonar chat-completions is migrating to Agent API; Sonar support cited through 26_09m_27d.', 'flosc' ),
				),
			),
		);
	}
}

if ( ! function_exists( 'flosc_render_provider_intricacies_html' ) ) {
	/**
	 * Dated provider-intricacies list for admin UI.
	 *
	 * @return void
	 */
	function flosc_render_provider_intricacies_html() {
		$mts  = flosc_provider_intricacies_mts();
		$data = flosc_provider_intricacies();
		echo '<details class="flosc-provider-intricacies" id="flosc-provider-intricacies" open>';
		echo '<summary>';
		echo esc_html__( 'What each API wants for this personality', 'flosc' );
		echo ' · MTS ' . esc_html( $mts );
		echo '</summary>';
		echo '<p class="description">';
		echo esc_html__( 'One compiled Markdown genome. Each vendor only chooses the pocket it goes in. Sampling (temperature, max tokens) is This-flow policy, not soul. Re-date this list when a vendor moves the pocket.', 'flosc' );
		echo '</p>';
		echo '<h4>' . esc_html__( 'FLOSC actually calls', 'flosc' ) . '</h4>';
		echo '<ul>';
		foreach ( $data['live'] as $row ) {
			echo '<li><strong>' . esc_html( $row['label'] ) . '</strong> — ' . esc_html( $row['wants'] );
			if ( ! empty( $row['path'] ) ) {
				echo ' <em>' . esc_html( $row['path'] ) . '</em>';
			}
			if ( ! empty( $row['models'] ) ) {
				echo ' ' . esc_html( $row['models'] );
			}
			echo '</li>';
		}
		echo '</ul>';
		echo '<h4>' . esc_html__( 'Same genome, other pockets (not extra FLOSC hops)', 'flosc' ) . '</h4>';
		echo '<ul>';
		foreach ( $data['packs'] as $row ) {
			echo '<li><strong>' . esc_html( $row['label'] ) . '</strong> — ' . esc_html( $row['wants'] ) . '</li>';
		}
		echo '</ul>';
		echo '</details>';
	}
}

if ( ! function_exists( 'flosc_available_providers_flow_key_map' ) ) {
	/**
	 * Map provider slug → flow bag key for the secret.
	 *
	 * @return array<string,string>
	 */
	function flosc_available_providers_flow_key_map() {
		return array(
			'anthropic'  => 'anthropic_api_key',
			'openai'     => 'openai_api_key',
			'xai'        => 'xai_api_key',
			'gemini'     => 'gemini_api_key',
			'assemblyai' => 'assemblyai_api_key',
		);
	}
}

if ( ! function_exists( 'flosc_available_providers_get_all' ) ) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function flosc_available_providers_get_all() {
		$raw = get_option( flosc_available_providers_option_key(), array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$out = array();
		foreach ( flosc_available_provider_slugs() as $slug ) {
			$row = isset( $raw[ $slug ] ) && is_array( $raw[ $slug ] ) ? $raw[ $slug ] : array();
			$out[ $slug ] = array(
				'api_key'    => isset( $row['api_key'] ) ? (string) $row['api_key'] : '',
				'label'      => isset( $row['label'] ) ? (string) $row['label'] : '',
				'updated_at' => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
			);
		}
		return $out;
	}
}

if ( ! function_exists( 'flosc_available_providers_save_all' ) ) {
	/**
	 * @param array<string,array<string,mixed>> $providers Full map.
	 * @return void
	 */
	function flosc_available_providers_save_all( $providers ) {
		if ( ! is_array( $providers ) ) {
			return;
		}
		$clean = array();
		foreach ( flosc_available_provider_slugs() as $slug ) {
			$row = isset( $providers[ $slug ] ) && is_array( $providers[ $slug ] ) ? $providers[ $slug ] : array();
			$key = isset( $row['api_key'] ) ? (string) $row['api_key'] : '';
			// Preserve existing secret when empty submit (password field blank).
			if ( $key === '' && isset( $row['keep_existing'] ) && $row['keep_existing'] ) {
				$existing = flosc_available_providers_get_all();
				$key      = (string) ( $existing[ $slug ]['api_key'] ?? '' );
			}
			$clean[ $slug ] = array(
				'api_key'    => $key,
				'label'      => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				'updated_at' => $key !== '' ? current_time( 'mysql' ) : (string) ( $row['updated_at'] ?? '' ),
			);
		}
		update_option( flosc_available_providers_option_key(), $clean, false );
	}
}

if ( ! function_exists( 'flosc_available_providers_set_key' ) ) {
	/**
	 * @param string $provider Provider slug.
	 * @param string $api_key  Secret (empty clears).
	 * @return void
	 */
	function flosc_available_providers_set_key( $provider, $api_key ) {
		$provider = sanitize_key( (string) $provider );
		if ( ! in_array( $provider, flosc_available_provider_slugs(), true ) ) {
			return;
		}
		$all = flosc_available_providers_get_all();
		$all[ $provider ]['api_key']    = (string) $api_key;
		$all[ $provider ]['updated_at'] = $api_key !== '' ? current_time( 'mysql' ) : '';
		flosc_available_providers_save_all( $all );
	}
}

if ( ! function_exists( 'flosc_available_providers_has_key' ) ) {
	/**
	 * @param string $provider Provider slug.
	 * @return bool
	 */
	function flosc_available_providers_has_key( $provider ) {
		$all = flosc_available_providers_get_all();
		$provider = sanitize_key( (string) $provider );
		return $provider !== '' && ! empty( $all[ $provider ]['api_key'] );
	}
}

if ( ! function_exists( 'flosc_available_providers_promote_from_flow' ) ) {
	/**
	 * When a flow saves a non-empty key, copy it into the install pool so it is
	 * available to other flows (floscAvailableProviders product rule).
	 *
	 * @param array<string,mixed> $flow_settings Flow bag after save.
	 * @return void
	 */
	function flosc_available_providers_promote_from_flow( $flow_settings ) {
		if ( ! is_array( $flow_settings ) ) {
			return;
		}
		$map = flosc_available_providers_flow_key_map();
		foreach ( $map as $provider => $flow_key ) {
			$val = isset( $flow_settings[ $flow_key ] ) ? trim( (string) $flow_settings[ $flow_key ] ) : '';
			if ( $val !== '' ) {
				flosc_available_providers_set_key( $provider, $val );
			}
		}
	}
}

if ( ! function_exists( 'flosc_get_provider_api_key' ) ) {
	/**
	 * Resolve API key for a provider: flow bag first, then floscAvailableProviders.
	 *
	 * @param string      $provider Provider slug (anthropic|openai|xai|assemblyai).
	 * @param string|null $flow_id  Optional flow stem for flosc_get_setting.
	 * @return string
	 */
	function flosc_get_provider_api_key( $provider, $flow_id = null ) {
		$provider = sanitize_key( (string) $provider );
		$map      = flosc_available_providers_flow_key_map();
		$flow_key = $map[ $provider ] ?? '';
		$from_flow = '';
		if ( $flow_key !== '' && function_exists( 'flosc_get_setting' ) ) {
			$from_flow = trim( (string) flosc_get_setting( $flow_key, '', $flow_id ) );
		}
		if ( $from_flow !== '' ) {
			return $from_flow;
		}
		$all = flosc_available_providers_get_all();
		return isset( $all[ $provider ]['api_key'] ) ? trim( (string) $all[ $provider ]['api_key'] ) : '';
	}
}

if ( ! function_exists( 'flosc_admin_save_available_providers' ) ) {
	/**
	 * admin-post.php?action=flosc_save_available_providers
	 *
	 * @return void
	 */
	function flosc_admin_save_available_providers() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AI providers.', 'flosc' ) );
		}
		check_admin_referer( 'flosc_save_available_providers' );

		$posted = array();
		if ( isset( $_POST['avail_api_key'] ) && is_array( $_POST['avail_api_key'] ) ) {
			$posted = array_map( 'sanitize_text_field', wp_unslash( $_POST['avail_api_key'] ) );
		}
		$clear = array();
		if ( isset( $_POST['avail_clear'] ) && is_array( $_POST['avail_clear'] ) ) {
			$clear = array_map( 'sanitize_text_field', wp_unslash( $_POST['avail_clear'] ) );
		}

		$all = flosc_available_providers_get_all();
		foreach ( flosc_available_provider_slugs() as $slug ) {
			if ( ! empty( $clear[ $slug ] ) ) {
				$all[ $slug ]['api_key']    = '';
				$all[ $slug ]['updated_at'] = '';
				continue;
			}
			$new = isset( $posted[ $slug ] ) ? trim( (string) $posted[ $slug ] ) : '';
			if ( $new !== '' ) {
				$all[ $slug ]['api_key']    = $new;
				$all[ $slug ]['updated_at'] = current_time( 'mysql' );
			}
			// blank password field = keep existing
		}
		flosc_available_providers_save_all( $all );

		set_transient(
			'flosc_ai_all_notice_' . get_current_user_id(),
			array(
				'message' => __( 'Available providers saved.', 'flosc' ),
				'type'    => 'success',
			),
			60
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ivr = isset( $_POST['flosc_return_ivr'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['flosc_return_ivr'] ) ) : '';
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'flosc-settings',
					'tab'  => 'ai',
					'view' => 'all',
					'ivr'  => $ivr,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
	add_action( 'admin_post_flosc_save_available_providers', 'flosc_admin_save_available_providers' );
}
