<?php
/**
 * Access Control
 * Handles user access permissions and content gating
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeSAEp_Access_Control {

    /**
     * Check if user has paid access
     */
    public static function has_paid_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Check BuddyBoss group membership
        $group_id = get_option('lesaep_paid_group_id', 0);
        if ($group_id && function_exists('groups_is_user_member')) {
            if (groups_is_user_member($user_id, $group_id)) {
                return true;
            }
        }

        // Check user meta flag
        if (get_user_meta($user_id, '_lesaep_paid_access', true)) {
            return true;
        }

        return false;
    }

    /**
     * Grant paid access to user
     */
    public static function grant_access($user_id) {
        if (!$user_id) {
            return false;
        }

        // Set user meta flag
        update_user_meta($user_id, '_lesaep_paid_access', true);

        // Add to BuddyBoss group if configured
        $group_id = get_option('lesaep_paid_group_id', 0);
        if ($group_id && function_exists('groups_join_group')) {
            groups_join_group($group_id, $user_id);
        }

        return true;
    }

    /**
     * Revoke paid access from user
     */
    public static function revoke_access($user_id) {
        if (!$user_id) {
            return false;
        }

        // Remove user meta flag
        delete_user_meta($user_id, '_lesaep_paid_access');

        // Remove from BuddyBoss group if configured
        $group_id = get_option('lesaep_paid_group_id', 0);
        if ($group_id && function_exists('groups_leave_group')) {
            groups_leave_group($group_id, $user_id);
        }

        return true;
    }

    /**
     * Get user state (visitor, free, paid)
     */
    public static function get_user_state($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return 'visitor';
        }

        if (self::has_paid_access($user_id)) {
            return 'paid';
        }

        return 'free';
    }
}
