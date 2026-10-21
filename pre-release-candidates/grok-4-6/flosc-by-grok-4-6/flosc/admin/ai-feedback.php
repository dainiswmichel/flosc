<?php
/**
 * AI Feedback & Praise Editor.
 *
 * @since 1.9.0
 * @since 1.9.5 Added the "Rated Responses" section, which lists chat log
 *              entries that carry a rating in the database.
 *
 * Admin can view, add, and delete feedback (flag bad responses) and
 * praises (reinforce good responses) that guide AI behavior.
 * Manual entries are stored per-flow in flow settings and loaded into the
 * system prompt. Rated entries remain evidence in the chat log until a
 * floscAdmin deliberately copies edited guidance into a flow or personality.
 *
 * Included from settings.php within the Chat Logs tab.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Identity before input.
 *
 * WordPress.org, 14 Sep 2026: "No nonce check found validating input origin on
 * lines 1-116". Their scanner measures whether a check appears BEFORE the
 * request is read, not merely whether one exists somewhere in the file. Several
 * files here verified correctly and verified late, and late did not count -- an
 * unauthorized request still walked the whole parser before being refused.
 *
 * This is the capability the FLOSC menu itself requires. Flow-level access is
 * still checked further down where the flow is known; this only establishes
 * that somebody who may administer FLOSC at all is asking.
 */
if ( ! current_user_can( 'edit_others_posts' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'flosc' ), 403 );
}

// Rated chat rows remain evidence until the floscAdmin deliberately copies a
// suggestion into the flow or personality. Merely rating a chat never edits a
// prompt, a flow, or a personality.
$flosc_rated_logs  = FLOSC_Chat_Logger::instance()->flosc_get_rated_logs( 50, $flosc_selected_flow_id );
$flosc_rated_count = count( $flosc_rated_logs );
?>

<div class="flosc-feedback-section">
	<h3 class="flosc-feedback-header">
		Memory &amp; Feedback
		<span class="flosc-feedback-count flosc-feedback-count--rated"><?php echo esc_html( (string) $flosc_rated_count ); ?></span>
	</h3>
	<p class="flosc-feedback-description">
		These are chat-log entries scored in the Chat Logs above. A non-zero score protects the original exchange from automatic expiration. The drafts below are suggestions only: edit and copy one into the personality or this flow’s Sticky aspects when it is ready.
	</p>

	<?php if ( $flosc_rated_count > 0 ) : ?>
	<table class="flosc-feedback-table widefat striped">
		<thead>
			<tr>
				<th class="flosc-feedback-col-score">Score</th>
				<th class="flosc-feedback-col-rated">Rated</th>
				<th>Personality</th>
				<th>User Said</th>
				<th>AI Response</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $flosc_rated_logs as $flosc_rlog ) : ?>
				<?php
				$flosc_score         = intval( $flosc_rlog['admin_rating'] );
				$flosc_score_class   = $flosc_score > 0 ? 'flosc-rating-positive' : ( $flosc_score < 0 ? 'flosc-rating-negative' : '' );
				$flosc_score_display = $flosc_score > 0 ? '+' . $flosc_score : $flosc_score;
				$flosc_log_id        = absint( $flosc_rlog['id'] ?? 0 );
				$flosc_admin_note    = trim( (string) ( $flosc_rlog['admin_note'] ?? '' ) );
				if ( $flosc_score < 0 ) {
					$flosc_personality_suggestion = 'Never respond this way again. It incorrectly [describe the problem]. Instead, [describe the required response].';
					$flosc_flow_suggestion        = 'For this flow, never [describe the incorrect claim or behavior]. Instead, [state the required response or fact].';
				} else {
					$flosc_personality_suggestion = 'Preserve this response pattern. It succeeded because [describe why]. Use this approach when [describe the relevant situation].';
					$flosc_flow_suggestion        = 'For this flow, preserve [describe the successful behavior] when [describe the relevant situation].';
				}
				if ( '' !== $flosc_admin_note ) {
					$flosc_personality_suggestion .= "\n\nfloscAdmin note: " . $flosc_admin_note;
					$flosc_flow_suggestion        .= "\n\nfloscAdmin note: " . $flosc_admin_note;
				}
				$flosc_personality_field_id = 'flosc-personality-memory-' . $flosc_log_id;
				$flosc_flow_field_id        = 'flosc-flow-sticky-' . $flosc_log_id;
				?>
			<tr>
				<td>
					<span class="flosc-rated-score <?php echo esc_attr( $flosc_score_class ); ?>"><?php echo esc_html( (string) $flosc_score_display ); ?></span>
				</td>
				<td><?php echo esc_html( gmdate( 'Y-m\m-d\d H:i', strtotime( (string) ( $flosc_rlog['rated_at'] ?? '' ) ) ) ); ?></td>
				<td><?php echo esc_html( (string) ( $flosc_rlog['personality_name'] ?? $flosc_rlog['personality_id'] ?? '' ) ); ?></td>
				<td><?php echo esc_html( (string) ( $flosc_rlog['user_message'] ?? '' ) ); ?></td>
				<td><?php echo esc_html( (string) ( $flosc_rlog['ai_response'] ?? '' ) ); ?></td>
			</tr>
			<tr>
				<td colspan="5">
					<div class="flosc-feedback-add">
						<p><strong>Suggested personality item — Memory &amp; Feedback</strong></p>
						<textarea id="<?php echo esc_attr( $flosc_personality_field_id ); ?>" rows="4" class="large-text code"><?php echo esc_textarea( $flosc_personality_suggestion ); ?></textarea>
						<p class="description">Written for the personality that produced this response. Copy it into the personality designer as a checked item. Recommended density: 55; the floscAdmin may change the density.</p>
						<p><button type="button" class="button button-secondary" data-flosc-action="copy-element-text" data-source-id="<?php echo esc_attr( $flosc_personality_field_id ); ?>" data-alert-message="Personality suggestion copied.">Copy personality suggestion</button></p>

						<p><strong>Suggested Flow Sticky sentence</strong></p>
						<textarea id="<?php echo esc_attr( $flosc_flow_field_id ); ?>" rows="3" class="large-text code"><?php echo esc_textarea( $flosc_flow_suggestion ); ?></textarea>
						<p class="description">Copy this only when the guidance belongs to this flow regardless of which personality is attached.</p>
						<p><button type="button" class="button button-secondary" data-flosc-action="copy-element-text" data-source-id="<?php echo esc_attr( $flosc_flow_field_id ); ?>" data-alert-message="Flow Sticky suggestion copied.">Copy Flow Sticky suggestion</button></p>
					</div>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<div class="flosc-feedback-empty">
		<p>No extracted chats yet. Give a chat-log row a non-zero score to preserve it here and prepare editable suggestions.</p>
	</div>
	<?php endif; ?>
