<?php
/**
 * User Linker Class
 * 
 * Handles linking social login accounts to WordPress users.
 * Manages the relationship between provider IDs and WP user accounts.
 * 
 * @package FLOSC
 * @subpackage SSO
 * @since 1.4.0
 */

namespace FLOSC\SSO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Linker Class
 */
class User_Linker {
    
    /**
     * Meta key prefix for linked accounts
     */
    const META_PREFIX = '_flosc_sso_';
    
    /**
     * Find WordPress user linked to a provider account
     * 
     * @param string $provider_id SSO provider ID
     * @param string $provider_user_id User ID from provider
     * @return int|false User ID or false if not found
     */
    public function find_linked_user($provider_id, $provider_user_id) {
        $meta_key = self::META_PREFIX . $provider_id . '_id';
        $ids      = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( $meta_key, (string) $provider_user_id, '=', 1 )
            : array();
        if ( empty( $ids ) ) {
            return false;
        }
        $user_id = (int) $ids[0];
        return $user_id > 0 ? $user_id : false;
    }
    
    /**
     * Link a provider account to a WordPress user
     * 
     * @param int $user_id WordPress user ID
     * @param string $provider_id SSO provider ID
     * @param array $user_data Normalized user data from provider
     * @param array $token_data Token response data
     * @return bool Success
     */
    public function link_account($user_id, $provider_id, $user_data, $token_data) {
        // Store provider user ID
        update_user_meta($user_id, self::META_PREFIX . $provider_id . '_id', $user_data['provider_id']);
        
        // Store linked timestamp
        update_user_meta($user_id, self::META_PREFIX . $provider_id . '_linked_at', time());
        
        // Store user data snapshot (re-sanitize at sink; never store decoded raw_data blobs).
        update_user_meta($user_id, self::META_PREFIX . $provider_id . '_data', array(
            'name'   => sanitize_text_field((string) ($user_data['name'] ?? '')),
            'email'  => sanitize_email((string) ($user_data['email'] ?? '')),
            'avatar' => esc_url_raw((string) ($user_data['avatar'] ?? '')),
        ));
        
        // Store tokens (encrypted)
        $this->store_tokens($user_id, $provider_id, $token_data);
        
        // Update linked providers list
        $linked_providers = get_user_meta($user_id, self::META_PREFIX . 'linked_providers', true);
        if (!is_array($linked_providers)) {
            $linked_providers = array();
        }
        if (!in_array($provider_id, $linked_providers)) {
            $linked_providers[] = $provider_id;
            update_user_meta($user_id, self::META_PREFIX . 'linked_providers', $linked_providers);
        }
        
        do_action('flosc_sso_account_linked', $user_id, $provider_id, $user_data);
        
        return true;
    }
    
    /**
     * Unlink a provider account from a WordPress user
     * 
     * @param int $user_id WordPress user ID
     * @param string $provider_id SSO provider ID
     * @return bool Success
     */
    public function unlink_account($user_id, $provider_id) {
        delete_user_meta($user_id, self::META_PREFIX . $provider_id . '_id');
        delete_user_meta($user_id, self::META_PREFIX . $provider_id . '_linked_at');
        delete_user_meta($user_id, self::META_PREFIX . $provider_id . '_data');
        delete_user_meta($user_id, self::META_PREFIX . $provider_id . '_tokens');
        
        // Update linked providers list
        $linked_providers = get_user_meta($user_id, self::META_PREFIX . 'linked_providers', true);
        if (is_array($linked_providers)) {
            $linked_providers = array_diff($linked_providers, array($provider_id));
            update_user_meta($user_id, self::META_PREFIX . 'linked_providers', array_values($linked_providers));
        }
        
        do_action('flosc_sso_account_unlinked', $user_id, $provider_id);
        
        return true;
    }
    
    /**
     * Get all linked providers for a user
     * 
     * @param int $user_id WordPress user ID
     * @return array Array of provider IDs
     */
    public function get_linked_providers($user_id) {
        $linked_providers = get_user_meta($user_id, self::META_PREFIX . 'linked_providers', true);
        return is_array($linked_providers) ? $linked_providers : array();
    }
    
    /**
     * Check if user has a specific provider linked
     * 
     * @param int $user_id WordPress user ID
     * @param string $provider_id SSO provider ID
     * @return bool
     */
    public function is_provider_linked($user_id, $provider_id) {
        return in_array($provider_id, $this->get_linked_providers($user_id));
    }
    
