<?php
/**
 * A list posted into a one-value field must not replace the stored value.
 *
 * The decision is executed here. Reading the plugin text is not the check.
 * A string, an API key that contains %2F+/=.~, and personality profile text
 * that contains a heading mark and a blank line must come back unchanged.
 *
 * @package flosc
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	/**
	 * Stand-in for the WordPress UTF-8 check. These fixtures are already UTF-8.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_check_invalid_utf8( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * The personality file registers its admin hooks while it loads.
	 *
	 * @return void
	 */
	function add_action() {}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * The personality file registers its admin body class while it loads.
	 *
	 * @return void
	 */
	function add_filter() {}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stand-in.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

require dirname( __DIR__ ) . '/includes/flosc-accessors.php';
require dirname( __DIR__ ) . '/includes/ai/flosc-provider-keys.php';
require dirname( __DIR__ ) . '/includes/flosc-personality-library.php';

/**
 * The save rule: a list leaves the stored value in place. Text is kept as text.
 *
 * @param mixed $stored    Value already stored.
 * @param mixed $submitted Value copied from the request.
 * @return mixed
 */
function flosc_keep_stored_text( $stored, $submitted ) {
	if ( ! flosc_submitted_value_is_text( $submitted ) ) {
		return $stored;
	}
	return $submitted;
}

$flosc_failures = array();

/**
 * Record one failed check.
 *
 * @param bool   $ok      Result.
 * @param string $message What failed.
 * @return void
 */
function flosc_shape_check( $ok, $message ) {
	global $flosc_failures;
	if ( ! $ok ) {
		$flosc_failures[] = $message;
	}
}

$flosc_stored_name = 'Spring Offer';
flosc_shape_check(
	'Spring Offer' === flosc_keep_stored_text( $flosc_stored_name, array( 'posted-list' ) ),
	'A list replaced stored offer text.'
);
flosc_shape_check(
	'Spring Offer updated' === flosc_keep_stored_text( $flosc_stored_name, 'Spring Offer updated' ),
	'A string was not stored.'
);
flosc_shape_check(
	'Array' !== flosc_keep_stored_text( $flosc_stored_name, array( 'posted-list' ) ),
	'The stored value became the word Array.'
);

$flosc_api_key = 'sk-live_%2F+/=.~';
flosc_shape_check( flosc_submitted_value_is_text( $flosc_api_key ), 'An API key was not accepted as text.' );
flosc_shape_check(
	flosc_sanitize_api_secret( $flosc_api_key ) === $flosc_api_key,
	'API key characters were changed.'
);
flosc_shape_check(
	'' === flosc_sanitize_api_secret( array( 'posted-list' ) ),
	'A list was turned into secret text.'
);
flosc_shape_check(
	flosc_keep_stored_text( $flosc_api_key, array( 'posted-list' ) ) === $flosc_api_key,
	'A list replaced the stored API key.'
);

$flosc_profile = "# Heading\n\nA blank line and a # hash stay.";
flosc_shape_check(
	flosc_sanitize_personality_profile_text( $flosc_profile ) === $flosc_profile,
	'Personality profile text was changed.'
);
flosc_shape_check(
	flosc_keep_stored_text( $flosc_profile, array( 'posted-list' ) ) === $flosc_profile,
	'A list replaced the stored personality profile.'
);

$flosc_offer = array(
	'offer_name'          => 'Named',
	'offer_access_codes'  => '',
	'offer_coupon_code'   => array( 'SAVE10' ),
	'offer_coupon_type'   => array( 'fixed_price' ),
	'offer_coupon_active' => array( '1' ),
);
$flosc_offer_lists = array( 'offer_coupon_code', 'offer_coupon_type', 'offer_coupon_active' );
flosc_shape_check(
	flosc_submitted_request_is_text( $flosc_offer, $flosc_offer_lists ),
	'A normal offer was refused.'
);

$flosc_bad_code                 = $flosc_offer;
$flosc_bad_code['offer_access_codes'] = array( 'posted-list' );
flosc_shape_check(
	! flosc_submitted_request_is_text( $flosc_bad_code, $flosc_offer_lists ),
	'A list in the access-code field was accepted.'
);

$flosc_bad_coupon                   = $flosc_offer;
$flosc_bad_coupon['offer_coupon_code'] = 'SAVE10';
flosc_shape_check(
	! flosc_submitted_request_is_text( $flosc_bad_coupon, $flosc_offer_lists ),
	'A string was accepted where a coupon list is required.'
);

