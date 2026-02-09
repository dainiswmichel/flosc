<?php
/**
 * FLOSC Flows Overview Page
 * v1.2.5: Simple list of flows with quick actions
 */

if (!defined('ABSPATH')) exit;

$flows = flosc_flows()->get_user_flows();
$is_admin = current_user_can('manage_options');

// Handle delete
if (isset($_GET['delete_flow']) && $is_admin && wp_verify_nonce($_GET['_wpnonce'], 'flosc_delete_flow')) {
    $result = flosc_flows()->delete_flow(sanitize_key($_GET['delete_flow']));
    if (!is_wp_error($result)) {
        wp_redirect(admin_url('admin.php?page=flosc-flows&deleted=1'));
        exit;
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
        <div class="notice notice-success is-dismissible"><p>Flow deleted.</p></div>
    <?php endif; ?>
    
    <p class="description" style="margin-bottom: 20px;">
        Quick overview of your flows. Click <strong>Configure</strong> to edit all settings for a flow.
    </p>
    
    <?php if (empty($flows)): ?>
        <div class="notice notice-warning">
            <p>No flows yet. <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>">Create your first flow</a>.</p>
        </div>
    <?php else: ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 50px;"></th>
                <th>Name</th>
                <th>URL</th>
                <th>IVR File</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($flows as $flow): ?>
                <?php
                $flow_url = '';
                if (!empty($flow['custom_domain'])) {
                    $flow_url = (is_ssl() ? 'https://' : 'http://') . rtrim($flow['custom_domain'], '/') . '/';
                } elseif (!empty($flow['slug'])) {
                    $flow_url = home_url('/' . $flow['slug'] . '/');
                }
                ?>
                <tr>
                    <td style="font-size: 28px; text-align: center;">
                        <?php echo esc_html($flow['product']['emoji'] ?? '🎯'); ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html($flow['product']['name'] ?? $flow['id']); ?></strong>
                        <?php if (!empty($flow['product']['tagline'])): ?>
                            <br><small style="color: #666;"><?php echo esc_html($flow['product']['tagline']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($flow_url): ?>
                            <a href="<?php echo esc_url($flow_url); ?>" target="_blank">
                                <?php echo esc_html(preg_replace('#^https?://#', '', $flow_url)); ?>
                            </a>
                        <?php else: ?>
                            <em>No URL set</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code style="font-size: 12px;"><?php echo esc_html($flow['ivr_file'] ?? 'default'); ?></code>
                    </td>
                    <td>
                        <?php if ($flow['status'] === 'active'): ?>
                            <span style="color: #059669; font-weight: 600;">● Active</span>
                        <?php else: ?>
                            <span style="color: #d97706;">○ Draft</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=flosc-settings&flow=' . $flow['id']); ?>" 
                           class="button button-primary button-small">Configure</a>
                        
                        <?php if ($flow_url && $flow['status'] === 'active'): ?>
                            <a href="<?php echo esc_url($flow_url); ?>" target="_blank" 
                               class="button button-small">View ↗</a>
                        <?php endif; ?>
                        
                        <?php if ($is_admin && count($flows) > 1): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=flosc-flows&delete_flow=' . $flow['id']), 'flosc_delete_flow'); ?>" 
                               class="button button-small" style="color: #dc2626;"
                               onclick="return confirm('Delete this flow? This cannot be undone.');">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php endif; ?>
    
    <div style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
        <h3 style="margin-top: 0;">💡 Tip: Use the Settings Page</h3>
        <p>The <a href="<?php echo admin_url('admin.php?page=flosc-settings'); ?>"><strong>Settings</strong></a> page is where you configure everything for each flow. 
        Use the dropdown at the top to switch between flows.</p>
    </div>
</div>
