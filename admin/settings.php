<?php
/**
 * FLOSC Admin Settings Page v1.4.8
 * 
 * Simple: IVR file = Flow. Pick file, edit all tabs, save.
 * 
 * v1.2.9: Added flosc_tab_header() helper, permalink status, Michel timestamp
 * v1.3.0: Fixed permalink status detection - save defaults on first access
 * v1.3.2: Identity tab shows URL mapping, DNS help
 * v1.3.3: All Flows = fully expanded inline editing, Domain field
 * v1.3.4: Register rewrite rules for ALL IVR files with default slugs, immediate version-flush
 * v1.4.8: Admin styling overhaul - WordPress-native colors, no gradients, no emojis in chrome
 */

if (!defined('ABSPATH')) exit;

/**
 * Tab Header Helper - displays consistent header across all tabs
 * Format: {emoji} {Tab Name} Configuration for {FlowName} ({filename})
 * 
 * @param string $emoji Tab emoji
 * @param string $tab_name Tab display name
 * @return void
 */
if (!function_exists('flosc_tab_header')) {
    function flosc_tab_header($emoji, $tab_name) {
        $flosc_ivr_file = $GLOBALS['flosc_current_ivr'] ?? '';
        $flosc_settings = $GLOBALS['flosc_current_settings'] ?? [];
        $flow_name = $flosc_settings['identity']['name'] ?? ucwords(str_replace(['_', '-', '.md'], [' ', ' ', ''], $flosc_ivr_file));
        
        echo '<div class="flosc-tab-header">';
        echo '<h2 class="flosc-tab-header__title">';
        echo esc_html($tab_name . ' Configuration');
        echo '</h2>';
        echo '<p class="flosc-tab-header__meta">';
        echo 'Flow: <strong>' . esc_html($flow_name) . '</strong> ';
        echo '<code class="flosc-tab-header__file">(' . esc_html($flosc_ivr_file) . ')</code>';
        echo '</p>';
        echo '</div>';
    }
}

/**
 * Tab Footer Helper - displays version in footer of all tabs
 * 
 * @return void
 */
if (!function_exists('flosc_tab_footer')) {
    function flosc_tab_footer() {
        $flosc_version = defined('FLOSC_VERSION') ? FLOSC_VERSION : '?.?.?';
        echo '<div class="flosc-tab-footer">';
        echo '<span class="flosc-tab-footer__version">FLOSC v' . esc_html($flosc_version) . '</span>';
        echo '</div>';
    }
}

/**
 * Generate Michel Timestamp - overthetop silly specific format
 * Format: 2026y-02m-05d-UTC10h-43m-22s
 * 
 * @return string
 */
if (!function_exists('flosc_michel_timestamp')) {
    function flosc_michel_timestamp() {
        return gmdate('Y') . 'y-' . gmdate('m') . 'm-' . gmdate('d') . 'd-UTC' . gmdate('H') . 'h-' . gmdate('i') . 'm-' . gmdate('s') . 's';
    }
}

/**
 * Check if a flow's slug is registered in rewrite rules
 * 
 * @param string $slug The slug to check
 * @return string 'ok', 'missing', or 'unknown'
 */
if (!function_exists('flosc_check_permalink_status')) {
    function flosc_check_permalink_status($slug) {
        if (empty($slug)) {
            return 'unknown';
        }
        
        $rules = get_option('rewrite_rules', []);
        if (empty($rules) || !is_array($rules)) {
            return 'unknown';
        }
        
        // Check if any rule contains our slug pattern
        // Rules are stored as: '^app/?$' => 'index.php?flosc_app=1&flosc_ivr=...'
        foreach ($rules as $regex => $flosc_query) {
            // Check if this rule matches our slug (the regex starts with ^slug)
            if (preg_match('/^\^' . preg_quote($slug, '/') . '/', $regex)) {
                return 'ok';
            }
            // Also check query string for flosc_app (indicates FLOSC route)
            if (strpos($regex, $slug) !== false && strpos($flosc_query, 'flosc_app=1') !== false) {
                return 'ok';
            }
        }
        
        return 'missing';
    }
}

/**
 * Render permalink status indicator
 * Green = OK, Yellow = Unknown, Red = Needs Flush
 * 
 * @param string $slug The flow slug
 * @return void
 */
if (!function_exists('flosc_permalink_status_indicator')) {
    function flosc_permalink_status_indicator($slug) {
        $flosc_status = flosc_check_permalink_status($slug);
        $last_flush = get_option('flosc_last_permalink_flush', null);
        
        $colors = [
            'ok' => ['class' => 'flosc-permalink-badge--ok', 'text' => '&#10003; Permalinks OK'],
            'missing' => ['class' => 'flosc-permalink-badge--missing', 'text' => '&#9888; Needs Flush'],
            'unknown' => ['class' => 'flosc-permalink-badge--unknown', 'text' => '? Status Unknown'],
        ];
        
        $color = $colors[$flosc_status];
        
        echo '<div class="flosc-permalink-status">';

        // Badge 1: Permalinks status
        echo '<span class="flosc-permalink-badge ' . esc_attr($color['class']) . '">';
        echo esc_html( $color['text'] );
        echo '</span>';

        // Badge 2: FLOW settings backfill status
        $last_backfill = get_option( 'flosc_last_flow_backfill', null );
        if ( $last_backfill ) {
            echo '<span class="flosc-permalink-badge flosc-permalink-badge--ok">&#10003; FLOW Settings OK</span>';
        }

        if ($last_flush) {
            echo '<span class="flosc-permalink-last-flush">Last flush: ' . esc_html($last_flush) . '</span>';
        }

        // Flush button
        $flush_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_flush_permalinks_v129'), 'flosc_flush_v129');
        echo '<a href="' . esc_url($flush_url) . '" class="button button-small flosc-permalink-flush-btn">Flush Now</a>';
        echo '</div>';
    }
}

/**
 * Resolve the canonical flow option key for a given IVR file.
 * Prevents duplicate flow option construction for the same file.
 *
 * @param string $flosc_ivr_filename
 * @return string
 */
if (!function_exists('flosc_resolve_flow_option_key_for_ivr')) {
    function flosc_resolve_flow_option_key_for_ivr($flosc_ivr_filename) {
        $flosc_ivr_filename = basename((string) $flosc_ivr_filename);
        $target_stem = sanitize_key(pathinfo($flosc_ivr_filename, PATHINFO_FILENAME));
        $default_key = 'flosc_flow_' . $target_stem;

        // Start with deterministic default key and score it conservatively.
        $best_key = $default_key;
        $best_score = -1;

        // Scan flosc_flow_* (autoload=no) via cached prepared options scan.
        $flosc_rows = function_exists( 'flosc_get_flow_option_rows' ) ? flosc_get_flow_option_rows() : array();
        if ( ! is_array( $flosc_rows ) || empty( $flosc_rows ) ) {
            return $default_key;
        }

        foreach ( $flosc_rows as $flosc_row ) {
            $option_name = (string) ( $flosc_row['option_name'] ?? '' );
            if ( $option_name === '' || strpos( $option_name, 'flosc_flow_' ) !== 0 ) {
                continue;
            }

            $flosc_settings = maybe_unserialize( $flosc_row['option_value'] ?? '' );
            if ( ! is_array( $flosc_settings ) ) {
                continue;
            }

            $active = basename((string) ($flosc_settings['active_ivr_file'] ?? ''));
            $primary = basename((string) ($flosc_settings['ivr_file'] ?? ''));
            $matches_active = ($active !== '' && $active === $flosc_ivr_filename);
            $matches_primary = ($primary !== '' && $primary === $flosc_ivr_filename);

            // Only consider keys that are explicitly tied to this IVR filename.
            if (!$matches_active && !$matches_primary && $option_name !== $default_key) {
                continue;
            }

            $message_count = 0;
            if (function_exists('flosc_flow_get_messages') && is_array($flosc_settings)) {
                $message_count = count(flosc_flow_get_messages($flosc_settings));
            } elseif (isset($flosc_settings['flow_messages']) && is_array($flosc_settings['flow_messages'])) {
                $message_count = count($flosc_settings['flow_messages']);
            }

            $score = 0;
            // Prefer rows explicitly bound to this IVR file over a plain default
            // key, because legacy duplicate rows can leave default keys stale.
            if ($matches_primary) {
                $score += 2000;
            }
            if ($matches_active) {
                $score += 1800;
            }
            if ($option_name === $default_key) {
                $score += 200;
            }
            $score += min($message_count, 200);

            if ($score > $best_score) {
                $best_score = $score;
                $best_key = $option_name;
            }
        }

        return $best_key;
    }
}

/**
 * §10: Allowlist of legitimate per-flow option keys.
 * Built from registered flows using the same resolver the render/save path uses,
 * so a posted flow key can be validated without being transformed.
 *
 * @return string[]
 */
if (!function_exists('flosc_known_flow_option_keys')) {
    function flosc_known_flow_option_keys() {
        $keys = [];
        if (function_exists('flosc_flows')) {
            foreach (flosc_flows()->get_all_flows() as $flow) {
                $ivr = (string) ($flow['ivr_file'] ?? '');
                if ($ivr === '') {
                    continue;
                }
                $keys[] = flosc_resolve_flow_option_key_for_ivr($ivr);
            }
        }
        return array_values(array_unique(array_filter($keys)));
    }
}

// Get available IVR files
// §2: union shipped defaults with uploaded/edited IVR files (uploads wins).
$flosc_ivr_files = [];
$flosc_files = flosc_config_glob(['*_ivr.md', 'ivr*.md']);
sort($flosc_files);
if (!empty($flosc_files)) {
    foreach ($flosc_files as $flosc_file) {
        $flosc_filename = basename($flosc_file);
        if (strpos($flosc_filename, 'backup') === false) {
            $flosc_ivr_files[] = $flosc_filename;
        }
    }
}
// De-dupe: the glob unions the uploads copy with the shipped plugin defaults,
// so the same flow file can appear twice. Keep one entry per filename.
$flosc_ivr_files = array_values( array_unique( $flosc_ivr_files ) );

$flosc_get_early = isset( $_GET ) && is_array( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$flosc_keep_ivr  = isset( $flosc_get_early['ivr'] ) ? sanitize_file_name( (string) $flosc_get_early['ivr'] ) : '';
if ( $flosc_keep_ivr === '' && is_user_logged_in() ) {
    $flosc_keep_ivr = sanitize_file_name( (string) get_user_meta( get_current_user_id(), '_flosc_admin_default_ivr', true ) );
}
if ( function_exists( 'flosc_filter_switch_flow_ivr_files' ) ) {
    $flosc_ivr_files = flosc_filter_switch_flow_ivr_files( $flosc_ivr_files, $flosc_keep_ivr );
}

// Delegated floscEditors only see the flows they were assigned to.
$flosc_is_site_admin = current_user_can('manage_options');
if (!$flosc_is_site_admin) {
    $flosc_user_flows = flosc_flows()->get_user_flows(get_current_user_id());
    $flosc_allowed_flow_ids = [];
    foreach ($flosc_user_flows as $flosc_user_flow) {
        $flosc_allowed_flow_ids[] = sanitize_key((string) ($flosc_user_flow['id'] ?? ''));
    }

    $flosc_ivr_files = array_values(array_filter(
        $flosc_ivr_files,
        static function($flosc_file) use ($flosc_allowed_flow_ids) {
            $flosc_flow_id = sanitize_key(pathinfo((string) $flosc_file, PATHINFO_FILENAME));
            return in_array($flosc_flow_id, $flosc_allowed_flow_ids, true);
        }
    ));
}

if (empty($flosc_ivr_files)) {
    echo '<div class="notice notice-error"><p>' . esc_html__('No accessible FLOSC flows were found for your account. Ask a site admin to assign you as a floscEditor for a flow.', 'flosc') . '</p></div>';
    return;
}

// Read request vars early. The flow selector below reads $flosc_get['ivr'] to know
// which flow is selected. ($get was previously first defined further down — after
// this point — so the selector always fell back to $flosc_ivr_files[0] and ignored the URL.)
$flosc_get = wp_unslash( $_GET );

$flosc_default_ivr_meta_key = '_flosc_admin_default_ivr';
$flosc_current_user_id = get_current_user_id();

// Selected IVR file (flow)
$flosc_selected_ivr = '';
$flosc_requested_ivr = isset($flosc_get['ivr']) ? sanitize_file_name($flosc_get['ivr']) : '';
if ($flosc_requested_ivr !== '' && in_array($flosc_requested_ivr, $flosc_ivr_files, true)) {
    $flosc_selected_ivr = $flosc_requested_ivr;
}

$flosc_user_default_ivr = sanitize_file_name((string) get_user_meta($flosc_current_user_id, $flosc_default_ivr_meta_key, true));
if ($flosc_selected_ivr === '' && $flosc_user_default_ivr !== '' && in_array($flosc_user_default_ivr, $flosc_ivr_files, true)) {
    $flosc_selected_ivr = $flosc_user_default_ivr;
}

if ($flosc_selected_ivr === '' && !empty($flosc_ivr_files)) {
    $flosc_selected_ivr = $flosc_ivr_files[0];
}

$flosc_selected_flow_id = sanitize_key(pathinfo($flosc_selected_ivr, PATHINFO_FILENAME));
if (!flosc_flows()->can_access_flow_admin($flosc_selected_flow_id)) {
    wp_die(esc_html__('You do not have access to this FLOSC flow.', 'flosc'));
}
$flosc_can_view_administration = flosc_flows()->can_access_flow_admin($flosc_selected_flow_id);

// Settings key for this flow
$flosc_settings_key = flosc_resolve_flow_option_key_for_ivr($flosc_selected_ivr);

// Load settings for this flow
$flosc_flow_settings = get_option($flosc_settings_key, []);
if (!is_array($flosc_flow_settings)) {
    $flosc_flow_settings = [];
}
if ( function_exists( 'flosc_personality_library_promote_custom_flow_voices' ) ) {
    flosc_personality_library_promote_custom_flow_voices();
    $flosc_promoted = get_option( $flosc_settings_key, array() );
    if ( is_array( $flosc_promoted ) ) {
        $flosc_flow_settings = $flosc_promoted;
    }
}
if (function_exists('flosc_normalize_content_item_flow_settings')) {
    $flosc_flow_settings = flosc_normalize_content_item_flow_settings($flosc_flow_settings, $flosc_settings_key);
}

// v1.3.5: Preserve underscores in default slug (don't use sanitize_title which converts to hyphens)
$flosc_default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($flosc_selected_ivr, PATHINFO_FILENAME)));
if ($flosc_default_slug === '') {
    $flosc_default_slug = 'flosc';
}

// Seed empty options fully; also backfill status/slug when a partial option exists
// (e.g. IVR re-parse wrote messages first — empty() is false, old seed skipped).
$flosc_flow_seed_needed = false;
$flosc_shipped_name = function_exists('flosc_shipped_flow_display_name')
    ? flosc_shipped_flow_display_name($flosc_selected_ivr)
    : '';
$flosc_fallback_name = $flosc_shipped_name !== ''
    ? $flosc_shipped_name
    : ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $flosc_selected_ivr));

if (empty($flosc_flow_settings)) {
    $flosc_flow_settings = [
        'name' => $flosc_fallback_name,
        'title' => '',
        'tagline' => '',
        'slug' => $flosc_default_slug,
        'primary_color' => '#4f46e5',
        'status' => 'active',
    ];
    $flosc_flow_seed_needed = true;
} else {
    if (empty($flosc_flow_settings['slug']) || !is_string($flosc_flow_settings['slug'])) {
        $flosc_flow_settings['slug'] = $flosc_default_slug;
        $flosc_flow_seed_needed = true;
    }
    if (empty($flosc_flow_settings['status']) || !is_string($flosc_flow_settings['status'])) {
        $flosc_flow_settings['status'] = 'active';
        $flosc_flow_seed_needed = true;
    } elseif (!in_array($flosc_flow_settings['status'], ['active', 'draft'], true)) {
        $flosc_flow_settings['status'] = 'active';
        $flosc_flow_seed_needed = true;
    }
    // Upgrade ugly filename-stem names for shipped samples only (leave custom names alone).
    if ($flosc_shipped_name !== '') {
        $flosc_cur_name  = trim((string) ($flosc_flow_settings['name'] ?? ($flosc_flow_settings['identity']['name'] ?? '')));
        $flosc_stem_ugly = ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $flosc_selected_ivr));
        if ($flosc_cur_name === '' || strcasecmp($flosc_cur_name, $flosc_stem_ugly) === 0 || strcasecmp($flosc_cur_name, $flosc_default_slug) === 0) {
            $flosc_flow_settings['name'] = $flosc_shipped_name;
            if (isset($flosc_flow_settings['identity']) && is_array($flosc_flow_settings['identity'])) {
                $flosc_flow_settings['identity']['name'] = $flosc_shipped_name;
            }
            $flosc_flow_seed_needed = true;
        }
    }
}
if ($flosc_flow_seed_needed) {
    update_option($flosc_settings_key, $flosc_flow_settings);
}

$flosc_get = wp_unslash($_GET);
$flosc_post = wp_unslash($_POST);
$flosc_active_tab = isset($flosc_get['tab']) ? sanitize_text_field($flosc_get['tab']) : 'identity';
$flosc_can_manage_administration = current_user_can('manage_options');
if ($flosc_active_tab === 'administration' && !$flosc_can_view_administration) {
    $flosc_active_tab = 'identity';
}
$flosc_identity_view = isset($flosc_get['view']) ? sanitize_text_field($flosc_get['view']) : 'single';
if (!in_array($flosc_identity_view, ['single', 'all'], true)) {
    $flosc_identity_view = 'single';
}

// Set-as-default is handled on admin_post_flosc_set_default_flow (before admin HTML).
// Do not redirect here — headers are already sent during page render (white content pane).

// Trajectory quick-toggle: flip a trajectory post between LIVE (private) and OFF (draft).
if (isset($flosc_post['flosc_toggle_trajectory_post']) && wp_verify_nonce(sanitize_text_field($flosc_post['flosc_toggle_trajectory_nonce'] ?? ''), 'flosc_toggle_trajectory_post')) {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to toggle trajectory posts.', 'flosc'));
    }

    $flosc_trj_post_id = absint($flosc_post['flosc_toggle_trajectory_post']);
    $flosc_trj_set = sanitize_key($flosc_post['flosc_trajectory_set'] ?? '');
    $flosc_trj_next_status = ($flosc_trj_set === 'on') ? 'private' : 'draft';
    $flosc_trj_redirect_args = [
        'page' => 'flosc-settings',
        'ivr'  => $flosc_selected_ivr,
        'tab'  => 'trajectories',
    ];

    $flosc_trj_post = get_post($flosc_trj_post_id);
    $flosc_trj_valid = $flosc_trj_post instanceof WP_Post
        && class_exists('FLOSC_Trajectory')
        && FLOSC_Trajectory::is_trajectory_post($flosc_trj_post);

    if ($flosc_trj_valid) {
        $flosc_trj_cfg = FLOSC_Trajectory::config_from_post($flosc_trj_post);
        $flosc_trj_flow = sanitize_file_name((string) ($flosc_trj_cfg['flow'] ?? ''));
        if ($flosc_trj_flow === $flosc_selected_ivr) {
            $flosc_trj_update = wp_update_post([
                'ID' => $flosc_trj_post_id,
                'post_status' => $flosc_trj_next_status,
            ], true);

            if (!is_wp_error($flosc_trj_update)) {
                if ($flosc_trj_next_status === 'private') {
                    FLOSC_Trajectory::sync_post($flosc_trj_post_id);
                    $flosc_trj_redirect_args['trajectory_toggled'] = 'on';
                } else {
                    FLOSC_Trajectory::unsync_post($flosc_trj_post_id);
                    $flosc_trj_redirect_args['trajectory_toggled'] = 'off';
                }
            } else {
                $flosc_trj_redirect_args['trajectory_error'] = 'toggle_failed';
            }
        }
    }

    $flosc_trj_redirect_url = add_query_arg($flosc_trj_redirect_args, admin_url('admin.php'));
    wp_safe_redirect($flosc_trj_redirect_url);
    exit;
}

