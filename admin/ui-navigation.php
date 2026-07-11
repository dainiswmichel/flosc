<?php
/**
 * FLOSC Admin — Profile Bar Settings (Tab Partial)
 *
 * Included inside settings.php <form> — no own form/nonce/submit needed.
 * Save handler lives in settings.php under the `if ($flosc_active_tab === 'ui')` block.
 *
 * @package FLOSC
 * @since 8.0.1
 */

if (!defined('ABSPATH')) exit;

wp_enqueue_media();

$flosc_profile_bar = get_option('flosc_profile_bar', [
    'visitor' => ['name' => 'Visitor', 'badge' => 'Hope you enjoy our chat :-)', 'icon' => '👋', 'icon_url' => '', 'avatar_radius' => '8px', 'show_upgrade' => true, 'upgrade_label' => 'Upgrade'],
    'guest'   => ['name' => '', 'badge' => 'Guest', 'icon' => '', 'icon_url' => '', 'avatar_radius' => '8px', 'show_upgrade' => true, 'upgrade_label' => 'Upgrade to Pro'],
    'member'  => ['name' => '', 'badge' => 'Member', 'icon' => '', 'icon_url' => '', 'avatar_radius' => '8px', 'show_upgrade' => false, 'upgrade_label' => 'Upgrade'],
]);

$flosc_legacy_avatar_radius = $flow_settings['avatar_radius'] ?? '8px';
$flosc_profile_bar['visitor']['avatar_radius'] = $flosc_profile_bar['visitor']['avatar_radius'] ?? $flosc_legacy_avatar_radius;
$flosc_profile_bar['guest']['avatar_radius'] = $flosc_profile_bar['guest']['avatar_radius'] ?? '8px';
$flosc_profile_bar['member']['avatar_radius'] = $flosc_profile_bar['member']['avatar_radius'] ?? '8px';

$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_ui_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-ui';

$flosc_standard_avatar_icons = ['👋', '💬', '⭐', '🎯', '🚀', '✅', '🔔', '📘', '🧠', '🛡️', '✨', '🔥'];
$flosc_render_icon_palette = static function ($flosc_target_input_id) use ($flosc_standard_avatar_icons) {
    ?>
    <div class="flosc-avatar-icon-palette" data-target-input="<?php echo esc_attr($flosc_target_input_id); ?>" hidden>
        <?php foreach ($flosc_standard_avatar_icons as $flosc_icon): ?>
            <button type="button"
                    class="button flosc-avatar-icon-option"
                    data-target-input="<?php echo esc_attr($flosc_target_input_id); ?>"
                    data-icon="<?php echo esc_attr($flosc_icon); ?>"
                    aria-label="Select <?php echo esc_attr($flosc_icon); ?> icon">
                <?php echo esc_html($flosc_icon); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php
};
?>

<div class="flosc-ui-docs-wrap">
    <a href="<?php echo esc_url($flosc_ui_docs_url); ?>" class="flosc-ui-docs-link">Docs</a>
</div>

<h2 class="title">User Profile Bar</h2>
<p class="description">The profile bar appears at the bottom of the sidebar. Its avatar, name, badge, and labels change based on user state.</p>

