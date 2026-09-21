<?php
/**
 * Domain collaborator — FLOSC_Token_Ledger
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The token wallet for one visitor, guest or member, per floscFlow.
 *
 * Tokens are how FLOSC meters AI conversation. Who holds them, and where they
 * are kept, depends on the tier:
 *
 *   visitor  no WordPress user exists, so the balance lives in a transient
 *            keyed by flow and session id, and expires after thirty days
 *   guest    a logged-in account that has not bought access; the balance is
 *            user meta, per flow
 *   member   the same, with the member grant applied
 *
 * Every balance is PER FLOW. Being a member of one floscFlow neither grants nor
 * withholds tokens on another, which is why almost every method here takes a
 * $flow_id and why the guest grant is applied once per flow rather than once
 * per user.
 *
 * This class holds no business rules of its own. It is a façade over
 * FLOSC_Framework, which owns the grant amounts and the charge arithmetic; what
 * lives here is the sequencing — read, reserve, settle — and the locking that
 * keeps two concurrent turns from spending the same visitor's last token twice.
 */
class FLOSC_Token_Ledger {

	/**
	 * The framework instance that owns the grant amounts and charge arithmetic.
	 *
	 * @var FLOSC_Framework
	 */
	private $flosc;

	/**
	 * Constructor.
	 *
	 * @param FLOSC_Framework $flosc Framework instance this ledger delegates to.
	 */
	public function __construct( $flosc ) {
		$this->flosc = $flosc;
	}

	/**
	 * Give a newly logged-in guest their opening balance on one flow.
	 *
	 * The visitor-to-guest step. Whatever the person had left as an anonymous
	 * visitor is carried over and the flow's guest grant is added on top, once
	 * and only once per flow. A second call for the same user and flow is a
	 * no-op, so it is safe on every page load.
	 *
	 * @param int    $user_id        The user who just became a guest.
	 * @param object $token_provider Unused. The wallet is flow meta, not provider
	 *                               state; the parameter stays for callers that
	 *                               already hold a provider.
	 * @param string $flow_id        Flow stem the grant belongs to. Empty means
	 *                               the framework resolves the current flow.
	 * @param string $reason         Unused. Kept for call sites that pass an
	 *                               audit note.
	 * @return int The balance after the grant, or 0 if $user_id is not a real user.
	 */
	public function flosc_ensure_guest_token_baseline( $user_id, $token_provider, $flow_id = '', $reason = '' ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		// Per-flow additive grant; $token_provider unused (wallet is flow meta).
		unset( $token_provider, $reason );
		return $this->flosc->flosc_apply_guest_token_grant_once( $user_id, $flow_id );
	}

	/**
	 * Public entry for product token credits (one-time, recurring, renewals).
	 *
	 * @param int    $user_id The buyer.
	 * @param string $flow_id Flow stem the purchase applies to.
	 * @param string $mode    One of onetime, recurring, recurring_yearly,
	 *                        monthly or yearly. Decides whether the credit is
	 *                        applied once or on each renewal.
	 * @param array  $context Purchase details from the payment provider, passed
	 *                        through for the audit record.
	 * @return array The framework's credit result: what was applied and the
	 *               resulting balance.
	 */
	public function flosc_apply_product_token_credit_public( $user_id, $flow_id = '', $mode = 'onetime', $context = array() ) {
		return $this->flosc->flosc_apply_product_token_credit( $user_id, $flow_id, $mode, $context );
	}

	/**
	 * Apply a subscription top-up.
	 *
	 * @deprecated Use flosc_apply_product_token_credit_public(), which this now
	 *             forwards to unchanged. Kept so older call sites keep working.
	 *
	 * @param int    $user_id   The subscriber.
	 * @param string $flow_id   Flow stem the subscription applies to.
	 * @param string $plan_type Passed through as the credit mode.
	 * @param array  $context   Purchase details from the payment provider.
	 * @return array The framework's credit result.
	 */
	public function flosc_apply_subscription_token_topup_public( $user_id, $flow_id = '', $plan_type = 'monthly', $context = array() ) {
		return $this->flosc->flosc_apply_product_token_credit( $user_id, $flow_id, $plan_type, $context );
	}

