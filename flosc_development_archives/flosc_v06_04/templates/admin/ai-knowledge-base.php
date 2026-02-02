<?php
/**
 * AI Knowledge Files Manager
 * 
 * Upload and manage markdown files for AI knowledge base.
 * 
 * @since 4.0.5
 * @updated 6.0.3 - Security hardening: nonce, capability checks, file validation, uploads dir storage
 */

if (!defined('ABSPATH')) exit;

// v6.0.3: Security - verify user has admin capability
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'flosc'), 403);
}

/**
 * Get the uploads directory for AI knowledge files
 * v6.0.3: Store in uploads dir (survives plugin updates, proper permissions)
 */
function flosc_get_knowledge_dir() {
    $upload_dir = wp_upload_dir();
    $knowledge_dir = trailingslashit($upload_dir['basedir']) . 'flosc-ai/';
    
    // Create directory if it doesn't exist
    if (!file_exists($knowledge_dir)) {
        wp_mkdir_p($knowledge_dir);
        
        // Add .htaccess to prevent direct access to files
        $htaccess = $knowledge_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n");
        }
        
        // Add index.php for extra protection
        $index = $knowledge_dir . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden\n");
        }
    }
    
    return $knowledge_dir;
}

/**
 * Migrate files from plugin dir to uploads dir (one-time)
 */
function flosc_migrate_knowledge_files() {
    $old_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
    $new_dir = flosc_get_knowledge_dir();
    
    if (!is_dir($old_dir)) {
        return;
    }
    
    $migrated = get_option('flosc_knowledge_files_migrated', false);
    if ($migrated) {
        return;
    }
    
    $files = scandir($old_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitkeep' || $file === 'README.md') {
            continue;
        }
        
        if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
            $old_path = $old_dir . $file;
            $new_path = $new_dir . $file;
            
            if (file_exists($old_path) && !file_exists($new_path)) {
                copy($old_path, $new_path);
            }
        }
    }
    
    update_option('flosc_knowledge_files_migrated', true);
}

// Run migration on load
flosc_migrate_knowledge_files();

/**
 * Validate file content
 * v6.0.3: Check for malicious content
 */
function flosc_validate_file_content($content) {
    // Check for PHP code
    if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
        return __('File contains PHP code which is not allowed.', 'flosc');
    }
    
    // Check for script tags
    if (preg_match('/<script[^>]*>/i', $content)) {
        return __('File contains script tags which are not allowed.', 'flosc');
    }
    
    // Check for other potentially dangerous patterns
    if (preg_match('/javascript:/i', $content)) {
        return __('File contains javascript: protocol which is not allowed.', 'flosc');
    }
    
    return true;
}

