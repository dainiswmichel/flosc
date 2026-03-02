<?php
/**
 * FLOSC Sample Text-Based Quiz
 *
 * User enters comma-separated numbers, system scores them.
 * Example: Correct answers = "1,2,3,4,5,6,7,8,9,10"
 *          User enters "3,7,9" = 30%
 *          User enters "1,3,7,10" = 40%
 *
 * @package FLOSC
 * @version 9.2.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Sample_Text_Based_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'flosc_sample_text_based_quiz';
    }

    public function get_name() {
        return 'FLOSC Sample 1-10 Numbers Quiz';
    }

    public function get_description() {
        return 'Input the following numbers: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10';
    }

    public function get_icon() {
        return '✍️';
    }

    public function needs_audio() {
        return false;
    }

    public function needs_stt() {
        return false;
    }

    public function needs_ai_analysis() {
        return false;
    }

    public function get_instructions() {
        return "Input the following numbers:\n\n1, 2, 3, 4, 5, 6, 7, 8, 9, 10";
    }

    public function get_default_content() {
        return '1,2,3,4,5,6,7,8,9,10';
    }

    public function validate_input($input) {
        if (empty($input) || !is_string($input)) {
            return new WP_Error('invalid_input', 'Please enter your answers.');
        }

        $separator = $this->get_setting('separator', ',');
        $items = $this->parse_input($input, $separator);

        if (empty($items)) {
            return new WP_Error('empty_input', 'No valid answers found.');
        }

        return true;
    }

    public function analyze($input, $expected_content, $context = []) {
        $separator = $this->get_setting('separator', ',');
        $case_sensitive = $this->get_setting('case_sensitive', false);
        $partial_credit = $this->get_setting('partial_credit', false);

        // Parse correct answers
        $correct_answers = $this->parse_input($expected_content, $separator);

        // Parse user answers
        $user_answers = $this->parse_input($input, $separator);

        // Normalize if case-insensitive
        if (!$case_sensitive) {
            $correct_answers = array_map('strtolower', $correct_answers);
            $user_answers = array_map('strtolower', $user_answers);
        }

        // Trim whitespace
        $correct_answers = array_map('trim', $correct_answers);
        $user_answers = array_map('trim', $user_answers);

        // Count correct
        $correct = [];
        $incorrect = [];
        $total_correct = 0;

        foreach ($user_answers as $answer) {
            if (in_array($answer, $correct_answers)) {
                $correct[] = $answer;
                $total_correct++;
            } else {
                $incorrect[] = $answer;
            }
        }

        // Calculate score
        $total_possible = count($correct_answers);
        $score = $this->calculate_percentage($total_correct, $total_possible);

        // Determine response key
        $response_key = $this->get_response_key_from_score($score);

        return [
            'score' => $score,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'response_key' => $response_key,
            'details' => [
                'total_correct' => $total_correct,
                'total_possible' => $total_possible,
                'total_answered' => count($user_answers),
                'missed' => array_diff($correct_answers, $user_answers),
            ],
        ];
    }

    public function get_settings_fields() {
        return [
            'separator' => [
                'type' => 'text',
                'label' => 'Answer Separator',
                'default' => ',',
                'description' => 'Character(s) separating answers (e.g., comma, semicolon, pipe)',
            ],
            'case_sensitive' => [
                'type' => 'checkbox',
                'label' => 'Case Sensitive',
                'default' => false,
                'description' => 'Require exact letter case matches',
            ],
            'partial_credit' => [
                'type' => 'checkbox',
                'label' => 'Partial Credit',
                'default' => false,
                'description' => 'Give credit for partially correct answers (future feature)',
            ],
        ];
    }

    public function get_default_response_templates() {
        return [
            '0-30' => "**Score: {score}%**\n\nYou got {total_correct} out of {total_possible} correct.\n\n{lesson_recommendations}",
            '31-60' => "**Score: {score}%**\n\nGood effort! You got {total_correct} out of {total_possible} correct.\n\n{lesson_recommendations}",
            '61-85' => "**Score: {score}%**\n\nNice work! You got {total_correct} out of {total_possible} correct.\n\n{lesson_recommendations}",
            '86-100' => "**Score: {score}%**\n\nExcellent! You got {total_correct} out of {total_possible} correct!\n\n{lesson_recommendations}",
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
        if (!empty($lessons)) {
            $free_lesson = $lessons[0];
            $paid_lessons = array_slice($lessons, 1);

            $lesson_text = "\n**📚 Recommended Lessons:**\n\n";
            $lesson_text .= "🎁 **{$free_lesson['title']}** (FREE)\n";
            $lesson_text .= "   {$free_lesson['reason']}\n";

            if (!empty($paid_lessons)) {
                $lesson_text .= "\n🔒 **Unlock " . count($paid_lessons) . " more lessons:**\n";
                foreach ($paid_lessons as $lesson) {
                    $lesson_text .= "   • {$lesson['title']}\n";
                }
            }
        }

        // Show what they got correct
        if (!empty($analysis['correct'])) {
            $lesson_text .= "\n\n✅ **Correct:** " . implode(', ', $analysis['correct']);
        }

        // Show what they missed
        if (!empty($details['missed'])) {
            $lesson_text .= "\n❌ **Missed:** " . implode(', ', $details['missed']);
        }

        // Replace placeholders
        $message = str_replace(
            [
                '{score}',
                '{total_correct}',
                '{total_possible}',
                '{lesson_recommendations}',
            ],
            [
                $score,
                $details['total_correct'],
                $details['total_possible'],
                $lesson_text,
            ],
            $template
        );

        return $message;
    }

    /**
     * Helper: Parse input string into array
     */
    private function parse_input($input, $separator) {
        if (empty($input)) return [];

        $items = explode($separator, $input);
        $items = array_map('trim', $items);
        $items = array_filter($items); // Remove empty

        return array_values($items);
    }
}
