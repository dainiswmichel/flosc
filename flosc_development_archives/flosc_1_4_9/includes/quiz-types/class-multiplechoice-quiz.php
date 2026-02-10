<?php
/**
 * Multiple Choice Quiz Type
 *
 * Classic quiz format: question with 2-4 options, user picks one.
 * Format: "Question?|A) Option 1|B) Option 2|C) Option 3|Correct: A"
 *
 * @package FLOSC
 * @version 3.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_MultipleChoice_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'multiplechoice';
    }

    public function get_name() {
        return 'Multiple Choice';
    }

    public function get_description() {
        return 'Classic quiz format with 2-4 options per question.';
    }

    public function get_icon() {
        return '☑️';
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
        return "Choose the correct answer for each question (e.g., A, B, C, or D).";
    }

    public function get_default_content() {
        return "What color is grass?|A) Red|B) Green|C) Blue|D) Yellow|Correct: B\nHow many legs does a dog have?|A) 2|B) 4|C) 6|D) 8|Correct: B\nWhat is 2+2?|A) 3|B) 4|C) 5|D) 6|Correct: B";
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

        // Parse user answers (e.g., "A,B,C" or "a,b,c")
        $user_answers = $this->parse_user_answers($input);

        $correct = [];
        $incorrect = [];
        $total_correct = 0;

        foreach ($questions as $index => $question) {
            $correct_answer = strtoupper(trim($question['correct']));
            $user_answer = isset($user_answers[$index]) ? strtoupper(trim($user_answers[$index])) : '';

            if ($user_answer === $correct_answer) {
                $correct[] = [
                    'question' => $question['text'],
                    'answer' => $correct_answer,
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
            'show_options' => [
                'type' => 'checkbox',
                'label' => 'Show Options in Results',
                'default' => true,
                'description' => 'Display what each letter (A, B, C, D) represented',
            ],
        ];
    }

    /**
     * Parse questions from content
     * Format: "Question?|A) Option 1|B) Option 2|C) Option 3|Correct: A"
     * Separated by newlines
     */
    private function parse_questions($content) {
        $questions = [];
        $blocks = explode("\n\n", $content); // Double newline separates questions

        // If no double newlines, try single
        if (count($blocks) === 1) {
            $blocks = explode("\n", $content);
        }

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            $parts = explode('|', $block);
            if (count($parts) < 3) continue; // Need at least question|option|correct

            $question_text = trim($parts[0]);
            $options = [];
            $correct = '';

            for ($i = 1; $i < count($parts); $i++) {
                $part = trim($parts[$i]);

                // Check if this is the "Correct:" part
                if (stripos($part, 'correct:') === 0) {
                    $correct = trim(str_ireplace('correct:', '', $part));
                } else {
                    // It's an option
                    $options[] = $part;
                }
            }

            if (!empty($question_text) && !empty($correct)) {
                $questions[] = [
                    'text' => $question_text,
                    'options' => $options,
                    'correct' => $correct,
                ];
            }
        }

        return $questions;
    }

    /**
     * Parse user answers
     * Accepts: "A,B,C" or "a,b,c" or "A\nB\nC"
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
}
