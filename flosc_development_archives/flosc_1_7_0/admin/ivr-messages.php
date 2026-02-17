<?php
/**
 * FLOSC IVR Messages Tab v1.2.9
 * 
 * Simple: Uses the IVR file selected in the Flow dropdown.
 * No separate file selector needed - the flow IS the IVR file.
 */

if (!defined('ABSPATH')) exit;

// v1.2.8: Get IVR file from settings context (set by settings.php)
$active_ivr_file = $GLOBALS['flosc_current_ivr'] ?? 'flosc_default_ivr.md';
$ivr_file_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $active_ivr_file;

// Per-flow settings
$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flow_key = $GLOBALS['flosc_settings_key'] ?? '';

// v1.2.9: Output tab header
flosc_tab_header('💬', 'IVR Messages');

/**
 * Run IVR diagnostics - checks DB, file, sync status
 */
function flosc_run_ivr_diagnostics() {
    // v1.2.8: Use current IVR file from context
    $active_ivr = $GLOBALS['flosc_current_ivr'] ?? 'flosc_default_ivr.md';
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $active_ivr;
    
    $diagnostics = [
        'db_connection' => ['status' => 'red', 'message' => 'Failed', 'details' => []],
        'ivr_file' => ['status' => 'red', 'message' => 'Not found', 'details' => []],
        'db_messages' => ['status' => 'red', 'message' => 'Empty', 'details' => []],
        'sync_status' => ['status' => 'red', 'message' => 'Not synced', 'details' => []],
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
        $file_modified = date('Y-m-d H:i:s', filemtime($ivr_file));
        
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
            // Check if content matches
            $content_mismatches = [];
            foreach ($file_ids as $id) {
                $file_content = $config['messages'][$id]['content'] ?? '';
                $db_content = $db_messages[$id]['content'] ?? '';
                if ($file_content !== $db_content) {
                    $content_mismatches[] = $id;
                }
            }
            
            if (empty($content_mismatches)) {
                $diagnostics['sync_status'] = [
                    'status' => 'green',
                    'message' => 'Match ✓',
                    'details' => ['Active IVR Messages MD file and FLOSC DB match (' . count($file_ids) . ' messages)']
                ];
            } else {
                $diagnostics['sync_status'] = [
                    'status' => 'yellow',
                    'message' => count($content_mismatches) . ' message(s) differ',
                    'details' => [
                        'FLOSC DB differs from file: ' . implode(', ', array_slice($content_mismatches, 0, 3)),
                        count($content_mismatches) > 3 ? '... and ' . (count($content_mismatches) - 3) . ' more' : '',
                        'Press "Resync" to save your FLOSC DB edits to the file.'
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
        $diagnostics['sync_status']['details'][] = 'Click "Load" to populate from file';
    }
    
    // 5. API Endpoint - actually test it server-side
    $api_url = rest_url('flosc/v1/ivr-messages?phase=freeline');
    
    // v9.3.1: Michel Timestamp format for last checked
    $michel_timestamp = date('Y') . '-' . date('m') . 'm-' . date('d') . 'd-T' . date('H:i:s');
    
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
if (isset($_POST['flosc_upload_ivr_file']) && isset($_FILES['ivr_file_upload'])) {
    check_admin_referer('flosc_upload_ivr_file');
    
    $uploaded_file = $_FILES['ivr_file_upload'];
    
    if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
        $filename = sanitize_file_name($uploaded_file['name']);
        
        // Ensure it's a .md file and starts with ivr
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'md') {
            add_settings_error('flosc_settings', 'upload_failed', 'Only .md files are allowed.', 'error');
        } elseif (strpos($filename, 'ivr') !== 0) {
            // Add ivr- prefix if missing
            $filename = 'ivr-' . $filename;
            $target_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;
            
            if (move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
                if ($flow_key) { $fs = get_option($flow_key, []); $fs['active_ivr_file'] = $filename; update_option($flow_key, $fs); }
                add_settings_error('flosc_settings', 'upload_success', 'Uploaded and set as active: ' . $filename . ' (ivr- prefix added)', 'success');
            } else {
                add_settings_error('flosc_settings', 'upload_failed', 'Failed to save uploaded file.', 'error');
            }
        } else {
            $target_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;
            
            if (move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
                if ($flow_key) { $fs = get_option($flow_key, []); $fs['active_ivr_file'] = $filename; update_option($flow_key, $fs); }
                add_settings_error('flosc_settings', 'upload_success', 'Uploaded and set as active: ' . $filename, 'success');
            } else {
                add_settings_error('flosc_settings', 'upload_failed', 'Failed to save uploaded file.', 'error');
            }
        }
    } else {
        add_settings_error('flosc_settings', 'upload_failed', 'File upload error: ' . $uploaded_file['error'], 'error');
    }
}

// Handle changing active IVR file
if (isset($_POST['flosc_change_active_file']) && isset($_POST['ivr_file_select'])) {
    check_admin_referer('flosc_change_active_file');
    
    $selected_file = sanitize_file_name($_POST['ivr_file_select']);
    $file_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $selected_file;
    
    if (file_exists($file_path)) {
        // v1.2.6: Save to flow if in flow context, otherwise save globally
        $editing_flow_id = $GLOBALS['flosc_editing_flow'] ?? null;
        
        if ($editing_flow_id) {
            // Update flow's ivr_file setting
            flosc_flows()->update_flow($editing_flow_id, ['ivr_file' => $selected_file]);
            add_settings_error('flosc_settings', 'file_changed', 'Flow IVR file changed to: ' . $selected_file . '. Click "Load" to import it into the FLOSC DB.', 'success');
        } else {
            // Per-flow storage
            if ($flow_key) { $fs = get_option($flow_key, []); $fs['active_ivr_file'] = $selected_file; update_option($flow_key, $fs); }
            add_settings_error('flosc_settings', 'file_changed', 'Active IVR Messages MD file changed to: ' . $selected_file . '. Click "Load" to import it into the FLOSC DB.', 'success');
        }
    } else {
        add_settings_error('flosc_settings', 'file_not_found', 'File not found: ' . $selected_file, 'error');
    }
}

// Handle clear DB action
if (isset($_POST['flosc_clear_ivr_db'])) {
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

// Handle force resync (this is actually Load File → FLOSC DB)
if (isset($_POST['flosc_force_resync'])) {
    check_admin_referer('flosc_force_resync');
    
    $result = flosc_import_ivr_to_database(false, $ivr_file_path, $flow_key);
    
    if ($result['success']) {
        // Refresh in-memory settings so diagnostics see the update
        if ($flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flow_key, []);
            $flow_settings = $GLOBALS['flosc_current_settings'];
        }
        add_settings_error('flosc_settings', 'load_done', 'Loaded Active IVR Messages MD file → FLOSC DB: ' . $result['message'], 'success');
    } else {
        add_settings_error('flosc_settings', 'load_failed', 'Load failed: ' . $result['message'], 'error');
    }
}

// Run diagnostics
$ivr_diagnostics = flosc_run_ivr_diagnostics();

// Handle import confirmation (same as Load)
if (isset($_POST['flosc_confirm_import'])) {
    check_admin_referer('flosc_confirm_import');
    
    $result = flosc_import_ivr_to_database(false, $ivr_file_path, $flow_key); // Execute import (not preview)
    
    if ($result['success']) {
        // Refresh in-memory settings
        if ($flow_key) {
            $GLOBALS['flosc_current_settings'] = get_option($flow_key, []);
            $flow_settings = $GLOBALS['flosc_current_settings'];
        }
        add_settings_error('flosc_settings', 'ivr_imported', 'Loaded Active IVR Messages MD file → FLOSC DB: ' . esc_html($result['message']), 'success');
    } else {
        add_settings_error('flosc_settings', 'ivr_import_failed', 'Load failed: ' . esc_html($result['message']), 'error');
    }
}

// Generate comparison preview
$import_preview = null;
if (isset($_POST['flosc_preview_import'])) {
    check_admin_referer('flosc_preview_import');
    
    $result = flosc_import_ivr_to_database(true, $ivr_file_path, $flow_key); // Preview only
    
    if ($result['success'] && isset($result['preview'])) {
        $import_preview = $result['stats'];
    } else {
        add_settings_error('flosc_settings', 'preview_failed', 'Compare failed. Check that the Active IVR Messages MD file exists and is valid.', 'error');
    }
}

// Handle export
if (isset($_POST['flosc_export_ivr'])) {
    check_admin_referer('flosc_export_ivr');
    
    $messages = $flow_settings['ivr_messages'] ?? [];
    $phases = $flow_settings['ivr_phases'] ?? [];
    $styles = $flow_settings['ivr_styles'] ?? [];
    
    // Generate markdown
    $markdown = "# FLOSC IVR Configuration\n\n";
    
    // Add styles
    foreach ($styles as $style_name => $style_css) {
        $markdown .= "## MessageStyle: $style_name\n";
        $markdown .= $style_css . "\n\n";
    }
    
    $markdown .= "## Available Variables\n";
    $markdown .= "{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}\n\n";
    
    $markdown .= "---\n\n";
    
    // Add messages by phase
    foreach ($phases as $phase_name => $message_ids) {
        $markdown .= "# " . ucfirst($phase_name) . " Messages\n\n";
        
        foreach ($message_ids as $msg_id) {
            if (!isset($messages[$msg_id])) continue;
            $msg = $messages[$msg_id];
            
            $markdown .= "## " . ($msg['name'] ?? $msg_id) . "\n";
            $markdown .= "MessageName: " . $msg_id . "\n";
            $markdown .= "MessageType: " . ($msg['type'] ?? 'auto') . "\n";
            
            if (!empty($msg['style'])) {
                $markdown .= "MessageStyle: " . $msg['style'] . "\n";
            }
            if (!empty($msg['icon'])) {
                $markdown .= "Icon: " . $msg['icon'] . "\n";
            }
            if (!empty($msg['user_input'])) {
                $markdown .= "UserInput: " . $msg['user_input'] . "\n";
            }
            
            $markdown .= "MessageContent: " . $msg['content'] . "\n";
            
            if (!empty($msg['conditions'])) {
                $markdown .= "MessageConditions: " . $msg['conditions'] . "\n";
            }
            if (!empty($msg['action'])) {
                $markdown .= "MessageAction: " . $msg['action'] . "\n";
            }
            
            $markdown .= "\n";
        }
    }
    
    // Save to file
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    file_put_contents($ivr_file, $markdown);
    
    add_settings_error('flosc_settings', 'ivr_exported', 'Resynced: FLOSC DB saved → Active IVR Messages MD file', 'success');
}

// Handle message save/delete
// v9.2.10: Two save options - DB only or DB + Resync to file
if (isset($_POST['save_ivr_message']) || isset($_POST['save_ivr_message_resync'])) {
    check_admin_referer('flosc_save_ivr_message');
    
    $do_resync = isset($_POST['save_ivr_message_resync']);
    
    $messages = $flow_settings['ivr_messages'] ?? [];
    $phases = $flow_settings['ivr_phases'] ?? [];
    
    $msg_id = sanitize_text_field($_POST['message_id']);
    $phase = sanitize_text_field($_POST['message_phase']);
    
    // v9.2.8: Use sanitize_textarea_field to preserve content without over-escaping
    $raw_content = $_POST['message_content'] ?? '';
    $clean_content = sanitize_textarea_field($raw_content);
    
    $message_data = [
        'name' => sanitize_text_field($_POST['message_name']),
        'type' => sanitize_text_field($_POST['message_type']),
        'phase' => $phase,
        'content' => $clean_content,
        'conditions' => sanitize_text_field($_POST['message_conditions'] ?? ''),
        'style' => sanitize_text_field($_POST['message_style'] ?? 'default'),
        'icon' => sanitize_text_field($_POST['message_icon'] ?? ''),
        'user_input' => sanitize_text_field($_POST['message_user_input'] ?? ''),
        'action' => sanitize_text_field($_POST['message_action'] ?? ''),
    ];
    
    // v1.6.2: Include offer-specific fields when type is 'offer'
    if ($message_data['type'] === 'offer') {
        $offer_fields = [
            'offer_id'       => sanitize_text_field($_POST['message_offer_id'] ?? ''),
            'price'          => sanitize_text_field($_POST['message_price'] ?? ''),
            'discount_price'  => sanitize_text_field($_POST['message_discount_price'] ?? ''),
            'timer'          => intval($_POST['message_timer'] ?? 0),
            'display_format'  => sanitize_text_field($_POST['message_display_format'] ?? 'card'),
            'html_file'      => sanitize_file_name($_POST['message_html_file'] ?? ''),
            'woo_product'    => sanitize_text_field($_POST['message_woo_product'] ?? ''),
            'post_id'        => intval($_POST['message_post_id'] ?? 0),
        ];
        // Only store non-empty values
        foreach ($offer_fields as $k => $v) {
            if (!empty($v)) $message_data[$k] = $v;
        }
    }
    
    // Save message to FLOSC DB (per-flow)
    $messages[$msg_id] = $message_data;
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
    
    // v9.2.10: Only resync to file if user clicked "Save and Resync"
    if ($do_resync) {
        flosc_auto_export_ivr_to_file();
        add_settings_error('flosc_settings', 'message_saved', 'Message saved to FLOSC DB and resynced to Active IVR Messages MD file', 'success');
    } else {
        add_settings_error('flosc_settings', 'message_saved', 'Message saved to FLOSC DB. Changes appear on frontend. Click "Resync" when ready to save to file.', 'success');
    }
}

if (isset($_GET['delete_message']) && isset($_GET['phase'])) {
    check_admin_referer('flosc_delete_message_' . $_GET['delete_message']);
    
    $msg_id = sanitize_text_field($_GET['delete_message']);
    $phase = sanitize_text_field($_GET['phase']);
    
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
    flosc_auto_export_ivr_to_file();
    
    add_settings_error('flosc_settings', 'message_deleted', 'Message deleted from FLOSC DB and removed from Active IVR Messages MD file', 'success');
}

$messages = $flow_settings['ivr_messages'] ?? [];
$phases = $flow_settings['ivr_phases'] ?? [];
$active_phase = $_GET['ivr_phase'] ?? 'freeline';
$editing_message = $_GET['edit_message'] ?? null;

// v1.2.6: Get flow context if available
$editing_flow_id = $GLOBALS['flosc_editing_flow'] ?? null;
$editing_flow_data = $GLOBALS['flosc_editing_flow_data'] ?? null;

// v1.2.5: Get list of available IVR files (matches *_ivr.md and *ivr*.md patterns)
$ivr_files_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
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

// v1.2.6: Use flow's IVR file if in flow context, otherwise fall back to global
if ($editing_flow_data && !empty($editing_flow_data['ivr_file'])) {
    $active_ivr_file = $editing_flow_data['ivr_file'];
} else {
    $active_ivr_file = $flow_settings['active_ivr_file'] ?? 'flosc_default_ivr.md';
}

?>

</form>

<!-- IVR System Status Panel -->
<div class="flosc-diagnostics-panel" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
        🔧 IVR Messages
        <span style="font-size: 12px; font-weight: normal; color: #667;">(v<?php echo FLOSC_VERSION; ?>)</span>
        <code style="margin-left: 8px; background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 4px; font-size: 13px;">
            <?php echo esc_html($active_ivr_file); ?>
        </code>
    </h3>
    
    <p class="description" style="margin-bottom: 15px;">
        Editing IVR messages for this flow. Use the Flow dropdown above to switch to a different IVR file.
    </p>
    
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
            'api_endpoint' => 'REST API',
        ];
        
        foreach ($ivr_diagnostics as $check_id => $check): 
            $colors = $status_colors[$check['status']];
        ?>
        <div style="background: <?php echo $colors['bg']; ?>; border-left: 4px solid <?php echo $colors['border']; ?>; padding: 12px; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <strong><?php echo $check_labels[$check_id]; ?></strong>
                <span style="font-size: 18px;"><?php echo $colors['icon']; ?></span>
            </div>
            <div style="font-size: 14px; color: #333; font-weight: 500;">
                <?php echo esc_html($check['message']); ?>
            </div>
            <?php if (!empty($check['details'])): ?>
            <div style="font-size: 11px; color: #667; margin-top: 5px;">
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
    
    <!-- Action Buttons with Clear Descriptions -->
    <div style="border-top: 1px solid #dee2e6; padding-top: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            
            <!-- Compare Button -->
            <div style="background: white; border: 1px solid #ddd; border-radius: 6px; padding: 15px;">
                <form method="post">
                    <?php wp_nonce_field('flosc_preview_import'); ?>
                    <button type="submit" name="flosc_preview_import" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
                        🔍 Compare Active IVR Messages MD file ↔ FLOSC DB
                    </button>
                </form>
                <p style="font-size: 12px; color: #667; margin: 0;">
                    See exactly which messages differ between the Active IVR Messages MD file and the FLOSC DB. Useful before loading or resyncing to understand what will change.
                </p>
            </div>
            
            <!-- Resync Button -->
            <div style="background: white; border: 1px solid #ddd; border-radius: 6px; padding: 15px;">
                <form method="post">
                    <?php wp_nonce_field('flosc_export_ivr'); ?>
                    <button type="submit" name="flosc_export_ivr" class="button button-primary" style="width: 100%; margin-bottom: 10px;">
                        🔄 Resync: Save FLOSC DB → Active IVR Messages MD file
                    </button>
                </form>
                <p style="font-size: 12px; color: #667; margin: 0;">
                    Save all messages from the FLOSC DB (edited in this IVR Messages tab) to the Active IVR Messages MD file. This keeps your file up to date with your edits.
                </p>
            </div>
            
            <!-- Load Button -->
            <div style="background: white; border: 1px solid #ddd; border-radius: 6px; padding: 15px;">
                <form method="post">
                    <?php wp_nonce_field('flosc_force_resync'); ?>
                    <button type="submit" name="flosc_force_resync" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
                        📥 Load Active IVR Messages MD file → FLOSC DB
                    </button>
                </form>
                <p style="font-size: 12px; color: #667; margin: 0;">
                    Load messages from the Active IVR Messages MD file into the FLOSC DB. This replaces your current FLOSC DB messages. Use this for initial setup or to switch to a different IVR configuration. A backup is created automatically.
                </p>
            </div>
            
            <!-- Test API Button -->
            <div style="background: white; border: 1px solid #ddd; border-radius: 6px; padding: 15px;">
                <button type="button" id="flosc-test-api" class="button button-secondary" onclick="floscTestAPI()" style="width: 100%; margin-bottom: 10px;">
                    🔌 Test API Endpoint
                </button>
                <p style="font-size: 12px; color: #667; margin: 0;">
                    Check that the REST API is working and returning messages from the FLOSC DB to the frontend chat.
                </p>
            </div>
            
        </div>
        
        <!-- Secondary Actions -->
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ddd; display: flex; gap: 10px; align-items: center;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&ivr_phase=' . $active_phase)); ?>" class="button">
                🔃 Refresh Diagnostics
            </a>
            <form method="post" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This will clear ALL IVR messages from the FLOSC DB.\n\nA backup will be created first, but you will need to reload from a file to restore messages.\n\nAre you sure you want to clear the FLOSC DB?');">
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

<script>
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
</script>

<h2>IVR Messages — All Phases</h2>

<div class="flosc-info-box" style="margin-bottom: 20px;">
    <strong>💾 FLOSC IVR System (v1.6.2)</strong>
    <p>All messages across every phase, in one scrollable page. Click any message header to expand its editor. Save individually.</p>
    <p style="margin-top: 8px;"><strong>Workflow:</strong> Expand → Edit → Save to DB → Changes appear on frontend → "Resync" to write to file</p>
</div>

<?php if ($import_preview !== null): ?>
    <!-- Comparison Results -->
    <div class="flosc-import-preview" style="background: #fff3cd; padding: 20px; border-left: 5px solid #ffc107; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #856404;">📋 Comparison: Active IVR Messages MD file ↔ FLOSC DB</h3>
        
        <p style="background: white; padding: 10px; border: 1px solid #ddd;">
            This shows the differences between your <strong>Active IVR Messages MD file</strong> and the <strong>FLOSC DB</strong>.
            Use "Load" to replace the FLOSC DB with the file contents.
        </p>
        
        <div style="background: white; padding: 15px; margin: 15px 0; border: 1px solid #ddd;">
            <h4 style="margin-top: 0;">Comparison Results:</h4>
            <ul style="margin: 5px 0 0 20px; line-height: 1.8;">
                <li>📊 <strong>FLOSC DB:</strong> <?php echo $import_preview['current_count']; ?> messages</li>
                <li>📄 <strong>Active IVR Messages MD file:</strong> <?php echo $import_preview['incoming_count']; ?> messages</li>
                <li>✅ <strong>New in file:</strong> <?php echo count($import_preview['added']); ?>
                    <?php if (!empty($import_preview['added'])): ?>
                        — <code><?php echo implode(', ', array_slice($import_preview['added'], 0, 10)); ?></code>
                    <?php endif; ?>
                </li>
                <li>🔄 <strong>Updated:</strong> <?php echo count($import_preview['updated']); ?>
                    <?php if (!empty($import_preview['updated'])): ?>
                        — <code><?php echo implode(', ', array_slice($import_preview['updated'], 0, 10)); ?></code>
                    <?php endif; ?>
                </li>
                <?php if ($import_preview['has_deletions']): ?>
                <li style="color: #d63638;">⚠️ <strong>Will be removed:</strong> <?php echo count($import_preview['deleted']); ?>
                    — <code><?php echo implode(', ', $import_preview['deleted']); ?></code>
                </li>
                <?php else: ?>
                <li style="color: #2e7d32;">✓ No deletions</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <form method="post" style="margin-top: 15px;">
            <?php wp_nonce_field('flosc_confirm_import'); ?>
            <button type="submit" name="flosc_confirm_import" 
                    class="button button-primary" 
                    style="background: <?php echo $import_preview['has_deletions'] ? '#dc3545' : '#0073aa'; ?>; 
                           border-color: <?php echo $import_preview['has_deletions'] ? '#bd2130' : '#0073aa'; ?>;">
                <?php echo $import_preview['has_deletions'] ? '⚠️ Load (will remove ' . count($import_preview['deleted']) . ')' : '✅ Load File → DB'; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ivr-messages'); ?>" class="button button-secondary">Cancel</a>
        </form>
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
$expand_id = $_GET['expand'] ?? $_GET['edit_message'] ?? null;
$total_count = count($messages);
?>

<style>
.flosc-phase-section { margin-bottom: 30px; }
.flosc-phase-header {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; background: #1d2327; color: #fff;
    border-radius: 6px 6px 0 0; font-size: 15px; font-weight: 600;
    position: sticky; top: 32px; z-index: 10;
}
.flosc-phase-header .flosc-phase-count {
    background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 400;
}
.flosc-phase-header .flosc-phase-desc {
    margin-left: auto; font-size: 12px; font-weight: 400; opacity: 0.7;
}
.flosc-msg-card {
    border: 1px solid #ddd; border-top: none; background: #fff;
}
.flosc-msg-card:last-of-type { border-radius: 0 0 6px 6px; }
.flosc-msg-card-header {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; cursor: pointer; user-select: none;
    transition: background 0.15s;
}
.flosc-msg-card-header:hover { background: #f0f6fc; }
.flosc-msg-card-header .flosc-msg-toggle { font-size: 11px; color: #999; transition: transform 0.2s; }
.flosc-msg-card.is-open .flosc-msg-toggle { transform: rotate(90deg); }
.flosc-msg-card-header .flosc-msg-name { font-weight: 600; font-size: 14px; }
.flosc-msg-card-header .flosc-msg-id { font-size: 11px; color: #999; font-family: monospace; }
.flosc-msg-card-header .flosc-msg-type-badge {
    display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500;
    background: #e8f0fe; color: #1a73e8;
}
.flosc-msg-card-header .flosc-msg-type-badge.type-offer { background: #fef3c7; color: #b45309; }
.flosc-msg-card-header .flosc-msg-type-badge.type-suggested_user_autoprompt { background: #dcfce7; color: #166534; }
.flosc-msg-card-header .flosc-msg-preview { flex: 1; font-size: 12px; color: #667; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 400px; }
.flosc-msg-card-header .flosc-msg-delete {
    font-size: 12px; color: #d63638; text-decoration: none; opacity: 0; transition: opacity 0.15s;
}
.flosc-msg-card-header:hover .flosc-msg-delete { opacity: 1; }
.flosc-msg-editor {
    display: none; padding: 16px 20px; border-top: 1px solid #eee; background: #fafbfc;
}
.flosc-msg-card.is-open .flosc-msg-editor { display: block; }
.flosc-msg-editor table.form-table th { width: 140px; padding: 8px 10px 8px 0; font-size: 13px; }
.flosc-msg-editor table.form-table td { padding: 8px 0; }
.flosc-msg-editor .flosc-offer-fields-inner {
    background: #f0f4ff; border: 1px solid #c3d6f5; padding: 12px 16px; margin: 8px 0; border-radius: 6px;
}
.flosc-add-msg-btn {
    display: block; width: 100%; padding: 10px; border: 2px dashed #c3c4c7; border-top: none;
    background: #fafbfc; color: #2271b1; font-size: 13px; font-weight: 500;
    cursor: pointer; text-align: center; border-radius: 0 0 6px 6px;
    transition: background 0.15s, color 0.15s;
}
.flosc-add-msg-btn:hover { background: #f0f6fc; color: #135e96; }
</style>

<p style="margin-bottom: 15px; color: #667;">
    <strong><?php echo $total_count; ?></strong> messages across <?php echo count(array_filter($phases, fn($ids) => !empty($ids))); ?> phases
    <?php
    // Quick jump links
    $active_phases = [];
    foreach ($phase_meta as $pid => $pm) {
        $cnt = count($phases[$pid] ?? []);
        if ($cnt > 0) $active_phases[] = '<a href="#phase-' . $pid . '" style="text-decoration:none;">' . $pm['icon'] . ' ' . $pm['label'] . ' (' . $cnt . ')</a>';
    }
    if ($active_phases): ?>
        — Jump: <?php echo implode(' · ', $active_phases); ?>
    <?php endif; ?>
</p>

<?php foreach ($phase_meta as $phase_id => $pm): 
    $phase_msg_ids = $phases[$phase_id] ?? [];
?>
<div class="flosc-phase-section" id="phase-<?php echo $phase_id; ?>">
    <!-- Phase Header -->
    <div class="flosc-phase-header">
        <span><?php echo $pm['icon']; ?></span>
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
    <div class="flosc-msg-card <?php echo $is_open ? 'is-open' : ''; ?>" id="card-<?php echo $safe_id; ?>">
        <div class="flosc-msg-card-header" onclick="floscToggleMsg('<?php echo esc_js($msg_id); ?>')">
            <span class="flosc-msg-toggle">▶</span>
            <span class="flosc-msg-name"><?php echo esc_html($msg['name'] ?? $msg_id); ?></span>
            <span class="flosc-msg-id"><?php echo esc_html($msg_id); ?></span>
            <span class="flosc-msg-type-badge <?php echo $type_class; ?>"><?php echo esc_html($msg['type'] ?? 'auto'); ?></span>
            <?php if (!empty($msg['conditions'])): ?>
                <span style="font-size: 11px; color: #8b5cf6;" title="<?php echo esc_attr($msg['conditions']); ?>">⚡ conditional</span>
            <?php endif; ?>
            <span class="flosc-msg-preview"><?php echo esc_html(wp_trim_words($msg['content'] ?? '', 12)); ?></span>
            <a href="?page=flosc-settings&tab=ivr-messages&delete_message=<?php echo urlencode($msg_id); ?>&phase=<?php echo $phase_id; ?>&_wpnonce=<?php echo wp_create_nonce('flosc_delete_message_' . $msg_id); ?>" 
               class="flosc-msg-delete" onclick="event.stopPropagation(); return confirm('Delete message: <?php echo esc_js($msg['name'] ?? $msg_id); ?>?');">✕ Delete</a>
        </div>
        
        <div class="flosc-msg-editor">
            <form method="post">
                <?php wp_nonce_field('flosc_save_ivr_message'); ?>
                <input type="hidden" name="message_id" value="<?php echo $safe_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th>Display Name</th>
                        <td><input type="text" name="message_name" value="<?php echo esc_attr($msg['name'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Phase</th>
                        <td>
                            <select name="message_phase">
                                <?php foreach (array_keys($phase_meta) as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php selected($msg['phase'] ?? $phase_id, $p); ?>><?php echo ucfirst($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <select name="message_type" onchange="floscToggleOfferFields(this, '<?php echo esc_js($msg_id); ?>')">
                                <option value="auto" <?php selected($msg['type'] ?? '', 'auto'); ?>>Auto (bot-initiated)</option>
                                <option value="suggested_user_autoprompt" <?php selected($msg['type'] ?? '', 'suggested_user_autoprompt'); ?>>Suggested User AutoPrompt</option>
                                <option value="offer" <?php selected($msg['type'] ?? '', 'offer'); ?>>Offer</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Content</th>
                        <td>
                            <textarea name="message_content" rows="4" class="large-text"><?php echo esc_textarea($msg['content'] ?? ''); ?></textarea>
                            <p class="description">Variables: {name}, {score}, {product_name}, {price}</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Conditions</th>
                        <td><input type="text" name="message_conditions" value="<?php echo esc_attr($msg['conditions'] ?? ''); ?>" class="large-text" placeholder="e.g. is_visitor && first_show_session"></td>
                    </tr>
                    <tr>
                        <th>Style</th>
                        <td>
                            <select name="message_style">
                                <?php foreach (['default','pill','button','chip','card'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php selected($msg['style'] ?? 'default', $s); ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Icon</th>
                        <td><input type="text" name="message_icon" value="<?php echo esc_attr($msg['icon'] ?? ''); ?>" class="small-text" placeholder="💬"></td>
                    </tr>
                    <tr>
                        <th>User Input</th>
                        <td><input type="text" name="message_user_input" value="<?php echo esc_attr($msg['user_input'] ?? ''); ?>" class="regular-text" placeholder="Button text for AutoPrompts"></td>
                    </tr>
                    <tr>
                        <th>Action</th>
                        <td><input type="text" name="message_action" value="<?php echo esc_attr($msg['action'] ?? ''); ?>" class="regular-text" placeholder="show_offer:offer_001, start_quiz, navigate:/lessons"></td>
                    </tr>
                </table>
                
                <!-- Offer-specific fields -->
                <div class="flosc-offer-fields-inner" id="offer-fields-<?php echo $safe_id; ?>" style="<?php echo ($msg['type'] ?? '') === 'offer' ? '' : 'display:none;'; ?>">
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
                                        <option value="<?php echo $fmt; ?>" <?php selected($msg['display_format'] ?? 'card', $fmt); ?>><?php echo ucfirst($fmt); ?></option>
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
                
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee;">
                    <button type="submit" name="save_ivr_message" class="button button-primary">💾 Save to DB</button>
                    <button type="submit" name="save_ivr_message_resync" class="button button-secondary">💾 Save & Resync to File</button>
                    <span style="font-size: 11px; color: #999; margin-left: auto;">ID: <?php echo esc_html($msg_id); ?></span>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Add New Message to this phase -->
    <?php $new_id = 'new_' . $phase_id . '_' . time(); ?>
    <div class="flosc-msg-card" id="card-<?php echo esc_attr($new_id); ?>" style="border-radius: 0;">
        <div class="flosc-msg-editor" id="editor-new-<?php echo $phase_id; ?>" style="display: none;">
            <form method="post">
                <?php wp_nonce_field('flosc_save_ivr_message'); ?>
                <input type="hidden" name="message_id" value="<?php echo esc_attr($new_id); ?>">
                <h4 style="margin: 0 0 12px;">✨ New <?php echo esc_html($pm['label']); ?> Message</h4>
                <table class="form-table">
                    <tr><th>Display Name</th><td><input type="text" name="message_name" value="" class="regular-text" required placeholder="Welcome Message"></td></tr>
                    <tr>
                        <th>Phase</th>
                        <td>
                            <select name="message_phase">
                                <?php foreach (array_keys($phase_meta) as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php selected($phase_id, $p); ?>><?php echo ucfirst($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <select name="message_type" onchange="floscToggleOfferFields(this, '<?php echo esc_js($new_id); ?>')">
                                <option value="auto">Auto (bot-initiated)</option>
                                <option value="suggested_user_autoprompt">Suggested User AutoPrompt</option>
                                <option value="offer">Offer</option>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Content</th><td><textarea name="message_content" rows="4" class="large-text" placeholder="Type your message content here..."></textarea></td></tr>
                    <tr><th>Conditions</th><td><input type="text" name="message_conditions" class="large-text" placeholder="e.g. is_visitor && first_show_session"></td></tr>
                    <tr>
                        <th>Style</th>
                        <td>
                            <select name="message_style">
                                <?php foreach (['default','pill','button','chip','card'] as $s): ?>
                                    <option value="<?php echo $s; ?>"><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Icon</th><td><input type="text" name="message_icon" class="small-text" placeholder="💬"></td></tr>
                    <tr><th>User Input</th><td><input type="text" name="message_user_input" class="regular-text" placeholder="Button text for AutoPrompts"></td></tr>
                    <tr><th>Action</th><td><input type="text" name="message_action" class="regular-text" placeholder="show_offer:offer_001"></td></tr>
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
                                    <option value="<?php echo $fmt; ?>"><?php echo ucfirst($fmt); ?></option>
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
                
                <div style="display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee;">
                    <button type="submit" name="save_ivr_message" class="button button-primary">💾 Save to DB</button>
                    <button type="submit" name="save_ivr_message_resync" class="button button-secondary">💾 Save & Resync to File</button>
                    <button type="button" class="button" onclick="document.getElementById('editor-new-<?php echo $phase_id; ?>').style.display='none';">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <button type="button" class="flosc-add-msg-btn" onclick="document.getElementById('editor-new-<?php echo $phase_id; ?>').style.display='block'; this.style.display='none';">
        + Add <?php echo esc_html($pm['label']); ?> Message
    </button>
</div>
<?php endforeach; ?>

<script>
function floscToggleMsg(id) {
    const card = document.getElementById('card-' + id);
    if (!card) return;
    card.classList.toggle('is-open');
}
function floscToggleOfferFields(selectEl, msgId) {
    const panel = document.getElementById('offer-fields-' + msgId);
    if (panel) panel.style.display = selectEl.value === 'offer' ? '' : 'none';
}
// Auto-scroll to expanded message on page load
document.addEventListener('DOMContentLoaded', function() {
    const open = document.querySelector('.flosc-msg-card.is-open');
    if (open) open.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
