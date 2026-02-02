<?php
/**
 * FLOSC Chat Styling Tab
 * v9.3.6: Added Reset to Default, improved live preview
 * 
 * Customize chat interface appearance including:
 * - Style presets (FLOSC/Grok/Claude/ChatGPT/Custom)
 * - Font families (monospace options for clarity)
 * - Text scaling (100-200% for accessibility)
 * - Color themes (professional, subtle options)
 * - Custom CSS override
 * - Live preview
 */

if (!defined('ABSPATH')) exit;

// Handle reset action
if (isset($_POST['flosc_reset_chat_style']) && check_admin_referer('flosc_reset_chat_style_nonce')) {
    delete_option('flosc_chat_style_preset');
    delete_option('flosc_chat_style_font');
    delete_option('flosc_chat_style_scale');
    delete_option('flosc_chat_style_theme');
    delete_option('flosc_chat_style_custom_css');
    echo '<div class="notice notice-success"><p>Chat styling reset to defaults.</p></div>';
}

$current_preset = get_option('flosc_chat_style_preset', 'flosc');
$current_font = get_option('flosc_chat_style_font', 'system');
$current_scale = get_option('flosc_chat_style_scale', 112);
$current_theme = get_option('flosc_chat_style_theme', 'default');
$custom_css = get_option('flosc_chat_style_custom_css', '');
?>

<h2>Chat Style Settings</h2>
<p class="description">Customize the appearance of your chat interface with professional presets and custom options.</p>

<!-- Reset to Default -->
<form method="post" style="margin-bottom: 20px;">
    <?php wp_nonce_field('flosc_reset_chat_style_nonce'); ?>
    <button type="submit" name="flosc_reset_chat_style" class="button" onclick="return confirm('Reset all chat styling to defaults?');">
        ↺ Reset to Default
    </button>
</form>

<table class="form-table">
    <!-- Style Preset -->
    <tr>
        <th scope="row"><label for="flosc_chat_style_preset">Style Preset</label></th>
        <td>
            <select name="flosc_chat_style_preset" id="flosc_chat_style_preset" class="regular-text">
                <option value="day" <?php selected($current_preset, 'day'); ?>>Day (Light)</option>
                <option value="night" <?php selected($current_preset, 'night'); ?>>Night (Dark)</option>
                <option value="custom" <?php selected($current_preset, 'custom'); ?>>Custom</option>
            </select>
            <p class="description">Day/Night are clean defaults. Custom is your editable canvas.</p>
        </td>
    </tr>

    <!-- Font Family -->
    <tr>
        <th scope="row"><label for="flosc_chat_style_font">Font Family</label></th>
        <td>
            <select name="flosc_chat_style_font" id="flosc_chat_style_font" class="regular-text">
                <option value="system" <?php selected($current_font, 'system'); ?>>System Default</option>
                <option value="ibm-plex-mono" <?php selected($current_font, 'ibm-plex-mono'); ?>>IBM Plex Mono</option>
                <option value="source-code-pro" <?php selected($current_font, 'source-code-pro'); ?>>Source Code Pro</option>
                <option value="inter" <?php selected($current_font, 'inter'); ?>>Inter</option>
                <option value="roboto-mono" <?php selected($current_font, 'roboto-mono'); ?>>Roboto Mono</option>
                <option value="fira-code" <?php selected($current_font, 'fira-code'); ?>>Fira Code</option>
            </select>
            <p class="description">Monospace fonts clearly differentiate I, l, and 1 characters.</p>
        </td>
    </tr>

    <!-- Text Scaling -->
    <tr>
        <th scope="row"><label for="flosc_chat_style_scale">Text Scaling</label></th>
        <td>
            <input type="range" name="flosc_chat_style_scale" id="flosc_chat_style_scale" min="100" max="200" step="10" value="<?php echo esc_attr($current_scale); ?>" style="width: 300px; vertical-align: middle;">
            <span id="scale_value" style="margin-left: 10px; font-weight: 600;"><?php echo $current_scale; ?>%</span>
            <p class="description">Make text larger for better readability (100%-200%). Never shrink text below 100%.</p>
            <script>
                document.getElementById('flosc_chat_style_scale').addEventListener('input', function(e) {
                    document.getElementById('scale_value').textContent = e.target.value + '%';
                });
            </script>
        </td>
    </tr>

    <!-- Color Theme -->
    <tr>
        <th scope="row"><label for="flosc_chat_style_theme">Color Theme</label></th>
        <td>
            <select name="flosc_chat_style_theme" id="flosc_chat_style_theme" class="regular-text">
                <option value="default" <?php selected($current_theme, 'default'); ?>>Default</option>
                <option value="claude-tan" <?php selected($current_theme, 'claude-tan'); ?>>Claude Tan</option>
                <option value="ocean" <?php selected($current_theme, 'ocean'); ?>>Ocean</option>
                <option value="forest" <?php selected($current_theme, 'forest'); ?>>Forest</option>
                <option value="sunset" <?php selected($current_theme, 'sunset'); ?>>Sunset</option>
                <option value="midnight" <?php selected($current_theme, 'midnight'); ?>>Midnight</option>
            </select>
            <p class="description">Subtle, professional color themes. Themes can be mixed with any preset.</p>
        </td>
    </tr>

    <!-- Custom CSS -->
    <tr>
        <th scope="row"><label for="flosc_chat_style_custom_css">Custom CSS</label></th>
        <td>
            <textarea name="flosc_chat_style_custom_css" id="flosc_chat_style_custom_css" rows="12" class="large-text code" placeholder="/* Add your custom CSS here */&#10;.message-text {&#10;    /* Your styles */&#10;}" style="font-family: monospace; font-size: 13px; line-height: 1.5;"><?php echo esc_textarea($custom_css); ?></textarea>
            <p class="description">Advanced: Add custom CSS to override styles. Supports all CSS3 properties.</p>
        </td>
    </tr>
