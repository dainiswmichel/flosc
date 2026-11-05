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

$asset = (string) file_get_contents( $root . '/assets/js/flosc-admin.js' );
$modules = array(
	'payments'     => array( '/* payments */', '/* da1 */' ),
	'ivr-messages' => array( '/* ivr-messages API test */', '/* ivr-messages upload */' ),
);

foreach ( $modules as $name => $markers ) {
	$start  = strpos( $asset, $markers[0] );
	$end    = strpos( $asset, $markers[1] );
	$source = ( false === $start || false === $end || $end <= $start ) ? '' : substr( $asset, $start, $end - $start );
	echo $name . "\n";
	ok( 'the enqueued JavaScript module was found', '' !== $source, true );
	ok( 'remote data is never assigned to innerHTML', strpos( $source, '.innerHTML' ) !== false, false );
	ok( 'the display uses textContent', strpos( $source, '.textContent' ) !== false, true );
	$tmp    = tempnam( sys_get_temp_dir(), 'flosc_admin_response_' ) . '.js';
	file_put_contents( $tmp, $source );
	$output = array();
	$code   = 0;
	exec( 'node --check ' . escapeshellarg( $tmp ) . ' 2>&1', $output, $code );
	unlink( $tmp );
	ok( 'the edited inline JavaScript parses', $code, 0 );
}

$ivr = $asset;
ok( 'structured IVR output creates text nodes', strpos( $ivr, 'document.createTextNode' ) !== false, true );
ok( 'full JSON remains readable in a pre element', strpos( $ivr, "'pre', JSON.stringify(data, null, 2)" ) !== false, true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nAdmin response rendering: all checks passed\n";
exit( $fail ? 1 : 0 );
