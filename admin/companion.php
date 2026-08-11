<?php
/**
 * FLOSC Companion Configuration Tab
 * 
 * Configures the floating Companion chat widget that appears on WordPress
 * pages alongside FLOSC content and interactions.
 *
 * Operator display modes (stored values unchanged for compatibility):
 * - Full-page  (in_chat)   — Chat only at the flow URL; no site bubble
 * - Companion  (companion) — Floating bubble on WP pages (except full chat route)
 * - Hybrid     (both)      — Full-page + companion; expand/collapse continuous session
 * 
 * Settings (per-flow via companion override group):
 * - content_display_mode:  'in_chat' | 'companion' | 'both'
 * - enabled:               Whether the companion widget is active
 * - position:              'bottom-right' | 'bottom-left'
 * - greeting:              Initial greeting message
 * - accent_color:          Custom accent color (hex, or empty for default)
 * - show_for_visitors:     Whether non-logged-in users see the widget
 * 
 * @package FLOSC
 * @since   1.6.0
 */

if (!defined('ABSPATH')) exit;

// Tab header
flosc_tab_header('🤝', 'Companion');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];

// Read current values with defaults
$flosc_content_display_mode = $flosc_flow_settings['companion_content_display_mode'] ?? 'in_chat';
// Normalize enabled to '1'/'' for checkbox + summary (DB may store 1/true/"1").
$flosc_enabled              = !empty($flosc_flow_settings['companion_enabled']) ? '1' : '';
$flosc_position             = $flosc_flow_settings['companion_position'] ?? 'bottom-right';
// Brand-neutral FLOSC defaults only — product voice lives in per-flow params (not hard-coded here).
$flosc_greeting             = $flosc_flow_settings['companion_greeting'] ?? 'Chat with us';
$flosc_subtitle             = $flosc_flow_settings['companion_subtitle'] ?? 'We reply instantly';
$flosc_contextual_prompt    = $flosc_flow_settings['companion_contextual_prompt'] ?? 'What do you want to explore together?';
$flosc_tier_visitor         = strtoupper( (string) ( $flosc_flow_settings['companion_profile_tier_visitor'] ?? 'V' ) );
$flosc_tier_guest           = strtoupper( (string) ( $flosc_flow_settings['companion_profile_tier_guest'] ?? 'G' ) );
$flosc_tier_member          = strtoupper( (string) ( $flosc_flow_settings['companion_profile_tier_member'] ?? 'M' ) );
$flosc_tier_visitor_label   = (string) ( $flosc_flow_settings['companion_profile_tier_visitor_label'] ?? 'Visitor' );
$flosc_tier_guest_label     = (string) ( $flosc_flow_settings['companion_profile_tier_guest_label'] ?? 'Guest' );
$flosc_tier_member_label    = (string) ( $flosc_flow_settings['companion_profile_tier_member_label'] ?? 'Member' );
// Explicit visibility toggles so header title/subtitle are never silent "dead" fields.
// Default true (show) preserves prior behavior; a saved 0 hides the element.
$flosc_show_header_title    = array_key_exists('companion_show_header_title', $flosc_flow_settings) ? !empty($flosc_flow_settings['companion_show_header_title']) : true;
$flosc_show_header_subtitle = array_key_exists('companion_show_header_subtitle', $flosc_flow_settings) ? !empty($flosc_flow_settings['companion_show_header_subtitle']) : true;
// Some floscAdmins won't want to offer the standalone full chat page; default true (show).
$flosc_show_open_fullpage   = array_key_exists('companion_show_open_fullpage', $flosc_flow_settings) ? !empty($flosc_flow_settings['companion_show_open_fullpage']) : true;
$flosc_accent_color         = $flosc_flow_settings['companion_accent_color'] ?? '';
// Sales default: visitors see companion when the key was never saved (new flows).
// After Style save: 1 = on, 0 or empty legacy = off.
if (array_key_exists('companion_show_for_visitors', $flosc_flow_settings)) {
    $flosc_show_for_visitors = !empty($flosc_flow_settings['companion_show_for_visitors']) ? '1' : '';
} else {
    $flosc_show_for_visitors = '1';
}
$flosc_target_include       = $flosc_flow_settings['companion_target_include'] ?? '';
$flosc_target_exclude       = $flosc_flow_settings['companion_target_exclude'] ?? '';
$flosc_panel_width          = intval($flosc_flow_settings['companion_panel_width'] ?? 380);
$flosc_panel_height         = intval($flosc_flow_settings['companion_panel_height'] ?? 560);
$flosc_mobile_behavior      = $flosc_flow_settings['companion_mobile_behavior'] ?? 'fullscreen';
$flosc_pass_page_context    = $flosc_flow_settings['companion_pass_page_context'] ?? '1';
$flosc_context_scope        = $flosc_flow_settings['companion_context_scope'] ?? 'basic';
$flosc_page_intent_phrases  = $flosc_flow_settings['companion_page_intent_phrases'] ?? '';
$flosc_auto_open_enabled    = $flosc_flow_settings['companion_auto_open_enabled'] ?? '';
$flosc_auto_open_delay_ms   = intval($flosc_flow_settings['companion_auto_open_delay_ms'] ?? 1500);
$flosc_auto_open_once       = $flosc_flow_settings['companion_auto_open_once_per_session'] ?? '1';
$flosc_launcher_size        = intval($flosc_flow_settings['companion_launcher_size'] ?? 60);
$flosc_launcher_icon        = $flosc_flow_settings['companion_launcher_icon'] ?? 'chat';
$flosc_launch_on_exit       = $flosc_flow_settings['companion_launch_on_exit_intent'] ?? '';
$flosc_launch_on_scroll     = $flosc_flow_settings['companion_launch_on_scroll_threshold'] ?? '';
$flosc_launch_scroll_pct    = intval($flosc_flow_settings['companion_launch_on_scroll_percent'] ?? 0);
$flosc_trigger_desktop_only = $flosc_flow_settings['companion_trigger_desktop_only'] ?? '1';
$flosc_trigger_min_time_ms  = intval($flosc_flow_settings['companion_trigger_min_page_time_ms'] ?? 0);
$flosc_trigger_suppress_ac  = $flosc_flow_settings['companion_trigger_suppress_on_auth_checkout'] ?? '1';
$flosc_trigger_suppress_paths = $flosc_flow_settings['companion_trigger_suppress_path_patterns'] ?? '';
$flosc_motion_mode         = $flosc_flow_settings['companion_motion_mode'] ?? 'system';
$flosc_focus_on_open       = $flosc_flow_settings['companion_focus_on_open'] ?? '1';
$flosc_allow_escape_close  = $flosc_flow_settings['companion_allow_escape_close'] ?? '1';
$flosc_keyboard_shortcut   = $flosc_flow_settings['companion_enable_keyboard_shortcut'] ?? '';
$flosc_shortcut_key        = $flosc_flow_settings['companion_keyboard_shortcut_key'] ?? 'k';
$flosc_launcher_aria_label = $flosc_flow_settings['companion_launcher_aria_label'] ?? esc_html__('Open Chat', 'flosc');
$flosc_close_aria_label    = $flosc_flow_settings['companion_close_aria_label'] ?? esc_html__('Collapse Chat', 'flosc');
$flosc_remember_open_state = $flosc_flow_settings['companion_remember_open_state'] ?? '';
$flosc_state_storage       = $flosc_flow_settings['companion_state_storage'] ?? 'session';
$flosc_trigger_cooldown_ms = intval($flosc_flow_settings['companion_trigger_cooldown_ms'] ?? 0);
$flosc_routing_mode        = sanitize_key((string) ($flosc_flow_settings['companion_routing_mode'] ?? 'hub'));
if (!in_array($flosc_routing_mode, ['hub', 'domain_persistence'], true)) {
    $flosc_routing_mode = 'hub';
}
// Hub defaults derived only from this flow's parameters (Identity domain/slug + Content lessons category).
$flosc_hub_defaults = function_exists('flosc_companion_hub_defaults_from_flow')
    ? flosc_companion_hub_defaults_from_flow(is_array($flosc_flow_settings) ? $flosc_flow_settings : [])
    : [
        'fullscreen' => home_url('/'),
        'companion'  => home_url('/'),
        'chat_app'   => home_url('/'),
        'flow_slug'  => sanitize_title((string) ($flosc_flow_settings['slug'] ?? '')),
        'content_item_category' => '',
        'include_rules' => '',
    ];
