<?php
/**
 * IVR .md upload → new flow (admin POST handler).
 *
 * Must run on admin_init before any admin HTML so wp_safe_redirect works
 * (same bug class as Set-as-default / settings save blank screens).
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flosc_admin_handle_ivr_file_upload')) {
    /**
     * Process flosc_upload_ivr_file POST. Redirects+exits on success.
     * On failure, sets settings errors and returns (page render can show them).
     *
     * @return void
     */
    function flosc_admin_handle_ivr_file_upload() {
        if (!empty($GLOBALS['flosc_ivr_upload_handled'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
        if (empty($_POST['flosc_upload_ivr_file']) || empty($_FILES['ivr_file_upload'])) {
            return;
        }

        $GLOBALS['flosc_ivr_upload_handled'] = true;

        check_admin_referer('flosc_upload_ivr_file');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to upload IVR files.', 'flosc'));
        }

        $flosc_raw_name = isset($_FILES['ivr_file_upload']['name'])
            ? wp_unslash((string) $_FILES['ivr_file_upload']['name'])
            : '';
        $flosc_tmp_name = isset($_FILES['ivr_file_upload']['tmp_name'])
            ? (string) $_FILES['ivr_file_upload']['tmp_name']
            : '';
        $flosc_error    = isset($_FILES['ivr_file_upload']['error'])
            ? (int) $_FILES['ivr_file_upload']['error']
            : UPLOAD_ERR_NO_FILE;
        $flosc_size     = isset($_FILES['ivr_file_upload']['size'])
            ? (int) $_FILES['ivr_file_upload']['size']
            : 0;

        if (UPLOAD_ERR_OK !== $flosc_error) {
            add_settings_error(
                'flosc_settings',
                'upload_failed',
                esc_html__('File upload failed. Please try again.', 'flosc'),
                'error'
            );
            return;
        }

        if ($flosc_tmp_name === '' || !is_uploaded_file($flosc_tmp_name)) {
            add_settings_error(
                'flosc_settings',
                'upload_failed',
                esc_html__('Upload could not be verified.', 'flosc'),
                'error'
            );
            return;
        }

        $flosc_filename = sanitize_file_name(basename($flosc_raw_name));
        $flosc_ext      = strtolower((string) pathinfo($flosc_filename, PATHINFO_EXTENSION));
        $flosc_stem_raw = (string) pathinfo($flosc_filename, PATHINFO_FILENAME);

        // Switch Flow enumerates *_ivr.md via flosc_config_glob().
        if ('md' === $flosc_ext && !preg_match('/_ivr$/i', $flosc_stem_raw)) {
            $flosc_filename = sanitize_file_name($flosc_stem_raw . '_ivr.md');
        }

        $flosc_target_path = function_exists('flosc_data_file_path')
            ? flosc_data_file_path($flosc_filename)
            : '';

        if ('md' !== strtolower((string) pathinfo($flosc_filename, PATHINFO_EXTENSION))) {
            add_settings_error(
                'flosc_settings',
                'upload_failed',
                esc_html__('Only .md files are allowed.', 'flosc'),
                'error'
            );
            return;
        }

        if ($flosc_size <= 0 || $flosc_size > 1024 * 1024) {
            add_settings_error(
                'flosc_settings',
                'upload_failed',
                esc_html__('IVR file must be between 1 byte and 1 MB.', 'flosc'),
                'error'
            );
            return;
        }

        if ('' === $flosc_target_path || !function_exists('flosc_write_data_file')) {
            add_settings_error(
                'flosc_settings',
                'upload_failed',
                esc_html__('FLOSC data directory is not available.', 'flosc'),
                'error'
            );
            return;
        }

        if (file_exists($flosc_target_path)) {
            add_settings_error(
                'flosc_settings',
                'upload_failed',
                sprintf(
                    /* translators: %s: IVR filename */
                    esc_html__('An IVR file named %s already exists. Choose a different filename or remove the existing flow first.', 'flosc'),
                    esc_html($flosc_filename)
                ),
                'error'
            );
            return;
        }

        $flosc_upload_body = function_exists('flosc_fs_get_contents')
            ? flosc_fs_get_contents($flosc_tmp_name)
            : false;
        if (false === $flosc_upload_body || !flosc_write_data_file($flosc_target_path, $flosc_upload_body)) {
            add_settings_error(
                'flosc_settings',
                'upload_failed',
                esc_html__('Could not store the IVR file in the FLOSC data directory.', 'flosc'),
                'error'
            );
            return;
        }

        $flosc_new_stem     = sanitize_key(pathinfo($flosc_filename, PATHINFO_FILENAME));
        $flosc_new_flow_key = 'flosc_flow_' . $flosc_new_stem;
        $flosc_display      = preg_replace('/_ivr$/i', '', $flosc_new_stem);
        $flosc_display      = trim(
            (string) preg_replace(
                '/\s+/',
                ' ',
                str_replace(array('flosc_', 'flosc-', '_', '-'), array('', '', ' ', ' '), (string) $flosc_display)
            )
        );
        $flosc_display = ('' !== $flosc_display) ? ucwords($flosc_display) : $flosc_new_stem;
        $flosc_new_slug = strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $flosc_new_stem));
        if ('' === $flosc_new_slug) {
            $flosc_new_slug = $flosc_new_stem;
        }

        // New flow bag only — no client/sample hardcodes. Operator configures
        // companion mode, freeline count, category, offers in admin after upload.
        $flosc_new_bag = array(
            'name'                        => $flosc_display,
            'slug'                        => $flosc_new_slug,
            'status'                      => 'active',
            'active_ivr_file'             => $flosc_filename,
            'ivr_file'                    => $flosc_filename,
            // Sales default: visitors may see companion when Hybrid/Companion is enabled later.
            'companion_show_for_visitors' => 1,
        );

        update_option($flosc_new_flow_key, $flosc_new_bag, false);

        if (function_exists('flosc_import_ivr_to_database')) {
            $flosc_import = flosc_import_ivr_to_database(false, $flosc_target_path, $flosc_new_flow_key, 'replace');
            if (!empty($flosc_import['success']) && function_exists('flosc_auto_export_ivr_to_file')) {
                flosc_auto_export_ivr_to_file($flosc_new_flow_key, $flosc_target_path);
            }
        }

        // PRG: redirect before any admin chrome so the main pane is not blank.
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'               => 'flosc-settings',
                    'tab'                => 'ivr-messages',
                    'ivr'                => $flosc_filename,
                    'view'               => 'all',
                    'flosc_ivr_uploaded' => '1',
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }
}
