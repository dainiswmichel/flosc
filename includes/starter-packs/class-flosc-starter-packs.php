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

	/** Term meta stamped on the category a pack creates. */
	const TERM_STAMP = '_flosc_starter_pack';

	/** Option holding what is currently installed. */
	const STATE_OPTION = 'flosc_starter_packs_installed';

	/**
	 * Category the example posts land in unless a pack or the operator says
	 * otherwise. Prefixed so it never collides with a category the site
	 * already uses, and shared across packs so example content stays in one
	 * recognisable place.
	 */
	const DEFAULT_CATEGORY_SLUG = 'flosc-example-content';

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
	 * @param string $slug          Pack slug.
	 * @param string $category_slug Optional category slug for example posts.
	 * @return array{ok:bool,message:string,detail:array<int,string>}
	 */
	public static function install( $slug, $category_slug = '' ) {
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

			if ( ! copy( $source, $target ) ) {
				return self::result( false, __( 'The flow file could not be written. Check folder permissions.', 'flosc' ) );
			}

			$flow_path           = $target;
			$record['flow_file'] = basename( $target );
			/* translators: %s: flow file name. */
			$detail[] = sprintf( __( 'Flow installed: %s', 'flosc' ), basename( $target ) );
		}

		// --- category and posts ---
		if ( ! empty( $pack['content']['file'] ) ) {
			$installed = self::install_content( $pack, $category_slug );

			if ( ! $installed['ok'] ) {
				self::rollback( $record );
				return $installed;
			}

			$record   = array_merge( $record, $installed['record'] );
			$detail[] = $installed['message'];
		}

		// --- DA1 catalog ---
		if ( ! empty( $pack['catalog']['file'] ) ) {
			$source    = $pack['dir'] . basename( (string) $pack['catalog']['file'] );
			$file_name = basename( (string) ( $pack['catalog']['install_as'] ?? $pack['catalog']['file'] ) );
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

			if ( ! copy( $source, $target ) ) {
				self::rollback( $record );
				return self::result( false, __( 'The catalog file could not be written. Check folder permissions.', 'flosc' ) );
			}

			$record['catalog_file'] = basename( $target );
			/* translators: %s: catalog file name. */
			$detail[] = sprintf( __( 'Catalog installed: %s', 'flosc' ), basename( $target ) );

			// Assign the catalog to this pack's flow.
			if ( ! empty( $record['flow_file'] ) ) {
				$assignments = get_option( 'flosc_da1_flow_catalogs', array() );
				$assignments = is_array( $assignments ) ? $assignments : array();
				$catalog_key = pathinfo( basename( $target ), PATHINFO_FILENAME );

				$assignments[ $record['flow_file'] ] = array( $catalog_key );
				update_option( 'flosc_da1_flow_catalogs', $assignments, false );

				$record['catalog_key'] = $catalog_key;
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
	 * @param array<string,mixed> $pack          Manifest.
	 * @param string              $category_slug Operator override, if any.
	 * @return array{ok:bool,message:string,detail:array<int,string>,record:array<string,mixed>}
	 */
	private static function install_content( $pack, $category_slug = '' ) {
		$source = $pack['dir'] . basename( (string) $pack['content']['file'] );

		if ( ! is_readable( $source ) ) {
			return self::result( false, __( 'The pack is missing its content file.', 'flosc' ) ) + array( 'record' => array() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file shipped inside the plugin.
		$posts = json_decode( (string) file_get_contents( $source ), true );

		if ( ! is_array( $posts ) || empty( $posts ) ) {
			return self::result( false, __( 'The pack content file could not be read.', 'flosc' ) ) + array( 'record' => array() );
		}

		// Operator override wins, then the pack's own preference, then the shared default.
		$category_slug = sanitize_title( (string) $category_slug );

		if ( '' === $category_slug ) {
			$category_slug = sanitize_title( (string) ( $pack['content']['category_slug'] ?? self::DEFAULT_CATEGORY_SLUG ) );
		}

		if ( '' === $category_slug ) {
			$category_slug = self::DEFAULT_CATEGORY_SLUG;
		}

		$category_name = (string) ( $pack['content']['category'] ?? 'FLOSC Example Content' );

		if ( term_exists( $category_slug, 'category' ) ) {
			return self::result(
				false,
				sprintf(
					/* translators: %s: category slug. */
					__( 'A category %s already exists. Rename or remove it first.', 'flosc' ),
					$category_slug
				)
			) + array( 'record' => array() );
		}

		$term = wp_insert_term( $category_name, 'category', array( 'slug' => $category_slug ) );

		if ( is_wp_error( $term ) ) {
			return self::result( false, $term->get_error_message() ) + array( 'record' => array() );
		}

		$term_id = (int) $term['term_id'];
		add_term_meta( $term_id, self::TERM_STAMP, $pack['slug'], true );

		$created = 0;

		foreach ( $posts as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['title'] ) ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_title'    => sanitize_text_field( (string) $entry['title'] ),
					'post_content'  => wp_kses_post( (string) ( $entry['content'] ?? '' ) ),
					'post_excerpt'  => sanitize_text_field( (string) ( $entry['excerpt'] ?? '' ) ),
					'post_status'   => 'publish',
					'post_type'     => 'post',
					'post_category' => array( $term_id ),
					'meta_input'    => array(
						self::POST_STAMP => $pack['slug'],
					),
				),
				true
			);

			if ( ! is_wp_error( $post_id ) ) {
				$created++;

				// Access level the journey gates this post at, when the pack says so.
				if ( ! empty( $entry['access'] ) ) {
					update_post_meta( $post_id, '_flosc_access_level', sanitize_key( (string) $entry['access'] ) );
				}
			}
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: number of posts, 2: category name. */
				__( '%1$d posts created in the %2$s category.', 'flosc' ),
				$created,
				$category_name
			),
			'detail'  => array(),
			'record'  => array(
				'category_id'   => $term_id,
				'category_slug' => $category_slug,
				'post_count'    => $created,
			),
		);
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
	 * @return array{ok:bool,message:string,detail:array<int,string>,record:array<string,mixed>}
	 */
	private static function register_flow( $pack, $flow_path, $category_slug = '' ) {
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
		if ( null !== get_option( $flow_key, null ) ) {
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

		if ( ! empty( $record['category_id'] ) ) {
			wp_delete_term( (int) $record['category_id'], 'category' );
			$detail[] = __( 'Category removed.', 'flosc' );
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
