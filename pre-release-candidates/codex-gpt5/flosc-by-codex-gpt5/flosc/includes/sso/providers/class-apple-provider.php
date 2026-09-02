<?php
/**
 * Apple SSO Provider
 * 
 * Implements Apple Sign In for FLOSC.
 * Note: Apple Sign In has unique requirements:
 * - Uses JWT for client secret (generated from key file)
 * - User info is only returned on first authorization
 * - POST callback instead of GET
 * 
 * @package FLOSC
 * @subpackage SSO\Providers
 * @since 1.4.0
 */

namespace FLOSC\SSO\Providers;

use FLOSC\SSO\SSO_Provider_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Apple Provider Class
 */
class Apple_Provider extends SSO_Provider_Base {
    
    /**
     * Apple Team ID
     * @var string
     */
    private $team_id;
    
    /**
     * Apple Key ID
     * @var string
     */
    private $key_id;
    
    /**
     * Apple Private Key (contents of .p8 file)
     * @var string
     */
    private $private_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->provider_id = 'apple';
        $this->provider_name = 'Apple';
        $this->provider_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>';
        
        $this->auth_url = 'https://appleid.apple.com/auth/authorize';
        $this->token_url = 'https://appleid.apple.com/auth/token';
        $this->user_info_url = ''; // Apple includes user info in ID token
        
        $this->scopes = array(
            'name',
            'email',
        );
        
        parent::__construct();
        
