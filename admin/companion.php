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

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];

// Read current values with defaults
$flosc_content_display_mode = $flosc_flow_settings['companion_content_display_mode'] ?? 'in_chat';
$flosc_enabled              = $flosc_flow_settings['companion_enabled'] ?? '';
$flosc_position             = $flosc_flow_settings['companion_position'] ?? 'bottom-right';
$flosc_greeting             = $flosc_flow_settings['companion_greeting'] ?? 'Hi! I\'m your learning companion. Need help with this lesson?';
$flosc_accent_color         = $flosc_flow_settings['companion_accent_color'] ?? '';
$flosc_show_for_visitors    = $flosc_flow_settings['companion_show_for_visitors'] ?? '';
?>

<h2>Content Display Mode</h2>
<p>Choose how members access lesson content. This determines whether lessons are viewed inside the chatbot, on your WordPress site, or both.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_companion_content_display_mode">Display Mode</label></th>
        <td>
            <fieldset>
                <label class="flosc-companion-mode-card <?php echo $flosc_content_display_mode === 'in_chat' ? 'is-active' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="in_chat" <?php checked($flosc_content_display_mode, 'in_chat'); ?>>
                    <strong>💬 In-Chat Only</strong> <em class="flosc-companion-mode-default">(default)</em>
                    <br><span class="flosc-companion-mode-copy">
                        Lessons are delivered inside the chatbot. Members read content in the chat interface.
                    </span>
                </label>
                
                <label class="flosc-companion-mode-card <?php echo $flosc_content_display_mode === 'companion' ? 'is-active' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="companion" <?php checked($flosc_content_display_mode, 'companion'); ?>>
                    <strong>🤝 Companion Mode</strong>
                    <br><span class="flosc-companion-mode-copy">
                        Lessons are normal WordPress posts/pages. A floating chat companion widget appears as members browse,
                        offering help, summaries, quizzes, and encouragement — like a tutor sitting beside them.
                    </span>
                </label>
                
                <label class="flosc-companion-mode-card <?php echo $flosc_content_display_mode === 'both' ? 'is-active' : ''; ?>">
                    <input type="radio" name="flow_companion_content_display_mode" value="both" <?php checked($flosc_content_display_mode, 'both'); ?>>
                    <strong>✨ Both</strong>
                    <br><span class="flosc-companion-mode-copy">
                        Members can view lessons in-chat AND browse WordPress pages with the companion. Maximum flexibility.
                    </span>
                </label>
            </fieldset>
        </td>
    </tr>
</table>

<!-- Companion Widget Settings (only relevant when companion or both mode is active) -->
<div id="companion-widget-settings" class="<?php echo $flosc_content_display_mode === 'in_chat' ? 'flosc-companion-disabled' : ''; ?>">
    
    <hr class="flosc-companion-divider">
    <h2>Companion Widget Settings</h2>
    <p>Configure the floating companion widget that appears on your WordPress pages.</p>
    
    <table class="form-table">
        <tr>
            <th scope="row"><label for="flow_companion_enabled">Enable Widget</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_enabled" id="flow_companion_enabled" value="1" <?php checked($flosc_enabled, '1'); ?>>
                    Show the companion widget on WordPress pages
                </label>
                <p class="description">When enabled, a floating chat widget appears on all non-chatbot pages of your site.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_position">Widget Position</label></th>
            <td>
                <select name="flow_companion_position" id="flow_companion_position">
                    <option value="bottom-right" <?php selected($flosc_position, 'bottom-right'); ?>>↘ Bottom Right</option>
                    <option value="bottom-left" <?php selected($flosc_position, 'bottom-left'); ?>>↙ Bottom Left</option>
                </select>
                <p class="description">Where the floating widget appears on screen.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_greeting">Greeting Message</label></th>
            <td>
                <textarea name="flow_companion_greeting" id="flow_companion_greeting" rows="3" class="large-text"><?php echo esc_textarea($flosc_greeting); ?></textarea>
                <p class="description">The first message the companion shows when opened. Use warm, encouraging language.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_accent_color">Accent Color</label></th>
            <td>
                <input type="color" name="flow_companion_accent_color" id="flow_companion_accent_color" value="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>" class="flosc-companion-color-input">
                <input type="text" id="companion_accent_hex" value="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>" class="flosc-companion-color-hex" readonly>
                <button type="button" id="flosc-companion-color-reset" class="button flosc-companion-color-reset">Reset</button>
                <p class="description">The primary color for the widget button and highlights. Leave as default for the FLOSC indigo.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="flow_companion_show_for_visitors">Visitor Visibility</label></th>
            <td>
                <label>
                    <input type="checkbox" name="flow_companion_show_for_visitors" id="flow_companion_show_for_visitors" value="1" <?php checked($flosc_show_for_visitors, '1'); ?>>
                    Show companion to non-logged-in visitors
                </label>
                <p class="description">When unchecked, only logged-in users see the companion. Visitors are directed to the full chatbot experience.</p>
            </td>
        </tr>
    </table>
</div>

<!-- Preview Panel -->
<hr class="flosc-companion-divider">
<h2>Preview</h2>
<div class="flosc-companion-preview">
    <p class="flosc-companion-preview-copy">
        📱 A floating <strong class="flosc-companion-preview-dot" data-accent="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>">●</strong> chat button will appear in the 
        <strong><?php echo $flosc_position === 'bottom-left' ? 'bottom-left' : 'bottom-right'; ?></strong> 
        corner of every WordPress page (except the chatbot app).
    </p>
    <p class="flosc-companion-preview-copy flosc-companion-preview-copy-spaced">
        When a member clicks it, the companion opens with: "<em><?php echo esc_html(wp_trim_words($flosc_greeting, 15)); ?></em>"
    </p>
    <p class="flosc-companion-preview-copy flosc-companion-preview-copy-spaced">
        If the member is reading a lesson, the companion will know which lesson they're on and offer contextual help.
    </p>
    <div class="flosc-companion-preview-bubble-wrap <?php echo $flosc_position === 'bottom-left' ? 'flosc-companion-preview-bubble-wrap--left' : 'flosc-companion-preview-bubble-wrap--right'; ?>">
        <div class="flosc-companion-preview-bubble" data-accent="<?php echo esc_attr($flosc_accent_color ?: '#6366f1'); ?>">
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
            $settings.addClass('flosc-companion-disabled');
        } else {
            $settings.removeClass('flosc-companion-disabled');
        }
        
        // Highlight selected radio card
        $('input[name="flow_companion_content_display_mode"]').closest('label').removeClass('is-active');
        $('input[name="flow_companion_content_display_mode"]:checked').closest('label').addClass('is-active');
    }
    
    $('input[name="flow_companion_content_display_mode"]').on('change', toggleCompanionSettings);
    
    // Sync color picker with hex display
    $('#flow_companion_accent_color').on('input', function() {
        var color = $(this).val();
        $('#companion_accent_hex').val(color);
        $('.flosc-companion-preview-dot').css('color', color);
        $('.flosc-companion-preview-bubble').css('background', color);
    });

    $('#flosc-companion-color-reset').on('click', function() {
        $('#flow_companion_accent_color').val('#6366f1').trigger('input');
    });

    $('#flow_companion_accent_color').trigger('input');
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