// Concierge quick-create: create a private concierge post and sync it to the flow.
if (isset($flosc_post['flosc_create_concierge_post']) && wp_verify_nonce(sanitize_text_field($flosc_post['flosc_concierge_create_nonce'] ?? ''), 'flosc_create_concierge_post')) {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to create concierge posts.', 'flosc'));
    }

    $flosc_cncrg_title = sanitize_text_field($flosc_post['flosc_cncrg_title'] ?? 'Concierge Note');
    $flosc_cncrg_flow = sanitize_file_name($flosc_post['flosc_cncrg_flow'] ?? $flosc_selected_ivr);
    $flosc_cncrg_keyword = sanitize_text_field($flosc_post['flosc_cncrg_keyword'] ?? '');
    $flosc_cncrg_password = sanitize_text_field($flosc_post['flosc_cncrg_password'] ?? '');
    $flosc_cncrg_success = sanitize_text_field($flosc_post['flosc_cncrg_success'] ?? '');
    $flosc_cncrg_max_tries = max(1, absint($flosc_post['flosc_cncrg_max_tries'] ?? 3));
    $flosc_cncrg_retry = sanitize_textarea_field($flosc_post['flosc_cncrg_retry'] ?? '');
    $flosc_cncrg_delivery = sanitize_textarea_field($flosc_post['flosc_cncrg_delivery'] ?? '');
    $flosc_cncrg_off_ramp_phrases = sanitize_textarea_field($flosc_post['flosc_cncrg_off_ramp_phrases'] ?? '');
    $flosc_cncrg_off_ramp_exactness = sanitize_key($flosc_post['flosc_cncrg_off_ramp_exactness'] ?? 'preferred');
    if (!in_array($flosc_cncrg_off_ramp_exactness, ['flexible', 'preferred', 'exact'], true)) {
        $flosc_cncrg_off_ramp_exactness = 'preferred';
    }
    $flosc_cncrg_content = sanitize_textarea_field($flosc_post['flosc_cncrg_content'] ?? '');

    if ($flosc_cncrg_title === '') {
        $flosc_cncrg_title = 'Concierge Note';
    }

    if ($flosc_cncrg_keyword === '' || $flosc_cncrg_content === '') {
        $flosc_redirect_url = add_query_arg([
            'page' => 'flosc-settings',
            'ivr'  => $flosc_selected_ivr,
            'tab'  => 'concierge',
            'concierge_error' => 'missing_required',
        ], admin_url('admin.php'));
        wp_safe_redirect($flosc_redirect_url);
        exit;
    }

    $flosc_internal_term = term_exists('flosc-internal', 'category');
    if (!$flosc_internal_term) {
        $flosc_internal_term = wp_insert_term('flosc-internal', 'category', ['slug' => 'flosc-internal']);
    }
    $flosc_internal_term_id = 0;
    if (is_array($flosc_internal_term)) {
        $flosc_internal_term_id = intval($flosc_internal_term['term_id'] ?? 0);
    } elseif (is_object($flosc_internal_term)) {
        $flosc_internal_term_id = intval($flosc_internal_term->term_id ?? 0);
    }

    $flosc_cncrg_term = term_exists('flosc-internal-concierge', 'category');
    if (!$flosc_cncrg_term) {
        $flosc_cncrg_term = wp_insert_term('concierge', 'category', [
            'slug' => 'flosc-internal-concierge',
            'parent' => $flosc_internal_term_id,
        ]);
    }
    $flosc_cncrg_term_id = 0;
    if (is_array($flosc_cncrg_term)) {
        $flosc_cncrg_term_id = intval($flosc_cncrg_term['term_id'] ?? 0);
    } elseif (is_object($flosc_cncrg_term)) {
        $flosc_cncrg_term_id = intval($flosc_cncrg_term->term_id ?? 0);
    }

    $flosc_new_post_id = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'private',
        'post_title' => $flosc_cncrg_title,
        'post_content' => $flosc_cncrg_content,
        'post_category' => array_values(array_filter([$flosc_internal_term_id, $flosc_cncrg_term_id])),
        'meta_input' => [
            '_flosc_concierge_flow' => $flosc_cncrg_flow,
            '_flosc_concierge_keyword' => $flosc_cncrg_keyword,
            '_flosc_concierge_password' => $flosc_cncrg_password,
            '_flosc_concierge_success' => $flosc_cncrg_success,
            '_flosc_concierge_max_tries' => (string) $flosc_cncrg_max_tries,
            '_flosc_concierge_retry' => $flosc_cncrg_retry,
            '_flosc_concierge_delivery' => $flosc_cncrg_delivery,
            '_flosc_concierge_off_ramp_phrases' => $flosc_cncrg_off_ramp_phrases,
            '_flosc_concierge_off_ramp_exactness' => $flosc_cncrg_off_ramp_exactness,
        ],
    ], true);

    if (!is_wp_error($flosc_new_post_id) && class_exists('FLOSC_Concierge')) {
        FLOSC_Concierge::sync_post($flosc_new_post_id);
    }

    $flosc_redirect_args = [
        'page' => 'flosc-settings',
        'ivr'  => $flosc_selected_ivr,
        'tab'  => 'concierge',
    ];
    if (is_wp_error($flosc_new_post_id)) {
        $flosc_redirect_args['concierge_error'] = 'create_failed';
    } else {
        $flosc_redirect_args['concierge_created'] = intval($flosc_new_post_id);
    }
    $flosc_redirect_url = add_query_arg($flosc_redirect_args, admin_url('admin.php'));
    wp_safe_redirect($flosc_redirect_url);
    exit;
}

// Trajectory quick-create: create a private trajectory post and sync trajectory guidance into flow DB.
if (isset($flosc_post['flosc_create_trajectory_post']) && wp_verify_nonce(sanitize_text_field($flosc_post['flosc_trajectory_create_nonce'] ?? ''), 'flosc_create_trajectory_post')) {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to create trajectory posts.', 'flosc'));
    }

    $flosc_trj_title = sanitize_text_field($flosc_post['flosc_trj_title'] ?? 'Trajectory Note');
    $flosc_trj_flow = sanitize_file_name($flosc_post['flosc_trj_flow'] ?? $flosc_selected_ivr);
    $flosc_trj_keywords = sanitize_text_field($flosc_post['flosc_trj_keywords'] ?? '');
    $flosc_trj_priority = max(0, min(100, absint($flosc_post['flosc_trj_priority'] ?? 50)));
    $flosc_trj_instructions = sanitize_textarea_field($flosc_post['flosc_trj_instructions'] ?? '');
    $flosc_trj_off_ramp_phrases = sanitize_textarea_field($flosc_post['flosc_trj_off_ramp_phrases'] ?? '');
    $flosc_trj_off_ramp_exactness = sanitize_key($flosc_post['flosc_trj_off_ramp_exactness'] ?? 'preferred');
    if (!in_array($flosc_trj_off_ramp_exactness, ['flexible', 'preferred', 'exact'], true)) {
        $flosc_trj_off_ramp_exactness = 'preferred';
    }

    if ($flosc_trj_title === '') {
        $flosc_trj_title = 'Trajectory Note';
    }

    if ($flosc_trj_instructions === '') {
        $flosc_redirect_url = add_query_arg([
            'page' => 'flosc-settings',
            'ivr'  => $flosc_selected_ivr,
            'tab'  => 'trajectories',
            'trajectory_error' => 'missing_required',
        ], admin_url('admin.php'));
        wp_safe_redirect($flosc_redirect_url);
        exit;
    }

    $flosc_internal_term = term_exists('flosc-internal', 'category');
    if (!$flosc_internal_term) {
        $flosc_internal_term = wp_insert_term('flosc-internal', 'category', ['slug' => 'flosc-internal']);
    }
    $flosc_internal_term_id = 0;
    if (is_array($flosc_internal_term)) {
        $flosc_internal_term_id = intval($flosc_internal_term['term_id'] ?? 0);
    } elseif (is_object($flosc_internal_term)) {
        $flosc_internal_term_id = intval($flosc_internal_term->term_id ?? 0);
    }

    $flosc_trj_term = term_exists('flosc-internal-trajectories', 'category');
    if (!$flosc_trj_term) {
        $flosc_trj_term_args = [
            'slug' => 'flosc-internal-trajectories',
            'parent' => $flosc_internal_term_id,
        ];
        $flosc_trj_term = wp_insert_term('trajectory', 'category', $flosc_trj_term_args);
    }

    $flosc_trj_term_id = 0;
    if (is_array($flosc_trj_term)) {
        $flosc_trj_term_id = intval($flosc_trj_term['term_id'] ?? 0);
    } elseif (is_object($flosc_trj_term)) {
        $flosc_trj_term_id = intval($flosc_trj_term->term_id ?? 0);
    }

    $flosc_new_post_id = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'private',
        'post_title' => $flosc_trj_title,
        'post_content' => $flosc_trj_instructions,
        'post_category' => array_values(array_filter([$flosc_internal_term_id, $flosc_trj_term_id])),
        'meta_input' => [
            '_flosc_trajectory_flow' => $flosc_trj_flow,
            '_flosc_trajectory_keywords' => $flosc_trj_keywords,
            '_flosc_trajectory_priority' => (string) $flosc_trj_priority,
            '_flosc_trajectory_instructions' => $flosc_trj_instructions,
            '_flosc_trajectory_off_ramp_phrases' => $flosc_trj_off_ramp_phrases,
            '_flosc_trajectory_off_ramp_exactness' => $flosc_trj_off_ramp_exactness,
        ],
    ], true);

    if (!is_wp_error($flosc_new_post_id) && class_exists('FLOSC_Trajectory')) {
        FLOSC_Trajectory::sync_post($flosc_new_post_id);
    }

    $flosc_redirect_args = [
        'page' => 'flosc-settings',
        'ivr'  => $flosc_selected_ivr,
        'tab'  => 'trajectories',
    ];
    if (is_wp_error($flosc_new_post_id)) {
        $flosc_redirect_args['trajectory_error'] = 'create_failed';
    } else {
        $flosc_redirect_args['trajectory_created'] = intval($flosc_new_post_id);
    }

    $flosc_redirect_url = add_query_arg($flosc_redirect_args, admin_url('admin.php'));
    wp_safe_redirect($flosc_redirect_url);
    exit;
}

