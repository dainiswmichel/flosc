<?php
/**
 * FLOSC Companion Configuration Tab
 * 
 * Configures the floating Companion chat widget that appears on WordPress
 * pages alongside lesson content. This is "Companion Mode" — the chatbot
 * as a learning companion while members browse lessons as normal WP posts.
 * 
 * Content Display Modes:
 * - In-Chat:    Lessons rendered inside the full chatbot app (default)
 * - Companion:  Floating widget on WP pages, lessons are normal WP posts
 * - Both:       Both modes available — full app shows lessons AND widget appears on WP pages
 * 
 * Settings (per-flow via companion override group):
 * - content_display_mode:  'in_chat' | 'companion' | 'both'
 * - enabled:               Whether the companion widget is active
 * - position:              'bottom-right' | 'bottom-left'
 * - greeting:              Initial greeting message
 * - accent_color:          Custom accent color (hex, or empty for default)
 * - show_for_visitors:     Whether non-logged-in users see the widget
 * 
 * @package FLOSC
 * @since   1.6.0
 */

if (!defined('ABSPATH')) exit;

// Tab header
flosc_tab_header('🤝', 'Companion');

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];

// Read current values with defaults
$content_display_mode = $flow_settings['companion_content_display_mode'] ?? 'in_chat';
$enabled              = $flow_settings['companion_enabled'] ?? '';
$position             = $flow_settings['companion_position'] ?? 'bottom-right';
$greeting             = $flow_settings['companion_greeting'] ?? 'Hi! I\'m your learning companion. Need help with this lesson?';
$accent_color         = $flow_settings['companion_accent_color'] ?? '';
$show_for_visitors    = $flow_settings['companion_show_for_visitors'] ?? '';
?>

<h2>Content Display Mode</h2>
<p>Choose how members access lesson content. This determines whether lessons are viewed inside the chatbot, on your WordPress site, or both.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_companion_content_display_mode">Display Mode</label></th>
        <td>
            <fieldset>
                <label style="display: block; margin-bottom: 12px; padding: 12px; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer; <?php echo $content_display_mode === 'in_chat' ? 'background: #f0f6fc; border-color: #2271b1;' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="in_chat" <?php checked($content_display_mode, 'in_chat'); ?>>
                    <strong>💬 In-Chat Only</strong> <em style="color: #787c82;">(default)</em>
                    <br><span style="color: #50575e; font-size: 12px; margin-left: 24px; display: block; margin-top: 4px;">
                        Lessons are delivered inside the chatbot. Members read content in the chat interface.
                    </span>
                </label>
                
                <label style="display: block; margin-bottom: 12px; padding: 12px; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer; <?php echo $content_display_mode === 'companion' ? 'background: #f0f6fc; border-color: #2271b1;' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="companion" <?php checked($content_display_mode, 'companion'); ?>>
                    <strong>🤝 Companion Mode</strong>
                    <br><span style="color: #50575e; font-size: 12px; margin-left: 24px; display: block; margin-top: 4px;">
                        Lessons are normal WordPress posts/pages. A floating chat companion widget appears as members browse,
                        offering help, summaries, quizzes, and encouragement — like a tutor sitting beside them.
                    </span>
                </label>
                
                <label style="display: block; margin-bottom: 12px; padding: 12px; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer; <?php echo $content_display_mode === 'both' ? 'background: #f0f6fc; border-color: #2271b1;' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="both" <?php checked($content_display_mode, 'both'); ?>>
                    <strong>✨ Both</strong>
                    <br><span style="color: #50575e; font-size: 12px; margin-left: 24px; display: block; margin-top: 4px;">
                        Members can view lessons in-chat AND browse WordPress pages with the companion. Maximum flexibility.
                    </span>
                </label>
            </fieldset>
        </td>
    </tr>
</table>

