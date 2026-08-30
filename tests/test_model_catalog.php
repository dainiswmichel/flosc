<?php
/**
 * Parser checks against each provider's own documented example payload.
 *
 * The bodies below are copied from the published references named in the
 * header of includes/ai/flosc-model-catalog.php — not invented, and not
 * recalled. If a provider changes shape, this is where it shows up.
 */

define( 'ABSPATH', __DIR__ );

function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }
function __( $text, $domain = null ) { return $text; }
function add_query_arg( $args, $url ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args ); }

require_once __DIR__ . '/../includes/ai/flosc-model-catalog.php';

$failures = 0;

function check( $label, $actual, $expected ) {
	global $failures;

	if ( $actual === $expected ) {
		echo "  ok   $label\n";
		return;
	}

	$failures++;
	echo "  FAIL $label\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

function ids( $page ) { return array_column( $page['models'], 'id' ); }

echo "Anthropic — platform.claude.com/docs/en/api/models-list\n";
$page = flosc_model_catalog_page( 'anthropic', json_decode( '{"data":[{"id":"claude-opus-5","created_at":"2026-07-24T00:00:00Z","display_name":"Claude Opus 5","type":"model"}],"first_id":"first_id","has_more":true,"last_id":"last_id"}', true ) );
check( 'reads data[].id', ids( $page ), array( 'claude-opus-5' ) );
check( 'reads display_name', $page['models'][0]['label'], 'Claude Opus 5' );
check( 'pages on has_more via last_id', $page['cursor'], 'last_id' );
$last = flosc_model_catalog_page( 'anthropic', array( 'data' => array( array( 'id' => 'claude-sonnet-5' ) ), 'has_more' => false, 'last_id' => 'x' ) );
check( 'stops when has_more is false', $last['cursor'], '' );

echo "OpenAI — github.com/openai/openai-openapi\n";
$page = flosc_model_catalog_page( 'openai', json_decode( '{"object":"list","data":[{"id":"gpt-5.4-mini","object":"model","created":1686935002,"owned_by":"openai"}]}', true ) );
check( 'reads data[].id', ids( $page ), array( 'gpt-5.4-mini' ) );
check( 'does not page', $page['cursor'], '' );

echo "xAI — docs.x.ai rest-api-reference/inference/models (/v1/language-models)\n";
$body = json_decode( '{"models":[{"id":"latest","fingerprint":"fp_777a9f8466","object":"model","owned_by":"xai","input_modalities":["text","image"],"output_modalities":["text"],"aliases":["grok-4.3-latest"]},{"id":"grok-420-reasoning","object":"model","owned_by":"xai","aliases":[]}]}', true );
$page = flosc_model_catalog_page( 'xai', $body );
check( 'reads models[].id and documented aliases', ids( $page ), array( 'latest', 'grok-4.3-latest', 'grok-420-reasoning' ) );
check( 'labels an alias as one', $page['models'][1]['label'], 'alias for latest' );
check( 'does not page', $page['cursor'], '' );
check( 'the endpoint is language-models, not models', flosc_model_catalog_request( 'xai', 'k' )['url'], 'https://api.x.ai/v1/language-models' );

echo "Gemini — generativelanguage discovery doc\n";
$body = json_decode( '{"models":[{"name":"models/gemini-3.7-flash","displayName":"Gemini 3.7 Flash","supportedGenerationMethods":["generateContent","countTokens"]},{"name":"models/text-embedding-004","displayName":"Embedding 004","supportedGenerationMethods":["embedContent"]}],"nextPageToken":"tok"}', true );
$page = flosc_model_catalog_page( 'gemini', $body );
check( 'strips the models/ prefix', ids( $page ), array( 'gemini-3.7-flash' ) );
check( 'drops what cannot generateContent', count( $page['models'] ), 1 );
check( 'reads displayName', $page['models'][0]['label'], 'Gemini 3.7 Flash' );
check( 'pages via nextPageToken', $page['cursor'], 'tok' );

echo "Request shapes\n";
$a = flosc_model_catalog_request( 'anthropic', 'sk-test' );
check( 'anthropic sends x-api-key', $a['args']['headers']['x-api-key'], 'sk-test' );
check( 'anthropic pins the version header', $a['args']['headers']['anthropic-version'], '2023-06-01' );
check( 'anthropic asks for the documented max page', strpos( $a['url'], 'limit=1000' ) !== false, true );
check( 'anthropic cursors with after_id', strpos( flosc_model_catalog_request( 'anthropic', 'k', 'abc' )['url'], 'after_id=abc' ) !== false, true );
check( 'openai sends Bearer', flosc_model_catalog_request( 'openai', 'sk-o' )['args']['headers']['Authorization'], 'Bearer sk-o' );
check( 'gemini sends x-goog-api-key', flosc_model_catalog_request( 'gemini', 'AIza' )['args']['headers']['x-goog-api-key'], 'AIza' );
check( 'gemini cursors with pageToken', strpos( flosc_model_catalog_request( 'gemini', 'k', 'p2' )['url'], 'pageToken=p2' ) !== false, true );
check( 'an unknown provider has no request', flosc_model_catalog_request( 'nope', 'k' ), null );

echo "Anthropic workspace id (400 without it on a multi-workspace key)\n";
$plain = flosc_model_catalog_request( 'anthropic', 'k' );
check( 'omitted when there is none to send', isset( $plain['args']['headers']['anthropic-workspace-id'] ), false );
$scoped = flosc_model_catalog_request( 'anthropic', 'k', '', 'wrkspc_123' );
check( 'sent under the name Anthropic asks for', $scoped['args']['headers']['anthropic-workspace-id'], 'wrkspc_123' );
check( '  and the key still goes with it', $scoped['args']['headers']['x-api-key'], 'k' );
check( 'blank is treated as none', isset( flosc_model_catalog_request( 'anthropic', 'k', '', '   ' )['args']['headers']['anthropic-workspace-id'] ), false );

echo "The list is the provider's answer, not FLOSC's opinion of it\n";
$shape = array_keys( flosc_model_catalog_page( 'openai', array( 'data' => array( array( 'id' => 'gpt-5.4' ) ) ) )['models'][0] );
sort( $shape );
check( 'a model row carries an id and a label, nothing else', $shape, array( 'id', 'label' ) );

echo "Malformed input\n";
check( 'a non-array body yields nothing', flosc_model_catalog_page( 'openai', 'garbage' )['models'], array() );
check( 'a body with no rows yields nothing', flosc_model_catalog_page( 'openai', array() )['models'], array() );
check( 'rows that are not arrays are skipped', flosc_model_catalog_page( 'openai', array( 'data' => array( 'x', 7 ) ) )['models'], array() );
check( 'blank ids are skipped', flosc_model_catalog_page( 'openai', array( 'data' => array( array( 'id' => '  ' ) ) ) )['models'], array() );
check( 'gemini rows with no methods are skipped', flosc_model_catalog_page( 'gemini', array( 'models' => array( array( 'name' => 'models/x' ) ) ) )['models'], array() );

echo "Defaults\n";
check( 'every provider FLOSC offers has one', array_filter( array_map( 'flosc_default_model', array( 'anthropic', 'openai', 'xai', 'gemini' ) ) ) === array( 'claude-sonnet-4-5-20250929', 'gpt-5.4-mini', 'grok-4.6', 'gemini-3.7-flash' ), true );
check( 'ivr has none', flosc_default_model( 'ivr' ), '' );

echo $failures ? "\n$failures FAILED\n" : "\nAll model catalog checks passed.\n";
exit( $failures ? 1 : 0 );
