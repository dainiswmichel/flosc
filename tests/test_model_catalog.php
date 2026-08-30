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

require_once __DIR__ . '/../includes/ai/flosc-provider-profiles.php';
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
check( 'and sends nothing else — a workspace-scoped key needs no more', array_keys( $a['args']['headers'] ), array( 'x-api-key', 'anthropic-version' ) );
check( 'anthropic asks for the documented max page', strpos( $a['url'], 'limit=1000' ) !== false, true );
check( 'anthropic cursors with after_id', strpos( flosc_model_catalog_request( 'anthropic', 'k', 'abc' )['url'], 'after_id=abc' ) !== false, true );
check( 'openai sends Bearer', flosc_model_catalog_request( 'openai', 'sk-o' )['args']['headers']['Authorization'], 'Bearer sk-o' );
check( 'gemini sends x-goog-api-key', flosc_model_catalog_request( 'gemini', 'AIza' )['args']['headers']['x-goog-api-key'], 'AIza' );
check( 'gemini cursors with pageToken', strpos( flosc_model_catalog_request( 'gemini', 'k', 'p2' )['url'], 'pageToken=p2' ) !== false, true );
check( 'an unknown provider has no request', flosc_model_catalog_request( 'nope', 'k' ), null );

echo "Against a real answer from api.anthropic.com, not a doc example\n";
// Captured from GET /v1/models with a workspace-scoped key on 2026-08-30.
// Only the fields the parser reads are kept; no account data.
$live = json_decode( <<<'JSON'
{
 "data": [
  {
   "id": "claude-opus-5",
   "display_name": "Claude Opus 5",
   "type": "model"
  },
  {
   "id": "claude-sonnet-5",
   "display_name": "Claude Sonnet 5",
   "type": "model"
  },
  {
   "id": "claude-fable-5",
   "display_name": "Claude Fable 5",
   "type": "model"
  },
  {
   "id": "claude-opus-4-8",
   "display_name": "Claude Opus 4.8",
   "type": "model"
  },
  {
   "id": "claude-opus-4-7",
   "display_name": "Claude Opus 4.7",
   "type": "model"
  },
  {
   "id": "claude-sonnet-4-6",
   "display_name": "Claude Sonnet 4.6",
   "type": "model"
  },
  {
   "id": "claude-opus-4-6",
   "display_name": "Claude Opus 4.6",
   "type": "model"
  },
  {
   "id": "claude-opus-4-5-20251101",
   "display_name": "Claude Opus 4.5",
   "type": "model"
  },
  {
   "id": "claude-haiku-4-5-20251001",
   "display_name": "Claude Haiku 4.5",
   "type": "model"
  },
  {
   "id": "claude-sonnet-4-5-20250929",
   "display_name": "Claude Sonnet 4.5",
   "type": "model"
  }
 ],
 "has_more": false,
 "first_id": null,
 "last_id": null
}
JSON
, true );
$page = flosc_model_catalog_page( 'anthropic', $live );
check( 'every model comes through', count( $page['models'] ), 10 );
check( 'ids are exact', ids( $page )[0], 'claude-opus-5' );
check( '  including a dated one', in_array( 'claude-sonnet-4-5-20250929', ids( $page ), true ), true );
check( 'display names come through', $page['models'][0]['label'], 'Claude Opus 5' );
check( 'has_more false means no second page is fetched', $page['cursor'], '' );
check( 'the default FLOSC ships is one the API really serves', in_array( flosc_default_model( 'anthropic' ), ids( $page ), true ), true );

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

