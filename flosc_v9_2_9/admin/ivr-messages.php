<?php
/**
 * FLOSC IVR Messages Tab - Full CRUD Editor (v9.2.7)
 * 
 * Database-first approach with import/export functionality
 * ivr.md is source of truth - import REPLACES database (with auto-backup)
 */

if (!defined('ABSPATH')) exit;

/**
 * Run IVR diagnostics - checks DB, file, sync status
 */
function flosc_run_ivr_diagnostics() {
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
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
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
    
    // 3. Database Messages Check
    $db_messages = get_option('flosc_ivr_messages', []);
    $db_phases = get_option('flosc_ivr_phases', []);
    $last_import = get_option('flosc_ivr_last_import', 'Never');
    
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
    
    // 5. API Endpoint (just show URL, actual test is client-side)
    $api_url = rest_url('flosc/v1/ivr-messages?phase=freeline');
    $diagnostics['api_endpoint'] = [
        'status' => 'yellow',
        'message' => 'Click Test',
        'details' => [
            "Click 'Test API Endpoint' to verify"
        ],
        'url' => $api_url
    ];
    
    return $diagnostics;
}

// Handle clear DB action
if (isset($_POST['flosc_clear_ivr_db'])) {
    check_admin_referer('flosc_clear_ivr_db');
    
    // Backup first
    flosc_export_ivr_backup();
    
    // Clear
    update_option('flosc_ivr_messages', []);
    update_option('flosc_ivr_phases', [
        'freeline' => [],
        'login' => [],
        'offer' => [],
        'sale' => [],
        'content' => [],
    ]);
    update_option('flosc_ivr_styles', []);
    delete_option('flosc_ivr_last_import');
    
    add_settings_error('flosc_settings', 'db_cleared', 'FLOSC DB cleared. Backup created automatically.', 'success');
}