// Handle save
if (isset($flosc_post['flosc_save']) && wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
    if (!flosc_flows()->can_access_flow_admin($flosc_selected_flow_id)) {
        wp_die(esc_html__('You do not have permission to save this FLOSC flow.', 'flosc'));
    }
    
    // Collect all POST data for this flow
    $flosc_new_settings = $flosc_flow_settings; // Start with existing
    
    // v1.5.0: Keys that contain multiline content (stored in flow settings via flow_ prefix)
    $flosc_textarea_flow_keys = [
        'sso_apple_private_key',
        // AI tab: brand facts are a multiline prompt injection — keep newlines.
        'ai_brand_facts',
        // Companion targeting
        'companion_target_include', 'companion_target_exclude', 'companion_trigger_suppress_path_patterns',
        // AI configuration
        'ai_base_prompt', 'ai_prompt_freeline', 'ai_prompt_login', 'ai_prompt_offer', 'ai_prompt_sale', 'ai_prompt_content',
        'phase_outcomes_freeline', 'phase_outcomes_login', 'phase_outcomes_offer', 'phase_outcomes_sale', 'phase_outcomes_content',
        // AI knowledge
        'ai_personality_traits', 'ai_mission', 'ai_context_awareness', 'ai_freeline_restrictions', 'ai_member_access', 'ai_boundaries',
        'ai_topic_scope', 'ai_off_topic_message', 'ai_off_topic_links',
        'ai_accuracy_test_questions',
        // Email
        'email_body',
        // Guest email bodies — follow-up slots are guest_followup_N_body (legacy guest_day*_body still accepted).
        'guest_welcome_body',
        'guest_followup_1_body', 'guest_followup_2_body', 'guest_followup_3_body',
        'guest_day10_body', 'guest_day20_body', 'guest_day28_body',
        // Payments
        'manual_payment_instructions',
        // Identity policy pages
        'privacy_policy_content',
        'terms_of_service_content',
        'data_deletion_content',
        'platform_compliance_content',
        // Guest / chat list copy
        'guest_new_chat_limit_message',
        'guest_new_chat_welcome_message',
        'member_new_chat_welcome_message',
        'guest_link_welcome_message',
        'member_link_welcome_message',
        'user_status_visitor',
        'user_status_guest',
        'user_status_member',
        'user_status_member_level',
        'user_status_admin',
    ];
    $flosc_identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
    
    foreach ($flosc_post as $flosc_key => $flosc_value) {
        if (strpos($flosc_key, 'flow_') === 0) {
            $flosc_setting_key = substr($flosc_key, 5); // Remove 'flow_' prefix
            // Check static textarea keys OR dynamic quiz content/template keys
            $flosc_is_textarea = in_array($flosc_setting_key, $flosc_textarea_flow_keys, true)
                || strpos($flosc_setting_key, 'quiz_content_') === 0
                || strpos($flosc_setting_key, '_template_') !== false
                || substr($flosc_setting_key, -5) === '_body'; // email bodies (guest/member/newsletter) — preserve newlines
            if (in_array($flosc_setting_key, $flosc_identity_html_keys, true)) {
                $flosc_new_settings[$flosc_setting_key] = wp_kses_post($flosc_value);
            } elseif ($flosc_setting_key === 'ai_base_prompt' && function_exists('flosc_sanitize_personality_profile_text')) {
                $flosc_new_settings[$flosc_setting_key] = flosc_sanitize_personality_profile_text(is_string($flosc_value) ? $flosc_value : '');
            } elseif ($flosc_is_textarea) {
                $flosc_new_settings[$flosc_setting_key] = sanitize_textarea_field($flosc_value);
            } elseif (is_array($flosc_value)) {
                $flosc_new_settings[$flosc_setting_key] = array_map('sanitize_text_field', $flosc_value);
            } else {
                $flosc_new_settings[$flosc_setting_key] = sanitize_text_field($flosc_value);
            }
        }
    }
    
    // v1.5.0: Handle checkbox unchecking — checkboxes don't POST when unchecked
    // Only handle checkboxes for the current tab to avoid wiping other tabs' values
    if ($flosc_active_tab === 'sso') {
        $flosc_sso_providers = ['google', 'apple', 'facebook', 'microsoft', 'linkedin'];
        foreach ($flosc_sso_providers as $flosc_provider) {
            if (!isset($flosc_post["flow_sso_{$flosc_provider}_enabled"])) {
                $flosc_new_settings["sso_{$flosc_provider}_enabled"] = '';
            }
        }
    }
    if ($flosc_active_tab === 'login') {
        // MagicLink per-flow enable (default off). Unchecked checkboxes omit POST key.
        if (!isset($flosc_post['flow_magic_access_links_enabled'])) {
            $flosc_new_settings['magic_access_links_enabled'] = '';
        }
        if (isset($flosc_new_settings['guest_link_max_uses'])) {
            $flosc_new_settings['guest_link_max_uses'] = max(1, min(100, intval($flosc_new_settings['guest_link_max_uses'])));
        }
        if (isset($flosc_new_settings['guest_link_window_days'])) {
            $flosc_new_settings['guest_link_window_days'] = max(1, min(365, intval($flosc_new_settings['guest_link_window_days'])));
        }

        // v10.0.0: WP native-auth takeover + login/logout destinations (per-flow).
        // Checkboxes omit their POST key when unchecked — persist '' explicitly.
        if (!isset($flosc_post['flow_takeover_wp_auth'])) {
            $flosc_new_settings['takeover_wp_auth'] = '';
        }
        // Store the global fallback option (non-flow) so non-BuddyBoss installs
        // have a working default without an IVR-flow settings bag.
        if (isset($flosc_new_settings['login_destination']) && $flosc_new_settings['login_destination'] !== '') {
            update_option('flosc_login_destination', esc_url_raw($flosc_new_settings['login_destination']));
        }
        foreach (['login_destination', 'logout_destination', 'login_destination_accounts_url', 'logout_destination_fallback'] as $flosc_url_key) {
            if (isset($flosc_new_settings[$flosc_url_key])) {
                $flosc_new_settings[$flosc_url_key] = esc_url_raw($flosc_new_settings[$flosc_url_key]);
            }
        }
        $flosc_mode_login = sanitize_key((string) ($flosc_new_settings['login_destination_mode'] ?? 'auto'));
        $flosc_new_settings['login_destination_mode'] = in_array($flosc_mode_login, array('auto', 'buddyboss_profile', 'core_profile', 'custom_url'), true)
            ? $flosc_mode_login : 'auto';
        $flosc_mode_logout = sanitize_key((string) ($flosc_new_settings['logout_destination_mode'] ?? 'entry_flow'));
        $flosc_new_settings['logout_destination_mode'] = in_array($flosc_mode_logout, array('entry_flow', 'fallback', 'flow'), true)
            ? $flosc_mode_logout : 'entry_flow';
        if (isset($flosc_new_settings['logout_farewell_message'])) {
            $flosc_new_settings['logout_farewell_message'] = sanitize_textarea_field($flosc_new_settings['logout_farewell_message']);
        }
    }
    if ($flosc_active_tab === 'engagement') {
        if (isset($flosc_new_settings['guest_access_days'])) {
            $flosc_new_settings['guest_access_days'] = max(0, min(365, intval($flosc_new_settings['guest_access_days'])));
        }
        // engagement_rules: when → condition → then (chat and/or email). Not offers.
        $flosc_r_ids   = (array) ($flosc_post['engagement_rule_id'] ?? []);
        $flosc_r_aud   = (array) ($flosc_post['engagement_rule_audience'] ?? []);
        $flosc_r_title = (array) ($flosc_post['engagement_rule_title'] ?? []);
        $flosc_r_trig  = (array) ($flosc_post['engagement_rule_trigger'] ?? []);
        $flosc_r_days  = (array) ($flosc_post['engagement_rule_days'] ?? []);
        $flosc_r_cond  = (array) ($flosc_post['engagement_rule_condition'] ?? []);
        $flosc_r_chat  = (array) ($flosc_post['engagement_rule_chat_message'] ?? []);
        $flosc_r_etpl  = (array) ($flosc_post['engagement_rule_email_template'] ?? []);
        $flosc_r_en    = (array) ($flosc_post['engagement_rule_enabled'] ?? []);
        $flosc_r_achat = (array) ($flosc_post['engagement_rule_action_chat'] ?? []);
        $flosc_r_aemail = (array) ($flosc_post['engagement_rule_action_email'] ?? []);
        $flosc_rules_out = [];
        $flosc_allow_trig = ['chat_open', 'return_login', 'inactive_days', 'days_since_registration', 'profile_incomplete'];
        $flosc_allow_tpl  = function_exists( 'flosc_guest_followup_template_ids' )
            ? flosc_guest_followup_template_ids()
            : [ '', 'reengagement', 'guest_welcome', 'guest_followup_1', 'guest_followup_2', 'guest_followup_3', 'guest_day10', 'guest_day20', 'guest_day28' ];
        foreach ($flosc_r_ids as $flosc_ri => $flosc_rid) {
            $flosc_rid = sanitize_key((string) $flosc_rid);
            if ($flosc_rid === '') {
                $flosc_rid = 'rule_' . (int) $flosc_ri;
            }
            $flosc_aud = sanitize_key((string) ($flosc_r_aud[$flosc_ri] ?? 'guest'));
            if (!in_array($flosc_aud, ['visitor', 'guest', 'member'], true)) {
                $flosc_aud = 'guest';
            }
            $flosc_trig = sanitize_key((string) ($flosc_r_trig[$flosc_ri] ?? 'inactive_days'));
            if (!in_array($flosc_trig, $flosc_allow_trig, true)) {
                $flosc_trig = 'inactive_days';
            }
            $flosc_tpl = sanitize_key((string) ($flosc_r_etpl[$flosc_ri] ?? ''));
            if (!in_array($flosc_tpl, $flosc_allow_tpl, true)) {
                $flosc_tpl = '';
            }
            $flosc_rtitle = sanitize_text_field((string) ($flosc_r_title[$flosc_ri] ?? ''));
            if ($flosc_rtitle === '') {
                $flosc_rtitle = $flosc_rid;
            }
            $flosc_rules_out[] = [
                'id'             => $flosc_rid,
                'title'          => $flosc_rtitle,
                'audience'       => $flosc_aud,
                'enabled'        => !empty($flosc_r_en[(string) $flosc_ri]) || !empty($flosc_r_en[$flosc_ri]) ? '1' : '',
                'trigger'        => $flosc_trig,
                'trigger_days'   => (string) max(0, min(365, intval($flosc_r_days[$flosc_ri] ?? 0))),
                'condition'      => sanitize_text_field((string) ($flosc_r_cond[$flosc_ri] ?? '')),
                'action_chat'    => !empty($flosc_r_achat[(string) $flosc_ri]) || !empty($flosc_r_achat[$flosc_ri]) ? '1' : '',
                'action_email'   => !empty($flosc_r_aemail[(string) $flosc_ri]) || !empty($flosc_r_aemail[$flosc_ri]) ? '1' : '',
                'email_template' => $flosc_tpl,
                'chat_message'   => sanitize_textarea_field((string) ($flosc_r_chat[$flosc_ri] ?? '')),
            ];
        }
        $flosc_new_settings['engagement_rules'] = $flosc_rules_out;

        // Keep legacy re-engagement keys in sync with first enabled inactive+email rule (email cron still uses them).
        $flosc_reeng_days = 7;
        $flosc_reeng_on   = '';
        foreach ($flosc_rules_out as $flosc_rr) {
            if (empty($flosc_rr['enabled']) || empty($flosc_rr['action_email'])) {
                continue;
            }
            if (($flosc_rr['trigger'] ?? '') !== 'inactive_days') {
                continue;
            }
            if (($flosc_rr['email_template'] ?? '') !== 'reengagement' && ($flosc_rr['email_template'] ?? '') !== '') {
                // still count as reeng if inactive email of any template
            }
            $flosc_reeng_on = '1';
            $flosc_reeng_days = max(1, min(365, intval($flosc_rr['trigger_days'] ?? 7)));
            break;
        }
        $flosc_new_settings['email_reengagement_enabled'] = $flosc_reeng_on;
        $flosc_new_settings['email_reengagement_days']    = $flosc_reeng_days;
    }
    if ($flosc_active_tab === 'ai') {
        foreach (['ai_enable_ivr_context', 'ai_enable_content_access', 'ai_enable_chaining'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$flosc_cb}"])) {
                $flosc_new_settings[$flosc_cb] = '';
            }
        }
    }
    if ($flosc_active_tab === 'knowledge-base' && $flosc_identity_view === 'single') {
        if (!isset($flosc_post['flow_knowledge_base_ids']) || !is_array($flosc_post['flow_knowledge_base_ids'])) {
            $flosc_new_settings['knowledge_base_ids'] = array();
        } else {
            $flosc_kb_ids = array();
            foreach ($flosc_post['flow_knowledge_base_ids'] as $flosc_kb_posted) {
                $flosc_kb_one = sanitize_key((string) $flosc_kb_posted);
                if ($flosc_kb_one !== '' && !in_array($flosc_kb_one, $flosc_kb_ids, true)) {
                    $flosc_kb_ids[] = $flosc_kb_one;
                }
            }
            $flosc_new_settings['knowledge_base_ids'] = $flosc_kb_ids;
        }
    }
    if ($flosc_active_tab === 'email') {
        foreach (['email_on_quiz_complete', 'email_reengagement_enabled', 'email_weekly_summary'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$flosc_cb}"])) {
                $flosc_new_settings[$flosc_cb] = '';
            }
        }
        // Follow-up repeaters (newsletter + member per level): store as <prefix>_followups arrays.
        // Day offsets and the number of follow-ups are per-flow parameters, not hardcoded stages.
        $flosc_fu_prefixes = ['newsletter'];
        foreach ((array) ($flosc_flow_settings['member_levels'] ?? []) as $flosc_lk => $flosc_lv) {
            $flosc_slug = sanitize_key($flosc_lv['slug'] ?? $flosc_lk);
            if ($flosc_slug !== '') { $flosc_fu_prefixes[] = 'member_' . $flosc_slug; }
        }
        foreach ($flosc_fu_prefixes as $flosc_pfx) {
            $flosc_days = (array) ($flosc_post[$flosc_pfx . '_fu_day'] ?? []);
            $flosc_subs = (array) ($flosc_post[$flosc_pfx . '_fu_subject'] ?? []);
            $flosc_bods = (array) ($flosc_post[$flosc_pfx . '_fu_body'] ?? []);
            $flosc_rows = [];
            foreach ($flosc_days as $flosc_i => $flosc_d) {
                $flosc_subject = sanitize_text_field($flosc_subs[$flosc_i] ?? '');
                $flosc_body    = sanitize_textarea_field($flosc_bods[$flosc_i] ?? '');
                if ($flosc_subject === '' && $flosc_body === '') { continue; }
                $flosc_rows[] = [
                    'day'     => max(0, min(365, (int) $flosc_d)),
                    'subject' => $flosc_subject,
                    'body'    => $flosc_body,
                ];
            }
            $flosc_new_settings[$flosc_pfx . '_followups'] = $flosc_rows;
        }
    }
    if ($flosc_active_tab === 'contact-form') {
        $flosc_new_settings['contact_form_title'] = sanitize_text_field((string) ($flosc_new_settings['contact_form_title'] ?? ''));
        $flosc_new_settings['contact_form_intro'] = sanitize_text_field((string) ($flosc_new_settings['contact_form_intro'] ?? ''));
        $flosc_new_settings['contact_form_submit_text'] = sanitize_text_field((string) ($flosc_new_settings['contact_form_submit_text'] ?? ''));
        $flosc_new_settings['contact_form_success_message'] = sanitize_text_field((string) ($flosc_new_settings['contact_form_success_message'] ?? ''));
        $flosc_new_settings['contact_form_forward_to_email'] = sanitize_email((string) ($flosc_new_settings['contact_form_forward_to_email'] ?? ''));
        $flosc_new_settings['contact_form_email_subject'] = sanitize_text_field((string) ($flosc_new_settings['contact_form_email_subject'] ?? ''));
        $flosc_new_settings['contact_form_min_submit_seconds'] = max(2, intval($flosc_new_settings['contact_form_min_submit_seconds'] ?? 4));
        $flosc_new_settings['contact_form_max_submissions_per_hour'] = max(1, intval($flosc_new_settings['contact_form_max_submissions_per_hour'] ?? 3));
        $flosc_new_settings['contact_form_duplicate_window_minutes'] = max(1, intval($flosc_new_settings['contact_form_duplicate_window_minutes'] ?? 30));
        $flosc_new_settings['contact_form_message_font_size'] = max(16, intval($flosc_new_settings['contact_form_message_font_size'] ?? 20));

        foreach (['contact_form_button_bg_color', 'contact_form_button_text_color', 'contact_form_card_background', 'contact_form_accent_color'] as $flosc_color_key) {
            $flosc_color_value = sanitize_hex_color((string) ($flosc_new_settings[$flosc_color_key] ?? ''));
            if ($flosc_color_value !== null) {
                $flosc_new_settings[$flosc_color_key] = $flosc_color_value;
            }
        }

        $flosc_font_family = trim((string) ($flosc_new_settings['contact_form_message_font_family'] ?? "'Courier New', Courier, monospace"));
        $flosc_new_settings['contact_form_message_font_family'] = preg_replace('/[^a-zA-Z0-9\s,\-\'\"]/', '', $flosc_font_family);
    }
    if ($flosc_active_tab === 'payments') {
        foreach (['stripe_enabled', 'paypal_enabled', 'manual_payments_enabled'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$flosc_cb}"])) {
                $flosc_new_settings[$flosc_cb] = '';
            }
        }
    }
    if ($flosc_active_tab === 'token-management') {
        if (!isset($flosc_post['flow_chat_token_enforcement'])) {
            $flosc_new_settings['chat_token_enforcement'] = '';
        }
        $flosc_decimal_to_rational = static function ($flosc_raw_value) {
            $flosc_normalized = str_replace(',', '.', trim((string) $flosc_raw_value));
            if ($flosc_normalized === '') {
                return [1, 1];
            }
            if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $flosc_normalized)) {
                $flosc_numeric = floatval($flosc_normalized);
                $flosc_normalized = number_format($flosc_numeric, 3, '.', '');
            }
            $flosc_parts = explode('.', $flosc_normalized, 2);
            $flosc_whole = max(0, intval($flosc_parts[0] ?? '0'));
            $flosc_fraction = substr(($flosc_parts[1] ?? '') . '000', 0, 3);
            $flosc_num = ($flosc_whole * 1000) + intval($flosc_fraction);
            $flosc_den = 1000;
            if ($flosc_num < 1) {
                $flosc_num = 1;
            }
            if (function_exists('gcd')) {
                $flosc_divisor = gcd($flosc_num, $flosc_den);
            } else {
                $flosc_a = $flosc_num;
                $flosc_b = $flosc_den;
                while ($flosc_b !== 0) {
                    $flosc_tmp = $flosc_a % $flosc_b;
                    $flosc_a = $flosc_b;
                    $flosc_b = $flosc_tmp;
                }
                $flosc_divisor = max(1, $flosc_a);
            }
            return [max(1, intval($flosc_num / $flosc_divisor)), max(1, intval($flosc_den / $flosc_divisor))];
        };
        if (isset($flosc_new_settings['tokens_nominal_millicents_per_token_decimal'])) {
            [$flosc_nom_num, $flosc_nom_den] = $flosc_decimal_to_rational($flosc_new_settings['tokens_nominal_millicents_per_token_decimal']);
            $flosc_new_settings['tokens_nominal_millicents_per_token_numerator'] = $flosc_nom_num;
            $flosc_new_settings['tokens_nominal_millicents_per_token_denominator'] = $flosc_nom_den;
        }
        if (isset($flosc_new_settings['tokens_real_millicents_per_token_decimal'])) {
            [$flosc_real_num, $flosc_real_den] = $flosc_decimal_to_rational($flosc_new_settings['tokens_real_millicents_per_token_decimal']);
            $flosc_new_settings['tokens_real_millicents_per_token_numerator'] = $flosc_real_num;
            $flosc_new_settings['tokens_real_millicents_per_token_denominator'] = $flosc_real_den;
        }
        unset($flosc_new_settings['tokens_nominal_millicents_per_token_decimal'], $flosc_new_settings['tokens_real_millicents_per_token_decimal']);
        $flosc_token_int_fields = [
            'tokens_communication_tokens_per_message',
            'tokens_nominal_millicents_per_token_numerator',
            'tokens_nominal_millicents_per_token_denominator',
            'tokens_real_millicents_per_token_numerator',
            'tokens_real_millicents_per_token_denominator',
        ];
        foreach ($flosc_token_int_fields as $flosc_field) {
            if (!isset($flosc_new_settings[$flosc_field])) {
                continue;
            }
            $flosc_new_settings[$flosc_field] = max(1, intval($flosc_new_settings[$flosc_field]));
        }
        if (isset($flosc_new_settings['guest_token_grant'])) {
            $flosc_new_settings['guest_token_grant'] = max(0, intval($flosc_new_settings['guest_token_grant']));
        }
        if (isset($flosc_new_settings['member_token_grant'])) {
            $flosc_new_settings['member_token_grant'] = max(0, intval($flosc_new_settings['member_token_grant']));
        }
        // Product token parameters (Token Management). Also keep legacy subscription_* in sync when present.
        foreach ([
            'product_token_grant_onetime',
            'product_token_grant_recurring',
            'product_token_grant_recurring_yearly',
            'product_token_cap',
            'subscription_monthly_token_grant',
            'subscription_yearly_token_grant',
            'subscription_token_cap',
        ] as $flosc_product_token_field) {
            if (isset($flosc_new_settings[$flosc_product_token_field])) {
                $flosc_new_settings[$flosc_product_token_field] = max(0, intval($flosc_new_settings[$flosc_product_token_field]));
            }
        }
        // Mirror new product_* into legacy keys so older readers keep working.
        if (isset($flosc_new_settings['product_token_grant_recurring'])) {
            $flosc_new_settings['subscription_monthly_token_grant'] = $flosc_new_settings['product_token_grant_recurring'];
        }
        if (isset($flosc_new_settings['product_token_grant_recurring_yearly'])) {
            $flosc_new_settings['subscription_yearly_token_grant'] = $flosc_new_settings['product_token_grant_recurring_yearly'];
        }
        if (isset($flosc_new_settings['product_token_cap'])) {
            $flosc_new_settings['subscription_token_cap'] = $flosc_new_settings['product_token_cap'];
        }

        // Per-product token grants from the Token Management accordion UI.
        // Stored on each offer as offers[id].tokens { source, mode, amount, cap, cap_mode }.
        if (isset($flosc_post['flosc_product_tokens']) && is_array($flosc_post['flosc_product_tokens'])) {
            $flosc_offers = is_array($flosc_new_settings['offers'] ?? null) ? $flosc_new_settings['offers'] : [];
            foreach ($flosc_post['flosc_product_tokens'] as $flosc_pt_id => $flosc_pt_row) {
                $flosc_pt_id = sanitize_key((string) $flosc_pt_id);
                if ($flosc_pt_id === '' || !is_array($flosc_pt_row)) {
                    continue;
                }
                if (!isset($flosc_offers[$flosc_pt_id]) || !is_array($flosc_offers[$flosc_pt_id])) {
                    // Offer may be keyed by original id inside the array.
                    $flosc_found = false;
                    foreach ($flosc_offers as $flosc_ok => $flosc_ov) {
                        if (!is_array($flosc_ov)) {
                            continue;
                        }
                        $flosc_inner_id = sanitize_key((string) ($flosc_ov['id'] ?? $flosc_ok));
                        if ($flosc_inner_id === $flosc_pt_id) {
                            $flosc_pt_id = is_string($flosc_ok) ? $flosc_ok : $flosc_pt_id;
                            $flosc_found = true;
                            break;
                        }
                    }
                    if (!$flosc_found) {
                        continue;
                    }
                }

                $flosc_source = sanitize_key((string) ($flosc_pt_row['source'] ?? 'flow'));
                if (!in_array($flosc_source, ['flow', 'custom', 'none'], true)) {
                    $flosc_source = 'flow';
                }
                $flosc_mode = sanitize_key((string) ($flosc_pt_row['mode'] ?? 'onetime'));
                if (!in_array($flosc_mode, ['onetime', 'recurring', 'recurring_yearly'], true)) {
                    $flosc_mode = 'onetime';
                }
                $flosc_cap_mode = sanitize_key((string) ($flosc_pt_row['cap_mode'] ?? 'flow'));
                if (!in_array($flosc_cap_mode, ['flow', 'none', 'custom'], true)) {
                    $flosc_cap_mode = 'flow';
                }

                $flosc_tokens = is_array($flosc_offers[$flosc_pt_id]['tokens'] ?? null)
                    ? $flosc_offers[$flosc_pt_id]['tokens']
                    : [];
                $flosc_tokens['source'] = $flosc_source;
                $flosc_tokens['mode'] = $flosc_mode;
                $flosc_tokens['cap_mode'] = $flosc_cap_mode;

                if ($flosc_source === 'none') {
                    $flosc_tokens['amount'] = 0;
                    $flosc_tokens['cap'] = 0;
                } elseif ($flosc_source === 'custom') {
                    if (isset($flosc_pt_row['amount']) && $flosc_pt_row['amount'] !== '') {
                        $flosc_tokens['amount'] = max(0, intval($flosc_pt_row['amount']));
                    } else {
                        unset($flosc_tokens['amount']); // inherit flow default for mode
                    }
                    if ($flosc_cap_mode === 'none') {
                        $flosc_tokens['cap'] = 0;
                    } elseif ($flosc_cap_mode === 'custom' && isset($flosc_pt_row['cap']) && $flosc_pt_row['cap'] !== '') {
                        $flosc_tokens['cap'] = max(0, intval($flosc_pt_row['cap']));
                    } else {
                        unset($flosc_tokens['cap']); // flow cap
                    }
                } else {
                    // flow defaults — clear overrides so runtime uses flow params
                    unset($flosc_tokens['amount'], $flosc_tokens['cap'], $flosc_tokens['bonus']);
                    $flosc_tokens['cap_mode'] = 'flow';
                }

                $flosc_offers[$flosc_pt_id]['tokens'] = $flosc_tokens;
            }
            $flosc_new_settings['offers'] = $flosc_offers;
        }
        if (isset($flosc_new_settings['visitor_low_token_threshold'])) {
            $flosc_new_settings['visitor_low_token_threshold'] = max(0, intval($flosc_new_settings['visitor_low_token_threshold']));
        }
        if (isset($flosc_new_settings['chat_token_enforcement'])) {
            $flosc_new_settings['chat_token_enforcement'] = !empty($flosc_new_settings['chat_token_enforcement']) ? '1' : '';
        }
        if (isset($flosc_new_settings['visitor_tokens_depleted_message'])) {
            $flosc_msg = sanitize_text_field((string) $flosc_new_settings['visitor_tokens_depleted_message']);
            $flosc_new_settings['visitor_tokens_depleted_message'] = trim($flosc_msg);
        }
        if (isset($flosc_new_settings['visitor_low_tokens_message'])) {
            $flosc_low_msg = sanitize_text_field((string) $flosc_new_settings['visitor_low_tokens_message']);
            $flosc_new_settings['visitor_low_tokens_message'] = trim($flosc_low_msg);
        }
        if (isset($flosc_new_settings['visitor_session_end_redirect_url'])) {
            $flosc_redirect = trim((string) $flosc_new_settings['visitor_session_end_redirect_url']);
            if ($flosc_redirect !== '' && strpos($flosc_redirect, '/') === 0 && strpos($flosc_redirect, '//') !== 0) {
                $flosc_redirect = home_url($flosc_redirect);
            }
            $flosc_new_settings['visitor_session_end_redirect_url'] = ($flosc_redirect === '')
                ? ''
                : esc_url_raw($flosc_redirect, ['http', 'https']);
        }
        if (isset($flosc_new_settings['visitor_depleted_contact_mode'])) {
            $flosc_mode = sanitize_key((string) $flosc_new_settings['visitor_depleted_contact_mode']);
            $flosc_new_settings['visitor_depleted_contact_mode'] = in_array($flosc_mode, ['message', 'in_chat_form'], true)
                ? $flosc_mode
                : 'message';
        }
    }
    if ($flosc_active_tab === 'quiz') {
        foreach (['wpq_integration', 'ld_integration', 'qsm_integration'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$flosc_cb}"])) {
                $flosc_new_settings[$flosc_cb] = '';
            }
        }
        if (!isset($flosc_post['flow_enabled_quizzes'])) {
            $flosc_new_settings['enabled_quizzes'] = [];
        }
        foreach (['A', 'B', 'C', 'D', 'E'] as $flosc_letter) {
            if (!isset($flosc_post["flow_quiz_variant_{$flosc_letter}_enabled"])) {
                $flosc_new_settings["quiz_variant_{$flosc_letter}_enabled"] = '';
            }
        }
        // Audio quiz escape hatch checkboxes (unchecked = omit POST key).
        foreach (['audio_quiz_escape_enabled', 'audio_quiz_escape_once'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$flosc_cb}"])) {
                $flosc_new_settings[$flosc_cb] = '';
            }
        }
        if (isset($flosc_new_settings['audio_quiz_escape_after_phrase'])) {
            $flosc_new_settings['audio_quiz_escape_after_phrase'] = max(
                0,
                min(99, intval($flosc_new_settings['audio_quiz_escape_after_phrase']))
            );
        }
    }
    if ($flosc_active_tab === 'style') {
        // Visitor visibility uses 0/1 so "off" is not confused with unset (sales default = on).
        $flosc_new_settings['companion_show_for_visitors'] = isset($flosc_post['flow_companion_show_for_visitors']) ? 1 : 0;

        foreach (['companion_enabled', 'companion_pass_page_context', 'companion_auto_open_enabled', 'companion_auto_open_once_per_session', 'companion_launch_on_exit_intent', 'companion_launch_on_scroll_threshold', 'companion_trigger_desktop_only', 'companion_trigger_suppress_on_auth_checkout', 'companion_focus_on_open', 'companion_allow_escape_close', 'companion_enable_keyboard_shortcut', 'companion_remember_open_state'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$flosc_cb}"])) {
                $flosc_new_settings[$flosc_cb] = '';
            }
        }

        $flosc_routing_mode = sanitize_key((string) ($flosc_new_settings['companion_routing_mode'] ?? 'hub'));
        if (!in_array($flosc_routing_mode, ['hub', 'domain_persistence'], true)) {
            $flosc_routing_mode = 'hub';
        }
        $flosc_new_settings['companion_routing_mode'] = $flosc_routing_mode;

        // Hub URLs + iframe slug: resolve defaults from this flow's own parameters only.
        $flosc_hub_source = array_merge(
            is_array($flosc_flow_settings) ? $flosc_flow_settings : [],
            is_array($flosc_new_settings) ? $flosc_new_settings : []
        );
        $flosc_hub_defaults = function_exists('flosc_companion_hub_defaults_from_flow')
            ? flosc_companion_hub_defaults_from_flow($flosc_hub_source)
            : [
                'fullscreen' => home_url('/'),
                'companion'  => home_url('/'),
                'chat_app'   => home_url('/'),
                'flow_slug'  => sanitize_title((string) ($flosc_hub_source['slug'] ?? '')),
            ];

        $flosc_hub_fullscreen_url = trim((string) ($flosc_new_settings['companion_hub_fullscreen_url'] ?? ''));
        if ($flosc_hub_fullscreen_url === '') {
            $flosc_hub_fullscreen_url = (string) ($flosc_hub_defaults['fullscreen'] ?? home_url('/'));
        }
        $flosc_hub_fullscreen_url = esc_url_raw($flosc_hub_fullscreen_url, ['http', 'https']);
        if ($flosc_hub_fullscreen_url === '') {
            $flosc_hub_fullscreen_url = esc_url_raw((string) ($flosc_hub_defaults['fullscreen'] ?? home_url('/')), ['http', 'https']);
        }
        $flosc_new_settings['companion_hub_fullscreen_url'] = $flosc_hub_fullscreen_url;

        $flosc_hub_companion_url = trim((string) ($flosc_new_settings['companion_hub_companion_url'] ?? ''));
        if ($flosc_hub_companion_url === '') {
            $flosc_hub_companion_url = (string) ($flosc_hub_defaults['companion'] ?? home_url('/'));
        }
        $flosc_hub_companion_url = esc_url_raw($flosc_hub_companion_url, ['http', 'https']);
        if ($flosc_hub_companion_url === '') {
            $flosc_hub_companion_url = esc_url_raw((string) ($flosc_hub_defaults['companion'] ?? home_url('/')), ['http', 'https']);
        }
        $flosc_new_settings['companion_hub_companion_url'] = $flosc_hub_companion_url;

        $flosc_companion_flow_slug = sanitize_title((string) ($flosc_new_settings['companion_flow_slug'] ?? ''));
        if ($flosc_companion_flow_slug === '') {
            $flosc_companion_flow_slug = sanitize_title((string) ($flosc_hub_defaults['flow_slug'] ?? ''));
        }
        $flosc_new_settings['companion_flow_slug'] = $flosc_companion_flow_slug;

        // Recompute chat_app default after slug is finalized.
        $flosc_hub_source['companion_flow_slug'] = $flosc_companion_flow_slug;
        $flosc_hub_defaults = function_exists('flosc_companion_hub_defaults_from_flow')
            ? flosc_companion_hub_defaults_from_flow($flosc_hub_source)
            : $flosc_hub_defaults;

        $flosc_chat_app_url = trim((string) ($flosc_new_settings['companion_chat_app_url'] ?? ''));
        if ($flosc_chat_app_url === '') {
            $flosc_chat_app_url = (string) ($flosc_hub_defaults['chat_app'] ?? home_url('/'));
        }
        $flosc_chat_app_url = esc_url_raw($flosc_chat_app_url, ['http', 'https']);
        if ($flosc_chat_app_url === '') {
            $flosc_chat_app_url = esc_url_raw((string) ($flosc_hub_defaults['chat_app'] ?? home_url('/')), ['http', 'https']);
        }
        $flosc_new_settings['companion_chat_app_url'] = $flosc_chat_app_url;

        // Normalize companion settings to filterable enums/defaults for forward compatibility.
        $flosc_companion_defaults = [
            'mode' => 'in_chat',
            'position' => 'bottom-right',
            'greeting' => 'Chat with us',
            'subtitle' => 'We reply instantly',
            'accent_color' => '#6366f1',
            'panel_width' => 380,
            'panel_height' => 560,
            'launcher_size' => 60,
            'launcher_icon' => 'chat',
            'mobile_behavior' => 'fullscreen',
            'context_scope' => 'basic',
            'auto_open_delay_ms' => 1500,
            'launch_on_scroll_percent' => 0,
            'trigger_min_page_time_ms' => 0,
            'motion_mode' => 'system',
            'keyboard_shortcut_key' => 'k',
            'launcher_aria_label' => 'Open Chat',
            'close_aria_label' => 'Collapse Chat',
            'state_storage' => 'session',
            'trigger_cooldown_ms' => 0,
            'profile_tier_visitor' => 'V',
            'profile_tier_guest' => 'G',
            'profile_tier_member' => 'M',
            'profile_tier_visitor_label' => 'Visitor',
            'profile_tier_guest_label' => 'Guest',
            'profile_tier_member_label' => 'Member',
            'contextual_prompt' => 'What do you want to explore together?',
        ];
        $flosc_companion_defaults = wp_parse_args(
            apply_filters('flosc_companion_defaults', $flosc_companion_defaults),
            $flosc_companion_defaults
        );
        $flosc_companion_numeric_limits = [
            'panel_width_min' => 280,
            'panel_width_max' => 900,
            'panel_height_min' => 320,
            'panel_height_max' => 1200,
            'launcher_size_min' => 44,
            'launcher_size_max' => 96,
            'auto_open_delay_min_ms' => 0,
            'auto_open_delay_max_ms' => 60000,
            'scroll_percent_min' => 0,
            'scroll_percent_max' => 100,
            'trigger_min_page_time_min_ms' => 0,
            'trigger_min_page_time_max_ms' => 120000,
            'trigger_cooldown_min_ms' => 0,
            'trigger_cooldown_max_ms' => 86400000,
        ];
        $flosc_companion_numeric_limits = wp_parse_args(
            apply_filters('flosc_companion_numeric_limits', $flosc_companion_numeric_limits),
            $flosc_companion_numeric_limits
        );
        foreach ($flosc_companion_numeric_limits as $flosc_limit_key => $flosc_limit_value) {
            $flosc_companion_numeric_limits[$flosc_limit_key] = absint($flosc_limit_value);
        }
        if ($flosc_companion_numeric_limits['panel_width_max'] < $flosc_companion_numeric_limits['panel_width_min']) {
            $flosc_companion_numeric_limits['panel_width_max'] = $flosc_companion_numeric_limits['panel_width_min'];
        }
        if ($flosc_companion_numeric_limits['panel_height_max'] < $flosc_companion_numeric_limits['panel_height_min']) {
            $flosc_companion_numeric_limits['panel_height_max'] = $flosc_companion_numeric_limits['panel_height_min'];
        }
        if ($flosc_companion_numeric_limits['launcher_size_max'] < $flosc_companion_numeric_limits['launcher_size_min']) {
            $flosc_companion_numeric_limits['launcher_size_max'] = $flosc_companion_numeric_limits['launcher_size_min'];
        }
        if ($flosc_companion_numeric_limits['auto_open_delay_max_ms'] < $flosc_companion_numeric_limits['auto_open_delay_min_ms']) {
            $flosc_companion_numeric_limits['auto_open_delay_max_ms'] = $flosc_companion_numeric_limits['auto_open_delay_min_ms'];
        }
        if ($flosc_companion_numeric_limits['scroll_percent_max'] < $flosc_companion_numeric_limits['scroll_percent_min']) {
            $flosc_companion_numeric_limits['scroll_percent_max'] = $flosc_companion_numeric_limits['scroll_percent_min'];
        }
        if ($flosc_companion_numeric_limits['trigger_min_page_time_max_ms'] < $flosc_companion_numeric_limits['trigger_min_page_time_min_ms']) {
            $flosc_companion_numeric_limits['trigger_min_page_time_max_ms'] = $flosc_companion_numeric_limits['trigger_min_page_time_min_ms'];
        }
        if ($flosc_companion_numeric_limits['trigger_cooldown_max_ms'] < $flosc_companion_numeric_limits['trigger_cooldown_min_ms']) {
            $flosc_companion_numeric_limits['trigger_cooldown_max_ms'] = $flosc_companion_numeric_limits['trigger_cooldown_min_ms'];
        }
        $flosc_companion_modes = apply_filters('flosc_companion_allowed_modes', ['in_chat', 'companion', 'both']);
        $flosc_companion_positions = apply_filters('flosc_companion_allowed_positions', ['bottom-right', 'bottom-left']);
        $flosc_companion_mobile_behaviors = apply_filters('flosc_companion_mobile_behaviors', ['fullscreen', 'panel']);
        $flosc_companion_context_scopes = apply_filters('flosc_companion_context_scopes', ['basic', 'extended']);
        $flosc_companion_launcher_icons = array_keys((array) apply_filters('flosc_companion_launcher_icon_choices', [
            'product_logo' => 'Product Logo (Chat Logo)',
            'chat' => 'Chat Bubble',
            'help' => 'Help Circle',
            'spark' => 'Spark',
        ]));
        $flosc_companion_motion_modes = apply_filters('flosc_companion_motion_modes', ['system', 'reduce', 'full']);
        $flosc_companion_state_storages = apply_filters('flosc_companion_state_storages', ['session', 'local']);

        $flosc_mode = sanitize_text_field((string) ($flosc_post['flow_companion_content_display_mode'] ?? $flosc_companion_defaults['mode']));
        if (!in_array($flosc_mode, (array) $flosc_companion_modes, true)) {
            $flosc_mode = $flosc_companion_defaults['mode'];
        }

        $flosc_position = sanitize_text_field((string) ($flosc_post['flow_companion_position'] ?? $flosc_companion_defaults['position']));
        if (!in_array($flosc_position, (array) $flosc_companion_positions, true)) {
            $flosc_position = $flosc_companion_defaults['position'];
        }

        $flosc_accent = sanitize_hex_color((string) ($flosc_post['flow_companion_accent_color'] ?? $flosc_companion_defaults['accent_color']));
        if (empty($flosc_accent)) {
            $flosc_accent = sanitize_hex_color((string) $flosc_companion_defaults['accent_color']) ?: '#6366f1';
        }

        $flosc_panel_width = absint($flosc_post['flow_companion_panel_width'] ?? $flosc_companion_defaults['panel_width']);
        $flosc_panel_height = absint($flosc_post['flow_companion_panel_height'] ?? $flosc_companion_defaults['panel_height']);
        $flosc_panel_width = max($flosc_companion_numeric_limits['panel_width_min'], min($flosc_companion_numeric_limits['panel_width_max'], $flosc_panel_width));
        $flosc_panel_height = max($flosc_companion_numeric_limits['panel_height_min'], min($flosc_companion_numeric_limits['panel_height_max'], $flosc_panel_height));
        $flosc_launcher_size = absint($flosc_post['flow_companion_launcher_size'] ?? $flosc_companion_defaults['launcher_size']);
        $flosc_launcher_size = max($flosc_companion_numeric_limits['launcher_size_min'], min($flosc_companion_numeric_limits['launcher_size_max'], $flosc_launcher_size));
        $flosc_launcher_icon = sanitize_key((string) ($flosc_post['flow_companion_launcher_icon'] ?? $flosc_companion_defaults['launcher_icon']));
        if (!in_array($flosc_launcher_icon, (array) $flosc_companion_launcher_icons, true)) {
            $flosc_launcher_icon = $flosc_companion_defaults['launcher_icon'];
        }

        $flosc_mobile_behavior = sanitize_text_field((string) ($flosc_post['flow_companion_mobile_behavior'] ?? $flosc_companion_defaults['mobile_behavior']));
        if (!in_array($flosc_mobile_behavior, (array) $flosc_companion_mobile_behaviors, true)) {
            $flosc_mobile_behavior = $flosc_companion_defaults['mobile_behavior'];
        }

        $flosc_context_scope = sanitize_text_field((string) ($flosc_post['flow_companion_context_scope'] ?? $flosc_companion_defaults['context_scope']));
        if (!in_array($flosc_context_scope, (array) $flosc_companion_context_scopes, true)) {
            $flosc_context_scope = $flosc_companion_defaults['context_scope'];
        }

        $flosc_motion_mode = sanitize_text_field((string) ($flosc_post['flow_companion_motion_mode'] ?? $flosc_companion_defaults['motion_mode']));
        if (!in_array($flosc_motion_mode, (array) $flosc_companion_motion_modes, true)) {
            $flosc_motion_mode = $flosc_companion_defaults['motion_mode'];
        }

        $flosc_auto_open_delay_ms = absint($flosc_post['flow_companion_auto_open_delay_ms'] ?? $flosc_companion_defaults['auto_open_delay_ms']);
        $flosc_auto_open_delay_ms = max($flosc_companion_numeric_limits['auto_open_delay_min_ms'], min($flosc_companion_numeric_limits['auto_open_delay_max_ms'], $flosc_auto_open_delay_ms));
        $flosc_launch_on_scroll_percent = absint($flosc_post['flow_companion_launch_on_scroll_percent'] ?? $flosc_companion_defaults['launch_on_scroll_percent']);
        $flosc_launch_on_scroll_percent = max($flosc_companion_numeric_limits['scroll_percent_min'], min($flosc_companion_numeric_limits['scroll_percent_max'], $flosc_launch_on_scroll_percent));
        $flosc_trigger_min_page_time_ms = absint($flosc_post['flow_companion_trigger_min_page_time_ms'] ?? $flosc_companion_defaults['trigger_min_page_time_ms']);
        $flosc_trigger_min_page_time_ms = max($flosc_companion_numeric_limits['trigger_min_page_time_min_ms'], min($flosc_companion_numeric_limits['trigger_min_page_time_max_ms'], $flosc_trigger_min_page_time_ms));
        $flosc_keyboard_shortcut_key = sanitize_text_field((string) ($flosc_post['flow_companion_keyboard_shortcut_key'] ?? $flosc_companion_defaults['keyboard_shortcut_key']));
        $flosc_keyboard_shortcut_key = strtolower(substr(trim($flosc_keyboard_shortcut_key), 0, 1));
        if ($flosc_keyboard_shortcut_key === '' || !preg_match('/^[a-z0-9]$/', $flosc_keyboard_shortcut_key)) {
            $flosc_keyboard_shortcut_key = 'k';
        }
        $flosc_launcher_aria_label = sanitize_text_field((string) ($flosc_post['flow_companion_launcher_aria_label'] ?? $flosc_companion_defaults['launcher_aria_label']));
        if ($flosc_launcher_aria_label === '') {
            $flosc_launcher_aria_label = 'Open Chat';
        }
        $flosc_close_aria_label = sanitize_text_field((string) ($flosc_post['flow_companion_close_aria_label'] ?? $flosc_companion_defaults['close_aria_label']));
        if ($flosc_close_aria_label === '') {
            $flosc_close_aria_label = 'Collapse Chat';
        }
        $flosc_state_storage = sanitize_text_field((string) ($flosc_post['flow_companion_state_storage'] ?? $flosc_companion_defaults['state_storage']));
        if (!in_array($flosc_state_storage, (array) $flosc_companion_state_storages, true)) {
            $flosc_state_storage = $flosc_companion_defaults['state_storage'];
        }
        $flosc_trigger_cooldown_ms = absint($flosc_post['flow_companion_trigger_cooldown_ms'] ?? $flosc_companion_defaults['trigger_cooldown_ms']);
        $flosc_trigger_cooldown_ms = max($flosc_companion_numeric_limits['trigger_cooldown_min_ms'], min($flosc_companion_numeric_limits['trigger_cooldown_max_ms'], $flosc_trigger_cooldown_ms));

        $flosc_new_settings['companion_content_display_mode'] = $flosc_mode;
        $flosc_new_settings['companion_position'] = $flosc_position;
        $flosc_new_settings['companion_greeting'] = sanitize_textarea_field((string) ($flosc_post['flow_companion_greeting'] ?? $flosc_companion_defaults['greeting']));
        $flosc_new_settings['companion_subtitle'] = sanitize_text_field((string) ($flosc_post['flow_companion_subtitle'] ?? $flosc_companion_defaults['subtitle']));
        $flosc_new_settings['companion_header_icon_url'] = esc_url_raw((string) ($flosc_post['flow_companion_header_icon_url'] ?? ''));
        $flosc_new_settings['companion_contextual_prompt'] = sanitize_text_field((string) ($flosc_post['flow_companion_contextual_prompt'] ?? $flosc_companion_defaults['contextual_prompt']));
        // Companion profile-row tier codes (defaults V/G/M) and accessible labels.
        $flosc_sanitize_tier_code = static function ( $raw, $fallback ) {
            $code = strtoupper( sanitize_text_field( (string) $raw ) );
            $code = preg_replace( '/[^A-Z0-9]/', '', $code );
            $code = substr( (string) $code, 0, 3 );
            return $code !== '' ? $code : $fallback;
        };
        $flosc_new_settings['companion_profile_tier_visitor'] = $flosc_sanitize_tier_code(
            $flosc_post['flow_companion_profile_tier_visitor'] ?? ( $flosc_companion_defaults['profile_tier_visitor'] ?? 'V' ),
            'V'
        );
        $flosc_new_settings['companion_profile_tier_guest'] = $flosc_sanitize_tier_code(
            $flosc_post['flow_companion_profile_tier_guest'] ?? ( $flosc_companion_defaults['profile_tier_guest'] ?? 'G' ),
            'G'
        );
        $flosc_new_settings['companion_profile_tier_member'] = $flosc_sanitize_tier_code(
            $flosc_post['flow_companion_profile_tier_member'] ?? ( $flosc_companion_defaults['profile_tier_member'] ?? 'M' ),
            'M'
        );
        $flosc_new_settings['companion_profile_tier_visitor_label'] = sanitize_text_field(
            (string) ( $flosc_post['flow_companion_profile_tier_visitor_label'] ?? ( $flosc_companion_defaults['profile_tier_visitor_label'] ?? 'Visitor' ) )
        );
        $flosc_new_settings['companion_profile_tier_guest_label'] = sanitize_text_field(
            (string) ( $flosc_post['flow_companion_profile_tier_guest_label'] ?? ( $flosc_companion_defaults['profile_tier_guest_label'] ?? 'Guest' ) )
        );
        $flosc_new_settings['companion_profile_tier_member_label'] = sanitize_text_field(
            (string) ( $flosc_post['flow_companion_profile_tier_member_label'] ?? ( $flosc_companion_defaults['profile_tier_member_label'] ?? 'Member' ) )
        );
        // Explicit header visibility toggles (checkbox: present = show, absent = hide).
        $flosc_new_settings['companion_show_header_title'] = isset($flosc_post['flow_companion_show_header_title']) ? 1 : 0;
        $flosc_new_settings['companion_show_header_subtitle'] = isset($flosc_post['flow_companion_show_header_subtitle']) ? 1 : 0;
        $flosc_new_settings['companion_show_open_fullpage'] = isset($flosc_post['flow_companion_show_open_fullpage']) ? 1 : 0;
        $flosc_new_settings['companion_accent_color'] = $flosc_accent;
        $flosc_new_settings['companion_panel_width'] = $flosc_panel_width;
        $flosc_new_settings['companion_panel_height'] = $flosc_panel_height;
        $flosc_new_settings['companion_launcher_size'] = $flosc_launcher_size;
        $flosc_new_settings['companion_launcher_icon'] = $flosc_launcher_icon;
        $flosc_new_settings['companion_mobile_behavior'] = $flosc_mobile_behavior;
        $flosc_new_settings['companion_context_scope'] = $flosc_context_scope;
        $flosc_new_settings['companion_page_intent_phrases'] = sanitize_textarea_field((string) ($flosc_post['flow_companion_page_intent_phrases'] ?? ''));
        $flosc_new_settings['companion_motion_mode'] = $flosc_motion_mode;
        $flosc_new_settings['companion_keyboard_shortcut_key'] = $flosc_keyboard_shortcut_key;
        $flosc_new_settings['companion_launcher_aria_label'] = $flosc_launcher_aria_label;
        $flosc_new_settings['companion_close_aria_label'] = $flosc_close_aria_label;
        $flosc_new_settings['companion_state_storage'] = $flosc_state_storage;
        $flosc_new_settings['companion_trigger_cooldown_ms'] = $flosc_trigger_cooldown_ms;
        $flosc_new_settings['companion_auto_open_delay_ms'] = $flosc_auto_open_delay_ms;
        $flosc_new_settings['companion_launch_on_scroll_percent'] = $flosc_launch_on_scroll_percent;
        $flosc_new_settings['companion_trigger_min_page_time_ms'] = $flosc_trigger_min_page_time_ms;
        $flosc_new_settings['companion_trigger_suppress_path_patterns'] = sanitize_textarea_field((string) ($flosc_post['flow_companion_trigger_suppress_path_patterns'] ?? ''));

        $flosc_parse_companion_custom_rules = static function ($raw_rules) {
            $rules = [];
            $chunks = preg_split('/[\r\n,]+/', (string) $raw_rules);
            if (!is_array($chunks)) {
                return $rules;
            }

            foreach ($chunks as $chunk) {
                $chunk = trim((string) $chunk);
                if ($chunk === '') {
                    continue;
                }

                if (strpos($chunk, ':') === false) {
                    $chunk = 'path:/' . ltrim($chunk, '/');
                }

                $rules[] = $chunk;
            }

            return array_values(array_unique($rules));
        };

        $flosc_collect_companion_rules = static function ($prefix, $post_data, $custom_parser) {
            $rules = [];

            $flosc_page_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($post_data['flow_companion_' . $prefix . '_pages'] ?? [])))));
            foreach ($flosc_page_ids as $page_id) {
                $rules[] = 'page:' . $page_id;
            }

            $flosc_post_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($post_data['flow_companion_' . $prefix . '_posts'] ?? [])))));
            foreach ($flosc_post_ids as $post_id) {
                $rules[] = 'post:' . $post_id;
            }

            $categories = array_values(array_unique(array_filter(array_map('absint', (array) ($post_data['flow_companion_' . $prefix . '_categories'] ?? [])))));
            foreach ($categories as $term_id) {
                $rules[] = 'category:' . $term_id;
            }

            $tags = array_values(array_unique(array_filter(array_map('absint', (array) ($post_data['flow_companion_' . $prefix . '_tags'] ?? [])))));
            foreach ($tags as $term_id) {
                $rules[] = 'tag:' . $term_id;
            }

            $custom_field_key = 'flow_companion_target_' . $prefix . '_custom';
            foreach ($custom_parser((string) ($post_data[$custom_field_key] ?? '')) as $custom_rule) {
                $rules[] = $custom_rule;
            }

            return implode("\n", array_values(array_unique($rules)));
        };

        $flosc_new_settings['companion_target_include'] = $flosc_collect_companion_rules('include', $flosc_post, $flosc_parse_companion_custom_rules);
        $flosc_new_settings['companion_target_exclude'] = $flosc_collect_companion_rules('exclude', $flosc_post, $flosc_parse_companion_custom_rules);

    }

    // v8.1.0: Member Levels tab — parse level registry + content protection repeaters
    if (in_array($flosc_active_tab, ['member-levels', 'content'], true)) {
        // Level registry repeater
        $flosc_level_slugs        = $flosc_post['level_slug']        ?? [];
        $flosc_level_names        = $flosc_post['level_name']        ?? [];
        $flosc_level_descriptions = $flosc_post['level_description'] ?? [];
        $flosc_member_levels = [];
        foreach ($flosc_level_slugs as $flosc_i => $flosc_slug) {
            $flosc_slug = sanitize_key($flosc_slug);
            if ($flosc_slug === '') continue;
            $flosc_member_levels[$flosc_slug] = [
                'slug'        => $flosc_slug,
                'name'        => sanitize_text_field($flosc_level_names[$flosc_i] ?? ''),
                'description' => sanitize_text_field($flosc_level_descriptions[$flosc_i] ?? ''),
            ];
        }
        $flosc_new_settings['member_levels'] = $flosc_member_levels;

        // Content protection repeater
        $flosc_prot_types  = $flosc_post['protection_type']  ?? [];
        $flosc_prot_values = $flosc_post['protection_value'] ?? [];
        $flosc_prot_levels = $flosc_post['protection_level'] ?? [];
        $flosc_protected_content = [];
        foreach ($flosc_prot_types as $flosc_i => $flosc_type) {
            $flosc_type  = sanitize_text_field($flosc_type);
            $flosc_value = sanitize_text_field($flosc_prot_values[$flosc_i] ?? '');
            $flosc_level = sanitize_key($flosc_prot_levels[$flosc_i] ?? '');
            if ($flosc_value === '') continue;
            $flosc_item = [
                'type'  => $flosc_type,
                'id'    => $flosc_value,
                'level' => $flosc_level,
            ];
            // Resolve names for display
            if (in_array($flosc_type, ['category', 'tag'])) {
                $flosc_term = get_term(intval($flosc_value));
                if ($flosc_term && !is_wp_error($flosc_term)) {
                    $flosc_item['slug'] = $flosc_term->slug;
                    $flosc_item['name'] = $flosc_term->name;
                }
            } elseif (in_array($flosc_type, ['post', 'page'])) {
                $flosc_post_obj = get_post(intval($flosc_value));
                if ($flosc_post_obj) {
                    $flosc_item['name'] = $flosc_post_obj->post_title;
                }
            }
            $flosc_protected_content[] = $flosc_item;
        }
        $flosc_new_settings['protected_content'] = $flosc_protected_content;

        // Guest chat entitlements (checkboxes need explicit empty when unchecked).
        $flosc_new_settings['guest_max_chats'] = max(0, min(9999, intval($flosc_new_settings['guest_max_chats'] ?? 0)));
        $flosc_new_settings['guest_can_delete_chats'] = !empty($flosc_post['flow_guest_can_delete_chats']) ? '1' : '';
        $flosc_new_settings['guest_can_rename_chats'] = !empty($flosc_post['flow_guest_can_rename_chats']) ? '1' : '';
        if (isset($flosc_post['flow_guest_new_chat_limit_message'])) {
            $flosc_new_settings['guest_new_chat_limit_message'] = sanitize_textarea_field(
                wp_unslash((string) $flosc_post['flow_guest_new_chat_limit_message'])
            );
        }
        if (isset($flosc_new_settings['guest_access_days'])) {
            $flosc_new_settings['guest_access_days'] = max(0, min(365, intval($flosc_new_settings['guest_access_days'])));
        }
        if (isset($flosc_new_settings['free_content_item_count'])) {
            $flosc_new_settings['free_content_item_count'] = max(1, min(50, intval($flosc_new_settings['free_content_item_count'])));
        }

        // Sync to term_meta while class-content-protection.php still reads term_meta.
        // Follow-up: move class-content-protection.php to flow_settings['protected_content']
        // and then remove this compatibility sync.
        // First clear old protection flags from categories no longer protected
        $flosc_old_protected = $flosc_flow_settings['protected_content'] ?? [];
        foreach ($flosc_old_protected as $flosc_old_item) {
            if ($flosc_old_item['type'] === 'category') {
                delete_term_meta(intval($flosc_old_item['id']), '_flosc_protected');
                delete_term_meta(intval($flosc_old_item['id']), '_flosc_required_level');
            }
        }
        // Set new protection flags
        foreach ($flosc_protected_content as $flosc_item) {
            if ($flosc_item['type'] === 'category') {
                update_term_meta(intval($flosc_item['id']), '_flosc_protected', 'yes');
                if (!empty($flosc_item['level'])) {
                    update_term_meta(intval($flosc_item['id']), '_flosc_required_level', $flosc_item['level']);
                }
            }
        }
    }

    // v3.0.0: Lessons tab — parse content_item_groups repeater
    // Repeater fields are NOT flow_-prefixed (content_item_group_quiz[], content_item_group_category[])
    if (in_array($flosc_active_tab, ['lessons', 'content'], true)) {
        $flosc_group_quizzes    = $flosc_post['content_item_group_quiz']     ?? [];
        $flosc_group_categories = $flosc_post['content_item_group_category'] ?? [];
        $flosc_content_item_groups = [];
        foreach ($flosc_group_categories as $flosc_i => $flosc_cat) {
            $flosc_cat = sanitize_text_field($flosc_cat);
            if ($flosc_cat === '') continue; // Skip rows with no category selected
            $flosc_quiz = sanitize_text_field($flosc_group_quizzes[$flosc_i] ?? '');
            $flosc_content_item_groups[] = [
                'quiz_id'  => $flosc_quiz,
                'category' => $flosc_cat,
            ];
        }
        // Only overwrite content_item_groups when the form actually submitted rows.
        // An accidental save on an empty/wrong-flow form must not wipe existing config.
        if (!empty($flosc_content_item_groups)) {
            $flosc_new_settings['content_item_groups']    = $flosc_content_item_groups;
            $flosc_new_settings['content_item_category'] = $flosc_content_item_groups[0]['category'];
        }
        // Free-sample pool = WP category (often a child of the main lessons category)
        if (isset($flosc_new_settings['free_content_item_pool_category'])) {
            $flosc_new_settings['free_content_item_pool_category'] = sanitize_title(
                (string) $flosc_new_settings['free_content_item_pool_category']
            );
        }
        if (isset($flosc_new_settings['exclude_items_from_freeline'])) {
            $flosc_raw_nf = (string) $flosc_new_settings['exclude_items_from_freeline'];
            $flosc_parts = preg_split('/[\s,;]+/', $flosc_raw_nf) ?: [];
            $flosc_nums = [];
            foreach ($flosc_parts as $flosc_part) {
                $flosc_n = intval($flosc_part);
                if ($flosc_n > 0) {
                    $flosc_nums[] = $flosc_n;
                }
            }
            $flosc_new_settings['exclude_items_from_freeline'] = implode(', ', array_values(array_unique($flosc_nums)));
        }
        if (isset($flosc_new_settings['free_content_item_guaranteed'])) {
            $flosc_new_settings['free_content_item_guaranteed'] = max(0, intval($flosc_new_settings['free_content_item_guaranteed']));
        }
        if (isset($flosc_new_settings['free_content_item_count'])) {
            $flosc_new_settings['free_content_item_count'] = max(1, min(50, intval($flosc_new_settings['free_content_item_count'])));
        }
        if (isset($flosc_new_settings['guest_access_days'])) {
            $flosc_new_settings['guest_access_days'] = max(0, min(365, intval($flosc_new_settings['guest_access_days'])));
        }
        // Content Types repeater (singular / plural columns).
        $flosc_ct_singular = $flosc_post['content_type_singular'] ?? [];
        $flosc_ct_plural   = $flosc_post['content_type_plural'] ?? [];
        if (is_array($flosc_ct_singular)) {
            $flosc_content_types = [];
            foreach ($flosc_ct_singular as $flosc_i => $flosc_s) {
                $flosc_s = sanitize_text_field(wp_unslash((string) $flosc_s));
                $flosc_p = sanitize_text_field(wp_unslash((string) ($flosc_ct_plural[$flosc_i] ?? '')));
                if ($flosc_s === '' && $flosc_p === '') {
                    continue;
                }
                if ($flosc_s === '' && $flosc_p !== '') {
                    $flosc_s = $flosc_p;
                }
                if ($flosc_p === '') {
                    $flosc_p = $flosc_s;
                }
                $flosc_content_types[] = [
                    'singular' => $flosc_s,
                    'plural'   => $flosc_p,
                ];
            }
            $flosc_new_settings['content_types'] = $flosc_content_types;
            // Keep first row as legacy single-label keys for older readers.
            if (!empty($flosc_content_types[0])) {
                $flosc_new_settings['content_item_label_singular'] = $flosc_content_types[0]['singular'];
                $flosc_new_settings['content_item_label_plural']   = $flosc_content_types[0]['plural'];
            } else {
                $flosc_new_settings['content_item_label_singular'] = '';
                $flosc_new_settings['content_item_label_plural']   = '';
            }
        }
    }

    // Chat navigation settings are managed from the Profile Bar tab.
    if ($flosc_active_tab === 'ui') {
        // Chat list chrome (flow-scoped labels / new-chat welcome copy).
        if (isset($flosc_post['flow_new_chat_button_label'])) {
            $flosc_new_settings['new_chat_button_label'] = sanitize_text_field(
                wp_unslash((string) $flosc_post['flow_new_chat_button_label'])
            );
        }
        // first_chat_title removed: session title is always "New Chat" on create, then auto/rename.
        if (isset($flosc_post['flow_empty_chat_list_message'])) {
            $flosc_new_settings['empty_chat_list_message'] = sanitize_text_field(
                wp_unslash((string) $flosc_post['flow_empty_chat_list_message'])
            );
        }
        if (isset($flosc_post['flow_guest_new_chat_welcome_message'])) {
            $flosc_new_settings['guest_new_chat_welcome_message'] = sanitize_textarea_field(
                wp_unslash((string) $flosc_post['flow_guest_new_chat_welcome_message'])
            );
        }
        if (isset($flosc_post['flow_member_new_chat_welcome_message'])) {
            $flosc_new_settings['member_new_chat_welcome_message'] = sanitize_textarea_field(
                wp_unslash((string) $flosc_post['flow_member_new_chat_welcome_message'])
            );
        }

        // Visitor menu — dynamic items from repeater
        $flosc_menu_labels  = $flosc_post['visitor_menu_label']  ?? [];
        $flosc_menu_actions = $flosc_post['visitor_menu_action'] ?? [];
        $flosc_new_menu = [];
        foreach ($flosc_menu_labels as $flosc_i => $flosc_label) {
            $flosc_label  = sanitize_text_field($flosc_label);
            $action = sanitize_text_field($flosc_menu_actions[$flosc_i] ?? '');
            if ($flosc_label !== '' && $action !== '') {
                $flosc_new_menu[] = ['label' => $flosc_label, 'action' => $action];
            }
        }
        update_option('flosc_visitor_menu_items', $flosc_new_menu);

        // v1.9.8: Guest menu — dynamic items from repeater
        // Purchase/offer actions are the profile-bar Upgrade button, not plain menu rows.
        $flosc_guest_labels  = $flosc_post['guest_menu_label']  ?? [];
        $flosc_guest_actions = $flosc_post['guest_menu_action'] ?? [];
        $flosc_new_guest_menu = [];
        foreach ($flosc_guest_labels as $flosc_i => $flosc_label) {
            $flosc_label  = sanitize_text_field($flosc_label);
            $action = sanitize_text_field($flosc_guest_actions[$flosc_i] ?? '');
            if ($flosc_label === '' || $action === '') {
                continue;
            }
            if ($action === 'open_sandbox_purchase' || strpos($action, 'show_offer') === 0) {
                continue;
            }
            $flosc_new_guest_menu[] = ['label' => $flosc_label, 'action' => $action];
        }
        update_option('flosc_guest_menu_items', $flosc_new_guest_menu);

        // v1.9.8: Member menu — dynamic items from repeater
        $flosc_member_labels  = $flosc_post['member_menu_label']  ?? [];
        $flosc_member_actions = $flosc_post['member_menu_action'] ?? [];
        $flosc_new_member_menu = [];
        foreach ($flosc_member_labels as $flosc_i => $flosc_label) {
            $flosc_label  = sanitize_text_field($flosc_label);
            $action = sanitize_text_field($flosc_member_actions[$flosc_i] ?? '');
            if ($flosc_label === '' || $action === '') {
                continue;
            }
            if ($action === 'open_sandbox_purchase' || strpos($action, 'show_offer') === 0) {
                continue;
            }
            $flosc_new_member_menu[] = ['label' => $flosc_label, 'action' => $action];
        }
        update_option('flosc_member_menu_items', $flosc_new_member_menu);

        // Login destination — v1.9.8: now a URL, not a key
        $flosc_login_dest = trim($flosc_post['flosc_login_destination'] ?? '');
        update_option('flosc_login_destination', $flosc_login_dest !== '' ? esc_url_raw($flosc_login_dest) : '');
    }

    // v8.0.1: Profile Bar tab now manages profile-bar state labels and badges only.
    if ($flosc_active_tab === 'ui') {

        $flosc_allowed_avatar_radii = ['8px', '50%', '4px', '0'];
        $flosc_visitor_avatar_radius = sanitize_text_field((string) ($flosc_post['profile_bar_visitor_avatar_radius'] ?? ($flosc_post['flow_avatar_radius'] ?? '8px')));
        if (!in_array($flosc_visitor_avatar_radius, $flosc_allowed_avatar_radii, true)) {
            $flosc_visitor_avatar_radius = '8px';
        }
        $flosc_guest_avatar_radius = sanitize_text_field((string) ($flosc_post['profile_bar_guest_avatar_radius'] ?? '8px'));
        if (!in_array($flosc_guest_avatar_radius, $flosc_allowed_avatar_radii, true)) {
            $flosc_guest_avatar_radius = '8px';
        }
        $flosc_member_avatar_radius = sanitize_text_field((string) ($flosc_post['profile_bar_member_avatar_radius'] ?? '8px'));
        if (!in_array($flosc_member_avatar_radius, $flosc_allowed_avatar_radii, true)) {
            $flosc_member_avatar_radius = '8px';
        }

        // Profile bar per-state settings
        $flosc_profile_bar = [
            'visitor' => [
                'name'  => sanitize_text_field($flosc_post['profile_bar_visitor_name'] ?? 'Visitor'),
                'badge' => sanitize_text_field($flosc_post['profile_bar_visitor_badge'] ?? 'Hope you enjoy our chat :-)'),
                'icon'  => sanitize_text_field($flosc_post['profile_bar_visitor_icon'] ?? '👋'),
                'icon_url' => esc_url_raw($flosc_post['profile_bar_visitor_icon_url'] ?? ''),
                'avatar_radius' => $flosc_visitor_avatar_radius,
                'show_upgrade' => isset($flosc_post['profile_bar_visitor_show_upgrade']) && $flosc_post['profile_bar_visitor_show_upgrade'] === '1',
                'upgrade_label' => sanitize_text_field($flosc_post['profile_bar_visitor_upgrade_label'] ?? 'Upgrade'),
            ],
            'guest' => [
                'name' => sanitize_text_field($flosc_post['profile_bar_guest_name'] ?? ''),
                'badge' => sanitize_text_field($flosc_post['profile_bar_guest_badge'] ?? 'Guest'),
                'icon' => sanitize_text_field($flosc_post['profile_bar_guest_icon'] ?? ''),
                'icon_url' => esc_url_raw($flosc_post['profile_bar_guest_icon_url'] ?? ''),
                'avatar_radius' => $flosc_guest_avatar_radius,
                'show_upgrade' => isset($flosc_post['profile_bar_guest_show_upgrade']) && $flosc_post['profile_bar_guest_show_upgrade'] === '1',
                'upgrade_label' => sanitize_text_field($flosc_post['profile_bar_guest_upgrade'] ?? 'Upgrade to Pro'),
            ],
            'member' => [
                'name' => sanitize_text_field($flosc_post['profile_bar_member_name'] ?? ''),
                'badge' => sanitize_text_field($flosc_post['profile_bar_member_badge'] ?? 'Member'),
                'icon' => sanitize_text_field($flosc_post['profile_bar_member_icon'] ?? ''),
                'icon_url' => esc_url_raw($flosc_post['profile_bar_member_icon_url'] ?? ''),
                'avatar_radius' => $flosc_member_avatar_radius,
                'show_upgrade' => isset($flosc_post['profile_bar_member_show_upgrade']) && $flosc_post['profile_bar_member_show_upgrade'] === '1',
                'upgrade_label' => sanitize_text_field($flosc_post['profile_bar_member_upgrade_label'] ?? 'Upgrade'),
            ],
        ];
        update_option('flosc_profile_bar', $flosc_profile_bar);

        // Legacy compatibility path for any runtime still reading flow-level avatar radius.
        $flosc_new_settings['avatar_radius'] = $flosc_visitor_avatar_radius;
    }

    if ($flosc_active_tab === 'administration') {
        if (current_user_can('manage_options')) {
            $flosc_allowed_plans = ['free', 'paid', 'enterprise'];
            $flosc_plan = sanitize_key($flosc_post['flosc_account_plan'] ?? 'free');
            if (!in_array($flosc_plan, $flosc_allowed_plans, true)) {
                $flosc_plan = 'free';
            }
            update_option('flosc_account_plan', $flosc_plan);

            $flosc_manual_purchases = sanitize_textarea_field($flosc_post['flosc_account_purchases_manual'] ?? '');
            update_option('flosc_account_purchases_manual', $flosc_manual_purchases);

            $flosc_allowed_debug_modes = ['inherit', 'on', 'off'];
            $flosc_debug_mode = sanitize_key($flosc_post['flosc_debug_mode'] ?? 'inherit');
            if (!in_array($flosc_debug_mode, $flosc_allowed_debug_modes, true)) {
                $flosc_debug_mode = 'inherit';
            }
            update_option('flosc_debug_mode', $flosc_debug_mode);
        }

        // Site admin assigns floscEditors (Editor or above) to this flow.
        if (current_user_can('manage_options') && isset($flosc_post['flosc_update_flosc_editors'])) {
            $flosc_selected_editor_ids = array_map('intval', (array) ($flosc_post['flosc_flow_editors'] ?? []));

            $flosc_assignable_users = get_users([
                'role__in' => ['administrator', 'editor'],
                'fields' => ['ID', 'roles'],
            ]);

            $flosc_allowed_user_ids = [];
            foreach ($flosc_assignable_users as $flosc_assignable_user) {
                $flosc_allowed_user_ids[] = (int) $flosc_assignable_user->ID;
            }

            $flosc_selected_editor_ids = array_values(array_intersect($flosc_selected_editor_ids, $flosc_allowed_user_ids));

            $flosc_current_team = flosc_flows()->get_flow_users($flosc_selected_flow_id);
            $flosc_current_team_ids = array_map(static function($flosc_user) {
                return (int) $flosc_user->ID;
            }, $flosc_current_team);

            // Remove users no longer selected.
            foreach ($flosc_current_team_ids as $flosc_uid) {
                if (!in_array($flosc_uid, $flosc_selected_editor_ids, true)) {
                    flosc_flows()->revoke_flow_access($flosc_uid, $flosc_selected_flow_id);
                }
            }

            // Grant selected Editor+ users; admins naturally retain global authority.
            foreach ($flosc_selected_editor_ids as $flosc_uid) {
                if (user_can($flosc_uid, 'edit_others_posts')) {
                    flosc_flows()->grant_flow_access($flosc_uid, $flosc_selected_flow_id);
                }
            }
        }
    }

    // Nest identity fields into the identity sub-array
    // The generic POST loop saves them flat (e.g. $flosc_new_settings['chatlogo_url']).
    // get_floscflow_identity() reads from $flow['identity']['chatlogo_url'].
    $flosc_identity_keys = [
        'name',
        'title',
        'tagline',
        'primary_color',
        'chatlogo_url',
        'favicon_url',
        'badgeUrl',
        'share_text',
        'privacy_policy_content',
        'terms_of_service_content',
        'data_deletion_content',
        'platform_compliance_content',
    ];
    $flosc_identity = $flosc_new_settings['identity'] ?? [];
    foreach ($flosc_identity_keys as $flosc_k) {
        if (isset($flosc_new_settings[$flosc_k])) {
            $flosc_identity[$flosc_k] = $flosc_new_settings[$flosc_k];
            unset($flosc_new_settings[$flosc_k]);
        }
    }
    $flosc_new_settings['identity'] = $flosc_identity;

    // Save flow settings (ALL tabs now per-flow)
    update_option($flosc_settings_key, $flosc_new_settings);

    // floscAvailableProviders: non-empty keys on this flow become available install-wide.
    if ( function_exists( 'flosc_available_providers_promote_from_flow' ) ) {
        flosc_available_providers_promote_from_flow( $flosc_new_settings );
    }

    // Post/Redirect/Get so save feedback is deterministic and browser back/refresh
    // does not resubmit the form.
    $flosc_redirect_url = add_query_arg([
        'page' => 'flosc-settings',
        'ivr' => $flosc_selected_ivr,
        'tab' => $flosc_active_tab,
        'view' => $flosc_identity_view,
        'saved' => '1',
    ], admin_url('admin.php'));
    wp_safe_redirect($flosc_redirect_url);
    exit;
}

