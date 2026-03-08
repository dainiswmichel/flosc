<?php
/**
 * LeSAEp Pronunciation Quiz
 *
 * Audio quiz where user reads a reference paragraph containing all 52 SAE phonemes.
 * STT transcribes what was said. Word-level diff + IPA analysis identifies
 * which phonemes were mispronounced. Maps missed phonemes to LeSAEp lessons (L1-L52).
 *
 * @package FLOSC
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class LeSAEp_Pronunciation_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'lesaep_pronunciation_quiz';
    }

    public function get_name() {
        return 'LeSAEp Pronunciation Assessment';
    }

    public function get_description() {
        return 'Read a reference paragraph aloud. The system transcribes your speech, compares word-by-word against the expected text, and identifies which SAE phonemes need work. Maps results to LeSAEp lessons L1-L52.';
    }

    public function get_icon() {
        return '🗣️';
    }

    public function needs_audio() {
        return true;
    }

    public function needs_stt() {
        return true;
    }

    public function needs_ai_analysis() {
        return false;
    }

    public function get_instructions() {
        $text = $this->get_reference_text();
        return "Read the following text aloud, clearly and at a natural pace:\n\n\"{$text}\"";
    }

    public function get_default_content() {
        // The reference paragraph — admin-configurable, but this is the default
        // containing all 52 SAE phonemes. Will be refined by the floscAdmin.
        return 'Cat bed skip clock truck cook paid we pie flow bloom bark bird birth cork north house clown joy voice veer steer hair wear compare sure cure She has a poodle power apparent boom trouble tap attack adorable dog cap accredited good jogging moon yummy noodle announce greeting driving fan laugh van beaver three froth the feather scathe see muscle zoo sunrise shape lotion brush beige pleasure casual hop rehearse children ketchup jeans badger marriage choice joke wet liquid want nowhere rock orange yes player love balloon box rocks quiet counterclockwise water ladder button tune caught stock stalk pin pen vary very mill meal sell sail salary celery horse hoarse war wore cat cot caught cut comfortable temperature beautiful law and order go away the end';
    }

    /**
     * Get reference text from admin settings or default
     */
    private function get_reference_text() {
        return get_option(
            'flosc_quiz_content_' . $this->get_id(),
            $this->get_default_content()
        );
    }

    public function validate_input($input) {
        if (empty($input)) {
            return new WP_Error('invalid_input', 'No audio input received.');
        }
        return true;
    }

    /**
     * Analyze transcript against reference text
     *
     * @param string $input STT transcript (plain text) or structured data with word confidences
     * @param string $expected_content Reference text from admin
     * @param array $context ['user_id' => int, 'word_confidences' => [...]]
     * @return array Standard quiz analysis result
     */
    public function analyze($input, $expected_content, $context = []) {
        require_once FLOSC_PLUGIN_DIR . 'includes/class-lesaep-phoneme-analyzer.php';
        $analyzer = new LeSAEp_Phoneme_Analyzer();

        // If input is structured (with word confidences from STT), extract
        $transcript = is_string($input) ? $input : '';
        $word_confidences = $context['word_confidences'] ?? [];

        $analysis = $analyzer->analyze($transcript, $expected_content, $word_confidences);

        // Convert to standard quiz format
        $score = $analysis['score'];
        $response_key = $this->get_response_key_from_score($score);

        // Build lesson list from missed phonemes
        $lessons = [];
        foreach ($analysis['missed_lessons'] as $lesson_data) {
            $lessons[] = [
                'id'     => $lesson_data['lesson_id'],
                'title'  => $lesson_data['title'],
                'reason' => $lesson_data['reason'],
            ];
        }

        return [
            'score'        => $score,
            'correct'      => $analysis['correct_words'],
            'incorrect'    => $analysis['incorrect_words'],
            'response_key' => $response_key,
            'details'      => [
                'total_words'       => $analysis['total_words'],
                'matched_words'     => $analysis['matched_count'],
                'mismatched_words'  => $analysis['mismatched_count'],
                'skipped_words'     => $analysis['skipped_count'],
                'transcript'        => $transcript,
                'expected'          => $expected_content,
                'phonemes_hit'      => $analysis['phonemes_hit'],
                'phonemes_missed'   => $analysis['phonemes_missed'],
                'word_details'      => $analysis['word_details'],
            ],
            'lessons' => $lessons,
        ];
    }

    public function map_to_lessons($analysis) {
        return $analysis['lessons'] ?? [];
    }

    public function get_settings_fields() {
        return [
            'reference_text' => [
                'type'        => 'textarea',
                'label'       => 'Reference Paragraph',
                'description' => 'The text the user will read aloud. Should contain all 52 SAE phonemes. Use the Magic Sentence Palette to verify phoneme coverage.',
                'default'     => $this->get_default_content(),
                'rows'        => 6,
            ],
            'confidence_threshold' => [
                'type'        => 'number',
                'label'       => 'Word Confidence Threshold',
                'description' => 'Words with STT confidence below this value are flagged as mispronounced (0.0 - 1.0). Lower = more forgiving.',
                'default'     => 0.6,
                'min'         => 0.1,
                'max'         => 0.95,
                'step'        => 0.05,
            ],
            'accent_target' => [
                'type'    => 'select',
                'label'   => 'Target Accent',
                'options' => [
                    'us' => 'Standard American English (SAE)',
                ],
                'default'     => 'us',
                'description' => 'Accent standard to compare against.',
            ],
        ];
    }

    public function get_default_response_templates() {
        return [
            '0-30'  => "**Pronunciation Score: {score}%**\n\nYou have several sounds to work on — and that's exactly what LeSAEp is for.\n\n{lesson_recommendations}",
            '31-60' => "**Pronunciation Score: {score}%**\n\nGood foundation. Let's sharpen the sounds you missed.\n\n{lesson_recommendations}",
            '61-85' => "**Pronunciation Score: {score}%**\n\nStrong pronunciation. A few sounds to refine:\n\n{lesson_recommendations}",
            '86-100' => "**Pronunciation Score: {score}%**\n\nExcellent SAE pronunciation.\n\n{lesson_recommendations}",
        ];
    }
}
