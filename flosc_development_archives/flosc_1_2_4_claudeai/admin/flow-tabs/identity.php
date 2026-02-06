<?php
/**
 * Flow Edit Tab: Identity
 * 
 * URL, domain, and product branding configuration
 */

if (!defined('ABSPATH')) exit;
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <h2 class="flosc-section-header">Flow Identity</h2>
    
    <div class="flosc-form-field">
        <label for="flow_slug">URL Slug</label>
        <div style="display: flex; align-items: center; gap: 4px;">
            <code><?php echo home_url('/'); ?></code>
            <input type="text" id="flow_slug" name="flow_slug" 
                   value="<?php echo esc_attr($flow['slug'] ?? ''); ?>" 
                   style="width: 150px; max-width: 150px;"
                   placeholder="myapp"
                   pattern="[a-z0-9-]+">
            <code>/</code>
        </div>
        <p class="description">Lowercase letters, numbers, and hyphens only. Leave empty if using custom domain only.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="flow_domain">Custom Domain</label>
        <input type="text" id="flow_domain" name="flow_domain" 
               value="<?php echo esc_attr($flow['custom_domain'] ?? ''); ?>" 
               placeholder="app.yourdomain.com">
        <p class="description">Optional. Point a domain to this WordPress install and enter it here. Include subdomain if applicable.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="flow_status">Status</label>
        <select id="flow_status" name="flow_status" style="max-width: 200px;">
            <option value="active" <?php selected($flow['status'] ?? 'draft', 'active'); ?>>🟢 Active (Live)</option>
            <option value="draft" <?php selected($flow['status'] ?? 'draft', 'draft'); ?>>⚪ Draft (Not visible)</option>
        </select>
    </div>
    
    <h2 class="flosc-section-header">Product Branding</h2>
    
    <div class="flosc-form-field">
        <label for="product_name">Product Name</label>
        <input type="text" id="product_name" name="product_name" 
               value="<?php echo esc_attr($flow['product']['name'] ?? ''); ?>" 
               placeholder="e.g., LeSAEp, Simplified Solfeggio">
        <p class="description">Displayed in the app header and emails.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="product_tagline">Tagline</label>
        <input type="text" id="product_tagline" name="product_tagline" 
               value="<?php echo esc_attr($flow['product']['tagline'] ?? ''); ?>" 
               placeholder="e.g., Master pronunciation in minutes a day">
        <p class="description">Short description shown below the product name.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="product_emoji">Emoji Icon</label>
        <input type="text" id="product_emoji" name="product_emoji" 
               value="<?php echo esc_attr($flow['product']['emoji'] ?? '🎯'); ?>" 
               style="width: 60px; max-width: 60px; font-size: 24px; text-align: center;">
        <p class="description">Used as favicon and icon when no logo is set.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="product_color">Primary Color</label>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="color" id="product_color" name="product_color" 
                   value="<?php echo esc_attr($flow['product']['primary_color'] ?? '#4f46e5'); ?>" 
                   style="width: 60px; height: 40px; padding: 0; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" id="product_color_hex" 
                   value="<?php echo esc_attr($flow['product']['primary_color'] ?? '#4f46e5'); ?>" 
                   style="width: 100px; max-width: 100px;" readonly>
        </div>
        <p class="description">Brand color used throughout the app.</p>
        
        <!-- Quick color swatches -->
        <div style="margin-top: 8px; display: flex; gap: 6px;">
            <?php 
            $swatches = ['#4f46e5', '#2563eb', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899'];
            foreach ($swatches as $color): ?>
                <button type="button" class="color-swatch" data-color="<?php echo $color; ?>" 
                    style="width: 28px; height: 28px; border-radius: 4px; border: 2px solid transparent; 
                    background: <?php echo $color; ?>; cursor: pointer;" title="<?php echo $color; ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="flosc-form-field">
        <label for="product_logo">Logo URL</label>
        <input type="url" id="product_logo" name="product_logo" 
               value="<?php echo esc_attr($flow['product']['logo_url'] ?? ''); ?>" 
               placeholder="https://...">
        <p class="description">Optional. Used instead of emoji if provided. Recommended: square, at least 200x200px.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="product_share_text">Share Text</label>
        <input type="text" id="product_share_text" name="product_share_text" 
               value="<?php echo esc_attr($flow['product']['share_text'] ?? ''); ?>" 
               placeholder="Check out this app that helped me...">
        <p class="description">Default text when users share the app.</p>
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="<?php echo $is_new ? 'Create Flow' : 'Save Changes'; ?>">
        <a href="<?php echo admin_url('admin.php?page=flosc-flows'); ?>" class="button">Cancel</a>
    </p>
</form>

<script>
jQuery(document).ready(function($) {
    // Color picker sync
    $('#product_color').on('input', function() {
        $('#product_color_hex').val(this.value);
        $('.color-swatch').css('border-color', 'transparent');
        $('.color-swatch[data-color="' + this.value + '"]').css('border-color', '#000');
    });
    
    // Quick swatches
    $('.color-swatch').on('click', function() {
        var color = $(this).data('color');
        $('#product_color').val(color);
        $('#product_color_hex').val(color);
        $('.color-swatch').css('border-color', 'transparent');
        $(this).css('border-color', '#000');
    });
});
</script>
