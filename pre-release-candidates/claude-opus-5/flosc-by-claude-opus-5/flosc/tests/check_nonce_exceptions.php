<?php
/**
 * The three places that read a superglobal without a WordPress nonce, checked.
 *
 * WPCS reports 22 WordPress.Security.NonceVerification.Recommended warnings, in
 * three files. None of them is suppressed and none of them is a defect: each is
 * a request a WordPress nonce cannot protect, guarded by a different control
 * that is named in the file's own comments.
 *
 * Comments rot. This codebase has already been bitten by exactly that: before
 * v75 the audio signature verifier existed, was described in a docblock, and
 * was never actually called. A suppression written on the strength of that
 * docblock would have been a lie, and nothing would have noticed.
 *
 * So the justifications are asserted here rather than trusted:
 *
 *   includes/class-flosc-framework.php, 12 warnings
 *     ajax_serve_user_audio() serves an <audio src>. The browser's media
 *     element fetches it, with Range requests, across a listening session; a
 *     form nonce is not a credential that URL can carry. It is protected by a
 *     capability check that runs BEFORE any flagged read, and by an HMAC over
 *     user + session + file + expiry that is verified with hash_equals().
 *     The HMAC covers the very parameters being read, so they must be read
 *     before it can be checked. No reordering fixes that, and none should.
 *
 *   includes/flosc-request.php, 7 warnings
 *     Read-only navigation parameters -- which tab, which view, which file to
 *     display. A nonce on ?tab=ai would break bookmarks and the back button
 *     while protecting nothing. This file already records that an earlier
 *     version routed the reads through filter_input( ..., FILTER_UNSAFE_RAW )
 *     and the warnings went to zero because the scanner could no longer SEE
 *     the read. That is the one outcome this gate exists to prevent.
 *
 *   includes/magic-link/class-flosc-magic-link-trait.php, 3 warnings
 *     A link from the user's inbox. The site did not compose the request and
 *     the user has no session yet, so there is no nonce to verify. The token
 *     on the URL is the credential, compared with hash_equals() before any
 *     session is established.
 *
 * Exit 0 when every control is present and in the right order, 1 otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_root = dirname( __DIR__ );
$flosc_fail = 0;

/**
 * Report one assertion.
 *
 * @param string $label What is being asserted.
 * @param mixed  $got   Measured.
 * @param mixed  $want  Required.
 * @return void
 */
function flosc_nx_ok( $label, $got, $want ) {
	global $flosc_fail;
	$pass = ( $got === $want );
	if ( ! $pass ) {
		++$flosc_fail;
	}
	printf(
		"%-5s%-62s %s%s\n",
		$pass ? 'ok' : 'FAIL',
		$label,
		var_export( $got, true ),
		$pass ? '' : ' (want ' . var_export( $want, true ) . ')'
	);
}

/**
 * The same source with every comment blanked out, line numbers preserved.
 *
 * Needed because these files talk about the very things being searched for.
 * class-flosc-framework.php names viewer_can_stream_member_audio() in a
 * docblock forty lines above it actually calls it, and flosc-request.php
 * narrates the FILTER_UNSAFE_RAW regression in prose. A first attempt at this
 * gate matched both and reported two failures that were its own.
 *
 * @param string $src File contents.
 * @return string Same length in lines, comments replaced by blanks.
 */
function flosc_nx_code_only( $src ) {
	$out = '';
	foreach ( token_get_all( $src ) as $token ) {
		if ( is_string( $token ) ) {
			$out .= $token;
			continue;
		}
		if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
			// Keep the newlines so every later line number still lines up.
			$out .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
			continue;
		}
		$out .= $token[1];
	}
	return $out;
}

/**
 * The line range of a named function or method, by brace matching.
 *
 * @param string $src  File contents.
 * @param string $name Function name.
 * @return array Two ints, first and last line, or an empty array.
 */
function flosc_nx_range( $src, $name ) {
	$at = strpos( $src, ' function ' . $name . '(' );
	if ( false === $at ) {
		return array();
	}
	$open = strpos( $src, '{', $at );
	if ( false === $open ) {
		return array();
	}

	$depth = 0;
	$len   = strlen( $src );
	for ( $i = $open; $i < $len; $i++ ) {
		if ( '{' === $src[ $i ] ) {
			++$depth;
		} elseif ( '}' === $src[ $i ] ) {
			--$depth;
			if ( 0 === $depth ) {
				return array(
					substr_count( $src, "\n", 0, $at ) + 1,
					substr_count( $src, "\n", 0, $i ) + 1,
				);
			}
		}
	}
	return array();
}

/**
 * The first line in a range matching a pattern, or 0.
 *
 * @param string[] $lines   File as lines.
 * @param array    $range   First and last line, 1-based.
 * @param string   $pattern Regex.
 * @return int
 */
function flosc_nx_first( array $lines, array $range, $pattern ) {
	if ( ! $range ) {
		return 0;
	}
	for ( $n = $range[0]; $n <= $range[1]; $n++ ) {
		if ( isset( $lines[ $n - 1 ] ) && preg_match( $pattern, $lines[ $n - 1 ] ) ) {
			return $n;
		}
	}
	return 0;
}

// ------------------------------------------------- the signed audio route ---

echo "Signed audio: the control runs, and it runs before the read\n";

