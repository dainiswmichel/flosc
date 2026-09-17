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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate FLOSC True False Quiz behavior and the WordPress services used by its methods.
 */
class FLOSC_TrueFalse_Quiz extends FLOSC_Abstract_Quiz_Type {

		/**
	 * Resolve the current id value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the id operation.
	 */
public function get_id() {
		return 'truefalse';
	}

		/**
	 * Resolve the current name value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the name operation.
	 */
public function get_name() {
		return 'True/False';
	}

		/**
	 * Resolve the current description value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the description operation.
	 */
public function get_description() {
		return 'User answers True or False to statements. Perfect for knowledge checks.';
	}

		/**
	 * Resolve the current icon value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the icon operation.
	 */
public function get_icon() {
		return '✓✗';
	}

		/**
	 * Coordinate the needs audio behavior implemented by this code path.
	 *
	 * @return bool Whether needs audio applies to the current state.
	 */
public function needs_audio() {
		return false;
	}

		/**
	 * Coordinate the needs stt behavior implemented by this code path.
	 *
	 * @return bool Whether needs stt applies to the current state.
	 */
public function needs_stt() {
		return false;
	}

		/**
	 * Coordinate the needs ai analysis behavior implemented by this code path.
	 *
	 * @return bool Whether needs ai analysis applies to the current state.
	 */
public function needs_ai_analysis() {
		return false;
	}

		/**
	 * Resolve the current instructions value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the instructions operation.
	 */
public function get_instructions() {
		return "One statement per line. Format: Statement.|True or Statement.|False\n\nOptional pipe segments (add as many as you like — they all accumulate):\n  |CorrectContent: post:my-post-slug\n  |CorrectContent: tag:my-tag, id:1042\n  |RelatedContent: post:slug-one, category:parent/child\n  |RelatedContent: tag:another-tag, id:1043\n  |RelatedContent: search:distinctive words from title\n\nPrefixes — always required, no quotes:\n  post:slug              — post by URL slug; use post:parent/child if the same slug exists under multiple parents\n  id:1042           — one post by numeric ID\n  category:slug     — posts in a category; category:parent/child for sub-categories\n  tag:slug          — posts with a tag (use the tag slug, not the display name)\n  search:any words  — keyword search (avoid: unreliable, may match wrong posts)\n\nMultiple |CorrectContent: and |RelatedContent: segments accumulate. CorrectContent items are tier 1 — shown first when a learner asks to review what they got wrong.";
	}

		/**
	 * Resolve the current default content value from the available WordPress and flow state.
	 *
	 * @return mixed Result produced by the default content operation.
	 */
public function get_default_content() {
		// Subject-neutral sample — replace with your own statements in FLOSC → Quiz.
		return "Sample statement for Topic 1 — Getting started: this product ships ready for any subject.|True|CorrectContent: post:sample-topic-1-getting-started|RelatedContent: post:sample-topic-1-getting-started-extra|Topic: topic-1-getting-started\nSample statement for Topic 2 — Core ideas: admins configure freeline, guest gifts, and member gates in the flow.|True|CorrectContent: post:sample-topic-2-core-ideas|RelatedContent: category:sample_lessons|Topic: topic-2-core-ideas\nSample statement for Topic 3 — Practice basics: wrong answers must always unlock the full member library.|False|CorrectContent: post:sample-topic-3-practice-basics|RelatedContent: post:sample-topic-3-practice-basics-extra|Topic: topic-3-practice-basics";
	}

