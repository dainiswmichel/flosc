<?php
/**
 * FLOSC IVR Management Tab v1.2.9
 *
 * Uses the IVR file selected in the Flow dropdown.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('flosc_resolve_ivr_file_path')) {
    function flosc_resolve_ivr_file_path($flosc_ivr_filename) {
        $flosc_ivr_filename = sanitize_file_name(trim((string) $flosc_ivr_filename));
        // Per WordPress.org policy: runtime-generated files must be written to uploads only.
        // flosc_data_dir() returns the writable uploads directory or '' when unavailable.
        $uploads_dir = function_exists('flosc_data_dir') ? flosc_data_dir() : '';
        $flosc_plugin_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';

        $uploads_path = ('' !== $uploads_dir) ? $uploads_dir . $flosc_ivr_filename : '';
        if ($uploads_path !== '' && file_exists($uploads_path)) {
            return $uploads_path;
        }

        if (function_exists('flosc_config_file')) {
            $resolved = flosc_config_file($flosc_ivr_filename);
            if (!empty($resolved) && file_exists($resolved)) {
                return $resolved;
            }
        }

        $flosc_plugin_path = $flosc_plugin_dir . $flosc_ivr_filename;
        if ($flosc_ivr_filename !== '' && file_exists($flosc_plugin_path)) {
            return $flosc_plugin_path;
        }

        return $uploads_path;
    }
}

if (!function_exists('flosc_get_new_ivr_template_content')) {
    function flosc_get_new_ivr_template_content() {
        $flosc_template_candidates = [
            FLOSC_PLUGIN_DIR . 'ai_configuration_files/flosc_starter_ivr.md',
            FLOSC_PLUGIN_DIR . 'ai_configuration_files/flosc_default_friendly_ivr.md',
            FLOSC_PLUGIN_DIR . 'ai_configuration_files/flosc_ai_ivr.md',
        ];

        foreach ($flosc_template_candidates as $flosc_template_path) {
            if (file_exists($flosc_template_path) && is_readable($flosc_template_path)) {
                $flosc_template_content = file_get_contents($flosc_template_path);
                if ($flosc_template_content !== false && trim($flosc_template_content) !== '') {
                    return $flosc_template_content;
                }
            }
        }

        return "# FLOSC Starter IVR Configuration\n"
            . "# Rename, customize, and expand this file for your flow.\n\n"
            . "---\n\n"
            . "## Welcome\n"
            . "MessageName: starter_welcome\n"
            . "MessageType: auto\n"
            . "MessageStyle: card\n"
            . "MessageContent: Welcome. This is your new starter IVR file.\n"
            . "MessageConditions: is_visitor && first_show_session\n";
    }
}

$flosc_get = wp_unslash($_GET);
$flosc_post = wp_unslash($_POST);

// v1.2.8: Resolve active IVR file from explicit request first, then context fallback.
$flosc_requested_ivr_file = sanitize_file_name((string)($flosc_get['ivr'] ?? ''));
$flosc_active_ivr_file = $flosc_requested_ivr_file !== ''
    ? $flosc_requested_ivr_file
    : (($GLOBALS['flosc_current_ivr'] ?? '') !== '' ? sanitize_file_name((string)$GLOBALS['flosc_current_ivr']) : 'flosc_default_ivr.md');
$GLOBALS['flosc_current_ivr'] = $flosc_active_ivr_file;
// Per WordPress.org policy: writable paths must be in uploads only.
$flosc_ivr_dir = function_exists('flosc_data_dir') ? flosc_data_dir() : '';
$flosc_ivr_file_write_path = ('' !== $flosc_ivr_dir) ? $flosc_ivr_dir . $flosc_active_ivr_file : '';
$flosc_ivr_file_path = flosc_resolve_ivr_file_path($flosc_active_ivr_file);

// Per-flow settings
$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_flow_key = $GLOBALS['flosc_settings_key'] ?? '';
$flosc_ivr_management_view = isset($flosc_get['view']) ? sanitize_text_field($flosc_get['view']) : 'single';
if (!in_array($flosc_ivr_management_view, ['single', 'all'], true)) {
    $flosc_ivr_management_view = 'single';
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
        $flosc_parser = FLOSC_IVR_Parser::flosc_instance();
        $markdown = file_get_contents($ivr_file);
        $config = $flosc_parser->flosc_parse($markdown);
        
        $file_message_count = count($config['messages'] ?? []);
        
        if ($file_message_count > 0) {
            $diagnostics['ivr_file'] = [
                'status' => 'green',
                'message' => "$file_message_count messages",
                'details' => [
                    "File size: " . number_format($file_size) . " bytes",
                    "Last modified: $file_modified",
                    "Phases: " . implode(', ', array_keys(array_filter($config['phases'], fn($flosc_p) => !empty($flosc_p))))
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
    $flosc_fs = $GLOBALS['flosc_current_settings'] ?? [];
    $db_messages = $flosc_fs['ivr_messages'] ?? [];
    $db_phases = $flosc_fs['ivr_phases'] ?? [];
    $last_import = $flosc_fs['ivr_last_import'] ?? 'Never';
    
    if (!empty($db_messages)) {
        $flosc_phase_counts = [];
        foreach ($db_phases as $flosc_phase => $ids) {
            if (!empty($ids)) {
                $flosc_phase_counts[] = "$flosc_phase: " . count($ids);
            }
        }
        
        $diagnostics['db_messages'] = [
            'status' => 'green',
            'message' => count($db_messages) . ' messages',
            'details' => [
                "Last sync: $last_import",
                "By phase: " . implode(', ', $flosc_phase_counts)
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

            // Compare with normalized defaults so sparse DB rows and parser-defaulted
            // file rows are treated as equivalent when they are semantically identical.
            $normalize_for_compare = static function ($msg) {
                if (!is_array($msg)) {
                    $msg = [];
                }

                $normalized = [
                    'name'           => (string) ($msg['name'] ?? ''),
                    'type'           => (string) ($msg['type'] ?? 'auto'),
                    'style'          => (string) ($msg['style'] ?? 'pill'),
                    'panel'          => (string) ($msg['panel'] ?? ''),
                    'icon'           => (string) ($msg['icon'] ?? ''),
                    'user_input'     => (string) ($msg['user_input'] ?? ''),
                    'keywords'       => (string) ($msg['keywords'] ?? ''),
                    'action'         => (string) ($msg['action'] ?? ''),
                    'conditions'     => (string) ($msg['conditions'] ?? 'always'),
                    'phase'          => (string) ($msg['phase'] ?? 'freeline'),
                    'offer_id'       => (string) ($msg['offer_id'] ?? ''),
                    'price'          => (string) ($msg['price'] ?? ''),
                    'discount_price' => (string) ($msg['discount_price'] ?? ''),
                    'timer'          => (string) (isset($msg['timer']) ? intval($msg['timer']) : ''),
                    'display_format' => (string) ($msg['display_format'] ?? ''),
                    'content'        => (string) ($msg['content'] ?? ''),
                ];

                $normalized['title'] = (string) ($msg['title'] ?? $normalized['name']);

                if (strtolower(trim($normalized['type'])) === 'offer' && $normalized['display_format'] === '') {
                    $normalized['display_format'] = 'card';
                }

                $normalized['content'] = trim((string) preg_replace('/\r\n?|\n/', "\n", $normalized['content']));

                return $normalized;
            };

            $mismatches = [];
            foreach ($file_ids as $id) {
                $file_msg = $normalize_for_compare($config['messages'][$id] ?? []);
                $db_msg   = $normalize_for_compare($db_messages[$id] ?? []);
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
            $flosc_details = [];
            if (!empty($in_file_not_db)) {
                $flosc_details[] = 'In file only: ' . implode(', ', array_slice($in_file_not_db, 0, 3));
            }
            if (!empty($in_db_not_file)) {
                $flosc_details[] = 'In FLOSC DB only: ' . implode(', ', array_slice($in_db_not_file, 0, 3));
            }
            $flosc_details[] = 'Use "Compare" to see full details.';
            $diagnostics['sync_status'] = [
                'status' => 'yellow',
                'message' => 'Out of sync',
                'details' => $flosc_details
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
    $db_offers = $flosc_fs['offers'] ?? [];
    if (isset($config)) {
        $ivr_offers = [];
        foreach (($config['messages'] ?? []) as $message_id => $flosc_msg) {
            $flosc_msg_type = strtolower(trim((string)($flosc_msg['type'] ?? '')));
            if ($flosc_msg_type !== 'offer') {
                continue;
            }

            $offer_id = sanitize_key((string)($flosc_msg['offer_id'] ?? $message_id));
            if ($offer_id === '') {
                continue;
            }

            $ivr_offers[$offer_id] = [
                'name' => trim((string)($flosc_msg['title'] ?? ($flosc_msg['name'] ?? ''))),
                'description' => trim((string)($flosc_msg['content'] ?? '')),
                'display_format' => trim((string)($flosc_msg['display_format'] ?? '')),
                'condition' => trim((string)($flosc_msg['conditions'] ?? '')),
                'reveal_phrase' => trim((string)($flosc_msg['user_input'] ?? '')),
                'icon' => trim((string)($flosc_msg['icon'] ?? '')),
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
            $flosc_details = [];
            $flosc_details[] = 'In IVR only: ' . implode(', ', array_slice($in_ivr_not_registry, 0, 3));
            $flosc_details[] = 'Open Offers tab and create these offer IDs in the flow offers registry.';
            $diagnostics['offer_sync'] = [
                'status' => 'yellow',
                'message' => 'Out of sync',
                'details' => $flosc_details
            ];
        } else {
            $flosc_offer_fields = ['name', 'description', 'display_format', 'condition', 'reveal_phrase', 'icon'];
            $field_mismatches = [];

            foreach ($ivr_offer_ids as $offer_id) {
                foreach ($flosc_offer_fields as $field) {
                    if ((string)($ivr_offers[$offer_id][$field] ?? '') !== (string)($registry_offers[$offer_id][$field] ?? '')) {
                        $field_mismatches[] = $offer_id;
                        break;
                    }
                }
            }

            if (empty($field_mismatches)) {
                if (!empty($in_registry_not_ivr)) {
                    $flosc_details = ['In FLOSC DB only: ' . implode(', ', array_slice($in_registry_not_ivr, 0, 3))];
                    if (count($in_registry_not_ivr) > 3) {
                        $flosc_details[] = '... and ' . (count($in_registry_not_ivr) - 3) . ' more';
                    }
                    $flosc_details[] = 'Offer registry contains IDs not referenced in the active IVR file.';
                    $diagnostics['offer_sync'] = [
                        'status' => 'yellow',
                        'message' => 'Out of sync',
                        'details' => $flosc_details
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
            $flosc_msg_count = count($data['messages']);
            $diagnostics['api_endpoint'] = [
                'status' => 'green',
                'message' => 'Working ✓',
                'details' => [
                    "Returns $flosc_msg_count messages for freeline phase",
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
if (isset($flosc_post['flosc_upload_ivr_file']) && isset($_FILES['ivr_file_upload'])) {
    check_admin_referer('flosc_upload_ivr_file');
    
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- source array is validated by wp_handle_upload(), extension and mime checks below
    $flosc_uploaded_file = $_FILES['ivr_file_upload'];
    
    if ($flosc_uploaded_file['error'] === UPLOAD_ERR_OK) {
        $flosc_filename = sanitize_file_name($flosc_uploaded_file['name']);

        // Ensure uploaded file is markdown.
        if (strtolower((string) pathinfo($flosc_filename, PATHINFO_EXTENSION)) !== 'md') {
            add_settings_error('flosc_settings', 'upload_failed', 'Only .md files are allowed.', 'error');
        } else {
            $flosc_target_path = $flosc_ivr_dir . $flosc_filename;

            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $flosc_upload_overrides = [
                'test_form' => false,
                'mimes'     => ['md' => 'text/markdown'],
            ];
            $flosc_handled_upload = wp_handle_upload($flosc_uploaded_file, $flosc_upload_overrides);

            if (isset($flosc_handled_upload['error'])) {
                add_settings_error('flosc_settings', 'upload_failed', 'Upload failed: ' . $flosc_handled_upload['error'], 'error');
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- copy uploaded temp file into managed IVR directory
            } elseif (!empty($flosc_handled_upload['file']) && @copy($flosc_handled_upload['file'], $flosc_target_path)) {
                wp_delete_file($flosc_handled_upload['file']);
                if ($flosc_flow_key) {
                    $flosc_fs = get_option($flosc_flow_key, []);
                    $flosc_fs['active_ivr_file'] = $flosc_filename;
                    $flosc_fs['ivr_file'] = $flosc_filename;
                    update_option($flosc_flow_key, $flosc_fs);
                }
                add_settings_error('flosc_settings', 'upload_success', 'Uploaded and set as active: ' . $flosc_filename, 'success');
            } else {
                add_settings_error('flosc_settings', 'upload_failed', 'Failed to save uploaded file.', 'error');
            }
        }
    } else {
        add_settings_error('flosc_settings', 'upload_failed', 'File upload error: ' . $flosc_uploaded_file['error'], 'error');
    }
}

// Handle new IVR file creation from starter template
if (isset($flosc_post['flosc_create_ivr_file']) && isset($flosc_post['new_ivr_file'])) {
    check_admin_referer('flosc_create_ivr_file');

    $flosc_new_file_input = strtolower(trim((string) $flosc_post['new_ivr_file']));
    $flosc_new_file_input = sanitize_file_name($flosc_new_file_input);
    $flosc_new_file_base = preg_replace('/\.md$/', '', $flosc_new_file_input);
    if ($flosc_new_file_base === null) {
        $flosc_new_file_base = '';
    }
    if ($flosc_new_file_base !== '' && substr($flosc_new_file_base, -4) !== '_ivr') {
        $flosc_new_file_base .= '_ivr';
    }
    $flosc_new_file = $flosc_new_file_base !== '' ? $flosc_new_file_base . '.md' : '';

    if (!preg_match('/^[a-z0-9_-]+_ivr\.md$/', $flosc_new_file)) {
        add_settings_error(
            'flosc_settings',
            'create_invalid_name',
            'Invalid filename. Use lowercase letters, numbers, underscores, or hyphens. You can enter "lesaep" or "lesaep_ivr.md" and FLOSC will create lesaep_ivr.md.',
            'error'
        );
    } elseif ('' === $flosc_ivr_dir) {
        add_settings_error('flosc_settings', 'create_uploads_unavailable', 'Cannot create IVR file because uploads storage is unavailable.', 'error');
    } else {
        $flosc_new_path = $flosc_ivr_dir . $flosc_new_file;
        if (file_exists($flosc_new_path)) {
            add_settings_error('flosc_settings', 'create_exists', 'File already exists: ' . esc_html($flosc_new_file), 'error');
        } else {
            $flosc_template_content = flosc_get_new_ivr_template_content();
            $flosc_create_ok = function_exists('flosc_write_data_file')
                ? flosc_write_data_file($flosc_new_path, $flosc_template_content)
                : false;

            if (!$flosc_create_ok) {
                add_settings_error('flosc_settings', 'create_failed', 'Could not create new IVR file. Check uploads permissions.', 'error');
            } else {
                add_settings_error('flosc_settings', 'create_success', 'Created new IVR file from starter template: ' . esc_html($flosc_new_file), 'success');
            }
        }
    }
}

// Handle explicit file import from IVR File Management (selected file -> FLOSC DB)
if (isset($flosc_post['flosc_import_selected_ivr_file']) && isset($flosc_post['import_ivr_file'])) {
    check_admin_referer('flosc_import_selected_ivr_file');

    $flosc_selected_file = sanitize_file_name($flosc_post['import_ivr_file']);
    $flosc_selected_path = $flosc_ivr_dir . $flosc_selected_file;

    if (!file_exists($flosc_selected_path)) {
        add_settings_error('flosc_settings', 'import_selected_failed', 'Selected IVR file not found: ' . $flosc_selected_file, 'error');
    } else {
        $flosc_result = flosc_import_ivr_to_database(false, $flosc_selected_path, $flosc_flow_key, 'replace');
        if ($flosc_result['success']) {
            if ($flosc_flow_key) {
                $flosc_fs = get_option($flosc_flow_key, []);
                $flosc_fs['active_ivr_file'] = $flosc_selected_file;
                $flosc_fs['ivr_file'] = $flosc_selected_file;
                update_option($flosc_flow_key, $flosc_fs);
                $GLOBALS['flosc_current_settings'] = $flosc_fs;
                $flosc_flow_settings = $flosc_fs;
            }
            add_settings_error('flosc_settings', 'import_selected_success', 'Imported selected IVR file → FLOSC DB: ' . esc_html($flosc_selected_file), 'success');
        } else {
            add_settings_error('flosc_settings', 'import_selected_failed', 'Import failed: ' . esc_html($flosc_result['message']), 'error');
        }
    }
}

// Handle changing active IVR file
if (isset($flosc_post['flosc_change_active_file']) && isset($flosc_post['ivr_file_select'])) {
    check_admin_referer('flosc_change_active_file');
    
    $flosc_selected_file = sanitize_file_name($flosc_post['ivr_file_select']);
    $flosc_file_path = flosc_resolve_ivr_file_path($flosc_selected_file);
    
    if (file_exists($flosc_file_path)) {
        // v1.2.6: Save to flow if in flow context, otherwise save globally
        $flosc_editing_flow_id = $GLOBALS['flosc_editing_flow'] ?? null;
        
        if ($flosc_editing_flow_id) {
            // Update flow's ivr_file setting
            flosc_flows()->update_flow($flosc_editing_flow_id, ['ivr_file' => $flosc_selected_file]);
            add_settings_error('flosc_settings', 'file_changed', 'Flow IVR file changed to: ' . $flosc_selected_file . '. Click "Merge" to import it into the FLOSC DB.', 'success');
        } else {
            // Per-flow storage
            if ($flosc_flow_key) { $flosc_fs = get_option($flosc_flow_key, []); $flosc_fs['active_ivr_file'] = $flosc_selected_file; update_option($flosc_flow_key, $flosc_fs); }
            add_settings_error('flosc_settings', 'file_changed', 'Active IVR Messages MD file changed to: ' . $flosc_selected_file . '. Click "Merge" to import it into the FLOSC DB.', 'success');
        }
    } else {
        add_settings_error('flosc_settings', 'file_not_found', 'File not found: ' . $flosc_selected_file, 'error');
    }
}

// Handle full text save for active IVR file
if (isset($flosc_post['flosc_save_full_ivr']) && isset($flosc_post['ivr_full_text'])) {
    check_admin_referer('flosc_save_full_ivr');

    $flosc_full_text = $flosc_post['ivr_full_text'];
    // Write edited IVR text using the uploads-only API.
    if ('' === $flosc_ivr_file_write_path) {
        $flosc_save_ok = false;
    } else {
        $flosc_save_ok = function_exists('flosc_write_data_file')
            ? flosc_write_data_file($flosc_ivr_file_write_path, $flosc_full_text)
            : false;
    }

    if ($flosc_save_ok === false) {
        add_settings_error('flosc_settings', 'full_text_save_failed', 'Could not save IVR file text. Check file permissions or uploads availability.', 'error');
    } else {
        add_settings_error('flosc_settings', 'full_text_saved', 'Saved full IVR file text. Use "Merge IVR File → DB" to refresh runtime DB from file.', 'success');
        clearstatcache(true, $flosc_ivr_file_write_path);
    }
}

// Handle IVR file download
if (isset($flosc_get['flosc_download_ivr']) && isset($flosc_get['_wpnonce'])) {
    $flosc_download_file = sanitize_file_name($flosc_get['flosc_download_ivr']);
    if (wp_verify_nonce(sanitize_text_field($flosc_get['_wpnonce']), 'flosc_download_ivr_' . $flosc_download_file)) {
        $flosc_download_path = flosc_resolve_ivr_file_path($flosc_download_file);
        if (file_exists($flosc_download_path) && is_readable($flosc_download_path)) {
            if (!function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            WP_Filesystem();
            $flosc_download_content = is_object($wp_filesystem) ? $wp_filesystem->get_contents($flosc_download_path) : '';
            if ($flosc_download_content === false) {
                $flosc_download_content = '';
            }
            nocache_headers();
            header('Content-Type: text/markdown; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . basename($flosc_download_file) . '"');
            header('Content-Length: ' . strlen($flosc_download_content));
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw markdown file download stream
            echo $flosc_download_content;
            exit;
        }
    }
    add_settings_error('flosc_settings', 'download_failed', 'Could not download IVR file.', 'error');
}

// Handle IVR file duplication
if (isset($flosc_post['flosc_duplicate_ivr_file']) && isset($flosc_post['duplicate_ivr_file'])) {
    check_admin_referer('flosc_duplicate_ivr_file');

    $flosc_source_file = sanitize_file_name($flosc_post['duplicate_ivr_file']);
    $flosc_source_path = flosc_resolve_ivr_file_path($flosc_source_file);

    if (!file_exists($flosc_source_path)) {
        add_settings_error('flosc_settings', 'duplicate_invalid', 'Source IVR file not found.', 'error');
    } else {
        $flosc_extension = pathinfo($flosc_source_file, PATHINFO_EXTENSION);
        $flosc_base_name = pathinfo($flosc_source_file, PATHINFO_FILENAME);
        $flosc_duplicate_file = $flosc_base_name . '-copy.' . $flosc_extension;
        $flosc_duplicate_path = $flosc_ivr_dir . $flosc_duplicate_file;
        $flosc_counter = 2;

        while (file_exists($flosc_duplicate_path)) {
            $flosc_duplicate_file = $flosc_base_name . '-copy-' . $flosc_counter . '.' . $flosc_extension;
            $flosc_duplicate_path = $flosc_ivr_dir . $flosc_duplicate_file;
            $flosc_counter++;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- duplicate operation constrained to managed IVR files
        if (!copy($flosc_source_path, $flosc_duplicate_path)) {
            add_settings_error('flosc_settings', 'duplicate_failed', 'Could not duplicate IVR file. Check file permissions.', 'error');
        } else {
            add_settings_error('flosc_settings', 'duplicate_success', 'Duplicated IVR file: ' . $flosc_duplicate_file, 'success');
        }
    }
}

// Handle file deletion from IVR Management
if (isset($flosc_post['flosc_delete_ivr_file']) && isset($flosc_post['delete_ivr_file'])) {
    check_admin_referer('flosc_delete_ivr_file');

    $flosc_delete_file = sanitize_file_name($flosc_post['delete_ivr_file']);
    $flosc_delete_path = $flosc_ivr_dir . $flosc_delete_file;

    if (!file_exists($flosc_delete_path)) {
        add_settings_error('flosc_settings', 'delete_invalid', 'File not found or not a managed IVR file.', 'error');
    } elseif (wp_delete_file($flosc_delete_path) === false) {
        add_settings_error('flosc_settings', 'delete_failed', 'Failed to delete IVR file. Check file permissions.', 'error');
    } else {
        add_settings_error('flosc_settings', 'delete_success', 'Deleted IVR file: ' . $flosc_delete_file, 'success');
    }
}

// Handle clear DB action
if (isset($flosc_post['flosc_clear_ivr_db'])) {
    check_admin_referer('flosc_clear_ivr_db');
    
    // Backup first
    flosc_export_ivr_backup($flosc_flow_key);
    
    // Clear (per-flow)
    if ($flosc_flow_key) {
        $flosc_fs = get_option($flosc_flow_key, []);
        $flosc_fs['ivr_messages'] = [];
        $flosc_fs['ivr_phases'] = [
            'freeline' => [],
            'login' => [],
            'offer' => [],
            'sale' => [],
            'content' => [],
        ];
        $flosc_fs['ivr_styles'] = [];
        unset($flosc_fs['ivr_last_import']);
        update_option($flosc_flow_key, $flosc_fs);
    }
    
    add_settings_error('flosc_settings', 'db_cleared', 'FLOSC DB cleared. Backup created automatically.', 'success');
}

// Handle merge-and-sync (union sync: keep entries from both sides, then restore parity)
if (isset($flosc_post['flosc_force_resync'])) {
    check_admin_referer('flosc_force_resync');
    
    $flosc_result = flosc_import_ivr_to_database(false, $flosc_ivr_file_path, $flosc_flow_key, 'merge');
    
    if ($flosc_result['success']) {
        // Merge is considered complete only when DB and file are brought back to parity.
        $flosc_export_ok = flosc_auto_export_ivr_to_file($flosc_flow_key, $flosc_ivr_file_write_path);
        // Refresh in-memory settings so diagnostics see the update
        if ($flosc_flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flosc_flow_key, []);
            $flosc_flow_settings = $GLOBALS['flosc_current_settings'];
        }
        if ($flosc_export_ok) {
            add_settings_error('flosc_settings', 'load_done', 'Merged Active IVR Messages MD file and synced FLOSC DB ↔ IVR file: ' . $flosc_result['message'] . '. No discrepancies remain.', 'success');
        } else {
            add_settings_error('flosc_settings', 'load_partial', 'Merged Active IVR Messages MD file → FLOSC DB, but file sync failed. Use Save DB → IVR File to finish parity.', 'error');
        }
    } else {
        add_settings_error('flosc_settings', 'load_failed', 'Import failed: ' . $flosc_result['message'], 'error');
    }
}

// Handle explicit offers alignment (active IVR offer messages -> flow offers registry)
// Keep offers registry aligned with IVR messages as part of normal DB<->IVR sync behavior.
if (!empty($flosc_flow_key) && function_exists('flosc_sync_flow_offers_with_ivr_messages')) {
    $flosc_messages_for_sync = $flosc_flow_settings['ivr_messages'] ?? [];
    flosc_sync_flow_offers_with_ivr_messages($flosc_flow_key, $flosc_messages_for_sync);
    $GLOBALS['flosc_current_settings'] = get_option($flosc_flow_key, []);
    $flosc_flow_settings = $GLOBALS['flosc_current_settings'];
}

// Handle import confirmation (same as Load)
if (isset($flosc_post['flosc_confirm_import'])) {
    check_admin_referer('flosc_confirm_import');
    
    $flosc_import_mode = (isset($flosc_post['flosc_import_mode']) && $flosc_post['flosc_import_mode'] === 'replace') ? 'replace' : 'merge';
    $flosc_result = flosc_import_ivr_to_database(false, $flosc_ivr_file_path, $flosc_flow_key, $flosc_import_mode);
    
    if ($flosc_result['success']) {
        $flosc_did_merge = ($flosc_import_mode !== 'replace');
        $flosc_export_ok = true;
        if ($flosc_did_merge) {
            // Merge must end with file parity to pass status cards.
            $flosc_export_ok = flosc_auto_export_ivr_to_file($flosc_flow_key, $flosc_ivr_file_write_path);
        }

        // Refresh in-memory settings
        if ($flosc_flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flosc_flow_key, []);
            $flosc_flow_settings = $GLOBALS['flosc_current_settings'];
        }
        if ($flosc_import_mode === 'replace') {
            add_settings_error('flosc_settings', 'ivr_imported', 'Replaced FLOSC DB to match Active IVR Messages MD file: ' . esc_html($flosc_result['message']), 'success');
        } elseif ($flosc_export_ok) {
            add_settings_error('flosc_settings', 'ivr_imported', 'Merged Active IVR Messages MD file and synced FLOSC DB ↔ IVR file: ' . esc_html($flosc_result['message']) . '. No discrepancies remain.', 'success');
        } else {
            add_settings_error('flosc_settings', 'ivr_import_partial', 'Merged Active IVR Messages MD file → FLOSC DB, but file sync failed. Use Save DB → IVR File to finish parity.', 'error');
        }
    } else {
        add_settings_error('flosc_settings', 'ivr_import_failed', 'Import failed: ' . esc_html($flosc_result['message']), 'error');
    }
}

// Generate comparison preview
$flosc_import_preview = null;
if (isset($flosc_post['flosc_preview_import'])) {
    check_admin_referer('flosc_preview_import');
    if (!file_exists($flosc_ivr_file_path)) {
        add_settings_error('flosc_settings', 'preview_file_missing', 'Compare unavailable: active IVR file is missing. Next step: Save DB → IVR File, then run Compare again.', 'warning');
    } else {
        $flosc_result = flosc_import_ivr_to_database(true, $flosc_ivr_file_path, $flosc_flow_key, 'merge'); // Preview only
        if ($flosc_result['success'] && isset($flosc_result['preview'])) {
            $flosc_import_preview = $flosc_result['stats'];
        } else {
            add_settings_error('flosc_settings', 'preview_file_unreadable', 'Compare unavailable: active IVR file could not be parsed. Next step: Save DB → IVR File, then Refresh Diagnostics.', 'warning');
        }
    }
}

// Handle export
if (isset($flosc_post['flosc_export_ivr'])) {
    check_admin_referer('flosc_export_ivr');

    $flosc_messages = $flosc_flow_settings['ivr_messages'] ?? [];
    if (!empty($flosc_flow_key) && function_exists('flosc_sync_flow_offers_with_ivr_messages')) {
        flosc_sync_flow_offers_with_ivr_messages($flosc_flow_key, $flosc_messages);
    }

    $flosc_result = flosc_auto_export_ivr_to_file($flosc_flow_key, $flosc_ivr_file_write_path);
    if ($flosc_result && file_exists($flosc_ivr_file_write_path)) {
        if ($flosc_flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flosc_flow_key, []);
            $flosc_flow_settings = $GLOBALS['flosc_current_settings'];
        }
        add_settings_error('flosc_settings', 'ivr_exported', 'Resynced: FLOSC DB saved → Active IVR Messages MD file', 'success');
    } elseif ($flosc_result) {
        add_settings_error('flosc_settings', 'ivr_export_failed_missing_file', 'Save DB → IVR reported success but file was not found at expected path: ' . esc_html($flosc_ivr_file_write_path), 'error');
    } else {
        add_settings_error('flosc_settings', 'ivr_export_failed', 'Save DB → IVR failed. Check file permissions and path.', 'error');
    }
}

// Handle message save/delete
// Save message: always writes to both DB (live runtime) and IVR file (portable config)
if (isset($flosc_post['save_ivr_message'])) {
    check_admin_referer('flosc_save_ivr_message');
    
    // DB = live runtime, IVR file = portable config. Always save to both.
    
    $flosc_messages = $flosc_flow_settings['ivr_messages'] ?? [];
    $flosc_phases = $flosc_flow_settings['ivr_phases'] ?? [];
    
    $flosc_msg_id = sanitize_text_field($flosc_post['message_id'] ?? '');
    $flosc_phase = sanitize_text_field($flosc_post['message_phase'] ?? '');
    
    // v9.2.8: Use sanitize_textarea_field to preserve content without over-escaping
    $flosc_raw_content = $flosc_post['message_content'] ?? '';
    $flosc_clean_content = sanitize_textarea_field($flosc_raw_content);
    
    $flosc_message_data = [
        'name' => sanitize_text_field($flosc_post['message_name'] ?? ''),
        'type' => sanitize_text_field($flosc_post['message_type'] ?? ''),
        'phase' => $flosc_phase,
        'content' => $flosc_clean_content,
        'conditions' => sanitize_text_field($flosc_post['message_conditions'] ?? ''),
        'style' => sanitize_text_field($flosc_post['message_style'] ?? 'default'),
        'icon' => sanitize_text_field($flosc_post['message_icon'] ?? ''),
        'user_input' => sanitize_text_field($flosc_post['message_user_input'] ?? ''),
        'keywords' => sanitize_text_field($flosc_post['message_keywords'] ?? ''),
        'action' => sanitize_text_field($flosc_post['message_action'] ?? ''),
    ];
    
    // v1.6.2: Include offer-specific fields when type is 'offer'
    if ($flosc_message_data['type'] === 'offer') {
        $flosc_offer_fields = [
            'offer_id'       => sanitize_text_field($flosc_post['message_offer_id'] ?? ''),
            'price'          => sanitize_text_field($flosc_post['message_price'] ?? ''),
            'discount_price'  => sanitize_text_field($flosc_post['message_discount_price'] ?? ''),
            'timer'          => intval($flosc_post['message_timer'] ?? 0),
            'display_format'  => sanitize_text_field($flosc_post['message_display_format'] ?? 'card'),
            'html_file'      => sanitize_file_name($flosc_post['message_html_file'] ?? ''),
            'woo_product'    => sanitize_text_field($flosc_post['message_woo_product'] ?? ''),
            'post_id'        => intval($flosc_post['message_post_id'] ?? 0),
        ];
        // Only store non-empty values
        foreach ($flosc_offer_fields as $flosc_k => $flosc_v) {
            if (!empty($v)) $flosc_message_data[$k] = $v;
        }
    }

    // v8.0.0: Concierge fields when type is 'concierge' — keyword-triggered message
    // with an optional password gate. Retry messages are one per line (the retry list).
    if ($flosc_message_data['type'] === 'concierge') {
        $flosc_message_data['individual_message_password'] = sanitize_text_field($flosc_post['message_individual_password'] ?? '');
        $flosc_message_data['password_prompt']  = sanitize_text_field($flosc_post['message_password_prompt'] ?? '');
        $flosc_message_data['password_success'] = sanitize_text_field($flosc_post['message_password_success'] ?? '');
        $flosc_message_data['password_max_tries'] = max(1, intval($flosc_post['message_password_max_tries'] ?? 3));
        $flosc_retry_list = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($flosc_post['message_password_retry'] ?? '')) as $flosc_retry_line) {
            $flosc_retry_line = sanitize_text_field($flosc_retry_line);
            if ($flosc_retry_line !== '') {
                $flosc_retry_list[] = $flosc_retry_line;
            }
        }
        $flosc_message_data['password_retry_messages'] = $flosc_retry_list;
    }

    // Save message to FLOSC DB (per-flow). Merge over the existing message so fields
    // the editor does not expose (e.g. MessagePanel) survive an edit instead of being
    // dropped. The form's fields still win — present-in-form values override the old
    // ones — so a field can be edited or cleared; only untouched fields are preserved.
    $flosc_existing_message  = ( isset($flosc_messages[$flosc_msg_id]) && is_array($flosc_messages[$flosc_msg_id]) ) ? $flosc_messages[$flosc_msg_id] : array();
    $flosc_messages[$flosc_msg_id] = array_merge($flosc_existing_message, $flosc_message_data);
    if ($flosc_flow_key) {
        $flosc_fs = get_option($flosc_flow_key, []);
        $flosc_fs['ivr_messages'] = $flosc_messages;
    
    // Update phase mapping
    if (!isset($flosc_phases[$flosc_phase])) {
        $flosc_phases[$flosc_phase] = [];
    }
    if (!in_array($flosc_msg_id, $flosc_phases[$flosc_phase])) {
        $flosc_phases[$flosc_phase][] = $flosc_msg_id;
    }
        $flosc_fs['ivr_phases'] = $flosc_phases;
        update_option($flosc_flow_key, $flosc_fs);
        $GLOBALS['flosc_current_settings'] = $flosc_fs;
        $flosc_flow_settings = $flosc_fs;
    }
    
    // Always save to both: DB is the live runtime, IVR file is the portable config
    flosc_auto_export_ivr_to_file($flosc_flow_key, $flosc_ivr_file_write_path);
    add_settings_error('flosc_settings', 'message_saved', 'Saved to FLOSC DB (live) and IVR file (portable config).', 'success');
}

if (isset($flosc_get['delete_message']) && isset($flosc_get['phase'])) {
    check_admin_referer('flosc_delete_message_' . $flosc_get['delete_message']);
    
    $flosc_msg_id = sanitize_text_field($flosc_get['delete_message']);
    $flosc_phase = sanitize_text_field($flosc_get['phase']);
    
    $flosc_messages = $flosc_flow_settings['ivr_messages'] ?? [];
    $flosc_phases = $flosc_flow_settings['ivr_phases'] ?? [];
    
    unset($flosc_messages[$flosc_msg_id]);
    if ($flosc_flow_key) {
        $flosc_fs = get_option($flosc_flow_key, []);
        $flosc_fs['ivr_messages'] = $flosc_messages;
    
    if (isset($flosc_phases[$flosc_phase])) {
        $flosc_phases[$flosc_phase] = array_diff($flosc_phases[$flosc_phase], [$flosc_msg_id]);
    }
        $flosc_fs['ivr_phases'] = $flosc_phases;
        update_option($flosc_flow_key, $flosc_fs);
        $GLOBALS['flosc_current_settings'] = $flosc_fs;
        $flosc_flow_settings = $flosc_fs;
    }
    
    // v9.2.10: Delete always resyncs to file (destructive operation)
    flosc_auto_export_ivr_to_file($flosc_flow_key, $flosc_ivr_file_write_path);
    
    add_settings_error('flosc_settings', 'message_deleted', 'Message deleted from FLOSC DB and removed from Active IVR Messages MD file', 'success');
}

// Run diagnostics after mutations so cards reflect the current request actions.
$flosc_ivr_diagnostics = flosc_run_ivr_diagnostics();

$flosc_messages = $flosc_flow_settings['ivr_messages'] ?? [];
$flosc_phases = $flosc_flow_settings['ivr_phases'] ?? [];
$flosc_active_phase = $flosc_get['ivr_phase'] ?? 'freeline';
$flosc_editing_message = $flosc_get['edit_message'] ?? null;

// v1.2.6: Get flow context if available
$flosc_editing_flow_id = $GLOBALS['flosc_editing_flow'] ?? null;
$flosc_editing_flow_data = $GLOBALS['flosc_editing_flow_data'] ?? null;

// v1.2.5: Get list of available IVR files (matches *_ivr.md and *ivr*.md patterns)
$flosc_ivr_files_dir = $flosc_ivr_dir;
$flosc_available_ivr_files = [];
if (is_dir($flosc_ivr_files_dir)) {
    // Match both patterns: *_ivr.md (flosc_default_ivr.md) and ivr*.md (ivr.md)
    $flosc_files = array_merge(
        glob($flosc_ivr_files_dir . '*_ivr.md'),
        glob($flosc_ivr_files_dir . 'ivr*.md')
    );
    $flosc_files = array_unique($flosc_files); // Remove duplicates
    sort($flosc_files); // Alphabetical order
    foreach ($flosc_files as $flosc_file) {
        $flosc_filename = basename($flosc_file);
        // Skip backup files
        if (strpos($flosc_filename, 'backup') === false) {
            $flosc_available_ivr_files[] = $flosc_filename;
        }
    }
}

// In single-flow view, the selected flow/file must remain the active IVR target.
// Only fall back to stored active_ivr_file when there is no selected flow context.
if ($flosc_requested_ivr_file !== '') {
    $flosc_active_ivr_file = $flosc_requested_ivr_file;
} elseif ($flosc_editing_flow_data && !empty($flosc_editing_flow_data['ivr_file'])) {
    $flosc_active_ivr_file = $flosc_editing_flow_data['ivr_file'];
} elseif (!empty($GLOBALS['flosc_current_ivr'])) {
    $flosc_active_ivr_file = $GLOBALS['flosc_current_ivr'];
} else {
    $flosc_active_ivr_file = $flosc_flow_settings['active_ivr_file'] ?? 'flosc_default_ivr.md';
}

$GLOBALS['flosc_current_ivr'] = $flosc_active_ivr_file;
$flosc_ivr_file_write_path = $flosc_ivr_dir . $flosc_active_ivr_file;
$flosc_ivr_file_path = flosc_resolve_ivr_file_path($flosc_active_ivr_file);

$flosc_ivr_management_base_url = esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_active_ivr_file) . '&view=' . urlencode($flosc_ivr_management_view)));
$flosc_ivr_management_phase_url = $flosc_ivr_management_base_url . '&ivr_phase=' . urlencode($flosc_active_phase);
$flosc_ivr_management_all_phase_url = esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_active_ivr_file) . '&view=all&ivr_phase=' . urlencode($flosc_active_phase)));
$flosc_ivr_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_active_ivr_file,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], esc_url(admin_url('admin.php'))) . '#tab-ivr-messages-all-flows-fields';

$flosc_missing_ivr_default_message = 'Unfortunately, there is a technical issue with the chat engine, which we hope will be resolved quickly. Our system has notified the site admins, and we invite you to return at a later time.';
$flosc_missing_ivr_message_value = trim((string)($flosc_flow_settings['missing_ivr_message'] ?? ''));
if ($flosc_missing_ivr_message_value === '') {
    $flosc_missing_ivr_message_value = $flosc_missing_ivr_default_message;
}

// v1.2.9: Output tab header after request handlers so download responses can send headers cleanly.
flosc_tab_header('💬', 'IVR Management');

?>

<div class="flosc-docs-link-wrap">
    <a href="<?php echo esc_url($flosc_ivr_docs_url); ?>" class="flosc-docs-link">Docs</a>
</div>

</form>

<!-- IVR System Status Panel -->
<div class="flosc-diagnostics-panel flosc-ivr-panel">
    <h3 class="flosc-ivr-panel__title">
        🔧 IVR Management
        <span class="flosc-ivr-panel__version">(v<?php echo esc_html( FLOSC_VERSION ); ?>)</span>
        <code class="flosc-ivr-panel__file-chip">
            <?php echo esc_html($flosc_active_ivr_file); ?>
        </code>
    </h3>
    
    <p class="description flosc-ivr-panel__intro">
        <?php if ($flosc_ivr_management_view === 'single'): ?>
            Single Flow view: edit message entries for the currently selected flow.
        <?php else: ?>
            All Flows view: manage IVR files and full-text file content across flows.
        <?php endif; ?>
    </p>

    <div class="flosc-ivr-actions-row">
        <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_active_ivr_file) . '&view=single')); ?>" class="button <?php echo $flosc_ivr_management_view === 'single' ? 'button-primary' : ''; ?>">
            Single Flow: Message Editing
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_active_ivr_file) . '&view=all')); ?>" class="button <?php echo $flosc_ivr_management_view === 'all' ? 'button-primary' : ''; ?>">
            All Flows: File Management
        </a>
    </div>

    <div class="flosc-ivr-missing-message-config">
        <h4>Missing IVR File Visitor Message</h4>
        <p class="description">Shown to visitors when this flow's IVR file cannot be resolved at runtime.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_active_ivr_file) . '&view=' . urlencode($flosc_ivr_management_view))); ?>">
            <?php wp_nonce_field('flosc_save_settings'); ?>
            <input type="hidden" name="flosc_save" value="1">
            <textarea name="flow_missing_ivr_message" rows="3" class="large-text"><?php echo esc_textarea($flosc_missing_ivr_message_value); ?></textarea>
            <p>
                <button type="submit" class="button button-secondary">Save Missing-IVR Message</button>
            </p>
        </form>
    </div>
    
    <!-- Status Indicators -->
    <div class="flosc-ivr-status-grid">
        <?php
        $flosc_status_colors = [
            'green' => ['bg' => '#d4edda', 'border' => '#28a745', 'icon' => '✅'],
            'yellow' => ['bg' => '#fff3cd', 'border' => '#ffc107', 'icon' => '⚠️'],
            'red' => ['bg' => '#f8d7da', 'border' => '#dc3545', 'icon' => '❌'],
        ];
        
        $flosc_check_labels = [
            'db_connection' => 'FLOSC DB Connection',
            'ivr_file' => 'Active IVR Messages MD file',
            'db_messages' => 'FLOSC DB Messages',
            'sync_status' => 'Active IVR Messages MD file ↔ FLOSC DB',
            'offer_sync' => 'IVR Offers ↔ FLOSC DB Offers',
            'api_endpoint' => 'REST API',
        ];
        
        foreach ($flosc_ivr_diagnostics as $flosc_check_id => $flosc_check): 
            $flosc_colors = $flosc_status_colors[$flosc_check['status']];
        ?>
        <div class="flosc-ivr-status-card flosc-ivr-status-card--<?php echo esc_attr( $flosc_check['status'] ); ?>">
            <div class="flosc-ivr-status-card__header">
                <strong><?php echo esc_html( $flosc_check_labels[$flosc_check_id] ); ?></strong>
                <span class="flosc-ivr-status-card__icon"><?php echo esc_html( $flosc_colors['icon'] ); ?></span>
            </div>
            <div class="flosc-ivr-status-card__message">
                <?php echo esc_html($flosc_check['message']); ?>
            </div>
            <?php if (!empty($flosc_check['details'])): ?>
            <div class="flosc-ivr-status-card__details">
                <?php foreach ($flosc_check['details'] as $flosc_detail): ?>
                    <?php if (!empty($flosc_detail)): ?>
                    <div class="flosc-ivr-status-card__detail"><?php echo esc_html($flosc_detail); ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- v3.0.9: Action Buttons — framed as two distinct workflows -->
    <div class="flosc-ivr-section-divider">

        <!-- Step 0: Compare (shared first step) -->
        <div class="flosc-ivr-compare-banner">
            <div class="flosc-ivr-compare-banner__body">
                <strong class="flosc-ivr-compare-banner__title">Not sure what's different?</strong>
                <p class="flosc-ivr-compare-banner__text">Compare the file and the DB before acting — shows new, changed, and unchanged messages.</p>
            </div>
            <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>" class="flosc-ivr-compare-banner__form">
                <?php wp_nonce_field('flosc_preview_import'); ?>
                <button type="submit" name="flosc_preview_import" class="button button-secondary">
                    🔍 Compare File ↔ DB
                </button>
            </form>
        </div>

        <!-- Two workflow cards side by side -->
        <div class="flosc-ivr-workflow-grid">

            <!-- Workflow A: Edited in admin → push DB to file -->
            <div class="flosc-ivr-workflow-card flosc-ivr-workflow-card--admin">
                <p class="flosc-ivr-workflow-card__eyebrow">Workflow A</p>
                <p class="flosc-ivr-workflow-card__title">I edited messages in this admin tab</p>
                <p class="flosc-ivr-workflow-card__text">
                    Your edits are already live on the frontend (DB is updated on Save).
                    Push the DB → file so the <code>.md</code> file stays in sync with your changes.
                </p>
                <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>">
                    <?php wp_nonce_field('flosc_export_ivr'); ?>
                    <button type="submit" name="flosc_export_ivr" class="button button-primary flosc-ivr-button-full">
                        🔄 Save DB → IVR File
                    </button>
                </form>
            </div>

            <!-- Workflow B: Edited the .md file → pull file into DB -->
            <div class="flosc-ivr-workflow-card flosc-ivr-workflow-card--file">
                <p class="flosc-ivr-workflow-card__eyebrow">Workflow B</p>
                <p class="flosc-ivr-workflow-card__title">I edited the <code>.md</code> file directly</p>
                <p class="flosc-ivr-workflow-card__text">
                    Merge now performs union sync: keep entries from both sides, then sync both sides to the same result.
                    If one side has more entries, those entries are preserved and copied to the other side so parity is restored.
                </p>
                <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>">
                    <?php wp_nonce_field('flosc_force_resync'); ?>
                    <button type="submit" name="flosc_force_resync" class="button button-primary flosc-ivr-button-full flosc-ivr-button-green">
                        📥 Merge And Sync File ↔ DB
                    </button>
                </form>
            </div>

        </div>

        <!-- Utility row: Test API (small) -->
        <div class="flosc-ivr-utility-row">
            <button type="button" id="flosc-test-api" class="button button-secondary" data-flosc-action="test-api-endpoint">
                🔌 Test API Endpoint
            </button>
            <span class="flosc-ivr-utility-note">Check that the REST API is returning messages from the DB to the frontend chat.</span>
        </div>
            
        </div>
        
        <!-- Secondary Actions -->
        <div class="flosc-ivr-secondary-row">
            <a href="<?php echo esc_url($flosc_ivr_management_phase_url); ?>" class="button">
                🔃 Refresh Diagnostics
            </a>
            <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>" class="flosc-ivr-inline-form" data-confirm-message="⚠️ WARNING: This will clear ALL IVR messages from the FLOSC DB. A backup will be created first, but you will need to reload from a file to restore messages. Continue?">
                <?php wp_nonce_field('flosc_clear_ivr_db'); ?>
                <button type="submit" name="flosc_clear_ivr_db" class="button flosc-ivr-danger-btn">
                    🗑️ Clear FLOSC DB
                </button>
            </form>
            <span class="flosc-ivr-last-sync">
                Last sync: <?php echo esc_html($flosc_flow_settings['ivr_last_import'] ?? 'Never'); ?>
            </span>
        </div>
    </div>
    
    <!-- API Test Result Area -->
    <div id="flosc-api-test-result" class="flosc-ivr-api-result">
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

<h2><?php echo $flosc_ivr_management_view === 'all' ? 'IVR Management - All Flows File Management' : 'IVR Management - Single Flow Message Editing'; ?></h2>

<!-- File Management + Full Text Editor -->
<?php if ($flosc_ivr_management_view === 'all'): ?>
<div class="flosc-info-box flosc-ivr-info-box">
    <div class="flosc-ivr-info-box__title-row">
        <h3 class="flosc-ivr-info-box__title">All Flows File Management</h3>
        <a href="<?php echo esc_url($flosc_ivr_docs_url); ?>" class="flosc-ivr-info-box__docs-link">Docs</a>
    </div>
    <p class="flosc-ivr-info-box__lead">Manage files across flows: refresh, upload, duplicate, import, delete, and edit full file text.</p>

    <div class="flosc-ivr-file-actions">
        <a href="<?php echo esc_url($flosc_ivr_management_all_phase_url); ?>" class="button">🔃 Refresh File List</a>
        <form method="post" action="<?php echo esc_url($flosc_ivr_management_all_phase_url); ?>" class="flosc-ivr-inline-form">
            <?php wp_nonce_field('flosc_create_ivr_file'); ?>
            <input type="text" name="new_ivr_file" class="regular-text" placeholder="new_flow_ivr.md" required pattern="[a-z0-9_-]+_ivr\.md" title="Use lowercase letters, numbers, underscores or hyphens, ending in _ivr.md">
            <button type="submit" name="flosc_create_ivr_file" class="button button-secondary">➕ Create New IVR File</button>
        </form>
        <form method="post" action="<?php echo esc_url($flosc_ivr_management_all_phase_url); ?>" enctype="multipart/form-data" class="flosc-ivr-upload-form">
            <?php wp_nonce_field('flosc_upload_ivr_file'); ?>
            <input type="file" name="ivr_file_upload" accept=".md,text/markdown" required>
            <button type="submit" name="flosc_upload_ivr_file" class="button button-secondary">📤 Upload IVR .md</button>
        </form>
    </div>

    <table class="widefat striped flosc-ivr-file-table">
        <thead>
            <tr>
                <th>File</th>
                <th>Diagnostics</th>
                <th class="flosc-ivr-col-status">Status</th>
                <th class="flosc-ivr-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($flosc_available_ivr_files)): ?>
            <tr>
                <td colspan="4">No managed IVR files found. Create one with the form above or upload an existing .md file.</td>
            </tr>
        <?php endif; ?>
        <?php
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
        $flosc_table_parser = FLOSC_IVR_Parser::flosc_instance();
        ?>
        <?php foreach ($flosc_available_ivr_files as $flosc_ivr_filename):
            $flosc_is_active_row = ($flosc_ivr_filename === $flosc_active_ivr_file);
            $flosc_edit_url = esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_ivr_filename) . '&view=single'));
            $flosc_download_url = wp_nonce_url(
                esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_active_ivr_file) . '&view=all&flosc_download_ivr=' . urlencode($flosc_ivr_filename))),
                'flosc_download_ivr_' . $flosc_ivr_filename
            );

            $flosc_file_path_for_row = flosc_resolve_ivr_file_path($flosc_ivr_filename);
            $flosc_row_file_exists = ($flosc_file_path_for_row !== '' && file_exists($flosc_file_path_for_row));
            $flosc_row_parse_state = 'error';
            $flosc_row_parse_note = 'File missing or unreadable';
            $flosc_row_total_messages = 0;
            $flosc_row_phase_counts = [
                'freeline' => 0,
                'login' => 0,
                'offer' => 0,
                'sale' => 0,
                'content' => 0,
            ];
            $flosc_row_modified = 'n/a';
            $flosc_row_size = 'n/a';

            if ($flosc_row_file_exists) {
                $flosc_row_modified = gmdate('Y-m-d H:i:s', filemtime($flosc_file_path_for_row));
                $flosc_row_size = number_format((int)filesize($flosc_file_path_for_row)) . ' bytes';
                $flosc_row_markdown = file_get_contents($flosc_file_path_for_row);
                if ($flosc_row_markdown !== false) {
                    $flosc_row_config = $flosc_table_parser->flosc_parse($flosc_row_markdown);
                    $flosc_row_total_messages = count($flosc_row_config['messages'] ?? []);
                    foreach (array_keys($flosc_row_phase_counts) as $flosc_phase_key) {
                        $flosc_row_phase_counts[$flosc_phase_key] = count($flosc_row_config['phases'][$flosc_phase_key] ?? []);
                    }
                    if ($flosc_row_total_messages > 0) {
                        $flosc_row_parse_state = 'valid';
                        $flosc_row_parse_note = 'Parsed successfully';
                    } else {
                        $flosc_row_parse_state = 'warning';
                        $flosc_row_parse_note = 'Parsed but no messages found';
                    }
                }
            }

            $flosc_row_file_stem = sanitize_key(pathinfo($flosc_ivr_filename, PATHINFO_FILENAME));
            $flosc_row_option_key = function_exists('flosc_resolve_flow_option_key_for_ivr')
                ? flosc_resolve_flow_option_key_for_ivr($flosc_ivr_filename)
                : ('flosc_flow_' . $flosc_row_file_stem);
            $flosc_row_flow_settings = get_option($flosc_row_option_key, []);

            $flosc_row_flow_id = str_replace('flosc_flow_', '', (string)$flosc_row_option_key);
            if ($flosc_row_flow_id === '') {
                $flosc_row_flow_id = $flosc_row_file_stem;
            }

            $flosc_row_flow_label = trim((string)($flosc_row_flow_settings['identity']['name'] ?? ''));
            if ($flosc_row_flow_label === '') {
                $flosc_row_flow_label = trim((string)($flosc_row_flow_settings['name'] ?? ''));
            }
            if ($flosc_row_flow_label === '') {
                $flosc_row_flow_label = ucwords(str_replace(['_', '-', '.md', 'ivr'], [' ', ' ', '', ''], $flosc_ivr_filename));
            }

            $flosc_row_assistant = trim((string)($flosc_row_flow_settings['identity']['name'] ?? ''));
            if ($flosc_row_assistant === '') {
                $flosc_row_assistant = $flosc_row_flow_label;
            }

            $flosc_row_ai_provider = trim((string)($flosc_row_flow_settings['ai_provider'] ?? get_option('flosc_ai_provider', 'ivr')));
            if ($flosc_row_ai_provider === '') {
                $flosc_row_ai_provider = 'ivr';
            }

            $flosc_row_selected_file = sanitize_file_name((string)($flosc_row_flow_settings['active_ivr_file'] ?? $flosc_row_flow_settings['ivr_file'] ?? ''));
            if ($flosc_row_selected_file === '') {
                $flosc_row_file_setting_status = 'No flow file selected in settings';
            } else {
                $flosc_row_file_setting_status = ($flosc_row_selected_file === $flosc_ivr_filename)
                    ? 'Matches flow file setting'
                    : ('Flow settings currently select ' . $flosc_row_selected_file);
            }
        ?>
            <tr>
                <td>
                    <div class="flosc-ivr-row-file-head"><code><?php echo esc_html($flosc_ivr_filename); ?></code></div>
                </td>
                <td>
                    <div class="flosc-ivr-row-diagnostics">
                        <span><strong>Flow Label:</strong> <?php echo esc_html($flosc_row_flow_label); ?></span>
                        <span><strong>Flow Key (internal):</strong> <?php echo esc_html($flosc_row_flow_id); ?></span>
                        <span><strong>AI Provider:</strong> <?php echo esc_html($flosc_row_ai_provider); ?></span>
                        <span><strong>Messages:</strong> <?php echo esc_html((string)$flosc_row_total_messages); ?></span>
                        <span><strong>Phases:</strong> F <?php echo esc_html((string)$flosc_row_phase_counts['freeline']); ?> | L <?php echo esc_html((string)$flosc_row_phase_counts['login']); ?> | O <?php echo esc_html((string)$flosc_row_phase_counts['offer']); ?> | S <?php echo esc_html((string)$flosc_row_phase_counts['sale']); ?> | C <?php echo esc_html((string)$flosc_row_phase_counts['content']); ?></span>
                        <span><strong>Modified:</strong> <?php echo esc_html($flosc_row_modified); ?></span>
                        <span><strong>Size:</strong> <?php echo esc_html($flosc_row_size); ?></span>
                        <span><strong>Parse Status:</strong> <?php echo esc_html(strtoupper($flosc_row_parse_state)); ?> (<?php echo esc_html($flosc_row_parse_note); ?>)</span>
                        <span><strong>Selected IVR File in Settings:</strong> <?php echo esc_html($flosc_row_file_setting_status); ?></span>
                    </div>
                </td>
                <td><?php echo $flosc_is_active_row ? 'Active' : 'Managed'; ?></td>
                <td>
                    <div class="flosc-ivr-file-action-group">
                        <a href="<?php echo esc_url($flosc_edit_url); ?>" class="button button-small">Edit</a>
                        <a href="<?php echo esc_url($flosc_download_url); ?>" class="button button-small">Download</a>
                        <form method="post" action="<?php echo esc_url($flosc_ivr_management_all_phase_url); ?>" class="flosc-ivr-inline-form">
                            <?php wp_nonce_field('flosc_duplicate_ivr_file'); ?>
                            <input type="hidden" name="duplicate_ivr_file" value="<?php echo esc_attr($flosc_ivr_filename); ?>">
                            <button type="submit" name="flosc_duplicate_ivr_file" class="button button-small">Duplicate</button>
                        </form>
                        <form method="post" action="<?php echo esc_url($flosc_ivr_management_all_phase_url); ?>" class="flosc-ivr-inline-form">
                            <?php wp_nonce_field('flosc_import_selected_ivr_file'); ?>
                            <input type="hidden" name="import_ivr_file" value="<?php echo esc_attr($flosc_ivr_filename); ?>">
                            <button type="submit" name="flosc_import_selected_ivr_file" class="button button-small flosc-ivr-file-action-wide">Import IVR File → DB</button>
                        </form>
                        <form method="post" action="<?php echo esc_url($flosc_ivr_management_all_phase_url); ?>" class="flosc-ivr-inline-form flosc-ivr-inline-form--warn" data-confirm-message="Delete IVR file <?php echo esc_attr($flosc_ivr_filename); ?>? This cannot be undone from this panel.">
                            <?php wp_nonce_field('flosc_delete_ivr_file'); ?>
                            <input type="hidden" name="delete_ivr_file" value="<?php echo esc_attr($flosc_ivr_filename); ?>">
                            <button type="submit" name="flosc_delete_ivr_file" class="button button-small">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    $flosc_active_ivr_full_text = '';
    if (file_exists($flosc_ivr_file_path)) {
        $flosc_active_ivr_full_text = file_get_contents($flosc_ivr_file_path);
        if ($flosc_active_ivr_full_text === false) {
            $flosc_active_ivr_full_text = '';
        }
    }
    ?>
    <form method="post" action="<?php echo esc_url($flosc_ivr_management_all_phase_url); ?>">
        <?php wp_nonce_field('flosc_save_full_ivr'); ?>
        <label for="ivr_full_text"><strong>Full Text Editor: <?php echo esc_html($flosc_active_ivr_file); ?></strong></label>
        <textarea id="ivr_full_text" name="ivr_full_text" rows="20" class="large-text code flosc-ivr-full-editor"><?php echo esc_textarea($flosc_active_ivr_full_text); ?></textarea>
        <div class="flosc-ivr-full-editor-actions">
            <button type="submit" name="flosc_save_full_ivr" class="button button-primary">💾 Save Full IVR File</button>
            <span class="flosc-ivr-full-editor-note">After save, use <strong>Merge IVR File → DB</strong> to refresh runtime messages.</span>
        </div>
    </form>
</div>
<?php else: ?>
<div class="flosc-info-box flosc-ivr-info-box flosc-ivr-info-box--muted">
    <strong>IVR File Management</strong>
    <p class="flosc-ivr-info-box__lead">Click <strong>View All Flows and Access File Management</strong> above to refresh/delete IVR files and use the full-text editor.</p>
</div>
<?php endif; ?>

<div class="flosc-info-box flosc-ivr-message-overview">
    <strong>💾 FLOSC IVR Messages</strong>
    <p>All messages across every phase, in one scrollable page. Click any message header to expand its editor. Save individually.</p>
    <p class="flosc-ivr-message-overview__workflow"><strong>Workflow:</strong> Expand → Edit → Save → Changes go live and sync to IVR file</p>
</div>

<?php if ($flosc_import_preview !== null):
    // v3.0.9: Pre-compute field_diffs early so we can split "updated" into changed vs unchanged
    $flosc_field_diffs   = $flosc_import_preview['field_diffs'] ?? [];
    $flosc_changed_ids   = array_keys($flosc_field_diffs);                                          // IDs with actual field differences
    $flosc_unchanged_ids = array_values(array_diff($flosc_import_preview['updated'] ?? [], $flosc_changed_ids)); // IDs in both, content identical
    $flosc_after_merge_count = (int) ($flosc_import_preview['current_count'] ?? 0) + count($flosc_import_preview['added'] ?? []);
    $flosc_after_replace_count = (int) ($flosc_import_preview['incoming_count'] ?? 0);
    $flosc_replace_removed_count = count($flosc_import_preview['deleted'] ?? []);

    // Build full-entry views for clear compare and direction decisions.
    $flosc_db_messages_for_compare = $flosc_flow_settings['ivr_messages'] ?? [];
    $flosc_file_messages_for_compare = [];
    if (file_exists($flosc_ivr_file_path)) {
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
        $flosc_preview_parser = FLOSC_IVR_Parser::flosc_instance();
        $flosc_preview_markdown = file_get_contents($flosc_ivr_file_path);
        $flosc_preview_config = $flosc_preview_parser->flosc_parse($flosc_preview_markdown ?: '');
        $flosc_file_messages_for_compare = $flosc_preview_config['messages'] ?? [];
    }
?>
    <!-- Comparison Results -->
    <div class="flosc-import-preview flosc-ivr-compare-preview">
        <h3 class="flosc-ivr-compare-preview__title">📋 Comparison: Active IVR Messages MD file ↔ FLOSC DB</h3>

        <p class="flosc-ivr-compare-preview__intro">
            This shows the differences between your <strong>Active IVR Messages MD file</strong> and the <strong>FLOSC DB</strong>.
            Merge performs union sync and ends in parity. Replace makes the DB match the file exactly (destructive when DB has extra entries).
        </p>

        <!-- Summary row counts -->
        <div class="flosc-ivr-compare-preview__panel">
            <h4 class="flosc-ivr-compare-preview__panel-title">Comparison Results:</h4>
            <ul class="flosc-ivr-compare-preview__list">

                <li>📊 <strong>FLOSC DB:</strong> <?php echo esc_html( (string) $flosc_import_preview['current_count'] ); ?> messages</li>
                <li>📄 <strong>Active IVR Messages MD file:</strong> <?php echo esc_html( (string) $flosc_import_preview['incoming_count'] ); ?> messages</li>

                <!-- New in file -->
                <li>✅ <strong>New in file:</strong> <?php echo count($flosc_import_preview['added']); ?>
                    <?php if (!empty($flosc_import_preview['added'])): ?>
                    <details class="flosc-ivr-inline-details">
                        <summary class="flosc-ivr-inline-details__summary flosc-ivr-inline-details__summary--blue">▼ show all</summary>
                        <div class="flosc-ivr-id-cloud flosc-ivr-id-cloud--green">
                            <?php foreach ($flosc_import_preview['added'] as $id): ?>
                                <code class="flosc-ivr-id-chip"><?php echo esc_html($id); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </li>

                <?php if (!empty($flosc_import_preview['added'])): ?>
                <li class="flosc-ivr-list-item-top-gap">
                    <details>
                        <summary class="flosc-ivr-inline-details__summary flosc-ivr-inline-details__summary--blue">Show full entries: New in file</summary>
                        <div class="flosc-ivr-entry-grid">
                            <?php foreach ($flosc_import_preview['added'] as $id): ?>
                                <div class="flosc-ivr-entry-card flosc-ivr-entry-card--file">
                                    <div class="flosc-ivr-entry-card__title"><?php echo esc_html($id); ?></div>
                                    <pre class="flosc-ivr-entry-card__json"><?php echo esc_html(wp_json_encode($flosc_file_messages_for_compare[$id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </li>
                <?php endif; ?>

                <!-- Changed (actual field differences) -->
                <?php if (!empty($flosc_changed_ids)): ?>
                <li>🔄 <strong>Changed</strong> (content differs): <?php echo count($flosc_changed_ids); ?> — see field details below</li>
                <?php endif; ?>

                <!-- Unchanged (present in both, identical content) -->
                <?php if (!empty($flosc_unchanged_ids)): ?>
                <li>⚡ <strong>Present in both</strong> (unchanged): <?php echo count($flosc_unchanged_ids); ?>
                    <details class="flosc-ivr-inline-details">
                        <summary class="flosc-ivr-inline-details__summary flosc-ivr-inline-details__summary--gray">▼ show all</summary>
                        <div class="flosc-ivr-id-cloud flosc-ivr-id-cloud--gray">
                            <?php foreach ($flosc_unchanged_ids as $id): ?>
                                <code class="flosc-ivr-id-chip"><?php echo esc_html($id); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </li>
                <?php endif; ?>

                <!-- DB-only messages -->
                <?php if ($flosc_import_preview['has_deletions']): ?>
                <li class="flosc-ivr-list-item-db-only">↔ <strong>Only in DB:</strong> <?php echo count($flosc_import_preview['deleted']); ?>
                    <details class="flosc-ivr-inline-details">
                        <summary class="flosc-ivr-inline-details__summary flosc-ivr-inline-details__summary--orange">▼ show all</summary>
                        <div class="flosc-ivr-id-cloud flosc-ivr-id-cloud--orange">
                            <?php foreach ($flosc_import_preview['deleted'] as $id): ?>
                                <code class="flosc-ivr-id-chip"><?php echo esc_html($id); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <div class="flosc-ivr-db-only-note">Merge keeps these messages and writes them into the IVR file so file ↔ DB parity is restored.</div>
                </li>

                <li>
                    <details>
                        <summary class="flosc-ivr-inline-details__summary flosc-ivr-inline-details__summary--orange">Show full entries: Only in DB</summary>
                        <div class="flosc-ivr-entry-grid">
                            <?php foreach ($flosc_import_preview['deleted'] as $id): ?>
                                <div class="flosc-ivr-entry-card flosc-ivr-entry-card--db-only">
                                    <div class="flosc-ivr-entry-card__title"><?php echo esc_html($id); ?></div>
                                    <pre class="flosc-ivr-entry-card__json"><?php echo esc_html(wp_json_encode($flosc_db_messages_for_compare[$id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </li>
                <?php else: ?>
                <li class="flosc-ivr-list-item-good">✓ No DB-only messages</li>
                <?php endif; ?>

                <li class="flosc-ivr-list-item-dashed-top">
                    <strong>After Merge And Sync:</strong> file = DB = <?php echo (int) $flosc_after_merge_count; ?> entries
                </li>
                <li>
                    <strong>After Replace:</strong> file = DB = <?php echo (int) $flosc_after_replace_count; ?> entries
                    <?php if ($flosc_replace_removed_count > 0): ?>
                        <span class="flosc-ivr-text-danger">(removes <?php echo (int) $flosc_replace_removed_count; ?> DB-only entries)</span>
                    <?php endif; ?>
                </li>

            </ul>
        </div>

        <!-- v3.0.9: Field-level diff table — one <details> row per changed message -->
        <?php if (!empty($flosc_field_diffs)): ?>
        <div class="flosc-ivr-field-diff-wrap">
            <h4 class="flosc-ivr-field-diff-wrap__title">🔍 Field-Level Differences (<?php echo count($flosc_field_diffs); ?> message<?php echo count($flosc_field_diffs) !== 1 ? 's' : ''; ?> changed):</h4>
            <?php foreach ($flosc_field_diffs as $flosc_msg_id => $flosc_diffs): ?>
            <details class="flosc-ivr-field-diff-item">
                <summary class="flosc-ivr-field-diff-item__summary">
                    ▶ <?php echo esc_html($flosc_msg_id); ?> — <?php echo count($flosc_diffs); ?> field<?php echo count($flosc_diffs) !== 1 ? 's' : ''; ?> changed
                </summary>
                <table class="flosc-ivr-field-diff-table">
                    <thead>
                        <tr class="flosc-ivr-field-diff-table__head-row">
                            <th class="flosc-ivr-field-diff-table__head flosc-ivr-field-diff-table__head--field">Field</th>
                            <th class="flosc-ivr-field-diff-table__head flosc-ivr-field-diff-table__head--db">DB Value</th>
                            <th class="flosc-ivr-field-diff-table__head flosc-ivr-field-diff-table__head--file">File Value</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($flosc_diffs as $flosc_field => $flosc_vals):
                        $flosc_db_display   = esc_html($vals['db']);
                        $flosc_file_display = esc_html($vals['file']);
                        if ($flosc_db_display === '') $flosc_db_display = '<em class="flosc-ivr-empty-value">(empty)</em>';
                        if ($flosc_file_display === '') $flosc_file_display = '<em class="flosc-ivr-empty-value">(empty)</em>';
                    ?>
                        <tr class="flosc-ivr-field-diff-table__row">
                            <td class="flosc-ivr-field-diff-table__cell flosc-ivr-field-diff-table__cell--field"><?php echo esc_html($field); ?></td>
                            <td class="flosc-ivr-field-diff-table__cell flosc-ivr-field-diff-table__cell--db"><pre class="flosc-ivr-field-diff-table__pre"><?php echo wp_kses_post( $flosc_db_display ); ?></pre></td>
                            <td class="flosc-ivr-field-diff-table__cell flosc-ivr-field-diff-table__cell--file"><pre class="flosc-ivr-field-diff-table__pre"><?php echo wp_kses_post( $flosc_file_display ); ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <details class="flosc-ivr-full-entry-details">
                    <summary class="flosc-ivr-inline-details__summary flosc-ivr-inline-details__summary--gray">Show full DB entry and file entry</summary>
                    <div class="flosc-ivr-full-entry-grid">
                        <div class="flosc-ivr-full-entry-card flosc-ivr-full-entry-card--db">
                            <div class="flosc-ivr-entry-card__title">DB Entry</div>
                            <pre class="flosc-ivr-entry-card__json"><?php echo esc_html(wp_json_encode($flosc_db_messages_for_compare[$flosc_msg_id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                        </div>
                        <div class="flosc-ivr-full-entry-card flosc-ivr-full-entry-card--file">
                            <div class="flosc-ivr-entry-card__title">File Entry</div>
                            <pre class="flosc-ivr-entry-card__json"><?php echo esc_html(wp_json_encode($flosc_file_messages_for_compare[$flosc_msg_id] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                        </div>
                    </div>
                </details>
            </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flosc-ivr-compare-actions">
            <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>" class="flosc-ivr-inline-form">
                <?php wp_nonce_field('flosc_confirm_import'); ?>
                <input type="hidden" name="flosc_import_mode" value="merge">
                <button type="submit" name="flosc_confirm_import" class="button button-primary">
                    ✅ Save File → DB (Merge And Sync)
                </button>
            </form>

            <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>" class="flosc-ivr-inline-form">
                <?php wp_nonce_field('flosc_export_ivr'); ?>
                <button type="submit" name="flosc_export_ivr" class="button button-secondary">
                    🔄 Save DB → IVR File
                </button>
            </form>

            <?php if ($flosc_import_preview['has_deletions']): ?>
            <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>" class="flosc-ivr-inline-form">
                <?php wp_nonce_field('flosc_confirm_import'); ?>
                <input type="hidden" name="flosc_import_mode" value="replace">
                <button type="submit" name="flosc_confirm_import" class="button flosc-ivr-replace-btn"
                        data-confirm-message="Replace is destructive. Replace FLOSC DB with the IVR file and remove DB-only messages?">
                    Replace DB To Match File
                </button>
            </form>
            <?php endif; ?>

            <a href="<?php echo esc_url($flosc_ivr_management_phase_url); ?>" class="button button-secondary">Cancel</a>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- ALL MESSAGES — SINGLE SCROLLABLE PAGE -->
<!-- ============================================ -->
<?php
$flosc_phase_meta = [
    'freeline' => ['icon' => '🌐', 'label' => 'Freeline', 'desc' => 'Visitor (not logged in) — Encourage quiz completion'],
    'login'    => ['icon' => '🔑', 'label' => 'Login',    'desc' => 'Post-quiz + Logged-in — Deliver free lesson, present offer'],
    'offer'    => ['icon' => '🏷️', 'label' => 'Offer',    'desc' => 'Sales pitch — Product offers and promotions'],
    'sale'     => ['icon' => '💳', 'label' => 'Sale',     'desc' => 'Post-purchase — Onboarding and welcome'],
    'content'  => ['icon' => '📚', 'label' => 'Content',  'desc' => 'Ongoing member support and engagement'],
];
$flosc_expand_id = $flosc_get['expand'] ?? $flosc_get['edit_message'] ?? null;
$flosc_total_count = count($flosc_messages);
?>

<!-- Styles in assets/css/flosc-admin.css -->

<p class="flosc-ivr-phase-summary">
    <strong><?php echo esc_html( (string) $flosc_total_count ); ?></strong> messages across <?php echo esc_html( (string) count(array_filter($flosc_phases, fn($ids) => !empty($ids))) ); ?> phases
    <?php
    // Quick jump links
    $flosc_active_phases = [];
    foreach ($flosc_phase_meta as $flosc_pid => $flosc_pm) {
        $flosc_cnt = count($flosc_phases[$flosc_pid] ?? []);
        if ($flosc_cnt > 0) $flosc_active_phases[] = '<a href="#phase-' . esc_attr( $flosc_pid ) . '" class="flosc-ivr-phase-link">' . esc_html( $flosc_pm['icon'] . ' ' . $flosc_pm['label'] . ' (' . $flosc_cnt . ')' ) . '</a>';
    }
    if ($flosc_active_phases): ?>
        — Jump: <?php echo wp_kses_post( implode(' · ', $flosc_active_phases) ); ?>
    <?php endif; ?>
</p>

<?php foreach ($flosc_phase_meta as $flosc_phase_id => $flosc_pm): 
    $flosc_phase_msg_ids = $flosc_phases[$flosc_phase_id] ?? [];
?>
<div class="flosc-phase-section" id="phase-<?php echo esc_attr( $flosc_phase_id ); ?>">
    <!-- Phase Header -->
    <div class="flosc-phase-header">
        <span><?php echo esc_html( $flosc_pm['icon'] ); ?></span>
        <span><?php echo esc_html($flosc_pm['label']); ?></span>
        <span class="flosc-phase-count"><?php echo count($flosc_phase_msg_ids); ?></span>
        <span class="flosc-phase-desc"><?php echo esc_html($flosc_pm['desc']); ?></span>
    </div>
    
    <?php if (empty($flosc_phase_msg_ids)): ?>
        <div class="flosc-msg-card flosc-ivr-empty-phase-card">
            No messages in this phase yet.
        </div>
    <?php endif; ?>
    
    <?php foreach ($flosc_phase_msg_ids as $flosc_msg_id):
        if (!isset($flosc_messages[$flosc_msg_id])) continue;
        $flosc_msg = $flosc_messages[$flosc_msg_id];
        $flosc_is_open = ($flosc_expand_id === $flosc_msg_id);
        $flosc_type_class = 'type-' . ($flosc_msg['type'] ?? 'auto');
        $flosc_safe_id = esc_attr($flosc_msg_id);
    ?>
    <div class="flosc-msg-card <?php echo esc_attr( $flosc_is_open ? 'is-open' : '' ); ?>" id="card-<?php echo esc_attr( $flosc_safe_id ); ?>">
        <div class="flosc-msg-card-header" data-flosc-action="toggle-msg-card" data-msg-id="<?php echo esc_attr($flosc_msg_id); ?>">
            <span class="flosc-msg-toggle">▶</span>
            <span class="flosc-msg-name"><?php echo esc_html($flosc_msg['name'] ?? $flosc_msg_id); ?></span>
            <span class="flosc-msg-id"><?php echo esc_html($flosc_msg_id); ?></span>
            <span class="flosc-msg-type-badge <?php echo esc_attr( $flosc_type_class ); ?>"><?php echo esc_html($flosc_msg['type'] ?? 'auto'); ?></span>
            <?php if (!empty($flosc_msg['conditions'])): ?>
                <span class="flosc-ivr-conditional-badge" title="<?php echo esc_attr($flosc_msg['conditions']); ?>">⚡ conditional</span>
            <?php endif; ?>
            <span class="flosc-msg-preview"><?php echo esc_html(wp_trim_words($flosc_msg['content'] ?? '', 12)); ?></span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr=' . urlencode($flosc_active_ivr_file) . '&view=' . urlencode($flosc_ivr_management_view) . '&ivr_phase=' . urlencode($flosc_active_phase) . '&delete_message=' . urlencode($flosc_msg_id) . '&phase=' . urlencode($flosc_phase_id) . '&_wpnonce=' . wp_create_nonce('flosc_delete_message_' . $flosc_msg_id))); ?>" 
               class="flosc-msg-delete" data-stop-propagation="1" data-confirm-message="Delete message: <?php echo esc_attr($flosc_msg['name'] ?? $flosc_msg_id); ?>?">✕ Delete</a>
        </div>
        
        <div class="flosc-msg-editor">
            <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>">
                <?php wp_nonce_field('flosc_save_ivr_message'); ?>
                <input type="hidden" name="message_id" value="<?php echo esc_attr( $flosc_safe_id ); ?>">
                
                <table class="form-table">
                    <tr>
                        <th>Phase</th>
                        <td>
                            <select name="message_phase">
                                <?php foreach (array_keys($flosc_phase_meta) as $flosc_p): ?>
                                    <option value="<?php echo esc_attr( $flosc_p ); ?>" <?php selected($flosc_msg['phase'] ?? $flosc_phase_id, $flosc_p); ?>><?php echo esc_html( ucfirst($flosc_p) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Message ID</th>
                        <td><input type="text" name="message_name" value="<?php echo esc_attr($flosc_msg['name'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <select name="message_type" data-flosc-action="toggle-offer-fields" data-msg-id="<?php echo esc_attr($flosc_msg_id); ?>">
                                <option value="auto" <?php selected($flosc_msg['type'] ?? '', 'auto'); ?>>Auto (bot sends automatically)</option>
                                <option value="suggested_user_autoprompt" <?php selected($flosc_msg['type'] ?? '', 'suggested_user_autoprompt'); ?>>Pill Button (user clicks to send)</option>
                                <option value="offer" <?php selected($flosc_msg['type'] ?? '', 'offer'); ?>>Offer</option>
                                <option value="concierge" <?php selected($flosc_msg['type'] ?? '', 'concierge'); ?>>Concierge (keyword + optional password)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Conditions</th>
                        <td>
                            <input type="text" name="message_conditions" value="<?php echo esc_attr($flosc_msg['conditions'] ?? ''); ?>" class="large-text" placeholder="e.g. is_visitor && first_show_session">
                            <details class="flosc-ivr-help-details">
                                <summary class="flosc-ivr-help-summary">Available conditions reference</summary>
                                <div class="flosc-ivr-help-body">
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
                                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected($flosc_msg['style'] ?? 'default', $s); ?>><?php echo esc_html( ucfirst($s) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Icon</th>
                        <td><input type="text" name="message_icon" value="<?php echo esc_attr($flosc_msg['icon'] ?? ''); ?>" class="small-text" placeholder="💬"></td>
                    </tr>
                    <tr>
                        <th>User Input Prompt Label Text</th>
                        <td><input type="text" name="message_user_input" value="<?php echo esc_attr($flosc_msg['user_input'] ?? ''); ?>" class="regular-text" placeholder="Button text for pill buttons"></td>
                    </tr>
                    <tr>
                        <th>Keywords</th>
                        <td>
                            <input type="text" name="message_keywords" value="<?php echo esc_attr($flosc_msg['keywords'] ?? ''); ?>" class="large-text" placeholder="comma-separated trigger words">
                            <p class="description">Comma-separated words the chatbot matches against. For a Concierge message, this is the keyword that opens it.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Chatbot Response Content</th>
                        <td>
                            <textarea name="message_content" rows="4" class="large-text"><?php echo esc_textarea($flosc_msg['content'] ?? ''); ?></textarea>
                            <p class="description">Variables: {name}, {score}, {product_name}, {price}</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Action After Chatbot Response</th>
                        <td>
                            <input type="text" name="message_action" value="<?php echo esc_attr($flosc_msg['action'] ?? ''); ?>" class="regular-text" placeholder="show_offer:offer_001, start_quiz, navigate:/lessons">
                            <details class="flosc-ivr-help-details">
                                <summary class="flosc-ivr-help-summary">Available actions reference</summary>
                                <div class="flosc-ivr-help-body">
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
                <div class="flosc-offer-fields-inner <?php echo esc_attr( ($flosc_msg['type'] ?? '') === 'offer' ? '' : 'flosc-hidden' ); ?>" id="offer-fields-<?php echo esc_attr( $flosc_safe_id ); ?>">
                    <h4 class="flosc-ivr-subsection-title">🏷️ Offer Fields</h4>
                    <table class="form-table flosc-form-table-reset">
                        <tr><th>Offer ID</th><td><input type="text" name="message_offer_id" value="<?php echo esc_attr($flosc_msg['offer_id'] ?? ''); ?>" class="regular-text" placeholder="full_access"></td></tr>
                        <tr><th>Price</th><td><input type="text" name="message_price" value="<?php echo esc_attr($flosc_msg['price'] ?? ''); ?>" class="small-text" placeholder="49"></td></tr>
                        <tr><th>Discount Price</th><td><input type="text" name="message_discount_price" value="<?php echo esc_attr($flosc_msg['discount_price'] ?? ''); ?>" class="small-text" placeholder="24.50"></td></tr>
                        <tr><th>Timer (sec)</th><td><input type="number" name="message_timer" value="<?php echo esc_attr($flosc_msg['timer'] ?? ''); ?>" class="small-text" placeholder="900"></td></tr>
                        <tr>
                            <th>Display Format</th>
                            <td>
                                <select name="message_display_format">
                                    <?php foreach (['card','pill','compact','banner','featured','text','inline-checkout'] as $flosc_fmt): ?>
                                        <option value="<?php echo esc_attr( $flosc_fmt ); ?>" <?php selected($flosc_msg['display_format'] ?? 'card', $flosc_fmt); ?>><?php echo esc_html( ucfirst($flosc_fmt) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Content Source</th>
                            <td>
                                <input type="text" name="message_html_file" value="<?php echo esc_attr($flosc_msg['html_file'] ?? ''); ?>" class="regular-text flosc-ivr-field-tight" placeholder="offer-page.html"><br>
                                <input type="text" name="message_woo_product" value="<?php echo esc_attr($flosc_msg['woo_product'] ?? ''); ?>" class="small-text flosc-ivr-field-tight" placeholder="WooCommerce Product ID">
                                <input type="number" name="message_post_id" value="<?php echo esc_attr($flosc_msg['post_id'] ?? ''); ?>" class="small-text" placeholder="WP Post ID">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Concierge-specific fields -->
                <div class="flosc-concierge-fields-inner <?php echo esc_attr( ($flosc_msg['type'] ?? '') === 'concierge' ? '' : 'flosc-hidden' ); ?>" id="concierge-fields-<?php echo esc_attr( $flosc_safe_id ); ?>">
                    <h4 class="flosc-ivr-subsection-title">🔐 Concierge Fields</h4>
                    <table class="form-table flosc-form-table-reset">
                        <tr><th>Individual Message Password</th><td><input type="text" name="message_individual_password" value="<?php echo esc_attr($flosc_msg['individual_message_password'] ?? ''); ?>" class="regular-text" placeholder="usually blank; exact, case-sensitive"></td></tr>
                        <tr><th>Password Prompt</th><td><input type="text" name="message_password_prompt" value="<?php echo esc_attr($flosc_msg['password_prompt'] ?? ''); ?>" class="large-text" placeholder="What the bot asks when the keyword is used"></td></tr>
                        <tr><th>Password Success</th><td><input type="text" name="message_password_success" value="<?php echo esc_attr($flosc_msg['password_success'] ?? ''); ?>" class="large-text" placeholder="Affirmation shown just before the content delivers"></td></tr>
                        <tr><th>Max Tries</th><td><input type="number" name="message_password_max_tries" value="<?php echo esc_attr($flosc_msg['password_max_tries'] ?? 3); ?>" class="small-text" min="1"></td></tr>
                        <tr>
                            <th>Retry Messages</th>
                            <td>
                                <textarea name="message_password_retry" rows="3" class="large-text" placeholder="One line per try. {try} and {max} are filled in."><?php echo esc_textarea(implode("\n", (array) ($flosc_msg['password_retry_messages'] ?? array()))); ?></textarea>
                                <p class="description">One retry message per line. {try}/{max} are substituted. The final line shows on the last miss, then the gate resets to normal chat.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="flosc-ivr-editor-actions">
                    <button type="submit" name="save_ivr_message" class="button button-primary">💾 Save</button>
                    <span class="flosc-ivr-msg-id-note">ID: <?php echo esc_html($flosc_msg_id); ?></span>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Add New Message to this phase -->
    <?php $flosc_new_id = 'new_' . $flosc_phase_id . '_' . time(); ?>
    <div class="flosc-msg-card flosc-msg-card--flat" id="card-<?php echo esc_attr($flosc_new_id); ?>">
        <div class="flosc-msg-editor flosc-hidden" id="editor-new-<?php echo esc_attr( $flosc_phase_id ); ?>">
            <form method="post" action="<?php echo esc_url($flosc_ivr_management_phase_url); ?>">
                <?php wp_nonce_field('flosc_save_ivr_message'); ?>
                <input type="hidden" name="message_id" value="<?php echo esc_attr($flosc_new_id); ?>">
                <h4 class="flosc-ivr-new-msg-title">✨ New <?php echo esc_html($flosc_pm['label']); ?> Message</h4>
                <table class="form-table">
                    <tr>
                        <th>Phase</th>
                        <td>
                            <select name="message_phase">
                                <?php foreach (array_keys($flosc_phase_meta) as $flosc_p): ?>
                                    <option value="<?php echo esc_attr( $flosc_p ); ?>" <?php selected($flosc_phase_id, $flosc_p); ?>><?php echo esc_html( ucfirst($flosc_p) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Message ID</th><td><input type="text" name="message_name" value="" class="regular-text" required placeholder="Welcome Message"></td></tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <select name="message_type" data-flosc-action="toggle-offer-fields" data-msg-id="<?php echo esc_attr($flosc_new_id); ?>">
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
                            <details class="flosc-ivr-help-details">
                                <summary class="flosc-ivr-help-summary">Available conditions reference</summary>
                                <div class="flosc-ivr-help-body">
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
                            <details class="flosc-ivr-help-details">
                                <summary class="flosc-ivr-help-summary">Available actions reference</summary>
                                <div class="flosc-ivr-help-body">
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
                
                <div class="flosc-offer-fields-inner flosc-hidden" id="offer-fields-<?php echo esc_attr($flosc_new_id); ?>">
                    <h4 class="flosc-ivr-subsection-title">🏷️ Offer Fields</h4>
                    <table class="form-table flosc-form-table-reset">
                        <tr><th>Offer ID</th><td><input type="text" name="message_offer_id" class="regular-text" placeholder="full_access"></td></tr>
                        <tr><th>Price</th><td><input type="text" name="message_price" class="small-text" placeholder="49"></td></tr>
                        <tr><th>Discount Price</th><td><input type="text" name="message_discount_price" class="small-text" placeholder="24.50"></td></tr>
                        <tr><th>Timer (sec)</th><td><input type="number" name="message_timer" class="small-text" placeholder="900"></td></tr>
                        <tr><th>Display Format</th><td>
                            <select name="message_display_format">
                                <?php foreach (['card','pill','compact','banner','featured','text','inline-checkout'] as $flosc_fmt): ?>
                                    <option value="<?php echo esc_attr( $flosc_fmt ); ?>"><?php echo esc_html( ucfirst($flosc_fmt) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Content Source</th><td>
                            <input type="text" name="message_html_file" class="regular-text flosc-ivr-field-tight" placeholder="offer-page.html"><br>
                            <input type="text" name="message_woo_product" class="small-text flosc-ivr-field-tight" placeholder="WooCommerce Product ID">
                            <input type="number" name="message_post_id" class="small-text" placeholder="WP Post ID">
                        </td></tr>
                    </table>
                </div>
                
                <!-- Concierge-specific fields -->
                <div class="flosc-concierge-fields-inner flosc-hidden" id="concierge-fields-<?php echo esc_attr($flosc_new_id); ?>">
                    <h4 class="flosc-ivr-subsection-title">🔐 Concierge Fields</h4>
                    <table class="form-table flosc-form-table-reset">
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

                <div class="flosc-ivr-editor-actions flosc-ivr-editor-actions--new">
                    <button type="submit" name="save_ivr_message" class="button button-primary">💾 Save</button>
                    <button type="button" class="button" data-flosc-action="toggle-new-editor" data-phase-id="<?php echo esc_attr( $flosc_phase_id ); ?>" data-open="0">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <button type="button" class="flosc-add-msg-btn" id="add-btn-<?php echo esc_attr( $flosc_phase_id ); ?>" data-flosc-action="toggle-new-editor" data-phase-id="<?php echo esc_attr( $flosc_phase_id ); ?>" data-open="1">
        + Add <?php echo esc_html($flosc_pm['label']); ?> Message
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
    if (panel) panel.classList.toggle('flosc-hidden', selectEl.value !== 'offer');
    const cpanel = document.getElementById('concierge-fields-' + msgId);
    if (cpanel) cpanel.classList.toggle('flosc-hidden', selectEl.value !== 'concierge');
}
function floscToggleNewEditor(phaseId, shouldOpen) {
    const editor = document.getElementById('editor-new-' + phaseId);
    const button = document.getElementById('add-btn-' + phaseId);
    if (editor) {
        editor.classList.toggle('flosc-hidden', !shouldOpen);
    }
    if (button) {
        button.classList.toggle('flosc-hidden', !!shouldOpen);
    }
}
// Auto-scroll to expanded message on page load
document.addEventListener('DOMContentLoaded', function() {
    const open = document.querySelector('.flosc-msg-card.is-open');
    if (open) open.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
