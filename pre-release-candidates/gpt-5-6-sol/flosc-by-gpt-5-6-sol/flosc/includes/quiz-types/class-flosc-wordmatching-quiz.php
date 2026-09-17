<?php
/**
 * Word Matching Quiz Type.
 *
 * User matches words to categories or definitions.
 * Example: Match animals → cat:mammal, fish:animal, bird:animal.
 *
 * @package FLOSC
 * @version 3.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate FLOSC Word Matching Quiz behavior and the WordPress services used by its methods.
 */
class FLOSC_WordMatching_Quiz extends FLOSC_Abstract_Quiz_Type {

/**
 * Resolve the current id value from the available WordPress and flow state.
 *
 * @return Mixed Result produced by the id operation.
 */
public function get_id() {
		return 'wordmatching';
	}

/**
 * Resolve the current name value from the available WordPress and flow state.
 *
 * @return Mixed Result produced by the name operation.
 */
public function get_name() {
		return 'Word Matching';
	}

/**
 * Resolve the current description value from the available WordPress and flow state.
 *
 * @return Mixed Result produced by the description operation.
 */
public function get_description() {
		return 'Match words to categories or definitions. Great for vocabulary and classification.';
	}

/**
 * Resolve the current icon value from the available WordPress and flow state.
 *
 * @return Mixed Result produced by the icon operation.
 */
public function get_icon() {
		return '🔗';
	}

/**
 * Coordinate the needs audio behavior implemented by this code path.
 *
 * @return Bool Whether needs audio applies to the current state.
 */
public function needs_audio() {
		return false;
	}

/**
 * Coordinate the needs stt behavior implemented by this code path.
 *
 * @return Bool Whether needs stt applies to the current state.
 */
public function needs_stt() {
		return false;
	}

/**
 * Coordinate the needs ai analysis behavior implemented by this code path.
 *
 * @return Bool Whether needs ai analysis applies to the current state.
 */
public function needs_ai_analysis() {
		return false;
	}

/**
 * Resolve the current instructions value from the available WordPress and flow state.
 *
 * @return Mixed Result produced by the instructions operation.
 */
public function get_instructions() {
		return 'Match each word to its category (format: word:category).';
	}

/**
 * Resolve the current default content value from the available WordPress and flow state.
 *
 * @return Mixed Result produced by the default content operation.
 */
public function get_default_content() {
		return "cat:mammal\ndog:mammal\nfish:aquatic\nbird:avian\nsnake:reptile";
	}

/**
 * Validate the input and trust conditions required for input.
 *
 * @param mixed $input Input consumed by the Validate the input and trust conditions required for input. operation.
 * @return Bool Whether input applies to the current state.
 */
public function validate_input( $input ) {
		if ( empty( $input ) || ! is_string( $input ) ) {
			return new WP_Error( 'invalid_input', __( 'Please enter your matches.', 'flosc' ) );
		}

		return true;
	}

/**
 * Coordinate the analyze behavior implemented by this code path.
 *
 * @param mixed $input            Input consumed by the Coordinate the analyze behavior implemented by this code path. operation.
 * @param mixed $expected_content Input consumed by the Coordinate the analyze behavior implemented by this code path. operation.
 * @param mixed $context          Context values used to resolve request- or flow-specific behavior.
 * @return Array Structured analyze data.
 */
public function analyze( $input, $expected_content, $context = array() ) {
		$case_sensitive = $this->get_setting( 'case_sensitive', false );

		// Parse correct matches.
		$correct_matches = $this->parse_matches( $expected_content, $case_sensitive );

		// Parse user matches.
		$user_matches = $this->parse_matches( $input, $case_sensitive );

		$correct       = array();
		$incorrect     = array();
		$total_correct = 0;

		// Check each word.
		$all_words = array_keys( $correct_matches );

		foreach ( $all_words as $word ) {
			$word_key         = $case_sensitive ? $word : strtolower( $word );
			$correct_category = $correct_matches[ $word_key ] ?? '';
			$user_category    = $user_matches[ $word_key ] ?? '';

			if ( $user_category === $correct_category && ! empty( $user_category ) ) {
				$correct[] = array(
					'word'     => $word,
					'category' => $user_category,
				);
				++$total_correct;
			} else {
				$incorrect[] = array(
					'word'             => $word,
					'user_category'    => $user_category,
					'correct_category' => $correct_category,
				);
			}
		}

		$total_possible = count( $correct_matches );
		$score          = $this->calculate_percentage( $total_correct, $total_possible );
		$response_key   = $this->get_response_key_from_score( $score );

		return array(
			'score'        => $score,
			'correct'      => $correct,
			'incorrect'    => $incorrect,
			'response_key' => $response_key,
			'details'      => array(
				'total_correct'  => $total_correct,
				'total_possible' => $total_possible,
			),
		);
	}

/**
 * Resolve the current settings fields value from the available WordPress and flow state.
 *
 * @return Array Structured settings fields data.
 */
public function get_settings_fields() {
		return array(
			'case_sensitive'  => array(
				'type'        => 'checkbox',
				'label'       => 'Case Sensitive',
				'default'     => false,
				'description' => 'Require exact letter case matches',
			),
			'show_categories' => array(
				'type'        => 'checkbox',
				'label'       => 'Show Valid Categories',
				'default'     => true,
				'description' => 'Display list of valid categories to users',
			),
		);
	}

