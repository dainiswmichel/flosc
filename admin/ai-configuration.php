<?php
/**
 * FLOSC AI Configuration Tab
 * 
 * Configures AI provider connections, model tuning, personality,
 * and phase-specific behavior for each FLOSC flow.
 * 
 * v1.9.0: Moved all inline styles to assets/css/flosc-admin.css
 *         Removed hardcoded default prompts (floscAdmin configures all)
 *         Added provider-aware show/hide for API key sections
 *         Cleaned up unbuilt feature UI
 */

if (!defined('ABSPATH')) exit;

flosc_tab_header('🤖', 'AI');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr   = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_get           = isset( $GLOBALS['flosc_get'] ) && is_array( $GLOBALS['flosc_get'] ) ? $GLOBALS['flosc_get'] : wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$flosc_ai_view       = isset( $flosc_get['view'] ) ? sanitize_key( (string) $flosc_get['view'] ) : 'single';
if ( ! in_array( $flosc_ai_view, array( 'single', 'all' ), true ) ) {
	$flosc_ai_view = 'single';
}
$flosc_personality_id = sanitize_key( (string) ( $flosc_flow_settings['personality_library_id'] ?? '' ) );
if ( $flosc_personality_id === '' && function_exists( 'flosc_personality_library_id_for_flow' ) ) {
	$flosc_personality_id = flosc_personality_library_id_for_flow(
		sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) )
	);
}
$flosc_ai_all_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'tab'  => 'ai',
		'view' => 'all',
		'ivr'  => $flosc_current_ivr,
	),
	admin_url( 'admin.php' )
);
$flosc_ai_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-ai';

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

$flosc_ai_provider = $flosc_flow_settings['ai_provider'] ?? 'ivr';
$flosc_ai_openai_model = $flosc_flow_settings['ai_openai_model'] ?? flosc_default_model('openai');
$flosc_ai_anthropic_model = $flosc_flow_settings['ai_anthropic_model'] ?? flosc_default_model('anthropic');
$flosc_ai_xai_model = $flosc_flow_settings['ai_xai_model'] ?? flosc_default_model('xai');
$flosc_ai_gemini_model = $flosc_flow_settings['ai_gemini_model'] ?? flosc_default_model('gemini');
// Retired xAI slugs no longer resolve on api.x.ai — surface current default in the UI.
$flosc_xai_legacy_models = ['grok-2-latest', 'grok-2', 'grok-2-1212', 'grok-beta', 'grok-vision-beta'];
if (in_array($flosc_ai_xai_model, $flosc_xai_legacy_models, true)) {
    $flosc_ai_xai_model = flosc_default_model('xai');
}
$flosc_ai_temperature = $flosc_flow_settings['ai_temperature'] ?? '0.3';
$flosc_ai_max_tokens = $flosc_flow_settings['ai_max_tokens'] ?? '500';
$flosc_enable_ivr_context = $flosc_flow_settings['ai_enable_ivr_context'] ?? true;
$flosc_enable_content_access = $flosc_flow_settings['ai_enable_content_access'] ?? true;
$flosc_ai_response_mode = $flosc_flow_settings['ai_response_mode'] ?? 'enhanced';
$flosc_enable_chaining = $flosc_flow_settings['ai_enable_chaining'] ?? false;
$flosc_chain_provider_1 = $flosc_flow_settings['ai_chain_provider_1'] ?? '';
$flosc_chain_provider_2 = $flosc_flow_settings['ai_chain_provider_2'] ?? '';
$flosc_chain_provider_3 = $flosc_flow_settings['ai_chain_provider_3'] ?? '';
$flosc_personas         = function_exists( 'flosc_personality_library_get_all' ) ? flosc_personality_library_get_all() : array();
$flosc_personality_label = isset( $flosc_personas[ $flosc_personality_id ]['label'] ) && $flosc_personas[ $flosc_personality_id ]['label'] !== ''
	? (string) $flosc_personas[ $flosc_personality_id ]['label']
	: $flosc_personality_id;
$flosc_avail            = function_exists( 'flosc_available_providers_get_all' ) ? flosc_available_providers_get_all() : array();

// Risk / setup notices — read directly from flow settings (get_current_flow() is null in admin context).
// No calendar-age nags for lesson catalog (stable catalogs can be fine for years).
$flosc_notices = [];
if ((float) $flosc_ai_temperature > 0.5) {
    $flosc_notices[] = '<strong>Temperature ' . esc_html($flosc_ai_temperature) . ' increases fabrication risk.</strong> Recommended: 0.3';
}
?>
<?php if (!empty($flosc_notices)): ?>
<div class="flosc-margin-bottom-20">
    <?php foreach ($flosc_notices as $flosc_notice): ?>
    <div class="notice notice-warning inline flosc-notice-compact">
        <p class="flosc-text-zero-margin"><?php echo wp_kses($flosc_notice, ['strong' => [], 'a' => ['href' => [], 'class' => []]]); ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="flosc-docs-link-wrap">
    <a href="<?php echo esc_url($flosc_ai_docs_url); ?>" class="flosc-docs-link">Docs</a>
</div>

<?php flosc_render_ai_tab_nav( 'single', $flosc_current_ivr ); ?>
<?php
/* Fail-closed visibility: if this flow has no resolvable personality
   profile, the chat refuses unpersonified answers — tell the admin why. */
$flosc_admin_stem    = sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) );
$flosc_admin_profile = ( $flosc_admin_stem !== '' && function_exists( 'flosc_personality_compiled_profile' ) )
	? trim( (string) flosc_personality_compiled_profile( $flosc_admin_stem ) )
	: '';
if ( $flosc_admin_profile === '' ) :
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
$flosc_avail_bits = array();
$flosc_key_catalog = function_exists( 'flosc_install_provider_catalog' ) ? flosc_install_provider_catalog() : array();
foreach ( $flosc_key_catalog as $flosc_slug => $flosc_meta ) {
	$flosc_lab = isset( $flosc_meta['label'] ) ? (string) $flosc_meta['label'] : $flosc_slug;
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
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Attached personality', 'flosc' ); ?> <?php echo esc_html( $flosc_personality_label ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Choose from the dropdown list to attach an AI personality to this flow’s API.', 'flosc' ); ?></span>
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
					<?php echo esc_html( $flosc_p['label'] !== '' ? $flosc_p['label'] : $flosc_pid ); ?>
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
		<th scope="row"><label for="flow_ai_brand_facts"><?php echo esc_html__( 'Product facts', 'flosc' ); ?></label></th>
		<td>
			<textarea name="flow_ai_brand_facts" id="flow_ai_brand_facts" rows="5" class="large-text code" placeholder="<?php echo esc_attr__( 'e.g. FLOSC always stands for Freeline, Login, Offer, Sale, Content.', 'flosc' ); ?>"><?php echo esc_textarea( (string) ( $GLOBALS['flosc_current_settings']['ai_brand_facts'] ?? '' ) ); ?></textarea>
			<p class="description">
				<?php echo esc_html__( 'Optional. Hard guarantees about what this product is — injected verbatim into every AI prompt. Leave empty for none; nothing is assumed on your behalf.', 'flosc' ); ?>
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

