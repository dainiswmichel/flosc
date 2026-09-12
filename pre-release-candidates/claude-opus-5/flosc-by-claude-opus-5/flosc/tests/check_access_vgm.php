<?php
/**
 * Access is a VGM list, and a row nobody gated is public.
 *
 * Br3nda told a visitor she had no information about a public post while that
 * post sat in the index with the right keywords on it. search() discards a row
 * before it reads a single keyword when the visitor's level does not clear the
 * row's access — and access_allows() read one token, so a real list like
 * "visitor guest member" came out of sanitize_key() as "visitorguestmember",
 * missed the hierarchy, and fell to the members-only default. Silently, for
 * every row that carried a list.
 *
 * These run the two real functions, lifted out of the class by name.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-flosc-site-content-index.php' );

function flosc_grab_method( $src, $name ) {
	if ( ! preg_match( '/\t(?:public|private|protected)(?: static)? function ' . preg_quote( $name, '/' ) . '\(.*?\n\t\}/s', $src, $m ) ) {
		fwrite( STDERR, "missing method: {$name}\n" );
		exit( 1 );
	}
	return $m[0];
}

eval( 'class FLOSC_VGM_Probe { '
	. flosc_grab_method( $src, 'vgm_list' ) . "\n"
	. flosc_grab_method( $src, 'access_allows' ) . ' }' );

$probe = new FLOSC_VGM_Probe();
$fail  = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = ( $actual === $expected );
	if ( ! $pass ) {
		$fail++;
	}
	printf(
		"%s %-54s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

/* ---- the parser ---- */
ok( 'a single level parses', FLOSC_VGM_Probe::vgm_list( 'member' ), array( 'member' ) );
ok( 'a spaced list parses', FLOSC_VGM_Probe::vgm_list( 'visitor guest member' ), array( 'visitor', 'guest', 'member' ) );
ok( 'a comma list parses', FLOSC_VGM_Probe::vgm_list( 'member,visitor' ), array( 'visitor', 'member' ) );
ok( 'order is always vgm', FLOSC_VGM_Probe::vgm_list( 'MEMBER Visitor' ), array( 'visitor', 'member' ) );
ok( 'junk is dropped', FLOSC_VGM_Probe::vgm_list( 'banana' ), array() );
ok( 'empty is empty', FLOSC_VGM_Probe::vgm_list( '' ), array() );

/* ---- the comparator ---- */
ok( 'visitor reaches visitor', $probe->access_allows( 'visitor', 'visitor' ), true );
ok( 'visitor reaches a full vgm list', $probe->access_allows( 'visitor', 'visitor guest member' ), true );
ok( 'visitor does not reach member', $probe->access_allows( 'visitor', 'member' ), false );
ok( 'visitor does not reach guest member', $probe->access_allows( 'visitor', 'guest member' ), false );
ok( 'a row nobody gated is public', $probe->access_allows( 'visitor', '' ), true );
ok( 'unparseable is public, not private', $probe->access_allows( 'visitor', 'banana' ), true );
ok( 'guest reaches guest', $probe->access_allows( 'guest', 'guest' ), true );
ok( 'member reaches member', $probe->access_allows( 'member', 'member' ), true );
ok( 'member reaches a visitor row', $probe->access_allows( 'member', 'visitor' ), true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nall green\n";
exit( $fail ? 1 : 0 );
