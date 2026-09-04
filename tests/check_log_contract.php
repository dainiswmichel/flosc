<?php
/**
 * A chat log row has to say who answered, where, and over what.
 *
 * It did not. Three facts were missing from the schema entirely:
 *
 *   surface     full page or companion. It existed only as a token packed into
 *               chain_detail, a shared VARCHAR(255) alongside ctx_post_id,
 *               page_content and page_ctx_note. A long note pushes the earlier
 *               tokens past the truncation point, so surface disappeared from
 *               exactly the turns worth investigating.
 *   page        a post id in that same string. No title, no URL, and nothing at
 *               all for a page that is not a post — an archive, a front page, a
 *               custom route, which is most of companion mode.
 *   personality nothing. Bot bubbles were labelled with the literal string 'AI',
 *               so a transcript could not show that the personality changed
 *               between two turns. Proving a Betty-to-Dan switch meant reading
 *               the prose and judging by ear.
 *
 * With profile_hash on the row, "did my edit take effect on the next turn" is a
 * string comparison rather than an opinion.
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
$screen = (string) file_get_contents( $root . '/admin/chat-logs.php' );

$columns = array(
	'surface'          => "VARCHAR(20)",
	'page_url'         => 'VARCHAR(255)',
	'page_title'       => 'VARCHAR(255)',
	'personality_id'   => 'VARCHAR(100)',
	'personality_name' => 'VARCHAR(120)',
	'profile_hash'     => 'VARCHAR(64)',
	'turn_status'      => 'VARCHAR(20)',
);

echo "Each fact is a column, not a token in a shared string\n";
foreach ( $columns as $column => $type ) {
	ok( $column . ' is declared ' . $type,
		(bool) preg_match( '/^\s+' . $column . '\s+' . preg_quote( $type, '/' ) . '/mi', $logger ), true );
	ok( '  and written on insert',
		(bool) preg_match( "/'" . $column . "'\s*=>/", $logger ), true );
}

// A wpdb->insert whose placeholder list is shorter than its column list
// silently writes the wrong values into the wrong columns.
echo "\nThe insert's placeholders match its columns\n";
preg_match( "/\\\$result = \\\$wpdb->insert\(\s*\\\$this->table_name,\s*\[(.*?)\],\s*(\[[^\]]*\])\s*\);/s", $logger, $m );
$cols = isset( $m[1] ) ? preg_match_all( "/'[a-z_]+'\s*=>/", $m[1] ) : 0;
$fmts = isset( $m[2] ) ? preg_match_all( "/'%[sd]'/", $m[2] ) : 0;
ok( 'the insert was found at all', $cols > 0, true );
// Counted, not asserted against a number: a hardcoded total goes stale the
// next time a column is added, which fails the build for the wrong reason and
// teaches whoever hits it to edit the number rather than check the pairing.
ok( 'one placeholder per column', $fmts, $cols );

echo "\nThe personality is read from the row the prompt was built from\n";
foreach ( array( 'personality_id', 'personality_name', 'profile_hash' ) as $key ) {
	ok( $key . ' is passed by the chat turn',
		(bool) preg_match( "/'" . $key . "'\s*=>\s*function_exists/", $turn ), true );
}
ok( 'and the logger resolves it for paths that build no prompt',
	strpos( $logger, "flosc_personality_library_id_for_flow((string) (\$data['flow_id'] ?? ''))" ) !== false, true );

// profile_hash is written when a personality is saved, so a row never saved
// since the field existed has none — which is every shipped default on a fresh
// install. The first live log came back with the column empty on every row:
// the mechanism was right and had nothing to read.
$library = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
ok( 'the fingerprint formula has one definition',
	substr_count( $library, "hash( 'sha256', (string) \$genome" ), 1 );
ok( '  and it is computed when the stored field is empty',
	strpos( $library, 'function flosc_personality_resolved_fingerprint' ) !== false, true );
ok( 'the chat turn reads it through that resolver',
	strpos( $turn, 'flosc_personality_resolved_fingerprint($flow_id)' ) !== false, true );
ok( '  and so does the logger',
	strpos( $logger, 'flosc_personality_resolved_fingerprint(' ) !== false, true );

// Absence used to be ambiguous: full page, or the client never said.
echo "\nSurface is explicit, never inferred from an empty field\n";
ok( "an unset surface is recorded as 'unknown'",
	strpos( $logger, "\$surface = 'unknown';" ) !== false, true );
ok( 'and the chat turn names the surface it was on',
	strpos( $turn, "'surface'         => \$flosc_ctx_surface !== '' ? \$flosc_ctx_surface : 'full_page'" ) !== false, true );

echo "\nturn_status can only be one of the two things it means\n";
ok( 'complete or abandoned, nothing else',
	strpos( $logger, "in_array(\$turn_status, ['complete', 'abandoned'], true)" ) !== false, true );

echo "\nThe transcript names who answered\n";
ok( 'bot bubbles use the row\'s own personality name',
	strpos( $screen, "\$speaker = trim((string) (\$r['personality_name'] ?? ''));" ) !== false, true );
ok( '  falling back to the old label for rows written before the column',
	strpos( $screen, "\$speaker = 'AI';" ) !== false, true );
ok( 'and the visitor bubble prefers the page_url column',
	strpos( $screen, "\$visitor_context_url = trim((string) (\$r['page_url'] ?? ''));" ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nA row says who answered, where, and over what\n";
exit( $fail ? 1 : 0 );
