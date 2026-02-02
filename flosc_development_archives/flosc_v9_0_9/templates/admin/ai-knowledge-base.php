<?php
/**
 * AI Knowledge Files Manager
 * v04_05: Upload and manage markdown files for AI knowledge base
 */

if (!defined('ABSPATH')) exit;

// Handle file operations
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file upload
    if (isset($_FILES['orientation_file']) && !empty($_FILES['orientation_file']['name'])) {
        $file = $_FILES['orientation_file'];
        $filename = sanitize_file_name($file['name']);

        // Only allow .md files
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'md') {
            $error = 'Only .md files are allowed';
        } else {
            $upload_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $message = 'File uploaded successfully: ' . $filename;
            } else {
                $error = 'Failed to upload file';
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

        $filepath = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;

        if (file_exists($filepath)) {
            $error = 'File already exists: ' . $filename;
        } else {
            $content = wp_unslash($_POST['new_file_content']);
            if (file_put_contents($filepath, $content)) {
                $message = 'File created successfully: ' . $filename;
            } else {
                $error = 'Failed to create file';
            }
        }
    }

    // Handle file editing
    if (isset($_POST['save_file']) && !empty($_POST['edit_filename'])) {
        $filename = sanitize_file_name($_POST['edit_filename']);
        $filepath = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;

        if (!file_exists($filepath)) {
            $error = 'File not found: ' . $filename;
        } else {
            $content = wp_unslash($_POST['file_content']);
            if (file_put_contents($filepath, $content)) {
                $message = 'File saved successfully: ' . $filename;
            } else {
                $error = 'Failed to save file';
            }
        }
    }

    // Handle file deletion
    if (isset($_POST['delete_file']) && !empty($_POST['delete_filename'])) {
        $filename = sanitize_file_name($_POST['delete_filename']);
        $filepath = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;

        if (file_exists($filepath) && unlink($filepath)) {
            $message = 'File deleted successfully: ' . $filename;
        } else {
            $error = 'Failed to delete file';
        }
    }
}

// Get all existing files
$orientation_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
$files = [];
if (is_dir($orientation_dir)) {
    $scan = scandir($orientation_dir);
    foreach ($scan as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'md') {
            $files[] = $file;
        }
    }
}

// Handle edit request
$editing_file = '';
$editing_content = '';
if (isset($_GET['edit'])) {
    $editing_file = sanitize_file_name($_GET['edit']);
    $filepath = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $editing_file;
    if (file_exists($filepath)) {
        $editing_content = file_get_contents($filepath);
    }
}
?>

<div class="wrap">
    <h1>AI Knowledge Files</h1>
    <p class="description">Upload or create markdown files that provide knowledge, catalogs, and instructions for your AI assistant.</p>

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <!-- Upload File -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2>Upload Markdown File</h2>
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row">Choose .md File</th>
                    <td>
                        <input type="file" name="orientation_file" accept=".md" required>
                        <p class="description">Upload a .md file containing lesson catalogs, knowledge base content, or AI instructions.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Upload File'); ?>
        </form>
    </div>

    <!-- Create New File -->
    <?php if (!$editing_file): ?>
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2>Create New File</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Filename</th>
                    <td>
                        <input type="text" name="new_filename" class="regular-text" placeholder="lesson-catalog.md" required>
                        <p class="description">Enter filename (will auto-add .md extension if needed)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Content</th>
                    <td>
                        <textarea name="new_file_content" rows="15" class="large-text code" style="font-family: monospace; width: 100%;" required></textarea>
                        <p class="description">Write your markdown content here</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="create_file" value="1">
            <?php submit_button('Create File'); ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- Edit File -->
    <?php if ($editing_file): ?>
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2>Edit File: <?php echo esc_html($editing_file); ?></h2>
        <form method="post">
            <textarea name="file_content" rows="20" class="large-text code" style="font-family: monospace; width: 100%;" required><?php echo esc_textarea($editing_content); ?></textarea>
            <input type="hidden" name="edit_filename" value="<?php echo esc_attr($editing_file); ?>">
            <p style="margin-top: 10px;">
                <input type="submit" name="save_file" class="button button-primary" value="Save Changes">
                <a href="<?php echo admin_url('admin.php?page=flosc-ai-knowledge'); ?>" class="button">Cancel</a>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <!-- Existing Files -->
    <div class="card" style="max-width: 800px;">
        <h2>Existing Files</h2>

        <?php if (empty($files)): ?>
            <p>No knowledge files yet. Upload or create your first file above.</p>
        <?php else: ?>
            <table class="widefat" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <?php
                        $filepath = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $file;
                        $size = filesize($filepath);
                        $modified = filemtime($filepath);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($file); ?></strong></td>
                            <td><?php echo size_format($size); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $modified); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=flosc-ai-knowledge&edit=' . urlencode($file)); ?>" class="button button-small">Edit</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this file?');">
                                    <input type="hidden" name="delete_filename" value="<?php echo esc_attr($file); ?>">
                                    <input type="submit" name="delete_file" class="button button-small" value="Delete">
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
        <h2>How AI Uses These Files</h2>

        <p><strong>These files are automatically loaded by the AI assistant based on the user's FLOSC phase and context.</strong></p>

        <h3>Suggested File Types</h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong>Lesson Catalogs:</strong> Lists of your lessons with URLs, difficulty, duration, etc.</li>
            <li><strong>Knowledge Base:</strong> Teaching methodologies, common mistakes, how-to guides</li>
            <li><strong>Content TOCs:</strong> Table of contents for free vs paid content</li>
            <li><strong>Capabilities:</strong> What the AI can/cannot do in each phase</li>
            <li><strong>Troubleshooting:</strong> Common issues and solutions</li>
        </ul>

        <h3>File Organization Tips</h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li>Use descriptive filenames: <code>lesson-catalog-pronunciation.md</code></li>
            <li>Organize by topic or phase if you have many files</li>
            <li>Use markdown headers (# ## ###) for structure</li>
            <li>The AI will have access to ALL files - organize for your own clarity</li>
        </ul>

        <h3>Loading Control</h3>
        <p>Configure which files to load in each FLOSC phase at <strong>FLOSC > AI Config</strong> page.</p>
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
