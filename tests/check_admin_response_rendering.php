<?php
/**
 * Remote responses on admin diagnostic panels are rendered as text.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		$fail++;
	}
	printf(
		"%s %-62s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

// v200: these screens' JavaScript lives in enqueued files, not inline PHP blocks.
foreach ( array( 'assets/js/admin/flosc-admin-payments.js', 'assets/js/admin/flosc-admin-ivr-messages.js' ) as $relative ) {
	$source = (string) file_get_contents( $root . '/' . $relative );
	echo $relative . "\n";
	ok( 'remote data is never assigned to innerHTML', strpos( $source, '.innerHTML' ) !== false, false );
	ok( 'the display uses textContent', strpos( $source, '.textContent' ) !== false, true );
	ok( 'the file carries no PHP', strpos( $source, '<?php' ) !== false, false );
	$output = array();
	$code   = 0;
	exec( 'node --check ' . escapeshellarg( $root . '/' . $relative ) . ' 2>&1', $output, $code );
	ok( 'the JavaScript parses', $code, 0 );
}

$ivr = (string) file_get_contents( $root . '/assets/js/admin/flosc-admin-ivr-messages.js' );
ok( 'structured IVR output creates text nodes', strpos( $ivr, 'document.createTextNode' ) !== false, true );
ok( 'full JSON remains readable in a pre element', strpos( $ivr, "'pre', JSON.stringify(data, null, 2)" ) !== false, true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nAdmin response rendering: all checks passed\n";
exit( $fail ? 1 : 0 );
