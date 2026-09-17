<?php
/**
 * FLOSC RAG Access Controller.
 * Server-side enforcement layer - deny-by-default for RAG tool access.
 *
 * @package FLOSC
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate FLOSC RAG Access Controller behavior and the WordPress services used by its methods.
 */
class FLOSC_RAG_Access_Controller {

	private $flosc_user_session;
	private $flosc_rag_manager;

/**
 * Coordinate the construct behavior implemented by this code path.
 *
 * @param mixed $flosc_user_session Input consumed by the Coordinate the construct behavior implemented by this code path. operation.
 */
public function __construct( $flosc_user_session ) {
		$this->flosc_user_session = $flosc_user_session;
		$this->flosc_rag_manager  = FLOSC_RAG_Manager::instance();
	}

	/**
	 * Execute tool with access control (deny-by-default)
	 *
	 * @param string $flosc_tool_name Tool to execute.
	 * @param array  $flosc_args      Tool arguments.
	 * @return Mixed Tool result or denial payload.
	 */
	public function flosc_execute_tool( $flosc_tool_name, $flosc_args ) {
		// CHECK ACCESS BEFORE EXECUTING.
		$flosc_access_check = $this->flosc_check_tool_access( $flosc_tool_name, $flosc_args );

		if ( ! $flosc_access_check['flosc_allowed'] ) {
			return $this->flosc_denial_payload(
				$flosc_access_check['flosc_reason'],
				$flosc_access_check['flosc_cta']
			);
		}

		// Get user's access level and flow context.
		$flosc_state            = $this->flosc_user_session->flosc_get();
		$flosc_access_level     = $flosc_state['flosc_access_level'];
		$flosc_flow_category_id = $flosc_state['flosc_flow']['flosc_wp_category_id'] ?? 0;

		// Execute tool via RAG manager's execute_tool() method.
		$flosc_result = $this->flosc_rag_manager->execute_tool(
			$flosc_tool_name,
			$flosc_args,
			$flosc_access_level,
			$flosc_flow_category_id
		);

		// Validate output.
		return $this->flosc_validate_output( $flosc_result, $flosc_tool_name );
	}

	/**
	 * Check tool access based on user session.
	 *
	 * @param string $flosc_tool_name Value consumed by this operation.
	 * @param mixed  $flosc_args      Optional arguments that refine how the Coordinate the check tool access behavior implemented by this code path. operation runs.
	 * @return Array Access check result.
	 */
	private function flosc_check_tool_access( $flosc_tool_name, $flosc_args ) {
		$flosc_state = $this->flosc_user_session->flosc_get();

		switch ( $flosc_tool_name ) {
			case 'get_lesson_content':
				return $this->flosc_check_lesson_access( $flosc_args['lesson_number'] ?? null, $flosc_state );

			case 'search_posts':
			case 'search_knowledge_base':
				// Always allowed (but results filtered by access level).
				return array( 'flosc_allowed' => true );

			default:
				// Deny by default for unknown tools.
				return array(
					'flosc_allowed' => false,
					'flosc_reason'  => 'Unknown tool',
					'flosc_cta'     => null,
				);
		}
	}

	/**
	 * Check lesson access.
	 *
	 * @param int|null $flosc_lesson_number Value consumed by this operation.
	 * @param mixed    $flosc_state         Input consumed by the Coordinate the check lesson access behavior implemented by this code path. operation.
	 * @return Array Access check result.
	 */
	private function flosc_check_lesson_access( $flosc_lesson_number, $flosc_state ) {
		$flosc_user_type    = $flosc_state['flosc_user_type'];
		$flosc_access_level = $flosc_state['flosc_access_level'];

		// Admin: always allowed.
		if ( 'flosc_admin' === $flosc_user_type ) {
			return array( 'flosc_allowed' => true );
		}

		// Member: always allowed.
		if ( 'member' === $flosc_access_level ) {
			return array( 'flosc_allowed' => true );
		}

		// Guest: ONLY their free lesson.
		if ( 'flosc_guest' === $flosc_user_type ) {
			$flosc_free_lesson = $flosc_state['flosc_quiz']['flosc_free_lesson_number'];
			if ( null !== $flosc_free_lesson && null !== $flosc_lesson_number
				&& (int) $flosc_lesson_number === (int) $flosc_free_lesson ) {
				return array( 'flosc_allowed' => true );
			}
			return array(
				'flosc_allowed' => false,
				'flosc_reason'  => 'Only your free lesson is available',
				'flosc_cta'     => 'unlock_full_access',
			);
		}

		// Visitor: MUST take quiz.
		return array(
			'flosc_allowed' => false,
			'flosc_reason'  => 'Take quiz to unlock your free lesson',
			'flosc_cta'     => 'take_quiz',
		);
	}

	/**
	 * Create denial payload.
	 *
	 * @param string $flosc_reason Value consumed by this operation.
	 * @param mixed  $flosc_cta    Input consumed by the Coordinate the denial payload behavior implemented by this code path. operation.
	 * @return Array Denial payload.
	 */
	private function flosc_denial_payload( $flosc_reason, $flosc_cta ) {
		$flosc_messages = array(
			'take_quiz'          => "I'd love to show you that lesson! First, take our quick 2-minute quiz to get your personalized free lesson. 🎯",
			'unlock_full_access' => 'That lesson is part of full access. Unlock all lessons now to continue your learning journey. ✨',
			'login_required'     => 'Please log in to access your personalized content.',
		);

		return array(
			'flosc_denied'              => true,
			'flosc_reason'              => $flosc_reason,
			'flosc_cta'                 => $flosc_cta,
			'flosc_user_facing_message' => $flosc_messages[ $flosc_cta ] ?? 'This content requires additional access.',
		);
	}

	/**
	 * Validate tool output.
	 *
	 * @param mixed $flosc_result    Value consumed by this operation.
	 * @param mixed $flosc_tool_name Name or key used to select the Coordinate the output behavior implemented by this code path. value.
	 * @return Mixed.
	 */
	private function flosc_validate_output( $flosc_result, $flosc_tool_name ) {
		// If already denied, pass through.
		if ( isset( $flosc_result['flosc_denied'] ) ) {
			return $flosc_result;
		}

		// Log suspicious patterns.
		if ( 'flosc_get_lesson_content' === $flosc_tool_name && strlen( $flosc_result['content'] ?? '' ) > 5000 ) {
			if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
				flosc_log( 'FLOSC RAG Access Controller: Potential content leak detected' );
			}
		}

		return $flosc_result;
	}
}
