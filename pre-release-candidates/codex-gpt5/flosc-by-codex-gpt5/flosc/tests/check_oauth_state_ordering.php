<?php
/**
 * Ensure handle_callback() does not touch OAuth state before verification.
 *
 * V86 collected attacker-controlled callback `state`, then used it as a
 * transient/option key (peek for redirect_to / flow_id, and delete on the
 * provider-error path) before verify_state() authenticated it. The comment
 * above that code said none of the values was used before verify_state()
 * passed. That sentence was false, and the defect survived a remediation
 * round because nothing in the suite went red on it.
 *
 * The invariant: in handle_callback(), the first `$this->verify_state(` is
 * the first transient or option get/set/delete. Reads are included, not
 * only writes — the peek is how an untrustworthy redirect target re-enters.
 *
 * After a successful verify, get_option( 'flosc_flow_' . $flow_id ) is
 * allowed: that key is verified flow_id, not raw callback state.
 *
 * Exit 0 when the ordering holds, 1 with a list otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_root = dirname( __DIR__ );
$flosc_file = $flosc_root . '/includes/sso/class-oauth2-handler.php';
$flosc_fail = 0;

/**
 * Write output from this CLI-only audit.
 *
 * @param resource $stream  Output stream.
 * @param string   $message Message to write.
 * @return void
 */
function flosc_test_write( $stream, $message ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI audit results to the requested standard stream.
	fwrite( $stream, $message );
}

/**
 * Record and print one audit assertion.
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
	}
	flosc_test_write(
		STDOUT,
		sprintf(
			"%s %s%s\n",
			$pass ? 'ok  ' : 'FAIL',
			$label,
			( $pass || '' === $detail ) ? '' : ' — ' . $detail
		)
	);
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the local handler source in this CLI-only audit.
$flosc_src = (string) file_get_contents( $flosc_file );
if ( '' === $flosc_src ) {
	flosc_test_write( STDERR, "unreadable: {$flosc_file}\n" );
	exit( 1 );
}

// Remove comments before checking forbidden live calls. The handler keeps
// explanatory comments naming the retired ambient accessors on purpose.
$flosc_code = '';
foreach ( token_get_all( $flosc_src ) as $flosc_token ) {
	if ( is_array( $flosc_token ) && in_array( $flosc_token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		continue;
	}
	$flosc_code .= is_array( $flosc_token ) ? $flosc_token[1] : $flosc_token;
}

flosc_ok(
	'no live ambient get_app_url() call remains in the OAuth handler',
	false === strpos( $flosc_code, 'flosc()->get_app_url(' )
);
flosc_ok(
	'no live ambient get_current_flow() call remains in the OAuth handler',
	false === strpos( $flosc_code, 'get_current_flow(' )
);

if ( ! preg_match( '/public function handle_callback\s*\([^)]*\)\s*\{/', $flosc_src, $flosc_m, PREG_OFFSET_CAPTURE ) ) {
	flosc_ok( 'handle_callback() is present', false );
	exit( 1 );
}
flosc_ok( 'handle_callback() is present', true );

$flosc_open  = (int) $flosc_m[0][1] + strlen( $flosc_m[0][0] ) - 1;
$flosc_n     = strlen( $flosc_src );
$flosc_depth = 0;
$flosc_end   = null;
for ( $flosc_i = $flosc_open; $flosc_i < $flosc_n; $flosc_i++ ) {
	$flosc_ch = $flosc_src[ $flosc_i ];
	if ( '{' === $flosc_ch ) {
		++$flosc_depth;
	} elseif ( '}' === $flosc_ch ) {
		--$flosc_depth;
		if ( 0 === $flosc_depth ) {
			$flosc_end = $flosc_i;
			break;
		}
	}
}

if ( null === $flosc_end ) {
	flosc_ok( 'handle_callback() body extracted', false, 'unbalanced braces' );
	exit( 1 );
}
$flosc_body = substr( $flosc_src, $flosc_open, $flosc_end - $flosc_open + 1 );
flosc_ok( 'handle_callback() body extracted', true );

$flosc_calls = preg_match_all( '/\$this->verify_state\s*\(/', $flosc_body );
flosc_ok( 'exactly one $this->verify_state( in handle_callback()', 1 === $flosc_calls, 'found ' . (int) $flosc_calls );

$flosc_pos = strpos( $flosc_body, '$this->verify_state(' );
if ( false === $flosc_pos ) {
	$flosc_pos = strpos( $flosc_body, '$this->verify_state (' );
}
flosc_ok( 'verify_state() call site located', false !== $flosc_pos );
if ( false === $flosc_pos ) {
	exit( 1 );
}

$flosc_before = substr( $flosc_body, 0, $flosc_pos );
$flosc_hits   = array();
foreach ( array( 'get_transient', 'set_transient', 'delete_transient', 'get_option', 'update_option', 'delete_option' ) as $flosc_fn ) {
	if ( preg_match( '/\b' . preg_quote( $flosc_fn, '/' ) . '\s*\(/', $flosc_before ) ) {
		$flosc_hits[] = $flosc_fn;
	}
}

flosc_ok(
	'no transient/option get/set/delete before verify_state()',
	array() === $flosc_hits,
	array() === $flosc_hits ? '' : implode( ', ', $flosc_hits )
);

$flosc_flow_assignment = strpos( $flosc_body, '$flow_id_for_redirect = sanitize_key(' );
flosc_ok(
	'verified flow id is assigned after verify_state()',
	false !== $flosc_flow_assignment && $flosc_flow_assignment > $flosc_pos
);

if ( false !== $flosc_flow_assignment ) {
	$flosc_before_flow            = substr( $flosc_body, $flosc_pos, $flosc_flow_assignment - $flosc_pos );
	$flosc_after_flow             = substr( $flosc_body, $flosc_flow_assignment );
	$flosc_unverified_error_calls = preg_match_all( '/\$this->redirect_with_error\s*\(/', $flosc_before_flow );
	$flosc_verified_error_calls   = preg_match_all( '/\$this->redirect_with_error\s*\([^;]+\$flow_id_for_redirect\s*\);/s', $flosc_after_flow );

	flosc_ok(
		'exactly one pre-flow-id error redirect handles failed verification',
		1 === $flosc_unverified_error_calls,
		'found ' . (int) $flosc_unverified_error_calls
	);
	flosc_ok(
		'all six post-verification error redirects carry the verified flow id',
		6 === $flosc_verified_error_calls,
		'found ' . (int) $flosc_verified_error_calls
	);
}

flosc_ok(
	'slug rewrite resolves from the verified flow id',
	false !== strpos( $flosc_body, '$this->resolve_app_url_from_flow_id( $flow_id_for_redirect )' )
);
flosc_ok(
	'success redirect allowlist is scoped to the verified flow id',
	false !== strpos( $flosc_body, '$this->is_allowed_sso_redirect( $redirect_to, $flow_id_for_redirect )' )
);
flosc_ok(
	'error redirect allowlist receives its verified flow id',
	false !== strpos( $flosc_code, '$this->is_allowed_sso_redirect( $base_url, $flow_id )' )
);

if ( $flosc_fail > 0 ) {
	flosc_test_write( STDOUT, "\n{$flosc_fail} failing assertion(s)\n" );
	exit( 1 );
}

flosc_test_write( STDOUT, "\ncheck_oauth_state_ordering: ok\n" );
exit( 0 );
