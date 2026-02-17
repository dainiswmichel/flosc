<?php
/**
 * FLOSC Admin — UI & Navigation Settings (Tab Partial)
 * v1.8.2: Dynamic visitor menu repeater, profile bar states, login destination
 * 
 * Included inside settings.php <form> — no own form/nonce/submit needed.
 * Save handler lives in settings.php under the `if ($active_tab === 'ui')` block.
 * 
 * @package FLOSC
 * @since 1.8.2
 */

if (!defined('ABSPATH')) exit;

// ── Load current values ──────────────────────────────────────────────

// v1.8.2 format: indexed array [['label'=>'…','action'=>'…'], …]
// Backward-compat: if old associative format is stored, convert it
$visitor_menu_raw = get_option('flosc_visitor_menu_items', []);
$visitor_menu = [];

if (!empty($visitor_menu_raw)) {
    // Check if old associative format (keys like 'signup', 'login', 'quiz')
    if (isset($visitor_menu_raw['signup']) || isset($visitor_menu_raw['login']) || isset($visitor_menu_raw['quiz'])) {
        // Migrate old format → new indexed format
        $action_map = [
            'signup' => 'open_registration',
            'login'  => 'login',
            'quiz'   => 'open_quiz',
        ];
        foreach ($visitor_menu_raw as $key => $item) {
            if (!empty($item['enabled'])) {
                $visitor_menu[] = [
                    'label'  => $item['label'] ?? ucfirst($key),
                    'action' => $action_map[$key] ?? $key,
                ];
            }
        }
    } else {
        // Already new format
        $visitor_menu = $visitor_menu_raw;
    }
}

// Default if empty
if (empty($visitor_menu)) {
    $visitor_menu = [
        ['label' => 'Sign Up',    'action' => 'open_registration'],
        ['label' => 'Log In',     'action' => 'login'],
        ['label' => 'Take Quiz',  'action' => 'open_quiz'],
    ];
}

$login_destination = get_option('flosc_login_destination', 'flosc_chat');

$profile_bar = get_option('flosc_profile_bar', [
    'visitor' => ['name' => 'Visitor', 'badge' => 'Hope you enjoy our chat :-)', 'icon' => '👋'],
    'guest'   => ['badge' => 'Guest', 'upgrade_label' => 'Upgrade to Pro'],
    'member'  => ['badge' => 'Member'],
]);

$app_url = flosc()->get_app_url();

// Available in-chat actions for the dropdown
$available_actions = [
    'open_registration'    => 'Sign Up — open registration modal',
    'login'                => 'Log In — redirect to login destination',
    'open_quiz'            => 'Take Quiz — open quiz modal',
    'open_free_lesson'     => 'Free Lesson — open the free lesson',
    'open_lesson_library'  => 'Lesson Library — show all lessons',
    'open_quiz_library'    => 'Quiz Library — show all quizzes',
    'open_support'         => 'Support — open help/support',
    'open_sandbox_purchase'=> 'Purchase — open purchase flow',
];
?>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: User Profile Bar
     ═══════════════════════════════════════════════════════════════════ -->
<h2 class="title">User Profile Bar</h2>
<p class="description">The profile bar appears at the bottom of the sidebar. Its avatar, name, badge, and dropdown menu change based on user state.</p>

<h3>Visitor State</h3>
<p class="description">Shown to users who are not logged in.</p>
<table class="form-table">
    <!-- v1.8.3: Avatar Shape (applies to all states) -->
    <?php $current_avatar_radius = $flow_settings['avatar_radius'] ?? '8px'; ?>
    <tr>
        <th scope="row">Avatar Shape</th>
        <td>
            <select name="flow_avatar_radius" id="flow_avatar_radius">
                <option value="8px" <?php selected($current_avatar_radius, '8px'); ?>>Rounded Rectangle</option>
                <option value="50%" <?php selected($current_avatar_radius, '50%'); ?>>Circle</option>
                <option value="4px" <?php selected($current_avatar_radius, '4px'); ?>>Slightly Rounded</option>
                <option value="0" <?php selected($current_avatar_radius, '0'); ?>>Square</option>
            </select>
            <p class="description">Shape of the user avatar in the profile bar and chat messages.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Icon</th>
        <td>
            <input type="text" name="profile_bar_visitor_icon"
                   value="<?php echo esc_attr($profile_bar['visitor']['icon'] ?? '👋'); ?>"
                   class="small-text" style="font-size: 24px; width: 60px; text-align: center;">
            <p class="description">Emoji displayed as the visitor avatar.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Display Name</th>
        <td>
            <input type="text" name="profile_bar_visitor_name"
                   value="<?php echo esc_attr($profile_bar['visitor']['name'] ?? 'Visitor'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row">Badge Text</th>
        <td>
            <input type="text" name="profile_bar_visitor_badge"
                   value="<?php echo esc_attr($profile_bar['visitor']['badge'] ?? 'Hope you enjoy our chat :-)'); ?>"
                   class="large-text">
            <p class="description">Subtitle shown below the visitor name.</p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Visitor Dropdown Menu — Dynamic Repeater
     ═══════════════════════════════════════════════════════════════════ -->
<h3>Visitor Dropdown Menu</h3>
<p class="description">
    Configure the menu items visitors see when they click the profile bar.
    All actions happen in-chat — no page navigation.
</p>

