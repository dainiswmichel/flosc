<?php
/**
 * FLOSC uninstall routine.
 *
 * Runs only when the plugin is deleted from WordPress admin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove plugin options and transients.
$flosc_option_like = $wpdb->esc_like('flosc_') . '%';
$flosc_transient_like = $wpdb->esc_like('_transient_flosc_') . '%';
$flosc_transient_timeout_like = $wpdb->esc_like('_transient_timeout_flosc_') . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup intentionally removes plugin-owned options
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $flosc_option_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup intentionally removes plugin-owned transients
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $flosc_transient_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup intentionally removes plugin-owned transient timeouts
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $flosc_transient_timeout_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists

if (is_multisite()) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- multisite uninstall cleanup for plugin-owned entries
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $flosc_option_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- multisite uninstall cleanup for plugin-owned entries
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $flosc_transient_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit coverage for Plugin Check direct/no-cache entries on this query
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- multisite uninstall cleanup for plugin-owned entries
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $flosc_transient_timeout_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
}

// Remove plugin-related user/term/post meta (_flosc_* and flosc_* without underscore).
$flosc_meta_underscore = $wpdb->esc_like('_flosc_') . '%';
$flosc_meta_plain      = $wpdb->esc_like('flosc_') . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup for plugin-owned user meta
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
    $flosc_meta_underscore,
    $flosc_meta_plain
)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup for plugin-owned term meta
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
    $flosc_meta_underscore,
    $flosc_meta_plain
)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup for plugin-owned post meta
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
    $flosc_meta_underscore,
    $flosc_meta_plain
)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists

// Drop FLOSC custom tables if present.
$flosc_tables = [
    $wpdb->prefix . 'flosc_chat_logs',
    $wpdb->prefix . 'flosc_lessons',
];

foreach ($flosc_tables as $flosc_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall teardown for plugin-owned custom tables only
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $flosc_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- direct query on FLOSC-owned tables/data path where no core API exists
}

// Clear FLOSC cron events if they still exist.
if (function_exists('wp_clear_scheduled_hook')) {
    wp_clear_scheduled_hook('flosc_cleanup_visitor_audio');
    wp_clear_scheduled_hook('flosc_guest_followup_cron');
}

// Remove FLOSC-managed uploads directories (AI configs, user audio, temp).
$flosc_uploads = wp_upload_dir(null, false);
if (empty($flosc_uploads['error']) && !empty($flosc_uploads['basedir'])) {
    $flosc_upload_roots = array(
        trailingslashit($flosc_uploads['basedir']) . 'flosc',
        trailingslashit($flosc_uploads['basedir']) . 'flosc-users',
        trailingslashit($flosc_uploads['basedir']) . 'flosc-temp',
    );
    foreach ($flosc_upload_roots as $flosc_dir) {
        if (is_dir($flosc_dir)) {
            flosc_uninstall_rm_rf($flosc_dir);
        }
    }
}

/**
 * Recursively remove a directory under uploads during uninstall.
 *
 * @param string $dir Absolute path.
 */
function flosc_uninstall_rm_rf($dir) {
    $dir = untrailingslashit($dir);
    if ($dir === '' || !is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            flosc_uninstall_rm_rf($path);
        } else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- uninstall cleanup of FLOSC upload files
            @unlink($path);
        }
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall cleanup of FLOSC upload dirs
    @rmdir($dir);
}
