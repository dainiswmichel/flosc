<?php
/**
 * FLOSC Usage Tracker.
 *
 * Tracks usage of metered features:
 * - AI queries.
 * - STT minutes.
 * - Quiz attempts.
 * - Lesson views.
 * - Custom events.
 *
 * Supports:
 * - Usage limits (free tier caps)
 * - Metered billing.
 * - Usage analytics.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate FLOSC Usage Tracker behavior and the WordPress services used by its methods.
 */
class FLOSC_Usage_Tracker {

	private $meta_key        = '_flosc_usage';
	private $limits_meta_key = '_flosc_usage_limits';

	/**
	 * Track a usage event.
	 *
	 * @param mixed $user_id  WordPress user ID whose Persist the track state in Word Press storage. state is being processed.
	 * @param mixed $event    Input consumed by the Persist the track state in Word Press storage. operation.
	 * @param mixed $quantity Input consumed by the Persist the track state in Word Press storage. operation.
	 * @param mixed $meta     Input consumed by the Persist the track state in Word Press storage. operation.
	 * @return Mixed Result produced by the track operation.
	 */
	public function track( $user_id, $event, $quantity = 1, $meta = array() ) {
		$usage  = $this->get_user_usage( $user_id );
		$period = $this->get_current_period();

		if ( ! isset( $usage[ $period ] ) ) {
			$usage[ $period ] = array();
		}

		if ( ! isset( $usage[ $period ][ $event ] ) ) {
			$usage[ $period ][ $event ] = array(
				'count'    => 0,
				'quantity' => 0,
				'first_at' => null,
				'last_at'  => null,
			);
		}

		++$usage[ $period ][ $event ]['count'];
		$usage[ $period ][ $event ]['quantity'] += $quantity;
		$usage[ $period ][ $event ]['last_at']   = current_time( 'mysql' );

		if ( ! $usage[ $period ][ $event ]['first_at'] ) {
			$usage[ $period ][ $event ]['first_at'] = current_time( 'mysql' );
		}

		// Store event detail if meta provided.
		if ( ! empty( $meta ) ) {
			if ( ! isset( $usage[ $period ][ $event ]['details'] ) ) {
				$usage[ $period ][ $event ]['details'] = array();
			}
			// Keep last 50 details per event.
			$usage[ $period ][ $event ]['details'][] = array_merge(
				$meta,
				array(
					'timestamp' => current_time( 'mysql' ),
					'quantity'  => $quantity,
				)
			);
			$usage[ $period ][ $event ]['details']   = array_slice( $usage[ $period ][ $event ]['details'], -50 );
		}

		update_user_meta( $user_id, $this->meta_key, $usage );

		do_action( 'flosc_usage_tracked', $user_id, $event, $quantity, $meta );

		return $usage[ $period ][ $event ];
	}

	/**
	 * Get user's usage data.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current user usage value from the available Word Press and flow state. state is being processed.
	 * @param mixed $period  Input consumed by the Resolve the current user usage value from the available Word Press and flow state. operation.
	 * @return Mixed Result produced by the user usage operation.
	 */
	public function get_user_usage( $user_id, $period = null ) {
		$usage = get_user_meta( $user_id, $this->meta_key, true );
		if ( ! $usage ) {
			$usage = array();
		}

		if ( $period ) {
			return $usage[ $period ] ?? array();
		}

		return $usage;
	}

	/**
	 * Get usage for current period.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current current usage value from the available Word Press and flow state. state is being processed.
	 * @return Mixed Result produced by the current usage operation.
	 */
	public function get_current_usage( $user_id ) {
		return $this->get_user_usage( $user_id, $this->get_current_period() );
	}

	/**
	 * Get usage count for a specific event.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current event count value from the available Word Press and flow state. state is being processed.
	 * @param mixed $event   Input consumed by the Resolve the current event count value from the available Word Press and flow state. operation.
	 * @param mixed $period  Input consumed by the Resolve the current event count value from the available Word Press and flow state. operation.
	 * @return Mixed Result produced by the event count operation.
	 */
	public function get_event_count( $user_id, $event, $period = null ) {
		$period = $period ? $period : $this->get_current_period();
		$usage  = $this->get_user_usage( $user_id, $period );

		return $usage[ $event ]['count'] ?? 0;
	}

