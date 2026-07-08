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
     * Uses the flow grant so chat reset/new visitor behavior is configurable.
     */
    private function flosc_get_initial_visitor_token_balance($flow_id = '', $token_provider = null) {
        $from_grant = max(0, intval($this->flosc_get_guest_token_grant_amount((string) $flow_id, 0)));
        if ($from_grant > 0) {
            return $from_grant;
        }

        if ($token_provider && method_exists($token_provider, 'get_communication_economics')) {
            $economics = (array) $token_provider->get_communication_economics();
            return max(0, intval($economics['tokens_per_message'] ?? 5000));
        }

        return 5000;
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

        $baseline = max(0, intval($this->flosc_get_guest_token_grant_amount((string) $flow_id, $user_id)));
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

        $baseline = max(0, intval($this->flosc_get_guest_token_grant_amount((string) $flow_id, $user_id)));
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

        $action_costs = method_exists($token_provider, 'get_action_costs')
            ? (array) $token_provider->get_action_costs()
            : [];
        $charge_tokens = max(0, intval($action_costs['ai_query'] ?? 0));

        $real_millicents = intval($billing_meta['real_millicents'] ?? 0);
        if ($real_millicents > 0 && method_exists($token_provider, 'convert_real_millicents_to_tokens')) {
            $charge_tokens = max(1, intval($token_provider->convert_real_millicents_to_tokens($real_millicents)));
        }

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