$flosc_bad_cell                      = $flosc_offer;
$flosc_bad_cell['offer_coupon_code'] = array( array( 'posted-list' ) );
flosc_shape_check(
	! flosc_submitted_request_is_text( $flosc_bad_cell, $flosc_offer_lists ),
	'A nested coupon cell was accepted.'
);

flosc_shape_check( flosc_submitted_list_is_text( array( '12', '34' ) ), 'A list of text ids was refused.' );
flosc_shape_check( ! flosc_submitted_list_is_text( '12' ), 'A string was treated as a list.' );
flosc_shape_check( flosc_submitted_value_is_text( '3' ), 'A numeric string was refused.' );
flosc_shape_check( flosc_submitted_value_is_text( 3 ), 'A number was refused.' );
flosc_shape_check( ! flosc_submitted_value_is_text( array( '3' ) ), 'A list was treated as one value.' );

/**
 * Same decision the meta-box, contact, and pack saves use.
 * A list is not cast. Text still goes through the field's number or text cleaner.
 *
 * @param mixed  $stored    Value already stored.
 * @param mixed  $submitted Value copied from the request.
 * @param string $cleaner   priority, max_tries, message, filename, or attachment.
 * @return mixed
 */
function flosc_keep_or_clean( $stored, $submitted, $cleaner ) {
	if ( ! flosc_submitted_value_is_text( $submitted ) ) {
		return $stored;
	}
	if ( 'priority' === $cleaner ) {
		return (string) max( 0, min( 100, abs( (int) $submitted ) ) );
	}
	if ( 'max_tries' === $cleaner ) {
		return (string) max( 1, abs( (int) $submitted ) );
	}
	if ( 'attachment' === $cleaner || 'rendered' === $cleaner ) {
		return (int) $submitted;
	}
	return (string) $submitted;
}

$flosc_cast_word = '';
set_error_handler(
	static function ( $errno ) {
		unset( $errno );
		return true;
	}
);
$flosc_cast_word = (string) array( 'posted-list' );
$flosc_cast_one  = (int) array( 'posted-list' );
restore_error_handler();
flosc_shape_check( 'Array' === $flosc_cast_word, 'This PHP did not cast a list to the word Array.' );
flosc_shape_check( 1 === $flosc_cast_one, 'This PHP did not cast a list to 1.' );

flosc_shape_check(
	'40' === flosc_keep_or_clean( '40', array( 'posted-list' ), 'priority' ),
	'A list priority became 1.'
);
flosc_shape_check(
	'7' === flosc_keep_or_clean( '40', '7', 'priority' ),
	'A typed priority was not kept.'
);
flosc_shape_check(
	'3' === flosc_keep_or_clean( '3', array( 'posted-list' ), 'max_tries' ),
	'A list max-tries became 1.'
);
flosc_shape_check(
	'4' === flosc_keep_or_clean( '3', '4', 'max_tries' ),
	'A typed max-tries was not kept.'
);
flosc_shape_check(
	'Please call me' === flosc_keep_or_clean( 'Please call me', array( 'posted-list' ), 'message' ),
	'A list contact message became the word Array.'
);
flosc_shape_check(
	'Please call tomorrow' === flosc_keep_or_clean( 'Please call me', 'Please call tomorrow', 'message' ),
	'A typed contact message was not kept.'
);
flosc_shape_check(
	'flow_ivr.md' === flosc_keep_or_clean( 'flow_ivr.md', array( 'posted-list' ), 'filename' ),
	'A list filename became Array.'
);
flosc_shape_check(
	'other_ivr.md' === flosc_keep_or_clean( 'flow_ivr.md', 'other_ivr.md', 'filename' ),
	'A typed filename was not kept.'
);
flosc_shape_check(
	15 === flosc_keep_or_clean( 15, array( 'posted-list' ), 'attachment' ),
	'A list attachment id became 1.'
);
flosc_shape_check(
	22 === flosc_keep_or_clean( 15, '22', 'attachment' ),
	'A typed attachment id was not kept.'
);

$flosc_contact = array(
	'first_name'  => 'Ada',
	'last_name'   => 'Lovelace',
	'email'       => 'ada@example.com',
	'phone'       => '555',
	'message'     => 'Please call me',
	'honeypot'    => '',
	'rendered_at' => '1700000000',
);
flosc_shape_check(
	flosc_submitted_fields_are_text( $flosc_contact, array_keys( $flosc_contact ) ),
	'A normal contact form was refused.'
);
$flosc_contact_list            = $flosc_contact;
$flosc_contact_list['message'] = array( 'posted-list' );
flosc_shape_check(
	! flosc_submitted_fields_are_text( $flosc_contact_list, array_keys( $flosc_contact_list ) ),
	'A list contact message was accepted.'
);
flosc_shape_check(
	1700000000 === flosc_keep_or_clean( 1700000000, array( 'posted-list' ), 'rendered' ),
	'A list rendered-at became 1.'
);
flosc_shape_check(
	1700000001 === flosc_keep_or_clean( 1700000000, '1700000001', 'rendered' ),
	'A typed rendered-at was not kept.'
);

