<?php
/**
 * DA1NI5 BuddyBoss Child Theme — functions.php
 *
 * Parent theme functions: /buddyboss-theme/inc/theme/functions.php
 * Add custom functions below the CUSTOM FUNCTIONS section.
 *
 * @package DA1NI5_BuddyBoss_Child
 *
 * ============================================================================
 * CHANGELOG — Michel Date Stamp Innovation (YYYY-MMm-DDd)
 * Newest entries at the top. Additive only — never delete previous entries.
 * ============================================================================
 *
 * 2026-02m-19d — Admin bar: hide for subscribers; site nav silver sheen
 *   - Subscribers (and other non-admin roles) no longer see the black
 *     WordPress admin bar at all (show_admin_bar filter).
 *   - Site navigation menu (Activity, Shop, My Account) styled with
 *     subtle silver sheen + Atkinson Hyperlegible Next font.
 *   - Body top-padding adjusted so admin bar doesn't overlap site header.
 *
 * 2026-02m-18d — Blog excerpt & "Read more..." link
 *   - Set excerpt length to 30 words (WordPress default was 55).
 *   - Replaced "[...]" with a "Read more..." link to the full post.
 *   - Posts with <!--more--> tag: excerpt uses content before the tag
 *     instead of the auto-generated trim, preserving author-controlled
 *     cut points. Posts without <!--more-->: auto-trim at 30 words.
 *   - Replaces Advanced Excerpt plugin functionality (plugin was deleted
 *     earlier because it was breaking BuddyBoss search results).
 *
 * 2026-02m-18d — BuddyBoss Search Fix (search broken for years)
 *   - ROOT CAUSE: bp_search_search_page_content injects results at priority 9
 *     on the_content, then self-removes. Filters at priority 10+ (Advanced
 *     Excerpt, WishList Member, Divi Builder) destroyed the injected HTML,
 *     leaving an empty page with "… Read more…" link.
 *   - FIX 1: Safety-net filter at priority 998 re-injects search results if
 *     stripped by other the_content filters.
 *   - FIX 2: advanced_excerpt_skip_excerpt_filtering filter tells Advanced
 *     Excerpt to skip BP search pages entirely. (Advanced Excerpt plugin was
 *     subsequently deleted, but filter kept as defensive code.)
 *   - FIX 3: bp_activity_search_where_conditions filter excludes
 *     activity_comment and last_activity types from search. These types are
 *     counted by the SQL but can't render as standalone search cards, causing
 *     a count mismatch (e.g., "2 results" but only 1 displayed).
 *   - RESULT: Search works. Counts match displayed results. No stray links.
 *
 * 2026-02m-18d — Font: Atkinson Hyperlegible Next
 *   - Enqueued Google Fonts stylesheet for Atkinson Hyperlegible Next.
 *   - Global text size set to 111% in custom.css.
 *
 * 2026-02m-18d — Pagination override
 *   - Replaced infinite-scroll with click-to-load "Load More" button.
 * ============================================================================
 */


/****************************** THEME SETUP ******************************/

/**
 * Sets up theme for translation
 *
 * @since BuddyBoss Child 1.0.0
 */
function buddyboss_theme_child_languages()
{
  /**
   * Makes child theme available for translation.
   * Translations can be added into the /languages/ directory.
   */

  // Translate text from the PARENT theme.
  load_theme_textdomain( 'buddyboss-theme', get_stylesheet_directory() . '/languages' );

  // Translate text from the CHILD theme only.
  // Change 'buddyboss-theme' instances in all child theme files to 'buddyboss-theme-child'.
  // load_theme_textdomain( 'buddyboss-theme-child', get_stylesheet_directory() . '/languages' );

}
add_action( 'after_setup_theme', 'buddyboss_theme_child_languages' );

/**
 * Enqueues scripts and styles for child theme front-end.
 *
 * @since Boss Child Theme  1.0.0
 */
