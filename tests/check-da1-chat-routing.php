<?php
/**
 * DA1 catalog answers remain authoritative on the public chat route.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress bootstrap sentinel required by the class under test.
	define( 'ABSPATH', __DIR__ );
}

$flosc_da1_fixture = dirname( __DIR__ ) . '/starter-packs/da1-catalog-sales/catalog.txt';

/**
 * Test translation helper.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress translation stub for this isolated CLI test.
	return $text;
}

/**
 * Test plural helper.
 *
 * @param string $single Singular text.
 * @param string $plural Plural text.
 * @param int    $number Number.
 * @return string
 */
function _n( $single, $plural, $number ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress plural translation stub for this isolated CLI test.
	return 1 === $number ? $single : $plural;
}

/**
 * Test setting reader.
 *
 * @param string $name    Setting name.
 * @param mixed  $fallback Default value.
 * @return mixed
 */
function flosc_get_setting( $name, $fallback = '' ) {
	return $fallback;
}

require dirname( __DIR__ ) . '/includes/da1/class-flosc-da1-catalogs.php';

$flosc_da1_failures = array();

/**
 * Record a failed assertion.
 *
 * @param bool   $condition Whether the check passed.
 * @param string $message   Failure message.
 * @return void
 */
function flosc_da1_chat_check( $condition, $message ) {
	global $flosc_da1_failures;
	if ( ! $condition ) {
		$flosc_da1_failures[] = $message;
	}
}

$flosc_da1_parser = new FLOSC_DA1_Catalogs();
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI regression test reads a local repository fixture.
$flosc_da1_table  = $flosc_da1_parser->parse_tsv_content( (string) file_get_contents( $flosc_da1_fixture ) );
$flosc_da1_header = array_shift( $flosc_da1_table );
$flosc_da1_rows   = array();

foreach ( $flosc_da1_table as $flosc_da1_cells ) {
	$flosc_da1_row = array();
	foreach ( $flosc_da1_header as $flosc_da1_column_index => $flosc_da1_column ) {
		if ( 'Record Type' === $flosc_da1_column ) {
			$flosc_da1_column = 'Item Type';
		}
		$flosc_da1_row[ $flosc_da1_column ] = (string) ( $flosc_da1_cells[ $flosc_da1_column_index ] ?? '' );
	}
	$flosc_da1_rows[] = $flosc_da1_row;
}

$flosc_da1_audience_method = new ReflectionMethod( 'FLOSC_DA1_Catalogs', 'row_allows_audience' );
$flosc_da1_audience_method->setAccessible( true );

$flosc_da1_catalogs = new class( $flosc_da1_rows, $flosc_da1_audience_method ) extends FLOSC_DA1_Catalogs {
	/**
	 * Catalog rows.
	 *
	 * @var array<int,array<string,string>>
	 */
	private $rows;

	/**
	 * Real VGM predicate.
	 *
	 * @var ReflectionMethod
	 */
	private $audience_method;

	/**
	 * Store fixture rows and the real private VGM predicate.
	 *
	 * @param array<int,array<string,string>> $rows            Catalog rows.
	 * @param ReflectionMethod                $audience_method VGM predicate.
	 */
	public function __construct( $rows, $audience_method ) {
		$this->rows            = $rows;
		$this->audience_method = $audience_method;
	}

	/**
	 * Supply fixture rows while executing FLOSC's real audience predicate.
	 *
	 * @param mixed  $flow_id      Flow ID.
	 * @param mixed  $ivr_file     IVR file.
	 * @param string $access_level Access level.
	 * @return array<int,array<string,string>>
	 */
	public function load_rows_for_flow( $flow_id, $ivr_file, $access_level = 'visitor' ) {
		$visible = array();
		foreach ( $this->rows as $row ) {
			if ( $this->audience_method->invoke( $this, $row, $access_level ) ) {
				$visible[] = $row;
			}
		}
		return $visible;
	}
};

$flosc_da1_visitor_list = $flosc_da1_catalogs->build_catalog_reply(
	'Show all Freeline manuals',
	'dcsj_ivr',
	'dcsj_ivr.md',
	'visitor'
);

flosc_da1_chat_check(
	false !== strpos( $flosc_da1_visitor_list, 'Household Three-Hole Punch User Manual for Beginners' ),
	'The visitor catalog reply omitted the first permitted title.'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_visitor_list, "Beginner's Guide to Refrigerator Shelf Adjustment" ),
	'The visitor catalog reply omitted the second permitted title.'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_visitor_list, 'Practical Strategies for Repositioning a Floor Lamp' ),
	'The visitor catalog reply omitted the third permitted title.'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_visitor_list, 'Effective Wobbly Table De-Wobbling Techniques' ),
	'The visitor catalog reply omitted the fourth permitted title.'
);
flosc_da1_chat_check(
	false === strpos( $flosc_da1_visitor_list, 'Advanced Techniques for Folding Fitted Sheets' ),
	'The visitor catalog reply exposed a Guest title.'
);
flosc_da1_chat_check(
	'You currently have access to 4 catalog items.' === $flosc_da1_catalogs->build_catalog_reply(
		'How many catalog items can I access?',
		'dcsj_ivr',
		'dcsj_ivr.md',
		'visitor'
	),
	'The visitor catalog count was not four.'
);
flosc_da1_chat_check(
	'You currently have access to 8 catalog items.' === $flosc_da1_catalogs->build_catalog_reply(
		'How many catalog items can I access?',
		'dcsj_ivr',
		'dcsj_ivr.md',
		'guest'
	),
	'The Guest catalog count was not eight.'
);

