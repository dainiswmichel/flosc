<?php
/**
 * Flow Portability upload handler (admin_init).
 *
 * Supports:
 * - Create new flow from .md (optional .tsv DA1 with it)
 * - Apply to current flow: merge IVR+YAML from .md; optional .tsv DA1 assign
 * - Multi-file drop: .md + .tsv together
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$written = false !== file_put_contents( $path, (string) $body );
			}
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
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

if ( ! function_exists( 'flosc_admin_handle_ivr_file_upload' ) ) {
	/**
	 * Portability kit upload: create new flow or apply to current flow.
	 * Accepts .md and optional .tsv (together or alone for apply-tsv-only).
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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

		// Kit limits: 1× .md, up to 10× .tsv per request (not a catalog farm).
		$max_tsv = 10;

		$files     = flosc_portability_collect_kit_files();
		$md        = null;
		$tsv_list  = array();
		$md_count  = 0;
		$tsv_count = 0;
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

		if ( null === $md && empty( $tsv_list ) ) {
			add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Drop one .md flow file and/or up to 10 .tsv DA1 catalogs.', 'flosc' ), 'error' );
			return;
		}

		if ( 'create' === $action && null === $md ) {
			add_settings_error( 'flosc_settings', 'upload_failed', esc_html__( 'Create new flow requires an .md IVR + Settings file (DA1 .tsv is optional).', 'flosc' ), 'error' );
			return;
		}

		// Current flow for Apply (and for DA1 assign after create).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$working_ivr = isset( $_POST['flosc_working_ivr'] )
			? sanitize_file_name( (string) wp_unslash( $_POST['flosc_working_ivr'] ) )
			: '';
		if ( $working_ivr === '' && isset( $_GET['ivr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
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

		// Persist notice for PRG flash.
		if ( ! empty( $notes ) ) {
			set_transient(
				'flosc_portability_notice_' . get_current_user_id(),
				implode( ' ', $notes ),
				60
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$redirect_tab = isset( $_POST['flosc_upload_redirect_tab'] )
			? sanitize_key( (string) wp_unslash( $_POST['flosc_upload_redirect_tab'] ) )
			: 'flow';
		if ( ! in_array( $redirect_tab, array( 'ivr-messages', 'flow', 'da1' ), true ) ) {
			$redirect_tab = 'flow';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
