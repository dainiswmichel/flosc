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
 */

if (!defined('ABSPATH')) exit;
?>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_app_slug">App URL Slug</label></th>
        <td>
            <input type="text" id="flosc_app_slug" name="flosc_app_slug" value="<?php echo esc_attr(get_option('flosc_app_slug', 'app')); ?>" class="regular-text">
            <p class="description">Your app will be at: <?php echo esc_html(home_url('/')); ?><strong>[slug]</strong>/</p>
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
