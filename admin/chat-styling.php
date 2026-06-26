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

// v1.2.9: Output tab header
flosc_tab_header('🎨', 'Style');

$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_style_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-style';

echo '<div style="margin:-8px 0 14px; text-align:right;">'
   . '<a href="' . esc_url($flosc_style_docs_url) . '" style="font-size:12px; text-decoration:none; color:#2271b1;">Docs</a>'
   . '</div>';

echo '<div style="margin: 0 0 20px; padding: 22px 24px; border: 1px solid #dbe7ff; border-radius: 16px; background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);">'
    . '<div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">'
    . '<div style="max-width: 640px;">'
    . '<div style="font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #4f6baf; margin-bottom: 8px;">Style system</div>'
    . '<h2 style="margin: 0 0 10px; font-size: 26px; line-height: 1.15; color: #0f172a;">FLOSC Signature Template</h2>'
    . '<p style="margin: 0; color: #334155; font-size: 14px; line-height: 1.65; max-width: 58ch;">Shape the chat surface with structured controls only. The preview below updates as you adjust the theme, bubble geometry, accent, font, and scale.</p>'
    . '</div>'
    . '<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">'
    . '<span style="padding:6px 10px; border-radius:999px; background:#ffffff; border:1px solid #dbe7ff; color:#1d4ed8; font-size:12px; font-weight:600;">Structured only</span>'
    . '<span style="padding:6px 10px; border-radius:999px; background:#ffffff; border:1px solid #dbe7ff; color:#334155; font-size:12px; font-weight:600;">Live preview</span>'
    . '<span style="padding:6px 10px; border-radius:999px; background:#ffffff; border:1px solid #dbe7ff; color:#334155; font-size:12px; font-weight:600;">CSS variables</span>'
    . '</div>'
    . '</div>'
    . '</div>';

// Handle reset action
if (isset($_POST['flosc_reset_chat_style']) && check_admin_referer('flosc_reset_chat_style_nonce')) {
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

<!-- Reset Button -->
<form method="post" style="margin-bottom: 24px;">
    <?php wp_nonce_field('flosc_reset_chat_style_nonce'); ?>
    <button type="submit" name="flosc_reset_chat_style" class="button" style="border-radius: 999px; padding: 0 16px; height: 38px; border-color: #d1d5db; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);" onclick="return confirm('Reset all styling to defaults?');">
        ↺ Reset to Default
    </button>
</form>

<div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:24px 24px 8px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05); margin-bottom: 24px;">
<table class="form-table" style="margin-top: 0;">

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
            <div style="margin-top: 16px; padding: 18px; background: linear-gradient(180deg, #f9fafb 0%, #ffffff 100%); border: 1px solid #e5e7eb; border-radius: 14px; max-width: 420px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.75);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 14px; color:#64748b; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; font-weight: 700;">
                    <span>Bubble preview</span>
                    <span>Live</span>
                </div>
                <div style="display: flex; gap: 12px; margin-bottom: 12px; align-items: flex-start;">
                    <div style="width: 24px; height: 24px; background: #2563eb; border-radius: 50%; flex: 0 0 auto; margin-top: 2px;"></div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;">Visitor</div>
                        <div id="bubble-preview-user" style="background: #2563eb; color: white; padding: 10px 16px; border-radius: 18px 18px 4px 18px; font-size: 14px; line-height: 1.45;">User message</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="width: 24px; height: 24px; background: #e5e7eb; border-radius: 50%; flex: 0 0 auto; margin-top: 2px;"></div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;">Guide</div>
                        <div id="bubble-preview-assistant" style="background: transparent; border: 1px solid rgba(0,0,0,0.08); padding: 10px 16px; border-radius: 4px 18px 18px 18px; font-size: 14px; line-height: 1.45;">Assistant message</div>
                    </div>
                </div>
            </div>
        </td>
    </tr>

    <!-- Accent Color -->
    <tr>
        <th scope="row"><label for="flow_chat_style_accent">Accent Color</label></th>
        <td>
            <input type="color" name="flow_chat_style_accent" id="flow_chat_style_accent" value="<?php echo esc_attr($flosc_current_accent); ?>" style="width: 60px; height: 36px; padding: 0; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" id="flow_chat_style_accent_hex" value="<?php echo esc_attr($flosc_current_accent); ?>" style="width: 100px; margin-left: 8px;" readonly>
            <p class="description">Controls the active accent across user bubbles, the send button, links, hover states, and quiz highlights. Sets <code>--flosc-accent</code>.</p>
            
            <!-- Quick color swatches -->
            <div style="margin-top: 8px; display: flex; gap: 6px;">
                <?php 
                $flosc_swatches = ['#2563eb', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#1d4ed8'];
                foreach ($flosc_swatches as $flosc_color): ?>
                    <button type="button" class="color-swatch" data-color="<?php echo esc_attr($flosc_color); ?>" 
                        style="width: 28px; height: 28px; border-radius: 4px; border: 2px solid <?php echo esc_attr(($flosc_color === $flosc_current_accent) ? '#000' : 'transparent'); ?>; 
                        background: <?php echo esc_attr($flosc_color); ?>; cursor: pointer;" title="<?php echo esc_attr($flosc_color); ?>"></button>
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
                style="width: 200px; vertical-align: middle;">
            <span id="scale_value" style="margin-left: 10px; font-weight: 600; min-width: 50px; display: inline-block;"><?php echo esc_html($flosc_current_scale); ?>%</span>
            <p class="description">Increase text size for better readability. 100% matches the browser default baseline.</p>
        </td>
    </tr>

</table>
</div>

<!-- CSS Variable Reference -->
<div style="background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%); border: 1px solid #dbe7ff; border-radius: 16px; padding: 22px; margin: 30px 0 0; max-width: 760px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);">
    <h3 style="margin: 0 0 10px; color: #1e3a8a; font-size: 18px;">CSS Variable Reference</h3>
    <p style="margin-bottom: 18px; color: #475569; line-height: 1.6;">Use these variables in WordPress Additional CSS or a child theme when you need to fine-tune the structured template outside this admin screen.</p>
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

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    // Bubble style data
    var bubbleStyles = <?php echo json_encode($flosc_bubble_styles); ?>;
    
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
