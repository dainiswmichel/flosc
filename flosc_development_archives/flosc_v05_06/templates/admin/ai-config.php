<?php
/**
 * AI Configuration Admin Page
 * v04_04: Phase-aware AI system prompts
 */

if (!defined('ABSPATH')) exit;

// Get current base prompt
$base_prompt = get_option('flosc_ai_base_prompt', '');
if (empty($base_prompt)) {
    // Get default
    $base_prompt = "You are " . get_option('flosc_product_name', 'FLOSC App') . ", an AI pronunciation coach. Your mission is to help users improve their English pronunciation through personalized practice and encouragement. Be helpful, friendly, specific, and action-oriented. Always reference the user's quiz results and progress when available.";
}

// Get AI provider setting
$ai_provider = get_option('flosc_ai_provider', 'ivr');
$openai_key = get_option('flosc_openai_api_key', '');
$anthropic_key = get_option('flosc_anthropic_api_key', '');
$xai_key = get_option('flosc_xai_api_key', '');
?>

<div class="wrap">
    <h1>AI Configuration</h1>
    <p class="description">Configure your AI assistant's personality, phase-specific instructions, and API keys.</p>

    <!-- Setup Guide -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px; background: #f0f9ff; border-color: #3b82f6;">
        <h2 style="color: #1e40af;">🚀 Quick Start: Connect Your AI</h2>
        <p class="description" style="font-size: 14px; line-height: 1.6;">
            FLOSC works with multiple AI providers. Choose the one that fits your needs:
        </p>

        <div style="margin-top: 20px;">
            <h3 style="margin-bottom: 10px;">Option 1: IVR (Scripted) - FREE</h3>
            <p style="margin: 0 0 10px 20px; color: #666;">
                <strong>Best for:</strong> Testing, low-traffic sites, simple interactions<br>
                <strong>Cost:</strong> Free (no API key needed)<br>
                <strong>Setup:</strong> Select "IVR (Scripted)" below and click Save. That's it!
            </p>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="margin-bottom: 10px;">Option 2: OpenAI (GPT-4o-mini) - RECOMMENDED</h3>
            <p style="margin: 0 0 10px 20px; color: #666;">
                <strong>Best for:</strong> Most users, excellent quality, affordable pricing<br>
                <strong>Cost:</strong> ~$0.15 per 1,000 messages (very affordable)<br>
                <strong>Setup Steps:</strong>
            </p>
            <ol style="margin-left: 40px; line-height: 1.8;">
                <li>Go to <a href="https://platform.openai.com/api-keys" target="_blank" style="font-weight: 600;">platform.openai.com/api-keys</a></li>
                <li>Sign up or log in to your OpenAI account</li>
                <li>Click <strong>"Create new secret key"</strong></li>
                <li>Give it a name (e.g., "FLOSC Plugin") and click <strong>"Create"</strong></li>
                <li>Copy the API key (starts with <code>sk-proj-...</code>)</li>
                <li>Paste it in the "OpenAI API Key" field below</li>
                <li>Select "OpenAI (GPT-4o-mini)" as your AI Provider</li>
                <li>Click <strong>"Save AI Configuration"</strong></li>
                <li>Use the "Test Connection" button below to verify it works!</li>
            </ol>
            <p style="margin: 10px 0 0 20px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">
                <strong>💳 Billing:</strong> You'll need to add a payment method at <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank">OpenAI Billing</a>. Set a monthly budget limit (e.g., $5/month) for safety.
            </p>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="margin-bottom: 10px;">Option 3: Anthropic (Claude 3.5 Sonnet) - PREMIUM</h3>
            <p style="margin: 0 0 10px 20px; color: #666;">
                <strong>Best for:</strong> Advanced conversations, nuanced understanding<br>
                <strong>Cost:</strong> ~$3 per 1,000 messages (higher quality, higher cost)<br>
                <strong>Setup Steps:</strong>
            </p>
            <ol style="margin-left: 40px; line-height: 1.8;">
                <li>Go to <a href="https://console.anthropic.com/settings/keys" target="_blank" style="font-weight: 600;">console.anthropic.com/settings/keys</a></li>
                <li>Sign up or log in to your Anthropic account</li>
                <li>Click <strong>"Create Key"</strong></li>
                <li>Give it a name (e.g., "FLOSC") and click <strong>"Create Key"</strong></li>
                <li>Copy the API key (starts with <code>sk-ant-...</code>)</li>
                <li>Paste it in the "Anthropic API Key" field below</li>
                <li>Select "Anthropic (Claude 3.5 Sonnet)" as your AI Provider</li>
                <li>Click <strong>"Save AI Configuration"</strong></li>
                <li>Use the "Test Connection" button below to verify it works!</li>
            </ol>
            <p style="margin: 10px 0 0 20px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">
                <strong>💳 Billing:</strong> Add payment at <a href="https://console.anthropic.com/settings/billing" target="_blank">Anthropic Billing</a>. You get $5 free credit to start.
            </p>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="margin-bottom: 10px;">Option 4: xAI (Grok Beta)</h3>
            <p style="margin: 0 0 10px 20px; color: #666;">
                <strong>Best for:</strong> Early adopters, experimental features<br>
                <strong>Cost:</strong> Varies (currently in beta)<br>
                <strong>Setup Steps:</strong>
            </p>
            <ol style="margin-left: 40px; line-height: 1.8;">
                <li>Go to <a href="https://console.x.ai" target="_blank" style="font-weight: 600;">console.x.ai</a></li>
                <li>Sign up or log in to your xAI account</li>
                <li>Navigate to API keys section</li>
                <li>Create a new API key</li>
                <li>Copy the API key (starts with <code>xai-...</code>)</li>
                <li>Paste it in the "xAI API Key" field below</li>
                <li>Select "xAI (Grok Beta)" as your AI Provider</li>
                <li>Click <strong>"Save AI Configuration"</strong></li>
                <li>Use the "Test Connection" button below to verify it works!</li>
            </ol>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #dcfce7; border: 1px solid #10b981; border-radius: 4px;">
            <strong style="color: #065f46;">✓ Pro Tip:</strong> Start with OpenAI GPT-4o-mini. It's affordable, reliable, and works great for most use cases. You can always switch providers later!
        </div>
    </div>

    <!-- Connection Test -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2>Test AI Connection</h2>
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

    <form method="post" action="options.php">
        <?php settings_fields('flosc_ai_settings'); ?>

        <!-- AI Provider Selection -->
        <div class="card" style="max-width: 800px;">
            <h2>AI Provider</h2>
            <p class="description">Choose which AI provider to use for chat responses.</p>

            <table class="form-table">
                <tr>
                    <th scope="row">AI Provider</th>
                    <td>
                        <select name="flosc_ai_provider" class="regular-text">
                            <option value="ivr" <?php selected($ai_provider, 'ivr'); ?>>IVR (Scripted) - Free</option>
                            <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (GPT-4o-mini)</option>
                            <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic (Claude 3.5 Sonnet)</option>
                            <option value="xai" <?php selected($ai_provider, 'xai'); ?>>xAI (Grok Beta)</option>
                        </select>
                        <p class="description">
                            <strong>IVR:</strong> Free pattern-matching responses.<br>
                            <strong>OpenAI/Anthropic/xAI:</strong> Advanced AI with phase-aware instructions (requires API key).
                        </p>
                    </td>
                </tr>
            </table>

            <!-- API Keys -->
            <h3>API Keys</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="password" name="flosc_openai_api_key" value="<?php echo esc_attr($openai_key); ?>" class="large-text" placeholder="sk-...">
                        <p class="description">Get your API key at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Anthropic API Key</th>
                    <td>
                        <input type="password" name="flosc_anthropic_api_key" value="<?php echo esc_attr($anthropic_key); ?>" class="large-text" placeholder="sk-ant-...">
                        <p class="description">Get your API key at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">xAI API Key</th>
                    <td>
                        <input type="password" name="flosc_xai_api_key" value="<?php echo esc_attr($xai_key); ?>" class="large-text" placeholder="xai-...">
                        <p class="description">Get your API key at <a href="https://console.x.ai" target="_blank">console.x.ai</a></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Base System Prompt -->
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Base System Prompt</h2>
            <p class="description">This is the foundational personality and mission for your AI assistant. It applies to all phases.</p>

            <textarea name="flosc_ai_base_prompt" rows="8" class="large-text code" style="font-family: monospace; width: 100%;"><?php echo esc_textarea($base_prompt); ?></textarea>

            <p class="description" style="margin-top: 10px;">
                <strong>Tips:</strong><br>
                • Define the AI's role and personality<br>
                • Specify the tone and style of responses<br>
                • Include core principles that apply across all phases<br>
                • Use variables like {product_name} to personalize
            </p>
        </div>

        <!-- Phase-Specific Prompts Info -->
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Phase-Specific Instructions</h2>
            <p class="description">Phase-specific instructions are loaded from markdown files in the <code>/prompts/</code> directory.</p>

            <p><strong>How it works:</strong></p>
            <ol>
                <li>The system detects which FLOSC phase the user is in (freeline, login-prompt, login, offer, sale, or content)</li>
                <li>It loads the corresponding prompt file: <code>/prompts/{phase}-prompt.md</code></li>
                <li>The phase-specific instructions are combined with your base system prompt above</li>
                <li>Context variables (user score, name, status) are automatically added</li>
            </ol>

            <p><strong>Available phase prompt files:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>freeline-prompt.md</code> - Goal: Get visitors to take the quiz</li>
                <li><code>login-prompt-prompt.md</code> - Goal: Encourage account creation after quiz</li>
                <li><code>login-prompt.md</code> - Goal: Build trust and demonstrate value</li>
                <li><code>offer-prompt.md</code> - Goal: Sales pitch and objection handling</li>
                <li><code>sale-prompt.md</code> - Goal: Post-purchase onboarding</li>
                <li><code>content-prompt.md</code> - Goal: Ongoing support and retention</li>
            </ul>

            <p><strong>To customize:</strong> Edit the markdown files in your plugin's <code>/prompts/</code> directory via FTP or file manager.</p>
        </div>

        <!-- Context Variables Info -->
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Automatic Context Variables</h2>
            <p class="description">These variables are automatically passed to the AI with each request:</p>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>Variable</th>
                        <th>Description</th>
                        <th>Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>phase</code></td>
                        <td>Current FLOSC phase</td>
                        <td>freeline, login, offer, etc.</td>
                    </tr>
                    <tr>
                        <td><code>user_name</code></td>
                        <td>User's display name</td>
                        <td>John Doe</td>
                    </tr>
                    <tr>
                        <td><code>user_status</code></td>
                        <td>User account type</td>
                        <td>visitor, free, paid</td>
                    </tr>
                    <tr>
                        <td><code>quiz_score</code></td>
                        <td>Last quiz result</td>
                        <td>75%</td>
                    </tr>
                    <tr>
                        <td><code>free_lesson_delivered</code></td>
                        <td>Has user received free lesson</td>
                        <td>Yes/No</td>
                    </tr>
                    <tr>
                        <td><code>purchased</code></td>
                        <td>Has user purchased</td>
                        <td>Yes/No</td>
                    </tr>
                    <tr>
                        <td><code>product_name</code></td>
                        <td>Your product name</td>
                        <td>FLOSC App</td>
                    </tr>
                </tbody>
            </table>

            <p style="margin-top: 10px;"><strong>These are automatically included in the AI's context and don't need to be manually specified.</strong></p>
        </div>

        <?php submit_button('Save AI Configuration'); ?>
    </form>
