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
$flosc_ai_single_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'tab'  => 'ai',
		'view' => 'single',
		'ivr'  => $flosc_current_ivr,
	),
	admin_url( 'admin.php' )
);
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

// Close main settings form for All Flows view (sibling forms for pool + personalities).
if ( 'all' === $flosc_ai_view ) {
	if ( empty( $GLOBALS['flosc_settings_form_closed_early'] ) ) {
		echo '</form>';
		$GLOBALS['flosc_settings_form_closed_early'] = true;
	}
	?>
<div class="flosc-docs-link-wrap">
	<a href="<?php echo esc_url( $flosc_ai_docs_url ); ?>" class="flosc-docs-link"><?php echo esc_html__( 'Docs', 'flosc' ); ?></a>
</div>
<div class="flosc-ivr-actions-row flosc-margin-bottom-16">
	<a href="<?php echo esc_url( $flosc_ai_single_url ); ?>" class="button <?php echo esc_attr( 'single' === $flosc_ai_view ? 'button-primary' : '' ); ?>">
		<?php echo esc_html__( 'This flow: AI settings', 'flosc' ); ?>
	</a>
	<a href="<?php echo esc_url( $flosc_ai_all_url ); ?>" class="button <?php echo esc_attr( 'all' === $flosc_ai_view ? 'button-primary' : '' ); ?>">
		<?php echo esc_html__( 'All Flows AI API Management', 'flosc' ); ?>
	</a>
</div>
	<?php
	require FLOSC_PLUGIN_DIR . 'admin/ai-all-flows.php';
	return;
}

