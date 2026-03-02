<?php
/**
 * Abstract Quiz Type Base Class
 *
 * All quiz types must extend this class and implement required methods.
 * This enables FLOSC to support any quiz format without changing core code.
 *
 * @package FLOSC
 * @version 3.0.1
 */

if (!defined('ABSPATH')) exit;

abstract class FLOSC_Abstract_Quiz_Type {

    /**
     * Quiz type identifier (unique slug)
     * @return string
     */
    abstract public function get_id();

    /**
     * Human-readable name for admin UI
     * @return string
     */
    abstract public function get_name();

    /**
     * Description shown in admin quiz type selector
     * @return string
     */
    abstract public function get_description();

    /**
     * Emoji/icon for UI
     * @return string
     */
    abstract public function get_icon();

    /**
     * Does this quiz need audio recording?
     * @return bool
     */
    abstract public function needs_audio();

    /**
     * Does this quiz need speech-to-text?
     * @return bool
     */
    abstract public function needs_stt();

    /**
     * Does this quiz need AI analysis?
     * @return bool
     */
    abstract public function needs_ai_analysis();

    /**
     * Get instructions shown to user
     * Can use placeholders: {quiz_content}, {passing_score}
     * @return string
     */
    abstract public function get_instructions();

    /**
     * Get default quiz content (for fresh installs)
     * @return string
     */
    abstract public function get_default_content();

    /**
     * Validate user input before processing
     * @param mixed $input User's answer (text, audio data, etc.)
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    abstract public function validate_input($input);

    /**
     * Analyze the user's answer
     *
     * @param mixed $input User's answer
     * @param string $expected_content Quiz content from admin settings
     * @param array $context Additional context (STT result, user_id, etc.)
     * @return array {
     *     @type int $score Score 0-100
     *     @type array $correct Items answered correctly
     *     @type array $incorrect Items answered incorrectly
     *     @type string $response_key Key for response template (e.g., '0-30', 'missing_th')
     *     @type array $details Additional analysis details
     * }
     */
    abstract public function analyze($input, $expected_content, $context = []);

    /**
     * Get admin settings fields for this quiz type
     * @return array Settings fields configuration
     */
    abstract public function get_settings_fields();

    /**
     * Get default response templates for this quiz type
     * Keys should match response_key from analyze()
     *
     * @return array ['response_key' => 'Template text with {placeholders}']
     */
    public function get_default_response_templates() {
        return [
            '0-30' => "Your score: {score}%\n\nYou need significant practice. {lesson_recommendations}",
            '31-60' => "Your score: {score}%\n\nGood start! Keep practicing. {lesson_recommendations}",
            '61-85' => "Your score: {score}%\n\nAlmost there! {lesson_recommendations}",
            '86-100' => "Your score: {score}%\n\nExcellent work! {lesson_recommendations}",
        ];
    }

    /**
     * Map analysis results to lesson recommendations
     * Override in subclass for custom lesson mapping
     *
     * @param array $analysis Result from analyze()
     * @return array [['id' => int, 'title' => string, 'reason' => string], ...]
     */
    public function map_to_lessons($analysis) {
        // Default: no lesson mapping
        return [];
    }

    /**
     * Format results for chat display
     * Override for custom formatting
     *
     * @param array $analysis Result from analyze()
     * @param array $lessons Result from map_to_lessons()
     * @param array $response_templates Admin-configured templates
     * @return string Formatted message
     */
    public function format_results($analysis, $lessons, $response_templates) {
        $score = $analysis['score'];
        $response_key = $analysis['response_key'];

        // Get appropriate template
        $template = $response_templates[$response_key] ?? $response_templates['31-60'] ?? "Your score: {score}%";

        // Build lesson text
        $lesson_text = '';
        if (!empty($lessons)) {
            $free_lesson = $lessons[0];
            $paid_lessons = array_slice($lessons, 1);

            $lesson_text = "\n\n**📚 Recommended Lessons:**\n\n";
            $lesson_text .= "🎁 **{$free_lesson['title']}** (FREE)\n";
            $lesson_text .= "   {$free_lesson['reason']}\n";

            if (!empty($paid_lessons)) {
                $lesson_text .= "\n🔒 **Unlock " . count($paid_lessons) . " more lessons:**\n";
                foreach ($paid_lessons as $lesson) {
                    $lesson_text .= "   • {$lesson['title']}\n";
                }
            }
        }

        // Replace placeholders
        $message = str_replace(
            ['{score}', '{lesson_recommendations}'],
            [$score, $lesson_text],
            $template
        );

        return $message;
    }

    /**
     * Get setting value for this quiz type
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get_setting($key, $default = '') {
        return get_option("flosc_quiz_{$this->get_id()}_{$key}", $default);
    }

    /**
     * Helper: Calculate percentage score
     */
    protected function calculate_percentage($correct_count, $total_count) {
        if ($total_count === 0) return 0;
        return round(($correct_count / $total_count) * 100);
    }

    /**
     * Helper: Determine response key from score
     */
    protected function get_response_key_from_score($score) {
        if ($score <= 30) return '0-30';
        if ($score <= 60) return '31-60';
        if ($score <= 85) return '61-85';
        return '86-100';
    }
}
