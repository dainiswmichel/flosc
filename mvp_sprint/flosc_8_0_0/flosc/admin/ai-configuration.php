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

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$base_prompt = $flow_settings['ai_base_prompt'] ?? '';
$ai_provider = $flow_settings['ai_provider'] ?? 'ivr';
$ai_openai_model = $flow_settings['ai_openai_model'] ?? 'gpt-4o-mini';
$ai_anthropic_model = $flow_settings['ai_anthropic_model'] ?? 'claude-sonnet-4-5-20250929';
$ai_xai_model = $flow_settings['ai_xai_model'] ?? 'grok-2-latest';
$ai_temperature = $flow_settings['ai_temperature'] ?? '0.3';
$ai_max_tokens = $flow_settings['ai_max_tokens'] ?? '500';
$enable_ivr_context = $flow_settings['ai_enable_ivr_context'] ?? true;
$enable_content_access = $flow_settings['ai_enable_content_access'] ?? true;
$ai_response_mode = $flow_settings['ai_response_mode'] ?? 'enhanced';
$enable_chaining = $flow_settings['ai_enable_chaining'] ?? false;
$chain_provider_1 = $flow_settings['ai_chain_provider_1'] ?? '';
$chain_provider_2 = $flow_settings['ai_chain_provider_2'] ?? '';
$chain_provider_3 = $flow_settings['ai_chain_provider_3'] ?? '';
<?php
// Fix 9: Risk condition notices — visible warnings for conditions that cause hallucinations
$identity      = function_exists('flosc') ? FLOSC_Framework::instance()->get_floscflow_identity() : [];
$product_name  = $identity['name'] ?? $flow_settings['name'] ?? '';
$product_tag   = $identity['tagline'] ?? $flow_settings['tagline'] ?? '';
$catalog_file  = defined('FLOSC_PLUGIN_DIR') ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/lesaep_lesson_catalog.md' : '';
$catalog_age   = ($catalog_file && file_exists($catalog_file)) ? (time() - filemtime($catalog_file)) : PHP_INT_MAX;
$notices = [];
if ((float) $ai_temperature > 0.5) {
    $notices[] = '<strong>Temperature ' . esc_html($ai_temperature) . ' increases fabrication risk.</strong> Recommended: 0.3';
}
if (empty($product_name)) {
    $notices[] = '<strong>Product name not configured.</strong> AI has no identity and will hallucinate.';
}
if (!empty($product_name) && empty($product_tag)) {
    $notices[] = '<strong>Product tagline not configured.</strong> AI cannot verify its own product acronym and will guess.';
}
if ($catalog_age > 7 * DAY_IN_SECONDS) {
    $regen_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_regenerate_lesson_catalog'), 'flosc_regen_catalog');
    $age_msg = ($catalog_age === PHP_INT_MAX) ? 'Lesson catalog has never been generated.' : 'Lesson catalog is more than 7 days old.';
    $notices[] = $age_msg . ' <a href="' . esc_url($regen_url) . '" class="button button-small">Regenerate Now</a>';
}
?>
<?php if (!empty($notices)): ?>
<div style="margin-bottom: 20px;">
    <?php foreach ($notices as $notice): ?>
    <div class="notice notice-warning inline" style="margin: 0 0 8px; padding: 10px 14px;">
        <p style="margin: 0;"><?php echo wp_kses($notice, ['strong' => [], 'a' => ['href' => [], 'class' => []]]); ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Styles in assets/css/flosc-admin.css (AI Configuration section) -->

<div class="flosc-ai-config">

<h2>🤖 AI Configuration</h2>
<p class="description flosc-ai-intro">
    Connect your FLOSC flow to AI providers (OpenAI, Anthropic, xAI) or use IVR-only mode (scripted responses, no API costs).
</p>

<!-- Quick Start Guide -->
<div class="flosc-ai-quickstart">
    <h3>📋 Quick Start Guide</h3>
    <ol>
        <li><strong>Choose your AI provider</strong> below (or keep "IVR" for scripted mode)</li>
        <li><strong>Get your API key</strong> by clicking the provider's link</li>
        <li><strong>Paste your API key</strong> into the password field</li>
        <li><strong>Click "Test Connection"</strong> to verify it works</li>
        <li><strong>Customize your AI's personality</strong> in the Base System Prompt (optional)</li>
        <li><strong>Save Settings</strong> at the bottom of this page</li>
    </ol>
    <p class="flosc-ai-tip">
        💡 <strong>Tip:</strong> Use the Provider Accuracy Test (below) to determine which provider gives the most reliable responses for your installation.
    </p>
