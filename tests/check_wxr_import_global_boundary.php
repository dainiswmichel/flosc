<?php
/**
 * Verify WXR import behavior without mutating importer bootstrap state.
 *
 * Source-only. tests/ is excluded from the distribution zip.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$flosc_root         = dirname( __DIR__ );
$flosc_test_uploads = sys_get_temp_dir() . '/flosc-wxr-global-boundary-' . getmypid();
$flosc_test_options = array();

if ( defined( 'WP_LOAD_' . 'IMPORTERS' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI regression output to standard error.
	fwrite( STDERR, "FAIL importer bootstrap flag was defined before the test\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Loads guarded plugin files in this isolated CLI test.
define( 'ABSPATH', $flosc_root . '/' );

function sanitize_file_name( $flosc_value ) {
	return basename( (string) $flosc_value );
}

function sanitize_key( $flosc_value ) {
	return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $flosc_value ) );
}

function trailingslashit( $flosc_value ) {
	return rtrim( (string) $flosc_value, '/\\' ) . '/';
}

function wp_normalize_path( $flosc_value ) {
	return str_replace( '\\', '/', (string) $flosc_value );
}

function wp_upload_dir() {
	global $flosc_test_uploads;
	return array(
		'basedir' => $flosc_test_uploads,
		'baseurl' => 'https://example.test/uploads',
		'error'   => false,
	);
}

function wp_mkdir_p( $flosc_path ) {
	return is_dir( $flosc_path ) || mkdir( $flosc_path, 0777, true );
}

function get_option( $flosc_key, $flosc_default = false ) {
	global $flosc_test_options;
	return array_key_exists( $flosc_key, $flosc_test_options ) ? $flosc_test_options[ $flosc_key ] : $flosc_default;
}

function update_option( $flosc_key, $flosc_value, $flosc_autoload = null ) {
	global $flosc_test_options;
	unset( $flosc_autoload );
	$flosc_test_options[ $flosc_key ] = $flosc_value;
	return true;
}

function current_time( $flosc_type ) {
	unset( $flosc_type );
	return 'test-time';
}

function __( $flosc_text, $flosc_domain = 'default' ) {
	unset( $flosc_domain );
	return $flosc_text;
}

function is_wp_error( $flosc_value ) {
	return $flosc_value instanceof WP_Error;
}

class WP_Error {
	public function __construct( $flosc_code = '', $flosc_message = '' ) {
		unset( $flosc_code, $flosc_message );
	}
}

class WP_Import {
	public static $last_path = '';
	public static $last_fetch_attachments = false;
	public $fetch_attachments = false;

	public function import( $flosc_path ) {
		self::$last_path              = $flosc_path;
		self::$last_fetch_attachments = $this->fetch_attachments;
		echo 'importer output';
	}
}

require_once $flosc_root . '/includes/flosc-output-buffer.php';
require_once $flosc_root . '/admin/ivr-upload-handler.php';

$flosc_flow     = 'boundary_ivr.md';
$flosc_pack_dir = flosc_portability_pack_dir( $flosc_flow );
$flosc_wxr_path = trailingslashit( $flosc_pack_dir ) . 'boundary.xml';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creates a temporary fixture for this CLI regression.
file_put_contents( $flosc_wxr_path, '<rss><channel></channel></rss>' );

$flosc_test_options['flosc_flow_pack_assets'] = array(
	$flosc_flow => array(
		'wxr'   => array(
			array(
				'filename' => 'boundary.xml',
				'path'     => $flosc_wxr_path,
				'status'   => 'staged',
			),
		),
		'media' => array(),
	),
);

$flosc_result = flosc_portability_run_wxr_import( $flosc_flow, 'boundary.xml' );
$flosc_saved  = $flosc_test_options['flosc_flow_pack_assets'][ $flosc_flow ]['wxr'][0] ?? array();
$flosc_failed = false;

if ( true !== $flosc_result ) {
	$flosc_failed = true;
}
if ( $flosc_wxr_path !== WP_Import::$last_path || true !== WP_Import::$last_fetch_attachments ) {
	$flosc_failed = true;
}
if ( 'imported' !== ( $flosc_saved['status'] ?? '' ) || 'test-time' !== ( $flosc_saved['imported_at'] ?? '' ) ) {
	$flosc_failed = true;
}
if ( defined( 'WP_LOAD_' . 'IMPORTERS' ) ) {
	$flosc_failed = true;
}

if ( file_exists( $flosc_wxr_path ) ) {
	unlink( $flosc_wxr_path );
}
if ( is_dir( $flosc_pack_dir ) ) {
	rmdir( $flosc_pack_dir );
}
$flosc_pack_root = trailingslashit( $flosc_test_uploads ) . 'flosc-packs';
if ( is_dir( $flosc_pack_root ) ) {
	rmdir( $flosc_pack_root );
}
if ( is_dir( $flosc_test_uploads ) ) {
	rmdir( $flosc_test_uploads );
}

if ( $flosc_failed ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI regression output to standard error.
	fwrite( STDERR, "FAIL WXR behavior changed or importer bootstrap state was mutated\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI regression output to standard output.
fwrite( STDOUT, "WXR import boundary passed: behavior preserved, global bootstrap state unchanged\n" );
exit( 0 );
