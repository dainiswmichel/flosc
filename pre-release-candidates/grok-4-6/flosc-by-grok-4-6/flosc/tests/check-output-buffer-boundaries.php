<?php
/**
 * Verify FLOSC output-buffer ownership and cleanup.
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
$flosc_php      = array();
$flosc_calls    = 0;
$flosc_opens    = 0;
$flosc_closers  = 0;
$flosc_skip     = array( '/.git/', '/tests/', '/pre-release-candidates/', '/vendor/' );
$flosc_items    = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $flosc_root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $flosc_items as $flosc_item ) {
	if ( ! $flosc_item->isFile() || 'php' !== strtolower( $flosc_item->getExtension() ) ) {
		continue;
	}

	$flosc_path     = $flosc_item->getPathname();
	$flosc_relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $flosc_path, strlen( $flosc_root ) ) );
	foreach ( $flosc_skip as $flosc_fragment ) {
		if ( false !== strpos( $flosc_relative, $flosc_fragment ) ) {
			continue 2;
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local source in a CLI-only audit.
	$flosc_source                               = (string) file_get_contents( $flosc_path );
	$flosc_php[ ltrim( $flosc_relative, '/' ) ] = $flosc_source;
	$flosc_calls                               += substr_count( $flosc_source, 'flosc_capture_output(' );
	$flosc_opens                               += substr_count( $flosc_source, 'ob_start(' );
	$flosc_closers                             += substr_count( $flosc_source, 'ob_get_clean(' );
	$flosc_closers                             += substr_count( $flosc_source, 'ob_end_clean(' );
}

$flosc_helper = $flosc_php['includes/flosc-output-buffer.php'] ?? '';
if ( 37 !== $flosc_calls ) {
	$flosc_failures[] = 'expected the helper definition plus 36 capture calls, found ' . $flosc_calls;
}
if ( 1 !== $flosc_opens ) {
	$flosc_failures[] = 'expected one shipped ob_start() call, found ' . $flosc_opens;
}
if ( 2 !== $flosc_closers ) {
	$flosc_failures[] = 'expected the helper cleanup branches, found ' . $flosc_closers . ' closing calls';
}
if ( 1 !== substr_count( $flosc_helper, 'ob_start(' ) ) {
	$flosc_failures[] = 'the only ob_start() must be owned by flosc_capture_output()';
}
if ( false === strpos( $flosc_helper, 'try {' ) || false === strpos( $flosc_helper, '} finally {' ) ) {
	$flosc_failures[] = 'flosc_capture_output() must close its buffer from finally';
}
if ( strpos( $flosc_helper, 'ob_start(' ) < strpos( $flosc_helper, 'try {' ) ) {
	$flosc_failures[] = 'flosc_capture_output() must open its buffer inside try';
}
if ( false === strpos( $flosc_helper, 'ob_get_level() > ( $flosc_parent_level + 1 )' ) ) {
	$flosc_failures[] = 'the helper must remove only nested buffers above its own';
}
if ( false === strpos( $flosc_helper, 'ob_get_level() === ( $flosc_parent_level + 1 )' ) ) {
	$flosc_failures[] = 'the helper must close its buffer only while it remains current';
}
if ( false === strpos( $flosc_helper, 'register_shutdown_function( $flosc_shutdown )' ) ) {
	$flosc_failures[] = 'exit must reach a shutdown close for the buffer finally does not close';
}
if ( 1 !== substr_count( $flosc_helper, 'ob_end_flush(' ) ) {
	$flosc_failures[] = 'the shutdown close must flush the buffer opened by this call';
}
if ( false === strpos( $flosc_helper, 'if ( ! ob_end_clean() )' ) ) {
	$flosc_failures[] = 'a nested buffer that cannot be removed must stop the cleanup loop';
}

$flosc_required_scope = array(
	'admin/chat-logs.php'          => array( '$flosc_chat_logs_nonce', '$flosc_session_scope', '$flosc_current_flow_id', '$flosc_recent_logs', '$flosc_selected_user_id' ),
	'admin/chat-navigation.php'    => array( '$flosc_available_actions' ),
	'admin/chat-styling.php'       => array( '$flosc_bubble_styles' ),
	'admin/content.php'            => array( '$flosc_enabled_quizzes', '$flosc_quiz_label_map', '$flosc_categories', '$flosc_tags', '$flosc_saved_levels', '$flosc_vgm_tiers', '$flosc_vgm_depths' ),
	'admin/da1.php'                => array( '$flosc_da1_columns', '$flosc_da1_ncols', '$flosc_da1_multiline_idx' ),
	'admin/flosc-app.php'          => array( '$flow_settings', '$user_state', '$user_data', '$flow_id', '$offers', '$admin_test_offers', '$identity', '$flosc_user', '$flosc_login_return_url', '$flosc_is_companion_embed', '$flosc_visitor_name', '$flosc_visitor_role', '$flosc_visitor_wallet_initial', '$flosc_visitor_wallet_initial_display', '$flosc_real_millicents_per_message', '$flosc_visitor_label_base', '&$flosc_ajax_url' ),
	'admin/login-registration.php' => array( '$flosc_guest_link_name' ),
	'admin/member-levels.php'      => array( '$flosc_categories', '$flosc_tags', '$flosc_saved_levels', '$flosc_vgm_tiers', '$flosc_vgm_depths' ),
	'admin/settings.php'           => array( '$flosc_active_tab', '$flosc_identity_view' ),
	'includes/flosc-admin.php'     => array( '$settings', '$status', '$show_form', '$current_url', '$rendered_at', '$honeypot_name' ),
);
foreach ( $flosc_required_scope as $flosc_file => $flosc_variables ) {
	$flosc_use_lists = array();
	preg_match_all( '/static function\s*\(\)\s*use\s*\(([^)]*)\)/s', $flosc_php[ $flosc_file ], $flosc_use_lists );
	$flosc_captures = implode( ', ', $flosc_use_lists[1] ?? array() );
	foreach ( $flosc_variables as $flosc_variable ) {
		if ( false === strpos( $flosc_captures, $flosc_variable ) ) {
			$flosc_failures[] = $flosc_file . ' does not preserve capture variable ' . $flosc_variable;
		}
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Loads a guarded plugin helper in this isolated CLI test.
	define( 'ABSPATH', $flosc_root . '/' );
}
require_once $flosc_root . '/includes/flosc-output-buffer.php';

$flosc_level = ob_get_level();
$flosc_value = flosc_capture_output(
	static function () {
		echo 'captured';
	}
);
if ( 'captured' !== $flosc_value || ob_get_level() !== $flosc_level ) {
	$flosc_failures[] = 'normal capture changed output or buffer depth';
}

$flosc_level = ob_get_level();
$flosc_value = flosc_capture_output(
	static function () {
		echo 'outer';
		ob_start();
		echo 'discarded nested output';
	}
);
if ( 'outer' !== $flosc_value || ob_get_level() !== $flosc_level ) {
	$flosc_failures[] = 'nested buffer cleanup changed output or buffer depth';
}

ob_start();
$flosc_parent_level = ob_get_level();
$flosc_value        = flosc_capture_output(
	static function () {
		ob_end_clean();
	}
);
$flosc_parent_open  = ( ob_get_level() === $flosc_parent_level );
ob_end_clean();
if ( '' !== $flosc_value || ! $flosc_parent_open || ob_get_level() !== $flosc_level ) {
	$flosc_failures[] = 'premature close handling disturbed a parent buffer';
}

$flosc_level  = ob_get_level();
$flosc_thrown = false;
try {
	flosc_capture_output(
		static function () {
			throw new RuntimeException( 'expected test exception' );
		}
	);
} catch ( RuntimeException $flosc_exception ) {
	$flosc_thrown = ( 'expected test exception' === $flosc_exception->getMessage() );
}
if ( ! $flosc_thrown || ob_get_level() !== $flosc_level ) {
	$flosc_failures[] = 'exception cleanup failed to restore the prior buffer depth';
}

ob_start();
echo 'PARENT';
$flosc_value = flosc_capture_output(
	static function () {
		echo 'CHILD';
	}
);
$flosc_parent_bytes = ob_get_clean();
if ( 'CHILD' !== $flosc_value || 'PARENT' !== $flosc_parent_bytes || ob_get_level() !== $flosc_level ) {
	$flosc_failures[] = 'normal capture altered the parent buffer contents';
}

/**
 * Run a CLI probe that exits inside a capture.
 *
 * @param string $mode Probe mode: exit, nested, normal, or stuck.
 * @return array{0:string,1:string,2:string,3:int} Stdout, stderr, marker, and whether it finished.
 */
