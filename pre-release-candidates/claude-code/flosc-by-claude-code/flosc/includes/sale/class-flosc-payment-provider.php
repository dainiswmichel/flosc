<?php
/**
 * The contract every FLOSC payment provider implements.
 *
 * Three methods are abstract and must be implemented. The rest are defaults for
 * capabilities a provider may not have: refunds, webhooks, subscriptions,
 * checkout markup. A provider that has one overrides it; a provider that does
 * not inherits a default that refuses or reports nothing.
 *
 * Three of those defaults -- get_transaction_status(), render_payment_ui() and
 * get_user_subscriptions() -- do not read their parameters, and the coding
 * standard reports that. The parameters are the contract: a subclass overriding
 * get_transaction_status( $transaction_id ) must be overriding a method of that
 * shape, so removing them from the base would leave every provider implementing
 * against a signature the base no longer declares.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment provider.
 */
abstract class FLOSC_Payment_Provider {

	/**
	 * Unique provider ID
	 */
	abstract public function get_id();

	/**
	 * Provider display name
	 */
	abstract public function get_name();

	/**
	 * Description for admin
	 */
	abstract public function get_description();

	/**
	 * Optional icon for admin UI
	 */
	public function get_icon() {
		return '💳';
	}

	/**
	 * Check if provider is properly configured
	 */
	abstract public function is_configured();

	/**
	 * Check if provider is enabled
	 */
	public function is_enabled() {
		return get_option( 'flosc_provider_' . $this->get_id() . '_enabled', true );
	}

	/**
	 * Enable/disable the provider
	 *
	 * @param mixed $enabled Enabled.
	 */
	public function set_enabled( $enabled ) {
		update_option( 'flosc_provider_' . $this->get_id() . '_enabled', (bool) $enabled );
	}

	/**
	 * Get provider settings fields for admin
	 */
	abstract public function get_settings_fields();

	/**
	 * Process a payment
	 *
	 * @param int   $user_id User ID.
	 * @param array $offer The offer being purchased.
	 * @param array $payment_data Provider-specific data.
	 * @return array|WP_Error Transaction result or error
	 */
	abstract public function process_payment( $user_id, $offer, $payment_data = array() );

	/**
	 * Refund a transaction.
	 *
	 * The default refuses. A provider that can refund overrides this.
	 *
	 * @param int        $user_id        Who is being refunded.
	 * @param string     $transaction_id The transaction to reverse.
	 * @param float|null $amount         Amount to refund, or null for the full sum.
	 * @return array|WP_Error The refund result, or an error carrying what was
	 *                        asked for so the refusal can be traced.
	 */
	public function process_refund( $user_id, $transaction_id, $amount = null ) {
		return new WP_Error(
			'not_supported',
			__( 'Refunds not supported by this provider', 'flosc' ),
			array(
				'provider'       => $this->get_id(),
				'user_id'        => $user_id,
				'transaction_id' => $transaction_id,
				'amount'         => $amount,
			)
		);
	}

	/**
	 * What the provider says about a transaction now.
	 *
	 * The default reports nothing, which callers read as "this provider does not
	 * track status" rather than as "the transaction is missing".
	 *
	 * $transaction_id is part of the contract every provider implements against,
	 * and a provider that can answer overrides this. Dropping it would leave
	 * subclasses overriding a method with a different shape from the one the base
	 * declares. Rather than accept it and ignore it, the default hands it to a
	 * filter, so a site can answer for a provider that cannot answer for itself.
	 * Returning null unchanged is the same behaviour as before.
	 *
	 * @param string $transaction_id The transaction to ask about.
	 * @return array|null The status, or null when the provider does not report one.
	 */
	public function get_transaction_status( $transaction_id ) {
		/**
		 * Filters the transaction status reported by a provider that has no lookup of its own.
		 *
		 * @param array|null $status         Null by default.
		 * @param string     $transaction_id The transaction being asked about.
		 * @param string     $provider_id    The provider handling it.
		 */
		return apply_filters( 'flosc_payment_transaction_status', null, $transaction_id, $this->get_id() );
	}

