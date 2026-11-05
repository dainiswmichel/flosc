<?php
/**
 * Issue 10 request-boundary regression checks for v153.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
	/** Minimal WordPress error double. */
	class WP_Error {
		/** @var string */
		private $code;

		/**
		 * @param string $code Error code.
		 */
		public function __construct( $code ) {
			$this->code = (string) $code;
		}

		/** @return string */
		public function get_error_code() {
			return $this->code;
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/** @return string */
	function __( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/** @return string */
	function sanitize_key( $value ) {
		return strtolower( (string) preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/** @return bool */
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

$flosc_remote_requests = 0;

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * @param string $url  Request URL.
	 * @param array  $args Request arguments.
	 * @return array
	 */
	function wp_remote_get( $url, $args ) {
		global $flosc_remote_requests;
		++$flosc_remote_requests;
		return array(
			'url'  => (string) $url,
			'args' => $args,
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/** @return int */
	function wp_remote_retrieve_response_code() {
		return 200;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array $response Response double.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( false !== strpos( $response['url'], '/models/gpt-test' ) ) {
			return '{"capabilities":{}}';
		}
		return '{"data":[{"id":"gpt-test"}]}';
	}
}

if ( ! function_exists( 'flosc_provider_model_detail_url' ) ) {
	/** @return string */
	function flosc_provider_model_detail_url( $provider, $model ) {
		return 'https://api.example.test/' . sanitize_key( $provider ) . '/models/' . rawurlencode( (string) $model );
	}
}

require_once dirname( __DIR__ ) . '/includes/ai/flosc-provider-keys.php';
require_once dirname( __DIR__ ) . '/includes/ai/flosc-model-catalog.php';

$flosc_failures = 0;

/**
 * Record one assertion.
 *
 * @param string $label Assertion label.
 * @param bool   $pass  Whether it passed.
 * @return void
 */
function flosc_issue10_v153_assert( $label, $pass ) {
	global $flosc_failures;
	if ( ! $pass ) {
		++$flosc_failures;
	}
	fwrite( STDOUT, sprintf( "%s %s\n", $pass ? 'ok  ' : 'FAIL', $label ) );
}

$flosc_bad_key = "valid-looking-key\r\nInjected: yes";
$flosc_catalog = flosc_fetch_model_catalog( 'openai', $flosc_bad_key );
$flosc_details = flosc_fetch_model_details( 'openai', $flosc_bad_key, 'gpt-test' );

flosc_issue10_v153_assert(
	'catalog rejects a control-character key',
	is_wp_error( $flosc_catalog ) && 'flosc_key_control_chars' === $flosc_catalog->get_error_code()
);
flosc_issue10_v153_assert(
	'details rejects a control-character key',
	is_wp_error( $flosc_details ) && 'flosc_key_control_chars' === $flosc_details->get_error_code()
);
flosc_issue10_v153_assert( 'invalid keys cause no HTTP request', 0 === $flosc_remote_requests );

$flosc_catalog = flosc_fetch_model_catalog( 'openai', 'valid-key' );
$flosc_details = flosc_fetch_model_details( 'openai', 'valid-key', 'gpt-test' );

flosc_issue10_v153_assert(
	'valid catalog request still succeeds',
	is_array( $flosc_catalog ) && 'gpt-test' === $flosc_catalog['models'][0]['id']
);
flosc_issue10_v153_assert( 'valid model-details request still succeeds', is_array( $flosc_details ) );
flosc_issue10_v153_assert( 'valid requests still reach the provider', 2 === $flosc_remote_requests );

$flosc_settings  = (string) file_get_contents( dirname( __DIR__ ) . '/admin/settings.php' );
$flosc_framework = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-flosc-framework.php' );
$flosc_magic     = (string) file_get_contents( dirname( __DIR__ ) . '/includes/magic-link/class-flosc-magic-link-trait.php' );

flosc_issue10_v153_assert(
	'companion rule chunks are sanitized before storage',
	false !== strpos( $flosc_settings, '$chunk = sanitize_text_field( trim( (string) $chunk ) );' )
);
flosc_issue10_v153_assert(
	'all three KB return-IVR values require strings',
	3 === substr_count( $flosc_framework, "isset( \$post['flosc_return_ivr'] ) && is_string( \$post['flosc_return_ivr'] )" )
);
flosc_issue10_v153_assert(
	'all five framework IVR request values require strings',
	5 === substr_count( $flosc_framework, "isset( \$_POST['ivr'] ) && is_string( \$_POST['ivr'] ) ? sanitize_file_name" )
);
flosc_issue10_v153_assert(
	'all four account-management IVR values require strings',
	4 === substr_count( $flosc_magic, "isset( \$_POST['ivr'] ) && is_string( \$_POST['ivr'] )" )
);

if ( $flosc_failures > 0 ) {
	fwrite( STDERR, sprintf( "%d failing assertion(s)\n", $flosc_failures ) );
	exit( 1 );
}

fwrite( STDOUT, "check_issue10_v153_boundaries: ok\n" );
exit( 0 );
