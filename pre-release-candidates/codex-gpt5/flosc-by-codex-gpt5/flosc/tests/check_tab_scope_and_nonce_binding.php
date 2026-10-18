<?php
/**
 * Properties that past iterations broke, asserted so they cannot regress.
 *
 * Each assertion names a GUARANTEE, not an implementation. An earlier version
 * of this file asserted the shape of one candidate's code -- a named helper, a
 * named render twin, guards inside one particular method -- and then failed
 * against a different candidate that met every guarantee by other means. A
 * gate that fails on a correct rewrite is a gate nobody trusts.
 *
 * 1. One tab writes one group. The flow editor has three forms that all post
 *    to the same handler. update_flow() merges with array_merge(), so any key
 *    present in the payload overwrites the stored value. v146 built every key
 *    unconditionally, which made saving the IVR tab blank the slug, the custom
 *    domain and the whole identity array and drop the flow to draft.
 *
 * 2. A nonce names its target. v146 replaced three action strings that carried
 *    an id with fixed ones, so one link verified against any knowledge-base
 *    file and any pending user. The generic action stays -- it is the early,
 *    scanner-visible origin proof -- and a second nonce names the target. The
 *    emitter and the verifier must agree on that action, whether they share a
 *    helper or repeat the concatenation.
 *
 * 3. Variables are escaped where they print, with no suppression standing in
 *    for the escaping.
 *
 * 4. No class-body method shadows a trait method of a different arity. PHP
 *    resolves the class body silently, and do_action() passes '' when called
 *    with no argument, so a hooked callback needing a parameter fatals before
 *    its body runs.
 *
 * 5. No request value reaches sanitize_file_name() without its shape
 *    established. That function opens at remove_accents(), which calls
 *    preg_match(); an array there is a TypeError, and isset() does not refuse
 *    one.
 *
 * Source-only. tests/ is excluded from the distribution zip.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$flosc_root     = dirname( __DIR__ );
$flosc_failures = array();

/**
 * Read one shipped file.
 *
 * @param string $relative Path below the plugin root.
 * @return string
 */
