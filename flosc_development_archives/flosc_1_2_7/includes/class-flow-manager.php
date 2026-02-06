<?php
/**
 * FLOSC Flow Manager v1.2.7
 * 
 * Multi-flow support with flow-first/global-fallback settings pattern.
 * 
 * KEY CONCEPTS:
 * - Each flow can override ANY global setting
 * - Empty flow setting = use global default
 * - Flow settings stored in flow['settings'][group][key]
 * 
 * USAGE:
 *   flosc_get_setting('ai_provider')           // Auto-detects current flow
 *   flosc_get_setting('ai_provider', '', $id)  // Specific flow
 *   flosc_save_setting('ai_provider', 'openai', $id) // Save to flow
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Flow_Manager {
    
    private static $instance = null;
    const OPTION_KEY = 'flosc_flows';
    
    // Settings that can be per-flow (mapped to global option names)
    const FLOW_SETTINGS_MAP = [
        // AI Configuration
        'ai_provider' => 'flosc_ai_provider',
        'openai_api_key' => 'flosc_openai_api_key',
        'anthropic_api_key' => 'flosc_anthropic_api_key',
        'xai_api_key' => 'flosc_xai_api_key',
        'ai_base_prompt' => 'flosc_ai_base_prompt',
        'ai_response_mode' => 'flosc_ai_response_mode',
        
        // AI Knowledge / Personality
        'ai_personality_name' => 'flosc_ai_personality_name',
        'ai_personality_role' => 'flosc_ai_personality_role',
        'ai_personality_traits' => 'flosc_ai_personality_traits',
        'ai_mission' => 'flosc_ai_mission',
        'ai_context_awareness' => 'flosc_ai_context_awareness',
        'ai_freeline_restrictions' => 'flosc_ai_freeline_restrictions',
        'ai_member_access' => 'flosc_ai_member_access',
        
        // Chat Styling
        'chat_style_preset' => 'flosc_chat_style_preset',
        'chat_style_bubble' => 'flosc_chat_style_bubble',
        'chat_style_accent' => 'flosc_chat_style_accent',
        'chat_style_font' => 'flosc_chat_style_font',
        'chat_style_scale' => 'flosc_chat_style_scale',
        'chat_style_custom_css' => 'flosc_chat_style_custom_css',
        
        // Email
        'email_from_name' => 'flosc_email_from_name',
        'email_from_address' => 'flosc_email_from_address',
        'email_subject' => 'flosc_email_subject',
        'email_body' => 'flosc_email_body',
        'send_quiz_results' => 'flosc_send_quiz_results',
        'congrats_threshold' => 'flosc_congrats_threshold',
        
        // Lessons
        'lessons_category' => 'flosc_lessons_category',
        'oto_offer_id' => 'flosc_oto_offer_id',
        'free_lesson_mode' => 'flosc_free_lesson_mode',
        'free_lesson_count' => 'flosc_free_lesson_count',
        
        // STT
        'stt_provider' => 'flosc_stt_provider',
        'assemblyai_api_key' => 'flosc_assemblyai_api_key',
        'deepgram_api_key' => 'flosc_deepgram_api_key',
        'custom_stt_endpoint' => 'flosc_custom_stt_endpoint',
        
        // Payments
        'stripe_enabled' => 'flosc_stripe_enabled',
        'stripe_mode' => 'flosc_stripe_mode',
        'stripe_test_pk' => 'flosc_stripe_test_pk',
        'stripe_test_sk' => 'flosc_stripe_test_sk',
        'stripe_live_pk' => 'flosc_stripe_live_pk',
        'stripe_live_sk' => 'flosc_stripe_live_sk',
        'stripe_webhook_secret' => 'flosc_stripe_webhook_secret',
        'paypal_enabled' => 'flosc_paypal_enabled',
        'paypal_client_id' => 'flosc_paypal_client_id',
        'paypal_secret' => 'flosc_paypal_secret',
        'paypal_mode' => 'flosc_paypal_mode',
    ];
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    // =========================================
    // FLOW CRUD
    // =========================================
    
    public function get_all_flows() {
        return get_option(self::OPTION_KEY, []);
    }
    
    public function get_user_flows($user_id = null) {
        $user_id = $user_id ?: get_current_user_id();
        $all_flows = $this->get_all_flows();
        
        if (user_can($user_id, 'manage_options')) {
            return $all_flows;
        }
        
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true) ?: [];
        if (!is_array($allowed)) $allowed = [];
        
        return array_filter($all_flows, function($flow) use ($allowed) {
            return in_array($flow['id'], $allowed);
        });
    }
    
    public function get_flow($flow_id) {
        $flows = $this->get_all_flows();
        return $flows[$flow_id] ?? null;
    }
    
    public function get_flow_by_slug($slug) {
        foreach ($this->get_all_flows() as $flow) {
            if (($flow['slug'] ?? '') === $slug) return $flow;
        }
        return null;
    }
    
    public function get_flow_by_domain($domain) {
        $domain = strtolower(preg_replace('#^https?://#', '', trim($domain)));
        $domain = rtrim($domain, '/');
        
        foreach ($this->get_all_flows() as $flow) {
            if (empty($flow['custom_domain'])) continue;
            
            $flow_domain = strtolower(preg_replace('#^https?://#', '', trim($flow['custom_domain'])));
            $flow_domain = rtrim($flow_domain, '/');
            
            if ($flow_domain === $domain || 'www.' . $flow_domain === $domain) {
                return $flow;
            }
        }
        return null;
    }
    
    public function get_first_active_flow() {
        foreach ($this->get_all_flows() as $flow) {
            if (($flow['status'] ?? 'draft') === 'active') return $flow;
        }
        $flows = $this->get_all_flows();
        return reset($flows) ?: null;
    }
    
    public function create_flow($data) {
        $flows = $this->get_all_flows();
        
        if (empty($data['id'])) {
            $data['id'] = sanitize_key($data['slug'] ?? 'flow_' . wp_generate_password(6, false, false));
        }
        
        if (isset($flows[$data['id']])) {
            return new WP_Error('duplicate_id', 'Flow ID already exists');
        }
        
        if (!empty($data['slug'])) {
            foreach ($flows as $flow) {
                if (($flow['slug'] ?? '') === $data['slug']) {
                    return new WP_Error('duplicate_slug', 'Slug already in use');
                }
            }
        }
        
        $flow = $this->normalize_flow_data($data);
        $flow['created_at'] = current_time('mysql');
        $flow['updated_at'] = current_time('mysql');
        
        $flows[$flow['id']] = $flow;
        update_option(self::OPTION_KEY, $flows);
        flush_rewrite_rules();
        
        return $flow;
    }
    
    public function update_flow($flow_id, $data) {
        $flows = $this->get_all_flows();
        
        if (!isset($flows[$flow_id])) {
            return new WP_Error('not_found', 'Flow not found');
        }
        
        if (!empty($data['slug']) && $data['slug'] !== ($flows[$flow_id]['slug'] ?? '')) {
            foreach ($flows as $id => $flow) {
                if ($id !== $flow_id && ($flow['slug'] ?? '') === $data['slug']) {
                    return new WP_Error('duplicate_slug', 'Slug already in use');
                }
            }
        }
        
        // Deep merge
        $flow = $this->deep_merge($flows[$flow_id], $data);
        $flow = $this->normalize_flow_data($flow);
        $flow['id'] = $flow_id;
        $flow['updated_at'] = current_time('mysql');
        
        $flows[$flow_id] = $flow;
        update_option(self::OPTION_KEY, $flows);
        flush_rewrite_rules();
        
        return $flow;
    }
    
    private function deep_merge($original, $new) {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($original[$key]) && is_array($original[$key])) {
                $original[$key] = $this->deep_merge($original[$key], $value);
            } else {
                $original[$key] = $value;
            }
        }
        return $original;
    }
    
    public function delete_flow($flow_id) {
        $flows = $this->get_all_flows();
        
        if (!isset($flows[$flow_id])) {
            return new WP_Error('not_found', 'Flow not found');
        }
        
        if (count($flows) <= 1) {
            return new WP_Error('last_flow', 'Cannot delete the last flow');
        }
        
        $users = $this->get_flow_users($flow_id);
        foreach ($users as $user) {
            $this->revoke_flow_access($user->ID, $flow_id);
        }
        
        unset($flows[$flow_id]);
        update_option(self::OPTION_KEY, $flows);
        flush_rewrite_rules();
        
        return true;
    }
    
    private function normalize_flow_data($data) {
        $defaults = [
            'id' => '',
            'slug' => '',
            'custom_domain' => '',
            'status' => 'active',
            'product' => [
                'name' => 'New Flow',
                'tagline' => '',
                'emoji' => '🎯',
                'logo_url' => '',
                'primary_color' => '#4f46e5',
                'share_text' => '',
            ],
            'ivr_file' => 'flosc_default_ivr.md',
            'wp_category_id' => 0,
            'quiz_type' => 'flosc_sample_text_based_quiz',
            'settings' => [], // v1.2.7: Per-flow settings
            'created_at' => '',
            'updated_at' => '',
        ];
        
        $flow = wp_parse_args($data, $defaults);
        $flow['product'] = wp_parse_args($data['product'] ?? [], $defaults['product']);
        $flow['settings'] = $data['settings'] ?? [];
        
        // Sanitize
        $flow['id'] = sanitize_key($flow['id']);
        $flow['slug'] = sanitize_title($flow['slug']);
        $flow['custom_domain'] = sanitize_text_field($flow['custom_domain']);
        $flow['status'] = in_array($flow['status'], ['active', 'draft']) ? $flow['status'] : 'draft';
        $flow['ivr_file'] = preg_replace('#[^a-zA-Z0-9/_.-]#', '', $flow['ivr_file']);
        $flow['wp_category_id'] = intval($flow['wp_category_id']);
        $flow['quiz_type'] = sanitize_key($flow['quiz_type']);
        
        $flow['product']['name'] = sanitize_text_field($flow['product']['name']);
        $flow['product']['tagline'] = sanitize_text_field($flow['product']['tagline']);
        $flow['product']['emoji'] = sanitize_text_field($flow['product']['emoji']);
        $flow['product']['logo_url'] = esc_url_raw($flow['product']['logo_url']);
        $flow['product']['primary_color'] = sanitize_hex_color($flow['product']['primary_color']) ?: '#4f46e5';
        $flow['product']['share_text'] = sanitize_text_field($flow['product']['share_text']);
        
        return $flow;
    }
    
    // =========================================
    // SETTINGS: FLOW-FIRST / GLOBAL-FALLBACK
    // =========================================
    
    /**
     * Get a setting value
     * 
     * 1. Check flow-specific setting (if flow provided/detected)
     * 2. Fall back to global wp_option
     * 
     * @param string $key Setting key (without flosc_ prefix)
     * @param mixed $default Default value
     * @param string|null $flow_id Flow ID (null = auto-detect)
     * @return mixed
     */
    public function get_setting($key, $default = '', $flow_id = null) {
        // Determine flow
        if ($flow_id === null) {
            // Check admin context first
            if (isset($GLOBALS['flosc_editing_flow'])) {
                $flow_id = $GLOBALS['flosc_editing_flow'];
            } else {
                // Frontend context
                $flow = $this->get_current_flow_frontend();
                $flow_id = $flow ? $flow['id'] : null;
            }
        }
        
        // Check flow-specific setting
        if ($flow_id) {
            $flow = $this->get_flow($flow_id);
            if ($flow && isset($flow['settings'][$key]) && $flow['settings'][$key] !== '') {
                return $flow['settings'][$key];
            }
        }
        
        // Fall back to global
        $option_name = self::FLOW_SETTINGS_MAP[$key] ?? ('flosc_' . $key);
        return get_option($option_name, $default);
    }
    
    /**
     * Save a setting to a specific flow
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value (empty string = use global)
     * @param string $flow_id Flow ID
     * @return bool
     */
    public function save_setting($key, $value, $flow_id) {
        $flows = $this->get_all_flows();
        
        if (!isset($flows[$flow_id])) {
            return false;
        }
        
        if (!isset($flows[$flow_id]['settings'])) {
            $flows[$flow_id]['settings'] = [];
        }
        
        // Empty value = remove override (use global)
        if ($value === '' || $value === null) {
            unset($flows[$flow_id]['settings'][$key]);
        } else {
            $flows[$flow_id]['settings'][$key] = $value;
        }
        
        $flows[$flow_id]['updated_at'] = current_time('mysql');
        
        return update_option(self::OPTION_KEY, $flows);
    }
    
    /**
     * Save multiple settings at once
     */
    public function save_settings($settings, $flow_id) {
        $flows = $this->get_all_flows();
        
        if (!isset($flows[$flow_id])) {
            return false;
        }
        
        if (!isset($flows[$flow_id]['settings'])) {
            $flows[$flow_id]['settings'] = [];
        }
        
        foreach ($settings as $key => $value) {
            if ($value === '' || $value === null) {
                unset($flows[$flow_id]['settings'][$key]);
            } else {
                $flows[$flow_id]['settings'][$key] = $value;
            }
        }
        
        $flows[$flow_id]['updated_at'] = current_time('mysql');
        
        return update_option(self::OPTION_KEY, $flows);
    }
    
    /**
     * Check if flow has own setting (not using global)
     */
    public function has_flow_setting($key, $flow_id) {
        $flow = $this->get_flow($flow_id);
        return $flow && isset($flow['settings'][$key]) && $flow['settings'][$key] !== '';
    }
    
    /**
     * Get global setting value (bypasses flow)
     */
    public function get_global_setting($key, $default = '') {
        $option_name = self::FLOW_SETTINGS_MAP[$key] ?? ('flosc_' . $key);
        return get_option($option_name, $default);
    }
    
    /**
     * Get current flow for frontend requests
     * Checks domain match, then slug match, then query var
     */
    public function get_current_flow_frontend() {
        static $cached = null;
        static $checked = false;
        
        if ($checked) return $cached;
        $checked = true;
        
        $flows = $this->get_all_flows();
        $current_host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($flows as $flow) {
            if (($flow['status'] ?? 'draft') !== 'active') continue;
            
            // Domain match
            if (!empty($flow['custom_domain'])) {
                $domain = strtolower(preg_replace('#^https?://#', '', trim($flow['custom_domain'])));
                $domain = rtrim($domain, '/');
                if ($current_host === $domain || $current_host === 'www.' . $domain) {
                    $cached = $flow;
                    return $cached;
                }
            }
            
            // Slug match
            if (!empty($flow['slug']) && preg_match('#^/' . preg_quote($flow['slug'], '#') . '/?#', $request_uri)) {
                $cached = $flow;
                return $cached;
            }
        }
        
        // Query var fallback
        $flow_id = get_query_var('flosc_flow');
        if ($flow_id && isset($flows[$flow_id])) {
            $cached = $flows[$flow_id];
            return $cached;
        }
        
        return null;
    }
    
    // =========================================
    // TEAM ACCESS
    // =========================================
    
    public function can_access_flow_admin($flow_id, $user_id = null) {
        $user_id = $user_id ?: get_current_user_id();
        if (user_can($user_id, 'manage_options')) return true;
        if (!user_can($user_id, 'edit_others_posts')) return false;
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true);
        return is_array($allowed) && in_array($flow_id, $allowed);
    }
    
    public function grant_flow_access($user_id, $flow_id) {
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true) ?: [];
        if (!is_array($allowed)) $allowed = [];
        if (!in_array($flow_id, $allowed)) {
            $allowed[] = $flow_id;
            update_user_meta($user_id, '_flosc_flow_access', $allowed);
        }
        return true;
    }
    
    public function revoke_flow_access($user_id, $flow_id) {
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true) ?: [];
        if (!is_array($allowed)) return true;
        $allowed = array_values(array_filter($allowed, fn($id) => $id !== $flow_id));
        update_user_meta($user_id, '_flosc_flow_access', $allowed);
        return true;
    }
    
    public function get_flow_users($flow_id) {
        global $wpdb;
        $user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_flosc_flow_access'");
        if (empty($user_ids)) return [];
        
        $matching = [];
        foreach ($user_ids as $uid) {
            $allowed = get_user_meta($uid, '_flosc_flow_access', true);
            if (is_array($allowed) && in_array($flow_id, $allowed)) $matching[] = $uid;
        }
        return empty($matching) ? [] : get_users(['include' => $matching]);
    }
    
    // =========================================
    // HELPERS
    // =========================================
    
    public function get_available_ivr_files() {
        $files = [];
        $base_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
        foreach (glob($base_path . '*_ivr.md') as $file) {
            $files[] = basename($file);
        }
        if (file_exists($base_path . 'ivr.md')) $files[] = 'ivr.md';
        sort($files);
        return $files;
    }
    
    public function get_available_quiz_types() {
        return [
            'flosc_sample_text_based_quiz' => 'Text-Based Quiz',
            'flosc_sample_audio_quiz' => 'Audio Quiz (Pronunciation)',
            'multiplechoice_quiz' => 'Multiple Choice',
            'truefalse_quiz' => 'True/False',
            'wordmatching_quiz' => 'Word Matching',
        ];
    }
    
    public function maybe_migrate_from_legacy() {
        if (get_option(self::OPTION_KEY) !== false) return false;
        
        $default_flow = [
            'id' => 'default',
            'slug' => get_option('flosc_app_slug', 'flosc'),
            'custom_domain' => get_option('flosc_custom_domain', ''),
            'status' => 'active',
            'product' => [
                'name' => get_option('flosc_product_name', 'FLOSC Default'),
                'tagline' => get_option('flosc_product_tagline', ''),
                'emoji' => get_option('flosc_product_emoji', '🎯'),
                'logo_url' => get_option('flosc_product_logo', ''),
                'primary_color' => get_option('flosc_primary_color', '#4f46e5'),
                'share_text' => get_option('flosc_share_text', ''),
            ],
            'ivr_file' => 'flosc_default_ivr.md',
            'wp_category_id' => intval(get_option('flosc_lessons_category', 0)),
            'quiz_type' => get_option('flosc_quiz_type', 'flosc_sample_text_based_quiz'),
            'settings' => [],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        update_option(self::OPTION_KEY, ['default' => $default_flow]);
        if (FLOSC_DEBUG) error_log('FLOSC v1.2.7: Created default flow from legacy settings');
        return true;
    }
}