<details class="flosc-ai-acc" open>
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Provider, keys, and test', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'This flow’s API. Install-wide keys live under All Flows.', 'flosc' ); ?></span>
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
                <option value="ivr" <?php selected($flosc_ai_provider, 'ivr'); ?>>IVR Only (Scripted Responses)</option>
                <option value="anthropic" <?php selected($flosc_ai_provider, 'anthropic'); ?>>Anthropic Claude</option>
                <option value="openai" <?php selected($flosc_ai_provider, 'openai'); ?>>OpenAI</option>
                <option value="xai" <?php selected($flosc_ai_provider, 'xai'); ?>>xAI Grok</option>
                <option value="gemini" <?php selected($flosc_ai_provider, 'gemini'); ?>>Google Gemini</option>
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
            <input type="password" id="flow_anthropic_api_key" name="flow_anthropic_api_key" value="<?php echo esc_attr( function_exists('flosc_admin_secret_input_value') ? flosc_admin_secret_input_value( $flosc_flow_settings['anthropic_api_key'] ?? '' ) : ( current_user_can('manage_options') ? (string) ( $flosc_flow_settings['anthropic_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-ant-api03-...">
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
            $flosc_anthropic_model_options = [
                'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
                'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
                'claude-sonnet-5'            => 'Claude Sonnet 5',
                'claude-opus-5'              => 'Claude Opus 5',
            ];
            ?>
            <input type="text" name="flow_ai_anthropic_model" id="flow_ai_anthropic_model"
                class="regular-text flosc-ai-model-select"
                list="flosc-anthropic-model-list"
                value="<?php echo esc_attr($flosc_ai_anthropic_model); ?>"
                placeholder="claude-sonnet-4-5-20250929">
            <datalist id="flosc-anthropic-model-list">
                <?php foreach ($flosc_anthropic_model_options as $flosc_a_id => $flosc_a_label) : ?>
                    <option value="<?php echo esc_attr($flosc_a_id); ?>"><?php echo esc_html($flosc_a_label); ?></option>
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
            <input type="password" id="flow_openai_api_key" name="flow_openai_api_key" value="<?php echo esc_attr( function_exists('flosc_admin_secret_input_value') ? flosc_admin_secret_input_value( $flosc_flow_settings['openai_api_key'] ?? '' ) : ( current_user_can('manage_options') ? (string) ( $flosc_flow_settings['openai_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-proj-...">
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
            $flosc_openai_model_options = [
                'gpt-5.5'       => 'GPT-5.5',
                'gpt-5.4'       => 'GPT-5.4',
                'gpt-5.4-mini'  => 'GPT-5.4 mini',
                'gpt-5.4-nano'  => 'GPT-5.4 nano',
            ];
            ?>
            <input type="text" name="flow_ai_openai_model" id="flow_ai_openai_model"
                class="regular-text flosc-ai-model-select"
                list="flosc-openai-model-list"
                value="<?php echo esc_attr($flosc_ai_openai_model); ?>"
                placeholder="gpt-5.4-mini">
            <datalist id="flosc-openai-model-list">
                <?php foreach ($flosc_openai_model_options as $flosc_o_id => $flosc_o_label) : ?>
                    <option value="<?php echo esc_attr($flosc_o_id); ?>"><?php echo esc_html($flosc_o_label); ?></option>
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
            <input type="password" id="flow_xai_api_key" name="flow_xai_api_key" value="<?php echo esc_attr( function_exists('flosc_admin_secret_input_value') ? flosc_admin_secret_input_value( $flosc_flow_settings['xai_api_key'] ?? '' ) : ( current_user_can('manage_options') ? (string) ( $flosc_flow_settings['xai_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="xai-...">
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
            $flosc_xai_model_options = [
                'grok-4.6'        => 'Grok 4.6',
                'grok-4.6-latest' => 'Grok 4.6 (latest)',
            ];
            ?>
            <input type="text" name="flow_ai_xai_model" id="flow_ai_xai_model"
                class="regular-text flosc-ai-model-select"
                list="flosc-xai-model-list"
                value="<?php echo esc_attr($flosc_ai_xai_model); ?>"
                placeholder="grok-4.6">
            <datalist id="flosc-xai-model-list">
                <?php foreach ($flosc_xai_model_options as $flosc_xai_id => $flosc_xai_label) : ?>
                    <option value="<?php echo esc_attr($flosc_xai_id); ?>"><?php echo esc_html($flosc_xai_label); ?></option>
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
            <input type="password" id="flow_gemini_api_key" name="flow_gemini_api_key" value="<?php echo esc_attr( function_exists('flosc_admin_secret_input_value') ? flosc_admin_secret_input_value( $flosc_flow_settings['gemini_api_key'] ?? '' ) : ( current_user_can('manage_options') ? (string) ( $flosc_flow_settings['gemini_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="AIza...">
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
            $flosc_gemini_model_options = [
                'gemini-3.7-flash'      => 'Gemini 3.7 Flash',
                'gemini-3.6-flash'      => 'Gemini 3.6 Flash',
                'gemini-3.5-flash'      => 'Gemini 3.5 Flash',
                'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite',
            ];
            ?>
            <input type="text" name="flow_ai_gemini_model" id="flow_ai_gemini_model"
                class="regular-text flosc-ai-model-select"
                list="flosc-gemini-model-list"
                value="<?php echo esc_attr($flosc_ai_gemini_model); ?>"
                placeholder="gemini-3.7-flash">
            <datalist id="flosc-gemini-model-list">
                <?php foreach ($flosc_gemini_model_options as $flosc_g_id => $flosc_g_label) : ?>
                    <option value="<?php echo esc_attr($flosc_g_id); ?>"><?php echo esc_html($flosc_g_label); ?></option>
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
                The model ID sent through AI Provider for Google. Fetch lists what this key can use;
                any current ID can also be typed in.
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
            <input type="number" id="flow_ai_temperature" name="flow_ai_temperature" value="<?php echo esc_attr($flosc_ai_temperature); ?>" min="0" max="2" step="0.1" class="flosc-ai-temp-input">
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
            <input type="number" id="flow_ai_max_tokens" name="flow_ai_max_tokens" value="<?php echo esc_attr($flosc_ai_max_tokens); ?>" min="50" max="4096" step="50" class="flosc-ai-tokens-input">
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
                <input type="checkbox" name="flow_ai_enable_chaining" id="flow_ai_enable_chaining" value="1" <?php checked($flosc_enable_chaining, true); ?>>
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
                    <option value="openai" <?php selected($flosc_chain_provider_1, 'openai'); ?>>OpenAI</option>
                    <option value="anthropic" <?php selected($flosc_chain_provider_1, 'anthropic'); ?>>Anthropic Claude</option>
                    <option value="xai" <?php selected($flosc_chain_provider_1, 'xai'); ?>>xAI Grok</option>
                    <option value="gemini" <?php selected($flosc_chain_provider_1, 'gemini'); ?>>Google Gemini</option>
                </select>
                <span class="description">Generates the initial response.</span>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_ai_chain_provider_2">Provider 2 (Refines)</label></th>
            <td>
                <select name="flow_ai_chain_provider_2" id="flow_ai_chain_provider_2" class="flosc-ai-model-select">
                    <option value="none">— Select —</option>
                    <option value="openai" <?php selected($flosc_chain_provider_2, 'openai'); ?>>OpenAI</option>
                    <option value="anthropic" <?php selected($flosc_chain_provider_2, 'anthropic'); ?>>Anthropic Claude</option>
                    <option value="xai" <?php selected($flosc_chain_provider_2, 'xai'); ?>>xAI Grok</option>
                    <option value="gemini" <?php selected($flosc_chain_provider_2, 'gemini'); ?>>Google Gemini</option>
                </select>
                <span class="description">Reviews and refines Provider 1's response.</span>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_ai_chain_provider_3">Provider 3 (Optional)</label></th>
            <td>
                <select name="flow_ai_chain_provider_3" id="flow_ai_chain_provider_3" class="flosc-ai-model-select">
                    <option value="none">— None —</option>
                    <option value="openai" <?php selected($flosc_chain_provider_3, 'openai'); ?>>OpenAI</option>
                    <option value="anthropic" <?php selected($flosc_chain_provider_3, 'anthropic'); ?>>Anthropic Claude</option>
                    <option value="xai" <?php selected($flosc_chain_provider_3, 'xai'); ?>>xAI Grok</option>
                    <option value="gemini" <?php selected($flosc_chain_provider_3, 'gemini'); ?>>Google Gemini</option>
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
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Response, context, and phases', 'flosc' ); ?></span>
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
                <option value="strict" <?php selected($flosc_ai_response_mode, 'strict'); ?>>Strict IVR (AI only rephrases configured messages)</option>
                <option value="enhanced" <?php selected($flosc_ai_response_mode, 'enhanced'); ?>>Enhanced (AI can expand on messages with context)</option>
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
                <input type="checkbox" name="flow_ai_enable_ivr_context" id="flow_ai_enable_ivr_context" value="1" <?php checked($flosc_enable_ivr_context, true); ?>>
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
                <input type="checkbox" name="flow_ai_enable_content_access" id="flow_ai_enable_content_access" value="1" <?php checked($flosc_enable_content_access, true); ?>>
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
            <textarea name="flow_ai_prompt_freeline" id="flow_ai_prompt_freeline" rows="3" class="large-text" placeholder="e.g. Encourage user to take the quiz. Be curious about their goals."><?php echo esc_textarea($flosc_flow_settings['ai_prompt_freeline'] ?? ''); ?></textarea>
            <textarea name="flow_phase_outcomes_freeline" id="flow_phase_outcomes_freeline" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Inquiry clarification&#10;Contact exchange"><?php echo esc_textarea($flosc_flow_settings['phase_outcomes_freeline'] ?? ''); ?></textarea>
            <p class="description">Optional outcomes for Freeline. One outcome per line.</p>
            <p class="description">Visitors who haven't taken the quiz yet.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_login">Login Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_login" id="flow_ai_prompt_login" rows="3" class="large-text" placeholder="e.g. Deliver free lesson based on quiz results. Build trust before presenting offer."><?php echo esc_textarea($flosc_flow_settings['ai_prompt_login'] ?? ''); ?></textarea>
            <textarea name="flow_phase_outcomes_login" id="flow_phase_outcomes_login" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Quiz result clarity&#10;Free lesson delivery"><?php echo esc_textarea($flosc_flow_settings['phase_outcomes_login'] ?? ''); ?></textarea>
            <p class="description">Optional outcomes for Login. One outcome per line.</p>
            <p class="description">Post-quiz visitors and logged-in users.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_offer">Offer Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_offer" id="flow_ai_prompt_offer" rows="3" class="large-text" placeholder="e.g. Present personalized offer. Address objections. Show value specific to their quiz results."><?php echo esc_textarea($flosc_flow_settings['ai_prompt_offer'] ?? ''); ?></textarea>
            <textarea name="flow_phase_outcomes_offer" id="flow_phase_outcomes_offer" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Offer readiness&#10;Objection handling"><?php echo esc_textarea($flosc_flow_settings['phase_outcomes_offer'] ?? ''); ?></textarea>
            <p class="description">Optional outcomes for Offer. One outcome per line.</p>
            <p class="description">Sales pitch mode.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_sale">Sale Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_sale" id="flow_ai_prompt_sale" rows="3" class="large-text" placeholder="e.g. Onboard user to content. Explain navigation. Build excitement for their purchase."><?php echo esc_textarea($flosc_flow_settings['ai_prompt_sale'] ?? ''); ?></textarea>
            <textarea name="flow_phase_outcomes_sale" id="flow_phase_outcomes_sale" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Member onboarding&#10;First content step"><?php echo esc_textarea($flosc_flow_settings['phase_outcomes_sale'] ?? ''); ?></textarea>
            <p class="description">Optional outcomes for Sale. One outcome per line.</p>
            <p class="description">Post-purchase onboarding.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_content">Content Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_content" id="flow_ai_prompt_content" rows="3" class="large-text" placeholder="e.g. Support learning journey. Answer questions. Encourage progress and celebrate wins."><?php echo esc_textarea($flosc_flow_settings['ai_prompt_content'] ?? ''); ?></textarea>
            <textarea name="flow_phase_outcomes_content" id="flow_phase_outcomes_content" rows="2" class="large-text" placeholder="Phase outcomes (one per line), e.g.&#10;Learning momentum&#10;Progress reinforcement"><?php echo esc_textarea($flosc_flow_settings['phase_outcomes_content'] ?? ''); ?></textarea>
            <p class="description">Optional outcomes for Content. One outcome per line.</p>
            <p class="description">Ongoing support for paying customers.</p>
        </td>
    </tr>
