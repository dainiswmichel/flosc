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
        
        // v1.2.3: Properly remove flow access from users (handles serialized arrays correctly)
        $users_with_access = $this->get_flow_users($flow_id);
        foreach ($users_with_access as $user) {
            $this->revoke_flow_access($user->ID, $flow_id);
        }
        
        flush_rewrite_rules();
        
        return true;
    }
    
    /**
     * Normalize flow data with defaults
     * v1.2.3: Added 'overrides' for per-flow settings
     */
    private function normalize_flow_data($data) {
        $defaults = [
            'id' => '',
            'slug' => '',
            'custom_domain' => '',
            'status' => 'active',
            'identity' => [
                'name' => 'FLOSC App',
                'tagline' => '',
                'chatlogo_url' => '',
                'favicon_url' => '',
                'primary_color' => '#4f46e5',
                'share_text' => '',
            ],
            'ivr_file' => 'flosc_default_technical_ivr.md',
            'wp_category_id' => 0,
            // Empty = no quiz until admin enables one on the Quiz tab.
            'quiz_type' => '',
            'enabled_quizzes' => [],
            'created_at' => '',
            'updated_at' => '',
            // v1.2.3: Per-flow settings overrides
            'overrides' => [
                'style' => ['use_global' => true],
                'ai' => ['use_global' => true],
                'email' => ['use_global' => true],
                'ai_knowledge' => ['use_global' => true],
                'offers' => ['use_global' => true],
                'payments' => ['use_global' => true],
                'lessons' => ['use_global' => true],
            ],
        ];
        
        $flow = wp_parse_args($data, $defaults);
        
        // Normalize identity sub-array
        $flow['identity'] = wp_parse_args(
            $data['identity'] ?? [],
            $defaults['identity']
        );
        
        // v1.2.3: Normalize overrides sub-array
        $flow['overrides'] = wp_parse_args(
            $data['overrides'] ?? [],
            $defaults['overrides']
        );
        // Ensure each override group has use_global flag
        foreach ($defaults['overrides'] as $key => $default_override) {
            if (!isset($flow['overrides'][$key])) {
                $flow['overrides'][$key] = $default_override;
            } elseif (!isset($flow['overrides'][$key]['use_global'])) {
                $flow['overrides'][$key]['use_global'] = true;
            }
        }
        
        // Sanitize
        $flow['id'] = sanitize_key($flow['id']);
        $flow['slug'] = sanitize_title($flow['slug']);
        $flow['custom_domain'] = sanitize_text_field($flow['custom_domain']);
        $flow['status'] = in_array($flow['status'], ['active', 'draft']) ? $flow['status'] : 'draft';
        // v1.2.3: Allow subdirectory paths for IVR files (e.g., 'lesaep/ivr.md')
        $flow['ivr_file'] = preg_replace('#[^a-zA-Z0-9/_.-]#', '', $flow['ivr_file']);
        $flow['wp_category_id'] = intval($flow['wp_category_id']);
        $flow['quiz_type'] = sanitize_key($flow['quiz_type']);
        
        // Sanitize identity
        $flow['identity']['name'] = sanitize_text_field($flow['identity']['name']);
        $flow['identity']['tagline'] = sanitize_text_field($flow['identity']['tagline']);
        $flow['identity']['chatlogo_url'] = esc_url_raw($flow['identity']['chatlogo_url']);
        $flow['identity']['favicon_url'] = esc_url_raw($flow['identity']['favicon_url'] ?? '');
        $flow['identity']['primary_color'] = sanitize_hex_color($flow['identity']['primary_color']) ?: '#4f46e5';
        $flow['identity']['share_text'] = sanitize_text_field($flow['identity']['share_text']);
        
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
     * v1.2.3: More robust serialized array handling
     */
    public function get_flow_users($flow_id) {
        $candidate_ids = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( '_flosc_flow_access' )
            : array();

        if ( empty( $candidate_ids ) ) {
            return array();
        }

        $matching_users = array();
        foreach ( $candidate_ids as $user_id ) {
            $user_id = (int) $user_id;
            $allowed = get_user_meta( $user_id, '_flosc_flow_access', true );
            if ( is_array( $allowed ) && in_array( $flow_id, $allowed, true ) ) {
                $matching_users[] = $user_id;
            }
        }

        if ( empty( $matching_users ) ) {
            return array();
        }

        return get_users(
            array(
                'include' => $matching_users,
                'fields'  => array( 'ID', 'display_name', 'user_email' ),
            )
        );
    }
    
    /**
     * Get available IVR files.
     * v1.2.3: Looks for *_ivr.md files in ai_configuration_files/.
     * Per WordPress.org policy, files are resolved uploads-first via flosc_config_glob().
     */
    public function get_available_ivr_files() {
        $files = [];
        
        // Get all *_ivr.md files — §2: union shipped defaults with uploaded/edited files (uploads wins).
        // flosc_config_glob() handles the search and returns paths in uploads-first order.
        $ivr_files = flosc_config_glob('*_ivr.md');
        foreach ($ivr_files as $file) {
            $files[] = basename($file);
        }

        // Also check for legacy ivr.md (backward compatibility)
        if (file_exists(flosc_config_file('ivr.md'))) {
            $files[] = 'ivr.md';
        }
        
        // Sort alphabetically
        sort($files);
        
        return $files;
    }
    
    /**
     * Get available quiz types
     */
    public function get_available_quiz_types() {
        return [
            'flosc_sample_data_numbers_quiz' => 'Text-Based Quiz',
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
        
        // Generic WordPress.org / fresh-install model: ONE active example flow
        // with an IVR that ships in the package. Specialty products (LeSAEp,
        // Solfeggio, Br3nda, etc.) are admin imports — not auto-activated.
        $ivr_file = 'flosc_default_technical_ivr.md';
        $shipped  = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $ivr_file;
        if (!file_exists($shipped)) {
            $ivr_file = 'flosc_default_ivr.md';
        }

        $default_flows = array(
            'default' => array(
                'id'            => 'default',
                'slug'          => get_option('flosc_app_slug', 'flosc'),
                'custom_domain' => get_option('flosc_custom_domain', ''),
                'status'        => 'active',
                'identity'      => array(
                    'name'          => get_option('flosc_product_name', 'FLOSC'),
                    'tagline'       => get_option('flosc_product_tagline', 'Try before you buy'),
                    'chatlogo_url'  => get_option('flosc_product_logo', ''),
                    'primary_color' => get_option('flosc_primary_color', '#4f46e5'),
                    'share_text'    => get_option('flosc_share_text', ''),
                ),
                'ivr_file'       => $ivr_file,
                'wp_category_id' => intval(get_option('flosc_lessons_category', 0)),
                'created_at'     => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ),
        );

        update_option(self::OPTION_KEY, $default_flows);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('FLOSC: Created single generic default flow (ivr=' . $ivr_file . ')');
        }

        return true;
    }
    
    /**
     * Get setting value with flow override support
     * v1.2.3: Checks flow override first, falls back to global option
     * 
     * @param string $option_name The wp_options key
     * @param string $override_group Which override group (style, ai, email, etc.)
     * @param string $override_key Key within the override group (optional, defaults to option_name)
     * @param mixed $default Default value if neither found
     * @param string|null $flow_id Flow ID (null = use current flow)
     * @return mixed The setting value
     */
    public function get_setting($option_name, $override_group, $override_key = null, $default = null, $flow_id = null) {
        // Determine flow
        if ($flow_id === null) {
            $flow = $this->get_current_flow();
        } else {
            $flow = $this->get_flow($flow_id);
        }
        
        // If no flow found, use global
        if (!$flow) {
            return get_option($option_name, $default);
        }
        
        // Check if flow uses global settings for this group
        $use_global = $flow['overrides'][$override_group]['use_global'] ?? true;
        
        if ($use_global) {
            return get_option($option_name, $default);
        }
        
        // Use flow override
        $key = $override_key ?: $option_name;
        return $flow['overrides'][$override_group][$key] ?? get_option($option_name, $default);
    }
    
    /**
     * Get current flow from context
     * Wrapper around global get_current_flow() function
     */
    public function get_current_flow() {
        if (function_exists('get_current_flow')) {
            $flow_data = get_current_flow();
            return $flow_data ?: null;
        }
        return null;
    }
    
    /**
     * Update flow override settings
     * v1.2.3: Sets override values for a specific group
     * 
     * @param string $flow_id The flow ID
     * @param string $override_group Which override group (style, ai, email, etc.)
     * @param array $values The settings values
     * @param bool $use_global Whether to use global settings
     * @return bool|WP_Error
     */
    public function update_override($flow_id, $override_group, $values, $use_global = false) {
        $flows = $this->get_all_flows();
        
        if (!isset($flows[$flow_id])) {
            return new WP_Error('not_found', 'Flow not found');
        }
        
        // Initialize overrides if needed
        if (!isset($flows[$flow_id]['overrides'])) {
            $flows[$flow_id]['overrides'] = [];
        }
        
        // Set the override
        $flows[$flow_id]['overrides'][$override_group] = array_merge(
            ['use_global' => $use_global],
            $values
        );
        
        $flows[$flow_id]['updated_at'] = current_time('mysql');
        
        return update_option(self::OPTION_KEY, $flows);
    }
}

/**
 * Global accessor function
 */
function flosc_flows() {
    return FLOSC_Flow_Manager::instance();
}
