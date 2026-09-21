<?php
/**
 * fix-docblocks-insert.php — write the docblocks that are missing entirely.
 *
 * Clears Squiz.Commenting.FunctionComment.Missing and
 * Squiz.Commenting.FunctionComment.MissingParamTag, neither of which phpcbf
 * can fix.
 *
 * Tokenizer-based. For every named function or method it finds, it either
 * inserts a complete docblock or adds the @param lines an existing docblock
 * is missing. Parameter order and names come from the signature, so the tags
 * always match the code rather than describing something else.
 *
 * Types come from the declared type-hint, then the default value, then the
 * name (a _id suffix is int, an is_ prefix is bool, a plural is array), and
 * are `mixed` when nothing indicates otherwise. @return is added only when
 * the body contains a return with a value.
 *
 * Every file is re-parsed with php -l before it is written; a file that would
 * not parse is refused and left untouched.
 *
 * Usage:
 *   php fix-docblocks-insert.php --dry-run  /path/to/flosc
 *   php fix-docblocks-insert.php            /path/to/flosc
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
	fwrite( STDERR, "usage: php fix-docblocks-insert.php [--dry-run] <plugin-root>\n" );
	exit( 2 );
}
$root = realpath( $root );
if ( false === $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "not a directory\n" );
	exit( 2 );
}

require_once __DIR__ . '/docblock-helpers.php';

/**
 * Token text.
 *
 * @param mixed $t Token.
 * @return string
 */
function flosc_tt( $t ) {
	return is_array( $t ) ? $t[1] : $t;
}

/**
 * Is the token ignorable?
 *
 * @param mixed $t Token.
 * @return bool
 */
function flosc_skip( $t ) {
	return is_array( $t ) && in_array( $t[0], array( T_WHITESPACE ), true );
}

$files = new RegexIterator(
	new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ),
	'/\.php$/'
);

$added_blocks = 0;
$added_params = 0;
$changed      = 0;
$refused      = 0;
$samples      = array();

foreach ( $files as $file ) {
	$path = $file->getPathname();
	if ( false !== strpos( $path, '/vendor/' ) || false !== strpos( $path, '/node_modules/' ) ) {
		continue;
	}

	$src    = file_get_contents( $path );
	$tokens = @token_get_all( $src );
	if ( ! is_array( $tokens ) ) {
		continue;
	}
	$count  = count( $tokens );
	$insert = array();   // index => text to insert before that token.
	$rewrite = array();  // index => replacement text for a doc comment.

	for ( $i = 0; $i < $count; $i++ ) {
		$t = $tokens[ $i ];
		if ( ! is_array( $t ) || T_FUNCTION !== $t[0] ) {
			continue;
		}

		// Named functions only; a closure has "(" straight after "function".
		$n = $i + 1;
		while ( $n < $count && flosc_skip( $tokens[ $n ] ) ) {
			++$n;
		}
		if ( $n >= $count || ! is_array( $tokens[ $n ] ) || T_STRING !== $tokens[ $n ][0] ) {
			continue;
		}

		// Walk back over modifiers to the real start of the declaration.
		$start = $i;
		$j     = $i - 1;
		while ( $j >= 0 ) {
			if ( flosc_skip( $tokens[ $j ] ) ) {
				--$j;
				continue;
			}
			$id = is_array( $tokens[ $j ] ) ? $tokens[ $j ][0] : 0;
			if ( in_array( $id, array( T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL ), true ) ) {
				$start = $j;
				--$j;
				continue;
			}
			break;
		}

		// Existing doc comment?
		$doc_idx = -1;
		$k       = $start - 1;
		while ( $k >= 0 && flosc_skip( $tokens[ $k ] ) ) {
			--$k;
		}
		if ( $k >= 0 && is_array( $tokens[ $k ] ) && T_DOC_COMMENT === $tokens[ $k ][0] ) {
			$doc_idx = $k;
		}

		$params = flosc_collect_params( $tokens, $n, $count );
		$has_rv = flosc_body_returns_value( $tokens, $n, $count );
		$indent = flosc_indent_before( $tokens, $start );

		if ( $doc_idx >= 0 ) {
			$doc  = $tokens[ $doc_idx ][1];
			$miss = array();
			foreach ( $params as $p ) {
				if ( false === strpos( $doc, '$' . ltrim( $p['name'], '$' ) ) ) {
					$miss[] = $p;
				}
			}
			if ( empty( $miss ) ) {
				continue;
			}
			$new = flosc_add_params_to_doc( $doc, $miss, $indent );
			if ( null !== $new && $new !== $doc ) {
				$rewrite[ $doc_idx ] = $new;
				$added_params       += count( $miss );
			}
			continue;
		}

		$insert[ $start ] = flosc_build_doc( $params, $has_rv, $indent, $tokens[ $n ][1] );
		++$added_blocks;
	}

	if ( empty( $insert ) && empty( $rewrite ) ) {
		continue;
	}

	$out = '';
	for ( $i = 0; $i < $count; $i++ ) {
		if ( isset( $insert[ $i ] ) ) {
			$out .= $insert[ $i ];
		}
		$out .= isset( $rewrite[ $i ] ) ? $rewrite[ $i ] : flosc_tt( $tokens[ $i ] );
	}

	$tmp = tempnam( sys_get_temp_dir(), 'dbi' ) . '.php';
	file_put_contents( $tmp, $out );
	$lo = array();
	$rc = 0;
	exec( 'php -l ' . escapeshellarg( $tmp ) . ' 2>&1', $lo, $rc );
	unlink( $tmp );
	if ( 0 !== $rc ) {
		fwrite( STDERR, 'REFUSED (would not parse): ' . $path . "\n" );
		++$refused;
		continue;
	}

	++$changed;
	if ( count( $samples ) < 8 ) {
		$samples[] = str_replace( $root . '/', '', $path );
	}
	if ( ! $dry_run ) {
		file_put_contents( $path, $out );
	}
}

echo $dry_run ? "DRY RUN — nothing written\n\n" : "WRITTEN\n\n";
printf( "  docblocks inserted   : %d\n", $added_blocks );
printf( "  @param tags added    : %d\n", $added_params );
printf( "  files changed        : %d\n", $changed );
printf( "  files refused        : %d\n", $refused );
echo "\nsample files:\n";
foreach ( $samples as $s ) {
	echo '  ' . $s . "\n";
}
