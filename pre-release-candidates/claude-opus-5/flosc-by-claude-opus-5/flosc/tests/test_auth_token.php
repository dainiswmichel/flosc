<?php
/**
 * FLOSC bearer tokens: signed, scoped, and revocable.
 *
 * The architecture is deliberate — determine_current_user and
 * rest_authentication_errors are WordPress's documented extension points, and
 * FLOSC needs cross-domain identity a WordPress cookie cannot carry. What was
 * missing was a way to take a token back: it was stateless for its whole
 * lifetime, so logging out cleared the browser's copy and left any other copy
 * valid until it expired. These are the rules that close that.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

$USER_META = array();
$USERS     = array( 7 => true, 9 => true );

function absint( $v ) { return abs( (int) $v ); }
function get_user_meta( $id, $key, $single = false ) {
	global $USER_META;
	return $USER_META[ $id ][ $key ] ?? '';
}
function update_user_meta( $id, $key, $value ) {
	global $USER_META;
	$USER_META[ $id ][ $key ] = $value;
	return true;
}
function wp_generate_password( $len = 12, $special = true, $extra = false ) {
	return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 3 ), 0, $len ) . substr( (string) mt_rand(), -4 );
}
function flosc_token_secret() { return 'a-dedicated-flosc-secret-not-a-wp-salt'; }
function __( $t, $d = null ) { return $t; }

class FLOSC_Fake_User {
	public $ID;
	public function __construct( $id ) { $this->ID = $id; }
	public function exists() { global $USERS; return isset( $USERS[ $this->ID ] ); }
}
function get_userdata( $id ) {
	global $USERS;
	return isset( $USERS[ $id ] ) ? new FLOSC_Fake_User( $id ) : false;
}

// Only the token half of the class is under test, lifted verbatim by name so a
// rewrite of it has to keep these rules or fail here.
$source = (string) file_get_contents(
	dirname( __DIR__ ) . '/includes/first-party-authentication/class-flosc-first-party-authentication.php'
);

function flosc_cut_method( $source, $signature ) {
	$start = strpos( $source, $signature );

	if ( false === $start ) {
		fwrite( STDERR, "FAIL could not find $signature\n" );
		exit( 1 );
	}

	$depth = 0;
	$i     = strpos( $source, '{', $start );

	for ( $j = $i; $j < strlen( $source ); $j++ ) {
		if ( '{' === $source[ $j ] ) {
			$depth++;
		} elseif ( '}' === $source[ $j ] ) {
			$depth--;
			if ( 0 === $depth ) {
				return substr( $source, $start, $j - $start + 1 );
			}
		}
	}

	fwrite( STDERR, "FAIL unbalanced braces in $signature\n" );
	exit( 1 );
}

$methods = flosc_cut_method( $source, 'public function generate_flosc_auth_token' ) . "\n"
	. flosc_cut_method( $source, 'private function get_flosc_auth_generation' ) . "\n"
	. flosc_cut_method( $source, 'public function revoke_flosc_auth_tokens' ) . "\n"
	. flosc_cut_method( $source, 'public function validate_flosc_auth_token' ) . "\n"
	. flosc_cut_method( $source, 'public function allow_flosc_token_auth' );

eval( 'class FLOSC_Token_Under_Test { public $flosc_token_auth_used = false; private function is_flosc_rest_request() { return $GLOBALS["IS_FLOSC_ROUTE"]; } ' . $methods . ' }' );

$fail = 0;
function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-58s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

$auth = new FLOSC_Token_Under_Test();

echo "A token identifies the user who was issued it\n";
$token = $auth->generate_flosc_auth_token( 7 );
ok( 'a fresh token validates to its user', $auth->validate_flosc_auth_token( $token ), 7 );
ok( '  two tokens issued together are still different',
	$auth->generate_flosc_auth_token( 7 ) !== $auth->generate_flosc_auth_token( 7 ), true );

echo "A forged or damaged token authenticates nobody\n";
ok( 'a tampered signature is refused', $auth->validate_flosc_auth_token( substr( $token, 0, -4 ) . 'AAAA' ), false );
ok( '  so is empty', $auth->validate_flosc_auth_token( '' ), false );
ok( '  so is a string that is not base64', $auth->validate_flosc_auth_token( '!!!!' ), false );
ok( '  so is an absurdly long one', $auth->validate_flosc_auth_token( str_repeat( 'A', 2000 ) ), false );
// Signed correctly by FLOSC's own secret, in the current shape, and stale.
$stale_payload = implode( ':', array( 'v2', 7, time() - 60, 1, 'somenonce' ) );
$stale = base64_encode( $stale_payload . ':' . hash_hmac( 'sha256', $stale_payload, flosc_token_secret() ) );
ok( '  and an expired token, however well signed', $auth->validate_flosc_auth_token( $stale ), false );
ok( '  a lifetime asked for in the past does not become one in the future',
	$auth->validate_flosc_auth_token( $auth->generate_flosc_auth_token( 7, -86400 ) ), 7 );

// The old shape: user:expiry:signature, correctly signed, and unrevocable.
$v1_payload = '7:' . ( time() + 3600 );
$v1 = base64_encode( $v1_payload . ':' . hash_hmac( 'sha256', $v1_payload, flosc_token_secret() ) );
ok( 'a v1 token is refused — it could never be taken back', $auth->validate_flosc_auth_token( $v1 ), false );

echo "Logging out ends the session everywhere, not just in one browser\n";
$stolen = $auth->generate_flosc_auth_token( 7 );
ok( 'the copy works while the session is open', $auth->validate_flosc_auth_token( $stolen ), 7 );
$auth->revoke_flosc_auth_tokens( 7 );
ok( 'REVOKED: THE COPY STOPS WORKING AT ONCE', $auth->validate_flosc_auth_token( $stolen ), false );
ok( '  and a token issued after it works', $auth->validate_flosc_auth_token( $auth->generate_flosc_auth_token( 7 ) ), 7 );
ok( '  revoking one user leaves another alone',
	$auth->validate_flosc_auth_token( $auth->generate_flosc_auth_token( 9 ) ), 9 );
$other = $auth->generate_flosc_auth_token( 9 );
$auth->revoke_flosc_auth_tokens( 7 );
ok( '  even across a second revocation', $auth->validate_flosc_auth_token( $other ), 9 );

echo "A token for a user who no longer exists is not a credential\n";
$gone = $auth->generate_flosc_auth_token( 9 );
unset( $USERS[9] );
ok( 'a deleted user authenticates nobody', $auth->validate_flosc_auth_token( $gone ), false );

echo "FLOSC never overrules another authentication method\n";
$GLOBALS['IS_FLOSC_ROUTE'] = true;
$auth->flosc_token_auth_used = true;
ok( 'a WP_Error from another method is handed back untouched',
	$auth->allow_flosc_token_auth( 'a-wp-error-object' ), 'a-wp-error-object' );
ok( '  and so is another method\'s success', $auth->allow_flosc_token_auth( true ), true );
ok( 'undecided plus a FLOSC token on a FLOSC route authenticates',
	$auth->allow_flosc_token_auth( null ), true );
$auth->flosc_token_auth_used = false;
ok( '  undecided without a FLOSC token stays undecided', $auth->allow_flosc_token_auth( null ), null );
$GLOBALS['IS_FLOSC_ROUTE'] = false;
$auth->flosc_token_auth_used = true;
ok( 'a FLOSC token decides nothing outside FLOSC\'s own routes',
	$auth->allow_flosc_token_auth( null ), null );

echo $fail ? "\n$fail FAILURES\n" : "\nFLOSC token authentication: all checks passed\n";
exit( $fail ? 1 : 0 );
