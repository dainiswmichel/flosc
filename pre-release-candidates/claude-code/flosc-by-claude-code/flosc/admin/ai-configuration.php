<?php
/**
 * FLOSC AI Configuration Tab
 *
 * Configures AI provider connections, model tuning, personality,
 * and phase-specific behavior for each FLOSC flow.
 *
 * Moved all inline styles to assets/css/flosc-admin.css
 *         Removed hardcoded default prompts (floscAdmin configures all)
 *         Added provider-aware show/hide for API key sections
 *         Cleaned up unbuilt feature UI
 *
 * @package FLOSC
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Identity before input.
 *
 * WordPress.org, 14 Sep 2026: "No nonce check found validating input origin on
 * lines 1-116". Their scanner measures whether a check appears BEFORE the
 * request is read, not merely whether one exists somewhere in the file. Several
 * files here verified correctly and verified late, and late did not count -- an
 * unauthorized request still walked the whole parser before being refused.
 *
 * This is the capability the FLOSC menu itself requires. Flow-level access is
 * still checked further down where the flow is known; this only establishes
 * that somebody who may administer FLOSC at all is asking.
 */
if ( ! current_user_can( 'edit_others_posts' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'flosc' ), 403 );
}

flosc_tab_header( '🤖', 'AI' );

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? array();
$flosc_current_ivr   = $GLOBALS['flosc_current_ivr'] ?? '';

/*
 * Which view of the AI tab to paint.
 *
 * A $GLOBALS['flosc_get'] indirection stood here, with a bulk wp_unslash( $_GET )
 * fallback behind it. Nothing in the plugin ever writes that global, so the
 * indirection was always false and the fallback was always the real read -- it
 * pulled in the entire query string to select one of two words. The allowlist
 * below is the whole rule now, including the default, so no corrective if()
 * follows it.
 */
$flosc_ai_view        = flosc_nav_param( 'view', array( 'single', 'all' ), 'single' );
$flosc_personality_id = sanitize_key( (string) ( $flosc_flow_settings['personality_library_id'] ?? '' ) );
if ( '' === $flosc_personality_id && function_exists( 'flosc_personality_library_id_for_flow' ) ) {
	$flosc_personality_id = flosc_personality_library_id_for_flow(
		sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) )
	);
}
$flosc_ai_all_url  = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'tab'  => 'ai',
		'view' => 'all',
		'ivr'  => $flosc_current_ivr,
	),
	admin_url( 'admin.php' )
);
$flosc_ai_docs_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'documentation',
		'doc'  => 'ref-admin',
	),
	admin_url( 'admin.php' )
) . '#tab-ai';

// Close main settings form for All Flows (sibling forms).
if ( 'all' === $flosc_ai_view ) {
	if ( empty( $GLOBALS['flosc_settings_form_closed_early'] ) ) {
		echo '</form>';
		$GLOBALS['flosc_settings_form_closed_early'] = true;
	}
	?>
<div class="flosc-docs-link-wrap">
	<a href="<?php echo esc_url( $flosc_ai_docs_url ); ?>" class="flosc-docs-link"><?php echo esc_html__( 'Docs', 'flosc' ); ?></a>
</div>
	<?php
	flosc_render_ai_tab_nav( $flosc_ai_view, $flosc_current_ivr );
	require FLOSC_PLUGIN_DIR . 'admin/ai-all-flows.php';
	return;
}

$flosc_ai_provider        = $flosc_flow_settings['ai_provider'] ?? 'ivr';
$flosc_ai_openai_model    = $flosc_flow_settings['ai_openai_model'] ?? flosc_default_model( 'openai' );
$flosc_ai_anthropic_model = $flosc_flow_settings['ai_anthropic_model'] ?? flosc_default_model( 'anthropic' );
$flosc_ai_xai_model       = $flosc_flow_settings['ai_xai_model'] ?? flosc_default_model( 'xai' );
$flosc_ai_gemini_model    = $flosc_flow_settings['ai_gemini_model'] ?? flosc_default_model( 'gemini' );
// Retired xAI slugs no longer resolve on api.x.ai — surface current default in the UI.
$flosc_xai_legacy_models = array( 'grok-2-latest', 'grok-2', 'grok-2-1212', 'grok-beta', 'grok-vision-beta' );
if ( in_array( $flosc_ai_xai_model, $flosc_xai_legacy_models, true ) ) {
	$flosc_ai_xai_model = flosc_default_model( 'xai' );
}
$flosc_ai_temperature        = $flosc_flow_settings['ai_temperature'] ?? '0.3';
$flosc_ai_max_tokens         = $flosc_flow_settings['ai_max_tokens'] ?? '500';
$flosc_enable_ivr_context    = $flosc_flow_settings['ai_enable_ivr_context'] ?? true;
$flosc_enable_content_access = $flosc_flow_settings['ai_enable_content_access'] ?? true;
$flosc_ai_response_mode      = $flosc_flow_settings['ai_response_mode'] ?? 'enhanced';
$flosc_enable_chaining       = $flosc_flow_settings['ai_enable_chaining'] ?? false;
$flosc_chain_provider_1      = $flosc_flow_settings['ai_chain_provider_1'] ?? '';
$flosc_chain_provider_2      = $flosc_flow_settings['ai_chain_provider_2'] ?? '';
$flosc_chain_provider_3      = $flosc_flow_settings['ai_chain_provider_3'] ?? '';
$flosc_personas              = function_exists( 'flosc_personality_library_get_all' ) ? flosc_personality_library_get_all() : array();
$flosc_personality_label     = isset( $flosc_personas[ $flosc_personality_id ]['label'] ) && '' !== $flosc_personas[ $flosc_personality_id ]['label']
	? (string) $flosc_personas[ $flosc_personality_id ]['label']
	: $flosc_personality_id;
$flosc_avail                 = function_exists( 'flosc_available_providers_get_all' ) ? flosc_available_providers_get_all() : array();

// Risk / setup notices — read directly from flow settings (get_current_flow() is null in admin context).
// No calendar-age nags for lesson catalog (stable catalogs can be fine for years).
$flosc_notices = array();
if ( (float) $flosc_ai_temperature > 0.5 ) {
	$flosc_notices[] = '<strong>Temperature ' . esc_html( $flosc_ai_temperature ) . ' increases fabrication risk.</strong> Recommended: 0.3';
}
?>
<?php if ( ! empty( $flosc_notices ) ) : ?>
<div class="flosc-margin-bottom-20">
	<?php foreach ( $flosc_notices as $flosc_notice ) : ?>
	<div class="notice notice-warning inline flosc-notice-compact">
		<p class="flosc-text-zero-margin">
		<?php
		echo wp_kses(
			$flosc_notice,
			array(
				'strong' => array(),
				'a'      => array(
					'href'  => array(),
					'class' => array(),
				),
			)
		);
		?>
											</p>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="flosc-docs-link-wrap">
	<a href="<?php echo esc_url( $flosc_ai_docs_url ); ?>" class="flosc-docs-link">Docs</a>
</div>

<?php flosc_render_ai_tab_nav( 'single', $flosc_current_ivr ); ?>
<?php
/*
Fail-closed visibility: if this flow has no resolvable personality
	profile, the chat refuses unpersonified answers — tell the admin why.
 */
$flosc_admin_stem    = sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) );
$flosc_admin_profile = ( '' !== $flosc_admin_stem && function_exists( 'flosc_personality_compiled_profile' ) )
	? trim( (string) flosc_personality_compiled_profile( $flosc_admin_stem ) )
	: '';
if ( '' === $flosc_admin_profile ) :
	?>
<div class="notice notice-error flosc-inline-notice">
	<p><strong><?php esc_html_e( 'Personality not configured.', 'flosc' ); ?></strong>
	<?php echo esc_html__( 'This flow refuses AI replies until a personality loads — visitors see a setup notice instead of answers. Attach one above (applies instantly), or write a custom profile and save.', 'flosc' ); ?></p>
</div>
<?php endif; ?>

<!-- Styles in assets/css/flosc-admin.css (AI Configuration section) -->

<div class="flosc-ai-config">

<h2><?php echo esc_html__( 'This flow: AI settings', 'flosc' ); ?></h2>
<p class="description flosc-ai-intro">
	<?php echo esc_html__( 'Attach one personality and one chat API. Chat APIs: Anthropic, OpenAI, xAI, Gemini (or IVR scripted only). Speech-to-text: AssemblyAI, OpenAI Whisper, or a custom endpoint. Install-wide keys live under All Flows AI API Management. APIs can be chained; personalities cannot.', 'flosc' ); ?>
</p>

<?php
$flosc_avail_bits  = array();
$flosc_key_catalog = function_exists( 'flosc_install_provider_catalog' ) ? flosc_install_provider_catalog() : array();
foreach ( $flosc_key_catalog as $flosc_slug => $flosc_meta ) {
	$flosc_lab          = isset( $flosc_meta['label'] ) ? (string) $flosc_meta['label'] : $flosc_slug;
	$flosc_avail_bits[] = $flosc_lab . ( ! empty( $flosc_avail[ $flosc_slug ]['api_key'] ) ? ' ✓' : ' —' );
}
?>
<p class="description">
	<strong><?php echo esc_html__( 'Install keys available:', 'flosc' ); ?></strong>
	<?php echo esc_html( implode( ' · ', $flosc_avail_bits ) ); ?>
	— <a href="<?php echo esc_url( $flosc_ai_all_url ); ?>"><?php echo esc_html__( 'Manage', 'flosc' ); ?></a>
</p>

