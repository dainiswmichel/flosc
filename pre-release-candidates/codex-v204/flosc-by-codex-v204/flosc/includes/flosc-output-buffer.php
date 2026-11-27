<?php
/**
 * Output-buffer helpers.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_capture_output' ) ) {
	/**
	 * Capture output from a renderer without disturbing buffers owned elsewhere.
	 *
	 * The buffer is opened and closed inside this function. Removable buffers
	 * opened by the renderer are discarded before FLOSC's buffer is collected.
	 * Cleanup stops at a non-removable buffer and leaves it for PHP to flush at
	 * request end. If the renderer closes FLOSC's buffer, this function never
	 * closes a parent buffer that it does not own.
	 *
	 * @param callable $renderer Callback that prints the content to capture.
	 * @return string Captured output, or an empty string when FLOSC's buffer is no
	 *                longer the current buffer and cannot be collected safely.
	 */
	function flosc_capture_output( $renderer ) {
		$flosc_parent_level = ob_get_level();
		$flosc_output       = '';

		try {
			ob_start();
			call_user_func( $renderer );
		} finally {
			// A buffer opened without PHP_OUTPUT_HANDLER_REMOVABLE -- zlib
			// compression, ob_gzhandler -- cannot be discarded: the discard
			// returns false and the level stays where it was. Stopping at the
			// first buffer that will not close leaves it for PHP to flush at
			// request end; looping on it would never return.
			while ( ob_get_level() > ( $flosc_parent_level + 1 ) ) {
				$flosc_level_before = ob_get_level();
				ob_end_clean();
				if ( ob_get_level() >= $flosc_level_before ) {
					break;
				}
			}

			if ( ob_get_level() === ( $flosc_parent_level + 1 ) ) {
				$flosc_captured = ob_get_clean();
				if ( false !== $flosc_captured ) {
					$flosc_output = $flosc_captured;
				}
			}
		}

		return $flosc_output;
	}
}
