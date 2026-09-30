<?php
/**
 * The flow-file request decision, against every request shape it can be sent.
 *
 * admin/flow.php decides which of duplicate, import and delete a POST is asking
 * for, and which file it applies to. That decision was rewritten so the nonce is
 * verified BEFORE any action payload is read, which is a stronger property than
 * the one PHPCS measures: the sniff is satisfied by a nonce check anywhere in a
 * function's scope, including after the read.
 *
 * A rewrite like that is exactly where behaviour goes missing quietly. So this
 * does not read the new code and agree with it. It lifts the two functions out
 * of admin/flow.php as they actually ship, stubs the WordPress functions they
 * call, and sends each request shape through them.
 *
 * The rows below are the acceptance contract:
 *
 *   GET / page render                      no action
 *   unrelated POST                         no action
 *   valid duplicate form                   duplicate
 *   valid import form                      import
 *   valid delete form                      delete
 *   button only, no filename               no action
 *   filename only, no button               no action
 *   button + filename + bad nonce          nonce rejection
 *   button + filename + missing nonce      nonce rejection
 *   valid nonce + insufficient capability  rejected
 *   non-scalar filename                    action runs, filename empty
 *
 * "Nonce rejection" is the WordPress "link you followed has expired" screen.
 * A request that carries a real submission with a stale nonce must be rejected
 * rather than silently ignored -- an admin whose session timed out needs to be
 * told, not left clicking a button that does nothing.
 *
 * Exit 0 when every row matches, 1 with the failures otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_fail = 0;

// ---------------------------------------------------------------- stubs ---

$GLOBALS['flosc_test_can']    = true;
$GLOBALS['flosc_test_denied'] = false;

/**
 * Stand-in for the WordPress capability check.
 *
 * @param string $cap Capability.
 * @return bool
 */
function current_user_can( $cap ) {
	unset( $cap );
	return (bool) $GLOBALS['flosc_test_can'];
}

/**
 * Stand-in for wp_unslash().
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/**
 * Stand-in for sanitize_text_field().
 *
 * @param mixed $value Value.
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( wp_strip_all_tags( (string) $value ) );
}

/**
 * Stand-in for wp_strip_all_tags().
 *
 * @param string $value Value.
 * @return string
 */
function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

/**
 * Stand-in for sanitize_file_name(): close enough for these cases.
 *
 * @param string $value Value.
 * @return string
 */
function sanitize_file_name( $value ) {
	$value = (string) $value;
	$value = str_replace( array( '..', "\0" ), '', $value );
	return preg_replace( '/[^A-Za-z0-9._\- \/]/', '', $value );
}

/**
 * Stand-in for wp_verify_nonce(): a nonce reads "valid:<action>".
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return int|false
 */
function wp_verify_nonce( $nonce, $action ) {
	return ( 'valid:' . $action === $nonce ) ? 1 : false;
}

/**
 * Stand-in for check_admin_referer(): records the rejection instead of dying.
 *
 * @param string $action Action.
 * @return void
 */
function check_admin_referer( $action ) {
	unset( $action );
	$GLOBALS['flosc_test_denied'] = true;
}

// ------------------------------------------------- lift the real source ---

/**
 * One function's source, taken from the file that ships it.
 *
 * @param string $src  File contents.
 * @param string $name Function name.
 * @return string Source, or '' when not found.
 */
function flosc_extract_function( $src, $name ) {
	$at = strpos( $src, 'function ' . $name . '(' );
	if ( false === $at ) {
		return '';
	}
	$open = strpos( $src, '{', $at );
	if ( false === $open ) {
		return '';
	}

	$depth = 0;
	$len   = strlen( $src );
	for ( $i = $open; $i < $len; $i++ ) {
		if ( '{' === $src[ $i ] ) {
			++$depth;
		} elseif ( '}' === $src[ $i ] ) {
			--$depth;
			if ( 0 === $depth ) {
				return substr( $src, $at, $i - $at + 1 );
			}
		}
	}
	return '';
}

$flosc_src = (string) file_get_contents( dirname( __DIR__ ) . '/admin/flow.php' );

foreach ( array( 'flosc_flow_file_name', 'flosc_flow_file_request' ) as $flosc_fn ) {
	$flosc_code = flosc_extract_function( $flosc_src, $flosc_fn );
	if ( '' === $flosc_code ) {
		echo "FAIL  could not lift $flosc_fn() out of admin/flow.php\n";
		exit( 1 );
	}
	eval( $flosc_code );
}

// ------------------------------------------------------------- the rows ---

/**
 * Send one request shape through the decision and check what came back.
 *
 * @param string $label  What this row is.
 * @param string $method REQUEST_METHOD.
 * @param array  $post   The $_POST to send.
 * @param bool   $can    Whether the user has the outer capability.
 * @param string $action Expected action.
 * @param string $file   Expected filename.
 * @param bool   $denied Whether a nonce rejection is expected.
 * @return void
 */
