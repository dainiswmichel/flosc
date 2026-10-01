<?php
/**
 * FLOSC Token Payment Provider
 *
 * Internal credit/token system for pay-per-use features.
 * Tokens can be earned through affiliate purchases or bought with real money.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token provider.
 */
class FLOSC_Token_Provider extends FLOSC_Payment_Provider {

	/**
	 * Balance meta key.
	 *
	 * @var string
	 */
	private $balance_meta_key = '_flosc_token_balance';
	/**
	 * Ledger meta key.
	 *
	 * @var string
	 */
	private $ledger_meta_key = '_flosc_token_ledger';

	/**
	 * Get ID.
	 *
	 * @return mixed
	 */
	public function get_id() {
		return 'tokens';
	}

	/**
	 * Get name.
	 *
	 * @return mixed
	 */
	public function get_name() {
		return 'Tokens';
	}

	/**
	 * Get description.
	 *
	 * @return mixed
	 */
	public function get_description() {
		return 'Internal credit system for pay-per-use features. Users earn or purchase tokens.';
	}

	/**
	 * Get icon.
	 *
	 * @return mixed
	 */
	public function get_icon() {
		return '🪙';
	}

	/**
	 * Is configured.
	 *
	 * @return mixed
	 */
	public function is_configured() {
		// Tokens are always "configured" - it's an internal system.
		return true;
	}

	/**
	 * Settings for admin
	 */
	public function get_settings_fields() {
		return array(
			'token_name'     => array(
				'type'        => 'text',
				'label'       => 'Token Name',
				'default'     => 'Credits',
				'description' => 'What to call your tokens (e.g., Credits, Coins, Points)',
			),
			'token_symbol'   => array(
				'type'        => 'text',
				'label'       => 'Token Symbol',
				'default'     => '🪙',
				'description' => 'Emoji or symbol for tokens',
			),
			'signup_bonus'   => array(
				'type'        => 'number',
				'label'       => 'Signup Bonus',
				'default'     => 10,
				'description' => 'Free tokens given on registration',
			),
			'referral_bonus' => array(
				'type'        => 'number',
				'label'       => 'Referral Bonus',
				'default'     => 25,
				'description' => 'Tokens given when a referral signs up',
			),
		);
	}

	/**
	 * Get client config
	 */
	public function get_client_config() {
		return array(
			'name'          => $this->get_setting( 'token_name', 'Credits' ),
			'symbol'        => $this->get_setting( 'token_symbol', '🪙' ),
			'communication' => $this->get_communication_economics(),
		);
	}

	/**
	 * Read a positive integer token-economics setting.
	 *
	 * @param mixed $key      Key.
	 * @param mixed $fallback Fallback.
	 */
	private function get_positive_setting_int( $key, $fallback ) {
		if ( function_exists( 'flosc_get_setting' ) ) {
			$value = intval( flosc_get_setting( 'tokens_' . $key, $this->get_setting( $key, $fallback ) ) );
		} else {
			$value = intval( $this->get_setting( $key, $fallback ) );
		}
		return $value > 0 ? $value : intval( $fallback );
	}

	/**
	 * Communication economics model.
	 * Defaults: 5000 tokens = 5 nominal cents = 2.5 real cents.
	 */
	public function get_communication_economics() {
		$tokens_per_message = $this->get_positive_setting_int( 'communication_tokens_per_message', 5000 );

		$nom_num  = $this->get_positive_setting_int( 'nominal_millicents_per_token_numerator', 1 );
		$nom_den  = $this->get_positive_setting_int( 'nominal_millicents_per_token_denominator', 1 );
		$real_num = $this->get_positive_setting_int( 'real_millicents_per_token_numerator', 1 );
		$real_den = $this->get_positive_setting_int( 'real_millicents_per_token_denominator', 2 );

		$nominal_millicents = intval( round( ( $tokens_per_message * $nom_num ) / $nom_den ) );
		$real_millicents    = intval( round( ( $tokens_per_message * $real_num ) / $real_den ) );

		return array(
			'tokens_per_message'             => $tokens_per_message,
			'nominal_millicents_per_token'   => array(
				'numerator'   => $nom_num,
				'denominator' => $nom_den,
			),
			'real_millicents_per_token'      => array(
				'numerator'   => $real_num,
				'denominator' => $real_den,
			),
			'nominal_millicents_per_message' => $nominal_millicents,
			'real_millicents_per_message'    => $real_millicents,
			'nominal_cents_per_message'      => $nominal_millicents / 1000,
			'real_cents_per_message'         => $real_millicents / 1000,
		);
	}

