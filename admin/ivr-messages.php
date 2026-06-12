<?php
/**
 * FLOSC IVR Management Tab v1.2.9
 *
 * Uses the IVR file selected in the Flow dropdown.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('flosc_resolve_ivr_file_path')) {
    function flosc_resolve_ivr_file_path($ivr_filename) {
        $ivr_filename = sanitize_file_name(trim((string) $ivr_filename));
        // Per WordPress.org policy: runtime-generated files must be written to uploads only.
        // flosc_data_dir() returns the writable uploads directory or '' when unavailable.
        $uploads_dir = function_exists('flosc_data_dir') ? flosc_data_dir() : '';
        $plugin_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';

        $uploads_path = ('' !== $uploads_dir) ? $uploads_dir . $ivr_filename : '';
        if ($uploads_path !== '' && file_exists($uploads_path)) {
            return $uploads_path;
        }

        if (function_exists('flosc_config_file')) {
            $resolved = flosc_config_file($ivr_filename);
            if (!empty($resolved) && file_exists($resolved)) {
                return $resolved;
            }
        }

        $plugin_path = $plugin_dir . $ivr_filename;
        if ($ivr_filename !== '' && file_exists($plugin_path)) {
            return $plugin_path;
        }

        return $uploads_path;
    }
}

$get = wp_unslash($_GET);
$post = wp_unslash($_POST);

// v1.2.8: Resolve active IVR file from explicit request first, then context fallback.
$requested_ivr_file = sanitize_file_name((string)($get['ivr'] ?? ''));
$active_ivr_file = $requested_ivr_file !== ''
    ? $requested_ivr_file
    : (($GLOBALS['flosc_current_ivr'] ?? '') !== '' ? sanitize_file_name((string)$GLOBALS['flosc_current_ivr']) : 'flosc_default_ivr.md');
$GLOBALS['flosc_current_ivr'] = $active_ivr_file;
// Per WordPress.org policy: writable paths must be in uploads only.
$flosc_ivr_dir = function_exists('flosc_data_dir') ? flosc_data_dir() : '';
$ivr_file_write_path = ('' !== $flosc_ivr_dir) ? $flosc_ivr_dir . $active_ivr_file : '';
$ivr_file_path = flosc_resolve_ivr_file_path($active_ivr_file);

// Per-flow settings
$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flow_key = $GLOBALS['flosc_settings_key'] ?? '';
$ivr_management_view = isset($get['view']) ? sanitize_text_field($get['view']) : 'single';
if (!in_array($ivr_management_view, ['single', 'all'], true)) {
    $ivr_management_view = 'single';
}

/**
 * Run IVR diagnostics - checks DB, file, sync status
 */
function flosc_run_ivr_diagnostics() {
    // v1.2.8: Use current IVR file from context
    $active_ivr = $GLOBALS['flosc_current_ivr'] ?? 'flosc_default_ivr.md';
    $ivr_file = function_exists('flosc_resolve_ivr_file_path')
        ? flosc_resolve_ivr_file_path($active_ivr)
        : (function_exists('flosc_config_file') ? flosc_config_file($active_ivr) : '');
    
    $diagnostics = [
        'db_connection' => ['status' => 'red', 'message' => 'Failed', 'details' => []],
        'ivr_file' => ['status' => 'red', 'message' => 'Not found', 'details' => []],
        'db_messages' => ['status' => 'red', 'message' => 'Empty', 'details' => []],
        'sync_status' => ['status' => 'red', 'message' => 'Not synced', 'details' => []],
        'offer_sync' => ['status' => 'red', 'message' => 'Not checked', 'details' => []],
        'api_endpoint' => ['status' => 'yellow', 'message' => 'Not tested', 'details' => []],
    ];
    
    // 1. DB Connection Test
    $test_key = 'flosc_diagnostic_test_' . time();
    $test_value = 'test_' . wp_rand();
    update_option($test_key, $test_value);
    $read_back = get_option($test_key);
    delete_option($test_key);
    
    if ($read_back === $test_value) {
        $diagnostics['db_connection'] = [
            'status' => 'green',
            'message' => 'Connected',
            'details' => ['Write/read/delete cycle successful']
        ];
    } else {
        $diagnostics['db_connection']['details'][] = "Write test failed: wrote '$test_value', got '$read_back'";
    }
    
    // 2. ivr.md File Check
    if (file_exists($ivr_file)) {
        $file_size = filesize($ivr_file);
        $file_modified = gmdate('Y-m-d H:i:s', filemtime($ivr_file));
        
        // Try to parse it
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
        $parser = FLOSC_IVR_Parser::flosc_instance();
        $markdown = file_get_contents($ivr_file);
        $config = $parser->flosc_parse($markdown);
        
        $file_message_count = count($config['messages'] ?? []);
        
        if ($file_message_count > 0) {
            $diagnostics['ivr_file'] = [
                'status' => 'green',
                'message' => "$file_message_count messages",
                'details' => [
                    "File size: " . number_format($file_size) . " bytes",
                    "Last modified: $file_modified",
                    "Phases: " . implode(', ', array_keys(array_filter($config['phases'], fn($p) => !empty($p))))
                ]
            ];
        } else {
            $diagnostics['ivr_file'] = [
                'status' => 'yellow',
                'message' => 'Parse issue',
                'details' => ["File exists but parser returned 0 messages"]
            ];
        }
    } else {
        $diagnostics['ivr_file']['details'][] = "Expected at: $ivr_file";
        $diagnostics['ivr_file']['details'][] = 'Recommended next step: click "Save DB → IVR File" to create/rebuild the active file from FLOSC DB.';
    }
    
    // 3. Database Messages Check (per-flow)
    $fs = $GLOBALS['flosc_current_settings'] ?? [];
    $db_messages = $fs['ivr_messages'] ?? [];
    $db_phases = $fs['ivr_phases'] ?? [];
    $last_import = $fs['ivr_last_import'] ?? 'Never';
    
    if (!empty($db_messages)) {
        $phase_counts = [];
        foreach ($db_phases as $phase => $ids) {
            if (!empty($ids)) {
                $phase_counts[] = "$phase: " . count($ids);
            }
        }
        
        $diagnostics['db_messages'] = [
            'status' => 'green',
            'message' => count($db_messages) . ' messages',
            'details' => [
                "Last sync: $last_import",
                "By phase: " . implode(', ', $phase_counts)
            ]
        ];
    } else {
        $diagnostics['db_messages']['details'][] = "FLOSC DB is empty";
        $diagnostics['db_messages']['details'][] = "Click 'Load' to populate from file";
    }
    
    // 4. Sync Status (compare file vs DB)
    if (isset($config) && !empty($db_messages)) {
        $file_ids = array_keys($config['messages'] ?? []);
        $db_ids = array_keys($db_messages);
        
        $in_file_not_db = array_diff($file_ids, $db_ids);
        $in_db_not_file = array_diff($db_ids, $file_ids);
        
        if (empty($in_file_not_db) && empty($in_db_not_file)) {
            // v2.0.0: Full field comparison — not just content
            $compare_fields = ['title', 'name', 'type', 'style', 'panel', 'icon',
                'user_input', 'keywords', 'action', 'conditions', 'phase',
                'offer_id', 'price', 'discount_price', 'timer', 'display_format', 'content'];
            $mismatches = [];
            foreach ($file_ids as $id) {
                $file_msg = $config['messages'][$id] ?? [];
                $db_msg   = $db_messages[$id] ?? [];
                foreach ($compare_fields as $field) {
                    if ((string) ($file_msg[$field] ?? '') !== (string) ($db_msg[$field] ?? '')) {
                        $mismatches[] = $id;
                        break; // one differing field is enough to flag this message
                    }
                }
            }
            
            if (empty($mismatches)) {
                $diagnostics['sync_status'] = [
                    'status' => 'green',
                    'message' => 'Match ✓',
                    'details' => ['Active IVR Messages MD file and FLOSC DB match (' . count($file_ids) . ' messages, all fields checked)']
                ];
            } else {
                $diagnostics['sync_status'] = [
                    'status' => 'yellow',
                    'message' => count($mismatches) . ' message(s) differ',
                    'details' => [
                        'FLOSC DB differs from file: ' . implode(', ', array_slice($mismatches, 0, 3)),
                        count($mismatches) > 3 ? '... and ' . (count($mismatches) - 3) . ' more' : '',
                        'Press "Compare" to see field-level details, or "Save DB → IVR File" to publish current DB values to file.'
                    ]
                ];
            }
        } else {
            $details = [];
            if (!empty($in_file_not_db)) {
                $details[] = 'In file only: ' . implode(', ', array_slice($in_file_not_db, 0, 3));
            }
            if (!empty($in_db_not_file)) {
                $details[] = 'In FLOSC DB only: ' . implode(', ', array_slice($in_db_not_file, 0, 3));
            }
            $details[] = 'Use "Compare" to see full details.';
            $diagnostics['sync_status'] = [
                'status' => 'yellow',
                'message' => 'Out of sync',
                'details' => $details
            ];
        }
    } elseif (empty($db_messages)) {
        $diagnostics['sync_status']['details'][] = 'FLOSC DB is empty';
        $diagnostics['sync_status']['details'][] = 'Click "Merge And Sync File ↔ DB" to unify file and DB with no entry loss';
    } else {
        $diagnostics['sync_status']['details'][] = 'Direction guide: DB currently has messages, but the active IVR file is missing or unreadable.';
        $diagnostics['sync_status']['details'][] = 'Use "Save DB → IVR File" to publish DB messages into the file.';
        $diagnostics['sync_status']['details'][] = 'Use "Merge And Sync File ↔ DB" only after the file exists and you want two-way union sync.';
    }

    // 5. Offer Sync Status (compare IVR offer messages vs per-flow offers registry)
    $db_offers = $fs['offers'] ?? [];
    if (isset($config)) {
        $ivr_offers = [];
        foreach (($config['messages'] ?? []) as $message_id => $msg) {
            $msg_type = strtolower(trim((string)($msg['type'] ?? '')));
            if ($msg_type !== 'offer') {
                continue;
            }

            $offer_id = sanitize_key((string)($msg['offer_id'] ?? $message_id));
            if ($offer_id === '') {
                continue;
            }

            $ivr_offers[$offer_id] = [
                'name' => trim((string)($msg['title'] ?? ($msg['name'] ?? ''))),
                'description' => trim((string)($msg['content'] ?? '')),
                'display_format' => trim((string)($msg['display_format'] ?? '')),
                'condition' => trim((string)($msg['conditions'] ?? '')),
                'reveal_phrase' => trim((string)($msg['user_input'] ?? '')),
                'icon' => trim((string)($msg['icon'] ?? '')),
            ];
        }

        $registry_offers = [];
        if (is_array($db_offers)) {
            foreach ($db_offers as $db_offer_id => $offer) {
                if (!is_array($offer)) {
                    continue;
                }

                $offer_id = sanitize_key((string)($offer['id'] ?? $db_offer_id));
                if ($offer_id === '') {
                    continue;
                }

                $registry_offers[$offer_id] = [
                    'name' => trim((string)($offer['name'] ?? '')),
                    'description' => trim((string)($offer['description'] ?? '')),
                    'display_format' => trim((string)($offer['display_format'] ?? '')),
                    'condition' => trim((string)($offer['condition'] ?? '')),
                    'reveal_phrase' => trim((string)($offer['reveal_phrase'] ?? '')),
                    'icon' => trim((string)($offer['meta']['icon'] ?? '')),
                ];
            }
        }

        $ivr_offer_ids = array_keys($ivr_offers);
        $registry_offer_ids = array_keys($registry_offers);
        $in_ivr_not_registry = array_values(array_diff($ivr_offer_ids, $registry_offer_ids));
        $in_registry_not_ivr = array_values(array_diff($registry_offer_ids, $ivr_offer_ids));

        if (!empty($in_ivr_not_registry)) {
            $details = [];
            $details[] = 'In IVR only: ' . implode(', ', array_slice($in_ivr_not_registry, 0, 3));
            $details[] = 'Open Offers tab and create these offer IDs in the flow offers registry.';
            $diagnostics['offer_sync'] = [
                'status' => 'yellow',
                'message' => 'Out of sync',
                'details' => $details
            ];
        } else {
            $offer_fields = ['name', 'description', 'display_format', 'condition', 'reveal_phrase', 'icon'];
            $field_mismatches = [];

            foreach ($ivr_offer_ids as $offer_id) {
                foreach ($offer_fields as $field) {
                    if ((string)($ivr_offers[$offer_id][$field] ?? '') !== (string)($registry_offers[$offer_id][$field] ?? '')) {
                        $field_mismatches[] = $offer_id;
                        break;
                    }
                }
            }

            if (empty($field_mismatches)) {
                if (!empty($in_registry_not_ivr)) {
                    $details = ['In FLOSC DB only: ' . implode(', ', array_slice($in_registry_not_ivr, 0, 3))];
                    if (count($in_registry_not_ivr) > 3) {
                        $details[] = '... and ' . (count($in_registry_not_ivr) - 3) . ' more';
                    }
                    $details[] = 'Offer registry contains IDs not referenced in the active IVR file.';
                    $diagnostics['offer_sync'] = [
                        'status' => 'yellow',
                        'message' => 'Out of sync',
                        'details' => $details
                    ];
                } else {
                    $diagnostics['offer_sync'] = [
                        'status' => 'green',
                        'message' => 'Match ✓',
                        'details' => ['IVR offers and FLOSC DB offers match (' . count($ivr_offer_ids) . ' offers, key fields checked)']
                    ];
                }
            } else {
                $diagnostics['offer_sync'] = [
                    'status' => 'yellow',
                    'message' => count($field_mismatches) . ' offer(s) differ',
                    'details' => [
                        'Differing offers: ' . implode(', ', array_slice($field_mismatches, 0, 3)),
                        count($field_mismatches) > 3 ? '... and ' . (count($field_mismatches) - 3) . ' more' : '',
                        'Open Offers tab to edit, or load file to re-sync IVR-defined offer content.'
                    ]
                ];
            }
        }
    } else {
        $diagnostics['offer_sync']['details'][] = 'IVR file parse required before offer comparison';
        $diagnostics['offer_sync']['details'][] = 'Direction guide: fix/create the active IVR file first, then rerun diagnostics.';
        $diagnostics['offer_sync']['details'][] = 'Fast path: click "Save DB → IVR File", then click "Refresh Diagnostics".';
    }
    
    // 6. API Endpoint - actually test it server-side
    $api_url = rest_url('flosc/v1/ivr-messages?phase=freeline');
    
    // v9.3.1: Michel Timestamp format for last checked
    $michel_timestamp = gmdate('Y') . '-' . gmdate('m') . 'm-' . gmdate('d') . 'd-T' . gmdate('H:i:s');
    
    // Perform actual API test
    $api_response = wp_remote_get($api_url, ['timeout' => 5]);
    
    if (is_wp_error($api_response)) {
        $diagnostics['api_endpoint'] = [
            'status' => 'red',
            'message' => 'Error',
            'details' => [
                'API request failed: ' . $api_response->get_error_message(),
                'Last checked: ' . $michel_timestamp
            ],
            'url' => $api_url
        ];
        // Store last check time even on error (per-flow)
        $fk = $GLOBALS['flosc_settings_key'] ?? '';
        if ($fk) { $tmp = get_option($fk, []); $tmp['api_last_check'] = $michel_timestamp; update_option($fk, $tmp); }
    } else {
        $body = wp_remote_retrieve_body($api_response);
        $data = json_decode($body, true);
        
        // Store successful check time (per-flow)
        $fk = $GLOBALS['flosc_settings_key'] ?? '';
        if ($fk) { $tmp = get_option($fk, []); $tmp['api_last_check'] = $michel_timestamp; update_option($fk, $tmp); }
        
        if (isset($data['success']) && $data['success'] && !empty($data['messages'])) {
            $msg_count = count($data['messages']);
            $diagnostics['api_endpoint'] = [
                'status' => 'green',
                'message' => 'Working ✓',
                'details' => [
                    "Returns $msg_count messages for freeline phase",
                    'Last checked: ' . $michel_timestamp
                ],
                'url' => $api_url
            ];
        } elseif (isset($data['success']) && $data['success']) {
            // v9.3.1: API works but 0 messages is still green (API is functional)
            $diagnostics['api_endpoint'] = [
                'status' => 'green',
                'message' => 'Working (0 msgs)',
                'details' => [
                    "API responds correctly, 0 messages match conditions",
                    'Last checked: ' . $michel_timestamp
                ],
                'url' => $api_url
            ];
        } else {
            $diagnostics['api_endpoint'] = [
                'status' => 'red',
                'message' => 'Error',
                'details' => [
                    'API returned unexpected response',
                    'Last checked: ' . $michel_timestamp
                ],
                'url' => $api_url
            ];
        }
    }
    
    return $diagnostics;
}

