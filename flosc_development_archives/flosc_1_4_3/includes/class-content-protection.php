<?php
/**
 * FLOSC Content Protection
 * Handles category-level protection and post-level visibility tiers
 * 
 * CATEGORY PROTECTION (term meta):
 * - _flosc_protected: 'yes' = all posts in category are protected
 * - _flosc_required_level: 'samplecourse' = membership level required
 * 
 * POST VISIBILITY (post meta):
 * - _flosc_post_visibility: 'hidden' | 'teaser' | 'preview' | 'public'
 *   - hidden: members only (no content shown to non-members)
 *   - teaser: title + excerpt shown, rest redirects to chatbot
 *   - preview: content up to <!--flosc_read_more--> shown, rest redirects to chatbot
 *   - public: full content shown (admin encouraged to add CTA)
 * - _flosc_public_post: 'yes' = public post with chat CTA
 * 
 * RESOLUTION ORDER:
 * 1. Post meta _flosc_post_visibility (if set, use it)
 * 2. Category is protected → default to 'hidden'
 * 3. No protection → 'public'
 * 
 * @since 1.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Content_Protection {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into the_content with high priority (runs after other filters)
        add_filter('the_content', [$this, 'filter_by_visibility'], 20);
        
        // Hook into the_excerpt for teaser tier
        add_filter('get_the_excerpt', [$this, 'filter_excerpt'], 20, 2);
    }
    
    /**
     * Check if a category is FLOSC protected
     * 
     * @param int $category_id
     * @return bool
     */
    public function is_category_protected($category_id) {
        $protected = get_term_meta($category_id, '_flosc_protected', true);
        return $protected === 'yes' || $protected === 'true' || $protected === true || $protected === '1';
    }
    
    /**
     * Get required membership level for a category
     * 
     * @param int $category_id
     * @return string|null Level name or null if not set
     */
    public function get_category_required_level($category_id) {
        return get_term_meta($category_id, '_flosc_required_level', true) ?: null;
    }
    
    /**
     * Check if a post is in a protected category
     * 
     * @param int $post_id
     * @return array ['protected' => bool, 'required_level' => string|null, 'category_id' => int|null]
     */
    public function check_post_protection($post_id) {
        $categories = wp_get_post_categories($post_id);
        
        foreach ($categories as $cat_id) {
            if ($this->is_category_protected($cat_id)) {
                return [
                    'protected' => true,
                    'required_level' => $this->get_category_required_level($cat_id),
                    'category_id' => $cat_id
                ];
            }
        }
        
        return [
            'protected' => false,
            'required_level' => null,
            'category_id' => null
        ];
    }
    
    /**
     * Get visibility tier for a post
     * 
     * @param int $post_id
     * @return string 'hidden' | 'teaser' | 'preview' | 'public'
     */
    public function get_post_visibility($post_id) {
        // First check post meta override
        $visibility = get_post_meta($post_id, '_flosc_post_visibility', true);
        
        if ($visibility && in_array($visibility, ['hidden', 'teaser', 'preview', 'public'])) {
            return $visibility;
        }
        
        // Check if in protected category
        $protection = $this->check_post_protection($post_id);
        
        if ($protection['protected']) {
            // Protected category, default to hidden
            return 'hidden';
        }
        
        // Not protected, public by default
        return 'public';
    }
    
    /**
     * Check if current user can access a post
     * 
     * @param int $post_id
     * @return bool
     */
    public function user_can_access($post_id) {
        // v1.4.3: Public posts are always accessible
        if (get_post_meta($post_id, '_flosc_public_post', true) === 'yes') {
            return true;
        }
        
        // v1.4.3: Check explicit public visibility
        $visibility = get_post_meta($post_id, '_flosc_post_visibility', true);
        if ($visibility === 'public') {
            return true;
        }
        
        $protection = $this->check_post_protection($post_id);
        
        // Not protected = can access
        if (!$protection['protected']) {
            return true;
        }
        
        // Not logged in = cannot access protected content
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
        $member_access = FLOSC_Member_Access::instance();
        
        // Check if user has guest access to this specific post (free lesson)
        if ($member_access->has_guest_access($user_id, $post_id)) {
            return true;
        }
        
        // Check if user has required level
        if ($protection['required_level']) {
            return $member_access->has_level($user_id, $protection['required_level']);
        }
        
        // Protected but no specific level required - check general member status
        return $member_access->is_member($user_id);
    }
    
    /**
     * Main content filter - applies visibility tier
     * 
     * @param string $content
     * @return string Filtered content
     */
    public function filter_by_visibility($content) {
        // Skip admin dashboard pages
        if (is_admin()) {
            return $content;
        }

        // Admin users on the frontend see everything unfiltered
        if (current_user_can('manage_options')) {
            return $content;
        }

        // Skip non-singular pages
        if (!is_singular('post')) {
            return $content;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }
        
        // Check if post is protected
        $protection = $this->check_post_protection($post_id);
        
        if (!$protection['protected']) {
            // Not protected, return full content
            return $content;
        }
        
        // Check user access
        if ($this->user_can_access($post_id)) {
            // Member with access - return full content
            return $content;
        }
        
        // User doesn't have access - apply visibility tier
        $visibility = $this->get_post_visibility($post_id);
        
        return $this->apply_visibility_tier($content, $visibility, $post_id);
    }
    
    /**
     * Apply visibility tier to content
     * 
     * @param string $content Full content
     * @param string $visibility 'hidden' | 'teaser' | 'preview' | 'public'
     * @param int $post_id
     * @return string Filtered content
     */
    public function apply_visibility_tier($content, $visibility, $post_id) {
        switch ($visibility) {
            case 'hidden':
                return $this->get_hidden_message($post_id);
                
            case 'teaser':
                return $this->get_teaser_content($post_id);
                
            case 'preview':
                return $this->get_preview_content($content, $post_id);
                
            case 'public':
            default:
                // v1.4.3: Add free sample CTAs if this is a free sample post
                if (get_post_meta($post_id, '_flosc_public_post', true) === 'yes') {
                    $content = $this->flosc_add_public_post_ctas($content, $post_id);
                }
                return $content;
        }
    }
    
    /**
     * v1.4.3: Add CTAs to public posts
     * Guides visitors to the chat for engagement
     * 
     * @param string $content
     * @param int $post_id
     * @return string
     */
    private function flosc_add_public_post_ctas($content, $post_id) {
        $app_slug = get_option('flosc_app_slug', 'app');
        $post = get_post($post_id);
        
        $chat_url = add_query_arg([
            'from' => 'public-post',
            'post_id' => $post_id,
            'slug' => $post ? $post->post_name : '',
        ], home_url('/' . $app_slug . '/'));
        
        $cta_box = '<div class="flosc-public-post-cta" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 24px; border-radius: 12px; margin: 24px 0; text-align: center;">';
        $cta_box .= '<p style="font-size: 18px; margin: 0 0 12px; font-weight: 500;">🎉 Enjoying this free lesson?</p>';
        $cta_box .= '<p style="margin: 0 0 16px; opacity: 0.9;">Want personalized help or access to all lessons?</p>';
        $cta_box .= '<a href="' . esc_url($chat_url) . '" style="display: inline-block; background: #fff; color: #667eea; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">💬 Chat with us</a>';
        $cta_box .= '</div>';
        
        // Add CTA at the end of content
        $content .= $cta_box;
        
        return apply_filters('flosc_public_post_content', $content, $post_id);
    }
    
    /**
     * Get message for hidden content
     * v1.4.3: Added post_id tracking
     * 
     * @param int $post_id
     * @return string
     */
    private function get_hidden_message($post_id) {
        $message = '<div class="flosc-protected-content flosc-hidden">';
        $message .= '<p class="flosc-protected-notice">🔒 This content is for members only.</p>';
        $message .= $this->get_chatbot_cta($post_id);
        $message .= '</div>';
        
        return apply_filters('flosc_hidden_content_message', $message, $post_id);
    }
    
    /**
     * Get teaser content (title + excerpt only)
     * v1.4.3: Added post_id tracking to CTA
     * 
     * @param int $post_id
     * @return string
     */
    private function get_teaser_content($post_id) {
        $post = get_post($post_id);
        $excerpt = $post->post_excerpt;
        
        if (empty($excerpt)) {
            // Generate excerpt from content
            $excerpt = wp_trim_words(strip_tags($post->post_content), 55, '...');
        }
        
        $output = '<div class="flosc-protected-content flosc-teaser">';
        $output .= '<div class="flosc-teaser-excerpt">' . wpautop($excerpt) . '</div>';
        $output .= '<div class="flosc-protected-notice">';
        $output .= '<p>🔒 Want to read more? Unlock full access!</p>';
        $output .= '</div>';
        $output .= $this->get_chatbot_cta($post_id);
        $output .= '</div>';
        
        return apply_filters('flosc_teaser_content', $output, $post_id);
    }
    
    /**
     * Get preview content (up to <!--flosc_read_more-->)
     * v1.4.3: Added post_id tracking to CTA
     * 
     * @param string $content
     * @param int $post_id
     * @return string
     */
    private function get_preview_content($content, $post_id) {
        // Split by <!--flosc_read_more--> tag (FLOSC's custom tag)
        $parts = preg_split('/<!--flosc_read_more(.*?)?-->/', $content, 2);
        
        // If no FLOSC tag, try WordPress <!--more--> as fallback
        if (count($parts) <= 1) {
            $parts = preg_split('/<!--more(.*?)?-->/', $content, 2);
        }
        
        $preview = $parts[0];
        $has_more = count($parts) > 1;
        
        $output = '<div class="flosc-protected-content flosc-preview">';
        $output .= '<div class="flosc-preview-content">' . $preview . '</div>';
        
        if ($has_more) {
            $output .= '<div class="flosc-protected-notice">';
            $output .= '<p>🔒 The rest of this lesson is for members only.</p>';
            $output .= '</div>';
            $output .= $this->get_chatbot_cta($post_id);
        }
        
        $output .= '</div>';
        
        return apply_filters('flosc_preview_content', $output, $post_id, $preview);
    }
    
    /**
     * Get CTA to chatbot with tracking
     * v1.4.3: Added post tracking for chat context
     * 
     * @param int $post_id Optional post ID for tracking
     * @return string
     */
    private function get_chatbot_cta($post_id = null) {
        $app_slug = get_option('flosc_app_slug', 'app');
        $app_url = home_url('/' . $app_slug . '/');
        
        // Add tracking params so chat knows where user came from
        if ($post_id) {
            $post = get_post($post_id);
            $tracking = [
                'from' => 'lesson',
                'post_id' => $post_id,
                'slug' => $post ? $post->post_name : '',
                'title' => $post ? urlencode($post->post_title) : '',
            ];
            $app_url = add_query_arg($tracking, $app_url);
        }
        
        $cta = '<div class="flosc-chatbot-cta">';
        $cta .= '<a href="' . esc_url($app_url) . '" class="flosc-cta-button">';
        $cta .= '💬 Chat with us to unlock access';
        $cta .= '</a>';
        $cta .= '</div>';
        
        return apply_filters('flosc_chatbot_cta', $cta, $post_id);
    }
    
    /**
     * v1.4.3: Get chat URL with tracking for a specific post
     * Used for redirect-to-chat functionality
     * 
     * @param int $post_id
     * @return string
     */
    public function get_chat_url_for_post($post_id) {
        $app_slug = get_option('flosc_app_slug', 'app');
        $app_url = home_url('/' . $app_slug . '/');
        
        $post = get_post($post_id);
        $tracking = [
            'from' => 'lesson',
            'post_id' => $post_id,
            'slug' => $post ? $post->post_name : '',
        ];
        
        return add_query_arg($tracking, $app_url);
    }
    
    /**
     * Filter excerpt for teaser tier
     * 
     * @param string $excerpt
     * @param WP_Post $post
     * @return string
     */
    public function filter_excerpt($excerpt, $post = null) {
        if (!$post) {
            return $excerpt;
        }
        
        // Only filter on singular post pages
        if (!is_singular('post')) {
            return $excerpt;
        }
        
        // Check protection
        if ($this->user_can_access($post->ID)) {
            return $excerpt;
        }
        
        // Protected and no access - return excerpt unchanged
        // (visibility tier handles the main content)
        return $excerpt;
    }
    
    /**
     * Set category as protected
     * 
     * @param int $category_id
     * @param string $required_level Optional membership level required
     * @return bool
     */
    public function protect_category($category_id, $required_level = null) {
        update_term_meta($category_id, '_flosc_protected', 'yes');
        
        if ($required_level) {
            update_term_meta($category_id, '_flosc_required_level', sanitize_key($required_level));
        }
        
        return true;
    }
    
    /**
     * Unprotect a category
     * 
     * @param int $category_id
     * @return bool
     */
    public function unprotect_category($category_id) {
        delete_term_meta($category_id, '_flosc_protected');
        delete_term_meta($category_id, '_flosc_required_level');
        
        return true;
    }
    
    /**
     * Set post visibility
     * 
     * @param int $post_id
     * @param string $visibility 'hidden' | 'teaser' | 'preview' | 'public'
     * @return bool
     */
    public function set_post_visibility($post_id, $visibility) {
        if (!in_array($visibility, ['hidden', 'teaser', 'preview', 'public'])) {
            return false;
        }
        
        update_post_meta($post_id, '_flosc_post_visibility', $visibility);
        return true;
    }
    
    /**
     * Get all protected categories
     * 
     * @return array Array of category objects with protection info
     */
    public function get_protected_categories() {
        $categories = get_categories(['hide_empty' => false]);
        $protected = [];
        
        foreach ($categories as $cat) {
            if ($this->is_category_protected($cat->term_id)) {
                $protected[] = [
                    'id' => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'required_level' => $this->get_category_required_level($cat->term_id)
                ];
            }
        }
        
        return $protected;
    }
}

// Initialize
FLOSC_Content_Protection::instance();