	/**
	 * Convert real millicents to tokens using configured real-millicents ratio.
	 *
	 * @param mixed $real_millicents Real millicents.
	 */
	public function convert_real_millicents_to_tokens( $real_millicents ) {
		$real_millicents = max( 0, intval( $real_millicents ) );
		if ( $real_millicents <= 0 ) {
			return 0;
		}

		$economics = $this->get_communication_economics();
		$num       = max( 1, intval( $economics['real_millicents_per_token']['numerator'] ?? 1 ) );
		$den       = max( 1, intval( $economics['real_millicents_per_token']['denominator'] ?? 2 ) );

		return max( 1, intval( ceil( ( $real_millicents * $den ) / $num ) ) );
	}

	/**
	 * Process payment (spend tokens)
	 *
	 * @param mixed $user_id      User ID.
	 * @param mixed $offer        Offer.
	 * @param mixed $payment_data Payment data.
	 */
	public function process_payment( $user_id, $offer, $payment_data = array() ) {
		// PAY-01B: token provider never treats missing/zero cost as free unlock of a paid offer.
		if ( ! is_array( $offer['pricing']['tokens'] ?? null ) || ! array_key_exists( 'cost', $offer['pricing']['tokens'] ) ) {
			return new WP_Error(
				'invalid_token_price',
				__( 'Token cost is not configured for this offer', 'flosc' ),
				array( 'status' => 400 )
			);
		}
		$cost = intval( $offer['pricing']['tokens']['cost'] );
		if ( $cost <= 0 ) {
			return new WP_Error(
				'invalid_token_price',
				__( 'Token purchases require a positive token cost', 'flosc' ),
				array( 'status' => 400 )
			);
		}

		$balance = $this->get_balance( $user_id );

		if ( $balance < $cost ) {
			return new WP_Error(
				'insufficient_tokens',
				sprintf(
					'Insufficient tokens. Required: %d, Available: %d',
					$cost,
					$balance
				),
				array(
					'required'  => $cost,
					'available' => $balance,
				)
			);
		}

		$deducted = $this->deduct( $user_id, $cost, 'Purchase: ' . ( $offer['name'] ?? $offer['id'] ?? 'offer' ) );
		if ( is_wp_error( $deducted ) ) {
			return $deducted;
		}

		return array(
			'success'        => true,
			'transaction_id' => 'token_purchase_' . $user_id . '_' . time() . '_' . wp_generate_password( 6, false, false ),
			'amount'         => $cost,
			'currency'       => 'tokens',
			'new_balance'    => $this->get_balance( $user_id ),
			'status'         => 'completed',
		);
	}

	/**
	 * Get user's token balance
	 *
	 * @param mixed $user_id User ID.
	 */
	public function get_balance( $user_id ) {
		$balance     = get_user_meta( $user_id, $this->balance_meta_key, true );
		$flosc_value = intval( $balance );
		return $flosc_value ? $flosc_value : 0;
	}

	/**
	 * Add tokens to user's balance
	 *
	 * @param mixed  $user_id User ID.
	 * @param mixed  $amount  Amount.
	 * @param string $reason  Reason.
	 * @param mixed  $meta    Meta.
	 */
	public function credit( $user_id, $amount, $reason = '', $meta = array() ) {
		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Amount must be positive', 'flosc' ) );
		}

		$current     = $this->get_balance( $user_id );
		$new_balance = $current + $amount;

		update_user_meta( $user_id, $this->balance_meta_key, $new_balance );

		$this->log_transaction(
			$user_id,
			array(
				'type'           => 'credit',
				'amount'         => $amount,
				'reason'         => $reason,
				'balance_before' => $current,
				'balance_after'  => $new_balance,
				'meta'           => $meta,
				'timestamp'      => current_time( 'mysql' ),
			)
		);

		do_action( 'flosc_tokens_credited', $user_id, $amount, $reason );