<!-- Companion Widget Settings (only relevant when companion or both mode is active) -->
<div id="companion-widget-settings" style="<?php echo $content_display_mode === 'in_chat' ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
    
    <hr style="margin: 40px 0;">
    <h2>Companion Widget Settings</h2>
    <p>Configure the floating companion widget that appears on your WordPress pages.</p>
    
    <table class="form-table">
        <tr>
            <th scope="row"><label for="flow_companion_enabled">Enable Widget</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_enabled" id="flow_companion_enabled" value="1" <?php checked($enabled, '1'); ?>>
                    Show the companion widget on WordPress pages
                </label>
                <p class="description">When enabled, a floating chat widget appears on all non-chatbot pages of your site.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_position">Widget Position</label></th>
            <td>
                <select name="flow_companion_position" id="flow_companion_position">
                    <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>>↘ Bottom Right</option>
                    <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>>↙ Bottom Left</option>
                </select>
                <p class="description">Where the floating widget appears on screen.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_greeting">Greeting Message</label></th>
            <td>
                <textarea name="flow_companion_greeting" id="flow_companion_greeting" rows="3" class="large-text"><?php echo esc_textarea($greeting); ?></textarea>
                <p class="description">The first message the companion shows when opened. Use warm, encouraging language.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_accent_color">Accent Color</label></th>
            <td>
                <input type="color" name="flow_companion_accent_color" id="flow_companion_accent_color" value="<?php echo esc_attr($accent_color ?: '#6366f1'); ?>" style="width: 60px; height: 36px; padding: 2px; cursor: pointer;">
                <input type="text" id="companion_accent_hex" value="<?php echo esc_attr($accent_color ?: '#6366f1'); ?>" style="width: 100px; margin-left: 8px;" readonly>
                <button type="button" onclick="document.getElementById('flow_companion_accent_color').value='#6366f1'; document.getElementById('companion_accent_hex').value='#6366f1';" class="button" style="margin-left: 8px;">Reset</button>
                <p class="description">The primary color for the widget button and highlights. Leave as default for the FLOSC indigo.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_show_for_visitors">Visitor Visibility</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_show_for_visitors" id="flow_companion_show_for_visitors" value="1" <?php checked($show_for_visitors, '1'); ?>>
                    Show companion to non-logged-in visitors
                </label>
                <p class="description">When unchecked, only logged-in users see the companion. Visitors are directed to the full chatbot experience.</p>
            </td>
        </tr>
    </table>
</div>

<!-- Preview Panel -->
<hr style="margin: 40px 0;">
<h2>Preview</h2>
<div style="background: #f8f9fa; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; position: relative; min-height: 200px; overflow: hidden;">
    <p style="color: #787c82; font-size: 13px; margin: 0;">
        📱 A floating <strong style="color: <?php echo esc_attr($accent_color ?: '#6366f1'); ?>;">●</strong> chat button will appear in the 
        <strong><?php echo $position === 'bottom-left' ? 'bottom-left' : 'bottom-right'; ?></strong> 
        corner of every WordPress page (except the chatbot app).
    </p>
    <p style="color: #787c82; font-size: 13px; margin: 10px 0 0;">
        When a member clicks it, the companion opens with: "<em><?php echo esc_html(wp_trim_words($greeting, 15)); ?></em>"
    </p>
    <p style="color: #787c82; font-size: 13px; margin: 10px 0 0;">
        If the member is reading a lesson, the companion will know which lesson they're on and offer contextual help.
    </p>
    <div style="position: absolute; bottom: 12px; <?php echo $position === 'bottom-left' ? 'left: 16px;' : 'right: 16px;'; ?>">
        <div style="width: 48px; height: 48px; border-radius: 50%; background: <?php echo esc_attr($accent_color ?: '#6366f1'); ?>; display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 16px rgba(0,0,0,0.2);">
            💬
        </div>
    </div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    // Toggle widget settings visibility based on display mode
    function toggleCompanionSettings() {
        var mode = $('input[name="flow_companion_content_display_mode"]:checked').val();
        var $settings = $('#companion-widget-settings');
        
        if (mode === 'in_chat') {
            $settings.css({ opacity: 0.5, pointerEvents: 'none' });
        } else {
            $settings.css({ opacity: 1, pointerEvents: 'all' });
        }
        
        // Highlight selected radio card
        $('input[name="flow_companion_content_display_mode"]').closest('label').css({
            background: '',
            borderColor: '#c3c4c7'
        });
        $('input[name="flow_companion_content_display_mode"]:checked').closest('label').css({
            background: '#f0f6fc',
            borderColor: '#2271b1'
        });
    }
    
    $('input[name="flow_companion_content_display_mode"]').on('change', toggleCompanionSettings);
    
    // Sync color picker with hex display
    $('#flow_companion_accent_color').on('input', function() {
        $('#companion_accent_hex').val($(this).val());
    });
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
