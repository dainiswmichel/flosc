<?php
if (!defined('ABSPATH')) {
    exit;
}

trait FLOSC_REST_Trait {

    private function flosc_public_request_protection() {
        $defaults = [
            'enabled'                  => '1',
            'anonymous_chat_limit'     => 60,
            'authenticated_chat_limit' => 120,
            'anonymous_ivr_limit'      => 120,
            'metered_compute_limit'    => 20,
            'visitor_compute_limit'    => 5,
            'retry_after_429'          => '0',
        ];
        $settings = get_option('flosc_public_request_protection', []);
        return is_array($settings) ? array_merge($defaults, $settings) : $defaults;
    }

    /**
     * Permission Callbacks for REST API
     */
    public function check_metered_visitor_compute_permission($request) {
        $protection = $this->flosc_public_request_protection();
        if ($protection['enabled'] !== '1') {
            return true;
        }
        // Check rate limit first
        if (!$this->check_rate_limit('metered_compute', absint($protection['metered_compute_limit']), HOUR_IN_SECONDS)) {
            return new WP_Error('rate_limit', __('Too many requests. Please try again later.', 'flosc'), ['status' => 429]);
        }

        // Allow logged-in users with usage tracking
        if (is_user_logged_in()) {
            return true;
        }

        // For visitors: strict rate limit
        if (!$this->check_rate_limit('visitor_metered_compute', absint($protection['visitor_compute_limit']), HOUR_IN_SECONDS)) {
            return new WP_Error('rate_limit', __('Free tier limit reached. Please log in.', 'flosc'), ['status' => 429]);
        }

        return true;
    }

    /**
     * v9.4.2: Permission callback for public endpoints that need rate limiting
     *
     * Unlike check_metered_visitor_compute_permission(), this is for truly public endpoints
     * like IVR chat that don't consume expensive AI credits but should still
     * be protected from abuse.
     *
     * Limits: 60 requests/hour for logged-in users, 30/hour for visitors
     */
    public function check_public_endpoint_permission($request) {
        $endpoint = $request->get_route();
        $protection = $this->flosc_public_request_protection();
        if ($protection['enabled'] !== '1') {
            return true;
        }

        if (is_user_logged_in()) {
            if (!$this->check_rate_limit('public_auth_' . $endpoint, absint($protection['authenticated_chat_limit']), HOUR_IN_SECONDS)) {
                return new WP_Error('rate_limit', __('Too many requests. Please slow down.', 'flosc'), ['status' => 429]);
            }
            return true;
        }

        // Visitors get stricter limits.
        $limit = $endpoint === '/flosc/v1/chat' ? absint($protection['anonymous_chat_limit']) : absint($protection['anonymous_ivr_limit']);
        if (!$this->check_rate_limit('public_visitor_' . $endpoint, $limit, HOUR_IN_SECONDS)) {
            return new WP_Error('rate_limit', __('Rate limit reached. Please try again later.', 'flosc'), ['status' => 429]);
        }

        return true;
    }