// Handle file upload
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is handled by wp_handle_upload() and validated by extension/mime checks below
if (isset($post['flosc_upload_ivr_file']) && isset($_FILES['ivr_file_upload'])) {
    check_admin_referer('flosc_upload_ivr_file');
    
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- source array is validated by wp_handle_upload(), extension and mime checks below
    $uploaded_file = $_FILES['ivr_file_upload'];
    
    if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
        $filename = sanitize_file_name($uploaded_file['name']);

        // Ensure uploaded file is markdown.
        if (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'md') {
            add_settings_error('flosc_settings', 'upload_failed', 'Only .md files are allowed.', 'error');
        } else {
            $target_path = $flosc_ivr_dir . $filename;

            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $upload_overrides = [
                'test_form' => false,
                'mimes'     => ['md' => 'text/markdown'],
            ];
            $handled_upload = wp_handle_upload($uploaded_file, $upload_overrides);

            if (isset($handled_upload['error'])) {
                add_settings_error('flosc_settings', 'upload_failed', 'Upload failed: ' . $handled_upload['error'], 'error');
            } elseif (!empty($handled_upload['file']) && @copy($handled_upload['file'], $target_path)) {
                wp_delete_file($handled_upload['file']);
                if ($flow_key) {
                    $fs = get_option($flow_key, []);
                    $fs['active_ivr_file'] = $filename;
                    $fs['ivr_file'] = $filename;
                    update_option($flow_key, $fs);
                }
                add_settings_error('flosc_settings', 'upload_success', 'Uploaded and set as active: ' . $filename, 'success');
            } else {
                add_settings_error('flosc_settings', 'upload_failed', 'Failed to save uploaded file.', 'error');
            }
        }
    } else {
        add_settings_error('flosc_settings', 'upload_failed', 'File upload error: ' . $uploaded_file['error'], 'error');
    }
}

// Handle explicit file import from IVR File Management (selected file -> FLOSC DB)
if (isset($post['flosc_import_selected_ivr_file']) && isset($post['import_ivr_file'])) {
    check_admin_referer('flosc_import_selected_ivr_file');

    $selected_file = sanitize_file_name($post['import_ivr_file']);
    $selected_path = $flosc_ivr_dir . $selected_file;

    if (!file_exists($selected_path)) {
        add_settings_error('flosc_settings', 'import_selected_failed', 'Selected IVR file not found: ' . $selected_file, 'error');
    } else {
        $result = flosc_import_ivr_to_database(false, $selected_path, $flow_key, 'merge');
        if ($result['success']) {
            // Complete sync cycle so file and DB end in parity after merge.
            $export_ok = flosc_auto_export_ivr_to_file($flow_key, $selected_path);
            if ($flow_key) {
                $fs = get_option($flow_key, []);
                $fs['active_ivr_file'] = $selected_file;
                $fs['ivr_file'] = $selected_file;
                update_option($flow_key, $fs);
                $GLOBALS['flosc_current_settings'] = $fs;
                $flow_settings = $fs;
            }
            if ($export_ok) {
                add_settings_error('flosc_settings', 'import_selected_success', 'Merged selected IVR file and synced FLOSC DB ↔ IVR file: ' . esc_html($selected_file) . '. No discrepancies remain.', 'success');
            } else {
                add_settings_error('flosc_settings', 'import_selected_partial', 'Merged selected IVR file → FLOSC DB, but file sync failed. Use Save DB → IVR File to finish parity for: ' . esc_html($selected_file), 'error');
            }
        } else {
            add_settings_error('flosc_settings', 'import_selected_failed', 'Import failed: ' . esc_html($result['message']), 'error');
        }
    }
}

// Handle changing active IVR file
if (isset($post['flosc_change_active_file']) && isset($post['ivr_file_select'])) {
    check_admin_referer('flosc_change_active_file');
    
    $selected_file = sanitize_file_name($post['ivr_file_select']);
    $file_path = flosc_resolve_ivr_file_path($selected_file);
    
    if (file_exists($file_path)) {
        // v1.2.6: Save to flow if in flow context, otherwise save globally
        $editing_flow_id = $GLOBALS['flosc_editing_flow'] ?? null;
        
        if ($editing_flow_id) {
            // Update flow's ivr_file setting
            flosc_flows()->update_flow($editing_flow_id, ['ivr_file' => $selected_file]);
            add_settings_error('flosc_settings', 'file_changed', 'Flow IVR file changed to: ' . $selected_file . '. Click "Merge" to import it into the FLOSC DB.', 'success');
        } else {
            // Per-flow storage
            if ($flow_key) { $fs = get_option($flow_key, []); $fs['active_ivr_file'] = $selected_file; update_option($flow_key, $fs); }
            add_settings_error('flosc_settings', 'file_changed', 'Active IVR Messages MD file changed to: ' . $selected_file . '. Click "Merge" to import it into the FLOSC DB.', 'success');
        }
    } else {
        add_settings_error('flosc_settings', 'file_not_found', 'File not found: ' . $selected_file, 'error');
    }
}

