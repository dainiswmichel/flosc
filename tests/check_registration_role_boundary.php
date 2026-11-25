<?php
/**
 * New FLOSC accounts receive an explicit non-administrator role at insertion.
 *
 * Source-only. tests/ is excluded from the distribution zip.
 * Exit 0 when the boundary holds, 1 with a list otherwise.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	/** @var string */
	public $code;

	/**
	 * @param string $code Error code.
	 */
	public function __construct( $code = '' ) {
		$this->code = $code;
	}
}

class WP_Role {
	/** @var array<string,bool> */
	public $capabilities;

	/**
	 * @param array<string,bool> $capabilities Role capabilities.
	 */
	public function __construct( array $capabilities ) {
		$this->capabilities = $capabilities;
	}
}

$flosc_test_roles = array();

/**
 * Minimal WordPress translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}

/**
 * Minimal sanitize_key() stub.
 *
 * @param mixed $key Key.
 * @return string
 */
function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
}

/**
 * Return a configured test role.
 *
 * @param string $role Role slug.
 * @return WP_Role|null
 */
function get_role( $role ) {
	global $flosc_test_roles;
	return $flosc_test_roles[ $role ] ?? null;
}

/**
 * Minimal is_wp_error() stub.
 *
 * @param mixed $value Value.
 * @return bool
 */
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

$flosc_root = dirname( __DIR__ );
$flosc_fail = 0;
require_once $flosc_root . '/includes/flosc-accessors.php';

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

/**
 * Whether a creation slice stops on role failure before inserting a user.
 *
 * @param string $source Creation-path source.
 * @return bool
 */
function flosc_role_guard_precedes_insert( $source ) {
	$role_at   = strpos( $source, 'flosc_registration_role(' );
	$error_at  = strpos( $source, 'is_wp_error( $role )' );
	$insert_at = strpos( $source, 'flosc_insert_customer_account(' );
	return false !== $role_at
		&& false !== $error_at
		&& false !== $insert_at
		&& $role_at < $error_at
		&& $error_at < $insert_at;
}

$access   = file_get_contents( $flosc_root . '/includes/class-flosc-member-access.php' );
$click    = file_get_contents( $flosc_root . '/includes/sale/providers/class-flosc-clickbank-provider.php' );
$fw       = file_get_contents( $flosc_root . '/includes/class-flosc-framework.php' );
$trait    = file_get_contents( $flosc_root . '/includes/magic-link/class-flosc-magic-link-trait.php' );
$linker   = file_get_contents( $flosc_root . '/includes/sso/class-user-linker.php' );
$grant    = flosc_slice( $access, 'function grant_level', 'function revoke_level' );

$flosc_test_roles = array(
	'subscriber'       => new WP_Role( array( 'read' => true ) ),
	'flosc_guest'      => new WP_Role(
		array(
			'read'                  => true,
			'flosc_view_catalogue' => true,
		)
	),
	'administrator'    => new WP_Role( array( 'manage_options' => true ) ),
	'user_manager'     => new WP_Role( array( 'promote_users' => true ) ),
	'plugin_activator' => new WP_Role( array( 'activate_plugins' => true ) ),
	'plugin_installer' => new WP_Role( array( 'install_plugins' => true ) ),
	'core_updater'     => new WP_Role( array( 'update_core' => true ) ),
	'theme_manager'    => new WP_Role( array( 'edit_theme_options' => true ) ),
	'network_manager'  => new WP_Role( array( 'manage_network' => true ) ),
);

flosc_ok( 'subscriber is accepted', 'subscriber' === flosc_registration_role( 'subscriber' ) );
flosc_ok( 'safe custom FLOSC role is accepted', 'flosc_guest' === flosc_registration_role( 'flosc_guest' ) );
foreach ( array( 'administrator', 'user_manager', 'plugin_activator', 'plugin_installer', 'core_updater', 'theme_manager', 'network_manager' ) as $unsafe_role ) {
	flosc_ok( $unsafe_role . ' falls back to subscriber', 'subscriber' === flosc_registration_role( $unsafe_role ) );
}
flosc_ok( 'unknown role falls back to subscriber', 'subscriber' === flosc_registration_role( 'missing_role' ) );
flosc_ok( 'array input is not coerced', 'subscriber' === flosc_registration_role( array( 'flosc_guest' ) ) );

unset( $flosc_test_roles['subscriber'] );
flosc_ok( 'missing subscriber stops account creation', is_wp_error( flosc_registration_role( 'missing_role' ) ) );
$flosc_test_roles['subscriber'] = new WP_Role( array( 'manage_options' => true ) );
flosc_ok( 'privileged subscriber stops account creation', is_wp_error( flosc_registration_role( 'administrator' ) ) );
$flosc_test_roles['subscriber'] = new WP_Role( array( 'read' => true ) );

flosc_ok( 'grant_level still adds the purchased role', false !== strpos( $grant, '->add_role(' ) && false === strpos( $grant, 'flosc_registration_role' ) );

$purchase = flosc_slice( $click, 'function create_user_from_purchase', 'function get_unique_username' );
flosc_ok( 'clickbank slice found', '' !== $purchase );
flosc_ok(
	'clickbank inserts the validated role',
	false !== strpos( $purchase, 'flosc_insert_customer_account(' )
		&& false !== strpos( $purchase, 'flosc_registration_role(' )
		&& false !== strpos( $purchase, "apply_filters( 'flosc_default_user_role', 'subscriber' )" )
		&& flosc_role_guard_precedes_insert( $purchase )
		&& false === strpos( $purchase, 'wp_create_user(' )
		&& false === strpos( $purchase, 'set_role(' )
);

