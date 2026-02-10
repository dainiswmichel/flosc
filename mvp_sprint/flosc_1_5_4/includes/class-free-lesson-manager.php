<?php
/**
 * FLOSC Free Lesson Manager
 * Handles quiz results and free lesson selection
 * 
 * STATUS: ✅ FULLY FUNCTIONAL
 * - Hooks into flosc_quiz_completed action (v9.1.9)
 * - Calculates missed lessons from quiz results
 * - Selects ONE random lesson to give free
 * - Stores in user meta: _flosc_free_lesson_number
 * - Delivers via get_free_lesson() and deliver_free_lesson()
 * 
 * FLOW:
 * 1. User takes quiz: "4,7,9" = 30%
 * 2. System calculates missed: 1,2,3,5,6,8,10
 * 3. Picks random (e.g., #8)
 * 4. Stores _flosc_free_lesson_number = 8
 * 5. Delivers WordPress post with _flosc_lesson_number = 8
 * 
 * @since 9.1.8
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Free_Lesson_Manager {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into quiz completion
        add_action('flosc_quiz_completed', [$this, 'handle_quiz_completion'], 10, 2);
    }
    
    /**
     * Handle quiz completion and select free lesson
     * 
     * @param array $quiz_result Quiz results with score and answers
     * @param int $user_id User ID
     */
    public function handle_quiz_completion($quiz_result, $user_id) {

        $score = $quiz_result['score'] ?? 0;

        // Only offer free lesson if quiz incomplete (<100%)
        if ($score >= 100) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: User {$user_id} scored {$score}% - no free lesson needed");
            return;
        }

        // Get missed lessons
        $missed = $this->get_missed_lessons($quiz_result);

        if (empty($missed)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: No missed lessons found for user {$user_id}");
            return;
        }

        // v1.5.4: Use admin-configured count instead of hardcoded 1
        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
        $member_access = FLOSC_Member_Access::instance();
        $count = $member_access->calculate_free_lesson_count(count($missed));

        // Shuffle missed lessons and pick N
        shuffle($missed);
        $selected_lessons = array_slice($missed, 0, $count);

        // Store selected free lessons in user meta (array for multiple, single for backward compat)
        update_user_meta($user_id, '_flosc_free_lesson_number', $selected_lessons[0]);
        update_user_meta($user_id, '_flosc_free_lesson_numbers', $selected_lessons);
        update_user_meta($user_id, '_flosc_free_lesson_offered', time());

        // Find and grant guest access to each selected lesson
        $granted_posts = [];
        foreach ($selected_lessons as $lesson_num) {
            $post = $this->find_lesson_post($lesson_num);
            if ($post) {
                $member_access->grant_guest_access($user_id, $post->ID);
                $granted_posts[] = $post->ID;
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: Granted guest access to post {$post->ID} (lesson #{$lesson_num}) for user {$user_id}");
            }
        }

        // Store granted post IDs for retrieval
        update_user_meta($user_id, '_flosc_free_lesson_post_ids', $granted_posts);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: User {$user_id} offered " . count($selected_lessons) . " free lesson(s) (scored {$score}%)");

        return $selected_lessons;
    }
    
    /**
     * Get missed lesson numbers from quiz result
     * 
     * @param array $quiz_result
     * @return array Array of missed lesson numbers
     */
    private function get_missed_lessons($quiz_result) {
        
        $missed = [];
        
        // Expected format: user answered "4,7,9" when correct answer is "1,2,3,4,5,6,7,8,9,10"
        $user_answer = $quiz_result['user_answer'] ?? '';
        $correct_answer = $quiz_result['correct_answer'] ?? '1,2,3,4,5,6,7,8,9,10';
        
        // Parse answers
        $user_numbers = array_map('trim', explode(',', $user_answer));
        $user_numbers = array_filter($user_numbers, 'is_numeric');
        
        $correct_numbers = array_map('trim', explode(',', $correct_answer));
        $correct_numbers = array_filter($correct_numbers, 'is_numeric');
        
        // Find missed numbers
        foreach ($correct_numbers as $num) {
            if (!in_array($num, $user_numbers)) {
                $missed[] = intval($num);
            }
        }
        
        return $missed;
    }
    
    /**
     * Find a lesson post by lesson number
     * v1.4.4: Use configured category, fallback to common slug patterns
     * 
     * @param int $lesson_num
     * @return WP_Post|null
     */
    private function find_lesson_post($lesson_num) {
        // v1.4.4: Try configured category first
        $configured_cat = get_option('flosc_lessons_category', '');
        
        // Build list of category slugs to try
        $slugs_to_try = array_filter([
            $configured_cat,
            'flosc-sample-data',
            'flosc_sample_data',
            'flosc-lessons',
        ]);
        
        foreach ($slugs_to_try as $slug) {
            $posts = get_posts([
                'category_name' => $slug,
                'meta_key' => '_flosc_lesson_number',
                'meta_value' => $lesson_num,
                'posts_per_page' => 1,
                'post_status' => 'publish'
            ]);
            
            if (!empty($posts)) {
                return $posts[0];
            }
        }
        
        // v1.4.4: Last resort - search all posts with lesson number meta
        $posts = get_posts([
            'meta_key' => '_flosc_lesson_number',
            'meta_value' => $lesson_num,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Get free lesson content for user (single — backward compatible)
     *
     * @param int $user_id
     * @return array|false Post data or false
     */
    public function get_free_lesson($user_id) {
        $lessons = $this->get_free_lessons($user_id);
        return !empty($lessons) ? $lessons[0] : false;
    }

    /**
     * v1.5.4: Get all free lessons for user
     *
     * @param int $user_id
     * @return array Array of lesson data arrays
     */
    public function get_free_lessons($user_id) {
        $lesson_nums = get_user_meta($user_id, '_flosc_free_lesson_numbers', true);

        // Backward compat: fall back to single lesson number
        if (empty($lesson_nums)) {
            $single = get_user_meta($user_id, '_flosc_free_lesson_number', true);
            $lesson_nums = $single ? [$single] : [];
        }

        if (empty($lesson_nums)) {
            return [];
        }

        $lessons = [];
        foreach ($lesson_nums as $lesson_num) {
            $post = $this->find_lesson_post($lesson_num);
            if ($post) {
                $lessons[] = [
                    'post_id' => $post->ID,
                    'lesson_number' => $lesson_num,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'url' => get_permalink($post->ID),
                    'excerpt' => wp_trim_words($post->post_content, 55),
                ];
            }
        }

        return $lessons;
    }
    
    /**
     * Check if user has already received free lesson
     * 
     * @param int $user_id
     * @return bool
     */
    public function has_received_free_lesson($user_id) {
        $offered = get_user_meta($user_id, '_flosc_free_lesson_offered', true);
        return !empty($offered);
    }
    
    /**
     * Deliver free lesson(s) via chat or redirect
     * v1.5.4: Supports multiple lessons
     *
     * @param int $user_id
     * @param string $delivery_mode 'chat' or 'redirect'
     * @return array Response data
     */
    public function deliver_free_lesson($user_id, $delivery_mode = 'chat') {

        $lessons = $this->get_free_lessons($user_id);

        if (empty($lessons)) {
            return [
                'success' => false,
                'message' => 'No free lesson available.',
            ];
        }

        // Mark as delivered
        update_user_meta($user_id, '_flosc_free_lesson_delivered', time());

        $count = count($lessons);

        if ($delivery_mode === 'redirect') {
            return [
                'success' => true,
                'mode' => 'redirect',
                'url' => $lessons[0]['url'],
                'message' => "Redirecting to your free lesson: {$lessons[0]['title']}",
            ];
        }

        // Chat delivery - return all lessons
        return [
            'success' => true,
            'mode' => 'chat',
            'lessons' => $lessons,
            'count' => $count,
            // Backward compat: single lesson fields
            'lesson_number' => $lessons[0]['lesson_number'],
            'title' => $lessons[0]['title'],
            'content' => $lessons[0]['content'],
            'url' => $lessons[0]['url'],
            'message' => $count === 1
                ? "Here's your free lesson on {$lessons[0]['title']}!"
                : "Here are your {$count} free lessons!",
        ];
    }
}
