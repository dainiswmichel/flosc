<?php
if (!defined('ABSPATH')) {
    exit;
}

trait FLOSC_Admin_Trait {
    /**
     * Admin Menu
     * v05_02: Menu shortcuts to Settings tabs in logical order
     */
    public function add_admin_menu() {
        // v1.2.8: Simplified - Settings page IS the main page
        // IVR file dropdown selects which flow to edit

        // Main FLOSC menu - goes directly to Settings
        add_menu_page(
            'FLOSC',
            'FLOSC',
            'edit_others_posts',
            'flosc-settings',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            30
        );

        // 1. Settings (main page - all tabs, flow selector at top)
        add_submenu_page(
            'flosc-settings',
            'Settings',
            'Settings',
            'edit_others_posts',
            'flosc-settings',
            [$this, 'render_admin_page']
        );

        // 1a. Flow (shortcut to Flow tab)
        add_submenu_page(
            'flosc-settings',
            'Flow',
            '🗺 Flow',
            'manage_options',
            'flosc-flow',
            [$this, 'redirect_to_flow_tab']
        );

        // Identity
        add_submenu_page(
            'flosc-settings',
            'Identity',
            'Identity',
            'manage_options',
            'flosc-identity',
            [$this, 'redirect_to_identity_tab']
        );

        // 2. IVR Messages (redirect to Settings > IVR tab)
        add_submenu_page(
            'flosc-settings',
            'IVR Management',
            'IVR Management',
            'manage_options',
            'flosc-ivr-messages',
            [$this, 'redirect_to_ivr_tab']
        );

        // AutoPrompt Panel
        add_submenu_page(
            'flosc-settings',
            'AutoPrompt Panel',
            'AutoPrompt Panel',
            'manage_options',
            'flosc-autoprompts',
            [$this, 'redirect_to_autoprompts_tab']
        );

        // Content (levels + groups + pool; replaces Member Levels + Lessons)
        add_submenu_page(
            'flosc-settings',
            'Content',
            'Content',
            'manage_options',
            'flosc-content',
            [$this, 'redirect_to_content_tab']
        );
        // Legacy submenu slugs → Content
        add_submenu_page(
            null,
            'Member Levels',
            'Member Levels',
            'manage_options',
            'flosc-member-levels',
            [$this, 'redirect_to_content_tab']
        );

        // Trajectories
        add_submenu_page(
            'flosc-settings',
            'Trajectories',
            'Trajectories',
            'manage_options',
            'flosc-trajectories',
            [$this, 'redirect_to_trajectories_tab']
        );

        // Offers
        add_submenu_page(
            'flosc-settings',
            'Offers',
            'Offers',
            'manage_options',
            'flosc-offers',
            [$this, 'redirect_to_offers_tab']
        );

        // Register & Login
        add_submenu_page(
            'flosc-settings',
            'Register & Login',
            'Register & Login',
            'manage_options',
            'flosc-login',
            [$this, 'redirect_to_login_tab']
        );

        // 3. Chat Styling
        add_submenu_page(
            'flosc-settings',
            'Style',
            'Style',
            'manage_options',
            'flosc-chat-style',
            [$this, 'redirect_to_style_tab']
        );

        // v1.8.0: UI & Navigation
        add_submenu_page(
            'flosc-settings',
            'UI & Nav',
            'UI & Nav',
            'manage_options',
            'flosc-ui-navigation',
            [$this, 'render_ui_navigation_page']
        );

        // 4. AI Configuration
        add_submenu_page(
            'flosc-settings',
            'AI',
            'AI',
            'manage_options',
            'flosc-ai-config',
            [$this, 'redirect_to_ai_tab']
        );

        // Token Management
        add_submenu_page(
            'flosc-settings',
            'Token Management',
            'Token Management',
            'manage_options',
            'flosc-token-management',
            [$this, 'redirect_to_token_management_tab']
        );

        // Concierge
        add_submenu_page(
            'flosc-settings',
            'Concierge',
            'Concierge',
            'manage_options',
            'flosc-concierge',
            [$this, 'redirect_to_concierge_tab']
        );

        // Quiz
        add_submenu_page(
            'flosc-settings',
            'Quiz Settings',
            'Quiz',
            'manage_options',
            'flosc-quiz',
            [$this, 'redirect_to_quiz_tab']
        );

        // Email
        add_submenu_page(
            'flosc-settings',
            'Email Settings',
            'Email',
            'manage_options',
            'flosc-email',
            [$this, 'redirect_to_email_tab']
        );

        // Contact Form
        add_submenu_page(
            'flosc-settings',
            'Contact Form',
            'Contact Form',
            'manage_options',
            'flosc-contact-form',
            [$this, 'redirect_to_contact_form_tab']
        );

        // Payments
        add_submenu_page(
            'flosc-settings',
            'Payments',
            'Payments',
            'manage_options',
            'flosc-payments',
            [$this, 'redirect_to_payments_tab']
        );

        // Legacy Lessons slug → Content (hidden from menu)
        add_submenu_page(
            null,
            'Lessons',
            'Lessons',
            'manage_options',
            'flosc-lessons',
            [$this, 'redirect_to_content_tab']
        );

        // SSO / Social Login (v1.4.0)
        add_submenu_page(
            'flosc-settings',
            'SSO / Social Login',
            'SSO',
            'manage_options',
            'flosc-sso',
            [$this, 'redirect_to_sso_tab']
        );

        // Engagement (journey parameters + profile summary; between SSO and Chat Logs)
        add_submenu_page(
            'flosc-settings',
            'Engagement',
            'Engagement',
            'manage_options',
            'flosc-engagement',
            [$this, 'redirect_to_engagement_tab']
        );

        // Chat Logs
        add_submenu_page(
            'flosc-settings',
            'Chat Logs',
            'Chat Logs',
            'manage_options',
            'flosc-chat-logs',
            [$this, 'redirect_to_chat_logs_tab']
        );

        // Administration (global account/debug controls)
        add_submenu_page(
            'flosc-settings',
            'Administration',
            'Administration',
            'manage_options',
            'flosc-administration',
            [$this, 'redirect_to_administration_tab']
        );

        // Docs
        add_submenu_page(
            'flosc-settings',
            'Docs',
            '📖 Docs',
            'manage_options',
            'flosc-docs',
            [$this, 'redirect_to_docs_tab']
        );

        // DA1 Catalog — standalone page (not flow-specific)
        add_submenu_page(
            'flosc-settings',
            'DA1 Catalog',
            '<b>DA1</b>',
            'manage_options',
            'flosc-da1',
            [$this, 'redirect_to_da1_tab']
        );
    }