// Handle force resync (this is actually Load File → FLOSC DB)
if (isset($_POST['flosc_force_resync'])) {
    check_admin_referer('flosc_force_resync');
    
    $result = flosc_import_ivr_to_database(false);
    
    if ($result['success']) {
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
    
    $result = flosc_import_ivr_to_database(false); // Execute import (not preview)
    
    if ($result['success']) {
        add_settings_error('flosc_settings', 'ivr_imported', 'Loaded Active IVR Messages MD file → FLOSC DB: ' . esc_html($result['message']), 'success');
    } else {
        add_settings_error('flosc_settings', 'ivr_import_failed', 'Load failed: ' . esc_html($result['message']), 'error');
    }
}

// Generate comparison preview
$import_preview = null;
if (isset($_POST['flosc_preview_import'])) {
    check_admin_referer('flosc_preview_import');
    
    $result = flosc_import_ivr_to_database(true); // Preview only
    
    if ($result['success'] && isset($result['preview'])) {
        $import_preview = $result['stats'];
    } else {
        add_settings_error('flosc_settings', 'preview_failed', 'Compare failed. Check that the Active IVR Messages MD file exists and is valid.', 'error');
    }
}

// Handle export
if (isset($_POST['flosc_export_ivr'])) {
    check_admin_referer('flosc_export_ivr');
    
    $messages = get_option('flosc_ivr_messages', []);
    $phases = get_option('flosc_ivr_phases', []);
    $styles = get_option('flosc_ivr_styles', []);
    
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
// v9.2.9: Two save options - DB only or DB + Resync to file
if (isset($_POST['save_ivr_message']) || isset($_POST['save_ivr_message_resync'])) {
    check_admin_referer('flosc_save_ivr_message');
    
    $do_resync = isset($_POST['save_ivr_message_resync']);
    
    $messages = get_option('flosc_ivr_messages', []);
    $phases = get_option('flosc_ivr_phases', []);
    
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
    
    // Save message to FLOSC DB
    $messages[$msg_id] = $message_data;
    update_option('flosc_ivr_messages', $messages);
    
    // Update phase mapping
    if (!isset($phases[$phase])) {
        $phases[$phase] = [];
    }
    if (!in_array($msg_id, $phases[$phase])) {
        $phases[$phase][] = $msg_id;
    }
    update_option('flosc_ivr_phases', $phases);
    
    // v9.2.9: Only resync to file if user clicked "Save and Resync"
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
    
    $messages = get_option('flosc_ivr_messages', []);
    $phases = get_option('flosc_ivr_phases', []);
    
    unset($messages[$msg_id]);
    update_option('flosc_ivr_messages', $messages);
    
    if (isset($phases[$phase])) {
        $phases[$phase] = array_diff($phases[$phase], [$msg_id]);
        update_option('flosc_ivr_phases', $phases);
    }
    
    // v9.2.9: Delete always resyncs to file (destructive operation)
    flosc_auto_export_ivr_to_file();
    
    add_settings_error('flosc_settings', 'message_deleted', 'Message deleted from FLOSC DB and removed from Active IVR Messages MD file', 'success');
}

$messages = get_option('flosc_ivr_messages', []);
$phases = get_option('flosc_ivr_phases', []);
$active_phase = $_GET['ivr_phase'] ?? 'freeline';
$editing_message = $_GET['edit_message'] ?? null;

// v9.2.9: Handle file upload
if (isset($_POST['flosc_upload_ivr_file']) && !empty($_FILES['ivr_file_upload']['tmp_name'])) {
    check_admin_referer('flosc_upload_ivr_file');
    
    $uploaded_file = $_FILES['ivr_file_upload'];
    
    // Validate file type
    $file_ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'md') {
        add_settings_error('flosc_settings', 'upload_error', 'Invalid file type. Please upload a .md (Markdown) file.', 'error');
    } elseif ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        add_settings_error('flosc_settings', 'upload_error', 'Upload failed. Error code: ' . $uploaded_file['error'], 'error');
    } else {
        // Sanitize filename
        $new_filename = sanitize_file_name($uploaded_file['name']);
        if (strpos($new_filename, 'ivr') !== 0) {
            $new_filename = 'ivr-' . $new_filename;
        }
        
        $destination = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $new_filename;
        
        // Check if file already exists
        if (file_exists($destination) && !isset($_POST['overwrite_existing'])) {
            add_settings_error('flosc_settings', 'upload_exists', 'File "' . $new_filename . '" already exists. Check "Overwrite if exists" to replace it.', 'error');
        } else {
            // Move uploaded file
            if (move_uploaded_file($uploaded_file['tmp_name'], $destination)) {
                // Validate it's parseable
                require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
                $parser = FLOSC_IVR_Parser::flosc_instance();
                $content = file_get_contents($destination);
                $config = $parser->flosc_parse($content);
                
                $msg_count = count($config['messages'] ?? []);
                
                if ($msg_count > 0) {
                    add_settings_error('flosc_settings', 'upload_success', 
                        'File "' . $new_filename . '" uploaded successfully! Found ' . $msg_count . ' messages. ' .
                        'Select it from the dropdown and click "Load" to import into the FLOSC DB.', 'success');
                } else {
                    add_settings_error('flosc_settings', 'upload_warning', 
                        'File "' . $new_filename . '" uploaded but contains 0 parseable messages. ' .
                        'Check the file format matches the IVR markdown specification.', 'warning');
                }
            } else {
                add_settings_error('flosc_settings', 'upload_error', 'Failed to save uploaded file. Check directory permissions.', 'error');
            }
        }
    }
}

// v9.2.9: Handle active file change
if (isset($_POST['flosc_set_active_ivr_file'])) {
    check_admin_referer('flosc_set_active_ivr_file');
    
    $new_active = sanitize_file_name($_POST['active_ivr_file']);
    $file_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $new_active;
    
    if (file_exists($file_path)) {
        update_option('flosc_active_ivr_file', $new_active);
        add_settings_error('flosc_settings', 'active_changed', 
            'Active IVR file changed to "' . $new_active . '". Click "Load" to import it into the FLOSC DB.', 'success');
    } else {
        add_settings_error('flosc_settings', 'file_not_found', 'File not found: ' . $new_active, 'error');
    }
}

// v9.2.9: Get list of available IVR files
$ivr_files_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
$available_ivr_files = [];
$backup_files = [];
if (is_dir($ivr_files_dir)) {
    $files = glob($ivr_files_dir . 'ivr*.md');
    foreach ($files as $file) {
        $filename = basename($file);
        if (strpos($filename, 'backup') !== false) {
            $backup_files[] = $filename;
        } else {
            $available_ivr_files[] = $filename;
        }
    }
}
$active_ivr_file = get_option('flosc_active_ivr_file', 'ivr.md');

