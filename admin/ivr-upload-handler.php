<?php
/**
 * Flow Portability upload handler (admin_init).
 *
 * Supports one kit drop for a full pack:
 * - .md  — create flow or apply IVR + Settings YAML
 * - .tsv — DA1 catalog(s), assigned to the flow (up to 10)
 * - .xml — WXR staged under uploads/flosc-packs/ and listed (import via WP tool / Import posts action)
 * - media — PDF and common images/audio into Media Library, tracked on the flow
 *
 * Must run on admin_init before admin HTML so redirects work.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_portability_normalize_ivr_filename' ) ) {
	/**
	 * @param string $raw_name Original upload basename.
	 * @return string Sanitized *_ivr.md name.
	 */
	function flosc_portability_normalize_ivr_filename( $raw_name ) {
		$filename = sanitize_file_name( basename( (string) $raw_name ) );
		$ext      = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		$stem     = (string) pathinfo( $filename, PATHINFO_FILENAME );
		if ( 'md' === $ext && ! preg_match( '/_ivr$/i', $stem ) ) {
			$filename = sanitize_file_name( $stem . '_ivr.md' );
		}
		return $filename;
	}
}

if ( ! function_exists( 'flosc_portability_display_name_from_stem' ) ) {
	/**
	 * @param string $stem Flow stem (e.g. vegan_latvian_kitchen_ivr).
	 * @return string
	 */
	function flosc_portability_display_name_from_stem( $stem ) {
		$display = preg_replace( '/_ivr$/i', '', (string) $stem );
		$display = trim(
			(string) preg_replace(
				'/\s+/',
				' ',
				str_replace( array( 'flosc_', 'flosc-', '_', '-' ), array( '', '', ' ', ' ' ), (string) $display )
			)
		);
		return ( '' !== $display ) ? ucwords( $display ) : (string) $stem;
	}
}

if ( ! function_exists( 'flosc_portability_collect_kit_files' ) ) {
	/**
	 * Normalize multi or single file upload into list of {name,tmp,error,size,ext}.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	function flosc_portability_collect_kit_files() {
		$out = array();

		// Multi: flosc_kit_files[] (PHP may give string for a single file).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies nonce.
		if ( ! empty( $_FILES['flosc_kit_files']['name'] ) ) {
			$names = $_FILES['flosc_kit_files']['name'];
			$tmps  = $_FILES['flosc_kit_files']['tmp_name'] ?? array();
			$errs  = $_FILES['flosc_kit_files']['error'] ?? array();
			$sizes = $_FILES['flosc_kit_files']['size'] ?? array();
			if ( ! is_array( $names ) ) {
				$names = array( $names );
				$tmps  = array( $tmps );
				$errs  = array( $errs );
				$sizes = array( $sizes );
			}
			foreach ( $names as $i => $name ) {
				$out[] = array(
					'name'  => isset( $name ) ? wp_unslash( (string) $name ) : '',
					'tmp'   => (string) ( $tmps[ $i ] ?? '' ),
					'error' => (int) ( $errs[ $i ] ?? UPLOAD_ERR_NO_FILE ),
					'size'  => (int) ( $sizes[ $i ] ?? 0 ),
					'ext'   => strtolower( (string) pathinfo( (string) $name, PATHINFO_EXTENSION ) ),
				);
			}
			return $out;
		}

		// Legacy single: ivr_file_upload
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies nonce.
		if ( ! empty( $_FILES['ivr_file_upload']['name'] ) ) {
			$out[] = array(
				'name'  => wp_unslash( (string) $_FILES['ivr_file_upload']['name'] ),
				'tmp'   => (string) ( $_FILES['ivr_file_upload']['tmp_name'] ?? '' ),
				'error' => (int) ( $_FILES['ivr_file_upload']['error'] ?? UPLOAD_ERR_NO_FILE ),
				'size'  => (int) ( $_FILES['ivr_file_upload']['size'] ?? 0 ),
				'ext'   => strtolower( (string) pathinfo( (string) $_FILES['ivr_file_upload']['name'], PATHINFO_EXTENSION ) ),
			);
		}

		return $out;
	}
}

if ( ! function_exists( 'flosc_portability_ingest_da1_tsv' ) ) {
	/**
	 * Store uploaded DA1 TSV and assign catalog to a flow IVR filename.
	 *
	 * @param string $tmp_name  Upload temp path.
	 * @param string $raw_name  Original filename.
	 * @param string $ivr_file  Flow IVR basename to assign (e.g. foo_ivr.md).
	 * @return true|WP_Error
	 */
	function flosc_portability_ingest_da1_tsv( $tmp_name, $raw_name, $ivr_file ) {
		$tmp_name = (string) $tmp_name;
		$raw_name = sanitize_file_name( basename( (string) $raw_name ) );
		$ivr_file = sanitize_file_name( (string) $ivr_file );

		if ( $tmp_name === '' || ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'flosc_da1_tmp', __( 'DA1 upload could not be verified.', 'flosc' ) );
		}
		if ( strtolower( (string) pathinfo( $raw_name, PATHINFO_EXTENSION ) ) !== 'tsv' ) {
			return new WP_Error( 'flosc_da1_ext', __( 'DA1 catalog must be a .tsv file.', 'flosc' ) );
		}
		if ( $ivr_file === '' ) {
			return new WP_Error( 'flosc_da1_flow', __( 'No current flow to assign the DA1 catalog to.', 'flosc' ) );
		}

		$body = function_exists( 'flosc_fs_get_contents' )
			? flosc_fs_get_contents( $tmp_name )
			: file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- upload temp after is_uploaded_file.
		if ( false === $body || trim( (string) $body ) === '' ) {
			return new WP_Error( 'flosc_da1_empty', __( 'DA1 catalog is empty or unreadable.', 'flosc' ) );
		}
		if ( strlen( (string) $body ) > 1024 * 1024 ) {
			return new WP_Error( 'flosc_da1_size', __( 'DA1 catalog must be 1 MB or smaller.', 'flosc' ) );
		}

		$stem = (string) pathinfo( $raw_name, PATHINFO_FILENAME );
		// flosc_da1_catalog_vegan_latvian_kitchen → vegan_latvian_kitchen
		$stem = preg_replace( '/^flosc_da1_catalog_/i', '', $stem );
		$stem = preg_replace( '/^flosc_da1_/i', '', (string) $stem );
		$key  = sanitize_key( str_replace( array( ' ', '-' ), '_', (string) $stem ) );
		if ( $key === '' ) {
			$key = 'catalog_' . substr( md5( $raw_name . microtime( true ) ), 0, 8 );
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'flosc_da1_uploads', __( 'Uploads directory is not available for DA1 catalogs.', 'flosc' ) );
		}
		$catalog_dir = trailingslashit( $upload['basedir'] ) . 'flosc-catalogs';
		if ( ! wp_mkdir_p( $catalog_dir ) ) {
			return new WP_Error( 'flosc_da1_mkdir', __( 'Could not create DA1 catalog directory.', 'flosc' ) );
		}

		$filename = 'flosc_da1_catalog_' . $key . '.tsv';
		$path     = trailingslashit( $catalog_dir ) . $filename;

		$written = false;
		if ( function_exists( 'flosc_da1_write_catalog_file' ) ) {
			$res = flosc_da1_write_catalog_file( $path, (string) $body, $catalog_dir );
			$written = ! is_wp_error( $res );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		} elseif ( function_exists( 'flosc_write_data_file' ) ) {
			// Not under flosc_data_dir — write via filesystem class if possible.
			$fs = null;
			if ( class_exists( 'FLOSC_Filesystem' ) ) {
				$fs = new FLOSC_Filesystem();
			}
			if ( $fs && method_exists( $fs, 'write_file_safely' ) ) {
				$written = (bool) $fs->write_file_safely( $path, (string) $body );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- DA1 catalog under uploads/flosc-catalogs after is_uploaded_file.
				$written = false !== file_put_contents( $path, (string) $body );
			}
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- DA1 catalog under uploads/flosc-catalogs after is_uploaded_file.
			$written = false !== file_put_contents( $path, (string) $body );
		}

		if ( ! $written ) {
			return new WP_Error( 'flosc_da1_write', __( 'Could not write DA1 catalog file.', 'flosc' ) );
		}

		$index = get_option( 'flosc_da1_catalogs', array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		$label = flosc_portability_display_name_from_stem( $key );
		$index[ $key ] = array(
			'label'      => $label,
			'key'        => $key,
			'filename'   => $filename,
			'created_at' => current_time( 'mysql' ),
		);
		update_option( 'flosc_da1_catalogs', $index, false );

		// One flow may have multiple catalogs: add this key; do not wipe prior assignments.
		$assign = get_option( 'flosc_da1_flow_catalogs', array() );
		if ( ! is_array( $assign ) ) {
			$assign = array();
		}
		$existing = isset( $assign[ $ivr_file ] ) && is_array( $assign[ $ivr_file ] )
			? $assign[ $ivr_file ]
			: array();
		$clean    = array();
		foreach ( $existing as $ex ) {
			$ex = sanitize_key( (string) $ex );
			if ( $ex !== '' && ! in_array( $ex, $clean, true ) ) {
				$clean[] = $ex;
			}
		}
		if ( ! in_array( $key, $clean, true ) ) {
			$clean[] = $key;
		}
		$assign[ $ivr_file ] = $clean;
		update_option( 'flosc_da1_flow_catalogs', $assign, false );

		return true;
	}
}

