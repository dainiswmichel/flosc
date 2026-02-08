<?php
/**
 * Facebook SSO Provider
 * 
 * Implements Facebook Login for FLOSC.
 * Uses Facebook Graph API v18.0.
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
     * Update periodically as versions are deprecated
     */
    const GRAPH_VERSION = 'v18.0';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->provider_id = 'facebook';
        $this->provider_name = 'Facebook';
        $this->provider_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
        
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
    public function get_user_info($access_token) {
        // Facebook requires explicit field requests
        $fields = 'id,name,first_name,last_name,email,picture.type(large)';
        
        $url = add_query_arg(array(
            'fields'       => $fields,
            'access_token' => $access_token,
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
        // Extract avatar from nested picture object
        $avatar = '';
        if (isset($raw_data['picture']['data']['url'])) {
            $avatar = $raw_data['picture']['data']['url'];
        }
        
        return array(
            'provider_id'    => $raw_data['id'] ?? '',
            'email'          => $raw_data['email'] ?? '',
            'email_verified' => !empty($raw_data['email']), // Facebook only returns verified emails
            'name'           => $raw_data['name'] ?? '',
            'first_name'     => $raw_data['first_name'] ?? '',
            'last_name'      => $raw_data['last_name'] ?? '',
            'avatar'         => $avatar,
            'locale'         => $raw_data['locale'] ?? '',
            'raw_data'       => $raw_data,
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
     * Verify app access token (optional security check)
     * 
     * @param string $access_token Token to verify
     * @return bool|WP_Error
     */
    public function verify_access_token($access_token) {
        $app_access_token = $this->client_id . '|' . $this->client_secret;
        
        $url = 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/debug_token';
        $url = add_query_arg(array(
            'input_token'  => $access_token,
            'access_token' => $app_access_token,
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