</div>

<?php
// ── Feedback ──
$flosc_feedback_items = $flosc_flow_settings['ai_feedback'] ?? array();
$flosc_feedback_count = count( $flosc_feedback_items );

// ── Praises ──
$flosc_praises       = $flosc_flow_settings['ai_praises'] ?? array();
$flosc_praises_count = count( $flosc_praises );

$flosc_feedback_post       = admin_url( 'admin-post.php' );
$flosc_feedback_delete_url = add_query_arg(
	array(
		'action' => 'flosc_delete_feedback',
		'ivr'    => $flosc_selected_ivr,
	),
	$flosc_feedback_post
);
$flosc_feedback_add_url    = add_query_arg(
	array(
		'action' => 'flosc_add_feedback',
		'ivr'    => $flosc_selected_ivr,
	),
	$flosc_feedback_post
);
$flosc_praise_delete_url   = add_query_arg(
	array(
		'action' => 'flosc_delete_praise',
		'ivr'    => $flosc_selected_ivr,
	),
	$flosc_feedback_post
);
$flosc_praise_add_url      = add_query_arg(
	array(
		'action' => 'flosc_add_praise',
		'ivr'    => $flosc_selected_ivr,
	),
	$flosc_feedback_post
);
?>

<?php settings_errors( 'flosc' ); ?>

