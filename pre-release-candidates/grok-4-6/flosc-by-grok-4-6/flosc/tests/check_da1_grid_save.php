<?php
/**
 * DA1 grid saves accept only known catalog keys and text tables.
 *
 * @package flosc
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

require dirname( __DIR__ ) . '/includes/da1/class-flosc-da1-catalogs.php';

$flosc_failures = array();

/**
 * Record one failed check.
 *
 * @param bool   $ok      Result.
 * @param string $message What failed.
 * @return void
 */
function flosc_da1_grid_check( $ok, $message ) {
	global $flosc_failures;
	if ( ! $ok ) {
		$flosc_failures[] = $message;
	}
}

$flosc_known_keys = array( 'recipes', 'lessons' );

flosc_da1_grid_check(
	'recipes' === FLOSC_DA1_Catalogs::save_catalog_key( ' Recipes ', $flosc_known_keys ),
	'A known string catalog key was not accepted.'
);
flosc_da1_grid_check(
	'' === FLOSC_DA1_Catalogs::save_catalog_key( 'artwork', $flosc_known_keys ),
	'An unknown string catalog key was accepted.'
);
flosc_da1_grid_check(
	'' === FLOSC_DA1_Catalogs::save_catalog_key( array( 'recipes' ), $flosc_known_keys ),
	'A list was accepted as a catalog key.'
);
flosc_da1_grid_check(
	! FLOSC_DA1_Catalogs::table_is_text( array( 'Row Key', array( 'Status' ) ), array( array( '1', 'active' ) ) ),
	'A list was accepted as a column name.'
);
flosc_da1_grid_check(
	! FLOSC_DA1_Catalogs::table_is_text( array( 'Row Key', 'Status' ), array( array( '1', array( 'active' ) ) ) ),
	'A list was accepted as a row cell.'
);

if ( array() !== $flosc_failures ) {
	fwrite( STDERR, implode( "\n", $flosc_failures ) . "\n" );
	exit( 1 );
}

echo "DA1 grid save shape: ok\n";
exit( 0 );
