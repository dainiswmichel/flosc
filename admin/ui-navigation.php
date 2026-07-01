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
$flosc_visitor_menu_raw = get_option('flosc_visitor_menu_items', []);
$flosc_visitor_menu = [];

if (!empty($flosc_visitor_menu_raw)) {
    // Check if old associative format (keys like 'signup', 'login', 'quiz')
    if (isset($flosc_visitor_menu_raw['signup']) || isset($flosc_visitor_menu_raw['login']) || isset($flosc_visitor_menu_raw['quiz'])) {
        // Migrate old format → new indexed format
        $flosc_action_map = [
            'signup' => 'open_registration',
            'login'  => 'login',
            'quiz'   => 'open_quiz',
        ];
        foreach ($flosc_visitor_menu_raw as $flosc_key => $flosc_item) {
            if (!empty($flosc_item['enabled'])) {
                $flosc_visitor_menu[] = [
                    'label'  => $flosc_item['label'] ?? ucfirst($flosc_key),
                    'action' => $flosc_action_map[$flosc_key] ?? $flosc_key,
                ];
            }
        }
    } else {
        // Already new format
        $flosc_visitor_menu = $flosc_visitor_menu_raw;
    }
}

// Default if empty
if (empty($flosc_visitor_menu)) {
    $flosc_visitor_menu = [
        ['label' => 'Sign Up',    'action' => 'open_registration'],
        ['label' => 'Log In',     'action' => 'login'],
        ['label' => 'Take Quiz',  'action' => 'open_quiz'],
    ];
}

// v1.9.8: Guest menu (logged-in, not purchased)
$flosc_guest_menu = get_option('flosc_guest_menu_items', []);
if (empty($flosc_guest_menu)) {
    $flosc_guest_menu = [
        ['label' => 'My Profile', 'action' => 'view_profile'],
        ['label' => 'Take Quiz',  'action' => 'open_quiz'],
        ['label' => 'Upgrade',    'action' => 'open_sandbox_purchase'],
        ['label' => 'Log Out',    'action' => 'logout'],
    ];
}

// v1.9.8: Member menu (logged-in, purchased)
$flosc_member_menu = get_option('flosc_member_menu_items', []);
if (empty($flosc_member_menu)) {
    $flosc_member_menu = [
        ['label' => 'My Profile',     'action' => 'view_profile'],
        ['label' => 'My Lessons',     'action' => 'open_lesson_library'],
        ['label' => 'Take Quiz',      'action' => 'open_quiz'],
        ['label' => 'Log Out',        'action' => 'logout'],
    ];
}

$flosc_login_destination = get_option('flosc_login_destination', '');

$flosc_profile_bar = get_option('flosc_profile_bar', [
    'visitor' => ['name' => 'Visitor', 'badge' => 'Hope you enjoy our chat :-)', 'icon' => '👋'],
    'guest'   => ['badge' => 'Guest', 'upgrade_label' => 'Upgrade to Pro'],
    'member'  => ['badge' => 'Member'],
]);

$flosc_app_url = flosc()->get_app_url();
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_ui_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-ui';
// UI tab doc link is rendered below.

// Available in-chat actions for the dropdown
$flosc_available_actions = [
    'open_registration'    => 'Sign Up — open registration modal',
    'open_login_modal'     => 'Log In — open login modal',
    'logout'               => 'Log Out — end session and return to visitor state',
    'open_quiz'            => 'Take Quiz — open quiz modal',
    'open_free_lesson'     => 'Free Lesson — open the free lesson',
    'open_lesson_library'  => 'Lesson Library — show all lessons',
    'open_quiz_library'    => 'Quiz Library — show all quizzes',
    'open_support'         => 'Support — open help/support',
    'open_sandbox_purchase'=> 'Purchase — open purchase flow',
    'send_prompt'          => 'Send Prompt — type a message into the chatbot',
    'view_profile'         => 'My Profile — go to user profile page',
    'view_dashboard'       => 'Dashboard — go to WordPress dashboard',
];

// v1.9.8: Guest and Member menus
$flosc_guest_menu = get_option('flosc_guest_menu_items', []);
if (empty($flosc_guest_menu)) {
    $flosc_guest_menu = [
        ['label' => 'Take Quiz',  'action' => 'open_quiz'],
        ['label' => 'Upgrade',    'action' => 'open_sandbox_purchase'],
        ['label' => 'Log Out',    'action' => 'logout'],
    ];
}

$flosc_member_menu = get_option('flosc_member_menu_items', []);
if (empty($flosc_member_menu)) {
    $flosc_member_menu = [
        ['label' => 'Lesson Library', 'action' => 'open_lesson_library'],
        ['label' => 'Quiz Library',   'action' => 'open_quiz_library'],
        ['label' => 'Log Out',        'action' => 'logout'],
    ];
}
?>