</div>

<div class="flosc-banner flosc-banner--info">
    <strong>💡 How It Works:</strong> When you select an AI provider and add your API key, FLOSC automatically provides IVR context and enforces content boundaries — all guided by your IVR configuration. The settings below let you fine-tune this behavior.
</div>

<!-- ============================================ -->
<!-- SECTION 1: PROVIDER SELECTION -->
<!-- ============================================ -->
<h3 class="flosc-ai-section-heading">
    🔌 Step 1: Choose Your AI Provider
</h3>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_provider">Primary AI Provider</label></th>
        <td>
            <select name="flow_ai_provider" id="flow_ai_provider" class="flosc-ai-provider-select">
                <option value="ivr" <?php selected($ai_provider, 'ivr'); ?>>IVR Only (Scripted Responses - Zero API Cost)</option>
                <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic Claude (Recommended for FLOSC)</option>
                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (Fast & Affordable)</option>
                <option value="xai" <?php selected($ai_provider, 'xai'); ?>>xAI Grok</option>
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
            <input type="password" id="flow_anthropic_api_key" name="flow_anthropic_api_key" value="<?php echo esc_attr($flow_settings['anthropic_api_key'] ?? ''); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-ant-api03-...">
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
                <option value="claude-sonnet-4-5-20250929" <?php selected($ai_anthropic_model, 'claude-sonnet-4-5-20250929'); ?>>Claude Sonnet 4.5 (Recommended)</option>
                <option value="claude-haiku-4-5-20251001" <?php selected($ai_anthropic_model, 'claude-haiku-4-5-20251001'); ?>>Claude Haiku 4.5 (Fastest, cheapest)</option>
                <option value="claude-opus-4-6" <?php selected($ai_anthropic_model, 'claude-opus-4-6'); ?>>Claude Opus 4.6 (Most capable)</option>
                <option value="claude-3-5-sonnet-20241022" <?php selected($ai_anthropic_model, 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet (Legacy)</option>
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
            <input type="password" id="flow_openai_api_key" name="flow_openai_api_key" value="<?php echo esc_attr($flow_settings['openai_api_key'] ?? ''); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-proj-...">
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
                <option value="gpt-4o-mini" <?php selected($ai_openai_model, 'gpt-4o-mini'); ?>>GPT-4o-mini (Fast & affordable)</option>
                <option value="gpt-4o" <?php selected($ai_openai_model, 'gpt-4o'); ?>>GPT-4o (More capable)</option>
                <option value="gpt-4.1" <?php selected($ai_openai_model, 'gpt-4.1'); ?>>GPT-4.1 (Latest)</option>
                <option value="gpt-4.1-mini" <?php selected($ai_openai_model, 'gpt-4.1-mini'); ?>>GPT-4.1 Mini (Latest affordable)</option>
                <option value="gpt-4.1-nano" <?php selected($ai_openai_model, 'gpt-4.1-nano'); ?>>GPT-4.1 Nano (Cheapest)</option>
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
            <input type="password" id="flow_xai_api_key" name="flow_xai_api_key" value="<?php echo esc_attr($flow_settings['xai_api_key'] ?? ''); ?>" class="regular-text flosc-ai-key-input" placeholder="xai-...">
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
                <option value="grok-2-latest" <?php selected($ai_xai_model, 'grok-2-latest'); ?>>Grok 2 (Recommended)</option>
                <option value="grok-beta" <?php selected($ai_xai_model, 'grok-beta'); ?>>Grok Beta (Legacy)</option>
            </select>
            <p class="description">Choose which Grok model to use for this flow.</p>
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
            <input type="number" id="flow_ai_temperature" name="flow_ai_temperature" value="<?php echo esc_attr($ai_temperature); ?>" min="0" max="2" step="0.1" class="flosc-ai-temp-input">
            <p class="description">
                Controls randomness. <strong>0.0</strong> = deterministic, <strong>0.7</strong> = balanced (default), <strong>1.5+</strong> = creative. Lower for sales scripts, higher for coaching.
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_max_tokens">Max Tokens</label></th>
        <td>
            <input type="number" id="flow_ai_max_tokens" name="flow_ai_max_tokens" value="<?php echo esc_attr($ai_max_tokens); ?>" min="50" max="4096" step="50" class="flosc-ai-tokens-input">
            <p class="description">
                Maximum response length. <strong>500</strong> = concise chat (default), <strong>1000</strong> = detailed explanations, <strong>2000+</strong> = long-form. Higher values cost more per response.
            </p>
        </td>
    </tr>
</table>

<!-- Provider Chaining -->
<h3 class="flosc-ai-section-heading">
    🔗 Step 2c: Provider Chaining (Optional)
</h3>
<p class="description">
    Send each user message through multiple AI providers sequentially. Provider 1 responds first, then Provider 2 sees that response as context and refines it. Useful for cross-checking or combining strengths of different models.
</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flow_ai_enable_chaining">
                <input type="checkbox" name="flow_ai_enable_chaining" id="flow_ai_enable_chaining" value="1" <?php checked($enable_chaining, true); ?>>
                Enable Provider Chaining
            </label>
        </th>
        <td>
            <p class="description">
                When enabled, responses pass through multiple providers in order. Each provider sees the previous provider's response. Requires API keys configured for each chained provider.
            </p>
        </td>
    </tr>
</table>

<div id="flosc-chain-config" class="flosc-ai-chain-config" style="<?php echo $enable_chaining ? '' : 'display:none;'; ?>">
    <table class="form-table">
        <tr>
            <th scope="row"><label for="flow_ai_chain_provider_1">Provider 1 (Drafts)</label></th>
            <td>
                <select name="flow_ai_chain_provider_1" id="flow_ai_chain_provider_1" class="flosc-ai-model-select">
                    <option value="none">— Select —</option>
                    <option value="openai" <?php selected($chain_provider_1, 'openai'); ?>>OpenAI</option>
                    <option value="anthropic" <?php selected($chain_provider_1, 'anthropic'); ?>>Anthropic Claude</option>
                    <option value="xai" <?php selected($chain_provider_1, 'xai'); ?>>xAI Grok</option>
                </select>
                <span class="description">Generates the initial response.</span>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_ai_chain_provider_2">Provider 2 (Refines)</label></th>
            <td>
                <select name="flow_ai_chain_provider_2" id="flow_ai_chain_provider_2" class="flosc-ai-model-select">
                    <option value="none">— Select —</option>
                    <option value="openai" <?php selected($chain_provider_2, 'openai'); ?>>OpenAI</option>
                    <option value="anthropic" <?php selected($chain_provider_2, 'anthropic'); ?>>Anthropic Claude</option>
                    <option value="xai" <?php selected($chain_provider_2, 'xai'); ?>>xAI Grok</option>
                </select>
                <span class="description">Reviews and refines Provider 1's response.</span>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_ai_chain_provider_3">Provider 3 (Optional)</label></th>
            <td>
                <select name="flow_ai_chain_provider_3" id="flow_ai_chain_provider_3" class="flosc-ai-model-select">
                    <option value="none">— None —</option>
                    <option value="openai" <?php selected($chain_provider_3, 'openai'); ?>>OpenAI</option>
                    <option value="anthropic" <?php selected($chain_provider_3, 'anthropic'); ?>>Anthropic Claude</option>
                    <option value="xai" <?php selected($chain_provider_3, 'xai'); ?>>xAI Grok</option>
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
    <div id="test-results" class="flosc-ai-test-results" style="display: none;">
        <div id="test-status"></div>
        <div id="test-details" class="flosc-ai-test-details"></div>
    </div>
    <div id="test-loading" class="flosc-ai-test-loading" style="display: none;">
        <span class="spinner is-active"></span>
        <span>Testing connection to AI provider...</span>
    </div>
</div>

<!-- Base System Prompt -->
<h3 class="flosc-ai-section-heading">
    🎨 Step 4: Customize AI Personality (Optional)
</h3>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_base_prompt">Base System Prompt</label></th>
        <td>
            <textarea id="flow_ai_base_prompt" name="flow_ai_base_prompt" rows="6" class="large-text flosc-ai-prompt-textarea" placeholder="Define your AI's personality and behavior here. Example: You are a friendly coach who helps users improve their skills..."><?php echo esc_textarea($base_prompt); ?></textarea>
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
                <option value="strict" <?php selected($ai_response_mode, 'strict'); ?>>Strict IVR (AI only rephrases configured messages)</option>
                <option value="enhanced" <?php selected($ai_response_mode, 'enhanced'); ?>>Enhanced (AI can expand on messages with context)</option>
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
                <input type="checkbox" name="flow_ai_enable_ivr_context" id="flow_ai_enable_ivr_context" value="1" <?php checked($enable_ivr_context, true); ?>>
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
                <input type="checkbox" name="flow_ai_enable_content_access" id="flow_ai_enable_content_access" value="1" <?php checked($enable_content_access, true); ?>>
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
            <textarea name="flow_ai_prompt_freeline" id="flow_ai_prompt_freeline" rows="3" class="large-text" placeholder="e.g. Encourage user to take the quiz. Be curious about their goals."><?php echo esc_textarea($flow_settings['ai_prompt_freeline'] ?? ''); ?></textarea>
            <p class="description">Visitors who haven't taken the quiz yet.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_login">Login Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_login" id="flow_ai_prompt_login" rows="3" class="large-text" placeholder="e.g. Deliver free lesson based on quiz results. Build trust before presenting offer."><?php echo esc_textarea($flow_settings['ai_prompt_login'] ?? ''); ?></textarea>
            <p class="description">Post-quiz visitors and logged-in users.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_offer">Offer Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_offer" id="flow_ai_prompt_offer" rows="3" class="large-text" placeholder="e.g. Present personalized offer. Address objections. Show value specific to their quiz results."><?php echo esc_textarea($flow_settings['ai_prompt_offer'] ?? ''); ?></textarea>
            <p class="description">Sales pitch mode.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_sale">Sale Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_sale" id="flow_ai_prompt_sale" rows="3" class="large-text" placeholder="e.g. Onboard user to content. Explain navigation. Build excitement for their purchase."><?php echo esc_textarea($flow_settings['ai_prompt_sale'] ?? ''); ?></textarea>
            <p class="description">Post-purchase onboarding.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_content">Content Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_content" id="flow_ai_prompt_content" rows="3" class="large-text" placeholder="e.g. Support learning journey. Answer questions. Encourage progress and celebrate wins."><?php echo esc_textarea($flow_settings['ai_prompt_content'] ?? ''); ?></textarea>
            <p class="description">Ongoing support for paying customers.</p>
        </td>
    </tr>
</table>

<script>
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
        var $status = $('#test-status');
        var $details = $('#test-details');

        $btn.prop('disabled', true);
        $loading.show();
        $results.hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flosc_test_ai_connection',
                nonce: '<?php echo wp_create_nonce('flosc_test_ai'); ?>',
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>'
            },
            success: function(response) {
                $loading.hide();
                $results.show();
                
                if (response.success) {
                    $status.html('<span style="color: #46b450; font-weight: bold;">✓ Connection Successful!</span>');
                    $details.text('Provider: ' + response.data.provider + '\nResponse: ' + response.data.response);
                } else {
                    $status.html('<span style="color: #dc3232; font-weight: bold;">✗ Connection Failed</span>');
                    $details.text(response.data.message || 'Unknown error');
                }
            },
            error: function() {
                $loading.hide();
                $results.show();
                $status.html('<span style="color: #dc3232; font-weight: bold;">✗ Request Failed</span>');
                $details.text('Could not reach the server. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>

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
                <option value="assemblyai" <?php selected($flow_settings['stt_provider'] ?? '', 'assemblyai'); ?>>AssemblyAI (High Accuracy)</option>
                <option value="openai" <?php selected($flow_settings['stt_provider'] ?? '', 'openai'); ?>>OpenAI Whisper (Multilingual)</option>
                <option value="deepgram" <?php selected($flow_settings['stt_provider'] ?? '', 'deepgram'); ?>>Deepgram (Fast & Real-time)</option>
                <option value="custom" <?php selected($flow_settings['stt_provider'] ?? '', 'custom'); ?>>Custom Endpoint (Self-hosted)</option>
            </select>
            <p class="description">Choose the service that will transcribe audio recordings from quiz takers.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_assemblyai_api_key"><strong>AssemblyAI API Key</strong></label>
        </th>
        <td>
            <input type="password" id="flow_assemblyai_api_key" name="flow_assemblyai_api_key" value="<?php echo esc_attr($flow_settings['assemblyai_api_key'] ?? ''); ?>" class="regular-text flosc-ai-stt-input" placeholder="Your AssemblyAI key">
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
            <label for="flow_deepgram_api_key"><strong>Deepgram API Key</strong></label>
        </th>
        <td>
            <input type="password" id="flow_deepgram_api_key" name="flow_deepgram_api_key" value="<?php echo esc_attr($flow_settings['deepgram_api_key'] ?? ''); ?>" class="regular-text flosc-ai-stt-input" placeholder="Your Deepgram key">
            <p class="description">
                <a href="https://console.deepgram.com/signup" target="_blank" class="button button-secondary flosc-ai-key-link">
                    📥 Get Your Deepgram API Key Here
                </a><br>
                <span class="flosc-ai-key-desc">Ultra-fast real-time transcription with excellent accuracy.</span>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_custom_stt_endpoint"><strong>Custom STT Endpoint URL</strong></label>
        </th>
        <td>
            <input type="url" id="flow_custom_stt_endpoint" name="flow_custom_stt_endpoint" value="<?php echo esc_attr($flow_settings['custom_stt_endpoint'] ?? ''); ?>" class="regular-text flosc-ai-stt-input" placeholder="https://your-stt-endpoint.com/transcribe">
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
<hr style="margin: 40px 0;" id="flosc-personality-section">
<h3 class="flosc-ai-section-heading">🧠 AI Personality & Identity</h3>
<p class="description">Define who your AI is and how it interacts with users. These fields are injected into every AI system prompt.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_personality_name">AI Name</label></th>
        <td>
            <input type="text" id="flow_ai_personality_name" name="flow_ai_personality_name"
                   value="<?php echo esc_attr($flow_settings['ai_personality_name'] ?? ($flow_settings['ai_name'] ?? '')); ?>"
                   class="regular-text" placeholder="e.g. LeSAEp Coach">
            <p class="description">What users call the AI.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_personality_role">AI Role</label></th>
        <td>
            <input type="text" id="flow_ai_personality_role" name="flow_ai_personality_role"
                   value="<?php echo esc_attr($flow_settings['ai_personality_role'] ?? ($flow_settings['ai_role'] ?? '')); ?>"
                   class="large-text" placeholder="e.g. pronunciation coach and learning guide">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_personality_traits">Personality Traits</label></th>
        <td>
            <textarea id="flow_ai_personality_traits" name="flow_ai_personality_traits" rows="3" class="large-text"><?php
                echo esc_textarea($flow_settings['ai_personality_traits'] ?? ($flow_settings['ai_traits'] ?? ''));
            ?></textarea>
            <p class="description">Tone and approach. e.g. "Encouraging, patient, specific, action-oriented."</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_mission">Mission Statement</label></th>
        <td>
            <textarea id="flow_ai_mission" name="flow_ai_mission" rows="3" class="large-text"><?php
                echo esc_textarea($flow_settings['ai_mission'] ?? '');
            ?></textarea>
            <p class="description">Core purpose in your own words. Injected into the orientation brief.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_boundaries">Boundaries & Limitations</label></th>
        <td>
            <textarea id="flow_ai_boundaries" name="flow_ai_boundaries" rows="3" class="large-text"><?php
                echo esc_textarea($flow_settings['ai_boundaries'] ?? '');
            ?></textarea>
            <p class="description">What the AI should refuse to do. e.g. "Never diagnose speech impediments. Never guarantee specific results."</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_topic_scope">Topic Scope</label></th>
        <td>
            <textarea id="flow_ai_topic_scope" name="flow_ai_topic_scope" rows="2" class="large-text"><?php
                echo esc_textarea($flow_settings['ai_topic_scope'] ?? '');
            ?></textarea>
            <p class="description">What topics the AI covers. e.g. "Stay within English pronunciation and phonetics."</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_off_topic_message">Off-Topic Response</label></th>
        <td>
            <textarea id="flow_ai_off_topic_message" name="flow_ai_off_topic_message" rows="2" class="large-text"><?php
                echo esc_textarea($flow_settings['ai_off_topic_message'] ?? '');
            ?></textarea>
            <p class="description">How the AI redirects off-topic questions.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_off_topic_links">Referral Links</label></th>
        <td>
            <textarea id="flow_ai_off_topic_links" name="flow_ai_off_topic_links" rows="3" class="large-text"><?php
                echo esc_textarea($flow_settings['ai_off_topic_links'] ?? '');
            ?></textarea>
            <p class="description">External resources to recommend when users need help outside your scope. One per line. Use affiliate links where applicable.</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION: KNOWLEDGE BASE (Fix 15) -->
<!-- ============================================ -->
<hr style="margin: 40px 0;" id="flosc-kb-section">
<h3 class="flosc-ai-section-heading">📚 Knowledge Base</h3>
<p class="description">Upload markdown files containing lesson catalogs, FAQs, product info, and teaching guidelines. These files are injected into the AI knowledge base on every session.</p>

<?php
// Fix 15: Display any KB action success message
if (isset($_GET['kb_action'])) {
    $kb_msg = '';
    if ($_GET['kb_action'] === 'uploaded')      $kb_msg = 'File uploaded successfully.';
    if ($_GET['kb_action'] === 'deleted')       $kb_msg = 'File deleted.';
    if ($_GET['kb_action'] === 'toggled')       $kb_msg = 'Access level updated.';
    if ($_GET['kb_action'] === 'saved')         $kb_msg = 'File saved.';
    if ($_GET['kb_action'] === 'error')         $kb_msg = isset($_GET['kb_error']) ? urldecode($_GET['kb_error']) : 'An error occurred.';
    if ($kb_msg):
?>
<div class="notice notice-success inline" style="margin: 0 0 15px;"><p><?php echo esc_html($kb_msg); ?></p></div>
<?php endif; } ?>

<?php
// Fix 6: Regenerate Lesson Catalog button
$catalog_file   = defined('FLOSC_PLUGIN_DIR') ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/lesaep_lesson_catalog.md' : '';
$catalog_exists = $catalog_file && file_exists($catalog_file);
$catalog_gen    = get_option('flosc_lesson_catalog_generated', '');
$catalog_count  = get_option('flosc_lesson_catalog_count', 0);
$regen_url      = wp_nonce_url(admin_url('admin-post.php?action=flosc_regenerate_lesson_catalog'), 'flosc_regen_catalog');
?>
<div style="background: #f6f7f7; border: 1px solid #ddd; padding: 12px 16px; margin-bottom: 20px; border-radius: 3px; display: flex; align-items: center; gap: 16px;">
    <div style="flex: 1;">
        <strong>Lesson Catalog</strong>
        <?php if ($catalog_exists): ?>
            <span style="color: #46b450; margin-left: 8px;">✓ Generated</span>
            <?php if ($catalog_gen): ?><span style="color: #888; font-size: 12px; margin-left: 8px;"><?php echo esc_html($catalog_gen); ?> (<?php echo (int)$catalog_count; ?> lessons)</span><?php endif; ?>
        <?php else: ?>
            <span style="color: #dc3232; margin-left: 8px;">Not yet generated</span>
        <?php endif; ?>
        <p class="description" style="margin: 4px 0 0;">Auto-regenerates when a LeSAEp lesson is saved. Manual regeneration queries all published LeSAEp posts.</p>
    </div>
    <a href="<?php echo esc_url($regen_url); ?>" class="button button-secondary">Regenerate Lesson Catalog</a>
</div>

<?php
$orientation_dir = defined('FLOSC_PLUGIN_DIR') ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/' : '';
$kb_files = [];
if ($orientation_dir && is_dir($orientation_dir)) {
    foreach (scandir($orientation_dir) as $f) {
        if ($f !== '.' && $f !== '..' && in_array(pathinfo($f, PATHINFO_EXTENSION), ['md', 'txt'])) {
            $kb_files[] = $f;
        }
    }
}

$editing_kb_file = isset($_GET['kb_edit']) ? sanitize_file_name($_GET['kb_edit']) : '';
$editing_kb_content = '';
if ($editing_kb_file && $orientation_dir && file_exists($orientation_dir . $editing_kb_file)) {
    $editing_kb_content = file_get_contents($orientation_dir . $editing_kb_file);
}
?>

<!-- Upload form (separate from the main settings form) -->
<div class="card" style="max-width: 700px; margin-bottom: 20px;">
    <h4 style="margin-top: 0;">Upload Knowledge File</h4>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('flosc_kb_upload', 'flosc_kb_upload_nonce'); ?>
        <input type="hidden" name="action" value="flosc_kb_upload">
        <input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr($GLOBALS['flosc_current_ivr'] ?? ''); ?>">
        <table class="form-table" style="margin: 0;">
            <tr>
                <th scope="row" style="padding-top: 8px;"><label for="kb_file_upload">File</label></th>
                <td>
                    <input type="file" name="orientation_file" id="kb_file_upload" accept=".md,.txt" required>
                    <p class="description">.md or .txt files only.</p>
                </td>
            </tr>
            <tr>
                <th scope="row" style="padding-top: 8px;"><label for="kb_access_level">Access Level</label></th>
                <td>
                    <select name="file_access_level" id="kb_access_level">
                        <option value="public">Public (all users including visitors)</option>
                        <option value="members">Members Only</option>
                    </select>
                </td>
            </tr>
        </table>
        <div style="margin-top: 10px;">
            <button type="submit" class="button button-secondary">Upload File</button>
        </div>
    </form>
</div>

<!-- File list -->
<?php if (!empty($kb_files)): ?>
<table class="widefat" style="max-width: 100%; margin-bottom: 20px;">
    <thead>
        <tr>
            <th style="width: 35%;">Filename</th>
            <th style="width: 15%;">Access</th>
            <th style="width: 10%;">Size</th>
            <th style="width: 15%;">Modified</th>
            <th style="width: 25%;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($kb_files as $kbf):
        $fp = $orientation_dir . $kbf;
        $kbf_access = $flow_settings['knowledge_access_' . md5($kbf)] ?? 'public';
        $toggle_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_kb_toggle&kb_file=' . urlencode($kbf) . '&return_ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '')), 'flosc_kb_toggle_' . $kbf);
        $delete_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_kb_delete&kb_file=' . urlencode($kbf) . '&return_ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '')), 'flosc_kb_delete_' . $kbf);
        $edit_url   = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '') . '&tab=ai&kb_edit=' . urlencode($kbf) . '#flosc-kb-section');
    ?>
        <tr>
            <td>
                <strong><?php echo esc_html($kbf); ?></strong>
                <?php if ($editing_kb_file === $kbf): ?><span style="color:#2271b1;margin-left:6px;">← editing</span><?php endif; ?>
            </td>
            <td>
                <?php if ($kbf_access === 'members'): ?>
                    <span style="background:#8b5cf6;color:#fff;padding:2px 7px;border-radius:3px;font-size:11px;">Members</span>
                <?php else: ?>
                    <span style="background:#10b981;color:#fff;padding:2px 7px;border-radius:3px;font-size:11px;">Public</span>
                <?php endif; ?>
            </td>
            <td><?php echo file_exists($fp) ? size_format(filesize($fp)) : '—'; ?></td>
            <td><?php echo file_exists($fp) ? human_time_diff(filemtime($fp), current_time('timestamp')) . ' ago' : '—'; ?></td>
            <td>
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-small"><?php echo $editing_kb_file === $kbf ? 'Editing...' : 'Edit'; ?></a>
                <a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">Toggle Access</a>
                <a href="<?php echo esc_url($delete_url); ?>" class="button button-small" style="color:#d63638;" onclick="return confirm('Delete <?php echo esc_js($kbf); ?>? Cannot be undone.');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#667;font-style:italic;">No knowledge files yet. Upload your first file above.</p>
