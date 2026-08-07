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
            'permission_callback' => array($this, 'check_public_sso_permission'),
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
            'permission_callback' => array($this, 'check_public_sso_permission'),
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
            'permission_callback' => array($this, 'check_public_sso_provider_list_permission'),
        ));
    }

    /**
     * Intentionally public: OAuth authorization and callbacks must be reachable
     * before a WordPress login exists.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_public_sso_permission($request) {
        return true;
    }

    /**
     * Intentionally public provider list endpoint for frontend discovery.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_public_sso_provider_list_permission($request) {
        return true;
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
        
        // v8.0.4: Prevent nginx/CDN from caching this 302 redirect.
        // A cached 302 would replay a stale auth URL with a consumed state token.
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        nocache_headers();
        
        // Only store allowlisted redirects (never arbitrary attacker-controlled hosts).
        $redirect_to = is_string($redirect_to) ? $redirect_to : '';
        if ($redirect_to !== '' && !$this->is_allowed_sso_redirect($redirect_to, $flow_id)) {
            $redirect_to = '';
        }

        // Generate state for CSRF protection
        // v1.4.9: Include flow_id in state for per-flow credential loading on callback
        $state = $this->generate_state($provider_id, $redirect_to, $flow_id);
        
        // Get authorization URL
        $auth_url = $provider->get_authorization_url($state, $provider->get_callback_url());
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] handle_authorize: provider=' . $provider_id . ' | state=' . substr($state, 0, 8) . '... | flow_id=' . $flow_id);
        
        // Redirect to provider (Google's Step 2: Redirect to OAuth 2.0 server).
        $this->flosc_safe_external_redirect( $auth_url );
    }

    /**
     * External redirect via wp_safe_redirect after temporarily allowlisting host.
     *
     * @param string $url Absolute http(s) URL.
     * @return void
     */
    private function flosc_safe_external_redirect( $url ) {
        $url  = esc_url_raw( (string) $url );
        $host = strtolower( (string) ( wp_parse_url( $url, PHP_URL_HOST ) ?? '' ) );
        if ( $url === '' || $host === '' || ! wp_http_validate_url( $url ) ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }
        $add_host = static function ( $hosts ) use ( $host ) {
            $hosts   = is_array( $hosts ) ? $hosts : array();
            $hosts[] = $host;
            return array_values( array_unique( $hosts ) );
        };
        add_filter( 'allowed_redirect_hosts', $add_host );
        wp_safe_redirect( $url );
        remove_filter( 'allowed_redirect_hosts', $add_host );
        exit;
    }
    
    /**
     * Handle OAuth callback
     * 
     * @param WP_REST_Request $request
     * @return void Redirects on completion
     */
    public function handle_callback($request) {
        // OAuth provider callback payload (not a WP form nonce action).
        $get  = array();
        $post = array();
        foreach ( array( 'code', 'state', 'error', 'error_description' ) as $flosc_k ) {
            $g = filter_input( INPUT_GET, $flosc_k, FILTER_UNSAFE_RAW );
            if ( is_string( $g ) && $g !== '' ) {
                $get[ $flosc_k ] = wp_unslash( $g );
            }
            $p = filter_input( INPUT_POST, $flosc_k, FILTER_UNSAFE_RAW );
            if ( is_string( $p ) && $p !== '' ) {
                $post[ $flosc_k ] = wp_unslash( $p );
            }
        }
        $server = array();
        foreach ( array( 'REQUEST_URI', 'REQUEST_METHOD', 'QUERY_STRING' ) as $flosc_sk ) {
            $sv = filter_input( INPUT_SERVER, $flosc_sk, FILTER_UNSAFE_RAW );
            $server[ $flosc_sk ] = is_string( $sv ) ? wp_unslash( $sv ) : '';
        }

        // v8.0.4: Prevent caching of callback responses
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        nocache_headers();
        
        // Force OPcache to recompile this file on every callback.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(__FILE__, true);
        }
        
        // Provider comes from the URL path (regex capture), so keep that from $request.
        $provider_id = $request->get_param('provider');
        
        // Google's Step 4: Handle the OAuth 2.0 server response
        // Google sends: ?code=AUTH_CODE&state=STATE_TOKEN (success)
        // or: ?error=ERROR_CODE (failure)
        // Apple uses form_post (POST body), so check POST first, then GET.
        $code  = isset($post['code'])  ? sanitize_text_field($post['code'])  : (isset($get['code'])  ? sanitize_text_field($get['code'])  : '');
        $state = isset($post['state']) ? sanitize_text_field($post['state']) : (isset($get['state']) ? sanitize_text_field($get['state']) : '');
        $error = isset($post['error']) ? sanitize_text_field($post['error']) : (isset($get['error']) ? sanitize_text_field($get['error']) : '');
        
        // v8.0.5: REQUEST_URI fallback. On ChemiCloud (NGINX reverse proxy → PHP-FPM),
        // Google's 302 redirect arrives with the query string visible in REQUEST_URI
        // but $_GET is empty — NGINX's fastcgi_param QUERY_STRING doesn't always
        // propagate from the rewritten URL. Parse the query string from REQUEST_URI
        // as a bulletproof fallback. Confirmed by 08:15:51 log: REQUEST_URI had
        // state=wIWRRIthC... and code=4/0AfrIep... but $_GET had neither.
        if (empty($state) && !empty($server['REQUEST_URI'])) {
            $parsed_uri = wp_parse_url($server['REQUEST_URI']);
            if (!empty($parsed_uri['query'])) {
                parse_str($parsed_uri['query'], $uri_params);
                if (!empty($uri_params['state'])) {
                    $state = sanitize_text_field($uri_params['state']);
                }
                if (empty($code) && !empty($uri_params['code'])) {
                    $code = sanitize_text_field($uri_params['code']);
                }
                if (empty($error) && !empty($uri_params['error'])) {
                    $error = sanitize_text_field($uri_params['error']);
                }
            }
        }
        
        // v8.0.5: Also try QUERY_STRING directly (another FastCGI variable that
        // may survive when $_GET doesn't)
        if (empty($state) && !empty($server['QUERY_STRING'])) {
            parse_str($server['QUERY_STRING'], $qs_params);
            if (!empty($qs_params['state'])) {
                $state = sanitize_text_field($qs_params['state']);
            }
            if (empty($code) && !empty($qs_params['code'])) {
                $code = sanitize_text_field($qs_params['code']);
            }
            if (empty($error) && !empty($qs_params['error'])) {
                $error = sanitize_text_field($qs_params['error']);
            }
        }
        
        // v8.0.5: Last resort — WP_REST_Request may have parsed them from the
        // matched route's query args
        if (empty($state)) {
            $state = sanitize_text_field($request->get_param('state') ?? '');
        }
        if (empty($code)) {
            $code = sanitize_text_field($request->get_param('code') ?? '');
        }
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] handle_callback: provider=' . $provider_id . ' | state=' . ($state ?: '(empty)') . ' | code=' . ($code ? 'present' : 'absent') . ' | error=' . ($error ?: 'none') . ' | method=' . sanitize_text_field($server['REQUEST_METHOD'] ?? 'unknown') . ' | source=' . (!empty($get['state']) ? '$_GET' : (!empty($server['REQUEST_URI']) && strpos($server['REQUEST_URI'], 'state=') !== false ? 'REQUEST_URI' : (!empty($server['QUERY_STRING']) ? 'QUERY_STRING' : 'WP_REST'))));
        
        // ── Resolve the correct app URL from state ──
        // The callback runs on the WordPress host (registered with Google), but the user
        // came from the flow domain. get_current_flow() fails here because it matches
        // by HTTP_HOST = the WordPress host. Instead, use the flow_id stored in state to
        // look up the flow's custom_domain directly from the database.
        $app_url = home_url(); // absolute last resort
        $error_redirect_to = '';
        
        if (!empty($state)) {
            $transient_key = self::STATE_PREFIX . $state;
            $peek_data = get_transient($transient_key);
            if (!$peek_data) {
                $peek_data = get_option($transient_key);
            }
            if ($peek_data) {
                // Use stored redirect_to (the URL the user was on: the flow domain)
                if (!empty($peek_data['redirect_to'])) {
                    $error_redirect_to = $peek_data['redirect_to'];
                }
                // Resolve app URL from flow_id → flow settings → domain
                if (!empty($peek_data['flow_id'])) {
                    $resolved = $this->resolve_app_url_from_flow_id($peek_data['flow_id']);
                    if ($resolved) {
                        $app_url = $resolved;
                    }
                }
            }
        }
        
        // If we couldn't get redirect_to from state, use the flow-resolved app URL
        if (empty($error_redirect_to)) {
            $error_redirect_to = $app_url;
        }
        
        // ── Handle provider-side errors (user denied permission, etc.) ──
        if ($error) {
            $error_description = isset($post['error_description']) ? sanitize_text_field($post['error_description']) : (isset($get['error_description']) ? sanitize_text_field($get['error_description']) : $error);
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] Provider error: ' . $error_description);
            if (!empty($state)) {
                $transient_key = self::STATE_PREFIX . $state;
                delete_transient($transient_key);
                delete_option($transient_key);
            }
            $this->redirect_with_error($error_description, $error_redirect_to);
            return;
        }
        
        // ── Verify state (CSRF protection, one-time use) ──
        $state_data = $this->verify_state($state);
        if (!$state_data) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] State verification failed for state: ' . ($state ?: '(empty)'));
            $this->redirect_with_error('Invalid or expired authentication state. Please try again.', $error_redirect_to);
            return;
        }
        
        // State verified — update redirect targets from authoritative state data
        if (!empty($state_data['redirect_to'])) {
            $error_redirect_to = $state_data['redirect_to'];
        }
        if (!empty($state_data['flow_id'])) {
            $resolved = $this->resolve_app_url_from_flow_id($state_data['flow_id']);
            if ($resolved) {
                $app_url = $resolved;
            }
        }
        
        // ── Verify provider matches ──
        if ($state_data['provider'] !== $provider_id) {
            $this->redirect_with_error('Provider mismatch. Please try again.', $error_redirect_to);
            return;
        }
        
        $provider = $this->manager->get_provider($provider_id);
        if (!$provider) {
            $this->redirect_with_error('Invalid provider.', $error_redirect_to);
            return;
        }
        
        // ── Load per-flow credentials ──
        $flow_id = $state_data['flow_id'] ?? '';
        if (!empty($flow_id)) {
            $flow_settings_key = 'flosc_flow_' . sanitize_key($flow_id);
            $flow_settings = get_option($flow_settings_key, []);
            $flow_client_id = $flow_settings["sso_{$provider_id}_client_id"] ?? '';
            $flow_client_secret = $flow_settings["sso_{$provider_id}_client_secret"] ?? '';
            $flow_enabled = !empty($flow_settings["sso_{$provider_id}_enabled"]);
            
            if (!empty($flow_client_id)) {
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
        
        // ── Exchange authorization code for token ──
        $token_data = $provider->exchange_code_for_token($code, $provider->get_callback_url());
        if (is_wp_error($token_data)) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] Token exchange failed: ' . $token_data->get_error_message());
            $this->redirect_with_error('Authentication failed: ' . $token_data->get_error_message(), $error_redirect_to);
            return;
        }
        
        // ── Get user info from provider ──
        $access_token = isset($token_data['access_token']) ? $token_data['access_token'] : '';
        $user_data = $provider->get_user_info($access_token, $token_data);
        if (is_wp_error($user_data)) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] User info failed: ' . $user_data->get_error_message());
            $this->redirect_with_error('Failed to get user info: ' . $user_data->get_error_message(), $error_redirect_to);
            return;
        }

        // Preserve flow context for downstream hooks (e.g. welcome email link domain)
        // when a new user is created via SSO.
        if (!empty($state_data['flow_id']) && is_array($user_data)) {
            $user_data['flow_id'] = sanitize_key($state_data['flow_id']);
        }
        
        // ── Process login (find/create/link user, set auth cookie on the WordPress host) ──
        $result = $this->process_sso_login($provider, $user_data, $token_data);
        if (is_wp_error($result)) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] Login processing failed: ' . $result->get_error_message());
            $this->redirect_with_error($result->get_error_message(), $error_redirect_to);
            return;
        }
        
        // ── SUCCESS — redirect user back to origin ──
        $flow_id_for_redirect = sanitize_key((string) ($state_data['flow_id'] ?? ''));
        $redirect_to = !empty($state_data['redirect_to']) ? $state_data['redirect_to'] : $app_url;
        
        // If redirect_to is a wp-login.php URL, extract the inner redirect_to
        if (strpos($redirect_to, 'wp-login.php') !== false) {
            $parsed = wp_parse_url($redirect_to);
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $params);
                if (!empty($params['redirect_to'])) {
                    $redirect_to = $params['redirect_to'];
                }
            }
        }
        
        // Resolve slug-based URLs to custom domain
        // e.g. the WordPress host/flow_path/ → the flow domain/
        if (function_exists('flosc')) {
            $app_slug = get_option('flosc_app_slug', 'flosc');
            if (strpos($redirect_to, '/' . $app_slug) !== false) {
                $custom_url = flosc()->get_app_url();
                if ($custom_url && strpos($custom_url, $app_slug) === false) {
                    $redirect_to = $custom_url;
                }
            }
        }

        // Fail closed: unapproved redirect_to never receives a login token or redirect.
        if (!$this->is_allowed_sso_redirect($redirect_to, $flow_id_for_redirect)) {
            $fallback = is_string($app_url) && $app_url !== '' ? $app_url : home_url('/');
            if (!$this->is_allowed_sso_redirect($fallback, $flow_id_for_redirect)) {
                $fallback = home_url('/');
            }
            $redirect_to = $fallback;
        }
        
        // Cross-domain login token: only on allowlisted hosts (never arbitrary external).
        $callback_host = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')));
        $redirect_host = strtolower((string) (wp_parse_url($redirect_to, PHP_URL_HOST) ?? ''));
        if ($redirect_host && $callback_host && $redirect_host !== $callback_host
            && $this->is_allowed_sso_redirect($redirect_to, $flow_id_for_redirect)
        ) {
            $token = $this->generate_login_token($result);
            $redirect_to = add_query_arg('flosc_login_token', $token, $redirect_to);
        }

        $redirect_to = add_query_arg('flosc_sso_success', '1', $redirect_to);
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC SSO] Success — redirecting to: ' . $redirect_to);
        }

        $home_host = strtolower((string) (wp_parse_url(home_url('/'), PHP_URL_HOST) ?? ''));
        if ($redirect_host === '' || $redirect_host === $home_host || $redirect_host === $callback_host) {
            wp_safe_redirect($redirect_to);
            exit;
        }
        // Cross-domain but allowlisted FLOSC app origin only.
        $this->flosc_safe_external_redirect( $redirect_to );
    }

    /**
     * Whether a post-SSO redirect URL is an approved FLOSC / site origin.
     *
     * Allows: same-origin WP, configured custom domains / app URLs, flow SSO
     * post-login redirect hosts. Rejects: javascript:, data:, empty, unconfigured hosts.
     *
     * @param string $url     Candidate redirect URL.
     * @param string $flow_id Optional flow context for domain settings.
     * @return bool
     */
    private function is_allowed_sso_redirect($url, $flow_id = '') {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        // Protocol-relative and dangerous schemes.
        $lower = strtolower($url);
        if (strpos($lower, 'javascript:') === 0 || strpos($lower, 'data:') === 0
            || strpos($lower, 'vbscript:') === 0
        ) {
            return false;
        }
        if (strpos($url, '//') === 0) {
            return false;
        }

        // Relative path → same origin (safe).
        if (isset($url[0]) && $url[0] === '/' && (!isset($url[1]) || $url[1] !== '/')) {
            return true;
        }

        $parsed = wp_parse_url($url);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return false;
        }
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if ($scheme !== 'https' && $scheme !== 'http') {
            return false;
        }
        // Production preference: allow http only for localhost.
        $host = strtolower((string) $parsed['host']);
        if ($scheme === 'http' && $host !== 'localhost' && $host !== '127.0.0.1') {
            // Still allow if site itself is http (local/dev).
            $site_scheme = strtolower((string) (wp_parse_url(home_url('/'), PHP_URL_SCHEME) ?? 'https'));
            if ($site_scheme !== 'http') {
                return false;
            }
        }

        $allowed_hosts = $this->get_allowed_sso_redirect_hosts($flow_id);
        return in_array($host, $allowed_hosts, true);
    }

    /**
     * Build allowlist of hosts for SSO redirects + login-token attachment.
     *
     * @param string $flow_id Optional flow id.
     * @return string[] Lowercase hosts.
     */
    private function get_allowed_sso_redirect_hosts($flow_id = '') {
        $hosts = [];
        $add = static function ($url_or_host) use (&$hosts) {
            $url_or_host = trim((string) $url_or_host);
            if ($url_or_host === '') {
                return;
            }
            if (strpos($url_or_host, '://') === false) {
                $host = strtolower(preg_replace('#^www\.#', '', $url_or_host));
            } else {
                $host = strtolower((string) (wp_parse_url($url_or_host, PHP_URL_HOST) ?? ''));
            }
            $host = preg_replace('#^www\.#', '', $host);
            if ($host !== '') {
                $hosts[$host] = true;
            }
        };

        $add(home_url('/'));
        $add(site_url('/'));
        $add(admin_url('/'));
        $add(rest_url('/'));
        $add(get_option('flosc_custom_domain', ''));

        if (function_exists('flosc')) {
            $app = flosc()->get_app_url();
            if (is_string($app) && $app !== '') {
                $add($app);
            }
        }

        // Current request host (callback domain, e.g. the WordPress host).
        if (!empty($_SERVER['HTTP_HOST'])) {
            $add(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST'])));
        }

        // Flow-scoped allowlist only: the current flow's configured domains/app URLs.
        // This prevents one flow's configured redirect host from implicitly approving
        // a different flow's post-login target.
        if ($flow_id !== '') {
            $settings = get_option('flosc_flow_' . sanitize_key($flow_id), []);
            if (is_array($settings)) {
                foreach (['domain', 'custom_domain', 'sso_post_login_redirect_url', 'app_url'] as $field) {
                    if (!empty($settings[$field])) {
                        $add($settings[$field]);
                    }
                }
            }

            $resolved_app_url = $this->resolve_app_url_from_flow_id($flow_id);
            if (is_string($resolved_app_url) && $resolved_app_url !== '') {
                $add($resolved_app_url);
            }
        }

        /**
         * Filter allowed SSO redirect hosts (lowercase hostnames only).
         *
         * @param string[] $hosts Hostnames.
         * @param string   $flow_id Flow id.
         */
        $hosts = apply_filters('flosc_sso_allowed_redirect_hosts', array_keys($hosts), $flow_id);
        return array_values(array_unique(array_map('strtolower', (array) $hosts)));
    }
    
    /**
     * Resolve the app URL from a flow_id by looking up the flow's custom domain
     * directly from the database. This works during REST API callbacks where
     * get_current_flow() fails because HTTP_HOST is the WordPress host, not the custom domain.
     *
     * @param string $flow_id Flow ID (e.g. 'flow_ivr')
     * @return string|false App URL (e.g. 'https://the flow domain/') or false if not found
     */
    private function resolve_app_url_from_flow_id($flow_id) {
        if (empty($flow_id)) {
            return false;
        }
        
        $settings_key = 'flosc_flow_' . sanitize_key($flow_id);
        $settings = get_option($settings_key, []);
        
        // Preferred fallback: explicit per-flow post-login URL configured by floscAdmin.
        $configured_redirect = trim((string)($settings['sso_post_login_redirect_url'] ?? ''));
        if (!empty($configured_redirect)) {
            $configured_redirect = esc_url_raw($configured_redirect);
            if (wp_http_validate_url($configured_redirect)) {
                return $configured_redirect;
            }
        }

        // Domain fallback from flow settings.
        $domain = $settings['domain'] ?? '';
        if (!empty($domain)) {
            $domain = preg_replace('#^https?://#', '', trim($domain));
            $domain = rtrim($domain, '/');
            return 'https://' . $domain . '/';
        }
        
        return false;
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
        
        // v8.0.4: Ungated logging — SSO failures are rare and critical
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] State generated: provider=' . $provider_id . ' | token=' . substr($state, 0, 8) . '... | saved=' . ($saved ? 'yes' : 'NO') . ' | flow_id=' . $flow_id);
        
        // v8.0.2: Always write to options table as backup.
        // Transients can disappear between requests due to object cache eviction.
        update_option($transient_key, $state_data, false);
        
        // Verify state is retrievable
        $verify = get_transient($transient_key);
        if (!$verify) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] WARNING: State transient not readable after save — options fallback active');
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
        // v8.0.4: All SSO logging ungated — SSO failures are rare and critical
        if (empty($state)) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] State verification failed: empty state parameter');
            return false;
        }
        
        $transient_key = self::STATE_PREFIX . $state;
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] Verifying state for key: ' . $transient_key);
        
        $state_data = get_transient($transient_key);
        
        // Fallback: check options table directly
        if (!$state_data) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO] Transient not found, checking options table fallback');
            $state_data = get_option($transient_key);
        }
        
        if (!$state_data || !is_array($state_data)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC SSO] State verification FAILED for key ' . $transient_key);
            }
            return false;
        }

        // Enforce absolute expiry even when options-table fallback outlives the transient.
        $ts = isset($state_data['timestamp']) ? (int) $state_data['timestamp'] : 0;
        if ($ts <= 0 || (time() - $ts) > self::STATE_EXPIRATION) {
            delete_transient($transient_key);
            delete_option($transient_key);
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC SSO] State expired (timestamp check) for key ' . $transient_key);
            }
            return false;
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC SSO] State verification SUCCESS for provider: ' . ($state_data['provider'] ?? 'unknown'));
        }
        
        // Delete used state (one-time use) — prevent replay via option fallback.
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
                // Auto-link only when provider asserts email_verified, unless site explicitly allows unverified.
                $email_verified = !empty($user_data['email_verified']);
                $allow_unverified = (bool) apply_filters('flosc_sso_auto_link_unverified_email', false, $provider->get_id());
                // Legacy filter name: if site set flosc_sso_auto_link_email to false, never auto-link.
                if (false === apply_filters('flosc_sso_auto_link_email', true, $provider->get_id())) {
                    $email_verified = false;
                    $allow_unverified = false;
                }
                if ($email_verified || $allow_unverified) {
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

        // v1.5.3: Do NOT fire do_action('wp_login') here.
        // Other plugins (WooCommerce, BuddyBoss) hook wp_login and call
        // wp_redirect() + exit, which kills the SSO callback before FLOSC's
        // own redirect logic runs. FLOSC's handle_user_login() is called
        // directly by handle_login_token() on the target domain instead.
    }

    /**
     * Generate a one-time login token for cross-domain redirect
     * v1.5.2: Solves cookie domain problem — auth cookie set on the WordPress host
     * doesn't travel to flosc.ai/the flow domain. Token lets the target domain
     * authenticate the user on arrival.
     *
     * @param int $user_id User ID
     * @return string Token
     */
    private function generate_login_token($user_id) {
        $token = wp_generate_password(40, false);
        // v1.5.3: 60s TTL (was 30s) — allows for slow DNS/CDN on custom domains
        set_transient('flosc_login_token_' . $token, $user_id, 60);
        return $token;
    }
    
    /**
     * Redirect with error message
     * 
     * v8.0.1: Accept optional redirect_to so user returns to the app page
     * (where FLOSC JS is running), not the homepage where it isn't.
     * 
     * @param string $message Error message
     * @param string $redirect_to URL to redirect to (falls back to home_url())
     */
    private function redirect_with_error($message, $redirect_to = '') {
        // Store error in transient for display
        // v8.0.2: Increased TTL from 60s to 300s — slow redirects or CDN delays
        // could cause the transient to expire before the page loads
        $error_token = wp_generate_password(8, false);
        $error_key = 'flosc_sso_error_' . $error_token;
        set_transient($error_key, $message, 300);
        
        // v8.0.2: Always log SSO errors (ungated) — SSO failures are rare and
        // critical enough that the log line is justified without a debug flag
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC SSO ERROR] ' . $message . ' | redirect_to: ' . ($redirect_to ?: '(empty)'));
        
        // v8.0.2: Use custom domain app URL as fallback instead of home_url().
        // home_url() returns the WordPress host, but if the user initiated SSO from
        // the flow domain, they need to go back to the flow domain where FLOSC JS runs.
        if (empty($redirect_to)) {
            if (function_exists('flosc')) {
                $redirect_to = flosc()->get_app_url();
            }
        }
        $base_url = !empty($redirect_to) ? $redirect_to : home_url('/');
        if (!$this->is_allowed_sso_redirect($base_url, '')) {
            $base_url = home_url('/');
        }
        
        $redirect_url = add_query_arg(
            array('flosc_sso_error' => $error_token),
            $base_url
        );

        $base_host = strtolower((string) (wp_parse_url($redirect_url, PHP_URL_HOST) ?? ''));
        $home_host = strtolower((string) (wp_parse_url(home_url('/'), PHP_URL_HOST) ?? ''));
        if ($base_host === '' || $base_host === $home_host) {
            wp_safe_redirect($redirect_url);
            exit;
        }
        $this->flosc_safe_external_redirect( $redirect_url );
    }
}
