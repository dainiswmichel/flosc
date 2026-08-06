<?php
/**
 * FLOSC Access Manager
 *
 * Manages user access based on:
 * - Purchased offers
 * - Active subscriptions
 * - Token balances
 * - Feature flags
 * - Usage limits
 * - Expiration dates
 *
 * User States (FLOSC determines):
 * - visitor: not logged in
 * - guest: logged in, hasn't paid
 * - member: has paid (has active offer or subscription)
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Access_Manager {

    private $meta_key = '_flosc_access';

    /**
     * Get user's complete access state
     */
    public function get_user_access($user_id) {
        $access = get_user_meta($user_id, $this->meta_key, true) ?: [];

        return wp_parse_args($access, [
            'features' => [],
            'offers' => [],          // Purchased offers and their grants
            'subscription' => null,  // Active subscription details
            'expires_at' => null,    // Overall access expiration
            'granted_at' => null,
            'updated_at' => null,
        ]);
    }

    /**
     * Normalize a flow id / ivr path to the stem used in flosc_flow_* options and meta.
     *
     * @param string|null $flow_id Flow id, ivr filename, or stem. Empty → current flow when available.
     * @return string Stem or '' when none can be resolved.
     */
    public function normalize_flow_stem($flow_id = null) {
        $raw = is_string($flow_id) || is_numeric($flow_id) ? (string) $flow_id : '';
        if ($raw === '' && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_current_flow')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow)) {
                $raw = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
            }
        }
        $stem = sanitize_key(pathinfo(basename($raw), PATHINFO_FILENAME));
        if ($stem === '' && $raw !== '') {
            $stem = sanitize_key($raw);
        }
        return $stem;
    }

    /**
     * Member levels that grant paid access on a given flow (never guest* roles).
     *
     * @param string $stem Flow stem.
     * @return string[]
     */
    public function get_flow_member_levels($stem) {
        $stem = sanitize_key((string) $stem);
        $levels = [];
        if ($stem === '') {
            return $levels;
        }

        $flows = get_option('flosc_flows', []);
        if (is_array($flows)) {
            foreach ($flows as $flow) {
                if (!is_array($flow)) {
                    continue;
                }
                $id = sanitize_key((string) ($flow['id'] ?? ''));
                $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? '');
                $flow_stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
                if ($flow_stem === '') {
                    $flow_stem = $id;
                }
                if ($flow_stem !== $stem && $id !== $stem) {
                    continue;
                }
                $default = sanitize_key((string) ($flow['default_member_level'] ?? ''));
                if ($default !== '') {
                    $levels[] = $default;
                }
                $ml = $flow['member_levels'] ?? [];
                if (is_array($ml)) {
                    foreach (array_keys($ml) as $k) {
                        $k = sanitize_key((string) $k);
                        if ($k !== '') {
                            $levels[] = $k;
                        }
                    }
                }
            }
        }

        $settings = get_option('flosc_flow_' . $stem, []);
        if (is_array($settings)) {
            $default = sanitize_key((string) ($settings['default_member_level'] ?? ''));
            if ($default !== '') {
                $levels[] = $default;
            }
            if (!empty($settings['member_level'])) {
                $levels[] = sanitize_key((string) $settings['member_level']);
            }
        }

        // Do NOT call flosc_get_setting() here: without a real flow row it falls back
        // to global flosc_default_member_level (often pronunciation_learners) and
        // incorrectly treats every flow as LeSAEp-member-eligible.

        $levels = array_values(array_unique(array_filter($levels)));
        // Paid levels only — guest roles are not membership.
        return array_values(array_filter($levels, static function ($level) {
            return $level !== '' && strpos($level, 'guest') === false;
        }));
    }

    /**
     * Check if user is a member for a flow (or any flow when $flow_id is null and no current flow).
     *
     * Per-flow rules (when a stem is known):
     * - Explicit _flosc_member_access_{stem}
     * - Holds a paid level declared by that flow
     * - Active offer / purchase history for that flow
     * - Does NOT treat global _flosc_member_access or LeSAEp roles as membership on other flows
     *
     * @param int         $user_id
     * @param string|null $flow_id Flow id / ivr / stem. Null = resolve current flow when possible.
     * @return bool
     */
    public function is_member($user_id, $flow_id = null) {
        if (!$user_id) {
            return false;
        }

        // WordPress admins: member for content testing on every surface.
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $stem = $this->normalize_flow_stem($flow_id);
        if ($stem !== '') {
            return $this->is_member_of_flow((int) $user_id, $stem);
        }

        // No flow context (admin tools, non-app requests): any paid entitlement.
        return $this->is_member_any_flow((int) $user_id);
    }

    /**
     * True when this flow stem is a LeSAEp surface (only place LeSAEp roles mean Member).
     *
     * @param string $stem
     * @return bool
     */
    public function stem_is_lesaep_family($stem) {
        $stem = sanitize_key((string) $stem);
        if ($stem === '') {
            return false;
        }
        if (strpos($stem, 'lesaep') !== false) {
            return true;
        }
        // Match by flow option id / custom domain / ivr when stem alone is ambiguous.
        $flows = get_option('flosc_flows', []);
        if (!is_array($flows)) {
            return false;
        }
        foreach ($flows as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $id = sanitize_key((string) ($flow['id'] ?? ''));
            $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? '');
            $flow_stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
            if ($flow_stem === '') {
                $flow_stem = $id;
            }
            if ($flow_stem !== $stem && $id !== $stem) {
                continue;
            }
            $domain = strtolower((string) ($flow['custom_domain'] ?? ''));
            $name   = strtolower((string) ($flow['name'] ?? ''));
            if (strpos($domain, 'lesaep') !== false || strpos($name, 'lesaep') !== false) {
                return true;
            }
            if (strpos($id, 'lesaep') !== false || strpos($flow_stem, 'lesaep') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Membership on one flow only.
     *
     * Product matrix (e.g. Piano4America):
     * - Member on LeSAEp
     * - Guest on dainis.net/chat (Br3nda) and flosc.ai when logged in
     *
     * Shared WP roles (pronunciation_learners / lesaep_learners) must NOT promote
     * Member on every flow that happens to list the same default_member_level.
     *
     * @param int    $user_id
     * @param string $stem
     * @return bool
     */
    public function is_member_of_flow($user_id, $stem) {
        $user_id = (int) $user_id;
        $stem    = sanitize_key((string) $stem);
        if ($user_id <= 0 || $stem === '') {
            return false;
        }

        // 1) Explicit per-flow grant (purchase / access code / sandbox for this flow).
        $flag = get_user_meta($user_id, '_flosc_member_access_' . $stem, true);
        if ($flag === 'true' || $flag === true || $flag === '1' || $flag === 'yes') {
            return true;
        }

        // 2) Offers / purchase ledger tagged to this flow only.
        if ($this->has_active_offer_for_flow($user_id, $stem)) {
            return true;
        }
        if ($this->has_purchase_history_for_flow($user_id, $stem)) {
            return true;
        }

        // 3) Legacy LeSAEp roles / global flag — ONLY on LeSAEp stems.
        //    Never on Br3nda (dainis_net_ivr) or flosc_ai_ivr.
        if ($this->stem_is_lesaep_family($stem) && $this->user_has_legacy_lesaep_membership($user_id)) {
            return true;
        }

        // 4) Non-LeSAEp: registration_flow matches this stem AND user holds a paid
        //    level declared for this flow (flow-specific product, not LeSAEp bleed).
        if (!$this->stem_is_lesaep_family($stem) && class_exists('FLOSC_Member_Access')) {
            $reg_stem = $this->normalize_flow_stem(
                (string) get_user_meta($user_id, '_flosc_registration_flow', true)
            );
            if ($reg_stem !== '' && $reg_stem === $stem) {
                require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
                $ma = FLOSC_Member_Access::instance();
                foreach ($this->get_flow_member_levels($stem) as $level) {
                    // Skip LeSAEp level slugs on non-LeSAEp flows even if misconfigured.
                    if (strpos($level, 'lesaep') !== false || strpos($level, 'pronunciation') !== false) {
                        continue;
                    }
                    if ($ma->has_level($user_id, $level)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Legacy LeSAEp paid membership (role / level meta / global flag).
     *
     * @param int $user_id
     * @return bool
     */
    private function user_has_legacy_lesaep_membership($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        if (class_exists('FLOSC_Member_Access')) {
            require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
            $ma = FLOSC_Member_Access::instance();
            if (
                $ma->has_level($user_id, 'pronunciation_learners')
                || $ma->has_level($user_id, 'lesaep_learners')
            ) {
                return true;
            }
        }
        $global = get_user_meta($user_id, '_flosc_member_access', true);
        if ($global === 'true' || $global === true || $global === '1') {
            // Global flag only counts as LeSAEp if registration is LeSAEp or empty (old accounts).
            $reg = $this->normalize_flow_stem(
                (string) get_user_meta($user_id, '_flosc_registration_flow', true)
            );
            if ($reg === '' || $this->stem_is_lesaep_family($reg)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int    $user_id
     * @param string $stem
     * @return bool
     */
    private function has_purchase_history_for_flow($user_id, $stem) {
        $history = get_user_meta($user_id, '_flosc_purchase_history', true);
        if (!is_array($history)) {
            return false;
        }
        $stem = sanitize_key((string) $stem);
        foreach ($history as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row_raw  = (string) ($row['flow_id'] ?? '');
            $row_stem = sanitize_key(pathinfo(basename($row_raw), PATHINFO_FILENAME));
            if ($row_stem === '') {
                $row_stem = sanitize_key($row_raw);
            }
            if ($row_stem !== '' && $row_stem === $stem) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when the user holds paid access on at least one flow (legacy / no-context).
     *
     * @param int $user_id
     * @return bool
     */
    public function is_member_any_flow($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        $access = $this->get_user_access($user_id);
        if (!empty($access['offers'])) {
            foreach ($access['offers'] as $offer_data) {
                if ($this->is_offer_active($offer_data)) {
                    return true;
                }
            }
        }
        if (!empty($access['subscription']) && $this->is_subscription_active($access['subscription'])) {
            return true;
        }

        // Any explicit per-flow member flag (core usermeta API).
        $all_meta = get_user_meta( (int) $user_id );
        if ( is_array( $all_meta ) ) {
            foreach ( $all_meta as $meta_key => $vals ) {
                $meta_key = (string) $meta_key;
                if ( 0 !== strpos( $meta_key, '_flosc_member_access_' ) ) {
                    continue;
                }
                $raw = is_array( $vals ) ? ( $vals[0] ?? '' ) : $vals;
                $raw = is_string( $raw ) || is_numeric( $raw ) || is_bool( $raw ) ? (string) $raw : '';
                if ( in_array( $raw, array( 'true', '1', 'yes' ), true ) ) {
                    return true;
                }
            }
        }

        // Legacy global flag or any non-guest member level meta.
        $global = get_user_meta($user_id, '_flosc_member_access', true);
        if ($global === 'true' || $global === true || $global === '1') {
            return true;
        }

        if (class_exists('FLOSC_Member_Access')) {
            require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
            $ma = FLOSC_Member_Access::instance();
            foreach ((array) $ma->get_user_levels($user_id) as $level) {
                $level = sanitize_key((string) $level);
                if ($level !== '' && strpos($level, 'guest') === false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether an active _flosc_access offer belongs to this flow.
     *
     * @param int    $user_id
     * @param string $stem
     * @return bool
     */
    private function has_active_offer_for_flow($user_id, $stem) {
        $access = $this->get_user_access($user_id);
        if (empty($access['offers']) || !is_array($access['offers'])) {
            return false;
        }

        $offer_manager = null;
        if (function_exists('flosc_sale') && flosc_sale() && method_exists(flosc_sale(), 'offers')) {
            $offer_manager = flosc_sale()->offers();
        }

        foreach ($access['offers'] as $offer_id => $offer_data) {
            if (!$this->is_offer_active($offer_data)) {
                continue;
            }
            // Require explicit flow_id on the grant — do not treat "offer id exists
            // in this flow's catalog" as purchase of this flow (cross-flow bleed).
            $offer_flow = sanitize_key((string) ($offer_data['flow_id'] ?? ''));
            if ($offer_flow === '') {
                continue;
            }
            $offer_stem = sanitize_key(pathinfo(basename($offer_flow), PATHINFO_FILENAME));
            if ($offer_stem === '') {
                $offer_stem = $offer_flow;
            }
            if ($offer_stem === $stem || $offer_flow === $stem) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has a specific feature
     */
    public function has_feature($user_id, $feature) {
        $access = $this->get_user_access($user_id);
        $feature = (string) $feature;

        // Check explicit feature flags
        if (in_array($feature, $access['features'], true)) {
            return true;
        }

        // Check offers for feature grants
        foreach ($access['offers'] as $offer_id => $offer_data) {
            if (isset($offer_data['grants']['features']) &&
                in_array($feature, $offer_data['grants']['features'], true)) {

                // Check if offer has expired
                if ($this->is_offer_active($offer_data)) {
                    return true;
                }
            }
        }

        // Content features for members who have level meta/roles but empty _flosc_access
        // (e.g. Piano4America sandbox / admin grant path).
        $member_content_features = [
            'all_lessons',
            'full_access',
            'lesaep_lessons',
            'pronunciation_exercises',
            'audio_recordings',
            'ipa_training',
            'ai_coach',
        ];
        if (in_array($feature, $member_content_features, true) && class_exists('FLOSC_Member_Access')) {
            require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
            $member_access = FLOSC_Member_Access::instance();
            if (
                $member_access->has_level($user_id, 'pronunciation_learners')
                || $member_access->has_level($user_id, 'lesaep_learners')
            ) {
                return true;
            }
            if (in_array($feature, ['all_lessons', 'full_access'], true) && $member_access->is_member($user_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can access something (feature or custom check).
     *
     * String aliases:
     * - 'full' / 'member' → any full member (offer, subscription, or FLOSC_Member_Access)
     * - other strings → feature flag via has_feature()
     */
    public function can_access($user_id, $requirement) {
        // If requirement is a feature name
        if (is_string($requirement)) {
            $requirement = strtolower(trim($requirement));
            // Full membership (not a narrow feature id) — keep userState consistent for AI/IVR.
            if ($requirement === 'full' || $requirement === 'member') {
                return $this->is_member($user_id);
            }
            return $this->has_feature($user_id, $requirement);
        }

        // If requirement is an array with conditions
        if (is_array($requirement)) {
            // Check feature requirement
            if (isset($requirement['feature'])) {
                if (!$this->has_feature($user_id, $requirement['feature'])) {
                    return false;
                }
            }

            // Check offer requirement
            if (isset($requirement['offer'])) {
                if (!$this->has_offer($user_id, $requirement['offer'])) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Grant access from an offer purchase
     */
    public function grant_from_offer($user_id, $offer, $transaction = []) {
        $access = $this->get_user_access($user_id);

        // Record the offer purchase
        $offer_flow_id = (string) ($transaction['flow_id'] ?? $offer['flow_id'] ?? '');
        if ($offer_flow_id === '') {
            $offer_flow_id = $this->normalize_flow_stem(null);
        }
        $access['offers'][$offer['id']] = [
            'purchased_at' => current_time('mysql'),
            'transaction' => $transaction,
            'grants' => $offer['grants'],
            'expires_at' => $this->calculate_expiration($offer),
            'flow_id' => $offer_flow_id,
        ];

        // Apply grants
        $grants = $offer['grants'];

        // Merge features
        if (!empty($grants['features'])) {
            $access['features'] = array_unique(array_merge(
                $access['features'],
                $grants['features']
            ));
        }

        // Set/extend expiration
        if (!empty($grants['duration_days']) && $grants['duration_days'] > 0) {
            $new_expiration = gmdate('Y-m-d H:i:s', strtotime('+' . $grants['duration_days'] . ' days'));

            // Extend if already has access
            if ($access['expires_at']) {
                $current = strtotime($access['expires_at']);
                $new = strtotime($new_expiration);
                $access['expires_at'] = gmdate('Y-m-d H:i:s', max($current, time()) + ($new - time()));
            } else {
                $access['expires_at'] = $new_expiration;
            }
        } elseif (empty($grants['duration_days'])) {
            // Lifetime access
            $access['expires_at'] = null;
        }

        // Handle subscription
        if ($offer['type'] === 'subscription' && isset($transaction['subscription_id'])) {
            $access['subscription'] = [
                'id' => $transaction['subscription_id'],
                'provider' => $transaction['provider'] ?? 'stripe',
                'status' => 'active',
                'started_at' => current_time('mysql'),
            ];
        }

        // Handle token grants
        if ($offer['type'] === 'tokens' && !empty($offer['tokens']['amount'])) {
            $token_provider = flosc_sale()->get_provider('tokens');
            if ($token_provider) {
                $total = ($offer['tokens']['amount'] ?? 0) + ($offer['tokens']['bonus'] ?? 0);
                $token_provider->credit($user_id, $total, 'Purchase: ' . $offer['name']);
            }
        }

        // Apply usage limits from offer
        if (!empty($grants['usage_limits'])) {
            $usage_tracker = flosc_sale()->usage();
            $current_limits = $usage_tracker->get_limits($user_id);

            foreach ($grants['usage_limits'] as $event => $limit) {
                // -1 means unlimited
                if ($limit === -1 || !isset($current_limits[$event]) || $limit > $current_limits[$event]) {
                    $current_limits[$event] = $limit;
                }
            }

            $usage_tracker->set_limits($user_id, $current_limits);
        }

        $access['granted_at'] = $access['granted_at'] ?? current_time('mysql');
        $access['updated_at'] = current_time('mysql');

        update_user_meta($user_id, $this->meta_key, $access);
        
        // v9.5.5: Store member level for IVR conditions
        // v8.0.1: Fixed — offer schema stores level at grants.level, not member_level
        $member_level = $offer['grants']['level'] ?? $offer['member_level'] ?? $offer['id'] ?? 'member';
        update_user_meta($user_id, '_flosc_member_level', $member_level);
        update_user_meta($user_id, '_flosc_purchased', true);
        update_user_meta($user_id, '_flosc_purchased_at', current_time('mysql'));

        // v8.0.1: Atomic purchase counter + immutable purchase history ledger.
        // Counter: quick integer read for admin display.
        // History: append-only log of every transaction — never delete, never overwrite.
        $count = (int) get_user_meta($user_id, '_flosc_purchase_count', true);
        update_user_meta($user_id, '_flosc_purchase_count', $count + 1);

        $history = get_user_meta($user_id, '_flosc_purchase_history', true) ?: [];
        $history_flow = (string) ($transaction['flow_id'] ?? $offer['flow_id'] ?? '');
        if ($history_flow === '') {
            $history_flow = $this->normalize_flow_stem(null);
        }
        $history[] = [
            'offer_id'       => $offer['id'] ?? 'unknown',
            'offer_name'     => $offer['name'] ?? '',
            'member_level'   => $member_level,
            'flow_id'        => $history_flow,
            'transaction_id' => $transaction['transaction_id'] ?? null,
            'provider'       => $transaction['provider'] ?? 'unknown',
            'amount'         => $transaction['amount'] ?? 0,
            'currency'       => $transaction['currency'] ?? '',
            'timestamp'      => current_time('mysql'),
            'purchase_number' => $count + 1,
        ];
        update_user_meta($user_id, '_flosc_purchase_history', $history);

        do_action('flosc_access_granted', $user_id, $offer, $access);

        return $access;
    }

    /**
     * Grant feature directly
     */
    public function grant_feature($user_id, $feature) {
        $access = $this->get_user_access($user_id);

        if (!in_array($feature, $access['features'])) {
            $access['features'][] = $feature;
            $access['updated_at'] = current_time('mysql');
            update_user_meta($user_id, $this->meta_key, $access);
        }

        return true;
    }

    /**
     * Revoke feature
     */
    public function revoke_feature($user_id, $feature) {
        $access = $this->get_user_access($user_id);

        $access['features'] = array_filter($access['features'], function($f) use ($feature) {
            return $f !== $feature;
        });

        $access['updated_at'] = current_time('mysql');
        update_user_meta($user_id, $this->meta_key, $access);

        return true;
    }

    /**
     * Check if user has purchased a specific offer
     */
    public function has_offer($user_id, $offer_id) {
        $access = $this->get_user_access($user_id);

        if (!isset($access['offers'][$offer_id])) {
            return false;
        }

        return $this->is_offer_active($access['offers'][$offer_id]);
    }

    /**
     * Revoke all access (reset to guest)
     */
    public function revoke_all($user_id) {
        $access = [
            'features' => [],
            'offers' => [],
            'subscription' => null,
            'expires_at' => null,
            'granted_at' => null,
            'updated_at' => current_time('mysql'),
        ];

        update_user_meta($user_id, $this->meta_key, $access);

        do_action('flosc_access_revoked', $user_id);

        return true;
    }

    /**
     * Update subscription status
     */
    public function update_subscription($user_id, $subscription_data) {
        $access = $this->get_user_access($user_id);

        if ($access['subscription']) {
            $access['subscription'] = array_merge($access['subscription'], $subscription_data);
        } else {
            $access['subscription'] = $subscription_data;
        }

        $access['updated_at'] = current_time('mysql');
        update_user_meta($user_id, $this->meta_key, $access);

        return $access;
    }

    /**
     * Cancel subscription access
     */
    public function cancel_subscription($user_id) {
        $access = $this->get_user_access($user_id);

        if ($access['subscription']) {
            $access['subscription']['status'] = 'canceled';
            $access['subscription']['canceled_at'] = current_time('mysql');
        }

        $access['updated_at'] = current_time('mysql');
        update_user_meta($user_id, $this->meta_key, $access);

        do_action('flosc_subscription_canceled', $user_id);

        return true;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Calculate expiration date from offer
     */
    private function calculate_expiration($offer) {
        $duration = $offer['grants']['duration_days'] ?? 0;

        if ($duration <= 0) {
            return null; // Lifetime
        }

        return gmdate('Y-m-d H:i:s', strtotime('+' . $duration . ' days'));
    }

    /**
     * Check if an offer is still active (not expired)
     */
    private function is_offer_active($offer_data) {
        if (empty($offer_data['expires_at'])) {
            return true; // Lifetime
        }

        return strtotime($offer_data['expires_at']) > time();
    }

    /**
     * Check if subscription is active
     */
    private function is_subscription_active($subscription) {
        if (!$subscription) {
            return false;
        }

        return in_array($subscription['status'], ['active', 'trialing']);
    }

    /**
     * Resolve the only three real-world app states.
     *
     * Real-world (one browser host, one session):
     * - No authenticated user  → visitor
     * - Authenticated, unpaid  → guest   (tokens/wallet from user profile for this flow)
     * - Authenticated + paid   → member  (for this flow only)
     *
     * A non-zero $user_id MUST NEVER return visitor. Multi-host same-user testing
     * is rare; do not invent visitor UI for a known WP user.
     *
     * @param int         $user_id 0 = anonymous
     * @param string|null $flow_id Flow id / ivr / stem for paid-tier check
     * @return string visitor|guest|member
     */
    public function get_simple_state($user_id, $flow_id = null) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 'visitor';
        }

        if ($this->is_member($user_id, $flow_id)) {
            return 'member';
        }

        return 'guest';
    }
}
