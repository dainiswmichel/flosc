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

foreach ( array( 'admin/payments.php', 'admin/ivr-messages.php' ) as $relative ) {
	$source = (string) file_get_contents( $root . '/' . $relative );
	echo $relative . "\n";
	ok( 'remote data is never assigned to innerHTML', strpos( $source, '.innerHTML' ) !== false, false );
	ok( 'the display uses textContent', strpos( $source, '.textContent' ) !== false, true );

	$start = strpos( $source, '<?php ob_start(); ?>' );
	$end   = false === $start ? false : strpos( $source, '<?php wp_add_inline_script', $start );
	if ( false === $start || false === $end ) {
		ok( 'the edited inline JavaScript block was found', false, true );
		continue;
	}

	$script = substr( $source, $start + strlen( '<?php ob_start(); ?>' ), $end - $start - strlen( '<?php ob_start(); ?>' ) );
	$script = preg_replace( '/<\?php.*?\?>/s', 'PHPVALUE', $script );
	$tmp    = tempnam( sys_get_temp_dir(), 'flosc_admin_response_' ) . '.js';
	file_put_contents( $tmp, $script );
	$output = array();
	$code   = 0;
	exec( 'node --check ' . escapeshellarg( $tmp ) . ' 2>&1', $output, $code );
	unlink( $tmp );
	ok( 'the edited inline JavaScript parses', $code, 0 );
}

$ivr = (string) file_get_contents( $root . '/admin/ivr-messages.php' );
ok( 'structured IVR output creates text nodes', strpos( $ivr, 'document.createTextNode' ) !== false, true );
ok( 'full JSON remains readable in a pre element', strpos( $ivr, "'pre', JSON.stringify(data, null, 2)" ) !== false, true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nAdmin response rendering: all checks passed\n";
exit( $fail ? 1 : 0 );
