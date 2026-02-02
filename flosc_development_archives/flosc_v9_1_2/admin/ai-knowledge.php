<?php
/**
 * FLOSC AI Knowledge Tab
 *
 * Manages:
 * - AI personality options
 * - Markdown knowledge files stored in ai_knowledge_files/
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$knowledge_dir = trailingslashit(FLOSC_PLUGIN_DIR) . 'ai_knowledge_files/';
if (!is_dir($knowledge_dir)) {
    wp_mkdir_p($knowledge_dir);
}

$notice = null;
$notice_type = 'success';

// ------------------------------
// Helpers
// ------------------------------
$sanitize_relpath = function($filename) {
    $filename = sanitize_file_name($filename);
    // Only allow .md files
    if (!preg_match('/\.md$/i', $filename)) {
        return '';
    }
    return $filename;
};

$resolve_file_path = function($filename) use ($knowledge_dir) {
    $filename = sanitize_file_name($filename);
    $path = $knowledge_dir . $filename;
    $real_dir = realpath($knowledge_dir);
    $real_path = realpath($path);
    if ($real_dir && $real_path && strpos($real_path, $real_dir) === 0) {
        return $real_path;
    }
    return $path;
};

// ------------------------------
// Handle actions
// ------------------------------
$access_levels = get_option('flosc_ai_knowledge_access', []);
if (!is_array($access_levels)) $access_levels = [];

// Toggle access (GET)
if (isset($_GET['toggle_access'])) {
    $file = $sanitize_relpath($_GET['toggle_access']);
    if ($file && check_admin_referer('flosc_toggle_knowledge_access_' . $file)) {
        $current = $access_levels[$file] ?? 'members';
        $access_levels[$file] = ($current === 'public') ? 'members' : 'public';
        update_option('flosc_ai_knowledge_access', $access_levels);
        $notice = 'Access level updated.';
    }
}

// Delete file (GET)
if (isset($_GET['delete'])) {
    $file = $sanitize_relpath($_GET['delete']);
    if ($file && check_admin_referer('flosc_delete_knowledge_file_' . $file)) {
        $path = $resolve_file_path($file);
        if (file_exists($path)) {
            unlink($path);
        }
        unset($access_levels[$file]);
        update_option('flosc_ai_knowledge_access', $access_levels);
        $notice = 'Knowledge file deleted.';
    }
}

// Save AI personality options (POST)
if (!empty($_POST['flosc_ai_personality_save'])) {
    check_admin_referer('flosc_ai_personality_save');

    $fields_text = [
        'flosc_ai_personality_name',
        'flosc_ai_personality_role',
    ];
    $fields_textarea = [
        'flosc_ai_personality_traits',
        'flosc_ai_personality_mission',
        'flosc_ai_personality_context_awareness',
        'flosc_ai_personality_freeline_restrictions',
        'flosc_ai_personality_member_access',
        'flosc_ai_personality_boundaries',
    ];

    foreach ($fields_text as $key) {
        if (isset($_POST[$key])) {
            update_option($key, sanitize_text_field(wp_unslash($_POST[$key])));
        }
    }
    foreach ($fields_textarea as $key) {
        if (isset($_POST[$key])) {
            update_option($key, sanitize_textarea_field(wp_unslash($_POST[$key])));
        }
    }

    $notice = 'AI personality settings saved.';
}

// Upload a knowledge file (POST)
if (!empty($_POST['flosc_ai_knowledge_upload'])) {
    check_admin_referer('flosc_ai_knowledge_upload');

    if (!empty($_FILES['knowledge_file']['name'])) {
        $orig_name = sanitize_file_name($_FILES['knowledge_file']['name']);
        if (!preg_match('/\.md$/i', $orig_name)) {
            $notice = 'Only .md files are allowed.';
            $notice_type = 'error';
        } else {
            $target = $knowledge_dir . $orig_name;
            if (move_uploaded_file($_FILES['knowledge_file']['tmp_name'], $target)) {
                // Default access level
                if (!isset($access_levels[$orig_name])) {
                    $access_levels[$orig_name] = 'members';
                    update_option('flosc_ai_knowledge_access', $access_levels);
                }
                $notice = 'Knowledge file uploaded.';
            } else {
                $notice = 'Upload failed. Please check file permissions on ai_knowledge_files/.';
                $notice_type = 'error';
            }
        }
    } else {
        $notice = 'No file selected.';
        $notice_type = 'error';
    }
}

// Save file content (POST)
if (!empty($_POST['flosc_ai_knowledge_save_file'])) {
    check_admin_referer('flosc_ai_knowledge_save_file');

    $file = $sanitize_relpath($_POST['file_name'] ?? '');
    if (!$file) {
        $notice = 'Invalid file name.';
        $notice_type = 'error';
    } else {
        $path = $resolve_file_path($file);
        if (!file_exists($path)) {
            $notice = 'File not found.';
            $notice_type = 'error';
        } else {
            $content = wp_unslash($_POST['file_content'] ?? '');
            // Keep as plain text; do not store HTML.
            file_put_contents($path, $content);
            $notice = 'Knowledge file saved.';
        }
    }
}

// Refresh access levels after updates
$access_levels = get_option('flosc_ai_knowledge_access', []);
if (!is_array($access_levels)) $access_levels = [];

$files = glob($knowledge_dir . '*.md') ?: [];
$files = array_map('basename', $files);

$editing_file = isset($_GET['edit']) ? $sanitize_relpath($_GET['edit']) : '';
$editing_path = $editing_file ? $resolve_file_path($editing_file) : '';
$editing_content = '';
if ($editing_file && file_exists($editing_path)) {
    $editing_content = file_get_contents($editing_path);
}
?>

<h2>AI Knowledge</h2>
<p>This tab controls the AI personality prompt and the markdown knowledge base loaded by the assistant.</p>

<?php if ($notice): ?>
    <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
<?php endif; ?>

<div class="flosc-info-box">
    <strong>How it works:</strong>
    <ul>
        <li><strong>AI personality</strong> is the behavior + voice prompt your assistant uses.</li>
        <li><strong>Knowledge files</strong> are markdown documents you can update without touching code.</li>
        <li><strong>Access level</strong> lets you keep some knowledge visible only to members.</li>
    </ul>
</div>

<h3>AI Personality Settings</h3>
<form method="post">
    <?php wp_nonce_field('flosc_ai_personality_save'); ?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="flosc_ai_personality_name">Assistant Name</label></th>
            <td><input type="text" id="flosc_ai_personality_name" name="flosc_ai_personality_name" class="regular-text" value="<?php echo esc_attr(get_option('flosc_ai_personality_name', 'Brenda')); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_ai_personality_role">Assistant Role</label></th>
            <td><input type="text" id="flosc_ai_personality_role" name="flosc_ai_personality_role" class="regular-text" value="<?php echo esc_attr(get_option('flosc_ai_personality_role', 'FLOSC Assistant')); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_ai_personality_traits">Traits</label></th>
            <td><textarea id="flosc_ai_personality_traits" name="flosc_ai_personality_traits" rows="4" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_personality_traits', '')); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_ai_personality_mission">Mission</label></th>
            <td><textarea id="flosc_ai_personality_mission" name="flosc_ai_personality_mission" rows="4" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_personality_mission', '')); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_ai_personality_context_awareness">Context Awareness</label></th>
            <td><textarea id="flosc_ai_personality_context_awareness" name="flosc_ai_personality_context_awareness" rows="4" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_personality_context_awareness', '')); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_ai_personality_freeline_restrictions">Freeline Restrictions</label></th>
            <td><textarea id="flosc_ai_personality_freeline_restrictions" name="flosc_ai_personality_freeline_restrictions" rows="4" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_personality_freeline_restrictions', '')); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_ai_personality_member_access">Member Access Rules</label></th>
            <td><textarea id="flosc_ai_personality_member_access" name="flosc_ai_personality_member_access" rows="4" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_personality_member_access', '')); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_ai_personality_boundaries">Boundaries</label></th>
            <td><textarea id="flosc_ai_personality_boundaries" name="flosc_ai_personality_boundaries" rows="4" class="large-text"><?php echo esc_textarea(get_option('flosc_ai_personality_boundaries', '')); ?></textarea></td>
        </tr>
    </table>

    <p>
        <button type="submit" name="flosc_ai_personality_save" value="1" class="button button-primary">Save AI Personality</button>
    </p>
</form>

<hr />

<h3>Knowledge Files</h3>

<form method="post" enctype="multipart/form-data" style="margin-bottom: 15px;">
    <?php wp_nonce_field('flosc_ai_knowledge_upload'); ?>
    <input type="file" name="knowledge_file" accept=".md" />
    <button type="submit" name="flosc_ai_knowledge_upload" value="1" class="button">Upload .md</button>
    <span class="description" style="margin-left: 10px;">Files are stored in <code>ai_knowledge_files/</code></span>
</form>

<?php if (empty($files)): ?>
    <p style="color: #666;">No knowledge files found yet. Upload your first <code>.md</code> file above.</p>
<?php else: ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>File</th>
                <th style="width: 140px;">Access</th>
                <th style="width: 240px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file):
                $access = $access_levels[$file] ?? 'members';
                $toggle_url = wp_nonce_url(add_query_arg(['tab' => 'ai-knowledge', 'toggle_access' => $file], admin_url('admin.php?page=flosc-settings')), 'flosc_toggle_knowledge_access_' . $file);
                $edit_url = add_query_arg(['tab' => 'ai-knowledge', 'edit' => $file], admin_url('admin.php?page=flosc-settings'));
                $delete_url = wp_nonce_url(add_query_arg(['tab' => 'ai-knowledge', 'delete' => $file], admin_url('admin.php?page=flosc-settings')), 'flosc_delete_knowledge_file_' . $file);
            ?>
                <tr>
                    <td><code><?php echo esc_html($file); ?></code></td>
                    <td>
                        <span class="tag" style="display:inline-block;padding:2px 8px;border-radius:12px;background:#f0f0f1;">
                            <?php echo esc_html($access); ?>
                        </span>
                    </td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Edit</a>
                        <a class="button button-small" href="<?php echo esc_url($toggle_url); ?>">Toggle Access</a>
                        <a class="button button-small button-link-delete" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Delete this file?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($editing_file): ?>
    <hr />
    <h3>Edit: <code><?php echo esc_html($editing_file); ?></code></h3>

    <form method="post">
        <?php wp_nonce_field('flosc_ai_knowledge_save_file'); ?>
        <input type="hidden" name="file_name" value="<?php echo esc_attr($editing_file); ?>" />
        <textarea name="file_content" rows="18" class="large-text" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"><?php echo esc_textarea($editing_content); ?></textarea>
        <p>
            <button type="submit" name="flosc_ai_knowledge_save_file" value="1" class="button button-primary">Save File</button>
            <a href="<?php echo esc_url(add_query_arg(['tab' => 'ai-knowledge'], admin_url('admin.php?page=flosc-settings'))); ?>" class="button">Cancel</a>
        </p>
    </form>
<?php endif; ?>
