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
        if (!file_exists($dir . 'index.php')) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guarded one-time protection file under uploads-only FLOSC directory
            file_put_contents($dir . 'index.php', "<?php // Silence is golden.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- controlled write in FLOSC-managed path
        }
        if (!file_exists($dir . '.htaccess')) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guarded one-time protection file under uploads-only FLOSC directory
            file_put_contents($dir . '.htaccess', "Deny from all\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- controlled write in FLOSC-managed path
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
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- centralized uploads-only write gate with path containment checks
        return false !== file_put_contents($target, $content); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- controlled write in FLOSC-managed path
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
 * bleed) — uploading a resume to the dainis.net flow's basket can never surface
 * in the lesaep flow. The folder is web-protected (Deny from all + silent index)
 * and created on first use. $flow_stem is the flow id (e.g. 'dainis_net_ivr').
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
     * @param string $flow_stem Flow identifier (e.g. 'dainis_net_ivr').
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
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress login hook.
        do_action('wp_login', $user->user_login, $user); // Let session-aware plugins observe the login.

        // FLOSC's own cross-domain auth cookie rides alongside the WP cookie so a
        // flow served on flosc.ai / lesaep.com / dainis.net authenticates even when
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

