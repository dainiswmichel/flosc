<?php
/**
 * FLOSC User Access Manager
 * Handles visitor/guest/member access levels
 * 
 * @since 9.1.6
 */

if (!defined('ABSPATH')) exit;

class FLOSC_User_Access_Manager {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get current user's access level
     * 
     * @return string 'visitor', 'guest', or 'member'
     */
    public function get_access_level($user_id = null) {
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Not logged in = visitor
        if (!$user_id) {
            return 'visitor';
        }
        
        // Check if member
        if ($this->is_member($user_id)) {
            return 'member';
        }
        
        // Logged in but not member = guest
        return 'guest';
    }
    
    /**
     * Check if user is a member
     * 
     * @param int $user_id
     * @return bool
     */
    public function is_member($user_id) {
        
        // Check user role
        $user = get_user_by('id', $user_id);
        if ($user && in_array('flosc_member', (array) $user->roles)) {
            return true;
        }
        
        // Check meta flag
        $member_status = get_user_meta($user_id, 'flosc_member_status', true);
        if ($member_status === 'active') {
            return true;
        }
        
        // Check if quiz completed (grants member access)
        $quiz_completed = get_user_meta($user_id, 'flosc_quiz_completed', true);
        if ($quiz_completed) {
            return true;
        }
        
        // PSEUDOCODE: Future payment integration
        // if (has_active_subscription($user_id)) {
        //     return true;
        // }
        
        return false;
    }
    
    /**
     * Grant member access to user
     * 
     * @param int $user_id
     * @param string $reason 'quiz_completion', 'payment', 'admin_grant'
     */
    public function grant_member_access($user_id, $reason = 'quiz_completion') {
        
        update_user_meta($user_id, 'flosc_member_status', 'active');
        update_user_meta($user_id, 'flosc_member_granted_date', current_time('mysql'));
        update_user_meta($user_id, 'flosc_member_granted_reason', $reason);
        
        // Log the grant
        error_log("FLOSC: Granted member access to user {$user_id} - Reason: {$reason}");
        
        // Fire action for extensibility
        do_action('flosc_member_access_granted', $user_id, $reason);
    }
    
    /**
     * Revoke member access
     * 
     * @param int $user_id
     */
    public function revoke_member_access($user_id) {
        
        delete_user_meta($user_id, 'flosc_member_status');
        
        error_log("FLOSC: Revoked member access from user {$user_id}");
        
        do_action('flosc_member_access_revoked', $user_id);
    }
    
    /**
     * Get user context for AI
     * Returns all relevant user data
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_context($user_id = null) {
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $access_level = $this->get_access_level($user_id);
        
        $context = [
            'user_id' => $user_id,
            'access_level' => $access_level,
            'is_logged_in' => $user_id > 0,
            'is_member' => $access_level === 'member',
        ];
        
        // Add quiz results if available
        if ($user_id) {
            $quiz_results = get_user_meta($user_id, 'flosc_quiz_results', true);
            $quiz_score = get_user_meta($user_id, 'flosc_quiz_score', true);
            $quiz_date = get_user_meta($user_id, 'flosc_quiz_completed_date', true);
            
            if ($quiz_results) {
                $context['quiz_results'] = $quiz_results;
                $context['quiz_score'] = $quiz_score;
                $context['quiz_date'] = $quiz_date;
                
                // Calculate time since quiz for pricing
                if ($quiz_date) {
                    $quiz_timestamp = strtotime($quiz_date);
                    $minutes_since_quiz = (time() - $quiz_timestamp) / 60;
                    $context['minutes_since_quiz'] = $minutes_since_quiz;
                    $context['within_discount_window'] = $minutes_since_quiz < 30;
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Check if user can access specific content level
     * 
     * @param string $required_level 'visitor', 'guest', or 'member'
     * @param int $user_id
     * @return bool
     */
    public function can_access_level($required_level, $user_id = null) {
        
        $user_level = $this->get_access_level($user_id);
        
        $hierarchy = [
            'visitor' => 1,
            'guest' => 2,
            'member' => 3
        ];
        
        $user_rank = $hierarchy[$user_level] ?? 1;
        $required_rank = $hierarchy[$required_level] ?? 1;
        
        return $user_rank >= $required_rank;
    }
}