    /**
     * Permission callback for routes that require a logged-in user.
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    public function check_authenticated_user_permission($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('flosc_login_required', __('Login is required.', 'flosc'), ['status' => 401]);
        }

        return true;
    }

    /**
     * §4: Permission callback for privileged admin-only REST actions.
     * Grants only to users who can manage_options; everyone else gets 403.
     */
    public function check_admin_endpoint_permission($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Administrator privileges required.', 'flosc'), ['status' => 403]);
        }
        return true;
    }

    /**
     * §4: Permission callback for buyer-scoped checkout/payment REST actions.
     * Requires a valid checkout-issued wp_rest nonce: X-WP-Nonce header first,
     * falling back to the _wpnonce request param. Handler then binds to the buyer.
     */
    public function check_checkout_endpoint_permission($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            $nonce = $request->get_param('_wpnonce');
        }
        $nonce = sanitize_text_field((string) $nonce);
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('forbidden', __('Invalid or missing security token.', 'flosc'), ['status' => 403]);
        }
        return true;
    }

    /**
     * Permission callback for checkout finalization endpoints.
     *
     * Requires the same REST nonce gate as checkout-start endpoints, plus a
     * server-issued checkout binding token that matches the browser session and,
     * when present, route/provider/flow/offer context.
     */
    public function check_checkout_finalization_permission($request) {
        $nonce_result = $this->check_checkout_endpoint_permission($request);
        if ($nonce_result !== true) {
            return $nonce_result;
        }

        $binding_token = sanitize_text_field((string) $request->get_param('binding_token'));
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));

        if ($binding_token === '' || $session_id === '') {
            return new WP_Error('flosc_checkout_binding_required', __('Checkout binding token and session_id are required.', 'flosc'), ['status' => 403]);
        }

        $binding_hash = hash_hmac('sha256', $binding_token, flosc_token_secret());
        $binding_record = get_transient('flosc_checkout_binding_' . $binding_hash);
        if (!is_array($binding_record)) {
            return new WP_Error('flosc_invalid_checkout_binding', __('Invalid checkout binding token.', 'flosc'), ['status' => 403]);
        }

        $bound_session_id = sanitize_text_field((string) ($binding_record['session_id'] ?? ''));
        if ($bound_session_id === '' || !hash_equals($bound_session_id, $session_id)) {
            return new WP_Error('flosc_checkout_session_mismatch', __('Checkout session mismatch.', 'flosc'), ['status' => 403]);
        }

        $request_provider = sanitize_key((string) $request->get_param('provider'));
        $route = (string) $request->get_route();
        if ($request_provider === '') {
            if (strpos($route, '/paypal/') !== false) {
                $request_provider = 'paypal';
            } elseif (strpos($route, '/complete-purchase') !== false) {
                $request_provider = 'stripe';
            }
        }
        $bound_provider = sanitize_key((string) ($binding_record['provider'] ?? ''));
        if ($request_provider !== '' && $bound_provider !== '' && !hash_equals($bound_provider, $request_provider)) {
            return new WP_Error('flosc_checkout_provider_mismatch', __('Checkout provider mismatch.', 'flosc'), ['status' => 403]);
        }

        $request_flow_id = sanitize_text_field((string) $request->get_param('flow_id'));
        $bound_flow_id = sanitize_text_field((string) ($binding_record['flow_id'] ?? ''));
        if ($request_flow_id !== '' && $bound_flow_id !== '' && !hash_equals($bound_flow_id, $request_flow_id)) {
            return new WP_Error('flosc_checkout_flow_mismatch', __('Checkout flow mismatch.', 'flosc'), ['status' => 403]);
        }

        $request_offer_id = sanitize_text_field((string) $request->get_param('offer_id'));
        $bound_offer_id = sanitize_text_field((string) ($binding_record['offer_id'] ?? ''));
        if ($request_offer_id !== '' && $bound_offer_id !== '' && !hash_equals($bound_offer_id, $request_offer_id)) {
            return new WP_Error('flosc_checkout_offer_mismatch', __('Checkout offer mismatch.', 'flosc'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Public nonce endpoint permission callback.
     *
     * Route only returns a WordPress REST nonce (no state mutation). Still
     * rate-limited so directory review does not treat it as an unbounded open surface.
     *
     * @param WP_REST_Request $request Request.
     * @return true|WP_Error
     */
    public function check_public_nonce_endpoint_permission($request) {
        $endpoint = $request->get_route();
        $bucket   = is_user_logged_in()
            ? 'public_nonce_auth_' . $endpoint
            : 'public_nonce_visitor_' . $endpoint;
        // Align with public endpoint visitor/auth ceilings.
        if (!$this->check_rate_limit($bucket, 60, 3600)) {
            return new WP_Error(
                'rate_limit',
                __('Too many requests. Please slow down.', 'flosc'),
                ['status' => 429]
            );
        }
        return true;
    }

    /**
     * Intentionally public webhook endpoint permission callback.
     *
     * Payment providers cannot present WordPress auth; signature checks happen in
     * the webhook handler itself.
     */
    public function check_webhook_endpoint_permission($request) {
        $provider = sanitize_key((string) $request->get_param('provider'));
        if (!in_array($provider, ['stripe', 'paypal', 'clickbank'], true)) {
            return new WP_Error('flosc_invalid_webhook_provider', __('Invalid webhook provider.', 'flosc'), ['status' => 400]);
        }
        return true;
    }

    /**
     * Normalize and validate IVR phase from request input.
     *
     * Missing phase defaults to freeline for compatibility. Unknown phases are
     * rejected to keep authorization behavior explicit and auditable.
     *
     * @param mixed $raw_phase Raw phase parameter.
     * @return string|WP_Error
     */
    private function normalize_ivr_phase($raw_phase) {
        $phase = (null === $raw_phase || '' === $raw_phase)
            ? 'freeline'
            : sanitize_key((string) $raw_phase);

        $valid_phases = ['freeline', 'login', 'offer', 'sale', 'content'];
        if (!in_array($phase, $valid_phases, true)) {
            return new WP_Error('flosc_invalid_phase', __('Invalid FLOSC phase.', 'flosc'), ['status' => 400]);
        }

        return $phase;
    }

    /**
     * Permission callback for /ivr-messages and /ivr/messages.
     * Public rate limiting for visitor funnel phases (including sale/offer).
     * Content phase requires membership entitlement.
     */
    public function check_ivr_messages_permission($request) {
        // Keep the existing public rate-limit behavior for the visitor funnel.
        $rate = $this->check_public_endpoint_permission($request);
        if ($rate !== true) {
            return $rate;
        }

        $phase = $this->normalize_ivr_phase($request->get_param('phase'));
        if (is_wp_error($phase)) {
            return $phase;
        }

        // Content phase is member-entitled only (sale stays public for guest purchase).
        if ($phase === 'content') {
            $user_id = get_current_user_id();
            // The meta is stored as the string 'true' / 'false' (see
            // FLOSC_Member_Access). A (bool) cast would treat the revoked value
            // 'false' as truthy and let a revoked member through — compare the
            // string explicitly.
            $has_member_access = $user_id && 'true' === get_user_meta($user_id, '_flosc_member_access', true);
            // Also honor flow-scoped membership when available.
            if (!$has_member_access && $user_id && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'sale')) {
                $sale = flosc()->sale();
                if ($sale && method_exists($sale, 'access') && method_exists($sale->access(), 'is_member')) {
                    $has_member_access = (bool) $sale->access()->is_member($user_id);
                }
            }
            if (!$has_member_access) {
                return new WP_Error('forbidden', __('Not entitled to this content.', 'flosc'), ['status' => 403]);
            }
        }

        return true;
    }

    /**
     * Build transient storage key for one visitor session's poll token hash.
     *
     * @param int $session_id Conversation/session ID.
     * @return string
     */
    private function get_admin_poll_token_storage_key($session_id) {
        return 'flosc_admin_poll_' . md5((string) absint($session_id));
    }

    /**
     * Mint poll token for visitor admin-message polling.
     *
     * @param int $session_id Conversation/session ID.
     * @return string
     */
    private function issue_admin_poll_token($session_id) {
        $session_id = absint($session_id);
        $token = wp_generate_password(43, false, false);
        $token_hash = hash_hmac('sha256', $token, flosc_token_secret());

        set_transient(
            $this->get_admin_poll_token_storage_key($session_id),
            [
                'session_id' => $session_id,
                'token_hash' => $token_hash,
                'issued_at' => time(),
            ],
            HOUR_IN_SECONDS
        );

        return $token;
    }

    /**
     * Verify poll token ownership for the provided session.
     *
     * @param int    $session_id Session ID.
     * @param string $poll_token Token provided by visitor.
     * @return bool
     */
    private function verify_admin_poll_token($session_id, $poll_token) {
        $session_id = absint($session_id);
        $poll_token = sanitize_text_field((string) $poll_token);
        if ($session_id <= 0 || $poll_token === '') {
            return false;
        }

        $record = get_transient($this->get_admin_poll_token_storage_key($session_id));
        if (!is_array($record) || empty($record['token_hash'])) {
            return false;
        }

        $presented_hash = hash_hmac('sha256', $poll_token, flosc_token_secret());
        return hash_equals((string) $record['token_hash'], $presented_hash);
    }

    /**
     * Verify that the current request can be tied to the provided visitor session.
     *
     * @param int $session_id Session ID.
     * @return bool
     */
    private function current_request_owns_chat_session($session_id) {
        $session_id = absint($session_id);
        if ($session_id <= 0) {
            return false;
        }

        if (!class_exists('FLOSC_Chat_Logger')) {
            return false;
        }

        return FLOSC_Chat_Logger::instance()->flosc_current_request_owns_session($session_id);
    }

    /**
     * Permission callback for visitor admin-message polling.
     *
     * Requires session ownership proof via poll token bound to session_id.
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    public function check_visitor_session_poll_permission($request) {
        // Canonicalize to the same crc32 id the chat logger and admin-inject
        // handlers store under; absint() on the raw Date.now() string would key
        // a different session and fail the ownership check (403), so the widget
        // would never receive admin messages.
        $session_id = $this->flosc_normalize_session_id(
            sanitize_text_field((string) ($request->get_param('session_id') ?? ''))
        );
        $poll_token = sanitize_text_field((string) $request->get_param('poll_token'));

        if ($session_id <= 0 || $poll_token === '') {
            return new WP_Error('flosc_missing_session_token', __('Missing session token.', 'flosc'), ['status' => 403]);
        }

        if (!$this->verify_admin_poll_token($session_id, $poll_token)) {
            return new WP_Error('flosc_invalid_session_token', __('Invalid session token.', 'flosc'), ['status' => 403]);
        }

        if (!$this->current_request_owns_chat_session($session_id)) {
            return new WP_Error('flosc_session_ownership_failed', __('Session ownership check failed.', 'flosc'), ['status' => 403]);
        }

        if (!$this->check_rate_limit('visitor_admin_poll_' . $session_id, 120, HOUR_IN_SECONDS)) {
            return new WP_Error('flosc_rate_limit', __('Too many requests.', 'flosc'), ['status' => 429]);
        }

        return true;
    }

    /**
     * Permission callback for minting visitor admin-message poll tokens.
     *
     * This route is intentionally visitor-facing but now requires ownership proof
     * of the requested session before a token is minted.
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    public function check_admin_messages_token_permission($request) {
        $public_check = $this->check_public_endpoint_permission($request);
        if (is_wp_error($public_check)) {
            return $public_check;
        }

        // Canonicalize to the same crc32 id the chat logger and admin-inject
        // handlers store under; absint() on the raw Date.now() string would key
        // a different session and fail the ownership check (403), so the widget
        // would never receive admin messages.
        $session_id = $this->flosc_normalize_session_id(
            sanitize_text_field((string) ($request->get_param('session_id') ?? ''))
        );
        if ($session_id <= 0) {
            return new WP_Error('invalid_session_id', __('Invalid session id.', 'flosc'), ['status' => 400]);
        }

        if (!$this->current_request_owns_chat_session($session_id)) {
            return new WP_Error('flosc_session_ownership_failed', __('Session ownership check failed.', 'flosc'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Public oEmbed proxy: returns provider-native player HTML for a media URL
     * via WordPress core oEmbed (YouTube, TikTok, Spotify, SoundCloud, Vimeo,
     * Apple Music, etc.). Results are cached in a transient. Only oEmbed-
     * whitelisted providers resolve, so arbitrary URLs cannot be embedded.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_oembed($request) {
        $url = esc_url_raw((string) $request->get_param('url'));
        $retry = '1' === sanitize_text_field((string) $request->get_param('retry'));
        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_REST_Response(['success' => false], 400);
        }

        // Cache per URL; store '' as a short negative-cache marker so unsupported
        // providers are not re-fetched on every render.
        $cache_key = 'flosc_oembed_' . md5($url);
        $cached = get_transient($cache_key);
        if (is_string($cached)) {
            if ($cached === '' && $retry) {
                // Retry path bypasses short-lived negative cache in case a provider
                // had a transient miss on first resolution.
            } else {
            return new WP_REST_Response(['success' => $cached !== '', 'html' => $cached]);
            }
        }

        $html  = wp_oembed_get($url, ['width' => 480]);
        $store = is_string($html) ? $html : '';
        $ttl = $store !== '' ? DAY_IN_SECONDS : (5 * MINUTE_IN_SECONDS);
        set_transient($cache_key, $store, $ttl);

        return new WP_REST_Response(['success' => $store !== '', 'html' => $store]);
    }

    /**
     * REST API Routes
     * v9.4.2: Added rate limiting to public endpoints
     */
    public function register_rest_routes() {
        // IVR Chat (primary endpoint)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // RAG Chat (v9.1.6 - AI with search capabilities)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/chat-rag', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat_with_rag'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v8.0.0: Admin-join poll — the visitor's widget fetches any human "(admin)"
        // messages an admin posted into its conversation. Read-only and low-sensitivity
        // (returns only the admin lines for a given session id), so it's public and
        // intentionally NOT behind the chat AI rate limit (the widget polls it).
        // POST (not GET) so the host/LiteSpeed page cache never serves a stale empty
        // result to the visitor's poll — GET responses to wp-json get cached.
        register_rest_route('flosc/v1', '/admin-messages', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_admin_messages_poll'],
            // Requires session-bound poll_token (minted via /admin-messages-token).
            'permission_callback' => [$this, 'check_visitor_session_poll_permission'],
        ]);

        // Visitor poll token minting for admin-message ownership proof.
        register_rest_route('flosc/v1', '/admin-messages-token', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_admin_messages_token'],
            'permission_callback' => [$this, 'check_admin_messages_token_permission'],
        ]);

        // Visitor session token count bootstrap for page-load display.
        // Purpose: resolve visitor token count on init without waiting for first
        // chat turn or admin-message poll ownership row.
        register_rest_route('flosc/v1', '/visitor-session-balance', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_visitor_session_balance'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // V→G additive grant with visitor session remaining (cross-domain SSO safe).
        register_rest_route('flosc/v1', '/apply-guest-token-grant', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_apply_guest_token_grant'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Quiz Submission (NEW: for collecting quiz answers)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        // v1.0.5: This endpoint returns bridge data status (reads, not writes)
        // Actual quiz storage: POST /quiz-result | Processing: POST /process-quiz
        register_rest_route('flosc/v1', '/quiz', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_quiz_submission'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v9.3.2: GET quiz questions for in-chat quiz
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/quiz', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quiz_questions'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v9.3.2: Store quiz results
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/quiz-result', [
            'methods' => 'POST',
            'callback' => [$this, 'store_quiz_result'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // AI Query
        register_rest_route('flosc/v1', '/ai-query', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_query'],
            'permission_callback' => [$this, 'check_metered_visitor_compute_permission'],
        ]);

        // Process Audio (for audio-based quiz types)
        register_rest_route('flosc/v1', '/process-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_audio'],
            'permission_callback' => [$this, 'check_metered_visitor_compute_permission'],
        ]);

        // v1.7.7: Transcribe alias — JS voice recording and quiz audio both call /transcribe
        register_rest_route('flosc/v1', '/transcribe', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_audio'],
            'permission_callback' => [$this, 'check_metered_visitor_compute_permission'],
        ]);

        // Process Quiz (for text-based quiz types)
        register_rest_route('flosc/v1', '/process-quiz', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_quiz'],
            'permission_callback' => [$this, 'check_metered_visitor_compute_permission'],
        ]);

        // Sessions
        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sessions'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'POST',
            'callback' => [$this, 'create_session'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v1.7.0: Get single session by ID
        register_rest_route('flosc/v1', '/sessions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_single_session'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v8.0.11: Delete a session
        register_rest_route('flosc/v1', '/sessions/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_session'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v8.0.11: Rename a session
        register_rest_route('flosc/v1', '/sessions/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'rename_session'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v1.7.1: Nonce refresh endpoint
        // v4.0.8: Open to visitors — they need a nonce to call payment endpoints before account creation
        register_rest_route('flosc/v1', '/nonce', [
            'methods' => 'GET',
            'callback' => function() {
                return new WP_REST_Response([
                    'success' => true,
                    'nonce' => wp_create_nonce('wp_rest'),
                ]);
            },
            'permission_callback' => [$this, 'check_public_nonce_endpoint_permission'],
        ]);

        // Offers
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/offers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_offers'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v1.6.2: Offer content source (HtmlFile, WooProduct, PostID)
        register_rest_route('flosc/v1', '/offer-content', [
            'methods' => 'GET',
            'callback' => [$this, 'get_offer_content'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Purchase
        register_rest_route('flosc/v1', '/purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_purchase'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v1.3.1: Sandbox Purchase (fun "pay what you want" testing)
        register_rest_route('flosc/v1', '/sandbox-purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_sandbox_purchase'],
            'permission_callback' => [$this, 'check_admin_endpoint_permission'],
        ]);

        // Free Lesson (v9.1.9) — v1.4.6: Accept both GET and POST (JS sends POST)
        register_rest_route('flosc/v1', '/free-lesson', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'get_free_lesson'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Create Payment Intent (for Stripe)
        // v4.0.8: Open to visitors — Stripe checkout starts before account creation
        register_rest_route('flosc/v1', '/create-payment-intent', [
            'methods' => 'POST',
            'callback' => [$this, 'create_payment_intent'],
            'permission_callback' => [$this, 'check_checkout_endpoint_permission'],
        ]);

        // v1.4.1: Complete purchase (verify and grant access after client-side payment)
        // v4.0.8: Open to visitors — account creation happens inside complete_purchase
        register_rest_route('flosc/v1', '/complete-purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_purchase'],
            'permission_callback' => [$this, 'check_checkout_finalization_permission'],
        ]);

        // PayPal - Create Order
        register_rest_route('flosc/v1', '/paypal/create-order', [
            'methods' => 'POST',
            'callback' => [$this, 'paypal_create_order'],
            'permission_callback' => [$this, 'check_checkout_endpoint_permission'],
        ]);

        // PayPal - Capture Order
        register_rest_route('flosc/v1', '/paypal/capture-order', [
            'methods' => 'POST',
            'callback' => [$this, 'paypal_capture_order'],
            'permission_callback' => [$this, 'check_checkout_finalization_permission'],
        ]);

        // PayPal Subscriptions - Get/create plans (auto-setup).
        // Checkout nonce: buyers may need plan IDs when config is empty; ensure_plans_exist is idempotent.
        register_rest_route('flosc/v1', '/paypal/get-plans', [
            'methods' => 'POST',
            'callback' => [$this, 'paypal_get_plans'],
            'permission_callback' => [$this, 'check_checkout_endpoint_permission'],
        ]);

        // PayPal Subscriptions — mint server purchase intent before JS createSubscription.
        register_rest_route('flosc/v1', '/paypal/prepare-subscription', [
            'methods' => 'POST',
            'callback' => [$this, 'paypal_prepare_subscription'],
            'permission_callback' => [$this, 'check_checkout_endpoint_permission'],
        ]);

        // PayPal Subscriptions - Activate after user approves
        register_rest_route('flosc/v1', '/paypal/activate-subscription', [
            'methods' => 'POST',
            'callback' => [$this, 'paypal_activate_subscription'],
            'permission_callback' => [$this, 'check_checkout_finalization_permission'],
        ]);

        // Checkout binding token (provider-neutral). The browser calls this once
        // when it begins checkout; the server mints a single-use token bound to
        // this session and returns it. The browser presents it back at payment
        // completion, which is how a completion handler proves the request is the
        // buyer's own browser rather than a replayed payment id. Public + rate
        // limited: minting a token bound to the caller's own session leaks nothing.
        register_rest_route('flosc/v1', '/checkout/binding', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_checkout_binding'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Webhooks (from payment providers like Stripe)
        // Public by design: payment providers cannot pass WordPress auth.
        // Security is enforced by webhook signature verification in handle_webhook().
        register_rest_route('flosc/v1', '/webhooks/(?P<provider>[a-z]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'check_webhook_endpoint_permission'],
        ]);

        // Access check
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/access', [
            'methods' => 'GET',
            'callback' => [$this, 'check_access'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Session bootstrap: rehydrate logged-in user + per-flow profile tokens when
        // the HTML shell was painted as visitor (token in localStorage / X-FLOSC-Token).
        register_rest_route('flosc/v1', '/session', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_session_bootstrap'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Token balance
        register_rest_route('flosc/v1', '/tokens', [
            'methods' => 'GET',
            'callback' => [$this, 'get_token_balance'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Purchase intents (affiliate system)
        register_rest_route('flosc/v1', '/intents', [
            'methods' => 'POST',
            'callback' => [$this, 'declare_intent'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        register_rest_route('flosc/v1', '/intents/(?P<id>[a-z0-9_]+)/offers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_intent_offers'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Referral
        register_rest_route('flosc/v1', '/referral', [
            'methods' => 'GET',
            'callback' => [$this, 'generate_referral'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Lessons
        // v1.7.8: Lesson list requires login (matches JS access gate)
        register_rest_route('flosc/v1', '/lessons', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lessons'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        register_rest_route('flosc/v1', '/lessons/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lesson'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        register_rest_route('flosc/v1', '/lessons/free', [
            'methods' => 'GET',
            'callback' => [$this, 'get_free_lesson'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // IVR Messages (v9.2.2)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/ivr-messages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ivr_messages'],
            'permission_callback' => [$this, 'check_ivr_messages_permission'],
        ]);

        // In-chat media players via WordPress core oEmbed (v8.0.1).
        register_rest_route('flosc/v1', '/oembed', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_oembed'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v1.0.4: Bridge Data endpoint (TASK-008) - quiz state between phases
        register_rest_route('flosc/v1', '/bridge-data', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bridge_data'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v1.0.5: Debug endpoint - full funnel state (TASK-108)
        // Only available when FLOSC_DEBUG is true
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            register_rest_route('flosc/v1', '/debug/funnel-state', [
                'methods' => 'GET',
                'callback' => [$this, 'get_debug_funnel_state'],
                'permission_callback' => [$this, 'check_admin_endpoint_permission'],
            ]);
        }

        // Store pre-login score
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/store-score', [
            'methods' => 'POST',
            'callback' => [$this, 'store_prelogin_score'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v8.0.0: Store visitor audio for deferred scoring
        // Visitors upload audio here instead of calling pronunciation API directly.
        // Audio is scored server-side after login/registration.
        register_rest_route('flosc/v1', '/store-visitor-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'store_visitor_audio'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Mark funnel completed (v3.0.4)
        register_rest_route('flosc/v1', '/funnel-complete', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_funnel_complete'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Test AI connection (v04_05)
        register_rest_route('flosc/v1', '/test-ai', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_test_ai'],
            'permission_callback' => [$this, 'check_admin_endpoint_permission'],
        ]);

        // v07.09: IVR message tracking
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/ivr/track', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ivr_track'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        // Task 4: Add entitlement gating — phase-level (permission callback) and per-message (handler filtering)
        register_rest_route('flosc/v1', '/ivr/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_ivr_get_messages'],
            'permission_callback' => [$this, 'check_ivr_messages_permission'],
        ]);

        // v1.4.0: Email registration (sends guest link — deferred user creation)
        register_rest_route('flosc/v1', '/register-email', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_email_registration'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Pre-SSO quiz stash — browser posts scored quiz data before OAuth redirect.
        register_rest_route('flosc/v1', '/stash-visitor-quiz', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_stash_visitor_quiz'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Guest profile setup — save nickname + optional password from in-chat card
        register_rest_route('flosc/v1', '/update-guest-profile', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_update_guest_profile'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v8.0.7: Score pending audio — called by JS after ANY login method (email, FB, Google).
        // JS sends the temp_id from localStorage. Server scores audio and returns results.
        // This replaces the unreliable cookie-based approach for SSO paths.
        register_rest_route('flosc/v1', '/score-pending-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_score_pending_audio'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Store browser-computed quiz data — no server-side re-scoring needed.
        // The browser already scored each phrase against the flow-configured pronunciation API during the quiz.
        // This endpoint accepts those results and stores them in user meta + moves audio files.
        // Used by SSO path (post-reload) when email registration didn't carry quiz_data.
        register_rest_route('flosc/v1', '/store-quiz-data', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_store_quiz_data'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // v1.9.0: AI Feedback — admin flags bad AI responses to improve quality
        register_rest_route('flosc/v1', '/feedback', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_save_feedback'],
            'permission_callback' => [$this, 'check_admin_endpoint_permission'],
        ]);

        register_rest_route('flosc/v1', '/feedback', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_feedback'],
            'permission_callback' => [$this, 'check_admin_endpoint_permission'],
        ]);

        register_rest_route('flosc/v1', '/feedback/(?P<feedback_id>[a-z0-9]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'handle_delete_feedback'],
            'permission_callback' => [$this, 'check_admin_endpoint_permission'],
        ]);

        // v1.9.0: AI Praise — admin reinforces good AI responses
        register_rest_route('flosc/v1', '/praises', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_save_praise'],
            'permission_callback' => [$this, 'check_admin_endpoint_permission'],
        ]);

        register_rest_route('flosc/v1', '/praises/(?P<praise_id>[a-z0-9_]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'handle_delete_praise'],
            'permission_callback' => [$this, 'check_admin_endpoint_permission'],
        ]);

        // v8.0.0: Redeem access code — grants role directly, no payment
        register_rest_route('flosc/v1', '/redeem-access-code', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_redeem_access_code'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Preview native offer coupon (fixed final $ / percent). Server validates UTC window.
        register_rest_route('flosc/v1', '/apply-offer-coupon', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_apply_offer_coupon'],
            'permission_callback' => [$this, 'check_authenticated_user_permission'],
        ]);

        // Client UI turns (free lesson, offer list, etc.) that skip /chat — still write Chat Logs.
        register_rest_route('flosc/v1', '/chat-log', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_client_chat_log'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);
    }
}