	/**
	 * Parse matches from content.
	 * Format: "word:category\nanotherword:category".
	 * Returns: ['word' => 'category', ...]
	 *
	 * @param mixed $content        Input consumed by the Coordinate the parse matches behavior implemented by this code path. operation.
	 * @param mixed $case_sensitive Input consumed by the Coordinate the parse matches behavior implemented by this code path. operation.
	 * @return Mixed Result produced by the parse matches operation.
	 */
	private function parse_matches( $content, $case_sensitive = false ) {
		$matches = array();
		$lines   = explode( "\n", $content );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$parts = explode( ':', $line );
			if ( 2 === count( $parts ) ) {
				$word     = trim( $parts[0] );
				$category = trim( $parts[1] );

				if ( ! $case_sensitive ) {
					$word     = strtolower( $word );
					$category = strtolower( $category );
				}

				$matches[ $word ] = $category;
			}
		}

		return $matches;
	}

/**
 * Coordinate the format results behavior implemented by this code path.
 *
 * @param mixed $analysis           Input consumed by the Coordinate the format results behavior implemented by this code path. operation.
 * @param mixed $lessons            Input consumed by the Coordinate the format results behavior implemented by this code path. operation.
 * @param mixed $response_templates Input consumed by the Coordinate the format results behavior implemented by this code path. operation.
 * @return Mixed Result produced by the format results operation.
 */
public function format_results( $analysis, $lessons, $response_templates ) {
		$score        = $analysis['score'];
		$response_key = $analysis['response_key'];
		$details      = $analysis['details'];

		// Get template.
		$template = $response_templates[ $response_key ] ?? $response_templates['31-60'] ?? 'Score: {score}%';

		// Build lesson text.
		$lesson_text = '';
		if ( ! empty( $lessons ) ) {
			$free_lesson  = $lessons[0];
			$paid_lessons = array_slice( $lessons, 1 );

			$lesson_text  = "\n**📚 Recommended Lessons:**\n\n";
			$lesson_text .= "🎁 **{$free_lesson['title']}** (FREE)\n";
			$lesson_text .= "   {$free_lesson['reason']}\n";

			if ( ! empty( $paid_lessons ) ) {
				$lesson_text .= "\n🔒 **Unlock " . count( $paid_lessons ) . " more lessons:**\n";
				foreach ( $paid_lessons as $lesson ) {
					$lesson_text .= "   • {$lesson['title']}\n";
				}
			}
		}

		// Show correct matches.
		if ( ! empty( $analysis['correct'] ) ) {
			$lesson_text .= "\n\n✅ **Correct Matches:**\n";
			foreach ( $analysis['correct'] as $match ) {
				$lesson_text .= "   • {$match['word']} → {$match['category']}\n";
			}
		}

		// Show incorrect matches.
		if ( ! empty( $analysis['incorrect'] ) ) {
			$lesson_text .= "\n❌ **Review These:**\n";
			foreach ( $analysis['incorrect'] as $match ) {
				$lesson_text .= "   • {$match['word']}: You said \"{$match['user_category']}\", correct is \"{$match['correct_category']}\"\n";
			}
		}

		// Replace placeholders.
		$message = str_replace(
			array(
				'{score}',
				'{total_correct}',
				'{total_possible}',
				'{lesson_recommendations}',
			),
			array(
				$score,
				$details['total_correct'],
				$details['total_possible'],
				$lesson_text,
			),
			$template
		);

		return $message;
	}
}
