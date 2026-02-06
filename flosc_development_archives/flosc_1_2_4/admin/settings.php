<?php
/**
 * FLOSC Admin Settings Page
 * v1.2.4: Flow-aware settings with dropdown selector
 */

if (!defined('ABSPATH')) exit;

// Include settings helper
require_once plugin_dir_path(__FILE__) . 'settings-helper.php';

// Get current context
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'product';
$editing_flow = isset($_GET['flow']) ? sanitize_key($_GET['flow']) : '';
$all_flows = flosc_flows()->get_all_flows();

// Handle flow-specific settings save
if (isset($_POST['flosc_save_flow_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_flow_settings')) {
    $flow_id = sanitize_key($_POST['editing_flow_id']);
    
    if (!empty($flow_id) && isset($all_flows[$flow_id])) {
        $flow = $all_flows[$flow_id];
        
        // Collect all settings from POST that start with 'flosc_'
        $settings_to_save = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'flosc_') === 0) {
                // Strip 'flosc_' prefix for flow storage
                $setting_key = substr($key, 6);
                if (is_array($value)) {
                    $settings_to_save[$setting_key] = array_map('sanitize_text_field', $value);
                } else {
                    $settings_to_save[$setting_key] = sanitize_text_field($value);
                }
            }
        }
        
        // Merge into flow data
        $flow = array_merge($flow, $settings_to_save);
        flosc_flows()->update_flow($flow_id, $flow);
        
        // Refresh flows list after save
        $all_flows = flosc_flows()->get_all_flows();
        $flow = $all_flows[$flow_id];
        
        $success_message = 'Settings saved for ' . esc_html($flow['product']['name'] ?? $flow_id) . '.';
    }
}

