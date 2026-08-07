<?php
/**
 * FLOSC Admin — DA1 Catalog Manager
 *
 * DA1 Catalogs are flow-scoped structured datasets used by FLOSC to deliver
 * curated records, media references, fallback content, and response material
 * without hard-coding project-specific data into plugin PHP.
 *
 * Catalogs are stored as TSV files under wp-content/uploads/flosc-catalogs
 * and assigned to flows by the floscAdmin.
 *
 * v8.0.1 foundation:
 * - Multi-catalog TSV management
 * - Parent/child row model (85, 85.1, 85.2)
 * - Required control columns with safe defaults
 * - Status model: active / paused
 * - Upload and export catalog actions
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    return;
}

if (!function_exists('flosc_da1_safe_json_decode')) {
    function flosc_da1_safe_json_decode($raw, $max_bytes = 200000, $depth = 32) {
        $raw = (string) $raw;
        if ($raw === '' || strlen($raw) > $max_bytes) {
            return new WP_Error('flosc_da1_payload_too_large', 'Payload is invalid or too large.');
        }

        $decoded = json_decode($raw, true, $depth);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($decoded)) {
            return new WP_Error('flosc_da1_payload_invalid', 'Invalid catalog payload.');
        }

        return $decoded;
    }
}

$flosc_upload_dir = wp_upload_dir();
$flosc_basedir = trailingslashit($flosc_upload_dir['basedir']);
$flosc_catalog_dir = $flosc_basedir . 'flosc-catalogs';
if (!wp_mkdir_p($flosc_catalog_dir)) {
    echo '<div class="notice notice-error"><p>Could not create catalog directory in uploads.</p></div>';
    return;
}

$flosc_sample_catalog_path = FLOSC_PLUGIN_DIR . 'sample-data/flosc_da1_catalog_default.tsv';
$flosc_catalog_index_option = 'flosc_da1_catalogs';
$flosc_catalog_assign_option = 'flosc_da1_flow_catalogs';
$flosc_default_catalog_key = 'default';

$flosc_required_columns = [
    'Row Key',
    'Parent Key',
    'Catalog Key',
    'Record Type',
    'Flow Scope',
    'VGM',
    'Delivery Instruction',
    'Delivery Rule',
    'Fallback Order',
    'Status',
];

$flosc_base_payload_columns = [
    'Date',
    'Title',
    'Description',
    'Lyrics',
    'Media',
    'Media Type',
    'Notes',
    'WP Post ID',
    'WP Slug',
    'Related Posts',
    'Related Content Keys',
    'Content Category',
    'Content Subcategory',
];

$flosc_control_defaults = [
    'Parent Key' => '',
    'Catalog Key' => $flosc_default_catalog_key,
    'Record Type' => 'work',
    'Flow Scope' => 'all',
    'VGM' => 'Visitor Guest Member',
    'Delivery Instruction' => 'intent match',
    'Delivery Rule' => 'preference',
    'Fallback Order' => 'chatplayer > media-link > text',
    'Status' => 'active',
    'Media Type' => 'chatplayer',
    'Content Category' => 'music',
];

// Prefer request arrays prepared by settings.php; avoid re-touching superglobals.
if ( ! isset( $flosc_get ) || ! is_array( $flosc_get ) ) {
	$flosc_get = array();
}
if ( ! isset( $flosc_post ) || ! is_array( $flosc_post ) ) {
	$flosc_post = array();
}
$flosc_da1_get  = $flosc_get;
$flosc_da1_post = $flosc_post;

function flosc_da1_slugify($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
    $value = trim((string) $value, '-');
    return $value === '' ? 'catalog' : $value;
}

function flosc_da1_normalize_key($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return '';
    }
    return preg_replace('/[^a-z0-9._-]/', '', $value);
}

function flosc_da1_parse_tsv($flosc_da1_content) {
    $flosc_da1_rows = [];
    $flosc_da1_row = [];
    $field = '';
    $in_quotes = false;
    $len = strlen($flosc_da1_content);

    for ($flosc_da1_i = 0; $flosc_da1_i < $len; $flosc_da1_i++) {
        $ch = $flosc_da1_content[$flosc_da1_i];
        if ($in_quotes) {
            if ($ch === '"') {
                if ($flosc_da1_i + 1 < $len && $flosc_da1_content[$flosc_da1_i + 1] === '"') {
                    $field .= '"';
                    $flosc_da1_i++;
                } else {
                    $in_quotes = false;
                }
            } else {
                $field .= $ch;
            }
        } elseif ($ch === '"') {
            $in_quotes = true;
        } elseif ($ch === "\t") {
            $flosc_da1_row[] = $field;
            $field = '';
        } elseif ($ch === "\n") {
            $flosc_da1_row[] = $field;
            $field = '';
            if (!empty($flosc_da1_row)) {
                $flosc_da1_rows[] = $flosc_da1_row;
            }
            $flosc_da1_row = [];
        } elseif ($ch !== "\r") {
            $field .= $ch;
        }
    }

    if ($field !== '' || !empty($flosc_da1_row)) {
        $flosc_da1_row[] = $field;
        $flosc_da1_rows[] = $flosc_da1_row;
    }

    return $flosc_da1_rows;
}

function flosc_da1_tsv_cell($value) {
    $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
    if (strpos($value, "\t") !== false || strpos($value, "\n") !== false || strpos($value, '"') !== false) {
        $value = '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

function flosc_da1_normalize_columns($flosc_da1_columns, $required_columns, $base_payload_columns) {
    $normalized = [];
    $extras = [];
    foreach ((array) $flosc_da1_columns as $c) {
        $c = trim((string) $c);
        if ($c === '') {
            continue;
        }
        if ($c === 'Video' || $c === 'Recordings') {
            $c = 'Media';
        }
        if ($c === 'Social Media Links') {
            $c = 'Related Posts';
        }
        if (!in_array($c, $required_columns, true) && !in_array($c, $base_payload_columns, true) && !in_array($c, $extras, true)) {
            $extras[] = $c;
        }
    }

    foreach ($required_columns as $c) {
        $normalized[] = $c;
    }
    foreach ($base_payload_columns as $c) {
        if (!in_array($c, $normalized, true)) {
            $normalized[] = $c;
        }
    }

    foreach ($extras as $c) {
        if (!in_array($c, $normalized, true)) {
            $normalized[] = $c;
        }
    }

    return $normalized;
}

function flosc_da1_col_index_map($flosc_da1_columns) {
    $map = [];
    foreach ($flosc_da1_columns as $flosc_da1_i => $flosc_da1_col) {
        $map[$flosc_da1_col] = (int) $flosc_da1_i;
    }
    return $map;
}

function flosc_da1_next_parent_key($flosc_da1_rows, $row_idx_key) {
    $max = 0;
    foreach ($flosc_da1_rows as $flosc_da1_row) {
        $flosc_da1_k = isset($flosc_da1_row[$row_idx_key]) ? trim((string) $flosc_da1_row[$row_idx_key]) : '';
        if ($flosc_da1_k !== '' && preg_match('/^[0-9]+$/', $flosc_da1_k)) {
            $n = (int) $flosc_da1_k;
            if ($n > $max) {
                $max = $n;
            }
        }
    }
    return (string) ($max + 1);
}

function flosc_da1_next_child_key($flosc_da1_rows, $row_idx_key, $flosc_da1_parent_key) {
    $max = 0;
    $prefix = trim((string) $flosc_da1_parent_key) . '.';
    foreach ($flosc_da1_rows as $flosc_da1_row) {
        $flosc_da1_k = isset($flosc_da1_row[$row_idx_key]) ? trim((string) $flosc_da1_row[$row_idx_key]) : '';
        if ($flosc_da1_k !== '' && strpos($flosc_da1_k, $prefix) === 0) {
            $suffix = substr($flosc_da1_k, strlen($prefix));
            if (preg_match('/^[0-9]+$/', $suffix)) {
                $n = (int) $suffix;
                if ($n > $max) {
                    $max = $n;
                }
            }
        }
    }
    return $prefix . ($max + 1);
}

function flosc_da1_normalize_vgm($value) {
    $flosc_da1_raw = strtolower(trim((string) $value));
    if ($flosc_da1_raw === '') {
        return '';
    }

    $compact = preg_replace('/[^a-z]/', '', $flosc_da1_raw);
    if ($compact === 'all' || $compact === 'vgm') {
        return 'Visitor Guest Member';
    }

    $tokens = preg_split('/[\s,;\/\-_]+/', $flosc_da1_raw);
    if (!is_array($tokens)) {
        $tokens = [];
    }

    $flags = ['v' => false, 'g' => false, 'm' => false];
    foreach ($tokens as $token) {
        $t = strtolower(trim((string) $token));
        if ($t === '') {
            continue;
        }
        if ($t === 'visitor' || $t === 'v') {
            $flags['v'] = true;
            continue;
        }
        if ($t === 'guest' || $t === 'g') {
            $flags['g'] = true;
            continue;
        }
        if ($t === 'member' || $t === 'm') {
            $flags['m'] = true;
            continue;
        }
        if (preg_match('/^[vgm]+$/', $t)) {
            if (strpos($t, 'v') !== false) $flags['v'] = true;
            if (strpos($t, 'g') !== false) $flags['g'] = true;
            if (strpos($t, 'm') !== false) $flags['m'] = true;
            continue;
        }
        return '';
    }

    $out = [];
    if ($flags['v']) $out[] = 'Visitor';
    if ($flags['g']) $out[] = 'Guest';
    if ($flags['m']) $out[] = 'Member';
    return implode(' ', $out);
}

function flosc_da1_apply_defaults(&$flosc_da1_row, $flosc_da1_columns, $flosc_da1_col_idx, $defaults, $catalog_key) {
    foreach ($flosc_da1_columns as $flosc_da1_ci => $column) {
        if (!isset($flosc_da1_row[$flosc_da1_ci])) {
            $flosc_da1_row[$flosc_da1_ci] = '';
        }
        if (trim((string) $flosc_da1_row[$flosc_da1_ci]) !== '') {
            continue;
        }
        if ($column === 'Catalog Key') {
            $flosc_da1_row[$flosc_da1_ci] = $catalog_key;
            continue;
        }
        if (isset($defaults[$column])) {
            $flosc_da1_row[$flosc_da1_ci] = $defaults[$column];
        }
    }

    if (isset($flosc_da1_col_idx['Status'])) {
        $flosc_s = strtolower(trim((string) ($flosc_da1_row[$flosc_da1_col_idx['Status']] ?? '')));
        if ($flosc_s === '' || ($flosc_s !== 'active' && $flosc_s !== 'paused')) {
            $flosc_da1_row[$flosc_da1_col_idx['Status']] = 'active';
        }
    }
    if (isset($flosc_da1_col_idx['VGM'])) {
        $normalized_vgm = flosc_da1_normalize_vgm((string) ($flosc_da1_row[$flosc_da1_col_idx['VGM']] ?? ''));
        if ($normalized_vgm === '') {
            $normalized_vgm = 'Visitor Guest Member';
        }
        $flosc_da1_row[$flosc_da1_col_idx['VGM']] = $normalized_vgm;
    }
}

function flosc_da1_catalog_file($catalog_dir, $catalog_key) {
    return trailingslashit($catalog_dir) . 'flosc_da1_catalog_' . $catalog_key . '.tsv';
}

function flosc_da1_is_allowed_catalog_path($path, $catalog_dir) {
    $catalog_dir = wp_normalize_path(trailingslashit((string) $catalog_dir));
    $path = wp_normalize_path((string) $path);

    return 0 === strpos($path, $catalog_dir) && '.tsv' === strtolower(substr($path, -4));
}

/**
 * Shared FLOSC_Filesystem for DA1 catalog I/O (one instance per request).
 *
 * @return FLOSC_Filesystem|null
 */