    /**
     * Create a new WordPress user from SSO data
     * 
     * @param string $provider_id SSO provider ID
     * @param array $user_data Normalized user data
     * @param array $token_data Token response data
     * @return int|WP_Error User ID or error
     */
    public function create_user_from_sso($provider_id, $user_data, $token_data) {
        $email = isset($user_data['email']) ? sanitize_email($user_data['email']) : '';
        $name = isset($user_data['name']) ? sanitize_text_field($user_data['name']) : '';
        $first_name = isset($user_data['first_name']) ? sanitize_text_field($user_data['first_name']) : '';
        $last_name = isset($user_data['last_name']) ? sanitize_text_field($user_data['last_name']) : '';
        
        // Generate username from email or name
        $username = $this->generate_unique_username($email, $name);
        
        // Generate secure random password (user won't need it for SSO)
        $password = wp_generate_password(24, true, true);
        
        $user_data_wp = array(
            'user_login'    => $username,
            'user_email'    => $email,
            'user_pass'     => $password,
            'display_name'  => $name ?: $username,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'role'          => apply_filters('flosc_sso_default_role', 'subscriber'),
        );
        
        // Allow filtering before creation
        $user_data_wp = apply_filters('flosc_sso_new_user_data', $user_data_wp, $provider_id, $user_data);

        // Best-in-class: re-sanitize after filter so third-party hooks cannot inject raw fields.
        if (is_array($user_data_wp)) {
            $user_data_wp['user_login']   = sanitize_user((string) ($user_data_wp['user_login'] ?? ''), true);
            $user_data_wp['user_email']   = sanitize_email((string) ($user_data_wp['user_email'] ?? ''));
            $user_data_wp['display_name'] = sanitize_text_field((string) ($user_data_wp['display_name'] ?? ''));
            $user_data_wp['first_name']   = sanitize_text_field((string) ($user_data_wp['first_name'] ?? ''));
            $user_data_wp['last_name']    = sanitize_text_field((string) ($user_data_wp['last_name'] ?? ''));
            if (isset($user_data_wp['role'])) {
                $user_data_wp['role'] = sanitize_key((string) $user_data_wp['role']);
            }
            if (empty($user_data_wp['user_email']) || !is_email($user_data_wp['user_email'])) {
                return new \WP_Error('flosc_sso_invalid_email', 'Valid email required for SSO registration.');
            }
            if (empty($user_data_wp['user_login'])) {
                return new \WP_Error('flosc_sso_invalid_login', 'Valid username required for SSO registration.');
            }
        } else {
            return new \WP_Error('flosc_sso_invalid_user_data', 'Invalid SSO user data.');
        }

        $user_id = wp_insert_user($user_data_wp);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Link the SSO account
        $this->link_account($user_id, $provider_id, $user_data, $token_data);
        
        // Store avatar if available
        if (!empty($user_data['avatar'])) {
            update_user_meta($user_id, self::META_PREFIX . 'avatar', esc_url_raw((string) $user_data['avatar']));
        }
        
        // Mark as SSO-created user
        update_user_meta($user_id, self::META_PREFIX . 'created_via', $provider_id);
        update_user_meta($user_id, self::META_PREFIX . 'created_at', time());
        // Canonical registration method for condition evaluator / Engagement summary.
        // Provider detail remains on created_via; method bucket is "sso".
        if (!(string) get_user_meta($user_id, '_flosc_registration_method', true)) {
            update_user_meta($user_id, '_flosc_registration_method', 'sso');
        }
        
        do_action('flosc_sso_user_created', $user_id, $provider_id, $user_data);
        
        return $user_id;
    }
    
