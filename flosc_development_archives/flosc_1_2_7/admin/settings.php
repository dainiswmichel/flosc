<?php
/**
 * FLOSC Admin Settings Page v1.2.7
 * 
 * Flow-first interface: Pick a flow, all tabs configure that flow.
 * Uses flosc_get_setting() helper for flow-first/global-fallback.
 */

if (!defined('ABSPATH')) exit;

// Get all flows (migrate if needed)
$all_flows = flosc_flows()->get_all_flows();
if (empty($all_flows)) {
    flosc_flows()->maybe_migrate_from_legacy();
    $all_flows = flosc_flows()->get_all_flows();
}

// Selected flow (default to first)
$selected_flow_id = isset($_GET['flow']) ? sanitize_key($_GET['flow']) : '';
if (empty($selected_flow_id) && !empty($all_flows)) {
    $selected_flow_id = array_key_first($all_flows);
}
$current_flow = $all_flows[$selected_flow_id] ?? null;

// Set global context for tab files
$GLOBALS['flosc_editing_flow'] = $selected_flow_id;
$GLOBALS['flosc_editing_flow_data'] = $current_flow;

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'product';

// ============================================
// HANDLE FORM SAVE
// ============================================
if (isset($_POST['flosc_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_settings')) {
    $flow_id = sanitize_key($_POST['flow_id'] ?? '');
    
    if ($flow_id && isset($all_flows[$flow_id])) {
        $flow_data = $all_flows[$flow_id];
        $flow_settings = [];
        
        // Collect flow-specific settings
        foreach ($_POST as $key => $value) {
            // Settings with fs_ prefix are explicitly flow-specific
            if (strpos($key, 'fs_') === 0) {
                $setting_key = substr($key, 3);
                $flow_settings[$setting_key] = is_array($value) 
                    ? array_map('sanitize_text_field', $value) 
                    : sanitize_text_field($value);
            }
            // Settings with gs_ prefix go to global options only
            elseif (strpos($key, 'gs_') === 0) {
                $option_key = 'flosc_' . substr($key, 3);
                update_option($option_key, is_array($value) 
                    ? array_map('sanitize_text_field', $value) 
                    : sanitize_text_field($value));
            }
            // v1.2.7: Legacy flosc_ prefixed fields - save to BOTH flow and global
            elseif (strpos($key, 'flosc_') === 0) {
                $setting_key = substr($key, 6); // Remove 'flosc_' prefix
                $flow_settings[$setting_key] = is_array($value) 
                    ? array_map('sanitize_text_field', $value) 
                    : sanitize_text_field($value);
                // Also update global
                update_option($key, is_array($value) 
                    ? array_map('sanitize_text_field', $value) 
                    : sanitize_text_field($value));
            }
        }
        
        // Save flow-specific settings
        if (!empty($flow_settings)) {
            flosc_flows()->save_settings($flow_settings, $flow_id);
        }
        
        // Handle core flow fields (identity)
        if (isset($_POST['flow_slug'])) {
            $flow_data['slug'] = sanitize_title($_POST['flow_slug']);
        }
        if (isset($_POST['flow_domain'])) {
            $flow_data['custom_domain'] = sanitize_text_field($_POST['flow_domain']);
        }
        if (isset($_POST['flow_status'])) {
            $flow_data['status'] = sanitize_key($_POST['flow_status']);
        }
        if (isset($_POST['ivr_file'])) {
            $flow_data['ivr_file'] = sanitize_file_name($_POST['ivr_file']);
        }
        
        // Handle product branding
        if (isset($_POST['product_name'])) {
            $flow_data['product'] = [
                'name' => sanitize_text_field($_POST['product_name'] ?? ''),
                'tagline' => sanitize_text_field($_POST['product_tagline'] ?? ''),
                'emoji' => sanitize_text_field($_POST['product_emoji'] ?? '🎯'),
                'logo_url' => esc_url_raw($_POST['product_logo'] ?? ''),
                'primary_color' => sanitize_hex_color($_POST['product_color'] ?? '#4f46e5'),
                'share_text' => sanitize_text_field($_POST['product_share_text'] ?? ''),
            ];
        }
        
        flosc_flows()->update_flow($flow_id, $flow_data);
        
        // Refresh
        $all_flows = flosc_flows()->get_all_flows();
        $current_flow = $all_flows[$flow_id];
        $GLOBALS['flosc_editing_flow_data'] = $current_flow;
        
        $success_message = '✓ Settings saved for ' . esc_html($current_flow['product']['name'] ?? $flow_id);
    }
}

// Build flow URL
$flow_url = '';
if ($current_flow) {
    if (!empty($current_flow['custom_domain'])) {
        $flow_url = (is_ssl() ? 'https://' : 'http://') . rtrim($current_flow['custom_domain'], '/') . '/';
    } elseif (!empty($current_flow['slug'])) {
        $flow_url = home_url('/' . $current_flow['slug'] . '/');
    }
}

// Tab configuration
$tabs = [
    'product' => '🏷️ Identity',
    'ivr-messages' => '💬 IVR',
    'style' => '🎨 Style',
    'ai' => '🤖 AI',
    'ai-knowledge' => '🧠 Knowledge',
    'quiz' => '❓ Quiz',
    'email' => '📧 Email',
    'lessons' => '📚 Lessons',
    'offers' => '💰 Offers',
    'payments' => '💳 Payments',
    'bridge' => '📊 Analytics',
];
?>
<div class="wrap flosc-admin">
    <h1>FLOSC Settings</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo $success_message; ?></p></div>
    <?php endif; ?>
    
    <!-- Flow Selector -->
    <div class="flosc-flow-selector">
        <label><strong>Editing:</strong></label>
        <select id="flow-select" onchange="switchFlow(this.value);">
            <?php foreach ($all_flows as $fid => $fdata): ?>
                <option value="<?php echo esc_attr($fid); ?>" <?php selected($selected_flow_id, $fid); ?>>
                    <?php echo esc_html($fdata['product']['emoji'] ?? '🎯'); ?> 
                    <?php echo esc_html($fdata['product']['name'] ?? $fid); ?>
                    <?php if (($fdata['status'] ?? '') === 'draft'): ?>(Draft)<?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>" class="button">+ New</a>
        <?php if ($flow_url): ?>
            <a href="<?php echo esc_url($flow_url); ?>" target="_blank" class="button">View ↗</a>
        <?php endif; ?>
    </div>
    
    <script>
    function switchFlow(flowId) {
        window.location.href = '<?php echo admin_url('admin.php?page=flosc-settings'); ?>&flow=' + flowId + '&tab=<?php echo esc_js($active_tab); ?>';
    }
    </script>
    
    <?php if ($current_flow): ?>
    
    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab_label): ?>
            <a href="?page=flosc-settings&flow=<?php echo $selected_flow_id; ?>&tab=<?php echo $tab_id; ?>" 
               class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo $tab_label; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Form -->
    <form method="post" class="flosc-settings-form">
        <?php wp_nonce_field('flosc_settings'); ?>
        <input type="hidden" name="flow_id" value="<?php echo esc_attr($selected_flow_id); ?>">
        
        <?php if ($active_tab === 'product'): ?>
            <!-- Identity Tab (inline) -->
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>Flow Identity</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <code><?php echo home_url('/'); ?></code>
                            <input type="text" id="flow_slug" name="flow_slug" 
                                   value="<?php echo esc_attr($current_flow['slug'] ?? ''); ?>" 
                                   style="width:150px;" placeholder="myapp">
                            <code>/</code>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_domain">Custom Domain</label></th>
                        <td>
                            <input type="text" id="flow_domain" name="flow_domain" 
                                   value="<?php echo esc_attr($current_flow['custom_domain'] ?? ''); ?>" 
                                   class="regular-text" placeholder="myapp.com">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <option value="active" <?php selected($current_flow['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($current_flow['status'] ?? '', 'draft'); ?>>Draft</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2 style="margin-top:30px;">Branding</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="product_name">App Name</label></th>
                        <td><input type="text" id="product_name" name="product_name" value="<?php echo esc_attr($current_flow['product']['name'] ?? ''); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="product_tagline">Tagline</label></th>
                        <td><input type="text" id="product_tagline" name="product_tagline" value="<?php echo esc_attr($current_flow['product']['tagline'] ?? ''); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th><label for="product_emoji">Emoji</label></th>
                        <td><input type="text" id="product_emoji" name="product_emoji" value="<?php echo esc_attr($current_flow['product']['emoji'] ?? '🎯'); ?>" style="width:60px;font-size:24px;text-align:center;"></td>
                    </tr>
                    <tr>
                        <th><label for="product_color">Primary Color</label></th>
                        <td><input type="color" id="product_color" name="product_color" value="<?php echo esc_attr($current_flow['product']['primary_color'] ?? '#4f46e5'); ?>" style="width:60px;height:40px;"></td>
                    </tr>
                    <tr>
                        <th><label for="product_logo">Logo URL</label></th>
                        <td><input type="url" id="product_logo" name="product_logo" value="<?php echo esc_attr($current_flow['product']['logo_url'] ?? ''); ?>" class="large-text" placeholder="https://..."></td>
                    </tr>
                </table>
                
                <h2 style="margin-top:30px;">IVR File</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="ivr_file">Conversation File</label></th>
                        <td>
                            <?php $ivr_files = flosc_flows()->get_available_ivr_files(); ?>
                            <select id="ivr_file" name="ivr_file">
                                <?php foreach ($ivr_files as $file): ?>
                                    <option value="<?php echo esc_attr($file); ?>" <?php selected($current_flow['ivr_file'] ?? '', $file); ?>><?php echo esc_html($file); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Located in <code>ai_configuration_files/</code></p>
                        </td>
                    </tr>
                </table>
            </div>
            
        <?php elseif ($active_tab === 'ivr-messages'): ?>
            <?php include plugin_dir_path(__FILE__) . 'ivr-messages.php'; ?>
            
        <?php elseif ($active_tab === 'style'): ?>
            <?php include plugin_dir_path(__FILE__) . 'chat-styling.php'; ?>
            
        <?php elseif ($active_tab === 'ai'): ?>
            <?php include plugin_dir_path(__FILE__) . 'ai-configuration.php'; ?>
            
        <?php elseif ($active_tab === 'ai-knowledge'): ?>
            <?php include plugin_dir_path(__FILE__) . 'ai-knowledge.php'; ?>
            
        <?php elseif ($active_tab === 'quiz'): ?>
            <?php include plugin_dir_path(__FILE__) . 'quiz.php'; ?>
            
        <?php elseif ($active_tab === 'email'): ?>
            <?php include plugin_dir_path(__FILE__) . 'email.php'; ?>
            
        <?php elseif ($active_tab === 'lessons'): ?>
            <?php include plugin_dir_path(__FILE__) . 'lessons.php'; ?>
            
        <?php elseif ($active_tab === 'offers'): ?>
            <?php include plugin_dir_path(__FILE__) . 'offers.php'; ?>
            
        <?php elseif ($active_tab === 'payments'): ?>
            <?php include plugin_dir_path(__FILE__) . 'payments.php'; ?>
            
        <?php elseif ($active_tab === 'bridge'): ?>
            <?php include plugin_dir_path(__FILE__) . 'bridge-analytics.php'; ?>
            
        <?php endif; ?>
        
        <p class="submit">
            <input type="submit" name="flosc_save_settings" class="button button-primary button-large" 
                   value="Save <?php echo esc_attr($current_flow['product']['name'] ?? 'Flow'); ?> Settings">
        </p>
    </form>
    
    <?php else: ?>
        <div class="notice notice-warning" style="margin-top:20px;">
            <p>No flows found. <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>">Create your first flow</a>.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.flosc-admin { max-width: 960px; }
.flosc-flow-selector {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 14px 18px;
    border-radius: 8px;
    margin: 16px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
}
.flosc-flow-selector label { color: white; font-size: 14px; }
.flosc-flow-selector select { font-size: 15px; padding: 7px 14px; border-radius: 6px; border: none; min-width: 200px; }
.flosc-flow-selector .button { background: rgba(255,255,255,0.9); border: none; color: #4f46e5; font-weight: 500; }
.flosc-flow-selector .button:hover { background: white; }
.flosc-admin .nav-tab-wrapper { margin-bottom: 0; }
.flosc-admin .nav-tab { font-size: 12px; padding: 6px 10px; }
.flosc-settings-form .card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
.flosc-global-hint { display: block; margin-top: 4px; font-size: 12px; }
</style>