</table>
</div>
</details>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    // --- Provider section show/hide ---
    // Which provider refuses which setting is data, declared once in
    // includes/ai/flosc-provider-profiles.php. The tab reads it rather than
    // naming a provider here, so filling in a row there is all it takes.
    // Sampling controls a provider refuses on the same request. Anthropic takes
    // temperature alone and top_p alone, and answers 400 to both together —
    // which is how visitor chat came to fail while the panel showed a request
    // that looked fine. The runtime holds one back; this is so the page says so
    // rather than displaying a request it knows will not be sent as written.
    var floscSamplingExclusive = <?php
        $flosc_excl = array();
        foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_excl_provider ) {
            $flosc_excl_profile = function_exists( 'flosc_provider_api_profile' )
                ? flosc_provider_api_profile( $flosc_excl_provider )
                : null;
            $flosc_excl[ $flosc_excl_provider ] = is_array( $flosc_excl_profile )
                ? array_values( (array) ( $flosc_excl_profile['sampling_exclusive'] ?? array() ) )
                : array();
        }
        echo wp_json_encode( $flosc_excl );
    ?>;

    var floscProviderRejects = <?php
        $flosc_rejects = array();
        foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_slug ) {
            $flosc_profile = function_exists( 'flosc_provider_api_profile' ) ? flosc_provider_api_profile( $flosc_slug ) : null;
            $flosc_rejects[ $flosc_slug ] = array(
                'params' => is_array( $flosc_profile ) ? array_values( (array) $flosc_profile['rejects_tuning'] ) : array(),
                'note'   => is_array( $flosc_profile ) ? (string) $flosc_profile['tuning_note'] : '',
            );
        }
        echo wp_json_encode( $flosc_rejects );
    ?>;

    // Model first, provider second — the same order the PHP resolver uses.
    // Sonnet 4.5 takes temperature and Sonnet 5 refuses it, and both are
    // Anthropic, so hiding the control by provider hid a control that works.
    // floscModelNoteFor already does the longest-prefix model match; this only
    // asks it the question.
    function floscModelRejectsParam(provider, model, param) {
        var note = (typeof floscModelNoteFor === 'function') ? floscModelNoteFor(provider, model) : null;

        if (note) {
            if ((note.accepts || []).indexOf(param) !== -1) { return false; }
            if ((note.refuses || []).indexOf(param) !== -1) { return true; }
        }

        // Same reason as above: this runs during setup, before the map exists.
        var wide = (floscProviderRejects && floscProviderRejects[provider]) || { params: [] };

        return (wide.params || []).indexOf(param) !== -1;
    }

    function updateProviderSections() {
        var selected = $('#flow_ai_provider').val();
        var rejects = floscProviderRejects[selected] || { params: [], note: '' };
        var selectedModelField = (typeof floscModelFieldFor === 'object' && floscModelFieldFor)
            ? floscModelFieldFor[selected]
            : '';
        var selectedModel = selectedModelField ? String($('#' + selectedModelField).val() || '').trim() : '';
        var noTemperature = floscModelRejectsParam(selected, selectedModel, 'temperature');

        // A control the provider will refuse is not a control. Hide it rather
        // than leave it on screen with a paragraph explaining it does nothing.
        $('#flosc-temperature-row').prop('hidden', noTemperature);
        $('#flosc-temperature-absent').prop('hidden', !noTemperature);
        $('#flosc-temperature-absent-note').text(rejects.note || '');
        // Showing only the selected provider hides the other key fields, which
        // makes storing a second key look impossible. It never was — the keys
        // are kept separately — so this reveals them on request.
        var showAll = $('#flosc-show-all-providers').is(':checked');

        $('.flosc-ai-provider-section').each(function() {
            var provider = $(this).data('provider');
            var keep = (provider === selected) || (showAll && provider !== 'ivr');

            if (keep) {
                $(this).removeClass('is-hidden');
            } else {
                $(this).addClass('is-hidden');
            }
        });
    }
    $('#flow_ai_provider').on('change', updateProviderSections);
    $('#flosc-show-all-providers').on('change', updateProviderSections);
    updateProviderSections();

    var attachSel = $('#flow_personality_library_id');
    var attachNote = $('#flosc-personality-attach-note');
    var attachSaved = attachSel.val();
    var floscAttach = {
        nonce: '<?php echo esc_js( wp_create_nonce('flosc_attach_personality') ); ?>',
        ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>',
        fallback: '<?php echo esc_js( __( 'Could not attach automatically — scroll down and click Save Settings.', 'flosc' ) ); ?>'
    };
    /*
     * The confirmation has to outlive the reload that this control triggers.
     *
     * It used to set the note to "Attached X. Reloading…" and call
     * location.reload() on the next statement. The browser never painted it, so
     * what a floscAdmin saw was "Attaching…" and then a page reload — an
     * indefinite state with no outcome, on the one control where being sure
     * matters most. The reload itself is needed: the designer below is rendered
     * from the attached row.
     *
     * So the confirmation is stashed before reloading and shown afterwards,
     * beside a dropdown that now reads the same name. Choose DadJokeDan, reload,
     * see DadJokeDan attached and a line saying when it was written.
     */
    var floscAttachStash = 'flosc_attach_confirmation';

    function floscShowStashedAttachConfirmation() {
        if (!attachNote.length) {
            return;
        }
        var raw = null;
        try {
            raw = window.sessionStorage.getItem(floscAttachStash);
            window.sessionStorage.removeItem(floscAttachStash);
        } catch (e) {
            raw = null;
        }
        if (!raw) {
            return;
        }
        try {
            var stash = JSON.parse(raw);
            if (stash && stash.text) {
                attachNote.removeClass('flosc-hidden').addClass('flosc-attach-ok').text(stash.text);
            }
        } catch (e) {
            // A confirmation we cannot read is not worth a broken page.
        }
    }
    floscShowStashedAttachConfirmation();

    attachSel.on('change', function () {
        var nextVal = attachSel.val();
        var nextLabel = attachSel.find('option:selected').text().trim();
        if (!attachNote.length || nextVal === attachSaved) {
            return;
        }
        attachSel.prop('disabled', true);
        attachNote.removeClass('flosc-hidden flosc-attach-ok flosc-attach-bad').text('<?php echo esc_js( __( 'Attaching…', 'flosc' ) ); ?>');
        $.post(ajaxurl, {
            action: 'flosc_attach_personality',
            nonce: floscAttach.nonce,
            ivr: floscAttach.ivr,
            persona: nextVal
        }).done(function (res) {
            if (res && res.success && res.data) {
                attachSaved = res.data.persona;
                var confirmed = res.data.persona
                    ? '<?php echo esc_js( __( 'Attached', 'flosc' ) ); ?> ' + (res.data.label || nextLabel || res.data.persona)
                    : '<?php echo esc_js( __( 'Attachment cleared', 'flosc' ) ); ?>';
                if (res.data.saved_at) {
                    confirmed += ' · <?php echo esc_js( __( 'saved', 'flosc' ) ); ?> ' + res.data.saved_at;
                }
                try {
                    window.sessionStorage.setItem(floscAttachStash, JSON.stringify({ text: confirmed }));
                } catch (e) {
                    // Without storage the reload eats the confirmation, which is
                    // where this started — so say it here and pause long enough
                    // to be read before reloading.
                    attachNote.addClass('flosc-attach-ok').text(confirmed);
                    window.setTimeout(function () { window.location.reload(); }, 1200);
                    return;
                }
                window.location.reload();
            } else {
                attachSel.prop('disabled', false);
                var failed = (res && res.data && res.data.message) ? res.data.message : floscAttach.fallback;
                attachNote.addClass('flosc-attach-bad').text(failed);
            }
        }).fail(function (xhr) {
            attachSel.prop('disabled', false);
            var msg = floscAttach.fallback;
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            attachNote.addClass('flosc-attach-bad').text(msg);
        });
    });

    // --- Chaining toggle ---
    $('#flow_ai_enable_chaining').on('change', function() {
        $('#flosc-chain-config').toggle(this.checked);
    });

    // --- The provider's model list, as something to click ---
    // Two lists matter and only one of them works here: what the API key can
    // see at the provider, and what the installed WordPress AI Provider plugin
    // carries. A model can be live at the provider and absent from the plugin's
    // catalog — which is the failure that reads as a bad key and is not one.
    //
    // A list nobody can click is not a choice. The ids go into the datalist for
    // typing, and into a visible row of buttons that fill the field on click.
    var floscModelFieldFor = {
        anthropic: 'flow_ai_anthropic_model',
        openai:    'flow_ai_openai_model',
        xai:       'flow_ai_xai_model',
        gemini:    'flow_ai_gemini_model'
    };

    function floscRenderModelPicker($picker, targetId, models) {
        var $field = $('#' + targetId);

        $picker.empty();

        if (!$field.length || !models || !models.length) {
            $picker.attr('hidden', true);
            return 0;
        }

        var current = String($field.val() || '');
        var heading = models.length + (models.length === 1 ? ' model' : ' models') + ' this key can use';

        $('<p>').addClass('flosc-model-picker__intro').text(heading + '. Click one to use it:').appendTo($picker);

        var $list = $('<div>').addClass('flosc-model-picker__list').appendTo($picker);

        models.forEach(function (m) {
            var $b = $('<button>')
                .attr('type', 'button')
                .addClass('button flosc-model-choice')
                .attr('data-id', m.id)
                .attr('data-target', targetId)
                .text(m.id);

            if (m.label && m.label !== m.id) {
                $('<span>').addClass('flosc-model-choice__label').text(m.label).appendTo($b);
            }

            if (m.id === current) {
                $b.addClass('flosc-model-choice--current');
            }

            $b.appendTo($list);
        });

        $('<p>')
            .addClass('flosc-model-picker__note')
            .text('Clicking one saves it for this flow straight away.')
            .appendTo($picker);

        $picker.removeAttr('hidden');

        // Typing still works, and the suggestions now match reality.
        var listId = $field.attr('list');

        if (listId) {
            var $dl = $('#' + listId).empty();

            models.forEach(function (m) {
                $('<option>').attr('value', m.id).text(m.label || m.id).appendTo($dl);
            });
        }

        return models.length;
    }

    // Choosing from the fetched list has to be the end of the job, so the pick
    // is saved on the spot. A choice that only fills a field, and is lost
    // unless a page-wide Save is found afterwards, is not a choice.
    $(document).on('click', '.flosc-model-choice', function () {
        var $b = $(this);
        var targetId = $b.data('target');
        var id = String($b.data('id'));
        var $field = $('#' + targetId);
        var provider = String(targetId).replace(/^flow_ai_/, '').replace(/_model$/, '');
        var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');

        if (!$field.length) { return; }

        $field.val(id).trigger('change').trigger('input');
        $('.flosc-model-choice[data-target="' + targetId + '"]').removeClass('flosc-model-choice--current');
        $b.addClass('flosc-model-choice--current');
        $status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok').text('Saving ' + id + '…');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_save_ai_provider_model',
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                provider: provider,
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>',
                model: id
            },
            success: function (response) {
                if (!response || !response.success) {
                    $status.addClass('flosc-model-fetch-status--bad')
                        .text((response && response.data && response.data.message) || 'Could not save the model.');
                    return;
                }

                $status.addClass('flosc-save-key-status--ok').text('\u2713 Model saved — ' + id);

                // The test reads saved settings; this model is now one of them.
                if (typeof floscSavedAi === 'object' && floscSavedAi) {
                    floscSavedAi[targetId] = id;
                }

                // Describe it now, unasked. What this model can do, and how long
                // a reply it can give, is exactly what an operator needs at the
                // moment they choose it — not after finding another button.
                floscDescribeModel(provider, targetId, id, null, false);
            },
            error: function () {
                $status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
            }
        });
    });

    // Save one key on its own. The full-page Save still works; this exists so
    // "did the key save?" has an answer next to the field, at the moment it is
    // pasted, instead of a banner at the top of a long tab.
    $('.flosc-save-key').on('click', function () {
        var $btn = $(this);
        var provider = $btn.data('provider');
        var targetId = $btn.data('target');
        var $status = $('.flosc-save-key-status[data-for="' + targetId + '"]');
        var key = String($('#' + targetId).val() || '').trim();

        if (!key) {
            $status.removeClass('flosc-save-key-status--ok')
                .addClass('flosc-model-fetch-status--bad').text('Paste a key first.');
            return;
        }

        $btn.prop('disabled', true);
        $status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok').text('Saving…');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_save_ai_provider_key',
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                provider: provider,
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>',
                api_key: key
            },
            success: function (response) {
                if (!response || !response.success) {
                    $status.addClass('flosc-model-fetch-status--bad')
                        .text((response && response.data && response.data.message) || 'Could not save the key.');
                    return;
                }

                var d = response.data || {};
                $status.removeClass('flosc-model-fetch-status--bad')
                    .addClass('flosc-save-key-status--ok')
                    .text('✓ Saved' + (d.suffix ? ' — ends ' + d.suffix : '') + '.');

                // The test reads saved settings; this key is now one of them, so
                // the unsaved-settings guard must stop calling it unsaved.
                if (typeof floscSavedAi === 'object' && floscSavedAi) {
                    floscSavedAi[targetId] = $('#' + targetId).val();
                }

                var $line = $('#' + targetId).closest('td').find('.flosc-key-state');

                if ($line.length) {
                    $line.removeClass('flosc-key-state--none').addClass('flosc-key-state--ok')
                        .text('Saved on this flow' + (d.suffix ? ' — ends ' + d.suffix : ''));
                }
            },
            error: function () {
                $status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    // Ask the provider to describe the chosen model. Everything rendered comes
    // from the provider; FLOSC ranks nothing and invents nothing. The real
    // max output also raises the Max Tokens ceiling, which used to be a
    // hardcoded 4096 belonging to no model.
    function floscDescribeModel(provider, targetId, model, $btn, announce) {
        var $detail = $('.flosc-model-detail[data-for="' + targetId + '"]');
        var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');

        if (!model) {
            $status.addClass('flosc-model-fetch-status--bad').text('Choose or type a model first.');
            return;
        }

        if ($btn) { $btn.prop('disabled', true); }

        if (announce) {
            $status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok')
                .text('Asking the provider about ' + model + '…');
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_describe_ai_model',
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                provider: provider,
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>',
                model: model,
                api_key: $('#flow_' + provider + '_api_key').val() || ''
            },
            success: function (response) {
                if (!response || !response.success) {
                    $detail.attr('hidden', true).empty();
                    $status.addClass('flosc-model-fetch-status--bad')
                        .text((response && response.data && response.data.message) || 'Could not describe the model.');
                    return;
                }

                var d = response.data || {};
                var n = function (v) { return Number(v || 0).toLocaleString(); };

                $detail.empty();
                $('<p>').addClass('flosc-model-detail__name')
                    .text((d.display_name || d.id) + ' — as described by the provider').appendTo($detail);

                var $ul = $('<ul>').addClass('flosc-model-detail__list').appendTo($detail);
                $('<li>').text('Context window: ' + n(d.max_input_tokens) + ' tokens').appendTo($ul);
                $('<li>').text('Longest reply it can produce: ' + n(d.max_tokens) + ' tokens').appendTo($ul);

                if (d.features && d.features.length) {
                    $('<li>').text('Can also: ' + d.features.join(', ')).appendTo($ul);
                }

                if (d.effort_levels && d.effort_levels.length) {
                    $('<li>').text('Effort levels: ' + d.effort_levels.join(', ')).appendTo($ul);
                }

                // Let Max Tokens go as high as this model really allows.
                var $mt = $('#flow_ai_max_tokens');

                if ($mt.length && d.max_tokens > 0) {
                    $mt.attr('max', d.max_tokens);

                    $('<p>').addClass('flosc-model-detail__note')
                        .text('Temperature is not listed here because Anthropic does not publish sampling support, and FLOSC does not send it to Claude at all.')
                        .appendTo($detail);

                    // Say it again where the number is actually typed.
                    var current = parseInt($mt.val(), 10) || 0;
                    var $mtNote = $('#flosc-max-tokens-live');

                    if (!$mtNote.length) {
                        $mtNote = $('<p>').attr('id', 'flosc-max-tokens-live').addClass('flosc-key-state');
                        $mt.closest('td').append($mtNote);
                    }

                    if (current > d.max_tokens) {
                        $mtNote.removeClass('flosc-key-state--ok').addClass('flosc-key-state--none')
                            .text((d.display_name || d.id) + ' accepts at most ' + n(d.max_tokens) +
                                  ' — ' + n(current) + ' is above that. Lower it, or the provider will reject the request.');
                    } else {
                        $mtNote.removeClass('flosc-key-state--none').addClass('flosc-key-state--ok')
                            .text((d.display_name || d.id) + ' accepts up to ' + n(d.max_tokens) +
                                  ' tokens per reply. Yours is ' + n(current) + '.');
                    }
                }

                $detail.removeAttr('hidden');

                if (announce) {
                    $status.addClass('flosc-save-key-status--ok').text('\u2713 Described by the provider.');
                }
            },
            error: function () {
                $status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
            },
            complete: function () { if ($btn) { $btn.prop('disabled', false); } }
        });
    }

    // Check what was typed before it is saved. The provider decides whether a
    // parameter is real; this only catches what could never reach a provider —
    // a line that is not name: value, or JSON that does not parse.
    // What each parameter does, so the answer is in the field rather than in a
    // provider's documentation in another tab. Never a gate: a name absent from
    // here is still sent, and says so.
    var floscParamRef = <?php echo wp_json_encode( function_exists( 'flosc_model_parameter_reference' ) ? flosc_model_parameter_reference() : array() ); ?>;

    // The same reference, split by provider, plus the whole-set recipes and the
    // measured per-model notes. All four providers are handed over at once so
    // that changing provider or model re-renders the menu from data already in
    // the page rather than from another round trip.
    var floscParamsByProvider = <?php
        $flosc_menu = array();
        foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_menu_provider ) {
            $flosc_menu[ $flosc_menu_provider ] = function_exists( 'flosc_model_parameters_for_provider' )
                ? flosc_model_parameters_for_provider( $flosc_menu_provider )
                : array();
        }
        echo wp_json_encode( $flosc_menu );
    ?>;

    var floscParamRecipes = <?php
        $flosc_recipes = array();
        foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_menu_provider ) {
            $flosc_recipes[ $flosc_menu_provider ] = function_exists( 'flosc_model_parameter_recipes' )
                ? flosc_model_parameter_recipes( $flosc_menu_provider )
                : array();
        }
        echo wp_json_encode( $flosc_recipes );
    ?>;

    var floscParamModelNotes = <?php
        $flosc_notes = array();
        foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_menu_provider ) {
            $flosc_note_profile = function_exists( 'flosc_provider_api_profile' )
                ? flosc_provider_api_profile( $flosc_menu_provider )
                : null;
            $flosc_notes[ $flosc_menu_provider ] = is_array( $flosc_note_profile )
                ? (array) ( $flosc_note_profile['model_parameter_notes'] ?? array() )
                : array();
        }
        echo wp_json_encode( $flosc_notes );
    ?>;

    // Where each provider explains one parameter, in its own words. A template
    // taking the parameter name where the anchor scheme has been read off the
    // live page, '' where it has not — and the caller falls back to the
    // provider's page rather than inventing an anchor.
    var floscProviderParamDocs = <?php
        $flosc_param_docs = array();
        foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_menu_provider ) {
            $flosc_param_profile = function_exists( 'flosc_provider_api_profile' )
                ? flosc_provider_api_profile( $flosc_menu_provider )
                : null;
            $flosc_param_docs[ $flosc_menu_provider ] = is_array( $flosc_param_profile )
                ? (string) ( $flosc_param_profile['param_doc_url'] ?? '' )
                : '';
        }
        echo wp_json_encode( $flosc_param_docs );
    ?>;

    var floscProviderDocs = <?php
        $flosc_docs = array();
        foreach ( array( 'anthropic', 'openai', 'xai', 'gemini' ) as $flosc_menu_provider ) {
            $flosc_docs[ $flosc_menu_provider ] = function_exists( 'flosc_provider_docs_url' )
                ? flosc_provider_docs_url( $flosc_menu_provider )
                : '';
        }
        echo wp_json_encode( $flosc_docs );
    ?>;

    var floscProviderLabels = {
        anthropic: 'Anthropic',
        openai:    'OpenAI',
        xai:       'xAI',
        gemini:    'Gemini'
    };

    // Locked, because it is a statement of what will be sent. Unlocking it is a
    // deliberate act, and from that point the operator's text is the truth.
    var floscParamsUnlocked = false;

    // Two states, and a way back from each. Editing is not a door that shuts
    // behind you: a save returns the request to rest, locked and complete,
    // with Edit alive again for whoever wants another go at it.
    function floscUnlockParams(focus) {
        if (floscParamsUnlocked) {
            if (focus) { $('#flow_ai_model_params').trigger('focus'); }
            return;
        }

        floscParamsUnlocked = true;
        $('#flow_ai_model_params').prop('readonly', false).removeClass('flosc-params-locked');
        if (focus) { $('#flow_ai_model_params').trigger('focus'); }
        $('#flosc-params-edit').prop('disabled', true).text('Editing…');
        $('#flosc-params-lock-note').text(
            'Editing. What you write is what FLOSC sends — Temperature and Max Tokens above follow it as ' +
            'you type. It saves itself when you click away.'
        );
    }

    function floscLockParams() {
        floscParamsUnlocked = false;
        $('#flow_ai_model_params').prop('readonly', true).addClass('flosc-params-locked');
        $('#flosc-params-edit').prop('disabled', false).text('Edit the request');
        $('#flosc-params-lock-note').text(
            'This is what will be sent. Built from the fields above — click Edit the request to add ' +
            'anything they cannot express.'
        );
    }

    $('#flosc-params-edit').on('click', function () { floscUnlockParams(true); });

    // Compose a first request when there is none. Never replace one that
    // exists: the text in that box is the operator's, and rebuilding it from
    // the fields is what silently dropped a temperature line on a provider
    // known to refuse temperature. Field edits reach the text through
    // floscSetParamValue, which changes one line and leaves the rest alone.
    function floscRebuildPreview() {
        var $box = $('#flow_ai_model_params');

        if (!$box.length || String($box.val() || '').trim() !== '') { return; }

        var provider = $('#flow_ai_provider').val();
        var rejects = (floscProviderRejects[provider] || { params: [] }).params;
        var lines = [];
        var t = String($('#flow_ai_temperature').val() || '').trim();
        var m = String($('#flow_ai_max_tokens').val() || '').trim();

        if (t && rejects.indexOf('temperature') === -1) { lines.push('temperature: ' + t); }
        if (m) { lines.push('max_tokens: ' + m); }

        // Anything already stored that no field represents.
        String($box.data('extra') || '').split(/\r\n|\r|\n/).forEach(function (line) {
            if (line.trim()) { lines.push(line.trim()); }
        });

        $box.val(lines.join('\n'));
        floscCheckParams();
    }

    // Guards the two-way sync between a field and the line that carries it, so
    // writing one does not bounce back and rewrite the other.
    var floscSyncing = false;

    // Set one parameter in the request and leave every other line exactly as
    // the operator wrote it — same name, same place, same ordering. An empty
    // field removes its line rather than sending an empty value.
    function floscSetParamValue(name, value) {
        var $box = $('#flow_ai_model_params');

        if (!$box.length || floscSyncing) { return; }

        floscSyncing = true;

        var current = String($box.val() || '').trim();
        value = String(value).trim();

        if (current.charAt(0) === '{') {
            var obj = null;

            try { obj = JSON.parse(current); } catch (e) { obj = null; }

            if (obj) {
                if (value === '') {
                    delete obj[name];
                } else {
                    var typed;

                    try { typed = JSON.parse(value); } catch (e2) { typed = value; }

                    obj[name] = typed;
                }

                $box.val(JSON.stringify(obj, null, 2));
                floscSyncing = false;
                floscCheckParams();
                return;
            }
        }

        var lines = current ? current.split(/\r\n|\r|\n/) : [];
        var found = false;

        lines = lines.filter(function (line) {
            var bit = floscSplitParamLine(line);

            if (!bit || bit.name !== name) { return true; }

            found = true;

            return value !== '';
        }).map(function (line) {
            var bit = floscSplitParamLine(line);

            return (bit && bit.name === name) ? name + ': ' + value : line;
        });

        if (!found && value !== '') {
            lines.push(name + ': ' + value);
        }

        $box.val(lines.filter(function (l) { return l.trim(); }).join('\n'));
        floscSyncing = false;
        floscCheckParams();
    }

    $('#flow_ai_temperature').on('input change', function () {
        var provider = floscCurrentProvider();

        // A provider that refuses this one never carries it, typed or not.
        var field = (typeof floscModelFieldFor === 'object' && floscModelFieldFor)
            ? floscModelFieldFor[provider]
            : '';
        var model = field ? String($('#' + field).val() || '').trim() : '';

        if (floscModelRejectsParam(provider, model, 'temperature')) {
            return;
        }

        floscSetParamValue('temperature', $(this).val());
    });

    $('#flow_ai_max_tokens').on('input change', function () {
        floscSetParamValue('max_tokens', $(this).val());
    });

    $('#flow_ai_provider').on('change', floscRebuildPreview);

    // ---- Saving Step 2b where it is typed -----------------------------------
    //
    // The page-wide Save at the foot of Settings still writes these. This is
    // the same write, reachable from the controls themselves, and it runs on
    // its own when a value is committed — a green flash on the field that
    // changed, so the answer to "did that take?" is visible without scrolling
    // to find out.

    var floscTuningTimer = null;
    var floscTuningInFlight = false;

    // One slot for the save that has to happen next. A save already running is
    // not a reason to throw the next one away — it is a reason to make it wait.
    // Manual outranks automatic: a deliberate press is stronger intent than a
    // background write, so it takes the slot even from an autosave already in it.
    var floscTuningPending = null;

    // What the last completed save stored, so a no-op can still report a real
    // state instead of leaving the button mid-sentence.
    var floscTuningLastSavedAt = '';
    var floscTuningLastSaveSource = 'manual';

    // Raised on pointerdown, which happens before the textarea loses focus.
    // Asking afterwards whether Save has focus cannot work: pressing Save
    // disables Save, and disabling a focused element takes its focus away — so
    // the check answered "they didn't click Save" about the click that was
    // running it. Intent is captured, never inferred after the fact.
    var floscManualSaveArmed = false;

    function floscQueueTuningSave($flash, source) {
        source = (source === 'manual') ? 'manual' : 'auto';

        if (!floscTuningPending || source === 'manual') {
            floscTuningPending = { source: source, flash: $flash };
        }
    }

    // The save button is the state, rather than a button beside a state: at
    // rest it is a green statement that the tuning is stored, and it turns
    // back into something to press the moment that stops being true.
    function floscTuningState(state, detail, source) {
        var $btn = $('#flosc-save-tuning');
        var $status = $('#flosc-tuning-status');
        var auto = (source === 'auto');

        $btn.removeClass('button-primary flosc-tuning-save--saved flosc-tuning-save--bad')
            .prop('disabled', false);
        $status.attr('class', 'flosc-tuning-status').text('');

        if (state === 'saved') {
            // How the save happened, not whether a timestamp came back. Every
            // successful save returns one, so reading the stamp as the source
            // labelled a deliberate press of Save as something the page did on
            // its own — which is exactly the confidence this is meant to give.
            $btn.addClass('flosc-tuning-save--saved')
                .html(auto ? '&#10003; Autosaved' : '&#10003; Saved')
                .attr('title', auto
                    ? 'Saved on its own. Press to save again.'
                    : 'Saved on this flow. Press to save again.');

            // The stamp sits beside the button rather than inside it: it is a
            // full Michel Time Stamp, and a button that changes width every
            // time it is pressed moves the page under whoever pressed it.
            if (detail) {
                $status.addClass('flosc-tuning-status--ok').text(detail);
            }

            return;
        }

        if (state === 'saving') {
            $btn.prop('disabled', true).text(auto ? 'Autosaving…' : 'Saving…');
            return;
        }

        if (state === 'bad') {
            $btn.addClass('flosc-tuning-save--bad').text('Save Model Tuning')
                .attr('title', 'Not saved.');
            $status.addClass('flosc-tuning-status--bad').text('\u2717 ' + (detail || 'Not saved.'));
            return;
        }

        // Dirty: something changed and has not reached the database yet.
        $btn.addClass('button-primary').text('Save Model Tuning')
            .attr('title', 'Save this flow\u2019s tuning now.');
        $status.text(detail || 'Unsaved changes.');
    }

    // The page was rendered from what is stored, so at load that is exactly
    // what the controls are showing. Nothing was autosaved to get here.
    floscTuningState('saved', '', 'manual');

    // What was last written, so an autosave that would rewrite the identical
    // values does not make a second round trip. A deliberate press always goes.
    var floscTuningLastSaved = '';

    function floscTuningFingerprintOf(provider, temperature, maxTokens, params) {
        return JSON.stringify({
            provider: String(provider || ''),
            temperature: String(temperature || ''),
            max_tokens: String(maxTokens || ''),
            params: String(params || '')
        });
    }

    function floscTuningFingerprint() {
        return floscTuningFingerprintOf(
            floscCurrentProvider(),
            $('#flow_ai_temperature').val(),
            $('#flow_ai_max_tokens').val(),
            $('#flow_ai_model_params').val()
        );
    }

    // One place that schedules an unattended save, so cancelling one is one call.
    function floscScheduleTuningAutosave($field, delay) {
        window.clearTimeout(floscTuningTimer);
        floscTuningTimer = window.setTimeout(function () {
            floscSaveTuning($field, 'auto');
        }, (typeof delay === 'number') ? delay : 1400);
    }

    floscTuningLastSaved = floscTuningFingerprint();

    function floscFlashSaved($el) {
        if (!$el || !$el.length) { return; }

        $el.removeClass('flosc-saved-flash');

        // Reading offsetWidth restarts the CSS transition on an element that
        // was flashed a moment ago; without it a second save shows nothing.
        $el[0].offsetWidth;
        $el.addClass('flosc-saved-flash');

        window.setTimeout(function () { $el.removeClass('flosc-saved-flash'); }, 1600);
    }

    function floscSaveTuning($flash, source) {
        source = (source === 'manual') ? 'manual' : 'auto';

        var provider = floscCurrentProvider();
        var $status = $('#flosc-tuning-status');

        if (!provider || provider === 'ivr') {
            $status.attr('class', 'flosc-tuning-status flosc-tuning-status--bad')
                .text('Pick an AI provider for this flow first.');
            return;
        }

        if (floscTuningInFlight) {
            floscQueueTuningSave($flash, source);
            $('#flosc-tuning-status').attr('class', 'flosc-tuning-status')
                .text(source === 'manual' ? 'Save queued…' : 'Latest changes waiting to save…');
            return;
        }

        // A pause in typing and the blur that follows it are one edit, not two.
        // Writing the same values twice is a wasted round trip and makes the
        // indicator flicker for no reason anyone can see.
        var fingerprint = floscTuningFingerprint();

        // Already stored. Nothing to write — but the button may be sitting in
        // Saving from the call that got us here, so say so rather than
        // returning into silence. No path out of a save may leave it there.
        //
        // The label describes the action that just finished, not the write that
        // happened to be last. Reporting the stored source instead attributed a
        // recipe click — an automatic path — to whatever had saved before it,
        // so clicking Use this could read Saved when nobody had pressed
        // anything. An autosave that finds the work already stored has still
        // done an autosave's job, and says so.
        if (fingerprint === floscTuningLastSaved) {
            floscTuningLastSaveSource = source;

            floscTuningState('saved', floscTuningLastSavedAt, source);
            return;
        }

        floscTuningInFlight = true;
        floscTuningState('saving', '', source);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_save_model_tuning',
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>',
                provider: provider,
                temperature: String($('#flow_ai_temperature').val() || ''),
                max_tokens: String($('#flow_ai_max_tokens').val() || ''),
                params: String($('#flow_ai_model_params').val() || '')
            },
            success: function (response) {
                if (!response || !response.success) {
                    // Almost always a request that will not parse, and the
                    // message names the line that broke it. Nothing was written,
                    // so nothing goes green and the request stays open for
                    // editing — re-locking it here would hide the mistake.
                    floscTuningState('bad', (response && response.data && response.data.message) || 'Not saved.', source);
                    return;
                }

                var d = response.data;

                // Stamped by the server, which is the machine that did the
                // writing. A browser clock can be wrong by hours, and "saved
                // at" is a claim only the writer can make.
                // What this request stored, taken from the answer rather than
                // from what the browser assumed before sending it.
                floscTuningLastSaved = floscTuningFingerprintOf(
                    provider, d.temperature, d.max_tokens, d.params
                );

                // This answer describes the form as it was when the request
                // left. If newer work is already waiting, that answer is now
                // older than the screen, and painting it back would undo an
                // edit the operator can see. Leave the screen alone and let the
                // queued save — which carries the newer values — have the last
                // word, including the last word on the label.
                floscTuningLastSavedAt = d.saved_at || '';
                floscTuningLastSaveSource = source;

                if (floscTuningPending) {
                    // This answer is older than the screen, so it must not be
                    // painted back over newer work — but it must still hand the
                    // button back. The queued save has the last word on the
                    // label; until it lands, the page says what is true.
                    $('#flosc-tuning-status').attr('class', 'flosc-tuning-status')
                        .text('Latest changes waiting to save…');
                    $('#flosc-save-tuning').prop('disabled', false)
                        .removeClass('flosc-tuning-save--saved flosc-tuning-save--bad')
                        .addClass('button-primary')
                        .text('Save Model Tuning');
                    return;
                }

                floscTuningState('saved', d.saved_at || '', source);

                // What came back is what is stored, after the same fold the
                // page-wide save performs. Showing it rather than what was
                // typed is the whole point: the page now displays the request
                // that will actually be sent.
                floscSyncing = true;

                if (!$('#flow_ai_temperature').is(':focus')) {
                    $('#flow_ai_temperature').val(d.temperature);
                }

                if (!$('#flow_ai_max_tokens').is(':focus')) {
                    $('#flow_ai_max_tokens').val(d.max_tokens);
                }

                if (!$('#flow_ai_model_params').is(':focus') && d.preview !== undefined) {
                    $('#flow_ai_model_params').val(d.preview).data('extra', d.params);
                }

                floscSyncing = false;
                floscCheckParams();

                floscFlashSaved($flash && $flash.length ? $flash : $('#flow_ai_model_params'));

                // Stored and complete, so the request goes back to being a
                // statement of what will be sent — unless the operator is still
                // inside it, in which case taking the box away mid-sentence
                // would be the rudest thing this page could do.
                if (floscParamsUnlocked && !$('#flow_ai_model_params').is(':focus')) {
                    floscLockParams();
                }
            },
            error: function () {
                // Through the state machine, not around it: the button was
                // disabled to say Saving, and something has to give it back.
                floscTuningState('bad', 'The save request did not complete.', source);
            },
            complete: function () {
                floscTuningInFlight = false;

                // Whatever was waiting goes now, on the next turn of the event
                // loop so this handler is off the stack first.
                if (floscTuningPending) {
                    var pending = floscTuningPending;
                    floscTuningPending = null;

                    window.setTimeout(function () {
                        floscSaveTuning(pending.flash, pending.source);
                    }, 0);
                }
            }
        });
    }

    $('#flosc-save-tuning').on('pointerdown mousedown touchstart', function () {
        floscManualSaveArmed = true;
        window.clearTimeout(floscTuningTimer);
    });

    $('#flosc-save-tuning').on('click', function (event) {
        event.preventDefault();
        window.clearTimeout(floscTuningTimer);
        floscSaveTuning($('#flow_ai_model_params'), 'manual');

        // Held through the focus transition that produced this click, then
        // released so the next blur is an ordinary blur again.
        window.setTimeout(function () { floscManualSaveArmed = false; }, 0);
    });

    // Any edit makes the form dirty, including one made while a save is
    // running. An in-flight request is an older snapshot of the form, not a
    // reason to stop noticing that the form has moved on since — so newer work
    // is marked, and queued behind the request that is already going.
    $('#flow_ai_temperature, #flow_ai_max_tokens, #flow_ai_model_params').on('input', function () {
        var $field = $(this);

        if (floscSyncing) { return; }

        // Typing by hand cancels an autosave a chip or recipe had scheduled.
        // Half a handwritten line is not something to send anywhere.
        if ($field.is('#flow_ai_model_params')) {
            window.clearTimeout(floscTuningTimer);
        }

        floscTuningState('dirty');

        if (floscTuningInFlight) {
            floscQueueTuningSave($field, 'auto');
        }
    });

    // The numeric fields save after a pause in typing. The request box does
    // not: half-written YAML would be refused on every keystroke and paint the
    // status red under somebody still writing it.
    $('#flow_ai_temperature, #flow_ai_max_tokens').on('input', function () {
        if (floscSyncing) { return; }

        floscScheduleTuningAutosave($(this), 1400);
    });

    $('#flow_ai_temperature, #flow_ai_max_tokens').on('change', function () {
        window.clearTimeout(floscTuningTimer);
        floscSaveTuning($(this), 'auto');
    });

    // Leaving the request saves it — unless you left it by reaching for Save.
    //
    // This is where the label was going wrong, and it was never the label's
    // fault. Pressing Save blurs the textarea first, so the textarea's own
    // change handler started an autosave, disabled the button, and the click
    // that caused all of it arrived to find a save already running. The press
    // was discarded and the page reported Autosaved for a deliberate save.
    //
    // focusout fires before focus lands, so the answer to "where did they go?"
    // is one turn of the event loop away. Waiting for it lets the click be the
    // save it was meant to be.
    $('#flow_ai_model_params').on('focusout', function (event) {
        var $field = $(this);

        window.clearTimeout(floscTuningTimer);

        // Leaving by way of Save is a manual save, and it is already running.
        // relatedTarget names where focus is going, before it gets there.
        var next = event.relatedTarget || null;

        if (floscManualSaveArmed || (next && next.id === 'flosc-save-tuning')) {
            return;
        }

        floscTuningTimer = window.setTimeout(function () {
            if (floscManualSaveArmed) { return; }

            floscSaveTuning($field, 'auto');
        }, 150);
    });

    // ---- The parameter menu -------------------------------------------------
    //
    // Pullable, visible, explained, usable: every parameter FLOSC has a note on
    // is a button that writes itself into the request, the note is beside it,
    // the whole-set recipes are one click, and for the parameter that arrived
    // after this plugin did there is the provider's own reference and the
    // provider's own model to ask.

    function floscCurrentProvider() {
        return String($('#flow_ai_provider').val() || '');
    }

    function floscCurrentModel() {
        var field = floscModelFieldFor[floscCurrentProvider()];

        return field ? String($('#' + field).val() || '').trim() : '';
    }

    // Same longest-prefix rule the PHP side uses: providers date their model
    // ids, so a note measured on claude-sonnet-4-5 covers -20250929.
    function floscModelNoteFor(provider, model) {
        // These maps are `var`s assigned further down this block, but callers
        // run during setup — updateProviderSections() is invoked long before
        // this line is reached. Hoisting makes the function callable while its
        // data is still undefined, and reading a property off that threw, which
        // aborted setup and left every handler after it unbound. A guard here
        // rather than a rule about ordering: this must answer safely whenever
        // it is asked, including before the page has finished assembling.
        var notes = (floscParamModelNotes && floscParamModelNotes[provider]) || {};
        var best = null, bestLen = -1;

        model = String(model || '');

        Object.keys(notes).forEach(function (prefix) {
            if (model.indexOf(prefix) === 0 && prefix.length > bestLen) {
                best = notes[prefix];
                bestLen = prefix.length;
            }
        });

        return best;
    }

    // One 'name: value' line into either notation the box may be holding.
    function floscSplitParamLine(line) {
        var i = String(line).indexOf(':');

        if (i === -1) { return null; }

        return {
            name: line.slice(0, i).trim(),
            raw:  line.slice(i + 1).trim()
        };
    }

    function floscInsertParamLines(text) {
        var $box = $('#flow_ai_model_params');

        if (!$box.length) { return; }

        floscUnlockParams(false);

        var current = String($box.val() || '').trim();
        var incoming = String(text).split(/\r\n|\r|\n/).filter(function (l) { return l.trim(); });

        if (current.charAt(0) === '{') {
            // Already JSON. Put the parameters inside the object rather than
            // appending lines that would stop it parsing.
            var obj;

            try { obj = JSON.parse(current); } catch (e) { obj = null; }

            if (obj) {
                incoming.forEach(function (line) {
                    var bit = floscSplitParamLine(line);

                    if (!bit) { return; }

                    var value;

                    try { value = JSON.parse(bit.raw); } catch (e2) { value = bit.raw; }

                    obj[bit.name] = value;
                });

                $box.val(JSON.stringify(obj, null, 2));
                floscCheckParams();
                floscTuningState('dirty');
                // FLOSC wrote this line, so it is known-good syntax and can
                // save itself. A short pause, so clicking three chips is one
                // save rather than three.
                floscScheduleTuningAutosave($box, 900);
                $box.trigger('focus');
                return;
            }
        }

        var lines = current ? current.split(/\r\n|\r|\n/) : [];
        var have = {};

        lines.forEach(function (line) {
            var bit = floscSplitParamLine(line);

            if (bit) { have[bit.name] = true; }
        });

        incoming.forEach(function (line) {
            var bit = floscSplitParamLine(line);

            if (bit && have[bit.name]) {
                // Replace rather than duplicate — two lines naming the same
                // parameter is not a request any provider can read.
                lines = lines.map(function (existing) {
                    var e = floscSplitParamLine(existing);

                    return (e && e.name === bit.name) ? line : existing;
                });
                return;
            }

            lines.push(line);

            if (bit) { have[bit.name] = true; }
        });

        $box.val(lines.filter(function (l) { return l.trim(); }).join('\n'));
        floscCheckParams();
        floscTuningState('dirty');
        floscScheduleTuningAutosave($box, 900);
        $box.trigger('focus');
    }

    function floscRenderParamMenu() {
        var provider = floscCurrentProvider();
        var model = floscCurrentModel();

        // IVR is the scripted local flow. It sends no request, so there is no
        // request to add parameters to.
        if (!provider || provider === 'ivr') {
            $('#flosc-param-menu').attr('hidden', true);
            return;
        }

        $('#flosc-param-menu').removeAttr('hidden');
        var label = floscProviderLabels[provider] || provider || 'this provider';
        var rows = floscParamsByProvider[provider] || {};
        var names = Object.keys(rows);

        $('#flosc-param-menu-where').text(
            model ? '— ' + label + ' · ' + model : '— ' + label
        );

        // What has actually been watched happen on this model, which is a
        // different and better fact than what the provider takes in general.
        var note = floscModelNoteFor(provider, model);
        var $measured = $('#flosc-param-menu-measured').empty();

        if (note && ((note.accepts && note.accepts.length) || (note.refuses && note.refuses.length))) {
            var parts = [];

            if (note.accepts && note.accepts.length) { parts.push('accepts ' + note.accepts.join(', ')); }
            if (note.refuses && note.refuses.length) { parts.push('refuses ' + note.refuses.join(', ')); }

            $measured.text('Measured on this model: ' + parts.join(' · ') + '.').removeAttr('hidden');
        } else if (model) {
            $measured.text(
                'Nothing has been measured on ' + model + ' here. The list below is what ' + label +
                ' is known to take; the model decides, and its answer comes back in Step 3.'
            ).removeAttr('hidden');
        } else {
            $measured.attr('hidden', true);
        }

        var refused = {};

        if (note && note.refuses) {
            note.refuses.forEach(function (n) { refused[n] = true; });
        }

        var $chips = $('#flosc-param-menu-chips').empty();

        if (!names.length) {
            $('<p>').addClass('description').text('FLOSC has no notes for this provider yet.').appendTo($chips);
        }

        names.forEach(function (name) {
            var ref = rows[name];
            var $chip = $('<button>')
                .attr('type', 'button')
                .addClass('button flosc-param-chip')
                .attr('data-param', name)
                .attr('data-insert', ref.example || (name + ': '))
                .text(name);

            if (refused[name]) {
                $chip.addClass('flosc-param-chip--refused')
                    .attr('title', model + ' refuses this one.');
            }

            $chip.appendTo($chips);
        });

        var $recipes = $('#flosc-param-menu-recipes').empty();
        var recipes = floscParamRecipes[provider] || [];

        if (!recipes.length) {
            $('<p>').addClass('description')
                .text('No setups for ' + label + ' yet — none of its models have been measured here, and a plausible guess is worth less than nothing.')
                .appendTo($recipes);
        }

        recipes.forEach(function (recipe, i) {
            var $r = $('<div>').addClass('flosc-param-recipe').appendTo($recipes);
            var $head = $('<div>').addClass('flosc-param-recipe__head').appendTo($r);

            $('<strong>').text(recipe.name).appendTo($head);
            $('<button>')
                .attr('type', 'button')
                .addClass('button button-small flosc-param-recipe__use')
                .attr('data-recipe', i)
                .text('Use this')
                .appendTo($head);

            $('<p>').addClass('flosc-param-recipe__why').text(recipe.why).appendTo($r);
            $('<pre>').addClass('flosc-param-recipe__params').text(recipe.params).appendTo($r);

            if (recipe.models) {
                $('<p>').addClass('flosc-param-recipe__models').text(recipe.models).appendTo($r);
            }
        });

        var docs = floscProviderDocs[provider] || '';
        var $docs = $('#flosc-param-docs');

        if (docs) {
            $docs.attr('href', docs).text('Open ' + label + '\u2019s own request reference \u2197').removeAttr('hidden');
        } else {
            $docs.attr('hidden', true);
        }

        $('#flosc-param-menu-note').attr('hidden', true).empty();
    }

    $('#flosc-param-menu-chips').on('click', '.flosc-param-chip', function () {
        var name = $(this).data('param');
        var ref = (floscParamsByProvider[floscCurrentProvider()] || {})[name];

        floscInsertParamLines(String($(this).data('insert')));

        // The explanation lands where the click did, not only in the list of
        // what is currently typed further up.
        var $note = $('#flosc-param-menu-note').empty();

        if (ref) {
            $('<code>').text(name).appendTo($note);
            $('<span>').addClass('flosc-param-menu__what').text(' ' + ref.what).appendTo($note);

            var $meta = $('<div>').addClass('flosc-param-menu__meta')
                .text('Range: ' + ref.range + ' · ' + ref.providers +
                      (ref.measured ? ' · measured against the live API' : ''))
                .appendTo($note);
            var $link = floscParamDocLink(name);

            if ($link) { $meta.append(document.createTextNode(' ')).append($link); }

            $note.removeAttr('hidden');
        }
    });

    $('#flosc-param-menu-recipes').on('click', '.flosc-param-recipe__use', function () {
        var recipes = floscParamRecipes[floscCurrentProvider()] || [];
        var recipe = recipes[parseInt($(this).data('recipe'), 10)];

        if (recipe) { floscInsertParamLines(recipe.params); }
    });

    // The parameter FLOSC has never heard of: ask the provider's own model,
    // through the key already configured on this flow. Labelled as the model's
    // answer, because a model can be wrong about its own API.
    $('#flosc-param-ask').on('click', function () {
        var $btn = $(this);
        var name = String($('#flosc-param-ask-name').val() || '').trim();
        var $out = $('#flosc-param-answer').removeClass('flosc-param-menu__answer--bad');

        if (!name) {
            $out.text('Type a parameter name first.').addClass('flosc-param-menu__answer--bad').removeAttr('hidden');
            return;
        }

        $btn.prop('disabled', true);
        $out.text('Asking ' + (floscCurrentModel() || 'the model') + '\u2026').removeAttr('hidden');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_explain_ai_parameter',
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>',
                param: name
            },
            success: function (response) {
                if (!response || !response.success) {
                    $out.text((response && response.data && response.data.message) || 'The provider did not answer.')
                        .addClass('flosc-param-menu__answer--bad');
                    return;
                }

                var d = response.data;

                $out.empty();
                $('<div>').addClass('flosc-param-menu__answersrc')
                    .text('Answered by ' + (d.model || d.provider) + ' just now — this is the model\u2019s account of its own API, not FLOSC\u2019s.')
                    .appendTo($out);
                $('<p>').text(d.answer).appendTo($out);

                var $row = $('<p>').appendTo($out);

                $('<button>')
                    .attr('type', 'button')
                    .addClass('button button-small')
                    .text('Add ' + d.param + ' to the request')
                    .on('click', function () { floscInsertParamLines(d.param + ': '); })
                    .appendTo($row);

                // Somewhere to check the model against, which matters most for
                // exactly the parameter nobody here has a note on.
                var $link = floscParamDocLink(d.param);

                if ($link) { $row.append(document.createTextNode(' ')).append($link); }
            },
            error: function () {
                $out.text('The request did not complete.').addClass('flosc-param-menu__answer--bad');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    $('#flow_ai_provider, #flow_ai_anthropic_model, #flow_ai_openai_model, #flow_ai_xai_model, #flow_ai_gemini_model')
        .on('input change', floscRenderParamMenu);

    // Changing the model can change which controls apply — Sonnet 4.5 takes
    // temperature where Sonnet 5 refuses it — so the rows are re-decided too.
    $('#flow_ai_anthropic_model, #flow_ai_openai_model, #flow_ai_xai_model, #flow_ai_gemini_model')
        .on('input change', function () {
            if (typeof updateProviderSections === 'function') { updateProviderSections(); }
        });

    floscRenderParamMenu();

    // The field and the line that carries it are two views of one value, so
    // they are kept equal as they are typed rather than argued over at save
    // time. Nothing is struck through and nothing is warned about, because
    // there is never a moment where the two disagree.
    function floscMarkOverrides(names, values) {
        if (floscSyncing) { return; }

        floscSyncing = true;

        ['temperature', 'max_tokens'].forEach(function (field) {
            var $input = $('#flow_ai_' + field);

            $('#flosc-overridden-' + field).attr('hidden', true).text('');
            $input.removeClass('flosc-input-overridden');

            // Never rewrite a field while it is being typed into: the value
            // is half-entered, and correcting "0.30" to "0.3" under the cursor
            // is the sort of help nobody asked for.
            if (!$input.length || $input.is(':focus') || names.indexOf(field) === -1) { return; }

            var value = values ? values[field] : undefined;

            if (value === undefined || value === null || typeof value === 'object') { return; }

            if (String($input.val()) !== String(value)) {
                $input.val(String(value));
            }
        });

        floscSyncing = false;
    }

    // FLOSC's note on a parameter is a paraphrase written for an operator, not
    // the authority. Every note carries the way out to the provider's own
    // entry for that exact parameter, in a new tab so nothing typed is lost.
    function floscParamDocLink(name) {
        var provider = floscCurrentProvider();
        var template = floscProviderParamDocs[provider] || '';
        var label = floscProviderLabels[provider] || provider;

        if (template) {
            return $('<a>')
                .addClass('flosc-param-doc')
                .attr('href', template.replace('%s', encodeURIComponent(name)))
                .attr('target', '_blank')
                .attr('rel', 'noopener noreferrer')
                .text(label + ' on ' + name + ' \u2197');
        }

        var page = floscProviderDocs[provider] || '';

        if (page) {
            // No anchor scheme read off that provider's page, so the reader is
            // sent to the page and told that is what this link is.
            return $('<a>')
                .addClass('flosc-param-doc')
                .attr('href', page)
                .attr('target', '_blank')
                .attr('rel', 'noopener noreferrer')
                .text(label + '\u2019s request reference \u2197');
        }

        // Nowhere to send them. Say so in words rather than leave the note
        // trailing off: an operator who finds nothing assumes there is nothing
        // to find, and there is — it is just not somewhere FLOSC can point at.
        return $('<span>')
            .addClass('flosc-param-doc flosc-param-doc--none')
            .text('For more on this parameter, see ' + label + '\u2019s own API reference.');
    }

    // Two sampling controls the provider will not take together. Named in the
    // order they appear, because that is the order the request applies them in:
    // the first one reaches the model and the rest are held back.
    function floscExplainSamplingClash($help, names) {
        var provider = floscCurrentProvider();
        var group = floscSamplingExclusive[provider] || [];

        if (group.length < 2) { return; }

        var present = names.filter(function (n) { return group.indexOf(n) !== -1; });

        if (present.length < 2) { return; }

        var kept = present[0];
        var held = present.slice(1);
        var label = floscProviderLabels[provider] || provider;
        var $row = $('<div>').addClass('flosc-param-help__row flosc-param-clash').prependTo($help);

        $('<strong>').text(label + ' will not take ' + present.join(' and ') + ' on the same request.').appendTo($row);
        $('<div>').addClass('flosc-param-help__meta').text(
            'It answers 400 to both, so FLOSC sends ' + kept + ' and holds back ' +
            held.join(', ') + '. Remove ' + held.join(' and ') +
            ' to make this request say what is actually sent.'
        ).appendTo($row);
    }

    function floscExplainParams(names) {
        var $help = $('#flosc-param-help').empty();

        if (!names.length) { $help.attr('hidden', true); return; }

        names.forEach(function (name) {
            var ref = floscParamRef[name];
            var $row = $('<div>').addClass('flosc-param-help__row').appendTo($help);
            var $link = floscParamDocLink(name);

            $('<code>').addClass('flosc-param-help__name').text(name).appendTo($row);

            if (!ref) {
                $('<span>').addClass('flosc-param-help__unknown')
                    .text('FLOSC has no note on this one. It will be sent as written and ' +
                          'the provider decides — its answer appears in Step 3.')
                    .appendTo($row);

                if ($link) {
                    $('<div>').addClass('flosc-param-help__meta').append($link).appendTo($row);
                }

                return;
            }

            $('<span>').addClass('flosc-param-help__what').text(ref.what).appendTo($row);

            var $meta = $('<div>').addClass('flosc-param-help__meta').appendTo($row);
            $('<span>').text('Range: ' + ref.range).appendTo($meta);
            $('<span>').text(ref.providers).appendTo($meta);

            if (ref.measured) {
                $('<span>').addClass('flosc-param-help__measured')
                    .text('measured against the live API').appendTo($meta);
            }

            if ($link) { $link.appendTo($meta); }
        });

        floscExplainSamplingClash($help, names);

        $help.removeAttr('hidden');
    }

    function floscCheckParams() {
        var $box = $('#flow_ai_model_params');
        var $out = $('#flosc-params-status');

        if (!$box.length) { return; }

        var raw = String($box.val() || '').trim();

        $out.removeClass('flosc-key-state--none flosc-key-state--ok');

        if (!raw) { $out.text(''); floscExplainParams([]); floscMarkOverrides([], {}); return; }

        if (raw.charAt(0) === '{') {
            try {
                var obj = JSON.parse(raw);
                var n = Object.keys(obj).length;
                $out.addClass('flosc-key-state--ok').text('\u2713 Valid JSON — ' + n + (n === 1 ? ' parameter' : ' parameters') + ' will be sent.');
                floscExplainParams(Object.keys(obj));
                floscMarkOverrides(Object.keys(obj), obj);
            } catch (e) {
                $out.addClass('flosc-key-state--none').text('\u2717 Not valid JSON: ' + e.message);
                floscExplainParams([]);
                floscMarkOverrides([], {});
            }
            return;
        }

        var lines = raw.split(/\r\n|\r|\n/), names = [], values = {}, bad = null;

        lines.forEach(function (line) {
            line = line.trim();
            if (!line || line.charAt(0) === '#' || line.indexOf('//') === 0) { return; }
            var i = line.indexOf(':');
            if (i < 1) { bad = bad || line; return; }
            var nm = line.slice(0, i).trim();
            names.push(nm);
            values[nm] = line.slice(i + 1).trim().replace(/,$/, '');
        });

        if (bad) {
            $out.addClass('flosc-key-state--none').text('\u2717 This line is not "name: value" — ' + bad);
            floscExplainParams([]);
            floscMarkOverrides([], {});
            return;
        }

        if (!names.length) { $out.text(''); floscExplainParams([]); floscMarkOverrides([], {}); return; }

        $out.addClass('flosc-key-state--ok').text('\u2713 ' + names.length + (names.length === 1 ? ' parameter' : ' parameters') + ' will be sent.');
        floscExplainParams(names);
        floscMarkOverrides(names, values);
    }

    $('#flow_ai_model_params').on('input change', floscCheckParams);
    floscCheckParams();

    $('.flosc-describe-model').on('click', function () {
        var $btn = $(this);
        floscDescribeModel($btn.data('provider'), $btn.data('target'), String($('#' + $btn.data('target')).val() || '').trim(), $btn, true);
    });

    $('.flosc-fetch-models').on('click', function () {
        var $btn = $(this);
        var provider = $btn.data('provider');
        var targetId = $btn.data('target');
        var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');
        var $picker = $('.flosc-model-picker[data-for="' + targetId + '"]');

        $btn.prop('disabled', true);
        $status.removeClass('flosc-model-fetch-status--bad').text('Asking the provider…');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_fetch_ai_models',
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                provider: provider,
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>',
                api_key: $('#flow_' + provider + '_api_key').val() || ''
            },
            success: function (response) {
                if (!response || !response.success) {
                    $picker.attr('hidden', true).empty();
                    $status.addClass('flosc-model-fetch-status--bad')
                        .text((response && response.data && response.data.message) || 'Could not fetch the list.');
                    return;
                }

                var all = response.data.models || [];

                if (floscRenderModelPicker($picker, targetId, all) === 0) {
                    $status.addClass('flosc-model-fetch-status--bad')
                        .text('The provider returned no models for this key.');
                    return;
                }

                $status.text('Your key reached the provider. Pick a model below.');
            },
            error: function () {
                $picker.attr('hidden', true).empty();
                $status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    // --- Connection test ---
    // The test runs against saved settings. Anything typed and not yet saved is
    // invisible to it, so testing then would diagnose the old value and blame
    // the provider for it. Remember what was on the page when it loaded, and
    // stop the test if the operator has changed it since.
    var floscSavedAi = {};
    $('.flosc-ai-key-input, #flow_ai_provider, .flosc-ai-model-select').each(function () {
        floscSavedAi[this.id] = $(this).val();
    });

    function floscUnsavedAiFields() {
        var changed = [];
        $('.flosc-ai-key-input, #flow_ai_provider, .flosc-ai-model-select').each(function () {
            if (floscSavedAi[this.id] !== undefined && floscSavedAi[this.id] !== $(this).val()) {
                var label = $('label[for="' + this.id + '"]').text().replace(/\s+/g, ' ').trim();
                changed.push(label || this.id);
            }
        });
        return changed;
    }

    // The test asks the provider for its model list before it tries to generate.
    // That call is the only step that isolates the key from everything built on
    // top of it, so its result is reported whether the test passed or failed —
    // and the ids it returns are rendered as buttons, not prose.
    function floscTestModelReport(d, lines) {
        var $picker = $('#test-model-picker').attr('hidden', true).empty();

        if (!d || !d.models_probed) { return; }

        if (d.models_error) {
            lines.push('');
            lines.push('Asking ' + (d.provider || 'the provider') + ' which models this key can use: ' + d.models_error);
            return;
        }

        var models = d.models || [];
        var targetId = floscModelFieldFor[d.provider];

        lines.push('');
        lines.push('\u2713 The key reached ' + (d.provider || 'the provider') + ', which lists ' +
            models.length + (models.length === 1 ? ' model' : ' models') + ' for it.');

        if (d.model && models.length) {
            var listed = models.some(function (m) { return m.id === d.model; });

            if (!listed) {
                lines.push('\u2717 "' + d.model + '" is not one of them.');
            }
        }

        if (targetId) {
            $picker.attr('data-for', targetId);
            floscRenderModelPicker($picker, targetId, models);
        }
    }

    $('#test-ai-connection').on('click', function() {
        var $btn = $(this);
        var $loading = $('#test-loading');
        var $results = $('#test-results');
        var $flosc_status = $('#test-status');
        var $details = $('#test-details');

        var unsaved = floscUnsavedAiFields();
        if (unsaved.length) {
            $results.show();
            $flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">\u2717 Unsaved AI settings</span>');
            $details.text(
                'These were changed but not saved yet:\n  ' + unsaved.join('\n  ') +
                '\n\nThe test runs against saved settings, so it would test the old values and blame the provider for it.' +
                '\n\nSave AI Settings, then Test again.'
            );
            return;
        }

        $btn.prop('disabled', true);
        $loading.show();
        $results.hide();
        $('#test-model-picker').attr('hidden', true).empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_test_ai_connection',
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>'
            },
            success: function(response) {
                $loading.hide();
                $results.show();
                
                if (response.success) {
                    var d = response.data || {};
                    var lines = [];
                    lines.push('Result: LIVE external API call succeeded (not IVR scripted mode)');
                    if (d.provider) { lines.push('Provider: ' + d.provider); }
                    if (d.model) { lines.push('Model used: ' + d.model); }
                    if (d.model_configured && d.model_configured !== d.model) {
                        lines.push('Model configured: ' + d.model_configured);
                    }
                    if (d.endpoint) { lines.push('Endpoint: ' + d.endpoint); }
                    if (d.api_key_present) {
                        lines.push('API key: present' + (d.api_key_suffix ? (' (…' + d.api_key_suffix + ')') : ''));
                    } else {
                        lines.push('API key: missing in saved settings');
                    }
                    if (d.flow_label || d.flow_ivr) {
                        lines.push('Flow: ' + (d.flow_label || d.flow_ivr) + (d.flow_ivr && d.flow_label ? (' (' + d.flow_ivr + ')') : ''));
                    }
                    if (d.response_time != null) { lines.push('Latency: ' + d.response_time + ' ms'); }
                    if (d.tokens_total > 0 || d.tokens_in > 0 || d.tokens_out > 0) {
                        lines.push(
                            'Tokens: in ' + (d.tokens_in || 0) +
                            ' / out ' + (d.tokens_out || 0) +
                            ' / total ' + (d.tokens_total || ((d.tokens_in || 0) + (d.tokens_out || 0)))
                        );
                    } else {
                        lines.push('Tokens: (provider did not return usage on this call)');
                    }
                    if (d.billing_source) { lines.push('Billing source: ' + d.billing_source); }
                    if (d.model_configured && d.model && d.model_configured !== d.model) {
                        lines.push('');
                        lines.push('Note: "' + d.model_configured + '" is not in the installed provider plugin\'s catalog,');
                        lines.push('so the provider answered with ' + d.model + ' instead. Pick a model the plugin offers,');
                        lines.push('or update the provider plugin, to use the one you configured.');
                    }
                    if (d.params_configured && d.params_configured.length) {
                        lines.push('Extra parameters configured: ' + d.params_configured.join(', '));
                    }
                    if (d.params_applied && d.params_applied.length) {
                        lines.push('\u2713 Carried by this request: ' + d.params_applied.join(', '));
                    }
                    if (d.params_unapplied && d.params_unapplied.length) {
                        lines.push('\u2717 Left out, with the reason each was refused:');
                        d.params_unapplied.forEach(function (p) { lines.push('    ' + p); });
                    }
                    lines.push('Model reply: ' + (d.response || '(empty)'));
                    floscTestModelReport(d, lines);
                    $flosc_status.html('<span class="flosc-pass-status flosc-pass-status--pass">✓ External API OK — key + model path verified</span>');
                    $details.text(lines.join('\n'));
                } else {
                    var ed = response.data || {};
                    var elines = [];
                    $flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Connection Failed</span>');
                    // Report the layer that actually failed. Everything above the
                    // failure is confirmed; only the last line is the diagnosis.
                    elines.push('\u2713 FLOSC configuration loaded');
                    if (ed.provider) { elines.push('\u2713 Provider selected: ' + ed.provider); }
                    if (ed.api_key_present === false) {
                        elines.push('\u2717 No API key in the saved settings');
                        elines.push('');
                        elines.push('Paste the key above, then Save Settings, then Test again. A key that is typed but not saved is not yet a key.');
                    } else {
                        if (ed.api_key_suffix) {
                            elines.push('\u2713 API key saved (\u2026' + ed.api_key_suffix + ')');
                        } else if (ed.api_key_present) {
                            elines.push('\u2713 API key saved');
                        }
                        if (ed.model) { elines.push('\u2713 Model requested: ' + ed.model); }
                        if (ed.endpoint) { elines.push('\u2713 Endpoint reached: ' + ed.endpoint); }
                        elines.push('\u2717 The request was rejected before a reply came back');
                        elines.push('');
                        elines.push('What the provider integration said:');
                    }
                    elines.push(ed.message || 'Unknown error');
                    if (ed.flow_ivr || ed.response_time != null) { elines.push(''); }
                    if (ed.flow_ivr) { elines.push('Flow: ' + ed.flow_ivr); }
                    if (ed.response_time != null) { elines.push('Round trip: ' + ed.response_time + ' ms'); }
                    floscTestModelReport(ed, elines);
                    $details.text(elines.join('\n'));
                }
            },
            error: function() {
                $loading.hide();
                $results.show();
                $flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Request Failed</span>');
                $details.text('Could not reach the server. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<details class="flosc-ai-acc">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Speech-to-text', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Only needed for audio quizzes.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">

<!-- Speech-to-Text Configuration -->
<hr class="flosc-ai-stt-divider">

<h2>🎤 Speech-to-Text Configuration</h2>
<p class="description flosc-ai-intro">
    Configure speech-to-text for audio-based quiz questions (pronunciation quizzes, audio-response scoring). <strong>Only needed if you're using audio quizzes.</strong>
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_stt_provider">Speech-to-Text Provider</label></th>
        <td>
            <select name="flow_stt_provider" id="flow_stt_provider" class="flosc-ai-provider-select">
                <option value="assemblyai" <?php selected($flosc_flow_settings['stt_provider'] ?? '', 'assemblyai'); ?>>AssemblyAI (High Accuracy)</option>
                <option value="openai" <?php selected($flosc_flow_settings['stt_provider'] ?? '', 'openai'); ?>>OpenAI Whisper (Multilingual)</option>
                <option value="custom" <?php selected($flosc_flow_settings['stt_provider'] ?? '', 'custom'); ?>>Custom Endpoint (Self-hosted)</option>
            </select>
            <p class="description">Choose the service that will transcribe audio recordings from quiz takers.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_assemblyai_api_key"><strong>AssemblyAI API Key</strong></label>
        </th>
        <td>
            <input type="password" id="flow_assemblyai_api_key" name="flow_assemblyai_api_key" value="<?php echo esc_attr( function_exists('flosc_admin_secret_input_value') ? flosc_admin_secret_input_value( $flosc_flow_settings['assemblyai_api_key'] ?? '' ) : ( current_user_can('manage_options') ? (string) ( $flosc_flow_settings['assemblyai_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-stt-input" placeholder="Your AssemblyAI key">
            <p class="description">
                <a href="https://www.assemblyai.com/dashboard/signup" target="_blank" class="button button-secondary flosc-ai-key-link">
                    📥 Get Your AssemblyAI API Key Here
                </a><br>
                <span class="flosc-ai-key-desc">Industry-leading accuracy for speech recognition.</span>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_custom_stt_endpoint"><strong>Custom STT Endpoint URL</strong></label>
        </th>
        <td>
            <input type="url" id="flow_custom_stt_endpoint" name="flow_custom_stt_endpoint" value="<?php echo esc_attr($flosc_flow_settings['custom_stt_endpoint'] ?? ''); ?>" class="regular-text flosc-ai-stt-input" placeholder="https://your-stt-endpoint.com/transcribe">
            <p class="description">Only required if using "Custom Endpoint" option above. URL to your self-hosted speech-to-text service.</p>
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
$flosc_sci_doc   = $flosc_sci_index ? $flosc_sci_index->load( $flosc_sci_stem ) : array( 'built_at' => '', 'posts' => array() );
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
$flosc_sci_action = is_array( $flosc_sci_notice ) ? sanitize_key( (string) ( $flosc_sci_notice['action'] ?? '' ) ) : ( isset( $flosc_get['site_index_action'] ) ? sanitize_key( (string) $flosc_get['site_index_action'] ) : '' );
$flosc_sci_err    = is_array( $flosc_sci_notice ) ? sanitize_text_field( (string) ( $flosc_sci_notice['message'] ?? '' ) ) : ( isset( $flosc_get['site_index_error'] ) ? sanitize_text_field( rawurldecode( (string) $flosc_get['site_index_error'] ) ) : '' );
$flosc_sci_msg    = '';
if ( $flosc_sci_action === 'rebuilt' ) {
	$flosc_sci_msg = $flosc_sci_err !== '' ? $flosc_sci_err : __( 'Site content index rebuilt.', 'flosc' );
} elseif ( $flosc_sci_action === 'excluded' ) {
	$flosc_sci_msg = __( 'Post excluded from the index.', 'flosc' );
} elseif ( $flosc_sci_action === 'included' ) {
	$flosc_sci_msg = __( 'Post included in the index again.', 'flosc' );
} elseif ( $flosc_sci_action === 'keywords' ) {
	$flosc_sci_msg = __( 'Keywords saved.', 'flosc' );
} elseif ( $flosc_sci_action === 'reindexed' ) {
	$flosc_sci_msg = __( 'Post reindexed.', 'flosc' );
} elseif ( $flosc_sci_action === 'error' ) {
	$flosc_sci_msg = $flosc_sci_err !== '' ? $flosc_sci_err : __( 'Site index action failed.', 'flosc' );
}
?>
<details class="flosc-ai-acc" id="flosc-site-index-section"<?php echo $flosc_sci_msg !== '' ? ' open' : ''; ?>>
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Site content index', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Posts this flow’s chat may cite.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">
<hr class="flosc-section-divider">
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Site content index', 'flosc' ); ?></h3>
<p class="description">
	<?php echo esc_html__( 'Indexes published posts across the site into a reference library. Chat pulls only matching posts when useful — never the whole library on every message. Set this flow’s content category on the Content tab for freeline, guest, and member product scope.', 'flosc' ); ?>
</p>

<?php if ( $flosc_sci_msg !== '' ) : ?>
	<div class="notice <?php echo esc_attr( $flosc_sci_action === 'error' ? 'notice-error' : 'notice-success' ); ?> inline flosc-margin-bottom-15"><p><?php echo esc_html( $flosc_sci_msg ); ?></p></div>
<?php endif; ?>

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
				if ( $flosc_sci_pid && $flosc_sci_mod !== '' ) {
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

<details class="flosc-ai-acc" id="flosc-kb-section" open>
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Knowledge Base', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Attached on the Knowledge Base tab.', 'flosc' ); ?></span>
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
$flosc_kb_stem = sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) );
$flosc_kb_ids  = function_exists( 'flosc_flow_knowledge_base_ids' ) ? flosc_flow_knowledge_base_ids( $flosc_kb_stem ) : array();
?>
<p class="description">
	<?php echo esc_html__( 'Markdown knowledge bases this flow may inject into chat, gated by Visitor / Guest / Member. Upload and attach them on the Knowledge Base tab.', 'flosc' ); ?>
	<a href="<?php echo esc_url( $flosc_kb_tab_url ); ?>"><?php echo esc_html__( 'Open Knowledge Base tab', 'flosc' ); ?></a>
</p>
<?php if ( $flosc_kb_ids === array() ) : ?>
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
// Fix 6: Regenerate Lesson Catalog button
$flosc_catalog_file   = function_exists('flosc_resolve_lesson_catalog_path')
    ? flosc_resolve_lesson_catalog_path()
    : (function_exists('flosc_config_file') ? flosc_config_file('lesson_catalog.md') : '');
$flosc_catalog_exists = $flosc_catalog_file && file_exists($flosc_catalog_file);
$flosc_catalog_gen    = get_option('flosc_lesson_catalog_generated', '');
$flosc_catalog_count  = get_option('flosc_lesson_catalog_count', 0);
$flosc_regen_url      = wp_nonce_url(admin_url('admin-post.php?action=flosc_regenerate_lesson_catalog'), 'flosc_regen_catalog');
?>
<div class="flosc-card-soft flosc-flex-row flosc-flex-align-center flosc-gap-16 flosc-margin-bottom-20">
    <div class="flosc-flex-1">
        <strong>Lesson Catalog</strong>
        <?php if ($flosc_catalog_exists): ?>
            <span class="flosc-text-green flosc-margin-left-8">✓ Generated</span>
            <?php if ($flosc_catalog_gen): ?><span class="flosc-text-gray-888 flosc-text-12 flosc-margin-left-8"><?php echo esc_html($flosc_catalog_gen); ?> (<?php echo (int)$flosc_catalog_count; ?> lessons)</span><?php endif; ?>
        <?php else: ?>
            <span class="flosc-text-danger flosc-margin-left-8">Not yet generated</span>
        <?php endif; ?>
        <p class="description flosc-margin-top-4">Auto-regenerates when a lesson post in the configured lessons category is saved. Manual regeneration queries published posts in that category.</p>
    </div>
    <a href="<?php echo esc_url($flosc_regen_url); ?>" class="button button-secondary">Regenerate Lesson Catalog</a>
</div>
</div>
</details>

<!-- ============================================ -->
<!-- SECTION: PROVIDER ACCURACY TEST -->
<!-- ============================================ -->
<?php
$flosc_acc_flow_name = (string) ( $flosc_flow_settings['identity']['name'] ?? $flosc_flow_settings['name'] ?? '' );
if ( $flosc_acc_flow_name === '' ) {
	$flosc_acc_flow_name = (string) ( $GLOBALS['flosc_current_ivr'] ?? 'this floscFlow' );
	$flosc_acc_flow_name = pathinfo( $flosc_acc_flow_name, PATHINFO_FILENAME );
	$flosc_acc_flow_name = $flosc_acc_flow_name !== '' ? $flosc_acc_flow_name : 'this floscFlow';
}
$flosc_acc_title = trim( (string) ( $flosc_flow_settings['identity']['title'] ?? $flosc_flow_settings['title'] ?? '' ) );
$flosc_acc_tagline = trim( (string) ( $flosc_flow_settings['identity']['tagline'] ?? $flosc_flow_settings['tagline'] ?? '' ) );
$flosc_acc_scope = trim( (string) ( $flosc_flow_settings['ai_topic_scope'] ?? '' ) );
if ( $flosc_acc_scope === '' && function_exists( 'flosc_personality_library_resolve_field' ) ) {
	$flosc_acc_scope = trim( (string) flosc_personality_library_resolve_field( 'ai_topic_scope', '' ) );
}
if ( $flosc_acc_scope === '' ) {
	$flosc_acc_scope = __( '(topic scope not set)', 'flosc' );
}
$flosc_acc_site = (string) get_bloginfo( 'name' );
if ( $flosc_acc_site === '' ) {
	$flosc_acc_site = (string) wp_parse_url( home_url(), PHP_URL_HOST );
}
if ( $flosc_acc_site === '' ) {
	$flosc_acc_site = 'this site';
}

// Content-agnostic templates (placeholders). Expanded for this flow for defaults / Reset.
$flosc_acc_templates = array(
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
$flosc_acc_title_for_prompt = $flosc_acc_title !== ''
	? $flosc_acc_title
	: '(none)';
$flosc_acc_var_map = array(
	'{flow_name}'   => $flosc_acc_flow_name,
	'{title}'       => $flosc_acc_title_for_prompt,
	'{tagline}'     => $flosc_acc_tagline,
	'{topic_scope}' => $flosc_acc_scope,
	'{site_name}'   => $flosc_acc_site,
);
$flosc_acc_expand = static function ( $text, $map ) {
	return str_replace( array_keys( $map ), array_values( $map ), (string) $text );
};
$flosc_acc_defaults_filled = array();
foreach ( $flosc_acc_templates as $flosc_acc_t ) {
	$flosc_acc_defaults_filled[] = $flosc_acc_expand( $flosc_acc_t, $flosc_acc_var_map );
}

// Saved suite: prefer templates with {placeholders}. Expanded-only legacy saves map back to templates when they match.
$flosc_acc_saved_raw = (string) ( $flosc_flow_settings['ai_accuracy_test_questions'] ?? '' );
$flosc_acc_saved_lines = array();
if ( trim( $flosc_acc_saved_raw ) !== '' ) {
	if ( strpos( $flosc_acc_saved_raw, "\n" ) === false && strpos( $flosc_acc_saved_raw, "\r" ) === false ) {
		// Mangled one-line save — use content-agnostic templates.
		$flosc_acc_saved_lines = $flosc_acc_templates;
	} else {
		$flosc_acc_saved_lines = array_values(
			array_filter(
				array_map( 'trim', preg_split( '/\r\n|\r|\n/', $flosc_acc_saved_raw ) ?: array() ),
				static function ( $l ) {
					return $l !== '';
				}
			)
		);
	}
}
// Edit fields hold templates (with {vars}). If a saved line equals the expanded default, show the template instead.
$flosc_acc_edit_lines = array();
if ( $flosc_acc_saved_lines === array() ) {
	$flosc_acc_edit_lines = $flosc_acc_templates;
} else {
	foreach ( $flosc_acc_saved_lines as $flosc_acc_si => $flosc_acc_line ) {
		$flosc_acc_tpl_i = $flosc_acc_templates[ $flosc_acc_si ] ?? '';
		$flosc_acc_filled_i = $flosc_acc_defaults_filled[ $flosc_acc_si ] ?? '';
		if ( $flosc_acc_tpl_i !== '' && ( $flosc_acc_line === $flosc_acc_filled_i || $flosc_acc_line === $flosc_acc_expand( $flosc_acc_tpl_i, $flosc_acc_var_map ) ) ) {
			$flosc_acc_edit_lines[] = $flosc_acc_tpl_i;
		} else {
			$flosc_acc_edit_lines[] = $flosc_acc_line;
		}
	}
}
$flosc_acc_row_count = max( count( $flosc_acc_templates ), count( $flosc_acc_edit_lines ) );
while ( count( $flosc_acc_edit_lines ) < $flosc_acc_row_count ) {
	$flosc_idx = count( $flosc_acc_edit_lines );
	$flosc_acc_edit_lines[] = $flosc_acc_templates[ $flosc_idx ] ?? '';
}
$flosc_acc_line_count = max( 1, count( array_filter( $flosc_acc_edit_lines ) ) );
$flosc_accuracy_personality_label = $flosc_personality_label !== ''
	? $flosc_personality_label
	: __( 'the selected personality', 'flosc' );
?>
</div>
</details>

<details class="flosc-ai-acc" id="flosc-accuracy-test">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Provider accuracy test', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php
	/* translators: %s: selected personality label. */
	echo esc_html( sprintf( __( 'Use %s to run template questions against this flow’s API.', 'flosc' ), $flosc_accuracy_personality_label ) );
	?></span>
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
		if ( $flosc_acc_val === '' && $flosc_acc_tpl !== '' ) {
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
				<code class="flosc-acc-row__template"><?php echo esc_html( $flosc_acc_tpl !== '' ? $flosc_acc_tpl : '—' ); ?></code>
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
		$flosc_accuracy_personality_label = $flosc_personality_label !== ''
			? $flosc_personality_label
			: __( 'selected personality', 'flosc' );
		?>
		<button type="button" id="flosc-run-accuracy-test" class="button button-primary"><?php
		/* translators: %s: selected personality label. */
		echo esc_html( sprintf( __( '▶ Run multi-turn provider test using %s', 'flosc' ), $flosc_accuracy_personality_label ) );
		?></button>
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

<?php ob_start(); ?>
jQuery(document).ready(function($) {
	function floscAccVarMap() {
		var raw = $('#flosc-accuracy-rows').attr('data-flosc-acc-map') || '{}';
		try { return JSON.parse(raw); } catch (e) { return {}; }
	}
	function floscAccExpand(text, map) {
		var out = String(text || '');
		$.each(map || {}, function(k, v) {
			out = out.split(k).join(v);
		});
		return out;
	}
	function floscAccRefreshPreviews() {
		var map = floscAccVarMap();
		$('#flosc-accuracy-rows .flosc-acc-row').each(function() {
			var t = $(this).find('.flosc-acc-row__input').val() || '';
			$(this).find('.flosc-acc-row__preview').text(floscAccExpand(t, map));
		});
	}
	function floscSyncAccuracyHidden() {
		var lines = [];
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var t = $.trim($(this).val() || '');
			if (t) { lines.push(t); }
		});
		$('#flow_ai_accuracy_test_questions').val(lines.join('\n'));
		$('#flosc-test-msg-total').text(Math.max(1, lines.length));
		floscAccRefreshPreviews();
	}
	function floscParseAccuracyQuestions() {
		floscSyncAccuracyHidden();
		var map = floscAccVarMap();
		var msgs = [];
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var t = $.trim($(this).val() || '');
			if (t) { msgs.push(floscAccExpand(t, map)); }
		});
		return msgs;
	}

	$('#flosc-accuracy-rows').on('input change', '.flosc-acc-row__input', floscSyncAccuracyHidden);

	$('#flosc-accuracy-rows').on('click', '.flosc-acc-reset-row', function() {
		var $row = $(this).closest('.flosc-acc-row');
		var $inp = $row.find('.flosc-acc-row__input');
		var tpl = $inp.attr('data-acc-template') || '';
		$inp.val(tpl);
		floscSyncAccuracyHidden();
	});

	$('#flosc-accuracy-reset-defaults').on('click', function() {
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			$(this).val($(this).attr('data-acc-template') || '');
		});
		floscSyncAccuracyHidden();
	});

	floscSyncAccuracyHidden();

	$('#flosc-run-accuracy-test').on('click', function() {
		var testMessages = floscParseAccuracyQuestions();
		if (!testMessages.length) {
			window.alert(<?php echo wp_json_encode( __( 'Add at least one test question.', 'flosc' ) ); ?>);
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#flosc-test-msg-total').text(testMessages.length);
		$('#flosc-test-progress').show();
		$('#flosc-accuracy-results').hide();
		$('#flosc-accuracy-tbody').empty();
		$('#flosc-accuracy-summary').empty();

		var history = [];
		var totalTokensIn = 0;
		var passes = 0;
		var feedback = 0;

		function runMessage(idx) {
			if (idx >= testMessages.length) {
				var summaryHtml = '<strong>Session Summary:</strong> '
					+ passes + '/' + testMessages.length + ' completed without hard fail | '
					+ 'Total input tokens: ' + totalTokensIn + ' | '
					+ 'Flags: ' + feedback;
				$('#flosc-accuracy-summary').html(summaryHtml);
				$('#flosc-accuracy-results').show();
				$('#flosc-test-progress').hide();
				$btn.prop('disabled', false);
				return;
			}
			$('#flosc-test-msg-num').text(idx + 1);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'flosc_accuracy_test_message',
					nonce: <?php echo wp_json_encode( wp_create_nonce( 'flosc_accuracy_test' ) ); ?>,
					ivr: <?php echo wp_json_encode( (string) ( $GLOBALS['flosc_current_ivr'] ?? '' ) ); ?>,
					message: testMessages[idx],
					message_index: idx,
					history: JSON.stringify(history)
				},
				success: function(resp) {
					var r = resp.data || {};
					var response_text = r.response || '(no response)';
					var tokens_in = r.tokens_in || 0;
					var pass = r.pass !== false;
					var corrected = r.corrected || false;

					totalTokensIn += tokens_in;
					if (pass) passes++;
					if (corrected) feedback++;

					var passCell = pass
						? '<td class="flosc-pass-status flosc-pass-status--pass">✓</td>'
						: '<td class="flosc-pass-status flosc-pass-status--fail">✗</td>';
					if (corrected) passCell = '<td class="flosc-pass-status flosc-pass-status--corrected">⚡</td>';

					var snippet = response_text.length > 200
						? response_text.substring(0, 200) + '…'
						: response_text;

					$('#flosc-accuracy-tbody').append(
						'<tr>'
						+ '<td>' + (idx + 1) + '</td>'
						+ '<td class="flosc-text-12">' + $('<div>').text(testMessages[idx]).html() + '</td>'
						+ '<td class="flosc-text-12">' + $('<div>').text(snippet).html() + '</td>'
						+ '<td>' + tokens_in + '</td>'
						+ passCell
						+ '</tr>'
					);

					history.push({ role: 'user', content: testMessages[idx] });
					history.push({ role: 'assistant', content: response_text });

					runMessage(idx + 1);
				},
				error: function() {
					$('#flosc-accuracy-tbody').append(
						'<tr><td>' + (idx + 1) + '</td><td>' + $('<div>').text(testMessages[idx]).html() + '</td><td colspan="3" class="flosc-request-failed">Request failed</td></tr>'
					);
					runMessage(idx + 1);
				}
			});
		}

		runMessage(0);
	});
});
<?php wp_add_inline_script( 'flosc-admin', ob_get_clean() ); ?>
</div>
</details>

</div><!-- .flosc-ai-config -->