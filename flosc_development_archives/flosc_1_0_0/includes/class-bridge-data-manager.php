<?php
/**
 * FLOSC Bridge Data Manager
 * 
 * Handles bridge data state creation, storage, and retrieval
 * Bridge data = quiz completed + user profile created + free preview shown
 * 
 * User states:
 * 1. No profile: Anonymous, can take quiz but no email
 * 2. Profile, not paid (BRIDGE DATA STATE): Took quiz, has WordPress profile + email, can see preview
 * 3. Profile, paid: Has access to full content
 * 
 * @package FLOSC
 */

class FLOSC_Bridge_Data_Manager {

	/**
	 * Create bridge data after quiz completion
	 * 
	 * Stores quiz results in user meta and marks user as in bridge data state
	 * 
	 * @param int    $user_id WordPress user ID
	 * @param string $quiz_id Quiz ID
	* @param array  $scoring_results From FLOSC_Quiz_Manager::score_flosc_quiz()
	 * @return bool Success
	 */
	public function flosc_create_bridge_data( $user_id, $quiz_id, $scoring_results ) {
		// TODO: Store bridge data in user meta
		// Pseudocode:
		// update_user_meta( $user_id, "_flosc_completed_quiz_$quiz_id", array(
		//     'date' => current_time( 'mysql' ),
		//     'score' => $scoring_results['score'],
		//     'percentage' => $scoring_results['percentage'],
		//     'correct_items' => $scoring_results['correct_items'],
		//     'incorrect_items' => $scoring_results['incorrect_items'],
		//     'item_results' => $scoring_results['item_results'],
		// ));
		//
		// // Mark user as in bridge data state
		// update_user_meta( $user_id, '_flosc_bridge_data_state', true );
		// update_user_meta( $user_id, "_flosc_bridge_quiz_$quiz_id", $quiz_id );

		return false;
	}

	/**
	 * Get bridge data for user and quiz
	 * 
	 * @param int    $user_id WordPress user ID
	 * @param string $quiz_id Quiz ID
	 * @return array|false Bridge data or false if quiz not completed
	 */
	public function get_flosc_bridge_data( $user_id, $quiz_id ) {
		// TODO: Retrieve bridge data from user meta
		// Pseudocode:
		// $bridge_data = get_user_meta( $user_id, "_flosc_completed_quiz_$quiz_id", true );
		// return ! empty( $bridge_data ) ? $bridge_data : false;

		return false;
	}

	/**
	 * Check if user is in bridge data state for quiz
	 * 
	 * @param int    $user_id WordPress user ID
	 * @param string $quiz_id Quiz ID
	 * @return bool
	 */
	public function is_in_flosc_bridge_state( $user_id, $quiz_id ) {
		// TODO: Check if user completed this quiz
		// Pseudocode:
		// return ! empty( get_user_meta( $user_id, "_flosc_completed_quiz_$quiz_id", true ) );

		return false;
	}

	/**
	 * Check if user has any profile (quiz completed)
	 * 
	 * @param int $user_id WordPress user ID
	 * @return bool
	 */
	public function flosc_has_profile( $user_id ) {
		// TODO: Check if user completed any quiz
		// Pseudocode:
		// $user_quizzes = get_user_meta( $user_id, '_flosc_quiz_attempts', true );
		// return ! empty( $user_quizzes ) && is_array( $user_quizzes ) && count( $user_quizzes ) > 0;

		return false;
	}

	/**
	 * Get correct answers for display in bridge data
	 * 
	 * Builds confirmation message: "You got these right..."
	 * 
	 * @param int    $user_id WordPress user ID
	 * @param string $quiz_id Quiz ID
	 * @return array Correct item information
	 */
	public function get_flosc_correct_answers( $user_id, $quiz_id ) {
		// TODO: Format correct items for display
		// Pseudocode:
		// $bridge_data = $this->get_flosc_bridge_data( $user_id, $quiz_id );
		// if ( ! $bridge_data ) {
		//     return array();
		// }
		//
		// $correct_items = $bridge_data['correct_items'];
		// $formatted = array();
		//
		// foreach ( $correct_items as $item_id ) {
		//     $formatted[] = array(
		//         'item_id' => $item_id,
		//         'text' => 'You got this right! ✓',
		//     );
		// }
		//
		// return $formatted;

		return array();
	}

	/**
	 * Get incorrect answers for display in bridge data
	 * 
	 * Builds reminder: "You missed these..."
	 * 
	 * @param int    $user_id WordPress user ID
	 * @param string $quiz_id Quiz ID
	 * @return array Incorrect item information
	 */
	public function get_flosc_incorrect_answers( $user_id, $quiz_id ) {
		// TODO: Format incorrect items for display
		// Similar to get_correct_answers but for missed items

		return array();
	}

	/**
	 * Get lowest-missed category for bridge data state
	 * 
	 * Used to determine which topic to show as free preview
	 * 
	 * @param int    $user_id WordPress user ID
	 * @param string $quiz_id Quiz ID
	 * @return string Category/topic ID
	 */
	public function get_flosc_weakest_category( $user_id, $quiz_id ) {
		// TODO: Analyze item_results by category
		// Find category with most incorrect items
		// Pseudocode:
		// $bridge_data = $this->get_flosc_bridge_data( $user_id, $quiz_id );
		// if ( ! $bridge_data || empty( $bridge_data['item_results'] ) ) {
		//     return null;
		// }
		//
		// $category_counts = array();
		// foreach ( $bridge_data['item_results'] as $result ) {
		//     if ( ! $result['correct'] ) {
		//         $cat = $result['category'];
		//         $category_counts[ $cat ] = isset( $category_counts[ $cat ] ) ? $category_counts[ $cat ] + 1 : 1;
		//     }
		// }
		//
		// return ! empty( $category_counts ) ? array_key_first( $category_counts ) : null;

		return null;
	}

	/**
	 * Record bridge data viewed/shown event
	 * 
	 * For marketing/analytics: track when user saw their bridge data
	 * 
	 * @param int    $user_id WordPress user ID
	 * @param string $quiz_id Quiz ID
	 * @return bool
	 */
	public function flosc_record_bridge_viewed( $user_id, $quiz_id ) {
		// TODO: Log event for analytics
		// Pseudocode:
		// update_user_meta( $user_id, "_flosc_bridge_viewed_$quiz_id", current_time( 'mysql' ) );
		// do_action( 'flosc_bridge_data_viewed', $user_id, $quiz_id );

		return true;
	}

	/**
	 * Get all quizzes user has completed
	 * 
	 * @param int $user_id WordPress user ID
	 * @return array Quiz IDs user completed
	 */
	public function get_flosc_completed_quizzes( $user_id ) {
		// TODO: Return array of quiz IDs
		// Pseudocode:
		// $user_meta = get_user_meta( $user_id );
		// $completed = array();
		// foreach ( $user_meta as $key => $value ) {
		//     if ( strpos( $key, '_flosc_completed_quiz_' ) === 0 ) {
		//         $quiz_id = str_replace( '_flosc_completed_quiz_', '', $key );
		//         $completed[] = $quiz_id;
		//     }
		// }
		// return $completed;

		return array();
	}
}
