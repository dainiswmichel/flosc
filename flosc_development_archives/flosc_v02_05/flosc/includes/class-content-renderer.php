<?php
/**
 * =============================================================================
 * FLOSC CONTENT RENDERER
 * =============================================================================
 * 
 * Renders WordPress posts as in-chat content.
 * All content displays INSIDE the chat - no external links.
 * 
 * =============================================================================
 * DISPLAY MODES
 * =============================================================================
 * 
 * INLINE MODE:
 *   - Full content renders directly in chat messages
 *   - Good for short lessons
 *   - User sees everything without clicking
 * 
 * LIST MODE:
 *   - Shows title + excerpt as clickable cards
 *   - Clicking expands to show full content
 *   - Good for longer content or many lessons
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Content_Renderer {
    
    /**
     * @var FLOSC_Framework
     */
    private $flosc;
    
    
    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
    }
    
    
    /* =========================================================================
     * SINGLE POST RENDERING
     * =========================================================================
     */
    
    /**
     * Get single post formatted for chat display
     * 
     * @param int $post_id WordPress post ID
     * @return WP_REST_Response
     */
    public function get_single_post($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_REST_Response([
                'id'      => 0,
                'title'   => 'Lesson Not Found',
                'excerpt' => 'The requested lesson could not be found.',
                'content' => '<p>Please contact support if this problem persists.</p>',
                'type'    => 'error',
            ]);
        }
        
        return new WP_REST_Response([
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'excerpt'    => get_the_excerpt($post),
            'content'    => $this->format_content($post),
            'quiz_item'  => get_post_meta($post->ID, '_flosc_quiz_item', true),
            'is_free'    => get_post_meta($post->ID, '_flosc_is_free', true) === '1',
            'type'       => 'lesson',
        ]);
    }
    
    
    /* =========================================================================
     * LESSON LIST RENDERING
     * =========================================================================
     */
    
    /**
     * Get lessons for specific quiz items
     * 
     * @param array $items Quiz items to get lessons for
     * @return WP_REST_Response
     */
    public function get_lessons_for_items($items) {
        $mapper = new FLOSC_Lesson_Mapper($this->flosc);
        $posts = $mapper->get_lessons_for_items($items);
        
        return $this->format_lesson_list($posts);
    }
    
    
    /**
     * Get all lessons in content category
     * 
     * @return WP_REST_Response
     */
    public function get_content_list() {
        $mapper = new FLOSC_Lesson_Mapper($this->flosc);
        $posts = $mapper->get_all_lessons();
        
        return $this->format_lesson_list($posts);
    }
    
    
    /**
     * Format list of posts for response
     * 
     * @param array $posts Array of WP_Post objects
     * @return WP_REST_Response
     */
    private function format_lesson_list($posts) {
        if (empty($posts)) {
            return new WP_REST_Response([
                'items'   => [],
                'total'   => 0,
                'message' => 'No lessons found. Please add posts to the content category.',
                'type'    => 'empty',
            ]);
        }
        
        $display_mode = $this->flosc->get_config('content_display_mode');
        
        $items = array_map(function($post) use ($display_mode) {
            $item = [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'excerpt'   => get_the_excerpt($post),
                'quiz_item' => get_post_meta($post->ID, '_flosc_quiz_item', true),
                'order'     => (int)get_post_meta($post->ID, '_flosc_lesson_order', true),
                'type'      => 'lesson',
            ];
            
            // Include full content in inline mode
            if ($display_mode === 'inline') {
                $item['content'] = $this->format_content($post);
            }
            
            return $item;
        }, $posts);
        
        // Sort by order
        usort($items, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        return new WP_REST_Response([
            'items'        => $items,
            'total'        => count($items),
            'display_mode' => $display_mode,
            'type'         => 'list',
        ]);
    }
    
    
    /* =========================================================================
     * CONTENT FORMATTING
     * =========================================================================
     */
    
    /**
     * Format post content for chat display
     * 
     * @param WP_Post $post
     * @return string HTML content
     */
    private function format_content($post) {
        // Apply WordPress content filters (shortcodes, embeds, etc.)
        $content = apply_filters('the_content', $post->post_content);
        
        // Wrap in lesson container
        $html = '<div class="flosc-lesson">';
        $html .= '<h3 class="flosc-lesson-title">' . esc_html($post->post_title) . '</h3>';
        $html .= '<div class="flosc-lesson-body">' . $content . '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    
    /* =========================================================================
     * SINGLE LESSON DETAIL (for list mode expansion)
     * =========================================================================
     */
    
    /**
     * Get full lesson content by ID
     * 
     * @param int $lesson_id Post ID
     * @return WP_REST_Response
     */
    public function get_lesson_detail($lesson_id) {
        $post = get_post($lesson_id);
        
        if (!$post) {
            return new WP_Error('not_found', 'Lesson not found', ['status' => 404]);
        }
        
        return new WP_REST_Response([
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'content'   => $this->format_content($post),
            'quiz_item' => get_post_meta($post->ID, '_flosc_quiz_item', true),
            'type'      => 'lesson_detail',
        ]);
    }
}