    /**
     * Generate a unique username
     * 
     * @param string $email User email
     * @param string $name User display name
     * @return string Unique username
     */
    private function generate_unique_username($email, $name = '') {
        // Try email-based username first
        if ($email) {
            $base = strstr($email, '@', true);
            $base = sanitize_user($base, true);
        } elseif ($name) {
            $base = sanitize_user(str_replace(' ', '', strtolower($name)), true);
        } else {
            $base = 'user';
        }
        
        // Ensure minimum length
        if (strlen($base) < 3) {
            $base = 'user_' . $base;
        }
        
        // Make unique
        $username = $base;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Store OAuth tokens (encrypted)
     * 
     * @param int $user_id WordPress user ID
     * @param string $provider_id SSO provider ID
     * @param array $token_data Token response data
     */
    private function store_tokens($user_id, $provider_id, $token_data) {
        $tokens = array(
            'access_token'  => isset($token_data['access_token']) ? $token_data['access_token'] : '',
            'refresh_token' => isset($token_data['refresh_token']) ? $token_data['refresh_token'] : '',
            'expires_at'    => isset($token_data['expires_in']) ? time() + $token_data['expires_in'] : 0,
            'token_type'    => isset($token_data['token_type']) ? $token_data['token_type'] : 'Bearer',
        );
        
        // Simple encryption using WP salts
        $encrypted = $this->encrypt_tokens($tokens);
        
        update_user_meta($user_id, self::META_PREFIX . $provider_id . '_tokens', $encrypted);
    }
    
    /**
     * Get stored tokens for a user/provider
     * 
     * @param int $user_id WordPress user ID
     * @param string $provider_id SSO provider ID
     * @return array|false Token data or false
     */
    public function get_tokens($user_id, $provider_id) {
        $encrypted = get_user_meta($user_id, self::META_PREFIX . $provider_id . '_tokens', true);
        
        if (!$encrypted) {
            return false;
        }
        
        return $this->decrypt_tokens($encrypted);
    }
    
    /**
     * Update stored tokens
     * 
     * @param int $user_id WordPress user ID
     * @param string $provider_id SSO provider ID
     * @param array $token_data New token data
     */
    public function update_user_tokens($user_id, $provider_id, $token_data) {
        $this->store_tokens($user_id, $provider_id, $token_data);
    }
    
    /**
     * Encrypt tokens using the dedicated FLOSC token secret (§5)
     * 
     * @param array $tokens Token data
     * @return string Encrypted string
     */
    private function encrypt_tokens($tokens) {
        $json = wp_json_encode($tokens);
        $key = flosc_token_secret(); // §5: dedicated secret, not the auth salt
        
        // Simple XOR encryption with base64 encoding
        $encrypted = '';
        for ($i = 0; $i < strlen($json); $i++) {
            $encrypted .= chr(ord($json[$i]) ^ ord($key[$i % strlen($key)]));
        }
        
        return base64_encode($encrypted);
    }
    
    /**
     * Decrypt tokens
     * 
     * @param string $encrypted Encrypted string
     * @return array Token data
     */
    private function decrypt_tokens($encrypted) {
        $encrypted = base64_decode($encrypted);
        $key = flosc_token_secret(); // §5: dedicated secret, not the auth salt
        
        $decrypted = '';
        for ($i = 0; $i < strlen($encrypted); $i++) {
            $decrypted .= chr(ord($encrypted[$i]) ^ ord($key[$i % strlen($key)]));
        }
        
        return json_decode($decrypted, true) ?: array();
    }
    
    /**
     * Get linked account info for display
     * 
     * @param int $user_id WordPress user ID
     * @param string $provider_id SSO provider ID
     * @return array|false Account info or false
     */
    public function get_linked_account_info($user_id, $provider_id) {
        if (!$this->is_provider_linked($user_id, $provider_id)) {
            return false;
        }
        
        $data = get_user_meta($user_id, self::META_PREFIX . $provider_id . '_data', true);
        $linked_at = get_user_meta($user_id, self::META_PREFIX . $provider_id . '_linked_at', true);
        
        return array(
            'provider_id' => $provider_id,
            'name'        => isset($data['name']) ? $data['name'] : '',
            'email'       => isset($data['email']) ? $data['email'] : '',
            'avatar'      => isset($data['avatar']) ? $data['avatar'] : '',
            'linked_at'   => $linked_at,
        );
    }
    
    /**
     * Get SSO avatar for user profile
     * 
     * @param int $user_id WordPress user ID
     * @return string|false Avatar URL or false
     */
    public function get_sso_avatar($user_id) {
        // Try stored avatar first
        $avatar = get_user_meta($user_id, self::META_PREFIX . 'avatar', true);
        if ($avatar) {
            return $avatar;
        }
        
        // Try linked provider avatars
        $providers = $this->get_linked_providers($user_id);
        foreach ($providers as $provider_id) {
            $data = get_user_meta($user_id, self::META_PREFIX . $provider_id . '_data', true);
            if (!empty($data['avatar'])) {
                return $data['avatar'];
            }
        }
        
        return false;
    }
}
