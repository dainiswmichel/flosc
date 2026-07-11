<?php
/**
 * FLOSC Chat Styling Tab (v1.2.9)
 * 
 * Clean architecture with:
 * - Structured theme preset (Auto/Light/Dark)
 * - Bubble geometry (Subtle Notch, Classic, Modern, Minimal, Sharp)
 * - Accent color cascade
 * - Font family
 * - Text scaling
 * - CSS variables generated from structured controls
 */

if (!defined('ABSPATH')) exit;

// v8.0.1: Output tab header
flosc_tab_header('🎨', 'Style & Nav');

$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_style_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-style';

?>
<div class="flosc-chat-style-docs-wrap">
    <a href="<?php echo esc_url($flosc_style_docs_url); ?>" class="flosc-chat-style-docs-link">Docs</a>
</div>

<div class="flosc-chat-style-hero">
    <div class="flosc-chat-style-hero-inner">
        <div class="flosc-chat-style-hero-copy">
            <div class="flosc-chat-style-eyebrow">Style system</div>
            <h2 class="flosc-chat-style-hero-title">FLOSC Signature Template</h2>
            <p class="flosc-chat-style-hero-text">Shape the chat surface with structured controls only. The preview below updates as you adjust the theme, bubble geometry, accent, font, and scale.</p>
        </div>
        <div class="flosc-chat-style-chip-row">
            <span class="flosc-chat-style-chip flosc-chat-style-chip--blue">Structured only</span>
            <span class="flosc-chat-style-chip">Live preview</span>
            <span class="flosc-chat-style-chip">CSS variables</span>
        </div>
    </div>
</div>

<?php