<details class="flosc-ai-acc" id="flosc-personality-section" open>
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Attached Personality', 'flosc' ); ?> <?php echo esc_html( $flosc_personality_label ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Attach personality. Input Sticky Aspects. Enable Sticky for Users.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">
<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_personality_library_id"><?php echo esc_html__( 'Attached personality', 'flosc' ); ?></label></th>
		<td>
			<select name="flow_personality_library_id" id="flow_personality_library_id">
				<option value="" <?php selected( $flosc_personality_id, '' ); ?>><?php echo esc_html__( 'No attached personality', 'flosc' ); ?></option>
				<?php foreach ( $flosc_personas as $flosc_pid => $flosc_p ) : ?>
				<option value="<?php echo esc_attr( $flosc_pid ); ?>" <?php selected( $flosc_personality_id, $flosc_pid ); ?>>
					<?php echo esc_html( '' !== $flosc_p['label'] ? $flosc_p['label'] : $flosc_pid ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php echo esc_html__( 'This flow’s personality. Choosing here attaches it immediately and reloads the designer below.', 'flosc' ); ?>
			</p>
			<p id="flosc-personality-attach-note" class="description flosc-hidden" role="status"></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_brand_facts"><?php echo esc_html__( 'Sticky aspects', 'flosc' ); ?></label></th>
		<td>
			<textarea name="flow_ai_brand_facts" id="flow_ai_brand_facts" rows="5" class="large-text code" placeholder="<?php echo esc_attr__( 'e.g. A cleaning is 45 minutes. We do not treat under-6s. Dr Vaida is the only endodontist on staff.', 'flosc' ); ?>"><?php echo esc_textarea( (string) ( $GLOBALS['flosc_current_settings']['ai_brand_facts'] ?? '' ) ); ?></textarea>
			<p class="description">
				<?php echo esc_html__( 'Aspects and facts that belong to this flow rather than to the personality. They stay put when you attach a different personality, and the same personality can carry different sticky aspects on another flow — so BubblyBetty can sell dental cleanings here and legal consultations somewhere else.', 'flosc' ); ?>
			</p>
			<p class="description">
				<?php echo esc_html__( 'Written into every AI prompt exactly as you type them, under a standing instruction never to invent alternatives. Leave empty for none; nothing is assumed on your behalf.', 'flosc' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Sticky for User', 'flosc' ); ?></th>
		<td>
			<?php
			$flosc_enable_user_sticky = false;
			if ( '' !== $flosc_personality_id && function_exists( 'flosc_personality_library_get' ) ) {
				$flosc_sticky_row         = flosc_personality_library_get( $flosc_personality_id );
				$flosc_enable_user_sticky = is_array( $flosc_sticky_row ) && ! empty( $flosc_sticky_row['enable_user_sticky'] );
			}
			?>
			<label for="flosc_personality_enable_user_sticky">
				<input type="checkbox" name="flosc_personality_enable_user_sticky" id="flosc_personality_enable_user_sticky" value="1" <?php checked( $flosc_enable_user_sticky ); ?> <?php disabled( '' === $flosc_personality_id ); ?>>
				<?php echo esc_html__( 'Enable Sticky for Users', 'flosc' ); ?>
			</label>
			<p class="description">
				<?php echo esc_html__( 'When this personality is attached, include that signed-in user’s private “Sticky for User” profile note in their AI turns. The note is authored on the WordPress user profile. It is never shown verbatim to the visitor. Attach a personality to use this switch.', 'flosc' ); ?>
			</p>
		</td>
	</tr>
</table>
</div>
</details>
<?php
if ( function_exists( 'flosc_render_personality_designer_accordion' ) ) {
	flosc_render_personality_designer_accordion( $flosc_personality_id, (string) $flosc_current_ivr );
}
?>

<details class="flosc-ai-acc">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Provider, Keys, and Test', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'This flow’s AI API. Fetch, describe, tune, and chain available AI models.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">

<!-- ============================================ -->
<!-- SECTION 1: PROVIDER SELECTION -->
<!-- ============================================ -->
<h3 class="flosc-ai-section-heading">
	<?php echo esc_html__( 'Primary AI Provider', 'flosc' ); ?>
</h3>

<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_ai_provider">Primary AI Provider</label></th>
		<td>
			<select name="flow_ai_provider" id="flow_ai_provider" class="flosc-ai-provider-select">
				<option value="ivr" <?php selected( $flosc_ai_provider, 'ivr' ); ?>>IVR Only (Scripted Responses)</option>
				<option value="anthropic" <?php selected( $flosc_ai_provider, 'anthropic' ); ?>>Anthropic Claude</option>
				<option value="openai" <?php selected( $flosc_ai_provider, 'openai' ); ?>>OpenAI</option>
				<option value="xai" <?php selected( $flosc_ai_provider, 'xai' ); ?>>xAI Grok</option>
				<option value="gemini" <?php selected( $flosc_ai_provider, 'gemini' ); ?>>Google Gemini</option>
			</select>
			<p class="description">
				<strong>IVR:</strong> Uses your configured messages only (no AI, no costs).<br>
				<strong>OpenAI, Anthropic, Gemini:</strong> Chat goes through <code>wp_ai_client_prompt()</code>. Activate the official plugin for each provider you Test. This flow still attaches only one. Paste the API key in FLOSC — not in Settings → Connectors.<br>
				<strong>xAI:</strong> No official WordPress provider plugin yet; FLOSC calls xAI directly.
			</p>
			<?php
			if ( class_exists( 'FLOSC_WP_AI_Client' ) ) {
				echo wp_kses_post( FLOSC_WP_AI_Client::plugin_status_table_html() );
			}
			?>
		</td>
	</tr>
</table>

<!-- ============================================ -->
<!-- SECTION 2: API KEYS (provider-aware visibility) -->
<!-- ============================================ -->
<h3 class="flosc-ai-section-heading">
	🔑 Step 2: Add Your API Key
</h3>
<p class="description">
	Configure the API key for your selected provider. Only the relevant section is shown.
</p>
<p class="flosc-show-all-keys-row">
	<label>
		<input type="checkbox" id="flosc-show-all-providers">
		<?php echo esc_html__( 'Show every provider, so more than one key can be saved', 'flosc' ); ?>
	</label>
	<span class="description"><?php echo esc_html__( 'Each key saves on its own button and is kept separately. Which provider this flow uses is the setting above.', 'flosc' ); ?></span>
</p>

<?php
if ( ! function_exists( 'flosc_ai_key_state_line' ) ) :
	/**
	 * Say, on the page and permanently, whether a key is stored for this provider.
	 *
	 * The save confirmation is a banner at the top of a long tab. Pressing Save at
	 * the foot of the page and being scrolled to a notice you never see is not
	 * feedback — it leaves "did that work?" unanswered, and a key that is fine
	 * looks like a key that never saved. The field answers for itself instead.
	 *
	 * It also separates two things that look identical in an empty box: no key
	 * anywhere, and no key on THIS flow while an install-wide one is doing the work.
	 *
	 * @param string              $provider FLOSC provider slug.
	 * @param array<string,mixed> $bag      This flow's settings.
	 * @return void
	 */
	function flosc_ai_key_state_line( $provider, $bag ) {
		$provider = sanitize_key( (string) $provider );
		$on_flow  = trim( (string) ( $bag[ $provider . '_api_key' ] ?? '' ) );
		$in_use   = function_exists( 'flosc_get_provider_api_key' )
		? trim( (string) flosc_get_provider_api_key( $provider ) )
		: $on_flow;

		$tail = static function ( $key ) {
			return strlen( $key ) >= 4 ? substr( $key, -4 ) : '';
		};

		if ( '' !== $on_flow ) {
			printf(
				'<p class="flosc-key-state flosc-key-state--ok">%s</p>',
				esc_html(
					sprintf(
					/* translators: %s: last four characters of the saved key. */
						__( 'Saved on this flow — ends %s', 'flosc' ),
						$tail( $on_flow )
					)
				)
			);
			return;
		}

		if ( '' !== $in_use ) {
			printf(
				'<p class="flosc-key-state flosc-key-state--ok">%s</p>',
				esc_html(
					sprintf(
					/* translators: %s: last four characters of the key in use. */
						__( 'Nothing saved on this flow. The install-wide key is being used — ends %s', 'flosc' ),
						$tail( $in_use )
					)
				)
			);
			return;
		}

		printf(
			'<p class="flosc-key-state flosc-key-state--none">%s</p>',
			esc_html__( 'No key saved yet. Paste one, then Save AI Settings.', 'flosc' )
		);
	}
endif;
?>

<!-- Anthropic -->
<div class="flosc-ai-provider-section" data-provider="anthropic">
<table class="form-table">
	<tr>
		<th scope="row">
			<label for="flow_anthropic_api_key"><strong>Anthropic API Key</strong></label>
		</th>
		<td>
			<input type="password" id="flow_anthropic_api_key" name="flow_anthropic_api_key" value="<?php echo esc_attr( function_exists( 'flosc_admin_secret_input_value' ) ? flosc_admin_secret_input_value( $flosc_flow_settings['anthropic_api_key'] ?? '' ) : ( current_user_can( 'manage_options' ) ? (string) ( $flosc_flow_settings['anthropic_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-ant-api03-...">
			<p class="flosc-model-fetch-row">
				<button type="button" class="button button-primary flosc-save-key" data-provider="anthropic" data-target="flow_anthropic_api_key">
					<?php echo esc_html__( 'Save Anthropic Key', 'flosc' ); ?>
				</button>
				<span class="description flosc-save-key-status" data-for="flow_anthropic_api_key"></span>
			</p>
			<?php flosc_ai_key_state_line( 'anthropic', $flosc_flow_settings ); ?>
			<p class="description">
				<a href="https://console.anthropic.com/settings/keys" target="_blank" class="button button-secondary flosc-ai-key-link">
					📥 Get Your Anthropic API Key Here
				</a><br>
				<span class="flosc-ai-key-desc">Best for context handling and following complex instructions.</span>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_anthropic_model">Anthropic Model</label></th>
		<td>
			<?php
			// A typed field with suggestions, not a fixed list. Model ids change
			// faster than any list shipped in a plugin, and the ids that work are
			// the ones the installed provider plugin carries — so the operator
			// must always be able to enter one FLOSC has never heard of.
			$flosc_anthropic_model_options = array(
				'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
				'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
				'claude-sonnet-5'            => 'Claude Sonnet 5',
				'claude-opus-5'              => 'Claude Opus 5',
			);
			?>
			<input type="text" name="flow_ai_anthropic_model" id="flow_ai_anthropic_model"
				class="regular-text flosc-ai-model-select"
				list="flosc-anthropic-model-list"
				value="<?php echo esc_attr( $flosc_ai_anthropic_model ); ?>"
				placeholder="claude-sonnet-4-5-20250929">
			<datalist id="flosc-anthropic-model-list">
				<?php foreach ( $flosc_anthropic_model_options as $flosc_a_id => $flosc_a_label ) : ?>
					<option value="<?php echo esc_attr( $flosc_a_id ); ?>"><?php echo esc_html( $flosc_a_label ); ?></option>
				<?php endforeach; ?>
			</datalist>
			<p class="flosc-model-fetch-row">
				<button type="button" class="button flosc-fetch-models" data-provider="anthropic" data-target="flow_ai_anthropic_model">
					<?php echo esc_html__( 'Fetch models this key can use', 'flosc' ); ?>
				</button>
				<button type="button" class="button flosc-describe-model" data-provider="anthropic" data-target="flow_ai_anthropic_model">
					<?php echo esc_html__( 'Describe this model', 'flosc' ); ?>
				</button>
				<button type="submit" name="flosc_save" value="1" form="flosc-settings-form" class="button button-primary">
					<?php echo esc_html__( 'Save AI Settings', 'flosc' ); ?>
				</button>
				<span class="description flosc-model-fetch-status" data-for="flow_ai_anthropic_model"></span>
			</p>
			<div class="flosc-model-picker" data-for="flow_ai_anthropic_model" hidden></div>
			<div class="flosc-model-detail" data-for="flow_ai_anthropic_model" hidden></div>
			<p class="description">The model id to use for this flow. Fetch lists what this key can use; any current id can also be typed in.</p>
		</td>
	</tr>
</table>
</div>

<!-- OpenAI -->
<div class="flosc-ai-provider-section" data-provider="openai">
<table class="form-table">
	<tr>
		<th scope="row">
			<label for="flow_openai_api_key"><strong>OpenAI API Key</strong></label>
		</th>
		<td>
			<input type="password" id="flow_openai_api_key" name="flow_openai_api_key" value="<?php echo esc_attr( function_exists( 'flosc_admin_secret_input_value' ) ? flosc_admin_secret_input_value( $flosc_flow_settings['openai_api_key'] ?? '' ) : ( current_user_can( 'manage_options' ) ? (string) ( $flosc_flow_settings['openai_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-proj-...">
			<p class="flosc-model-fetch-row">
				<button type="button" class="button button-primary flosc-save-key" data-provider="openai" data-target="flow_openai_api_key">
					<?php echo esc_html__( 'Save OpenAI Key', 'flosc' ); ?>
				</button>
				<span class="description flosc-save-key-status" data-for="flow_openai_api_key"></span>
			</p>
			<?php flosc_ai_key_state_line( 'openai', $flosc_flow_settings ); ?>
			<p class="description">
				<a href="https://platform.openai.com/api-keys" target="_blank" class="button button-secondary flosc-ai-key-link">
					📥 Get Your OpenAI API Key Here
				</a><br>
				<span class="flosc-ai-key-desc">Fast and cost-effective for general conversations.</span>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_openai_model">OpenAI Model</label></th>
		<td>
			<?php
			$flosc_openai_model_options = array(
				'gpt-5.5'      => 'GPT-5.5',
				'gpt-5.4'      => 'GPT-5.4',
				'gpt-5.4-mini' => 'GPT-5.4 mini',
				'gpt-5.4-nano' => 'GPT-5.4 nano',
			);
			?>
			<input type="text" name="flow_ai_openai_model" id="flow_ai_openai_model"
				class="regular-text flosc-ai-model-select"
				list="flosc-openai-model-list"
				value="<?php echo esc_attr( $flosc_ai_openai_model ); ?>"
				placeholder="gpt-5.4-mini">
			<datalist id="flosc-openai-model-list">
				<?php foreach ( $flosc_openai_model_options as $flosc_o_id => $flosc_o_label ) : ?>
					<option value="<?php echo esc_attr( $flosc_o_id ); ?>"><?php echo esc_html( $flosc_o_label ); ?></option>
				<?php endforeach; ?>
			</datalist>
			<p class="flosc-model-fetch-row">
				<button type="button" class="button flosc-fetch-models" data-provider="openai" data-target="flow_ai_openai_model">
					<?php echo esc_html__( 'Fetch models this key can use', 'flosc' ); ?>
				</button>
				<button type="submit" name="flosc_save" value="1" form="flosc-settings-form" class="button button-primary">
					<?php echo esc_html__( 'Save AI Settings', 'flosc' ); ?>
				</button>
				<span class="description flosc-model-fetch-status" data-for="flow_ai_openai_model"></span>
			</p>
			<div class="flosc-model-picker" data-for="flow_ai_openai_model" hidden></div>
			<p class="description">The model id to use for this flow. Fetch lists what this key can use; any current id can also be typed in.</p>
		</td>
	</tr>
</table>
</div>

<!-- xAI -->
<div class="flosc-ai-provider-section" data-provider="xai">
<table class="form-table">
	<tr>
		<th scope="row">
			<label for="flow_xai_api_key"><strong>xAI API Key</strong></label>
		</th>
		<td>
			<input type="password" id="flow_xai_api_key" name="flow_xai_api_key" value="<?php echo esc_attr( function_exists( 'flosc_admin_secret_input_value' ) ? flosc_admin_secret_input_value( $flosc_flow_settings['xai_api_key'] ?? '' ) : ( current_user_can( 'manage_options' ) ? (string) ( $flosc_flow_settings['xai_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="xai-...">
			<p class="flosc-model-fetch-row">
				<button type="button" class="button button-primary flosc-save-key" data-provider="xai" data-target="flow_xai_api_key">
					<?php echo esc_html__( 'Save xAI Key', 'flosc' ); ?>
				</button>
				<span class="description flosc-save-key-status" data-for="flow_xai_api_key"></span>
			</p>
			<?php flosc_ai_key_state_line( 'xai', $flosc_flow_settings ); ?>
			<p class="description">
				<a href="https://console.x.ai" target="_blank" class="button button-secondary flosc-ai-key-link">
					📥 Get Your xAI API Key Here
				</a><br>
				<span class="flosc-ai-key-desc">Grok models from xAI.</span>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_xai_model">xAI Model</label></th>
		<td>
			<?php
			// Typed field with suggestions, not a fixed list — the ids that work
			// are whichever ones xAI currently serves this key. Fetch asks.
			// xAI aliases <modelname> to the latest stable release and
			// <modelname>-latest to the newest, so the alias keeps working after
			// a version turns over. Older ids retire on announced dates.
			$flosc_xai_model_options = array(
				'grok-4.6'        => 'Grok 4.6',
				'grok-4.6-latest' => 'Grok 4.6 (latest)',
			);
			?>
			<input type="text" name="flow_ai_xai_model" id="flow_ai_xai_model"
				class="regular-text flosc-ai-model-select"
				list="flosc-xai-model-list"
				value="<?php echo esc_attr( $flosc_ai_xai_model ); ?>"
				placeholder="grok-4.6">
			<datalist id="flosc-xai-model-list">
				<?php foreach ( $flosc_xai_model_options as $flosc_xai_id => $flosc_xai_label ) : ?>
					<option value="<?php echo esc_attr( $flosc_xai_id ); ?>"><?php echo esc_html( $flosc_xai_label ); ?></option>
				<?php endforeach; ?>
			</datalist>
			<p class="flosc-model-fetch-row">
				<button type="button" class="button flosc-fetch-models" data-provider="xai" data-target="flow_ai_xai_model">
					<?php echo esc_html__( 'Fetch models this key can use', 'flosc' ); ?>
				</button>
				<button type="submit" name="flosc_save" value="1" form="flosc-settings-form" class="button button-primary">
					<?php echo esc_html__( 'Save AI Settings', 'flosc' ); ?>
				</button>
				<span class="description flosc-model-fetch-status" data-for="flow_ai_xai_model"></span>
			</p>
			<div class="flosc-model-picker" data-for="flow_ai_xai_model" hidden></div>
			<p class="description">
				The model ID sent to <code>api.x.ai</code>. Fetch lists what this key can use;
				any current ID can also be typed in.
			</p>
		</td>
	</tr>
</table>
</div>

<!-- Gemini -->
<div class="flosc-ai-provider-section" data-provider="gemini">
<table class="form-table">
	<tr>
		<th scope="row">
			<label for="flow_gemini_api_key"><strong>Gemini API Key</strong></label>
		</th>
		<td>
			<input type="password" id="flow_gemini_api_key" name="flow_gemini_api_key" value="<?php echo esc_attr( function_exists( 'flosc_admin_secret_input_value' ) ? flosc_admin_secret_input_value( $flosc_flow_settings['gemini_api_key'] ?? '' ) : ( current_user_can( 'manage_options' ) ? (string) ( $flosc_flow_settings['gemini_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="AIza...">
			<p class="flosc-model-fetch-row">
				<button type="button" class="button button-primary flosc-save-key" data-provider="gemini" data-target="flow_gemini_api_key">
					<?php echo esc_html__( 'Save Gemini Key', 'flosc' ); ?>
				</button>
				<span class="description flosc-save-key-status" data-for="flow_gemini_api_key"></span>
			</p>
			<?php flosc_ai_key_state_line( 'gemini', $flosc_flow_settings ); ?>
			<p class="description">
				<a href="https://aistudio.google.com/apikey" target="_blank" class="button button-secondary flosc-ai-key-link">
					Get Your Gemini API Key Here
				</a><br>
				<span class="flosc-ai-key-desc">Google AI Studio keys. Install-wide keys also live under All Flows.</span>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_gemini_model">Gemini Model</label></th>
		<td>
			<?php
			// Stable text endpoints only. The image, TTS, embedding and video
			// ids Google lists alongside these cannot hold a conversation.
			$flosc_gemini_model_options = array(
				'gemini-3.8-flash'      => 'Gemini 3.8 Flash',
				'gemini-3.6-flash'      => 'Gemini 3.6 Flash',
				'gemini-3.5-flash'      => 'Gemini 3.5 Flash',
				'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite',
			);
			?>
			<input type="text" name="flow_ai_gemini_model" id="flow_ai_gemini_model"
				class="regular-text flosc-ai-model-select"
				list="flosc-gemini-model-list"
				value="<?php echo esc_attr( $flosc_ai_gemini_model ); ?>"
				placeholder="gemini-3.5-flash-lite">
			<datalist id="flosc-gemini-model-list">
				<?php foreach ( $flosc_gemini_model_options as $flosc_g_id => $flosc_g_label ) : ?>
					<option value="<?php echo esc_attr( $flosc_g_id ); ?>"><?php echo esc_html( $flosc_g_label ); ?></option>
				<?php endforeach; ?>
			</datalist>
			<p class="flosc-model-fetch-row">
				<button type="button" class="button flosc-fetch-models" data-provider="gemini" data-target="flow_ai_gemini_model">
					<?php echo esc_html__( 'Fetch models this key can use', 'flosc' ); ?>
				</button>
				<button type="submit" name="flosc_save" value="1" form="flosc-settings-form" class="button button-primary">
					<?php echo esc_html__( 'Save AI Settings', 'flosc' ); ?>
				</button>
				<span class="description flosc-model-fetch-status" data-for="flow_ai_gemini_model"></span>
			</p>
			<div class="flosc-model-picker" data-for="flow_ai_gemini_model" hidden></div>
			<p class="description">
				<?php echo esc_html__( 'The model ID sent through AI Provider for Google. Fetch lists what this key can use; any current ID can also be typed in. Google changes model availability and capacity over time, so its model guide can help match a model to this project’s speed, capability, and cost needs.', 'flosc' ); ?>
				<a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Google Gemini model guide', 'flosc' ); ?></a>
			</p>
		</td>
	</tr>
</table>
</div>

<!-- IVR-only notice -->
<div class="flosc-ai-provider-section flosc-banner flosc-banner--info" data-provider="ivr">
	<strong>IVR-Only Mode:</strong> No API key needed. Your IVR messages handle all responses. Switch to an AI provider above to enable conversational AI.
</div>

<!-- Model Tuning -->
<h3 class="flosc-ai-section-heading">
	🎛️ Step 2b: Model Tuning
</h3>
<p class="description">
	Fine-tune AI behavior for this flow. Only settings the selected provider accepts are shown.
</p>

<table class="form-table">
	<tr id="flosc-temperature-absent" hidden>
		<th scope="row">Temperature</th>
		<td>
			<p class="description" id="flosc-temperature-absent-note"></p>
		</td>
	</tr>
	<tr id="flosc-temperature-row">
		<th scope="row"><label for="flow_ai_temperature">Temperature</label></th>
		<td>
			<input type="number" id="flow_ai_temperature" name="flow_ai_temperature" value="<?php echo esc_attr( $flosc_ai_temperature ); ?>" min="0" max="2" step="0.1" class="flosc-ai-temp-input">
			<p class="description">
				Controls randomness. <strong>0.0</strong> = fully deterministic, <strong>0.3</strong> = recommended (precision/coaching), <strong>0.7</strong> = creative/balanced, <strong>1.5+</strong> = highly random. Lower values reduce hallucination.
				<span class="flosc-overridden" id="flosc-overridden-temperature" hidden></span>

			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_model_params">The request FLOSC sends</label></th>
		<td>
			<?php
			$flosc_params_key = 'ai_' . sanitize_key( $flosc_ai_provider ) . '_params';
			$flosc_params_raw = (string) ( $flosc_flow_settings[ $flosc_params_key ] ?? '' );
			?>
			<?php
			// Examples for the provider actually selected, taken from the same
			// profile table the request path reads.
			$flosc_params_profile = function_exists( 'flosc_provider_api_profile' )
				? flosc_provider_api_profile( $flosc_ai_provider )
				: null;
			$flosc_params_example = is_array( $flosc_params_profile )
				? (string) ( $flosc_params_profile['example_params'] ?? '' )
				: '';
			?>
			<?php
			// Built from the fields above plus anything the operator added, the
			// way the personality preview is built from its parts. Locked until
			// they ask to edit it, so it reads as a statement of what will be
			// sent rather than another box to fill in.
			$flosc_params_preview = function_exists( 'flosc_build_model_parameter_preview' )
				? flosc_build_model_parameter_preview( $flosc_ai_provider, $flosc_flow_settings )
				: $flosc_params_raw;
			?>
			<textarea id="flow_ai_model_params" name="<?php echo esc_attr( 'flow_' . $flosc_params_key ); ?>"
				rows="6" class="large-text code flosc-params-locked" spellcheck="false" readonly
				data-extra="<?php echo esc_attr( $flosc_params_raw ); ?>"
				placeholder="<?php echo esc_attr( $flosc_params_example ); ?>"><?php echo esc_textarea( $flosc_params_preview ); ?></textarea>
			<p class="flosc-model-fetch-row">
				<button type="button" class="button" id="flosc-params-edit">
					<?php echo esc_html__( 'Edit the request', 'flosc' ); ?>
				</button>
				<button type="button" class="button button-primary" id="flosc-save-tuning">
					<?php echo esc_html__( 'Save Model Tuning', 'flosc' ); ?>
				</button>
				<span class="flosc-tuning-status" id="flosc-tuning-status" aria-live="polite"></span>
			</p>
			<p class="flosc-model-fetch-row">
				<span class="description" id="flosc-params-lock-note">
					<?php echo esc_html__( 'This is what will be sent. Built from the fields above — click Edit the request to add anything they cannot express.', 'flosc' ); ?>
				</span>
			</p>
			<div class="flosc-param-help" id="flosc-param-help" hidden></div>
			<p class="description flosc-params-status" id="flosc-params-status"></p>

			<?php
			// The catalogue, below the request it writes into. Rendered by the
			// browser from every provider's rows at once so that switching
			// provider or model changes what is listed without a page load —
			// the parameters a flow can use are a property of the model it is
			// pointed at, and the operator is pointing it at one right here.
			?>
			<div class="flosc-param-menu" id="flosc-param-menu">
				<p class="flosc-param-menu__head">
					<strong><?php echo esc_html__( 'Parameters you can add', 'flosc' ); ?></strong>
					<span class="flosc-param-menu__where" id="flosc-param-menu-where"></span>
				</p>
				<p class="flosc-param-menu__measured" id="flosc-param-menu-measured" hidden></p>
				<div class="flosc-param-menu__chips" id="flosc-param-menu-chips"></div>
				<div class="flosc-param-menu__note" id="flosc-param-menu-note" hidden></div>

				<p class="flosc-param-menu__head">
					<strong><?php echo esc_html__( 'Sample setups', 'flosc' ); ?></strong>
					<span class="flosc-param-menu__where"><?php echo esc_html__( 'whole combinations that do one named job', 'flosc' ); ?></span>
				</p>
				<div class="flosc-param-menu__recipes" id="flosc-param-menu-recipes"></div>

				<div class="flosc-param-menu__ask">
					<p class="flosc-param-menu__askline">
						<?php echo esc_html__( 'Somewhere below is a parameter your provider shipped after this plugin did. Type it into the request above and FLOSC sends it as written — and to find out what it does before you do:', 'flosc' ); ?>
					</p>
					<p class="flosc-model-fetch-row">
						<input type="text" id="flosc-param-ask-name" class="regular-text code"
							placeholder="<?php echo esc_attr__( 'parameter name', 'flosc' ); ?>"
							autocomplete="off" spellcheck="false">
						<button type="button" class="button" id="flosc-param-ask">
							<?php echo esc_html__( 'Ask the model what it does', 'flosc' ); ?>
						</button>
						<a href="#" class="flosc-param-menu__docs" id="flosc-param-docs" target="_blank" rel="noopener noreferrer" hidden></a>
					</p>
					<div class="flosc-param-menu__answer" id="flosc-param-answer" hidden></div>
				</div>
			</div>
			<p class="description">
				This is the request FLOSC sends. The fields above are a second view of it, not a rival to
				it: change Max Tokens and the <code>max_tokens</code> line changes with it, and every other
				line stays exactly as you wrote it. Edit that line instead and the field follows. One
				<code>name: value</code> per line, or a JSON object pasted from the provider's own
				documentation.
				<br>FLOSC keeps no list of valid parameters — providers add them faster than any list stays
				true — so a name FLOSC has never heard of is sent as written and the provider decides. Its
				answer comes back word for word in Step 3.
				<br>Numbers, <code>true</code>, <code>false</code> and JSON objects keep their type. Only
				<code>messages</code>, <code>contents</code> and <code>stream</code> are refused: FLOSC
				assembles the conversation itself, and cannot read a reply that streams.
				<br><strong>Parameters differ by model, not just by provider.</strong> Measured on one Anthropic
				key: Sonnet 4.5 accepts <code>temperature</code>, <code>top_p</code> and <code>top_k</code>;
				Sonnet 5 refuses all three and accepts <code>thinking</code> instead. Use
				<em>Describe this model</em> above, then test.
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_max_tokens">Max Tokens</label></th>
		<td>
			<input type="number" id="flow_ai_max_tokens" name="flow_ai_max_tokens" value="<?php echo esc_attr( $flosc_ai_max_tokens ); ?>" min="50" max="4096" step="50" class="flosc-ai-tokens-input">
			<p class="description">
				Maximum response length. <strong>500</strong> = concise chat (default), <strong>1000</strong> = detailed explanations, <strong>2000+</strong> = long-form. Higher values cost more per response.
				<span class="flosc-overridden" id="flosc-overridden-max_tokens" hidden></span>
			</p>
		</td>
	</tr>
</table>

<!-- Provider Chaining (APIs only — not personalities) -->
<h3 class="flosc-ai-section-heading">
	<?php echo esc_html__( 'Provider chaining (optional)', 'flosc' ); ?>
</h3>
<p class="description">
	Send each user message through multiple AI providers sequentially. Provider 1 responds first, then Provider 2 sees that response as context and refines it. Useful for cross-checking or combining strengths of different models.
</p>

<table class="form-table">
	<tr>
		<th scope="row">
			<label for="flow_ai_enable_chaining">
				<input type="checkbox" name="flow_ai_enable_chaining" id="flow_ai_enable_chaining" value="1" <?php checked( $flosc_enable_chaining, true ); ?>>
				Enable Provider Chaining
			</label>
		</th>
		<td>
			<p class="description">
				<?php echo esc_html__( 'When enabled, responses pass through providers in order. Each later provider receives the previous reply as assistant history (same user message and system prompt). Each hop needs a key on this flow or in floscAvailableProviders.', 'flosc' ); ?>
			</p>
		</td>
	</tr>
</table>

<div id="flosc-chain-config" class="flosc-ai-chain-config<?php echo esc_attr( $flosc_enable_chaining ? '' : ' flosc-hidden' ); ?>">
	<table class="form-table">
		<tr>
			<th scope="row"><label for="flow_ai_chain_provider_1">Provider 1 (Drafts)</label></th>
			<td>
				<select name="flow_ai_chain_provider_1" id="flow_ai_chain_provider_1" class="flosc-ai-model-select">
					<option value="none">— Select —</option>
					<option value="openai" <?php selected( $flosc_chain_provider_1, 'openai' ); ?>>OpenAI</option>
					<option value="anthropic" <?php selected( $flosc_chain_provider_1, 'anthropic' ); ?>>Anthropic Claude</option>
					<option value="xai" <?php selected( $flosc_chain_provider_1, 'xai' ); ?>>xAI Grok</option>
					<option value="gemini" <?php selected( $flosc_chain_provider_1, 'gemini' ); ?>>Google Gemini</option>
				</select>
				<span class="description">Generates the initial response.</span>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="flow_ai_chain_provider_2">Provider 2 (Refines)</label></th>
			<td>
				<select name="flow_ai_chain_provider_2" id="flow_ai_chain_provider_2" class="flosc-ai-model-select">
					<option value="none">— Select —</option>
					<option value="openai" <?php selected( $flosc_chain_provider_2, 'openai' ); ?>>OpenAI</option>
					<option value="anthropic" <?php selected( $flosc_chain_provider_2, 'anthropic' ); ?>>Anthropic Claude</option>
					<option value="xai" <?php selected( $flosc_chain_provider_2, 'xai' ); ?>>xAI Grok</option>
					<option value="gemini" <?php selected( $flosc_chain_provider_2, 'gemini' ); ?>>Google Gemini</option>
				</select>
				<span class="description">Reviews and refines Provider 1's response.</span>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="flow_ai_chain_provider_3">Provider 3 (Optional)</label></th>
			<td>
				<select name="flow_ai_chain_provider_3" id="flow_ai_chain_provider_3" class="flosc-ai-model-select">
					<option value="none">— None —</option>
					<option value="openai" <?php selected( $flosc_chain_provider_3, 'openai' ); ?>>OpenAI</option>
					<option value="anthropic" <?php selected( $flosc_chain_provider_3, 'anthropic' ); ?>>Anthropic Claude</option>
					<option value="xai" <?php selected( $flosc_chain_provider_3, 'xai' ); ?>>xAI Grok</option>
					<option value="gemini" <?php selected( $flosc_chain_provider_3, 'gemini' ); ?>>Google Gemini</option>
				</select>
				<span class="description">Optional third pass. Leave as "None" for 2-provider chain.</span>
			</td>
		</tr>
	</table>

	<?php
	// Save where the work happens. The page's main Save button is far below,
	// and a key typed here but not saved is invisible to the connection test.
	// Bound to the settings form by id rather than nested inside it, because
	// the AI tab closes that form early for its own sibling forms.
	?>
	<p class="submit flosc-ai-save-row">
		<button type="submit" name="flosc_save" value="1" form="flosc-settings-form" class="button button-primary">
			<?php echo esc_html__( 'Save AI Settings', 'flosc' ); ?>
		</button>
		<span class="description"><?php echo esc_html__( 'Save before testing — the test reads saved settings, not what is on screen.', 'flosc' ); ?></span>
	</p>
</div>

<!-- Connection Test -->
<h3 class="flosc-ai-section-heading">
	🧪 Step 3: Test Your Connection
</h3>

<div class="flosc-ai-test-card">
	<p class="description flosc-ai-test-desc">
		Verify that your selected AI provider is configured correctly and responding. This sends a test message to the AI.
	</p>
	<button type="button" id="test-ai-connection" class="button button-primary button-large flosc-ai-test-btn">
		🚀 Test AI Connection Now
	</button>
	<div id="test-results" class="flosc-ai-test-results flosc-hidden">
		<div id="test-status"></div>
		<div id="test-details" class="flosc-ai-test-details"></div>
		<div class="flosc-model-picker" data-for="" id="test-model-picker" hidden></div>
	</div>
	<div id="test-loading" class="flosc-ai-test-loading flosc-hidden">
		<span class="spinner is-active"></span>
		<span>Testing connection to AI provider...</span>
	</div>
</div>
</div>
</details>

<details class="flosc-ai-acc">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Response, Context, and Phase Settings', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'How this flow talks across Freeline, Login, Offer, Sale, Content.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">

<!-- AI Response Mode -->
<h3 class="flosc-ai-section-heading flosc-ai-phase-section">
	⚙️ Advanced: AI Response Mode
</h3>
<p class="description">
	Control how the AI uses your IVR (scripted) messages when generating conversational responses.
</p>

<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_ai_response_mode">Response Mode</label></th>
		<td>
			<select name="flow_ai_response_mode" id="flow_ai_response_mode">
				<option value="strict" <?php selected( $flosc_ai_response_mode, 'strict' ); ?>>Strict IVR (AI only rephrases configured messages)</option>
				<option value="enhanced" <?php selected( $flosc_ai_response_mode, 'enhanced' ); ?>>Enhanced (AI can expand on messages with context)</option>
			</select>
			<p class="description">
				<strong>Strict:</strong> AI generates natural variations of IVR messages ONLY. Conditions still determine WHEN to respond.<br>
				<strong>Enhanced:</strong> AI can add context, ask follow-up questions, and provide personalized guidance beyond IVR messages.
			</p>
		</td>
	</tr>
</table>

<!-- ============================================ -->
<!-- IVR Context & Content Access -->
<!-- ============================================ -->
<h3 class="flosc-ai-section-heading flosc-ai-phase-section">
	📡 Advanced: Context Settings
</h3>

<table class="form-table">
	<tr>
		<th scope="row">
			<label for="flow_ai_enable_ivr_context">
				<input type="checkbox" name="flow_ai_enable_ivr_context" id="flow_ai_enable_ivr_context" value="1" <?php checked( $flosc_enable_ivr_context, true ); ?>>
				Enable IVR Context
			</label>
		</th>
		<td>
			<p class="description">
				When enabled, AI receives the full IVR structure including conditions, phases, and message types.
				The AI learns WHEN to use specific messages based on your configured conditions.
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="flow_ai_enable_content_access">
				<input type="checkbox" name="flow_ai_enable_content_access" id="flow_ai_enable_content_access" value="1" <?php checked( $flosc_enable_content_access, true ); ?>>
				Enable Content Access
			</label>
		</th>
		<td>
			<p class="description">
				When enabled, AI can reference lessons, quizzes, offers, and knowledge base entries for personalized recommendations.
			</p>
			<div class="flosc-ai-content-info">
				<strong>How Content Access Works:</strong><br>
				The AI automatically receives context about your lessons, quizzes, offers, and any content you upload to the AI Knowledge Base.
				Upload expert knowledge via the <strong>AI Knowledge</strong> tab to give AI access to information beyond its training data.
			</div>
		</td>
	</tr>
</table>

<!-- ============================================ -->
<!-- Phase-Specific AI Instructions -->
<!-- ============================================ -->
<h3 class="flosc-ai-section-heading flosc-ai-phase-section">
	📍 Advanced: Phase-Specific AI Instructions
</h3>
<p class="description">
	Customize how your AI behaves during each FLOSC phase. These instructions are automatically added to your base prompt based on where the user is in their journey. Leave blank to let the AI use its own judgment for that phase.
</p>

<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_ai_prompt_freeline">Freeline Phase</label></th>
		<td>
			<textarea name="flow_ai_prompt_freeline" id="flow_ai_prompt_freeline" rows="3" class="large-text" placeholder="e.g. Encourage user to take the quiz. Be curious about their goals."><?php echo esc_textarea( $flosc_flow_settings['ai_prompt_freeline'] ?? '' ); ?></textarea>
			<textarea name="flow_phase_outcomes_freeline" id="flow_phase_outcomes_freeline" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Inquiry clarification&#10;Contact exchange"><?php echo esc_textarea( $flosc_flow_settings['phase_outcomes_freeline'] ?? '' ); ?></textarea>
			<p class="description">Optional outcomes for Freeline. One outcome per line.</p>
			<p class="description">Visitors who haven't taken the quiz yet.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_prompt_login">Login Phase</label></th>
		<td>
			<textarea name="flow_ai_prompt_login" id="flow_ai_prompt_login" rows="3" class="large-text" placeholder="e.g. Deliver free lesson based on quiz results. Build trust before presenting offer."><?php echo esc_textarea( $flosc_flow_settings['ai_prompt_login'] ?? '' ); ?></textarea>
			<textarea name="flow_phase_outcomes_login" id="flow_phase_outcomes_login" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Quiz result clarity&#10;Free lesson delivery"><?php echo esc_textarea( $flosc_flow_settings['phase_outcomes_login'] ?? '' ); ?></textarea>
			<p class="description">Optional outcomes for Login. One outcome per line.</p>
			<p class="description">Post-quiz visitors and logged-in users.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_prompt_offer">Offer Phase</label></th>
		<td>
			<textarea name="flow_ai_prompt_offer" id="flow_ai_prompt_offer" rows="3" class="large-text" placeholder="e.g. Present personalized offer. Address objections. Show value specific to their quiz results."><?php echo esc_textarea( $flosc_flow_settings['ai_prompt_offer'] ?? '' ); ?></textarea>
			<textarea name="flow_phase_outcomes_offer" id="flow_phase_outcomes_offer" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Offer readiness&#10;Objection handling"><?php echo esc_textarea( $flosc_flow_settings['phase_outcomes_offer'] ?? '' ); ?></textarea>
			<p class="description">Optional outcomes for Offer. One outcome per line.</p>
			<p class="description">Sales pitch mode.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_prompt_sale">Sale Phase</label></th>
		<td>
			<textarea name="flow_ai_prompt_sale" id="flow_ai_prompt_sale" rows="3" class="large-text" placeholder="e.g. Onboard user to content. Explain navigation. Build excitement for their purchase."><?php echo esc_textarea( $flosc_flow_settings['ai_prompt_sale'] ?? '' ); ?></textarea>
			<textarea name="flow_phase_outcomes_sale" id="flow_phase_outcomes_sale" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Member onboarding&#10;First content step"><?php echo esc_textarea( $flosc_flow_settings['phase_outcomes_sale'] ?? '' ); ?></textarea>
			<p class="description">Optional outcomes for Sale. One outcome per line.</p>
			<p class="description">Post-purchase onboarding.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_ai_prompt_content">Content Phase</label></th>
		<td>
			<textarea name="flow_ai_prompt_content" id="flow_ai_prompt_content" rows="3" class="large-text" placeholder="e.g. Support learning journey. Answer questions. Encourage progress and celebrate wins."><?php echo esc_textarea( $flosc_flow_settings['ai_prompt_content'] ?? '' ); ?></textarea>
			<textarea name="flow_phase_outcomes_content" id="flow_phase_outcomes_content" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Learning momentum&#10;Progress reinforcement"><?php echo esc_textarea( $flosc_flow_settings['phase_outcomes_content'] ?? '' ); ?></textarea>
			<p class="description">Optional outcomes for Content. One outcome per line.</p>
			<p class="description">Ongoing support for paying customers.</p>
		</td>
	</tr>
</table>
</div>
</details>

<?php
$flosc_ai_profile_tables = array(
	'samplingExclusive' => array(),
	'providerRejects'   => array(),
	'paramsByProvider'  => array(),
	'paramRecipes'      => array(),
	'paramModelNotes'   => array(),
	'providerParamDocs' => array(),
	'providerDocs'      => array(),
);
foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_ai_provider ) {
	$flosc_ai_profile = function_exists( 'flosc_provider_api_profile' ) ? flosc_provider_api_profile( $flosc_ai_provider ) : null;

	$flosc_ai_profile_tables['samplingExclusive'][ $flosc_ai_provider ] = is_array( $flosc_ai_profile )
		? array_values( (array) ( $flosc_ai_profile['sampling_exclusive'] ?? array() ) )
		: array();
	$flosc_ai_profile_tables['providerRejects'][ $flosc_ai_provider ]   = array(
		'params' => is_array( $flosc_ai_profile ) ? array_values( (array) $flosc_ai_profile['rejects_tuning'] ) : array(),
		'note'   => is_array( $flosc_ai_profile ) ? (string) $flosc_ai_profile['tuning_note'] : '',
	);
	$flosc_ai_profile_tables['paramsByProvider'][ $flosc_ai_provider ]  = function_exists( 'flosc_model_parameters_for_provider' )
		? flosc_model_parameters_for_provider( $flosc_ai_provider )
		: array();
	$flosc_ai_profile_tables['paramRecipes'][ $flosc_ai_provider ]      = function_exists( 'flosc_model_parameter_recipes' )
		? flosc_model_parameter_recipes( $flosc_ai_provider )
		: array();
	$flosc_ai_profile_tables['paramModelNotes'][ $flosc_ai_provider ]   = is_array( $flosc_ai_profile )
		? (array) ( $flosc_ai_profile['model_parameter_notes'] ?? array() )
		: array();
	$flosc_ai_profile_tables['providerParamDocs'][ $flosc_ai_provider ] = is_array( $flosc_ai_profile )
		? (string) ( $flosc_ai_profile['param_doc_url'] ?? '' )
		: '';
	$flosc_ai_profile_tables['providerDocs'][ $flosc_ai_provider ]      = function_exists( 'flosc_provider_docs_url' )
		? flosc_provider_docs_url( $flosc_ai_provider )
		: '';
}
wp_enqueue_script(
	'flosc-admin-ai',
	FLOSC_PLUGIN_URL . 'assets/js/admin/flosc-admin-ai.js',
	array( 'jquery', 'flosc-admin' ),
	FLOSC_VERSION,
	true
);
wp_localize_script(
	'flosc-admin-ai',
	'floscAdminAi',
	array_merge(
		$flosc_ai_profile_tables,
		array(
			'paramRef'      => function_exists( 'flosc_model_parameter_reference' ) ? flosc_model_parameter_reference() : array(),
			'attachNonce'   => wp_create_nonce( 'flosc_attach_personality' ),
			'testAiNonce'   => wp_create_nonce( 'flosc_test_ai' ),
			'accuracyNonce' => wp_create_nonce( 'flosc_accuracy_test' ),
			'ivr'           => (string) ( $GLOBALS['flosc_current_ivr'] ?? '' ),
			'i18n'          => array(
				'attachFallback'    => __( 'Could not attach automatically — scroll down and click Save Settings.', 'flosc' ),
				'attaching'         => __( 'Attaching…', 'flosc' ),
				'attached'          => __( 'Attached', 'flosc' ),
				'attachmentCleared' => __( 'Attachment cleared', 'flosc' ),
				'saved'             => __( 'saved', 'flosc' ),
				'addQuestion'       => __( 'Add at least one test question.', 'flosc' ),
			),
		)
	)
);
unset( $flosc_ai_provider, $flosc_ai_profile, $flosc_ai_profile_tables );
?>

<details class="flosc-ai-acc">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Speech-to-text', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Speech input settings.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">

<!-- Speech-to-Text Configuration -->
<hr class="flosc-ai-stt-divider">

<h2>🎤 Speech-to-Text Configuration</h2>
<p class="description flosc-ai-intro">
	Configure transcription for recorded voice input and audio-based quiz responses.
</p>

<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_stt_provider">Speech-to-Text Provider</label></th>
		<td>
			<select name="flow_stt_provider" id="flow_stt_provider" class="flosc-ai-provider-select">
				<option value="assemblyai" <?php selected( $flosc_flow_settings['stt_provider'] ?? '', 'assemblyai' ); ?>>AssemblyAI</option>
				<option value="openai" <?php selected( $flosc_flow_settings['stt_provider'] ?? '', 'openai' ); ?>>OpenAI Whisper</option>
				<option value="custom" <?php selected( $flosc_flow_settings['stt_provider'] ?? '', 'custom' ); ?>>Custom endpoint</option>
			</select>
			<p class="description">Choose the service that transcribes recorded audio for this flow.</p>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="flow_assemblyai_api_key"><strong>AssemblyAI API Key</strong></label>
		</th>
		<td>
			<input type="password" id="flow_assemblyai_api_key" name="flow_assemblyai_api_key" value="<?php echo esc_attr( function_exists( 'flosc_admin_secret_input_value' ) ? flosc_admin_secret_input_value( $flosc_flow_settings['assemblyai_api_key'] ?? '' ) : ( current_user_can( 'manage_options' ) ? (string) ( $flosc_flow_settings['assemblyai_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-stt-input" placeholder="Your AssemblyAI key">
			<p class="description">
				<a href="https://www.assemblyai.com/dashboard/signup" target="_blank" class="button button-secondary flosc-ai-key-link">
					📥 Get Your AssemblyAI API Key Here
				</a><br>
				<span class="flosc-ai-key-desc">AssemblyAI transcription credentials for this flow.</span>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="flow_custom_stt_endpoint"><strong>Custom STT Endpoint URL</strong></label>
		</th>
		<td>
			<input type="url" id="flow_custom_stt_endpoint" name="flow_custom_stt_endpoint" value="<?php echo esc_attr( $flosc_flow_settings['custom_stt_endpoint'] ?? '' ); ?>" class="regular-text flosc-ai-stt-input" placeholder="https://your-stt-endpoint.com/transcribe">
			<p class="description">URL for a self-hosted or third-party speech-to-text service.</p>
		</td>
	</tr>
</table>

<div class="flosc-banner flosc-banner--info flosc-ai-footer-reminder">
	<strong>💡 Remember:</strong> After adding your API keys, click <strong>"Save Settings"</strong> at the bottom of this page, then use the <strong>"Test AI Connection"</strong> button above to verify everything works!
</div>
</div>
</details>

<!-- ============================================ -->
<!-- SECTION: SITE CONTENT INDEX -->
<!-- ============================================ -->
<?php
$flosc_sci_ivr   = (string) ( $GLOBALS['flosc_current_ivr'] ?? '' );
$flosc_sci_stem  = sanitize_key( pathinfo( $flosc_sci_ivr, PATHINFO_FILENAME ) );
$flosc_sci_index = class_exists( 'FLOSC_Site_Content_Index' ) ? FLOSC_Site_Content_Index::instance() : null;
$flosc_sci_doc   = $flosc_sci_index ? $flosc_sci_index->load( $flosc_sci_stem ) : array(
	'built_at' => '',
	'posts'    => array(),
);
$flosc_sci_posts = is_array( $flosc_sci_doc['posts'] ?? null ) ? $flosc_sci_doc['posts'] : array();
$flosc_sci_slugs = $flosc_sci_index ? $flosc_sci_index->resolve_category_slugs( $flosc_sci_stem ) : array();
$flosc_sci_count = count( $flosc_sci_posts );
$flosc_sci_excl  = 0;
foreach ( $flosc_sci_posts as $flosc_sci_row ) {
	if ( ! empty( $flosc_sci_row['excluded'] ) ) {
		++$flosc_sci_excl;
	}
}
$flosc_sci_in_flow = $flosc_sci_index ? (int) $flosc_sci_index->count_in_flow_category( $flosc_sci_stem ) : 0;

// Notices (transient preferred).
$flosc_sci_notice = get_transient( 'flosc_site_index_notice_' . get_current_user_id() );
if ( is_array( $flosc_sci_notice ) ) {
	delete_transient( 'flosc_site_index_notice_' . get_current_user_id() );
}

/*
 * The URL is the fallback, and it is the ONLY carrier for nine of the ten
 * outcomes. FLOSC_Site_Content_Index::redirect_ai() puts site_index_action --
 * and site_index_error when the action failed -- in the query string on every
 * path; set_transient() runs on exactly one of them, the successful full
 * rebuild. So the read below is what makes "Excluded.", "Keywords saved.",
 * "Could not reindex that post." and the rest appear at all.
 *
 * v78 changed $flosc_get's fallback from wp_unslash( $_GET ) to array() and
 * nothing writes $GLOBALS['flosc_get'], so these two reads silently became
 * permanently empty and those nine notices stopped rendering. Sourcing them
 * through the request boundary restores the notice without restoring the bulk
 * superglobal read.
 */
$flosc_sci_action = is_array( $flosc_sci_notice )
	? sanitize_key( (string) ( $flosc_sci_notice['action'] ?? '' ) )
	: flosc_nav_param( 'site_index_action' );
$flosc_sci_err    = is_array( $flosc_sci_notice )
	? sanitize_text_field( (string) ( $flosc_sci_notice['message'] ?? '' ) )
	: sanitize_text_field( rawurldecode( flosc_nav_param( 'site_index_error', array(), '', 'sanitize_text_field' ) ) );
$flosc_sci_msg    = '';
if ( 'rebuilt' === $flosc_sci_action ) {
	$flosc_sci_msg = '' !== $flosc_sci_err ? $flosc_sci_err : __( 'Site content index rebuilt.', 'flosc' );
} elseif ( 'excluded' === $flosc_sci_action ) {
	$flosc_sci_msg = __( 'Post excluded from the index.', 'flosc' );
} elseif ( 'included' === $flosc_sci_action ) {
	$flosc_sci_msg = __( 'Post included in the index again.', 'flosc' );
} elseif ( 'keywords' === $flosc_sci_action ) {
	$flosc_sci_msg = __( 'Keywords saved.', 'flosc' );
} elseif ( 'reindexed' === $flosc_sci_action ) {
	$flosc_sci_msg = __( 'Post reindexed.', 'flosc' );
} elseif ( 'error' === $flosc_sci_action ) {
	$flosc_sci_msg = '' !== $flosc_sci_err ? $flosc_sci_err : __( 'Site index action failed.', 'flosc' );
}
?>
<details class="flosc-ai-acc" id="flosc-site-index-section"<?php echo '' !== $flosc_sci_msg ? ' open' : ''; ?>>
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Site Content Index', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Index selected site content for access-aware retrieval in chat.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">
<hr class="flosc-section-divider">
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Site content index', 'flosc' ); ?></h3>
<p class="description">
	<?php echo esc_html__( 'Indexes published posts across the site into a reference library. Chat pulls only matching posts when useful — never the whole library on every message. Set this flow’s content category on the Content tab for freeline, guest, and member product scope.', 'flosc' ); ?>
</p>

<?php if ( '' !== $flosc_sci_msg ) : ?>
	<div class="notice <?php echo esc_attr( 'error' === $flosc_sci_action ? 'notice-error' : 'notice-success' ); ?> inline flosc-margin-bottom-15"><p><?php echo esc_html( $flosc_sci_msg ); ?></p></div>
<?php endif; ?>

<?php
/*
 * Which post types the index reads.
 *
 * It read the literal string 'post', which is why the chatbot could not see
 * the shop: WooCommerce products, pages and bbPress forum topics are all
 * WP_Post and would have gone through this indexer untouched — the query never
 * asked for them. Posts stay in whatever is chosen, so turning this on adds to
 * a working library rather than replacing it.
 *
 * This sits inside the settings form on purpose: the page-wide Save writes it,
 * and the form closes a few lines below for the rebuild control.
 */
$flosc_sci_types_selected  = FLOSC_Site_Content_Index::indexed_post_types( sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) ) );
$flosc_sci_types_available = get_post_types( array( 'public' => true ), 'objects' );
unset( $flosc_sci_types_available['attachment'] );
?>
<table class="form-table flosc-admin-form-table">
	<tr>
		<th scope="row"><?php echo esc_html__( 'Content to index', 'flosc' ); ?></th>
		<td>
			<?php
			foreach ( $flosc_sci_types_available as $flosc_sci_type ) :
				$flosc_sci_is_post = ( 'post' === $flosc_sci_type->name );
				?>
				<label class="flosc-sci-type">
					<input type="checkbox"
						name="flow_site_index_post_types[]"
						value="<?php echo esc_attr( $flosc_sci_type->name ); ?>"
						<?php checked( in_array( $flosc_sci_type->name, $flosc_sci_types_selected, true ) ); ?>
						<?php disabled( $flosc_sci_is_post ); ?>>
					<?php echo esc_html( $flosc_sci_type->labels->name ); ?>
					<code><?php echo esc_html( $flosc_sci_type->name ); ?></code>
					<?php if ( $flosc_sci_is_post ) : ?>
						<em><?php echo esc_html__( 'always', 'flosc' ); ?></em>
						<input type="hidden" name="flow_site_index_post_types[]" value="post">
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
			<p class="description">
				<?php echo esc_html__( 'Anything ticked here is read into the library on the next rebuild — products, pages, forum topics, any public type this site registers. Posts are always included. Save this page, then rebuild below.', 'flosc' ); ?>
			</p>
		</td>
	</tr>
	<?php
	/*
	 * BuddyBoss groups.
	 *
	 * The one part of a BuddyBoss site that genuinely is not in wp_posts — the
	 * group directory lives in the BuddyPress tables. Forum topics and replies
	 * are bbPress post types and are ticked above like anything else.
	 *
	 * Which groups a chatbot may mention is stored on this flow, not on the
	 * group: the same group can be open on one flow and withheld on another.
	 */
	$flosc_bb_stem   = sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) );
	$flosc_bb_policy = FLOSC_Site_Content_Index::buddyboss_policy( $flosc_bb_stem );
	$flosc_bb_ready  = FLOSC_Site_Content_Index::groups_available();
	$flosc_bb_groups = array();
	if ( $flosc_bb_ready ) {
		$flosc_bb_found  = groups_get_groups(
			array(
				'per_page'    => 200,
				'show_hidden' => false,
				'type'        => 'alphabetical',
			)
		);
		$flosc_bb_groups = isset( $flosc_bb_found['groups'] ) ? (array) $flosc_bb_found['groups'] : array();
	}
	$flosc_bb_default = preg_split( '/\s+/', (string) $flosc_bb_policy['vgm_default'] );
	if ( ! $flosc_bb_default ) {
		$flosc_bb_default = array();
	}
	?>
	<tr>
		<th scope="row"><?php echo esc_html__( 'BuddyBoss groups', 'flosc' ); ?></th>
		<td>
			<?php if ( ! $flosc_bb_ready ) : ?>
				<p class="description">
					<?php echo esc_html__( 'No BuddyBoss or BuddyPress group directory was found on this site, so there is nothing to index here. Everything else on this panel works as usual.', 'flosc' ); ?>
				</p>
			<?php else : ?>
				<label class="flosc-sci-type">
					<input type="checkbox" name="flow_buddyboss_index[enabled]" value="1" <?php checked( ! empty( $flosc_bb_policy['enabled'] ) ); ?>>
					<?php echo esc_html__( 'Index the group directory for this flow', 'flosc' ); ?>
				</label>
				<p class="description">
					<?php echo esc_html__( 'Name, description and link for each group, so the chatbot can send someone to the right one. Activity, forums and media are not indexed.', 'flosc' ); ?>
				</p>

				<p class="flosc-sci-subhead"><?php echo esc_html__( 'Which groups', 'flosc' ); ?></p>
				<?php foreach ( $flosc_bb_groups as $flosc_bb_group ) : ?>
					<label class="flosc-sci-type">
						<input type="checkbox" name="flow_buddyboss_index[group_ids][]" value="<?php echo esc_attr( (int) $flosc_bb_group->id ); ?>"
							<?php checked( in_array( (int) $flosc_bb_group->id, $flosc_bb_policy['group_ids'], true ) ); ?>>
						<?php echo esc_html( (string) $flosc_bb_group->name ); ?>
						<code><?php echo esc_html( (string) $flosc_bb_group->status ); ?></code>
					</label>
				<?php endforeach; ?>
				<p class="description">
					<?php echo esc_html__( 'Tick none to index every group. A private group is never linked to somebody who is not a member of it, whatever is set here — FLOSC can tighten BuddyBoss privacy, never loosen it.', 'flosc' ); ?>
				</p>

				<p class="flosc-sci-subhead"><?php echo esc_html__( 'Who may be told about a group', 'flosc' ); ?></p>
				<?php
				foreach ( array(
					'visitor' => __( 'Visitor', 'flosc' ),
					'guest'   => __( 'Guest', 'flosc' ),
					'member'  => __( 'Member', 'flosc' ),
				) as $flosc_bb_tier => $flosc_bb_label ) :
					?>
					<label class="flosc-sci-tier">
						<input type="checkbox" name="flow_buddyboss_index[vgm_default][]" value="<?php echo esc_attr( $flosc_bb_tier ); ?>"
												<?php checked( in_array( $flosc_bb_tier, $flosc_bb_default, true ) ); ?>>
												<?php echo esc_html( $flosc_bb_label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description">
					<?php echo esc_html__( 'Save this page, then rebuild below.', 'flosc' ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
</table>
<?php
// The rebuild control below is its own form posting to admin-post.php, so the
// settings form has to end before it. That end tag has to be emitted HERE,
// outside the table — a </form> written inside a <td>, for a form opened
// outside the table, does not close anything: the HTML parser clears its form
// pointer and leaves the element open. Everything after it then stayed inside
// #flosc-settings-form, the inline form nested inside it was discarded (forms
// cannot nest), and that inline form's own _wpnonce landed in the settings
// form — overwriting the settings nonce, because PHP keeps the last value for
// a repeated field name. wp_verify_nonce() then failed on every page-wide
// Save, the handler never ran, and the button did nothing at all.
if ( empty( $GLOBALS['flosc_settings_form_closed_early'] ) ) {
	echo '</form>';
	$GLOBALS['flosc_settings_form_closed_early'] = true;
}
?>
<div class="flosc-card-soft flosc-margin-bottom-20">
	<table class="form-table flosc-form-table-reset" role="presentation">
		<tr>
			<th scope="row"><?php echo esc_html__( 'Index scope', 'flosc' ); ?></th>
			<td>
				<strong><?php echo esc_html__( 'Whole site', 'flosc' ); ?></strong>
				<span class="description"> — <?php echo esc_html__( 'published posts (capped for safety; exclusions supported).', 'flosc' ); ?></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'This flow’s category', 'flosc' ); ?></th>
			<td>
				<?php if ( ! empty( $flosc_sci_slugs ) ) : ?>
					<code><?php echo esc_html( implode( ', ', $flosc_sci_slugs ) ); ?></code>
					<?php if ( $flosc_sci_count > 0 ) : ?>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: posts in this flow category that appear in the site index */
									__( '%d indexed post(s) are in this flow’s category (freeline / sell slice).', 'flosc' ),
									(int) $flosc_sci_in_flow
								)
							);
							?>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<span class="description"><?php echo esc_html__( 'Not set. Optional for indexing; set on the Content tab so this flow freelines and sells the right topic.', 'flosc' ); ?></span>
				<?php endif; ?>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=flosc-settings&ivr=' . rawurlencode( $flosc_sci_ivr ) . '&tab=content' ) ); ?>"><?php echo esc_html__( 'Content tab', 'flosc' ); ?></a>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Status', 'flosc' ); ?></th>
			<td>
				<?php if ( ! empty( $flosc_sci_doc['built_at'] ) ) : ?>
					<span class="flosc-text-green">✓ <?php echo esc_html__( 'Built', 'flosc' ); ?></span>
					<span class="description flosc-margin-left-8"><?php echo esc_html( (string) $flosc_sci_doc['built_at'] ); ?></span>
				<?php else : ?>
					<span class="description"><?php echo esc_html__( 'Not built yet — use Build below.', 'flosc' ); ?></span>
				<?php endif; ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: indexed count 2: excluded count */
							__( '%1$d posts in site index (%2$d excluded).', 'flosc' ),
							(int) $flosc_sci_count,
							(int) $flosc_sci_excl
						)
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Rebuild', 'flosc' ); ?></th>
			<td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-inline-form">
					<?php wp_nonce_field( 'flosc_site_index_rebuild', 'flosc_sci_nonce' ); ?>
					<input type="hidden" name="action" value="flosc_site_index_rebuild">
					<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_sci_ivr ); ?>">
					<button type="submit" class="button button-primary">
						<?php echo esc_html__( 'Build / rebuild site index', 'flosc' ); ?>
					</button>
				</form>
				<p class="description"><?php echo esc_html__( 'Stores full post text for lookup. Chat still retrieves only matches (access-aware). Manual keywords and exclusions are kept across rebuilds.', 'flosc' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<?php if ( $flosc_sci_count > 0 ) : ?>
	<div class="card flosc-margin-bottom-20 flosc-site-index-card">
		<h4 class="flosc-card-title-reset"><?php echo esc_html__( 'Indexed posts (site library)', 'flosc' ); ?></h4>
		<p class="description flosc-site-index-table-hint">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of indexed posts */
					__( 'Showing %d posts.', 'flosc' ),
					(int) $flosc_sci_count
				)
			);
			?>
		</p>
		<div class="flosc-site-index-table-wrap">
		<table class="widefat striped flosc-site-index-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'ID', 'flosc' ); ?></th>
					<th><?php echo esc_html__( 'Title', 'flosc' ); ?></th>
					<th><?php echo esc_html__( 'Categories', 'flosc' ); ?></th>
					<th><?php echo esc_html__( 'Access', 'flosc' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'flosc' ); ?></th>
					<th><?php echo esc_html__( 'Keywords', 'flosc' ); ?></th>
					<th><?php echo esc_html__( 'Snippet', 'flosc' ); ?></th>
					<th><?php echo esc_html__( 'Actions', 'flosc' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			// Sort by title for stable admin scan.
			$flosc_sci_sorted = $flosc_sci_posts;
			uasort(
				$flosc_sci_sorted,
				static function ( $a, $b ) {
					return strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
				}
			);
			foreach ( $flosc_sci_sorted as $flosc_sci_row ) :
				$flosc_sci_pid   = (int) ( $flosc_sci_row['post_id'] ?? 0 );
				$flosc_sci_title = (string) ( $flosc_sci_row['title'] ?? '' );
				$flosc_sci_acc   = (string) ( $flosc_sci_row['access'] ?? 'member' );
				$flosc_sci_is_ex = ! empty( $flosc_sci_row['excluded'] );
				$flosc_sci_kw    = (string) ( $flosc_sci_row['keywords'] ?? '' );
				$flosc_sci_snip  = (string) ( $flosc_sci_row['snippet'] ?? '' );
				$flosc_sci_mod   = (string) ( $flosc_sci_row['modified'] ?? '' );
				$flosc_sci_cats  = isset( $flosc_sci_row['categories'] ) && is_array( $flosc_sci_row['categories'] ) ? $flosc_sci_row['categories'] : array();
				$flosc_sci_edit  = $flosc_sci_pid ? get_edit_post_link( $flosc_sci_pid, 'raw' ) : '';
				$flosc_sci_stale = false;
				if ( $flosc_sci_pid && '' !== $flosc_sci_mod ) {
					$flosc_sci_wp = get_post( $flosc_sci_pid );
					if ( $flosc_sci_wp && $flosc_sci_wp->post_modified_gmt > $flosc_sci_mod ) {
						$flosc_sci_stale = true;
					}
				}
				$flosc_sci_in_this_flow = false;
				if ( ! empty( $flosc_sci_slugs ) && ! empty( $flosc_sci_cats ) ) {
					foreach ( $flosc_sci_slugs as $flosc_sci_slug_one ) {
						if ( in_array( $flosc_sci_slug_one, $flosc_sci_cats, true ) ) {
							$flosc_sci_in_this_flow = true;
							break;
						}
					}
				}
				$flosc_sci_status_label = $flosc_sci_is_ex ? __( 'Excluded', 'flosc' ) : ( $flosc_sci_stale ? __( 'Stale', 'flosc' ) : __( 'Indexed', 'flosc' ) );
				if ( $flosc_sci_in_this_flow && ! $flosc_sci_is_ex ) {
					$flosc_sci_status_label .= ' · ' . __( 'this flow', 'flosc' );
				}
				?>
				<tr class="<?php echo $flosc_sci_is_ex ? 'flosc-site-index-row-excluded' : ''; ?>">
					<td class="flosc-site-index-id"><code><?php echo esc_html( (string) $flosc_sci_pid ); ?></code></td>
					<td class="flosc-site-index-title">
						<?php if ( $flosc_sci_edit ) : ?>
							<a href="<?php echo esc_url( $flosc_sci_edit ); ?>"><?php echo esc_html( $flosc_sci_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $flosc_sci_title ); ?>
						<?php endif; ?>
					</td>
					<td class="flosc-site-index-cats">
						<?php if ( empty( $flosc_sci_cats ) ) : ?>
							<span class="description">—</span>
						<?php else : ?>
							<?php foreach ( $flosc_sci_cats as $flosc_sci_cat_slug ) : ?>
								<code class="flosc-site-index-cat"><?php echo esc_html( (string) $flosc_sci_cat_slug ); ?></code>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $flosc_sci_acc ); ?></td>
					<td><?php echo esc_html( $flosc_sci_status_label ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-site-index-kw-form">
							<?php wp_nonce_field( 'flosc_site_index_keywords' ); ?>
							<input type="hidden" name="action" value="flosc_site_index_keywords">
							<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_sci_ivr ); ?>">
							<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $flosc_sci_pid ); ?>">
							<input type="text" name="keywords_manual" class="regular-text" value="<?php echo esc_attr( (string) ( $flosc_sci_row['keywords_manual'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $flosc_sci_kw ); ?>" title="<?php echo esc_attr( $flosc_sci_kw ); ?>">
							<button type="submit" class="button button-small"><?php echo esc_html__( 'Save', 'flosc' ); ?></button>
						</form>
						<p class="description flosc-site-index-kw-preview"><?php echo esc_html( wp_html_excerpt( $flosc_sci_kw, 120, '…' ) ); ?></p>
					</td>
					<td class="flosc-site-index-snippet-cell"><span class="flosc-site-index-snippet"><?php echo esc_html( $flosc_sci_snip ); ?></span></td>
					<td class="flosc-site-index-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-inline-form">
							<?php wp_nonce_field( 'flosc_site_index_reindex_one' ); ?>
							<input type="hidden" name="action" value="flosc_site_index_reindex_one">
							<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_sci_ivr ); ?>">
							<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $flosc_sci_pid ); ?>">
							<button type="submit" class="button button-small"><?php echo esc_html__( 'Reindex', 'flosc' ); ?></button>
						</form>
						<?php if ( $flosc_sci_is_ex ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-inline-form">
								<?php wp_nonce_field( 'flosc_site_index_include' ); ?>
								<input type="hidden" name="action" value="flosc_site_index_include">
								<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_sci_ivr ); ?>">
								<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $flosc_sci_pid ); ?>">
								<button type="submit" class="button button-small"><?php echo esc_html__( 'Include', 'flosc' ); ?></button>
							</form>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-inline-form">
								<?php wp_nonce_field( 'flosc_site_index_exclude' ); ?>
								<input type="hidden" name="action" value="flosc_site_index_exclude">
								<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_sci_ivr ); ?>">
								<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $flosc_sci_pid ); ?>">
								<button type="submit" class="button button-small"><?php echo esc_html__( 'Exclude', 'flosc' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div><!-- .flosc-site-index-table-wrap -->
	</div>
<?php endif; ?>

</div>
</details>

<details class="flosc-ai-acc" id="flosc-kb-section">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Knowledge Base', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Review knowledge base files.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">
<hr class="flosc-section-divider">
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Knowledge Base', 'flosc' ); ?></h3>
<?php
$flosc_kb_tab_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'knowledge-base',
		'view' => 'single',
	),
	admin_url( 'admin.php' )
);
$flosc_kb_stem    = sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) );
$flosc_kb_ids     = function_exists( 'flosc_flow_knowledge_base_ids' ) ? flosc_flow_knowledge_base_ids( $flosc_kb_stem ) : array();
?>
<p class="description">
	<?php echo esc_html__( 'Markdown knowledge bases this flow may inject into chat, gated by Visitor / Guest / Member. Upload and attach them on the Knowledge Base tab.', 'flosc' ); ?>
	<a href="<?php echo esc_url( $flosc_kb_tab_url ); ?>"><?php echo esc_html__( 'Open Knowledge Base tab', 'flosc' ); ?></a>