function buddyboss_theme_child_scripts_styles()
{
  /**
   * Scripts and Styles loaded by the parent theme can be unloaded if needed
   * using wp_deregister_script or wp_deregister_style.
   *
   * See the WordPress Codex for more information about those functions:
   * http://codex.wordpress.org/Function_Reference/wp_deregister_script
   * http://codex.wordpress.org/Function_Reference/wp_deregister_style
   **/

  // Google Fonts — Atkinson Hyperlegible Next
  wp_enqueue_style( 'google-fonts-atkinson', 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:ital,wght@0,200..800;1,200..800&display=swap', array(), null );

  // Styles
  wp_enqueue_style( 'buddyboss-child-css', get_stylesheet_directory_uri().'/assets/css/custom.css' );

  // Javascript
  wp_enqueue_script( 'buddyboss-child-js', get_stylesheet_directory_uri().'/assets/js/custom.js' );
}
add_action( 'wp_enqueue_scripts', 'buddyboss_theme_child_scripts_styles', 9999 );


/****************************** CUSTOM FUNCTIONS ******************************/

/**
 * Hide the WordPress admin bar for non-admin users (subscribers, etc.).
 * 2026-02m-19d
 *
 * Tina Hotz and other subscribers should never see the black admin bar.
 * Only users who can edit posts (editors, admins) keep the admin bar.
 */
add_filter( 'show_admin_bar', 'dainis_hide_admin_bar_for_subscribers' );
function dainis_hide_admin_bar_for_subscribers( $show ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return false;
    }
    return $show;
}

/**
 * Blog excerpt: 30 words + "Read more..." link.
 * 2026-02m-18d
 *
 * Replaces Advanced Excerpt plugin (deleted because it broke BP search).
 * For posts with <!--more--> tag, the content before the tag is used
 * as the excerpt instead of the auto-generated 30-word trim.
 */

// Set excerpt length to 30 words.
add_filter( 'excerpt_length', 'dainis_excerpt_length', 999 );
function dainis_excerpt_length( $length ) {
    if ( is_admin() ) {
        return $length;
    }
    return 30;
}

// Replace "[...]" with a "Read more..." link.
add_filter( 'excerpt_more', 'dainis_excerpt_more', 999 );
function dainis_excerpt_more( $more ) {
    if ( is_admin() ) {
        return $more;
    }
    global $post;
    return '&hellip; <a class="read-more" href="' . esc_url( get_permalink( $post ) ) . '">Read more&hellip;</a>';
}

// If the post has a <!--more--> tag, use content before it as the excerpt.
add_filter( 'get_the_excerpt', 'dainis_more_tag_excerpt', 5, 2 );
function dainis_more_tag_excerpt( $excerpt, $post ) {
    if ( is_admin() || is_single() || is_search() ) {
        return $excerpt;
    }
    // Only override if the post has a <!--more--> tag.
    if ( strpos( $post->post_content, '<!--more-->' ) !== false ) {
        $parts   = explode( '<!--more-->', $post->post_content, 2 );
        $before  = $parts[0];
        // Strip shortcodes and tags, keep plain text.
        $before  = strip_shortcodes( $before );
        $before  = wp_strip_all_tags( $before );
        $before  = trim( $before );
        $before .= '&hellip; <a class="read-more" href="' . esc_url( get_permalink( $post ) ) . '">Read more&hellip;</a>';
        return $before;
    }
    return $excerpt;
}

/**
 * Override BuddyBoss pagination — click-to-load instead of infinite scroll.
 * 2026-02m-18d
 *
 * The parent theme hardcodes `post-infinite-scroll` class which auto-loads
 * posts on scroll. This override removes that class so the "Load More"
 * button requires a manual click.
 */