<?php endif; ?>

<!-- File editor (if editing) -->
<?php if ($editing_kb_file): ?>
<div class="card" style="max-width: 100%; margin-bottom: 20px;">
    <h4 style="margin-top: 0;">Editing: <?php echo esc_html($editing_kb_file); ?></h4>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('flosc_kb_save_edit', 'flosc_kb_save_edit_nonce'); ?>
        <input type="hidden" name="action" value="flosc_kb_save_edit">
        <input type="hidden" name="editing_file" value="<?php echo esc_attr($editing_kb_file); ?>">
        <input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr($GLOBALS['flosc_current_ivr'] ?? ''); ?>">
        <textarea name="file_content" rows="30" class="large-text code" style="font-family: monospace; font-size: 13px; width: 100%;"><?php echo esc_textarea($editing_kb_content); ?></textarea>
        <div style="margin-top: 10px;">
            <button type="submit" class="button button-primary">Save File</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '') . '&tab=ai#flosc-kb-section')); ?>" class="button" style="margin-left: 8px;">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SECTION: PROVIDER ACCURACY TEST (Fix 14) -->
<!-- ============================================ -->
<hr style="margin: 40px 0;" id="flosc-accuracy-test">
<h3 class="flosc-ai-section-heading">🧪 Provider Accuracy Test</h3>
<p class="description">Run a 10-message test sequence to evaluate how faithfully any configured provider maintains acronym definitions and product knowledge across a full session. Tests mid-session drift (the hallucination pattern this sprint fixes).</p>