?>

</form>

<!-- IVR System Status Panel -->
<div class="flosc-diagnostics-panel" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
        🔧 IVR System Status
        <span style="font-size: 12px; font-weight: normal; color: #666;">(v9.2.9)</span>
    </h3>
    
    <!-- Active IVR File Selector -->
    <div style="background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
        <div style="display: flex; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
            
            <!-- File Selector -->
            <div style="flex: 1; min-width: 300px;">
                <form method="post" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <?php wp_nonce_field('flosc_set_active_ivr_file'); ?>
                    <label style="font-weight: 600; white-space: nowrap;">
                        📁 Active IVR File:
                    </label>
                    <select name="active_ivr_file" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #ccc; min-width: 200px;">
                        <?php foreach ($available_ivr_files as $file): ?>
                        <option value="<?php echo esc_attr($file); ?>" <?php selected($active_ivr_file, $file); ?>>
                            <?php echo esc_html($file); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="flosc_set_active_ivr_file" class="button button-secondary">
                        Set Active
                    </button>
                </form>
                <p style="font-size: 12px; color: #666; margin: 8px 0 0 0;">
                    Select which IVR file to use. Click "Load" below to import into FLOSC DB.
                </p>
            </div>
            
            <!-- Upload New File -->
            <div style="flex: 1; min-width: 300px; border-left: 1px solid #b3d7ff; padding-left: 20px;">
                <form method="post" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <?php wp_nonce_field('flosc_upload_ivr_file'); ?>
                    <label style="font-weight: 600; white-space: nowrap;">
                        📤 Upload New:
                    </label>
                    <input type="file" name="ivr_file_upload" accept=".md" style="flex: 1; min-width: 150px;">
                    <label style="font-size: 12px; white-space: nowrap;">
                        <input type="checkbox" name="overwrite_existing" value="1"> Overwrite if exists
                    </label>
                    <button type="submit" name="flosc_upload_ivr_file" class="button button-primary">
                        Upload
                    </button>
                </form>
                <p style="font-size: 12px; color: #666; margin: 8px 0 0 0;">
                    Upload a new .md file. Filename must start with "ivr" (will be prefixed if not).
                </p>
            </div>
            
        </div>
        
        <?php if (!empty($backup_files)): ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #b3d7ff;">
            <details>
                <summary style="cursor: pointer; font-size: 12px; color: #666;">
                    📦 <?php echo count($backup_files); ?> backup file(s) available
                </summary>
                <div style="margin-top: 10px; font-size: 12px; color: #666;">
                    <?php foreach ($backup_files as $backup): ?>
                        <code style="display: inline-block; margin: 2px 5px 2px 0; padding: 2px 6px; background: #f0f0f0; border-radius: 3px;">
                            <?php echo esc_html($backup); ?>
                        </code>
                    <?php endforeach; ?>
                    <p style="margin: 10px 0 0 0;">
                        To restore a backup, rename it in your file manager (remove "backup" from filename) or upload it above.
                    </p>
                </div>
            </details>
        </div>
        <?php endif; ?>
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
            <div style="font-size: 11px; color: #666; margin-top: 5px;">
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
                <p style="font-size: 12px; color: #666; margin: 0;">
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
                <p style="font-size: 12px; color: #666; margin: 0;">
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
                <p style="font-size: 12px; color: #666; margin: 0;">
                    Load messages from the Active IVR Messages MD file into the FLOSC DB. This replaces your current FLOSC DB messages. Use this for initial setup or to switch to a different IVR configuration. A backup is created automatically.
                </p>
            </div>
            
            <!-- Test API Button -->
            <div style="background: white; border: 1px solid #ddd; border-radius: 6px; padding: 15px;">
                <button type="button" id="flosc-test-api" class="button button-secondary" onclick="floscTestAPI()" style="width: 100%; margin-bottom: 10px;">
                    🔌 Test API Endpoint
                </button>
                <p style="font-size: 12px; color: #666; margin: 0;">
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
                Last sync: <?php echo get_option('flosc_ivr_last_import', 'Never'); ?>
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

<h2>IVR Messages Configuration</h2>

