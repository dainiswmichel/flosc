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
 * @param string   $default   Returned when the parameter is absent, is not a
 *                            string, or is not in $allowed.
 * @param string   $sanitizer Named WordPress sanitizer applied when $allowed is
 *                            empty. Defaults to sanitize_key. Use
 *                            sanitize_file_name for filenames.
 * @return string
 */
function flosc_nav_param( $key, array $allowed = array(), $default = '', $sanitizer = 'sanitize_key' ) {
	if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
		return $default;
	}

	$value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );

	if ( array() !== $allowed ) {
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	if ( is_string( $sanitizer ) && function_exists( $sanitizer ) ) {
		$value = (string) call_user_func( $sanitizer, $value );
	}

	return ( '' === $value ) ? $default : $value;
}

/**
 * Read one GET parameter that selects a display position, as an integer.
 *
 * @param string $key     Query parameter name.
 * @param int    $min     Lowest acceptable value.
 * @param int    $max     Highest acceptable value.
 * @param int    $default Returned when absent or outside the range.
 * @return int
 */
function flosc_nav_param_int( $key, $min = 0, $max = PHP_INT_MAX, $default = 0 ) {
	$value = filter_input(
		INPUT_GET,
		$key,
		FILTER_VALIDATE_INT,
		array( 'options' => array( 'min_range' => (int) $min, 'max_range' => (int) $max ) )
	);

	return ( null === $value || false === $value ) ? (int) $default : (int) $value;
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
