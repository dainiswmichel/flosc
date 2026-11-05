<?php
/**
 * Regression checks for DA1 purchase intent taking priority over catalog terms.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/da1/class-flosc-da1-catalogs.php';

$catalogs = new FLOSC_DA1_Catalogs();
$cases    = array(
	'purchase the manual'                  => true,
	'buy the UberManual'                   => true,
	'how much is the UberManual?'          => true,
	'what does the UberManual cost?'       => true,
	'checkout for $10'                     => true,
	'get the UberManual'                   => true,
	'show me a manual about fitted sheets' => false,
	'suggest three manuals'                => false,
	'find the potato pancake recipe'       => false,
);

foreach ( $cases as $message => $expected ) {
	$actual = $catalogs->has_purchase_intent( $message );
	if ( $expected !== $actual ) {
		echo "FAIL: DA1 purchase-intent case did not match.\n";
		exit( 1 );
	}
}

echo "PASS: DA1 purchase intent stays narrow.\n";