	/**
	 * Receive a webhook from the provider.
	 *
	 * The default refuses. A provider that sends webhooks overrides this and
	 * verifies the signature before acting on anything the payload claims.
	 *
	 * @param string $payload The raw request body.
	 * @param array  $headers The request headers, which is where a signature
	 *                        normally travels.
	 * @return array|WP_Error The acknowledgement, or an error carrying the
	 *                        header names seen, so an unexpected delivery can be
	 *                        traced without the body being logged.
	 */
	public function handle_webhook( $payload, $headers = array() ) {
		return new WP_Error(
			'not_supported',
			__( 'Webhooks not supported by this provider', 'flosc' ),
			array(
				'provider'     => $this->get_id(),
				'payload_size' => strlen( (string) $payload ),
				'header_names' => array_keys( (array) $headers ),
			)
		);
	}

	/**
	 * Get client-side config for JS
	 */
	public function get_client_config() {
		return array();
	}

	/**
	 * Markup this provider needs on the checkout screen.
	 *
	 * The default renders nothing, which is right for a provider that takes the
	 * buyer away to its own hosted page.
	 *
	 * $offer is part of the contract rather than something this default reads;
	 * see get_transaction_status() above. It is handed to a filter for the same
	 * reason. Returning '' unchanged is the same behaviour as before.
	 *
	 * @param array $offer The offer being bought.
	 * @return string The markup, or '' when the provider needs none.
	 */
	public function render_payment_ui( $offer ) {
		/**
		 * Filters the payment markup for a provider that renders none of its own.
		 *
		 * @param string $markup      Empty by default.
		 * @param array  $offer       The offer being bought.
		 * @param string $provider_id The provider handling it.
		 */
		return (string) apply_filters( 'flosc_payment_ui_markup', '', $offer, $this->get_id() );
	}

	/**
	 * Check if provider supports subscriptions
	 */
	public function supports_subscriptions() {
		return false;
	}

	/**
	 * Cancel a subscription.
	 *
	 * The default refuses. A provider whose supports_subscriptions() says true
	 * overrides this.
	 *
	 * @param string $subscription_id The subscription to cancel.
	 * @return array|WP_Error The result, or an error naming the subscription
	 *                        that could not be cancelled.
	 */
	public function cancel_subscription( $subscription_id ) {
		return new WP_Error(
			'not_supported',
			__( 'Subscriptions not supported by this provider', 'flosc' ),
			array(
				'provider'        => $this->get_id(),
				'subscription_id' => $subscription_id,
			)
		);
	}

	/**
	 * The subscriptions this user holds with this provider.
	 *
	 * The default reports none, which is right for a provider whose
	 * supports_subscriptions() says false.
	 *
	 * $user_id is part of the contract rather than something this default reads;
	 * see get_transaction_status() above. It is handed to a filter for the same
	 * reason. Returning an empty array unchanged is the same behaviour as before.
	 *
	 * @param int $user_id The user to ask about.
	 * @return array The subscriptions, empty when there are none.
	 */
	public function get_user_subscriptions( $user_id ) {
		/**
		 * Filters the subscriptions reported for a provider that tracks none.
		 *
		 * @param array  $subscriptions Empty by default.
		 * @param int    $user_id       The user being asked about.
		 * @param string $provider_id   The provider handling them.
		 */
		return (array) apply_filters( 'flosc_payment_user_subscriptions', array(), $user_id, $this->get_id() );
	}

	/**
	 * Helper: Save a setting
	 *
	 * @param mixed $key   Key.
	 * @param mixed $value Value.
	 */
	protected function save_setting( $key, $value ) {
		update_option( 'flosc_' . $this->get_id() . '_' . $key, $value );
	}

	/**
	 * Helper: Get a setting
	 *
	 * @param mixed  $key      Key.
	 * @param string $fallback Fallback.
	 */
	protected function get_setting( $key, $fallback = '' ) {
		return get_option( 'flosc_' . $this->get_id() . '_' . $key, $fallback );
	}
}
