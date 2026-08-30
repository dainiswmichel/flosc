<?php
/**
 * Saving an AI provider API key: fresh, overwritten, and several at once.
 *
 * The rule under test is the one an operator relies on without thinking:
 * saving one key never costs another, and saving over a key replaces it.
 */

define( 'ABSPATH', __DIR__ );
define( 'FLOSC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$OPTIONS = array();

function get_option( $name, $default = false ) { global $OPTIONS; return array_key_exists( $name, $OPTIONS ) ? $OPTIONS[ $name ] : $default; }
function update_option( $name, $value, $autoload = null ) { global $OPTIONS; $OPTIONS[ $name ] = $value; return true; }
function delete_option( $name ) { global $OPTIONS; unset( $OPTIONS[ $name ] ); return true; }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function __( $t, $d = null ) { return $t; }
function maybe_unserialize( $v ) { return $v; }
function flosc_available_providers_flow_key_map() {
	return array( 'anthropic' => 'anthropic_api_key', 'openai' => 'openai_api_key', 'xai' => 'xai_api_key', 'gemini' => 'gemini_api_key', 'assemblyai' => 'assemblyai_api_key' );
}

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

require_once __DIR__ . '/../includes/ai/flosc-provider-keys.php';

$fail = 0;
function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-52s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

$ivr    = 'wcmj_ivr.md';
$option = 'flosc_flow_wcmj_ivr';

// A flow that already carries the operator's other settings.
update_option( $option, array(
	'name'                  => 'WordPress Content Membership Journey',
	'ivr_file'              => 'wcmj_ivr.md',
	'ai_provider'           => 'anthropic',
	'ai_temperature'        => '0.3',
	'personality_library_id'=> 'bubblybetty',
), false );

echo "A fresh key\n";
$r = flosc_store_provider_api_key( $ivr, 'anthropic', 'sk-ant-fresh-0001PQAA' );
ok( 'stores without error', is_wp_error( $r ), false );
ok( '  into the row the settings page reads', $r['option'], $option );
ok( '  under the provider\'s own setting name', $r['setting'], 'anthropic_api_key' );
ok( '  and reports the last four', $r['suffix'], 'PQAA' );
ok( '  the key is actually there', get_option( $option )['anthropic_api_key'], 'sk-ant-fresh-0001PQAA' );
ok( '  nothing else on the flow moved', get_option( $option )['personality_library_id'], 'bubblybetty' );
ok( '  including the tuning', get_option( $option )['ai_temperature'], '0.3' );

echo "Overwriting that key\n";
flosc_store_provider_api_key( $ivr, 'anthropic', 'sk-ant-second-0002WXYZ' );
ok( 'the new key replaces the old', get_option( $option )['anthropic_api_key'], 'sk-ant-second-0002WXYZ' );
ok( '  no leftover of the first', substr_count( wp_json_stub( get_option( $option ) ), 'fresh-0001' ), 0 );

echo "A second, third and fourth provider\n";
flosc_store_provider_api_key( $ivr, 'openai', 'sk-proj-openai-3333' );
flosc_store_provider_api_key( $ivr, 'xai', 'xai-key-4444' );
flosc_store_provider_api_key( $ivr, 'gemini', 'AIza-gemini-5555' );
$bag = get_option( $option );
ok( 'anthropic survives', $bag['anthropic_api_key'], 'sk-ant-second-0002WXYZ' );
ok( 'openai stored', $bag['openai_api_key'], 'sk-proj-openai-3333' );
ok( 'xai stored', $bag['xai_api_key'], 'xai-key-4444' );
ok( 'gemini stored', $bag['gemini_api_key'], 'AIza-gemini-5555' );
ok( '  all four coexist in one flow', count( array_filter( array( $bag['anthropic_api_key'] ?? '', $bag['openai_api_key'] ?? '', $bag['xai_api_key'] ?? '', $bag['gemini_api_key'] ?? '' ) ) ), 4 );
ok( '  and the flow is still itself', $bag['name'], 'WordPress Content Membership Journey' );

echo "Overwriting one of four leaves the other three\n";
flosc_store_provider_api_key( $ivr, 'openai', 'sk-proj-openai-REPLACED' );
$bag = get_option( $option );
ok( 'openai replaced', $bag['openai_api_key'], 'sk-proj-openai-REPLACED' );
ok( '  anthropic untouched', $bag['anthropic_api_key'], 'sk-ant-second-0002WXYZ' );
ok( '  xai untouched', $bag['xai_api_key'], 'xai-key-4444' );
ok( '  gemini untouched', $bag['gemini_api_key'], 'AIza-gemini-5555' );

echo "Keys FLOSC must not mangle\n";
$odd = 'sk-ant-api03-_A-Za-z0-9+/=.~key_with-every.char~AA';
flosc_store_provider_api_key( $ivr, 'anthropic', $odd );
ok( 'stored byte for byte', get_option( $option )['anthropic_api_key'], $odd );
$padded = flosc_store_provider_api_key( $ivr, 'xai', "  xai-padded-6666  " );
ok( 'surrounding whitespace is trimmed', get_option( $option )['xai_api_key'], 'xai-padded-6666' );

echo "What must be refused\n";
ok( 'an empty key', is_wp_error( flosc_store_provider_api_key( $ivr, 'anthropic', '' ) ), true );
ok( '  whitespace only', is_wp_error( flosc_store_provider_api_key( $ivr, 'anthropic', "   \t " ) ), true );
ok( '  a newline-carrying paste', is_wp_error( flosc_store_provider_api_key( $ivr, 'anthropic', "sk-ant\nbroken" ) ), true );
ok( '  something absurdly long', is_wp_error( flosc_store_provider_api_key( $ivr, 'anthropic', str_repeat( 'k', 5000 ) ) ), true );
ok( '  an unknown provider', is_wp_error( flosc_store_provider_api_key( $ivr, 'notaprovider', 'k' ) ), true );
ok( '  no flow at all', is_wp_error( flosc_store_provider_api_key( '', 'anthropic', 'k' ) ), true );
ok( 'and a refusal changes nothing', get_option( $option )['anthropic_api_key'], $odd );

echo "A flow with no settings row yet\n";
$r = flosc_store_provider_api_key( 'brand_new_ivr.md', 'gemini', 'AIza-brandnew-7777' );
ok( 'creates the row', is_wp_error( $r ), false );
ok( '  and holds the key', get_option( 'flosc_flow_brand_new_ivr' )['gemini_api_key'], 'AIza-brandnew-7777' );

function wp_json_stub( $v ) { return print_r( $v, true ); }

echo $fail ? "\n$fail FAILURES\n" : "\nAI key saving: all checks passed\n";
exit( $fail ? 1 : 0 );
