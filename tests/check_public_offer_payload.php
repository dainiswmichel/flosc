<?php
/**
 * Keep redemption and coupon definitions out of customer offer data.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/sale/class-flosc-checkout-rest.php';

$offer = array(
	'id'              => 'trial_membership',
	'name'            => 'Trial membership',
	'price'           => 10,
	'display_formats' => array( 'card' => array( 'enabled' => true ) ),
	'pricing'         => array( 'price' => 10, 'processor' => 'paypal' ),
	'access_codes'    => array( 'fixture-access-code' ),
	'coupons'         => array( array( 'code' => 'fixture-coupon' ) ),
);

$expected = $offer;
unset( $expected['access_codes'], $expected['coupons'] );

if ( $expected !== FLOSC_Checkout_REST::public_offer_fields( $offer ) ) {
	fwrite( STDERR, "FAIL: customer offer data must preserve display and billing fields while omitting private codes.\n" );
	exit( 1 );
}

if ( array() !== FLOSC_Checkout_REST::public_offer_fields( null ) ) {
	fwrite( STDERR, "FAIL: an invalid offer must not produce customer data.\n" );
	exit( 1 );
}

echo "PASS: display and billing fields are preserved. Access codes and coupon definitions are omitted.\n";
