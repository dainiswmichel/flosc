<?php
/**
 * Site content index for FLOSC AI retrieval.
 *
 * Stores full post bodies + hierarchy per flow (uploads data dir).
 * Chat turns retrieve selectively; admin AI tab shows a manageable table.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLOSC_Site_Content_Index {

	const MAX_BODY_CHARS = 200000;
	const DEFAULT_RETRIEVE_LIMIT = 5;

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_flosc_site_index_rebuild', array( $this, 'handle_rebuild' ) );
		add_action( 'admin_post_flosc_site_index_exclude', array( $this, 'handle_exclude' ) );
		add_action( 'admin_post_flosc_site_index_include', array( $this, 'handle_include' ) );
		add_action( 'admin_post_flosc_site_index_keywords', array( $this, 'handle_keywords' ) );
		add_action( 'admin_post_flosc_site_index_reindex_one', array( $this, 'handle_reindex_one' ) );
	}

	/**
	 * Flow stem from IVR filename.
	 *
	 * @param string $ivr_file e.g. flosc_default_technical_ivr.md
	 * @return string
	 */
	public function stem_from_ivr( $ivr_file ) {
		$stem = sanitize_key( pathinfo( basename( (string) $ivr_file ), PATHINFO_FILENAME ) );
		return $stem !== '' ? $stem : 'default';
	}

	/**
	 * Absolute path for the site-wide content index (uploads data dir).
	 * One library for the whole site; each flow binds a category for freeline/sell.
	 *
	 * @param string $flow_stem Unused (kept for call-site compatibility).
	 * @return string
	 */
	public function index_path( $flow_stem = '' ) {
		unset( $flow_stem );
		if ( ! function_exists( 'flosc_data_file_path' ) ) {
			return '';
		}
		return flosc_data_file_path( 'content-index-site.json' );
	}

	/**
	 * Load the site-wide index document.
	 *
	 * @param string $flow_stem Unused (kept for call-site compatibility).
	 * @return array{built_at:string,scope:string,category_slugs:array,category_ids:array,posts:array}
	 */
	public function load( $flow_stem = '' ) {
		$empty = array(
			'built_at'       => '',
			'scope'          => 'site',
			'category_slugs' => array(),
			'category_ids'   => array(),
			'posts'          => array(),
		);
		$path = $this->index_path( $flow_stem );
		if ( $path === '' || ! is_readable( $path ) ) {
			// Legacy: one earlier build wrote per-flow files — try once if site file missing.
			$legacy_stem = sanitize_key( (string) $flow_stem );
			if ( $legacy_stem !== '' && function_exists( 'flosc_data_file_path' ) ) {
				$legacy = flosc_data_file_path( 'content-index-' . $legacy_stem . '.json' );
				if ( $legacy !== '' && is_readable( $legacy ) ) {
					$path = $legacy;
				} else {
					return $empty;
				}
			} else {
				return $empty;
			}
		}
		$raw = function_exists( 'flosc_fs_get_contents' ) ? flosc_fs_get_contents( $path ) : file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads index JSON under uploads via flosc_data_file_path
		if ( ! is_string( $raw ) || $raw === '' ) {
			return $empty;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return $empty;
		}
		$posts = isset( $data['posts'] ) && is_array( $data['posts'] ) ? $data['posts'] : array();
		// Normalize keys to string post IDs.
		$normalized = array();
		foreach ( $posts as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['post_id'] ) ? (int) $row['post_id'] : (int) $key;
			if ( $id <= 0 ) {
				continue;
			}
			$row['post_id'] = $id;
			$normalized[ (string) $id ] = $row;
		}
		return array(
			'built_at'       => sanitize_text_field( (string) ( $data['built_at'] ?? '' ) ),
			'category_slugs' => array_values( array_filter( array_map( 'sanitize_title', (array) ( $data['category_slugs'] ?? array() ) ) ) ),
			'category_ids'   => array_values( array_filter( array_map( 'absint', (array) ( $data['category_ids'] ?? array() ) ) ) ),
			'posts'          => $normalized,
		);
	}

	/**
	 * Persist index document.
	 *
	 * @param string $flow_stem
	 * @param array  $doc
	 * @return bool
	 */
	public function save( $flow_stem, array $doc ) {
		$path = $this->index_path( $flow_stem );
		if ( $path === '' || ! function_exists( 'flosc_write_data_file' ) ) {
			return false;
		}
		$payload = wp_json_encode( $doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $payload ) ) {
			return false;
		}
		return (bool) flosc_write_data_file( $path, $payload );
	}

	/**
	 * Category slugs configured for this flow (content_item_groups + content_item_category).
	 *
	 * @param string $flow_stem Optional; uses current flow settings when empty.
	 * @return string[]
	 */
	public function resolve_category_slugs( $flow_stem = '' ) {
		$slugs = array();
		$fs    = array();

		// Admin AI tab already loads the working flow into this global.
		if ( ! empty( $GLOBALS['flosc_current_settings'] ) && is_array( $GLOBALS['flosc_current_settings'] ) ) {
			$fs = $GLOBALS['flosc_current_settings'];
		}

		if ( empty( $fs ) && $flow_stem !== '' ) {
			$candidates = array(
				'flosc_flow_' . sanitize_key( $flow_stem ),
				sanitize_key( $flow_stem ),
			);
			if ( function_exists( 'flosc_resolve_flow_option_key_for_ivr' ) && ! empty( $GLOBALS['flosc_current_ivr'] ) ) {
				$candidates[] = flosc_resolve_flow_option_key_for_ivr( (string) $GLOBALS['flosc_current_ivr'] );
			}
			foreach ( $candidates as $key ) {
				if ( ! is_string( $key ) || $key === '' ) {
					continue;
				}
				$opt = get_option( $key, null );
				if ( is_array( $opt ) && ( ! empty( $opt['content_item_category'] ) || ! empty( $opt['content_item_groups'] ) ) ) {
					$fs = $opt;
					break;
				}
			}
		}

		if ( ! empty( $fs['content_item_category'] ) ) {
			$slugs[] = sanitize_title( (string) $fs['content_item_category'] );
		}
		if ( ! empty( $fs['content_item_groups'] ) && is_array( $fs['content_item_groups'] ) ) {
			foreach ( $fs['content_item_groups'] as $group ) {
				if ( is_array( $group ) && ! empty( $group['category'] ) ) {
					$slugs[] = sanitize_title( (string) $group['category'] );
				}
			}
		}

		if ( empty( $slugs ) && function_exists( 'flosc_get_setting' ) ) {
			$cat = flosc_get_setting( 'content_item_category', '' );
			if ( is_string( $cat ) && $cat !== '' ) {
				$slugs[] = sanitize_title( $cat );
			}
			$groups = flosc_get_setting( 'content_item_groups', array() );
			if ( is_array( $groups ) ) {
				foreach ( $groups as $group ) {
					if ( is_array( $group ) && ! empty( $group['category'] ) ) {
						$slugs[] = sanitize_title( (string) $group['category'] );
					}
				}
			}
		}

		if ( empty( $slugs ) ) {
			$global = get_option( 'flosc_content_item_category', '' );
			if ( is_string( $global ) && $global !== '' ) {
				$slugs[] = sanitize_title( $global );
			}
		}

		return array_values( array_unique( array_filter( $slugs ) ) );
	}

	/**
	 * @param string[] $slugs
	 * @return int[]
	 */
	public function category_ids_from_slugs( array $slugs ) {
		$ids = array();
		foreach ( $slugs as $slug ) {
			if ( is_numeric( $slug ) ) {
				$ids[] = (int) $slug;
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( $slug ), 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Build / rebuild the site-wide content index (all published posts, capped).
	 * Flow content category is not required — that binds freeline/sell on the Content tab.
	 * Preserves excluded flags and manual keywords by post ID.
	 *
	 * @param string $flow_stem Unused for path; kept for call-site compatibility.
	 * @return array{ok:bool,message:string,count:int}
	 */
	public function rebuild( $flow_stem = '' ) {
		$previous   = $this->load( $flow_stem );
		$prev_posts = is_array( $previous['posts'] ) ? $previous['posts'] : array();

		// Safety cap: large sites still get a usable library; admin can exclude rows.
		$max_posts = (int) apply_filters( 'flosc_site_content_index_max_posts', 1000 );
		if ( $max_posts < 1 ) {
			$max_posts = 1000;
		}

		$posts = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => $max_posts,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'no_found_rows'          => true,
			)
		);

		$indexed = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$id_key   = (string) $post->ID;
			$prev     = isset( $prev_posts[ $id_key ] ) && is_array( $prev_posts[ $id_key ] ) ? $prev_posts[ $id_key ] : array();
			$excluded = ! empty( $prev['excluded'] );
			$manual   = isset( $prev['keywords_manual'] ) ? (string) $prev['keywords_manual'] : '';

			$indexed[ $id_key ] = $this->build_row_from_post( $post, $manual, $excluded );
		}

		$doc = array(
			'built_at'       => gmdate( 'c' ),
			'scope'          => 'site',
			'category_slugs' => array(),
			'category_ids'   => array(),
			'posts'          => $indexed,
		);

		if ( ! $this->save( $flow_stem, $doc ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Could not write the index file under uploads. Check that the uploads directory is writable.', 'flosc' ),
				'count'   => 0,
			);
		}

		return array(
			'ok'      => true,
			/* translators: %d: number of posts indexed */
			'message' => sprintf( __( 'Site index built: %d published posts stored for selective AI retrieval.', 'flosc' ), count( $indexed ) ),
			'count'   => count( $indexed ),
		);
	}

	/**
	 * How many indexed posts belong to this flow's content category (for admin stats).
	 *
	 * @param string $flow_stem
	 * @return int
	 */
	public function count_in_flow_category( $flow_stem ) {
		$slugs = $this->resolve_category_slugs( $flow_stem );
		if ( empty( $slugs ) ) {
			return 0;
		}
		$doc   = $this->load( $flow_stem );
		$posts = $doc['posts'];
		$n     = 0;
		foreach ( $posts as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['excluded'] ) ) {
				continue;
			}
			$cats = isset( $row['categories'] ) && is_array( $row['categories'] ) ? $row['categories'] : array();
			foreach ( $slugs as $slug ) {
				if ( in_array( $slug, $cats, true ) ) {
					++$n;
					break;
				}
			}
		}
		return $n;
	}

	/**
	 * @param WP_Post $post
	 * @param string  $keywords_manual
	 * @param bool    $excluded
	 * @return array
	 */
	public function build_row_from_post( WP_Post $post, $keywords_manual = '', $excluded = false ) {
		$body = wp_strip_all_tags( (string) $post->post_content );
		$body = preg_replace( '/\s+/u', ' ', $body );
		$body = is_string( $body ) ? trim( $body ) : '';
		if ( strlen( $body ) > self::MAX_BODY_CHARS ) {
			$body = substr( $body, 0, self::MAX_BODY_CHARS );
		}

		$auto_kw = $this->derive_keywords( $post, $body );
		$manual  = sanitize_text_field( (string) $keywords_manual );
		$merged  = $manual !== '' ? $this->merge_keywords( $auto_kw, $manual ) : $auto_kw;

		$access = get_post_meta( $post->ID, '_flosc_access_level', true );
		if ( ! is_string( $access ) || $access === '' ) {
			// Content subcategory visitor|guest|member if used by sample packs.
			$sub = get_post_meta( $post->ID, '_flosc_content_subcategory', true );
			if ( in_array( $sub, array( 'visitor', 'guest', 'member' ), true ) ) {
				$access = $sub;
			} else {
				$access = 'member';
			}
		}
		$access = sanitize_key( (string) $access );

		$parent = (int) $post->post_parent;
		$cats   = array();
		$terms  = get_the_terms( $post->ID, 'category' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term && ! is_wp_error( $term ) ) {
					$cats[] = $term->slug;
				}
			}
		}

		$snippet = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 160 ) : substr( $body, 0, 160 );

		return array(
			'post_id'          => (int) $post->ID,
			'title'            => sanitize_text_field( get_the_title( $post ) ),
			'content'          => $body,
			'snippet'          => sanitize_text_field( $snippet ),
			'keywords'         => $merged,
			'keywords_manual'  => $manual,
			'access'           => $access,
			'excluded'         => (bool) $excluded,
			'parent'           => $parent,
			'categories'       => $cats,
			'modified'         => (string) $post->post_modified_gmt,
			'indexed_at'       => gmdate( 'c' ),
			'url'              => esc_url_raw( (string) get_permalink( $post ) ),
			'lesson_number'    => sanitize_text_field( (string) get_post_meta( $post->ID, '_flosc_lesson_number', true ) ),
		);
	}

	/**
	 * @param WP_Post $post
	 * @param string  $body
	 * @return string comma-separated
	 */
	private function derive_keywords( WP_Post $post, $body ) {
		$parts = array();
		$title = get_the_title( $post );
		if ( $title !== '' ) {
			$parts[] = $title;
		}
		$tags = get_the_tags( $post->ID );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				if ( $tag && ! is_wp_error( $tag ) ) {
					$parts[] = $tag->name;
				}
			}
		}
		$terms = get_the_terms( $post->ID, 'category' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term && ! is_wp_error( $term ) ) {
					$parts[] = $term->name;
				}
			}
		}
		// A few distinctive words from body (length > 4).
		$words = preg_split( '/[^a-zA-ZāčēģīķļņšūžĀČĒĢĪĶĻŅŠŪŽ0-9]+/u', $body, -1, PREG_SPLIT_NO_EMPTY );
		if ( is_array( $words ) ) {
			$freq = array();
			foreach ( $words as $w ) {
				if ( function_exists( 'mb_strlen' ) ? mb_strlen( $w ) < 5 : strlen( $w ) < 5 ) {
					continue;
				}
				$lw = function_exists( 'mb_strtolower' ) ? mb_strtolower( $w ) : strtolower( $w );
				$freq[ $lw ] = ( $freq[ $lw ] ?? 0 ) + 1;
			}
			arsort( $freq );
			$i = 0;
			foreach ( array_keys( $freq ) as $w ) {
				$parts[] = $w;
				if ( ++$i >= 12 ) {
					break;
				}
			}
		}
		return $this->merge_keywords( '', implode( ', ', $parts ) );
	}

	/**
	 * @param string $base
	 * @param string $extra
	 * @return string
	 */
	private function merge_keywords( $base, $extra ) {
		$all = array();
		foreach ( array( $base, $extra ) as $chunk ) {
			foreach ( preg_split( '/\s*,\s*/', (string) $chunk ) as $piece ) {
				$piece = sanitize_text_field( trim( $piece ) );
				if ( $piece === '' ) {
					continue;
				}
				$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $piece ) : strtolower( $piece );
				$all[ $key ] = $piece;
			}
		}
		return implode( ', ', array_values( $all ) );
	}

	/**
	 * Light hierarchy map for AI (titles / ids / access) — no full bodies.
	 *
	 * @param string $flow_stem
	 * @param string $access_level visitor|guest|member
	 * @return string
	 */
	public function format_map_for_ai( $flow_stem, $access_level = 'visitor' ) {
		$doc   = $this->load( $flow_stem );
		$posts = $doc['posts'];
		if ( empty( $posts ) ) {
			return '';
		}
		$lines = array( '**Site content index (titles):**', '' );
		foreach ( $posts as $row ) {
			if ( ! empty( $row['excluded'] ) ) {
				continue;
			}
			$req = sanitize_key( (string) ( $row['access'] ?? 'member' ) );
			$ok  = $this->access_allows( $access_level, $req );
			$lock = $ok ? '' : ' [locked]';
			$lines[] = sprintf(
				'- #%d %s%s (access: %s)',
				(int) ( $row['post_id'] ?? 0 ),
				(string) ( $row['title'] ?? '' ),
				$lock,
				$req
			);
		}
		return implode( "\n", $lines );
	}

	/**
	 * Selective full-text retrieval from the index.
	 *
	 * @param string $flow_stem
	 * @param string $keywords
	 * @param string $access_level
	 * @param int    $limit
	 * @return string Human-readable block for the model
	 */
	public function search( $flow_stem, $keywords, $access_level = 'visitor', $limit = self::DEFAULT_RETRIEVE_LIMIT ) {
		$doc   = $this->load( $flow_stem );
		$posts = $doc['posts'];
		if ( empty( $posts ) ) {
			return '';
		}

		$limit = max( 1, min( 20, (int) $limit ) );
		$q     = trim( (string) $keywords );
		$terms = preg_split( '/\s+/', function_exists( 'mb_strtolower' ) ? mb_strtolower( $q ) : strtolower( $q ) );
		$terms = is_array( $terms ) ? array_filter( $terms ) : array();

		$scored = array();
		foreach ( $posts as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['excluded'] ) ) {
				continue;
			}
			$req = sanitize_key( (string) ( $row['access'] ?? 'member' ) );
			if ( ! $this->access_allows( $access_level, $req ) ) {
				// List locked title only in map path; skip full body here.
				continue;
			}
			$hay = function_exists( 'mb_strtolower' )
				? mb_strtolower( (string) ( $row['title'] ?? '' ) . ' ' . (string) ( $row['keywords'] ?? '' ) . ' ' . (string) ( $row['content'] ?? '' ) )
				: strtolower( (string) ( $row['title'] ?? '' ) . ' ' . (string) ( $row['keywords'] ?? '' ) . ' ' . (string) ( $row['content'] ?? '' ) );

			$score = 0;
			if ( $q !== '' && $hay !== '' ) {
				if ( false !== strpos( $hay, function_exists( 'mb_strtolower' ) ? mb_strtolower( $q ) : strtolower( $q ) ) ) {
					$score += 50;
				}
				foreach ( $terms as $t ) {
					if ( strlen( $t ) < 2 ) {
						continue;
					}
					if ( false !== strpos( $hay, $t ) ) {
						$score += 5;
					}
				}
			} else {
				$score = 1; // empty query: allow first N
			}
			// Numeric lesson / post id match.
			if ( is_numeric( $q ) ) {
				if ( (int) $q === (int) ( $row['post_id'] ?? 0 ) ) {
					$score += 100;
				}
				if ( (string) $q === (string) ( $row['lesson_number'] ?? '' ) ) {
					$score += 80;
				}
			}
			// Prefer posts in the active flow's content category when one is set (same library, product slice first).
			if ( $score > 0 && $flow_stem !== '' ) {
				static $flow_slugs_cache = null;
				if ( null === $flow_slugs_cache ) {
					$flow_slugs_cache = $this->resolve_category_slugs( $flow_stem );
				}
				if ( ! empty( $flow_slugs_cache ) ) {
					$rcats = isset( $row['categories'] ) && is_array( $row['categories'] ) ? $row['categories'] : array();
					foreach ( $flow_slugs_cache as $fs_slug ) {
						if ( in_array( $fs_slug, $rcats, true ) ) {
							$score += 15;
							break;
						}
					}
				}
			}
			if ( $score > 0 ) {
				$scored[] = array( 'score' => $score, 'row' => $row );
			}
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		$scored = array_slice( $scored, 0, $limit );

		if ( empty( $scored ) ) {
			return "No indexed posts matched: {$keywords}";
		}

		$out = '**Site content index — full posts (' . count( $scored ) . "):**\n\n";
		foreach ( $scored as $hit ) {
			$row = $hit['row'];
			$out .= '**' . (string) ( $row['title'] ?? '' ) . "**\n";
			$out .= 'ID: ' . (int) ( $row['post_id'] ?? 0 );
			if ( ! empty( $row['url'] ) ) {
				$out .= ' | URL: ' . (string) $row['url'];
			}
			$out .= "\n";
			if ( ! empty( $row['keywords'] ) ) {
				$out .= 'Keywords: ' . (string) $row['keywords'] . "\n";
			}
			$out .= "\n" . (string) ( $row['content'] ?? '' ) . "\n\n---\n\n";
		}
		return $out;
	}

	/**
	 * @param string $user_level
	 * @param string $required
	 * @return bool
	 */
	public function access_allows( $user_level, $required ) {
		$hierarchy = array(
			'visitor' => 1,
			'guest'   => 2,
			'member'  => 3,
		);
		$u = $hierarchy[ sanitize_key( (string) $user_level ) ] ?? 1;
		$r = $hierarchy[ sanitize_key( (string) $required ) ] ?? 3;
		return $u >= $r;
	}

	/**
	 * Set excluded flag and save.
	 *
	 * @param string $flow_stem
	 * @param int    $post_id
	 * @param bool   $excluded
	 * @return bool
	 */
	public function set_excluded( $flow_stem, $post_id, $excluded ) {
		$doc = $this->load( $flow_stem );
		$key = (string) (int) $post_id;
		if ( ! isset( $doc['posts'][ $key ] ) ) {
			return false;
		}
		$doc['posts'][ $key ]['excluded'] = (bool) $excluded;
		return $this->save( $flow_stem, $doc );
	}

	/**
	 * @param string $flow_stem
	 * @param int    $post_id
	 * @param string $manual_keywords
	 * @return bool
	 */
	public function set_manual_keywords( $flow_stem, $post_id, $manual_keywords ) {
		$doc = $this->load( $flow_stem );
		$key = (string) (int) $post_id;
		if ( ! isset( $doc['posts'][ $key ] ) ) {
			return false;
		}
		$manual = sanitize_text_field( (string) $manual_keywords );
		$doc['posts'][ $key ]['keywords_manual'] = $manual;
		$title   = (string) ( $doc['posts'][ $key ]['title'] ?? '' );
		$content = (string) ( $doc['posts'][ $key ]['content'] ?? '' );
		// Re-derive light auto keywords from title + body, then fold in manual overrides.
		$auto = $this->merge_keywords( $title, implode( ', ', array_slice( preg_split( '/\s+/', $content ) ?: array(), 0, 24 ) ) );
		$doc['posts'][ $key ]['keywords'] = $manual !== '' ? $this->merge_keywords( $auto, $manual ) : $auto;
		return $this->save( $flow_stem, $doc );
	}

	/**
	 * Reindex a single post if still in category.
	 *
	 * @param string $flow_stem
	 * @param int    $post_id
	 * @return bool
	 */
	public function reindex_one( $flow_stem, $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return false;
		}
		$doc  = $this->load( $flow_stem );
		$key  = (string) (int) $post_id;
		$prev = isset( $doc['posts'][ $key ] ) ? $doc['posts'][ $key ] : array();
		$manual   = isset( $prev['keywords_manual'] ) ? (string) $prev['keywords_manual'] : '';
		$excluded = ! empty( $prev['excluded'] );
		$doc['posts'][ $key ] = $this->build_row_from_post( $post, $manual, $excluded );
		if ( empty( $doc['built_at'] ) ) {
			$doc['built_at'] = gmdate( 'c' );
		}
		return $this->save( $flow_stem, $doc );
	}

	// ─── Admin POST handlers ─────────────────────────────────────────────

	/**
	 * @return void
	 */
	private function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the site content index.', 'flosc' ) );
		}
	}

	/**
	 * @return string
	 */
	private function ivr_from_request( $request ) {
		$ivr = isset( $request['flosc_return_ivr'] ) ? sanitize_file_name( (string) $request['flosc_return_ivr'] ) : '';
		return $ivr;
	}

	/**
	 * @param string $ivr
	 * @param string $action
	 * @param string $error
	 * @return void
	 */
	private function redirect_ai( $ivr, $action, $error = '' ) {
		$args = array(
			'page'             => 'flosc-settings',
			'tab'              => 'ai',
			'site_index_action'=> sanitize_key( $action ),
		);
		if ( $ivr !== '' ) {
			$args['ivr'] = $ivr;
		}
		if ( $error !== '' ) {
			$args['site_index_error'] = rawurlencode( $error );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) . '#flosc-site-index-section' );
		exit;
	}

	/**
	 * @return void
	 */
	public function handle_rebuild() {
		$this->require_admin();
		// Its own field name, not the default _wpnonce. This form renders on the
		// same page as the settings form, and two fields called _wpnonce in one
		// submission leave PHP holding only the last of them.
		check_admin_referer( 'flosc_site_index_rebuild', 'flosc_sci_nonce' );
		$ivr  = $this->ivr_from_request( wp_unslash( $_POST ) );
		$stem = $this->stem_from_ivr( $ivr );
		$result = $this->rebuild( $stem );
		if ( empty( $result['ok'] ) ) {
			$this->redirect_ai( $ivr, 'error', (string) ( $result['message'] ?? 'Rebuild failed.' ) );
		}
		set_transient(
			'flosc_site_index_notice_' . get_current_user_id(),
			array(
				'action'  => 'rebuilt',
				'message' => (string) $result['message'],
			),
			60
		);
		$this->redirect_ai( $ivr, 'rebuilt' );
	}

	/**
	 * @return void
	 */
	public function handle_exclude() {
		$this->require_admin();
		check_admin_referer( 'flosc_site_index_exclude' );
		$ivr = $this->ivr_from_request( wp_unslash( $_POST ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer in this method before read
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$stem    = $this->stem_from_ivr( $ivr );
		if ( $post_id && $this->set_excluded( $stem, $post_id, true ) ) {
			$this->redirect_ai( $ivr, 'excluded' );
		}
		$this->redirect_ai( $ivr, 'error', __( 'Could not exclude that post.', 'flosc' ) );
	}

	/**
	 * @return void
	 */
	public function handle_include() {
		$this->require_admin();
		check_admin_referer( 'flosc_site_index_include' );
		$ivr = $this->ivr_from_request( wp_unslash( $_POST ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer in this method before read
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$stem    = $this->stem_from_ivr( $ivr );
		if ( $post_id && $this->set_excluded( $stem, $post_id, false ) ) {
			$this->redirect_ai( $ivr, 'included' );
		}
		$this->redirect_ai( $ivr, 'error', __( 'Could not include that post.', 'flosc' ) );
	}

	/**
	 * @return void
	 */
	public function handle_keywords() {
		$this->require_admin();
		check_admin_referer( 'flosc_site_index_keywords' );
		$ivr = $this->ivr_from_request( wp_unslash( $_POST ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer in this method before read
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer in this method before read
		$kw      = isset( $_POST['keywords_manual'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['keywords_manual'] ) ) : '';
		$stem    = $this->stem_from_ivr( $ivr );
		if ( $post_id && $this->set_manual_keywords( $stem, $post_id, $kw ) ) {
			$this->redirect_ai( $ivr, 'keywords' );
		}
		$this->redirect_ai( $ivr, 'error', __( 'Could not save keywords.', 'flosc' ) );
	}

	/**
	 * @return void
	 */
	public function handle_reindex_one() {
		$this->require_admin();
		check_admin_referer( 'flosc_site_index_reindex_one' );
		$ivr = $this->ivr_from_request( wp_unslash( $_POST ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer in this method before read
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$stem    = $this->stem_from_ivr( $ivr );
		if ( $post_id && $this->reindex_one( $stem, $post_id ) ) {
			$this->redirect_ai( $ivr, 'reindexed' );
		}
		$this->redirect_ai( $ivr, 'error', __( 'Could not reindex that post.', 'flosc' ) );
	}
}

/**
 * Bootstrap singleton (admin hooks).
 */
function flosc_site_content_index() {
	return FLOSC_Site_Content_Index::instance();
}