// Handle full text save for active IVR file
if (isset($post['flosc_save_full_ivr']) && isset($post['ivr_full_text'])) {
    check_admin_referer('flosc_save_full_ivr');

    $full_text = $post['ivr_full_text'];
    // Write edited IVR text using the uploads-only API.
    if ('' === $ivr_file_write_path) {
        $save_ok = false;
    } else {
        $save_ok = function_exists('flosc_write_data_file')
            ? flosc_write_data_file($ivr_file_write_path, $full_text)
            : false;
    }

    if ($save_ok === false) {
        add_settings_error('flosc_settings', 'full_text_save_failed', 'Could not save IVR file text. Check file permissions or uploads availability.', 'error');
    } else {
        add_settings_error('flosc_settings', 'full_text_saved', 'Saved full IVR file text. Use "Merge IVR File → DB" to refresh runtime DB from file.', 'success');
        clearstatcache(true, $ivr_file_write_path);
    }
}

// Handle IVR file download
if (isset($get['flosc_download_ivr']) && isset($get['_wpnonce'])) {
    $download_file = sanitize_file_name($get['flosc_download_ivr']);
    if (wp_verify_nonce(sanitize_text_field($get['_wpnonce']), 'flosc_download_ivr_' . $download_file)) {
        $download_path = flosc_resolve_ivr_file_path($download_file);
        if (file_exists($download_path) && is_readable($download_path)) {
            if (!function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            WP_Filesystem();
            $download_content = is_object($wp_filesystem) ? $wp_filesystem->get_contents($download_path) : '';
            if ($download_content === false) {
                $download_content = '';
            }
            nocache_headers();
            header('Content-Type: text/markdown; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . basename($download_file) . '"');
            header('Content-Length: ' . strlen($download_content));
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw markdown file download stream
            echo $download_content;
            exit;
        }
    }
    add_settings_error('flosc_settings', 'download_failed', 'Could not download IVR file.', 'error');
}

// Handle IVR file duplication
if (isset($post['flosc_duplicate_ivr_file']) && isset($post['duplicate_ivr_file'])) {
    check_admin_referer('flosc_duplicate_ivr_file');

    $source_file = sanitize_file_name($post['duplicate_ivr_file']);
    $source_path = flosc_resolve_ivr_file_path($source_file);

    if (!file_exists($source_path)) {
        add_settings_error('flosc_settings', 'duplicate_invalid', 'Source IVR file not found.', 'error');
    } else {
        $extension = pathinfo($source_file, PATHINFO_EXTENSION);
        $base_name = pathinfo($source_file, PATHINFO_FILENAME);
        $duplicate_file = $base_name . '-copy.' . $extension;
        $duplicate_path = $flosc_ivr_dir . $duplicate_file;
        $counter = 2;

        while (file_exists($duplicate_path)) {
            $duplicate_file = $base_name . '-copy-' . $counter . '.' . $extension;
            $duplicate_path = $flosc_ivr_dir . $duplicate_file;
            $counter++;
        }

        if (!copy($source_path, $duplicate_path)) {
            add_settings_error('flosc_settings', 'duplicate_failed', 'Could not duplicate IVR file. Check file permissions.', 'error');
        } else {
            add_settings_error('flosc_settings', 'duplicate_success', 'Duplicated IVR file: ' . $duplicate_file, 'success');
        }
    }
}

// Handle file deletion from IVR Management
if (isset($post['flosc_delete_ivr_file']) && isset($post['delete_ivr_file'])) {
    check_admin_referer('flosc_delete_ivr_file');

    $delete_file = sanitize_file_name($post['delete_ivr_file']);
    $delete_path = $flosc_ivr_dir . $delete_file;

    if (!file_exists($delete_path)) {
        add_settings_error('flosc_settings', 'delete_invalid', 'File not found or not a managed IVR file.', 'error');
    } elseif (wp_delete_file($delete_path) === false) {
        add_settings_error('flosc_settings', 'delete_failed', 'Failed to delete IVR file. Check file permissions.', 'error');
    } else {
        add_settings_error('flosc_settings', 'delete_success', 'Deleted IVR file: ' . $delete_file, 'success');
    }
}

// Handle clear DB action
if (isset($post['flosc_clear_ivr_db'])) {
    check_admin_referer('flosc_clear_ivr_db');
    
    // Backup first
    flosc_export_ivr_backup($flow_key);
    
    // Clear (per-flow)
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $fs['ivr_messages'] = [];
        $fs['ivr_phases'] = [
            'freeline' => [],
            'login' => [],
            'offer' => [],
            'sale' => [],
            'content' => [],
        ];
        $fs['ivr_styles'] = [];
        unset($fs['ivr_last_import']);
        update_option($flow_key, $fs);
    }
    
    add_settings_error('flosc_settings', 'db_cleared', 'FLOSC DB cleared. Backup created automatically.', 'success');
}

// Handle merge-and-sync (union sync: keep entries from both sides, then restore parity)
if (isset($post['flosc_force_resync'])) {
    check_admin_referer('flosc_force_resync');
    
    $result = flosc_import_ivr_to_database(false, $ivr_file_path, $flow_key, 'merge');
    
    if ($result['success']) {
        // Merge is considered complete only when DB and file are brought back to parity.
        $export_ok = flosc_auto_export_ivr_to_file($flow_key, $ivr_file_write_path);
        // Refresh in-memory settings so diagnostics see the update
        if ($flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flow_key, []);
            $flow_settings = $GLOBALS['flosc_current_settings'];
        }
        if ($export_ok) {
            add_settings_error('flosc_settings', 'load_done', 'Merged Active IVR Messages MD file and synced FLOSC DB ↔ IVR file: ' . $result['message'] . '. No discrepancies remain.', 'success');
        } else {
            add_settings_error('flosc_settings', 'load_partial', 'Merged Active IVR Messages MD file → FLOSC DB, but file sync failed. Use Save DB → IVR File to finish parity.', 'error');
        }
    } else {
        add_settings_error('flosc_settings', 'load_failed', 'Import failed: ' . $result['message'], 'error');
    }
}

// Handle explicit offers alignment (active IVR offer messages -> flow offers registry)
// Keep offers registry aligned with IVR messages as part of normal DB<->IVR sync behavior.
if (!empty($flow_key) && function_exists('flosc_sync_flow_offers_with_ivr_messages')) {
    $messages_for_sync = $flow_settings['ivr_messages'] ?? [];
    flosc_sync_flow_offers_with_ivr_messages($flow_key, $messages_for_sync);
    $GLOBALS['flosc_current_settings'] = get_option($flow_key, []);
    $flow_settings = $GLOBALS['flosc_current_settings'];
}

// Handle import confirmation (same as Load)
if (isset($post['flosc_confirm_import'])) {
    check_admin_referer('flosc_confirm_import');
    
    $import_mode = (isset($post['flosc_import_mode']) && $post['flosc_import_mode'] === 'replace') ? 'replace' : 'merge';
    $result = flosc_import_ivr_to_database(false, $ivr_file_path, $flow_key, $import_mode);
    
    if ($result['success']) {
        $did_merge = ($import_mode !== 'replace');
        $export_ok = true;
        if ($did_merge) {
            // Merge must end with file parity to pass status cards.
            $export_ok = flosc_auto_export_ivr_to_file($flow_key, $ivr_file_write_path);
        }

        // Refresh in-memory settings
        if ($flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flow_key, []);
            $flow_settings = $GLOBALS['flosc_current_settings'];
        }
        if ($import_mode === 'replace') {
            add_settings_error('flosc_settings', 'ivr_imported', 'Replaced FLOSC DB to match Active IVR Messages MD file: ' . esc_html($result['message']), 'success');
        } elseif ($export_ok) {
            add_settings_error('flosc_settings', 'ivr_imported', 'Merged Active IVR Messages MD file and synced FLOSC DB ↔ IVR file: ' . esc_html($result['message']) . '. No discrepancies remain.', 'success');
        } else {
            add_settings_error('flosc_settings', 'ivr_import_partial', 'Merged Active IVR Messages MD file → FLOSC DB, but file sync failed. Use Save DB → IVR File to finish parity.', 'error');
        }
    } else {
        add_settings_error('flosc_settings', 'ivr_import_failed', 'Import failed: ' . esc_html($result['message']), 'error');
    }
}

// Generate comparison preview
$import_preview = null;
if (isset($post['flosc_preview_import'])) {
    check_admin_referer('flosc_preview_import');
    if (!file_exists($ivr_file_path)) {
        add_settings_error('flosc_settings', 'preview_file_missing', 'Compare unavailable: active IVR file is missing. Next step: Save DB → IVR File, then run Compare again.', 'warning');
    } else {
        $result = flosc_import_ivr_to_database(true, $ivr_file_path, $flow_key, 'merge'); // Preview only
        if ($result['success'] && isset($result['preview'])) {
            $import_preview = $result['stats'];
        } else {
            add_settings_error('flosc_settings', 'preview_file_unreadable', 'Compare unavailable: active IVR file could not be parsed. Next step: Save DB → IVR File, then Refresh Diagnostics.', 'warning');
        }
    }
}

// Handle export
if (isset($post['flosc_export_ivr'])) {
    check_admin_referer('flosc_export_ivr');

    $messages = $flow_settings['ivr_messages'] ?? [];
    if (!empty($flow_key) && function_exists('flosc_sync_flow_offers_with_ivr_messages')) {
        flosc_sync_flow_offers_with_ivr_messages($flow_key, $messages);
    }

    $result = flosc_auto_export_ivr_to_file($flow_key, $ivr_file_write_path);
    if ($result && file_exists($ivr_file_write_path)) {
        if ($flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flow_key, []);
            $flow_settings = $GLOBALS['flosc_current_settings'];
        }
        add_settings_error('flosc_settings', 'ivr_exported', 'Resynced: FLOSC DB saved → Active IVR Messages MD file', 'success');
    } elseif ($result) {
        add_settings_error('flosc_settings', 'ivr_export_failed_missing_file', 'Save DB → IVR reported success but file was not found at expected path: ' . esc_html($ivr_file_write_path), 'error');
    } else {
        add_settings_error('flosc_settings', 'ivr_export_failed', 'Save DB → IVR failed. Check file permissions and path.', 'error');
    }
}

