<?php
/**
 * FLOSC IVR Messages Tab - Full CRUD Editor (v9.2.2)
 * 
 * Database-first approach with import/export functionality
 */

if (!defined('ABSPATH')) exit;

// Handle import with preview/confirmation
if (isset($_POST['flosc_confirm_import'])) {
    check_admin_referer('flosc_confirm_import');
    
    $import_mode = sanitize_text_field($_POST['import_mode'] ?? 'merge');
    $result = flosc_import_ivr_to_database($import_mode);
    
    if ($result['success']) {
        add_settings_error('flosc_settings', 'ivr_imported', $result['message'], 'success');
    } else {
        add_settings_error('flosc_settings', 'ivr_import_failed', $result['message'], 'error');
    }
}

// Generate import preview
$import_preview = null;
if (isset($_POST['flosc_preview_import'])) {
    check_admin_referer('flosc_preview_import');
    
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    if (file_exists($ivr_file)) {
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
        $parser = FLOSC_IVR_Parser::flosc_instance();
        $markdown = file_get_contents($ivr_file);
        $config = $parser->flosc_parse($markdown);
        
        $current_messages = get_option('flosc_ivr_messages', []);
        $incoming_messages = $config['messages'] ?? [];
        
        $import_preview = [
            'added' => [],
            'updated' => [],
            'preserved' => [],
            'deleted' => []
        ];
        
        // Calculate what would happen
        foreach ($incoming_messages as $msg_id => $msg_data) {
            if (isset($current_messages[$msg_id])) {
                $import_preview['updated'][] = $msg_id;
            } else {
                $import_preview['added'][] = $msg_id;
            }
        }
        
        foreach ($current_messages as $msg_id => $msg_data) {
            if (!isset($incoming_messages[$msg_id])) {
                $import_preview['preserved'][] = $msg_id; // In merge mode
                $import_preview['deleted'][] = $msg_id;   // In replace mode
            }
        }
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
    
    add_settings_error('flosc_settings', 'ivr_exported', 'IVR messages exported successfully to ivr.md!', 'success');
}

// Handle message save/delete
if (isset($_POST['save_ivr_message'])) {
    check_admin_referer('flosc_save_ivr_message');
    
    $messages = get_option('flosc_ivr_messages', []);
    $phases = get_option('flosc_ivr_phases', []);
    
    $msg_id = sanitize_text_field($_POST['message_id']);
    $phase = sanitize_text_field($_POST['message_phase']);
    
    $message_data = [
        'name' => sanitize_text_field($_POST['message_name']),
        'type' => sanitize_text_field($_POST['message_type']),
        'phase' => $phase,
        'content' => wp_kses_post($_POST['message_content']),
        'conditions' => sanitize_text_field($_POST['message_conditions'] ?? ''),
        'style' => sanitize_text_field($_POST['message_style'] ?? 'default'),
        'icon' => sanitize_text_field($_POST['message_icon'] ?? ''),
        'user_input' => sanitize_text_field($_POST['message_user_input'] ?? ''),
        'action' => sanitize_text_field($_POST['message_action'] ?? ''),
    ];
    
    // Save message
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
    
    add_settings_error('flosc_settings', 'message_saved', 'Message saved successfully!', 'success');
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
    
    add_settings_error('flosc_settings', 'message_deleted', 'Message deleted successfully!', 'success');
}

$messages = get_option('flosc_ivr_messages', []);
$phases = get_option('flosc_ivr_phases', []);
$active_phase = $_GET['ivr_phase'] ?? 'freeline';
$editing_message = $_GET['edit_message'] ?? null;

?>

</form>

<h2>IVR Messages Configuration</h2>

<div class="flosc-info-box" style="margin-bottom: 20px;">
    <strong>💾 Database-First IVR System (v9.2.2)</strong>
    <p>Messages are stored in WordPress database for fast API access. Use Import/Export for version control.</p>
    <ul style="margin: 10px 0 0 20px;">
        <li><strong>Freeline:</strong> Visitor (not logged in) - Encourage quiz</li>
        <li><strong>Login:</strong> Post-quiz + Logged-in - Deliver free lesson, present offer</li>
        <li><strong>Offer:</strong> Sales pitch</li>
        <li><strong>Sale:</strong> Post-purchase onboarding</li>
        <li><strong>Content:</strong> Ongoing member support</li>
    </ul>
</div>

<div style="margin-bottom: 20px;">
    <?php if ($import_preview === null): ?>
        <!-- Step 1: Preview Import Button -->
        <form method="post" style="display: inline-block; margin-right: 10px;">
            <?php wp_nonce_field('flosc_preview_import'); ?>
            <button type="submit" name="flosc_preview_import" class="button button-secondary">
                🔍 Preview Import from ivr.md
            </button>
        </form>
    <?php else: ?>
        <!-- Step 2: Show Preview and Confirmation -->
        <div class="flosc-import-preview" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 15px;">
            <h3 style="margin-top: 0;">Import Preview</h3>
            
            <div style="margin-bottom: 15px;">
                <strong>MERGE MODE (Safe, Recommended):</strong>
                <ul style="margin: 5px 0 0 20px;">
                    <li>✅ Add: <?php echo count($import_preview['added']); ?> new messages</li>
                    <li>🔄 Update: <?php echo count($import_preview['updated']); ?> existing messages</li>
                    <li>💾 Preserve: <?php echo count($import_preview['preserved']); ?> database-only messages</li>
                    <li>🗑️ Delete: 0 messages</li>
                </ul>
                <?php if (!empty($import_preview['preserved'])): ?>
                    <details style="margin-left: 20px; font-size: 12px; color: #666;">
                        <summary>Database-only messages that will be preserved</summary>
                        <code><?php echo implode(', ', $import_preview['preserved']); ?></code>
                    </details>
                <?php endif; ?>
            </div>
            
            <div style="background: #fff3cd; padding: 10px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                <strong>REPLACE MODE (Destructive):</strong>
                <ul style="margin: 5px 0 0 20px;">
                    <li>✅ Add: <?php echo count($import_preview['added']); ?> new messages</li>
                    <li>🔄 Update: <?php echo count($import_preview['updated']); ?> existing messages</li>
                    <li>💾 Preserve: 0 messages</li>
                    <li>⚠️ Delete: <?php echo count($import_preview['deleted']); ?> database-only messages</li>
                </ul>
                <?php if (!empty($import_preview['deleted'])): ?>
                    <details style="margin-left: 20px; font-size: 12px; color: #856404;">
                        <summary>Messages that will be DELETED in replace mode</summary>
                        <code><?php echo implode(', ', $import_preview['deleted']); ?></code>
                    </details>
                <?php endif; ?>
            </div>
            
            <div style="background: #d4edda; padding: 10px; margin-bottom: 15px; border-left: 4px solid #28a745;">
                <strong>🔒 Auto-Backup:</strong> Current database state will be backed up to 
                <code>ivr-backup-TIMESTAMP.md</code> before import.
            </div>
            
            <form method="post" style="margin-top: 15px;">
                <?php wp_nonce_field('flosc_confirm_import'); ?>
                <input type="radio" name="import_mode" value="merge" id="mode_merge" checked>
                <label for="mode_merge"><strong>Smart Merge</strong> (recommended)</label>
                <br>
                <input type="radio" name="import_mode" value="replace" id="mode_replace">
                <label for="mode_replace"><strong>Full Replace</strong> (deletes database-only messages)</label>
                <br><br>
                <button type="submit" name="flosc_confirm_import" class="button button-primary">
                    ✅ Confirm Import
                </button>
                <a href="<?php echo admin_url('admin.php?page=flosc-ivr-messages'); ?>" class="button button-secondary">
                    ❌ Cancel
                </a>
            </form>
        </div>
    <?php endif; ?>
    
    <form method="post" style="display: inline-block;">
        <?php wp_nonce_field('flosc_export_ivr'); ?>
        <button type="submit" name="flosc_export_ivr" class="button button-secondary">
            📤 Export to ivr.md
        </button>
    </form>
    
    <span style="color: #666; font-size: 12px; margin-left: 10px;">
        Last import: <?php echo get_option('flosc_ivr_last_import', 'Never'); ?>
        <?php 
        $last_mode = get_option('flosc_ivr_last_import_mode', 'N/A');
        $last_stats = get_option('flosc_ivr_last_import_stats', []);
        if (!empty($last_stats)): ?>
            (<?php echo strtoupper($last_mode); ?>: +<?php echo count($last_stats['added']); ?> 
            ~<?php echo count($last_stats['updated']); ?> 
            ✓<?php echo count($last_stats['preserved']); ?> 
            -<?php echo count($last_stats['deleted']); ?>)
        <?php endif; ?>
    </span>
</div>

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
                <button type="submit" name="save_ivr_message" class="button button-primary">Save Message</button>
                <a href="?page=flosc-settings&tab=ivr-messages&ivr_phase=<?php echo esc_attr($active_phase); ?>" class="button">Cancel</a>
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
