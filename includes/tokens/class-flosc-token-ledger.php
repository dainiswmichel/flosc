<?php
/**
 * Domain collaborator — FLOSC_Token_Ledger
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Token_Ledger {

    /** @var FLOSC_Framework */
    private $flosc;

    public function __construct($flosc) {
        $this->flosc = $flosc;
    }

    /**
     * V→G: apply guest_token_grant once per flow (visitor remaining + grant).
     */
    public function flosc_ensure_guest_token_baseline($user_id, $token_provider, $flow_id = '', $reason = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        // Per-flow additive grant; $token_provider unused (wallet is flow meta).
        unset($token_provider, $reason);
        return $this->flosc->flosc_apply_guest_token_grant_once($user_id, $flow_id);
    }

    /**
     * Public entry for product token credits (one-time, recurring, renewals).
     *
     * @param int    $user_id
     * @param string $flow_id
     * @param string $mode    onetime|recurring|recurring_yearly|monthly|yearly
     * @param array  $context
     * @return array
     */
    public function flosc_apply_product_token_credit_public($user_id, $flow_id = '', $mode = 'onetime', $context = []) {
        return $this->flosc->flosc_apply_product_token_credit($user_id, $flow_id, $mode, $context);
    }

    /**
     * @deprecated Use flosc_apply_product_token_credit_public.
     */
    public function flosc_apply_subscription_token_topup_public($user_id, $flow_id = '', $plan_type = 'monthly', $context = []) {
        return $this->flosc->flosc_apply_product_token_credit($user_id, $flow_id, $plan_type, $context);
    }

    /**
     * G→M: apply member_token_grant once per flow (guest remaining + grant).
     * Hooked to flosc_member_access_granted (Access Code, PayPal, sandbox, etc.).
     *
     * @param int   $user_id
     * @param array $purchase_data
     */
    public function apply_member_token_grant_on_access($user_id, $purchase_data = []) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $flow_id = '';
        if (is_array($purchase_data) && !empty($purchase_data['flow_id'])) {
            $flow_id = sanitize_key((string) $purchase_data['flow_id']);
        }
        if ($flow_id === '') {
            $flow_id = sanitize_key((string) get_user_meta($user_id, '_flosc_registration_flow', true));
        }
        if ($flow_id === '') {
            $current_flow = $this->flosc->get_current_flow();
            $ivr_file = (string) ($current_flow['ivr_file'] ?? $current_flow['ivr'] ?? '');
            $flow_id = sanitize_key(pathinfo(basename($ivr_file), PATHINFO_FILENAME));
        }

        return $this->flosc->flosc_apply_member_token_grant_once($user_id, $flow_id);
    }

    /**
     * Whether this user should receive V→G guest tokens on a flow.
     * Members of *this* flow do not; members of other flows still do.
     *
     * @param int    $user_id
     * @param string $flow_id Flow id / stem for the page or request.
     */
    public function flosc_user_should_receive_guest_tokens($user_id, $flow_id = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        if (user_can($user_id, 'manage_options')) {
            return false;
        }

        // Per-flow membership only — membership on one flow must not block guest tokens on another.
        if ($this->flosc->sale() && method_exists($this->flosc->sale(), 'access')) {
            if ($this->flosc->sale()->access()->is_member($user_id, $flow_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read or initialize visitor session token balance.
     */
    public function flosc_get_visitor_session_token_balance($flow_id, $session_id, $token_provider) {
        $session_id = absint($session_id);
        if ($session_id <= 0 || !$token_provider) {
            return 0;
        }

        $transient_key = $this->flosc->flosc_visitor_token_transient_key($flow_id, $session_id);
        $stored = get_transient($transient_key);
        $balance = is_numeric($stored) ? max(0, intval($stored)) : -1;

        if ($balance < 0) {
            $balance = $this->flosc->flosc_get_initial_visitor_token_balance($flow_id, $token_provider);
        }

        set_transient($transient_key, $balance, 30 * DAY_IN_SECONDS);
        return $balance;
    }

    /**
     * Persist visitor session token balance.
     */
    public function flosc_set_visitor_session_token_balance($flow_id, $session_id, $balance) {
        $session_id = absint($session_id);
        if ($session_id <= 0) {
            return 0;
        }

        $balance = max(0, intval($balance));
        set_transient($this->flosc->flosc_visitor_token_transient_key($flow_id, $session_id), $balance, 30 * DAY_IN_SECONDS);
        return $balance;
    }

    /**
     * Apply one spend event against visitor session balance.
     */
    public function flosc_charge_visitor_session_tokens($flow_id, $session_id, $token_provider, $billing_meta = []) {
        $session_id = absint($session_id);
        if ($session_id <= 0 || !$token_provider) {
            return [
                'charged' => false,
                'charge_tokens' => 0,
                'balance_before' => 0,
                'balance_after' => 0,
            ];
        }

        $charge_tokens = max(0, intval($this->flosc->flosc_resolve_chat_charge_tokens($flow_id, $token_provider, $billing_meta)));

        $balance_before = $this->flosc_get_visitor_session_token_balance($flow_id, $session_id, $token_provider);
        if ($charge_tokens <= 0) {
            return [
                'charged' => true,
                'charge_tokens' => 0,
                'balance_before' => $balance_before,
                'balance_after' => $balance_before,
            ];
        }

        if ($balance_before <= 0) {
            return [
                'charged' => false,
                'charge_tokens' => $charge_tokens,
                'balance_before' => $balance_before,
                'balance_after' => $balance_before,
            ];
        }

        $applied_charge = min($charge_tokens, $balance_before);
        $balance_after = $this->flosc_set_visitor_session_token_balance($flow_id, $session_id, $balance_before - $applied_charge);

        return [
            'charged' => true,
            'charge_tokens' => $applied_charge,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
        ];
    }

    /**
     * Compact token display formatter for profile-bar labels.
     * <= 9999 stays full (e.g. 5000). >= 10000 uses compact suffixes.
     */
    public function flosc_format_token_display($value) {
        $value = max(0, intval($value));
        if ($value <= 9999) {
            return (string) $value;
        }

        $units = [
            ['suffix' => 'b', 'size' => 1000000000],
            ['suffix' => 'm', 'size' => 1000000],
            ['suffix' => 'k', 'size' => 1000],
        ];

        foreach ($units as $unit) {
            if ($value >= $unit['size']) {
                return (string) floor($value / $unit['size']) . $unit['suffix'];
            }
        }

        return (string) $value;
    }

    /**
     * V→G additive token grant for the logged-in guest.
    * Client sends visitor_session_id so visitor remaining can be carried after SSO
    * (when the grant on wp_login ran without the flow-domain visitor cookie).
     *
     * @param WP_REST_Request $request flow_id, visitor_session_id
     * @return WP_REST_Response
     */
    public function handle_apply_guest_token_grant($request) {
        nocache_headers();

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'Not logged in'], 401);
        }

        $flow_id = $this->flosc->flosc_normalize_flow_stem((string) ($request->get_param('flow_id') ?? ''));
        if ($flow_id === '' || $flow_id === 'default') {
            $flow_id = $this->flosc->flosc_normalize_flow_stem(
                (string) get_user_meta($user_id, '_flosc_registration_flow', true)
            );
        }

        if (!$this->flosc_user_should_receive_guest_tokens($user_id, $flow_id)) {
            // Admins / members of this flow: return current flow balance (no V→G).
            $balance = $this->flosc->flosc_get_user_flow_token_balance($user_id, $flow_id);
            if ($balance <= 0 && $this->flosc->sale()->access()->is_member($user_id, $flow_id)) {
                $balance = $this->flosc->flosc_apply_member_token_grant_once($user_id, $flow_id);
            }
            return new WP_REST_Response([
                'success' => true,
                'skipped' => true,
                'reason' => 'not_guest',
                'flow_id' => $flow_id,
                'token_balance' => $balance,
                'formatted' => $this->flosc_format_token_display($balance),
            ]);
        }

        $session_raw = sanitize_text_field((string) ($request->get_param('visitor_session_id') ?? ''));
        if ($session_raw === '') {
            $session_raw = $this->flosc->flosc_resolve_visitor_session_id_for_grant();
        }

        $flag_key = $this->flosc->flosc_guest_token_grant_flag_key($flow_id);
        $already = (bool) get_user_meta($user_id, $flag_key, true);
        $remaining_before = $session_raw !== ''
            ? $this->flosc->flosc_get_visitor_remaining_for_session($flow_id, $session_raw)
            : 0;
        $grant_amount = max(0, intval($this->flosc->flosc_get_guest_token_grant_amount($flow_id, $user_id)));

        // Client always sends visitor_session_id when available; allow 0 remaining + grant
        // even if session id is missing (first load without localStorage).
        $balance = $this->flosc->flosc_apply_guest_token_grant_once($user_id, $flow_id, $session_raw, true);

        return new WP_REST_Response([
            'success' => true,
            'token_balance' => $balance,
            'formatted' => $this->flosc_format_token_display($balance),
            'flow_id' => $flow_id,
            'applied_new' => !$already,
            'visitor_remaining' => $remaining_before,
            'grant' => $grant_amount,
            'had_session' => ($session_raw !== ''),
        ]);
    }

    /**
     * v8.0.0: REST handler — resolve a visitor's token count at page load.
     *
     * The header needs the real balance before the first chat turn or admin
     * poll writes it; without this the label sits at its pending placeholder.
     * This is a read-only mirror of the visitor branch of the chat response and
     * the admin poll payload: the server transient stays the sole owner, and the
     * client only renders the returned token_balance shape. Unlike the admin
     * message poll, no chat-log ownership row is required — a visitor may ask for
     * their own count on any surface. Route registered in FLOSC_REST_Trait with
     * check_public_endpoint_permission (rate-limited, public).
     *
     * @param WP_REST_Request $request Expects session_id and flow_id.
     * @return WP_REST_Response { success, token_balance|null }
     */
    public function handle_visitor_session_balance($request) {
        nocache_headers(); // belt-and-suspenders against any caching layer

        $session_id = $this->flosc->flosc_normalize_session_id((string) ($request->get_param('session_id') ?? ''));
        $flow_id    = $this->flosc->flosc_normalize_flow_stem((string) ($request->get_param('flow_id') ?? ''));

        if ($session_id <= 0 || $flow_id === '') {
            return new WP_REST_Response(['success' => false, 'token_balance' => null]);
        }

        $token_provider = $this->flosc->sale()->get_provider('tokens');
        if (!$token_provider) {
            return new WP_REST_Response(['success' => false, 'token_balance' => null]);
        }

        $value = intval($this->flosc_get_visitor_session_token_balance($flow_id, $session_id, $token_provider));

        // Same low-balance resolution the chat response uses, so the header can
        // show the low-tokens nudge consistently across surfaces.
        $low_token_threshold = $this->flosc->flosc_get_low_token_threshold($flow_id);
        $low_tokens_message  = $this->flosc->flosc_get_visitor_low_tokens_message($flow_id);
        $is_low_balance      = ($low_token_threshold > 0 && $value <= $low_token_threshold);

        return new WP_REST_Response([
            'success' => true,
            'token_balance' => [
                'scope'         => 'visitor_session',
                'value'         => $value,
                'formatted'     => $this->flosc_format_token_display($value),
                'low_threshold' => $low_token_threshold,
                'is_low'        => $is_low_balance,
                'low_message'   => $is_low_balance ? $low_tokens_message : '',
            ],
        ]);
    }

}