$flosc_base_prompt = $flosc_flow_settings['ai_base_prompt'] ?? '';
$flosc_ai_provider = $flosc_flow_settings['ai_provider'] ?? 'ivr';
$flosc_ai_openai_model = $flosc_flow_settings['ai_openai_model'] ?? 'gpt-4o-mini';
$flosc_ai_anthropic_model = $flosc_flow_settings['ai_anthropic_model'] ?? 'claude-sonnet-4-5-20250929';
$flosc_ai_xai_model = $flosc_flow_settings['ai_xai_model'] ?? 'grok-4.5';
// Retired xAI slugs no longer resolve on api.x.ai — surface current default in the UI.
$flosc_xai_legacy_models = ['grok-2-latest', 'grok-2', 'grok-2-1212', 'grok-beta', 'grok-vision-beta'];
if (in_array($flosc_ai_xai_model, $flosc_xai_legacy_models, true)) {
    $flosc_ai_xai_model = 'grok-4.5';
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
$flosc_personality_id   = sanitize_key( (string) ( $flosc_flow_settings['personality_library_id'] ?? '' ) );
$flosc_personas         = function_exists( 'flosc_personality_library_get_all' ) ? flosc_personality_library_get_all() : array();
$flosc_avail            = function_exists( 'flosc_available_providers_get_all' ) ? flosc_available_providers_get_all() : array();

// Risk / setup notices — read directly from flow settings (get_current_flow() is null in admin context).
// No calendar-age nags for lesson catalog (stable catalogs can be fine for years).
$flosc_product_name = $flosc_flow_settings['identity']['name'] ?? $flosc_flow_settings['name'] ?? '';
$flosc_product_tag  = $flosc_flow_settings['identity']['tagline'] ?? $flosc_flow_settings['tagline'] ?? '';
$flosc_notices = [];
if ((float) $flosc_ai_temperature > 0.5) {
    $flosc_notices[] = '<strong>Temperature ' . esc_html($flosc_ai_temperature) . ' increases fabrication risk.</strong> Recommended: 0.3';
}
if (empty($flosc_product_name)) {
    $flosc_notices[] = '<strong>Product name not configured.</strong> AI has no identity and will hallucinate.';
}
if (!empty($flosc_product_name) && empty($flosc_product_tag)) {
    $flosc_notices[] = 'Reminder: The Product Tagline for this floscFlow is empty. You can set a short tagline on the Identity tab to improve how accurately AI describes this flow in chat.';
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

<div class="flosc-ivr-actions-row flosc-margin-bottom-16">
	<a href="<?php echo esc_url( $flosc_ai_single_url ); ?>" class="button button-primary">
		<?php echo esc_html__( 'This flow: AI settings', 'flosc' ); ?>
	</a>
	<a href="<?php echo esc_url( $flosc_ai_all_url ); ?>" class="button">
		<?php echo esc_html__( 'All Flows AI API Management', 'flosc' ); ?>
	</a>
</div>

<!-- Styles in assets/css/flosc-admin.css (AI Configuration section) -->

<div class="flosc-ai-config">

<h2><?php echo esc_html__( 'This flow: AI settings', 'flosc' ); ?></h2>
<p class="description flosc-ai-intro">
	<?php echo esc_html__( 'Attach one personality and choose which available AI providers this floscFlow uses. Configure install-wide keys and the personality library under All Flows AI API Management. APIs can be chained; personalities cannot.', 'flosc' ); ?>
</p>

<?php
$flosc_avail_bits = array();
foreach ( array( 'anthropic' => 'Anthropic', 'openai' => 'OpenAI', 'xai' => 'xAI', 'assemblyai' => 'AssemblyAI' ) as $flosc_slug => $flosc_lab ) {
	$flosc_avail_bits[] = $flosc_lab . ( ! empty( $flosc_avail[ $flosc_slug ]['api_key'] ) ? ' ✓' : ' —' );
}
?>
<p class="description">
	<strong><?php echo esc_html__( 'Install keys available:', 'flosc' ); ?></strong>
	<?php echo esc_html( implode( ' · ', $flosc_avail_bits ) ); ?>
	— <a href="<?php echo esc_url( $flosc_ai_all_url ); ?>"><?php echo esc_html__( 'Manage', 'flosc' ); ?></a>
</p>

<!-- Personality attach (exactly one) -->
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Personality (one per flow)', 'flosc' ); ?></h3>
<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_personality_library_id"><?php echo esc_html__( 'Attached personality', 'flosc' ); ?></label></th>
		<td>
			<select name="flow_personality_library_id" id="flow_personality_library_id">
				<option value="" <?php selected( $flosc_personality_id, '' ); ?>><?php echo esc_html__( 'Custom on this flow only (fields below)', 'flosc' ); ?></option>
				<?php foreach ( $flosc_personas as $flosc_pid => $flosc_p ) : ?>
				<option value="<?php echo esc_attr( $flosc_pid ); ?>" <?php selected( $flosc_personality_id, $flosc_pid ); ?>>
					<?php echo esc_html( $flosc_p['label'] !== '' ? $flosc_p['label'] : $flosc_pid ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php echo esc_html__( 'Library entries are managed under All Flows AI API Management → Personalities. One personality only — not chained.', 'flosc' ); ?>
			</p>
		</td>
	</tr>
</table>

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
                <option value="ivr" <?php selected($flosc_ai_provider, 'ivr'); ?>>IVR Only (Scripted Responses - Zero API Cost)</option>
                <option value="anthropic" <?php selected($flosc_ai_provider, 'anthropic'); ?>>Anthropic Claude (Recommended for FLOSC)</option>
                <option value="openai" <?php selected($flosc_ai_provider, 'openai'); ?>>OpenAI (Fast & Affordable)</option>
                <option value="xai" <?php selected($flosc_ai_provider, 'xai'); ?>>xAI Grok</option>
            </select>
            <p class="description">
                <strong>IVR:</strong> Uses your configured messages only (no AI, no costs).<br>
                <strong>AI Providers:</strong> Enable conversational AI with retrieval-augmented generation (RAG) powered by your IVR configuration and knowledge base.
            </p>
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

<!-- Anthropic -->
<div class="flosc-ai-provider-section" data-provider="anthropic">
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flow_anthropic_api_key"><strong>Anthropic API Key</strong></label>
        </th>
        <td>
            <input type="password" id="flow_anthropic_api_key" name="flow_anthropic_api_key" value="<?php echo esc_attr( function_exists('flosc_admin_secret_input_value') ? flosc_admin_secret_input_value( $flosc_flow_settings['anthropic_api_key'] ?? '' ) : ( current_user_can('manage_options') ? (string) ( $flosc_flow_settings['anthropic_api_key'] ?? '' ) : '' ) ); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-ant-api03-...">
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
            <select name="flow_ai_anthropic_model" id="flow_ai_anthropic_model" class="flosc-ai-model-select">
                <option value="claude-sonnet-4-5-20250929" <?php selected($flosc_ai_anthropic_model, 'claude-sonnet-4-5-20250929'); ?>>Claude Sonnet 4.5 (Recommended)</option>
                <option value="claude-haiku-4-5-20251001" <?php selected($flosc_ai_anthropic_model, 'claude-haiku-4-5-20251001'); ?>>Claude Haiku 4.5 (Fastest, cheapest)</option>
                <option value="claude-opus-4-6" <?php selected($flosc_ai_anthropic_model, 'claude-opus-4-6'); ?>>Claude Opus 4.6 (Most capable)</option>
                <option value="claude-3-5-sonnet-20241022" <?php selected($flosc_ai_anthropic_model, 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet (Legacy)</option>
            </select>
            <p class="description">Choose which Claude model to use for this flow.</p>
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
            <select name="flow_ai_openai_model" id="flow_ai_openai_model" class="flosc-ai-model-select">
                <option value="gpt-4o-mini" <?php selected($flosc_ai_openai_model, 'gpt-4o-mini'); ?>>GPT-4o-mini (Fast & affordable)</option>
                <option value="gpt-4o" <?php selected($flosc_ai_openai_model, 'gpt-4o'); ?>>GPT-4o (More capable)</option>
                <option value="gpt-4.1" <?php selected($flosc_ai_openai_model, 'gpt-4.1'); ?>>GPT-4.1 (Latest)</option>
                <option value="gpt-4.1-mini" <?php selected($flosc_ai_openai_model, 'gpt-4.1-mini'); ?>>GPT-4.1 Mini (Latest affordable)</option>
                <option value="gpt-4.1-nano" <?php selected($flosc_ai_openai_model, 'gpt-4.1-nano'); ?>>GPT-4.1 Nano (Cheapest)</option>
            </select>
            <p class="description">Choose which OpenAI model to use for this flow.</p>
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
            <select name="flow_ai_xai_model" id="flow_ai_xai_model" class="flosc-ai-model-select">
                <?php
                $flosc_xai_model_options = [
                    'grok-4.5'     => 'Grok 4.5 (Recommended — chat / coding)',
                    'grok-4-0709'  => 'Grok 4 (0709)',
                    'grok-4'       => 'Grok 4 (alias)',
                    'grok-3'       => 'Grok 3 (legacy alias if still enabled on your account)',
                    'grok-3-mini'  => 'Grok 3 Mini (legacy / budget)',
                ];
                $flosc_xai_known = array_keys($flosc_xai_model_options);
                if ($flosc_ai_xai_model !== '' && !in_array($flosc_ai_xai_model, $flosc_xai_known, true)) {
                    $flosc_xai_model_options[$flosc_ai_xai_model] = $flosc_ai_xai_model . ' (saved custom ID)';
                }
                foreach ($flosc_xai_model_options as $flosc_xai_id => $flosc_xai_label) :
                    ?>
                <option value="<?php echo esc_attr($flosc_xai_id); ?>" <?php selected($flosc_ai_xai_model, $flosc_xai_id); ?>><?php echo esc_html($flosc_xai_label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                API model ID sent to <code>api.x.ai</code>. Default: <code>grok-4.5</code>.
                If a model is retired, xAI returns “Model not found” — pick a current ID from
                <a href="https://docs.x.ai/developers/models" target="_blank" rel="noopener noreferrer">docs.x.ai/models</a>
                and Save again.
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
    Fine-tune AI behavior for this flow. These settings apply to whichever provider is selected above.
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_temperature">Temperature</label></th>
        <td>
            <input type="number" id="flow_ai_temperature" name="flow_ai_temperature" value="<?php echo esc_attr($flosc_ai_temperature); ?>" min="0" max="2" step="0.1" class="flosc-ai-temp-input">
            <p class="description">
                Controls randomness. <strong>0.0</strong> = fully deterministic, <strong>0.3</strong> = recommended (precision/coaching), <strong>0.7</strong> = creative/balanced, <strong>1.5+</strong> = highly random. Lower values reduce hallucination.
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_max_tokens">Max Tokens</label></th>
        <td>
            <input type="number" id="flow_ai_max_tokens" name="flow_ai_max_tokens" value="<?php echo esc_attr($flosc_ai_max_tokens); ?>" min="50" max="4096" step="50" class="flosc-ai-tokens-input">
            <p class="description">
                Maximum response length. <strong>500</strong> = concise chat (default), <strong>1000</strong> = detailed explanations, <strong>2000+</strong> = long-form. Higher values cost more per response.
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
                </select>
                <span class="description">Optional third pass. Leave as "None" for 2-provider chain.</span>
            </td>
        </tr>
    </table>
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
    </div>
    <div id="test-loading" class="flosc-ai-test-loading flosc-hidden">
        <span class="spinner is-active"></span>
        <span>Testing connection to AI provider...</span>
    </div>
</div>

<!-- Base System Prompt -->
<h3 class="flosc-ai-section-heading">
    <?php echo esc_html__( 'Personality details (used when Custom, or as library overrides are empty)', 'flosc' ); ?>
</h3>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_base_prompt">Base System Prompt</label></th>
        <td>
            <textarea id="flow_ai_base_prompt" name="flow_ai_base_prompt" rows="6" class="large-text flosc-ai-prompt-textarea" placeholder="Define your AI's personality and behavior here. Example: You are a friendly coach who helps users improve their skills..."><?php echo esc_textarea($flosc_base_prompt); ?></textarea>
            <p class="description">
                Define your AI's core personality and behavior. FLOSC automatically adds phase-specific instructions on top of this base prompt. Leave blank to use the AI Knowledge tab settings only.
            </p>
        </td>
    </tr>
</table>

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

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    // --- Provider section show/hide ---
    function updateProviderSections() {
        var selected = $('#flow_ai_provider').val();
        $('.flosc-ai-provider-section').each(function() {
            var provider = $(this).data('provider');
            if (provider === selected) {
                $(this).removeClass('is-hidden');
            } else {
                $(this).addClass('is-hidden');
            }
        });
    }
    $('#flow_ai_provider').on('change', updateProviderSections);
    updateProviderSections();

    // --- Chaining toggle ---
    $('#flow_ai_enable_chaining').on('change', function() {
        $('#flosc-chain-config').toggle(this.checked);
    });

    // --- Connection test ---
    $('#test-ai-connection').on('click', function() {
        var $btn = $(this);
        var $loading = $('#test-loading');
        var $results = $('#test-results');
        var $flosc_status = $('#test-status');
        var $details = $('#test-details');

        $btn.prop('disabled', true);
        $loading.show();
        $results.hide();

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
                    lines.push('Model reply: ' + (d.response || '(empty)'));
                    $flosc_status.html('<span class="flosc-pass-status flosc-pass-status--pass">✓ External API OK — key + model path verified</span>');
                    $details.text(lines.join('\n'));
                } else {
                    var ed = response.data || {};
                    var elines = [];
                    $flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Connection Failed</span>');
                    if (ed.provider) { elines.push('Provider: ' + ed.provider); }
                    if (ed.model) { elines.push('Model: ' + ed.model); }
                    if (ed.endpoint) { elines.push('Endpoint: ' + ed.endpoint); }
                    if (ed.api_key_present === false) {
                        elines.push('API key: missing in saved settings — Save Settings after paste');
                    } else if (ed.api_key_present && ed.api_key_suffix) {
                        elines.push('API key: present (…' + ed.api_key_suffix + ') — rejected by provider or wrong model');
                    }
                    if (ed.flow_ivr) { elines.push('Flow: ' + ed.flow_ivr); }
                    if (ed.response_time != null) { elines.push('Latency: ' + ed.response_time + ' ms'); }
                    elines.push(ed.message || 'Unknown error');
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

<!-- ============================================ -->
<!-- SECTION: AI PERSONALITY (Fix 12 / Fix 15) -->
<!-- ============================================ -->
<hr class="flosc-section-divider" id="flosc-personality-section">
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Personality fields', 'flosc' ); ?></h3>
<p class="description">Define who your AI is and how it interacts with users. These fields are injected into every AI system prompt.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_personality_name">AI Name</label></th>
        <td>
            <input type="text" id="flow_ai_personality_name" name="flow_ai_personality_name"
                   value="<?php echo esc_attr($flosc_flow_settings['ai_personality_name'] ?? ($flosc_flow_settings['ai_name'] ?? '')); ?>"
                   class="regular-text" placeholder="e.g. Course Coach">
            <p class="description">What users call the AI.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_personality_role">AI Role</label></th>
        <td>
            <input type="text" id="flow_ai_personality_role" name="flow_ai_personality_role"
                   value="<?php echo esc_attr($flosc_flow_settings['ai_personality_role'] ?? ($flosc_flow_settings['ai_role'] ?? '')); ?>"
                   class="large-text" placeholder="e.g. pronunciation coach and learning guide">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_personality_traits">Personality Traits</label></th>
        <td>
            <textarea id="flow_ai_personality_traits" name="flow_ai_personality_traits" rows="3" class="large-text"><?php
                echo esc_textarea($flosc_flow_settings['ai_personality_traits'] ?? ($flosc_flow_settings['ai_traits'] ?? ''));
            ?></textarea>
            <p class="description">Tone and approach. e.g. "Encouraging, patient, specific, action-oriented."</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_mission">Mission Statement</label></th>
        <td>
            <textarea id="flow_ai_mission" name="flow_ai_mission" rows="3" class="large-text"><?php
                echo esc_textarea($flosc_flow_settings['ai_mission'] ?? '');
            ?></textarea>
            <p class="description">Core purpose in your own words. Injected into the orientation brief.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_boundaries">Boundaries & Limitations</label></th>
        <td>
            <textarea id="flow_ai_boundaries" name="flow_ai_boundaries" rows="3" class="large-text"><?php
                echo esc_textarea($flosc_flow_settings['ai_boundaries'] ?? '');
            ?></textarea>
            <p class="description">What the AI should refuse to do. e.g. "Never diagnose speech impediments. Never guarantee specific results."</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_topic_scope">Topic Scope</label></th>
        <td>
            <textarea id="flow_ai_topic_scope" name="flow_ai_topic_scope" rows="2" class="large-text"><?php
                echo esc_textarea($flosc_flow_settings['ai_topic_scope'] ?? '');
            ?></textarea>
            <p class="description">What topics the AI covers. e.g. "Stay within English pronunciation and phonetics."</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_off_topic_message">Off-Topic Response</label></th>
        <td>
            <textarea id="flow_ai_off_topic_message" name="flow_ai_off_topic_message" rows="2" class="large-text"><?php
                echo esc_textarea($flosc_flow_settings['ai_off_topic_message'] ?? '');
            ?></textarea>
            <p class="description">How the AI redirects off-topic questions.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_fallback_phrase">Fallback Phrase</label></th>
        <td>
            <textarea id="flow_fallback_phrase" name="flow_fallback_phrase" rows="2" class="large-text"><?php
                echo esc_textarea($flosc_flow_settings['fallback_phrase'] ?? '');
            ?></textarea>
            <p class="description">Shown &mdash; repeated &mdash; when this flow has no IVR configured in the database. No AI, no persona; its appearance tells you the flow isn't set up yet. Leave blank to use the built-in default.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_off_topic_links">Referral Links</label></th>
        <td>
            <textarea id="flow_ai_off_topic_links" name="flow_ai_off_topic_links" rows="3" class="large-text"><?php
                echo esc_textarea($flosc_flow_settings['ai_off_topic_links'] ?? '');
            ?></textarea>
            <p class="description">External resources to recommend when users need help outside your scope. One per line. Use affiliate links where applicable.</p>
        </td>
    </tr>
</table>

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
<hr class="flosc-section-divider" id="flosc-site-index-section">
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Site content index', 'flosc' ); ?></h3>
<p class="description">
	<?php echo esc_html__( 'Indexes published posts across the site into a reference library. Chat pulls only matching posts when useful — never the whole library on every message. Set this flow’s content category on the Content tab for freeline, guest, and member product scope.', 'flosc' ); ?>
</p>

<?php if ( $flosc_sci_msg !== '' ) : ?>
	<div class="notice <?php echo esc_attr( $flosc_sci_action === 'error' ? 'notice-error' : 'notice-success' ); ?> inline flosc-margin-bottom-15"><p><?php echo esc_html( $flosc_sci_msg ); ?></p></div>
<?php endif; ?>

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
				<?php if ( empty( $GLOBALS['flosc_settings_form_closed_early'] ) ) : ?>
					<?php
					echo '</form>';
					$GLOBALS['flosc_settings_form_closed_early'] = true;
					?>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-inline-form">
					<?php wp_nonce_field( 'flosc_site_index_rebuild' ); ?>
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

<!-- ============================================ -->
<!-- SECTION: KNOWLEDGE BASE (Fix 15) -->
<!-- ============================================ -->
<hr class="flosc-section-divider" id="flosc-kb-section">
<h3 class="flosc-ai-section-heading">📚 Knowledge Base</h3>
<p class="description">Upload markdown files containing lesson catalogs, FAQs, product info, and teaching guidelines. These files are injected into the AI knowledge base on every session.</p>

<?php
// KB action notices: prefer one-shot user transient (set by handlers); fall back to query arg
// already present on $flosc_get from settings.php (no re-read of $_GET here).
$flosc_kb_notice = get_transient( 'flosc_kb_notice_' . get_current_user_id() );
if ( is_array( $flosc_kb_notice ) ) {
	delete_transient( 'flosc_kb_notice_' . get_current_user_id() );
	$flosc_kb_action = sanitize_key( (string) ( $flosc_kb_notice['action'] ?? '' ) );
	$flosc_kb_error  = sanitize_text_field( (string) ( $flosc_kb_notice['error'] ?? '' ) );
} else {
	// $flosc_get is prepared by admin/settings.php before this file is included.
	$flosc_kb_action = isset( $flosc_get['kb_action'] ) ? sanitize_key( (string) $flosc_get['kb_action'] ) : '';
	$flosc_kb_error  = isset( $flosc_get['kb_error'] ) ? sanitize_text_field( urldecode( (string) $flosc_get['kb_error'] ) ) : '';
}
$flosc_kb_msg = '';
if ( $flosc_kb_action === 'uploaded' ) {
	$flosc_kb_msg = 'File uploaded successfully.';
} elseif ( $flosc_kb_action === 'deleted' ) {
	$flosc_kb_msg = 'File deleted.';
} elseif ( $flosc_kb_action === 'toggled' ) {
	$flosc_kb_msg = 'Access level updated.';
} elseif ( $flosc_kb_action === 'saved' ) {
	$flosc_kb_msg = 'File saved.';
} elseif ( $flosc_kb_action === 'error' ) {
	$flosc_kb_msg = $flosc_kb_error !== '' ? $flosc_kb_error : 'An error occurred.';
}
if ( $flosc_kb_msg !== '' ) :
	$flosc_kb_notice_class = ( $flosc_kb_action === 'error' ) ? 'notice-error' : 'notice-success';
	?>
<div class="notice <?php echo esc_attr( $flosc_kb_notice_class ); ?> inline flosc-margin-bottom-15"><p><?php echo esc_html( $flosc_kb_msg ); ?></p></div>
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

<?php
// Per-flow basket: list ONLY this flow's own uploaded files. Each flow's folder is
// physically separate, so another flow's files can never appear here (no bleed).
$flosc_flow_stem = sanitize_key(pathinfo($GLOBALS['flosc_current_ivr'] ?? '', PATHINFO_FILENAME));
$flosc_kb_dir    = function_exists('flosc_flow_kb_dir') ? flosc_flow_kb_dir($flosc_flow_stem) : '';
$flosc_kb_files  = [];
if ($flosc_kb_dir && is_dir($flosc_kb_dir)) {
    foreach (glob($flosc_kb_dir . '*.{md,txt}', GLOB_BRACE) ?: [] as $flosc_fp) {
        $flosc_kb_files[] = basename($flosc_fp);
    }
}
sort($flosc_kb_files);

$flosc_editing_kb_file = isset($flosc_get['kb_edit']) ? sanitize_file_name($flosc_get['kb_edit']) : '';
$flosc_editing_kb_content = '';
$flosc_editing_kb_path = ($flosc_editing_kb_file && $flosc_kb_dir) ? $flosc_kb_dir . $flosc_editing_kb_file : '';
if ($flosc_editing_kb_path && file_exists($flosc_editing_kb_path)) {
    $flosc_editing_kb_content = flosc_fs_get_contents($flosc_editing_kb_path);
}
?>

<?php
// HTML forbids nested forms. Close the main settings form before KB upload/edit forms.
// Save Settings uses form="flosc-settings-form" so it still posts provider/key fields above.
if (empty($GLOBALS['flosc_settings_form_closed_early'])) {
    echo '</form>';
    $GLOBALS['flosc_settings_form_closed_early'] = true;
}
?>

<!-- Upload form (sibling of main settings form — not nested) -->
<div class="card flosc-card-max-700">
    <h4 class="flosc-card-title-reset">Upload Knowledge File</h4>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('flosc_kb_upload', 'flosc_kb_upload_nonce'); ?>
        <input type="hidden" name="action" value="flosc_kb_upload">
        <input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr($GLOBALS['flosc_current_ivr'] ?? ''); ?>">
        <table class="form-table flosc-form-table-reset">
            <tr>
            <th scope="row" class="flosc-th-pad-top-8"><label for="kb_file_upload">File</label></th>
                <td>
                    <input type="file" name="orientation_file" id="kb_file_upload" accept=".md,.txt" required>
                    <p class="description">.md or .txt files only.</p>
                </td>
            </tr>
            <tr>
                <th scope="row" class="flosc-th-pad-top-8"><label for="kb_access_level">Access Level</label></th>
                <td>
                    <select name="file_access_level" id="kb_access_level">
                        <option value="visitor">Visitor — everyone, including pre-login</option>
                        <option value="guest">Guest — logged-in users</option>
                        <option value="member">Member — full access (through to Content)</option>
                    </select>
                    <p class="description">Tiers are cumulative: a Guest file is also seen by Members; a Visitor file is seen by all.</p>
                </td>
            </tr>
        </table>
        <div class="flosc-margin-top-10">
            <button type="submit" class="button button-secondary">Upload File</button>
        </div>
    </form>
</div>

<!-- File list -->
<?php if (!empty($flosc_kb_files)): ?>
<table class="widefat flosc-table-full">
    <thead>
        <tr>
            <th class="flosc-width-35">Filename</th>
            <th class="flosc-width-15">Access</th>
            <th class="flosc-width-10">Size</th>
            <th class="flosc-width-15">Modified</th>
            <th class="flosc-width-25">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($flosc_kb_files as $flosc_kbf):
        $flosc_fp = $flosc_kb_dir . $flosc_kbf;
        $flosc_kbf_access = $flosc_flow_settings['knowledge_access_' . md5($flosc_kbf)] ?? 'visitor';
        if ($flosc_kbf_access === 'public')  $flosc_kbf_access = 'visitor';
        if ($flosc_kbf_access === 'members') $flosc_kbf_access = 'member';
        $flosc_kbf_badge = [
            'visitor' => ['Visitor', 'flosc-inline-badge flosc-inline-badge--visitor'],
            'guest'   => ['Guest', 'flosc-inline-badge flosc-inline-badge--guest'],
            'member'  => ['Member', 'flosc-inline-badge flosc-inline-badge--member'],
        ][$flosc_kbf_access] ?? ['Visitor', 'flosc-inline-badge flosc-inline-badge--visitor'];
        $flosc_toggle_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_kb_toggle&kb_file=' . rawurlencode($flosc_kbf) . '&return_ivr=' . rawurlencode($GLOBALS['flosc_current_ivr'] ?? '')), 'flosc_kb_toggle_' . $flosc_kbf);
        $flosc_delete_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_kb_delete&kb_file=' . rawurlencode($flosc_kbf) . '&return_ivr=' . rawurlencode($GLOBALS['flosc_current_ivr'] ?? '')), 'flosc_kb_delete_' . $flosc_kbf);
        $flosc_edit_url   = admin_url('admin.php?page=flosc-settings&ivr=' . rawurlencode($GLOBALS['flosc_current_ivr'] ?? '') . '&tab=ai&kb_edit=' . rawurlencode($flosc_kbf) . '#flosc-kb-section');
    ?>
        <tr>
            <td>
                <strong><?php echo esc_html($flosc_kbf); ?></strong>
                <?php if ($flosc_editing_kb_file === $flosc_kbf): ?><span class="flosc-text-blue flosc-margin-left-6">← editing</span><?php endif; ?>
            </td>
            <td>
                <span class="<?php echo esc_attr($flosc_kbf_badge[1]); ?>"><?php echo esc_html($flosc_kbf_badge[0]); ?></span>
            </td>
            <td><?php echo file_exists($flosc_fp) ? esc_html(size_format(filesize($flosc_fp))) : '—'; ?></td>
            <td><?php echo file_exists($flosc_fp) ? esc_html(human_time_diff(filemtime($flosc_fp), current_time('timestamp')) . ' ago') : '—'; ?></td>
            <td>
                <a href="<?php echo esc_url($flosc_edit_url); ?>" class="button button-small"><?php echo $flosc_editing_kb_file === $flosc_kbf ? 'Editing...' : 'Edit'; ?></a>
                <a href="<?php echo esc_url($flosc_toggle_url); ?>" class="button button-small">Toggle Access</a>
                <a href="<?php echo esc_url($flosc_delete_url); ?>" class="button button-small flosc-ai-kb-delete" data-confirm-message="Delete <?php echo esc_attr($flosc_kbf); ?>? Cannot be undone.">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="flosc-text-muted-italic">No knowledge files yet. Upload your first file above.</p>
<?php endif; ?>

<!-- File editor (if editing) -->
<?php if ($flosc_editing_kb_file): ?>
<div class="card flosc-card-max-full">
    <h4 class="flosc-card-title-reset">Editing: <?php echo esc_html($flosc_editing_kb_file); ?></h4>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('flosc_kb_save_edit', 'flosc_kb_save_edit_nonce'); ?>
        <input type="hidden" name="action" value="flosc_kb_save_edit">
        <input type="hidden" name="editing_file" value="<?php echo esc_attr($flosc_editing_kb_file); ?>">
        <input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr($GLOBALS['flosc_current_ivr'] ?? ''); ?>">
        <textarea name="file_content" rows="30" class="large-text code flosc-code-textarea"><?php echo esc_textarea($flosc_editing_kb_content); ?></textarea>
        <div class="flosc-margin-top-10">
            <button type="submit" class="button button-primary">Save File</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . rawurlencode($GLOBALS['flosc_current_ivr'] ?? '') . '&tab=ai#flosc-kb-section')); ?>" class="button flosc-margin-left-8">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SECTION: PROVIDER ACCURACY TEST -->
<!-- ============================================ -->
<?php
$flosc_acc_flow_name = (string) ( $flosc_flow_settings['identity']['name'] ?? $flosc_flow_settings['name'] ?? '' );
if ( $flosc_acc_flow_name === '' && function_exists( 'flosc_personality_library_resolve_field' ) ) {
	$flosc_acc_flow_name = (string) flosc_personality_library_resolve_field( 'ai_personality_name', '' );
}
if ( $flosc_acc_flow_name === '' ) {
	$flosc_acc_flow_name = (string) ( $GLOBALS['flosc_current_ivr'] ?? 'this floscFlow' );
	$flosc_acc_flow_name = pathinfo( $flosc_acc_flow_name, PATHINFO_FILENAME );
	$flosc_acc_flow_name = $flosc_acc_flow_name !== '' ? $flosc_acc_flow_name : 'this floscFlow';
}
$flosc_acc_tagline = (string) ( $flosc_flow_settings['identity']['tagline'] ?? $flosc_flow_settings['tagline'] ?? '' );
if ( $flosc_acc_tagline === '' ) {
	$flosc_acc_tagline = __( '(no tagline set)', 'flosc' );
}
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
	'Hello — what is the name of this floscFlow, and who are you in this chat?',
	'What is the product or flow name you represent here? (Expected name: {flow_name}.)',
	'What does the Product Tagline for this floscFlow mean or convey? (Configured tagline: {tagline}.)',
	'In your own words, what is {flow_name} for, and who is it meant to help?',
	'What topics or tasks are you authorized to handle on {flow_name}? (Topic scope note: {topic_scope}.)',
	'How does {flow_name} relate to {site_name}?',
	'What should a first-time visitor do next on {flow_name}?',
	'Stay in character for {flow_name}: state your role in one or two sentences.',
	'If someone asks for details you do not have about {flow_name}, what do you do instead of inventing them?',
	'Summarize {flow_name}: name, purpose, and how you help — based on this conversation.',
);
$flosc_acc_var_map = array(
	'{flow_name}'   => $flosc_acc_flow_name,
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

// Saved suite: newline-joined (legacy) or re-split; repair if no newlines but looks like many sentences.
$flosc_acc_saved_raw = (string) ( $flosc_flow_settings['ai_accuracy_test_questions'] ?? '' );
$flosc_acc_saved_lines = array();
if ( trim( $flosc_acc_saved_raw ) !== '' ) {
	if ( strpos( $flosc_acc_saved_raw, "\n" ) === false && strpos( $flosc_acc_saved_raw, "\r" ) === false ) {
		// Likely mangled one-line save — fall back to expanded defaults.
		$flosc_acc_saved_lines = $flosc_acc_defaults_filled;
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
$flosc_acc_edit_lines = $flosc_acc_saved_lines !== array() ? $flosc_acc_saved_lines : $flosc_acc_defaults_filled;
// Pad/truncate to template count for stable row UI (extras kept if admin added more).
$flosc_acc_row_count = max( count( $flosc_acc_templates ), count( $flosc_acc_edit_lines ) );
while ( count( $flosc_acc_edit_lines ) < $flosc_acc_row_count ) {
	$flosc_acc_edit_lines[] = '';
}
$flosc_acc_line_count = max( 1, count( array_filter( $flosc_acc_edit_lines ) ) );
?>
<hr class="flosc-section-divider" id="flosc-accuracy-test">
<h3 class="flosc-ai-section-heading"><?php echo esc_html__( 'Provider accuracy test', 'flosc' ); ?></h3>
<p class="description">
	<?php echo esc_html__( 'Multi-turn chat against this floscFlow’s AI. Each row shows the content-agnostic default (with {variables}) and an editable sentence for this run. Variables expand from Identity, topic scope, and site name.', 'flosc' ); ?>
</p>

<div class="flosc-acc-var-chips" aria-label="<?php echo esc_attr__( 'Variables for this flow', 'flosc' ); ?>">
	<span class="flosc-acc-var-chip"><code>{flow_name}</code> = <?php echo esc_html( $flosc_acc_flow_name ); ?></span>
	<span class="flosc-acc-var-chip"><code>{tagline}</code> = <?php echo esc_html( $flosc_acc_tagline ); ?></span>
	<span class="flosc-acc-var-chip"><code>{topic_scope}</code> = <?php echo esc_html( $flosc_acc_scope ); ?></span>
	<span class="flosc-acc-var-chip"><code>{site_name}</code> = <?php echo esc_html( $flosc_acc_site ); ?></span>
</div>

<div id="flosc-accuracy-rows" class="flosc-acc-rows"
	data-flosc-acc-map="<?php echo esc_attr( wp_json_encode( $flosc_acc_var_map ) ); ?>">
	<?php
	for ( $flosc_acc_i = 0; $flosc_acc_i < $flosc_acc_row_count; $flosc_acc_i++ ) :
		$flosc_acc_tpl = $flosc_acc_templates[ $flosc_acc_i ] ?? '';
		$flosc_acc_def = isset( $flosc_acc_defaults_filled[ $flosc_acc_i ] )
			? $flosc_acc_defaults_filled[ $flosc_acc_i ]
			: $flosc_acc_expand( $flosc_acc_tpl, $flosc_acc_var_map );
		$flosc_acc_val = $flosc_acc_edit_lines[ $flosc_acc_i ] ?? $flosc_acc_def;
		?>
	<div class="flosc-acc-row" data-acc-index="<?php echo esc_attr( (string) $flosc_acc_i ); ?>">
		<div class="flosc-acc-row__num" aria-hidden="true"><?php echo esc_html( (string) ( $flosc_acc_i + 1 ) ); ?></div>
		<div class="flosc-acc-row__body">
			<label class="screen-reader-text" for="flosc_acc_q_<?php echo esc_attr( (string) $flosc_acc_i ); ?>">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: question number */
						__( 'Test question %d', 'flosc' ),
						$flosc_acc_i + 1
					)
				);
				?>
			</label>
			<textarea
				id="flosc_acc_q_<?php echo esc_attr( (string) $flosc_acc_i ); ?>"
				class="large-text flosc-acc-row__input"
				rows="2"
				data-acc-template="<?php echo esc_attr( $flosc_acc_tpl ); ?>"
				data-acc-default-filled="<?php echo esc_attr( $flosc_acc_def ); ?>"
			><?php echo esc_textarea( $flosc_acc_val ); ?></textarea>
			<p class="description flosc-acc-row__default">
				<strong><?php echo esc_html__( 'Default:', 'flosc' ); ?></strong>
				<code class="flosc-acc-row__template"><?php echo esc_html( $flosc_acc_tpl !== '' ? $flosc_acc_tpl : '—' ); ?></code>
			</p>
			<p class="flosc-acc-row__actions">
				<button type="button" class="button button-small flosc-acc-reset-row"><?php echo esc_html__( 'Reset row', 'flosc' ); ?></button>
			</p>
		</div>
	</div>
	<?php endfor; ?>
</div>

<?php // Hidden field keeps Save Settings compatibility (newline-joined suite). ?>
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
		<button type="button" class="button button-secondary" id="flosc-accuracy-reset-defaults"><?php echo esc_html__( 'Reset all to defaults', 'flosc' ); ?></button>
		<button type="button" id="flosc-run-accuracy-test" class="button button-primary"><?php echo esc_html__( '▶ Run multi-turn provider test', 'flosc' ); ?></button>
		<span class="description"><?php echo esc_html__( 'Save Settings on this page to keep edits. Run expands any remaining {placeholders} from the chips above.', 'flosc' ); ?></span>
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
					<th class="flosc-width-35"><?php echo esc_html__( 'Message', 'flosc' ); ?></th>
					<th class="flosc-width-45"><?php echo esc_html__( 'Response', 'flosc' ); ?></th>
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
	function floscSyncAccuracyHidden() {
		var lines = [];
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var t = $.trim($(this).val() || '');
			if (t) { lines.push(t); }
		});
		$('#flow_ai_accuracy_test_questions').val(lines.join('\n'));
		$('#flosc-test-msg-total').text(Math.max(1, lines.length));
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
		var filled = $inp.attr('data-acc-default-filled') || floscAccExpand(tpl, floscAccVarMap());
		$inp.val(filled);
		floscSyncAccuracyHidden();
	});

	$('#flosc-accuracy-reset-defaults').on('click', function() {
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var filled = $(this).attr('data-acc-default-filled') || '';
			if (!filled) {
				filled = floscAccExpand($(this).attr('data-acc-template') || '', floscAccVarMap());
			}
			$(this).val(filled);
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

</div><!-- .flosc-ai-config -->