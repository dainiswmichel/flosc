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

	if ( ! preg_match_all( '/\(static function \(\)(?:\s+use\s*\([^)]*\))?\s*\{.*?return \$flosc_out;\s*\}\)\(\)/s', $src, $m ) ) {
		printf( "  --   %s (no inline jQuery block)\n", $rel );
		continue;
	}

	$pattern = "/<<<'(FLOSC_INLINE_\\d+)'\\R(.*?)\\R\\1;/s";
	foreach ( $m[0] as $i => $capture ) {
		if ( false === strpos( $capture, 'jQuery(document).ready' ) ) {
			continue;
		}
		$block  = '';
		$offset = 0;
		while ( preg_match( $pattern, $capture, $piece, PREG_OFFSET_CAPTURE, $offset ) ) {
			$start   = $piece[0][1];
			$between = substr( $capture, $offset, $start - $offset );
			$between = preg_replace( '/\$flosc_out\s*\.=\s*$/', '', $between );
			if ( preg_match( '/\$flosc_out\s*\.=/', $between ) ) {
				$block .= 'PHPVALUE';
			}
			$block .= $piece[2][0];
			$offset = $start + strlen( $piece[0][0] );
		}

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