<div id="flosc-visitor-menu-repeater">
    <table class="widefat" style="max-width: 700px;">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Label</th>
                <th>Action</th>
                <th style="width: 60px;"></th>
            </tr>
        </thead>
        <tbody id="flosc-menu-items-body">
            <?php foreach ($visitor_menu as $i => $item): ?>
            <tr class="flosc-menu-row">
                <td class="flosc-menu-row-number"><?php echo ($i + 1); ?></td>
                <td>
                    <input type="text" name="visitor_menu_label[]"
                           value="<?php echo esc_attr($item['label']); ?>"
                           class="regular-text" placeholder="Menu label" required>
                </td>
                <td>
                    <select name="visitor_menu_action[]" style="min-width: 260px;">
                        <?php foreach ($available_actions as $val => $desc): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($item['action'], $val); ?>>
                            <?php echo esc_html($desc); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="button flosc-remove-menu-item" title="Remove item">&times;</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top: 10px;">
        <button type="button" class="button" id="flosc-add-menu-item">+ Add Menu Item</button>
    </p>
</div>

<script>
(function() {
    const tbody = document.getElementById('flosc-menu-items-body');
    const addBtn = document.getElementById('flosc-add-menu-item');

    // Available actions for new rows
    const actions = <?php echo wp_json_encode($available_actions); ?>;

    function buildActionOptions(selected) {
        let html = '';
        for (const [val, desc] of Object.entries(actions)) {
            const sel = val === selected ? ' selected' : '';
            html += `<option value="${val}"${sel}>${desc}</option>`;
        }
        return html;
    }

    function renumber() {
        tbody.querySelectorAll('.flosc-menu-row').forEach((row, i) => {
            row.querySelector('.flosc-menu-row-number').textContent = i + 1;
        });
    }

    // Add item
    addBtn.addEventListener('click', function() {
        const tr = document.createElement('tr');
        tr.className = 'flosc-menu-row';
        tr.innerHTML = `
            <td class="flosc-menu-row-number"></td>
            <td><input type="text" name="visitor_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
            <td><select name="visitor_menu_action[]" style="min-width: 260px;">${buildActionOptions('open_quiz')}</select></td>
            <td><button type="button" class="button flosc-remove-menu-item" title="Remove item">&times;</button></td>
        `;
        tbody.appendChild(tr);
        renumber();
        tr.querySelector('input').focus();
    });

    // Remove item (delegated)
    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('flosc-remove-menu-item')) {
            e.target.closest('.flosc-menu-row').remove();
            renumber();
        }
    });
})();
</script>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Guest State
     ═══════════════════════════════════════════════════════════════════ -->
<h3>Guest State</h3>
<p class="description">Shown to logged-in users who have not purchased. Avatar and display name come from their WordPress account.</p>
<table class="form-table">
    <tr>
        <th scope="row">Badge Text</th>
        <td>
            <input type="text" name="profile_bar_guest_badge"
                   value="<?php echo esc_attr($profile_bar['guest']['badge'] ?? 'Guest'); ?>"
                   class="regular-text">
            <p class="description">Shown below the user's name (e.g., "Guest", "Free Account").</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Upgrade Button Label</th>
        <td>
            <input type="text" name="profile_bar_guest_upgrade"
                   value="<?php echo esc_attr($profile_bar['guest']['upgrade_label'] ?? 'Upgrade to Pro'); ?>"
                   class="regular-text">
            <p class="description">Button shown in guest dropdown to encourage purchase.</p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Member State
     ═══════════════════════════════════════════════════════════════════ -->
<h3>Member State</h3>
<p class="description">Shown to logged-in users who have purchased. No upgrade button. Avatar and name from WordPress.</p>
<table class="form-table">
    <tr>
        <th scope="row">Badge Text</th>
        <td>
            <input type="text" name="profile_bar_member_badge"
                   value="<?php echo esc_attr($profile_bar['member']['badge'] ?? 'Member'); ?>"
                   class="regular-text">
            <p class="description">Shown below the user's name (e.g., "Member", "Pro", "Premium").</p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Login Destination
     ═══════════════════════════════════════════════════════════════════ -->
<h2 class="title">Login Destination</h2>
<p class="description">Where should users land after logging in?</p>

<table class="form-table">
    <tr>
        <th scope="row">After login, redirect to</th>
        <td>
            <select name="flosc_login_destination">
                <option value="flosc_chat" <?php selected($login_destination, 'flosc_chat'); ?>>
                    FLOSC Chat (<?php echo esc_html($app_url); ?>)
                </option>
                <option value="my_account" <?php selected($login_destination, 'my_account'); ?>>
                    WooCommerce My Account
                </option>
                <option value="profile" <?php selected($login_destination, 'profile'); ?>>
                    User Profile (wp-admin/profile.php)
                </option>
                <option value="home" <?php selected($login_destination, 'home'); ?>>
                    Home Page
                </option>
            </select>
            <p class="description">Controls where the "Log In" visitor menu item and WordPress login form redirect to.</p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Action Reference
     ═══════════════════════════════════════════════════════════════════ -->
<h2 class="title">Available In-Chat Actions</h2>
<p class="description">These actions can be assigned to visitor menu items above. All stay in-chat — no page navigation (except Log In).</p>

<table class="widefat striped" style="max-width: 700px;">
    <thead>
        <tr>
            <th>Action Key</th>
            <th>What Happens</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($available_actions as $key => $desc): ?>
        <tr>
            <td><code><?php echo esc_html($key); ?></code></td>
            <td><?php echo esc_html($desc); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
