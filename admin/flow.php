<?php
/**
 * FLOSC Flow Tab — F→L→O→S→C read-only phase overview
 *
 * Shows live counts and edit links for each of the five FLOSC flow phases.
 * Data sourced from $flosc_flow_settings (via $GLOBALS) and flosc() helper objects.
 *
 * v4.0.0: Initial implementation
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$flosc_flow_settings  = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_selected_ivr   = $GLOBALS['flosc_current_ivr']      ?? '';
$flosc_flow_key       = $GLOBALS['flosc_settings_key']     ?? '';
$flosc_get            = wp_unslash($_GET);
$flosc_post           = wp_unslash($_POST);
$flosc_ivr_param      = rawurlencode( $flosc_selected_ivr );
$flosc_base_url       = admin_url( 'admin.php?page=flosc-settings&ivr=' . $flosc_ivr_param . '&tab=' );
$flosc_flow_docs_url  = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_selected_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-flow';
$flosc_flow_docs_inventory_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_selected_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#inventory-flow-family';
$flosc_portability_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_selected_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-flow-all-flows-portability-fields';
$flosc_flow_view = isset($flosc_get['view']) ? sanitize_key((string) $flosc_get['view']) : 'single';
if (!in_array($flosc_flow_view, ['single', 'all'], true)) {
    $flosc_flow_view = 'single';
}
$flosc_flow_single_url = add_query_arg([
    'page' => 'flosc-settings',
    'tab'  => 'flow',
    'ivr'  => $flosc_selected_ivr,
    'view' => 'single',
], admin_url('admin.php'));
$flosc_flow_all_url = add_query_arg([
    'page' => 'flosc-settings',
    'tab'  => 'flow',
    'ivr'  => $flosc_selected_ivr,
    'view' => 'all',
], admin_url('admin.php'));

$flosc_available_ivr_files = [];
if ($flosc_flow_view === 'all') {
    /*
     * The kit upload itself runs on admin_init, in
     * FLOSC_Admin::maybe_process_flosc_settings_post(). It has to: a successful
     * upload ends in wp_safe_redirect(), which needs headers that are already
     * sent by the time this template renders. Validation failures record
     * add_settings_error() during that same request, so the notices below and
     * settings_errors() in admin/settings.php still show them.
     *
     * What is left here is reading the post-redirect query flags and turning
     * them into success notices.
     */
    if (isset($flosc_get['flosc_ivr_uploaded']) && '1' === (string) $flosc_get['flosc_ivr_uploaded']) {
        $flosc_up_name = isset($flosc_get['ivr']) ? sanitize_file_name((string) $flosc_get['ivr']) : '';
        add_settings_error(
            'flosc_settings',
            'upload_success',
            $flosc_up_name !== ''
                ? sprintf(
                    /* translators: %s: IVR filename for the new flow */
                    esc_html__('New flow ready: %s. It is selected in Switch Flow.', 'flosc'),
                    esc_html($flosc_up_name)
                )
                : esc_html__('New flow ready from the uploaded IVR file.', 'flosc'),
            'success'
        );
    }
    if (isset($flosc_get['flosc_portability_done']) && '1' === (string) $flosc_get['flosc_portability_done']) {
        $flosc_port_notice = get_transient('flosc_portability_notice_' . get_current_user_id());
        delete_transient('flosc_portability_notice_' . get_current_user_id());
        $flosc_port_msg  = '';
        $flosc_port_type = 'success';
        if ( is_array( $flosc_port_notice ) ) {
            $flosc_port_msg  = isset( $flosc_port_notice['message'] ) ? (string) $flosc_port_notice['message'] : '';
            $flosc_port_type = ( isset( $flosc_port_notice['type'] ) && 'error' === $flosc_port_notice['type'] ) ? 'error' : 'success';
        } elseif ( is_string( $flosc_port_notice ) && $flosc_port_notice !== '' ) {
            $flosc_port_msg = $flosc_port_notice;
        }
        if ( $flosc_port_msg !== '' ) {
            add_settings_error( 'flosc_settings', 'portability_done', esc_html( $flosc_port_msg ), $flosc_port_type );
        } else {
            add_settings_error(
                'flosc_settings',
                'portability_done',
                esc_html__( 'Portability upload finished.', 'flosc' ),
                'success'
            );
        }
    }

    $flosc_ivr_dir = function_exists('flosc_data_dir') ? flosc_data_dir() : '';

    if (isset($flosc_get['flosc_download_ivr']) && isset($flosc_get['_wpnonce'])) {
        $flosc_download_file = sanitize_file_name((string) $flosc_get['flosc_download_ivr']);
        if (wp_verify_nonce(sanitize_text_field((string) $flosc_get['_wpnonce']), 'flosc_download_ivr_' . $flosc_download_file)) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to download IVR files.', 'flosc'));
            }
            $flosc_download_path = function_exists('flosc_resolve_ivr_file_path')
                ? flosc_resolve_ivr_file_path($flosc_download_file)
                : '';
            if ($flosc_download_path !== '' && file_exists($flosc_download_path) && is_readable($flosc_download_path)) {
                if (!function_exists('WP_Filesystem')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                global $wp_filesystem;
                WP_Filesystem();
                $flosc_download_content = is_object($wp_filesystem) ? $wp_filesystem->get_contents($flosc_download_path) : '';
                if ($flosc_download_content === false) {
                    $flosc_download_content = '';
                }
                $flosc_fs_dl = class_exists('FLOSC_Filesystem') ? new FLOSC_Filesystem() : null;
                if ($flosc_fs_dl) {
                    $flosc_fs_dl->stream_plain_download_and_exit(
                        (string) $flosc_download_content,
                        'text/markdown; charset=UTF-8',
                        basename((string) $flosc_download_file)
                    );
                }
                status_header(500);
                exit;
            }
        }
        add_settings_error('flosc_settings', 'download_failed', 'Could not download IVR file.', 'error');
    }

    if (isset($flosc_post['flosc_duplicate_ivr_file']) && isset($flosc_post['duplicate_ivr_file'])) {
        check_admin_referer('flosc_duplicate_ivr_file');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to duplicate IVR files.', 'flosc'));
        }

        $flosc_source_file = basename(sanitize_file_name((string) $flosc_post['duplicate_ivr_file']));
        $flosc_source_path = function_exists('flosc_resolve_ivr_file_path')
            ? flosc_resolve_ivr_file_path($flosc_source_file)
            : '';

        if (!file_exists((string) $flosc_source_path)) {
            add_settings_error('flosc_settings', 'duplicate_invalid', 'Source IVR file not found.', 'error');
        } elseif (!function_exists('flosc_data_file_path') || !function_exists('flosc_write_data_file')) {
            add_settings_error('flosc_settings', 'duplicate_failed', 'Uploads data directory is not available. Cannot duplicate IVR file.', 'error');
        } else {
            $flosc_extension = pathinfo($flosc_source_file, PATHINFO_EXTENSION);
            $flosc_base_name = pathinfo($flosc_source_file, PATHINFO_FILENAME);
            $flosc_duplicate_file = $flosc_base_name . '-copy.' . $flosc_extension;
            $flosc_duplicate_path = flosc_data_file_path($flosc_duplicate_file);
            $flosc_counter = 2;

            while ('' !== $flosc_duplicate_path && file_exists($flosc_duplicate_path)) {
                $flosc_duplicate_file = $flosc_base_name . '-copy-' . $flosc_counter . '.' . $flosc_extension;
                $flosc_duplicate_path = flosc_data_file_path($flosc_duplicate_file);
                $flosc_counter++;
            }

            $flosc_source_body = ('' !== $flosc_duplicate_path) ? flosc_fs_get_contents($flosc_source_path) : false;
            if (false === $flosc_source_body || '' === $flosc_duplicate_path || !flosc_write_data_file($flosc_duplicate_path, $flosc_source_body)) {
                add_settings_error('flosc_settings', 'duplicate_failed', 'Could not duplicate IVR file. Check file permissions or uploads availability.', 'error');
            } else {
                add_settings_error('flosc_settings', 'duplicate_success', 'Duplicated IVR file: ' . $flosc_duplicate_file, 'success');
            }
        }
    }

    // Table "Apply": merge SOURCE file into CURRENT flow (Switch Flow); do not re-point current flow to the source filename.
    if (isset($flosc_post['flosc_import_selected_ivr_file']) && isset($flosc_post['import_ivr_file'])) {
        check_admin_referer('flosc_import_selected_ivr_file');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to import IVR files.', 'flosc'));
        }

        $flosc_source_file = basename(sanitize_file_name((string) $flosc_post['import_ivr_file']));
        $flosc_source_path = '';
        if (function_exists('flosc_resolve_ivr_file_path')) {
            $flosc_source_path = (string) flosc_resolve_ivr_file_path($flosc_source_file);
        }
        if ($flosc_source_path === '' && function_exists('flosc_data_file_path')) {
            $flosc_source_path = (string) flosc_data_file_path($flosc_source_file);
        }
        $flosc_work_file = $flosc_selected_ivr !== '' ? $flosc_selected_ivr : $flosc_source_file;
        $flosc_work_path = function_exists('flosc_data_file_path')
            ? flosc_data_file_path($flosc_work_file)
            : '';
        $flosc_work_key = $flosc_flow_key;
        if ($flosc_work_key === '' && $flosc_work_file !== '') {
            $flosc_work_key = 'flosc_flow_' . sanitize_key(pathinfo($flosc_work_file, PATHINFO_FILENAME));
        }

        if ($flosc_source_path === '' || !file_exists($flosc_source_path)) {
            add_settings_error('flosc_settings', 'import_selected_failed', 'Selected IVR file not found: ' . $flosc_source_file, 'error');
        } elseif ($flosc_work_key === '') {
            add_settings_error('flosc_settings', 'import_selected_failed', esc_html__('No current flow selected (Switch Flow).', 'flosc'), 'error');
        } else {
            $flosc_result = flosc_import_ivr_to_database(false, $flosc_source_path, $flosc_work_key, 'merge');
            if (!empty($flosc_result['success'])) {
                $flosc_export_ok = false;
                if ($flosc_work_path !== '' && function_exists('flosc_auto_export_ivr_to_file')) {
                    $flosc_export_ok = (bool) flosc_auto_export_ivr_to_file($flosc_work_key, $flosc_work_path);
                }
                $flosc_fs = get_option($flosc_work_key, []);
                if (!is_array($flosc_fs)) {
                    $flosc_fs = [];
                }
                // Keep current flow's own IVR filename; only ensure it is set.
                if (empty($flosc_fs['active_ivr_file'])) {
                    $flosc_fs['active_ivr_file'] = $flosc_work_file;
                }
                if (empty($flosc_fs['ivr_file'])) {
                    $flosc_fs['ivr_file'] = $flosc_work_file;
                }
                update_option($flosc_work_key, $flosc_fs);
                $GLOBALS['flosc_current_settings'] = $flosc_fs;
                $flosc_flow_settings = $flosc_fs;
                if ($flosc_export_ok) {
                    add_settings_error(
                        'flosc_settings',
                        'import_selected_success',
                        sprintf(
                            /* translators: 1: source file 2: current flow file */
                            esc_html__('Merged %1$s into current flow %2$s (settings merge; secrets unchanged).', 'flosc'),
                            esc_html($flosc_source_file),
                            esc_html($flosc_work_file)
                        ),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'flosc_settings',
                        'import_selected_partial',
                        sprintf(
                            /* translators: %s: current flow file */
                            esc_html__('Merged into current flow, but file sync failed for: %s', 'flosc'),
                            esc_html($flosc_work_file)
                        ),
                        'error'
                    );
                }
            } else {
                add_settings_error('flosc_settings', 'import_selected_failed', 'Import failed: ' . esc_html((string) ($flosc_result['message'] ?? 'Unknown error')), 'error');
            }
        }
    }

    if (isset($flosc_post['flosc_delete_ivr_file']) && isset($flosc_post['delete_ivr_file'])) {
        check_admin_referer('flosc_delete_ivr_file');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete IVR files.', 'flosc'));
        }

        $flosc_delete_file = basename(sanitize_file_name((string) $flosc_post['delete_ivr_file']));
        $flosc_delete_path = function_exists('flosc_data_file_path')
            ? flosc_data_file_path($flosc_delete_file)
            : '';

        if ($flosc_delete_file === (string) $flosc_selected_ivr) {
            add_settings_error('flosc_settings', 'delete_blocked_current', 'Cannot delete the currently selected flow file. Switch flow first, then delete if needed.', 'error');
        } elseif ('' === $flosc_delete_path || !file_exists($flosc_delete_path)) {
            add_settings_error('flosc_settings', 'delete_invalid', 'File not found or not a managed IVR file.', 'error');
        } elseif (wp_delete_file($flosc_delete_path) === false) {
            add_settings_error('flosc_settings', 'delete_failed', 'Failed to delete IVR file. Check file permissions.', 'error');
        } else {
            add_settings_error('flosc_settings', 'delete_success', 'Deleted IVR file: ' . $flosc_delete_file, 'success');
        }
    }

    if (is_dir($flosc_ivr_dir)) {
        $flosc_files = array_merge(
            glob($flosc_ivr_dir . '*_ivr.md'),
            glob($flosc_ivr_dir . 'ivr*.md')
        );
        $flosc_files = array_unique($flosc_files);
        sort($flosc_files);
        foreach ($flosc_files as $flosc_file) {
            $flosc_filename = basename($flosc_file);
            if (strpos($flosc_filename, 'backup') === false) {
                $flosc_available_ivr_files[] = $flosc_filename;
            }
        }
    }
}

