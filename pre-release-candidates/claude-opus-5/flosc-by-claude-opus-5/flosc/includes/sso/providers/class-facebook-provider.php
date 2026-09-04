<?php
/**
 * Facebook SSO Provider
 * 
 * Implements Facebook Login for FLOSC.
 * Uses Facebook Graph API v19.0 (aligned with BuddyBoss).
 * 
 * Note: Facebook/Meta requires periodic API version updates.
 * Check https://developers.facebook.com/docs/graph-api/changelog
 * for deprecation notices (typically 2-year lifecycle).
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
 * Facebook Provider Class
 */
class Facebook_Provider extends SSO_Provider_Base {
    
    /**
     * Graph API version
     * v1.4.6: Aligned with BuddyBoss proven working version (v19.0)
     * Facebook deprecates versions ~2 years after release
     */
    const GRAPH_VERSION = 'v19.0';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->provider_id = 'facebook';
        $this->provider_name = 'Facebook';
        $this->provider_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#ffffff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
        
        $this->auth_url = 'https://www.facebook.com/' . self::GRAPH_VERSION . '/dialog/oauth';
        $this->token_url = 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/oauth/access_token';
        $this->user_info_url = 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/me';
        
        $this->scopes = array(
            'email',
            'public_profile',
        );
        
        parent::__construct();
    }
    
    /**
     * Customize authorization parameters for Facebook
     * 
     * @param array $params Default parameters
     * @return array Modified parameters
     */
    protected function customize_auth_params($params) {
        // Facebook uses comma-separated scopes, not spaces
        $params['scope'] = implode(',', $this->scopes);
        
        // Request re-authorization if needed
        $params['auth_type'] = 'rerequest';
        
        return $params;
    }
    
    /**
     * Get user info from Facebook
     * Facebook requires specifying fields explicitly
     * 
     * @param string $access_token OAuth access token
     * @return array|WP_Error User data or error
     */
    public function get_user_info($access_token, $token_data = array()) {
        // Facebook requires explicit field requests
        $fields = 'id,name,first_name,last_name,email,picture.type(large)';
        
        // v1.4.6: Send appsecret_proof on every Graph API request (BuddyBoss pattern)
        // This is required when "Require App Secret" is enabled in Facebook App settings
        $appsecret_proof = hash_hmac('sha256', $access_token, $this->client_secret);
        
        $url = add_query_arg(array(
            'fields'           => $fields,
            'access_token'     => $access_token,
            'appsecret_proof'  => $appsecret_proof,
        ), $this->user_info_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
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
        
        return $this->normalize_user_data($body);
    }
    
    /**
     * Normalize Facebook user data to standard format
     * 
     * @param array $raw_data Raw user data from Facebook
     * @return array Normalized user data
     */
    protected function normalize_user_data($raw_data) {
        // Pass 8: field-sanitize after provider JSON decode; do not keep raw blob.
        $avatar = '';
        if (isset($raw_data['picture']['data']) && is_array($raw_data['picture']['data'])) {
            // Skip default silhouette avatars — only use real profile pictures
            if (isset($raw_data['picture']['data']['is_silhouette']) && !$raw_data['picture']['data']['is_silhouette']) {
                $avatar = esc_url_raw((string) ($raw_data['picture']['data']['url'] ?? ''));
            }
        } elseif (!empty($raw_data['picture']) && is_string($raw_data['picture']) && filter_var($raw_data['picture'], FILTER_VALIDATE_URL)) {
            $avatar = esc_url_raw((string) $raw_data['picture']);
        }

        $first = (string) (!empty($raw_data['first_name']) ? $raw_data['first_name'] : ($raw_data['given_name'] ?? ''));
        $last  = (string) (!empty($raw_data['last_name']) ? $raw_data['last_name'] : ($raw_data['family_name'] ?? ''));
        $email = sanitize_email((string) ($raw_data['email'] ?? ''));

        return array(
            'provider_id'    => sanitize_text_field((string) ($raw_data['id'] ?? '')),
            'email'          => $email,
            'email_verified' => $email !== '', // Facebook only returns verified emails
            'name'           => sanitize_text_field((string) ($raw_data['name'] ?? '')),
            'first_name'     => sanitize_text_field($first),
            'last_name'      => sanitize_text_field($last),
            'avatar'         => $avatar,
            'locale'         => sanitize_text_field((string) ($raw_data['locale'] ?? '')),
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
            'background' => '#1877f2',
            'text'       => '#ffffff',
            'border'     => '#1877f2',
        );
    }
    
    /**
     * Get setup instructions for Facebook Login
     * 
     * @return string HTML instructions
     */
    public function get_setup_instructions() {
        $callback_url = $this->get_callback_url();
        
        return '
        <div class="flosc-sso-setup-instructions">
            <h4>Facebook Login Setup Instructions</h4>
            <ol>
                <li>Go to <a href="https://developers.facebook.com/apps/" target="_blank">Meta for Developers</a></li>
                <li>Create a new app or select an existing one:
                    <ul>
                        <li>Choose "Consumer" or "Business" app type</li>
                        <li>Fill in app details</li>
                    </ul>
                </li>
                <li>Add "Facebook Login" product to your app</li>
                <li>Go to Facebook Login → Settings:
                    <ul>
                        <li>Enable "Client OAuth Login"</li>
                        <li>Enable "Web OAuth Login"</li>
                        <li>Add Valid OAuth Redirect URI:</li>
                        <li><code class="flosc-code-block">' . esc_html($callback_url) . '</code></li>
                    </ul>
                </li>
                <li>From App Settings → Basic, copy:
                    <ul>
                        <li><strong>App ID</strong> → Client ID field above</li>
                        <li><strong>App Secret</strong> → Client Secret field above</li>
                    </ul>
                </li>
                <li>For production:
                    <ul>
                        <li>Complete App Review</li>
                        <li>Request "email" and "public_profile" permissions</li>
                        <li>Set App Mode to "Live"</li>
                    </ul>
                </li>
            </ol>
            <p><strong>Note:</strong> Facebook requires your app to pass App Review for public use. During development, you can add test users in App Roles.</p>
            <p><strong>API Version:</strong> This plugin uses Graph API ' . self::GRAPH_VERSION . '. Facebook typically supports versions for 2 years.</p>
        </div>';
    }
    
    /**
     * Exchange authorization code for access token
     * v1.4.6: Override to add long-lived token exchange (BuddyBoss pattern)
     * 
     * @param string $code Authorization code
     * @param string $redirect_uri Callback URL
     * @return array|WP_Error Token data or error
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        // Standard token exchange first
        $token_data = parent::exchange_code_for_token($code, $redirect_uri);
        
        if (is_wp_error($token_data)) {
            return $token_data;
        }
        
        // Add created timestamp
        $token_data['created'] = time();
        
        // Try to get long-lived token if current one expires soon (< 2 hours)
        if (isset($token_data['expires_in']) && ($token_data['created'] + $token_data['expires_in'] < time() + 7200)) {
            $long_lived = $this->request_long_lived_token($token_data['access_token']);
            if (!is_wp_error($long_lived)) {
                $token_data = $long_lived;
            }
        }
        
        return $token_data;
    }
    
    /**
     * Request long-lived access token from Facebook
     * v1.4.6: BuddyBoss pattern — exchanges short-lived token for ~60 day token
     * 
     * @param string $short_lived_token The short-lived access token
     * @return array|WP_Error Long-lived token data or error
     */
    private function request_long_lived_token($short_lived_token) {
        // v1.4.6: Use add_query_arg, NOT wp_remote_get body — GET request bodies are ignored by Facebook
        $url = add_query_arg(array(
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->client_id,
            'client_secret'     => $this->client_secret,
            'fb_exchange_token' => $short_lived_token,
        ), 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/oauth/access_token');
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error('long_lived_token_error', 'Failed to exchange for long-lived token');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!is_array($body) || empty($body['access_token'])) {
            return new \WP_Error('long_lived_token_error', 'Invalid response from long-lived token exchange');
        }
        
        $body['created'] = time();
        
        return $body;
    }
    
    /**
     * Verify app access token (optional security check)
     * 
     * @param string $access_token Token to verify
     * @return bool|WP_Error
     */
    public function verify_access_token($access_token) {
        $app_access_token = $this->client_id . '|' . $this->client_secret;
        
        $url = 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/debug_token';
        $url = add_query_arg(array(
            'input_token'      => $access_token,
            'access_token'     => $app_access_token,
            'appsecret_proof'  => hash_hmac('sha256', $app_access_token, $this->client_secret),
        ), $url);
        
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data']['is_valid']) || !$body['data']['is_valid']) {
            return new \WP_Error('invalid_token', 'Facebook access token is invalid');
        }
        
        // Verify app_id matches
        if (isset($body['data']['app_id']) && $body['data']['app_id'] !== $this->client_id) {
            return new \WP_Error('app_mismatch', 'Token belongs to a different app');
        }
        
        return true;
    }
}
