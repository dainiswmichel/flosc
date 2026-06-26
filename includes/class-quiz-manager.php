<?php
/**
 * FLOSC Quiz Integration Manager
 * 
 * Provides a unified interface for integrating external quiz plugins with FLOSC.
 * Handles quiz metadata, score submission, and routing to Bridge Data Manager.
 * 
 * EXTERNAL PLUGIN INTEGRATION:
 * 1. Direct API: FLOSC_Quiz_Manager::submit_score($user_id, $quiz_id, $score_data)
 * 2. Action hook: do_action('flosc_external_quiz_score', $user_id, $quiz_id, $score_data)
 * 3. REST API: POST /wp-json/flosc/v1/external-quiz
 * 
 * QUIZ METADATA (optional):
 * Register quiz metadata to enable richer personalization:
 * FLOSC_Quiz_Manager::register_quiz('my_quiz_id', [
 *     'title' => 'My Quiz',
 *     'category' => 'grammar',
 *     'lesson_mapping' => ['q1' => 1, 'q2' => 2], // question ID → lesson number
 * ]);
 * 
 * @package FLOSC
 * @since 1.0.3
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Quiz_Manager {
    
    /**
     * Singleton instance
     * @var FLOSC_Quiz_Manager
     */
    private static $instance = null;
    
    /**
     * Registered quiz metadata
     * @var array
     */
    private static $quiz_registry = [];
    
    /**
     * Get singleton instance
     * @return FLOSC_Quiz_Manager
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load registered quizzes from options
        self::$quiz_registry = get_option('flosc_quiz_registry', []);
        
        // Register REST endpoint for external submissions
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Register REST routes for external quiz submission
     */
    public function register_rest_routes() {
        register_rest_route('flosc/v1', '/external-quiz', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_external_quiz_rest'],
            'permission_callback' => [$this, 'check_external_quiz_permission'],
        ]);
    }

    /**
     * Permission callback for external quiz submissions.
     *
     * Allows either a valid configured API key or an authenticated user.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_external_quiz_permission($request) {
        $api_key = sanitize_text_field((string) $request->get_header('X-FLOSC-API-Key'));
        $stored_key = (string) get_option('flosc_external_api_key', '');

        if ($api_key !== '' && $stored_key !== '' && hash_equals($stored_key, $api_key)) {
            return true;
        }

        return is_user_logged_in();
    }
    
    /**
     * Handle REST API quiz submission
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_external_quiz_rest($request) {
        $user_id = absint($request->get_param('user_id') ?: get_current_user_id());
        $quiz_id = $request->get_param('quiz_id');
        $score_data = $request->get_param('score_data');

        $api_key = sanitize_text_field((string) $request->get_header('X-FLOSC-API-Key'));
        $stored_key = (string) get_option('flosc_external_api_key', '');
        $using_api_key = ($api_key !== '' && $stored_key !== '' && hash_equals($stored_key, $api_key));

        if (!$using_api_key && !current_user_can('manage_options') && $user_id !== get_current_user_id()) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Cannot submit quiz data for another user.',
            ], 403);
        }
        
        if (!$user_id || !$quiz_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Missing required parameters: user_id, quiz_id',
            ], 400);
        }
        
        $result = self::submit_score($user_id, $quiz_id, $score_data);
        
        return new WP_REST_Response([
            'success' => $result,
            'message' => $result ? 'Score submitted successfully' : 'Failed to submit score',
        ]);
    }
    
    /**
     * Register a quiz with metadata
     * 
     * Optional but recommended for richer personalization.
     * 
     * @param string $quiz_id Unique quiz identifier
     * @param array $metadata Quiz metadata:
     *   - title: (string) Display title
     *   - description: (string) Quiz description
     *   - category: (string) Category for weakness analysis
     *   - lesson_mapping: (array) Maps question IDs to lesson numbers
     *   - pass_score: (int) Score needed to pass (default: 70)
     *   - source: (string) Plugin name ('learndash', 'tutor', 'custom')
     * @return bool
     */
    public static function register_quiz($quiz_id, $metadata = []) {
        $quiz_id = sanitize_key($quiz_id);
        
        $defaults = [
            'title' => 'Quiz ' . $quiz_id,
            'description' => '',
            'category' => 'general',
            'lesson_mapping' => [],
            'pass_score' => 70,
            'source' => 'external',
            'registered_at' => current_time('mysql'),
        ];
        
        self::$quiz_registry[$quiz_id] = wp_parse_args($metadata, $defaults);
        
        // Persist to database
        update_option('flosc_quiz_registry', self::$quiz_registry);
        
        return true;
    }
    
    /**
     * Get quiz metadata
     * 
     * @param string $quiz_id
     * @return array|null
     */
    public static function get_quiz($quiz_id) {
        $quiz_id = sanitize_key($quiz_id);
        return self::$quiz_registry[$quiz_id] ?? null;
    }
    
    /**
     * Get all registered quizzes
     * 
     * @return array
     */
    public static function get_all_quizzes() {
        return self::$quiz_registry;
    }
    
    /**
     * Unregister a quiz
     * 
     * @param string $quiz_id
     * @return bool
     */
    public static function unregister_quiz($quiz_id) {
        $quiz_id = sanitize_key($quiz_id);
        
        if (isset(self::$quiz_registry[$quiz_id])) {
            unset(self::$quiz_registry[$quiz_id]);
            update_option('flosc_quiz_registry', self::$quiz_registry);
            return true;
        }
        
        return false;
    }
    
    /**
     * Submit a quiz score
     * 
     * Main API for external plugins to submit scores to FLOSC.
     * 
     * @param int $user_id WordPress user ID
     * @param string $quiz_id Quiz identifier (should be registered first)
     * @param array $score_data Score data:
     *   - score: (int) Percentage score 0-100 (required)
     *   - correct_items: (array) IDs/names of correct answers
     *   - incorrect_items: (array) IDs/names of incorrect answers
     *   - answers: (array) All user answers keyed by question ID
     *   - time_spent: (int) Seconds spent on quiz
     * @return bool Success
     */
    public static function submit_score($user_id, $quiz_id, $score_data) {
        if (!$user_id || !$quiz_id || !is_array($score_data)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC Quiz Manager: Invalid parameters - user_id: {$user_id}, quiz_id: {$quiz_id}");
            return false;
        }
        
        // Ensure score is set
        if (!isset($score_data['score'])) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC Quiz Manager: Missing score in score_data");
            return false;
        }
        
        $quiz_id = sanitize_key($quiz_id);
        
        // Get quiz metadata if registered
        $quiz_meta = self::get_quiz($quiz_id);
        
        // Map question IDs to lesson numbers if mapping exists
        if ($quiz_meta && !empty($quiz_meta['lesson_mapping'])) {
            $score_data = self::apply_lesson_mapping($score_data, $quiz_meta['lesson_mapping']);
        }
        
        // Add source info
        $score_data['plugin'] = $quiz_meta['source'] ?? 'external';
        $score_data['quiz_title'] = $quiz_meta['title'] ?? $quiz_id;
        
        // Fire the external quiz hook (Bridge Data Manager listens to this)
        do_action('flosc_external_quiz_score', $user_id, $quiz_id, $score_data);
        
        // Fire completion action
        do_action('flosc_quiz_manager_score_submitted', $user_id, $quiz_id, $score_data);
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC Quiz Manager: Submitted score for user {$user_id}, quiz {$quiz_id}, score {$score_data['score']}%");
        
        return true;
    }
    
    /**
     * Apply lesson mapping to score data
     * 
     * Converts question IDs to lesson numbers based on registered mapping.
     * 
     * @param array $score_data Original score data
     * @param array $mapping Question ID → Lesson number mapping
     * @return array Modified score data
     */
    private static function apply_lesson_mapping($score_data, $mapping) {
        // Map correct items
        if (!empty($score_data['correct_items'])) {
            $mapped_correct = [];
            foreach ($score_data['correct_items'] as $item) {
                $mapped_correct[] = $mapping[$item] ?? $item;
            }
            $score_data['correct_items'] = $mapped_correct;
        }
        
        // Map incorrect items
        if (!empty($score_data['incorrect_items'])) {
            $mapped_incorrect = [];
            foreach ($score_data['incorrect_items'] as $item) {
                $mapped_incorrect[] = $mapping[$item] ?? $item;
            }
            $score_data['incorrect_items'] = $mapped_incorrect;
        }
        
        return $score_data;
    }
    
    /**
     * Get user's quiz history
     * 
     * @param int $user_id
     * @param string $quiz_id Optional specific quiz
     * @return array
     */
    public static function get_user_quiz_history($user_id, $quiz_id = null) {
        $bridge_manager = FLOSC_Bridge_Data_Manager::instance();
        
        if ($quiz_id) {
            $data = $bridge_manager->get_flosc_bridge_data($user_id, $quiz_id);
            return $data ? [$data] : [];
        }
        
        return $bridge_manager->get_flosc_completed_quizzes($user_id);
    }
    
    /**
     * Check if user passed a quiz
     * 
     * @param int $user_id
     * @param string $quiz_id
     * @return bool|null True if passed, false if failed, null if not taken
     */
    public static function user_passed_quiz($user_id, $quiz_id) {
        $bridge_manager = FLOSC_Bridge_Data_Manager::instance();
        $data = $bridge_manager->get_flosc_bridge_data($user_id, $quiz_id);
        
        if (!$data) {
            return null;
        }
        
        $quiz_meta = self::get_quiz($quiz_id);
        $pass_score = $quiz_meta['pass_score'] ?? 70;
        
        return ($data['score'] ?? 0) >= $pass_score;
    }
    
    /**
     * Get user's best score for a quiz
     * 
     * @param int $user_id
     * @param string $quiz_id
     * @return int|null Best score or null if never taken
     */
    public static function get_best_score($user_id, $quiz_id = null) {
        $bridge_manager = FLOSC_Bridge_Data_Manager::instance();
        $summary = $bridge_manager->get_flosc_user_summary($user_id);
        
        return $summary['best_score'] ?? null;
    }
    
    /**
     * Shortcode to display quiz results
     * 
     * Usage: [flosc_quiz_results quiz_id="my_quiz"]
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function shortcode_quiz_results($atts) {
        $atts = shortcode_atts([
            'quiz_id' => null,
            'show_correct' => 'yes',
            'show_incorrect' => 'yes',
        ], $atts);
        
        if (!is_user_logged_in()) {
            return '<p>Please log in to see your quiz results.</p>';
        }
        
        $user_id = get_current_user_id();
        $bridge_manager = FLOSC_Bridge_Data_Manager::instance();
        $data = $bridge_manager->get_flosc_bridge_data($user_id, $atts['quiz_id']);
        
        if (!$data) {
            return '<p>No quiz results found.</p>';
        }
        
        $output = '<div class="flosc-quiz-results">';
        $output .= '<h3>Quiz Results</h3>';
        $output .= '<p><strong>Score:</strong> ' . esc_html($data['score']) . '%</p>';
        $output .= '<p><strong>Date:</strong> ' . esc_html($data['date']) . '</p>';
        
        if ($atts['show_correct'] === 'yes' && !empty($data['correct_items'])) {
            $output .= '<h4>✓ Correct</h4><ul>';
            foreach ($data['correct_items'] as $item) {
                $output .= '<li>' . esc_html($item) . '</li>';
            }
            $output .= '</ul>';
        }
        
        if ($atts['show_incorrect'] === 'yes' && !empty($data['incorrect_items'])) {
            $output .= '<h4>✗ Needs Practice</h4><ul>';
            foreach ($data['incorrect_items'] as $item) {
                $output .= '<li>' . esc_html($item) . '</li>';
            }
            $output .= '</ul>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
}

// Initialize
add_action('plugins_loaded', function() {
    FLOSC_Quiz_Manager::instance();
}, 10);

// Register shortcode
add_shortcode('flosc_quiz_results', ['FLOSC_Quiz_Manager', 'shortcode_quiz_results']);
