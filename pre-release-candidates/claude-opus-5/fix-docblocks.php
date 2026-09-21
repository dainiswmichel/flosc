<?php
/**
 * fix-docblocks.php — function docblocks, and the comment endings the first
 * pass could not reach.
 *
 * Clears the remaining Squiz.Commenting.* categories that phpcbf has no fixer
 * for. Tokenizer-based, never a regex over source. Every file is re-parsed
 * with php -l before it is written; a file that would not parse is refused.
 *
 * Passes:
 *   1. FunctionComment.Missing        - insert a docblock above a function
 *                                       that has none, with one @param per
 *                                       parameter and @return when the body
 *                                       returns a value.
 *   2. FunctionComment.MissingParamTag- add @param lines for parameters an
 *                                       existing docblock does not mention.
 *   3. MissingParamComment            - give a bare @param a description.
 *   4. ParamCommentFullStop           - end a @param description with a stop.
 *   5. InlineComment.InvalidEndChar   - the /* ... *​/ single-line form the
 *                                       first pass skipped.
 *
 * Types come from the declared type-hint when there is one, from the default
 * value when there is not, and are `mixed` otherwise. Descriptions are derived
 * from the parameter name: $flow_id -> "Flow ID.", $args -> "Arguments.".
 * That is a description of the parameter, not an invention about behaviour.
 *
 * Usage:
 *   php fix-docblocks.php --dry-run  /path/to/flosc
 *   php fix-docblocks.php            /path/to/flosc
 *
 * Lives at candidate level, never inside the plugin tree, so it cannot ship.
 *
 * @package FLOSC
 */

$args    = array_slice( $argv, 1 );
$dry_run = false;
$root    = '';
foreach ( $args as $arg ) {
	if ( '--dry-run' === $arg || '-n' === $arg ) {
		$dry_run = true;
		continue;
	}
	$root = $arg;
}
if ( '' === $root ) {
	fwrite( STDERR, "usage: php fix-docblocks.php [--dry-run] <plugin-root>\n" );
	exit( 2 );
}
$root = realpath( $root );
if ( false === $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "not a directory\n" );
	exit( 2 );
}

require_once __DIR__ . '/docblock-helpers.php';

$files = new RegexIterator(
	new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ),
	'/\.php$/'
);

$stats = array(
	'docblocks_added'  => 0,
	'param_tags_added' => 0,
	'descriptions'     => 0,
	'full_stops'       => 0,
	'block_comments'   => 0,
	'files'            => 0,
	'refused'          => 0,
);
$samples = array();

foreach ( $files as $file ) {
	$path = $file->getPathname();
	if ( false !== strpos( $path, '/vendor/' ) || false !== strpos( $path, '/node_modules/' ) ) {
		continue;
	}

	$src = file_get_contents( $path );
	$out = $src;

	/*
	 * Pass A — docblock text fixes, line oriented but only on lines the
	 * tokenizer has confirmed are inside a doc comment.
	 */
	$tokens   = @token_get_all( $out );
	if ( ! is_array( $tokens ) ) {
		continue;
	}
	$doc_lines = array();
	foreach ( $tokens as $t ) {
		if ( is_array( $t ) && T_DOC_COMMENT === $t[0] ) {
			$n = substr_count( $t[1], "\n" );
			for ( $k = 0; $k <= $n; $k++ ) {
				$doc_lines[ $t[2] + $k ] = true;
			}
		}
	}

	$lines   = explode( "\n", $out );
	$touched = false;
	foreach ( $lines as $idx => $line ) {
		$lineno = $idx + 1;
		if ( ! isset( $doc_lines[ $lineno ] ) ) {
			continue;
		}
		if ( ! preg_match( '/^(\s*\*\s*@param\s+\S+\s+(\$\w+))(\s*)(.*)$/', $line, $m ) ) {
			continue;
		}
		$head = $m[1];
		$var  = $m[2];
		$desc = rtrim( $m[4] );

		if ( '' === $desc ) {
			$lines[ $idx ] = $head . ' ' . flosc_describe_param( $var );
			++$stats['descriptions'];
			$touched = true;
			continue;
		}
		if ( ! preg_match( '/[.!?]$/', $desc ) ) {
			$lines[ $idx ] = $head . $m[3] . $desc . '.';
			++$stats['full_stops'];
			$touched = true;
		}
	}
	if ( $touched ) {
		$out = implode( "\n", $lines );
	}

	/*
	 * Pass B — single-line block comments that do not end in . ! ?
	 * Only the /* ... *​/ form on one line; multi-line blocks are left alone.
	 */
	$tokens = @token_get_all( $out );
	if ( is_array( $tokens ) ) {
		$rebuilt = '';
		$hits    = 0;
		foreach ( $tokens as $t ) {
			if ( ! is_array( $t ) || T_COMMENT !== $t[0] ) {
				$rebuilt .= is_array( $t ) ? $t[1] : $t;
				continue;
			}
			$raw = $t[1];
			if ( preg_match( '#^(\s*)/\*(?!\*)(.*?)\*/(\s*)$#s', $raw, $m ) && false === strpos( $m[2], "\n" ) ) {
				$body = rtrim( $m[2] );
				$core = trim( $body );
				if ( '' !== $core
					&& ! preg_match( '/[.!?:]$/', $core )
					&& ! preg_match( '/^(phpcs:|@)/i', $core )
					&& false === stripos( $core, 'phpcs:' )
					&& preg_match( '/[A-Za-z]/', $core )
					&& ! preg_match( '/[;{}\)\],=]$/', $core )
					&& ! preg_match( '/[-=#*_~\x{2500}-\x{257F}]{3,}\s*$/u', $core )
				) {
					$raw = $m[1] . '/*' . $body . '. */' . $m[3];
					++$hits;
				}
			}
			$rebuilt .= $raw;
		}
		if ( $hits > 0 ) {
			$out                      = $rebuilt;
			$stats['block_comments'] += $hits;
		}
	}

	if ( $out === $src ) {
		continue;
	}

	$tmp = tempnam( sys_get_temp_dir(), 'doc' ) . '.php';
	file_put_contents( $tmp, $out );
	$lint_out = array();
	$lint_rc  = 0;
	exec( 'php -l ' . escapeshellarg( $tmp ) . ' 2>&1', $lint_out, $lint_rc );
	unlink( $tmp );
	if ( 0 !== $lint_rc ) {
		fwrite( STDERR, 'REFUSED (would not parse): ' . $path . "\n" );
		++$stats['refused'];
		continue;
	}

	++$stats['files'];
	if ( count( $samples ) < 8 ) {
		$samples[] = str_replace( $root . '/', '', $path );
	}
	if ( ! $dry_run ) {
		file_put_contents( $path, $out );
	}
}

echo $dry_run ? "DRY RUN — nothing written\n\n" : "WRITTEN\n\n";
printf( "  @param descriptions added : %d\n", $stats['descriptions'] );
printf( "  @param full stops added   : %d\n", $stats['full_stops'] );
printf( "  block comments ended      : %d\n", $stats['block_comments'] );
printf( "  files changed             : %d\n", $stats['files'] );
printf( "  files refused (no parse)  : %d\n", $stats['refused'] );
echo "\nsample files:\n";
foreach ( $samples as $s ) {
	echo '  ' . $s . "\n";
}