	/**
	 * Get usage quantity for a specific event.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current event quantity value from the available Word Press and flow state. state is being processed.
	 * @param mixed $event   Input consumed by the Resolve the current event quantity value from the available Word Press and flow state. operation.
	 * @param mixed $period  Input consumed by the Resolve the current event quantity value from the available Word Press and flow state. operation.
	 * @return Mixed Result produced by the event quantity operation.
	 */
	public function get_event_quantity( $user_id, $event, $period = null ) {
		$period = $period ? $period : $this->get_current_period();
		$usage  = $this->get_user_usage( $user_id, $period );

		return $usage[ $event ]['quantity'] ?? 0;
	}

	/**
	 * Get summary of user's usage across all periods.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current user summary value from the available Word Press and flow state. state is being processed.
	 * @return Mixed Result produced by the user summary operation.
	 */
	public function get_user_summary( $user_id ) {
		$all_usage = $this->get_user_usage( $user_id );

		$summary = array(
			'total'          => array(),
			'current_period' => $this->get_current_usage( $user_id ),
			'periods'        => array_keys( $all_usage ),
		);

		// Aggregate totals.
		foreach ( $all_usage as $period => $events ) {
			foreach ( $events as $event => $data ) {
				if ( ! isset( $summary['total'][ $event ] ) ) {
					$summary['total'][ $event ] = array(
						'count'    => 0,
						'quantity' => 0,
					);
				}
				$summary['total'][ $event ]['count']    += $data['count'];
				$summary['total'][ $event ]['quantity'] += $data['quantity'];
			}
		}

		return $summary;
	}

	// =========================================================================.
	// USAGE LIMITS.
	// =========================================================================.

	/**
	 * Set usage limits for a user.
	 *
	 * @param mixed $user_id WordPress user ID whose Persist the limits state in Word Press storage. state is being processed.
	 * @param mixed $limits  Input consumed by the Persist the limits state in Word Press storage. operation.
	 */
	public function set_limits( $user_id, $limits ) {
		update_user_meta( $user_id, $this->limits_meta_key, $limits );
	}

	/**
	 * Get user's usage limits.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current limits value from the available Word Press and flow state. state is being processed.
	 * @return Mixed Result produced by the limits operation.
	 */
	public function get_limits( $user_id ) {
		$limits = get_user_meta( $user_id, $this->limits_meta_key, true );

		if ( empty( $limits ) ) {
			// Return default limits.
			return $this->get_default_limits();
		}

		return $limits;
	}

	/**
	 * Get default limits (free tier)
	 *
	 * @return Mixed Result produced by the default limits operation.
	 */
	public function get_default_limits() {
		return apply_filters(
			'flosc_default_usage_limits',
			array(
				'ai_queries'  => 10,         // Per period.
				'stt_minutes' => 2,         // Per period.
				'quizzes'     => 3,             // Per period.
				'lessons'     => 1,             // Total (one free lesson)
			)
		);
	}

	/**
	 * Check if user has remaining quota for an event.
	 *
	 * @param mixed $user_id  WordPress user ID whose Determine whether the current state satisfies quota. state is being processed.
	 * @param mixed $event    Input consumed by the Determine whether the current state satisfies quota. operation.
	 * @param mixed $quantity Input consumed by the Determine whether the current state satisfies quota. operation.
	 * @return Bool Whether quota applies to the current state.
	 */
	public function has_quota( $user_id, $event, $quantity = 1 ) {
		$limits = $this->get_limits( $user_id );

		// No limit for this event.
		if ( ! isset( $limits[ $event ] ) || -1 === $limits[ $event ] ) {
			return true;
		}

		$used = $this->get_event_quantity( $user_id, $event );

		return ( $used + $quantity ) <= $limits[ $event ];
	}