if ( ! function_exists( 'flosc_portability_pack_assets_option_key' ) ) {
	/**
	 * @return string
	 */
	function flosc_portability_pack_assets_option_key() {
		return 'flosc_flow_pack_assets';
	}
}

if ( ! function_exists( 'flosc_portability_get_pack_assets' ) ) {
	/**
	 * Pack inventory for one flow IVR file.
	 *
	 * @param string $ivr_file Flow IVR basename.
	 * @return array{wxr:array<int,array<string,mixed>>,media:array<int,array<string,mixed>>,catalogs:array<int,string>}
	 */
	function flosc_portability_get_pack_assets( $ivr_file ) {
		$ivr_file = sanitize_file_name( (string) $ivr_file );
		$empty    = array(
			'wxr'      => array(),
			'media'    => array(),
			'catalogs' => array(),
		);
		if ( $ivr_file === '' ) {
			return $empty;
		}
		$all = get_option( flosc_portability_pack_assets_option_key(), array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$row = isset( $all[ $ivr_file ] ) && is_array( $all[ $ivr_file ] ) ? $all[ $ivr_file ] : array();
		$out = array(
			'wxr'      => isset( $row['wxr'] ) && is_array( $row['wxr'] ) ? array_values( $row['wxr'] ) : array(),
			'media'    => isset( $row['media'] ) && is_array( $row['media'] ) ? array_values( $row['media'] ) : array(),
			'catalogs' => array(),
		);
		// Live catalog keys from DA1 assignment (source of truth for .tsv links).
		$assign = get_option( 'flosc_da1_flow_catalogs', array() );
		if ( is_array( $assign ) && isset( $assign[ $ivr_file ] ) && is_array( $assign[ $ivr_file ] ) ) {
			foreach ( $assign[ $ivr_file ] as $ck ) {
				$ck = sanitize_key( (string) $ck );
				if ( $ck !== '' && ! in_array( $ck, $out['catalogs'], true ) ) {
					$out['catalogs'][] = $ck;
				}
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'flosc_portability_save_pack_assets' ) ) {
	/**
	 * @param string               $ivr_file Flow IVR basename.
	 * @param array<string,mixed>  $row      Pack row (wxr + media; catalogs stay in DA1 options).
	 * @return void
	 */
	function flosc_portability_save_pack_assets( $ivr_file, $row ) {
		$ivr_file = sanitize_file_name( (string) $ivr_file );
		if ( $ivr_file === '' ) {
			return;
		}
		$all = get_option( flosc_portability_pack_assets_option_key(), array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$all[ $ivr_file ] = array(
			'wxr'   => isset( $row['wxr'] ) && is_array( $row['wxr'] ) ? array_values( $row['wxr'] ) : array(),
			'media' => isset( $row['media'] ) && is_array( $row['media'] ) ? array_values( $row['media'] ) : array(),
		);
		update_option( flosc_portability_pack_assets_option_key(), $all, false );
	}
}

if ( ! function_exists( 'flosc_portability_pack_dir' ) ) {
	/**
	 * Writable pack directory for one flow stem under uploads.
	 *
	 * @param string $ivr_file Flow IVR basename.
	 * @return string Absolute path or empty.
	 */
	function flosc_portability_pack_dir( $ivr_file ) {
		$ivr_file = sanitize_file_name( (string) $ivr_file );
		$stem     = sanitize_key( pathinfo( $ivr_file, PATHINFO_FILENAME ) );
		if ( $stem === '' ) {
			return '';
		}
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}
		$base = trailingslashit( $upload['basedir'] ) . 'flosc-packs';
		if ( ! wp_mkdir_p( $base ) ) {
			return '';
		}
		// Block directory listing (same pattern as flosc data dir).
		if ( ! file_exists( $base . '/index.php' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writes under wp_upload_dir pack/catalog paths only
			file_put_contents( $base . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		$dir = trailingslashit( $base ) . $stem;
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writes under wp_upload_dir pack/catalog paths only
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		return $dir;
	}
}

if ( ! function_exists( 'flosc_portability_path_is_in_pack_dir' ) ) {
	/**
	 * True when $path resolves under this flow's pack directory.
	 *
	 * @param string $path     Absolute path.
	 * @param string $ivr_file Flow IVR basename.
	 * @return bool
	 */
	function flosc_portability_path_is_in_pack_dir( $path, $ivr_file ) {
		$pack_dir = flosc_portability_pack_dir( $ivr_file );
		if ( $pack_dir === '' || $path === '' ) {
			return false;
		}
		$real_path = realpath( $path );
		$real_dir  = realpath( $pack_dir );
		if ( false === $real_path || false === $real_dir ) {
			return false;
		}
		$real_path = wp_normalize_path( $real_path );
		$real_dir  = trailingslashit( wp_normalize_path( $real_dir ) );
		return 0 === strpos( $real_path, $real_dir );
	}
}

if ( ! function_exists( 'flosc_portability_allowed_media_ext' ) ) {
	/**
	 * @return array<int,string>
	 */
	function flosc_portability_allowed_media_ext() {
		return array( 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'mp4', 'm4a', 'wav', 'ogg', 'webm' );
	}
}

if ( ! function_exists( 'flosc_portability_ingest_wxr' ) ) {
	/**
	 * Stage a WXR/XML export under the flow pack dir and record it.
	 *
	 * @param string $tmp_name Upload temp path.
	 * @param string $raw_name Original filename.
	 * @param string $ivr_file Flow IVR basename.
	 * @return true|WP_Error
	 */
	function flosc_portability_ingest_wxr( $tmp_name, $raw_name, $ivr_file ) {
		$tmp_name = (string) $tmp_name;
		$raw_name = sanitize_file_name( basename( (string) $raw_name ) );
		$ivr_file = sanitize_file_name( (string) $ivr_file );

		if ( $tmp_name === '' || ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'flosc_wxr_tmp', __( 'WXR upload could not be verified.', 'flosc' ) );
		}
		if ( strtolower( (string) pathinfo( $raw_name, PATHINFO_EXTENSION ) ) !== 'xml' ) {
			return new WP_Error( 'flosc_wxr_ext', __( 'WordPress content export must be a .xml (WXR) file.', 'flosc' ) );
		}
		if ( $ivr_file === '' ) {
			return new WP_Error( 'flosc_wxr_flow', __( 'No flow to attach the WXR file to.', 'flosc' ) );
		}
		$size = (int) filesize( $tmp_name );
		if ( $size <= 0 || $size > 15 * 1024 * 1024 ) {
			return new WP_Error( 'flosc_wxr_size', __( 'WXR file must be between 1 byte and 15 MB.', 'flosc' ) );
		}

		$body = function_exists( 'flosc_fs_get_contents' )
			? flosc_fs_get_contents( $tmp_name )
			: file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- upload temp after is_uploaded_file.
		if ( false === $body || trim( (string) $body ) === '' ) {
			return new WP_Error( 'flosc_wxr_empty', __( 'WXR file is empty or unreadable.', 'flosc' ) );
		}
		// Light sanity: real WXR / RSS-ish export, not random XML.
		if ( stripos( (string) $body, '<rss' ) === false && stripos( (string) $body, 'xmlns:wp' ) === false && stripos( (string) $body, '<channel' ) === false ) {
			return new WP_Error( 'flosc_wxr_format', __( 'File does not look like a WordPress WXR export.', 'flosc' ) );
		}

		$pack_dir = flosc_portability_pack_dir( $ivr_file );
		if ( $pack_dir === '' ) {
			return new WP_Error( 'flosc_wxr_dir', __( 'Could not create flow pack directory for WXR.', 'flosc' ) );
		}

		$filename = $raw_name;
		if ( strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) ) !== 'xml' ) {
			$filename = sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) . '.xml' );
		}
		$path = trailingslashit( $pack_dir ) . $filename;

		$written = false;
		if ( class_exists( 'FLOSC_Filesystem' ) ) {
			$fs = new FLOSC_Filesystem();
			if ( method_exists( $fs, 'write_file_safely' ) ) {
				$written = (bool) $fs->write_file_safely( $path, (string) $body );
			}
		}
		if ( ! $written ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writes under wp_upload_dir pack/catalog paths only
			$written = false !== file_put_contents( $path, (string) $body );
		}
		if ( ! $written ) {
			return new WP_Error( 'flosc_wxr_write', __( 'Could not store the WXR file.', 'flosc' ) );
		}

		$upload  = wp_upload_dir();
		$rel     = '';
		$url     = '';
		if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) && 0 === strpos( $path, (string) $upload['basedir'] ) ) {
			$rel = ltrim( str_replace( (string) $upload['basedir'], '', $path ), '/\\' );
			$url = trailingslashit( (string) $upload['baseurl'] ) . str_replace( '\\', '/', $rel );
		}

		$pack = flosc_portability_get_pack_assets( $ivr_file );
		// Replace same filename if re-uploaded.
		$wxr = array();
		foreach ( $pack['wxr'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $item['filename'] ) && (string) $item['filename'] === $filename ) {
				continue;
			}
			$wxr[] = $item;
		}
		$wxr[] = array(
			'filename'    => $filename,
			'path'        => $path,
			'rel'         => $rel,
			'url'         => $url,
			'status'      => 'staged',
			'bytes'       => $size,
			'uploaded_at' => current_time( 'mysql' ),
		);
		$pack['wxr'] = $wxr;
		flosc_portability_save_pack_assets( $ivr_file, $pack );

		return true;
	}
}

