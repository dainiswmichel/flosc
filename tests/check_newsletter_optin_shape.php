<?php
/**
 * Newsletter opt-in has two stored states. A list is neither of them.
 *
 * Checked subscribes. A missing field unsubscribes. A posted list leaves the
 * stored subscription unchanged.
 *
 * @package flosc
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

require dirname( __DIR__ ) . '/includes/email/class-flosc-email.php';

$flosc_failures = array();

/**
 * Record one failed check.
 *
 * @param bool   $ok      Result.
 * @param string $message What failed.
 * @return void
 */
function flosc_optin_check( $ok, $message ) {
	global $flosc_failures;
	if ( ! $ok ) {
		$flosc_failures[] = $message;
	}
}

flosc_optin_check(
	'subscribe' === flosc_newsletter_optin_decision( '1' ),
	'A checked box did not subscribe.'
);
flosc_optin_check(
	'unsubscribe' === flosc_newsletter_optin_decision( null ),
	'A missing checkbox did not unsubscribe.'
);
flosc_optin_check(
	'unchanged' === flosc_newsletter_optin_decision( array( '1' ) ),
	'A posted list changed the subscription decision.'
);
flosc_optin_check(
	'unchanged' === flosc_newsletter_optin_decision( (object) array( 'value' => '1' ) ),
	'A posted object changed the subscription decision.'
);

if ( array() !== $flosc_failures ) {
	fwrite( STDERR, implode( "\n", $flosc_failures ) . "\n" );
	exit( 1 );
}

echo "newsletter opt-in shape: ok\n";
exit( 0 );
