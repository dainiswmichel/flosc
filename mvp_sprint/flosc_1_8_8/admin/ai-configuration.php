<?php
/**
 * FLOSC AI Configuration Tab
 * 
 * COMPREHENSIVE AI INTEGRATION SETTINGS
 * =====================================
 * 
 * This tab configures how AI providers interact with FLOSC's framework.
 * The AI needs to understand:
 * 
 * 1. IVR STRUCTURE (conditions teach AI when to use specific messages)
 *    - Access to ivr.md parsed structure
 *    - Condition evaluation context (phase, quiz score, session vars)
 *    - Message priority and fallback chains
 * 
 * 2. CONTENT ACCESS (AI needs to know what content exists)
 *    - Lessons library
 *    - Quiz questions and answers
 *    - Offer details and pricing
 *    - Custom knowledge base entries
 * 
 * 3. FLOSC PHASE NAVIGATION (AI guides users through journey)
 *    - Freeline: Encourage quiz participation
 *    - Login: Deliver free lesson, present offer
 *    - Offer: Sales pitch with personalization
 *    - Sale: Onboarding to content
 *    - Content: Ongoing support and encouragement
 * 
 * 4. PROVIDER CHAINING (optional: chain responses for quality)
 *    - ChatGPT → Claude → Grok (daisy-chain for review/refinement)
 * 
 * v1.2.9: Added tab header for flow context
 *    - Each provider can validate or enhance previous response
 *    - Fallback chains if primary provider fails
 * 
 * BACKEND STATUS: 🚧 IN DEVELOPMENT
 * ----------------------------------
 * Settings marked [READY] are functional.
 * Settings marked [BACKEND NEEDED] require additional implementation.
 * Pseudocode provided for developer reference.
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('🤖', 'AI');

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$base_prompt = $flow_settings['ai_base_prompt'] ?? '';
if (empty($base_prompt)) {
    $base_prompt = "You are " . ($flow_settings['name'] ?? 'FLOSC App') . ", an AI assistant. Your mission is to help users through personalized guidance. Be helpful, friendly, specific, and action-oriented.";
}
$ai_provider = $flow_settings['ai_provider'] ?? 'ivr';
$ai_openai_model = $flow_settings['ai_openai_model'] ?? 'gpt-4o-mini';
$ai_anthropic_model = $flow_settings['ai_anthropic_model'] ?? 'claude-sonnet-4-5-20250929';
$ai_xai_model = $flow_settings['ai_xai_model'] ?? 'grok-2-latest';
$ai_temperature = $flow_settings['ai_temperature'] ?? '0.7';
$ai_max_tokens = $flow_settings['ai_max_tokens'] ?? '500';
$enable_provider_chaining = $flow_settings['ai_enable_chaining'] ?? false;
$chain_providers = $flow_settings['ai_chain_providers'] ?? [];
$enable_ivr_context = $flow_settings['ai_enable_ivr_context'] ?? true;
$enable_content_access = $flow_settings['ai_enable_content_access'] ?? true;
$ai_response_mode = $flow_settings['ai_response_mode'] ?? 'enhanced'; // 'strict' or 'enhanced'
?>

<h2>🤖 AI Configuration</h2>
<p class="description" style="font-size: 14px; margin-bottom: 20px;">
    Connect your FLOSC flow to AI providers (OpenAI, Anthropic, xAI) or use IVR-only mode (scripted responses, no API costs).
</p>

<!-- Quick Start Guide -->
<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 20px; margin-bottom: 30px;">
    <h3 style="margin-top: 0; color: #0073aa;">📋 Quick Start Guide</h3>
    <ol style="margin: 15px 0; padding-left: 20px; line-height: 1.8;">
        <li><strong>Choose your AI provider</strong> below (or keep "IVR" for scripted mode)</li>
        <li><strong>Get your API key</strong> by clicking the provider's link in the corresponding section</li>
        <li><strong>Paste your API key</strong> into the password field</li>
        <li><strong>Click "Test Connection"</strong> to verify it works</li>
        <li><strong>Customize your AI's personality</strong> in the Base System Prompt (optional)</li>
        <li><strong>Save Settings</strong> at the bottom of this page</li>
    </ol>
    <p style="margin-bottom: 0; color: #555;">
        💡 <strong>Tip:</strong> Start with <strong>Anthropic (Claude)</strong> for best FLOSC integration, or use <strong>IVR</strong> if you want zero API costs.
    </p>
</div>

<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 30px;">
    <strong>💡 How It Works:</strong> When you select an AI provider and add your API key, FLOSC automatically injects IVR context, enforces content boundaries, and enables conversational AI — all guided by your IVR configuration. The settings below let you fine-tune this behavior.
</div>

<!-- ============================================ -->
<!-- SECTION 1: PROVIDER CONNECTION [READY] -->
<!-- ============================================ -->
<h3 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
    🔌 Step 1: Choose Your AI Provider
</h3>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_provider">Primary AI Provider</label></th>
        <td>
            <select name="flow_ai_provider" id="flow_ai_provider" style="font-size: 14px; padding: 8px;">
                <option value="ivr" <?php selected($ai_provider, 'ivr'); ?>>IVR Only (Scripted Responses - Zero API Cost)</option>
                <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic Claude (Recommended for FLOSC)</option>
                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (Fast & Affordable)</option>
                <option value="xai" <?php selected($ai_provider, 'xai'); ?>>xAI Grok</option>
            </select>
            <p class="description" style="margin-top: 10px;">
                <strong>IVR:</strong> Uses your configured messages only (no AI, no costs).<br>
                <strong>AI Providers:</strong> Enable conversational intelligence with retrieval-augmented generation (RAG).
            </p>
        </td>
    </tr>
</table>

<h3 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin: 40px 0 20px 0;">
    🔑 Step 2: Add Your API Keys
</h3>
<p class="description" style="margin-bottom: 20px;">
    Add API keys for the providers you want to use. You only need keys for the provider you selected above.
</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flow_anthropic_api_key">
                <strong>Anthropic API Key</strong>
            </label>
        </th>
        <td>
            <input type="password" id="flow_anthropic_api_key" name="flow_anthropic_api_key" value="<?php echo esc_attr($flow_settings['anthropic_api_key'] ?? ''); ?>" class="regular-text" style="font-family: monospace; font-size: 13px;" placeholder="sk-ant-api03-...">
            <p class="description">
                <a href="https://console.anthropic.com/settings/keys" target="_blank" class="button button-secondary" style="margin-top: 8px;">
                    📥 Get Your Anthropic API Key Here
                </a><br>
                <span style="margin-top: 8px; display: inline-block;">Best for RAG with tools, context handling, and following complex instructions.</span>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_anthropic_model">Anthropic Model</label></th>
        <td>
            <select name="flow_ai_anthropic_model" id="flow_ai_anthropic_model" style="font-size: 13px; padding: 6px;">
                <option value="claude-sonnet-4-5-20250929" <?php selected($ai_anthropic_model, 'claude-sonnet-4-5-20250929'); ?>>Claude Sonnet 4.5 (Recommended)</option>
                <option value="claude-haiku-4-5-20251001" <?php selected($ai_anthropic_model, 'claude-haiku-4-5-20251001'); ?>>Claude Haiku 4.5 (Fastest, cheapest)</option>
                <option value="claude-opus-4-6" <?php selected($ai_anthropic_model, 'claude-opus-4-6'); ?>>Claude Opus 4.6 (Most capable)</option>
                <option value="claude-3-5-sonnet-20241022" <?php selected($ai_anthropic_model, 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet (Legacy)</option>
            </select>
            <p class="description">Choose which Claude model to use for this flow.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_openai_api_key">
                <strong>OpenAI API Key</strong>
            </label>
        </th>
        <td>
            <input type="password" id="flow_openai_api_key" name="flow_openai_api_key" value="<?php echo esc_attr($flow_settings['openai_api_key'] ?? ''); ?>" class="regular-text" style="font-family: monospace; font-size: 13px;" placeholder="sk-proj-...">
            <p class="description">
                <a href="https://platform.openai.com/api-keys" target="_blank" class="button button-secondary" style="margin-top: 8px;">
                    📥 Get Your OpenAI API Key Here
                </a><br>
                <span style="margin-top: 8px; display: inline-block;">Fast and cost-effective for general conversations.</span>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_openai_model">OpenAI Model</label></th>
        <td>
            <select name="flow_ai_openai_model" id="flow_ai_openai_model" style="font-size: 13px; padding: 6px;">
                <option value="gpt-4o-mini" <?php selected($ai_openai_model, 'gpt-4o-mini'); ?>>GPT-4o-mini (Fast & affordable)</option>
                <option value="gpt-4o" <?php selected($ai_openai_model, 'gpt-4o'); ?>>GPT-4o (More capable)</option>
                <option value="gpt-4.1" <?php selected($ai_openai_model, 'gpt-4.1'); ?>>GPT-4.1 (Latest)</option>
                <option value="gpt-4.1-mini" <?php selected($ai_openai_model, 'gpt-4.1-mini'); ?>>GPT-4.1 Mini (Latest affordable)</option>
                <option value="gpt-4.1-nano" <?php selected($ai_openai_model, 'gpt-4.1-nano'); ?>>GPT-4.1 Nano (Cheapest)</option>
            </select>
            <p class="description">Choose which OpenAI model to use for this flow.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_xai_api_key">
                <strong>xAI API Key</strong>
            </label>
        </th>
        <td>
            <input type="password" id="flow_xai_api_key" name="flow_xai_api_key" value="<?php echo esc_attr($flow_settings['xai_api_key'] ?? ''); ?>" class="regular-text" style="font-family: monospace; font-size: 13px;" placeholder="xai-...">
            <p class="description">
                <a href="https://console.x.ai" target="_blank" class="button button-secondary" style="margin-top: 8px;">
                    📥 Get Your xAI API Key Here
                </a><br>
                <span style="margin-top: 8px; display: inline-block;">Experimental provider from xAI.</span>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_xai_model">xAI Model</label></th>
        <td>
            <select name="flow_ai_xai_model" id="flow_ai_xai_model" style="font-size: 13px; padding: 6px;">
                <option value="grok-2-latest" <?php selected($ai_xai_model, 'grok-2-latest'); ?>>Grok 2 (Recommended)</option>
                <option value="grok-beta" <?php selected($ai_xai_model, 'grok-beta'); ?>>Grok Beta (Legacy)</option>
            </select>
            <p class="description">Choose which Grok model to use for this flow.</p>
        </td>
    </tr>
</table>

<!-- v1.8.7: Per-flow AI tuning -->
<h3 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin: 40px 0 20px 0;">
    🎛️ Step 2b: Model Tuning (Per Flow)
</h3>
<p class="description" style="margin-bottom: 20px;">
    Fine-tune AI behavior for this flow. These settings apply to whichever provider is selected above.
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_temperature">Temperature</label></th>
        <td>
            <input type="number" id="flow_ai_temperature" name="flow_ai_temperature" value="<?php echo esc_attr($ai_temperature); ?>" min="0" max="2" step="0.1" style="width: 80px; font-size: 13px;">
            <p class="description">
                Controls randomness. <strong>0.0</strong> = deterministic, <strong>0.7</strong> = balanced (default), <strong>1.5+</strong> = creative. Lower for sales scripts, higher for coaching.
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_max_tokens">Max Tokens</label></th>
        <td>
            <input type="number" id="flow_ai_max_tokens" name="flow_ai_max_tokens" value="<?php echo esc_attr($ai_max_tokens); ?>" min="50" max="4096" step="50" style="width: 100px; font-size: 13px;">
            <p class="description">
                Maximum response length. <strong>500</strong> = concise chat (default), <strong>1000</strong> = detailed explanations, <strong>2000+</strong> = long-form coaching. Higher values cost more per response.
            </p>
        </td>
    </tr>
</table>

<!-- Connection Test -->
<h3 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin: 40px 0 20px 0;">
    🧪 Step 3: Test Your Connection
</h3>

<div class="card" style="max-width: 800px; margin-bottom: 20px; padding: 20px;">
    <p class="description" style="font-size: 14px; margin-bottom: 15px;">
        Verify that your selected AI provider is configured correctly and responding. This sends a test message to the AI.
    </p>
    <button type="button" id="test-ai-connection" class="button button-primary button-large" style="font-size: 15px; padding: 10px 20px;">
        🚀 Test AI Connection Now
    </button>
    <div id="test-results" style="margin-top: 15px; display: none;">
        <div id="test-status"></div>
        <div id="test-details" style="margin-top: 10px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
    </div>
    <div id="test-loading" style="margin-top: 15px; display: none;">
        <span class="spinner is-active" style="float: none; margin-right: 10px;"></span>
        <span style="font-size: 14px;">Testing connection to AI provider...</span>
    </div>
</div>

<h3 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin: 40px 0 20px 0;">
    🎨 Step 4: Customize AI Personality (Optional)
</h3>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_base_prompt">Base System Prompt</label></th>
        <td>
            <textarea id="flow_ai_base_prompt" name="flow_ai_base_prompt" rows="6" class="large-text" style="font-family: monospace; font-size: 13px;"><?php echo esc_textarea($base_prompt); ?></textarea>
            <p class="description">
                Define your AI's core personality and behavior. FLOSC automatically adds phase-specific instructions (freeline, login, offer, etc.) on top of this base prompt.
            </p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION 2: AI RESPONSE MODE [READY] -->
<!-- ============================================ -->
<h3 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin: 50px 0 20px 0;">
    ⚙️ Advanced: AI Response Mode
</h3>
<p class="description" style="margin-bottom: 15px;">
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
<!-- SECTION 3: IVR CONTEXT INJECTION [BACKEND NEEDED] -->
<!-- ============================================ -->
<h3 style="margin-top: 40px;">IVR Context Injection</h3>
<p class="description">Configure how the AI accesses and uses your IVR message structure.</p>

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
</table>

<!-- ============================================ -->
<!-- SECTION 4: CONTENT ACCESS -->
<!-- ============================================ -->
<h3 style="margin-top: 40px;">Content Access</h3>
<p class="description">Give the AI access to your content library for personalized recommendations.</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flow_ai_enable_content_access">
                <input type="checkbox" name="flow_ai_enable_content_access" id="flow_ai_enable_content_access" value="1" <?php checked($enable_content_access, true); ?>>
                Enable Content Access
            </label>
        </th>
        <td>
            <p class="description">
                When enabled, AI can reference lessons, quizzes, offers, and knowledge base entries.
                This allows personalized recommendations based on user progress and interests.
            </p>
            <div style="background: #e7f5fe; border-left: 4px solid #0073aa; padding: 12px 15px; margin-top: 10px;">
                <strong>How Content Access Works:</strong><br>
                The AI automatically receives context about your lessons, quizzes, offers, and any content you upload to the AI Knowledge Base.
                When a user asks questions like "What are my upgrade options?" or "What lesson should I do next?", the AI draws from your actual content to give accurate, personalized answers.
                Upload expert knowledge via the <strong>AI Knowledge</strong> tab to give AI access to information beyond its training data.
            </div>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION 5: PROVIDER CHAINING -->
<!-- ============================================ -->
<h3 style="margin-top: 40px;">Provider Chaining</h3>
<p class="description">Chain multiple AI providers for quality enhancement or fallback.</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flow_ai_enable_chaining">
                <input type="checkbox" name="flow_ai_enable_chaining" id="flow_ai_enable_chaining" value="1" <?php checked($enable_provider_chaining, true); ?>>
                Enable Provider Chaining
            </label>
        </th>
        <td>
            <p class="description">
                Chain providers to refine responses or provide fallback if primary fails.<br>
                Example: ChatGPT generates → Claude reviews/enhances → Final response to user
            </p>
            
            <div style="margin-top: 15px;">
                <label><strong>Chain Configuration:</strong></label><br>
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                    <select name="flow_ai_chain_provider_1" style="width: 150px;">
                        <option value="">None</option>
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Claude</option>
                        <option value="xai">Grok</option>
                    </select>
                    <span>→</span>
                    <select name="flow_ai_chain_provider_2" style="width: 150px;">
                        <option value="">None</option>
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Claude</option>
                        <option value="xai">Grok</option>
                    </select>
                    <span>→</span>
                    <select name="flow_ai_chain_provider_3" style="width: 150px;">
                        <option value="">None</option>
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Claude</option>
                        <option value="xai">Grok</option>
                    </select>
                </div>
                <p class="description">Leave empty slots for simple chains. Example: OpenAI → Claude → (none)</p>
            </div>
            
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; margin-top: 15px;">
                <strong>Coming Soon:</strong> Provider chaining is a planned feature.
                For now, select your primary AI provider in Step 1 above. Your primary provider handles all responses,
                with IVR as the automatic fallback if the AI provider is unavailable.
            </div>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION 6: PHASE-SPECIFIC PROMPTS [READY] -->
<!-- ============================================ -->
<h3 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin: 50px 0 20px 0;">
    📍 Advanced: Phase-Specific AI Instructions
</h3>
<p class="description" style="margin-bottom: 15px;">
    Customize how your AI behaves during each FLOSC phase. These instructions are automatically added to your base prompt based on where the user is in their journey.
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_prompt_freeline">Freeline Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_freeline" id="flow_ai_prompt_freeline" rows="3" class="large-text"><?php echo esc_textarea($flow_settings['ai_prompt_freeline'] ?? 'Encourage user to take the quiz. Be curious about their goals.'); ?></textarea>
            <p class="description">Visitors who haven't taken the quiz yet.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_login">Login Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_login" id="flow_ai_prompt_login" rows="3" class="large-text"><?php echo esc_textarea($flow_settings['ai_prompt_login'] ?? 'Deliver free lesson based on quiz results. Build trust before presenting offer.'); ?></textarea>
            <p class="description">Post-quiz visitors and logged-in users.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_offer">Offer Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_offer" id="flow_ai_prompt_offer" rows="3" class="large-text"><?php echo esc_textarea($flow_settings['ai_prompt_offer'] ?? 'Present personalized offer. Address objections. Show value specific to their quiz results.'); ?></textarea>
            <p class="description">Sales pitch mode.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_sale">Sale Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_sale" id="flow_ai_prompt_sale" rows="3" class="large-text"><?php echo esc_textarea($flow_settings['ai_prompt_sale'] ?? 'Onboard user to content. Explain navigation. Build excitement for their purchase.'); ?></textarea>
            <p class="description">Post-purchase onboarding.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_ai_prompt_content">Content Phase</label></th>
        <td>
            <textarea name="flow_ai_prompt_content" id="flow_ai_prompt_content" rows="3" class="large-text"><?php echo esc_textarea($flow_settings['ai_prompt_content'] ?? 'Support learning journey. Answer questions. Encourage progress and celebrate wins.'); ?></textarea>
            <p class="description">Ongoing support for paying customers.</p>
        </td>
    </tr>
</table>

<script>
jQuery(document).ready(function($) {
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
                nonce: '<?php echo wp_create_nonce('flosc_test_ai'); ?>'
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

<!-- Speech-to-Text Configuration (v1.8.2: moved from Lessons tab) -->
<hr style="margin: 60px 0 40px 0; border: none; border-top: 3px solid #ddd;">

<h2 style="margin-top: 40px;">🎤 Speech-to-Text Configuration</h2>
<p class="description" style="font-size: 14px; margin-bottom: 20px;">
    Configure speech-to-text for audio-based quiz questions (Pronunciation quizzes, audio-response scoring). <strong>Only needed if you're using audio quizzes.</strong>
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_stt_provider">Speech-to-Text Provider</label></th>
        <td>
            <select name="flow_stt_provider" id="flow_stt_provider" style="font-size: 14px; padding: 8px;">
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
            <label for="flow_assemblyai_api_key">
                <strong>AssemblyAI API Key</strong>
            </label>
        </th>
        <td>
            <input type="password" id="flow_assemblyai_api_key" name="flow_assemblyai_api_key" value="<?php echo esc_attr($flow_settings['assemblyai_api_key'] ?? ''); ?>" class="regular-text" style="font-family: monospace; font-size: 13px;" placeholder="Your AssemblyAI key">
            <p class="description">
                <a href="https://www.assemblyai.com/dashboard/signup" target="_blank" class="button button-secondary" style="margin-top: 8px;">
                    📥 Get Your AssemblyAI API Key Here
                </a><br>
                <span style="margin-top: 8px; display: inline-block;">Industry-leading accuracy for speech recognition.</span>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_deepgram_api_key">
                <strong>Deepgram API Key</strong>
            </label>
        </th>
        <td>
            <input type="password" id="flow_deepgram_api_key" name="flow_deepgram_api_key" value="<?php echo esc_attr($flow_settings['deepgram_api_key'] ?? ''); ?>" class="regular-text" style="font-family: monospace; font-size: 13px;" placeholder="Your Deepgram key">
            <p class="description">
                <a href="https://console.deepgram.com/signup" target="_blank" class="button button-secondary" style="margin-top: 8px;">
                    📥 Get Your Deepgram API Key Here
                </a><br>
                <span style="margin-top: 8px; display: inline-block;">Ultra-fast real-time transcription with excellent accuracy.</span>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="flow_custom_stt_endpoint">
                <strong>Custom STT Endpoint URL</strong>
            </label>
        </th>
        <td>
            <input type="url" id="flow_custom_stt_endpoint" name="flow_custom_stt_endpoint" value="<?php echo esc_attr($flow_settings['custom_stt_endpoint'] ?? ''); ?>" class="regular-text" style="font-family: monospace; font-size: 13px;" placeholder="https://your-stt-endpoint.com/transcribe">
            <p class="description">Only required if using "Custom Endpoint" option above. URL to your self-hosted speech-to-text service.</p>
        </td>
    </tr>
</table>

<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 30px 0;">
    <strong>💡 Remember:</strong> After adding your API keys, click <strong>"Save Settings"</strong> at the bottom of this page, then use the <strong>"Test AI Connection"</strong> button above to verify everything works!
</div>