	/**
	 * G→M: apply member_token_grant once per flow (guest remaining + grant).
	 * Hooked to flosc_member_access_granted (Access Code, PayPal, sandbox, etc.).
	 *
	 * The flow is resolved in three steps, first match wins: the purchase data,
	 * then the flow the person registered on, then the flow currently being
	 * served. Without that fallback a member who bought through a route that
	 * carries no flow id would be granted tokens on the wrong wallet.
	 *
	 * @param int   $user_id       The user who was just granted access.
	 * @param array $purchase_data Payload from the access-granting event. Its
	 *                             flow_id is preferred when present.
	 * @return int The balance after the grant, or 0 if $user_id is not a real user.
	 */
	public function apply_member_token_grant_on_access( $user_id, $purchase_data = array() ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		$flow_id = '';
		if ( is_array( $purchase_data ) && ! empty( $purchase_data['flow_id'] ) ) {
			$flow_id = sanitize_key( (string) $purchase_data['flow_id'] );
		}
		if ( '' === $flow_id ) {
			$flow_id = sanitize_key( (string) get_user_meta( $user_id, '_flosc_registration_flow', true ) );
		}
		if ( '' === $flow_id ) {
			$current_flow = $this->flosc->get_current_flow();
			$ivr_file     = (string) ( $current_flow['ivr_file'] ?? $current_flow['ivr'] ?? '' );
			$flow_id      = sanitize_key( pathinfo( basename( $ivr_file ), PATHINFO_FILENAME ) );
		}

		return $this->flosc->flosc_apply_member_token_grant_once( $user_id, $flow_id );
	}

