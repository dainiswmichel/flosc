<?php
/**
 * Two things v146 got wrong, asserted so they cannot regress silently.
 *
 * 1. One tab writes one group. The flow editor has three forms that all post
 *    to handle_save_flow_record(). update_flow() merges with array_merge(), so
 *    any key present in the payload overwrites the stored value. v146 built
 *    every key unconditionally, which made saving the IVR tab blank the slug,
 *    the custom domain and the whole identity array and drop the flow to
 *    draft. Each group must therefore be built only when its own form posted
 *    it.
 *
 * 2. A nonce names its target. v146 replaced three action strings that carried
 *    the id with fixed ones, so one link verified against any knowledge-base
 *    file and any pending user. The generic action stays -- it is the early,
 *    scanner-visible origin proof -- and a second nonce names the target.
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

$flosc_admin = flosc_tabscope_read( 'includes/flosc-admin.php' );
$flosc_edit  = flosc_tabscope_read( 'admin/flow-edit.php' );
$flosc_fw    = flosc_tabscope_read( 'includes/class-flosc-framework.php' );
$flosc_kb    = flosc_tabscope_read( 'admin/knowledge-base.php' );
$flosc_kbh   = flosc_tabscope_read( 'includes/flosc-knowledge-bases.php' );
$flosc_magic = flosc_tabscope_read( 'includes/magic-link/class-flosc-magic-link-trait.php' );

echo "One tab writes one group\n";

// The handler body only, so a match elsewhere in the file cannot satisfy
// these. The slice ends at the next method's docblock rather than at a fixed
// length, because a fixed length silently stops testing when the body grows.
$flosc_start = strpos( $flosc_admin, 'public function handle_save_flow_record()' );
$flosc_body  = '';
if ( false !== $flosc_start ) {
	$flosc_next  = strpos( $flosc_admin, "\n\t/**", $flosc_start );
	$flosc_body  = false === $flosc_next
		? substr( $flosc_admin, $flosc_start )
		: substr( $flosc_admin, $flosc_start, $flosc_next - $flosc_start );
}

flosc_tabscope_assert( 'handle_save_flow_record() was found', '' !== $flosc_body );
flosc_tabscope_assert(
	'the payload starts empty, so an absent key keeps its stored value',
	false !== strpos( $flosc_body, '$data = array();' )
);
flosc_tabscope_assert(
	'no unconditional payload literal carrying slug',
	false === strpos( $flosc_body, "\$data = array(\n\t\t\t'slug'" )
);

$flosc_groups = array(
	'identity group is guarded by its own fields' => "isset( \$post['flow_slug'] )",
	'ivr_file is guarded by its own field'        => "if ( \$is_new || isset( \$post['ivr_file'] ) ) {",
	'wp_category is guarded by its own field'     => "if ( \$is_new || isset( \$post['wp_category'] ) ) {",
	'quiz_type is guarded by its own field'       => "if ( \$is_new || isset( \$post['quiz_type'] ) ) {",
);
foreach ( $flosc_groups as $flosc_label => $flosc_needle ) {
	flosc_tabscope_assert( $flosc_label, false !== strpos( $flosc_body, $flosc_needle ) );
}

// The three forms are why the guards exist. If a fourth appears, or the
// partial ones start carrying the identity fields, this test has to be reread.
flosc_tabscope_assert(
	'flow-edit.php still posts to that handler from three forms',
	3 === substr_count( $flosc_edit, "value=\"flosc_save_flow_record\"" )
);

echo "\nA nonce names its target\n";

flosc_tabscope_assert(
	'one helper builds the knowledge-base target action',
	false !== strpos( $flosc_kbh, 'function flosc_kb_target_nonce_action(' )
);
foreach ( array( 'flosc_kb_toggle', 'flosc_kb_delete' ) as $flosc_action ) {
	flosc_tabscope_assert(
		$flosc_action . ' keeps the generic action as its early origin proof',
		false !== strpos( $flosc_fw, "check_admin_referer( '" . $flosc_action . "' )" )
	);
	flosc_tabscope_assert(
		$flosc_action . ' also verifies a nonce naming the base and the file',
		false !== strpos( $flosc_fw, "flosc_kb_target_nonce_action( '" . $flosc_action . "', \$kb_id, \$file )" )
	);
	flosc_tabscope_assert(
		$flosc_action . ' link mints that same target nonce',
		false !== strpos( $flosc_kb, "wp_create_nonce( flosc_kb_target_nonce_action( '" . $flosc_action . "'" )
	);
}

flosc_tabscope_assert(
	'email activation keeps the generic action as its early origin proof',
	false !== strpos( $flosc_magic, "check_admin_referer( 'flosc_activate_email_account' )" )
);
flosc_tabscope_assert(
	'email activation also verifies a nonce naming the user',
	false !== strpos( $flosc_magic, "'flosc_activate_email_account_' . \$user_id" )
);
flosc_tabscope_assert(
	'the activate link mints that same per-user nonce',
	false !== strpos( $flosc_magic, "wp_create_nonce( 'flosc_activate_email_account_' . (int) \$user->ID )" )
);
flosc_tabscope_assert(
	'the user id is validated as a positive integer before the action is named',
	false !== strpos( $flosc_magic, "'options' => array( 'min_range' => 1 )" )
);

echo "\nNo class-body method shadows a trait handler with a different arity\n";

/*
 * FLOSC_Framework does `use FLOSC_Admin_Trait`, and PHP lets a method declared
 * in the class body silently override a trait method of the same name. When the
 * two take different numbers of required arguments and the trait's version is
 * what a hook is bound to, the override is a fatal: the hook calls with the
 * trait's argument count and the class-body method demands its own.
 *
 * That is what happened to handle_delete_feedback() and handle_delete_praise().
 * Both were declared twice, the class-body REST versions won, and the Delete
 * buttons on the AI Feedback screen raised ArgumentCountError while the trait's
 * nonce-checked handlers sat unreachable. Same-arity twins are harmless and are
 * allowed through; a differing arity is not.
 */