$flosc_default_fullscreen    = (string) ($flosc_hub_defaults['fullscreen'] ?? home_url('/'));
$flosc_default_companion_hub = (string) ($flosc_hub_defaults['companion'] ?? home_url('/'));
$flosc_default_chat_app      = (string) ($flosc_hub_defaults['chat_app'] ?? home_url('/'));
$flosc_hub_fullscreen_url    = esc_url((string) ($flosc_flow_settings['companion_hub_fullscreen_url'] ?? $flosc_default_fullscreen));
if ($flosc_hub_fullscreen_url === '') {
    $flosc_hub_fullscreen_url = esc_url($flosc_default_fullscreen);
}
$flosc_hub_companion_url = esc_url((string) ($flosc_flow_settings['companion_hub_companion_url'] ?? $flosc_default_companion_hub));
if ($flosc_hub_companion_url === '') {
    $flosc_hub_companion_url = esc_url($flosc_default_companion_hub);
}
$flosc_chat_app_url = esc_url((string) ($flosc_flow_settings['companion_chat_app_url'] ?? $flosc_default_chat_app));
if ($flosc_chat_app_url === '') {
    $flosc_chat_app_url = esc_url($flosc_default_chat_app);
}
$flosc_companion_flow_slug = sanitize_title((string) ($flosc_flow_settings['companion_flow_slug'] ?? ($flosc_hub_defaults['flow_slug'] ?? '')));

$flosc_parse_target_rules = static function ($raw_rules) {
    $rules = [];
    $chunks = preg_split('/[\r\n,]+/', (string) $raw_rules);
    if (!is_array($chunks)) {
        return $rules;
    }

    foreach ($chunks as $raw_rule) {
        $raw_rule = trim((string) $raw_rule);
        if ($raw_rule === '') {
            continue;
        }

        if (strpos($raw_rule, ':') === false) {
            $rules[] = ['type' => 'path', 'value' => '/' . ltrim($raw_rule, '/')];
            continue;
        }

        list($flosc_type, $value) = array_map('trim', explode(':', $raw_rule, 2));
        $flosc_type = strtolower($flosc_type);
        $value = (string) $value;
        if ($value === '') {
            continue;
        }

        if ($flosc_type === 'path') {
            $value = '/' . ltrim($value, '/');
        }

        $rules[] = ['type' => $flosc_type, 'value' => $value];
    }

    return $rules;
};

$flosc_include_rules = $flosc_parse_target_rules($flosc_target_include);
$flosc_exclude_rules = $flosc_parse_target_rules($flosc_target_exclude);

$flosc_target_include_pages = [];
$flosc_target_include_posts = [];
$flosc_target_include_categories = [];
$flosc_target_include_tags = [];
$flosc_target_include_custom = [];

$flosc_target_exclude_pages = [];
$flosc_target_exclude_posts = [];
$flosc_target_exclude_categories = [];
$flosc_target_exclude_tags = [];
$flosc_target_exclude_custom = [];

foreach ($flosc_include_rules as $flosc_rule) {
    $flosc_rule_type = (string) ($flosc_rule['type'] ?? '');
    $flosc_rule_value = trim((string) ($flosc_rule['value'] ?? ''));
    if ($flosc_rule_value === '') {
        continue;
    }

    if ($flosc_rule_type === 'page' && ctype_digit($flosc_rule_value)) {
        $flosc_target_include_pages[] = (int) $flosc_rule_value;
    } elseif ($flosc_rule_type === 'post' && ctype_digit($flosc_rule_value)) {
        $flosc_target_include_posts[] = (int) $flosc_rule_value;
    } elseif ($flosc_rule_type === 'category' && ctype_digit($flosc_rule_value)) {
        $flosc_target_include_categories[] = (int) $flosc_rule_value;
    } elseif ($flosc_rule_type === 'tag' && ctype_digit($flosc_rule_value)) {
        $flosc_target_include_tags[] = (int) $flosc_rule_value;
    } else {
        $flosc_target_include_custom[] = $flosc_rule_type . ':' . $flosc_rule_value;
    }
}

foreach ($flosc_exclude_rules as $flosc_rule) {
    $flosc_rule_type = (string) ($flosc_rule['type'] ?? '');
    $flosc_rule_value = trim((string) ($flosc_rule['value'] ?? ''));
    if ($flosc_rule_value === '') {
        continue;
    }

    if ($flosc_rule_type === 'page' && ctype_digit($flosc_rule_value)) {
        $flosc_target_exclude_pages[] = (int) $flosc_rule_value;
    } elseif ($flosc_rule_type === 'post' && ctype_digit($flosc_rule_value)) {
        $flosc_target_exclude_posts[] = (int) $flosc_rule_value;
    } elseif ($flosc_rule_type === 'category' && ctype_digit($flosc_rule_value)) {
        $flosc_target_exclude_categories[] = (int) $flosc_rule_value;
    } elseif ($flosc_rule_type === 'tag' && ctype_digit($flosc_rule_value)) {
        $flosc_target_exclude_tags[] = (int) $flosc_rule_value;
    } else {
        $flosc_target_exclude_custom[] = $flosc_rule_type . ':' . $flosc_rule_value;
    }
}

$flosc_target_include_pages = array_values(array_unique(array_map('intval', $flosc_target_include_pages)));
$flosc_target_include_posts = array_values(array_unique(array_map('intval', $flosc_target_include_posts)));
$flosc_target_include_categories = array_values(array_unique(array_map('intval', $flosc_target_include_categories)));
$flosc_target_include_tags = array_values(array_unique(array_map('intval', $flosc_target_include_tags)));
$flosc_target_include_custom = array_values(array_unique(array_filter(array_map('trim', $flosc_target_include_custom))));

$flosc_target_exclude_pages = array_values(array_unique(array_map('intval', $flosc_target_exclude_pages)));
$flosc_target_exclude_posts = array_values(array_unique(array_map('intval', $flosc_target_exclude_posts)));
$flosc_target_exclude_categories = array_values(array_unique(array_map('intval', $flosc_target_exclude_categories)));
$flosc_target_exclude_tags = array_values(array_unique(array_map('intval', $flosc_target_exclude_tags)));
$flosc_target_exclude_custom = array_values(array_unique(array_filter(array_map('trim', $flosc_target_exclude_custom))));

$flosc_target_include_has_rules = !empty($flosc_target_include_pages)
    || !empty($flosc_target_include_posts)
    || !empty($flosc_target_include_categories)
    || !empty($flosc_target_include_tags)
    || !empty($flosc_target_include_custom);
$flosc_target_exclude_has_rules = !empty($flosc_target_exclude_pages)
    || !empty($flosc_target_exclude_posts)
    || !empty($flosc_target_exclude_categories)
    || !empty($flosc_target_exclude_tags)
    || !empty($flosc_target_exclude_custom);
$flosc_target_has_any_rules = $flosc_target_include_has_rules || $flosc_target_exclude_has_rules;
$flosc_target_mode = $flosc_target_include_has_rules ? 'selected' : 'sitewide';

$flosc_companion_effective_summary = '';
if ($flosc_content_display_mode === 'in_chat' || $flosc_enabled !== '1') {
    $flosc_companion_effective_summary = 'Full-page mode: chat at the flow URL only. Companion widget is OFF.';
} elseif ($flosc_target_mode === 'selected') {
    $flosc_companion_effective_summary = ($flosc_content_display_mode === 'both' ? 'Hybrid' : 'Companion')
        . ': widget ON for selected targets only (Include/Exclude rules).';
} else {
    $flosc_companion_effective_summary = ($flosc_content_display_mode === 'both' ? 'Hybrid' : 'Companion')
        . ': widget ON sitewide (except full-page chat route).';
}

$flosc_target_pages = get_pages([
    'sort_column' => 'post_title',
    'sort_order' => 'ASC',
    'post_status' => ['publish', 'private', 'draft'],
]);

$flosc_target_posts = get_posts([
    'post_type' => 'post',
    'post_status' => ['publish', 'private', 'draft'],
    'posts_per_page' => 500,
    'orderby' => 'title',
    'order' => 'ASC',
    'fields' => 'ids',
]);

$flosc_target_categories = get_terms([
    'taxonomy' => 'category',
    'hide_empty' => false,
]);

$flosc_target_tags = get_terms([
    'taxonomy' => 'post_tag',
    'hide_empty' => false,
]);

$flosc_target_posts_map = [];
foreach ((array) $flosc_target_posts as $flosc_target_post_id) {
    $flosc_target_posts_map[(int) $flosc_target_post_id] = get_the_title((int) $flosc_target_post_id);
}

$flosc_companion_launcher_icons = apply_filters('flosc_companion_launcher_icon_choices', [
    'product_logo' => 'Product Logo (Chat Logo)',
    'chat' => 'Chat Bubble',
    'help' => 'Help Circle',
    'spark' => 'Spark',
]);
if (!is_array($flosc_companion_launcher_icons) || empty($flosc_companion_launcher_icons)) {
    $flosc_companion_launcher_icons = ['product_logo' => 'Product Logo (Chat Logo)', 'chat' => 'Chat Bubble'];
}

$flosc_companion_positions = apply_filters('flosc_companion_allowed_positions', ['bottom-right', 'bottom-left']);
if (!is_array($flosc_companion_positions) || empty($flosc_companion_positions)) {
    $flosc_companion_positions = ['bottom-right', 'bottom-left'];
}
$flosc_companion_position_labels = apply_filters('flosc_companion_position_labels', [
    'bottom-right' => '↘ Bottom Right',
    'bottom-left' => '↙ Bottom Left',
]);

