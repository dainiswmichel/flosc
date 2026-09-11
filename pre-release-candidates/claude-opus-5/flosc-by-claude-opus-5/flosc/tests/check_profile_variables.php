<?php
/**
 * A variable a floscAdmin can type must actually resolve, and a variable the
 * builder advertises must actually exist.
 *
 * The IVR greeting system drifted exactly this way and it reached a live
 * visitor: admin/ivr-messages.php advertises {id}, {max}, {try} and {url},
 * which assets/js/flosc-app.js never replaces, and flosc-app.js replaces eight
 * tokens the editor never mentions. The failure mode is a prompt carrying the
 * literal text "{personality_name}" to a paying model.
 *
 * This gate holds three things together: the catalog, what the builder is
 * handed, and what the expander will actually substitute.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-62s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

/* The library loads under a stubbed WordPress: the functions the resolver
   reaches for are the ones a test has to stand in for, and standing them in
   is also the list of what it may legitimately call. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $root . '/' ); }
$GLOBALS['flosc_test_calls'] = array();
function flosc_test_note( $what ) { $GLOBALS['flosc_test_calls'][] = $what; }

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $key = '' ) {
		flosc_test_note( 'get_bloginfo:' . $key );
		$map = array( 'name' => 'Test Site', 'url' => 'https://example.test', 'description' => 'A tagline' );
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}
}
if ( ! function_exists( 'wp_timezone_string' ) ) { function wp_timezone_string() { return 'UTC'; } }
if ( ! function_exists( 'get_locale' ) ) { function get_locale() { return 'en_US'; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
if ( ! function_exists( 'flosc_get_setting' ) ) {
	function flosc_get_setting( $key, $default = '', $flow_id = null ) {
		flosc_test_note( 'flosc_get_setting:' . $key );
		return $default;
	}
}
if ( ! function_exists( 'flosc_flow_name' ) ) {
	function flosc_flow_name( $flow_id = null ) { return 'Vegan Latvian Kitchen'; }
}
if ( ! function_exists( 'flosc_flow_public_title' ) ) {
	function flosc_flow_public_title( $flow_id = null ) { return 'Cook Fourteen Dishes'; }
}
if ( ! function_exists( 'flosc_flow_public_tagline' ) ) {
	function flosc_flow_public_tagline( $flow_id = null ) { return 'Plant-based, start to finish'; }
}

/* Only the variable block is needed, and loading the whole library file would
   drag in the admin surface. Cut the functions out by name and evaluate them. */
$src = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
$wanted = array(
	'flosc_personality_variable_catalog',
	'flosc_personality_variable_user',
	'flosc_personality_variable_context',
	'flosc_personality_variable_value',
	'flosc_personality_variable_field',
	'flosc_personality_variable_clean',
	'flosc_personality_expand_variables',
	'flosc_personality_variable_boot',
);
$code = '';
foreach ( $wanted as $name ) {
	$start = strpos( $src, "\tfunction " . $name . '(' );
	if ( false === $start ) {
		echo "FATAL: $name not found in the library\n";
		exit( 1 );
	}
	/* Brace counting would trip over the braces these functions carry inside
	   string literals and regexes — which is the whole subject here. Every
	   function sits one tab in, so its body ends at the first "\n\t}". */
	$end = strpos( $src, "\n\t}", $start );
	if ( false === $end ) {
		echo "FATAL: could not find the end of $name\n";
		exit( 1 );
	}
	$code .= substr( $src, $start, $end - $start + 3 ) . "\n";
}
eval( $code );

echo "The catalog is the one list\n";
$catalog = flosc_personality_variable_catalog();
ok( 'it has entries', count( $catalog ) > 30, true );
$bad = array();
foreach ( $catalog as $token => $meta ) {
	if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $token ) ) { $bad[] = $token; }
	if ( ! in_array( $meta['scope'], array( 'flow', 'visitor' ), true ) ) { $bad[] = $token . ' (scope)'; }
	if ( empty( $meta['label'] ) ) { $bad[] = $token . ' (label)'; }
}
ok( 'every token is snake_case with a scope and a label', $bad, array() );

echo "\nThe builder is handed exactly what the catalog holds\n";
$boot = flosc_personality_variable_boot( 'vlkit_ivr.md' );
ok( 'one row per catalog entry', count( $boot ), count( $catalog ) );
$shape = array();
foreach ( $boot as $row ) {
	if ( ! preg_match( '/^\{[a-z][a-z0-9_]*\}$/', $row['token'] ) ) { $shape[] = $row['token']; }
	if ( 'visitor' === $row['scope'] && '' !== $row['value'] ) { $shape[] = $row['token'] . ' (invented a visitor value)'; }
}
ok( 'every row is a braced token, and no visitor value is invented', $shape, array() );
$flow_named = '';
foreach ( $boot as $row ) { if ( '{flow_name}' === $row['token'] ) { $flow_named = $row['value']; } }
ok( 'a flow token shows its real value', $flow_named, 'Vegan Latvian Kitchen' );

echo "\nA document with no brace is returned untouched, and costs nothing\n";
$GLOBALS['flosc_test_calls'] = array();
$plain = "# 6 Identity and Role\nYou are the host.\n";
ok( 'returned unchanged', flosc_personality_expand_variables( $plain, 'vlkit_ivr.md' ), $plain );
ok( '  and nothing was looked up', $GLOBALS['flosc_test_calls'], array() );

