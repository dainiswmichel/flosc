<?php
/**
 * FLOSC Admin Settings Page
 * v1.2.5: Clean flow-first interface
 * 
 * Simple: Pick a flow from dropdown, all tabs configure that flow.
 */

if (!defined('ABSPATH')) exit;

// Get all flows
$all_flows = flosc_flows()->get_all_flows();

// If no flows exist, create default from legacy settings
if (empty($all_flows)) {
    flosc_flows()->maybe_migrate_from_legacy();
    $all_flows = flosc_flows()->get_all_flows();
}

// Get selected flow (default to first flow)
$selected_flow_id = isset($_GET['flow']) ? sanitize_key($_GET['flow']) : '';
if (empty($selected_flow_id) && !empty($all_flows)) {
    $selected_flow_id = array_key_first($all_flows);
}

$current_flow = $all_flows[$selected_flow_id] ?? null;
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'product';

// Handle flow settings save
if (isset($_POST['flosc_save_flow_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_flow_settings')) {
    $flow_id = sanitize_key($_POST['flow_id']);
    
    if (!empty($flow_id) && isset($all_flows[$flow_id])) {
        $flow_data = $all_flows[$flow_id];
        
        // Collect settings from POST
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'flosc_') === 0) {
                $setting_key = substr($key, 6); // Remove 'flosc_' prefix
                if (is_array($value)) {
                    $flow_data[$setting_key] = array_map('sanitize_text_field', $value);
                } else {
                    $flow_data[$setting_key] = sanitize_text_field($value);
                }
            }
        }
        
        // Handle product sub-array specially
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
        
        // Handle identity fields
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
        
        flosc_flows()->update_flow($flow_id, $flow_data);
        $all_flows = flosc_flows()->get_all_flows();
        $current_flow = $all_flows[$flow_id];
        
        $success_message = 'Settings saved for ' . esc_html($current_flow['product']['name'] ?? $flow_id) . '.';
    }
}

