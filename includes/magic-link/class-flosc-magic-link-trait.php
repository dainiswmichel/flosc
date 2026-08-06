<?php
/**
 * MagicLink / guest access-link and related one-time login tokens.
 *
 * Product model:
 * - MagicLink / convenience access link is for an EXISTING WordPress user only.
 * - Never create a WP user on: link click, mint, admin "send guest link", or admin approve+send.
 * - Account creation is separate: email registration (pending + verify), payment, SSO — not convenience-link mint.
 *
 * WordPress.org directory package (Pass 10 / E1 decision — product multi-use retained):
 * - MagicLink is DISABLED by default at package level (filter/constant default false).
 * - Per-flow enable is also default OFF (flow setting magic_access_links_enabled).
 * - Both package AND flow must be on for mint/consume.
 * - Product model when ON: multi-use guest access links —
 *   configurable window (default 30 days after first click) and max uses
 *   (default 10 for non-members); members unlimited.
 * - MagicLink never creates accounts (existing WP user only).
 * - Private deploys may re-enable package with:
 *     define( 'FLOSC_ENABLE_MAGIC_ACCESS_LINKS', true );
 *   or add_filter( 'flosc_enable_magic_access_links', '__return_true' );
 *   then enable the flow checkbox on Register & Login.
 * - When disabled: no mint, no flosc_magic consume, no flosc_wp_sync hop,
 *   no guest-link emails with access URLs.
 *
 * Auth-cookie inventory (remaining justified paths when MagicLink OFF):
 * - SSO OAuth success → log_user_in + short-lived flosc_login_token (cross-domain).
 * - User registration + email verification (no cookie on verify by design).
 * - Purchase-driven wp_create_user after payment/IPN proof (PayPal/ClickBank).
 * - Post-purchase instant cookie / emailed login token: default OFF
 *   (flosc_post_purchase_instant_login / flosc_post_purchase_login_token).
 * - Logged-in guest profile password set (re-issues cookie after wp_set_password).
 * - REST X-FLOSC-Token bootstrap: sets current user only, not auth cookie.
 *
 * File: includes/magic-link/class-flosc-magic-link-trait.php
 */

if (!defined('ABSPATH')) {
    exit;
}

trait FLOSC_Magic_Link_Trait {

    /**
     * Whether guest MagicLink access (flosc_magic) is enabled.
     *
     * Requires package-level allow AND per-flow enable (both default false).
     * Private deploys: define FLOSC_ENABLE_MAGIC_ACCESS_LINKS true (or filter true),
     * then enable the flow checkbox magic_access_links_enabled.
     *
     * @param string|null $flow_id Flow stem. Null = package gate only (cookie sync hop).
     *                             Empty string = current flow context. Non-empty = that flow.
     * @return bool
     */
    private function flosc_magic_access_links_enabled($flow_id = '') {
        // Package-level gate: constant wins when defined; else filter (default false).
        if (defined('FLOSC_ENABLE_MAGIC_ACCESS_LINKS')) {
            if (!(bool) FLOSC_ENABLE_MAGIC_ACCESS_LINKS) {
                return false;
            }
        } else {
            /**
             * Filter: package-level enable/disable MagicLink (mint + consume + guest emails + wp_sync).
             *
             * @param bool $enabled Default false for directory-safe ship.
             */
            if (!(bool) apply_filters('flosc_enable_magic_access_links', false)) {
                return false;
            }
        }

        // Cookie sync hop: Case 0 already validated the flow; package gate is enough.
        if ($flow_id === null) {
            return true;
        }

        // Per-flow gate: default off. Explicit checkbox on Register & Login.
        $flow_id = sanitize_key((string) $flow_id);
        if ($flow_id !== '') {
            return !empty(flosc_get_setting('magic_access_links_enabled', '', $flow_id));
        }
        return !empty(flosc_get_setting('magic_access_links_enabled', ''));
    }

    /**
     * Max MagicLink uses for non-members (members unlimited). Default 10.
     *
     * @param string $flow_id
     * @return int 1–100
     */
    private function flosc_magic_link_max_uses($flow_id = '') {
        $flow_id = sanitize_key((string) $flow_id);
        $raw = $flow_id !== ''
            ? flosc_get_setting('guest_link_max_uses', 10, $flow_id)
            : flosc_get_setting('guest_link_max_uses', 10);
        return max(1, min(100, absint($raw)));
    }

    /**
     * MagicLink active window in days after first click. Default 30.
     *
     * @param string $flow_id
     * @return int 1–365
     */
    private function flosc_magic_link_window_days($flow_id = '') {
        $flow_id = sanitize_key((string) $flow_id);
        $raw = $flow_id !== ''
            ? flosc_get_setting('guest_link_window_days', 30, $flow_id)
            : flosc_get_setting('guest_link_window_days', 30);
        return max(1, min(365, absint($raw)));
    }

    /**
     * Fail-closed redirect when MagicLink is disabled or invalid.
     */
    private function flosc_magic_access_disabled_redirect() {
        $login = wp_login_url(home_url('/'));
        wp_safe_redirect($login);
        exit;
    }

