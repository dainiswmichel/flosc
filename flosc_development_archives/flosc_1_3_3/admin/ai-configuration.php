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

$base_prompt = get_option('flosc_ai_base_prompt', '');
if (empty($base_prompt)) {
    $base_prompt = "You are " . get_option('flosc_product_name', 'FLOSC App') . ", an AI assistant. Your mission is to help users through personalized guidance. Be helpful, friendly, specific, and action-oriented.";
}
$ai_provider = get_option('flosc_ai_provider', 'ivr');
$enable_provider_chaining = get_option('flosc_ai_enable_chaining', false);
$chain_providers = get_option('flosc_ai_chain_providers', []);
$enable_ivr_context = get_option('flosc_ai_enable_ivr_context', true);
$enable_content_access = get_option('flosc_ai_enable_content_access', true);
$ai_response_mode = get_option('flosc_ai_response_mode', 'enhanced'); // 'strict' or 'enhanced'
?>

<h2>AI Configuration</h2>
<p class="description">Configure your AI assistant's connection to providers, content, and FLOSC framework.</p>

<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
    <strong>⚠️ Development Status:</strong> Basic AI provider connections are functional. Advanced features (IVR context injection, content access, provider chaining) are marked <strong>[BACKEND NEEDED]</strong> and require additional development.
</div>

<!-- ============================================ -->
<!-- SECTION 1: PROVIDER CONNECTION [READY] -->
<!-- ============================================ -->
<h3>AI Provider Connection</h3>

<!-- Connection Test -->
<div class="card" style="max-width: 800px; margin-bottom: 20px;">
    <h3>Test AI Connection</h3>
    <p class="description">Verify that your AI provider is configured correctly and responding.</p>
    <button type="button" id="test-ai-connection" class="button button-large">Test Connection</button>
    <div id="test-results" style="margin-top: 15px; display: none;">
        <div id="test-status"></div>
        <div id="test-details" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
    </div>
    <div id="test-loading" style="margin-top: 15px; display: none;">
        <span class="spinner is-active" style="float: none; margin-right: 10px;"></span>
        Testing connection...
    </div>
</div>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_ai_provider">Primary AI Provider</label></th>
        <td>
            <select name="flosc_ai_provider" id="flosc_ai_provider">
                <option value="ivr" <?php selected($ai_provider, 'ivr'); ?>>IVR (Scripted - Free)</option>
                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (GPT-4o-mini)</option>
                <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic (Claude)</option>
                <option value="xai" <?php selected($ai_provider, 'xai'); ?>>xAI (Grok)</option>
            </select>
            <p class="description">IVR uses scripted messages only. AI providers require API keys.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_openai_api_key">OpenAI API Key</label></th>
        <td>
            <input type="password" id="flosc_openai_api_key" name="flosc_openai_api_key" value="<?php echo esc_attr(get_option('flosc_openai_api_key', '')); ?>" class="regular-text">
            <p class="description">Get your key at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_anthropic_api_key">Anthropic API Key</label></th>
        <td>
            <input type="password" id="flosc_anthropic_api_key" name="flosc_anthropic_api_key" value="<?php echo esc_attr(get_option('flosc_anthropic_api_key', '')); ?>" class="regular-text">
            <p class="description">Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_xai_api_key">xAI API Key</label></th>
        <td>
            <input type="password" id="flosc_xai_api_key" name="flosc_xai_api_key" value="<?php echo esc_attr(get_option('flosc_xai_api_key', '')); ?>" class="regular-text">
            <p class="description">Get your key at <a href="https://console.x.ai" target="_blank">console.x.ai</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_ai_base_prompt">Base System Prompt</label></th>
        <td>
            <textarea id="flosc_ai_base_prompt" name="flosc_ai_base_prompt" rows="6" class="large-text"><?php echo esc_textarea($base_prompt); ?></textarea>
            <p class="description">The AI's base personality and instructions. Phase-specific prompts are added automatically.</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION 2: AI RESPONSE MODE [READY] -->