</p>
<?php if ( array() === $flosc_kb_ids ) : ?>
<p class="description"><?php echo esc_html__( 'None attached to this flow.', 'flosc' ); ?></p>
<?php else : ?>
<ul>
	<?php
	foreach ( $flosc_kb_ids as $flosc_kb_aid ) :
		$flosc_kb_row = function_exists( 'flosc_knowledge_base_get' ) ? flosc_knowledge_base_get( $flosc_kb_aid ) : null;
		$flosc_kb_lab = is_array( $flosc_kb_row ) ? (string) ( $flosc_kb_row['label'] ?? $flosc_kb_aid ) : $flosc_kb_aid;
		?>
	<li><code><?php echo esc_html( $flosc_kb_aid ); ?></code> — <?php echo esc_html( $flosc_kb_lab ); ?></li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php
// Fix 6: Regenerate Lesson Catalog button.
$flosc_catalog_file   = function_exists( 'flosc_resolve_lesson_catalog_path' )
	? flosc_resolve_lesson_catalog_path()
	: ( function_exists( 'flosc_config_file' ) ? flosc_config_file( 'lesson_catalog.md' ) : '' );
$flosc_catalog_exists = $flosc_catalog_file && file_exists( $flosc_catalog_file );
$flosc_catalog_gen    = get_option( 'flosc_lesson_catalog_generated', '' );
$flosc_catalog_count  = get_option( 'flosc_lesson_catalog_count', 0 );
$flosc_regen_url      = wp_nonce_url( admin_url( 'admin-post.php?action=flosc_regenerate_lesson_catalog' ), 'flosc_regen_catalog' );
?>
<div class="flosc-card-soft flosc-flex-row flosc-flex-align-center flosc-gap-16 flosc-margin-bottom-20">
	<div class="flosc-flex-1">
		<strong>Lesson Catalog</strong>
		<?php if ( $flosc_catalog_exists ) : ?>
			<span class="flosc-text-green flosc-margin-left-8">✓ Generated</span>
			<?php
			if ( $flosc_catalog_gen ) :
				?>
				<span class="flosc-text-gray-888 flosc-text-12 flosc-margin-left-8"><?php echo esc_html( $flosc_catalog_gen ); ?> (<?php echo (int) $flosc_catalog_count; ?> lessons)</span><?php endif; ?>
		<?php else : ?>
			<span class="flosc-text-danger flosc-margin-left-8">Not yet generated</span>
		<?php endif; ?>
		<p class="description flosc-margin-top-4">Auto-regenerates when a lesson post in the configured lessons category is saved. Manual regeneration queries published posts in that category.</p>
	</div>
	<a href="<?php echo esc_url( $flosc_regen_url ); ?>" class="button button-secondary">Regenerate Lesson Catalog</a>