<div class="flosc-ui-docs-wrap">
    <a href="<?php echo esc_url($flosc_ui_docs_url); ?>" class="flosc-ui-docs-link">Docs</a>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: User Profile Bar
     ═══════════════════════════════════════════════════════════════════ -->
<h2 class="title">User Profile Bar</h2>
<p class="description">The profile bar appears at the bottom of the sidebar. Its avatar, name, badge, and dropdown menu change based on user state.</p>

<h3 class="flosc-ui-section-title">Visitor State</h3>
<p class="description">Shown to users who are not logged in.</p>
<table class="form-table">
    <!-- v1.8.3: Avatar Shape (applies to all states) -->
    <?php $flosc_current_avatar_radius = $flow_settings['avatar_radius'] ?? '8px'; ?>
    <tr>
        <th scope="row">Avatar Shape</th>
        <td>
            <select name="flow_avatar_radius" id="flow_avatar_radius">
                <option value="8px" <?php selected($flosc_current_avatar_radius, '8px'); ?>>Rounded Rectangle</option>
                <option value="50%" <?php selected($flosc_current_avatar_radius, '50%'); ?>>Circle</option>
                <option value="4px" <?php selected($flosc_current_avatar_radius, '4px'); ?>>Slightly Rounded</option>
                <option value="0" <?php selected($flosc_current_avatar_radius, '0'); ?>>Square</option>
            </select>
            <p class="description">Shape of the user avatar in the profile bar and chat messages.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Avatar Icon</th>
        <td>
            <input type="text" name="profile_bar_visitor_icon"
                   value="<?php echo esc_attr($flosc_profile_bar['visitor']['icon'] ?? '👋'); ?>"
                   class="small-text flosc-ui-visitor-icon-input">
            <p class="description">Emoji displayed as the visitor avatar.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Display Name</th>
        <td>
            <input type="text" name="profile_bar_visitor_name"
                   value="<?php echo esc_attr($flosc_profile_bar['visitor']['name'] ?? 'Visitor'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row">Badge Text</th>
        <td>
            <input type="text" name="profile_bar_visitor_badge"
                   value="<?php echo esc_attr($flosc_profile_bar['visitor']['badge'] ?? 'Hope you enjoy our chat :-)'); ?>"
                   class="large-text">
            <p class="description">Subtitle shown below the visitor name.</p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Visitor Dropdown Menu — Dynamic Repeater
     ═══════════════════════════════════════════════════════════════════ -->
<h3 class="flosc-ui-section-title">Visitor Dropdown Menu</h3>
<p class="description">
    Configure the menu items visitors see when they click the profile bar.
    All actions happen in-chat — no page navigation.
</p>

<div id="flosc-visitor-menu-repeater">
    <table class="widefat flosc-ui-menu-table">
        <thead>
            <tr>
                <th class="flosc-ui-col-index">#</th>
                <th>Label</th>
                <th>Action</th>
                <th class="flosc-ui-col-actions"></th>
            </tr>
        </thead>
        <tbody id="flosc-menu-items-body">
            <?php foreach ($flosc_visitor_menu as $flosc_i => $flosc_item): ?>
            <tr class="flosc-menu-row">
                <td class="flosc-menu-row-number"><?php echo esc_html((string) ($flosc_i + 1)); ?></td>
                <td>
                    <input type="text" name="visitor_menu_label[]"
                           value="<?php echo esc_attr($flosc_item['label']); ?>"
                           class="regular-text" placeholder="Menu label" required>
                </td>
                <td>
                    <select name="visitor_menu_action[]" class="flosc-ui-action-select">
                        <?php foreach ($flosc_available_actions as $flosc_val => $flosc_desc): ?>
                        <option value="<?php echo esc_attr($flosc_val); ?>" <?php selected($flosc_item['action'], $flosc_val); ?>>
                            <?php echo esc_html($flosc_desc); ?>
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

    <p class="flosc-ui-menu-actions-wrap">
        <button type="button" class="button" id="flosc-add-menu-item">+ Add Menu Item</button>
    </p>
</div>

