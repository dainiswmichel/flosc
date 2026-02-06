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
        
        // Load Apple-specific credentials
        $this->team_id = get_option('flosc_sso_apple_team_id', '');
        $this->key_id = get_option('flosc_sso_apple_key_id', '');
        $this->private_key = get_option('flosc_sso_apple_private_key', '');
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
    public function get_user_info($access_token) {
        // For Apple, we need to decode the id_token from the token response
        // This is called with access_token but we stored id_token data in session
        
        // Get the ID token from the stored token data
        $id_token = isset($_SESSION['flosc_apple_id_token']) ? $_SESSION['flosc_apple_id_token'] : '';
        
        if (empty($id_token)) {
            // Fallback: try to get from transient
            $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
            $id_token = get_transient('flosc_apple_id_token_' . $state);
        }
        
        if (empty($id_token)) {
            return new \WP_Error('no_id_token', 'Apple ID token not found');
        }
        
        // Decode the JWT (without verification - Apple's auth server is trusted)
        $parts = explode('.', $id_token);
        if (count($parts) !== 3) {
            return new \WP_Error('invalid_id_token', 'Invalid Apple ID token format');
        }
        
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        
        if (!$payload) {
            return new \WP_Error('decode_error', 'Failed to decode Apple ID token');
        }
        
        // Apple may also send user info in the POST data (first login only)
        $user_data = isset($_POST['user']) ? json_decode($_POST['user'], true) : array();
        
        return $this->normalize_user_data(array(
            'id_token_payload' => $payload,
            'user_data'        => $user_data,
        ));
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
            $first_name = $user_data['name']['firstName'] ?? '';
            $last_name = $user_data['name']['lastName'] ?? '';
            $name = trim($first_name . ' ' . $last_name);
        }
        
        return array(
            'provider_id'    => $payload['sub'] ?? '',
            'email'          => $payload['email'] ?? '',
            'email_verified' => ($payload['email_verified'] ?? 'false') === 'true',
            'name'           => $name,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'avatar'         => '', // Apple doesn't provide avatars
            'locale'         => '',
            'raw_data'       => $raw_data,
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
        $header_encoded = $this->base64_url_encode(json_encode($header));
        $claims_encoded = $this->base64_url_encode(json_encode($claims));
        
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
