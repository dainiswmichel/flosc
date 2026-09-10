<?php
/**
 * User-specific AI guidance stays private and membership roles stay canonical.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root     = dirname( __DIR__ );
$main     = (string) file_get_contents( $root . '/flosc.php' );
$chatpack = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$dispatch = (string) file_get_contents( $root . '/includes/class-ai-chat-dispatch.php' );
$chat     = (string) file_get_contents( $root . '/includes/chat-turn/trait-flosc-chat-turn.php' );
$member   = (string) file_get_contents( $root . '/includes/class-member-access.php' );
$runtime  = $main . $chatpack . $dispatch . $chat . $member
	. (string) file_get_contents( $root . '/admin/offers.php' )
	. (string) file_get_contents( $root . '/includes/sale/class-offer-manager.php' );
$fail = 0;

function flosc_contract_ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		$fail++;
	}
	printf( "%s %-68s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

echo "Sticky for User is native, private WordPress profile data\n";
flosc_contract_ok( 'the native profile screen renders the field', substr_count( $main, "'render_user_sticky_profile_field'" ) >= 2, true );
flosc_contract_ok( 'the native profile save hooks are registered', substr_count( $main, "'save_user_sticky_profile_field'" ) >= 2, true );
flosc_contract_ok( 'saving requires edit_users', strpos( $main, "current_user_can('edit_users')" ) !== false, true );
flosc_contract_ok( 'saving verifies its nonce', strpos( $main, "wp_verify_nonce(sanitize_text_field(wp_unslash(\$_POST['flosc_user_sticky_nonce']))" ) !== false, true );
flosc_contract_ok( 'the prompt requires the authenticated user ID', strpos( $main, 'get_current_user_id() !== $user_id' ) !== false, true );
flosc_contract_ok( 'sending requires the enable checkbox', strpos( $main, "_flosc_user_sticky_enabled', true) !== 'yes'" ) !== false, true );
flosc_contract_ok( 'BuddyPress is not used for the field', stripos( $runtime, 'xprofile_insert_field' ), false );
flosc_contract_ok( 'the field says it addresses the configured AI API', strpos( $main, 'message to the configured AI API about how it should communicate with this specific user' ) !== false, true );
foreach ( array( 'userName', 'firstName', 'lastName', 'email', 'userId', 'accessLevel', 'memberLevel', 'quizScore', 'weakestPhonemes', 'flowName', 'siteName' ) as $variable ) {
	flosc_contract_ok( "{{$variable}} is documented and expanded", substr_count( $main, "{{$variable}}" ) >= 2, true );
}

echo "Sticky for User reaches every AI prompt route\n";
flosc_contract_ok( 'full and follow-up chatpacks share build_user_section', substr_count( $chatpack, 'self::build_user_section($eval_context)' ), 2 );
flosc_contract_ok( 'full and follow-up place sticky after personality', substr_count( $chatpack, 'self::build_user_sticky_section($eval_context)' ), 2 );
flosc_contract_ok( 'chatpack uses the sticky helper', strpos( $chatpack, 'flosc_get_user_sticky_prompt' ) !== false, true );
flosc_contract_ok( 'legacy dispatch adds the sticky helper', strpos( $dispatch, 'flosc_get_user_sticky_prompt' ) !== false, true );
flosc_contract_ok( 'the direct RAG route does not append it twice', strpos( $chat, 'flosc_get_user_sticky_prompt' ), false );

echo "LeSAEp roles have one canonical pair\n";
flosc_contract_ok( 'member defaults use lesaep_learners', strpos( $runtime, "'lesaep_learners'" ) !== false, true );
flosc_contract_ok( 'guest defaults use guest_lesaep_learner', strpos( $runtime, "'guest_lesaep_learner'" ) !== false, true );
flosc_contract_ok( 'old pronunciation member role is absent', strpos( $runtime, 'pronunciation_learners' ), false );
flosc_contract_ok( 'old pronunciation guest role is absent', strpos( $runtime, 'guest_pronunciation_learner' ), false );
flosc_contract_ok( 'flow-aware promotion removes only its guest tier', strpos( $member, "flosc_get_setting('default_guest_level', '', \$flow_id)" ) !== false, true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nUser sticky and role contract: all checks passed\n";
exit( $fail ? 1 : 0 );
