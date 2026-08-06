<?php
/**
 * FLOSC Bridge Analytics Tab
 *
 * Admin dashboard for bridge data analytics (quiz complete → purchase).
 * Uses WP_User_Query / get_users / get_user_meta — no direct database queries in this view.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FLOSC_PLUGIN_DIR . 'includes/class-bridge-data-manager.php';

/**
 * Count users matching a meta_query (admin analytics).
 *
 * @param array $meta_query WP meta_query clauses.
 * @return int
 */
// Users who have bridge payload stored.
$flosc_bridge_ids = function_exists( 'flosc_get_user_ids_for_meta' )
	? flosc_get_user_ids_for_meta( '_flosc_bridge_data' )
	: array();
$flosc_users_with_bridge = count( $flosc_bridge_ids );

// In bridge: has bridge data, not purchased.
$flosc_users_in_bridge = 0;
foreach ( $flosc_bridge_ids as $flosc_bid ) {
	$purchased = get_user_meta( (int) $flosc_bid, '_flosc_purchased', true );
	if ( '1' !== (string) $purchased && true !== $purchased ) {
		$flosc_users_in_bridge++;
	}
}

// Converted: bridge data + purchased.
$flosc_users_converted = 0;
foreach ( $flosc_bridge_ids as $flosc_bid ) {
	$purchased = get_user_meta( (int) $flosc_bid, '_flosc_purchased', true );
	if ( '1' === (string) $purchased || true === $purchased ) {
		$flosc_users_converted++;
	}
}

$flosc_conversion_rate = $flosc_users_with_bridge > 0
	? round( ( $flosc_users_converted / $flosc_users_with_bridge ) * 100, 1 )
	: 0;

// Weakness categories (sample up to 500 users with the meta).
$flosc_weakness_user_ids = function_exists( 'flosc_get_user_ids_for_meta' )
	? array_slice( flosc_get_user_ids_for_meta( '_flosc_weakest_category' ), 0, 500 )
	: array();
$flosc_weakness_counts = array();
foreach ( (array) $flosc_weakness_user_ids as $flosc_uid ) {
	$flosc_category = sanitize_text_field( (string) get_user_meta( (int) $flosc_uid, '_flosc_weakest_category', true ) );
	if ( $flosc_category === '' ) {
		continue;
	}
	if ( ! isset( $flosc_weakness_counts[ $flosc_category ] ) ) {
		$flosc_weakness_counts[ $flosc_category ] = 0;
	}
	$flosc_weakness_counts[ $flosc_category ]++;
}
arsort( $flosc_weakness_counts );

// Recent bridge users.
$flosc_recent_ids = array_slice( array_reverse( $flosc_bridge_ids ), 0, 10 );
$flosc_recent_objs = ! empty( $flosc_recent_ids )
	? get_users(
		array(
			'include' => $flosc_recent_ids,
			'fields'  => array( 'ID', 'user_email', 'display_name', 'user_registered' ),
			'orderby' => 'registered',
			'order'   => 'DESC',
		)
	)
	: array();
$flosc_recent_bridge_users = array();
foreach ( (array) $flosc_recent_objs as $flosc_user ) {
	$flosc_recent_bridge_users[] = (object) array(
		'ID'           => (int) $flosc_user->ID,
		'user_email'   => (string) $flosc_user->user_email,
		'display_name' => (string) $flosc_user->display_name,
		'bridge_data'  => get_user_meta( (int) $flosc_user->ID, '_flosc_bridge_data', true ),
	);
}

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
		<div class="flosc-bridge-stat__value"><?php echo esc_html( (string) $flosc_users_with_bridge ); ?></div>
		<div class="flosc-bridge-stat__label">Total Quiz Completions</div>
	</div>

	<div class="flosc-bridge-stat">
		<div class="flosc-bridge-stat__icon">🌉</div>
		<div class="flosc-bridge-stat__value"><?php echo esc_html( (string) $flosc_users_in_bridge ); ?></div>
		<div class="flosc-bridge-stat__label">Currently in Bridge</div>
	</div>

	<div class="flosc-bridge-stat">
		<div class="flosc-bridge-stat__icon">✅</div>
		<div class="flosc-bridge-stat__value"><?php echo esc_html( (string) $flosc_users_converted ); ?></div>
		<div class="flosc-bridge-stat__label">Converted to Purchase</div>
	</div>

	<div class="flosc-bridge-stat">
		<div class="flosc-bridge-stat__icon">📈</div>
		<div class="flosc-bridge-stat__value"><?php echo esc_html( (string) $flosc_conversion_rate ); ?>%</div>
		<div class="flosc-bridge-stat__label">Conversion Rate</div>
	</div>
