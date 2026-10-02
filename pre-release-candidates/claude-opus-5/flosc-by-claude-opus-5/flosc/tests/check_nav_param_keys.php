<?php
/**
 * Every query key an admin screen reads is declared in flosc_nav_param_keys().
 *
 * admin/settings.php, admin/flow.php, admin/flows.php and admin/offers.php
 * build $flosc_get from flosc_nav_params(), which reads a declared list rather
 * than whatever the URL carried. A key left off that list does not raise an
 * error. The parameter simply never arrives and the feature that wanted it goes
 * quiet.
 *
 * That already happened once. 'catalog' and 'da1_export' were omitted when the
 * list was first written, which left the DA1 screen unable to select a catalog
 * from a URL and made its Export TSV button do nothing. Forty-two other gates
 * passed, php -l passed, WPCS passed, and the button was dead. The omission was
 * found by a person reading a diff.
 *
 * Two mistakes made it invisible, and both are why this file resolves aliases
 * rather than grepping for one variable name:
 *
 *   1. admin/da1.php copies the array -- $flosc_da1_get = $flosc_get -- so the
 *      keys are read through a second name, and a search for $flosc_get['...']
 *      finds nothing there.
 *   2. The check that was supposed to find aliases matched [a-z_]+, which does
 *      not match the digits in $flosc_da1_get.
 *
 * So: find every variable assigned from $flosc_get, follow one level of
 * aliasing, collect every literal key read through any of those names, and
 * require each to be declared. Reports both directions -- a key read but not
 * declared is a broken feature, a key declared but read nowhere is dead weight
 * worth knowing about.
 *
 * Exit 0 when every key read is declared, 1 with a list otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_root = dirname( __DIR__ );
$flosc_fail = 0;

/**
 * Report one assertion in the shape the rest of the suite uses.
 *
 * @param string $label What is being asserted.
 * @param mixed  $got   The measured value.
 * @param mixed  $want  The required value.
 * @return void
 */
function flosc_nav_ok( $label, $got, $want ) {
	global $flosc_fail;
	$pass = ( $got === $want );
	if ( ! $pass ) {
		++$flosc_fail;
	}
	printf(
		"%-5s%-58s %s%s\n",
		$pass ? 'ok' : 'FAIL',
		$label,
		is_array( $got ) ? trim( var_export( $got, true ) ) : var_export( $got, true ),
		$pass ? '' : ' (want ' . trim( var_export( $want, true ) ) . ')'
	);
}

/**
 * Every shipping PHP file, tests excluded.
 *
 * @param string $root Plugin root.
 * @return string[] Absolute paths.
 */
function flosc_nav_php_files( $root ) {
	$out      = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);
	/*
	 * Match on the path RELATIVE to $root, never on the absolute path.
	 *
	 * These skips were written against the absolute path. This plugin's own
	 * home in git is pre-release-candidates/<agent>/flosc-by-<agent>/flosc, so
	 * every absolute path inside it contains "/pre-release-candidates/" and the
	 * walker skipped the entire tree. It then read 0 keys, concluded all 28
	 * declared keys were unread, and failed — while reporting a clean run from
	 * a ship tree that happens to live outside that directory.
	 *
	 * That is the same shape as the phpcs.xml.dist exclude-pattern for the
	 * candidates directory, which matches zero files when the scan is started
	 * inside one. A path filter written for one checkout location silently
	 * blinds the check in another.
	 *
	 * The danger is not the red result. It is the green one: an agent reading
	 * "28 declared but never read" deletes the declared keys, and the DA1 screen
	 * loses its catalog selection and its Export TSV button again, exactly as in
	 * v95. Relative matching skips a nested candidate tree and nothing else.
	 */
	$root_prefix = rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	$skip_dirs   = array( 'tests', 'vendor', 'pre-release-candidates', 'testing-environment' );

	foreach ( $iterator as $file ) {
		$path = $file->getPathname();
		if ( substr( $path, -4 ) !== '.php' ) {
			continue;
		}

		$relative = ( 0 === strpos( $path, $root_prefix ) )
			? substr( $path, strlen( $root_prefix ) )
			: $path;
		$relative = DIRECTORY_SEPARATOR . ltrim( $relative, DIRECTORY_SEPARATOR );

		$skip = false;
		foreach ( $skip_dirs as $dir ) {
			if ( strpos( $relative, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR ) !== false ) {
				$skip = true;
				break;
			}
		}
		if ( $skip ) {
			continue;
		}

		$out[] = $path;
	}
	sort( $out );
	return $out;
}

$flosc_files = flosc_nav_php_files( $flosc_root );

echo "The declared list is the one the code actually reads\n";

