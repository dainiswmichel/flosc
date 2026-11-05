<?php
/**
 * Spreadsheet-safe cells for FLOSC's tab-separated exports.
 *
 * A chat log export is opened in Excel, Numbers, LibreOffice or Google Sheets
 * far more often than in a text editor. Those programs read a cell that begins
 * with = + - or @ as a formula, and a cell that begins with a tab or carriage
 * return can be shifted into one. Chat text is typed by visitors, so a message
 * such as =HYPERLINK("https://example.invalid","click") would arrive in the
 * administrator's spreadsheet as a live formula.
 *
 * The defence is the one OWASP describes for CSV injection: put a single quote
 * in front of any cell that starts with one of those characters. Spreadsheets
 * show the quote-prefixed cell as text and do not evaluate it. The quote is
 * visible in a text editor, which is the intended trade: the export stays a
 * faithful record, and nothing in it runs.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encode one value as a cell of a tab-separated export.
 *
 * In order:
 * 1. A value whose first character is = + - @ tab or carriage return gets a
 *    leading single quote, so spreadsheets treat it as text.
 * 2. Line breaks become the two characters \n, keeping one record per line.
 * 3. A value that still holds a tab or a double quote is wrapped in double
 *    quotes, with inner double quotes doubled.
 *
 * Step 1 runs first, on the raw value, so a leading tab or carriage return is
 * seen before step 2 rewrites it.
 *
 * @param mixed $value Cell value. Non-strings are cast to string.
 * @return string Encoded cell, without a trailing separator.
 */
function flosc_spreadsheet_text_cell( $value ) {
	$value = (string) $value;

	if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
		$value = "'" . $value;
	}

	$value = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $value );

	if ( false !== strpos( $value, "\t" ) || false !== strpos( $value, '"' ) ) {
		$value = '"' . str_replace( '"', '""', $value ) . '"';
	}

	return $value;
}
