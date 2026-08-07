<?php
/**
 * Sample Assessment Quiz type
 *
 * Subject-neutral sample: 10 topics with one multiple-choice item each.
 * floscAdmins replace this content with their own questions, map wrong answers
 * to topics, and gate freeline / guest / member content via flow settings —
 * not via this sample data.
 *
 * Default CorrectContent / RelatedContent use placeholder post:sample-topic-N
 * slugs. Point them at real posts (or leave empty) in the Quiz admin for each flow.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLOSC_Sample_Assessment_Quiz extends FLOSC_Abstract_Quiz_Type {

	public function get_id() {
		return 'sample_assessment_quiz';
	}

	public function get_name() {
		return 'Sample Assessment Quiz';
	}

	public function get_description() {
		return 'Sample multi-topic assessment (10 generic questions). Replace with your own items; topics drive freeline, guest gifts, and member content via admin config.';
	}

	public function get_icon() {
		return '📋';
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
		return "One question per block, separated by a blank line.\n\n"
			. "Each block:\n"
			. "  Question text\n"
			. "  A: Option text\n"
			. "  B: Option text\n"
			. "  C: Option text  (D: optional)\n"
			. "  CORRECT: A\n"
			. "  CorrectContent: post:my-post-slug\n"
			. "  RelatedContent: post:slug-one, category:parent/child\n"
			. "  TOPIC: topic-slug\n\n"
			. "Paste your own questions here (or replace the sample bank in FLOSC → Quiz).\n"
			. "Topics and content gates (freeline / guest / member) are configured in the flow — this sample only illustrates format.";
	}

	/**
	 * Default content as admin-editable text (sample only — replace in Quiz admin).
	 */
	public function get_default_content() {
		$lines = array();
		foreach ( $this->get_default_questions() as $q ) {
			$lines[] = $q['text'];
			foreach ( $q['options'] as $opt ) {
				$lines[] = $opt['key'] . ': ' . $opt['text'];
			}
			$lines[] = 'CORRECT: ' . $q['correct'];
			foreach ( (array) ( $q['correct_content'] ?? array() ) as $ref ) {
				if ( $ref !== '' ) {
					$lines[] = 'CorrectContent: ' . $ref;
				}
			}
			if ( ! empty( $q['related_content'] ) ) {
				$lines[] = 'RelatedContent: ' . implode( ', ', (array) $q['related_content'] );
			}
			if ( ! empty( $q['topics'] ) ) {
				$lines[] = 'TOPIC: ' . implode( ', ', (array) $q['topics'] );
			}
			$lines[] = '';
		}
		return implode( "\n", $lines );
	}

	/**
	 * Parse admin textarea into question arrays.
	 *
	 * @param string $content Raw admin content.
	 * @return array
	 */
	public function parse_content_to_questions( $content ) {
		$questions = array();
		$blocks    = preg_split( "/\n\s*\n/", trim( (string) $content ) );

		foreach ( $blocks as $block ) {
			$lines           = array_values( array_filter( array_map( 'trim', explode( "\n", $block ) ) ) );
			$question_text   = '';
			$options         = array();
			$correct         = '';
			$correct_content = array();
			$related_content = array();
			$topics          = array();

			foreach ( $lines as $line ) {
				if ( preg_match( '/^([A-D]):\s*(.+)$/i', $line, $m ) ) {
					$options[] = array(
						'key'  => strtoupper( $m[1] ),
						'text' => $m[2],
					);
				} elseif ( preg_match( '/^CORRECT:\s*(.+)$/i', $line, $m ) ) {
					$correct = strtoupper( trim( $m[1] ) );
				} elseif ( preg_match( '/^CorrectContent:\s*(.+)$/i', $line, $m ) ) {
					$correct_content[] = trim( $m[1] );
				} elseif ( preg_match( '/^RelatedContent:\s*(.+)$/i', $line, $m ) ) {
					$parts           = array_map( 'trim', explode( ',', $m[1] ) );
					$related_content = array_merge( $related_content, $parts );
				} elseif ( preg_match( '/^TOPIC:\s*(.+)$/i', $line, $m ) ) {
					$parts  = array_map( 'trim', explode( ',', $m[1] ) );
					$topics = array_merge( $topics, $parts );
				} elseif ( $question_text === '' ) {
					$question_text = $line;
				}
			}

			if ( $question_text !== '' && ! empty( $options ) && $correct !== '' ) {
				$questions[] = array(
					'id'              => 'q' . ( count( $questions ) + 1 ),
					'text'            => $question_text,
					'options'         => $options,
					'correct'         => $correct,
					'correct_content' => $correct_content,
					'related_content' => $related_content,
					'topics'          => $topics,
				);
			}
		}

		return $questions;
	}

	/**
	 * Validate user input before scoring.
	 *
	 * @param mixed $input Answer map, letter list, or empty (not yet answered).
	 * @return true|WP_Error
	 */
	public function validate_input( $input ) {
		if ( $input === null || $input === '' || $input === array() ) {
			return new WP_Error( 'invalid_input', __( 'Please answer the questions before submitting.', 'flosc' ) );
		}
		if ( ! is_string( $input ) && ! is_array( $input ) ) {
			return new WP_Error( 'invalid_input', __( 'Invalid answer format.', 'flosc' ) );
		}
		return true;
	}

	/**
	 * Score answers against expected content or sample defaults.
	 *
	 * Compatible with FLOSC_Abstract_Quiz_Type::analyze( $input, $expected_content, $context = [] ).
	 *
	 * $input may be:
	 *   - array map: q1 => 'B', q2 => 'A', ...  or  0 => 'B', 1 => 'A', ...
	 *   - string: "A,B,C,..." or "A\nB\nC"
	 *   - JSON string of the same map/list
	 *
	 * @param mixed  $input             User answers.
	 * @param string $expected_content  Admin-edited question bank (optional).
	 * @param array  $context           Unused optional context.
	 * @return array score, correct, incorrect (structured), response_key, details
	 */
	public function analyze( $input, $expected_content, $context = array() ) {
		$questions = ! empty( $expected_content )
			? $this->parse_content_to_questions( $expected_content )
			: array();

		if ( empty( $questions ) ) {
			$questions = $this->get_default_questions();
		}

		$user_answers = $this->normalize_user_answers( $input );

		$correct   = array();
		$incorrect = array();
		$details   = array();

		foreach ( $questions as $i => $question ) {
			$qid         = $question['id'] ?? ( 'q' . ( $i + 1 ) );
			$correct_key = strtoupper( trim( (string) $question['correct'] ) );
			$user_key    = $this->lookup_user_answer( $user_answers, $qid, $i );
			$is_correct  = ( $user_key !== '' && $user_key === $correct_key );

			$item = array(
				'question_index'  => $i + 1,
				'question_id'     => $qid,
				'question'        => $question['text'] ?? '',
				'user_answer'     => $user_key,
				'correct_answer'  => $correct_key,
				'answer'          => $correct_key,
				'topics'          => array_values( (array) ( $question['topics'] ?? array() ) ),
				'correct_content' => (array) ( $question['correct_content'] ?? array() ),
				'related_content' => (array) ( $question['related_content'] ?? array() ),
			);

			if ( $is_correct ) {
				$correct[] = $item;
			} else {
				$incorrect[] = $item;
			}

			$details[] = array_merge( $item, array( 'is_correct' => $is_correct ) );
		}

		$total_possible = count( $questions );
		$total_correct  = count( $correct );
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
				'items'          => $details,
			),
		);
	}

	/**
	 * Admin settings for this quiz type.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		return array(
			'show_topics' => array(
				'type'        => 'checkbox',
				'label'       => 'Show Topics in Results',
				'default'     => true,
				'description' => 'Display topic labels for missed items in results messaging.',
			),
		);
	}

	/**
	 * Sample question bank: 10 generic topics (not subject-specific).
	 * floscAdmins replace this in Quiz admin for each flow.
	 *
	 * @return array
	 */
	public function get_default_questions() {
		$topics = array(
			1  => array( 'label' => 'Topic 1 — Getting started', 'slug' => 'topic-1-getting-started' ),
			2  => array( 'label' => 'Topic 2 — Core ideas', 'slug' => 'topic-2-core-ideas' ),
			3  => array( 'label' => 'Topic 3 — Practice basics', 'slug' => 'topic-3-practice-basics' ),
			4  => array( 'label' => 'Topic 4 — Common mistakes', 'slug' => 'topic-4-common-mistakes' ),
			5  => array( 'label' => 'Topic 5 — Building habits', 'slug' => 'topic-5-building-habits' ),
			6  => array( 'label' => 'Topic 6 — Intermediate skills', 'slug' => 'topic-6-intermediate-skills' ),
			7  => array( 'label' => 'Topic 7 — Applying skills', 'slug' => 'topic-7-applying-skills' ),
			8  => array( 'label' => 'Topic 8 — Feedback loops', 'slug' => 'topic-8-feedback-loops' ),
			9  => array( 'label' => 'Topic 9 — Advanced practice', 'slug' => 'topic-9-advanced-practice' ),
			10 => array( 'label' => 'Topic 10 — Next steps', 'slug' => 'topic-10-next-steps' ),
		);

		$out = array();
		foreach ( $topics as $n => $meta ) {
			$out[] = array(
				'id'              => 'q' . $n,
				'text'            => sprintf(
					/* translators: %s: topic label */
					__( 'Sample question for %s. (Replace this entire quiz in FLOSC → Quiz with your own items.) Which statement is true?', 'flosc' ),
					$meta['label']
				),
				'options'         => array(
					array( 'key' => 'A', 'text' => __( 'This is a placeholder wrong answer.', 'flosc' ) ),
					array( 'key' => 'B', 'text' => __( 'This is the sample correct answer for this topic.', 'flosc' ) ),
					array( 'key' => 'C', 'text' => __( 'Another placeholder wrong answer.', 'flosc' ) ),
					array( 'key' => 'D', 'text' => __( 'Another placeholder wrong answer.', 'flosc' ) ),
				),
				'correct'         => 'B',
				// Placeholder slugs — map to real posts in admin for each flow.
				'correct_content' => array( 'post:sample-' . $meta['slug'] ),
				'related_content' => array( 'post:sample-' . $meta['slug'] . '-extra' ),
				'topics'          => array( $meta['slug'] ),
			);
		}
		return $out;
	}

	/**
	 * Normalize raw input into a flat answer map/list.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	private function normalize_user_answers( $input ) {
		if ( is_array( $input ) ) {
			return $input;
		}

		if ( ! is_string( $input ) ) {
			return array();
		}

		$trim = trim( $input );
		if ( $trim === '' ) {
			return array();
		}

		// JSON object/array of answers.
		if ( ( $trim[0] === '{' || $trim[0] === '[' ) ) {
			$decoded = json_decode( $trim, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Comma- or newline-separated letter list: A,B,C or A\nB\nC
		if ( strpos( $trim, ',' ) !== false ) {
			$parts = explode( ',', $trim );
		} else {
			$parts = preg_split( "/\r\n|\n|\r/", $trim );
		}

		return array_map(
			static function ( $v ) {
				return strtoupper( trim( (string) $v ) );
			},
			$parts
		);
	}

	/**
	 * Resolve the user's selected key for one question.
	 *
	 * @param array  $user_answers Normalized answers.
	 * @param string $qid          Question id (e.g. q3).
	 * @param int    $index        Zero-based index.
	 * @return string Uppercase letter or empty.
	 */
	private function lookup_user_answer( array $user_answers, $qid, $index ) {
		$candidates = array(
			$qid,
			(string) $qid,
			$index,
			(string) $index,
			$index + 1,
			(string) ( $index + 1 ),
		);

		foreach ( $candidates as $key ) {
			if ( array_key_exists( $key, $user_answers ) ) {
				return strtoupper( trim( (string) $user_answers[ $key ] ) );
			}
		}

		// Numeric list: answers ordered by question index.
		if ( array_key_exists( $index, $user_answers ) ) {
			return strtoupper( trim( (string) $user_answers[ $index ] ) );
		}

		return '';
	}
}