$flosc_companion_mobile_behaviors = apply_filters('flosc_companion_mobile_behaviors', ['fullscreen', 'panel']);
if (!is_array($flosc_companion_mobile_behaviors) || empty($flosc_companion_mobile_behaviors)) {
    $flosc_companion_mobile_behaviors = ['fullscreen', 'panel'];
}
$flosc_companion_mobile_labels = apply_filters('flosc_companion_mobile_behavior_labels', [
    'fullscreen' => 'Fullscreen Overlay',
    'panel' => 'Floating Panel',
]);

$flosc_companion_context_scopes = apply_filters('flosc_companion_context_scopes', ['basic', 'extended']);
if (!is_array($flosc_companion_context_scopes) || empty($flosc_companion_context_scopes)) {
    $flosc_companion_context_scopes = ['basic', 'extended'];
}
$flosc_companion_context_scope_labels = apply_filters('flosc_companion_context_scope_labels', [
    'basic' => 'Basic (URL + Post ID)',
    'extended' => 'Extended (URL + Post ID + Type + Title)',
]);

$flosc_companion_motion_modes = apply_filters('flosc_companion_motion_modes', ['system', 'reduce', 'full']);
if (!is_array($flosc_companion_motion_modes) || empty($flosc_companion_motion_modes)) {
    $flosc_companion_motion_modes = ['system', 'reduce', 'full'];
}
$flosc_companion_motion_mode_labels = apply_filters('flosc_companion_motion_mode_labels', [
    'system' => 'Follow System Preference',
    'reduce' => 'Reduce Motion',
    'full' => 'Full Motion',
]);

$flosc_companion_state_storages = apply_filters('flosc_companion_state_storages', ['session', 'local']);
if (!is_array($flosc_companion_state_storages) || empty($flosc_companion_state_storages)) {
    $flosc_companion_state_storages = ['session', 'local'];
}
$flosc_companion_state_storage_labels = apply_filters('flosc_companion_state_storage_labels', [
    'session' => 'Session (tab lifetime)',
    'local' => 'Local (persistent)',
]);

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

$flosc_companion_extension_hooks = [
    ['hook' => 'flosc_companion_defaults', 'type' => 'filter', 'purpose' => 'Override default companion runtime values.'],
    ['hook' => 'flosc_companion_frontend_config', 'type' => 'filter', 'purpose' => 'Adjust the final frontend config payload before init.'],
    ['hook' => 'flosc_companion_allowed_modes', 'type' => 'filter', 'purpose' => 'Define allowed content display modes.'],
    ['hook' => 'flosc_companion_widget_modes', 'type' => 'filter', 'purpose' => 'Choose which modes render widget shell.'],
    ['hook' => 'flosc_companion_allowed_positions', 'type' => 'filter', 'purpose' => 'Allow launcher positions shown in Style tab.'],
    ['hook' => 'flosc_companion_mobile_behaviors', 'type' => 'filter', 'purpose' => 'Control supported mobile open behaviors.'],
    ['hook' => 'flosc_companion_context_scopes', 'type' => 'filter', 'purpose' => 'Control context-handoff scope options.'],
    ['hook' => 'flosc_companion_motion_modes', 'type' => 'filter', 'purpose' => 'Set motion/accessibility mode options.'],
    ['hook' => 'flosc_companion_state_storages', 'type' => 'filter', 'purpose' => 'Set browser storage backends for open-state/cooldown.'],
    ['hook' => 'flosc_companion_launcher_svg_paths', 'type' => 'filter', 'purpose' => 'Override launcher icon SVG path map.'],
    ['hook' => 'flosc_companion_context_params', 'type' => 'filter', 'purpose' => 'Customize iframe context params at launch.'],
    ['hook' => 'flosc_companion_target_rule_types', 'type' => 'filter', 'purpose' => 'Add or constrain targeting rule types.'],
    ['hook' => 'flosc_companion_target_rule_match', 'type' => 'filter', 'purpose' => 'Inject custom rule match logic.'],
    ['hook' => 'flosc_companion_launcher_icon_choices', 'type' => 'filter', 'purpose' => 'Configure launcher icon choices in Style UI.'],
    ['hook' => 'flosc_companion_position_labels', 'type' => 'filter', 'purpose' => 'Override launcher position labels in Style UI.'],
    ['hook' => 'flosc_companion_mobile_behavior_labels', 'type' => 'filter', 'purpose' => 'Override mobile behavior labels in Style UI.'],
    ['hook' => 'flosc_companion_context_scope_labels', 'type' => 'filter', 'purpose' => 'Override context scope labels in Style UI.'],
    ['hook' => 'flosc_companion_motion_mode_labels', 'type' => 'filter', 'purpose' => 'Override motion mode labels in Style UI.'],
    ['hook' => 'flosc_companion_state_storage_labels', 'type' => 'filter', 'purpose' => 'Override state storage labels in Style UI.'],
    ['hook' => 'flosc_companion_numeric_limits', 'type' => 'filter', 'purpose' => 'Set min/max numeric bounds for companion controls and sanitization.'],
];

$flosc_companion_snippet_numeric_limits = <<<'FLOSC_COMPANION_SNIPPET_NUMERIC_LIMITS'
add_filter('flosc_companion_numeric_limits', function ($limits) {
    $limits['panel_width_min'] = 320;
    $limits['panel_width_max'] = 640;
    $limits['trigger_cooldown_min_ms'] = 15000;
    $limits['trigger_cooldown_max_ms'] = 3600000;
    return $limits;
});
FLOSC_COMPANION_SNIPPET_NUMERIC_LIMITS;

$flosc_companion_snippet_frontend_config = <<<'FLOSC_COMPANION_SNIPPET_FRONTEND_CONFIG'
add_filter('flosc_companion_frontend_config', function ($config, $framework) {
    $config['autoOpenDelayMs'] = max(2000, (int) ($config['autoOpenDelayMs'] ?? 0));
    $config['launcherAriaLabel'] = 'Open chat companion';
    return $config;
}, 10, 2);
FLOSC_COMPANION_SNIPPET_FRONTEND_CONFIG;
?>

<h2>Display Mode</h2>
<p>
    <strong>Full-page</strong> — chat at the flow URL.
    <strong>Companion</strong> — floating bubble on WordPress pages.
    <strong>Hybrid</strong> — both surfaces, with expand/collapse and continuous session.
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_companion_content_display_mode">Display Mode</label></th>
        <td>
            <fieldset>
                <label class="flosc-companion-mode-card <?php echo esc_attr( $flosc_content_display_mode === 'in_chat' ? 'is-active' : '' ); ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="in_chat" <?php checked($flosc_content_display_mode, 'in_chat'); ?>>
                    <strong>Full-page</strong> <em class="flosc-companion-mode-default">(default)</em>
                    <br><span class="flosc-companion-mode-copy">
                        Chat only at the flow URL. No floating companion widget.
                    </span>
                </label>
                
                <label class="flosc-companion-mode-card <?php echo esc_attr( $flosc_content_display_mode === 'companion' ? 'is-active' : '' ); ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="companion" <?php checked($flosc_content_display_mode, 'companion'); ?>>
                    <strong>Companion</strong>
                    <br><span class="flosc-companion-mode-copy">
                        Floating bubble on WordPress pages (except the full-page chat route).
                        Targeting uses Include/Exclude below.
                    </span>
                </label>
                
                <label class="flosc-companion-mode-card <?php echo esc_attr( $flosc_content_display_mode === 'both' ? 'is-active' : '' ); ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="both" <?php checked($flosc_content_display_mode, 'both'); ?>>
                    <strong>Hybrid</strong>
                    <br><span class="flosc-companion-mode-copy">
                        Full-page chat and companion bubble. Expand to full page, collapse back to the hub;
                        conversation continues. Targeting uses Include/Exclude below.
                    </span>
                </label>
            </fieldset>
        </td>
    </tr>
</table>

