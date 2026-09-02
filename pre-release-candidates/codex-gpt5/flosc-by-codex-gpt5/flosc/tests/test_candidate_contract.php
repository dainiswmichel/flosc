<?php
/**
 * Static regression checks for the Codex deployment seams.
 * No WordPress runtime is required.
 */

$root = dirname(__DIR__);
$failures = array();

$expect = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$chatpack = file_get_contents($root . '/includes/class-flosc-chatpack.php');
$dispatch = file_get_contents($root . '/includes/class-ai-chat-dispatch.php');
$turn = file_get_contents($root . '/includes/chat-turn/trait-flosc-chat-turn.php');
$library = file_get_contents($root . '/includes/flosc-personality-library.php');

$expect(false !== strpos($chatpack, "build_identity_section((string) (\$eval_context['flow_id'] ?? ''), false)"), 'Follow-ups must send the complete current personality.');
$expect(false !== strpos($dispatch, 'public function get_response_result('), 'Structured production dispatch result is missing.');
$expect(false !== strpos($turn, "do_action('flosc_ai_dispatch_failed'"), 'Provider failure event is missing.');
$expect(false !== strpos($turn, 'A RAG failure must fall through'), 'RAG-to-plain-AI fallback contract is missing.');
$expect(false !== strpos($library, "'profile_hash'"), 'Personality deployment hash is missing.');
$expect(false !== strpos($library, "hash( 'sha256', \$genome"), 'Genome/profile fingerprint is missing.');

if ($failures) {
    fwrite(STDERR, "Candidate contract failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Candidate contract checks passed.\n";
