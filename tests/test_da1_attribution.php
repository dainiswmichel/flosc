<?php
/**
 * DA1 attributes catalogs to flows, and only to flows.
 *
 * FLOSC's own backups of a flow live beside it as *_ivr_bak_*.md and
 * ivr-backup-*.md. An earlier form of the v8 catalog migration walked every
 * .md in the data directory and pinned a catalog to each, so those backup
 * filenames were stored as attributions and DA1 displayed them under
 * "Attributed to" — names the operator had never made and Switch Flow would
 * not show. This is the rule that stops that recurring.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );

function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $n ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function __( $t, $d = null ) { return $t; }
function trailingslashit( $p ) { return rtrim( (string) $p, '/\\' ) . '/'; }

require_once __DIR__ . '/../includes/filesystem/flosc-data-paths.php';

$fail = 0;
function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-56s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

$stored = array(
	'dainis_net_ivr.md'                                  => array( 'default' ),
	'vlkit_ivr.md'                                       => array( 'default', 'recipes' ),
	'dainis_net_ivr_bak_companion_hubs_20260807.md'      => array( 'default' ),
	'flosc_ai_ivr_bak_founders_offer_20260807.md'        => array( 'default' ),
	'ivr-backup-2026-07-03_05-48-20.md'                  => array( 'default' ),
	'notes.md'                                           => array( 'default' ),
);

$clean = flosc_da1_prune_flow_assignments( $stored );

echo "Real flows are kept\n";
ok( 'a flow keeps its catalog', $clean['dainis_net_ivr.md'], array( 'default' ) );
ok( '  including one with several', $clean['vlkit_ivr.md'], array( 'default', 'recipes' ) );

echo "Backups are not flows and never were\n";
ok( 'a dated backup of a flow is dropped', isset( $clean['dainis_net_ivr_bak_companion_hubs_20260807.md'] ), false );
ok( '  a second one too', isset( $clean['flosc_ai_ivr_bak_founders_offer_20260807.md'] ), false );
ok( '  and the ivr-backup- form', isset( $clean['ivr-backup-2026-07-03_05-48-20.md'] ), false );
ok( '  a stray .md that is not a flow at all', isset( $clean['notes.md'] ), false );
ok( 'so only the two real flows are attributed', count( $clean ), 2 );

echo "It prunes, and does nothing else\n";
$already_clean = array( 'dainis_net_ivr.md' => array( 'default' ) );
ok( 'a clean list comes back identical', flosc_da1_prune_flow_assignments( $already_clean ), $already_clean );
ok( '  an empty list stays empty', flosc_da1_prune_flow_assignments( array() ), array() );
ok( '  a corrupt option does not fatal', flosc_da1_prune_flow_assignments( 'not an array' ), array() );
ok( '  a flow whose catalogs are not a list is dropped, not guessed at',
	flosc_da1_prune_flow_assignments( array( 'a_ivr.md' => 'default' ) ), array() );
ok( '  a flow left with no catalogs is not stored as empty',
	flosc_da1_prune_flow_assignments( array( 'a_ivr.md' => array( '', null ) ) ), array() );
ok( '  duplicate catalog slugs collapse', flosc_da1_prune_flow_assignments(
	array( 'a_ivr.md' => array( 'default', 'default' ) ) ), array( 'a_ivr.md' => array( 'default' ) ) );
ok( 'a path cannot climb out of the flow directory',
	isset( flosc_da1_prune_flow_assignments( array( '../../wp-config_ivr.md' => array( 'default' ) ) )['../../wp-config_ivr.md'] ),
	false );

echo $fail ? "\n$fail FAILURES\n" : "\nDA1 attribution: all checks passed\n";
exit( $fail ? 1 : 0 );
