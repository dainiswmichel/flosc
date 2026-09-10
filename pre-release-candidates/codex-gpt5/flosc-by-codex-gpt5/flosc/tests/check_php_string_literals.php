<?php
/**
 * Shipped PHP uses ordinary string literals and preserves personality bytes.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		$fail++;
	}
	printf(
		"%s %-64s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

$excluded = array( '.git', 'vendor', 'tests', 'pre-release-candidates' );
$found    = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( $current ) use ( $excluded ) {
			return ! $current->isDir() || ! in_array( $current->getFilename(), $excluded, true );
		}
	)
);

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	foreach ( token_get_all( (string) file_get_contents( $file->getPathname() ) ) as $token ) {
		if ( is_array( $token ) && T_START_HEREDOC === $token[0] ) {
			$found[] = substr( $file->getPathname(), strlen( $root ) + 1 ) . ':' . $token[2];
		}
	}
}

echo "Shipped PHP contains no heredoc or NOWDOC declarations\n";
ok( 'all shipped declarations use ordinary literals', $found, array() );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'FLOSC_PLUGIN_DIR' ) ) {
	define( 'FLOSC_PLUGIN_DIR', $root . '/' );
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}
require_once $root . '/includes/flosc-personality-library.php';

echo "\nThe four shipped runtime profiles retain their exact v25 bytes\n";
$expected = array(
	'friendly'     => '148857604f6cd44f945d58b7ab5fb4ecdf13d85ad0931ced3349a8b408c3c82e',
	'tech'         => 'b9f3c546d88896664980a233d88b7f1baee38d9725d6d58488a1fbb004aec21a',
	'bubblybetty'  => '875deb1c50b450f493bb7e65b4fac9defd7f0427b4428e9f8c53275fbe2bd8a6',
	'dadjokedan'   => 'cc79d1f8ff715e531a2ed56fdc21e441a7be5015760996aa81bc0b4b1899bf27',
);
$defaults = flosc_personality_library_defaults();
foreach ( $expected as $id => $hash ) {
	$body = (string) ( $defaults[ $id ]['ai_base_prompt'] ?? '' );
	ok( $id, hash( 'sha256', $body ), $hash );
}

echo $fail ? "\n{$fail} FAILURES\n" : "\nPHP string literals: all checks passed\n";
exit( $fail ? 1 : 0 );
