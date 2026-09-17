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

if ( ! function_exists( 'flosc_nows' ) ) {
	/**
	 * Strip every whitespace character.
	 *
	 * Source-text assertions below compare code, not the way it is laid out. A
	 * WordPress Coding Standards pass reformatted the plugin -- tabs for spaces,
	 * spaces inside call parentheses, realigned array arrows -- and every literal
	 * match went red on behaviour that had not changed. Both sides of those
	 * comparisons now pass through here, so the assertion is the same and the
	 * formatting no longer decides it. Assertions that use a regular expression
	 * are deliberately left reading the raw source.
	 *
	 * @param string $s Source text.
	 * @return string
	 */
	function flosc_nows( $s ) {
		return (string) preg_replace( '/\s+/', '', (string) $s );
	}
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
	'user_tier'        => 'VARCHAR(10)',
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
// Extracted in two steps rather than one regex spanning both lists. The
// single-regex version required the format list to follow the array with
// nothing between them but whitespace, so putting a comment there made it
// run past the end of the insert and count 41 placeholders against 13
// columns — a false failure, and the kind that teaches you to distrust the
// gate rather than the code.
// The close is found by pattern, not by a literal newline-plus-eight-spaces.
// That literal was what this used, and a WordPress Coding Standards pass
// replaced the space indent with tabs, so the string stopped existing anywhere
// in the file and the whole insert block came back empty -- reported as "the
// insert was found at all: false" on a logger that had not changed.
$insert_at  = strpos( $logger, '$wpdb->insert(', (int) strpos( $logger, 'function flosc_log_chat' ) );
$insert_end = false;
if ( $insert_at !== false && preg_match( '/\n\s*\);/', $logger, $flosc_m, PREG_OFFSET_CAPTURE, $insert_at ) ) {
	$insert_end = $flosc_m[0][1];
}
$insert_block = ( $insert_at !== false && $insert_end !== false )
	? substr( $logger, $insert_at, $insert_end - $insert_at )
	: '';
$cols = $insert_block !== '' ? preg_match_all( "/'[a-z_]+'\s*=>/", $insert_block ) : 0;
$fmts = $insert_block !== '' ? preg_match_all( "/'%[sd]'/", $insert_block ) : 0;
ok( 'the insert was found at all', $cols > 0, true );
// Counted, not asserted against a number: a hardcoded total goes stale the
// next time a column is added, which fails the build for the wrong reason and
// teaches whoever hits it to edit the number rather than check the pairing.
ok( 'one placeholder per column', $fmts, $cols );

// Counting is not enough. Equal counts in the wrong ORDER put every value one
// column to the left of where it belongs, which is the same silent corruption
// with a different cause. The insert writes every column the schema declares
// except the five the rating path owns.
preg_match( '/CREATE TABLE.*?PRIMARY KEY/s', $logger, $schema_match );
preg_match_all( '/^\s{12}([a-z_]+) /m', (string) ( $schema_match[0] ?? '' ), $schema_cols );
$audit_columns  = array( 'id', 'admin_rating', 'admin_note', 'rated_at', 'rated_by', 'is_protected' );
$schema_written = array_values( array_diff( $schema_cols[1], $audit_columns ) );
preg_match_all( "/'([a-z_]+)'\s*=>/", $insert_block, $insert_cols );

ok( 'the insert writes every non-audit column',
	count( $insert_cols[1] ), count( $schema_written ) );

$order_faults = array();
foreach ( $schema_written as $position => $column ) {
	$written = $insert_cols[1][ $position ] ?? '(missing)';
	if ( $column !== $written ) {
		$order_faults[] = $position . ': schema ' . $column . ' vs insert ' . $written;
	}
}
ok( '  in the order the schema declares them', $order_faults, array() );

echo "\nThe personality is read from the row the prompt was built from\n";
foreach ( array( 'personality_id', 'personality_name', 'profile_hash' ) as $key ) {
	ok( $key . ' is passed by the chat turn',
		(bool) preg_match( "/'" . $key . "'\s*=>\s*function_exists/", $turn ), true );
}
ok( 'and the logger resolves it for paths that build no prompt',
	strpos( flosc_nows( $logger ), flosc_nows( "flosc_personality_library_id_for_flow((string) (\$data['flow_id'] ?? ''))"  )) !== false, true );

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
	strpos( flosc_nows( $turn ), flosc_nows( 'flosc_personality_resolved_fingerprint($flow_id)'  )) !== false, true );
ok( '  and so does the logger',
	strpos( $logger, 'flosc_personality_resolved_fingerprint(' ) !== false, true );

// Absence used to be ambiguous: full page, or the client never said.
echo "\nSurface is explicit, never inferred from an empty field\n";
ok( "an unset surface is recorded as 'unknown'",
	strpos( $logger, "\$surface = 'unknown';" ) !== false, true );
ok( 'and the chat turn names the surface it was on',
	strpos( flosc_nows( $turn ), flosc_nows( "'surface'         => \$flosc_ctx_surface !== '' ? \$flosc_ctx_surface : 'full_page'"  )) !== false, true );

