<?php
/**
 * Flow Edit Tab: Style
 * 
 * Chat appearance customization
 */

if (!defined('ABSPATH')) exit;

// Get current flow values
$style = $flow['style'] ?? [];

// Bubble style presets
$bubble_styles = [
    '' => '— Use Global —',
    'subtle-notch' => 'Subtle Notch',
    'classic' => 'Classic Triangle',
    'modern' => 'Modern Curve',
    'minimal' => 'Minimal (No Tail)',
    'sharp' => 'Sharp Corner',
];

$font_options = [
    '' => '— Use Global —',
    'system' => 'System Default',
    'inter' => 'Inter',
    'ibm-plex-sans' => 'IBM Plex Sans',
    'ibm-plex-mono' => 'IBM Plex Mono',
    'roboto' => 'Roboto',
    'roboto-mono' => 'Roboto Mono',
    'fira-code' => 'Fira Code',
];

$preset_options = [
    '' => '— Use Global —',
    'auto' => '🖥️ Auto (System)',
    'light' => '☀️ Light',
    'dark' => '🌙 Dark',
];
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <h2 class="flosc-section-header">Chat Appearance</h2>
    <p class="description">Customize the chat interface for this flow. Leave empty to use global settings.</p>
    
    <div class="flosc-form-field">
        <label for="style_preset">Theme</label>
        <select id="style_preset" name="style_preset" style="max-width: 250px;">
            <?php foreach ($preset_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($style['preset'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($style['preset'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('style.preset', 'light')); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <div class="flosc-form-field">
        <label for="style_bubble">Bubble Style</label>
        <select id="style_bubble" name="style_bubble" style="max-width: 250px;">
            <?php foreach ($bubble_styles as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($style['bubble'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($style['bubble'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('style.bubble', 'subtle-notch')); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <div class="flosc-form-field">
        <label for="style_accent">Accent Color</label>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="color" id="style_accent" name="style_accent" 
                   value="<?php echo esc_attr($style['accent'] ?: flosc_get_global_setting('style.accent', '#2563eb')); ?>" 
                   style="width: 60px; height: 40px; padding: 0; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" id="style_accent_hex" 
                   value="<?php echo esc_attr($style['accent'] ?? ''); ?>" 
                   style="width: 100px; max-width: 100px;" 
                   placeholder="<?php echo flosc_global_placeholder('style.accent'); ?>">
            <?php if (!empty($style['accent'])): ?>
                <button type="button" class="button flosc-clear-field" data-field="style_accent" data-hex="style_accent_hex">Clear</button>
            <?php endif; ?>
        </div>
        <p class="description">User bubble color, buttons, and interactive elements.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="style_font">Font</label>
        <select id="style_font" name="style_font" style="max-width: 250px;">
            <?php foreach ($font_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($style['font'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($style['font'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('style.font', 'system')); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <div class="flosc-form-field">
        <label for="style_scale">Text Size</label>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="range" id="style_scale" name="style_scale" 
                   min="100" max="150" step="10" 
                   value="<?php echo esc_attr($style['scale'] !== '' ? $style['scale'] : flosc_get_global_setting('style.scale', 100)); ?>" 
                   style="width: 200px;">
            <span id="scale_display" style="font-weight: 600; min-width: 50px;">
                <?php echo esc_html($style['scale'] !== '' ? $style['scale'] : flosc_get_global_setting('style.scale', 100)); ?>%
            </span>
            <?php if ($style['scale'] !== ''): ?>
                <button type="button" class="button flosc-clear-field" data-field="style_scale" data-default="<?php echo flosc_get_global_setting('style.scale', 100); ?>">Use Global</button>
            <?php endif; ?>
        </div>
        <p class="description">Increase text size for better readability. 100% is browser default.</p>
    </div>
    
    <h2 class="flosc-section-header">Custom CSS</h2>
    
    <div class="flosc-form-field">
        <label for="style_custom_css">Custom CSS</label>
        <textarea id="style_custom_css" name="style_custom_css" rows="10" 
                  style="font-family: monospace; font-size: 13px; width: 100%; max-width: 100%;"
                  placeholder="/* Override any style for this flow */&#10;:root {&#10;    --flosc-accent: #your-color;&#10;}"><?php echo esc_textarea($style['custom_css'] ?? ''); ?></textarea>
        <p class="description">Advanced: Add custom CSS specific to this flow. Overrides global custom CSS.</p>
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
</form>

<script>
jQuery(document).ready(function($) {
    // Scale display
    $('#style_scale').on('input', function() {
        $('#scale_display').text(this.value + '%');
    });
    
    // Color picker sync
    $('#style_accent').on('input', function() {
        $('#style_accent_hex').val(this.value);
    });
    
    $('#style_accent_hex').on('input', function() {
        var hex = this.value;
        if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
            $('#style_accent').val(hex);
        }
    });
    
    // Clear field buttons
    $('.flosc-clear-field').on('click', function() {
        var field = $(this).data('field');
        var hex = $(this).data('hex');
        var defaultVal = $(this).data('default');
        
        $('#' + field).val(defaultVal || '');
        if (hex) {
            $('#' + hex).val('');
        }
        $(this).hide();
    });
});
</script>
