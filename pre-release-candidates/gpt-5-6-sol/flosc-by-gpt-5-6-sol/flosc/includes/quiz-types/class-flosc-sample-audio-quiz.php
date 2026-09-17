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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate FLOSC Sample Audio Quiz behavior and the WordPress services used by its methods.
 */
class FLOSC_Sample_Audio_Quiz extends FLOSC_Abstract_Quiz_Type {

		/**
	 * Resolve the current id value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the id operation.
	 */
public function get_id() {
		return 'flosc_sample_audio_quiz';
	}

		/**
	 * Resolve the current name value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the name operation.
	 */
public function get_name() {
		return 'FLOSC Sample Audio Quiz';
	}

		/**
	 * Resolve the current description value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the description operation.
	 */
public function get_description() {
		return 'Read the following series of numbers in order: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10';
	}

		/**
	 * Resolve the current icon value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the icon operation.
	 */
public function get_icon() {
		return '🎤';
	}

		/**
	 * Coordinate the needs audio behavior implemented by this code path.
	 *
	 * @return bool Whether needs audio applies to the current state.
	 */
public function needs_audio() {
		return true;
	}

		/**
	 * Coordinate the needs stt behavior implemented by this code path.
	 *
	 * @return bool Whether needs stt applies to the current state.
	 */
public function needs_stt() {
		return true;
	}

		/**
	 * Coordinate the needs ai analysis behavior implemented by this code path.
	 *
	 * @return bool Whether needs ai analysis applies to the current state.
	 */
public function needs_ai_analysis() {
		return false; // Uses phoneme analysis, not AI.
	}

		/**
	 * Resolve the current instructions value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the instructions operation.
	 */
public function get_instructions() {
		return "Read the following series of numbers in order:\n\n1, 2, 3, 4, 5, 6, 7, 8, 9, 10";
	}

		/**
	 * Resolve the current default content value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the default content operation.
	 */
public function get_default_content() {
		return '1,2,3,4,5,6,7,8,9,10';
	}

		/**
	 * Validate the input and trust conditions required for input.
	 *
	 * @param mixed $input Input consumed by the Validate the input and trust conditions required for input. operation.
	 * @return bool Whether input applies to the current state.
	 */
public function validate_input( $input ) {
		// Input is audio file path or STT transcript.
		if ( empty( $input ) ) {
			return new WP_Error( 'invalid_input', __( 'No audio input received.', 'flosc' ) );
		}

		return true;
	}

		/**
	 * Coordinate the analyze behavior implemented by this code path.
	 *
	 * @param mixed $input Input consumed by the Coordinate the analyze behavior implemented by this code path. operation.
	 * @param mixed $expected_content Input consumed by the Coordinate the analyze behavior implemented by this code path. operation.
	 * @param mixed $context Context values used to resolve request- or flow-specific behavior.
	 * @return array Structured analyze data.
	 */
public function analyze( $input, $expected_content, $context = array() ) {
		// If input is already a transcript (from STT), use it.
		// Otherwise, input would be audio file path (handled by main plugin).
		$transcript = is_string( $input ) ? $input : '';

		// Use existing pronunciation analyzer.
		require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-pronunciation-analyzer.php';
		$analyzer        = new FLOSC_Pronunciation_Analyzer();
		$analysis_result = $analyzer->analyze( $transcript, $expected_content );

		// Convert to standard format.
		$correct   = $analysis_result['correct_items'];
		$incorrect = $analysis_result['missed_items'];
		$score     = $analysis_result['score'];

		// Map to lessons.
		$lessons = array();
		if ( ! empty( $analysis_result['suggested_lessons'] ) ) {
			foreach ( $analysis_result['suggested_lessons'] as $item => $lesson_data ) {
				$lessons[] = array(
					'id'     => $lesson_data['lesson_id'],
					'title'  => $lesson_data['lesson'],
					'reason' => "You struggled with: {$item} (phoneme: {$lesson_data['phoneme']})",
				);
			}
		}

		$response_key = $this->get_response_key_from_score( $score );

		return array(
			'score'        => $score,
			'correct'      => $correct,
			'incorrect'    => $incorrect,
			'response_key' => $response_key,
			'details'      => array(
				'total_correct'     => count( $correct ),
				'total_possible'    => $analysis_result['total_items'],
				'transcript'        => $transcript,
				'expected'          => $expected_content,
				'suggested_lessons' => $analysis_result['suggested_lessons'],
			),
			'lessons'      => $lessons,
		);
	}

