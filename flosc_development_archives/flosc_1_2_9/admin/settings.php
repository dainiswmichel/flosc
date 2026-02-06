<?php
/**
 * FLOSC Admin Settings Page v1.2.9
 * 
 * Simple: IVR file = Flow. Pick file, edit all tabs, save.
 * 
 * v1.2.9: Added flosc_tab_header() helper, permalink status, Michel timestamp
 */

if (!defined('ABSPATH')) exit;

/**
 * Tab Header Helper - displays consistent header across all tabs
 * Format: {emoji} {Tab Name} Configuration for {FlowName} ({filename})
 * 
 * @param string $emoji Tab emoji
 * @param string $tab_name Tab display name
 * @return void
 */
if (!function_exists('flosc_tab_header')) {
    function flosc_tab_header($emoji, $tab_name) {
        $ivr_file = $GLOBALS['flosc_current_ivr'] ?? '';
        $settings = $GLOBALS['flosc_current_settings'] ?? [];
        $flow_name = $settings['name'] ?? ucwords(str_replace(['_', '-', '.md'], [' ', ' ', ''], $ivr_file));
        
        echo '<div class="flosc-tab-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h2 style="margin: 0; color: white; font-size: 18px;">';
        echo esc_html($emoji . ' ' . $tab_name . ' Configuration');
        echo '</h2>';
        echo '<p style="margin: 5px 0 0; color: rgba(255,255,255,0.9); font-size: 13px;">';
        echo 'Flow: <strong>' . esc_html($flow_name) . '</strong> ';
        echo '<code style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; color: white;">(' . esc_html($ivr_file) . ')</code>';
        echo '</p>';
        echo '</div>';
    }
}

/**
 * Generate Michel Timestamp - overthetop silly specific format
 * Format: 2026y-02m-05d-T10h:43m:22s
 * 
 * @return string
 */
if (!function_exists('flosc_michel_timestamp')) {
    function flosc_michel_timestamp() {
        return date('Y') . 'y-' . date('m') . 'm-' . date('d') . 'd-T' . date('H') . 'h:' . date('i') . 'm:' . date('s') . 's';
    }
}

/**
 * Check if a flow's slug is registered in rewrite rules
 * 
 * @param string $slug The slug to check
 * @return string 'ok', 'missing', or 'unknown'
 */
if (!function_exists('flosc_check_permalink_status')) {
    function flosc_check_permalink_status($slug) {
        if (empty($slug)) {
            return 'unknown';
        }
        
        $rules = get_option('rewrite_rules', []);
        if (empty($rules)) {
            return 'unknown';
        }
        
        $pattern = '^' . preg_quote($slug, '/') . '/?$';
        foreach ($rules as $regex => $query) {
            if (strpos($regex, $slug) !== false) {
                return 'ok';
            }
        }
        
        return 'missing';
    }
}

/**
 * Render permalink status indicator
 * Green = OK, Yellow = Unknown, Red = Needs Flush
 * 
 * @param string $slug The flow slug
 * @return void
 */
if (!function_exists('flosc_permalink_status_indicator')) {
    function flosc_permalink_status_indicator($slug) {
        $status = flosc_check_permalink_status($slug);
        $last_flush = get_option('flosc_last_permalink_flush', null);
        
        $colors = [
            'ok' => ['bg' => '#10b981', 'text' => '✓ Permalinks OK'],
            'missing' => ['bg' => '#ef4444', 'text' => '⚠ Needs Flush'],
            'unknown' => ['bg' => '#f59e0b', 'text' => '? Status Unknown'],
        ];
        
        $color = $colors[$status];
        
        echo '<div class="flosc-permalink-status" style="display: inline-flex; align-items: center; gap: 10px; margin-left: 15px;">';
        echo '<span style="background: ' . $color['bg'] . '; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">';
        echo esc_html($color['text']);
        echo '</span>';
        
        if ($last_flush) {
            echo '<span style="color: #6b7280; font-size: 11px;">Last flush: ' . esc_html($last_flush) . '</span>';
        }
        
        // Flush button
        $flush_url = wp_nonce_url(admin_url('admin-post.php?action=flosc_flush_permalinks_v129'), 'flosc_flush_v129');
        echo '<a href="' . esc_url($flush_url) . '" class="button button-small" style="font-size: 11px;">Flush Now</a>';
        echo '</div>';
    }
}

