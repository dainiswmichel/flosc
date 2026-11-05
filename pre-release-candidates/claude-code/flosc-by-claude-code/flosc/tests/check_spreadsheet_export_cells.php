<?php
/**
 * Spreadsheet formula prefixes in the chat-log TSV export.
 *
 * Run: php tests/check_spreadsheet_export_cells.php
 *
 * @package FLOSC
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/flosc-spreadsheet-export.php';

$flosc_failures = 0;
$flosc_cases    = array(
	// input => expected encoded cell.
	array( '=HYPERLINK("https://example.invalid","x")', '"\'=HYPERLINK(""https://example.invalid"",""x"")"' ),
	array( '+1+1', "'+1+1" ),
	array( '-2+3', "'-2+3" ),
	array( '@SUM(A1:A2)', "'@SUM(A1:A2)" ),
	array( "\t=1+1", "\"'\t=1+1\"" ),
	array( "\r=1+1", "'\\n=1+1" ),
	array( 'hello', 'hello' ),
	array( 'a=b', 'a=b' ),
	array( "line one\nline two", 'line one\\nline two' ),
	array( 'say "hi"', '"say ""hi"""' ),
	array( '', '' ),
	array( 42, '42' ),
	array( -5, "'-5" ),
);

foreach ( $flosc_cases as $flosc_case ) {
	$flosc_got = flosc_spreadsheet_text_cell( $flosc_case[0] );
	$flosc_ok  = ( $flosc_got === $flosc_case[1] );
	if ( ! $flosc_ok ) {
		++$flosc_failures;
	}
	fwrite( STDOUT, ( $flosc_ok ? 'ok   ' : 'FAIL ' ) . var_export( $flosc_case[0], true ) . ' -> ' . var_export( $flosc_got, true ) . "\n" );
}

// Read-back: every cell of an encoded row, split on tabs outside quotes, must
// not begin with a formula trigger.
$flosc_row   = array( '=1+1', '+2', '-3', '@4', 'plain' );
$flosc_line  = implode( "\t", array_map( 'flosc_spreadsheet_text_cell', $flosc_row ) );
$flosc_cells = str_getcsv( $flosc_line, "\t", '"', '\\' );
foreach ( $flosc_cells as $flosc_cell ) {
	$flosc_ok = ( '' === $flosc_cell || false === strpos( "=+-@\t\r", $flosc_cell[0] ) );
	if ( ! $flosc_ok ) {
		++$flosc_failures;
	}
	fwrite( STDOUT, ( $flosc_ok ? 'ok   ' : 'FAIL ' ) . 'read back as text: ' . $flosc_cell . "\n" );
}

exit( $flosc_failures ? 1 : 0 );
