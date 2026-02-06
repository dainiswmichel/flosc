<?php
/**
 * Flow Edit Tab: IVR
 * 
 * Conversation messages and content configuration
 */

if (!defined('ABSPATH')) exit;
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <h2 class="flosc-section-header">IVR Configuration</h2>
    <p class="description">Select the IVR file that contains this flow's conversation messages.</p>
    
    <div class="flosc-form-field">
        <label for="ivr_file">IVR File</label>
        <select id="ivr_file" name="ivr_file">
            <option value="">— Use Global Default —</option>
            <?php foreach ($ivr_files as $file): ?>
                <option value="<?php echo esc_attr($file); ?>" <?php selected($flow['ivr_file'] ?? '', $file); ?>>
                    <?php echo esc_html($file); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            Files are located in <code>ai_configuration_files/</code><br>
            Use naming convention: <code>{flowname}_ivr.md</code> (e.g., <code>lesaep_ivr.md</code>)
        </p>
    </div>
    
    <h2 class="flosc-section-header">Content Configuration</h2>
    
    <div class="flosc-form-field">
        <label for="wp_category">Lessons Category</label>
        <select id="wp_category" name="wp_category">
            <option value="0">— Use Global Default —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($flow['wp_category_id'] ?? 0, $cat->term_id); ?>>
                    <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?> posts)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Only lessons in this category will be available in this flow.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="quiz_type">Quiz Type</label>
        <select id="quiz_type" name="quiz_type">
            <?php foreach ($quiz_types as $type_id => $type_label): ?>
                <option value="<?php echo esc_attr($type_id); ?>" <?php selected($flow['quiz_type'] ?? '', $type_id); ?>>
                    <?php echo esc_html($type_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">The type of quiz used for the FLOSC funnel in this flow.</p>
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
    
    <hr style="margin: 30px 0;">
    
    <h3>Edit IVR Messages</h3>
    <p>
        <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ivr-messages'); ?>" class="button">
            Open IVR Message Editor →
        </a>
    </p>
    <p class="description">The IVR editor currently edits the selected IVR file's messages.</p>
</form>
