<?php
/**
 * Spreadsheet-safe cells for FLOSC's tab-separated exports.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encode one value as a spreadsheet-safe tab-separated cell.
 *
 * Formula markers and control-prefix characters are neutralized before line
 * breaks are normalized. A formula preceded by spaces is neutralized too,
 * because spreadsheet import behavior differs between applications.
 *
 * @param mixed $value Cell value. Non-strings are cast to string.
 * @return string Encoded cell, without a trailing separator.
 */
function flosc_spreadsheet_text_cell( $value ) {
	$value = (string) $value;

	if ( '' !== $value && preg_match( '/\A(?:[=+\-@\x09\x0A\x0D]| +[=+\-@])/', $value ) ) {
		$value = "'" . $value;
	}

	$value = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $value );

	if ( false !== strpos( $value, "\t" ) || false !== strpos( $value, '"' ) ) {
		$value = '"' . str_replace( '"', '""', $value ) . '"';
	}

	return $value;
}
