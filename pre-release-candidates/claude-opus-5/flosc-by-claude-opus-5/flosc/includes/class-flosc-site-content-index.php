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
		/*
		 * Categories and tags get the same two fields the post metabox and the
		 * Content tab carry. A floscAdmin setting up a category is on the
		 * category screen, not the Content tab, and making them go somewhere
		 * else to say who may read it is how rules end up unset.
		 */
		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $this, 'render_term_vgm_add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_vgm_edit_fields' ) );
		}
		add_action( 'created_term', array( $this, 'save_term_vgm' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'save_term_vgm' ), 10, 3 );

		add_action( 'admin_post_flosc_site_index_rebuild', array( $this, 'handle_rebuild' ) );
		add_action( 'admin_post_flosc_site_index_exclude', array( $this, 'handle_exclude' ) );
		add_action( 'admin_post_flosc_site_index_include', array( $this, 'handle_include' ) );
		add_action( 'admin_post_flosc_site_index_keywords', array( $this, 'handle_keywords' ) );
		add_action( 'admin_post_flosc_site_index_reindex_one', array( $this, 'handle_reindex_one' ) );
	}

	/**
	 * The two selects, shared by the add and edit forms.
	 *
	 * @param string $tier
	 * @param string $depth
	 * @return void
	 */
	private function term_vgm_selects( $tier, $depth ) {
		if ( ! function_exists( 'flosc_vgm_tier_labels' ) ) {
			return;
		}
		?>
		<select name="flosc_vgm" id="flosc_vgm">
			<option value="">&mdash; <?php esc_html_e( 'No rule', 'flosc' ); ?> &mdash;</option>
			<?php echo flosc_vgm_options_markup( flosc_vgm_tier_labels(), (string) $tier ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped in flosc_vgm_options_markup() ?>
		</select>
		<select name="flosc_depth" id="flosc_depth">
			<option value="">&mdash; <?php esc_html_e( 'No rule', 'flosc' ); ?> &mdash;</option>
			<?php echo flosc_vgm_options_markup( flosc_vgm_depth_labels(), (string) $depth ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped in flosc_vgm_options_markup() ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'What the AI may retrieve of posts in this term. The tier is a floor: Visitors also covers guests and members. Both halves must be set for the rule to count. A rule on a post beats one on its tags, which beats one on its categories.', 'flosc' ); ?>
		</p>
		<?php
	}

	/**
	 * @return void
	 */
	public function render_term_vgm_add_fields() {
		?>
		<div class="form-field">
			<label for="flosc_vgm"><?php esc_html_e( 'FLOSC AI retrieval', 'flosc' ); ?></label>
			<?php
			wp_nonce_field( 'flosc_term_vgm', 'flosc_term_vgm_nonce' );
			$this->term_vgm_selects( '', '' );
			?>
		</div>
		<?php
	}

	/**
	 * @param WP_Term $term
	 * @return void
	 */
	public function render_term_vgm_edit_fields( $term ) {
		$term_id = isset( $term->term_id ) ? (int) $term->term_id : 0;
		?>
		<tr class="form-field">
			<th scope="row"><label for="flosc_vgm"><?php esc_html_e( 'FLOSC AI retrieval', 'flosc' ); ?></label></th>
			<td>
				<?php
				wp_nonce_field( 'flosc_term_vgm', 'flosc_term_vgm_nonce' );
				$this->term_vgm_selects(
					get_term_meta( $term_id, '_flosc_vgm', true ),
					get_term_meta( $term_id, '_flosc_depth', true )
				);
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 * @return void
	 */
	public function save_term_vgm( $term_id, $tt_id = 0, $taxonomy = '' ) {
		unset( $tt_id );

		if ( ! in_array( (string) $taxonomy, array( 'category', 'post_tag' ), true ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$nonce = isset( $_POST['flosc_term_vgm_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['flosc_term_vgm_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'flosc_term_vgm' ) ) {
			return;
		}

		foreach ( array(
			'flosc_vgm'   => array( '_flosc_vgm', 'tier_token' ),
			'flosc_depth' => array( '_flosc_depth', 'depth_token' ),
		) as $field => $spec ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$value = call_user_func( array( __CLASS__, $spec[1] ), sanitize_key( wp_unslash( $_POST[ $field ] ) ) );
			if ( '' === $value ) {
				delete_term_meta( (int) $term_id, $spec[0] );
			} else {
				update_term_meta( (int) $term_id, $spec[0], $value );
			}
		}
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
		/*
		 * Row keys are strings, not post IDs.
		 *
		 * This cast every key to (int) and dropped anything that came out <= 0,
		 * which is correct for WordPress posts and fatal for anything else: a
		 * BuddyBoss row keyed "bb_group:52" became 0 and was silently discarded
		 * on the first save, with no error and no missing-row warning. The
		 * adapter would have appeared to work and the library would have been
		 * empty of groups.
		 *
		 * post_id stays an int for WordPress rows because exclusions, manual
		 * keywords and the offer wiring all key on it. A group row carries
		 * post_id 0 and identifies itself by id and kind.
		 */
		$normalized = array();
		foreach ( $posts as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row_id = isset( $row['id'] ) ? self::normalize_row_id( $row['id'] ) : '';
			if ( '' === $row_id ) {
				$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : (int) $key;
				$row_id  = $post_id > 0 ? (string) $post_id : self::normalize_row_id( $key );
			}
			if ( '' === $row_id ) {
				continue;
			}

			$row['id']      = $row_id;
			$row['kind']    = isset( $row['kind'] ) ? sanitize_key( (string) $row['kind'] ) : 'post';
			$row['post_id'] = isset( $row['post_id'] ) ? (int) $row['post_id'] : ( ctype_digit( $row_id ) ? (int) $row_id : 0 );

			$normalized[ $row_id ] = $row;
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
	/**
	 * The group catalogue for one turn, filtered to what this person may see.
	 *
	 * Retrieval by keyword will not produce "/groups/lesaep-learners/" from a
	 * blog post, so the groups a visitor is allowed to hear about ride on the
	 * turn as a short list. Thirteen rows is one or two kilobytes.
	 *
	 * Fail closed, in this order:
	 *
	 *   1. the person's tier must appear in that row's VGM string
	 *   2. an excluded row is never shown
	 *   3. a private or hidden group is never given to somebody who is not a
	 *      member of it — it may be named as a gated next step, never described
	 *
	 * FLOSC can tighten BuddyBoss privacy. It can never loosen it.
	 *
	 * @param string $flow_stem  Flow.
	 * @param string $user_level visitor | guest | member.
	 * @return string Empty when there is nothing this person may be shown.
	 */
	public function format_groups_for_ai( $flow_stem = '', $user_level = 'visitor' ) {
		$policy = self::buddyboss_policy( $flow_stem );
		if ( empty( $policy['enabled'] ) ) {
			return '';
		}

		$doc  = $this->load( $flow_stem );
		$rows = isset( $doc['posts'] ) && is_array( $doc['posts'] ) ? $doc['posts'] : array();
		$tier = in_array( $user_level, array( 'visitor', 'guest', 'member' ), true ) ? $user_level : 'visitor';

		$open   = array();
		$gated  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || 'bb_group' !== ( $row['kind'] ?? '' ) ) {
				continue;
			}
			if ( ! empty( $row['excluded'] ) ) {
				continue;
			}

			$allowed = preg_split( '/\s+/', (string) ( $row['access'] ?? '' ) ) ?: array();
			if ( ! in_array( $tier, $allowed, true ) ) {
				continue;
			}

			$name   = (string) ( $row['title'] ?? '' );
			$url    = (string) ( $row['url'] ?? '' );
			$status = (string) ( $row['bb_status'] ?? 'public' );

			if ( '' === $name ) {
				continue;
			}

			if ( 'public' === $status ) {
				$desc    = (string) ( $row['snippet'] ?? '' );
				$open[]  = '- ' . $name . ( '' !== $desc ? ' — ' . $desc : '' ) . ' Public.'
					. ( '' !== $url ? "\n  " . $url : '' );
				continue;
			}

			// Not public. Naming it is a choice the floscAdmin already made by
			// putting this tier in the row's VGM. Describing it is not.
			if ( self::viewer_is_group_member( (int) ( $row['group_id'] ?? 0 ) ) ) {
				$open[] = '- ' . $name . ' — you are a member. '
					. ( '' !== $url ? "\n  " . $url : '' );
			} else {
				$gated[] = '- ' . $name . ' — members only. Name it if it fits what they want; do not describe it and do not give a link.';
			}
		}

		if ( empty( $open ) && empty( $gated ) ) {
			return '';
		}

		$out = "**Groups this person may be pointed to:**\n";
		if ( ! empty( $open ) ) {
			$out .= implode( "\n", $open ) . "\n";
		}
		if ( ! empty( $gated ) ) {
			$out .= "\nMentionable but closed to them:\n" . implode( "\n", $gated ) . "\n";
		}
		$out .= "\nUse these URLs exactly as written. Never invent a group, a URL, or terms of entry.\n";

		return $out;
	}

	/**
	 * Is the person on this turn actually in that group?
	 *
	 * @param int $group_id Group.
	 * @return bool
	 */
	public static function viewer_is_group_member( $group_id ) {
		if ( $group_id <= 0 || ! function_exists( 'groups_is_user_member' ) || ! is_user_logged_in() ) {
			return false;
		}
		return (bool) groups_is_user_member( get_current_user_id(), $group_id );
	}

	/**
	 * Is a BuddyPress/BuddyBoss group directory available on this install?
	 *
	 * Guarded everywhere. FLOSC ships to sites that have never heard of
	 * BuddyBoss, and an unguarded groups_get_groups() is a white screen.
	 *
	 * @return bool
	 */
	public static function groups_available() {
		return function_exists( 'groups_get_groups' ) && function_exists( 'bp_get_group_permalink' );
	}

	/**
	 * This flow's BuddyBoss index policy.
	 *
	 * Stored on the flow, not on the group: which groups a chatbot may mention
	 * is a decision about that conversation, and the same group can be open on
	 * one flow and withheld on another.
	 *
	 * @param string $flow_stem Flow.
	 * @return array
	 */
	public static function buddyboss_policy( $flow_stem = '' ) {
		$defaults = array(
			'enabled'     => false,
			'group_ids'   => array(),
			'vgm_default' => 'visitor guest member',
			'vgm_rows'    => array(),
		);

		$saved = array();
		if ( function_exists( 'flosc_get_setting' ) ) {
			$saved = flosc_get_setting( 'buddyboss_index', array(), $flow_stem !== '' ? $flow_stem : null );
		}
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$policy                = array_merge( $defaults, $saved );
		$policy['enabled']     = ! empty( $policy['enabled'] );
		$policy['group_ids']   = array_values( array_filter( array_map( 'absint', (array) $policy['group_ids'] ) ) );
		$policy['vgm_default'] = strtolower( trim( (string) $policy['vgm_default'] ) );
		$policy['vgm_rows']    = is_array( $policy['vgm_rows'] ) ? $policy['vgm_rows'] : array();

		if ( '' === $policy['vgm_default'] ) {
			$policy['vgm_default'] = 'visitor guest member';
		}

		return $policy;
	}

	/**
	 * One index row per BuddyBoss group in this flow's scope.
	 *
	 * @param string $flow_stem  Flow.
	 * @param array  $prev_posts Previous rows, so exclusions and manual keywords survive.
	 * @return array
	 */
	private function build_group_rows( $flow_stem, array $prev_posts ) {
		$policy = self::buddyboss_policy( $flow_stem );
		if ( empty( $policy['enabled'] ) || ! self::groups_available() ) {
			return array();
		}

		$args = array(
			'per_page'    => 200,
			'show_hidden' => false,
			'type'        => 'alphabetical',
		);
		if ( ! empty( $policy['group_ids'] ) ) {
			$args['include'] = $policy['group_ids'];
		}

		$found = groups_get_groups( $args );
		$list  = isset( $found['groups'] ) && is_array( $found['groups'] ) ? $found['groups'] : array();

		$rows = array();
		foreach ( $list as $group ) {
			if ( empty( $group->id ) ) {
				continue;
			}

			$key  = 'bb_group:' . (int) $group->id;
			$prev = isset( $prev_posts[ $key ] ) && is_array( $prev_posts[ $key ] ) ? $prev_posts[ $key ] : array();

			$name = sanitize_text_field( (string) ( $group->name ?? '' ) );
			$desc = wp_strip_all_tags( (string) ( $group->description ?? '' ) );
			$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );
			$slug = sanitize_title( (string) ( $group->slug ?? '' ) );

			$manual  = isset( $prev['keywords_manual'] ) ? (string) $prev['keywords_manual'] : '';
			$snippet = function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, 160 ) : substr( $desc, 0, 160 );

			$rows[ $key ] = array(
				'id'              => $key,
				'kind'            => 'bb_group',
				'source'          => 'buddyboss',
				'post_id'         => 0,
				'group_id'        => (int) $group->id,
				'bb_status'       => sanitize_key( (string) ( $group->status ?? 'public' ) ),
				'title'           => $name,
				'content'         => $desc,
				'snippet'         => sanitize_text_field( $snippet ),
				'keywords'        => trim( $name . ' ' . $slug . ' ' . $desc . ' ' . $manual ),
				'keywords_manual' => $manual,
				'access'          => self::group_vgm( $policy, (int) $group->id ),
				'excluded'        => ! empty( $prev['excluded'] ),
				'parent'          => '',
				'categories'      => array(),
				'modified'        => sanitize_text_field( (string) ( $group->date_created ?? '' ) ),
				'indexed_at'      => gmdate( 'c' ),
				'url'             => esc_url_raw( (string) bp_get_group_permalink( $group ) ),
				'lesson_number'   => '',
			);
		}

		return $rows;
	}

	/**
	 * Which tiers this flow lets a group be shown to.
	 *
	 * @param array $policy   Flow policy.
	 * @param int   $group_id Group.
	 * @return string Space-separated tiers.
	 */
	/**
	 * Any access value as a clean VGM list.
	 *
	 * "member", "visitor guest member", "visitor,guest" all arrive here and
	 * leave as an ordered list of the levels that are actually meant. Empty
	 * means nobody said anything, which is not the same as "members only".
	 *
	 * @param mixed $raw
	 * @return string[]
	 */
	/**
	 * FLOSC's own plumbing, which is not site content.
	 *
	 * settings.php creates a flosc-internal category and hangs
	 * flosc-internal-concierge and flosc-internal-trajectories under it. Those
	 * posts are how FLOSC stores its own working parts — they are not writing
	 * about the site and chat has no business citing them at any access level.
	 *
	 * The indexer had no idea the convention existed. Every internal post was
	 * indexed as ordinary content, and what kept it out of a visitor's reach was
	 * nothing but the members-only default that used to sit on every un-gated
	 * row. Deriving access properly removed that accident, so the convention has
	 * to be honoured on purpose: internal posts are not indexed at all.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	/**
	 * Categories that never enter the index, matched exactly.
	 *
	 * Two different things, both excluded for the same reason — neither is site
	 * content and neither may come back out of retrieval as content.
	 *
	 * 'internal' is the site owner's own material: the rolodex. No flow, no
	 * personality and no AI provider ever sees it. It has no VGM value, because
	 * VGM does not reach it at all.
	 *
	 * 'trajectory', 'trajectories' and 'concierge' are FLOSC's own plumbing. The
	 * flow personality and the provider DO see these — that is what they are
	 * for — but through their own readers, admin/concierge.php and
	 * includes/flosc-personality-library.php, which take exactly these bare
	 * names alongside the prefixed ones. Indexing them would let the same text
	 * come back to a visitor as retrieved content.
	 *
	 * Exact match, so 'internal-notes', 'international' and 'concierges' are
	 * somebody's own categories and stay in the index.
	 */
	const INTERNAL_CATEGORY_ALIASES = array( 'internal', 'trajectory', 'trajectories', 'concierge' );

	public static function is_internal_post( $post_id ) {
		$terms = get_the_terms( (int) $post_id, 'category' );
		if ( ! is_array( $terms ) ) {
			return false;
		}
		foreach ( $terms as $term ) {
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$slug = (string) $term->slug;
			if ( 'flosc-internal' === $slug || 0 === strpos( $slug, 'flosc-internal-' ) ) {
				return true;
			}
			/*
			 * The bare aliases the readers themselves accept.
			 *
			 * The trajectory reader takes
			 * "flosc-internal-trajectories,trajectory,trajectories" and the
			 * concierge reader takes "flosc-internal-concierge,concierge". A
			 * floscAdmin who filed either under the short name has internal
			 * content in a category with no flosc- prefix, and matching only
			 * the prefix would have left it in the index. What the readers
			 * treat as internal, this treats as internal.
			 */
			if ( in_array( $slug, self::INTERNAL_CATEGORY_ALIASES, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * How much of a post a tier gets, deepest last.
	 *
	 * Access has two axes, not one. VGM says WHO — and the tier is a floor, so
	 * V includes VGM, G excludes V, M excludes VG. Depth says HOW MUCH that
	 * tier gets of the post itself.
	 */
	const DEPTHS = array( 'title', 'excerpt', 'readmore', 'full' );

	/** The tiers, shallowest first. A tier never gets less than the one below. */
	const TIERS = array( 'visitor', 'guest', 'member' );

	/**
	 * A depth token, or '' when it is not one.
	 *
	 * @param mixed $raw
	 * @return string
	 */
	public static function depth_token( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );
		return in_array( $raw, self::DEPTHS, true ) ? $raw : '';
	}

	/**
	 * Position on the depth ladder. -1 for anything that is not a depth.
	 *
	 * @param mixed $raw
	 * @return int
	 */
	public static function depth_rank( $raw ) {
		$idx = array_search( self::depth_token( $raw ), self::DEPTHS, true );
		return false === $idx ? -1 : (int) $idx;
	}

	/**
	 * A tier token, or '' when it is not one.
	 *
	 * @param mixed $raw
	 * @return string
	 */
	public static function tier_token( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );
		return in_array( $raw, self::TIERS, true ) ? $raw : '';
	}

	/**
	 * Every tier at or above $tier. The floor, expressed as a list.
	 *
	 * @param string $tier
	 * @return string[]
	 */
	public static function tiers_from( $tier ) {
		$tier = self::tier_token( $tier );
		if ( '' === $tier ) {
			return array();
		}
		$idx = (int) array_search( $tier, self::TIERS, true );
		return array_slice( self::TIERS, $idx );
	}

	/**
	 * A complete depth map: every tier, one depth each.
	 *
	 * @param string $depth Depth every tier gets.
	 * @return array<string,string>
	 */
	public static function depth_map( $depth ) {
		$depth = self::depth_token( $depth );
		if ( '' === $depth ) {
			$depth = 'title';
		}
		$map = array();
		foreach ( self::TIERS as $tier ) {
			$map[ $tier ] = $depth;
		}
		return $map;
	}

	/**
	 * Normalise a stored map, and enforce the floor.
	 *
	 * A higher tier can never get less than a lower one — that is what "V
	 * includes VGM" means — so each tier is raised to the deepest depth granted
	 * at or below it.
	 *
	 * @param mixed $raw
	 * @return array<string,string>
	 */
	public static function normalize_depth_map( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$map = array();
		$run = 'title';
		foreach ( self::TIERS as $tier ) {
			$depth = self::depth_token( $raw[ $tier ] ?? '' );
			if ( '' === $depth ) {
				$depth = 'title';
			}
			if ( self::depth_rank( $depth ) < self::depth_rank( $run ) ) {
				$depth = $run;
			}
			$run          = $depth;
			$map[ $tier ] = $depth;
		}
		return $map;
	}

	/**
	 * The site-wide default, from flow settings.
	 *
	 * Ships as full body for everybody, because published is public. A
	 * floscAdmin who wants "all titles VGM" or "all excerpts VGM" sets it here
	 * once and every post with no rule of its own follows.
	 *
	 * @param string $flow_stem
	 * @return array<string,string>
	 */
	public static function default_depth_map( $flow_stem = '' ) {
		$saved = null;

		if ( ! empty( $GLOBALS['flosc_current_settings']['content_default_vgm'] ) ) {
			$saved = $GLOBALS['flosc_current_settings']['content_default_vgm'];
		} elseif ( function_exists( 'flosc_get_setting' ) ) {
			$saved = flosc_get_setting( 'content_default_vgm', null, $flow_stem !== '' ? $flow_stem : null );
		}

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return self::depth_map( 'full' );
		}

		return self::normalize_depth_map( $saved );
	}

	/**
	 * Protection rules for this flow, as the Content tab stores them.
	 *
	 * @param string $flow_stem
	 * @return array[]
	 */
	public static function protection_rules( $flow_stem = '' ) {
		$rules = array();

		if ( ! empty( $GLOBALS['flosc_current_settings']['protected_content'] ) ) {
			$rules = $GLOBALS['flosc_current_settings']['protected_content'];
		} elseif ( function_exists( 'flosc_get_setting' ) ) {
			$rules = flosc_get_setting( 'protected_content', array(), $flow_stem !== '' ? $flow_stem : null );
		}

		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Fold one scope's rules into a depth map.
	 *
	 * A rule reads "at this tier and above, you get this depth". Several rules
	 * on one scope are normal — a post can be readmore for visitors and full
	 * for guests — so the deepest grant wins per tier, and tiers no rule
	 * mentions fall to title: the post still exists and may be named, which is
	 * the locked stub behaviour that was already there.
	 *
	 * @param array[] $rules
	 * @return array<string,string>|null Null when no rule in the set applies.
	 */
	public static function fold_rules( array $rules ) {
		$map   = self::depth_map( 'title' );
		$any   = false;

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$tier  = self::tier_token( $rule['vgm'] ?? '' );
			$depth = self::depth_token( $rule['depth'] ?? '' );

			/*
			 * A rule stored before these two columns existed. Every such rule
			 * on dainis.net means what it has always meant: gated, members
			 * only, whole body once cleared. Nothing changes meaning until
			 * somebody edits the row.
			 */
			if ( '' === $tier ) {
				$tier = 'member';
			}
			if ( '' === $depth ) {
				$depth = 'full';
			}

			$any = true;
			foreach ( self::tiers_from( $tier ) as $t ) {
				if ( self::depth_rank( $depth ) > self::depth_rank( $map[ $t ] ) ) {
					$map[ $t ] = $depth;
				}
			}
		}

		return $any ? self::normalize_depth_map( $map ) : null;
	}

	/**
	 * What each tier gets of this post.
	 *
	 * Scopes, most specific first: post, then its tags, then its categories,
	 * then the site default. The most specific scope that carries any rule at
	 * all decides — so a rule on the post can open a post its category closed,
	 * and it can close one its category left open. Merging instead of deciding
	 * would make the second of those impossible.
	 *
	 * @param int    $post_id
	 * @param string $flow_stem
	 * @return array<string,string>
	 */
	public static function resolve_vgm( $post_id, $flow_stem = '' ) {
		$post_id = (int) $post_id;

		$tag_ids = array();
		$terms   = get_the_terms( $post_id, 'post_tag' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term && ! is_wp_error( $term ) ) {
					$tag_ids[] = (int) $term->term_id;
				}
			}
		}

		$cat_ids = array();
		$terms   = get_the_terms( $post_id, 'category' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term && ! is_wp_error( $term ) ) {
					$cat_ids[] = (int) $term->term_id;
				}
			}
		}

		/*
		 * A rule can be written in two places and means the same thing in both:
		 * on the object's own editing screen, or in the table on the Content
		 * tab. The panels write meta because that is where a floscAdmin is when
		 * they think of it; the table writes flow settings because that is the
		 * site overview. Both land here.
		 */
		$by_post = self::meta_rules( 'post', $post_id );
		$by_tag  = array();
		$by_cat  = array();

		foreach ( $tag_ids as $tid ) {
			$by_tag = array_merge( $by_tag, self::meta_rules( 'term', $tid ) );
		}
		foreach ( $cat_ids as $cid ) {
			$by_cat = array_merge( $by_cat, self::meta_rules( 'term', $cid ) );
		}

		foreach ( self::protection_rules( $flow_stem ) as $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['type'], $rule['id'] ) ) {
				continue;
			}
			$type = strtolower( trim( (string) $rule['type'] ) );
			$id   = (int) $rule['id'];

			if ( ( 'post' === $type || 'page' === $type ) && $id === $post_id ) {
				$by_post[] = $rule;
			} elseif ( 'tag' === $type && in_array( $id, $tag_ids, true ) ) {
				$by_tag[] = $rule;
			} elseif ( 'category' === $type && in_array( $id, $cat_ids, true ) ) {
				$by_cat[] = $rule;
			}
		}

		foreach ( array( $by_post, $by_tag, $by_cat ) as $scope ) {
			if ( empty( $scope ) ) {
				continue;
			}
			$map = self::fold_rules( $scope );
			if ( null !== $map ) {
				return $map;
			}
		}

		return self::default_depth_map( $flow_stem );
	}

	/**
	 * Every rule written on an object rather than in the table.
	 *
	 * The Content tab is meant to be the one place a floscAdmin can see what
	 * the site does. A rule set on a category screen is invisible there unless
	 * something goes and looks, so this goes and looks.
	 *
	 * @return array[] Each: kind, id, name, vgm, depth, edit_url.
	 */
	public static function rules_written_on_objects() {
		$out = array();

		$terms = get_terms(
			array(
				'taxonomy'   => array( 'category', 'post_tag' ),
				'hide_empty' => false,
				'meta_key'   => '_flosc_vgm', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin overview, runs once per page load
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$rule = self::meta_rules( 'term', (int) $term->term_id );
				if ( empty( $rule ) ) {
					continue;
				}
				$out[] = array(
					'kind'     => 'category' === $term->taxonomy ? __( 'Category', 'flosc' ) : __( 'Tag', 'flosc' ),
					'id'       => (int) $term->term_id,
					'name'     => (string) $term->name,
					'vgm'      => $rule[0]['vgm'],
					'depth'    => $rule[0]['depth'],
					'edit_url' => get_edit_term_link( (int) $term->term_id, (string) $term->taxonomy ),
				);
			}
		}

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'meta_key'       => '_flosc_vgm', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin overview, runs once per page load
				'no_found_rows'  => true,
			)
		);
		foreach ( (array) $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$rule = self::meta_rules( 'post', (int) $post->ID );
			if ( empty( $rule ) ) {
				continue;
			}
			$out[] = array(
				'kind'     => __( 'Post', 'flosc' ),
				'id'       => (int) $post->ID,
				'name'     => (string) get_the_title( $post ),
				'vgm'      => $rule[0]['vgm'],
				'depth'    => $rule[0]['depth'],
				'edit_url' => get_edit_post_link( (int) $post->ID ),
			);
		}

		return $out;
	}

	/**
	 * A rule written on the object itself, as zero or one rule.
	 *
	 * Both halves must be set for it to count. A post carrying only a tier and
	 * no depth is somebody half-way through a thought, not a rule, and treating
	 * it as one would silently gate the post.
	 *
	 * @param string $kind 'post' or 'term'
	 * @param int    $id
	 * @return array[]
	 */
	public static function meta_rules( $kind, $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return array();
		}

		$tier  = 'term' === $kind ? get_term_meta( $id, '_flosc_vgm', true ) : get_post_meta( $id, '_flosc_vgm', true );
		$depth = 'term' === $kind ? get_term_meta( $id, '_flosc_depth', true ) : get_post_meta( $id, '_flosc_depth', true );

		$tier  = self::tier_token( $tier );
		$depth = self::depth_token( $depth );

		if ( '' === $tier || '' === $depth ) {
			return array();
		}

		return array( array( 'vgm' => $tier, 'depth' => $depth ) );
	}

	public static function vgm_list( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );
		if ( '' === $raw ) {
			return array();
		}
		$parts = preg_split( '/[\s,]+/', $raw ) ?: array();
		/* array_intersect keeps the first array's order, so the list always
		   reads visitor, guest, member however it was typed. */
		return array_values( array_intersect( array( 'visitor', 'guest', 'member' ), $parts ) );
	}

	public static function group_vgm( array $policy, $group_id ) {
		$key = 'bb_group:' . (int) $group_id;
		$raw = isset( $policy['vgm_rows'][ $key ] ) ? $policy['vgm_rows'][ $key ] : $policy['vgm_default'];
		$raw = strtolower( trim( (string) $raw ) );

		$allowed = array_values( array_intersect(
			array( 'visitor', 'guest', 'member' ),
			preg_split( '/[\s,]+/', $raw ) ?: array()
		) );

		return empty( $allowed ) ? '' : implode( ' ', $allowed );
	}

	/**
	 * A library row key: digits for a WordPress post, "kind:id" for anything else.
	 *
	 * @param mixed $raw Candidate id.
	 * @return string Empty when it is not usable as a key.
	 */
	public static function normalize_row_id( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ctype_digit( $raw ) ) {
			return (int) $raw > 0 ? $raw : '';
		}
		// kind:id — both halves restricted, so a key can never carry markup or
		// a path separator into the library file.
		if ( preg_match( '/^([a-z0-9_]+):([a-z0-9_-]+)$/i', $raw, $m ) ) {
			return strtolower( $m[1] ) . ':' . strtolower( $m[2] );
		}
		return '';
	}

	/**
	 * Post types this flow indexes. Always at least 'post'.
	 *
	 * @param string $flow_stem Flow being rebuilt.
	 * @return array
	 */
	public static function indexed_post_types( $flow_stem = '' ) {
		$types = array();

		if ( function_exists( 'flosc_get_setting' ) ) {
			$saved = flosc_get_setting( 'site_index_post_types', array(), $flow_stem !== '' ? $flow_stem : null );
			if ( is_string( $saved ) ) {
				$saved = preg_split( '/[\s,]+/', $saved );
			}
			$types = array_map( 'sanitize_key', (array) $saved );
		}

		// 'post' is not optional: the index has always held posts, and removing
		// them on upgrade would empty a working library without anyone asking.
		$types[] = 'post';

		$types = array_values( array_unique( array_filter( $types ) ) );

		// Only types this site actually registers. A stale saved value for a
		// plugin that has since been deactivated must not break the rebuild.
		$types = array_values( array_filter( $types, static function ( $type ) {
			return post_type_exists( $type );
		} ) );

		if ( empty( $types ) ) {
			$types = array( 'post' );
		}

		return (array) apply_filters( 'flosc_site_content_index_post_types', $types, $flow_stem );
	}

	public function rebuild( $flow_stem = '' ) {
		$previous   = $this->load( $flow_stem );
		$prev_posts = is_array( $previous['posts'] ) ? $previous['posts'] : array();

		// Safety cap: large sites still get a usable library; admin can exclude rows.
		$max_posts = (int) apply_filters( 'flosc_site_content_index_max_posts', 1000 );
		if ( $max_posts < 1 ) {
			$max_posts = 1000;
		}

		/*
		 * Which post types the index reads.
		 *
		 * This was the literal string 'post', which is why the chatbot could not
		 * see the shop: WooCommerce products, pages and bbPress forum topics are
		 * all WP_Post and would have flowed through this indexer untouched — the
		 * query simply never asked for them. Nothing downstream cares about the
		 * type. The row builder reads ID, title, body, permalink and modified
		 * date, and exclusions and manual keywords key on the numeric post id,
		 * so every type behaves the same once it is indexed.
		 *
		 * Default stays 'post' so no existing install changes on upgrade. The
		 * floscAdmin adds types on the Site content index panel.
		 */
		$types = self::indexed_post_types( $flow_stem );

		$posts = get_posts(
			array(
				'post_type'              => $types,
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
			/* FLOSC's own plumbing is not site content. Skipping it here also
			   drops any internal row an earlier build wrote, because $indexed
			   is what gets saved. */
			if ( self::is_internal_post( $post->ID ) ) {
				continue;
			}
			$id_key   = (string) $post->ID;
			$prev     = isset( $prev_posts[ $id_key ] ) && is_array( $prev_posts[ $id_key ] ) ? $prev_posts[ $id_key ] : array();
			$excluded = ! empty( $prev['excluded'] );
			$manual   = isset( $prev['keywords_manual'] ) ? (string) $prev['keywords_manual'] : '';

			$indexed[ $id_key ] = $this->build_row_from_post( $post, $manual, $excluded );
		}

		/*
		 * BuddyBoss groups.
		 *
		 * These are the one thing on a BuddyBoss site that genuinely does not
		 * live in wp_posts — the directory is in the BuddyPress tables, reached
		 * through groups_get_groups(). Forum topics and replies are bbPress
		 * CPTs and come through the post query above once their types are
		 * ticked; only the group directory needs an adapter.
		 *
		 * The directory alone is enough to route somebody to the right group.
		 * Activity, media and forum bodies are a later slice and are not
		 * indexed here.
		 */
		$indexed = $indexed + $this->build_group_rows( $flow_stem, $prev_posts );

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
		/*
		 * Shortcodes out before tags out. wp_strip_all_tags() removes HTML and
		 * leaves shortcodes whole, so a page built with Divi indexed as its
		 * builder markup — [et_pb_section fb_built="1" _builder_version="4.17.4"
		 * …] — and that markup ate the character budget from the top of the post
		 * downward. Text at the bottom of a long builder page never reached the
		 * index at all, which is why a translation added at the end of a post
		 * could not be found by chat.
		 */
		$raw_body = (string) $post->post_content;

		/*
		 * The read-more split happens before cleaning, because cleaning removes
		 * the marker along with every other tag. What is stored is the LENGTH of
		 * the cleaned teaser, so a readmore slice is a substr() of the same body
		 * string the full depth returns — no second copy, and no need to reach
		 * for the live post at retrieval time.
		 */
		$teaser_raw = $raw_body;
		if ( preg_match( '/<!--\s*more(.*?)?-->/', $raw_body, $more_m, PREG_OFFSET_CAPTURE ) ) {
			$teaser_raw = substr( $raw_body, 0, (int) $more_m[0][1] );
		}

		$body = strip_shortcodes( $raw_body );
		/*
		 * strip_shortcodes() only knows shortcodes that are REGISTERED, and a page
		 * builder registers its own only when the builder loads. A rebuild runs in
		 * admin without it, so every [et_pb_*] survived the call above and the
		 * index still filled with builder attributes. This second pass matches the
		 * shape rather than the registry, and it is not fussy about the shape:
		 * nothing between square brackets belongs in a search corpus, whoever
		 * wrote it and whether or not anything has declared it. Safe here and only
		 * here — this text is stored for lookup and is never rendered.
		 */
		$body = preg_replace( '/\[[^\]]*\]/', ' ', (string) $body );
		$body = wp_strip_all_tags( (string) $body );
		$body = preg_replace( '/\s+/u', ' ', $body );
		$body = is_string( $body ) ? trim( $body ) : '';
		if ( strlen( $body ) > self::MAX_BODY_CHARS ) {
			$body = substr( $body, 0, self::MAX_BODY_CHARS );
		}

		$teaser = strip_shortcodes( $teaser_raw );
		$teaser = preg_replace( '/\[[^\]]*\]/', ' ', (string) $teaser );
		$teaser = wp_strip_all_tags( (string) $teaser );
		$teaser = preg_replace( '/\s+/u', ' ', $teaser );
		$teaser = is_string( $teaser ) ? trim( $teaser ) : '';
		$more_offset = min( strlen( $teaser ), strlen( $body ) );

		$auto_kw = $this->derive_keywords( $post, $body );
		$manual  = sanitize_text_field( (string) $keywords_manual );
		$merged  = $manual !== '' ? $this->merge_keywords( $auto_kw, $manual ) : $auto_kw;

		/*
		 * Access is derived, never defaulted.
		 *
		 * It defaulted to 'member', so every ordinary post on the site — carrying
		 * no FLOSC meta because nobody had ever asked for it to be gated — was
		 * indexed members-only and skipped outright by a visitor-level search.
		 * Ask a public chat about a public post and it said it had no
		 * information, which was false: the row was there, keywords and all,
		 * behind a gate nobody set.
		 *
		 * In order: what the floscAdmin said about this post, then what the
		 * protection class says — it is the thing that actually gates
		 * the_content, so the index and the page can never disagree — then what
		 * WordPress itself says. Published is public.
		 */
		$access = implode( ' ', self::vgm_list( get_post_meta( $post->ID, '_flosc_access_level', true ) ) );

		if ( '' === $access ) {
			$access = implode( ' ', self::vgm_list( get_post_meta( $post->ID, '_flosc_content_subcategory', true ) ) );
		}

		if ( '' === $access && 'full' === get_post_meta( $post->ID, '_flosc_protection_mode', true ) ) {
			/* The floscAdmin marked it public outright. */
			$access = 'visitor';
		}

		if ( '' === $access && class_exists( 'FLOSC_Content_Protection' ) ) {
			$protection = FLOSC_Content_Protection::instance()->check_post_protection( (int) $post->ID );
			if ( ! empty( $protection['protected'] ) ) {
				$access = implode( ' ', self::vgm_list( $protection['required_level'] ) );
				if ( '' === $access ) {
					$access = 'member';
				}
			}
		}

		if ( '' === $access ) {
			$private = ( 'publish' !== $post->post_status ) || ( '' !== (string) $post->post_password );
			$access  = $private ? 'member' : 'visitor';
		}

		/*
		 * Depth, the second axis.
		 *
		 * resolve_vgm() answers from the rule table — post, then tag, then
		 * category, then the site default. The derived tier above then CLAMPS
		 * it: anything below that tier drops to title, so a rule can never hand
		 * out a body the page itself would refuse to render. A floscAdmin who
		 * wants a gated post opened does it the way that already exists, with
		 * the post's own visibility override, which lands on 'visitor' here and
		 * clamps nothing.
		 */
		$vgm   = self::resolve_vgm( (int) $post->ID );
		$floor = self::vgm_list( $access );
		$floor = empty( $floor ) ? 'visitor' : $floor[0];
		$open  = self::tiers_from( $floor );
		foreach ( self::TIERS as $tier ) {
			if ( ! in_array( $tier, $open, true ) ) {
				$vgm[ $tier ] = 'title';
			}
		}
		$vgm = self::normalize_depth_map( $vgm );

		$excerpt = sanitize_text_field( (string) $post->post_excerpt );

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
			// Every row now carries an id and a kind. For a WordPress post the
			// id is the post id as a string, so nothing that keyed on post_id
			// changes meaning.
			'id'               => (string) (int) $post->ID,
			'kind'             => 'post',
			'source'           => 'wordpress',
			'post_type'        => sanitize_key( (string) $post->post_type ),
			'post_id'          => (int) $post->ID,
			'title'            => sanitize_text_field( get_the_title( $post ) ),
			'content'          => $body,
			'snippet'          => sanitize_text_field( $snippet ),
			'keywords'         => $merged,
			'keywords_manual'  => $manual,
			'access'           => $access,
			// What each tier gets: title | excerpt | readmore | full.
			'vgm'              => $vgm,
			'excerpt'          => $excerpt,
			'more_offset'      => (int) $more_offset,
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
			/*
			 * No sanitize_key() on the way in.
			 *
			 * access is a VGM list, and sanitize_key() strips the space:
			 * "guest member" arrived as "guestmember", vgm_list() found no level
			 * in it, and access_allows() reads an empty list as nobody having
			 * gated the row — so a row restricted to guests and members was
			 * handed to a logged-out visitor, body and all. vgm_list() is the
			 * sanitizer: it lowercases, trims, splits and whitelists.
			 */
			$req   = (string) ( $row['access'] ?? 'visitor' );
			$depth = $this->row_depth( $row, $access_level );
			$lock  = ( 'title' === $depth ) ? ' [locked]' : '';
			$lines[] = sprintf(
				'- #%d %s%s (access: %s, available: %s)',
				(int) ( $row['post_id'] ?? 0 ),
				(string) ( $row['title'] ?? '' ),
				$lock,
				$req,
				$depth
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
			/*
			 * A post the visitor may not READ is still a post that EXISTS.
			 *
			 * This skipped the whole row, so a member-only post was invisible
			 * to search entirely — its title, its keywords, all of it. Ask a
			 * visitor-level chat about a member-only piece and the honest
			 * answer available to it was "I have no information", which is
			 * false: the site knows the piece perfectly well.
			 *
			 * The row is scored and returned now, with its title, URL and
			 * keywords and WITHOUT its body, marked locked. The model can say
			 * the piece exists and point at it; it cannot quote what is behind
			 * the gate. That is what the comment here always said it wanted:
			 * "list locked title only".
			 */
			/*
			 * No sanitize_key() on the way in.
			 *
			 * access is a VGM list, and sanitize_key() strips the space:
			 * "guest member" arrived as "guestmember", vgm_list() found no level
			 * in it, and access_allows() reads an empty list as nobody having
			 * gated the row — so a row restricted to guests and members was
			 * handed to a logged-out visitor, body and all. vgm_list() is the
			 * sanitizer: it lowercases, trims, splits and whitelists.
			 */
			$depth  = $this->row_depth( $row, $access_level );
			$slice  = $this->row_body_at( $row, $depth );
			$locked = ( 'title' === $depth );
			/*
			 * A row is searched at the depth it is returned at, never deeper.
			 * At title depth the body is not searched and not returned; at
			 * excerpt or readmore only that slice is. Otherwise a keyword that
			 * appears solely past the gate would pull up a row the reader then
			 * cannot see the reason for.
			 */
			$hay_src = (string) ( $row['title'] ?? '' ) . ' ' . (string) ( $row['keywords'] ?? '' )
				. ( '' !== $slice ? ' ' . $slice : '' );
			$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay_src ) : strtolower( $hay_src );

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
				$scored[] = array(
					'score'  => $score,
					'row'    => $row,
					'locked' => $locked,
					'depth'  => $depth,
					'slice'  => $slice,
				);
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
			$out .= 'ID: ' . (string) ( $row['id'] ?? (int) ( $row['post_id'] ?? 0 ) );
			if ( ! empty( $row['url'] ) ) {
				$out .= ' | URL: ' . (string) $row['url'];
			}
			$out .= "\n";
			if ( ! empty( $row['keywords'] ) ) {
				$out .= 'Keywords: ' . (string) $row['keywords'] . "\n";
			}
			if ( ! empty( $hit['locked'] ) ) {
				$out .= "Access: title only. This piece exists and may be named and linked. Its content is not available at this access level, so do not quote or summarise it — say it is there and point to it.\n";
				$out .= "\n---\n\n";
				continue;
			}

			$depth = (string) ( $hit['depth'] ?? 'full' );
			if ( 'full' !== $depth ) {
				/*
				 * Say which slice this is, so the model does not present a
				 * teaser as the whole piece. It may quote what it was given and
				 * point at the post for the rest.
				 */
				$out .= 'excerpt' === $depth
					? "Access: excerpt only. Quote this much and point to the post for the rest.\n"
					: "Access: opening section only, up to the read-more break. Quote this much and point to the post for the rest.\n";
			}

			$out .= "\n" . (string) ( $hit['slice'] ?? ( $row['content'] ?? '' ) ) . "\n\n---\n\n";
		}
		return $out;
	}

	/**
	 * What this tier gets of this row.
	 *
	 * A row built before depth existed carries only its access string. That
	 * still answers the question, at the two depths it could express: whole
	 * body for a tier that clears it, title for one that does not. So an index
	 * file written by an older build keeps working until it is rebuilt.
	 *
	 * @param array  $row
	 * @param string $tier visitor|guest|member
	 * @return string title|excerpt|readmore|full
	 */
	public function row_depth( array $row, $tier ) {
		$tier = self::tier_token( $tier );
		if ( '' === $tier ) {
			$tier = 'visitor';
		}

		if ( ! empty( $row['vgm'] ) && is_array( $row['vgm'] ) ) {
			$map = self::normalize_depth_map( $row['vgm'] );
			return $map[ $tier ];
		}

		return $this->access_allows( $tier, (string) ( $row['access'] ?? 'visitor' ) ) ? 'full' : 'title';
	}

	/**
	 * The slice of body this depth returns. Empty string at title depth.
	 *
	 * @param array  $row
	 * @param string $depth
	 * @return string
	 */
	public function row_body_at( array $row, $depth ) {
		$body = (string) ( $row['content'] ?? '' );

		switch ( self::depth_token( $depth ) ) {
			case 'full':
				return $body;

			case 'readmore':
				$offset = isset( $row['more_offset'] ) ? (int) $row['more_offset'] : 0;
				if ( $offset <= 0 || $offset >= strlen( $body ) ) {
					/* No read-more break in this post: the teaser IS the post. */
					return $body;
				}
				return rtrim( substr( $body, 0, $offset ) );

			case 'excerpt':
				$excerpt = trim( (string) ( $row['excerpt'] ?? '' ) );
				if ( '' !== $excerpt ) {
					return $excerpt;
				}
				/* No hand-written excerpt: the snippet the row already carries. */
				return trim( (string) ( $row['snippet'] ?? '' ) );
		}

		return '';
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

		/*
		 * access is a VGM list, not one word.
		 *
		 * This read a single token: $hierarchy[ sanitize_key( $required ) ] ?? 3.
		 * group_vgm() has always produced lists like "visitor guest member", and
		 * load() has always split access on whitespace — so a real list arrived
		 * here, came out of sanitize_key() as "visitorguestmember", missed the
		 * hierarchy, and fell to the ?? 3 default. Members only. Silently, for
		 * every row that carried a list.
		 *
		 * Nobody having said anything is not the same as members only either:
		 * an empty value means published, and published is public.
		 */
		$levels = self::vgm_list( $required );
		if ( empty( $levels ) ) {
			return true;
		}
		foreach ( $levels as $level ) {
			if ( $u >= $hierarchy[ $level ] ) {
				return true;
			}
		}
		return false;
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
		$key = self::normalize_row_id( $post_id );
		if ( '' === $key ) {
			return false;
		}
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
		$key = self::normalize_row_id( $post_id );
		if ( '' === $key ) {
			return false;
		}
		if ( ! isset( $doc['posts'][ $key ] ) ) {
			return false;
		}
		$manual = sanitize_text_field( (string) $manual_keywords );
		$doc['posts'][ $key ]['keywords_manual'] = $manual;
		$title   = (string) ( $doc['posts'][ $key ]['title'] ?? '' );
		$content = (string) ( $doc['posts'][ $key ]['content'] ?? '' );
		// Re-derive light auto keywords from title + body, then fold in manual overrides.
		/* Saving a keyword rebuilt the row's searchable field from the first 24
		   words of the body, throwing away everything derive_keywords() had
		   built from the whole post. Adding one word quietly shrank the row. */
		$auto = $this->merge_keywords( $title, (string) ( $doc['posts'][ $key ]['keywords'] ?? '' ) );
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
		/* Reindexing an internal post removes it rather than refreshing it. */
		if ( self::is_internal_post( $post->ID ) ) {
			unset( $doc['posts'][ $key ] );
			return $this->save( $flow_stem, $doc );
		}
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
