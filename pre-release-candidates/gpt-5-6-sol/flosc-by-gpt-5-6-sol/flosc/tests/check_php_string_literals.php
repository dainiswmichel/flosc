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

/*
 * A shipped profile changes only when the Captain approves the words.
 *
 * The pin caught the conversion from heredoc to implode, which had to be
 * byte-identical. It keeps catching an accidental edit. It is not a freeze:
 * when a revision is approved the hash below moves with it, in the same commit
 * as the text, so the two cannot drift apart quietly.
 *
 * Last moved: v38, for the revision itself — all four personalities rewritten
 * across the fourteen headings, cards and documents generated from one source
 * so they cannot disagree. Before that, v37, for the gain ladder. Every frequency word in all four
 * profiles was recomputed from its own card's gain — the old nine-rung ladder
 * rounded every gain from 65 to 95 down to "usually", which is why these
 * documents said it twenty-one times for eleven different values.
 */
echo "\nThe four shipped runtime profiles match their approved text\n";
$expected = array(
	'friendly'     => '65504c279fbfd6ff4fb58da922af230fa778c60ba022f7927997226ea2e91762',
	'tech'         => '7ebc8e7e369bb62e5a5cc710be3c12d2c3b7279a09b4a5340c6882e8916dba38',
	'bubblybetty'  => '45d038547aeb399f7dc23cf9a04fd6a8083946c1e4be577ee7df5b04354c18e3',
	'dadjokedan'   => 'b7293376b83588031e67e653758eef298921dbece39b67a5c97802493010d05b',
);
$defaults = flosc_personality_library_defaults();
foreach ( $expected as $id => $hash ) {
	$body = (string) ( $defaults[ $id ]['ai_base_prompt'] ?? '' );
	ok( $id, hash( 'sha256', $body ), $hash );
}

echo $fail ? "\n{$fail} FAILURES\n" : "\nPHP string literals: all checks passed\n";
exit( $fail ? 1 : 0 );