</div>
</div>
</details>

<!-- ============================================ -->
<!-- SECTION: PROVIDER ACCURACY TEST -->
<!-- ============================================ -->
<?php
$flosc_acc_flow_name = (string) ( $flosc_flow_settings['identity']['name'] ?? $flosc_flow_settings['name'] ?? '' );
if ( '' === $flosc_acc_flow_name ) {
	$flosc_acc_flow_name = (string) ( $GLOBALS['flosc_current_ivr'] ?? 'this floscFlow' );
	$flosc_acc_flow_name = pathinfo( $flosc_acc_flow_name, PATHINFO_FILENAME );
	$flosc_acc_flow_name = '' !== $flosc_acc_flow_name ? $flosc_acc_flow_name : 'this floscFlow';
}
$flosc_acc_title   = trim( (string) ( $flosc_flow_settings['identity']['title'] ?? $flosc_flow_settings['title'] ?? '' ) );
$flosc_acc_tagline = trim( (string) ( $flosc_flow_settings['identity']['tagline'] ?? $flosc_flow_settings['tagline'] ?? '' ) );
$flosc_acc_scope   = trim( (string) ( $flosc_flow_settings['ai_topic_scope'] ?? '' ) );
if ( '' === $flosc_acc_scope && function_exists( 'flosc_personality_library_resolve_field' ) ) {
	$flosc_acc_scope = trim( (string) flosc_personality_library_resolve_field( 'ai_topic_scope', '' ) );
}
if ( '' === $flosc_acc_scope ) {
	$flosc_acc_scope = __( '(topic scope not set)', 'flosc' );
}
$flosc_acc_site = (string) get_bloginfo( 'name' );
if ( '' === $flosc_acc_site ) {
	$flosc_acc_site = (string) wp_parse_url( home_url(), PHP_URL_HOST );
}
if ( '' === $flosc_acc_site ) {
	$flosc_acc_site = 'this site';
}