	/**
	 * Whether this user should receive V→G guest tokens on a flow.
	 * Members of *this* flow do not; members of other flows still do.
	 *
	 * @param int    $user_id The user being considered.
	 * @param string $flow_id Flow id or stem for the page or request.
	 * @return bool False for administrators and for members of THIS flow. True
	 *              for everyone else, including members of other flows.
	 */
	public function flosc_user_should_receive_guest_tokens( $user_id, $flow_id = '' ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		// Per-flow membership only — membership on one flow must not block guest tokens on another.
		if ( $this->flosc->sale() && method_exists( $this->flosc->sale(), 'access' ) ) {
			if ( $this->flosc->sale()->access()->is_member( $user_id, $flow_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Read an anonymous visitor's balance, seeding it on first contact.
	 *
	 * A visitor has no WordPress user, so the balance lives in a transient keyed
	 * by flow and session. On the first read for a session there is nothing
	 * stored, and the flow's opening visitor allowance is used instead. Either
	 * way the transient is rewritten with a fresh thirty-day life, so an active
	 * visitor's balance does not expire under them mid-conversation.
	 *
	 * @param string $flow_id        Flow stem the wallet belongs to.
	 * @param int    $session_id     Visitor session id.
	 * @param object $token_provider Provider used to resolve the opening
	 *                               allowance for this flow.
	 * @return int The balance, or 0 when the session id or provider is missing.
	 */
	public function flosc_get_visitor_session_token_balance( $flow_id, $session_id, $token_provider ) {
		$session_id = absint( $session_id );
		if ( $session_id <= 0 || ! $token_provider ) {
			return 0;
		}

		$transient_key = $this->flosc->flosc_visitor_token_transient_key( $flow_id, $session_id );
		$stored        = get_transient( $transient_key );
		$balance       = is_numeric( $stored ) ? max( 0, intval( $stored ) ) : -1;

		if ( $balance < 0 ) {
			$balance = $this->flosc->flosc_get_initial_visitor_token_balance( $flow_id, $token_provider );
		}

		set_transient( $transient_key, $balance, 30 * DAY_IN_SECONDS );
		return $balance;
	}

	/**
	 * Write an anonymous visitor's balance back to its transient.
	 *
	 * Negative balances are clamped to zero: a visitor can run out, never into
	 * debt.
	 *
	 * @param string $flow_id    Flow stem the wallet belongs to.
	 * @param int    $session_id Visitor session id.
	 * @param int    $balance    Balance to store. Clamped at zero.
	 * @return int The balance as stored, or 0 when the session id is missing.
	 */
	public function flosc_set_visitor_session_token_balance( $flow_id, $session_id, $balance ) {
		$session_id = absint( $session_id );
		if ( $session_id <= 0 ) {
			return 0;
		}

		$balance = max( 0, intval( $balance ) );
		set_transient( $this->flosc->flosc_visitor_token_transient_key( $flow_id, $session_id ), $balance, 30 * DAY_IN_SECONDS );
		return $balance;
	}

	/**
	 * Charge one conversation turn against an anonymous visitor's balance.
	 *
	 * A visitor is never charged more than they hold: the debit is clamped to
	 * the remaining balance, and the return says what was actually taken rather
	 * than what was asked for. A turn costing nothing reports charged with a
	 * zero amount; a turn attempted on an empty wallet reports NOT charged, and
	 * the caller is expected to refuse the turn.
	 *
	 * @param string $flow_id        Flow stem the wallet belongs to.
	 * @param int    $session_id     Visitor session id.
	 * @param object $token_provider Provider used to price the turn.
	 * @param array  $billing_meta   Usage the provider reported, used to price
	 *                               the turn.
	 * @return array{charged:bool,charge_tokens:int,balance_before:int,balance_after:int}
	 */
	public function flosc_charge_visitor_session_tokens( $flow_id, $session_id, $token_provider, $billing_meta = array() ) {
		$session_id = absint( $session_id );
		if ( $session_id <= 0 || ! $token_provider ) {
			return array(
				'charged'        => false,
				'charge_tokens'  => 0,
				'balance_before' => 0,
				'balance_after'  => 0,
			);
		}

		$charge_tokens = max( 0, intval( $this->flosc->flosc_resolve_chat_charge_tokens( $flow_id, $token_provider, $billing_meta ) ) );

		$balance_before = $this->flosc_get_visitor_session_token_balance( $flow_id, $session_id, $token_provider );
		if ( $charge_tokens <= 0 ) {
			return array(
				'charged'        => true,
				'charge_tokens'  => 0,
				'balance_before' => $balance_before,
				'balance_after'  => $balance_before,
			);
		}

		if ( $balance_before <= 0 ) {
			return array(
				'charged'        => false,
				'charge_tokens'  => $charge_tokens,
				'balance_before' => $balance_before,
				'balance_after'  => $balance_before,
			);
		}

		$applied_charge = min( $charge_tokens, $balance_before );
		$balance_after  = $this->flosc_set_visitor_session_token_balance( $flow_id, $session_id, $balance_before - $applied_charge );

		return array(
			'charged'        => true,
			'charge_tokens'  => $applied_charge,
			'balance_before' => $balance_before,
			'balance_after'  => $balance_after,
		);
	}

	/**
	 * Deduct the configured per-turn gate before the provider call.
	 *
	 * This is a concurrency hold using flosc_get_ai_query_token_cost() (often 1),
	 * not a hold of the eventual millicent-derived debit. A later settle applies
	 * the actual charge up to remaining balance. A provider attempt keeps the
	 * hold (same policy as the previous post-call debit-on-attempt).
	 *
	 * @param string $flow_id        Flow stem the wallet belongs to.
	 * @param int    $session_id     Visitor session id. Also names the lock.
	 * @param int    $estimated_cost Tokens to hold. Clamped to the balance.
	 * @param string $request_id     Identifies this turn. Becomes the
	 *                               reservation id that settle takes back, so
	 *                               it must be unique per turn.
	 * @param object $token_provider Provider used to read the balance.
	 * @return array{reserved:bool,id:string,amount:int,balance_after:int}
	 *               reserved is false when the lock could not be taken or the
	 *               wallet is empty, and the caller must refuse the turn.
	 */
	public function reserve_visitor_tokens( $flow_id, $session_id, $estimated_cost, $request_id, $token_provider ) {
		$session_id     = absint( $session_id );
		$estimated_cost = max( 0, intval( $estimated_cost ) );
		$request_id     = sanitize_key( (string) $request_id );
		if ( $session_id <= 0 || '' === $request_id || ! $token_provider ) {
			return array(
				'reserved'      => false,
				'id'            => '',
				'amount'        => 0,
				'balance_after' => 0,
			);
		}

		$lock         = 'flosc_vtok_lock_' . $session_id;
		$lock_payload = $request_id . '|' . time();
		$got          = add_option( $lock, $lock_payload, '', 'no' );
		if ( ! $got ) {
			global $wpdb;
			$stale_before = time() - 30;
			// Stale-lock steal. add_option() above is the atomic acquire (it returns
			// false when the row already exists); this reclaims a lock whose holder
			// died, and only when it is older than 30s. It must be one atomic
			// conditional UPDATE: get_option() + update_option() is a race in which
			// two requests both read the same stale lock and both believe they won,
			// and WordPress exposes no compare-and-swap for options. $updated === 1
			// is precisely how this request learns it took the lock.
			//
			// Caching is not applicable and would be harmful -- a lock is only a lock
			// if the check reaches the database. Table is core wp_options, values are
			// bound through prepare(), and the query runs only on lock contention.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic lock steal; see above.
			$updated = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options}
                     SET option_value = %s
                     WHERE option_name = %s
                       AND CAST(SUBSTRING_INDEX(option_value, '|', -1) AS UNSIGNED) > 0
                       AND CAST(SUBSTRING_INDEX(option_value, '|', -1) AS UNSIGNED) < %d",
					$lock_payload,
					$lock,
					$stale_before
				)
			);
			$got     = ( 1 === $updated );
			if ( ! $got ) {
				return array(
					'reserved'      => false,
					'id'            => '',
					'amount'        => 0,
					'balance_after' => 0,
				);
			}
		}

		$balance = $this->flosc_get_visitor_session_token_balance( $flow_id, $session_id, $token_provider );
		if ( $balance <= 0 ) {
			delete_option( $lock );
			return array(
				'reserved'      => false,
				'id'            => '',
				'amount'        => 0,
				'balance_after' => $balance,
			);
		}

		$amount = ( $estimated_cost > 0 ) ? min( $estimated_cost, $balance ) : 0;
		if ( $amount > 0 ) {
			$this->flosc_set_visitor_session_token_balance( $flow_id, $session_id, $balance - $amount );
		}

		$reservation = array(
			'id'             => $request_id,
			'flow_id'        => (string) $flow_id,
			'session_id'     => $session_id,
			'amount'         => $amount,
			'balance_before' => $balance,
		);
		set_transient( 'flosc_vtok_res_' . $request_id, $reservation, 15 * MINUTE_IN_SECONDS );
		delete_option( $lock );

		return array(
			'reserved'      => true,
			'id'            => $request_id,
			'amount'        => $amount,
			'balance_after' => $balance - $amount,
		);
	}

	/**
	 * Adjust a visitor reservation to actual provider billing.
	 *
	 * The reservation is consumed whether or not it settles cleanly, so a turn
	 * can never be settled twice. If the provider cost more than was held the
	 * difference is taken, up to what remains; if it cost less, the difference
	 * is given back.
	 *
	 * @param string $reservation_id The id returned by reserve_visitor_tokens().
	 * @param object $token_provider Provider used to price the turn.
	 * @param array  $billing_meta   Usage the provider reported.
	 * @return array{charged:bool,charge_tokens:int,balance_after:int}
	 *               charged is false when the reservation had already expired.
	 */
	public function settle_visitor_reservation( $reservation_id, $token_provider, $billing_meta = array() ) {
		$reservation_id = sanitize_key( (string) $reservation_id );
		$reservation    = get_transient( 'flosc_vtok_res_' . $reservation_id );
		delete_transient( 'flosc_vtok_res_' . $reservation_id );
		if ( ! is_array( $reservation ) || ! $token_provider ) {
			return array(
				'charged'       => false,
				'charge_tokens' => 0,
				'balance_after' => 0,
			);
		}

		$actual     = max(
			0,
			intval(
				$this->flosc->flosc_resolve_chat_charge_tokens(
					$reservation['flow_id'],
					$token_provider,
					is_array( $billing_meta ) ? $billing_meta : array()
				)
			)
		);
		$prepaid    = intval( $reservation['amount'] ?? 0 );
		$session_id = absint( $reservation['session_id'] ?? 0 );
		$flow_id    = (string) ( $reservation['flow_id'] ?? '' );
		$current    = $this->flosc_get_visitor_session_token_balance( $flow_id, $session_id, $token_provider );

		if ( $actual > $prepaid ) {
			$extra   = min( $actual - $prepaid, $current );
			$current = $this->flosc_set_visitor_session_token_balance( $flow_id, $session_id, $current - $extra );
		} elseif ( $actual < $prepaid ) {
			$refund  = $prepaid - $actual;
			$current = $this->flosc_set_visitor_session_token_balance( $flow_id, $session_id, $current + $refund );
		}

		return array(
			'charged'       => true,
			'charge_tokens' => $actual,
			'balance_after' => $current,
		);
	}

	/**
	 * Render a token count for a profile-bar label.
	 *
	 * Up to 9999 the exact number is shown, because at that size the person is
	 * watching it go down and wants the real figure. At 10000 and above it is
	 * truncated, never rounded up, to k, m or b — so a label never claims more
	 * tokens than the wallet holds.
	 *
	 * @param int $value Token count. Negative values read as zero.
	 * @return string The label, for example 5000, 12k or 3m.
	 */
	public function flosc_format_token_display( $value ) {
		$value = max( 0, intval( $value ) );
		if ( $value <= 9999 ) {
			return (string) $value;
		}

		$units = array(
			array(
				'suffix' => 'b',
				'size'   => 1000000000,
			),
			array(
				'suffix' => 'm',
				'size'   => 1000000,
			),
			array(
				'suffix' => 'k',
				'size'   => 1000,
			),
		);

		foreach ( $units as $unit ) {
			if ( $value >= $unit['size'] ) {
				return (string) floor( $value / $unit['size'] ) . $unit['suffix'];
			}
		}

		return (string) $value;
	}

	/**
	 * V→G additive token grant for the logged-in guest.
	 * Client sends visitor_session_id so visitor remaining can be carried after SSO
	 * (when the grant on wp_login ran without the flow-domain visitor cookie).
	 *
	 * @param WP_REST_Request $request flow_id, visitor_session_id.
	 * @return WP_REST_Response
	 */
	public function handle_apply_guest_token_grant( $request ) {
		nocache_headers();

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Not logged in',
				),
				401
			);
		}

		$flow_id = $this->flosc->flosc_normalize_flow_stem( (string) ( $request->get_param( 'flow_id' ) ?? '' ) );
		if ( '' === $flow_id || 'default' === $flow_id ) {
			$flow_id = $this->flosc->flosc_normalize_flow_stem(
				(string) get_user_meta( $user_id, '_flosc_registration_flow', true )
			);
		}

		if ( ! $this->flosc_user_should_receive_guest_tokens( $user_id, $flow_id ) ) {
			// Admins / members of this flow: return current flow balance (no V→G).
			$balance = $this->flosc->flosc_get_user_flow_token_balance( $user_id, $flow_id );
			if ( $balance <= 0 && $this->flosc->sale()->access()->is_member( $user_id, $flow_id ) ) {
				$balance = $this->flosc->flosc_apply_member_token_grant_once( $user_id, $flow_id );
			}
			return new WP_REST_Response(
				array(
					'success'       => true,
					'skipped'       => true,
					'reason'        => 'not_guest',
					'flow_id'       => $flow_id,
					'token_balance' => $balance,
					'formatted'     => $this->flosc_format_token_display( $balance ),
				)
			);
		}

		$session_raw = sanitize_text_field( (string) ( $request->get_param( 'visitor_session_id' ) ?? '' ) );
		if ( '' === $session_raw ) {
			$session_raw = $this->flosc->flosc_resolve_visitor_session_id_for_grant();
		}

		$flag_key         = $this->flosc->flosc_guest_token_grant_flag_key( $flow_id );
		$already          = (bool) get_user_meta( $user_id, $flag_key, true );
		$remaining_before = '' !== $session_raw
			? $this->flosc->flosc_get_visitor_remaining_for_session( $flow_id, $session_raw )
			: 0;
		$grant_amount     = max( 0, intval( $this->flosc->flosc_get_guest_token_grant_amount( $flow_id, $user_id ) ) );

		// Client always sends visitor_session_id when available; allow 0 remaining + grant
		// even if session id is missing (first load without localStorage).
		$balance = $this->flosc->flosc_apply_guest_token_grant_once( $user_id, $flow_id, $session_raw, true );

		return new WP_REST_Response(
			array(
				'success'           => true,
				'token_balance'     => $balance,
				'formatted'         => $this->flosc_format_token_display( $balance ),
				'flow_id'           => $flow_id,
				'applied_new'       => ! $already,
				'visitor_remaining' => $remaining_before,
				'grant'             => $grant_amount,
				'had_session'       => ( '' !== $session_raw ),
			)
		);
	}

	/**
	 * Resolve a visitor's token count at page load.
	 *
	 * The header needs the real balance before the first chat turn or admin
	 * poll writes it; without this the label sits at its pending placeholder.
	 * This is a read-only mirror of the visitor branch of the chat response and
	 * the admin poll payload: the server transient stays the sole owner, and the
	 * client only renders the returned token_balance shape. Unlike the admin
	 * message poll, no chat-log ownership row is required — a visitor may ask for
	 * their own count on any surface. Route registered in FLOSC_REST_Trait with
	 * check_public_endpoint_permission (rate-limited, public).
	 *
	 * @since 8.0.0
	 *
	 * @param WP_REST_Request $request Expects session_id and flow_id.
	 * @return WP_REST_Response { success, token_balance|null }
	 */
	public function handle_visitor_session_balance( $request ) {
		nocache_headers(); // Belt-and-suspenders against any caching layer.

		$session_id = $this->flosc->flosc_normalize_session_id( (string) ( $request->get_param( 'session_id' ) ?? '' ) );
		$flow_id    = $this->flosc->flosc_normalize_flow_stem( (string) ( $request->get_param( 'flow_id' ) ?? '' ) );

		if ( $session_id <= 0 || '' === $flow_id ) {
			return new WP_REST_Response(
				array(
					'success'       => false,
					'token_balance' => null,
				)
			);
		}

		$token_provider = $this->flosc->sale()->get_provider( 'tokens' );
		if ( ! $token_provider ) {
			return new WP_REST_Response(
				array(
					'success'       => false,
					'token_balance' => null,
				)
			);
		}

		$value = intval( $this->flosc_get_visitor_session_token_balance( $flow_id, $session_id, $token_provider ) );

		// Same low-balance resolution the chat response uses, so the header can
		// show the low-tokens nudge consistently across surfaces.
		$low_token_threshold = $this->flosc->flosc_get_low_token_threshold( $flow_id );
		$low_tokens_message  = $this->flosc->flosc_get_visitor_low_tokens_message( $flow_id );
		$is_low_balance      = ( $low_token_threshold > 0 && $value <= $low_token_threshold );

		return new WP_REST_Response(
			array(
				'success'       => true,
				'token_balance' => array(
					'scope'         => 'visitor_session',
					'value'         => $value,
					'formatted'     => $this->flosc_format_token_display( $value ),
					'low_threshold' => $low_token_threshold,
					'is_low'        => $is_low_balance,
					'low_message'   => $is_low_balance ? $low_tokens_message : '',
				),
			)
		);
	}
}
