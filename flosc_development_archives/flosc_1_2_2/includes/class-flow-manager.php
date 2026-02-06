<?php
/**
 * FLOSC Flow Manager
 * 
 * v1.2.2: Handles CRUD operations for FLOSC Flows
 * Enables multiple independent chatbots from a single WordPress installation
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
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Get all flows
     */
    public function get_all_flows() {
        return get_option(self::OPTION_KEY, []);
    }
    
    /**
     * Get flows accessible by a user
     */
    public function get_user_flows($user_id = null) {
        $user_id = $user_id ?: get_current_user_id();
        $all_flows = $this->get_all_flows();
        
        // Administrators see all flows
        if (user_can($user_id, 'manage_options')) {
            return $all_flows;
        }
        
        // Others see only assigned flows
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true) ?: [];
        
        if (!is_array($allowed)) {
            $allowed = [];
        }
        
        return array_filter($all_flows, function($flow) use ($allowed) {
            return in_array($flow['id'], $allowed);
        });
    }
    
    /**
     * Get a single flow by ID
     */
    public function get_flow($flow_id) {
        $flows = $this->get_all_flows();
        return $flows[$flow_id] ?? null;
    }
    
    /**
     * Get flow by slug
     */
    public function get_flow_by_slug($slug) {
        $flows = $this->get_all_flows();
        foreach ($flows as $flow) {
            if ($flow['slug'] === $slug) {
                return $flow;
            }
        }
        return null;
    }
    
    /**
     * Get flow by custom domain
     */
    public function get_flow_by_domain($domain) {
        $domain = strtolower(preg_replace('#^https?://#', '', trim($domain)));
        $domain = rtrim($domain, '/');
        
        $flows = $this->get_all_flows();
        foreach ($flows as $flow) {
            if (empty($flow['custom_domain'])) continue;
            
            $flow_domain = strtolower(preg_replace('#^https?://#', '', trim($flow['custom_domain'])));
            $flow_domain = rtrim($flow_domain, '/');
            
            if ($flow_domain === $domain || 'www.' . $flow_domain === $domain) {
                return $flow;
            }
        }
        return null;
    }
    
    /**
     * Create a new flow
     */
    public function create_flow($data) {
        $flows = $this->get_all_flows();
        
        // Generate ID if not provided
        if (empty($data['id'])) {
            $data['id'] = sanitize_key($data['slug'] ?? 'flow_' . wp_generate_password(6, false, false));
        }
        
        // Validate unique ID
        if (isset($flows[$data['id']])) {
            return new WP_Error('duplicate_id', 'Flow ID already exists');
        }
        
        // Validate unique slug
        if (!empty($data['slug'])) {
            foreach ($flows as $flow) {
                if ($flow['slug'] === $data['slug']) {
                    return new WP_Error('duplicate_slug', 'Slug already in use');
                }
            }
        }
        
        // Normalize data
        $flow = $this->normalize_flow_data($data);
        $flow['created_at'] = current_time('mysql');
        $flow['updated_at'] = current_time('mysql');
        
        // Save
        $flows[$flow['id']] = $flow;
        update_option(self::OPTION_KEY, $flows);
        
        // Flush rewrite rules for new slug
        flush_rewrite_rules();
        
        return $flow;
    }
    
    /**
     * Update an existing flow
     */
    public function update_flow($flow_id, $data) {
        $flows = $this->get_all_flows();
        
        if (!isset($flows[$flow_id])) {
            return new WP_Error('not_found', 'Flow not found');
        }
        
        // Check slug uniqueness if changed
        if (!empty($data['slug']) && $data['slug'] !== $flows[$flow_id]['slug']) {
            foreach ($flows as $id => $flow) {
                if ($id !== $flow_id && $flow['slug'] === $data['slug']) {
                    return new WP_Error('duplicate_slug', 'Slug already in use');
                }
            }
        }
        
        // Merge with existing data
        $flow = array_merge($flows[$flow_id], $data);
        $flow = $this->normalize_flow_data($flow);
        $flow['id'] = $flow_id; // Preserve ID
        $flow['updated_at'] = current_time('mysql');
        
        // Save
        $flows[$flow_id] = $flow;
        update_option(self::OPTION_KEY, $flows);
        
        // Flush rewrite rules in case slug changed
        flush_rewrite_rules();
        
        return $flow;
    }
    
    /**
     * Delete a flow
     */
    public function delete_flow($flow_id) {
        $flows = $this->get_all_flows();
        
        if (!isset($flows[$flow_id])) {
            return new WP_Error('not_found', 'Flow not found');
        }
        
        // Don't allow deleting the last flow
        if (count($flows) <= 1) {
            return new WP_Error('last_flow', 'Cannot delete the last flow');
        }
        
        unset($flows[$flow_id]);
        update_option(self::OPTION_KEY, $flows);
        
        // Remove flow access from users
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_flosc_flow_access' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($flow_id) . '%'
        ));
        
        flush_rewrite_rules();
        
        return true;
    }
    
    /**
     * Normalize flow data with defaults
     */
    private function normalize_flow_data($data) {
        $defaults = [
            'id' => '',
            'slug' => '',
            'custom_domain' => '',
            'status' => 'active',
            'product' => [
                'name' => 'FLOSC App',
                'tagline' => '',
                'emoji' => '🎯',
                'logo_url' => '',
                'primary_color' => '#4f46e5',
                'share_text' => '',
            ],
            'ivr_file' => 'ivr.md',
            'wp_category_id' => 0,
            'quiz_type' => 'flosc_sample_text_based_quiz',
            'created_at' => '',
            'updated_at' => '',
        ];
        
        $flow = wp_parse_args($data, $defaults);
        
        // Normalize product sub-array
        $flow['product'] = wp_parse_args(
            $data['product'] ?? [],
            $defaults['product']
        );
        
        // Sanitize
        $flow['id'] = sanitize_key($flow['id']);
        $flow['slug'] = sanitize_title($flow['slug']);
        $flow['custom_domain'] = sanitize_text_field($flow['custom_domain']);
        $flow['status'] = in_array($flow['status'], ['active', 'draft']) ? $flow['status'] : 'draft';
        $flow['ivr_file'] = sanitize_file_name($flow['ivr_file']);
        $flow['wp_category_id'] = intval($flow['wp_category_id']);
        $flow['quiz_type'] = sanitize_key($flow['quiz_type']);
        
        // Sanitize product
        $flow['product']['name'] = sanitize_text_field($flow['product']['name']);
        $flow['product']['tagline'] = sanitize_text_field($flow['product']['tagline']);
        $flow['product']['emoji'] = sanitize_text_field($flow['product']['emoji']);
        $flow['product']['logo_url'] = esc_url_raw($flow['product']['logo_url']);
        $flow['product']['primary_color'] = sanitize_hex_color($flow['product']['primary_color']) ?: '#4f46e5';
        $flow['product']['share_text'] = sanitize_text_field($flow['product']['share_text']);
        
        return $flow;
    }
    
    /**
     * Check if user can access flow admin
     */
    public function can_access_flow_admin($flow_id, $user_id = null) {
        $user_id = $user_id ?: get_current_user_id();
        
        // Administrators see all flows
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Must be at least Editor
        if (!user_can($user_id, 'edit_others_posts')) {
            return false;
        }
        
        // Check flow assignment
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true);
        return is_array($allowed) && in_array($flow_id, $allowed);
    }
    
    /**
     * Grant user access to a flow
     */
    public function grant_flow_access($user_id, $flow_id) {
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true) ?: [];
        
        if (!is_array($allowed)) {
            $allowed = [];
        }
        
        if (!in_array($flow_id, $allowed)) {
            $allowed[] = $flow_id;
            update_user_meta($user_id, '_flosc_flow_access', $allowed);
        }
        
        return true;
    }
    
    /**
     * Revoke user access to a flow
     */
    public function revoke_flow_access($user_id, $flow_id) {
        $allowed = get_user_meta($user_id, '_flosc_flow_access', true) ?: [];
        
        if (!is_array($allowed)) {
            return true;
        }
        
        $allowed = array_filter($allowed, function($id) use ($flow_id) {
            return $id !== $flow_id;
        });
        
        update_user_meta($user_id, '_flosc_flow_access', array_values($allowed));
        
        return true;
    }
    
    /**
     * Get users with access to a flow
     */
    public function get_flow_users($flow_id) {
        global $wpdb;
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_flosc_flow_access' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like('"' . $flow_id . '"') . '%'
        ));
        
        if (empty($user_ids)) {
            return [];
        }
        
        return get_users([
            'include' => $user_ids,
            'fields' => ['ID', 'display_name', 'user_email'],
        ]);
    }
    
    /**
     * Get available IVR files
     */
    public function get_available_ivr_files() {
        $files = [];
        $base_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
        
        // Get files in base directory
        $base_files = glob($base_path . '*.md');
        foreach ($base_files as $file) {
            $filename = basename($file);
            if (strpos($filename, 'ivr') !== false || $filename === 'ivr.md') {
                $files[] = $filename;
            }
        }
        
        // Get subdirectories (each could be a flow's folder)
        $subdirs = glob($base_path . '*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $dirname = basename($subdir);
            $ivr_path = $subdir . '/ivr.md';
            if (file_exists($ivr_path)) {
                $files[] = $dirname . '/ivr.md';
            }
        }
        
        return $files;
    }
    
    /**
     * Get available quiz types
     */
    public function get_available_quiz_types() {
        return [
            'flosc_sample_text_based_quiz' => 'Text-Based Quiz',
            'flosc_sample_audio_quiz' => 'Audio Quiz (Pronunciation)',
            'multiplechoice_quiz' => 'Multiple Choice',
            'truefalse_quiz' => 'True/False',
            'wordmatching_quiz' => 'Word Matching',
        ];
    }
    
    /**
     * Migrate from legacy settings (v1.2.1) to flows (v1.2.2)
     */
    public function maybe_migrate_from_legacy() {
        // If flows already exist, don't migrate
        if (get_option(self::OPTION_KEY) !== false) {
            return false;
        }
        
        // Create default flow from existing settings
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
            'ivr_file' => 'ivr.md',
            'wp_category_id' => intval(get_option('flosc_lessons_category', 0)),
            'quiz_type' => get_option('flosc_quiz_type', 'flosc_sample_text_based_quiz'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        update_option(self::OPTION_KEY, ['default' => $default_flow]);
        
        if (FLOSC_DEBUG) {
            error_log('FLOSC: Migrated legacy settings to flows system');
        }
        
        return true;
    }
}

/**
 * Global accessor function
 */
function flosc_flows() {
    return FLOSC_Flow_Manager::instance();
}
