<?php
/**
 * Settings API secret callbacks preserve credential bytes.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

$flosc_test_filter  = '';
$flosc_test_options = array();

function current_filter() {
	global $flosc_test_filter;
	return $flosc_test_filter;
}

function get_option( $name, $default = false ) {
	global $flosc_test_options;
	return array_key_exists( $name, $flosc_test_options ) ? $flosc_test_options[ $name ] : $default;
}

require dirname( __DIR__ ) . '/includes/flosc-admin.php';
require dirname( __DIR__ ) . '/includes/sso/class-sso-manager.php';

class FLOSC_Test_Admin_Secrets {
	use FLOSC_Admin_Trait;
}

$flosc_test_fail = 0;
function flosc_test_ok( $label, $actual, $expected ) {
	global $flosc_test_fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		$flosc_test_fail++;
	}
	printf(
		"%s %-58s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

$admin      = new FLOSC_Test_Admin_Secrets();
$reflection = new ReflectionClass( '\\FLOSC\\SSO\\SSO_Manager' );
$sso        = $reflection->newInstanceWithoutConstructor();
$sample     = "audit\\literal\\secret<value>%2F\nline2\r\n-----BEGIN KEY-----\nabc\\def\n-----END KEY-----";

foreach (
	array(
		'flosc_sso_google_client_secret' => array( $admin, 'sanitize_secret_setting' ),
		'flosc_sso_apple_private_key'    => array( $sso, 'sanitize_secret_setting' ),
	) as $option_name => $callback
) {
	$flosc_test_filter                  = 'sanitize_option_' . $option_name;
	$flosc_test_options[ $option_name ] = 'already stored';

	echo $option_name . "\n";
	flosc_test_ok( 'quotes, slashes, percent signs, and newlines survive', call_user_func( $callback, $sample ), $sample );
	flosc_test_ok( 'blank input preserves the stored credential', call_user_func( $callback, '' ), 'already stored' );
	flosc_test_ok( 'non-string input preserves the stored credential', call_user_func( $callback, array( 'bad' ) ), 'already stored' );
}

echo $flosc_test_fail ? "\n{$flosc_test_fail} FAILURES\n" : "\nSecret sanitizers: all checks passed\n";
exit( $flosc_test_fail ? 1 : 0 );
