<?php
/**
 * IVR Configuration Admin Page
 * v1.2.3: Multi-flow IVR file support with Save As feature
 * Enhanced UI with message list, inline editing, condition builder
 */

if (!defined('ABSPATH')) exit;

$parser = FLOSC_IVR_Parser::flosc_instance();

// Get Flow Manager for IVR file listing
require_once FLOSC_PLUGIN_DIR . 'includes/class-flow-manager.php';
$flow_manager = flosc_flows();

// Determine which IVR file we're editing
$available_ivr_files = $flow_manager->get_available_ivr_files();
$current_ivr_file = isset($_GET['ivr_file']) ? sanitize_file_name($_GET['ivr_file']) : 'flosc_default_ivr.md';
$ivr_file_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $current_ivr_file;

// Handle actions - sanitize input
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : (isset($_POST['action']) ? sanitize_key($_POST['action']) : '');
$message_saved = false;
$message_deleted = false;
$import_result = '';
$save_as_result = '';

// Handle Save As (create new IVR file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_as') {
    if (!wp_verify_nonce($_POST['flosc_saveas_nonce'] ?? '', 'flosc_save_as_ivr')) {
        wp_die('Security check failed');
    }
    
    $new_filename = sanitize_file_name($_POST['new_ivr_filename'] ?? '');
    if ($new_filename) {
        // Ensure _ivr.md suffix
        if (!preg_match('/_ivr\.md$/', $new_filename)) {
            $new_filename = preg_replace('/\.md$/', '', $new_filename) . '_ivr.md';
        }
        
        $new_file_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $new_filename;
        
        // Get content from textarea
        $content = wp_unslash($_POST['flosc_ivr_content'] ?? '');
        
        if (file_put_contents($new_file_path, $content) !== false) {
            $save_as_result = 'Saved as ' . $new_filename;
            // Switch to the new file
            $current_ivr_file = $new_filename;
            $ivr_file_path = $new_file_path;
            // Refresh available files list
            $available_ivr_files = $flow_manager->get_available_ivr_files();
        } else {
            $save_as_result = 'Error: Could not save file.';
        }
    }
}

// Handle message save/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_message') {
    if (!wp_verify_nonce($_POST['flosc_ivr_message_nonce'] ?? '', 'flosc_save_ivr_message')) {
        wp_die('Security check failed');
    }
    
    $message_name = sanitize_text_field($_POST['message_name'] ?? '');
    $original_name = sanitize_text_field($_POST['original_name'] ?? '');
    $phase = sanitize_text_field($_POST['phase'] ?? 'freeline');
    $message_type = sanitize_text_field($_POST['message_type'] ?? 'auto');
    $message_content = wp_unslash($_POST['message_content'] ?? '');
    $message_conditions = sanitize_text_field($_POST['message_conditions'] ?? 'always');
    $message_style = sanitize_text_field($_POST['message_style'] ?? 'pill');
    $icon = sanitize_text_field($_POST['icon'] ?? '');
    $user_input = sanitize_text_field($_POST['user_input'] ?? '');
    $action_value = sanitize_text_field($_POST['action_value'] ?? '');
    $title = sanitize_text_field($_POST['title'] ?? $message_name);
    
    // Build message block
    $message_block = "## $title\n";
    $message_block .= "MessageName: $message_name\n";
    $message_block .= "MessageType: $message_type\n";
    if ($message_type === 'suggested_user_autoprompt') {
        $message_block .= "MessageStyle: $message_style\n";
        if ($icon) $message_block .= "Icon: $icon\n";
        if ($user_input) $message_block .= "UserInput: $user_input\n";
    }
    if ($action_value) $message_block .= "Action: $action_value\n";
    $message_block .= "MessageContent: $message_content\n";
    $message_block .= "MessageConditions: $message_conditions\n";
    $message_block .= "\n---\n\n";
    
    // Load current IVR file
    $ivr_content = file_exists($ivr_file_path) ? file_get_contents($ivr_file_path) : '';
    
    // If updating existing message, remove old version
    if ($original_name) {
        $ivr_content = preg_replace(
            '/##\s+.*?\nMessageName:\s*' . preg_quote($original_name, '/') . '\n.*?(\n---\n|\Z)/s',
            '',
            $ivr_content
        );
    }
    
    // Find correct phase section and insert
    $phase_header = "# " . ucfirst($phase) . " Messages";
    if (strpos($ivr_content, $phase_header) !== false) {
        // Insert after phase header
        $ivr_content = preg_replace(
            '/(# ' . ucfirst($phase) . ' Messages\n)/i',
            "$1\n$message_block",
            $ivr_content
        );
    } else {
        // Append at end
        $ivr_content .= "\n$phase_header\n\n$message_block";
    }
    
    // Save to current IVR file
    file_put_contents($ivr_file_path, $ivr_content);
    $message_saved = true;
}