<h3 class="flosc-ui-section-title">Visitor State</h3>
<p class="description">Shown to users who are not logged in.</p>
<table class="form-table">
    <tr>
        <th scope="row">Avatar Shape</th>
        <td>
            <select name="profile_bar_visitor_avatar_radius" id="profile_bar_visitor_avatar_radius">
                <option value="8px" <?php selected($flosc_profile_bar['visitor']['avatar_radius'] ?? '8px', '8px'); ?>>Rounded Rectangle</option>
                <option value="50%" <?php selected($flosc_profile_bar['visitor']['avatar_radius'] ?? '8px', '50%'); ?>>Circle</option>
                <option value="4px" <?php selected($flosc_profile_bar['visitor']['avatar_radius'] ?? '8px', '4px'); ?>>Slightly Rounded</option>
                <option value="0" <?php selected($flosc_profile_bar['visitor']['avatar_radius'] ?? '8px', '0'); ?>>Square</option>
            </select>
            <p class="description">Shape of the user avatar in the profile bar and chat messages.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Icon</th>
        <td>
                 <input type="text" name="profile_bar_visitor_icon" id="profile_bar_visitor_icon"
                   value="<?php echo esc_attr($flosc_profile_bar['visitor']['icon'] ?? '👋'); ?>"
                     class="regular-text flosc-ui-visitor-icon-input flosc-avatar-icon-input"
                     placeholder="Emoji or code (for example, U+1F44B)">
            <button type="button" class="button flosc-avatar-icon-picker-button" data-target-input="profile_bar_visitor_icon">Browse Icons</button>
            <?php $flosc_render_icon_palette('profile_bar_visitor_icon'); ?>
            <p class="description">Emoji displayed as the visitor avatar. Click the field or button to pick an icon, or paste a Unicode icon code (for example, U+1F44B).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Graphic URL</th>
        <td>
            <div class="flosc-flow-media-row">
                <input type="url" name="profile_bar_visitor_icon_url" id="profile_bar_visitor_icon_url"
                       value="<?php echo esc_attr($flosc_profile_bar['visitor']['icon_url'] ?? ''); ?>"
                       class="large-text flosc-avatar-media-input"
                       placeholder="https://yoursite.com/wp-content/uploads/.../visitor-avatar.png">
                <button type="button" class="button flosc-avatar-media-button" data-target-input="profile_bar_visitor_icon_url">Select from Media Library</button>
            </div>
            <p class="description">Graphical Icon Overrides Avatar Icon. Recommended: 36x36 px PNG/SVG, transparent background, under 100 KB.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">State Display Label</th>
        <td>
            <input type="text" name="profile_bar_visitor_name"
                   value="<?php echo esc_attr($flosc_profile_bar['visitor']['name'] ?? 'Visitor'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row">Tagline</th>
        <td>
            <input type="text" name="profile_bar_visitor_badge"
                   value="<?php echo esc_attr($flosc_profile_bar['visitor']['badge'] ?? 'Hope you enjoy our chat :-)'); ?>"
                   class="large-text">
            <p class="description">Subtitle shown below the visitor name.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Show Upgrade Button</th>
        <td>
            <label>
                <input type="checkbox" name="profile_bar_visitor_show_upgrade" value="1" <?php checked(!empty($flosc_profile_bar['visitor']['show_upgrade'])); ?>>
                Yes
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row">Upgrade Button Label</th>
        <td>
            <input type="text" name="profile_bar_visitor_upgrade_label"
                   value="<?php echo esc_attr($flosc_profile_bar['visitor']['upgrade_label'] ?? 'Upgrade'); ?>"
                   class="regular-text">
        </td>
    </tr>
</table>

<h3 class="flosc-ui-section-title">Guest State</h3>
<p class="description">Shown to logged-in users who have not purchased. Avatar and display name come from their WordPress account.</p>
<table class="form-table">
    <tr>
        <th scope="row">Avatar Shape</th>
        <td>
            <select name="profile_bar_guest_avatar_radius" id="profile_bar_guest_avatar_radius">
                <option value="8px" <?php selected($flosc_profile_bar['guest']['avatar_radius'] ?? '8px', '8px'); ?>>Rounded Rectangle</option>
                <option value="50%" <?php selected($flosc_profile_bar['guest']['avatar_radius'] ?? '8px', '50%'); ?>>Circle</option>
                <option value="4px" <?php selected($flosc_profile_bar['guest']['avatar_radius'] ?? '8px', '4px'); ?>>Slightly Rounded</option>
                <option value="0" <?php selected($flosc_profile_bar['guest']['avatar_radius'] ?? '8px', '0'); ?>>Square</option>
            </select>
            <p class="description">Shape used when profile bar state is Guest.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Icon</th>
        <td>
                 <input type="text" name="profile_bar_guest_icon" id="profile_bar_guest_icon"
                   value="<?php echo esc_attr($flosc_profile_bar['guest']['icon'] ?? ''); ?>"
                     class="regular-text flosc-ui-visitor-icon-input flosc-avatar-icon-input"
                     placeholder="Emoji or code (for example, U+1F44B)">
            <button type="button" class="button flosc-avatar-icon-picker-button" data-target-input="profile_bar_guest_icon">Browse Icons</button>
            <?php $flosc_render_icon_palette('profile_bar_guest_icon'); ?>
            <p class="description">Optional emoji/avatar for Guest state. Leave empty to use WordPress user avatar. You can also paste a Unicode icon code (for example, U+1F44B).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Graphic URL</th>
        <td>
            <div class="flosc-flow-media-row">
                <input type="url" name="profile_bar_guest_icon_url" id="profile_bar_guest_icon_url"
                       value="<?php echo esc_attr($flosc_profile_bar['guest']['icon_url'] ?? ''); ?>"
                       class="large-text flosc-avatar-media-input"
                       placeholder="https://yoursite.com/wp-content/uploads/.../guest-avatar.png">
                <button type="button" class="button flosc-avatar-media-button" data-target-input="profile_bar_guest_icon_url">Select from Media Library</button>
            </div>
            <p class="description">Graphical Icon Overrides Avatar Icon. Recommended: 36x36 px PNG/SVG, transparent background, under 100 KB.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">State Display Label</th>
        <td>
            <input type="text" name="profile_bar_guest_name"
                   value="<?php echo esc_attr($flosc_profile_bar['guest']['name'] ?? ''); ?>"
                   class="regular-text">
            <p class="description">Optional override. Leave empty to use WordPress display name.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Tagline</th>
        <td>
            <input type="text" name="profile_bar_guest_badge"
                   value="<?php echo esc_attr($flosc_profile_bar['guest']['badge'] ?? 'Guest'); ?>"
                   class="regular-text">
            <p class="description">Shown below the user's name (for example, Guest or Free Account).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Upgrade Button Label</th>
        <td>
            <input type="text" name="profile_bar_guest_upgrade"
                   value="<?php echo esc_attr($flosc_profile_bar['guest']['upgrade_label'] ?? 'Upgrade to Pro'); ?>"
                   class="regular-text">
            <p class="description">Button label shown in guest dropdown to encourage purchase.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Show Upgrade Button</th>
        <td>
            <label>
                <input type="checkbox" name="profile_bar_guest_show_upgrade" value="1" <?php checked(!empty($flosc_profile_bar['guest']['show_upgrade'])); ?>>
                Yes
            </label>
        </td>
    </tr>
