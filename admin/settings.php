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
        $version = defined('FLOSC_VERSION') ? FLOSC_VERSION : '?.?.?';
        echo '<div class="flosc-tab-footer">';
        echo '<span class="flosc-tab-footer__version">FLOSC v' . esc_html($version) . '</span>';
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
        // Rules are stored as: '^lesaep/?$' => 'index.php?flosc_app=1&flosc_ivr=...'
        foreach ($rules as $regex => $query) {
            // Check if this rule matches our slug (the regex starts with ^slug)
            if (preg_match('/^\^' . preg_quote($slug, '/') . '/', $regex)) {
                return 'ok';
            }
            // Also check query string for flosc_app (indicates FLOSC route)
            if (strpos($regex, $slug) !== false && strpos($query, 'flosc_app=1') !== false) {
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
        $status = flosc_check_permalink_status($slug);
        $last_flush = get_option('flosc_last_permalink_flush', null);
        
        $colors = [
            'ok' => ['class' => 'flosc-permalink-badge--ok', 'text' => '&#10003; Permalinks OK'],
            'missing' => ['class' => 'flosc-permalink-badge--missing', 'text' => '&#9888; Needs Flush'],
            'unknown' => ['class' => 'flosc-permalink-badge--unknown', 'text' => '? Status Unknown'],
        ];
        
        $color = $colors[$status];
        
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
        global $wpdb;

        $flosc_ivr_filename = basename((string) $flosc_ivr_filename);
        $target_stem = sanitize_key(pathinfo($flosc_ivr_filename, PATHINFO_FILENAME));
        $default_key = 'flosc_flow_' . $target_stem;

        // Start with deterministic default key and score it conservatively.
        $best_key = $default_key;
        $best_score = -1;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only scan of plugin flow options for deterministic key resolution
        $flosc_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only scan of plugin flow options for deterministic key resolution
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'flosc_flow_%'"
        );

        if (!is_array($flosc_rows)) {
            return $default_key;
        }

        foreach ($flosc_rows as $flosc_row) {
            $option_name = (string) ($row->option_name ?? '');
            if ($option_name === '') {
                continue;
            }

            $flosc_settings = maybe_unserialize($row->option_value ?? null);
            if (!is_array($flosc_settings)) {
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
            if (isset($flosc_settings['ivr_messages']) && is_array($flosc_settings['ivr_messages'])) {
                $message_count = count($flosc_settings['ivr_messages']);
            }

            $score = 0;
            if ($option_name === $default_key) {
                $score += 1000;
            }
            if ($matches_primary) {
                $score += 500;
            }
            if ($matches_active) {
                $score += 300;
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

// Set defaults if empty AND save them (v1.3.0 fix)
if (empty($flosc_flow_settings)) {
    // v1.3.5: Preserve underscores in default slug (don't use sanitize_title which converts to hyphens)
    $flosc_default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($flosc_selected_ivr, PATHINFO_FILENAME)));
    $flosc_flow_settings = [
        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $flosc_selected_ivr)),
        'title' => '',
        'tagline' => '',
        'slug' => $flosc_default_slug,
        'primary_color' => '#4f46e5',
        'status' => 'active',
    ];
    // v1.3.0: Save defaults so rewrite rules can find them
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

if (isset($flosc_post['flosc_set_default_flow']) && wp_verify_nonce(sanitize_text_field($flosc_post['flosc_default_flow_nonce'] ?? ''), 'flosc_set_default_flow')) {
    if (!flosc_flows()->can_access_flow_admin($flosc_selected_flow_id)) {
        wp_die(esc_html__('You do not have permission to set a default FLOSC flow.', 'flosc'));
    }

    update_user_meta($flosc_current_user_id, $flosc_default_ivr_meta_key, $flosc_selected_ivr);
    $flosc_user_default_ivr = $flosc_selected_ivr;

    $flosc_redirect_url = add_query_arg([
        'page' => 'flosc-settings',
        'ivr'  => $flosc_selected_ivr,
        'tab'  => $flosc_active_tab,
        'view' => $flosc_identity_view,
        'default_set' => '1',
    ], admin_url('admin.php'));

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
        'missing_ivr_message',
        // AI configuration
        'ai_base_prompt', 'ai_prompt_freeline', 'ai_prompt_login', 'ai_prompt_offer', 'ai_prompt_sale', 'ai_prompt_content',
        'phase_outcomes_freeline', 'phase_outcomes_login', 'phase_outcomes_offer', 'phase_outcomes_sale', 'phase_outcomes_content',
        // AI knowledge
        'ai_personality_traits', 'ai_mission', 'ai_context_awareness', 'ai_freeline_restrictions', 'ai_member_access', 'ai_boundaries',
        'ai_topic_scope', 'ai_off_topic_message', 'ai_off_topic_links',
        // Email
        'email_body',
        'guest_welcome_body', 'guest_day10_body', 'guest_day20_body', 'guest_day28_body',
        // Payments
        'manual_payment_instructions',
        // Identity policy pages
        'privacy_policy_content',
        'terms_of_service_content',
        'data_deletion_content',
        'platform_compliance_content',
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
    if ($flosc_active_tab === 'ai') {
        foreach (['ai_enable_ivr_context', 'ai_enable_content_access', 'ai_enable_chaining'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$cb}"])) {
                $flosc_new_settings[$cb] = '';
            }
        }
    }
    if ($flosc_active_tab === 'email') {
        foreach (['email_on_quiz_complete', 'email_reengagement_enabled', 'email_weekly_summary'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$cb}"])) {
                $flosc_new_settings[$cb] = '';
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
    if ($flosc_active_tab === 'payments') {
        foreach (['stripe_enabled', 'paypal_enabled', 'manual_payments_enabled'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$cb}"])) {
                $flosc_new_settings[$cb] = '';
            }
        }
    }
    if ($flosc_active_tab === 'quiz') {
        foreach (['wpq_integration', 'ld_integration', 'qsm_integration'] as $flosc_cb) {
            if (!isset($flosc_post["flow_{$cb}"])) {
                $flosc_new_settings[$cb] = '';
            }
        }
        if (!isset($flosc_post['flow_enabled_quizzes'])) {
            $flosc_new_settings['enabled_quizzes'] = [];
        }
        foreach (['A', 'B', 'C', 'D', 'E'] as $flosc_letter) {
            if (!isset($flosc_post["flow_quiz_variant_{$letter}_enabled"])) {
                $flosc_new_settings["quiz_variant_{$letter}_enabled"] = '';
            }
        }
    }

    // v8.1.0: Member Levels tab — parse level registry + content protection repeaters
    if ($flosc_active_tab === 'member-levels') {
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

        // Sync to term_meta — class-content-protection.php reads from term_meta currently.
        // TODO: Refactor class-content-protection.php to read from flow_settings['protected_content']
        // and remove this sync entirely.
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

    // v3.0.0: Lessons tab — parse lesson_groups repeater
    // Repeater fields are NOT flow_-prefixed (lesson_group_quiz[], lesson_group_category[])
    if ($flosc_active_tab === 'lessons') {
        $flosc_group_quizzes    = $flosc_post['lesson_group_quiz']     ?? [];
        $flosc_group_categories = $flosc_post['lesson_group_category'] ?? [];
        $flosc_lesson_groups = [];
        foreach ($flosc_group_categories as $flosc_i => $cat) {
            $cat = sanitize_text_field($cat);
            if ($cat === '') continue; // Skip rows with no category selected
            $flosc_quiz = sanitize_text_field($flosc_group_quizzes[$flosc_i] ?? '');
            $flosc_lesson_groups[] = [
                'quiz_id'  => $flosc_quiz,
                'category' => $cat,
            ];
        }
        // Only overwrite lesson_groups when the form actually submitted rows.
        // An accidental save on an empty/wrong-flow form must not wipe existing config.
        if (!empty($flosc_lesson_groups)) {
            $flosc_new_settings['lesson_groups']    = $flosc_lesson_groups;
            $flosc_new_settings['lessons_category'] = $flosc_lesson_groups[0]['category'];
        }
    }

    // v1.8.2: UI & Nav tab uses WordPress options (global, not per-flow)
    if ($flosc_active_tab === 'ui') {
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
        $flosc_guest_labels  = $flosc_post['guest_menu_label']  ?? [];
        $flosc_guest_actions = $flosc_post['guest_menu_action'] ?? [];
        $flosc_new_guest_menu = [];
        foreach ($flosc_guest_labels as $flosc_i => $flosc_label) {
            $flosc_label  = sanitize_text_field($flosc_label);
            $action = sanitize_text_field($flosc_guest_actions[$flosc_i] ?? '');
            if ($flosc_label !== '' && $action !== '') {
                $flosc_new_guest_menu[] = ['label' => $flosc_label, 'action' => $action];
            }
        }
        update_option('flosc_guest_menu_items', $flosc_new_guest_menu);

        // v1.9.8: Member menu — dynamic items from repeater
        $flosc_member_labels  = $flosc_post['member_menu_label']  ?? [];
        $flosc_member_actions = $flosc_post['member_menu_action'] ?? [];
        $flosc_new_member_menu = [];
        foreach ($flosc_member_labels as $flosc_i => $flosc_label) {
            $flosc_label  = sanitize_text_field($flosc_label);
            $action = sanitize_text_field($flosc_member_actions[$flosc_i] ?? '');
            if ($flosc_label !== '' && $action !== '') {
                $flosc_new_member_menu[] = ['label' => $flosc_label, 'action' => $action];
            }
        }
        update_option('flosc_member_menu_items', $flosc_new_member_menu);

        // Login destination — v1.9.8: now a URL, not a key
        $flosc_login_dest = trim($flosc_post['flosc_login_destination'] ?? '');
        update_option('flosc_login_destination', $flosc_login_dest !== '' ? esc_url_raw($flosc_login_dest) : '');

        // Profile bar per-state settings
        $flosc_profile_bar = [
            'visitor' => [
                'name'  => sanitize_text_field($flosc_post['profile_bar_visitor_name'] ?? 'Visitor'),
                'badge' => sanitize_text_field($flosc_post['profile_bar_visitor_badge'] ?? 'Hope you enjoy our chat :-)'),
                'icon'  => sanitize_text_field($flosc_post['profile_bar_visitor_icon'] ?? '👋'),
            ],
            'guest' => [
                'badge' => sanitize_text_field($flosc_post['profile_bar_guest_badge'] ?? 'Guest'),
                'upgrade_label' => sanitize_text_field($flosc_post['profile_bar_guest_upgrade'] ?? 'Upgrade to Pro'),
            ],
            'member' => [
                'badge' => sanitize_text_field($flosc_post['profile_bar_member_badge'] ?? 'Member'),
            ],
        ];
        update_option('flosc_profile_bar', $flosc_profile_bar);
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
    $flosc_flow_settings = $flosc_new_settings;
    
    $flosc_saved = true;
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

?>
<div class="wrap flosc-admin">
    <h1 class="flosc-settings-title">
        FLOSC Settings 
        <span class="flosc-settings-version">v<?php echo esc_html(FLOSC_VERSION); ?></span>
    </h1>
    
    <?php if (isset($flosc_saved)): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Settings saved for <strong><?php echo esc_html($flosc_flow_settings['identity']['name'] ?? $flosc_selected_ivr); ?></strong></p>
        </div>
    <?php endif; ?>

    <?php if (isset($flosc_get['default_set'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Default flow set to <strong><?php echo esc_html($flosc_flow_selector_labels[$flosc_selected_ivr] ?? $flosc_selected_ivr); ?></strong>.</p>
        </div>
    <?php endif; ?>
    
    <!-- IVR File Selector = Flow Selector -->
    <div class="flosc-ivr-selector">

        <?php // Flow-context row above the selector: single-flow view highlights the active flow; all-flows view lists every flow with the active one highlighted + bold. ?>
        <?php if ($flosc_identity_view === 'all'): ?>
            <div class="flosc-ivr-context-row">
                <span class="flosc-ivr-context-label">All flows:</span>
                <?php foreach ($flosc_ivr_files as $flosc_file): $flosc_is_current_flow = ($flosc_file === $flosc_selected_ivr); ?>
                    <span class="flosc-ivr-chip <?php echo $flosc_is_current_flow ? 'is-current' : ''; ?>">
                        <?php echo esc_html($flosc_file); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="flosc-ivr-working-row">
                Editing flow:
                <span class="flosc-ivr-working-chip">
                    <?php echo esc_html($flosc_selected_flow_name); ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="flosc-ivr-controls-row">
            <label class="flosc-ivr-select-label">Switch Flow:</label>
            <select id="ivr-select" class="flosc-ivr-select-control">
                <?php foreach ($flosc_ivr_files as $flosc_file): ?>
                    <option value="<?php echo esc_attr($flosc_file); ?>" <?php selected($flosc_selected_ivr, $flosc_file); ?>>
                        <?php echo esc_html($flosc_flow_selector_labels[$flosc_file] ?? $flosc_file); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($flosc_flow_settings['status'] === 'active' && !empty($flosc_flow_settings['slug'])): ?>
                <a href="<?php echo esc_url($flosc_flow_url); ?>" target="_blank" class="button button-small">
                    View App &#8599;
                </a>
            <?php endif; ?>
        </div>

        <div class="flosc-ivr-meta-row">
            floscFlowFileName: <span class="flosc-ivr-meta-filename"><?php echo esc_html($flosc_selected_ivr); ?></span>
            <?php if ($flosc_user_default_ivr === $flosc_selected_ivr): ?>
                <span class="flosc-ivr-default-note">(default)</span>
            <?php endif; ?>
        </div>
        
        <!-- Permalink Status Row -->
        <div class="flosc-ivr-permalink-row">
            <?php flosc_permalink_status_indicator($flosc_flow_settings['slug'] ?? ''); ?>
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

        const tab = '<?php echo esc_js($flosc_active_tab); ?>';
        const tabTitles = {
            'flow': 'Flow',
            'identity': 'Identity',
            'ivr-messages': 'IVR Management',
            'autoprompts': 'AutoPrompt Panel',
            'member-levels': 'Member Levels',
            'offers': 'Offers',
            'login': 'Register and Login',
            'style': 'Style',
            'ui': 'UI and Nav',
            'ai': 'AI',
            'quiz': 'Quiz',
            'email': 'Email',
            'payments': 'Payments',
            'lessons': 'Lessons',
            'sso': 'SSO',
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
            'member-levels' => 'Member Levels',
            'offers'        => 'Offers',
            'login'         => 'Register & Login',
            'style'         => 'Style',
            'ui'            => 'UI & Nav',
            'ai'            => 'AI',
            'quiz'          => 'Quiz',
            'email'         => 'Email',
            'payments'      => 'Payments',
            'lessons'       => 'Lessons',
            'sso'           => 'SSO',
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
               class="nav-tab <?php echo esc_attr( $flosc_active_tab === $flosc_tab_id ? 'nav-tab-active' : '' ); ?>">
                <?php echo esc_html( $flosc_tab_label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Settings Form -->
    <form method="post" class="flosc-settings-form">
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
                $flosc_save_ivr = sanitize_file_name($flosc_post['flosc_save_flow']);
                if (isset($flosc_all_flows[$flosc_save_ivr])) {
                    $flosc_flow_key = $flosc_all_flows[$flosc_save_ivr]['key'];
                    $flosc_new_settings = $flosc_all_flows[$flosc_save_ivr]['settings'];
                    
                    // Update from POST data (fields prefixed with ivr filename hash)
                    $flosc_prefix = 'flow_' . md5($flosc_save_ivr) . '_';
                    $flosc_identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
                    foreach ($post as $flosc_key => $flosc_value) {
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
                    $id = $flosc_new_settings['identity'] ?? [];
                    foreach ($flosc_id_keys as $flosc_ik) {
                        if (isset($flosc_new_settings[$flosc_ik])) {
                            $id[$flosc_ik] = $flosc_new_settings[$flosc_ik];
                            unset($flosc_new_settings[$flosc_ik]);
                        }
                    }
                    $flosc_new_settings['identity'] = $id;
                    
                    update_option($flosc_flow_key, $flosc_new_settings);
                    $flosc_all_flows[$flosc_save_ivr]['settings'] = $flosc_new_settings;
                    $flosc_individual_saved = $flosc_save_ivr;
                }
            }
            
            // Handle save all flows
            if (isset($flosc_post['flosc_save_all_flows']) && wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
                foreach ($flosc_all_flows as $flosc_ivr_file => $flosc_flow_data) {
                    $flosc_flow_key = $flosc_flow_data['key'];
                    $flosc_new_settings = $flosc_flow_data['settings'];
                    
                    $flosc_prefix = 'flow_' . md5($flosc_ivr_file) . '_';
                    $flosc_identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
                    foreach ($post as $flosc_key => $flosc_value) {
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
                    $id = $flosc_new_settings['identity'] ?? [];
                    foreach ($flosc_id_keys as $flosc_ik) {
                        if (isset($flosc_new_settings[$flosc_ik])) {
                            $id[$flosc_ik] = $flosc_new_settings[$flosc_ik];
                            unset($flosc_new_settings[$flosc_ik]);
                        }
                    }
                    $flosc_new_settings['identity'] = $id;
                    
                    update_option($flosc_flow_key, $flosc_new_settings);
                    $flosc_all_flows[$flosc_ivr_file]['settings'] = $flosc_new_settings;
                }
                $flosc_all_saved = true;
            }
            ?>
            
            <!-- View Toggle -->
            <div class="flosc-view-toggle-row">
                <a href="<?php echo esc_url( '?page=flosc-settings&ivr=' . urlencode( $flosc_selected_ivr ) . '&tab=identity&view=single' ); ?>" 
                   class="button <?php echo esc_attr( $flosc_view_mode === 'single' ? 'button-primary' : '' ); ?>">
                    Single Flow
                </a>
                <a href="<?php echo esc_url( '?page=flosc-settings&ivr=' . urlencode( $flosc_selected_ivr ) . '&tab=identity&view=all' ); ?>" 
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
                                       placeholder="e.g., flosc.ai or lesaep.com"
                                       class="flosc-flow-input">
                                <p class="flosc-flow-field__hint">Custom domain pointing to this flow</p>
                            </div>
                            
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label">Name</label>
                                <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>name" 
                                       value="<?php echo esc_attr($flosc_settings['name'] ?? ''); ?>"
                                       placeholder="e.g., FLOSC or LeSAEp"
                                       class="flosc-flow-input">
                            </div>
                            
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label">Title</label>
                                <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>title" 
                                       value="<?php echo esc_attr($flosc_settings['title'] ?? ''); ?>"
                                       placeholder="e.g., Learn Excellent Standard American English Pronunciation"
                                       class="flosc-flow-input">
                                <p class="flosc-flow-field__hint">Description shown under the name</p>
                            </div>
                            
                            <div class="flosc-flow-field">
                                <label class="flosc-flow-field__label">Tagline</label>
                                <input type="text" name="<?php echo esc_attr( $flosc_prefix ); ?>tagline" 
                                       value="<?php echo esc_attr($flosc_settings['tagline'] ?? ''); ?>"
                                       placeholder="e.g., Freeline → Login → Offer → Sale → Content"
                                       class="flosc-flow-input">
                                <p class="flosc-flow-field__hint">Short tagline shown below the title (leave empty to hide)</p>
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
                                   placeholder="e.g., flosc.ai or lesaep.com">
                            <p class="description">Custom domain pointing to this flow</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_name">Name</label></th>
                        <td>
                            <input type="text" id="flow_name" name="flow_name" class="regular-text"
                                   value="<?php echo esc_attr($flosc_fi['name'] ?? ''); ?>"
                                   placeholder="e.g., FLOSC or LeSAEp">
                            <p class="description">Display name shown in the chat header.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_title">Title</label></th>
                        <td>
                            <input type="text" id="flow_title" name="flow_title" class="regular-text"
                                   value="<?php echo esc_attr($flosc_fi['title'] ?? ''); ?>"
                                   placeholder="e.g., Learn Excellent Standard American English Pronunciation">
                            <p class="description">Description shown under the name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="flow_tagline" name="flow_tagline" class="regular-text"
                                   value="<?php echo esc_attr($flosc_fi['tagline'] ?? ''); ?>"
                                   placeholder="e.g., Freeline → Login → Offer → Sale → Content">
                            <p class="description">Short tagline shown below the title. Leave empty to hide.</p>
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
                            document.getElementById('flow_primary_color').addEventListener('input', function(e) {
                                document.getElementById('color-preview').style.background = e.target.value;
                            });
                            (function () {
                                var preview = document.getElementById('color-preview');
                                if (preview) {
                                    preview.style.background = preview.getAttribute('data-color') || '#4f46e5';
                                }
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
                            <p class="description">Image shown in the chat header beside the name. Wider/rectangular formats welcome.</p>
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
                                <option value="active" <?php selected($flosc_flow_settings['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($flosc_flow_settings['status'] ?? '', 'draft'); ?>>Draft</option>
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
            
        <?php elseif ($flosc_active_tab === 'ai'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-configuration.php'; ?>
        <?php elseif ($flosc_active_tab === 'ai-knowledge'): ?>
            <?php $flosc_redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($flosc_selected_ivr) . '&tab=ai#flosc-kb-section'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($flosc_redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($flosc_redirect_url); ?>">Knowledge Base in AI tab</a>&hellip;</p>
        <?php elseif ($flosc_active_tab === 'ai-guide'): ?>
            <?php $flosc_redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($flosc_selected_ivr) . '&tab=documentation&doc=ref-ai-config'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($flosc_redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($flosc_redirect_url); ?>">AI Configuration Guide in Documentation</a>&hellip;</p>
        <?php elseif ($flosc_active_tab === 'knowledge'): ?>
            <?php $flosc_redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($flosc_selected_ivr) . '&tab=ai#flosc-kb-section'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($flosc_redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($flosc_redirect_url); ?>">Knowledge Base in AI tab</a>&hellip;</p>
            
        <?php elseif ($flosc_active_tab === 'quiz'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/quiz.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'email'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/email.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'lessons'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/lessons.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'autoprompts'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/autoprompts.php'; ?>

        <?php elseif ($flosc_active_tab === 'member-levels'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/member-levels.php'; ?>

        <?php elseif ($flosc_active_tab === 'offers'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/offers.php'; ?>

        <?php elseif ($flosc_active_tab === 'login'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/login-registration.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'payments'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/payments.php'; ?>
            
        <?php elseif ($flosc_active_tab === 'sso'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/sso.php'; ?>

        <?php elseif ($flosc_active_tab === 'administration'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/administration.php'; ?>

        <?php elseif ($flosc_active_tab === 'ui'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ui-navigation.php'; ?>

        <?php elseif ($flosc_active_tab === 'chat-logs'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/chat-logs.php'; ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-feedback.php'; ?>

        <?php elseif ($flosc_active_tab === 'documentation'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/documentation.php'; ?>

        <?php elseif ($flosc_active_tab === 'da1'): ?>
            <div class="flosc-da1-launch-row">
                <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-da1') ); ?>" class="button button-primary">Open DA1 Catalog</a>
            </div>
            <p>DA1 catalog is available as a dedicated admin page.</p>

        <?php endif; ?>

        <?php if ($flosc_active_tab !== 'documentation' && $flosc_active_tab !== 'autoprompts' && $flosc_active_tab !== 'da1'): ?>
        <p class="submit flosc-settings-submit-row">
            <button type="submit" name="flosc_save" class="button button-primary button-large">
                Save Settings for <?php echo esc_html($flosc_flow_settings['identity']['name'] ?? $flosc_selected_ivr); ?>
            </button>
        </p>
        <?php endif; ?>
        
        <?php flosc_tab_footer(); ?>
    </form>
</div>

<!-- Styles in assets/css/flosc-admin.css -->
