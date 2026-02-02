<?php
/**
 * IVR Configuration Admin Page
 * Enhanced UI with message list, inline editing, condition builder
 */

if (!defined('ABSPATH')) exit;

$parser = FLOSC_IVR_Parser::flosc_instance();

// Handle actions - sanitize input
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : (isset($_POST['action']) ? sanitize_key($_POST['action']) : '');
$message_saved = false;
$message_deleted = false;
$import_result = '';

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
    
    // Load current ivr.md
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    $ivr_content = file_exists($ivr_file) ? file_get_contents($ivr_file) : '';
    
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
    
    // Save
    $parser->flosc_save_config($ivr_content);
    $message_saved = true;
}

// Handle message delete
if ($action === 'delete_message' && isset($_GET['message'])) {
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_message_' . $_GET['message'])) {
        wp_die('Security check failed');
    }
    
    $message_name = sanitize_text_field($_GET['message']);
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    $ivr_content = file_exists($ivr_file) ? file_get_contents($ivr_file) : '';
    
    // Remove message block
    $ivr_content = preg_replace(
        '/##\s+.*?\nMessageName:\s*' . preg_quote($message_name, '/') . '\n.*?(\n---\n|\Z)/s',
        '',
        $ivr_content
    );
    
    $parser->flosc_save_config($ivr_content);
    $message_deleted = true;
}

// Handle raw markdown save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_raw') {
    if (!wp_verify_nonce($_POST['flosc_ivr_nonce'] ?? '', 'flosc_save_ivr')) {
        wp_die('Security check failed');
    }
    
    $content = wp_unslash($_POST['flosc_ivr_content']);
    $parser->flosc_save_config($content);
    $message_saved = true;
}

// Handle export
if ($action === 'export') {
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    if (file_exists($ivr_file)) {
        header('Content-Type: text/markdown');
        header('Content-Disposition: attachment; filename="ivr-messages-' . date('Y-m-d') . '.md"');
        readfile($ivr_file);
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
            $parser->flosc_save_config($uploaded_content);
            $import_result = 'All messages replaced with imported file.';
        } else {
            // Add to existing
            $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
            $existing_content = file_exists($ivr_file) ? file_get_contents($ivr_file) : '';
            $merged_content = $existing_content . "\n\n" . $uploaded_content;
            $parser->flosc_save_config($merged_content);
            $import_result = 'Messages added from imported file.';
        }
    } else {
        $import_result = 'Error: No file uploaded or upload failed.';
    }
}

// Load current config
$config = $parser->get_flosc_config();
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
?>

<div class="wrap">
    <h1>FLOSC IVR Messages</h1>
    
    <?php if ($message_saved): ?>
        <div class="notice notice-success is-dismissible"><p>Message saved successfully!</p></div>
    <?php endif; ?>
    
    <?php if ($message_deleted): ?>
        <div class="notice notice-success is-dismissible"><p>Message deleted successfully!</p></div>
    <?php endif; ?>
    
    <?php if ($import_result): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($import_result); ?></p></div>
    <?php endif; ?>
    
    <!-- Import/Export -->
    <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">
        <h3 style="margin-top: 0;">Import / Export</h3>
        <p>
            <a href="?page=flosc-ivr&action=export" class="button">Export Messages</a>
        </p>
        <form method="post" enctype="multipart/form-data" style="display: inline-block;">
            <?php wp_nonce_field('flosc_import_ivr', 'flosc_import_nonce'); ?>
            <input type="file" name="ivr_import_file" accept=".md" style="margin-right: 10px;">
            <button type="submit" name="action" value="import_add" class="button">Import & Add</button>
            <button type="submit" name="action" value="import_replace" class="button" 
                    onclick="return confirm('This will replace ALL existing messages. Continue?');">Import & Replace</button>
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
        <select id="filter-phase" onchange="window.location.href='?page=flosc-ivr&filter_phase=' + this.value + '&filter_type=<?php echo esc_attr($filter_type); ?>'">
            <option value="all" <?php selected($filter_phase, 'all'); ?>>All Phases</option>
            <option value="freeline" <?php selected($filter_phase, 'freeline'); ?>>Freeline</option>
            <option value="login" <?php selected($filter_phase, 'login'); ?>>Login</option>
            <option value="offer" <?php selected($filter_phase, 'offer'); ?>>Offer</option>
            <option value="sale" <?php selected($filter_phase, 'sale'); ?>>Sale</option>
            <option value="content" <?php selected($filter_phase, 'content'); ?>>Content</option>
        </select>
        
        <select id="filter-type" onchange="window.location.href='?page=flosc-ivr&filter_phase=<?php echo esc_attr($filter_phase); ?>&filter_type=' + this.value">
            <option value="all" <?php selected($filter_type, 'all'); ?>>All Types</option>
            <option value="auto" <?php selected($filter_type, 'auto'); ?>>Auto</option>
            <option value="suggested_user_autoprompt" <?php selected($filter_type, 'suggested_user_autoprompt'); ?>>Suggested User AutoPrompt</option>
            <option value="offer" <?php selected($filter_type, 'offer'); ?>>Offer</option>
        </select>
        
        <a href="?page=flosc-ivr&edit=new" class="button button-primary" style="margin-left: 20px;">+ Add New Message</a>
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
                <tr><td colspan="6" style="text-align: center; padding: 40px;">No messages found. <a href="?page=flosc-ivr&edit=new">Add your first message</a></td></tr>
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
                            <a href="?page=flosc-ivr&edit=<?php echo urlencode($msg_name); ?>" class="button button-small">Edit</a>
                            <a href="?page=flosc-ivr&action=delete_message&message=<?php echo urlencode($msg_name); ?>&_wpnonce=<?php echo wp_create_nonce('delete_message_' . $msg_name); ?>" 
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
        <p class="description">Advanced: Edit the raw ivr.md file directly.</p>
        
        <form method="post">
            <?php wp_nonce_field('flosc_save_ivr', 'flosc_ivr_nonce'); ?>
            <input type="hidden" name="action" value="save_raw">
            
            <textarea name="flosc_ivr_content" style="width: 100%; height: 500px; font-family: monospace; font-size: 13px;"><?php 
                $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
                echo esc_textarea(file_exists($ivr_file) ? file_get_contents($ivr_file) : '');
            ?></textarea>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Save Raw Markdown</button>
            </p>
        </form>
    </div>
</div>

<style>
.message-row:hover {
    background: #f0f0f1;
}
.message-edit-row {
    display: table-row;
}
</style>
