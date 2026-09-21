<?php
/**
 * FLOSC request-edge helpers: client IP, rate limits, signed cookies.
 *
 * Domain folder: includes/request-guard/ — not login/auth, not REST routing.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLOSC_Request_Guard {

	/**
	 * v1.7.7: Get real client IP, accounting for CDN/proxy headers
	 * Checks trusted proxy headers in priority order, falls back to REMOTE_ADDR
	 */
	public function get_client_ip() {
		// Cloudflare (most specific, hardest to spoof when CF is in use)
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		// Standard proxy header (X-Forwarded-For can be comma-separated; first = real client)
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			return sanitize_text_field( trim( $ips[0] ) );
		}
		// AWS ALB / generic proxy.
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		}
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
	}

	/**
	 * Rate Limiting Helper
	 * Prevents API abuse on public endpoints
	 *
	 * @param mixed $endpoint Endpoint.
	 * @param int $limit Limit.
	 * @param int $window Window.
	 */
	public function check_rate_limit( $endpoint, $limit = 20, $window = 3600 ) {
		// v1.7.7: Use real client IP behind CDN/proxy (Cloudflare, AWS ALB, etc.)
		$ip    = $this->get_client_ip();
		$key   = 'flosc_rate_' . md5( $endpoint . $ip );
		$count = get_transient( $key ) ?: 0;

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Signed Cookie Helpers (v9.4.2 Security Hardening)
	 *
	 * Prevents cookie forgery by adding HMAC signature.
	 * §5: Signed with the dedicated flosc_token_secret(), not wp_salt('auth').
	 */

	/**
	 * Create a signed cookie value
	 * Format: base64(data)|signature
	 *
	 * @param array $data Data to store in cookie.
	 * @return string Signed cookie value
	 */
	public function sign_cookie_data( $data ) {
		$json = wp_json_encode( $data );
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary/JWT token encoding, not obfuscation
		$encoded   = base64_encode( $json );
		$signature = hash_hmac( 'sha256', $encoded, flosc_token_secret() );
		return $encoded . '|' . $signature;
	}

	/**
	 * Verify and decode a signed cookie
	 *
	 * @param string $cookie_value Raw cookie value.
	 * @return array|false Decoded data or false if invalid
	 */
	public function verify_signed_cookie( $cookie_value ) {
		if ( empty( $cookie_value ) || strpos( $cookie_value, '|' ) === false ) {
			return false;
		}

		$parts = explode( '|', $cookie_value, 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list($encoded, $signature) = $parts;

		// Verify signature.
		$expected_signature = hash_hmac( 'sha256', $encoded, flosc_token_secret() );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			// Invalid signature - possible tampering.
			return false;
		}

		// Decode and return data.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary/JWT token decoding, not obfuscation
		$json = base64_decode( $encoded );
		if ( false === $json ) {
			return false;
		}

		// Pass 8: decode only; callers must field-sanitize. Reject non-arrays.
		$data = json_decode( $json, true, 16 );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Set a signed cookie
	 *
	 * @param string $name Cookie name.
	 * @param array  $data Data to store.
	 * @param int    $expiry Expiry time (timestamp or seconds from now).
	 */
	public function set_signed_cookie( $name, $data, $expiry = 0 ) {
		$value = $this->sign_cookie_data( $data );

		// v1.7.7: Explicit threshold — values under 1 year are treated as seconds-from-now.
		// Values over 1 year (31536000) are treated as absolute Unix timestamps.
		if ( $expiry > 0 && $expiry < 31536000 ) {
			$expiry = time() + $expiry;
		}

		// v1.0.7: Use array syntax with SameSite for security.
		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expiry,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Get data from a signed cookie
	 *
	 * @param string $name Cookie name.
	 * @return array|false Decoded data or false if invalid/missing
	 */
	public function get_signed_cookie( $name ) {
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return false;
		}
		// base64|hmac hex — sanitize_text_field preserves charset; signature still verified below.
		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		if ( '' === $value ) {
			return false;
		}
		return $this->verify_signed_cookie( $value );
	}

	/**
	 * Every query key the FLOSC admin screens read.
	 *
	 * One list, because the screens share the array: admin/settings.php builds
	 * it and the tab templates it includes (autoprompts, chat-logs, concierge,
	 * da1, documentation, knowledge-base, flow, flows, offers, ivr-messages,
	 * ai-configuration) read from the same variable. A per-screen list would
	 * mean a template narrowing the array for whatever ran after it.
	 *
	 * Adding a new ?key= to an admin link means adding it here.
	 *
	 * @return string[]
	 */
	public static function admin_query_keys() {
		return array(
			'_wpnonce',
			'catalog',
			'concierge_created',
			'concierge_error',
			'da1_export',
			'default_set',
			'delete_flow',
			'delete_message',
			'delete_offer',
			'doc',
			'edit_message',
			'edit_offer',
			'expand',
			'flosc_download_ivr',
			'flosc_guest_request_notice',
			'flosc_ivr_uploaded',
			'flosc_portability_done',
			'flosc_user_id',
			'ivr',
			'ivr_phase',
			'kb_edit',
			'kb_id',
			'logview',
			'phase',
			'saved',
			'session_scope',
			'set_status',
			'site_index_action',
			'site_index_error',
			'status',
			'tab',
			'toggle_status',
			'trajectory_created',
			'trajectory_error',
			'trajectory_toggled',
			'view',
		);
	}

	/**
	 * Read named query parameters for an admin screen, sanitized on the read.
	 *
	 * Admin screens need a handful of query values to decide what to render.
	 * Reading the whole of $_GET at file scope hands raw request data to every
	 * line below it, and does that work on every page load rather than only on
	 * the loads that actually carry those values.
	 *
	 * This reads only the keys a screen names, sanitizes each one as it is
	 * read, and returns them in the array shape the existing callers expect.
	 * Keys absent from the request are omitted, so `isset()` on the result
	 * behaves exactly as it did against the raw array.
	 *
	 * Routing and display values only. Every branch that changes state still
	 * verifies its own nonce and capability before it acts — this method does
	 * not authorize anything and must not be treated as if it does.
	 *
	 * @param string[] $keys Query keys this screen reads.
	 * @return array<string,mixed> Sanitized values, keyed as requested.
	 */
	public static function query_params( array $keys ) {
		$params = array();
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( '' === $key || ! isset( $_GET[ $key ] ) ) {
				continue;
			}
			$params[ $key ] = is_array( $_GET[ $key ] )
				? map_deep( wp_unslash( $_GET[ $key ] ), 'sanitize_text_field' )
				: sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}
		return $params;
	}

	/**
	 * The submitted admin form payload, or an empty array when this request is
	 * not one.
	 *
	 * Reading $_POST at file scope does that work on every page view, the vast
	 * majority of which are not submissions. This returns early unless the
	 * request really is a POST from a user who can reach the FLOSC screens, so
	 * an ordinary page view costs nothing and an unprivileged POST gets
	 * nothing to work with.
	 *
	 * Values are unslashed but deliberately NOT blanket-sanitized. These
	 * screens carry IVR markdown and other multi-line content that
	 * sanitize_text_field() would flatten, and the settings save iterates every
	 * submitted key rather than a fixed list. Each consumer sanitizes for its
	 * own field, and every branch that writes verifies its own nonce first.
	 *
	 * @return array<string,mixed> The unslashed payload, or an empty array.
	 */
	public static function admin_post_payload() {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';
		if ( 'POST' !== $method ) {
			return array();
		}
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return array();
		}
		return wp_unslash( $_POST );
	}
}
