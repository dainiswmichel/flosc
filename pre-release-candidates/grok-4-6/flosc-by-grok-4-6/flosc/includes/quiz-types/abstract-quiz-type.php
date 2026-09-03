<?php
/**
 * Abstract Quiz Type Base Class
 *
 * All quiz types must extend this class and implement required methods.
 * This enables FLOSC to support any quiz format without changing core code.
 *
 * @package FLOSC
 * @version 3.0.1
 */

if (!defined('ABSPATH')) exit;

abstract class FLOSC_Abstract_Quiz_Type {

    /**
     * Quiz type identifier (unique slug)
     * @return string
     */
    abstract public function get_id();

    /**
     * Human-readable name for admin UI
     * @return string
     */
    abstract public function get_name();

    /**
     * Description shown in admin quiz type selector
     * @return string
     */
    abstract public function get_description();

    /**
     * Emoji/icon for UI
     * @return string
     */
    abstract public function get_icon();

    /**
     * Does this quiz need audio recording?
     * @return bool
     */
    abstract public function needs_audio();

    /**
     * Does this quiz need speech-to-text?
     * @return bool
     */
    abstract public function needs_stt();

    /**
     * Does this quiz need AI analysis?
     * @return bool
     */
    abstract public function needs_ai_analysis();

    /**
     * Get instructions shown to user
     * Can use placeholders: {quiz_content}, {passing_score}
     * @return string
     */
    abstract public function get_instructions();

    /**
     * Get default quiz content (for fresh installs)
     * @return string
     */
    abstract public function get_default_content();

    /**
     * Validate user input before processing
     * @param mixed $input User's answer (text, audio data, etc.)
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    abstract public function validate_input($input);

    /**
     * Analyze the user's answer
     *
     * @param mixed $input User's answer
     * @param string $expected_content Quiz content from admin settings
     * @param array $context Additional context (STT result, user_id, etc.)
     * @return array {
     *     @type int $score Score 0-100
     *     @type array $correct Items answered correctly
     *     @type array $incorrect Items answered incorrectly
     *     @type string $response_key Key for response template (e.g., '0-30', 'missing_th')
     *     @type array $details Additional analysis details
     * }
     */
    abstract public function analyze($input, $expected_content, $context = []);

    /**
     * Get admin settings fields for this quiz type
     * @return array Settings fields configuration
     */
    abstract public function get_settings_fields();

    /**
     * Get default response templates for this quiz type
     * Keys should match response_key from analyze()
     *
     * @return array ['response_key' => 'Template text with {placeholders}']
     */
    public function get_default_response_templates() {
        return [
            '0-30' => "Your score: {score}%\n\nYou need significant practice. {lesson_recommendations}",
            '31-60' => "Your score: {score}%\n\nGood start! Keep practicing. {lesson_recommendations}",
            '61-85' => "Your score: {score}%\n\nAlmost there! {lesson_recommendations}",
            '86-100' => "Your score: {score}%\n\nExcellent work! {lesson_recommendations}",
        ];
    }