<?php ob_start(); ?>
(function() {
    const tbody = document.getElementById('flosc-menu-items-body');
    const addBtn = document.getElementById('flosc-add-menu-item');

    // Available actions for new rows
    const actions = <?php echo wp_json_encode($flosc_available_actions); ?>;

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
            <td><select name="visitor_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_quiz')}</select></td>
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
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Guest State
     ═══════════════════════════════════════════════════════════════════ -->
<h3 class="flosc-ui-section-title">Guest State</h3>
<p class="description">Shown to logged-in users who have not purchased. Avatar and display name come from their WordPress account.</p>
<table class="form-table">
    <tr>
        <th scope="row">Badge Text</th>
        <td>
            <input type="text" name="profile_bar_guest_badge"
                   value="<?php echo esc_attr($flosc_profile_bar['guest']['badge'] ?? 'Guest'); ?>"
                   class="regular-text">
            <p class="description">Shown below the user's name (e.g., "Guest", "Free Account").</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Upgrade Button Label</th>
        <td>
            <input type="text" name="profile_bar_guest_upgrade"
                   value="<?php echo esc_attr($flosc_profile_bar['guest']['upgrade_label'] ?? 'Upgrade to Pro'); ?>"
                   class="regular-text">
            <p class="description">Button shown in guest dropdown to encourage purchase.</p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Guest Dropdown Menu — Dynamic Repeater
     ═══════════════════════════════════════════════════════════════════ -->
<h3 class="flosc-ui-section-title">Guest Dropdown Menu</h3>
<p class="description">
    Menu items for logged-in users who have not purchased.
</p>

<div id="flosc-guest-menu-repeater">
    <table class="widefat flosc-ui-menu-table">
        <thead>
            <tr>
                <th class="flosc-ui-col-index">#</th>
                <th>Label</th>
                <th>Action</th>
                <th class="flosc-ui-col-actions"></th>
            </tr>
        </thead>
        <tbody id="flosc-guest-menu-items-body">
            <?php foreach ($flosc_guest_menu as $flosc_i => $flosc_item): ?>
            <tr class="flosc-guest-menu-row">
                <td class="flosc-guest-menu-row-number"><?php echo esc_html( (string) ($flosc_i + 1) ); ?></td>
                <td>
                    <input type="text" name="guest_menu_label[]"
                           value="<?php echo esc_attr($flosc_item['label']); ?>"
                           class="regular-text" placeholder="Menu label" required>
                </td>
                <td>
                    <select name="guest_menu_action[]" class="flosc-ui-action-select">
                        <?php foreach ($flosc_available_actions as $flosc_val => $flosc_desc): ?>
                        <option value="<?php echo esc_attr($flosc_val); ?>" <?php selected($flosc_item['action'], $flosc_val); ?>>
                            <?php echo esc_html($flosc_desc); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="button flosc-guest-remove-menu-item" title="Remove item">&times;</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="flosc-ui-menu-actions-wrap">
        <button type="button" class="button" id="flosc-guest-add-menu-item">+ Add Menu Item</button>
    </p>
</div>

<?php ob_start(); ?>
(function() {
    const tbody = document.getElementById('flosc-guest-menu-items-body');
    const addBtn = document.getElementById('flosc-guest-add-menu-item');
    const actions = <?php echo wp_json_encode($flosc_available_actions); ?>;

    function buildActionOptions(selected) {
        let html = '';
        for (const [val, desc] of Object.entries(actions)) {
            const sel = val === selected ? ' selected' : '';
            html += `<option value="${val}"${sel}>${desc}</option>`;
        }
        return html;
    }

    function renumber() {
        tbody.querySelectorAll('.flosc-guest-menu-row').forEach((row, i) => {
            row.querySelector('.flosc-guest-menu-row-number').textContent = i + 1;
        });
    }

    addBtn.addEventListener('click', function() {
        const tr = document.createElement('tr');
        tr.className = 'flosc-guest-menu-row';
        tr.innerHTML = `
            <td class="flosc-guest-menu-row-number"></td>
            <td><input type="text" name="guest_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
            <td><select name="guest_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_quiz')}</select></td>
            <td><button type="button" class="button flosc-guest-remove-menu-item" title="Remove item">&times;</button></td>
        `;
        tbody.appendChild(tr);
        renumber();
        tr.querySelector('input').focus();
    });

    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('flosc-guest-remove-menu-item')) {
            e.target.closest('.flosc-guest-menu-row').remove();
            renumber();
        }
    });
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Member State
     ═══════════════════════════════════════════════════════════════════ -->
<h3 class="flosc-ui-section-title">Member State</h3>
<p class="description">Shown to logged-in users who have purchased. No upgrade button. Avatar and name from WordPress.</p>
<table class="form-table">
    <tr>
        <th scope="row">Badge Text</th>
        <td>
            <input type="text" name="profile_bar_member_badge"
                   value="<?php echo esc_attr($flosc_profile_bar['member']['badge'] ?? 'Member'); ?>"
                   class="regular-text">
            <p class="description">Shown below the user's name (e.g., "Member", "Pro", "Premium").</p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Member Dropdown Menu — Dynamic Repeater
     ═══════════════════════════════════════════════════════════════════ -->
<h3 class="flosc-ui-section-title">Member Dropdown Menu</h3>
<p class="description">
    Menu items for logged-in users who have purchased.
</p>

<div id="flosc-member-menu-repeater">
    <table class="widefat flosc-ui-menu-table">
        <thead>
            <tr>
                <th class="flosc-ui-col-index">#</th>
                <th>Label</th>
                <th>Action</th>
                <th class="flosc-ui-col-actions"></th>
            </tr>
        </thead>
        <tbody id="flosc-member-menu-items-body">
            <?php foreach ($flosc_member_menu as $flosc_i => $flosc_item): ?>
            <tr class="flosc-member-menu-row">
                <td class="flosc-member-menu-row-number"><?php echo esc_html( (string) ($flosc_i + 1) ); ?></td>
                <td>
                    <input type="text" name="member_menu_label[]"
                           value="<?php echo esc_attr($flosc_item['label']); ?>"
                           class="regular-text" placeholder="Menu label" required>
                </td>
                <td>
                    <select name="member_menu_action[]" class="flosc-ui-action-select">
                        <?php foreach ($flosc_available_actions as $flosc_val => $flosc_desc): ?>
                        <option value="<?php echo esc_attr($flosc_val); ?>" <?php selected($flosc_item['action'], $flosc_val); ?>>
                            <?php echo esc_html($flosc_desc); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="button flosc-member-remove-menu-item" title="Remove item">&times;</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="flosc-ui-menu-actions-wrap">
        <button type="button" class="button" id="flosc-member-add-menu-item">+ Add Menu Item</button>
    </p>
</div>

<?php ob_start(); ?>
(function() {
    const tbody = document.getElementById('flosc-member-menu-items-body');
    const addBtn = document.getElementById('flosc-member-add-menu-item');
    const actions = <?php echo wp_json_encode($flosc_available_actions); ?>;

    function buildActionOptions(selected) {
        let html = '';
        for (const [val, desc] of Object.entries(actions)) {
            const sel = val === selected ? ' selected' : '';
            html += `<option value="${val}"${sel}>${desc}</option>`;
        }
        return html;
    }

    function renumber() {
        tbody.querySelectorAll('.flosc-member-menu-row').forEach((row, i) => {
            row.querySelector('.flosc-member-menu-row-number').textContent = i + 1;
        });
    }

    addBtn.addEventListener('click', function() {
        const tr = document.createElement('tr');
        tr.className = 'flosc-member-menu-row';
        tr.innerHTML = `
            <td class="flosc-member-menu-row-number"></td>
            <td><input type="text" name="member_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
            <td><select name="member_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_lesson_library')}</select></td>
            <td><button type="button" class="button flosc-member-remove-menu-item" title="Remove item">&times;</button></td>
        `;
        tbody.appendChild(tr);
        renumber();
        tr.querySelector('input').focus();
    });

    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('flosc-member-remove-menu-item')) {
            e.target.closest('.flosc-member-menu-row').remove();
            renumber();
        }
    });
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Login Destination
     ═══════════════════════════════════════════════════════════════════ -->
