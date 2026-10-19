<?php
/**
 * Exercise production Issue 10 handlers with scalar and array-shaped input.
 *
 * WordPress storage, mail, and redirect functions are small test doubles. The
 * handler code itself is loaded from the shipping files and invoked directly.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['flosc_test_meta_writes']      = array();
$GLOBALS['flosc_test_transient_writes'] = array();
$GLOBALS['flosc_test_mail_writes']      = array();
$GLOBALS['flosc_test_failures']         = array();

class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
}

class WP_REST_Response {
	public $data;
	public $status;

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

class FLOSC_Issue10_Request {
	private $params;

	public function __construct( array $params ) {
		$this->params = $params;
	}

	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}
}

function current_user_can() {
	return true;
}

function wp_unslash( $value ) {
	return $value;
}

function wp_verify_nonce() {
	return true;
}

function sanitize_text_field( $value ) {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function sanitize_textarea_field( $value ) {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( sanitize_text_field( $value ) ) );
}

function sanitize_file_name( $value ) {
	return basename( sanitize_text_field( $value ) );
}

function sanitize_email( $value ) {
	return filter_var( sanitize_text_field( $value ), FILTER_SANITIZE_EMAIL );
}

function sanitize_hex_color( $value ) {
	return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? (string) $value : '';
}

function esc_url_raw( $value ) {
	return sanitize_text_field( $value );
}

function absint( $value ) {
	return abs( (int) $value );
}

function is_email( $value ) {
	return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['flosc_test_meta_writes'][] = array( $post_id, $key, $value );
	return true;
}

function get_option( $key, $default = false ) {
	if ( 'admin_email' === $key ) {
		return 'operator@example.test';
	}
	if ( 'flosc_flow_demo' === $key ) {
		return array(
			'contact_form_forward_to_email'         => 'operator@example.test',
			'contact_form_max_submissions_per_hour' => 3,
			'contact_form_duplicate_window_minutes' => 30,
		);
	}
	return $default;
}

function get_transient() {
	return false;
}

function set_transient( $key, $value, $expiration ) {
	$GLOBALS['flosc_test_transient_writes'][] = array( $key, $value, $expiration );
	return true;
}

function wp_mail( $recipient, $subject, $body, $headers ) {
	$GLOBALS['flosc_test_mail_writes'][] = array( $recipient, $subject, $body, $headers );
	return true;
}

function add_action() {}

function add_filter() {}

function __( $text ) {
	return $text;
}

function flosc_issue10_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['flosc_test_failures'][] = $message;
	}
}

require dirname( __DIR__ ) . '/includes/flosc-accessors.php';
require dirname( __DIR__ ) . '/includes/class-flosc-trajectory.php';
require dirname( __DIR__ ) . '/includes/class-flosc-concierge.php';
require dirname( __DIR__ ) . '/includes/flosc-admin.php';
require dirname( __DIR__ ) . '/admin/ivr-upload-handler.php';
require dirname( __DIR__ ) . '/includes/chat-turn/trait-flosc-chat-turn.php';
require dirname( __DIR__ ) . '/includes/tokens/class-flosc-token-ledger.php';
require dirname( __DIR__ ) . '/includes/sale/class-flosc-checkout-rest.php';
require dirname( __DIR__ ) . '/includes/sessions/class-flosc-session-rest.php';

$_POST = array(
	'flosc_trajectory_nonce' => 'valid',
	'flosc_trj_flow'         => 'demo',
	'flosc_trj_priority'     => array( '99' ),
);
FLOSC_Trajectory::save_meta_box( 11 );
flosc_issue10_assert( array() === $GLOBALS['flosc_test_meta_writes'], 'Trajectory array input reached update_post_meta().' );

$_POST = array(
	'flosc_trajectory_nonce' => 'valid',
	'flosc_trj_flow'         => 'demo',
	'flosc_trj_priority'     => '99',
);
FLOSC_Trajectory::save_meta_box( 11 );
flosc_issue10_assert( 2 === count( $GLOBALS['flosc_test_meta_writes'] ), 'Valid trajectory text no longer saves.' );

$GLOBALS['flosc_test_meta_writes'] = array();
$_POST = array(
	'flosc_concierge_nonce'   => 'valid',
	'flosc_cncrg_keyword'     => 'hello',
	'flosc_cncrg_max_tries'   => array( '3' ),
);
FLOSC_Concierge::save_meta_box( 12 );
flosc_issue10_assert( array() === $GLOBALS['flosc_test_meta_writes'], 'Concierge array input reached update_post_meta().' );

$_POST = array(
	'flosc_concierge_nonce' => 'valid',
	'flosc_cncrg_keyword'   => 'hello',
	'flosc_cncrg_max_tries' => '3',
);
FLOSC_Concierge::save_meta_box( 12 );
flosc_issue10_assert( 2 === count( $GLOBALS['flosc_test_meta_writes'] ), 'Valid concierge text no longer saves.' );

class FLOSC_Issue10_Admin_Harness {
	use FLOSC_Admin_Trait;
}

$admin = new FLOSC_Issue10_Admin_Harness();
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$bad_contact = $admin->process_contact_form_submission(
	array(
		'first_name'  => array( 'Ada' ),
		'last_name'   => 'Lovelace',
		'email'       => 'ada@example.test',
		'phone'       => '123',
		'message'     => 'Hello',
		'honeypot'    => '',
		'rendered_at' => '1',
	),
	'demo'
);
flosc_issue10_assert( empty( $bad_contact['success'] ), 'Contact array input was accepted.' );
flosc_issue10_assert( array() === $GLOBALS['flosc_test_transient_writes'], 'Bad contact input wrote a transient.' );
flosc_issue10_assert( array() === $GLOBALS['flosc_test_mail_writes'], 'Bad contact input sent mail.' );

$good_contact = $admin->process_contact_form_submission(
	array(
		'first_name'  => 'Ada',
		'last_name'   => 'Lovelace',
		'email'       => 'ada@example.test',
		'phone'       => '123',
		'message'     => 'Hello',
		'honeypot'    => '',
		'rendered_at' => '1',
	),
	'demo'
);
flosc_issue10_assert( ! empty( $good_contact['success'] ), 'Valid contact text no longer sends.' );
flosc_issue10_assert( 1 === count( $GLOBALS['flosc_test_mail_writes'] ), 'Valid contact text did not reach wp_mail().' );

$GLOBALS['flosc_test_transient_writes'] = array();
$_POST = array(
	'flosc_portability_pack_action' => 'remove_wxr',
	'flosc_working_ivr'             => 'demo_ivr.md',
	'flosc_pack_filename'           => array( 'pack.xml' ),
);
flosc_admin_handle_portability_pack_actions();
flosc_issue10_assert( array() === $GLOBALS['flosc_test_transient_writes'], 'Bad pack action wrote a transient.' );

$_POST = array(
	'flosc_portability_submit' => 'apply',
	'flosc_working_ivr'        => array( 'demo_ivr.md' ),
);
$_GET = array();
flosc_admin_handle_ivr_file_upload();
flosc_issue10_assert( empty( $GLOBALS['flosc_ivr_upload_handled'] ), 'Bad upload request entered the mutation path.' );

class FLOSC_Issue10_Chat_Harness {
	use FLOSC_Chat_Turn_Trait;
}

$bad_chat = ( new FLOSC_Issue10_Chat_Harness() )->handle_chat(
	new FLOSC_Issue10_Request(
		array(
			'message'    => 'Hello',
			'session_id' => array( '42' ),
		)
	)
);
flosc_issue10_assert( $bad_chat instanceof WP_REST_Response && 400 === $bad_chat->status, 'Chat array input entered the turn.' );

$token_ledger = new FLOSC_Token_Ledger( new stdClass() );
$reservation = $token_ledger->reserve_visitor_tokens( 'demo', array( '42' ), '1', 'turn-1', new stdClass() );
flosc_issue10_assert( empty( $reservation['reserved'] ), 'Token reservation accepted an array session id.' );
$settlement = $token_ledger->settle_visitor_reservation( array( 'turn-1' ), new stdClass() );
flosc_issue10_assert( empty( $settlement['charged'] ), 'Token settlement accepted an array reservation id.' );

$checkout = new FLOSC_Checkout_Rest( new stdClass() );
$binding = $checkout->handle_checkout_binding(
	new FLOSC_Issue10_Request(
		array(
			'session_id' => array( '42' ),
			'flow_id'    => 'demo',
			'offer_id'   => 'offer',
			'provider'   => 'paypal',
		)
	)
);
flosc_issue10_assert( $binding instanceof WP_Error && 'invalid_request_shape' === $binding->code, 'Checkout binding accepted an array session id.' );

$sessions = new FLOSC_Session_Rest( new stdClass() );
$deleted = $sessions->delete_session(
	new FLOSC_Issue10_Request(
		array(
			'id'      => array( '1' ),
			'flow_id' => 'demo',
		)
	)
);
flosc_issue10_assert( $deleted instanceof WP_Error && 'invalid_request_shape' === $deleted->code, 'Session deletion accepted an array id.' );

if ( array() !== $GLOBALS['flosc_test_failures'] ) {
	fwrite( STDERR, implode( "\n", $GLOBALS['flosc_test_failures'] ) . "\n" );
	exit( 1 );
}

echo "issue10 actual handlers: ok\n";
