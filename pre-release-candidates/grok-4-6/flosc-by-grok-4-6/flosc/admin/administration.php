<?php
/**
 * FLOSC Administration Tab
 *
 * Global controls for request protection and debug mode.
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

$flosc_protection_defaults = [
    'enabled'                  => '1',
    'anonymous_chat_limit'     => 60,
    'authenticated_chat_limit' => 120,
    'anonymous_ivr_limit'      => 120,
    'metered_compute_limit'    => 20,
    'visitor_compute_limit'    => 5,
    'retry_after_429'          => '0',
];
$flosc_protection = get_option('flosc_public_request_protection', []);
$flosc_protection = is_array($flosc_protection) ? array_merge($flosc_protection_defaults, $flosc_protection) : $flosc_protection_defaults;

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
        Public request protection and debug controls.
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

    <h3 class="flosc-admin-section-title flosc-admin-section-title-topless">Public Request Protection</h3>
    <table class="form-table flosc-admin-form-table">
        <tr>
            <th scope="row">Public request protection</th>
            <td>
                <label><input type="checkbox" name="flosc_public_request_protection[enabled]" value="1" <?php checked($flosc_protection['enabled'], '1'); ?>> Enable request-rate protection</label>
                <p class="description">Global for this FLOSC installation: rate limits public requests by visitor identity across every floscFlow. Keep enabled outside controlled testing.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_anonymous_chat_limit">Anonymous chat</label></th>
            <td><input id="flosc_anonymous_chat_limit" name="flosc_public_request_protection[anonymous_chat_limit]" type="number" min="1" max="10000" value="<?php echo esc_attr((string)$flosc_protection['anonymous_chat_limit']); ?>"> requests per hour</td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_authenticated_chat_limit">Authenticated chat</label></th>
            <td><input id="flosc_authenticated_chat_limit" name="flosc_public_request_protection[authenticated_chat_limit]" type="number" min="1" max="10000" value="<?php echo esc_attr((string)$flosc_protection['authenticated_chat_limit']); ?>"> requests per hour</td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_anonymous_ivr_limit">Anonymous IVR reads</label></th>
            <td><input id="flosc_anonymous_ivr_limit" name="flosc_public_request_protection[anonymous_ivr_limit]" type="number" min="1" max="10000" value="<?php echo esc_attr((string)$flosc_protection['anonymous_ivr_limit']); ?>"> requests per hour</td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_metered_compute_limit">Metered compute</label></th>
            <td><input id="flosc_metered_compute_limit" name="flosc_public_request_protection[metered_compute_limit]" type="number" min="1" max="10000" value="<?php echo esc_attr((string)$flosc_protection['metered_compute_limit']); ?>"> requests per hour</td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_visitor_compute_limit">Visitor compute</label></th>
            <td><input id="flosc_visitor_compute_limit" name="flosc_public_request_protection[visitor_compute_limit]" type="number" min="1" max="10000" value="<?php echo esc_attr((string)$flosc_protection['visitor_compute_limit']); ?>"> requests per hour</td>
        </tr>
        <tr>
            <th scope="row">Retry after HTTP 429</th>
            <td><label><input type="checkbox" name="flosc_public_request_protection[retry_after_429]" value="1" <?php checked($flosc_protection['retry_after_429'], '1'); ?>> Retry after refreshing the REST nonce</label></td>
        </tr>
    </table>

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
