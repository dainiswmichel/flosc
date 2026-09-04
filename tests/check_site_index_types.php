<?php
/**
 * The site index reads what the site has, not one hardcoded post type.
 *
 * It queried the literal string 'post'. So a chatbot on a site with a shop
 * could not see the shop — and the reason given, for a while, was that
 * WooCommerce products "aren't posts". They are. So are pages, and so are
 * bbPress forum topics and replies. Every one of them is a WP_Post that this
 * indexer already knew how to handle: the row builder reads ID, title, body,
 * permalink and modified date, and nothing in it is specific to a type.
 *
 * The query simply never asked. That is a one-line limitation that was
 * described as an architectural one, which is a worse mistake than the
 * limitation.
 *
 * 'post' stays in the list whatever is chosen. Removing it on upgrade would
 * empty a working library without anyone asking for that.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = $actual === $expected;
	if ( ! $pass ) { $fail++; }
	printf( "%s %-62s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

$index = (string) file_get_contents( $root . '/includes/class-flosc-site-content-index.php' );
$panel = (string) file_get_contents( $root . '/admin/ai-configuration.php' );
$css   = (string) file_get_contents( $root . '/assets/css/flosc-admin.css' );

echo "The rebuild query is not hardcoded to one type\n";
ok( 'no literal post_type => post in the rebuild',
	(bool) preg_match( "/'post_type'\s*=>\s*'post'/", $index ), false );
ok( 'it asks for the resolved list instead',
	strpos( $index, "'post_type'              => \$types," ) !== false, true );
ok( 'which comes from indexed_post_types()',
	strpos( $index, 'public static function indexed_post_types(' ) !== false, true );

echo "\nThe list can never come back empty or bogus\n";
ok( "'post' is appended whatever was saved",
	strpos( $index, "\$types[] = 'post';" ) !== false, true );
ok( 'types this site does not register are dropped',
	strpos( $index, 'return post_type_exists( $type );' ) !== false, true );
ok( "  and an empty result falls back to 'post'",
	strpos( $index, "\$types = array( 'post' );" ) !== false, true );
ok( 'the list is filterable',
	strpos( $index, "apply_filters( 'flosc_site_content_index_post_types'" ) !== false, true );

echo "\nThe floscAdmin chooses, on the panel that rebuilds\n";
ok( 'a checkbox per public post type',
	strpos( $panel, 'name="flow_site_index_post_types[]"' ) !== false, true );
ok( 'built from what this site actually registers',
	strpos( $panel, "get_post_types( array( 'public' => true ), 'objects' )" ) !== false, true );
ok( '  minus attachments, which are not content',
	strpos( $panel, "unset( \$flosc_sci_types_available['attachment'] );" ) !== false, true );
ok( "'post' is shown ticked and disabled",
	strpos( $panel, 'disabled( $flosc_sci_is_post )' ) !== false, true );
ok( '  with a hidden field so disabling does not drop it from the post',
	strpos( $panel, '<input type="hidden" name="flow_site_index_post_types[]" value="post">' ) !== false, true );

// flow_* keys are written by the page-wide Save, which maps arrays through
// sanitize_text_field. The control has to sit inside that form to be saved.
echo "\nIt is saved by the form it sits in\n";
$settings = (string) file_get_contents( $root . '/admin/settings.php' );
ok( 'the page-wide save handles array values',
	strpos( $settings, "\$flosc_new_settings[\$flosc_setting_key] = array_map('sanitize_text_field', \$flosc_value);" ) !== false, true );
// There are two form-closes in this file. The first is the All Flows branch,
// which does not run on the single-flow view where this control lives, so the
// one that matters is the guarded close just above the rebuild control.
ok( 'and the control is emitted before the form closes',
	strpos( $panel, 'name="flow_site_index_post_types[]"' ) < strrpos( $panel, "echo '</form>';" ), true );
ok( '  which is the guarded close above the rebuild control',
	strpos( $panel, "if ( empty( \$GLOBALS['flosc_settings_form_closed_early'] ) ) {" ) !== false, true );

echo "\nStyled by class, nothing inline\n";
ok( 'the rows have a stylesheet rule',
	strpos( $css, '.flosc-sci-type {' ) !== false, true );
ok( 'and the markup carries no style attribute',
	(bool) preg_match( '/class="flosc-sci-type"[^>]*style=/', $panel ), false );

echo $fail ? "\n$fail FAILURES\n" : "\nThe index reads what the site has\n";
exit( $fail ? 1 : 0 );
