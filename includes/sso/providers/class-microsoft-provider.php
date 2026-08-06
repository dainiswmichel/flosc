<?php
/**
 * Microsoft SSO Provider
 * 
 * Implements Microsoft Account login for FLOSC.
 * Uses Microsoft Identity Platform (Azure AD v2.0 endpoints).
 * Supports both personal Microsoft accounts and work/school accounts.
 * 
 * @package FLOSC
 * @subpackage SSO\Providers
 * @since 1.4.0
 */

namespace FLOSC\SSO\Providers;

use FLOSC\SSO\SSO_Provider_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Microsoft Provider Class
 */
class Microsoft_Provider extends SSO_Provider_Base {
    
    /**
     * Tenant - 'common' allows both personal and work accounts
     * Can be set to 'consumers' for personal only, 'organizations' for work only
     * Or a specific tenant ID for single-tenant apps
     */
    const TENANT = 'common';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->provider_id = 'microsoft';
        $this->provider_name = 'Microsoft';
        $this->provider_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 23 23"><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>';
        
        $tenant = self::TENANT;
        $this->auth_url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize";
        $this->token_url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";
        $this->user_info_url = 'https://graph.microsoft.com/v1.0/me';
        
        $this->scopes = array(
            'openid',
            'email',
            'profile',
            'User.Read',
        );
        
        parent::__construct();
    }
    
    /**
     * Customize authorization parameters for Microsoft
     * 
     * @param array $params Default parameters
     * @return array Modified parameters
     */
    protected function customize_auth_params($params) {
        // Microsoft prefers 'response_mode=query' for web apps
        $params['response_mode'] = 'query';
        
        // Prompt for account selection
        $params['prompt'] = 'select_account';
        
        return $params;
    }
    
    /**
     * Get user info from Microsoft Graph
     * 
     * @param string $access_token OAuth access token
     * @return array|WP_Error User data or error
     */
    public function get_user_info($access_token, $token_data = array()) {
        $response = wp_remote_get($this->user_info_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new \WP_Error(
                'user_info_error',
                isset($body['error']['message']) ? $body['error']['message'] : 'Failed to get user info'
            );
        }
        
        // Try to get profile photo
        $avatar = $this->get_profile_photo($access_token);
        $body['avatar_url'] = $avatar;
        
        return $this->normalize_user_data($body);
    }
    
    /**
     * Get user's profile photo from Microsoft Graph
     * 
     * @param string $access_token OAuth access token
     * @return string Photo URL or empty string
     */
    private function get_profile_photo($access_token) {
        $response = wp_remote_get('https://graph.microsoft.com/v1.0/me/photo/$value', array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return '';
        }
        
        // Convert binary image to base64 data URI
        $image_data = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        if ($image_data && $content_type) {
            return 'data:' . $content_type . ';base64,' . base64_encode($image_data);
        }
        
        return '';
    }
    
    /**
     * Normalize Microsoft user data to standard format
     * 
     * @param array $raw_data Raw user data from Microsoft Graph
     * @return array Normalized user data
     */
    protected function normalize_user_data($raw_data) {
        // Pass 8: field-sanitize after Graph JSON decode; do not keep raw blob.
        return array(
            'provider_id'    => sanitize_text_field((string) ($raw_data['id'] ?? '')),
            'email'          => sanitize_email((string) ($raw_data['mail'] ?? $raw_data['userPrincipalName'] ?? '')),
            'email_verified' => true, // Microsoft verifies emails
            'name'           => sanitize_text_field((string) ($raw_data['displayName'] ?? '')),
            'first_name'     => sanitize_text_field((string) ($raw_data['givenName'] ?? '')),
            'last_name'      => sanitize_text_field((string) ($raw_data['surname'] ?? '')),
            'avatar'         => esc_url_raw((string) ($raw_data['avatar_url'] ?? '')),
            'locale'         => sanitize_text_field((string) ($raw_data['preferredLanguage'] ?? '')),
            'job_title'      => sanitize_text_field((string) ($raw_data['jobTitle'] ?? '')),
            'raw_data'       => array(),
        );
    }
    
    /**
     * Get provider-specific user ID
     * 
     * @param array $raw_data Raw user data
     * @return string Provider user ID
     */
    public function get_provider_user_id($raw_data) {
        return $raw_data['id'] ?? '';
    }
    
    /**
     * Get button colors
     * 
     * @return array
     */
    public function get_button_colors() {
        return array(
            'background' => '#2f2f2f',
            'text'       => '#ffffff',
            'border'     => '#2f2f2f',
        );
    }
    
    /**
     * Get setup instructions for Microsoft Login
     * 
     * @return string HTML instructions
     */
    public function get_setup_instructions() {
        $callback_url = $this->get_callback_url();
        
        return '
        <div class="flosc-sso-setup-instructions">
            <h4>Microsoft Login Setup Instructions</h4>
            <ol>
                <li>Go to <a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank">Azure Portal - App Registrations</a></li>
                <li>Click "New registration":
                    <ul>
                        <li>Enter a name for your application</li>
                        <li>For "Supported account types", choose:<br>
                            <strong>"Accounts in any organizational directory and personal Microsoft accounts"</strong>
                        </li>
                        <li>For Redirect URI, select "Web" and enter:<br>
                            <code class="flosc-code-block">' . esc_html($callback_url) . '</code>
                        </li>
                    </ul>
                </li>
                <li>After registration, from the Overview page:
                    <ul>
                        <li>Copy <strong>Application (client) ID</strong> → Client ID above</li>
                    </ul>
                </li>
                <li>Go to "Certificates & secrets":
                    <ul>
                        <li>Click "New client secret"</li>
                        <li>Add a description and expiration</li>
                        <li>Copy the <strong>Value</strong> (not Secret ID) → Client Secret above</li>
                    </ul>
                </li>
                <li>Go to "API permissions":
                    <ul>
                        <li>Ensure these permissions exist:</li>
                        <li>Microsoft Graph → User.Read (Delegated)</li>
                        <li>openid, email, profile should be included</li>
                    </ul>
                </li>
            </ol>
            <p><strong>Note:</strong> Client secrets expire. Set a reminder to rotate them before expiration.</p>
        </div>';
    }
}