// Constants
define('FLOSC_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Handle file operations
$message = '';
$error = '';
$knowledge_dir = flosc_get_knowledge_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // v6.0.3: Security - verify nonce for all POST operations
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'flosc_ai_files_nonce')) {
        wp_die(__('Security check failed. Please try again.', 'flosc'), 403);
    }
    
    // Handle file upload
    if (isset($_FILES['configuration_file']) && !empty($_FILES['configuration_file']['name'])) {
        $file = $_FILES['configuration_file'];
        $filename = sanitize_file_name($file['name']);
        
        // v6.0.3: Validate file size
        if ($file['size'] > FLOSC_MAX_FILE_SIZE) {
            $error = sprintf(__('File too large. Maximum size is %s.', 'flosc'), size_format(FLOSC_MAX_FILE_SIZE));
        }
        // Check for upload errors
        elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = __('File upload failed. Please try again.', 'flosc');
        }
        // Only allow .md files
        elseif (pathinfo($filename, PATHINFO_EXTENSION) !== 'md') {
            $error = __('Only .md files are allowed.', 'flosc');
        } 
        else {
            // v6.0.3: Validate file content
            $content = file_get_contents($file['tmp_name']);
            $validation = flosc_validate_file_content($content);
            
            if ($validation !== true) {
                $error = $validation;
            } else {
                $upload_path = $knowledge_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $message = sprintf(__('File uploaded successfully: %s', 'flosc'), $filename);
                    FLOSC_Logger::info('AI knowledge file uploaded', ['filename' => $filename]);
                } else {
                    $error = __('Failed to upload file. Check directory permissions.', 'flosc');
                    FLOSC_Logger::error('AI knowledge file upload failed', ['filename' => $filename]);
                }
            }
        }
    }

    // Handle new file creation
    if (isset($_POST['create_file']) && !empty($_POST['new_filename']) && !empty($_POST['new_file_content'])) {
        $filename = sanitize_file_name($_POST['new_filename']);

        // Add .md extension if not present
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'md') {
            $filename .= '.md';
        }

        $filepath = $knowledge_dir . $filename;
        $content = wp_unslash($_POST['new_file_content']);
        
        // v6.0.3: Validate content size
        if (strlen($content) > FLOSC_MAX_FILE_SIZE) {
            $error = sprintf(__('Content too large. Maximum size is %s.', 'flosc'), size_format(FLOSC_MAX_FILE_SIZE));
        }
        // v6.0.3: Validate content
        elseif (($validation = flosc_validate_file_content($content)) !== true) {
            $error = $validation;
        }
        elseif (file_exists($filepath)) {
            $error = sprintf(__('File already exists: %s', 'flosc'), $filename);
        } 
        else {
            if (file_put_contents($filepath, $content)) {
                $message = sprintf(__('File created successfully: %s', 'flosc'), $filename);
                FLOSC_Logger::info('AI knowledge file created', ['filename' => $filename]);
            } else {
                $error = __('Failed to create file. Check directory permissions.', 'flosc');
            }
        }
    }

    // Handle file editing
    if (isset($_POST['save_file']) && !empty($_POST['edit_filename'])) {
        $filename = sanitize_file_name($_POST['edit_filename']);
        $filepath = $knowledge_dir . $filename;
        $content = wp_unslash($_POST['file_content']);

        // v6.0.3: Validate content size
        if (strlen($content) > FLOSC_MAX_FILE_SIZE) {
            $error = sprintf(__('Content too large. Maximum size is %s.', 'flosc'), size_format(FLOSC_MAX_FILE_SIZE));
        }
        // v6.0.3: Validate content
        elseif (($validation = flosc_validate_file_content($content)) !== true) {
            $error = $validation;
        }
        elseif (!file_exists($filepath)) {
            $error = sprintf(__('File not found: %s', 'flosc'), $filename);
        } 
        else {
            if (file_put_contents($filepath, $content)) {
                $message = sprintf(__('File saved successfully: %s', 'flosc'), $filename);
                FLOSC_Logger::info('AI knowledge file updated', ['filename' => $filename]);
            } else {
                $error = __('Failed to save file. Check directory permissions.', 'flosc');
            }
        }
    }

    // Handle file deletion
    if (isset($_POST['delete_file']) && !empty($_POST['delete_filename'])) {
        $filename = sanitize_file_name($_POST['delete_filename']);
        $filepath = $knowledge_dir . $filename;

        // v6.0.3: Extra validation - ensure file is in the right directory
        $real_path = realpath($filepath);
        $real_dir = realpath($knowledge_dir);
        
        if ($real_path && $real_dir && strpos($real_path, $real_dir) === 0) {
            if (unlink($filepath)) {
                $message = sprintf(__('File deleted successfully: %s', 'flosc'), $filename);
                FLOSC_Logger::info('AI knowledge file deleted', ['filename' => $filename]);
            } else {
                $error = __('Failed to delete file.', 'flosc');
            }
        } else {
            $error = __('Invalid file path.', 'flosc');
            FLOSC_Logger::security('Attempted directory traversal in AI knowledge delete', ['filename' => $filename]);
        }
    }
}

// Get all existing files
$files = [];
if (is_dir($knowledge_dir)) {
    $scan = scandir($knowledge_dir);
    foreach ($scan as $file) {
        if ($file !== '.' && $file !== '..' && $file !== '.htaccess' && $file !== 'index.php') {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
                $files[] = $file;
            }
        }
    }
    sort($files);
}

// Handle edit request (GET - no nonce needed for read-only)
$editing_file = '';
$editing_content = '';
if (isset($_GET['edit'])) {
    $editing_file = sanitize_file_name($_GET['edit']);
    $filepath = $knowledge_dir . $editing_file;
    
    // Validate path
    $real_path = realpath($filepath);
    $real_dir = realpath($knowledge_dir);
    
    if ($real_path && $real_dir && strpos($real_path, $real_dir) === 0 && file_exists($filepath)) {
        $editing_content = file_get_contents($filepath);
    } else {
        $error = __('File not found or invalid path.', 'flosc');
        $editing_file = '';
    }
}
?>

