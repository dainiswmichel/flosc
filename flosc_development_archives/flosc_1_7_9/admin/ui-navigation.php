<?php
/**
 * FLOSC UI & Navigation Settings
 * Admin page for visitor menu, profile menu, and navigation configuration
 *
 * @since 1.8.0
 */

if (!defined('ABSPATH')) exit;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flosc_ui_nav_nonce'])) {
    if (!wp_verify_nonce($_POST['flosc_ui_nav_nonce'], 'flosc_ui_nav_save')) {
        die('Security check failed');
    }

    // Save visitor menu items
    if (isset($_POST['visitor_menu'])) {
        $visitor_menu = [];
        foreach ($_POST['visitor_menu'] as $action => $item) {
            $visitor_menu[$action] = [
                'label' => sanitize_text_field($item['label']),
                'enabled' => isset($item['enabled'])
            ];
        }
        update_option('flosc_visitor_menu_items', $visitor_menu);
    }

    // Save login destination
    if (isset($_POST['login_destination'])) {
        update_option('flosc_login_destination', sanitize_text_field($_POST['login_destination']));
    }

    // Save visitor bar settings
    if (isset($_POST['visitor_bar_text'])) {
        update_option('flosc_visitor_bar_text', sanitize_text_field($_POST['visitor_bar_text']));
    }
    if (isset($_POST['visitor_bar_icon'])) {
        update_option('flosc_visitor_bar_icon', sanitize_text_field($_POST['visitor_bar_icon']));
    }

    echo '<div class="notice notice-success is-dismissible"><p>✓ UI & Navigation settings saved successfully!</p></div>';
}

// Get current settings
$visitor_menu = get_option('flosc_visitor_menu_items', [
    'signup' => ['label' => 'Sign Up', 'enabled' => true],
    'login'  => ['label' => 'Log In',  'enabled' => true],
    'quiz'   => ['label' => 'Take Quiz','enabled' => true],
]);

$login_destination = get_option('flosc_login_destination', 'account');
$visitor_bar_text = get_option('flosc_visitor_bar_text', 'Try our free quiz to assess your skills! 🎯');
$visitor_bar_icon = get_option('flosc_visitor_bar_icon', '👋');
?>

