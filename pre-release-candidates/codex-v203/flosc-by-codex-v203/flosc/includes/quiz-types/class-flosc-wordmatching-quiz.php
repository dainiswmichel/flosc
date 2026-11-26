<?php
/**
 * Word Matching Quiz Type
 *
 * User matches words to categories or definitions.
 * Example: Match animals → cat:mammal, fish:animal, bird:animal
 *
 * @package FLOSC
 * @version 3.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Word matching quiz.
 */
class FLOSC_WordMatching_Quiz extends FLOSC_Abstract_Quiz_Type {

	/**
	 * Get ID.
	 *
	 * @return mixed
	 */
	public function get_id() {
		return 'wordmatching';
	}

	/**
	 * Get name.
	 *
	 * @return mixed
	 */
	public function get_name() {
		return 'Word Matching';
	}

	/**
	 * Get description.
	 *
	 * @return mixed
	 */
	public function get_description() {
		return 'Match words to categories or definitions. Great for vocabulary and classification.';
	}

	/**
	 * Get icon.
	 *
	 * @return mixed
	 */
	public function get_icon() {
		return '🔗';
	}

	/**
	 * Needs audio.
	 *
	 * @return mixed
	 */
	public function needs_audio() {
		return false;
	}

	/**
	 * Needs STT.
	 *
	 * @return mixed
	 */
	public function needs_stt() {
		return false;
	}

	/**
	 * Needs AI analysis.
	 *
	 * @return mixed
	 */
	public function needs_ai_analysis() {
		return false;
	}

	/**
	 * Get instructions.
	 *
	 * @return mixed
	 */
	public function get_instructions() {
		return 'Match each word to its category (format: word:category).';
	}

	/**
	 * Get default content.
	 *
	 * @return mixed
	 */
	public function get_default_content() {
		return "cat:mammal\ndog:mammal\nfish:aquatic\nbird:avian\nsnake:reptile";
	}

	/**
	 * Validate input.
	 *
	 * @param mixed $input Input.
	 * @return mixed
	 */
	public function validate_input( $input ) {
		if ( empty( $input ) || ! is_string( $input ) ) {
			return new WP_Error( 'invalid_input', __( 'Please enter your matches.', 'flosc' ) );
		}

		return true;
	}

	/**
	 * Analyze.
	 *
	 * @param mixed $input            Input.
	 * @param mixed $expected_content Expected content.
	 * @param mixed $context          Context.
	 * @return mixed
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
	 * Get settings fields.
	 *
	 * @return mixed
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
	 * Parse matches from content
	 * Format: "word:category\nanotherword:category"
	 * Returns: ['word' => 'category', ...]
	 *
	 * @param mixed $content        Content.
	 * @param bool  $case_sensitive Case sensitive.
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
	 * Format results.
	 *
	 * @param mixed $analysis           Analysis.
	 * @param mixed $lessons            Lessons.
	 * @param mixed $response_templates Response templates.
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
