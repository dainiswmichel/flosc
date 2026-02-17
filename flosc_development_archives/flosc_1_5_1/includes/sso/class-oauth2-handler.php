<?php
/**
 * OAuth2 Flow Handler
 * 
 * Manages the OAuth2 authentication flow for all SSO providers.
 * Handles redirects, callbacks, state verification, and error handling.
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
 * OAuth2 Flow Handler Class
 */
class OAuth2_Handler {
    
    /**
     * State transient prefix
     */
    const STATE_PREFIX = 'flosc_sso_state_';
    
    /**
     * State expiration (10 minutes)
     */
    const STATE_EXPIRATION = 600;
    
    /**
     * SSO Manager reference
     * @var SSO_Manager
     */
    private $manager;
    
    /**
     * Constructor
     * 
     * @param SSO_Manager $manager SSO Manager instance
     */
    public function __construct($manager) {
        $this->manager = $manager;
    }
    
    /**
     * Initialize OAuth2 routes
     */
    public function init() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes for OAuth2 flow
     */
    public function register_routes() {
        // Initiate OAuth flow
        register_rest_route('flosc/v1', '/sso/authorize/(?P<provider>[a-z_]+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_authorize'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'provider' => array(
                    'required'          => true,
                    'validate_callback' => array($this, 'validate_provider'),
                ),
                'redirect_to' => array(
                    'required' => false,
                    'default'  => '',
                ),
                // v1.4.9: Flow context for per-flow SSO credentials
                'flow_id' => array(
                    'required' => false,
                    'default'  => '',
                ),
            ),
        ));
        
        // OAuth callback (GET for most providers, POST for Apple form_post)
        register_rest_route('flosc/v1', '/sso/callback/(?P<provider>[a-z_]+)', array(
            'methods'             => 'GET, POST',
            'callback'            => array($this, 'handle_callback'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'provider' => array(
                    'required'          => true,
                    'validate_callback' => array($this, 'validate_provider'),
                ),
                'code' => array(
                    'required' => false,
                ),
                'state' => array(
                    'required' => false,
                ),
                'error' => array(
                    'required' => false,
                ),
                'error_description' => array(
                    'required' => false,
                ),
            ),
        ));
        
        // Get available providers (for frontend)
        register_rest_route('flosc/v1', '/sso/providers', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_providers'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Validate provider parameter
     * 
     * @param string $provider Provider ID
     * @return bool
     */
    public function validate_provider($provider) {
        return $this->manager->has_provider($provider);
    }
    
    /**
     * Handle OAuth authorization redirect
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_authorize($request) {
        $provider_id = $request->get_param('provider');
        $redirect_to = $request->get_param('redirect_to');
        $flow_id = $request->get_param('flow_id');
        
        $provider = $this->manager->get_provider($provider_id);
        
        if (!$provider) {
            return new \WP_Error('invalid_provider', 'Invalid SSO provider', array('status' => 400));
        }
        
        // v1.4.9: Load per-flow credentials if flow_id is provided
        if (!empty($flow_id)) {
            $flow_settings_key = 'flosc_flow_' . sanitize_key($flow_id);
            $flow_settings = get_option($flow_settings_key, []);
            $flow_client_id = $flow_settings["sso_{$provider_id}_client_id"] ?? '';
            $flow_client_secret = $flow_settings["sso_{$provider_id}_client_secret"] ?? '';
            $flow_enabled = !empty($flow_settings["sso_{$provider_id}_enabled"]);
            
            if (!empty($flow_client_id)) {
                // v1.5.0: Apple has extra fields (team_id, key_id, private_key)
                if ($provider_id === 'apple' && method_exists($provider, 'set_flow_apple_credentials')) {
                    $provider->set_flow_apple_credentials(
                        $flow_client_id,
                        $flow_client_secret,
                        $flow_enabled,
                        $flow_settings['sso_apple_team_id'] ?? '',
                        $flow_settings['sso_apple_key_id'] ?? '',
                        $flow_settings['sso_apple_private_key'] ?? ''
                    );
                } elseif (!empty($flow_client_secret)) {
                    $provider->set_flow_credentials($flow_client_id, $flow_client_secret, $flow_enabled);
                }
            }
        }
        
        if (!$provider->is_enabled()) {
            return new \WP_Error('provider_disabled', 'This login method is not available', array('status' => 400));
        }
        
        // Prevent caching of this endpoint (critical for state transients)
        nocache_headers();
        
        // Generate state for CSRF protection
        // v1.4.9: Include flow_id in state for per-flow credential loading on callback
        $state = $this->generate_state($provider_id, $redirect_to, $flow_id);
        
        // Get authorization URL
        $auth_url = $provider->get_authorization_url($state, $provider->get_callback_url());
        
        // Redirect to provider
        wp_redirect($auth_url);
        exit;
    }
    
    /**
     * Handle OAuth callback
     * 
     * @param WP_REST_Request $request
     * @return void Redirects on completion
     */
    public function handle_callback($request) {
        // Prevent caching of callback endpoint
        nocache_headers();
        
        $provider_id = $request->get_param('provider');
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');
        
        // Handle provider errors
        if ($error) {
            $error_description = $request->get_param('error_description') ?? $error;
            $this->redirect_with_error($error_description);
            return;
        }
        
        // Verify state
        $state_data = $this->verify_state($state);
        if (!$state_data) {
            $this->redirect_with_error('Invalid or expired authentication state. Please try again.');
            return;
        }
        
        // Verify provider matches
        if ($state_data['provider'] !== $provider_id) {
            $this->redirect_with_error('Provider mismatch. Please try again.');
            return;
        }
        
        $provider = $this->manager->get_provider($provider_id);
        if (!$provider) {
            $this->redirect_with_error('Invalid provider.');
            return;
        }
        
        // v1.4.9: Load per-flow credentials from state before token exchange
        $flow_id = $state_data['flow_id'] ?? '';
        if (!empty($flow_id)) {
            $flow_settings_key = 'flosc_flow_' . sanitize_key($flow_id);
            $flow_settings = get_option($flow_settings_key, []);
            $flow_client_id = $flow_settings["sso_{$provider_id}_client_id"] ?? '';
            $flow_client_secret = $flow_settings["sso_{$provider_id}_client_secret"] ?? '';
            $flow_enabled = !empty($flow_settings["sso_{$provider_id}_enabled"]);
            
            if (!empty($flow_client_id)) {
                // v1.5.0: Apple has extra fields (team_id, key_id, private_key)
                if ($provider_id === 'apple' && method_exists($provider, 'set_flow_apple_credentials')) {
                    $provider->set_flow_apple_credentials(
                        $flow_client_id,
                        $flow_client_secret,
                        $flow_enabled,
                        $flow_settings['sso_apple_team_id'] ?? '',
                        $flow_settings['sso_apple_key_id'] ?? '',
                        $flow_settings['sso_apple_private_key'] ?? ''
                    );
                } elseif (!empty($flow_client_secret)) {
                    $provider->set_flow_credentials($flow_client_id, $flow_client_secret, $flow_enabled);
                }
            }
        }
        
        // Exchange code for token
        $token_data = $provider->exchange_code_for_token($code, $provider->get_callback_url());
        
        if (is_wp_error($token_data)) {
            $this->redirect_with_error('Authentication failed: ' . $token_data->get_error_message());
            return;
        }
        
        // Get user info
        // v1.4.6: Pass full token_data for providers that need id_token (Apple)
        $access_token = isset($token_data['access_token']) ? $token_data['access_token'] : '';
        $user_data = $provider->get_user_info($access_token, $token_data);
        
        if (is_wp_error($user_data)) {
            $this->redirect_with_error('Failed to get user info: ' . $user_data->get_error_message());
            return;
        }
        
        // Process the login
        $result = $this->process_sso_login($provider, $user_data, $token_data);
        
        if (is_wp_error($result)) {
            $this->redirect_with_error($result->get_error_message());
            return;
        }
        
        // Success - redirect to intended destination or home
        $redirect_to = !empty($state_data['redirect_to']) ? $state_data['redirect_to'] : home_url();
        
        // v1.5.0: If redirect_to is a wp-login.php URL, extract the inner redirect_to
        // This prevents SSO users from bouncing through wp-login.php → profile page
        if (strpos($redirect_to, 'wp-login.php') !== false) {
            $parsed = wp_parse_url($redirect_to);
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $params);
                if (!empty($params['redirect_to'])) {
                    $redirect_to = $params['redirect_to'];
                }
            }
        }
        
        // v1.5.0: Resolve slug-based URLs to custom domain if configured
        // e.g. dainis.net/flosc/ → flosc.ai/
        if (function_exists('flosc')) {
            $app_slug = get_option('flosc_app_slug', 'flosc');
            if (strpos($redirect_to, '/' . $app_slug) !== false) {
                $custom_url = flosc()->get_app_url();
                if ($custom_url && strpos($custom_url, $app_slug) === false) {
                    // Custom domain is configured and differs from slug URL
                    $redirect_to = $custom_url;
                }
            }
        }
        
        // Add success flag for frontend to detect
        $redirect_to = add_query_arg('flosc_sso_success', '1', $redirect_to);
        
        wp_redirect($redirect_to);
        exit;
    }
    
    /**
     * Get available SSO providers for frontend
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_providers($request) {
        $providers = $this->manager->get_enabled_providers();
        $result = array();
        
        foreach ($providers as $provider) {
            $result[] = array(
                'id'      => $provider->get_id(),
                'name'    => $provider->get_name(),
                'icon'    => $provider->get_icon(),
                'colors'  => $provider->get_button_colors(),
                'auth_url' => rest_url("flosc/v1/sso/authorize/{$provider->get_id()}"),
            );
        }
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * Generate state token for CSRF protection
     * 
     * @param string $provider_id Provider ID
     * @param string $redirect_to Redirect URL after login
     * @param string $flow_id Flow ID for per-flow credential loading (v1.4.9)
     * @return string State token
     */
    private function generate_state($provider_id, $redirect_to = '', $flow_id = '') {
        $state = wp_generate_password(32, false);
        
        $state_data = array(
            'provider'    => $provider_id,
            'redirect_to' => $redirect_to,
            'flow_id'     => $flow_id, // v1.4.9: Per-flow SSO
            'timestamp'   => time(),
            'nonce'       => wp_create_nonce('flosc_sso_' . $provider_id),
        );
        
        $transient_key = self::STATE_PREFIX . $state;
        $saved = set_transient($transient_key, $state_data, self::STATE_EXPIRATION);
        
        // Debug logging for troubleshooting
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC SSO] State generated for ' . $provider_id . ' | Saved: ' . ($saved ? 'yes' : 'NO'));
        }
        
        // Verify it was actually saved
        $verify = get_transient($transient_key);
        if (!$verify) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC SSO] WARNING: State transient not readable immediately after save!');
            }
            // Fallback: write directly to options table
            update_option($transient_key, $state_data, false);
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC SSO] Fallback: saved state to options table');
            }
        }
        
        return $state;
    }
    
    /**
     * Verify state token
     * 
     * @param string $state State token
     * @return array|false State data or false if invalid
     */
    private function verify_state($state) {
        if (empty($state)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC SSO] State verification failed: empty state parameter');
            }
            return false;
        }
        
        $transient_key = self::STATE_PREFIX . $state;
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC SSO] Verifying state for key: ' . $transient_key);
        }
        
        $state_data = get_transient($transient_key);
        
        // Fallback: check options table directly
        if (!$state_data) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC SSO] Transient not found, checking options table fallback');
            }
            $state_data = get_option($transient_key);
        }
        
        if (!$state_data) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC SSO] State verification FAILED for key ' . $transient_key);
            }
            return false;
        }
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC SSO] State verification SUCCESS for provider: ' . ($state_data['provider'] ?? 'unknown'));
        }
        
        // Delete used state (one-time use)
        delete_transient($transient_key);
        delete_option($transient_key);
        
        return $state_data;
    }
    
    /**
     * Process SSO login - create or link user
     * 
     * @param SSO_Provider_Base $provider Provider instance
     * @param array $user_data Normalized user data
     * @param array $token_data Token response data
     * @return int|WP_Error User ID or error
     */
    private function process_sso_login($provider, $user_data, $token_data) {
        // Get the user linker
        $linker = $this->manager->get_user_linker();
        
        // Try to find existing linked user
        $user_id = $linker->find_linked_user($provider->get_id(), $user_data['provider_id']);
        
        if ($user_id) {
            // Update stored tokens
            $linker->update_user_tokens($user_id, $provider->get_id(), $token_data);
            
            // Log the user in
            $this->log_user_in($user_id);
            
            do_action('flosc_sso_login_success', $user_id, $provider->get_id(), $user_data);
            
            return $user_id;
        }
        
        // Check if user is currently logged in (linking account)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $linker->link_account($user_id, $provider->get_id(), $user_data, $token_data);
            
            do_action('flosc_sso_account_linked', $user_id, $provider->get_id(), $user_data);
            
            return $user_id;
        }
        
        // Try to find user by email
        $email = isset($user_data['email']) ? $user_data['email'] : '';
        
        if ($email) {
            $existing_user = get_user_by('email', $email);
            
            if ($existing_user) {
                // Auto-link if email matches and is verified
                if (!empty($user_data['email_verified']) || apply_filters('flosc_sso_auto_link_email', true, $provider->get_id())) {
                    $linker->link_account($existing_user->ID, $provider->get_id(), $user_data, $token_data);
                    $this->log_user_in($existing_user->ID);
                    
                    do_action('flosc_sso_account_auto_linked', $existing_user->ID, $provider->get_id(), $user_data);
                    
                    return $existing_user->ID;
                }
                
                // Email exists but auto-link disabled
                return new \WP_Error(
                    'email_exists',
                    'An account with this email already exists. Please log in with your password first, then link your social account.'
                );
            }
        }
        
        // Create new user
        $new_user_id = $linker->create_user_from_sso($provider->get_id(), $user_data, $token_data);
        
        if (is_wp_error($new_user_id)) {
            return $new_user_id;
        }
        
        // Log the new user in
        $this->log_user_in($new_user_id);
        
        do_action('flosc_sso_user_created', $new_user_id, $provider->get_id(), $user_data);
        
        return $new_user_id;
    }
    
    /**
     * Log a user in programmatically
     * 
     * @param int $user_id User ID
     */
    private function log_user_in($user_id) {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
    }
    
    /**
     * Redirect with error message
     * 
     * @param string $message Error message
     */
    private function redirect_with_error($message) {
        // Store error in transient for display
        $error_key = 'flosc_sso_error_' . wp_generate_password(8, false);
        set_transient($error_key, $message, 60);
        
        $redirect_url = add_query_arg(
            array('flosc_sso_error' => $error_key),
            home_url()
        );
        
        wp_redirect($redirect_url);
        exit;
    }
}
