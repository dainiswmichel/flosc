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
     */
    public function is_member($user_id) {
        if (!$user_id) {
            return false;
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

        return false;
    }

    /**
     * Check if user has a specific feature
     */
    public function has_feature($user_id, $feature) {
        $access = $this->get_user_access($user_id);

        // Check explicit feature flags
        if (in_array($feature, $access['features'])) {
            return true;
        }

        // Check offers for feature grants
        foreach ($access['offers'] as $offer_id => $offer_data) {
            if (isset($offer_data['grants']['features']) &&
                in_array($feature, $offer_data['grants']['features'])) {

                // Check if offer has expired
                if ($this->is_offer_active($offer_data)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user can access something (feature or custom check)
     */
    public function can_access($user_id, $requirement) {
        // If requirement is a feature name
        if (is_string($requirement)) {
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
            $new_expiration = date('Y-m-d H:i:s', strtotime('+' . $grants['duration_days'] . ' days'));

            // Extend if already has access
            if ($access['expires_at']) {
                $current = strtotime($access['expires_at']);
                $new = strtotime($new_expiration);
                $access['expires_at'] = date('Y-m-d H:i:s', max($current, time()) + ($new - time()));
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
        $member_level = $offer['member_level'] ?? $offer['id'] ?? 'member';
        update_user_meta($user_id, '_flosc_member_level', $member_level);
        update_user_meta($user_id, '_flosc_purchased', true);
        update_user_meta($user_id, '_flosc_purchased_at', current_time('mysql'));

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

        return date('Y-m-d H:i:s', strtotime('+' . $duration . ' days'));
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
    public function get_simple_state($user_id) {
        if (!$user_id) {
            return 'visitor';
        }

        // Check if they are a member
        if ($this->is_member($user_id)) {
            return 'member';
        }

        return 'guest';
    }
}