// Get display info based on context
if ($editing_flow && isset($all_flows[$editing_flow])) {
    $flow = $all_flows[$editing_flow];
    $page_title = esc_html($flow['product']['name'] ?? $editing_flow) . ' Settings';
    $app_url = !empty($flow['custom_domain']) 
        ? (is_ssl() ? 'https://' : 'http://') . rtrim($flow['custom_domain'], '/') . '/'
        : (!empty($flow['slug']) ? home_url('/' . $flow['slug'] . '/') : '');
} else {
    $page_title = 'Global Settings';
    $slug = get_option('flosc_app_slug', 'flosc');
    $app_url = home_url('/' . $slug . '/');
}
?>
<div class="wrap flosc-admin">
    <h1><?php echo $page_title; ?></h1>
    
    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $success_message; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Flow Selector -->
    <div class="flosc-flow-selector">
        <label for="flow-select"><strong>Editing:</strong></label>
        <select id="flow-select" onchange="window.location.href=this.value;">
            <option value="<?php echo admin_url('admin.php?page=flosc-settings&tab=' . $active_tab); ?>" <?php selected($editing_flow, ''); ?>>
                🌐 Global Defaults
            </option>
            <?php foreach ($all_flows as $fid => $fdata): ?>
                <option value="<?php echo admin_url('admin.php?page=flosc-settings&tab=' . $active_tab . '&flow=' . $fid); ?>" <?php selected($editing_flow, $fid); ?>>
                    <?php echo esc_html($fdata['product']['emoji'] ?? '🎯'); ?> <?php echo esc_html($fdata['product']['name'] ?? $fid); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($editing_flow): ?>
            <span class="flosc-flow-badge">Flow-specific settings</span>
        <?php else: ?>
            <span class="flosc-flow-badge global">Applies to all flows without custom settings</span>
        <?php endif; ?>
    </div>
    
    <?php if ($app_url): ?>
    <div class="flosc-admin-header">
        <p>App URL: <a href="<?php echo esc_url($app_url); ?>" target="_blank"><?php echo esc_html($app_url); ?></a></p>
    </div>
    <?php endif; ?>
    
    <nav class="nav-tab-wrapper">
        <?php 
        $flow_param = $editing_flow ? '&flow=' . $editing_flow : '';
        $tabs = [
            'product' => 'Product',
            'ivr-messages' => 'IVR Messages',
            'style' => 'Chat Styling',
            'ai' => 'AI Configuration',
            'quiz' => 'Quiz',
            'email' => 'Email',
            'ai-knowledge' => 'AI Knowledge',
            'offers' => 'Offers',
            'payments' => 'Payments',
            'lessons' => 'Lessons',
            'bridge' => 'Bridge Analytics',
        ];
        foreach ($tabs as $tab_id => $tab_label): ?>
            <a href="?page=flosc-settings&tab=<?php echo $tab_id . $flow_param; ?>" 
               class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo $tab_label; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <?php if ($editing_flow): ?>
        <!-- Flow-specific form (saves to flow array) -->
        <form method="post">
            <?php wp_nonce_field('flosc_flow_settings'); ?>
            <input type="hidden" name="editing_flow_id" value="<?php echo esc_attr($editing_flow); ?>">
            
            <?php
            // Set global variable for tab files to use
            $GLOBALS['flosc_editing_flow'] = $editing_flow;
            $GLOBALS['flosc_editing_flow_data'] = $flow;
            ?>
            
            <?php if ($active_tab === 'product'): ?>
                <?php include plugin_dir_path(__FILE__) . 'product.php'; ?>
            <?php elseif ($active_tab === 'ivr-messages'): ?>
                <?php include plugin_dir_path(__FILE__) . 'ivr-messages.php'; ?>
            <?php elseif ($active_tab === 'style'): ?>
                <?php include plugin_dir_path(__FILE__) . 'chat-styling.php'; ?>
            <?php elseif ($active_tab === 'ai'): ?>
                <?php include plugin_dir_path(__FILE__) . 'ai-configuration.php'; ?>
            <?php elseif ($active_tab === 'quiz'): ?>
                <?php include plugin_dir_path(__FILE__) . 'quiz.php'; ?>
            <?php elseif ($active_tab === 'lessons'): ?>
                <?php include plugin_dir_path(__FILE__) . 'lessons.php'; ?>
            <?php elseif ($active_tab === 'email'): ?>
                <?php include plugin_dir_path(__FILE__) . 'email.php'; ?>
            <?php elseif ($active_tab === 'ai-knowledge'): ?>
                <?php include plugin_dir_path(__FILE__) . 'ai-knowledge.php'; ?>
            <?php elseif ($active_tab === 'offers'): ?>
                <?php include plugin_dir_path(__FILE__) . 'offers.php'; ?>
            <?php elseif ($active_tab === 'payments'): ?>
                <?php include plugin_dir_path(__FILE__) . 'payments.php'; ?>
            <?php elseif ($active_tab === 'bridge'): ?>
                <?php include plugin_dir_path(__FILE__) . 'bridge-analytics.php'; ?>
            <?php endif; ?>
            
            <p class="submit">
                <input type="submit" name="flosc_save_flow_settings" class="button button-primary" value="Save Flow Settings">
                <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=' . $active_tab); ?>" class="button">
                    Switch to Global Settings
                </a>
            </p>
        </form>
    <?php else: ?>
        <!-- Global settings form (saves to wp_options) -->
        <form method="post" action="options.php">
            <?php settings_fields('flosc_settings'); ?>
            
            <?php
            // Clear flow editing context
            unset($GLOBALS['flosc_editing_flow']);
            unset($GLOBALS['flosc_editing_flow_data']);
            ?>
            
            <?php if ($active_tab === 'product'): ?>
                <?php include plugin_dir_path(__FILE__) . 'product.php'; ?>
            <?php elseif ($active_tab === 'ivr-messages'): ?>
                <?php include plugin_dir_path(__FILE__) . 'ivr-messages.php'; ?>
            <?php elseif ($active_tab === 'style'): ?>
                <?php include plugin_dir_path(__FILE__) . 'chat-styling.php'; ?>
            <?php elseif ($active_tab === 'ai'): ?>
                <?php include plugin_dir_path(__FILE__) . 'ai-configuration.php'; ?>
            <?php elseif ($active_tab === 'quiz'): ?>
                <?php include plugin_dir_path(__FILE__) . 'quiz.php'; ?>
            <?php elseif ($active_tab === 'lessons'): ?>
                <?php include plugin_dir_path(__FILE__) . 'lessons.php'; ?>
            <?php elseif ($active_tab === 'email'): ?>
                <?php include plugin_dir_path(__FILE__) . 'email.php'; ?>
            <?php elseif ($active_tab === 'ai-knowledge'): ?>
                <?php include plugin_dir_path(__FILE__) . 'ai-knowledge.php'; ?>
            <?php elseif ($active_tab === 'offers'): ?>
                <?php include plugin_dir_path(__FILE__) . 'offers.php'; ?>
            <?php elseif ($active_tab === 'payments'): ?>
                <?php include plugin_dir_path(__FILE__) . 'payments.php'; ?>
            <?php elseif ($active_tab === 'bridge'): ?>
                <?php include plugin_dir_path(__FILE__) . 'bridge-analytics.php'; ?>
            <?php endif; ?>
            
            <?php submit_button(); ?>
        </form>
    <?php endif; ?>
</div>

<style>
.flosc-admin { max-width: 900px; }
.flosc-admin-header { background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 4px; }
.flosc-admin .nav-tab-wrapper { margin-bottom: 20px; }
.flosc-info-box { background: #f9f9f9; border-left: 4px solid #2271b1; padding: 15px 20px; margin: 20px 0; }
.flosc-info-box ol { margin: 10px 0 0 20px; }
.flosc-info-box ul { margin: 5px 0 5px 20px; list-style-type: disc; }

/* Flow selector */
.flosc-flow-selector {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 12px 16px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.flosc-flow-selector select {
    font-size: 14px;
    padding: 6px 12px;
    min-width: 200px;
}
.flosc-flow-badge {
    background: #dbeafe;
    color: #1e40af;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}
.flosc-flow-badge.global {
    background: #f3f4f6;
    color: #6b7280;
}
</style>