// Handle message save/delete
// Save message: always writes to both DB (live runtime) and IVR file (portable config)
if (isset($post['save_ivr_message'])) {
    check_admin_referer('flosc_save_ivr_message');
    
    // DB = live runtime, IVR file = portable config. Always save to both.
    
    $messages = $flow_settings['ivr_messages'] ?? [];
    $phases = $flow_settings['ivr_phases'] ?? [];
    
    $msg_id = sanitize_text_field($post['message_id'] ?? '');
    $phase = sanitize_text_field($post['message_phase'] ?? '');
    
    // v9.2.8: Use sanitize_textarea_field to preserve content without over-escaping
    $raw_content = $post['message_content'] ?? '';
    $clean_content = sanitize_textarea_field($raw_content);
    
    $message_data = [
        'name' => sanitize_text_field($post['message_name'] ?? ''),
        'type' => sanitize_text_field($post['message_type'] ?? ''),
        'phase' => $phase,
        'content' => $clean_content,
        'conditions' => sanitize_text_field($post['message_conditions'] ?? ''),
        'style' => sanitize_text_field($post['message_style'] ?? 'default'),
        'icon' => sanitize_text_field($post['message_icon'] ?? ''),
        'user_input' => sanitize_text_field($post['message_user_input'] ?? ''),
        'keywords' => sanitize_text_field($post['message_keywords'] ?? ''),
        'action' => sanitize_text_field($post['message_action'] ?? ''),
    ];
    
    // v1.6.2: Include offer-specific fields when type is 'offer'
    if ($message_data['type'] === 'offer') {
        $offer_fields = [
            'offer_id'       => sanitize_text_field($post['message_offer_id'] ?? ''),
            'price'          => sanitize_text_field($post['message_price'] ?? ''),
            'discount_price'  => sanitize_text_field($post['message_discount_price'] ?? ''),
            'timer'          => intval($post['message_timer'] ?? 0),
            'display_format'  => sanitize_text_field($post['message_display_format'] ?? 'card'),
            'html_file'      => sanitize_file_name($post['message_html_file'] ?? ''),
            'woo_product'    => sanitize_text_field($post['message_woo_product'] ?? ''),
            'post_id'        => intval($post['message_post_id'] ?? 0),
        ];
        // Only store non-empty values
        foreach ($offer_fields as $k => $v) {
            if (!empty($v)) $message_data[$k] = $v;
        }
    }

    // v8.0.0: Concierge fields when type is 'concierge' — keyword-triggered message
    // with an optional password gate. Retry messages are one per line (the retry list).
    if ($message_data['type'] === 'concierge') {
        $message_data['individual_message_password'] = sanitize_text_field($post['message_individual_password'] ?? '');
        $message_data['password_prompt']  = sanitize_text_field($post['message_password_prompt'] ?? '');
        $message_data['password_success'] = sanitize_text_field($post['message_password_success'] ?? '');
        $message_data['password_max_tries'] = max(1, intval($post['message_password_max_tries'] ?? 3));
        $retry_list = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($post['message_password_retry'] ?? '')) as $retry_line) {
            $retry_line = sanitize_text_field($retry_line);
            if ($retry_line !== '') {
                $retry_list[] = $retry_line;
            }
        }
        $message_data['password_retry_messages'] = $retry_list;
    }

    // Save message to FLOSC DB (per-flow). Merge over the existing message so fields
    // the editor does not expose (e.g. MessagePanel) survive an edit instead of being
    // dropped. The form's fields still win — present-in-form values override the old
    // ones — so a field can be edited or cleared; only untouched fields are preserved.
    $existing_message  = ( isset($messages[$msg_id]) && is_array($messages[$msg_id]) ) ? $messages[$msg_id] : array();
    $messages[$msg_id] = array_merge($existing_message, $message_data);
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $fs['ivr_messages'] = $messages;
    
    // Update phase mapping
    if (!isset($phases[$phase])) {
        $phases[$phase] = [];
    }
    if (!in_array($msg_id, $phases[$phase])) {
        $phases[$phase][] = $msg_id;
    }
        $fs['ivr_phases'] = $phases;
        update_option($flow_key, $fs);
        $GLOBALS['flosc_current_settings'] = $fs;
        $flow_settings = $fs;
    }
    
    // Always save to both: DB is the live runtime, IVR file is the portable config
    flosc_auto_export_ivr_to_file($flow_key, $ivr_file_write_path);
    add_settings_error('flosc_settings', 'message_saved', 'Saved to FLOSC DB (live) and IVR file (portable config).', 'success');
}

if (isset($get['delete_message']) && isset($get['phase'])) {
    check_admin_referer('flosc_delete_message_' . $get['delete_message']);
    
    $msg_id = sanitize_text_field($get['delete_message']);
    $phase = sanitize_text_field($get['phase']);
    
    $messages = $flow_settings['ivr_messages'] ?? [];
    $phases = $flow_settings['ivr_phases'] ?? [];
    
    unset($messages[$msg_id]);
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $fs['ivr_messages'] = $messages;
    
    if (isset($phases[$phase])) {
        $phases[$phase] = array_diff($phases[$phase], [$msg_id]);
    }
        $fs['ivr_phases'] = $phases;
        update_option($flow_key, $fs);
        $GLOBALS['flosc_current_settings'] = $fs;
        $flow_settings = $fs;
    }
    
    // v9.2.10: Delete always resyncs to file (destructive operation)
    flosc_auto_export_ivr_to_file($flow_key, $ivr_file_write_path);
    
    add_settings_error('flosc_settings', 'message_deleted', 'Message deleted from FLOSC DB and removed from Active IVR Messages MD file', 'success');
}

// Run diagnostics after mutations so cards reflect the current request actions.
$ivr_diagnostics = flosc_run_ivr_diagnostics();

$messages = $flow_settings['ivr_messages'] ?? [];
$phases = $flow_settings['ivr_phases'] ?? [];
$active_phase = $get['ivr_phase'] ?? 'freeline';
$editing_message = $get['edit_message'] ?? null;

// v1.2.6: Get flow context if available
$editing_flow_id = $GLOBALS['flosc_editing_flow'] ?? null;
$editing_flow_data = $GLOBALS['flosc_editing_flow_data'] ?? null;

// v1.2.5: Get list of available IVR files (matches *_ivr.md and *ivr*.md patterns)
$ivr_files_dir = $flosc_ivr_dir;
$available_ivr_files = [];
if (is_dir($ivr_files_dir)) {
    // Match both patterns: *_ivr.md (flosc_default_ivr.md) and ivr*.md (ivr.md)
    $files = array_merge(
        glob($ivr_files_dir . '*_ivr.md'),
        glob($ivr_files_dir . 'ivr*.md')
    );
    $files = array_unique($files); // Remove duplicates
    sort($files); // Alphabetical order
    foreach ($files as $file) {
        $filename = basename($file);
        // Skip backup files
        if (strpos($filename, 'backup') === false) {
            $available_ivr_files[] = $filename;
        }
    }
}

// In single-flow view, the selected flow/file must remain the active IVR target.
// Only fall back to stored active_ivr_file when there is no selected flow context.
if ($requested_ivr_file !== '') {
    $active_ivr_file = $requested_ivr_file;
} elseif ($editing_flow_data && !empty($editing_flow_data['ivr_file'])) {
    $active_ivr_file = $editing_flow_data['ivr_file'];
} elseif (!empty($GLOBALS['flosc_current_ivr'])) {
    $active_ivr_file = $GLOBALS['flosc_current_ivr'];
} else {
    $active_ivr_file = $flow_settings['active_ivr_file'] ?? 'flosc_default_ivr.md';
}

$GLOBALS['flosc_current_ivr'] = $active_ivr_file;
$ivr_file_write_path = $flosc_ivr_dir . $active_ivr_file;
$ivr_file_path = flosc_resolve_ivr_file_path($active_ivr_file);

$ivr_management_base_url = esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($active_ivr_file) . '&view=' . urlencode($ivr_management_view)));
$ivr_management_phase_url = $ivr_management_base_url . '&ivr_phase=' . urlencode($active_phase);
$ivr_management_all_phase_url = esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($active_ivr_file) . '&view=all&ivr_phase=' . urlencode($active_phase)));
$ivr_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $active_ivr_file,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], esc_url(admin_url('admin.php'))) . '#tab-ivr-messages';

// v1.2.9: Output tab header after request handlers so download responses can send headers cleanly.
flosc_tab_header('💬', 'IVR Management');

?>

<div style="margin:-8px 0 14px; text-align:right;">
    <a href="<?php echo esc_url($ivr_docs_url); ?>" style="font-size:12px; text-decoration:none; color:#2271b1;">Docs</a>
</div>

</form>

<!-- IVR System Status Panel -->
<div class="flosc-diagnostics-panel" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
        🔧 IVR Management
        <span style="font-size: 12px; font-weight: normal; color: #667;">(v<?php echo esc_html( FLOSC_VERSION ); ?>)</span>
        <code style="margin-left: 8px; background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 4px; font-size: 13px;">
            <?php echo esc_html($active_ivr_file); ?>
        </code>
    </h3>
    
    <p class="description" style="margin-bottom: 15px;">
        Editing IVR messages for this flow. Use the Flow dropdown above to switch to a different IVR file.
    </p>

    <div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($active_ivr_file) . '&view=single')); ?>" class="button <?php echo $ivr_management_view === 'single' ? 'button-primary' : ''; ?>">
            Single Flow IVR Management
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($active_ivr_file) . '&view=all')); ?>" class="button <?php echo $ivr_management_view === 'all' ? 'button-primary' : ''; ?>">
            View All Flows and Access File Management
        </a>
    </div>
    
    <!-- Status Indicators -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <?php
        $status_colors = [
            'green' => ['bg' => '#d4edda', 'border' => '#28a745', 'icon' => '✅'],
            'yellow' => ['bg' => '#fff3cd', 'border' => '#ffc107', 'icon' => '⚠️'],
            'red' => ['bg' => '#f8d7da', 'border' => '#dc3545', 'icon' => '❌'],
        ];
        
        $check_labels = [
            'db_connection' => 'FLOSC DB Connection',
            'ivr_file' => 'Active IVR Messages MD file',
            'db_messages' => 'FLOSC DB Messages',
            'sync_status' => 'Active IVR Messages MD file ↔ FLOSC DB',
            'offer_sync' => 'IVR Offers ↔ FLOSC DB Offers',
            'api_endpoint' => 'REST API',
        ];
        
        foreach ($ivr_diagnostics as $check_id => $check): 
            $colors = $status_colors[$check['status']];
        ?>
        <div style="background: <?php echo esc_attr( $colors['bg'] ); ?>; border-left: 4px solid <?php echo esc_attr( $colors['border'] ); ?>; padding: 12px; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <strong><?php echo esc_html( $check_labels[$check_id] ); ?></strong>
                <span style="font-size: 18px;"><?php echo esc_html( $colors['icon'] ); ?></span>
            </div>
            <div style="font-size: 14px; color: #333; font-weight: 500;">
                <?php echo esc_html($check['message']); ?>
            </div>
            <?php if (!empty($check['details'])): ?>
            <div style="font-size: 11px; color: #667; margin-top: 5px; overflow-wrap: anywhere; word-break: break-word;">
                <?php foreach ($check['details'] as $detail): ?>
                    <?php if (!empty($detail)): ?>
                    <div style="margin: 2px 0;"><?php echo esc_html($detail); ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- v3.0.9: Action Buttons — framed as two distinct workflows -->
    <div style="border-top: 1px solid #dee2e6; padding-top: 20px;">

        <!-- Step 0: Compare (shared first step) -->
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <strong style="font-size: 13px;">Not sure what's different?</strong>
                <p style="margin: 4px 0 0; font-size: 12px; color: #555;">Compare the file and the DB before acting — shows new, changed, and unchanged messages.</p>
            </div>
            <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>" style="flex-shrink: 0;">
                <?php wp_nonce_field('flosc_preview_import'); ?>
                <button type="submit" name="flosc_preview_import" class="button button-secondary">
                    🔍 Compare File ↔ DB
                </button>
            </form>
        </div>

        <!-- Two workflow cards side by side -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">

            <!-- Workflow A: Edited in admin → push DB to file -->
            <div style="background: #eaf4fb; border: 2px solid #90caf9; border-radius: 8px; padding: 18px;">
                <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #1565c0;">Workflow A</p>
                <p style="margin: 0 0 10px; font-size: 14px; font-weight: 600; color: #1a1a1a;">I edited messages in this admin tab</p>
                <p style="margin: 0 0 14px; font-size: 12px; color: #444; line-height: 1.5;">
                    Your edits are already live on the frontend (DB is updated on Save).
                    Push the DB → file so the <code>.md</code> file stays in sync with your changes.
                </p>
                <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>">
                    <?php wp_nonce_field('flosc_export_ivr'); ?>
                    <button type="submit" name="flosc_export_ivr" class="button button-primary" style="width: 100%;">
                        🔄 Save DB → IVR File
                    </button>
                </form>
            </div>

            <!-- Workflow B: Edited the .md file → pull file into DB -->
            <div style="background: #f0faf0; border: 2px solid #a5d6a7; border-radius: 8px; padding: 18px;">
                <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #2e7d32;">Workflow B</p>
                <p style="margin: 0 0 10px; font-size: 14px; font-weight: 600; color: #1a1a1a;">I edited the <code>.md</code> file directly</p>
                <p style="margin: 0 0 14px; font-size: 12px; color: #444; line-height: 1.5;">
                    Merge now performs union sync: keep entries from both sides, then sync both sides to the same result.
                    If one side has more entries, those entries are preserved and copied to the other side so parity is restored.
                </p>
                <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>">
                    <?php wp_nonce_field('flosc_force_resync'); ?>
                    <button type="submit" name="flosc_force_resync" class="button button-primary" style="width: 100%; background: #2e7d32; border-color: #2e7d32;">
                        📥 Merge And Sync File ↔ DB
                    </button>
                </form>
            </div>

        </div>

        <!-- Utility row: Test API (small) -->
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <button type="button" id="flosc-test-api" class="button button-secondary" onclick="floscTestAPI()">
                🔌 Test API Endpoint
            </button>
            <span style="font-size: 12px; color: #888;">Check that the REST API is returning messages from the DB to the frontend chat.</span>
        </div>
            
        </div>
        
        <!-- Secondary Actions -->
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ddd; display: flex; gap: 10px; align-items: center;">
            <a href="<?php echo esc_url($ivr_management_phase_url); ?>" class="button">
                🔃 Refresh Diagnostics
            </a>
            <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This will clear ALL IVR messages from the FLOSC DB.\n\nA backup will be created first, but you will need to reload from a file to restore messages.\n\nAre you sure you want to clear the FLOSC DB?');">
                <?php wp_nonce_field('flosc_clear_ivr_db'); ?>
                <button type="submit" name="flosc_clear_ivr_db" class="button" style="color: #dc3545; border-color: #dc3545;">
                    🗑️ Clear FLOSC DB
                </button>
            </form>
            <span style="font-size: 11px; color: #999;">
                Last sync: <?php echo esc_html($flow_settings['ivr_last_import'] ?? 'Never'); ?>
            </span>
        </div>
    </div>
    
    <!-- API Test Result Area -->
    <div id="flosc-api-test-result" style="display: none; margin-top: 15px; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow: auto;">
    </div>