function flosc_tabscope_read( $relative ) {
	global $flosc_root;
	$path = $flosc_root . '/' . $relative;
	if ( ! is_file( $path ) ) {
		return '';
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local source in a CLI-only audit.
	return (string) file_get_contents( $path );
}

/**
 * Record an assertion.
 *
 * @param string $label  What is being asserted.
 * @param bool   $passed Result.
 * @return void
 */
function flosc_tabscope_assert( $label, $passed ) {
	global $flosc_failures;
	printf( "%-6s %s\n", $passed ? 'ok' : 'FAIL', $label );
	if ( ! $passed ) {
		$flosc_failures[] = $label;
	}
}

/**
 * The body of the function or method that contains a given string.
 *
 * Finds the nearest declaration above the needle and stops at the next one, so
 * a candidate may move code between methods without failing the assertion.
 *
 * @param string $source Whole file.
 * @param string $needle A string inside the wanted body.
 * @return string Body, or '' when the needle is absent.
 */
function flosc_tabscope_body( $source, $needle ) {
	$at = strpos( $source, $needle );
	if ( false === $at ) {
		return '';
	}
	if ( ! preg_match_all( '/\n(?:\t)?(?:public |private |protected )?function [a-z0-9_]+\(/i', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}
	$start = 0;
	$end   = strlen( $source );
	foreach ( $m[0] as $one ) {
		// A declaration whose own text contains the needle still opens the body.
		$head = $one[1] + strlen( $one[0] );
		if ( $one[1] <= $at && $head >= $at ) {
			$start = $one[1];
			continue;
		}
		if ( $one[1] < $at && $one[1] > $start ) {
			$start = $one[1];
		}
	}
	foreach ( $m[0] as $one ) {
		if ( $one[1] > $start && $one[1] < $end && $one[1] + strlen( $one[0] ) > $at ) {
			$end = $one[1];
		}
	}
	return substr( $source, $start, $end - $start );
}

/**
 * The literal prefix of the first concatenated nonce action after an anchor.
 *
 * Returns the quoted literal in "'flosc_thing_' . $id" so the action a handler
 * verifies can be compared with the action its link mints, without requiring
 * either side to use a particular helper.
 *
 * @param string $source Whole file.
 * @param string $anchor Text just before the call.
 * @param string $field  The nonce field name both sides must name.
 * @return string Literal prefix, or '' when not found.
 */
function flosc_tabscope_bound_action( $source, $anchor, $field ) {
	$at = strpos( $source, $anchor );
	if ( false === $at ) {
		return '';
	}
	$window = substr( $source, $at, 4000 );
	if ( false === strpos( $window, $field ) ) {
		return '';
	}
	if ( ! preg_match( "/'([a-z0-9_]+_)'\s*\.\s*/i", $window, $m ) ) {
		return '';
	}
	return $m[1];
}

$flosc_admin = flosc_tabscope_read( 'includes/flosc-admin.php' );
$flosc_edit  = flosc_tabscope_read( 'admin/flow-edit.php' );
$flosc_fw    = flosc_tabscope_read( 'includes/class-flosc-framework.php' );
$flosc_kb    = flosc_tabscope_read( 'admin/knowledge-base.php' );
$flosc_magic = flosc_tabscope_read( 'includes/magic-link/class-flosc-magic-link-trait.php' );

echo "One tab writes one group\n";

// Whichever method builds the payload, found by a key only it assigns.
$flosc_body = flosc_tabscope_body( $flosc_admin, "'wp_category_id'" );

flosc_tabscope_assert( 'the flow payload builder was found', '' !== $flosc_body );
flosc_tabscope_assert(
	'the payload starts empty, so an absent key keeps its stored value',
	(bool) preg_match( '/\$\w+\s*=\s*array\(\s*\)\s*;/', $flosc_body )
);

// Each group is written only when its own form posted a field of that group.
$flosc_groups = array(
	'the identity group is guarded by its own fields' => array( "'slug'", array( 'flow_slug', 'floscflow_name', 'flow_status', 'flow_domain' ) ),
	'ivr_file is guarded by its own field'            => array( "'ivr_file'", array( 'ivr_file' ) ),
	'wp_category is guarded by its own field'         => array( "'wp_category_id'", array( 'wp_category' ) ),
	'quiz_type is guarded by its own field'           => array( "'quiz_type'", array( 'quiz_type' ) ),
);
foreach ( $flosc_groups as $flosc_label => $flosc_spec ) {
	list( $flosc_key, $flosc_fields ) = $flosc_spec;

	// The last mention of the key is the assignment; earlier ones are guards.
	$flosc_assign = strrpos( $flosc_body, '[ ' . $flosc_key . ' ]' );
	if ( false === $flosc_assign ) {
		$flosc_assign = strrpos( $flosc_body, '[' . $flosc_key . ']' );
	}
	$flosc_guarded = false;
	if ( false !== $flosc_assign ) {
		$flosc_window = substr( $flosc_body, max( 0, $flosc_assign - 600 ), min( 600, $flosc_assign ) );
		foreach ( $flosc_fields as $flosc_field ) {
			if ( false !== strpos( $flosc_window, "isset( \$post['" . $flosc_field . "'] )" ) ) {
				$flosc_guarded = true;
				break;
			}
		}
	}
	flosc_tabscope_assert( $flosc_label, $flosc_guarded );
}

flosc_tabscope_assert(
	'flow-edit.php still posts to that handler from three forms',
	3 <= substr_count( $flosc_edit, 'flosc_save_flow_record' )
);

echo "\nA nonce names its target\n";

/*
 * Three controls whose link must not work on a different object. For each:
 * the generic action is verified before the request is read, a second nonce
 * carries the target, and the link mints the same action under the same field
 * name. The prefixes are compared rather than asserted literally, so the two
 * sides are checked against each other instead of against this file.
 */
$flosc_bound = array(
	'flosc_kb_toggle' => array(
		'handler'        => array( $flosc_fw, 'function handle_kb_toggle', 'flosc_kb_toggle_target_nonce' ),
		'emitter'        => array( $flosc_kb, 'action=flosc_kb_toggle', 'flosc_kb_toggle_target_nonce' ),
		'generic'        => "check_admin_referer( 'flosc_kb_toggle' )",
		'reads'          => '$_GET',
	),
	'flosc_kb_delete' => array(
		'handler'        => array( $flosc_fw, 'function handle_kb_delete', 'flosc_kb_delete_target_nonce' ),
		'emitter'        => array( $flosc_kb, 'action=flosc_kb_delete', 'flosc_kb_delete_target_nonce' ),
		'generic'        => "check_admin_referer( 'flosc_kb_delete' )",
		'reads'          => '$_GET',
	),
	'email activation' => array(
		'handler'        => array( $flosc_magic, 'function handle_admin_activate_email_account', 'flosc_activate_email_target_nonce' ),
		'emitter'        => array( $flosc_magic, 'action=flosc_activate_email_account', 'flosc_activate_email_target_nonce' ),
		'generic'        => "check_admin_referer( 'flosc_activate_email_account' )",
		'reads'          => 'INPUT_GET',
	),
);
foreach ( $flosc_bound as $flosc_name => $flosc_spec ) {
	list( $flosc_hsrc, $flosc_hanchor, $flosc_field ) = $flosc_spec['handler'];
	list( $flosc_esrc, $flosc_eanchor )               = $flosc_spec['emitter'];

	$flosc_hbody = flosc_tabscope_body( $flosc_hsrc, $flosc_hanchor );

	// The generic check is the origin proof and must precede the read.
	$flosc_generic_at = strpos( $flosc_hbody, $flosc_spec['generic'] );
	$flosc_read_at    = strpos( $flosc_hbody, $flosc_spec['reads'] );
	flosc_tabscope_assert(
		$flosc_name . ' verifies its generic action before it reads the request',
		false !== $flosc_generic_at && ( false === $flosc_read_at || $flosc_generic_at < $flosc_read_at )
	);

	// A capability check of its own, not folded into the nonce condition.
	flosc_tabscope_assert(
		$flosc_name . ' checks a capability as its own statement',
		(bool) preg_match( '/if \(\s*! current_user_can\( \'[a-z_]+\' \) \) \{/', $flosc_hbody )
	);

	$flosc_hprefix = flosc_tabscope_bound_action( $flosc_hbody, 'check_admin_referer', $flosc_field );
	$flosc_eprefix = flosc_tabscope_bound_action( $flosc_esrc, $flosc_eanchor, $flosc_field );

	flosc_tabscope_assert(
		$flosc_name . ' verifies a second nonce whose action carries the target',
		'' !== $flosc_hprefix
	);
	flosc_tabscope_assert(
		$flosc_name . ' mints that same action under that same field (' . $flosc_hprefix . ' vs ' . $flosc_eprefix . ')',
		'' !== $flosc_hprefix && $flosc_hprefix === $flosc_eprefix
	);
}

flosc_tabscope_assert(
	'the user id is validated as a positive integer before the action is named',
	false !== strpos( $flosc_magic, "'options' => array( 'min_range' => 1 )" )
		&& false !== strpos( $flosc_magic, 'get_userdata( $user_id )' )
);

echo "\nEvery offer-list control names the offer it acts on\n";

/*
 * A nonce proves the request came from a form this user was served. Without
 * the offer id in the action it cannot also prove WHICH row that form was
 * for, so one link minted for one row verified for every row on the screen.
 * set_status also carries the requested status, because the link encodes the
 * outcome: a link minted to set draft must not verify a request setting active.
 *
 * One helper builds the action for both the link and the handler, so the two
 * cannot drift. The assertion is that no bare action string survives anywhere.
 */
$flosc_offers_src  = flosc_tabscope_read( 'admin/offers.php' );
$flosc_request_src = flosc_tabscope_read( 'includes/flosc-request.php' );

flosc_tabscope_assert(
	'one helper builds every offer-control nonce action',
	false !== strpos( $flosc_request_src, 'function flosc_offer_action_nonce_action(' )
);

$flosc_bare = array();
foreach ( array( 'flosc_set_status', 'flosc_toggle_status' ) as $flosc_bare_action ) {
	if ( false !== strpos( $flosc_offers_src, "wp_create_nonce( '" . $flosc_bare_action . "' )" )
		|| false !== strpos( $flosc_admin, "'" . $flosc_bare_action . "'" ) ) {
		$flosc_bare[] = $flosc_bare_action;
	}
}
flosc_tabscope_assert(
	'no offer control mints or verifies an unbound action'
		. ( $flosc_bare ? ' -- ' . implode( ', ', $flosc_bare ) : '' ),
	array() === $flosc_bare
);
flosc_tabscope_assert(
	'every offer link mints its action through that helper',
	8 === substr_count( $flosc_offers_src, 'flosc_offer_action_nonce_action(' )
);
flosc_tabscope_assert(
	'and the handler verifies the action that helper returns',
	false !== strpos( $flosc_admin, '$flosc_nonce_action = flosc_offer_action_nonce_action(' )
		&& false !== strpos( $flosc_admin, '! wp_verify_nonce( $flosc_nonce, $flosc_nonce_action )' )
);

echo "\nSubmission detection is not folded into nonce verification\n";

/*
 * "Did this form post?" is routing. "Is the request genuine?" is security.
 * Sharing one condition made a page that posted nothing and a forged save
 * indistinguishable, both returning in silence. The reviewer's note is
 * explicit that compound conditions around nonces are where bypasses hide.
 */
$flosc_compound = array();
foreach ( array( 'admin/settings.php', 'admin/offers.php', 'admin/autoprompts.php' ) as $flosc_rel ) {
	foreach ( explode( "\n", flosc_tabscope_read( $flosc_rel ) ) as $flosc_n => $flosc_line ) {
		if ( ! preg_match( '/(wp_verify_nonce|check_admin_referer)/', $flosc_line ) ) {
			continue;
		}
		if ( ! preg_match( '/isset\(/', $flosc_line ) ) {
			continue;
		}
		// isset() on the nonce field itself is not submission detection.
		if ( preg_match( '/isset\(\s*\$\w+\[\s*.[a-z_]*nonce/i', $flosc_line ) ) {
			continue;
		}
		$flosc_compound[] = $flosc_rel . ':' . ( $flosc_n + 1 );
	}
}
flosc_tabscope_assert(
	'no handler mixes an isset() submission test into its nonce condition'
		. ( $flosc_compound ? ' -- ' . implode( ', ', $flosc_compound ) : '' ),
	array() === $flosc_compound
);

echo "\nNo class-body method shadows a trait handler with a different arity\n";

/**
 * Method name to parameter count for every declaration in a source string.
 *
 * @param string $source Whole file.
 * @return array<string,int>
 */
$flosc_decls = static function ( $source ) {
	$out = array();
	if ( preg_match_all( '/\n\t(?:public |private |protected )?function ([a-z0-9_]+)\(([^)]*)\)/i', $source, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $one ) {
			$args         = trim( $one[2] );
			$out[ $one[1] ] = '' === $args ? 0 : count( explode( ',', $args ) );
		}
	}
	return $out;
};

$flosc_class_methods = $flosc_decls( $flosc_fw );
$flosc_trait_methods = $flosc_decls( $flosc_admin );
$flosc_arity_clash   = array();
foreach ( $flosc_trait_methods as $flosc_method => $flosc_arity ) {
	if ( isset( $flosc_class_methods[ $flosc_method ] ) && $flosc_class_methods[ $flosc_method ] !== $flosc_arity ) {
		$flosc_arity_clash[] = $flosc_method . '(' . $flosc_arity . ' vs ' . $flosc_class_methods[ $flosc_method ] . ')';
	}
}
flosc_tabscope_assert(
	'no trait method is shadowed by a class-body method of different arity'
		. ( $flosc_arity_clash ? ' -- ' . implode( ', ', $flosc_arity_clash ) : '' ),
	array() === $flosc_arity_clash
);
flosc_tabscope_assert(
	'the feedback and praise delete hooks reach the trait handlers that check the nonce',
	false !== strpos( $flosc_admin, 'function handle_delete_feedback()' )
		&& false !== strpos( $flosc_admin, 'function handle_delete_praise()' )
);

echo "\nThe tier and depth selects escape where they print\n";

/*
 * The builder returns an <option> list as a string. Whether a candidate
 * escapes it with wp_kses() and an allowed-HTML list, or prints it through a
 * render twin, the requirement is the same: nothing echoes it raw, and no
 * suppression stands in for the escaping. wp_json_encode() counts: the four
 * sites that use it assign the markup to a JS string literal inside a script
 * block, which is that context's escaping.
 */
$flosc_raw_echo    = 0;
$flosc_suppressed  = 0;
$flosc_print_sites = array( 'admin/content.php', 'admin/member-levels.php', 'includes/class-flosc-framework.php', 'includes/class-flosc-site-content-index.php' );
foreach ( $flosc_print_sites as $flosc_rel ) {
	$flosc_src = flosc_tabscope_read( $flosc_rel );
	foreach ( explode( "\n", $flosc_src ) as $flosc_line ) {
		if ( false === strpos( $flosc_line, 'flosc_vgm_options_markup(' ) ) {
			continue;
		}
		if ( false === strpos( $flosc_line, 'echo' ) ) {
			continue;
		}
		if ( ! preg_match( '/echo\s+(wp_kses|wp_kses_post|esc_html|esc_attr|wp_json_encode)\(/', $flosc_line ) ) {
			++$flosc_raw_echo;
		}
	}
	$flosc_suppressed += preg_match_all( '/phpcs:(ignore|disable).*EscapeOutput/', $flosc_src, $flosc_ignored );
}
flosc_tabscope_assert(
	'every echo of the option markup escapes it at the echo',
	0 === $flosc_raw_echo
);
flosc_tabscope_assert(
	'and no print site carries an EscapeOutput suppression',
	0 === $flosc_suppressed
);

echo "\nNeither review sniff is suppressed anywhere in the shipping code\n";

/*
 * The two sniffs this review is about are ValidatedSanitizedInput and
 * EscapeOutput. A phpcs:ignore for either one is a claim a reviewer has to
 * take on trust, scattered wherever it was convenient. There are none: the
 * project declares its five sanitizers once in phpcs.xml.dist, where one list
 * is read instead of hunting annotations, and renaming every annotation in the
 * tree therefore changes nothing either sniff reports.
 *
 * Measured on this tree: the project ruleset reports 0 errors for both sniffs,
 * and 0 again with annotations disabled. The bare WordPress standard reports 5
 * for them, because it discards customSanitizingFunctions -- that difference is
 * the declarations being dropped, not code changing.
 */
$flosc_suppressions = array();
$flosc_walk         = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flosc_root ) );
foreach ( $flosc_walk as $flosc_f ) {
	if ( ! $flosc_f->isFile() || 'php' !== $flosc_f->getExtension() ) {
		continue;
	}
	$flosc_p = str_replace( '\\', '/', $flosc_f->getPathname() );
	if ( false !== strpos( $flosc_p, '/tests/' ) || false !== strpos( $flosc_p, '/vendor/' ) ) {
		continue;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local source in a CLI-only audit.
	foreach ( explode( "\n", (string) file_get_contents( $flosc_p ) ) as $flosc_n => $flosc_l ) {
		if ( preg_match( '/phpcs:(ignore|disable)[^\n]*(ValidatedSanitizedInput|EscapeOutput)/', $flosc_l ) ) {
			$flosc_suppressions[] = str_replace( $flosc_root . '/', '', $flosc_f->getPathname() ) . ':' . ( $flosc_n + 1 );
		}
	}
}
flosc_tabscope_assert(
	'no shipping file suppresses the sanitization or escaping sniff'
		. ( $flosc_suppressions ? ' -- ' . implode( ', ', $flosc_suppressions ) : '' ),
	array() === $flosc_suppressions
);

// The five declarations are the one place that claim is made, so each must name
// a function that exists in the shipping tree.
$flosc_ruleset = flosc_tabscope_read( 'phpcs.xml.dist' );
preg_match_all( '/<element value="(flosc_[a-z_]+)"\/>/', $flosc_ruleset, $flosc_declared );
$flosc_missing = array();
foreach ( array_unique( $flosc_declared[1] ) as $flosc_fn ) {
	$flosc_found = false;
	$flosc_walk2 = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flosc_root ) );
	foreach ( $flosc_walk2 as $flosc_f2 ) {
		if ( ! $flosc_f2->isFile() || 'php' !== $flosc_f2->getExtension() ) {
			continue;
		}
		if ( false !== strpos( str_replace( '\\', '/', $flosc_f2->getPathname() ), '/tests/' ) ) {
			continue;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local source in a CLI-only audit.
		if ( false !== strpos( (string) file_get_contents( $flosc_f2->getPathname() ), 'function ' . $flosc_fn . '(' ) ) {
			$flosc_found = true;
			break;
		}
	}
	if ( ! $flosc_found ) {
		$flosc_missing[] = $flosc_fn;
	}
}
flosc_tabscope_assert(
	'every function the ruleset credits as a sanitizer exists in the shipping tree'
		. ( $flosc_missing ? ' -- ' . implode( ', ', $flosc_missing ) : '' ),
	array() === $flosc_missing
);