$flosc_da1_item_eleven = $flosc_da1_catalogs->build_catalog_reply(
	'show me 11',
	'dcsj_ivr',
	'dcsj_ivr.md',
	'visitor'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_item_eleven, "11. Beginner's Guide to Refrigerator Shelf Adjustment" ),
	'A short Row Key request did not return the literal catalog title for Item 11.'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_item_eleven, 'Instructions for removing, repositioning, leveling, and reinstalling adjustable refrigerator shelves.' ),
	'A short Row Key request did not deliver the catalog description in chat.'
);
flosc_da1_chat_check(
	false === strpos( $flosc_da1_item_eleven, 'Household Three-Hole Punch User Manual for Beginners' ),
	'A short Row Key request returned an unrelated accessible item.'
);
flosc_da1_chat_check(
	false === strpos( $flosc_da1_item_eleven, '/content/' ),
	'A short Row Key request fabricated a WordPress content URL.'
);

flosc_da1_chat_check(
	false !== strpos( $flosc_da1_visitor_list, "11. Beginner's Guide to Refrigerator Shelf Adjustment" ),
	'The catalog list replaced Item 11 with its visible list position.'
);

flosc_da1_chat_check(
	'You currently have access to 4 catalog items.' === $flosc_da1_catalogs->build_catalog_reply(
		'How many manuals am I trusted with?',
		'dcsj_ivr',
		'dcsj_ivr.md',
		'visitor'
	),
	'The playful Visitor count prompt did not use the authoritative catalog count.'
);

$flosc_da1_three_recommendations = $flosc_da1_catalogs->build_catalog_reply(
	'Suggest 3 absurdly serious manuals',
	'dcsj_ivr',
	'dcsj_ivr.md',
	'visitor'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_three_recommendations, 'Here are 3 catalog items:' ),
	'The three-item recommendation prompt did not return three accessible catalog items. Actual: ' . $flosc_da1_three_recommendations
);
flosc_da1_chat_check(
	false === strpos( $flosc_da1_three_recommendations, 'Advanced Techniques for Folding Fitted Sheets' ),
	'The Visitor recommendation prompt exposed a Guest catalog item.'
);

$flosc_da1_guest_item_twelve = $flosc_da1_catalogs->build_catalog_reply(
	'Show me Item 12',
	'dcsj_ivr',
	'dcsj_ivr.md',
	'guest'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_guest_item_twelve, '12. Effortless Mini-Wastebasket Maintenance and Repair' ),
	'The Guest Item 12 AutoPrompt did not resolve its literal catalog row.'
);

$flosc_da1_member_item_fifty = $flosc_da1_catalogs->build_catalog_reply(
	'Show me Item 50',
	'dcsj_ivr',
	'dcsj_ivr.md',
	'member'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_member_item_fifty, '50. A Practical Guide for Sipping Hot Beverages' ),
	'The Member Item 50 AutoPrompt did not resolve its literal catalog row.'
);

$flosc_da1_hot_beverage = $flosc_da1_catalogs->build_catalog_reply(
	'Find one manual about hot beverages',
	'dcsj_ivr',
	'dcsj_ivr.md',
	'member'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_hot_beverage, '50. A Practical Guide for Sipping Hot Beverages' ),
	'The hot-beverage discovery AutoPrompt did not find the matching catalog item.'
);

$flosc_da1_turn_path = dirname( __DIR__ ) . '/includes/chat-turn/trait-flosc-chat-turn.php';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI regression test inspects one local source file.
$flosc_da1_turn_file   = file_get_contents( $flosc_da1_turn_path );
$flosc_da1_turn_source = preg_replace(
	'/\s+/',
	'',
	(string) $flosc_da1_turn_file
);

flosc_da1_chat_check(
	false !== strpos( $flosc_da1_turn_source, 'if($response_message&&$ai_available&&!$flosc_da1_catalog_answer)' ),
	'The public chat route still permits an AI provider to rewrite a DA1 catalog answer.'
);
flosc_da1_chat_check(
	false !== strpos( $flosc_da1_turn_source, '$ai_available&&!$flosc_da1_catalog_answer&&$token_provider' ),
	'A deterministic DA1 catalog answer can still reserve or charge AI tokens.'
);

if ( array() !== $flosc_da1_failures ) {
	fprintf( STDERR, "%s\n", implode( "\n", $flosc_da1_failures ) );
	exit( 1 );
}

echo "DA1 chat routing: ok\n";
exit( 0 );
