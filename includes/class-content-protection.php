<?php
/**
 * FLOSC Content Protection
 * Handles category-level protection and post-level visibility
 * 
 * CATEGORY PROTECTION (term meta):
 * - _flosc_protected: 'yes' = all posts in category are hidden from public
 * 
 * POST OVERRIDE (post meta):
 * - _flosc_public_post: 'yes' = override FLOSC category protection,
 *   show per WordPress settings
 * 
 * RESOLUTION ORDER:
 * 1. Post has _flosc_public_post = 'yes' → FLOSC steps aside
 * 2. Category is protected → hidden (content filtered, excluded from queries)
 * 3. No protection → public
 * 
 * v1.4.7: Added pre_get_posts to hide protected posts from archives/feeds/search
 * v1.4.7: Auto-protect flosc_sample_data category on first run
 * v1.4.7: Simplified meta box — single override checkbox
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
        
        // v1.4.7: Hide protected-category posts from public queries (archives, feeds, search)
        add_action('pre_get_posts', [$this, 'hide_protected_from_public_queries']);
        
        // v1.4.7: Auto-protect flosc_sample_data category (runs once, stores flag in options)
        add_action('init', [$this, 'maybe_auto_protect_sample_category'], 20);
    }
    
    /**
     * v1.4.7: Hide posts in protected categories from public queries
     * 
     * Excludes protected-category posts from archives, feeds, and search
     * unless the post has _flosc_public_post override set to 'yes'.
     * Skips admin, single post views, and users with manage_options.
     * 
     * @param WP_Query $query
     */
    public function hide_protected_from_public_queries($query) {
        // Only modify public front-end queries
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Don't filter singular post views (content protection handles those)
        if ($query->is_singular()) {
            return;
        }
        
        // Admins see everything
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Collect all protected category IDs
        $protected_cat_ids = $this->get_protected_category_ids();

        // Entitled members must still see their flow lessons on category archives
        // (knowledge hub). Without this, only _flosc_public_post / mode=full posts
        // appear and paid members get an empty-looking hub.
        if (is_user_logged_in() && !empty($protected_cat_ids)) {
            $user_id = get_current_user_id();
            $protected_cat_ids = array_values(array_filter(
                $protected_cat_ids,
                function ($cat_id) use ($user_id) {
                    return !$this->user_can_access_category((int) $cat_id, $user_id);
                }
            ));
        }
        
        if (empty($protected_cat_ids)) {
            return;
        }
        
        // v1.8.2: Find posts with any non-"protected" mode (they should be visible in archives)
        // This covers: _flosc_protection_mode = full/title_excerpt/title_readmore
        // AND legacy: _flosc_public_post = yes
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- intentional visibility override query across protected categories
        $override_post_ids = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'category__in'   => $protected_cat_ids,
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- intentional visibility override query across protected categories
                'relation' => 'OR',
                [
                    'key'     => '_flosc_public_post',
                    'value'   => 'yes',
                    'compare' => '=',
                ],
                [
                    'key'     => '_flosc_protection_mode',
                    'value'   => 'protected',
                    'compare' => '!=',
                ],
            ],
        ]);
        
        // Exclude protected categories from the query
        $existing_cat_not_in = $query->get('category__not_in');
        if (!is_array($existing_cat_not_in)) {
            $existing_cat_not_in = [];
        }
        $query->set('category__not_in', array_merge($existing_cat_not_in, $protected_cat_ids));
        
        // Re-include override posts via post__in (merge with existing if any)
        if (!empty($override_post_ids)) {
            // We can't use post__in with category__not_in directly,
            // so we use a post__not_in exclusion approach instead:
            // Get ALL post IDs in protected categories that do NOT have the override
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- intentional inverse visibility query for hidden-post exclusion set
            $hidden_post_ids = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'category__in'   => $protected_cat_ids,
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- intentional inverse visibility query for hidden-post exclusion set
                    'relation' => 'OR',
                    [
                        'key'     => '_flosc_public_post',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_flosc_public_post',
                        'value'   => 'yes',
                        'compare' => '!=',
                    ],
                ],
            ]);
            
            // Remove category exclusion, use post exclusion instead
            $query->set('category__not_in', $existing_cat_not_in);
            
            if (!empty($hidden_post_ids)) {
                $existing_not_in = $query->get('post__not_in');
                if (!is_array($existing_not_in)) {
                    $existing_not_in = [];
                }
                $query->set('post__not_in', array_merge($existing_not_in, $hidden_post_ids));
            }
        }
    }
    
    /**
     * v1.4.7: Get all protected category IDs (cached per request)
     * 
     * @return array of category IDs
     */
    public function get_protected_category_ids() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        
        $all_categories = get_categories(['hide_empty' => false]);
        $cached = [];
        
        foreach ($all_categories as $cat) {
            if ($this->is_category_protected($cat->term_id)) {
                $cached[] = $cat->term_id;
            }
        }
        
        return $cached;
    }
    
    /**
     * v1.4.7: Auto-protect flosc_sample_data category on first run
     * Uses an option flag so this only runs once per site.
     */
    public function maybe_auto_protect_sample_category() {
        if (get_option('flosc_sample_data_auto_protected')) {
            return;
        }
        
        $sample_cat = get_category_by_slug('flosc_sample_data');
        if ($sample_cat) {
            update_term_meta($sample_cat->term_id, '_flosc_protected', 'yes');
        }
        
        update_option('flosc_sample_data_auto_protected', '1');
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
     * v1.8.2: Check _flosc_protection_mode first (4-tier: protected, title_excerpt, title_readmore, full)
     * 
     * @param int $post_id
     * @return string 'hidden' | 'teaser' | 'preview' | 'public'
     */
    public function get_post_visibility($post_id) {
        // v1.8.2: Check new 4-tier protection mode
        $protection_mode = get_post_meta($post_id, '_flosc_protection_mode', true);
        if ($protection_mode) {
            switch ($protection_mode) {
                case 'full':
                    return 'public';
                case 'title_excerpt':
                    return 'teaser';
                case 'title_readmore':
                    return 'preview';
                case 'protected':
                    // Fall through to category check below
                    break;
            }
        }
        
        // Legacy: check old _flosc_public_post override
        if (get_post_meta($post_id, '_flosc_public_post', true) === 'yes') {
            return 'public';
        }
        
        // Legacy: check old _flosc_post_visibility override
        $visibility = get_post_meta($post_id, '_flosc_post_visibility', true);
        if ($visibility && in_array($visibility, ['hidden', 'teaser', 'preview', 'public'])) {
            return $visibility;
        }
        
        // Check if in protected category
        $protection = $this->check_post_protection($post_id);
        
        if ($protection['protected']) {
            return 'hidden';
        }
        
        return 'public';
    }
    
    /**
     * Whether a user may browse posts in a protected category (archives / hubs).
     *
     * @param int      $category_id Category term ID.
     * @param int|null $user_id     User ID (default: current user).
     * @return bool
     */
    public function user_can_access_category($category_id, $user_id = null) {
        $category_id = absint($category_id);
        if ($category_id <= 0) {
            return true;
        }

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        $user_id = absint($user_id);

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (!$this->is_category_protected($category_id)) {
            return true;
        }

        if (!$user_id) {
            return false;
        }

        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
        $member_access = FLOSC_Member_Access::instance();

        $required_level = $this->get_category_required_level($category_id);
        if ($required_level && $member_access->has_level($user_id, $required_level)) {
            return true;
        }

        // Flow default / declared member levels for this lessons category.
        if ($this->user_has_flow_member_level_for_category($user_id, $category_id, $member_access)) {
            return true;
        }

        // No specific level on the category: any paid member.
        if (!$required_level) {
            return $member_access->is_member($user_id);
        }

        return false;
    }

    /**
     * True when the user holds a non-guest member level for a flow that owns this category.
     *
     * @param int                  $user_id
     * @param int                  $category_id
     * @param FLOSC_Member_Access  $member_access
     * @return bool
     */
    private function user_has_flow_member_level_for_category($user_id, $category_id, $member_access) {
        $cat = get_term($category_id, 'category');
        if (!$cat || is_wp_error($cat) || empty($cat->slug)) {
            return false;
        }
        $cat_slug = sanitize_title((string) $cat->slug);

        $flows = $this->get_flows_owning_lessons_category($cat_slug);
        foreach ($flows as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $default_member = sanitize_key((string) ($flow['default_member_level'] ?? ''));
            if ($default_member !== '' && $member_access->has_level($user_id, $default_member)) {
                return true;
            }
            $levels = $flow['member_levels'] ?? [];
            if (is_array($levels)) {
                foreach (array_keys($levels) as $level_key) {
                    $level_key = sanitize_key((string) $level_key);
                    if ($level_key === '' || strpos($level_key, 'guest') !== false) {
                        continue;
                    }
                    if ($member_access->has_level($user_id, $level_key)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Find flow option payloads whose lessons_category / lesson_groups use this category slug.
     *
     * @param string $category_slug
     * @return array[]
     */
    private function get_flows_owning_lessons_category($category_slug) {
        $category_slug = sanitize_title((string) $category_slug);
        if ($category_slug === '' || !function_exists('flosc_config_glob')) {
            return [];
        }

        $found = [];
        $ivr_files = array_unique(array_map('basename', flosc_config_glob(['*_ivr.md', 'ivr*.md'])));
        foreach ($ivr_files as $filename) {
            if (strpos($filename, 'backup') !== false) {
                continue;
            }
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $settings = get_option('flosc_flow_' . sanitize_key($base), []);
            if (!is_array($settings) || empty($settings)) {
                continue;
            }
            $flow_cats = [];
            if (!empty($settings['lessons_category'])) {
                $flow_cats[] = sanitize_title((string) $settings['lessons_category']);
            }
            if (!empty($settings['lesson_groups']) && is_array($settings['lesson_groups'])) {
                foreach ($settings['lesson_groups'] as $group) {
                    if (!empty($group['category'])) {
                        $flow_cats[] = sanitize_title((string) $group['category']);
                    }
                }
            }
            if (in_array($category_slug, array_filter(array_unique($flow_cats)), true)) {
                $found[] = $settings;
            }
        }

        return $found;
    }

    /**
     * Check if current user can access a post
     * v1.8.2: Check _flosc_protection_mode for granular access
     * 
     * @param int $post_id
     * @return bool
     */
    public function user_can_access($post_id) {
        // v1.8.2: Check 4-tier protection mode — 'full' always accessible
        $protection_mode = get_post_meta($post_id, '_flosc_protection_mode', true);
        if ($protection_mode === 'full') {
            return true;
        }
        
        // v1.4.3: Legacy public posts are always accessible
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

        // Category-level entitlement (required level + aliases + owning flow member levels).
        if (!empty($protection['category_id']) && $this->user_can_access_category((int) $protection['category_id'], $user_id)) {
            return true;
        }
        
        // v3.0.1: Flow-wide member access — if this post's category is in ANY
        // of the current flow's lesson_groups, check if the user has the required
        // level for ANY category in that flow. One purchase unlocks all flow categories.
        if (function_exists('flosc')) {
            $flow = flosc()->get_current_flow();
            if ($flow && !empty($flow['lesson_groups']) && is_array($flow['lesson_groups'])) {
                // Is this post's category in this flow's lesson_groups?
                $post_cat_id = $protection['category_id'];
                $post_in_flow = false;
                foreach ($flow['lesson_groups'] as $group) {
                    if (!empty($group['category'])) {
                        $cat_obj = get_term_by('slug', sanitize_title($group['category']), 'category');
                        if ($cat_obj && (int)$cat_obj->term_id === (int)$post_cat_id) {
                            $post_in_flow = true;
                            break;
                        }
                    }
                }
                // If yes, check if user has ANY level from this flow's categories
                if ($post_in_flow) {
                    foreach ($flow['lesson_groups'] as $group) {
                        if (!empty($group['category'])) {
                            $cat_obj = get_term_by('slug', sanitize_title($group['category']), 'category');
                            if ($cat_obj) {
                                $level = get_term_meta($cat_obj->term_id, '_flosc_required_level', true);
                                if ($level && $member_access->has_level($user_id, $level)) {
                                    return true;
                                }
                            }
                        }
                    }
                    $default_member = sanitize_key((string) ($flow['default_member_level'] ?? ''));
                    if ($default_member !== '' && $member_access->has_level($user_id, $default_member)) {
                        return true;
                    }
                }
            }
        }
        
        // Protected with no specific level required — any paid member.
        if (empty($protection['required_level'])) {
            return $member_access->is_member($user_id);
        }

        return false;
    }
    
    /**
     * Main content filter - applies visibility tier
     * 
     * @param string $content
     * @return string Filtered content
     */
    public function filter_by_visibility($content) {
        /* Escaping principle for this filter: FLOSC escapes what FLOSC
         * builds — the hidden/teaser/preview messages and CTA boxes are
         * wp_kses_post()'d where they are constructed (their helpers, after
         * their public filters run). Post content that merely passes through
         * unmodified is core's pipeline, not plugin output: re-filtering it
         * here would strip oEmbed iframes and other plugins' markup from
         * lessons readers are entitled to see. */

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

        // User doesn't have access - apply visibility tier.
        // Tier output is escaped where it is built (each tier helper ends in
        // wp_kses_post() after its filter), so it arrives here already safe.
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
            // Each tier helper escapes its own constructed markup with
            // wp_kses_post() as its final act — escaping lives at the one
            // place the HTML is built, not re-applied at every relay point.
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
        
        $cta_box = '<div class="flosc-public-post-cta">';
        $cta_box .= '<p>🎉 Enjoying this free lesson?</p>';
        $cta_box .= '<p>Want personalized help or access to all lessons?</p>';
        $cta_box .= '<a href="' . esc_url($chat_url) . '">💬 Chat with us</a>';
        $cta_box .= '</div>';

        // Escape ONLY the markup FLOSC constructs (the CTA box). The post body
        // is core's own content and is relayed untouched, exactly as the_content
        // would output it — re-filtering it through wp_kses_post() here would
        // strip oEmbed iframes (video lessons) and other legitimate embeds from
        // free posts. Escape-what-we-build; never re-filter relayed core content.
        $content .= wp_kses_post($cta_box);

        return $content;
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
        
        return wp_kses_post(apply_filters('flosc_hidden_content_message', $message, $post_id));
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
            $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 55, '...');
        }
        
        $output = '<div class="flosc-protected-content flosc-teaser">';
        $output .= '<div class="flosc-teaser-excerpt">' . wp_kses_post(wpautop($excerpt)) . '</div>';
        $output .= '<div class="flosc-protected-notice">';
        $output .= '<p>🔒 Want to read more? Unlock full access!</p>';
        $output .= '</div>';
        $output .= $this->get_chatbot_cta($post_id);
        $output .= '</div>';
        
        return wp_kses_post(apply_filters('flosc_teaser_content', $output, $post_id));
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

        // The wrapper, notice, and CTA above are FLOSC's own hardcoded markup
        // (the CTA's URL is escaped at its source in get_chatbot_cta()); $preview
        // is core's partial post content. Relay it untouched so a free preview
        // keeps any oEmbed/video it contains — wp_kses_post() here would strip
        // those iframes. Escape-what-we-build; never re-filter relayed content.
        return $output;
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

        // Escape at the source. The CTA is FLOSC-built markup with no relayed
        // core content, so wp_kses_post() here is safe (it strips nothing from
        // this hardcoded fragment) and idempotent where a caller wraps again.
        // This guarantees the CTA is escaped in every consumption path —
        // including the preview tier, which deliberately leaves its own return
        // unwrapped to preserve oEmbed embeds in the relayed $preview.
        return wp_kses_post( apply_filters( 'flosc_chatbot_cta', $cta, $post_id ) );
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
        // This filter never builds markup — every branch relays core's own
        // excerpt untouched, so there is nothing of FLOSC's to escape here.
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