// Content-agnostic templates (placeholders). Expanded for this flow for defaults / Reset.
$flosc_acc_templates        = array(
	'Hello — what is the name of this floscFlow ({flow_name}), and who are you in this chat?',
	'What does the Title of this floscFlow ({title}) mean?',
	'What does the Tagline of this floscFlow ({tagline}) mean or convey?',
	'In your own words, what is this floscFlow for, and who is it meant to help?',
	'What topics or tasks are you authorized to handle? (Topic scope note: {topic_scope}.)',
	'How does this floscFlow relate to {site_name}?',
	'What should a first-time visitor do next in this chat?',
	'Stay in character: state your role in one or two sentences.',
	'If someone asks for details you do not have about this floscFlow, what do you do instead of inventing them?',
	'What is this floscFlow about: title {title}, tagline {tagline}, and how you help? Based on the title and tagline, what is going on here?',
);
$flosc_acc_title_for_prompt = '' !== $flosc_acc_title
	? $flosc_acc_title
	: '(none)';
$flosc_acc_var_map          = array(
	'{flow_name}'   => $flosc_acc_flow_name,
	'{title}'       => $flosc_acc_title_for_prompt,
	'{tagline}'     => $flosc_acc_tagline,
	'{topic_scope}' => $flosc_acc_scope,
	'{site_name}'   => $flosc_acc_site,
);
$flosc_acc_expand           = static function ( $text, $map ) {
	return str_replace( array_keys( $map ), array_values( $map ), (string) $text );
};
$flosc_acc_defaults_filled  = array();
foreach ( $flosc_acc_templates as $flosc_acc_t ) {
	$flosc_acc_defaults_filled[] = $flosc_acc_expand( $flosc_acc_t, $flosc_acc_var_map );
}

