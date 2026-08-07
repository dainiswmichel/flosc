<?php
/**
 * FLOSC Admin Settings Helper
 * v1.2.4: Helper for flow-aware settings in admin tabs
 * 
 * Usage in tab files:
 *   $value = flosc_admin_get_value('ai_provider', 'ivr');
 *   - When editing a flow: returns flow[$key] if set, else global
 *   - When editing global: returns global wp_option value
 */

if (!defined('ABSPATH')) exit;

/**
 * Get the appropriate value for an admin settings field
 * 
 * @param string $key Setting key (without 'flosc_' prefix)
 * @param mixed $default Default value
 * @return mixed The value to display in the form
 */
function flosc_admin_get_value($key, $default = '') {
    // Check if we're editing a specific flow
    if (isset($GLOBALS['flosc_editing_flow']) && isset($GLOBALS['flosc_editing_flow_data'])) {
        $flow = $GLOBALS['flosc_editing_flow_data'];
        
        // Return flow-specific value if it exists
        if (isset($flow[$key]) && $flow[$key] !== '' && $flow[$key] !== null) {
            return $flow[$key];
        }
        
        // Return empty to indicate "using global"
        // The placeholder will show the global value
        return '';
    }
    
    // Editing global settings - return wp_option
    return get_option('flosc_' . $key, $default);
}

/**
 * Get the global value for showing as placeholder when editing flow
 * 
 * @param string $key Setting key (without 'flosc_' prefix)
 * @param mixed $default Default value
 * @return mixed The global value for placeholder text
 */
function flosc_admin_get_global($key, $default = '') {
    return get_option('flosc_' . $key, $default);
}

/**
 * Check if currently editing a specific flow (vs global)
 * 
 * @return bool True if editing a flow, false if editing global
 */
function flosc_admin_is_editing_flow() {
    return isset($GLOBALS['flosc_editing_flow']) && !empty($GLOBALS['flosc_editing_flow']);
}

/**
 * Get the flow ID being edited (or empty string if global)
 * 
 * @return string Flow ID or empty string
 */
function flosc_admin_get_editing_flow_id() {
    return $GLOBALS['flosc_editing_flow'] ?? '';
}

/**
 * Render a text input with "using global" placeholder when editing flow
 * 
 * @param string $key Setting key (without 'flosc_' prefix)
 * @param string $default Default value
 * @param string $class CSS class
 * @param string $placeholder Custom placeholder (overrides global value)
 */
function flosc_admin_text_input($key, $default = '', $class = 'regular-text', $placeholder = null) {
    $value = flosc_admin_get_value($key, $default);
    $flosc_name = 'flosc_' . $key;
    
    // When editing flow, show global value as placeholder
    if (flosc_admin_is_editing_flow() && $placeholder === null) {
        $global_value = flosc_admin_get_global($key, $default);
        $placeholder = $global_value ? "Using global: " . $global_value : '';
    }
    
    printf(
        '<input type="text" id="%s" name="%s" value="%s" class="%s" placeholder="%s">',
        esc_attr($flosc_name),
        esc_attr($flosc_name),
        esc_attr($value),
        esc_attr($class),
        esc_attr($placeholder ?? '')
    );
}

/**
 * Render a textarea with "using global" placeholder when editing flow
 */
function flosc_admin_textarea($key, $default = '', $rows = 5, $class = 'large-text') {
    $value = flosc_admin_get_value($key, $default);
    $flosc_name = 'flosc_' . $key;
    
    $placeholder = '';
    if (flosc_admin_is_editing_flow()) {
        $global_value = flosc_admin_get_global($key, $default);
        $placeholder = $global_value ? "Using global setting..." : '';
    }
    
    printf(
        '<textarea id="%s" name="%s" rows="%d" class="%s" placeholder="%s">%s</textarea>',
        esc_attr($flosc_name),
        esc_attr($flosc_name),
        absint( $rows ),
        esc_attr($class),
        esc_attr($placeholder),
        esc_textarea($value)
    );
}

/**
 * Render a select dropdown
 */