// ── Gather live data ──────────────────────────────────────────────────────────

// ── Parse IVR file once — used for pill counts across all phases ──────────────
$flosc_ivr_pill_counts = [ 'freeline' => 0, 'offer' => 0, 'content' => 0 ];
$flosc_ivr_path = flosc_config_file($flosc_selected_ivr);
if ( $flosc_selected_ivr && file_exists( $flosc_ivr_path ) && class_exists( 'FLOSC_IVR_Parser' ) ) {
    $flosc_ivr_parser = FLOSC_IVR_Parser::flosc_instance();
    $flosc_ivr_data   = $flosc_ivr_parser->flosc_parse( flosc_fs_get_contents( $flosc_ivr_path ) );
    foreach ( array_keys( $flosc_ivr_pill_counts ) as $flosc_phase ) {
        foreach ( $flosc_ivr_data['phases'][ $flosc_phase ] ?? [] as $flosc_name ) {
            if ( ( $flosc_ivr_data['messages'][ $flosc_name ]['type'] ?? '' ) === 'suggested_user_autoprompt' ) {
                $flosc_ivr_pill_counts[ $flosc_phase ]++;
            }
        }
    }
}

// F — Freeline: quiz + visitor pills + IVR file
// Empty enabled_quizzes = no quiz for this flow. Never invent a sample quiz.
$flosc_enabled_quizzes = $flosc_flow_settings['enabled_quizzes'] ?? [];
if ( ! is_array( $flosc_enabled_quizzes ) ) {
    $flosc_enabled_quizzes = [];
}
$flosc_enabled_quizzes  = array_values( array_filter( array_map( 'sanitize_key', $flosc_enabled_quizzes ) ) );
$flosc_quiz_configured  = ! empty( $flosc_enabled_quizzes );
$flosc_quiz_count       = count( $flosc_enabled_quizzes );
$flosc_quiz_word        = $flosc_quiz_count === 1 ? 'Quiz' : 'Quizzes';
$flosc_edit_quiz_label  = $flosc_quiz_count === 1 ? 'Edit Quiz →' : 'Edit Quizzes →';

