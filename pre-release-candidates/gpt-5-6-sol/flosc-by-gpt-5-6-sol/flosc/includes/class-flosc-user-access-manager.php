<?php
/**
 * FLOSC User Access Manager.
 * Handles visitor/guest/member access levels.
 *
 * @since 9.1.6
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate FLOSC User Access Manager behavior and the WordPress services used by its methods.
 */
class FLOSC_User_Access_Manager {

	private static $instance = null;

/**
 * Coordinate the instance behavior implemented by this code path.
 *
 * @return Mixed Result produced by the instance operation.
 */
public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get current user's access level.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current access level value from the available Word Press and flow state. state is being processed.
	 * @param mixed $flow_id Flow identifier used to resolve flow-scoped configuration and state.
	 * @return String 'visitor', 'guest', or 'member'.
	 */
	public function get_access_level( $user_id = null, $flow_id = null ) {

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// Not logged in = visitor.
		if ( ! $user_id ) {
			return 'visitor';
		}

		// v1.1.0: WordPress admins are ALWAYS members (for testing all content).
		if ( user_can( $user_id, 'manage_options' ) ) {
			return 'member';
		}

		// Check if member.
		if ( $this->is_member( $user_id, $flow_id ) ) {
			return 'member';
		}

		// Logged in but not member = guest.
		return 'guest';
	}

	/**
	 * Check if user is a member (full entitlement).
	 *
	 * Bridges legacy keys plus FLOSC_Member_Access and sale-side access so RAG.
	 * And AI userState match content gates (sandbox grants, roles, offers).
	 *
	 * @param int $user_id Value consumed by this operation.
	 * @return Bool.
	 */
	/**
	 * Determine whether the current state satisfies member.
	 *
	 * @param int   $user_id Value consumed by this operation.
	 * @param mixed $flow_id Flow identifier used to resolve flow-scoped configuration and state.
	 * @return Bool Whether member applies to the current state.
	 */
	public function is_member( $user_id, $flow_id = null ) {
		if ( ! $user_id ) {
			return false;
		}

		// Admins always member for testing (matches sale/member-access managers).
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Canonical sale-side per-flow membership (preferred).
		if ( function_exists( 'flosc' ) && is_object( flosc() ) && method_exists( flosc(), 'sale' ) ) {
			$sale = flosc()->sale();
			if ( $sale && method_exists( $sale, 'access' ) ) {
				$access = $sale->access();
				if ( $access && method_exists( $access, 'is_member' ) && $access->is_member( $user_id, $flow_id ) ) {
					return true;
				}
			}
		}

		// FLOSC_Member_Access with optional flow.
		if ( class_exists( 'FLOSC_Member_Access' ) ) {
			$ma = FLOSC_Member_Access::instance();
			if ( $ma && method_exists( $ma, 'is_member' ) && $ma->is_member( $user_id, $flow_id ) ) {
				return true;
			}
		}

		// Without a flow context only: legacy global markers (do not use when flow_id set).
		if ( null === $flow_id || '' === $flow_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user && in_array( 'flosc_member', (array) $user->roles, true ) ) {
				return true;
			}
			$member_status = get_user_meta( $user_id, 'flosc_member_status', true );
			if ( 'active' === $member_status ) {
				return true;
			}
			$member_access = get_user_meta( $user_id, '_flosc_member_access', true );
			if ( 'true' === $member_access || true === $member_access || '1' === $member_access ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Grant member access to user.
	 *
	 * @param int   $user_id Value consumed by this operation.
	 * @param mixed $reason  Input consumed by the Persist the grant member access state in Word Press storage. operation.
	 */
	public function grant_member_access( $user_id, $reason = 'quiz_completion' ) {

		update_user_meta( $user_id, 'flosc_member_status', 'active' );
		update_user_meta( $user_id, 'flosc_member_granted_date', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'flosc_member_granted_reason', $reason );

		// Log the grant.
		if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
			flosc_log( "FLOSC: Granted member access to user {$user_id} - Reason: {$reason}" );
		}

		// Fire action for extensibility.
		do_action( 'flosc_member_access_granted', $user_id, $reason );
	}

	/**
	 * Revoke member access.
	 *
	 * @param int $user_id Value consumed by this operation.
	 */
	public function revoke_member_access( $user_id ) {

		delete_user_meta( $user_id, 'flosc_member_status' );

		if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
			flosc_log( "FLOSC: Revoked member access from user {$user_id}" );
		}

		do_action( 'flosc_member_access_revoked', $user_id );
	}