// =========================================
// GLOBAL ACCESSOR FUNCTIONS
// =========================================

function flosc_flows() {
    return FLOSC_Flow_Manager::instance();
}

/**
 * Get setting with flow-first/global-fallback
 * 
 * @param string $key Setting key (e.g., 'ai_provider', 'chat_style_preset')
 * @param mixed $default Default value
 * @param string|null $flow_id Flow ID (null = auto-detect)
 */
function flosc_get_setting($key, $default = '', $flow_id = null) {
    return flosc_flows()->get_setting($key, $default, $flow_id);
}

/**
 * Get global setting only (bypass flow)
 */
function flosc_get_global($key, $default = '') {
    return flosc_flows()->get_global_setting($key, $default);
}

/**
 * Save setting to flow
 */
function flosc_save_setting($key, $value, $flow_id) {
    return flosc_flows()->save_setting($key, $value, $flow_id);
}

/**
 * Check if flow has its own setting
 */
function flosc_has_setting($key, $flow_id = null) {
    if ($flow_id === null && isset($GLOBALS['flosc_editing_flow'])) {
        $flow_id = $GLOBALS['flosc_editing_flow'];
    }
    return $flow_id ? flosc_flows()->has_flow_setting($key, $flow_id) : false;
}

/**
 * Helper to display "Using global: X" placeholder
 */
function flosc_global_hint($key, $label = null) {
    $global_val = flosc_get_global($key);
    if ($global_val === '' || $global_val === null) return '';
    
    $display = is_array($global_val) ? implode(', ', $global_val) : $global_val;
    if (strlen($display) > 50) $display = substr($display, 0, 47) . '...';
    
    return '<span class="flosc-global-hint" style="color:#666;font-style:italic;">Using global: ' . esc_html($display) . '</span>';
}
