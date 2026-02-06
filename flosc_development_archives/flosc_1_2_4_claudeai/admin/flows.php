<?php
/**
 * FLOSC Flows Admin Page v1.2.4
 * 
 * Lists all flows with management actions
 */

if (!defined('ABSPATH')) exit;

$is_admin = current_user_can('manage_options');
$flows = flosc_flows()->get_user_flows();

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'flosc_toggle_flow_status')) {
    $toggle_id = sanitize_key($_GET['toggle_status']);
    $flow = flosc_flows()->get_flow($toggle_id);
    
    if ($flow && flosc_flows()->can_access_flow_admin($toggle_id)) {
        $new_status = $flow['status'] === 'active' ? 'draft' : 'active';
        flosc_flows()->update_flow($toggle_id, ['status' => $new_status]);
        wp_redirect(admin_url('admin.php?page=flosc-flows&toggled=1'));
        exit;
    }
}

// Handle deletion
if (isset($_GET['delete_flow']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'flosc_delete_flow')) {
    $delete_id = sanitize_key($_GET['delete_flow']);
    
    if ($is_admin && flosc_flows()->can_access_flow_admin($delete_id)) {
        $result = flosc_flows()->delete_flow($delete_id);
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } else {
            wp_redirect(admin_url('admin.php?page=flosc-flows&deleted=1'));
            exit;
        }
    }
}

// Refresh flows list after changes
$flows = flosc_flows()->get_user_flows();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">FLOSC Flows</h1>
    
    <?php if ($is_admin): ?>
        <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>" class="page-title-action">Add New Flow</a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Flow saved successfully.</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Flow deleted successfully.</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['toggled'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ Flow status updated.</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <p class="description" style="margin-bottom: 20px;">
        Each flow is an independent chatbot with its own identity, AI configuration, and content.
        <?php if ($is_admin): ?>
            <br>Configure <strong>Global Defaults</strong> in the Settings tabs below. Flows inherit global settings unless overridden.
        <?php endif; ?>
    </p>
    
    <?php if (empty($flows)): ?>
        <div class="card" style="padding: 40px; text-align: center;">
            <h2>No Flows Found</h2>
            <p>Create your first flow to get started.</p>
            <?php if ($is_admin): ?>
                <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=new'); ?>" class="button button-primary button-hero">Create Your First Flow</a>
            <?php else: ?>
                <p><em>Contact an administrator to be assigned to a flow.</em></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="flosc-flows-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php foreach ($flows as $flow): ?>
                <?php
                $flow_url = !empty($flow['custom_domain']) 
                    ? 'https://' . $flow['custom_domain']
                    : home_url('/' . $flow['slug'] . '/');
                $status_class = $flow['status'] === 'active' ? 'active' : 'draft';
                ?>
                <div class="card flosc-flow-card" style="margin: 0; padding: 0; overflow: hidden;">
                    <!-- Header with color stripe -->
                    <div style="background: <?php echo esc_attr($flow['product']['primary_color'] ?? '#4f46e5'); ?>; padding: 15px 20px; color: white;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if (!empty($flow['product']['logo_url'])): ?>
                                <img src="<?php echo esc_url($flow['product']['logo_url']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                            <?php else: ?>
                                <span style="font-size: 32px;"><?php echo esc_html($flow['product']['emoji'] ?? '🎯'); ?></span>
                            <?php endif; ?>
                            <div>
                                <h3 style="margin: 0; font-size: 18px; color: white;">
                                    <?php echo esc_html($flow['product']['name'] ?? $flow['id']); ?>
                                </h3>
                                <?php if (!empty($flow['product']['tagline'])): ?>
                                    <div style="opacity: 0.9; font-size: 13px;"><?php echo esc_html($flow['product']['tagline']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div style="padding: 15px 20px;">
                        <!-- Status Badge -->
                        <div style="margin-bottom: 12px;">
                            <span class="flosc-status-badge flosc-status-<?php echo $status_class; ?>" 
                                  style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;
                                  <?php echo $status_class === 'active' ? 'background: #dcfce7; color: #166534;' : 'background: #f3f4f6; color: #6b7280;'; ?>">
                                <?php echo $status_class === 'active' ? '● Active' : '○ Draft'; ?>
                            </span>
                        </div>
                        
                        <!-- Details -->
                        <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Slug:</td>
                                <td style="padding: 4px 0;"><code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px;">/<?php echo esc_html($flow['slug'] ?: '—'); ?>/</code></td>
                            </tr>
                            <?php if (!empty($flow['custom_domain'])): ?>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Domain:</td>
                                <td style="padding: 4px 0;"><?php echo esc_html($flow['custom_domain']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">IVR:</td>
                                <td style="padding: 4px 0;"><?php echo esc_html($flow['ivr_file'] ?: '(global)'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280;">Quiz:</td>
                                <td style="padding: 4px 0;"><?php echo esc_html($flow['quiz_type'] ?: '(global)'); ?></td>
                            </tr>
                        </table>
                        
                        <!-- Actions -->
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=' . urlencode($flow['id'])); ?>" 
                               class="button button-primary" style="flex: 1;">
                                ✎ Edit
                            </a>
                            <?php if ($flow['status'] === 'active'): ?>
                                <a href="<?php echo esc_url($flow_url); ?>" 
                                   class="button" target="_blank" style="flex: 1;">
                                    ↗ View
                                </a>
                            <?php endif; ?>
                            
                            <!-- Toggle Status -->
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=flosc-flows&toggle_status=' . urlencode($flow['id'])), 'flosc_toggle_flow_status'); ?>" 
                               class="button" title="<?php echo $flow['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                <?php echo $flow['status'] === 'active' ? '⏸' : '▶'; ?>
                            </a>
                            
                            <?php if ($is_admin && count($flows) > 1): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=flosc-flows&delete_flow=' . urlencode($flow['id'])), 'flosc_delete_flow'); ?>" 
                                   class="button" style="color: #dc2626;" 
                                   onclick="return confirm('Delete flow &quot;<?php echo esc_js($flow['product']['name'] ?? $flow['id']); ?>&quot;? This cannot be undone.');">
                                    🗑
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($is_admin): ?>
        <div style="margin-top: 40px; padding: 20px; background: #f0f6ff; border: 1px solid #c3dafe; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #1e40af;">💡 Flow Architecture</h3>
            <p style="margin-bottom: 0;">
                <strong>Global Settings:</strong> Configure defaults in Settings, AI, Knowledge, Style, Email, Lessons, and Payments tabs below.<br>
                <strong>Per-Flow Settings:</strong> Override any setting for a specific flow by editing that flow. Empty values inherit from global.<br>
                <strong>Use Cases:</strong> Run multiple products (LeSAEp, Simplified Solfeggio, FLOSC) from one WordPress install.
            </p>
        </div>
    <?php endif; ?>
</div>

<style>
.flosc-flow-card {
    transition: box-shadow 0.2s, transform 0.2s;
}
.flosc-flow-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
</style>
