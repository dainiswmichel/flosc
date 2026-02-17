<?php
/**
 * FLOSC Lesson Manager
 * 
 * Manages lessons stored as WordPress posts.
 * Lessons are regular posts in a configured category.
 * Quiz items map to lessons via post tags.
 *
 * @package FLOSC
 * @version 3.0.3
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Lesson_Manager {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * v1.8.2: Resolve lessons category from per-flow settings first, then global option.
     * The admin Lessons tab saves to flow_lessons_category → $flow_settings['lessons_category'].
     * The global flosc_lessons_category option may be empty if only per-flow was configured.
     */
    private function resolve_category() {
        // 1. Try per-flow settings (where the admin actually saves it)
        if (function_exists('flosc')) {
            $flow = flosc()->get_current_flow();
            if ($flow && !empty($flow['lessons_category'])) {
                return $flow['lessons_category'];
            }
        }
        
        // 2. Try global option (legacy / fallback)
        $global = get_option('flosc_lessons_category', '');
        if (!empty($global)) {
            return $global;
        }
        
        // 3. Last resort: scan all flow settings for any configured category
        $ivr_dir = defined('FLOSC_PLUGIN_DIR') ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/' : '';
        if ($ivr_dir && is_dir($ivr_dir)) {
            $files = array_merge(
                glob($ivr_dir . '*_ivr.md') ?: [],
                glob($ivr_dir . 'ivr*.md') ?: []
            );
            foreach (array_unique(array_map('basename', $files)) as $filename) {
                $key = 'flosc_flow_' . sanitize_key(pathinfo($filename, PATHINFO_FILENAME));
                $settings = get_option($key, []);
                if (!empty($settings['lessons_category'])) {
                    return $settings['lessons_category'];
                }
            }
        }
        
        return '';
    }
    
    /**
     * Get all lessons from configured category
     */
    public function get_all_lessons() {
        $category = $this->resolve_category();
        
        if (empty($category)) {
            error_log('[FLOSC Lessons] flosc_lessons_category option is empty. Set it in FLOSC > Settings.');
            return [];
        }
        
        error_log('[FLOSC Lessons] Looking for lessons in category: "' . $category . '" (type: ' . (is_numeric($category) ? 'ID' : 'slug') . ')');
        
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order date',
            'order' => 'ASC',
        ];
        
        // Support both slug and ID
        if (is_numeric($category)) {
            $args['cat'] = intval($category);
        } else {
            $args['category_name'] = sanitize_title($category);
        }
        
        $query = new WP_Query($args);
        $lessons = [];
        
        error_log('[FLOSC Lessons] WP_Query found ' . $query->found_posts . ' posts (query SQL: ' . $query->request . ')');
        
        foreach ($query->posts as $post) {
            $lessons[] = $this->format_lesson($post);
        }
        
        if (empty($lessons)) {
            // Diagnostic: check if the category exists at all
            if (is_numeric($category)) {
                $term = get_term(intval($category), 'category');
                error_log('[FLOSC Lessons] Category ID ' . $category . ' exists: ' . ($term && !is_wp_error($term) ? 'YES ("' . $term->name . '", ' . $term->count . ' posts)' : 'NO'));
            } else {
                $term = get_term_by('slug', sanitize_title($category), 'category');
                error_log('[FLOSC Lessons] Category slug "' . $category . '" exists: ' . ($term ? 'YES (ID: ' . $term->term_id . ', ' . $term->count . ' posts)' : 'NO'));
            }
        }
        
        return $lessons;
    }
    
    /**
     * Get a single lesson by ID
     */
    public function get_lesson($lesson_id) {
        $post = get_post($lesson_id);
        
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }
        
        return $this->format_lesson($post, true);
    }
    
    /**
     * Get lessons that match quiz results (missed items)
     * 
     * @param array $missed_items Items the user got wrong
     * @return array Matching lessons
     */
    public function get_lessons_for_missed_items($missed_items) {
        if (empty($missed_items)) {
            return [];
        }
        
        $category = $this->resolve_category();
        if (empty($category)) {
            return [];
        }
        
        // Build tag query from missed items
        // Tags should match the quiz item (e.g., "5" or "phoneme-ai")
        $tag_slugs = [];
        foreach ($missed_items as $item) {
            // Try numeric tag (e.g., "5")
            $tag_slugs[] = sanitize_title($item);
            // Try phoneme tag (e.g., "phoneme-5")
            $tag_slugs[] = 'phoneme-' . sanitize_title($item);
        }
        
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tag_slug__in' => $tag_slugs,
            'orderby' => 'menu_order date',
            'order' => 'ASC',
        ];
        
        // Filter by category
        if (is_numeric($category)) {
            $args['cat'] = intval($category);
        } else {
            $args['category_name'] = sanitize_title($category);
        }
        
        $query = new WP_Query($args);
        $lessons = [];
        
        foreach ($query->posts as $post) {
            $lesson = $this->format_lesson($post);
            
            // Find which missed item this lesson addresses
            $post_tags = wp_get_post_tags($post->ID, ['fields' => 'slugs']);
            foreach ($missed_items as $item) {
                $item_slug = sanitize_title($item);
                if (in_array($item_slug, $post_tags) || in_array('phoneme-' . $item_slug, $post_tags)) {
                    $lesson['addresses_item'] = $item;
                    break;
                }
            }
            
            $lessons[] = $lesson;
        }
        
        return $lessons;
    }
    
    /**
     * Get the first (free) lesson for a set of missed items
     */
    public function get_free_lesson($missed_items) {
        $lessons = $this->get_lessons_for_missed_items($missed_items);
        
        if (empty($lessons)) {
            // Fallback: get first lesson in category
            $all_lessons = $this->get_all_lessons();
            return !empty($all_lessons) ? $all_lessons[0] : null;
        }
        
        return $lessons[0];
    }
    
    /**
     * Format a post as a lesson array
     */
    private function format_lesson($post, $include_content = false) {
        $lesson = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'excerpt' => get_the_excerpt($post),
            'url' => get_permalink($post),
            'tags' => wp_get_post_tags($post->ID, ['fields' => 'slugs']),
            'thumbnail' => get_the_post_thumbnail_url($post, 'medium'),
        ];
        
        // Get custom fields for phoneme data
        $phoneme = get_post_meta($post->ID, '_flosc_phoneme', true);
        $phoneme_symbol = get_post_meta($post->ID, '_flosc_phoneme_symbol', true);
        
        if ($phoneme) {
            $lesson['phoneme'] = $phoneme;
        }
        if ($phoneme_symbol) {
            $lesson['phoneme_symbol'] = $phoneme_symbol;
        }
        
        if ($include_content) {
            $lesson['content'] = apply_filters('the_content', $post->post_content);
        }
        
        return $lesson;
    }
    
    /**
     * Check if user has access to a lesson
     */
    public function user_can_access($user_id, $lesson_id, $is_free_lesson = false) {
        // Free lesson is always accessible to logged-in users
        if ($is_free_lesson && $user_id) {
            return true;
        }
        
        // Check if user has paid access
        $access_manager = flosc()->sale()->access();
        $user_access = $access_manager->get_user_access($user_id);
        
        // Check for all_lessons feature or paid level
        if ($access_manager->has_feature($user_id, 'all_lessons')) {
            return true;
        }

        // v1.8.4: Use FLOSC_Member_Access which has actual level/member checking
        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
        $member_access = FLOSC_Member_Access::instance();
        if ($member_access->is_member($user_id)) {
            return true;
        }

        return false;
    }
    
    /**
     * Get lesson mapping for pronunciation analyzer
     * Returns array compatible with existing lesson_mapping format
     */
    public function get_lesson_mapping() {
        $lessons = $this->get_all_lessons();
        $mapping = [];
        
        foreach ($lessons as $lesson) {
            foreach ($lesson['tags'] as $tag) {
                // Check if tag is numeric or phoneme-X format
                $item = null;
                if (is_numeric($tag)) {
                    $item = $tag;
                } elseif (strpos($tag, 'phoneme-') === 0) {
                    $item = str_replace('phoneme-', '', $tag);
                }
                
                if ($item) {
                    $mapping[$item] = [
                        'lesson_id' => $lesson['id'],
                        'lesson' => $lesson['title'],
                        'phoneme' => $lesson['phoneme_symbol'] ?? $lesson['phoneme'] ?? '',
                    ];
                }
            }
        }
        
        return $mapping;
    }
}
