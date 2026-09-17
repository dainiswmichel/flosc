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

// Internal notes are not plugin content. A handoff carries the operator's
// local ship path, their machine's username, and project context that has no
// business in a public plugin directory — and nothing in it is needed to run
// FLOSC. It shipped until someone read the artifact's file list.
// Checked by enumeration, not by string: a second handoff file under a new
// name is exactly how this leaks back in, so every handoff*.md in the root
// must be matched by some .distignore pattern.
$dist_patterns = array();
foreach ( preg_split( '/\R/', $distignore ) as $dist_line ) {
	$dist_line = trim( preg_replace( '/#.*$/', '', $dist_line ) );
	if ( '' !== $dist_line ) {
		$dist_patterns[] = rtrim( $dist_line, '/' );
	}
}
$handoffs = glob( $root . '/[Hh][Aa][Nn][Dd][Oo][Ff][Ff]*.md' );
$unshielded = array();
foreach ( (array) $handoffs as $handoff ) {
	$base    = basename( $handoff );
	$shielded = false;
	foreach ( $dist_patterns as $dist_pattern ) {
		if ( fnmatch( $dist_pattern, $base ) ) {
			$shielded = true;
			break;
		}
	}
	if ( ! $shielded ) {
		$unshielded[] = $base;
	}
}
ok( 'and the session handoff notes stay out of the artifact',
	$unshielded ? implode( ', ', $unshielded ) : 'all excluded', 'all excluded' );

echo "\nThe version has not moved\n";
$main   = (string) file_get_contents( $root . '/flosc.php' );
$readme = (string) file_get_contents( $root . '/readme.txt' );
preg_match( '/^ \* Version:\s*(\S+)/m', $main, $v );
preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $t );
ok( 'flosc.php header', isset( $v[1] ) ? $v[1] : '', '8.0.0' );
ok( 'readme.txt stable tag', isset( $t[1] ) ? $t[1] : '', '8.0.0' );
ok( '  and they agree', ( $v[1] ?? 'a' ) === ( $t[1] ?? 'b' ), true );

/*
 * The WordPress version headers are checked by SHAPE, never against a stored value.
 *
 * This block used to assert 'Requires at least' === '7.0.4' and printed a
 * confident comment explaining why that was right. WordPress.org returned it as
 * an ERROR in three consecutive reviews — 27 Jun, 12 Jul and 13 Sep 2026 — while
 * this suite printed "0 failing gates", because the assertion recorded what the
 * code said instead of what the standard requires. A test that agrees with the
 * code it is testing cannot fail when the code is wrong.
 *
 * The rule, from the 13 Sep 2026 email: "include only the major WordPress
 * version, as the minor version is ignored." So: major.minor, no patch digit.
 * No version literal appears below.
 */
echo "\nThe WordPress version headers name a real release\n";
preg_match( '/^Requires at least:\s*(\S+)/m', $readme, $rmin );
preg_match( '/^Tested up to:\s*(\S+)/m', $readme, $rmax );
preg_match( '/^ \* Requires at least:\s*(\S+)/m', $main, $pmin );
ok( 'readme.txt Requires at least is major.minor', (bool) preg_match( '/^\d+\.\d+$/', $rmin[1] ?? '' ), true );
ok( 'readme.txt Tested up to is major.minor', (bool) preg_match( '/^\d+\.\d+$/', $rmax[1] ?? '' ), true );
ok( 'flosc.php Requires at least is major.minor', (bool) preg_match( '/^\d+\.\d+$/', $pmin[1] ?? '' ), true );
ok( '  and the two files agree', ( $rmin[1] ?? 'a' ) === ( $pmin[1] ?? 'b' ), true );
ok( '  and Tested up to is never ahead of Requires at least',
	version_compare( $rmax[1] ?? '0', $rmin[1] ?? '0', '>=' ), true );

echo $fail ? "\n$fail FAILURES\n" : "\nThe tree is shippable\n";
exit( $fail ? 1 : 0 );
