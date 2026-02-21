<?php
/**
 * Temporary debug: aggressive bp_search diagnostics
 * DELETE THIS FILE after debugging
 */

define('BPSD_LOG', ABSPATH . 'bp-search-debug.txt');

function bpsd_log($msg) {
    file_put_contents(BPSD_LOG, date('H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

// Early init hook to confirm mu-plugin loads
add_action('init', function() {
    if (isset($_REQUEST['bp_search'])) {
        file_put_contents(BPSD_LOG, "=== NEW REQUEST " . date('Y-m-d H:i:s') . " ===\n");
        bpsd_log('[init] mu-plugin loaded, bp_search param present');
    }
});

// Hook at template_redirect
add_action('template_redirect', function() {
    if (isset($_REQUEST['bp_search'])) {
        $log  = '[template_redirect] ';
        $log .= 'URI=' . $_SERVER['REQUEST_URI'] . ' ';
        $log .= 'is_search=' . (is_search() ? 'YES' : 'NO') . ' ';
        $log .= 'bp_search_is_search=' . (function_exists('bp_search_is_search') ? (bp_search_is_search() ? 'YES' : 'NO') : 'FUNC_MISSING') . ' ';

        if (class_exists('BP_Search')) {
            $instance = BP_Search::instance();
            $log .= 'has_results=' . ($instance->has_search_results() ? 'YES' : 'NO') . ' ';
        } else {
            $log .= 'BP_Search=MISSING ';
        }
        bpsd_log($log);
    }
}, 999);

// Hook on template_include
add_filter('template_include', function($template) {
    if (isset($_REQUEST['bp_search'])) {
        bpsd_log('[template_include] ' . $template);
    }
    return $template;
}, 999);

// Hook the_content at multiple priorities including 11 and 12
foreach ([1, 8, 10, 11, 12, 20, 999] as $pri) {
    add_filter('the_content', function($content) use ($pri) {
        if (isset($_REQUEST['bp_search'])) {
            global $bpgs_main_content_filter_has_run;
            $log  = "[the_content@{$pri}] ";
            $log .= 'content_len=' . strlen($content) . ' ';
            $log .= 'flag=' . var_export($bpgs_main_content_filter_has_run, true) . ' ';
            $log .= 'post_id=' . get_the_ID();
            bpsd_log($log);
        }
        return $content;
    }, $pri);
}

// Shutdown hook to log all the_content filters registered
add_action('shutdown', function() {
    if (isset($_REQUEST['bp_search'])) {
        global $wp_filter;
        if (isset($wp_filter['the_content'])) {
            $priorities = array_keys($wp_filter['the_content']->callbacks);
            sort($priorities);
            bpsd_log('[shutdown] the_content priorities: ' . implode(',', $priorities));
            // Check priorities 9, 11, 12, 1500, 9999
            foreach ([9, 11, 12, 1500, 9999] as $chk) {
                if (isset($wp_filter['the_content']->callbacks[$chk])) {
                    foreach ($wp_filter['the_content']->callbacks[$chk] as $key => $val) {
                        $cb_name = $key;
                        if (is_array($val['function'])) {
                            if (is_object($val['function'][0])) {
                                $cb_name = get_class($val['function'][0]) . '::' . $val['function'][1];
                            } elseif (is_string($val['function'][0])) {
                                $cb_name = $val['function'][0] . '::' . $val['function'][1];
                            }
                        }
                        bpsd_log("[shutdown] priority $chk filter: $cb_name");
                    }
                }
            }
        }
        bpsd_log('[shutdown] DONE');
    }
});
