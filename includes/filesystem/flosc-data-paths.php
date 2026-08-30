<?php
/**
 * FLOSC filesystem path helpers (uploads-only data dir + config resolvers).
 *
 * Domain folder: includes/filesystem/ — where FLOSC may resolve/write data files.
 * Loaded early from flosc.php after plugin constants.
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flosc_admin_may_view_secrets')) {
    /**
     * Whether the current user may see stored API keys / client secrets in admin UI.
     *
     * @return bool
     */
    function flosc_admin_may_view_secrets() {
        return current_user_can('manage_options');
    }
}

if (!function_exists('flosc_admin_secret_input_value')) {
    /**
     * Value attribute for secret inputs: full value for admins; empty for others
     * (empty submit preserves stored secret via sanitize_secret_setting).
     *
     * @param mixed $stored Stored option/setting value.
     * @return string
     */
    function flosc_admin_secret_input_value($stored) {
        if (flosc_admin_may_view_secrets()) {
            return (string) $stored;
        }
        return '';
    }
}

if (!function_exists('flosc_safe_remote_request')) {
    /**
     * Outbound HTTP for admin-configurable URLs.
     * Validates URL then uses wp_safe_remote_* (no private/loopback hosts).
     *
     * @param string $method GET|POST|DELETE|…
     * @param string $url    Absolute URL.
     * @param array  $args   wp_remote_* args (sslverify cannot be forced off).
     * @return array|WP_Error
     */
    function flosc_safe_remote_request($method, $url, $args = array()) {
        $url = esc_url_raw((string) $url);
        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_Error(
                'flosc_invalid_remote_url',
                __('Invalid or disallowed remote URL.', 'flosc')
            );
        }
        if (!is_array($args)) {
            $args = array();
        }
        // Certificate verification required.
        $args['sslverify'] = true;
        $method = strtoupper((string) $method);
        if ($method === 'GET') {
            return wp_safe_remote_get($url, $args);
        }
        if ($method === 'POST') {
            return wp_safe_remote_post($url, $args);
        }
        $args['method'] = $method;
        return wp_safe_remote_request($url, $args);
    }
}

/* =============================================================================
 * §1 — FLOSC writable data directory (uploads-only, by design)
 * -----------------------------------------------------------------------------
 * WordPress.org rule, and the reason this section exists: runtime-generated or
 * admin-edited files must never be written inside the plugin folder. Plugin
 * folders are replaced wholesale on upgrade (data loss) and are publicly
 * readable (data exposure). FLOSC therefore has exactly ONE writable home —
 * wp-content/uploads/flosc/ai_configuration_files/ — and exactly one function
 * that resolves it. There is deliberately no fallback: when uploads are
 * unavailable this returns '' and every caller fails safely, surfacing the
 * hosting problem instead of hiding it inside the plugin folder.
 *
 * The plugin's own ai_configuration_files/ folder still exists, but strictly
 * as READ-ONLY shipped defaults (see flosc_config_file() below for the
 * uploads-first read order).
 * ========================================================================== */
