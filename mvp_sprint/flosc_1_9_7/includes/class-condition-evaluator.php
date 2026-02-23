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
        }
        
        // Unknown condition - default to false
        return false;
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
        ];
        
        if ($user_id) {
            $context['score'] = intval(get_user_meta($user_id, '_flosc_last_quiz_score', true));
            $context['quiz_taken'] = !empty($context['score']) || get_user_meta($user_id, '_flosc_quiz_completed_at', true);
            $context['purchased'] = (bool) get_user_meta($user_id, '_flosc_purchased', true);
            $context['lesson_viewed'] = (bool) get_user_meta($user_id, '_flosc_free_lesson_delivered', true);
            $context['onboarded'] = (bool) get_user_meta($user_id, '_flosc_funnel_completed', true);
            $context['lessons_completed'] = intval(get_user_meta($user_id, '_flosc_lessons_completed', true));
            $context['returning_user'] = (bool) get_user_meta($user_id, '_flosc_last_visit', true);
            
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
            
            // v1.6.2: Set access_level for is_guest/is_visitor/is_member condition evaluation
            $has_member_access = (bool) get_user_meta($user_id, '_flosc_member_access', true);
            $context['access_level'] = $has_member_access ? 'member' : 'guest';
            
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
            
            // Update last visit
            update_user_meta($user_id, '_flosc_last_visit', current_time('mysql'));
        }
        
        // Merge additional context
        return array_merge($context, $additional);
    }
}