<div id="flosc-accuracy-test-ui">
    <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
        <button type="button" id="flosc-run-accuracy-test" class="button button-secondary">▶ Run 10-Message Accuracy Test</button>
        <span id="flosc-test-progress" style="display:none; color: #666; font-size: 13px;">Running... message <span id="flosc-test-msg-num">0</span>/10</span>
    </div>

    <div id="flosc-accuracy-results" style="display:none;">
        <table class="widefat" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th style="width:5%;">#</th>
                    <th style="width:35%;">Message</th>
                    <th style="width:45%;">Response</th>
                    <th style="width:8%;">Tokens In</th>
                    <th style="width:7%;">Pass?</th>
                </tr>
            </thead>
            <tbody id="flosc-accuracy-tbody"></tbody>
        </table>
        <div id="flosc-accuracy-summary" style="background: #f6f7f7; padding: 12px 16px; border: 1px solid #ddd; border-radius: 3px; font-size: 13px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var testMessages = [
        "Hello, I'm new here. What is this?",
        "What does FLOSC stand for?",
        "Tell me about LeSAEp",
        "What lessons are available for me?",
        "What does LeSAEp stand for exactly?",
        "How many lessons are there in total?",
        "What is lesson 25 about?",
        "What makes this different from other pronunciation programs?",
        "Who created this program?",
        "What does FLOSC stand for again?"
    ];
    var testProbes = [
        'identity',
        'flosc_acronym',
        'lesaep_knowledge',
        'lesson_inventory',
        'lesaep_acronym',
        'lesson_count',
        'specific_lesson',
        'marketing_claims',
        'attribution',
        'mid_session_drift'
    ];

    $('#flosc-run-accuracy-test').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#flosc-test-progress').show();
        $('#flosc-accuracy-results').hide();
        $('#flosc-accuracy-tbody').empty();
        $('#flosc-accuracy-summary').empty();

        var history = [];
        var totalTokensIn = 0;
        var passes = 0;
        var corrections = 0;

        function runMessage(idx) {
            if (idx >= testMessages.length) {
                // Show summary
                var summaryHtml = '<strong>Session Summary:</strong> '
                    + passes + '/' + testMessages.length + ' pass | '
                    + 'Total input tokens: ' + totalTokensIn + ' | '
                    + 'Corrections triggered: ' + corrections;
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
                    nonce: '<?php echo wp_create_nonce('flosc_accuracy_test'); ?>',
                    ivr: '<?php echo esc_js($GLOBALS['flosc_current_ivr'] ?? ''); ?>',
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
                    if (corrected) corrections++;

                    var passCell = pass
                        ? '<td style="color:#46b450;font-weight:bold;">✓</td>'
                        : '<td style="color:#dc3232;font-weight:bold;">✗</td>';
                    if (corrected) passCell = '<td style="color:#ffa500;font-weight:bold;">⚡</td>';

                    var snippet = response_text.length > 200
                        ? response_text.substring(0, 200) + '…'
                        : response_text;

                    $('#flosc-accuracy-tbody').append(
                        '<tr>'
                        + '<td>' + (idx+1) + '</td>'
                        + '<td style="font-size:12px;">' + $('<div>').text(testMessages[idx]).html() + '</td>'
                        + '<td style="font-size:12px;">' + $('<div>').text(snippet).html() + '</td>'
                        + '<td>' + tokens_in + '</td>'
                        + passCell
                        + '</tr>'
                    );

                    // Add to history for next message
                    history.push({role: 'user', content: testMessages[idx]});
                    history.push({role: 'assistant', content: response_text});

                    runMessage(idx + 1);
                },
                error: function() {
                    $('#flosc-accuracy-tbody').append(
                        '<tr><td>' + (idx+1) + '</td><td>' + $('<div>').text(testMessages[idx]).html() + '</td><td colspan="3" style="color:#dc3232;">Request failed</td></tr>'
                    );
                    runMessage(idx + 1);
                }
            });
        }

        runMessage(0);
    });
});
</script>

</div><!-- .flosc-ai-config -->