</div>

<style>
.card {
    background: white;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
}

.card h2 {
    margin-top: 0;
}

.card h3 {
    margin-top: 20px;
    margin-bottom: 10px;
}

#test-status.success {
    color: #10b981;
    font-weight: bold;
}

#test-status.error {
    color: #dc2626;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#test-ai-connection').on('click', function() {
        const $button = $(this);
        const $loading = $('#test-loading');
        const $results = $('#test-results');
        const $status = $('#test-status');
        const $details = $('#test-details');

        // Disable button and show loading
        $button.prop('disabled', true);
        $loading.show();
        $results.hide();

        // Make AJAX call
        $.ajax({
            url: '<?php echo rest_url('flosc/v1/test-ai'); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                $loading.hide();
                $results.show();

                if (response.success) {
                    $status.html('✓ Connection successful!').removeClass('error').addClass('success');

                    let details = 'Provider: ' + response.provider + '\n';
                    details += 'Response time: ' + response.response_time + 'ms\n\n';
                    details += 'Test message sent: "' + response.test_message + '"\n\n';
                    details += 'AI response:\n' + response.ai_response;

                    $details.text(details);
                    $details.css({
                        'background': '#f0fdf4',
                        'border-color': '#10b981',
                        'color': '#166534'
                    });
                } else {
                    $status.html('✗ Connection failed').removeClass('success').addClass('error');

                    // Display the smart error message with next steps
                    const errorMessage = response.message || 'Unknown error';
                    $details.text(errorMessage);
                    $details.css({
                        'background': '#fef2f2',
                        'border-color': '#dc2626',
                        'color': '#991b1b',
                        'font-family': 'system-ui, -apple-system, sans-serif',
                        'font-size': '13px',
                        'line-height': '1.6'
                    });
                }

                $button.prop('disabled', false);
            },
            error: function(xhr) {
                $loading.hide();
                $results.show();
                $status.html('✗ Connection failed').removeClass('success').addClass('error');

                let errorMsg = 'Network error';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }

                $details.text('Error: ' + errorMsg);
                $button.prop('disabled', false);
            }
        });
    });
});
</script>
