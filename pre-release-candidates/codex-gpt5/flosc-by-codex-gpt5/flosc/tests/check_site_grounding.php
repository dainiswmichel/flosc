<?php
/**
 * Provider-neutral site grounding and title-depth confidentiality gates.
 *
 * This test executes the production search method against synthetic indexed
 * rows, then checks the integration points which make the same result part of
 * every provider's system prompt.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID = 0;
		public $post_status = 'publish';
		public $post_password = '';
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		$post = new WP_Post();
		$post->ID = (int) $post_id;
		return $post;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args ) {
		return array_map( 'get_post', (array) ( $args['post__in'] ?? array() ) );
	}
}

$root       = dirname( __DIR__ );
$index_src  = (string) file_get_contents( $root . '/includes/class-flosc-site-content-index.php' );
$turn_src   = (string) file_get_contents( $root . '/includes/chat-turn/trait-flosc-chat-turn.php' );
$chat_src   = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$rest_src   = (string) file_get_contents( $root . '/includes/flosc-rest.php' );
$rag_src    = (string) file_get_contents( $root . '/includes/class-rag-manager.php' );
$client_src = (string) file_get_contents( $root . '/includes/class-flosc-wp-ai-client.php' );
$dispatch   = (string) file_get_contents( $root . '/includes/class-ai-chat-dispatch.php' );
$fail       = 0;

function flosc_grounding_ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		++$fail;
	}
	printf(
		"%s %-69s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

function flosc_grounding_method( $source, $name ) {
	if ( ! preg_match( '/^(\t| {4})(?:public|private|protected)(?: static)? function ' . preg_quote( $name, '/' ) . '\(.*?^\1\}/ms', $source, $match ) ) {
		fwrite( STDERR, "missing method: {$name}\n" );
		exit( 1 );
	}
	return $match[0];
}

function flosc_grounding_const( $source, $name ) {
	if ( ! preg_match( '/\tconst ' . preg_quote( $name, '/' ) . '\s*=.*?;/s', $source, $match ) ) {
		fwrite( STDERR, "missing constant: {$name}\n" );
		exit( 1 );
	}
	return $match[0];
}

eval(
	'class FLOSC_Grounding_Index_Probe {'
	. ' public $doc = array();'
	. ' public function load( $flow_stem = "" ) { unset( $flow_stem ); return $this->doc; }'
	. ' public function resolve_category_slugs( $flow_stem = "" ) { unset( $flow_stem ); return array(); }'
	. flosc_grounding_const( $index_src, 'DEFAULT_RETRIEVE_LIMIT' ) . "\n"
	. flosc_grounding_const( $index_src, 'MAX_RETRIEVAL_CHARS_PER_HIT' ) . "\n"
	. flosc_grounding_const( $index_src, 'DEPTHS' ) . "\n"
	. flosc_grounding_const( $index_src, 'TIERS' ) . "\n"
	. flosc_grounding_method( $index_src, 'depth_token' ) . "\n"
	. flosc_grounding_method( $index_src, 'depth_rank' ) . "\n"
	. flosc_grounding_method( $index_src, 'tier_token' ) . "\n"
	. flosc_grounding_method( $index_src, 'normalize_depth_map' ) . "\n"
	. flosc_grounding_method( $index_src, 'vgm_list' ) . "\n"
	. flosc_grounding_method( $index_src, 'access_allows' ) . "\n"
	. flosc_grounding_method( $index_src, 'live_public_posts_for_rows' ) . "\n"
	. flosc_grounding_method( $index_src, 'row_is_currently_public' ) . "\n"
	. flosc_grounding_method( $index_src, 'row_depth' ) . "\n"
	. flosc_grounding_method( $index_src, 'row_body_at' ) . "\n"
	. flosc_grounding_method( $index_src, 'search' )
	. '}'
);

$probe = new FLOSC_Grounding_Index_Probe();
$row   = array(
	'id'              => '77',
	'post_id'         => 77,
	'title'           => 'Bites',
	'url'             => 'https://example.test/bites/',
	'content'         => 'Written for mixed choir a cappella. Hidden second sentence.',
	'excerpt'         => 'Written for mixed choir.',
	'snippet'         => 'Written for mixed choir a cappella.',
	'more_offset'     => strlen( 'Written for mixed choir a cappella.' ),
	'keywords'        => 'children choir piano secretbodyword',
	'keywords_manual' => '',
	'access'          => 'visitor guest member',
	'vgm'             => array( 'visitor' => 'title', 'guest' => 'excerpt', 'member' => 'full' ),
	'excluded'        => false,
	'categories'      => array(),
);
$probe->doc = array( 'posts' => array( '77' => $row ) );

echo "Title-only retrieval cannot use or reveal body-derived terms\n";
$hidden_query = $probe->search( '', 'secretbodyword', 'visitor', 3 );
flosc_grounding_ok( 'a hidden body keyword cannot retrieve a title-only row', strpos( $hidden_query, '**Bites**' ), false );

$title_result = $probe->search( '', 'Bites', 'visitor', 3 );
flosc_grounding_ok( 'the visible title can retrieve the title-only row', strpos( $title_result, '**Bites**' ) !== false, true );
flosc_grounding_ok( 'the title-only result carries its URL', strpos( $title_result, 'https://example.test/bites/' ) !== false, true );
foreach ( array( 'mixed choir', 'children choir', 'piano', 'secretbodyword', 'Hidden second sentence' ) as $forbidden ) {
	flosc_grounding_ok( 'title-only result omits ' . $forbidden, stripos( $title_result, $forbidden ), false );
}
flosc_grounding_ok( 'title-only text explicitly forbids instrumentation inference', stripos( $title_result, 'instrumentation' ) !== false, true );

echo "Authorized depths return only their configured slice\n";
$excerpt_result = $probe->search( '', 'Bites', 'guest', 3 );
flosc_grounding_ok( 'guest receives the configured excerpt', strpos( $excerpt_result, 'Written for mixed choir.' ) !== false, true );
flosc_grounding_ok( 'guest does not receive text past the excerpt', strpos( $excerpt_result, 'Hidden second sentence' ), false );
$full_result = $probe->search( '', 'Bites', 'member', 3 );
flosc_grounding_ok( 'member receives the full authorized body', strpos( $full_result, 'Written for mixed choir a cappella. Hidden second sentence.' ) !== false, true );
flosc_grounding_ok( 'retrieval metadata is never printed as evidence', strpos( $full_result, 'Keywords:' ), false );

echo "Every provider receives the same backend-selected evidence\n";
$retrieval_pos = strpos( $turn_src, "unset(\$eval_context['site_retrieval_context'])" );
$first_pack_pos = strpos( $turn_src, 'FLOSC_Chatpack::build_full_chatpack' );
$anthropic_pos = strpos( $turn_src, "\$flosc_use_rag = (\$ai_provider === 'anthropic')" );
flosc_grounding_ok( 'backend overwrites any browser retrieval field', $retrieval_pos !== false, true );
flosc_grounding_ok( 'retrieval occurs before the first Chatpack build', $retrieval_pos < $first_pack_pos, true );
flosc_grounding_ok( 'retrieval occurs before the Anthropic-only tool branch', $retrieval_pos < $anthropic_pos, true );
flosc_grounding_ok( 'Chatpack consumes the server retrieval field', strpos( $chat_src, "array_key_exists('site_retrieval_context'" ) !== false, true );
flosc_grounding_ok( 'full Chatpack includes the shared knowledge builder', substr_count( $chat_src, 'self::build_knowledge_section($eval_context)' ) >= 2, true );
flosc_grounding_ok( 'WP AI Client uses a system instruction', strpos( $client_src, 'using_system_instruction( $system )' ) !== false, true );
flosc_grounding_ok( 'xAI sends a system-role message', strpos( $dispatch, "['role' => 'system', 'content' => \$system_prompt]" ) !== false, true );

echo "There is one public conversational engine and one VGM body authority\n";
flosc_grounding_ok(
	'/chat-rag is a compatibility alias of /chat',
	substr_count( $rest_src, "'callback' => [\$this, 'handle_chat']" ) >= 2,
	true
);
$search_method = flosc_grounding_method( $rag_src, 'search_posts' );
$search_code = preg_replace( '#/\*.*?\*/#s', '', $search_method );
$search_code = preg_replace( '#(^|\s)//.*$#m', '$1', (string) $search_code );
flosc_grounding_ok( 'RAG post search has no live get_posts fallback', strpos( (string) $search_code, 'get_posts(' ), false );
$lesson_method = flosc_grounding_method( $rag_src, 'get_lesson_content' );
flosc_grounding_ok( 'specific-item retrieval also uses the VGM index', strpos( $lesson_method, 'FLOSC_Site_Content_Index::instance()' ) !== false && strpos( $lesson_method, '$index->search(' ) !== false, true );
$flow_method = flosc_grounding_method( $rag_src, 'site_index_flow_stem' );
flosc_grounding_ok( 'both RAG post tools resolve the active flow', substr_count( $rag_src, '$this->site_index_flow_stem( $index )' ) >= 2, true );

echo "Stale index rows cannot override current WordPress access state\n";
$public_method = flosc_grounding_method( $index_src, 'row_is_currently_public' );
$depth_method = flosc_grounding_method( $index_src, 'row_depth' );
flosc_grounding_ok( 'retrieval checks the live publication and password state', strpos( $public_method, "'publish' === \$post->post_status" ) !== false && strpos( $public_method, '$post->post_password' ) !== false, true );
flosc_grounding_ok( 'retrieval resolves the live FLOSC access floor', strpos( $depth_method, 'self::resolve_post_access( $live_post )' ) !== false, true );
flosc_grounding_ok( 'live post state is loaded in one bounded query', substr_count( $index_src, '$this->live_public_posts_for_rows( $posts )' ) >= 2, true );
flosc_grounding_ok( 'post saves refresh an index that already exists', strpos( $index_src, "add_action( 'save_post', array( \$this, 'sync_saved_post' ), 30, 3 )" ) !== false, true );

echo $fail ? "\n{$fail} FAILURES\n" : "\nprovider-neutral grounding: all green\n";
exit( $fail ? 1 : 0 );