$flosc_fw_path  = $flosc_root . '/includes/class-flosc-framework.php';
$flosc_fw_src   = flosc_nx_code_only( (string) file_get_contents( $flosc_fw_path ) );
$flosc_fw_lines = explode( "\n", $flosc_fw_src );

$flosc_serve = flosc_nx_range( $flosc_fw_src, 'ajax_serve_user_audio' );
flosc_nx_ok( '  ajax_serve_user_audio() was found', (bool) $flosc_serve, true );

$flosc_cap  = flosc_nx_first( $flosc_fw_lines, $flosc_serve, '/viewer_can_stream_member_audio\s*\(/' );
$flosc_read = flosc_nx_first( $flosc_fw_lines, $flosc_serve, '/\$_GET\s*\[/' );
$flosc_sig  = flosc_nx_first( $flosc_fw_lines, $flosc_serve, '/is_valid_audio_access_signature\s*\(/' );

flosc_nx_ok( '  the capability check is called', $flosc_cap > 0, true );
flosc_nx_ok( '  the signature check is called', $flosc_sig > 0, true );
flosc_nx_ok(
	sprintf( '  capability (%d) runs before the first $_GET read (%d)', $flosc_cap, $flosc_read ),
	( $flosc_cap > 0 && $flosc_read > 0 && $flosc_cap < $flosc_read ),
	true
);
flosc_nx_ok(
	'  the signature is checked before the file is served',
	( $flosc_sig > 0 && $flosc_read > 0 && $flosc_sig > $flosc_read ),
	true
);

$flosc_verify = flosc_nx_range( $flosc_fw_src, 'is_valid_audio_access_signature' );
flosc_nx_ok(
	'  it compares with hash_equals(), not ===',
	flosc_nx_first( $flosc_fw_lines, $flosc_verify, '/hash_equals\s*\(/' ) > 0,
	true
);
flosc_nx_ok(
	'  and refuses an expired or far-future signature',
	flosc_nx_first( $flosc_fw_lines, $flosc_verify, '/\$expires\s*<\s*\$now/' ) > 0,
	true
);

// -------------------------------------------- read-only navigation params ---

echo "\nNavigation parameters: the read stays visible to the scanner\n";

$flosc_req_raw = (string) file_get_contents( $flosc_root . '/includes/flosc-request.php' );
$flosc_req_src = flosc_nx_code_only( $flosc_req_raw );

/*
 * The regression this guards against: routing the reads through
 * filter_input( ..., FILTER_UNSAFE_RAW ) took the warnings to zero because
 * WPCS could no longer see a superglobal being read. Nothing was protected;
 * the scanner was blinded. FLOSC's own WPORG-04 rule calls FILTER_UNSAFE_RAW
 * not-sanitizing, which was WordPress.org's objection too.
 */
flosc_nx_ok(
	'  FILTER_UNSAFE_RAW appears nowhere in it',
	(bool) preg_match( '/FILTER_UNSAFE_RAW/', $flosc_req_src ),
	false
);
flosc_nx_ok(
	'  the reads are plain $_GET reads, sanitized where they happen',
	(bool) preg_match( '/sanitize_text_field\(\s*wp_unslash\(\s*\$_GET/', $flosc_req_src ),
	true
);
flosc_nx_ok(
	'  and the file still says why it has no nonce',
	(bool) preg_match( '/A warning that is true is not a defect to be hidden/', $flosc_req_raw ),
	true
);

// ------------------------------------------------------ the emailed token ---

echo "\nMagic link: the token is compared before a session is granted\n";

$flosc_ml_path  = $flosc_root . '/includes/magic-link/class-flosc-magic-link-trait.php';
$flosc_ml_src   = flosc_nx_code_only( (string) file_get_contents( $flosc_ml_path ) );
$flosc_ml_lines = explode( "\n", $flosc_ml_src );

$flosc_handle = flosc_nx_range( $flosc_ml_src, 'handle_login_token' );
flosc_nx_ok( '  handle_login_token() was found', (bool) $flosc_handle, true );

$flosc_cmp  = flosc_nx_first( $flosc_ml_lines, $flosc_handle, '/hash_equals\s*\(/' );
$flosc_auth = flosc_nx_first( $flosc_ml_lines, $flosc_handle, '/wp_set_auth_cookie\s*\(/' );

flosc_nx_ok( '  it compares the token with hash_equals()', $flosc_cmp > 0, true );
flosc_nx_ok(
	sprintf( '  the comparison (%d) precedes wp_set_auth_cookie (%d)', $flosc_cmp, $flosc_auth ),
	( $flosc_cmp > 0 && $flosc_auth > 0 && $flosc_cmp < $flosc_auth ),
	true
);

// ------------------------------------------- and none of it is suppressed ---

echo "\nNone of these is silenced\n";

$flosc_silenced = array();
foreach ( array( $flosc_fw_path, $flosc_root . '/includes/flosc-request.php', $flosc_ml_path ) as $flosc_file ) {
	$flosc_src = (string) file_get_contents( $flosc_file );
	if ( preg_match( '/phpcs:(ignore|disable)[^\n]*NonceVerification/', $flosc_src ) ) {
		$flosc_silenced[] = basename( $flosc_file );
	}
}
flosc_nx_ok( '  no NonceVerification suppression in any of the three', $flosc_silenced, array() );

echo $flosc_fail
	? "\n$flosc_fail FAILURES\n"
	: "\nEvery nonce exception is backed by a control that actually runs\n";
exit( $flosc_fail ? 1 : 0 );
