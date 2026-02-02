<?php
/**
 * FLOSC Member Access Manager
 * Checks membership status and grants access
 * 
 * STATUS: ✅ FULLY FUNCTIONAL  
 * - Hooks into flosc_purchase_completed action (v9.1.9)
 * - Checks _flosc_member_access user meta
 * - Three-tier access: visitor → guest → member
 * - Member statistics tracking
 * 
 * USER META:
 * - _flosc_member_access: 'true'/'false'
 * - _flosc_member_since: timestamp
 * - _flosc_purchase_data: array
 * 
 * @since 9.1.8
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Member_Access {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into purchase completion
        add_action('flosc_purchase_completed', [$this, 'grant_member_access'], 10, 2);
    }
    
    /**
     * Check if user is a member (has purchased)
     * 
     * @param int $user_id
     * @return bool
     */
    public function is_member($user_id) {
        
        if (!$user_id) {
            return false;
        }
        
        $member_status = get_user_meta($user_id, '_flosc_member_access', true);
        
        return $member_status === 'true' || $member_status === true || $member_status === '1';
    }
    
    /**
     * Grant member access after purchase
     * 
     * @param int $user_id
     * @param array $purchase_data
     */
    public function grant_member_access($user_id, $purchase_data = []) {
        
        update_user_meta($user_id, '_flosc_member_access', 'true');
        update_user_meta($user_id, '_flosc_member_since', time());
        update_user_meta($user_id, '_flosc_purchase_data', $purchase_data);
        
        error_log("FLOSC: Granted member access to user {$user_id}");
        
        // Trigger any additional member welcome actions
        do_action('flosc_member_access_granted', $user_id, $purchase_data);
    }
    
    /**
     * Get user's access level
     * 
     * @param int $user_id
     * @return string 'visitor', 'guest', or 'member'
     */
    public function get_access_level($user_id) {
        
        // Check if member
        if ($this->is_member($user_id)) {
            return 'member';
        }
        
        // Check if logged in (guest)
        if ($user_id && is_user_logged_in()) {
            return 'guest';
        }
        
        // Not logged in (visitor)
        return 'visitor';
    }
    
    /**
     * Check if user can access specific content
     * 
     * @param int $user_id
     * @param string $required_level 'visitor', 'guest', or 'member'
     * @return bool
     */
    public function can_access($user_id, $required_level = 'member') {
        
        $user_level = $this->get_access_level($user_id);
        
        $hierarchy = [
            'visitor' => 0,
            'guest' => 1,
            'member' => 2
        ];
        
        $required = $hierarchy[$required_level] ?? 2;
        $current = $hierarchy[$user_level] ?? 0;
        
        return $current >= $required;
    }
    
    /**
     * Revoke member access (for refunds, etc.)
     * 
     * @param int $user_id
     * @param string $reason
     */
    public function revoke_member_access($user_id, $reason = '') {
        
        update_user_meta($user_id, '_flosc_member_access', 'false');
        update_user_meta($user_id, '_flosc_access_revoked', time());
        update_user_meta($user_id, '_flosc_revoke_reason', $reason);
        
        error_log("FLOSC: Revoked member access for user {$user_id}. Reason: {$reason}");
        
        do_action('flosc_member_access_revoked', $user_id, $reason);
    }
    
    /**
     * Get member statistics
     * 
     * @param int $user_id
     * @return array
     */
    public function get_member_stats($user_id) {
        
        if (!$this->is_member($user_id)) {
            return [
                'is_member' => false
            ];
        }
        
        $member_since = get_user_meta($user_id, '_flosc_member_since', true);
        $purchase_data = get_user_meta($user_id, '_flosc_purchase_data', true);
        
        return [
            'is_member' => true,
            'member_since' => $member_since,
            'member_since_formatted' => $member_since ? date('Y-m-d H:i:s', $member_since) : '',
            'days_active' => $member_since ? floor((time() - $member_since) / DAY_IN_SECONDS) : 0,
            'purchase_data' => $purchase_data
        ];
    }
}
