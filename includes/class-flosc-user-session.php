<?php
/**
 * FLOSC User Session
 * Single source of truth for user, flow, quiz, and conversational context
 *
 * @package FLOSC
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_User_Session {
    private $flosc_user_id;
    private $flosc_flow_id;
    private $flosc_state;

    /**
     * Constructor - builds unified state object
     *
     * @param int $flosc_user_id WordPress user ID (0 for visitors)
     * @param string $flosc_flow_id FLOSC flow ID (2-digit: 01, 02, etc.)
     */
    public function __construct($flosc_user_id, $flosc_flow_id) {
        $this->flosc_user_id = $flosc_user_id;
        $this->flosc_flow_id = $flosc_flow_id;
        $this->flosc_state = $this->flosc_build_state();
    }

    /**
     * Build unified state array
     * Leverages existing Condition Evaluator - don't rebuild what works
     *
     * @return array Complete state array with flosc_ prefixed keys
     */
    private function flosc_build_state() {
        // Use existing Condition Evaluator's build_context()
        // Pass flow_id in additional context
        $flosc_additional = ['flow_id' => $this->flosc_flow_id];
        $flosc_context = FLOSC_Condition_Evaluator::build_context($this->flosc_user_id, $flosc_additional);

        // Get current flow configuration (use singleton instance, not new)
        $flosc_flow_manager = FLOSC_Flow_Manager::instance();
        $flosc_flow = $flosc_flow_manager->get_flow($this->flosc_flow_id);

        return [
            // Identity & Access
            'flosc_user_id' => $this->flosc_user_id,
            'flosc_flow_id' => $this->flosc_flow_id,
            'flosc_phase' => $flosc_context['phase'] ?? 'freeline',
            'flosc_access_level' => $this->flosc_determine_access_level($flosc_context),
            'flosc_user_type' => $this->flosc_determine_user_type($flosc_context),

            // Flow Configuration
            'flosc_flow' => [
                'flosc_name' => $flosc_flow['name'] ?? 'Unknown',
                'flosc_slug' => $flosc_flow['slug'] ?? '',
                'flosc_wp_category_id' => $flosc_flow['wp_category_id'] ?? 0,
                'flosc_ivr_file' => $flosc_flow['ivr_file'] ?? 'flosc_default_ivr.md',
                'flosc_custom_domain' => $flosc_flow['custom_domain'] ?? '',
            ],

            // Quiz State
            'flosc_quiz' => [
                'flosc_taken' => $flosc_context['quiz_taken'] ?? false,
                'flosc_score' => $flosc_context['score'] ?? 0,
                'flosc_results_shown' => $flosc_context['quiz_results_shown'] ?? false,
                'flosc_free_lesson_number' => $flosc_context['free_lesson_number'] ?? null,
                'flosc_free_lesson_assigned' => $flosc_context['free_lesson_assigned'] ?? false,
                'flosc_free_lesson_viewed' => $flosc_context['free_lesson_viewed'] ?? false,
            ],

            // IVR Context
            'flosc_ivr' => [
                'flosc_active_conditions' => $flosc_context['active_conditions'] ?? [],
                'flosc_visible_autoprompts' => $this->flosc_get_visible_autoprompts($flosc_context, $flosc_flow),
                'flosc_boundary_rules' => $this->flosc_get_boundary_rules($flosc_context),
            ],

            // Learning Progress
            'flosc_progress' => [
                'flosc_lessons_completed' => $flosc_context['lessons_completed'] ?? 0,
                'flosc_current_lesson' => $flosc_context['current_lesson'] ?? null,
                'flosc_last_activity' => $flosc_context['last_activity'] ?? null,
            ],

            // Session
            'flosc_session_id' => $flosc_context['session_id'] ?? null,
            'flosc_visitor_id' => $flosc_context['visitor_id'] ?? null,
        ];
    }

    /**
     * Get state value by key, or entire state if no key provided
     *
     * @param string|null $flosc_key State key to retrieve
     * @return mixed State value or entire state array
     */
    public function flosc_get($flosc_key = null) {
        if ($flosc_key === null) {
            return $this->flosc_state;
        }
        return $this->flosc_state[$flosc_key] ?? null;
    }

    /**
     * Get state hash for cache keying
     * Only includes state properties that affect AI responses
     *
     * @return string MD5 hash of cache-relevant state
     */
    public function flosc_get_state_hash() {
        $flosc_cache_relevant = [
            'flosc_phase' => $this->flosc_state['flosc_phase'],
            'flosc_access_level' => $this->flosc_state['flosc_access_level'],
            'flosc_quiz_taken' => $this->flosc_state['flosc_quiz']['flosc_taken'],
            'flosc_free_lesson_number' => $this->flosc_state['flosc_quiz']['flosc_free_lesson_number'],
            'flosc_flow_id' => $this->flosc_state['flosc_flow_id'],
        ];
        return md5(json_encode($flosc_cache_relevant));
    }

    /**
     * Determine user type based on context
     * Returns: flosc_admin, flosc_member, flosc_guest, or flosc_visitor
     *
     * @param array $flosc_context Condition evaluator context
     * @return string User type with flosc_ prefix
     */
    private function flosc_determine_user_type($flosc_context) {
        // Admin: has manage_options capability (global)
        if ($this->flosc_user_id > 0 && current_user_can('manage_options')) {
            return 'flosc_admin';
        }

        // Member: purchased full access (per-flow)
        if (($flosc_context['access_level'] ?? '') === 'member' || ($flosc_context['purchased'] ?? false)) {
            return 'flosc_member';
        }

        // Guest: completed quiz (per-flow)
        if ($flosc_context['quiz_taken'] ?? false) {
            return 'flosc_guest';
        }

        // Visitor: default (per-flow)
        return 'flosc_visitor';
    }

    /**
     * Determine access level based on context
     *
     * @param array $flosc_context Condition evaluator context
     * @return string Access level: member|guest|user|visitor
     */
    private function flosc_determine_access_level($flosc_context) {
        if ($flosc_context['purchased'] ?? false) {
            return 'member';
        }
        if ($flosc_context['quiz_taken'] ?? false) {
            return 'guest';
        }
        if ($this->flosc_user_id > 0) {
            return 'user';
        }
        return 'visitor';
    }

    /**
     * Get visible autoprompts for current phase and conditions
     *
     * @param array $flosc_context Condition evaluator context
     * @param array $flosc_flow Flow configuration
     * @return array Visible autoprompt options
     */
    private function flosc_get_visible_autoprompts($flosc_context, $flosc_flow) {
        $flosc_autoprompts = [];

        // Parse IVR file if it exists
        $flosc_ivr_file = $flosc_flow['ivr_file'] ?? 'flosc_default_ivr.md';
        if (!empty($flosc_ivr_file) && class_exists('FLOSC_IVR_Parser')) {
            try {
                $flosc_ivr_parser = new FLOSC_IVR_Parser($flosc_ivr_file);
                $flosc_phase = $this->flosc_state['flosc_phase'] ?? 'freeline';

                // Get autoprompts for current phase
                if (method_exists($flosc_ivr_parser, 'get_visible_autoprompts')) {
                    $flosc_autoprompts = $flosc_ivr_parser->get_visible_autoprompts($flosc_phase, $flosc_context);
                }
            } catch (Exception $e) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC User Session: Error parsing IVR autoprompts: ' . $e->getMessage());
            }
        }

        return $flosc_autoprompts;
    }

    /**
     * Get boundary rules based on user type
     * Policy ladder: what can this user see/do?
     *
     * @param array $flosc_context Condition evaluator context
     * @return array Boundary rules for current user type
     */
    private function flosc_get_boundary_rules($flosc_context) {
        $flosc_user_type = $this->flosc_determine_user_type($flosc_context);

        $flosc_rules = [
            'flosc_admin' => [
                'flosc_can_see_config' => true,
                'flosc_can_see_all_lessons' => true,
                'flosc_can_see_pricing' => true,
                'flosc_can_debug' => true,
                'flosc_description' => 'Full access to configuration, debugging, and all content',
            ],
            'flosc_member' => [
                'flosc_can_see_all_lessons' => true,
                'flosc_can_see_pricing' => false, // Already purchased
                'flosc_can_see_catalog' => true,
                'flosc_description' => 'Full lesson access, supportive learning coach mode',
            ],
            'flosc_guest' => [
                'flosc_can_see_free_lesson' => true,
                'flosc_can_see_catalog' => true, // Titles only
                'flosc_can_see_pricing' => true,
                'flosc_must_encourage_purchase' => true,
                'flosc_description' => 'Quiz completed - can access assigned free lesson only',
            ],
            'flosc_visitor' => [
                'flosc_can_see_catalog' => true, // Titles only
                'flosc_must_encourage_quiz' => true,
                'flosc_can_see_pricing' => false, // Only after quiz
                'flosc_description' => 'New visitor - primary goal is quiz completion',
            ],
        ];

        return $flosc_rules[$flosc_user_type] ?? $flosc_rules['flosc_visitor'];
    }

    /**
     * Export state as JSON for debugging or logging
     *
     * @return string JSON representation of state
     */
    public function flosc_to_json() {
        return json_encode($this->flosc_state, JSON_PRETTY_PRINT);
    }

    /**
     * Check if user has specific capability based on boundary rules
     *
     * @param string $flosc_capability Capability to check (e.g., 'flosc_can_see_all_lessons')
     * @return bool Whether user has this capability
     */
    public function flosc_can($flosc_capability) {
        $flosc_rules = $this->flosc_state['flosc_ivr']['flosc_boundary_rules'] ?? [];
        return $flosc_rules[$flosc_capability] ?? false;
    }

    /**
     * Generate 5-character random visitor ID
     *
     * @return string 5-character alphanumeric ID
     */
    public static function flosc_generate_visitor_id_suffix() {
        return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 5);
    }

    /**
     * Build full visitor ID with flow context
     *
     * @param string $flosc_flow_id 2-digit flow ID
     * @return string Full visitor ID: flosc_flow_01_visitor_abc12
     */
    public static function flosc_build_visitor_id($flosc_flow_id) {
        $flosc_suffix = self::flosc_generate_visitor_id_suffix();
        return "flosc_flow_{$flosc_flow_id}_visitor_{$flosc_suffix}";
    }
}
