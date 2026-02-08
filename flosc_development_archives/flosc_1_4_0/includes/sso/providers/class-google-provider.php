<?php
/**
 * Google SSO Provider
 * 
 * Implements Google OAuth2 login for FLOSC.
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
 * Google Provider Class
 */
class Google_Provider extends SSO_Provider_Base {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->provider_id = 'google';
        $this->provider_name = 'Google';
        $this->provider_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/></svg>';
        
        $this->auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
        $this->token_url = 'https://oauth2.googleapis.com/token';
        $this->user_info_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
        
        $this->scopes = array(
            'openid',
            'email',
            'profile',
        );
        
        parent::__construct();
    }
    
    /**
     * Customize authorization parameters for Google
     * 
     * @param array $params Default parameters
     * @return array Modified parameters
     */
    protected function customize_auth_params($params) {
        // Add Google-specific parameters
        $params['access_type'] = 'offline';  // Get refresh token
        $params['prompt'] = 'select_account'; // Always show account selector
        
        return $params;
    }
    
    /**
     * Normalize Google user data to standard format
     * 
     * @param array $raw_data Raw user data from Google
     * @return array Normalized user data
     */
    protected function normalize_user_data($raw_data) {
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
            'background' => '#ffffff',
            'text'       => '#757575',
            'border'     => '#dadce0',
        );
    }
    
    /**
     * Get setup instructions for Google OAuth
     * 
     * @return string HTML instructions
     */
    public function get_setup_instructions() {
        $callback_url = $this->get_callback_url();
        
        return '
        <div class="flosc-sso-setup-instructions">
            <h4>Google OAuth Setup Instructions</h4>
            <ol>
                <li>Go to the <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></li>
                <li>Create a new project or select an existing one</li>
                <li>Navigate to "APIs & Services" → "Credentials"</li>
                <li>Click "Create Credentials" → "OAuth client ID"</li>
                <li>Select "Web application" as the application type</li>
                <li>Add the following Authorized redirect URI:
                    <code class="flosc-code-block">' . esc_html($callback_url) . '</code>
                </li>
                <li>Click "Create" to generate your Client ID and Client Secret</li>
                <li>Copy the Client ID and Client Secret into the fields above</li>
            </ol>
            <p><strong>Note:</strong> You may need to configure the OAuth consent screen first. Set it to "External" for production use.</p>
        </div>';
    }
}
