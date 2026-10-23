<?php
/**
 * Regression guard for the per-user working-flow selection.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_root      = dirname( __DIR__ );
$flosc_settings  = (string) file_get_contents( $flosc_root . '/admin/settings.php' );
$flosc_admin     = (string) file_get_contents( $flosc_root . '/includes/flosc-admin.php' );
$flosc_framework = (string) file_get_contents( $flosc_root . '/includes/class-flosc-framework.php' );
$flosc_fail      = 0;

/**
 * Report one source-contract assertion.
 *
 * @param string $label Assertion label.
 * @param bool   $pass  Whether it passed.
 * @return void
 */
function flosc_working_flow_ok( $label, $pass ) {
	global $flosc_fail;
	if ( ! $pass ) {
		++$flosc_fail;
	}
	printf( "%-5s%s\n", $pass ? 'ok' : 'FAIL', $label );
}

flosc_working_flow_ok(
	'working-flow handler is registered',
	false !== strpos( $flosc_framework, "admin_post_flosc_set_working_flow" )
);
flosc_working_flow_ok(
	'flow switch uses a nonce-protected POST',
	false !== strpos( $flosc_settings, "wp_nonce_field( 'flosc_set_working_flow', 'flosc_working_flow_nonce' )" )
);
flosc_working_flow_ok(
	'handler verifies the working-flow nonce',
	false !== strpos( $flosc_admin, "check_admin_referer( 'flosc_set_working_flow', 'flosc_working_flow_nonce' )" )
);
flosc_working_flow_ok(
	'handler verifies access to the selected flow',
	(bool) preg_match( '/handle_set_working_flow\(\).*?can_access_flow_admin/s', $flosc_admin )
);
flosc_working_flow_ok(
	'working selection is stored per user',
	false !== strpos( $flosc_admin, "update_user_meta( get_current_user_id(), '_flosc_admin_working_ivr', \$ivr )" )
);

$flosc_working_pos = strpos( $flosc_settings, "get_user_meta( \$flosc_current_user_id, \$flosc_working_ivr_meta_key" );
$flosc_default_pos = strpos( $flosc_settings, "get_user_meta( \$flosc_current_user_id, \$flosc_default_ivr_meta_key" );
flosc_working_flow_ok(
	'working flow is considered before the default flow',
	false !== $flosc_working_pos && false !== $flosc_default_pos && $flosc_working_pos < $flosc_default_pos
);

echo $flosc_fail ? "\n{$flosc_fail} FAILURES\n" : "\nWorking-flow persistence contract is present\n";
exit( $flosc_fail ? 1 : 0 );
