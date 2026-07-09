<?php
/**
 * FLOSC Companion Configuration Tab
 * 
 * Configures the floating Companion chat widget that appears on WordPress
 * pages alongside FLOSC content and interactions. This is "Companion Mode" —
 * the chatbot as a contextual companion while members browse flow resources as
 * normal WordPress content.
 * 
 * Content Display Modes:
 * - In-Chat:    FLOSC content and interactions run inside the chatbot app (default)
 * - Companion:  Floating widget on WP pages while content is browsed on-site
 * - Both:       Both modes available — in-chat plus companion on WP pages
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
$flosc_enabled              = $flosc_flow_settings['companion_enabled'] ?? '';
$flosc_position             = $flosc_flow_settings['companion_position'] ?? 'bottom-right';
$flosc_greeting             = $flosc_flow_settings['companion_greeting'] ?? 'Hi! I\'m your learning companion. Need help with this lesson?';
$flosc_subtitle             = $flosc_flow_settings['companion_subtitle'] ?? 'We reply instantly';
$flosc_accent_color         = $flosc_flow_settings['companion_accent_color'] ?? '';
$flosc_show_for_visitors    = $flosc_flow_settings['companion_show_for_visitors'] ?? '';
$flosc_target_include       = $flosc_flow_settings['companion_target_include'] ?? '';
$flosc_target_exclude       = $flosc_flow_settings['companion_target_exclude'] ?? '';
$flosc_allow_fullscreen     = $flosc_flow_settings['companion_allow_fullscreen'] ?? '';
$flosc_default_fullscreen   = $flosc_flow_settings['companion_default_fullscreen'] ?? '';
$flosc_panel_width          = intval($flosc_flow_settings['companion_panel_width'] ?? 380);
$flosc_panel_height         = intval($flosc_flow_settings['companion_panel_height'] ?? 560);
$flosc_mobile_behavior      = $flosc_flow_settings['companion_mobile_behavior'] ?? 'fullscreen';
$flosc_pass_page_context    = $flosc_flow_settings['companion_pass_page_context'] ?? '1';
$flosc_context_scope        = $flosc_flow_settings['companion_context_scope'] ?? 'basic';
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

        list($type, $value) = array_map('trim', explode(':', $raw_rule, 2));
        $type = strtolower($type);
        $value = (string) $value;
        if ($value === '') {
            continue;
        }

        if ($type === 'path') {
            $value = '/' . ltrim($value, '/');
        }

        $rules[] = ['type' => $type, 'value' => $value];
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
$flosc_companion_quick_enabled = (
    $flosc_enabled === '1'
    && ($flosc_content_display_mode === 'companion' || $flosc_content_display_mode === 'both')
);

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
    'chat' => 'Chat Bubble',
    'help' => 'Help Circle',
    'spark' => 'Spark',
]);
if (!is_array($flosc_companion_launcher_icons) || empty($flosc_companion_launcher_icons)) {
    $flosc_companion_launcher_icons = ['chat' => 'Chat Bubble'];
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
    $config['launcherAriaLabel'] = 'Open learning companion';
    return $config;
}, 10, 2);
FLOSC_COMPANION_SNIPPET_FRONTEND_CONFIG;
?>

<h2>Content Display Mode</h2>
<p>Choose where the FLOSC chat experience runs. Full-page mode runs at your flow URL. Companion mode runs as a floating widget on selected WordPress targets (posts, pages, categories, or tags).</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_companion_quick_toggle">Companion Quick Toggle</label></th>
        <td>
            <label>
                <input type="checkbox" id="flosc_companion_quick_toggle" name="flow_companion_sitewide_master" value="1" <?php checked($flosc_companion_quick_enabled); ?>>
                Sitewide ON/OFF for everyone
            </label>
            <p class="description">ON enables companion for visitors, guests, and members sitewide (except the full-page chatbot URL), with fullscreen behavior on open. Exclusions can still be added in Target Parameters.</p>
        </td>
    </tr>
</table>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_companion_content_display_mode">Display Mode</label></th>
        <td>
            <fieldset>
                <label class="flosc-companion-mode-card <?php echo $flosc_content_display_mode === 'in_chat' ? 'is-active' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="in_chat" <?php checked($flosc_content_display_mode, 'in_chat'); ?>>
                    <strong>💬 In-Chat Only</strong> <em class="flosc-companion-mode-default">(default)</em>
                    <br><span class="flosc-companion-mode-copy">
                        The full-page FLOSC chat interface runs at your flow URL only.
                    </span>
                </label>
                
                <label class="flosc-companion-mode-card <?php echo $flosc_content_display_mode === 'companion' ? 'is-active' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="companion" <?php checked($flosc_content_display_mode, 'companion'); ?>>
                    <strong>🤝 Companion Mode</strong>
                    <br><span class="flosc-companion-mode-copy">
                        The companion widget appears on selected WordPress targets (posts, pages, categories, or tags)
                        and provides contextual chat support while users stay on-site.
                    </span>
                </label>
                
                <label class="flosc-companion-mode-card <?php echo $flosc_content_display_mode === 'both' ? 'is-active' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="both" <?php checked($flosc_content_display_mode, 'both'); ?>>
                    <strong>✨ Both</strong>
                    <br><span class="flosc-companion-mode-copy">
                        Enable both: full-page chat at the flow URL and companion widget on selected WordPress targets.
                    </span>
                </label>
            </fieldset>
        </td>
    </tr>
</table>

<!-- Companion Widget Settings (only relevant when companion or both mode is active) -->
<div id="companion-widget-settings" class="<?php echo $flosc_content_display_mode === 'in_chat' ? 'flosc-companion-disabled' : ''; ?>">
    
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
                <p class="description">Select the launcher glyph used in the floating button.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_greeting">Greeting Message</label></th>
            <td>
                <textarea name="flow_companion_greeting" id="flow_companion_greeting" rows="3" class="large-text"><?php echo esc_textarea($flosc_greeting); ?></textarea>
                <p class="description">The first message the companion shows when opened. Use warm, encouraging language.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_subtitle">Header Subtitle</label></th>
            <td>
                <input type="text" name="flow_companion_subtitle" id="flow_companion_subtitle" class="regular-text" value="<?php echo esc_attr($flosc_subtitle); ?>">
                <p class="description">Short helper line shown in the companion header under the title.</p>
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
                <p class="description">When unchecked, only logged-in users see the companion. Visitors are directed to the full chatbot experience.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_allow_fullscreen">Fullscreen Option</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_allow_fullscreen" id="flow_companion_allow_fullscreen" value="1" <?php checked($flosc_allow_fullscreen, '1'); ?>>
                    Show a fullscreen toggle in the companion header
                </label>
                <p class="description">When enabled, users can expand the companion to fullscreen.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flow_companion_default_fullscreen">Default Fullscreen</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_default_fullscreen" id="flow_companion_default_fullscreen" value="1" <?php checked($flosc_default_fullscreen, '1'); ?>>
                    Open in fullscreen by default (when fullscreen option is enabled)
                </label>
                <p class="description">Use this for immersive companion-first experiences.</p>
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
        📱 A floating <strong class="flosc-companion-preview-dot" data-accent="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>">●</strong> companion button will appear in the
        <strong><?php echo $flosc_position === 'bottom-left' ? 'bottom-left' : 'bottom-right'; ?></strong> 
        corner of every WordPress page (except the chatbot app).
    </p>
    <p class="flosc-companion-preview-copy flosc-companion-preview-copy-spaced">
        When a member clicks it, the companion opens with: "<em><?php echo esc_html(wp_trim_words($flosc_greeting, 15)); ?></em>"
    </p>
    <p class="flosc-companion-preview-copy flosc-companion-preview-copy-spaced">
        If the member is reading a lesson, the companion will know which lesson they're on and offer contextual help.
    </p>
    <div class="flosc-companion-preview-bubble-wrap <?php echo $flosc_position === 'bottom-left' ? 'flosc-companion-preview-bubble-wrap--left' : 'flosc-companion-preview-bubble-wrap--right'; ?>">
        <div class="flosc-companion-preview-bubble" data-accent="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>">
            💬
        </div>
    </div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    function applyCompanionQuickToggle() {
        var quickEnabled = $('#flosc_companion_quick_toggle').is(':checked');
        if (quickEnabled) {
            $('input[name="flow_companion_content_display_mode"][value="both"]').prop('checked', true);
            $('#flow_companion_enabled').prop('checked', true);
            $('#flow_companion_show_for_visitors').prop('checked', true);
            $('#flow_companion_allow_fullscreen').prop('checked', true);
            $('#flow_companion_default_fullscreen').prop('checked', true);
            $('#flow_companion_auto_open_enabled').prop('checked', true);
            $('#flow_companion_auto_open_once_per_session').prop('checked', true);
            if ((parseInt($('#flow_companion_auto_open_delay_ms').val(), 10) || 0) < 600) {
                $('#flow_companion_auto_open_delay_ms').val('800');
            }
            $('#flow_companion_mobile_behavior').val('fullscreen');
            $('input[name="flow_companion_target_mode"][value="sitewide"]').prop('checked', true);
            updateCompanionTargetMode();
        } else {
            $('input[name="flow_companion_content_display_mode"][value="in_chat"]').prop('checked', true);
            $('#flow_companion_enabled').prop('checked', false);
        }

        toggleCompanionSettings();
    }

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
    $('#flosc_companion_quick_toggle').on('change', applyCompanionQuickToggle);
    
    // Sync color picker with hex display
    $('#flow_companion_accent_color').on('input', function() {
        var color = $(this).val();
        $('#companion_accent_hex').val(color);
        $('.flosc-companion-preview-dot').css('color', color);
        $('.flosc-companion-preview-bubble').css('background', color);
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
        var allowFullscreen = $('#flow_companion_allow_fullscreen').is(':checked');
        var defaultFullscreen = $('#flow_companion_default_fullscreen').is(':checked');
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

        if (!allowFullscreen && defaultFullscreen) {
            hints.push('Default Fullscreen is enabled while Fullscreen Option is off. Default fullscreen will not apply until Fullscreen Option is enabled.');
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

    $('#flow_companion_auto_open_enabled, #flow_companion_auto_open_delay_ms, #flow_companion_auto_open_once_per_session, #flow_companion_launch_on_exit_intent, #flow_companion_launch_on_scroll_threshold, #flow_companion_launch_on_scroll_percent, #flow_companion_trigger_cooldown_ms, #flow_companion_allow_fullscreen, #flow_companion_default_fullscreen, #flow_companion_remember_open_state, #flow_companion_state_storage')
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

    $('input[name="flow_companion_target_mode"]').on('change', updateCompanionTargetMode);
    $('#flow_companion_targeting_customize').on('change', updateCompanionTargetMode);

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
    applyCompanionQuickToggle();
    updateCompanionValidationHints();
    updateCompanionTargetMode();
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
