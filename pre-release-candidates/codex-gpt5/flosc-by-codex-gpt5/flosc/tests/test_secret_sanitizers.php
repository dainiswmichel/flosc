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

function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
}

function sanitize_text_field( $value ) {
	return (string) $value;
}

function sanitize_textarea_field( $value ) {
	return (string) $value;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function esc_url_raw( $value ) {
	return (string) $value;
}

function sanitize_hex_color( $value ) {
	return (string) $value;
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

echo "Settings API callbacks receive values WordPress already unslashed\n";
$literal = 'C:\\FLOSC\\profiles\\voice and regex \\d+\\s';
flosc_test_ok( 'admin text callback preserves literal backslashes', $admin->sanitize_text_setting( $literal ), $literal );
flosc_test_ok( 'admin textarea callback preserves literal backslashes', $admin->sanitize_textarea_setting( $literal ), $literal );
flosc_test_ok( 'admin URL callback preserves literal backslashes', $admin->sanitize_url_setting( $literal ), $literal );
flosc_test_ok( 'admin nested-array callback preserves literal backslashes', $admin->sanitize_array_setting( array( 'path' => $literal ) ), array( 'path' => $literal ) );
flosc_test_ok( 'SSO text callback preserves literal backslashes', $sso->sanitize_text_setting( $literal ), $literal );
flosc_test_ok( 'SSO textarea callback preserves literal backslashes', $sso->sanitize_textarea_setting( $literal ), $literal );

$admin_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/flosc-admin.php' );
$sso_source   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/sso/class-sso-manager.php' );
$settings_source = (string) file_get_contents( dirname( __DIR__ ) . '/admin/settings.php' );
flosc_test_ok( 'admin setting callbacks do not unslash a second time', strpos( $admin_source, 'sanitize_text_field(wp_unslash((string) $value))' ), false );
flosc_test_ok( 'SSO setting callbacks do not unslash a second time', strpos( $sso_source, 'sanitize_text_field(wp_unslash((string) $value))' ), false );
flosc_test_ok( 'settings handler does not unslash its prepared POST values again', strpos( $settings_source, 'wp_unslash((string) $flosc_post' ), false );

echo $flosc_test_fail ? "\n{$flosc_test_fail} FAILURES\n" : "\nSecret sanitizers: all checks passed\n";
exit( $flosc_test_fail ? 1 : 0 );
