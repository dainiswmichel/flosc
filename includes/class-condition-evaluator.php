<?php
/**
 * FLOSC Condition Evaluator
 * 
 * Evaluates IVR message conditions against current user/session state.
 * 
 * @since 7.0.8
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Condition_Evaluator {
    
    private $context = [];
    private $user_id = 0;
    private $session_shown = [];
    
    /**
     * Constructor
     */
    public function __construct($context = []) {
        $this->context = $context;
        $this->user_id = get_current_user_id();
    }
    
    /**
     * Set context
     */
    public function set_context($context) {
        $this->context = $context;
    }
    
    /**
     * Update context value
     */
    public function update_context($key, $value) {
        $this->context[$key] = $value;
    }
    
    /**
     * Evaluate a condition string
     */
    public function evaluate($condition_string) {
        $condition_string = trim($condition_string);
        
        // Always show
        if ($condition_string === 'always' || empty($condition_string)) {
            return true;
        }
        
        // Never show
        if ($condition_string === 'never') {
            return false;
        }
        
        // Parse and evaluate complex conditions
        return $this->evaluate_expression($condition_string);
    }
    
    /**
     * Evaluate a complex expression with && || ! ()
     */
    private function evaluate_expression($expr) {
        $expr = trim($expr);
        
        // Handle parentheses first
        while (preg_match('/\(([^()]+)\)/', $expr, $matches)) {
            $inner_result = $this->evaluate_expression($matches[1]) ? 'TRUE' : 'FALSE';
            $expr = str_replace($matches[0], $inner_result, $expr);
        }
        
        // Handle OR (||)
        if (strpos($expr, '||') !== false) {
            $parts = preg_split('/\s*\|\|\s*/', $expr);
            foreach ($parts as $part) {
                if ($this->evaluate_expression(trim($part))) {
                    return true;
                }
            }
            return false;
        }
        
        // Handle AND (&&)
        if (strpos($expr, '&&') !== false) {
            $parts = preg_split('/\s*&&\s*/', $expr);
            foreach ($parts as $part) {
                if (!$this->evaluate_expression(trim($part))) {
                    return false;
                }
            }
            return true;
        }
        
        // Handle NOT (!)
        if (strpos($expr, '!') === 0) {
            return !$this->evaluate_expression(substr($expr, 1));
        }
        
        // Handle TRUE/FALSE placeholders
        if ($expr === 'TRUE') return true;
        if ($expr === 'FALSE') return false;
        
        // Evaluate single condition
        return $this->evaluate_single($expr);
    }
    
    /**
     * Evaluate a single condition
     */
    private function evaluate_single($condition) {
        $condition = trim($condition);

        // Time window conditions with Michel timestamp support.
        // Keep timezone parsing simple: UTC with optional +/- offsets.
        // Examples:
        // - active_until_mts("2026-06m-08d-T20h:00m:00s UTC+2")
        // - active_until_mts("2026-06m-08d-T20h:00m:00s UTC-05:30")
        // - active_from_mts("2026-06m-08d-T09h:00m:00s UTC")
        if (preg_match('/^active_until_mts\("([^"]+)"\)$/', $condition, $matches)) {
            $target_ts = $this->parse_mts_with_timezone($matches[1]);
            return ($target_ts !== null) ? (time() <= $target_ts) : false;
        }

        if (preg_match('/^active_from_mts\("([^"]+)"\)$/', $condition, $matches)) {
            $target_ts = $this->parse_mts_with_timezone($matches[1]);
            return ($target_ts !== null) ? (time() >= $target_ts) : false;
        }

        if (preg_match('/^now_before_mts\("([^"]+)"\)$/', $condition, $matches)) {
            $target_ts = $this->parse_mts_with_timezone($matches[1]);
            return ($target_ts !== null) ? (time() < $target_ts) : false;
        }

        if (preg_match('/^now_after_mts\("([^"]+)"\)$/', $condition, $matches)) {
            $target_ts = $this->parse_mts_with_timezone($matches[1]);
            return ($target_ts !== null) ? (time() > $target_ts) : false;
        }
        
        // Score comparisons
        if (preg_match('/^score\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = intval($matches[2]);
            $score = intval($this->context['score'] ?? 0);
            return $this->compare($score, $operator, $value);
        }
        
        // v8.0.3: Initial score comparisons
        if (preg_match('/^initial_score\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = intval($matches[2]);
            $initial_score = intval($this->context['initial_score'] ?? 0);
            return $this->compare($initial_score, $operator, $value);
        }
        
        // Message count comparisons
        if (preg_match('/^message_count\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = intval($matches[2]);
            $count = intval($this->context['message_count'] ?? 0);
            return $this->compare($count, $operator, $value);
        }
        
        // Lessons completed comparisons
        if (preg_match('/^lessons_completed\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = intval($matches[2]);
            $completed = intval($this->context['lessons_completed'] ?? 0);
            return $this->compare($completed, $operator, $value);
        }
        
        // Time-based conditions
        if (preg_match('/^inactive_seconds\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = intval($matches[2]);
            $inactive = intval($this->context['inactive_seconds'] ?? 0);
            return $this->compare($inactive, $operator, $value);
        }
        
        if (preg_match('/^session_seconds\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = intval($matches[2]);
            $seconds = intval($this->context['session_seconds'] ?? 0);
            return $this->compare($seconds, $operator, $value);
        }
        
        if (preg_match('/^session_minutes\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = intval($matches[2]);
            $minutes = intval($this->context['session_minutes'] ?? 0);
            return $this->compare($minutes, $operator, $value);
        }

        // Login / registration tenure (MagicLink admin gates, IVR, offers)
        if (preg_match('/^login_count\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            return $this->compare(intval($this->context['login_count'] ?? 0), $matches[1], intval($matches[2]));
        }
        if (preg_match('/^days_since_registration\s*(>=|<=|>|<|==)\s*(\d+)$/', $condition, $matches)) {
            return $this->compare(intval($this->context['days_since_registration'] ?? 0), $matches[1], intval($matches[2]));
        }
        if (preg_match('/^registration_method\s*==\s*"([^"]+)"$/', $condition, $matches)) {
            return (string) ($this->context['registration_method'] ?? '') === $matches[1];
        }
        if (preg_match('/^registration_method\s*!=\s*"([^"]+)"$/', $condition, $matches)) {
            return (string) ($this->context['registration_method'] ?? '') !== $matches[1];
        }
        
        // Command conditions
        if (preg_match('/^command\s*==\s*"([^"]+)"$/', $condition, $matches)) {
            return ($this->context['command'] ?? '') === $matches[1];
        }
        
        // v8.0.3: Quiz ID comparisons
        if (preg_match('/^quiz_id\s*==\s*"([^"]+)"$/', $condition, $matches)) {
            return ($this->context['quiz_id'] ?? '') === $matches[1];
        }
        if (preg_match('/^quiz_id\s*!=\s*"([^"]+)"$/', $condition, $matches)) {
            return ($this->context['quiz_id'] ?? '') !== $matches[1];
        }
        if (preg_match('/^initial_quiz_id\s*==\s*"([^"]+)"$/', $condition, $matches)) {
            return ($this->context['initial_quiz_id'] ?? '') === $matches[1];
        }
        
        // Offer tracking conditions
        if (preg_match('/^offer_shown_(\w+)$/', $condition, $matches)) {
            return $this->check_offer_state($matches[1], 'shown');
        }
        if (preg_match('/^offer_dismissed_(\w+)$/', $condition, $matches)) {
            return $this->check_offer_state($matches[1], 'dismissed');
        }
        if (preg_match('/^offer_purchased_(\w+)$/', $condition, $matches)) {
            return $this->check_offer_state($matches[1], 'purchased');
        }
        
        // Boolean conditions
        switch ($condition) {
            case 'quiz_taken':
                return !empty($this->context['quiz_taken']);
            case 'logged_in':
                return is_user_logged_in();
            case 'purchased':
                return !empty($this->context['purchased']);
            case 'lesson_viewed':
                return !empty($this->context['lesson_viewed']);
            case 'returning_user':
                return !empty($this->context['returning_user']);
            case 'onboarded':
                return !empty($this->context['onboarded']);
            case 'has_incomplete_lesson':
                return !empty($this->context['has_incomplete_lesson']);
            case 'first_show_session':
                return !empty($this->context['first_show_session']);
            case 'first_message_after_quiz':
                return !empty($this->context['first_message_after_quiz']);
            case 'first_message_after_login':
                return !empty($this->context['first_message_after_login']);
            case 'first_message_after_purchase':
                return !empty($this->context['first_message_after_purchase']);
            case 'first_message_after_free_lesson':
                return !empty($this->context['first_message_after_free_lesson']);
            
            // Access level conditions (v9.2.7)
            case 'is_visitor':
                return ($this->context['access_level'] ?? 'visitor') === 'visitor';
            case 'is_guest':
                return ($this->context['access_level'] ?? 'visitor') === 'guest';
            case 'is_member':
                return ($this->context['access_level'] ?? 'visitor') === 'member';
            case 'has_profile':
                return !empty($this->context['has_profile']) || is_user_logged_in();
            case 'has_sso':
                return !empty($this->context['has_sso']);
        }
        
        // Unknown condition - default to false
        return false;
    }

    /**
     * Parse Michel timestamp with optional timezone token.
     *
    * Supported timezone forms:
    * - UTC
    * - UTC+2, UTC+02, UTC+02:00, UTC-5, UTC-05:30
     * - No explicit token: fallback to site timezone, then system timezone
     */
    private function parse_mts_with_timezone($raw_value) {
        $raw_value = trim((string) $raw_value);
        if ($raw_value === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $raw_value);
        $mts_value = trim((string) ($parts[0] ?? ''));
        $tz_token = trim((string) ($parts[1] ?? ''));

        if (!preg_match('/^(\d{4})-(\d{2})m-(\d{2})d-T(\d{2})h:(\d{2})m:(\d{2})s$/', $mts_value, $m)) {
            return null;
        }

        $timezone = $this->resolve_timezone($tz_token);

        try {
            $date_string = sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                (int) $m[1],
                (int) $m[2],
                (int) $m[3],
                (int) $m[4],
                (int) $m[5],
                (int) $m[6]
            );

            $dt = new DateTime($date_string, $timezone);
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Resolve timezone in this order:
     * 1) explicit token (if provided and valid)
     * 2) site timezone (WordPress)
     * 3) system timezone
     * 4) UTC
     */
    private function resolve_timezone($token = '') {
        $token = strtoupper(trim((string) $token));

        if ($token !== '') {
            if ($token === 'UTC') {
                return new DateTimeZone('UTC');
            }

            // UTC offsets: UTC+2, UTC+02, UTC+02:00, UTC-05:30
            if (preg_match('/^UTC([+-])(\d{1,2})(?::?(\d{2}))?$/', $token, $m)) {
                $sign = $m[1];
                $hours = str_pad((string) min(14, (int) $m[2]), 2, '0', STR_PAD_LEFT);
                $minutes = str_pad((string) min(59, (int) ($m[3] ?? 0)), 2, '0', STR_PAD_LEFT);
                $offset = $sign . $hours . ':' . $minutes;
                try {
                    return new DateTimeZone($offset);
                } catch (Exception $e) {
                    // fall through to fallback order
                }
            }
        }

        // Fallback 1: site timezone (WordPress)
        if (function_exists('wp_timezone')) {
            try {
                $site_tz = wp_timezone();
                if ($site_tz instanceof DateTimeZone) {
                    return $site_tz;
                }
            } catch (Exception $e) {
                // continue fallback
            }
        }

        if (function_exists('wp_timezone_string')) {
            $site_tz_string = trim((string) wp_timezone_string());
            if ($site_tz_string !== '') {
                try {
                    return new DateTimeZone($site_tz_string);
                } catch (Exception $e) {
                    // continue fallback
                }
            }
        }

        // Fallback 2: system timezone
        $system_tz = trim((string) date_default_timezone_get());
        if ($system_tz !== '') {
            try {
                return new DateTimeZone($system_tz);
            } catch (Exception $e) {
                // continue fallback
            }
        }

        // Final fallback
        return new DateTimeZone('UTC');
    }
    
    /**
     * Compare values with operator
     */
    private function compare($left, $operator, $right) {
        switch ($operator) {
            case '>':  return $left > $right;
            case '<':  return $left < $right;
            case '>=': return $left >= $right;
            case '<=': return $left <= $right;
            case '==': return $left == $right;
            default:   return false;
        }
    }
    
    /**
     * Check offer state
     */
    private function check_offer_state($offer_id, $state) {
        if (!$this->user_id) {
            return false;
        }
        $key = "_flosc_offer_{$state}_{$offer_id}";
        return (bool) get_user_meta($this->user_id, $key, true);
    }
    
    /**
     * Mark offer state
     */
    public function mark_offer_state($offer_id, $state) {
        if (!$this->user_id) {
            return false;
        }
        $key = "_flosc_offer_{$state}_{$offer_id}";
        return update_user_meta($this->user_id, $key, current_time('mysql'));
    }
    
    /**
     * Check if message was shown this session
     */
    public function was_shown_this_session($message_name) {
        return isset($this->session_shown[$message_name]);
    }
    
    /**
     * Mark message as shown this session
     */
    public function mark_shown_this_session($message_name) {
        $this->session_shown[$message_name] = true;
    }
    
    /**
     * Check if message was ever shown to user
     */
    public function was_ever_shown($message_name) {
        if (!$this->user_id) {
            return false;
        }
        $key = "_flosc_msg_shown_" . sanitize_key($message_name);
        return (bool) get_user_meta($this->user_id, $key, true);
    }
    
    /**
     * Mark message as shown to user (persistent)
     */
    public function mark_shown($message_name) {
        if (!$this->user_id) {
            return false;
        }
        $key = "_flosc_msg_shown_" . sanitize_key($message_name);
        return update_user_meta($this->user_id, $key, current_time('mysql'));
    }
    
    /**
     * Get all applicable messages for current state
     */
    public function get_applicable_messages($messages, $type = null) {
        $applicable = [];
        
        foreach ($messages as $message) {
            // Filter by type if specified
            if ($type !== null && $message['type'] !== $type) {
                continue;
            }
            
            // Skip if already shown this session (for auto and offer messages)
            if (($message['type'] === 'auto' || $message['type'] === 'offer') && $this->was_shown_this_session($message['name'])) {
                continue;
            }
            
            // Evaluate conditions
            if ($this->evaluate($message['conditions'])) {
                $applicable[] = $message;
            }
        }
        
        return $applicable;
    }
    
    /**
     * Build context from user state
     */
    public static function build_context($user_id = null, $additional = []) {
        $user_id = $user_id ?: get_current_user_id();
        
        $context = [
            'logged_in' => is_user_logged_in(),
            'user_id' => $user_id,
            'access_level' => 'visitor', // v1.6.2: Default access level for is_guest/is_visitor/is_member conditions
            'score' => 0,
            'quiz_taken' => false,
            'purchased' => false,
            'lesson_viewed' => false,
            'returning_user' => false,
            'onboarded' => false,
            'has_incomplete_lesson' => false,
            'lessons_completed' => 0,
            'message_count' => 0,
            'inactive_seconds' => 0,
            'session_seconds' => 0,
            'session_minutes' => 0,
            'first_show_session' => true,
            'first_message_after_quiz' => false,
            'first_message_after_login' => false,
            'first_message_after_purchase' => false,
            'first_message_after_free_lesson' => false,
            // v1.0.4: Bridge data variables for IVR targeting (TASK-009)
            'in_bridge_state' => false,
            'has_quiz_profile' => false,
            'bridge_score' => 0,
            'bridge_correct_count' => 0,
            'bridge_incorrect_count' => 0,
            'weakest_category' => '',
            // Access-link / engagement gates (defaults when no user)
            'login_count' => 0,
            'days_since_registration' => 0,
            'registration_method' => '',
            'has_sso' => false,
        ];

        $skip_last_visit = !empty($additional['_skip_last_visit']);
        unset($additional['_skip_last_visit']);
        
        if ($user_id) {
            $context['score'] = intval(get_user_meta($user_id, '_flosc_last_quiz_score', true));
            $quiz_data = get_user_meta($user_id, '_flosc_last_quiz_data', true);
            $has_phrase_results = is_array($quiz_data)
                && !empty($quiz_data['phrase_results'])
                && is_array($quiz_data['phrase_results'])
                && count($quiz_data['phrase_results']) > 0;
            $context['quiz_taken'] = $has_phrase_results
                || get_user_meta($user_id, '_flosc_quiz_completed_at', true)
                || get_user_meta($user_id, '_flosc_last_quiz_score', true) !== '';
            $context['purchased'] = (bool) get_user_meta($user_id, '_flosc_purchased', true);
            $context['lesson_viewed'] = (bool) get_user_meta($user_id, '_flosc_free_lesson_delivered', true);
            $context['onboarded'] = (bool) get_user_meta($user_id, '_flosc_funnel_completed', true);
            $context['lessons_completed'] = intval(get_user_meta($user_id, '_flosc_lessons_completed', true));
            $context['returning_user'] = (bool) get_user_meta($user_id, '_flosc_last_visit', true);

            $context['login_count'] = intval(get_user_meta($user_id, '_flosc_login_count', true));
            $user_obj = get_userdata($user_id);
            if ($user_obj && !empty($user_obj->user_registered)) {
                $registered_ts = strtotime($user_obj->user_registered);
                if ($registered_ts) {
                    $context['days_since_registration'] = max(0, (int) floor((time() - $registered_ts) / DAY_IN_SECONDS));
                }
            }
            $reg_method = sanitize_key((string) get_user_meta($user_id, '_flosc_registration_method', true));
            if ($reg_method === '' && get_user_meta($user_id, '_flosc_sso_created_via', true)) {
                $reg_method = 'sso';
            }
            $context['registration_method'] = $reg_method;
            $linked = get_user_meta($user_id, '_flosc_sso_linked_providers', true);
            $context['has_sso'] = (is_array($linked) && !empty($linked))
                || (bool) get_user_meta($user_id, '_flosc_sso_created_via', true)
                || (strpos($reg_method, 'sso') === 0);
            
            // v1.9.6: Phase — needed by FLOSC_User_Session for RAG handler
            $context['phase'] = flosc()->determine_flosc_phase();
            
            // v1.9.6: Free lesson number — needed by RAG Access Controller to allow guest lesson access
            $context['free_lesson_number'] = get_user_meta($user_id, '_flosc_free_lesson_number', true) ?: null;
            $context['free_lesson_id'] = get_user_meta($user_id, '_flosc_free_lesson_id', true) ?: null;
            // v1.9.6: Free lessons count — needed by IVR condition evaluation
            if (class_exists('FLOSC_Free_Lesson_Manager')) {
                $free_lesson_mgr = FLOSC_Free_Lesson_Manager::instance();
                $free_lessons = $free_lesson_mgr->get_free_lessons($user_id);
                $context['free_lessons_count'] = is_array($free_lessons) ? count($free_lessons) : ($context['free_lesson_number'] ? 1 : 0);
            } else {
                $context['free_lessons_count'] = $context['free_lesson_number'] ? 1 : 0;
            }
            
            // Per-flow access_level for is_guest / is_visitor / is_member.
            // Global _flosc_member_access alone must not make a member on one
            // flow appear as a member on another flow.
            $flow_for_level = (string) ($context['flow_id'] ?? '');
            if ($flow_for_level === '' && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_current_flow')) {
                $cf = flosc()->get_current_flow();
                if (is_array($cf)) {
                    $flow_for_level = (string) ($cf['ivr_file'] ?? $cf['ivr'] ?? $cf['id'] ?? '');
                }
            }
            $context['access_level'] = 'guest';
            if (function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'sale')) {
                $sale = flosc()->sale();
                if ($sale && method_exists($sale, 'access')) {
                    $acc = $sale->access();
                    if ($acc && method_exists($acc, 'get_simple_state')) {
                        $context['access_level'] = $acc->get_simple_state($user_id, $flow_for_level);
                    }
                }
            } elseif (class_exists('FLOSC_Member_Access')) {
                $context['access_level'] = FLOSC_Member_Access::instance()->get_access_level($user_id, $flow_for_level);
            }
            
            // v1.0.4: Populate bridge data context (TASK-009)
            if (class_exists('FLOSC_Bridge_Data_Manager')) {
                $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
                $context['in_bridge_state'] = $bridge_mgr->is_in_flosc_bridge_state($user_id);
                $context['has_quiz_profile'] = $bridge_mgr->flosc_has_profile($user_id);
                
                $bridge_data = $bridge_mgr->get_flosc_bridge_data($user_id);
                if ($bridge_data) {
                    $context['bridge_score'] = intval($bridge_data['score'] ?? 0);
                    $context['bridge_correct_count'] = count($bridge_data['correct_items'] ?? []);
                    $context['bridge_incorrect_count'] = count($bridge_data['incorrect_items'] ?? []);
                }
                $context['weakest_category'] = $bridge_mgr->get_flosc_weakest_category($user_id) ?? '';
            }
            
            // Chat/session path touches last visit; admin gates pass _skip_last_visit.
            if (!$skip_last_visit) {
                update_user_meta($user_id, '_flosc_last_visit', current_time('mysql'));
            }
        }
        
        // Merge additional context
        return array_merge($context, $additional);
    }
}
