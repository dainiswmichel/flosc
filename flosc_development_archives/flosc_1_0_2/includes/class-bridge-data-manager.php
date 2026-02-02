<?php
/**
 * FLOSC Bridge Data Manager
 * 
 * Handles bridge data state creation, storage, and retrieval.
 * Bridge data = quiz completed + user profile created + free preview shown
 * 
 * User states:
 * 1. No profile: Anonymous, can take quiz but no email
 * 2. Profile, not paid (BRIDGE DATA STATE): Took quiz, has WordPress profile + email, can see preview
 * 3. Profile, paid: Has access to full content
 * 
 * USER META KEYS:
 * - _flosc_bridge_data_state: bool - Is user in bridge state?
 * - _flosc_completed_quiz_{quiz_id}: array - Quiz results for specific quiz
 * - _flosc_quiz_attempts: array - All quiz attempt timestamps
 * - _flosc_bridge_viewed_{quiz_id}: string - When user viewed bridge data
 * - _flosc_weakest_category: string - Calculated weakest topic
 * 
 * HOOKS:
 * - flosc_quiz_completed: Auto-creates bridge data when quiz finishes
 * - flosc_bridge_data_created: Fires after bridge data stored
 * - flosc_bridge_data_viewed: Fires when user views their results
 * - flosc_external_quiz_score: Accepts scores from external quiz plugins
 * 
 * @package FLOSC
 * @since 1.0.2
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Bridge_Data_Manager {
    
    /**
     * Singleton instance
     * @var FLOSC_Bridge_Data_Manager
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * @return FLOSC_Bridge_Data_Manager
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - register hooks
     */
    private function __construct() {
        // Hook into FLOSC quiz completion
        add_action('flosc_quiz_completed', [$this, 'handle_quiz_completion'], 5, 2);
        
        // Hook for external quiz plugins
        add_action('flosc_external_quiz_score', [$this, 'handle_external_quiz'], 10, 3);
        
        // Alternative hook names for common quiz plugins
        add_action('learndash_quiz_completed', [$this, 'handle_learndash_quiz'], 10, 2);
        add_action('tutor_quiz_finished', [$this, 'handle_tutor_quiz'], 10, 3);
    }
    
    /**
     * Handle FLOSC quiz completion
     * 
     * @param array $quiz_result Quiz results
     * @param int $user_id User ID
     */
    public function handle_quiz_completion($quiz_result, $user_id) {
        if (!$user_id) {
            return;
        }
        
        $quiz_id = $quiz_result['quiz_id'] ?? 'default';
        
        // Build scoring results from quiz data
        $scoring_results = [
            'score' => $quiz_result['score'] ?? 0,
            'percentage' => $quiz_result['score'] ?? 0,
            'total_questions' => 10, // Default for FLOSC sample quiz
            'correct_items' => $quiz_result['correct'] ?? [],
            'incorrect_items' => $quiz_result['incorrect'] ?? [],
            'item_results' => $this->build_item_results($quiz_result),
            'user_answer' => $quiz_result['user_answer'] ?? '',
            'correct_answer' => $quiz_result['correct_answer'] ?? '',
        ];
        
        $this->flosc_create_bridge_data($user_id, $quiz_id, $scoring_results);
    }
    
    /**
     * Handle external quiz plugin scores
     * 
     * Universal hook for any quiz plugin to feed scores into FLOSC.
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id External quiz identifier
     * @param array $score_data Score data with keys:
     *   - score: (int) Percentage score 0-100
     *   - correct_items: (array) IDs/names of correct answers
     *   - incorrect_items: (array) IDs/names of incorrect answers
     *   - categories: (array) Optional category breakdown
     */
    public function handle_external_quiz($user_id, $quiz_id, $score_data) {
        if (!$user_id || !is_array($score_data)) {
            return;
        }
        
        $scoring_results = [
            'score' => intval($score_data['score'] ?? 0),
            'percentage' => intval($score_data['score'] ?? 0),
            'total_questions' => count($score_data['correct_items'] ?? []) + count($score_data['incorrect_items'] ?? []),
            'correct_items' => $score_data['correct_items'] ?? [],
            'incorrect_items' => $score_data['incorrect_items'] ?? [],
            'item_results' => $score_data['item_results'] ?? [],
            'source' => 'external',
            'plugin' => $score_data['plugin'] ?? 'unknown',
        ];
        
        $this->flosc_create_bridge_data($user_id, 'external_' . $quiz_id, $scoring_results);
    }
    
    /**
     * Handle LearnDash quiz completion
     * 
     * @param array $quiz_data LearnDash quiz data
     * @param WP_User $user User object
     */
    public function handle_learndash_quiz($quiz_data, $user) {
        if (!$user || !isset($user->ID)) {
            return;
        }
        
        $score_data = [
            'score' => $quiz_data['percentage'] ?? 0,
            'correct_items' => [], // LearnDash structure varies
            'incorrect_items' => [],
            'plugin' => 'learndash',
        ];
        
        $quiz_id = $quiz_data['quiz'] ?? 'ld_quiz';
        
        do_action('flosc_external_quiz_score', $user->ID, $quiz_id, $score_data);
    }
    
    /**
     * Handle Tutor LMS quiz completion
     * 
     * @param int $attempt_id Attempt ID
     * @param int $course_id Course ID
     * @param int $user_id User ID
     */
    public function handle_tutor_quiz($attempt_id, $course_id, $user_id) {
        if (!$user_id) {
            return;
        }
        
        // Get attempt data from Tutor
        $attempt = function_exists('tutor_utils') ? tutor_utils()->get_attempt($attempt_id) : null;
        
        $score_data = [
            'score' => $attempt ? ($attempt->earned_marks / $attempt->total_marks * 100) : 0,
            'correct_items' => [],
            'incorrect_items' => [],
            'plugin' => 'tutor',
        ];
        
        do_action('flosc_external_quiz_score', $user_id, 'tutor_' . $course_id, $score_data);
    }
    
    /**
     * Build item results array from quiz data
     * 
     * @param array $quiz_result Raw quiz result
     * @return array Structured item results
     */
    private function build_item_results($quiz_result) {
        $item_results = [];
        
        $correct = $quiz_result['correct'] ?? [];
        $incorrect = $quiz_result['incorrect'] ?? [];
        
        foreach ($correct as $item) {
            $item_results[$item] = [
                'correct' => true,
                'category' => $this->get_item_category($item),
            ];
        }
        
        foreach ($incorrect as $item) {
            $item_results[$item] = [
                'correct' => false,
                'category' => $this->get_item_category($item),
            ];
        }
        
        return $item_results;
    }
    
    /**
     * Get category for an item (lesson number)
     * 
     * Maps lesson numbers to categories for weakness analysis.
     * Override this method or use filter for custom categorization.
     * 
     * @param mixed $item Item ID or number
     * @return string Category name
     */
    private function get_item_category($item) {
        // Allow filtering for custom categorization
        $category = apply_filters('flosc_item_category', null, $item);
        if ($category) {
            return $category;
        }
        
        // Default: group by lesson number ranges
        $num = intval($item);
        if ($num <= 3) return 'basics';
        if ($num <= 6) return 'intermediate';
        if ($num <= 10) return 'advanced';
        
        return 'general';
    }

    /**
     * Create bridge data after quiz completion
     * 
     * Stores quiz results in user meta and marks user as in bridge data state.
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Quiz ID
     * @param array $scoring_results Scoring data with keys:
     *   - score: (int) Total score
     *   - percentage: (float) Percentage correct
     *   - correct_items: (array) IDs of correct answers
     *   - incorrect_items: (array) IDs of incorrect answers
     *   - item_results: (array) Detailed per-item results
     * @return bool Success
     */
    public function flosc_create_bridge_data($user_id, $quiz_id, $scoring_results) {
        if (!$user_id) {
            return false;
        }
        
        // Sanitize quiz_id for use in meta key
        $safe_quiz_id = sanitize_key($quiz_id);
        
        // Store quiz results
        $bridge_data = [
            'date' => current_time('mysql'),
            'timestamp' => time(),
            'quiz_id' => $quiz_id,
            'score' => intval($scoring_results['score'] ?? 0),
            'percentage' => floatval($scoring_results['percentage'] ?? 0),
            'total_questions' => intval($scoring_results['total_questions'] ?? 0),
            'correct_items' => $scoring_results['correct_items'] ?? [],
            'incorrect_items' => $scoring_results['incorrect_items'] ?? [],
            'item_results' => $scoring_results['item_results'] ?? [],
            'user_answer' => $scoring_results['user_answer'] ?? '',
            'correct_answer' => $scoring_results['correct_answer'] ?? '',
            'source' => $scoring_results['source'] ?? 'flosc',
        ];
        
        // Store this quiz attempt
        update_user_meta($user_id, '_flosc_completed_quiz_' . $safe_quiz_id, $bridge_data);
        
        // Mark user as in bridge data state (took quiz, not yet purchased)
        $has_purchased = get_user_meta($user_id, '_flosc_has_purchased', true);
        if (!$has_purchased) {
            update_user_meta($user_id, '_flosc_bridge_data_state', true);
        }
        
        // Track quiz attempt
        $attempts = get_user_meta($user_id, '_flosc_quiz_attempts', true);
        if (!is_array($attempts)) {
            $attempts = [];
        }
        $attempts[] = [
            'quiz_id' => $quiz_id,
            'timestamp' => time(),
            'score' => $bridge_data['score'],
        ];
        update_user_meta($user_id, '_flosc_quiz_attempts', $attempts);
        
        // Calculate and store weakest category
        $weakest = $this->calculate_weakest_category($user_id);
        if ($weakest) {
            update_user_meta($user_id, '_flosc_weakest_category', $weakest);
        }
        
        // Fire action for other systems to hook into
        do_action('flosc_bridge_data_created', $user_id, $quiz_id, $bridge_data);
        
        error_log("FLOSC Bridge: Created bridge data for user {$user_id}, quiz {$quiz_id}, score {$bridge_data['score']}%");
        
        return true;
    }

    /**
     * Get bridge data for user and quiz
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Quiz ID (optional, returns latest if not specified)
     * @return array|false Bridge data or false if not found
     */
    public function get_flosc_bridge_data($user_id, $quiz_id = null) {
        if (!$user_id) {
            return false;
        }
        
        // If no quiz_id specified, get most recent
        if (!$quiz_id) {
            $attempts = get_user_meta($user_id, '_flosc_quiz_attempts', true);
            if (empty($attempts) || !is_array($attempts)) {
                return false;
            }
            // Get most recent attempt
            $latest = end($attempts);
            $quiz_id = $latest['quiz_id'] ?? 'default';
        }
        
        $safe_quiz_id = sanitize_key($quiz_id);
        $bridge_data = get_user_meta($user_id, '_flosc_completed_quiz_' . $safe_quiz_id, true);
        
        return !empty($bridge_data) ? $bridge_data : false;
    }

    /**
     * Check if user is in bridge data state
     * 
     * Bridge state = took quiz + has profile + hasn't purchased
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Quiz ID (optional)
     * @return bool
     */
    public function is_in_flosc_bridge_state($user_id, $quiz_id = null) {
        if (!$user_id) {
            return false;
        }
        
        // Check if user has purchased (exits bridge state)
        $has_purchased = get_user_meta($user_id, '_flosc_has_purchased', true);
        if ($has_purchased) {
            return false;
        }
        
        // Check if specific quiz completed
        if ($quiz_id) {
            $safe_quiz_id = sanitize_key($quiz_id);
            $quiz_data = get_user_meta($user_id, '_flosc_completed_quiz_' . $safe_quiz_id, true);
            return !empty($quiz_data);
        }
        
        // Check general bridge state
        return (bool) get_user_meta($user_id, '_flosc_bridge_data_state', true);
    }

    /**
     * Check if user has any quiz profile
     * 
     * @param int $user_id WordPress user ID
     * @return bool
     */
    public function flosc_has_profile($user_id) {
        if (!$user_id) {
            return false;
        }
        
        $attempts = get_user_meta($user_id, '_flosc_quiz_attempts', true);
        return !empty($attempts) && is_array($attempts) && count($attempts) > 0;
    }

    /**
     * Get correct answers for display
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Quiz ID
     * @return array Formatted correct items
     */
    public function get_flosc_correct_answers($user_id, $quiz_id = null) {
        $bridge_data = $this->get_flosc_bridge_data($user_id, $quiz_id);
        
        if (!$bridge_data || empty($bridge_data['correct_items'])) {
            return [];
        }
        
        $formatted = [];
        foreach ($bridge_data['correct_items'] as $item_id) {
            $formatted[] = [
                'item_id' => $item_id,
                'text' => $this->get_item_display_text($item_id, true),
                'category' => $this->get_item_category($item_id),
            ];
        }
        
        return $formatted;
    }

    /**
     * Get incorrect answers for display
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Quiz ID
     * @return array Formatted incorrect items
     */
    public function get_flosc_incorrect_answers($user_id, $quiz_id = null) {
        $bridge_data = $this->get_flosc_bridge_data($user_id, $quiz_id);
        
        if (!$bridge_data || empty($bridge_data['incorrect_items'])) {
            return [];
        }
        
        $formatted = [];
        foreach ($bridge_data['incorrect_items'] as $item_id) {
            $formatted[] = [
                'item_id' => $item_id,
                'text' => $this->get_item_display_text($item_id, false),
                'category' => $this->get_item_category($item_id),
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Get display text for an item
     * 
     * @param mixed $item_id Item ID
     * @param bool $correct Whether item was correct
     * @return string Display text
     */
    private function get_item_display_text($item_id, $correct) {
        // Try to get lesson title
        $posts = get_posts([
            'meta_key' => '_flosc_lesson_number',
            'meta_value' => $item_id,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (!empty($posts)) {
            $title = $posts[0]->post_title;
            return $correct 
                ? "✓ {$title}"
                : "✗ {$title}";
        }
        
        // Fallback
        return $correct 
            ? "✓ Lesson {$item_id}"
            : "✗ Lesson {$item_id}";
    }

    /**
     * Get weakest category for user
     * 
     * Analyzes all quiz results to find category with most incorrect answers.
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Specific quiz (optional)
     * @return string|null Category name or null
     */
    public function get_flosc_weakest_category($user_id, $quiz_id = null) {
        // Return cached value if available
        $cached = get_user_meta($user_id, '_flosc_weakest_category', true);
        if ($cached && !$quiz_id) {
            return $cached;
        }
        
        return $this->calculate_weakest_category($user_id, $quiz_id);
    }
    
    /**
     * Calculate weakest category from quiz results
     * 
     * @param int $user_id User ID
     * @param string $quiz_id Specific quiz (optional)
     * @return string|null Weakest category
     */
    private function calculate_weakest_category($user_id, $quiz_id = null) {
        $bridge_data = $this->get_flosc_bridge_data($user_id, $quiz_id);
        
        if (!$bridge_data || empty($bridge_data['item_results'])) {
            // Try from incorrect_items
            if (!empty($bridge_data['incorrect_items'])) {
                $category_counts = [];
                foreach ($bridge_data['incorrect_items'] as $item) {
                    $cat = $this->get_item_category($item);
                    $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
                }
                if (!empty($category_counts)) {
                    arsort($category_counts);
                    return array_key_first($category_counts);
                }
            }
            return null;
        }
        
        // Count incorrect by category
        $category_counts = [];
        foreach ($bridge_data['item_results'] as $item_id => $result) {
            if (!($result['correct'] ?? true)) {
                $cat = $result['category'] ?? $this->get_item_category($item_id);
                $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
            }
        }
        
        if (empty($category_counts)) {
            return null;
        }
        
        // Return category with most incorrect
        arsort($category_counts);
        return array_key_first($category_counts);
    }

    /**
     * Record when user views their bridge data
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Quiz ID
     * @return bool
     */
    public function flosc_record_bridge_viewed($user_id, $quiz_id = 'default') {
        if (!$user_id) {
            return false;
        }
        
        $safe_quiz_id = sanitize_key($quiz_id);
        update_user_meta($user_id, '_flosc_bridge_viewed_' . $safe_quiz_id, current_time('mysql'));
        
        do_action('flosc_bridge_data_viewed', $user_id, $quiz_id);
        
        return true;
    }

    /**
     * Get all quizzes user has completed
     * 
     * @param int $user_id WordPress user ID
     * @return array Quiz IDs with scores and dates
     */
    public function get_flosc_completed_quizzes($user_id) {
        if (!$user_id) {
            return [];
        }
        
        $attempts = get_user_meta($user_id, '_flosc_quiz_attempts', true);
        
        if (empty($attempts) || !is_array($attempts)) {
            return [];
        }
        
        return $attempts;
    }
    
    /**
     * Get summary statistics for user
     * 
     * @param int $user_id User ID
     * @return array Summary stats
     */
    public function get_flosc_user_summary($user_id) {
        if (!$user_id) {
            return [];
        }
        
        $attempts = $this->get_flosc_completed_quizzes($user_id);
        $latest = $this->get_flosc_bridge_data($user_id);
        
        $total_attempts = count($attempts);
        $best_score = 0;
        $latest_score = 0;
        
        foreach ($attempts as $attempt) {
            $score = $attempt['score'] ?? 0;
            if ($score > $best_score) {
                $best_score = $score;
            }
        }
        
        if ($latest) {
            $latest_score = $latest['score'] ?? 0;
        }
        
        return [
            'total_attempts' => $total_attempts,
            'best_score' => $best_score,
            'latest_score' => $latest_score,
            'in_bridge_state' => $this->is_in_flosc_bridge_state($user_id),
            'has_profile' => $this->flosc_has_profile($user_id),
            'weakest_category' => $this->get_flosc_weakest_category($user_id),
            'correct_count' => count($latest['correct_items'] ?? []),
            'incorrect_count' => count($latest['incorrect_items'] ?? []),
        ];
    }
    
    /**
     * Clear bridge data state (after purchase)
     * 
     * @param int $user_id User ID
     * @return bool
     */
    public function clear_bridge_state($user_id) {
        if (!$user_id) {
            return false;
        }
        
        delete_user_meta($user_id, '_flosc_bridge_data_state');
        update_user_meta($user_id, '_flosc_has_purchased', true);
        update_user_meta($user_id, '_flosc_purchase_date', current_time('mysql'));
        
        do_action('flosc_bridge_state_cleared', $user_id);
        
        return true;
    }
}

// Initialize singleton
add_action('plugins_loaded', function() {
    FLOSC_Bridge_Data_Manager::instance();
}, 5);