if ( ! function_exists( 'flosc_get_flow_option_rows' ) ) {
	/**
	 * All flosc_flow_* option rows (autoload=no). Prepared query + object cache.
	 *
	 * @return array<int, array{option_name?:string,option_value?:string}>
	 */
	function flosc_get_flow_option_rows() {
		$cache_key = 'flow_option_rows_v1';
		$cached    = wp_cache_get( $cache_key, 'flosc_options' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// Bulk LIKE on autoload=no options — no WP API; prepared + object-cached.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk LIKE flosc_flow_* options; object-cached
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				$wpdb->esc_like( 'flosc_flow_' ) . '%',
				'%' . $wpdb->esc_like( '_transient' ) . '%'
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		wp_cache_set( $cache_key, $rows, 'flosc_options', 60 );
		return $rows;
	}
}

if ( ! function_exists( 'flosc_bust_flow_option_rows_cache' ) ) {
	/**
	 * Invalidate flosc_get_flow_option_rows() cache after flow option writes.
	 *
	 * @return void
	 */
	function flosc_bust_flow_option_rows_cache() {
		wp_cache_delete( 'flow_option_rows_v1', 'flosc_options' );
	}
}

if ( ! function_exists( 'flosc_get_user_ids_for_meta' ) ) {
	/**
	 * User IDs matching a usermeta key (optional value). Prepared + object cache.
	 * Avoids SlowDBQuery meta_query / meta_key args on get_users().
	 *
	 * @param string      $meta_key   Meta key.
	 * @param string|null $meta_value Exact value, or null for key EXISTS.
	 * @param string      $compare    '=' or 'LIKE' when value set.
	 * @param int         $limit      Max IDs (0 = no SQL LIMIT).
	 * @return int[]
	 */
	function flosc_get_user_ids_for_meta( $meta_key, $meta_value = null, $compare = '=', $limit = 0 ) {
		$meta_key = (string) $meta_key;
		if ( $meta_key === '' ) {
			return array();
		}
		$compare   = ( 'LIKE' === strtoupper( (string) $compare ) ) ? 'LIKE' : '=';
		$limit     = max( 0, (int) $limit );
		$cache_key = 'uids_' . md5( $meta_key . '|' . (string) $meta_value . '|' . $compare . '|' . $limit );
		$cached    = wp_cache_get( $cache_key, 'flosc_usermeta' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// Prepared $wpdb (not get_users meta_key args) — avoids SlowDBQuery; object-cached.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared usermeta lookups; object-cached
		if ( null === $meta_value || '' === $meta_value ) {
			if ( $limit > 0 ) {
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT %d",
						$meta_key,
						$limit
					)
				);
			} else {
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
						$meta_key
					)
				);
			}
		} elseif ( 'LIKE' === $compare ) {
			$like = '%' . $wpdb->esc_like( (string) $meta_value ) . '%';
			if ( $limit > 0 ) {
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s LIMIT %d",
						$meta_key,
						$like,
						$limit
					)
				);
			} else {
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
						$meta_key,
						$like
					)
				);
			}
		} elseif ( $limit > 0 ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT %d",
					$meta_key,
					(string) $meta_value,
					$limit
				)
			);
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
					$meta_key,
					(string) $meta_value
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- end bulk usermeta lookups block
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		wp_cache_set( $cache_key, $ids, 'flosc_usermeta', 60 );
		return $ids;
	}
}

if ( ! function_exists( 'flosc_get_user_ids_for_meta_in' ) ) {
	/**
	 * User IDs where meta_key matches and meta_value is in $values.
	 *
	 * @param string   $meta_key Meta key.
	 * @param string[] $values   Allowed values.
	 * @param int      $limit    Max IDs.
	 * @return int[]
	 */
	function flosc_get_user_ids_for_meta_in( $meta_key, $values, $limit = 80 ) {
		$meta_key = (string) $meta_key;
		$values   = array_values( array_unique( array_filter( array_map( 'strval', (array) $values ) ) ) );
		$limit    = max( 1, (int) $limit );
		if ( $meta_key === '' || empty( $values ) ) {
			return array();
		}
		// Cap value list (engagement flow stems, status enums — small sets).
		$values    = array_slice( $values, 0, 50 );
		$cache_key = 'uids_in_' . md5( $meta_key . '|' . wp_json_encode( $values ) . '|' . $limit );
		$cached    = wp_cache_get( $cache_key, 'flosc_usermeta' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		// No dynamic IN (...) SQL — WPCS/Plugin Check reject interpolated IN lists.
		// Union per-value prepared lookups (same helper, object-cached per key/value).
		$found = array();
		foreach ( $values as $value ) {
			foreach ( flosc_get_user_ids_for_meta( $meta_key, $value, '=', 0 ) as $uid ) {
				$found[ (int) $uid ] = true;
				if ( count( $found ) >= $limit ) {
					break 2;
				}
			}
		}
		$ids = array_slice( array_map( 'intval', array_keys( $found ) ), 0, $limit );
		wp_cache_set( $cache_key, $ids, 'flosc_usermeta', 60 );
		return $ids;
	}
}

if ( ! function_exists( 'flosc_get_post_ids_for_meta' ) ) {
	/**
	 * Post IDs matching postmeta (prepared + object cache). Avoids SlowDBQuery meta_query.
	 *
	 * @param string $meta_key   Meta key.
	 * @param string $meta_value Meta value.
	 * @param int    $limit      Max posts.
	 * @return int[]
	 */
	function flosc_get_post_ids_for_meta( $meta_key, $meta_value, $limit = 1 ) {
		$meta_key   = (string) $meta_key;
		$meta_value = (string) $meta_value;
		$limit      = max( 1, (int) $limit );
		if ( $meta_key === '' ) {
			return array();
		}
		$cache_key = 'pids_' . md5( $meta_key . '|' . $meta_value . '|' . $limit );
		$cached    = wp_cache_get( $cache_key, 'flosc_postmeta' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// Prepared $wpdb (not get_posts meta_key args) — avoids SlowDBQuery; object-cached.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared postmeta lookup; object-cached
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT %d",
				$meta_key,
				$meta_value,
				$limit
			)
		);
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		wp_cache_set( $cache_key, $ids, 'flosc_postmeta', 60 );
		return $ids;
	}
}

