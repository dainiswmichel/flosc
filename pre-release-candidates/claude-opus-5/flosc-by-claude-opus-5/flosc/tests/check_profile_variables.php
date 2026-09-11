<?php
/**
 * Verify personality variables against the shape of FLOSC's real turn context.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

function flosc_profile_vars_check( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		++$fail;
	}
	printf(
		"%s %-64s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
$GLOBALS['flosc_profile_var_calls'] = array();
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata() {
		$GLOBALS['flosc_profile_var_calls'][] = 'get_userdata';
		return false;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $key ) {
		$GLOBALS['flosc_profile_var_calls'][] = 'get_bloginfo:' . $key;
		$values = array(
			'name'        => 'Test Site',
			'url'         => 'https://example.test',
			'description' => 'Test description',
		);
		return $values[ $key ] ?? '';
	}
}
if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string() {
		return 'Europe/Riga';
	}
}
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'en_US';
	}
}
if ( ! function_exists( 'flosc_get_setting' ) ) {
	function flosc_get_setting( $key, $default = '', $flow_id = null ) {
		$GLOBALS['flosc_profile_var_calls'][] = 'setting:' . $key . ':' . (string) $flow_id;
		$values = array(
			'name'                   => 'LeSAEp',
			'title'                  => 'Learn Standard American English Pronunciation',
			'tagline'                => 'Clear speech, one sound at a time',
			'personality_library_id' => '',
			'ai_base_prompt'         => 'You are {personality_name} on {current_url}.',
			'ai_topic_scope'         => 'American English pronunciation',
			'ai_personality_role'    => 'Learning guide',
		);
		return $values[ $key ] ?? $default;
	}
}
if ( ! function_exists( 'flosc_personality_library_get' ) ) {
	function flosc_personality_library_get() {
		return null;
	}
}
if ( ! function_exists( 'flosc_personality_library_id_for_flow' ) ) {
	function flosc_personality_library_id_for_flow() {
		return '';
	}
}

require_once $root . '/includes/flosc-personality-library.php';

echo "Pure turn-context expansion\n";
$turn = array(
	'logged_in'          => true,
	'user_id'            => 20292,
	'user_display_name'  => 'Tim Koninckx',
	'user_first_name'    => 'Tim',
	'user_login'         => 'tim.koninckx@me.com',
	'user_email'         => 'tim.koninckx@me.com',
	'access_level'       => 'member',
	'browsing_page_url'  => 'https://example.test/w-sound/',
	'browsing_page_title'=> 'The W sound',
	'quiz_score'         => 72,
	'bridge_correct_count' => 18,
	'total_possible'     => 25,
	'incorrectItems'     => array( 'w', 'r' ),
	'weakest_category'   => 'W sound',
	'message_count'      => 3,
);
$GLOBALS['flosc_profile_var_calls'] = array();
$context = flosc_personality_turn_variable_context( $turn );
flosc_profile_vars_check( 'current URL uses browsing_page_url', $context['current_url'], 'https://example.test/w-sound/' );
flosc_profile_vars_check( 'current page title uses browsing_page_title', $context['current_page_title'], 'The W sound' );
flosc_profile_vars_check( 'display name comes from the existing WP_User data', $context['name'], 'Tim Koninckx' );
flosc_profile_vars_check( 'first name comes from the existing WP_User data', $context['first_name'], 'Tim' );
flosc_profile_vars_check( 'WordPress login comes from the existing WP_User data', $context['user_name'], 'tim.koninckx@me.com' );
flosc_profile_vars_check( 'quiz_score is a score alias', $context['score'], '72' );
flosc_profile_vars_check( 'legacy percent suffix is normalized', flosc_personality_turn_variable_context( array( 'quiz_score' => '72%' ) )['score'], '72' );
flosc_profile_vars_check( 'bridge correct count is an answer-count alias', $context['total_correct'], '18' );
flosc_profile_vars_check( 'nested turn values flatten without warnings', flosc_personality_variable_clean( array( array( 'w', 'r' ), 'th' ) ), 'w, r, th' );
flosc_profile_vars_check( 'turn mapping performs no lookup', $GLOBALS['flosc_profile_var_calls'], array() );

echo "Expansion behavior\n";
$plain = "# Identity\nYou are the host.\n";
flosc_profile_vars_check( 'a no-brace profile returns byte-for-byte unchanged', flosc_personality_expand_variables( $plain, array() ), $plain );
flosc_profile_vars_check( 'recognized tokens expand in one pass', flosc_personality_expand_variables( '{first_name}: {score}% at {current_url}', $context ), 'Tim: 72% at https://example.test/w-sound/' );
/* A recognized variable with no value leaves nothing behind. The literal
   reaching a model is the failure mode that once put "I'm {personality_name}"
   in front of a visitor, and a stand-in phrase is the model reading "not
   available" aloud as if it were the answer. */
flosc_profile_vars_check( 'an unavailable recognized value disappears', flosc_personality_expand_variables( 'Possible: {total_possible}; missing: {total_correct}.', flosc_personality_turn_variable_context( array() ) ), 'Possible: ; missing: .' );
flosc_profile_vars_check( '  and no stand-in phrase survives anywhere', (bool) preg_match( '/not available|not provided|not signed in/', (string) file_get_contents( $root . '/includes/flosc-personality-library.php' ) ), false );

echo "\nA quiz variable can name its quiz\n";
/* {score:ipa_basics} asks about one quiz; {score} means the most recent. The
   qualifier has to survive the scanner, or it reaches the model as literal
   text. */
