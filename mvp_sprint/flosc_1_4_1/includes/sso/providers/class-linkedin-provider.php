<?php
/**
 * LinkedIn SSO Provider
 * 
 * Implements LinkedIn Sign In for FLOSC.
 * Uses LinkedIn OAuth 2.0 with OpenID Connect.
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
 * LinkedIn Provider Class
 */
class LinkedIn_Provider extends SSO_Provider_Base {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->provider_id = 'linkedin';
        $this->provider_name = 'LinkedIn';
        $this->provider_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#0a66c2"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
        
        $this->auth_url = 'https://www.linkedin.com/oauth/v2/authorization';
        $this->token_url = 'https://www.linkedin.com/oauth/v2/accessToken';
        $this->user_info_url = 'https://api.linkedin.com/v2/userinfo';
        
        // Use OpenID Connect scopes (replaces legacy r_liteprofile, r_emailaddress)
        $this->scopes = array(
            'openid',
            'profile',
            'email',
        );
        
        parent::__construct();
    }
    
    /**
     * Get user info from LinkedIn
     * 
     * @param string $access_token OAuth access token
     * @return array|WP_Error User data or error
     */
    public function get_user_info($access_token) {
        // LinkedIn now supports OpenID Connect userinfo endpoint
        $response = wp_remote_get($this->user_info_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new \WP_Error(
                'user_info_error',
                isset($body['error_description']) ? $body['error_description'] : $body['error']
            );
        }
        
        return $this->normalize_user_data($body);
    }
    
    /**
     * Normalize LinkedIn user data to standard format
     * 
     * @param array $raw_data Raw user data from LinkedIn
     * @return array Normalized user data
     */
    protected function normalize_user_data($raw_data) {
        // OpenID Connect format
        return array(
            'provider_id'    => $raw_data['sub'] ?? '',
            'email'          => $raw_data['email'] ?? '',
            'email_verified' => $raw_data['email_verified'] ?? false,
            'name'           => $raw_data['name'] ?? '',
            'first_name'     => $raw_data['given_name'] ?? '',
            'last_name'      => $raw_data['family_name'] ?? '',
            'avatar'         => $raw_data['picture'] ?? '',
            'locale'         => $raw_data['locale'] ?? '',
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
        return $raw_data['sub'] ?? '';
    }
    
    /**
     * Get button colors
     * 
     * @return array
     */
    public function get_button_colors() {
        return array(
            'background' => '#0a66c2',
            'text'       => '#ffffff',
            'border'     => '#0a66c2',
        );
    }
    
    /**
     * Get setup instructions for LinkedIn Login
     * 
     * @return string HTML instructions
     */
    public function get_setup_instructions() {
        $callback_url = $this->get_callback_url();
        
        return '
        <div class="flosc-sso-setup-instructions">
            <h4>LinkedIn Sign In Setup Instructions</h4>
            <ol>
                <li>Go to <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developer Portal</a></li>
                <li>Click "Create app":
                    <ul>
                        <li>Enter app name</li>
                        <li>Associate with a LinkedIn Page (required)</li>
                        <li>Upload an app logo</li>
                        <li>Agree to terms and create</li>
                    </ul>
                </li>
                <li>In the "Auth" tab:
                    <ul>
                        <li>Copy <strong>Client ID</strong> → Client ID above</li>
                        <li>Copy <strong>Client Secret</strong> → Client Secret above</li>
                        <li>Add Authorized redirect URL:<br>
                            <code class="flosc-code-block">' . esc_html($callback_url) . '</code>
                        </li>
                    </ul>
                </li>
                <li>In the "Products" tab:
                    <ul>
                        <li>Request access to "Sign In with LinkedIn using OpenID Connect"</li>
                        <li>This is usually auto-approved</li>
                    </ul>
                </li>
            </ol>
            <p><strong>Note:</strong> LinkedIn requires your app to be associated with a company LinkedIn Page.</p>
            <p><strong>API Note:</strong> This plugin uses LinkedIn\'s OpenID Connect implementation (recommended). Legacy APIs are deprecated.</p>
        </div>';
    }
}
