<?php
/**
 * Both IVR message routes authorize the requested flow, not another flow.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'FLOSC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$flosc_test_current_user = 7;
$flosc_test_admins       = array( 9 => true );
$flosc_test_user_meta    = array(
	8 => array( '_flosc_member_access' => 'true' ),
);
$flosc_test_memberships  = array(
	7 => array( 'flow_a' => true ),
);

class WP_Error {
	private $code;
	private $data;

	public function __construct( $code, $message = '', $data = array() ) {
		$this->code = $code;
		$this->data = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WP_REST_Response {
	private $data;
	private $status;

	public function __construct( $data, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status() {
		return $this->status;
	}
}

class FLOSC_Test_Request {
	private $params;

	public function __construct( $params ) {
		$this->params = $params;
	}

	public function get_param( $name ) {
		return $this->params[ $name ] ?? null;
	}

	public function get_route() {
		return '/flosc/v1/ivr/messages';
	}
}

function __( $text, $domain = null ) {
	return $text;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function sanitize_key( $value ) {
	$value = strtolower( (string) $value );
	return preg_replace( '/[^a-z0-9_\-]/', '', $value );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_file_name( $value ) {
	return basename( preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) $value ) );
}

function get_current_user_id() {
	global $flosc_test_current_user;
	return $flosc_test_current_user;
}

function is_user_logged_in() {
	return get_current_user_id() > 0;
}

function user_can( $user_id, $capability ) {
	global $flosc_test_admins;
	return 'manage_options' === $capability && ! empty( $flosc_test_admins[ $user_id ] );
}

function current_user_can( $capability ) {
	return user_can( get_current_user_id(), $capability );
}

function get_user_meta( $user_id, $key = '', $single = false ) {
	global $flosc_test_user_meta;
	if ( '' === $key ) {
		return $flosc_test_user_meta[ $user_id ] ?? array();
	}
	return $flosc_test_user_meta[ $user_id ][ $key ] ?? '';
}

function update_user_meta( $user_id, $key, $value ) {
	global $flosc_test_user_meta;
	$flosc_test_user_meta[ $user_id ][ $key ] = $value;
	return true;
}

function get_user_by( $field, $user_id ) {
	return (object) array( 'roles' => array( 'subscriber' ) );
}

function get_userdata( $user_id ) {
	return (object) array( 'user_registered' => '' );
}

function wp_get_current_user() {
	return (object) array( 'display_name' => 'Test User' );
}

function current_time( $type ) {
	return '2026-09-10 00:00:00';
}

function get_option( $name, $default = false ) {
	if ( 'flosc_public_request_protection' === $name ) {
		return array( 'enabled' => '0' );
	}
	return $default;
}

class FLOSC_Test_Access {
	public function is_member( $user_id, $flow_id = null ) {
		global $flosc_test_admins, $flosc_test_memberships;
		if ( ! empty( $flosc_test_admins[ $user_id ] ) ) {
			return true;
		}
		$stem = sanitize_key( pathinfo( basename( (string) $flow_id ), PATHINFO_FILENAME ) );
		return ! empty( $flosc_test_memberships[ $user_id ][ $stem ] );
	}

	public function get_simple_state( $user_id, $flow_id = null ) {
		if ( ! $user_id ) {
			return 'visitor';
		}
		return $this->is_member( $user_id, $flow_id ) ? 'member' : 'guest';
	}
}

class FLOSC_Test_Sale {
	private $access;

	public function __construct( $access ) {
		$this->access = $access;
	}

	public function access() {
		return $this->access;
	}
}

class FLOSC_Test_Framework {
	private $sale;

	public function __construct( $access ) {
		$this->sale = new FLOSC_Test_Sale( $access );
	}

	public function sale() {
		return $this->sale;
	}

	public function get_current_flow() {
		return array(
			'id'       => 'flow_a',
			'ivr_file' => 'flow_a.md',
		);
	}

	public function determine_flosc_phase() {
		return 'content';
	}
}

$flosc_test_access    = new FLOSC_Test_Access();
$flosc_test_framework = new FLOSC_Test_Framework( $flosc_test_access );

function flosc() {
	global $flosc_test_framework;
	return $flosc_test_framework;
}

function flosc_resolve_flow_runtime( $flow_id = '', $ivr_file = '' ) {
	$stem = sanitize_key( pathinfo( basename( (string) $flow_id ), PATHINFO_FILENAME ) );
	return array(
		'messages' => array(
			'sale'    => array(
				'name'       => 'sale',
				'type'       => 'auto',
				'content'    => 'SALE_' . strtoupper( $stem ),
				'conditions' => 'always',
			),
			'private' => array(
				'name'       => 'private',
				'type'       => 'auto',
				'content'    => 'PRIVATE_' . strtoupper( $stem ),
				'conditions' => 'always',
			),
		),
		'phases'   => array(
			'sale'    => array( 'sale' ),
			'content' => array( 'private' ),
		),
		'source'   => 'test',
	);
}

function flosc_flow_phase_messages( $config, $phase ) {
	$out = array();
	foreach ( $config['phases'][ $phase ] ?? array() as $message_id ) {
		if ( isset( $config['messages'][ $message_id ] ) ) {
			$out[] = $config['messages'][ $message_id ];
		}
	}
	return $out;
}

require dirname( __DIR__ ) . '/includes/class-user-access-manager.php';
require dirname( __DIR__ ) . '/includes/class-condition-evaluator.php';
require dirname( __DIR__ ) . '/includes/flosc-rest.php';

function flosc_test_cut_method( $source, $signature ) {
	$start = strpos( $source, $signature );
	if ( false === $start ) {
		fwrite( STDERR, "FAIL could not find {$signature}\n" );
		exit( 1 );
	}
	$depth = 0;
	$open  = strpos( $source, '{', $start );
	for ( $index = $open; $index < strlen( $source ); $index++ ) {
		if ( '{' === $source[ $index ] ) {
			$depth++;
		} elseif ( '}' === $source[ $index ] ) {
			$depth--;
			if ( 0 === $depth ) {
				return substr( $source, $start, $index - $start + 1 );
			}
		}
	}
	fwrite( STDERR, "FAIL unbalanced method {$signature}\n" );
	exit( 1 );
}

$flosc_source  = (string) file_get_contents( dirname( __DIR__ ) . '/flosc.php' );
$flosc_methods = flosc_test_cut_method( $flosc_source, 'public function flosc_request_flow_stem' ) . "\n";
$flosc_methods .= flosc_test_cut_method( $flosc_source, 'public function get_ivr_messages' ) . "\n";
$flosc_methods .= flosc_test_cut_method( $flosc_source, 'public function handle_ivr_get_messages' );

eval(
	'class FLOSC_IVR_Under_Test {'
	. ' use FLOSC_REST_Trait;'
	. ' public $member_access; public $user_access_manager; public $session_manager;'
	. ' public function __construct($access) {'
	. ' $this->member_access = $access;'
	. ' $this->user_access_manager = FLOSC_User_Access_Manager::instance();'
	. ' $this->session_manager = new class {'
	. ' public function normalize_flow_stem($raw) {'
	. ' $stem = sanitize_key(pathinfo(basename((string) $raw), PATHINFO_FILENAME));'
	. ' return $stem !== "" ? $stem : "default";'
	. ' } }; }'
	. ' public function get_current_flow() { return flosc()->get_current_flow(); }'
	. ' private function substitute_ivr_variables($content, $context) { return $content; }'
	. $flosc_methods
	. '}'
);

$flosc_test_fail = 0;
function flosc_test_ok( $label, $actual, $expected ) {
	global $flosc_test_fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		$flosc_test_fail++;
	}
	printf(
		"%s %-64s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

function flosc_test_result_code( $result ) {
	return is_wp_error( $result ) ? $result->get_error_code() : $result;
}

function flosc_test_response_contains( $response, $needle ) {
	if ( ! $response instanceof WP_REST_Response ) {
		return false;
	}
	return false !== strpos( json_encode( $response->get_data() ), $needle );
}

$ivr = new FLOSC_IVR_Under_Test( $flosc_test_access );

echo "Membership in flow A grants no content access in flow B\n";
$flow_b = new FLOSC_Test_Request(
	array(
		'phase'   => 'content',
		'flow_id' => 'flow_b',
		'type'    => 'auto',
	)
);
flosc_test_ok( 'permission callback denies the other flow', flosc_test_result_code( $ivr->check_ivr_messages_permission( $flow_b ) ), 'forbidden' );
flosc_test_ok( 'primary handler denies the other flow', flosc_test_result_code( $ivr->get_ivr_messages( $flow_b ) ), 'flosc_content_phase_forbidden' );
flosc_test_ok( 'compatibility handler denies the other flow', flosc_test_result_code( $ivr->handle_ivr_get_messages( $flow_b ) ), 'flosc_content_phase_forbidden' );

echo "The owned flow remains available\n";
$flow_a = new FLOSC_Test_Request(
	array(
		'phase'   => 'content',
		'flow_id' => 'flow_a',
		'type'    => 'auto',
	)
);
flosc_test_ok( 'permission callback allows the owned flow', $ivr->check_ivr_messages_permission( $flow_a ), true );
flosc_test_ok( 'primary handler returns owned content', flosc_test_response_contains( $ivr->get_ivr_messages( $flow_a ), 'PRIVATE_FLOW_A' ), true );
flosc_test_ok( 'compatibility handler returns owned content', flosc_test_response_contains( $ivr->handle_ivr_get_messages( $flow_a ), 'PRIVATE_FLOW_A' ), true );

echo "Global or revoked membership cannot authorize a requested flow\n";
$flosc_test_current_user = 8;
flosc_test_ok( 'legacy global member flag does not grant flow B', flosc_test_result_code( $ivr->check_ivr_messages_permission( $flow_b ) ), 'forbidden' );
$flosc_test_current_user = 0;
flosc_test_ok( 'anonymous content stays denied', flosc_test_result_code( $ivr->check_ivr_messages_permission( $flow_a ) ), 'forbidden' );
$public_sale = new FLOSC_Test_Request(
	array(
		'phase'   => 'sale',
		'flow_id' => 'flow_b',
		'type'    => 'auto',
	)
);
flosc_test_ok( 'the public sale phase remains available', $ivr->check_ivr_messages_permission( $public_sale ), true );
flosc_test_ok( 'public sale does not merge content', flosc_test_response_contains( $ivr->get_ivr_messages( $public_sale ), 'PRIVATE_FLOW_B' ), false );

echo "Administrators retain all-flow testing access\n";
$flosc_test_current_user = 9;
flosc_test_ok( 'administrator permission is allowed', $ivr->check_ivr_messages_permission( $flow_b ), true );
flosc_test_ok( 'administrator can inspect flow B content', flosc_test_response_contains( $ivr->handle_ivr_get_messages( $flow_b ), 'PRIVATE_FLOW_B' ), true );

echo $flosc_test_fail ? "\n{$flosc_test_fail} FAILURES\n" : "\nIVR flow entitlement: all checks passed\n";
exit( $flosc_test_fail ? 1 : 0 );