if ( ! function_exists( 'flosc_portability_ingest_media' ) ) {
	/**
	 * Upload media into the Media Library and track on the flow pack.
	 *
	 * @param string $tmp_name Upload temp path.
	 * @param string $raw_name Original filename.
	 * @param string $ivr_file Flow IVR basename.
	 * @return true|WP_Error
	 */
	function flosc_portability_ingest_media( $tmp_name, $raw_name, $ivr_file ) {
		$tmp_name = (string) $tmp_name;
		$raw_name = sanitize_file_name( basename( (string) $raw_name ) );
		$ivr_file = sanitize_file_name( (string) $ivr_file );
		$ext      = strtolower( (string) pathinfo( $raw_name, PATHINFO_EXTENSION ) );

		if ( $tmp_name === '' || ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'flosc_media_tmp', __( 'Media upload could not be verified.', 'flosc' ) );
		}
		if ( ! in_array( $ext, flosc_portability_allowed_media_ext(), true ) ) {
			return new WP_Error( 'flosc_media_ext', __( 'Media type not allowed for Flow Portability.', 'flosc' ) );
		}
		if ( $ivr_file === '' ) {
			return new WP_Error( 'flosc_media_flow', __( 'No flow to attach media to.', 'flosc' ) );
		}

		$max = (int) wp_max_upload_size();
		if ( $max <= 0 ) {
			$max = 15 * 1024 * 1024;
		}
		$size = (int) filesize( $tmp_name );
		if ( $size <= 0 || $size > $max ) {
			return new WP_Error( 'flosc_media_size', __( 'Media file is empty or larger than the site upload limit.', 'flosc' ) );
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// media_handle_sideload expects a $_FILES-like array and moves the temp file.
		$file_array = array(
			'name'     => $raw_name,
			'tmp_name' => $tmp_name,
			'error'    => 0,
			'size'     => $size,
		);
		$attachment_id = media_handle_sideload( $file_array, 0, null, array( 'test_form' => false ) );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return new WP_Error( 'flosc_media_id', __( 'Media Library did not return an attachment.', 'flosc' ) );
		}

		// Tag for later filtering.
		update_post_meta( $attachment_id, '_flosc_flow_ivr', $ivr_file );

		$pack  = flosc_portability_get_pack_assets( $ivr_file );
		$media = array();
		foreach ( $pack['media'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $item['attachment_id'] ) && (int) $item['attachment_id'] === $attachment_id ) {
				continue;
			}
			$media[] = $item;
		}
		$media[] = array(
			'attachment_id' => $attachment_id,
			'filename'      => $raw_name,
			'url'           => (string) wp_get_attachment_url( $attachment_id ),
			'mime'          => (string) get_post_mime_type( $attachment_id ),
			'uploaded_at'   => current_time( 'mysql' ),
		);
		$pack['media'] = $media;
		flosc_portability_save_pack_assets( $ivr_file, $pack );

		return true;
	}
}