function flosc_admin_select($key, $options, $default = '') {
    $value = flosc_admin_get_value($key, $default);
    $flosc_name = 'flosc_' . $key;
    
    // When editing flow and no value set, show "Use Global" option
    $show_use_global = flosc_admin_is_editing_flow();
    $global_value = flosc_admin_get_global($key, $default);
    
    echo '<select id="' . esc_attr($flosc_name) . '" name="' . esc_attr($flosc_name) . '">';
    
    if ($show_use_global) {
        $global_label = isset($options[$global_value]) ? $options[$global_value] : $global_value;
        printf(
            '<option value="" %s>— Use Global (%s) —</option>',
            selected($value, '', false),
            esc_html($global_label)
        );
    }
    
    foreach ($options as $opt_value => $opt_label) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($opt_value),
            selected($value, $opt_value, false),
            esc_html($opt_label)
        );
    }
    
    echo '</select>';
}

/**
 * Derive companion hub defaults from a flow settings array (parameterized — no brand hardcodes).
 *
 * Uses only this flow's own Identity/Content fields:
 * - domain / custom_domain + slug → full-screen chat URL
 * - lessons_category or first lesson_groups[].category → knowledge-hub category archive
 * - companion_flow_slug or slug on WordPress site URL → companion iframe chat route
 *   (same origin as the knowledge hub so WP login cookies apply for guests/members)
 *
 * @param array $flow_settings Per-flow option payload (flosc_flow_*).
 * @return array{
 *   fullscreen: string,
 *   companion: string,
 *   chat_app: string,
 *   flow_slug: string,
 *   lessons_category: string,
 *   include_rules: string
 * }
 */
function flosc_companion_hub_defaults_from_flow(array $flow_settings) {
    $slug = sanitize_title((string) ($flow_settings['slug'] ?? ''));
    $domain = trim((string) ($flow_settings['domain'] ?? $flow_settings['custom_domain'] ?? ''));
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = rtrim((string) $domain, '/');

    $lessons_cat = sanitize_title((string) ($flow_settings['lessons_category'] ?? ''));
    if ($lessons_cat === '' && !empty($flow_settings['lesson_groups']) && is_array($flow_settings['lesson_groups'])) {
        foreach ($flow_settings['lesson_groups'] as $group) {
            if (!empty($group['category'])) {
                $lessons_cat = sanitize_title((string) $group['category']);
                break;
            }
        }
    }

    $flow_for_app = $flow_settings;
    if (empty($flow_for_app['custom_domain']) && $domain !== '') {
        $flow_for_app['custom_domain'] = $domain;
    }
    if (empty($flow_for_app['slug']) && $slug !== '') {
        $flow_for_app['slug'] = $slug;
    }

    $fullscreen = '';
    if (function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_app_url')) {
        $fullscreen = (string) flosc()->get_app_url($flow_for_app);
    }
    if ($fullscreen === '') {
        $fullscreen = $slug !== '' ? home_url('/' . $slug . '/') : home_url('/');
    }
    $fullscreen = esc_url_raw($fullscreen, ['http', 'https']);
    if ($fullscreen === '') {
        $fullscreen = home_url('/');
    }

    $companion = $lessons_cat !== ''
        ? home_url('/category/' . $lessons_cat . '/')
        : home_url('/');
    $companion = esc_url_raw($companion, ['http', 'https']);
    if ($companion === '') {
        $companion = home_url('/');
    }

    $flow_slug = sanitize_title((string) ($flow_settings['companion_flow_slug'] ?? $slug));

    // Iframe chat route: WordPress site + flow slug (parameterized).
    // Knowledge hubs usually live on the WP host; a same-origin iframe keeps
    // guest/member WP sessions. Override anytime with companion_chat_app_url.
    $chat_app = $flow_slug !== ''
        ? home_url('/' . $flow_slug . '/')
        : home_url('/');
    $chat_app = esc_url_raw($chat_app, ['http', 'https']);
    if ($chat_app === '') {
        $chat_app = home_url('/');
    }

    // Suggested include rules from Content → lessons category (still editable in admin).
    $include = [];
    if ($lessons_cat !== '') {
        if (function_exists('get_term_by')) {
            $term = get_term_by('slug', $lessons_cat, 'category');
            if ($term && !is_wp_error($term) && !empty($term->term_id)) {
                $include[] = 'category:' . (int) $term->term_id;
            } else {
                $include[] = 'category:' . $lessons_cat;
            }
        } else {
            $include[] = 'category:' . $lessons_cat;
        }
        $include[] = 'path:/category/' . $lessons_cat;
    }

    return [
        'fullscreen'       => $fullscreen,
        'companion'        => $companion,
        'chat_app'         => $chat_app,
        'flow_slug'        => $flow_slug,
        'lessons_category' => $lessons_cat,
        'include_rules'    => implode("\n", $include),
    ];
}
