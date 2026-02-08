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
     * @param array $purchase_data Contains offer_id, grants_level, etc.
     */
    public function grant_member_access($user_id, $purchase_data = []) {
        
        update_user_meta($user_id, '_flosc_member_access', 'true');
        update_user_meta($user_id, '_flosc_member_since', time());
        update_user_meta($user_id, '_flosc_purchase_data', $purchase_data);
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: Granted member access to user {$user_id}");
        
        // Grant specific membership level if offer specifies one (v1.0.1)
        if (!empty($purchase_data['grants_level'])) {
            $this->grant_level($user_id, $purchase_data['grants_level']);
        } elseif (!empty($purchase_data['offer_id'])) {
            // Look up offer to get grants_level
            $offers = get_option('flosc_offers', []);
            if (isset($offers[$purchase_data['offer_id']]['grants_level'])) {
                $level = $offers[$purchase_data['offer_id']]['grants_level'];
                if (!empty($level)) {
                    $this->grant_level($user_id, $level);
                }
            }
        }
        
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
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: Revoked member access for user {$user_id}. Reason: {$reason}");
        
        do_action('flosc_member_access_revoked', $user_id, $reason);
    }
    
    /**
     * Check if user has a specific membership level
     * Checks _flosc_memberlevel_{level} user meta
     * 
     * @param int $user_id
     * @param string $level e.g. 'samplecourse', 'spanishcourse'
     * @return bool
     */
    public function has_level($user_id, $level) {
        if (!$user_id || !$level) {
            return false;
        }
        
        $meta_key = '_flosc_memberlevel_' . sanitize_key($level);
        $value = get_user_meta($user_id, $meta_key, true);
        
        return $value === 'yes' || $value === 'true' || $value === true || $value === '1';
    }
    
    /**
     * Grant a specific membership level to user
     * 
     * @param int $user_id
     * @param string $level
     */
    public function grant_level($user_id, $level) {
        if (!$user_id || !$level) {
            return false;
        }
        
        $meta_key = '_flosc_memberlevel_' . sanitize_key($level);
        update_user_meta($user_id, $meta_key, 'yes');
        update_user_meta($user_id, $meta_key . '_since', time());
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: Granted {$level} membership to user {$user_id}");
        
        do_action('flosc_level_granted', $user_id, $level);
        return true;
    }
    
    /**
     * Revoke a specific membership level from user
     * 
     * @param int $user_id
     * @param string $level
     * @param string $reason
     */
    public function revoke_level($user_id, $level, $reason = '') {
        if (!$user_id || !$level) {
            return false;
        }
        
        $meta_key = '_flosc_memberlevel_' . sanitize_key($level);
        delete_user_meta($user_id, $meta_key);
        update_user_meta($user_id, $meta_key . '_revoked', time());
        update_user_meta($user_id, $meta_key . '_revoke_reason', $reason);
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: Revoked {$level} membership from user {$user_id}. Reason: {$reason}");
        
        do_action('flosc_level_revoked', $user_id, $level, $reason);
        return true;
    }
    
    /**
     * Get all membership levels for a user
     * 
     * @param int $user_id
     * @return array List of level names
     */
    public function get_user_levels($user_id) {
        if (!$user_id) {
            return [];
        }
        
        global $wpdb;
        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->usermeta} 
             WHERE user_id = %d 
             AND meta_key LIKE '_flosc_memberlevel_%' 
             AND meta_key NOT LIKE '%_since' 
             AND meta_key NOT LIKE '%_revoked'
             AND meta_key NOT LIKE '%_revoke_reason'
             AND meta_value = 'yes'",
            $user_id
        ));
        
        $levels = [];
        foreach ($meta_keys as $key) {
            $levels[] = str_replace('_flosc_memberlevel_', '', $key);
        }
        
        return $levels;
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
                'is_member' => false,
                'levels' => []
            ];
        }
        
        $member_since = get_user_meta($user_id, '_flosc_member_since', true);
        $purchase_data = get_user_meta($user_id, '_flosc_purchase_data', true);
        $levels = $this->get_user_levels($user_id);
        
        return [
            'is_member' => true,
            'levels' => $levels,
            'member_since' => $member_since,
            'member_since_formatted' => $member_since ? date('Y-m-d H:i:s', $member_since) : '',
            'days_active' => $member_since ? floor((time() - $member_since) / DAY_IN_SECONDS) : 0,
            'purchase_data' => $purchase_data
        ];
    }
    
    // =========================================================================
    // GUEST ACCESS - Free Lesson System (v1.0.1)
    // =========================================================================
    
    /**
     * Grant guest access to a specific post
     * Used for free lessons after quiz completion
     * 
     * @param int $user_id
     * @param int $post_id
     * @return bool
     */
    public function grant_guest_access($user_id, $post_id) {
        if (!$user_id || !$post_id) {
            return false;
        }
        
        $meta_key = '_flosc_guest_access_post_' . intval($post_id);
        update_user_meta($user_id, $meta_key, 'yes');
        
        // Set expiration if configured
        $days = intval(get_option('flosc_guest_access_days', 0));
        if ($days > 0) {
            $expires = time() + ($days * DAY_IN_SECONDS);
            update_user_meta($user_id, $meta_key . '_expires', $expires);
        }
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: Granted guest access to post {$post_id} for user {$user_id}");
        
        do_action('flosc_guest_access_granted', $user_id, $post_id);
        return true;
    }
    
    /**
     * Check if user has guest access to a specific post
     * 
     * @param int $user_id
     * @param int $post_id
     * @return bool
     */
    public function has_guest_access($user_id, $post_id) {
        if (!$user_id || !$post_id) {
            return false;
        }
        
        $meta_key = '_flosc_guest_access_post_' . intval($post_id);
        $access = get_user_meta($user_id, $meta_key, true);
        
        if ($access !== 'yes') {
            return false;
        }
        
        // Check expiration
        $expires = get_user_meta($user_id, $meta_key . '_expires', true);
        if ($expires && intval($expires) < time()) {
            // Access expired
            return false;
        }
        
        return true;
    }
    
    /**
     * Revoke guest access to a specific post
     * 
     * @param int $user_id
     * @param int $post_id
     * @return bool
     */
    public function revoke_guest_access($user_id, $post_id) {
        if (!$user_id || !$post_id) {
            return false;
        }
        
        $meta_key = '_flosc_guest_access_post_' . intval($post_id);
        delete_user_meta($user_id, $meta_key);
        delete_user_meta($user_id, $meta_key . '_expires');
        
        do_action('flosc_guest_access_revoked', $user_id, $post_id);
        return true;
    }
    
    /**
     * Get all posts a user has guest access to
     * 
     * @param int $user_id
     * @return array Array of post IDs
     */
    public function get_guest_access_posts($user_id) {
        if (!$user_id) {
            return [];
        }
        
        global $wpdb;
        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->usermeta} 
             WHERE user_id = %d 
             AND meta_key LIKE '_flosc_guest_access_post_%' 
             AND meta_key NOT LIKE '%_expires'
             AND meta_value = 'yes'",
            $user_id
        ));
        
        $post_ids = [];
        foreach ($meta_keys as $key) {
            $post_id = intval(str_replace('_flosc_guest_access_post_', '', $key));
            if ($post_id && $this->has_guest_access($user_id, $post_id)) {
                $post_ids[] = $post_id;
            }
        }
        
        return $post_ids;
    }
    
    /**
     * Calculate how many free lessons to grant based on admin settings
     * 
     * @param int $missed_count Number of missed quiz items
     * @return int Number of free lessons to grant
     */
    public function calculate_free_lesson_count($missed_count) {
        $mode = get_option('flosc_free_lesson_mode', 'fixed');
        
        if ($mode === 'fixed') {
            return intval(get_option('flosc_free_lesson_count', 1));
        }
        
        // Proportion mode
        $proportion = get_option('flosc_free_lesson_proportion', '1/3');
        $parts = explode('/', $proportion);
        
        if (count($parts) === 2) {
            $numerator = intval($parts[0]);
            $denominator = intval($parts[1]);
            
            if ($denominator > 0) {
                $calculated = ceil($missed_count * $numerator / $denominator);
                return max(1, $calculated); // At least 1
            }
        }
        
        return 1; // Default fallback
    }
    
    /**
     * Grant free lesson access to a user based on missed quiz items
     * 
     * @param int $user_id
     * @param array $missed_post_ids Array of post IDs for missed items
     * @return array Array of post IDs that were granted
     */
    public function grant_free_lessons($user_id, $missed_post_ids) {
        if (!$user_id || empty($missed_post_ids)) {
            return [];
        }
        
        $count = $this->calculate_free_lesson_count(count($missed_post_ids));
        
        // Shuffle and pick random lessons
        shuffle($missed_post_ids);
        $selected = array_slice($missed_post_ids, 0, $count);
        
        $granted = [];
        foreach ($selected as $post_id) {
            if ($this->grant_guest_access($user_id, $post_id)) {
                $granted[] = $post_id;
            }
        }
        
        // Store which lessons were granted as free
        update_user_meta($user_id, '_flosc_free_lessons', $granted);
        update_user_meta($user_id, '_flosc_free_lessons_granted', time());
        
        do_action('flosc_free_lessons_granted', $user_id, $granted);
        
        return $granted;
    }
    
    /**
     * Get the free lessons that were granted to a user
     * 
     * @param int $user_id
     * @return array Array of post IDs
     */
    public function get_free_lessons($user_id) {
        if (!$user_id) {
            return [];
        }
        
        return get_user_meta($user_id, '_flosc_free_lessons', true) ?: [];
    }}