		return $new_balance;
	}

	/**
	 * Deduct tokens from user's balance (atomic conditional debit).
	 *
	 * Uses a row lock on usermeta when available so two concurrent purchases
	 * that can only afford one debit cannot both succeed (PAY-ACC-01).
	 *
	 * @param mixed  $user_id User ID.
	 * @param mixed  $amount  Amount.
	 * @param string $reason  Reason.
	 * @param mixed  $meta    Meta.
	 */
	public function deduct( $user_id, $amount, $reason = '', $meta = array() ) {
		$user_id = absint( $user_id );
		$amount  = intval( $amount );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user', 'flosc' ) );
		}
		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Amount must be positive', 'flosc' ) );
		}

		global $wpdb;
		$meta_key = $this->balance_meta_key;
		$locked   = false;
		$current  = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic debit under row lock
		$wpdb->query( 'START TRANSACTION' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- atomic token/affiliate ledger under transaction; no WP API for row lock
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1 FOR UPDATE",
				$user_id,
				$meta_key
			)
		);
		if ( $row ) {
			$locked  = true;
			$current = intval( $row->meta_value );
		} else {
			// No row yet: create zero balance under the same transaction path.
			$current = 0;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- atomic usermeta balance row under transaction (user_id + key, not open meta scans)
			$wpdb->insert(
				$wpdb->usermeta,
				array(
					'user_id'    => $user_id,
					'meta_key'   => $meta_key,
					'meta_value' => '0',
				),
				array( '%d', '%s', '%s' )
			);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- end of atomic ledger block
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1 FOR UPDATE",
					$user_id,
					$meta_key
				)
			);
			if ( $row ) {
				$locked  = true;
				$current = intval( $row->meta_value );
			}
		}

		if ( ! $locked ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'debit_failed', __( 'Could not lock token balance', 'flosc' ) );
		}

		if ( $current < $amount ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'insufficient_balance', __( 'Insufficient token balance', 'flosc' ) );
		}

		$new_balance = $current - $amount;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- atomic debit by umeta_id under row lock
		$updated = $wpdb->update(
			$wpdb->usermeta,
			array( 'meta_value' => (string) $new_balance ),
			array( 'umeta_id' => absint( $row->umeta_id ) ),
			array( '%s' ),
			array( '%d' )
		);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- end of atomic ledger block
		if ( false === $updated ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'debit_failed', __( 'Token debit failed', 'flosc' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
		$wpdb->query( 'COMMIT' );
		wp_cache_delete( $user_id, 'user_meta' );

		$this->log_transaction(
			$user_id,
			array(
				'type'           => 'debit',
				'amount'         => $amount,
				'reason'         => $reason,
				'balance_before' => $current,
				'balance_after'  => $new_balance,
				'meta'           => $meta,
				'timestamp'      => current_time( 'mysql' ),
			)
		);

		do_action( 'flosc_tokens_debited', $user_id, $amount, $reason );

		return $new_balance;
	}

	/**
	 * Set balance directly (admin function)
	 *
	 * @param mixed  $user_id User ID.
	 * @param mixed  $amount  Amount.
	 * @param string $reason  Reason.
	 */
	public function set_balance( $user_id, $amount, $reason = 'Admin adjustment' ) {
		$current = $this->get_balance( $user_id );

		update_user_meta( $user_id, $this->balance_meta_key, max( 0, intval( $amount ) ) );

		$this->log_transaction(
			$user_id,
			array(
				'type'           => 'adjustment',
				'amount'         => $amount - $current,
				'reason'         => $reason,
				'balance_before' => $current,
				'balance_after'  => $amount,
				'timestamp'      => current_time( 'mysql' ),
			)
		);

		return $amount;
	}

	/**
	 * Log transaction to ledger
	 *
	 * @param mixed $user_id     User ID.
	 * @param mixed $transaction Transaction.
	 */
	private function log_transaction( $user_id, $transaction ) {
		$ledger = get_user_meta( $user_id, $this->ledger_meta_key, true );
		if ( ! $ledger ) {
			$ledger = array();
		}

		// Keep last 100 transactions.
		$ledger   = array_slice( $ledger, -99 );
		$ledger[] = $transaction;

		update_user_meta( $user_id, $this->ledger_meta_key, $ledger );
	}

	/**
	 * Get user's transaction ledger
	 *
	 * @param mixed $user_id User ID.
	 * @param int   $limit   Limit.
	 */
	public function get_ledger( $user_id, $limit = 50 ) {
		$ledger = get_user_meta( $user_id, $this->ledger_meta_key, true );
		if ( ! $ledger ) {
			$ledger = array();
		}
		return array_slice( array_reverse( $ledger ), 0, $limit );
	}

	/**
	 * Grant signup bonus
	 *
	 * @param mixed $user_id User ID.
	 */
	public function grant_signup_bonus( $user_id ) {
		$bonus = intval( $this->get_setting( 'signup_bonus', 10 ) );

		if ( $bonus > 0 ) {
			$this->credit( $user_id, $bonus, 'Welcome bonus' );
		}

		return $bonus;
	}

	/**
	 * Grant referral bonus
	 *
	 * @param mixed $referrer_id Referrer ID.
	 * @param mixed $referred_id Referred ID.
	 */
	public function grant_referral_bonus( $referrer_id, $referred_id ) {
		$bonus = intval( $this->get_setting( 'referral_bonus', 25 ) );

		if ( $bonus > 0 ) {
			$referred_user = get_user_by( 'ID', $referred_id );
			$this->credit(
				$referrer_id,
				$bonus,
				'Referral: ' . ( $referred_user ? $referred_user->display_name : 'User #' . $referred_id )
			);
		}

		return $bonus;
	}

	/**
	 * Convert tokens from affiliate earnings
	 *
	 * @param mixed $user_id          User ID.
	 * @param mixed $affiliate_amount Affiliate amount.
	 * @param mixed $affiliate_meta   Affiliate meta.
	 */
	public function credit_from_affiliate( $user_id, $affiliate_amount, $affiliate_meta = array() ) {
		// How many tokens one unit of affiliate commission buys. Ten by default.
		$rate   = intval( $this->get_setting( 'affiliate_conversion_rate', 10 ) );
		$tokens = round( $affiliate_amount * $rate );

		if ( $tokens > 0 ) {
			return $this->credit(
				$user_id,
				$tokens,
				'Affiliate commission',
				array_merge(
					$affiliate_meta,
					array(
						'affiliate_amount' => $affiliate_amount,
						'conversion_rate'  => $rate,
					)
				)
			);
		}

		return 0;
	}

	/**
	 * Token costs for actions (configurable)
	 */
	public function get_action_costs() {
		// Default per-turn AI cost = the configured per-message budget. Real cost
		// is applied separately when provider billing is available; this default
		// is only the fallback when no billing metadata exists. A hardcoded "1"
		// silently caps every turn at one token — the budget default is sensible
		// and admin-overridable via the cost_ai_query setting.
		$communication   = $this->get_communication_economics();
		$default_ai_cost = max( 1, intval( $communication['tokens_per_message'] ?? 5000 ) );
		return apply_filters(
			'flosc_token_costs',
			array(
				'ai_query'     => intval( $this->get_setting( 'cost_ai_query', $default_ai_cost ) ),
				'stt_minute'   => intval( $this->get_setting( 'cost_stt_minute', 2 ) ),
				'quiz_attempt' => intval( $this->get_setting( 'cost_quiz', 0 ) ),
				'lesson_view'  => intval( $this->get_setting( 'cost_lesson', 5 ) ),
				'certificate'  => intval( $this->get_setting( 'cost_certificate', 10 ) ),
			)
		);
	}

	/**
	 * Check if user can afford an action
	 *
	 * @param mixed $user_id User ID.
	 * @param mixed $action  Action.
	 */
	public function can_afford( $user_id, $action ) {
		$costs = $this->get_action_costs();
		$cost  = $costs[ $action ] ?? 0;

		if ( 0 === $cost ) {
			return true;
		}

		return $this->get_balance( $user_id ) >= $cost;
	}

	/**
	 * Charge for an action
	 *
	 * @param mixed $user_id User ID.
	 * @param mixed $action  Action.
	 * @param mixed $meta    Meta.
	 */
	public function charge_for_action( $user_id, $action, $meta = array() ) {
		$costs = $this->get_action_costs();
		$cost  = $costs[ $action ] ?? 0;

		if ( 0 === $cost ) {
			return true;
		}

		$result = $this->deduct( $user_id, $cost, 'Action: ' . $action, $meta );

		return ! is_wp_error( $result );
	}
}