echo "Describing one model, from its real description\n";
// Captured from GET /v1/models/claude-sonnet-5 on 2026-08-30.
$detail = flosc_model_details_summarise( json_decode( <<<'JSON'
{
 "type": "model",
 "id": "claude-sonnet-5",
 "display_name": "Claude Sonnet 5",
 "created_at": "2026-06-29T00:00:00Z",
 "max_input_tokens": 1000000,
 "max_tokens": 128000,
 "capabilities": {
  "batch": {
   "supported": true
  },
  "citations": {
   "supported": true
  },
  "code_execution": {
   "supported": true
  },
  "context_management": {
   "supported": true,
   "clear_tool_uses_20250919": {
    "supported": true
   },
   "clear_thinking_20251015": {
    "supported": true
   },
   "compact_20260112": {
    "supported": true
   }
  },
  "effort": {
   "supported": true,
   "low": {
    "supported": true
   },
   "medium": {
    "supported": true
   },
   "high": {
    "supported": true
   },
   "xhigh": {
    "supported": true
   },
   "max": {
    "supported": true
   }
  },
  "image_input": {
   "supported": true
  },
  "pdf_input": {
   "supported": true
  },
  "structured_outputs": {
   "supported": true
  },
  "thinking": {
   "supported": true,
   "types": {
    "enabled": {
     "supported": false
    },
    "adaptive": {
     "supported": true
    }
   }
  }
 }
}
JSON
, true ) );
check( 'the id comes through', $detail['id'], 'claude-sonnet-5' );
check( 'the real context window, not a guess', $detail['max_input_tokens'], 1000000 );
check( 'the real maximum reply', $detail['max_tokens'], 128000 );
check( '  which is far above the 4096 FLOSC used to hardcode', $detail['max_tokens'] > 4096, true );
check( 'effort levels come from the provider', $detail['effort_levels'], array( 'low', 'medium', 'high', 'xhigh', 'max' ) );
check( 'thinking is reported with only the types it supports', in_array( 'thinking (adaptive)', $detail['features'], true ), true );
check( '  images', in_array( 'reads images', $detail['features'], true ), true );
check( '  PDFs', in_array( 'reads PDFs', $detail['features'], true ), true );
$empty = flosc_model_details_summarise( array() );
check( 'a body with no capabilities describes nothing rather than guessing', $empty['features'], array() );
check( '  and claims no limits', $empty['max_tokens'], 0 );

echo "Every provider FLOSC offers has a row, none is implied\n";
$offered = array( 'anthropic', 'openai', 'xai', 'gemini' );
foreach ( $offered as $slug ) {
	$profile = flosc_provider_api_profile( $slug );
	check( "  $slug has a profile", is_array( $profile ), true );
	check( "  $slug declares its detail endpoint", array_key_exists( 'model_detail_url', (array) $profile ), true );
	check( "  $slug declares what it rejects", is_array( $profile['rejects_tuning'] ), true );
}
check( 'a provider FLOSC does not offer has none', flosc_provider_api_profile( 'notaprovider' ), null );

echo "Measured facts, and the absence of them, are both explicit\n";
check( 'anthropic rejects temperature — measured', flosc_provider_rejects_tuning( 'anthropic', 'temperature' ), true );
check( 'openai is not assumed to reject it', flosc_provider_rejects_tuning( 'openai', 'temperature' ), false );
check( '  nor xai', flosc_provider_rejects_tuning( 'xai', 'temperature' ), false );
check( '  nor gemini', flosc_provider_rejects_tuning( 'gemini', 'temperature' ), false );
check( 'an unknown provider suppresses nothing', flosc_provider_rejects_tuning( 'notaprovider', 'temperature' ), false );
check( 'anthropic can be asked about one model', flosc_provider_model_detail_url( 'anthropic', 'claude-sonnet-5' ), 'https://api.anthropic.com/v1/models/claude-sonnet-5' );
check( '  and a model id is escaped into the path', flosc_provider_model_detail_url( 'anthropic', 'a/b c' ), 'https://api.anthropic.com/v1/models/a%2Fb%20c' );
check( 'a provider with no measured endpoint returns none', flosc_provider_model_detail_url( 'openai', 'gpt-5.4-mini' ), '' );
check( '  and so does an empty model', flosc_provider_model_detail_url( 'anthropic', '' ), '' );

echo "Defaults\n";
check( 'every provider FLOSC offers has one', array_filter( array_map( 'flosc_default_model', array( 'anthropic', 'openai', 'xai', 'gemini' ) ) ) === array( 'claude-sonnet-4-5-20250929', 'gpt-5.4-mini', 'grok-4.6', 'gemini-3.7-flash' ), true );
check( 'ivr has none', flosc_default_model( 'ivr' ), '' );

echo $failures ? "\n$failures FAILED\n" : "\nAll model catalog checks passed.\n";
exit( $failures ? 1 : 0 );
