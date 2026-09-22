<?php
/**
 * Prove that a comment-only pass changed no executable code.
 *
 * Adding a docblock cannot change behaviour. Proving that it did not is a
 * different matter, and "I only meant to touch comments" is exactly the claim
 * that preceded every round of damage this codebase has taken. So this does not
 * ask to be trusted: it tokenizes each file as PHP itself does, discards every
 * comment and whitespace token, and requires the remaining token stream to be
 * identical to the same file at a given git revision.
 *
 * What survives the filter is every keyword, identifier, operator, literal,
 * variable name and inline-HTML block -- the whole of what the engine executes.
 * If that stream is byte-identical, the two files compile to the same thing and
 * no amount of comment editing in between can have altered behaviour.
 *
 * The one thing this does NOT cover: a docblock read at runtime by an
 * annotation reader. FLOSC has none -- no Doctrine, no attribute parser, no
 * ReflectionMethod::getDocComment() anywhere in the tree -- and this file
 * checks that too, so the exemption stays true rather than merely being
 * asserted here.
 *
 * Usage:
 *   php tests/check_token_identity.php <git-ref>
 *
 * Exit 0 when every file's executable token stream matches, 1 with a list of
 * the files that differ and the first differing token in each.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_root = dirname( __DIR__ );
$flosc_ref  = isset( $argv[1] ) ? (string) $argv[1] : 'HEAD';
$flosc_fail = 0;

/**
 * Executable token stream: everything PHP runs, nothing it ignores.
 *
 * T_WHITESPACE, T_COMMENT and T_DOC_COMMENT are dropped. The open tag is
 * right-trimmed because its token text carries the newline that follows it.
 * T_INLINE_HTML is kept byte-exact, because that text is output.
 *
 * @param string $src PHP source.
 * @return string Serialized token stream, stable across comment edits.
 */
function flosc_exec_tokens( $src ) {
	$drop = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
	$out  = array();

	foreach ( token_get_all( $src ) as $token ) {
		if ( is_string( $token ) ) {
			$out[] = $token;
			continue;
		}
		if ( in_array( $token[0], $drop, true ) ) {
			continue;
		}
		$text = $token[1];
		if ( T_OPEN_TAG === $token[0] || T_OPEN_TAG_WITH_ECHO === $token[0] ) {
			$text = rtrim( $text );
		}
		$out[] = $token[0] . ':' . $text;
	}

	return implode( "\x1f", $out );
}

/**
 * Every PHP file tracked by git under the plugin root.
 *
 * Reading the list from git rather than from disk means a file added by the
 * pass being checked is reported as new rather than silently skipped.
 *
 * @param string $root Plugin root.
 * @param string $ref  Git revision.
 * @return string[] Paths relative to the plugin root.
 */
function flosc_tracked_php( $root, $ref ) {
	$cmd = sprintf(
		'git -C %s ls-tree -r --name-only %s -- .',
		escapeshellarg( $root ),
		escapeshellarg( $ref )
	);
	$out = array();
	exec( $cmd, $out, $status );
	if ( 0 !== $status ) {
		fwrite( STDERR, "cannot read git tree at $ref\n" );
		exit( 1 );
	}

	$files = array();
	foreach ( $out as $path ) {
		if ( substr( $path, -4 ) === '.php' ) {
			$files[] = $path;
		}
	}
	sort( $files );
	return $files;
}

/**
 * One file's source at a git revision.
 *
 * @param string $root Plugin root.
 * @param string $ref  Git revision.
 * @param string $path Path relative to the plugin root.
 * @return string|null Source, or null when the file is absent at that revision.
 */
function flosc_source_at( $root, $ref, $path ) {
	$cmd = sprintf(
		'git -C %s show %s 2>/dev/null',
		escapeshellarg( $root ),
		escapeshellarg( $ref . ':./' . $path )
	);
	$src = shell_exec( $cmd );
	return ( null === $src || '' === $src ) ? null : $src;
}