echo "\nEvery request value reaching sanitize_file_name() has its shape established\n";

/*
 * sanitize_file_name() opens at remove_accents(), which calls preg_match() on
 * its argument. An array there is a TypeError, so ?ivr[]=x is a fatal rather
 * than an empty string, and isset() does not refuse it. Measured on PHP 8.3:
 * "preg_match(): Argument #2 ($subject) must be of type string, array given".
 *
 * A shape check is is_string(), is_scalar() -- an array fails both -- or a
 * (string) cast on the call itself. Three lines of lookback covers the
 * multi-line ternaries this codebase uses.
 */
$flosc_unguarded = array();
$flosc_iter      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $flosc_root ) );
foreach ( $flosc_iter as $flosc_file ) {
	if ( ! $flosc_file->isFile() || 'php' !== $flosc_file->getExtension() ) {
		continue;
	}
	$flosc_path = str_replace( '\\', '/', $flosc_file->getPathname() );
	if ( false !== strpos( $flosc_path, '/tests/' ) || false !== strpos( $flosc_path, '/vendor/' ) ) {
		continue;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local source in a CLI-only audit.
	$flosc_lines = explode( "\n", (string) file_get_contents( $flosc_path ) );
	foreach ( $flosc_lines as $flosc_i => $flosc_line ) {
		if ( false === strpos( $flosc_line, 'sanitize_file_name(' ) ) {
			continue;
		}
		if ( ! preg_match( '/\$_(POST|GET|REQUEST|FILES)\b/', $flosc_line ) ) {
			continue;
		}
		$flosc_ctx = implode( "\n", array_slice( $flosc_lines, max( 0, $flosc_i - 3 ), 4 ) );
		if ( false !== strpos( $flosc_ctx, 'is_string' )
			|| false !== strpos( $flosc_ctx, 'is_scalar' )
			|| false !== strpos( $flosc_line, '(string)' ) ) {
			continue;
		}
		$flosc_unguarded[] = str_replace( $flosc_root . '/', '', $flosc_file->getPathname() ) . ':' . ( $flosc_i + 1 );
	}
}
flosc_tabscope_assert(
	'no shipping call passes a request value to sanitize_file_name() unchecked'
		. ( $flosc_unguarded ? ' -- ' . implode( ', ', $flosc_unguarded ) : '' ),
	array() === $flosc_unguarded
);

echo "\n";
if ( $flosc_failures ) {
	printf( "%d FAILURES\n", count( $flosc_failures ) );
	exit( 1 );
}
echo "tab scope and nonce binding hold\n";
exit( 0 );
