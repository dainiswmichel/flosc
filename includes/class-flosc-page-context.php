<?php
/**
 * On-demand page/post content for AI chat context.
 * Injects access-filtered post body only when a message needs it - never on navigation.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page context.
 */
class FLOSC_Page_Context {

	/**
	 * Instance.
	 *
	 * @var mixed
	 */
	private static $instance = null;

	const MAX_CONTENT_CHARS = 12000;
	const SESSION_TTL       = HOUR_IN_SECONDS;

	/**
	 * Instance.
	 *
	 * @return mixed
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Resolve and persist browsing post ID for chat logs — never loads post body.
	 *
	 * @param array $eval_context By reference.
	 */
	public function normalize_browsing_post_id( array &$eval_context ) {
		if ( ! $this->is_page_context_handoff_enabled() ) {
			return;
		}

		/*
		 * A BuddyBoss group page is not a WordPress post, so resolving a post
		 * id for a site's own BuddyBoss group URL returns nothing and
		 * the companion arrives on that page knowing nothing about it.
		 *
		 * Record the group instead. The chatpack's group catalogue already
		 * carries the row; this just says which one the person is looking at.
		 */
		if ( function_exists( 'bp_is_group' ) && function_exists( 'bp_get_current_group_id' ) && bp_is_group() ) {
			$flosc_group_id = (int) bp_get_current_group_id();
			if ( $flosc_group_id > 0 ) {
				$eval_context['browsing_row_id'] = 'bb_group:' . $flosc_group_id;
				if ( empty( $eval_context['browsing_page_title'] ) && function_exists( 'bp_get_current_group_name' ) ) {
					$eval_context['browsing_page_title'] = sanitize_text_field( (string) bp_get_current_group_name() );
				}
				return;
			}
		}

		$current_id = $this->resolve_current_browsing_post_id( $eval_context );
		if ( $current_id <= 0 ) {
			return;
		}

		$eval_context['browsing_page_post_id'] = $current_id;

		$post = get_post( $current_id );
		if ( $post ) {
			if ( empty( $eval_context['browsing_page_title'] ) ) {
				$eval_context['browsing_page_title'] = sanitize_text_field( (string) get_the_title( $post ) );
			}
			if ( empty( $eval_context['browsing_page_url'] ) ) {
				$permalink = get_permalink( $post );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					$eval_context['browsing_page_url'] = esc_url_raw( $permalink );
				}
			}
		}
	}

	/**
	 * Find a link when the current post or one accessible index entry is named.
	 *
	 * @param string $message      Visitor message.
	 * @param array  $eval_context Chat context.
	 * @param string $flow_id      Flow stem, for legacy per-flow indexes.
	 * @return string Page link reply, or an empty string when no clear match exists.
	 */
	public function find_named_page_reply( $message, array $eval_context, $flow_id ) {
		$post = $this->find_named_page( $message, $eval_context, $flow_id, true );
		if ( ! $post ) {
			return '';
		}
		$url = esc_url_raw( (string) get_permalink( $post ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		return sprintf(
			/* translators: 1: WordPress post title, 2: link to the post. */
			__( "You can find %1\$s here:\n%2\$s", 'flosc' ),
			$this->post_title( $post ),
			$url
		);
	}

	/**
	 * Resolve a unique post or prefer the named post the reader is already on.
	 *
	 * @param string $message      Reader message.
	 * @param array  $eval_context Chat context.
	 * @param string $flow_id      Active flow.
	 * @param bool   $link_only    Require a directly viewable destination.
	 * @return WP_Post|null
	 */
	private function find_named_page( $message, array $eval_context, $flow_id, $link_only ) {
		if ( ! $this->is_page_context_handoff_enabled() ) {
			return null;
		}

		$query = $this->normalize_page_name( $message );
		if ( false === strpos( $query, '-' ) ) {
			return null;
		}

		$document   = FLOSC_Site_Content_Index::instance()->load( $flow_id );
		$rows       = $document['posts'];
		$current_id = absint( $eval_context['browsing_page_post_id'] ?? 0 );

		// A title-only index row already exposes its title and URL. WordPress
		// and FLOSC still decide whether the destination can be linked directly.
		if ( $current_id && empty( $rows[ $current_id ]['excluded'] ) ) {
			$current = $this->get_named_post( $current_id, $query, $link_only );
			if ( $current ) {
				return $current;
			}
		}

		$match = null;
		foreach ( $rows as $row ) {
			$post_id = absint( $row['post_id'] ?? 0 );
			if ( ! $post_id || $current_id === $post_id || ! empty( $row['excluded'] ) ) {
				continue;
			}

			$path = (string) wp_parse_url( (string) ( $row['url'] ?? '' ), PHP_URL_PATH );
			if ( ! $this->query_names_page( $query, (string) ( $row['title'] ?? '' ), wp_basename( untrailingslashit( $path ) ) ) ) {
				continue;
			}

			$candidate = $this->get_named_post( $post_id, $query, $link_only );
			if ( ! $candidate ) {
				continue;
			}
			if ( $match ) {
				return null;
			}
			$match = $candidate;
		}

		return $match;
	}

	/**
	 * Revalidate an index or browsing match against the live WordPress post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $query     Normalized message.
	 * @param bool   $link_only Require a directly viewable destination.
	 * @return WP_Post|null
	 */
	private function get_named_post( $post_id, $query, $link_only ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || ! is_post_publicly_viewable( $post ) || FLOSC_Site_Content_Index::is_internal_post( $post_id ) ) {
			return null;
		}
		$title = $this->post_title( $post );
		if ( '' === $title || ! $this->query_names_page( $query, $title, $post->post_name ) ) {
			return null;
		}
		if ( $link_only && ( '' !== $post->post_password || ! FLOSC_Content_Protection::instance()->user_can_access( $post_id ) ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Get the plain title for a named resource.
	 *
	 * @param WP_Post $post Published resource.
	 * @return string
	 */
	private function post_title( WP_Post $post ) {
		return sanitize_text_field( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Compare whole titles or slugs instead of partial keyword hits.
	 *
	 * @param string $query Normalized message.
	 * @param string $title WordPress post title.
	 * @param string $slug  WordPress post slug.
	 * @return bool
	 */
	private function query_names_page( $query, $title, $slug ) {
		foreach ( array( $title, $slug ) as $name ) {
			$name = $this->normalize_page_name( $name );
			if ( false !== strpos( $name, '-' ) && false !== strpos( '-' . $query . '-', '-' . $name . '-' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize text with the same conventions WordPress uses for slugs.
	 *
	 * @param string $text Message, title, or slug.
	 * @return string
	 */
	private function normalize_page_name( $text ) {
		return sanitize_title( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Attach post content to eval_context when the user message warrants it.
	 *
	 * @param array  $eval_context By reference.
	 * @param string $message      User message.
	 * @param string $session_key  Visitor session id or server session id string.
	 */
	public function maybe_attach_to_context( array &$eval_context, $message, $session_key = '' ) {
		if ( ! $this->is_page_context_handoff_enabled() ) {
			return;
		}

		$message = trim( (string) $message );
		if ( '' === $message ) {
			return;
		}

		$this->normalize_browsing_post_id( $eval_context );

		// Location-only questions need title/URL/post ID (already in chatpack) — not full body.
		if ( $this->message_is_page_location_query( $message ) ) {
			return;
		}

		$flow_id          = (string) ( $eval_context['flow_id'] ?? '' );
		$content_question = $this->message_requests_page_content( $message );
		$post             = $content_question ? $this->find_named_page( $message, $eval_context, $flow_id, false ) : null;
		if ( ! $post ) {
			$post_id = $this->resolve_post_id_for_content_injection( $eval_context, $message, $session_key );
			$post    = $post_id > 0 ? get_post( $post_id ) : null;
		}
		if ( ! $post ) {
			return;
		}

		$access_level = (string) ( $eval_context['access_level'] ?? 'visitor' );
		$index        = FLOSC_Site_Content_Index::instance()->load( $flow_id );
		if ( ! empty( $index['posts'][ $post->ID ]['excluded'] ) ) {
			return;
		}
		$depth = $this->post_access_depth( $post, $access_level, $flow_id );
		if ( '' === $depth ) {
			return;
		}
		$source  = $this->post_source( $post, $depth, $access_level );
		$content = $this->render_post_text( $post, $source, $depth, $access_level );

		$eval_context['content_access_depth'] = $depth;
		$eval_context['content_limited']      = $this->content_is_limited( $post, $depth, $access_level );
		$eval_context['content_page_title']   = $this->post_title( $post );
		$eval_context['content_page_url']     = esc_url_raw( (string) get_permalink( $post ) );
		$eval_context['content_question']     = $content_question || $eval_context['content_limited'];
		if ( '' !== $content ) {
			$eval_context['browsing_page_content'] = $content;
			$eval_context['page_content_injected'] = true;
		}

		if ( '' !== $session_key && absint( $eval_context['browsing_page_post_id'] ?? 0 ) === $post->ID ) {
			set_transient( $this->session_transient_key( $session_key ), $post->ID, self::SESSION_TTL );
		}
	}

	/**
	 * Distinguish a question about the contents from a directory lookup.
	 *
	 * @param string $message Reader message.
	 * @return bool
	 */
	private function message_requests_page_content( $message ) {
		return 1 === preg_match( '/\b(?:summari[sz]e|describe|tell me about|what(?: is| are|\'s)? (?:on|in|listed|shown|displayed)|(?:what|which)\b[^?.!]{1,80}\b(?:listed|shown|displayed|on this page))\b/i', $message );
	}

	/**
	 * Session transient key.
	 *
	 * @param mixed $session_key Session key.
	 * @return mixed
	 */
	private function session_transient_key( $session_key ) {
		return 'flosc_page_focus_' . md5( sanitize_text_field( (string) $session_key ) );
	}

	/**
	 * Get session focus post ID.
	 *
	 * @param mixed $session_key Session key.
	 * @return mixed
	 */
	private function get_session_focus_post_id( $session_key ) {
		if ( '' === $session_key ) {
			return 0;
		}
		return absint( get_transient( $this->session_transient_key( $session_key ) ) );
	}

	/**
	 * Is page context handoff enabled.
	 *
	 * @return mixed
	 */
	private function is_page_context_handoff_enabled() {
		$pass_page_context = flosc_get_setting( 'companion_pass_page_context', '1' );
		return filter_var( $pass_page_context, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Resolve current browsing post ID.
	 *
	 * @param array $eval_context Eval context.
	 * @return mixed
	 */
	private function resolve_current_browsing_post_id( array $eval_context ) {
		// Companion already hands off the post ID — trust it when valid.
		$explicit_id = absint( $eval_context['browsing_page_post_id'] ?? 0 );
		if ( $explicit_id > 0 ) {
			$resolved = $this->resolve_post_id( $explicit_id );
			if ( $resolved > 0 ) {
				return $resolved;
			}
		}

		$page_url = esc_url_raw( (string) ( $eval_context['browsing_page_url'] ?? '' ) );
		if ( '' !== $page_url ) {
			return $this->resolve_post_id_from_url( $page_url );
		}

		return 0;
	}

	/**
	 * Resolve post ID for content injection.
	 *
	 * @param array $eval_context Eval context.
	 * @param mixed $message      Message.
	 * @param mixed $session_key  Session key.
	 * @return mixed
	 */
	private function resolve_post_id_for_content_injection( array $eval_context, $message, $session_key ) {
		$current_id = absint( $eval_context['browsing_page_post_id'] ?? 0 );
		if ( $current_id <= 0 ) {
			return 0;
		}

		$title         = sanitize_text_field( (string) ( $eval_context['browsing_page_title'] ?? '' ) );
		$session_focus = $this->get_session_focus_post_id( $session_key );

		// Follow-up on same focused page (reuse cached focus, no re-ingest every turn).
		if ( $session_focus > 0 && $current_id === $session_focus && $this->message_is_short_followup( $message ) ) {
			return $session_focus;
		}

		// On-demand body load: only when the user message targets this page/post/product.
		if ( $this->message_targets_current_page( $message, $title ) ) {
			return $current_id;
		}

		return 0;
	}

	/**
	 * Resolve post ID.
	 *
	 * @param mixed $explicit_id Explicit ID.
	 * @return mixed
	 */
	private function resolve_post_id( $explicit_id ) {
		if ( $explicit_id > 0 ) {
			$post = get_post( $explicit_id );
			if ( $post && 'publish' === $post->post_status ) {
				return (int) $post->ID;
			}
		}

		return 0;
	}

	/**
	 * Resolve post ID from URL.
	 *
	 * @param mixed $url URL.
	 * @return mixed
	 */
	private function resolve_post_id_from_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return 0;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		if ( $site_host && $url_host && strtolower( (string) $site_host ) !== strtolower( (string) $url_host ) ) {
			return 0;
		}

		$candidates = array( $url );
		$parsed     = wp_parse_url( $url );
		if ( ! empty( $parsed['path'] ) ) {
			$path_only    = home_url( $parsed['path'] );
			$candidates[] = $path_only;
			$candidates[] = trailingslashit( $path_only );
			$candidates[] = untrailingslashit( $path_only );
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$resolved = $this->resolve_post_id( absint( url_to_postid( $candidate ) ) );
			if ( $resolved > 0 ) {
				return $resolved;
			}
		}

		return 0;
	}

	/**
	 * Message is page location query.
	 *
	 * @param mixed $message Message.
	 * @return mixed
	 */
	private function message_is_page_location_query( $message ) {
		$msg = strtolower( trim( (string) $message ) );
		if ( '' === $msg ) {
			return false;
		}

		return (bool) preg_match(
			'/\b(what page|which page|where am i|page am i on|page i\'m on|page i am on|current page|am i on)\b/',
			$msg
		);
	}

	/**
	 * Message targets current page.
	 *
	 * @param mixed $message Message.
	 * @param mixed $title   Title.
	 * @return mixed
	 */
	private function message_targets_current_page( $message, $title ) {
		$msg = strtolower( trim( $message ) );

		foreach ( $this->get_page_intent_phrases() as $phrase ) {
			if ( '' !== $phrase && false !== strpos( $msg, $phrase ) ) {
				return true;
			}
		}

		if ( '' !== $title && strlen( $title ) > 4 ) {
			$title_clean = strtolower( preg_replace( '/[^a-z0-9\s]/', ' ', $title ) );
			$words       = array_filter(
				explode( ' ', $title_clean ),
				function ( $w ) {
					return strlen( $w ) > 3;
				}
			);
			$hits        = 0;
			foreach ( $words as $word ) {
				if ( false !== strpos( $msg, $word ) ) {
					++$hits;
				}
			}
			if ( $hits >= 2 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Page-intent phrases the AI treats as "the user is asking about the current page,"
	 * which triggers on-demand page-content injection. Ships with the FLOSC defaults;
	 * floscAdmins append their own via the per-flow "Page Intent Phrases" setting (one per
	 * line or comma-separated), and extensions can adjust the final list via the filter.
	 * Matching is lowercase substring.
	 *
	 * @return string[]
	 */
	private function get_page_intent_phrases() {
		$defaults = array(
			'this page',
			'this post',
			'this product',
			'this course',
			'this song',
			'this article',
			'this item',
			'this listing',
			'this lesson',
			'more about this',
			'about this',
			'about the song',
			'about the page',
			'about the post',
			'about the product',
			'about the article',
			'about the lesson',
			'about the course',
			'about the item',
			'what i\'m looking at',
			'what i am looking at',
			'on this page',
			'tell me about this',
			'what is this',
			'explain this',
			'describe this',
			'summarize this',
			'summary of this',
			'read this',
			'page content',
			'product description',
			'post content',
			'looking at this',
			'what\'s this',
			'whats this',
			'what is this page',
			'what\'s this page',
			'what is this about',
			'what\'s this about',
			'what\'s on this page',
			'what is this product',
			'what\'s this product',
			'what is this lesson',
			'what\'s this lesson',
			'tell me about this page',
			'tell me about this product',
			'tell me about this lesson',
		);

		// floscAdmin additions — per-flow setting, one phrase per line (or comma-separated).
		$custom = array();
		$raw    = (string) flosc_get_setting( 'companion_page_intent_phrases', '' );
		if ( '' !== $raw ) {
			foreach ( preg_split( '/[\r\n,]+/', $raw ) as $line ) {
				$line = strtolower( trim( (string) $line ) );
				if ( '' !== $line ) {
					$custom[] = $line;
				}
			}
		}

		$phrases = array_values( array_unique( array_merge( $defaults, $custom ) ) );

		/**
		 * Filter the page-intent phrase list (shipped defaults + admin additions).
		 *
		 * @param string[] $phrases Lowercase substrings that mark a page-content question.
		 */
		$phrases = apply_filters( 'flosc_companion_page_intent_phrases', $phrases );

		return is_array( $phrases ) ? $phrases : $defaults;
	}

	/**
	 * Message is short followup.
	 *
	 * @param mixed $message Message.
	 * @return mixed
	 */
	private function message_is_short_followup( $message ) {
		$msg = strtolower( trim( $message ) );

		if ( '' === $msg ) {
			return false;
		}

		// Language/meta conversation choices are not page-content follow-ups.
		if ( preg_match( '/\b(english|latvian|german|spanish|french|language|prefer|servus|sveiks)\b/i', $msg ) ) {
			return false;
		}

		// Explicit continuation confirmations.
		if ( in_array( $msg, array( 'yes', 'yeah', 'yep', 'sure', 'please', 'ok', 'okay' ), true ) ) {
			return true;
		}

		// Pronoun-based continuation of the current page/topic.
		if ( preg_match( '/\b(it|its|this|that|these|those|they|them|there)\b/', $msg ) ) {
			return true;
		}

		// Short question-style follow-ups are likely same-thread continuations.
		if (
			strlen( $msg ) <= 120
			&& preg_match( '/^(what|how|why|where|when|which|who|can|could|would|should|do|does|did|is|are|was|were)\b/', $msg )
			&& ! preg_match( '/\b(testimonial|testimonials|review|reviews|another page|different page|new page|other page)\b/', $msg )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve the most content this request can retrieve from a live post.
	 *
	 * @param WP_Post $post         Published post or page.
	 * @param string  $access_level Server-resolved access level.
	 * @param string  $flow_id      Active flow.
	 * @return string Depth, or an empty string when the post itself is unavailable.
	 */
	public function post_access_depth( WP_Post $post, $access_level, $flow_id = '' ) {
		if ( 'publish' !== $post->post_status || ! is_post_publicly_viewable( $post ) || FLOSC_Site_Content_Index::is_internal_post( $post->ID ) ) {
			return '';
		}
		if ( post_password_required( $post ) ) {
			return 'title';
		}

		$index        = FLOSC_Site_Content_Index::instance();
		$access_level = FLOSC_Site_Content_Index::tier_token( $access_level );
		$access_level = '' === $access_level ? 'visitor' : $access_level;
		$required     = (string) get_post_meta( $post->ID, '_flosc_access_level', true );
		if ( '' === $required ) {
			$required = (string) get_post_meta( $post->ID, '_flosc_content_subcategory', true );
		}
		if ( ! $index->access_allows( $access_level, $required ) ) {
			return 'title';
		}

		$levels     = FLOSC_Site_Content_Index::resolve_vgm( $post->ID, $flow_id );
		$depth      = $levels[ $access_level ];
		$protection = FLOSC_Content_Protection::instance();
		if ( 'title' === $depth || $protection->user_can_access( $post->ID ) ) {
			return $depth;
		}

		$visibility = $protection->get_post_visibility( $post->ID );
		$preview    = array(
			'hidden'  => 'title',
			'teaser'  => 'excerpt',
			'preview' => 'readmore',
		);
		$cap        = $preview[ $visibility ] ?? 'title';
		return FLOSC_Site_Content_Index::depth_rank( $cap ) < FLOSC_Site_Content_Index::depth_rank( $depth ) ? $cap : $depth;
	}

	/**
	 * Report a restricted body even if a post-level tier permits its public portion.
	 *
	 * @param WP_Post $post         WordPress post.
	 * @param string  $depth        Effective retrieval depth.
	 * @param string  $access_level Server-resolved access level.
	 * @return bool
	 */
	public function content_is_limited( WP_Post $post, $depth, $access_level ) {
		return 'full' !== $depth || ( 'member' !== $access_level && false !== strpos( $post->post_content, '<!--flosc_read_more' ) );
	}

	/**
	 * Get the permitted source before running block or shortcode callbacks.
	 *
	 * @param WP_Post $post         WordPress post.
	 * @param string  $depth        Effective retrieval depth.
	 * @param string  $access_level Server-resolved access level.
	 * @return string
	 */
	public function post_source( WP_Post $post, $depth, $access_level ) {
		if ( ! in_array( $depth, array( 'excerpt', 'readmore', 'full' ), true ) ) {
			return '';
		}
		$filter = FLOSC_Content_Filter::instance();
		$source = $filter->filter_post_content( $post->post_content, $access_level, false );
		if ( 'excerpt' === $depth ) {
			$excerpt = $filter->filter_post_content( $post->post_excerpt, $access_level, false );
			return '' !== trim( $excerpt ) ? $excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $source ) ) );
		}
		if ( 'readmore' === $depth ) {
			$parts  = preg_split( '/<!--\s*more\b[^>]*-->/', $source, 2 );
			$source = $parts[0];
			if ( 1 === count( $parts ) && 'preview' === FLOSC_Content_Protection::instance()->get_post_visibility( $post->ID ) ) {
				return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $source ) ) );
			}
		}
		return $source;
	}

	/**
	 * Render a permitted source with the request's WordPress user and cookies.
	 *
	 * @param WP_Post $post         WordPress post.
	 * @param string  $source       Permitted source from post_source().
	 * @param string  $depth        Effective retrieval depth.
	 * @param string  $access_level Server-resolved access level.
	 * @return string
	 */
	public function render_post_text( WP_Post $post, $source, $depth, $access_level ) {
		if ( '' === $source ) {
			return '';
		}

		$block_context = static function ( $context, $block, $parent_block ) use ( $post ) {
			if ( null === $parent_block ) {
				$context['postId']   = $post->ID;
				$context['postType'] = $post->post_type;
			}
			return $context;
		};
		add_filter( 'render_block_context', $block_context, 10, 3 );
		try {
			// WordPress content filters (shortcodes, embeds, blocks, etc.).
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WP content filter required for oEmbed/shortcodes
			$html = apply_filters( 'the_content', $source );
		} finally {
			remove_filter( 'render_block_context', $block_context );
		}
		$html = FLOSC_Content_Filter::instance()->filter_post_content( (string) $html, $access_level, false );
		$text = $this->rendered_text( $html );
		/**
		 * Supply a plain-text description of components in the permitted HTML.
		 *
		 * @param string  $description  Component description.
		 * @param WP_Post $post         The resource being rendered.
		 * @param string  $html         Permitted rendered HTML.
		 * @param string  $depth        Effective retrieval depth.
		 * @param string  $access_level FLOSC access tier for this request.
		 */
		$description = apply_filters( 'flosc_rendered_post_description', '', $post, $html, $depth, $access_level );
		$text        = trim( $text . "\n" . sanitize_textarea_field( (string) $description ) );
		return wp_html_excerpt( $text, self::MAX_CONTENT_CHARS, strlen( $text ) > self::MAX_CONTENT_CHARS ? '...' : '' );
	}

	/**
	 * Extract text from permitted markup.
	 *
	 * @param string $html Permitted HTML.
	 * @return string
	 */
	private function rendered_text( $html ) {
		$html = preg_replace( '~<(template|noscript)\b[^>]*>.*?</\1\s*>~is', '', $html );
		$html = preg_replace( '~</(?:p|div|section|li|tr|h[1-6])\s*>|<br\s*/?>~i', ' ', $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Guide the assistant to the next step already configured in the flow.
	 *
	 * @param string $access_level Server-resolved access level.
	 * @return string Instruction for the model.
	 */
	public static function access_guidance( $access_level ) {
		$guidance = 'Acknowledge the person\'s interest and share any available title, link, or preview. Use the active IVR for the invitation and wording. ';
		if ( 'visitor' === $access_level ) {
			return $guidance . 'When the IVR offers a profile, warmly invite the person to register as a guest. Describe only what the IVR says becoming a guest opens.';
		}
		if ( 'guest' === $access_level ) {
			return $guidance . 'When the IVR has a matching membership offer, warmly invite the guest to see it. Use only the configured offer and describe only what it includes.';
		}
		return $guidance . 'Help the member find what is included in this flow or the relevant help it provides.';
	}
}
