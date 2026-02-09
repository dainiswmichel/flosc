<?php
/**
 * SSO Provider Base Class
 * 
 * Abstract base class for all social login providers.
 * Based on BuddyBoss SSO patterns with FLOSC-specific adaptations.
 * 
 * @package FLOSC
 * @subpackage SSO
 * @since 1.4.0
 */

namespace FLOSC\SSO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for SSO providers
 */
abstract class SSO_Provider_Base {
    
    /**
     * Provider ID (e.g., 'google', 'facebook', 'apple')
     * @var string
     */
    protected $provider_id;
    
    /**
     * Provider display name
     * @var string
     */
    protected $provider_name;
    
    /**
     * Provider icon class or URL
     * @var string
     */
    protected $provider_icon;
    
    /**
     * OAuth2 authorization endpoint
     * @var string
     */
    protected $auth_url;
    
    /**
     * OAuth2 token endpoint
     * @var string
     */
    protected $token_url;
    
    /**
     * User info endpoint
     * @var string
     */
    protected $user_info_url;
    
    /**
     * Required OAuth scopes
     * @var array
     */
    protected $scopes = array();
    
    /**
     * Client ID from provider settings
     * @var string
     */
    protected $client_id;
    
    /**
     * Client Secret from provider settings
     * @var string
     */
    protected $client_secret;
    
    /**
     * v1.4.9: Whether flow-specific credentials have been set
     * @var bool
     */
    protected $flow_credentials_set = false;
    
    /**
     * v1.4.9: Flow-specific enabled flag (null = not set, use global)
     * @var bool|null
     */
    protected $flow_enabled = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Get provider ID
     * @return string
     */
    public function get_id() {
        return $this->provider_id;
    }
    
    /**
     * Get provider display name
     * @return string
     */
    public function get_name() {
        return $this->provider_name;
    }
    
    /**
     * Get provider icon
     * @return string
     */
    public function get_icon() {
        return $this->provider_icon;
    }
    
    /**
     * Check if provider is enabled and configured
     * v1.4.9: Checks flow-specific enabled flag if set, otherwise falls back to global
     * @return bool
     */
    public function is_enabled() {
        if ($this->flow_enabled !== null) {
            return $this->flow_enabled && $this->is_configured();
        }
        $enabled = get_option("flosc_sso_{$this->provider_id}_enabled", false);
        return $enabled && $this->is_configured();
    }
    