	/**
	 * Get remaining quota for an event.
	 *
	 * @param mixed $user_id WordPress user ID whose Resolve the current remaining value from the available Word Press and flow state. state is being processed.
	 * @param mixed $event   Input consumed by the Resolve the current remaining value from the available Word Press and flow state. operation.
	 * @return Mixed Result produced by the remaining operation.
	 */
	public function get_remaining( $user_id, $event ) {
		$limits = $this->get_limits( $user_id );

		if ( ! isset( $limits[ $event ] ) || -1 === $limits[ $event ] ) {
			return PHP_INT_MAX; // Unlimited.
		}

		$used = $this->get_event_quantity( $user_id, $event );

		return max( 0, $limits[ $event ] - $used );
	}

	/**
	 * Consume quota (track + check limit in one call)
	 *
	 * @param mixed $user_id  WordPress user ID whose Coordinate the consume behavior implemented by this code path. state is being processed.
	 * @param mixed $event    Input consumed by the Coordinate the consume behavior implemented by this code path. operation.
	 * @param mixed $quantity Input consumed by the Coordinate the consume behavior implemented by this code path. operation.
	 * @param mixed $meta     Input consumed by the Coordinate the consume behavior implemented by this code path. operation.
	 * @return Mixed Result of the consume operation, or a WP_Error when it cannot complete.
	 */
	public function consume( $user_id, $event, $quantity = 1, $meta = array() ) {
		// Check quota first.
		if ( ! $this->has_quota( $user_id, $event, $quantity ) ) {
			return new WP_Error(
				'quota_exceeded',
				/* translators: 1: usage event name (e.g. a quiz or chat request), 2: remaining quota count. */
				sprintf( __( 'Usage limit exceeded for %1$s. Remaining: %2$d', 'flosc' ), $event, $this->get_remaining( $user_id, $event ) ),
				array(
					'event'     => $event,
					'remaining' => $this->get_remaining( $user_id, $event ),
					'requested' => $quantity,
				)
			);
		}

		// Track usage.
		return $this->track( $user_id, $event, $quantity, $meta );
	}

	/**
	 * Grant unlimited quota for specific events.
	 *
	 * @param mixed $user_id WordPress user ID whose Coordinate the grant unlimited behavior implemented by this code path. state is being processed.
	 * @param mixed $events  Input consumed by the Coordinate the grant unlimited behavior implemented by this code path. operation.
	 */
	public function grant_unlimited( $user_id, $events ) {
		$limits = $this->get_limits( $user_id );

		foreach ( (array) $events as $event ) {
			$limits[ $event ] = -1; // -1 = unlimited.
		}

		$this->set_limits( $user_id, $limits );
	}

	/**
	 * Reset usage for a user (new billing period)
	 *
	 * @param mixed $user_id WordPress user ID whose Persist the reset period usage state in Word Press storage. state is being processed.
	 */
	public function reset_period_usage( $user_id ) {
		$usage   = $this->get_user_usage( $user_id );
		$current = $this->get_current_period();

		// Archive current period.
		if ( isset( $usage[ $current ] ) ) {
			$usage[ $current ]['_archived'] = true;
		}

		// Start fresh for new period.
		$new_period           = $this->get_current_period();
		$usage[ $new_period ] = array();

		update_user_meta( $user_id, $this->meta_key, $usage );

		do_action( 'flosc_usage_reset', $user_id );
	}

	// =========================================================================.
	// PERIODS.
	// =========================================================================.

	/**
	 * Get current billing period identifier.
	 *
	 * @return Mixed Result produced by the current period operation.
	 */
	private function get_current_period() {
		// Monthly periods: YYYY-MM.
		return gmdate( 'Y-m' );
	}

	/**
	 * Get period for a specific date.
	 *
	 * @param mixed $date Input consumed by the Resolve the current period for date value from the available Word Press and flow state. operation.
	 * @return Mixed Result produced by the period for date operation.
	 */
	public function get_period_for_date( $date ) {
		return gmdate( 'Y-m', strtotime( $date ) );
	}

