<?php
/**
 * FLOSC Flow Manager v1.2.4
 * 
 * Comprehensive flow management with ALL settings flow-aware.
 * Core principle: Flow Setting (if set) → Global Setting (fallback)
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Flow_Manager {
    
    private static $instance = null;
    const OPTION_KEY = 'flosc_flows';
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
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
                'name' => '',
                'tagline' => '',
                'emoji' => '',
                'logo_url' => '',
                'primary_color' => '',
                'share_text' => '',
            ],
            'ivr_file' => '',
            'wp_category_id' => '',
            'quiz_type' => '',
            'style' => ['preset' => '', 'bubble' => '', 'accent' => '', 'font' => '', 'scale' => '', 'custom_css' => ''],
            'ai' => ['provider' => '', 'openai_key' => '', 'anthropic_key' => '', 'xai_key' => '', 'base_prompt' => '', 'response_mode' => '', 'prompt_freeline' => '', 'prompt_login' => '', 'prompt_offer' => '', 'prompt_sale' => '', 'prompt_content' => ''],
            'knowledge' => ['personality_name' => '', 'personality_role' => '', 'personality_traits' => '', 'mission' => '', 'context_awareness' => '', 'freeline_restrictions' => '', 'member_access' => ''],
            'email' => ['from_name' => '', 'from_email' => '', 'quiz_subject' => '', 'quiz_body' => '', 'send_on_quiz_complete' => '', 'congrats_threshold' => ''],
            'lessons' => ['category' => '', 'oto_offer_id' => '', 'free_mode' => '', 'free_count' => ''],
            'stt' => ['provider' => '', 'assemblyai_key' => '', 'deepgram_key' => '', 'custom_endpoint' => ''],
            'payments' => ['stripe_enabled' => '', 'stripe_mode' => '', 'stripe_test_pk' => '', 'stripe_test_sk' => '', 'stripe_live_pk' => '', 'stripe_live_sk' => '', 'stripe_webhook_secret' => '', 'paypal_enabled' => '', 'paypal_client_id' => '', 'paypal_secret' => '', 'paypal_mode' => ''],
            'created_at' => '',
            'updated_at' => '',
        ];
        
        $flow = wp_parse_args($data, $defaults);
        
        foreach (['product', 'style', 'ai', 'knowledge', 'email', 'lessons', 'stt', 'payments'] as $key) {
            $flow[$key] = wp_parse_args($data[$key] ?? [], $defaults[$key]);
        }
        
        $flow['id'] = sanitize_key($flow['id']);
        $flow['slug'] = sanitize_title($flow['slug']);
        $flow['custom_domain'] = sanitize_text_field($flow['custom_domain']);
        $flow['status'] = in_array($flow['status'], ['active', 'draft']) ? $flow['status'] : 'draft';
        $flow['ivr_file'] = preg_replace('#[^a-zA-Z0-9/_.-]#', '', $flow['ivr_file']);
        $flow['wp_category_id'] = $flow['wp_category_id'] !== '' ? intval($flow['wp_category_id']) : '';
        $flow['quiz_type'] = sanitize_key($flow['quiz_type']);
        
        $flow['product']['name'] = sanitize_text_field($flow['product']['name']);
        $flow['product']['tagline'] = sanitize_text_field($flow['product']['tagline']);
        $flow['product']['emoji'] = sanitize_text_field($flow['product']['emoji']);
        $flow['product']['logo_url'] = esc_url_raw($flow['product']['logo_url']);
        $flow['product']['primary_color'] = $flow['product']['primary_color'] ? (sanitize_hex_color($flow['product']['primary_color']) ?: '') : '';
        $flow['product']['share_text'] = sanitize_text_field($flow['product']['share_text']);
        
        return $flow;
    }
    
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
        $allowed = array_values(array_filter($allowed, function($id) use ($flow_id) { return $id !== $flow_id; }));
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
            '' => '— Use Global Default —',
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
                'name' => get_option('flosc_product_name', 'FLOSC App'),
                'tagline' => get_option('flosc_product_tagline', ''),
                'emoji' => get_option('flosc_product_emoji', '🎯'),
                'logo_url' => get_option('flosc_product_logo', ''),
                'primary_color' => get_option('flosc_primary_color', '#4f46e5'),
                'share_text' => get_option('flosc_share_text', ''),
            ],
            'ivr_file' => 'flosc_default_ivr.md',
            'wp_category_id' => intval(get_option('flosc_lessons_category', 0)),
            'quiz_type' => get_option('flosc_quiz_type', 'flosc_sample_text_based_quiz'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        update_option(self::OPTION_KEY, ['default' => $default_flow]);
        if (FLOSC_DEBUG) error_log('FLOSC: Migrated legacy settings to flows system');
        return true;
    }
}

function flosc_flows() {
    return FLOSC_Flow_Manager::instance();
}

function flosc_get_setting($key, $default = '', $flow = null) {
    if ($flow === null && function_exists('flosc')) {
        $flow = flosc()->get_current_flow();
    }
    
    $parts = explode('.', $key);
    
    if ($flow) {
        $value = $flow;
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                $value = null;
                break;
            }
        }
        if ($value !== null && $value !== '' && $value !== []) {
            return $value;
        }
    }
    
    $option_key = 'flosc_' . str_replace('.', '_', $key);
    $mappings = [
        'flosc_style_preset' => 'flosc_chat_style_preset',
        'flosc_style_bubble' => 'flosc_chat_style_bubble',
        'flosc_style_accent' => 'flosc_chat_style_accent',
        'flosc_style_font' => 'flosc_chat_style_font',
        'flosc_style_scale' => 'flosc_chat_style_scale',
        'flosc_style_custom_css' => 'flosc_chat_style_custom_css',
        'flosc_ai_openai_key' => 'flosc_openai_api_key',
        'flosc_ai_anthropic_key' => 'flosc_anthropic_api_key',
        'flosc_ai_xai_key' => 'flosc_xai_api_key',
        'flosc_knowledge_personality_name' => 'flosc_ai_personality_name',
        'flosc_knowledge_personality_role' => 'flosc_ai_personality_role',
        'flosc_knowledge_personality_traits' => 'flosc_ai_personality_traits',
        'flosc_knowledge_mission' => 'flosc_ai_mission',
        'flosc_stt_assemblyai_key' => 'flosc_assemblyai_api_key',
        'flosc_stt_deepgram_key' => 'flosc_deepgram_api_key',
        'flosc_stt_custom_endpoint' => 'flosc_custom_stt_endpoint',
        'flosc_payments_stripe_test_pk' => 'flosc_stripe_test_pk',
        'flosc_payments_stripe_test_sk' => 'flosc_stripe_test_sk',
        'flosc_payments_stripe_live_pk' => 'flosc_stripe_live_pk',
        'flosc_payments_stripe_live_sk' => 'flosc_stripe_live_sk',
        'flosc_lessons_oto_offer_id' => 'flosc_oto_offer_id',
        'flosc_email_quiz_subject' => 'flosc_email_subject',
        'flosc_email_quiz_body' => 'flosc_email_body',
    ];
    
    if (isset($mappings[$option_key])) $option_key = $mappings[$option_key];
    return get_option($option_key, $default);
}

function flosc_get_global_setting($key, $default = '') {
    return flosc_get_setting($key, $default, false);
}
