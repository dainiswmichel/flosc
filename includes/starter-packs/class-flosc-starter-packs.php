<?php
/**
 * FLOSC starter packs — install a complete working journey in one click.
 *
 * A fresh FLOSC install has nothing to say. A starter pack gives it a flow, a
 * personality, and something to talk about, so the first thing an operator sees
 * is a bot that works rather than an empty form.
 *
 * Everything a pack creates is stamped, so uninstalling removes exactly what
 * that pack made and nothing else. Nothing is ever removed by title or by date.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLOSC_Starter_Packs {

	/** Post meta stamped on every post a pack creates. */
	const POST_STAMP = '_flosc_starter_pack';

	/** Post meta holding the pack's own item number, 1..N. */
	const POST_ITEM_STAMP = '_flosc_starter_pack_item';

	/** Term meta stamped on the category a pack creates. */
	const TERM_STAMP = '_flosc_starter_pack';

	/** Option holding what is currently installed. */
	const STATE_OPTION = 'flosc_starter_packs_installed';

	/**
	 * Absolute path to the shipped packs directory.
	 *
	 * @return string
	 */
	public static function packs_dir() {
		return trailingslashit( FLOSC_PLUGIN_DIR ) . 'starter-packs';
	}

	/**
	 * Every pack that ships with the plugin, keyed by slug.
	 *
	 * A pack is a directory holding pack.json. A malformed manifest is skipped
	 * rather than fataling the settings screen.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function discover() {
		$packs = array();
		$dirs  = glob( self::packs_dir() . '/*', GLOB_ONLYDIR );

		if ( ! is_array( $dirs ) ) {
			return $packs;
		}

		foreach ( $dirs as $dir ) {
			$manifest_path = trailingslashit( $dir ) . 'pack.json';

			if ( ! is_readable( $manifest_path ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file shipped inside the plugin.
			$raw      = file_get_contents( $manifest_path );
			$manifest = json_decode( (string) $raw, true );

			if ( ! is_array( $manifest ) || empty( $manifest['slug'] ) ) {
				continue;
			}

			$manifest['dir']  = trailingslashit( $dir );
			$manifest['slug'] = sanitize_key( $manifest['slug'] );

			$packs[ $manifest['slug'] ] = $manifest;
		}

		ksort( $packs );

		return $packs;
	}

	/**
	 * One pack by slug.
	 *
	 * @param string $slug Pack slug.
	 * @return array<string,mixed>|null
	 */
	public static function get( $slug ) {
		$packs = self::discover();
		$slug  = sanitize_key( $slug );
		return isset( $packs[ $slug ] ) ? $packs[ $slug ] : null;
	}

	/**
	 * What has been installed, keyed by slug.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function state() {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Whether a pack is currently installed.
	 *
	 * @param string $slug Pack slug.
	 * @return bool
	 */
	public static function is_installed( $slug ) {
		$state = self::state();
		return isset( $state[ sanitize_key( $slug ) ] );
	}

	/**
	 * What is actually on disk and in the database for a pack.
	 *
	 * The install record says what was created; this says what is still there.
	 * An operator who deleted the flow, emptied the category, or lost the
	 * catalog gets told which piece is gone rather than a badge that still
	 * reads Installed.
	 *
	 * @param string $slug Pack slug.
	 * @return array{state:string,missing:array<int,string>,present:array<int,string>}
	 */
	public static function status( $slug ) {
		$slug  = sanitize_key( $slug );
		$state = self::state();

		if ( ! isset( $state[ $slug ] ) ) {
			return array(
				'state'   => 'not_installed',
				'missing' => array(),
				'present' => array(),
			);
		}

		$pack   = self::get( $slug );
		$record = $state[ $slug ];
		$missing = array();
		$present = array();

		// The flow file, and the settings row that makes it a flow at all.
		if ( ! empty( $record['flow_file'] ) ) {
			$path = self::flow_dir() . basename( (string) $record['flow_file'] );

			if ( file_exists( $path ) ) {
				$present[] = (string) $record['flow_file'];
			} else {
				/* translators: %s: flow file name. */
				$missing[] = sprintf( __( 'the flow file %s', 'flosc' ), basename( (string) $record['flow_file'] ) );
			}
		}

		if ( ! empty( $record['flow_option'] ) ) {
			$bag = get_option( (string) $record['flow_option'], null );

			if ( is_array( $bag ) && ! empty( $bag['flow_messages'] ) ) {
				$present[] = __( 'flow messages', 'flosc' );
			} else {
				$missing[] = __( 'the flow settings and its messages', 'flosc' );
			}
		}

		// Categories, and whether the posts are still in them.
		if ( ! empty( $record['categories'] ) && is_array( $record['categories'] ) ) {
			foreach ( $record['categories'] as $category ) {
				$term = get_term( (int) ( $category['id'] ?? 0 ), 'category' );

				if ( ! $term || is_wp_error( $term ) ) {
					/* translators: %s: category name. */
					$missing[] = sprintf( __( 'the category %s', 'flosc' ), (string) ( $category['name'] ?? '' ) );
				} else {
					$present[] = (string) ( $category['name'] ?? '' );
				}
			}
		}

		$posts = self::installed_post_ids( $slug );
		$want  = (int) ( $record['post_count'] ?? 0 );

		if ( $want > 0 && count( $posts ) < $want ) {
			$missing[] = sprintf(
				/* translators: 1: number of posts found, 2: number expected. */
				__( '%1$d of %2$d posts', 'flosc' ),
				count( $posts ),
				$want
			);
		} elseif ( $want > 0 ) {
			/* translators: %d: number of posts. */
			$present[] = sprintf( __( '%d posts', 'flosc' ), count( $posts ) );
		}

		// The DA1 catalog file and its place in the index.
		if ( ! empty( $record['catalog_file'] ) ) {
			$path = self::catalog_dir() . basename( (string) $record['catalog_file'] );

			if ( file_exists( $path ) ) {
				$present[] = (string) $record['catalog_file'];
			} else {
				/* translators: %s: catalog file name. */
				$missing[] = sprintf( __( 'the catalog %s', 'flosc' ), basename( (string) $record['catalog_file'] ) );
			}
		}

		// Product files the pack sideloaded.
		if ( ! empty( $record['attachment_ids'] ) && is_array( $record['attachment_ids'] ) ) {
			foreach ( $record['attachment_ids'] as $attachment_id ) {
				if ( 'attachment' === get_post_type( (int) $attachment_id ) ) {
					$present[] = __( 'product file', 'flosc' );
				} else {
					$missing[] = __( 'a product file', 'flosc' );
				}
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'state'   => 'needs_repair',
				'missing' => $missing,
				'present' => $present,
			);
		}

		if ( ! empty( $pack['needs_configuration'] ) ) {
			return array(
				'state'   => 'needs_configuration',
				'missing' => array(),
				'present' => $present,
			);
		}

		return array(
			'state'   => 'installed',
			'missing' => array(),
			'present' => $present,
		);
	}

	/**
	 * Post ids a pack owns, found by its stamp.
	 *
	 * @param string $slug Pack slug.
	 * @return array<int,int>
	 */
	private static function installed_post_ids( $slug ) {
		$found = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => self::POST_STAMP, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded, admin-only.
				'meta_value'  => sanitize_key( $slug ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
			)
		);

		return array_map( 'intval', (array) $found );
	}

	/**
	 * Directory holding flow files.
	 *
	 * @return string
	 */
	private static function flow_dir() {
		if ( function_exists( 'flosc_data_dir' ) ) {
			return trailingslashit( flosc_data_dir() );
		}
		return trailingslashit( FLOSC_PLUGIN_DIR ) . 'ai_configuration_files/';
	}

	/**
	 * Directory holding DA1 catalog files.
	 *
	 * @return string
	 */
	private static function catalog_dir() {
		$uploads = wp_upload_dir();
		return trailingslashit( (string) ( $uploads['basedir'] ?? '' ) ) . 'flosc-catalogs/';
	}

	/* ------------------------------------------------------------------ *
	 * Install
	 * ------------------------------------------------------------------ */

	/**
	 * Install a pack.
	 *
	 * Refuses rather than overwrites: if the flow file or the category already
	 * exists, the operator is told instead of losing work they did themselves.
	 *
	 * @param string $slug Pack slug.
	 * @return array{ok:bool,message:string,detail:array<int,string>}
	 */
	public static function install( $slug ) {
		$pack = self::get( $slug );

		if ( null === $pack ) {
			return self::result( false, __( 'That starter pack is not available.', 'flosc' ) );
		}

		if ( self::is_installed( $pack['slug'] ) ) {
			return self::result( false, __( 'That starter pack is already installed.', 'flosc' ) );
		}

		$record    = array(
			'installed_at' => gmdate( 'c' ),
			'name'         => (string) ( $pack['name'] ?? $pack['slug'] ),
			'pack_slug'    => $pack['slug'],
		);
		$detail    = array();
		$flow_path = '';

		// --- flow file ---
		if ( ! empty( $pack['flow']['file'] ) ) {
			$source    = $pack['dir'] . basename( (string) $pack['flow']['file'] );
			$file_name = basename( (string) ( $pack['flow']['install_as'] ?? $pack['flow']['file'] ) );
			$target    = self::flow_dir() . $file_name;

			if ( ! is_readable( $source ) ) {
				return self::result( false, __( 'The pack is missing its flow file.', 'flosc' ) );
			}

			if ( file_exists( $target ) ) {
				return self::result(
					false,
					sprintf(
						/* translators: %s: flow file name. */
						__( 'A flow named %s already exists. Rename or remove it first so nothing of yours is overwritten.', 'flosc' ),
						basename( $target )
					)
				);
			}

			wp_mkdir_p( dirname( $target ) );

			if ( ! self::place_file( $source, $target ) ) {
				return self::result( false, __( 'The flow file could not be written. Check folder permissions.', 'flosc' ) );
			}

			$flow_path           = $target;
			$record['flow_file'] = basename( $target );
			/* translators: %s: flow file name. */
			$detail[] = sprintf( __( 'Flow installed: %s', 'flosc' ), basename( $target ) );
		}

		// --- category and posts ---
		if ( ! empty( $pack['content']['file'] ) ) {
			$installed = self::install_content( $pack );

			if ( ! $installed['ok'] ) {
				self::rollback( $record );
				return $installed;
			}

			$record   = array_merge( $record, $installed['record'] );
			$detail[] = $installed['message'];
		}

		// --- DA1 catalog ---
		if ( ! empty( $pack['catalog']['file'] ) ) {
			$source = $pack['dir'] . basename( (string) $pack['catalog']['file'] );

			// The runtime resolves a catalog key to flosc_da1_catalog_{key}.tsv,
			// so the file name is the convention, not a free choice.
			$catalog_key = sanitize_key( (string) ( $pack['catalog']['key'] ?? pathinfo( basename( (string) $pack['catalog']['file'] ), PATHINFO_FILENAME ) ) );

			if ( '' === $catalog_key ) {
				self::rollback( $record );
				return self::result( false, __( 'The pack catalog has no usable key.', 'flosc' ) );
			}

			$file_name = 'flosc_da1_catalog_' . $catalog_key . '.tsv';
			$target    = self::catalog_dir() . $file_name;

			if ( ! is_readable( $source ) ) {
				self::rollback( $record );
				return self::result( false, __( 'The pack is missing its catalog file.', 'flosc' ) );
			}

			wp_mkdir_p( self::catalog_dir() );

			if ( file_exists( $target ) ) {
				self::rollback( $record );
				return self::result(
					false,
					sprintf(
						/* translators: %s: catalog file name. */
						__( 'A catalog named %s already exists. Rename or remove it first.', 'flosc' ),
						basename( $target )
					)
				);
			}

			if ( ! self::place_file( $source, $target ) ) {
				self::rollback( $record );
				return self::result( false, __( 'The catalog file could not be written. Check folder permissions.', 'flosc' ) );
			}

			$record['catalog_file'] = basename( $target );
			$record['catalog_key']  = $catalog_key;
			/* translators: %s: catalog file name. */
			$detail[] = sprintf( __( 'Catalog installed: %s', 'flosc' ), basename( $target ) );

			// Register it in the index the DA1 tab lists catalogs from, or the
			// operator has a catalog the runtime can read and the admin cannot see.
			$index = get_option( 'flosc_da1_catalogs', array() );
			$index = is_array( $index ) ? $index : array();

			$index[ $catalog_key ] = array(
				'label'      => (string) ( $pack['catalog']['label'] ?? $catalog_key ),
				'key'        => $catalog_key,
				'filename'   => $file_name,
				'created_at' => current_time( 'mysql' ),
			);
			update_option( 'flosc_da1_catalogs', $index, false );

			// Assign the catalog to this pack's flow.
			if ( ! empty( $record['flow_file'] ) ) {
				$assignments = get_option( 'flosc_da1_flow_catalogs', array() );
				$assignments = is_array( $assignments ) ? $assignments : array();

				$assignments[ $record['flow_file'] ] = array( $catalog_key );
				update_option( 'flosc_da1_flow_catalogs', $assignments, false );
			}
		}

		// --- product files ---
		if ( ! empty( $pack['assets'] ) ) {
			$assets = self::install_assets( $pack );

			if ( ! $assets['ok'] ) {
				self::rollback( $record );
				return self::result( false, $assets['message'] );
			}

			if ( ! empty( $assets['ids'] ) ) {
				$record['attachment_ids'] = $assets['ids'];
				$detail[]                 = $assets['message'];
			}
		}

		// --- register the flow ---
		// Copying the markdown is not enough. A flow only exists once it has a
		// per-flow option holding its messages, and the operator should not have
		// to import it by hand after clicking install.
		if ( '' !== $flow_path ) {
			$registered = self::register_flow( $pack, $flow_path, (string) ( $record['category_slug'] ?? '' ) );

			if ( ! $registered['ok'] ) {
				self::rollback( $record );
				return $registered;
			}

			$record['flow_option'] = $registered['record']['flow_option'];
			$detail[]              = $registered['message'];
		}

		// --- content index ---
		// The assistant retrieves posts from the site content index, and the
		// index is a file that has to be built. Without this the pack installs
		// a hundred posts the bot cannot see.
		if ( ! empty( $record['post_count'] ) ) {
			$detail[] = self::refresh_content_index();
		}

		$state                  = self::state();
		$state[ $pack['slug'] ] = $record;
		update_option( self::STATE_OPTION, $state, false );

		return self::result(
			true,
			sprintf(
				/* translators: %s: starter pack name. */
				__( '%s installed.', 'flosc' ),
				(string) ( $pack['name'] ?? $pack['slug'] )
			),
			$detail
		);
	}

	/**
	 * Create the pack's category and its posts.
	 *
	 * @param array<string,mixed> $pack Manifest.
	 * @return array{ok:bool,message:string,detail:array<int,string>,record:array<string,mixed>}
	 */
	private static function install_content( $pack ) {
		$source = $pack['dir'] . basename( (string) $pack['content']['file'] );

		if ( ! is_readable( $source ) ) {
			return self::result( false, __( 'The pack is missing its content file.', 'flosc' ) ) + array( 'record' => array() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file shipped inside the plugin.
		$doc = json_decode( (string) file_get_contents( $source ), true );

		if ( ! is_array( $doc ) || empty( $doc['posts'] ) || ! is_array( $doc['posts'] ) ) {
			return self::result( false, __( 'The pack content file could not be read.', 'flosc' ) ) + array( 'record' => array() );
		}

		$categories = ( isset( $doc['categories'] ) && is_array( $doc['categories'] ) ) ? $doc['categories'] : array();

		if ( empty( $categories ) ) {
			return self::result( false, __( 'The pack content file names no categories.', 'flosc' ) ) + array( 'record' => array() );
		}

		// The flow references these category slugs by name, so they are the
		// pack's to define. Refuse if any already exists rather than adopting
		// a category the operator built for something else.
		foreach ( $categories as $category ) {
			$slug = sanitize_title( (string) ( $category['slug'] ?? '' ) );

			if ( '' === $slug ) {
				return self::result( false, __( 'The pack names a category with no slug.', 'flosc' ) ) + array( 'record' => array() );
			}

			if ( term_exists( $slug, 'category' ) ) {
				return self::result(
					false,
					sprintf(
						/* translators: %s: category slug. */
						__( 'A category %s already exists. Rename or remove it first.', 'flosc' ),
						$slug
					)
				) + array( 'record' => array() );
			}
		}

		// Parents before children, so a child can resolve its parent's id.
		$term_ids   = array();
		$created    = array();
		$pending    = $categories;
		$safety     = count( $categories ) + 1;

		while ( ! empty( $pending ) && $safety-- > 0 ) {
			$still_pending = array();

			foreach ( $pending as $category ) {
				$slug   = sanitize_title( (string) $category['slug'] );
				$parent = sanitize_title( (string) ( $category['parent'] ?? '' ) );

				if ( '' !== $parent && ! isset( $term_ids[ $parent ] ) ) {
					$still_pending[] = $category;
					continue;
				}

				$term = wp_insert_term(
					sanitize_text_field( (string) ( $category['name'] ?? $slug ) ),
					'category',
					array(
						'slug'        => $slug,
						'description' => sanitize_text_field( (string) ( $category['description'] ?? '' ) ),
						'parent'      => '' !== $parent ? (int) $term_ids[ $parent ] : 0,
					)
				);

				if ( is_wp_error( $term ) ) {
					return self::result( false, $term->get_error_message() ) + array( 'record' => array( 'category_ids' => $created ) );
				}

				$term_id            = (int) $term['term_id'];
				$term_ids[ $slug ]  = $term_id;
				$created[]          = $term_id;

				add_term_meta( $term_id, self::TERM_STAMP, $pack['slug'], true );

				if ( ! empty( $category['term_meta'] ) && is_array( $category['term_meta'] ) ) {
					foreach ( $category['term_meta'] as $meta_key => $meta_value ) {
						add_term_meta( $term_id, sanitize_key( (string) $meta_key ), sanitize_text_field( (string) $meta_value ), true );
					}
				}
			}

			$pending = $still_pending;
		}

		if ( ! empty( $pending ) ) {
			return self::result( false, __( 'The pack category hierarchy could not be resolved.', 'flosc' ) ) + array( 'record' => array( 'category_ids' => $created ) );
		}

		$count       = 0;
		$per_category = array();

		foreach ( $doc['posts'] as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['title'] ) ) {
				continue;
			}

			$slug    = sanitize_title( (string) ( $entry['category'] ?? '' ) );
			$term_id = isset( $term_ids[ $slug ] ) ? (int) $term_ids[ $slug ] : (int) reset( $term_ids );
			$item    = isset( $entry['item'] ) ? (int) $entry['item'] : $count + 1;
			$post_id = self::insert_pack_post( $pack['slug'], $entry, $term_id, $item );

			if ( $post_id < 1 ) {
				continue;
			}

			++$count;

			if ( ! isset( $per_category[ $term_id ] ) ) {
				$per_category[ $term_id ] = 0;
			}
			++$per_category[ $term_id ];
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: number of posts, 2: number of categories. */
				__( '%1$d posts created across %2$d categories.', 'flosc' ),
				$count,
				count( $created )
			),
			'detail'  => array(),
			'record'  => array(
				'category_ids'  => $created,
				'category_slug' => (string) ( $categories[0]['slug'] ?? '' ),
				'post_count'    => $count,
				'categories'    => self::describe_categories( $categories, $term_ids, $per_category ),
			),
		);
	}

	/**
	 * Copy a pack's product file into the media library.
	 *
	 * Sideloads from inside the plugin — never a remote fetch — so a pack's PDF
	 * is present without the operator uploading it by hand.
	 *
	 * @param array<string,mixed> $pack Manifest.
	 * @return array{ok:bool,message:string,ids:array<int,int>}
	 */
	private static function install_assets( $pack ) {
		$ids = array();

		if ( empty( $pack['assets'] ) || ! is_array( $pack['assets'] ) ) {
			return array( 'ok' => true, 'message' => '', 'ids' => $ids );
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return array( 'ok' => false, 'message' => (string) $uploads['error'], 'ids' => $ids );
		}

		foreach ( $pack['assets'] as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['file'] ) ) {
				continue;
			}

			$source = $pack['dir'] . basename( (string) $asset['file'] );

			if ( ! is_readable( $source ) ) {
				return array(
					'ok'      => false,
					/* translators: %s: file name. */
					'message' => sprintf( __( 'The pack is missing %s.', 'flosc' ), basename( (string) $asset['file'] ) ),
					'ids'     => $ids,
				);
			}

			$file_name = wp_unique_filename( $uploads['path'], basename( $source ) );
			$target    = trailingslashit( $uploads['path'] ) . $file_name;

			if ( ! self::place_file( $source, $target ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'A pack file could not be written to the uploads directory.', 'flosc' ),
					'ids'     => $ids,
				);
			}

			$type    = wp_check_filetype( $file_name, null );
			$post_id = wp_insert_attachment(
				array(
					'post_mime_type' => (string) ( $type['type'] ?? 'application/octet-stream' ),
					'post_title'     => sanitize_text_field( (string) ( $asset['title'] ?? $file_name ) ),
					'post_content'   => sanitize_text_field( (string) ( $asset['description'] ?? '' ) ),
					'post_status'    => 'inherit',
					'meta_input'     => array( self::POST_STAMP => $pack['slug'] ),
				),
				$target,
				0,
				true
			);

			if ( is_wp_error( $post_id ) ) {
				wp_delete_file( $target );
				return array( 'ok' => false, 'message' => $post_id->get_error_message(), 'ids' => $ids );
			}

			$ids[] = (int) $post_id;
		}

		return array(
			'ok'      => true,
			/* translators: %d: number of files. */
			'message' => sprintf( __( '%d product file(s) added to the media library.', 'flosc' ), count( $ids ) ),
			'ids'     => $ids,
		);
	}

	/**
	 * Create one post a pack owns, stamped so it can be found again.
	 *
	 * The stamp pair — pack slug and item number — is what makes install and
	 * repair idempotent: a second run recognises what already exists instead
	 * of duplicating it.
	 *
	 * @param string              $pack_slug Pack slug.
	 * @param array<string,mixed> $entry     One entry from the pack's content file.
	 * @param int                 $term_id   Category to file it under.
	 * @param int                 $item      The pack's own item number.
	 * @return int Post id, or 0 on failure.
	 */
	private static function insert_pack_post( $pack_slug, $entry, $term_id, $item ) {
		$post_arg = array(
			'post_title'    => sanitize_text_field( (string) $entry['title'] ),
			'post_content'  => wp_kses_post( (string) ( $entry['content'] ?? '' ) ),
			'post_excerpt'  => sanitize_text_field( (string) ( $entry['excerpt'] ?? '' ) ),
			'post_status'   => 'publish',
			'post_type'     => 'post',
			'post_category' => array( (int) $term_id ),
			'meta_input'    => array(
				self::POST_STAMP       => $pack_slug,
				self::POST_ITEM_STAMP  => (int) $item,
				'_flosc_lesson_number' => (int) $item,
			),
		);

		$post_slug = sanitize_title( (string) ( $entry['slug'] ?? '' ) );

		if ( '' !== $post_slug ) {
			$post_arg['post_name'] = $post_slug;
		}

		$post_id = wp_insert_post( $post_arg, true );

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		// The tier the runtime gates on, plus the pack's own record of what it
		// called that tier.
		$access = sanitize_key( (string) ( $entry['access'] ?? 'member' ) );

		if ( ! in_array( $access, array( 'visitor', 'guest', 'member' ), true ) ) {
			$access = 'member';
		}

		update_post_meta( $post_id, '_flosc_access_level', $access );

		if ( ! empty( $entry['starter_access'] ) ) {
			update_post_meta( $post_id, '_flosc_starter_access', sanitize_key( (string) $entry['starter_access'] ) );
		}

		if ( ! empty( $entry['protection_mode'] ) ) {
			update_post_meta( $post_id, '_flosc_protection_mode', sanitize_key( (string) $entry['protection_mode'] ) );
		}

		return (int) $post_id;
	}

	/**
	 * Copy one file the pack ships into a writable FLOSC location.
	 *
	 * Never a raw copy(): reads the shipped file, then writes through the
	 * plugin's own filesystem chokepoint, which refuses any path outside
	 * uploads and goes through WP_Filesystem.
	 *
	 * @param string $source Absolute path of a file inside the plugin.
	 * @param string $target Absolute path to write, under uploads.
	 * @return bool Whether the file landed.
	 */
	private static function place_file( $source, $target ) {
		if ( ! is_readable( $source ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file shipped inside the plugin.
		$content = file_get_contents( $source );

		if ( false === $content ) {
			return false;
		}

		// The flow directory has its own guarded writer; everything else goes
		// through the uploads-restricted one.
		if ( function_exists( 'flosc_write_data_file' ) && 0 === strpos( $target, self::flow_dir() ) ) {
			return (bool) flosc_write_data_file( $target, $content );
		}

		if ( ! class_exists( 'FLOSC_Filesystem' ) ) {
			return false;
		}

		$fs = new FLOSC_Filesystem();

		return (bool) $fs->write_file_safely( $target, $content );
	}

	/**
	 * Give the copied flow file a per-flow option and import its messages.
	 *
	 * This is what the flow upload screen does after it writes a file, and it is
	 * the difference between a flow that appears in the list and a flow that
	 * actually answers. When the pack also installed posts, the flow is pointed
	 * at their category so the assistant can find them.
	 *
	 * @param array<string,mixed> $pack          Manifest.
	 * @param string              $flow_path     Absolute path of the installed flow file.
	 * @param string              $category_slug Category the pack's posts landed in, if any.
	 * @param bool                $allow_existing Overwrite a settings row this pack already owns (repair only).
	 * @return array{ok:bool,message:string,detail:array<int,string>,record:array<string,mixed>}
	 */
	private static function register_flow( $pack, $flow_path, $category_slug = '', $allow_existing = false ) {
		if ( ! function_exists( 'flosc_import_ivr_to_database' ) ) {
			require_once FLOSC_PLUGIN_DIR . 'includes/portability/flosc-ivr-sync.php';
		}

		if ( ! function_exists( 'flosc_import_ivr_to_database' ) ) {
			return self::result( false, __( 'The flow importer is unavailable, so the flow could not be registered.', 'flosc' ) ) + array( 'record' => array() );
		}

		$file_name = basename( $flow_path );
		$stem      = sanitize_key( pathinfo( $file_name, PATHINFO_FILENAME ) );

		if ( '' === $stem ) {
			return self::result( false, __( 'The pack flow file has no usable name.', 'flosc' ) ) + array( 'record' => array() );
		}

		$flow_key = 'flosc_flow_' . $stem;

		// The file check upstream cannot see a settings row left behind by a flow
		// the operator deleted by hand. Refuse rather than overwrite it.
		if ( ! $allow_existing && null !== get_option( $flow_key, null ) ) {
			return self::result(
				false,
				sprintf(
					/* translators: %s: flow settings option name. */
					__( 'Settings for a flow named %s already exist. Remove them first so nothing of yours is overwritten.', 'flosc' ),
					$flow_key
				)
			) + array( 'record' => array() );
		}

		$bag = array(
			'name'                        => (string) ( $pack['name'] ?? $stem ),
			'slug'                        => $stem,
			'status'                      => 'active',
			'active_ivr_file'             => $file_name,
			'ivr_file'                    => $file_name,
			'companion_show_for_visitors' => 1,
		);

		// The pack names a voice from the shipped library. Reference it — never
		// create or overwrite a personality record.
		$personality = sanitize_key( (string) ( $pack['personality'] ?? '' ) );

		if ( '' !== $personality && function_exists( 'flosc_personality_library_get' ) ) {
			if ( null === flosc_personality_library_get( $personality ) ) {
				$personality = '';
			}
		}

		if ( '' !== $personality ) {
			$bag['personality_library_id'] = $personality;
		}

		// Point the flow at the posts this pack just created.
		$category_slug = sanitize_title( (string) $category_slug );

		if ( '' !== $category_slug ) {
			$bag['content_item_category'] = $category_slug;
			$bag['content_item_groups']   = array(
				array(
					'quiz_id'  => '',
					'category' => $category_slug,
				),
			);
		}

		if ( function_exists( 'flosc_normalize_content_item_flow_settings' ) ) {
			$bag = flosc_normalize_content_item_flow_settings( $bag );
		}

		update_option( $flow_key, $bag, false );

		$import = flosc_import_ivr_to_database( false, $flow_path, $flow_key, 'replace' );

		if ( empty( $import['success'] ) ) {
			delete_option( $flow_key );

			return self::result(
				false,
				sprintf(
					/* translators: %s: reason the import failed. */
					__( 'The pack flow could not be imported: %s', 'flosc' ),
					(string) ( $import['message'] ?? __( 'Unknown error', 'flosc' ) )
				)
			) + array( 'record' => array() );
		}

		$count = isset( $import['stats']['incoming_count'] ) ? (int) $import['stats']['incoming_count'] : 0;

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: number of messages, 2: flow file name. */
				__( '%1$d flow messages imported for %2$s.', 'flosc' ),
				$count,
				$file_name
			),
			'detail'  => array(),
			'record'  => array( 'flow_option' => $flow_key ),
		);
	}

	/**
	 * Rebuild the site content index so the assistant can see the pack's posts.
	 *
	 * Never fatal: a pack whose index did not build is still installed, and the
	 * operator can rebuild it from the AI tab. Say so rather than failing.
	 *
	 * @return string One line of detail for the operator.
	 */
	private static function refresh_content_index() {
		if ( ! class_exists( 'FLOSC_Site_Content_Index' ) ) {
			return __( 'Content index not rebuilt — rebuild it from the AI tab so the assistant can see these posts.', 'flosc' );
		}

		$result = FLOSC_Site_Content_Index::instance()->rebuild();

		if ( empty( $result['ok'] ) ) {
			return (string) ( $result['message'] ?? __( 'The content index could not be rebuilt. Rebuild it from the AI tab.', 'flosc' ) );
		}

		/* translators: %d: number of posts indexed. */
		return sprintf( __( 'Content index rebuilt: %d posts the assistant can now retrieve.', 'flosc' ), (int) ( $result['count'] ?? 0 ) );
	}

	/**
	 * Name, id and post count for each category a pack created.
	 *
	 * Categories holding nothing are dropped, so the card links only to places
	 * that actually have posts in them.
	 *
	 * @param array<int,array<string,mixed>> $categories   Manifest categories.
	 * @param array<string,int>              $term_ids     Slug to term id.
	 * @param array<int,int>                 $per_category Term id to post count.
	 * @return array<int,array<string,mixed>>
	 */
	private static function describe_categories( $categories, $term_ids, $per_category ) {
		$out = array();

		foreach ( $categories as $category ) {
			$slug = sanitize_title( (string) ( $category['slug'] ?? '' ) );

			if ( ! isset( $term_ids[ $slug ] ) ) {
				continue;
			}

			$term_id = (int) $term_ids[ $slug ];
			$count   = isset( $per_category[ $term_id ] ) ? (int) $per_category[ $term_id ] : 0;

			if ( $count < 1 ) {
				continue;
			}

			$out[] = array(
				'id'    => $term_id,
				'name'  => (string) ( $category['name'] ?? $slug ),
				'slug'  => $slug,
				'count' => $count,
			);
		}

		return $out;
	}

	/**
	 * Point an installed pack's flow at a different personality.
	 *
	 * The whole journey is curated by whoever this names, so switching it is
	 * the fastest way to see what FLOSC actually does. Only the flow this pack
	 * installed is touched, and only with a personality the library really has.
	 *
	 * @param string $slug           Pack slug.
	 * @param string $personality_id Personality library id.
	 * @return array{ok:bool,message:string,detail:array<int,string>}
	 */
	public static function set_personality( $slug, $personality_id ) {
		$slug  = sanitize_key( $slug );
		$state = self::state();

		if ( ! isset( $state[ $slug ]['flow_option'] ) ) {
			return self::result( false, __( 'That starter pack is not installed.', 'flosc' ) );
		}

		$personality_id = sanitize_key( $personality_id );

		if ( ! function_exists( 'flosc_personality_library_get' ) || null === flosc_personality_library_get( $personality_id ) ) {
			return self::result( false, __( 'That personality is not in the library.', 'flosc' ) );
		}

		$flow_key = (string) $state[ $slug ]['flow_option'];

		if ( 0 !== strpos( $flow_key, 'flosc_flow_' ) ) {
			return self::result( false, __( 'That starter pack has no flow to change.', 'flosc' ) );
		}

		$bag = get_option( $flow_key, array() );

		if ( ! is_array( $bag ) ) {
			return self::result( false, __( 'That flow has no settings to change.', 'flosc' ) );
		}

		$bag['personality_library_id'] = $personality_id;
		update_option( $flow_key, $bag, false );

		$personality = flosc_personality_library_get( $personality_id );
		$label       = (string) ( $personality['label'] ?? $personality_id );

		return self::result(
			true,
			sprintf(
				/* translators: %s: personality name. */
				__( '%s is now curating this journey. Open the flow and talk to it.', 'flosc' ),
				$label
			)
		);
	}

	/**
	 * Put back only the pieces of an installed pack that have gone missing.
	 *
	 * Structural, not a reset. Content the operator edited is left exactly as
	 * they left it; only components that no longer exist are recreated. A pack
	 * with nothing missing is reported as such rather than rebuilt.
	 *
	 * @param string $slug Pack slug.
	 * @return array{ok:bool,message:string,detail:array<int,string>}
	 */
	public static function repair( $slug ) {
		$slug   = sanitize_key( $slug );
		$status = self::status( $slug );

		if ( 'not_installed' === $status['state'] ) {
			return self::result( false, __( 'That starter pack is not installed, so there is nothing to repair.', 'flosc' ) );
		}

		if ( empty( $status['missing'] ) ) {
			return self::result( true, __( 'Nothing to repair — every piece of this pack is present.', 'flosc' ) );
		}

		$pack = self::get( $slug );

		if ( null === $pack ) {
			return self::result( false, __( 'That starter pack is no longer available in this build.', 'flosc' ) );
		}

		$state  = self::state();
		$record = $state[ $slug ];
		$detail = array();

		// --- flow file ---
		if ( ! empty( $record['flow_file'] ) ) {
			$target = self::flow_dir() . basename( (string) $record['flow_file'] );

			if ( ! file_exists( $target ) ) {
				$source = $pack['dir'] . basename( (string) $pack['flow']['file'] );

				if ( ! self::place_file( $source, $target ) ) {
					return self::result( false, __( 'The flow file could not be restored. Check folder permissions.', 'flosc' ) );
				}

				/* translators: %s: flow file name. */
				$detail[] = sprintf( __( 'Flow file restored: %s', 'flosc' ), basename( $target ) );
			}

			// --- flow settings and messages ---
			$bag = ! empty( $record['flow_option'] ) ? get_option( (string) $record['flow_option'], null ) : null;

			if ( ! is_array( $bag ) || empty( $bag['flow_messages'] ) ) {
				$registered = self::register_flow( $pack, $target, (string) ( $record['category_slug'] ?? '' ), true );

				if ( ! $registered['ok'] ) {
					return $registered;
				}

				$record['flow_option'] = $registered['record']['flow_option'];
				$detail[]              = $registered['message'];
			}
		}

		// --- categories and posts ---
		$restored = self::repair_content( $pack, $record );

		if ( ! $restored['ok'] ) {
			return self::result( false, $restored['message'] );
		}

		if ( '' !== $restored['message'] ) {
			$detail[] = $restored['message'];
		}

		if ( ! empty( $restored['record'] ) ) {
			$record = array_merge( $record, $restored['record'] );
		}

		// --- catalog ---
		if ( ! empty( $record['catalog_file'] ) ) {
			$target = self::catalog_dir() . basename( (string) $record['catalog_file'] );

			if ( ! file_exists( $target ) ) {
				$source = $pack['dir'] . basename( (string) $pack['catalog']['file'] );

				if ( ! self::place_file( $source, $target ) ) {
					return self::result( false, __( 'The catalog file could not be restored. Check folder permissions.', 'flosc' ) );
				}

				/* translators: %s: catalog file name. */
				$detail[] = sprintf( __( 'Catalog restored: %s', 'flosc' ), basename( $target ) );
			}

			// The index entry and the flow assignment, whether or not the file was missing.
			if ( ! empty( $record['catalog_key'] ) ) {
				$index = get_option( 'flosc_da1_catalogs', array() );
				$index = is_array( $index ) ? $index : array();

				if ( ! isset( $index[ $record['catalog_key'] ] ) ) {
					$index[ $record['catalog_key'] ] = array(
						'label'      => (string) ( $pack['catalog']['label'] ?? $record['catalog_key'] ),
						'key'        => (string) $record['catalog_key'],
						'filename'   => basename( (string) $record['catalog_file'] ),
						'created_at' => current_time( 'mysql' ),
					);
					update_option( 'flosc_da1_catalogs', $index, false );
					$detail[] = __( 'Catalog put back in the DA1 index.', 'flosc' );
				}

				if ( ! empty( $record['flow_file'] ) ) {
					$assign = get_option( 'flosc_da1_flow_catalogs', array() );
					$assign = is_array( $assign ) ? $assign : array();

					if ( empty( $assign[ $record['flow_file'] ] ) ) {
						$assign[ $record['flow_file'] ] = array( (string) $record['catalog_key'] );
						update_option( 'flosc_da1_flow_catalogs', $assign, false );
						$detail[] = __( 'Catalog reassigned to its flow.', 'flosc' );
					}
				}
			}
		}

		// --- product files ---
		$live = array();

		foreach ( (array) ( $record['attachment_ids'] ?? array() ) as $attachment_id ) {
			if ( 'attachment' === get_post_type( (int) $attachment_id ) ) {
				$live[] = (int) $attachment_id;
			}
		}

		if ( ! empty( $pack['assets'] ) && count( $live ) < count( (array) $pack['assets'] ) ) {
			$assets = self::install_assets( $pack );

			if ( $assets['ok'] ) {
				$record['attachment_ids'] = array_values( array_unique( array_merge( $live, $assets['ids'] ) ) );
				$detail[]                 = $assets['message'];
			}
		}

		$state[ $slug ] = $record;
		update_option( self::STATE_OPTION, $state, false );

		if ( ! empty( $record['post_count'] ) ) {
			$detail[] = self::refresh_content_index();
		}

		return self::result( true, __( 'Starter pack repaired.', 'flosc' ), $detail );
	}

	/**
	 * Recreate any category or post a pack owns that is no longer there.
	 *
	 * Posts are matched on the pack slug plus the item number, so a post the
	 * operator rewrote is recognised and left alone. Only genuinely absent
	 * items are created again.
	 *
	 * @param array<string,mixed> $pack   Manifest.
	 * @param array<string,mixed> $record Install record.
	 * @return array{ok:bool,message:string,record:array<string,mixed>}
	 */
	private static function repair_content( $pack, $record ) {
		if ( empty( $pack['content']['file'] ) ) {
			return array( 'ok' => true, 'message' => '', 'record' => array() );
		}

		$source = $pack['dir'] . basename( (string) $pack['content']['file'] );

		if ( ! is_readable( $source ) ) {
			return array( 'ok' => false, 'message' => __( 'The pack is missing its content file.', 'flosc' ), 'record' => array() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file shipped inside the plugin.
		$doc = json_decode( (string) file_get_contents( $source ), true );

		if ( ! is_array( $doc ) || empty( $doc['posts'] ) ) {
			return array( 'ok' => false, 'message' => __( 'The pack content file could not be read.', 'flosc' ), 'record' => array() );
		}

		// Which categories still exist, by slug.
		$term_ids = array();

		foreach ( (array) ( $doc['categories'] ?? array() ) as $category ) {
			$slug = sanitize_title( (string) ( $category['slug'] ?? '' ) );

			if ( '' === $slug ) {
				continue;
			}

			$existing = get_term_by( 'slug', $slug, 'category' );

			if ( $existing && ! is_wp_error( $existing ) ) {
				$term_ids[ $slug ] = (int) $existing->term_id;
			}
		}

		$made_categories = 0;
		$pending         = array();

		foreach ( (array) ( $doc['categories'] ?? array() ) as $category ) {
			$slug = sanitize_title( (string) ( $category['slug'] ?? '' ) );

			if ( '' !== $slug && ! isset( $term_ids[ $slug ] ) ) {
				$pending[] = $category;
			}
		}

		$safety = count( $pending ) + 1;

		while ( ! empty( $pending ) && $safety-- > 0 ) {
			$still = array();

			foreach ( $pending as $category ) {
				$slug   = sanitize_title( (string) $category['slug'] );
				$parent = sanitize_title( (string) ( $category['parent'] ?? '' ) );

				if ( '' !== $parent && ! isset( $term_ids[ $parent ] ) ) {
					$still[] = $category;
					continue;
				}

				$term = wp_insert_term(
					sanitize_text_field( (string) ( $category['name'] ?? $slug ) ),
					'category',
					array(
						'slug'        => $slug,
						'description' => sanitize_text_field( (string) ( $category['description'] ?? '' ) ),
						'parent'      => '' !== $parent ? (int) $term_ids[ $parent ] : 0,
					)
				);

				if ( is_wp_error( $term ) ) {
					continue;
				}

				$term_id           = (int) $term['term_id'];
				$term_ids[ $slug ] = $term_id;
				++$made_categories;

				add_term_meta( $term_id, self::TERM_STAMP, $pack['slug'], true );

				foreach ( (array) ( $category['term_meta'] ?? array() ) as $meta_key => $meta_value ) {
					add_term_meta( $term_id, sanitize_key( (string) $meta_key ), sanitize_text_field( (string) $meta_value ), true );
				}
			}

			$pending = $still;
		}

		// Which item numbers this pack still has posts for.
		$have = array();

		foreach ( self::installed_post_ids( $pack['slug'] ) as $post_id ) {
			$item = (int) get_post_meta( $post_id, self::POST_ITEM_STAMP, true );

			if ( $item > 0 ) {
				$have[ $item ] = true;
			}
		}

		$made_posts   = 0;
		$per_category = array();

		foreach ( $doc['posts'] as $index => $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['title'] ) ) {
				continue;
			}

			$item = isset( $entry['item'] ) ? (int) $entry['item'] : $index + 1;

			if ( isset( $have[ $item ] ) ) {
				continue;
			}

			$slug    = sanitize_title( (string) ( $entry['category'] ?? '' ) );
			$term_id = isset( $term_ids[ $slug ] ) ? (int) $term_ids[ $slug ] : (int) reset( $term_ids );
			$post_id = self::insert_pack_post( $pack['slug'], $entry, $term_id, $item );

			if ( $post_id > 0 ) {
				++$made_posts;

				if ( ! isset( $per_category[ $term_id ] ) ) {
					$per_category[ $term_id ] = 0;
				}
				++$per_category[ $term_id ];
			}
		}

		$message = '';

		if ( $made_categories > 0 || $made_posts > 0 ) {
			$message = sprintf(
				/* translators: 1: number of posts, 2: number of categories. */
				__( 'Restored %1$d posts and %2$d categories.', 'flosc' ),
				$made_posts,
				$made_categories
			);
		}

		return array( 'ok' => true, 'message' => $message, 'record' => array() );
	}

	/* ------------------------------------------------------------------ *
	 * Uninstall
	 * ------------------------------------------------------------------ */

	/**
	 * Remove everything a pack created, by stamp.
	 *
	 * @param string $slug Pack slug.
	 * @return array{ok:bool,message:string,detail:array<int,string>}
	 */
	public static function uninstall( $slug ) {
		$slug  = sanitize_key( $slug );
		$state = self::state();

		if ( ! isset( $state[ $slug ] ) ) {
			return self::result( false, __( 'That starter pack is not installed.', 'flosc' ) );
		}

		$detail = self::rollback( $state[ $slug ] );

		if ( ! empty( $state[ $slug ]['post_count'] ) ) {
			$detail[] = self::refresh_content_index();
		}

		unset( $state[ $slug ] );
		update_option( self::STATE_OPTION, $state, false );

		return self::result( true, __( 'Starter pack removed.', 'flosc' ), $detail );
	}

	/**
	 * Undo an install record. Used both by uninstall and to clean up a partial
	 * install that failed halfway.
	 *
	 * @param array<string,mixed> $record Install record.
	 * @return array<int,string> What was removed.
	 */
	private static function rollback( $record ) {
		$detail = array();

		if ( ! empty( $record['attachment_ids'] ) && is_array( $record['attachment_ids'] ) ) {
			foreach ( $record['attachment_ids'] as $attachment_id ) {
				wp_delete_attachment( (int) $attachment_id, true );
			}

			/* translators: %d: number of files removed. */
			$detail[] = sprintf( __( '%d product file(s) removed.', 'flosc' ), count( $record['attachment_ids'] ) );
		}

		// Posts, found by their stamp rather than by title, date or category.
		if ( ! empty( $record['pack_slug'] ) ) {
			$slug  = (string) $record['pack_slug'];
			$found = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'any',
					'numberposts'    => -1,
					'fields'         => 'ids',
					'meta_key'       => self::POST_STAMP, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded, admin-only, runs once.
					'meta_value'     => $slug,            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
				)
			);

			foreach ( (array) $found as $post_id ) {
				wp_delete_post( (int) $post_id, true );
			}

			/* translators: %d: number of posts removed. */
			$detail[] = sprintf( __( '%d posts removed.', 'flosc' ), count( (array) $found ) );
		}

		// Children first, so a parent is never deleted out from under one.
		if ( ! empty( $record['category_ids'] ) && is_array( $record['category_ids'] ) ) {
			foreach ( array_reverse( $record['category_ids'] ) as $term_id ) {
				wp_delete_term( (int) $term_id, 'category' );
			}

			/* translators: %d: number of categories removed. */
			$detail[] = sprintf( __( '%d categories removed.', 'flosc' ), count( $record['category_ids'] ) );
		}

		if ( ! empty( $record['flow_file'] ) ) {
			$path = self::flow_dir() . basename( (string) $record['flow_file'] );
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
			/* translators: %s: flow file name. */
			$detail[] = sprintf( __( 'Flow removed: %s', 'flosc' ), basename( (string) $record['flow_file'] ) );
		}

		if ( ! empty( $record['flow_option'] ) && 0 === strpos( (string) $record['flow_option'], 'flosc_flow_' ) ) {
			delete_option( (string) $record['flow_option'] );
			$detail[] = __( 'Flow settings and messages removed.', 'flosc' );
		}

		if ( ! empty( $record['catalog_file'] ) ) {
			$path = self::catalog_dir() . basename( (string) $record['catalog_file'] );
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
			/* translators: %s: catalog file name. */
			$detail[] = sprintf( __( 'Catalog removed: %s', 'flosc' ), basename( (string) $record['catalog_file'] ) );
		}

		if ( ! empty( $record['catalog_key'] ) ) {
			$index = get_option( 'flosc_da1_catalogs', array() );
			if ( is_array( $index ) && isset( $index[ $record['catalog_key'] ] ) ) {
				unset( $index[ $record['catalog_key'] ] );
				update_option( 'flosc_da1_catalogs', $index, false );
			}
		}

		if ( ! empty( $record['flow_file'] ) ) {
			$assignments = get_option( 'flosc_da1_flow_catalogs', array() );
			if ( is_array( $assignments ) && isset( $assignments[ $record['flow_file'] ] ) ) {
				unset( $assignments[ $record['flow_file'] ] );
				update_option( 'flosc_da1_flow_catalogs', $assignments, false );
			}
		}

		return $detail;
	}

	/**
	 * Shape a result.
	 *
	 * @param bool              $ok      Whether it worked.
	 * @param string            $message Human message.
	 * @param array<int,string> $detail  Optional lines of detail.
	 * @return array{ok:bool,message:string,detail:array<int,string>}
	 */
	private static function result( $ok, $message, $detail = array() ) {
		return array(
			'ok'      => (bool) $ok,
			'message' => (string) $message,
			'detail'  => (array) $detail,
		);
	}
}