	/**
	 * Clean up old usage data (keep last N periods)
	 *
	 * @param mixed $user_id      WordPress user ID whose Persist the cleanup old data state in Word Press storage. state is being processed.
	 * @param mixed $keep_periods Input consumed by the Persist the cleanup old data state in Word Press storage. operation.
	 * @return Mixed Result produced by the cleanup old data operation.
	 */
	public function cleanup_old_data( $user_id, $keep_periods = 12 ) {
		$usage = $this->get_user_usage( $user_id );

		if ( count( $usage ) <= $keep_periods ) {
			return;
		}

		// Sort by period (newest first).
		krsort( $usage );

		// Keep only recent periods.
		$usage = array_slice( $usage, 0, $keep_periods, true );

		update_user_meta( $user_id, $this->meta_key, $usage );
	}

	// =========================================================================.
	// ANALYTICS.
	// =========================================================================.

	/**
	 * Get aggregate usage across all users for a period.
	 *
	 * @param mixed $period Input consumed by the Resolve the current global usage value from the available Word Press and flow state. operation.
	 * @return Mixed Result produced by the global usage operation.
	 */
	public function get_global_usage( $period = null ) {
		$period = $period ? $period : $this->get_current_period();

		$user_ids = function_exists( 'flosc_get_user_ids_for_meta' )
			? flosc_get_user_ids_for_meta( $this->meta_key )
			: array();

		$aggregate = array();

		foreach ( (array) $user_ids as $user_id ) {
			$usage = get_user_meta( (int) $user_id, $this->meta_key, true );
			if ( ! is_array( $usage ) || ! isset( $usage[ $period ] ) || ! is_array( $usage[ $period ] ) ) {
				continue;
			}

			foreach ( $usage[ $period ] as $event => $data ) {
				if ( ! is_string( $event ) || '' === $event || '_' === $event[0] ) {
					continue;
				}
				if ( ! is_array( $data ) ) {
					continue;
				}

				if ( ! isset( $aggregate[ $event ] ) ) {
					$aggregate[ $event ] = array(
						'users'    => 0,
						'count'    => 0,
						'quantity' => 0,
					);
				}

				++$aggregate[ $event ]['users'];
				$aggregate[ $event ]['count']    += (int) ( $data['count'] ?? 0 );
				$aggregate[ $event ]['quantity'] += (int) ( $data['quantity'] ?? 0 );
			}
		}

		return $aggregate;
	}

	/**
	 * Get top users by usage.
	 *
	 * @param mixed $event  Input consumed by the Resolve the current top users value from the available Word Press and flow state. operation.
	 * @param mixed $period Input consumed by the Resolve the current top users value from the available Word Press and flow state. operation.
	 * @param mixed $limit  Input consumed by the Resolve the current top users value from the available Word Press and flow state. operation.
	 * @return Mixed Result produced by the top users operation.
	 */
	public function get_top_users( $event, $period = null, $limit = 10 ) {
		$period = $period ? $period : $this->get_current_period();

		$user_ids = function_exists( 'flosc_get_user_ids_for_meta' )
			? flosc_get_user_ids_for_meta( $this->meta_key )
			: array();

		$users = array();

		foreach ( (array) $user_ids as $user_id ) {
			$usage = get_user_meta( (int) $user_id, $this->meta_key, true );
			if ( ! is_array( $usage ) || ! isset( $usage[ $period ][ $event ] ) || ! is_array( $usage[ $period ][ $event ] ) ) {
				continue;
			}
			$users[] = array(
				'user_id'  => (int) $user_id,
				'count'    => (int) ( $usage[ $period ][ $event ]['count'] ?? 0 ),
				'quantity' => (int) ( $usage[ $period ][ $event ]['quantity'] ?? 0 ),
			);
		}

		// Sort by quantity descending.
		usort(
			$users,
			function ( $a, $b ) {
				return $b['quantity'] - $a['quantity'];
			}
		);

		return array_slice( $users, 0, $limit );
	}
}