    /**
     * Register Settings
     */
    public function register_settings() {
        // Pass 2: every option has an explicit sanitizer type (no bare-name → implicit text).
        $this->register_settings_group(array(
            'flosc_app_slug' => 'text',
            'flosc_custom_domain' => 'text',
            'flosc_product_name' => 'text',
            'flosc_product_title' => 'text',
            'flosc_product_tagline' => 'text',
            'flosc_share_text' => 'text',
            'flosc_email_subject' => 'text',
            'flosc_email_body' => 'textarea',
            'flosc_account_plan' => 'text',
            'flosc_account_purchases_manual' => 'text',
            'flosc_ai_provider' => 'text',
            'flosc_openai_api_key' => 'secret',
            'flosc_anthropic_api_key' => 'secret',
            'flosc_xai_api_key' => 'secret',
            'flosc_ai_openai_model' => 'text',
            'flosc_ai_anthropic_model' => 'text',
            'flosc_ai_xai_model' => 'text',
            'flosc_ai_temperature' => 'text',
            'flosc_ai_max_tokens' => 'text',
            'flosc_stt_provider' => 'text',
            'flosc_assemblyai_api_key' => 'secret',
            'flosc_custom_stt_endpoint' => 'url',
            'flosc_buddyboss_group_id' => 'text',
            'flosc_lessons_category' => 'text',
            'flosc_oto_offer_id' => 'text',
            'flosc_free_lesson_mode' => 'text',
            'flosc_free_lesson_count' => 'text',
            'flosc_free_lesson_proportion' => 'text',
            'flosc_guest_access_days' => 'text',
            'flosc_stripe_enabled' => 'bool',
            'flosc_stripe_mode' => 'text',
            'flosc_stripe_test_pk' => 'text',
            'flosc_stripe_test_sk' => 'secret',
            'flosc_stripe_live_pk' => 'text',
            'flosc_stripe_live_sk' => 'secret',
            'flosc_clickbank_enabled' => 'bool',
            'flosc_clickbank_mode' => 'text',
            'flosc_clickbank_vendor' => 'text',
            'flosc_clickbank_secret' => 'secret',
            'flosc_clickbank_product' => 'text',
            'flosc_clickbank_offer_id' => 'text',
            'flosc_clickbank_access_level' => 'text',
            'flosc_paypal_mode' => 'text',
            'flosc_paypal_client_id' => 'text',
            'flosc_paypal_secret' => 'secret',
            'flosc_paypal_webhook_id' => 'text',
            'flosc_chat_style_preset' => 'text',
            'flosc_chat_style_bubble' => 'text',
            'flosc_chat_style_font' => 'text',
        ));

        $this->register_settings_group(array(
            'flosc_product_logo' => 'url',
            'flosc_primary_color' => 'hex',
            'flosc_login_destination' => 'url',
            'flosc_profile_bar' => 'array',
            'flosc_visitor_menu_items' => 'array',
            'flosc_guest_menu_items' => 'array',
            'flosc_member_menu_items' => 'array',
            'flosc_debug_mode' => 'bool',
            'flosc_enabled_quizzes' => 'array',
            'flosc_wpq_integration' => 'bool',
            'flosc_ld_integration' => 'bool',
            'flosc_qsm_integration' => 'bool',
            'flosc_chat_style_scale' => 'text',
            'flosc_chat_style_accent' => 'hex',
            'flosc_sso_google_enabled' => 'bool',
            'flosc_sso_google_client_id' => 'text',
            'flosc_sso_google_client_secret' => 'secret',
            'flosc_sso_apple_enabled' => 'bool',
            'flosc_sso_apple_client_id' => 'text',
            'flosc_sso_apple_client_secret' => 'secret',
            'flosc_sso_facebook_enabled' => 'bool',
            'flosc_sso_facebook_client_id' => 'text',
            'flosc_sso_facebook_client_secret' => 'secret',
            'flosc_sso_microsoft_enabled' => 'bool',
            'flosc_sso_microsoft_client_id' => 'text',
            'flosc_sso_microsoft_client_secret' => 'secret',
            'flosc_sso_linkedin_enabled' => 'bool',
            'flosc_sso_linkedin_client_id' => 'text',
            'flosc_sso_linkedin_client_secret' => 'secret',
            'flosc_sso_apple_team_id' => 'text',
            'flosc_sso_apple_key_id' => 'text',
            // PEM: pass-through secret sanitizer (not sanitize_textarea_field — Jul email / Pass 2).
            'flosc_sso_apple_private_key' => 'secret',
            'flosc_ai_base_prompt' => 'textarea',
            'flosc_ai_personality_name' => 'textarea',
            'flosc_ai_personality_role' => 'textarea',
            'flosc_ai_personality_traits' => 'textarea',
            'flosc_ai_mission' => 'textarea',
            'flosc_ai_boundaries' => 'textarea',
            'flosc_ai_context_awareness' => 'textarea',
            'flosc_ai_freeline_restrictions' => 'textarea',
            'flosc_ai_member_access' => 'textarea',
            // Global communication-token economics (integer/rational, saved by Payments tab)
            'flosc_tokens_communication_tokens_per_message' => 'text',
            'flosc_tokens_nominal_millicents_per_token_numerator' => 'text',
            'flosc_tokens_nominal_millicents_per_token_denominator' => 'text',
            'flosc_tokens_real_millicents_per_token_numerator' => 'text',
            'flosc_tokens_real_millicents_per_token_denominator' => 'text',
        ));

        // User Profile Bar (v1.8.0: unified 3-state bar replaces v1.7.8 visitor-only settings)

        // v1.8.0: UI & Navigation

        // v1.7.7: Removed duplicate AI settings registration (was under both flosc_settings and flosc_ai_settings)
        // All settings now live under flosc_settings only

        // STT Provider

        // Quiz Type System

        // Third-party quiz plugin integrations (v9.3.4)

        // Register quiz content settings for each quiz type dynamically
        $quiz_types = FLOSC_Quiz_Registry::get_all_quizzes();
        foreach ($quiz_types as $quiz_id => $quiz_type) {
            $this->register_setting_value('flosc_quiz_content_' . $quiz_id, 'textarea');

            $settings_fields = $quiz_type->get_settings_fields();
            foreach ($settings_fields as $field_key => $field_config) {
                $option_name = 'flosc_quiz_' . $quiz_id . '_' . $field_key;
                $field_type  = $field_config['type'] ?? 'text';

                if ($field_type === 'select') {
                    $this->register_select_setting_value(
                        $option_name,
                        array_keys($field_config['options'] ?? array())
                    );
                    continue;
                }

                if ($field_type === 'checkbox') {
                    $field_type = 'bool';
                }
                // Pass 2: map password/secret field types to secret pass-through sanitizer.
                if ($field_type === 'password' || $field_type === 'secret') {
                    $field_type = 'secret';
                }

                $this->register_setting_value($option_name, $field_type);
            }

            $templates = $quiz_type->get_default_response_templates();
            foreach (array_keys($templates) as $template_key) {
                $this->register_setting_value('flosc_quiz_' . $quiz_id . '_template_' . $template_key, 'textarea');
            }
        }

        // v1.7.7: Removed auto-seeded PayPal sandbox credentials (security)
        // PayPal credentials must be configured via Settings > FLOSC > PayPal
    }

    /**
     * Register one or more settings with a shared sanitizer.
     *
     * @param array $settings List of setting names or name => sanitizer type pairs.
     */
    private function register_settings_group($settings) {
        foreach ($settings as $key => $value) {
            if (is_int($key)) {
                $this->register_setting_value($value, 'text');
            } else {
                $this->register_setting_value($key, $value);
            }
        }
    }

    /**
     * Register a single setting with the appropriate sanitizer.
     *
     * @param string $option_name Setting name.
     * @param string $sanitize_type text, textarea, url, hex, bool, checkbox (alias), or array.
     *                              Select fields should use register_select_setting_value().
     */
    private function register_setting_value($option_name, $sanitize_type = 'text') {
        switch ($sanitize_type) {
            case 'textarea':
                $sanitize_callback = array($this, 'sanitize_textarea_setting');
                break;
            case 'url':
                $sanitize_callback = array($this, 'sanitize_url_setting');
                break;
            case 'hex':
                $sanitize_callback = array($this, 'sanitize_hex_setting');
                break;
            case 'bool':
            case 'checkbox':
                $sanitize_callback = array($this, 'sanitize_bool_setting');
                break;
            case 'array':
                $sanitize_callback = array($this, 'sanitize_array_setting');
                break;
            case 'secret':
                // Named public callback (Pass 3): empty-preserve via current_filter option name.
                $sanitize_callback = array( $this, 'sanitize_secret_setting' );
                break;
            default:
                $sanitize_callback = array($this, 'sanitize_text_setting');
                break;
        }

        register_setting(
            'flosc_settings',
            $option_name,
            array(
                'type'              => $this->get_setting_registration_type($sanitize_type),
                'sanitize_callback' => $sanitize_callback,
                'default'           => $this->get_setting_default_value($sanitize_type),
            )
        );
    }