function flosc_da1_filesystem() {
	static $flosc_da1_fs_singleton = null;
	if ( null === $flosc_da1_fs_singleton && class_exists( 'FLOSC_Filesystem' ) ) {
		$flosc_da1_fs_singleton = new FLOSC_Filesystem();
	}
	return $flosc_da1_fs_singleton instanceof FLOSC_Filesystem ? $flosc_da1_fs_singleton : null;
}

// Catalog dir is under uploads — block direct HTTP access to TSV files.
$flosc_da1_fs = flosc_da1_filesystem();
if ( $flosc_da1_fs ) {
	$flosc_da1_fs->protect_uploads_dir_with_htaccess( $flosc_catalog_dir );
}

/**
 * Atomic write of a catalog TSV under uploads/flosc-catalogs.
 *
 * @param string $path        Absolute catalog path.
 * @param string $content     TSV body.
 * @param string $catalog_dir Allowed catalog directory.
 * @return true|WP_Error
 */
function flosc_da1_write_catalog_file( $path, $content, $catalog_dir ) {
	if ( ! flosc_da1_is_allowed_catalog_path( $path, $catalog_dir ) ) {
		return new WP_Error( 'flosc_da1_invalid_catalog_path', __( 'Invalid DA1 catalog path.', 'flosc' ) );
	}

	$content = (string) $content;
	if ( '' === trim( $content ) ) {
		return new WP_Error( 'flosc_da1_empty_catalog', __( 'DA1 catalog content cannot be empty.', 'flosc' ) );
	}

	$flosc_da1_fs = flosc_da1_filesystem();
	if ( ! $flosc_da1_fs ) {
		return new WP_Error( 'flosc_da1_filesystem_unavailable', __( 'Filesystem API unavailable while writing DA1 catalog file.', 'flosc' ) );
	}

	if ( ! $flosc_da1_fs->write_text_atomic( $path, $content ) ) {
		return new WP_Error( 'flosc_da1_write_failed', __( 'Could not write DA1 catalog file.', 'flosc' ) );
	}

	return true;
}

/**
 * Read a catalog TSV under uploads/flosc-catalogs.
 *
 * @param string $path        Absolute catalog path.
 * @param string $catalog_dir Allowed catalog directory.
 * @return string|WP_Error Empty string if missing; WP_Error on failure.
 */
function flosc_da1_read_catalog_file( $path, $catalog_dir ) {
	if ( ! flosc_da1_is_allowed_catalog_path( $path, $catalog_dir ) ) {
		return new WP_Error( 'flosc_da1_invalid_catalog_path', __( 'Invalid DA1 catalog path.', 'flosc' ) );
	}

	if ( ! file_exists( $path ) ) {
		return '';
	}

	$flosc_da1_fs = flosc_da1_filesystem();
	if ( ! $flosc_da1_fs ) {
		return new WP_Error( 'flosc_da1_read_failed', __( 'Could not read DA1 catalog file.', 'flosc' ) );
	}

	$content = $flosc_da1_fs->read_file_safely( $path );
	if ( false === $content ) {
		return new WP_Error( 'flosc_da1_read_failed', __( 'Could not read DA1 catalog file.', 'flosc' ) );
	}

	return $content;
}

