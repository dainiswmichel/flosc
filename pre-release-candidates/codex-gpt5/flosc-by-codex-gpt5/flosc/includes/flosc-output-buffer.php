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
	 * The buffer is opened and closed inside this function. If the renderer opens
	 * additional buffers, they are discarded before FLOSC's buffer is collected.
	 * If the renderer closes FLOSC's buffer, this function never closes a parent
	 * buffer that it does not own.
	 *
	 * @param callable $renderer Callback that prints the content to capture.
	 * @return string Captured output, or an empty string when the renderer closed
	 *                FLOSC's buffer before it could be collected.
	 */
	function flosc_capture_output( $renderer ) {
		$flosc_parent_level = ob_get_level();
		$flosc_output       = '';

		try {
			ob_start();
			call_user_func( $renderer );
		} finally {
			while ( ob_get_level() > ( $flosc_parent_level + 1 ) ) {
				ob_end_clean();
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
