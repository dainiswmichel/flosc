<?php
/**
 * FLOSC Quiz Manager
 * 
 * Handles quiz definitions, scoring, and storage
 * Supports multiple quizzes: draft_quiz_1, quiz_2, quiz_3, etc.
 * 
 * @package FLOSC
 */

class FLOSC_Quiz_Manager {

	/**
	 * Quiz definitions
	 * 
	 * @var array
	 */
	private $flosc_quizzes = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// TODO: Load quiz definitions from JSON/MD files or WordPress options
		// Example structure:
		// $this->flosc_quizzes['draft_quiz_1'] = array(
		//     'title' => 'Draft Quiz 1',
		//     'questions' => array(
		//         array(
		//             'id' => 'q1',
		//             'text' => 'Question text?',
		//             'correct_answer' => 'answer_a',
		//             'options' => array('answer_a' => 'Option A', 'answer_b' => 'Option B'),
		//             'category' => 'topic_1'
		//         ),
		//         // ... more questions
		//     )
		// );
	}

	/**
	* Get quiz definition by ID
	 * 
	 * @param string $quiz_id Quiz ID
	 * @return array|false Quiz definition or false if not found
	 */
	public function get_flosc_quiz( $quiz_id ) {
		// TODO: Return quiz definition if exists, false otherwise
		// return isset( $this->flosc_quizzes[ $quiz_id ] ) ? $this->flosc_quizzes[ $quiz_id ] : false;
		return false;
	}

	/**
	* Score quiz answers
	 * 
	 * Calculates per-item correct/incorrect and overall score
	 * 
	 * @param string $quiz_id Quiz ID
	 * @param array  $answers User answers keyed by question ID
	 * @return array Scoring results:
	 *   - score: (int) Total score
	 *   - percentage: (float) Percentage correct
	 *   - total_questions: (int) Total questions
	 *   - correct_items: (array) IDs of questions user got correct
	 *   - incorrect_items: (array) IDs of questions user got wrong
	 *   - item_results: (array) Detailed per-item results with categories
	 */
	public function score_flosc_quiz( $quiz_id, $answers ) {
		// TODO: Implement scoring logic
		// 1. Get quiz definition
		// 2. For each question, check if answer matches correct_answer
		// 3. Track correct_items and incorrect_items arrays
		// 4. Calculate per-category performance for bridge data
		// 5. Return scoring array

		// Pseudocode:
		// $quiz = $this->get_flosc_quiz( $quiz_id );
		// $results = array(
		//     'score' => 0,
		//     'percentage' => 0,
		//     'total_questions' => count( $quiz['questions'] ),
		//     'correct_items' => array(),
		//     'incorrect_items' => array(),
		//     'item_results' => array(),
		// );
		//
		// foreach ( $quiz['questions'] as $question ) {
		//     $q_id = $question['id'];
		//     $user_answer = isset( $answers[ $q_id ] ) ? $answers[ $q_id ] : null;
		//     $is_correct = ( $user_answer === $question['correct_answer'] );
		//     
		//     if ( $is_correct ) {
		//         $results['correct_items'][] = $q_id;
		//         $results['score']++;
		//     } else {
		//         $results['incorrect_items'][] = $q_id;
		//     }
		//     
		//     $results['item_results'][ $q_id ] = array(
		//         'correct' => $is_correct,
		//         'user_answer' => $user_answer,
		//         'correct_answer' => $question['correct_answer'],
		//         'category' => $question['category'],
		//     );
		// }
		//
		// $results['percentage'] = ( $results['score'] / $results['total_questions'] ) * 100;
		// return $results;

		return array(
			'score' => 0,
			'percentage' => 0,
			'total_questions' => 0,
			'correct_items' => array(),
			'incorrect_items' => array(),
			'item_results' => array(),
		);
	}

	/**
	* Get lowest-missed item for bridge data preview
	 * 
	 * @param string $quiz_id Quiz ID
	 * @param array  $incorrect_items List of incorrect question IDs
	 * @return array Item to show as free preview:
	 *   - item_id: (string) Question ID
	 *   - content: (string) Educational content for this item
	 *   - category: (string) Topic category
	 */
	public function get_flosc_bridge_preview_item( $quiz_id, $incorrect_items ) {
		// TODO: Implement logic to identify "most important" or "highest priority" missed item
		// Options:
		// 1. Random from incorrect_items
		// 2. First missed item (question order)
		// 3. Highest value/priority missed item (from metadata)
		// 4. Most common missed item across all users
		//
		// For now, return first incorrect item as preview

		// Pseudocode:
		// if ( empty( $incorrect_items ) ) {
		//     return false; // User got everything right
		// }
		//
		// $preview_item_id = reset( $incorrect_items );
		// $quiz = $this->get_flosc_quiz( $quiz_id );
		// $question = $this->find_flosc_question_by_id( $quiz, $preview_item_id );
		//
		// return array(
		//     'item_id' => $preview_item_id,
		//     'content' => $this->get_flosc_item_content( $quiz_id, $preview_item_id ),
		//     'category' => $question['category'],
		// );

		return false;
	}

	/**
	 * Find question by ID in quiz
	 * 
	 * @param array  $quiz Quiz definition
	 * @param string $question_id Question ID
	 * @return array|false Question definition or false if not found
	 */
	private function find_flosc_question_by_id( $quiz, $question_id ) {
		// TODO: Search quiz questions array for matching ID
		return false;
	}

	/**
	 * Get educational content for an item
	 * 
	 * @param string $quiz_id Quiz ID
	 * @param string $item_id Question/item ID
	 * @return string Content/explanation
	 */
	private function get_flosc_item_content( $quiz_id, $item_id ) {
		// TODO: Retrieve educational content for this item
		// Could be:
		// - From WordPress post with taxonomy
		// - From JSON/MD in ai_configuration_files/
		// - From structured data in database
		return '';
	}

	/**
	 * Get all quiz IDs
	 * 
	 * @return array List of quiz IDs
	 */
	public function get_all_flosc_quiz_ids() {
		// TODO: Return array of available quiz IDs
		return array_keys( $this->flosc_quizzes );
	}

	/**
	 * Check if quiz exists
	 * 
	 * @param string $quiz_id Quiz ID
	 * @return bool
	 */
	public function flosc_quiz_exists( $quiz_id ) {
		// TODO: Check if quiz definition exists
		return isset( $this->flosc_quizzes[ $quiz_id ] );
	}
}