<div class="flosc-feedback-section">
	<h3 class="flosc-feedback-header">
		AI Feedback
		<span class="flosc-feedback-count"><?php echo esc_html( (string) $flosc_feedback_count ); ?></span>
	</h3>
	<p class="flosc-feedback-description">
		When you flag an AI response in the chat (using the ⚑ button), or add a feedback here manually, 
		the AI receives these as direct instructions in its system prompt. Use this to teach the AI 
		what NOT to do and how to respond instead.
	</p>

	<?php if ( $flosc_feedback_count > 0 ) : ?>
	<table class="flosc-feedback-table widefat striped">
		<thead>
			<tr>
				<th class="flosc-feedback-col-date">Date</th>
				<th class="flosc-feedback-col-input">User Said</th>
				<th class="flosc-feedback-col-issue">Issue</th>
				<th class="flosc-feedback-col-preferred">Preferred Response</th>
				<th class="flosc-feedback-col-actions">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( array_reverse( $flosc_feedback_items ) as $flosc_feedback_item ) : ?>
			<tr>
				<td class="flosc-feedback-col-date">
					<?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $flosc_feedback_item['timestamp'] ?? '' ) ) ); ?>
				</td>
				<td class="flosc-feedback-col-input">
					<div class="flosc-feedback-text" title="<?php echo esc_attr( $flosc_feedback_item['user_message'] ?? '' ); ?>">
						<?php echo esc_html( mb_strimwidth( $flosc_feedback_item['user_message'] ?? '', 0, 80, '...' ) ); ?>
					</div>
					<?php if ( ! empty( $flosc_feedback_item['bad_response'] ) ) : ?>
					<div class="flosc-feedback-bad-preview" title="<?php echo esc_attr( $flosc_feedback_item['bad_response'] ); ?>">
						Bad: <?php echo esc_html( mb_strimwidth( $flosc_feedback_item['bad_response'], 0, 60, '...' ) ); ?>
					</div>
					<?php endif; ?>
				</td>
				<td class="flosc-feedback-col-issue">
					<?php echo esc_html( $flosc_feedback_item['admin_note'] ?? '' ); ?>
				</td>
				<td class="flosc-feedback-col-preferred">
					<?php if ( ! empty( $flosc_feedback_item['preferred_response'] ) ) : ?>
						<?php echo esc_html( mb_strimwidth( $flosc_feedback_item['preferred_response'], 0, 100, '...' ) ); ?>
					<?php else : ?>
						<em class="flosc-feedback-none">—</em>
					<?php endif; ?>
				</td>
				<td class="flosc-feedback-col-actions">
					<button type="submit" name="flosc_delete_feedback" value="<?php echo esc_attr( $flosc_feedback_item['id'] ); ?>"
							class="button button-small flosc-feedback-delete"
							formaction="<?php echo esc_url( $flosc_feedback_delete_url ); ?>"
							data-confirm-message="Delete this feedback?">
						Delete
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<div class="flosc-feedback-empty">
		<p>No feedback yet. Flag a bad AI response in the chat using the ⚑ button, or add one manually below.</p>
	</div>
	<?php endif; ?>

	<!-- Manual Add Feedback Form -->
	<div class="flosc-feedback-add">
		<h4>Add Feedback Manually</h4>
		<table class="form-table flosc-feedback-form-table">
			<tr>
				<th><label for="feedback_user_message">When user says <span class="required">*</span></label></th>
				<td>
					<textarea id="feedback_user_message" name="feedback_user_message" rows="2" class="large-text"
								placeholder="e.g., How much does it cost?"></textarea>
					<p class="description">The type of user message this feedback applies to.</p>
				</td>
			</tr>
			<tr>
				<th><label for="feedback_bad_response">Bad response</label></th>
				<td>
					<textarea id="feedback_bad_response" name="feedback_bad_response" rows="2" class="large-text"
								placeholder="e.g., The AI said something incorrect or inappropriate here"></textarea>
					<p class="description">What the AI said that was wrong (leave blank if adding a proactive rule).</p>
				</td>
			</tr>
			<tr>
				<th><label for="feedback_admin_note">What was wrong <span class="required">*</span></label></th>
				<td>
					<textarea id="feedback_admin_note" name="feedback_admin_note" rows="2" class="large-text"
								placeholder="e.g., Too pushy about purchasing, gave wrong price, went off-topic"></textarea>
					<p class="description">Describe the problem so the AI knows what to avoid.</p>
				</td>
			</tr>
			<tr>
				<th><label for="feedback_preferred_response">Preferred response</label></th>
				<td>
					<textarea id="feedback_preferred_response" name="feedback_preferred_response" rows="2" class="large-text"
								placeholder="e.g., The course is $49. You can try a free lesson first to see if it's right for you."></textarea>
					<p class="description">How the AI should respond instead (optional but recommended).</p>
				</td>
			</tr>
		</table>
		<button type="submit" name="flosc_add_feedback" value="1" class="button button-secondary" formaction="<?php echo esc_url( $flosc_feedback_add_url ); ?>">
			Add Feedback
		</button>
	</div>
