<?php
/**
 * Login sessions fire wp_login once, at the end of the issuing branch.
 *
 * Source-only. tests/ is excluded from the distribution zip.
 * Exit 0 when the boundary holds, 1 with a list otherwise.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$flosc_root = dirname( __DIR__ );
$flosc_fail = 0;

/**
 * Record one assertion.
 *
 * @param string $label  Assertion name.
 * @param bool   $pass   True when the check holds.
 * @param string $detail Extra text on failure.
 * @return void
 */
function flosc_ok( $label, $pass, $detail = '' ) {
	global $flosc_fail;
	if ( ! $pass ) {
		++$flosc_fail;
		fwrite( STDERR, "FAIL {$label}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n" );
		return;
	}
	fwrite( STDOUT, "ok   {$label}\n" );
}

/**
 * Slice a file between two markers. The end marker is excluded.
 *
 * @param string $src   File contents.
 * @param string $start Start marker.
 * @param string $end   End marker.
 * @return string
 */
function flosc_slice( $src, $start, $end ) {
	$from = strpos( $src, $start );
	$to   = ( false === $from ) ? false : strpos( $src, $end, $from + strlen( $start ) );
	if ( false === $from || false === $to ) {
		return '';
	}
	return substr( $src, $from, $to - $from );
}

$trait = file_get_contents( $flosc_root . '/includes/magic-link/class-flosc-magic-link-trait.php' );
$oauth = file_get_contents( $flosc_root . '/includes/sso/class-oauth2-handler.php' );
$fw    = file_get_contents( $flosc_root . '/includes/class-flosc-framework.php' );
$paths = file_get_contents( $flosc_root . '/includes/filesystem/flosc-data-paths.php' );
$auth  = file_get_contents( $flosc_root . '/includes/first-party-authentication/class-flosc-first-party-authentication.php' );

$email = flosc_slice( $trait, 'Email registration verification', 'Case 0: Guest MagicLink' );
$case0 = flosc_slice( $trait, 'Case 0: Guest MagicLink', 'Case 3: WP auth cookie sync hop' );
$case3 = flosc_slice( $trait, 'Case 3: WP auth cookie sync hop', 'Case 1: Cross-domain login token' );
$case1 = flosc_slice( $trait, 'Case 1: Cross-domain login token', 'Case 2: Same-domain SSO' );
$case2 = flosc_slice( $trait, 'Case 2: Same-domain SSO', 'Pull quiz session from DO at login time' );

$action = "do_action( 'wp_login'";
flosc_ok( 'trait has four wp_login actions', 4 === substr_count( $trait, $action ), (string) substr_count( $trait, $action ) );
flosc_ok( 'trait has no direct handle_user_login call', false === strpos( $trait, '$this->handle_user_login(' ) );
flosc_ok( 'email verification fires once', 1 === substr_count( $email, $action ) && false !== strpos( $email, "check_rate_limit( 'email_verify_consume'" ) );
flosc_ok( 'magic link fires once after bookkeeping', 1 === substr_count( $case0, $action ) && false !== strpos( $case0, "check_rate_limit( 'magic_consume'" ) );
flosc_ok( 'wp-sync fires no wp_login', 0 === substr_count( $case3, $action ) && false !== strpos( $case3, "check_rate_limit( 'wp_sync_consume'" ) );
flosc_ok( 'cross-domain token fires once', 1 === substr_count( $case1, $action ) && false !== strpos( $case1, "check_rate_limit( 'login_token_consume'" ) );
flosc_ok( 'same-domain SSO fires once', 1 === substr_count( $case2, $action ) );

$rate_fail = flosc_slice( $case1, "check_rate_limit( 'login_token_consume'", '$transient_key' );
flosc_ok(
	'login-token rate-limit failure strips the token and exits',
	false !== strpos( $rate_fail, 'remove_query_arg' ) && false !== strpos( $rate_fail, 'wp_safe_redirect' ) && false !== strpos( $rate_fail, 'exit;' ) && false === strpos( $rate_fail, 'return;' )
);

$guest = flosc_slice( $fw, 'function handle_update_guest_profile', 'function generate_username_from_email' );
flosc_ok( 'password change does not fire wp_login', false !== strpos( $guest, 'wp_set_password(' ) && false === strpos( $guest, $action ) );

$purchase = flosc_slice( $paths, 'function flosc_issue_post_purchase_session', 'function flosc_paypal_purchase_intent_get' );
if ( '' === $purchase ) {
	$purchase = flosc_slice( $paths, 'function flosc_issue_post_purchase_session', 'AI-configuration read resolvers' );
}
flosc_ok(
	'post-purchase instant login stays default-off and fires once',
	false !== strpos( $purchase, "apply_filters( 'flosc_post_purchase_instant_login', false )" ) && 1 === substr_count( $purchase, $action )
);

$log_in = flosc_slice( $oauth, 'function log_user_in', 'function generate_login_token' );
flosc_ok( 'OAuth callback does not fire wp_login', false === strpos( $log_in, $action ) && false !== strpos( $log_in, 'wp_set_auth_cookie(' ) );

flosc_ok(
	'login count increments inside the wp_login handler',
	1 === substr_count( $fw, "add_action( 'wp_login', array( \$this, 'handle_user_login' )" ) && false !== strpos( $auth, "'_flosc_login_count'" )
);

if ( $flosc_fail ) {
	fwrite( STDERR, "{$flosc_fail} failed\n" );
	exit( 1 );
}
fwrite( STDOUT, "login action boundary passed\n" );
exit( 0 );
