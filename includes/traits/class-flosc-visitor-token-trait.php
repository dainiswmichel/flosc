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
     * Resolve per-chat AI token charge for this flow.
     *
     * Priority:
     * 1) Flow override: cost_ai_query
     * 2) Global override option: flosc_cost_ai_query
     * 3) Visitor wallet baseline: tokens_communication_tokens_per_message
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

        return max(1, intval($this->flosc_get_visitor_wallet_initial_amount((string) $flow_id, $token_provider)));
    }

    /**
     * Resolve the actual token debit for a chat turn.
     *
     * When provider billing data is available, convert the reported real
     * millicent cost into tokens using the configured real factor. Otherwise,
     * fall back to the configured/default AI query token cost.
     */
    private function flosc_resolve_chat_charge_tokens($flow_id, $token_provider, $billing_meta = []) {
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
        // "billing unavailable" signal -- a wallet ticking down by 1/turn tells the
        // floscAdmin the provider isn't reporting cost (a broken/misconfigured API).
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

        $has_member_access = ('true' === get_user_meta($user_id, '_flosc_member_access', true));
        if ($has_member_access) {
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
    private function flosc_get_initial_visitor_token_balance($flow_id = '', $token_provider = null) {
        return max(0, intval($this->flosc_get_visitor_wallet_initial_amount((string) $flow_id, $token_provider)));
    }

    /**
     * Normalize flow id to a stable stem used in meta/transient keys.
     */
    private function flosc_normalize_flow_stem($flow_id = '') {
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
     * Read or initialize logged-in user's per-flow token balance.
     */
    private function flosc_get_user_flow_token_balance($user_id, $flow_id = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $meta_key = $this->flosc_user_flow_token_meta_key($flow_id);
        $stored = get_user_meta($user_id, $meta_key, true);
        if (is_numeric($stored)) {
            return max(0, intval($stored));
        }

        $baseline = max(0, intval($this->flosc_get_user_flow_initial_amount($user_id, (string) $flow_id)));
        update_user_meta($user_id, $meta_key, $baseline);
        return $baseline;
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
     * Ensure logged-in user has at least the configured per-flow baseline.
     */
    private function flosc_ensure_user_flow_token_baseline($user_id, $flow_id = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $baseline = max(0, intval($this->flosc_get_user_flow_initial_amount($user_id, (string) $flow_id)));
        $current = $this->flosc_get_user_flow_token_balance($user_id, $flow_id);
        if ($current >= $baseline) {
            return $current;
        }

        return $this->flosc_set_user_flow_token_balance($user_id, $flow_id, $baseline);
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
    private function flosc_get_guest_token_grant_amount($flow_id = '', $user_id = 0) {
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
    private function flosc_get_low_token_threshold($flow_id = '') {
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
    private function flosc_get_visitor_low_tokens_message($flow_id = '') {
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
            $template = __('This session has run out of chat tokens. You can log in, and Dainis will give you {token_grant} tokens to use this chat. You can also contact Dainis personally or input your phone number or email address and preferred contact method and time for Dainis to get back to you.', 'flosc');
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
     */
    private function flosc_get_visitor_depleted_contact_mode($flow_id = '') {
        $flow_stem = $this->flosc_normalize_flow_stem((string) $flow_id);
        $settings = get_option('flosc_flow_' . $flow_stem, []);
        if (!is_array($settings)) {
            return 'message';
        }

        $mode = sanitize_key((string) ($settings['visitor_depleted_contact_mode'] ?? 'message'));
        return in_array($mode, ['message', 'in_chat_form'], true) ? $mode : 'message';
    }
}