    public function handle_login_token() {
        // Auth/callback routing via query string (not a WP form nonce action).
        $get = array();
        foreach ( array(
            'flosc_verify_email',
            'flosc_magic',
            'flosc_wp_sync',
            'flosc_login_token',
            'flosc_sso_success',
            'redirect_to',
        ) as $flosc_qk ) {
            $raw = filter_input( INPUT_GET, $flosc_qk, FILTER_UNSAFE_RAW );
            if ( is_string( $raw ) && $raw !== '' ) {
                $get[ $flosc_qk ] = wp_unslash( $raw );
            }
        }

        // Email registration verification (not MagicLink login).
        if (!empty($get['flosc_verify_email'])) {
            $token = sanitize_text_field($get['flosc_verify_email']);
            $transient_key = 'flosc_verify_email_' . $token;
            $payload = get_transient($transient_key);
            if (!is_array($payload) || empty($payload['user_id'])) {
                wp_safe_redirect(add_query_arg('flosc_email_status', 'verify_invalid', home_url('/')));
                exit;
            }
            $user_id = absint($payload['user_id']);
            $user = get_userdata($user_id);
            if (!$user) {
                delete_transient($transient_key);
                wp_safe_redirect(add_query_arg('flosc_email_status', 'verify_invalid', home_url('/')));
                exit;
            }
            $payload_email = sanitize_email((string) ($payload['email'] ?? ''));
            if ($payload_email !== '' && strcasecmp($payload_email, (string) $user->user_email) !== 0) {
                delete_transient($transient_key);
                wp_safe_redirect(add_query_arg('flosc_email_status', 'verify_invalid', home_url('/')));
                exit;
            }
            $meta_tok = (string) get_user_meta($user_id, '_flosc_email_verify_token', true);
            if ($meta_tok !== '' && !hash_equals($meta_tok, $token)) {
                // Stale token after resend — leave current valid transient alone.
                wp_safe_redirect(add_query_arg('flosc_email_status', 'verify_invalid', home_url('/')));
                exit;
            }
            $result = $this->flosc_activate_email_account($user_id, $payload);
            if (is_wp_error($result)) {
                wp_safe_redirect(add_query_arg('flosc_email_status', 'verify_error', home_url('/')));
                exit;
            }
            // Consume token only after successful activation.
            delete_transient($transient_key);
            $flow_id = sanitize_key((string) ($payload['flow_id'] ?? ''));
            $dest = $this->get_guest_link_base_url($flow_id);
            if ($dest === '') {
                $dest = home_url('/');
            }
            $redir = esc_url_raw((string) ($payload['redirect_to'] ?? ''));
            if ($redir !== '' && wp_http_validate_url($redir)) {
                $dest = $redir;
            }
            wp_safe_redirect(add_query_arg('flosc_email_status', 'verified', $dest));
            exit;
        }

        // Case 0: Guest MagicLink access (existing users only; never creates accounts)
        if (!empty($get['flosc_magic'])) {
            $token        = sanitize_text_field($get['flosc_magic']);
            $transient_key = 'flosc_magic_' . $token;
            $payload      = get_transient($transient_key);
            $_payload_flow = is_array($payload)
                ? sanitize_key((string) ($payload['flow_id'] ?? ''))
                : '';
            if (!$this->flosc_magic_access_links_enabled($_payload_flow)) {
                $this->flosc_magic_access_disabled_redirect();
            }
            $offer_url = flosc_get_setting('guest_link_expired_offer_url', '', $_payload_flow ?: null);
            $max_uses  = $this->flosc_magic_link_max_uses($_payload_flow);
            $window_days = $this->flosc_magic_link_window_days($_payload_flow);
            $window_ttl  = $window_days * DAY_IN_SECONDS;

            // Invalid or expired token — redirect to offer page or show expired status
            if (!$payload || !isset($payload['status'])) {
                delete_transient($transient_key);
                if (!empty($offer_url)) {
                    wp_safe_redirect($offer_url); exit;
                }
                wp_safe_redirect(add_query_arg('flosc_guest_status', 'expired', remove_query_arg('flosc_magic'))); exit;
            }

            $email        = sanitize_email($payload['email']);
            $is_first_click = ($payload['status'] === 'pending');

            // Check membership before applying use-count limits — members of this guest-link flow get unlimited access
            $_pre_user = get_user_by('email', $email);
            $_link_flow = sanitize_key((string) ($payload['flow_id'] ?? get_user_meta($_pre_user ? $_pre_user->ID : 0, '_flosc_registration_flow', true)));
            $is_member_user = $_pre_user &&
                $this->sale_manager->access()->get_simple_state($_pre_user->ID, $_link_flow) === 'member';

            if ($is_first_click) {
                // Phase 1 → Phase 2: Activate link on first click
                $payload['status']         = 'active';
                $payload['first_clicked_at'] = time();
                if (!$is_member_user) {
                    $payload['use_count'] = 1;
                }
                set_transient($transient_key, $payload, $window_ttl);
            } else {
                // Phase 2: Enforce active window; enforce max-use limit only for non-members
                $expired = (
                    $payload['status'] !== 'active' ||
                    (time() - $payload['first_clicked_at']) > $window_ttl ||
                    (!$is_member_user && $payload['use_count'] >= $max_uses)
                );
                if ($expired) {
                    delete_transient($transient_key);
                    if (!empty($offer_url)) {
                        wp_safe_redirect($offer_url); exit;
                    }
                    wp_safe_redirect(add_query_arg('flosc_guest_status', 'expired', remove_query_arg('flosc_magic'))); exit;
                }
                if (!$is_member_user) {
                    // Increment use_count and re-save with remaining TTL
                    $payload['use_count']++;
                }
                $elapsed     = time() - $payload['first_clicked_at'];
                $remaining_ttl = max($window_ttl - $elapsed, DAY_IN_SECONDS);
                set_transient($transient_key, $payload, $remaining_ttl);
            }

            $remaining_after_use = $is_member_user ? null : ($max_uses - $payload['use_count']);

            // MagicLink is an access convenience for an EXISTING WP user only.
            // Never provision accounts from a URL token (dangerous + incorrect product model).
            $user_id = 0;
            $payload_uid = absint($payload['user_id'] ?? 0);
            if ($payload_uid > 0) {
                $by_id = get_userdata($payload_uid);
                if ($by_id && (! $email || strcasecmp((string) $by_id->user_email, (string) $email) === 0)) {
                    $user_id = (int) $by_id->ID;
                }
            }
            if ($user_id <= 0 && $email) {
                $existing_user = get_user_by('email', $email);
                if ($existing_user) {
                    $user_id = (int) $existing_user->ID;
                }
            }
            if ($user_id <= 0) {
                // Token without a known account: mint was wrong or data was purged. Fail closed.
                delete_transient($transient_key);
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                    flosc_log('FLOSC MagicLink: refuse login — no existing WP user for token email/user_id');
                }
                if (!empty($offer_url)) {
                    wp_safe_redirect($offer_url); exit;
                }
                wp_safe_redirect(add_query_arg('flosc_guest_status', 'error', remove_query_arg('flosc_magic'))); exit;
            }

            $existing_user = get_userdata($user_id);
            if (!$existing_user) {
                delete_transient($transient_key);
                wp_safe_redirect(add_query_arg('flosc_guest_status', 'error', remove_query_arg('flosc_magic'))); exit;
            }

            // MagicLink only for active accounts (email pending must verify first).
            if ($this->flosc_email_account_is_pending($user_id)) {
                delete_transient($transient_key);
                wp_safe_redirect(add_query_arg('flosc_email_status', 'pending', remove_query_arg('flosc_magic'))); exit;
            }

            // Optional: promote bare subscriber to THIS flow's configured guest role only.
            // Never hardcode product roles — set per floscFlow. Never demote privileged users.
            $level_flow   = sanitize_key((string) ($_link_flow ?? ''));
            $member_level = sanitize_key((string) flosc_get_setting('default_member_level', '', $level_flow ?: null));
            $guest_level  = sanitize_key((string) flosc_get_setting('default_guest_level', '', $level_flow ?: null));
            $roles_now    = (array) $existing_user->roles;
            $has_member   = ($member_level !== '' && in_array($member_level, $roles_now, true));
            $has_guest    = ($guest_level !== '' && in_array($guest_level, $roles_now, true));
            $is_privileged = user_can($user_id, 'manage_options') || user_can($user_id, 'edit_users');
            $is_bare = empty($roles_now)
                || (count($roles_now) === 1 && in_array('subscriber', $roles_now, true));
            if (!$has_member && !$has_guest && $guest_level !== '' && !$is_privileged && $is_bare) {
                $existing_user->set_role($guest_level);
            }

            // Log in the known user only
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            $flosc_token = $this->generate_flosc_auth_token($user_id);
            $this->set_flosc_auth_cookie($flosc_token);
            $wp_user = get_userdata($user_id);
            if ($wp_user) {
                $this->handle_user_login($wp_user->user_login, $wp_user);
            }
            $this->process_prelogin_data_for_user($user_id);

            // Store token for credential-save email (email-registered users)
            update_user_meta($user_id, '_flosc_magic_link_token', $token);

            // First click only: snapshot send count to user meta for admin profile visibility
            if ($is_first_click) {
                $log  = get_option('flosc_guest_link_log', []);
                $hash = md5(strtolower($email));
                if (isset($log[$hash]['count'])) {
                    update_user_meta($user_id, '_flosc_links_sent', (int) $log[$hash]['count']);
                }
            }

            // Persist quiz/session data on first click, or on later clicks when user
            // meta still lacks scored IPA results (email-scanner prefetch, DO race).
            $session_id        = sanitize_text_field($payload['session_id'] ?? '');
            $body_temp_id      = sanitize_text_field($payload['temp_id'] ?? '');
            $browser_quiz_data = isset($payload['quiz_data']) && is_array($payload['quiz_data']) ? $payload['quiz_data'] : null;
            if (!$body_temp_id && is_array($browser_quiz_data) && !empty($browser_quiz_data['tempId'])) {
                $body_temp_id = sanitize_text_field($browser_quiz_data['tempId']);
            }
            $has_temp_id       = ($body_temp_id && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $body_temp_id));
            $existing_quiz     = get_user_meta($user_id, '_flosc_last_quiz_data', true);
            $quiz_data_missing = !is_array($existing_quiz) || empty($existing_quiz['phrase_results']);

            if ($is_first_click || ($quiz_data_missing && ($browser_quiz_data || $session_id || $has_temp_id))) {
                $quiz_stored = false;

                // Browser-computed quiz data is authoritative; DO pull is fallback.
                if ($browser_quiz_data) {
                    $quiz_stored = (bool) $this->store_browser_quiz_data($user_id, $browser_quiz_data, $body_temp_id);
                } elseif ($session_id) {
                    $quiz_stored = $this->pull_session_from_do($user_id, $session_id);
                    if ($quiz_stored) {
                        $this->delete_session_from_do($session_id);
                    }
                }

                if (!$quiz_stored && $has_temp_id) {
                    update_user_meta($user_id, '_flosc_audio_temp_id', $body_temp_id);
                }
            }

            // Short-lived transients consumed by FLOSC_CONFIG on next page render
            // Members receive a marker value ('member') so memberLinkLogin can detect the magic-link login;
            // guests receive the remaining-use count for guestLinkRemaining.
            $_login_transient_val = $is_member_user ? 'member' : (int) $remaining_after_use;
            set_transient('flosc_just_guest_login_' . $user_id, $_login_transient_val, 10 * MINUTE_IN_SECONDS);
            if ($is_first_click) {
                set_transient('flosc_first_guest_login_' . $user_id, true, 10 * MINUTE_IN_SECONDS);
            }

            $payload_flow_id = sanitize_key((string) ($payload['flow_id'] ?? ''));
            if (!empty($payload_flow_id)) {
                update_user_meta($user_id, '_flosc_registration_flow', $payload_flow_id);
                $this->record_user_flow_usage($user_id, $payload_flow_id, 'magic_link');
                $this->set_flow_context($payload_flow_id);
            }

            $payload_redirect = esc_url_raw((string) ($payload['redirect_to'] ?? ''));
            $redirect_url = (wp_http_validate_url($payload_redirect))
                ? $payload_redirect
                : $this->get_guest_link_base_url($payload_flow_id);

            $sync_nonce   = wp_generate_password(32, false);
            set_transient('flosc_wp_sync_' . $sync_nonce, ['uid' => $user_id, 'url' => $redirect_url], 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect(home_url('/?flosc_wp_sync=' . rawurlencode($sync_nonce)));
            exit;
        }

