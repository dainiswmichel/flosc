<?php
/**
 * Input boundary for read-only navigation parameters.
 *
 * FLOSC reads a handful of GET parameters to decide what to *show*: which.
 * Settings tab, which IVR file, which view, which list filter. They are the.
 * Admin equivalent of a page number. None of them reaches a mutation, and a.
 * Nonce on them would break bookmarks and the back button while protecting.
 * Nothing.
 *
 * WHY THIS READS $_GET DIRECTLY.
 *
 * An earlier version read through filter_input( ..., FILTER_UNSAFE_RAW ) and.
 * This docblock claimed that was not about quietening a sniff. That claim was.
 * False. WPCS's NonceVerification sniff detects reads of the $_GET and $_POST.
 * Variables; it does not model filter_input(), so routing thirty reads through.
 * A function call made them invisible to it. The warnings went to zero because.
 * The scanner could no longer see the read, not because a control was added.
 *
 * FLOSC's own gate caught it. tests/check_wporg_rules.php WPORG-04, written.
 * From the 13 Sep 2026 rejection, reports FILTER_UNSAFE_RAW as not sanitizing,.
 * Which is the same objection WordPress.org made.
 *
 * So the read is a plain superglobal read again, unslashed and sanitized where.
 * It happens. What this function legitimately provides is centralization: the.
 * Read occurs HERE, once, instead of at thirty call sites, so a reviewer checks.
 * One function. NonceVerification will report this function, and that report is.
 * True -- it reads GET without a nonce, deliberately, for the reasons above.
 * A warning that is true is not a defect to be hidden.
 *
 * Anything that returns from here has either matched a closed set of expected.
 * Values or passed a named WordPress sanitizer, and is a string.
 *
 * WHAT THIS MUST NEVER BE USED FOR.
 *
 * Anything that writes. If a value read through here reaches update_option, a.
 * Delete, a file write, a redirect target or any other state change, this is.
 * The wrong function: that caller needs check_admin_referer() or.
 * check_ajax_referer() plus a capability check, as two separate refusals,
 * Before it touches the request body. There is deliberately no variant of this.
 * Helper that covers that case.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read one GET parameter that selects what to display.
 *
 * Values are constrained on the way out, not merely sanitized. Several of the.
 * Call sites this replaced accepted any string the URL carried and then used it.
 * To pick a template or a script handle; passing $allowed turns that into a.
 * Closed set, which is a real narrowing rather than tidier code.
 *
 * @param string   $key       Query parameter name.
 * @param string[] $allowed   Closed set of acceptable values. Empty means the.
 * Value cannot be enumerated in advance -- a.
 * Filename, for instance -- and $sanitizer decides.
 * @param string   $fallback  Returned when the parameter is absent, is not a.
 * String, or is not in $allowed.
 * @param string   $sanitizer Named WordPress sanitizer applied when $allowed is.
 * Empty. Defaults to sanitize_key. Use.
 * Sanitize_file_name for filenames.
 * @return String.
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
 * @param string $key      Query parameter name.
 * @param int    $min      Lowest acceptable value.
 * @param int    $max      Highest acceptable value.
 * @param int    $fallback Returned when absent or outside the range.
 * @return Int.
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
 * Some call sites branch on presence rather than value -- "was ?saved passed",.
 * Not "what does ?saved say".
 *
 * @param string $key Query parameter name.
 * @return Bool.
 */
function flosc_nav_param_present( $key ) {
	return isset( $_GET[ $key ] );
}
