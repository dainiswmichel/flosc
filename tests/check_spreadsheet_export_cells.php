<?php
/**
 * Verify spreadsheet-formula prefixes in the chat-log TSV export.
 *
 * @package FLOSC
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/flosc-spreadsheet-export.php';

$flosc_failures = 0;
$flosc_cases    = array(
	array( '=HYPERLINK("https://example.invalid","x")', '"\'=HYPERLINK(""https://example.invalid"",""x"")"' ),
	array( '+1+1', "'+1+1" ),
	array( '-2+3', "'-2+3" ),
	array( '@SUM(A1:A2)', "'@SUM(A1:A2)" ),
	array( "\t=1+1", "\"'\t=1+1\"" ),
	array( "\r=1+1", "'\\n=1+1" ),
	array( "\n=1+1", "'\\n=1+1" ),
	array( '  =1+1', "'  =1+1" ),
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
	if ( $flosc_got !== $flosc_case[1] ) {
		++$flosc_failures;
		fwrite( STDERR, 'FAIL ' . var_export( $flosc_case[0], true ) . ' -> ' . var_export( $flosc_got, true ) . "\n" );
	}
}

if ( 0 === $flosc_failures ) {
	fwrite( STDOUT, "Spreadsheet export cells: all checks passed\n" );
}

exit( $flosc_failures ? 1 : 0 );
