<?php
/**
 * The two places FLOSC creates a WordPress account or starts a login session.
 *
 * Why FLOSC does either at all
 * ----------------------------
 * FLOSC sells access to content. A buyer, a registrant who confirmed their
 * email, or a visitor who signed in with an SSO provider needs a WordPress
 * account, because purchased access, member level, quiz results and content
 * permissions are stored against a WordPress user and must follow that person
 * across sessions and devices. An application password does not fit: the
 * person is a site visitor, not an API client.
 *
 * Why there are exactly two functions
 * -----------------------------------
 * Every FLOSC path that creates an account calls flosc_insert_customer_account().
 * Every FLOSC path that starts a session calls flosc_start_user_session(). No
 * other FLOSC file calls wp_insert_user(), wp_create_user(), wp_set_auth_cookie()
 * or wp_set_current_user() for a login. One place to read is one place to audit.
 *
 * What the two functions guarantee
 * --------------------------------
 * - An account is created with a nonprivileged role only. The role passes
 *   through flosc_registration_role(), which refuses any role carrying an
 *   administrative capability and stops creation when no safe role exists.
 * - A session is started only for a user the caller has already proved: a
 *   single-use token from the user's own inbox, a verified payment, or an SSO
 *   provider's validated profile. The caller names which in $context.
 * - A session started from an emailed or purchase token is refused for any
 *   account that can edit posts or manage the site. Those accounts sign in at
 *   wp-login.php, where WordPress and security plugins apply their own checks.
 * - Callers fire the core wp_login action once per new session, so security
 *   and session plugins observe every FLOSC login.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create one nonprivileged customer account.
 *
 * @param array  $account        {
 *     Account fields. Each is sanitized here, whatever the caller did.
 *
 *     @type string $user_login   Required. Passed through sanitize_user( strict ).
 *     @type string $user_email   Required. Must pass is_email().
 *     @type string $user_pass    Optional. A random 24-character password when empty.
 *     @type string $display_name Optional.
 *     @type string $first_name   Optional.
 *     @type string $last_name    Optional.
 * }
 * @param string $requested_role Role proposed by the caller or its filter.
 *                               flosc_registration_role() has the final say.
 * @return int|WP_Error New user ID, or the reason no account was created.
 */
function flosc_insert_customer_account( array $account, $requested_role = 'subscriber' ) {
	$user_login = sanitize_user( (string) ( $account['user_login'] ?? '' ), true );
	$user_email = sanitize_email( (string) ( $account['user_email'] ?? '' ) );

	if ( '' === $user_login ) {
		return new WP_Error( 'flosc_account_invalid_login', __( 'A valid username is required to create the account.', 'flosc' ) );
	}
	if ( '' === $user_email || ! is_email( $user_email ) ) {
		return new WP_Error( 'flosc_account_invalid_email', __( 'A valid email address is required to create the account.', 'flosc' ) );
	}
	if ( email_exists( $user_email ) ) {
		return new WP_Error( 'flosc_account_email_exists', __( 'An account with this email address already exists.', 'flosc' ) );
	}

	$role = flosc_registration_role( $requested_role );
	if ( is_wp_error( $role ) ) {
		return $role;
	}

	$user_pass = (string) ( $account['user_pass'] ?? '' );
	if ( '' === $user_pass ) {
		$user_pass = wp_generate_password( 24, true, true );
	}

	return wp_insert_user(
		array(
			'user_login'   => wp_slash( $user_login ),
			'user_email'   => wp_slash( $user_email ),
			'user_pass'    => $user_pass,
			'display_name' => wp_slash( sanitize_text_field( (string) ( $account['display_name'] ?? '' ) ) ),
			'first_name'   => wp_slash( sanitize_text_field( (string) ( $account['first_name'] ?? '' ) ) ),
			'last_name'    => wp_slash( sanitize_text_field( (string) ( $account['last_name'] ?? '' ) ) ),
			'role'         => $role,
		)
	);
}

/**
 * Whether an account may only sign in through wp-login.php.
 *
 * True for any account that can edit posts or that holds an administrative
 * capability. A FLOSC customer account (subscriber, or a nonprivileged role an
 * administrator configured) has none of these.
 *
 * @param WP_User $user Account to test.
 * @return bool
 */
function flosc_user_requires_core_login( WP_User $user ) {
	foreach ( array( 'edit_posts', 'manage_options', 'create_users', 'promote_users', 'edit_users' ) as $capability ) {
		if ( user_can( $user, $capability ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Start a WordPress login session for a user the caller has already proved.
 *
 * Contexts and the proof each one names:
 * - 'email_verification' The single-use token mailed at registration matched.
 * - 'magic_link'         A MagicLink token from the user's inbox matched.
 * - 'wp_sync'            A five-minute, single-use hop that follows a MagicLink
 *                        login on another FLOSC domain.
 * - 'login_token'        A single-use post-purchase token from the buyer's inbox.
 * - 'post_purchase'      A payment the provider has confirmed, in the buyer's
 *                        own browser, with flosc_post_purchase_instant_login on.
 * - 'sso'                An SSO provider returned a validated profile, or a
 *                        single-use token minted by that SSO callback.
 *
 * Every context except 'sso' refuses an account for which
 * flosc_user_requires_core_login() is true. An SSO provider authenticates the
 * person directly, as a password would; an emailed or purchase token only
 * proves access to an inbox or a receipt, which is not enough for an account
 * that can change the site.
 *
 * The caller fires do_action( 'wp_login' ) after this returns true, at the
 * point its own flow has finished the session setup, so the hook sees the
 * same state it always has.
 *
 * @param int    $user_id User to sign in.
 * @param string $context One of the contexts above.
 * @return bool Whether a session was started.
 */
function flosc_start_user_session( $user_id, $context ) {
	$allowed = array( 'email_verification', 'magic_link', 'wp_sync', 'login_token', 'post_purchase', 'sso' );
	if ( ! in_array( $context, $allowed, true ) ) {
		return false;
	}

	$user = get_userdata( absint( $user_id ) );
	if ( ! $user instanceof WP_User ) {
		return false;
	}

	if ( 'sso' !== $context && flosc_user_requires_core_login( $user ) ) {
		return false;
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	return true;
}