</div>

<?php ob_start(); ?>
function floscTestAPI() {
    const resultDiv = document.getElementById('flosc-api-test-result');
    const btn = document.getElementById('flosc-test-api');
    
    resultDiv.style.display = 'block';
    resultDiv.style.background = '#e9ecef';
    resultDiv.innerHTML = '⏳ Testing API endpoint...';
    btn.disabled = true;
    
    const apiUrl = '<?php echo esc_js(rest_url('flosc/v1/ivr-messages?phase=freeline')); ?>';
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            
            if (data.success && data.messages) {
                const msgCount = data.messages.length;
                const msgNames = data.messages.map(m => m.name || 'unnamed').join(', ');
                
                if (msgCount > 0) {
                    resultDiv.style.background = '#d4edda';
                    resultDiv.innerHTML = `✅ <strong>API Working!</strong><br><br>` +
                        `<strong>Messages returned:</strong> ${msgCount}<br>` +
                        `<strong>Names:</strong> ${msgNames}<br>` +
                        `<strong>User context:</strong> ${JSON.stringify(data.user_context)}<br><br>` +
                        `<details><summary>Full response</summary><pre>${JSON.stringify(data, null, 2)}</pre></details>`;
                } else {
                    resultDiv.style.background = '#fff3cd';
                    resultDiv.innerHTML = `⚠️ <strong>API responded but returned 0 messages</strong><br><br>` +
                        `This usually means condition evaluation is filtering everything out.<br>` +
                        `<strong>User context:</strong> ${JSON.stringify(data.user_context)}<br><br>` +
                        `Check that conditions like "is_visitor" are being evaluated correctly.`;
                }
            } else {
                resultDiv.style.background = '#f8d7da';
                resultDiv.innerHTML = `❌ <strong>API Error</strong><br><pre>${JSON.stringify(data, null, 2)}</pre>`;
            }
        })
        .catch(error => {
            btn.disabled = false;
            resultDiv.style.background = '#f8d7da';
            resultDiv.innerHTML = `❌ <strong>Fetch failed:</strong> ${error.message}`;
        });
}
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<h2>IVR Management — All Phases</h2>

<!-- File Management + Full Text Editor -->
<?php if ($ivr_management_view === 'all'): ?>
<div class="flosc-info-box" style="margin: 18px 0; padding: 16px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;">
    <h3 style="margin-top: 0;">IVR File Management</h3>
    <p style="margin: 6px 0 14px;">Refresh the file list, delete unwanted IVR files, and edit the full text of the active IVR file.</p>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
        <a href="<?php echo esc_url($ivr_management_all_phase_url); ?>" class="button">🔃 Refresh File List</a>
        <form method="post" action="<?php echo esc_url($ivr_management_all_phase_url); ?>" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center; margin:0;">
            <?php wp_nonce_field('flosc_upload_ivr_file'); ?>
            <input type="file" name="ivr_file_upload" accept=".md,text/markdown" required>
            <button type="submit" name="flosc_upload_ivr_file" class="button button-secondary">📤 Upload IVR .md</button>
        </form>
    </div>

    <table class="widefat striped" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th>File</th>
                <th style="width:110px;">Status</th>
                <th style="width:160px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($available_ivr_files as $ivr_filename):
            $is_active_row = ($ivr_filename === $active_ivr_file);
            $edit_url = esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($ivr_filename) . '&view=single'));
            $download_url = wp_nonce_url(
                esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($active_ivr_file) . '&view=all&flosc_download_ivr=' . urlencode($ivr_filename))),
                'flosc_download_ivr_' . $ivr_filename
            );
        ?>
            <tr>
                <td><code><?php echo esc_html($ivr_filename); ?></code></td>
                <td><?php echo $is_active_row ? 'Active' : 'Managed'; ?></td>
                <td>
                    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                        <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a>
                        <a href="<?php echo esc_url($download_url); ?>" class="button button-small">Download</a>
                        <form method="post" action="<?php echo esc_url($ivr_management_all_phase_url); ?>" style="display:inline; margin:0;">
                            <?php wp_nonce_field('flosc_duplicate_ivr_file'); ?>
                            <input type="hidden" name="duplicate_ivr_file" value="<?php echo esc_attr($ivr_filename); ?>">
                            <button type="submit" name="flosc_duplicate_ivr_file" class="button button-small">Duplicate</button>
                        </form>
                        <form method="post" action="<?php echo esc_url($ivr_management_all_phase_url); ?>" style="display:inline; margin:0;">
                            <?php wp_nonce_field('flosc_import_selected_ivr_file'); ?>
                            <input type="hidden" name="import_ivr_file" value="<?php echo esc_attr($ivr_filename); ?>">
                            <button type="submit" name="flosc_import_selected_ivr_file" class="button button-small">Merge And Sync File ↔ DB</button>
                        </form>
                        <form method="post" action="<?php echo esc_url($ivr_management_all_phase_url); ?>" style="display:inline; margin:0;" onsubmit="return confirm('Delete IVR file ' + <?php echo wp_json_encode($ivr_filename); ?> + '? This cannot be undone from this panel.');">
                            <?php wp_nonce_field('flosc_delete_ivr_file'); ?>
                            <input type="hidden" name="delete_ivr_file" value="<?php echo esc_attr($ivr_filename); ?>">
                            <button type="submit" name="flosc_delete_ivr_file" class="button button-small" style="color:#b91c1c; border-color:#b91c1c;">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    $active_ivr_full_text = '';
    if (file_exists($ivr_file_path)) {
        $active_ivr_full_text = file_get_contents($ivr_file_path);
        if ($active_ivr_full_text === false) {
            $active_ivr_full_text = '';
        }
    }
    ?>
    <form method="post" action="<?php echo esc_url($ivr_management_all_phase_url); ?>">
        <?php wp_nonce_field('flosc_save_full_ivr'); ?>
        <label for="ivr_full_text"><strong>Full Text Editor: <?php echo esc_html($active_ivr_file); ?></strong></label>
        <textarea id="ivr_full_text" name="ivr_full_text" rows="20" class="large-text code" style="font-family: monospace; margin-top: 8px;"><?php echo esc_textarea($active_ivr_full_text); ?></textarea>
        <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <button type="submit" name="flosc_save_full_ivr" class="button button-primary">💾 Save Full IVR File</button>
            <span style="font-size:12px; color:#475569;">After save, use <strong>Merge IVR File → DB</strong> to refresh runtime messages.</span>
        </div>
    </form>
</div>
<?php else: ?>
<div class="flosc-info-box" style="margin: 18px 0; padding: 16px; border: 1px solid #d1d5db; border-radius: 8px; background: #f8fafc;">
    <strong>IVR File Management</strong>
    <p style="margin: 6px 0 0;">Click <strong>View All Flows and Access File Management</strong> above to refresh/delete IVR files and use the full-text editor.</p>
</div>
<?php endif; ?>

<div class="flosc-info-box" style="margin-bottom: 20px;">
    <strong>💾 FLOSC IVR Messages</strong>
    <p>All messages across every phase, in one scrollable page. Click any message header to expand its editor. Save individually.</p>
    <p style="margin-top: 8px;"><strong>Workflow:</strong> Expand → Edit → Save → Changes go live and sync to IVR file</p>
</div>

<?php if ($import_preview !== null):
    // v3.0.9: Pre-compute field_diffs early so we can split "updated" into changed vs unchanged
    $field_diffs   = $import_preview['field_diffs'] ?? [];
    $changed_ids   = array_keys($field_diffs);                                          // IDs with actual field differences
    $unchanged_ids = array_values(array_diff($import_preview['updated'] ?? [], $changed_ids)); // IDs in both, content identical
    $after_merge_count = (int) ($import_preview['current_count'] ?? 0) + count($import_preview['added'] ?? []);
    $after_replace_count = (int) ($import_preview['incoming_count'] ?? 0);
    $replace_removed_count = count($import_preview['deleted'] ?? []);

    // Build full-entry views for clear compare and direction decisions.
    $db_messages_for_compare = $flow_settings['ivr_messages'] ?? [];
    $file_messages_for_compare = [];
    if (file_exists($ivr_file_path)) {
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
        $preview_parser = FLOSC_IVR_Parser::flosc_instance();
        $preview_markdown = file_get_contents($ivr_file_path);
        $preview_config = $preview_parser->flosc_parse($preview_markdown ?: '');
        $file_messages_for_compare = $preview_config['messages'] ?? [];
    }
