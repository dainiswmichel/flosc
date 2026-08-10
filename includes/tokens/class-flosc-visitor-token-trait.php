<?php
/**
 * Visitor token runtime helpers.
 *
 * Keeps token grant and depletion copy logic out of the main framework file.
 */

if (!defined('ABSPATH')) {
    exit;
}

trait FLOSC_Visitor_Token_Trait {
    /**
     * Flow-scoped visitor wallet initial amount.
     *
     * This is the runtime baseline for anonymous visitor sessions and should
     * match the Token Management "Visitor Wallet Initial Amount" setting.
     */
    private function flosc_get_visitor_wallet_initial_amount($flow_id = '', $token_provider = null) {
        $flow_stem = $this->flosc_normalize_flow_stem((string) $flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (is_array($settings) && isset($settings['tokens_communication_tokens_per_message'])) {
            $configured = intval($settings['tokens_communication_tokens_per_message']);
            if ($configured > 0) {
                return $configured;
            }
        }

        $global_default = intval(get_option('flosc_tokens_communication_tokens_per_message', 5000));
        if ($global_default <= 0) {
            $global_default = 5000;
        }

        if ($token_provider && method_exists($token_provider, 'get_communication_economics')) {
            $economics = (array) $token_provider->get_communication_economics();
            $provider_default = intval($economics['tokens_per_message'] ?? 0);
            if ($provider_default > 0) {
                return $provider_default;
            }
        }

        return $global_default;
    }

    /**
     * Minimum tokens required to START an AI chat turn (pre-check gate).
     *
     * Priority:
     * 1) Flow override: cost_ai_query (flat per-turn reserve)
     * 2) Global override option: flosc_cost_ai_query
     * 3) 1 token — allow any positive balance; actual debit uses
     *    flosc_resolve_chat_charge_tokens() (real API millicents → tokens, or 1).
     *
     * NOTE: Do NOT fall back to the full visitor wallet size. That made a balance
     * of e.g. 3876 fail the gate when the wallet baseline was 5000, while the UI
     * still showed thousands remaining ("Token limit reached" false positive).
     */
    private function flosc_get_ai_query_token_cost($flow_id = '', $token_provider = null) {
        $flow_stem = $this->flosc_normalize_flow_stem((string) $flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (is_array($settings) && isset($settings['cost_ai_query'])) {
            $flow_override = intval($settings['cost_ai_query']);
            if ($flow_override > 0) {
                return $flow_override;
            }
        }

        $global_override = intval(get_option('flosc_cost_ai_query', 0));
        if ($global_override > 0) {
            return $global_override;
        }

        return 1;
    }

    /**
     * Resolve the actual token debit for a chat turn.
     *
     * When provider billing data is available, convert the reported real
     * millicent cost into tokens using the configured real factor. Otherwise,
     * fall back to the configured/default AI query token cost.
     */
    public function flosc_resolve_chat_charge_tokens($flow_id, $token_provider, $billing_meta = []) {
        // Primary: debit the REAL provider cost, converted to floscTokens via the
        // configured ratio (Token Management -> Real Millicents per Token).
        $real_millicents = max(0, intval($billing_meta['real_millicents'] ?? 0));
        if ($real_millicents > 0 && $token_provider && method_exists($token_provider, 'convert_real_millicents_to_tokens')) {
            $converted = intval($token_provider->convert_real_millicents_to_tokens($real_millicents));
            if ($converted > 0) {
                return $converted;
            }
        }

        // No billing metadata (the AI API reported no usage/cost). If an admin set an
        // explicit flat per-turn cost, use it; otherwise debit 1 as a deliberate
        // "billing unavailable" signal.
        $flow_stem = $this->flosc_normalize_flow_stem((string) $flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        $flat = (is_array($settings) && isset($settings['cost_ai_query'])) ? intval($settings['cost_ai_query']) : 0;
        if ($flat <= 0) {
            $flat = intval(get_option('flosc_cost_ai_query', 0));
        }
        return $flat > 0 ? $flat : 1;
    }

    /**
     * Flow-scoped member wallet initial amount.
     *
     * Falls back to guest grant for backward compatibility until a dedicated
     * member amount is configured for the flow.
     */
    private function flosc_get_member_token_grant_amount($flow_id = '', $user_id = 0) {
        $flow_id = sanitize_key((string) $flow_id);
        $user_id = absint($user_id);

        $context = $this->get_guest_email_context($flow_id, $user_id);
        $settings = (array) ($context['settings'] ?? []);

        if (isset($settings['member_token_grant'])) {
            return max(0, intval($settings['member_token_grant']));
        }

        return $this->flosc_get_guest_token_grant_amount($flow_id, $user_id);
    }

    /**
     * Initial logged-in wallet amount for this flow.
     */
    private function flosc_get_user_flow_initial_amount($user_id, $flow_id = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        // Per-flow: member wallet only when entitled on this flow (not global flag).
        $is_flow_member = false;
        if ($this->sale_manager && method_exists($this->sale_manager, 'access')) {
            $is_flow_member = $this->sale_manager->access()->is_member($user_id, (string) $flow_id);
        } elseif (class_exists('FLOSC_Member_Access')) {
            $is_flow_member = FLOSC_Member_Access::instance()->is_member($user_id, (string) $flow_id);
        }
        if ($is_flow_member) {
            return max(0, intval($this->flosc_get_member_token_grant_amount((string) $flow_id, $user_id)));
        }

        return max(0, intval($this->flosc_get_guest_token_grant_amount((string) $flow_id, $user_id)));
    }

    /**
     * Whether chat token charging is enforced for the given flow.
     * Default is enabled unless the flow explicitly disables it.
     */
    private function flosc_is_flow_chat_token_enforced($flow_id = '') {
        $flow_stem = $this->flosc_normalize_flow_stem($flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (!is_array($settings)) {
            return true;
        }

        if (!array_key_exists('chat_token_enforcement', $settings)) {
            return true;
        }

        return !empty($settings['chat_token_enforcement']);
    }

    /**
     * Initial visitor token balance for a flow.
     * Uses visitor wallet initial amount configured in Token Management.
     */
    public function flosc_get_initial_visitor_token_balance($flow_id = '', $token_provider = null) {
        return max(0, intval($this->flosc_get_visitor_wallet_initial_amount((string) $flow_id, $token_provider)));
    }

    /**
     * Normalize flow id to a stable stem used in meta/transient keys.
     */
    public function flosc_normalize_flow_stem($flow_id = '') {
        $flow_id = (string) $flow_id;
        $stem = sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
        if ($stem === '') {
            $stem = sanitize_key($flow_id);
        }
        return $stem !== '' ? $stem : 'default';
    }

    /**
     * Per-flow user token balance meta key.
     */
    private function flosc_user_flow_token_meta_key($flow_id = '') {
        return '_flosc_flow_tokens_' . $this->flosc_normalize_flow_stem($flow_id);
    }

    /**
     * Read logged-in user's per-flow token balance (0 if not yet granted).
     * Does not invent a floor baseline — grants are additive (remaining + grant).
     */
    public function flosc_get_user_flow_token_balance($user_id, $flow_id = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $meta_key = $this->flosc_user_flow_token_meta_key($flow_id);
        $stored = get_user_meta($user_id, $meta_key, true);
        if (is_numeric($stored)) {
            return max(0, intval($stored));
        }

        return 0;
    }

    /**
     * Persist logged-in user's per-flow token balance.
     */
    private function flosc_set_user_flow_token_balance($user_id, $flow_id = '', $balance = 0) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $balance = max(0, intval($balance));
        update_user_meta($user_id, $this->flosc_user_flow_token_meta_key($flow_id), $balance);
        return $balance;
    }

    /**
     * Meta flag: guest additive grant already applied for this flow.
     */
    public function flosc_guest_token_grant_flag_key($flow_id = '') {
        return '_flosc_guest_token_grant_applied_' . $this->flosc_normalize_flow_stem($flow_id);
    }

    /**
     * Meta flag: member additive grant already applied for this flow.
     */
    private function flosc_member_token_grant_flag_key($flow_id = '') {
        return '_flosc_member_token_grant_applied_' . $this->flosc_normalize_flow_stem($flow_id);
    }

    /**
     * Read visitor remaining tokens for a browser session (same flow).
     * $session_id_raw is the client session id (localStorage flosc_visitor_session).
     *
     * Tries the normalized flow stem first, then the raw sanitized id, so V→G
     * carry still works if chat charged under a slightly different flow_id form.
     */
    public function flosc_get_visitor_remaining_for_session($flow_id, $session_id_raw) {
        $session_id_raw = trim((string) $session_id_raw);
        if ($session_id_raw === '') {
            return 0;
        }

        // Prefer the same normalize path used when charging visitor sessions.
        if (method_exists($this, 'flosc_normalize_session_id')) {
            $session_id = $this->flosc_normalize_session_id($session_id_raw);
        } else {
            $session_id = absint($session_id_raw);
        }
        if ($session_id <= 0) {
            return 0;
        }

        $candidates = [];
        $stem = $this->flosc_normalize_flow_stem($flow_id);
        if ($stem !== '') {
            $candidates[] = $stem;
        }
        $raw = sanitize_key((string) $flow_id);
        if ($raw !== '' && $raw !== $stem) {
            $candidates[] = $raw;
        }
        if (empty($candidates)) {
            $candidates[] = 'default';
        }

        foreach ($candidates as $candidate_flow) {
            $transient_key = $this->flosc_visitor_token_transient_key($candidate_flow, $session_id);
            $stored = get_transient($transient_key);
            if (is_numeric($stored)) {
                return max(0, intval($stored));
            }
        }

        return 0;
    }

    /**
     * Resolve visitor session id for token carry (cookie set by JS before auth).
     */
    public function flosc_resolve_visitor_session_id_for_grant() {
        foreach ( array( 'flosc_visitor_session', 'flosc_vtok_session' ) as $cookie_name ) {
            $raw = filter_input( INPUT_COOKIE, $cookie_name, FILTER_UNSAFE_RAW );
            if ( is_string( $raw ) && $raw !== '' ) {
                return sanitize_text_field( rawurldecode( wp_unslash( $raw ) ) );
            }
        }
        return '';
    }

    /**
     * V→G once per flow: guest_balance = visitor_remaining + guest_token_grant.
     * Idempotent via per-flow user meta flag (safe on every login / init).
     *
     * If $session_id_raw is empty and no cookie, defers (does not set flag) so the
     * guest app can call again with visitor_session_id after SSO return.
     * Pass $allow_without_session true only when a client explicitly confirms.
     */
    public function flosc_apply_guest_token_grant_once($user_id, $flow_id = '', $session_id_raw = '', $allow_without_session = false) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $flow_stem = $this->flosc_normalize_flow_stem($flow_id);
        $flag_key = $this->flosc_guest_token_grant_flag_key($flow_stem);
        if (get_user_meta($user_id, $flag_key, true)) {
            return $this->flosc_get_user_flow_token_balance($user_id, $flow_stem);
        }

        if ($session_id_raw === '') {
            $session_id_raw = $this->flosc_resolve_visitor_session_id_for_grant();
        }

        $session_id_raw = trim((string) $session_id_raw);
        if ($session_id_raw === '' && !$allow_without_session) {
            // Defer until client provides visitor_session_id (common after cross-domain SSO).
            return $this->flosc_get_user_flow_token_balance($user_id, $flow_stem);
        }

        // Remaining = visitor session wallet for this flow (0 if none / not found).
        $remaining = $session_id_raw !== ''
            ? $this->flosc_get_visitor_remaining_for_session($flow_stem, $session_id_raw)
            : 0;
        $grant = max(0, intval($this->flosc_get_guest_token_grant_amount($flow_stem, $user_id)));
        $new_balance = $remaining + $grant;

        // Safety: never lock a guest at 0 when Token Management configured a positive
        // guest grant (mis-resolved flow / missing settings would otherwise brick the wallet).
        if ($new_balance <= 0 && $grant <= 0) {
            $fallback = max(0, intval($this->flosc_get_visitor_wallet_initial_amount($flow_stem, null)));
            if ($fallback > 0) {
                $grant = $fallback;
                $new_balance = $remaining + $grant;
            }
        }

        $this->flosc_set_user_flow_token_balance($user_id, $flow_stem, $new_balance);
        update_user_meta($user_id, $flag_key, 1);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log(sprintf(
                'FLOSC tokens V→G additive: user=%d flow=%s remaining=%d grant=%d total=%d session=%s',
                $user_id,
                $flow_stem,
                $remaining,
                $grant,
                $new_balance,
                $session_id_raw !== '' ? 'yes' : 'no'
            ));
        }

        return $new_balance;
    }

    /**
     * Resolve product-token parameters for this flow (Token Management tab).
     *
     * Flow defaults (any product can use more/less via offer.tokens or context):
     * - product_token_grant_onetime     — one-time purchase add
     * - product_token_grant_recurring   — each recurring cycle (e.g. monthly)
     * - product_token_grant_recurring_yearly — yearly cycle add
     * - product_token_cap               — wallet ceiling for product credits (0 = no cap)
     *
     * Legacy keys subscription_* still read for backward compatibility.
     *
     * @return array{onetime:int,recurring:int,recurring_yearly:int,cap:int}
     */
    private function flosc_get_product_token_params($flow_id = '') {
        $flow_stem = $this->flosc_normalize_flow_stem($flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $cap_default = 35000;
        $recurring_default = 10000;

        $cap = array_key_exists('product_token_cap', $settings)
            ? max(0, intval($settings['product_token_cap']))
            : (array_key_exists('subscription_token_cap', $settings)
                ? max(0, intval($settings['subscription_token_cap']))
                : $cap_default);

        $recurring = array_key_exists('product_token_grant_recurring', $settings)
            ? max(0, intval($settings['product_token_grant_recurring']))
            : (array_key_exists('subscription_monthly_token_grant', $settings)
                ? max(0, intval($settings['subscription_monthly_token_grant']))
                : $recurring_default);

        $recurring_yearly = array_key_exists('product_token_grant_recurring_yearly', $settings)
            ? max(0, intval($settings['product_token_grant_recurring_yearly']))
            : (array_key_exists('subscription_yearly_token_grant', $settings)
                ? max(0, intval($settings['subscription_yearly_token_grant']))
                : $cap);

        $onetime = array_key_exists('product_token_grant_onetime', $settings)
            ? max(0, intval($settings['product_token_grant_onetime']))
            : $recurring; // sensible default: same as one recurring pack

        return [
            'onetime' => $onetime,
            'recurring' => $recurring,
            'recurring_yearly' => $recurring_yearly,
            'cap' => $cap,
        ];
    }

    /**
     * Credit product tokens to the flow-scoped wallet (with optional cap).
     *
     * Modes (Token Management parameters):
     * - onetime           — single purchase (PayPal capture, Stripe, etc.)
     * - recurring         — each paid recurring cycle (monthly plan default)
     * - recurring_yearly  — each paid yearly cycle
     *
     * Payment success is independent of credit amount: if already at cap, credit 0.
     * Context overrides: grant (int), cap (int|null), offer tokens.amount / tokens.cap.
     *
     * @param int    $user_id
     * @param string $flow_id
     * @param string $mode    onetime|recurring|recurring_yearly|monthly|yearly
     * @param array  $context idempotency_key, reason, grant, cap, offer, subscription_id
     * @return array{credited:int,balance:int,cap:int,grant:int,capped:bool,skipped:bool,mode:string}
     */
    public function flosc_apply_product_token_credit($user_id, $flow_id = '', $mode = 'onetime', $context = []) {
        $user_id = absint($user_id);
        $result = [
            'credited' => 0,
            'balance' => 0,
            'cap' => 0,
            'grant' => 0,
            'capped' => false,
            'skipped' => true,
            'mode' => 'onetime',
        ];
        if ($user_id <= 0) {
            return $result;
        }

        $flow_stem = $this->flosc_normalize_flow_stem($flow_id);
        $mode = strtolower(sanitize_key((string) $mode));
        // Aliases used by subscription plan types.
        if ($mode === 'monthly') {
            $mode = 'recurring';
        } elseif ($mode === 'yearly') {
            $mode = 'recurring_yearly';
        }
        if (!in_array($mode, ['onetime', 'recurring', 'recurring_yearly'], true)) {
            $mode = 'onetime';
        }
        $result['mode'] = $mode;

        $idem = sanitize_key((string) ($context['idempotency_key'] ?? ''));
        if ($idem !== '') {
            $done = get_user_meta($user_id, '_flosc_product_token_credits', true);
            if (!is_array($done)) {
                // Legacy store from first subscription-only implementation.
                $legacy = get_user_meta($user_id, '_flosc_sub_token_topups', true);
                $done = is_array($legacy) ? $legacy : [];
            }
            if (!empty($done[$idem]) && is_array($done[$idem])) {
                return array_merge($result, $done[$idem], ['skipped' => true, 'mode' => $mode]);
            }
        }

        $params = $this->flosc_get_product_token_params($flow_stem);

        // Offer-level product token config (Token Management accordion).
        $offer = is_array($context['offer'] ?? null) ? $context['offer'] : [];
        $offer_tokens = is_array($offer['tokens'] ?? null) ? $offer['tokens'] : [];
        $source = sanitize_key((string) ($offer_tokens['source'] ?? ''));
        if ($source === 'none') {
            $result['skipped'] = true;
            $result['grant'] = 0;
            $result['balance'] = $this->flosc_get_user_flow_token_balance($user_id, $flow_stem);
            return $result;
        }

        // Prefer offer.tokens.mode when source is custom (or mode explicitly set).
        $offer_mode = sanitize_key((string) ($offer_tokens['mode'] ?? ''));
        if (in_array($offer_mode, ['onetime', 'recurring', 'recurring_yearly'], true)
            && ($source === 'custom' || $source === '')
        ) {
            // Only force offer mode when custom; for flow source, keep caller mode
            // (subscription activate already passes recurring / yearly).
            if ($source === 'custom') {
                $mode = $offer_mode;
                $result['mode'] = $mode;
            }
        }

        if ($mode === 'recurring_yearly') {
            $grant = $params['recurring_yearly'];
        } elseif ($mode === 'recurring') {
            $grant = $params['recurring'];
        } else {
            $grant = $params['onetime'];
        }
        $cap = $params['cap'];

        $cap_mode = sanitize_key((string) ($offer_tokens['cap_mode'] ?? 'flow'));
        // Only apply amount/cap overrides for explicit custom products.
        // source=flow (or unset) always uses flow defaults for the caller's mode.
        if ($source === 'custom') {
            if (isset($offer_tokens['amount']) && $offer_tokens['amount'] !== '') {
                $grant = max(0, intval($offer_tokens['amount'])) + max(0, intval($offer_tokens['bonus'] ?? 0));
            }
            if ($cap_mode === 'none') {
                $cap = 0;
            } elseif ($cap_mode === 'custom' && array_key_exists('cap', $offer_tokens) && $offer_tokens['cap'] !== '') {
                $cap = max(0, intval($offer_tokens['cap']));
            }
        }
        // Explicit context overrides win last.
        if (isset($context['grant']) && $context['grant'] !== '') {
            $grant = max(0, intval($context['grant']));
        }
        if (array_key_exists('cap', $context) && $context['cap'] !== '' && $context['cap'] !== null) {
            $cap = max(0, intval($context['cap']));
        }

        $current = $this->flosc_get_user_flow_token_balance($user_id, $flow_stem);
        $result['balance'] = $current;
        $result['cap'] = $cap;
        $result['grant'] = $grant;

        if ($grant <= 0) {
            $result['skipped'] = true;
            return $result;
        }

        // Cap reached: paid product still succeeds — credit zero until spent below cap.
        if ($cap > 0 && $current >= $cap) {
            $result['capped'] = true;
            $result['skipped'] = false;
            $result['credited'] = 0;
            $result['balance'] = $current;
        } else {
            $room = ($cap > 0) ? max(0, $cap - $current) : $grant;
            $credit = min($grant, $room);
            $new_balance = $current + $credit;
            $this->flosc_set_user_flow_token_balance($user_id, $flow_stem, $new_balance);

            $token_provider = null;
            if (function_exists('flosc_sale')) {
                $token_provider = flosc_sale()->get_provider('tokens');
            }
            if ($token_provider && method_exists($token_provider, 'credit') && $credit > 0) {
                $token_provider->credit(
                    $user_id,
                    $credit,
                    (string) ($context['reason'] ?? ('Product token credit (' . $mode . ')')),
                    [
                        'flow_id' => $flow_stem,
                        'mode' => $mode,
                        'cap' => $cap,
                        'subscription_id' => $context['subscription_id'] ?? '',
                        'offer_id' => $offer['id'] ?? ($context['offer_id'] ?? ''),
                    ]
                );
            }

            $result['credited'] = $credit;
            $result['balance'] = $new_balance;
            $result['capped'] = ($cap > 0 && $new_balance >= $cap);
            $result['skipped'] = false;
        }

        if ($idem !== '') {
            $done = get_user_meta($user_id, '_flosc_product_token_credits', true);
            if (!is_array($done)) {
                $done = [];
            }
            if (count($done) > 50) {
                $done = array_slice($done, -40, null, true);
            }
            $done[$idem] = [
                'credited' => $result['credited'],
                'balance' => $result['balance'],
                'cap' => $result['cap'],
                'grant' => $result['grant'],
                'capped' => $result['capped'],
                'mode' => $mode,
                'at' => current_time('mysql'),
            ];
            update_user_meta($user_id, '_flosc_product_token_credits', $done);
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log(sprintf(
                'FLOSC product token credit: user=%d flow=%s mode=%s grant=%d credited=%d balance=%d cap=%d capped=%s',
                $user_id,
                $flow_stem,
                $mode,
                $grant,
                $result['credited'],
                $result['balance'],
                $cap,
                $result['capped'] ? 'yes' : 'no'
            ));
        }

        return $result;
    }

    /**
     * @deprecated Prefer flosc_apply_product_token_credit — kept as alias for subscription call sites.
     */
    private function flosc_apply_subscription_token_topup($user_id, $flow_id = '', $plan_type = 'monthly', $context = []) {
        return $this->flosc_apply_product_token_credit($user_id, $flow_id, $plan_type, $context);
    }

    /**
     * G→M once per flow: member_balance = guest_remaining + member_token_grant.
     * Idempotent via per-flow user meta flag.
     */
    public function flosc_apply_member_token_grant_once($user_id, $flow_id = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $flow_stem = $this->flosc_normalize_flow_stem($flow_id);
        $flag_key = $this->flosc_member_token_grant_flag_key($flow_stem);
        if (get_user_meta($user_id, $flag_key, true)) {
            return $this->flosc_get_user_flow_token_balance($user_id, $flow_stem);
        }

        $remaining = $this->flosc_get_user_flow_token_balance($user_id, $flow_stem);
        $grant = max(0, intval($this->flosc_get_member_token_grant_amount($flow_stem, $user_id)));
        $new_balance = $remaining + $grant;

        $this->flosc_set_user_flow_token_balance($user_id, $flow_stem, $new_balance);
        update_user_meta($user_id, $flag_key, 1);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log(sprintf(
                'FLOSC tokens G→M additive: user=%d flow=%s remaining=%d grant=%d total=%d',
                $user_id,
                $flow_stem,
                $remaining,
                $grant,
                $new_balance
            ));
        }

        return $new_balance;
    }

    /**
     * @deprecated Use flosc_apply_guest_token_grant_once — kept as alias for call sites.
     * Ensure logged-in guest has received the per-flow additive guest grant (once).
     */
    private function flosc_ensure_user_flow_token_baseline($user_id, $flow_id = '') {
        return $this->flosc_apply_guest_token_grant_once($user_id, $flow_id);
    }

    /**
     * Apply one spend event against logged-in user's per-flow balance.
     */
    private function flosc_charge_user_flow_tokens($user_id, $flow_id, $token_provider, $billing_meta = []) {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !$token_provider) {
            return [
                'charged' => false,
                'charge_tokens' => 0,
                'balance_before' => 0,
                'balance_after' => 0,
            ];
        }

        $charge_tokens = max(0, intval($this->flosc_resolve_chat_charge_tokens($flow_id, $token_provider, $billing_meta)));

        $balance_before = $this->flosc_get_user_flow_token_balance($user_id, $flow_id);
        if ($charge_tokens <= 0 || $balance_before < $charge_tokens) {
            return [
                'charged' => false,
                'charge_tokens' => $charge_tokens,
                'balance_before' => $balance_before,
                'balance_after' => $balance_before,
            ];
        }

        $balance_after = $this->flosc_set_user_flow_token_balance($user_id, $flow_id, $balance_before - $charge_tokens);

        return [
            'charged' => true,
            'charge_tokens' => $charge_tokens,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
        ];
    }

