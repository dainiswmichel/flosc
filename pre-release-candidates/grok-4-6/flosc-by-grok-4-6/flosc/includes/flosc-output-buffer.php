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
	 * Capture output from a renderer without closing a buffer this call did not open.
	 *
	 * Nested buffers above the one this call opens are discarded. The collect
	 * call runs only while this call's buffer is still the current buffer. A renderer
	 * that has already closed it gets an empty string back, and the parent buffer
	 * stays open. exit(), die(), and wp_die() skip finally. The shutdown callback
	 * for this call then flushes only buffers above the saved parent level, which
	 * keeps flushed bytes, including a wp_die() message.
	 *
	 * @param callable $renderer Callback that prints the content to capture.
	 * @return string Captured output, or an empty string when the renderer closed
	 *                this buffer before it could be collected.
	 */
	function flosc_capture_output( $renderer ) {
		$flosc_parent_level = ob_get_level();
		$flosc_output       = '';
		$flosc_closed       = false;

		$flosc_shutdown = static function () use ( $flosc_parent_level, &$flosc_closed ) {
			if ( $flosc_closed ) {
				return;
			}

			while ( ob_get_level() > $flosc_parent_level ) {
				if ( ! ob_end_flush() ) {
					break;
				}
			}

			$flosc_closed = true;
		};
		register_shutdown_function( $flosc_shutdown );

		try {
			ob_start();
			call_user_func( $renderer );
		} finally {
			if ( ! $flosc_closed ) {
				while ( ob_get_level() > ( $flosc_parent_level + 1 ) ) {
					if ( ! ob_end_clean() ) {
						break;
					}
				}

				if ( ob_get_level() === ( $flosc_parent_level + 1 ) ) {
					$flosc_captured = ob_get_clean();
					if ( false !== $flosc_captured ) {
						$flosc_output = $flosc_captured;
					}
				}

				$flosc_closed = true;
			}
		}

		return $flosc_output;
	}
}