		/**
	 * Validate the input and trust conditions required for input.
	 *
	 * @param mixed $input Input consumed by the Validate the input and trust conditions required for input. operation.
	 * @return bool Whether input applies to the current state.
	 */
public function validate_input( $input ) {
		if ( empty( $input ) || ! is_string( $input ) ) {
			return new WP_Error( 'invalid_input', __( 'Please enter your answers.', 'flosc' ) );
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
		// Parse questions.
		$questions = $this->parse_questions( $expected_content );

		// Parse answers (can be "T,F,T" or "True,False,True").
		$user_answers = $this->parse_user_answers( $input );

		$correct       = array();
		$incorrect     = array();
		$total_correct = 0;

		foreach ( $questions as $index => $question ) {
			$correct_answer = strtolower( $question['answer'] );
			$user_answer    = isset( $user_answers[ $index ] ) ? strtolower( $user_answers[ $index ] ) : '';

			// Normalize.
			$correct_answer = $this->normalize_answer( $correct_answer );
			$user_answer    = $this->normalize_answer( $user_answer );

			if ( $user_answer === $correct_answer ) {
				$correct[] = array(
					'question' => $question['text'],
					'answer'   => $user_answer,
					'topics'   => $question['topics'] ?? array(),
				);
				++$total_correct;
			} else {
				$incorrect[] = array(
					'question'        => $question['text'],
					'user_answer'     => $user_answer,
					'correct_answer'  => $correct_answer,
					'topics'          => $question['topics'] ?? array(),
					'correct_content' => $question['correct_content'] ?? '',
					'related_content' => $question['related_content'] ?? array(),
				);
			}
		}

		$total_possible = count( $questions );
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
	 * @return array Structured settings fields data.
	 */
public function get_settings_fields() {
		return array(
			'answer_format' => array(
				'type'        => 'select',
				'label'       => 'Answer Format',
				'options'     => array(
					'tf'        => 'T/F',
					'truefalse' => 'True/False',
					'yesno'     => 'Yes/No',
				),
				'default'     => 'truefalse',
				'description' => 'How users should format their answers',
			),
		);
	}

	/**
	 * Parse questions from content.
	 * Format: "Statement.|True|Topic: slug1, slug2"
	 * Topic segment is optional.
 * @param mixed $content Input consumed by the Coordinate the parse questions behavior implemented by this code path. operation.
 * @return mixed Result produced by the parse questions operation.
	 */
	private function parse_questions( $content ) {
		$lines     = explode( "\n", $content );
		$questions = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$parts = explode( '|', $line );
			if ( count( $parts ) < 2 ) {
				continue;
			}

			$topics          = array();
			$correct_content = array();
			$related_content = array();
			$part_count      = count( $parts );
			for ( $i = 2; $i < $part_count; $i++ ) {
				$seg = trim( $parts[ $i ] );
				if ( 0 === stripos( $seg, 'correctcontent:' ) ) {
					// Appends — multiple |CorrectContent: segments are all tier-1.
					foreach ( array_map( 'trim', explode( ',', trim( substr( $seg, strlen( 'correctcontent:' ) ) ) ) ) as $r ) {
						if ( '' !== $r ) {
							$correct_content[] = $r;
						}
					}
				} elseif ( 0 === stripos( $seg, 'relatedcontent:' ) ) {
					// Appends — multiple |RelatedContent: pipe segments are cumulative.
					foreach ( array_map( 'trim', explode( ',', trim( substr( $seg, strlen( 'relatedcontent:' ) ) ) ) ) as $r ) {
						if ( '' !== $r ) {
							$related_content[] = $r;
						}
					}
				} elseif ( 0 === stripos( $seg, 'topic:' ) ) {
					$topics = array_map( 'trim', explode( ',', trim( str_ireplace( 'topic:', '', $seg ) ) ) );
				}
			}

			$questions[] = array(
				'text'            => trim( $parts[0] ),
				'answer'          => trim( $parts[1] ),
				'topics'          => $topics,
				'correct_content' => $correct_content,
				'related_content' => $related_content,
			);
		}

		return $questions;
	}

	/**
	 * Parse user answers
	 * Accepts: "T,F,T" or "True,False,True" or "true\nfalse\ntrue"
 * @param mixed $input Input consumed by the Coordinate the parse user answers behavior implemented by this code path. operation.
 * @return mixed Result produced by the parse user answers operation.
	 */
	private function parse_user_answers( $input ) {
		// Try comma-separated first.
		if ( false !== strpos( $input, ',' ) ) {
			$answers = explode( ',', $input );
		} else {
			// Try newline-separated.
			$answers = explode( "\n", $input );
		}

		return array_map( 'trim', $answers );
	}

	/**
	 * Normalize answer (T/True/Yes → true, F/False/No → false)
 * @param mixed $answer Input consumed by the Normalize the input into the canonical form required for normalize answer. operation.
 * @return mixed Result produced by the normalize answer operation.
	 */
	private function normalize_answer( $answer ) {
		$answer = strtolower( trim( $answer ) );

		if ( in_array( $answer, array( 't', 'true', 'yes', '1' ), true ) ) {
			return 'true';
		}

		if ( in_array( $answer, array( 'f', 'false', 'no', '0' ), true ) ) {
			return 'false';
		}

		return $answer;
	}
}
