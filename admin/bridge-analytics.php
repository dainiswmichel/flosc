<?php
/**
 * FLOSC Bridge Analytics Tab
 * 
 * v1.0.4 - TASK-010: Admin dashboard for bridge data analytics
 * 
 * The "bridge" is the state between quiz completion and purchase.
 * This dashboard shows:
 * - How many users are currently in bridge state
 * - Common weakness categories from quiz results
 * - Conversion rates from quiz → purchase
 * - Bridge state duration analytics
 * 
 * Bridge data is used for:
 * - Personalized offer targeting (IVR conditions: in_bridge_state, weakest_category)
 * - Sales message personalization
 * - Post-purchase content recommendations
 */

if (!defined('ABSPATH')) exit;

// Load bridge data manager
require_once FLOSC_PLUGIN_DIR . 'includes/class-bridge-data-manager.php';

// Get aggregate analytics
global $wpdb;

// Count users with bridge data
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only analytics aggregate query
$flosc_users_with_bridge = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only analytics aggregate query
    "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_flosc_bridge_data'"
);

// Count users currently in bridge state (quiz taken, not purchased)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only analytics aggregate query
$flosc_users_in_bridge = $wpdb->get_var(" // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only analytics aggregate query
    SELECT COUNT(DISTINCT um1.user_id)
    FROM {$wpdb->usermeta} um1
    WHERE um1.meta_key = '_flosc_bridge_data'
    AND NOT EXISTS (
        SELECT 1 FROM {$wpdb->usermeta} um2
        WHERE um2.user_id = um1.user_id
        AND um2.meta_key = '_flosc_purchased'
        AND um2.meta_value = '1'
    )
");

// Count users who converted (had bridge data and then purchased)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only analytics aggregate query
$flosc_users_converted = $wpdb->get_var(" // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only analytics aggregate query
    SELECT COUNT(DISTINCT um1.user_id)
    FROM {$wpdb->usermeta} um1
    INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
    WHERE um1.meta_key = '_flosc_bridge_data'
    AND um2.meta_key = '_flosc_purchased'
    AND um2.meta_value = '1'
");

// Calculate conversion rate
$flosc_conversion_rate = $flosc_users_with_bridge > 0 
    ? round(($flosc_users_converted / $flosc_users_with_bridge) * 100, 1)
    : 0;

// Get weakness category distribution
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only analytics distribution query
$flosc_weakness_data = $wpdb->get_results(" // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only analytics distribution query
    SELECT meta_value FROM {$wpdb->usermeta}
    WHERE meta_key = '_flosc_weakest_category'
    AND meta_value != ''
");

$flosc_weakness_counts = [];
foreach ($flosc_weakness_data as $flosc_row) {
    $flosc_category = $flosc_row->meta_value;
    if (!isset($flosc_weakness_counts[$flosc_category])) {
        $flosc_weakness_counts[$flosc_category] = 0;
    }
    $flosc_weakness_counts[$flosc_category]++;
}
arsort($flosc_weakness_counts);

// Get recent bridge users (last 10)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- read-only analytics recent users query
$flosc_recent_bridge_users = $wpdb->get_results("
    SELECT u.ID, u.user_email, u.display_name, um.meta_value as bridge_data
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
    WHERE um.meta_key = '_flosc_bridge_data'
    ORDER BY u.user_registered DESC
    LIMIT 10
");

?>

<h2>🌉 Bridge Analytics</h2>
<p class="description">
    Track users in the "bridge" state — between quiz completion and purchase. 
    Use this data to optimize IVR targeting and sales messaging.
</p>

<!-- Stats Overview -->
<div class="flosc-bridge-stats">
    <div class="flosc-bridge-stat">
        <div class="flosc-bridge-stat__icon">📊</div>
        <div class="flosc-bridge-stat__value"><?php echo esc_html($flosc_users_with_bridge); ?></div>
        <div class="flosc-bridge-stat__label">Total Quiz Completions</div>
    </div>
    
    <div class="flosc-bridge-stat">
        <div class="flosc-bridge-stat__icon">🌉</div>
        <div class="flosc-bridge-stat__value"><?php echo esc_html($flosc_users_in_bridge); ?></div>
        <div class="flosc-bridge-stat__label">Currently in Bridge</div>
    </div>
    
    <div class="flosc-bridge-stat">
        <div class="flosc-bridge-stat__icon">✅</div>
        <div class="flosc-bridge-stat__value"><?php echo esc_html($flosc_users_converted); ?></div>
        <div class="flosc-bridge-stat__label">Converted to Purchase</div>
    </div>
    
    <div class="flosc-bridge-stat">
        <div class="flosc-bridge-stat__icon">📈</div>
        <div class="flosc-bridge-stat__value"><?php echo esc_html($flosc_conversion_rate); ?>%</div>
        <div class="flosc-bridge-stat__label">Conversion Rate</div>
    </div>
</div>

<!-- IVR Targeting Info -->
<div class="flosc-banner flosc-banner--info">
    <strong>💡 IVR Targeting:</strong> Use these conditions in IVR messages:
    <ul style="margin: 10px 0 0 20px;">
        <li><code>in_bridge_state = true</code> — User completed quiz but hasn't purchased</li>
        <li><code>bridge_score >= 70</code> — User scored 70% or higher on quiz</li>
        <li><code>weakest_category = "category_name"</code> — Target by weakness area</li>
        <li><code>has_quiz_profile = true</code> — User has any quiz data stored</li>
    </ul>
</div>

<!-- Weakness Categories -->
<h3>📉 Top Weakness Categories</h3>
<p class="description">Most common areas where users struggle. Use for targeted content recommendations.</p>

<?php if (!empty($flosc_weakness_counts)): ?>
    <div class="flosc-weakness-chart">
        <?php 
        $flosc_max_count = max($flosc_weakness_counts);
        foreach ($flosc_weakness_counts as $flosc_category => $flosc_count): 
            $flosc_percentage = $flosc_max_count > 0 ? round(($flosc_count / $flosc_max_count) * 100) : 0;
        ?>
            <div class="flosc-weakness-bar">
                <div class="flosc-weakness-bar__label"><?php echo esc_html($flosc_category); ?></div>
                <div class="flosc-weakness-bar__track">
                    <div class="flosc-weakness-bar__fill" style="width: <?php echo esc_attr($flosc_percentage); ?>%;">
                        <?php echo esc_html($flosc_count); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="flosc-banner flosc-banner--warning">
        No weakness data yet. Weakness categories are populated when users complete the quiz.
    </div>
<?php endif; ?>

<!-- Recent Bridge Users -->
<h3>👥 Recent Bridge Users</h3>
<p class="description">Last 10 users who completed the quiz.</p>

<?php if (!empty($flosc_recent_bridge_users)): ?>
    <table class="flosc-table widefat">
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>Quiz Score</th>
                <th>Correct/Incorrect</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($flosc_recent_bridge_users as $flosc_user): 
                $flosc_bridge_data = maybe_unserialize($flosc_user->bridge_data);
                $flosc_score = $flosc_bridge_data['score'] ?? 0;
                $flosc_correct = count($flosc_bridge_data['correct_items'] ?? []);
                $flosc_incorrect = count($flosc_bridge_data['incorrect_items'] ?? []);
                $flosc_purchased = get_user_meta($flosc_user->ID, '_flosc_purchased', true);
            ?>
                <tr>
                    <td><?php echo esc_html($flosc_user->display_name ?: 'User #' . $flosc_user->ID); ?></td>
                    <td><?php echo esc_html($flosc_user->user_email); ?></td>
                    <td><?php echo esc_html($flosc_score); ?>%</td>
                    <td>
                        <span style="color: green;">✓ <?php echo esc_html($flosc_correct); ?></span> / 
                        <span style="color: red;">✗ <?php echo esc_html($flosc_incorrect); ?></span>
                    </td>
                    <td>
                        <?php if ($flosc_purchased): ?>
                            <span class="flosc-status flosc-status--active">Purchased</span>
                        <?php else: ?>
                            <span class="flosc-status flosc-status--pending">In Bridge</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="flosc-banner flosc-banner--warning">
        No bridge users yet. Users enter bridge state when they complete the quiz.
    </div>
<?php endif; ?>

<!-- API Endpoint Info -->
<h3>🔌 REST API Endpoint</h3>
<p class="description">Programmatic access to bridge data:</p>

<div class="flosc-code">
GET <?php echo esc_html(rest_url('flosc/v1/bridge-data')); ?>

Response:
{
    "success": true,
    "in_bridge_state": true,
    "has_profile": true,
    "bridge_data": {
        "score": 75,
        "correct_items": ["q1", "q3", "q5"],
        "incorrect_items": ["q2", "q4"],
        "completed_at": "2025-01-15 10:30:00"
    },
    "weakest_category": "category_name"
}
</div>