</table>

<h3 class="flosc-ui-section-title">Member State</h3>
<p class="description">Shown to logged-in users who have purchased. Avatar and name come from WordPress.</p>
<table class="form-table">
    <tr>
        <th scope="row">Avatar Shape</th>
        <td>
            <select name="profile_bar_member_avatar_radius" id="profile_bar_member_avatar_radius">
                <option value="8px" <?php selected($flosc_profile_bar['member']['avatar_radius'] ?? '8px', '8px'); ?>>Rounded Rectangle</option>
                <option value="50%" <?php selected($flosc_profile_bar['member']['avatar_radius'] ?? '8px', '50%'); ?>>Circle</option>
                <option value="4px" <?php selected($flosc_profile_bar['member']['avatar_radius'] ?? '8px', '4px'); ?>>Slightly Rounded</option>
                <option value="0" <?php selected($flosc_profile_bar['member']['avatar_radius'] ?? '8px', '0'); ?>>Square</option>
            </select>
            <p class="description">Shape used when profile bar state is Member.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Icon</th>
        <td>
                 <input type="text" name="profile_bar_member_icon" id="profile_bar_member_icon"
                   value="<?php echo esc_attr($flosc_profile_bar['member']['icon'] ?? ''); ?>"
                     class="regular-text flosc-ui-visitor-icon-input flosc-avatar-icon-input"
                     placeholder="Emoji or code (for example, U+1F44B)">
            <button type="button" class="button flosc-avatar-icon-picker-button" data-target-input="profile_bar_member_icon">Browse Icons</button>
            <?php $flosc_render_icon_palette('profile_bar_member_icon'); ?>
            <p class="description">Optional emoji/avatar for Member state. Leave empty to use WordPress user avatar. You can also paste a Unicode icon code (for example, U+1F44B).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Graphic URL</th>
        <td>
            <div class="flosc-flow-media-row">
                <input type="url" name="profile_bar_member_icon_url" id="profile_bar_member_icon_url"
                       value="<?php echo esc_attr($flosc_profile_bar['member']['icon_url'] ?? ''); ?>"
                       class="large-text flosc-avatar-media-input"
                       placeholder="https://yoursite.com/wp-content/uploads/.../member-avatar.png">
                <button type="button" class="button flosc-avatar-media-button" data-target-input="profile_bar_member_icon_url">Select from Media Library</button>
            </div>
            <p class="description">Graphical Icon Overrides Avatar Icon. Recommended: 36x36 px PNG/SVG, transparent background, under 100 KB.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">State Display Label</th>
        <td>
            <input type="text" name="profile_bar_member_name"
                   value="<?php echo esc_attr($flosc_profile_bar['member']['name'] ?? ''); ?>"
                   class="regular-text">
            <p class="description">Optional override. Leave empty to use WordPress display name.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Tagline</th>
        <td>
            <input type="text" name="profile_bar_member_badge"
                   value="<?php echo esc_attr($flosc_profile_bar['member']['badge'] ?? 'Member'); ?>"
                   class="regular-text">
            <p class="description">Shown below the user's name (for example, Member, Pro, Premium).</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Show Upgrade Button</th>
        <td>
            <label>
                <input type="checkbox" name="profile_bar_member_show_upgrade" value="1" <?php checked(!empty($flosc_profile_bar['member']['show_upgrade'])); ?>>
                Yes
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row">Upgrade Button Label</th>
        <td>
            <input type="text" name="profile_bar_member_upgrade_label"
                   value="<?php echo esc_attr($flosc_profile_bar['member']['upgrade_label'] ?? 'Upgrade'); ?>"
                   class="regular-text">
        </td>
    </tr>
