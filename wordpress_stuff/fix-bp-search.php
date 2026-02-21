<?php
/**
 * Plugin Name: BuddyBoss Search Fix
 * Description: Fixes BuddyBoss Platform search results being stripped by content filters (WLM, Divi, etc.)
 * Version: 1.0.0
 * Author: DA1NI5
 *
 * Problem: bp_search_search_page_content fires at priority 9 on the_content,
 * injects search results, then removes itself. But another filter at priority 10+
 * strips the injected content, leaving only "… Read more…" on the page.
 *
 * Solution: Re-inject search results at a very late priority (998) if the content
 * was destroyed after BP search ran.
 */

defined('ABSPATH') || exit;

add_filter('the_content', 'dainis_fix_bp_search_results', 998);

function dainis_fix_bp_search_results($content) {
    global $bpgs_main_content_filter_has_run;

    // Only act on BuddyBoss search pages where BP search already ran
    if (
        ! function_exists('bp_search_is_search') ||
        ! bp_search_is_search() ||
        'yes' !== $bpgs_main_content_filter_has_run
    ) {
        return $content;
    }

    // If content already contains search results, don't re-inject
    if (strpos($content, 'bp-search-page') !== false) {
        return $content;
    }

    // BP search ran but content was stripped — re-generate search results
    ob_start();
    bp_get_template_part('search/results-page');
    $search_html = ob_get_clean();

    if (! empty($search_html)) {
        return $search_html;
    }

    return $content;
}
