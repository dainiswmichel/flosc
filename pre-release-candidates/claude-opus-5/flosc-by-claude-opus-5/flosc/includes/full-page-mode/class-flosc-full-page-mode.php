<?php
/**
 * Full-page mode presentation (full-page floscFlow UI).
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full page mode.
 */
class FLOSC_Full_Page_Mode {

	/**
	 * Construct.
	 *
	 * @var FLOSC_Framework
	 */
	private $flosc;

	/**
	 * Construct.
	 *
	 * @param mixed $flosc FLOSC.
	 */
	public function __construct( $flosc ) {
		$this->flosc = $flosc;
	}

	/**
	 * Is FLOSC request.
	 *
	 * @return mixed
	 */
	public function is_flosc_request() {
		// Full-page chat SPA only: custom domain, flow slug, or flosc_ivr rewrite.
		// Intentionally ignores forced_flow — companion knowledge-hub resolution sets
		// forced_flow on normal WP pages (e.g. /category/lessons/) so settings resolve
		// to the owning flow; those pages must keep the theme shell + companion widget,
		// not the full-app nuclear dequeue / flosc-app.js surface.
		return null !== $this->flosc->detect_flow_from_request_route();
	}

	/**
	 * Check if currently serving via custom domain
	 *
	 * @deprecated Use is_flosc_request() instead for most cases
	 * @since 1.1.9
	 */
	public static function is_custom_domain() {
		return defined( 'FLOSC_CUSTOM_DOMAIN_ACTIVE' ) && FLOSC_CUSTOM_DOMAIN_ACTIVE;
	}

