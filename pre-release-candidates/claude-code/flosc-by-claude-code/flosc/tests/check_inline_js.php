<?php
/**
 * Parse the JavaScript that admin templates emit inside PHP.
 *
 * A syntax error in an inline block is invisible to php -l and to node's file
 * check, because the file is PHP and the script is a string being built. It
 * shows up only in a browser console on a page an operator is already using.
 * This pulls each block out, replaces the PHP islands with a literal, and
 * hands the result to node.
 */

// These files stub WordPress on purpose — they define ABSPATH and redeclare
// core functions — so running one through a web server is never intended and
// could execute on any host they are copied to. They are command line only.
if ( PHP_SAPI !== 'cli' ) {
	exit;
}


$root  = dirname( __DIR__ );
$files = array( $root . '/admin/ai-configuration.php' );
$fail  = 0;

foreach ( $files as $file ) {
	$src = (string) file_get_contents( $file );
	$rel = str_replace( $root . '/', '', $file );

	if ( ! preg_match_all( '/jQuery\(document\)\.ready\(function\s*\(\$\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		printf( "  --   %s (no inline jQuery block)\n", $rel );
		continue;
	}

	foreach ( $m[0] as $i => $hit ) {
		$start = (int) $hit[1];
		$end   = strpos( $src, 'wp_add_inline_script', $start );
		$end   = ( false === $end ) ? strlen( $src ) : $end;
		$block = substr( $src, $start, $end - $start );
		$close = strrpos( $block, '});' );
		$block = ( false === $close ) ? $block : substr( $block, 0, $close + 3 );

		// PHP islands always sit inside a JS string literal, so a bare token
		// keeps the surrounding quotes balanced.
		$block = preg_replace( '/<\?php.*?\?>/s', 'PHPVALUE', $block );

		$tmp = tempnam( sys_get_temp_dir(), 'flosc_js_' ) . '.js';
		file_put_contents( $tmp, $block );

		$out  = array();
		$code = 0;
		exec( 'node --check ' . escapeshellarg( $tmp ) . ' 2>&1', $out, $code );
		unlink( $tmp );

		if ( 0 === $code ) {
			printf( "  ok   %s block %d (%d bytes)\n", $rel, $i + 1, strlen( $block ) );
			continue;
		}

		$fail++;
		printf( "  FAIL %s block %d\n       %s\n", $rel, $i + 1, implode( "\n       ", array_slice( $out, 0, 4 ) ) );
	}
}

echo $fail ? "\n$fail inline block(s) failed to parse\n" : "\nInline admin JavaScript parses cleanly\n";
exit( $fail ? 1 : 0 );
