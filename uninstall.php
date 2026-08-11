<?php
/**
 * FLOSC uninstall routine.
 *
 * Runs only when the plugin is deleted from WordPress admin.
 * Keep this fast and dependency-free so Delete can finish removing plugins/flosc/.
 * Do not load the main plugin, loop all users/posts, or call optional helpers.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall purge

/**
 * @param string $prefix Option name prefix.
 * @return void
 */
function flosc_uninstall_delete_options_by_prefix( $prefix ) {
	global $wpdb;
	$prefix = (string) $prefix;
	if ( $prefix === '' ) {
		return;
	}
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}

/**
 * @param string $prefix Meta key prefix.
 * @return void
 */
function flosc_uninstall_delete_sitemeta_by_prefix( $prefix ) {
	if ( ! is_multisite() ) {
		return;
	}
	global $wpdb;
	$prefix = (string) $prefix;
	if ( $prefix === '' ) {
		return;
	}
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
}

/**
 * Bulk-delete object meta by key prefix (no per-object PHP loops).
 *
 * @param string $table Full table name (usermeta, postmeta, termmeta).
 * @param string $prefix Meta key prefix.
 * @return void
 */
function flosc_uninstall_delete_meta_table_prefix( $table, $prefix ) {
	global $wpdb;
	$table  = (string) $table;
	$prefix = (string) $prefix;
	if ( $table === '' || $prefix === '' ) {
		return;
	}
	// Table name from $wpdb->* only — not user input.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table} WHERE meta_key LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
}

// Options + transients.
flosc_uninstall_delete_options_by_prefix( 'flosc_' );
flosc_uninstall_delete_options_by_prefix( '_flosc_' );
flosc_uninstall_delete_options_by_prefix( '_transient_flosc_' );
flosc_uninstall_delete_options_by_prefix( '_transient_timeout_flosc_' );

if ( is_multisite() ) {
	flosc_uninstall_delete_sitemeta_by_prefix( 'flosc_' );
	flosc_uninstall_delete_sitemeta_by_prefix( '_flosc_' );
	flosc_uninstall_delete_sitemeta_by_prefix( '_transient_flosc_' );
	flosc_uninstall_delete_sitemeta_by_prefix( '_transient_timeout_flosc_' );
}

// Meta (SQL bulk — safe on large user/post counts).
global $wpdb;
foreach ( array( '_flosc_', 'flosc_' ) as $flosc_meta_prefix ) {
	flosc_uninstall_delete_meta_table_prefix( $wpdb->usermeta, $flosc_meta_prefix );
	flosc_uninstall_delete_meta_table_prefix( $wpdb->postmeta, $flosc_meta_prefix );
	if ( ! empty( $wpdb->termmeta ) ) {
		flosc_uninstall_delete_meta_table_prefix( $wpdb->termmeta, $flosc_meta_prefix );
	}
}

// Custom tables.
$flosc_tables = array(
	$wpdb->prefix . 'flosc_chat_logs',
	$wpdb->prefix . 'flosc_lessons',
);
foreach ( $flosc_tables as $flosc_table ) {
	// Identifier only from $wpdb->prefix + fixed suffix.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$flosc_table}`" );
}

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'flosc_cleanup_visitor_audio' );
	wp_clear_scheduled_hook( 'flosc_guest_followup_cron' );
}

/**
 * Recursively remove a directory under uploads.
 *
 * @param string $dir Absolute path.
 * @return void
 */
function flosc_uninstall_rm_rf( $dir ) {
	$dir = untrailingslashit( (string) $dir );
	if ( $dir === '' || ! is_dir( $dir ) ) {
		return;
	}

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	global $wp_filesystem;
	if ( ! is_object( $wp_filesystem ) ) {
		// Direct method: uninstall has no form credentials for FTP.
		WP_Filesystem();
	}
	if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'rmdir' ) && $wp_filesystem->rmdir( $dir, true ) ) {
		return;
	}

	$items = @scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) ) {
			flosc_uninstall_rm_rf( $path );
		} elseif ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $path );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	@rmdir( $dir );
}

// FLOSC runtime under uploads only (never touch plugins/flosc — core removes that).
// Keep uploads/flosc-catalogs: DA1 TSV catalogs are site content (re-uploadable index
// lives in options and is cleared with other flosc_* options above).
$flosc_uploads = wp_upload_dir( null, false );
if ( empty( $flosc_uploads['error'] ) && ! empty( $flosc_uploads['basedir'] ) ) {
	$base = trailingslashit( $flosc_uploads['basedir'] );
	foreach ( array( 'flosc', 'flosc-users', 'flosc-temp' ) as $flosc_subdir ) {
		$flosc_dir = $base . $flosc_subdir;
		if ( is_dir( $flosc_dir ) ) {
			flosc_uninstall_rm_rf( $flosc_dir );
		}
	}
}
