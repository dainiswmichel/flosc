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
 * Last moved: v35, for the heading rename. Philosophy and Values became
 * Mission, Philosophy and Values in three profiles, and Dad Joke Dan's three
 * jokes followed their cards from Decisions to Tone at 45, 46 and 47.
 */
echo "\nThe four shipped runtime profiles match their approved text\n";
$expected = array(
	'friendly'     => 'c61e0461c835277786ee615e7e2b08b34f6b1c664bb82b606e941ee95f6bb11f',
	'tech'         => 'b9f3c546d88896664980a233d88b7f1baee38d9725d6d58488a1fbb004aec21a',
	'bubblybetty'  => 'b50dae7dfa9d7fcdb36bc295d66db28ecdd6c97dd2f16d9d89d6af2f5764f6b5',
	'dadjokedan'   => '53bdad32ca0ceec4251c2b277af8c2309fcf3981e4588d1b1d653bac56fb45bc',
);
$defaults = flosc_personality_library_defaults();
foreach ( $expected as $id => $hash ) {
	$body = (string) ( $defaults[ $id ]['ai_base_prompt'] ?? '' );
	ok( $id, hash( 'sha256', $body ), $hash );
}

echo $fail ? "\n{$fail} FAILURES\n" : "\nPHP string literals: all checks passed\n";
exit( $fail ? 1 : 0 );