        // Load Apple-specific credentials (global defaults; overridden per-flow at runtime)
        $this->team_id = get_option('flosc_sso_apple_team_id', '');
        $this->key_id = get_option('flosc_sso_apple_key_id', '');
        $this->private_key = get_option('flosc_sso_apple_private_key', '');
    }
    
    /**
     * v1.5.0: Set flow-specific Apple credentials (overrides global options)
     * Extends the base set_flow_credentials to include Apple's extra fields.
     *
     * @param string $client_id    Flow-specific Client/Service ID
     * @param string $client_secret Flow-specific Client Secret (unused for Apple, generated from keys)
     * @param bool   $enabled      Whether Apple SSO is enabled for this flow
     * @param string $team_id      Flow-specific Apple Team ID
     * @param string $key_id       Flow-specific Apple Key ID
     * @param string $private_key  Flow-specific Apple Private Key (.p8 contents)
     */
    public function set_flow_apple_credentials($client_id, $client_secret, $enabled, $team_id, $key_id, $private_key) {
        // Set base credentials (client_id, client_secret, enabled)
        $this->set_flow_credentials($client_id, $client_secret, $enabled);
        
        // Override Apple-specific fields
        if (!empty($team_id)) {
            $this->team_id = $team_id;
        }
        if (!empty($key_id)) {
            $this->key_id = $key_id;
        }
        if (!empty($private_key)) {
            $this->private_key = $private_key;
        }
    }
    
    /**
     * Check if provider has valid credentials
     * Apple requires additional credentials beyond client_id/secret
     * 
     * @return bool
     */
    public function is_configured() {
        return !empty($this->client_id) && 
               !empty($this->team_id) && 
               !empty($this->key_id) && 
               !empty($this->private_key);
    }
    
    /**
     * Customize authorization parameters for Apple
     * 
     * @param array $params Default parameters
     * @return array Modified parameters
     */
    protected function customize_auth_params($params) {
        // Apple uses 'response_mode' parameter
        $params['response_mode'] = 'form_post'; // Apple sends POST response
        
        // Apple scopes are space-separated
        $params['scope'] = implode(' ', $this->scopes);
        
        return $params;
    }
    
    /**
     * Exchange authorization code for access token
     * Apple requires a dynamically generated JWT as client_secret
     * 
     * @param string $code Authorization code
     * @param string $redirect_uri Callback URL
     * @return array|WP_Error Token data or error
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        // Generate the client secret JWT
        $client_secret = $this->generate_client_secret();
        
        if (is_wp_error($client_secret)) {
            return $client_secret;
        }
        
        $response = wp_remote_post($this->token_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'client_id'     => $this->client_id,
                'client_secret' => $client_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirect_uri,
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new \WP_Error(
                'token_error',
                isset($body['error_description']) ? $body['error_description'] : $body['error']
            );
        }
        
        if (empty($body['id_token'])) {
            return new \WP_Error('token_error', 'No ID token in response');
        }
        
        return $body;
    }
    
    /**
     * Get user info from Apple
     * Apple embeds user info in the id_token JWT
     * 
     * @param string $access_token OAuth access token (we use id_token instead)
     * @return array|WP_Error User data or error
     */
    public function get_user_info($access_token, $token_data = array()) {
        // Standard OIDC: user claims come from a verified id_token (not access_token alone).
        $id_token = isset($token_data['id_token']) ? $token_data['id_token'] : '';

        if (empty($id_token) || !is_string($id_token)) {
            return new \WP_Error('no_id_token', 'Apple ID token not found in token response');
        }

        $payload_raw = $this->verify_id_token($id_token);
        if (is_wp_error($payload_raw)) {
            return $payload_raw;
        }

        $email_verified_raw = $payload_raw['email_verified'] ?? false;
        if (is_bool($email_verified_raw)) {
            $email_verified = $email_verified_raw ? 'true' : 'false';
        } else {
            $email_verified = strtolower(sanitize_text_field((string) $email_verified_raw));
            if (in_array($email_verified, array('1', 'yes'), true)) {
                $email_verified = 'true';
            }
        }

        $payload = array(
            'sub'            => sanitize_text_field((string) ($payload_raw['sub'] ?? '')),
            'email'          => sanitize_email((string) ($payload_raw['email'] ?? '')),
            'email_verified' => $email_verified,
        );

        if ($payload['sub'] === '') {
            return new \WP_Error('invalid_id_token', 'Apple ID token missing subject');
        }

        // Apple may send a JSON user object in POST on first authorization only.
        $user_post = filter_input( INPUT_POST, 'user', FILTER_UNSAFE_RAW );
        $raw_user_json = is_string( $user_post ) && $user_post !== ''
            ? sanitize_textarea_field( wp_unslash( $user_post ) )
            : '';
        $user_data_raw = array();
        if ( $raw_user_json !== '' && strlen( $raw_user_json ) <= 20000 ) {
            $decoded_user = json_decode( $raw_user_json, true, 8 );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded_user ) ) {
                $user_data_raw = $decoded_user;
            }
        }
        $user_data = array();
        if ( isset( $user_data_raw['name'] ) && is_array( $user_data_raw['name'] ) ) {
            $user_data['name'] = array(
                'firstName' => sanitize_text_field( (string) ( $user_data_raw['name']['firstName'] ?? '' ) ),
                'lastName'  => sanitize_text_field( (string) ( $user_data_raw['name']['lastName'] ?? '' ) ),
            );
        }
        if ( isset( $user_data_raw['email'] ) ) {
            $user_data['email'] = sanitize_email( (string) $user_data_raw['email'] );
        }

        return $this->normalize_user_data(array(
            'id_token_payload' => $payload,
            'user_data'        => $user_data,
        ));
    }

    /**
     * Verify Apple Sign In id_token (standard OIDC JWT / JWKS RS256).
     *
     * Checks: alg, signature via Apple JWKS, iss, aud (Service ID), exp, iat, sub.
     * Fail closed on any check failure.
     *
     * @param string $id_token JWT from token endpoint.
     * @return array|\WP_Error Claims on success.
     */
    private function verify_id_token($id_token) {
        $parts = explode('.', $id_token);
        if (count($parts) !== 3) {
            return new \WP_Error('invalid_id_token', 'Invalid Apple ID token format');
        }

        list($header_b64, $payload_b64, $sig_b64) = $parts;

        $header  = json_decode($this->base64_url_decode($header_b64), true);
        $payload = json_decode($this->base64_url_decode($payload_b64), true);
        $sig     = $this->base64_url_decode($sig_b64);

        if (!is_array($header) || !is_array($payload) || $sig === '' || $sig === false) {
            return new \WP_Error('invalid_id_token', 'Failed to decode Apple ID token');
        }

        $alg = isset($header['alg']) ? (string) $header['alg'] : '';
        if ($alg !== 'RS256') {
            return new \WP_Error('invalid_id_token', 'Unsupported Apple ID token algorithm');
        }

        $kid = isset($header['kid']) ? (string) $header['kid'] : '';
        if ($kid === '') {
            return new \WP_Error('invalid_id_token', 'Apple ID token missing key id');
        }

        $jwk = $this->get_apple_jwk_by_kid($kid);
        if (is_wp_error($jwk)) {
            return $jwk;
        }

        $pem = $this->jwk_to_pem($jwk);
        if (is_wp_error($pem)) {
            return $pem;
        }

        $signed = $header_b64 . '.' . $payload_b64;
        $ok     = openssl_verify($signed, $sig, $pem, OPENSSL_ALGO_SHA256);
        if (1 !== $ok) {
            return new \WP_Error('invalid_id_token', 'Apple ID token signature verification failed');
        }

        $iss = isset($payload['iss']) ? (string) $payload['iss'] : '';
        if ($iss !== 'https://appleid.apple.com') {
            return new \WP_Error('invalid_id_token', 'Apple ID token issuer mismatch');
        }

        $aud = $payload['aud'] ?? '';
        $aud_ok = false;
        if (is_string($aud) && $aud !== '' && hash_equals((string) $this->client_id, $aud)) {
            $aud_ok = true;
        } elseif (is_array($aud)) {
            foreach ($aud as $a) {
                if (is_string($a) && hash_equals((string) $this->client_id, $a)) {
                    $aud_ok = true;
                    break;
                }
            }
        }
        if (!$aud_ok) {
            return new \WP_Error('invalid_id_token', 'Apple ID token audience mismatch');
        }

        $now = time();
        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        if ($exp < 1 || $now >= $exp) {
            return new \WP_Error('invalid_id_token', 'Apple ID token expired');
        }

        $iat = isset($payload['iat']) ? (int) $payload['iat'] : 0;
        // Reject tokens issued more than 60s in the future (clock skew).
        if ($iat > 0 && $iat > ($now + 60)) {
            return new \WP_Error('invalid_id_token', 'Apple ID token iat is not credible');
        }

        $sub = isset($payload['sub']) ? (string) $payload['sub'] : '';
        if ($sub === '') {
            return new \WP_Error('invalid_id_token', 'Apple ID token missing subject');
        }

        return $payload;
    }

    /**
     * Fetch Apple JWKS and return the JWK for $kid (cached).
     *
     * @param string $kid JWT header kid.
     * @return array|\WP_Error
     */
    private function get_apple_jwk_by_kid($kid) {
        $keys = $this->get_apple_jwks();
        if (is_wp_error($keys)) {
            return $keys;
        }

        foreach ($keys as $key) {
            if (!is_array($key)) {
                continue;
            }
            if (isset($key['kid']) && (string) $key['kid'] === (string) $kid) {
                return $key;
            }
        }

        // Kid miss: bust cache once and retry (key rotation).
        delete_transient('flosc_apple_jwks');
        $keys = $this->get_apple_jwks(true);
        if (is_wp_error($keys)) {
            return $keys;
        }
        foreach ($keys as $key) {
            if (is_array($key) && isset($key['kid']) && (string) $key['kid'] === (string) $kid) {
                return $key;
            }
        }

        return new \WP_Error('invalid_id_token', 'Apple signing key not found for token');
    }

    /**
     * @param bool $force Force network refresh.
     * @return array|\WP_Error List of JWK arrays.
     */
    private function get_apple_jwks($force = false) {
        if (!$force) {
            $cached = get_transient('flosc_apple_jwks');
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            'https://appleid.apple.com/auth/keys',
            array(
                'timeout' => 15,
                'headers' => array('Accept' => 'application/json'),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('apple_jwks', 'Failed to fetch Apple JWKS');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['keys']) || !is_array($body['keys'])) {
            return new \WP_Error('apple_jwks', 'Invalid Apple JWKS response');
        }

        set_transient('flosc_apple_jwks', $body['keys'], 12 * HOUR_IN_SECONDS);
        return $body['keys'];
    }

    /**
     * Convert an RSA JWK to a PEM public key for openssl_verify.
     *
     * @param array $jwk JWK with n, e.
     * @return string|\WP_Error PEM
     */
    private function jwk_to_pem(array $jwk) {
        if (empty($jwk['n']) || empty($jwk['e'])) {
            return new \WP_Error('apple_jwks', 'Incomplete Apple JWK');
        }

        $n = $this->base64_url_decode($jwk['n']);
        $e = $this->base64_url_decode($jwk['e']);
        if ($n === '' || $n === false || $e === '' || $e === false) {
            return new \WP_Error('apple_jwks', 'Invalid Apple JWK modulus/exponent');
        }

        $modulus  = $this->asn1_integer($n);
        $exponent = $this->asn1_integer($e);
        $sequence = $this->asn1_sequence($modulus . $exponent);
        $bitstring = "\x03" . $this->asn1_length(strlen($sequence) + 1) . "\x00" . $sequence;
        $rsa_oid   = pack('H*', '300d06092a864886f70d0101010500'); // rsaEncryption
        $pubkey    = $this->asn1_sequence($rsa_oid . $bitstring);

        $pem  = "-----BEGIN PUBLIC KEY-----\n";
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary/JWT token encoding, not obfuscation
        $pem .= chunk_split(base64_encode($pubkey), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    /**
     * @param string $bytes Unsigned big-endian integer bytes.
     * @return string ASN.1 INTEGER
     */
    private function asn1_integer($bytes) {
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . $this->asn1_length(strlen($bytes)) . $bytes;
    }

    /**
     * @param string $contents Inner DER.
     * @return string ASN.1 SEQUENCE
     */
    private function asn1_sequence($contents) {
        return "\x30" . $this->asn1_length(strlen($contents)) . $contents;
    }

    /**
     * @param int $length
     * @return string ASN.1 length encoding
     */
    private function asn1_length($length) {
        if ($length < 0x80) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($temp)) . $temp;
    }

    /**
     * Base64url decode (JWT).
     *
     * @param string $data
     * @return string|false
     */
    private function base64_url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary/JWT token decoding, not obfuscation
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
    
    /**
     * Normalize Apple user data to standard format
     * 
     * @param array $raw_data Raw user data from Apple
     * @return array Normalized user data
     */
    protected function normalize_user_data($raw_data) {
        $payload = $raw_data['id_token_payload'] ?? array();
        $user_data = $raw_data['user_data'] ?? array();
        
        // Apple only sends name on first authorization
        $first_name = '';
        $last_name = '';
        $name = '';
        
        if (!empty($user_data['name'])) {
            $first_name = sanitize_text_field($user_data['name']['firstName'] ?? '');
            $last_name = sanitize_text_field($user_data['name']['lastName'] ?? '');
            $name = trim($first_name . ' ' . $last_name);
        }

        $provider_id = sanitize_text_field((string) ($payload['sub'] ?? ''));
        $email = sanitize_email((string) ($payload['email'] ?? ''));
        $email_verified = sanitize_text_field((string) ($payload['email_verified'] ?? 'false'));

        return array(
            'provider_id'    => $provider_id,
            'email'          => $email,
            'email_verified' => $email_verified === 'true',
            'name'           => $name,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'avatar'         => '', // Apple doesn't provide avatars
            'locale'         => '',
            // Pass 8 / WPORG: never pass through the full decoded POST user JSON.
            // Only sanitized fields above are exposed to hooks and storage.
            'raw_data'       => array(),
        );
    }
    
    /**
     * Get provider-specific user ID
     * 
     * @param array $raw_data Raw user data
     * @return string Provider user ID
     */
    public function get_provider_user_id($raw_data) {
        $payload = $raw_data['id_token_payload'] ?? array();
        return $payload['sub'] ?? '';
    }
    
    /**
     * Generate Apple client secret JWT
     * 
     * @return string|WP_Error JWT client secret or error
     */
    private function generate_client_secret() {
        // Check if we have the required credentials
        if (!$this->is_configured()) {
            return new \WP_Error('missing_credentials', 'Apple Sign In is not fully configured');
        }
        
        // JWT header
        $header = array(
            'alg' => 'ES256',
            'kid' => $this->key_id,
        );
        
        // JWT claims
        $time = time();
        $claims = array(
            'iss' => $this->team_id,
            'iat' => $time,
            'exp' => $time + (86400 * 180), // 180 days (max allowed)
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->client_id,
        );
        
        // Encode header and claims
        $header_encoded = $this->base64_url_encode(wp_json_encode($header));
        $claims_encoded = $this->base64_url_encode(wp_json_encode($claims));
        
        $signature_input = $header_encoded . '.' . $claims_encoded;
        
        // Sign with ES256
        $signature = $this->sign_es256($signature_input);
        
        if (is_wp_error($signature)) {
            return $signature;
        }
        
        return $signature_input . '.' . $signature;
    }
    
    /**
     * Sign data with ES256 algorithm
     * 
     * @param string $data Data to sign
     * @return string|WP_Error Base64 URL encoded signature or error
     */
    private function sign_es256($data) {
        if (!function_exists('openssl_sign')) {
            return new \WP_Error('openssl_missing', 'OpenSSL extension is required for Apple Sign In');
        }
        
        $private_key = openssl_pkey_get_private($this->private_key);
        
        if (!$private_key) {
            return new \WP_Error('invalid_key', 'Invalid Apple private key');
        }
        
        $success = openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA256);
        
        if (!$success) {
            return new \WP_Error('sign_failed', 'Failed to sign Apple client secret');
        }
        
        // Convert DER signature to raw format (remove ASN.1 structure)
        $signature = $this->der_to_raw($signature);
        
        return $this->base64_url_encode($signature);
    }
    
    /**
     * Convert DER signature to raw format
     * 
     * @param string $der DER encoded signature
     * @return string Raw signature
     */
    private function der_to_raw($der) {
        $pos = 0;
        $size = strlen($der);
        
        // Skip SEQUENCE
        $pos += 2;
        
        // Get R
        $pos++; // Skip INTEGER tag
        $r_len = ord($der[$pos++]);
        if ($r_len > 128) {
            $r_len = ord($der[$pos++]);
        }
        $r = substr($der, $pos, $r_len);
        $pos += $r_len;
        
        // Get S
        $pos++; // Skip INTEGER tag
        $s_len = ord($der[$pos++]);
        if ($s_len > 128) {
            $s_len = ord($der[$pos++]);
        }
        $s = substr($der, $pos, $s_len);
        
        // Pad to 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
        
        return $r . $s;
    }
    
    /**
     * Base64 URL encode
     * 
     * @param string $data Data to encode
     * @return string Encoded data
     */
    private function base64_url_encode($data) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary/JWT token encoding, not obfuscation
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Get button colors
     * 
     * @return array
     */
    public function get_button_colors() {
        return array(
            'background' => '#000000',
            'text'       => '#ffffff',
            'border'     => '#000000',
        );
    }
    
    /**
     * Get provider settings fields
     * Apple requires additional fields
     * 
     * @return array Settings fields configuration
     */
    public function get_settings_fields() {
        $fields = parent::get_settings_fields();
        
        // Add Apple-specific fields
        $apple_fields = array(
            array(
                'id'          => 'flosc_sso_apple_team_id',
                'title'       => __('Team ID', 'flosc'),
                'type'        => 'text',
                'default'     => '',
                'description' => __('Your Apple Developer Team ID (10 characters)', 'flosc'),
            ),
            array(
                'id'          => 'flosc_sso_apple_key_id',
                'title'       => __('Key ID', 'flosc'),
                'type'        => 'text',
                'default'     => '',
                'description' => __('The Key ID from your Apple Sign In key', 'flosc'),
            ),
            array(
                'id'          => 'flosc_sso_apple_private_key',
                'title'       => __('Private Key', 'flosc'),
                // UI: multi-line textarea for .p8 body. Sanitizer: class-sso-manager maps
                // field ids containing "private_key" to sanitize_secret_setting (Pass 2).
                'type'        => 'textarea',
                'default'     => '',
                'description' => __('Contents of the .p8 private key file (include BEGIN/END lines)', 'flosc'),
            ),
        );
        
        return array_merge($fields, $apple_fields);
    }
    
    /**
     * Get setup instructions for Apple Sign In
     * 
     * @return string HTML instructions
     */
    public function get_setup_instructions() {
        $callback_url = $this->get_callback_url();
        
        return '
        <div class="flosc-sso-setup-instructions">
            <h4>Apple Sign In Setup Instructions</h4>
            <ol>
                <li>Go to the <a href="https://developer.apple.com/account/resources/identifiers/list" target="_blank">Apple Developer Portal</a></li>
                <li>Create an App ID:
                    <ul>
                        <li>Register a new identifier → App IDs</li>
                        <li>Enable "Sign in with Apple" capability</li>
                    </ul>
                </li>
                <li>Create a Services ID:
                    <ul>
                        <li>Register a new identifier → Services IDs</li>
                        <li>Enable "Sign in with Apple"</li>
                        <li>Configure the Web Domain and Return URL</li>
                        <li>Return URL: <code class="flosc-code-block">' . esc_html($callback_url) . '</code></li>
                        <li>The Services ID becomes your <strong>Client ID</strong></li>
                    </ul>
                </li>
                <li>Create a Key:
                    <ul>
                        <li>Go to Keys → Create a new key</li>
                        <li>Enable "Sign in with Apple"</li>
                        <li>Download the .p8 file (you can only download it once!)</li>
                        <li>Note the <strong>Key ID</strong></li>
                    </ul>
                </li>
                <li>Find your <strong>Team ID</strong> in the top right of the Apple Developer portal</li>
                <li>Enter all credentials above:
                    <ul>
                        <li>Client ID = Services ID</li>
                        <li>Team ID, Key ID from portal</li>
                        <li>Private Key = contents of .p8 file</li>
                    </ul>
                </li>
            </ol>
            <p><strong>Note:</strong> Apple Sign In requires HTTPS and a valid domain.</p>
        </div>';
    }
}