/**
 * The handler boundary. A list in any present scalar field refuses the whole
 * save. No field is written. Text still applies the requested writes.
 *
 * @param array $stored Stored values.
 * @param array $posted Copied request.
 * @param array $keys   Scalar field names.
 * @param array $writes Values a valid request would store.
 * @param array $side   Mail, transient, import, remove, option, and file flags.
 * @return array
 */
function flosc_atomic_handler( array $stored, array $posted, array $keys, array $writes, array $side ) {
	if ( ! flosc_submitted_fields_are_text( $posted, $keys ) ) {
		return array(
			'stored' => $stored,
			'side'   => $side,
		);
	}
	foreach ( $writes as $flosc_key => $flosc_value ) {
		$stored[ $flosc_key ] = $flosc_value;
	}
	foreach ( $side as $flosc_flag => $flosc_on ) {
		unset( $flosc_on );
		$side[ $flosc_flag ] = true;
	}
	return array(
		'stored' => $stored,
		'side'   => $side,
	);
}

$flosc_trj_keys = array(
	'flosc_trajectory_nonce',
	'flosc_trj_flow',
	'flosc_trj_keywords',
	'flosc_trj_instructions',
	'flosc_trj_priority',
	'flosc_trj_off_ramp_phrases',
	'flosc_trj_off_ramp_exactness',
);
$flosc_trj_stored = array(
	'flow'               => 'dainis_net_ivr.md',
	'keywords'           => 'keep-keywords',
	'instructions'       => 'keep-instructions',
	'priority'           => '40',
	'off_ramp_phrases'   => 'keep-phrase',
	'off_ramp_exactness' => 'preferred',
);
$flosc_trj_valid = array(
	'flosc_trajectory_nonce'     => 'nonce',
	'flosc_trj_flow'             => 'other_ivr.md',
	'flosc_trj_keywords'         => 'new-keywords',
	'flosc_trj_instructions'     => 'new-instructions',
	'flosc_trj_priority'         => '7',
	'flosc_trj_off_ramp_phrases' => 'new-phrase',
	'flosc_trj_off_ramp_exactness' => 'exact',
);
$flosc_trj_writes = array(
	'flow'               => 'other_ivr.md',
	'keywords'           => 'new-keywords',
	'instructions'       => 'new-instructions',
	'priority'           => '7',
	'off_ramp_phrases'   => 'new-phrase',
	'off_ramp_exactness' => 'exact',
);
$flosc_quiet = array(
	'mail'      => false,
	'transient' => false,
	'import'    => false,
	'remove'    => false,
	'option'    => false,
	'file'      => false,
);
$flosc_trj_bad             = $flosc_trj_valid;
$flosc_trj_bad['flosc_trj_priority'] = array( 'posted-list' );
$flosc_trj_kept            = flosc_atomic_handler( $flosc_trj_stored, $flosc_trj_bad, $flosc_trj_keys, $flosc_trj_writes, $flosc_quiet );
flosc_shape_check( $flosc_trj_kept['stored'] === $flosc_trj_stored, 'A list priority changed trajectory metadata.' );
flosc_shape_check( '1' !== $flosc_trj_kept['stored']['priority'], 'A list priority became 1.' );
flosc_shape_check( '' !== $flosc_trj_kept['stored']['keywords'], 'A list priority emptied trajectory text.' );
$flosc_trj_saved = flosc_atomic_handler( $flosc_trj_stored, $flosc_trj_valid, $flosc_trj_keys, $flosc_trj_writes, $flosc_quiet );
flosc_shape_check( $flosc_trj_saved['stored'] === $flosc_trj_writes, 'A valid trajectory save was refused.' );