    /**
     * Register a select setting with whitelist validation.
     *
     * @param string $option_name Setting name.
     * @param array  $allowed_keys Allowed option keys from get_settings_fields()['options'].
     */
    private function register_select_setting_value($option_name, $allowed_keys) {
        $allowed_keys = array_values(array_filter(array_map('sanitize_key', (array) $allowed_keys)));
        $default_value = $allowed_keys[0] ?? '';

        register_setting(
            'flosc_settings',
            $option_name,
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($value) use ($allowed_keys, $default_value) {
                    if (empty($allowed_keys)) {
                        return '';
                    }

                    $sanitized = sanitize_key(wp_unslash((string) $value));
                    if ($sanitized !== '' && in_array($sanitized, $allowed_keys, true)) {
                        return $sanitized;
                    }

                    return $default_value;
                },
                'default'           => $default_value,
            )
        );
    }

    /**
     * Map sanitizer type to register_setting type metadata.
     *
     * @param string $sanitize_type Sanitizer category.
     * @return string
     */
    private function get_setting_registration_type($sanitize_type) {
        if ($sanitize_type === 'array') {
            return 'array';
        }

        if ($sanitize_type === 'bool') {
            return 'integer';
        }

        return 'string';
    }

    /**
     * Default values aligned to sanitizer type.
     *
     * @param string $sanitize_type Sanitizer category.
     * @return mixed
     */
    private function get_setting_default_value($sanitize_type) {
        if ($sanitize_type === 'array') {
            return array();
        }

        if ($sanitize_type === 'bool') {
            return 0;
        }

        return '';
    }

    /**
     * Sanitize a text setting.
     *
     * MUST stay public: registered as a register_setting() sanitize_callback,
     * which WordPress core invokes from outside this class via the
     * sanitize_option_{$option} filter. A private method here fatals
     * ("Call to private method") on every settings save. The same applies to
     * the five sibling sanitizers below — do not narrow their visibility.
     *
     * @param mixed $value Raw value.
     * @return string|array
     */
    public function sanitize_text_setting($value) {
        if (is_array($value)) {
            return array_map(array($this, 'sanitize_text_setting'), $value);
        }

        return sanitize_text_field(wp_unslash((string) $value));
    }

    /**
     * Pass-through for API keys and payment secrets (do not use sanitize_text_field).
     *
     * Empty string on save keeps the stored option (password fields submit empty when
     * unchanged). To clear a secret deliberately, operators must use a dedicated clear
     * action or overwrite with a new non-empty value — blank resave is not a wipe.
     *
     * Resolves the option name from sanitize_option_{$option} when registered as a
     * named register_setting sanitize_callback (Pass 3).
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public function sanitize_secret_setting($value) {
        $option_name = '';
        $filter      = current_filter();
        if (is_string($filter) && 0 === strpos($filter, 'sanitize_option_')) {
            $option_name = substr($filter, strlen('sanitize_option_'));
        }

        if (!is_string($value)) {
            return $option_name !== '' ? (string) get_option($option_name, '') : '';
        }

        $value = wp_unslash($value);
        if ('' === $value) {
            return $option_name !== '' ? (string) get_option($option_name, '') : '';
        }

        return $value;
    }

    /**
     * Sanitize a textarea setting.
     *
     * @param mixed $value Raw value.
     * @return string|array
     */
    public function sanitize_textarea_setting($value) {
        if (is_array($value)) {
            return array_map(array($this, 'sanitize_textarea_setting'), $value);
        }

        return sanitize_textarea_field(wp_unslash((string) $value));
    }

    /**
     * Sanitize a URL setting.
     *
     * @param mixed $value Raw value.
     * @return string|array
     */
    public function sanitize_url_setting($value) {
        if (is_array($value)) {
            return array_map(array($this, 'sanitize_url_setting'), $value);
        }

        return esc_url_raw(wp_unslash((string) $value));
    }

    /**
     * Sanitize a hex color setting.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public function sanitize_hex_setting($value) {
        $sanitized = sanitize_hex_color(wp_unslash((string) $value));
        return $sanitized ? $sanitized : '';
    }

    /**
     * Sanitize a boolean setting.
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public function sanitize_bool_setting($value) {
        return !empty($value) ? 1 : 0;
    }

    /**
     * Sanitize nested array settings.
     *
     * @param mixed $value Raw value.
     * @return array|string
     */
    public function sanitize_array_setting($value) {
        if (!is_array($value)) {
            return sanitize_text_field(wp_unslash((string) $value));
        }

        $sanitized = array();
        foreach ($value as $key => $item) {
            $sanitized_key = is_string($key) ? sanitize_key($key) : $key;
            $sanitized[$sanitized_key] = is_array($item)
                ? $this->sanitize_array_setting($item)
                : sanitize_text_field(wp_unslash((string) $item));
        }

        return $sanitized;
    }

    /**
     * Render Admin Pages
     */
    public function render_admin_page() {
        // Site admins always have FLOSC settings access.
        if (!current_user_can('manage_options')) {
            // Delegated floscEditors must be Editor+ and assigned to at least one flow.
            if (!current_user_can('edit_others_posts')) {
                wp_die(esc_html__('You do not have permission to access FLOSC settings.', 'flosc'));
            }

            $flosc_user_flows = flosc_flows()->get_user_flows(get_current_user_id());
            if (empty($flosc_user_flows)) {
                wp_die(esc_html__('You do not have FLOSC flow assignments yet. Ask a site admin to assign you in Administration for a flow.', 'flosc'));
            }
        }

        include FLOSC_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Check whether current user can manage chat logs for a specific flow.
     * Site admins are always allowed; delegated floscEditors require flow assignment.
     *
     * @param string $flow_id Flow stem (for example flow_ivr).
     * @return bool
     */
    private function can_manage_flow_chat_logs($flow_id) {
        if (current_user_can('manage_options')) {
            return true;
        }

        if (!current_user_can('edit_others_posts')) {
            return false;
        }

        $flow_id = sanitize_key((string) $flow_id);
        if ($flow_id === '') {
            return false;
        }

        return flosc_flows()->can_access_flow_admin($flow_id, get_current_user_id());
    }

    /**
     * v1.2.2: Render Flows list page
     */
    public function render_flows_page() {
        include FLOSC_PLUGIN_DIR . 'admin/flows.php';
    }

    /**
     * v1.2.2: Render Flow edit page
     */
    public function render_flow_edit_page() {
        include FLOSC_PLUGIN_DIR . 'admin/flow-edit.php';
    }

    /**
     * v1.0.4: Enqueue admin assets (TASK-006)
     * Loads flosc-admin.css on FLOSC admin pages
     */
    public function enqueue_admin_assets($hook) {
        // §12: Post-visibility metabox styles render on the post editor (post.php / post-new.php),
        // which is a different screen than the FLOSC settings pages. Enqueue them there via an
        // inline-only style handle instead of echoing a <style> tag inside the metabox markup.
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_register_style('flosc-metabox', false, [], FLOSC_VERSION);
            wp_enqueue_style('flosc-metabox');
            wp_add_inline_style('flosc-metabox',
                '.flosc-post-visibility-meta-box .flosc-description { color: #666; font-size: 12px; margin-top: 4px; }' .
                '.flosc-post-visibility-meta-box .flosc-protected-notice { background: #fff3cd; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px; }' .
                '.flosc-post-visibility-meta-box .flosc-protection-options label { display: block; margin: 6px 0; padding: 6px 8px; border-radius: 4px; cursor: pointer; }' .
                '.flosc-post-visibility-meta-box .flosc-protection-options label:hover { background: #f0f0f1; }' .
                '.flosc-post-visibility-meta-box .flosc-protection-options .option-desc { color: #666; font-size: 11px; display: block; margin-left: 22px; }'
            );
            // Concierge metabox rules ride the same handle. They must be added
            // HERE (admin_enqueue_scripts) and not inside render_meta_box():
            // by metabox render time the head styles have already printed, and
            // inline data attached to a printed handle is silently discarded.
            wp_add_inline_style('flosc-metabox',
                '.flosc-cncrg-row { margin: 0 0 12px; }' .
                '.flosc-cncrg-row label { display: block; font-weight: 600; margin: 0 0 4px; }' .
                '.flosc-cncrg-row input, .flosc-cncrg-row textarea { width: 100%; font-family: ui-monospace, Menlo, Consolas, monospace; }' .
                '.flosc-cncrg-max-tries { width: 80px !important; }'
            );
            return;
        }

        // Only load on FLOSC admin pages
        // v1.2.8: Simplified - just check for 'flosc'
        if (strpos($hook, 'flosc') === false &&
            $hook !== 'toplevel_page_flosc-settings') {
            return;
        }

        $css_path = FLOSC_PLUGIN_DIR . 'assets/css/flosc-admin.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'flosc-admin',
                FLOSC_PLUGIN_URL . 'assets/css/flosc-admin.css',
                [],
                filemtime($css_path)
            );
        }

        // §12: Footer-printed script handle (no src) that FLOSC admin page templates
        // attach their page JS to via wp_add_inline_script('flosc-admin', ...), instead
        // of echoing raw <script> tags. Registering it here (on admin_enqueue_scripts)
        // means the handle is enqueued before render, so inline JS added during the page
        // body still prints in the admin footer. jQuery dep covers the existing jQuery use.
        wp_register_script('flosc-admin', false, ['jquery'], FLOSC_VERSION, true);
        wp_enqueue_script('flosc-admin');

        $flosc_admin_events_js_path = FLOSC_PLUGIN_DIR . 'assets/js/flosc-admin-events.js';
        if (file_exists($flosc_admin_events_js_path)) {
            wp_enqueue_script(
                'flosc-admin-events',
                FLOSC_PLUGIN_URL . 'assets/js/flosc-admin-events.js',
                ['flosc-admin'],
                filemtime($flosc_admin_events_js_path),
                true
            );
        }

        // Dedicated AutoPrompts admin runtime (externalized from inline tab template JS).
        $flosc_tab_raw = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
        $flosc_tab     = is_string( $flosc_tab_raw ) ? sanitize_key( wp_unslash( $flosc_tab_raw ) ) : '';
        if ($flosc_tab === 'autoprompts') {
            $flosc_autoprompts_js_path = FLOSC_PLUGIN_DIR . 'assets/js/flosc-autoprompts-admin.js';
            if (file_exists($flosc_autoprompts_js_path)) {
                wp_enqueue_script(
                    'flosc-autoprompts-admin',
                    FLOSC_PLUGIN_URL . 'assets/js/flosc-autoprompts-admin.js',
                    ['flosc-admin'],
                    filemtime($flosc_autoprompts_js_path),
                    true
                );
            }
        }

        if ($flosc_tab === 'email') {
            $flosc_email_css_path = FLOSC_PLUGIN_DIR . 'assets/css/flosc-email.css';
            if (file_exists($flosc_email_css_path)) {
                wp_enqueue_style(
                    'flosc-email',
                    FLOSC_PLUGIN_URL . 'assets/css/flosc-email.css',
                    ['flosc-admin'],
                    filemtime($flosc_email_css_path)
                );
            }
        }

        // Debug mode badge
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            wp_add_inline_style('flosc-admin', '
                body.wp-admin:after {
                    content: "FLOSC DEBUG";
                    position: fixed;
                    bottom: 10px;
                    right: 10px;
                    background: #dc3545;
                    color: #fff;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: bold;
                    z-index: 9999;
                    opacity: 0.8;
                }
            ');
        }

        // Tame WordPress admin footer (#wpfooter) on FLOSC pages.
        // WP core uses position:fixed/absolute which causes the "Version X.X.X" text
        // to float over FLOSC admin content at various zoom levels.
        // Fix: make it flow normally in the document, properly positioned at the bottom.
        wp_add_inline_style('flosc-admin', '
            #wpfooter { position: static; padding: 15px 20px; text-align: right; }
        ');
    }

    /**
     * v8.0.0: Relabel right side of WP admin footer on FLOSC pages.
     * WordPress shows "Version 6.9.3" — we relabel to "WordPress 6.9.3 | FLOSC v8.0.0"
     * so it's clear what each version number refers to.
     * Only applies on FLOSC admin pages (checked via current screen).
     */
    public function relabel_admin_footer($text) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'flosc') !== false) {
            global $wp_version;
            return 'WordPress ' . esc_html($wp_version) . ' | FLOSC v' . esc_html(FLOSC_VERSION);
        }
        return $text;
    }

    /**
     * v8.0.0: Replace left-side "Thank you for creating with WordPress" with FLOSC branding
     * on FLOSC admin pages only.
     */
    public function relabel_admin_footer_left($text) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'flosc') !== false) {
            return '<span id="footer-thankyou">FLOSC &mdash; Flow-Oriented Sales Companion</span>';
        }
        return $text;
    }

    // Offers now integrated into main settings page

    // Payments now integrated into main settings page

    // AI Config now integrated into main settings page

    // AI Knowledge now integrated into main settings page

    // Chat Style now integrated into main settings page

    /**
     * Early admin_init redirects for FLOSC sidebar shortcuts.
     * Menu callbacks run after admin chrome starts; if headers are already sent,
     * wp_safe_redirect is a no-op and exit leaves a blank content pane
     * (seen on UI & Nav → page=flosc-ui-navigation).
     */
    public function maybe_redirect_flosc_admin_shortcuts() {
        if (!is_admin() || wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        // Read-only admin menu routing (capability-checked below). No nonce: GET page
        // slug only; never mutates options. filter_input avoids direct $_GET PHPCS noise.
        $page_raw = filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW);
        $page     = is_string($page_raw) ? sanitize_key(wp_unslash($page_raw)) : '';
        if ($page === '' || $page === 'flosc-settings') {
            return;
        }

        $map = [
            'flosc-flow'              => 'flow',
            'flosc-identity'          => 'identity',
            'flosc-ivr-messages'      => 'ivr-messages',
            'flosc-autoprompts'       => 'autoprompts',
            'flosc-content'           => 'content',
            'flosc-member-levels'     => 'content',
            'flosc-lessons'           => 'content',
            'flosc-trajectories'      => 'trajectories',
            'flosc-offers'            => 'offers',
            'flosc-login'             => 'login',
            'flosc-chat-style'        => 'style',
            'flosc-ui-navigation'     => 'ui',
            'flosc-ai-config'         => 'ai',
            'flosc-token-management'  => 'token-management',
            'flosc-concierge'         => 'concierge',
            'flosc-quiz'              => 'quiz',
            'flosc-email'             => 'email',
            'flosc-contact-form'      => 'contact-form',
            'flosc-payments'          => 'payments',
            'flosc-sso'               => 'sso',
            'flosc-engagement'        => 'engagement',
            'flosc-chat-logs'         => 'chat-logs',
            'flosc-administration'    => 'administration',
            'flosc-documentation'     => 'documentation',
            'flosc-docs'              => 'documentation',
            'flosc-da1'               => 'da1',
        ];

        if (!isset($map[$page])) {
            return;
        }

        // Same gate as Settings shell (site admin or delegated editor).
        if (!current_user_can('manage_options') && !current_user_can('edit_others_posts')) {
            return;
        }

        $tab = $map[$page];
        $args = [
            'page' => 'flosc-settings',
            'tab'  => $tab,
        ];
        $ivr_raw = filter_input(INPUT_GET, 'ivr', FILTER_UNSAFE_RAW);
        if (is_string($ivr_raw) && $ivr_raw !== '') {
            $args['ivr'] = sanitize_file_name(wp_unslash($ivr_raw));
        }
        $view_raw = filter_input(INPUT_GET, 'view', FILTER_UNSAFE_RAW);
        if (is_string($view_raw) && $view_raw !== '') {
            $view = sanitize_text_field(wp_unslash($view_raw));
            if (in_array($view, ['single', 'all'], true)) {
                $args['view'] = $view;
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Process FLOSC Settings form POSTs that redirect (Save, trajectory, concierge).
     * Must run on admin_init — render_admin_page already has headers sent.
     */
    public function maybe_process_flosc_settings_post() {
        if (!is_admin() || wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        $page_raw = filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW);
        $page     = is_string($page_raw) ? sanitize_key(wp_unslash($page_raw)) : '';
        if ($page !== 'flosc-settings') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside handlers
        $post = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];
        if ($post === []) {
            return;
        }

        // IVR new-flow upload: must redirect before admin chrome (Set-as-default class of bug).
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handler
        if (!empty($post['flosc_upload_ivr_file']) && !empty($_FILES['ivr_file_upload'])) {
            if (!function_exists('flosc_admin_handle_ivr_file_upload')) {
                require_once FLOSC_PLUGIN_DIR . 'admin/ivr-upload-handler.php';
            }
            flosc_admin_handle_ivr_file_upload();
            // Success exits inside handler. Failure: continue so the settings page can show errors.
            return;
        }

        $redirect_post_keys = [
            'flosc_save',
            'flosc_toggle_trajectory_post',
            'flosc_create_concierge_post',
            'flosc_create_trajectory_post',
        ];
        $hit = false;
        foreach ($redirect_post_keys as $key) {
            if (!empty($post[$key])) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return;
        }

        if (!empty($GLOBALS['flosc_settings_early_post_running'])) {
            return;
        }
        $GLOBALS['flosc_settings_early_post_running'] = true;

        // settings.php POST handlers call wp_safe_redirect + exit on success.
        // On failure they fall through; we return before any HTML is printed.
        include FLOSC_PLUGIN_DIR . 'admin/settings.php';

        $GLOBALS['flosc_settings_early_post_running'] = false;
    }

    /**
     * Admin-post: set current user's default FLOSC flow (Settings chrome).
     * Runs before any admin HTML so wp_safe_redirect works (not mid-render).
     */
    public function handle_set_default_flow() {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in.', 'flosc'));
        }

        check_admin_referer('flosc_set_default_flow', 'flosc_default_flow_nonce');

        $post = wp_unslash($_POST);
        $ivr  = isset($post['ivr']) ? sanitize_file_name((string) $post['ivr']) : '';
        $tab  = isset($post['tab']) ? sanitize_text_field((string) $post['tab']) : 'identity';
        $view = isset($post['view']) ? sanitize_text_field((string) $post['view']) : 'single';
        if (!in_array($view, ['single', 'all'], true)) {
            $view = 'single';
        }

        if ($ivr === '') {
            wp_die(esc_html__('Missing flow file.', 'flosc'));
        }

        $flow_id = sanitize_key(pathinfo($ivr, PATHINFO_FILENAME));
        if (!flosc_flows()->can_access_flow_admin($flow_id)) {
            wp_die(esc_html__('You do not have permission to set a default FLOSC flow.', 'flosc'));
        }

        // Same key as admin/settings.php selector.
        update_user_meta(get_current_user_id(), '_flosc_admin_default_ivr', $ivr);

        $redirect = add_query_arg(
            [
                'page'        => 'flosc-settings',
                'ivr'         => $ivr,
                'tab'         => $tab,
                'view'        => $view,
                'default_set' => '1',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Redirect to a FLOSC Settings tab. Prefer HTTP redirect; if headers already
     * sent, render the settings shell so the admin never gets a blank pane.
     *
     * @param string $tab Settings tab slug.
     */
    private function redirect_to_settings_tab($tab) {
        $tab = sanitize_key((string) $tab);
        if ($tab === '') {
            $tab = 'flow';
        }

        $args = [
            'page' => 'flosc-settings',
            'tab'  => $tab,
        ];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['ivr'])) {
            $args['ivr'] = sanitize_file_name(wp_unslash((string) $_GET['ivr']));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['view'])) {
            $view = sanitize_text_field(wp_unslash((string) $_GET['view']));
            if (in_array($view, ['single', 'all'], true)) {
                $args['view'] = $view;
            }
        }

        $url = add_query_arg($args, admin_url('admin.php'));
        if (!headers_sent()) {
            wp_safe_redirect($url);
            exit;
        }

        // Fallback: paint Settings with the requested tab (no blank exit).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $_GET['page'] = 'flosc-settings';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $_GET['tab'] = $tab;
        $this->render_admin_page();
    }

    /**
     * Redirect handlers for tab shortcuts - ALL menu items go to Settings tabs
     */
    public function redirect_to_product_tab() {
        $this->redirect_to_settings_tab('product');
    }

    public function redirect_to_flow_tab() {
        $this->redirect_to_settings_tab('flow');
    }

    public function redirect_to_identity_tab() {
        $this->redirect_to_settings_tab('identity');
    }

    public function redirect_to_ivr_tab() {
        $this->redirect_to_settings_tab('ivr-messages');
    }

    public function redirect_to_autoprompts_tab() {
        $this->redirect_to_settings_tab('autoprompts');
    }

    public function redirect_to_content_tab() {
        $this->redirect_to_settings_tab('content');
    }

    public function redirect_to_member_levels_tab() {
        $this->redirect_to_content_tab();
    }

    public function redirect_to_trajectories_tab() {
        $this->redirect_to_settings_tab('trajectories');
    }

    public function redirect_to_style_tab() {
        $this->redirect_to_settings_tab('style');
    }

    public function redirect_to_ai_tab() {
        $this->redirect_to_settings_tab('ai');
    }

    public function redirect_to_token_management_tab() {
        $this->redirect_to_settings_tab('token-management');
    }

    public function redirect_to_concierge_tab() {
        $this->redirect_to_settings_tab('concierge');
    }

    public function redirect_to_quiz_tab() {
        $this->redirect_to_settings_tab('quiz');
    }

    public function redirect_to_email_tab() {
        $this->redirect_to_settings_tab('email');
    }

    public function redirect_to_contact_form_tab() {
        $this->redirect_to_settings_tab('contact-form');
    }

    public function redirect_to_ai_knowledge_tab() {
        $this->redirect_to_settings_tab('ai');
    }

    public function redirect_to_login_tab() {
        $this->redirect_to_settings_tab('login');
    }

    public function redirect_to_offers_tab() {
        $this->redirect_to_settings_tab('offers');
    }

    public function redirect_to_payments_tab() {
        $this->redirect_to_settings_tab('payments');
    }

    public function redirect_to_lessons_tab() {
        $this->redirect_to_content_tab();
    }

    public function redirect_to_sso_tab() {
        $this->redirect_to_settings_tab('sso');
    }

    public function redirect_to_engagement_tab() {
        $this->redirect_to_settings_tab('engagement');
    }

    public function redirect_to_administration_tab() {
        $this->redirect_to_settings_tab('administration');
    }

    public function redirect_to_chat_logs_tab() {
        $this->redirect_to_settings_tab('chat-logs');
    }

    public function redirect_to_docs_tab() {
        $this->redirect_to_settings_tab('documentation');
    }

    public function redirect_to_da1_tab() {
        $this->redirect_to_settings_tab('da1');
    }

    /**
     * v1.8.0 → v1.8.2: UI & Navigation → Settings tab=ui (Profile Bar + chat menus).
     * Early admin_init also maps flosc-ui-navigation; this is the menu callback fallback.
     */
    public function render_ui_navigation_page() {
        $this->redirect_to_settings_tab('ui');
    }

    public function render_da1_page() {
        echo '<div class="wrap">';
        include FLOSC_PLUGIN_DIR . 'admin/da1.php';
        echo '</div>';
    }

    /**
     * Shortcode: [flosc_visitor_only]
     * Shows content only to non-logged-in visitors
     */
    public function shortcode_visitor_only($atts, $content = '') {
        if (!is_user_logged_in()) {
            return wp_kses_post(do_shortcode($content));
        }
        return '';
    }

    /**
     * Shortcode: [flosc_member_only]
     * Shows content only to members (users with _flosc_member_access = true)
     * 
     * Usage: [flosc_member_only fallback="Upgrade to unlock"]Content here[/flosc_member_only]
     * 
     * @param array $atts Shortcode attributes (fallback message)
     * @param string $content Shortcode content
     * @return string
     */
    public function shortcode_member_only($atts, $content = '') {
        // Parse attributes
        $atts = shortcode_atts([
            'fallback' => '', // Optional fallback message for non-members
        ], $atts);
        
        if (!is_user_logged_in()) {
            return $atts['fallback'] ? wp_kses_post('<div class="flosc-member-only-fallback">' . esc_html($atts['fallback']) . '</div>') : '';
        }
        
        $user_id = get_current_user_id();
        $is_member = get_user_meta($user_id, '_flosc_member_access', true);
        
        if ($is_member === 'true' || $is_member === true) {
            return wp_kses_post(do_shortcode($content));
        }
        
        // Not a member - show fallback if provided
        return $atts['fallback'] ? wp_kses_post('<div class="flosc-member-only-fallback">' . esc_html($atts['fallback']) . '</div>') : '';
    }

    /**
     * Shortcode: [flosc_contact_form_01] and [flosc-contact-form-01]
     */
    public function shortcode_contact_form_01($atts = []) {
        $atts = shortcode_atts([
            'flow' => '',
        ], $atts);

        $settings = $this->get_contact_form_settings((string) $atts['flow']);
        $status_raw = filter_input( INPUT_GET, 'flosc_contact_status', FILTER_UNSAFE_RAW );
        $status     = is_string( $status_raw ) ? sanitize_key( wp_unslash( $status_raw ) ) : '';

        wp_enqueue_style(
            'flosc-contact-form',
            FLOSC_PLUGIN_URL . 'assets/css/flosc-contact-form.css',
            [],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-contact-form.css')
        );

        $css_vars = sprintf(
            '.flosc-contact-form-wrap{--flosc-contact-bg:%s;--flosc-contact-accent:%s;--flosc-contact-button-bg:%s;--flosc-contact-button-text:%s;--flosc-contact-msg-font:%s;--flosc-contact-msg-size:%dpx;}',
            esc_html($settings['card_background']),
            esc_html($settings['accent_color']),
            esc_html($settings['button_bg_color']),
            esc_html($settings['button_text_color']),
            esc_html($settings['message_font_family']),
            max(14, intval($settings['message_font_size']))
        );
        wp_add_inline_style('flosc-contact-form', $css_vars);

        $current_url = home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
        $rendered_at = time();
        $honeypot_name = 'flosc_contact_company';
        $show_form = ($status !== 'success');

        ob_start();
        ?>
        <section class="flosc-contact-form-wrap" aria-label="Contact Form">
            <div class="flosc-contact-form-card">
                <h2 class="flosc-contact-form-title"><?php echo esc_html($settings['form_title']); ?></h2>
                <?php if ($settings['form_intro'] !== ''): ?>
                    <p class="flosc-contact-form-intro"><?php echo esc_html($settings['form_intro']); ?></p>
                <?php endif; ?>

                <?php if ($status === 'success'): ?>
                    <p class="flosc-contact-notice flosc-contact-notice-success"><?php echo esc_html($settings['success_message']); ?></p>
                <?php elseif ($status === 'error'): ?>
                    <p class="flosc-contact-notice flosc-contact-notice-error"><?php esc_html_e('Please review the form and try again.', 'flosc'); ?></p>
                <?php endif; ?>

                <?php if ($show_form): ?>
                <form class="flosc-contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
                    <input type="hidden" name="action" value="flosc_contact_submit">
                    <input type="hidden" name="flosc_contact_return" value="<?php echo esc_url($current_url); ?>">
                    <input type="hidden" name="flosc_contact_flow" value="<?php echo esc_attr($settings['flow_id']); ?>">
                    <input type="hidden" name="flosc_contact_rendered_at" value="<?php echo esc_attr($rendered_at); ?>">
                    <?php wp_nonce_field('flosc_contact_submit', 'flosc_contact_nonce'); ?>

                    <label class="flosc-contact-hp" for="<?php echo esc_attr($honeypot_name); ?>">Leave this empty</label>
                    <input class="flosc-contact-hp" type="text" id="<?php echo esc_attr($honeypot_name); ?>" name="<?php echo esc_attr($honeypot_name); ?>" value="" autocomplete="off" tabindex="-1">

                    <div class="flosc-contact-grid">
                        <div class="flosc-contact-field">
                            <label for="flosc_contact_first_name"><?php esc_html_e('First Name', 'flosc'); ?></label>
                            <input type="text" id="flosc_contact_first_name" name="flosc_contact_first_name" required maxlength="80" autocomplete="given-name">
                        </div>
                        <div class="flosc-contact-field">
                            <label for="flosc_contact_last_name"><?php esc_html_e('Last Name', 'flosc'); ?></label>
                            <input type="text" id="flosc_contact_last_name" name="flosc_contact_last_name" required maxlength="80" autocomplete="family-name">
                        </div>
                        <div class="flosc-contact-field">
                            <label for="flosc_contact_email"><?php esc_html_e('Email Address', 'flosc'); ?></label>
                            <input type="text" id="flosc_contact_email" name="flosc_contact_email" required maxlength="190" inputmode="email" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                        </div>
                        <div class="flosc-contact-field">
                            <label for="flosc_contact_phone"><?php esc_html_e('Phone Number', 'flosc'); ?></label>
                            <input type="text" id="flosc_contact_phone" name="flosc_contact_phone" required maxlength="40" autocomplete="tel">
                        </div>
                    </div>

                    <div class="flosc-contact-field flosc-contact-field-message">
                        <label for="flosc_contact_message"><?php esc_html_e('Message', 'flosc'); ?></label>
                        <textarea id="flosc_contact_message" name="flosc_contact_message" required rows="8" maxlength="4000"></textarea>
                    </div>

                    <button type="submit" class="flosc-contact-submit"><?php echo esc_html($settings['submit_button_text']); ?></button>
                </form>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public function handle_contact_form_submit() {
        $return_url = esc_url_raw((string) wp_unslash($_POST['flosc_contact_return'] ?? home_url('/')));
        $nonce = sanitize_text_field((string) wp_unslash($_POST['flosc_contact_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'flosc_contact_submit')) {
            $this->redirect_contact_form_result($return_url, 'error');
        }

        $flow_id = sanitize_key((string) wp_unslash($_POST['flosc_contact_flow'] ?? ''));
        $result = $this->process_contact_form_submission([
            'first_name' => sanitize_text_field((string) wp_unslash($_POST['flosc_contact_first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) wp_unslash($_POST['flosc_contact_last_name'] ?? '')),
            'email' => sanitize_text_field((string) wp_unslash($_POST['flosc_contact_email'] ?? '')),
            'phone' => sanitize_text_field((string) wp_unslash($_POST['flosc_contact_phone'] ?? '')),
            'message' => sanitize_textarea_field((string) wp_unslash($_POST['flosc_contact_message'] ?? '')),
            'honeypot' => sanitize_text_field((string) wp_unslash($_POST['flosc_contact_company'] ?? '')),
            'rendered_at' => intval(wp_unslash($_POST['flosc_contact_rendered_at'] ?? 0)),
        ], $flow_id, [
            'source' => 'page_form',
            'enforce_timing' => true,
        ]);

        if (empty($result['success'])) {
            $this->redirect_contact_form_result($return_url, 'error');
        }

        $this->redirect_contact_form_result($return_url, 'success');
    }

    public function process_contact_form_submission($data, $flow_id = '', $options = []) {
        $flow_id = sanitize_key((string) $flow_id);
        $settings = $this->get_contact_form_settings($flow_id);

        $first_name = sanitize_text_field((string) ($data['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($data['last_name'] ?? ''));
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $phone = sanitize_text_field((string) ($data['phone'] ?? ''));
        $message = sanitize_textarea_field((string) ($data['message'] ?? ''));
        $honeypot = sanitize_text_field((string) ($data['honeypot'] ?? ''));
        $rendered_at = intval($data['rendered_at'] ?? 0);
        $enforce_timing = !empty($options['enforce_timing']);
        $source = sanitize_key((string) ($options['source'] ?? 'contact_form'));

        if ($honeypot !== '') {
            return ['success' => false, 'error' => 'spam_honeypot'];
        }

        if ($enforce_timing) {
            if ($rendered_at <= 0 || (time() - $rendered_at) < intval($settings['min_submit_seconds'])) {
                return ['success' => false, 'error' => 'timing'];
            }
        }

        if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $message === '' || !is_email($email)) {
            return ['success' => false, 'error' => 'validation'];
        }

        $ip = sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        $ip_hash = md5($ip);
        $hourly_key = 'flosc_contact_rate_' . $ip_hash;
        $hourly_count = intval(get_transient($hourly_key));
        if ($hourly_count >= intval($settings['max_submissions_per_hour'])) {
            return ['success' => false, 'error' => 'rate_limit'];
        }
        set_transient($hourly_key, $hourly_count + 1, HOUR_IN_SECONDS);

        $dedupe_window = max(1, intval($settings['duplicate_window_minutes'])) * MINUTE_IN_SECONDS;
        $dedupe_hash = md5(strtolower(trim($email)) . '|' . preg_replace('/\s+/', ' ', trim($message)));
        $dedupe_key = 'flosc_contact_dupe_' . $dedupe_hash;
        if (get_transient($dedupe_key)) {
            return ['success' => false, 'error' => 'duplicate'];
        }
        set_transient($dedupe_key, 1, $dedupe_window);

        $recipient = sanitize_email($settings['forward_to_email']);
        if (!is_email($recipient)) {
            $recipient = get_option('admin_email');
        }

        $subject = trim((string) $settings['email_subject']);
        if ($subject === '') {
            $subject = 'New Contact Form Message';
        }

        $body = "Contact Form Submission\n\n"
            . "Source: {$source}\n"
            . "First Name: {$first_name}\n"
            . "Last Name: {$last_name}\n"
            . "Email: {$email}\n"
            . "Phone: {$phone}\n"
            . "Flow: " . ($flow_id !== '' ? $flow_id : 'default') . "\n"
            . "IP Hash: {$ip_hash}\n"
            . "\nMessage:\n{$message}\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $first_name . ' ' . $last_name . ' <' . $email . '>',
        ];

        $sent = wp_mail($recipient, $subject, $body, $headers);
        if (!$sent) {
            return ['success' => false, 'error' => 'mail_failed'];
        }

        return [
            'success' => true,
            'message' => $settings['success_message'],
        ];
    }

    private function redirect_contact_form_result($return_url, $status) {
        $target = $return_url ?: home_url('/');
        $target = add_query_arg('flosc_contact_status', sanitize_key($status), $target);
        wp_safe_redirect($target);
        exit;
    }

    private function get_contact_form_settings($flow_id = '') {
        $flow_id = sanitize_key((string) $flow_id);
        $settings = [];

        if ($flow_id !== '') {
            $flow_settings = get_option('flosc_flow_' . $flow_id, []);
            if (is_array($flow_settings)) {
                $settings = $flow_settings;
            }
        } elseif (method_exists($this, 'get_current_flow')) {
            $flow = $this->get_current_flow();
            if (!empty($flow['ivr_file'])) {
                $resolved_flow = sanitize_key(pathinfo(basename((string) $flow['ivr_file']), PATHINFO_FILENAME));
                $flow_settings = get_option('flosc_flow_' . $resolved_flow, []);
                if (is_array($flow_settings)) {
                    $settings = $flow_settings;
                    $flow_id = $resolved_flow;
                }
            }
        }

        return [
            'flow_id' => $flow_id,
            'form_title' => trim((string) ($settings['contact_form_title'] ?? 'Contact the Site Operator')),
            'form_intro' => trim((string) ($settings['contact_form_intro'] ?? 'Please fill out the form below to get in touch with the site operator. If you do not receive a response, please try another contact method.')),
            'submit_button_text' => trim((string) ($settings['contact_form_submit_text'] ?? 'Send Message')),
            'success_message' => trim((string) ($settings['contact_form_success_message'] ?? 'We\'ve forwarded your message to the site operator, thank you!')),
            'forward_to_email' => trim((string) ($settings['contact_form_forward_to_email'] ?? get_option('admin_email'))),
            'email_subject' => trim((string) ($settings['contact_form_email_subject'] ?? 'FLOSC Contact Form Message')),
            'min_submit_seconds' => max(2, intval($settings['contact_form_min_submit_seconds'] ?? 4)),
            'max_submissions_per_hour' => max(1, intval($settings['contact_form_max_submissions_per_hour'] ?? 3)),
            'duplicate_window_minutes' => max(1, intval($settings['contact_form_duplicate_window_minutes'] ?? 30)),
            'button_bg_color' => $this->sanitize_contact_color((string) ($settings['contact_form_button_bg_color'] ?? '#1f6feb'), '#1f6feb'),
            'button_text_color' => $this->sanitize_contact_color((string) ($settings['contact_form_button_text_color'] ?? '#ffffff'), '#ffffff'),
            'card_background' => $this->sanitize_contact_color((string) ($settings['contact_form_card_background'] ?? '#ffffff'), '#ffffff'),
            'accent_color' => $this->sanitize_contact_color((string) ($settings['contact_form_accent_color'] ?? '#2e8b57'), '#2e8b57'),
            'message_font_family' => trim((string) ($settings['contact_form_message_font_family'] ?? "'Courier New', Courier, monospace")),
            'message_font_size' => max(16, intval($settings['contact_form_message_font_size'] ?? 20)),
        ];
    }

    private function sanitize_contact_color($color, $fallback) {
        $clean = sanitize_hex_color($color);
        return $clean ? $clean : $fallback;
    }

    /**
     * v1.6.1: Enqueue companion widget on non-app WordPress pages.
     * Only loads if companion mode is enabled for the current flow.
     * v1.6.3: Fixed to read from flat per-flow settings (matching admin save pattern)
     */
    public function enqueue_companion() {
        // Production path is FLOSC_Companion_Mode::enqueue_companion (app-route-only iframe).
        // App routes never load outer chrome; non-app iframe targets are invalid there.
        if ($this->is_flosc_request()) {
            return;
        }

        // Read from per-flow settings (flat keys, not overrides)
        $enabled = $this->get_setting('companion_enabled', false);
        if (!$enabled) {
            return;
        }

        $app_url = $this->get_app_url();
        if (empty($app_url)) {
            return;
        }

        $accent = $this->get_setting('companion_accent_color', '#2563eb');
        $title  = $this->get_setting('companion_greeting', 'Chat with us');
        $header_icon = $this->get_setting('companion_header_icon_url', '');
        if ($header_icon === '' && function_exists('flosc_get_chatlogo_url')) {
            $header_icon = flosc_get_chatlogo_url();
        }
        $product_name = '';
        if (function_exists('flosc') && method_exists(flosc(), 'get_floscflow_identity')) {
            $id = flosc()->get_floscflow_identity();
            $product_name = sanitize_text_field((string) ($id['name'] ?? ''));
        }

        wp_enqueue_style(
            'flosc-companion',
            FLOSC_PLUGIN_URL . 'assets/css/flosc-companion.css',
            [],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-companion.css')
        );

        wp_enqueue_script(
            'flosc-companion',
            FLOSC_PLUGIN_URL . 'assets/js/flosc-companion.js',
            [],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/js/flosc-companion.js'),
            true
        );

        wp_add_inline_script('flosc-companion', sprintf(
            'FloscCompanion.init(%s);',
            wp_json_encode([
                'appUrl'         => $app_url,
                'title'          => $title,
                'productName'    => $product_name,
                'headerIconUrl'  => $header_icon,
                'assistantTitle' => $product_name ?: $title,
                'showHeaderTokens' => false,
                'headerTokenText'  => '',
                'accentColor'    => $accent ?: '#2563eb',
            ])
        ));
    }

    /**
     * Enqueue chat styling (v9.3.9 - Bulletproof Architecture)
     *
     * Architecture:
     * 1. flosc-frontend.css - Non-chat served content
     * 2. flosc-chat.css - Chat interface styles
     * 3. This method - Chat preset variables + dynamic overrides
     * 
     * Presets: auto (system preference), light, dark
     * Customization: bubble style, accent color, font, scale
     */
    private function enqueue_chat_style() {
        // v1.6.1: Per-flow settings via FLOSC_Flow_Manager::get_setting()
        $fm = FLOSC_Flow_Manager::instance();
        $preset     = $fm->get_setting('flosc_chat_style_preset', 'style', 'preset', 'light');
        $bubble     = $fm->get_setting('flosc_chat_style_bubble', 'style', 'bubble', 'subtle-notch');
        $accent     = $fm->get_setting('flosc_chat_style_accent', 'style', 'accent', '');
        $font       = $fm->get_setting('flosc_chat_style_font', 'style', 'font', 'system');
        $scale      = intval($fm->get_setting('flosc_chat_style_scale', 'style', 'scale', 100));

        // Bubble style presets (border-radius values per FLOSC_STYLE_GUIDE.md)
        $bubble_styles = [
            'subtle-notch' => ['user' => '18px 18px 4px 18px', 'assistant' => '4px 18px 18px 18px'],
            'classic'      => ['user' => '18px 18px 0 18px',   'assistant' => '0 18px 18px 18px'],
            'modern'       => ['user' => '20px 20px 6px 20px', 'assistant' => '6px 20px 20px 20px'],
            'minimal'      => ['user' => '16px',               'assistant' => '16px'],
            'sharp'        => ['user' => '12px 12px 2px 12px', 'assistant' => '2px 12px 12px 12px'],
        ];

        // Font family map
        $font_families = [
            'system'        => '',
            'inter'         => '"Inter", -apple-system, sans-serif',
            'ibm-plex-sans' => '"IBM Plex Sans", -apple-system, sans-serif',
            'ibm-plex-mono' => '"IBM Plex Mono", "SF Mono", Monaco, monospace',
            'roboto'        => '"Roboto", -apple-system, sans-serif',
            'roboto-mono'   => '"Roboto Mono", "SF Mono", Monaco, monospace',
            'fira-code'     => '"Fira Code", "SF Mono", Monaco, monospace',
        ];

        // File paths
        $light_path = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-light.css';
        $dark_path  = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-dark.css';
        
        $inline_css = "";

        // ===========================================
        // PRESET LOADING
        // ===========================================
        if ($preset === 'auto') {
            // Auto mode: Light by default, dark via prefers-color-scheme
            if (file_exists($light_path) && file_exists($dark_path)) {
                $light_content = flosc_fs_get_contents($light_path);
                $dark_content  = flosc_fs_get_contents($dark_path);
                
                if ($light_content) {
                    $light_vars = $this->extract_css_variables($light_content);
                    if ($light_vars) {
                        $inline_css .= "/* Light Theme (Default) */\n:root {\n{$light_vars}}\n\n";
                    }
                }
                
                if ($dark_content) {
                    $dark_vars = $this->extract_css_variables($dark_content);
                    if ($dark_vars) {
                        $inline_css .= "/* Dark Theme (System Preference) */\n@media (prefers-color-scheme: dark) {\n  :root {\n{$dark_vars}  }\n}\n\n";
                    }
                }
            }
        } else {
            // Named preset (light, dark, chatgpt, claude, grok): load as external stylesheet
            $safe_preset = preg_replace('/[^a-z0-9-]/', '', $preset);
            $preset_path = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-' . $safe_preset . '.css';
            if (file_exists($preset_path)) {
                wp_enqueue_style(
                    'flosc-preset',
                    FLOSC_PLUGIN_URL . 'assets/css/chat-style-' . $safe_preset . '.css',
                    ['flosc-chat'],
                    filemtime($preset_path)
                );
            }
        }

        // ===========================================
        // DYNAMIC OVERRIDES
        // ===========================================
        $bubble_config = $bubble_styles[$bubble] ?? $bubble_styles['subtle-notch'];
        
        $overrides = [];
        $overrides[] = "--flosc-user-message-radius: {$bubble_config['user']}";
        $overrides[] = "--flosc-assistant-message-radius: {$bubble_config['assistant']}";
        
        // v1.6.1: Full accent color cascade (5→15 derived variables)
        if (!empty($accent) && $accent !== '#2563eb') {
            // Compute derived colors from hex accent
            $hover   = $this->adjust_color_brightness($accent, -15);
            $subtle  = $this->hex_to_rgba($accent, 0.06);
            $subtle4 = $this->hex_to_rgba($accent, 0.04);
            $light   = $this->adjust_color_brightness($accent, 40);

            // Core accent
            $overrides[] = "--flosc-accent: {$accent}";
            $overrides[] = "--flosc-accent-hover: {$hover}";
            $overrides[] = "--flosc-accent-subtle: {$subtle}";

            // Components that derive from accent
            $overrides[] = "--flosc-user-message-bg: {$accent}";
            $overrides[] = "--flosc-user-avatar-bg: {$accent}";
            $overrides[] = "--flosc-send-btn-bg: {$accent}";
            $overrides[] = "--flosc-pill-hover-text: {$accent}";
            $overrides[] = "--flosc-pill-hover-border: {$light}";
            $overrides[] = "--flosc-card-hover-text: {$accent}";
            $overrides[] = "--flosc-card-hover-border: {$light}";
            $overrides[] = "--flosc-content-link: {$accent}";
            $overrides[] = "--flosc-content-link-hover: {$hover}";
            $overrides[] = "--flosc-content-blockquote-border: {$accent}";
            $overrides[] = "--flosc-content-blockquote-bg: {$subtle4}";
            $overrides[] = "--flosc-quiz-tab-active-bg: {$accent}";
            $overrides[] = "--flosc-quiz-input-focus-border: {$accent}";
        }
        
        // Scale factor
        if ($scale !== 100 && $scale > 0) {
            $scale_factor = $scale / 100;
            $overrides[] = "--flosc-scale: {$scale_factor}";
        }
        
        // Font family
        if ($font !== 'system' && isset($font_families[$font]) && !empty($font_families[$font])) {
            $overrides[] = "--flosc-font-family: {$font_families[$font]}";
        }
        
        if (!empty($overrides)) {
            $inline_css .= "/* Dynamic Overrides */\n:root {\n    " . implode(";\n    ", $overrides) . ";\n}\n\n";
        }
        
        // Font application
        if ($font !== 'system' && isset($font_families[$font]) && !empty($font_families[$font])) {
            $inline_css .= "/* Font Application */\n";
            $inline_css .= ".flosc-app,\n.flosc-app .messages,\n.flosc-app .message-text {\n";
            $inline_css .= "    font-family: var(--flosc-font-family) !important;\n}\n\n";
        }

        // Attach inline styles to flosc-chat handle (always exists on app requests)
        if (!empty(trim($inline_css))) {
            wp_add_inline_style('flosc-chat', $inline_css);
        }
    }

    /**
     * Extract CSS variables from stylesheet content
     * Returns the inner content of :root { } block
     * 
     * @param string $css_content Raw CSS file content
     * @return string Variable declarations or empty string
     */
    private function extract_css_variables($css_content) {
        if (empty($css_content)) {
            return '';
        }
        
        // Remove CSS comments
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css_content);
        
        // Extract content inside :root { }
        if (preg_match('/:root\s*\{([^}]+)\}/s', $css, $matches)) {
            return trim($matches[1]) . "\n";
        }
        
        return '';
    }

    /**
     * Adjust hex color brightness by a percentage (-100 to +100).
     * Negative = darker, positive = lighter.
     * v1.6.1: Used for accent color cascade.
     */
    private function adjust_color_brightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + round($r * $percent / 100)));
        $g = max(0, min(255, $g + round($g * $percent / 100)));
        $b = max(0, min(255, $b + round($b * $percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Convert hex color to rgba string.
     * v1.6.1: Used for accent-subtle generation.
     */
    private function hex_to_rgba($hex, $alpha) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}
