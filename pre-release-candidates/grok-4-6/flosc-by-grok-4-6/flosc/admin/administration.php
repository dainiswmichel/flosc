<?php
/**
 * FLOSC Administration Tab
 *
 * Global controls for account plan and debug mode.
 */

if (!defined('ABSPATH')) exit;

$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_administration_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-administration';

$flosc_user = wp_get_current_user();
$flosc_user_id = get_current_user_id();

$flosc_first_name = $flosc_user_id > 0 ? (string) get_user_meta($flosc_user_id, 'first_name', true) : '';
$flosc_last_name = $flosc_user_id > 0 ? (string) get_user_meta($flosc_user_id, 'last_name', true) : '';
$flosc_roles = (!empty($flosc_user->roles) && is_array($flosc_user->roles)) ? implode(', ', $flosc_user->roles) : '';

$flosc_account_plan = get_option('flosc_account_plan', 'free');
if (!in_array($flosc_account_plan, ['free', 'paid', 'enterprise'], true)) {
    $flosc_account_plan = 'free';
}

$flosc_manual_purchases_raw = (string) get_option('flosc_account_purchases_manual', '');
$flosc_manual_purchase_lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $flosc_manual_purchases_raw))));

$flosc_debug_mode = get_option('flosc_debug_mode', 'inherit');
if (!in_array($flosc_debug_mode, ['inherit', 'on', 'off'], true)) {
    $flosc_debug_mode = 'inherit';
}

$flosc_wp_debug = defined('WP_DEBUG') && WP_DEBUG;
$flosc_effective_debug = ($flosc_debug_mode === 'on') ? true : (($flosc_debug_mode === 'off') ? false : $flosc_wp_debug);

$flosc_runtime_access = 'visitor';
if ($flosc_user_id > 0) {
    $flosc_runtime_access = 'free';
    if (function_exists('flosc') && method_exists(flosc(), 'sale')) {
        $flosc_runtime_access = flosc()->sale()->access()->can_access($flosc_user_id, 'full') ? 'paid' : 'free';
    }
}

$flosc_flow_id = sanitize_key(pathinfo($flosc_current_ivr, PATHINFO_FILENAME));
$flosc_can_assign_editors = current_user_can('manage_options') && $flosc_flow_id !== '';
$flosc_assignable_users = [];
$flosc_assigned_editor_ids = [];

if ($flosc_can_assign_editors) {
    $flosc_assignable_users = get_users([
        'role__in' => ['administrator', 'editor'],
        'orderby' => 'display_name',
    ]);

    $flosc_current_team = flosc_flows()->get_flow_users($flosc_flow_id);
    $flosc_assigned_editor_ids = array_map(static function($flosc_user) {
        return (int) $flosc_user->ID;
    }, $flosc_current_team);
}
?>