<!-- ============================================ -->
<h3 style="margin-top: 40px;">AI Response Mode</h3>
<p class="description">Control how the AI uses IVR messages when generating responses.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_ai_response_mode">Response Mode</label></th>
        <td>
            <select name="flosc_ai_response_mode" id="flosc_ai_response_mode">
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
<h3 style="margin-top: 40px;">IVR Context Injection <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">BACKEND NEEDED</span></h3>
<p class="description">Configure how the AI accesses and uses your IVR message structure.</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flosc_ai_enable_ivr_context">
                <input type="checkbox" name="flosc_ai_enable_ivr_context" id="flosc_ai_enable_ivr_context" value="1" <?php checked($enable_ivr_context, true); ?>>
                Enable IVR Context
            </label>
        </th>
        <td>
            <p class="description">
                When enabled, AI receives the full IVR structure including conditions, phases, and message types.
                The AI learns WHEN to use specific messages based on your configured conditions.
            </p>
            <div style="background: #f0f0f1; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 12px;">
                <strong>Backend Implementation Needed:</strong><br>
                /* PSEUDOCODE - Developer Reference */<br>
                <br>
                // On each AI request, inject IVR context into system prompt:<br>
                $ivr_config = FLOSC_IVR_Parser::flosc_instance()->get_flosc_config();<br>
                $current_phase = flosc_get_user_phase(); // freeline, login, offer, sale, content<br>
                $session_vars = flosc_get_session_variables(); // quiz_score, has_purchased, etc.<br>
                <br>
                // Filter messages by current phase and evaluate conditions<br>
                $available_messages = array_filter($ivr_config['messages'], function($msg) use ($current_phase, $session_vars) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;if ($msg['phase'] !== $current_phase) return false;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;return flosc_evaluate_condition($msg['condition'], $session_vars);<br>
                });<br>
                <br>
                // Build context injection<br>
                $ivr_context = "AVAILABLE MESSAGES FOR CURRENT CONTEXT:\n";<br>
                foreach ($available_messages as $msg) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;$ivr_context .= "- [{$msg['type']}] {$msg['name']}: {$msg['content']}\n";<br>
                &nbsp;&nbsp;&nbsp;&nbsp;if (!empty($msg['action'])) $ivr_context .= "&nbsp;&nbsp;Action: {$msg['action']}\n";<br>
                }<br>
                <br>
                // Prepend to AI system prompt<br>
                $full_prompt = $ivr_context . "\n\n" . $base_prompt;<br>
                <br>
                // Send to AI provider with full context
            </div>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION 4: CONTENT ACCESS [BACKEND NEEDED] -->
<!-- ============================================ -->
<h3 style="margin-top: 40px;">Content Access <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-weight: bold; font-size: 11px;">BACKEND NEEDED</span></h3>
<p class="description">Give the AI access to your content library for personalized recommendations.</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flosc_ai_enable_content_access">
                <input type="checkbox" name="flosc_ai_enable_content_access" id="flosc_ai_enable_content_access" value="1" <?php checked($enable_content_access, true); ?>>
                Enable Content Access
            </label>
        </th>
        <td>
            <p class="description">
                When enabled, AI can reference lessons, quizzes, offers, and knowledge base entries.
                This allows personalized recommendations based on user progress and interests.
            </p>
            <div style="background: #f0f0f1; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 12px;">
                <strong>Backend Implementation Needed:</strong><br>
                /* PSEUDOCODE - Developer Reference */<br>
                <br>
                // Build content inventory for AI context<br>
                $content_context = [];<br>
                <br>
                // 1. Available Lessons<br>
                $lessons = get_option('flosc_lessons', []);<br>
                $content_context['lessons'] = array_map(function($lesson) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;return [<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'title' => $lesson['title'],<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'description' => $lesson['description'],<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'difficulty' => $lesson['difficulty'],<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'duration' => $lesson['duration']<br>
                &nbsp;&nbsp;&nbsp;&nbsp;];<br>
                }, $lessons);<br>
                <br>
                // 2. Quiz Structure<br>
                $quiz_type = get_option('flosc_quiz_type', 'flosc_sample_text_based_quiz');<br>
                $content_context['quiz'] = [<br>
                &nbsp;&nbsp;&nbsp;&nbsp;'type' => $quiz_type,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;'questions_count' => count(get_option('flosc_quiz_questions', [])),<br>
                &nbsp;&nbsp;&nbsp;&nbsp;'scoring_logic' => 'User scores determine personalization'<br>
                ];<br>
                <br>
                // 3. Active Offers<br>
                $offers = get_option('flosc_offers', []);<br>
                $content_context['offers'] = array_map(function($offer) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;return [<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'name' => $offer['name'],<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'price' => $offer['price'],<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'description' => $offer['description']<br>
                &nbsp;&nbsp;&nbsp;&nbsp;];<br>
                }, $offers);<br>
                <br>
                // 4. Knowledge Base (AI Knowledge tab)<br>
                $kb_entries = get_option('flosc_ai_knowledge', '');<br>
                $content_context['knowledge_base'] = $kb_entries;<br>
                <br>
                // Format for AI injection<br>
                $content_prompt = "AVAILABLE CONTENT:\n" . json_encode($content_context, JSON_PRETTY_PRINT);<br>
                <br>
                // Inject into system prompt
            </div>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION 5: PROVIDER CHAINING [BACKEND NEEDED] -->
