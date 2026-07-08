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
$flosc_ai_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-ai';
$flosc_base_prompt = $flosc_flow_settings['ai_base_prompt'] ?? '';
$flosc_ai_provider = $flosc_flow_settings['ai_provider'] ?? 'ivr';
$flosc_ai_openai_model = $flosc_flow_settings['ai_openai_model'] ?? 'gpt-4o-mini';
$flosc_ai_anthropic_model = $flosc_flow_settings['ai_anthropic_model'] ?? 'claude-sonnet-4-5-20250929';
$flosc_ai_xai_model = $flosc_flow_settings['ai_xai_model'] ?? 'grok-2-latest';
$flosc_ai_temperature = $flosc_flow_settings['ai_temperature'] ?? '0.3';
$flosc_ai_max_tokens = $flosc_flow_settings['ai_max_tokens'] ?? '500';
$flosc_enable_ivr_context = $flosc_flow_settings['ai_enable_ivr_context'] ?? true;
$flosc_enable_content_access = $flosc_flow_settings['ai_enable_content_access'] ?? true;
$flosc_ai_response_mode = $flosc_flow_settings['ai_response_mode'] ?? 'enhanced';
$flosc_enable_chaining = $flosc_flow_settings['ai_enable_chaining'] ?? false;
$flosc_chain_provider_1 = $flosc_flow_settings['ai_chain_provider_1'] ?? '';
$flosc_chain_provider_2 = $flosc_flow_settings['ai_chain_provider_2'] ?? '';
$flosc_chain_provider_3 = $flosc_flow_settings['ai_chain_provider_3'] ?? '';