    /**
     * Flow-scoped guest token grant parameter.
     */
    public function flosc_get_guest_token_grant_amount($flow_id = '', $user_id = 0) {
        $flow_id = sanitize_key((string) $flow_id);
        $user_id = absint($user_id);

        $context = $this->get_guest_email_context($flow_id, $user_id);
        $settings = (array) ($context['settings'] ?? []);

        $fallback_grant = isset($settings['tokens_communication_tokens_per_message'])
            ? intval($settings['tokens_communication_tokens_per_message'])
            : intval(flosc_get_setting('tokens_communication_tokens_per_message', 5000));
        if ($fallback_grant <= 0) {
            $fallback_grant = 5000;
        }

        $amount = isset($settings['guest_token_grant'])
            ? intval($settings['guest_token_grant'])
            : $fallback_grant;

        return max(0, intval(apply_filters('flosc_guest_token_grant_amount', $amount, $flow_id, $user_id, $settings)));
    }

    /**
     * Flow-scoped low-token threshold parameter (0 = disabled).
     */
    public function flosc_get_low_token_threshold($flow_id = '') {
        $flow_stem = $this->flosc_normalize_flow_stem((string) $flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (!is_array($settings)) {
            return 0;
        }
        return max(0, intval($settings['visitor_low_token_threshold'] ?? 0));
    }

    /**
     * Visitor low-token warning copy with flow-specific grant amount.
     */
    public function flosc_get_visitor_low_tokens_message($flow_id = '') {
        $grant = $this->flosc_get_guest_token_grant_amount((string) $flow_id, 0);
        if ($grant <= 0) {
            $grant = 5000;
        }

        $context = $this->get_guest_email_context((string) $flow_id, 0);
        $settings = (array) ($context['settings'] ?? []);
        $template = trim((string) ($settings['visitor_low_tokens_message'] ?? ''));

        if ($template === '') {
            $template = __('You\'re running low on chat tokens. Pretty soon, you\'ll be invited to register or log in to receive {token_grant} more tokens.', 'flosc');
        }

        return str_replace('{token_grant}', number_format_i18n($grant), $template);
    }

    /**
     * Visitor token-depleted copy with flow-specific grant amount.
     */
    private function flosc_get_visitor_token_depleted_message($flow_id = '') {
        $grant = $this->flosc_get_guest_token_grant_amount((string) $flow_id, 0);
        if ($grant <= 0) {
            $grant = 5000;
        }

        $context = $this->get_guest_email_context((string) $flow_id, 0);
        $settings = (array) ($context['settings'] ?? []);
        $template = trim((string) ($settings['visitor_tokens_depleted_message'] ?? ''));

        if ($template === '') {
            $template = __('This session has run out of chat tokens. You can log in to receive {token_grant} tokens to use this chat. You can also share your phone number or email address, plus your preferred contact method and time, and an administrator can follow up with you.', 'flosc');
        }

        return str_replace('{token_grant}', number_format_i18n($grant), $template);
    }

    /**
     * Optional URL to redirect after visitor depleted-session contact capture.
     */
    private function flosc_get_visitor_session_end_redirect_url($flow_id = '') {
        $flow_stem = $this->flosc_normalize_flow_stem((string) $flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (!is_array($settings)) {
            return '';
        }

        $url = trim((string) ($settings['visitor_session_end_redirect_url'] ?? ''));
        if ($url === '') {
            return '';
        }

        return esc_url_raw($url, ['http', 'https']);
    }

    /**
     * Contact capture mode once visitor tokens are depleted.
     * Public: flosc-app.php / full-page shell need this for FLOSC_CONFIG.
     */
    public function flosc_get_visitor_depleted_contact_mode($flow_id = '') {
        $flow_stem = $this->flosc_normalize_flow_stem((string) $flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (!is_array($settings)) {
            return 'message';
        }

        $mode = sanitize_key((string) ($settings['visitor_depleted_contact_mode'] ?? 'message'));
        return in_array($mode, ['message', 'in_chat_form'], true) ? $mode : 'message';
    }
}
