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

// Public Request Protection. These were fixed numbers inside includes/flosc-rest.php
// until a live visitor was refused after one message and read "Rate limit reached"
// as the AI provider saying no. It was FLOSC's own per-IP bucket. A limit nobody
// can see or change is indistinguishable from a broken site.
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

$flosc_protection_fields = [
    'anonymous_chat_limit'     => ['Anonymous chat', 'Chat requests an hour from one visitor who is not logged in. A conversation costs more requests than reading does, so chat has its own budget.'],
    'authenticated_chat_limit' => ['Signed-in chat', 'Chat requests an hour from one logged-in person.'],
    'anonymous_ivr_limit'      => ['Anonymous content reads', 'Requests an hour to the other public endpoints — IVR content, offers, page context.'],
    'metered_compute_limit'    => ['Metered compute', 'Requests an hour to endpoints that spend tokens.'],
    'visitor_compute_limit'    => ['Visitor compute', 'The stricter ceiling for metered compute from someone who is not logged in.'],
];

// What FLOSC tells an AI provider about itself. Both on by default; both are
// one click to turn off. Nothing here reaches flosc.ai or da1.fm — it rides on
// the request the floscAdmin's own key is already paying for, and nowhere else.
$flosc_identity_defaults = [
    'enabled'   => '1',
    'send_site' => '1',
];
$flosc_identity = get_option('flosc_provider_identity', []);
$flosc_identity = is_array($flosc_identity) ? array_merge($flosc_identity_defaults, $flosc_identity) : $flosc_identity_defaults;

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
                <td>Public request protection</td>
                <td><?php echo esc_html($flosc_protection['enabled'] === '1' ? 'On' : 'Off'); ?></td>
            </tr>
            <tr>
                <td>Anonymous chat requests an hour</td>
                <td><?php echo esc_html((string) absint($flosc_protection['anonymous_chat_limit'])); ?></td>
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
    <p class="description flosc-admin-subtitle">
        Global for this FLOSC installation, not per floscFlow. The buckets are keyed by
        visitor IP and endpoint, so a per-flow setting would be a promise the storage
        cannot keep. Counted per hour, per visitor.
    </p>
    <table class="form-table flosc-admin-form-table">
        <tr>
            <th scope="row">Protection</th>
            <td>
                <label for="flosc_protection_enabled">
                    <input type="checkbox" id="flosc_protection_enabled" name="flosc_public_request_protection[enabled]" value="1" <?php checked($flosc_protection['enabled'], '1'); ?>>
                    Limit how often one visitor can call the public endpoints
                </label>
                <p class="description">Turning this off removes every limit below. Public endpoints are then bounded only by your host.</p>
            </td>
        </tr>
        <?php foreach ($flosc_protection_fields as $flosc_protection_key => $flosc_protection_field): ?>
            <tr>
                <th scope="row"><label for="flosc_protection_<?php echo esc_attr($flosc_protection_key); ?>"><?php echo esc_html($flosc_protection_field[0]); ?></label></th>
                <td>
                    <input type="number" min="1" max="10000" step="1"
                           id="flosc_protection_<?php echo esc_attr($flosc_protection_key); ?>"
                           name="flosc_public_request_protection[<?php echo esc_attr($flosc_protection_key); ?>]"
                           value="<?php echo esc_attr((string) absint($flosc_protection[$flosc_protection_key])); ?>"
                           class="small-text">
                    <span class="description">an hour</span>
                    <p class="description"><?php echo esc_html($flosc_protection_field[1]); ?></p>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <th scope="row">After a refusal</th>
            <td>
                <label for="flosc_protection_retry_after_429">
                    <input type="checkbox" id="flosc_protection_retry_after_429" name="flosc_public_request_protection[retry_after_429]" value="1" <?php checked($flosc_protection['retry_after_429'], '1'); ?>>
                    Let the chat client retry once after a refused request
                </label>
                <p class="description">Off by default. A refusal means the visitor is already at the limit, so retrying spends a second request from the same bucket and the limit arrives twice as fast.</p>
            </td>
        </tr>
    </table>

    <h3 class="flosc-admin-section-title">What FLOSC Tells Your AI Provider</h3>
    <p class="description flosc-admin-subtitle">
        Two headers on each call to the AI provider you configured. They never reach
        FLOSC, da1.fm or flosc.ai — only the provider your own API key already pays,
        on a request that is already carrying the whole conversation.
    </p>
    <table class="form-table flosc-admin-form-table">
        <tr>
            <th scope="row">Identify FLOSC</th>
            <td>
                <label for="flosc_identity_enabled">
                    <input type="checkbox" id="flosc_identity_enabled" name="flosc_provider_identity[enabled]" value="1" <?php checked($flosc_identity['enabled'], '1'); ?>>
                    Tell the provider which software is calling
                </label>
                <p class="description">
                    A <code>User-Agent</code> naming FLOSC, the personality builder, WordPress and PHP,
                    plus one <code>X-DA1-Trace</code> line: this install, this floscFlow, this
                    personality, which knowledge base, whether the person was a Visitor, Guest or
                    Member, and which message pair. The flow, personality and knowledge base ride as
                    scrambled short codes, so two turns from the same flow match each other without
                    the provider learning what the flow is called.
                </p>
                <p class="description">
                    <strong>Never sent:</strong> who the visitor is — no id, name, email, IP, or the page
                    they were reading. Nothing that narrows a turn to one person.
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">Site address</th>
            <td>
                <label for="flosc_identity_send_site">
                    <input type="checkbox" id="flosc_identity_send_site" name="flosc_provider_identity[send_site]" value="1" <?php checked($flosc_identity['send_site'], '1'); ?>>
                    Include this site's domain
                </label>
                <p class="description">
                    On by default. A provider that cannot tell which site it is serving cannot help you
                    when something goes wrong. Turn it off and the install code still identifies this
                    copy of FLOSC — just not where it lives.
                </p>
            </td>
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