// Early POST processing (admin_init): redirect handlers above exit on success.
// Do not print admin HTML during admin_init.
if (!empty($GLOBALS['flosc_settings_early_post_running'])) {
    return;
}

// Build flow URL
$flosc_flow_url = home_url('/' . ($flosc_flow_settings['slug'] ?? 'flosc') . '/');

// Set global context for tab includes
$GLOBALS['flosc_current_ivr'] = $flosc_selected_ivr;
$GLOBALS['flosc_current_settings'] = $flosc_flow_settings;
$GLOBALS['flosc_settings_key'] = $flosc_settings_key;

// Build display labels for selector options: show flow name, keep filename as value.
$flosc_flow_selector_labels = [];
foreach ($flosc_ivr_files as $flosc_selector_file) {
    $flosc_selector_key = flosc_resolve_flow_option_key_for_ivr($flosc_selector_file);
    $flosc_selector_settings = get_option($flosc_selector_key, []);
    $flosc_selector_name = '';

    if (is_array($flosc_selector_settings)) {
        $flosc_selector_name = trim((string) ($flosc_selector_settings['identity']['name'] ?? ''));
        if ($flosc_selector_name === '') {
            $flosc_selector_name = trim((string) ($flosc_selector_settings['name'] ?? ''));
        }
    }

    if ($flosc_selector_name === '') {
        $flosc_selector_name = ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $flosc_selector_file));
    }

    $flosc_flow_selector_labels[$flosc_selector_file] = $flosc_selector_name;
}