/**
 * Read the shipped sample catalog (plugin sample-data only).
 *
 * @param string $sample_path Absolute path under FLOSC_PLUGIN_DIR/sample-data/.
 * @return string|false
 */
function flosc_da1_read_shipped_sample( $sample_path ) {
	$sample_path = wp_normalize_path( (string) $sample_path );
	$allowed     = wp_normalize_path( trailingslashit( FLOSC_PLUGIN_DIR ) . 'sample-data/' );
	if ( $sample_path === '' || 0 !== strpos( $sample_path, $allowed ) ) {
		return false;
	}
	if ( ! file_exists( $sample_path ) ) {
		return false;
	}
	$flosc_da1_fs = flosc_da1_filesystem();
	if ( ! $flosc_da1_fs ) {
		return false;
	}
	// Sample lives in the plugin tree (not uploads) — use read_contents after path allowlist.
	return $flosc_da1_fs->read_contents( $sample_path );
}

/**
 * Read a PHP upload temp file after is_uploaded_file() verification.
 *
 * @param string $tmp_name Raw $_FILES['…']['tmp_name'] (do not path-sanitize).
 * @return string|false
 */
function flosc_da1_read_uploaded_tmp( $tmp_name ) {
	$tmp_name = (string) $tmp_name;
	if ( $tmp_name === '' || ! is_uploaded_file( $tmp_name ) ) {
		return false;
	}
	$flosc_da1_fs = flosc_da1_filesystem();
	if ( ! $flosc_da1_fs ) {
		return false;
	}
	return $flosc_da1_fs->read_contents( $tmp_name );
}

$flosc_da1_catalogs = get_option($flosc_catalog_index_option, []);
if (!is_array($flosc_da1_catalogs)) {
    $flosc_da1_catalogs = [];
}
if (!isset($flosc_da1_catalogs[$flosc_default_catalog_key])) {
    $flosc_da1_catalogs[$flosc_default_catalog_key] = [
        'label' => 'Default Catalog',
        'key' => $flosc_default_catalog_key,
        'filename' => 'flosc_da1_catalog_default.tsv',
        'created_at' => current_time('mysql'),
    ];
}

$flosc_da1_requested_catalog_key = $flosc_default_catalog_key;
if (isset($flosc_da1_post['catalog'])) {
    $flosc_da1_requested_catalog_key = flosc_da1_normalize_key(sanitize_text_field((string) $flosc_da1_post['catalog']));
} elseif (isset($flosc_da1_get['catalog'])) {
    $flosc_da1_requested_catalog_key = flosc_da1_normalize_key(sanitize_text_field((string) $flosc_da1_get['catalog']));
}
if ($flosc_da1_requested_catalog_key === '' || !isset($flosc_da1_catalogs[$flosc_da1_requested_catalog_key])) {
    $flosc_da1_requested_catalog_key = $flosc_default_catalog_key;
}

$flosc_da1_notice_success = '';
$flosc_da1_notice_error = '';

$flosc_da1_selected_ivr = isset($flosc_selected_ivr) ? sanitize_file_name((string) $flosc_selected_ivr) : '';
$flosc_da1_flow_assignments = get_option($flosc_catalog_assign_option, []);
if (!is_array($flosc_da1_flow_assignments)) {
    $flosc_da1_flow_assignments = [];
}

$flosc_known_flow_scopes = ['all'];
if (isset($flosc_ivr_files) && is_array($flosc_ivr_files)) {
    foreach ($flosc_ivr_files as $flosc_ivr_file) {
        $flosc_scope_key = flosc_da1_normalize_key(pathinfo((string) $flosc_ivr_file, PATHINFO_FILENAME));
        if ($flosc_scope_key !== '' && !in_array($flosc_scope_key, $flosc_known_flow_scopes, true)) {
            $flosc_known_flow_scopes[] = $flosc_scope_key;
        }
    }
}

if (isset($flosc_da1_post['flosc_da1_create_catalog'])) {
    check_admin_referer('flosc_da1_catalog_manage');
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to manage DA1 catalogs.', 'flosc'));
    }
    $flosc_da1_label = sanitize_text_field((string) ($flosc_da1_post['flosc_new_catalog_label'] ?? ''));
    $flosc_da1_key_raw = sanitize_text_field((string) ($flosc_da1_post['flosc_new_catalog_key'] ?? ''));
    $flosc_da1_key = flosc_da1_slugify($flosc_da1_key_raw !== '' ? $flosc_da1_key_raw : $flosc_da1_label);
    if ($flosc_da1_key === '') {
        $flosc_da1_notice_error = 'Catalog key is required.';
    } elseif (isset($flosc_da1_catalogs[$flosc_da1_key])) {
        $flosc_da1_notice_error = 'Catalog key already exists.';
    } else {
        $flosc_da1_catalogs[$flosc_da1_key] = [
            'label' => $flosc_da1_label !== '' ? $flosc_da1_label : ucwords(str_replace(['-', '_'], ' ', $flosc_da1_key)),
            'key' => $flosc_da1_key,
            'filename' => 'flosc_da1_catalog_' . $flosc_da1_key . '.tsv',
            'created_at' => current_time('mysql'),
        ];
        update_option($flosc_catalog_index_option, $flosc_da1_catalogs, false);
        $flosc_da1_requested_catalog_key = $flosc_da1_key;
        $flosc_da1_notice_success = 'Catalog created.';
    }
}

if (isset($flosc_da1_post['flosc_da1_assign_catalogs']) && $flosc_da1_selected_ivr !== '') {
    check_admin_referer('flosc_da1_catalog_manage');
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to assign DA1 catalogs.', 'flosc'));
    }
    $flosc_da1_assigned = sanitize_text_field((string) ($flosc_da1_post['flosc_flow_catalogs'] ?? ''));
    $flosc_da1_parts = array_filter(array_map('trim', explode(',', (string) $flosc_da1_assigned)));
    $flosc_da1_clean = [];
    foreach ($flosc_da1_parts as $flosc_da1_p) {
        $flosc_da1_k = flosc_da1_normalize_key($flosc_da1_p);
        if ($flosc_da1_k !== '' && isset($flosc_da1_catalogs[$flosc_da1_k]) && !in_array($flosc_da1_k, $flosc_da1_clean, true)) {
            $flosc_da1_clean[] = $flosc_da1_k;
        }
    }
    if (empty($flosc_da1_clean)) {
        $flosc_da1_clean[] = $flosc_default_catalog_key;
    }
    $flosc_da1_flow_assignments[$flosc_da1_selected_ivr] = $flosc_da1_clean;
    update_option($flosc_catalog_assign_option, $flosc_da1_flow_assignments, false);
    $flosc_da1_notice_success = 'Flow catalog assignment saved.';
}

$flosc_da1_catalog_path = flosc_da1_catalog_file($flosc_catalog_dir, $flosc_da1_requested_catalog_key);
$flosc_da1_selected_catalog_label = $flosc_da1_catalogs[$flosc_da1_requested_catalog_key]['label'] ?? $flosc_da1_requested_catalog_key;

