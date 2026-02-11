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
    
    add_settings_error('flosc_settings', 'db_cleared', 'FLOSC DB cleared. Backup created automatically. Click "Load MD → FLOSC DB" to restore messages from the .md file.', 'success');
}

// Handle force resync (this is actually Load File → FLOSC DB)
if (isset($_POST['flosc_force_resync'])) {
    check_admin_referer('flosc_force_resync');
    
    $result = flosc_import_ivr_to_database(false, $flow_key);
    
    if ($result['success']) {
        // v1.5.4: Refresh flow_settings after import
        $flow_settings = get_option($flow_key, []);
        $GLOBALS['flosc_current_settings'] = $flow_settings;
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
    
    $result = flosc_import_ivr_to_database(false, $flow_key); // Execute import (not preview)
    
    if ($result['success']) {
        // v1.5.4: Refresh flow_settings after import
        $flow_settings = get_option($flow_key, []);
        $GLOBALS['flosc_current_settings'] = $flow_settings;
        add_settings_error('flosc_settings', 'ivr_imported', 'Loaded Active IVR Messages MD file → FLOSC DB: ' . esc_html($result['message']), 'success');
    } else {
        add_settings_error('flosc_settings', 'ivr_import_failed', 'Load failed: ' . esc_html($result['message']), 'error');
    }
}

// Generate comparison preview
$import_preview = null;
if (isset($_POST['flosc_preview_import'])) {
    check_admin_referer('flosc_preview_import');
    
    $result = flosc_import_ivr_to_database(true, $flow_key); // Preview only
    
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
    
    // Save message to FLOSC DB (per-flow)
    $messages[$msg_id] = $message_data;
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $fs['ivr_messages'] = $messages;
    
    // v1.5.5: Remove message from ALL phases first (handles phase reassignment)
    foreach ($phases as $p_key => &$p_ids) {
        $p_ids = array_values(array_diff($p_ids, [$msg_id]));
    }
    unset($p_ids);

    // Then add to the correct phase
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
        flosc_auto_export_ivr_to_file($flow_key);
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
    flosc_auto_export_ivr_to_file($flow_key);
    
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
        $available_ivr_files[] = $filename;
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

<h2>IVR Messages</h2>

<div class="flosc-info-box" style="margin-bottom: 20px; background: #f0f6ff; border-left: 4px solid #4f46e5; padding: 16px 20px; border-radius: 0 8px 8px 0;">
    <strong>📝 How IVR Messages Work</strong>
    <p style="margin: 8px 0 0;">Edit messages in your <code>.md</code> file, then click <strong>"📥 Load MD → FLOSC DB"</strong> above to import them. Only messages in the FLOSC DB are shown to your users. You can also edit messages inline below and click <strong>"🔄 Resync"</strong> to save back to the file.</p>
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
                <li>✅ <strong>New in file (will be added to FLOSC DB):</strong> <?php echo count($import_preview['added']); ?> message(s)
                    <?php if (!empty($import_preview['added'])): ?>
                        <br><span style="font-size: 12px; color: #667; margin-left: 20px;">
                            <code><?php echo implode(', ', array_slice($import_preview['added'], 0, 10)); ?></code>
                            <?php if (count($import_preview['added']) > 10): ?>
                                <em>... and <?php echo count($import_preview['added']) - 10; ?> more</em>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </li>
                <li>🔄 <strong>Exist in both (will be updated in FLOSC DB):</strong> <?php echo count($import_preview['updated']); ?> message(s)
                    <?php if (!empty($import_preview['updated'])): ?>
                        <br><span style="font-size: 12px; color: #667; margin-left: 20px;">
                            <code><?php echo implode(', ', array_slice($import_preview['updated'], 0, 10)); ?></code>
                            <?php if (count($import_preview['updated']) > 10): ?>
                                <em>... and <?php echo count($import_preview['updated']) - 10; ?> more</em>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </li>
                
                <?php if ($import_preview['has_deletions']): ?>
                <li style="color: #d63638;"><strong>⚠️ Only in FLOSC DB (will be REMOVED):</strong> <?php echo count($import_preview['deleted']); ?> message(s)
                    <br><span style="font-size: 12px; margin-left: 20px;">
                        <code><?php echo implode(', ', $import_preview['deleted']); ?></code>
                    </span>
                    <br><span style="font-size: 12px; color: #d63638; margin-left: 20px;">
                        <strong>These messages exist in FLOSC DB but NOT in the file. Loading will remove them!</strong>
                    </span>
                </li>
                <?php else: ?>
                <li style="color: #2e7d32;">✓ <strong>No deletions</strong> - all FLOSC DB messages exist in the Active IVR Messages MD file</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div style="background: #d4edda; padding: 12px; margin: 15px 0; border-left: 4px solid #28a745;">
            <strong>🔒 Auto-Backup Protection:</strong> Before loading, the current FLOSC DB will be saved as a backup file 
            (e.g. <code>bckp_01_<?php echo pathinfo($active_ivr_file, PATHINFO_FILENAME); ?>.md</code>)
        </div>
        
        <?php if ($import_preview['has_deletions']): ?>
        <div style="background: #f8d7da; padding: 12px; margin: 15px 0; border-left: 4px solid #dc3545; color: #721c24;">
            <strong>⚠️ WARNING:</strong> Loading will remove <?php echo count($import_preview['deleted']); ?> message(s) from the FLOSC DB that don't exist in the file.
            If you want to keep these messages, click "Resync" first to save the FLOSC DB to the file.
        </div>
        <?php endif; ?>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('flosc_confirm_import'); ?>
            <button type="submit" name="flosc_confirm_import" 
                    class="button button-primary" 
                    style="background: <?php echo $import_preview['has_deletions'] ? '#dc3545' : '#0073aa'; ?>; 
                           border-color: <?php echo $import_preview['has_deletions'] ? '#bd2130' : '#0073aa'; ?>;">
                <?php if ($import_preview['has_deletions']): ?>
                    ⚠️ Load Active IVR Messages MD file → FLOSC DB (Will Remove <?php echo count($import_preview['deleted']); ?> Message(s))
                <?php else: ?>
                    ✅ Load Active IVR Messages MD file → FLOSC DB
                <?php endif; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ivr-messages'); ?>" class="button button-secondary">
                ❌ Cancel
            </a>
        </form>
    </div>
<?php endif; ?>

<!-- v1.5.5: Flat-scroll layout — all phases, all messages, inline editing -->
<style>
.flosc-phase-header {
    background: #1d2327;
    color: #fff;
    padding: 14px 20px;
    margin: 30px 0 0;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 32px; /* below WP admin bar */
    z-index: 90;
}
.flosc-phase-header:first-of-type { margin-top: 0; }
.flosc-phase-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.flosc-phase-header .flosc-phase-count {
    background: rgba(255,255,255,0.15);
    padding: 3px 12px;
    border-radius: 12px;
    font-size: 12px;
}
.flosc-phase-header .flosc-phase-desc {
    font-size: 12px;
    opacity: 0.7;
    font-weight: normal;
    margin-left: 12px;
}
.flosc-msg-card {
    background: #fff;
    border: 1px solid #ddd;
    border-top: none;
    padding: 0;
    transition: border-color 0.15s;
}
.flosc-msg-card:last-child {
    border-radius: 0 0 8px 8px;
    margin-bottom: 0;
}
.flosc-msg-card:hover {
    border-color: #b0b5ba;
}
.flosc-msg-summary {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: start;
    padding: 14px 20px;
    cursor: pointer;
    gap: 16px;
}
.flosc-msg-summary:hover {
    background: #f9f9fb;
}
.flosc-msg-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}
.flosc-msg-name {
    font-weight: 600;
    font-size: 14px;
    color: #1d2327;
}
.flosc-msg-id {
    font-size: 11px;
    color: #999;
    font-family: monospace;
}
.flosc-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
    line-height: 1.4;
}
.flosc-badge-auto { background: #e8f4fd; color: #0369a1; }
.flosc-badge-autoprompt { background: #f0fdf4; color: #166534; }
.flosc-badge-offer { background: #fef3c7; color: #92400e; }
.flosc-badge-pill { background: #f3e8ff; color: #7c3aed; }
.flosc-badge-button { background: #dbeafe; color: #1d4ed8; }
.flosc-badge-card { background: #fce7f3; color: #be185d; }
.flosc-badge-chip { background: #e0e7ff; color: #4338ca; }
.flosc-msg-preview {
    font-size: 13px;
    color: #555;
    margin-top: 4px;
    line-height: 1.4;
    max-height: 2.8em;
    overflow: hidden;
}
.flosc-msg-conditions {
    font-size: 11px;
    color: #8b8d91;
    margin-top: 4px;
    font-family: monospace;
}
.flosc-msg-conditions::before {
    content: 'if: ';
    color: #b0b5ba;
}
.flosc-msg-actions-col {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
/* Expanded edit form */
.flosc-msg-edit {
    display: none;
    border-top: 2px solid #4f46e5;
    padding: 20px;
    background: #fafbff;
}
.flosc-msg-card.is-editing .flosc-msg-edit {
    display: block;
}
.flosc-msg-card.is-editing .flosc-msg-summary {
    background: #f0f2ff;
    border-bottom: 1px solid #e0e2ef;
}
.flosc-edit-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 20px;
}
.flosc-edit-grid .flosc-field-full {
    grid-column: 1 / -1;
}
.flosc-edit-grid label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.flosc-edit-grid input[type="text"],
.flosc-edit-grid select,
.flosc-edit-grid textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    background: #fff;
    transition: border-color 0.15s;
}
.flosc-edit-grid input:focus,
.flosc-edit-grid select:focus,
.flosc-edit-grid textarea:focus {
    border-color: #4f46e5;
    outline: none;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
}
.flosc-edit-grid textarea {
    min-height: 80px;
    resize: vertical;
}
.flosc-edit-grid .flosc-field-hint {
    font-size: 11px;
    color: #999;
    margin-top: 3px;
}
.flosc-edit-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}
.flosc-edit-actions .button-primary {
    background: #4f46e5;
    border-color: #4f46e5;
}
.flosc-edit-actions .button-primary:hover {
    background: #4338ca;
    border-color: #4338ca;
}
.flosc-phase-empty {
    padding: 30px 20px;
    text-align: center;
    color: #999;
    background: #fff;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 8px 8px;
    font-size: 14px;
}
.flosc-add-msg-row {
    background: #fff;
    border: 1px solid #ddd;
    border-top: none;
    padding: 10px 20px;
    text-align: center;
}
.flosc-add-msg-row:last-child {
    border-radius: 0 0 8px 8px;
}
</style>

<?php
$phase_labels = [
    'freeline' => ['emoji' => '🎯', 'label' => 'Freeline', 'desc' => 'Visitor — not logged in'],
    'login'    => ['emoji' => '🔑', 'label' => 'Login / Guest', 'desc' => 'Logged in, has quiz score, gets free lesson'],
    'offer'    => ['emoji' => '🎁', 'label' => 'Offer', 'desc' => 'Present purchase options after free lesson'],
    'sale'     => ['emoji' => '💳', 'label' => 'Sale', 'desc' => 'Post-purchase onboarding'],
    'content'  => ['emoji' => '📚', 'label' => 'Content', 'desc' => 'Member — ongoing access & support'],
];

// Which message is being saved? Scroll back to it after save
$just_saved_id = '';
if (isset($_POST['save_ivr_message']) || isset($_POST['save_ivr_message_resync'])) {
    $just_saved_id = sanitize_text_field($_POST['message_id'] ?? '');
}

foreach ($phase_labels as $phase_key => $phase_info):
    $phase_msgs = $phases[$phase_key] ?? [];
    $phase_count = count($phase_msgs);
?>

<div class="flosc-phase-header" id="phase-<?php echo $phase_key; ?>">
    <h3>
        <?php echo $phase_info['emoji']; ?> <?php echo $phase_info['label']; ?>
        <span class="flosc-phase-desc"><?php echo $phase_info['desc']; ?></span>
    </h3>
    <span class="flosc-phase-count"><?php echo $phase_count; ?> message<?php echo $phase_count !== 1 ? 's' : ''; ?></span>
</div>

<?php if (empty($phase_msgs)): ?>
    <div class="flosc-phase-empty">
        No messages in this phase. Add one below, or load from your <code>.md</code> file.
    </div>
<?php else: ?>
    <?php foreach ($phase_msgs as $msg_id):
        if (!isset($messages[$msg_id])) continue;
        $msg = $messages[$msg_id];
        $is_editing = (isset($_GET['edit_message']) && $_GET['edit_message'] === $msg_id) || $just_saved_id === $msg_id;
        $type_label = $msg['type'] ?? 'auto';
        $style_val = $msg['style'] ?? 'default';
        $badge_class = 'flosc-badge-auto';
        if ($type_label === 'suggested_user_autoprompt') $badge_class = 'flosc-badge-autoprompt';
        if ($type_label === 'offer') $badge_class = 'flosc-badge-offer';
        $style_badge_class = '';
        if ($style_val === 'pill') $style_badge_class = 'flosc-badge-pill';
        elseif ($style_val === 'button') $style_badge_class = 'flosc-badge-button';
        elseif ($style_val === 'card') $style_badge_class = 'flosc-badge-card';
        elseif ($style_val === 'chip') $style_badge_class = 'flosc-badge-chip';
    ?>
    <div class="flosc-msg-card <?php echo $is_editing ? 'is-editing' : ''; ?>" id="msg-<?php echo esc_attr($msg_id); ?>">
        <div class="flosc-msg-summary" onclick="floscToggleEdit('<?php echo esc_js($msg_id); ?>')">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="flosc-msg-name"><?php echo esc_html($msg['name'] ?? $msg_id); ?></span>
                    <?php if (!empty($msg['icon'])): ?>
                        <span style="font-size: 16px;"><?php echo esc_html($msg['icon']); ?></span>
                    <?php endif; ?>
                    <span class="flosc-badge <?php echo $badge_class; ?>"><?php
                        echo $type_label === 'suggested_user_autoprompt' ? 'autoprompt' : esc_html($type_label);
                    ?></span>
                    <?php if ($style_badge_class): ?>
                        <span class="flosc-badge <?php echo $style_badge_class; ?>"><?php echo esc_html($style_val); ?></span>
                    <?php endif; ?>
                </div>
                <div class="flosc-msg-id"><?php echo esc_html($msg_id); ?></div>
                <div class="flosc-msg-preview"><?php echo esc_html(wp_trim_words($msg['content'] ?? '', 25)); ?></div>
                <?php if (!empty($msg['conditions'])): ?>
                    <div class="flosc-msg-conditions"><?php echo esc_html($msg['conditions']); ?></div>
                <?php endif; ?>
                <?php if (!empty($msg['action'])): ?>
                    <div style="font-size: 11px; color: #6d28d9; margin-top: 2px; font-family: monospace;">⚡ <?php echo esc_html($msg['action']); ?></div>
                <?php endif; ?>
            </div>
            <div class="flosc-msg-actions-col">
                <button type="button" class="button button-small" onclick="event.stopPropagation(); floscToggleEdit('<?php echo esc_js($msg_id); ?>')">✏️</button>
            </div>
        </div>

        <div class="flosc-msg-edit">
            <form method="post" action="<?php echo admin_url('admin.php?page=flosc-settings&tab=ivr-messages'); ?>#msg-<?php echo esc_attr($msg_id); ?>">
                <?php wp_nonce_field('flosc_save_ivr_message'); ?>
                <input type="hidden" name="message_id" value="<?php echo esc_attr($msg_id); ?>">

                <div class="flosc-edit-grid">
                    <div>
                        <label>Name</label>
                        <input type="text" name="message_name" value="<?php echo esc_attr($msg['name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label>Message ID</label>
                        <input type="text" value="<?php echo esc_attr($msg_id); ?>" disabled style="background: #f0f0f1;">
                    </div>
                    <div>
                        <label>Type</label>
                        <select name="message_type">
                            <option value="auto" <?php selected($msg['type'] ?? 'auto', 'auto'); ?>>Auto (bot-initiated)</option>
                            <option value="suggested_user_autoprompt" <?php selected($msg['type'] ?? '', 'suggested_user_autoprompt'); ?>>AutoPrompt (user button)</option>
                            <option value="offer" <?php selected($msg['type'] ?? '', 'offer'); ?>>Offer</option>
                        </select>
                    </div>
                    <div>
                        <label>Phase</label>
                        <select name="message_phase">
                            <?php foreach (['freeline', 'login', 'offer', 'sale', 'content'] as $p): ?>
                                <option value="<?php echo $p; ?>" <?php selected($phase_key, $p); ?>><?php echo ucfirst($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Style</label>
                        <select name="message_style">
                            <option value="default" <?php selected($style_val, 'default'); ?>>Default</option>
                            <option value="pill" <?php selected($style_val, 'pill'); ?>>Pill</option>
                            <option value="button" <?php selected($style_val, 'button'); ?>>Button</option>
                            <option value="chip" <?php selected($style_val, 'chip'); ?>>Chip</option>
                            <option value="card" <?php selected($style_val, 'card'); ?>>Card</option>
                        </select>
                    </div>
                    <div>
                        <label>Icon (emoji)</label>
                        <input type="text" name="message_icon" value="<?php echo esc_attr($msg['icon'] ?? ''); ?>" placeholder="🚀">
                    </div>
                    <div class="flosc-field-full">
                        <label>User Input Text <span style="font-weight: normal; color: #999;">(shown on autoprompt button)</span></label>
                        <input type="text" name="message_user_input" value="<?php echo esc_attr($msg['user_input'] ?? ''); ?>" placeholder="e.g. Start free quiz">
                    </div>
                    <div class="flosc-field-full">
                        <label>Content</label>
                        <textarea name="message_content" rows="4"><?php echo esc_textarea($msg['content'] ?? ''); ?></textarea>
                        <div class="flosc-field-hint">Variables: {name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {user_status_response}</div>
                    </div>
                    <div class="flosc-field-full">
                        <label>Conditions</label>
                        <input type="text" name="message_conditions" value="<?php echo esc_attr($msg['conditions'] ?? ''); ?>" placeholder="e.g. is_visitor && quiz_taken && score >= 70">
                        <div class="flosc-field-hint">Combine with <code>&&</code> (and), <code>||</code> (or), <code>!</code> (not). Available: is_visitor, is_guest, is_member, quiz_taken, score > N, lesson_viewed, first_show_session, first_message_after_quiz, free_lessons_count == N, inactive_seconds > N, always</div>
                    </div>
                    <div class="flosc-field-full">
                        <label>Action</label>
                        <input type="text" name="message_action" value="<?php echo esc_attr($msg['action'] ?? ''); ?>" placeholder="e.g. open_quiz, open_free_lesson, checkout_oto_main">
                        <div class="flosc-field-hint">Actions: open_quiz, open_registration, open_free_lesson, show_offer_{id}, checkout_{id}, sandbox_purchase_{id}, open_lesson_library, open_quiz_library, open_support</div>
                    </div>
                </div>

                <div class="flosc-edit-actions">
                    <button type="submit" name="save_ivr_message" class="button button-primary">💾 Save to DB</button>
                    <button type="submit" name="save_ivr_message_resync" class="button button-secondary">💾 Save &amp; Sync to File</button>
                    <button type="button" class="button" onclick="floscToggleEdit('<?php echo esc_js($msg_id); ?>')">Cancel</button>
                    <span style="flex: 1;"></span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&delete_message=' . urlencode($msg_id) . '&phase=' . $phase_key . '&_wpnonce=' . wp_create_nonce('flosc_delete_message_' . $msg_id))); ?>"
                       class="button" style="color: #dc3545; border-color: #dc3545;"
                       onclick="return confirm('Delete this message permanently?');">🗑️ Delete</a>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Add message button for this phase -->
<div class="flosc-add-msg-row">
    <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=ivr-messages&edit_message=new_' . $phase_key . '_' . time() . '&new_phase=' . $phase_key)); ?>#msg-new_<?php echo $phase_key; ?>_" class="button button-small">+ Add <?php echo $phase_info['label']; ?> Message</a>
</div>

<?php endforeach; ?>

<!-- Handle "new message" inline -->
<?php
$new_msg_id = $_GET['edit_message'] ?? '';
$new_phase = $_GET['new_phase'] ?? '';
if ($new_msg_id && $new_phase && !isset($messages[$new_msg_id])):
?>
<div class="flosc-phase-header" style="background: #166534;">
    <h3>➕ New <?php echo ucfirst($new_phase); ?> Message</h3>
</div>
<div class="flosc-msg-card is-editing" id="msg-<?php echo esc_attr($new_msg_id); ?>">
    <div class="flosc-msg-edit" style="display: block; border-top: 2px solid #166534;">
        <form method="post" action="<?php echo admin_url('admin.php?page=flosc-settings&tab=ivr-messages'); ?>#phase-<?php echo esc_attr($new_phase); ?>">
            <?php wp_nonce_field('flosc_save_ivr_message'); ?>
            <input type="hidden" name="message_id" value="<?php echo esc_attr($new_msg_id); ?>">
            <input type="hidden" name="message_phase" value="<?php echo esc_attr($new_phase); ?>">

            <div class="flosc-edit-grid">
                <div>
                    <label>Name</label>
                    <input type="text" name="message_name" value="" placeholder="e.g. Welcome Visitor">
                </div>
                <div>
                    <label>Message ID</label>
                    <input type="text" name="message_id_display" value="<?php echo esc_attr($new_msg_id); ?>" disabled style="background: #f0f0f1;">
                </div>
                <div>
                    <label>Type</label>
                    <select name="message_type">
                        <option value="auto">Auto (bot-initiated)</option>
                        <option value="suggested_user_autoprompt">AutoPrompt (user button)</option>
                        <option value="offer">Offer</option>
                    </select>
                </div>
                <div>
                    <label>Phase</label>
                    <select name="message_phase">
                        <?php foreach (['freeline', 'login', 'offer', 'sale', 'content'] as $p): ?>
                            <option value="<?php echo $p; ?>" <?php selected($new_phase, $p); ?>><?php echo ucfirst($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Style</label>
                    <select name="message_style">
                        <option value="default">Default</option>
                        <option value="pill">Pill</option>
                        <option value="button">Button</option>
                        <option value="chip">Chip</option>
                        <option value="card">Card</option>
                    </select>
                </div>
                <div>
                    <label>Icon (emoji)</label>
                    <input type="text" name="message_icon" value="" placeholder="🚀">
                </div>
                <div class="flosc-field-full">
                    <label>User Input Text</label>
                    <input type="text" name="message_user_input" value="" placeholder="e.g. Start free quiz">
                </div>
                <div class="flosc-field-full">
                    <label>Content</label>
                    <textarea name="message_content" rows="4" placeholder="The message your users will see..."></textarea>
                </div>
                <div class="flosc-field-full">
                    <label>Conditions</label>
                    <input type="text" name="message_conditions" value="" placeholder="e.g. is_visitor && first_show_session">
                    <div class="flosc-field-hint">Combine with <code>&&</code> (and), <code>||</code> (or), <code>!</code> (not)</div>
                </div>
                <div class="flosc-field-full">
                    <label>Action</label>
                    <input type="text" name="message_action" value="" placeholder="e.g. open_quiz">
                    <div class="flosc-field-hint">Actions: open_quiz, open_registration, open_free_lesson, show_offer_{id}, checkout_{id}, open_lesson_library</div>
                </div>
            </div>

            <div class="flosc-edit-actions">
                <button type="submit" name="save_ivr_message" class="button button-primary">💾 Create Message</button>
                <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ivr-messages'); ?>" class="button">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function floscToggleEdit(msgId) {
    const card = document.getElementById('msg-' + msgId);
    if (!card) return;
    card.classList.toggle('is-editing');
    if (card.classList.contains('is-editing')) {
        // Scroll the card into view with some padding
        setTimeout(() => {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 50);
    }
}

// Auto-scroll to saved message after page reload
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const el = document.querySelector(window.location.hash);
        if (el) {
            setTimeout(() => {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 200);
        }
    }
});
</script>

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
