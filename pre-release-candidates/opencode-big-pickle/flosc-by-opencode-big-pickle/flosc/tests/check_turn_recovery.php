<?php
/**
 * Reloading while the assistant is still typing must not poison the next turn.
 *
 * /flosc/v1/chat is an ordinary POST, not a stream. A reload drops the
 * browser's end of it; PHP keeps running, finishes, and writes the answer that
 * nobody reads. Two things were then wrong at once:
 *
 *   the answer existed and was never shown, and
 *   the restored thread held a visitor message with no assistant reply, which
 *   went to the server as history on the next request — so a normal follow-up
 *   looked like a question the assistant had ignored, and came back as
 *   scripted IVR copy on the same subject.
 *
 * Observed on the 2026-09m-04d visitor log, session 570378963: continuity was
 * fine through the companion handoff, then a mid-response refresh produced
 * off-topic IVR and the scripted fallback on the same Spotify question.
 *
 * The browser mints a turn id before the request leaves. On the next load it
 * asks what became of that turn: recovered, and the answer is shown; not
 * recovered, and the orphaned message is dropped rather than sent as history.
 * The same id also makes a resend idempotent, so a turn is never billed twice.
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

$logger = (string) file_get_contents( $root . '/includes/logging/class-flosc-chat-logger.php' );
$turn   = (string) file_get_contents( $root . '/includes/chat-turn/trait-flosc-chat-turn.php' );
$client = (string) file_get_contents( $root . '/assets/js/flosc-app.js' );

echo "A turn has an identity that survives the page\n";
ok( 'turn_id is a column',
	(bool) preg_match( '/^\s+turn_id\s+VARCHAR\(64\)/mi', $logger ), true );
ok( '  and it is indexed, because it is looked up by',
	strpos( $logger, 'KEY idx_flosc_chat_turn (turn_id)' ) !== false, true );
ok( 'the id is validated, never trusted as given',
	strpos( $logger, "preg_match('/^[a-f0-9-]{8,64}\$/', \$raw)" ) !== false, true );
ok( 'the browser mints one before the request leaves',
	strpos( $client, 'this._floscTurnId = this.floscMintTurnId();' ) !== false, true );
ok( '  marks it pending',
	strpos( $client, 'this.floscMarkTurnPending(this._floscTurnId, message);' ) !== false, true );
ok( '  sends it',
	strpos( $client, 'payload.turn_id = this._floscTurnId;' ) !== false, true );
ok( '  and clears it when the answer arrives',
	strpos( $client, 'this.floscClearTurnPending();' ) !== false, true );

echo "\nThe next page load asks what became of an unfinished turn\n";
ok( 'the client resumes a pending turn',
	strpos( $turn, "\$resume_turn_id = class_exists('FLOSC_Chat_Logger')" ) !== false, true );
ok( 'the server can find the row a turn id wrote',
	strpos( $logger, 'public function flosc_find_turn(' ) !== false, true );
ok( 'a written answer is returned to the reloaded page',
	strpos( $turn, "'recovered'       => true," ) !== false, true );
ok( 'and the visitor is shown the reply they reloaded away from',
	strpos( $client, "this.addMessage('assistant', html, true);" ) !== false, true );

echo "\nA turn that never completed leaves nothing behind\n";
ok( 'the row is marked abandoned',
	strpos( $logger, 'public function flosc_mark_turn_abandoned(' ) !== false, true );
ok( '  by the resume path when nothing was written',
	strpos( $turn, 'flosc_mark_turn_abandoned($resume_turn_id)' ) !== false, true );
ok( 'the orphaned visitor message is dropped',
	strpos( $client, 'floscDropOrphanVisitorMessage(pending.message)' ) !== false, true );
ok( '  and only when it is the message we were waiting on',
	strpos( $client, "last.role === 'user' && String(last.content || '') === String(message)" ) !== false, true );

// A resend after a reload used to mean a second provider call and a second
// bill for one question.
echo "\nThe same turn is never answered twice\n";
ok( 'a turn id already written is replayed, not re-dispatched',
	strpos( $turn, "'replayed'        => true," ) !== false, true );

// The recovered answer must not be shown twice, and which half of the pair is
// already on screen depends on who is asking.
echo "\nA recovered answer is never shown twice\n";
ok( 'the thread is checked before the answer is appended',
	strpos( $client, 'if (this.floscAssistantAlreadyInThread(String(data.message))) {' ) !== false, true );
ok( '  comparing normalised plain text, not markup',
	strpos( $client, 'const candidate = this._normalizeAssistantPlain(text);' ) !== false, true );
ok( 'because a signed-in turn is written to the session by PHP before the browser sees it',
	strpos( $turn, "\$this->session_manager->add_flosc_message(\$session_id, 'assistant'" ) !== false, true );

echo "\nBoth kinds of visitor recover\n";
ok( 'the anonymous path resumes after restoring its thread',
	strpos( $client, "this.restoreVisitorMessages();\n                    // After the thread is back, so a recovered answer lands" ) !== false, true );
ok( 'and the signed-in path resumes too',
	strpos( $client, "if (this.state !== 'visitor') {\n                // A signed-in thread restores from the server" ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nAn interrupted turn is recovered or retired, never left half-written\n";
exit( $fail ? 1 : 0 );