// Saved suite: prefer templates with {placeholders}. Expanded-only legacy saves map back to templates when they match.
$flosc_acc_saved_raw   = (string) ( $flosc_flow_settings['ai_accuracy_test_questions'] ?? '' );
$flosc_acc_saved_lines = array();
if ( '' !== trim( $flosc_acc_saved_raw ) ) {
	if ( false === strpos( $flosc_acc_saved_raw, "\n" ) && false === strpos( $flosc_acc_saved_raw, "\r" ) ) {
		// Mangled one-line save — use content-agnostic templates.
		$flosc_acc_saved_lines = $flosc_acc_templates;
	} else {
		// preg_split() returns false on a pattern error rather than an empty
		// list, and array_map() cannot take false.
		$flosc_acc_split       = preg_split( '/\r\n|\r|\n/', $flosc_acc_saved_raw );
		$flosc_acc_saved_lines = array_values(
			array_filter(
				array_map( 'trim', $flosc_acc_split ? $flosc_acc_split : array() ),
				static function ( $l ) {
					return '' !== $l;
				}
			)
		);
	}
}
// Edit fields hold templates (with {vars}). If a saved line equals the expanded default, show the template instead.
$flosc_acc_edit_lines = array();
if ( array() === $flosc_acc_saved_lines ) {
	$flosc_acc_edit_lines = $flosc_acc_templates;
} else {
	foreach ( $flosc_acc_saved_lines as $flosc_acc_si => $flosc_acc_line ) {
		$flosc_acc_tpl_i    = $flosc_acc_templates[ $flosc_acc_si ] ?? '';
		$flosc_acc_filled_i = $flosc_acc_defaults_filled[ $flosc_acc_si ] ?? '';
		if ( '' !== $flosc_acc_tpl_i && ( $flosc_acc_line === $flosc_acc_filled_i || $flosc_acc_line === $flosc_acc_expand( $flosc_acc_tpl_i, $flosc_acc_var_map ) ) ) {
			$flosc_acc_edit_lines[] = $flosc_acc_tpl_i;
		} else {
			$flosc_acc_edit_lines[] = $flosc_acc_line;
		}
	}
}
$flosc_acc_row_count = max( count( $flosc_acc_templates ), count( $flosc_acc_edit_lines ) );
// The list grows inside the loop, so the count is re-taken after each pass
// rather than hoisted -- hoisting it would never terminate.
for ( $flosc_idx = count( $flosc_acc_edit_lines ); $flosc_idx < $flosc_acc_row_count; $flosc_idx = count( $flosc_acc_edit_lines ) ) {
	$flosc_acc_edit_lines[] = $flosc_acc_templates[ $flosc_idx ] ?? '';
}
$flosc_acc_line_count             = max( 1, count( array_filter( $flosc_acc_edit_lines ) ) );
$flosc_accuracy_personality_label = '' !== $flosc_personality_label
	? $flosc_personality_label
	: __( 'the selected personality', 'flosc' );