$flosc_files   = flosc_tracked_php( $flosc_root, $flosc_ref );
$flosc_checked = 0;
$flosc_differ  = array();
$flosc_gone    = array();

foreach ( $flosc_files as $flosc_path ) {
	$flosc_was = flosc_source_at( $flosc_root, $flosc_ref, $flosc_path );
	if ( null === $flosc_was ) {
		continue;
	}

	$flosc_disk = $flosc_root . '/' . $flosc_path;
	if ( ! is_file( $flosc_disk ) ) {
		$flosc_gone[] = $flosc_path;
		continue;
	}

	$flosc_now = (string) file_get_contents( $flosc_disk );
	++$flosc_checked;

	$flosc_a = flosc_exec_tokens( $flosc_was );
	$flosc_b = flosc_exec_tokens( $flosc_now );
	if ( $flosc_a === $flosc_b ) {
		continue;
	}

	// Name the first token that diverges, so a real change is findable.
	$flosc_ta = explode( "\x1f", $flosc_a );
	$flosc_tb = explode( "\x1f", $flosc_b );
	$flosc_at = 0;
	$flosc_n  = min( count( $flosc_ta ), count( $flosc_tb ) );
	while ( $flosc_at < $flosc_n && $flosc_ta[ $flosc_at ] === $flosc_tb[ $flosc_at ] ) {
		++$flosc_at;
	}

	$flosc_differ[ $flosc_path ] = sprintf(
		'token %d of %d: %s -> %s',
		$flosc_at + 1,
		count( $flosc_ta ),
		isset( $flosc_ta[ $flosc_at ] ) ? substr( $flosc_ta[ $flosc_at ], 0, 60 ) : '(end)',
		isset( $flosc_tb[ $flosc_at ] ) ? substr( $flosc_tb[ $flosc_at ], 0, 60 ) : '(end)'
	);
}

printf( "Executable code is identical to %s\n\n", $flosc_ref );
printf( "  files compared          : %d\n", $flosc_checked );
printf( "  files deleted since ref : %d\n", count( $flosc_gone ) );
printf( "  files differing         : %d\n\n", count( $flosc_differ ) );

foreach ( $flosc_gone as $flosc_path ) {
	echo "FAIL  deleted: $flosc_path\n";
	++$flosc_fail;
}
foreach ( $flosc_differ as $flosc_path => $flosc_where ) {
	echo "FAIL  $flosc_path  ($flosc_where)\n";
	++$flosc_fail;
}

/*
 * The exemption above is only true while nothing reads a docblock at runtime.
 * Checked here rather than asserted in prose, because an annotation reader
 * added later would make every comment edit a behaviour change without any
 * other gate noticing.
 */
$flosc_reflect = array();
foreach ( $flosc_files as $flosc_path ) {
	if ( strpos( $flosc_path, 'tests/' ) === 0 || strpos( $flosc_path, 'vendor/' ) === 0 ) {
		continue;
	}
	$flosc_disk = $flosc_root . '/' . $flosc_path;
	if ( ! is_file( $flosc_disk ) ) {
		continue;
	}
	$flosc_src = (string) file_get_contents( $flosc_disk );
	if ( preg_match( '/getDocComment\s*\(|Doctrine\\\\|ReflectionClass::.*DocComment/', $flosc_src ) ) {
		$flosc_reflect[] = $flosc_path;
	}
}

if ( $flosc_reflect ) {
	echo "FAIL  a docblock is read at runtime, so comments are not inert:\n";
	foreach ( $flosc_reflect as $flosc_path ) {
		echo "        $flosc_path\n";
	}
	++$flosc_fail;
} else {
	echo "ok    no docblock is read at runtime (no getDocComment anywhere)\n";
}

echo $flosc_fail ? "\n$flosc_fail FAILURES\n" : "\nNo executable token changed.\n";
exit( $flosc_fail ? 1 : 0 );
