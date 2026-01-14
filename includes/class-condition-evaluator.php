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
            
            // Skip if already shown this session (for auto messages)
            if ($message['type'] === 'auto' && $this->was_shown_this_session($message['name'])) {
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
        ];
        
        if ($user_id) {
            $context['score'] = intval(get_user_meta($user_id, '_flosc_last_quiz_score', true));
            $context['quiz_taken'] = !empty($context['score']) || get_user_meta($user_id, '_flosc_quiz_completed_at', true);
            $context['purchased'] = (bool) get_user_meta($user_id, '_flosc_purchased', true);
            $context['lesson_viewed'] = (bool) get_user_meta($user_id, '_flosc_free_lesson_delivered', true);
            $context['onboarded'] = (bool) get_user_meta($user_id, '_flosc_funnel_completed', true);
            $context['lessons_completed'] = intval(get_user_meta($user_id, '_flosc_lessons_completed', true));
            $context['returning_user'] = (bool) get_user_meta($user_id, '_flosc_last_visit', true);
            
            // Update last visit
            update_user_meta($user_id, '_flosc_last_visit', current_time('mysql'));
        }
        
        // Merge additional context
        return array_merge($context, $additional);
    }
}
