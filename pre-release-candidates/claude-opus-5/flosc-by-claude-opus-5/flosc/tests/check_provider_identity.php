<?php
/**
 * What leaves this site, and to whom.
 *
 * FLOSC sends two headers to AI providers so they can tell which software is
 * calling. The risk in a change like that is not that it breaks — it will not
 * — but that it quietly grows: a field added here, a visitor id added there,
 * and a plugin that told a provider its version is now telling four companies
 * who is reading what.
 *
 * So this gate holds three lines:
 *
 *   1. The headers reach AI hosts and nothing else.
 *   2. Nothing identifying an individual visitor is in them.
 *   3. readme.txt discloses every key the code can emit. A disclosure that
 *      drifts from the code is worse than none, because it reads as a promise.
 *
 * CLI only.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

/**
 * Report one assertion.
 *
 * @param string $label What is being asserted.
 * @param mixed  $got   Actual value.
 * @param mixed  $want  Expected value.
 * @return void
 */
function ok( $label, $got, $want = true ) {
	global $fail;
	$pass = ( $got === $want );
	if ( ! $pass ) {
		++$fail;
	}
	echo ( $pass ? 'ok   ' : 'FAIL ' ) . str_pad( $label, 62 ) . ' ' . var_export( $got, true );
	echo $pass ? "\n" : ' (want ' . var_export( $want, true ) . ")\n";
}

$identity = (string) file_get_contents( $root . '/includes/ai/flosc-provider-identity.php' );
$readme   = (string) file_get_contents( $root . '/readme.txt' );
$admin    = (string) file_get_contents( $root . '/admin/administration.php' );
$save     = (string) file_get_contents( $root . '/admin/settings.php' );

echo "The headers go to AI providers and nowhere else\n";
ok( 'the host list is explicit, never a wildcard',
	strpos( $identity, "function flosc_provider_identity_hosts" ) !== false, true );
ok( '  and the request filter checks it before adding anything',
	(bool) preg_match(
		'/function flosc_provider_identity_http_args.*?in_array\(\s*strtolower\(\s*\$host\s*\).*?flosc_provider_identity_hosts\(\)/s',
		$identity
	), true );
// http_request_args sees every outbound request every plugin on the site
// makes. A filter that forgot to check the host would put FLOSC headers on
// somebody else's API call, and it would be their bug report.
ok( '  and returns other hosts untouched',
	substr_count( $identity, 'return $args;' ) >= 3, true );

echo "\nFLOSC phones nothing home\n";
foreach ( array( 'flosc.ai/collect', 'da1.fm/collect', 'telemetry', 'phone_home' ) as $forbidden ) {
	ok( 'no ' . $forbidden . ' endpoint', stripos( $identity, $forbidden ) !== false, false );
}
// The two domains appear as attribution inside the User-Agent, which is a
// label, not a destination. Any http call from this file would be one.
ok( 'this file makes no outbound request of its own',
	(bool) preg_match( '/wp_remote_(get|post|request|head)\s*\(/', $identity ), false );

echo "\nNothing in the header identifies one visitor\n";
// tier is deliberately excluded from this list: 'v', 'g' or 'm' says what KIND
// of turn it was, and one of three letters narrows nothing to one person.
$never = array(
	'get_current_user_id' => 'the visitor user id',
	'user_email'          => 'an email address',
	'display_name'        => 'a display name',
	'REMOTE_ADDR'         => 'an IP address',
	'user_login'          => 'a username',
);
foreach ( $never as $needle => $what ) {
	ok( 'never sends ' . $what, strpos( $identity, $needle ) !== false, false );
}

echo "\nreadme.txt discloses every key the header can carry\n";
// Read the keys out of the code rather than listing them here: a list in the
// test is one more place to forget, and would pass while the code sent more.
preg_match_all( "/\\\$pairs\[\s*'([a-z_]+)'\s*\]\s*=/", $identity, $emitted );
$keys = array_values( array_unique( $emitted[1] ) );
// The literal keys of the opening array, which is written as a map.
preg_match( '/\$pairs = array\((.*?)\);/s', $identity, $seed );
preg_match_all( "/'([a-z_]+)'\s*=>/", (string) ( $seed[1] ?? '' ), $seed_keys );
$keys = array_values( array_unique( array_merge( $seed_keys[1], $keys ) ) );

ok( 'the header emits keys at all', count( $keys ) > 5, true );

$undisclosed = array();
foreach ( $keys as $key ) {
	if ( ! preg_match( '/`' . preg_quote( $key, '/' ) . '`/', $readme ) ) {
		$undisclosed[] = $key;
	}
}
ok( 'every key is named in readme.txt', $undisclosed, array() );
ok( '  and the header itself is named', strpos( $readme, 'X-DA1-Trace' ) !== false, true );
ok( '  and the readme says what is not sent',
	stripos( $readme, 'Not sent in these headers' ) !== false, true );