// The names $flosc_get is known by: itself, plus anything assigned from it.
$flosc_names = array( 'flosc_get' => true );
foreach ( $flosc_files as $flosc_file ) {
	$flosc_src = (string) file_get_contents( $flosc_file );
	if ( preg_match_all( '/\$([a-zA-Z0-9_]+)\s*=\s*\$flosc_get\s*;/', $flosc_src, $flosc_m ) ) {
		foreach ( $flosc_m[1] as $flosc_alias ) {
			$flosc_names[ $flosc_alias ] = true;
		}
	}
}

flosc_nav_ok(
	'  $flosc_get is read under at least one name',
	count( $flosc_names ) >= 1,
	true
);

// Every literal key read through any of those names.
$flosc_pattern = '/\$(' . implode( '|', array_map( 'preg_quote', array_keys( $flosc_names ) ) ) . ')\[\s*[\'"]([^\'"]+)[\'"]\s*\]/';
$flosc_read    = array();
foreach ( $flosc_files as $flosc_file ) {
	$flosc_src = (string) file_get_contents( $flosc_file );
	if ( preg_match_all( $flosc_pattern, $flosc_src, $flosc_m, PREG_SET_ORDER ) ) {
		foreach ( $flosc_m as $flosc_hit ) {
			$flosc_read[ $flosc_hit[2] ] = true;
		}
	}
}

// The declared list, read from source so this gate does not boot WordPress.
$flosc_request_src = (string) file_get_contents( $flosc_root . '/includes/flosc-request.php' );
$flosc_declared    = array();
if ( preg_match( '/function flosc_nav_param_keys\(\).*?return array\((.*?)\);/s', $flosc_request_src, $flosc_block ) ) {
	if ( preg_match_all( '/[\'"]([^\'"]+)[\'"]/', $flosc_block[1], $flosc_keys ) ) {
		foreach ( $flosc_keys[1] as $flosc_key ) {
			$flosc_declared[ $flosc_key ] = true;
		}
	}
}

flosc_nav_ok( '  flosc_nav_param_keys() was found and parsed', count( $flosc_declared ) > 0, true );

ksort( $flosc_read );
ksort( $flosc_declared );

$flosc_undeclared = array_keys( array_diff_key( $flosc_read, $flosc_declared ) );
$flosc_unread     = array_keys( array_diff_key( $flosc_declared, $flosc_read ) );

printf( "\n  names carrying the array : %s\n", implode( ', ', array_keys( $flosc_names ) ) );
printf( "  keys read                : %d\n", count( $flosc_read ) );
printf( "  keys declared            : %d\n\n", count( $flosc_declared ) );

flosc_nav_ok( 'every key an admin screen reads is declared', $flosc_undeclared, array() );
flosc_nav_ok( '  and nothing is declared that no screen reads', $flosc_unread, array() );

// The two that were missed, named so a future edit cannot quietly drop them.
foreach ( array( 'catalog', 'da1_export' ) as $flosc_regression_key ) {
	flosc_nav_ok(
		sprintf( "  '%s' is declared (it was omitted once)", $flosc_regression_key ),
		isset( $flosc_declared[ $flosc_regression_key ] ),
		true
	);
}

echo "\nAbsent parameters stay absent\n";

/*
 * flosc_nav_params() must not fill the array with empty strings. Every caller
 * tests with isset(), so a key present-but-empty reads as supplied and changes
 * which branch runs. The contract is: only what the request carried.
 */
flosc_nav_ok(
	'  the reader skips keys the request did not carry',
	(bool) preg_match( '/!\s*isset\(\s*\$_GET\[\s*\$flosc_key\s*\]\s*\)\s*\)\s*\{\s*continue;/s', $flosc_request_src ),
	true
);

flosc_nav_ok(
	'  and values are sanitized where they are read',
	(bool) preg_match( '/sanitize_text_field\(\s*wp_unslash\(\s*\$_GET\[\s*\$flosc_key\s*\]\s*\)\s*\)/', $flosc_request_src ),
	true
);

/*
 * sanitize_key() would be wrong here and the reason is not obvious: 'ivr' and
 * 'flosc_download_ivr' carry IVR filenames such as dainis_net_ivr.md, and
 * sanitize_key() strips the dot, so the extension disappears and the file is
 * never found.
 */
flosc_nav_ok(
	'  and not with sanitize_key(), which would eat .md filenames',
	(bool) preg_match( '/sanitize_key\(\s*wp_unslash\(\s*\$_GET/', $flosc_request_src ),
	false
);

echo $flosc_fail ? "\n$flosc_fail FAILURES\n" : "\nThe declared list matches what the screens read\n";
exit( $flosc_fail ? 1 : 0 );
