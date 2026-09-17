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
 * WHY filter_input() AND NOT $_GET
 *
 * Not to quieten a sniff. filter_input() is the correct boundary here because
 * these values arrive from outside and must be typed and constrained on the way
 * in, and because it reads the original request rather than a superglobal that
 * any earlier code may have written to -- which this plugin did do, in
 * redirect_to_settings_tab(), until it was removed.
 *
 * FILTER_UNSAFE_RAW is NOT the sanitization here. It is the "give me the string
 * unchanged" flag; sanitize_text_field() and the allowlist below do the actual
 * work, in that order. Anything that returns from this function has either
 * matched a closed set of expected values or passed a named WordPress
 * sanitizer, and is a string.
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
	$raw = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
	if ( ! is_string( $raw ) ) {
		return $default;
	}

	$value = sanitize_text_field( wp_unslash( $raw ) );

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
	return filter_has_var( INPUT_GET, $key );
}