<div class="wrap">
    <h1><?php _e('AI Knowledge Files', 'flosc'); ?></h1>
    <p class="description"><?php _e('Upload or create markdown files that provide knowledge, catalogs, and instructions for your AI assistant.', 'flosc'); ?></p>
    
    <p class="description" style="margin-top: 5px;">
        <strong><?php _e('Storage location:', 'flosc'); ?></strong> 
        <code><?php echo esc_html($knowledge_dir); ?></code>
    </p>

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <!-- Upload File -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php _e('Upload Markdown File', 'flosc'); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('flosc_ai_files_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Choose .md File', 'flosc'); ?></th>
                    <td>
                        <input type="file" name="configuration_file" accept=".md" required>
                        <p class="description">
                            <?php printf(
                                __('Upload a .md file (max %s) containing lesson catalogs, knowledge base content, or AI instructions.', 'flosc'),
                                size_format(FLOSC_MAX_FILE_SIZE)
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Upload File', 'flosc')); ?>
        </form>
    </div>

    <!-- Create New File -->
    <?php if (!$editing_file): ?>
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php _e('Create New File', 'flosc'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('flosc_ai_files_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Filename', 'flosc'); ?></th>
                    <td>
                        <input type="text" name="new_filename" class="regular-text" placeholder="lesson-catalog.md" required pattern="[a-zA-Z0-9_\-\.]+">
                        <p class="description"><?php _e('Enter filename (will auto-add .md extension if needed)', 'flosc'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Content', 'flosc'); ?></th>
                    <td>
                        <textarea name="new_file_content" rows="15" class="large-text code" style="font-family: monospace; width: 100%;" required></textarea>
                        <p class="description"><?php _e('Write your markdown content here', 'flosc'); ?></p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="create_file" value="1">
            <?php submit_button(__('Create File', 'flosc')); ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- Edit File -->
    <?php if ($editing_file): ?>
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php printf(__('Edit File: %s', 'flosc'), esc_html($editing_file)); ?></h2>
        <form method="post">
            <?php wp_nonce_field('flosc_ai_files_nonce'); ?>
            <textarea name="file_content" rows="20" class="large-text code" style="font-family: monospace; width: 100%;" required><?php echo esc_textarea($editing_content); ?></textarea>
            <input type="hidden" name="edit_filename" value="<?php echo esc_attr($editing_file); ?>">
            <p style="margin-top: 10px;">
                <input type="submit" name="save_file" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'flosc'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-ai-configuration')); ?>" class="button"><?php _e('Cancel', 'flosc'); ?></a>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <!-- Existing Files -->
    <div class="card" style="max-width: 800px;">
        <h2><?php _e('Existing Files', 'flosc'); ?></h2>

        <?php if (empty($files)): ?>
            <p><?php _e('No knowledge files yet. Upload or create your first file above.', 'flosc'); ?></p>
        <?php else: ?>
            <table class="widefat" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th><?php _e('Filename', 'flosc'); ?></th>
                        <th><?php _e('Size', 'flosc'); ?></th>
                        <th><?php _e('Modified', 'flosc'); ?></th>
                        <th><?php _e('Actions', 'flosc'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <?php
                        $filepath = $knowledge_dir . $file;
                        $size = filesize($filepath);
                        $modified = filemtime($filepath);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($file); ?></strong></td>
                            <td><?php echo size_format($size); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', $modified)); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-ai-configuration&edit=' . urlencode($file))); ?>" class="button button-small"><?php _e('Edit', 'flosc'); ?></a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e('Delete this file?', 'flosc'); ?>');">
                                    <?php wp_nonce_field('flosc_ai_files_nonce'); ?>
                                    <input type="hidden" name="delete_filename" value="<?php echo esc_attr($file); ?>">
                                    <input type="submit" name="delete_file" class="button button-small" value="<?php esc_attr_e('Delete', 'flosc'); ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Usage Guide -->
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2><?php _e('How AI Uses These Files', 'flosc'); ?></h2>

        <p><strong><?php _e('These files are automatically loaded by the AI assistant based on the user\'s FLOSC phase and context.', 'flosc'); ?></strong></p>

        <h3><?php _e('Suggested File Types', 'flosc'); ?></h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong><?php _e('Lesson Catalogs:', 'flosc'); ?></strong> <?php _e('Lists of your lessons with URLs, difficulty, duration, etc.', 'flosc'); ?></li>
            <li><strong><?php _e('Knowledge Base:', 'flosc'); ?></strong> <?php _e('Teaching methodologies, common mistakes, how-to guides', 'flosc'); ?></li>
            <li><strong><?php _e('Content TOCs:', 'flosc'); ?></strong> <?php _e('Table of contents for free vs paid content', 'flosc'); ?></li>
            <li><strong><?php _e('Capabilities:', 'flosc'); ?></strong> <?php _e('What the AI can/cannot do in each phase', 'flosc'); ?></li>
            <li><strong><?php _e('Troubleshooting:', 'flosc'); ?></strong> <?php _e('Common issues and solutions', 'flosc'); ?></li>
        </ul>

        <h3><?php _e('Security Notes', 'flosc'); ?></h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><?php _e('Only .md (Markdown) files are allowed', 'flosc'); ?></li>
            <li><?php printf(__('Maximum file size: %s', 'flosc'), size_format(FLOSC_MAX_FILE_SIZE)); ?></li>
            <li><?php _e('PHP code and script tags are automatically rejected', 'flosc'); ?></li>
            <li><?php _e('Files are stored in your WordPress uploads directory', 'flosc'); ?></li>
        </ul>
    </div>
</div>

<style>
.card {
    background: white;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
}

.card h2 {
    margin-top: 0;
}

.card h3 {
    margin-top: 20px;
    margin-bottom: 10px;
}
</style>
