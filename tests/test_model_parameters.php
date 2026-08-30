<?php
/**
 * Operator-written model parameters: parsed, typed, and never guessed at.
 */

define( 'ABSPATH', __DIR__ );

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function __( $t, $d = null ) { return $t; }

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

require_once __DIR__ . '/../includes/ai/flosc-model-parameters.php';

$fail = 0;
function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-54s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

echo "name: value lines\n";
ok( 'a float stays a float', flosc_parse_model_parameters( "top_p: 0.9" ), array( 'top_p' => 0.9 ) );
ok( 'an integer stays an integer', flosc_parse_model_parameters( "top_k: 40" ), array( 'top_k' => 40 ) );
ok( 'several at once', flosc_parse_model_parameters( "top_p: 0.9\ntop_k: 40" ), array( 'top_p' => 0.9, 'top_k' => 40 ) );
ok( 'true is boolean, not the word', flosc_parse_model_parameters( "stream_hint: true" ), array( 'stream_hint' => true ) );
ok( 'a string stays a string', flosc_parse_model_parameters( "mode: creative" ), array( 'mode' => 'creative' ) );
ok( 'quotes are stripped', flosc_parse_model_parameters( 'mode: "creative"' ), array( 'mode' => 'creative' ) );
ok( 'a nested object survives', flosc_parse_model_parameters( 'thinking: {"type":"adaptive"}' ), array( 'thinking' => array( 'type' => 'adaptive' ) ) );
ok( 'an array survives', flosc_parse_model_parameters( 'stop_sequences: ["END","STOP"]' ), array( 'stop_sequences' => array( 'END', 'STOP' ) ) );
ok( 'comments and blank lines are skipped', flosc_parse_model_parameters( "# tuning\n\ntop_p: 0.9\n// note" ), array( 'top_p' => 0.9 ) );
ok( 'a trailing comma is forgiven', flosc_parse_model_parameters( "top_p: 0.9," ), array( 'top_p' => 0.9 ) );
ok( 'nothing typed means nothing sent', flosc_parse_model_parameters( '   ' ), array() );

echo "JSON pasted from a provider's docs\n";
ok( 'an object parses', flosc_parse_model_parameters( '{"top_p":0.9,"top_k":40}' ), array( 'top_p' => 0.9, 'top_k' => 40 ) );
ok( 'nested shapes survive', flosc_parse_model_parameters( '{"thinking":{"type":"adaptive"}}' ), array( 'thinking' => array( 'type' => 'adaptive' ) ) );
ok( 'broken JSON is refused', is_wp_error( flosc_parse_model_parameters( '{"top_p":}' ) ), true );

echo "A parameter FLOSC has never heard of is still sent\n";
ok( 'invented today, carried anyway', flosc_parse_model_parameters( "reasoning_intensity_2027: 11" ), array( 'reasoning_intensity_2027' => 11 ) );
ok( '  and so is one from another provider', flosc_parse_model_parameters( "frequency_penalty: 0.5" ), array( 'frequency_penalty' => 0.5 ) );