echo "\nOnly the tokens present are resolved\n";
$GLOBALS['flosc_test_calls'] = array();
$one = "You are the host of {site_name}.";
ok( 'the token is replaced', flosc_personality_expand_variables( $one, 'vlkit_ivr.md' ), 'You are the host of Test Site.' );
ok( '  and only that one was resolved', $GLOBALS['flosc_test_calls'], array( 'get_bloginfo:name' ) );

echo "\nBraces that are not FLOSC variables are the floscAdmin's own text\n";
$prose = 'Never say {the thing} or {NotAToken} or {}.';
ok( 'left exactly as written', flosc_personality_expand_variables( $prose, 'vlkit_ivr.md' ), $prose );
$unknown = 'A {definitely_not_a_flosc_variable} stays put.';
ok( 'an unrecognised snake_case token stays too', flosc_personality_expand_variables( $unknown, 'vlkit_ivr.md' ), $unknown );

echo "\nA recognised token with no value substitutes empty, never the literal\n";
$out = flosc_personality_expand_variables( 'Score: {score}.', 'vlkit_ivr.md' );
ok( 'the token is gone', strpos( $out, '{score}' ), false );
ok( '  and what is left is the sentence', $out, 'Score: .' );

echo "\nPer-turn context supplies the visitor, and nothing else does\n";
$turn = array( 'wp_user_id' => 7, 'logged_in' => true, 'access_level' => 'member', 'score' => 82, 'current_url' => 'https://other.test/lesson-3' );
$ctx  = flosc_personality_variable_context( $turn );
ok( 'user_id came through', isset( $ctx['user_id'] ) ? (int) $ctx['user_id'] : 0, 7 );
ok( 'logged_in became a word', $ctx['logged_in'] ?? '', 'yes' );
ok( 'current_url came through', $ctx['current_url'] ?? '', 'https://other.test/lesson-3' );
ok( 'a key the turn did not carry is absent', isset( $ctx['streak_days'] ), false );
ok( 'the score expands', flosc_personality_expand_variables( 'Score: {score}.', 'x', $ctx ), 'Score: 82.' );

echo "\nOne visitor's values cannot reach another's document\n";
$a = flosc_personality_expand_variables( 'Score: {score}.', 'x', flosc_personality_variable_context( array( 'score' => 11 ) ) );
$b = flosc_personality_expand_variables( 'Score: {score}.', 'x', flosc_personality_variable_context( array( 'score' => 99 ) ) );
ok( 'two turns, two answers', array( $a, $b ), array( 'Score: 11.', 'Score: 99.' ) );
ok( 'and a third with no context is empty', flosc_personality_expand_variables( 'Score: {score}.', 'x' ), 'Score: .' );

echo "\nA substituted value cannot smuggle anything into the prompt\n";
ok( 'braces are stripped from the value', flosc_personality_variable_clean( 'Bob {site_url} Jones' ), 'Bob site_url Jones' );
ok( 'control characters are stripped', flosc_personality_variable_clean( "Bob\x07\x00Jones" ), 'BobJones' );
ok( 'length is capped', strlen( flosc_personality_variable_clean( str_repeat( 'x', 900 ) ) ), 500 );
ok( 'a boolean reads as a word', flosc_personality_variable_clean( true ), 'yes' );
ok( 'an array joins', flosc_personality_variable_clean( array( 'a', 'b' ) ), 'a, b' );
$nested = flosc_personality_expand_variables( 'Hello {name}.', 'x', flosc_personality_variable_context( array() ) + array( 'name' => '{site_url}' ) );
ok( 'a value that looks like a token is not re-expanded', $nested, 'Hello site_url.' );

echo "\nThe stored document keeps its tokens\n";
$stored = 'You are the host of {site_name}.';
$copy   = flosc_personality_expand_variables( $stored, 'vlkit_ivr.md' );
ok( 'the input string is untouched', $stored, 'You are the host of {site_name}.' );
ok( '  and the copy differs', $copy !== $stored, true );

echo "\nThe expansion is wired into every path that speaks\n";
$lib      = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
$chatpack = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$dispatch = (string) file_get_contents( $root . '/includes/class-ai-chat-dispatch.php' );
$builder  = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder.js' );
$markup   = (string) file_get_contents( $root . '/assets/personality-builder/flosc-personality-builder-markup.php' );
ok( 'compiled_profile expands before it returns',
	strpos( $lib, 'flosc_personality_expand_variables( $profile' ) !== false, true );
ok( 'the chatpack hands it the turn',
	strpos( $chatpack, 'flosc_personality_variable_context( $eval_context )' ) !== false, true );
ok( 'the dispatch hands it the turn',
	strpos( $dispatch, 'flosc_personality_variable_context( $context )' ) !== false, true );
ok( 'the builder is booted the catalog',
	strpos( $lib, "'variables'         => flosc_personality_variable_boot" ) !== false, true );
ok( 'the builder draws the panel',
	strpos( $builder, 'renderVariables()' ) !== false, true );
ok( '  and the page has somewhere to draw it',
	strpos( $markup, 'id="varMount"' ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nEvery variable a floscAdmin can type resolves, and nothing else moves\n";
exit( $fail ? 1 : 0 );
