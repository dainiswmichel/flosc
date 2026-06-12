<?php
/**
 * FLOSC Usage Tracker
 * 
 * Tracks usage of metered features:
 * - AI queries
 * - STT minutes
 * - Quiz attempts
 * - Lesson views
 * - Custom events
 * 
 * Supports:
 * - Usage limits (free tier caps)
 * - Metered billing
 * - Usage analytics
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Usage_Tracker {
    
    private $meta_key = '_flosc_usage';
    private $limits_meta_key = '_flosc_usage_limits';
    
    /**
     * Track a usage event
     */
    public function track($user_id, $event, $quantity = 1, $meta = []) {
        $usage = $this->get_user_usage($user_id);
        $period = $this->get_current_period();
        
        if (!isset($usage[$period])) {
            $usage[$period] = [];
        }
        
        if (!isset($usage[$period][$event])) {
            $usage[$period][$event] = [
                'count' => 0,
                'quantity' => 0,
                'first_at' => null,
                'last_at' => null,
            ];
        }
        
        $usage[$period][$event]['count']++;
        $usage[$period][$event]['quantity'] += $quantity;
        $usage[$period][$event]['last_at'] = current_time('mysql');
        
        if (!$usage[$period][$event]['first_at']) {
            $usage[$period][$event]['first_at'] = current_time('mysql');
        }
        
        // Store event detail if meta provided
        if (!empty($meta)) {
            if (!isset($usage[$period][$event]['details'])) {
                $usage[$period][$event]['details'] = [];
            }
            // Keep last 50 details per event
            $usage[$period][$event]['details'][] = array_merge($meta, [
                'timestamp' => current_time('mysql'),
                'quantity' => $quantity,
            ]);
            $usage[$period][$event]['details'] = array_slice($usage[$period][$event]['details'], -50);
        }
        
        update_user_meta($user_id, $this->meta_key, $usage);
        
        do_action('flosc_usage_tracked', $user_id, $event, $quantity, $meta);
        
        return $usage[$period][$event];
    }
    
    /**
     * Get user's usage data
     */
    public function get_user_usage($user_id, $period = null) {
        $usage = get_user_meta($user_id, $this->meta_key, true) ?: [];
        
        if ($period) {
            return $usage[$period] ?? [];
        }
        
        return $usage;
    }
    
    /**
     * Get usage for current period
     */
    public function get_current_usage($user_id) {
        return $this->get_user_usage($user_id, $this->get_current_period());
    }
    
    /**
     * Get usage count for a specific event
     */
    public function get_event_count($user_id, $event, $period = null) {
        $period = $period ?: $this->get_current_period();
        $usage = $this->get_user_usage($user_id, $period);
        
        return $usage[$event]['count'] ?? 0;
    }
    
    /**
     * Get usage quantity for a specific event
     */
    public function get_event_quantity($user_id, $event, $period = null) {
        $period = $period ?: $this->get_current_period();
        $usage = $this->get_user_usage($user_id, $period);
        
        return $usage[$event]['quantity'] ?? 0;
    }
    
    /**
     * Get summary of user's usage across all periods
     */
    public function get_user_summary($user_id) {
        $all_usage = $this->get_user_usage($user_id);
        
        $summary = [
            'total' => [],
            'current_period' => $this->get_current_usage($user_id),
            'periods' => array_keys($all_usage),
        ];
        
        // Aggregate totals
        foreach ($all_usage as $period => $events) {
            foreach ($events as $event => $data) {
                if (!isset($summary['total'][$event])) {
                    $summary['total'][$event] = ['count' => 0, 'quantity' => 0];
                }
                $summary['total'][$event]['count'] += $data['count'];
                $summary['total'][$event]['quantity'] += $data['quantity'];
            }
        }
        
        return $summary;
    }
    
    // =========================================================================
    // USAGE LIMITS
    // =========================================================================
    
    /**
     * Set usage limits for a user
     */
    public function set_limits($user_id, $limits) {
        update_user_meta($user_id, $this->limits_meta_key, $limits);
    }
    
    /**
     * Get user's usage limits
     */
    public function get_limits($user_id) {
        $limits = get_user_meta($user_id, $this->limits_meta_key, true);
        
        if (empty($limits)) {
            // Return default limits
            return $this->get_default_limits();
        }
        
        return $limits;
    }
    
    /**
     * Get default limits (free tier)
     */
    public function get_default_limits() {
        return apply_filters('flosc_default_usage_limits', [
            'ai_queries' => 10,         // Per period
            'stt_minutes' => 2,         // Per period
            'quizzes' => 3,             // Per period
            'lessons' => 1,             // Total (one free lesson)
        ]);
    }
    
    /**
     * Check if user has remaining quota for an event
     */
    public function has_quota($user_id, $event, $quantity = 1) {
        $limits = $this->get_limits($user_id);
        
        // No limit for this event
        if (!isset($limits[$event]) || $limits[$event] === -1) {
            return true;
        }
        
        $used = $this->get_event_quantity($user_id, $event);
        
        return ($used + $quantity) <= $limits[$event];
    }
    
    /**
     * Get remaining quota for an event
     */
    public function get_remaining($user_id, $event) {
        $limits = $this->get_limits($user_id);
        
        if (!isset($limits[$event]) || $limits[$event] === -1) {
            return PHP_INT_MAX; // Unlimited
        }
        
        $used = $this->get_event_quantity($user_id, $event);
        
        return max(0, $limits[$event] - $used);
    }
    
    /**
     * Consume quota (track + check limit in one call)
     */
    public function consume($user_id, $event, $quantity = 1, $meta = []) {
        // Check quota first
        if (!$this->has_quota($user_id, $event, $quantity)) {
            return new WP_Error(
                'quota_exceeded',
                sprintf(__('Usage limit exceeded for %1$s. Remaining: %2$d', 'flosc'), $event, $this->get_remaining($user_id, $event)),
                [
                    'event' => $event,
                    'remaining' => $this->get_remaining($user_id, $event),
                    'requested' => $quantity,
                ]
            );
        }
        
        // Track usage
        return $this->track($user_id, $event, $quantity, $meta);
    }
    
    /**
     * Grant unlimited quota for specific events
     */
    public function grant_unlimited($user_id, $events) {
        $limits = $this->get_limits($user_id);
        
        foreach ((array) $events as $event) {
            $limits[$event] = -1; // -1 = unlimited
        }
        
        $this->set_limits($user_id, $limits);
    }
    
    /**
     * Reset usage for a user (new billing period)
     */
    public function reset_period_usage($user_id) {
        $usage = $this->get_user_usage($user_id);
        $current = $this->get_current_period();
        
        // Archive current period
        if (isset($usage[$current])) {
            $usage[$current]['_archived'] = true;
        }
        
        // Start fresh for new period
        $new_period = $this->get_current_period();
        $usage[$new_period] = [];
        
        update_user_meta($user_id, $this->meta_key, $usage);
        
        do_action('flosc_usage_reset', $user_id);
    }
    
    // =========================================================================
    // PERIODS
    // =========================================================================
    
    /**
     * Get current billing period identifier
     */
    private function get_current_period() {
        // Monthly periods: YYYY-MM
        return gmdate('Y-m');
    }
    
    /**
     * Get period for a specific date
     */
    public function get_period_for_date($date) {
        return gmdate('Y-m', strtotime($date));
    }
    
    /**
     * Clean up old usage data (keep last N periods)
     */
    public function cleanup_old_data($user_id, $keep_periods = 12) {
        $usage = $this->get_user_usage($user_id);
        
        if (count($usage) <= $keep_periods) {
            return;
        }
        
        // Sort by period (newest first)
        krsort($usage);
        
        // Keep only recent periods
        $usage = array_slice($usage, 0, $keep_periods, true);
        
        update_user_meta($user_id, $this->meta_key, $usage);
    }
    
    // =========================================================================
    // ANALYTICS
    // =========================================================================
    
    /**
     * Get aggregate usage across all users for a period
     */
    public function get_global_usage($period = null) {
        global $wpdb;
        
        $period = $period ?: $this->get_current_period();
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only analytics scan on usermeta
        $results = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only analytics scan on usermeta
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $this->meta_key
        ));
        
        $aggregate = [];
        
        foreach ($results as $row) {
            $usage = maybe_unserialize($row->meta_value);
            
            if (isset($usage[$period])) {
                foreach ($usage[$period] as $event => $data) {
                    if ($event[0] === '_') continue; // Skip meta keys
                    
                    if (!isset($aggregate[$event])) {
                        $aggregate[$event] = [
                            'users' => 0,
                            'count' => 0,
                            'quantity' => 0,
                        ];
                    }
                    
                    $aggregate[$event]['users']++;
                    $aggregate[$event]['count'] += $data['count'];
                    $aggregate[$event]['quantity'] += $data['quantity'];
                }
            }
        }
        
        return $aggregate;
    }
    
    /**
     * Get top users by usage
     */
    public function get_top_users($event, $period = null, $limit = 10) {
        global $wpdb;
        
        $period = $period ?: $this->get_current_period();
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only analytics scan on usermeta
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $this->meta_key
        ));
        
        $users = [];
        
        foreach ($results as $row) {
            $usage = maybe_unserialize($row->meta_value);
            
            if (isset($usage[$period][$event])) {
                $users[] = [
                    'user_id' => $row->user_id,
                    'count' => $usage[$period][$event]['count'],
                    'quantity' => $usage[$period][$event]['quantity'],
                ];
            }
        }
        
        // Sort by quantity descending
        usort($users, function($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });
        
        return array_slice($users, 0, $limit);
    }
}
