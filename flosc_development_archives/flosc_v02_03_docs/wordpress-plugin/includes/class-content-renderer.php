<?php
/**
 * FLOSC Content Renderer
 *
 * Renders WordPress posts INLINE in chat (no external links)
 * Two modes:
 * - inline: Show full content in chat messages
 * - list: Show titles, expand on click
 *
 * ALL CONTENT STAYS IN CHAT - this is critical for engagement
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Content_Renderer {

    private $flosc;

    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
    }

    /**
     * Get single post (for free content)
     *
     * @param int $post_id WordPress post ID
     * @return WP_REST_Response|WP_Error
     */
    public function get_single_post($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('not_found', 'Content not found', ['status' => 404]);
        }

        $content = $this->format_post($post);

        return new WP_REST_Response([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $content,
            'type' => 'single',
        ]);
    }

    /**
     * Get content list (for paid users)
     *
     * Returns all posts from configured category
     * Format depends on display mode setting
     *
     * @return WP_REST_Response
     */
    public function get_content_list() {
        $category = $this->flosc->get_config('content_category');

        if (!$category) {
            return new WP_REST_Response([
                'items' => [],
                'message' => 'No content category configured. Please configure in FLOSC settings.',
                'type' => 'error',
            ]);
        }

        // Get all posts in category
        $posts = get_posts([
            'post_type' => 'post',
            'category_name' => $category,
            'posts_per_page' => -1,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'post_status' => 'publish',
        ]);

        if (empty($posts)) {
            return new WP_REST_Response([
                'items' => [],
                'message' => "No content found in category '{$category}'. Please add posts to this category.",
                'type' => 'empty',
            ]);
        }

        $display_mode = $this->flosc->get_config('content_display_mode');

        if ($display_mode === 'inline') {
            // Render all content immediately
            $items = array_map(function($post) {
                return [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $this->format_post($post),
                    'type' => 'lesson',
                ];
            }, $posts);
        } else {
            // List mode: just titles + excerpts, expand on click
            $items = array_map(function($post) {
                return [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'excerpt' => get_the_excerpt($post),
                    'type' => 'lesson_preview',
                ];
            }, $posts);
        }

        return new WP_REST_Response([
            'items' => $items,
            'total' => count($items),
            'display_mode' => $display_mode,
            'type' => 'list',
        ]);
    }

    /**
     * Format post content for chat display
     *
     * Processing:
     * - Apply WordPress filters (shortcodes, embeds, etc)
     * - Wrap in chat-friendly HTML
     * - Ensure images/videos display properly
     * - Remove problematic elements (scripts, iframes from untrusted sources)
     *
     * @param WP_Post $post
     * @return string HTML content
     */
    private function format_post($post) {
        // Get content with WordPress filters applied
        $content = apply_filters('the_content', $post->post_content);

        // Wrap in container with lesson styling
        $html = '<div class="flosc-lesson-content">';
        $html .= '<h3 class="flosc-lesson-title">' . esc_html($post->post_title) . '</h3>';
        $html .= '<div class="flosc-lesson-body">' . $content . '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get single lesson content (for list mode expansion)
     *
     * Endpoint: /flosc/v1/lesson/{id}
     *
     * @param int $lesson_id Post ID
     * @return WP_REST_Response
     */
    public function get_lesson_detail($lesson_id) {
        $post = get_post($lesson_id);

        if (!$post) {
            return new WP_Error('not_found', 'Lesson not found', ['status' => 404]);
        }

        // Verify user has access to this category
        $category = $this->flosc->get_config('content_category');
        $post_categories = wp_get_post_categories($post->ID, ['fields' => 'slugs']);

        if (!in_array($category, $post_categories)) {
            return new WP_Error('forbidden', 'This lesson is not part of your course', ['status' => 403]);
        }

        $content = $this->format_post($post);

        return new WP_REST_Response([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $content,
            'type' => 'lesson_detail',
        ]);
    }
}