if ( $flosc_quiz_configured && class_exists( 'FLOSC_Quiz_Registry' ) ) {
    $flosc_names = [];
    foreach ( $flosc_enabled_quizzes as $flosc_qid ) {
        $flosc_qt      = FLOSC_Quiz_Registry::get_quiz( $flosc_qid );
        $flosc_names[] = $flosc_qt ? $flosc_qt->get_name() : ucwords( str_replace( '_', ' ', $flosc_qid ) );
    }
    $flosc_bullets         = array_map( fn( $n ) => '<span class="flosc-flow-bullet">• ' . esc_html( $n ) . '</span>', $flosc_names );
    $flosc_quiz_label_html = '<strong>' . $flosc_quiz_count . ' ' . $flosc_quiz_word . ' ✅</strong>' . implode( '', $flosc_bullets );
} else {
    $flosc_quiz_label_html = '<strong>0 Quizzes ❌</strong>';
    $flosc_edit_quiz_label = 'Edit Quiz →';
}

$flosc_visitor_pills = count( $flosc_flow_settings['autoprompts']['visitor'] ?? [] );
$flosc_ivr_file_label = $flosc_selected_ivr ? esc_html( $flosc_selected_ivr ) : 'None configured';

// L — Login: SSO providers
$flosc_sso_providers   = [];
foreach ( ['google', 'apple', 'facebook', 'microsoft', 'linkedin'] as $flosc_p ) {
    if ( ! empty( $flosc_flow_settings[ 'sso_' . $flosc_p . '_enabled' ] ) ) {
        $flosc_sso_providers[] = ucfirst( $flosc_p );
    }
}
$flosc_sso_label = $flosc_sso_providers ? implode( ', ', $flosc_sso_providers ) : 'WordPress native';

// O — Offer: offers count + guest pills
$flosc_flow_id_key    = $flosc_selected_ivr ? pathinfo( $flosc_selected_ivr, PATHINFO_FILENAME ) : null;
$flosc_all_offers     = [];
$flosc_active_count   = 0;
$flosc_draft_count    = 0;
if ( function_exists( 'flosc' ) && $flosc_flow_id_key ) {
    $flosc_all_offers = flosc()->sale()->offers()->get_all_offers( $flosc_flow_id_key );
    foreach ( $flosc_all_offers as $flosc_o ) {
        if ( ( $flosc_o['status'] ?? 'draft' ) === 'active' ) {
            $flosc_active_count++;
        } else {
            $flosc_draft_count++;
        }
    }
}
$flosc_offers_label  = $flosc_active_count . ' active' . ( $flosc_draft_count ? ', ' . $flosc_draft_count . ' draft' : '' );
$flosc_guest_pills   = count( $flosc_flow_settings['autoprompts']['guest'] ?? [] ) + $flosc_ivr_pill_counts['offer'];

// S — Sale: payment providers (read directly from flow_settings — same source as Payments tab)
$flosc_paypal_cfg  = ! empty( $flosc_flow_settings['paypal_enabled'] )
               && ! empty( $flosc_flow_settings['paypal_client_id'] )
               && ! empty( $flosc_flow_settings['paypal_secret'] );
$flosc_paypal_mode = $flosc_flow_settings['paypal_mode'] ?? 'sandbox';

$flosc_stripe_mode = $flosc_flow_settings['stripe_mode'] ?? 'test';
$flosc_stripe_sk   = $flosc_stripe_mode === 'live'
               ? ( $flosc_flow_settings['stripe_live_sk'] ?? '' )
               : ( $flosc_flow_settings['stripe_test_sk'] ?? '' );
$flosc_stripe_cfg  = ! empty( $flosc_flow_settings['stripe_enabled'] ) && ! empty( $flosc_stripe_sk );

// C — Content: lessons + member pills + AI provider
$flosc_content_item_groups  = $flosc_flow_settings['content_item_groups'] ?? [];
if ( empty( $flosc_content_item_groups ) && ! empty( $flosc_flow_settings['content_item_category'] ) ) {
    $flosc_content_item_groups = [ [ 'category' => $flosc_flow_settings['content_item_category'] ] ];
}
$flosc_lesson_count  = count( $flosc_content_item_groups );
$flosc_lessons_label = $flosc_lesson_count ? $flosc_lesson_count . ' lesson group' . ( $flosc_lesson_count !== 1 ? 's' : '' ) : 'Not configured';

$flosc_member_pills  = count( $flosc_flow_settings['autoprompts']['member'] ?? [] ) + $flosc_ivr_pill_counts['content'];

$flosc_companion_mode = sanitize_text_field((string) ($flosc_flow_settings['companion_content_display_mode'] ?? 'in_chat'));
$flosc_companion_enabled = !empty($flosc_flow_settings['companion_enabled']);
$flosc_companion_mode_labels = [
    'in_chat' => 'Full-page',
    'companion' => 'Companion',
    'both' => 'Hybrid',
];
$flosc_companion_sitewide_profile_active = (
    $flosc_companion_enabled
    && ($flosc_companion_mode === 'companion' || $flosc_companion_mode === 'both')
    && !empty($flosc_flow_settings['companion_show_for_visitors'])
    && !empty($flosc_flow_settings['companion_allow_fullscreen'])
    && !empty($flosc_flow_settings['companion_default_fullscreen'])
);
$flosc_companion_mode_label = $flosc_companion_mode_labels[$flosc_companion_mode] ?? 'Full-page';
if ($flosc_companion_sitewide_profile_active) {
    $flosc_companion_mode_label = 'Sitewide ON Profile (' . $flosc_companion_mode_label . ')';
}
$flosc_companion_status = ($flosc_companion_mode === 'companion' || $flosc_companion_mode === 'both')
    ? ($flosc_companion_enabled ? 'Widget enabled' : 'Widget disabled')
    : 'Full-page only (no companion widget)';
$flosc_companion_auto = !empty($flosc_flow_settings['companion_auto_open_enabled']) ? 'Auto open' : 'Manual open';
$flosc_companion_exit = !empty($flosc_flow_settings['companion_launch_on_exit_intent']) ? 'Exit trigger on' : 'Exit trigger off';
$flosc_companion_scroll = !empty($flosc_flow_settings['companion_launch_on_scroll_threshold'])
    ? ('Scroll trigger ' . max(0, min(100, intval($flosc_flow_settings['companion_launch_on_scroll_percent'] ?? 0))) . '%')
    : 'Scroll trigger off';
$flosc_companion_cooldown_ms = max(0, intval($flosc_flow_settings['companion_trigger_cooldown_ms'] ?? 0));
$flosc_companion_cooldown_label = $flosc_companion_cooldown_ms > 0
    ? ('Cooldown ' . round($flosc_companion_cooldown_ms / 1000) . 's')
    : 'No cooldown';
$flosc_companion_remember_state = !empty($flosc_flow_settings['companion_remember_open_state']);
$flosc_companion_state_storage = sanitize_text_field((string) ($flosc_flow_settings['companion_state_storage'] ?? 'session'));
$flosc_companion_storage_label = ucfirst($flosc_companion_state_storage);
$flosc_companion_effective_parts = [];

if (!$flosc_companion_enabled || ($flosc_companion_mode !== 'companion' && $flosc_companion_mode !== 'both')) {
    $flosc_companion_effective_parts[] = 'Companion widget inactive in current mode';
} else {
    $flosc_companion_effective_parts[] = 'Companion widget active';

    if (!empty($flosc_flow_settings['companion_auto_open_enabled'])) {
        $flosc_companion_effective_parts[] = 'Auto open enabled';
    }

    if (!empty($flosc_flow_settings['companion_launch_on_exit_intent']) || !empty($flosc_flow_settings['companion_launch_on_scroll_threshold'])) {
        $flosc_companion_effective_parts[] = 'Behavior triggers enabled';
    } else {
        $flosc_companion_effective_parts[] = 'Behavior triggers disabled';
    }

    if ($flosc_companion_remember_state) {
        $flosc_companion_effective_parts[] = 'Open state remembered (' . $flosc_companion_storage_label . ')';
    } else {
        $flosc_companion_effective_parts[] = 'Open state not remembered';
    }

    if ($flosc_companion_cooldown_ms > 0 && (!empty($flosc_flow_settings['companion_launch_on_exit_intent']) || !empty($flosc_flow_settings['companion_launch_on_scroll_threshold']))) {
        $flosc_companion_effective_parts[] = 'Trigger cooldown active';
    } elseif ($flosc_companion_cooldown_ms > 0) {
        $flosc_companion_effective_parts[] = 'Trigger cooldown set but triggers are off';
    }
}