</div>

<!-- IVR Targeting Info -->
<div class="flosc-banner flosc-banner--info">
	<strong>💡 IVR Targeting:</strong> Use these conditions in IVR messages:
	<ul class="flosc-bridge-targeting-list">
		<li><code>in_bridge_state = true</code> — User completed quiz but hasn't purchased</li>
		<li><code>bridge_score >= 70</code> — User scored 70% or higher on quiz</li>
		<li><code>weakest_category = "category_name"</code> — Target by weakness area</li>
		<li><code>has_quiz_profile = true</code> — User has any quiz data stored</li>
	</ul>
</div>

<!-- Weakness Categories -->
<h3>📉 Top Weakness Categories</h3>
<p class="description">Most common areas where users struggle. Use for targeted content recommendations.</p>

<?php if ( ! empty( $flosc_weakness_counts ) ) : ?>
	<div class="flosc-weakness-chart">
		<?php
		$flosc_max_count = max( $flosc_weakness_counts );
		foreach ( $flosc_weakness_counts as $flosc_category => $flosc_count ) :
			$flosc_percentage = $flosc_max_count > 0 ? round( ( $flosc_count / $flosc_max_count ) * 100 ) : 0;
			?>
			<div class="flosc-weakness-bar">
				<div class="flosc-weakness-bar__label"><?php echo esc_html( $flosc_category ); ?></div>
				<div class="flosc-weakness-bar__track">
					<progress class="flosc-weakness-progress" max="100" value="<?php echo esc_attr( (string) $flosc_percentage ); ?>"></progress>
					<span class="flosc-weakness-bar__count"><?php echo esc_html( (string) $flosc_count ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php else : ?>
	<div class="flosc-banner flosc-banner--warning">
		No weakness data yet. Weakness categories are populated when users complete the quiz.
	</div>
<?php endif; ?>

<!-- Recent Bridge Users -->
<h3>👥 Recent Bridge Users</h3>
<p class="description">Last 10 users who completed the quiz.</p>

<?php if ( ! empty( $flosc_recent_bridge_users ) ) : ?>
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
			<?php
			foreach ( $flosc_recent_bridge_users as $flosc_user ) :
				$flosc_bridge_data = maybe_unserialize( $flosc_user->bridge_data );
				if ( ! is_array( $flosc_bridge_data ) ) {
					$flosc_bridge_data = array();
				}
				$flosc_score     = isset( $flosc_bridge_data['score'] ) ? (int) $flosc_bridge_data['score'] : 0;
				$flosc_correct   = count( $flosc_bridge_data['correct_items'] ?? array() );
				$flosc_incorrect = count( $flosc_bridge_data['incorrect_items'] ?? array() );
				$flosc_purchased = get_user_meta( $flosc_user->ID, '_flosc_purchased', true );
				?>
				<tr>
					<td><?php echo esc_html( $flosc_user->display_name ? $flosc_user->display_name : 'User #' . $flosc_user->ID ); ?></td>
					<td><?php echo esc_html( $flosc_user->user_email ); ?></td>
					<td><?php echo esc_html( (string) $flosc_score ); ?>%</td>
					<td>
						<span class="flosc-bridge-correct">✓ <?php echo esc_html( (string) $flosc_correct ); ?></span> /
						<span class="flosc-bridge-incorrect">✗ <?php echo esc_html( (string) $flosc_incorrect ); ?></span>
					</td>
					<td>
						<?php if ( $flosc_purchased ) : ?>
							<span class="flosc-status flosc-status--active">Purchased</span>
						<?php else : ?>
							<span class="flosc-status flosc-status--pending">In Bridge</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="flosc-banner flosc-banner--warning">
		No bridge users yet. Users enter bridge state when they complete the quiz.
	</div>
<?php endif; ?>

<!-- API Endpoint Info -->
<h3>🔌 REST API Endpoint</h3>
<p class="description">Programmatic access to bridge data:</p>

<div class="flosc-code">
GET <?php echo esc_html( rest_url( 'flosc/v1/bridge-data' ) ); ?>

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
