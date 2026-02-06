<?php
/**
 * Flow Edit Tab: Team
 * 
 * User access management (admin only)
 */

if (!defined('ABSPATH')) exit;
?>

<form method="post">
    <?php wp_nonce_field('flosc_update_team'); ?>
    
    <h2 class="flosc-section-header">Team Access</h2>
    <p class="description">
        Select which Editors, Authors, and Contributors can manage this flow. 
        Administrators always have access to all flows.
    </p>
    
    <?php
    // Get all editors, authors, and contributors
    $team_users = get_users([
        'role__in' => ['editor', 'author', 'contributor'],
        'orderby' => 'display_name',
    ]);
    
    // Get users who currently have access
    $current_team = flosc_flows()->get_flow_users($flow_id);
    $current_team_ids = array_map(function($u) { return $u->ID; }, $current_team);
    ?>
    
    <?php if (empty($team_users)): ?>
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; text-align: center;">
            <p><em>No editors, authors, or contributors found.</em></p>
            <p>Only administrators can currently manage flows. Add users with these roles to assign them to specific flows.</p>
        </div>
    <?php else: ?>
        <table class="widefat" style="max-width: 600px;">
            <thead>
                <tr>
                    <th style="width: 40px;">Access</th>
                    <th>User</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team_users as $user): ?>
                    <tr>
                        <td style="text-align: center;">
                            <input type="checkbox" name="team_users[]" value="<?php echo $user->ID; ?>"
                                   <?php checked(in_array($user->ID, $current_team_ids)); ?>>
                        </td>
                        <td>
                            <strong><?php echo esc_html($user->display_name); ?></strong><br>
                            <small style="color: #6b7280;"><?php echo esc_html($user->user_email); ?></small>
                        </td>
                        <td><?php echo esc_html(ucfirst(implode(', ', $user->roles))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="flosc_update_team" class="button button-primary" value="Update Team">
        </p>
    <?php endif; ?>
    
    <hr style="margin: 40px 0;">
    
    <div style="background: #f0f6ff; border: 1px solid #c3dafe; padding: 15px; border-radius: 8px;">
        <strong>💡 Permission Levels</strong>
        <ul style="margin: 10px 0 0 20px;">
            <li><strong>Administrator:</strong> Full access to all flows and global settings</li>
            <li><strong>Editor/Author/Contributor:</strong> Can only access flows they're assigned to</li>
        </ul>
        <p style="margin-top: 10px;">
            Assigned users can edit the flow's content, IVR messages, and view analytics, 
            but cannot modify global settings or access other flows.
        </p>
    </div>
</form>
