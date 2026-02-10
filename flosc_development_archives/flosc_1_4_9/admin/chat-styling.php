<?php
/**
 * FLOSC Chat Styling Tab (v1.2.9)
 * 
 * Clean architecture with:
 * - Theme preset (Auto/Light/Dark)
 * - Bubble style (Subtle Notch, Classic, Modern, Minimal, Sharp)
 * - Accent color
 * - Font family
 * - Text scaling
 * - Custom CSS
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('🎨', 'Style');

// Handle reset action
if (isset($_POST['flosc_reset_chat_style']) && check_admin_referer('flosc_reset_chat_style_nonce')) {
    $reset_key = $GLOBALS['flosc_settings_key'] ?? '';
    if ($reset_key) {
        $fs = get_option($reset_key, []);
        unset($fs['chat_style_preset'], $fs['chat_style_bubble'], $fs['chat_style_accent'], $fs['chat_style_font'], $fs['chat_style_scale'], $fs['chat_style_custom_css']);
        update_option($reset_key, $fs);
        $GLOBALS['flosc_current_settings'] = $fs;
    }
    echo '<div class="notice notice-success"><p>✓ Chat styling reset to defaults.</p></div>';
}

// Get current values (defaults match enqueue_chat_style)
$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$current_preset = $flow_settings['chat_style_preset'] ?? 'light';
$current_bubble = $flow_settings['chat_style_bubble'] ?? 'subtle-notch';
$current_accent = $flow_settings['chat_style_accent'] ?? '#2563eb';
$current_font = $flow_settings['chat_style_font'] ?? 'system';
$current_scale = $flow_settings['chat_style_scale'] ?? 100;
$custom_css = $flow_settings['chat_style_custom_css'] ?? '';

// Bubble style presets
$bubble_styles = [
    'subtle-notch' => [
        'name' => 'Subtle Notch',
        'user' => '18px 18px 4px 18px',
        'assistant' => '4px 18px 18px 18px',
        'border' => '1px solid rgba(0,0,0,0.08)',
    ],
    'classic' => [
        'name' => 'Classic Triangle',
        'user' => '18px 18px 0 18px',
        'assistant' => '0 18px 18px 18px',
        'border' => '1px solid rgba(0,0,0,0.1)',
    ],
    'modern' => [
        'name' => 'Modern Curve',
        'user' => '20px 20px 6px 20px',
        'assistant' => '6px 20px 20px 20px',
        'border' => '1px solid rgba(0,0,0,0.06)',
    ],
    'minimal' => [
        'name' => 'Minimal (No Tail)',
        'user' => '16px',
        'assistant' => '16px',
        'border' => '1px solid rgba(0,0,0,0.05)',
    ],
    'sharp' => [
        'name' => 'Sharp Corner',
        'user' => '12px 12px 2px 12px',
        'assistant' => '2px 12px 12px 12px',
        'border' => '1px solid rgba(0,0,0,0.12)',
    ],
];
?>

<h2>Chat Style Settings</h2>
<p class="description">Customize the appearance of your chat interface. Changes apply to all users.</p>

<!-- Reset Button -->
<form method="post" style="margin-bottom: 24px;">
    <?php wp_nonce_field('flosc_reset_chat_style_nonce'); ?>
    <button type="submit" name="flosc_reset_chat_style" class="button" onclick="return confirm('Reset all styling to defaults?');">
        ↺ Reset to Default
    </button>
</form>

<table class="form-table">

    <!-- Theme Preset -->
    <tr>
        <th scope="row"><label for="flow_chat_style_preset">Theme</label></th>
        <td>
            <select name="flow_chat_style_preset" id="flow_chat_style_preset" class="regular-text">
                <option value="auto" <?php selected($current_preset, 'auto'); ?>>🖥️ Auto (System)</option>
                <option value="light" <?php selected($current_preset, 'light'); ?>>☀️ Light</option>
                <option value="dark" <?php selected($current_preset, 'dark'); ?>>🌙 Dark</option>
            </select>
            <p class="description">Choose <strong>Auto</strong> to match the user's system preference. Light = white background. Dark = dark background.</p>
        </td>
    </tr>

    <!-- Bubble Style -->
    <tr>
        <th scope="row"><label for="flow_chat_style_bubble">Bubble Style</label></th>
        <td>
            <select name="flow_chat_style_bubble" id="flow_chat_style_bubble" class="regular-text">
                <?php foreach ($bubble_styles as $key => $style): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_bubble, $key); ?>>
                        <?php echo esc_html($style['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Message bubble corner style. Affects both user and assistant messages.</p>
            
            <!-- Bubble Preview -->
            <div style="margin-top: 16px; padding: 16px; background: #f9fafb; border-radius: 8px; max-width: 400px;">
                <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 24px; height: 24px; background: #2563eb; border-radius: 50%;"></div>
                    <div id="bubble-preview-user" style="background: #2563eb; color: white; padding: 8px 14px; border-radius: 18px 18px 4px 18px; font-size: 14px;">User message</div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <div style="width: 24px; height: 24px; background: #e5e7eb; border-radius: 50%;"></div>
                    <div id="bubble-preview-assistant" style="background: transparent; border: 1px solid rgba(0,0,0,0.08); padding: 8px 14px; border-radius: 4px 18px 18px 18px; font-size: 14px;">Assistant message</div>
                </div>
            </div>
        </td>
    </tr>

    <!-- Accent Color -->
    <tr>
        <th scope="row"><label for="flow_chat_style_accent">Accent Color</label></th>
        <td>
            <input type="color" name="flow_chat_style_accent" id="flow_chat_style_accent" value="<?php echo esc_attr($current_accent); ?>" style="width: 60px; height: 36px; padding: 0; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" id="flow_chat_style_accent_hex" value="<?php echo esc_attr($current_accent); ?>" style="width: 100px; margin-left: 8px;" readonly>
            <p class="description">User bubble color, send button, and interactive elements.</p>
            
            <!-- Quick color swatches -->
            <div style="margin-top: 8px; display: flex; gap: 6px;">
                <?php 
                $swatches = ['#2563eb', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#1d4ed8'];
                foreach ($swatches as $color): ?>
                    <button type="button" class="color-swatch" data-color="<?php echo $color; ?>" 
                        style="width: 28px; height: 28px; border-radius: 4px; border: 2px solid <?php echo ($color === $current_accent) ? '#000' : 'transparent'; ?>; 
                        background: <?php echo $color; ?>; cursor: pointer;" title="<?php echo $color; ?>"></button>
                <?php endforeach; ?>
            </div>
        </td>
    </tr>

    <!-- Font Family -->
    <tr>
        <th scope="row"><label for="flow_chat_style_font">Font</label></th>
        <td>
            <select name="flow_chat_style_font" id="flow_chat_style_font" class="regular-text">
                <option value="system" <?php selected($current_font, 'system'); ?>>System Default</option>
                <option value="inter" <?php selected($current_font, 'inter'); ?>>Inter</option>
                <option value="ibm-plex-sans" <?php selected($current_font, 'ibm-plex-sans'); ?>>IBM Plex Sans</option>
                <option value="ibm-plex-mono" <?php selected($current_font, 'ibm-plex-mono'); ?>>IBM Plex Mono</option>
                <option value="roboto" <?php selected($current_font, 'roboto'); ?>>Roboto</option>
                <option value="roboto-mono" <?php selected($current_font, 'roboto-mono'); ?>>Roboto Mono</option>
                <option value="fira-code" <?php selected($current_font, 'fira-code'); ?>>Fira Code</option>
            </select>
            <p class="description">Monospace fonts (Mono) help distinguish similar characters like I, l, 1.</p>
        </td>
    </tr>

    <!-- Text Scaling -->
    <tr>
        <th scope="row"><label for="flow_chat_style_scale">Text Size</label></th>
        <td>
            <input type="range" name="flow_chat_style_scale" id="flow_chat_style_scale" 
                min="100" max="150" step="10" 
                value="<?php echo esc_attr($current_scale); ?>" 
                style="width: 200px; vertical-align: middle;">
            <span id="scale_value" style="margin-left: 10px; font-weight: 600; min-width: 50px; display: inline-block;"><?php echo $current_scale; ?>%</span>
            <p class="description">Increase text size for better readability. 100% is browser default.</p>
        </td>
    </tr>

    <!-- Custom CSS -->
    <tr>
        <th scope="row"><label for="flow_chat_style_custom_css">Custom CSS</label></th>
        <td>
            <textarea name="flow_chat_style_custom_css" id="flow_chat_style_custom_css" 
                rows="8" class="large-text code" 
                placeholder="/* Override any style */&#10;:root {&#10;    --flosc-accent: #your-color;&#10;}"
                style="font-family: 'SF Mono', Monaco, monospace; font-size: 13px;"><?php echo esc_textarea($custom_css); ?></textarea>
            <p class="description">Advanced: Add custom CSS to override any style. See variable reference below.</p>
        </td>
    </tr>