function flosc_row( $label, $method, array $post, $can, $action, $file, $denied ) {
	global $flosc_fail;

	$_SERVER['REQUEST_METHOD']    = $method;
	$_POST                        = $post;
	$GLOBALS['flosc_test_can']    = $can;
	$GLOBALS['flosc_test_denied'] = false;

	$got = flosc_flow_file_request();

	$ok = ( $got['action'] === $action )
		&& ( $got['file'] === $file )
		&& ( $GLOBALS['flosc_test_denied'] === $denied );

	if ( ! $ok ) {
		++$flosc_fail;
	}

	printf(
		"%-5s%-42s action=%-10s file=%-16s rejected=%s\n",
		$ok ? 'ok' : 'FAIL',
		$label,
		"'" . $got['action'] . "'",
		"'" . $got['file'] . "'",
		$GLOBALS['flosc_test_denied'] ? 'yes' : 'no'
	);

	if ( ! $ok ) {
		printf(
			"     wanted action='%s' file='%s' rejected=%s\n",
			$action,
			$file,
			$denied ? 'yes' : 'no'
		);
	}
}

echo "Nothing action-specific is read before a nonce verifies\n\n";

$flosc_dup = array(
	'_wpnonce'                 => 'valid:flosc_duplicate_ivr_file',
	'flosc_duplicate_ivr_file' => 'Duplicate',
	'duplicate_ivr_file'       => 'dainis_net_ivr.md',
);

flosc_row( 'GET, page render', 'GET', $flosc_dup, true, '', '', false );
flosc_row( 'unrelated POST', 'POST', array( 'something_else' => '1' ), true, '', '', false );
flosc_row( 'valid duplicate form', 'POST', $flosc_dup, true, 'duplicate', 'dainis_net_ivr.md', false );

flosc_row(
	'valid import form',
	'POST',
	array(
		'_wpnonce'                       => 'valid:flosc_import_selected_ivr_file',
		'flosc_import_selected_ivr_file' => 'Apply',
		'import_ivr_file'                => 'other_ivr.md',
	),
	true,
	'import',
	'other_ivr.md',
	false
);

flosc_row(
	'valid delete form',
	'POST',
	array(
		'_wpnonce'               => 'valid:flosc_delete_ivr_file',
		'flosc_delete_ivr_file'  => 'Delete',
		'delete_ivr_file'        => 'gone_ivr.md',
	),
	true,
	'delete',
	'gone_ivr.md',
	false
);

flosc_row(
	'button only, no filename',
	'POST',
	array(
		'_wpnonce'                 => 'valid:flosc_duplicate_ivr_file',
		'flosc_duplicate_ivr_file' => 'Duplicate',
	),
	true,
	'',
	'',
	false
);

flosc_row(
	'filename only, no button',
	'POST',
	array(
		'_wpnonce'           => 'valid:flosc_duplicate_ivr_file',
		'duplicate_ivr_file' => 'dainis_net_ivr.md',
	),
	true,
	'',
	'',
	false
);

echo "\nA real submission with a stale nonce is rejected, not ignored\n\n";

flosc_row(
	'button + filename + bad nonce',
	'POST',
	array(
		'_wpnonce'                 => 'stale',
		'flosc_duplicate_ivr_file' => 'Duplicate',
		'duplicate_ivr_file'       => 'dainis_net_ivr.md',
	),
	true,
	'',
	'',
	true
);

flosc_row(
	'button + filename + no nonce at all',
	'POST',
	array(
		'flosc_delete_ivr_file' => 'Delete',
		'delete_ivr_file'       => 'gone_ivr.md',
	),
	true,
	'',
	'',
	true
);

echo "\nCapability and filename shape\n\n";

flosc_row( 'valid nonce, no capability', 'POST', $flosc_dup, false, '', '', false );

flosc_row(
	'non-scalar filename',
	'POST',
	array(
		'_wpnonce'                 => 'valid:flosc_duplicate_ivr_file',
		'flosc_duplicate_ivr_file' => 'Duplicate',
		'duplicate_ivr_file'       => array( 'a', 'b' ),
	),
	true,
	'duplicate',
	'',
	false
);

flosc_row(
	'filename carrying a path is reduced to a basename',
	'POST',
	array(
		'_wpnonce'                 => 'valid:flosc_duplicate_ivr_file',
		'flosc_duplicate_ivr_file' => 'Duplicate',
		'duplicate_ivr_file'       => '/etc/passwd',
	),
	true,
	'duplicate',
	'passwd',
	false
);

flosc_row(
	'a nonce valid for one action does not unlock another',
	'POST',
	array(
		'_wpnonce'              => 'valid:flosc_duplicate_ivr_file',
		'flosc_delete_ivr_file' => 'Delete',
		'delete_ivr_file'       => 'gone_ivr.md',
	),
	true,
	'',
	'',
	true
);

echo $flosc_fail ? "\n$flosc_fail FAILURES\n" : "\nEvery request shape behaves as the contract says\n";
exit( $flosc_fail ? 1 : 0 );