// Handle message delete
if ($action === 'delete_message' && isset($_GET['message'])) {
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_message_' . $_GET['message'])) {
        wp_die('Security check failed');
    }
    
    $message_name = sanitize_text_field($_GET['message']);
    $ivr_content = file_exists($ivr_file_path) ? file_get_contents($ivr_file_path) : '';
    
    // Remove message block
    $ivr_content = preg_replace(
        '/##\s+.*?\nMessageName:\s*' . preg_quote($message_name, '/') . '\n.*?(\n---\n|\Z)/s',
        '',
        $ivr_content
    );
    
    file_put_contents($ivr_file_path, $ivr_content);
    $message_deleted = true;
}

// Handle raw markdown save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_raw') {
    if (!wp_verify_nonce($_POST['flosc_ivr_nonce'] ?? '', 'flosc_save_ivr')) {
        wp_die('Security check failed');
    }
    
    $content = wp_unslash($_POST['flosc_ivr_content']);
    file_put_contents($ivr_file_path, $content);
    $message_saved = true;
}

// Handle export
if ($action === 'export') {
    if (file_exists($ivr_file_path)) {
        header('Content-Type: text/markdown');
        header('Content-Disposition: attachment; filename="' . $current_ivr_file . '"');
        readfile($ivr_file_path);
        exit;
    }
}

// Handle import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'import_add' || $action === 'import_replace')) {
    if (!wp_verify_nonce($_POST['flosc_import_nonce'] ?? '', 'flosc_import_ivr')) {
        wp_die('Security check failed');
    }
    
    if (isset($_FILES['ivr_import_file']) && $_FILES['ivr_import_file']['error'] === 0) {
        $uploaded_content = file_get_contents($_FILES['ivr_import_file']['tmp_name']);
        
        if ($action === 'import_replace') {
            // Replace entirely
            file_put_contents($ivr_file_path, $uploaded_content);
            $import_result = 'All messages replaced with imported file.';
        } else {
            // Add to existing
            $existing_content = file_exists($ivr_file_path) ? file_get_contents($ivr_file_path) : '';
            $merged_content = $existing_content . "\n\n" . $uploaded_content;
            file_put_contents($ivr_file_path, $merged_content);
            $import_result = 'Messages added from imported file.';
        }
    } else {
        $import_result = 'Error: No file uploaded or upload failed.';
    }
}

// Load current config from selected IVR file
$ivr_content_raw = file_exists($ivr_file_path) ? file_get_contents($ivr_file_path) : '';
$config = $parser->flosc_parse($ivr_content_raw);
$messages = $config['messages'] ?? [];
$phases = $config['phases'] ?? [];

// Get filter values
$filter_phase = $_GET['filter_phase'] ?? 'all';
$filter_type = $_GET['filter_type'] ?? 'all';

// Filter messages
$filtered_messages = $messages;
if ($filter_phase !== 'all') {
    $filtered_messages = array_filter($filtered_messages, function($msg) use ($filter_phase) {
        return ($msg['phase'] ?? '') === $filter_phase;
    });
}
if ($filter_type !== 'all') {
    $filtered_messages = array_filter($filtered_messages, function($msg) use ($filter_type) {
        return ($msg['type'] ?? '') === $filter_type;
    });
}

// Check if we're editing a specific message
$editing_message = null;
if (isset($_GET['edit'])) {
    $edit_name = sanitize_text_field($_GET['edit']);
    if ($edit_name !== 'new') {
        $editing_message = $messages[$edit_name] ?? null;
    }
}

// Available variables
$variables = [
    '{name}', '{score}', '{product_name}', '{price}', '{discount_price}', 
    '{timer_remaining}', '{customer_count}', '{lessons_completed}'
];

// Available actions
$actions = [
    'open_quiz', 'open_registration', 'open_free_lesson', 'checkout_oto_main',
    'open_lesson_library', 'resume_last_lesson', 'open_support'
];

// Build query string base for links (preserves ivr_file selection)
$base_query = 'page=flosc-ivr&ivr_file=' . urlencode($current_ivr_file);
?>