</div>

<!-- ═══ AI Praise Section ═══ -->
<div class="flosc-feedback-section flosc-praise-section">
	<h3 class="flosc-feedback-header">
		AI Praise
		<span class="flosc-praise-count"><?php echo esc_html( (string) $flosc_praises_count ); ?></span>
	</h3>
	<p class="flosc-feedback-description">
		When you praise an AI response in the chat (using the ✓ button), or add praise here manually, 
		the AI receives these as examples of its best work. Use this to reinforce the tone, style, 
		and approach you want the AI to keep using.
	</p>

	<?php if ( $flosc_praises_count > 0 ) : ?>
	<table class="flosc-feedback-table flosc-praise-table widefat striped">
		<thead>
			<tr>
				<th class="flosc-feedback-col-date">Date</th>
				<th class="flosc-feedback-col-input">User Said</th>
				<th class="flosc-feedback-col-issue">Good Response</th>
				<th class="flosc-feedback-col-preferred">Why It Was Good</th>
				<th class="flosc-feedback-col-actions">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( array_reverse( $flosc_praises ) as $flosc_praise ) : ?>
			<tr>
				<td class="flosc-feedback-col-date">
					<?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $flosc_praise['timestamp'] ?? '' ) ) ); ?>
				</td>
				<td class="flosc-feedback-col-input">
					<div class="flosc-feedback-text" title="<?php echo esc_attr( $flosc_praise['user_message'] ?? '' ); ?>">
						<?php echo esc_html( mb_strimwidth( $flosc_praise['user_message'] ?? '', 0, 80, '...' ) ); ?>
					</div>
				</td>
				<td class="flosc-feedback-col-issue">
					<div class="flosc-feedback-text flosc-feedback-good-preview" title="<?php echo esc_attr( $flosc_praise['good_response'] ?? '' ); ?>">
						<?php echo esc_html( mb_strimwidth( $flosc_praise['good_response'] ?? '', 0, 100, '...' ) ); ?>
					</div>
				</td>
				<td class="flosc-feedback-col-preferred">
					<?php echo esc_html( $flosc_praise['admin_note'] ?? '' ); ?>
				</td>
				<td class="flosc-feedback-col-actions">
					<button type="submit" name="flosc_delete_praise" value="<?php echo esc_attr( $flosc_praise['id'] ); ?>"
							class="button button-small flosc-feedback-praise-delete"
							formaction="<?php echo esc_url( $flosc_praise_delete_url ); ?>"
							data-confirm-message="Delete this praise?">
						Delete
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<div class="flosc-feedback-empty flosc-praise-empty">
		<p>No praises yet. Praise a good AI response in the chat using the ✓ button, or add one manually below.</p>
	</div>
	<?php endif; ?>

	<!-- Manual Add Praise Form -->
	<div class="flosc-feedback-add flosc-praise-add">
		<h4>Add Praise Manually</h4>
		<table class="form-table flosc-feedback-form-table">
			<tr>
				<th><label for="praise_user_message">When user said <span class="required">*</span></label></th>
				<td>
					<textarea id="praise_user_message" name="praise_user_message" rows="2" class="large-text"
								placeholder="e.g., Can you explain how the lessons work?"></textarea>
					<p class="description">What the user asked or said.</p>
				</td>
			</tr>
			<tr>
				<th><label for="praise_good_response">Good response</label></th>
				<td>
					<textarea id="praise_good_response" name="praise_good_response" rows="2" class="large-text"
								placeholder="e.g., The AI's response that you liked"></textarea>
					<p class="description">The AI response you want to reinforce.</p>
				</td>
			</tr>
			<tr>
				<th><label for="praise_admin_note">Why this was good <span class="required">*</span></label></th>
				<td>
					<textarea id="praise_admin_note" name="praise_admin_note" rows="2" class="large-text"
								placeholder="e.g., Perfect tone — warm but professional, gave accurate info without being pushy"></textarea>
					<p class="description">What made this response great, so the AI replicates the approach.</p>
				</td>
			</tr>
		</table>
		<button type="submit" name="flosc_add_praise" value="1" class="button button-secondary" formaction="<?php echo esc_url( $flosc_praise_add_url ); ?>">
			Add Praise
		</button>
	</div>
</div>
