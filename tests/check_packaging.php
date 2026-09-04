<?php
/**
 * What WordPress.org will look at, checked before it does.
 *
 * Plugin Check flags inline style attributes, assets that bypass the enqueue
 * system, and files that can be requested directly. None of those are hard to
 * keep clean; all of them are easy to reintroduce in a hurry, and a submission
 * that comes back over a style="" attribute costs a review cycle.
 *
 * The candidate directories are the other risk. Four full plugin trees plus
 * their ZIPs live under pre-release-candidates on main. A build that swept
 * them in would ship three other people's plugins inside this one.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-62s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

/** Every PHP file that ships, which is not the same as every PHP file here. */
function flosc_shipped_php( $root ) {
	$files = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$rel = str_replace( $root . '/', '', $file->getPathname() );
		if ( substr( $rel, -4 ) !== '.php' ) {
			continue;
		}
		foreach ( array( '.git/', 'tests/', 'pre-release-candidates/', 'sample-data/', 'vendor/', 'node_modules/' ) as $skip ) {
			if ( strpos( $rel, $skip ) === 0 ) {
				continue 2;
			}
		}
		$files[ $rel ] = (string) file_get_contents( $file->getPathname() );
	}
	ksort( $files );
	return $files;
}

$php = flosc_shipped_php( $root );
printf( "Shipped PHP files: %d\n\n", count( $php ) );
ok( 'the tree was read', count( $php ) > 100, true );

// A rule with a name can be changed once. Six style="" attributes cannot.
echo "\nNo inline style attributes in anything that ships\n";
$inline = array();
foreach ( $php as $rel => $src ) {
	if ( preg_match_all( '/\sstyle\s*=\s*["\']/', $src, $m ) ) {
		$inline[] = $rel . ' (' . count( $m[0] ) . ')';
	}
}
ok( 'no style="" in shipped PHP', $inline, array() );

echo "\nEvery asset goes through the enqueue system\n";
$raw = array();
foreach ( $php as $rel => $src ) {
	if ( preg_match( '/<script[^>]+src\s*=/i', $src ) || preg_match( '/<link[^>]+rel\s*=\s*["\']stylesheet/i', $src ) ) {
		$raw[] = $rel;
	}
}
ok( 'no hand-written script or stylesheet tags', $raw, array() );

echo "\nNo shipped file can be requested directly\n";
$unguarded = array();
foreach ( $php as $rel => $src ) {
	// index.php stubs are silence-by-design and need no guard of their own.
	if ( basename( $rel ) === 'index.php' && strlen( $src ) < 200 ) {
		continue;
	}
	// uninstall.php is the exception, and guarding it on ABSPATH would be the
	// error: WordPress defines WP_UNINSTALL_PLUGIN only while it is deleting
	// the plugin, so that is the narrower and correct gate for that one file.
	if ( basename( $rel ) === 'uninstall.php' ) {
		if ( ! preg_match( "/defined\(\s*'WP_UNINSTALL_PLUGIN'\s*\)/", $src ) ) {
			$unguarded[] = $rel . ' (WP_UNINSTALL_PLUGIN)';
		}
		continue;
	}
	if ( ! preg_match( "/defined\(\s*'ABSPATH'\s*\)/", $src ) ) {
		$unguarded[] = $rel;
	}
}
ok( 'every file checks for ABSPATH', $unguarded, array() );

echo "\nThe candidate trees cannot reach a build\n";
$distignore = (string) file_get_contents( $root . '/.distignore' );
$builder    = (string) file_get_contents( $root . '/build-dist-zip.sh' );
ok( 'pre-release-candidates is in .distignore',
	strpos( $distignore, 'pre-release-candidates/' ) !== false, true );
ok( '  and in the hard deny list, which .distignore cannot undo',
	strpos( $builder, "'pre-release-candidates'" ) !== false, true );
ok( 'tests/ too — they stub WordPress and redeclare core functions',
	strpos( $distignore, 'tests/' ) !== false, true );

// Internal notes are not plugin content. HANDOFF.md carries the operator's
// local ship path, their machine's username, and project context that has no
// business in a public plugin directory — and nothing in it is needed to run
// FLOSC. It shipped until someone read the artifact's file list.
ok( 'and the session handoff notes stay out of the artifact',
	strpos( $distignore, 'HANDOFF.md' ) !== false, true );

echo "\nThe version has not moved\n";
$main   = (string) file_get_contents( $root . '/flosc.php' );
$readme = (string) file_get_contents( $root . '/readme.txt' );
preg_match( '/^ \* Version:\s*(\S+)/m', $main, $v );
preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $t );
ok( 'flosc.php header', isset( $v[1] ) ? $v[1] : '', '8.0.0' );
ok( 'readme.txt stable tag', isset( $t[1] ) ? $t[1] : '', '8.0.0' );
ok( '  and they agree', ( $v[1] ?? 'a' ) === ( $t[1] ?? 'b' ), true );

echo $fail ? "\n$fail FAILURES\n" : "\nThe tree is shippable\n";
exit( $fail ? 1 : 0 );
