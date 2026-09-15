<?php
/**
 * First-party WP authentication for FLOSC (not SSO, not MagicLink).
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_First_Party_Authentication {

    /** @var FLOSC_Framework */
    private $flosc;

    /**
     * Set when authenticate_flosc_token() succeeds for this request.
     * Used by allow_flosc_token_auth() to skip WP REST nonce check.
     *
     * @var bool
     */
    private $flosc_token_auth_used = false;

    public function __construct($flosc) {
        $this->flosc = $flosc;
    }

    /** @return object|null Token provider from framework sale manager. */
    private function get_token_provider() {
        $sale = method_exists($this->flosc, 'sale') ? $this->flosc->sale() : null;
        return ($sale && method_exists($sale, 'get_provider')) ? $sale->get_provider('tokens') : null;
    }

    /** Flag FLOSC token auth for allow_flosc_token_auth() (REST / session bootstrap). */
    public function mark_flosc_token_auth_used() {
        $this->flosc_token_auth_used = true;
    }

    /**
     * Handle new user registration
     */
    /**
     * Block WP password login for pending email-registered accounts until verification.
     *
     * @param WP_User|WP_Error $user
     * @param string           $password
     * @return WP_User|WP_Error
     */
    public function flosc_block_pending_email_login($user, $password) {
        if (is_wp_error($user) || !($user instanceof WP_User)) {
            return $user;
        }
        $status = (string) get_user_meta($user->ID, '_flosc_email_account_status', true);
        if ($status === 'pending') {
            return new WP_Error(
                'flosc_email_pending',
                __('Please verify your email address before signing in. Check your inbox for the verification link.', 'flosc')
            );
        }
        return $user;
    }

    public function handle_user_registration($user_id) {
        // Pending email registrants receive tokens only after verification/activation.
        // Flag is set on the framework instance by MagicLink / email registration paths.
        if (!empty($this->flosc->flosc_skip_registration_token_grants)) {
            return;
        }
        // Grant signup bonus tokens + flow-specific guest token baseline
        $token_provider = $this->get_token_provider();
        if ($token_provider && method_exists($token_provider, 'grant_signup_bonus')) {
            $token_provider->grant_signup_bonus($user_id);
        }

        $flow_id = sanitize_key((string) get_user_meta($user_id, '_flosc_registration_flow', true));
        if ($flow_id === '') {
            $current_flow = $this->flosc->get_current_flow();
            $ivr_file = (string) ($current_flow['ivr_file'] ?? $current_flow['ivr'] ?? '');
            $flow_id = $this->flosc->flosc_normalize_flow_stem($ivr_file !== '' ? $ivr_file : (string) ($current_flow['id'] ?? ''));
        }
        if ($this->flosc->flosc_user_should_receive_guest_tokens($user_id, $flow_id)) {
            $this->flosc->flosc_ensure_guest_token_baseline($user_id, $token_provider, $flow_id, 'Guest registration baseline');
        }
        
        // Check for referrer
        $referrer = isset($_COOKIE['flosc_referrer']) ? sanitize_text_field(wp_unslash($_COOKIE['flosc_referrer'])) : null;
        if ($referrer && preg_match('/^REF(\d+)$/', $referrer, $matches)) {
            $referrer_id = intval($matches[1]);
            if ($referrer_id && $referrer_id !== $user_id && $token_provider && method_exists($token_provider, 'grant_referral_bonus')) {
                $token_provider->grant_referral_bonus($referrer_id, $user_id);
            }
        }

        // v8.0.5: Audio scoring is NOT done here. This hook fires for ALL registration
        // methods (email, SSO, WP form) and has no reliable access to the temp_id.
        // Instead, scoring is called DIRECTLY by the function that has the temp_id:
        //   - Email registration: handle_email_registration() calls score_visitor_audio() directly
        //   - SSO registration: handle_user_login() reads the browser cookie (reliable for SSO)
        // This hook handles signup bonus + referral only.
    }

    /**
     * Handle user login - process pre-login quiz scores
     */
    public function handle_user_login($user_login, $user) {
        $token_provider = $this->get_token_provider();
        $flow_id = sanitize_key((string) get_user_meta($user->ID, '_flosc_registration_flow', true));
        if ($flow_id === '') {
            $current_flow = $this->flosc->get_current_flow();
            $ivr_file = (string) ($current_flow['ivr_file'] ?? $current_flow['ivr'] ?? '');
            $flow_id = $this->flosc->flosc_normalize_flow_stem($ivr_file !== '' ? $ivr_file : (string) ($current_flow['id'] ?? ''));
        }
        if ($this->flosc->flosc_user_should_receive_guest_tokens($user->ID, $flow_id)) {
            $this->flosc->flosc_ensure_guest_token_baseline($user->ID, $token_provider, $flow_id, 'Guest login baseline');
        }

        // v07.09: Set justLoggedIn flag for IVR
        set_transient('flosc_just_logged_in_' . $user->ID, true, MINUTE_IN_SECONDS * 5);

        // Restore browser-computed quiz data stashed before SSO redirect.
        $this->flosc->consume_stashed_visitor_quiz($user->ID);

        // v2.0.2: Track login count for IVR condition evaluation (login_count)
        $current_count = (int) get_user_meta($user->ID, '_flosc_login_count', true);
        update_user_meta($user->ID, '_flosc_login_count', $current_count + 1);

        // v8.0.12: Reliability guard for SSO guest email sequence.
        // If WP-Cron is delayed, process welcome/follow-up checks when an SSO user logs in.
        $this->flosc->maybe_run_sso_email_sequence_for_user($user->ID);

        // v9.4.2: Check for pre-login score in SIGNED cookie
        $score_data = $this->flosc->get_signed_cookie('flosc_prelogin_score');

        // v8.0.5: Score visitor audio on login — covers SSO path (Google/Facebook) where
        // the browser sends the visitor_temp_id cookie set during audio recording.
        // Email registration scoring is handled directly in handle_email_registration().
        // Don't re-score server-side (times out on shared hosting).
        // Instead, store temp_id in user meta and let JS send browser-computed
        // results via /store-quiz-data after the page reloads.
        $temp_id = $this->flosc->get_signed_cookie('flosc_visitor_temp_id');
        if ($temp_id && is_string($temp_id)) {
            update_user_meta($user->ID, '_flosc_audio_temp_id', sanitize_text_field($temp_id));
            setcookie('flosc_visitor_temp_id', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
        }

        // v3.0.7: Also fall back to flosc_quiz_result cookie (in-chat MC quiz path via /quiz-result).
        // flosc_prelogin_score is set by /store-score (text-sequence & fixed MC path).
        // flosc_quiz_result is set by /quiz-result and is the only cookie when /store-score is unavailable.
        if ( ! $score_data || ! isset( $score_data['score'] ) ) {
            $raw = $this->flosc->get_signed_cookie('flosc_quiz_result');
            if ( $raw && isset( $raw['score'] ) ) {
                $answers   = is_array( $raw['answers'] ?? null ) ? $raw['answers'] : [];
                $correct   = [];
                $incorrect = [];
                foreach ( $answers as $i => $a ) {
                    $lesson = $i + 1;
                    if ( isset( $a['correct'] ) && $a['correct'] === true ) {
                        $correct[]   = $lesson;
                    } else {
                        $incorrect[] = $lesson;
                    }
                }
                $score_data = [
                    'quiz_id'   => $raw['quiz_id']      ?? flosc_get_setting('default_text_quiz_id', 'sample_assessment_quiz'),
                    'score'     => intval( $raw['score'] ),
                    'correct'   => $correct,
                    'incorrect' => $incorrect,
                    'timestamp' => isset( $raw['completed_at'] ) ? intval( $raw['completed_at'] / 1000 ) : time(),
                ];
                // Clear the fallback cookie
                setcookie( 'flosc_quiz_result', '', [ 'expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax' ] );
            }
        }

        if ($score_data && isset($score_data['score'])) {
            // v8.0.3: Store score with quiz_id tracking
            $this->flosc->store_quiz_score($user->ID, $score_data);

            // v1.8.2: Fire flosc_quiz_completed so Free Lesson Manager assigns lessons
            do_action('flosc_quiz_completed', $score_data, $user->ID);

            // v07.09: Set justCompletedQuiz flag for IVR
            set_transient('flosc_just_completed_quiz_' . $user->ID, true, MINUTE_IN_SECONDS * 5);

            // Send email with score and OTO
            $this->flosc->send_score_email($user, $score_data);

            // Clear the cookie (v1.0.7: use array syntax)
            setcookie('flosc_prelogin_score', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'samesite' => 'Lax'
            ]);
        }
    }

    /**
     * v9.5.7: Redirect users to FLOSC app after login
     * v1.0.0: ONLY redirect if user was on FLOSC app or has pre-login quiz score
     * v1.4.9: Use get_app_url() for custom domain support (the flow domain, flosc.ai)
     *
     * IMPORTANT: This function does NOT hijack normal WordPress logins.
     * Only redirects to FLOSC app when there's a clear FLOSC context.
     */
    public function handle_login_redirect($redirect_to, $requested_redirect_to, $user) {
        $app_slug = get_option('flosc_app_slug', 'flosc');
        // v1.4.9: Use flow-aware URL so custom domains redirect correctly
        $app_url = $this->flosc->get_app_url();
        // v1.9.8: FloscAdmin-configured destination URL (empty = use app_url)
        // v10.0.0: Per-flow login_destination is resolved first; global
        // flosc_login_destination remains the fallback via get_setting().
        // Slice 2: explicit login_destination wins first; then multi-flow
        // routing per login_destination_mode; else single-flow app URL.
        $explicit_dest = flosc_get_setting('login_destination', '');
        if ($explicit_dest !== '') {
            $dest_url = esc_url_raw($explicit_dest);
        } else {
            $dest_user_id = ($user instanceof WP_User) ? (int) $user->ID : 0;
            $flow_count = 0;
            // is_callable() is visibility-aware: the builder is private, so a
            // cross-class call is intentionally skipped (no fatal) until the
            // method becomes callable. Multi-flow routing stays inert meanwhile.
            if ($dest_user_id > 0 && is_callable(array($this->flosc, 'flosc_build_user_flow_statuses'))) {
                $rows = $this->flosc->flosc_build_user_flow_statuses($dest_user_id);
                $flow_count = is_array($rows) ? count($rows) : 0;
            }
            if ($flow_count > 1) {
                $mode = flosc_get_setting('login_destination_mode', 'auto');
                if ($mode === 'core_profile') {
                    $dest_url = admin_url('profile.php');
                } elseif ($mode === 'custom_url') {
                    $accounts = flosc_get_setting('login_destination_accounts_url', '');
                    $dest_url = $accounts !== '' ? esc_url_raw($accounts) : admin_url('profile.php');
                } elseif (function_exists('bp_core_get_user_domain') && $dest_user_id) {
                    $dest_url = bp_core_get_user_domain($dest_user_id);
                } else {
                    $dest_url = admin_url('profile.php');
                }
            } else {
                $dest_url = $app_url;
            }
        }

        // Check 1: If requested redirect is already to FLOSC app, allow it
        if (!empty($requested_redirect_to) && strpos($requested_redirect_to, '/' . $app_slug) !== false) {
            return $requested_redirect_to;
        }

        // v1.4.9: Also check if requested redirect is to a custom domain flow
        if (!empty($requested_redirect_to)) {
            $flows = get_option('flosc_flows', []);
            foreach ($flows as $flow) {
                if (!empty($flow['custom_domain']) && strpos($requested_redirect_to, $flow['custom_domain']) !== false) {
                    return $requested_redirect_to;
                }
            }
        }

        // Check 2: If user has a pre-login quiz score cookie, redirect to configured destination
        $score_data = $this->flosc->get_signed_cookie('flosc_prelogin_score');
        if ($score_data && isset($score_data['score'])) {
            return $dest_url;
        }

        // Check 3: If referrer was the FLOSC app, redirect to configured destination
        $referer = wp_get_referer();
        if ($referer) {
            // Check slug-based URL
            if (strpos($referer, '/' . $app_slug) !== false) {
                return $dest_url;
            }
            // v1.4.9: Check custom domain referrers
            $referer_host = wp_parse_url($referer, PHP_URL_HOST);
            if ($referer_host) {
                $current_flow = $this->flosc->get_current_flow();
                if ($current_flow && !empty($current_flow['custom_domain'])) {
                    $flow_domain = strtolower(preg_replace('#^https?://#', '', trim($current_flow['custom_domain'])));
                    if (strtolower($referer_host) === $flow_domain) {
                        return $dest_url;
                    }
                }
            }
        }

        // Otherwise, respect WordPress's default redirect behavior
        // This allows normal WordPress posts/pages to work properly
        return $redirect_to;
    }

    /**
     * v9.5.7: Handle WooCommerce-specific login redirect
     * v1.0.0: ONLY redirect to FLOSC app if there's FLOSC context
     * v1.4.9: Custom domain support
     */
    public function handle_woocommerce_login_redirect($redirect, $user) {
        $app_slug = get_option('flosc_app_slug', 'flosc');

        // Only redirect if referrer was FLOSC app
        $referer = wp_get_referer();
        if ($referer && strpos($referer, '/' . $app_slug) !== false) {
            return $this->flosc->get_app_url();
        }
        
        // Otherwise, let WooCommerce handle it normally
        return $redirect;
    }

    /**
     * v10.0.0: Takeover WordPress native auth surfaces.
     *
     * Hooks into 'login_url' and (via takeover_wp_auth's register disable) routes
     * WP's native login/registration destinations into the FLOSC app so the site
     * exposes a SINGLE auth surface (no BuddyBoss double buttons / wp-login.php).
     *
     * Per-flow admin control: the flow setting 'takeover_wp_auth' (checkbox) turns
     * this on. When on, the native login screen is redirected to the flow's app URL
     * (which itself auto-opens the FLOSC Register-Or-LogIn modal via ?flosc_open_login=1).
     *
     * @param string $url    Current URL.
     * @param string $redirect Requested redirect_to (unused; FLOSC owns the funnel).
     * @param bool   $force_reauth Unused.
     * @return string
     */
    public function takeover_wp_auth_url($url, $redirect = '', $force_reauth = false) {
        if (!$this->is_takeover_enabled()) {
            return $url;
        }

        // Never break the password-reset surface. WP's lost-password screen is a
        // distinct surface; we only take over the SIGN-IN entry point, not recovery.
        if (defined('WP_CLI') && WP_CLI) {
            return $url;
        }

        // Keep a direct escape hatch for admins who must hit wp-login.php
        // (e.g. an expired interactive session forces a deliberate re-auth).
        // FLOSC's own auth still works for those users via the normal login path,
        // but we must not loop the admin back into a modal that can't complete
        // a re-login from a non-FLOSC-context screen.
        $in_admin = (is_admin() && !wp_doing_ajax()) || (function_exists('wp_get_referer') && strpos((string) wp_get_referer(), '/wp-admin/') !== false);
        if ($in_admin || $force_reauth) {
            return $url;
        }

        // Open the FLOSC Register/Log-in modal IN PLACE (email + SSO) rather than
        // navigating the user away to the full-page app. We point at the current
        // front-end page URL with ?flosc_open_login=1, which flosc-app.js consumes
        // to auto-open the combined auth modal without a cross-page jump.
        return add_query_arg('flosc_open_login', '1', $this->get_front_current_url());
    }

    /**
     * Current front-end page URL (no query args) for in-place auth modals.
     * Returns the flow app URL as a safe fallback if a front-end URL cannot be
     * determined (e.g. an odd CLI/server setup).
     *
     * @return string
     */
    private function get_front_current_url() {
        if ($this->is_takeover_enabled() && !is_admin() && !empty($_SERVER['REQUEST_URI'])) {
            $request_path = explode('?', sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), 2)[0];
            if ($request_path !== '' && $request_path !== false) {
                return home_url($request_path);
            }
        }
        return $this->flosc->get_app_url();
    }

    /**
     * Whether the current flow has WP native auth takeover enabled.
     *
     * @return bool
     */
    private function is_takeover_enabled() {
        $setting = flosc_get_setting('takeover_wp_auth', '');
        return $setting !== '' && filter_var((string) $setting, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Generate a FLOSC auth token for the given user.
     * Token is stateless — no database storage needed.
     *
     * @param int $user_id WordPress user ID
     * @param int $ttl Token lifetime in seconds (default: 24 hours)
     * @return string Base64-encoded token
     */
    public function generate_flosc_auth_token($user_id, $ttl = DAY_IN_SECONDS) {
        $expiry = time() + $ttl;
        $payload = $user_id . ':' . $expiry;
        $signature = hash_hmac('sha256', $payload, flosc_token_secret());
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary/JWT token encoding, not obfuscation
        return base64_encode($payload . ':' . $signature);
    }

    /**
     * Validate a FLOSC auth token and return the user ID.
     *
     * @param string $token Base64-encoded token
     * @return int|false User ID if valid, false otherwise
     */
    public function validate_flosc_auth_token($token) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary/JWT token decoding, not obfuscation
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        list($user_id, $expiry, $signature) = $parts;
        $user_id = intval($user_id);
        $expiry = intval($expiry);

        // Check expiry
        if (time() > $expiry) {
            return false;
        }

        // Verify HMAC signature
        $expected = hash_hmac('sha256', $user_id . ':' . $expiry, flosc_token_secret());
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        // Verify user exists
        $user = get_userdata($user_id);
        if (!$user || !$user->exists()) {
            return false;
        }

        return $user_id;
    }

    /**
     * Set the FLOSC auth token as a cookie with EMPTY domain.
     * Empty domain binds to the host that served the response
     * (the flow domain, flosc.ai, the WordPress host — whichever the user is on).
     * Do not use get_app_url() host: that can point at a different flow
     * domain than the current request and the browser will reject the cookie.
     *
     * @param string $token The auth token
     * @param int $ttl Lifetime in seconds
     */
    public function set_flosc_auth_cookie($token, $ttl = DAY_IN_SECONDS) {
        if (headers_sent()) {
            return;
        }

        setcookie('flosc_auth_token', $token, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_ssl(),
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Filter: determine_current_user (priority 20)
     * Authenticates users via FLOSC auth token when WordPress cookies fail.
     *
     * Checks (in order):
     * 1. X-FLOSC-Token request header (for API calls from JS)
     * 2. flosc_auth_token cookie (for page loads on custom domains)
     *
     * @param int $user_id Current user ID (0 if not authenticated)
     * @return int Authenticated user ID
     */
    public function authenticate_flosc_token($user_id) {
        // If WordPress already authenticated via cookies, skip
        if ($user_id) {
            return $user_id;
        }

        // Check X-FLOSC-Token header first (API calls)
        $token = '';
        if (!empty($_SERVER['HTTP_X_FLOSC_TOKEN'])) {
            $token = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FLOSC_TOKEN']));
        }

        // Fall back to the cookie for page loads. REST requests must present
        // the explicit X-FLOSC-Token header so a browser cookie does not
        // silently become a REST authentication credential.
        $is_rest_request = defined('REST_REQUEST') && REST_REQUEST;
        if (!$is_rest_request && empty($token) && !empty($_COOKIE['flosc_auth_token'])) {
            $token = sanitize_text_field(wp_unslash($_COOKIE['flosc_auth_token']));
        }

        if (empty($token)) {
            return $user_id;
        }

        $validated_user_id = $this->validate_flosc_auth_token($token);
        if ($validated_user_id) {
            // Set flag so allow_flosc_token_auth() can bypass WordPress nonce check
            $this->flosc_token_auth_used = true;

            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log("FLOSC Auth Token: Authenticated user {$validated_user_id} via token (cookie auth bypassed)");
            }
            return $validated_user_id;
        }

        return $user_id;
    }

    /**
     * AJAX: Instant logout — logs out and returns redirect URL to JS.
     */
    public function ajax_logout() {
        check_ajax_referer('flosc_logout', 'nonce');

        wp_logout();
        wp_send_json_success(['redirect' => $this->resolve_logout_destination()]);
    }

    /**
     * v10.0.0: Resolve the logout redirect destination.
     *
     * Priority (first match wins):
     * 1. Current flow's per-flow 'logout_destination'
     * 2. Entry-flow recall ('flosc_entry_flow' cookie) -> that flow's destination
     * 3. Legacy global 'logout_redirect_url'
     * 4. '/thank-you/' page when it exists (clear, brand-agnostic farewell)
     * 5. Home URL
     *
     * @return string
     */
    private function resolve_logout_destination() {
        $mode = flosc_get_setting('logout_destination_mode', 'entry_flow');
        $fallback = flosc_get_setting('logout_destination_fallback', '');

        $entry_flow = '';
        if (!empty($_COOKIE['flosc_entry_flow'])) {
            $entry_flow = sanitize_key((string) wp_unslash($_COOKIE['flosc_entry_flow']));
        }

        if ($mode === 'fallback') {
            return $fallback !== '' ? esc_url_raw($fallback) : $this->flosc->get_app_url();
        }

        if ($mode === 'flow') {
            $flow_dest = flosc_get_setting('logout_destination', '');
            return $flow_dest !== '' ? esc_url_raw($flow_dest) : $this->flosc->get_app_url();
        }

        // entry_flow (default)
        if ($entry_flow !== '') {
            $recall = flosc_get_setting('logout_destination', '', $entry_flow);
            if ($recall !== '') {
                return esc_url_raw($recall);
            }
            return $this->flosc->get_app_url();
        }

        return $fallback !== '' ? esc_url_raw($fallback) : $this->flosc->get_app_url();
    }

    /**
     * v10.0.0: Remember the entry flow so logout can recall it (per-flow logout
     * destination). Single-session, host-global (not flow-scoped), cleared on logout.
     *
     * @param string $flow_id Normalized flow id/stem.
     */
    public function set_entry_flow_cookie($flow_id) {
        if (headers_sent()) {
            return;
        }
        $flow_id = sanitize_key((string) $flow_id);
        if ($flow_id === '') {
            return;
        }
        // First visit only — don't overwrite the original entry flow mid-session.
        if (!empty($_COOKIE['flosc_entry_flow'])) {
            return;
        }
        setcookie('flosc_entry_flow', $flow_id, [
            'expires'  => time() + WEEK_IN_SECONDS,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Action: wp_logout — Clear FLOSC auth token + entry-flow recall cookies.
     */
    public function clear_flosc_auth_token() {
        if (headers_sent()) {
            return;
        }

        // Match set_flosc_auth_cookie: empty domain = host that served this response.
        setcookie('flosc_auth_token', '', [
            'expires'  => time() - YEAR_IN_SECONDS,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_ssl(),
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);

        // v10.0.0: Entry-flow recall is single-session state; wipe it on logout so
        // a later login starts a fresh entry-flow journey.
        setcookie('flosc_entry_flow', '', [
            'expires'  => time() - YEAR_IN_SECONDS,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Filter: rest_authentication_errors (priority 99)
     * Bypasses WordPress's nonce check when FLOSC token auth was used.
     *
     * WordPress hooks rest_cookie_check_errors at priority 100, which:
     * 1. Fires auth_cookie_malformed when NO cookie is present (cross-domain)
     * 2. Sets $wp_rest_auth_cookie = true
     * 3. Checks nonce → fails (no session token for HMAC match)
     * 4. Calls wp_set_current_user(0) → undoes our FLOSC token auth
     *
     * By returning true at priority 99, rest_cookie_check_errors receives
     * a non-empty $result and short-circuits without checking the nonce.
     *
     * @param WP_Error|null|true $result Current auth result
     * @return WP_Error|null|true Modified auth result
     */
    public function allow_flosc_token_auth($result) {
        // Don't override existing errors from other auth systems
        if (is_wp_error($result)) {
            return $result;
        }

        // Limit the nonce short-circuit to FLOSC REST routes. A FLOSC token
        // must not alter authentication for unrelated WordPress endpoints.
        if (!$this->is_flosc_rest_request()) {
            return $result;
        }

        // If FLOSC token was used, signal "auth succeeded" to skip nonce check
        if ($this->flosc_token_auth_used) {
            return true;
        }

        return $result;
    }

    /**
     * Determine whether the current REST request belongs to FLOSC.
     *
     * @return bool
     */
    private function is_flosc_rest_request() {
        $route = '';
        if (function_exists('rest_get_server')) {
            $server = rest_get_server();
            if (is_object($server) && method_exists($server, 'get_current_request')) {
                $request = $server->get_current_request();
                if (is_object($request) && method_exists($request, 'get_route')) {
                    $route = (string) $request->get_route();
                }
            }
        }

        if ($route === '' && isset($GLOBALS['wp']->query_vars['rest_route'])) {
            $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
        }

        return (bool) preg_match('#^/flosc/v1(?:/|$)#', $route);
    }

}