$quiz_tokens = flosc_personality_variable_tokens( 'A {score:ipa_basics} and a {score} and a {missed_items:ipa_basics}.' );
flosc_profile_vars_check( 'the scanner keeps the qualifier', $quiz_tokens, array( 'score:ipa_basics', 'score', 'missed_items:ipa_basics' ) );
flosc_profile_vars_check( 'the same base token can name two quizzes',
	flosc_personality_variable_tokens( '{score:one} then {score:two}' ), array( 'score:one', 'score:two' ) );
flosc_profile_vars_check( 'a qualifier on an unknown token is still rejected',
	flosc_personality_variable_tokens( '{not_a_token:one}' ), array() );
/* {score:} is malformed, not a variable. It falls under the same rule as any
   other unrecognized brace: left exactly as the floscAdmin typed it. */
flosc_profile_vars_check( 'a malformed qualifier is left as written',
	flosc_personality_variable_tokens( '{score:}' ), array() );
flosc_profile_vars_check( '  so it survives expansion untouched',
	flosc_personality_expand_variables( 'Score: {score:}.', array() ), 'Score: {score:}.' );
/* No WordPress here, so the bridge manager is absent and a named quiz has
   nowhere to read from. It must come back empty rather than leaving the
   qualified token in the prompt. */
flosc_profile_vars_check( 'with no bridge data the qualified token still leaves nothing',
	flosc_personality_expand_variables( 'Score: {score:ipa_basics}.', array( 'user_id' => 7 ) ), 'Score: .' );
flosc_profile_vars_check( '  and the turn value still wins for the plain token',
	flosc_personality_expand_variables( 'Score: {score}.', array( 'score' => '82' ) ), 'Score: 82.' );
flosc_profile_vars_check( 'an unknown brace remains admin-authored text', flosc_personality_expand_variables( 'Keep {the thing}.', $context ), 'Keep {the thing}.' );
flosc_profile_vars_check( 'a substituted token-looking value is not expanded again', flosc_personality_expand_variables( '{name} / {site_url}', array( 'name' => '{site_url}', 'site_url' => 'https://example.test' ) ), 'site_url / https://example.test' );
$GLOBALS['flosc_profile_var_calls'] = array();
$needed = flosc_personality_variable_tokens( 'Use {site_name}; keep {ordinary prose}.' );
flosc_personality_flow_variable_context( 'lesaep_com_ivr', array(), $needed );
flosc_profile_vars_check( 'only the present recognized flow variable is resolved', $GLOBALS['flosc_profile_var_calls'], array( 'get_bloginfo:name' ) );
$GLOBALS['flosc_profile_var_calls'] = array();
flosc_profile_vars_check( 'unrecognized braces produce no variable work', flosc_personality_variable_tokens( 'Keep {ordinary prose}.' ), array() );
flosc_profile_vars_check( 'unrecognized braces cause no lookup', $GLOBALS['flosc_profile_var_calls'], array() );

echo "Stored-template boundary\n";
$stored = flosc_personality_compiled_profile( 'lesaep_com_ivr' );
flosc_profile_vars_check( 'compiled profile preserves stored variables', $stored, 'You are {personality_name} on {current_url}.' );
$library_source = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
$chatpack_source = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$dispatch_source = (string) file_get_contents( $root . '/includes/class-ai-chat-dispatch.php' );
$trait_source = (string) file_get_contents( $root . '/includes/chat-turn/trait-flosc-chat-turn.php' );
flosc_profile_vars_check( 'generic compiled-profile function does not expand', strpos( substr( $library_source, strpos( $library_source, 'function flosc_personality_compiled_profile' ), 900 ), 'flosc_personality_expand_variables' ), false );
flosc_profile_vars_check( 'Chatpack expands the request copy', strpos( $chatpack_source, 'flosc_personality_expand_variables( $compiled_profile' ) !== false, true );
flosc_profile_vars_check( 'legacy dispatch expands the request copy', strpos( $dispatch_source, 'flosc_personality_expand_variables( $compiled_profile' ) !== false, true );
flosc_profile_vars_check( 'the real turn builder exposes the already-loaded first name', strpos( $trait_source, "\$eval_context['user_first_name']" ) !== false, true );

echo "Designer contract\n";
$catalog = flosc_personality_variable_catalog();
$expected = array(
	'flow_name', 'site_name', 'site_url', 'site_description', 'public_title', 'title', 'tagline',
	'topic_scope', 'personality_name', 'personality_role', 'product_name', 'app_name', 'timezone', 'locale',
	'current_url', 'current_page_title', 'name', 'first_name', 'user_name', 'user_email', 'user_id',
	'logged_in', 'member_level', 'access_level', 'score', 'total_correct', 'total_possible',
	'correct_items', 'missed_items', 'weak_area', 'quiz_id', 'quiz_title', 'message_count',
);
flosc_profile_vars_check( 'catalog contains only the verified contract', array_keys( $catalog ), $expected );
$boot = flosc_personality_variable_boot( 'lesaep_com_ivr.md' );
flosc_profile_vars_check( 'designer receives every catalog variable', count( $boot ), count( $expected ) );
flosc_profile_vars_check( 'flow filename is normalized before lookup', in_array( 'setting:name:lesaep_com_ivr', $GLOBALS['flosc_profile_var_calls'], true ), true );
$js = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder.js' );
$markup = (string) file_get_contents( $root . '/assets/personality-builder/flosc-personality-builder-markup.php' );
flosc_profile_vars_check( 'designer renders the variable panel', strpos( $js, 'function renderVariables()' ) !== false, true );
flosc_profile_vars_check( 'new panel writes through textContent', strpos( $js, 'mount.textContent = ""' ) !== false, true );
flosc_profile_vars_check( 'designer markup contains the mount', strpos( $markup, 'id="varMount"' ) !== false, true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nPersonality variable contract passed\n";
exit( $fail ? 1 : 0 );
