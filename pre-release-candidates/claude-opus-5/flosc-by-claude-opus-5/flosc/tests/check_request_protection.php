<?php
/**
 * The public throttles are the floscAdmin's, not the source file's.
 *
 * A visitor on a live site sent one message and read "Rate limit reached.
 * Please try again later." That was not the AI provider refusing anything. It
 * was FLOSC's own per-IP bucket, hardcoded at 30 requests an hour inside
 * includes/flosc-rest.php, and the chat client's nonce-refresh retry could
 * spend two of those on a single send.
 *
 * Nobody could see the number, nobody could change it, and the message gave no
 * hint where it came from. A limit like that is indistinguishable from a
 * broken site — which is exactly how it was reported.
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

$rest   = (string) file_get_contents( $root . '/includes/flosc-rest.php' );
$admin  = (string) file_get_contents( $root . '/admin/administration.php' );
$save   = (string) file_get_contents( $root . '/admin/settings.php' );
$boot   = (string) file_get_contents( $root . '/admin/flosc-app.php' );
$client = (string) file_get_contents( $root . '/assets/js/flosc-app.js' );

$keys = array(
	'enabled',
	'anonymous_chat_limit',
	'authenticated_chat_limit',
	'anonymous_ivr_limit',
	'metered_compute_limit',
	'visitor_compute_limit',
	'retry_after_429',
);

echo "Every limit is read from the saved option\n";
foreach ( $keys as $key ) {
	ok( $key . ' has a default in the REST reader',
		strpos( $rest, "'" . $key . "'" ) !== false, true );
}

// The whole point. A number typed into this file is a number nobody can reach.
echo "\nNo limit is hardcoded in the REST permission callbacks\n";
$callbacks = '';
// The close is matched as "newline, any indent, brace". It used to be spelled
// as four literal spaces, which stopped existing the day a WordPress Coding
// Standards pass converted the indentation to tabs -- and the extraction came
// back empty on callbacks that were still exactly where they had always been.
if ( preg_match_all( '/function check_(?:public_endpoint|metered_visitor_compute)_permission.*?\n\s*\}/s', $rest, $m ) ) {
	$callbacks = implode( "\n", $m[0] );
}
ok( 'the permission callbacks were found', $callbacks !== '', true );
ok( 'no check_rate_limit() carries a literal count',
	(bool) preg_match( "/check_rate_limit\(\s*'[^']*'\s*,\s*\d+/", $callbacks ), false );
ok( 'the chat route gets its own budget, apart from content reads',
	strpos( $rest, "\$endpoint === '/flosc/v1/chat'" ) !== false, true );

echo "\nThe floscAdmin can see and change every one of them\n";

// The two switches are written out; the five counts are one loop over a
// labelled map, so the check is that the key is in the map and that the loop
// still emits a name attribute for it.
foreach ( array( 'enabled', 'retry_after_429' ) as $key ) {
	ok( $key . ' has its own control',
		strpos( $admin, 'flosc_public_request_protection[' . $key . ']' ) !== false, true );
}
ok( 'the counts are rendered from a labelled map',
	strpos( flosc_nows( $admin ), flosc_nows( 'name="flosc_public_request_protection[<?php echo esc_attr($flosc_protection_key); ?>]"'  )) !== false, true );

// Both array syntaxes are accepted. This used to require "= [", and a
// WordPress Coding Standards pass converted the plugin to long array syntax,
// so the map came back empty and all five fields below reported missing on a
// screen that still offered every one of them.
preg_match( '/\$flosc_protection_fields\s*=\s*(?:\[|array\()(.*?)\n\s*(?:\]|\))\s*;/s', $admin, $map );
$fields = isset( $map[1] ) ? $map[1] : '';
foreach ( array( 'anonymous_chat_limit', 'authenticated_chat_limit', 'anonymous_ivr_limit', 'metered_compute_limit', 'visitor_compute_limit' ) as $key ) {
	ok( $key . ' is offered, with a label and an explanation',
		(bool) preg_match( "/'" . preg_quote( $key, '/' ) . "'\s*=>\s*(?:\[|array\()\s*'[^']+'\s*,\s*'[^']+'\s*(?:\]|\))/", $fields ), true );
}
ok( 'and the page says the scope is the whole installation',
	stripos( $admin, 'Global for this FLOSC installation' ) !== false, true );

echo "\nWhat the form posts is validated before it is stored\n";
ok( 'the save clamps to a usable range',
	strpos( flosc_nows( $save ), flosc_nows( 'min(10000, absint($flosc_post[\'flosc_public_request_protection\']'  )) !== false, true );
ok( '  and never to zero, which would refuse every visitor',
	strpos( flosc_nows( $save ), flosc_nows( '$flosc_protection[ $flosc_protection_key ] = max(' ) ) !== false, true );
ok( 'the option is written',
	strpos( flosc_nows( $save ), flosc_nows( "update_option('flosc_public_request_protection'"  )) !== false, true );

echo "\nA refused request is not retried unless the floscAdmin asked for it\n";
ok( 'the client is told the choice',
	strpos( $boot, "'retryAfter429'" ) !== false, true );
ok( 'errors carry the HTTP status so a 429 is recognisable',
	substr_count( $client, 'err.httpStatus = response.status;' ), 3 );
ok( 'and a 429 stops the nonce-refresh retry by default',
	strpos( $client, "firstErr?.httpStatus === 429 && !this.config?.retryAfter429" ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nThe public throttles are configured, visible, and bounded\n";
exit( $fail ? 1 : 0 );
