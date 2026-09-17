<?php
/**
 * FLOSC Pronunciation Analyzer
 * Compares transcript to expected text and identifies errors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLOSC_Pronunciation_Analyzer {

	// Number words to digits mapping.
	private $number_words = array(
		'one'   => '1',
		'won'   => '1',
		'two'   => '2',
		'to'    => '2',
		'too'   => '2',
		'three' => '3',
		'tree'  => '3',
		'four'  => '4',
		'for'   => '4',
		'fore'  => '4',
		'five'  => '5',
		'six'   => '6',
		'sex'   => '6',
		'sax'   => '6',
		'seven' => '7',
		'eight' => '8',
		'ate'   => '8',
		'nine'  => '9',
		'nein'  => '9',
		'ten'   => '10',
	);

	// Default phoneme lessons mapping (fallback if no WP posts).
	private $default_lesson_mapping = array(
		'5'  => array(
			'phoneme'   => '/aɪ/',
			'lesson'    => 'The "long I" sound',
			'lesson_id' => 'lesson_ai',
		),
		'6'  => array(
			'phoneme'   => '/ɪ/',
			'lesson'    => 'The "short I" sound',
			'lesson_id' => 'lesson_i',
		),
		'7'  => array(
			'phoneme'   => '/ɛ/',
			'lesson'    => 'The "short E" sound',
			'lesson_id' => 'lesson_e',
		),
		'8'  => array(
			'phoneme'   => '/eɪ/',
			'lesson'    => 'The "long A" sound',
			'lesson_id' => 'lesson_ei',
		),
		'9'  => array(
			'phoneme'   => '/aɪ/',
			'lesson'    => 'The "long I" sound',
			'lesson_id' => 'lesson_ai',
		),
		'10' => array(
			'phoneme'   => '/ɛ/',
			'lesson'    => 'The "short E" sound',
			'lesson_id' => 'lesson_e',
		),
	);

	/**
	 * Get lesson mapping (from WP posts or fallback to defaults)
	 */
	private function get_lesson_mapping() {
		// Try to get from lesson manager.
		if ( function_exists( 'flosc' ) && flosc()->lessons() ) {
			$wp_mapping = flosc()->lessons()->get_lesson_mapping();
			if ( ! empty( $wp_mapping ) ) {
				return $wp_mapping;
			}
		}

		return $this->default_lesson_mapping;
	}

	/**
	 * Analyze transcript against expected text
	 */
	public function analyze( $transcript, $expected ) {
		$transcript = $this->normalize( $transcript );
		$expected   = $this->normalize( $expected );

		$transcript_items = explode( ' ', $transcript );
		$expected_items   = explode( ' ', $expected );

		$lesson_mapping = $this->get_lesson_mapping();

		$results = array(
			'score'                 => 0,
			'total_items'           => count( $expected_items ),
			'correct_items'         => array(),
			'missed_items'          => array(),
			'transcript_normalized' => $transcript,
			'expected_normalized'   => $expected,
			'suggested_lessons'     => array(),
			'free_lesson'           => null,
		);

		// Compare each expected item.
		foreach ( $expected_items as $index => $expected_item ) {
			$found = false;

			foreach ( $transcript_items as $transcript_item ) {
				if ( $this->items_match( $expected_item, $transcript_item ) ) {
					$found                      = true;
					$results['correct_items'][] = $expected_item;
					break;
				}
			}

			if ( ! $found ) {
				$results['missed_items'][] = $expected_item;

				// Map to lesson if available.
				if ( isset( $lesson_mapping[ $expected_item ] ) ) {
					$lesson = $lesson_mapping[ $expected_item ];
					$results['suggested_lessons'][ $expected_item ] = $lesson;
				}
			}
		}

		// Calculate score.
		$correct_count    = count( $results['correct_items'] );
		$results['score'] = round( ( $correct_count / $results['total_items'] ) * 100 );

		// Select free lesson (first missed item with a lesson).
		if ( ! empty( $results['missed_items'] ) ) {
			foreach ( $results['missed_items'] as $missed ) {
				if ( isset( $lesson_mapping[ $missed ] ) ) {
					$results['free_lesson'] = array_merge(
						$lesson_mapping[ $missed ],
						array( 'item' => $missed )
					);
					break;
				}
			}
		}

		// Generate feedback message.
		$results['feedback'] = $this->generate_feedback( $results );

		return $results;
	}

	/**
	 * Normalize text for comparison
	 */
	private function normalize( $text ) {
		// Lowercase.
		$text = strtolower( trim( $text ) );

		// Remove punctuation.
		$text = preg_replace( '/[^\w\s]/', '', $text );

		// Convert number words to digits.
		$words      = explode( ' ', $text );
		$normalized = array();

		foreach ( $words as $word ) {
			if ( isset( $this->number_words[ $word ] ) ) {
				$normalized[] = $this->number_words[ $word ];
			} elseif ( is_numeric( $word ) ) {
				$normalized[] = $word;
			} elseif ( ! empty( $word ) ) {
				// Keep non-number words for sentence mode.
				$normalized[] = $word;
			}
		}

		return implode( ' ', $normalized );
	}

	/**
	 * Check if two items match (with fuzzy matching)
	 */
	private function items_match( $expected, $actual ) {
		// Exact match.
		if ( $expected === $actual ) {
			return true;
		}

		// Number variations.
		if ( is_numeric( $expected ) && is_numeric( $actual ) ) {
			return $expected === $actual;
		}

		// Fuzzy match for words (Levenshtein distance <= 1).
		if ( ! is_numeric( $expected ) && ! is_numeric( $actual ) ) {
			return levenshtein( $expected, $actual ) <= 1;
		}

		return false;
	}

	/**
	 * Generate human-readable feedback
	 */
	private function generate_feedback( $results ) {
		$score        = $results['score'];
		$missed       = $results['missed_items'];
		$product_name = get_option( 'flosc_product_name', 'our course' );

		if ( 100 === $score ) {
			return '🎉 Perfect score! You pronounced everything correctly. Want to explore advanced lessons?';
		}

		if ( $score >= 80 ) {
			$missed_str = implode( ', ', $missed );
			return "Great job! You scored {$score}%. You had some trouble with: {$missed_str}. Let me show you a free lesson to help with that!";
		}

		if ( $score >= 60 ) {
			return "Good effort! You scored {$score}%. There's room for improvement, and I have just the lesson for you. Let's work on it together!";
		}

		if ( $score >= 40 ) {
			return "You scored {$score}%. Don't worry - this is exactly what {$product_name} is designed to help with! Let me show you where to start.";
		}

		return "You scored {$score}%. Everyone starts somewhere, and you've just taken the first step! Let me show you a lesson that will make a big difference.";
	}

	/**
	 * Get available lessons (for paid users)
	 */
	public function get_all_lessons() {
		return array(
			'lesson_ai' => array(
				'title'       => 'The "Long I" Sound /aɪ/',
				'numbers'     => array( '5', '9' ),
				'description' => 'Master the diphthong in "five" and "nine"',
			),
			'lesson_i'  => array(
				'title'       => 'The "Short I" Sound /ɪ/',
				'numbers'     => array( '6' ),
				'description' => 'Perfect the vowel in "six"',
			),
			'lesson_e'  => array(
				'title'       => 'The "Short E" Sound /ɛ/',
				'numbers'     => array( '7', '10' ),
				'description' => 'Master the vowel in "seven" and "ten"',
			),
			'lesson_ei' => array(
				'title'       => 'The "Long A" Sound /eɪ/',
				'numbers'     => array( '8' ),
				'description' => 'Perfect the diphthong in "eight"',
			),
		);
	}
}