	/**
	 * Get the appropriate app URL for current or specified flow
	 *
	 * @since 1.2.2
	 *
	 * @param mixed $flow Flow.
	 */
	public function get_app_url( $flow = null ) {
		if ( null === $flow ) {
			$flow = $this->flosc->get_current_flow();
		}

		if ( $flow && ! empty( $flow['custom_domain'] ) ) {
			// Normalize and return custom domain URL.
			// Prefer https for public chat hosts so companion iframes on https hubs
			// (e.g. the WordPress host/category/lessons/) are not mixed-content blocked.
			$custom_domain         = preg_replace( '#^https?://#', '', $flow['custom_domain'] );
			$custom_domain         = rtrim( $custom_domain, '/' );
			$flosc_forwarded_proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
				? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) )
				: '';
			$forwarded_https       = ( 'https' === $flosc_forwarded_proto );
			$use_https             = is_ssl() || $forwarded_https || apply_filters( 'flosc_custom_domain_force_https', true, $flow );
			return ( $use_https ? 'https://' : 'http://' ) . $custom_domain . '/';
		}

		if ( $flow && ! empty( $flow['slug'] ) ) {
			return home_url( '/' . $flow['slug'] . '/' );
		}

		// Fallback to legacy settings.
		$custom_domain = get_option( 'flosc_custom_domain', '' );

		if ( ! empty( $custom_domain ) ) {
			$custom_domain = preg_replace( '#^https?://#', '', $custom_domain );
			$custom_domain = rtrim( $custom_domain, '/' );
			return ( is_ssl() ? 'https://' : 'http://' ) . $custom_domain . '/';
		}

		// Fall back to slug-based URL.
		$slug = get_option( 'flosc_app_slug', 'flosc' );
		return home_url( '/' . $slug . '/' );
	}

	/**
	 * Add query vars.
	 *
	 * @param mixed $vars Vars.
	 * @return mixed
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'flosc_app';
		$vars[] = 'flosc_flow'; // v1.2.2: Multi-flow support.
		$vars[] = 'flosc_ivr';  // v1.2.9: IVR-file-based flows.
		$vars[] = 'ref';
		return $vars;
	}

	/**
	 * Handle app route.
	 */
	public function handle_app_route() {
		// v1.2.1: Use centralized is_flosc_request() helper
		// This reads from flosc_custom_domain setting (not hardcoded).
		if ( ! $this->is_flosc_request() ) {
			return;
		}

		$legal_page = $this->get_requested_legal_page();
		if ( null !== $legal_page ) {
			$this->render_legal_page( $legal_page );
			exit;
		}

		// v1.9.5: Disable WordPress admin bar on FLOSC app pages.
		// The admin bar injects CSS (html { margin-top: 32px !important; }),
		// JS, and HTML that conflicts with FLOSC's full-viewport flex layout.
		// FloscAdmins can still access wp-admin via the profile dropdown.
		show_admin_bar( false );

		// v1.9.5: Clean up wp_head() output — strip ALL theme/plugin hooks.
		// BuddyBoss hooks HTML templates (link-preview, profile-card, group-card),
		// inline scripts (ajaxurl), and late-enqueues (child theme CSS/JS) into wp_head
		// at various priorities. Removing individual actions is whack-a-mole.
		// Instead: clear everything, re-add only the three core WP functions:
		// 1. wp_enqueue_scripts (priority 1) — fires our nuclear dequeue
		// 2. wp_print_styles (priority 8) — outputs surviving CSS
		// 3. wp_print_head_scripts (priority 9) — outputs surviving head JS.
		remove_all_actions( 'wp_head' );
		add_action( 'wp_head', 'wp_enqueue_scripts', 1 );
		add_action( 'wp_head', 'wp_print_styles', 8 );
		add_action( 'wp_head', 'wp_print_head_scripts', 9 );

		// v1.9.5: Second dequeue pass — catch styles/scripts enqueued AFTER
		// our nuclear dequeue (BuddyBoss child theme enqueues via wp_head
		// callbacks at priority > 1, which fires after do_action('wp_enqueue_scripts')).
		// These hooks fire inside wp_print_styles()/wp_print_head_scripts()
		// just before the actual output, catching anything that slipped through.
		$flosc_style_whitelist = array( 'flosc-layout', 'flosc-theme', 'flosc-offers', 'flosc-preset', 'flosc-companion' );
		add_action(
			'wp_print_styles',
			function () use ( $flosc_style_whitelist ) {
				global $wp_styles;
				foreach ( $wp_styles->queue as $handle ) {
					if ( ! in_array( $handle, $flosc_style_whitelist, true ) ) {
						wp_dequeue_style( $handle );
					}
				}
			},
			0
		);

		$flosc_script_whitelist = array( 'flosc-app', 'flosc-companion', 'paypal-js', 'stripe-js' );
		add_action(
			'wp_print_scripts',
			function () use ( $flosc_script_whitelist ) {
				global $wp_scripts;
				foreach ( $wp_scripts->queue as $handle ) {
					if ( ! in_array( $handle, $flosc_script_whitelist, true ) ) {
						wp_dequeue_script( $handle );
					}
				}
			},
			0
		);

		// v1.9.5: Clean up wp_footer() output — BuddyBoss hooks modals
		// (Report, Block Member, etc.) into wp_footer as hidden HTML.
		// With theme CSS removed, these become visible. Solution: strip
		// wp_footer down to ONLY wp_print_footer_scripts (which outputs our
		// enqueued JS). This also fires did_action('wp_footer') correctly.
		remove_all_actions( 'wp_footer' );
		add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );

		// v1.9.5: Also clear wp_print_footer_scripts action hooks.
		// wp_print_footer_scripts() fires do_action('wp_print_footer_scripts').
		// _wp_footer_scripts() is hooked there — it's the core function that calls
		// $wp_scripts->do_footer_items() to output enqueued JS (flosc-app, paypal-js).
		// BuddyBoss/Jetpack ALSO hook inline JS + HTML templates on this action,
		// bypassing our wp_footer cleanup. Fix: clear all, re-add only _wp_footer_scripts.
		remove_all_actions( 'wp_print_footer_scripts' );
		add_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );

		$this->render_flosc_app();
		exit;
	}

	/**
	 * The policy definitions for a flow, resolved.
	 *
	 * One place answers what these four pages are called, where they live and
	 * whether FLOSC serves them at all. Before this, the slugs were written out
	 * three times in this file -- once for routing, once for the page map and
	 * once for the nav links -- so changing one meant finding all three.
	 *
	 * Every value has a default equal to what was hardcoded here before, so a
	 * flow whose admin has never opened the Identity tab serves exactly what it
	 * served previously, at the same addresses, with no migration.
	 *
	 * A policy in external mode keeps its stored content -- the assistant still
	 * reads it -- but the public link points at the admin's own address and
	 * FLOSC stops treating its local slug as the canonical page.
	 *
	 * @param array|string|null $flow A flow array, a flow id, or null for the current one.
	 * @return array<string,array<string,mixed>> Empty when policy management is inactive.
	 */
	public function policy_pages( $flow = null ) {
		/*
		 * A flow id rather than a flow array reads through flosc_get_setting(),
		 * which already knows that an IVR-file flow is absent from the flows
		 * registry and has to be rebuilt from its file. Resolving it here with
		 * get_flow() alone returns false for exactly those flows, and the
		 * caller silently falls back to whatever flow the current request is
		 * on -- which, from a chat turn, is the wrong one.
		 */
		$flosc_flow_id = null;
		if ( is_string( $flow ) ) {
			$flosc_flow_id = ( '' !== $flow ) ? $flow : null;
			$flow          = null;
		}

		if ( null === $flosc_flow_id && ! is_array( $flow ) ) {
			$flow = $this->flosc->get_current_flow();
		}
		$identity = ( is_array( $flow ) && is_array( $flow['identity'] ?? null ) ) ? $flow['identity'] : array();

		$flosc_read = function ( $key, $fallback = null ) use ( $identity, $flosc_flow_id ) {
			if ( null !== $flosc_flow_id ) {
				$value = flosc_get_setting( $key, null, $flosc_flow_id );
				return ( null === $value ) ? $fallback : $value;
			}
			return array_key_exists( $key, $identity ) ? $identity[ $key ] : $fallback;
		};
		$flosc_has  = function ( $key ) use ( $identity, $flosc_flow_id ) {
			if ( null !== $flosc_flow_id ) {
				$value = flosc_get_setting( $key, null, $flosc_flow_id );
				return null !== $value && '' !== $value;
			}
			return array_key_exists( $key, $identity );
		};

		// Absent means active: these pages already exist on every install, and a
		// missing setting must not switch them off under a site that has one.
		$status = strtolower( trim( (string) ( $flosc_read( 'policy_content_management_status', 'active' ) ) ) );
		if ( 'inactive' === $status ) {
			return array();
		}

		$defaults = array(
			'privacy_policy'      => array( 'privacy', 'Privacy Policy', 'Privacy' ),
			'terms_of_service'    => array( 'terms-of-service', 'Terms of Service', 'Terms' ),
			'data_deletion'       => array( 'data-deletion', 'User Data Deletion', 'Data Deletion' ),
			'platform_compliance' => array( 'platform-compliance', 'Platform Compliance', 'Platform Compliance' ),
		);

		$base = $this->get_current_request_base_url();

		$pages = array();
		foreach ( $defaults as $flosc_key => $flosc_default ) {
			/*
			 * An empty slug is a decision -- do not serve this page -- while a
			 * missing key means the admin has never touched it. The two cannot
			 * be told apart with ??, so the key is tested for existence.
			 */
			$slug = $flosc_has( $flosc_key . '_slug' )
				? sanitize_title( (string) $flosc_read( $flosc_key . '_slug', '' ) )
				: $flosc_default[0];

			$external_url = trim( (string) $flosc_read( $flosc_key . '_external_url', '' ) );
			$flosc_toggle = (string) $flosc_read( $flosc_key . '_use_external_link', '0' );

			/*
			 * An absolute http(s) address with a host is all this needs to be.
			 *
			 * wp_http_validate_url() was used here first and was the wrong
			 * tool: it exists to vet URLs the server is about to REQUEST, so it
			 * resolves the host and refuses anything that does not answer or
			 * points somewhere private. FLOSC never fetches these -- it prints
			 * them as links -- and that check quietly rejected an admin whose
			 * terms live on an intranet, or on any host this particular server
			 * cannot resolve, leaving the toggle switched on and doing nothing.
			 */
			$flosc_ext_scheme = strtolower( (string) wp_parse_url( $external_url, PHP_URL_SCHEME ) );
			$flosc_ext_host   = (string) wp_parse_url( $external_url, PHP_URL_HOST );
			$is_external      = ( '' !== $flosc_toggle && '0' !== $flosc_toggle )
				&& '' !== $external_url
				&& in_array( $flosc_ext_scheme, array( 'http', 'https' ), true )
				&& '' !== $flosc_ext_host;

			// Nothing to link to and nothing to serve.
			if ( ! $is_external && '' === $slug ) {
				continue;
			}

			$heading = trim( (string) $flosc_read( $flosc_key . '_heading', '' ) );

			$pages[ $flosc_key ] = array(
				'key'               => $flosc_key,
				// The table of contents keeps stable labels. A heading an admin
				// renames is the page's title, not the name of the link to it.
				'nav_label'         => $flosc_default[2],
				'heading'           => '' !== $heading ? $heading : $flosc_default[1],
				'slug'              => $slug,
				'content'           => (string) $flosc_read( $flosc_key . '_content', '' ),
				'use_external_link' => $is_external,
				'external_url'      => $is_external ? esc_url_raw( $external_url ) : '',
				'effective_url'     => $is_external ? esc_url_raw( $external_url ) : esc_url_raw( $base . $slug . '/' ),
				'is_external'       => $is_external,
			);
		}

		return $pages;
	}

	/**
	 * Which policy page this request is for.
	 *
	 * Matched against the slugs this flow actually serves, so a page an admin
	 * has switched off answers like any other unknown path rather than
	 * rendering an empty shell.
	 *
	 * @return string|null The page key, or null when this is not one of them.
	 */
	public function get_requested_legal_page() {
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( '' === $request_uri ) {
			return null;
		}

		$path = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return null;
		}

		foreach ( $this->policy_pages() as $flosc_key => $flosc_page ) {
			/*
			 * A policy pointing at the admin's own address is not served here.
			 * Routing its local slug as well would leave a second, stale copy
			 * of the same document answering on this domain.
			 */
			if ( $flosc_page['is_external'] || '' === $flosc_page['slug'] ) {
				continue;
			}
			if ( $flosc_page['slug'] === $path ) {
				return $flosc_key;
			}
		}

		return null;
	}

	/**
	 * Get current request base URL.
	 *
	 * @return mixed
	 */
	public function get_current_request_base_url() {
		$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
		if ( '' === $host ) {
			return home_url( '/' );
		}

		return ( is_ssl() ? 'https://' : 'http://' ) . $host . '/';
	}

	/**
	 * Render legal page.
	 *
	 * @param mixed $page Page.
	 */
	public function render_legal_page( $page ) {
		status_header( 200 );
		nocache_headers();

		$flow      = $this->flosc->get_current_flow();
		$site_name = $flow['identity']['name'] ?? 'FLOSC';
		$base_url  = $this->get_current_request_base_url();

		$page_map = $this->policy_pages( $flow );

		if ( ! isset( $page_map[ $page ] ) || $page_map[ $page ]['is_external'] ) {
			wp_die( 'Legal page not found.', 'Not Found', array( 'response' => 404 ) );
		}

		$current   = $page_map[ $page ];
		$home_link = esc_url( $base_url );

		$flosc_legal_css = FLOSC_PLUGIN_DIR . 'assets/css/flosc-frontend.css';
		$flosc_legal_ver = file_exists( $flosc_legal_css ) ? (string) filemtime( $flosc_legal_css ) : ( defined( 'FLOSC_VERSION' ) ? FLOSC_VERSION : '8.0.0' );
		wp_register_style(
			'flosc-legal-page',
			FLOSC_PLUGIN_URL . 'assets/css/flosc-frontend.css',
			array(),
			$flosc_legal_ver
		);
		wp_enqueue_style( 'flosc-legal-page' );

		echo '<!DOCTYPE html>';
		echo '<html ' . wp_kses_data( get_language_attributes() ) . '>';
		echo '<head>';
		echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( $current['heading'] . ' | ' . $site_name ) . '</title>';
		// Emit only this handle (standalone page has no full wp_head stack).
		wp_print_styles( array( 'flosc-legal-page' ) );
		echo '</head>';
		echo '<body>';
		echo '<main class="flosc-legal-shell">';
		echo '<nav class="flosc-legal-nav" aria-label="Legal navigation">';
		echo '<a href="' . esc_url( $home_link ) . '">Home</a>';
		// Only the pages this flow actually serves: a page switched off in the
		// Identity tab leaves no link behind pointing at a 404.
		foreach ( $page_map as $flosc_nav_page ) {
			// effective_url already points at the admin's own address when this
			// policy is external, and at the FLOSC-served page otherwise.
			echo '<a href="' . esc_url( $flosc_nav_page['effective_url'] ) . '">' . esc_html( $flosc_nav_page['nav_label'] ) . '</a>';
		}
		echo '</nav>';
		echo '<section class="flosc-legal-card">';
		echo '<h1>' . esc_html( $current['heading'] ) . '</h1>';
		if ( '' !== $current['content'] ) {
			echo wp_kses_post( $current['content'] );
		}
		echo '</section>';
		echo '</main>';
		echo '</body>';
		echo '</html>';
	}


	/**
	 * Extracted app rendering to separate method
	 * Called by handle_app_route() for both custom domain and slug routing
	 *
	 * @since 1.2.0
	 */
	public function render_flosc_app() {
		// v2.0.0: Prevent page caching — identity data is dynamic per-flow.
		nocache_headers();

		// Track referral (v1.0.7: use array syntax with SameSite).
		$get = wp_unslash( $_GET );
		$ref = get_query_var( 'ref' );
		if ( ! $ref ) {
			$ref = ( $get['ref'] ?? '' );
		}
		if ( $ref && ! is_user_logged_in() ) {
			setcookie(
				'flosc_referrer',
				sanitize_text_field( $ref ),
				array(
					'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
					'path'     => '/',
					'samesite' => 'Lax',
				)
			);
		}

		// Real-world state on this host only:
		// not logged in → visitor
		// logged in     → guest | member for THIS flow (never visitor).
		$user_state             = 'visitor';
		$user_data              = array();
		$current_flow_for_state = $this->flosc->get_current_flow();
		$flow_stem_for_state    = '';
		if ( is_array( $current_flow_for_state ) ) {
			$ivr_for_state       = (string) ( $current_flow_for_state['ivr_file'] ?? $current_flow_for_state['ivr'] ?? $current_flow_for_state['id'] ?? '' );
			$flow_stem_for_state = $this->flosc->flosc_normalize_flow_stem( $ivr_for_state );
			if ( '' === $flow_stem_for_state || 'default' === $flow_stem_for_state ) {
				$flow_stem_for_state = $this->flosc->flosc_normalize_flow_stem( (string) ( $current_flow_for_state['id'] ?? '' ) );
			}
		}

		if ( is_user_logged_in() ) {
			// Profile + per-flow wallet. Never paint visitor for an authenticated user.
			$user_data  = $this->flosc->build_app_user_payload(
				get_current_user_id(),
				$flow_stem_for_state,
				array(
					'allow_guest_grant_without_session' => false,
					'consume_event_transients'          => true,
				)
			);
			$user_state = (string) ( $user_data['state'] ?? 'guest' );
			if ( 'member' !== $user_state && 'guest' !== $user_state ) {
				$user_state = 'guest';
			}
			$user_data['state'] = $user_state;
		}

		// v1.3.5: Add admin verification data for in-chat message.
		$flow     = $this->flosc->get_current_flow();
		$ivr_file = $flow['ivr_file'] ?? '';

		// Load flow settings for all users — needed for autoprompts, headers, etc.
		$flow_settings = array();
		if ( ! empty( $ivr_file ) ) {
			$ivr_basename      = basename( $ivr_file );
			$flow_settings_key = 'flosc_flow_' . sanitize_key( pathinfo( $ivr_basename, PATHINFO_FILENAME ) );
			$flow_settings     = get_option( $flow_settings_key, array() );
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) && ! empty( $ivr_file ) ) {
			$ivr_basename      = basename( $ivr_file );
			$flow_settings_key = 'flosc_flow_' . sanitize_key( pathinfo( $ivr_basename, PATHINFO_FILENAME ) );

			// v2.0.0: Read from identity sub-array (where settings.php saves them).
			$av_identity                    = $flow_settings['identity'] ?? array();
			$user_data['adminVerification'] = array(
				'ivrFile' => $ivr_basename,
				'slug'    => $flow_settings['slug'] ?? sanitize_title( pathinfo( $ivr_basename, PATHINFO_FILENAME ) ),
				'name'    => $av_identity['name'] ?? pathinfo( $ivr_basename, PATHINFO_FILENAME ),
				'title'   => $av_identity['title'] ?? '',
				'tagline' => $av_identity['tagline'] ?? '',
				'domain'  => $flow_settings['domain'] ?? '',
			);
		}

		// Get flow identity (name, logo, favicon, brand color, pricing).
		$identity = $this->flosc->get_floscflow_identity();

		// Get available offers
		// v1.6.2: Pass flow_id so offers load from per-flow storage.
		$flow_id = null;
		if ( $flow && ! empty( $flow['ivr_file'] ) ) {
			$flow_id = pathinfo( basename( $flow['ivr_file'] ), PATHINFO_FILENAME );
		}
		$sale   = method_exists( $this->flosc, 'sale' ) ? $this->flosc->sale() : null;
		$offers = ( $sale && method_exists( $sale, 'get_available_offers' ) )
			? $sale->get_available_offers(
				is_user_logged_in() ? get_current_user_id() : null,
				$flow_id
			)
			: array();

		// Admin test-offer mode: bypass conditions/draft status to preview any offer.
		$test_offer_id = '';
		$get           = wp_unslash( $_GET );
		if ( current_user_can( 'manage_options' ) && ! empty( $get['flosc_test_offer'] ) ) {
			$oid   = sanitize_text_field( $get['flosc_test_offer'] );
			$nonce = sanitize_text_field( $get['flosc_test_nonce'] ?? '' );
			if ( wp_verify_nonce( $nonce, 'flosc_test_offer_' . $oid ) && $sale && method_exists( $sale, 'offers' ) ) {
				$test_offer_id  = $oid;
				$all_raw_offers = $sale->offers()->get_all_offers( $flow_id );
				foreach ( $all_raw_offers as $o ) {
					if ( ( $o['id'] ?? '' ) === $oid ) {
						$offers[ $oid ] = $o; // inject even if draft/inactive.
						break;
					}
				}
			}
		}

		// v4.0.0: Admin test mode — expose ALL offers (incl. drafts) for direct testing in chat.
		$admin_test_offers = array();
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) && $sale && method_exists( $sale, 'offers' ) ) {
			$all_raw = $sale->offers()->get_all_offers( $flow_id );
			foreach ( $all_raw as $o ) {
				$admin_test_offers[] = $o;
			}
		}

		// Get payment providers config for frontend.
		$providers = array();
		if ( $sale && method_exists( $sale, 'get_active_providers' ) ) {
			foreach ( $sale->get_active_providers() as $id => $provider ) {
				$providers[ $id ] = array(
					'id'     => $id,
					'name'   => $provider->get_name(),
					'icon'   => $provider->get_icon(),
					'config' => $provider->get_client_config(),
				);
			}
		}

		// v3.0.0: Generate FLOSC auth token for cross-domain compatibility
		// On every page load for logged-in users, generate a fresh token.
		// This token is included in FLOSC_CONFIG and set as a cookie.
		// It enables authentication when WordPress's native cookies fail
		// due to COOKIE_DOMAIN mismatch on custom domains.
		$flosc_auth_token = '';
		if ( is_user_logged_in() ) {
			$flosc_auth_token = $this->flosc->generate_flosc_auth_token( get_current_user_id() );
			$this->flosc->set_flosc_auth_cookie( $flosc_auth_token );
		}

		// Load template.
		include FLOSC_PLUGIN_DIR . 'admin/flosc-app.php';
		exit;
	}
}