	/**
	 * Get user context for AI.
	 * Returns all relevant user data.
	 *
	 * @param int   $user_id Value consumed by this operation.
	 * @param mixed $flow_id Flow identifier used to resolve flow-scoped configuration and state.
	 * @return Array.
	 */
	public function get_user_context( $user_id = null, $flow_id = null ) {

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$access_level = $this->get_access_level( $user_id, $flow_id );

		$context = array(
			'user_id'      => $user_id,
			'access_level' => $access_level,
			'is_logged_in' => $user_id > 0,
			'is_visitor'   => 'visitor' === $access_level,
			'is_guest'     => 'guest' === $access_level,
			'is_member'    => 'member' === $access_level,
			'logged_in'    => $user_id > 0, // Alias for backward compatibility.
		);

		// v9.5.5: Add admin status.
		if ( $user_id > 0 ) {
			$context['is_admin'] = user_can( $user_id, 'manage_options' );

			// v9.5.5: Add membership level from WishList Member if available.
			if ( function_exists( 'wlmapi_get_member_levels' ) ) {
				try {
					$levels = wlmapi_get_member_levels( $user_id );
					if ( ! empty( $levels ) && is_array( $levels ) ) {
						$context['membership_levels'] = $levels;
						// Add individual level flags for IVR conditions.
						foreach ( $levels as $level ) {
							if ( is_array( $level ) && isset( $level['id'] ) ) {
								$level_key             = 'is_member_level_' . sanitize_key( $level['id'] );
								$context[ $level_key ] = true;
							}
						}
					}
				} catch ( \Throwable $e ) {
					if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
						if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
							flosc_log( 'FLOSC WishList Member API error: ' . $e->getMessage() );
						}
					}
				}
			}

			// v9.5.5: Alternative - check user meta for membership level.
			$member_level = get_user_meta( $user_id, '_flosc_member_level', true );
			if ( $member_level ) {
				$context['member_level']                                       = $member_level;
				$context[ 'is_member_level_' . sanitize_key( $member_level ) ] = true;
			}
		}

		// Add quiz results if available.
		if ( $user_id ) {
			// v1.6.8: Use correct meta keys (underscore-prefixed, matching save locations).
			$quiz_results = get_user_meta( $user_id, '_flosc_last_quiz_data', true );
			$quiz_score   = get_user_meta( $user_id, '_flosc_last_quiz_score', true );
			$quiz_date    = get_user_meta( $user_id, '_flosc_quiz_completed_at', true );

			if ( $quiz_results ) {
				$context['quiz_results'] = $quiz_results;
				$context['quiz_score']   = $quiz_score;
				$context['quiz_date']    = $quiz_date;

				// Calculate time since quiz for pricing.
				if ( $quiz_date ) {
					$quiz_timestamp                    = strtotime( $quiz_date );
					$minutes_since_quiz                = ( time() - $quiz_timestamp ) / 60;
					$context['minutes_since_quiz']     = $minutes_since_quiz;
					$context['within_discount_window'] = $minutes_since_quiz < 30;
				}
			}

			// v1.6.7: Condition evaluator fields — matches JS buildIVRContext.
			$context['quiz_taken']        = ! empty( $quiz_score ) || ! empty( get_user_meta( $user_id, '_flosc_quiz_completed_at', true ) );
			$context['score']             = intval( $quiz_score ? $quiz_score : 0 );
			$context['purchased']         = $this->is_member( $user_id, $flow_id );
			$context['lesson_viewed']     = (bool) get_user_meta( $user_id, '_flosc_free_content_item_delivered', true );
			$context['lessons_completed'] = intval( get_user_meta( $user_id, '_flosc_lessons_completed', true ) );
			$context['onboarded']         = (bool) get_user_meta( $user_id, '_flosc_funnel_completed', true );
		}

		return $context;
	}

	/**
	 * Check if user can access specific content level.
	 *
	 * @param string $required_level 'visitor', 'guest', or 'member'.
	 * @param int    $user_id        Value consumed by this operation.
	 * @return Bool.
	 */
	public function can_access_level( $required_level, $user_id = null ) {

		$user_level = $this->get_access_level( $user_id );

		$hierarchy = array(
			'visitor' => 1,
			'guest'   => 2,
			'member'  => 3,
		);

		$user_rank     = $hierarchy[ $user_level ] ?? 1;
		$required_rank = $hierarchy[ $required_level ] ?? 1;

		return $user_rank >= $required_rank;
	}
}