<div class="flosc-info-box" style="margin-bottom: 20px;">
    <strong>💾 FLOSC IVR System (v9.2.9)</strong>
    <p>Messages are stored in the FLOSC DB for fast API access. Use the controls above to sync with your Active IVR Messages MD file.</p>
    <p style="margin-top: 10px;"><strong>Workflow:</strong> Edit messages below → Save → Changes appear on frontend → Click "Resync" to save to file</p>
    <ul style="margin: 10px 0 0 20px;">
        <li><strong>Freeline:</strong> Visitor (not logged in) - Encourage quiz</li>
        <li><strong>Login:</strong> Post-quiz + Logged-in - Deliver free lesson, present offer</li>
        <li><strong>Offer:</strong> Sales pitch</li>
        <li><strong>Sale:</strong> Post-purchase onboarding</li>
        <li><strong>Content:</strong> Ongoing member support</li>
    </ul>
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
                        <br><span style="font-size: 12px; color: #666; margin-left: 20px;">
                            <code><?php echo implode(', ', array_slice($import_preview['added'], 0, 10)); ?></code>
                            <?php if (count($import_preview['added']) > 10): ?>
                                <em>... and <?php echo count($import_preview['added']) - 10; ?> more</em>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </li>
                <li>🔄 <strong>Exist in both (will be updated in FLOSC DB):</strong> <?php echo count($import_preview['updated']); ?> message(s)
                    <?php if (!empty($import_preview['updated'])): ?>
                        <br><span style="font-size: 12px; color: #666; margin-left: 20px;">
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
            <strong>🔒 Auto-Backup Protection:</strong> Before loading, the current FLOSC DB will be saved to 
            <code>ivr-backup-<?php echo date('Y-m-d_H-i-s'); ?>.md</code>
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

<!-- Phase Tabs -->
<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
    <?php foreach (['freeline', 'login', 'offer', 'sale', 'content'] as $phase): ?>
        <a href="?page=flosc-settings&tab=ivr-messages&ivr_phase=<?php echo $phase; ?>" 
           class="nav-tab <?php echo $active_phase === $phase ? 'nav-tab-active' : ''; ?>">
            <?php echo ucfirst($phase); ?>
            <span style="opacity: 0.6;">(<?php echo count($phases[$phase] ?? []); ?>)</span>
        </a>
    <?php endforeach; ?>
</nav>

