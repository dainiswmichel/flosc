<?php
/**
 * =============================================================================
 * FLOSC LESSON MAPPER
 * =============================================================================
 * 
 * Maps quiz items to WordPress lesson posts.
 * 
 * Each quiz item (e.g., "4" or "/æ/") maps to exactly one lesson post.
 * This mapping is stored as post meta: _flosc_quiz_item
 * 
 * =============================================================================
 * HOW IT WORKS
 * =============================================================================
 * 
 * 1. User takes quiz → misses items [4, 5, 6, 7, 8, 9, 10]
 * 2. Lesson Mapper finds posts where _flosc_quiz_item matches missed items
 * 3. For free content: returns ONE lesson from missed set
 * 4. For paid content: returns ALL lessons from missed set
 * 
 * =============================================================================
 * POST META FIELDS
 * =============================================================================
 * 
 * _flosc_quiz_item     - Which quiz item this lesson teaches (e.g., "4" or "/æ/")
 * _flosc_is_free       - Whether this lesson can be given for free ("1" = yes)
 * _flosc_lesson_order  - Display order (1, 2, 3...)
 * 
 * =============================================================================
 * SAE PHONEME MAPPING (Future)
 * =============================================================================
 * 
 * For LeSAEp, quiz items will be phonemes:
 * 
 *   Quiz Item     Lesson Post
 *   ---------     -----------
 *   /æ/      →    "The 'a' in 'cat'" (post ID 101)
 *   /ɛ/      →    "The 'e' in 'bed'" (post ID 102)
 *   /ɪ/      →    "The 'i' in 'sit'" (post ID 103)
 *   ...
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Lesson_Mapper {
    
    /**
     * Reference to main FLOSC framework
     * @var FLOSC_Framework
     */
    private $flosc;
    
    
    /**
     * Constructor
     * 
     * @param FLOSC_Framework $flosc_framework Main plugin instance
     */
    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
    }
    
    
    /* =========================================================================
     * FREE LESSON SELECTION
     * =========================================================================
     * 
     * Returns ONE lesson from the user's missed items.
     * The selection method is configurable:
     *   - first_missed: First item in the missed list
     *   - random_missed: Random item from missed list
     */
    
    /**
     * Get a free lesson matching one of the missed items
     * 
     * @param array $missed_items Items the user needs to learn
     * @return WP_Post|null Lesson post or null if none found
     */
    public function get_free_lesson_for_items($missed_items) {
        if (empty($missed_items)) {
            // User got everything correct - give them any free lesson
            return $this->get_any_free_lesson();
        }
        
        $selection_method = $this->flosc->get_config('free_lesson_selection');
        $category = $this->flosc->get_config('content_category');
        
        // Determine which item to look for
        if ($selection_method === 'random_missed') {
            $target_item = $missed_items[array_rand($missed_items)];
        } else {
            // Default: first_missed
            $target_item = $missed_items[0];
        }
        
        error_log("FLOSC Lesson Mapper: Looking for lesson for item '{$target_item}'");
        
        // Query for lesson matching this item
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_flosc_quiz_item',
                    'value' => $target_item,
                ],
            ],
        ];
        
        // Add category filter if configured
        if ($category) {
            $args['category_name'] = $category;
        }
        
        $posts = get_posts($args);
        
        if (!empty($posts)) {
            error_log("FLOSC Lesson Mapper: Found lesson '{$posts[0]->post_title}' for item '{$target_item}'");
            return $posts[0];
        }
        
        // Fallback: try to find any lesson marked as free
        error_log("FLOSC Lesson Mapper: No lesson found for '{$target_item}', trying fallback");
        return $this->get_any_free_lesson();
    }
    
    
    /**
     * Get any lesson marked as free (fallback)
     * 
     * @return WP_Post|null
     */
    private function get_any_free_lesson() {
        $category = $this->flosc->get_config('content_category');
        
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_flosc_is_free',
                    'value' => '1',
                ],
            ],
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_flosc_lesson_order',
            'order'          => 'ASC',
        ];
        
        if ($category) {
            $args['category_name'] = $category;
        }
        
        $posts = get_posts($args);
        
        if (!empty($posts)) {
            return $posts[0];
        }
        
        // Ultimate fallback: any lesson
        unset($args['meta_query']);
        $posts = get_posts($args);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    
    /* =========================================================================
     * PAID CONTENT SELECTION
     * =========================================================================
     * 
     * Returns ALL lessons matching the user's missed items.
     * Used after purchase to show personalized curriculum.
     */
    
    /**
     * Get all lessons for the given items
     * 
     * @param array $items Quiz items to get lessons for
     * @return array Array of WP_Post objects
     */
    public function get_lessons_for_items($items) {
        if (empty($items)) {
            // Return all lessons if no specific items
            return $this->get_all_lessons();
        }
        
        $category = $this->flosc->get_config('content_category');
        
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_flosc_quiz_item',
                    'value'   => $items,
                    'compare' => 'IN',
                ],
            ],
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_flosc_lesson_order',
            'order'          => 'ASC',
        ];
        
        if ($category) {
            $args['category_name'] = $category;
        }
        
        $posts = get_posts($args);
        
        error_log("FLOSC Lesson Mapper: Found " . count($posts) . " lessons for items: " . implode(', ', $items));
        
        return $posts;
    }
    
    
    /**
     * Get all lessons in the content category
     * 
     * @return array Array of WP_Post objects
     */
    public function get_all_lessons() {
        $category = $this->flosc->get_config('content_category');
        
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_flosc_lesson_order',
            'order'          => 'ASC',
        ];
        
        if ($category) {
            $args['category_name'] = $category;
        }
        
        return get_posts($args);
    }
    
    
    /* =========================================================================
     * ITEM TO LESSON MAPPING
     * =========================================================================
     */
    
    /**
     * Get the lesson for a specific quiz item
     * 
     * @param string $item Quiz item (e.g., "4" or "/æ/")
     * @return WP_Post|null
     */
    public function get_lesson_for_item($item) {
        $category = $this->flosc->get_config('content_category');
        
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_flosc_quiz_item',
                    'value' => $item,
                ],
            ],
        ];
        
        if ($category) {
            $args['category_name'] = $category;
        }
        
        $posts = get_posts($args);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    
    /**
     * Get the quiz item for a lesson
     * 
     * @param int $post_id Post ID
     * @return string|null Quiz item or null
     */
    public function get_item_for_lesson($post_id) {
        return get_post_meta($post_id, '_flosc_quiz_item', true) ?: null;
    }
    
    
    /**
     * Check if a lesson is marked as free
     * 
     * @param int $post_id Post ID
     * @return bool
     */
    public function is_lesson_free($post_id) {
        return get_post_meta($post_id, '_flosc_is_free', true) === '1';
    }
    
    
    /* =========================================================================
     * MAPPING TABLE (for admin display)
     * =========================================================================
     */
    
    /**
     * Get complete mapping of items to lessons
     * 
     * Returns array like:
     * [
     *     '1' => ['post_id' => 101, 'title' => 'Lesson 1', 'is_free' => true],
     *     '2' => ['post_id' => 102, 'title' => 'Lesson 2', 'is_free' => false],
     *     ...
     * ]
     * 
     * @return array
     */
    public function get_mapping_table() {
        $quiz_items = $this->get_quiz_items();
        $mapping = [];
        
        foreach ($quiz_items as $item) {
            $lesson = $this->get_lesson_for_item($item);
            
            $mapping[$item] = [
                'item'    => $item,
                'post_id' => $lesson ? $lesson->ID : null,
                'title'   => $lesson ? $lesson->post_title : '(Not configured)',
                'is_free' => $lesson ? $this->is_lesson_free($lesson->ID) : false,
            ];
        }
        
        return $mapping;
    }
    
    
    /**
     * Get quiz items from configuration
     */
    private function get_quiz_items() {
        $raw = $this->flosc->get_config('quiz_items');
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $items = array_values(array_filter(array_map('trim', $lines)));
        
        if (empty($items)) {
            $items = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
        }
        
        return $items;
    }
}
