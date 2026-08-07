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
     * Check if user is a member (optionally for one flow only).
     *
     * Prefer FLOSC_Access_Manager when available so UI, tokens, and IVR share one rule.
     *
     * @param int         $user_id
     * @param string|null $flow_id Flow id / ivr / stem. Null = current flow when possible.
     * @return bool
     */
    public function is_member($user_id, $flow_id = null) {
        if (!$user_id) {
            return false;
        }

        // v8.1.0: WordPress admins are always members (for testing all content)
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'sale')) {
            $sale = flosc()->sale();
            if ($sale && method_exists($sale, 'access')) {
                $access = $sale->access();
                if ($access && method_exists($access, 'is_member')) {
                    return (bool) $access->is_member($user_id, $flow_id);
                }
            }
        }

        // Fallback without sale manager: per-flow flag, else legacy global.
        $stem = '';
        if ($flow_id !== null && $flow_id !== '') {
            $stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
            if ($stem === '') {
                $stem = sanitize_key((string) $flow_id);
            }
        }
        if ($stem !== '') {
            $flag = get_user_meta($user_id, '_flosc_member_access_' . $stem, true);
            if ($flag === 'true' || $flag === true || $flag === '1' || $flag === 'yes') {
                return true;
            }
            return false;
        }

        $member_status = get_user_meta($user_id, '_flosc_member_access', true);
        return $member_status === 'true' || $member_status === true || $member_status === '1';
    }
    
    /**
     * Grant member access after purchase
     * 
     * @param int $user_id
     * @param array $purchase_data Contains offer_id, grants_level, flow_id, etc.
     */
    public function grant_member_access($user_id, $purchase_data = []) {
        $purchase_data = is_array($purchase_data) ? $purchase_data : [];

        // Legacy global flag (admin lists, older readers). Not sufficient alone for UI state.
        update_user_meta($user_id, '_flosc_member_access', 'true');
        update_user_meta($user_id, '_flosc_member_since', time());
        update_user_meta($user_id, '_flosc_purchase_data', $purchase_data);

        // Per-flow membership — required for multi-flow (LeSAEp member ≠ flosc.ai member).
        $flow_raw = (string) ($purchase_data['flow_id'] ?? '');
        if ($flow_raw === '' && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_current_flow')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow)) {
                $flow_raw = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
            }
        }
        $stem = sanitize_key(pathinfo(basename($flow_raw), PATHINFO_FILENAME));
        if ($stem === '' && $flow_raw !== '') {
            $stem = sanitize_key($flow_raw);
        }
        if ($stem !== '') {
            update_user_meta($user_id, '_flosc_member_access_' . $stem, 'true');
            update_user_meta($user_id, '_flosc_member_access_' . $stem . '_since', time());
            if (empty($purchase_data['flow_id'])) {
                $purchase_data['flow_id'] = $stem;
            }
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log("FLOSC: Granted member access to user {$user_id}" . ($stem !== '' ? " flow={$stem}" : ''));
        }
        
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
     * Get user's access level for a flow.
     *
     * @param int         $user_id
     * @param string|null $flow_id
     * @return string 'visitor', 'guest', or 'member'
     */
    public function get_access_level($user_id, $flow_id = null) {
        if (!$user_id) {
            return 'visitor';
        }

        if ($this->is_member($user_id, $flow_id)) {
            return 'member';
        }

        return 'guest';
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
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: Revoked member access for user {$user_id}. Reason: {$reason}");
        
        do_action('flosc_member_access_revoked', $user_id, $reason);
    }
    
    /**
     * Equivalent membership level slugs (instance-specific aliases via filter only).
     * FLOSC core does not ship product brand level pairs — instances add their own.
     *
     * @param string $level
     * @return string[]
     */
    public function get_level_aliases($level) {
        $level = sanitize_key((string) $level);
        if ($level === '') {
            return [];
        }

        $aliases = [$level];
        /**
         * Alias groups for an install (e.g. historical product role renames).
         * Default empty — product instances supply groups if needed.
         *
         * @param array[] $groups List of string[] groups of equivalent slugs.
         */
        $groups = apply_filters('flosc_member_level_alias_groups', []);
        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $group = array_map('sanitize_key', $group);
                if (in_array($level, $group, true)) {
                    $aliases = array_merge($aliases, $group);
                }
            }
        }

        /**
         * Filter equivalent membership level slugs for access checks.
         *
         * @param string[] $aliases Candidate level slugs including $level.
         * @param string   $level   Requested level.
         */
        $aliases = apply_filters('flosc_member_level_aliases', $aliases, $level);
        if (!is_array($aliases)) {
            $aliases = [$level];
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $aliases))));
    }

    /**
     * Check if user has a specific membership level
     * Checks _flosc_memberlevel_{level} user meta, WP role, and legacy aliases.
     *
     * @param int $user_id
     * @param string $level e.g. 'samplecourse', 'spanishcourse', 'pronunciation_learners'
     * @return bool
     */
    public function has_level($user_id, $level) {
        if (!$user_id || !$level) {
            return false;
        }

        $user = get_userdata($user_id);
        $roles = ($user && is_array($user->roles)) ? $user->roles : [];

        foreach ($this->get_level_aliases($level) as $candidate) {
            $meta_key = '_flosc_memberlevel_' . $candidate;
            $value = get_user_meta($user_id, $meta_key, true);
            if ($value === 'yes' || $value === 'true' || $value === true || $value === '1') {
                return true;
            }
            // Role-only grants (older accounts / admin-assigned roles without meta).
            if (in_array($candidate, $roles, true)) {
                return true;
            }
        }

        return false;
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
        
        // v8.0.0: Also add as WP role so the level appears in admin Users list filter
        $sanitized_level = sanitize_key($level);
        if (get_role($sanitized_level)) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                if (!in_array($sanitized_level, $user->roles, true)) {
                    $user->add_role($sanitized_level);
                }
                // Remove guest-tier roles on member-level grant when instance defines pairs.
                $guest_aliases = apply_filters('flosc_guest_level_slugs_to_clear_on_member_grant', [], $sanitized_level);
                if (is_array($guest_aliases)) {
                    foreach ($guest_aliases as $guest_alias) {
                        $guest_alias = sanitize_key((string) $guest_alias);
                        if ($guest_alias !== '' && in_array($guest_alias, $user->roles, true)) {
                            $user->remove_role($guest_alias);
                        }
                    }
                }
            }
        }
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: Granted {$level} membership to user {$user_id}");
        
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
        
        // v8.0.0: Remove WP role so user no longer appears under this level in admin
        $sanitized_level = sanitize_key($level);
        $user = get_user_by('id', $user_id);
        if ($user && in_array($sanitized_level, $user->roles, true)) {
            $user->remove_role($sanitized_level);
        }
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: Revoked {$level} membership from user {$user_id}. Reason: {$reason}");
        
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

        // All usermeta for user via core API; filter membership-level keys in PHP.
        $all_meta = get_user_meta( (int) $user_id );
        if ( ! is_array( $all_meta ) ) {
            return array();
        }

        $levels = array();
        foreach ( $all_meta as $key => $vals ) {
            $key = (string) $key;
            if ( 0 !== strpos( $key, '_flosc_memberlevel_' ) ) {
                continue;
            }
            if ( preg_match( '/(_since|_revoked|_revoke_reason)$/', $key ) ) {
                continue;
            }
            $raw = is_array( $vals ) ? ( $vals[0] ?? '' ) : $vals;
            if ( 'yes' === (string) $raw ) {
                $levels[] = str_replace( '_flosc_memberlevel_', '', $key );
            }
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
            'member_since_formatted' => $member_since ? gmdate('Y-m-d H:i:s', $member_since) : '',
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
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: Granted guest access to post {$post_id} for user {$user_id}");
        
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

        $all_meta = get_user_meta( (int) $user_id );
        if ( ! is_array( $all_meta ) ) {
            return array();
        }

        $post_ids = array();
        foreach ( $all_meta as $key => $vals ) {
            $key = (string) $key;
            if ( 0 !== strpos( $key, '_flosc_guest_access_post_' ) ) {
                continue;
            }
            if ( false !== strpos( $key, '_expires' ) ) {
                continue;
            }
            $raw = is_array( $vals ) ? ( $vals[0] ?? '' ) : $vals;
            if ( 'yes' !== (string) $raw ) {
                continue;
            }
            $post_id = (int) str_replace( '_flosc_guest_access_post_', '', $key );
            if ( $post_id && $this->has_guest_access( $user_id, $post_id ) ) {
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
        // v1.5.4: Read from per-flow settings via flow manager
        $flow_manager = FLOSC_Flow_Manager::instance();
        $mode = $flow_manager->get_setting('flosc_free_lesson_mode', 'lessons', 'free_lesson_mode', 'fixed');

        if ($mode === 'fixed') {
            return intval($flow_manager->get_setting('flosc_free_lesson_count', 'lessons', 'free_lesson_count', 1));
        }

        // Proportion mode
        $proportion = $flow_manager->get_setting('flosc_free_lesson_proportion', 'lessons', 'free_lesson_proportion', '1/3');
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