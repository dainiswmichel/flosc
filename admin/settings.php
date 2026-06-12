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
        $ivr_file = $GLOBALS['flosc_current_ivr'] ?? '';
        $settings = $GLOBALS['flosc_current_settings'] ?? [];
        $flow_name = $settings['identity']['name'] ?? ucwords(str_replace(['_', '-', '.md'], [' ', ' ', ''], $ivr_file));
        
        echo '<div class="flosc-tab-header" style="background: #f0f0f1; border: 1px solid #c3c4c7; padding: 12px 18px; border-radius: 2px; margin-bottom: 20px;">';
        echo '<h2 style="margin: 0; color: #1d2327; font-size: 16px;">';
        echo esc_html($tab_name . ' Configuration');
        echo '</h2>';
        echo '<p style="margin: 5px 0 0; color: #50575e; font-size: 13px;">';
        echo 'Flow: <strong>' . esc_html($flow_name) . '</strong> ';
        echo '<code style="background: #e0e0e0; padding: 2px 8px; border-radius: 2px; color: #1d2327;">(' . esc_html($ivr_file) . ')</code>';
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
        echo '<div class="flosc-tab-footer" style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #c3c4c7; text-align: right;">';
        echo '<span style="color: #787c82; font-size: 11px;">FLOSC v' . esc_html($version) . '</span>';
        echo '</div>';
    }
}

/**
 * Generate Michel Timestamp - overthetop silly specific format
 * Format: 2026y-02m-05d-T10h:43m:22s
 * 
 * @return string
 */
if (!function_exists('flosc_michel_timestamp')) {
    function flosc_michel_timestamp() {
        return gmdate('Y') . 'y-' . gmdate('m') . 'm-' . gmdate('d') . 'd-T' . gmdate('H') . 'h:' . gmdate('i') . 'm:' . gmdate('s') . 's';
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
            'ok' => ['bg' => '#d4edda', 'text_color' => '#155724', 'text' => '&#10003; Permalinks OK'],
            'missing' => ['bg' => '#f8d7da', 'text_color' => '#721c24', 'text' => '&#9888; Needs Flush'],
            'unknown' => ['bg' => '#fff3cd', 'text_color' => '#856404', 'text' => '? Status Unknown'],
        ];
        
        $color = $colors[$status];
        
        echo '<div class="flosc-permalink-status" style="display: inline-flex; align-items: center; gap: 10px; margin-left: 15px;">';

        // Badge 1: Permalinks status
        echo '<span style="background: ' . esc_attr( $color['bg'] ) . '; color: ' . esc_attr( $color['text_color'] ) . '; padding: 4px 10px; border-radius: 2px; font-size: 12px; font-weight: 600;">';
        echo esc_html( $color['text'] );
        echo '</span>';

        // Badge 2: FLOW settings backfill status
        $last_backfill = get_option( 'flosc_last_flow_backfill', null );
        if ( $last_backfill ) {
            echo '<span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 2px; font-size: 12px; font-weight: 600;">&#10003; FLOW Settings OK</span>';
        }

        if ($last_flush) {
            echo '<span style="color: #c3c4c7; font-size: 11px;">Last flush: ' . esc_html($last_flush) . '</span>';
        }

        // Flush button
        $flush_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_flush_permalinks_v129'), 'flosc_flush_v129');
        echo '<a href="' . esc_url($flush_url) . '" class="button button-small" style="font-size: 11px;">Flush Now</a>';
        echo '</div>';
    }
}

/**
 * Resolve the canonical flow option key for a given IVR file.
 * Prevents duplicate flow option construction for the same file.
 *
 * @param string $ivr_filename
 * @return string
 */
if (!function_exists('flosc_resolve_flow_option_key_for_ivr')) {
    function flosc_resolve_flow_option_key_for_ivr($ivr_filename) {
        global $wpdb;

        $ivr_filename = basename((string) $ivr_filename);
        $target_stem = sanitize_key(pathinfo($ivr_filename, PATHINFO_FILENAME));
        $default_key = 'flosc_flow_' . $target_stem;

        // Start with deterministic default key and score it conservatively.
        $best_key = $default_key;
        $best_score = -1;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only scan of plugin flow options for deterministic key resolution
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only scan of plugin flow options for deterministic key resolution
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'flosc_flow_%'"
        );

        if (!is_array($rows)) {
            return $default_key;
        }

        foreach ($rows as $row) {
            $option_name = (string) ($row->option_name ?? '');
            if ($option_name === '') {
                continue;
            }

            $settings = maybe_unserialize($row->option_value ?? null);
            if (!is_array($settings)) {
                continue;
            }

            $active = basename((string) ($settings['active_ivr_file'] ?? ''));
            $primary = basename((string) ($settings['ivr_file'] ?? ''));
            $matches_active = ($active !== '' && $active === $ivr_filename);
            $matches_primary = ($primary !== '' && $primary === $ivr_filename);

            // Only consider keys that are explicitly tied to this IVR filename.
            if (!$matches_active && !$matches_primary && $option_name !== $default_key) {
                continue;
            }

            $message_count = 0;
            if (isset($settings['ivr_messages']) && is_array($settings['ivr_messages'])) {
                $message_count = count($settings['ivr_messages']);
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
$ivr_files = [];
$files = flosc_config_glob(['*_ivr.md', 'ivr*.md']);
sort($files);
if (!empty($files)) {
    foreach ($files as $file) {
        $filename = basename($file);
        if (strpos($filename, 'backup') === false) {
            $ivr_files[] = $filename;
        }
    }
}
// De-dupe: the glob unions the uploads copy with the shipped plugin defaults,
// so the same flow file can appear twice. Keep one entry per filename.
$ivr_files = array_values( array_unique( $ivr_files ) );

// Read request vars early. The flow selector below reads $get['ivr'] to know
// which flow is selected. ($get was previously first defined further down — after
// this point — so the selector always fell back to $ivr_files[0] and ignored the URL.)
$get = wp_unslash( $_GET );

// Selected IVR file (flow)
$selected_ivr = isset($get['ivr']) ? sanitize_file_name($get['ivr']) : '';
if (empty($selected_ivr) && !empty($ivr_files)) {
    $selected_ivr = $ivr_files[0];
}

// Settings key for this flow
$settings_key = flosc_resolve_flow_option_key_for_ivr($selected_ivr);

// Load settings for this flow
$flow_settings = get_option($settings_key, []);

// Set defaults if empty AND save them (v1.3.0 fix)
if (empty($flow_settings)) {
    // v1.3.5: Preserve underscores in default slug (don't use sanitize_title which converts to hyphens)
    $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($selected_ivr, PATHINFO_FILENAME)));
    $flow_settings = [
        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $selected_ivr)),
        'title' => '',
        'tagline' => '',
        'slug' => $default_slug,
        'primary_color' => '#4f46e5',
        'status' => 'active',
    ];
    // v1.3.0: Save defaults so rewrite rules can find them
    update_option($settings_key, $flow_settings);
}

