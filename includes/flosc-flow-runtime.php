<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Flow runtime config lives in WordPress options:
 *   option name: flosc_flow_{stem}
 *   array keys (runtime only): flow_messages, flow_phases, flow_styles
 *
 * Portable markdown (*.md) is separate: import/export only.
 * Do not store runtime message/phase/style lists under ivr_* keys.
 */

/**
 * Copy legacy per-flow option keys into flow_* keys, then remove legacy keys from $fs.
 *
 * Legacy keys (ivr_messages / ivr_phases / ivr_styles) may still exist in old options.
 * Call this before update_option so the saved row is clean.
 *
 * @param array $fs Flow option array (by reference).
 * @return bool True if any migration or cleanup changed $fs.
 */
function flosc_flow_migrate_legacy_runtime_keys(array &$fs) {
    $changed = false;

    if ((empty($fs['flow_messages']) || !is_array($fs['flow_messages']))
        && !empty($fs['ivr_messages']) && is_array($fs['ivr_messages'])
    ) {
        $fs['flow_messages'] = $fs['ivr_messages'];
        $changed = true;
    }
    if ((empty($fs['flow_phases']) || !is_array($fs['flow_phases']))
        && !empty($fs['ivr_phases']) && is_array($fs['ivr_phases'])
    ) {
        $fs['flow_phases'] = $fs['ivr_phases'];
        $changed = true;
    }
    if ((empty($fs['flow_styles']) || !is_array($fs['flow_styles']))
        && !empty($fs['ivr_styles']) && is_array($fs['ivr_styles'])
    ) {
        $fs['flow_styles'] = $fs['ivr_styles'];
        $changed = true;
    }

    foreach (array('ivr_messages', 'ivr_phases', 'ivr_styles') as $legacy_key) {
        if (array_key_exists($legacy_key, $fs)) {
            unset($fs[$legacy_key]);
            $changed = true;
        }
    }

    return $changed;
}

/**
 * Messages for a flow option array.
 *
 * Prefers flow_messages. Reads ivr_messages only if flow_messages is empty (old installs).
 *
 * @param array $fs Flow option array from get_option('flosc_flow_*').
 * @return array
 */
function flosc_flow_get_messages(array $fs) {
    if (!empty($fs['flow_messages']) && is_array($fs['flow_messages'])) {
        return $fs['flow_messages'];
    }

    // One-release read path for sites that still have only legacy keys in the option row.
    if (!empty($fs['ivr_messages']) && is_array($fs['ivr_messages'])) {
        return $fs['ivr_messages'];
    }

    return array();
}

/**
 * Phases for a flow option array.
 *
 * @param array $fs Flow option array.
 * @return array
 */
function flosc_flow_get_phases(array $fs) {
    if (!empty($fs['flow_phases']) && is_array($fs['flow_phases'])) {
        return $fs['flow_phases'];
    }

    if (!empty($fs['ivr_phases']) && is_array($fs['ivr_phases'])) {
        return $fs['ivr_phases'];
    }

    return array();
}

/**
 * Styles for a flow option array (message style definitions used at runtime).
 *
 * Not “IVR styles” — runtime styles belong under flow_styles only.
 *
 * @param array $fs Flow option array.
 * @return array
 */
function flosc_flow_get_styles(array $fs) {
    if (!empty($fs['flow_styles']) && is_array($fs['flow_styles'])) {
        return $fs['flow_styles'];
    }

    if (!empty($fs['ivr_styles']) && is_array($fs['ivr_styles'])) {
        return $fs['ivr_styles'];
    }

    return array();
}

/**
 * Write runtime messages/phases/styles into a flow option array.
 *
 * Writes only flow_messages, flow_phases, flow_styles.
 * Removes legacy ivr_messages / ivr_phases / ivr_styles if present.
 *
 * @param array $fs       Flow option array (by reference; caller must update_option).
 * @param array $messages Message map id => message array.
 * @param array $phases   Phase name => list of message ids.
 * @param array $styles   Style definitions.
 */
function flosc_flow_set_runtime(array &$fs, array $messages, array $phases = array(), array $styles = array()) {
    $fs['flow_messages'] = $messages;
    $fs['flow_phases']   = $phases;
    $fs['flow_styles']   = $styles;

    unset($fs['ivr_messages'], $fs['ivr_phases'], $fs['ivr_styles']);
}

/**
 * Load flosc_flow_{stem} option, migrate legacy keys in memory, optionally persist cleanup.
 *
 * @param string $flow_key e.g. flosc_flow_lesaep_ivr
 * @param bool   $persist  If true and migration removed legacy keys, update_option once.
 * @return array
 */
function flosc_flow_get_option_array($flow_key, $persist = false) {
    $flow_key = (string) $flow_key;
    $fs = get_option($flow_key, array());
    if (!is_array($fs)) {
        $fs = array();
    }

    $changed = flosc_flow_migrate_legacy_runtime_keys($fs);
    if ($persist && $changed && $flow_key !== '') {
        update_option($flow_key, $fs, false);
    }

    return $fs;
}

