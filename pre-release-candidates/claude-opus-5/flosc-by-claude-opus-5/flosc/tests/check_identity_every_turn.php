<?php
/**
 * The personality goes to the model on every turn, not only the first.
 *
 * This exists because of a change that looked like a pure saving. Follow-up
 * turns stopped carrying the compiled profile and carried a short anchor
 * instead — name, role, traits. Input tokens went down and two things broke at
 * once.
 *
 * The character thinned from turn 2. A name is a label, not a voice, so Betty
 * stopped being bubbly the moment the profile stopped arriving, and it read as
 * the personality designer being broken when the designer was fine.
 *
 * And switching personality mid-conversation stopped working, which is the
 * demonstration this release is built around. The anchor told the model it was
 * "the same person as the opening turn", so changing the attached personality
 * changed the name on the bubble and nothing else.
 *
 * Identity can change between any two turns. It cannot be inferred from an
 * earlier one, and no prompt may claim it can.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$src  = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$fail = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-62s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

// Comments are not prompts. This file explains the bug in prose that quotes the
// sentence it removed, and so does the chatpack — searching raw source would
// match the explanation and miss nothing real. Strip comments first, then look
// only at what the code can actually send.
$code = preg_replace( '#/\*.*?\*/#s', '', $src );
$code = preg_replace( '#(^|\s)//.*$#m', '$1', (string) $code );

// Every call site, with whatever it passes for $compact.
preg_match_all( '/build_identity_section\(\s*([^;]*?)\)\s*;/s', (string) $code, $calls );

$sites = array();
foreach ( $calls[1] as $args ) {
	$sites[] = preg_replace( '/\s+/', ' ', trim( (string) $args ) );
}

echo "Every turn sends the compiled profile\n";
ok( 'the chatpack builds identity in more than one place', count( $sites ) >= 2, true );

$compacted = array_values( array_filter( $sites, static function ( $args ) {
	return (bool) preg_match( '/,\s*true\s*$/', $args );
} ) );

ok( 'NO CALL SITE ASKS FOR A COMPACT IDENTITY', $compacted, array() );

echo "No prompt claims the personality is unchanged since an earlier turn\n";
// The exact sentences that made a switched personality keep answering as the
// personality it replaced.
ok( 'nothing asserts the model is the same person as the opening turn',
	stripos( (string) $code, 'same person as the opening turn' ), false );
ok( '  and nothing says an opening-turn profile still applies',
	stripos( (string) $code, 'opening-turn profile' ), false );

echo "The identity section still carries a compiled profile at all\n";
ok( 'the compiled profile is read where identity is built',
	strpos( (string) $code, 'flosc_personality_compiled_profile' ) !== false, true );
ok( '  and the personality is resolved fresh rather than snapshotted',
	strpos( (string) $code, 'flosc_personality_library_resolve_field' ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nIdentity reaches the model every turn: all checks passed\n";
exit( $fail ? 1 : 0 );