<!-- Companion Widget Settings (only relevant when companion or both mode is active) -->
<div id="companion-widget-settings" class="<?php echo esc_attr( $flosc_content_display_mode === 'in_chat' ? 'flosc-companion-disabled' : '' ); ?>">
    
    <hr class="flosc-companion-divider">
    <h2>Companion Widget Settings</h2>
    <p>Configure the floating companion widget that appears on your WordPress pages.</p>
    
    <table class="form-table">
        <tr>
            <th scope="row"><label for="flow_companion_enabled">Enable Widget</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_enabled" id="flow_companion_enabled" value="1" <?php checked($flosc_enabled, '1'); ?>>
                    Show the companion widget on WordPress pages
                </label>
                <p class="description">When enabled, a floating chat widget appears on all non-chatbot pages of your site.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_routing_mode">Companion Routing Mode</label></th>
            <td>
                <select name="flow_companion_routing_mode" id="flow_companion_routing_mode">
                    <option value="hub" <?php selected($flosc_routing_mode, 'hub'); ?>>Hub Mode</option>
                    <option value="domain_persistence" <?php selected($flosc_routing_mode, 'domain_persistence'); ?>>Domain Persistence Mode</option>
                </select>
                <p class="description">
                    <strong>Hub Mode (knowledge hubs):</strong> full-page chat may live on this flow’s chat domain
                    (Identity domain / slug); docking collapses to a <em>content</em> URL
                    (usually the Content tab lessons category archive) with companion still open.<br>
                    <strong>Domain Persistence:</strong> expand/collapse stays on the current domain.
                </p>
            </td>
        </tr>

        <tr class="flosc-hub-routing-row">
            <th scope="row"><label for="flow_companion_hub_fullscreen_url">Hub Full-Screen URL</label></th>
            <td>
                <input type="url" name="flow_companion_hub_fullscreen_url" id="flow_companion_hub_fullscreen_url" class="large-text" value="<?php echo esc_attr($flosc_hub_fullscreen_url); ?>" placeholder="<?php echo esc_attr($flosc_default_fullscreen); ?>">
                <p class="description">
                    Full-page FLOSC chat for this flow (expand destination).
                    Default from Identity: <code><?php echo esc_html($flosc_default_fullscreen); ?></code>
                    (<code>companion_hub_fullscreen_url</code>).
                </p>
            </td>
        </tr>

        <tr class="flosc-hub-routing-row">
            <th scope="row"><label for="flow_companion_hub_companion_url">Hub Companion URL (knowledge hub)</label></th>
            <td>
                <input type="url" name="flow_companion_hub_companion_url" id="flow_companion_hub_companion_url" class="large-text" value="<?php echo esc_attr($flosc_hub_companion_url); ?>" placeholder="<?php echo esc_attr($flosc_default_companion_hub); ?>">
                <p class="description">
                    <strong>Dock / collapse destination</strong> from full-page chat.
                    Default from Content lessons category: <code><?php echo esc_html($flosc_default_companion_hub); ?></code>
                    (<code>companion_hub_companion_url</code>). Include that category under Target Parameters.
                </p>
            </td>
        </tr>

        <tr class="flosc-hub-routing-row">
            <th scope="row"><label for="flow_companion_flow_slug">Companion chat flow slug</label></th>
            <td>
                <input type="text" name="flow_companion_flow_slug" id="flow_companion_flow_slug" class="regular-text" value="<?php echo esc_attr($flosc_companion_flow_slug); ?>" placeholder="<?php echo esc_attr((string) ($flosc_hub_defaults['flow_slug'] ?? '')); ?>">
                <p class="description">
                    Flow slug used when deriving the default companion chat URL (this flow’s slug if empty).
                    Setting: <code>companion_flow_slug</code>.
                </p>
            </td>
        </tr>

        <tr class="flosc-hub-routing-row">
            <th scope="row"><label for="flow_companion_chat_app_url">Companion chat URL (iframe)</label></th>
            <td>
                <input type="url" name="flow_companion_chat_app_url" id="flow_companion_chat_app_url" class="large-text" value="<?php echo esc_attr($flosc_chat_app_url); ?>" placeholder="<?php echo esc_attr($flosc_default_chat_app); ?>">
                <p class="description">
                    FLOSC app URL loaded <strong>inside</strong> the companion iframe (not the knowledge-hub page).
                    Default is this WordPress site + companion flow slug:
                    <code><?php echo esc_html($flosc_default_chat_app); ?></code>
                    (<code>companion_chat_app_url</code>).
                    Use the same site origin as your knowledge hub so logged-in guests/members keep their WP session
                    (and server chat history). Full-screen expand still uses Hub Full-Screen URL above.
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">Target Parameters</th>
            <td>
                <p class="description">Select exactly where companion mode runs. These parameters write targeting rules automatically.</p>
                <fieldset class="flosc-companion-target-mode" id="flosc-companion-target-mode">
                    <label>
                        <input type="radio" name="flow_companion_target_mode" value="sitewide" <?php checked($flosc_target_mode, 'sitewide'); ?>>
                        Sitewide (recommended): show on all pages, then use Exclude rules where needed.
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="flow_companion_target_mode" value="selected" <?php checked($flosc_target_mode, 'selected'); ?>>
                        Selected pages only: define explicit Include rules below.
                    </label>
                </fieldset>
                <div class="flosc-companion-target-advanced-toggle">
                    <label>
                        <input type="checkbox" id="flow_companion_targeting_customize" <?php checked($flosc_target_has_any_rules); ?>>
                        Customize targeting (advanced)
                    </label>
                </div>
                <p id="flosc-companion-targets-disabled-note" class="flosc-companion-targets-disabled-note">Companion will run everywhere except chatbot page while advanced targeting is off.</p>
                <div id="flosc-companion-target-groups">
                <div class="flosc-companion-target-grid flosc-companion-target-include">
                    <div class="flosc-companion-target-column">
                        <strong>Include: Pages</strong>
                        <select name="flow_companion_include_pages[]" id="flow_companion_include_pages" multiple size="6" class="widefat">
                            <?php foreach ((array) $flosc_target_pages as $flosc_target_page): ?>
                                <?php $flosc_target_page_id = (int) ($flosc_target_page->ID ?? 0); ?>
                                <option value="<?php echo esc_attr($flosc_target_page_id); ?>" <?php selected(in_array($flosc_target_page_id, $flosc_target_include_pages, true)); ?>>
                                    <?php echo esc_html(($flosc_target_page->post_title ?: '(untitled)') . ' (#' . $flosc_target_page_id . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flosc-companion-target-column">
                        <strong>Include: Posts</strong>
                        <select name="flow_companion_include_posts[]" id="flow_companion_include_posts" multiple size="6" class="widefat">
                            <?php foreach ($flosc_target_posts_map as $flosc_target_post_id => $flosc_target_post_title): ?>
                                <option value="<?php echo esc_attr((int) $flosc_target_post_id); ?>" <?php selected(in_array((int) $flosc_target_post_id, $flosc_target_include_posts, true)); ?>>
                                    <?php echo esc_html(($flosc_target_post_title ?: '(untitled)') . ' (#' . (int) $flosc_target_post_id . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flosc-companion-target-grid flosc-companion-target-include">
                    <div class="flosc-companion-target-column">
                        <strong>Include: Categories</strong>
                        <select name="flow_companion_include_categories[]" id="flow_companion_include_categories" multiple size="6" class="widefat">
                            <?php if (!is_wp_error($flosc_target_categories)): ?>
                                <?php foreach ((array) $flosc_target_categories as $flosc_target_category): ?>
                                    <?php $flosc_target_category_id = (int) ($flosc_target_category->term_id ?? 0); ?>
                                    <option value="<?php echo esc_attr($flosc_target_category_id); ?>" <?php selected(in_array($flosc_target_category_id, $flosc_target_include_categories, true)); ?>>
                                        <?php echo esc_html(($flosc_target_category->name ?? '(unnamed)') . ' (#' . $flosc_target_category_id . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="flosc-companion-target-column">
                        <strong>Include: Tags</strong>
                        <select name="flow_companion_include_tags[]" id="flow_companion_include_tags" multiple size="6" class="widefat">
                            <?php if (!is_wp_error($flosc_target_tags)): ?>
                                <?php foreach ((array) $flosc_target_tags as $flosc_target_tag): ?>
                                    <?php $flosc_target_tag_id = (int) ($flosc_target_tag->term_id ?? 0); ?>
                                    <option value="<?php echo esc_attr($flosc_target_tag_id); ?>" <?php selected(in_array($flosc_target_tag_id, $flosc_target_include_tags, true)); ?>>
                                        <?php echo esc_html(($flosc_target_tag->name ?? '(unnamed)') . ' (#' . $flosc_target_tag_id . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="flosc-companion-target-grid flosc-companion-target-grid-spaced flosc-companion-target-exclude">
                    <div class="flosc-companion-target-column">
                        <strong>Exclude: Pages</strong>
                        <select name="flow_companion_exclude_pages[]" id="flow_companion_exclude_pages" multiple size="6" class="widefat">
                            <?php foreach ((array) $flosc_target_pages as $flosc_target_page): ?>
                                <?php $flosc_target_page_id = (int) ($flosc_target_page->ID ?? 0); ?>
                                <option value="<?php echo esc_attr($flosc_target_page_id); ?>" <?php selected(in_array($flosc_target_page_id, $flosc_target_exclude_pages, true)); ?>>
                                    <?php echo esc_html(($flosc_target_page->post_title ?: '(untitled)') . ' (#' . $flosc_target_page_id . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flosc-companion-target-column">
                        <strong>Exclude: Posts</strong>
                        <select name="flow_companion_exclude_posts[]" id="flow_companion_exclude_posts" multiple size="6" class="widefat">
                            <?php foreach ($flosc_target_posts_map as $flosc_target_post_id => $flosc_target_post_title): ?>
                                <option value="<?php echo esc_attr((int) $flosc_target_post_id); ?>" <?php selected(in_array((int) $flosc_target_post_id, $flosc_target_exclude_posts, true)); ?>>
                                    <?php echo esc_html(($flosc_target_post_title ?: '(untitled)') . ' (#' . (int) $flosc_target_post_id . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flosc-companion-target-grid flosc-companion-target-exclude">
                    <div class="flosc-companion-target-column">
                        <strong>Exclude: Categories</strong>
                        <select name="flow_companion_exclude_categories[]" id="flow_companion_exclude_categories" multiple size="6" class="widefat">
                            <?php if (!is_wp_error($flosc_target_categories)): ?>
                                <?php foreach ((array) $flosc_target_categories as $flosc_target_category): ?>
                                    <?php $flosc_target_category_id = (int) ($flosc_target_category->term_id ?? 0); ?>
                                    <option value="<?php echo esc_attr($flosc_target_category_id); ?>" <?php selected(in_array($flosc_target_category_id, $flosc_target_exclude_categories, true)); ?>>
                                        <?php echo esc_html(($flosc_target_category->name ?? '(unnamed)') . ' (#' . $flosc_target_category_id . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="flosc-companion-target-column">
                        <strong>Exclude: Tags</strong>
                        <select name="flow_companion_exclude_tags[]" id="flow_companion_exclude_tags" multiple size="6" class="widefat">
                            <?php if (!is_wp_error($flosc_target_tags)): ?>
                                <?php foreach ((array) $flosc_target_tags as $flosc_target_tag): ?>
                                    <?php $flosc_target_tag_id = (int) ($flosc_target_tag->term_id ?? 0); ?>
                                    <option value="<?php echo esc_attr($flosc_target_tag_id); ?>" <?php selected(in_array($flosc_target_tag_id, $flosc_target_exclude_tags, true)); ?>>
                                        <?php echo esc_html(($flosc_target_tag->name ?? '(unnamed)') . ' (#' . $flosc_target_tag_id . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                </div>

                <p class="description">Hold Cmd/Ctrl to multi-select. Include rules define where widget may run; exclude rules always override include rules.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_position">Widget Position</label></th>
            <td>
                <select name="flow_companion_position" id="flow_companion_position">
                    <?php foreach ($flosc_companion_positions as $flosc_position_option): ?>
                        <?php $flosc_position_option = sanitize_text_field((string) $flosc_position_option); ?>
                        <option value="<?php echo esc_attr($flosc_position_option); ?>" <?php selected($flosc_position, $flosc_position_option); ?>><?php echo esc_html($flosc_companion_position_labels[$flosc_position_option] ?? ucwords(str_replace('-', ' ', $flosc_position_option))); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Where the floating widget appears on screen.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_panel_width">Panel Width</label></th>
            <td>
                <input type="number" name="flow_companion_panel_width" id="flow_companion_panel_width" min="<?php echo esc_attr($flosc_companion_numeric_limits['panel_width_min']); ?>" max="<?php echo esc_attr($flosc_companion_numeric_limits['panel_width_max']); ?>" step="1" value="<?php echo esc_attr($flosc_panel_width); ?>" class="small-text">
                <span>px</span>
                <p class="description">Desktop panel width. Recommended range: 320 to 520.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_panel_height">Panel Height</label></th>
            <td>
                <input type="number" name="flow_companion_panel_height" id="flow_companion_panel_height" min="<?php echo esc_attr($flosc_companion_numeric_limits['panel_height_min']); ?>" max="<?php echo esc_attr($flosc_companion_numeric_limits['panel_height_max']); ?>" step="1" value="<?php echo esc_attr($flosc_panel_height); ?>" class="small-text">
                <span>px</span>
                <p class="description">Desktop panel height. Recommended range: 480 to 720.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_launcher_size">Launcher Size</label></th>
            <td>
                <input type="number" name="flow_companion_launcher_size" id="flow_companion_launcher_size" min="<?php echo esc_attr($flosc_companion_numeric_limits['launcher_size_min']); ?>" max="<?php echo esc_attr($flosc_companion_numeric_limits['launcher_size_max']); ?>" step="1" value="<?php echo esc_attr($flosc_launcher_size); ?>" class="small-text">
                <span>px</span>
                <p class="description">Floating launcher button diameter.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_launcher_icon">Launcher Icon</label></th>
            <td>
                <select name="flow_companion_launcher_icon" id="flow_companion_launcher_icon">
                    <?php foreach ($flosc_companion_launcher_icons as $flosc_icon_key => $flosc_icon_label): ?>
                        <?php $flosc_icon_key = sanitize_key((string) $flosc_icon_key); ?>
                        <option value="<?php echo esc_attr($flosc_icon_key); ?>" <?php selected($flosc_launcher_icon, $flosc_icon_key); ?>><?php echo esc_html((string) $flosc_icon_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Floating button icon. <strong>Product Logo</strong> uses the flow Chat Logo (Identity). Glyph options are generic SVG marks.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_header_icon_url">Header Icon URL</label></th>
            <td>
                <?php
                // Resolve from THIS flow’s Identity (not global runtime identity — admin edit context).
                $flosc_header_icon_url = esc_url( (string) ( $flosc_flow_settings['companion_header_icon_url'] ?? '' ) );
                $flosc_identity_chatlogo = '';
                if ( function_exists( 'flosc_resolve_chatlogo_url' ) ) {
                    // No plugin default — only this flow’s Chat Logo for preview/copy.
                    $flosc_identity_chatlogo = flosc_resolve_chatlogo_url( is_array( $flosc_flow_settings ) ? $flosc_flow_settings : [], false );
                } else {
                    $flosc_id_arr = is_array( $flosc_flow_settings['identity'] ?? null ) ? $flosc_flow_settings['identity'] : [];
                    $flosc_identity_chatlogo = esc_url( (string) ( $flosc_id_arr['chatlogo_url'] ?? $flosc_flow_settings['chatlogo_url'] ?? '' ) );
                }
                $flosc_preview_icon = $flosc_header_icon_url !== '' ? $flosc_header_icon_url : $flosc_identity_chatlogo;
                ?>
                <input type="url" name="flow_companion_header_icon_url" id="flow_companion_header_icon_url" class="large-text" value="<?php echo esc_attr( $flosc_header_icon_url ); ?>" placeholder="<?php echo esc_attr( $flosc_identity_chatlogo !== '' ? $flosc_identity_chatlogo : '' ); ?>">
                <?php
                $flosc_identity_tab_url = add_query_arg(
                    [
                        'page' => 'flosc-settings',
                        'ivr'  => $GLOBALS['flosc_current_ivr'] ?? '',
                        'tab'  => 'identity',
                        'view' => 'single',
                    ],
                    admin_url( 'admin.php' )
                );
                ?>
                <p class="description">
                    Optional override for the companion header icon only.
                    <strong>Leave blank</strong> to use this flow’s <strong>Identity → Chat Logo</strong>
                    <?php if ( $flosc_identity_chatlogo !== '' ) : ?>
                        (<a href="<?php echo esc_url( $flosc_identity_tab_url ); ?>">Identity tab</a> — already set for this flow).
                    <?php else : ?>
                        (set a Chat Logo on the <a href="<?php echo esc_url( $flosc_identity_tab_url ); ?>">Identity tab</a> for this flow).
                    <?php endif; ?>
                </p>
                <?php if ( $flosc_preview_icon !== '' ) : ?>
                    <p class="description flosc-companion-desc-spaced">
                        <?php if ( $flosc_header_icon_url !== '' ) : ?>
                            <strong>Using override:</strong>
                        <?php else : ?>
                            <strong>Using Identity Chat Logo:</strong>
                        <?php endif; ?>
                    </p>
                    <img src="<?php echo esc_url( $flosc_preview_icon ); ?>" alt="" class="flosc-companion-logo-preview">
                <?php else : ?>
                    <p class="description flosc-companion-desc-warn">
                        No Chat Logo on this flow yet — companion will fall back to the generic FLOSC icon until Identity → Chat Logo is set.
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_greeting">Header Title</label></th>
            <td>
                <textarea name="flow_companion_greeting" id="flow_companion_greeting" rows="2" class="large-text"><?php echo esc_textarea($flosc_greeting); ?></textarea>
                <p class="description"><strong>Per-flow FLOSC parameter</strong> (<code>companion_greeting</code>). Short header title for this product only — not hard-coded in the plugin. Prefer one line. Empty → “{product name} Companion” from Identity. Username/tokens live under the message box.</p>
                <label><input type="checkbox" name="flow_companion_show_header_title" value="1" <?php checked($flosc_show_header_title); ?>> Show this title in the companion header</label>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_subtitle">Header Subtitle</label></th>
            <td>
                <input type="text" name="flow_companion_subtitle" id="flow_companion_subtitle" class="regular-text" value="<?php echo esc_attr($flosc_subtitle); ?>">
                <p class="description"><strong>Per-flow parameter</strong> (<code>companion_subtitle</code>). Optional line under the title. Leave blank if unused. Copy can be refined later; keep it product-specific in this field, not in FLOSC core.</p>
                <label><input type="checkbox" name="flow_companion_show_header_subtitle" value="1" <?php checked($flosc_show_header_subtitle); ?>> Show this subtitle in the companion header</label>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_contextual_prompt">Companion Contextual Prompt</label></th>
            <td>
                <input type="text" name="flow_companion_contextual_prompt" id="flow_companion_contextual_prompt" class="regular-text" value="<?php echo esc_attr($flosc_contextual_prompt); ?>">
                <p class="description"><strong>Per-flow parameter</strong> (<code>companion_contextual_prompt</code>). First context line when the companion is aware of the current page. Brand-neutral default; override per product here.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Profile row tier codes</th>
            <td>
                <p class="description flosc-description-margin-top-0">
                    Companion profile row format: <code>Name (code)</code> then token count.
                    FLOSC defaults are <strong>V</strong> / <strong>G</strong> / <strong>M</strong> — override per flow if needed.
                </p>
                <fieldset class="flosc-companion-tier-grid">
                    <span></span>
                    <strong class="flosc-companion-tier-col-head">Code</strong>
                    <strong class="flosc-companion-tier-col-head">A11y label</strong>

                    <label for="flow_companion_profile_tier_visitor">Visitor</label>
                    <input type="text" class="small-text" maxlength="3" name="flow_companion_profile_tier_visitor" id="flow_companion_profile_tier_visitor" value="<?php echo esc_attr( $flosc_tier_visitor ); ?>" pattern="[A-Za-z0-9]{1,3}">
                    <input type="text" class="regular-text" name="flow_companion_profile_tier_visitor_label" id="flow_companion_profile_tier_visitor_label" value="<?php echo esc_attr( $flosc_tier_visitor_label ); ?>">

                    <label for="flow_companion_profile_tier_guest">Guest</label>
                    <input type="text" class="small-text" maxlength="3" name="flow_companion_profile_tier_guest" id="flow_companion_profile_tier_guest" value="<?php echo esc_attr( $flosc_tier_guest ); ?>" pattern="[A-Za-z0-9]{1,3}">
                    <input type="text" class="regular-text" name="flow_companion_profile_tier_guest_label" id="flow_companion_profile_tier_guest_label" value="<?php echo esc_attr( $flosc_tier_guest_label ); ?>">

                    <label for="flow_companion_profile_tier_member">Member</label>
                    <input type="text" class="small-text" maxlength="3" name="flow_companion_profile_tier_member" id="flow_companion_profile_tier_member" value="<?php echo esc_attr( $flosc_tier_member ); ?>" pattern="[A-Za-z0-9]{1,3}">
                    <input type="text" class="regular-text" name="flow_companion_profile_tier_member_label" id="flow_companion_profile_tier_member_label" value="<?php echo esc_attr( $flosc_tier_member_label ); ?>">
                </fieldset>
                <p class="description">Keys: <code>companion_profile_tier_visitor</code>, <code>_guest</code>, <code>_member</code> (+ <code>_label</code> for each). Preview: <code>DisplayName (<?php echo esc_html( $flosc_tier_member ); ?>)</code></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_show_open_fullpage">Full Chat Page Button</label></th>
            <td>
                <label><input type="checkbox" name="flow_companion_show_open_fullpage" id="flow_companion_show_open_fullpage" value="1" <?php checked($flosc_show_open_fullpage); ?>> Show the "open full chat page" button in the companion header</label>
                <p class="description">Uncheck to keep the conversation in the docked companion only (no link out to the standalone chat page).</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_accent_color">Accent Color</label></th>
            <td>
                <input type="color" name="flow_companion_accent_color" id="flow_companion_accent_color" value="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>" class="flosc-companion-color-input">
                <input type="text" id="companion_accent_hex" value="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>" class="flosc-companion-color-hex" readonly>
                <button type="button" id="flosc-companion-color-reset" class="button flosc-companion-color-reset">Reset</button>
                <p class="description">The primary color for the widget button and highlights. Leave as default for the FLOSC indigo.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_show_for_visitors">Visitor Visibility</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_show_for_visitors" id="flow_companion_show_for_visitors" value="1" <?php checked($flosc_show_for_visitors, '1'); ?>>
                    Show companion to non-logged-in visitors
                </label>
                <p class="description">
                    Default is <strong>on</strong> (sales freeline: visitors see the bubble).
                    Uncheck only for logged-in / member-only companion surfaces.
                </p>
            </td>
        </tr>


        <tr>
            <th scope="row"><label for="flow_companion_mobile_behavior">Mobile Behavior</label></th>
            <td>
                <select name="flow_companion_mobile_behavior" id="flow_companion_mobile_behavior">
                    <?php foreach ($flosc_companion_mobile_behaviors as $flosc_mobile_option): ?>
                        <?php $flosc_mobile_option = sanitize_text_field((string) $flosc_mobile_option); ?>
                        <option value="<?php echo esc_attr($flosc_mobile_option); ?>" <?php selected($flosc_mobile_behavior, $flosc_mobile_option); ?>><?php echo esc_html($flosc_companion_mobile_labels[$flosc_mobile_option] ?? ucwords(str_replace('-', ' ', $flosc_mobile_option))); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Controls how the companion opens on small screens.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_pass_page_context">Page Context Handoff</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_pass_page_context" id="flow_companion_pass_page_context" value="1" <?php checked($flosc_pass_page_context, '1'); ?>>
                    Include current page context in companion iframe launch params
                </label>
                <p class="description">When enabled, companion receives the current page URL and metadata for context-aware responses.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_context_scope">Context Scope</label></th>
            <td>
                <select name="flow_companion_context_scope" id="flow_companion_context_scope">
                    <?php foreach ($flosc_companion_context_scopes as $flosc_context_option): ?>
                        <?php $flosc_context_option = sanitize_text_field((string) $flosc_context_option); ?>
                        <option value="<?php echo esc_attr($flosc_context_option); ?>" <?php selected($flosc_context_scope, $flosc_context_option); ?>><?php echo esc_html($flosc_companion_context_scope_labels[$flosc_context_option] ?? ucwords(str_replace('-', ' ', $flosc_context_option))); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Select how much page metadata is passed to the companion.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_page_intent_phrases">Page Intent Phrases</label></th>
            <td>
                <textarea name="flow_companion_page_intent_phrases" id="flow_companion_page_intent_phrases" rows="4" class="large-text" placeholder="one phrase per line"><?php echo esc_textarea($flosc_page_intent_phrases); ?></textarea>
                <p class="description">Extra phrases (one per line) that signal a visitor is asking about the current page, so the AI ingests that page's content. FLOSC's built-in phrases (e.g. "what is this", "tell me about this page") always apply — anything here is added on top.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_auto_open_enabled">Auto Open</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_auto_open_enabled" id="flow_companion_auto_open_enabled" value="1" <?php checked($flosc_auto_open_enabled, '1'); ?>>
                    Open companion automatically when the page loads
                </label>
                <p class="description">When enabled, companion launches automatically after the configured delay.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_auto_open_delay_ms">Auto Open Delay</label></th>
            <td>
                <input type="number" name="flow_companion_auto_open_delay_ms" id="flow_companion_auto_open_delay_ms" min="<?php echo esc_attr($flosc_companion_numeric_limits['auto_open_delay_min_ms']); ?>" max="<?php echo esc_attr($flosc_companion_numeric_limits['auto_open_delay_max_ms']); ?>" step="100" value="<?php echo esc_attr($flosc_auto_open_delay_ms); ?>" class="small-text">
                <span>ms</span>
                <p class="description">Delay before auto open triggers. Example: 1500 = 1.5 seconds.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_auto_open_once_per_session">Auto Open Frequency</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_auto_open_once_per_session" id="flow_companion_auto_open_once_per_session" value="1" <?php checked($flosc_auto_open_once, '1'); ?>>
                    Timed auto open only once per browser session
                </label>
                <p class="description">Prevents repeated timed auto-open behavior while browsing pages in the same session. Exit-intent and scroll triggers still follow their own rules.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_launch_on_exit_intent">Exit Intent Trigger</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_launch_on_exit_intent" id="flow_companion_launch_on_exit_intent" value="1" <?php checked($flosc_launch_on_exit, '1'); ?>>
                    Open companion when cursor exits near top of viewport (desktop)
                </label>
                <p class="description">Optional behavioral trigger for users about to leave.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_launch_on_scroll_threshold">Scroll Trigger</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_launch_on_scroll_threshold" id="flow_companion_launch_on_scroll_threshold" value="1" <?php checked($flosc_launch_on_scroll, '1'); ?>>
                    Open companion when scroll progress reaches threshold
                </label>
                <div class="flosc-companion-scroll-threshold-row">
                    <input type="number" name="flow_companion_launch_on_scroll_percent" id="flow_companion_launch_on_scroll_percent" min="<?php echo esc_attr($flosc_companion_numeric_limits['scroll_percent_min']); ?>" max="<?php echo esc_attr($flosc_companion_numeric_limits['scroll_percent_max']); ?>" step="1" value="<?php echo esc_attr($flosc_launch_scroll_pct); ?>" class="small-text">
                    <span>%</span>
                </div>
                <p class="description">Set to 0 to trigger immediately after scrolling starts.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_trigger_desktop_only">Trigger Device Scope</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_trigger_desktop_only" id="flow_companion_trigger_desktop_only" value="1" <?php checked($flosc_trigger_desktop_only, '1'); ?>>
                    Run behavioral triggers on desktop only
                </label>
                <p class="description">Auto/exit/scroll triggers are ignored on smaller screens when enabled.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_trigger_min_page_time_ms">Minimum Page Time Before Trigger</label></th>
            <td>
                <input type="number" name="flow_companion_trigger_min_page_time_ms" id="flow_companion_trigger_min_page_time_ms" min="<?php echo esc_attr($flosc_companion_numeric_limits['trigger_min_page_time_min_ms']); ?>" max="<?php echo esc_attr($flosc_companion_numeric_limits['trigger_min_page_time_max_ms']); ?>" step="100" value="<?php echo esc_attr($flosc_trigger_min_time_ms); ?>" class="small-text">
                <span>ms</span>
                <p class="description">Behavioral triggers will not open companion before this time on page.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_trigger_suppress_on_auth_checkout">Suppress on Auth/Checkout</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_trigger_suppress_on_auth_checkout" id="flow_companion_trigger_suppress_on_auth_checkout" value="1" <?php checked($flosc_trigger_suppress_ac, '1'); ?>>
                    Disable behavioral triggers on login/account/checkout paths
                </label>
                <p class="description">Companion still appears manually; only auto/exit/scroll trigger launches are suppressed.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_trigger_suppress_path_patterns">Trigger Suppress Paths</label></th>
            <td>
                <textarea name="flow_companion_trigger_suppress_path_patterns" id="flow_companion_trigger_suppress_path_patterns" rows="3" class="large-text code"><?php echo esc_textarea($flosc_trigger_suppress_paths); ?></textarea>
                <p class="description">Optional path prefixes (one per line), e.g. <code>/checkout</code> or <code>/my-account</code>. Trigger launch is suppressed on matches.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_motion_mode">Motion Mode</label></th>
            <td>
                <select name="flow_companion_motion_mode" id="flow_companion_motion_mode">
                    <?php foreach ($flosc_companion_motion_modes as $flosc_motion_option): ?>
                        <?php $flosc_motion_option = sanitize_text_field((string) $flosc_motion_option); ?>
                        <option value="<?php echo esc_attr($flosc_motion_option); ?>" <?php selected($flosc_motion_mode, $flosc_motion_option); ?>><?php echo esc_html($flosc_companion_motion_mode_labels[$flosc_motion_option] ?? ucwords(str_replace('-', ' ', $flosc_motion_option))); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Controls companion animation intensity.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_focus_on_open">Focus on Open</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_focus_on_open" id="flow_companion_focus_on_open" value="1" <?php checked($flosc_focus_on_open, '1'); ?>>
                    Move keyboard focus into companion when it opens
                </label>
                <p class="description">Improves keyboard navigation for accessibility workflows.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_allow_escape_close">Escape-to-Close</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_allow_escape_close" id="flow_companion_allow_escape_close" value="1" <?php checked($flosc_allow_escape_close, '1'); ?>>
                    Allow Escape key to close companion
                </label>
                <p class="description">Recommended for keyboard accessibility and predictable dismissal behavior.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_enable_keyboard_shortcut">Keyboard Shortcut</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_enable_keyboard_shortcut" id="flow_companion_enable_keyboard_shortcut" value="1" <?php checked($flosc_keyboard_shortcut, '1'); ?>>
                    Enable keyboard shortcut to toggle companion (Alt+Shift+Key)
                </label>
                <div class="flosc-companion-scroll-threshold-row">
                    <input type="text" name="flow_companion_keyboard_shortcut_key" id="flow_companion_keyboard_shortcut_key" class="small-text" maxlength="1" value="<?php echo esc_attr($flosc_shortcut_key); ?>">
                    <span>default: K</span>
                </div>
                <p class="description">Shortcut is ignored while typing in input, textarea, select, or contenteditable fields.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_launcher_aria_label">Launcher ARIA Label</label></th>
            <td>
                <input type="text" name="flow_companion_launcher_aria_label" id="flow_companion_launcher_aria_label" class="regular-text" value="<?php echo esc_attr($flosc_launcher_aria_label); ?>">
                <p class="description">Accessible label announced for the launcher button.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_close_aria_label">Close ARIA Label</label></th>
            <td>
                <input type="text" name="flow_companion_close_aria_label" id="flow_companion_close_aria_label" class="regular-text" value="<?php echo esc_attr($flosc_close_aria_label); ?>">
                <p class="description">Accessible label announced for the close button.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_remember_open_state">Remember Open State</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_remember_open_state" id="flow_companion_remember_open_state" value="1" <?php checked($flosc_remember_open_state, '1'); ?>>
                    Re-open companion automatically if user left it open on previous page
                </label>
                <p class="description">State persistence applies only to the current flow context key.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_state_storage">State Storage</label></th>
            <td>
                <select name="flow_companion_state_storage" id="flow_companion_state_storage">
                    <?php foreach ($flosc_companion_state_storages as $flosc_storage_option): ?>
                        <?php $flosc_storage_option = sanitize_text_field((string) $flosc_storage_option); ?>
                        <option value="<?php echo esc_attr($flosc_storage_option); ?>" <?php selected($flosc_state_storage, $flosc_storage_option); ?>><?php echo esc_html($flosc_companion_state_storage_labels[$flosc_storage_option] ?? ucfirst($flosc_storage_option)); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Use session storage (tab lifetime) or local storage (persistent across sessions) for remembered open state and trigger cooldown metadata.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_trigger_cooldown_ms">Trigger Cooldown</label></th>
            <td>
                <input type="number" name="flow_companion_trigger_cooldown_ms" id="flow_companion_trigger_cooldown_ms" min="<?php echo esc_attr($flosc_companion_numeric_limits['trigger_cooldown_min_ms']); ?>" max="<?php echo esc_attr($flosc_companion_numeric_limits['trigger_cooldown_max_ms']); ?>" step="1000" value="<?php echo esc_attr($flosc_trigger_cooldown_ms); ?>" class="small-text">
                <span>ms</span>
                <p class="description">Minimum time between behavior-triggered opens. Example: 600000 = 10 minutes.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Validation Notes</th>
            <td>
                <div id="flosc-companion-validation-hints" class="notice notice-warning inline flosc-companion-validation-hints">
                    <p id="flosc-companion-validation-hints-text" class="flosc-companion-validation-hints-text"></p>
                </div>
                <p class="description">Live checks help prevent aggressive launch behavior and unexpected persistence scope.</p>
            </td>
        </tr>

        <tr id="flosc-companion-target-include-custom-row">
            <th scope="row"><label for="flow_companion_target_include_custom">Additional Include Rules</label></th>
            <td>
                <textarea name="flow_companion_target_include_custom" id="flow_companion_target_include_custom" rows="4" class="large-text code"><?php echo esc_textarea(implode("\n", $flosc_target_include_custom)); ?></textarea>
                <p class="description">Optional advanced rules, one per line. Examples: <code>path:/lessons/</code>, <code>path:/pricing/</code>.</p>
            </td>
        </tr>

        <tr id="flosc-companion-target-exclude-custom-row">
            <th scope="row"><label for="flow_companion_target_exclude_custom">Additional Exclude Rules</label></th>
            <td>
                <textarea name="flow_companion_target_exclude_custom" id="flow_companion_target_exclude_custom" rows="4" class="large-text code"><?php echo esc_textarea(implode("\n", $flosc_target_exclude_custom)); ?></textarea>
                <p class="description">Optional advanced rules, one per line. Exclude rules always win over include rules.</p>
            </td>
        </tr>
    </table>
</div>

<hr class="flosc-companion-divider">
<h2>Developer Extension Hooks</h2>
<p>Companion behavior is fully parameterized. Use these hooks in custom code to extend companion behavior without editing core plugin files.</p>
<table class="widefat striped">
    <thead>
        <tr>
            <th scope="col">Hook</th>
            <th scope="col">Type</th>
            <th scope="col">Purpose</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($flosc_companion_extension_hooks as $flosc_hook_def): ?>
            <tr>
                <td><code><?php echo esc_html($flosc_hook_def['hook']); ?></code></td>
                <td><?php echo esc_html(strtoupper($flosc_hook_def['type'])); ?></td>
                <td><?php echo esc_html($flosc_hook_def['purpose']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>Extension Snippets</h3>
<p>Copy these into a small must-use plugin or theme customization file to extend Companion behavior safely.</p>

<p><strong>1) Override numeric bounds</strong></p>
<pre><code><?php echo esc_html($flosc_companion_snippet_numeric_limits); ?></code></pre>

<p><strong>2) Adjust frontend init payload</strong></p>
<pre><code><?php echo esc_html($flosc_companion_snippet_frontend_config); ?></code></pre>

<!-- Preview Panel -->
<hr class="flosc-companion-divider">
<h2>Preview</h2>
<div class="flosc-companion-preview">
    <p class="flosc-companion-preview-copy">
        📱 <strong>Current behavior:</strong>
        <?php echo esc_html($flosc_companion_effective_summary); ?>
    </p>
    <p class="flosc-companion-preview-copy flosc-companion-preview-copy-spaced">
        The floating <strong class="flosc-companion-preview-dot">●</strong> launcher is positioned in the
        <strong><?php echo esc_html( $flosc_position === 'bottom-left' ? 'bottom-left' : 'bottom-right' ); ?></strong>
        corner when companion is active.
    </p>
    <p class="flosc-companion-preview-copy flosc-companion-preview-copy-spaced">
        When a member clicks it, the companion opens with: "<em><?php echo esc_html(wp_trim_words($flosc_greeting, 15)); ?></em>"
    </p>
    <p class="flosc-companion-preview-copy flosc-companion-preview-copy-spaced">
        If the member is reading a lesson, the companion will know which lesson they're on and offer contextual help.
    </p>
    <div class="flosc-companion-preview-bubble-wrap <?php echo esc_attr( $flosc_position === 'bottom-left' ? 'flosc-companion-preview-bubble-wrap--left' : 'flosc-companion-preview-bubble-wrap--right' ); ?>">
        <div class="flosc-companion-preview-bubble">
            💬
        </div>
    </div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    var $modeRadios = $('input[name="flow_companion_content_display_mode"]');

    // Toggle widget settings visibility based on display mode
    function toggleCompanionSettings() {
        var mode = $('input[name="flow_companion_content_display_mode"]:checked').val();
        var $settings = $('#companion-widget-settings');
        
        if (mode === 'in_chat') {
            $settings.addClass('flosc-companion-disabled');
        } else {
            $settings.removeClass('flosc-companion-disabled');
        }
        
        // Highlight selected radio card
        $('.flosc-companion-mode-card').removeClass('is-active');
        $('input[name="flow_companion_content_display_mode"]:checked').closest('.flosc-companion-mode-card').addClass('is-active');

        // Companion and Both modes require the widget shell; auto-enable to avoid hidden misconfiguration.
        if ((mode === 'companion' || mode === 'both') && !$('#flow_companion_enabled').is(':checked')) {
            $('#flow_companion_enabled').prop('checked', true);
        }
    }
    
    $('input[name="flow_companion_content_display_mode"]').on('change', toggleCompanionSettings);
    
    // Sync color picker with hex display + preview via CSS custom property (no per-element .css())
    $('#flow_companion_accent_color').on('input', function() {
        var color = $(this).val();
        var preview = document.querySelector('.flosc-companion-preview');
        $('#companion_accent_hex').val(color);
        if (preview) {
            preview.style.setProperty('--flosc-companion-accent', color);
        }
    });

    $('#flosc-companion-color-reset').on('click', function() {
        $('#flow_companion_accent_color').val('#6366f1').trigger('input');
    });

    function updateCompanionValidationHints() {
        var hints = [];
        var autoOpenEnabled = $('#flow_companion_auto_open_enabled').is(':checked');
        var autoOpenDelay = parseInt($('#flow_companion_auto_open_delay_ms').val(), 10) || 0;
        var autoOpenOnce = $('#flow_companion_auto_open_once_per_session').is(':checked');
        var launchOnExit = $('#flow_companion_launch_on_exit_intent').is(':checked');
        var launchOnScroll = $('#flow_companion_launch_on_scroll_threshold').is(':checked');
        var launchScrollPercent = parseInt($('#flow_companion_launch_on_scroll_percent').val(), 10) || 0;
        var cooldownMs = parseInt($('#flow_companion_trigger_cooldown_ms').val(), 10) || 0;
        var rememberOpen = $('#flow_companion_remember_open_state').is(':checked');
        var stateStorage = String($('#flow_companion_state_storage').val() || 'session');

        if (autoOpenEnabled && autoOpenDelay < 500) {
            hints.push('Auto Open Delay is below 500ms. Consider 1000ms+ to reduce intrusive launches.');
        }

        if (!autoOpenEnabled && autoOpenOnce) {
            hints.push('Timed Auto Open Once Per Session is enabled while Auto Open is off. This frequency setting has no effect until Auto Open is enabled.');
        }

        if ((launchOnExit || launchOnScroll) && cooldownMs > 0 && cooldownMs < 5000) {
            hints.push('Trigger Cooldown is below 5000ms while behavior triggers are active. Consider a longer cooldown to avoid repeated opens.');
        }

        if (launchOnScroll && launchScrollPercent === 0) {
            hints.push('Scroll Trigger threshold is set to 0%. Companion can open as soon as scrolling starts.');
        }

        if (!rememberOpen && stateStorage === 'local' && cooldownMs > 0) {
            hints.push('State Storage is set to Local while Remember Open State is off. Trigger cooldown will still persist across browser restarts.');
        } else if (!rememberOpen && stateStorage === 'local') {
            hints.push('State Storage is set to Local while Remember Open State is off. Storage selection mainly affects trigger cooldown behavior in this combination.');
        }

        if (rememberOpen && stateStorage === 'local') {
            hints.push('Remember Open State + Local storage persists companion state across browser restarts for this flow.');
        }

        if (hints.length > 0) {
            $('#flosc-companion-validation-hints-text').html(hints.join('<br>'));
            $('#flosc-companion-validation-hints').show();
        } else {
            $('#flosc-companion-validation-hints').hide();
            $('#flosc-companion-validation-hints-text').empty();
        }
    }

    $('#flow_companion_auto_open_enabled, #flow_companion_auto_open_delay_ms, #flow_companion_auto_open_once_per_session, #flow_companion_launch_on_exit_intent, #flow_companion_launch_on_scroll_threshold, #flow_companion_launch_on_scroll_percent, #flow_companion_trigger_cooldown_ms, #flow_companion_remember_open_state, #flow_companion_state_storage')
        .on('change input', updateCompanionValidationHints);

    function updateCompanionTargetMode() {
        var mode = $('input[name="flow_companion_target_mode"]:checked').val() || 'sitewide';
        var advancedEnabled = $('#flow_companion_targeting_customize').is(':checked');
        var disableIncludes = (mode === 'sitewide') || !advancedEnabled;
        var disableExcludes = !advancedEnabled;

        $('#flosc-companion-target-groups').toggleClass('flosc-companion-targets-disabled', !advancedEnabled);
        $('#flosc-companion-targets-disabled-note').toggle(!advancedEnabled);
        $('#flosc-companion-target-include-custom-row').toggle(advancedEnabled && mode !== 'sitewide');
        $('#flosc-companion-target-exclude-custom-row').toggle(advancedEnabled);

        $('#flow_companion_include_pages, #flow_companion_include_posts, #flow_companion_include_categories, #flow_companion_include_tags, #flow_companion_target_include_custom')
            .prop('disabled', disableIncludes);
        $('#flow_companion_exclude_pages, #flow_companion_exclude_posts, #flow_companion_exclude_categories, #flow_companion_exclude_tags, #flow_companion_target_exclude_custom')
            .prop('disabled', disableExcludes);
    }

    function updateCompanionRoutingMode() {
        var mode = String($('#flow_companion_routing_mode').val() || 'hub');
        $('.flosc-hub-routing-row').toggle(mode === 'hub');
    }

    $('input[name="flow_companion_target_mode"]').on('change', updateCompanionTargetMode);
    $('#flow_companion_targeting_customize').on('change', updateCompanionTargetMode);
    $('#flow_companion_routing_mode').on('change', updateCompanionRoutingMode);

    $('.flosc-settings-form').on('submit', function() {
        var mode = $('input[name="flow_companion_target_mode"]:checked').val() || 'sitewide';
        var advancedEnabled = $('#flow_companion_targeting_customize').is(':checked');

        if (!advancedEnabled) {
            $('#flow_companion_include_pages option, #flow_companion_include_posts option, #flow_companion_include_categories option, #flow_companion_include_tags option').prop('selected', false);
            $('#flow_companion_exclude_pages option, #flow_companion_exclude_posts option, #flow_companion_exclude_categories option, #flow_companion_exclude_tags option').prop('selected', false);
            $('#flow_companion_target_include_custom').val('');
            $('#flow_companion_target_exclude_custom').val('');
            return;
        }

        if (mode !== 'sitewide') {
            return;
        }

        $('#flow_companion_include_pages option, #flow_companion_include_posts option, #flow_companion_include_categories option, #flow_companion_include_tags option')
            .prop('selected', false);
        $('#flow_companion_target_include_custom').val('');
    });

    $('#flow_companion_accent_color').trigger('input');
    $modeRadios.prop('disabled', false);
    updateCompanionValidationHints();
    updateCompanionTargetMode();
    updateCompanionRoutingMode();
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
