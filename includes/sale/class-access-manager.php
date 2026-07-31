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
     * Check if user is a member (has active offer or subscription)
     * v1.1.1: WordPress admins are always members
     * v8.0.x: Also honor FLOSC_Member_Access (_flosc_member_access / level meta) so
     * sandbox, admin grants, and legacy upgrades unlock sale-side features without
     * a populated _flosc_access ledger.
     */
    public function is_member($user_id) {
        if (!$user_id) {
            return false;
        }

        // v1.1.1: WordPress admins are always members (for testing)
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $access = $this->get_user_access($user_id);

        // Check if they have any active offers
        if (!empty($access['offers'])) {
            foreach ($access['offers'] as $offer_id => $offer_data) {
                if ($this->is_offer_active($offer_data)) {
                    return true;
                }
            }
        }

        // Check if they have an active subscription
        if ($access['subscription'] && $this->is_subscription_active($access['subscription'])) {
            return true;
        }

        // Bridge content-protection membership (role/meta grants).
        if (class_exists('FLOSC_Member_Access')) {
            require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
            if (FLOSC_Member_Access::instance()->is_member($user_id)) {
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
        $access['offers'][$offer['id']] = [
            'purchased_at' => current_time('mysql'),
            'transaction' => $transaction,
            'grants' => $offer['grants'],
            'expires_at' => $this->calculate_expiration($offer),
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
        $history[] = [
            'offer_id'       => $offer['id'] ?? 'unknown',
            'offer_name'     => $offer['name'] ?? '',
            'member_level'   => $member_level,
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
     * Get simple state for frontend (visitor/guest/member)
     */
    /**
     * Get simple user state: visitor, guest, member, or admin
     * 
     * MTS-2026-02-02: [USER-STATE] This function is used for frontend data-user-state attribute.
     * It returns 'member' for admins because the frontend UI (content access, etc) should
     * treat admins the same as members. But for chat responses about user status,
     * use generate_user_status_response() which explicitly says "FLOSC Admin".
     * 
     * IMPORTANT: This is DIFFERENT from generate_user_status_response()!
     * - get_simple_state() → frontend body attribute → member/guest/visitor
     * - generate_user_status_response() → chat response → "FLOSC Admin"/"Member"/etc
     */
    public function get_simple_state($user_id) {
        if (!$user_id) {
            return 'visitor';
        }

        // MTS-2026-02-02: [ADMIN-ACCESS] Admins get 'member' for content access purposes
        if (user_can($user_id, 'manage_options')) {
            return 'member';
        }

        // Check if they are a member (purchased via offer/subscription)
        if ($this->is_member($user_id)) {
            return 'member';
        }

        // Fallback: check _flosc_member_access (set by access code redemption)
        $member_access = get_user_meta($user_id, '_flosc_member_access', true);
        if ($member_access === 'true' || $member_access === true || $member_access === '1') {
            return 'member';
        }

        // Belt-and-suspenders: WP role granted by grant_level()
        $user_roles = (array)(get_userdata($user_id)->roles ?? []);
        foreach (['pronunciation_learners', 'lesaep_learners'] as $member_role) {
            if (user_can($user_id, $member_role) || in_array($member_role, $user_roles, true)) {
                return 'member';
            }
        }

        return 'guest';
    }
}