$flosc_companion_effective_label = implode(' · ', $flosc_companion_effective_parts);
$flosc_companion_numeric_limits = [
    'panel_width_min' => 280,
    'panel_width_max' => 900,
    'panel_height_min' => 320,
    'panel_height_max' => 1200,
    'auto_open_delay_min_ms' => 0,
    'auto_open_delay_max_ms' => 60000,
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
if ($flosc_companion_numeric_limits['auto_open_delay_max_ms'] < $flosc_companion_numeric_limits['auto_open_delay_min_ms']) {
    $flosc_companion_numeric_limits['auto_open_delay_max_ms'] = $flosc_companion_numeric_limits['auto_open_delay_min_ms'];
}
if ($flosc_companion_numeric_limits['trigger_cooldown_max_ms'] < $flosc_companion_numeric_limits['trigger_cooldown_min_ms']) {
    $flosc_companion_numeric_limits['trigger_cooldown_max_ms'] = $flosc_companion_numeric_limits['trigger_cooldown_min_ms'];
}
$flosc_companion_limits_label = sprintf(
    'Panel %d-%dpx W · %d-%dpx H · Auto delay %d-%dms · Cooldown %d-%dms',
    $flosc_companion_numeric_limits['panel_width_min'],
    $flosc_companion_numeric_limits['panel_width_max'],
    $flosc_companion_numeric_limits['panel_height_min'],
    $flosc_companion_numeric_limits['panel_height_max'],
    $flosc_companion_numeric_limits['auto_open_delay_min_ms'],
    $flosc_companion_numeric_limits['auto_open_delay_max_ms'],
    $flosc_companion_numeric_limits['trigger_cooldown_min_ms'],
    $flosc_companion_numeric_limits['trigger_cooldown_max_ms']
);

$flosc_diagnostics_stem = $flosc_selected_ivr ? sanitize_key(pathinfo($flosc_selected_ivr, PATHINFO_FILENAME)) : 'global';
$flosc_diagnostics_start = 'start_' . $flosc_diagnostics_stem;
$flosc_diagnostics_end = 'end_' . $flosc_diagnostics_stem;
$flosc_prompt_panel_counts = [
    'visitor' => $flosc_visitor_pills,
    'guest' => $flosc_guest_pills,
    'member' => $flosc_member_pills,
];
$flosc_diagnostics_flow_name = trim((string) ($flosc_flow_settings['identity']['name'] ?? ''));
$flosc_diagnostics_flow_label = $flosc_diagnostics_flow_name !== '' ? $flosc_diagnostics_flow_name : ($flosc_selected_ivr ?: 'this flow');

$flosc_ai_provider   = $flosc_flow_settings['ai_provider'] ?? 'ivr';
$flosc_ai_labels     = function_exists( 'flosc_chat_provider_labels' )
    ? flosc_chat_provider_labels()
    : [
        'ivr'       => 'IVR / Scripted only',
        'anthropic' => 'Anthropic Claude',
        'openai'    => 'OpenAI',
        'xai'       => 'xAI Grok',
        'gemini'    => 'Google Gemini',
    ];
$flosc_ai_label = $flosc_ai_labels[ $flosc_ai_provider ] ?? ucfirst( $flosc_ai_provider );
if ( $flosc_ai_provider === 'anthropic' ) {
    $flosc_ai_model  = $flosc_flow_settings['ai_model'] ?? flosc_get_setting( 'ai_model', 'claude-sonnet-4-6' );
    $flosc_ai_label .= ' (' . esc_html( $flosc_ai_model ) . ')';
}

// ── Helper: render a phase card ───────────────────────────────────────────────
function flosc_flow_card( $letter, $flosc_phase_name, $subtitle, $rows ) {
    $phase_class = strtolower( $letter );
    echo '<div class="flosc-flow-card flosc-flow-card--' . esc_attr( $phase_class ) . '">';
    echo '<div class="flosc-flow-card__header">';
    echo '<span class="flosc-flow-card__badge flosc-flow-card__badge--' . esc_attr( $phase_class ) . '">' . esc_html( $letter ) . '</span>';
    echo '<div>';
    echo '<div class="flosc-flow-card__phase">' . esc_html( strtoupper( $flosc_phase_name ) ) . '</div>';
    echo '<div class="flosc-flow-card__subtitle">' . esc_html( $subtitle ) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '<table class="flosc-flow-card__table">';
    foreach ( $rows as $row ) {
        echo '<tr>';
        echo '<td class="flosc-flow-card__label">' . wp_kses_post( $row['label'] ) . '</td>';
        echo '<td class="flosc-flow-card__action">';
        if ( ! empty( $row['edit_url'] ) ) {
            echo '<a href="' . esc_url( $row['edit_url'] ) . '" class="button button-small flosc-flow-card__action-link">' . esc_html( $row['edit_label'] ?? 'Edit →' ) . '</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
}

// ── Output ────────────────────────────────────────────────────────────────────
?>
<div class="flosc-flow-overview">

    <div class="flosc-flow-overview-header">
        <h2 class="flosc-flow-overview-title">
            <span>🗺 FLOSC Flow Overview</span>
            <a href="<?php echo esc_url($flosc_flow_docs_url); ?>" class="flosc-docs-link">Docs</a>
            <a href="<?php echo esc_url($flosc_flow_docs_inventory_url); ?>" class="flosc-docs-link">Parameter Docs</a>
        </h2>
        <p class="flosc-flow-overview-summary">Read-only snapshot of the five flow phases for <strong><?php echo esc_html( $flosc_selected_ivr ?: 'this flow' ); ?></strong>. Click any Edit button to jump to that tab.</p>

        <div class="flosc-view-toggle-row">
            <a href="<?php echo esc_url($flosc_flow_single_url); ?>" class="button <?php echo esc_attr($flosc_flow_view === 'single' ? 'button-primary' : ''); ?>"><?php echo esc_html__('Overview', 'flosc'); ?></a>
            <a href="<?php echo esc_url($flosc_flow_all_url); ?>" class="button <?php echo esc_attr($flosc_flow_view === 'all' ? 'button-primary' : ''); ?>"><?php echo esc_html__('Portability', 'flosc'); ?></a>
        </div>
        <p class="description flosc-flow-view-hint">
            <?php if ($flosc_flow_view === 'all') : ?>
                <?php echo esc_html__('Portability: manage all flow files. Apply always targets the current flow in Switch Flow above.', 'flosc'); ?>
            <?php else : ?>
                <?php echo esc_html__('Overview: phase snapshot for the current flow selected in Switch Flow.', 'flosc'); ?>
            <?php endif; ?>
        </p>
    </div>

    <?php
    // ── F — Freeline ──────────────────────────────────────────────────────────
    flosc_flow_card( 'F', 'Freeline', 'What visitors see before logging in', [
        [
            'label'      => $flosc_quiz_label_html,
            'edit_url'   => $flosc_base_url . 'quiz',
            'edit_label' => $flosc_edit_quiz_label,
        ],
        [
            'label'      => 'Visitor pills: ' . $flosc_visitor_pills . ' configured',
            'edit_url'   => $flosc_base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
        [
            'label'      => 'IVR file: ' . $flosc_ivr_file_label,
            'edit_url'   => $flosc_base_url . 'ivr-messages',
            'edit_label' => 'Edit IVR →',
        ],
    ] );

    // ── L — Login ─────────────────────────────────────────────────────────────
    flosc_flow_card( 'L', 'Login', 'Account gate', [
        [
            'label'      => 'SSO: ' . esc_html( $flosc_sso_label ),
            'edit_url'   => $flosc_base_url . 'sso',
            'edit_label' => 'Edit SSO →',
        ],
    ] );

    // ── O — Offer ─────────────────────────────────────────────────────────────
    // v8.1.0: Member levels summary
    $flosc_ml_registry = $flosc_flow_settings['member_levels'] ?? [];
    $flosc_ml_count    = count( array_filter( $flosc_ml_registry, fn( $l ) => ! empty( $l['slug'] ?? '' ) ) );
    $flosc_ml_names    = array_map( fn( $l ) => $l['name'] ?: ( $l['slug'] ?? '?' ), array_filter( $flosc_ml_registry, fn( $l ) => ! empty( $l['slug'] ?? '' ) ) );
    $flosc_ml_label    = $flosc_ml_count ? $flosc_ml_count . ' level' . ( $flosc_ml_count !== 1 ? 's' : '' ) . ' (' . implode( ', ', $flosc_ml_names ) . ')' : 'None configured';

    flosc_flow_card( 'O', 'Offer', 'Upgrade prompts for guests', [
        [
            'label'      => 'Member Levels: ' . esc_html( $flosc_ml_label ),
            'edit_url'   => $flosc_base_url . 'member-levels',
            'edit_label' => 'Edit Levels →',
        ],
        [
            'label'      => 'Offers: ' . esc_html( $flosc_offers_label ?: 'None configured' ),
            'edit_url'   => $flosc_base_url . 'offers',
            'edit_label' => 'Edit Offers →',
        ],
        [
            'label'      => 'Guest pills: ' . $flosc_guest_pills . ' configured',
            'edit_url'   => $flosc_base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
    ] );

    // ── S — Sale ──────────────────────────────────────────────────────────────
    $flosc_stripe_status = $flosc_stripe_cfg
        ? '✅ configured' . ( $flosc_stripe_mode ? ' (' . esc_html( $flosc_stripe_mode ) . ')' : '' )
        : '❌ not configured';
    $flosc_paypal_status = $flosc_paypal_cfg
        ? '✅ configured' . ( $flosc_paypal_mode ? ' (' . esc_html( $flosc_paypal_mode ) . ')' : '' )
        : '❌ not configured';

    flosc_flow_card( 'S', 'Sale', 'Payment processing', [
        [
            'label'      => 'Stripe: ' . $flosc_stripe_status,
            'edit_url'   => $flosc_base_url . 'payments',
            'edit_label' => 'Edit Pay →',
        ],
        [
            'label'      => 'PayPal: ' . $flosc_paypal_status,
            'edit_url'   => $flosc_base_url . 'payments',
            'edit_label' => 'Edit Pay →',
        ],
    ] );

    // ── C — Content ───────────────────────────────────────────────────────────
    flosc_flow_card( 'C', 'Content', 'What learners see after purchase', [
        [
            'label'      => 'Companion: ' . esc_html($flosc_companion_mode_label . ' · ' . $flosc_companion_status),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Launch policy: ' . esc_html($flosc_companion_auto . ' · ' . $flosc_companion_exit . ' · ' . $flosc_companion_scroll . ' · ' . $flosc_companion_cooldown_label),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Effective behavior: ' . esc_html($flosc_companion_effective_label),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Active limits: ' . esc_html($flosc_companion_limits_label),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Lessons: ' . esc_html( $flosc_lessons_label ),
            'edit_url'   => $flosc_base_url . 'lessons',
            'edit_label' => 'Edit Lessons →',
        ],
        [
            'label'      => 'Member pills: ' . $flosc_member_pills . ' configured',
            'edit_url'   => $flosc_base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
        [
            'label'      => 'AI: ' . esc_html( $flosc_ai_label ),
            'edit_url'   => $flosc_base_url . 'ai',
            'edit_label' => 'Edit AI →',
        ],
    ] );

    echo '<div class="flosc-flow-diagnostics">';
    echo '<div id="' . esc_attr( $flosc_diagnostics_start ) . '" class="flosc-flow-diagnostics__anchor"></div>';
    echo '<h3 class="flosc-flow-diagnostics__title">Diagnostics <a href="' . esc_url( $flosc_flow_docs_url ) . '" class="flosc-docs-link">Docs</a></h3>';
    echo '<p class="flosc-flow-diagnostics__hashes">Open <code>#' . esc_html( $flosc_diagnostics_start ) . '</code> to jump here, and <code>#' . esc_html( $flosc_diagnostics_end ) . '</code> to jump past the diagnostics block.</p>';
    echo '<ul class="flosc-flow-diagnostics__list">';
    echo '<li><strong>Flow:</strong> ' . esc_html( $flosc_diagnostics_flow_label ) . ' <code>' . esc_html( $flosc_selected_ivr ?: '(no IVR selected)' ) . '</code></li>';
    echo '<li><strong>Prompt panels:</strong> Visitor ' . esc_html( (string) $flosc_prompt_panel_counts['visitor'] ) . ', Guest ' . esc_html( (string) $flosc_prompt_panel_counts['guest'] ) . ', Member ' . esc_html( (string) $flosc_prompt_panel_counts['member'] ) . '</li>';
    echo '<li><strong>AI:</strong> ' . esc_html( $flosc_ai_label ) . '</li>';
    echo '<li><strong>Companion:</strong> ' . esc_html( $flosc_companion_mode_label . ' · ' . $flosc_companion_status . ' · ' . $flosc_companion_effective_label ) . '</li>';
    echo '</ul>';
    echo '<div id="' . esc_attr( $flosc_diagnostics_end ) . '" class="flosc-flow-diagnostics__anchor"></div>';
    echo '</div>';
    ?>

    <?php if ($flosc_flow_view === 'all'): ?>
        <?php
        /*
         * HTML forbids nested forms, and this template renders inside
         * #flosc-settings-form (opened in admin/settings.php). A second <form> start
         * tag is discarded by the parser, and the next </form> closes the outer form
         * instead — so the Portability form would lose its id, its
         * enctype="multipart/form-data" and its own nonce, and the kit would post
         * through the settings form with no multipart encoding and therefore no
         * files. Closing the settings form here makes the Portability form and the
         * file-table action forms siblings of it rather than children.
         *
         * Save Settings in admin/settings.php stays bound through
         * form="flosc-settings-form", and the shared flag keeps that file from
         * printing a second </form>. Same mechanism as admin/ai-configuration.php.
         * This view carries no flow_* settings fields, so nothing is orphaned.
         */
        if (empty($GLOBALS['flosc_settings_form_closed_early'])) {
            echo '</form>';
            $GLOBALS['flosc_settings_form_closed_early'] = true;
        }
    ?>
    <div class="flosc-info-box flosc-ivr-info-box">
        <h3 class="flosc-flow-portability-title">
            <span><?php echo esc_html__('Flow Portability', 'flosc'); ?></span>
            <a href="<?php echo esc_url($flosc_portability_docs_url); ?>" class="flosc-docs-link"><?php echo esc_html__('Docs', 'flosc'); ?></a>
        </h3>
        <p class="flosc-ivr-info-box__lead">
            <?php echo esc_html__( 'Portability for floscFlowConfiguration: floscFlowIvr + journey wiring (Settings YAML) in .md; floscFlowContent as .tsv / WXR / media. floscFlowPersonality and floscFlowAiPolicy (no secrets) can live in YAML; API keys stay in floscAvailableProviders.', 'flosc' ); ?>
        </p>

        <div class="flosc-portability-split" role="group" aria-label="<?php echo esc_attr__( 'Configuration layers', 'flosc' ); ?>">
            <div class="flosc-portability-split__card flosc-portability-split__card--personality">
                <h4 class="flosc-portability-split__heading"><?php echo esc_html__( 'floscFlowIvr + wiring', 'flosc' ); ?></h4>
                <p class="flosc-portability-split__body">
                    <?php echo esc_html__( 'Scripted input→output pairs and journey wiring (levels, offers, pointers). Optional floscFlowPersonality fields in Settings YAML. File: .md', 'flosc' ); ?>
                </p>
                <p class="flosc-portability-split__meta">
                    <span class="flosc-portability-chip flosc-portability-chip--personality"><?php echo esc_html__( '.md', 'flosc' ); ?></span>
                    <?php echo esc_html__( 'Create needs .md. Apply merges into the current floscFlow.', 'flosc' ); ?>
                </p>
            </div>
            <div class="flosc-portability-split__card flosc-portability-split__card--dataset">
                <h4 class="flosc-portability-split__heading"><?php echo esc_html__( 'floscFlowContent', 'flosc' ); ?></h4>
                <p class="flosc-portability-split__body">
                    <?php echo esc_html__( 'Materials: posts (WXR), media (PDF and files), DA1 catalogs (.tsv). Pointed at by journey wiring; not the AI voice.', 'flosc' ); ?>
                </p>
                <p class="flosc-portability-split__meta">
                    <span class="flosc-portability-chip flosc-portability-chip--dataset"><?php echo esc_html__( '.tsv', 'flosc' ); ?></span>
                    <span class="flosc-portability-chip flosc-portability-chip--dataset"><?php echo esc_html__( '.xml WXR', 'flosc' ); ?></span>
                    <span class="flosc-portability-chip flosc-portability-chip--dataset"><?php echo esc_html__( 'PDF / media', 'flosc' ); ?></span>
                    <?php echo esc_html__( 'Apply attaches content to the current floscFlow (or Create with a .md in the same drop).', 'flosc' ); ?>
                </p>
            </div>
        </div>

        <div class="flosc-flow-portability-target">
            <div class="flosc-flow-portability-target__line">
                <strong><?php echo esc_html__('Current flow (Apply target):', 'flosc'); ?></strong>
                <code><?php echo esc_html($flosc_selected_ivr ?: '(none — use Switch Flow)'); ?></code>
            </div>
            <p class="flosc-flow-portability-target__hint">
                <?php echo esc_html__('Switch Flow picks which flow receives Apply. Personality (.md) and data set files can travel together; secrets never come from files.', 'flosc'); ?>
            </p>
        </div>

        <ul class="flosc-flow-portability-checklist">
            <li>
                <strong><?php echo esc_html__( 'Personality (.md):', 'flosc' ); ?></strong>
                <?php echo esc_html__( 'Creates the flow or merges voice/settings. Listed under AI personalities below.', 'flosc' ); ?>
            </li>
            <li>
                <strong><?php echo esc_html__( 'Data — catalogs (.tsv):', 'flosc' ); ?></strong>
                <?php echo esc_html__( 'Up to 10; assigned to this flow’s data set.', 'flosc' ); ?>
                <a href="<?php echo esc_url( $flosc_base_url . 'da1' ); ?>"><?php echo esc_html__( 'DA1', 'flosc' ); ?></a>
            </li>
            <li>
                <strong><?php echo esc_html__( 'Data — posts (WXR .xml):', 'flosc' ); ?></strong>
                <?php echo esc_html__( 'Staged on the data set list; Import posts or Tools → Import loads WordPress content.', 'flosc' ); ?>
                <a href="<?php echo esc_url( admin_url( 'import.php' ) ); ?>"><?php echo esc_html__( 'Tools → Import', 'flosc' ); ?></a>
            </li>
            <li>
                <strong><?php echo esc_html__( 'Data — media:', 'flosc' ); ?></strong>
                <?php echo esc_html__( 'PDF / images / audio into Media Library, tracked on this flow’s data set.', 'flosc' ); ?>
                <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php echo esc_html__( 'Media Library', 'flosc' ); ?></a>
            </li>
            <li>
                <strong><?php echo esc_html__( 'After posts exist:', 'flosc' ); ?></strong>
                <a href="<?php echo esc_url( $flosc_base_url . 'content' ); ?>"><?php echo esc_html__( 'Content tab', 'flosc' ); ?></a>
                <?php echo esc_html__( 'wires category/tag targets — that is data wiring, not personality.', 'flosc' ); ?>
            </li>
        </ul>

        <?php
        /*
         * The kit stylesheet and script are enqueued for this screen in
         * FLOSC_Admin::enqueue_admin_assets() — handle 'flosc-portability-admin'.
         * Nothing is printed here, so the page has one copy of each asset and
         * the drop zone is fully styled before the browser paints the form.
         */
        ?>
        <form method="post" action="<?php echo esc_url($flosc_flow_all_url); ?>" enctype="multipart/form-data" class="flosc-portability-kit-form" id="flosc-kit-upload-form">
            <?php wp_nonce_field('flosc_portability_kit'); ?>
            <input type="hidden" name="flosc_upload_redirect_tab" value="flow">
            <input type="hidden" name="flosc_upload_redirect_view" value="all">
            <input type="hidden" name="flosc_working_ivr" value="<?php echo esc_attr($flosc_selected_ivr); ?>">

            <div class="flosc-dropzone flosc-dropzone--kit" id="flosc-kit-dropzone" aria-controls="flosc-portability-file-list">
                <?php
                /*
                 * The file input carries the multipart payload and stays in the tab
                 * order; the stylesheet hides it from view. The label below covers the
                 * whole zone, so a click anywhere in the box opens the picker without a
                 * second control and without the browser's native file-picker button.
                 */
                ?>
                <input
                    type="file"
                    name="flosc_kit_files[]"
                    id="flosc-kit-file-input"
                    class="flosc-sr-only screen-reader-text"
                    accept=".md,.tsv,.xml,.pdf,.jpg,.jpeg,.png,.gif,.webp,.mp3,.mp4,.m4a,.wav,.ogg,.webm,text/markdown,text/plain,text/tab-separated-values,text/xml,application/xml,application/pdf,image/*,audio/*,video/mp4"
                    multiple
                >
                <label class="flosc-dropzone__label" for="flosc-kit-file-input">
                    <span class="flosc-dropzone__surface">
                        <span class="flosc-dropzone__glyph" aria-hidden="true">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                <path d="M12 16V4M12 4l-4 4M12 4l4 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span id="flosc-dropzone-title-idle" class="flosc-dropzone__title"><?php echo esc_html__('Drop files here or click to select', 'flosc'); ?></span>
                        <span id="flosc-dropzone-title-drag" class="flosc-dropzone__title flosc-dropzone__title--active" hidden><?php echo esc_html__('Release to stage these files', 'flosc'); ?></span>
                        <span id="flosc-dropzone-hint" class="flosc-dropzone__hint"><?php echo esc_html__('Personality: one .md. Data set: .tsv (max 10), WXR .xml (max 5), media (max 10). Then Create or Apply.', 'flosc'); ?></span>
                    </span>
                </label>
            </div>

            <div class="flosc-portability-file-panel" id="flosc-portability-file-panel">
                <div class="flosc-portability-file-panel__head">
                    <strong><?php echo esc_html__('Selected files (personality + data)', 'flosc'); ?></strong>
                    <span class="description" id="flosc-portability-file-count"><?php echo esc_html__('None selected', 'flosc'); ?></span>
                    <button type="button" class="button-link" id="flosc-portability-clear-files" disabled><?php echo esc_html__('Clear', 'flosc'); ?></button>
                </div>
                <ul class="flosc-portability-file-list" id="flosc-portability-file-list" aria-live="polite"></ul>
            </div>

            <div class="flosc-flow-portability-actions-row">
                <button type="submit" name="flosc_portability_submit" value="create" class="button button-primary flosc-flow-portability-primary-btn" id="flosc-portability-btn-create">
                    <?php echo esc_html__('Create new flow', 'flosc'); ?>
                </button>
                <button type="submit" name="flosc_portability_submit" value="apply" class="button flosc-flow-portability-primary-btn" id="flosc-portability-btn-apply" <?php disabled($flosc_selected_ivr === ''); ?>>
                    <?php echo esc_html__('Apply to current flow', 'flosc'); ?>
                </button>
            </div>
            <p class="description">
                <?php echo esc_html__('Create = new flow from personality (.md); data files in the same drop attach as that flow’s data set. Apply = merge personality and/or attach data to the current flow.', 'flosc'); ?>
            </p>
        </form>

        <?php
        if ( ! function_exists( 'flosc_portability_get_pack_assets' ) ) {
            require_once FLOSC_PLUGIN_DIR . 'admin/ivr-upload-handler.php';
        }
        $flosc_pack_assets = function_exists( 'flosc_portability_get_pack_assets' )
            ? flosc_portability_get_pack_assets( $flosc_selected_ivr )
            : array(
                'wxr'      => array(),
                'media'    => array(),
                'catalogs' => array(),
            );
        $flosc_da1_index = get_option( 'flosc_da1_catalogs', array() );
        if ( ! is_array( $flosc_da1_index ) ) {
            $flosc_da1_index = array();
        }
        $flosc_pack_has_rows = ( $flosc_selected_ivr !== '' )
            && (
                ! empty( $flosc_pack_assets['catalogs'] )
                || ! empty( $flosc_pack_assets['wxr'] )
                || ! empty( $flosc_pack_assets['media'] )
            );
        ?>
        <div class="flosc-portability-section flosc-portability-section--dataset">
        <div class="flosc-ivr-file-actions flosc-ivr-file-actions--table-toolbar">
            <div class="flosc-portability-section__title-block">
                <strong><?php echo esc_html__( 'Data set for current flow', 'flosc' ); ?></strong>
                <span class="description"><?php echo esc_html__( 'Catalogs, posts (WXR), and media only — not the AI voice.', 'flosc' ); ?></span>
            </div>
            <a href="<?php echo esc_url($flosc_flow_all_url); ?>" class="button button-small"><?php echo esc_html__('Refresh list', 'flosc'); ?></a>
        </div>
        <?php if ( $flosc_selected_ivr === '' ) : ?>
            <p class="description"><?php echo esc_html__( 'Select a flow in Switch Flow to see its data set (catalogs, WXR, media).', 'flosc' ); ?></p>
        <?php elseif ( ! $flosc_pack_has_rows ) : ?>
            <p class="description"><?php echo esc_html__( 'No data set files on this flow yet. Drop .tsv / WXR / media above (Apply), or Create with a .md plus data files.', 'flosc' ); ?></p>
        <?php else : ?>
        <table class="widefat striped flosc-ivr-file-table flosc-pack-assets-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Data type', 'flosc' ); ?></th>
                    <th><?php echo esc_html__( 'File / asset', 'flosc' ); ?></th>
                    <th class="flosc-ivr-col-status"><?php echo esc_html__( 'Status', 'flosc' ); ?></th>
                    <th class="flosc-ivr-col-actions"><?php echo esc_html__( 'Actions', 'flosc' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $flosc_pack_assets['catalogs'] as $flosc_pack_cat_key ) :
                $flosc_pack_cat_key = sanitize_key( (string) $flosc_pack_cat_key );
                if ( $flosc_pack_cat_key === '' ) {
                    continue;
                }
                $flosc_pack_cat_label = isset( $flosc_da1_index[ $flosc_pack_cat_key ]['label'] )
                    ? (string) $flosc_da1_index[ $flosc_pack_cat_key ]['label']
                    : $flosc_pack_cat_key;
                $flosc_pack_cat_file = isset( $flosc_da1_index[ $flosc_pack_cat_key ]['filename'] )
                    ? (string) $flosc_da1_index[ $flosc_pack_cat_key ]['filename']
                    : ( 'flosc_da1_catalog_' . $flosc_pack_cat_key . '.tsv' );
                $flosc_da1_url = add_query_arg(
                    array(
                        'page'        => 'flosc-settings',
                        'tab'         => 'da1',
                        'ivr'         => $flosc_selected_ivr,
                        'catalog_key' => $flosc_pack_cat_key,
                    ),
                    admin_url( 'admin.php' )
                );
                ?>
                <tr>
                    <td><span class="flosc-portability-chip flosc-portability-chip--dataset"><?php echo esc_html__( 'Catalog', 'flosc' ); ?></span></td>
                    <td>
                        <code><?php echo esc_html( $flosc_pack_cat_file ); ?></code>
                        <span class="description"> · <?php echo esc_html( $flosc_pack_cat_label ); ?></span>
                    </td>
                    <td><?php echo esc_html__( 'Assigned', 'flosc' ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( $flosc_da1_url ); ?>" class="button button-small"><?php echo esc_html__( 'Open DA1', 'flosc' ); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ( $flosc_pack_assets['wxr'] as $flosc_pack_wxr ) :
                if ( ! is_array( $flosc_pack_wxr ) ) {
                    continue;
                }
                $flosc_wxr_name   = isset( $flosc_pack_wxr['filename'] ) ? sanitize_file_name( (string) $flosc_pack_wxr['filename'] ) : '';
                $flosc_wxr_status = isset( $flosc_pack_wxr['status'] ) ? sanitize_key( (string) $flosc_pack_wxr['status'] ) : 'staged';
                $flosc_wxr_url    = isset( $flosc_pack_wxr['url'] ) ? (string) $flosc_pack_wxr['url'] : '';
                $flosc_wxr_path_ok = ! empty( $flosc_pack_wxr['path'] ) && is_string( $flosc_pack_wxr['path'] ) && file_exists( $flosc_pack_wxr['path'] );
                if ( $flosc_wxr_name === '' ) {
                    continue;
                }
                $flosc_status_label = ( 'imported' === $flosc_wxr_status )
                    ? __( 'Imported', 'flosc' )
                    : ( $flosc_wxr_path_ok ? __( 'Staged — ready to import', 'flosc' ) : __( 'Missing on disk', 'flosc' ) );
                ?>
                <tr>
                    <td><span class="flosc-portability-chip flosc-portability-chip--dataset"><?php echo esc_html__( 'Posts (WXR)', 'flosc' ); ?></span></td>
                    <td><code><?php echo esc_html( $flosc_wxr_name ); ?></code></td>
                    <td><?php echo esc_html( $flosc_status_label ); ?></td>
                    <td>
                        <div class="flosc-ivr-file-action-group">
                            <?php if ( $flosc_wxr_url !== '' ) : ?>
                                <a href="<?php echo esc_url( $flosc_wxr_url ); ?>" class="button button-small" download><?php echo esc_html__( 'Download', 'flosc' ); ?></a>
                            <?php endif; ?>
                            <?php if ( $flosc_wxr_path_ok && 'imported' !== $flosc_wxr_status ) : ?>
                            <form method="post" action="<?php echo esc_url( $flosc_flow_all_url ); ?>" class="flosc-ivr-inline-form">
                                <?php wp_nonce_field( 'flosc_portability_pack' ); ?>
                                <input type="hidden" name="flosc_working_ivr" value="<?php echo esc_attr( $flosc_selected_ivr ); ?>">
                                <input type="hidden" name="flosc_pack_filename" value="<?php echo esc_attr( $flosc_wxr_name ); ?>">
                                <input type="hidden" name="flosc_portability_pack_action" value="import_wxr">
                                <button type="submit" class="button button-small button-primary"><?php echo esc_html__( 'Import posts', 'flosc' ); ?></button>
                            </form>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( admin_url( 'import.php' ) ); ?>" class="button button-small"><?php echo esc_html__( 'Tools → Import', 'flosc' ); ?></a>
                            <form method="post" action="<?php echo esc_url( $flosc_flow_all_url ); ?>" class="flosc-ivr-inline-form flosc-ivr-inline-form--warn" data-confirm-message="<?php echo esc_attr__( 'Remove this WXR from the pack list? Staged file on disk will be deleted.', 'flosc' ); ?>">
                                <?php wp_nonce_field( 'flosc_portability_pack' ); ?>
                                <input type="hidden" name="flosc_working_ivr" value="<?php echo esc_attr( $flosc_selected_ivr ); ?>">
                                <input type="hidden" name="flosc_pack_filename" value="<?php echo esc_attr( $flosc_wxr_name ); ?>">
                                <input type="hidden" name="flosc_portability_pack_action" value="remove_wxr">
                                <button type="submit" class="button button-small"><?php echo esc_html__( 'Remove', 'flosc' ); ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ( $flosc_pack_assets['media'] as $flosc_pack_media ) :
                if ( ! is_array( $flosc_pack_media ) ) {
                    continue;
                }
                $flosc_media_id   = isset( $flosc_pack_media['attachment_id'] ) ? (int) $flosc_pack_media['attachment_id'] : 0;
                $flosc_media_name = isset( $flosc_pack_media['filename'] ) ? (string) $flosc_pack_media['filename'] : '';
                $flosc_media_url  = isset( $flosc_pack_media['url'] ) ? (string) $flosc_pack_media['url'] : '';
                if ( $flosc_media_id > 0 && $flosc_media_url === '' ) {
                    $flosc_media_url = (string) wp_get_attachment_url( $flosc_media_id );
                }
                if ( $flosc_media_id <= 0 && $flosc_media_name === '' ) {
                    continue;
                }
                $flosc_edit_media = $flosc_media_id > 0 ? get_edit_post_link( $flosc_media_id, 'raw' ) : '';
                $flosc_media_alive = $flosc_media_id > 0 && get_post( $flosc_media_id );
                ?>
                <tr>
                    <td><span class="flosc-portability-chip flosc-portability-chip--dataset"><?php echo esc_html__( 'Media', 'flosc' ); ?></span></td>
                    <td>
                        <code><?php echo esc_html( $flosc_media_name !== '' ? $flosc_media_name : ( 'attachment:' . $flosc_media_id ) ); ?></code>
                    </td>
                    <td><?php echo esc_html( $flosc_media_alive ? __( 'In Media Library', 'flosc' ) : __( 'Missing attachment', 'flosc' ) ); ?></td>
                    <td>
                        <div class="flosc-ivr-file-action-group">
                            <?php if ( $flosc_media_url !== '' ) : ?>
                                <a href="<?php echo esc_url( $flosc_media_url ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'View', 'flosc' ); ?></a>
                            <?php endif; ?>
                            <?php if ( $flosc_edit_media ) : ?>
                                <a href="<?php echo esc_url( $flosc_edit_media ); ?>" class="button button-small"><?php echo esc_html__( 'Edit media', 'flosc' ); ?></a>
                            <?php endif; ?>
                            <form method="post" action="<?php echo esc_url( $flosc_flow_all_url ); ?>" class="flosc-ivr-inline-form flosc-ivr-inline-form--warn" data-confirm-message="<?php echo esc_attr__( 'Unlink this media from the pack list? The file stays in the Media Library.', 'flosc' ); ?>">
                                <?php wp_nonce_field( 'flosc_portability_pack' ); ?>
                                <input type="hidden" name="flosc_working_ivr" value="<?php echo esc_attr( $flosc_selected_ivr ); ?>">
                                <input type="hidden" name="flosc_pack_attachment_id" value="<?php echo esc_attr( (string) $flosc_media_id ); ?>">
                                <input type="hidden" name="flosc_portability_pack_action" value="remove_media">
                                <button type="submit" class="button button-small"><?php echo esc_html__( 'Unlink', 'flosc' ); ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php echo esc_html__( 'This list is the data set only. Import posts needs the WordPress Importer for one-click import; Tools → Import works with a downloaded WXR. Personality files are in the table below.', 'flosc' ); ?>
        </p>
        <?php endif; ?>
        </div><!-- .flosc-portability-section--dataset -->

        <div class="flosc-portability-section flosc-portability-section--personality">
        <div class="flosc-ivr-file-actions flosc-ivr-file-actions--table-toolbar">
            <div class="flosc-portability-section__title-block">
                <strong><?php echo esc_html__( 'AI personalities (IVR flows)', 'flosc' ); ?></strong>
                <span class="description"><?php echo esc_html__( 'Voice and freeline .md files — not catalogs, posts, or media.', 'flosc' ); ?></span>
            </div>
            <a href="<?php echo esc_url($flosc_flow_all_url); ?>" class="button button-small"><?php echo esc_html__('Refresh list', 'flosc'); ?></a>
        </div>

        <table class="widefat striped flosc-ivr-file-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('File', 'flosc'); ?></th>
                    <th class="flosc-ivr-col-status"><?php echo esc_html__('Status', 'flosc'); ?></th>
                    <th class="flosc-ivr-col-actions"><?php echo esc_html__('Actions', 'flosc'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($flosc_available_ivr_files as $flosc_ivr_filename):
                $flosc_is_active_row = ($flosc_ivr_filename === $flosc_selected_ivr);
                $flosc_edit_url = esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . rawurlencode($flosc_ivr_filename) . '&view=single'));
                $flosc_download_url = wp_nonce_url(
                    esc_url(add_query_arg([
                        'page' => 'flosc-settings',
                        'tab' => 'flow',
                        'ivr' => $flosc_selected_ivr,
                        'view' => 'all',
                        'flosc_download_ivr' => $flosc_ivr_filename,
                    ], admin_url('admin.php'))),
                    'flosc_download_ivr_' . $flosc_ivr_filename
                );
            ?>
                <tr class="<?php echo esc_attr($flosc_is_active_row ? 'flosc-ivr-row--active' : ''); ?>">
                    <td><code><?php echo esc_html($flosc_ivr_filename); ?></code></td>
                    <td><?php echo esc_html($flosc_is_active_row ? __('Current flow', 'flosc') : __('Managed', 'flosc')); ?></td>
                    <td>
                        <div class="flosc-ivr-file-action-group">
                            <a href="<?php echo esc_url($flosc_edit_url); ?>" class="button button-small"><?php echo esc_html__('Edit', 'flosc'); ?></a>
                            <a href="<?php echo esc_url($flosc_download_url); ?>" class="button button-small"><?php echo esc_html__('Download', 'flosc'); ?></a>
                            <form method="post" action="<?php echo esc_url($flosc_flow_all_url); ?>" class="flosc-ivr-inline-form">
                                <?php wp_nonce_field('flosc_duplicate_ivr_file'); ?>
                                <input type="hidden" name="duplicate_ivr_file" value="<?php echo esc_attr($flosc_ivr_filename); ?>">
                                <button type="submit" name="flosc_duplicate_ivr_file" class="button button-small"><?php echo esc_html__('Duplicate', 'flosc'); ?></button>
                            </form>
                            <form method="post" action="<?php echo esc_url($flosc_flow_all_url); ?>" class="flosc-ivr-inline-form">
                                <?php wp_nonce_field('flosc_import_selected_ivr_file'); ?>
                                <input type="hidden" name="import_ivr_file" value="<?php echo esc_attr($flosc_ivr_filename); ?>">
                                <button type="submit" name="flosc_import_selected_ivr_file" class="button button-small" title="<?php echo esc_attr__('Merge this file into the current flow', 'flosc'); ?>"><?php echo esc_html__('Apply', 'flosc'); ?></button>
                            </form>
                            <form method="post" action="<?php echo esc_url($flosc_flow_all_url); ?>" class="flosc-ivr-inline-form flosc-ivr-inline-form--warn" data-confirm-message="<?php echo esc_attr(sprintf(/* translators: %s filename */ __('Delete IVR file %s? This cannot be undone from this panel.', 'flosc'), $flosc_ivr_filename)); ?>">
                                <?php wp_nonce_field('flosc_delete_ivr_file'); ?>
                                <input type="hidden" name="delete_ivr_file" value="<?php echo esc_attr($flosc_ivr_filename); ?>">
                                <button type="submit" name="flosc_delete_ivr_file" class="button button-small"><?php echo esc_html__('Delete', 'flosc'); ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description flosc-flow-portability-actions-help">
            <?php echo esc_html__( 'Table Apply: merge that personality (.md) into the current flow (same merge rules as the drop zone). Data set files are managed in the Data set table above.', 'flosc' ); ?>
        </p>
        </div><!-- .flosc-portability-section--personality -->
    </div>

    <?php
    /*
     * No confirm handler here. assets/js/flosc-admin-events.js is enqueued on
     * every FLOSC admin screen and already confirms any submit of a
     * form[data-confirm-message]. A second listener on document does not
     * override the first — both run, both call confirm(), and the floscAdmin
     * is asked twice to delete one flow.
     */
    ?>
    <?php endif; ?>

</div>
<?php flosc_tab_footer(); ?>