// FLOSC computed the VGM tier on every turn and threw it away at logging
// time: $eval_context['access_level'] built the prompt, gated the content and
// picked the user-status reply, but never reached a column. The Chat Logs
// screen guessed from user_id alone, which renders a Guest and a Member
// identically, so "is anyone registering repeatedly to farm Guest content?"
// had nothing to query.
// Everything FLOSC sends outward lands in a provider's logs and can never be
// read back. The id the provider returns is the reverse direction, and the
// only identifier that exists on both sides of the wire.
echo "\nThe row records the provider's own id for the call\n";
$identity        = (string) file_get_contents( $root . '/includes/ai/flosc-provider-identity.php' );
$turn_src_for_id = (string) file_get_contents( $root . '/includes/chat-turn/trait-flosc-chat-turn.php' );
ok( 'the id is captured from the response, not guessed',
	strpos( $identity, "add_filter( 'http_response', 'flosc_provider_capture_request_id'" ) !== false, true );
ok( '  from whichever header the provider used',
	strpos( $identity, "array( 'request-id', 'x-request-id', 'x-guploader-uploadid' )" ) !== false, true );
ok( '  and only for hosts FLOSC actually calls',
	strpos( $identity, 'flosc_provider_identity_hosts()' ) !== false, true );
ok( 'the module is loaded, or the filter never runs',
	strpos( (string) file_get_contents( $root . '/flosc.php' ), 'flosc-provider-identity.php' ) !== false, true );
// An IVR turn calls no provider, so it has no id to record. Only the two
// sites that follow an AI call read one.
ok( 'only the turns that called a provider record an id',
	substr_count( $turn_src_for_id, "'provider_request_id'" ), 2 );

echo "\nThe row records which VGM tier answered\n";
// Long array syntax, because WordPress Coding Standards require it and the
// plugin was converted to it. This needle used to spell the same call with
// short array syntax and stopped matching code that had not changed meaning.
ok( 'only visitor, guest or member is stored',
	strpos( flosc_nows( $logger ), flosc_nows( "in_array( \$user_tier, array( 'visitor', 'guest', 'member' ), true )" ) ) !== false, true );
ok( '  and anything else is left blank rather than guessed',
	strpos( $logger, "\$user_tier = '';" ) !== false, true );

// Six call sites write a chat row. A tier recorded on some of them and not
// others is worse than none: the gaps look like Visitors.
$turn_src = (string) file_get_contents( $root . '/includes/chat-turn/trait-flosc-chat-turn.php' );
ok( 'every flosc_log_chat call site names a tier',
	substr_count( flosc_nows( $turn_src ), flosc_nows( "'user_tier'" ) ),
	substr_count( flosc_nows( $turn_src ), flosc_nows( 'flosc_log_chat( array(' ) ) );

// A row from before the column has no tier, and must still render as it did.
ok( 'the screen appends the tier only when the row has one',
	strpos( flosc_nows( $screen ), flosc_nows( "in_array(\$tier, array('visitor', 'guest', 'member'), true)"  )) !== false, true );

echo "\nturn_status can only be one of the two things it means\n";
ok( 'complete or abandoned, nothing else',
	strpos( flosc_nows( $logger ), flosc_nows( "in_array( \$turn_status, array( 'complete', 'abandoned' ), true )" ) ) !== false, true );

echo "\nThe transcript names who answered\n";
ok( 'bot bubbles use the row\'s own personality name',
	strpos( flosc_nows( $screen ), flosc_nows( "\$speaker = trim( (string) ( \$r['personality_name'] ?? '' ) );" ) ) !== false, true );
ok( '  falling back to the old label for rows written before the column',
	strpos( $screen, "\$speaker = 'AI';" ) !== false, true );
ok( 'and the visitor bubble prefers the page_url column',
	strpos( flosc_nows( $screen ), flosc_nows( "\$visitor_context_url = trim((string) (\$r['page_url'] ?? ''));"  )) !== false, true );

// Plugin Check, 2026-09-05: one query still built its FROM clause by string
// interpolation while every other query in the file used %i. $wpdb->prepare()
// treats %i as an identifier and quotes it; interpolation is how a table name
// reaches SQL unquoted. The rest of the file was already right — this asserts
// the next query written here is too.
echo "\nNo query interpolates the table name into SQL\n";
$interpolated = array();
foreach ( preg_split( "/\r?\n/", $logger ) as $n => $line ) {
	// CREATE TABLE is dbDelta's own schema string: prepare() cannot build it.
	if ( strpos( $line, 'CREATE TABLE' ) !== false ) {
		continue;
	}
	if ( strpos( $line, '{$this->table_name}' ) !== false ) {
		$interpolated[] = ( $n + 1 ) . ': ' . trim( $line );
	}
}
ok( 'every table name reaches SQL as a %i placeholder', $interpolated, array() );
ok( '  and prepare() is given the name to quote',
	substr_count( $logger, 'FROM %i' ) > 0, true );

echo $fail ? "\n$fail FAILURES\n" : "\nA row says who answered, where, and over what\n";
exit( $fail ? 1 : 0 );
