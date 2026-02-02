<?php
/**
 * FLOSC Product Settings Tab
 * 
 * Handles basic product configuration including:
 * - App URL slug
 * - Product name and tagline
 * - Logo emoji
 * - Primary brand color
 * - Google Analytics integration
 * - Permalink maintenance tools
 */

if (!defined('ABSPATH')) exit;

// Show success message after permalink flush
if (isset($_GET['flushed'])) {
    echo '<div class="notice notice-success is-dismissible"><p>✓ Permalinks flushed successfully. Your app should now be accessible.</p></div>';
}

$current_slug = get_option('flosc_app_slug', 'app');
$app_url = home_url('/' . $current_slug . '/');

// Quick check if rewrite rules exist
global $wp_rewrite;
$rules_exist = false;
if (!empty($wp_rewrite->rules)) {
    foreach ($wp_rewrite->rules as $rule => $rewrite) {
        if (strpos($rule, '^' . $current_slug) === 0) {
            $rules_exist = true;
            break;
        }
    }
}
?>

<!-- App Status Card -->
<div style="background: <?php echo $rules_exist ? '#d1fae5' : '#fef3c7'; ?>; border: 1px solid <?php echo $rules_exist ? '#10b981' : '#f59e0b'; ?>; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
        <span style="font-size: 24px;"><?php echo $rules_exist ? '✅' : '⚠️'; ?></span>
        <strong style="font-size: 16px;">App Status: <?php echo $rules_exist ? 'Active' : 'Needs Permalink Flush'; ?></strong>
    </div>
    <p style="margin: 0 0 12px 0;">
        Your app URL: <a href="<?php echo esc_url($app_url); ?>" target="_blank" style="font-weight: 600;"><?php echo esc_html($app_url); ?></a>
    </p>
    <?php if (!$rules_exist): ?>
        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=flosc_flush_permalinks'), 'flosc_flush_permalinks'); ?>" class="button button-primary">
            🔄 Flush Permalinks Now
        </a>
        <p class="description" style="margin-top: 8px;">Click to activate your app URL. This is safe and takes 1 second.</p>
    <?php endif; ?>
</div>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_app_slug">App URL Slug</label></th>
        <td>
            <input type="text" id="flosc_app_slug" name="flosc_app_slug" value="<?php echo esc_attr($current_slug); ?>" class="regular-text">
            <p class="description">Your app will be at: <?php echo esc_html(home_url('/')); ?><strong>[slug]</strong>/</p>
            <p class="description" style="color: #6b7280;">After changing the slug, save settings then click "Flush Permalinks" if needed.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_product_name">Product Name</label></th>
        <td>
            <input type="text" id="flosc_product_name" name="flosc_product_name" value="<?php echo esc_attr(get_option('flosc_product_name', '')); ?>" class="regular-text" placeholder="e.g., LeSAEp">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_product_tagline">Tagline</label></th>
        <td>
            <input type="text" id="flosc_product_tagline" name="flosc_product_tagline" value="<?php echo esc_attr(get_option('flosc_product_tagline', '')); ?>" class="regular-text" placeholder="e.g., Your AI pronunciation coach">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_product_emoji">Logo Emoji</label></th>
        <td>
            <input type="text" id="flosc_product_emoji" name="flosc_product_emoji" value="<?php echo esc_attr(get_option('flosc_product_emoji', '🎯')); ?>" class="small-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_primary_color">Primary Color</label></th>
        <td>
            <input type="color" id="flosc_primary_color" name="flosc_primary_color" value="<?php echo esc_attr(get_option('flosc_primary_color', '#4f46e5')); ?>">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_ga4_id">Google Analytics ID</label></th>
        <td>
            <input type="text" id="flosc_ga4_id" name="flosc_ga4_id" value="<?php echo esc_attr(get_option('flosc_ga4_id', '')); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
        </td>
    </tr>
</table>

<!-- Maintenance Tools -->
<h3 style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">🔧 Maintenance Tools</h3>
<p class="description">Quick actions to keep FLOSC running smoothly.</p>

<table class="form-table">
    <tr>
        <th scope="row">Flush Permalinks</th>
        <td>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=flosc_flush_permalinks'), 'flosc_flush_permalinks'); ?>" class="button">
                🔄 Flush Permalinks
            </a>
            <p class="description">Run this after plugin updates or if your app URL stops working.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Plugin Version</th>
        <td>
            <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px;">v<?php echo FLOSC_VERSION; ?></code>
            <p class="description">Current FLOSC version installed.</p>
        </td>
    </tr>
</table>
