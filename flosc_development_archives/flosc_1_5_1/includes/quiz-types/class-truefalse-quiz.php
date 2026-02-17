<?php
/**
 * True/False Quiz Type
 *
 * User answers True or False questions.
 * Simple, effective, works out of the box.
 *
 * @package FLOSC
 * @version 3.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_TrueFalse_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'truefalse';
    }

    public function get_name() {
        return 'True/False';
    }

    public function get_description() {
        return 'User answers True or False to statements. Perfect for knowledge checks.';
    }

    public function get_icon() {
        return '✓✗';
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
        return "Answer True or False for each statement.";
    }

    public function get_default_content() {
        return "The sky is blue.|True\nWater freezes at 100°C.|False\nEarth orbits the Sun.|True";
    }

    public function validate_input($input) {
        if (empty($input) || !is_string($input)) {
            return new WP_Error('invalid_input', 'Please enter your answers.');
        }

        return true;
    }

    public function analyze($input, $expected_content, $context = []) {
        // Parse questions
        $questions = $this->parse_questions($expected_content);

        // Parse answers (can be "T,F,T" or "True,False,True")
        $user_answers = $this->parse_user_answers($input);

        $correct = [];
        $incorrect = [];
        $total_correct = 0;

        foreach ($questions as $index => $question) {
            $correct_answer = strtolower($question['answer']);
            $user_answer = isset($user_answers[$index]) ? strtolower($user_answers[$index]) : '';

            // Normalize
            $correct_answer = $this->normalize_answer($correct_answer);
            $user_answer = $this->normalize_answer($user_answer);

            if ($user_answer === $correct_answer) {
                $correct[] = [
                    'question' => $question['text'],
                    'answer' => $user_answer,
                ];
                $total_correct++;
            } else {
                $incorrect[] = [
                    'question' => $question['text'],
                    'user_answer' => $user_answer,
                    'correct_answer' => $correct_answer,
                ];
            }
        }

        $total_possible = count($questions);
        $score = $this->calculate_percentage($total_correct, $total_possible);
        $response_key = $this->get_response_key_from_score($score);

        return [
            'score' => $score,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'response_key' => $response_key,
            'details' => [
                'total_correct' => $total_correct,
                'total_possible' => $total_possible,
            ],
        ];
    }

    public function get_settings_fields() {
        return [
            'answer_format' => [
                'type' => 'select',
                'label' => 'Answer Format',
                'options' => [
                    'tf' => 'T/F',
                    'truefalse' => 'True/False',
                    'yesno' => 'Yes/No',
                ],
                'default' => 'truefalse',
                'description' => 'How users should format their answers',
            ],
        ];
    }

    /**
     * Parse questions from content
     * Format: "Question text|True\nQuestion text|False"
     */
    private function parse_questions($content) {
        $lines = explode("\n", $content);
        $questions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $questions[] = [
                    'text' => trim($parts[0]),
                    'answer' => trim($parts[1]),
                ];
            }
        }

        return $questions;
    }

    /**
     * Parse user answers
     * Accepts: "T,F,T" or "True,False,True" or "true\nfalse\ntrue"
     */
    private function parse_user_answers($input) {
        // Try comma-separated first
        if (strpos($input, ',') !== false) {
            $answers = explode(',', $input);
        } else {
            // Try newline-separated
            $answers = explode("\n", $input);
        }

        return array_map('trim', $answers);
    }

    /**
     * Normalize answer (T/True/Yes → true, F/False/No → false)
     */
    private function normalize_answer($answer) {
        $answer = strtolower(trim($answer));

        if (in_array($answer, ['t', 'true', 'yes', '1'])) {
            return 'true';
        }

        if (in_array($answer, ['f', 'false', 'no', '0'])) {
            return 'false';
        }

        return $answer;
    }
}
