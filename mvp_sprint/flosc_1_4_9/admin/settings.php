<?php
/**
 * FLOSC Admin Settings Page v1.4.8
 * 
 * Simple: IVR file = Flow. Pick file, edit all tabs, save.
 * 
 * v1.2.9: Added flosc_tab_header() helper, permalink status, Michel timestamp
 * v1.3.0: Fixed permalink status detection - save defaults on first access
 * v1.3.2: Identity tab shows URL mapping, DNS help
 * v1.3.3: All Flows = fully expanded inline editing, Domain field
 * v1.3.4: Register rewrite rules for ALL IVR files with default slugs, immediate version-flush
 * v1.4.8: Admin styling overhaul - WordPress-native colors, no gradients, no emojis in chrome
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
        
        echo '<div class="flosc-tab-header" style="background: #f0f0f1; border: 1px solid #c3c4c7; padding: 12px 18px; border-radius: 2px; margin-bottom: 20px;">';
        echo '<h2 style="margin: 0; color: #1d2327; font-size: 16px; display: flex; justify-content: space-between; align-items: center;">';
        echo '<span>' . esc_html($tab_name . ' Configuration') . '</span>';
        echo '<span style="font-size: 11px; font-weight: normal; color: #787c82;">v' . esc_html($version) . '</span>';
        echo '</h2>';
        echo '<p style="margin: 5px 0 0; color: #50575e; font-size: 13px;">';
        echo 'Flow: <strong>' . esc_html($flow_name) . '</strong> ';
        echo '<code style="background: #e0e0e0; padding: 2px 8px; border-radius: 2px; color: #1d2327;">(' . esc_html($ivr_file) . ')</code>';
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
        echo '<div class="flosc-tab-footer" style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #c3c4c7; text-align: right;">';
        echo '<span style="color: #787c82; font-size: 11px;">FLOSC v' . esc_html($version) . '</span>';
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
            'ok' => ['bg' => '#d4edda', 'text_color' => '#155724', 'text' => '&#10003; Permalinks OK'],
            'missing' => ['bg' => '#f8d7da', 'text_color' => '#721c24', 'text' => '&#9888; Needs Flush'],
            'unknown' => ['bg' => '#fff3cd', 'text_color' => '#856404', 'text' => '? Status Unknown'],
        ];
        
        $color = $colors[$status];
        
        echo '<div class="flosc-permalink-status" style="display: inline-flex; align-items: center; gap: 10px; margin-left: 15px;">';
        echo '<span style="background: ' . $color['bg'] . '; color: ' . $color['text_color'] . '; padding: 4px 10px; border-radius: 2px; font-size: 12px; font-weight: 600;">';
        echo $color['text'];
        echo '</span>';
        
        if ($last_flush) {
            echo '<span style="color: #c3c4c7; font-size: 11px;">Last flush: ' . esc_html($last_flush) . '</span>';
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
    // v1.3.5: Preserve underscores in default slug (don't use sanitize_title which converts to hyphens)
    $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($selected_ivr, PATHINFO_FILENAME)));
    $flow_settings = [
        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $selected_ivr)),
        'tagline' => '',
        'emoji' => '🎯',
        'slug' => $default_slug,
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
    
    // Save flow settings
    update_option($settings_key, $new_settings);
    $flow_settings = $new_settings;
    
    // v1.4.6: Also save global flosc_* options (SSO, payments, quiz, AI, etc.)
    // These are registered via register_setting() but stored as individual WP options
    // v1.4.6: Keys that contain multiline content (e.g. Apple private key)
    $textarea_keys = ['flosc_sso_apple_private_key'];
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'flosc_') === 0 && $key !== 'flosc_save' && $key !== 'flosc_save_flow' && $key !== 'flosc_save_all_flows') {
            if (is_array($value)) {
                update_option($key, array_map('sanitize_text_field', $value));
            } elseif (in_array($key, $textarea_keys, true)) {
                update_option($key, sanitize_textarea_field($value));
            } else {
                update_option($key, sanitize_text_field($value));
            }
        }
    }
    
    // v1.4.6: Handle checkbox unchecking — checkboxes don't POST when unchecked
    // IMPORTANT: Only run on SSO tab — otherwise saving any other tab wipes SSO enabled flags
    if ($active_tab === 'sso') {
        $sso_providers = ['google', 'apple', 'facebook', 'microsoft', 'linkedin'];
        foreach ($sso_providers as $provider) {
            $enabled_key = "flosc_sso_{$provider}_enabled";
            if (!isset($_POST[$enabled_key])) {
                update_option($enabled_key, '');
            }
        }
    }
    
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
        <span style="font-size: 12px; font-weight: normal; color: #50575e; background: #f0f0f1; padding: 3px 10px; border-radius: 2px;">v<?php echo esc_html(FLOSC_VERSION); ?></span>
    </h1>
    
    <?php if (isset($saved)): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Settings saved for <strong><?php echo esc_html($flow_settings['name'] ?? $selected_ivr); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <!-- IVR File Selector = Flow Selector -->
    <div class="flosc-ivr-selector" style="background: #1d2327; padding: 15px 20px; border-radius: 2px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <label style="color: #f0f0f1; font-weight: 600; font-size: 14px;">Flow:</label>
            <select id="ivr-select" onchange="switchIVR(this.value);" style="padding: 8px 12px; border-radius: 2px; border: 1px solid #50575e; min-width: 250px; font-size: 14px;">
                <?php foreach ($ivr_files as $file): ?>
                    <option value="<?php echo esc_attr($file); ?>" <?php selected($selected_ivr, $file); ?>>
                        <?php echo esc_html($file); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($flow_settings['status'] === 'active' && !empty($flow_settings['slug'])): ?>
                <a href="<?php echo esc_url($flow_url); ?>" target="_blank" class="button button-small" style="font-size: 13px;">
                    View App &#8599;
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Permalink Status Row -->
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #50575e;">
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
            'identity' => 'Identity',
            'ivr-messages' => 'IVR Messages',
            'style' => 'Style',
            'ai' => 'AI',
            'knowledge' => 'Knowledge',
            'quiz' => 'Quiz',
            'email' => 'Email',
            'lessons' => 'Lessons',
            'offers' => 'Offers',
            'payments' => 'Payments',
            'sso' => 'SSO',
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
            <!-- Identity Tab v1.3.3 - All Flows = fully expanded inline editing -->
            
            <?php
            // View mode: single flow or all flows
            $view_mode = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'single';
            
            // Get all flows data
            $all_flows = [];
            foreach ($ivr_files as $ivr_file) {
                $key = 'flosc_flow_' . sanitize_key(pathinfo($ivr_file, PATHINFO_FILENAME));
                $settings = get_option($key, []);
                if (empty($settings)) {
                    // v1.3.5: Preserve underscores in default slug
                    $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($ivr_file, PATHINFO_FILENAME)));
                    $settings = [
                        'name' => ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $ivr_file)),
                        'slug' => $default_slug,
                        'emoji' => '🎯',
                        'domain' => '',
                        'tagline' => '',
                        'primary_color' => '#4f46e5',
                        'status' => 'active',
                    ];
                }
                $all_flows[$ivr_file] = ['key' => $key, 'settings' => $settings];
            }
            
            // Handle individual flow save via AJAX or form
            if (isset($_POST['flosc_save_flow']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_settings')) {
                $save_ivr = sanitize_file_name($_POST['flosc_save_flow']);
                if (isset($all_flows[$save_ivr])) {
                    $flow_key = $all_flows[$save_ivr]['key'];
                    $new_settings = $all_flows[$save_ivr]['settings'];
                    
                    // Update from POST data (fields prefixed with ivr filename hash)
                    $prefix = 'flow_' . md5($save_ivr) . '_';
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, $prefix) === 0) {
                            $setting_key = substr($key, strlen($prefix));
                            $new_settings[$setting_key] = sanitize_text_field($value);
                        }
                    }
                    
                    update_option($flow_key, $new_settings);
                    $all_flows[$save_ivr]['settings'] = $new_settings;
                    $individual_saved = $save_ivr;
                }
            }
            
            // Handle save all flows
            if (isset($_POST['flosc_save_all_flows']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_settings')) {
                foreach ($all_flows as $ivr_file => $flow_data) {
                    $flow_key = $flow_data['key'];
                    $new_settings = $flow_data['settings'];
                    
                    $prefix = 'flow_' . md5($ivr_file) . '_';
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, $prefix) === 0) {
                            $setting_key = substr($key, strlen($prefix));
                            $new_settings[$setting_key] = sanitize_text_field($value);
                        }
                    }
                    
                    update_option($flow_key, $new_settings);
                    $all_flows[$ivr_file]['settings'] = $new_settings;
                }
                $all_saved = true;
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
            
            <?php if (isset($all_saved)): ?>
                <div class="notice notice-success is-dismissible"><p>✓ All flows saved!</p></div>
            <?php endif; ?>
            <?php if (isset($individual_saved)): ?>
                <div class="notice notice-success is-dismissible"><p>✓ Saved: <?php echo esc_html($individual_saved); ?></p></div>
            <?php endif; ?>
            
            <?php if ($view_mode === 'all'): ?>
            
            <!-- ALL FLOWS - FULLY EXPANDED INLINE EDITING -->
            <div style="max-width: 1000px;">
                <div style="background: #1d2327; padding: 15px 20px; border-radius: 2px; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: #f0f0f1; font-size: 16px;">All FLOSC Flows &mdash; Identity Settings</h2>
                    <p style="margin: 5px 0 0; color: #a7aaad; font-size: 13px;">
                        All flows expanded. Edit any field, then save individually or save all at bottom.
                    </p>
                </div>
                
                <?php foreach ($all_flows as $ivr_file => $flow_data): 
                    $settings = $flow_data['settings'];
                    $prefix = 'flow_' . md5($ivr_file) . '_';
                    $is_current = ($ivr_file === $selected_ivr);
                    // v1.3.5: Preserve underscores in default slug
                    $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($ivr_file, PATHINFO_FILENAME)));
                    $slug = $settings['slug'] ?? $default_slug;
                    $flow_url = home_url('/' . $slug . '/');
                    $full_url = !empty($settings['domain']) ? 'https://' . $settings['domain'] . '/' : $flow_url;
                ?>
                
                <div class="flosc-flow-block" style="background: white; border: 2px solid <?php echo $is_current ? '#2271b1' : '#c3c4c7'; ?>; border-radius: 2px; padding: 25px; margin-bottom: 20px;">
                    
                    <!-- Flow Header with IVR file -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #c3c4c7;">
                        <div>
                            <h3 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 28px;"><?php echo esc_html($settings['emoji'] ?? '🎯'); ?></span>
                                <?php echo esc_html($settings['name'] ?? $ivr_file); ?>
                                <?php if ($is_current): ?>
                                    <span style="background: #2271b1; color: white; padding: 3px 10px; border-radius: 2px; font-size: 11px;">CURRENT</span>
                                <?php endif; ?>
                            </h3>
                            <div style="margin-top: 5px;">
                                <code style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 12px;"><?php echo esc_html($ivr_file); ?></code>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: <?php echo ($settings['status'] ?? 'active') === 'active' ? '#d4edda' : '#f0f0f1'; ?>; color: <?php echo ($settings['status'] ?? 'active') === 'active' ? '#155724' : '#50575e'; ?>; padding: 4px 12px; border-radius: 2px; font-size: 12px; font-weight: 600;">
                                <?php echo esc_html(ucfirst($settings['status'] ?? 'active')); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- URL Mapping Summary -->
                    <div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 15px 20px; border-radius: 2px; color: #1d2327; margin-bottom: 20px;">
                        <div style="font-size: 11px; color: #50575e; margin-bottom: 3px;">This flow is accessible at:</div>
                        <div style="font-family: monospace; font-size: 15px; font-weight: 600;">
                            <a href="<?php echo esc_url($flow_url); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">
                                <?php echo esc_html($flow_url); ?> &#8599;
                            </a>
                            <?php if (!empty($settings['domain'])): ?>
                                <span style="color: #787c82; margin: 0 8px;">&rarr;</span>
                                <a href="https://<?php echo esc_attr($settings['domain']); ?>/" target="_blank" style="color: #2271b1; text-decoration: none;">
                                    https://<?php echo esc_html($settings['domain']); ?>/ &#8599;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- ALL EDITABLE FIELDS - Slug first -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        
                        <!-- Left Column -->
                        <div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">URL Slug <span style="color: #d63638;">(required)</span></label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <code style="background: #f3f4f6; padding: 8px 10px; border-radius: 4px; font-size: 13px;"><?php echo home_url('/'); ?></code>
                                    <input type="text" name="<?php echo $prefix; ?>slug" 
                                           value="<?php echo esc_attr($slug); ?>"
                                           placeholder="myapp"
                                           style="width: 250px; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px; font-weight: 600;">
                                    <code style="background: #f3f4f6; padding: 8px 10px; border-radius: 4px; font-size: 13px;">/</code>
                                </div>
                                <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">The URL path where this flow is served on your WordPress site</p>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Custom Domain</label>
                                <input type="text" name="<?php echo $prefix; ?>domain" 
                                       value="<?php echo esc_attr($settings['domain'] ?? ''); ?>"
                                       placeholder="e.g., flosc.ai or lesaep.com"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                                <p style="font-size: 11px; color: #50575e; margin: 4px 0 0;">Custom domain pointing to this flow</p>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Name</label>
                                <input type="text" name="<?php echo $prefix; ?>name" 
                                       value="<?php echo esc_attr($settings['name'] ?? ''); ?>"
                                       placeholder="e.g., FLOSC or LeSAEp"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Tagline</label>
                                <input type="text" name="<?php echo $prefix; ?>tagline" 
                                       value="<?php echo esc_attr($settings['tagline'] ?? ''); ?>"
                                       placeholder="e.g., AI-Powered Sales Funnels"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div>
                            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Emoji</label>
                                    <input type="text" name="<?php echo $prefix; ?>emoji" 
                                           value="<?php echo esc_attr($settings['emoji'] ?? '🎯'); ?>"
                                           style="width: 60px; padding: 8px; border: 1px solid #c3c4c7; border-radius: 2px; font-size: 20px; text-align: center;">
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Color</label>
                                    <input type="color" name="<?php echo $prefix; ?>primary_color" 
                                           value="<?php echo esc_attr($settings['primary_color'] ?? '#4f46e5'); ?>"
                                           style="width: 60px; height: 38px; padding: 0; border: 1px solid #c3c4c7; border-radius: 2px; cursor: pointer;">
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Status</label>
                                    <select name="<?php echo $prefix; ?>status" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                                        <option value="active" <?php selected($settings['status'] ?? '', 'active'); ?>>Active</option>
                                        <option value="draft" <?php selected($settings['status'] ?? '', 'draft'); ?>>Draft</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- DNS Setup Info (if custom domain set) -->
                            <?php if (!empty($settings['domain'])): ?>
                            <div style="background: #fef3c7; border: 1px solid #dba617; padding: 12px 15px; border-radius: 2px; margin-bottom: 15px;">
                                <div style="font-weight: 600; font-size: 12px; color: #50575e; margin-bottom: 5px;">DNS Setup for <?php echo esc_html($settings['domain']); ?></div>
                                <div style="font-size: 11px; color: #50575e;">
                                    Point your domain to: <code style="background: white; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code><br>
                                    <a href="https://flosc.ai/docs/dns" target="_blank" style="color: #50575e;">Full DNS guide →</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Save This Flow Button -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #c3c4c7;">
                        <button type="submit" name="flosc_save_flow" value="<?php echo esc_attr($ivr_file); ?>" class="button button-secondary">
                            Save <?php echo esc_html($settings['name'] ?? $ivr_file); ?>
                        </button>
                    </div>
                </div>
                
                <?php endforeach; ?>
                
                <!-- DNS Setup Guide -->
                <div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 20px; border-radius: 2px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px; color: #1d2327; font-size: 15px;">Custom Domain DNS Setup</h4>
                    
                    <p style="font-size: 13px; color: #1d2327; margin: 0 0 15px; line-height: 1.6;">
                        <strong>How it works:</strong> Your custom domain (e.g., <code>flosc.ai</code>) will point to this WordPress installation, 
                        and FLOSC will automatically serve the correct flow when visitors access that domain.
                    </p>
                    
                    <div style="background: white; padding: 15px; border-radius: 2px; margin-bottom: 15px;">
                        <p style="font-size: 13px; color: #1d2327; margin: 0 0 10px;"><strong>Step 1: Configure your DNS records</strong></p>
                        <ul style="font-size: 12px; color: #1d2327; margin: 0; padding-left: 20px; list-style: disc;">
                            <li style="margin-bottom: 5px;">Add a <strong>CNAME record</strong> pointing your domain to: <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code></li>
                            <li style="margin-bottom: 5px;">Or add an <strong>A record</strong> pointing to your server's IP address</li>
                            <li>For www subdomain, add another CNAME pointing <code>www.yourdomain.com</code> → <code>yourdomain.com</code></li>
                        </ul>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 2px; margin-bottom: 15px;">
                        <p style="font-size: 13px; color: #1d2327; margin: 0 0 10px;"><strong>Step 2: Configure your web server</strong></p>
                        <p style="font-size: 12px; color: #1d2327; margin: 0;">
                            Your web server (Apache/Nginx) must be configured to accept requests for the custom domain 
                            and route them to this WordPress installation. Contact your hosting provider if needed.
                        </p>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 2px;">
                        <p style="font-size: 13px; color: #1d2327; margin: 0 0 10px;"><strong>Step 3: Enter the domain above</strong></p>
                        <p style="font-size: 12px; color: #1d2327; margin: 0;">
                            Enter just the domain name (e.g., <code>flosc.ai</code>) in the Custom Domain field for your flow. 
                            FLOSC will handle the rest!
                        </p>
                    </div>
                </div>
                
                <!-- Save All Flows Button -->
                <div style="background: #f0f0f1; padding: 20px; border-radius: 2px; text-align: center; margin-top: 20px;">
                    <button type="submit" name="flosc_save_all_flows" value="1" class="button button-primary button-large">
                        Save All Flows
                    </button>
                    <p style="margin: 10px 0 0; font-size: 12px; color: #50575e;">Saves identity settings for all <?php echo count($all_flows); ?> flows at once</p>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- SINGLE FLOW EDIT VIEW -->
            <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
                <?php flosc_tab_header('🏷️', 'Identity'); ?>
                
                <!-- URL Mapping Info Box -->
                <?php 
                // v1.3.5: Preserve underscores in default slug
                $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($selected_ivr, PATHINFO_FILENAME)));
                $current_slug = $flow_settings['slug'] ?? $default_slug;
                $flow_url = home_url('/' . $current_slug . '/');
                ?>
                <div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 20px; border-radius: 2px; color: #1d2327; margin-bottom: 25px;">
                    <div style="font-size: 12px; color: #50575e; margin-bottom: 5px;">This flow is accessible at:</div>
                    <div style="font-family: monospace; font-size: 18px; font-weight: 600;">
                        <a href="<?php echo esc_url($flow_url); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">
                            <?php echo esc_html($flow_url); ?> &#8599;
                        </a>
                        <?php if (!empty($flow_settings['domain'])): ?>
                            <span style="color: #787c82; margin: 0 10px;">&rarr;</span>
                            <a href="https://<?php echo esc_attr($flow_settings['domain']); ?>/" target="_blank" style="color: #2271b1; text-decoration: none;">
                                https://<?php echo esc_html($flow_settings['domain']); ?>/ &#8599;
                            </a>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 11px; color: #787c82; margin-top: 8px;">
                        IVR File: <code style="background: #e0e0e0; padding: 2px 6px; border-radius: 2px;"><?php echo esc_html($selected_ivr); ?></code>
                    </div>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <code style="background: #f3f4f6; padding: 8px 12px; border-radius: 4px;"><?php echo home_url('/'); ?></code>
                                <input type="text" id="flow_slug" name="flow_slug" style="width: 180px; font-weight: 600;"
                                       value="<?php echo esc_attr($current_slug); ?>"
                                       placeholder="myapp">
                                <code style="background: #f3f4f6; padding: 8px 12px; border-radius: 4px;">/</code>
                            </div>
                            <p class="description">The URL path where this flow is served. Lowercase letters, numbers, hyphens only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_domain">Custom Domain</label></th>
                        <td>
                            <input type="text" id="flow_domain" name="flow_domain" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['domain'] ?? ''); ?>"
                                   placeholder="e.g., flosc.ai or lesaep.com">
                            <p class="description">Custom domain pointing to this flow</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_name">Name</label></th>
                        <td>
                            <input type="text" id="flow_name" name="flow_name" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['name'] ?? ''); ?>"
                                   placeholder="e.g., FLOSC or LeSAEp">
                            <p class="description">Display name shown in the chat header.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="flow_tagline" name="flow_tagline" class="regular-text"
                                   value="<?php echo esc_attr($flow_settings['tagline'] ?? ''); ?>"
                                   placeholder="e.g., AI-Powered Sales Funnels">
                            <p class="description">Short description shown under the name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_emoji">Emoji</label></th>
                        <td>
                            <input type="text" id="flow_emoji" name="flow_emoji" style="width: 60px; font-size: 24px; text-align: center;"
                                   value="<?php echo esc_attr($flow_settings['emoji'] ?? '🎯'); ?>">
                            <p class="description">Icon shown in the chat interface.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_primary_color">Color</label></th>
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
                                <option value="active" <?php selected($flow_settings['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($flow_settings['status'] ?? '', 'draft'); ?>>Draft</option>
                            </select>
                            <p class="description">Draft flows are only visible to admins.</p>
                        </td>
                    </tr>
                </table>
                
                <!-- DNS Setup Info (if custom domain set) -->
                <?php if (!empty($flow_settings['domain'])): ?>
                <div style="background: #fef3c7; border: 1px solid #dba617; padding: 15px 20px; border-radius: 2px; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px; color: #50575e; font-size: 14px;">DNS Setup for <?php echo esc_html($flow_settings['domain']); ?></h4>
                    <p style="font-size: 13px; color: #50575e; margin: 0;">
                        Point your domain to: <code style="background: white; padding: 3px 8px; border-radius: 4px;"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code>
                    </p>
                    <p style="font-size: 12px; color: #50575e; margin: 8px 0 0;">
                        <a href="https://flosc.ai/docs/dns" target="_blank" style="color: #50575e;">Full DNS Configuration Guide</a>
                    </p>
                </div>
                <?php endif; ?>
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
            
        <?php elseif ($active_tab === 'sso'): ?>
            <?php include FLOSC_PLUGIN_DIR . 'admin/sso.php'; ?>
            
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