$flosc_cn_keys = array(
	'flosc_concierge_nonce',
	'flosc_cncrg_flow',
	'flosc_cncrg_keyword',
	'flosc_cncrg_password',
	'flosc_cncrg_success',
	'flosc_cncrg_max_tries',
	'flosc_cncrg_retry',
	'flosc_cncrg_delivery',
	'flosc_cncrg_off_ramp_phrases',
	'flosc_cncrg_off_ramp_exactness',
	'flosc_cncrg_start_utc_mts',
	'flosc_cncrg_end_utc_mts',
	'flosc_cncrg_expires_utc_mts',
	'flosc_cncrg_parameters',
	'flosc_cncrg_instruction_template',
);
$flosc_cn_stored = array(
	'flow'      => 'dainis_net_ivr.md',
	'keyword'   => 'keep-keyword',
	'password'  => 'keep-password',
	'success'   => 'keep-success',
	'max_tries' => '3',
	'retry'     => 'keep-retry',
	'delivery'  => 'keep-delivery',
	'phrases'   => 'keep-phrase',
	'exactness' => 'preferred',
	'start'     => '2026y-01m-01d',
	'end'       => '2026y-02m-01d',
	'expires'   => '2026y-03m-01d',
	'params'    => 'keep-params',
	'instruct'  => 'keep-instruct',
);
$flosc_cn_valid = array(
	'flosc_concierge_nonce'            => 'nonce',
	'flosc_cncrg_flow'                 => 'other_ivr.md',
	'flosc_cncrg_keyword'              => 'new-keyword',
	'flosc_cncrg_password'             => 'new-password',
	'flosc_cncrg_success'              => 'new-success',
	'flosc_cncrg_max_tries'            => '4',
	'flosc_cncrg_retry'                => 'new-retry',
	'flosc_cncrg_delivery'             => 'new-delivery',
	'flosc_cncrg_off_ramp_phrases'     => 'new-phrase',
	'flosc_cncrg_off_ramp_exactness'   => 'exact',
	'flosc_cncrg_start_utc_mts'        => '2026y-04m-01d',
	'flosc_cncrg_end_utc_mts'          => '2026y-05m-01d',
	'flosc_cncrg_expires_utc_mts'      => '2026y-06m-01d',
	'flosc_cncrg_parameters'           => 'new-params',
	'flosc_cncrg_instruction_template' => 'new-instruct',
);
$flosc_cn_writes = array(
	'flow'      => 'other_ivr.md',
	'keyword'   => 'new-keyword',
	'password'  => 'new-password',
	'success'   => 'new-success',
	'max_tries' => '4',
	'retry'     => 'new-retry',
	'delivery'  => 'new-delivery',
	'phrases'   => 'new-phrase',
	'exactness' => 'exact',
	'start'     => '2026y-04m-01d',
	'end'       => '2026y-05m-01d',
	'expires'   => '2026y-06m-01d',
	'params'    => 'new-params',
	'instruct'  => 'new-instruct',
);
$flosc_cn_bad                        = $flosc_cn_valid;
$flosc_cn_bad['flosc_cncrg_max_tries'] = array( 'posted-list' );
$flosc_cn_kept                       = flosc_atomic_handler( $flosc_cn_stored, $flosc_cn_bad, $flosc_cn_keys, $flosc_cn_writes, $flosc_quiet );
flosc_shape_check( $flosc_cn_kept['stored'] === $flosc_cn_stored, 'A list max-tries changed concierge metadata.' );
flosc_shape_check( '1' !== $flosc_cn_kept['stored']['max_tries'], 'A list max-tries became 1.' );
flosc_shape_check( '' !== $flosc_cn_kept['stored']['keyword'], 'A list max-tries emptied concierge text.' );
$flosc_cn_saved = flosc_atomic_handler( $flosc_cn_stored, $flosc_cn_valid, $flosc_cn_keys, $flosc_cn_writes, $flosc_quiet );
flosc_shape_check( $flosc_cn_saved['stored'] === $flosc_cn_writes, 'A valid concierge save was refused.' );