		/**
	 * Coordinate the map to lessons behavior implemented by this code path.
	 *
	 * @param mixed $analysis Input consumed by the Coordinate the map to lessons behavior implemented by this code path. operation.
	 * @return mixed Result produced by the map to lessons operation.
	 */
public function map_to_lessons( $analysis ) {
		// Already done in analyze().
		return $analysis['lessons'] ?? array();
	}

		/**
	 * Resolve the current settings fields value from the available WordPress and flow state.
	 *
	 * @return array Structured settings fields data.
	 */
public function get_settings_fields() {
		return array(
			'language'      => array(
				'type'        => 'select',
				'label'       => 'Target Language',
				'options'     => array(
					'en' => 'English',
					'es' => 'Spanish',
					'fr' => 'French',
					'de' => 'German',
					'it' => 'Italian',
					'pt' => 'Portuguese',
				),
				'default'     => 'en',
				'description' => 'Language to analyze',
			),
			'accent_target' => array(
				'type'        => 'select',
				'label'       => 'Target Accent',
				'options'     => array(
					'us' => 'US/Standard American English',
					'uk' => 'UK/Received Pronunciation',
					'au' => 'Australian',
					'ca' => 'Canadian',
				),
				'default'     => 'us',
				'description' => 'Which accent to compare against (for English)',
			),
		);
	}

		/**
	 * Resolve the current default response templates value from the available WordPress and flow state.
	 *
	 * @return array Structured default response templates data.
	 */
public function get_default_response_templates() {
		return array(
			'0-30'   => "**Pronunciation Score: {score}%**\n\nYou need significant practice with these sounds.\n\n{lesson_recommendations}",
			'31-60'  => "**Pronunciation Score: {score}%**\n\nGood effort! Focus on improving these sounds:\n\n{lesson_recommendations}",
			'61-85'  => "**Pronunciation Score: {score}%**\n\nNice work! Refine these final sounds:\n\n{lesson_recommendations}",
			'86-100' => "**Pronunciation Score: {score}%**\n\nExcellent pronunciation! You're speaking clearly.\n\n{lesson_recommendations}",
		);
	}

		/**
	 * Coordinate the format results behavior implemented by this code path.
	 *
	 * @param mixed $analysis Input consumed by the Coordinate the format results behavior implemented by this code path. operation.
	 * @param mixed $lessons Input consumed by the Coordinate the format results behavior implemented by this code path. operation.
	 * @param mixed $response_templates Input consumed by the Coordinate the format results behavior implemented by this code path. operation.
	 * @return mixed Result produced by the format results operation.
	 */
public function format_results( $analysis, $lessons, $response_templates ) {
		$score        = $analysis['score'];
		$response_key = $analysis['response_key'];
		$details      = $analysis['details'];

		// Get template.
		$template = $response_templates[ $response_key ] ?? $response_templates['31-60'] ?? 'Score: {score}%';

		// Build lesson text.
		$lesson_text = '';

		// Show what they said.
		if ( ! empty( $details['transcript'] ) ) {
			$lesson_text .= "**You said:** {$details['transcript']}\n";
			$lesson_text .= "**Expected:** {$details['expected']}\n\n";
		}

		// Show correct items.
		if ( ! empty( $analysis['correct'] ) ) {
			$lesson_text .= '✅ **Correct:** ' . implode( ', ', $analysis['correct'] ) . "\n\n";
		}

		// Show missed items.
		if ( ! empty( $analysis['incorrect'] ) ) {
			$lesson_text .= '❌ **Needs Practice:** ' . implode( ', ', $analysis['incorrect'] ) . "\n\n";
		}

		// Lesson recommendations.
		if ( ! empty( $lessons ) ) {
			$free_lesson  = $lessons[0];
			$paid_lessons = array_slice( $lessons, 1 );

			$lesson_text .= "**📚 Recommended Lessons:**\n\n";
			$lesson_text .= "🎁 **{$free_lesson['title']}** (FREE)\n";
			$lesson_text .= "   {$free_lesson['reason']}\n";

			if ( ! empty( $paid_lessons ) ) {
				$lesson_text .= "\n🔒 **Unlock " . count( $paid_lessons ) . " more lessons:**\n";
				foreach ( $paid_lessons as $lesson ) {
					$lesson_text .= "   • {$lesson['title']}\n";
				}
			}
		}

		// Replace placeholders.
		$message = str_replace(
			array( '{score}', '{lesson_recommendations}' ),
			array( $score, $lesson_text ),
			$template
		);

		return $message;
	}
}