if ( ! function_exists( 'flosc_fs_path_is_allowed_read' ) ) {
	/**
	 * Whether $path may be read by FLOSC (uploads, plugin dir, upload tmp, PHP temp).
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	function flosc_fs_path_is_allowed_read( $path ) {
		if ( ! is_string( $path ) || $path === '' ) {
			return false;
		}
		if ( is_uploaded_file( $path ) ) {
			return true;
		}
		$norm = wp_normalize_path( $path );
		$real = realpath( $path );
		if ( false !== $real ) {
			$norm = wp_normalize_path( $real );
		}
		if ( $norm === '' ) {
			return false;
		}
		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			$base = realpath( $uploads['basedir'] );
			if ( false !== $base && 0 === strpos( $norm, wp_normalize_path( $base ) ) ) {
				return true;
			}
		}
		$plugin_root = wp_normalize_path( trailingslashit( FLOSC_PLUGIN_DIR ) );
		if ( 0 === strpos( $norm, $plugin_root ) ) {
			return true;
		}
		// PHP system temp (some hosts move upload temps here before is_uploaded_file is true).
		$tmp_root = realpath( sys_get_temp_dir() );
		if ( false !== $tmp_root && 0 === strpos( $norm, wp_normalize_path( $tmp_root ) ) ) {
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'flosc_fs_get_contents' ) ) {
	/**
	 * Read a local file the WordPress way (WP_Filesystem), with Direct fallback
	 * so IVR/audio/config still work when the global FS object is unavailable.
	 *
	 * Only allowlisted paths (uploads, plugin dir, upload/PHP temp).
	 *
	 * @param string $path Absolute filesystem path.
	 * @return string|false
	 */
	function flosc_fs_get_contents( $path ) {
		if ( ! flosc_fs_path_is_allowed_read( $path ) ) {
			return false;
		}
		if ( ! class_exists( 'FLOSC_Filesystem', false ) ) {
			$fs_file = FLOSC_PLUGIN_DIR . 'includes/filesystem/class-flosc-filesystem.php';
			if ( is_readable( $fs_file ) ) {
				require_once $fs_file;
			}
		}
		if ( class_exists( 'FLOSC_Filesystem' ) ) {
			$fs   = new FLOSC_Filesystem();
			$body = $fs->read_file_safely( $path );
			if ( false !== $body ) {
				return $body;
			}
			$body = $fs->read_contents( $path );
			if ( false !== $body ) {
				return $body;
			}
		}
		// Local Direct transport (same API WordPress uses when FS_METHOD is direct).
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'WP_Filesystem_Direct', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}
		if ( class_exists( 'WP_Filesystem_Direct' ) ) {
			$direct = new WP_Filesystem_Direct( null );
			if ( $direct->exists( $path ) ) {
				$contents = $direct->get_contents( $path );
				return ( false === $contents ) ? false : (string) $contents;
			}
		}
		return false;
	}
}

if (!function_exists('flosc_protect_uploads_directory')) {
    /**
     * Drops lightweight access-control files into a FLOSC uploads folder.
     *
     * index.php blanks directory listings everywhere; .htaccess denies direct
     * reads on Apache/LiteSpeed hosts. These complement — never replace —
     * server-level security; they exist so a casual URL guess returns nothing.
     *
     * @param string $dir Absolute directory path (already created).
     */
    function flosc_protect_uploads_directory($dir) {
        $dir = trailingslashit($dir);
        if ( ! class_exists( 'FLOSC_Filesystem' ) ) {
            return;
        }
        $fs = new FLOSC_Filesystem();
        if ( ! file_exists( $dir . 'index.php' ) ) {
            $fs->write_file_safely( $dir . 'index.php', "<?php // Silence is golden.\n" );
        }
        if ( ! file_exists( $dir . '.htaccess' ) ) {
            $fs->write_file_safely( $dir . '.htaccess', "Deny from all\n" );
        }
    }
}

if (!function_exists('flosc_data_dir')) {
    /**
     * The single source of truth for where FLOSC may write.
     *
     * @return string Trailing-slashed uploads data directory, or '' when
     *                uploads are unavailable — never a plugin-folder path.
     */
    function flosc_data_dir() {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }
        $dir = trailingslashit($uploads['basedir']) . 'flosc/ai_configuration_files';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir)) {
            return '';
        }
        flosc_protect_uploads_directory($dir);
        return trailingslashit($dir);
    }
}

