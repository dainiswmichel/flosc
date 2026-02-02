<?php
/**
 * Chat Styling Admin Page
 * v9.0.5: Professional chat design system with presets, scaling, fonts, and colors
 */

if (!defined('ABSPATH')) exit;

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flosc_chat_style'])) {
    if (!wp_verify_nonce($_POST['flosc_chat_style_nonce'] ?? '', 'flosc_save_chat_style')) {
        wp_die('Security check failed');
    }

    // Save settings
    update_option('flosc_chat_style_preset', sanitize_text_field($_POST['flosc_chat_style']));
    update_option('flosc_chat_style_font', sanitize_text_field($_POST['flosc_chat_font'] ?? 'system'));
    update_option('flosc_chat_style_scale', intval($_POST['flosc_chat_scale'] ?? 100));
    update_option('flosc_chat_style_theme', sanitize_text_field($_POST['flosc_chat_theme'] ?? 'default'));
    update_option('flosc_chat_style_custom_css', wp_unslash($_POST['flosc_custom_css'] ?? ''));

    echo '<div class="notice notice-success"><p>Chat styling settings saved!</p></div>';
}

// Load current settings
$current_preset = get_option('flosc_chat_style_preset', 'flosc');
$current_font = get_option('flosc_chat_style_font', 'system');
$current_scale = get_option('flosc_chat_style_scale', 100);
$current_theme = get_option('flosc_chat_style_theme', 'default');
$custom_css = get_option('flosc_chat_style_custom_css', '');
?>

<div class="wrap">
    <h1>Chat Styling</h1>
    <p class="description">Customize the appearance of your chat interface with professional presets and custom options.</p>

    <form method="post" style="max-width: 900px;">
        <?php wp_nonce_field('flosc_save_chat_style', 'flosc_chat_style_nonce'); ?>

        <table class="form-table" role="presentation">
            <!-- Style Preset -->
            <tr>
                <th scope="row">
                    <label for="flosc_chat_style">Style Preset</label>
                </th>
                <td>
                    <select name="flosc_chat_style" id="flosc_chat_style" class="regular-text">
                        <option value="flosc" <?php selected($current_preset, 'flosc'); ?>>FLOSC Default</option>
                        <option value="grok" <?php selected($current_preset, 'grok'); ?>>Grok</option>
                        <option value="claude" <?php selected($current_preset, 'claude'); ?>>Claude</option>
                        <option value="chatgpt" <?php selected($current_preset, 'chatgpt'); ?>>ChatGPT</option>
                        <option value="custom" <?php selected($current_preset, 'custom'); ?>>Custom</option>
                    </select>
                    <p class="description">
                        Choose a preset style. You can override fonts, scaling, and colors below to create custom combinations.
                    </p>
                </td>
            </tr>

            <!-- Font Family -->
            <tr>
                <th scope="row">
                    <label for="flosc_chat_font">Font Family</label>
                </th>
                <td>
                    <select name="flosc_chat_font" id="flosc_chat_font" class="regular-text">
                        <option value="system" <?php selected($current_font, 'system'); ?>>System Default</option>
                        <option value="ibm-plex-mono" <?php selected($current_font, 'ibm-plex-mono'); ?>>IBM Plex Mono</option>
                        <option value="source-code-pro" <?php selected($current_font, 'source-code-pro'); ?>>Source Code Pro</option>
                        <option value="inter" <?php selected($current_font, 'inter'); ?>>Inter</option>
                        <option value="roboto-mono" <?php selected($current_font, 'roboto-mono'); ?>>Roboto Mono</option>
                        <option value="fira-code" <?php selected($current_font, 'fira-code'); ?>>Fira Code</option>
                    </select>
                    <p class="description">
                        Monospace fonts clearly differentiate I, l, and 1 characters.
                    </p>
                </td>
            </tr>

            <!-- Text Scaling -->
            <tr>
                <th scope="row">
                    <label for="flosc_chat_scale">Text Scaling</label>
                </th>
                <td>
                    <input type="range"
                           name="flosc_chat_scale"
                           id="flosc_chat_scale"
                           min="100"
                           max="200"
                           step="10"
                           value="<?php echo esc_attr($current_scale); ?>"
                           style="width: 300px; vertical-align: middle;">
                    <span id="scale_value" style="margin-left: 10px; font-weight: 600;"><?php echo $current_scale; ?>%</span>
                    <p class="description">
                        Make text larger for better readability (100%-200%). Never shrink text below 100%.
                    </p>
                    <script>
                        document.getElementById('flosc_chat_scale').addEventListener('input', function(e) {
                            document.getElementById('scale_value').textContent = e.target.value + '%';
                        });
                    </script>
                </td>
            </tr>

            <!-- Color Theme -->
            <tr>
                <th scope="row">
                    <label for="flosc_chat_theme">Color Theme</label>
                </th>
                <td>
                    <select name="flosc_chat_theme" id="flosc_chat_theme" class="regular-text">
                        <option value="default" <?php selected($current_theme, 'default'); ?>>Default</option>
                        <option value="claude-tan" <?php selected($current_theme, 'claude-tan'); ?>>Claude Tan</option>
                        <option value="ocean" <?php selected($current_theme, 'ocean'); ?>>Ocean</option>
                        <option value="forest" <?php selected($current_theme, 'forest'); ?>>Forest</option>
                        <option value="sunset" <?php selected($current_theme, 'sunset'); ?>>Sunset</option>
                        <option value="midnight" <?php selected($current_theme, 'midnight'); ?>>Midnight</option>
                    </select>
                    <p class="description">
                        Subtle, professional color themes. Themes can be mixed with any preset.
                    </p>
                </td>
            </tr>

            <!-- Custom CSS -->
            <tr>
                <th scope="row">
                    <label for="flosc_custom_css">Custom CSS</label>
                </th>
                <td>
                    <textarea name="flosc_custom_css"
                              id="flosc_custom_css"
                              rows="12"
                              class="large-text code"
                              placeholder="/* Add your custom CSS here */&#10;.message-text {&#10;    /* Your styles */&#10;}"
                              style="font-family: monospace; font-size: 13px; line-height: 1.5;"><?php echo esc_textarea($custom_css); ?></textarea>
                    <p class="description">
                        Advanced: Add custom CSS to override styles. Supports all CSS3 properties.
                    </p>
                </td>
            </tr>
        </table>

        <div style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin: 30px 0;">
            <h3 style="margin-top: 0;">Preset Mixing</h3>
            <p style="margin-bottom: 10px;">
                <strong>You can mix and match:</strong> Select a preset (e.g., ChatGPT), then change the font to IBM Plex Mono
                and color theme to Ocean. Your custom combination will be saved.
            </p>
            <p style="margin-bottom: 0;">
                <strong>Example:</strong> "ChatGPT preset + Inter font + Forest theme + 150% scale" = Your unique style
            </p>
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

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                Save Chat Styling
            </button>
            <a href="<?php echo admin_url('admin.php?page=flosc-chat-style'); ?>"
               class="button"
               style="margin-left: 10px;">
                Cancel
            </a>
        </p>
    </form>

    <!-- Preview Section -->
    <div style="background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 20px; margin-top: 40px;">
        <h2>Preview</h2>
        <p class="description" style="margin-bottom: 20px;">
            Save your settings, then visit your chat page to see the changes live.
            Preview functionality can be added in a future version.
        </p>
    </div>
</div>

<style>
.form-table th {
    width: 180px;
    padding: 15px 10px 15px 0;
}
.form-table td {
    padding: 15px 10px;
}
code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}
</style>
