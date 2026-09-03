<?php
/**
 * Isolated contract checks for the grok-4-6 personality pipeline.
 * CLI only — stubs WordPress.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
define( 'FLOSC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$OPTIONS = array();
$fail    = 0;

function ok( $label, $got, $want = true ) {
	global $fail;
	$pass = ( $got === $want );
	echo ( $pass ? 'OK   ' : 'FAIL ' ) . $label . ( $pass ? '' : ' got=' . var_export( $got, true ) . ' want=' . var_export( $want, true ) ) . PHP_EOL;
	if ( ! $pass ) {
		$fail++;
	}
}

function get_option( $k, $d = false ) {
	global $OPTIONS;
	return array_key_exists( $k, $OPTIONS ) ? $OPTIONS[ $k ] : $d;
}
function add_option( $k, $v, $deprecated = '', $autoload = true ) {
	global $OPTIONS;
	if ( ! array_key_exists( $k, $OPTIONS ) ) {
		$OPTIONS[ $k ] = $v;
	}
	return true;
}
function update_option( $k, $v, $a = null ) {
	global $OPTIONS;
	$OPTIONS[ $k ] = $v;
	return true;
}
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
function sanitize_text_field( $t ) {
	return trim( strip_tags( (string) $t ) );
}
function sanitize_textarea_field( $t ) {
	return trim( (string) $t );
}
function sanitize_file_name( $t ) {
	return preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) $t );
}
function wp_json_encode( $v ) {
	return json_encode( $v );
}
function wp_check_invalid_utf8( $t ) {
	return $t;
}
function __($s, $d = null) {
	return $s;
}
function add_action( $h, $c, $p = 10, $a = 1 ) {
	return true;
}
function add_filter( $h, $c, $p = 10, $a = 1 ) {
	return true;
}
function admin_url( $p = '' ) {
	return 'http://example.test/wp-admin/' . $p;
}
function wp_create_nonce( $a ) {
	return 'nonce';
}
function current_user_can( $c ) {
	return true;
}

require FLOSC_PLUGIN_DIR . 'includes/flosc-personality-library.php';

$defaults = flosc_personality_library_defaults();
ok( 'betty default exists', isset( $defaults['bubblybetty'] ) );
ok( 'dan default exists', isset( $defaults['dadjokedan'] ) );
$betty = $defaults['bubblybetty']['ai_base_prompt'];
$dan   = $defaults['dadjokedan']['ai_base_prompt'];
ok( 'betty has no shared Always blob', strpos( $betty, '## Always' ) === false );
ok( 'dan has no shared Always blob', strpos( $dan, '## Always' ) === false );
ok( 'betty under 4000 bytes', strlen( $betty ) < 4000 );
ok( 'dan under 4000 bytes', strlen( $dan ) < 4000 );
ok( 'betty distinct from dan', $betty !== $dan );

$authored = "# Personality profile: NightWarden\nYou are NightWarden, a laconic harbour-watch.\nSpeak as this person.\n\n## Voice\nShort sentences. No emoji. Never a dad joke.";
$compiled = flosc_personality_compile(
	array(
		'ai_base_prompt'      => $authored,
		'ai_personality_name' => 'ShouldNotReplace',
	)
);
ok( 'authored profile kept verbatim', $compiled['ai_base_prompt'], $authored );
ok( 'authored hash is sha256 of profile', $compiled['profile_hash'], hash( 'sha256', $authored ) );

$synth = flosc_personality_compile(
	array(
		'ai_personality_name'  => 'Forge',
		'ai_personality_role'  => 'blacksmith',
		'ai_personality_traits'=> 'blunt, warm',
		'ai_mission'           => 'Make useful things.',
	)
);
ok( 'empty profile synthesizes name', strpos( $synth['ai_base_prompt'], 'Forge' ) !== false );
ok( 'synth is deterministic', flosc_personality_compile( array( 'ai_personality_name' => 'Forge', 'ai_personality_role' => 'blacksmith', 'ai_personality_traits' => 'blunt, warm', 'ai_mission' => 'Make useful things.' ) )['profile_hash'], $synth['profile_hash'] );
ok( 'synth differs from authored', $synth['profile_hash'] !== $compiled['profile_hash'] );

$chatpack = file_get_contents( FLOSC_PLUGIN_DIR . 'includes/class-flosc-chatpack.php' );
ok( 'follow-up no longer says same person as opening', strpos( $chatpack, 'same person as the opening turn' ) === false );
ok( 'follow-up sends full identity', strpos( $chatpack, 'build_identity_section($followup_flow, false)' ) !== false );
ok( 'shared policy layer exists', strpos( $chatpack, 'build_shared_policy_section' ) !== false );
ok( 'shared policy is not inside betty', strpos( $betty, 'Discover before you offer' ) === false );

$turn = file_get_contents( FLOSC_PLUGIN_DIR . 'includes/chat-turn/trait-flosc-chat-turn.php' );
ok( 'dispatch uses structured result', strpos( $turn, 'get_response_result' ) !== false );
ok( 'RAG failure falls through', strpos( $turn, 'A RAG failure must fall through' ) !== false );

$allflows = file_get_contents( FLOSC_PLUGIN_DIR . 'admin/ai-all-flows.php' );
ok( 'every row has a Design link', strpos( $allflows, "esc_html__( 'Design', 'flosc' )" ) !== false );

$url_src = file_get_contents( FLOSC_PLUGIN_DIR . 'includes/flosc-personality-library.php' );
ok( 'designer URL carries persona id', strpos( $url_src, "\$args['persona'] = \$persona_id" ) !== false );
ok( 'request context honors ?persona=', strpos( $url_src, "INPUT_GET, 'persona'" ) !== false );

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit( $fail === 0 ? 0 : 1 );