if ( ! function_exists( 'flosc_portability_run_wxr_import' ) ) {
	/**
	 * Import a staged WXR if WordPress Importer is available.
	 *
	 * @param string $ivr_file Flow IVR basename.
	 * @param string $filename Staged WXR basename.
	 * @return true|WP_Error
	 */
	function flosc_portability_run_wxr_import( $ivr_file, $filename ) {
		$ivr_file = sanitize_file_name( (string) $ivr_file );
		$filename = sanitize_file_name( (string) $filename );
		$pack     = flosc_portability_get_pack_assets( $ivr_file );
		$path     = '';
		$idx      = -1;
		foreach ( $pack['wxr'] as $i => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $item['filename'] ) && (string) $item['filename'] === $filename ) {
				$path = isset( $item['path'] ) ? (string) $item['path'] : '';
				$idx  = (int) $i;
				break;
			}
		}
		if ( $path === '' || ! file_exists( $path ) ) {
			return new WP_Error( 'flosc_wxr_missing', __( 'Staged WXR file was not found on disk.', 'flosc' ) );
		}
		if ( ! flosc_portability_path_is_in_pack_dir( $path, $ivr_file ) ) {
			return new WP_Error( 'flosc_wxr_path', __( 'Staged WXR path is not inside this flow’s pack directory.', 'flosc' ) );
		}

		// Prefer the WordPress Importer plugin when present.
		if ( ! class_exists( 'WP_Import' ) ) {
			$importer_path = WP_PLUGIN_DIR . '/wordpress-importer/wordpress-importer.php';
			if ( file_exists( $importer_path ) ) {
				// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- known plugin path under WP_PLUGIN_DIR.
				require_once $importer_path;
			}
		}
		if ( ! class_exists( 'WP_Import' ) ) {
			return new WP_Error(
				'flosc_wxr_importer',
				__( 'Install and activate the WordPress Importer plugin, then use Import posts again — or use Tools → Import.', 'flosc' )
			);
		}

		if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
			define( 'WP_LOAD_IMPORTERS', true );
		}
		if ( ! function_exists( 'wordpress_importer_init' ) && function_exists( 'get_plugins' ) ) {
			// Class may load without full bootstrap; try import.php helpers.
			require_once ABSPATH . 'wp-admin/includes/import.php';
		}

		// Suppress HTML output from the importer UI classes.
		ob_start();
		$importer = new WP_Import();
		if ( method_exists( $importer, 'fetch_attachments' ) ) {
			$importer->fetch_attachments = true;
		}
		// import() is the public entry on classic WordPress Importer.
		if ( method_exists( $importer, 'import' ) ) {
			$importer->import( $path );
		} else {
			ob_end_clean();
			return new WP_Error( 'flosc_wxr_api', __( 'WordPress Importer API is not available on this site.', 'flosc' ) );
		}
		ob_end_clean();

		if ( $idx >= 0 && isset( $pack['wxr'][ $idx ] ) && is_array( $pack['wxr'][ $idx ] ) ) {
			$pack['wxr'][ $idx ]['status']     = 'imported';
			$pack['wxr'][ $idx ]['imported_at'] = current_time( 'mysql' );
			flosc_portability_save_pack_assets( $ivr_file, $pack );
		}

		return true;
	}
}

