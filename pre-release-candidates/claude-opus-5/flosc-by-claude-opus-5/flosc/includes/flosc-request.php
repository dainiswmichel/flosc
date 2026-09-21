<?php
/**
 * Input boundary for read-only navigation parameters.
 *
 * FLOSC reads a handful of GET parameters to decide what to *show*: which
 * settings tab, which IVR file, which view, which list filter. They are the
 * admin equivalent of a page number. None of them reaches a mutation, and a
 * nonce on them would break bookmarks and the back button while protecting
 * nothing.
 *
 * WHY THIS READS $_GET DIRECTLY
 *
 * An earlier version read through filter_input( ..., FILTER_UNSAFE_RAW ) and
 * this docblock claimed that was not about quietening a sniff. That claim was
 * false. WPCS's NonceVerification sniff detects reads of the $_GET and $_POST
 * variables; it does not model filter_input(), so routing thirty reads through
 * a function call made them invisible to it. The warnings went to zero because
 * the scanner could no longer see the read, not because a control was added.
 *
 * FLOSC's own gate caught it. tests/check_wporg_rules.php WPORG-04, written
 * from the 13 Sep 2026 rejection, reports FILTER_UNSAFE_RAW as not sanitizing,
 * which is the same objection WordPress.org made.
 *
 * So the read is a plain superglobal read again, unslashed and sanitized where
 * it happens. What this function legitimately provides is centralization: the
 * read occurs HERE, once, instead of at thirty call sites, so a reviewer checks
 * one function. NonceVerification will report this function, and that report is
 * true -- it reads GET without a nonce, deliberately, for the reasons above.
 * A warning that is true is not a defect to be hidden.
 *
 * Anything that returns from here has either matched a closed set of expected
 * values or passed a named WordPress sanitizer, and is a string.
 *
 * WHAT THIS MUST NEVER BE USED FOR
 *
 * Anything that writes. If a value read through here reaches update_option, a
 * delete, a file write, a redirect target or any other state change, this is
 * the wrong function: that caller needs check_admin_referer() or
 * check_ajax_referer() plus a capability check, as two separate refusals,
 * before it touches the request body. There is deliberately no variant of this
 * helper that covers that case.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read one GET parameter that selects what to display.
 *
 * Values are constrained on the way out, not merely sanitized. Several of the
 * call sites this replaced accepted any string the URL carried and then used it
 * to pick a template or a script handle; passing $allowed turns that into a
 * closed set, which is a real narrowing rather than tidier code.
 *
 * @param string   $key       Query parameter name.
 * @param string[] $allowed   Closed set of acceptable values. Empty means the
 *                            value cannot be enumerated in advance -- a
 *                            filename, for instance -- and $sanitizer decides.
 * @param string   $fallback   Returned when the parameter is absent, is not a
 *                            string, or is not in $allowed.
 * @param string   $sanitizer Named WordPress sanitizer applied when $allowed is
 *                            empty. Defaults to sanitize_key. Use
 *                            sanitize_file_name for filenames.
 * @return string
 */
function flosc_nav_param( $key, array $allowed = array(), $fallback = '', $sanitizer = 'sanitize_key' ) {
	if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
		return $fallback;
	}

	$value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );

	if ( array() !== $allowed ) {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	if ( is_string( $sanitizer ) && function_exists( $sanitizer ) ) {
		$value = (string) call_user_func( $sanitizer, $value );
	}

	return ( '' === $value ) ? $fallback : $value;
}

/**
 * Read one GET parameter that selects a display position, as an integer.
 *
 * @param string $key     Query parameter name.
 * @param int    $min     Lowest acceptable value.
 * @param int    $max     Highest acceptable value.
 * @param int    $fallback Returned when absent or outside the range.
 * @return int
 */
function flosc_nav_param_int( $key, $min = 0, $max = PHP_INT_MAX, $fallback = 0 ) {
	$value = filter_input(
		INPUT_GET,
		$key,
		FILTER_VALIDATE_INT,
		array(
			'options' => array(
				'min_range' => (int) $min,
				'max_range' => (int) $max,
			),
		)
	);

	return ( null === $value || false === $value ) ? (int) $fallback : (int) $value;
}

/**
 * True when the named GET parameter is present at all.
 *
 * Some call sites branch on presence rather than value -- "was ?saved passed",
 * not "what does ?saved say".
 *
 * @param string $key Query parameter name.
 * @return bool
 */
function flosc_nav_param_present( $key ) {
	return isset( $_GET[ $key ] );
}

/**
 * The query parameters this plugin's admin screens are allowed to read.
 *
 * Declared here rather than at each screen so the set is reviewable in one
 * place. Every name is one that some admin template reads from $flosc_get;
 * anything arriving in the URL that is not on this list is not read at all.
 *
 * @return string[] Query parameter names, in alphabetical order.
 */
function flosc_nav_param_keys() {
	return array(
		'_wpnonce',
		'concierge_created',
		'concierge_error',
		'default_set',
		'delete_flow',
		'delete_message',
		'delete_offer',
		'doc',
		'edit_message',
		'edit_offer',
		'expand',
		'flosc_download_ivr',
		'flosc_guest_request_notice',
		'flosc_ivr_uploaded',
		'flosc_portability_done',
		'flosc_user_id',
		'ivr',
		'ivr_phase',
		'logview',
		'phase',
		'saved',
		'session_scope',
		'set_status',
		'status',
		'tab',
		'toggle_status',
		'trajectory_created',
		'trajectory_error',
		'trajectory_toggled',
		'view',
	);
}

/**
 * Read the declared navigation parameters into an array.
 *
 * This replaces `$flosc_get = wp_unslash( $_GET );` at the top of the admin
 * screens. WordPress.org's reviewer raised that pattern twice -- T7 and T13,
 * "don't check for post submission outside of functions" -- and WPCS never
 * reported it, because a nonce check further down the same file satisfies the
 * sniff for the whole file scope. The sniff measures scope; the reviewer reads
 * execution order. This reads a closed set instead of whatever the URL carried.
 *
 * Absent keys are left out rather than set empty, because the callers test with
 * isset() and filling the array would make every one of those tests true.
 *
 * Values get sanitize_text_field(), which strips tags and control bytes without
 * altering the value otherwise. It deliberately is not sanitize_key(): 'ivr'
 * and 'flosc_download_ivr' carry IVR filenames such as dainis_net_ivr.md, and
 * sanitize_key() would silently drop the extension. The screens that use those
 * two as paths apply sanitize_file_name() themselves, at the point of use, and
 * this function does not take that job away from them.
 *
 * Like flosc_nav_param(), this is for deciding what to SHOW. Anything that
 * writes needs check_admin_referer() and a capability check, as two separate
 * refusals, before it reads the request body.
 *
 * @param string[] $keys Optional subset of flosc_nav_param_keys().
 * @return array<string,string> The parameters present in this request.
 */
function flosc_nav_params( array $keys = array() ) {
	$flosc_wanted = empty( $keys ) ? flosc_nav_param_keys() : $keys;
	$flosc_out    = array();

	foreach ( $flosc_wanted as $flosc_key ) {
		$flosc_key = (string) $flosc_key;
		if ( '' === $flosc_key || ! isset( $_GET[ $flosc_key ] ) ) {
			continue;
		}

		// Arrays are not expected here; a scalar is the whole contract.
		if ( is_array( $_GET[ $flosc_key ] ) ) {
			continue;
		}

		$flosc_out[ $flosc_key ] = sanitize_text_field( wp_unslash( $_GET[ $flosc_key ] ) );
	}

	return $flosc_out;
}