<!-- Message Editor -->
<?php if ($editing_message && isset($messages[$editing_message])): ?>
    <?php $msg = $messages[$editing_message]; ?>
    
    <div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin-bottom: 20px;">
        <h3>Edit Message: <?php echo esc_html($msg['name']); ?></h3>
        
        <form method="post">
            <?php wp_nonce_field('flosc_save_ivr_message'); ?>
            <input type="hidden" name="message_id" value="<?php echo esc_attr($editing_message); ?>">
            
            <table class="form-table">
                <tr>
                    <th>Message ID</th>
                    <td><code><?php echo esc_html($editing_message); ?></code></td>
                </tr>
                <tr>
                    <th>Display Name</th>
                    <td><input type="text" name="message_name" value="<?php echo esc_attr($msg['name']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Phase</th>
                    <td>
                        <select name="message_phase">
                            <?php foreach (['freeline', 'login', 'offer', 'sale', 'content'] as $p): ?>
                                <option value="<?php echo $p; ?>" <?php selected($msg['phase'], $p); ?>><?php echo ucfirst($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Message Type</th>
                    <td>
                        <select name="message_type">
                            <option value="auto" <?php selected($msg['type'], 'auto'); ?>>Auto (bot-initiated)</option>
                            <option value="suggested_user_autoprompt" <?php selected($msg['type'], 'suggested_user_autoprompt'); ?>>Suggested User AutoPrompt (button)</option>
                            <option value="offer" <?php selected($msg['type'], 'offer'); ?>>Offer</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Content</th>
                    <td>
                        <textarea name="message_content" rows="5" class="large-text"><?php echo esc_textarea($msg['content']); ?></textarea>
                        <p class="description">Available variables: {name}, {score}, {product_name}, {price}</p>
                    </td>
                </tr>
                <tr>
                    <th>Conditions</th>
                    <td>
                        <input type="text" name="message_conditions" value="<?php echo esc_attr($msg['conditions'] ?? ''); ?>" class="large-text">
                        <p class="description">e.g., is_visitor && first_show_session, score > 70, !quiz_taken</p>
                    </td>
                </tr>
                <tr>
                    <th>Style</th>
                    <td>
                        <select name="message_style">
                            <option value="default" <?php selected($msg['style'] ?? 'default', 'default'); ?>>Default</option>
                            <option value="pill" <?php selected($msg['style'] ?? 'default', 'pill'); ?>>Pill</option>
                            <option value="button" <?php selected($msg['style'] ?? 'default', 'button'); ?>>Button</option>
                            <option value="chip" <?php selected($msg['style'] ?? 'default', 'chip'); ?>>Chip</option>
                            <option value="card" <?php selected($msg['style'] ?? 'default', 'card'); ?>>Card</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Icon (emoji)</th>
                    <td><input type="text" name="message_icon" value="<?php echo esc_attr($msg['icon'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>User Input Text</th>
                    <td>
                        <input type="text" name="message_user_input" value="<?php echo esc_attr($msg['user_input'] ?? ''); ?>" class="regular-text">
                        <p class="description">For AutoPrompts: text shown on button</p>
                    </td>
                </tr>
                <tr>
                    <th>Action</th>
                    <td>
                        <input type="text" name="message_action" value="<?php echo esc_attr($msg['action'] ?? ''); ?>" class="regular-text">
                        <p class="description">e.g., show_offer:offer_001, start_quiz, navigate:/lessons</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="save_ivr_message" class="button button-primary">Save to FLOSC DB</button>
                <button type="submit" name="save_ivr_message_resync" class="button button-secondary">Save to FLOSC DB and Resync to File</button>
                <a href="?page=flosc-settings&tab=ivr-messages&ivr_phase=<?php echo esc_attr($active_phase); ?>" class="button">Cancel</a>
            </p>
            <p class="description" style="margin-top: 10px;">
                <strong>Save to FLOSC DB:</strong> Changes appear on frontend immediately. Use this for quick edits.<br>
                <strong>Save and Resync:</strong> Also writes changes to the Active IVR Messages MD file. Use this to commit your edits to the file.
            </p>
        </form>
    </div>
    
<?php endif; ?>

<!-- Messages List -->
<div style="background: #fff; border: 1px solid #ccc; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0;"><?php echo ucfirst($active_phase); ?> Messages</h3>
        <a href="?page=flosc-settings&tab=ivr-messages&ivr_phase=<?php echo $active_phase; ?>&edit_message=new_<?php echo time(); ?>" class="button button-primary">+ Add Message</a>
    </div>
    
    <?php 
    $phase_messages = $phases[$active_phase] ?? [];
    
    if (empty($phase_messages)): ?>
        <p style="color: #999;">No messages configured for this phase yet.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 30%;">Name</th>
                    <th style="width: 15%;">Type</th>
                    <th style="width: 35%;">Content Preview</th>
                    <th style="width: 20%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($phase_messages as $msg_id): 
                    if (!isset($messages[$msg_id])) continue;
                    $msg = $messages[$msg_id];
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($msg['name']); ?></strong><br>
                        <code style="font-size: 11px; color: #666;"><?php echo esc_html($msg_id); ?></code>
                    </td>
                    <td>
                        <span style="display: inline-block; padding: 3px 8px; background: #f0f0f1; border-radius: 3px; font-size: 12px;">
                            <?php echo esc_html($msg['type']); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(wp_trim_words($msg['content'], 15)); ?></td>
                    <td>
                        <a href="?page=flosc-settings&tab=ivr-messages&ivr_phase=<?php echo $active_phase; ?>&edit_message=<?php echo urlencode($msg_id); ?>" class="button button-small">Edit</a>
                        <a href="?page=flosc-settings&tab=ivr-messages&ivr_phase=<?php echo $active_phase; ?>&delete_message=<?php echo urlencode($msg_id); ?>&phase=<?php echo $active_phase; ?>&_wpnonce=<?php echo wp_create_nonce('flosc_delete_message_' . $msg_id); ?>" 
                           class="button button-small button-link-delete" 
                           onclick="return confirm('Delete this message?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
