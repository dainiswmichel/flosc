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
     * The admin Lessons tab saves to flow_content_item_category → $flow_settings['content_item_category'].
     * The global flosc_content_item_category option may be empty if only per-flow was configured.
     */
    private function resolve_category() {
        // 1. Try per-flow settings (where the admin actually saves it)
        if (function_exists('flosc')) {
            $flow = flosc()->get_current_flow();
            if ($flow && !empty($flow['content_item_category'])) {
                return $flow['content_item_category'];
            }
        }
        
        // 2. Try global option (legacy / fallback)
        $global = get_option('flosc_content_item_category', '');
        if (!empty($global)) {
            return $global;
        }
        
        // 3. Last resort: scan all flow settings for any configured category
        // §2: union shipped defaults with uploaded/edited IVR files (uploads wins).
        $files = function_exists('flosc_config_glob') ? flosc_config_glob(['*_ivr.md', 'ivr*.md']) : [];
        if (!empty($files)) {
            foreach (array_unique(array_map('basename', $files)) as $filename) {
                $key = 'flosc_flow_' . sanitize_key(pathinfo($filename, PATHINFO_FILENAME));
                $settings = get_option($key, []);
                if (!empty($settings['content_item_category'])) {
                    return $settings['content_item_category'];
                }
            }
        }
        
        return '';
    }
    
    /**
     * Get all lessons from ALL configured categories (content_item_groups + content_item_category).
     *
     * v3.0.8: Queries every category across all content_item_groups in the current flow so
     * "show all lessons" returns the full library across enabled lesson groups.
     * Falls back to single content_item_category for flows without content_item_groups.
     */
    public function get_all_lessons() {
        // --- Collect every category slug/ID from content_item_groups first ---
        $categories = [];
        if ( function_exists( 'flosc' ) ) {
            $flow = flosc()->get_current_flow();
            if ( $flow && ! empty( $flow['content_item_groups'] ) && is_array( $flow['content_item_groups'] ) ) {
                foreach ( $flow['content_item_groups'] as $group ) {
                    if ( ! empty( $group['category'] ) ) {
                        $categories[] = $group['category'];
                    }
                }
            }
        }

        // --- If no content_item_groups, fall back to the single content_item_category ---
        if ( empty( $categories ) ) {
            $single = $this->resolve_category();
            if ( empty( $single ) ) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log( '[FLOSC Lessons] No lesson categories configured. Set content_item_groups or content_item_category.' );
                return [];
            }
            $categories = [ $single ];
        }

        $categories = array_unique( $categories );

        // --- Resolve slugs / IDs into term_id list for a single efficient query ---
        $cat_ids = [];
        foreach ( $categories as $cat ) {
            if ( is_numeric( $cat ) ) {
                $cat_ids[] = intval( $cat );
            } else {
                $term = get_term_by( 'slug', sanitize_title( $cat ), 'category' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $cat_ids[] = $term->term_id;
                }
            }
        }

        if ( empty( $cat_ids ) ) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log( '[FLOSC Lessons] None of the configured categories exist: ' . implode( ', ', $categories ) );
            return [];
        }

        // Single WP_Query across all category IDs (category__in = OR logic)
        $query = new WP_Query( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'category__in'   => $cat_ids,
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
        ] );
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log( '[FLOSC Lessons] v3.0.8 get_all_lessons: categories=' . implode( ',', $categories ) . ' found=' . $query->found_posts );

        $lessons = [];
        foreach ( $query->posts as $post ) {
            $lessons[] = $this->format_lesson( $post );
        }

        return $lessons;
    }
    
    /**
     * v3.0.8: Get lessons from quiz-linked categories only (content_item_groups with quiz_id set).
     * Used for "show me the lessons covered in the quiz" — returns the quiz-mapped
     * content library (e.g. FLOSC Sample Data 10 posts), not the standalone library.
     */
    public function get_quiz_lessons() {
        $categories = [];
        if ( function_exists( 'flosc' ) ) {
            $flow = flosc()->get_current_flow();
            if ( $flow && ! empty( $flow['content_item_groups'] ) && is_array( $flow['content_item_groups'] ) ) {
                foreach ( $flow['content_item_groups'] as $group ) {
                    // Only include groups that have a quiz_id (quiz-linked, not standalone)
                    if ( ! empty( $group['quiz_id'] ) && ! empty( $group['category'] ) ) {
                        $categories[] = $group['category'];
                    }
                }
            }
        }

        if ( empty( $categories ) ) {
            // No quiz-linked groups — fall back to all lessons
            return $this->get_all_lessons();
        }

        $categories = array_unique( $categories );
        $cat_ids    = [];
        foreach ( $categories as $cat ) {
            if ( is_numeric( $cat ) ) {
                $cat_ids[] = intval( $cat );
            } else {
                $term = get_term_by( 'slug', sanitize_title( $cat ), 'category' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $cat_ids[] = $term->term_id;
                }
            }
        }

        if ( empty( $cat_ids ) ) {
            return [];
        }

        $query = new WP_Query(
            array(
                'post_type'              => 'post',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'category__in'           => $cat_ids,
                'orderby'                => 'date',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
            )
        );

        $posts = $query->posts;
        usort(
            $posts,
            static function ( $a, $b ) {
                $na = (float) get_post_meta( $a->ID, '_flosc_lesson_number', true );
                $nb = (float) get_post_meta( $b->ID, '_flosc_lesson_number', true );
                if ( $na === $nb ) {
                    return strcmp( (string) $a->post_date, (string) $b->post_date );
                }
                return $na <=> $nb;
            }
        );

        $lessons = [];
        foreach ( $posts as $post ) {
            $lessons[] = $this->format_lesson( $post );
        }

        return $lessons;
    }

    /**
     * v3.0.8: Search lessons by keyword within all configured categories.
     * Matches against post title and content so "vowel sounds", "TH", "rhotic R", etc. work naturally.
     * Results are scoped to the flow's lesson categories so members can't accidentally browse other content.
     */
    public function search_lessons( $search ) {
        if ( empty( trim( $search ) ) ) {
            return $this->get_all_lessons();
        }

        // Collect all configured category IDs (same as get_all_lessons)
        $categories = [];
        if ( function_exists( 'flosc' ) ) {
            $flow = flosc()->get_current_flow();
            if ( $flow && ! empty( $flow['content_item_groups'] ) && is_array( $flow['content_item_groups'] ) ) {
                foreach ( $flow['content_item_groups'] as $group ) {
                    if ( ! empty( $group['category'] ) ) {
                        $categories[] = $group['category'];
                    }
                }
            }
        }
        if ( empty( $categories ) ) {
            $single = $this->resolve_category();
            if ( ! empty( $single ) ) {
                $categories = [ $single ];
            }
        }

        $cat_ids = [];
        foreach ( array_unique( $categories ) as $cat ) {
            if ( is_numeric( $cat ) ) {
                $cat_ids[] = intval( $cat );
            } else {
                $term = get_term_by( 'slug', sanitize_title( $cat ), 'category' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $cat_ids[] = $term->term_id;
                }
            }
        }

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            's'              => $search,
            'orderby'        => 'relevance',
            'order'          => 'DESC',
        ];

        if ( ! empty( $cat_ids ) ) {
            $args['category__in'] = $cat_ids;
        }

        $query   = new WP_Query( $args );
        $lessons = [];
        foreach ( $query->posts as $post ) {
            $lessons[] = $this->format_lesson( $post );
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
        // v1.9.5: Decode HTML entities in title and excerpt.
        // WordPress may store curly quotes as &#8217; or &rsquo; in the DB.
        // The JS client calls escapeHtml() which would double-encode these
        // (e.g. &#8217; → &amp;#8217; rendering as literal "&#8217;" on screen).
        // html_entity_decode() converts entities back to UTF-8 characters
        // so the JSON→JS→escapeHtml() pipeline produces clean output.
        $title = html_entity_decode($post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $excerpt = html_entity_decode(get_the_excerpt($post), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $lesson = [
            'id' => $post->ID,
            'title' => $title,
            'excerpt' => $excerpt,
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
            // WordPress content filters (shortcodes, embeds, blocks, etc.).
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WP content filter required for oEmbed/shortcodes
            $lesson['content'] = apply_filters( 'the_content', $post->post_content );
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

        // v1.8.2: Use FLOSC_Member_Access which has actual level/member checking
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