        // Case 3: WP auth cookie sync hop (minted only from MagicLink Case 0).
        // Package gate only — Case 0 already enforced per-flow enable.
        if (!empty($get['flosc_wp_sync'])) {
            $sync_nonce = sanitize_text_field($get['flosc_wp_sync']);
            if (!$this->flosc_magic_access_links_enabled(null)) {
                delete_transient('flosc_wp_sync_' . $sync_nonce);
                $this->flosc_magic_access_disabled_redirect();
            }
            $data = get_transient('flosc_wp_sync_' . $sync_nonce);
            if (!$data) {
                wp_safe_redirect($this->get_app_url());
                exit;
            }
            $user_id      = (int) (is_array($data) ? $data['uid'] : $data);
            $redirect_url = is_array($data) && !empty($data['url']) ? (string) $data['url'] : $this->get_app_url();
            delete_transient('flosc_wp_sync_' . $sync_nonce);
            if ($user_id <= 0 || !get_userdata($user_id)) {
                wp_safe_redirect($this->get_app_url());
                exit;
            }
            $safe_redirect = wp_validate_redirect($redirect_url, $this->get_app_url());
            if ('' === $safe_redirect) {
                $safe_redirect = $this->get_app_url();
            }
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            wp_safe_redirect($safe_redirect);
            exit;
        }

        // Case 1: Cross-domain login token
        if (!empty($get['flosc_login_token'])) {
            $token = sanitize_text_field($get['flosc_login_token']);
            $transient_key = 'flosc_login_token_' . $token;
            $user_id = get_transient($transient_key);

            if (!$user_id) {
                return;
            }

            // One-time use — delete immediately
            delete_transient($transient_key);

            $user_id = absint(is_array($user_id) ? ($user_id['user_id'] ?? $user_id['uid'] ?? 0) : $user_id);
            $user = $user_id > 0 ? get_userdata($user_id) : false;
            if (!$user) {
                return;
            }

            // Set auth cookie on THIS domain (flosc.ai / lesaep.com) for known user only
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);

            // v3.0.0: Set FLOSC auth token cookie (empty domain = current host)
            // This works even when COOKIE_DOMAIN doesn't match the custom domain
            $flosc_token = $this->generate_flosc_auth_token($user_id);
            $this->set_flosc_auth_cookie($flosc_token);

            // v1.5.3: Call FLOSC's login handler directly (not do_action)
            $this->handle_user_login($user->user_login, $user);

            // v8.0.0: Pull quiz session from DO if pending.
            // JS set a flosc_pending_session cookie before SSO redirect.
            // The cookie is on THIS domain (lesaep.com), so it survived the OAuth round-trip.
            // Pull now so FLOSC_USER.lastQuizData is ready when the page renders.
            $this->pull_pending_session_from_do($user_id);