<div class="card flosc-admin-card">
    <h2 class="flosc-admin-title-row">
        <span>Administration</span>
        <a href="<?php echo esc_url($flosc_administration_docs_url); ?>" class="flosc-admin-docs-link">Docs</a>
    </h2>
    <p class="description flosc-admin-subtitle">
        Central account and debug controls.
    </p>

    <table class="widefat striped flosc-admin-status-table">
        <thead>
            <tr>
                <th>Runtime Status</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Current user</td>
                <td><?php echo esc_html($flosc_user->user_login ?: 'Not logged in'); ?></td>
            </tr>
            <tr>
                <td>Current access level</td>
                <td><?php echo esc_html(ucfirst($flosc_runtime_access)); ?></td>
            </tr>
            <tr>
                <td>Configured account plan</td>
                <td><?php echo esc_html(ucfirst($flosc_account_plan)); ?></td>
            </tr>
            <tr>
                <td>Configured purchased items</td>
                <td><?php echo esc_html((string)count($flosc_manual_purchase_lines)); ?></td>
            </tr>
            <tr>
                <td>WP_DEBUG</td>
                <td><?php echo esc_html( $flosc_wp_debug ? 'ON' : 'OFF' ); ?></td>
            </tr>
            <tr>
                <td>FLOSC debug mode</td>
                <td><?php echo esc_html(strtoupper($flosc_debug_mode)); ?></td>
            </tr>
            <tr>
                <td>Effective FLOSC_DEBUG</td>
                <td><?php echo esc_html( $flosc_effective_debug ? 'ON' : 'OFF' ); ?></td>
            </tr>
            <tr>
                <td>Debug badge visibility</td>
                <td><?php echo esc_html( $flosc_effective_debug ? 'Visible' : 'Hidden' ); ?></td>
            </tr>
        </tbody>
    </table>

    <h3 class="flosc-admin-section-title">Debug Display</h3>
    <table class="form-table flosc-admin-form-table">
        <tr>
            <th scope="row"><label for="flosc_debug_mode">Debug mode</label></th>
            <td>
                <select id="flosc_debug_mode" name="flosc_debug_mode">
                    <option value="inherit" <?php selected($flosc_debug_mode, 'inherit'); ?>>Use WordPress setting (WP_DEBUG)</option>
                    <option value="off" <?php selected($flosc_debug_mode, 'off'); ?>>Disable FLOSC debug output</option>
                    <option value="on" <?php selected($flosc_debug_mode, 'on'); ?>>Enable FLOSC debug output</option>
                </select>
                <p class="description">Set this to "Disable FLOSC debug output" to remove the FLOSC DEBUG badge.</p>
            </td>
        </tr>
    </table>

    <h3 class="flosc-admin-section-title">User Profile Parameters</h3>
    <table class="widefat striped flosc-admin-profile-table">
        <thead>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>User ID</td>
                <td><?php echo esc_html($flosc_user_id > 0 ? (string) $flosc_user_id : 'Not logged in'); ?></td>
            </tr>
            <tr>
                <td>Login</td>
                <td><?php echo esc_html($flosc_user->user_login ?: 'Not logged in'); ?></td>
            </tr>
            <tr>
                <td>Display name</td>
                <td><?php echo esc_html($flosc_user->display_name ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <td>Email</td>
                <td><?php echo esc_html($flosc_user->user_email ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <td>First name</td>
                <td><?php echo esc_html($flosc_first_name !== '' ? $flosc_first_name : 'Not set'); ?></td>
            </tr>
            <tr>
                <td>Last name</td>
                <td><?php echo esc_html($flosc_last_name !== '' ? $flosc_last_name : 'Not set'); ?></td>
            </tr>
            <tr>
                <td>Roles</td>
                <td><?php echo esc_html($flosc_roles !== '' ? $flosc_roles : 'None'); ?></td>
            </tr>
            <tr>
                <td>Registered</td>
                <td><?php echo esc_html($flosc_user->user_registered && $flosc_user->user_registered !== '0000-00-00 00:00:00' ? $flosc_user->user_registered : 'Not set'); ?></td>
            </tr>
        </tbody>
    </table>

    <h3 class="flosc-admin-section-title flosc-admin-section-title-topless">Account Management</h3>
    <table class="form-table flosc-admin-form-table">
        <tr>
            <th scope="row"><label for="flosc_account_plan">Account plan</label></th>
            <td>
                <select id="flosc_account_plan" name="flosc_account_plan">
                    <option value="free" <?php selected($flosc_account_plan, 'free'); ?>>Free</option>
                    <option value="paid" <?php selected($flosc_account_plan, 'paid'); ?>>Paid</option>
                    <option value="enterprise" <?php selected($flosc_account_plan, 'enterprise'); ?>>Enterprise</option>
                </select>
                <p class="description">Stored as FLOSC account metadata.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_account_purchases_manual">Purchased items (manual)</label></th>
            <td>
                <textarea id="flosc_account_purchases_manual" name="flosc_account_purchases_manual" rows="6" class="large-text" placeholder="One item per line, e.g.&#10;Pronunciation Advanced Bundle&#10;Member Access"><?php echo esc_textarea($flosc_manual_purchases_raw); ?></textarea>
                <p class="description">Enter one item per line.</p>
            </td>
        </tr>
    </table>

    <h3 class="flosc-admin-section-title">Configured Purchased Items</h3>
    <div class="card flosc-admin-purchases-card">
        <?php if (!empty($flosc_manual_purchase_lines)): ?>
            <ol class="flosc-admin-purchases-list">
                <?php foreach ($flosc_manual_purchase_lines as $flosc_item): ?>
                    <li><?php echo esc_html($flosc_item); ?></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="flosc-admin-purchases-empty">No items listed.</p>
        <?php endif; ?>
    </div>

    <?php if ($flosc_can_assign_editors): ?>
        <h3 class="flosc-admin-section-title flosc-admin-editors-title">Assign floscEditors for This Flow</h3>
        <p class="description flosc-admin-subtitle">
            Assign WordPress Editors (and optionally Administrators) to manage this flow. Site admin remains global floscAdmin authority.
        </p>

        <?php if (empty($flosc_assignable_users)): ?>
            <p><em>No Editor or Administrator users were found.</em></p>
        <?php else: ?>
            <table class="widefat striped flosc-admin-editors-table">
                <thead>
                    <tr>
                        <th class="flosc-admin-col-assign">Assign</th>
                        <th>User</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flosc_assignable_users as $flosc_candidate_user): ?>
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    name="flosc_flow_editors[]"
                                    value="<?php echo esc_attr((string) $flosc_candidate_user->ID); ?>"
                                    <?php checked(in_array((int) $flosc_candidate_user->ID, $flosc_assigned_editor_ids, true)); ?>
                                >
                            </td>
                            <td>
                                <strong><?php echo esc_html($flosc_candidate_user->display_name); ?></strong><br>
                                <small><?php echo esc_html($flosc_candidate_user->user_email); ?></small>
                            </td>
                            <td><?php echo esc_html(implode(', ', (array) $flosc_candidate_user->roles)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="submit" name="flosc_update_flosc_editors" value="1" class="button button-secondary">
                    Update floscEditors for <?php echo esc_html($flosc_flow_id); ?>
                </button>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
