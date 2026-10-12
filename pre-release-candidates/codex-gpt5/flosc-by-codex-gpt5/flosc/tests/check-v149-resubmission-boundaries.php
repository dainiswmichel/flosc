<?php
/**
 * Regression coverage for the v149.codex resubmission boundaries.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Minimal attribute escaping for the renderer unit test.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Minimal HTML escaping for the renderer unit test.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Minimal selected() implementation for the renderer unit test.
	 *
	 * @param mixed $selected Selected value.
	 * @param mixed $current  Current value.
	 * @return void
	 */
	function selected( $selected, $current ) {
		if ( (string) $selected === (string) $current ) {
			echo 'selected="selected"';
		}
	}
}

require_once dirname( __DIR__ ) . '/admin/settings-helper.php';

$flosc_fail = 0;

/**
 * Record one assertion.
 *
 * @param string $label Assertion label.
 * @param bool   $pass  Whether it passed.
 * @return void
 */
function flosc_v149_resubmission_ok( $label, $pass ) {
	global $flosc_fail;
	if ( ! $pass ) {
		++$flosc_fail;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI regression output to stdout.
	fwrite( STDOUT, sprintf( "%s %s\n", $pass ? 'ok  ' : 'FAIL', $label ) );
}

/**
 * Read one shipping source file.
 *
 * @param string $relative Relative path.
 * @return string
 */
function flosc_v149_source( $relative ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local source for structural regression assertions.
	return (string) file_get_contents( dirname( __DIR__ ) . '/' . $relative );
}

ob_start();
flosc_render_vgm_options(
	array(
		'bad" onclick="alert(1)' => '<script>alert(1)</script>Member',
	),
	'bad" onclick="alert(1)'
);
$flosc_options = (string) ob_get_clean();
flosc_v149_resubmission_ok( 'VGM renderer escapes option value', false !== strpos( $flosc_options, 'bad&quot; onclick=&quot;alert(1)' ) );
flosc_v149_resubmission_ok( 'VGM renderer escapes option label', false !== strpos( $flosc_options, '&lt;script&gt;alert(1)&lt;/script&gt;Member' ) && false === strpos( $flosc_options, '<script>' ) );
flosc_v149_resubmission_ok( 'VGM renderer preserves selected option', false !== strpos( $flosc_options, 'selected="selected"' ) );

$flosc_framework = flosc_v149_source( 'includes/class-flosc-framework.php' );
$flosc_full_page = flosc_v149_source( 'includes/full-page-mode/class-flosc-full-page-mode.php' );
$flosc_autoprompt = flosc_v149_source( 'admin/autoprompts.php' );
$flosc_ivr = flosc_v149_source( 'admin/ivr-messages.php' );
$flosc_personality = flosc_v149_source( 'includes/flosc-personality-library.php' );
$flosc_magic = flosc_v149_source( 'includes/magic-link/class-flosc-magic-link-trait.php' );
$flosc_first_party = flosc_v149_source( 'includes/first-party-authentication/class-flosc-first-party-authentication.php' );
$flosc_plugin = flosc_v149_source( 'flosc.php' );

flosc_v149_resubmission_ok( 'candidate identifies v149.codex', false !== strpos( $flosc_plugin, 'Internal Iteration: v149.codex' ) );
flosc_v149_resubmission_ok( 'AI handlers sanitize API keys, model ids and tuning fields at input', false !== strpos( $flosc_framework, "sanitize_text_field( wp_unslash( \$_POST['api_key'] ) )" ) && false !== strpos( $flosc_framework, "sanitize_text_field( wp_unslash( \$_POST['model'] ) )" ) && false !== strpos( $flosc_framework, "sanitize_textarea_field( wp_unslash( \$_POST['params'] ) )" ) );
flosc_v149_resubmission_ok( 'full-page referral uses registered query variable', false !== strpos( $flosc_full_page, "\$vars[] = 'ref'" ) && false !== strpos( $flosc_full_page, "get_query_var( 'ref' )" ) && false === strpos( $flosc_full_page, "\$_GET['ref']" ) );
flosc_v149_resubmission_ok( 'test-offer preview verifies an id-bound nonce', false !== strpos( $flosc_full_page, "wp_verify_nonce( \$nonce, 'flosc_test_offer_' . \$offer_id )" ) );
flosc_v149_resubmission_ok( 'AutoPrompts sanitizes the verified body and validates trigger type', false !== strpos( $flosc_autoprompt, "map_deep( wp_unslash( \$_POST ), 'sanitize_text_field' )" ) && false !== strpos( $flosc_autoprompt, "array( 'ai', 'offer', 'action' )" ) );
flosc_v149_resubmission_ok( 'IVR input keeps safe multiline content', false !== strpos( $flosc_ivr, "map_deep( wp_unslash( \$_POST ), 'sanitize_text_field' )" ) && false !== strpos( $flosc_ivr, "wp_kses_post( wp_unslash( \$_POST['message_content'] ) )" ) && false !== strpos( $flosc_ivr, "sanitize_textarea_field( wp_unslash( \$_POST['message_password_retry'] ) )" ) );
flosc_v149_resubmission_ok( 'personality boot data uses the WordPress script-data API', false !== strpos( $flosc_personality, 'wp_localize_script(' ) && false === strpos( $flosc_personality, "'window.floscPersonalityWp = '" ) );

flosc_v149_resubmission_ok( 'email registration still creates a pending WordPress user', false !== strpos( $flosc_magic, 'function flosc_create_pending_email_registrant' ) && false !== strpos( $flosc_magic, 'wp_create_user( $username, $password, $email )' ) && false !== strpos( $flosc_magic, "'_flosc_email_account_status', 'pending'" ) );
flosc_v149_resubmission_ok( 'email registration still sends verification and activates the same account', false !== strpos( $flosc_magic, 'function flosc_send_email_verification_message' ) && false !== strpos( $flosc_magic, 'function flosc_activate_email_account' ) && false !== strpos( $flosc_magic, 'flosc_send_email_registration_welcome' ) );
flosc_v149_resubmission_ok( 'pending account login remains blocked until activation', false !== strpos( $flosc_first_party, 'function flosc_block_pending_email_login' ) && false !== strpos( $flosc_first_party, "'flosc_email_pending'" ) );

if ( $flosc_fail > 0 ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI regression output to stderr.
	fwrite( STDERR, "{$flosc_fail} failing assertion(s)\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI regression output to stdout.
fwrite( STDOUT, "check_v149_resubmission_boundaries: ok\n" );
exit( 0 );