<div class="wrap">
    <h1>FLOSC IVR Messages</h1>
    
    <!-- IVR File Selector -->
    <div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #0073aa; border-radius: 4px; border-left-width: 4px;">
        <strong style="font-size: 14px;">📄 Current IVR File:</strong>
        <select id="ivr-file-selector" style="margin-left: 10px; font-size: 14px; padding: 5px 10px;" 
                onchange="window.location.href='?page=flosc-ivr&ivr_file=' + encodeURIComponent(this.value)">
            <?php foreach ($available_ivr_files as $file): ?>
                <option value="<?php echo esc_attr($file); ?>" <?php selected($current_ivr_file, $file); ?>>
                    <?php echo esc_html($file); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span style="color: #666; margin-left: 15px; font-size: 12px;">
            <?php echo esc_html($ivr_file_path); ?>
        </span>
    </div>
    
    <?php if ($message_saved): ?>
        <div class="notice notice-success is-dismissible"><p>Message saved successfully!</p></div>
    <?php endif; ?>
    
    <?php if ($message_deleted): ?>
        <div class="notice notice-success is-dismissible"><p>Message deleted successfully!</p></div>
    <?php endif; ?>
    
    <?php if ($import_result): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($import_result); ?></p></div>
    <?php endif; ?>
    
    <?php if ($save_as_result): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($save_as_result); ?></p></div>
    <?php endif; ?>
    
    <!-- Import/Export -->
    <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">
        <h3 style="margin-top: 0;">Import / Export</h3>
        <p>
            <a href="?<?php echo $base_query; ?>&action=export" class="button">Export Messages</a>
        </p>
        <form method="post" enctype="multipart/form-data" style="display: inline-block;">
            <?php wp_nonce_field('flosc_import_ivr', 'flosc_import_nonce'); ?>
            <input type="file" name="ivr_import_file" accept=".md" style="margin-right: 10px;">
            <button type="submit" name="action" value="import_add" class="button">Import & Add</button>
            <button type="submit" name="action" value="import_replace" class="button" 
                    onclick="return confirm('This will replace ALL messages in <?php echo esc_attr($current_ivr_file); ?>. Continue?');">Import & Replace</button>
        </form>
    </div>
    
    <!-- Stats -->
    <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 20px 0;">
        <strong>Total Messages:</strong> <?php echo count($messages); ?>
        <div style="margin-top: 10px;">
            <strong>By Phase:</strong>
            <?php foreach ($phases as $phase => $phase_messages): ?>
                <span style="background: #fff; padding: 2px 8px; border-radius: 3px; margin-right: 8px;">
                    <?php echo ucfirst($phase); ?>: <?php echo count($phase_messages); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div style="margin: 20px 0; padding: 10px; background: #fff; border: 1px solid #ddd;">
        <strong>Filter:</strong>
        <select id="filter-phase" onchange="window.location.href='?<?php echo $base_query; ?>&filter_phase=' + this.value + '&filter_type=<?php echo esc_attr($filter_type); ?>'">
            <option value="all" <?php selected($filter_phase, 'all'); ?>>All Phases</option>
            <option value="freeline" <?php selected($filter_phase, 'freeline'); ?>>Freeline</option>
            <option value="login" <?php selected($filter_phase, 'login'); ?>>Login</option>
            <option value="offer" <?php selected($filter_phase, 'offer'); ?>>Offer</option>
            <option value="sale" <?php selected($filter_phase, 'sale'); ?>>Sale</option>
            <option value="content" <?php selected($filter_phase, 'content'); ?>>Content</option>
        </select>
        
        <select id="filter-type" onchange="window.location.href='?<?php echo $base_query; ?>&filter_phase=<?php echo esc_attr($filter_phase); ?>&filter_type=' + this.value">
            <option value="all" <?php selected($filter_type, 'all'); ?>>All Types</option>
            <option value="auto" <?php selected($filter_type, 'auto'); ?>>Auto</option>
            <option value="suggested_user_autoprompt" <?php selected($filter_type, 'suggested_user_autoprompt'); ?>>Suggested User AutoPrompt</option>
            <option value="offer" <?php selected($filter_type, 'offer'); ?>>Offer</option>
        </select>
        
        <a href="?<?php echo $base_query; ?>&edit=new" class="button button-primary" style="margin-left: 20px;">+ Add New Message</a>
        <a href="#raw-markdown" class="button" style="margin-left: 10px;">Edit Raw Markdown</a>
    </div>
    
    <!-- Message List -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 10%;">Phase</th>
                <th style="width: 20%;">Name</th>
                <th style="width: 10%;">Type</th>
                <th style="width: 30%;">Content</th>
                <th style="width: 20%;">Conditions</th>
                <th style="width: 10%;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filtered_messages)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 40px;">No messages found. <a href="?<?php echo $base_query; ?>&edit=new">Add your first message</a></td></tr>
            <?php else: ?>
                <?php foreach ($filtered_messages as $msg): ?>
                    <?php 
                    $msg_name = $msg['name'] ?? '';
                    $is_editing = ($editing_message && $editing_message['name'] === $msg_name);
                    ?>
                    <tr class="message-row" data-message="<?php echo esc_attr($msg_name); ?>">
                        <td><?php echo esc_html(ucfirst($msg['phase'] ?? '')); ?></td>
                        <td><strong><?php echo esc_html($msg_name); ?></strong></td>
                        <td><?php echo esc_html($msg['type'] ?? 'auto'); ?></td>
                        <td><?php echo esc_html(substr($msg['content'] ?? '', 0, 60)) . (strlen($msg['content'] ?? '') > 60 ? '...' : ''); ?></td>
                        <td><code style="font-size: 11px;"><?php echo esc_html($msg['conditions'] ?? 'always'); ?></code></td>
                        <td>
                            <a href="?<?php echo $base_query; ?>&edit=<?php echo urlencode($msg_name); ?>" class="button button-small">Edit</a>
                            <a href="?<?php echo $base_query; ?>&action=delete_message&message=<?php echo urlencode($msg_name); ?>&_wpnonce=<?php echo wp_create_nonce('delete_message_' . $msg_name); ?>" 
                               class="button button-small" 
                               onclick="return confirm('Delete this message?');">Delete</a>
                        </td>
                    </tr>
                    
                    <?php if ($is_editing): ?>
                        <tr class="message-edit-row">
                            <td colspan="6" style="background: #f9f9f9; padding: 20px;">
                                <?php include __DIR__ . '/ivr-message-form.php'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Add New Message Form -->
    <?php if (isset($_GET['edit']) && $_GET['edit'] === 'new'): ?>
        <div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
            <h2>Add New Message</h2>
            <?php 
            $editing_message = [
                'name' => '',
                'phase' => 'freeline',
                'type' => 'auto',
                'content' => '',
                'conditions' => 'always',
                'style' => 'pill',
                'icon' => '',
                'user_input' => '',
                'action' => '',
                'title' => ''
            ];
            include __DIR__ . '/ivr-message-form.php';
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Raw Markdown Editor -->
    <div id="raw-markdown" style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd;">
        <h2>Edit Raw Markdown</h2>
        <p class="description">Advanced: Edit the raw <?php echo esc_html($current_ivr_file); ?> file directly.</p>
        
        <form method="post" id="ivr-raw-form">
            <?php wp_nonce_field('flosc_save_ivr', 'flosc_ivr_nonce'); ?>
            <?php wp_nonce_field('flosc_save_as_ivr', 'flosc_saveas_nonce'); ?>
            <input type="hidden" name="action" value="save_raw" id="ivr-form-action">
            
            <textarea name="flosc_ivr_content" id="flosc_ivr_content" style="width: 100%; height: 500px; font-family: monospace; font-size: 13px;"><?php 
                echo esc_textarea($ivr_content_raw);
            ?></textarea>
            
            <p class="submit" style="display: flex; gap: 10px; align-items: center;">
                <button type="submit" class="button button-primary">Save</button>
                <button type="button" class="button" id="save-as-btn">Save As...</button>
                
                <!-- Save As Dialog (hidden by default) -->
                <span id="save-as-dialog" style="display: none; margin-left: 10px;">
                    <input type="text" name="new_ivr_filename" id="new_ivr_filename" 
                           placeholder="new_flow_ivr.md" style="width: 200px;">
                    <button type="submit" class="button button-primary" id="save-as-confirm">Save As New File</button>
                    <button type="button" class="button" id="save-as-cancel">Cancel</button>
                </span>
            </p>
        </form>
    </div>
</div>

<!-- Styles in assets/css/flosc-admin.css -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    var saveAsBtn = document.getElementById('save-as-btn');
    var saveAsDialog = document.getElementById('save-as-dialog');
    var saveAsCancel = document.getElementById('save-as-cancel');
    var saveAsConfirm = document.getElementById('save-as-confirm');
    var formAction = document.getElementById('ivr-form-action');
    var filenameInput = document.getElementById('new_ivr_filename');
    
    saveAsBtn.addEventListener('click', function() {
        saveAsDialog.style.display = 'inline';
        saveAsBtn.style.display = 'none';
        filenameInput.focus();
    });
    
    saveAsCancel.addEventListener('click', function() {
        saveAsDialog.style.display = 'none';
        saveAsBtn.style.display = 'inline';
        filenameInput.value = '';
    });
    
    saveAsConfirm.addEventListener('click', function(e) {
        e.preventDefault();
        var filename = filenameInput.value.trim();
        if (!filename) {
            alert('Please enter a filename.');
            return;
        }
        formAction.value = 'save_as';
        document.getElementById('ivr-raw-form').submit();
    });
});
</script>