ok( '  and that nothing goes to a FLOSC service',
	stripos( $readme, 'FLOSC sends nothing to flosc.ai' ) !== false, true );

echo "\nA floscAdmin can turn both off\n";
ok( 'the identity toggle is rendered',
	strpos( $admin, "name=\"flosc_provider_identity[enabled]\"" ) !== false, true );
ok( 'the site-address toggle is rendered',
	strpos( $admin, "name=\"flosc_provider_identity[send_site]\"" ) !== false, true );
// An unticked checkbox posts nothing, and both of these default to on when
// the key is absent. Writing '0' explicitly is what makes "off" possible.
ok( 'and both are written explicitly, so unticking sticks',
	strpos( $save, "\$flosc_identity[\$flosc_identity_key] = isset(\$flosc_post['flosc_provider_identity'][\$flosc_identity_key])" ) !== false, true );

// Plugin Check flags every literal LLM-provider hostname it finds and points
// at wp_ai_client_prompt(). Five of ours are model DISCOVERY — listing what an
// administrator's own key may use, and reading one model's context window — and
// the AI Client cannot do either: it sends prompts, it does not enumerate a
// provider's catalogue. Two more are a chat provider and a transcription
// endpoint the AI Client has no path for at all.
//
// Each therefore carries phpcs:ignore with its own reason. This asserts none
// is added later without one, because an unexplained suppression is how a real
// finding gets hidden behind a habit.
echo "\nEvery direct provider call explains itself\n";
$llm_hosts   = array( 'api.anthropic.com', 'api.openai.com', 'api.x.ai', 'generativelanguage.googleapis.com' );
$unexplained = array();
$explained   = 0;
$php_files   = array();
$walker      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) );
foreach ( $walker as $file ) {
	if ( $file->isFile() && substr( $file->getFilename(), -4 ) === '.php' ) {
		$php_files[] = $file->getPathname();
	}
}
$php_files[] = $root . '/flosc.php';

foreach ( $php_files as $file ) {
	// The identity module lists these hosts to know which requests are ours.
	// It is an allowlist, not a call.
	if ( strpos( $file, 'flosc-provider-identity.php' ) !== false ) {
		continue;
	}
	$lines = explode( "\n", (string) file_get_contents( $file ) );
	foreach ( $lines as $n => $line ) {
		$trimmed = ltrim( $line );
		if ( $trimmed === '' || $trimmed[0] === '*' || strpos( $trimmed, '//' ) === 0 || strpos( $trimmed, '/*' ) === 0 ) {
			continue;
		}
		$hit = false;
		foreach ( $llm_hosts as $host ) {
			if ( strpos( $line, $host ) !== false ) {
				$hit = true;
				break;
			}
		}
		if ( ! $hit ) {
			continue;
		}
		$previous = $n > 0 ? $lines[ $n - 1 ] : '';
		if ( strpos( $previous, 'phpcs:ignore PluginCheck.CodeAnalysis.AIProvider' ) === false ) {
			$unexplained[] = basename( $file ) . ':' . ( $n + 1 );
			continue;
		}
		// "-- reason" is the part that makes a suppression reviewable.
		if ( ! preg_match( '/AIProvider\.DirectIntegration\s+--\s+\S/', $previous ) ) {
			$unexplained[] = basename( $file ) . ':' . ( $n + 1 ) . ' (no reason given)';
			continue;
		}
		++$explained;
	}
}
ok( 'every one carries a reason', $unexplained, array() );
ok( '  and there are the seven we know about', $explained, 7 );

// wp.org truncates the Description at 2500 characters and warns. Nothing was
// rewritten to fit: the subsections past the limit moved whole into their own
// section, so the copy still exists and is still read.
echo "\nreadme.txt fits what wp.org will actually show\n";
preg_match( '/^== Description ==\s*\n(.*?)(?=^== )/ms', $readme, $description );
$description_length = isset( $description[1] ) ? strlen( $description[1] ) : 0;
ok( 'the Description section was found', $description_length > 0, true );
ok( '  and fits in 2500 characters', $description_length <= 2500, true );
ok( '  with the rest kept, not deleted',
	strpos( $readme, '== More About FLOSC ==' ) !== false, true );

echo "\nThe prompt is untouched\n";
// The whole point of the header is that it costs no tokens. If this file ever
// reaches into the chatpack, the change has been made in the wrong place.
ok( 'the identity module never builds prompt text',
	(bool) preg_match( '/FLOSC_Chatpack|build_full_chatpack|ai_base_prompt/', $identity ), false );

echo $fail ? "\n$fail FAILURES\n" : "\nWhat leaves this site is disclosed, bounded, and switchable\n";
exit( $fail ? 1 : 0 );