if (!function_exists('flosc_write_data_file')) {
    /**
     * The only sanctioned way to write a FLOSC data file.
     *
     * Resolves both the data directory and the write target through realpath
     * and refuses the write unless the target sits inside the data directory.
     * This makes "write outside uploads" structurally impossible at the one
     * chokepoint every save passes through, rather than a rule each call site
     * must remember.
     *
     * @param string $target  Absolute file path inside flosc_data_dir().
     * @param string $content File content.
     * @return bool Whether the write happened.
     */
    function flosc_write_data_file($target, $content) {
        $base = flosc_data_dir();
        if ('' === $base || !is_string($target) || '' === $target) {
            return false;
        }
        $base_real = realpath($base);
        $dir_real  = realpath(dirname($target));
        if (!$base_real || !$dir_real) {
            return false;
        }
        if (0 !== strpos(trailingslashit($dir_real), trailingslashit($base_real))) {
            return false;
        }
        if ( ! class_exists( 'FLOSC_Filesystem' ) ) {
            return false;
        }
        $fs = new FLOSC_Filesystem();
        return (bool) $fs->write_file_safely( $target, $content );
    }
}

if (!function_exists('flosc_data_file_path')) {
    /**
     * Build an absolute path under flosc_data_dir() for a basename only.
     * Returns '' if uploads data dir is unavailable or the name is empty after cleanup.
     *
     * @param string $filename File name (path segments stripped).
     * @return string Absolute path or ''.
     */
    function flosc_data_file_path($filename) {
        $base = flosc_data_dir();
        if ('' === $base) {
            return '';
        }
        $name = basename(sanitize_file_name((string) $filename));
        if ('' === $name || '.' === $name || '..' === $name) {
            return '';
        }
        return $base . $name;
    }
}

if (!function_exists('flosc_is_allowed_ivr_source_path')) {
    /**
     * True when $path realpath is under uploads flosc data dir or shipped plugin IVR folder.
     * Used before reading markdown for import so callers cannot pass arbitrary filesystem paths.
     *
     * @param string $path Absolute or relative path.
     * @return bool
     */
    function flosc_is_allowed_ivr_source_path($path) {
        if (!is_string($path) || '' === $path) {
            return false;
        }
        $real = realpath($path);
        if (false === $real || !is_file($real)) {
            return false;
        }
        $allowed_roots = array();
        $data_dir = flosc_data_dir();
        if ('' !== $data_dir) {
            $data_real = realpath($data_dir);
            if (false !== $data_real) {
                $allowed_roots[] = trailingslashit($data_real);
            }
        }
        $plugin_ivr = realpath(FLOSC_PLUGIN_DIR . 'ai_configuration_files');
        if (false !== $plugin_ivr) {
            $allowed_roots[] = trailingslashit($plugin_ivr);
        }
        foreach ($allowed_roots as $root) {
            if (0 === strpos($real, $root)) {
                return true;
            }
        }
        return false;
    }
}

/* =============================================================================
 * Per-flow Knowledge Base directory
 * -----------------------------------------------------------------------------
 * Each floscFlow owns a physically separate basket of uploaded knowledge files,
 * living under flosc_data_dir()/kb/<flow_stem>/. Because the folder is keyed to
 * the flow's stem, one flow's files are never visible to another (no cross-flow
 * bleed) — uploading a resume to the the WordPress host flow's basket can never surface
 * in the this flow. The folder is web-protected (Deny from all + silent index)
 * and created on first use. $flow_stem is the flow id (e.g. 'flow_ivr').
 * ========================================================================== */
