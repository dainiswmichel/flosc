<?php
/**
 * FLOSC Admin — Chat Navigation Settings (Style & Nav Tab Partial)
 *
 * Included inside settings.php <form> — no own form/nonce/submit needed.
 * Save handler lives in settings.php under the `if ($flosc_active_tab === 'style')` block.
 *
 * @package FLOSC
 * @since 8.0.1
 */

if (!defined('ABSPATH')) exit;

// Visitor menu
$flosc_visitor_menu_raw = get_option('flosc_visitor_menu_items', []);
$flosc_visitor_menu = [];
if (!empty($flosc_visitor_menu_raw)) {
    // Backward compatibility: old associative format (signup/login/quiz)
    if (isset($flosc_visitor_menu_raw['signup']) || isset($flosc_visitor_menu_raw['login']) || isset($flosc_visitor_menu_raw['quiz'])) {
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
        $flosc_visitor_menu = $flosc_visitor_menu_raw;
    }
}
if (empty($flosc_visitor_menu)) {
    $flosc_visitor_menu = [
        ['label' => 'Sign Up',    'action' => 'open_registration'],
        ['label' => 'Log In',     'action' => 'open_login_modal'],
        ['label' => 'Take Quiz',  'action' => 'open_quiz'],
    ];
}

$flosc_guest_menu = get_option('flosc_guest_menu_items', []);
if (empty($flosc_guest_menu)) {
    $flosc_guest_menu = [
        ['label' => 'My Profile', 'action' => 'view_profile'],
        ['label' => 'Take Quiz',  'action' => 'open_quiz'],
        ['label' => 'Upgrade',    'action' => 'open_sandbox_purchase'],
        ['label' => 'Log Out',    'action' => 'logout'],
    ];
}

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
$flosc_app_url = flosc()->get_app_url();

$flosc_available_actions = [
    'open_registration'     => 'Sign Up — open registration modal',
    'open_login_modal'      => 'Log In — open login modal',
    'logout'                => 'Log Out — end session and return to visitor state',
    'open_quiz'             => 'Take Quiz — open quiz modal',
    'open_free_lesson'      => 'Free Lesson — open the free lesson',
    'open_lesson_library'   => 'Lesson Library — show all lessons',
    'open_quiz_library'     => 'Quiz Library — show all quizzes',
    'open_support'          => 'Support — open help/support',
    'open_sandbox_purchase' => 'Purchase — open purchase flow',
    'send_prompt'           => 'Send Prompt — type a message into the chatbot',
    'view_profile'          => 'My Profile — go to user profile page',
    'view_dashboard'        => 'Dashboard — go to WordPress dashboard',
];
?>

<?php
// Chat list chrome — flow-scoped labels / opening messages (not entitlements).
$flosc_flow_for_chat_list = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_new_chat_button_label = (string) ( $flosc_flow_for_chat_list['new_chat_button_label'] ?? 'New chat' );
$flosc_first_chat_title = (string) ( $flosc_flow_for_chat_list['first_chat_title'] ?? 'Our first chat :-)' );
$flosc_empty_chat_list_message = (string) ( $flosc_flow_for_chat_list['empty_chat_list_message'] ?? 'No chats yet' );
$flosc_guest_new_chat_welcome = (string) ( $flosc_flow_for_chat_list['guest_new_chat_welcome_message']
	?? 'Welcome back, what would you like to work on?' );
$flosc_member_new_chat_welcome = (string) ( $flosc_flow_for_chat_list['member_new_chat_welcome_message']
	?? 'Hi {NickName}, glad to be chatting with you! What would you like to work on in this session?' );
$flosc_member_levels_chat_url = add_query_arg(
	[
		'page' => 'flosc-settings',
		'ivr'  => $GLOBALS['flosc_current_ivr'] ?? '',
		'tab'  => 'member-levels',
		'view' => 'single',
	],
	admin_url( 'admin.php' )
);
?>

<h2 class="title">Chat Navigation</h2>
<p class="description">Configure visitor, guest, and member dropdown menus and login redirect behavior.</p>

<h3 class="flosc-ui-section-title">Chat list (sidebar)</h3>
<p class="description">Labels and opening messages for the guest/member chat list. Visitors never see multi-chat management. Guest max chats and limit message: <a href="<?php echo esc_url( $flosc_member_levels_chat_url ); ?>">Member Levels → Guest Access</a>.</p>
<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_new_chat_button_label">New chat button label</label></th>
		<td>
			<input type="text" class="regular-text" id="flow_new_chat_button_label" name="flow_new_chat_button_label"
				   value="<?php echo esc_attr( $flosc_new_chat_button_label ); ?>">
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_first_chat_title">First chat title</label></th>
		<td>
			<input type="text" class="regular-text" id="flow_first_chat_title" name="flow_first_chat_title"
				   value="<?php echo esc_attr( $flosc_first_chat_title ); ?>">
			<p class="description">Title for a user’s first saved session (sidebar list).</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_empty_chat_list_message">Empty list message</label></th>
		<td>
			<input type="text" class="regular-text" id="flow_empty_chat_list_message" name="flow_empty_chat_list_message"
				   value="<?php echo esc_attr( $flosc_empty_chat_list_message ); ?>">
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_guest_new_chat_welcome_message">Guest New chat opening message</label></th>
		<td>
			<textarea class="large-text" rows="2" id="flow_guest_new_chat_welcome_message" name="flow_guest_new_chat_welcome_message"><?php echo esc_textarea( $flosc_guest_new_chat_welcome ); ?></textarea>
			<p class="description">Shown when a guest starts a new chat (not the visitor intro). Placeholders: <code>{NickName}</code>, <code>{name}</code>, <code>{email}</code>, <code>{flow_name}</code>.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_member_new_chat_welcome_message">Member New chat opening message</label></th>
		<td>
			<textarea class="large-text" rows="2" id="flow_member_new_chat_welcome_message" name="flow_member_new_chat_welcome_message"><?php echo esc_textarea( $flosc_member_new_chat_welcome ); ?></textarea>
			<p class="description">Shown when a member starts a new chat. Same placeholders as guest.</p>
		</td>
	</tr>
</table>

<h3 class="flosc-ui-section-title">Visitor Dropdown Menu</h3>
<p class="description">Configure the menu items visitors see when they click the profile bar. All actions happen in-chat.</p>

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
    const actions = <?php echo wp_json_encode($flosc_available_actions); ?>;

    if (!tbody || !addBtn) {
        return;
    }

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

    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('flosc-remove-menu-item')) {
            e.target.closest('.flosc-menu-row').remove();
            renumber();
        }
    });
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<h3 class="flosc-ui-section-title">Guest Dropdown Menu</h3>
<p class="description">Menu items for logged-in users who have not purchased.</p>

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
                <td class="flosc-guest-menu-row-number"><?php echo esc_html((string) ($flosc_i + 1)); ?></td>
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

    if (!tbody || !addBtn) {
        return;
    }

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

<h3 class="flosc-ui-section-title">Member Dropdown Menu</h3>
<p class="description">Menu items for logged-in users who have purchased.</p>

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
                <td class="flosc-member-menu-row-number"><?php echo esc_html((string) ($flosc_i + 1)); ?></td>
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

    if (!tbody || !addBtn) {
        return;
    }

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
