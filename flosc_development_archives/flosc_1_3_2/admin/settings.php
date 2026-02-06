<?php
/**
 * FLOSC Admin Settings Page v1.3.2
 * 
 * Simple: IVR file = Flow. Pick file, edit all tabs, save.
 * 
 * v1.2.9: Added flosc_tab_header() helper, permalink status, Michel timestamp
 * v1.3.0: Fixed permalink status detection - save defaults on first access
 * v1.3.2: Identity tab shows URL mapping, DNS help, All Flows accordion view
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
        $version = defined('FLOSC_VERSION') ? FLOSC_VERSION : '?.?.?';
        
        echo '<div class="flosc-tab-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h2 style="margin: 0; color: white; font-size: 18px; display: flex; justify-content: space-between; align-items: center;">';
        echo '<span>' . esc_html($emoji . ' ' . $tab_name . ' Configuration') . '</span>';
        echo '<span style="font-size: 11px; font-weight: normal; opacity: 0.8;">v' . esc_html($version) . '</span>';
        echo '</h2>';
        echo '<p style="margin: 5px 0 0; color: rgba(255,255,255,0.9); font-size: 13px;">';
        echo 'Flow: <strong>' . esc_html($flow_name) . '</strong> ';
        echo '<code style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; color: white;">(' . esc_html($ivr_file) . ')</code>';
        echo '</p>';
        echo '</div>';
    }
}

/**
 * Tab Footer Helper - displays version in footer of all tabs
 * 
 * @return void
 */