if (!function_exists('flosc_flow_kb_dir')) {
    function flosc_flow_kb_dir($flow_stem) {
        $base = flosc_data_dir();
        if ('' === $base) {
            // Uploads unavailable — propagate the empty path so callers fail
            // safely instead of building a path relative to nowhere.
            return '';
        }
        $flow_stem = sanitize_key((string) $flow_stem);
        if ($flow_stem === '') {
            // No flow context — fall back to the shared base rather than guess a flow.
            return $base;
        }
        $dir = $base . 'kb/' . $flow_stem;
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir)) {
            return '';
        }
        flosc_protect_uploads_directory($dir);
        return trailingslashit($dir);
    }
}
if (!function_exists('flosc_chat_archive_dir')) {
    /**
     * Get per-flow chat archive directory.
     *
     * @param string $flow_stem Flow identifier (e.g. 'flow_ivr').
     *
     * @return string Trailing-slashed archive directory, or '' if unavailable.
     */
    function flosc_chat_archive_dir($flow_stem = '') {
        $base = flosc_data_dir();
        if ('' === $base) {
            return '';
        }

        $flow_stem = sanitize_key((string) $flow_stem);
        $dir = trailingslashit($base) . 'chat-archives/';

        if ('' !== $flow_stem) {
            $dir .= $flow_stem . '/';
        }

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir)) {
            return '';
        }

        flosc_protect_uploads_directory($dir);
        return trailingslashit($dir);
    }
}

/* =============================================================================
 * §5 — Token signing secret (dedicated; NOT the WordPress auth salt)
 * -----------------------------------------------------------------------------
 * WordPress.org flags signing/exposing material derived from wp_salt('auth'):
 * the auth salt protects login cookies, so reusing it for plugin tokens (and
 * surfacing those tokens to JS) couples our HMAC to a core secret. This helper
 * returns a dedicated 64-char secret, generated once and stored with
 * autoload=false so it is never shipped to the browser. Every FLOSC HMAC/XOR
 * key uses this instead of wp_salt('auth').
 * ========================================================================== */
if (!function_exists('flosc_token_secret')) {
    function flosc_token_secret() {
        $secret = get_option('flosc_token_secret');
        if (!$secret) {
            // Generated once on first use; autoload=false keeps it server-side only.
            $secret = wp_generate_password(64, true, true);
            update_option('flosc_token_secret', $secret, false);
        }
        return $secret;
    }
}

/* =============================================================================
 * §5b — Checkout binding token (proof a completion request is the buyer's browser)
 * -----------------------------------------------------------------------------
 * The problem this solves: a payment completion request carries a payment
 * identifier (PayPal subscription/order id). That identifier is not a secret —
 * it appears in URLs, emails, and logs — so on its own it cannot prove WHO is
 * making the request. Issuing a login session on the identifier alone would let
 * anyone holding a copied id authenticate as the buyer (the wordpress.org review
 * finding).
 *
 * The binding token is the missing proof. It is minted SERVER-SIDE when the
 * browser begins checkout, stored against that browser's session, and handed
 * back only to that browser. The browser returns it with the completion request;
 * the server consumes it once and confirms it was the token it issued to this
 * session. A replayed payment id from a log carries no valid binding token, so
 * it grants access (payment is real) but never a login session.
 *
 * This is provider-neutral: any browser-facing completion handler verifies the
 * token and calls flosc_issue_post_purchase_session(). Server-to-server paths
 * (webhooks, IPN) have no browser and never issue sessions — the buyer reaches
 * those through the emailed single-use link instead.
 * ========================================================================== */
if (!function_exists('flosc_checkout_binding_create')) {
    /**
     * Mint a single-use binding token for a checkout that is about to begin.
     *
     * Called server-side from the binding REST endpoint (and any server-side
     * order-creation path). The raw token is returned to the initiating browser
     * once; only its HMAC is stored, keyed to the caller's session.
     *
     * @param array $context Optional: 'session_id', 'flow_id', 'provider'.
     * @return string The raw token to hand to the initiating browser.
     */
    function flosc_checkout_binding_create($context = array()) {
        $token = wp_generate_password(43, false, false);
        $hash  = hash_hmac('sha256', $token, flosc_token_secret());
        $record = array(
            'session_id' => isset($context['session_id']) ? sanitize_text_field((string) $context['session_id']) : '',
            'flow_id'    => isset($context['flow_id']) ? sanitize_text_field((string) $context['flow_id']) : '',
            'provider'   => isset($context['provider']) ? sanitize_text_field((string) $context['provider']) : '',
            'offer_id'   => isset($context['offer_id']) ? sanitize_text_field((string) $context['offer_id']) : '',
            'user_id'    => isset($context['user_id']) ? absint($context['user_id']) : get_current_user_id(),
            'created_at' => time(),
        );
        // 1-hour lifetime: long enough to complete a payment, short enough that a
        // leaked token expires quickly. autoload is irrelevant for transients.
        set_transient('flosc_checkout_binding_' . $hash, $record, HOUR_IN_SECONDS);
        return $token;
    }
}