/**
 * Resolve live flow runtime config.
 *
 * 1) Read option flosc_flow_{stem} (keys flow_messages / flow_phases / flow_styles).
 * 2) If messages empty, parse portable IVR markdown file once for this request.
 * Does not write options on the empty-file fallback path.
 *
 * @param string $flow_id  Flow id or stem.
 * @param string $ivr_file Portable markdown filename (e.g. foo_ivr.md), used only if option empty.
 * @return array{messages:array,phases:array,styles:array,source:string,flow_key:string,stem:string}
 */
function flosc_resolve_flow_runtime($flow_id = '', $ivr_file = '') {
    $source   = 'empty';
    $messages = array();
    $phases   = array();
    $styles   = array();

    $stem = '';
    if ($flow_id !== '' && $flow_id !== null) {
        $stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
        if ($stem === '') {
            $stem = sanitize_key((string) $flow_id);
        }
    }

    if ($stem === '' && $ivr_file !== '' && $ivr_file !== null) {
        $stem = sanitize_key(pathinfo((string) $ivr_file, PATHINFO_FILENAME));
    }

    if ($stem === '' && function_exists('flosc') && method_exists(flosc(), 'get_current_flow')) {
        $flow = flosc()->get_current_flow();
        if (is_array($flow)) {
            $from_id  = (string) ($flow['id'] ?? '');
            $from_ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? '');

            if ($from_id !== '') {
                $stem = sanitize_key(pathinfo(basename($from_id), PATHINFO_FILENAME));
            }

            if ($stem === '' && $from_ivr !== '') {
                $stem = sanitize_key(pathinfo(basename($from_ivr), PATHINFO_FILENAME));
                if ($ivr_file === '') {
                    $ivr_file = basename($from_ivr);
                }
            }
        }
    }

    if ($stem === '') {
        $stem = 'flosc_default_technical_ivr';
        if ($ivr_file === '') {
            $ivr_file = 'flosc_default_technical_ivr.md';
        }
    }

    $flow_key = 'flosc_flow_' . $stem;
    // Persist one-time cleanup of legacy ivr_* keys when present.
    $fs = flosc_flow_get_option_array($flow_key, true);
    $messages = flosc_flow_get_messages($fs);
    $phases   = flosc_flow_get_phases($fs);
    $styles   = flosc_flow_get_styles($fs);

    if (!empty($messages)) {
        $source = 'option';
    }

    // Portable markdown fallback only when the option has no messages.
    if (empty($messages) && $ivr_file !== '' && function_exists('flosc_config_file')) {
        $path = flosc_config_file($ivr_file);
        if ($path && file_exists($path)) {
            if (!class_exists('FLOSC_IVR_Parser')) {
                require_once FLOSC_PLUGIN_DIR . 'includes/portability/class-ivr-parser.php';
            }

            $parser   = FLOSC_IVR_Parser::flosc_instance();
            $cfg      = $parser->flosc_parse(flosc_fs_get_contents($path));
            $messages = $cfg['messages'] ?? array();
            $phases   = $cfg['phases'] ?? array();
            $styles   = $cfg['styles'] ?? array();

            if (!empty($messages)) {
                $source = 'file';
            }
        }
    }

    if (is_array($messages)) {
        foreach ($messages as $k => $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $type = strtolower(trim((string) ($msg['type'] ?? '')));
            if ($type === 'offer' && empty($msg['display_format'])) {
                $messages[$k]['display_format'] = 'card';
            }
        }
    }

    return array(
        'messages' => is_array($messages) ? $messages : array(),
        'phases'   => is_array($phases) ? $phases : array(),
        'styles'   => is_array($styles) ? $styles : array(),
        'source'   => $source,
        'flow_key' => $flow_key,
        'stem'     => $stem,
    );
}

/**
 * Extract one phase message list from a resolved flow runtime config.
 *
 * @param array  $config Return value of flosc_resolve_flow_runtime().
 * @param string $phase  Phase name.
 * @return array
 */
function flosc_flow_phase_messages(array $config, $phase) {
    $phases = $config['phases'] ?? array();
    $all    = $config['messages'] ?? array();
    if (!isset($phases[$phase]) || !is_array($phases[$phase])) {
        return array();
    }

    $out = array();
    foreach ($phases[$phase] as $name) {
        if (isset($all[$name])) {
            $out[] = $all[$name];
        }
    }

    return $out;
}

/**
 * Build CSS string from resolved flow runtime styles.
 *
 * @param array $config Return value of flosc_resolve_flow_runtime().
 * @return string
 */
function flosc_flow_styles_css(array $config) {
    $styles = $config['styles'] ?? array();
    $css    = '';
    if (!is_array($styles)) {
        return $css;
    }

    foreach ($styles as $style) {
        if (is_array($style) && !empty($style['css'])) {
            $css .= $style['css'] . "\n";
        } elseif (is_string($style) && $style !== '') {
            $css .= $style . "\n";
        }
    }

    return $css;
}