if (isset($flosc_da1_post['flosc_da1_upload_catalog'])) {
    check_admin_referer('flosc_da1_catalog_manage');
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to upload DA1 catalogs.', 'flosc'));
    }

    $flosc_da1_name  = isset( $_FILES['flosc_da1_upload_file']['name'] )
        ? sanitize_file_name( wp_unslash( (string) $_FILES['flosc_da1_upload_file']['name'] ) )
        : '';
    $flosc_da1_error = isset( $_FILES['flosc_da1_upload_file']['error'] )
        ? absint( $_FILES['flosc_da1_upload_file']['error'] )
        : UPLOAD_ERR_NO_FILE;
    $flosc_da1_size  = isset( $_FILES['flosc_da1_upload_file']['size'] )
        ? absint( $_FILES['flosc_da1_upload_file']['size'] )
        : 0;
    // tmp_name: unslash + sanitize_text_field for PHPCS; still validated via is_uploaded_file() in reader.
    $flosc_da1_tmp = isset( $_FILES['flosc_da1_upload_file']['tmp_name'] )
        ? sanitize_text_field( wp_unslash( (string) $_FILES['flosc_da1_upload_file']['tmp_name'] ) )
        : '';

    if ( UPLOAD_ERR_OK !== $flosc_da1_error || $flosc_da1_name === '' ) {
        $flosc_da1_notice_error = 'Choose a valid TSV file to upload.';
    } elseif ( '.tsv' !== strtolower( substr( $flosc_da1_name, -4 ) ) ) {
        $flosc_da1_notice_error = 'DA1 uploads must be .tsv files.';
    } elseif ( $flosc_da1_size <= 0 || $flosc_da1_size > 1024 * 1024 ) {
        $flosc_da1_notice_error = 'DA1 catalog uploads must be between 1 byte and 1 MB.';
    } else {
        $flosc_da1_content = flosc_da1_read_uploaded_tmp( $flosc_da1_tmp );
        if ( false === $flosc_da1_content || trim( (string) $flosc_da1_content ) === '' ) {
            $flosc_da1_notice_error = 'Uploaded DA1 catalog is empty or unreadable.';
        } else {
            $flosc_da1_write_result = flosc_da1_write_catalog_file( $flosc_da1_catalog_path, $flosc_da1_content, $flosc_catalog_dir );
            if ( is_wp_error( $flosc_da1_write_result ) ) {
                $flosc_da1_notice_error = $flosc_da1_write_result->get_error_message();
            } else {
                $flosc_da1_notice_success = 'Catalog uploaded.';
            }
        }
    }
}

if (isset($flosc_da1_post['da1_save_catalog'])) {
    check_admin_referer('da1_catalog_save');
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to save DA1 catalogs.', 'flosc'));
    }
}

if ( ! file_exists( $flosc_da1_catalog_path ) && $flosc_da1_requested_catalog_key === $flosc_default_catalog_key ) {
	$flosc_da1_sample_content = flosc_da1_read_shipped_sample( $flosc_sample_catalog_path );
	if ( is_string( $flosc_da1_sample_content ) && trim( $flosc_da1_sample_content ) !== '' ) {
		flosc_da1_write_catalog_file( $flosc_da1_catalog_path, $flosc_da1_sample_content, $flosc_catalog_dir );
	}
}

$flosc_da1_columns = [];
$flosc_da1_rows = [];

if (file_exists($flosc_da1_catalog_path)) {
    $flosc_da1_loaded_content = flosc_da1_read_catalog_file($flosc_da1_catalog_path, $flosc_catalog_dir);
    if (is_wp_error($flosc_da1_loaded_content)) {
        $flosc_da1_notice_error = $flosc_da1_loaded_content->get_error_message();
        $flosc_da1_parsed = [];
    } else {
        $flosc_da1_parsed = flosc_da1_parse_tsv((string) $flosc_da1_loaded_content);
    }
    $flosc_da1_columns = flosc_da1_normalize_columns($flosc_da1_parsed[0] ?? [], $flosc_required_columns, $flosc_base_payload_columns);
    $flosc_da1_rows = array_slice($flosc_da1_parsed, 1);
} else {
    $flosc_da1_columns = flosc_da1_normalize_columns(['Date', 'Title', 'Description', 'Lyrics', 'Media', 'Notes'], $flosc_required_columns, $flosc_base_payload_columns);
    $flosc_da1_rows = [];
}

$flosc_da1_col_idx = flosc_da1_col_index_map($flosc_da1_columns);
$flosc_da1_ncols = count($flosc_da1_columns);

foreach ($flosc_da1_rows as &$flosc_da1_row) {
    while (count($flosc_da1_row) < $flosc_da1_ncols) {
        $flosc_da1_row[] = '';
    }
    flosc_da1_apply_defaults($flosc_da1_row, $flosc_da1_columns, $flosc_da1_col_idx, $flosc_control_defaults, $flosc_da1_requested_catalog_key);
}
unset($flosc_da1_row);

if (isset($flosc_da1_col_idx['Row Key'])) {
    foreach ($flosc_da1_rows as $flosc_da1_i => $flosc_da1_row) {
        $flosc_da1_row_key = trim((string) ($flosc_da1_row[$flosc_da1_col_idx['Row Key']] ?? ''));
        $flosc_da1_parent_key = isset($flosc_da1_col_idx['Parent Key']) ? trim((string) ($flosc_da1_row[$flosc_da1_col_idx['Parent Key']] ?? '')) : '';
        if ($flosc_da1_row_key !== '') {
            continue;
        }
        if ($flosc_da1_parent_key !== '') {
            $flosc_da1_rows[$flosc_da1_i][$flosc_da1_col_idx['Row Key']] = flosc_da1_next_child_key($flosc_da1_rows, $flosc_da1_col_idx['Row Key'], $flosc_da1_parent_key);
        } else {
            $flosc_da1_rows[$flosc_da1_i][$flosc_da1_col_idx['Row Key']] = flosc_da1_next_parent_key($flosc_da1_rows, $flosc_da1_col_idx['Row Key']);
        }
    }
}

