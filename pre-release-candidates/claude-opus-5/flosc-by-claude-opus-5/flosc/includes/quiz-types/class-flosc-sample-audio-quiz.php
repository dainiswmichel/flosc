<?php
/**
 * FLOSC Sample Audio Quiz
 *
 * Audio-based quiz where user records speaking numbers in order.
 * Uses STT transcription to score responses.
 *
 * @package FLOSC
 * @version 9.2.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Sample_Audio_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'flosc_sample_audio_quiz';
    }

    public function get_name() {
        return 'FLOSC Sample Audio Quiz';
    }

    public function get_description() {
        return 'Read the following series of numbers in order: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10';
    }

    public function get_icon() {
        return '🎤';
    }

    public function needs_audio() {
        return true;
    }

    public function needs_stt() {
        return true;
    }

    public function needs_ai_analysis() {
        return false; // Uses phoneme analysis, not AI
    }

    public function get_instructions() {
        return "Read the following series of numbers in order:\n\n1, 2, 3, 4, 5, 6, 7, 8, 9, 10";
    }

    public function get_default_content() {
        return '1,2,3,4,5,6,7,8,9,10';
    }

    public function validate_input($input) {
        // Input is audio file path or STT transcript
        if (empty($input)) {
            return new WP_Error('invalid_input', __('No audio input received.', 'flosc'));
        }

        return true;
    }

    public function analyze($input, $expected_content, $context = []) {
        // If input is already a transcript (from STT), use it
        // Otherwise, input would be audio file path (handled by main plugin)
        $transcript = is_string($input) ? $input : '';

        // Use existing pronunciation analyzer
        require_once FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';
        $analyzer = new FLOSC_Pronunciation_Analyzer();
        $analysis_result = $analyzer->analyze($transcript, $expected_content);

        // Convert to standard format
        $correct = $analysis_result['correct_items'];
        $incorrect = $analysis_result['missed_items'];
        $score = $analysis_result['score'];

        // Map to lessons
        $lessons = [];
        if (!empty($analysis_result['suggested_lessons'])) {
            foreach ($analysis_result['suggested_lessons'] as $item => $lesson_data) {
                $lessons[] = [
                    'id' => $lesson_data['lesson_id'],
                    'title' => $lesson_data['lesson'],
                    'reason' => "You struggled with: {$item} (phoneme: {$lesson_data['phoneme']})",
                ];
            }
        }

        $response_key = $this->get_response_key_from_score($score);

        return [
            'score' => $score,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'response_key' => $response_key,
            'details' => [
                'total_correct' => count($correct),
                'total_possible' => $analysis_result['total_items'],
                'transcript' => $transcript,
                'expected' => $expected_content,
                'suggested_lessons' => $analysis_result['suggested_lessons'],
            ],
            'lessons' => $lessons,
        ];
    }

    public function map_to_lessons($analysis) {
        // Already done in analyze()
        return $analysis['lessons'] ?? [];
    }

    public function get_settings_fields() {
        return [
            'language' => [
                'type' => 'select',
                'label' => 'Target Language',
                'options' => [
                    'en' => 'English',
                    'es' => 'Spanish',
                    'fr' => 'French',
                    'de' => 'German',
                    'it' => 'Italian',
                    'pt' => 'Portuguese',
                ],
                'default' => 'en',
                'description' => 'Language to analyze',
            ],
            'accent_target' => [
                'type' => 'select',
                'label' => 'Target Accent',
                'options' => [
                    'us' => 'US/Standard American English',
                    'uk' => 'UK/Received Pronunciation',
                    'au' => 'Australian',
                    'ca' => 'Canadian',
                ],
                'default' => 'us',
                'description' => 'Which accent to compare against (for English)',
            ],
        ];
    }

    public function get_default_response_templates() {
        return [
            '0-30' => "**Pronunciation Score: {score}%**\n\nYou need significant practice with these sounds.\n\n{lesson_recommendations}",
            '31-60' => "**Pronunciation Score: {score}%**\n\nGood effort! Focus on improving these sounds:\n\n{lesson_recommendations}",
            '61-85' => "**Pronunciation Score: {score}%**\n\nNice work! Refine these final sounds:\n\n{lesson_recommendations}",
            '86-100' => "**Pronunciation Score: {score}%**\n\nExcellent pronunciation! You're speaking clearly.\n\n{lesson_recommendations}",
        ];
    }

    public function format_results($analysis, $lessons, $response_templates) {
        $score = $analysis['score'];
        $response_key = $analysis['response_key'];
        $details = $analysis['details'];

        // Get template
        $template = $response_templates[$response_key] ?? $response_templates['31-60'] ?? "Score: {score}%";

        // Build lesson text
        $lesson_text = '';

        // Show what they said
        if (!empty($details['transcript'])) {
            $lesson_text .= "**You said:** {$details['transcript']}\n";
            $lesson_text .= "**Expected:** {$details['expected']}\n\n";
        }

        // Show correct items
        if (!empty($analysis['correct'])) {
            $lesson_text .= "✅ **Correct:** " . implode(', ', $analysis['correct']) . "\n\n";
        }

        // Show missed items
        if (!empty($analysis['incorrect'])) {
            $lesson_text .= "❌ **Needs Practice:** " . implode(', ', $analysis['incorrect']) . "\n\n";
        }

        // Lesson recommendations
        if (!empty($lessons)) {
            $free_lesson = $lessons[0];
            $paid_lessons = array_slice($lessons, 1);

            $lesson_text .= "**📚 Recommended Lessons:**\n\n";
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
}
