<?php
/**
 * Every literal require/include path in the plugin names a file that is there.
 *
 * A missing include is a fatal on whichever request first reaches it, and
 * nothing else in this suite would catch it: php -l parses one file at a time
 * and never follows a require, and the other gates stub what they need rather
 * than booting the plugin.
 *
 * v82.14 renamed 34 class files to carry the flosc- prefix the coding standard
 * requires. Every reference was rewritten, but "every" is a claim, and this is
 * what checks it -- on that commit and on every one after.
 *
 * Only literal paths are checked: require FLOSC_PLUGIN_DIR . 'includes/x.php'
 * and the __DIR__ forms. A path built from a variable cannot be resolved
 * without running the plugin, and those are counted and reported rather than
 * silently passed over.
 *
 * Exit 0 when every literal path resolves, 1 with a list otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_root = dirname( __DIR__ );

/**
 * Every PHP file that ships, as paths relative to the plugin root.
 *
 * @param string $root Plugin root directory.
 * @return string[] Relative paths.
 */
function flosc_shipping_php_files( $root ) {
	$found    = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		$path = $file->getPathname();
		if ( substr( $path, -4 ) !== '.php' ) {
			continue;
		}
		$rel = ltrim( str_replace( $root, '', $path ), '/' );
		if ( preg_match( '#^(node_modules|vendor|tests)/#', $rel ) ) {
			continue;
		}
		$found[] = $rel;
	}
	sort( $found );
	return $found;
}

$flosc_files    = flosc_shipping_php_files( $flosc_root );
$flosc_checked  = 0;
$flosc_dynamic  = 0;
$flosc_missing  = array();
$flosc_literal  = '/(?:require|include)(?:_once)?\s+(?<base>FLOSC_PLUGIN_DIR|dirname\(\s*__DIR__\s*\)|__DIR__)\s*\.\s*\'(?<path>[^\']+)\'/';
$flosc_variable = '/(?:require|include)(?:_once)?\s+\$/';

foreach ( $flosc_files as $flosc_rel ) {
	$flosc_source = (string) file_get_contents( $flosc_root . '/' . $flosc_rel );

	$flosc_dynamic += preg_match_all( $flosc_variable, $flosc_source );

	if ( ! preg_match_all( $flosc_literal, $flosc_source, $flosc_hits, PREG_SET_ORDER ) ) {
		continue;
	}

	foreach ( $flosc_hits as $flosc_hit ) {
		++$flosc_checked;

		if ( 'FLOSC_PLUGIN_DIR' === $flosc_hit['base'] ) {
			$flosc_base = $flosc_root;
		} elseif ( '__DIR__' === $flosc_hit['base'] ) {
			$flosc_base = dirname( $flosc_root . '/' . $flosc_rel );
		} else {
			$flosc_base = dirname( dirname( $flosc_root . '/' . $flosc_rel ) );
		}

		$flosc_target = $flosc_base . '/' . ltrim( $flosc_hit['path'], '/' );
		if ( ! file_exists( $flosc_target ) ) {
			$flosc_missing[] = $flosc_rel . ' requires ' . $flosc_hit['path'];
		}
	}
}

printf(
	"check_include_paths: %d literal paths in %d files; %d built from a variable and not checkable here\n",
	$flosc_checked,
	count( $flosc_files ),
	$flosc_dynamic
);

if ( $flosc_missing ) {
	foreach ( $flosc_missing as $flosc_line ) {
		echo '  FAIL  ' . $flosc_line . "\n";
	}
	exit( 1 );
}

echo "  PASS  every literal include path resolves\n";
exit( 0 );