if ( ! function_exists( 'flosc_admin_handle_portability_pack_actions' ) ) {
	/**
	 * Non-upload pack actions: import staged WXR, remove tracked asset.
	 *
	 * @return void
	 */
	function flosc_admin_handle_portability_pack_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$action = isset( $_POST['flosc_portability_pack_action'] )
			? sanitize_key( (string) wp_unslash( $_POST['flosc_portability_pack_action'] ) )
			: '';
		if ( $action === '' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage flow pack files.', 'flosc' ) );
		}
		check_admin_referer( 'flosc_portability_pack' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$ivr_file = isset( $_POST['flosc_working_ivr'] )
			? sanitize_file_name( (string) wp_unslash( $_POST['flosc_working_ivr'] ) )
			: '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$filename = isset( $_POST['flosc_pack_filename'] )
			? sanitize_file_name( (string) wp_unslash( $_POST['flosc_pack_filename'] ) )
			: '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$attachment_id = isset( $_POST['flosc_pack_attachment_id'] )
			? (int) $_POST['flosc_pack_attachment_id']
			: 0;

		$notes   = array();
		$is_error = false;
		if ( 'import_wxr' === $action ) {
			$result = flosc_portability_run_wxr_import( $ivr_file, $filename );
			if ( is_wp_error( $result ) ) {
				$is_error = true;
				$notes[]  = $result->get_error_message();
			} else {
				$notes[] = sprintf(
					/* translators: %s: WXR filename */
					__( 'Imported posts from %s.', 'flosc' ),
					$filename
				);
			}
		} elseif ( 'remove_wxr' === $action ) {
			$pack = flosc_portability_get_pack_assets( $ivr_file );
			$wxr  = array();
			foreach ( $pack['wxr'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( isset( $item['filename'] ) && (string) $item['filename'] === $filename ) {
					if ( ! empty( $item['path'] ) && is_string( $item['path'] ) && file_exists( $item['path'] )
						&& flosc_portability_path_is_in_pack_dir( (string) $item['path'], $ivr_file ) ) {
						wp_delete_file( $item['path'] );
					}
					continue;
				}
				$wxr[] = $item;
			}
			$pack['wxr'] = $wxr;
			flosc_portability_save_pack_assets( $ivr_file, $pack );
			$notes[] = __( 'Removed WXR from this flow’s pack list.', 'flosc' );
		} elseif ( 'remove_media' === $action ) {
			$pack  = flosc_portability_get_pack_assets( $ivr_file );
			$media = array();
			foreach ( $pack['media'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( isset( $item['attachment_id'] ) && (int) $item['attachment_id'] === $attachment_id ) {
					continue;
				}
				$media[] = $item;
			}
			$pack['media'] = $media;
			flosc_portability_save_pack_assets( $ivr_file, $pack );
			$notes[] = __( 'Unlinked media from this flow’s pack list (file stays in Media Library).', 'flosc' );
		}

		if ( ! empty( $notes ) ) {
			set_transient(
				'flosc_portability_notice_' . get_current_user_id(),
				array(
					'message' => implode( ' ', $notes ),
					'type'    => $is_error ? 'error' : 'success',
				),
				60
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                   => 'flosc-settings',
					'tab'                    => 'flow',
					'ivr'                    => $ivr_file,
					'view'                   => 'all',
					'flosc_portability_done' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

if ( ! function_exists( 'flosc_admin_handle_ivr_file_upload' ) ) {
	/**
	 * Portability kit upload: create/apply flow pack pieces.
	 *
	 * @return void
	 */
	function flosc_admin_handle_ivr_file_upload() {
		if ( ! empty( $GLOBALS['flosc_ivr_upload_handled'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		// Submit value is create|apply (clicked button) — no JS required.
		$submit_raw = isset( $_POST['flosc_portability_submit'] )
			? sanitize_key( (string) wp_unslash( $_POST['flosc_portability_submit'] ) )
			: '';
		$is_kit     = in_array( $submit_raw, array( 'create', 'apply' ), true );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$is_legacy = ! empty( $_POST['flosc_upload_ivr_file'] ) && ! empty( $_FILES['ivr_file_upload']['name'] );

		if ( ! $is_kit && ! $is_legacy ) {
			return;
		}

		$GLOBALS['flosc_ivr_upload_handled'] = true;

		if ( $is_kit ) {
			check_admin_referer( 'flosc_portability_kit' );
		} else {
			check_admin_referer( 'flosc_upload_ivr_file' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload flow files.', 'flosc' ) );
		}

		// The clicked button is the only source of intent, already narrowed to
		// create|apply by the $is_kit test above. Create is the safe default: it
		// writes a new flow rather than merging into an existing one.
		$action = $submit_raw;
		if ( ! in_array( $action, array( 'create', 'apply' ), true ) ) {
			$action = 'create';
		}
		// Legacy single-button always created.
		if ( $is_legacy && ! $is_kit ) {
			$action = 'create';
		}

		// Kit limits: 1× .md, up to 10× .tsv, up to 5× .xml WXR, up to 10 media files.
		$max_tsv   = 10;
		$max_wxr   = 5;
		$max_media = 10;
		$media_ext = flosc_portability_allowed_media_ext();

		$files      = flosc_portability_collect_kit_files();
		$md         = null;
		$tsv_list   = array();
		$wxr_list   = array();
		$media_list = array();
		$md_count   = 0;
		$tsv_count  = 0;
		$wxr_count  = 0;
		$media_count = 0;
		$unknown    = array();
		foreach ( $files as $f ) {
			if ( (int) ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE || (string) ( $f['name'] ?? '' ) === '' ) {
				continue;
			}
			if ( (int) ( $f['error'] ?? 0 ) !== UPLOAD_ERR_OK ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'File upload failed. Please try again.', 'flosc' ), 'error' );
				return;
			}
			$ext = strtolower( (string) ( $f['ext'] ?? '' ) );
			if ( 'md' === $ext ) {
				++$md_count;
				if ( null === $md ) {
					$md = $f;
				}
			} elseif ( 'tsv' === $ext ) {
				++$tsv_count;
				if ( count( $tsv_list ) < $max_tsv ) {
					$tsv_list[] = $f;
				}
			} elseif ( 'xml' === $ext ) {
				++$wxr_count;
				if ( count( $wxr_list ) < $max_wxr ) {
					$wxr_list[] = $f;
				}
			} elseif ( in_array( $ext, $media_ext, true ) ) {
				++$media_count;
				if ( count( $media_list ) < $max_media ) {
					$media_list[] = $f;
				}
			} else {
				$unknown[] = (string) ( $f['name'] ?? '' );
			}
		}

		// Exactly one flow file max — never two .md files in one kit drop.
		if ( $md_count > 1 ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				esc_html__( 'Only one .md flow file per upload.', 'flosc' ),
				'error'
			);
			return;
		}
		if ( $tsv_count > $max_tsv ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				sprintf(
					/* translators: %d: max tsv count */
					esc_html__( 'At most %d DA1 .tsv catalogs per upload. Use the DA1 tab for bulk catalog work beyond that.', 'flosc' ),
					(int) $max_tsv
				),
				'error'
			);
			return;
		}
		if ( $wxr_count > $max_wxr ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				sprintf(
					/* translators: %d: max wxr count */
					esc_html__( 'At most %d WXR (.xml) files per upload.', 'flosc' ),
					(int) $max_wxr
				),
				'error'
			);
			return;
		}
		if ( $media_count > $max_media ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				sprintf(
					/* translators: %d: max media count */
					esc_html__( 'At most %d media files per upload.', 'flosc' ),
					(int) $max_media
				),
				'error'
			);
			return;
		}
		if ( ! empty( $unknown ) ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				sprintf(
					/* translators: %s: filenames */
					esc_html__( 'Unsupported file type(s): %s. Use .md, .tsv, .xml (WXR), or media (PDF/images/audio).', 'flosc' ),
					esc_html( implode( ', ', $unknown ) )
				),
				'error'
			);
			return;
		}

		if ( null === $md && empty( $tsv_list ) && empty( $wxr_list ) && empty( $media_list ) ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				esc_html__( 'Drop a pack: one .md (for Create), and/or .tsv catalogs, WXR .xml, and media (PDF/images/audio).', 'flosc' ),
				'error'
			);
			return;
		}

		if ( 'create' === $action && null === $md ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				esc_html__( 'Create new flow requires an .md IVR + Settings file (other pack files are optional with it).', 'flosc' ),
				'error'
			);
			return;
		}

		// Current flow for Apply (and for DA1 assign after create).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$working_ivr = isset( $_POST['flosc_working_ivr'] )
			? sanitize_file_name( (string) wp_unslash( $_POST['flosc_working_ivr'] ) )
			: '';
		if ( $working_ivr === '' && isset( $_GET['ivr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin GET context after POST kit handler; ivr is sanitized_file_name only
			$working_ivr = sanitize_file_name( (string) wp_unslash( $_GET['ivr'] ) );
		}

		$notes         = array();
		$redirect_ivr  = $working_ivr;
		$created_file  = '';

		// ── IVR .md ───────────────────────────────────────────────────────────
		if ( null !== $md ) {
			$tmp  = (string) $md['tmp'];
			$size = (int) $md['size'];
			if ( $tmp === '' || ! is_uploaded_file( $tmp ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'IVR upload could not be verified.', 'flosc' ), 'error' );
				return;
			}
			if ( $size <= 0 || $size > 1024 * 1024 ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'IVR file must be between 1 byte and 1 MB.', 'flosc' ), 'error' );
				return;
			}

			$filename = flosc_portability_normalize_ivr_filename( (string) $md['name'] );
			if ( 'md' !== strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Flow file must be .md.', 'flosc' ), 'error' );
				return;
			}

			$target_path = function_exists( 'flosc_data_file_path' ) ? flosc_data_file_path( $filename ) : '';
			if ( $target_path === '' || ! function_exists( 'flosc_write_data_file' ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'FLOSC data directory is not available.', 'flosc' ), 'error' );
				return;
			}

			$body = function_exists( 'flosc_fs_get_contents' ) ? flosc_fs_get_contents( $tmp ) : false;
			if ( false === $body && is_uploaded_file( $tmp ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- upload temp path after is_uploaded_file, or staged apply body
				$body = file_get_contents( $tmp );
			}
			if ( false === $body || ! is_string( $body ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Could not read the uploaded IVR file.', 'flosc' ), 'error' );
				return;
			}

			if ( ! function_exists( 'flosc_import_ivr_to_database' ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'IVR import is unavailable.', 'flosc' ), 'error' );
				return;
			}

			if ( 'create' === $action ) {
				if ( file_exists( $target_path ) ) {
					add_settings_error(
						'flosc_settings',
						'upload_failed',
						sprintf(
							/* translators: %s: IVR filename */
							esc_html__( 'An IVR file named %s already exists. Choose Apply to current flow, or use a different filename.', 'flosc' ),
							esc_html( $filename )
						),
						'error'
					);
					return;
				}
				if ( ! flosc_write_data_file( $target_path, $body ) ) {
					add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Could not store the IVR file.', 'flosc' ), 'error' );
					return;
				}

				$stem     = sanitize_key( pathinfo( $filename, PATHINFO_FILENAME ) );
				$flow_key = 'flosc_flow_' . $stem;
				$display  = flosc_portability_display_name_from_stem( $stem );
				$slug     = strtolower( (string) preg_replace( '/[^a-z0-9_-]/i', '', $stem ) );
				if ( $slug === '' ) {
					$slug = $stem;
				}

				$bag = array(
					'name'                        => $display,
					'slug'                        => $slug,
					'status'                      => 'active',
					'active_ivr_file'             => $filename,
					'ivr_file'                    => $filename,
					'companion_show_for_visitors' => 1,
				);
				update_option( $flow_key, $bag, false );

				// Create = clean slate from file.
				$import = flosc_import_ivr_to_database( false, $target_path, $flow_key, 'replace' );
				if ( empty( $import['success'] ) ) {
					delete_option( $flow_key );
					wp_delete_file( $target_path );
					add_settings_error(
						'flosc_settings',
						'upload_failed',
						sprintf(
							/* translators: %s: reason */
							esc_html__( 'Flow creation failed: %s', 'flosc' ),
							esc_html( (string) ( $import['message'] ?? __( 'Unknown error', 'flosc' ) ) )
						),
						'error'
					);
					return;
				}
				if ( function_exists( 'flosc_auto_export_ivr_to_file' ) ) {
					flosc_auto_export_ivr_to_file( $flow_key, $target_path );
				}
				if ( function_exists( 'flush_rewrite_rules' ) ) {
					flush_rewrite_rules( false );
				}
				$redirect_ivr = $filename;
				$created_file = $filename;
				$working_ivr  = $filename;
				$notes[]      = sprintf(
					/* translators: %s: filename */
					__( 'Created new flow from %s.', 'flosc' ),
					$filename
				);
			} else {
				// Apply = merge into current flow (backup file first so a failed import can restore).
				if ( $working_ivr === '' ) {
					add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Select a current flow (Switch Flow) before Apply.', 'flosc' ), 'error' );
					return;
				}
				$work_path = function_exists( 'flosc_data_file_path' ) ? flosc_data_file_path( $working_ivr ) : '';
				$work_stem = sanitize_key( pathinfo( $working_ivr, PATHINFO_FILENAME ) );
				$flow_key  = 'flosc_flow_' . $work_stem;
				if ( $work_path === '' ) {
					add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Current flow path is not available.', 'flosc' ), 'error' );
					return;
				}
				// Staging under data dir (must pass IVR path allowlist). Name must NOT match *_ivr.md glob.
				$stage_name = 'portability_stage_' . $work_stem . '_' . wp_generate_password( 8, false, false ) . '.md';
				$stage_path = function_exists( 'flosc_data_file_path' ) ? flosc_data_file_path( $stage_name ) : '';
				if ( $stage_path === '' || ! flosc_write_data_file( $stage_path, $body ) ) {
					add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Could not stage the uploaded IVR for Apply.', 'flosc' ), 'error' );
					return;
				}
				$import = flosc_import_ivr_to_database( false, $stage_path, $flow_key, 'merge' );
				// Always remove stage (not a real flow file).
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $stage_path );
				}
				if ( empty( $import['success'] ) ) {
					add_settings_error(
						'flosc_settings',
						'upload_failed',
						sprintf(
							/* translators: %s: reason */
							esc_html__( 'Apply failed: %s', 'flosc' ),
							esc_html( (string) ( $import['message'] ?? __( 'Unknown error', 'flosc' ) ) )
						),
						'error'
					);
					return;
				}
				// Write merged export to the current flow file (DB already updated).
				if ( function_exists( 'flosc_auto_export_ivr_to_file' ) ) {
					flosc_auto_export_ivr_to_file( $flow_key, $work_path );
				} elseif ( ! flosc_write_data_file( $work_path, $body ) ) {
					add_settings_error( 'flosc_settings', 'upload_partial', esc_html__( 'Settings merged, but current flow file could not be updated on disk.', 'flosc' ), 'error' );
				}
				$redirect_ivr = $working_ivr;
				$notes[]      = sprintf(
					/* translators: %s: current flow filename */
					__( 'Merged IVR + Settings YAML into current flow %s (other settings kept).', 'flosc' ),
					$working_ivr
				);
			}
		}

		// ── DA1 .tsv (0–10; each added to the flow’s catalog list) ────────────
		if ( ! empty( $tsv_list ) ) {
			$assign_ivr = ( 'create' === $action && $created_file !== '' ) ? $created_file : $working_ivr;
			if ( $assign_ivr === '' ) {
				add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'DA1 catalog needs a current flow (Switch Flow) or create an .md flow in the same drop.', 'flosc' ), 'error' );
				return;
			}
			$da1_ok     = 0;
			$da1_errors = array();
			foreach ( $tsv_list as $tsv ) {
				$da1 = flosc_portability_ingest_da1_tsv( (string) $tsv['tmp'], (string) $tsv['name'], $assign_ivr );
				if ( is_wp_error( $da1 ) ) {
					$da1_errors[] = $da1->get_error_message();
					continue;
				}
				++$da1_ok;
			}
			if ( $da1_ok > 0 ) {
				$notes[] = sprintf(
					/* translators: 1: count 2: flow ivr filename */
					_n(
						'%1$d DA1 catalog stored and assigned to %2$s.',
						'%1$d DA1 catalogs stored and assigned to %2$s.',
						$da1_ok,
						'flosc'
					),
					(int) $da1_ok,
					$assign_ivr
				);
			}
			if ( ! empty( $da1_errors ) ) {
				// MD may have succeeded — surface DA1 issues but still finish PRG if we have notes.
				$notes[] = __( 'DA1 issue:', 'flosc' ) . ' ' . implode( ' ', $da1_errors );
			}
			if ( $da1_ok === 0 && empty( $notes ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', implode( ' ', $da1_errors ), 'error' );
				return;
			}
			if ( $redirect_ivr === '' ) {
				$redirect_ivr = $assign_ivr;
			}
		}

		// ── WXR .xml + media (need a flow to attach to) ───────────────────────
		$assign_ivr = ( 'create' === $action && $created_file !== '' ) ? $created_file : $working_ivr;
		if ( ( ! empty( $wxr_list ) || ! empty( $media_list ) ) && $assign_ivr === '' ) {
			add_settings_error(
				'flosc_settings',
				'upload_failed',
				esc_html__( 'WXR and media need a current flow (Switch Flow) or create an .md flow in the same drop.', 'flosc' ),
				'error'
			);
			return;
		}

		if ( ! empty( $wxr_list ) && $assign_ivr !== '' ) {
			$wxr_ok     = 0;
			$wxr_errors = array();
			foreach ( $wxr_list as $wxr_file ) {
				$wxr = flosc_portability_ingest_wxr( (string) $wxr_file['tmp'], (string) $wxr_file['name'], $assign_ivr );
				if ( is_wp_error( $wxr ) ) {
					$wxr_errors[] = $wxr->get_error_message();
					continue;
				}
				++$wxr_ok;
			}
			if ( $wxr_ok > 0 ) {
				$notes[] = sprintf(
					/* translators: 1: count 2: flow ivr filename */
					_n(
						'%1$d WXR file staged for %2$s (Import posts from the pack list below, or Tools → Import).',
						'%1$d WXR files staged for %2$s (Import posts from the pack list below, or Tools → Import).',
						$wxr_ok,
						'flosc'
					),
					(int) $wxr_ok,
					$assign_ivr
				);
			}
			if ( ! empty( $wxr_errors ) ) {
				$notes[] = __( 'WXR issue:', 'flosc' ) . ' ' . implode( ' ', $wxr_errors );
			}
			if ( $wxr_ok === 0 && empty( $notes ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', implode( ' ', $wxr_errors ), 'error' );
				return;
			}
			if ( $redirect_ivr === '' ) {
				$redirect_ivr = $assign_ivr;
			}
		}

		if ( ! empty( $media_list ) && $assign_ivr !== '' ) {
			$media_ok     = 0;
			$media_errors = array();
			foreach ( $media_list as $media_file ) {
				$media_res = flosc_portability_ingest_media( (string) $media_file['tmp'], (string) $media_file['name'], $assign_ivr );
				if ( is_wp_error( $media_res ) ) {
					$media_errors[] = $media_res->get_error_message();
					continue;
				}
				++$media_ok;
			}
			if ( $media_ok > 0 ) {
				$notes[] = sprintf(
					/* translators: 1: count 2: flow ivr filename */
					_n(
						'%1$d media file added to the Media Library and listed on %2$s.',
						'%1$d media files added to the Media Library and listed on %2$s.',
						$media_ok,
						'flosc'
					),
					(int) $media_ok,
					$assign_ivr
				);
			}
			if ( ! empty( $media_errors ) ) {
				$notes[] = __( 'Media issue:', 'flosc' ) . ' ' . implode( ' ', $media_errors );
			}
			if ( $media_ok === 0 && empty( $notes ) ) {
				add_settings_error( 'flosc_settings', 'upload_failed', implode( ' ', $media_errors ), 'error' );
				return;
			}
			if ( $redirect_ivr === '' ) {
				$redirect_ivr = $assign_ivr;
			}
		}

		// Persist notice for PRG flash.
		if ( ! empty( $notes ) ) {
			set_transient(
				'flosc_portability_notice_' . get_current_user_id(),
				array(
					'message' => implode( ' ', $notes ),
					'type'    => 'success',
				),
				60
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$redirect_tab = isset( $_POST['flosc_upload_redirect_tab'] )
			? sanitize_key( (string) wp_unslash( $_POST['flosc_upload_redirect_tab'] ) )
			: 'flow';
		if ( ! in_array( $redirect_tab, array( 'ivr-messages', 'flow', 'da1' ), true ) ) {
			$redirect_tab = 'flow';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer in this handler
		$redirect_view = isset( $_POST['flosc_upload_redirect_view'] )
			? sanitize_key( (string) wp_unslash( $_POST['flosc_upload_redirect_view'] ) )
			: 'all';
		if ( ! in_array( $redirect_view, array( 'single', 'all' ), true ) ) {
			$redirect_view = 'all';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                   => 'flosc-settings',
					'tab'                    => $redirect_tab,
					'ivr'                    => $redirect_ivr !== '' ? $redirect_ivr : $working_ivr,
					'view'                   => $redirect_view,
					'flosc_ivr_uploaded'     => ( 'create' === $action && $created_file !== '' ) ? '1' : '0',
					'flosc_portability_done' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