// Handle reset action (runs inside the parent settings form)
$flosc_reset_nonce = isset($_POST['flosc_reset_chat_style_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['flosc_reset_chat_style_nonce'])) : '';
if (isset($_POST['flosc_reset_chat_style']) && wp_verify_nonce($flosc_reset_nonce, 'flosc_reset_chat_style_nonce')) {
    $flosc_reset_key = $GLOBALS['flosc_settings_key'] ?? '';
    if ($flosc_reset_key) {
        $flosc_fs = get_option($flosc_reset_key, []);
        unset($flosc_fs['chat_style_preset'], $flosc_fs['chat_style_bubble'], $flosc_fs['chat_style_accent'], $flosc_fs['chat_style_font'], $flosc_fs['chat_style_scale']);
        update_option($flosc_reset_key, $flosc_fs);
        $GLOBALS['flosc_current_settings'] = $flosc_fs;
    }
    echo '<div class="notice notice-success"><p>✓ Chat styling reset to defaults.</p></div>';
}

// Get current values (defaults match enqueue_chat_style)
$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_preset = $flosc_flow_settings['chat_style_preset'] ?? 'light';
$flosc_current_bubble = $flosc_flow_settings['chat_style_bubble'] ?? 'subtle-notch';
$flosc_current_accent = $flosc_flow_settings['chat_style_accent'] ?? '#2563eb';
$flosc_current_font = $flosc_flow_settings['chat_style_font'] ?? 'system';
$flosc_current_scale = $flosc_flow_settings['chat_style_scale'] ?? 100;

// Bubble style presets
$flosc_bubble_styles = [
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

<!-- Reset Button (uses parent settings form) -->
<div class="flosc-chat-style-reset-form">
    <?php wp_nonce_field('flosc_reset_chat_style_nonce', 'flosc_reset_chat_style_nonce'); ?>
    <button type="submit" name="flosc_reset_chat_style" class="button flosc-chat-style-reset-btn" data-confirm-message="Reset all styling to defaults?">
        ↺ Reset to Default
    </button>
</div>

<div class="flosc-chat-style-panel">
<table class="form-table flosc-chat-style-form-table">

    <!-- Theme Preset -->
    <tr>
        <th scope="row"><label for="flow_chat_style_preset">Theme</label></th>
        <td>
            <select name="flow_chat_style_preset" id="flow_chat_style_preset" class="regular-text">
                <option value="auto" <?php selected($flosc_current_preset, 'auto'); ?>>🖥️ Auto (System)</option>
                <option value="light" <?php selected($flosc_current_preset, 'light'); ?>>☀️ Light</option>
                <option value="dark" <?php selected($flosc_current_preset, 'dark'); ?>>🌙 Dark</option>
            </select>
            <p class="description">Choose <strong>Auto</strong> to match the user's system preference. Light is the FLOSC Signature Template base, and Dark keeps the same geometry with a darker surface.</p>
        </td>
    </tr>

    <!-- Bubble Style -->
    <tr>
        <th scope="row"><label for="flow_chat_style_bubble">Bubble Style</label></th>
        <td>
            <select name="flow_chat_style_bubble" id="flow_chat_style_bubble" class="regular-text">
                <?php foreach ($flosc_bubble_styles as $flosc_key => $flosc_style): ?>
                    <option value="<?php echo esc_attr($flosc_key); ?>" <?php selected($flosc_current_bubble, $flosc_key); ?>>
                        <?php echo esc_html($flosc_style['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Message bubble geometry. Affects both user and assistant messages.</p>
            
            <!-- Bubble Preview -->
            <div class="flosc-chat-style-preview-card">
                <div class="flosc-chat-style-preview-head">
                    <span>Bubble preview</span>
                    <span>Live</span>
                </div>
                <div class="flosc-chat-style-preview-row flosc-chat-style-preview-row--top">
                    <div class="flosc-chat-style-preview-avatar flosc-chat-style-preview-avatar--user"></div>
                    <div class="flosc-chat-style-preview-col">
                        <div class="flosc-chat-style-preview-label">Visitor</div>
                        <div id="bubble-preview-user" class="flosc-chat-style-preview-user-bubble">User message</div>
                    </div>
                </div>
                <div class="flosc-chat-style-preview-row">
                    <div class="flosc-chat-style-preview-avatar flosc-chat-style-preview-avatar--assistant"></div>
                    <div class="flosc-chat-style-preview-col">
                        <div class="flosc-chat-style-preview-label">Guide</div>
                        <div id="bubble-preview-assistant" class="flosc-chat-style-preview-assistant-bubble">Assistant message</div>
                    </div>
                </div>
            </div>
        </td>
    </tr>

    <!-- Accent Color -->
    <tr>
        <th scope="row"><label for="flow_chat_style_accent">Accent Color</label></th>
        <td>
            <input type="color" name="flow_chat_style_accent" id="flow_chat_style_accent" value="<?php echo esc_attr($flosc_current_accent); ?>" class="flosc-chat-style-color-input">
            <input type="text" id="flow_chat_style_accent_hex" value="<?php echo esc_attr($flosc_current_accent); ?>" class="flosc-chat-style-accent-hex" readonly>
            <p class="description">Controls the active accent across user bubbles, the send button, links, hover states, and quiz highlights. Sets <code>--flosc-accent</code>.</p>
            
            <!-- Quick color swatches -->
            <div class="flosc-chat-style-swatches">
                <?php 
                $flosc_swatches = ['#2563eb', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#1d4ed8'];
                foreach ($flosc_swatches as $flosc_color): ?>
                    <button type="button" class="color-swatch flosc-chat-style-swatch <?php echo ($flosc_color === $flosc_current_accent) ? 'is-active' : ''; ?>" data-color="<?php echo esc_attr($flosc_color); ?>" title="<?php echo esc_attr($flosc_color); ?>"></button>
                <?php endforeach; ?>
            </div>
        </td>
    </tr>

    <!-- Font Family -->
    <tr>
        <th scope="row"><label for="flow_chat_style_font">Font</label></th>
        <td>
            <select name="flow_chat_style_font" id="flow_chat_style_font" class="regular-text">
                <option value="system" <?php selected($flosc_current_font, 'system'); ?>>System Default</option>
                <option value="inter" <?php selected($flosc_current_font, 'inter'); ?>>Inter</option>
                <option value="ibm-plex-sans" <?php selected($flosc_current_font, 'ibm-plex-sans'); ?>>IBM Plex Sans</option>
                <option value="ibm-plex-mono" <?php selected($flosc_current_font, 'ibm-plex-mono'); ?>>IBM Plex Mono</option>
                <option value="roboto" <?php selected($flosc_current_font, 'roboto'); ?>>Roboto</option>
                <option value="roboto-mono" <?php selected($flosc_current_font, 'roboto-mono'); ?>>Roboto Mono</option>
                <option value="fira-code" <?php selected($flosc_current_font, 'fira-code'); ?>>Fira Code</option>
            </select>
            <p class="description">Choose a system-safe default or a documented web font. Monospace options help distinguish similar characters like I, l, 1.</p>
        </td>
    </tr>

    <!-- Text Scaling -->
    <tr>
        <th scope="row"><label for="flow_chat_style_scale">Text Size</label></th>
        <td>
            <input type="range" name="flow_chat_style_scale" id="flow_chat_style_scale" 
                min="100" max="150" step="10" 
                value="<?php echo esc_attr($flosc_current_scale); ?>" 
                class="flosc-chat-style-scale-range">
            <span id="scale_value" class="flosc-chat-style-scale-value"><?php echo esc_html($flosc_current_scale); ?>%</span>
            <p class="description">Increase text size for better readability. 100% matches the browser default baseline.</p>
        </td>
    </tr>

</table>
</div>

<!-- CSS Variable Reference -->
<div class="flosc-chat-style-vars-box">
    <h3 class="flosc-chat-style-vars-title">CSS Variable Reference</h3>
    <p class="flosc-chat-style-vars-copy">Use these variables in WordPress Additional CSS or a child theme when you need to fine-tune the structured template outside this admin screen.</p>
    <div class="flosc-chat-style-vars-grid">
        <div>
            <strong class="flosc-chat-style-vars-heading">Messages</strong><br>
            <code>--flosc-user-message-bg</code><br>
            <code>--flosc-user-message-text</code><br>
            <code>--flosc-user-message-radius</code><br>
            <code>--flosc-assistant-message-bg</code><br>
            <code>--flosc-assistant-message-text</code>
        </div>
        <div>
            <strong class="flosc-chat-style-vars-heading">Global</strong><br>
            <code>--flosc-bg</code><br>
            <code>--flosc-text</code><br>
            <code>--flosc-accent</code><br>
            <code>--flosc-border</code>
        </div>
    </div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    // Bubble style data
    var bubbleStyles = <?php echo wp_json_encode($flosc_bubble_styles); ?>;
    
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
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