echo "What is refused\n";
ok( 'a line that is not name: value', is_wp_error( flosc_parse_model_parameters( "just some words" ) ), true );
ok( '  and it says which line', strpos( flosc_parse_model_parameters( "just some words" )->get_error_message(), 'just some words' ) !== false, true );
ok( 'the conversation itself — messages', is_wp_error( flosc_parse_model_parameters( "messages: x" ) ), true );
ok( '  its Gemini name too', is_wp_error( flosc_parse_model_parameters( "contents: x" ) ), true );
ok( '  and streaming, which the reply parser cannot read', is_wp_error( flosc_parse_model_parameters( "stream: true" ) ), true );
ok( '  regardless of case', is_wp_error( flosc_parse_model_parameters( "MESSAGES: x" ) ), true );
ok( 'but model is the operator\'s to override', flosc_parse_model_parameters( "model: claude-opus-5" ), array( 'model' => 'claude-opus-5' ) );
ok( '  and so is max_tokens', flosc_parse_model_parameters( "max_tokens: 999" ), array( 'max_tokens' => 999 ) );
ok( 'a name that is not a name', is_wp_error( flosc_parse_model_parameters( '{"has spaces":1}' ) ), true );
ok( 'something absurdly long', is_wp_error( flosc_parse_model_parameters( str_repeat( 'a: 1
', 3000 ) ) ), true );

echo "temperature is the operator's to send, if their model takes it\n";
ok( 'not blocked — Sonnet 4.5 accepts it', flosc_parse_model_parameters( "temperature: 0.3" ), array( 'temperature' => 0.3 ) );

echo "YAML-style and true JSON are the same request\n";
$yaml = "temperature: 0.7\ntop_p: 0.9\nseed: 42\nstop: [\"END\"]";
$json = '{"temperature":0.7,"top_p":0.9,"seed":42,"stop":["END"]}';
ok( 'the two forms parse identically', flosc_parse_model_parameters( $yaml ), flosc_parse_model_parameters( $json ) );
ok( '  and both keep 0.7 a number', flosc_parse_model_parameters( $yaml )['temperature'], 0.7 );
ok( '  and both keep the list a list', flosc_parse_model_parameters( $json )['stop'], array( 'END' ) );
ok( 'JSON with quoted keys works', flosc_parse_model_parameters( '{"top_p": 0.9}' ), array( 'top_p' => 0.9 ) );
ok( 'YAML without quoted keys works', flosc_parse_model_parameters( 'top_p: 0.9' ), array( 'top_p' => 0.9 ) );
ok( 'a copied JSON block with newlines works', flosc_parse_model_parameters( "{\n  \"top_p\": 0.9,\n  \"top_k\": 40\n}" ), array( 'top_p' => 0.9, 'top_k' => 40 ) );

echo "The payload rules; the fields above only write into it\n";
ok( 'temperature can be set here', flosc_parse_model_parameters( 'temperature: 0.9' ), array( 'temperature' => 0.9 ) );
ok( '  and so can max_tokens', flosc_parse_model_parameters( 'max_tokens: 2000' ), array( 'max_tokens' => 2000 ) );
ok( 'messages is refused — FLOSC builds the conversation', is_wp_error( flosc_parse_model_parameters( 'messages: x' ) ), true );
ok( '  and stream, because the reply could not be read', is_wp_error( flosc_parse_model_parameters( 'stream: true' ) ), true );
ok( '  the refusal explains rather than scolds', strpos( flosc_parse_model_parameters( 'messages: x' )->get_error_message(), 'Every other parameter is yours' ) !== false, true );

echo "After a save, the fields show what the text said\n";
function wp_json_encode( $v ) { return json_encode( $v ); }
$settings = array( 'ai_temperature' => '0.3', 'ai_max_tokens' => '500', 'ai_anthropic_params' => "temperature: 0.9\nmax_tokens: 2000\ntop_p: 0.8" );
$after = flosc_reconcile_model_parameters( $settings, 'anthropic' );
ok( 'the Temperature field takes the written value', $after['ai_temperature'], '0.9' );
ok( '  and Max Tokens too', $after['ai_max_tokens'], '2000' );
ok( '  so neither is left duplicated in the text', $after['ai_anthropic_params'], "top_p: 0.8" );
ok( '  while what no field represents stays put', flosc_parse_model_parameters( $after['ai_anthropic_params'] ), array( 'top_p' => 0.8 ) );

$untouched = flosc_reconcile_model_parameters( array( 'ai_temperature' => '0.3', 'ai_anthropic_params' => 'top_k: 40' ), 'anthropic' );
ok( 'a field nobody named is left alone', $untouched['ai_temperature'], '0.3' );

$broken = flosc_reconcile_model_parameters( array( 'ai_temperature' => '0.3', 'ai_anthropic_params' => 'this is not a parameter' ), 'anthropic' );
ok( 'text that will not parse is kept exactly as typed', $broken['ai_anthropic_params'], 'this is not a parameter' );
ok( '  and changes no field on the way past', $broken['ai_temperature'], '0.3' );

echo "The reference explains, and never gates\n";
$ref = flosc_model_parameter_reference();
ok( 'every entry says what it does', count( array_filter( $ref, static function ( $r ) { return '' !== trim( $r['what'] ); } ) ), count( $ref ) );
ok( '  its range', count( array_filter( $ref, static function ( $r ) { return '' !== trim( $r['range'] ); } ) ), count( $ref ) );
ok( '  and which providers take it', count( array_filter( $ref, static function ( $r ) { return '' !== trim( $r['providers'] ); } ) ), count( $ref ) );
ok( 'the ones tested here are marked measured', $ref['temperature']['measured'], true );
ok( '  including the correction to the published lists', $ref['seed']['measured'], true );
ok( '  and seed is named as not an Anthropic parameter', strpos( $ref['seed']['providers'], 'Not an Anthropic parameter' ) !== false, true );
ok( 'a documented name is still only documentation', flosc_parse_model_parameters( 'seed: 42' ), array( 'seed' => 42 ) );
ok( 'an undocumented name is not refused for being absent', flosc_parse_model_parameters( 'not_in_the_reference: 1' ), array( 'not_in_the_reference' => 1 ) );
ok( '  so the reference can never block a new parameter', array_key_exists( 'not_in_the_reference', $ref ), false );

echo $fail ? "\n$fail FAILURES\n" : "\nModel parameters: all checks passed\n";
exit( $fail ? 1 : 0 );