            // Redirect to clean URL (strip token + sso_success params)
            $clean_url = remove_query_arg(['flosc_login_token', 'flosc_sso_success']);
            wp_safe_redirect($clean_url);
            exit;
        }

        // Case 2: Same-domain SSO success (no token needed, cookie already valid)
        if (!empty($get['flosc_sso_success']) && is_user_logged_in()) {
            $user = wp_get_current_user();
            $this->handle_user_login($user->user_login, $user);

            // v8.0.0: Pull quiz session from DO if pending (same as Case 1)
            $this->pull_pending_session_from_do($user->ID);

            $clean_url = remove_query_arg('flosc_sso_success');
            wp_safe_redirect($clean_url);
            exit;
        }
    }

    /**
     * v8.0.0: Pull quiz session from DO at login time.
     *
     * Called during handle_login_token() — before the page renders.
     * JS sets a flosc_pending_session cookie before SSO redirect with the
     * DO session_id. We read it here, pull scores + audio from DO, store
     * in user meta, and clear the cookie. By the time the page loads,
     * FLOSC_USER.lastQuizData is already populated from user meta.
     *
     * Why server-side? The client-side authFetch POST to store-quiz-data
     * fails on custom domains because WP cookies are on the WP domain
     * (dainis.net) while the browser is on the flow domain (lesaep.com).
     * Even with FLOSC token auth, the timing is fragile. The server knows
     * the user is logged in (we just set the cookie) and knows the session_id
     * (from the cookie). Pull now, no client help needed.
     */


    /**
     * Resolve an EXISTING WP user for convenience-link mint only.
     * Never creates an account (admin send / approve / mint must not provision users).
     *
     * @param string $email
     * @return int|WP_Error User ID.
     */
    private function flosc_resolve_existing_user_for_convenience_link($email) {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return new WP_Error('flosc_invalid_email', 'A valid email is required.');
        }
        $existing = get_user_by('email', $email);
        if ($existing) {
            return (int) $existing->ID;
        }
        return new WP_Error(
            'flosc_no_user_for_access_link',
            'No WordPress user exists for that email. Convenience access links are for existing accounts only. Register, purchase, or use SSO first.'
        );
    }

    /**
     * Email registration only: create a pending subscriber (or return existing pending/active id).
     * Not used by convenience-link mint / admin send access link.
     *
     * @param string $email
     * @param string $flow_id
     * @return int|WP_Error User ID.
     */
    private function flosc_create_pending_email_registrant($email, $flow_id = '') {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return new WP_Error('flosc_invalid_email', 'A valid email is required.');
        }

        $existing = get_user_by('email', $email);
        if ($existing) {
            return (int) $existing->ID;
        }

        $username = $this->generate_username_from_email($email);
        $password = wp_generate_password(16, true, true);
        // Skip user_register token grants until email activation.
        $this->flosc_skip_registration_token_grants = true;
        $user_id  = wp_create_user($username, $password, $email);
        $this->flosc_skip_registration_token_grants = false;
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $user_id = (int) $user_id;
        $new_user = get_user_by('id', $user_id);
        $flow_id = sanitize_key((string) $flow_id);

        update_user_meta($user_id, '_flosc_registration_method', 'email');
        update_user_meta($user_id, '_flosc_registered_at', current_time('mysql'));
        if ($flow_id !== '') {
            update_user_meta($user_id, '_flosc_registration_flow', $flow_id);
        }
        if ($new_user) {
            $new_user->set_role('subscriber');
        }
        update_user_meta($user_id, '_flosc_email_account_status', 'pending');

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log("FLOSC: pending email registrant created email={$email} id={$user_id}");
        }

        return $user_id;
    }

    /**
     * Whether an email-registered account is still pending verification.
     */
    private function flosc_email_account_is_pending($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }
        $status = (string) get_user_meta($user_id, '_flosc_email_account_status', true);
        if ($status === 'pending') {
            return true;
        }
        // Legacy email users without status meta: treat as active.
        return false;
    }

    /**
     * Send email-registration verification message (not MagicLink).
     *
     * @param int   $user_id
     * @param string $flow_id
     * @param array  $attach temp_id, quiz_data, session_id, redirect_to, flow_id
     * @return bool
     */
    private function flosc_send_email_verification_message($user_id, $flow_id = '', $attach = array()) {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }
        if (!is_array($attach)) {
            $attach = array();
        }
        $flow_id = sanitize_key((string) ($attach['flow_id'] ?? $flow_id));
        $token = wp_generate_password(32, false, false);
        $payload = array(
            'user_id'     => (int) $user_id,
            'email'       => $user->user_email,
            'flow_id'     => $flow_id,
            'temp_id'     => sanitize_text_field((string) ($attach['temp_id'] ?? '')),
            'quiz_data'   => (isset($attach['quiz_data']) && is_array($attach['quiz_data'])) ? $attach['quiz_data'] : null,
            'session_id'  => sanitize_text_field((string) ($attach['session_id'] ?? '')),
            'redirect_to' => esc_url_raw((string) ($attach['redirect_to'] ?? '')),
            'created_at'  => time(),
        );
        set_transient('flosc_verify_email_' . $token, $payload, 2 * DAY_IN_SECONDS);
        update_user_meta($user_id, '_flosc_email_verify_token', $token);

        $base = $this->get_guest_link_base_url($flow_id);
        if ($base === '') {
            $base = home_url('/');
        }
        $verify_url = add_query_arg('flosc_verify_email', rawurlencode($token), $base);
        $context = $this->get_guest_email_context($flow_id, $user_id);
        $app_name = !empty($context['app_name']) ? $context['app_name'] : 'FLOSC';
        $safe_url = esc_url($verify_url);
        $safe_email = esc_html($user->user_email);

        /* translators: %s: application / product name */
        $subject = sprintf(__('Verify your email for %s', 'flosc'), $app_name);
        $body = '<!doctype html><html><body class="flosc-email-body">'
            . '<div class="flosc-email-wrap"><div class="flosc-email-card">'
            . '<h1 class="flosc-email-title">' . esc_html__('Verify your email', 'flosc') . '</h1>'
            . '<p class="flosc-email-lead">' . esc_html__('Click the button below to verify your email and activate your account.', 'flosc') . '</p>'
            . '<p class="flosc-email-cta-wrap"><a class="flosc-email-cta" href="' . $safe_url . '">' . esc_html__('Verify email address', 'flosc') . '</a></p>'
            . '<p class="flosc-email-copy">' . esc_html__('If the button does not work, copy and paste this link:', 'flosc') . '</p>'
            . '<p class="flosc-email-url"><a href="' . $safe_url . '">' . $safe_url . '</a></p>'
            . '<p class="flosc-email-foot">This message was sent to ' . $safe_email . '.</p>'
            . '</div></div></body></html>';

        return (bool) wp_mail(
            $user->user_email,
            $subject,
            $body,
            $this->get_flosc_mail_headers($flow_id, (int) $user_id, true)
        );
    }

    /**
     * Activate a pending email-registered account (verification click or admin).
     * Applies guest role/tokens, attaches optional quiz payload, sends welcome + MagicLink when enabled.
     *
     * @param int   $user_id
     * @param array $attach Optional override; otherwise user meta _flosc_email_pending_attach
     * @return true|WP_Error
     */
    public function flosc_activate_email_account($user_id, $attach = null) {
        $user_id = absint($user_id);
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('flosc_no_user', 'User not found.');
        }

        if (!is_array($attach)) {
            $stored = get_user_meta($user_id, '_flosc_email_pending_attach', true);
            $attach = is_array($stored) ? $stored : array();
        }

        $flow_id = sanitize_key((string) ($attach['flow_id'] ?? get_user_meta($user_id, '_flosc_registration_flow', true)));
        $was_pending = $this->flosc_email_account_is_pending($user_id);

        // Per-flow roles only — never hardcode product roles as all-flow defaults.
        $guest_level  = sanitize_key((string) flosc_get_setting('default_guest_level', '', $flow_id !== '' ? $flow_id : null));
        $member_level = sanitize_key((string) flosc_get_setting('default_member_level', '', $flow_id !== '' ? $flow_id : null));
        $roles = (array) $user->roles;
        $has_member = ($member_level !== '' && in_array($member_level, $roles, true));
        $has_guest  = ($guest_level !== '' && in_array($guest_level, $roles, true));
        $is_privileged = user_can($user_id, 'manage_options') || user_can($user_id, 'edit_users');
        $is_bare = empty($roles)
            || (count($roles) === 1 && in_array('subscriber', $roles, true));
        if (!$has_member && !$has_guest && $guest_level !== '' && !$is_privileged && $is_bare) {
            $user->set_role($guest_level);
        }

        update_user_meta($user_id, '_flosc_email_account_status', 'active');
        update_user_meta($user_id, '_flosc_email_verified_at', current_time('mysql'));
        if ($flow_id !== '') {
            update_user_meta($user_id, '_flosc_registration_flow', $flow_id);
            $this->record_user_flow_usage($user_id, $flow_id, 'email_verified');
            $token_provider = ($this->sale_manager && method_exists($this->sale_manager, 'get_provider'))
                ? $this->sale_manager->get_provider('tokens')
                : null;
            if ($this->flosc_user_should_receive_guest_tokens($user_id, $flow_id)) {
                $this->flosc_ensure_guest_token_baseline($user_id, $token_provider, $flow_id, 'Email verified guest baseline');
            }
        }

        // Attach browser quiz / session ids stored at registration.
        $body_temp_id = sanitize_text_field((string) ($attach['temp_id'] ?? ''));
        $browser_quiz_data = (isset($attach['quiz_data']) && is_array($attach['quiz_data'])) ? $attach['quiz_data'] : null;
        $session_id = sanitize_text_field((string) ($attach['session_id'] ?? ''));
        if ($browser_quiz_data) {
            $this->store_browser_quiz_data($user_id, $browser_quiz_data, $body_temp_id);
        } elseif ($session_id) {
            if ($this->pull_session_from_do($user_id, $session_id)) {
                $this->delete_session_from_do($session_id);
            }
        } elseif ($body_temp_id !== '') {
            update_user_meta($user_id, '_flosc_audio_temp_id', $body_temp_id);
        }

        delete_user_meta($user_id, '_flosc_email_pending_attach');
        $old_tok = (string) get_user_meta($user_id, '_flosc_email_verify_token', true);
        if ($old_tok !== '') {
            delete_transient('flosc_verify_email_' . $old_tok);
            delete_user_meta($user_id, '_flosc_email_verify_token');
        }

        if ($was_pending) {
            // Deferred from user_register: signup bonus when account becomes active.
            $token_provider = ($this->sale_manager && method_exists($this->sale_manager, 'get_provider'))
                ? $this->sale_manager->get_provider('tokens')
                : null;
            if ($token_provider && method_exists($token_provider, 'grant_signup_bonus')) {
                $token_provider->grant_signup_bonus($user_id);
            }
            do_action('flosc_user_registered', $user_id, 'email', array('flow_id' => $flow_id, 'verified' => true));
            do_action('flosc_email_account_activated', $user_id, $flow_id);
            // Welcome + MagicLink only on transition pending → active.
            $this->flosc_send_email_registration_welcome($user_id, $flow_id, $attach);
        }

        return true;
    }

    /**
     * Welcome email after email verification. Includes MagicLink when enabled.
     */
    private function flosc_send_email_registration_welcome($user_id, $flow_id = '', $attach = array()) {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }
        $flow_id = sanitize_key((string) $flow_id);
        $context = $this->get_guest_email_context($flow_id, $user_id);
        $chat_url = !empty($context['chat_url']) ? $context['chat_url'] : $this->get_guest_link_base_url($flow_id);
        $app_name = !empty($context['app_name']) ? $context['app_name'] : 'FLOSC';
        $link_name = !empty($context['link_name']) ? $context['link_name'] : __('Access link', 'flosc');

        $cta_url = $chat_url;
        $cta_label = __('Continue', 'flosc');
        $magic_line = '';

        if ($this->flosc_magic_access_links_enabled($flow_id)) {
            $redirect = '';
            if (is_array($attach) && !empty($attach['redirect_to']) && wp_http_validate_url($attach['redirect_to'])) {
                $redirect = esc_url_raw($attach['redirect_to']);
            }
            $token = $this->flosc_mint_magic_access_for_user($user_id, array(
                'status' => 'active',
                'flow_id' => $flow_id,
                'redirect_to' => $redirect !== '' ? $redirect : $chat_url,
                'temp_id' => is_array($attach) ? (string) ($attach['temp_id'] ?? '') : '',
                'quiz_data' => (is_array($attach) && isset($attach['quiz_data']) && is_array($attach['quiz_data'])) ? $attach['quiz_data'] : null,
                'session_id' => is_array($attach) ? (string) ($attach['session_id'] ?? '') : '',
                'ttl' => 30 * DAY_IN_SECONDS,
            ));
            if (!is_wp_error($token) && $token !== '') {
                $cta_url = add_query_arg('flosc_magic', rawurlencode($token), $chat_url);
                $cta_label = $link_name;
                $magic_line = '<p class="flosc-email-copy">' . esc_html__('Use your access link to open the chat without re-entering a password.', 'flosc') . '</p>';
            }
        }

        $safe_url = esc_url($cta_url);
        $safe_email = esc_html($user->user_email);
        /* translators: %s: application / product name */
        $subject = sprintf(__('Welcome to %s', 'flosc'), $app_name);
        $body = '<!doctype html><html><body class="flosc-email-body">'
            . '<div class="flosc-email-wrap"><div class="flosc-email-card">'
            /* translators: %s: application / product name */
            . '<h1 class="flosc-email-title">' . esc_html(sprintf(__('Welcome to %s', 'flosc'), $app_name)) . '</h1>'
            . '<p class="flosc-email-lead">' . esc_html__('Your email is verified and your account is active.', 'flosc') . '</p>'
            . $magic_line
            . '<p class="flosc-email-cta-wrap"><a class="flosc-email-cta" href="' . $safe_url . '">' . esc_html($cta_label) . '</a></p>'
            . '<p class="flosc-email-copy">' . esc_html__('If the button does not work, copy and paste this link:', 'flosc') . '</p>'
            . '<p class="flosc-email-url"><a href="' . $safe_url . '">' . $safe_url . '</a></p>'
            . '<p class="flosc-email-foot">This message was sent to ' . $safe_email . '.</p>'
            . '</div></div></body></html>';

        $ok = (bool) wp_mail(
            $user->user_email,
            $subject,
            $body,
            $this->get_flosc_mail_headers($flow_id, (int) $user_id, true)
        );
        if ($ok) {
            update_user_meta($user_id, '_flosc_email_welcome_sent_at', time());
        }
        return $ok;
    }




    /**
     * Mint a MagicLink access token for an EXISTING WordPress user only.
     *
     * @param int   $user_id
     * @param array $args flow_id, redirect_to, temp_id, quiz_data, session_id, status, ttl, reuse_token.
     * @return string|WP_Error Token string on success.
     */
    private function flosc_mint_magic_access_for_user($user_id, array $args = []) {
        $mint_flow = sanitize_key((string) ($args['flow_id'] ?? ''));
        if (!$this->flosc_magic_access_links_enabled($mint_flow)) {
            return new WP_Error(
                'flosc_magic_disabled',
                'Magic access links are disabled (package and/or this flow).'
            );
        }
        $user_id = absint($user_id);
        $user    = $user_id > 0 ? get_userdata($user_id) : false;
        if (!$user || empty($user->user_email) || !is_email($user->user_email)) {
            return new WP_Error(
                'flosc_magic_requires_user',
                'Magic access links may only be issued for an existing WordPress user account.'
            );
        }

        $flow_id     = sanitize_key((string) ($args['flow_id'] ?? ''));
        $redirect_to = esc_url_raw((string) ($args['redirect_to'] ?? ''));
        if ($redirect_to !== '' && !wp_http_validate_url($redirect_to)) {
            $redirect_to = '';
        }
        $status = sanitize_key((string) ($args['status'] ?? 'pending'));
        if (!in_array($status, ['pending', 'active'], true)) {
            $status = 'pending';
        }
        $ttl = isset($args['ttl']) ? absint($args['ttl']) : (7 * DAY_IN_SECONDS);
        if ($ttl < MINUTE_IN_SECONDS) {
            $ttl = 7 * DAY_IN_SECONDS;
        }

        $reuse = !empty($args['reuse_token']);
        $token = '';
        if ($reuse) {
            $token = (string) get_user_meta($user_id, '_flosc_magic_link_token', true);
        }
        if ($token === '') {
            $token = wp_generate_password(32, false, false);
        }

        $payload = [
            'status'      => $status,
            'user_id'     => $user_id,
            'email'       => $user->user_email,
            'temp_id'     => sanitize_text_field((string) ($args['temp_id'] ?? '')),
            'quiz_data'   => (isset($args['quiz_data']) && is_array($args['quiz_data'])) ? $args['quiz_data'] : null,
            'session_id'  => sanitize_text_field((string) ($args['session_id'] ?? '')),
            'flow_id'     => $flow_id,
            'redirect_to' => $redirect_to,
            'created_at'  => time(),
        ];
        if ($status === 'active') {
            $payload['first_clicked_at'] = time();
            $payload['use_count']        = 0;
        }

        $transient_key = 'flosc_magic_' . $token;
        set_transient($transient_key, $payload, $ttl);
        update_user_meta($user_id, '_flosc_magic_link_token', $token);

        return $token;
    }

    /**
     * Rotate/revoke magic-link artifacts after password change for magic-link users.
     *
     * Triggered by wp_set_password in the framework hook wiring.
     * - Old token transient is invalidated immediately (old emailed links stop working).
     * - If magic links are enabled, a fresh token is minted and seeded as active.
     * - Optional refresh email can be enabled via filter.
     *
     * @param string       $password      New password (unused).
     * @param int          $user_id       User ID.
     * @param WP_User|null $old_user_data Previous user object.
     */
    public function flosc_handle_password_change_revoke_magic_access($password, $user_id, $old_user_data = null) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $had_token = (string) get_user_meta($user_id, '_flosc_magic_link_token', true);
        $is_magic_user = !empty($had_token)
            || (bool) get_user_meta($user_id, '_flosc_magic_link_user_credentials_set', true);
        if (!$is_magic_user) {
            return;
        }

        // Always invalidate the prior token payload so previously sent links cannot be replayed.
        if ($had_token !== '') {
            delete_transient('flosc_magic_' . $had_token);
        }

        $flow_id = sanitize_key((string) get_user_meta($user_id, '_flosc_registration_flow', true));

        // Package and/or flow off: keep magic links shelved after revocation.
        if (!$this->flosc_magic_access_links_enabled($flow_id)) {
            delete_user_meta($user_id, '_flosc_magic_link_token');
            return;
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email) || !is_email($user->user_email)) {
            delete_user_meta($user_id, '_flosc_magic_link_token');
            return;
        }

        $redirect_to = $this->get_guest_link_base_url($flow_id);
        $new_token = wp_generate_password(32, false, false);

        $payload = [
            'status'           => 'active',
            'user_id'          => $user_id,
            'email'            => $user->user_email,
            'temp_id'          => '',
            'quiz_data'        => null,
            'session_id'       => '',
            'flow_id'          => $flow_id,
            'redirect_to'      => $redirect_to,
            'created_at'       => time(),
            'first_clicked_at' => time(),
            'use_count'        => 0,
        ];

        set_transient('flosc_magic_' . $new_token, $payload, 30 * DAY_IN_SECONDS);
        update_user_meta($user_id, '_flosc_magic_link_token', $new_token);

        $send_refresh = (bool) apply_filters('flosc_magic_link_send_on_password_change', false, $user_id, $flow_id);
        if ($send_refresh) {
            $this->send_guest_link_email($user->user_email, $new_token, $flow_id);
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log("FLOSC MagicLink: rotated token on password change for user {$user_id}");
        }
    }


    /**
     * Issue (or reuse) the user's magic-link URL for a flow. Members keep a 30-day active token.
     */
    /**
     * Collaborator API: FLOSC_Email member welcome magic link.
     */
    public function flosc_user_magic_url($user, $context, $flow_id) {
        if (!$user || empty($user->ID)) {
            return '';
        }
        $token = $this->flosc_mint_magic_access_for_user((int) $user->ID, [
            'status'       => 'active',
            'flow_id'      => $flow_id,
            'redirect_to'  => is_array($context) ? (string) ($context['chat_url'] ?? '') : '',
            'ttl'          => 30 * DAY_IN_SECONDS,
            'reuse_token'  => true,
        ]);
        if (is_wp_error($token) || $token === '') {
            return '';
        }
        $chat_url = is_array($context) ? (string) ($context['chat_url'] ?? '') : '';
        if ($chat_url === '') {
            $chat_url = $this->get_guest_link_base_url($flow_id);
        }
        return add_query_arg('flosc_magic', rawurlencode($token), $chat_url);
    }

    public function handle_email_registration($request) {
        $email = sanitize_email($request->get_param('email'));
        $flow_id = sanitize_key((string) $request->get_param('flow_id'));
        $redirect_to_raw = esc_url_raw((string) $request->get_param('redirect_to'));
        $redirect_to = wp_http_validate_url($redirect_to_raw) ? $redirect_to_raw : '';

        if (empty($email) || !is_email($email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Please enter a valid email address.',
            ), 400);
        }

        if ($this->is_guest_request_email_blocked($email)) {
            return new WP_REST_Response(array(
                'success' => true,
                'verification_sent' => false,
                'magic_link_sent' => false,
                'message' => 'Guest account request received. Dainis will respond by email.',
                'nonce' => wp_create_nonce('wp_rest'),
            ));
        }

        $body_temp_id = sanitize_text_field($request->get_param('temp_id') ?? '');
        $has_temp_id = ($body_temp_id && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $body_temp_id));
        $browser_quiz_data = $request->get_param('quiz_data');
        $session_id = sanitize_text_field($request->get_param('session_id') ?? '');

        $existing = get_user_by('email', $email);
        if ($existing && !$this->flosc_email_account_is_pending((int) $existing->ID)) {
            // Active account: do not re-run pending registration.
            return new WP_REST_Response(array(
                'success' => true,
                'verification_sent' => false,
                'magic_link_sent' => false,
                'message' => 'An account with this email already exists. Sign in with your password, social login, or request an access link from the site administrator.',
                'nonce' => wp_create_nonce('wp_rest'),
            ));
        }

        $user_id = $this->flosc_create_pending_email_registrant($email, $flow_id);
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'We could not prepare your account right now. Please try again.',
            ), 500);
        }
        $user_id = (int) $user_id;

        // Existing pending: keep pending. New: already pending from create helper.
        if (!$this->flosc_email_account_is_pending($user_id)) {
            update_user_meta($user_id, '_flosc_email_account_status', 'pending');
        }
        if ($flow_id !== '') {
            update_user_meta($user_id, '_flosc_registration_flow', $flow_id);
        }
        update_user_meta($user_id, '_flosc_registration_method', 'email');

        $attach = array(
            'temp_id' => $has_temp_id ? $body_temp_id : '',
            'quiz_data' => is_array($browser_quiz_data) ? $browser_quiz_data : null,
            'session_id' => $session_id,
            'redirect_to' => $redirect_to,
            'flow_id' => $flow_id,
        );
        update_user_meta($user_id, '_flosc_email_pending_attach', $attach);

        $sent = $this->flosc_send_email_verification_message($user_id, $flow_id, $attach);
        if (!$sent) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'We could not send your verification email right now. Please try again.',
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'verification_sent' => true,
            'magic_link_sent' => false,
            'message' => 'Check your email and click the verification link to activate your account.',
            'nonce' => wp_create_nonce('wp_rest'),
        ));
    }

    /**
     * Task 5: Post-purchase single-use login token for cross-domain access.
     * 
     * Fires on flosc_purchase_completed (all payment methods). Generates a single-use,
     * short-lived login token stored in a transient. The token allows immediate cross-domain
     * login via ?flosc_login_token=TOKEN on the purchase flow's custom domain.
     * 
     * Separate from guest magic links: guest links are multi-use (10x/30d), post-purchase
     * tokens are single-use to prevent shared/forwarded access after purchase.
     * 
     * @param int $user_id User who just purchased
     * @param array $purchase_data Offer details from flosc_purchase_completed action
     */
    public function handle_purchase_completed($user_id, $purchase_data = []) {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) return;
        if (!is_array($purchase_data)) $purchase_data = [];

        // Extract flow context
        $flow_id = sanitize_key((string) ($purchase_data['flow_id'] ?? get_user_meta($user_id, '_flosc_registration_flow', true)));
        if (empty($flow_id)) return; // No flow context — skip

        $context = $this->get_guest_email_context($flow_id, $user_id);
        $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
        $subject_tpl = trim((string) ($settings['purchase_confirmation_subject'] ?? 'Your {app_name} purchase is complete!'));
        $body_tpl = trim((string) ($settings['purchase_confirmation_body'] ?? "Hi {name}!\n\nThank you for your purchase!\n\nYour access is now active. Log in with the account email used at checkout to continue.\n\n{chat_url}\n\n— The {team_name}"));

        // Pass 2 / E1: passwordless post-purchase login token is OFF by default.
        // Private deploys: add_filter( 'flosc_post_purchase_login_token', '__return_true' );
        $mint_login_token = (bool) apply_filters('flosc_post_purchase_login_token', false, $user_id, $purchase_data);
        $login_url = '';
        $button_url = $context['chat_url'];
        $button_label = 'Continue';

        if ($mint_login_token) {
            $token = wp_generate_password(32, false, false);
            $transient_key = 'flosc_login_token_' . $token;
            // Single-use consume in handle_login_token Case 1.
            // Best-in-class: short TTL (5 min), not day-long reusable windows.
            set_transient($transient_key, $user_id, 5 * MINUTE_IN_SECONDS);
            $login_url = add_query_arg('flosc_login_token', rawurlencode($token), $context['chat_url']);
            $button_url = $login_url;
            $button_label = 'Access Now';
        }

        $subject = $this->replace_guest_email_placeholders($subject_tpl, $user, 0);
        $text = $this->replace_guest_email_placeholders($body_tpl, $user, 0);
        $text = str_replace(
            array('{magic_url}', '{chat_url}', '{login_url}'),
            array($login_url !== '' ? $login_url : $context['chat_url'], $context['chat_url'], $button_url),
            $text
        );
        $body = $this->flosc_email_html_card($context, $user, $text, $button_url, $button_label);

        $ok = wp_mail($user->user_email, $subject, $body, $this->get_flosc_mail_headers($flow_id, $user_id, true));
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log(
                'FLOSC post-purchase email: user ' . $user_id
                . ' flow ' . $flow_id
                . ' login_token=' . ($mint_login_token ? '1' : '0')
                . ' sent=' . ($ok ? '1' : '0')
            );
        }
    }

    /**
     * Subscribe a user to the newsletter (idempotent); sends the welcome on first opt-in.
     * The chatbot opt-in flow can call this for a logged-in user.
     */

    private function send_guest_link_email($email, $token, $flow_id = '') {
        $context = $this->get_guest_email_context($flow_id);
        $magic_url  = add_query_arg('flosc_magic', rawurlencode($token), $context['chat_url']);
        $link_name  = $context['link_name'];
        $raw_subject = trim((string) (($context['settings']['guest_link_email_subject'] ?? '')));
        if ($raw_subject === '') {
            $raw_subject = 'Your {link_name}';
        }
        $subject    = str_replace('{link_name}', $link_name, $raw_subject);

        $safe_link_name = esc_html($link_name);
        $safe_email     = esc_html($email);
        $safe_url       = esc_url($magic_url);
        $max_uses       = $this->flosc_magic_link_max_uses($flow_id);
        $window_days    = $this->flosc_magic_link_window_days($flow_id);

        $body = '<!doctype html><html><body class="flosc-email-body">'
            . '<div class="flosc-email-wrap">'
            . '<div class="flosc-email-card">'
            . '<h1 class="flosc-email-title">Your ' . $safe_link_name . ' is ready</h1>'
            . '<p class="flosc-email-lead">Click the button below to access the chat, view your quiz score, free lessons, and upgrade options.</p>'
            . '<p class="flosc-email-copy">Your link can be used up to ' . (int) $max_uses . ' times within ' . (int) $window_days . ' days after first click (members unlimited).</p>'
            . '<p class="flosc-email-cta-wrap"><a class="flosc-email-cta" href="' . $safe_url . '">' . $safe_link_name . '</a></p>'
            . '<p class="flosc-email-copy">If the button does not work, copy and paste this link into your browser:</p>'
            . '<p class="flosc-email-url"><a href="' . $safe_url . '">' . $safe_url . '</a></p>'
            . '<p class="flosc-email-foot">This message was sent to ' . $safe_email . '.</p>'
            . '</div></div></body></html>';

        $headers = $this->get_flosc_mail_headers($flow_id, 0, true);

        return wp_mail($email, $subject, $body, $headers);
    }

    /**
     * Resolve a stable app URL for guest-link emails and login redirects.
     * Uses explicit flow settings first, then current host detection fallback.
     */


    /**
     * Resolve a stable app URL for guest-link emails and login redirects.
     * Uses explicit flow settings first, then current host detection fallback.
     */
    /**
     * Collaborator API: FLOSC_Email guest context / redirects.
     */
    public function get_guest_link_base_url($flow_id = '') {
        $flow_id = sanitize_key((string) $flow_id);
        if (!empty($flow_id)) {
            $settings_key = 'flosc_flow_' . $flow_id;
            $settings = get_option($settings_key, []);

            $configured_redirect = trim((string) ($settings['sso_post_login_redirect_url'] ?? ''));
            if (!empty($configured_redirect)) {
                $configured_redirect = esc_url_raw($configured_redirect);
                if (wp_http_validate_url($configured_redirect)) {
                    return trailingslashit($configured_redirect);
                }
            }

            $domain = trim((string) ($settings['domain'] ?? ''));
            if (!empty($domain)) {
                $domain = preg_replace('#^https?://#', '', $domain);
                $domain = rtrim($domain, '/');
                if (!empty($domain)) {
                    return 'https://' . $domain . '/';
                }
            }
        }

        return $this->get_app_url();
    }

    /**
     * Send a warning email when an email has requested 6+ guest links.
     * Friendly but firm — covers both genuine learners and potential abusers.
     */


    /**
     * Send a warning email when an email has requested 6+ guest links.
     * Friendly but firm — covers both genuine learners and potential abusers.
     */
    private function send_guest_link_warning_email($email, $count) {
        $context     = $this->get_guest_email_context('');
        $link_name   = !empty($context['link_name']) ? $context['link_name'] : 'Guest Access Link';
        $upgrade_url = flosc_get_setting('guest_link_upgrade_url', '');
        $team_name   = !empty($context['team_name']) ? $context['team_name'] : 'Team';
        $safe_email  = esc_html($email);
        $safe_name   = esc_html($link_name);
        $safe_upg    = $upgrade_url ? esc_url($upgrade_url) : '';
        $safe_team   = esc_html($team_name);

        $subject = "A note about your {$link_name} requests";

        $body = '<!doctype html><html><body class="flosc-email-body">'
            . '<div class="flosc-email-wrap">'
            . '<div class="flosc-email-card">'
            . '<h1 class="flosc-email-title flosc-email-title--small">A quick note</h1>'
            . '<p class="flosc-email-lead flosc-email-lead--spaced">We\'ve noticed that ' . $safe_email . ' has requested <strong>' . (int) $count . ' ' . $safe_name . 's</strong>.</p>'
            . '<p class="flosc-email-lead flosc-email-lead--spaced">If you are a sincere learner — that\'s absolutely fine! We look forward to welcoming you as a full member soon.'
            . ($safe_upg ? ' <a class="flosc-email-link" href="' . $safe_upg . '">Click here to upgrade</a> and get complete access.' : '')
            . '</p>'
            . '<p class="flosc-email-copy">If you are acting maliciously: we are now tracking IP address, geolocation, device fingerprint, and other identifying data associated with these requests. This data is retained and can be reported to the appropriate authorities.</p>'
            . '<p class="flosc-email-foot flosc-email-foot--brand">' . $safe_team . '</p>'
            . '</div></div></body></html>';

        $headers = $this->get_flosc_mail_headers('', 0, true);
        wp_mail($email, $subject, $body, $headers);
    }

    /**
     * Delete a DO session directory after its data has been pulled to WP.
     * Fire-and-forget: failures are logged but do not block the login flow.
     */

    private function record_guest_link_send($email) {
        $email = sanitize_email($email);
        if (empty($email) || !is_email($email)) {
            return;
        }

        $log = get_option('flosc_guest_link_log', []);
        if (!is_array($log)) {
            $log = [];
        }
        $hash = $this->get_guest_request_key($email);
        $now = time();
        if (isset($log[$hash]) && is_array($log[$hash])) {
            $log[$hash]['count'] = max(0, intval($log[$hash]['count'] ?? 0)) + 1;
            $log[$hash]['last_sent'] = $now;
            $log[$hash]['email'] = $email;
        } else {
            $log[$hash] = [
                'email' => $email,
                'count' => 1,
                'first_sent' => $now,
                'last_sent' => $now,
            ];
        }

        $cutoff = $now - (90 * DAY_IN_SECONDS);
        foreach ($log as $key => $entry) {
            if (!is_array($entry)) {
                unset($log[$key]);
                continue;
            }
            if (intval($entry['last_sent'] ?? 0) < $cutoff) {
                unset($log[$key]);
            }
        }
        update_option('flosc_guest_link_log', $log, false);

        if (intval($log[$hash]['count'] ?? 0) === 6) {
            $this->send_guest_link_warning_email($email, 6);
        }
    }


    /**
     * Send the Guest Access Link email.
     */
    private function normalize_guest_request_email($email) {
        return strtolower(trim((string) $email));
    }

    private function get_guest_request_key($email) {
        return md5($this->normalize_guest_request_email($email));
    }

    private function get_guest_account_request_queue() {
        $queue = get_option('flosc_guest_account_request_queue', []);
        return is_array($queue) ? $queue : [];
    }

    private function save_guest_account_request_queue(array $queue) {
        update_option('flosc_guest_account_request_queue', $queue, false);
    }

    private function get_guest_account_request_denylist() {
        $denylist = get_option('flosc_guest_account_request_denylist', []);
        return is_array($denylist) ? $denylist : [];
    }

    private function save_guest_account_request_denylist(array $denylist) {
        update_option('flosc_guest_account_request_denylist', $denylist, false);
    }

    private function is_guest_request_email_blocked($email) {
        $email = $this->normalize_guest_request_email($email);
        if ($email === '') {
            return false;
        }
        $denylist = $this->get_guest_account_request_denylist();
        return isset($denylist[$this->get_guest_request_key($email)]);
    }

    private function upsert_guest_account_request($email, $flow_id = '', $message = '') {
        $email = sanitize_email($email);
        if (empty($email) || !is_email($email)) {
            return;
        }

        $flow_id = sanitize_key((string) $flow_id);
        $queue = $this->get_guest_account_request_queue();
        $key = $this->get_guest_request_key($email);
        $now = time();
        $excerpt = sanitize_textarea_field((string) $message);

        if (!isset($queue[$key]) || !is_array($queue[$key])) {
            $queue[$key] = [
                'email' => $email,
                'flow_id' => $flow_id,
                'status' => 'pending',
                'requested_at' => $now,
                'last_requested_at' => $now,
                'last_message_excerpt' => $excerpt,
                'decision_at' => 0,
                'decided_by' => 0,
            ];
        } else {
            $queue[$key]['email'] = $email;
            $queue[$key]['flow_id'] = $flow_id !== '' ? $flow_id : sanitize_key((string) ($queue[$key]['flow_id'] ?? ''));
            $queue[$key]['status'] = 'pending';
            $queue[$key]['last_requested_at'] = $now;
            if ($excerpt !== '') {
                $queue[$key]['last_message_excerpt'] = $excerpt;
            }
            if (empty($queue[$key]['requested_at'])) {
                $queue[$key]['requested_at'] = $now;
            }
            $queue[$key]['decision_at'] = 0;
            $queue[$key]['decided_by'] = 0;
        }

        $this->save_guest_account_request_queue($queue);
    }

    private function set_guest_account_request_status($email, $status, $actor_id = 0) {
        $email = sanitize_email($email);
        if (empty($email) || !is_email($email)) {
            return false;
        }

        $status = sanitize_key((string) $status);
        if (!in_array($status, ['pending', 'approved', 'denied'], true)) {
            return false;
        }

        $queue = $this->get_guest_account_request_queue();
        $key = $this->get_guest_request_key($email);
        if (!isset($queue[$key]) || !is_array($queue[$key])) {
            $queue[$key] = [
                'email' => $email,
                'flow_id' => '',
                'status' => $status,
                'requested_at' => time(),
                'last_requested_at' => time(),
                'last_message_excerpt' => '',
                'decision_at' => 0,
                'decided_by' => 0,
            ];
        }

        $queue[$key]['status'] = $status;
        $queue[$key]['decision_at'] = time();
        $queue[$key]['decided_by'] = intval($actor_id);
        $this->save_guest_account_request_queue($queue);

        return true;
    }

    private function delete_guest_account_request($email) {
        $email = sanitize_email($email);
        if (empty($email) || !is_email($email)) {
            return false;
        }

        $queue = $this->get_guest_account_request_queue();
        $key = $this->get_guest_request_key($email);
        if (isset($queue[$key])) {
            unset($queue[$key]);
            $this->save_guest_account_request_queue($queue);
        }
        return true;
    }

    private function deny_and_block_guest_account_request($email, $actor_id = 0) {
        $email = sanitize_email($email);
        if (empty($email) || !is_email($email)) {
            return false;
        }

        $this->set_guest_account_request_status($email, 'denied', $actor_id);
        $denylist = $this->get_guest_account_request_denylist();
        $denylist[$this->get_guest_request_key($email)] = [
            'email' => $email,
            'blocked_at' => time(),
            'blocked_by' => intval($actor_id),
        ];
        $this->save_guest_account_request_denylist($denylist);

        return true;
    }

    private function unblock_guest_account_request_email($email) {
        $email = sanitize_email($email);
        if (empty($email) || !is_email($email)) {
            return false;
        }

        $denylist = $this->get_guest_account_request_denylist();
        $key = $this->get_guest_request_key($email);
        if (isset($denylist[$key])) {
            unset($denylist[$key]);
            $this->save_guest_account_request_denylist($denylist);
        }

        return true;
    }

    private function build_guest_request_admin_redirect($notice_key) {
        $notice_key = sanitize_key((string) $notice_key);
        $uid = get_current_user_id();
        if ( $uid > 0 && $notice_key !== '' ) {
            set_transient( 'flosc_guest_request_notice_' . $uid, $notice_key, MINUTE_IN_SECONDS );
        }
        // Prefer flow already resolved on the framework; fall back to sanitized filter_input.
        $ivr = '';
        if ( isset( $this->current_ivr_file ) && is_string( $this->current_ivr_file ) ) {
            $ivr = sanitize_file_name( $this->current_ivr_file );
        }
        if ( $ivr === '' ) {
            $ivr_in = filter_input( INPUT_POST, 'ivr', FILTER_DEFAULT );
            if ( ! is_string( $ivr_in ) || $ivr_in === '' ) {
                $ivr_in = filter_input( INPUT_GET, 'ivr', FILTER_DEFAULT );
            }
            if ( is_string( $ivr_in ) ) {
                $ivr = sanitize_file_name( wp_unslash( $ivr_in ) );
            }
        }
        return add_query_arg(
            array(
                'page' => 'flosc-settings',
                'tab'  => 'login',
                'ivr'  => $ivr,
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Optional admin MagicLink send gate (flow setting magic_link_admin_send_condition).
     *
     * Ship default: empty / always → allow. When set, target must already exist and
     * FLOSC_Condition_Evaluator must pass. manage_options may force-bypass.
     *
     * @param int    $user_id Existing WP user (0 = none).
     * @param string $flow_id
     * @param bool   $force   Bypass condition (admin only caller).
     * @return true|WP_Error
     */
    private function flosc_magic_admin_send_condition_ok($user_id, $flow_id = '', $force = false) {
        if ($force) {
            return true;
        }
        $cond = trim((string) (
            $flow_id !== ''
                ? flosc_get_setting('magic_link_admin_send_condition', '', $flow_id)
                : flosc_get_setting('magic_link_admin_send_condition', '')
        ));
        if ($cond === '' || $cond === 'always') {
            return true;
        }
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error(
                'flosc_magic_condition_needs_user',
                'Send condition is set: target must be an existing account (SSO or email). Clear the condition or create the user first.'
            );
        }
        if (!class_exists('FLOSC_Condition_Evaluator')) {
            return true;
        }
        $context = FLOSC_Condition_Evaluator::build_context($user_id, [
            'flow_id'           => sanitize_key((string) $flow_id),
            '_skip_last_visit'  => true,
        ]);
        $evaluator = new FLOSC_Condition_Evaluator($context);
        if (!$evaluator->evaluate($cond)) {
            return new WP_Error(
                'flosc_magic_condition_failed',
                'Send condition not met for this user. Adjust the condition on Register & Login, or use Force send.'
            );
        }
        return true;
    }

    /**
     * Admin AJAX: send a Guest Access Link to any email.
     * Used from the Register & Login settings tab by admins.
     * Works for SSO-registered users (existing account) the same as email-registered.
     */
    public function ajax_send_guest_link() {
        $post = wp_unslash($_POST);
        check_ajax_referer('flosc_send_guest_link', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $email = sanitize_email($post['email'] ?? '');
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        // Set flow context so flosc_get_setting reads the correct per-flow settings
        $ivr = sanitize_file_name($post['ivr'] ?? '');
        if (!empty($ivr)) {
            $this->set_flow_context(pathinfo($ivr, PATHINFO_FILENAME));
        }

        $flow_id = !empty($ivr) ? sanitize_key(pathinfo($ivr, PATHINFO_FILENAME)) : '';
        $force   = !empty($post['force']);

        if (!$this->flosc_magic_access_links_enabled($flow_id)) {
            wp_send_json_error(['message' => 'Magic access links are disabled (package and/or this flow).']);
        }

        // Prefer existing account (SSO or email) so conditions can run before mint.
        $existing = get_user_by('email', $email);
        $existing_id = $existing ? (int) $existing->ID : 0;

        $cond_ok = $this->flosc_magic_admin_send_condition_ok($existing_id, $flow_id, $force);
        if (is_wp_error($cond_ok)) {
            wp_send_json_error(['message' => $cond_ok->get_error_message()]);
        }

        // Convenience link only: existing account required (never create user here).
        $user_id = $this->flosc_resolve_existing_user_for_convenience_link($email);
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        $token = $this->flosc_mint_magic_access_for_user((int) $user_id, [
            'status'  => 'pending',
            'flow_id' => $flow_id,
            'ttl'     => 7 * DAY_IN_SECONDS,
        ]);
        if (is_wp_error($token)) {
            wp_send_json_error(['message' => $token->get_error_message()]);
        }

        $sent = $this->send_guest_link_email($email, $token, $flow_id);
        if (!$sent) {
            delete_transient('flosc_magic_' . $token);
            wp_send_json_error(['message' => 'Email could not be sent. Check your mail configuration.']);
        }

        $this->record_guest_link_send($email);

        $link_name = flosc_get_setting('guest_link_name', 'Guest Access Link');
        wp_send_json_success([
            'message' => sprintf('%s sent to %s', esc_html($link_name), esc_html($email)),
        ]);
    }

    public function handle_guest_request_approve() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'flosc'), '', ['response' => 403]);
        }

        check_admin_referer('flosc_guest_request_action', 'flosc_guest_request_nonce');

        $post = wp_unslash($_POST);
        $email = sanitize_email((string) ($post['email'] ?? ''));
        $actor = get_current_user_id();
        if (empty($email) || !is_email($email)) {
            wp_safe_redirect($this->build_guest_request_admin_redirect('invalid_email'));
            exit;
        }

        $this->unblock_guest_account_request_email($email);
        $this->set_guest_account_request_status($email, 'approved', $actor);

        wp_safe_redirect($this->build_guest_request_admin_redirect('approved'));
        exit;
    }

    public function handle_guest_request_approve_send() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'flosc'), '', ['response' => 403]);
        }

        check_admin_referer('flosc_guest_request_action', 'flosc_guest_request_nonce');

        $post = wp_unslash($_POST);
        $email = sanitize_email((string) ($post['email'] ?? ''));
        $flow_id = sanitize_key((string) ($post['flow_id'] ?? ''));
        $actor = get_current_user_id();

        if (!$this->flosc_magic_access_links_enabled($flow_id)) {
            wp_safe_redirect($this->build_guest_request_admin_redirect('approve_send_failed'));
            exit;
        }

        if (empty($email) || !is_email($email)) {
            wp_safe_redirect($this->build_guest_request_admin_redirect('invalid_email'));
            exit;
        }

        $this->unblock_guest_account_request_email($email);
        $this->set_guest_account_request_status($email, 'approved', $actor);

        // Convenience link only: existing account required (never create user here).
        $user_id = $this->flosc_resolve_existing_user_for_convenience_link($email);
        if (is_wp_error($user_id)) {
            wp_safe_redirect($this->build_guest_request_admin_redirect('approve_send_failed'));
            exit;
        }

        $token = $this->flosc_mint_magic_access_for_user((int) $user_id, [
            'status'  => 'pending',
            'flow_id' => $flow_id,
            'ttl'     => 7 * DAY_IN_SECONDS,
        ]);
        if (is_wp_error($token)) {
            wp_safe_redirect($this->build_guest_request_admin_redirect('approve_send_failed'));
            exit;
        }

        $sent = $this->send_guest_link_email($email, $token, $flow_id);
        if (!$sent) {
            delete_transient('flosc_magic_' . $token);
            wp_safe_redirect($this->build_guest_request_admin_redirect('approve_send_failed'));
            exit;
        }

        $this->record_guest_link_send($email);

        wp_safe_redirect($this->build_guest_request_admin_redirect('approve_sent'));
        exit;
    }

    public function handle_guest_request_deny_block() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'flosc'), '', ['response' => 403]);
        }

        check_admin_referer('flosc_guest_request_action', 'flosc_guest_request_nonce');

        $post = wp_unslash($_POST);
        $email = sanitize_email((string) ($post['email'] ?? ''));
        $actor = get_current_user_id();
        if (empty($email) || !is_email($email)) {
            wp_safe_redirect($this->build_guest_request_admin_redirect('invalid_email'));
            exit;
        }

        $this->deny_and_block_guest_account_request($email, $actor);

        wp_safe_redirect($this->build_guest_request_admin_redirect('denied_blocked'));
        exit;
    }

    public function handle_guest_request_delete() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'flosc'), '', ['response' => 403]);
        }

        check_admin_referer('flosc_guest_request_action', 'flosc_guest_request_nonce');

        $post = wp_unslash($_POST);
        $email = sanitize_email((string) ($post['email'] ?? ''));
        if (empty($email) || !is_email($email)) {
            wp_safe_redirect($this->build_guest_request_admin_redirect('invalid_email'));
            exit;
        }

        $this->delete_guest_account_request($email);

        wp_safe_redirect($this->build_guest_request_admin_redirect('deleted'));
        exit;
    }

    /**
     * v1.9.0: AJAX handler for chat logs polling
     * Returns recent chat log entries for the admin Chat Logs tab.
     * Supports since_id for incremental polling (new entries only).
     */


    /**
     * Admin UI: show pending email status + activate control on user profile.
     */
    public function render_email_account_status_profile($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        $status = (string) get_user_meta($user->ID, '_flosc_email_account_status', true);
        if ($status === '') {
            $status = 'active';
        }
        $verified_at = (string) get_user_meta($user->ID, '_flosc_email_verified_at', true);
        ?>
        <h2><?php esc_html_e('FLOSC email account', 'flosc'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label><?php esc_html_e('Status', 'flosc'); ?></label></th>
                <td>
                    <code><?php echo esc_html($status); ?></code>
                    <?php if ($verified_at !== '') : ?>
                        <p class="description"><?php
                        /* translators: %s: local date/time when email was verified */
                        echo esc_html(sprintf(__('Verified at: %s', 'flosc'), $verified_at));
                        ?></p>
                    <?php endif; ?>
                    <?php if ($status === 'pending' && current_user_can('promote_users')) : ?>
                        <p>
                            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(
                                admin_url('admin-post.php?action=flosc_activate_email_account&user_id=' . (int) $user->ID),
                                'flosc_activate_email_' . (int) $user->ID
                            )); ?>"><?php esc_html_e('Activate email account', 'flosc'); ?></a>
                        </p>
                        <p class="description"><?php esc_html_e('Marks the account active and sends the welcome email (with MagicLink when enabled).', 'flosc'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * admin-post: floscAdmin activates a pending email-registered user.
     */
    public function handle_admin_activate_email_account() {
        if (!current_user_can('promote_users')) {
            wp_die(esc_html__('Unauthorized', 'flosc'), '', array('response' => 403));
        }
        $user_id = isset($_GET['user_id']) ? absint(wp_unslash($_GET['user_id'])) : 0;
        check_admin_referer('flosc_activate_email_' . $user_id);
        if ($user_id <= 0 || !get_userdata($user_id)) {
            wp_safe_redirect(admin_url('users.php'));
            exit;
        }
        $result = $this->flosc_activate_email_account($user_id);
        $redir = get_edit_user_link($user_id);
        if (!$redir) {
            $redir = admin_url('user-edit.php?user_id=' . $user_id);
        }
        $arg = is_wp_error($result) ? 'flosc_activate_failed' : 'flosc_activate_ok';
        wp_safe_redirect(add_query_arg('flosc_notice', $arg, $redir));
        exit;
    }

}