$flosc_decls = static function ( $source ) {
	$found = array();
	if ( preg_match_all( '/^\t(?:public |private |protected |static |final )*function\s+([A-Za-z0-9_]+)\s*\(([^)]*)\)/m', $source, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $one ) {
			if ( isset( $found[ $one[1] ] ) ) {
				continue;
			}
			$required = 0;
			foreach ( explode( ',', $one[2] ) as $param ) {
				$param = trim( $param );
				if ( '' !== $param && false === strpos( $param, '=' ) ) {
					++$required;
				}
			}
			$found[ $one[1] ] = $required;
		}
	}
	return $found;
};

$flosc_class_methods = $flosc_decls( $flosc_fw );
$flosc_trait_methods = $flosc_decls( $flosc_admin );
$flosc_arity_clash   = array();
foreach ( $flosc_class_methods as $flosc_name => $flosc_required ) {
	if ( isset( $flosc_trait_methods[ $flosc_name ] ) && $flosc_trait_methods[ $flosc_name ] !== $flosc_required ) {
		$flosc_arity_clash[] = $flosc_name;
	}
}
flosc_tabscope_assert(
	'no trait method is shadowed by a class-body method of different arity',
	array() === $flosc_arity_clash
);
flosc_tabscope_assert(
	'the feedback and praise delete hooks reach the trait handlers that check the nonce',
	false === strpos( $flosc_fw, 'function handle_delete_feedback(' )
		&& false === strpos( $flosc_fw, 'function handle_delete_praise(' )
		&& false !== strpos( $flosc_admin, 'function handle_delete_feedback()' )
		&& false !== strpos( $flosc_admin, 'function handle_delete_praise()' )
);

echo "\nThe tier and depth selects escape where they print\n";

flosc_tabscope_assert(
	'a render twin prints the options',
	false !== strpos( flosc_tabscope_read( 'admin/settings-helper.php' ), 'function flosc_vgm_options_render(' )
);
$flosc_suppressed = 0;
foreach ( array( 'admin/content.php', 'admin/member-levels.php', 'includes/class-flosc-framework.php', 'includes/class-flosc-site-content-index.php' ) as $flosc_rel ) {
	$flosc_suppressed += substr_count( flosc_tabscope_read( $flosc_rel ), 'built escaped in flosc_vgm_options_markup()' );
}
flosc_tabscope_assert(
	'and no call site carries an EscapeOutput suppression for it',
	0 === $flosc_suppressed
);

echo "\n";
if ( $flosc_failures ) {
	printf( "%d FAILURES\n", count( $flosc_failures ) );
	exit( 1 );
}
echo "tab scope and nonce binding hold\n";
exit( 0 );