$get = wp_unslash($_GET);
$post = wp_unslash($_POST);
$active_tab = isset($get['tab']) ? sanitize_text_field($get['tab']) : 'identity';
$identity_view = isset($get['view']) ? sanitize_text_field($get['view']) : 'single';
if (!in_array($identity_view, ['single', 'all'], true)) {
    $identity_view = 'single';
}

// Handle save
if (isset($post['flosc_save']) && wp_verify_nonce(sanitize_text_field($post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
    
    // Collect all POST data for this flow
    $new_settings = $flow_settings; // Start with existing
    
    // v1.5.0: Keys that contain multiline content (stored in flow settings via flow_ prefix)
    $textarea_flow_keys = [
        'sso_apple_private_key',
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
    $identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
    
    foreach ($post as $key => $value) {
        if (strpos($key, 'flow_') === 0) {
            $setting_key = substr($key, 5); // Remove 'flow_' prefix
            // Check static textarea keys OR dynamic quiz content/template keys
            $is_textarea = in_array($setting_key, $textarea_flow_keys, true)
                || strpos($setting_key, 'quiz_content_') === 0
                || strpos($setting_key, '_template_') !== false
                || substr($setting_key, -5) === '_body'; // email bodies (guest/member/newsletter) — preserve newlines
            if (in_array($setting_key, $identity_html_keys, true)) {
                $new_settings[$setting_key] = wp_kses_post($value);
            } elseif ($is_textarea) {
                $new_settings[$setting_key] = sanitize_textarea_field($value);
            } elseif (is_array($value)) {
                $new_settings[$setting_key] = array_map('sanitize_text_field', $value);
            } else {
                $new_settings[$setting_key] = sanitize_text_field($value);
            }
        }
    }
    
    // v1.5.0: Handle checkbox unchecking — checkboxes don't POST when unchecked
    // Only handle checkboxes for the current tab to avoid wiping other tabs' values
    if ($active_tab === 'sso') {
        $sso_providers = ['google', 'apple', 'facebook', 'microsoft', 'linkedin'];
        foreach ($sso_providers as $provider) {
            if (!isset($post["flow_sso_{$provider}_enabled"])) {
                $new_settings["sso_{$provider}_enabled"] = '';
            }
        }
    }
    if ($active_tab === 'ai') {
        foreach (['ai_enable_ivr_context', 'ai_enable_content_access', 'ai_enable_chaining'] as $cb) {
            if (!isset($post["flow_{$cb}"])) {
                $new_settings[$cb] = '';
            }
        }
    }
    if ($active_tab === 'email') {
        foreach (['email_on_quiz_complete', 'email_reengagement_enabled', 'email_weekly_summary'] as $cb) {
            if (!isset($post["flow_{$cb}"])) {
                $new_settings[$cb] = '';
            }
        }
        // Follow-up repeaters (newsletter + member per level): store as <prefix>_followups arrays.
        // Day offsets and the number of follow-ups are per-flow parameters, not hardcoded stages.
        $fu_prefixes = ['newsletter'];
        foreach ((array) ($flow_settings['member_levels'] ?? []) as $lk => $lv) {
            $s = sanitize_key($lv['slug'] ?? $lk);
            if ($s !== '') { $fu_prefixes[] = 'member_' . $s; }
        }
        foreach ($fu_prefixes as $pfx) {
            $days = (array) ($post[$pfx . '_fu_day'] ?? []);
            $subs = (array) ($post[$pfx . '_fu_subject'] ?? []);
            $bods = (array) ($post[$pfx . '_fu_body'] ?? []);
            $rows = [];
            foreach ($days as $i => $d) {
                $subject = sanitize_text_field($subs[$i] ?? '');
                $body    = sanitize_textarea_field($bods[$i] ?? '');
                if ($subject === '' && $body === '') { continue; }
                $rows[] = [
                    'day'     => max(0, min(365, (int) $d)),
                    'subject' => $subject,
                    'body'    => $body,
                ];
            }
            $new_settings[$pfx . '_followups'] = $rows;
        }
    }
    if ($active_tab === 'payments') {
        foreach (['stripe_enabled', 'paypal_enabled', 'manual_payments_enabled'] as $cb) {
            if (!isset($post["flow_{$cb}"])) {
                $new_settings[$cb] = '';
            }
        }
    }
    if ($active_tab === 'quiz') {
        foreach (['wpq_integration', 'ld_integration', 'qsm_integration'] as $cb) {
            if (!isset($post["flow_{$cb}"])) {
                $new_settings[$cb] = '';
            }
        }
        if (!isset($post['flow_enabled_quizzes'])) {
            $new_settings['enabled_quizzes'] = [];
        }
        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
            if (!isset($post["flow_quiz_variant_{$letter}_enabled"])) {
                $new_settings["quiz_variant_{$letter}_enabled"] = '';
            }
        }
    }

    // v8.1.0: Member Levels tab — parse level registry + content protection repeaters
    if ($active_tab === 'member-levels') {
        // Level registry repeater
        $level_slugs        = $post['level_slug']        ?? [];
        $level_names        = $post['level_name']        ?? [];
        $level_descriptions = $post['level_description'] ?? [];
        $member_levels = [];
        foreach ($level_slugs as $i => $slug) {
            $slug = sanitize_key($slug);
            if ($slug === '') continue;
            $member_levels[$slug] = [
                'slug'        => $slug,
                'name'        => sanitize_text_field($level_names[$i] ?? ''),
                'description' => sanitize_text_field($level_descriptions[$i] ?? ''),
            ];
        }
        $new_settings['member_levels'] = $member_levels;

        // Content protection repeater
        $prot_types  = $post['protection_type']  ?? [];
        $prot_values = $post['protection_value'] ?? [];
        $prot_levels = $post['protection_level'] ?? [];
        $protected_content = [];
        foreach ($prot_types as $i => $type) {
            $type  = sanitize_text_field($type);
            $value = sanitize_text_field($prot_values[$i] ?? '');
            $level = sanitize_key($prot_levels[$i] ?? '');
            if ($value === '') continue;
            $item = [
                'type'  => $type,
                'id'    => $value,
                'level' => $level,
            ];
            // Resolve names for display
            if (in_array($type, ['category', 'tag'])) {
                $term = get_term(intval($value));
                if ($term && !is_wp_error($term)) {
                    $item['slug'] = $term->slug;
                    $item['name'] = $term->name;
                }
            } elseif (in_array($type, ['post', 'page'])) {
                $post_obj = get_post(intval($value));
                if ($post_obj) {
                    $item['name'] = $post_obj->post_title;
                }
            }
            $protected_content[] = $item;
        }
        $new_settings['protected_content'] = $protected_content;

        // Sync to term_meta — class-content-protection.php reads from term_meta currently.
        // TODO: Refactor class-content-protection.php to read from flow_settings['protected_content']
        // and remove this sync entirely.
        // First clear old protection flags from categories no longer protected
        $old_protected = $flow_settings['protected_content'] ?? [];
        foreach ($old_protected as $old_item) {
            if ($old_item['type'] === 'category') {
                delete_term_meta(intval($old_item['id']), '_flosc_protected');
                delete_term_meta(intval($old_item['id']), '_flosc_required_level');
            }
        }
        // Set new protection flags
        foreach ($protected_content as $item) {
            if ($item['type'] === 'category') {
                update_term_meta(intval($item['id']), '_flosc_protected', 'yes');
                if (!empty($item['level'])) {
                    update_term_meta(intval($item['id']), '_flosc_required_level', $item['level']);
                }
            }
        }
    }

    // v3.0.0: Lessons tab — parse lesson_groups repeater
    // Repeater fields are NOT flow_-prefixed (lesson_group_quiz[], lesson_group_category[])
    if ($active_tab === 'lessons') {
        $group_quizzes    = $post['lesson_group_quiz']     ?? [];
        $group_categories = $post['lesson_group_category'] ?? [];
        $lesson_groups = [];
        foreach ($group_categories as $i => $cat) {
            $cat = sanitize_text_field($cat);
            if ($cat === '') continue; // Skip rows with no category selected
            $quiz = sanitize_text_field($group_quizzes[$i] ?? '');
            $lesson_groups[] = [
                'quiz_id'  => $quiz,
                'category' => $cat,
            ];
        }
        // Only overwrite lesson_groups when the form actually submitted rows.
        // An accidental save on an empty/wrong-flow form must not wipe existing config.
        if (!empty($lesson_groups)) {
            $new_settings['lesson_groups']    = $lesson_groups;
            $new_settings['lessons_category'] = $lesson_groups[0]['category'];
        }
    }

    // v1.8.2: UI & Nav tab uses WordPress options (global, not per-flow)
    if ($active_tab === 'ui') {
        // Visitor menu — dynamic items from repeater
        $menu_labels  = $post['visitor_menu_label']  ?? [];
        $menu_actions = $post['visitor_menu_action'] ?? [];
        $new_menu = [];
        foreach ($menu_labels as $i => $label) {
            $label  = sanitize_text_field($label);
            $action = sanitize_text_field($menu_actions[$i] ?? '');
            if ($label !== '' && $action !== '') {
                $new_menu[] = ['label' => $label, 'action' => $action];
            }
        }
        update_option('flosc_visitor_menu_items', $new_menu);

        // v1.9.8: Guest menu — dynamic items from repeater
        $guest_labels  = $post['guest_menu_label']  ?? [];
        $guest_actions = $post['guest_menu_action'] ?? [];
        $new_guest_menu = [];
        foreach ($guest_labels as $i => $label) {
            $label  = sanitize_text_field($label);
            $action = sanitize_text_field($guest_actions[$i] ?? '');
            if ($label !== '' && $action !== '') {
                $new_guest_menu[] = ['label' => $label, 'action' => $action];
            }
        }
        update_option('flosc_guest_menu_items', $new_guest_menu);

        // v1.9.8: Member menu — dynamic items from repeater
        $member_labels  = $post['member_menu_label']  ?? [];
        $member_actions = $post['member_menu_action'] ?? [];
        $new_member_menu = [];
        foreach ($member_labels as $i => $label) {
            $label  = sanitize_text_field($label);
            $action = sanitize_text_field($member_actions[$i] ?? '');
            if ($label !== '' && $action !== '') {
                $new_member_menu[] = ['label' => $label, 'action' => $action];
            }
        }
        update_option('flosc_member_menu_items', $new_member_menu);

        // Login destination — v1.9.8: now a URL, not a key
        $login_dest = trim($post['flosc_login_destination'] ?? '');
        update_option('flosc_login_destination', $login_dest !== '' ? esc_url_raw($login_dest) : '');

        // Profile bar per-state settings
        $profile_bar = [
            'visitor' => [
                'name'  => sanitize_text_field($post['profile_bar_visitor_name'] ?? 'Visitor'),
                'badge' => sanitize_text_field($post['profile_bar_visitor_badge'] ?? 'Hope you enjoy our chat :-)'),
                'icon'  => sanitize_text_field($post['profile_bar_visitor_icon'] ?? '👋'),
            ],
            'guest' => [
                'badge' => sanitize_text_field($post['profile_bar_guest_badge'] ?? 'Guest'),
                'upgrade_label' => sanitize_text_field($post['profile_bar_guest_upgrade'] ?? 'Upgrade to Pro'),
            ],
            'member' => [
                'badge' => sanitize_text_field($post['profile_bar_member_badge'] ?? 'Member'),
            ],
        ];
        update_option('flosc_profile_bar', $profile_bar);
    }

    if ($active_tab === 'administration') {
        $allowed_plans = ['free', 'paid', 'enterprise'];
        $plan = sanitize_key($post['flosc_account_plan'] ?? 'free');
        if (!in_array($plan, $allowed_plans, true)) {
            $plan = 'free';
        }
        update_option('flosc_account_plan', $plan);

        $manual_purchases = sanitize_textarea_field($post['flosc_account_purchases_manual'] ?? '');
        update_option('flosc_account_purchases_manual', $manual_purchases);

        $allowed_debug_modes = ['inherit', 'on', 'off'];
        $debug_mode = sanitize_key($post['flosc_debug_mode'] ?? 'inherit');
        if (!in_array($debug_mode, $allowed_debug_modes, true)) {
            $debug_mode = 'inherit';
        }
        update_option('flosc_debug_mode', $debug_mode);
    }

    // Nest identity fields into the identity sub-array
    // The generic POST loop saves them flat (e.g. $new_settings['chatlogo_url']).
    // get_floscflow_identity() reads from $flow['identity']['chatlogo_url'].
    $identity_keys = [
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
    $identity = $new_settings['identity'] ?? [];
    foreach ($identity_keys as $k) {
        if (isset($new_settings[$k])) {
            $identity[$k] = $new_settings[$k];
            unset($new_settings[$k]);
        }
    }
    $new_settings['identity'] = $identity;

    // Save flow settings (ALL tabs now per-flow)
    update_option($settings_key, $new_settings);
    $flow_settings = $new_settings;
    
    $saved = true;
}

// Build flow URL
$flow_url = home_url('/' . ($flow_settings['slug'] ?? 'flosc') . '/');

// Set global context for tab includes
$GLOBALS['flosc_current_ivr'] = $selected_ivr;
$GLOBALS['flosc_current_settings'] = $flow_settings;
$GLOBALS['flosc_settings_key'] = $settings_key;

?>
<div class="wrap flosc-admin">
    <h1 style="display: flex; align-items: center; gap: 10px;">
        FLOSC Settings 
        <span style="font-size: 12px; font-weight: normal; color: #50575e; background: #f0f0f1; padding: 3px 10px; border-radius: 2px;">v<?php echo esc_html(FLOSC_VERSION); ?></span>
    </h1>
    
    <?php if (isset($saved)): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Settings saved for <strong><?php echo esc_html($flow_settings['identity']['name'] ?? $selected_ivr); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <!-- IVR File Selector = Flow Selector -->
    <div class="flosc-ivr-selector" style="background: #2f363d; border: 1px solid #3c434a; padding: 15px 20px; border-radius: 4px 4px 0 0; margin-bottom: 20px;">

        <?php // Flow-context row above the selector: single-flow view highlights the active flow; all-flows view lists every flow with the active one highlighted + bold. ?>
        <?php if ($identity_view === 'all'): ?>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                <span style="color: #9ca3af; font-size: 13px;">All flows:</span>
                <?php foreach ($ivr_files as $file): $flosc_is_current_flow = ($file === $selected_ivr); ?>
                    <span style="display: inline-block; padding: 3px 11px; border-radius: 12px; font-size: 12px; background: <?php echo $flosc_is_current_flow ? '#2271b1' : '#3c434a'; ?>; color: <?php echo $flosc_is_current_flow ? '#ffffff' : '#c3c4c7'; ?>; font-weight: <?php echo $flosc_is_current_flow ? '700' : '400'; ?>;">
                        <?php echo esc_html($file); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 12px; color: #9ca3af; font-size: 13px;">
                Working on:
                <span style="display: inline-block; margin-left: 4px; padding: 3px 12px; border-radius: 12px; background: #2271b1; color: #ffffff; font-weight: 700; font-size: 13px;">
                    <?php echo esc_html($flow_settings['identity']['name'] ?? $selected_ivr); ?>
                </span>
                <span style="margin-left: 8px; color: #6b7280; font-size: 12px;"><?php echo esc_html($selected_ivr); ?></span>
            </div>
        <?php endif; ?>

        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <label style="color: #f0f0f1; font-weight: 600; font-size: 14px;">Select Flow:</label>
            <select id="ivr-select" onchange="switchIVR(this.value);" style="padding: 8px 12px; border-radius: 2px; border: 1px solid #50575e; min-width: 250px; font-size: 14px;">
                <?php foreach ($ivr_files as $file): ?>
                    <option value="<?php echo esc_attr($file); ?>" <?php selected($selected_ivr, $file); ?>>
                        <?php echo esc_html($file); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($flow_settings['status'] === 'active' && !empty($flow_settings['slug'])): ?>
                <a href="<?php echo esc_url($flow_url); ?>" target="_blank" class="button button-small" style="font-size: 13px;">
                    View App &#8599;
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url(add_query_arg([
                'page' => 'flosc-settings',
                'ivr' => $selected_ivr,
                'tab' => 'administration',
                'view' => $identity_view,
            ], admin_url('admin.php'))); ?>" class="button button-small" style="font-size: 13px;">
                Administration
            </a>
        </div>
        
        <!-- Permalink Status Row -->
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #50575e;">
            <?php flosc_permalink_status_indicator($flow_settings['slug'] ?? ''); ?>
        </div>
    </div>
    
    <?php ob_start(); ?>
    function switchIVR(ivr) {
        const tab = '<?php echo esc_js($active_tab); ?>';
        const view = '<?php echo esc_js($identity_view); ?>';
        window.location.href = '<?php echo esc_js( admin_url('admin.php?page=flosc-settings') ); ?>&ivr=' + encodeURIComponent(ivr) + '&tab=' + tab + '&view=' + encodeURIComponent(view);
    }

    (function () {
        const tab = '<?php echo esc_js($active_tab); ?>';
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
        foreach ($tabs as $tab_id => $tab_label):
            $tab_url = add_query_arg([
                'page' => 'flosc-settings',
                'ivr' => $selected_ivr,
                'tab' => $tab_id,
                'view' => $identity_view,
            ], admin_url('admin.php'));
        ?>
            <a href="<?php echo esc_url($tab_url); ?>" 
               class="nav-tab <?php echo esc_attr( $active_tab === $tab_id ? 'nav-tab-active' : '' ); ?>">
                <?php echo esc_html( $tab_label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Settings Form -->
    <form method="post" class="flosc-settings-form">
        <?php wp_nonce_field('flosc_save_settings'); ?>
        
        <?php if ($active_tab === 'flow'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/flow.php'; ?>

        <?php elseif ($active_tab === 'identity'): ?>
            <!-- Identity Tab v1.3.3 - All Flows = fully expanded inline editing -->
            
            <?php
            // View mode: single flow or all flows
            $view_mode = $identity_view;
            
            // Get all flows data
            $all_flows = [];
            foreach ($ivr_files as $ivr_file) {
                $key = flosc_resolve_flow_option_key_for_ivr($ivr_file);
                $settings = get_option($key, []);
                if (empty($settings)) {
                    // v1.3.5: Preserve underscores in default slug
                    $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($ivr_file, PATHINFO_FILENAME)));
                    $settings = [
                        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $ivr_file)),
                        'slug' => $default_slug,
                        'domain' => '',
                        'title' => '',
                        'tagline' => '',
                        'primary_color' => '#4f46e5',
                        'status' => 'active',
                    ];
                }
                $all_flows[$ivr_file] = ['key' => $key, 'settings' => $settings];
            }
            
            // Handle individual flow save via AJAX or form
            if (isset($post['flosc_save_flow']) && wp_verify_nonce(sanitize_text_field($post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
                $save_ivr = sanitize_file_name($post['flosc_save_flow']);
                if (isset($all_flows[$save_ivr])) {
                    $flow_key = $all_flows[$save_ivr]['key'];
                    $new_settings = $all_flows[$save_ivr]['settings'];
                    
                    // Update from POST data (fields prefixed with ivr filename hash)
                    $prefix = 'flow_' . md5($save_ivr) . '_';
                    $identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
                    foreach ($post as $key => $value) {
                        if (strpos($key, $prefix) === 0) {
                            $setting_key = substr($key, strlen($prefix));
                            if (in_array($setting_key, $identity_html_keys, true)) {
                                $new_settings[$setting_key] = wp_kses_post($value);
                            } else {
                                $new_settings[$setting_key] = sanitize_text_field($value);
                            }
                        }
                    }
                    
                    // v2.0.0: Nest identity fields into identity sub-array
                    $id_keys = [
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
                    $id = $new_settings['identity'] ?? [];
                    foreach ($id_keys as $ik) {
                        if (isset($new_settings[$ik])) {
                            $id[$ik] = $new_settings[$ik];
                            unset($new_settings[$ik]);
                        }
                    }
                    $new_settings['identity'] = $id;
                    
                    update_option($flow_key, $new_settings);
                    $all_flows[$save_ivr]['settings'] = $new_settings;
                    $individual_saved = $save_ivr;
                }
            }
            
            // Handle save all flows
            if (isset($post['flosc_save_all_flows']) && wp_verify_nonce(sanitize_text_field($post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
                foreach ($all_flows as $ivr_file => $flow_data) {
                    $flow_key = $flow_data['key'];
                    $new_settings = $flow_data['settings'];
                    
                    $prefix = 'flow_' . md5($ivr_file) . '_';
                    $identity_html_keys = ['privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'];
                    foreach ($post as $key => $value) {
                        if (strpos($key, $prefix) === 0) {
                            $setting_key = substr($key, strlen($prefix));
                            if (in_array($setting_key, $identity_html_keys, true)) {
                                $new_settings[$setting_key] = wp_kses_post($value);
                            } else {
                                $new_settings[$setting_key] = sanitize_text_field($value);
                            }
                        }
                    }
                    
                    // v2.0.0: Nest identity fields into identity sub-array
                    $id_keys = [
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
                    $id = $new_settings['identity'] ?? [];
                    foreach ($id_keys as $ik) {
                        if (isset($new_settings[$ik])) {
                            $id[$ik] = $new_settings[$ik];
                            unset($new_settings[$ik]);
                        }
                    }
                    $new_settings['identity'] = $id;
                    
                    update_option($flow_key, $new_settings);
                    $all_flows[$ivr_file]['settings'] = $new_settings;
                }
                $all_saved = true;
            }
            ?>
            
            <!-- View Toggle -->
            <div style="display: flex; gap: 10px; margin: 20px 0;">
                <a href="<?php echo esc_url( '?page=flosc-settings&ivr=' . urlencode( $selected_ivr ) . '&tab=identity&view=single' ); ?>" 
                   class="button <?php echo esc_attr( $view_mode === 'single' ? 'button-primary' : '' ); ?>">
                    Single Flow
                </a>
                <a href="<?php echo esc_url( '?page=flosc-settings&ivr=' . urlencode( $selected_ivr ) . '&tab=identity&view=all' ); ?>" 
                   class="button <?php echo esc_attr( $view_mode === 'all' ? 'button-primary' : '' ); ?>">
                    All Flows (<?php echo count($ivr_files); ?>)
                </a>
            </div>
            
            <?php if (isset($all_saved)): ?>
                <div class="notice notice-success is-dismissible"><p>✓ All flows saved!</p></div>
            <?php endif; ?>
            <?php if (isset($individual_saved)): ?>
                <div class="notice notice-success is-dismissible"><p>✓ Saved: <?php echo esc_html($individual_saved); ?></p></div>
            <?php endif; ?>
            
            <?php if ($view_mode === 'all'): ?>
            <?php
            $identity_docs_url_all = add_query_arg([
                'page' => 'flosc-settings',
                'ivr'  => $selected_ivr,
                'tab'  => 'documentation',
                'doc'  => 'ref-admin',
            ], admin_url('admin.php')) . '#tab-identity';
            ?>
            
            <!-- ALL FLOWS - FULLY EXPANDED INLINE EDITING -->
            <div style="max-width: 1000px;">
                <div style="background: #2f363d; border: 1px solid #3c434a; padding: 15px 20px; border-radius: 4px 4px 0 0; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: #f0f0f1; font-size: 16px; display:flex; align-items:center; gap:10px;">
                        <span>All FLOSC Flows &mdash; Identity Settings</span>
                        <a href="<?php echo esc_url($identity_docs_url_all); ?>" style="margin-left:auto; font-size:12px; text-decoration:none; color:#8ec5ff;">Docs</a>
                    </h2>
                    <p style="margin: 5px 0 0; color: #a7aaad; font-size: 13px;">
                        All flows expanded. Edit any field, then save individually or save all at bottom.
                    </p>
                </div>
                
                <?php foreach ($all_flows as $ivr_file => $flow_data): 
                    $settings = $flow_data['settings'];
                    // v2.0.0: Merge identity sub-array up for form display
                    // Identity fields are stored nested but form fields read flat
                    $si = $settings['identity'] ?? [];
                    foreach (['name', 'title', 'tagline', 'primary_color', 'chatlogo_url', 'favicon_url', 'badgeUrl', 'share_text', 'privacy_policy_content', 'terms_of_service_content', 'data_deletion_content', 'platform_compliance_content'] as $_ik) {
                        if (isset($si[$_ik]) && !isset($settings[$_ik])) {
                            $settings[$_ik] = $si[$_ik];
                        }
                    }
                    $prefix = 'flow_' . md5($ivr_file) . '_';
                    $is_current = ($ivr_file === $selected_ivr);
                    // v1.3.5: Preserve underscores in default slug
                    $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($ivr_file, PATHINFO_FILENAME)));
                    $slug = $settings['slug'] ?? $default_slug;
                    $flow_url = home_url('/' . $slug . '/');
                    $full_url = !empty($settings['domain']) ? 'https://' . $settings['domain'] . '/' : $flow_url;
                ?>
                
                <div class="flosc-flow-block" style="background: white; border: 2px solid <?php echo esc_attr( $is_current ? '#2271b1' : '#c3c4c7' ); ?>; border-radius: 2px; padding: 25px; margin-bottom: 20px;">
                    
                    <!-- Flow Header with IVR file -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #c3c4c7;">
                        <div>
                            <h3 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                                <?php echo esc_html($settings['name'] ?? $ivr_file); ?>
                                <?php if ($is_current): ?>
                                    <span style="background: #2271b1; color: white; padding: 3px 10px; border-radius: 2px; font-size: 11px;">CURRENT</span>
                                <?php endif; ?>
                            </h3>
                            <div style="margin-top: 5px;">
                                <code style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 12px;"><?php echo esc_html($ivr_file); ?></code>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: <?php echo ($settings['status'] ?? 'active') === 'active' ? '#d4edda' : '#f0f0f1'; ?>; color: <?php echo ($settings['status'] ?? 'active') === 'active' ? '#155724' : '#50575e'; ?>; padding: 4px 12px; border-radius: 2px; font-size: 12px; font-weight: 600;">
                                <?php echo esc_html(ucfirst($settings['status'] ?? 'active')); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- URL Mapping Summary -->
                    <div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 15px 20px; border-radius: 2px; color: #1d2327; margin-bottom: 20px;">
                        <div style="font-size: 11px; color: #50575e; margin-bottom: 3px;">This flow is accessible at:</div>
                        <div style="font-family: monospace; font-size: 15px; font-weight: 600;">
                            <a href="<?php echo esc_url($flow_url); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">
                                <?php echo esc_html($flow_url); ?> &#8599;
                            </a>
                            <?php if (!empty($settings['domain'])): ?>
                                <span style="color: #787c82; margin: 0 8px;">&rarr;</span>
                                <a href="https://<?php echo esc_attr($settings['domain']); ?>/" target="_blank" style="color: #2271b1; text-decoration: none;">
                                    https://<?php echo esc_html($settings['domain']); ?>/ &#8599;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- ALL EDITABLE FIELDS - Slug first -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        
                        <!-- Left Column -->
                        <div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">URL Slug <span style="color: #d63638;">(required)</span></label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <code style="background: #f3f4f6; padding: 8px 10px; border-radius: 4px; font-size: 13px;"><?php echo esc_html( home_url('/') ); ?></code>
                                    <input type="text" name="<?php echo esc_attr( $prefix ); ?>slug" 
                                           value="<?php echo esc_attr($slug); ?>"
                                           placeholder="myapp"
                                           style="width: 250px; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px; font-weight: 600;">
                                    <code style="background: #f3f4f6; padding: 8px 10px; border-radius: 4px; font-size: 13px;">/</code>
                                </div>
                                <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">The URL path where this flow is served on your WordPress site</p>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Custom Domain</label>
                                <input type="text" name="<?php echo esc_attr( $prefix ); ?>domain" 
                                       value="<?php echo esc_attr($settings['domain'] ?? ''); ?>"
                                       placeholder="e.g., flosc.ai or lesaep.com"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                                <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Custom domain pointing to this flow</p>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Name</label>
                                <input type="text" name="<?php echo esc_attr( $prefix ); ?>name" 
                                       value="<?php echo esc_attr($settings['name'] ?? ''); ?>"
                                       placeholder="e.g., FLOSC or LeSAEp"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Title</label>
                                <input type="text" name="<?php echo esc_attr( $prefix ); ?>title" 
                                       value="<?php echo esc_attr($settings['title'] ?? ''); ?>"
                                       placeholder="e.g., Learn Excellent Standard American English Pronunciation"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                                <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Description shown under the name</p>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Tagline</label>
                                <input type="text" name="<?php echo esc_attr( $prefix ); ?>tagline" 
                                       value="<?php echo esc_attr($settings['tagline'] ?? ''); ?>"
                                       placeholder="e.g., Freeline → Login → Offer → Sale → Content"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                                <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Short tagline shown below the title (leave empty to hide)</p>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div>
                            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Brand Color</label>
                                    <input type="color" name="<?php echo esc_attr( $prefix ); ?>primary_color" 
                                           value="<?php echo esc_attr($settings['primary_color'] ?? '#4f46e5'); ?>"
                                           style="width: 60px; height: 38px; padding: 0; border: 1px solid #c3c4c7; border-radius: 2px; cursor: pointer;">
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Status</label>
                                    <select name="<?php echo esc_attr( $prefix ); ?>status" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                                        <option value="active" <?php selected($settings['status'] ?? '', 'active'); ?>>Active</option>
                                        <option value="draft" <?php selected($settings['status'] ?? '', 'draft'); ?>>Draft</option>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Chat Logo URL</label>
                                <input type="url" name="<?php echo esc_attr( $prefix ); ?>chatlogo_url"
                                       value="<?php echo esc_attr($settings['chatlogo_url'] ?? ''); ?>"
                                       placeholder="https://..."
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                            </div>

                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Favicon URL</label>
                                <input type="url" name="<?php echo esc_attr( $prefix ); ?>favicon_url"
                                       value="<?php echo esc_attr($settings['favicon_url'] ?? ''); ?>"
                                       placeholder="https://..."
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Badge Image URL</label>
                                <input type="url" name="<?php echo esc_attr( $prefix ); ?>badgeUrl"
                                       value="<?php echo esc_attr($settings['badgeUrl'] ?? ''); ?>"
                                       placeholder="https://..."
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                            </div>
                            
                            <!-- DNS Setup Info (if custom domain set) -->
                            <?php if (!empty($settings['domain'])): ?>
                            <div style="background: #fef3c7; border: 1px solid #dba617; padding: 12px 15px; border-radius: 2px; margin-bottom: 15px;">
                                <div style="font-weight: 600; font-size: 12px; color: #50575e; margin-bottom: 5px;">DNS Setup for <?php echo esc_html($settings['domain']); ?></div>
                                <div style="font-size: 11px; color: #50575e;">
                                    Point your domain to: <code style="background: white; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code><br>
                                    <a href="https://flosc.ai/docs/dns" target="_blank" style="color: #50575e;">Full DNS guide →</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-top: 20px; border-top: 1px solid #c3c4c7; padding-top: 20px;">
                        <h4 style="margin: 0 0 12px; font-size: 14px; color: #1d2327;">Policy Pages Content (Per Flow)</h4>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Privacy Policy Content</label>
                            <textarea name="<?php echo esc_attr( $prefix ); ?>privacy_policy_content" rows="8" style="width: 100%; padding: 8px 10px; border: 1px solid #c3c4c7; border-radius: 2px; font-family: monospace;"><?php echo esc_textarea($settings['privacy_policy_content'] ?? ''); ?></textarea>
                            <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Shown at /privacy for this flow domain. HTML is allowed.</p>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Terms of Service Content</label>
                            <textarea name="<?php echo esc_attr( $prefix ); ?>terms_of_service_content" rows="8" style="width: 100%; padding: 8px 10px; border: 1px solid #c3c4c7; border-radius: 2px; font-family: monospace;"><?php echo esc_textarea($settings['terms_of_service_content'] ?? ''); ?></textarea>
                            <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Shown at /terms-of-service for this flow domain. HTML is allowed.</p>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Data Deletion Content</label>
                            <textarea name="<?php echo esc_attr( $prefix ); ?>data_deletion_content" rows="8" style="width: 100%; padding: 8px 10px; border: 1px solid #c3c4c7; border-radius: 2px; font-family: monospace;"><?php echo esc_textarea($settings['data_deletion_content'] ?? ''); ?></textarea>
                            <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Shown at /data-deletion for this flow domain. HTML is allowed.</p>
                        </div>

                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Platform Compliance Content</label>
                            <textarea name="<?php echo esc_attr( $prefix ); ?>platform_compliance_content" rows="8" style="width: 100%; padding: 8px 10px; border: 1px solid #c3c4c7; border-radius: 2px; font-family: monospace;"><?php echo esc_textarea($settings['platform_compliance_content'] ?? ''); ?></textarea>
                            <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Appended to all three policy pages (privacy, terms-of-service, data-deletion). HTML is allowed.</p>
                        </div>
                    </div>
                    
                    <!-- Save This Flow Button -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #c3c4c7;">
                        <button type="submit" name="flosc_save_flow" value="<?php echo esc_attr($ivr_file); ?>" class="button button-secondary">
                            Save <?php echo esc_html($settings['name'] ?? $ivr_file); ?>
                        </button>
                    </div>
                </div>
                
                <?php endforeach; ?>
                
                <!-- DNS Setup Guide -->
                <div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 20px; border-radius: 2px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px; color: #1d2327; font-size: 15px;">Custom Domain DNS Setup</h4>
                    
                    <p style="font-size: 13px; color: #1d2327; margin: 0 0 15px; line-height: 1.6;">
                        <strong>How it works:</strong> Your custom domain (e.g., <code>flosc.ai</code>) will point to this WordPress installation, 
                        and FLOSC will automatically serve the correct flow when visitors access that domain.
                    </p>
                    
                    <div style="background: white; padding: 15px; border-radius: 2px; margin-bottom: 15px;">
                        <p style="font-size: 13px; color: #1d2327; margin: 0 0 10px;"><strong>Step 1: Configure your DNS records</strong></p>
                        <ul style="font-size: 12px; color: #1d2327; margin: 0; padding-left: 20px; list-style: disc;">
                            <li style="margin-bottom: 5px;">Add a <strong>CNAME record</strong> pointing your domain to: <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code></li>
                            <li style="margin-bottom: 5px;">Or add an <strong>A record</strong> pointing to your server's IP address</li>
                            <li>For www subdomain, add another CNAME pointing <code>www.yourdomain.com</code> → <code>yourdomain.com</code></li>
                        </ul>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 2px; margin-bottom: 15px;">
                        <p style="font-size: 13px; color: #1d2327; margin: 0 0 10px;"><strong>Step 2: Configure your web server</strong></p>
                        <p style="font-size: 12px; color: #1d2327; margin: 0;">
                            Your web server (Apache/Nginx) must be configured to accept requests for the custom domain 
                            and route them to this WordPress installation. Contact your hosting provider if needed.
                        </p>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 2px;">
                        <p style="font-size: 13px; color: #1d2327; margin: 0 0 10px;"><strong>Step 3: Enter the domain above</strong></p>
                        <p style="font-size: 12px; color: #1d2327; margin: 0;">
                            Enter just the domain name (e.g., <code>flosc.ai</code>) in the Custom Domain field for your flow. 
                            FLOSC will handle the rest!
                        </p>
                    </div>
                </div>
                
                <!-- Save All Flows Button -->
                <div style="background: #f0f0f1; padding: 20px; border-radius: 2px; text-align: center; margin-top: 20px;">
                    <button type="submit" name="flosc_save_all_flows" value="1" class="button button-primary button-large">
                        Save All Flows
                    </button>
                    <p style="margin: 10px 0 0; font-size: 12px; color: #50575e;">Saves identity settings for all <?php echo count($all_flows); ?> flows at once</p>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- SINGLE FLOW EDIT VIEW -->
            <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
                <?php flosc_tab_header('🏷️', 'Identity'); ?>
                <?php
                $identity_docs_url = add_query_arg([
                    'page' => 'flosc-settings',
                    'ivr'  => $selected_ivr,
                    'tab'  => 'documentation',
                    'doc'  => 'ref-admin',
                ], admin_url('admin.php')) . '#tab-identity';
                ?>
                <div style="margin:-8px 0 14px; text-align:right;">
                    <a href="<?php echo esc_url($identity_docs_url); ?>" style="font-size:12px; text-decoration:none; color:#2271b1;">Docs</a>
                </div>
                
                <!-- URL Mapping Info Box -->
                <?php 
                // v1.3.5: Preserve underscores in default slug
                $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($selected_ivr, PATHINFO_FILENAME)));
                $current_slug = $flow_settings['slug'] ?? $default_slug;
                $flow_url = home_url('/' . $current_slug . '/');
                ?>
                <div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 20px; border-radius: 2px; color: #1d2327; margin-bottom: 25px;">
                    <div style="font-size: 12px; color: #50575e; margin-bottom: 5px;">This flow is accessible at:</div>
                    <div style="font-family: monospace; font-size: 18px; font-weight: 600;">
                        <a href="<?php echo esc_url($flow_url); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">
                            <?php echo esc_html($flow_url); ?> &#8599;
                        </a>
                        <?php if (!empty($flow_settings['domain'])): ?>
                            <span style="color: #787c82; margin: 0 10px;">&rarr;</span>
                            <a href="https://<?php echo esc_attr($flow_settings['domain']); ?>/" target="_blank" style="color: #2271b1; text-decoration: none;">
                                https://<?php echo esc_html($flow_settings['domain']); ?>/ &#8599;
                            </a>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 11px; color: #787c82; margin-top: 8px;">
                        IVR File: <code style="background: #e0e0e0; padding: 2px 6px; border-radius: 2px;"><?php echo esc_html($selected_ivr); ?></code>
                    </div>
                </div>
                
                <?php $fi = $flow_settings['identity'] ?? []; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <code style="background: #f3f4f6; padding: 8px 12px; border-radius: 4px;"><?php echo esc_html( home_url('/') ); ?></code>
                                <input type="text" id="flow_slug" name="flow_slug" style="width: 180px; font-weight: 600;"
                                       value="<?php echo esc_attr($current_slug); ?>"
                                       placeholder="myapp">
                                <code style="background: #f3f4f6; padding: 8px 12px; border-radius: 4px;">/</code>
                            </div>
                            <p class="description">The URL path where this flow is served. Lowercase letters, numbers, hyphens only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_domain">Custom Domain</label></th>
                        <td>
                            <input type="text" id="flow_domain" name="flow_domain" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['domain'] ?? ''); ?>"
                                   placeholder="e.g., flosc.ai or lesaep.com">
                            <p class="description">Custom domain pointing to this flow</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_name">Name</label></th>
                        <td>
                            <input type="text" id="flow_name" name="flow_name" class="regular-text"
                                   value="<?php echo esc_attr($fi['name'] ?? ''); ?>"
                                   placeholder="e.g., FLOSC or LeSAEp">
                            <p class="description">Display name shown in the chat header.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_title">Title</label></th>
                        <td>
                            <input type="text" id="flow_title" name="flow_title" class="regular-text"
                                   value="<?php echo esc_attr($fi['title'] ?? ''); ?>"
                                   placeholder="e.g., Learn Excellent Standard American English Pronunciation">
                            <p class="description">Description shown under the name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="flow_tagline" name="flow_tagline" class="regular-text"
                                   value="<?php echo esc_attr($fi['tagline'] ?? ''); ?>"
                                   placeholder="e.g., Freeline → Login → Offer → Sale → Content">
                            <p class="description">Short tagline shown below the title. Leave empty to hide.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_primary_color">Brand Color</label></th>
                        <td>
                            <input type="color" id="flow_primary_color" name="flow_primary_color"
                                   value="<?php echo esc_attr($fi['primary_color'] ?? '#4f46e5'); ?>"
                                   style="width: 60px; height: 40px; padding: 0; border: 1px solid #ccc; cursor: pointer;">
                            <span id="color-preview" style="margin-left: 10px; padding: 5px 15px; border-radius: 4px; color: white; background: <?php echo esc_attr($fi['primary_color'] ?? '#4f46e5'); ?>;">
                                Preview
                            </span>
                            <p class="description">Lesson highlights, form buttons, focus rings, carousel controls. Sets <code>--flosc-primary</code>.</p>
                            <?php ob_start(); ?>
                            document.getElementById('flow_primary_color').addEventListener('input', function(e) {
                                document.getElementById('color-preview').style.background = e.target.value;
                            });
                            <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_chatlogo_url">Chat Logo</label></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="url" id="flow_chatlogo_url" name="flow_chatlogo_url" class="regular-text"
                                       value="<?php echo esc_attr($fi['chatlogo_url'] ?? ''); ?>"
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_chatlogo">Choose Image</button>
                                <img src="<?php echo esc_url($fi['chatlogo_url'] ?? ''); ?>" 
                                     alt="" id="flosc_chatlogo_preview"
                                     style="max-height: 40px; border-radius: 4px;<?php echo empty($fi['chatlogo_url']) ? ' display: none;' : ''; ?>">
                            </div>
                            <p class="description">Image shown in the chat header beside the name. Wider/rectangular formats welcome.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_favicon_url">Favicon</label></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="url" id="flow_favicon_url" name="flow_favicon_url" class="regular-text"
                                       value="<?php echo esc_attr($fi['favicon_url'] ?? ''); ?>"
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_favicon">Choose Image</button>
                                <img src="<?php echo esc_url($fi['favicon_url'] ?? ''); ?>" 
                                     alt="" id="flosc_favicon_preview"
                                     style="width: 32px; height: 32px; border-radius: 6px;<?php echo empty($fi['favicon_url']) ? ' display: none;' : ''; ?>">
                            </div>
                            <p class="description">Browser tab icon. Square PNG recommended (512&times;512+).</p>
                        </td>
                    </tr>
                    <?php wp_enqueue_media(); ?>
                    <tr>
                        <th><label for="flow_badgeUrl">Badge Image</label></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="url" id="flow_badgeUrl" name="flow_badgeUrl" class="regular-text"
                                       value="<?php echo esc_attr($fi['badgeUrl'] ?? ''); ?>"
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_badge">Choose Image</button>
                                <img src="<?php echo esc_url($fi['badgeUrl'] ?? ''); ?>" 
                                     alt="" id="flosc_badge_preview"
                                     style="max-height: 40px; border-radius: 6px;<?php echo empty($fi['badgeUrl']) ? ' display: none;' : ''; ?>">
                            </div>
                            <p class="description">Badge shown in the AI welcome message. Leave empty for no badge.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <option value="active" <?php selected($flow_settings['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($flow_settings['status'] ?? '', 'draft'); ?>>Draft</option>
                            </select>
                            <p class="description">Draft flows are only visible to admins.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_privacy_policy_content">Privacy Policy Content</label></th>
                        <td>
                            <textarea id="flow_privacy_policy_content" name="flow_privacy_policy_content" class="large-text" rows="8"><?php echo esc_textarea($fi['privacy_policy_content'] ?? ''); ?></textarea>
                            <p class="description">Per-flow content shown at /privacy. HTML is allowed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_terms_of_service_content">Terms of Service Content</label></th>
                        <td>
                            <textarea id="flow_terms_of_service_content" name="flow_terms_of_service_content" class="large-text" rows="8"><?php echo esc_textarea($fi['terms_of_service_content'] ?? ''); ?></textarea>
                            <p class="description">Per-flow content shown at /terms-of-service. HTML is allowed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_data_deletion_content">Data Deletion Content</label></th>
                        <td>
                            <textarea id="flow_data_deletion_content" name="flow_data_deletion_content" class="large-text" rows="8"><?php echo esc_textarea($fi['data_deletion_content'] ?? ''); ?></textarea>
                            <p class="description">Per-flow content shown at /data-deletion. HTML is allowed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_platform_compliance_content">Platform Compliance Content</label></th>
                        <td>
                            <textarea id="flow_platform_compliance_content" name="flow_platform_compliance_content" class="large-text" rows="8"><?php echo esc_textarea($fi['platform_compliance_content'] ?? ''); ?></textarea>
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
                <?php if (!empty($flow_settings['domain'])): ?>
                <div style="background: #fef3c7; border: 1px solid #dba617; padding: 15px 20px; border-radius: 2px; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px; color: #50575e; font-size: 14px;">DNS Setup for <?php echo esc_html($flow_settings['domain']); ?></h4>
                    <p style="font-size: 13px; color: #50575e; margin: 0;">
                        Point your domain to: <code style="background: white; padding: 3px 8px; border-radius: 4px;"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code>
                    </p>
                    <p style="font-size: 12px; color: #50575e; margin: 8px 0 0;">
                        <a href="https://flosc.ai/docs/dns" target="_blank" style="color: #50575e;">Full DNS Configuration Guide</a>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php endif; ?>
            
        <?php elseif ($active_tab === 'ivr-messages'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ivr-messages.php'; ?>
            
        <?php elseif ($active_tab === 'style'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/chat-styling.php'; ?>
            
        <?php elseif ($active_tab === 'ai'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-configuration.php'; ?>
        <?php elseif ($active_tab === 'ai-knowledge'): ?>
            <?php $redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($selected_ivr) . '&tab=ai#flosc-kb-section'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($redirect_url); ?>">Knowledge Base in AI tab</a>&hellip;</p>
        <?php elseif ($active_tab === 'ai-guide'): ?>
            <?php $redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($selected_ivr) . '&tab=documentation&doc=ref-ai-config'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($redirect_url); ?>">AI Configuration Guide in Documentation</a>&hellip;</p>
        <?php elseif ($active_tab === 'knowledge'): ?>
            <?php $redirect_url = admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($selected_ivr) . '&tab=ai#flosc-kb-section'); ?>
            <?php wp_add_inline_script('flosc-admin', 'window.location.replace(' . wp_json_encode($redirect_url) . ');'); ?>
            <p>Redirecting to <a href="<?php echo esc_url($redirect_url); ?>">Knowledge Base in AI tab</a>&hellip;</p>
            
        <?php elseif ($active_tab === 'quiz'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/quiz.php'; ?>
            
        <?php elseif ($active_tab === 'email'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/email.php'; ?>
            
        <?php elseif ($active_tab === 'lessons'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/lessons.php'; ?>
            
        <?php elseif ($active_tab === 'autoprompts'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/autoprompts.php'; ?>

        <?php elseif ($active_tab === 'member-levels'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/member-levels.php'; ?>

        <?php elseif ($active_tab === 'offers'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/offers.php'; ?>

        <?php elseif ($active_tab === 'login'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/login-registration.php'; ?>
            
        <?php elseif ($active_tab === 'payments'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/payments.php'; ?>
            
        <?php elseif ($active_tab === 'sso'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/sso.php'; ?>

        <?php elseif ($active_tab === 'administration'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/administration.php'; ?>

        <?php elseif ($active_tab === 'ui'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ui-navigation.php'; ?>

        <?php elseif ($active_tab === 'chat-logs'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/chat-logs.php'; ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-feedback.php'; ?>

        <?php elseif ($active_tab === 'documentation'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/documentation.php'; ?>

        <?php elseif ($active_tab === 'da1'): ?>
            <div style="margin: 18px 0;">
                <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-da1') ); ?>" class="button button-primary">Open DA1 Catalog</a>
            </div>
            <p>DA1 catalog is available as a dedicated admin page.</p>

        <?php endif; ?>

        <?php if ($active_tab !== 'documentation' && $active_tab !== 'autoprompts' && $active_tab !== 'da1'): ?>
        <p class="submit" style="margin-top: 20px;">
            <button type="submit" name="flosc_save" class="button button-primary button-large">
                Save Settings for <?php echo esc_html($flow_settings['identity']['name'] ?? $selected_ivr); ?>
            </button>
        </p>
        <?php endif; ?>
        
        <?php flosc_tab_footer(); ?>
    </form>
</div>

<!-- Styles in assets/css/flosc-admin.css -->