// Build flow URL for display
$flow_url = '';
if ($current_flow) {
    if (!empty($current_flow['custom_domain'])) {
        $flow_url = (is_ssl() ? 'https://' : 'http://') . rtrim($current_flow['custom_domain'], '/') . '/';
    } elseif (!empty($current_flow['slug'])) {
        $flow_url = home_url('/' . $current_flow['slug'] . '/');
    }
}
?>
<div class="wrap flosc-admin">
    <h1>FLOSC Settings</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $success_message; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Flow Selector - THE KEY UI ELEMENT -->
    <div class="flosc-flow-selector">
        <label for="flow-select"><strong>Working on:</strong></label>
        <select id="flow-select" onchange="switchFlow(this.value);">
            <?php foreach ($all_flows as $fid => $fdata): ?>
                <option value="<?php echo esc_attr($fid); ?>" <?php selected($selected_flow_id, $fid); ?>>
                    <?php echo esc_html($fdata['product']['emoji'] ?? '🎯'); ?> 
                    <?php echo esc_html($fdata['product']['name'] ?? $fid); ?>
                    <?php if ($fdata['status'] === 'draft'): ?>(Draft)<?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>" class="button">
            + New Flow
        </a>
        <?php if ($flow_url): ?>
            <a href="<?php echo esc_url($flow_url); ?>" target="_blank" class="button">
                View App ↗
            </a>
        <?php endif; ?>
    </div>
    
    <script>
    function switchFlow(flowId) {
        const tab = '<?php echo esc_js($active_tab); ?>';
        window.location.href = '<?php echo admin_url('admin.php?page=flosc-settings'); ?>&flow=' + flowId + '&tab=' + tab;
    }
    </script>
    
    <?php if ($current_flow): ?>
    
    <!-- All Settings Tabs -->
    <nav class="nav-tab-wrapper">
        <?php 
        $tabs = [
            'product' => '🏷️ Identity',
            'ivr-messages' => '💬 IVR Messages',
            'style' => '🎨 Chat Styling',
            'ai' => '🤖 AI Configuration',
            'quiz' => '❓ Quiz',
            'email' => '📧 Email',
            'ai-knowledge' => '🧠 AI Knowledge',
            'offers' => '💰 Offers',
            'payments' => '💳 Payments',
            'lessons' => '📚 Lessons',
            'bridge' => '📊 Bridge Analytics',
        ];
        foreach ($tabs as $tab_id => $tab_label): ?>
            <a href="?page=flosc-settings&flow=<?php echo $selected_flow_id; ?>&tab=<?php echo $tab_id; ?>" 
               class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo $tab_label; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Settings Form -->
    <form method="post" class="flosc-settings-form">
        <?php wp_nonce_field('flosc_flow_settings'); ?>
        <input type="hidden" name="flow_id" value="<?php echo esc_attr($selected_flow_id); ?>">
        
        <?php
        // Set global context for tab files
        $GLOBALS['flosc_editing_flow'] = $selected_flow_id;
        $GLOBALS['flosc_editing_flow_data'] = $current_flow;
        ?>
        
        <?php if ($active_tab === 'product'): ?>
            <!-- Identity/Product Tab - Built-in for simplicity -->
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>Flow Identity</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <code><?php echo home_url('/'); ?></code>
                            <input type="text" id="flow_slug" name="flow_slug" 
                                   value="<?php echo esc_attr($current_flow['slug'] ?? ''); ?>" 
                                   style="width: 150px;"
                                   placeholder="myapp">
                            <code>/</code>
                            <p class="description">Lowercase letters, numbers, hyphens only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flow_domain">Custom Domain</label></th>
                        <td>
                            <input type="text" id="flow_domain" name="flow_domain" 
                                   value="<?php echo esc_attr($current_flow['custom_domain'] ?? ''); ?>" 
                                   class="regular-text" placeholder="myapp.com">
                            <p class="description">Optional. Point your domain's DNS here first.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <option value="active" <?php selected($current_flow['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($current_flow['status'] ?? 'draft', 'draft'); ?>>Draft</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2 style="margin-top: 30px;">Branding</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="product_name">App Name</label></th>
                        <td>
                            <input type="text" id="product_name" name="product_name" 
                                   value="<?php echo esc_attr($current_flow['product']['name'] ?? ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="product_tagline" name="product_tagline" 
                                   value="<?php echo esc_attr($current_flow['product']['tagline'] ?? ''); ?>" 
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_emoji">Emoji</label></th>
                        <td>
                            <input type="text" id="product_emoji" name="product_emoji" 
                                   value="<?php echo esc_attr($current_flow['product']['emoji'] ?? '🎯'); ?>" 
                                   style="width: 60px; font-size: 24px; text-align: center;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_color">Primary Color</label></th>
                        <td>
                            <input type="color" id="product_color" name="product_color" 
                                   value="<?php echo esc_attr($current_flow['product']['primary_color'] ?? '#4f46e5'); ?>" 
                                   style="width: 60px; height: 40px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_logo">Logo URL</label></th>
                        <td>
                            <input type="url" id="product_logo" name="product_logo" 
                                   value="<?php echo esc_attr($current_flow['product']['logo_url'] ?? ''); ?>" 
                                   class="large-text" placeholder="https://...">
                        </td>
                    </tr>
                </table>
                
                <h2 style="margin-top: 30px;">IVR File</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ivr_file">Conversation File</label></th>
                        <td>
                            <?php $ivr_files = flosc_flows()->get_available_ivr_files(); ?>
                            <select id="ivr_file" name="ivr_file" class="regular-text">
                                <?php foreach ($ivr_files as $file): ?>
                                    <option value="<?php echo esc_attr($file); ?>" <?php selected($current_flow['ivr_file'] ?? '', $file); ?>>
                                        <?php echo esc_html($file); ?>
                                    </option>
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
            <input type="submit" name="flosc_save_flow_settings" class="button button-primary button-large" 
                   value="Save <?php echo esc_attr($current_flow['product']['name'] ?? 'Flow'); ?> Settings">
        </p>
    </form>
    
    <?php else: ?>
        <div class="notice notice-warning" style="margin-top: 20px;">
            <p>No flows found. <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>">Create your first flow</a>.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.flosc-admin { max-width: 960px; }

.flosc-flow-selector {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 16px 20px;
    border-radius: 8px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
.flosc-flow-selector label {
    color: white;
    font-size: 14px;
}
.flosc-flow-selector select {
    font-size: 16px;
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    min-width: 220px;
    font-weight: 500;
}
.flosc-flow-selector .button {
    background: rgba(255,255,255,0.9);
    border: none;
    color: #4f46e5;
    font-weight: 500;
}
.flosc-flow-selector .button:hover {
    background: white;
}

.flosc-admin .nav-tab-wrapper {
    margin-bottom: 0;
    border-bottom: 1px solid #c3c4c7;
}
.flosc-admin .nav-tab {
    font-size: 13px;
    padding: 8px 12px;
}

.flosc-settings-form .card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.flosc-settings-form .submit {
    margin-top: 20px;
    padding: 20px;
    background: #f6f7f7;
    border-top: 1px solid #c3c4c7;
    margin-left: -20px;
    margin-right: -20px;
    margin-bottom: -20px;
}
</style>
