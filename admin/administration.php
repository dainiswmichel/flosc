<?php
/**
 * FLOSC Administration Tab
 *
 * Global controls for account plan and debug mode.
 */

if (!defined('ABSPATH')) exit;

$current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$administration_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-administration';

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

$first_name = $user_id > 0 ? (string) get_user_meta($user_id, 'first_name', true) : '';
$last_name = $user_id > 0 ? (string) get_user_meta($user_id, 'last_name', true) : '';
$roles = (!empty($current_user->roles) && is_array($current_user->roles)) ? implode(', ', $current_user->roles) : '';

$account_plan = get_option('flosc_account_plan', 'free');
if (!in_array($account_plan, ['free', 'paid', 'enterprise'], true)) {
    $account_plan = 'free';
}

$manual_purchases_raw = (string) get_option('flosc_account_purchases_manual', '');
$manual_purchase_lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $manual_purchases_raw))));

$debug_mode = get_option('flosc_debug_mode', 'inherit');
if (!in_array($debug_mode, ['inherit', 'on', 'off'], true)) {
    $debug_mode = 'inherit';
}

$wp_debug = defined('WP_DEBUG') && WP_DEBUG;
$effective_debug = ($debug_mode === 'on') ? true : (($debug_mode === 'off') ? false : $wp_debug);

$runtime_access = 'visitor';
if ($user_id > 0) {
    $runtime_access = 'free';
    if (function_exists('flosc') && method_exists(flosc(), 'sale')) {
        $runtime_access = flosc()->sale()->access()->can_access($user_id, 'full') ? 'paid' : 'free';
    }
}
?>

<div class="card" style="max-width: 980px; padding: 18px 20px; margin-top: 12px;">
    <h2 style="margin-top: 0; display:flex; align-items:center; gap:12px;">
        <span>Administration</span>
        <a href="<?php echo esc_url($administration_docs_url); ?>" style="font-size:12px; text-decoration:none; color:#2271b1; margin-left:auto;">Docs</a>
    </h2>
    <p class="description" style="margin-top: 0;">
        Central account and debug controls.
    </p>

    <table class="widefat striped" style="margin: 16px 0 22px; max-width: 980px;">
        <thead>
            <tr>
                <th>Runtime Status</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Current user</td>
                <td><?php echo esc_html($current_user->user_login ?: 'Not logged in'); ?></td>
            </tr>
            <tr>
                <td>Current access level</td>
                <td><?php echo esc_html(ucfirst($runtime_access)); ?></td>
            </tr>
            <tr>
                <td>Configured account plan</td>
                <td><?php echo esc_html(ucfirst($account_plan)); ?></td>
            </tr>
            <tr>
                <td>Configured purchased items</td>
                <td><?php echo esc_html((string)count($manual_purchase_lines)); ?></td>
            </tr>
            <tr>
                <td>WP_DEBUG</td>
                <td><?php echo $wp_debug ? 'ON' : 'OFF'; ?></td>
            </tr>
            <tr>
                <td>FLOSC debug mode</td>
                <td><?php echo esc_html(strtoupper($debug_mode)); ?></td>
            </tr>
            <tr>
                <td>Effective FLOSC_DEBUG</td>
                <td><?php echo $effective_debug ? 'ON' : 'OFF'; ?></td>
            </tr>
            <tr>
                <td>Debug badge visibility</td>
                <td><?php echo $effective_debug ? 'Visible' : 'Hidden'; ?></td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin: 14px 0 10px;">Debug Display</h3>
    <table class="form-table" style="margin-top: 0;">
        <tr>
            <th scope="row"><label for="flosc_debug_mode">Debug mode</label></th>
            <td>
                <select id="flosc_debug_mode" name="flosc_debug_mode">
                    <option value="inherit" <?php selected($debug_mode, 'inherit'); ?>>Use WordPress setting (WP_DEBUG)</option>
                    <option value="off" <?php selected($debug_mode, 'off'); ?>>Disable FLOSC debug output</option>
                    <option value="on" <?php selected($debug_mode, 'on'); ?>>Enable FLOSC debug output</option>
                </select>
                <p class="description">Set this to "Disable FLOSC debug output" to remove the FLOSC DEBUG badge.</p>
            </td>
        </tr>
    </table>

    <h3 style="margin: 14px 0 10px;">User Profile Parameters</h3>
    <table class="widefat striped" style="margin: 0 0 22px; max-width: 980px;">
        <thead>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>User ID</td>
                <td><?php echo esc_html($user_id > 0 ? (string) $user_id : 'Not logged in'); ?></td>
            </tr>
            <tr>
                <td>Login</td>
                <td><?php echo esc_html($current_user->user_login ?: 'Not logged in'); ?></td>
            </tr>
            <tr>
                <td>Display name</td>
                <td><?php echo esc_html($current_user->display_name ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <td>Email</td>
                <td><?php echo esc_html($current_user->user_email ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <td>First name</td>
                <td><?php echo esc_html($first_name !== '' ? $first_name : 'Not set'); ?></td>
            </tr>
            <tr>
                <td>Last name</td>
                <td><?php echo esc_html($last_name !== '' ? $last_name : 'Not set'); ?></td>
            </tr>
            <tr>
                <td>Roles</td>
                <td><?php echo esc_html($roles !== '' ? $roles : 'None'); ?></td>
            </tr>
            <tr>
                <td>Registered</td>
                <td><?php echo esc_html($current_user->user_registered && $current_user->user_registered !== '0000-00-00 00:00:00' ? $current_user->user_registered : 'Not set'); ?></td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin: 0 0 10px;">Account Management</h3>
    <table class="form-table" style="margin-top: 0;">
        <tr>
            <th scope="row"><label for="flosc_account_plan">Account plan</label></th>
            <td>
                <select id="flosc_account_plan" name="flosc_account_plan">
                    <option value="free" <?php selected($account_plan, 'free'); ?>>Free</option>
                    <option value="paid" <?php selected($account_plan, 'paid'); ?>>Paid</option>
                    <option value="enterprise" <?php selected($account_plan, 'enterprise'); ?>>Enterprise</option>
                </select>
                <p class="description">Stored as FLOSC account metadata.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_account_purchases_manual">Purchased items (manual)</label></th>
            <td>
                <textarea id="flosc_account_purchases_manual" name="flosc_account_purchases_manual" rows="6" class="large-text" placeholder="One item per line, e.g.&#10;LeSAEp Advanced Bundle&#10;LeSAEp Member Access"><?php echo esc_textarea($manual_purchases_raw); ?></textarea>
                <p class="description">Enter one item per line.</p>
            </td>
        </tr>
    </table>

    <h3 style="margin: 14px 0 10px;">Configured Purchased Items</h3>
    <div class="card" style="padding: 12px 14px; margin: 0 0 16px; max-width: 980px;">
        <?php if (!empty($manual_purchase_lines)): ?>
            <ol style="margin: 0 0 0 18px;">
                <?php foreach ($manual_purchase_lines as $item): ?>
                    <li><?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p style="margin: 0;">No items listed.</p>
        <?php endif; ?>
    </div>
</div>