</table>

<?php ob_start(); ?>
(function() {
    function normalizeUnicodeIconInput(rawValue) {
        var value = String(rawValue || '').trim();
        if (!value) return '';

        var codePattern = /^U\+[0-9A-Fa-f]{4,6}(?:\s+U\+[0-9A-Fa-f]{4,6})*$/;
        if (!codePattern.test(value)) {
            return value;
        }

        var parts = value.split(/\s+/);
        var chars = [];
        for (var i = 0; i < parts.length; i++) {
            var hex = parts[i].slice(2);
            var codePoint = parseInt(hex, 16);
            if (!Number.isFinite(codePoint) || codePoint < 0) {
                return value;
            }
            chars.push(String.fromCodePoint(codePoint));
        }

        return chars.join('');
    }

    function hideAllIconPalettes() {
        var palettes = document.querySelectorAll('.flosc-avatar-icon-palette');
        palettes.forEach(function(palette) {
            palette.hidden = true;
        });
    }

    function showIconPalette(inputId) {
        hideAllIconPalettes();
        var palette = document.querySelector('.flosc-avatar-icon-palette[data-target-input="' + inputId + '"]');
        if (!palette) return;
        palette.hidden = false;
    }

    function openAvatarMediaPicker(targetInputId) {
        var input = document.getElementById(targetInputId);
        if (!input || typeof wp === 'undefined' || !wp.media) return;

        var frame = wp.media({
            title: 'Select Avatar Graphic',
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function() {
            var selection = frame.state().get('selection').first();
            if (!selection) return;
            var media = selection.toJSON();
            input.value = media.url || '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        frame.open();
    }

    var fields = document.querySelectorAll('.flosc-avatar-media-input');
    fields.forEach(function(field) {
        field.addEventListener('click', function(e) {
            e.preventDefault();
            openAvatarMediaPicker(field.id);
        });
    });

    var iconInputs = document.querySelectorAll('.flosc-avatar-icon-input');
    iconInputs.forEach(function(input) {
        input.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        input.addEventListener('blur', function() {
            var normalized = normalizeUnicodeIconInput(input.value);
            if (normalized !== input.value) {
                input.value = normalized;
            }
        });
    });

    var iconPickerButtons = document.querySelectorAll('.flosc-avatar-icon-picker-button');
    iconPickerButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var targetInputId = button.getAttribute('data-target-input');
            if (!targetInputId) return;
            showIconPalette(targetInputId);
        });
    });

    var iconOptions = document.querySelectorAll('.flosc-avatar-icon-option');
    iconOptions.forEach(function(option) {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            var targetInputId = option.getAttribute('data-target-input');
            var icon = option.getAttribute('data-icon') || '';
            var targetInput = targetInputId ? document.getElementById(targetInputId) : null;
            if (!targetInput) return;
            targetInput.value = icon;
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            hideAllIconPalettes();
        });
    });

    var buttons = document.querySelectorAll('.flosc-avatar-media-button');
    buttons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var targetInputId = button.getAttribute('data-target-input');
            if (!targetInputId) return;
            openAvatarMediaPicker(targetInputId);
        });
    });

    document.addEventListener('click', function() {
        hideAllIconPalettes();
    });
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
