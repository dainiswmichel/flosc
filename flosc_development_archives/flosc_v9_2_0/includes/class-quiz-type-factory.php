<?php
/**
 * Quiz Type Factory
 *
 * Dynamically loads and manages quiz types.
 * Provides registry for all available quiz types.
 *
 * @package FLOSC
 * @version 3.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Quiz_Type_Factory {

    /**
     * Loaded quiz type instances
     * @var array
     */
    private static $quiz_types = [];

    /**
     * Whether quiz types have been loaded
     * @var bool
     */
    private static $loaded = false;

    /**
     * Load all quiz types from quiz-types directory
     */
    private static function load_quiz_types() {
        if (self::$loaded) {
            return;
        }

        // Load abstract base class first
        require_once FLOSC_PLUGIN_DIR . 'includes/quiz-types/abstract-quiz-type.php';

        // Get all quiz type files
        $quiz_types_dir = FLOSC_PLUGIN_DIR . 'includes/quiz-types/';
        $files = glob($quiz_types_dir . 'class-*-quiz.php');

        foreach ($files as $file) {
            require_once $file;

            // Extract class name from filename
            // class-simple-scoring-quiz.php → FLOSC_Simple_Scoring_Quiz
            $filename = basename($file, '.php');
            $class_name = str_replace('class-', '', $filename);
            $class_name = str_replace('-', '_', $class_name);
            $class_name = 'FLOSC_' . ucwords($class_name, '_');

            // Instantiate and register
            if (class_exists($class_name)) {
                $instance = new $class_name();
                if ($instance instanceof FLOSC_Abstract_Quiz_Type) {
                    self::$quiz_types[$instance->get_id()] = $instance;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get a specific quiz type by ID
     *
     * @param string $quiz_type_id The quiz type ID
     * @return FLOSC_Abstract_Quiz_Type|null Quiz type instance or null if not found
     */
    public static function get_quiz_type($quiz_type_id) {
        self::load_quiz_types();

        if (isset(self::$quiz_types[$quiz_type_id])) {
            return self::$quiz_types[$quiz_type_id];
        }

        return null;
    }

    /**
     * Get all available quiz types
     *
     * @return array Array of quiz type instances
     */
    public static function get_all_quiz_types() {
        self::load_quiz_types();
        return self::$quiz_types;
    }

    /**
     * Get quiz types formatted for admin dropdown
     *
     * @return array Array of ['id' => 'Name'] pairs
     */
    public static function get_quiz_types_for_dropdown() {
        self::load_quiz_types();

        $options = [];
        foreach (self::$quiz_types as $id => $quiz_type) {
            $options[$id] = $quiz_type->get_icon() . ' ' . $quiz_type->get_name();
        }

        return $options;
    }

    /**
     * Check if a quiz type exists
     *
     * @param string $quiz_type_id The quiz type ID
     * @return bool True if exists
     */
    public static function quiz_type_exists($quiz_type_id) {
        self::load_quiz_types();
        return isset(self::$quiz_types[$quiz_type_id]);
    }

    /**
     * Get quiz type metadata
     *
     * @param string $quiz_type_id The quiz type ID
     * @return array|null Metadata array or null if not found
     */
    public static function get_quiz_type_meta($quiz_type_id) {
        $quiz_type = self::get_quiz_type($quiz_type_id);

        if (!$quiz_type) {
            return null;
        }

        return [
            'id' => $quiz_type->get_id(),
            'name' => $quiz_type->get_name(),
            'description' => $quiz_type->get_description(),
            'icon' => $quiz_type->get_icon(),
            'needs_audio' => $quiz_type->needs_audio(),
            'needs_stt' => $quiz_type->needs_stt(),
            'needs_ai_analysis' => $quiz_type->needs_ai_analysis(),
            'instructions' => $quiz_type->get_instructions(),
            'default_content' => $quiz_type->get_default_content(),
            'settings_fields' => $quiz_type->get_settings_fields(),
            'response_templates' => $quiz_type->get_default_response_templates(),
        ];
    }

    /**
     * Get active quiz type (from plugin settings)
     *
     * @return FLOSC_Abstract_Quiz_Type|null Active quiz type instance
     */
    public static function get_active_quiz_type() {
        $active_quiz_type_id = get_option('flosc_quiz_type', 'flosc_sample_text_based_quiz');
        return self::get_quiz_type($active_quiz_type_id);
    }

    /**
     * Validate quiz type configuration
     *
     * @param string $quiz_type_id The quiz type ID
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_quiz_type_config($quiz_type_id) {
        if (!self::quiz_type_exists($quiz_type_id)) {
            return new WP_Error(
                'invalid_quiz_type',
                sprintf('Quiz type "%s" does not exist.', $quiz_type_id)
            );
        }

        $quiz_type = self::get_quiz_type($quiz_type_id);

        // Check if content is set
        $content = get_option('flosc_quiz_content_' . $quiz_type_id, '');
        if (empty($content)) {
            $content = $quiz_type->get_default_content();
            update_option('flosc_quiz_content_' . $quiz_type_id, $content);
        }

        // Check if audio/STT providers are configured (if needed)
        if ($quiz_type->needs_audio()) {
            $audio_provider = get_option('flosc_audio_provider', '');
            if (empty($audio_provider)) {
                return new WP_Error(
                    'missing_audio_provider',
                    'This quiz type requires audio recording. Please configure an audio provider.'
                );
            }
        }

        if ($quiz_type->needs_stt()) {
            $stt_provider = get_option('flosc_stt_provider', '');
            if (empty($stt_provider)) {
                return new WP_Error(
                    'missing_stt_provider',
                    'This quiz type requires speech-to-text. Please configure an STT provider.'
                );
            }
        }

        if ($quiz_type->needs_ai_analysis()) {
            $ai_provider = get_option('flosc_ai_provider', '');
            if (empty($ai_provider)) {
                return new WP_Error(
                    'missing_ai_provider',
                    'This quiz type requires AI analysis. Please configure an AI provider.'
                );
            }
        }

        return true;
    }

    /**
     * Get quiz types that work without configuration (text-only)
     *
     * @return array Array of quiz type IDs
     */
    public static function get_zero_config_quiz_types() {
        self::load_quiz_types();

        $zero_config = [];
        foreach (self::$quiz_types as $id => $quiz_type) {
            if (!$quiz_type->needs_audio() && !$quiz_type->needs_stt() && !$quiz_type->needs_ai_analysis()) {
                $zero_config[] = $id;
            }
        }

        return $zero_config;
    }

    /**
     * Get quiz types by capability requirement
     *
     * @param string $capability 'audio', 'stt', or 'ai'
     * @return array Array of quiz type IDs
     */
    public static function get_quiz_types_by_capability($capability) {
        self::load_quiz_types();

        $filtered = [];
        foreach (self::$quiz_types as $id => $quiz_type) {
            switch ($capability) {
                case 'audio':
                    if ($quiz_type->needs_audio()) {
                        $filtered[] = $id;
                    }
                    break;
                case 'stt':
                    if ($quiz_type->needs_stt()) {
                        $filtered[] = $id;
                    }
                    break;
                case 'ai':
                    if ($quiz_type->needs_ai_analysis()) {
                        $filtered[] = $id;
                    }
                    break;
            }
        }

        return $filtered;
    }
}
