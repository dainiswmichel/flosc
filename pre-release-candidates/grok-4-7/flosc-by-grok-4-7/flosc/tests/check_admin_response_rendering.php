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

/**
 * Rebuild one inline script from the nowdoc pieces wp_add_inline_script receives.
 *
 * PHP statements between nowdocs become the token PHPVALUE, which is what the
 * old PHP-island check substituted for an echoed value.
 *
 * @param string $capture Source of one static function that returns $flosc_out.
 * @return string
 */
function flosc_reconstruct_inline_js( $capture ) {
	$parts   = array();
	$pattern = "/<<<'(FLOSC_INLINE_\\d+)'\\R(.*?)\\R\\1;/s";
	$offset  = 0;
	while ( preg_match( $pattern, $capture, $hit, PREG_OFFSET_CAPTURE, $offset ) ) {
		$start   = $hit[0][1];
		$between = substr( $capture, $offset, $start - $offset );
		$between = preg_replace( '/\$flosc_out\s*\.=\s*$/', '', $between );
		if ( preg_match( '/\$flosc_out\s*\.=/', $between ) ) {
			$parts[] = 'PHPVALUE';
		}
		$parts[] = $hit[2][0];
		$offset  = $start + strlen( $hit[0][0] );
	}
	return implode( '', $parts );
}

foreach ( array( 'admin/payments.php', 'admin/ivr-messages.php' ) as $relative ) {
	$source = (string) file_get_contents( $root . '/' . $relative );
	echo $relative . "\n";
	ok( 'remote data is never assigned to innerHTML', strpos( $source, '.innerHTML' ) !== false, false );
	ok( 'the display uses textContent', strpos( $source, '.textContent' ) !== false, true );

	$matched = preg_match_all(
		'/\(static function \(\)(?:\s+use\s*\([^)]*\))?\s*\{.*?return \$flosc_out;\s*\}\)\(\)/s',
		$source,
		$captures
	);
	if ( 1 > $matched ) {
		ok( 'the edited inline JavaScript block was found', false, true );
		continue;
	}

	$parsed = 0;
	foreach ( $captures[0] as $capture ) {
		$script = flosc_reconstruct_inline_js( $capture );
		if ( '' === trim( $script ) ) {
			continue;
		}
		$tmp = tempnam( sys_get_temp_dir(), 'flosc_admin_response_' ) . '.js';
		file_put_contents( $tmp, $script );
		$output = array();
		$code   = 0;
		exec( 'node --check ' . escapeshellarg( $tmp ) . ' 2>&1', $output, $code );
		unlink( $tmp );
		ok( 'the edited inline JavaScript parses', $code, 0 );
		++$parsed;
	}
	ok( 'the edited inline JavaScript block was found', $parsed > 0, true );
}

$ivr = (string) file_get_contents( $root . '/admin/ivr-messages.php' );
ok( 'structured IVR output creates text nodes', strpos( $ivr, 'document.createTextNode' ) !== false, true );
ok( 'full JSON remains readable in a pre element', strpos( $ivr, "'pre', JSON.stringify(data, null, 2)" ) !== false, true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nAdmin response rendering: all checks passed\n";
exit( $fail ? 1 : 0 );