    /**
     * Check if provider has valid credentials
     * @return bool
     */
    public function is_configured() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }
    
    /**
     * Load credentials from WordPress options
     */
    protected function load_credentials() {
        $this->client_id = get_option("flosc_sso_{$this->provider_id}_client_id", '');
        $this->client_secret = get_option("flosc_sso_{$this->provider_id}_client_secret", '');
    }
    
    /**
     * v1.4.9: Set flow-specific credentials (overrides global options)
     * Called at runtime when we know which flow triggered the SSO login.
     * 
     * @param string $client_id Flow-specific Client ID
     * @param string $client_secret Flow-specific Client Secret
     * @param bool $enabled Whether this provider is enabled for this flow
     */
    public function set_flow_credentials($client_id, $client_secret, $enabled = true) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->flow_enabled = (bool) $enabled;
        $this->flow_credentials_set = true;
    }
    
    /**
     * Get OAuth2 authorization URL
     * 
     * @param string $state CSRF protection state
     * @param string $redirect_uri Callback URL
     * @return string
     */
    public function get_authorization_url($state, $redirect_uri) {
        $params = array(
            'client_id'     => $this->client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->scopes),
            'state'         => $state,
        );
        
        // Allow providers to add custom parameters
        $params = $this->customize_auth_params($params);
        
        return $this->auth_url . '?' . http_build_query($params);
    }
    
    /**
     * Customize authorization parameters (override in subclasses)
     * 
     * @param array $params Default parameters
     * @return array Modified parameters
     */
    protected function customize_auth_params($params) {
        return $params;
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code
     * @param string $redirect_uri Callback URL
     * @return array|WP_Error Token data or error
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        $response = wp_remote_post($this->token_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ),
            'body' => array(
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirect_uri,
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            // v1.4.6: Handle both flat and nested error formats
            // Flat: { "error": "invalid_grant", "error_description": "Code expired" }
            // Nested (Facebook/Google): { "error": { "message": "...", "code": 190 } }
            if (is_array($body['error']) && isset($body['error']['message'])) {
                $error_msg = $body['error']['message'];
            } elseif (isset($body['error_description'])) {
                $error_msg = $body['error_description'];
            } else {
                $error_msg = is_string($body['error']) ? $body['error'] : 'Unknown error';
            }
            return new \WP_Error('token_error', $error_msg);
        }
        
        if (empty($body['access_token'])) {
            return new \WP_Error('token_error', 'No access token in response');
        }
        
        return $body;
    }
    
    /**
     * Get user info from provider
     * 
     * @param string $access_token OAuth access token
     * @param array  $token_data   Full token response (needed by Apple for id_token)
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
                isset($body['error_description']) ? $body['error_description'] : $body['error']
            );
        }
        
        return $this->normalize_user_data($body);
    }
    
    /**
     * Normalize user data to standard format
     * Override in each provider to map provider-specific fields
     * 
     * @param array $raw_data Raw user data from provider
     * @return array Normalized user data with standard keys
     */
    abstract protected function normalize_user_data($raw_data);
    
    /**
     * Get provider-specific user ID from raw data
     * 
     * @param array $raw_data Raw user data
     * @return string Provider user ID
     */
    abstract public function get_provider_user_id($raw_data);
    
    /**
     * Get provider settings fields for admin UI
     * 
     * @return array Settings fields configuration
     */
    public function get_settings_fields() {
        return array(
            array(
                'id'          => "flosc_sso_{$this->provider_id}_enabled",
                'title'       => __('Enable ' . $this->provider_name, 'flosc'),
                'type'        => 'checkbox',
                'default'     => false,
                'description' => sprintf(__('Allow users to log in with %s', 'flosc'), $this->provider_name),
            ),
            array(
                'id'          => "flosc_sso_{$this->provider_id}_client_id",
                'title'       => __('Client ID', 'flosc'),
                'type'        => 'text',
                'default'     => '',
                'description' => sprintf(__('%s OAuth Client ID', 'flosc'), $this->provider_name),
            ),
            array(
                'id'          => "flosc_sso_{$this->provider_id}_client_secret",
                'title'       => __('Client Secret', 'flosc'),
                'type'        => 'password',
                'default'     => '',
                'description' => sprintf(__('%s OAuth Client Secret', 'flosc'), $this->provider_name),
            ),
        );
    }
    
    /**
     * Get setup instructions for this provider
     * 
     * @return string HTML instructions
     */
    abstract public function get_setup_instructions();
    
    /**
     * Get the callback URL for this provider
     * 
     * @return string
     */
    public function get_callback_url() {
        return rest_url("flosc/v1/sso/callback/{$this->provider_id}");
    }
    
    /**
     * Get provider button HTML for login form
     * 
     * @return string HTML button
     */
    public function get_login_button_html() {
        $button_class = "flosc-sso-button flosc-sso-{$this->provider_id}";
        $icon = $this->get_icon();
        $name = $this->get_name();
        
        return sprintf(
            '<button type="button" class="%s" data-provider="%s">
                <span class="flosc-sso-icon">%s</span>
                <span class="flosc-sso-text">Continue with %s</span>
            </button>',
            esc_attr($button_class),
            esc_attr($this->provider_id),
            $icon,
            esc_html($name)
        );
    }
    
    /**
     * Get provider button color for CSS
     * 
     * @return array ['background' => '#xxx', 'text' => '#xxx']
     */
    public function get_button_colors() {
        // Override in subclasses for provider-specific colors
        return array(
            'background' => '#4285f4',
            'text'       => '#ffffff',
        );
    }
}