    /**
     * Map analysis results to lesson recommendations using a three-tier system.
     *
     * Tier 1 — CorrectContent: the explicit primary lesson the admin tagged for this question.
     * Tier 2 — RelatedContent: secondary posts (e.g., concepts from the wrong answer options).
     * Tier 3 — TOPIC: fallback tag lookup (backward compat for older question formats).
     *
     * Results are sorted tier-1 first so callers get the highest-priority lessons up front.
     *
     * @param array $analysis Result from analyze()
     * @return array [['id' => int, 'title' => string, 'reason' => string, 'tier' => int], ...]
     */
    public function map_to_lessons( $analysis ) {
        $lessons = [];

        // Tier 1 — CorrectContent (primary lesson references per incorrect question)
        // correct_content may be a string (single ref) or array (multiple refs) — both handled via (array) cast
        foreach ( $analysis['incorrect'] ?? [] as $item ) {
            foreach ( (array) ( $item['correct_content'] ?? [] ) as $ref ) {
                $ref = trim( $ref );
                if ( $ref === '' ) continue;
                foreach ( $this->lookup_lesson_by_tag( $ref ) as $post ) {
                    if ( ! isset( $lessons[ $post->ID ] ) ) {
                        $lessons[ $post->ID ] = [
                            'id'     => $post->ID,
                            'title'  => $post->post_title,
                            'reason' => 'Primary: ' . $ref,
                            'tier'   => 1,
                        ];
                    }
                }
            }
        }

        // Tier 2 — RelatedContent (secondary references, e.g. concepts in wrong options)
        foreach ( $analysis['incorrect'] ?? [] as $item ) {
            foreach ( (array) ( $item['related_content'] ?? [] ) as $t ) {
                $t = trim( $t );
                if ( $t === '' ) continue;
                foreach ( $this->lookup_lesson_by_tag( $t ) as $post ) {
                    if ( ! isset( $lessons[ $post->ID ] ) ) {
                        $lessons[ $post->ID ] = [
                            'id'     => $post->ID,
                            'title'  => $post->post_title,
                            'reason' => 'Related: ' . $t,
                            'tier'   => 2,
                        ];
                    }
                }
            }
        }

        // Tier 3 — TOPIC fallback (backward compat with older question formats)
        $topics = [];
        foreach ( $analysis['incorrect'] ?? [] as $item ) {
            foreach ( (array) ( $item['topics'] ?? [] ) as $t ) {
                $t = trim( $t );
                if ( $t !== '' ) $topics[] = $t;
            }
        }
        foreach ( array_unique( $topics ) as $tag ) {
            foreach ( $this->lookup_lesson_by_tag( $tag ) as $post ) {
                if ( ! isset( $lessons[ $post->ID ] ) ) {
                    $lessons[ $post->ID ] = [
                        'id'     => $post->ID,
                        'title'  => $post->post_title,
                        'reason' => 'Topic: ' . $tag,
                        'tier'   => 3,
                    ];
                }
            }
        }

        if ( empty( $lessons ) ) return [];

        // Sort by tier so tier-1 (primary) lessons appear first
        $result = array_values( $lessons );
        usort( $result, function ( $a, $b ) {
            return ( $a['tier'] ?? 3 ) - ( $b['tier'] ?? 3 );
        } );
        return $result;
    }

    /**
     * Look up WordPress posts from a typed reference.
     *
     * The prefix declares the lookup type — it is always required:
     *
     *   post:my-post-slug        — single post by slug
     *   id:1042                  — single post by numeric ID
     *   category:my-cat-slug     — all posts in a category (up to 5)
     *   tag:my-tag-slug          — all posts with a tag (up to 5)
     *   search:my search query   — title/full-text search (up to 3)
     *
     * Legacy fallback (no prefix): tries numeric ID → post slug → category → tag → title search.
     * This only exists for saved content from older versions; all new content must use a prefix.
     *
     * A missing or non-existent reference always returns [] — never an error.
     *
     * @param string $ref
     * @return WP_Post[]
     */
    protected function lookup_lesson_by_tag( $ref ) {
        $ref = trim( $ref );
        if ( $ref === '' ) return [];

        // Typed dispatch — prefix:value
        if ( strpos( $ref, ':' ) !== false ) {
            list( $type, $value ) = explode( ':', $ref, 2 );
            $type  = strtolower( trim( $type ) );
            $value = trim( $value );

            switch ( $type ) {
                case 'post':
                    if ( strpos( $value, '/' ) !== false ) {
                        // Hierarchical path (e.g. vowels/lesson-1) — try all public post types
                        // because the same slug can exist under different parents
                        foreach ( get_post_types( [ 'public' => true ], 'names' ) as $pt ) {
                            $post = get_page_by_path( $value, OBJECT, $pt );
                            if ( $post && $post->post_status === 'publish' ) return [ $post ];
                        }
                        return [];
                    }
                    // Flat slug — globally unique within 'post' type
                    $post = get_page_by_path( $value, OBJECT, 'post' );
                    if ( $post && $post->post_status === 'publish' ) return [ $post ];
                    return [];

                case 'id':
                    if ( is_numeric( $value ) ) {
                        $post = get_post( (int) $value );
                        if ( $post && $post->post_status === 'publish' ) return [ $post ];
                    }
                    return [];

                case 'category':
                    // Supports parent/child path: category:parent-slug/child-slug
                    $cat = null;
                    if ( strpos( $value, '/' ) !== false ) {
                        $parent_id = 0;
                        foreach ( explode( '/', $value ) as $slug ) {
                            $args = [ 'taxonomy' => 'category', 'slug' => $slug, 'hide_empty' => false ];
                            if ( $parent_id ) $args['parent'] = $parent_id;
                            $terms = get_terms( $args );
                            if ( empty( $terms ) || is_wp_error( $terms ) ) { $cat = null; break; }
                            $cat       = $terms[0];
                            $parent_id = $cat->term_id;
                        }
                    } else {
                        $cat = get_category_by_slug( $value );
                    }
                    if ( $cat ) {
                        $posts = get_posts( [ 'category' => $cat->term_id, 'post_status' => 'publish', 'numberposts' => 5 ] );
                        if ( $posts ) return $posts;
                    }
                    return [];

                case 'tag':
                    $term = get_term_by( 'slug', $value, 'post_tag' );
                    if ( $term ) {
                        $posts = get_posts( [ 'tag' => $value, 'post_status' => 'publish', 'numberposts' => 5 ] );
                        if ( $posts ) return $posts;
                    }
                    return [];

                case 'search':
                    return get_posts( [ 's' => $value, 'post_status' => 'publish', 'numberposts' => 3 ] ) ?: [];
            }
            // Unrecognised prefix — fall through to legacy auto-resolve
        }

        // Legacy auto-resolve (no prefix — supports old saved content only)
        if ( is_numeric( $ref ) ) {
            $post = get_post( (int) $ref );
            if ( $post && $post->post_status === 'publish' ) return [ $post ];
        }
        if ( strpos( $ref, '/' ) !== false ) {
            // Hierarchical path — try all public post types
            foreach ( get_post_types( [ 'public' => true ], 'names' ) as $pt ) {
                $post = get_page_by_path( $ref, OBJECT, $pt );
                if ( $post && $post->post_status === 'publish' ) return [ $post ];
            }
            return [];
        }
        $post = get_page_by_path( $ref, OBJECT, 'post' );
        if ( $post && $post->post_status === 'publish' ) return [ $post ];
        $cat = get_category_by_slug( $ref );
        if ( $cat ) {
            $posts = get_posts( [ 'category' => $cat->term_id, 'post_status' => 'publish', 'numberposts' => 5 ] );
            if ( $posts ) return $posts;
        }
        $term = get_term_by( 'slug', $ref, 'post_tag' );
        if ( $term ) {
            $posts = get_posts( [ 'tag' => $ref, 'post_status' => 'publish', 'numberposts' => 5 ] );
            if ( $posts ) return $posts;
        }
        return get_posts( [ 's' => $ref, 'post_status' => 'publish', 'numberposts' => 3 ] ) ?: [];
    }