if (!function_exists('flosc_checkout_binding_verify')) {
    /**
     * Verify and consume a binding token.
     *
     * Single-use: the stored record is deleted on lookup, so the same token can
     * never authorize two sessions. When the completion request carries a
     * session id, it must match the session the token was minted for; this binds
     * the proof to one browser. When no session id is threaded through a given
     * provider flow, the server-minted single-use token is itself the proof.
     *
     * @param string $token      The token returned by the browser.
     * @param string $session_id The completing request's session id (may be '').
     * @return array|false The stored record on success, false otherwise.
     */
    function flosc_checkout_binding_verify($token, $session_id = '') {
        if (empty($token) || !is_string($token)) {
            return false;
        }
        $hash = hash_hmac('sha256', $token, flosc_token_secret());
        $key  = 'flosc_checkout_binding_' . $hash;
        $record = get_transient($key);
        delete_transient($key); // Consume regardless of outcome — one attempt only.
        if (!is_array($record)) {
            return false;
        }
        $session_id = sanitize_text_field((string) $session_id);
        if ('' !== $record['session_id'] && '' !== $session_id
            && !hash_equals($record['session_id'], $session_id)) {
            return false; // Token belongs to a different browser session.
        }
        return $record;
    }
}

/**
 * PayPal purchase intent (industry standard bind).
 *
 * Minted server-side before the PayPal JS createSubscription call. The intent UUID
 * is placed in PayPal custom_id. On activate, the server loads the intent and
 * requires: ACTIVE status, plan_id match, offer/amount/currency from intent only.
 */
if (!function_exists('flosc_paypal_purchase_intent_create')) {
    /**
     * @param array $data offer_id, plan_id, plan_type, amount, currency, flow_id, user_id, session_id, mode
     * @return array|WP_Error Intent record including purchase_uuid
     */
    function flosc_paypal_purchase_intent_create(array $data) {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : wp_generate_password(32, false, false);
        $uuid = sanitize_text_field((string) $uuid);
        if ($uuid === '') {
            return new WP_Error('intent_failed', __('Could not create purchase intent', 'flosc'), array('status' => 500));
        }
        $offer_id = sanitize_text_field((string) ($data['offer_id'] ?? ''));
        $plan_id  = sanitize_text_field((string) ($data['plan_id'] ?? ''));
        if ($offer_id === '' || $plan_id === '') {
            return new WP_Error('invalid_intent', __('Offer and PayPal plan are required', 'flosc'), array('status' => 400));
        }
        $record = array(
            'purchase_uuid' => $uuid,
            'offer_id'      => $offer_id,
            'plan_id'       => $plan_id,
            'plan_type'     => sanitize_key((string) ($data['plan_type'] ?? '')),
            'amount'        => number_format((float) ($data['amount'] ?? 0), 2, '.', ''),
            'currency'      => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'USD'))) ?: 'USD',
            'flow_id'       => sanitize_key((string) ($data['flow_id'] ?? '')),
            'user_id'       => absint($data['user_id'] ?? 0),
            'session_id'    => sanitize_text_field((string) ($data['session_id'] ?? '')),
            'mode'          => sanitize_key((string) ($data['mode'] ?? '')),
            'status'        => 'pending',
            'created_at'    => time(),
            'expires_at'    => time() + (2 * HOUR_IN_SECONDS),
        );
        // 2h: enough for PayPal popup; short-lived if leaked.
        set_transient('flosc_pp_pi_' . $uuid, $record, 2 * HOUR_IN_SECONDS);
        return $record;
    }
}

if (!function_exists('flosc_paypal_purchase_intent_get')) {
    /**
     * @param string $uuid
     * @return array|false
     */
    function flosc_paypal_purchase_intent_get($uuid) {
        $uuid = sanitize_text_field((string) $uuid);
        if ($uuid === '') {
            return false;
        }
        $record = get_transient('flosc_pp_pi_' . $uuid);
        return is_array($record) ? $record : false;
    }
}

if (!function_exists('flosc_paypal_purchase_intent_mark_fulfilled')) {
    /**
     * @param string $uuid
     * @param string $subscription_id
     * @param int    $user_id
     * @return bool
     */
    function flosc_paypal_purchase_intent_mark_fulfilled($uuid, $subscription_id, $user_id = 0) {
        $record = flosc_paypal_purchase_intent_get($uuid);
        if (!is_array($record)) {
            return false;
        }
        $record['status']          = 'fulfilled';
        $record['subscription_id'] = sanitize_text_field((string) $subscription_id);
        $record['fulfilled_at']    = time();
        if (absint($user_id) > 0) {
            $record['user_id'] = absint($user_id);
        }
        set_transient('flosc_pp_pi_' . $uuid, $record, DAY_IN_SECONDS);
        return true;
    }
}

