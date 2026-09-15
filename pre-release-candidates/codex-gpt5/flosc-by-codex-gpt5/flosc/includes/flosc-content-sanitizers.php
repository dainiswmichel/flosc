<?php
/**
 * Shared sanitizers for authored FLOSC text files.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_sanitize_ivr_markdown' ) ) {
	/**
	 * Sanitize authored Markdown before it is written to FLOSC storage.
	 *
	 * Intentional Markdown remains intact. The boundary rejects null bytes,
	 * validates UTF-8, normalizes line endings, removes unsafe control bytes and
	 * PHP opening tags, and enforces the caller's stored-size limit.
	 *
	 * @param mixed $raw       Untrusted text body.
	 * @param int   $max_bytes Maximum stored size. Defaults to 1.5 MiB.
	 * @return string|WP_Error Sanitized body or validation error.
	 */
	function flosc_sanitize_ivr_markdown( $raw, $max_bytes = 1572864 ) {
		if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
			return new WP_Error( 'flosc_markdown_invalid', __( 'Content must be text.', 'flosc' ) );
		}

		$text = (string) $raw;
		if ( false !== strpos( $text, "\0" ) ) {
			return new WP_Error( 'flosc_markdown_null_byte', __( 'Content contains invalid characters.', 'flosc' ) );
		}

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
			if ( ! function_exists( 'mb_convert_encoding' ) ) {
				return new WP_Error( 'flosc_markdown_encoding', __( 'Content must be valid UTF-8.', 'flosc' ) );
			}

			$converted = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
			$text      = is_string( $converted ) ? $converted : '';
		}

		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
		if ( ! is_string( $text ) ) {
			$text = '';
		}

		$text = str_ireplace( array( '<?php', '<?=', '<?' ), '', $text );
		if ( strlen( $text ) > absint( $max_bytes ) ) {
			return new WP_Error( 'flosc_markdown_too_large', __( 'Content is too large.', 'flosc' ) );
		}

		return $text;
	}
}

if ( ! function_exists( 'flosc_uploaded_audio_container_matches_format' ) ) {
	/**
	 * Confirm that uploaded bytes match a supported browser audio container.
	 *
	 * MIME labels and filename extensions are browser-supplied and are not
	 * evidence of file type. These signatures cover the three MediaRecorder
	 * containers accepted by FLOSC's visitor-audio route.
	 *
	 * @param string $bytes  Uploaded file bytes.
	 * @param string $format Allowlisted format slug.
	 * @return bool Whether the container signature matches the format.
	 */
	function flosc_uploaded_audio_container_matches_format( $bytes, $format ) {
		if ( ! is_string( $bytes ) || strlen( $bytes ) < 4 ) {
			return false;
		}
		if ( 'webm' === $format ) {
			return "\x1A\x45\xDF\xA3" === substr( $bytes, 0, 4 );
		}
		if ( 'ogg' === $format ) {
			return 'OggS' === substr( $bytes, 0, 4 );
		}
		if ( 'mp4' === $format ) {
			return strlen( $bytes ) >= 12 && 'ftyp' === substr( $bytes, 4, 4 );
		}
		return false;
	}
}
