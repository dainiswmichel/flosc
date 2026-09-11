<?php
/**
 * Attaching a personality has to say what was stored.
 *
 * The control showed "Attaching…", then called location.reload() on the
 * statement after setting the success text. The browser never painted it, so
 * what a floscAdmin saw was an indefinite "Attaching…" and then a page reload,
 * with no statement of what had been written — on the one control where being
 * sure matters most, and the one being switched constantly while testing
 * whether BubblyBetty and DadJokeDan stay distinct.
 *
 * Underneath, the handler never checked. update_option() returns false for a
 * failed write and for a write that changed nothing, so its return cannot tell
 * them apart — and this handler ignored it either way. Every attach reported
 * success, including one that never landed.
 *
 * Acceptance, in the Captain's words: choose DadJokeDan, receive a clear saved
 * confirmation, reload the page, and see DadJokeDan still attached.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-62s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

$library = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
$page    = (string) file_get_contents( $root . '/admin/ai-configuration.php' );
$css     = (string) file_get_contents( $root . '/assets/css/flosc-admin.css' );

echo "The server confirms from storage, not from the request\n";
ok( 'the flow row is read back after the write',
	strpos( $library, "\$stored_settings = get_option( \$option_key, array() );" ) !== false, true );
ok( 'and success requires the stored value to match what was asked for',
	strpos( $library, "if ( \$stored !== \$persona ) {" ) !== false, true );
ok( 'a write that did not land is reported as an error',
	strpos( $library, 'The attachment was not saved.' ) !== false, true );
ok( 'success carries what is stored, not what was sent',
	strpos( $library, "'persona'   => \$stored," ) !== false, true );
ok( 'and when it happened, in the same stamp the page-wide Save uses',
	strpos( $library, "flosc_mts_utc()" ) !== false, true );

// The control reloads on purpose — the designer below renders from the
// attached row — so the confirmation cannot simply be painted before it.
echo "\nThe confirmation outlives the reload the control triggers\n";
ok( 'it is stashed before reloading',
	strpos( $page, 'window.sessionStorage.setItem(floscAttachStash' ) !== false, true );
ok( 'and shown on the page that comes back',
	strpos( $page, 'floscShowStashedAttachConfirmation();' ) !== false, true );
ok( 'the stash is cleared when read, so it shows once',
	strpos( $page, 'window.sessionStorage.removeItem(floscAttachStash);' ) !== false, true );
ok( 'and a browser with no session storage still gets to read it',
	strpos( $page, 'window.setTimeout(function () { window.location.reload(); }, 1200);' ) !== false, true );

echo "\nA failed attach says so, and leaves the control usable\n";
ok( 'the select is re-enabled on failure',
	substr_count( $page, "attachSel.prop('disabled', false);" ), 2 );
ok( 'the server message is shown rather than a generic one',
	strpos( $page, 'xhr.responseJSON.data.message' ) !== false, true );

echo "\nThe states are styled by class, never inline\n";
foreach ( array( 'flosc-attach-ok', 'flosc-attach-bad' ) as $class ) {
	ok( $class . ' has a rule in the stylesheet',
		strpos( $css, '.' . $class . ' {' ) !== false, true );
}
ok( 'and the note element carries no style attribute',
	(bool) preg_match( '/id="flosc-personality-attach-note"[^>]*style=/', $page ), false );

/*
 * Every field the designer computes reaches the database.
 *
 * libraryEntry() built a complete six-field entry from the first day and only
 * the downloadable builder state read it. The save sent four keys, so traits,
 * mission, boundaries and topic scope stayed empty in every personality the
 * designer ever made — and ai_boundaries and ai_topic_scope are read on every
 * turn, so a floscAdmin had no way to set two values the AI was being given.
 */
echo "\nWhat the designer computes is what the database receives\n";
$flosc_bridge  = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/flosc-personality-builder-wp.js' );
$flosc_builder = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/flosc-personality-builder.js' );
$flosc_lib     = (string) file_get_contents( dirname( __DIR__ ) . '/includes/flosc-personality-library.php' );
$flosc_sidecar = array( 'ai_personality_traits', 'ai_mission', 'ai_boundaries', 'ai_topic_scope' );

ok( 'the builder exposes libraryEntry to the bridge', strpos( $flosc_builder, 'libraryEntry: libraryEntry,' ) !== false, true );
ok( 'the bridge reads it rather than rebuilding it', strpos( $flosc_bridge, 'api.libraryEntry()' ) !== false, true );

$flosc_unsent = array();
$flosc_unread = array();
foreach ( $flosc_sidecar as $flosc_field ) {
	if ( strpos( $flosc_bridge, $flosc_field ) === false ) {
		$flosc_unsent[] = $flosc_field;
	}
	if ( strpos( $flosc_lib, "'" . $flosc_field . "'" ) === false ) {
		$flosc_unread[] = $flosc_field;
	}
}
ok( 'every sidecar field is sent', $flosc_unsent, array() );
ok( '  and read by the save handler', $flosc_unread, array() );
/* This file stubs WordPress rather than loading the plugin, so the key list is
   read from the source instead of called. */
preg_match( '/function flosc_personality_library_field_keys\(\).*?return array\((.*?)\);/s', $flosc_lib, $flosc_keys_m );
$flosc_key_src = isset( $flosc_keys_m[1] ) ? $flosc_keys_m[1] : '';
$flosc_unstorable = array();
foreach ( $flosc_sidecar as $flosc_field ) {
	if ( strpos( $flosc_key_src, "'" . $flosc_field . "'" ) === false ) {
		$flosc_unstorable[] = $flosc_field;
	}
}
ok( '  and storable', $flosc_unstorable, array() );

echo $fail ? "\n$fail FAILURES\n" : "\nAn attach reports what is stored, and the answer survives the reload\n";
exit( $fail ? 1 : 0 );