?>
</div>
</details>

<details class="flosc-ai-acc" id="flosc-accuracy-test">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Provider Accuracy Test', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint">
	<?php
	/* translators: %s: selected personality label. */
	echo esc_html( sprintf( __( 'Use %s to run template questions against this flow’s API.', 'flosc' ), $flosc_accuracy_personality_label ) );
	?>
	</span>
</summary>
<div class="flosc-ai-acc__body">
<hr class="flosc-section-divider">
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Provider accuracy test', 'flosc' ); ?></h3>
<p class="description">
	<?php echo esc_html__( 'Edit each template using the variables below. The user input that will be sent to the AI is previewed under each row (variables expanded). Save Settings keeps your templates.', 'flosc' ); ?>
</p>

<div class="flosc-acc-var-chips" aria-label="<?php echo esc_attr__( 'Variables you can use in templates', 'flosc' ); ?>">
	<p class="description flosc-acc-var-intro">
		<strong><?php echo esc_html__( 'Variables you can use:', 'flosc' ); ?></strong>
		<?php echo esc_html__( 'Type these in the template box. Values for this flow:', 'flosc' ); ?>
	</p>
	<span class="flosc-acc-var-chip"><code>{flow_name}</code> = <?php echo esc_html( $flosc_acc_flow_name ); ?></span>
	<span class="flosc-acc-var-chip"><code>{title}</code> = <?php echo esc_html( $flosc_acc_title ); ?></span>
	<span class="flosc-acc-var-chip"><code>{tagline}</code> = <?php echo esc_html( $flosc_acc_tagline ); ?></span>
	<span class="flosc-acc-var-chip"><code>{topic_scope}</code> = <?php echo esc_html( $flosc_acc_scope ); ?></span>
	<span class="flosc-acc-var-chip"><code>{site_name}</code> = <?php echo esc_html( $flosc_acc_site ); ?></span>
