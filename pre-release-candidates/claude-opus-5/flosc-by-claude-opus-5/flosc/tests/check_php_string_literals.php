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
 * Last moved: v37, for the gain ladder. Every frequency word in all four
 * profiles was recomputed from its own card's gain — the old nine-rung ladder
 * rounded every gain from 65 to 95 down to "usually", which is why these
 * documents said it twenty-one times for eleven different values.
 */
echo "\nThe four shipped runtime profiles match their approved text\n";
$expected = array(
	'friendly'     => '7b53c542d49e3c393973f3e7b886fa84540cc0e551dd864bf09dadc785197f72',
	'tech'         => '3bdd964bce7c44f279fda1d5c113e8395941921e19a98c51ab4ad9d75f6ff570',
	'bubblybetty'  => '7264b32907c84a45b7fb43f60d5fe257cb0c8778d48cda203b985b41be8dcde1',
	'dadjokedan'   => '97cb6d86554693134a3a83628120873c496aa3d102578372d20a88173b857eed',
);
$defaults = flosc_personality_library_defaults();
foreach ( $expected as $id => $hash ) {
	$body = (string) ( $defaults[ $id ]['ai_base_prompt'] ?? '' );
	ok( $id, hash( 'sha256', $body ), $hash );
}

echo $fail ? "\n{$fail} FAILURES\n" : "\nPHP string literals: all checks passed\n";
exit( $fail ? 1 : 0 );