$flosc_contact_keys = array(
	'flosc_contact_return',
	'flosc_contact_nonce',
	'flosc_contact_flow',
	'flosc_contact_first_name',
	'flosc_contact_last_name',
	'flosc_contact_email',
	'flosc_contact_phone',
	'flosc_contact_message',
	'flosc_contact_company',
	'flosc_contact_rendered_at',
);
$flosc_contact_page = array(
	'flosc_contact_return'      => 'https://dainis.net/contact/',
	'flosc_contact_nonce'       => 'nonce',
	'flosc_contact_flow'        => 'dainis_net',
	'flosc_contact_first_name'  => 'Ada',
	'flosc_contact_last_name'   => 'Lovelace',
	'flosc_contact_email'       => 'ada@example.com',
	'flosc_contact_phone'       => '555',
	'flosc_contact_message'     => 'Please call me',
	'flosc_contact_company'     => '',
	'flosc_contact_rendered_at' => '1700000000',
);
$flosc_contact_bad              = $flosc_contact_page;
$flosc_contact_bad['flosc_contact_message'] = array( 'posted-list' );
$flosc_contact_side             = flosc_atomic_handler( array(), $flosc_contact_bad, $flosc_contact_keys, array( 'message' => 'Array' ), $flosc_quiet );
flosc_shape_check( false === $flosc_contact_side['side']['mail'], 'A list contact message sent mail.' );
flosc_shape_check( false === $flosc_contact_side['side']['transient'], 'A list contact message wrote a transient.' );
flosc_shape_check( array() === $flosc_contact_side['stored'], 'A list contact message was stored.' );
$flosc_contact_ok = flosc_atomic_handler( array(), $flosc_contact_page, $flosc_contact_keys, array( 'message' => 'Please call me' ), $flosc_quiet );
flosc_shape_check( true === $flosc_contact_ok['side']['mail'], 'A valid contact form sent no mail.' );
flosc_shape_check( 'Please call me' === $flosc_contact_ok['stored']['message'], 'A valid contact message was not kept.' );
$flosc_return_bad = $flosc_contact_page;
$flosc_return_bad['flosc_contact_return'] = array( 'posted-list' );
flosc_shape_check(
	! flosc_submitted_fields_are_text( $flosc_return_bad, array( 'flosc_contact_return' ) ),
	'A list return URL was accepted.'
);

$flosc_pack_keys = array(
	'flosc_portability_pack_action',
	'flosc_portability_submit',
	'flosc_upload_ivr_file',
	'flosc_working_ivr',
	'flosc_pack_filename',
	'flosc_pack_attachment_id',
	'flosc_upload_redirect_tab',
	'flosc_upload_redirect_view',
	'ivr',
);
$flosc_pack_valid = array(
	'flosc_portability_pack_action' => 'import_wxr',
	'flosc_portability_submit'      => 'apply',
	'flosc_upload_ivr_file'         => '1',
	'flosc_working_ivr'             => 'flow_ivr.md',
	'flosc_pack_filename'           => 'posts.xml',
	'flosc_pack_attachment_id'      => '22',
	'flosc_upload_redirect_tab'     => 'flow',
	'flosc_upload_redirect_view'    => 'all',
	'ivr'                           => 'flow_ivr.md',
);
$flosc_pack_bad = $flosc_pack_valid;
$flosc_pack_bad['flosc_pack_filename'] = array( 'posted-list' );
$flosc_pack_kept = flosc_atomic_handler( array( 'option' => 'unchanged' ), $flosc_pack_bad, $flosc_pack_keys, array( 'option' => 'Array' ), $flosc_quiet );
flosc_shape_check( false === $flosc_pack_kept['side']['import'], 'A list filename imported a pack.' );
flosc_shape_check( false === $flosc_pack_kept['side']['remove'], 'A list filename removed a pack file.' );
flosc_shape_check( false === $flosc_pack_kept['side']['file'], 'A list filename wrote a file.' );
flosc_shape_check( false === $flosc_pack_kept['side']['option'], 'A list filename changed an option.' );
flosc_shape_check( 'unchanged' === $flosc_pack_kept['stored']['option'], 'A list filename replaced a stored option.' );
$flosc_pack_ok = flosc_atomic_handler( array( 'option' => 'unchanged' ), $flosc_pack_valid, $flosc_pack_keys, array( 'option' => 'imported' ), $flosc_quiet );
flosc_shape_check( true === $flosc_pack_ok['side']['import'], 'A valid pack request was refused.' );
flosc_shape_check( 'imported' === $flosc_pack_ok['stored']['option'], 'A valid pack request did not record its result.' );
$flosc_files = array(
	'ivr_file_upload' => array(
		'name'     => 'flow_ivr.md',
		'tmp_name' => '/tmp/flow_ivr.md',
	),
);
flosc_shape_check( is_array( $flosc_files['ivr_file_upload'] ), 'A real uploaded file was treated as a scalar.' );
flosc_shape_check(
	flosc_submitted_fields_are_text( $flosc_pack_valid, $flosc_pack_keys ),
	'A pack request with a real file upload was refused for its text fields.'
);

if ( array() !== $flosc_failures ) {
	fwrite( STDERR, implode( "\n", $flosc_failures ) . "\n" );
	exit( 1 );
}

echo "submitted text shape: ok\n";
exit( 0 );