    /**
     * Format results for chat display
     * Override for custom formatting
     *
     * @param array $analysis Result from analyze()
     * @param array $lessons Result from map_to_lessons()
     * @param array $response_templates Admin-configured templates
     * @return string Formatted message
     */
    public function format_results($analysis, $lessons, $response_templates) {
        $score = $analysis['score'];
        $response_key = $analysis['response_key'];

        // Get appropriate template
        $template = $response_templates[$response_key] ?? $response_templates['31-60'] ?? "Your score: {score}%";

        // Build lesson text
        $lesson_text = '';
        if (!empty($lessons)) {
            $free_lesson = $lessons[0];
            $paid_lessons = array_slice($lessons, 1);

            $lesson_text = "\n\n**📚 Recommended Lessons:**\n\n";
            $lesson_text .= "🎁 **{$free_lesson['title']}** (FREE)\n";
            $lesson_text .= "   {$free_lesson['reason']}\n";

            if (!empty($paid_lessons)) {
                $lesson_text .= "\n🔒 **Unlock " . count($paid_lessons) . " more lessons:**\n";
                foreach ($paid_lessons as $lesson) {
                    $lesson_text .= "   • {$lesson['title']}\n";
                }
            }
        }

        // Replace placeholders
        $message = str_replace(
            ['{score}', '{lesson_recommendations}'],
            [$score, $lesson_text],
            $template
        );

        return $message;
    }

    /**
     * Get setting value for this quiz type
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get_setting($key, $default = '') {
        return get_option("flosc_quiz_{$this->get_id()}_{$key}", $default);
    }

    /**
     * Helper: Calculate percentage score
     */
    protected function calculate_percentage($correct_count, $total_count) {
        if ($total_count === 0) return 0;
        return round(($correct_count / $total_count) * 100);
    }

    /**
     * Helper: Determine response key from score
     */
    protected function get_response_key_from_score($score) {
        if ($score <= 30) return '0-30';
        if ($score <= 60) return '31-60';
        if ($score <= 85) return '61-85';
        return '86-100';
    }
}
