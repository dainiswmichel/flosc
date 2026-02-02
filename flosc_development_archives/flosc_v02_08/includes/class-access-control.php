<?php
/**
 * FLOSC Access Control
 * Manages user access levels (free vs paid)
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Access_Control {
    
    private $meta_key = '_flosc_paid_access';
    
    /**
     * Check if user has paid access
     */
    public function has_paid_access($user_id) {
        // Check user meta first
        if (get_user_meta($user_id, $this->meta_key, true) === 'yes') {
            return true;
        }
        
        // Check BuddyBoss group membership
        $group_id = get_option('flosc_buddyboss_group_id', 0);
        
        if ($group_id && function_exists('groups_is_user_member')) {
            return groups_is_user_member($user_id, $group_id);
        }
        
        return false;
    }
    
    /**
     * Grant paid access to user
     */
    public function grant_paid_access($user_id) {
        update_user_meta($user_id, $this->meta_key, 'yes');
        update_user_meta($user_id, '_flosc_access_granted', current_time('mysql'));
        
        // Also add to BuddyBoss group if configured
        $group_id = get_option('flosc_buddyboss_group_id', 0);
        
        if ($group_id && function_exists('groups_join_group')) {
            groups_join_group($group_id, $user_id);
        }
        
        // Fire action for integrations
        do_action('flosc_access_granted', $user_id);
        
        return true;
    }
    
    /**
     * Revoke paid access from user
     */
    public function revoke_paid_access($user_id) {
        delete_user_meta($user_id, $this->meta_key);
        update_user_meta($user_id, '_flosc_access_revoked', current_time('mysql'));
        
        // Also remove from BuddyBoss group if configured
        $group_id = get_option('flosc_buddyboss_group_id', 0);
        
        if ($group_id && function_exists('groups_leave_group')) {
            groups_leave_group($group_id, $user_id);
        }
        
        do_action('flosc_access_revoked', $user_id);
        
        return true;
    }
    
    /**
     * Get user access state
     */
    public function get_access_state($user_id = null) {
        if (!$user_id) {
            if (!is_user_logged_in()) {
                return 'visitor';
            }
            $user_id = get_current_user_id();
        }
        
        return $this->has_paid_access($user_id) ? 'paid' : 'free';
    }
    
    /**
     * Get access granted date
     */
    public function get_access_date($user_id) {
        return get_user_meta($user_id, '_flosc_access_granted', true);
    }
    
    /**
     * Check if access is from Stripe payment
     */
    public function get_payment_info($user_id) {
        return [
            'payment_intent' => get_user_meta($user_id, '_flosc_payment_intent', true),
            'purchase_date' => get_user_meta($user_id, '_flosc_purchase_date', true),
        ];
    }
}
