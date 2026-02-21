<?php
/**
 * FLOSC Admin — UI & Navigation Settings
 * v1.8.0: Configurable user profile bar (3 states), login destination, engagement bar
 * 
 * @package FLOSC
 * @since 1.8.0
 */

if (!defined('ABSPATH')) exit;

// Get current values
$visitor_menu = get_option('flosc_visitor_menu_items', [
    'signup' => ['label' => 'Sign Up', 'enabled' => true],
    'login'  => ['label' => 'Log In',  'enabled' => true],
    'quiz'   => ['label' => 'Take Quiz','enabled' => true],
]);

$login_destination = get_option('flosc_login_destination', 'flosc_chat');

// Profile bar defaults for each state
$profile_bar = get_option('flosc_profile_bar', [
    'visitor' => [
        'name'  => 'Visitor',
        'badge' => 'Sign in to get started',
        'icon'  => '👋',
    ],
    'guest' => [
        'badge' => 'Guest',
        'upgrade_label' => 'Upgrade to Pro',
    ],
    'member' => [
        'badge' => 'Member',
    ],
]);

// Handle save
if (isset($_POST['flosc_ui_nav_nonce']) && wp_verify_nonce($_POST['flosc_ui_nav_nonce'], 'flosc_ui_nav_save')) {
    
    // Visitor menu items
    $new_menu = [];
    foreach (['signup', 'login', 'quiz'] as $key) {
        $new_menu[$key] = [
            'label' => sanitize_text_field($_POST['visitor_menu_label_' . $key] ?? ''),
            'enabled' => isset($_POST['visitor_menu_enabled_' . $key]),
        ];
    }
    update_option('flosc_visitor_menu_items', $new_menu);
    $visitor_menu = $new_menu;

    // Login destination
    $login_destination = sanitize_text_field($_POST['flosc_login_destination'] ?? 'flosc_chat');
    update_option('flosc_login_destination', $login_destination);

    // Profile bar per-state settings
    $profile_bar = [
        'visitor' => [
            'name'  => sanitize_text_field($_POST['profile_bar_visitor_name'] ?? 'Visitor'),
            'badge' => sanitize_text_field($_POST['profile_bar_visitor_badge'] ?? 'Sign in to get started'),
            'icon'  => sanitize_text_field($_POST['profile_bar_visitor_icon'] ?? '👋'),
        ],
        'guest' => [
            'badge' => sanitize_text_field($_POST['profile_bar_guest_badge'] ?? 'Guest'),
            'upgrade_label' => sanitize_text_field($_POST['profile_bar_guest_upgrade'] ?? 'Upgrade to Pro'),
        ],
        'member' => [
            'badge' => sanitize_text_field($_POST['profile_bar_member_badge'] ?? 'Member'),
        ],
    ];
    update_option('flosc_profile_bar', $profile_bar);

    echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
}

$app_url = flosc()->get_app_url();
?>

<div class="wrap">
    <h1>UI & Navigation</h1>
    <p>Configure how visitors and members interact with FLOSC's interface elements.</p>

    <form method="post">
        <?php wp_nonce_field('flosc_ui_nav_save', 'flosc_ui_nav_nonce'); ?>

        <!-- Section: User Profile Bar -->
        <h2 class="title">User Profile Bar</h2>
        <p class="description">The profile bar appears at the bottom of the sidebar for all users. Its avatar, name, badge text, and dropdown menu change based on user state.</p>

        <h3>Visitor State</h3>
        <p class="description">Shown to users who are not logged in.</p>
        <table class="form-table">
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
                           value="<?php echo esc_attr($profile_bar['visitor']['badge'] ?? 'Sign in to get started'); ?>"
                           class="large-text">
                    <p class="description">Subtitle shown below the visitor name.</p>
                </td>
            </tr>
        </table>

        <h3>Visitor Dropdown Menu</h3>
        <p class="description">Menu items shown when a visitor clicks the profile bar.</p>
        <table class="form-table">
            <?php foreach (['signup' => 'Sign Up', 'login' => 'Log In', 'quiz' => 'Take Quiz'] as $key => $default_label): 
                $item = $visitor_menu[$key] ?? ['label' => $default_label, 'enabled' => true];
                $actions = [
                    'signup' => 'Opens the registration modal (inline in FLOSC)',
                    'login'  => 'Redirects to the login destination configured below',
                    'quiz'   => 'Sends "Take quiz" to chat, which triggers the IVR quiz flow',
                ];
            ?>
            <tr>
                <th scope="row">
                    <label>
                        <input type="checkbox" name="visitor_menu_enabled_<?php echo esc_attr($key); ?>" 
                               value="1" <?php checked(!empty($item['enabled'])); ?>>
                        <?php echo esc_html(ucfirst($key)); ?>
                    </label>
                </th>
                <td>
                    <input type="text" name="visitor_menu_label_<?php echo esc_attr($key); ?>" 
                           value="<?php echo esc_attr($item['label'] ?? $default_label); ?>" 
                           class="regular-text">
                    <p class="description"><?php echo esc_html($actions[$key]); ?></p>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h3>Guest State</h3>
        <p class="description">Shown to logged-in users who have not purchased. Avatar and name are pulled from their WordPress account.</p>
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

        <!-- Section: Login Destination -->
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

        <!-- Note: The old Visitor Engagement Bar (popup banner) has been replaced by
             the unified User Profile Bar section above. Visitor avatar, name, and badge
             are now configured under "Visitor State". -->

        <!-- Section: IVR Trigger Reference -->
        <h2 class="title">Quiz Trigger Reference</h2>
        <p class="description">How user phrases map to quiz actions through the IVR system.</p>

        <table class="widefat striped" style="max-width: 700px;">
            <thead>
                <tr>
                    <th>User says</th>
                    <th>IVR matches</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>Start free quiz</code></td>
                    <td>Exact match on <code>UserInput</code></td>
                    <td><code>open_quiz</code> — opens quiz modal</td>
                </tr>
                <tr>
                    <td><code>Take quiz</code></td>
                    <td>Keyword match — <code>Keywords: quiz, test, assessment, evaluate, take quiz, try quiz</code></td>
                    <td>Same IVR message → <code>open_quiz</code></td>
                </tr>
                <tr>
                    <td><code>quiz</code>, <code>test</code>, <code>assessment</code></td>
                    <td>Keyword match (single word)</td>
                    <td>Same IVR message → <code>open_quiz</code></td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top: 12px;">
            <strong>To add custom trigger phrases:</strong> Edit the IVR file's <code>Keywords:</code> line for the quiz message.<br>
            Navigate to <strong>FLOSC → IVR Messages</strong> and find the "Start Free Quiz" message.
        </p>

        <?php submit_button('Save UI & Navigation Settings'); ?>
    </form>
</div>