// Fix 9: Risk condition notices — read directly from flow settings (get_current_flow() is null in admin context)
$flosc_product_name = $flosc_flow_settings['identity']['name'] ?? $flosc_flow_settings['name'] ?? '';
$flosc_product_tag  = $flosc_flow_settings['identity']['tagline'] ?? $flosc_flow_settings['tagline'] ?? '';
$flosc_catalog_file  = function_exists('flosc_config_file') ? flosc_config_file('lesaep_lesson_catalog.md') : '';
$flosc_catalog_age   = ($flosc_catalog_file && file_exists($flosc_catalog_file)) ? (time() - filemtime($flosc_catalog_file)) : PHP_INT_MAX;
$flosc_notices = [];
if ((float) $flosc_ai_temperature > 0.5) {
    $flosc_notices[] = '<strong>Temperature ' . esc_html($flosc_ai_temperature) . ' increases fabrication risk.</strong> Recommended: 0.3';
}
if (empty($flosc_product_name)) {
    $flosc_notices[] = '<strong>Product name not configured.</strong> AI has no identity and will hallucinate.';
}
if (!empty($flosc_product_name) && empty($flosc_product_tag)) {
    $flosc_notices[] = '<strong>Product tagline not configured.</strong> AI cannot verify its own product acronym and will guess.';
}
if ($flosc_catalog_age > 7 * DAY_IN_SECONDS) {
    $flosc_regen_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_regenerate_lesson_catalog'), 'flosc_regen_catalog');
    $flosc_age_msg = ($flosc_catalog_age === PHP_INT_MAX) ? 'Lesson catalog has never been generated.' : 'Lesson catalog is more than 7 days old.';
    $flosc_notices[] = $flosc_age_msg . ' <a href="' . esc_url($flosc_regen_url) . '" class="button button-small">Regenerate Now</a>';
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
            <input type="password" id="flow_anthropic_api_key" name="flow_anthropic_api_key" value="<?php echo esc_attr($flosc_flow_settings['anthropic_api_key'] ?? ''); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-ant-api03-...">
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
            <input type="password" id="flow_openai_api_key" name="flow_openai_api_key" value="<?php echo esc_attr($flosc_flow_settings['openai_api_key'] ?? ''); ?>" class="regular-text flosc-ai-key-input" placeholder="sk-proj-...">
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
            <input type="password" id="flow_xai_api_key" name="flow_xai_api_key" value="<?php echo esc_attr($flosc_flow_settings['xai_api_key'] ?? ''); ?>" class="regular-text flosc-ai-key-input" placeholder="xai-...">
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
                <option value="grok-2-latest" <?php selected($flosc_ai_xai_model, 'grok-2-latest'); ?>>Grok 2 (Recommended)</option>
                <option value="grok-beta" <?php selected($flosc_ai_xai_model, 'grok-beta'); ?>>Grok Beta (Legacy)</option>
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
                <input type="checkbox" name="flow_ai_enable_chaining" id="flow_ai_enable_chaining" value="1" <?php checked($flosc_enable_chaining, true); ?>>
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

<div id="flosc-chain-config" class="flosc-ai-chain-config<?php echo $flosc_enable_chaining ? '' : ' flosc-hidden'; ?>">
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
    🎨 Step 4: Customize AI Personality (Optional)
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
                nonce: '<?php echo esc_js( wp_create_nonce('flosc_test_ai') ); ?>',
                ivr: '<?php echo esc_js( $GLOBALS['flosc_current_ivr'] ?? '' ); ?>'
            },
            success: function(response) {
                $loading.hide();
                $results.show();
                
                if (response.success) {
                    $status.html('<span class="flosc-pass-status flosc-pass-status--pass">✓ Connection Successful!</span>');
                    $details.text('Provider: ' + response.data.provider + '\nResponse: ' + response.data.response);
                } else {
                    $status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Connection Failed</span>');
                    $details.text(response.data.message || 'Unknown error');
                }
            },
            error: function() {
                $loading.hide();
                $results.show();
                $status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Request Failed</span>');
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
            <input type="password" id="flow_assemblyai_api_key" name="flow_assemblyai_api_key" value="<?php echo esc_attr($flosc_flow_settings['assemblyai_api_key'] ?? ''); ?>" class="regular-text flosc-ai-stt-input" placeholder="Your AssemblyAI key">
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
<h3 class="flosc-ai-section-heading">🧠 AI Personality & Identity</h3>
<p class="description">Define who your AI is and how it interacts with users. These fields are injected into every AI system prompt.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_personality_name">AI Name</label></th>
        <td>
            <input type="text" id="flow_ai_personality_name" name="flow_ai_personality_name"
                   value="<?php echo esc_attr($flosc_flow_settings['ai_personality_name'] ?? ($flosc_flow_settings['ai_name'] ?? '')); ?>"
                   class="regular-text" placeholder="e.g. LeSAEp Coach">
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
<!-- SECTION: KNOWLEDGE BASE (Fix 15) -->
<!-- ============================================ -->
<hr class="flosc-section-divider" id="flosc-kb-section">
<h3 class="flosc-ai-section-heading">📚 Knowledge Base</h3>
<p class="description">Upload markdown files containing lesson catalogs, FAQs, product info, and teaching guidelines. These files are injected into the AI knowledge base on every session.</p>

<?php
// Fix 15: Display any KB action success message
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status parameters for admin notice display
 $flosc_get = wp_unslash($_GET);
if (isset($flosc_get['kb_action'])) {
    $flosc_kb_action = sanitize_key($flosc_get['kb_action']);
    $flosc_kb_msg = '';
    if ($flosc_kb_action === 'uploaded')      $flosc_kb_msg = 'File uploaded successfully.';
    if ($flosc_kb_action === 'deleted')       $flosc_kb_msg = 'File deleted.';
    if ($flosc_kb_action === 'toggled')       $flosc_kb_msg = 'Access level updated.';
    if ($flosc_kb_action === 'saved')         $flosc_kb_msg = 'File saved.';
    if ($flosc_kb_action === 'error')         $flosc_kb_msg = isset($flosc_get['kb_error']) ? sanitize_text_field(urldecode((string) $flosc_get['kb_error'])) : 'An error occurred.';
    if ($flosc_kb_msg):
?>
<div class="notice notice-success inline flosc-margin-bottom-15"><p><?php echo esc_html($flosc_kb_msg); ?></p></div>
<?php endif; } ?>

<?php
// Fix 6: Regenerate Lesson Catalog button
$flosc_catalog_file   = function_exists('flosc_config_file') ? flosc_config_file('lesaep_lesson_catalog.md') : '';
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
        <p class="description flosc-margin-top-4">Auto-regenerates when a LeSAEp lesson is saved. Manual regeneration queries all published LeSAEp posts.</p>
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
    $flosc_editing_kb_content = file_get_contents($flosc_editing_kb_path);
}
?>

