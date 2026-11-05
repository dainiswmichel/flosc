<?php
/**
 * Regression checks for the Memory & Feedback admin workflow.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

/**
 * Record one assertion.
 *
 * @param string $label Assertion label.
 * @param bool   $pass  Whether the assertion passed.
 * @return void
 */
function flosc_memory_feedback_ok( $label, $pass ) {
	global $fail;
	if ( ! $pass ) {
		++$fail;
	}
	printf( "%s %s\n", $pass ? 'ok  ' : 'FAIL', $label );
}

$admin_ui  = (string) file_get_contents( $root . '/admin/ai-feedback.php' );
$chatpack  = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$dispatch  = (string) file_get_contents( $root . '/includes/class-flosc-ai-chat-dispatch.php' );
$framework = (string) file_get_contents( $root . '/includes/class-flosc-framework.php' );
$rest      = (string) file_get_contents( $root . '/includes/flosc-rest.php' );
$admin     = (string) file_get_contents( $root . '/includes/flosc-admin.php' );
$logger    = (string) file_get_contents( $root . '/includes/logging/class-flosc-chat-logger.php' );
$events    = (string) file_get_contents( $root . '/assets/js/flosc-admin-events.js' );

echo "Admin-post and REST deletion handlers stay distinct\n";
flosc_memory_feedback_ok( 'REST feedback route uses rest_delete_feedback()', false !== strpos( $rest, "'callback'            => array( \$this, 'rest_delete_feedback' )" ) );
flosc_memory_feedback_ok( 'REST praise route uses rest_delete_praise()', false !== strpos( $rest, "'callback'            => array( \$this, 'rest_delete_praise' )" ) );
flosc_memory_feedback_ok( 'framework owns the request-taking REST methods', false !== strpos( $framework, 'function rest_delete_feedback( $request )' ) && false !== strpos( $framework, 'function rest_delete_praise( $request )' ) );
flosc_memory_feedback_ok( 'admin trait retains zero-argument form handlers', false !== strpos( $admin, 'function handle_delete_feedback()' ) && false !== strpos( $admin, 'function handle_delete_praise()' ) );

echo "\nRated chats are evidence, not automatic prompt instructions\n";
flosc_memory_feedback_ok( 'legacy prompt builder does not read rated logs', false === strpos( $dispatch, 'flosc_get_rated_logs(' ) );
flosc_memory_feedback_ok( 'rated rows are filtered to the selected flow', false !== strpos( $admin_ui, 'flosc_get_rated_logs( 50, $flosc_selected_flow_id )' ) );
flosc_memory_feedback_ok( 'rated query carries flow and personality identity', false !== strpos( $logger, 'personality_id, personality_name' ) && false !== strpos( $logger, "OR flow_id = %s" ) );

echo "\nThe administrator receives both editable suggestions\n";
flosc_memory_feedback_ok( 'Memory & Feedback workspace is present', false !== strpos( $admin_ui, 'Memory &amp; Feedback' ) );
flosc_memory_feedback_ok( 'personality suggestion recommends density 55', false !== strpos( $admin_ui, 'Recommended density: 55' ) );
flosc_memory_feedback_ok( 'Flow Sticky suggestion is present', false !== strpos( $admin_ui, 'Suggested Flow Sticky sentence' ) );
flosc_memory_feedback_ok( 'copy action reads an edited textarea value', false !== strpos( $events, "('value' in source) ? source.value" ) );

echo "\nFlow Sticky aspects reach the active Chatpack\n";
flosc_memory_feedback_ok( 'Chatpack reads ai_brand_facts for its explicit flow', false !== strpos( $chatpack, "flosc_get_setting( 'ai_brand_facts', '', \$flow_id )" ) );
flosc_memory_feedback_ok( 'Chatpack labels Sticky aspects in the provider prompt', false !== strpos( $chatpack, '**Sticky aspects:**' ) );

echo $fail ? "\n{$fail} FAILURES\n" : "\nMemory & Feedback contract passes\n";
exit( $fail ? 1 : 0 );