if (!function_exists('flosc_issue_post_purchase_session')) {
    /**
     * Issue an authenticated session after a verified purchase, for the buyer's
     * own browser. This is the single sanctioned post-purchase login path; every
     * browser-facing payment handler routes through it.
     *
    * WordPress.org / Pass 2: flosc_post_purchase_instant_login defaults to false
     * so a completed checkout does not auto-set a WP auth cookie. Private deploys
     * that want same-browser post-purchase session may enable:
     *
     *     add_filter( 'flosc_post_purchase_instant_login', '__return_true' );
     *
     * Related: emailed passwordless ?flosc_login_token= after purchase is gated by
     * flosc_post_purchase_login_token (also default false).
     *
     * @param int    $user_id     The buyer.
     * @param string $redirect_to Optional safe redirect target after login.
     * @return bool Whether a session was issued.
     */
    function flosc_issue_post_purchase_session($user_id, $redirect_to = '') {
        $user_id = absint($user_id);
        if (!$user_id || !apply_filters('flosc_post_purchase_instant_login', false)) {
            return false;
        }
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        // Core WP login action (required for session-aware plugins).
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WP action wp_login
        do_action( 'wp_login', $user->user_login, $user );

        // FLOSC's own cross-domain auth cookie rides alongside the WP cookie so a
        // flow served on flosc.ai / the flow domain / the WordPress host authenticates even when
        // COOKIE_DOMAIN does not match the custom domain. The methods live on the
        // framework singleton (flosc()), not a separate session class.
        if (function_exists('flosc') && method_exists(flosc(), 'generate_flosc_auth_token')) {
            $auth_token = flosc()->generate_flosc_auth_token($user_id);
            flosc()->set_flosc_auth_cookie($auth_token);
        }

        if ('' !== $redirect_to) {
            $safe = wp_validate_redirect($redirect_to, '');
            if ('' !== $safe) {
                wp_safe_redirect($safe);
                exit;
            }
        }
        return true;
    }
}

/* =============================================================================
 * §2 — AI-configuration read resolvers (uploads-first, plugin default fallback)
 * -----------------------------------------------------------------------------
 * Writes land in the per-install uploads dir (flosc_data_dir()); the plugin ships
 * read-only defaults. These resolvers let every reader pick up an admin-edited or
 * newly-uploaded copy from uploads while still falling back to the shipped default,
 * so saved edits are actually read back. They resolve a SPECIFIC filename within
 * THIS install's dirs only — no cross-flow or cross-install bleeding.
 * ========================================================================== */
if (!function_exists('flosc_config_file')) {
    // Single config file: the uploads copy if it exists, else the shipped
    // default. The plugin path is a READ-ONLY resolution — every write goes
    // through flosc_write_data_file(), which only accepts uploads targets.
    function flosc_config_file($filename) {
        $filename = ltrim((string) $filename, '/');
        $base     = flosc_data_dir();
        if ('' !== $base && file_exists($base . $filename)) {
            return $base . $filename;
        }
        return FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;
    }
}

/**
 * Lesson-catalog basenames: neutral first, legacy product filename second.
 * Filterable so a product module can add/override names without forking readers.
 *
 * @return string[]
 */
if (!function_exists('flosc_lesson_catalog_basenames')) {
    function flosc_lesson_catalog_basenames() {
        // Ship core: neutral name only. Instances may add legacy basenames via filter.
        $names = ['lesson_catalog.md'];
        /**
         * @param string[] $names Basename candidates, first match wins for reads.
         */
        $names = apply_filters('flosc_lesson_catalog_basenames', $names);
        return is_array($names) ? array_values(array_filter(array_map('strval', $names))) : [];
    }
}

/**
 * Resolve the on-disk lesson catalog for recs/admin: first existing basename.
 * If none exist, returns the path for the preferred (first) basename (may not exist yet).
 *
 * @return string Absolute path or empty string.
 */