$flosc_probe = static function ( $mode ) use ( $flosc_root ) {
	$flosc_script = tempnam( sys_get_temp_dir(), 'flosc-ob' );
	$flosc_marker = tempnam( sys_get_temp_dir(), 'flosc-ob-mark' );
	$flosc_code   = <<<'PHP'
<?php
define( 'ABSPATH', dirname( $argv[1] ) . '/' );
require $argv[1];
$mode   = $argv[3];
$marker = $argv[2];
if ( 'stuck' === $mode ) {
	flosc_capture_output(
		static function () {
			ob_start( null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE );
			echo 'STUCK';
		}
	);
	fwrite( STDOUT, 'RETURNED' );
	exit( 0 );
}
ob_start();
echo 'PARENT';
$parent = ob_get_level();
if ( 'normal' === $mode ) {
	$child = flosc_capture_output(
		static function () {
			echo 'CHILD';
		}
	);
	register_shutdown_function(
		static function () use ( $parent, $marker, $child ) {
			file_put_contents( $marker, ob_get_level() . ':' . $parent . ':' . $child . ':' . ob_get_contents() );
		}
	);
	exit( 0 );
}
if ( 'nested' === $mode ) {
	flosc_capture_output(
		static function () use ( $parent, $marker ) {
			echo 'OUTER';
			flosc_capture_output(
				static function () use ( $parent, $marker ) {
					echo 'INNER';
					register_shutdown_function(
						static function () use ( $parent, $marker ) {
							file_put_contents( $marker, ob_get_level() . ':' . $parent );
						}
					);
					exit( 0 );
				}
			);
		}
	);
	exit( 2 );
}
flosc_capture_output(
	static function () use ( $parent, $marker ) {
		echo 'INNER';
		register_shutdown_function(
			static function () use ( $parent, $marker ) {
				file_put_contents( $marker, ob_get_level() . ':' . $parent );
			}
		);
		exit( 0 );
	}
);
exit( 2 );
PHP;
	file_put_contents( $flosc_script, $flosc_code );
	$flosc_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $flosc_script ) . ' ' . escapeshellarg( $flosc_root . '/includes/flosc-output-buffer.php' ) . ' ' . escapeshellarg( $flosc_marker ) . ' ' . escapeshellarg( $mode );
	$flosc_pipes   = array();
	$flosc_process = proc_open(
		$flosc_command,
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$flosc_pipes
	);
	fclose( $flosc_pipes[0] );
	stream_set_blocking( $flosc_pipes[1], false );
	stream_set_blocking( $flosc_pipes[2], false );
	$flosc_stdout  = '';
	$flosc_stderr  = '';
	$flosc_started = microtime( true );
	$flosc_done    = 0;
	if ( is_resource( $flosc_process ) ) {
		do {
			$flosc_stdout .= (string) stream_get_contents( $flosc_pipes[1] );
			$flosc_stderr .= (string) stream_get_contents( $flosc_pipes[2] );
			$flosc_status  = proc_get_status( $flosc_process );
			if ( ! $flosc_status['running'] ) {
				$flosc_done = 1;
				break;
			}
			if ( ( microtime( true ) - $flosc_started ) > 3 ) {
				proc_terminate( $flosc_process );
				break;
			}
			usleep( 10000 );
		} while ( true );
		$flosc_stdout .= (string) stream_get_contents( $flosc_pipes[1] );
		$flosc_stderr .= (string) stream_get_contents( $flosc_pipes[2] );
		fclose( $flosc_pipes[1] );
		fclose( $flosc_pipes[2] );
		proc_close( $flosc_process );
	}
	$flosc_mark = is_file( $flosc_marker ) ? (string) file_get_contents( $flosc_marker ) : '';
	unlink( $flosc_script );
	unlink( $flosc_marker );
	return array( $flosc_stdout, $flosc_stderr, $flosc_mark, $flosc_done );
};