<!-- ============================================ -->
<h3 style="margin-top: 40px;">Provider Chaining <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-weight: bold; font-size: 11px;">BACKEND NEEDED</span></h3>
<p class="description">Chain multiple AI providers for quality enhancement or fallback.</p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="flosc_ai_enable_chaining">
                <input type="checkbox" name="flosc_ai_enable_chaining" id="flosc_ai_enable_chaining" value="1" <?php checked($enable_provider_chaining, true); ?>>
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
                    <select name="flosc_ai_chain_provider_1" style="width: 150px;">
                        <option value="">None</option>
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Claude</option>
                        <option value="xai">Grok</option>
                    </select>
                    <span>→</span>
                    <select name="flosc_ai_chain_provider_2" style="width: 150px;">
                        <option value="">None</option>
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Claude</option>
                        <option value="xai">Grok</option>
                    </select>
                    <span>→</span>
                    <select name="flosc_ai_chain_provider_3" style="width: 150px;">
                        <option value="">None</option>
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Claude</option>
                        <option value="xai">Grok</option>
                    </select>
                </div>
                <p class="description">Leave empty slots for simple chains. Example: OpenAI → Claude → (none)</p>
            </div>
            
            <div style="background: #f0f0f1; padding: 10px; margin-top: 15px; font-family: monospace; font-size: 12px;">
                <strong>Backend Implementation Needed:</strong><br>
                /* PSEUDOCODE - Developer Reference */<br>
                <br>
                function flosc_ai_chained_request($user_message, $context) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;$chain = get_option('flosc_ai_chain_providers', []);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;$response = $user_message;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;// First provider: Generate initial response<br>
                &nbsp;&nbsp;&nbsp;&nbsp;if (!empty($chain[0])) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$provider1 = new FLOSC_AI_Provider($chain[0]);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$response = $provider1->generate($user_message, $context);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;}<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;// Second provider: Review and enhance<br>
                &nbsp;&nbsp;&nbsp;&nbsp;if (!empty($chain[1]) && $response) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$provider2 = new FLOSC_AI_Provider($chain[1]);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$review_prompt = "Review this response and enhance it:\n\n" . $response;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$response = $provider2->generate($review_prompt, $context);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;}<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;// Third provider: Final polish (optional)<br>
                &nbsp;&nbsp;&nbsp;&nbsp;if (!empty($chain[2]) && $response) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$provider3 = new FLOSC_AI_Provider($chain[2]);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$polish_prompt = "Polish this response for clarity:\n\n" . $response;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$response = $provider3->generate($polish_prompt, $context);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;}<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<br>
                &nbsp;&nbsp;&nbsp;&nbsp;return $response;<br>
                }<br>
                <br>
                // Fallback logic<br>
                try {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;$response = $primary_provider->generate($message, $context);<br>
                } catch (Exception $e) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;// Primary failed, try chain<br>
                &nbsp;&nbsp;&nbsp;&nbsp;$response = flosc_ai_chained_request($message, $context);<br>
                }
            </div>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- SECTION 6: PHASE-SPECIFIC PROMPTS [READY] -->
<!-- ============================================ -->
<h3 style="margin-top: 40px;">Phase-Specific Instructions</h3>
<p class="description">Customize AI behavior for each FLOSC phase. These are appended to your base prompt.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_ai_prompt_freeline">Freeline Phase</label></th>
        <td>
            <textarea name="flosc_ai_prompt_freeline" id="flosc_ai_prompt_freeline" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_prompt_freeline', 'Encourage user to take the quiz. Be curious about their goals.')); ?></textarea>
            <p class="description">Visitors who haven't taken the quiz yet.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_ai_prompt_login">Login Phase</label></th>
        <td>
            <textarea name="flosc_ai_prompt_login" id="flosc_ai_prompt_login" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_prompt_login', 'Deliver free lesson based on quiz results. Build trust before presenting offer.')); ?></textarea>
            <p class="description">Post-quiz visitors and logged-in users.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_ai_prompt_offer">Offer Phase</label></th>
        <td>
            <textarea name="flosc_ai_prompt_offer" id="flosc_ai_prompt_offer" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_prompt_offer', 'Present personalized offer. Address objections. Show value specific to their quiz results.')); ?></textarea>
            <p class="description">Sales pitch mode.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_ai_prompt_sale">Sale Phase</label></th>
        <td>
            <textarea name="flosc_ai_prompt_sale" id="flosc_ai_prompt_sale" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_prompt_sale', 'Onboard user to content. Explain navigation. Build excitement for their purchase.')); ?></textarea>
            <p class="description">Post-purchase onboarding.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_ai_prompt_content">Content Phase</label></th>
        <td>
            <textarea name="flosc_ai_prompt_content" id="flosc_ai_prompt_content" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_prompt_content', 'Support learning journey. Answer questions. Encourage progress and celebrate wins.')); ?></textarea>
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