if (isset($flosc_da1_post['da1_save_catalog'])) {
    $flosc_post = $flosc_da1_post;
    // Large catalogs exceed PHP max_input_vars when posted as per-cell fields.
    // The grid submits the whole table as one JSON field instead; decode it
    // into the shape the rest of this handler expects.
    if (!empty($flosc_post['da1_payload'])) {
        $flosc_da1_decoded = flosc_da1_safe_json_decode((string) $flosc_post['da1_payload']);
        if (is_array($flosc_da1_decoded)) {
            if (isset($flosc_da1_decoded['columns']) && is_array($flosc_da1_decoded['columns'])) {
                $flosc_post['da1_columns'] = $flosc_da1_decoded['columns'];
            }
            if (isset($flosc_da1_decoded['rows']) && is_array($flosc_da1_decoded['rows'])) {
                $flosc_post['da1_rows'] = $flosc_da1_decoded['rows'];
            }
        }
    }
    $flosc_da1_saved_columns = isset($flosc_post['da1_columns']) && is_array($flosc_post['da1_columns']) ? array_values(array_map('sanitize_text_field', $flosc_post['da1_columns'])) : $flosc_da1_columns;
    $flosc_da1_saved_columns = flosc_da1_normalize_columns($flosc_da1_saved_columns, $flosc_required_columns, $flosc_base_payload_columns);
    $flosc_da1_saved_col_idx = flosc_da1_col_index_map($flosc_da1_saved_columns);

    foreach ($flosc_required_columns as $flosc_da1_req_col) {
        if (!isset($flosc_da1_saved_col_idx[$flosc_da1_req_col])) {
            $flosc_da1_notice_error = 'Missing required column: ' . $flosc_da1_req_col;
            break;
        }
    }

    if ($flosc_da1_notice_error === '') {
        $flosc_da1_rows_post = $flosc_post['da1_rows'] ?? [];
        $flosc_da1_lines = [];
        $flosc_da1_lines[] = implode("\t", array_map('flosc_da1_tsv_cell', $flosc_da1_saved_columns));
        $flosc_da1_validation_errors = [];
        $flosc_da1_required_value_columns = [
            'Row Key',
            'Catalog Key',
            'Record Type',
            'Flow Scope',
            'VGM',
            'Delivery Instruction',
            'Delivery Rule',
            'Fallback Order',
            'Status',
        ];

        foreach ($flosc_da1_rows_post as $flosc_da1_row_post) {
            $flosc_da1_cells = [];
            $flosc_da1_all_empty = true;
            $flosc_da1_row_values = [];
            foreach ($flosc_da1_saved_columns as $flosc_da1_ci => $flosc_da1_col) {
                $flosc_da1_raw = isset($flosc_da1_row_post[$flosc_da1_ci]) ? (string) $flosc_da1_row_post[$flosc_da1_ci] : '';
                $flosc_da1_is_multi = (bool) preg_match('/description|lyrics|media|notes/i', $flosc_da1_col);
                $flosc_da1_val = $flosc_da1_is_multi ? sanitize_textarea_field($flosc_da1_raw) : sanitize_text_field($flosc_da1_raw);
                $flosc_da1_val = str_replace(["\r\n", "\r"], "\n", $flosc_da1_val);

                if ($flosc_da1_col === 'Status') {
                    $flosc_da1_val = strtolower(trim($flosc_da1_val));
                }
                if ($flosc_da1_col === 'VGM') {
                    $flosc_da1_val = flosc_da1_normalize_vgm($flosc_da1_val);
                }
                if ($flosc_da1_col === 'Catalog Key') {
                    $flosc_da1_val = $flosc_da1_requested_catalog_key;
                }

                // Never block a floscAdmin's save on a missing control value:
                // fill the column's safe default so edits always save. Status is
                // always "active" unless explicitly "paused".
                if (trim($flosc_da1_val) === '' && isset($flosc_control_defaults[$flosc_da1_col])) {
                    $flosc_da1_val = $flosc_control_defaults[$flosc_da1_col];
                }
                if ($flosc_da1_col === 'Status') {
                    $flosc_da1_val = ($flosc_da1_val === 'paused') ? 'paused' : 'active';
                }

                if (trim($flosc_da1_val) !== '') {
                    $flosc_da1_all_empty = false;
                }
                $flosc_da1_row_values[$flosc_da1_col] = $flosc_da1_val;
                $flosc_da1_cells[] = flosc_da1_tsv_cell($flosc_da1_val);
            }

            if (!$flosc_da1_all_empty) {
                $flosc_da1_row_key_idx = $flosc_da1_saved_col_idx['Row Key'];
                $flosc_da1_incoming_parent = trim((string) ($flosc_da1_row_values['Parent Key'] ?? ''));
                if (trim((string) ($flosc_da1_row_post[$flosc_da1_row_key_idx] ?? '')) === '') {
                    if ($flosc_da1_incoming_parent !== '') {
                        $flosc_da1_row_values['Row Key'] = flosc_da1_next_child_key($flosc_da1_rows, $flosc_da1_saved_col_idx['Row Key'], $flosc_da1_incoming_parent);
                    } else {
                        $flosc_da1_row_values['Row Key'] = flosc_da1_next_parent_key($flosc_da1_rows, $flosc_da1_saved_col_idx['Row Key']);
                    }
                    $flosc_da1_cells[$flosc_da1_row_key_idx] = flosc_da1_tsv_cell($flosc_da1_row_values['Row Key']);
                }

                foreach ($flosc_da1_required_value_columns as $flosc_da1_required_col) {
                    if (trim((string) ($flosc_da1_row_values[$flosc_da1_required_col] ?? '')) === '') {
                        $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_values['Row Key'] . ': ' . $flosc_da1_required_col . ' is required.';
                    }
                }

                $flosc_da1_row_key = trim((string) ($flosc_da1_row_values['Row Key'] ?? ''));
                $flosc_da1_parent_key = trim((string) ($flosc_da1_row_values['Parent Key'] ?? ''));
                if ($flosc_da1_row_key !== '' && !preg_match('/^[0-9]+(\.[0-9]+)?$/', $flosc_da1_row_key)) {
                    $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_key . ': Row Key must use numeric format like 85 or 85.1.';
                }
                if ($flosc_da1_parent_key !== '' && !preg_match('/^[0-9]+$/', $flosc_da1_parent_key)) {
                    $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_key . ': Parent Key must be numeric like 85.';
                }
                if ($flosc_da1_parent_key !== '' && strpos($flosc_da1_row_key, $flosc_da1_parent_key . '.') !== 0) {
                    $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_key . ': child Row Key must begin with Parent Key plus dot.';
                }

                $flosc_status = strtolower(trim((string) ($flosc_da1_row_values['Status'] ?? '')));
                if ($flosc_status !== 'active' && $flosc_status !== 'paused') {
                    $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_key . ': Status must be active or paused.';
                }

                $flosc_da1_scope_csv = strtolower(trim((string) ($flosc_da1_row_values['Flow Scope'] ?? '')));
                $flosc_da1_scope_parts = array_filter(array_map('trim', explode(',', $flosc_da1_scope_csv)));
                if (empty($flosc_da1_scope_parts)) {
                    $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_key . ': Flow Scope is required.';
                } else {
                    foreach ($flosc_da1_scope_parts as $flosc_da1_scope_part) {
                        if (!in_array($flosc_da1_scope_part, $flosc_known_flow_scopes, true)) {
                            $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_key . ': Flow Scope value "' . $flosc_da1_scope_part . '" is not a known flow key.';
                        }
                    }
                }

                $flosc_da1_vgm = strtolower(trim((string) ($flosc_da1_row_values['VGM'] ?? '')));
                if ($flosc_da1_vgm === '') {
                    $flosc_da1_validation_errors[] = 'Row ' . $flosc_da1_row_key . ': VGM must include at least one of Visitor, Guest, Member.';
                }

                $flosc_da1_lines[] = implode("\t", $flosc_da1_cells);

                $flosc_da1_materialized_row = [];
                foreach ($flosc_da1_saved_columns as $flosc_da1_ci => $flosc_da1_col) {
                    $flosc_da1_materialized_row[$flosc_da1_ci] = trim((string) ($flosc_da1_row_values[$flosc_da1_col] ?? ''));
                }
                $flosc_da1_rows[] = $flosc_da1_materialized_row;
            }
        }

        if (!empty($flosc_da1_validation_errors)) {
            $flosc_da1_notice_error = 'Catalog not saved. Fix these issues: ' . implode(' | ', array_slice($flosc_da1_validation_errors, 0, 8));
        }

        if ($flosc_da1_notice_error === '') {
            $flosc_da1_content = implode("\n", $flosc_da1_lines) . "\n";
            $flosc_da1_write_result = flosc_da1_write_catalog_file($flosc_da1_catalog_path, $flosc_da1_content, $flosc_catalog_dir);
            if (is_wp_error($flosc_da1_write_result)) {
                $flosc_da1_notice_error = $flosc_da1_write_result->get_error_message();
            } else {
                $flosc_da1_notice_success = 'Catalog saved.';
                $flosc_da1_parsed = flosc_da1_parse_tsv($flosc_da1_content);
                $flosc_da1_columns = flosc_da1_normalize_columns($flosc_da1_parsed[0] ?? [], $flosc_required_columns, $flosc_base_payload_columns);
                $flosc_da1_col_idx = flosc_da1_col_index_map($flosc_da1_columns);
                $flosc_da1_rows = array_slice($flosc_da1_parsed, 1);
                $flosc_da1_ncols = count($flosc_da1_columns);
                foreach ($flosc_da1_rows as &$flosc_da1_row) {
                    while (count($flosc_da1_row) < $flosc_da1_ncols) {
                        $flosc_da1_row[] = '';
                    }
                    flosc_da1_apply_defaults($flosc_da1_row, $flosc_da1_columns, $flosc_da1_col_idx, $flosc_control_defaults, $flosc_da1_requested_catalog_key);
                }
                unset($flosc_da1_row);
            }
        }
    }
}

