<?php
/**
 * FLOSC uninstall routine.
 *
 * Runs only when the plugin is deleted from WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Uninstall must purge FLOSC options (autoload=no) and custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall purge of options + custom tables

/**
 * Delete all options whose names match a prefix (uses core option API).
 *
 * @param string $prefix Option name prefix.
 * @return void
 */
function flosc_uninstall_delete_options_by_prefix( $prefix ) {
	global $wpdb;
	$prefix = (string) $prefix;
	if ( $prefix === '' ) {
		return;
	}
	// Include autoload=no options. Bust options object cache after bulk delete.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	if ( function_exists( 'flosc_bust_flow_option_rows_cache' ) ) {
		flosc_bust_flow_option_rows_cache();
	}
}

/**
 * Delete all site meta keys with a prefix (multisite).
 *
 * @param string $prefix Meta key prefix.
 * @return void
 */
function flosc_uninstall_delete_sitemeta_by_prefix( $prefix ) {
	if ( ! is_multisite() ) {
		return;
	}
	$prefix = (string) $prefix;
	if ( $prefix === '' || ! function_exists( 'get_site_option' ) ) {
		return;
	}
	global $wpdb;
	// Network options live in sitemeta; list keys then delete via API.
	$like      = $wpdb->esc_like( $prefix ) . '%';
	$cache_key = 'sitemeta_keys_' . md5( $like );
	$keys      = wp_cache_get( $cache_key, 'flosc_uninstall' );
	if ( ! is_array( $keys ) ) {
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
				$like
			)
		);
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}
		wp_cache_set( $cache_key, $keys, 'flosc_uninstall', 60 );
	}
	foreach ( $keys as $key ) {
		delete_site_option( (string) $key );
	}
	wp_cache_delete( $cache_key, 'flosc_uninstall' );
}

/**
 * Delete user/term/post meta rows with FLOSC key prefixes via object loops.
 *
 * @param string $object_type user|term|post.
 * @return void
 */
function flosc_uninstall_delete_meta_by_prefixes( $object_type ) {
	$prefixes = array( '_flosc_', 'flosc_' );

	if ( 'user' === $object_type ) {
		$user_ids = get_users( array( 'fields' => 'ID' ) );
		foreach ( (array) $user_ids as $user_id ) {
			$all = get_user_meta( (int) $user_id );
			if ( ! is_array( $all ) ) {
				continue;
			}
			foreach ( array_keys( $all ) as $key ) {
				$key = (string) $key;
				foreach ( $prefixes as $prefix ) {
					if ( 0 === strpos( $key, $prefix ) ) {
						delete_user_meta( (int) $user_id, $key );
						break;
					}
				}
			}
		}
		return;
	}

	if ( 'term' === $object_type ) {
		$terms = get_terms(
			array(
				'taxonomy'   => get_taxonomies(),
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return;
		}
		foreach ( $terms as $term_id ) {
			$all = get_term_meta( (int) $term_id );
			if ( ! is_array( $all ) ) {
				continue;
			}
			foreach ( array_keys( $all ) as $key ) {
				$key = (string) $key;
				foreach ( $prefixes as $prefix ) {
					if ( 0 === strpos( $key, $prefix ) ) {
						delete_term_meta( (int) $term_id, $key );
						break;
					}
				}
			}
		}
		return;
	}

	if ( 'post' === $object_type ) {
		$q = new WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( (array) $q->posts as $post_id ) {
			$all = get_post_meta( (int) $post_id );
			if ( ! is_array( $all ) ) {
				continue;
			}
			foreach ( array_keys( $all ) as $key ) {
				$key = (string) $key;
				foreach ( $prefixes as $prefix ) {
					if ( 0 === strpos( $key, $prefix ) ) {
						delete_post_meta( (int) $post_id, $key );
						break;
					}
				}
			}
		}
	}
}

// Options + transients (option API).
flosc_uninstall_delete_options_by_prefix( 'flosc_' );
flosc_uninstall_delete_options_by_prefix( '_transient_flosc_' );
flosc_uninstall_delete_options_by_prefix( '_transient_timeout_flosc_' );

if ( is_multisite() ) {
	flosc_uninstall_delete_sitemeta_by_prefix( 'flosc_' );
	flosc_uninstall_delete_sitemeta_by_prefix( '_transient_flosc_' );
	flosc_uninstall_delete_sitemeta_by_prefix( '_transient_timeout_flosc_' );
}

// User / term / post meta.
flosc_uninstall_delete_meta_by_prefixes( 'user' );
flosc_uninstall_delete_meta_by_prefixes( 'term' );
flosc_uninstall_delete_meta_by_prefixes( 'post' );

/**
 * Drop a FLOSC custom table (no core API for arbitrary plugin tables).
 *
 * @param string $flosc_table Full table name.
 * @return void
 */
function flosc_uninstall_drop_table( $flosc_table ) {
	global $wpdb;
	wp_cache_delete( 'table_exists_' . $flosc_table, 'flosc_lesaep' );
	wp_cache_delete( 'all_' . $flosc_table, 'flosc_lesaep' );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $flosc_table ) );
}

// Drop FLOSC custom tables if present.
global $wpdb;
$flosc_tables = array(
	$wpdb->prefix . 'flosc_chat_logs',
	$wpdb->prefix . 'flosc_lessons',
);
foreach ( $flosc_tables as $flosc_table ) {
	flosc_uninstall_drop_table( $flosc_table );
}

// Clear FLOSC cron events if they still exist.
if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'flosc_cleanup_visitor_audio' );
	wp_clear_scheduled_hook( 'flosc_guest_followup_cron' );
}

// Remove FLOSC-managed uploads directories (AI configs, user audio, temp).
$flosc_uploads = wp_upload_dir( null, false );
if ( empty( $flosc_uploads['error'] ) && ! empty( $flosc_uploads['basedir'] ) ) {
	$flosc_upload_roots = array(
		trailingslashit( $flosc_uploads['basedir'] ) . 'flosc',
		trailingslashit( $flosc_uploads['basedir'] ) . 'flosc-users',
		trailingslashit( $flosc_uploads['basedir'] ) . 'flosc-temp',
		trailingslashit( $flosc_uploads['basedir'] ) . 'flosc-catalogs',
	);
	foreach ( $flosc_upload_roots as $flosc_dir ) {
		if ( is_dir( $flosc_dir ) ) {
			flosc_uninstall_rm_rf( $flosc_dir );
		}
	}
}

/**
 * Recursively remove a directory under uploads during uninstall via WP_Filesystem.
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
		WP_Filesystem();
	}
	if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'rmdir' ) ) {
		$wp_filesystem->rmdir( $dir, true );
		return;
	}

	// Fallback: recursive delete with wp_delete_file for files.
	$items = scandir( $dir );
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
		} else {
			wp_delete_file( $path );
		}
	}
	if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'rmdir' ) ) {
		$wp_filesystem->rmdir( $dir, false );
	}
}