</div>

<div id="flosc-accuracy-rows" class="flosc-acc-rows"
	data-flosc-acc-map="<?php echo esc_attr( wp_json_encode( $flosc_acc_var_map ) ); ?>">
	<?php
	for ( $flosc_acc_i = 0; $flosc_acc_i < $flosc_acc_row_count; $flosc_acc_i++ ) :
		$flosc_acc_tpl = $flosc_acc_templates[ $flosc_acc_i ] ?? '';
		$flosc_acc_val = $flosc_acc_edit_lines[ $flosc_acc_i ] ?? $flosc_acc_tpl;
		// Prefer template with placeholders in the edit box.
		if ( '' === $flosc_acc_val && '' !== $flosc_acc_tpl ) {
			$flosc_acc_val = $flosc_acc_tpl;
		}
		$flosc_acc_preview = $flosc_acc_expand( $flosc_acc_val, $flosc_acc_var_map );
		?>
	<div class="flosc-acc-row" data-acc-index="<?php echo esc_attr( (string) $flosc_acc_i ); ?>">
		<div class="flosc-acc-row__num" aria-hidden="true"><?php echo esc_html( (string) ( $flosc_acc_i + 1 ) ); ?></div>
		<div class="flosc-acc-row__body">
			<label for="flosc_acc_q_<?php echo esc_attr( (string) $flosc_acc_i ); ?>">
				<strong><?php echo esc_html__( 'Template (edit with variables)', 'flosc' ); ?></strong>
			</label>
			<textarea
				id="flosc_acc_q_<?php echo esc_attr( (string) $flosc_acc_i ); ?>"
				class="large-text code flosc-acc-row__input"
				rows="2"
				data-acc-template="<?php echo esc_attr( $flosc_acc_tpl ); ?>"
			><?php echo esc_textarea( $flosc_acc_val ); ?></textarea>
			<p class="description flosc-acc-row__default">
				<strong><?php echo esc_html__( 'Default template:', 'flosc' ); ?></strong>
				<code class="flosc-acc-row__template"><?php echo esc_html( '' !== $flosc_acc_tpl ? $flosc_acc_tpl : '—' ); ?></code>
			</p>
			<p class="flosc-acc-row__preview-wrap">
				<strong><?php echo esc_html__( 'User input (sent to AI):', 'flosc' ); ?></strong>
				<span class="flosc-acc-row__preview"><?php echo esc_html( $flosc_acc_preview ); ?></span>
			</p>
			<p class="flosc-acc-row__actions">
				<button type="button" class="button button-small flosc-acc-reset-row"><?php echo esc_html__( 'Reset row to default template', 'flosc' ); ?></button>
			</p>
		</div>
	</div>
	<?php endfor; ?>
</div>

<?php // Hidden field: save templates (with {vars}) newline-joined. ?>
<textarea
	id="flow_ai_accuracy_test_questions"
	name="flow_ai_accuracy_test_questions"
	form="flosc-settings-form"
	class="flosc-sr-only screen-reader-text"
	rows="1"
	aria-hidden="true"
	tabindex="-1"
><?php echo esc_textarea( implode( "\n", array_filter( array_map( 'trim', $flosc_acc_edit_lines ) ) ) ); ?></textarea>

<div id="flosc-accuracy-test-ui" class="flosc-margin-top-12">
	<div class="flosc-ai-accuracy-controls">
		<button type="button" class="button button-secondary" id="flosc-accuracy-reset-defaults"><?php echo esc_html__( 'Reset all to default templates', 'flosc' ); ?></button>
		<?php
		$flosc_accuracy_personality_label = '' !== $flosc_personality_label
			? $flosc_personality_label
			: __( 'selected personality', 'flosc' );
		?>
		<button type="button" id="flosc-run-accuracy-test" class="button button-primary">
		<?php
		/* translators: %s: selected personality label. */
		echo esc_html( sprintf( __( '▶ Run multi-turn provider test using %s', 'flosc' ), $flosc_accuracy_personality_label ) );
		?>
		</button>
		<span class="description"><?php echo esc_html__( 'Save Settings keeps templates. Run sends the expanded user input from each row.', 'flosc' ); ?></span>
		<span id="flosc-test-progress" class="flosc-ai-progress flosc-hidden">
			<?php echo esc_html__( 'Running… message', 'flosc' ); ?>
			<span id="flosc-test-msg-num">0</span>/<span id="flosc-test-msg-total"><?php echo esc_html( (string) $flosc_acc_line_count ); ?></span>
		</span>
	</div>

	<div id="flosc-accuracy-results" class="flosc-hidden">
		<table class="widefat flosc-margin-bottom-12">
			<thead>
				<tr>
					<th class="flosc-width-5">#</th>
					<th class="flosc-width-35"><?php echo esc_html__( 'User input', 'flosc' ); ?></th>
					<th class="flosc-width-45"><?php echo esc_html__( 'AI response', 'flosc' ); ?></th>
					<th class="flosc-width-8"><?php echo esc_html__( 'Tokens In', 'flosc' ); ?></th>
					<th class="flosc-width-7"><?php echo esc_html__( 'OK?', 'flosc' ); ?></th>
				</tr>
			</thead>
			<tbody id="flosc-accuracy-tbody"></tbody>
		</table>
		<div id="flosc-accuracy-summary" class="flosc-ai-summary"></div>
	</div>
</div>

<?php
// The accuracy-test runner is part of flosc-admin-ai.js, enqueued and
// localized above this tab's tables; a second enqueue is a no-op.
wp_enqueue_script(
	'flosc-admin-ai',
	FLOSC_PLUGIN_URL . 'assets/js/admin/flosc-admin-ai.js',
	array( 'jquery', 'flosc-admin' ),
	FLOSC_VERSION,
	true
);
?>
</div>
</details>

</div><!-- .flosc-ai-config -->
