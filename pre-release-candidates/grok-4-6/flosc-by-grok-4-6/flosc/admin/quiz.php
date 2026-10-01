<?php
/**
 * FLOSC Quiz Configuration Tab
 *
 * Enable/disable quizzes, edit questions inline, and load ready-made demo sets.
 *
 * Stripped dead weight; added per-card inline edit panel + demo library
 *         with Load → buttons that fill the editor directly.
 *
 * @package FLOSC
 * @since 4.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

flosc_tab_header( '❓', 'Quiz' );

$flosc_flow_settings           = $GLOBALS['flosc_current_settings'] ?? array();
$flosc_current_ivr             = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_quiz_docs_url           = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'documentation',
		'doc'  => 'ref-admin',
	),
	admin_url( 'admin.php' )
) . '#tab-quiz';
$flosc_quiz_docs_inventory_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'documentation',
		'doc'  => 'ref-admin',
	),
	admin_url( 'admin.php' )
) . '#inventory-quiz-family';
// Empty = no quizzes on this flow. Do not invent sample quiz IDs.
$flosc_enabled_quizzes = $flosc_flow_settings['enabled_quizzes'] ?? array();
if ( ! is_array( $flosc_enabled_quizzes ) ) {
	$flosc_enabled_quizzes = array();
}
$flosc_enabled_quizzes = array_values( array_filter( array_map( 'sanitize_key', $flosc_enabled_quizzes ) ) );
$flosc_all_quiz_types  = FLOSC_Quiz_Registry::get_all_quizzes();

echo '<div class="flosc-docs-link-wrap">'
	. '<a href="' . esc_url( $flosc_quiz_docs_url ) . '" class="flosc-docs-link">Docs</a>'
	. '</div>';

if ( empty( $flosc_all_quiz_types ) ) {
	echo '<div class="notice notice-error"><p>No quiz types registered. Please reinstall FLOSC.</p></div>';
	return;
}

// ── Demo library ──────────────────────────────────────────────────────────────
// Each entry: [ 'name', 'desc', 'content' ]
// Content format must match the quiz type's own get_instructions() format.
$flosc_quiz_demos = array(

	// ── Sample Assessment (Q+options block format) ─────────────────
	// Subject-neutral sample. floscAdmins replace content per flow.
	'sample_assessment_quiz'         => array(

		array(
			'name'    => 'Sample 10-topic assessment',
			'desc'    => 'Generic placeholders for 10 topics. Replace with your own questions; map CorrectContent to real posts.',
			'content' => implode(
				"\n",
				array(
					'Sample question for Topic 1 — Getting started. (Replace this quiz in FLOSC → Quiz.) Which statement is true?',
					'A: This is a placeholder wrong answer.',
					'B: This is the sample correct answer for this topic.',
					'C: Another placeholder wrong answer.',
					'D: Another placeholder wrong answer.',
					'CORRECT: B',
					'TOPIC: topic-1-getting-started',
					'CorrectContent: post:sample-topic-1-getting-started',
					'',
					'Sample question for Topic 2 — Core ideas. Which statement is true?',
					'A: This is a placeholder wrong answer.',
					'B: This is the sample correct answer for this topic.',
					'C: Another placeholder wrong answer.',
					'D: Another placeholder wrong answer.',
					'CORRECT: B',
					'TOPIC: topic-2-core-ideas',
					'CorrectContent: post:sample-topic-2-core-ideas',
					'',
				)
			),
		),

	),

	// ── Multiple Choice (pipe-delimited format) ────────────────────────────
	'multiplechoice'                 => array(

		array(
			'name'    => 'FLOSC Basics',
			'desc'    => '10 multiple-choice questions on what FLOSC is and how a floscFlow works.',
			'content' => implode(
				"\n",
				array(
					'What does FLOSC stand for?|A) Free Login Offer Sale Content|B) Freeline, Login, Offer, Sale, Content|C) Flow Logic Order System Core|D) Flexible Online Sales Chat|Correct: B',
					'A floscFlow is:|A) A WordPress theme|B) One configured journey with its own identity, messages and offers|C) A payment gateway|D) A page builder block|Correct: B',
					'In the Freeline phase, a person is:|A) A paying member|B) A visitor who has not signed up|C) An administrator|D) A refunded customer|Correct: B',
					'What happens in the Login phase?|A) The visitor pays|B) The visitor becomes a guest by creating an account|C) The flow is published|D) The AI key is saved|Correct: B',
					'The Offer phase is where:|A) Content is delivered|B) The guest is shown what they can buy|C) The site is installed|D) Messages are written|Correct: B',
					'After the Sale phase, a person is a:|A) Visitor|B) Guest|C) Member|D) Subscriber only|Correct: C',
					'BYOK means:|A) Buy Your Own Key|B) Bring Your Own Key -- you supply the AI provider key|C) Build Your Own Kit|D) Basic Yearly Onboarding Kit|Correct: B',
					'IVR messages in FLOSC are:|A) Voice recordings|B) Scripted messages that guide the assistant and answer without an AI key|C) Email templates|D) Payment receipts|Correct: B',
					'How many floscFlows can one FLOSC install run?|A) One|B) Three|C) Ten|D) As many as you like|Correct: D',
					'A Starter Pack installs:|A) Only a theme|B) A working flow with example posts and the gating that makes the journey legible|C) A payment plugin|D) Nothing until you buy a licence|Correct: B',
					'',
				)
			),
		),

		array(
			'name'    => 'Setting Up Your First Flow',
			'desc'    => '10 multiple-choice questions on configuring a floscFlow in the FLOSC admin.',
			'content' => implode(
				"\n",
				array(
					'Where do you set a flow\'s name, slug and colours?|A) The AI tab|B) The Identity tab|C) The Payments tab|D) The Docs tab|Correct: B',
					'Where do you paste an AI provider key?|A) Settings -> Connectors|B) The FLOSC AI tab, for this flow or All Flows|C) wp-config.php|D) The theme customizer|Correct: B',
					'Which providers need their official WordPress AI Provider plugin installed?|A) None of them|B) OpenAI, Anthropic and Gemini|C) Only xAI|D) All four|Correct: B',
					'A flow with no AI key configured will:|A) Show an error page|B) Still answer, using its IVR messages|C) Refuse to load|D) Disable the whole plugin|Correct: B',
					'The Flow tab is where you:|A) Edit CSS|B) Switch between flows and manage their files|C) Configure SMTP|D) Write the privacy policy|Correct: B',
					'Where do you write the messages the assistant uses?|A) IVR Management|B) Chat Logs|C) Token Management|D) Engagement|Correct: A',
					'Offers are configured on:|A) The Offers tab|B) The Quiz tab|C) The Identity tab|D) The Style tab|Correct: A',
					'A flow can be served on its own domain by setting:|A) A custom domain in the flow settings|B) A WordPress permalink|C) An .htaccess rule only|D) A DNS record only|Correct: A',
					'Display Mode Hybrid means:|A) Two AI providers at once|B) Full-page chat and a floating companion bubble, sharing one conversation|C) Half the messages are scripted|D) Desktop and mobile themes|Correct: B',
					'Chat Logs let an administrator:|A) Edit the AI model|B) Review conversations, archive and export them|C) Change the site language|D) Issue refunds|Correct: B',
					'',
				)
			),
		),
	),

	// ── True/False (Statement.|True or Statement.|False format) ────────────
	'truefalse'                      => array(

		array(
			'name'    => 'FLOSC True or False',
			'desc'    => '10 True/False statements about how FLOSC works.',
			'content' => implode(
				"\n",
				array(
					'A FLOSC install can run more than one floscFlow.|True',
					'FLOSC requires an AI provider key before it will answer anything.|False',
					'The five phases are Freeline, Login, Offer, Sale and Content.|True',
					'BYOK means FLOSC supplies the AI credits for you.|False',
					'A floscFlow can be served on its own domain.|True',
					'IVR messages are written by the site administrator, not generated.|True',
					'Visitors have the same content access as members.|False',
					'A Starter Pack can install a flow, example posts and their gating in one step.|True',
					'FLOSC stores visitor conversation data on an external FLOSC server.|False',
					'The same compiled personality can be attached to more than one flow.|True',
					'',
				)
			),
		),

		array(
			'name'    => 'Flow Configuration Check',
			'desc'    => '10 True/False statements about configuring a flow in the FLOSC admin.',
			'content' => implode(
				"\n",
				array(
					'A flow\'s name, slug and colours live on the Identity tab.|True',
					'An AI key pasted on the AI tab can apply to one flow or to all flows.|True',
					'OpenAI, Anthropic and Gemini each need their official WordPress AI Provider plugin.|True',
					'xAI needs an official provider plugin installed before it will work.|False',
					'Offers are configured on the Style tab.|False',
					'Hybrid display mode gives a full-page chat and a companion bubble sharing one conversation.|True',
					'Chat Logs can be exported and archived from the admin.|True',
					'Deleting the plugin leaves all FLOSC data behind in the database.|False',
					'Each flow can have its own privacy policy, terms and data-deletion pages.|True',
					'Changing a policy page slug moves the page FLOSC serves for that flow.|True',
					'',
				)
			),
		),
	),

	// ── 1-10 Numbers (comma-separated format) ─────────────────────────────
	'flosc_sample_data_numbers_quiz' => array(

		array(
			'name'    => 'Classic 1–10 Sequence',
			'desc'    => 'The default flow test. User types all 10 numbers — perfect for testing the full FLOSC pipeline.',
			'content' => '1,2,3,4,5,6,7,8,9,10',
		),

		array(
			'name'    => 'Primary Color Names',
			'desc'    => 'Type the 6 primary and secondary color names. Tests text-matching with words instead of numbers.',
			'content' => 'red,orange,yellow,green,blue,purple',
		),

		array(
			'name'    => 'Days of the Week',
			'desc'    => 'Type all 7 days in order. Good for testing case-insensitive matching.',
			'content' => 'monday,tuesday,wednesday,thursday,friday,saturday,sunday',
		),
	),

);
?>

<div class="flosc-quiz-config">

	<!-- ── Flow preview ──────────────────────────────────────────────────── -->
	<div class="flosc-section-header">
		<h2>
			Quiz Configuration
			<a href="<?php echo esc_url( $flosc_quiz_docs_inventory_url ); ?>" class="flosc-docs-link">Docs</a>
		</h2>
		<p>Enable quizzes and edit their questions. The first enabled quiz is shown to visitors.</p>
	</div>

	<div class="flosc-flow-preview">
		<div class="flosc-flow-step"><div class="icon">📝</div><div class="label">Quiz</div></div>
		<div class="flosc-flow-arrow">→</div>
		<div class="flosc-flow-step"><div class="icon">📊</div><div class="label">Score</div></div>
		<div class="flosc-flow-arrow">→</div>
		<div class="flosc-flow-step"><div class="icon">🔐</div><div class="label">Login</div></div>
		<div class="flosc-flow-arrow">→</div>
		<div class="flosc-flow-step"><div class="icon">🎁</div><div class="label">Free Lesson</div></div>
		<div class="flosc-flow-arrow">→</div>
		<div class="flosc-flow-step"><div class="icon">💰</div><div class="label">Offer</div></div>
		<div class="flosc-flow-arrow">→</div>
		<div class="flosc-flow-step"><div class="icon">🎓</div><div class="label">Content</div></div>
	</div>

	<?php
	// Split quiz types: ready (functional) vs coming soon (needs STT/microphone).
	$flosc_ready_quizzes  = array();
	$flosc_coming_quizzes = array();
	foreach ( $flosc_all_quiz_types as $flosc_qid => $flosc_qt ) {
		if ( method_exists( $flosc_qt, 'needs_stt' ) && $flosc_qt->needs_stt() ) {
			$flosc_coming_quizzes[ $flosc_qid ] = $flosc_qt;
		} else {
			$flosc_ready_quizzes[ $flosc_qid ] = $flosc_qt;
		}
	}
	$flosc_active_quiz_types = array_filter( $flosc_enabled_quizzes, fn( $flosc_qid ) => isset( $flosc_ready_quizzes[ $flosc_qid ] ) );
	?>

	<!-- ── Audio Quiz Messages — configurable chatbot responses ─────────── -->
	<h3>💬 Audio Quiz Messages
		<?php
		$flosc_helplink_url = add_query_arg(
			array(
				'page' => 'flosc-settings',
				'ivr'  => isset( $flosc_selected_ivr ) ? $flosc_selected_ivr : '',
				'tab'  => 'documentation',
				'doc'  => 'ref-audio-quiz-flow',
			),
			admin_url( 'admin.php' )
		);
		?>
		<a href="<?php echo esc_url( $flosc_quiz_docs_inventory_url ); ?>" class="flosc-docs-link">Docs</a>
		<a href="<?php echo esc_url( $flosc_helplink_url ); ?>" class="flosc-help-link" title="Full documentation for the Audio Quiz Flow">📖 Help</a>
	</h3>
	<p class="description">These messages appear in the chatbot during and after the audio pronunciation quiz. Placeholders: <code>{current}</code> = phrase number, <code>{total}</code> = total phrases.</p>
	<table class="form-table flosc-form-table-margin-bottom-24">
		<tr>
			<th scope="row"><label for="flow_audio_conversion_provider">Audio Conversion Provider</label></th>
			<td>
				<select id="flow_audio_conversion_provider" name="flow_audio_conversion_provider">
					<option value="none" <?php selected( $flosc_flow_settings['audio_conversion_provider'] ?? 'none', 'none' ); ?>>none (default)</option>
					<option value="external" <?php selected( $flosc_flow_settings['audio_conversion_provider'] ?? 'none', 'external' ); ?>>external</option>
				</select>
				<p class="description">Select <code>external</code> only when your flow is configured to call a compatible remote conversion endpoint. <code>none</code> keeps conversion dispatch disabled.</p>
			</td>
		</tr>
		<tr>
			<th scope="row" class="flosc-width-200"><label for="flow_audio_quiz_phrase_complete_message">Phrase Complete</label></th>
			<td>
				<input type="text" id="flow_audio_quiz_phrase_complete_message" name="flow_audio_quiz_phrase_complete_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_phrase_complete_message'] ?? 'Thank you. {current} of {total} recorded.' ); ?>">
				<p class="description">Shown after each phrase is recorded.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="flow_audio_quiz_complete_message">Quiz Complete (Visitors)</label></th>
			<td>
				<input type="text" id="flow_audio_quiz_complete_message" name="flow_audio_quiz_complete_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_complete_message'] ?? 'Assessment complete! All {total} recordings captured and analyzed. Sign up to see your results.' ); ?>">
				<p class="description">Shown to visitors after all phrases are done.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="flow_audio_quiz_results_message">Results Intro</label></th>
			<td>
				<input type="text" id="flow_audio_quiz_results_message" name="flow_audio_quiz_results_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_results_message'] ?? 'Welcome! Here are your assessment results.' ); ?>">
				<p class="description">Shown after login, before the detailed results.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="flow_audio_quiz_upsell_message">Upsell Message</label></th>
			<td>
				<input type="text" id="flow_audio_quiz_upsell_message" name="flow_audio_quiz_upsell_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_upsell_message'] ?? 'Our accent analysis shows you would benefit from lessons on {1st}, {2nd}, and {4th}. Upgrade today for full access to all lessons.' ); ?>">
				<p class="description">Shown after login results. Placeholders: <code>{1st}</code>, <code>{2nd}</code>, <code>{3rd}</code>, <code>{4th}</code> = worst phoneme names (by rank).</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="flow_audio_quiz_phoneme_lesson_map">Phoneme → Lesson Map</label></th>
			<td>
				<textarea id="flow_audio_quiz_phoneme_lesson_map" name="flow_audio_quiz_phoneme_lesson_map" class="large-text" rows="6"><?php echo esc_textarea( $flosc_flow_settings['audio_quiz_phoneme_lesson_map'] ?? '{}' ); ?></textarea>
				<p class="description">JSON object mapping IPA phoneme symbols (as returned by the pronunciation API) to lesson numbers. Example: <code>{"æ": 1, "θ": 35, "ð": 36}</code></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Between-phrase escape hatch', 'flosc' ); ?></th>
			<td>
				<?php
				$flosc_escape_enabled = ! isset( $flosc_flow_settings['audio_quiz_escape_enabled'] )
					|| ! empty( $flosc_flow_settings['audio_quiz_escape_enabled'] );
				$flosc_escape_once    = ! isset( $flosc_flow_settings['audio_quiz_escape_once'] )
					|| ! empty( $flosc_flow_settings['audio_quiz_escape_once'] );
				$flosc_escape_after   = isset( $flosc_flow_settings['audio_quiz_escape_after_phrase'] )
					? max( 0, min( 99, (int) $flosc_flow_settings['audio_quiz_escape_after_phrase'] ) )
					: 3;
				?>
				<label for="flow_audio_quiz_escape_enabled">
					<input type="checkbox" id="flow_audio_quiz_escape_enabled" name="flow_audio_quiz_escape_enabled" value="1" <?php checked( $flosc_escape_enabled ); ?>>
					<?php esc_html_e( 'Show escape hatch (upgrade / change tier) during the audio quiz', 'flosc' ); ?>
				</label>
				<br>
				<label for="flow_audio_quiz_escape_once" class="flosc-inline-check-top">
					<input type="checkbox" id="flow_audio_quiz_escape_once" name="flow_audio_quiz_escape_once" value="1" <?php checked( $flosc_escape_once ); ?>>
					<?php esc_html_e( 'Show at most once per quiz attempt', 'flosc' ); ?>
				</label>
				<p class="description flosc-description-top-8">
					<label for="flow_audio_quiz_escape_after_phrase">
						<?php esc_html_e( 'Show after phrase number', 'flosc' ); ?>
						<input type="number" id="flow_audio_quiz_escape_after_phrase" name="flow_audio_quiz_escape_after_phrase" class="small-text" min="0" max="99" step="1" value="<?php echo esc_attr( (string) $flosc_escape_after ); ?>">
					</label>
				</p>
				<p class="description">
					<?php esc_html_e( 'Example: after phrase 3 of 5. Use 0 to show after every completed phrase (not recommended). Beginner tier shows an upgrade link; intermediate/advanced offer a softer tier. Defaults: enabled, once, after phrase 3.', 'flosc' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<!-- ── Active Quizzes — summary of what is live in the funnel ────────── -->
	<h3>
		✅ Active Quizzes
		<a href="<?php echo esc_url( $flosc_quiz_docs_inventory_url ); ?>" class="flosc-docs-link">Docs</a>
	</h3>
	<?php if ( empty( $flosc_active_quiz_types ) ) : ?>
	<p class="flosc-quiz-warning">
		No quizzes are currently active. Enable one in the Quiz Deck below.
	</p>
	<?php else : ?>
	<div class="flosc-quiz-pill-row">
		<?php
		foreach ( $flosc_active_quiz_types as $flosc_qid ) :
			$flosc_qt = $flosc_ready_quizzes[ $flosc_qid ] ?? null;
			if ( ! $flosc_qt ) {
				continue;
			}
			?>
		<span class="flosc-quiz-pill">
			✅ <?php echo esc_html( $flosc_qt->get_icon() . ' ' . $flosc_qt->get_name() ); ?>
		</span>
		<?php endforeach; ?>
	</div>
	<p class="description">These quizzes are currently live in the visitor funnel. Enable or disable them in the Quiz Deck below.</p>
	<?php endif; ?>

	<!-- ── Quiz Deck — configure and enable quizzes ───────────────────────── -->
	<h3 class="flosc-heading-top-28">
		🗂 Quiz Deck
		<a href="<?php echo esc_url( $flosc_quiz_docs_url ); ?>" class="flosc-docs-link">Docs</a>
	</h3>
	<p>Your library of available quizzes. Enable a quiz to make it Active. Load a demo below to populate the question editor.</p>

	<div class="flosc-quiz-grid">
	<?php
	foreach ( $flosc_ready_quizzes as $flosc_quiz_id => $flosc_qt ) :
		$flosc_is_enabled = in_array( (string) $flosc_quiz_id, array_map( 'strval', (array) $flosc_enabled_quizzes ), true );
		$flosc_content    = ! empty( $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ] )
						? $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ]
						: $flosc_qt->get_default_content();
		$flosc_ta_id      = 'flosc_quiz_editor_' . esc_attr( $flosc_quiz_id );
		$flosc_det_id     = 'flosc_quiz_details_' . esc_attr( $flosc_quiz_id );
		?>
		<div class="flosc-quiz-card <?php echo esc_attr( $flosc_is_enabled ? 'active' : '' ); ?>">
			<h4>
				<span class="icon"><?php echo esc_html( $flosc_qt->get_icon() ); ?></span>
				<?php echo esc_html( $flosc_qt->get_name() ); ?>
				<span class="badge native">NATIVE</span>
				<?php if ( $flosc_is_enabled ) : ?>
				<span class="badge flosc-quiz-badge-active">✅ Active</span>
				<?php endif; ?>
			</h4>
			<p class="desc"><?php echo esc_html( $flosc_qt->get_description() ); ?></p>

			<div class="flosc-quiz-toggle">
				<input type="checkbox"
						name="flow_enabled_quizzes[]"
						value="<?php echo esc_attr( $flosc_quiz_id ); ?>"
						<?php checked( $flosc_is_enabled ); ?>>
				<label>Enable (make Active)</label>
			</div>

			<details id="<?php echo esc_attr( $flosc_det_id ); ?>" class="flosc-margin-top-12">
				<summary class="flosc-details-summary flosc-details-summary--blue">
					✏️ Edit Quiz
				</summary>
				<div class="flosc-details-content-top-8">

					<p class="flosc-micro-heading">Questions, Correct Answers &amp; WordPress Topic Links</p>
					<textarea
						id="<?php echo esc_attr( $flosc_ta_id ); ?>"
						name="flow_quiz_content_<?php echo esc_attr( $flosc_quiz_id ); ?>"
						rows="14"
						class="large-text code"
						class="large-text code flosc-textarea-12"
						placeholder="<?php echo esc_attr( $flosc_qt->get_default_content() ); ?>"
					><?php echo esc_textarea( $flosc_content ); ?></textarea>
					<p class="description flosc-description-top-6">
						<strong>Format:</strong> <?php echo esc_html( $flosc_qt->get_instructions() ); ?>
					</p>

					<?php
					$flosc_templates = $flosc_qt->get_default_response_templates();
					if ( ! empty( $flosc_templates ) ) :
						?>
					<div class="flosc-top-border-panel">
						<p class="flosc-micro-heading">Score Feedback Templates</p>
						<p class="description flosc-description-margin-0-0-10">
							Shown to the learner after scoring.
							Placeholders: <code>{score}</code> <code>{total_correct}</code> <code>{total_possible}</code> <code>{lesson_recommendations}</code>
						</p>
						<table class="form-table flosc-form-table-reset flosc-form-table-reset-row">
						<?php
						foreach ( $flosc_templates as $flosc_range => $flosc_default ) :
							$flosc_key   = 'quiz_' . $flosc_quiz_id . '_template_' . $flosc_range;
							$flosc_value = ! empty( $flosc_flow_settings[ $flosc_key ] ) ? $flosc_flow_settings[ $flosc_key ] : $flosc_default;
							?>
						<tr>
							<th scope="row" class="flosc-score-template-col">
								<label for="<?php echo esc_attr( 'flow_' . $flosc_key ); ?>"><?php echo esc_html( $flosc_range ); ?>%</label>
							</th>
							<td class="flosc-score-template-cell">
								<textarea
									id="<?php echo esc_attr( 'flow_' . $flosc_key ); ?>"
									name="flow_<?php echo esc_attr( $flosc_key ); ?>"
									rows="2"
									class="large-text flosc-textarea-12"
								><?php echo esc_textarea( $flosc_value ); ?></textarea>
							</td>
						</tr>
						<?php endforeach; ?>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</details>
		</div>
	<?php endforeach; ?>

	<?php
	foreach ( $flosc_coming_quizzes as $flosc_quiz_id => $flosc_qt ) :
		$flosc_content = ! empty( $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ] )
					? $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ]
					: $flosc_qt->get_default_content();
		$flosc_ta_id   = 'flosc_quiz_editor_' . esc_attr( $flosc_quiz_id );
		$flosc_det_id  = 'flosc_quiz_details_' . esc_attr( $flosc_quiz_id );
		?>
		<div class="flosc-quiz-card flosc-quiz-card-dim">
			<h4>
				<span class="icon"><?php echo esc_html( $flosc_qt->get_icon() ); ?></span>
				<?php echo esc_html( $flosc_qt->get_name() ); ?>
				<span class="badge native">NATIVE</span>
			</h4>
			<p class="desc"><?php echo esc_html( $flosc_qt->get_description() ); ?></p>
			<p class="flosc-quiz-warning flosc-quiz-warning--block">
				🎤 Requires microphone + speech-to-text — not yet functional.
			</p>

			<details id="<?php echo esc_attr( $flosc_det_id ); ?>" class="flosc-margin-top-12">
				<summary class="flosc-details-summary flosc-details-summary--gray">
					✏️ Edit Quiz
				</summary>
				<div class="flosc-details-content-top-8">
					<p class="flosc-micro-heading">Questions, Correct Answers &amp; WordPress Topic Links</p>
					<textarea
						id="<?php echo esc_attr( $flosc_ta_id ); ?>"
						name="flow_quiz_content_<?php echo esc_attr( $flosc_quiz_id ); ?>"
						rows="8"
						class="large-text code flosc-textarea-12"
						readonly
					><?php echo esc_textarea( $flosc_content ); ?></textarea>
					<p class="description flosc-description-top-6">
						<strong>Format:</strong> <?php echo esc_html( $flosc_qt->get_instructions() ); ?>
					</p>
				</div>
			</details>
		</div>
	<?php endforeach; ?>
	</div>

	<!-- ── Demo library ──────────────────────────────────────────────────── -->
	<details class="flosc-margin-top-28" open>
		<summary class="flosc-ai-section-heading">
			🎯 Demo Quiz Sets — load ready-made questions into any editor
		</summary>
		<div class="flosc-demo-panel">
			<p class="description flosc-demo-description">
				Click <strong>Load →</strong> to fill that quiz's editor with demo content. Then enable the quiz above, customize as needed, and save.
			</p>

			<?php
			foreach ( $flosc_quiz_demos as $flosc_quiz_id => $flosc_demos ) :
				$flosc_qt = FLOSC_Quiz_Registry::get_quiz( $flosc_quiz_id );
				if ( ! $flosc_qt ) {
					continue;
				}
				$flosc_ta_id  = 'flosc_quiz_editor_' . esc_attr( $flosc_quiz_id );
				$flosc_det_id = 'flosc_quiz_details_' . esc_attr( $flosc_quiz_id );
				?>
			<h4 class="flosc-demo-section-title">
				<?php echo esc_html( $flosc_qt->get_icon() . ' ' . $flosc_qt->get_name() ); ?>
			</h4>
			<div class="flosc-demo-list">
				<?php foreach ( $flosc_demos as $flosc_i => $flosc_demo ) : ?>
				<div class="flosc-demo-item">
					<div class="flosc-demo-item__body">
						<strong class="flosc-demo-item__title"><?php echo esc_html( $flosc_demo['name'] ); ?></strong>
						<span class="flosc-demo-item__desc"><?php echo esc_html( $flosc_demo['desc'] ); ?></span>
					</div>
					<textarea
						id="flosc_demo_<?php echo esc_attr( $flosc_quiz_id ); ?>_<?php echo esc_attr( (string) $flosc_i ); ?>"
						class="flosc-hidden"
						readonly
					><?php echo esc_textarea( $flosc_demo['content'] ); ?></textarea>
					<button
						type="button"
						class="button button-small flosc-load-demo"
						data-textarea="<?php echo esc_attr( $flosc_ta_id ); ?>"
						data-details="<?php echo esc_attr( $flosc_det_id ); ?>"
						data-source="flosc_demo_<?php echo esc_attr( $flosc_quiz_id ); ?>_<?php echo esc_attr( (string) $flosc_i ); ?>"
						class="button button-small flosc-load-demo flosc-nowrap-shrink"
					>Load →</button>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</details>

	<!-- Score feedback templates now live inside each quiz card's ✏️ Edit Questions panel -->

	<!-- Save is handled by the main settings form Save button at top of page -->

</div>

<?php ob_start(); ?>
document.addEventListener('click', function(e) {
	var btn = e.target.closest('.flosc-load-demo');
	if (!btn) return;

	var sourceId  = btn.dataset.source;
	var targetId  = btn.dataset.textarea;
	var detailsId = btn.dataset.details;

	var source  = document.getElementById(sourceId);
	var target  = document.getElementById(targetId);
	var details = document.getElementById(detailsId);

	if (!source || !target) return;

	target.value = source.value;

	if (details) details.open = true;
	target.scrollIntoView({ behavior: 'smooth', block: 'center' });
	target.focus();

	var original = btn.textContent;
	btn.textContent = '✓ Loaded!';
	btn.disabled = true;
	setTimeout(function() {
		btn.textContent = original;
		btn.disabled = false;
	}, 1800);
});
<?php wp_add_inline_script( 'flosc-admin', ob_get_clean() ); ?>