?>
    <!-- Comparison Results -->
    <div class="flosc-import-preview" style="background: #fff3cd; padding: 20px; border-left: 5px solid #ffc107; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #856404;">📋 Comparison: Active IVR Messages MD file ↔ FLOSC DB</h3>

        <p style="background: white; padding: 10px; border: 1px solid #ddd;">
            This shows the differences between your <strong>Active IVR Messages MD file</strong> and the <strong>FLOSC DB</strong>.
            Merge performs union sync and ends in parity. Replace makes the DB match the file exactly (destructive when DB has extra entries).
        </p>

        <!-- Summary row counts -->
        <div style="background: white; padding: 15px; margin: 15px 0; border: 1px solid #ddd;">
            <h4 style="margin-top: 0;">Comparison Results:</h4>
            <ul style="margin: 5px 0 0 20px; line-height: 2;">

                <li>📊 <strong>FLOSC DB:</strong> <?php echo esc_html( (string) $import_preview['current_count'] ); ?> messages</li>
                <li>📄 <strong>Active IVR Messages MD file:</strong> <?php echo esc_html( (string) $import_preview['incoming_count'] ); ?> messages</li>

                <!-- New in file -->
                <li>✅ <strong>New in file:</strong> <?php echo count($import_preview['added']); ?>
                    <?php if (!empty($import_preview['added'])): ?>
                    <details style="display:inline-block; vertical-align:middle; margin-left:6px;">
                        <summary style="cursor:pointer; color:#0073aa; font-size:12px; list-style:none;">▼ show all</summary>
                        <div style="margin-top:6px; padding:6px 10px; background:#f0fff0; border:1px solid #c3e6cb; border-radius:3px; font-size:12px; line-height:1.8;">
                            <?php foreach ($import_preview['added'] as $id): ?>
                                <code style="display:inline-block; margin:2px 4px 2px 0;"><?php echo esc_html($id); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </li>

                <?php if (!empty($import_preview['added'])): ?>
                <li style="margin-top:6px;">
                    <details>
                        <summary style="cursor:pointer; color:#0073aa; font-size:12px;">Show full entries: New in file</summary>
                        <div style="margin-top:8px; display:grid; gap:10px;">
                            <?php foreach ($import_preview['added'] as $id): ?>
                                <div style="border:1px solid #cbd5e1; border-radius:4px; padding:8px; background:#f8fbff;">
                                    <div style="font-weight:600; margin-bottom:6px;"><?php echo esc_html($id); ?></div>
                                    <pre style="white-space:pre-wrap; word-break:break-word; margin:0; font-size:12px;"><?php echo esc_html(wp_json_encode($file_messages_for_compare[$id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </li>
                <?php endif; ?>

                <!-- Changed (actual field differences) -->
                <?php if (!empty($changed_ids)): ?>
                <li>🔄 <strong>Changed</strong> (content differs): <?php echo count($changed_ids); ?> — see field details below</li>
                <?php endif; ?>

                <!-- Unchanged (present in both, identical content) -->
                <?php if (!empty($unchanged_ids)): ?>
                <li>⚡ <strong>Present in both</strong> (unchanged): <?php echo count($unchanged_ids); ?>
                    <details style="display:inline-block; vertical-align:middle; margin-left:6px;">
                        <summary style="cursor:pointer; color:#666; font-size:12px; list-style:none;">▼ show all</summary>
                        <div style="margin-top:6px; padding:6px 10px; background:#f7f7f7; border:1px solid #ddd; border-radius:3px; font-size:12px; line-height:1.8;">
                            <?php foreach ($unchanged_ids as $id): ?>
                                <code style="display:inline-block; margin:2px 4px 2px 0;"><?php echo esc_html($id); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </li>
                <?php endif; ?>

                <!-- DB-only messages -->
                <?php if ($import_preview['has_deletions']): ?>
                <li style="color: #b45309;">↔ <strong>Only in DB:</strong> <?php echo count($import_preview['deleted']); ?>
                    <details style="display:inline-block; vertical-align:middle; margin-left:6px;">
                        <summary style="cursor:pointer; color:#b45309; font-size:12px; list-style:none;">▼ show all</summary>
                        <div style="margin-top:6px; padding:6px 10px; background:#fff7ed; border:1px solid #fdba74; border-radius:3px; font-size:12px; line-height:1.8;">
                            <?php foreach ($import_preview['deleted'] as $id): ?>
                                <code style="display:inline-block; margin:2px 4px 2px 0;"><?php echo esc_html($id); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <div style="margin-top:6px; font-size:12px; color:#7c2d12;">Merge keeps these messages and writes them into the IVR file so file ↔ DB parity is restored.</div>
                </li>

                <li>
                    <details>
                        <summary style="cursor:pointer; color:#b45309; font-size:12px;">Show full entries: Only in DB</summary>
                        <div style="margin-top:8px; display:grid; gap:10px;">
                            <?php foreach ($import_preview['deleted'] as $id): ?>
                                <div style="border:1px solid #fdba74; border-radius:4px; padding:8px; background:#fffaf5;">
                                    <div style="font-weight:600; margin-bottom:6px;"><?php echo esc_html($id); ?></div>
                                    <pre style="white-space:pre-wrap; word-break:break-word; margin:0; font-size:12px;"><?php echo esc_html(wp_json_encode($db_messages_for_compare[$id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </li>
                <?php else: ?>
                <li style="color: #2e7d32;">✓ No DB-only messages</li>
                <?php endif; ?>

                <li style="margin-top: 8px; border-top: 1px dashed #ddd; padding-top: 8px;">
                    <strong>After Merge And Sync:</strong> file = DB = <?php echo (int) $after_merge_count; ?> entries
                </li>
                <li>
                    <strong>After Replace:</strong> file = DB = <?php echo (int) $after_replace_count; ?> entries
                    <?php if ($replace_removed_count > 0): ?>
                        <span style="color:#b91c1c;">(removes <?php echo (int) $replace_removed_count; ?> DB-only entries)</span>
                    <?php endif; ?>
                </li>

            </ul>
        </div>

        <!-- v3.0.9: Field-level diff table — one <details> row per changed message -->
        <?php if (!empty($field_diffs)): ?>
        <div style="background: white; padding: 15px; margin: 15px 0; border: 1px solid #ddd;">
            <h4 style="margin-top: 0;">🔍 Field-Level Differences (<?php echo count($field_diffs); ?> message<?php echo count($field_diffs) !== 1 ? 's' : ''; ?> changed):</h4>
            <?php foreach ($field_diffs as $msg_id => $diffs): ?>
            <details style="margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px;">
                <summary style="padding: 8px 12px; background: #f7f7f7; cursor: pointer; font-weight: 600; list-style: none;">
                    ▶ <?php echo esc_html($msg_id); ?> — <?php echo count($diffs); ?> field<?php echo count($diffs) !== 1 ? 's' : ''; ?> changed
                </summary>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="text-align: left; padding: 6px 10px; border-bottom: 2px solid #ccc; width: 15%;">Field</th>
                            <th style="text-align: left; padding: 6px 10px; border-bottom: 2px solid #ccc; width: 42%;">DB Value</th>
                            <th style="text-align: left; padding: 6px 10px; border-bottom: 2px solid #ccc; width: 42%;">File Value</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($diffs as $field => $vals):
                        $db_display   = esc_html($vals['db']);
                        $file_display = esc_html($vals['file']);
                        if ($db_display === '') $db_display = '<em style="color:#999;">(empty)</em>';
                        if ($file_display === '') $file_display = '<em style="color:#999;">(empty)</em>';
                    ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 6px 10px; font-weight: 500;"><?php echo esc_html($field); ?></td>
                            <td style="padding: 6px 10px; background: #fff0f0; word-break: break-word;"><pre style="white-space:pre-wrap; margin:0;"><?php echo wp_kses_post( $db_display ); ?></pre></td>
                            <td style="padding: 6px 10px; background: #f0fff0; word-break: break-word;"><pre style="white-space:pre-wrap; margin:0;"><?php echo wp_kses_post( $file_display ); ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <details style="margin: 10px;">
                    <summary style="cursor:pointer; font-size:12px; color:#555;">Show full DB entry and file entry</summary>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px;">
                        <div style="border:1px solid #fecaca; border-radius:4px; padding:8px; background:#fff7f7;">
                            <div style="font-weight:600; margin-bottom:6px;">DB Entry</div>
                            <pre style="white-space:pre-wrap; word-break:break-word; margin:0; font-size:12px;"><?php echo esc_html(wp_json_encode($db_messages_for_compare[$msg_id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                        </div>
                        <div style="border:1px solid #bbf7d0; border-radius:4px; padding:8px; background:#f5fff7;">
                            <div style="font-weight:600; margin-bottom:6px;">File Entry</div>
                            <pre style="white-space:pre-wrap; word-break:break-word; margin:0; font-size:12px;"><?php echo esc_html(wp_json_encode($file_messages_for_compare[$msg_id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                        </div>
                    </div>
                </details>
            </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:15px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>" style="display:inline; margin:0;">
                <?php wp_nonce_field('flosc_confirm_import'); ?>
                <input type="hidden" name="flosc_import_mode" value="merge">
                <button type="submit" name="flosc_confirm_import" class="button button-primary">
                    ✅ Save File → DB (Merge And Sync)
                </button>
            </form>

            <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>" style="display:inline; margin:0;">
                <?php wp_nonce_field('flosc_export_ivr'); ?>
                <button type="submit" name="flosc_export_ivr" class="button button-secondary">
                    🔄 Save DB → IVR File
                </button>
            </form>

            <?php if ($import_preview['has_deletions']): ?>
            <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>" style="display:inline; margin:0;">
                <?php wp_nonce_field('flosc_confirm_import'); ?>
                <input type="hidden" name="flosc_import_mode" value="replace">
                <button type="submit" name="flosc_confirm_import" class="button" style="color:#b91c1c; border-color:#b91c1c;"
                        onclick="return confirm('Replace is destructive. Replace FLOSC DB with the IVR file and remove DB-only messages?');">
                    Replace DB To Match File
                </button>
            </form>
            <?php endif; ?>

            <a href="<?php echo esc_url($ivr_management_phase_url); ?>" class="button button-secondary">Cancel</a>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- ALL MESSAGES — SINGLE SCROLLABLE PAGE -->
<!-- ============================================ -->
<?php
$phase_meta = [
    'freeline' => ['icon' => '🌐', 'label' => 'Freeline', 'desc' => 'Visitor (not logged in) — Encourage quiz completion'],
    'login'    => ['icon' => '🔑', 'label' => 'Login',    'desc' => 'Post-quiz + Logged-in — Deliver free lesson, present offer'],
    'offer'    => ['icon' => '🏷️', 'label' => 'Offer',    'desc' => 'Sales pitch — Product offers and promotions'],
    'sale'     => ['icon' => '💳', 'label' => 'Sale',     'desc' => 'Post-purchase — Onboarding and welcome'],
    'content'  => ['icon' => '📚', 'label' => 'Content',  'desc' => 'Ongoing member support and engagement'],
];
$expand_id = $get['expand'] ?? $get['edit_message'] ?? null;
$total_count = count($messages);
?>

<!-- Styles in assets/css/flosc-admin.css -->

<p style="margin-bottom: 15px; color: #667;">
    <strong><?php echo esc_html( (string) $total_count ); ?></strong> messages across <?php echo esc_html( (string) count(array_filter($phases, fn($ids) => !empty($ids))) ); ?> phases
    <?php
    // Quick jump links
    $active_phases = [];
    foreach ($phase_meta as $pid => $pm) {
        $cnt = count($phases[$pid] ?? []);
        if ($cnt > 0) $active_phases[] = '<a href="#phase-' . esc_attr( $pid ) . '" style="text-decoration:none;">' . esc_html( $pm['icon'] . ' ' . $pm['label'] . ' (' . $cnt . ')' ) . '</a>';
    }
    if ($active_phases): ?>
        — Jump: <?php echo wp_kses_post( implode(' · ', $active_phases) ); ?>
    <?php endif; ?>
</p>

<?php foreach ($phase_meta as $phase_id => $pm): 
    $phase_msg_ids = $phases[$phase_id] ?? [];
?>
<div class="flosc-phase-section" id="phase-<?php echo esc_attr( $phase_id ); ?>">
    <!-- Phase Header -->
    <div class="flosc-phase-header">
        <span><?php echo esc_html( $pm['icon'] ); ?></span>
        <span><?php echo esc_html($pm['label']); ?></span>
        <span class="flosc-phase-count"><?php echo count($phase_msg_ids); ?></span>
        <span class="flosc-phase-desc"><?php echo esc_html($pm['desc']); ?></span>
    </div>
    
    <?php if (empty($phase_msg_ids)): ?>
        <div class="flosc-msg-card" style="padding: 20px; color: #999; font-style: italic; border-radius: 0;">
            No messages in this phase yet.
        </div>
    <?php endif; ?>
    
    <?php foreach ($phase_msg_ids as $msg_id):
        if (!isset($messages[$msg_id])) continue;
        $msg = $messages[$msg_id];
        $is_open = ($expand_id === $msg_id);
        $type_class = 'type-' . ($msg['type'] ?? 'auto');
        $safe_id = esc_attr($msg_id);
    ?>
    <div class="flosc-msg-card <?php echo esc_attr( $is_open ? 'is-open' : '' ); ?>" id="card-<?php echo esc_attr( $safe_id ); ?>">
        <div class="flosc-msg-card-header" onclick="floscToggleMsg('<?php echo esc_js($msg_id); ?>')">
            <span class="flosc-msg-toggle">▶</span>
            <span class="flosc-msg-name"><?php echo esc_html($msg['name'] ?? $msg_id); ?></span>
            <span class="flosc-msg-id"><?php echo esc_html($msg_id); ?></span>
            <span class="flosc-msg-type-badge <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html($msg['type'] ?? 'auto'); ?></span>
            <?php if (!empty($msg['conditions'])): ?>
                <span style="font-size: 11px; color: #8b5cf6;" title="<?php echo esc_attr($msg['conditions']); ?>">⚡ conditional</span>
            <?php endif; ?>
            <span class="flosc-msg-preview"><?php echo esc_html(wp_trim_words($msg['content'] ?? '', 12)); ?></span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($active_ivr_file) . '&view=' . urlencode($ivr_management_view) . '&ivr_phase=' . urlencode($active_phase) . '&delete_message=' . urlencode($msg_id) . '&phase=' . urlencode($phase_id) . '&_wpnonce=' . wp_create_nonce('flosc_delete_message_' . $msg_id))); ?>" 
               class="flosc-msg-delete" onclick="event.stopPropagation(); return confirm('Delete message: <?php echo esc_js($msg['name'] ?? $msg_id); ?>?');">✕ Delete</a>
        </div>
        
        <div class="flosc-msg-editor">
            <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>">
                <?php wp_nonce_field('flosc_save_ivr_message'); ?>
                <input type="hidden" name="message_id" value="<?php echo esc_attr( $safe_id ); ?>">
                
                <table class="form-table">
                    <tr>
                        <th>Phase</th>
                        <td>
                            <select name="message_phase">
                                <?php foreach (array_keys($phase_meta) as $p): ?>
                                    <option value="<?php echo esc_attr( $p ); ?>" <?php selected($msg['phase'] ?? $phase_id, $p); ?>><?php echo esc_html( ucfirst($p) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Message ID</th>
                        <td><input type="text" name="message_name" value="<?php echo esc_attr($msg['name'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <select name="message_type" onchange="floscToggleOfferFields(this, '<?php echo esc_js($msg_id); ?>')">
                                <option value="auto" <?php selected($msg['type'] ?? '', 'auto'); ?>>Auto (bot sends automatically)</option>
                                <option value="suggested_user_autoprompt" <?php selected($msg['type'] ?? '', 'suggested_user_autoprompt'); ?>>Pill Button (user clicks to send)</option>
                                <option value="offer" <?php selected($msg['type'] ?? '', 'offer'); ?>>Offer</option>
                                <option value="concierge" <?php selected($msg['type'] ?? '', 'concierge'); ?>>Concierge (keyword + optional password)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Conditions</th>
                        <td>
                            <input type="text" name="message_conditions" value="<?php echo esc_attr($msg['conditions'] ?? ''); ?>" class="large-text" placeholder="e.g. is_visitor && first_show_session">
                            <details style="margin-top: 6px; font-size: 12px; color: #666;">
                                <summary style="cursor: pointer; color: #2271b1;">Available conditions reference</summary>
                                <div style="margin-top: 6px; line-height: 1.8;">
                                    <strong>Boolean flags:</strong> <code>is_visitor</code>, <code>logged_in</code>, <code>quiz_taken</code>, <code>purchased</code>, <code>first_show_session</code>, <code>first_show_ever</code>, <code>offer_shown</code>, <code>offer_clicked</code>, <code>offer_dismissed</code>, <code>timer_expired</code>, <code>email_collected</code>, <code>has_active_sub</code><br>
                                    <strong>Numeric:</strong> <code>score &gt;= 80</code>, <code>message_count &gt; 3</code>, <code>session_seconds &gt; 60</code><br>
                                    <strong>String:</strong> <code>command == "take_quiz"</code>, <code>quiz_id == "ipa_quiz_01"</code><br>
                                    <strong>Operators:</strong> <code>&amp;&amp;</code> (AND), <code>||</code> (OR), <code>!</code> (NOT)
                                </div>
                            </details>
                        </td>
                    </tr>
                    <tr>
                        <th>Style</th>
                        <td>
                            <select name="message_style">
                                <?php foreach (['default','pill','button','chip','card'] as $s): ?>
                                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected($msg['style'] ?? 'default', $s); ?>><?php echo esc_html( ucfirst($s) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Icon</th>
                        <td><input type="text" name="message_icon" value="<?php echo esc_attr($msg['icon'] ?? ''); ?>" class="small-text" placeholder="💬"></td>
                    </tr>
                    <tr>
                        <th>User Input Prompt Label Text</th>
                        <td><input type="text" name="message_user_input" value="<?php echo esc_attr($msg['user_input'] ?? ''); ?>" class="regular-text" placeholder="Button text for pill buttons"></td>
                    </tr>
                    <tr>
                        <th>Keywords</th>
                        <td>
                            <input type="text" name="message_keywords" value="<?php echo esc_attr($msg['keywords'] ?? ''); ?>" class="large-text" placeholder="comma-separated trigger words">
                            <p class="description">Comma-separated words the chatbot matches against. For a Concierge message, this is the keyword that opens it.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Chatbot Response Content</th>
                        <td>
                            <textarea name="message_content" rows="4" class="large-text"><?php echo esc_textarea($msg['content'] ?? ''); ?></textarea>
                            <p class="description">Variables: {name}, {score}, {product_name}, {price}</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Action After Chatbot Response</th>
                        <td>
                            <input type="text" name="message_action" value="<?php echo esc_attr($msg['action'] ?? ''); ?>" class="regular-text" placeholder="show_offer:offer_001, start_quiz, navigate:/lessons">
                            <details style="margin-top: 6px; font-size: 12px; color: #666;">
                                <summary style="cursor: pointer; color: #2271b1;">Available actions reference</summary>
                                <div style="margin-top: 6px; line-height: 1.8;">
                                    <strong>Quiz:</strong> <code>open_quiz:{id}</code>, <code>start_quiz</code><br>
                                    <strong>Offers:</strong> <code>show_offer:{id}</code>, <code>checkout:{id}</code><br>
                                    <strong>Navigation:</strong> <code>navigate:{url}</code>, <code>open_registration</code>, <code>open_free_lesson</code><br>
                                    <strong>Chat flow:</strong> <code>collect_email</code>, <code>collect_name</code>, <code>sandbox_purchase</code><br>
                                    <strong>Other:</strong> <code>open_login</code>, <code>dismiss</code>, <code>close_chat</code>
                                </div>
                            </details>
                        </td>
                    </tr>
                </table>
                
                <!-- Offer-specific fields -->
                <div class="flosc-offer-fields-inner" id="offer-fields-<?php echo esc_attr( $safe_id ); ?>" style="<?php echo esc_attr( ($msg['type'] ?? '') === 'offer' ? '' : 'display:none;' ); ?>">
                    <h4 style="margin: 0 0 10px;">🏷️ Offer Fields</h4>
                    <table class="form-table" style="margin: 0;">
                        <tr><th>Offer ID</th><td><input type="text" name="message_offer_id" value="<?php echo esc_attr($msg['offer_id'] ?? ''); ?>" class="regular-text" placeholder="full_access"></td></tr>
                        <tr><th>Price</th><td><input type="text" name="message_price" value="<?php echo esc_attr($msg['price'] ?? ''); ?>" class="small-text" placeholder="49"></td></tr>
                        <tr><th>Discount Price</th><td><input type="text" name="message_discount_price" value="<?php echo esc_attr($msg['discount_price'] ?? ''); ?>" class="small-text" placeholder="24.50"></td></tr>
                        <tr><th>Timer (sec)</th><td><input type="number" name="message_timer" value="<?php echo esc_attr($msg['timer'] ?? ''); ?>" class="small-text" placeholder="900"></td></tr>
                        <tr>
                            <th>Display Format</th>
                            <td>
                                <select name="message_display_format">
                                    <?php foreach (['card','pill','compact','banner','featured','text','inline-checkout'] as $fmt): ?>
                                        <option value="<?php echo esc_attr( $fmt ); ?>" <?php selected($msg['display_format'] ?? 'card', $fmt); ?>><?php echo esc_html( ucfirst($fmt) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Content Source</th>
                            <td>
                                <input type="text" name="message_html_file" value="<?php echo esc_attr($msg['html_file'] ?? ''); ?>" class="regular-text" placeholder="offer-page.html" style="margin-bottom: 4px;"><br>
                                <input type="text" name="message_woo_product" value="<?php echo esc_attr($msg['woo_product'] ?? ''); ?>" class="small-text" placeholder="WooCommerce Product ID" style="margin-bottom: 4px;">
                                <input type="number" name="message_post_id" value="<?php echo esc_attr($msg['post_id'] ?? ''); ?>" class="small-text" placeholder="WP Post ID">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Concierge-specific fields -->
                <div class="flosc-concierge-fields-inner" id="concierge-fields-<?php echo esc_attr( $safe_id ); ?>" style="<?php echo esc_attr( ($msg['type'] ?? '') === 'concierge' ? '' : 'display:none;' ); ?>">
                    <h4 style="margin: 0 0 10px;">🔐 Concierge Fields</h4>
                    <table class="form-table" style="margin: 0;">
                        <tr><th>Individual Message Password</th><td><input type="text" name="message_individual_password" value="<?php echo esc_attr($msg['individual_message_password'] ?? ''); ?>" class="regular-text" placeholder="usually blank; exact, case-sensitive"></td></tr>
                        <tr><th>Password Prompt</th><td><input type="text" name="message_password_prompt" value="<?php echo esc_attr($msg['password_prompt'] ?? ''); ?>" class="large-text" placeholder="What the bot asks when the keyword is used"></td></tr>
                        <tr><th>Password Success</th><td><input type="text" name="message_password_success" value="<?php echo esc_attr($msg['password_success'] ?? ''); ?>" class="large-text" placeholder="Affirmation shown just before the content delivers"></td></tr>
                        <tr><th>Max Tries</th><td><input type="number" name="message_password_max_tries" value="<?php echo esc_attr($msg['password_max_tries'] ?? 3); ?>" class="small-text" min="1"></td></tr>
                        <tr>
                            <th>Retry Messages</th>
                            <td>
                                <textarea name="message_password_retry" rows="3" class="large-text" placeholder="One line per try. {try} and {max} are filled in."><?php echo esc_textarea(implode("\n", (array) ($msg['password_retry_messages'] ?? array()))); ?></textarea>
                                <p class="description">One retry message per line. {try}/{max} are substituted. The final line shows on the last miss, then the gate resets to normal chat.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="display: flex; gap: 8px; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee;">
                    <button type="submit" name="save_ivr_message" class="button button-primary">💾 Save</button>
                    <span style="font-size: 11px; color: #999; margin-left: auto;">ID: <?php echo esc_html($msg_id); ?></span>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Add New Message to this phase -->
    <?php $new_id = 'new_' . $phase_id . '_' . time(); ?>
    <div class="flosc-msg-card" id="card-<?php echo esc_attr($new_id); ?>" style="border-radius: 0;">
        <div class="flosc-msg-editor" id="editor-new-<?php echo esc_attr( $phase_id ); ?>" style="display: none;">
            <form method="post" action="<?php echo esc_url($ivr_management_phase_url); ?>">
                <?php wp_nonce_field('flosc_save_ivr_message'); ?>
                <input type="hidden" name="message_id" value="<?php echo esc_attr($new_id); ?>">
                <h4 style="margin: 0 0 12px;">✨ New <?php echo esc_html($pm['label']); ?> Message</h4>
                <table class="form-table">
                    <tr>
                        <th>Phase</th>
                        <td>
                            <select name="message_phase">
                                <?php foreach (array_keys($phase_meta) as $p): ?>
                                    <option value="<?php echo esc_attr( $p ); ?>" <?php selected($phase_id, $p); ?>><?php echo esc_html( ucfirst($p) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Message ID</th><td><input type="text" name="message_name" value="" class="regular-text" required placeholder="Welcome Message"></td></tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <select name="message_type" onchange="floscToggleOfferFields(this, '<?php echo esc_js($new_id); ?>')">
                                <option value="auto">Auto (bot sends automatically)</option>
                                <option value="suggested_user_autoprompt">Pill Button (user clicks to send)</option>
                                <option value="offer">Offer</option>
                                <option value="concierge">Concierge (keyword + optional password)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Conditions</th>
                        <td>
                            <input type="text" name="message_conditions" class="large-text" placeholder="e.g. is_visitor && first_show_session">
                            <details style="margin-top: 6px; font-size: 12px; color: #666;">
                                <summary style="cursor: pointer; color: #2271b1;">Available conditions reference</summary>
                                <div style="margin-top: 6px; line-height: 1.8;">
                                    <strong>Boolean flags:</strong> <code>is_visitor</code>, <code>logged_in</code>, <code>quiz_taken</code>, <code>purchased</code>, <code>first_show_session</code>, <code>first_show_ever</code>, <code>offer_shown</code>, <code>offer_clicked</code>, <code>offer_dismissed</code>, <code>timer_expired</code>, <code>email_collected</code>, <code>has_active_sub</code><br>
                                    <strong>Numeric:</strong> <code>score &gt;= 80</code>, <code>message_count &gt; 3</code>, <code>session_seconds &gt; 60</code><br>
                                    <strong>String:</strong> <code>command == "take_quiz"</code>, <code>quiz_id == "ipa_quiz_01"</code><br>
                                    <strong>Operators:</strong> <code>&amp;&amp;</code> (AND), <code>||</code> (OR), <code>!</code> (NOT)
                                </div>
                            </details>
                        </td>
                    </tr>
                    <tr>
                        <th>Style</th>
                        <td>
                            <select name="message_style">
                                <?php foreach (['default','pill','button','chip','card'] as $s): ?>
                                    <option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( ucfirst($s) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Icon</th><td><input type="text" name="message_icon" class="small-text" placeholder="💬"></td></tr>
                    <tr><th>User Input Prompt Label Text</th><td><input type="text" name="message_user_input" class="regular-text" placeholder="Button text for pill buttons"></td></tr>
                    <tr><th>Keywords</th><td><input type="text" name="message_keywords" class="large-text" placeholder="comma-separated trigger words (the keyword that opens a Concierge message)"></td></tr>
                    <tr><th>Chatbot Response Content</th><td><textarea name="message_content" rows="4" class="large-text" placeholder="Type your message content here..."></textarea></td></tr>
                    <tr>
                        <th>Action After Chatbot Response</th>
                        <td>
                            <input type="text" name="message_action" class="regular-text" placeholder="show_offer:offer_001">
                            <details style="margin-top: 6px; font-size: 12px; color: #666;">
                                <summary style="cursor: pointer; color: #2271b1;">Available actions reference</summary>
                                <div style="margin-top: 6px; line-height: 1.8;">
                                    <strong>Quiz:</strong> <code>open_quiz:{id}</code>, <code>start_quiz</code><br>
                                    <strong>Offers:</strong> <code>show_offer:{id}</code>, <code>checkout:{id}</code><br>
                                    <strong>Navigation:</strong> <code>navigate:{url}</code>, <code>open_registration</code>, <code>open_free_lesson</code><br>
                                    <strong>Chat flow:</strong> <code>collect_email</code>, <code>collect_name</code>, <code>sandbox_purchase</code><br>
                                    <strong>Other:</strong> <code>open_login</code>, <code>dismiss</code>, <code>close_chat</code>
                                </div>
                            </details>
                        </td>
                    </tr>
                </table>
                
                <div class="flosc-offer-fields-inner" id="offer-fields-<?php echo esc_attr($new_id); ?>" style="display:none;">
                    <h4 style="margin: 0 0 10px;">🏷️ Offer Fields</h4>
                    <table class="form-table" style="margin: 0;">
                        <tr><th>Offer ID</th><td><input type="text" name="message_offer_id" class="regular-text" placeholder="full_access"></td></tr>
                        <tr><th>Price</th><td><input type="text" name="message_price" class="small-text" placeholder="49"></td></tr>
                        <tr><th>Discount Price</th><td><input type="text" name="message_discount_price" class="small-text" placeholder="24.50"></td></tr>
                        <tr><th>Timer (sec)</th><td><input type="number" name="message_timer" class="small-text" placeholder="900"></td></tr>
                        <tr><th>Display Format</th><td>
                            <select name="message_display_format">
                                <?php foreach (['card','pill','compact','banner','featured','text','inline-checkout'] as $fmt): ?>
                                    <option value="<?php echo esc_attr( $fmt ); ?>"><?php echo esc_html( ucfirst($fmt) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Content Source</th><td>
                            <input type="text" name="message_html_file" class="regular-text" placeholder="offer-page.html" style="margin-bottom:4px;"><br>
                            <input type="text" name="message_woo_product" class="small-text" placeholder="WooCommerce Product ID" style="margin-bottom:4px;">
                            <input type="number" name="message_post_id" class="small-text" placeholder="WP Post ID">
                        </td></tr>
                    </table>
                </div>
                
                <!-- Concierge-specific fields -->
                <div class="flosc-concierge-fields-inner" id="concierge-fields-<?php echo esc_attr($new_id); ?>" style="display:none;">
                    <h4 style="margin: 0 0 10px;">🔐 Concierge Fields</h4>
                    <table class="form-table" style="margin: 0;">
                        <tr><th>Individual Message Password</th><td><input type="text" name="message_individual_password" class="regular-text" placeholder="usually blank; exact, case-sensitive"></td></tr>
                        <tr><th>Password Prompt</th><td><input type="text" name="message_password_prompt" class="large-text" placeholder="What the bot asks when the keyword is used"></td></tr>
                        <tr><th>Password Success</th><td><input type="text" name="message_password_success" class="large-text" placeholder="Affirmation shown just before the content delivers"></td></tr>
                        <tr><th>Max Tries</th><td><input type="number" name="message_password_max_tries" value="3" class="small-text" min="1"></td></tr>
                        <tr><th>Retry Messages</th><td>
                            <textarea name="message_password_retry" rows="3" class="large-text" placeholder="One line per try. {try} and {max} are filled in."></textarea>
                            <p class="description">One retry message per line. {try}/{max} are substituted. The final line shows on the last miss, then the gate resets.</p>
                        </td></tr>
                    </table>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee;">
                    <button type="submit" name="save_ivr_message" class="button button-primary">💾 Save</button>
                    <button type="button" class="button" onclick="document.getElementById('editor-new-<?php echo esc_js( $phase_id ); ?>').style.display='none';">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <button type="button" class="flosc-add-msg-btn" onclick="document.getElementById('editor-new-<?php echo esc_js( $phase_id ); ?>').style.display='block'; this.style.display='none';">
        + Add <?php echo esc_html($pm['label']); ?> Message
    </button>
</div>
<?php endforeach; ?>

<?php ob_start(); ?>
function floscToggleMsg(id) {
    const card = document.getElementById('card-' + id);
    if (!card) return;
    card.classList.toggle('is-open');
}
function floscToggleOfferFields(selectEl, msgId) {
    const panel = document.getElementById('offer-fields-' + msgId);
    if (panel) panel.style.display = selectEl.value === 'offer' ? '' : 'none';
    const cpanel = document.getElementById('concierge-fields-' + msgId);
    if (cpanel) cpanel.style.display = selectEl.value === 'concierge' ? '' : 'none';
}
// Auto-scroll to expanded message on page load
document.addEventListener('DOMContentLoaded', function() {
    const open = document.querySelector('.flosc-msg-card.is-open');
    if (open) open.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