$flosc_da1_multiline_idx = [];
foreach ($flosc_da1_columns as $flosc_da1_ci => $flosc_da1_col) {
    if (preg_match('/description|lyrics|media|notes/i', $flosc_da1_col)) {
        $flosc_da1_multiline_idx[] = $flosc_da1_ci;
    }
}

if (empty($flosc_da1_rows)) {
    $flosc_da1_rows = [array_fill(0, count($flosc_da1_columns), '')];
    flosc_da1_apply_defaults($flosc_da1_rows[0], $flosc_da1_columns, $flosc_da1_col_idx, $flosc_control_defaults, $flosc_da1_requested_catalog_key);
    if (isset($flosc_da1_col_idx['Row Key'])) {
        $flosc_da1_rows[0][$flosc_da1_col_idx['Row Key']] = '1';
    }
}

$flosc_da1_flow_assigned = $flosc_da1_selected_ivr !== '' && isset($flosc_da1_flow_assignments[$flosc_da1_selected_ivr]) ? implode(',', (array) $flosc_da1_flow_assignments[$flosc_da1_selected_ivr]) : $flosc_default_catalog_key;
$flosc_da1_export_url = add_query_arg([
    'page' => 'flosc-settings',
    'tab' => 'da1',
    'catalog' => $flosc_da1_requested_catalog_key,
    'da1_export' => '1',
    '_wpnonce' => wp_create_nonce('flosc_da1_export_' . $flosc_da1_requested_catalog_key),
], admin_url('admin.php'));

$flosc_da1_form_action_args = [
    'page' => 'flosc-settings',
    'tab' => 'da1',
    'catalog' => $flosc_da1_requested_catalog_key,
];
if ($flosc_da1_selected_ivr !== '') {
    $flosc_da1_form_action_args['ivr'] = $flosc_da1_selected_ivr;
}
if (!empty($flosc_identity_view)) {
    $flosc_da1_form_action_args['view'] = $flosc_identity_view;
}
$flosc_da1_form_action = add_query_arg($flosc_da1_form_action_args, admin_url('admin.php'));

