<?php
/**
 * The seams the personality travels through, asserted at build time.
 *
 * Each check here stands for something that was live and wrong, and each was
 * found by a person using the site rather than by reading the diff:
 *
 *  - a follow-up turn that sent a name instead of a personality
 *  - a dispatch whose only answer was a string, so a refused request and an
 *    empty answer were indistinguishable at the call site
 *  - a retrieval miss that jumped straight to scripted copy, so a question the
 *    model could have answered got a canned reply
 *  - four shipped personalities carrying the flow's sales trajectory inside
 *    their own character text, which is what thinned BubblyBetty from 91%
 *    herself to 20%
 *
 * These are cheap string checks over source. They cannot prove the behaviour
 * is right; they prove nobody quietly removed the thing that made it right.
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

$chatpack = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$dispatch = (string) file_get_contents( $root . '/includes/class-ai-chat-dispatch.php' );
$turn     = (string) file_get_contents( $root . '/includes/chat-turn/trait-flosc-chat-turn.php' );
$library  = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );

echo "The personality reaches the model whole, every turn\n";
ok( 'follow-ups send the complete current profile',
	strpos( $chatpack, "build_identity_section((string) (\$eval_context['flow_id'] ?? ''), false)" ) !== false, true );

echo "\nA failed provider call is distinguishable from a quiet one\n";
ok( 'dispatch reports a structured outcome',
	strpos( $dispatch, 'public function get_response_result(' ) !== false, true );
ok( 'the turn asks for it',
	strpos( $turn, "method_exists(\$this->ai_chat_dispatch, 'get_response_result')" ) !== false, true );
ok( 'failure raises an event for admin monitors',
	strpos( $turn, "do_action('flosc_ai_dispatch_failed'" ) !== false, true );
ok( 'and response_source is read from the dispatch, not from a non-empty string',
	strpos( $turn, "\$dispatch_source === 'ai' && \$ai_response !== ''" ) !== false, true );

echo "\nRetrieval is optional, scripted copy is the last resort\n";
ok( 'a RAG miss falls through to the ordinary provider',
	strpos( $turn, 'A RAG failure must fall through' ) !== false, true );

echo "\nThe compiled character is fingerprinted and counted\n";
ok( 'a deployment hash is stored',
	strpos( $library, "'profile_hash'" ) !== false, true );
ok( 'over the genome and the runtime profile together',
	strpos( $library, 'flosc_personality_fingerprint( $genome, $profile )' ) !== false, true );
ok( '  from one definition, so a read and a write cannot disagree',
	substr_count( $library, "hash( 'sha256', (string) \$genome" ), 1 );
ok( 'the version counts changes rather than saves',
	strpos( $library, '$prior_hash === $hash' ) !== false, true );
ok( '  so a version is never hardcoded',
	preg_match( "/\\\$entry\\['profile_version'\\]\s*=\s*'1'\s*;/", $library ), 0 );

// A guest asked how to become a member and was told membership "opens up
// everything Dainis has created here". Not the personality — the framework had
// been handing the model "Sale — Member (purchased). Full access to all
// content." on every turn. FLOSC does not know what any membership contains.
echo "\nThe framework promises nothing on the floscAdmin's behalf\n";
$pack = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
// The five numbered lines only. The guard sentence below them names the very
// words it forbids, so scanning the whole block would match the prohibition
// and report it as the claim.
preg_match_all( '/\$section \.= "\d\. \*\*(?:Freeline|Login|Offer|Sale|Content)\*\*[^;]*;/', $pack, $phases );
$phase_text = implode( "\n", $phases[0] );
ok( 'all five phase lines were found', count( $phases[0] ), 5 );
foreach ( array( 'full access', 'everything', 'all content' ) as $claim ) {
	ok( 'no "' . $claim . '" claimed in a phase line',
		stripos( $phase_text, $claim ) !== false, false );
}
ok( 'and the model is told these are tiers, not contents',
	strpos( $pack, 'These name access tiers, not what any tier contains' ) !== false, true );

// The flow section sends the five phases, their outcomes and the floscAdmin's
// phase prompt on every turn. A personality that repeats that text does not
// reinforce it — it crowds out the character that was the reason to attach a
// personality at all.
echo "\nNo shipped personality carries the flow's trajectory in its own voice\n";
preg_match_all( "/'ai_base_prompt'\s*=>\s*<<<'PROMPT'\n(.*?)\nPROMPT,/s", $library, $prompts );
ok( 'four shipped profiles found', count( $prompts[1] ), 4 );

foreach ( $prompts[1] as $body ) {
	preg_match( '/# Personality profile: (.+)/', $body, $who );
	$name = isset( $who[1] ) ? trim( $who[1] ) : 'unnamed';

	foreach ( array( '## How you sell', '## Always' ) as $heading ) {
		ok( sprintf( '%s: no %s section', $name, trim( $heading, '# ' ) ),
			strpos( $body, $heading ) !== false, false );
	}
	ok( sprintf( '%s: profile stays under 2000 bytes', $name ),
		strlen( $body ) < 2000, true );
}

echo $fail ? "\n$fail FAILURES\n" : "\nEvery seam the personality travels through is intact\n";
exit( $fail ? 1 : 0 );