// Get available IVR files
$ivr_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
$ivr_files = [];
if (is_dir($ivr_dir)) {
    $files = array_merge(
        glob($ivr_dir . '*_ivr.md'),
        glob($ivr_dir . 'ivr*.md')
    );
    $files = array_unique($files);
    sort($files);
    foreach ($files as $file) {
        $filename = basename($file);
        if (strpos($filename, 'backup') === false) {
            $ivr_files[] = $filename;
        }
    }
}

// Selected IVR file (flow)
$selected_ivr = isset($_GET['ivr']) ? sanitize_file_name($_GET['ivr']) : '';
if (empty($selected_ivr) && !empty($ivr_files)) {
    $selected_ivr = $ivr_files[0];
}

// Settings key for this flow
$settings_key = 'flosc_flow_' . sanitize_key(pathinfo($selected_ivr, PATHINFO_FILENAME));

// Load settings for this flow
$flow_settings = get_option($settings_key, []);

// Set defaults if empty
if (empty($flow_settings)) {
    $flow_settings = [
        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $selected_ivr)),
        'tagline' => '',
        'emoji' => '🎯',
        'slug' => sanitize_title(pathinfo($selected_ivr, PATHINFO_FILENAME)),
        'primary_color' => '#4f46e5',
        'status' => 'active',
    ];
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'identity';

// Handle save
if (isset($_POST['flosc_save']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_settings')) {
    
    // Collect all POST data for this flow
    $new_settings = $flow_settings; // Start with existing
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'flow_') === 0) {
            $setting_key = substr($key, 5); // Remove 'flow_' prefix
            $new_settings[$setting_key] = is_array($value) 
                ? array_map('sanitize_text_field', $value)
                : sanitize_text_field($value);
        }
    }
    
    // Save
    update_option($settings_key, $new_settings);
    $flow_settings = $new_settings;
    
    $saved = true;
}

// Build flow URL
$flow_url = home_url('/' . ($flow_settings['slug'] ?? 'flosc') . '/');

// Set global context for tab includes
$GLOBALS['flosc_current_ivr'] = $selected_ivr;
$GLOBALS['flosc_current_settings'] = $flow_settings;
$GLOBALS['flosc_settings_key'] = $settings_key;

