<?php
/**
 * Every file a starter pack promises must be in the pack, and in the ZIP.
 *
 * The packs are directory payloads: pack.json, content.json, flow_ivr.md, and
 * for three of them binaries — a catalog TSV, two PDFs, a recipes XML. The
 * installer is not the risk. The build is. A .distignore rule, a stray *.pdf
 * exclusion, or a rename nobody followed through and the pack installs against
 * an asset that is not there — on a fresh site, in front of whoever is trying
 * FLOSC for the first time.
 *
 * That failure is invisible in the working tree, because in the working tree
 * every file is present. It only appears in the artifact, which is why this
 * checks the artifact when one has been built and the source tree otherwise.
 *
 * Run against a built ZIP:  php tests/check_starter_pack_assets.php path/to/flosc.zip
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

// Where to look. A ZIP argument makes this a check of the shipped artifact;
// without one it checks the tree the artifact would be built from.
$zip_path = isset( $argv[1] ) ? (string) $argv[1] : '';
$listing  = null;

if ( $zip_path !== '' ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		echo "FAIL ZipArchive is not available in this PHP build\n";
		exit( 1 );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		printf( "FAIL could not open %s\n", $zip_path );
		exit( 1 );
	}
	$listing = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$listing[ $zip->getNameIndex( $i ) ] = $zip->statIndex( $i )['size'];
	}
	$zip->close();
	printf( "Checking the built artifact: %s (%d entries)\n\n", basename( $zip_path ), count( $listing ) );
} else {
	echo "Checking the source tree. Pass a ZIP path to check a built artifact instead.\n\n";
}

/**
 * Is this pack-relative file present, and not empty?
 */
function flosc_pack_file( $slug, $file, $listing, $root ) {
	if ( is_array( $listing ) ) {
		$needle = 'flosc/starter-packs/' . $slug . '/' . $file;
		return isset( $listing[ $needle ] ) && $listing[ $needle ] > 0;
	}
	$path = $root . '/starter-packs/' . $slug . '/' . $file;
	return is_file( $path ) && filesize( $path ) > 0;
}

$packs = glob( $root . '/starter-packs/*/pack.json' );
sort( $packs );

echo "Every shipped pack was found\n";
ok( 'four starter packs', count( $packs ), 4 );

foreach ( $packs as $pack_json ) {
	$slug = basename( dirname( $pack_json ) );
	$pack = json_decode( (string) file_get_contents( $pack_json ), true );

	printf( "\n%s\n", $slug );
	ok( '  pack.json parses', is_array( $pack ), true );
	if ( ! is_array( $pack ) ) {
		continue;
	}

	ok( '  the slug matches its directory', (string) ( $pack['slug'] ?? '' ), $slug );

	// Everything a pack.json points at, wherever it points from.
	$referenced = array( 'pack.json' );
	if ( ! empty( $pack['flow']['file'] ) ) {
		$referenced[] = (string) $pack['flow']['file'];
	}
	if ( ! empty( $pack['content']['file'] ) ) {
		$referenced[] = (string) $pack['content']['file'];
	}
	if ( ! empty( $pack['catalog']['file'] ) ) {
		$referenced[] = (string) $pack['catalog']['file'];
	}
	foreach ( (array) ( $pack['assets'] ?? array() ) as $asset ) {
		if ( ! empty( $asset['file'] ) ) {
			$referenced[] = (string) $asset['file'];
		}
	}

	// Everything pack.json points at must exist.
	foreach ( array_unique( $referenced ) as $file ) {
		ok( '  ships ' . $file, flosc_pack_file( $slug, $file, $listing, $root ), true );
	}

	/*
	 * And everything in the directory must be declared.
	 *
	 * The READMEs tell a floscAdmin to import the WXR files by hand — the
	 * installer never reads them, so nothing referenced them and nothing would
	 * have stopped a build exclusion dropping them and leaving the instructions
	 * pointing at files that are not there. "ships" is the pack saying what it
	 * contains, so a gate can hold the build to it.
	 */
	$declared = array_map( 'strval', (array) ( $pack['ships'] ?? array() ) );
	ok( '  declares what it ships', count( $declared ) > 0, true );

	foreach ( $declared as $file ) {
		ok( '  ships ' . $file, flosc_pack_file( $slug, $file, $listing, $root ), true );
	}

	$present = array_values( array_filter( scandir( dirname( $pack_json ) ), static function ( $f ) {
		return $f !== '.' && $f !== '..' && strpos( $f, '.' ) !== 0;
	} ) );
	sort( $present );
	$missing_from_manifest = array_values( array_diff( $present, $declared ) );
	ok( '  nothing in the pack is undeclared', $missing_from_manifest, array() );

	$referenced_not_declared = array_values( array_diff( array_unique( $referenced ), $declared ) );
	ok( '  everything it points at is also declared', $referenced_not_declared, array() );

	// A pack names the personality it attaches. Naming one that is not in the
	// library installs a flow with no character at all.
	$personality = (string) ( $pack['personality'] ?? '' );
	if ( $personality !== '' ) {
		$library = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
		ok( "  attaches '" . $personality . "', which the library ships",
			strpos( $library, "'id'                     => '" . $personality . "'" ) !== false, true );
	}
}

// The tests directory stubs WordPress on purpose — it defines ABSPATH and
// redeclares core functions — so it must never reach a running site.
if ( is_array( $listing ) ) {
	echo "\nThe artifact carries nothing it should not\n";
	$forbidden = array( 'flosc/tests/', 'flosc/pre-release-candidates/', 'flosc/sample-data/', 'flosc/.git' );
	foreach ( $forbidden as $prefix ) {
		$found = array_values( array_filter( array_keys( $listing ), static function ( $name ) use ( $prefix ) {
			return strpos( $name, $prefix ) === 0;
		} ) );
		ok( 'no ' . $prefix, count( $found ), 0 );
	}

	echo "\nAnd exactly one top-level directory\n";
	$tops = array();
	foreach ( array_keys( $listing ) as $name ) {
		$tops[ strtok( $name, '/' ) ] = true;
	}
	ok( 'one root, named flosc', array_keys( $tops ), array( 'flosc' ) );
}

echo $fail ? "\n$fail FAILURES\n" : "\nEvery pack ships what it promises\n";
exit( $fail ? 1 : 0 );
