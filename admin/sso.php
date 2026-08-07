<?php
/**
 * FLOSC SSO / Social Login Settings
 * 
 * @package FLOSC
 * @subpackage Admin
 * @since v1.4.0
 * 
 * Provides admin UI for configuring OAuth2/SSO providers:
 * - Google
 * - Apple
 * - Facebook  
 * - Microsoft
 * - LinkedIn
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get callback base URL
$flosc_site_url = get_site_url();
$flosc_callback_base = $flosc_site_url . '/wp-json/flosc/v1/sso/callback/';

// Provider configurations
$flosc_providers = [
    'google' => [
        'name' => 'Google',
        'icon' => '🔵',
        'docs_url' => 'https://console.cloud.google.com/apis/credentials',
        'instructions' => [
            'Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>',
            'Create a new project or select existing',
            'Navigate to "APIs & Services" → "Credentials"',
            'Click "Create Credentials" → "OAuth Client ID"',
            'Select "Web application" as Application type',
            'Under "Authorized redirect URIs", add your callback URL (shown below)',
            'Copy the Client ID and Client Secret to the fields below',
            'Enable the "Google+ API" or "People API" in the API Library',
        ],
    ],
    'apple' => [
        'name' => 'Apple',
        'icon' => '🍎',
        'docs_url' => 'https://developer.apple.com/account/resources/identifiers/list',
        'instructions' => [
            'Go to <a href="https://developer.apple.com/account" target="_blank">Apple Developer Portal</a>',
            'Navigate to "Certificates, Identifiers & Profiles"',
            'Under "Identifiers", create a new App ID with "Sign in with Apple" enabled',
            'Under "Keys", create a new key with "Sign in with Apple" enabled',
            'Download the private key file (.p8) - this can only be downloaded once',
            'Note your Team ID (top right of developer portal)',
            'Note your Key ID (from the key you just created)',
            'Your Client ID (Service ID) should be in reverse domain notation (e.g., com.example.app)',
            'The Client Secret is automatically generated using your Team ID, Key ID, and Private Key',
        ],
        'extra_fields' => ['team_id', 'key_id', 'private_key'],
    ],
    'facebook' => [
        'name' => 'Facebook',
        'icon' => '📘',
        'docs_url' => 'https://developers.facebook.com/apps',
        'instructions' => [
            'Go to <a href="https://developers.facebook.com/apps" target="_blank">Facebook Developers</a>',
            'Create a new app or select existing (choose "Consumer" type)',
            'In the dashboard, add "Facebook Login" product',
            'Go to "Facebook Login" → "Settings"',
            'Add your callback URL to "Valid OAuth Redirect URIs"',
            'Go to "Settings" → "Basic" to find your App ID and App Secret',
            'Switch the app to "Live" mode when ready for production',
            'Note: Users must have roles assigned to test in development mode',
        ],
    ],
    'microsoft' => [
        'name' => 'Microsoft',
        'icon' => '🪟',
        'docs_url' => 'https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
        'instructions' => [
            'Go to <a href="https://portal.azure.com/" target="_blank">Azure Portal</a>',
            'Navigate to "Azure Active Directory" → "App registrations"',
            'Click "New registration"',
            'Set account type (personal accounts, work accounts, or both)',
            'Add your callback URL as a "Web" redirect URI',
            'After creation, note the "Application (client) ID"',
            'Go to "Certificates & secrets" → "New client secret"',
            'Copy the secret value immediately (it\'s only shown once)',
        ],
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'icon' => '💼',
        'docs_url' => 'https://www.linkedin.com/developers/apps',
        'instructions' => [
            'Go to <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developers</a>',
            'Create a new app or select existing',
            'Go to the "Auth" tab',
            'Under "OAuth 2.0 settings", add your callback URL',
            'Request access to "Sign In with LinkedIn using OpenID Connect" product',
            'Copy your Client ID and Client Secret from the "Auth" tab',
            'Note: API access requires app verification for some features',
        ],
    ],
];

// v1.4.9: SSO settings are PER-FLOW, stored in the flow settings array
$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_selected_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_sso_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_selected_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-sso';
$flosc_flow_display_name = trim((string)($flosc_flow_settings['identity']['name'] ?? ''));
if ($flosc_flow_display_name === '') {
    $flosc_flow_display_name = trim((string)($flosc_flow_settings['name'] ?? ''));
}
if ($flosc_flow_display_name === '') {
    $flosc_flow_display_name = $flosc_selected_ivr;
}
$flosc_current_flow_id = $flosc_selected_ivr ? sanitize_key(pathinfo($flosc_selected_ivr, PATHINFO_FILENAME)) : '';
?>

<div class="flosc-sso-settings">
    <div class="flosc-sso-docs-wrap">
        <a href="<?php echo esc_url($flosc_sso_docs_url); ?>" class="flosc-sso-docs-link">Docs</a>
    </div>
    <div class="flosc-sso-header">
        <div>
            <h2 class="flosc-sso-title">🔐 SSO / Social Login Settings</h2>
            <p class="description flosc-sso-subtitle">Configure OAuth2 providers to enable social login in your FLOSC chat. Users can sign in with their existing accounts.</p>
        </div>
        <button type="submit" name="flosc_save" class="button button-primary flosc-sso-save-top">
            💾 Save SSO Settings for <?php echo esc_html($flosc_flow_display_name); ?>
        </button>
    </div>
    
    <?php
    // Build the flow-based fallback app URL used when state.redirect_to is missing.
    $flosc_configured_post_login_redirect = trim((string)($flosc_flow_settings['sso_post_login_redirect_url'] ?? ''));
    if (!empty($flosc_configured_post_login_redirect) && wp_http_validate_url($flosc_configured_post_login_redirect)) {
        $flosc_flow_fallback_url = $flosc_configured_post_login_redirect;
        $flosc_redirect_source = 'flow setting: Post-login fallback URL';
    } else {
    $flosc_configured_domain = trim((string)($flosc_flow_settings['custom_domain'] ?? ($flosc_flow_settings['domain'] ?? '')));
    $flosc_configured_domain = preg_replace('#^https?://#i', '', $flosc_configured_domain);
    $flosc_configured_domain = trim($flosc_configured_domain, " \t\n\r\0\x0B/");
    $flosc_flow_slug = trim((string)($flosc_flow_settings['slug'] ?? ''), '/');

    if ($flosc_flow_slug === 'chat') {
        $flosc_flow_fallback_url = home_url('/chat');
        $flosc_redirect_source = '/chat slug shortcut';
    } elseif (!empty($flosc_configured_domain)) {
        $flosc_flow_fallback_url = 'https://' . $flosc_configured_domain . '/';
        $flosc_redirect_source = 'flow custom domain';
    } elseif (!empty($flosc_flow_slug)) {
        $flosc_flow_fallback_url = home_url('/' . $flosc_flow_slug . '/');
        $flosc_redirect_source = 'flow slug';
    } else {
        $flosc_flow_fallback_url = home_url('/');
        $flosc_redirect_source = 'site home fallback';
    }
    }
    ?>

    <div class="card flosc-sso-flow-card">
        <h3 class="flosc-sso-flow-title">Flow Redirect Context</h3>
        <p class="flosc-sso-flow-row"><strong>IVR file:</strong> <code><?php echo esc_html($flosc_selected_ivr ?: '(none selected)'); ?></code></p>
        <p class="flosc-sso-flow-row"><strong>flow_id parameter:</strong> <code><?php echo esc_html($flosc_current_flow_id ?: '(none)'); ?></code></p>
        <p class="flosc-sso-flow-row"><strong>Runtime redirect_to (primary):</strong> <code>window.location.href</code> from chat page at click-time</p>
        <p class="flosc-sso-flow-row"><strong>Configured Post-login redirect URL:</strong>
            <input type="url"
                   name="flow_sso_post_login_redirect_url"
                   value="<?php echo esc_attr($flosc_flow_settings['sso_post_login_redirect_url'] ?? ''); ?>"
                   class="regular-text flosc-sso-post-login-url"
                   placeholder="https://flosc.ai/">
        </p>
        <p class="description flosc-sso-flow-row">Used only if OAuth state has no <code>redirect_to</code>. Leave empty to use flow domain/slug fallback.</p>
        <p class="flosc-sso-flow-row"><strong>Fallback app URL (if state has no redirect_to):</strong> <code><?php echo esc_html($flosc_flow_fallback_url); ?></code></p>
        <p class="flosc-sso-flow-row flosc-sso-flow-row-last"><strong>Fallback source:</strong> <?php echo esc_html($flosc_redirect_source); ?></p>
    </div>

    <?php foreach ($flosc_providers as $flosc_provider_id => $flosc_provider): ?>
        <?php
        $flosc_is_enabled = !empty($flosc_flow_settings["sso_{$flosc_provider_id}_enabled"]);
        $flosc_client_id = $flosc_flow_settings["sso_{$flosc_provider_id}_client_id"] ?? '';
        $flosc_client_secret = $flosc_flow_settings["sso_{$flosc_provider_id}_client_secret"] ?? '';
        $flosc_callback_url = $flosc_callback_base . $flosc_provider_id;
        $flosc_authorize_url_example = add_query_arg(
            [
                'flow_id' => $flosc_current_flow_id,
                'redirect_to' => $flosc_flow_fallback_url,
            ],
            $flosc_site_url . '/wp-json/flosc/v1/sso/authorize/' . $flosc_provider_id
        );
        ?>
        
        <div class="flosc-sso-provider card <?php echo esc_attr( $flosc_is_enabled ? 'flosc-sso-provider--enabled' : 'flosc-sso-provider--disabled' ); ?>">
            <h3 class="flosc-sso-provider-title">
            <span class="flosc-sso-provider-icon"><?php echo esc_html($flosc_provider['icon']); ?></span>
                <?php echo esc_html($flosc_provider['name']); ?>
                <?php if ($flosc_is_enabled && $flosc_client_id && $flosc_client_secret): ?>
                    <span class="flosc-sso-enabled-badge">ENABLED</span>
                <?php endif; ?>
            </h3>
            
            <table class="form-table flosc-sso-form-table">
                <tr>
                    <th scope="row">Enable <?php echo esc_html($flosc_provider['name']); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                name="flow_sso_<?php echo esc_attr($flosc_provider_id); ?>_enabled" 
                                   value="1" 
                                   <?php checked($flosc_is_enabled, true); ?>>
                            Allow users to sign in with <?php echo esc_html($flosc_provider['name']); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Client ID</th>
                    <td>
                           <input type="text" 
                               name="flow_sso_<?php echo esc_attr($flosc_provider_id); ?>_client_id" 
                               value="<?php echo esc_attr($flosc_client_id); ?>" 
                               class="regular-text"
                               placeholder="<?php echo esc_attr($flosc_provider_id === 'apple' ? 'Service ID (e.g., com.example.app)' : 'Your ' . $flosc_provider['name'] . ' Client/App ID'); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Client Secret</th>
                    <td>
                           <input type="password" 
                               name="flow_sso_<?php echo esc_attr($flosc_provider_id); ?>_client_secret" 
                               value="<?php echo esc_attr($flosc_client_secret); ?>" 
                               class="regular-text <?php echo esc_attr( $flosc_provider_id === 'apple' ? 'flosc-sso-apple-secret' : '' ); ?>"
                               placeholder="<?php echo esc_attr($flosc_provider_id === 'apple' ? 'Leave empty - auto-generated from keys' : 'Your ' . $flosc_provider['name'] . ' Client Secret'); ?>"
                               <?php if ($flosc_provider_id === 'apple') : ?>readonly<?php endif; ?>>
                        <?php if ($flosc_provider_id === 'apple'): ?>
                            <p class="description">Apple client secrets are automatically generated from Team ID, Key ID, and Private Key below.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <?php if (isset($flosc_provider['extra_fields']) && in_array('team_id', $flosc_provider['extra_fields'])): ?>
                    <?php
                    $flosc_team_id = $flosc_flow_settings["sso_{$flosc_provider_id}_team_id"] ?? '';
                    $flosc_key_id = $flosc_flow_settings["sso_{$flosc_provider_id}_key_id"] ?? '';
                    $flosc_private_key = $flosc_flow_settings["sso_{$flosc_provider_id}_private_key"] ?? '';
                    ?>
                    <tr>
                        <th scope="row">Team ID</th>
                        <td>
                            <input type="text" 
                                name="flow_sso_<?php echo esc_attr($flosc_provider_id); ?>_team_id" 
                                   value="<?php echo esc_attr($flosc_team_id); ?>" 
                                   class="regular-text"
                                   placeholder="10-character Team ID (e.g., ABCDE12345)">
                            <p class="description">Found in the top right of your Apple Developer account</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Key ID</th>
                        <td>
                            <input type="text" 
                                name="flow_sso_<?php echo esc_attr($flosc_provider_id); ?>_key_id" 
                                   value="<?php echo esc_attr($flosc_key_id); ?>" 
                                   class="regular-text"
                                   placeholder="10-character Key ID">
                            <p class="description">Found in the key details page in Apple Developer</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Private Key</th>
                        <td>
                            <textarea name="flow_sso_<?php echo esc_attr($flosc_provider_id); ?>_private_key" 
                                      rows="5" 
                                      class="large-text code"
                                      placeholder="-----BEGIN PRIVATE KEY-----&#10;Paste your .p8 file contents here&#10;-----END PRIVATE KEY-----"><?php echo esc_textarea($flosc_private_key); ?></textarea>
                            <p class="description">Contents of the .p8 file downloaded from Apple Developer. Keep this secure!</p>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <tr>
                    <th scope="row">Callback URL</th>
                    <td>
                        <code class="flosc-sso-code-url"><?php echo esc_html($flosc_callback_url); ?></code>
                        <button type="button" 
                                class="button button-small"
                                data-flosc-action="copy-text"
                                data-copy-text="<?php echo esc_attr($flosc_callback_url); ?>">
                            Copy
                        </button>
                        <p class="description">Add this URL to your <?php echo esc_html($flosc_provider['name']); ?> app's authorized redirect URIs.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Authorize URL (Diagnostic Example)</th>
                    <td>
                        <code class="flosc-sso-code-url"><?php echo esc_html($flosc_authorize_url_example); ?></code>
                        <button type="button"
                                class="button button-small"
                                data-flosc-action="copy-text"
                                data-copy-text="<?php echo esc_attr($flosc_authorize_url_example); ?>">
                            Copy
                        </button>
                        <p class="description">Diagnostic URL with selected <code>flow_id</code>. Production chat uses the current page URL as <code>redirect_to</code> at click-time.</p>
                    </td>
                </tr>
            </table>
            
            <!-- Setup Instructions (Collapsible) -->
            <details class="flosc-sso-setup-details">
                <summary class="flosc-sso-setup-summary">
                    📖 Setup Instructions for <?php echo esc_html($flosc_provider['name']); ?>
                </summary>
                <ol class="flosc-sso-setup-list">
                    <?php foreach ($flosc_provider['instructions'] as $flosc_step): ?>
                        <li class="flosc-sso-setup-item"><?php echo wp_kses_post($flosc_step); ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!empty($flosc_provider['docs_url'])): ?>
                    <p class="flosc-sso-setup-link-wrap">
                        <a href="<?php echo esc_url($flosc_provider['docs_url']); ?>" target="_blank" class="button button-secondary button-small">
                            Open <?php echo esc_html($flosc_provider['name']); ?> Developer Console →
                        </a>
                    </p>
                <?php endif; ?>
            </details>

            <!-- v1.5.0: Inline Connection Test -->
            <div class="flosc-sso-test">
                <div class="flosc-sso-test-head">
                    <button type="button" 
                            class="button flosc-test-connection-btn" 
                            data-provider="<?php echo esc_attr($flosc_provider_id); ?>"
                            >
                        🔌 Test Connection
                    </button>
                    <span class="flosc-test-status" id="flosc-test-status-<?php echo esc_attr($flosc_provider_id); ?>">
                        Click to verify your <?php echo esc_html($flosc_provider['name']); ?> credentials without leaving this page
                    </span>
                </div>
                <div class="flosc-test-results" id="flosc-test-results-<?php echo esc_attr($flosc_provider_id); ?>">
                    <!-- Results injected by JS -->
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <p class="submit flosc-sso-submit-wrap">
        <button type="submit" name="flosc_save" class="button button-primary flosc-sso-save-bottom">
            💾 Save SSO Settings for <?php echo esc_html($flosc_flow_display_name); ?>
        </button>
    </p>
    
    <!-- General SSO Info -->
    <div class="card flosc-sso-info-card">
        <h3 class="flosc-sso-card-title">ℹ️ How SSO Works</h3>
        <p>When a user clicks a social login button in the FLOSC chat:</p>
        <ol>
            <li>They're redirected to the provider's login page</li>
            <li>After authenticating, they're redirected back to your site</li>
            <li>FLOSC creates or links their WordPress account automatically</li>
            <li>Their chat session continues with full access</li>
        </ol>
        <p><strong>Account Linking:</strong> If a user already has an account with the same email, their social login will be linked to the existing account.</p>
    </div>
    
    <!-- Test Endpoints -->
    <div class="card flosc-sso-endpoints-card">
        <h3 class="flosc-sso-card-title">🔧 API Endpoints</h3>
        <table class="widefat flosc-sso-endpoints-table">
            <tr>
                <th>Endpoint</th>
                <th>URL</th>
            </tr>
            <tr>
                <td>Get Enabled Providers</td>
                <td><code><?php echo esc_html($flosc_site_url); ?>/wp-json/flosc/v1/sso/providers</code></td>
            </tr>
            <tr>
                <td>Initiate Login</td>
                <td><code><?php echo esc_html($flosc_site_url); ?>/wp-json/flosc/v1/sso/authorize/{provider}</code></td>
            </tr>
            <tr>
                <td>Callback (auto)</td>
                <td><code><?php echo esc_html($flosc_site_url); ?>/wp-json/flosc/v1/sso/callback/{provider}</code></td>
            </tr>
        </table>
    </div>
</div>

<!-- Styles in assets/css/flosc-admin.css -->

<!-- v1.5.0: Connection Test AJAX -->
<?php ob_start(); ?>
(function() {
    var nonce = '<?php echo esc_js(wp_create_nonce('flosc_test_sso')); ?>';
    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    // v1.5.0: Pass flow_id so test reads per-flow SSO settings
    var flowId = '<?php echo esc_js(sanitize_key(pathinfo($GLOBALS['flosc_current_ivr'] ?? '', PATHINFO_FILENAME))); ?>';

    document.querySelectorAll('.flosc-test-connection-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var provider = this.dataset.provider;
            var statusEl = document.getElementById('flosc-test-status-' + provider);
            var resultsEl = document.getElementById('flosc-test-results-' + provider);

            // Disable button, show loading
            btn.disabled = true;
            btn.textContent = '⏳ Testing...';
            statusEl.textContent = 'Connecting to ' + provider + '...';
            statusEl.style.color = '#666';
            resultsEl.style.display = 'none';
            resultsEl.innerHTML = '';

            var formData = new FormData();
            formData.append('action', 'flosc_test_sso_connection');
            formData.append('nonce', nonce);
            formData.append('provider', provider);
            formData.append('flow_id', flowId);

            fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    btn.disabled = false;
                    btn.textContent = '🔌 Test Connection';

                    if (!resp.success) {
                        statusEl.textContent = '❌ Broken';
                        statusEl.style.color = '#d63638';
                        return;
                    }

                    var data = resp.data;
                    var html = '';

                    data.checks.forEach(function(check) {
                        var cls = check.pass ? 'pass' : 'fail';
                        var icon = check.pass ? '✅' : '❌';
                        html += '<div class="flosc-test-check ' + cls + '">'
                            + '<span class="flosc-test-check-icon">' + icon + '</span>'
                            + '<span class="flosc-test-check-label">' + escHtml(check.label) + '</span>'
                            + '<span class="flosc-test-check-detail">' + escHtml(check.detail) + '</span>'
                            + '</div>';
                    });

                    if (data.all_pass) {
                        html += '<div class="flosc-test-summary all-pass">✅ Live</div>';
                        statusEl.textContent = '✅ Live';
                        statusEl.style.color = '#00a32a';
                    } else {
                        html += '<div class="flosc-test-summary has-fail">❌ Broken</div>';
                        statusEl.textContent = '❌ Broken';
                        statusEl.style.color = '#d63638';
                    }

                    resultsEl.innerHTML = html;
                    resultsEl.style.display = 'block';
                })
                .catch(function(err) {
                    btn.disabled = false;
                    btn.textContent = '🔌 Test Connection';
                    statusEl.textContent = '❌ Network error: ' + err.message;
                    statusEl.style.color = '#d63638';
                });
        });
    });

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