$flosc_selected_flow_name = $flosc_flow_selector_labels[$flosc_selected_ivr]
    ?? trim((string) ($flosc_flow_settings['identity']['name'] ?? ($flosc_flow_settings['name'] ?? '')));
if ($flosc_selected_flow_name === '') {
    $flosc_selected_flow_name = $flosc_selected_ivr;
}

// Flow chrome accent (Identity brand color) — used by flow-selector theme "flow-primary".
// Dynamic color via wp_add_inline_style (no HTML style attributes).
$flosc_flow_primary_color = sanitize_hex_color(
    (string) ($flosc_flow_settings['identity']['primary_color']
        ?? $flosc_flow_settings['primary_color']
        ?? '#4f46e5')
);
if (!$flosc_flow_primary_color) {
    $flosc_flow_primary_color = '#4f46e5';
}
if (function_exists('wp_add_inline_style')) {
    wp_add_inline_style(
        'flosc-admin',
        sprintf(
            '#flosc-flow-selector{--flosc-flow-primary:%1$flosc_s;}#color-preview{--flosc-preview-bg:%1$flosc_s;background:var(--flosc-preview-bg);}',
            esc_attr($flosc_flow_primary_color)
        )
    );
}

?>
<div class="wrap flosc-admin">
    <h1 class="flosc-settings-title">
        FLOSC Settings 
        <span class="flosc-settings-version">v<?php echo esc_html(FLOSC_VERSION); ?></span>
    </h1>

    <?php
    // Portability upload, import, delete, etc. use add_settings_error( 'flosc_settings', … ).
    settings_errors( 'flosc_settings' );
    ?>
    
    <?php if (isset($flosc_saved) || isset($flosc_get['saved'])): ?>
        <div id="flosc-save-feedback" class="notice notice-success is-dismissible" role="status" tabindex="-1">
            <p>✓ Settings saved for <strong><?php echo esc_html($flosc_flow_settings['identity']['name'] ?? $flosc_selected_ivr); ?></strong></p>
        </div>
        <?php
        // One notice only (bottom duplicate removed). After PRG reload, bring it into view
        // and drop ?saved=1 so refresh does not re-flash the banner.
        ob_start();
        ?>
        (function () {
            var notice = document.getElementById('flosc-save-feedback');
            if (notice) {
                try {
                    notice.focus({ preventScroll: true });
                } catch (e) {
                    try { notice.focus(); } catch (e2) { /* ignore */ }
                }
                if (typeof notice.scrollIntoView === 'function') {
                    notice.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            try {
                var url = new URL(window.location.href);
                if (url.searchParams.has('saved')) {
                    url.searchParams.delete('saved');
                    window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
                }
            } catch (e3) { /* ignore */ }
        })();
        <?php
        wp_add_inline_script('flosc-admin', ob_get_clean());
        ?>
    <?php endif; ?>

    <?php if (isset($flosc_get['default_set'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Default flow set to <strong><?php echo esc_html($flosc_flow_selector_labels[$flosc_selected_ivr] ?? $flosc_selected_ivr); ?></strong>.</p>
        </div>
    <?php endif; ?>
    
    <?php // Flow switcher chrome: single-line header + details accordion (ops/secondary). ?>
    <div class="flosc-flow-selector"
         id="flosc-flow-selector"
         data-theme="glass">

        <div class="flosc-flow-selector__main-row">
            <label class="flosc-flow-selector__select-label" for="ivr-select">Switch Flow:</label>
            <select id="ivr-select" class="flosc-flow-selector__select-control" aria-label="Switch Flow">
                <?php
                // Always-work cue only: append " (default)" to the label. No option CSS
                // (font-weight/color on <option> is OS-dependent and often ignored).
                foreach ($flosc_ivr_files as $flosc_file):
                    $flosc_opt_label = (string) ($flosc_flow_selector_labels[$flosc_file] ?? $flosc_file);
                    if ($flosc_user_default_ivr !== '' && $flosc_user_default_ivr === $flosc_file) {
                        $flosc_opt_label .= ' (default)';
                    }
                    ?>
                    <option value="<?php echo esc_attr($flosc_file); ?>" <?php selected($flosc_selected_ivr, $flosc_file); ?>>
                        <?php echo esc_html($flosc_opt_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (($flosc_flow_settings['status'] ?? '') === 'active' && !empty($flosc_flow_settings['slug'])): ?>
                <a href="<?php echo esc_url($flosc_flow_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-small">
                    View Flow &#8599;
                </a>
            <?php endif; ?>

            <?php if ($flosc_user_default_ivr === $flosc_selected_ivr && $flosc_user_default_ivr !== ''): ?>
                <span class="flosc-flow-selector__default-btn"
                      title="<?php echo esc_attr__('Opens this flow when you enter FLOSC Settings without a flow URL', 'flosc'); ?>">
                    <?php echo esc_html__('Default', 'flosc'); ?>
                </span>
            <?php else: ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="flosc-default-flow-form">
                    <?php wp_nonce_field('flosc_set_default_flow', 'flosc_default_flow_nonce'); ?>
                    <input type="hidden" name="action" value="flosc_set_default_flow">
                    <input type="hidden" name="ivr" value="<?php echo esc_attr($flosc_selected_ivr); ?>">
                    <input type="hidden" name="tab" value="<?php echo esc_attr($flosc_active_tab); ?>">
                    <input type="hidden" name="view" value="<?php echo esc_attr($flosc_identity_view); ?>">
                    <button type="submit" class="button button-small">
                        <?php echo esc_html__('Set as default', 'flosc'); ?>
                    </button>
                </form>
            <?php endif; ?>

            <button type="button"
                    class="button button-small flosc-flow-selector__details-toggle"
                    id="flosc-flow-selector-details-toggle"
                    aria-expanded="false"
                    aria-controls="flosc-flow-selector-details-panel">
                <?php echo esc_html__('Details', 'flosc'); ?>
                <span class="flosc-flow-selector__details-caret" aria-hidden="true">▾</span>
            </button>
        </div>

        <div id="flosc-flow-selector-details-panel"
             class="flosc-flow-selector__details-panel"
             hidden>
            <div class="flosc-flow-selector__details-row">
                <span class="flosc-flow-selector__details-label"><?php echo esc_html__('Flow file', 'flosc'); ?></span>
                <code class="flosc-flow-selector__meta-filename"><?php echo esc_html($flosc_selected_ivr); ?></code>
            </div>
            <div class="flosc-flow-selector__details-row flosc-flow-selector__details-row--status">
                <?php flosc_permalink_status_indicator($flosc_flow_settings['slug'] ?? ''); ?>
            </div>
        </div>
    </div>
    
    <?php ob_start(); ?>
    function switchIVR(ivr) {
        const tab = '<?php echo esc_js($flosc_active_tab); ?>';
        const view = '<?php echo esc_js($flosc_identity_view); ?>';
        window.location.href = '<?php echo esc_js( admin_url('admin.php?page=flosc-settings') ); ?>&ivr=' + encodeURIComponent(ivr) + '&tab=' + tab + '&view=' + encodeURIComponent(view);
    }

    (function () {
        const ivrSelect = document.getElementById('ivr-select');
        if (ivrSelect) {
            ivrSelect.addEventListener('change', function () {
                switchIVR(this.value);
            });
        }

        const detailsToggle = document.getElementById('flosc-flow-selector-details-toggle');
        const detailsPanel = document.getElementById('flosc-flow-selector-details-panel');
        if (detailsToggle && detailsPanel) {
            detailsToggle.addEventListener('click', function () {
                const isOpen = detailsToggle.getAttribute('aria-expanded') === 'true';
                const nextOpen = !isOpen;
                detailsToggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
                detailsToggle.classList.toggle('is-open', nextOpen);
                if (nextOpen) {
                    detailsPanel.removeAttribute('hidden');
                } else {
                    detailsPanel.setAttribute('hidden', '');
                }
            });
        }

        const tab = '<?php echo esc_js($flosc_active_tab); ?>';
        const tabTitles = {
            'flow': 'Flow',
            'identity': 'Identity',
            'ivr-messages': 'IVR Management',
            'autoprompts': 'AutoPrompt Panel',
            'content': 'Content',
            'member-levels': 'Content',
            'lessons': 'Content',
            'knowledge-base': 'Knowledge Base',
            'trajectories': 'Trajectories',
            'offers': 'Offers',
            'login': 'Register and Login',
            'style': 'Style and Nav',
            'ui': 'Profile Bar',
            'ai': 'AI',
            'token-management': 'Token Management',
            'concierge': 'Concierge',
            'quiz': 'Quiz',
            'email': 'Email',
            'contact-form': 'Contact Form',
            'payments': 'Payments',
            
            'sso': 'SSO',
            'engagement': 'Engagement',
            'chat-logs': 'Chat Logs',
            'administration': 'Administration',
            'documentation': 'Docs',
            'da1': 'DA1'
        };

        const section = tabTitles[tab] || 'Settings';
        document.title = section + ' < Settings < FLOSC';
    })();
    <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
    
    <!-- Tabs -->
    <nav class="nav-tab-wrapper flosc-settings-tabs" aria-label="FLOSC Settings Tabs">
        <?php
        $tabs = [
            'flow'          => '🗺 Flow',
            'identity'      => 'Identity',
            'ivr-messages'  => 'IVR Management',
            'autoprompts'   => 'AutoPrompt Panel',
            'content'       => 'Content',
            'knowledge-base'=> 'Knowledge Base',
            'trajectories'  => 'Trajectories',
            'offers'        => 'Offers',
            'login'         => 'Register & Login',
            'style'         => 'Style & Nav',
            'ui'            => 'Profile Bar',
            'ai'            => 'AI',
            'token-management' => 'Token Management',
            'concierge'     => 'Concierge',
            'quiz'          => 'Quiz',
            'email'         => 'Email',
            'contact-form'  => 'Contact Form',
            'payments'      => 'Payments',
            'sso'           => 'SSO',
            'engagement'    => 'Engagement',
            'chat-logs'     => 'Chat Logs',
            'administration'=> 'Administration',
            'documentation' => '📖 Docs',
            'da1'           => 'DA1',
        ];
        if (!$flosc_can_view_administration) {
            unset($tabs['administration']);
        }
        foreach ($tabs as $flosc_tab_id => $flosc_tab_label):
            $flosc_tab_url = add_query_arg([
                'page' => 'flosc-settings',
                'ivr' => $flosc_selected_ivr,
                'tab' => $flosc_tab_id,
                'view' => $flosc_identity_view,
            ], admin_url('admin.php'));
        ?>
            <a href="<?php echo esc_url($flosc_tab_url); ?>" 
               class="nav-tab <?php
               $flosc_tab_is_active = ( $flosc_active_tab === $flosc_tab_id )
                   || ( $flosc_tab_id === 'content' && in_array( $flosc_active_tab, [ 'member-levels', 'lessons' ], true ) );
               echo esc_attr( $flosc_tab_is_active ? 'nav-tab-active' : '' );
           ?>">
                <?php echo esc_html( $flosc_tab_label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($flosc_active_tab === 'da1'): ?>
        <?php include FLOSC_PLUGIN_DIR . 'admin/da1.php'; ?>
        <?php flosc_tab_footer(); ?>
</div>
        <?php return; ?>
    <?php endif; ?>
    
    <!-- Settings Form (id required: AI tab closes this form before sibling KB forms — save uses form=) -->
    <form method="post" class="flosc-settings-form" id="flosc-settings-form">
        <?php wp_nonce_field('flosc_save_settings'); ?>
        
        <?php if ($flosc_active_tab === 'flow'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/flow.php'; ?>

        <?php elseif ($flosc_active_tab === 'identity'): ?>
            <!-- Identity Tab v1.3.3 - All Flows = fully expanded inline editing -->
            
            <?php
            // View mode: single flow or all flows
            $flosc_view_mode = $flosc_identity_view;
            
            // Get all flows data
            $flosc_all_flows = [];
            foreach ($flosc_ivr_files as $flosc_ivr_file) {
                $flosc_key = flosc_resolve_flow_option_key_for_ivr($flosc_ivr_file);
                $flosc_settings = get_option($flosc_key, []);
                if (empty($flosc_settings)) {
                    // v1.3.5: Preserve underscores in default slug
                    $flosc_default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($flosc_ivr_file, PATHINFO_FILENAME)));
                    $flosc_settings = [
                        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $flosc_ivr_file)),
                        'slug' => $flosc_default_slug,
                        'domain' => '',
                        'title' => '',
                        'tagline' => '',
                        'primary_color' => '#4f46e5',
                        'status' => 'active',
                    ];
                }
                $flosc_all_flows[$flosc_ivr_file] = ['key' => $flosc_key, 'settings' => $flosc_settings];
            }
            
            // Handle individual flow save via AJAX or form
            if (isset($flosc_post['flosc_save_flow']) && wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
                if (!current_user_can('manage_options')) {
                    wp_die(esc_html__('You do not have permission to save multi-flow identity settings.', 'flosc'));
                }
                $flosc_save_ivr = sanitize_file_name($flosc_post['flosc_save_flow']);
                if (isset($flosc_all_flows[$flosc_save_ivr])) {
                    $flosc_flow_key = $flosc_all_flows[$flosc_save_ivr]['key'];
                    $flosc_new_settings = $flosc_all_flows[$flosc_save_ivr]['settings'];
                    
                    // Update from POST data (fields prefixed with ivr filename hash)
                    $flosc_prefix = 'flow_' . md5($flosc_save_ivr) . '_';
                    $flosc_identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
                    foreach ($flosc_post as $flosc_key => $flosc_value) {
                        if (strpos($flosc_key, $flosc_prefix) === 0) {
                            $flosc_setting_key = substr($flosc_key, strlen($flosc_prefix));
                            if (in_array($flosc_setting_key, $flosc_identity_html_keys, true)) {
                                $flosc_new_settings[$flosc_setting_key] = wp_kses_post($flosc_value);
                            } else {
                                $flosc_new_settings[$flosc_setting_key] = sanitize_text_field($flosc_value);
                            }
                        }
                    }
                    
                    // v2.0.0: Nest identity fields into identity sub-array
                    $flosc_id_keys = [
                        'name',
                        'title',
                        'tagline',
                        'primary_color',
                        'chatlogo_url',
                        'favicon_url',
                        'badgeUrl',
                        'share_text',
                        'privacy_policy_content',
                        'terms_of_service_content',
                        'data_deletion_content',
                        'platform_compliance_content',
                    ];
                    $flosc_id = $flosc_new_settings['identity'] ?? [];
                    foreach ($flosc_id_keys as $flosc_ik) {
                        if (isset($flosc_new_settings[$flosc_ik])) {
                            $flosc_id[$flosc_ik] = $flosc_new_settings[$flosc_ik];
                            unset($flosc_new_settings[$flosc_ik]);
                        }
                    }
                    $flosc_new_settings['identity'] = $flosc_id;
                    
                    update_option($flosc_flow_key, $flosc_new_settings);
                    $flosc_all_flows[$flosc_save_ivr]['settings'] = $flosc_new_settings;
                    $flosc_individual_saved = $flosc_save_ivr;
                }
            }
            
            // Handle save all flows
            if (isset($flosc_post['flosc_save_all_flows']) && wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
                if (!current_user_can('manage_options')) {
                    wp_die(esc_html__('You do not have permission to save multi-flow identity settings.', 'flosc'));
                }
                foreach ($flosc_all_flows as $flosc_ivr_file => $flosc_flow_data) {
                    $flosc_flow_key = $flosc_flow_data['key'];
                    $flosc_new_settings = $flosc_flow_data['settings'];
                    
                    $flosc_prefix = 'flow_' . md5($flosc_ivr_file) . '_';
                    $flosc_identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
                    foreach ($flosc_post as $flosc_key => $flosc_value) {
                        if (strpos($flosc_key, $flosc_prefix) === 0) {
                            $flosc_setting_key = substr($flosc_key, strlen($flosc_prefix));
                            if (in_array($flosc_setting_key, $flosc_identity_html_keys, true)) {
                                $flosc_new_settings[$flosc_setting_key] = wp_kses_post($flosc_value);
                            } else {
                                $flosc_new_settings[$flosc_setting_key] = sanitize_text_field($flosc_value);
                            }
                        }
                    }
                    
                    // v2.0.0: Nest identity fields into identity sub-array
                    $flosc_id_keys = [
                        'name',
                        'title',
                        'tagline',
                        'primary_color',
                        'chatlogo_url',
                        'favicon_url',
                        'badgeUrl',
                        'share_text',
                        'privacy_policy_content',
                        'terms_of_service_content',
                        'data_deletion_content',
                        'platform_compliance_content',
                    ];
                    $flosc_id = $flosc_new_settings['identity'] ?? [];
                    foreach ($flosc_id_keys as $flosc_ik) {
                        if (isset($flosc_new_settings[$flosc_ik])) {
                            $flosc_id[$flosc_ik] = $flosc_new_settings[$flosc_ik];
                            unset($flosc_new_settings[$flosc_ik]);
                        }
                    }
                    $flosc_new_settings['identity'] = $flosc_id;
                    
                    update_option($flosc_flow_key, $flosc_new_settings);
                    $flosc_all_flows[$flosc_ivr_file]['settings'] = $flosc_new_settings;
                }
                $flosc_all_saved = true;
            }
            ?>
            
            <!-- View Toggle -->
            <div class="flosc-view-toggle-row">
                <a href="<?php echo esc_url( '?page=flosc-settings&ivr=' . rawurlencode( $flosc_selected_ivr ) . '&tab=identity&view=single' ); ?>" 
                   class="button <?php echo esc_attr( $flosc_view_mode === 'single' ? 'button-primary' : '' ); ?>">
                    Single Flow
                </a>
                <a href="<?php echo esc_url( '?page=flosc-settings&ivr=' . rawurlencode( $flosc_selected_ivr ) . '&tab=identity&view=all' ); ?>" 
                   class="button <?php echo esc_attr( $flosc_view_mode === 'all' ? 'button-primary' : '' ); ?>">
                    All Flows (<?php echo count($flosc_ivr_files); ?>)
                </a>
            </div>
            
            <?php if (isset($all_saved)): ?>
                <div class="notice notice-success is-dismissible"><p>✓ All flows saved!</p></div>
            <?php endif; ?>
            <?php if (isset($flosc_individual_saved)): ?>
                <div class="notice notice-success is-dismissible"><p>✓ Saved: <?php echo esc_html($flosc_individual_saved); ?></p></div>
            <?php endif; ?>
            
            <?php if ($flosc_view_mode === 'all'): ?>
            <?php
            $flosc_identity_docs_url_all = add_query_arg([
                'page' => 'flosc-settings',
                'ivr'  => $flosc_selected_ivr,
                'tab'  => 'documentation',
                'doc'  => 'ref-admin',
            ], admin_url('admin.php')) . '#tab-identity';
            ?>
            
            <!-- ALL FLOWS - FULLY EXPANDED INLINE EDITING -->
            <div class="flosc-flow-editor-all">
                <div class="flosc-flow-editor-all__header">
                    <h2 class="flosc-flow-editor-all__title">
                        <span>All FLOSC Flows &mdash; Identity Settings</span>
                        <a href="<?php echo esc_url($flosc_identity_docs_url_all); ?>" class="flosc-flow-editor-all__docs-link">Docs</a>
                    </h2>
                    <p class="flosc-flow-editor-all__subtitle">
                        All flows expanded. Edit any field, then save individually or save all at bottom.
                    </p>
                </div>
                
                <?php foreach ($flosc_all_flows as $flosc_ivr_file => $flosc_flow_data): 
                    $flosc_settings = $flosc_flow_data['settings'];
                    // v2.0.0: Merge identity sub-array up for form display
                    // Identity fields are stored nested but form fields read flat
                    $flosc_si = $flosc_settings['identity'] ?? [];
                    foreach (['name', 'title', 'tagline', 'primary_color', 'chatlogo_url', 'favicon_url', 'badgeUrl', 'share_text', 'privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'] as $flosc__ik) {
                        if (isset($flosc_si[$flosc__ik]) && !isset($flosc_settings[$flosc__ik])) {
                            $flosc_settings[$flosc__ik] = $flosc_si[$flosc__ik];
                        }
                    }
                    $flosc_prefix = 'flow_' . md5($flosc_ivr_file) . '_';
                    $flosc_is_current = ($flosc_ivr_file === $flosc_selected_ivr);
                    $flosc_flow_block_classes = 'flosc-flow-block' . ( $flosc_is_current ? ' is-current' : '' );
                    // v1.3.5: Preserve underscores in default slug
                    $flosc_default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($flosc_ivr_file, PATHINFO_FILENAME)));
                    $flosc_slug = $flosc_settings['slug'] ?? $flosc_default_slug;
                    $flosc_flow_url = home_url('/' . $flosc_slug . '/');
                    $flosc_full_url = !empty($flosc_settings['domain']) ? 'https://' . $flosc_settings['domain'] . '/' : $flosc_flow_url;
                    $flosc_status_value = $flosc_settings['status'] ?? 'active';
                    $flosc_status_badge_class = 'flosc-flow-status-badge' . ( $flosc_status_value === 'active' ? ' is-active' : ' is-draft' );
                ?>
                
                <div class="<?php echo esc_attr( $flosc_flow_block_classes ); ?>">
                    
                    <!-- Flow Header with IVR file -->
                    <div class="flosc-flow-block__header">
                        <div>
                            <h3 class="flosc-flow-block__title">
                                <?php echo esc_html($flosc_settings['name'] ?? $flosc_ivr_file); ?>
                                <?php if ($flosc_is_current): ?>
                                    <span class="flosc-flow-block__current-badge">CURRENT</span>
                                <?php endif; ?>
                            </h3>
                            <div class="flosc-flow-block__file-row">
                                <code class="flosc-flow-block__file-pill"><?php echo esc_html($flosc_ivr_file); ?></code>
                            </div>
                        </div>
                        <div class="flosc-flow-block__status-wrap">
                            <span class="<?php echo esc_attr( $flosc_status_badge_class ); ?>">
                                <?php echo esc_html(ucfirst($flosc_status_value)); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- URL Mapping Summary -->
                    <div class="flosc-flow-block__url-box">
                        <div class="flosc-flow-block__url-label">This flow is accessible at:</div>
                        <div class="flosc-flow-block__url-value">
                            <a href="<?php echo esc_url($flosc_flow_url); ?>" target="_blank" class="flosc-flow-block__url-link">
                                <?php echo esc_html($flosc_flow_url); ?> &#8599;
                            </a>
                            <?php if (!empty($flosc_settings['domain'])): ?>
                                <span class="flosc-flow-block__url-arrow">&rarr;</span>
                                <a href="https://<?php echo esc_attr($flosc_settings['domain']); ?>/" target="_blank" class="flosc-flow-block__url-link">
                                    https://<?php echo esc_html($flosc_settings['domain']); ?>/ &#8599;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- ALL EDITABLE FIELDS - Slug first -->
                    <div class="flosc-flow-block__grid">
                        
                        <!-- Left Column -->
                        <div>
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label">URL Slug <span class="flosc-flow-field__required">(required)</span></label>
                                <div class="flosc-flow-field__slug-row">
                                    <code class="flosc-flow-field__slug-code"><?php echo esc_html( home_url('/') ); ?></code>
                                    <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>slug" 
                                         value="<?php echo esc_attr($flosc_slug); ?>"
                                           placeholder="myapp"
                                           class="flosc-flow-input flosc-flow-input--slug">
                                    <code class="flosc-flow-field__slug-code">/</code>
                                </div>
                                <p class="flosc-flow-field__hint">The URL path where this flow is served on your WordPress site</p>
                            </div>
                            
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label">Custom Domain</label>
                                <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>domain" 
                                       value="<?php echo esc_attr($flosc_settings['domain'] ?? ''); ?>"
                                        placeholder="e.g., flow.example.com"
                                       class="flosc-flow-input">
                                <p class="flosc-flow-field__hint">Custom domain pointing to this flow</p>
                            </div>
                            
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label"><?php echo esc_html__( 'Flow Name', 'flosc' ); ?></label>
                                <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>name" 
                                       value="<?php echo esc_attr($flosc_settings['name'] ?? ''); ?>"
                                        placeholder="<?php echo esc_attr__( 'e.g., dainis.net chat', 'flosc' ); ?>"
                                       class="flosc-flow-input">
                                <p class="flosc-flow-field__hint"><?php echo esc_html__( 'This is the name of this floscFlow, shown in the “Switch Flow” pull-down menu.', 'flosc' ); ?></p>
                            </div>
                            
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label"><?php echo esc_html__( 'Title', 'flosc' ); ?></label>
                                <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>title" 
                                       value="<?php echo esc_attr($flosc_settings['title'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr__( 'e.g., Standard American English Pronunciation', 'flosc' ); ?>"
                                       class="flosc-flow-input">
                                <p class="flosc-flow-field__hint"><?php echo esc_html__( 'Public flow description name. Shown under the personality; sent to the AI as this flow’s flow description.', 'flosc' ); ?></p>
                            </div>
                            
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label"><?php echo esc_html__( 'Tagline', 'flosc' ); ?></label>
                                <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>tagline" 
                                       value="<?php echo esc_attr($flosc_settings['tagline'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr__( 'e.g., Clear spoken English, taught in conversation', 'flosc' ); ?>"
                                       class="flosc-flow-input">
                                <p class="flosc-flow-field__hint"><?php echo esc_html__( 'One-line expansion of the Title. Not the FLOSC acronym, not the personality. Leave empty to hide.', 'flosc' ); ?></p>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div>
                            <div class="flosc-flow-field-row">
                                <div>
                                    <label class="flosc-flow-field__label">Brand Color</label>
                                    <input type="color" name="<?php echo esc_attr( $flosc_prefix ); ?>primary_color" 
                                           value="<?php echo esc_attr($flosc_settings['primary_color'] ?? '#4f46e5'); ?>"
                                           class="flosc-flow-input-color">
                                </div>
                                <div>
                                    <label class="flosc-flow-field__label">Status</label>
                                    <select name="<?php echo esc_attr( $flosc_prefix ); ?>status" class="flosc-flow-input flosc-flow-input--status">
                                        <option value="active" <?php selected($flosc_settings['status'] ?? '', 'active'); ?>>Active</option>
                                        <option value="draft" <?php selected($flosc_settings['status'] ?? '', 'draft'); ?>>Draft</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flosc-flow-field flosc-flow-field--tight">
                                <label class="flosc-flow-field__label">Chat Logo URL</label>
                                <input type="url" name="<?php echo esc_attr( $flosc_prefix ); ?>chatlogo_url"
                                       value="<?php echo esc_attr($flosc_settings['chatlogo_url'] ?? ''); ?>"
                                       placeholder="https://..."
                                       class="flosc-flow-input">
                            </div>

                            <div class="flosc-flow-field flosc-flow-field--tight">
                                <label class="flosc-flow-field__label">Favicon URL</label>
                                <input type="url" name="<?php echo esc_attr( $flosc_prefix ); ?>favicon_url"
                                       value="<?php echo esc_attr($flosc_settings['favicon_url'] ?? ''); ?>"
                                       placeholder="https://..."
                                       class="flosc-flow-input">
                            </div>

                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label">Badge Image URL</label>
                                <input type="url" name="<?php echo esc_attr( $flosc_prefix ); ?>badgeUrl"
                                       value="<?php echo esc_attr($flosc_settings['badgeUrl'] ?? ''); ?>"
                                       placeholder="https://..."
                                       class="flosc-flow-input">
                            </div>
                            
                            <!-- DNS Setup Info (if custom domain set) -->
                            <?php if (!empty($flosc_settings['domain'])): ?>
                            <div class="flosc-flow-dns-inline-box">
                                <div class="flosc-flow-dns-inline-box__title">DNS Setup for <?php echo esc_html($flosc_settings['domain']); ?></div>
                                <div class="flosc-flow-dns-inline-box__body">
                                    Point your domain to: <code class="flosc-flow-dns-inline-box__code"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code><br>
                                    <a href="https://flosc.ai/docs/dns" target="_blank" class="flosc-flow-dns-inline-box__link">Full DNS guide →</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flosc-flow-policies">
                        <h4 class="flosc-flow-policies__title">Policy Pages Content (Per Flow)</h4>

                        <div class="flosc-flow-field flosc-flow-field--tight">
                            <label class="flosc-flow-field__label">Privacy Policy Content</label>
                            <textarea name="<?php echo esc_attr( $flosc_prefix ); ?>privacy_policy_content" rows="8" class="flosc-flow-textarea"><?php echo esc_textarea($flosc_settings['privacy_policy_content'] ?? ''); ?></textarea>
                            <p class="flosc-flow-field__hint">Shown at /privacy for this flow domain. HTML is allowed.</p>
                        </div>

                        <div class="flosc-flow-field flosc-flow-field--tight">
                            <label class="flosc-flow-field__label">Terms of Service Content</label>
                            <textarea name="<?php echo esc_attr( $flosc_prefix ); ?>terms_of_service_content" rows="8" class="flosc-flow-textarea"><?php echo esc_textarea($flosc_settings['terms_of_service_content'] ?? ''); ?></textarea>
                            <p class="flosc-flow-field__hint">Shown at /terms-of-service for this flow domain. HTML is allowed.</p>
                        </div>

                        <div class="flosc-flow-field flosc-flow-field--tight">
                            <label class="flosc-flow-field__label">Data Deletion Content</label>
                            <textarea name="<?php echo esc_attr( $flosc_prefix ); ?>data_deletion_content" rows="8" class="flosc-flow-textarea"><?php echo esc_textarea($flosc_settings['data_deletion_content'] ?? ''); ?></textarea>
                            <p class="flosc-flow-field__hint">Shown at /data-deletion for this flow domain. HTML is allowed.</p>
                        </div>

                        <div>
                            <label class="flosc-flow-field__label">Platform Compliance Content</label>
                            <textarea name="<?php echo esc_attr( $flosc_prefix ); ?>platform_compliance_content" rows="8" class="flosc-flow-textarea"><?php echo esc_textarea($flosc_settings['platform_compliance_content'] ?? ''); ?></textarea>
                            <p class="flosc-flow-field__hint">Appended to all three policy pages (privacy, terms-of-service, data-deletion). HTML is allowed.</p>
                        </div>
                    </div>
                    
                    <!-- Save This Flow Button -->
                    <div class="flosc-flow-block__save-row">
                        <button type="submit" name="flosc_save_flow" value="<?php echo esc_attr($flosc_ivr_file); ?>" class="button button-secondary">
                            Save <?php echo esc_html($flosc_settings['name'] ?? $flosc_ivr_file); ?>
                        </button>
                    </div>
                </div>
                
                <?php endforeach; ?>
                
                <!-- DNS Setup Guide -->
                <div class="flosc-domain-guide">
                    <h4 class="flosc-domain-guide__title">Custom Domain DNS Setup</h4>
                    
                    <p class="flosc-domain-guide__intro">
                        <strong>How it works:</strong> Your custom domain (e.g., <code>flosc.ai</code>) will point to this WordPress installation, 
                        and FLOSC will automatically serve the correct flow when visitors access that domain.
                    </p>
                    
                    <div class="flosc-domain-guide__step">
                        <p class="flosc-domain-guide__step-title"><strong>Step 1: Configure your DNS records</strong></p>
                        <ul class="flosc-domain-guide__step-list">
                            <li class="flosc-domain-guide__step-list-item">Add a <strong>CNAME record</strong> pointing your domain to: <code class="flosc-domain-guide__code"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code></li>
                            <li class="flosc-domain-guide__step-list-item">Or add an <strong>A record</strong> pointing to your server's IP address</li>
                            <li>For www subdomain, add another CNAME pointing <code>www.yourdomain.com</code> → <code>yourdomain.com</code></li>
                        </ul>
                    </div>
                    
                    <div class="flosc-domain-guide__step">
                        <p class="flosc-domain-guide__step-title"><strong>Step 2: Configure your web server</strong></p>
                        <p class="flosc-domain-guide__step-text">
                            Your web server (Apache/Nginx) must be configured to accept requests for the custom domain 
                            and route them to this WordPress installation. Contact your hosting provider if needed.
                        </p>
                    </div>
                    
                    <div class="flosc-domain-guide__step flosc-domain-guide__step--last">
                        <p class="flosc-domain-guide__step-title"><strong>Step 3: Enter the domain above</strong></p>
                        <p class="flosc-domain-guide__step-text">
                            Enter just the domain name (e.g., <code>flosc.ai</code>) in the Custom Domain field for your flow. 
                            FLOSC will handle the rest!
                        </p>
                    </div>
                </div>
                
                <!-- Save All Flows Button -->
                <div class="flosc-flow-save-all-row">
                    <button type="submit" name="flosc_save_all_flows" value="1" class="button button-primary button-large">
                        Save All Flows
                    </button>
                    <p class="flosc-flow-save-all-row__hint">Saves identity settings for all <?php echo count($flosc_all_flows); ?> flows at once</p>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- SINGLE FLOW EDIT VIEW -->
            <div class="card flosc-settings-card">
                <?php flosc_tab_header('🏷️', 'Identity'); ?>
                <?php
                $flosc_identity_docs_url = add_query_arg([
                    'page' => 'flosc-settings',
                    'ivr'  => $flosc_selected_ivr,
                    'tab'  => 'documentation',
                    'doc'  => 'ref-admin',
                ], admin_url('admin.php')) . '#tab-identity';
                ?>
                <div class="flosc-settings-docs-row">
                    <a href="<?php echo esc_url($flosc_identity_docs_url); ?>" class="flosc-settings-docs-link">Docs</a>
                </div>
                
                <!-- URL Mapping Info Box -->
                <?php 
                // v1.3.5: Preserve underscores in default slug
                $flosc_default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($flosc_selected_ivr, PATHINFO_FILENAME)));
                $flosc_current_slug = $flosc_flow_settings['slug'] ?? $flosc_default_slug;
                $flosc_flow_url = home_url('/' . $flosc_current_slug . '/');
                ?>
                <div class="flosc-flow-url-box">
                    <div class="flosc-flow-url-box__label">This flow is accessible at:</div>
                    <div class="flosc-flow-url-box__value">
                        <a href="<?php echo esc_url($flosc_flow_url); ?>" target="_blank" class="flosc-flow-url-box__link">
                            <?php echo esc_html($flosc_flow_url); ?> &#8599;
                        </a>
                        <?php if (!empty($flosc_flow_settings['domain'])): ?>
                            <span class="flosc-flow-url-box__arrow">&rarr;</span>
                            <a href="https://<?php echo esc_attr($flosc_flow_settings['domain']); ?>/" target="_blank" class="flosc-flow-url-box__link">
                                https://<?php echo esc_html($flosc_flow_settings['domain']); ?>/ &#8599;
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="flosc-flow-url-box__meta">
                        IVR File: <code class="flosc-flow-url-box__ivr-file"><?php echo esc_html($flosc_selected_ivr); ?></code>
                    </div>
                </div>
                
                <?php $flosc_fi = $flosc_flow_settings['identity'] ?? []; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <div class="flosc-flow-slug-row">
                                <code class="flosc-flow-slug-row__code"><?php echo esc_html( home_url('/') ); ?></code>
                                <input type="text" id="flow_slug" name="flow_slug" class="flosc-flow-slug-row__input"
                                       value="<?php echo esc_attr($flosc_current_slug); ?>"
                                       placeholder="myapp">
                                <code class="flosc-flow-slug-row__code">/</code>
                            </div>
                            <p class="description">The URL path where this flow is served. Lowercase letters, numbers, hyphens only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_domain">Custom Domain</label></th>
                        <td>
                            <input type="text" id="flow_domain" name="flow_domain" class="regular-text"
                                   value="<?php echo esc_attr($flosc_flow_settings['domain'] ?? ''); ?>"
                                placeholder="e.g., flow.example.com">
                            <p class="description">Custom domain pointing to this flow</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_name"><?php echo esc_html__( 'Flow Name', 'flosc' ); ?></label></th>
                        <td>
                            <input type="text" id="flow_name" name="flow_name" class="regular-text"
                                   value="<?php echo esc_attr($flosc_fi['name'] ?? ''); ?>"
                                placeholder="<?php echo esc_attr__( 'e.g., dainis.net chat', 'flosc' ); ?>">
                            <p class="description">
                                <?php echo esc_html__( 'This is the name of this floscFlow, shown in the “Switch Flow” pull-down menu.', 'flosc' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_title"><?php echo esc_html__( 'Title', 'flosc' ); ?></label></th>
                        <td>
                            <input type="text" id="flow_title" name="flow_title" class="regular-text"
                                   value="<?php echo esc_attr($flosc_fi['title'] ?? ''); ?>"
                                   placeholder="<?php echo esc_attr__( 'e.g., Standard American English Pronunciation', 'flosc' ); ?>">
                            <p class="description">
                                <?php echo esc_html__( 'Public flow description name. Visitors see it under the personality on the landing screen and as the browser-tab suffix. The AI uses it as this flow’s flow description — not the floscFlow name and not the personality.', 'flosc' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_tagline"><?php echo esc_html__( 'Tagline', 'flosc' ); ?></label></th>
                        <td>
                            <input type="text" id="flow_tagline" name="flow_tagline" class="regular-text"
                                   value="<?php echo esc_attr($flosc_fi['tagline'] ?? ''); ?>"
                                   placeholder="<?php echo esc_attr__( 'e.g., Clear spoken English, taught in conversation', 'flosc' ); ?>">
                            <p class="description">
                                <?php echo esc_html__( 'One-line expansion of the Title. Visitors see it under the Title. The AI uses it as the flow description’s short description. Not the FLOSC acronym, not the personality. Leave empty to hide.', 'flosc' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_primary_color">Brand Color</label></th>
                        <td>
                            <input type="color" id="flow_primary_color" name="flow_primary_color"
                                   value="<?php echo esc_attr($flosc_fi['primary_color'] ?? '#4f46e5'); ?>"
                                class="flosc-flow-color-input">
                            <span id="color-preview" class="flosc-flow-color-preview" data-color="<?php echo esc_attr($flosc_fi['primary_color'] ?? '#4f46e5'); ?>">
                                Preview
                            </span>
                            <p class="description">Lesson highlights, form buttons, focus rings, carousel controls. Sets <code>--flosc-primary</code>.</p>
                            <?php ob_start(); ?>
                            (function () {
                                var input = document.getElementById('flow_primary_color');
                                var preview = document.getElementById('color-preview');
                                if (!input || !preview) return;
                                function syncPreview(hex) {
                                    preview.setAttribute('data-color', hex);
                                    preview.style.setProperty('--flosc-preview-bg', hex);
                                }
                                syncPreview(preview.getAttribute('data-color') || input.value || '#4f46e5');
                                input.addEventListener('input', function (e) {
                                    syncPreview(e.target.value);
                                });
                            })();
                            <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_chatlogo_url">Chat Logo</label></th>
                        <td>
                            <div class="flosc-flow-media-row">
                                <input type="url" id="flow_chatlogo_url" name="flow_chatlogo_url" class="regular-text"
                                       value="<?php echo esc_attr($flosc_fi['chatlogo_url'] ?? ''); ?>"
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_chatlogo">Choose Image</button>
                                <img src="<?php echo esc_url($flosc_fi['chatlogo_url'] ?? ''); ?>" 
                                     alt="" id="flosc_chatlogo_preview"
                                     class="flosc-flow-media-preview flosc-flow-media-preview--chatlogo<?php echo empty($flosc_fi['chatlogo_url']) ? ' flosc-hidden' : ''; ?>">
                            </div>
                            <p class="description"><?php echo esc_html__( 'Image shown in the chat header beside the personality name. Wider/rectangular formats welcome.', 'flosc' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_favicon_url">Favicon</label></th>
                        <td>
                            <div class="flosc-flow-media-row">
                                <input type="url" id="flow_favicon_url" name="flow_favicon_url" class="regular-text"
                                       value="<?php echo esc_attr($flosc_fi['favicon_url'] ?? ''); ?>"
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_favicon">Choose Image</button>
                                <img src="<?php echo esc_url($flosc_fi['favicon_url'] ?? ''); ?>" 
                                     alt="" id="flosc_favicon_preview"
                                     class="flosc-flow-media-preview flosc-flow-media-preview--favicon<?php echo empty($flosc_fi['favicon_url']) ? ' flosc-hidden' : ''; ?>">
                            </div>
                            <p class="description">Browser tab icon. Square PNG recommended (512&times;512+).</p>
                        </td>
                    </tr>
                    <?php wp_enqueue_media(); ?>
                    <tr>
                        <th><label for="flow_badgeUrl">Badge Image</label></th>
                        <td>
                            <div class="flosc-flow-media-row">
                                <input type="url" id="flow_badgeUrl" name="flow_badgeUrl" class="regular-text"
                                       value="<?php echo esc_attr($flosc_fi['badgeUrl'] ?? ''); ?>"
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_badge">Choose Image</button>
                                <img src="<?php echo esc_url($flosc_fi['badgeUrl'] ?? ''); ?>" 
                                     alt="" id="flosc_badge_preview"
                                     class="flosc-flow-media-preview flosc-flow-media-preview--badge<?php echo empty($flosc_fi['badgeUrl']) ? ' flosc-hidden' : ''; ?>">
                            </div>
                            <p class="description">Badge shown in the AI welcome message. Leave empty for no badge.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <?php $flosc_status_ui = (string) ($flosc_flow_settings['status'] ?? 'active'); ?>
                                <option value="active" <?php selected($flosc_status_ui, 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($flosc_status_ui, 'draft'); ?>>Draft</option>
                            </select>
                            <p class="description">Draft flows are only visible to admins.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_privacy_policy_content">Privacy Policy Content</label></th>
                        <td>
                            <textarea id="flow_privacy_policy_content" name="flow_privacy_policy_content" class="large-text" rows="8"><?php echo esc_textarea($flosc_fi['privacy_policy_content'] ?? ''); ?></textarea>
                            <p class="description">Per-flow content shown at /privacy. HTML is allowed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_terms_of_service_content">Terms of Service Content</label></th>
                        <td>
                            <textarea id="flow_terms_of_service_content" name="flow_terms_of_service_content" class="large-text" rows="8"><?php echo esc_textarea($flosc_fi['terms_of_service_content'] ?? ''); ?></textarea>
                            <p class="description">Per-flow content shown at /terms-of-service. HTML is allowed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_data_deletion_content">Data Deletion Content</label></th>
                        <td>
                            <textarea id="flow_data_deletion_content" name="flow_data_deletion_content" class="large-text" rows="8"><?php echo esc_textarea($flosc_fi['data_deletion_content'] ?? ''); ?></textarea>
                            <p class="description">Per-flow content shown at /data-deletion. HTML is allowed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_platform_compliance_content">Platform Compliance Content</label></th>
                        <td>
                            <textarea id="flow_platform_compliance_content" name="flow_platform_compliance_content" class="large-text" rows="8"><?php echo esc_textarea($flosc_fi['platform_compliance_content'] ?? ''); ?></textarea>
                            <p class="description">Appended to all three policy pages (privacy, terms-of-service, data-deletion). HTML is allowed.</p>
                        </td>
                    </tr>
                </table>
                
                <?php ob_start(); ?>
                (function() {
                    function setupMediaButton(buttonId, inputId, previewId) {
                        var btn = document.getElementById(buttonId);
                        if (!btn) return;
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            var frame = wp.media({ title: 'Choose Image', multiple: false, library: { type: 'image' } });
                            frame.on('select', function() {
                                var url = frame.state().get('selection').first().toJSON().url;
                                document.getElementById(inputId).value = url;
                                var preview = document.getElementById(previewId);
                                preview.src = url;
                                preview.style.display = '';
                            });
                            frame.open();
                        });
                    }
                    setupMediaButton('flosc_upload_chatlogo', 'flow_chatlogo_url', 'flosc_chatlogo_preview');
                    setupMediaButton('flosc_upload_favicon', 'flow_favicon_url', 'flosc_favicon_preview');
                    setupMediaButton('flosc_upload_badge', 'flow_badgeUrl', 'flosc_badge_preview');
                })();
                <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
                
                <!-- DNS Setup Info (if custom domain set) -->
                <?php if (!empty($flosc_flow_settings['domain'])): ?>
                <div class="flosc-flow-dns-box">
                    <h4 class="flosc-flow-dns-box__title">DNS Setup for <?php echo esc_html($flosc_flow_settings['domain']); ?></h4>
                    <p class="flosc-flow-dns-box__text">
                        Point your domain to: <code class="flosc-flow-dns-box__code"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code>
                    </p>
                    <p class="flosc-flow-dns-box__text flosc-flow-dns-box__text--spaced">
                        <a href="https://flosc.ai/docs/dns" target="_blank" class="flosc-flow-dns-box__link">Full DNS Configuration Guide</a>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php endif; ?>
            
        <?php elseif ($flosc_active_tab === 'ivr-messages'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ivr-messages.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'style'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/chat-styling.php'; ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/companion.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'ai'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-configuration.php'; ?>
        <?php elseif ($flosc_active_tab === 'token-management'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/token-management.php'; ?>
        <?php elseif ($flosc_active_tab === 'ai-knowledge'): ?>
            <?php $flosc_redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . rawurlencode($flosc_selected_ivr) . '&tab=knowledge-base&view=single'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($flosc_redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($flosc_redirect_url); ?>">Knowledge Base</a>&hellip;</p>
        <?php elseif ($flosc_active_tab === 'ai-guide'): ?>
            <?php $flosc_redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . rawurlencode($flosc_selected_ivr) . '&tab=documentation&doc=ref-ai-config'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($flosc_redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($flosc_redirect_url); ?>">AI Configuration Guide in Documentation</a>&hellip;</p>
        <?php elseif ($flosc_active_tab === 'knowledge'): ?>
            <?php $flosc_redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . rawurlencode($flosc_selected_ivr) . '&tab=knowledge-base&view=single'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($flosc_redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($flosc_redirect_url); ?>">Knowledge Base</a>&hellip;</p>
            
        <?php elseif ($flosc_active_tab === 'quiz'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/quiz.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'email'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/email.php'; ?>

        <?php elseif ($flosc_active_tab === 'contact-form'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/contact-form.php'; ?>
            
        <?php elseif (in_array($flosc_active_tab, ['content', 'member-levels', 'lessons'], true)): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/content.php'; ?>

        <?php elseif ($flosc_active_tab === 'knowledge-base'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/knowledge-base.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'autoprompts'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/autoprompts.php'; ?>

        <?php elseif ($flosc_active_tab === 'trajectories'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/trajectories.php'; ?>

        <?php elseif ($flosc_active_tab === 'offers'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/offers.php'; ?>

        <?php elseif ($flosc_active_tab === 'login'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/login-registration.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'payments'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/payments.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'sso'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/sso.php'; ?>

        <?php elseif ($flosc_active_tab === 'engagement'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/engagement.php'; ?>

        <?php elseif ($flosc_active_tab === 'administration'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/administration.php'; ?>

        <?php elseif ($flosc_active_tab === 'ui'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ui-navigation.php'; ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/chat-navigation.php'; ?>

        <?php elseif ($flosc_active_tab === 'concierge'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/concierge.php'; ?>

        <?php elseif ($flosc_active_tab === 'chat-logs'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/chat-logs.php'; ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-feedback.php'; ?>

        <?php elseif ($flosc_active_tab === 'documentation'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/documentation.php'; ?>

        <?php endif; ?>

        <?php if ($flosc_active_tab !== 'documentation' && $flosc_active_tab !== 'autoprompts' && $flosc_active_tab !== 'da1' && $flosc_active_tab !== 'trajectories' && $flosc_active_tab !== 'concierge'): ?>
        <p class="submit flosc-settings-submit-row">
            <?php // form= keeps submit bound if AI tab closed #flosc-settings-form early (no nested forms). ?>
            <button type="submit" name="flosc_save" value="1" form="flosc-settings-form" class="button button-primary button-large">
                Save Settings for <?php echo esc_html($flosc_flow_settings['identity']['name'] ?? $flosc_selected_ivr); ?>
            </button>
        </p>
        <?php endif; ?>
        
        <?php flosc_tab_footer(); ?>
    <?php if (empty($GLOBALS['flosc_settings_form_closed_early'])): ?>
    </form>
    <?php endif; ?>
</div>

<!-- Styles in assets/css/flosc-admin.css -->