?>
<div class="wrap flosc-admin">
    <h1>FLOSC Settings</h1>
    
    <?php if (isset($saved)): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Settings saved for <strong><?php echo esc_html($flow_settings['name'] ?? $selected_ivr); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <!-- IVR File Selector = Flow Selector -->
    <div class="flosc-ivr-selector" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <label style="color: white; font-weight: 600; font-size: 14px;">Flow:</label>
            <select id="ivr-select" onchange="switchIVR(this.value);" style="padding: 8px 12px; border-radius: 6px; border: none; min-width: 250px; font-size: 14px;">
                <?php foreach ($ivr_files as $file): ?>
                    <option value="<?php echo esc_attr($file); ?>" <?php selected($selected_ivr, $file); ?>>
                        <?php echo esc_html($file); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($flow_settings['status'] === 'active' && !empty($flow_settings['slug'])): ?>
                <a href="<?php echo esc_url($flow_url); ?>" target="_blank" style="background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px;">
                    View App ↗
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Permalink Status Row -->
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.2);">
            <?php flosc_permalink_status_indicator($flow_settings['slug'] ?? ''); ?>
        </div>
    </div>
    
    <script>
    function switchIVR(ivr) {
        const tab = '<?php echo esc_js($active_tab); ?>';
        window.location.href = '<?php echo admin_url('admin.php?page=flosc-settings'); ?>&ivr=' + encodeURIComponent(ivr) + '&tab=' + tab;
    }
    </script>
    
    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <?php
        $tabs = [
            'identity' => '🏷️ Identity',
            'ivr-messages' => '💬 IVR Messages',
            'style' => '🎨 Style',
            'ai' => '🤖 AI',
            'knowledge' => '🧠 Knowledge',
            'quiz' => '❓ Quiz',
            'email' => '📧 Email',
            'lessons' => '📚 Lessons',
            'offers' => '💰 Offers',
            'payments' => '💳 Payments',
        ];
        foreach ($tabs as $tab_id => $tab_label):
        ?>
            <a href="?page=flosc-settings&ivr=<?php echo urlencode($selected_ivr); ?>&tab=<?php echo $tab_id; ?>" 
               class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo $tab_label; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Settings Form -->
    <form method="post" class="flosc-settings-form">
        <?php wp_nonce_field('flosc_save_settings'); ?>
        
        <?php if ($active_tab === 'identity'): ?>
            <!-- Identity Tab -->
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <?php flosc_tab_header('🏷️', 'Identity'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="flow_name">Name</label></th>
                        <td>
                            <input type="text" id="flow_name" name="flow_name" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['name'] ?? ''); ?>"
                                   placeholder="e.g., LeSAEp">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="flow_tagline" name="flow_tagline" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['tagline'] ?? ''); ?>"
                                   placeholder="e.g., Learn Spanish the easy way">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_emoji">Emoji</label></th>
                        <td>
                            <input type="text" id="flow_emoji" name="flow_emoji" style="width: 60px; font-size: 24px; text-align: center;"
                                   value="<?php echo esc_attr($flow_settings['emoji'] ?? '🎯'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <code><?php echo home_url('/'); ?></code>
                            <input type="text" id="flow_slug" name="flow_slug" style="width: 150px;"
                                   value="<?php echo esc_attr($flow_settings['slug'] ?? ''); ?>"
                                   placeholder="myapp">
                            <code>/</code>
                            <p class="description">Lowercase letters, numbers, hyphens only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_primary_color">Primary Color</label></th>
                        <td>
                            <input type="color" id="flow_primary_color" name="flow_primary_color"
                                   value="<?php echo esc_attr($flow_settings['primary_color'] ?? '#4f46e5'); ?>"
                                   style="width: 60px; height: 40px; padding: 0; border: 1px solid #ccc;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <option value="active" <?php selected($flow_settings['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($flow_settings['status'] ?? '', 'draft'); ?>>Draft</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
        <?php elseif ($active_tab === 'ivr-messages'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ivr-messages.php'; ?>
            
        <?php elseif ($active_tab === 'style'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/chat-styling.php'; ?>
            
        <?php elseif ($active_tab === 'ai'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-configuration.php'; ?>
            
        <?php elseif ($active_tab === 'knowledge'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/ai-knowledge.php'; ?>
            
        <?php elseif ($active_tab === 'quiz'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/quiz.php'; ?>
            
        <?php elseif ($active_tab === 'email'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/email.php'; ?>
            
        <?php elseif ($active_tab === 'lessons'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/lessons.php'; ?>
            
        <?php elseif ($active_tab === 'offers'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/offers.php'; ?>
            
        <?php elseif ($active_tab === 'payments'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/payments.php'; ?>
            
        <?php endif; ?>
        
        <p class="submit" style="margin-top: 20px;">
            <button type="submit" name="flosc_save" class="button button-primary button-large">
                Save Settings for <?php echo esc_html($flow_settings['name'] ?? $selected_ivr); ?>
            </button>
        </p>
    </form>
</div>

<style>
.flosc-admin { max-width: 1200px; }
.flosc-admin .nav-tab-wrapper { margin-bottom: 0; }
.flosc-admin .card { margin-top: 20px; }
</style>