function buddyboss_pagination() {
    global $paged, $wp_query;

    $max_page = 0;

    if ( ! $max_page ) {
        $max_page = $wp_query->max_num_pages;
    }

    if ( ! $paged ) {
        $paged = 1;
    }

    $nextpage = intval( $paged ) + 1;

    if ( is_front_page() || is_home() ) {
        $template = 'home';
    } elseif ( is_category() ) {
        $template = 'category';
    } elseif ( is_search() ) {
        $template = 'search';
    } else {
        $template = 'archive';
    }

    $label = __( 'Load More', 'buddyboss-theme' );

    if ( ! is_single() && ( $nextpage <= $max_page ) ) {
        $attr = 'data-page=' . $nextpage . ' data-template=' . $template;
        echo '<div class="bb-pagination pagination-below"><a class="button-load-more-posts" href="' . esc_url( next_posts( $max_page, false ) ) . '" ' . esc_attr( $attr ) . '>' . esc_html( $label ) . '</a></div>';
    }
}

/**
 * Fix BuddyBoss activity search count to exclude activity_comment type.
 * 2026-02m-18d
 *
 * BP search's activity SQL has three OR branches for matching:
 *   1. content LIKE '%term%' AND type = 'activity_update'
 *   2. meta_key = 'post_title' AND meta_value LIKE '%term%'
 *   3. post_title LIKE '%term%'
 * Branches 2 and 3 don't filter by type, so activity_comments with matching
 * post_title get counted. But activity_comments can't render as standalone
 * search results — they cause a count mismatch (e.g., "2 results" but only
 * 1 displays). This filter adds an exclusion for non-renderable activity types.
 */
add_filter( 'bp_activity_search_where_conditions', 'dainis_exclude_activity_comments_from_search', 10, 2 );

function dainis_exclude_activity_comments_from_search( $where_conditions, $search_term ) {
    $where_conditions[] = "a.type NOT IN ('activity_comment', 'last_activity')";
    return $where_conditions;
}

/**
 * Prevent Advanced Excerpt from truncating BuddyBoss search results.
 * 2026-02m-19d
 *
 * Root cause: Advanced Excerpt hooks the_content at priority 10. BP search
 * injects full results HTML at priority 9. Advanced Excerpt then:
 *   1. Calls get_the_content('') internally → gets empty dummy post content
 *   2. Applies the_content filters on that empty text → our safety-net fix
 *      re-injects search results inside Advanced Excerpt's own filter chain
 *   3. Truncates the re-injected HTML to 40 words
 *   4. Appends "… Read more…" link with empty href
 *
 * This caused: (a) Activity results being cut off entirely (only Blog Posts
 * section survived the 40-word truncation), and (b) a stray "… Read more…"
 * link at the bottom of the page.
 *
 * Fix: Tell Advanced Excerpt to skip filtering on BuddyBoss search pages.
 */
add_filter( 'advanced_excerpt_skip_excerpt_filtering', 'dainis_skip_excerpt_on_bp_search' );

function dainis_skip_excerpt_on_bp_search( $skip ) {
    if ( function_exists( 'bp_search_is_search' ) && bp_search_is_search() ) {
        return true;
    }
    return $skip;
}

/**
 * Safety net: re-inject BuddyBoss search results if stripped by content filters.
 * 2026-02m-18d, updated 2026-02m-19d
 *
 * With Advanced Excerpt disabled on search pages (above), BP search content
 * injected at priority 9 should survive. This safety net catches any other
 * filter that might strip the content.
 */
add_filter( 'the_content', 'dainis_fix_bp_search_results', 998 );

function dainis_fix_bp_search_results( $content ) {
    global $bpgs_main_content_filter_has_run;

    // Only act on BuddyBoss search pages where BP search already ran
    if (
        ! function_exists( 'bp_search_is_search' ) ||
        ! bp_search_is_search() ||
        'yes' !== $bpgs_main_content_filter_has_run
    ) {
        return $content;
    }

    // If content already contains search results, don't re-inject
    if ( strpos( $content, 'bp-search-page' ) !== false ) {
        return $content;
    }

    // BP search ran but content was stripped — re-generate search results
    ob_start();
    bp_get_template_part( 'search/results-page' );
    $search_html = ob_get_clean();

    if ( ! empty( $search_html ) ) {
        return $search_html;
    }

    return '';
}