</table>

<div style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin: 30px 0;">
    <h3 style="margin-top: 0;">Preset Mixing</h3>
    <p style="margin-bottom: 10px;"><strong>You can mix and match:</strong> Select a preset (e.g., ChatGPT), then change the font to IBM Plex Mono and color theme to Ocean. Your custom combination will be saved.</p>
    <p style="margin-bottom: 0;"><strong>Example:</strong> "ChatGPT preset + Inter font + Forest theme + 150% scale" = Your unique style</p>
</div>

<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
    <h3 style="margin-top: 0;">CSS Variable Reference</h3>
    <p>Use these variables in your custom CSS:</p>
    <ul style="font-family: monospace; font-size: 13px; line-height: 1.8;">
        <li><code>--flosc-scale</code> - Current scaling percentage</li>
        <li><code>--flosc-base-font</code> - Base font size</li>
        <li><code>--flosc-user-bg</code> - User message background</li>
        <li><code>--flosc-user-text</code> - User message text color</li>
        <li><code>--flosc-assistant-bg</code> - Assistant message background</li>
        <li><code>--flosc-assistant-text</code> - Assistant message text color</li>
        <li><code>--flosc-border-color</code> - Border colors</li>
        <li><code>--flosc-avatar-size</code> - Avatar dimensions</li>
        <li><code>--flosc-border-radius</code> - Message bubble roundness</li>
    </ul>
</div>

<!-- Live Preview -->
<h3 style="margin-top: 40px;">Live Preview</h3>
<p>See how your selected font and theme will appear in the chat interface.</p>

<div id="flosc-style-preview" style="margin-top: 20px; padding: 20px; border: 2px solid #ccc; border-radius: 8px; max-width: 700px;">
    <link rel="stylesheet" href="<?php echo FLOSC_PLUGIN_URL; ?>assets/css/chat-style-flosc.css">
    
    <div class="messages"
         data-flosc-style-preset="<?php echo esc_attr($current_preset); ?>"
         data-flosc-font="<?php echo esc_attr($current_font); ?>"
         data-flosc-theme="<?php echo esc_attr($current_theme); ?>"
         style="background: var(--flosc-bg); padding: 20px; border-radius: 8px; --flosc-scale: <?php echo esc_attr($current_scale); ?>%;">
        <div class="message assistant">
            <div class="message-avatar">AI</div>
            <div class="message-content">
                <div class="message-text">
                    <p>This is an assistant message showing the current theme and font. Notice the background color and text color.</p>
                </div>
            </div>
        </div>

        <div class="message user">
            <div class="message-avatar">U</div>
            <div class="message-content">
                <div class="message-text">
                    <p>This is a user message showing the blue bubble with white text.</p>
                </div>
            </div>
        </div>

        <div class="message assistant">
            <div class="message-avatar">AI</div>
            <div class="message-content">
                <div class="message-text">
                    <p>You can see the <strong>font family</strong>, <em>text styling</em>, and <code>inline code</code> appearance here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function updatePreview() {
        var font = $('#flosc_chat_style_font').val();
        var theme = $('#flosc_chat_style_theme').val();
        var preset = $('#flosc_chat_style_preset').val();
        var scale = $('#flosc_chat_style_scale').val();
        var $msg = $('#flosc-style-preview .messages');
        $msg.attr('data-flosc-font', font)
            .attr('data-flosc-theme', theme)
            .attr('data-flosc-style-preset', preset)
            .css('--flosc-scale', scale + '%');
    }
    $('#flosc_chat_style_font, #flosc_chat_style_theme, #flosc_chat_style_preset, #flosc_chat_style_scale').on('change input', updatePreview);
});
</script>
