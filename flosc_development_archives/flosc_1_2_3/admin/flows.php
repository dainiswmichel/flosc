<?php
/**
 * FLOSC Flows List Page
 * 
 * v1.2.2: Lists all flows accessible to the current user
 */

if (!defined('ABSPATH')) exit;

// Get flows for current user
$flows = flosc_flows()->get_user_flows();
$is_admin = current_user_can('manage_options');

// Handle delete action
if (isset($_GET['delete_flow']) && $is_admin) {
    check_admin_referer('flosc_delete_flow_' . $_GET['delete_flow']);
    $result = flosc_flows()->delete_flow(sanitize_key($_GET['delete_flow']));
    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
    } else {
        wp_redirect(admin_url('admin.php?page=flosc-flows&deleted=1'));
        exit;
    }
}

// Handle status toggle
if (isset($_GET['toggle_flow']) && isset($_GET['_wpnonce'])) {
    check_admin_referer('flosc_toggle_flow_' . $_GET['toggle_flow']);
    $flow_id = sanitize_key($_GET['toggle_flow']);
    
    if (flosc_flows()->can_access_flow_admin($flow_id)) {
        $flow = flosc_flows()->get_flow($flow_id);
        if ($flow) {
            $new_status = ($flow['status'] === 'active') ? 'draft' : 'active';
            flosc_flows()->update_flow($flow_id, ['status' => $new_status]);
            wp_redirect(admin_url('admin.php?page=flosc-flows&toggled=1'));
            exit;
        }
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">FLOSC Flows</h1>
    <?php if ($is_admin): ?>
        <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>" class="page-title-action">Add New</a>
    <?php endif; ?>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Flow deleted successfully.</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['toggled'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Flow status updated.</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Flow saved successfully.</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($flows)): ?>
        <div class="notice notice-warning">
            <p>No flows found. <?php if ($is_admin): ?><a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>">Create your first flow</a>.<?php endif; ?></p>
        </div>
    <?php else: ?>
        
        <style>
            .flosc-flows-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .flosc-flow-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                position: relative;
            }
            .flosc-flow-card.status-draft {
                opacity: 0.7;
                border-style: dashed;
            }
            .flosc-flow-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }
            .flosc-flow-emoji {
                font-size: 32px;
                line-height: 1;
            }
            .flosc-flow-title {
                font-size: 18px;
                font-weight: 600;
                margin: 0;
                color: #1d2327;
            }
            .flosc-flow-tagline {
                color: #50575e;
                font-size: 13px;
                margin: 0;
            }
            .flosc-flow-meta {
                font-size: 13px;
                color: #646970;
                margin: 12px 0;
                line-height: 1.8;
            }
            .flosc-flow-meta code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
            }
            .flosc-flow-status {
                position: absolute;
                top: 12px;
                right: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                padding: 4px 8px;
                border-radius: 3px;
            }
            .flosc-flow-status.active {
                background: #d1fae5;
                color: #065f46;
            }
            .flosc-flow-status.draft {
                background: #fef3c7;
                color: #92400e;
            }
            .flosc-flow-actions {
                display: flex;
                gap: 8px;
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid #e5e7eb;
            }
            .flosc-flow-color {
                width: 20px;
                height: 20px;
                border-radius: 50%;
                display: inline-block;
                vertical-align: middle;
                margin-left: 4px;
                border: 1px solid rgba(0,0,0,0.1);
            }
        </style>
        
        <div class="flosc-flows-grid">
            <?php foreach ($flows as $flow): ?>
                <?php 
                $flow_url = '';
                if (!empty($flow['custom_domain'])) {
                    $domain = preg_replace('#^https?://#', '', $flow['custom_domain']);
                    $flow_url = (is_ssl() ? 'https://' : 'http://') . rtrim($domain, '/') . '/';
                } elseif (!empty($flow['slug'])) {
                    $flow_url = home_url('/' . $flow['slug'] . '/');
                }
                ?>
                <div class="flosc-flow-card status-<?php echo esc_attr($flow['status']); ?>">
                    <span class="flosc-flow-status <?php echo esc_attr($flow['status']); ?>">
                        <?php echo esc_html($flow['status']); ?>
                    </span>
                    
                    <div class="flosc-flow-header">
                        <span class="flosc-flow-emoji"><?php echo esc_html($flow['product']['emoji'] ?? '🎯'); ?></span>
                        <div>
                            <h3 class="flosc-flow-title"><?php echo esc_html($flow['product']['name'] ?? $flow['id']); ?></h3>
                            <?php if (!empty($flow['product']['tagline'])): ?>
                                <p class="flosc-flow-tagline"><?php echo esc_html($flow['product']['tagline']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flosc-flow-meta">
                        <strong>ID:</strong> <code><?php echo esc_html($flow['id']); ?></code><br>
                        <?php if (!empty($flow['slug'])): ?>
                            <strong>Slug:</strong> <code>/<?php echo esc_html($flow['slug']); ?></code><br>
                        <?php endif; ?>
                        <?php if (!empty($flow['custom_domain'])): ?>
                            <strong>Domain:</strong> <code><?php echo esc_html($flow['custom_domain']); ?></code><br>
                        <?php endif; ?>
                        <strong>IVR:</strong> <code><?php echo esc_html($flow['ivr_file'] ?? 'ivr.md'); ?></code>
                        <span class="flosc-flow-color" style="background-color: <?php echo esc_attr($flow['product']['primary_color'] ?? '#4f46e5'); ?>"></span>
                    </div>
                    
                    <div class="flosc-flow-actions">
                        <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=' . urlencode($flow['id'])); ?>" class="button button-primary">
                            Edit
                        </a>
                        <?php if ($flow_url): ?>
                            <a href="<?php echo esc_url($flow_url); ?>" class="button" target="_blank">
                                View ↗
                            </a>
                        <?php endif; ?>
                        <?php if (flosc_flows()->can_access_flow_admin($flow['id'])): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=flosc-flows&toggle_flow=' . urlencode($flow['id'])), 'flosc_toggle_flow_' . $flow['id']); ?>" class="button">
                                <?php echo $flow['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($is_admin && count($flows) > 1): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=flosc-flows&delete_flow=' . urlencode($flow['id'])), 'flosc_delete_flow_' . $flow['id']); ?>" 
                               class="button" 
                               style="color: #d63638;"
                               onclick="return confirm('Delete this flow? This cannot be undone.');">
                                Delete
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php endif; ?>
    
    <?php if ($is_admin): ?>
        <hr style="margin: 40px 0 20px;">
        <h2>About FLOSC Flows</h2>
        <p>FLOSC Flows allow you to run multiple independent chatbots from a single WordPress installation. Each flow can have:</p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong>Its own branding</strong> - Name, emoji, colors, logo</li>
            <li><strong>Its own URL</strong> - Either a slug (/myapp) or custom domain (myapp.com)</li>
            <li><strong>Its own IVR messages</strong> - Different conversation flows per app</li>
            <li><strong>Its own content</strong> - WordPress category for lessons</li>
            <li><strong>Its own team</strong> - Assign Editors/Authors to specific flows</li>
        </ul>
        <p><a href="<?php echo admin_url('admin.php?page=flosc-global'); ?>">Global Settings →</a></p>
    <?php endif; ?>
</div>
