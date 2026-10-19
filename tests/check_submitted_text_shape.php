<?php
/**
 * A list posted into a one-value field must not replace the stored value.
 *
 * The decision is executed here. Reading the plugin text is not the check.
 * A string, an API key that contains %2F+/=.~, and personality profile text
 * that contains a heading mark and a blank line must come back unchanged.
 *
 * @package flosc
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	/**
	 * Stand-in for the WordPress UTF-8 check. These fixtures are already UTF-8.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_check_invalid_utf8( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * The personality file registers its admin hooks while it loads.
	 *
	 * @return void
	 */
	function add_action() {}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * The personality file registers its admin body class while it loads.
	 *
	 * @return void
	 */
	function add_filter() {}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stand-in.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

require dirname( __DIR__ ) . '/includes/flosc-accessors.php';
require dirname( __DIR__ ) . '/includes/ai/flosc-provider-keys.php';
require dirname( __DIR__ ) . '/includes/flosc-personality-library.php';

/**
 * The save rule: a list leaves the stored value in place. Text is kept as text.
 *
 * @param mixed $stored    Value already stored.
 * @param mixed $submitted Value copied from the request.
 * @return mixed
 */
function flosc_keep_stored_text( $stored, $submitted ) {
	if ( ! flosc_submitted_value_is_text( $submitted ) ) {
		return $stored;
	}
	return $submitted;
}

$flosc_failures = array();

/**
 * Record one failed check.
 *
 * @param bool   $ok      Result.
 * @param string $message What failed.
 * @return void
 */
function flosc_shape_check( $ok, $message ) {
	global $flosc_failures;
	if ( ! $ok ) {
		$flosc_failures[] = $message;
	}
}

$flosc_stored_name = 'Spring Offer';
flosc_shape_check(
	'Spring Offer' === flosc_keep_stored_text( $flosc_stored_name, array( 'posted-list' ) ),
	'A list replaced stored offer text.'
);
flosc_shape_check(
	'Spring Offer updated' === flosc_keep_stored_text( $flosc_stored_name, 'Spring Offer updated' ),
	'A string was not stored.'
);
flosc_shape_check(
	'Array' !== flosc_keep_stored_text( $flosc_stored_name, array( 'posted-list' ) ),
	'The stored value became the word Array.'
);

$flosc_api_key = 'sk-live_%2F+/=.~';
flosc_shape_check( flosc_submitted_value_is_text( $flosc_api_key ), 'An API key was not accepted as text.' );
flosc_shape_check(
	flosc_sanitize_api_secret( $flosc_api_key ) === $flosc_api_key,
	'API key characters were changed.'
);
flosc_shape_check(
	'' === flosc_sanitize_api_secret( array( 'posted-list' ) ),
	'A list was turned into secret text.'
);
flosc_shape_check(
	flosc_keep_stored_text( $flosc_api_key, array( 'posted-list' ) ) === $flosc_api_key,
	'A list replaced the stored API key.'
);

$flosc_profile = "# Heading\n\nA blank line and a # hash stay.";
flosc_shape_check(
	flosc_sanitize_personality_profile_text( $flosc_profile ) === $flosc_profile,
	'Personality profile text was changed.'
);
flosc_shape_check(
	flosc_keep_stored_text( $flosc_profile, array( 'posted-list' ) ) === $flosc_profile,
	'A list replaced the stored personality profile.'
);

$flosc_offer = array(
	'offer_name'          => 'Named',
	'offer_access_codes'  => '',
	'offer_coupon_code'   => array( 'SAVE10' ),
	'offer_coupon_type'   => array( 'fixed_price' ),
	'offer_coupon_active' => array( '1' ),
);
$flosc_offer_lists = array( 'offer_coupon_code', 'offer_coupon_type', 'offer_coupon_active' );
flosc_shape_check(
	flosc_submitted_request_is_text( $flosc_offer, $flosc_offer_lists ),
	'A normal offer was refused.'
);

$flosc_bad_code                 = $flosc_offer;
$flosc_bad_code['offer_access_codes'] = array( 'posted-list' );
flosc_shape_check(
	! flosc_submitted_request_is_text( $flosc_bad_code, $flosc_offer_lists ),
	'A list in the access-code field was accepted.'
);

$flosc_bad_coupon                   = $flosc_offer;
$flosc_bad_coupon['offer_coupon_code'] = 'SAVE10';
flosc_shape_check(
	! flosc_submitted_request_is_text( $flosc_bad_coupon, $flosc_offer_lists ),
	'A string was accepted where a coupon list is required.'
);

$flosc_bad_cell                      = $flosc_offer;
$flosc_bad_cell['offer_coupon_code'] = array( array( 'posted-list' ) );
flosc_shape_check(
	! flosc_submitted_request_is_text( $flosc_bad_cell, $flosc_offer_lists ),
	'A nested coupon cell was accepted.'
);

flosc_shape_check( flosc_submitted_list_is_text( array( '12', '34' ) ), 'A list of text ids was refused.' );
flosc_shape_check( ! flosc_submitted_list_is_text( '12' ), 'A string was treated as a list.' );
flosc_shape_check( flosc_submitted_value_is_text( '3' ), 'A numeric string was refused.' );
flosc_shape_check( flosc_submitted_value_is_text( 3 ), 'A number was refused.' );
flosc_shape_check( ! flosc_submitted_value_is_text( array( '3' ) ), 'A list was treated as one value.' );

if ( array() !== $flosc_failures ) {
	fwrite( STDERR, implode( "\n", $flosc_failures ) . "\n" );
	exit( 1 );
}

echo "submitted text shape: ok\n";
exit( 0 );