$sub = flosc_slice( $fw, 'function paypal_activate_subscription', 'function paypal_create_order' );
$one = flosc_slice( $fw, 'function paypal_capture_order', 'function handle_session_bootstrap' );
flosc_ok( 'paypal subscription slice found', '' !== $sub );
flosc_ok( 'paypal one-time slice found', '' !== $one );
foreach ( array( $sub, $one ) as $src ) {
	flosc_ok(
		'paypal path inserts the validated role',
		false !== strpos( $src, 'flosc_insert_customer_account(' )
			&& false !== strpos( $src, 'flosc_registration_role(' )
			&& false !== strpos( $src, "apply_filters( 'flosc_default_user_role', 'subscriber' )" )
			&& flosc_role_guard_precedes_insert( $src )
			&& false === strpos( $src, 'wp_create_user(' )
			&& false === strpos( $src, 'set_role(' )
	);
}

$email = flosc_slice( $trait, 'function flosc_create_pending_email_registrant', 'function flosc_email_account_is_pending' );
flosc_ok( 'email registration slice found', '' !== $email );
flosc_ok(
	'email registration inserts subscriber',
	false !== strpos( $email, 'flosc_insert_customer_account(' )
		&& false !== strpos( $email, "flosc_registration_role( 'subscriber' )" )
		&& flosc_role_guard_precedes_insert( $email )
		&& false === strpos( $email, 'wp_create_user(' )
		&& false === strpos( $email, 'set_role(' )
);

// The one place FLOSC inserts a WordPress user.
if ( ! function_exists( 'flosc_code_only' ) ) {
	/**
	 * PHP source with comments removed, so a count sees calls, not prose.
	 *
	 * @param string $src PHP source.
	 * @return string
	 */
	function flosc_code_only( $src ) {
		$out = '';
		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$out .= is_array( $token ) ? $token[1] : $token;
		}
		return $out;
	}
}
$accounts     = file_get_contents( $flosc_root . '/includes/flosc-accounts.php' );
$insert_slice = flosc_code_only( flosc_slice( $accounts, 'function flosc_insert_customer_account', 'function flosc_user_requires_core_login' ) );
flosc_ok( 'accounts helper slice found', '' !== $insert_slice );
flosc_ok( 'accounts helper is the only wp_insert_user in its file', 1 === substr_count( flosc_code_only( $accounts ), 'wp_insert_user(' ) );
$flosc_exists_at = strpos( $insert_slice, 'email_exists(' );
$flosc_role_at   = strpos( $insert_slice, 'flosc_registration_role(' );
$flosc_guard_at  = strpos( $insert_slice, 'is_wp_error( $role )' );
$flosc_insert_at = strpos( $insert_slice, 'wp_insert_user(' );
flosc_ok(
	'accounts helper refuses an existing email, then validates the role, then inserts',
	false !== $flosc_exists_at && false !== $flosc_role_at && false !== $flosc_guard_at && false !== $flosc_insert_at
		&& $flosc_exists_at < $flosc_role_at && $flosc_role_at < $flosc_guard_at && $flosc_guard_at < $flosc_insert_at
);
foreach ( array( 'clickbank' => $click, 'framework' => $fw, 'magic link' => $trait, 'sso linker' => $linker ) as $flosc_name => $flosc_caller ) {
	$flosc_code = flosc_code_only( $flosc_caller );
	flosc_ok( $flosc_name . ' calls no wp_insert_user or wp_create_user', false === strpos( $flosc_code, 'wp_insert_user(' ) && false === strpos( $flosc_code, 'wp_create_user(' ) );
}

$guest_assigns = substr_count( $trait, '->set_role( $guest_level )' );
$guest_guards  = substr_count( $trait, '$guest_level === $safe_guest' );
flosc_ok( 'guest promotion is the only remaining set_role', 2 === $guest_assigns, (string) $guest_assigns );
flosc_ok( 'guest promotion keeps the configured guest only when it is safe', 2 === $guest_guards, (string) $guest_guards );

$sso       = flosc_slice( $linker, 'function create_user_from_sso', 'function generate_unique_username' );
$filter_at = strpos( $sso, 'flosc_sso_default_role' );
$data_at   = strpos( $sso, 'flosc_sso_new_user_data' );
$role_at   = strpos( $sso, 'flosc_registration_role(' );
$error_at  = strpos( $sso, 'is_wp_error( $registration_role )' );
$insert_at = strpos( $sso, 'flosc_insert_customer_account(' );
flosc_ok( 'sso slice found', '' !== $sso );
flosc_ok(
	'sso proposes subscriber, then filters, then validates before insert',
	false !== $filter_at
		&& false !== $data_at
		&& false !== $role_at
		&& false !== $error_at
		&& false !== $insert_at
		&& false !== strpos( $sso, "apply_filters( 'flosc_sso_default_role', 'subscriber' )" )
		&& false === strpos( $sso, 'default_guest_level' )
		&& $filter_at < $data_at
		&& $data_at < $role_at
		&& $role_at < $error_at
		&& $error_at < $insert_at
		&& false === strpos( $sso, 'set_role(' )
);

if ( $flosc_fail > 0 ) {
	fwrite( STDERR, $flosc_fail . " failed\n" );
	exit( 1 );
}
fwrite( STDOUT, "registration role boundary passed\n" );
exit( 0 );