$flosc_exit_probe = $flosc_probe( 'exit' );
if ( 1 !== $flosc_exit_probe[3] || '1:1' !== $flosc_exit_probe[2] || false === strpos( $flosc_exit_probe[0], 'PARENT' ) || false === strpos( $flosc_exit_probe[0], 'INNER' ) ) {
	$flosc_failures[] = 'exit left the capture buffer open or dropped the parent output';
}

$flosc_nested_probe = $flosc_probe( 'nested' );
if ( 1 !== $flosc_nested_probe[3] || '1:1' !== $flosc_nested_probe[2] || false === strpos( $flosc_nested_probe[0], 'PARENT' ) || false === strpos( $flosc_nested_probe[0], 'OUTER' ) || false === strpos( $flosc_nested_probe[0], 'INNER' ) ) {
	$flosc_failures[] = 'nested exit did not flush both capture buffers into the parent';
}

$flosc_normal_probe = $flosc_probe( 'normal' );
if ( 1 !== $flosc_normal_probe[3] || '1:1:CHILD:PARENT' !== $flosc_normal_probe[2] ) {
	$flosc_failures[] = 'a finished capture flushed its caller buffer during shutdown';
}

$flosc_stuck_probe = $flosc_probe( 'stuck' );
if ( 1 !== $flosc_stuck_probe[3] || false === strpos( $flosc_stuck_probe[0], 'RETURNED' ) ) {
	$flosc_failures[] = 'a nested buffer that cannot be removed hung the cleanup loop';
}

if ( ! empty( $flosc_failures ) ) {
	foreach ( $flosc_failures as $flosc_failure ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI audit output to standard error.
		fwrite( STDERR, 'FAIL ' . $flosc_failure . "\n" );
	}
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI audit output to standard output.
fwrite( STDOUT, "output buffer boundary passed: 36 captures, one owner, zero leaked buffers\n" );
exit( 0 );