<!-- Upload form (separate from the main settings form) -->
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
        $flosc_toggle_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_kb_toggle&kb_file=' . urlencode($flosc_kbf) . '&return_ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '')), 'flosc_kb_toggle_' . $flosc_kbf);
        $flosc_delete_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_kb_delete&kb_file=' . urlencode($flosc_kbf) . '&return_ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '')), 'flosc_kb_delete_' . $flosc_kbf);
        $flosc_edit_url   = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '') . '&tab=ai&kb_edit=' . urlencode($flosc_kbf) . '#flosc-kb-section');
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
            <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($GLOBALS['flosc_current_ivr'] ?? '') . '&tab=ai#flosc-kb-section')); ?>" class="button flosc-margin-left-8">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SECTION: PROVIDER ACCURACY TEST (Fix 14) -->
<!-- ============================================ -->
<hr class="flosc-section-divider" id="flosc-accuracy-test">
<h3 class="flosc-ai-section-heading">🧪 Provider Accuracy Test</h3>
<p class="description">Run a 10-message test sequence to evaluate how faithfully any configured provider maintains acronym definitions and product knowledge across a full session. Tests mid-session drift (the hallucination pattern this sprint fixes).</p>

<div id="flosc-accuracy-test-ui">
    <div class="flosc-ai-accuracy-controls">
        <button type="button" id="flosc-run-accuracy-test" class="button button-secondary">▶ Run 10-Message Accuracy Test</button>
        <span id="flosc-test-progress" class="flosc-ai-progress flosc-hidden">Running... message <span id="flosc-test-msg-num">0</span>/10</span>
    </div>

    <div id="flosc-accuracy-results" class="flosc-hidden">
        <table class="widefat flosc-margin-bottom-12">
            <thead>
                <tr>
                    <th class="flosc-width-5">#</th>
                    <th class="flosc-width-35">Message</th>
                    <th class="flosc-width-45">Response</th>
                    <th class="flosc-width-8">Tokens In</th>
                    <th class="flosc-width-7">Pass?</th>
                </tr>
            </thead>
            <tbody id="flosc-accuracy-tbody"></tbody>
        </table>
        <div id="flosc-accuracy-summary" class="flosc-ai-summary"></div>
    </div>
</div>

<?php ob_start(); ?>
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
        var feedback = 0;

        function runMessage(idx) {
            if (idx >= testMessages.length) {
                // Show summary
                var summaryHtml = '<strong>Session Summary:</strong> '
                    + passes + '/' + testMessages.length + ' pass | '
                    + 'Total input tokens: ' + totalTokensIn + ' | '
                    + 'Feedback triggered: ' + feedback;
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
                    nonce: '<?php echo esc_attr(wp_create_nonce('flosc_accuracy_test')); ?>',
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
                        + '<td>' + (idx+1) + '</td>'
                        + '<td class="flosc-text-12">' + $('<div>').text(testMessages[idx]).html() + '</td>'
                        + '<td class="flosc-text-12">' + $('<div>').text(snippet).html() + '</td>'
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
                        '<tr><td>' + (idx+1) + '</td><td>' + $('<div>').text(testMessages[idx]).html() + '</td><td colspan="3" class="flosc-request-failed">Request failed</td></tr>'
                    );
                    runMessage(idx + 1);
                }
            });
        }

        runMessage(0);
    });
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

</div><!-- .flosc-ai-config -->