if (!function_exists('flosc_resolve_lesson_catalog_path')) {
    function flosc_resolve_lesson_catalog_path() {
        if (!function_exists('flosc_config_file')) {
            return '';
        }
        $preferred = '';
        foreach (flosc_lesson_catalog_basenames() as $i => $base) {
            $base = ltrim((string) $base, '/');
            if ($base === '') {
                continue;
            }
            $path = flosc_config_file($base);
            if ($i === 0) {
                $preferred = $path;
            }
            // flosc_config_file returns plugin path even when missing; require real file for match.
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        return $preferred;
    }
}

/**
 * Absolute write targets for generated catalog (uploads dir). Writes all basenames
 * so legacy product filenames and neutral ship names stay in sync.
 *
 * @return string[] Absolute paths under flosc_data_dir(), or empty if uploads unavailable.
 */
if (!function_exists('flosc_lesson_catalog_write_paths')) {
    function flosc_lesson_catalog_write_paths() {
        $dir = function_exists('flosc_data_dir') ? flosc_data_dir() : '';
        if ($dir === '') {
            return [];
        }
        $out = [];
        foreach (flosc_lesson_catalog_basenames() as $base) {
            $base = ltrim((string) $base, '/');
            if ($base !== '') {
                $out[] = $dir . $base;
            }
        }
        return $out;
    }
}

if (!function_exists('flosc_config_glob')) {
    // Union of glob matches across uploads + plugin dirs, deduped by basename
    // (uploads wins, since it is scanned first). $patterns is one pattern or a list.
    function flosc_config_glob($patterns) {
        $patterns = (array) $patterns;
        $dirs     = [];
        $base     = flosc_data_dir();
        if ('' !== $base) {
            $dirs[] = $base; // Uploads scanned first, so an edited copy wins.
        }
        $dirs[] = FLOSC_PLUGIN_DIR . 'ai_configuration_files/'; // Read-only shipped defaults.
        $seen = [];
        $out  = [];
        foreach ($dirs as $dir) {
            foreach ($patterns as $pattern) {
                foreach (glob($dir . $pattern) ?: [] as $match) {
                    $base = basename($match);
                    if (isset($seen[$base])) {
                        continue;
                    }
                    $seen[$base] = true;
                    $out[] = $match;
                }
            }
        }
        return $out;
    }
}

if (!function_exists('flosc_resolve_flow_option_key_for_ivr')) {
    function flosc_resolve_flow_option_key_for_ivr($flosc_ivr_filename) {
        $flosc_ivr_filename = basename((string) $flosc_ivr_filename);
        $target_stem = sanitize_key(pathinfo($flosc_ivr_filename, PATHINFO_FILENAME));
        $default_key = 'flosc_flow_' . $target_stem;

        // Start with deterministic default key and score it conservatively.
        $best_key = $default_key;
        $best_score = -1;

        // Scan flosc_flow_* (autoload=no) via cached prepared options scan.
        $flosc_rows = function_exists( 'flosc_get_flow_option_rows' ) ? flosc_get_flow_option_rows() : array();
        if ( ! is_array( $flosc_rows ) || empty( $flosc_rows ) ) {
            return $default_key;
        }

        foreach ( $flosc_rows as $flosc_row ) {
            $option_name = (string) ( $flosc_row['option_name'] ?? '' );
            if ( $option_name === '' || strpos( $option_name, 'flosc_flow_' ) !== 0 ) {
                continue;
            }

            $flosc_settings = maybe_unserialize( $flosc_row['option_value'] ?? '' );
            if ( ! is_array( $flosc_settings ) ) {
                continue;
            }

            $active = basename((string) ($flosc_settings['active_ivr_file'] ?? ''));
            $primary = basename((string) ($flosc_settings['ivr_file'] ?? ''));
            $matches_active = ($active !== '' && $active === $flosc_ivr_filename);
            $matches_primary = ($primary !== '' && $primary === $flosc_ivr_filename);

            // Only consider keys that are explicitly tied to this IVR filename.
            if (!$matches_active && !$matches_primary && $option_name !== $default_key) {
                continue;
            }

            $message_count = 0;
            if (function_exists('flosc_flow_get_messages') && is_array($flosc_settings)) {
                $message_count = count(flosc_flow_get_messages($flosc_settings));
            } elseif (isset($flosc_settings['flow_messages']) && is_array($flosc_settings['flow_messages'])) {
                $message_count = count($flosc_settings['flow_messages']);
            }

            $score = 0;
            // Prefer rows explicitly bound to this IVR file over a plain default
            // key, because legacy duplicate rows can leave default keys stale.
            if ($matches_primary) {
                $score += 2000;
            }
            if ($matches_active) {
                $score += 1800;
            }
            if ($option_name === $default_key) {
                $score += 200;
            }
            $score += min($message_count, 200);

            if ($score > $best_score) {
                $best_score = $score;
                $best_key = $option_name;
            }
        }

        return $best_key;
    }
}
