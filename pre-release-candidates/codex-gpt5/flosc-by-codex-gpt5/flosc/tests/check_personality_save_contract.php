<?php
/**
 * Personality create, edit, and attach must save the document runtime uses.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

/**
 * Print one contract result.
 *
 * @param string $label Assertion label.
 * @param bool   $pass  Whether it passed.
 * @return void
 */
function flosc_personality_save_ok( $label, $pass ) {
	global $fail;
	if ( ! $pass ) {
		++$fail;
	}
	echo ( $pass ? 'ok   ' : 'FAIL ' ) . $label . "\n";
}

$bridge  = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder-wp.js' );
$builder = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder.js' );
$library = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
$main    = (string) file_get_contents( $root . '/flosc.php' );
$runtime = (string) file_get_contents( $root . '/includes/flosc-flow-runtime.php' );

echo "The library stores the runtime profile, not the export footer\n";
flosc_personality_save_ok( 'the bridge has one explicit runtime-profile boundary', strpos( $bridge, 'function runtimeProfile(api)' ) !== false );
flosc_personality_save_ok( 'ordinary edits use that boundary', strpos( $bridge, 'var profile = runtimeProfile(api);' ) !== false );
flosc_personality_save_ok( 'the WordPress bridge never calls promptFile()', strpos( $bridge, 'api.promptFile(' ) === false );
flosc_personality_save_ok( 'new rows use libraryEntry.ai_base_prompt', strpos( $builder, 'const profile = entry.ai_base_prompt;' ) !== false );

echo "\nCreation saves the complete new row\n";
flosc_personality_save_ok( 'the new preset snapshots its computed library entry', strpos( $builder, 'const entry = libraryEntry();' ) !== false );
flosc_personality_save_ok( 'that entry crosses the WordPress bridge', strpos( $builder, 'window.floscCreatePersonality(label, profile, workshop, label, role, entry);' ) !== false );
flosc_personality_save_ok( 'creation appends its own computed sidecars', strpos( $bridge, 'appendSidecarEntry(body, entryFields);' ) !== false );

echo "\nAttach reports a real stored attachment\n";
flosc_personality_save_ok( 'the server resolves the installed flow option row', strpos( $library, "flosc_resolve_flow_option_key_for_ivr( \$ivr )" ) !== false );
$resolver_start = strpos( $library, 'function flosc_personality_library_id_for_flow( $flow_id = null )' );
$resolver_body  = false === $resolver_start ? '' : substr( $library, $resolver_start, 5000 );
flosc_personality_save_ok( 'runtime reads that same resolved option row', strpos( $resolver_body, 'flosc_personality_flow_settings_for_ivr( $ivr )' ) !== false );
$context_start = strpos( $library, 'function flosc_personality_builder_request_context()' );
$context_body  = false === $context_start ? '' : substr( $library, $context_start, 5000 );
flosc_personality_save_ok( 'designer reload reads that same resolved option row', strpos( $context_body, 'flosc_personality_flow_settings_for_ivr( $ivr )' ) !== false );
flosc_personality_save_ok( 'framework flow loading uses the shared option resolver', strpos( $main, 'flosc_resolve_flow_option_key_for_ivr($filename)' ) !== false );
flosc_personality_save_ok( 'message runtime uses the shared option resolver', strpos( $runtime, "flosc_resolve_flow_option_key_for_ivr( basename( (string) \$ivr_file ) )" ) !== false );
flosc_personality_save_ok( 'unknown library ids are rejected', strpos( $library, 'That personality is not in the FLOSC library.' ) !== false );
flosc_personality_save_ok( 'create checks attach JSON success before reload', strpos( $bridge, 'if (!attachJson || !attachJson.success)' ) !== false );

echo "\nNo-op saves remain no-ops and the page shows the stored revision\n";
flosc_personality_save_ok( 'volatile workshop receipt fields are removed', strpos( $library, "unset( \$decoded['written_at'], \$decoded['provenance'], \$decoded['derived'] );" ) !== false );
flosc_personality_save_ok( 'library writes are confirmed by exact storage read-back', strpos( $library, 'return is_array( $stored ) && $stored === $clean;' ) !== false );
flosc_personality_save_ok( 'entry update returns that storage result', strpos( $library, 'return flosc_personality_library_save_all( $lib );' ) !== false );
flosc_personality_save_ok( 'the server returns the stored hash', strpos( $library, "'hash'     => is_array( \$saved_row )" ) !== false );
flosc_personality_save_ok( 'the browser adopts the returned hash', strpos( $bridge, 'wp.entry.hash = json.data.hash' ) !== false );
flosc_personality_save_ok( 'the browser redraws the revision chips', strpos( $bridge, 'api.render();' ) !== false );

echo $fail ? "\n{$fail} FAILURES\n" : "\nPersonality create, edit, and attach share one verified contract\n";
exit( $fail ? 1 : 0 );
