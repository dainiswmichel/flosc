<?php
/**
 * FLOSC request-edge helpers: client IP, rate limits, signed cookies.
 *
 * Domain folder: includes/request-guard/ — not login/auth, not REST routing.
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Request_Guard {

    /**
     * v1.7.7: Get real client IP, accounting for CDN/proxy headers
     * Checks trusted proxy headers in priority order, falls back to REMOTE_ADDR
     */
    public function get_client_ip() {
        // Cloudflare (most specific, hardest to spoof when CF is in use)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        }
        // Standard proxy header (X-Forwarded-For can be comma-separated; first = real client)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            return sanitize_text_field(trim($ips[0]));
        }
        // AWS ALB / generic proxy
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REAL_IP']));
        }
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    }

    /**
     * Rate Limiting Helper
     * Prevents API abuse on public endpoints
     */
    public function check_rate_limit($endpoint, $limit = 20, $window = 3600) {
        // v1.7.7: Use real client IP behind CDN/proxy (Cloudflare, AWS ALB, etc.)
        $ip = $this->get_client_ip();
        $key = 'flosc_rate_' . md5($endpoint . $ip);
        $count = get_transient($key) ?: 0;

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $window);
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
     * @param array $data Data to store in cookie
     * @return string Signed cookie value
     */
    public function sign_cookie_data($data) {
        $json = wp_json_encode($data);
        $encoded = base64_encode($json);
        $signature = hash_hmac('sha256', $encoded, flosc_token_secret());
        return $encoded . '|' . $signature;
    }
    
    /**
     * Verify and decode a signed cookie
     * 
     * @param string $cookie_value Raw cookie value
     * @return array|false Decoded data or false if invalid
     */
    public function verify_signed_cookie($cookie_value) {
        if (empty($cookie_value) || strpos($cookie_value, '|') === false) {
            return false;
        }
        
        $parts = explode('|', $cookie_value, 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($encoded, $signature) = $parts;
        
        // Verify signature
        $expected_signature = hash_hmac('sha256', $encoded, flosc_token_secret());
        if (!hash_equals($expected_signature, $signature)) {
            // Invalid signature - possible tampering
            return false;
        }
        
        // Decode and return data
        $json = base64_decode($encoded);
        if ($json === false) {
            return false;
        }
        
        // Pass 8: decode only; callers must field-sanitize. Reject non-arrays.
        $data = json_decode($json, true, 16);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return false;
        }

        return $data;
    }
    
    /**
     * Set a signed cookie
     * 
     * @param string $name Cookie name
     * @param array $data Data to store
     * @param int $expiry Expiry time (timestamp or seconds from now)
     */
    public function set_signed_cookie($name, $data, $expiry = 0) {
        $value = $this->sign_cookie_data($data);
        
        // v1.7.7: Explicit threshold — values under 1 year are treated as seconds-from-now
        // Values over 1 year (31536000) are treated as absolute Unix timestamps
        if ($expiry > 0 && $expiry < 31536000) {
            $expiry = time() + $expiry;
        }
        
        // v1.0.7: Use array syntax with SameSite for security
        setcookie($name, $value, [
            'expires' => $expiry,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    /**
     * Get data from a signed cookie
     * 
     * @param string $name Cookie name
     * @return array|false Decoded data or false if invalid/missing
     */
    public function get_signed_cookie($name) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw value required for HMAC signature verification; sanitizing would corrupt the hash
        $value = isset($_COOKIE[$name]) ? wp_unslash($_COOKIE[$name]) : null;
        if (empty($value)) {
            return false;
        }
        return $this->verify_signed_cookie($value);
    }

}
