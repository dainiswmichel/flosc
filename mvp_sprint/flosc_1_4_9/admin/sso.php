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
$site_url = get_site_url();
$callback_base = $site_url . '/wp-json/flosc/v1/sso/callback/';

// Provider configurations
$providers = [
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
?>

<div class="flosc-sso-settings">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="margin-bottom: 4px;">🔐 SSO / Social Login Settings</h2>
            <p class="description" style="margin-top: 0;">Configure OAuth2 providers to enable social login in your FLOSC chat. Users can sign in with their existing accounts.</p>
        </div>
        <button type="submit" name="flosc_save" class="button button-primary" style="margin-top: 10px;">
            💾 Save SSO Settings
        </button>
    </div>
    
    <?php
    // v1.4.9: SSO settings are PER-FLOW, stored in the flow settings array
    $flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
    ?>
    <?php foreach ($providers as $provider_id => $provider): ?>
        <?php
        $is_enabled = !empty($flow_settings["sso_{$provider_id}_enabled"]);
        $client_id = $flow_settings["sso_{$provider_id}_client_id"] ?? '';
        $client_secret = $flow_settings["sso_{$provider_id}_client_secret"] ?? '';
        $callback_url = $callback_base . $provider_id;
        ?>
        
        <div class="flosc-sso-provider card" style="margin-bottom: 20px; padding: 20px; border-left: 4px solid <?php echo $is_enabled ? '#28a745' : '#ccc'; ?>;">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.5em;"><?php echo $provider['icon']; ?></span>
                <?php echo esc_html($provider['name']); ?>
                <?php if ($is_enabled && $client_id && $client_secret): ?>
                    <span style="background: #28a745; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: normal;">ENABLED</span>
                <?php endif; ?>
            </h3>
            
            <table class="form-table" style="margin-top: 0;">
                <tr>
                    <th scope="row">Enable <?php echo esc_html($provider['name']); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="flow_sso_<?php echo $provider_id; ?>_enabled" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>>
                            Allow users to sign in with <?php echo esc_html($provider['name']); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Client ID</th>
                    <td>
                        <input type="text" 
                               name="flow_sso_<?php echo $provider_id; ?>_client_id" 
                               value="<?php echo esc_attr($client_id); ?>" 
                               class="regular-text"
                               placeholder="<?php echo $provider_id === 'apple' ? 'Service ID (e.g., com.example.app)' : 'Your ' . $provider['name'] . ' Client/App ID'; ?>">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Client Secret</th>
                    <td>
                        <input type="password" 
                               name="flow_sso_<?php echo $provider_id; ?>_client_secret" 
                               value="<?php echo esc_attr($client_secret); ?>" 
                               class="regular-text"
                               placeholder="<?php echo $provider_id === 'apple' ? 'Leave empty - auto-generated from keys' : 'Your ' . $provider['name'] . ' Client Secret'; ?>"
                               <?php echo $provider_id === 'apple' ? 'readonly style="background: #f0f0f0;"' : ''; ?>>
                        <?php if ($provider_id === 'apple'): ?>
                            <p class="description">Apple client secrets are automatically generated from Team ID, Key ID, and Private Key below.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <?php if (isset($provider['extra_fields']) && in_array('team_id', $provider['extra_fields'])): ?>
                    <?php
                    $team_id = $flow_settings["sso_{$provider_id}_team_id"] ?? '';
                    $key_id = $flow_settings["sso_{$provider_id}_key_id"] ?? '';
                    $private_key = $flow_settings["sso_{$provider_id}_private_key"] ?? '';
                    ?>
                    <tr>
                        <th scope="row">Team ID</th>
                        <td>
                            <input type="text" 
                                   name="flow_sso_<?php echo $provider_id; ?>_team_id" 
                                   value="<?php echo esc_attr($team_id); ?>" 
                                   class="regular-text"
                                   placeholder="10-character Team ID (e.g., ABCDE12345)">
                            <p class="description">Found in the top right of your Apple Developer account</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Key ID</th>
                        <td>
                            <input type="text" 
                                   name="flow_sso_<?php echo $provider_id; ?>_key_id" 
                                   value="<?php echo esc_attr($key_id); ?>" 
                                   class="regular-text"
                                   placeholder="10-character Key ID">
                            <p class="description">Found in the key details page in Apple Developer</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Private Key</th>
                        <td>
                            <textarea name="flow_sso_<?php echo $provider_id; ?>_private_key" 
                                      rows="5" 
                                      class="large-text code"
                                      placeholder="-----BEGIN PRIVATE KEY-----&#10;Paste your .p8 file contents here&#10;-----END PRIVATE KEY-----"><?php echo esc_textarea($private_key); ?></textarea>
                            <p class="description">Contents of the .p8 file downloaded from Apple Developer. Keep this secure!</p>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <tr>
                    <th scope="row">Callback URL</th>
                    <td>
                        <code style="display: inline-block; padding: 8px 12px; background: #f5f5f5; border-radius: 4px; word-break: break-all;"><?php echo esc_html($callback_url); ?></code>
                        <button type="button" 
                                class="button button-small" 
                                onclick="navigator.clipboard.writeText('<?php echo esc_js($callback_url); ?>').then(() => { this.textContent = 'Copied!'; setTimeout(() => { this.textContent = 'Copy'; }, 2000); });">
                            Copy
                        </button>
                        <p class="description">Add this URL to your <?php echo esc_html($provider['name']); ?> app's authorized redirect URIs.</p>
                    </td>
                </tr>
            </table>
            
            <!-- Setup Instructions (Collapsible) -->
            <details style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                <summary style="cursor: pointer; font-weight: 600; color: #0073aa;">
                    📖 Setup Instructions for <?php echo esc_html($provider['name']); ?>
                </summary>
                <ol style="margin-top: 10px; padding-left: 20px;">
                    <?php foreach ($provider['instructions'] as $step): ?>
                        <li style="margin-bottom: 8px;"><?php echo wp_kses_post($step); ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!empty($provider['docs_url'])): ?>
                    <p style="margin-top: 10px;">
                        <a href="<?php echo esc_url($provider['docs_url']); ?>" target="_blank" class="button button-secondary button-small">
                            Open <?php echo esc_html($provider['name']); ?> Developer Console →
                        </a>
                    </p>
                <?php endif; ?>
            </details>
        </div>
    <?php endforeach; ?>
    
    <p class="submit" style="margin: 10px 0 20px;">
        <button type="submit" name="flosc_save" class="button button-primary button-large">
            💾 Save SSO Settings
        </button>
    </p>
    
    <!-- General SSO Info -->
    <div class="card" style="padding: 20px; background: #f0f7ff; border-left: 4px solid #0073aa;">
        <h3 style="margin-top: 0;">ℹ️ How SSO Works</h3>
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
    <div class="card" style="padding: 20px; margin-top: 20px;">
        <h3 style="margin-top: 0;">🔧 API Endpoints</h3>
        <table class="widefat" style="max-width: 600px;">
            <tr>
                <th>Endpoint</th>
                <th>URL</th>
            </tr>
            <tr>
                <td>Get Enabled Providers</td>
                <td><code><?php echo esc_html($site_url); ?>/wp-json/flosc/v1/sso/providers</code></td>
            </tr>
            <tr>
                <td>Initiate Login</td>
                <td><code><?php echo esc_html($site_url); ?>/wp-json/flosc/v1/sso/authorize/{provider}</code></td>
            </tr>
            <tr>
                <td>Callback (auto)</td>
                <td><code><?php echo esc_html($site_url); ?>/wp-json/flosc/v1/sso/callback/{provider}</code></td>
            </tr>
        </table>
    </div>
</div>

<style>
.flosc-sso-settings .card {
    max-width: 900px;
}
.flosc-sso-settings details[open] summary {
    margin-bottom: 10px;
}
.flosc-sso-settings .form-table th {
    width: 150px;
    padding: 10px 10px 10px 0;
}
.flosc-sso-settings .form-table td {
    padding: 10px 0;
}
</style>
