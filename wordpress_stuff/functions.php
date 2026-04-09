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
 * 2026-04-09 — BuddyBoss + page-builder compatibility (Divi, WPBakery, etc.)
 *   - BuddyBoss does not strip page-builder shortcodes from search results,
 *     activity stream, or excerpts. Raw [et_pb_*], [vc_*], [fl_builder_*],
 *     [fusion_builder_*], [siteorigin_widget_*] shortcodes show as visible text.
 *   - Fix 1: template override yourtheme/buddypress/search/loop/post.php —
 *     reads raw post_content, strip_shortcodes() + regex fallback for
 *     deactivated builders, then wp_strip_all_tags before bp_create_excerpt.
 *   - Fix 2: bp_get_activity_content_body filter at priority 8 (early pass)
 *     and priority 10000 (late pass, after BuddyBoss priority-9999 rebuilds
 *     blog-post activity content from post_content/get_the_excerpt directly).
 *   - Fix 3: get_the_excerpt filter covers archive, blog, and other contexts.
 *   - Shared helper dainis_strip_builder_shortcodes_from() used by all three.
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
  wp_enqueue_script( 'safari-tiktok-fix', get_stylesheet_directory_uri().'/assets/js/safari-tiktok-fix.js', array(), '20260409a', true );
  wp_localize_script( 'safari-tiktok-fix', 'safariTikTokFix', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
  wp_enqueue_script( 'works-catalog', get_stylesheet_directory_uri().'/assets/js/works-catalog.js', array(), '20260409f', true );
  wp_localize_script( 'works-catalog', 'worksCatalogAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'wp_enqueue_scripts', 'buddyboss_theme_child_scripts_styles', 9999 );

/* ---- oEmbed AJAX handler for works-catalog TikTok embeds ---- */
add_action( 'wp_ajax_works_oembed', 'works_oembed_handler' );
add_action( 'wp_ajax_nopriv_works_oembed', 'works_oembed_handler' );
function works_oembed_handler() {
    $url = isset( $_GET['url'] ) ? esc_url_raw( $_GET['url'] ) : '';
    if ( ! $url ) {
        wp_send_json_error( 'No URL provided' );
    }
    /* Resolve vm.tiktok.com short URLs to full tiktok.com URLs */
    if ( preg_match( '/vm\.tiktok\.com/i', $url ) ) {
        $response = wp_remote_get( $url, array( 'redirection' => 0, 'timeout' => 10 ) );
        if ( ! is_wp_error( $response ) ) {
            $resolved = wp_remote_retrieve_header( $response, 'location' );
            if ( $resolved ) {
                $url = strtok( $resolved, '?' );
            }
        }
    }
    $html = wp_oembed_get( $url, array( 'width' => 605 ) );
    if ( $html ) {
        wp_send_json_success( $html );
    } else {
        wp_send_json_error( 'Could not embed' );
    }
}


/* ---- TikTok oEmbed thumbnail proxy (Safari CORS workaround) ---- */
add_action( 'wp_ajax_works_tiktok_thumb', 'works_tiktok_thumb_handler' );
add_action( 'wp_ajax_nopriv_works_tiktok_thumb', 'works_tiktok_thumb_handler' );
function works_tiktok_thumb_handler() {
    $url = isset( $_GET['url'] ) ? esc_url_raw( $_GET['url'] ) : '';
    if ( ! $url ) { wp_send_json_error( 'No URL' ); }
    $response = wp_remote_get( 'https://www.tiktok.com/oembed?url=' . rawurlencode( $url ), array( 'timeout' => 10 ) );
    if ( is_wp_error( $response ) ) { wp_send_json_error( 'Fetch failed' ); }
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    if ( empty( $data['thumbnail_url'] ) ) { wp_send_json_error( 'No thumbnail' ); }
    wp_send_json_success( array( 'thumbnail_url' => $data['thumbnail_url'], 'title' => isset($data['title']) ? $data['title'] : '' ) );
}
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
 * ============================================================================
 * BUDDYBOSS + PAGE BUILDER COMPATIBILITY LAYER
 * 2026-04-09
 *
 * BuddyBoss Platform does not correctly strip page-builder shortcodes from
 * search results, activity stream snippets, and excerpts. Raw shortcodes like
 * [et_pb_section...] (Divi), [vc_row...] (WPBakery), [fl_builder...] (Beaver),
 * [fusion_builder...] (Avada), [siteorigin_widget...] (SiteOrigin), etc.
 * appear as visible text in search results and activity cards.
 *
 * ROOT CAUSE: BuddyBoss removes page-builder the_content filters before
 * running its own WP_Query, so shortcodes are never rendered — they fall
 * through as literal text. BuddyBoss's own strip_shortcodes() call fires
 * too late or in the wrong order to clean them up.
 *
 * STRATEGY:
 *   Primary:  strip_shortcodes() — removes ALL shortcodes registered via
 *             add_shortcode(). Covers any active builder automatically.
 *   Fallback: regex strip of known builder prefixes — covers content from
 *             deactivated builders whose old shortcodes remain in the DB.
 *             Prefixes covered: et_pb (Divi), vc (WPBakery), fl (Beaver),
 *             fusion (Avada), siteorigin (SiteOrigin).
 *
 * THREE-PART FIX:
 *
 * 1. SEARCH RESULTS (Posts/Pages built with any shortcode builder):
 *    Template override at yourtheme/buddypress/search/loop/post.php
 *    (deployed to child theme). Reads raw post_content, applies primary +
 *    fallback strip, then wp_strip_all_tags before bp_create_excerpt.
 *    Bypasses BuddyBoss's broken content pipeline entirely.
 *
 * 2. ACTIVITY STREAM (blog post activities referencing builder-built posts):
 *    Filter on bp_get_activity_content_body applies primary + fallback strip
 *    before BuddyBoss renders the activity content snippet.
 *
 * 3. FALLBACK (get_the_excerpt in archive, blog, and other contexts):
 *    Filter on get_the_excerpt applies primary + fallback strip as a safety
 *    net for any context where builder shortcodes surface in excerpts.
 * ============================================================================
 */

/**
 * Strip page-builder shortcodes from a string.
 * Primary: strip_shortcodes() (all registered shortcodes).
 * Fallback regex: known builder prefixes for deactivated plugins.
 *
 * @param string $text Raw content that may contain builder shortcodes.
 * @return string Clean text with shortcodes removed.
 */
function dainis_strip_builder_shortcodes_from( $text ) {
    // Primary: WordPress strip_shortcodes() handles all active builders.
    $text = strip_shortcodes( $text );

    // Fallback regex for deactivated-builder content left in the database,
    // and for truncated fragments: BuddyBoss stores a trimmed copy of post
    // content in the activity table and inserts [&hellip;] mid-shortcode,
    // leaving fragments like [et_pb_column type="4_4" that have no closing ].
    // \]? makes the closing bracket optional so truncated fragments are caught.
    // /u flag handles Unicode attribute values (smart quotes stored in the DB).
    // Covers: Divi (et_pb), WPBakery (vc), Beaver Builder (fl_builder),
    //         Avada Fusion (fusion_builder), SiteOrigin (siteorigin_widget).
    $bp = 'et_pb|vc|fl_builder|fusion_builder|siteorigin_widget';
    // Opening and self-closing: [et_pb_section ...] or [et_pb_section .../]
    $text = preg_replace( '/\[(?:' . $bp . ')[^\]]*\/?\]?/u', '', $text );
    // Closing tags: [/et_pb_section]
    $text = preg_replace( '/\[\/(?:' . $bp . ')[^\]]*\]?/u', '', $text );

    return trim( $text );
}

/**
 * BuddyBoss + Page Builders: strip builder shortcodes from activity stream.
 * Covers activity cards in the regular loop and the full search results template.
 */
add_filter( 'bp_get_activity_content_body', 'dainis_strip_builder_from_activity', 8 );
function dainis_strip_builder_from_activity( $content ) {
    if ( strpos( $content, '[' ) === false ) {
        return $content; // fast path: no shortcodes at all
    }
    return dainis_strip_builder_shortcodes_from( $content );
}

/**
 * BuddyBoss + Page Builders: second-pass strip for blog-post activity cards
 * on the Activity page.
 *
 * bp_blogs_activity_content_with_read_more() (buddyboss-platform, priority 9999)
 * rebuilds the entire activity content body by reading get_the_excerpt() /
 * post_content directly, then assembles the bb-content-wrp HTML wrapper.
 * It bypasses whatever our priority-8 filter set — so shortcodes reappear in
 * the final string. This filter at priority 10000 strips them from the
 * assembled HTML before BuddyBoss sends it to the template.
 */
add_filter( 'bp_get_activity_content_body', 'dainis_strip_builder_from_activity_late', 10000 );
function dainis_strip_builder_from_activity_late( $content ) {
    if ( strpos( $content, '[' ) === false ) {
        return $content;
    }
    $bp      = 'et_pb|vc|fl_builder|fusion_builder|siteorigin_widget';
    $content = preg_replace( '/\[(?:' . $bp . ')[^\]]*\/?\]?/u', '', $content );
    $content = preg_replace( '/\[\/(?:' . $bp . ')[^\]]*\]?/u', '', $content );
    return $content;
}

/**
 * BuddyBoss + Page Builders: strip builder shortcodes from the AJAX search
 * activity intro snippet. bp_search_activity_intro() calls
 * bp_get_activity_content_body() but then runs wp_strip_all_tags + substr,
 * so any surviving shortcode fragments must also be stripped at this layer.
 */
add_filter( 'bp_search_activity_intro', 'dainis_strip_builder_from_activity_intro' );
function dainis_strip_builder_from_activity_intro( $content ) {
    if ( strpos( $content, '[' ) === false ) {
        return $content;
    }
    return dainis_strip_builder_shortcodes_from( $content );
}

/**
 * BuddyBoss + Page Builders: strip builder shortcodes from excerpts.
 * Covers archive pages, blog front page, and any other excerpt context.
 */
add_filter( 'get_the_excerpt', 'dainis_strip_builder_from_excerpt', 8 );
function dainis_strip_builder_from_excerpt( $excerpt ) {
    if ( strpos( $excerpt, '[' ) === false ) {
        return $excerpt; // fast path: no shortcodes at all
    }
    return dainis_strip_builder_shortcodes_from( $excerpt );
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