<h2 class="title">Login Destination</h2>
<p class="description">Where should users land after logging in from the FLOSC chat?</p>

<table class="form-table">
    <tr>
        <th scope="row">After login, redirect to</th>
        <td>
            <input type="url" name="flosc_login_destination" class="large-text"
                   value="<?php echo esc_attr($flosc_login_destination); ?>"
                   placeholder="<?php echo esc_attr($flosc_app_url); ?>">
            <p class="description">
                Full URL. Leave empty to default to this flow's chat URL: <code><?php echo esc_html($flosc_app_url); ?></code><br>
                Only applies when login originates from the FLOSC chat. Normal WordPress logins are unaffected.
            </p>
        </td>
    </tr>
</table>

<!-- ═══════════════════════════════════════════════════════════════════
     SECTION: Action Reference
     ═══════════════════════════════════════════════════════════════════ -->
<h2 class="title">Available In-Chat Actions</h2>
<p class="description">These actions can be assigned to Visitor, Guest, and Member menu items above. All stay in-chat — no page navigation (except Log In/Out).</p>

<table class="widefat striped flosc-ui-actions-table">
    <thead>
        <tr>
            <th>Action Key</th>
            <th>What Happens</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($flosc_available_actions as $flosc_key => $flosc_desc): ?>
        <tr>
            <td><code><?php echo esc_html($flosc_key); ?></code></td>
            <td><?php echo esc_html($flosc_desc); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