</table>

<!-- CSS Variable Reference -->
<div style="background: #f0f6ff; border: 1px solid #c3dafe; border-radius: 8px; padding: 20px; margin: 30px 0; max-width: 700px;">
    <h3 style="margin-top: 0; color: #1e40af;">📖 CSS Variable Reference</h3>
    <p style="margin-bottom: 16px; color: #374151;">Use these in Custom CSS to override specific elements:</p>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-family: monospace; font-size: 13px;">
        <div>
            <strong style="color: #1e40af;">Messages</strong><br>
            <code>--flosc-user-message-bg</code><br>
            <code>--flosc-user-message-text</code><br>
            <code>--flosc-user-message-radius</code><br>
            <code>--flosc-assistant-message-bg</code><br>
            <code>--flosc-assistant-message-text</code>
        </div>
        <div>
            <strong style="color: #1e40af;">Global</strong><br>
            <code>--flosc-bg</code><br>
            <code>--flosc-text</code><br>
            <code>--flosc-accent</code><br>
            <code>--flosc-border</code>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Bubble style data
    var bubbleStyles = <?php echo json_encode($bubble_styles); ?>;
    
    // Update bubble preview
    function updateBubblePreview() {
        var style = bubbleStyles[$('#flosc_chat_style_bubble').val()];
        var accent = $('#flosc_chat_style_accent').val();
        
        $('#bubble-preview-user').css({
            'border-radius': style.user,
            'background': accent
        });
        $('#bubble-preview-assistant').css({
            'border-radius': style.assistant,
            'border': style.border
        });
    }
    
    // Update scale display
    $('#flosc_chat_style_scale').on('input', function() {
        $('#scale_value').text(this.value + '%');
    });
    
    // Bubble style change
    $('#flosc_chat_style_bubble').on('change', updateBubblePreview);
    
    // Accent color sync
    $('#flosc_chat_style_accent').on('input', function() {
        $('#flosc_chat_style_accent_hex').val(this.value);
        updateBubblePreview();
        // Update swatch borders
        $('.color-swatch').css('border-color', 'transparent');
        $('.color-swatch[data-color="' + this.value + '"]').css('border-color', '#000');
    });
    
    // Quick swatches
    $('.color-swatch').on('click', function() {
        var color = $(this).data('color');
        $('#flosc_chat_style_accent').val(color).trigger('input');
    });
    
    // Initialize
    updateBubblePreview();
});
</script>