<div class="wrap">
    <h1>UI & Navigation Settings</h1>
    <p>Configure visitor engagement, profile menus, and navigation options.</p>

    <form method="post" action="">
        <?php wp_nonce_field('flosc_ui_nav_save', 'flosc_ui_nav_nonce'); ?>

        <!-- Visitor Profile Menu -->
        <div style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px; margin: 20px 0;">
            <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">👤</span>
                Visitor Profile Menu
            </h2>
            <p style="color: #50575e; margin-bottom: 20px;">
                Configure the dropdown menu that appears for non-logged-in visitors. These items appear when visitors click their profile icon in the sidebar.
            </p>

            <div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>📖 How it works:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>signup</strong> — Opens registration modal</li>
                    <li><strong>login</strong> — Redirects to WordPress login (destination configurable below)</li>
                    <li><strong>quiz</strong> — Triggers IVR message: "start free quiz" (opens quiz modal)</li>
                </ul>
                <p style="margin: 10px 0 0; color: #50575e; font-size: 13px;">
                    ℹ️ The "quiz" action sends the message "start free quiz" to the IVR, which must have a corresponding UserInput configured.
                </p>
            </div>

            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 120px;">Action</th>
                        <th>Label</th>
                        <th style="width: 100px; text-align: center;">Enabled</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitor_menu as $action => $item): ?>
                    <tr>
                        <td><code><?php echo esc_html($action); ?></code></td>
                        <td>
                            <input type="text"
                                   name="visitor_menu[<?php echo esc_attr($action); ?>][label]"
                                   value="<?php echo esc_attr($item['label']); ?>"
                                   style="width: 100%; padding: 6px 10px; border: 1px solid #c3c4c7; border-radius: 2px;">
                        </td>
                        <td style="text-align: center;">
                            <input type="checkbox"
                                   name="visitor_menu[<?php echo esc_attr($action); ?>][enabled]"
                                   <?php checked($item['enabled'] ?? true); ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; font-size: 13px;">
                <strong>⚠️ Important:</strong> If you disable all menu items, visitors will see an empty dropdown. Keep at least one item enabled for good UX.
            </p>
        </div>

        <!-- Login Destination -->
        <div style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px; margin: 20px 0;">
            <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🚪</span>
                Login Destination
            </h2>
            <p style="color: #50575e; margin-bottom: 20px;">
                Where should users go after clicking "Log In" from the visitor menu?
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">Redirect After Login</th>
                    <td>
                        <select name="login_destination" style="min-width: 300px; padding: 6px 10px;">
                            <option value="account" <?php selected($login_destination, 'account'); ?>>My Account (WooCommerce)</option>
                            <option value="profile" <?php selected($login_destination, 'profile'); ?>>User Profile</option>
                            <option value="home" <?php selected($login_destination, 'home'); ?>>Home Page</option>
                            <option value="chat" <?php selected($login_destination, 'chat'); ?>>FLOSC Chat (Current Page)</option>
                        </select>
                        <p class="description">
                            Default: "My Account" redirects to <code>/my-account/</code> (WooCommerce).
                            "FLOSC Chat" returns users to the current chat page after login.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Visitor Engagement Bar -->
        <div style="background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px; margin: 20px 0;">
            <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📢</span>
                Visitor Engagement Bar
            </h2>
            <p style="color: #50575e; margin-bottom: 20px;">
                A dismissible banner that slides down for non-logged-in visitors to encourage engagement.
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">Bar Text</th>
                    <td>
                        <input type="text"
                               name="visitor_bar_text"
                               value="<?php echo esc_attr($visitor_bar_text); ?>"
                               style="width: 100%; max-width: 600px; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px;">
                        <p class="description">
                            Text displayed in the visitor bar. Example: "Try our free quiz to assess your skills! 🎯"
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Icon/Emoji</th>
                    <td>
                        <input type="text"
                               name="visitor_bar_icon"
                               value="<?php echo esc_attr($visitor_bar_icon); ?>"
                               style="width: 80px; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 2px; text-align: center; font-size: 20px;">
                        <p class="description">
                            Single emoji or icon shown next to the text. Example: 👋 🎯 💡 ⚡
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- IVR Message Triggers Reference -->
        <div style="background: #f9fafb; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px; margin: 20px 0;">
            <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📋</span>
                IVR Message Triggers Reference
            </h2>
            <p style="color: #50575e; margin-bottom: 15px;">
                When visitor menu actions trigger IVR messages, they send specific phrases. Make sure your IVR configuration includes corresponding UserInput entries.
            </p>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>Menu Action</th>
                        <th>IVR Message Sent</th>
                        <th>Required IVR UserInput</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>quiz</code></td>
                        <td><code>start free quiz</code></td>
                        <td>
                            <code style="font-size: 11px; display: block; background: #f0f0f0; padding: 8px; border-radius: 2px;">
                                UserInput: Start free quiz<br>
                                Keywords: quiz, test, assessment<br>
                                Action: open_quiz
                            </code>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p style="margin: 15px 0 0; padding: 12px; background: #e7f3ff; border-left: 4px solid #2271b1; font-size: 13px;">
                <strong>💡 Best Practice:</strong> Use consistent, normalized phrases across your IVR.
                The phrase "start free quiz" is now standardized in FLOSC v1.8.0.
                If you customize visitor menu actions, ensure your IVR has matching UserInput entries.
            </p>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                💾 Save UI & Navigation Settings
            </button>
        </p>
    </form>
</div>

<style>
.widefat th {
    font-weight: 600;
    background: #f9fafb;
}
.widefat td, .widefat th {
    padding: 12px;
}
</style>