if ( isset( $flosc_da1_get['da1_export'] ) && (string) $flosc_da1_get['da1_export'] === '1' ) {
	$flosc_da1_nonce = sanitize_text_field( (string) ( $flosc_da1_get['_wpnonce'] ?? '' ) );
	if ( ! wp_verify_nonce( $flosc_da1_nonce, 'flosc_da1_export_' . $flosc_da1_requested_catalog_key ) ) {
		wp_die( esc_html__( 'Invalid export link.', 'flosc' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export DA1 catalogs.', 'flosc' ) );
	}

	$flosc_export_body = flosc_da1_read_catalog_file( $flosc_da1_catalog_path, $flosc_catalog_dir );
	if ( is_wp_error( $flosc_export_body ) || ! is_string( $flosc_export_body ) || $flosc_export_body === '' ) {
		wp_die( esc_html__( 'Could not read DA1 catalog for export.', 'flosc' ) );
	}

	$flosc_da1_fs = flosc_da1_filesystem();
	if ( ! $flosc_da1_fs ) {
		wp_die( esc_html__( 'Filesystem unavailable for export.', 'flosc' ) );
	}
	$flosc_da1_fs->stream_plain_download_and_exit(
		$flosc_export_body,
		'text/tab-separated-values; charset=utf-8',
		'flosc_da1_catalog_' . sanitize_file_name( $flosc_da1_requested_catalog_key ) . '.tsv'
	);
}
?>

<div class="flosc-da1-wrap">
    <?php if ($flosc_da1_notice_success !== ''): ?>
        <div class="notice notice-success is-dismissible flosc-da1-notice"><p><?php echo esc_html($flosc_da1_notice_success); ?></p></div>
    <?php endif; ?>
    <?php if ($flosc_da1_notice_error !== ''): ?>
        <div class="notice notice-error flosc-da1-notice"><p><strong>Save blocked.</strong> <?php echo esc_html($flosc_da1_notice_error); ?></p></div>
    <?php endif; ?>

    <div class="flosc-da1-header">
        <div>
            <h2 class="flosc-da1-title">DA1 Catalog</h2>
            <p class="flosc-da1-meta">
                <?php echo esc_html($flosc_da1_selected_catalog_label); ?>
                &mdash;
                <code class="flosc-da1-filename"><?php echo esc_html(basename($flosc_da1_catalog_path)); ?></code>
            </p>
        </div>
        <div class="flosc-da1-actions-top">
            <a href="<?php echo esc_url($flosc_da1_export_url); ?>" class="button">Export TSV</a>
            <button type="button" class="button button-primary da1-save-trigger" data-form-id="da1-catalog-form">Save Catalog</button>
        </div>
    </div>

    <div class="flosc-da1-catalog-tools">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="flosc-da1-tool-form">
            <input type="hidden" name="page" value="flosc-settings">
            <input type="hidden" name="tab" value="da1">
            <?php if ($flosc_da1_selected_ivr !== ''): ?>
                <input type="hidden" name="ivr" value="<?php echo esc_attr($flosc_da1_selected_ivr); ?>">
            <?php endif; ?>
            <label for="flosc-da1-catalog-select"><strong>Catalog</strong></label>
            <select id="flosc-da1-catalog-select" name="catalog">
                <?php foreach ($flosc_da1_catalogs as $flosc_da1_cat_key => $flosc_cat): ?>
                    <option value="<?php echo esc_attr($flosc_da1_cat_key); ?>" <?php selected($flosc_da1_cat_key, $flosc_da1_requested_catalog_key); ?>>
                        <?php echo esc_html(($flosc_cat['label'] ?? $flosc_da1_cat_key) . ' [' . $flosc_da1_cat_key . ']'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Open</button>
        </form>

        <form method="post" class="flosc-da1-tool-form">
            <?php wp_nonce_field('flosc_da1_catalog_manage'); ?>
            <input type="hidden" name="flosc_da1_create_catalog" value="1">
            <label><strong>Create Catalog</strong></label>
            <input type="text" name="flosc_new_catalog_label" placeholder="Label (e.g. Music Core)">
            <input type="text" name="flosc_new_catalog_key" placeholder="Key (e.g. music_core)">
            <button type="submit" class="button">Create</button>
        </form>

        <form method="post" enctype="multipart/form-data" class="flosc-da1-tool-form">
            <?php wp_nonce_field('flosc_da1_catalog_manage'); ?>
            <input type="hidden" name="flosc_da1_upload_catalog" value="1">
            <label><strong>Upload TSV</strong></label>
            <input type="file" name="flosc_da1_upload_file" accept=".tsv,text/tab-separated-values,text/plain">
            <button type="submit" class="button">Upload to Current Catalog</button>
        </form>

        <?php if ($flosc_da1_selected_ivr !== ''): ?>
            <form method="post" class="flosc-da1-tool-form">
                <?php wp_nonce_field('flosc_da1_catalog_manage'); ?>
                <input type="hidden" name="flosc_da1_assign_catalogs" value="1">
                <label><strong>Flow Catalogs for <?php echo esc_html($flosc_da1_selected_ivr); ?></strong></label>
                <input type="text" name="flosc_flow_catalogs" value="<?php echo esc_attr($flosc_da1_flow_assigned); ?>" class="flosc-da1-flow-catalogs-input">
                <span class="description">Comma-separated catalog keys, ordered by priority.</span>
                <button type="submit" class="button">Save Assignment</button>
            </form>
        <?php endif; ?>
    </div>

    <form id="da1-catalog-form" method="post" action="<?php echo esc_url($flosc_da1_form_action); ?>">
        <input type="hidden" name="page" value="flosc-settings">
        <input type="hidden" name="tab" value="da1">
        <input type="hidden" name="catalog" value="<?php echo esc_attr($flosc_da1_requested_catalog_key); ?>">
        <?php if ($flosc_da1_selected_ivr !== ''): ?>
            <input type="hidden" name="ivr" value="<?php echo esc_attr($flosc_da1_selected_ivr); ?>">
        <?php endif; ?>
        <?php if (!empty($flosc_identity_view)): ?>
            <input type="hidden" name="view" value="<?php echo esc_attr($flosc_identity_view); ?>">
        <?php endif; ?>
        <?php wp_nonce_field('da1_catalog_save'); ?>
        <input type="hidden" name="da1_save_catalog" value="1">
        <input type="hidden" name="da1_payload" value="">

        <?php foreach ($flosc_da1_columns as $flosc_da1_ci => $flosc_da1_col): ?>
            <input type="hidden" name="da1_columns[<?php echo esc_attr((string) $flosc_da1_ci); ?>]" value="<?php echo esc_attr($flosc_da1_col); ?>">
        <?php endforeach; ?>

        <div class="flosc-da1-table-wrap">
            <table id="da1-table" class="flosc-da1-table">
                <thead>
                    <tr class="flosc-da1-head-row">
                        <?php foreach ($flosc_da1_columns as $flosc_da1_col): ?>
                            <th class="flosc-da1-head-cell <?php echo in_array($flosc_da1_col, $flosc_required_columns, true) ? 'flosc-da1-head-cell-control' : 'flosc-da1-head-cell-payload'; ?>"><?php echo esc_html($flosc_da1_col); ?></th>
                        <?php endforeach; ?>
                        <th class="flosc-da1-head-cell flosc-da1-head-cell-actions"></th>
                    </tr>
                </thead>
                <tbody id="da1-tbody">
                    <?php foreach ($flosc_da1_rows as $flosc_da1_ri => $flosc_da1_row): ?>
                        <tr class="da1-row flosc-da1-row">
                            <?php foreach ($flosc_da1_columns as $flosc_da1_ci => $flosc_da1_col):
                                $flosc_da1_val = stripslashes($flosc_da1_row[$flosc_da1_ci] ?? '');
                                $flosc_da1_is_multi = in_array($flosc_da1_ci, $flosc_da1_multiline_idx, true);
                                $flosc_da1_name = 'da1_rows[' . $flosc_da1_ri . '][' . $flosc_da1_ci . ']';
                            ?>
                                <td class="flosc-da1-cell <?php echo in_array($flosc_da1_col, $flosc_required_columns, true) ? 'flosc-da1-cell-control' : 'flosc-da1-cell-payload'; ?>" data-col="<?php echo esc_attr($flosc_da1_col); ?>">
                                    <?php if ($flosc_da1_is_multi): ?>
                                        <textarea name="<?php echo esc_attr($flosc_da1_name); ?>" rows="3" class="flosc-da1-input flosc-da1-textarea"><?php echo esc_textarea($flosc_da1_val); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" name="<?php echo esc_attr($flosc_da1_name); ?>" value="<?php echo esc_attr($flosc_da1_val); ?>" class="flosc-da1-input">
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="flosc-da1-cell flosc-da1-cell-actions">
                                <button type="button" class="da1-open-modal button button-small flosc-da1-edit-btn">Edit</button>
                                <button type="button" class="da1-add-related button button-small">+ Child Entry</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flosc-da1-actions-bottom">
            <button type="button" id="da1-add-entry-bottom" class="button">+ Add Entry</button>
            <span class="description">Procedure: if a media URL was typed into Description, create + Child Entry on that row, paste URL into Media, set Media Type, then remove URL from Description.</span>
            <button type="button" class="button button-primary button-large da1-save-trigger" data-form-id="da1-catalog-form">Save Catalog</button>
        </div>
    </form>
</div>

<?php ob_start(); ?>
(function () {
    var COLUMNS = <?php echo wp_json_encode(array_values($flosc_da1_columns)); ?>;
    var NCOLS = <?php echo (int) $flosc_da1_ncols; ?>;
    var MULTILINE = <?php echo wp_json_encode(array_values($flosc_da1_multiline_idx)); ?>;

    function colIndex(name) {
        return COLUMNS.indexOf(name);
    }

    function nextIndex() {
        return document.querySelectorAll('#da1-tbody .da1-row').length;
    }

    function nextParentKey() {
        var iRow = colIndex('Row Key');
        var maxVal = 0;
        document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
            var input = tr.querySelector('[name*="[' + iRow + ']"]');
            if (!input) return;
            var v = (input.value || '').trim();
            if (/^[0-9]+$/.test(v)) {
                var n = parseInt(v, 10);
                if (n > maxVal) maxVal = n;
            }
        });
        return String(maxVal + 1);
    }

    function nextChildKey(parentKey) {
        var iRow = colIndex('Row Key');
        var maxSuffix = 0;
        var pattern = new RegExp('^' + parentKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\.(\\d+)$');
        document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
            var input = tr.querySelector('[name*="[' + iRow + ']"]');
            if (!input) return;
            var m = (input.value || '').trim().match(pattern);
            if (m) {
                var s = parseInt(m[1], 10);
                if (s > maxSuffix) maxSuffix = s;
            }
        });
        return parentKey + '.' + String(maxSuffix + 1);
    }

    function createInput(ci, name, value) {
        if (MULTILINE.indexOf(ci) !== -1) {
            var ta = document.createElement('textarea');
            ta.name = name;
            ta.rows = 3;
            ta.className = 'flosc-da1-input flosc-da1-textarea';
            ta.value = value || '';
            return ta;
        }
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.name = name;
        inp.className = 'flosc-da1-input';
        inp.value = value || '';
        return inp;
    }

    function makeRow(seed) {
        var ri = nextIndex();
        var tr = document.createElement('tr');
        tr.className = 'da1-row flosc-da1-row';

        for (var ci = 0; ci < NCOLS; ci++) {
            var td = document.createElement('td');
            td.className = 'flosc-da1-cell';
            var name = 'da1_rows[' + ri + '][' + ci + ']';
            var col = COLUMNS[ci];
            var val = (seed && seed[col]) ? seed[col] : '';
            td.appendChild(createInput(ci, name, val));
            tr.appendChild(td);
        }

        var tdBtn = document.createElement('td');
        tdBtn.className = 'flosc-da1-cell flosc-da1-cell-actions';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'da1-open-modal button button-small flosc-da1-edit-btn';
        btn.textContent = 'Edit';
        tdBtn.appendChild(btn);
        tr.appendChild(tdBtn);

        return tr;
    }

    function readRowObj(tr) {
        var obj = {};
        COLUMNS.forEach(function (col, ci) {
            var f = tr.querySelector('[name*="[' + ci + ']"]');
            obj[col] = f ? f.value : '';
        });
        return obj;
    }

    function addEntryRow() {
        var rowKey = nextParentKey();
        var seed = {
            'Row Key': rowKey,
            'Parent Key': '',
            'Record Type': 'work',
            'Status': 'active',
            'VGM': 'Visitor Guest Member',
            'Flow Scope': 'all'
        };
        var tr = makeRow(seed);
        document.getElementById('da1-tbody').appendChild(tr);
        openModal(tr);
    }

    function addRelatedRowFrom(baseTr) {
        var data = readRowObj(baseTr);
        var parent = (data['Parent Key'] || '').trim();
        var rowKey = (data['Row Key'] || '').trim();
        if (rowKey === '') {
            rowKey = nextParentKey();
            var iRowKey = colIndex('Row Key');
            var keyInput = baseTr.querySelector('[name*="[' + iRowKey + ']"]');
            if (keyInput) {
                keyInput.value = rowKey;
            }
        }
        var parentKey = parent !== '' ? parent : rowKey;
        if (parentKey === '') {
            alert('Entry key is required before adding a child entry.');
            return;
        }
        var seed = {
            'Row Key': nextChildKey(parentKey),
            'Parent Key': parentKey,
            'Catalog Key': data['Catalog Key'] || '',
            'Record Type': data['Record Type'] && data['Record Type'] !== 'work' ? data['Record Type'] : 'item',
            'Flow Scope': data['Flow Scope'] || 'all',
            'VGM': data['VGM'] || 'Visitor Guest Member',
            'Delivery Instruction': data['Delivery Instruction'] || 'intent match',
            'Delivery Rule': data['Delivery Rule'] || 'preference',
            'Fallback Order': data['Fallback Order'] || 'chatplayer > media-link > text',
            'Status': 'active',
            'Date': data['Date'] || '',
            'Title': data['Title'] || '',
            'Media Type': data['Media Type'] || 'chatplayer'
        };
        var tr = makeRow(seed);
        document.getElementById('da1-tbody').appendChild(tr);
        openModal(tr);
    }

    var overlay = null;
    var body = null;
    var titleNode = null;
    var activeRow = null;

    function ensureModal() {
        if (overlay) return;
        overlay = document.createElement('div');
        overlay.className = 'flosc-da1-modal-overlay';
        overlay.id = 'da1-modal-overlay';
        overlay.innerHTML = [
            '<div class="flosc-da1-modal">',
            '<div class="flosc-da1-modal-header">',
            '<span id="da1-modal-title">Edit Row</span>',
            '<button type="button" id="da1-modal-close" class="flosc-da1-modal-close">&times;</button>',
            '</div>',
            '<div id="da1-modal-body" class="flosc-da1-modal-body"></div>',
            '<div class="flosc-da1-modal-footer">',
            '<button type="button" id="da1-modal-delete" class="flosc-da1-modal-delete">Delete this row</button>',
            '<div class="flosc-da1-modal-actions">',
            '<button type="button" id="da1-modal-cancel" class="button">Cancel</button>',
            '<button type="button" id="da1-modal-save" class="button button-primary">Save</button>',
            '</div>',
            '</div>',
            '</div>'
        ].join('');
        document.body.appendChild(overlay);
        body = document.getElementById('da1-modal-body');
        titleNode = document.getElementById('da1-modal-title');

        document.getElementById('da1-modal-save').addEventListener('click', saveModal);
        document.getElementById('da1-modal-cancel').addEventListener('click', closeModal);
        document.getElementById('da1-modal-close').addEventListener('click', closeModal);
        document.getElementById('da1-modal-delete').addEventListener('click', function () {
            if (activeRow) {
                activeRow.remove();
            }
            closeModal();
        });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeModal();
            }
        });
    }

    function getRowFields(tr) {
        return Array.prototype.slice.call(tr.querySelectorAll('input[name*="da1_rows"], textarea[name*="da1_rows"]'));
    }

    function openModal(tr) {
        ensureModal();
        activeRow = tr;
        body.innerHTML = '';
        var fields = getRowFields(tr);
        var title = '';
        var tIdx = colIndex('Title');
        if (tIdx >= 0 && fields[tIdx]) {
            title = fields[tIdx].value.trim();
        }
        titleNode.textContent = title || 'Edit Row';

        COLUMNS.forEach(function (col, ci) {
            var wrap = document.createElement('div');
            wrap.className = 'flosc-da1-modal-field';
            var lbl = document.createElement('label');
            lbl.className = 'flosc-da1-modal-label';
            lbl.setAttribute('for', 'da1m-' + ci);
            lbl.textContent = col;
            wrap.appendChild(lbl);

            var input;
            if (MULTILINE.indexOf(ci) !== -1) {
                input = document.createElement('textarea');
                input.rows = 6;
                input.className = 'flosc-da1-modal-input flosc-da1-modal-textarea';
            } else {
                input = document.createElement('input');
                input.type = 'text';
                input.className = 'flosc-da1-modal-input';
            }
            input.id = 'da1m-' + ci;
            input.setAttribute('data-ci', String(ci));
            input.value = fields[ci] ? fields[ci].value : '';
            wrap.appendChild(input);

            if (col === 'Status') {
                var hint = document.createElement('p');
                hint.className = 'flosc-da1-modal-hint';
                hint.textContent = 'Allowed values: active, paused.';
                wrap.appendChild(hint);
            }
            body.appendChild(wrap);
        });

        overlay.style.display = 'flex';
    }

    function saveModal() {
        if (!activeRow) return;
        var fields = getRowFields(activeRow);
        body.querySelectorAll('[data-ci]').forEach(function (el) {
            var ci = parseInt(el.getAttribute('data-ci'), 10);
            if (fields[ci]) {
                fields[ci].value = el.value;
            }
        });
        closeModal();
    }

    function closeModal() {
        if (overlay) {
            overlay.style.display = 'none';
        }
        activeRow = null;
    }

    var btnAddEntry = document.getElementById('da1-add-entry-bottom');
    if (btnAddEntry) {
        btnAddEntry.addEventListener('click', addEntryRow);
    }

    document.getElementById('da1-tbody').addEventListener('click', function (e) {
        var row = e.target.closest('.da1-row');
        if (row) {
            document.querySelectorAll('#da1-tbody .da1-row.is-da1-active-row').forEach(function (r) {
                r.classList.remove('is-da1-active-row');
            });
            row.classList.add('is-da1-active-row');
        }
        var edit = e.target.closest('.da1-open-modal');
        if (edit) {
            openModal(edit.closest('.da1-row'));
            return;
        }
        var plus = e.target.closest('.da1-add-related');
        if (plus) {
            addRelatedRowFrom(plus.closest('.da1-row'));
        }
    });

    // Save: serialize the whole table into one JSON field and disable the
    // per-cell inputs, so the POST carries a few fields instead of thousands
    // (PHP max_input_vars). Works for a catalog of any size.
    Array.prototype.forEach.call(document.querySelectorAll('.da1-save-trigger'), function (btn) {
        btn.addEventListener('click', function () {
            var form = document.getElementById('da1-catalog-form');
            if (!form) return;
            var rows = [];
            document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
                var cells = [];
                tr.querySelectorAll('[name^="da1_rows"]').forEach(function (f) { cells.push(f.value); });
                rows.push(cells);
            });
            var payload = form.querySelector('input[name="da1_payload"]');
            if (payload) { payload.value = JSON.stringify({ columns: COLUMNS, rows: rows }); }
            form.querySelectorAll('[name^="da1_rows"], [name^="da1_columns"]').forEach(function (el) { el.disabled = true; });
            form.submit();
        });
    });
})();
<?php
wp_add_inline_script('flosc-admin', ob_get_clean());