if (!function_exists('flosc_tab_footer')) {
    function flosc_tab_footer() {
        $version = defined('FLOSC_VERSION') ? FLOSC_VERSION : '?.?.?';
        echo '<div class="flosc-tab-footer" style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: right;">';
        echo '<span style="color: #9ca3af; font-size: 11px;">FLOSC v' . esc_html($version) . '</span>';
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
        if (empty($rules) || !is_array($rules)) {
            return 'unknown';
        }
        
        // Check if any rule contains our slug pattern
        // Rules are stored as: '^lesaep/?$' => 'index.php?flosc_app=1&flosc_ivr=...'
        foreach ($rules as $regex => $query) {
            // Check if this rule matches our slug (the regex starts with ^slug)
            if (preg_match('/^\^' . preg_quote($slug, '/') . '/', $regex)) {
                return 'ok';
            }
            // Also check query string for flosc_app (indicates FLOSC route)
            if (strpos($regex, $slug) !== false && strpos($query, 'flosc_app=1') !== false) {
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

// Set defaults if empty AND save them (v1.3.0 fix)
if (empty($flow_settings)) {
    $flow_settings = [
        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $selected_ivr)),
        'tagline' => '',
        'emoji' => '🎯',
        'slug' => sanitize_title(pathinfo($selected_ivr, PATHINFO_FILENAME)),
        'primary_color' => '#4f46e5',
        'status' => 'active',
    ];
    // v1.3.0: Save defaults so rewrite rules can find them
    update_option($settings_key, $flow_settings);
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
    <h1 style="display: flex; align-items: center; gap: 10px;">
        FLOSC Settings 
        <span style="font-size: 12px; font-weight: normal; color: #6b7280; background: #f3f4f6; padding: 3px 10px; border-radius: 4px;">v<?php echo esc_html(FLOSC_VERSION); ?></span>
    </h1>
    
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
            <!-- Identity Tab v1.3.2 - Enhanced with URL mapping + All Flows view -->
            
            <?php
            // View mode: single flow or all flows
            $view_mode = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'single';
            
            // Get all flows for accordion view
            $all_flows = [];
            foreach ($ivr_files as $ivr_file) {
                $key = 'flosc_flow_' . sanitize_key(pathinfo($ivr_file, PATHINFO_FILENAME));
                $settings = get_option($key, []);
                if (empty($settings)) {
                    $settings = [
                        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $ivr_file)),
                        'slug' => sanitize_title(pathinfo($ivr_file, PATHINFO_FILENAME)),
                        'emoji' => '🎯',
                        'status' => 'active',
                    ];
                }
                $all_flows[$ivr_file] = $settings;
            }
            ?>
            
            <!-- View Toggle -->
            <div style="display: flex; gap: 10px; margin: 20px 0;">
                <a href="?page=flosc-settings&ivr=<?php echo urlencode($selected_ivr); ?>&tab=identity&view=single" 
                   class="button <?php echo $view_mode === 'single' ? 'button-primary' : ''; ?>">
                    Single Flow
                </a>
                <a href="?page=flosc-settings&ivr=<?php echo urlencode($selected_ivr); ?>&tab=identity&view=all" 
                   class="button <?php echo $view_mode === 'all' ? 'button-primary' : ''; ?>">
                    All Flows (<?php echo count($ivr_files); ?>)
                </a>
            </div>
            
            <?php if ($view_mode === 'all'): ?>
            
            <!-- ALL FLOWS ACCORDION VIEW -->
            <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;">🔀 All FLOSC Flows Identity Overview</h2>
                <p style="color: #6b7280;">Configure identity settings for all your flows. Click to expand each flow.</p>
                
                <div class="flosc-flows-accordion" style="margin-top: 20px;">
                    <?php foreach ($all_flows as $ivr_file => $settings): 
                        $is_current = ($ivr_file === $selected_ivr);
                        $flow_url = home_url('/' . ($settings['slug'] ?? 'flosc') . '/');
                        $status_color = ($settings['status'] ?? 'active') === 'active' ? '#10b981' : '#9ca3af';
                    ?>
                    <div class="flosc-flow-item" style="border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 10px; <?php echo $is_current ? 'border-color: #4f46e5; border-width: 2px;' : ''; ?>">
                        <div class="flosc-flow-header" 
                             onclick="toggleFlowAccordion(this)" 
                             style="padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: <?php echo $is_current ? '#f0f0ff' : '#f9fafb'; ?>; border-radius: 7px 7px 0 0;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 24px;"><?php echo esc_html($settings['emoji'] ?? '🎯'); ?></span>
                                <div>
                                    <strong style="font-size: 15px;"><?php echo esc_html($settings['name'] ?? $ivr_file); ?></strong>
                                    <?php if ($is_current): ?>
                                        <span style="background: #4f46e5; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; margin-left: 8px;">EDITING</span>
                                    <?php endif; ?>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                                        <code style="background: #e5e7eb; padding: 1px 6px; border-radius: 3px;"><?php echo esc_html($ivr_file); ?></code>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span style="background: <?php echo $status_color; ?>; color: white; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    <?php echo esc_html(ucfirst($settings['status'] ?? 'active')); ?>
                                </span>
                                <span class="dashicons dashicons-arrow-down-alt2" style="color: #9ca3af;"></span>
                            </div>
                        </div>
                        <div class="flosc-flow-content" style="display: none; padding: 20px; border-top: 1px solid #e5e7eb;">
                            <!-- URL Mapping Info -->
                            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 15px 20px; border-radius: 8px; color: white; margin-bottom: 20px;">
                                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">🌐 Your FLOSC App URL</div>
                                <div style="font-family: monospace; font-size: 16px; font-weight: 600;">
                                    <a href="<?php echo esc_url($flow_url); ?>" target="_blank" style="color: white; text-decoration: none;">
                                        <?php echo esc_html($flow_url); ?> ↗
                                    </a>
                                </div>
                                <div style="font-size: 11px; opacity: 0.8; margin-top: 8px;">
                                    Slug: <code style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($settings['slug'] ?? ''); ?></code>
                                    → IVR: <code style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($ivr_file); ?></code>
                                </div>
                            </div>
                            
                            <!-- Quick Info Table -->
                            <table style="width: 100%; font-size: 13px;">
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280; width: 120px;">Name:</td>
                                    <td style="padding: 8px 0;"><strong><?php echo esc_html($settings['name'] ?? ''); ?></strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280;">Tagline:</td>
                                    <td style="padding: 8px 0;"><?php echo esc_html($settings['tagline'] ?? '—'); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280;">Primary Color:</td>
                                    <td style="padding: 8px 0;">
                                        <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo esc_attr($settings['primary_color'] ?? '#4f46e5'); ?>; border-radius: 4px; vertical-align: middle;"></span>
                                        <code style="margin-left: 8px;"><?php echo esc_html($settings['primary_color'] ?? '#4f46e5'); ?></code>
                                    </td>
                                </tr>
                            </table>
                            
                            <div style="margin-top: 15px;">
                                <a href="?page=flosc-settings&ivr=<?php echo urlencode($ivr_file); ?>&tab=identity&view=single" class="button button-primary">
                                    Edit This Flow
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <script>
            function toggleFlowAccordion(header) {
                const content = header.nextElementSibling;
                const icon = header.querySelector('.dashicons');
                const isOpen = content.style.display !== 'none';
                
                // Close all others (accordion behavior)
                document.querySelectorAll('.flosc-flow-content').forEach(c => c.style.display = 'none');
                document.querySelectorAll('.flosc-flow-header .dashicons').forEach(i => {
                    i.classList.remove('dashicons-arrow-up-alt2');
                    i.classList.add('dashicons-arrow-down-alt2');
                });
                
                // Toggle this one
                if (!isOpen) {
                    content.style.display = 'block';
                    icon.classList.remove('dashicons-arrow-down-alt2');
                    icon.classList.add('dashicons-arrow-up-alt2');
                }
            }
            </script>
            
            <?php else: ?>
            
            <!-- SINGLE FLOW EDIT VIEW -->
            <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
                <?php flosc_tab_header('🏷️', 'Identity'); ?>
                
                <!-- v1.3.2: URL Mapping Info Box -->
                <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 20px; border-radius: 8px; color: white; margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                        <div>
                            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">🌐 Your FLOSC App URL</div>
                            <div style="font-family: monospace; font-size: 18px; font-weight: 600;">
                                <a href="<?php echo esc_url($flow_url); ?>" target="_blank" style="color: white; text-decoration: none;">
                                    <?php echo esc_html($flow_url); ?> ↗
                                </a>
                            </div>
                            <div style="font-size: 12px; opacity: 0.8; margin-top: 8px;">
                                Visitors to this URL will see the <strong><?php echo esc_html($flow_settings['name'] ?? $selected_ivr); ?></strong> chat experience.
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 11px; opacity: 0.8;">IVR File</div>
                            <code style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 4px; font-size: 13px;"><?php echo esc_html($selected_ivr); ?></code>
                        </div>
                    </div>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th><label for="flow_name">Name</label></th>
                        <td>
                            <input type="text" id="flow_name" name="flow_name" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['name'] ?? ''); ?>"
                                   placeholder="e.g., LeSAEp">
                            <p class="description">Display name for this flow. Shown in the chat header.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="flow_tagline" name="flow_tagline" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['tagline'] ?? ''); ?>"
                                   placeholder="e.g., Learn Spanish the easy way">
                            <p class="description">Short description shown under the name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_emoji">Emoji</label></th>
                        <td>
                            <input type="text" id="flow_emoji" name="flow_emoji" style="width: 60px; font-size: 24px; text-align: center;"
                                   value="<?php echo esc_attr($flow_settings['emoji'] ?? '🎯'); ?>">
                            <p class="description">App icon emoji. Click to select.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <code style="background: #f3f4f6; padding: 8px 12px; border-radius: 4px;"><?php echo home_url('/'); ?></code>
                                <input type="text" id="flow_slug" name="flow_slug" style="width: 150px;"
                                       value="<?php echo esc_attr($flow_settings['slug'] ?? ''); ?>"
                                       placeholder="myapp">
                                <code style="background: #f3f4f6; padding: 8px 12px; border-radius: 4px;">/</code>
                            </div>
                            <p class="description">Lowercase letters, numbers, hyphens only. After changing, click "Flush Now" above.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_primary_color">Primary Color</label></th>
                        <td>
                            <input type="color" id="flow_primary_color" name="flow_primary_color"
                                   value="<?php echo esc_attr($flow_settings['primary_color'] ?? '#4f46e5'); ?>"
                                   style="width: 60px; height: 40px; padding: 0; border: 1px solid #ccc; cursor: pointer;">
                            <span id="color-preview" style="margin-left: 10px; padding: 5px 15px; border-radius: 4px; color: white; background: <?php echo esc_attr($flow_settings['primary_color'] ?? '#4f46e5'); ?>;">
                                Preview
                            </span>
                            <script>
                            document.getElementById('flow_primary_color').addEventListener('input', function(e) {
                                document.getElementById('color-preview').style.background = e.target.value;
                            });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <option value="active" <?php selected($flow_settings['status'] ?? '', 'active'); ?>>🟢 Active</option>
                                <option value="draft" <?php selected($flow_settings['status'] ?? '', 'draft'); ?>>⚪ Draft</option>
                            </select>
                            <p class="description">Draft flows are only visible to admins.</p>
                        </td>
                    </tr>
                </table>
                
                <!-- v1.3.2: DNS & Setup Help -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px; cursor: pointer;" onclick="document.getElementById('dns-help').style.display = document.getElementById('dns-help').style.display === 'none' ? 'block' : 'none';">
                        <span class="dashicons dashicons-editor-help" style="color: #6b7280;"></span>
                        DNS & Setup Guide
                        <span style="font-size: 12px; font-weight: normal; color: #9ca3af;">(click to expand)</span>
                    </h3>
                    <div id="dns-help" style="display: none; background: #f9fafb; padding: 20px; border-radius: 8px; margin-top: 15px;">
                        <h4 style="margin-top: 0;">📌 How FLOSC URLs Work</h4>
                        <p>Your FLOSC app runs on your WordPress site. The URL slug you set above creates a custom route:</p>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><code><?php echo home_url('/'); ?><strong>[slug]</strong>/</code> → Serves this flow's chat interface</li>
                            <li>Each flow gets its own slug → Each flow has its own URL</li>
                            <li>IVR file determines the conversation flow and AI behavior</li>
                        </ul>
                        
                        <h4>🌍 Custom Domain Setup</h4>
                        <p>Want to use a custom domain like <code>chat.yourdomain.com</code>?</p>
                        <ol style="list-style: decimal; margin-left: 20px;">
                            <li>Add a CNAME or A record pointing your subdomain to your WordPress server</li>
                            <li>Configure your web server (Apache/Nginx) to handle the domain</li>
                            <li>Set up the slug to match the path you want</li>
                        </ol>
                        
                        <h4>📚 Full Documentation</h4>
                        <p>For detailed setup guides, user manuals, and technical reference:</p>
                        <p>
                            <a href="https://flosc.ai/docs/setup" target="_blank" class="button">
                                📖 FLOSC Setup Guide
                            </a>
                            <a href="https://flosc.ai/docs/dns" target="_blank" class="button" style="margin-left: 10px;">
                                🌐 DNS Configuration
                            </a>
                            <a href="https://flosc.ai/docs" target="_blank" class="button" style="margin-left: 10px;">
                                📚 Full Documentation
                            </a>
                        </p>
                        <p style="font-size: 12px; color: #6b7280; margin-top: 15px;">
                            Need help? Visit <a href="https://flosc.ai" target="_blank">flosc.ai</a> for support and community resources.
                        </p>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
            
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
        
        <?php flosc_tab_footer(); ?>
    </form>
</div>

<style>
.flosc-admin { max-width: 1200px; }
.flosc-admin .nav-tab-wrapper { margin-bottom: 0; }
.flosc-admin .card { margin-top: 20px; }
</style>
