<?php
/**
 * Pre-login quiz transfer must call the bridge manager's real create method.
 *
 * @package flosc
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$flosc_framework = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-flosc-framework.php' );
$flosc_failures  = array();

if ( false !== strpos( $flosc_framework, 'update_flosc_bridge_data(' ) ) {
	$flosc_failures[] = 'The pre-login transfer still calls the nonexistent update_flosc_bridge_data() method.';
}

if ( 1 !== substr_count( $flosc_framework, 'flosc_create_bridge_data( $user_id, $quiz_id, $score_data )' ) ) {
	$flosc_failures[] = 'The pre-login transfer does not create bridge data with the user, quiz, and complete score data.';
}

if ( array() !== $flosc_failures ) {
	fwrite( STDERR, implode( "\n", $flosc_failures ) . "\n" );
	exit( 1 );
}

echo "bridge data transfer: ok\n";
exit( 0 );
