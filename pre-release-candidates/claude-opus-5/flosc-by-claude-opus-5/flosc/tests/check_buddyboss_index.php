<?php
/**
 * The chatbot can see BuddyBoss groups, and cannot leak them.
 *
 * The site index queried one hardcoded post type. Most of what that hid was
 * ordinary WordPress — products, pages, forum topics are all WP_Post and the
 * indexer always knew how to read them. The group directory is the one part
 * that genuinely lives elsewhere, in the BuddyPress tables.
 *
 * Two things have to hold. The catalogue must reach the model, because keyword
 * retrieval over post bodies will never produce /groups/lesaep-learners/. And
 * it must fail closed: FLOSC may tighten BuddyBoss privacy and may never loosen
 * it, so a private group is never linked to somebody who is not in it, whatever
 * a flow's settings say.
 *
 * Every BuddyPress call is guarded. FLOSC ships to sites that have never heard
 * of BuddyBoss, and an unguarded groups_get_groups() is a white screen.
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

$index    = (string) file_get_contents( $root . '/includes/class-flosc-site-content-index.php' );
$pack     = (string) file_get_contents( $root . '/includes/class-flosc-chatpack.php' );
$panel    = (string) file_get_contents( $root . '/admin/ai-configuration.php' );
$settings = (string) file_get_contents( $root . '/admin/settings.php' );
$context  = (string) file_get_contents( $root . '/includes/class-flosc-page-context.php' );

echo "Rows can be keyed by something other than a post id\n";
ok( 'there is a row-id normaliser',
	strpos( $index, 'public static function normalize_row_id(' ) !== false, true );
ok( '  it accepts kind:id',
	strpos( $index, "'/^([a-z0-9_]+):([a-z0-9_-]+)\$/i'" ) !== false, true );
ok( 'load() no longer drops non-numeric keys',
	(bool) preg_match( '/\$id = isset\( \$row\[.post_id.\] \).*\(int\) \$key;\s*if \( \$id <= 0 \)/s', $index ), false );
ok( 'every row carries an id and a kind',
	strpos( $index, "'kind'             => 'post'," ) !== false, true );

// Grok's guide flagged load(); these three are the rest of the same tail.
echo "\nAnd the other three places that keyed on an int\n";
ok( 'set_excluded and set_manual_keywords normalise',
	substr_count( $index, '$key = self::normalize_row_id( $post_id );' ), 2 );
ok( "search() prints the row's own id",
	strpos( $index, "\$out .= 'ID: ' . (string) ( \$row['id'] ?? (int) ( \$row['post_id'] ?? 0 ) );" ) !== false, true );

echo "\nBuddyPress is never called without checking it is there\n";
ok( 'there is one availability check',
	strpos( $index, 'public static function groups_available()' ) !== false, true );
ok( '  and the adapter uses it',
	strpos( $index, '! self::groups_available()' ) !== false, true );
foreach ( array( 'groups_is_user_member', 'bp_is_group', 'bp_get_current_group_id' ) as $fn ) {
	$src = ( 'bp_is_group' === $fn || 'bp_get_current_group_id' === $fn ) ? $context : $index;
	ok( $fn . '() is guarded',
		strpos( $src, "function_exists('" . $fn . "')" ) !== false
		|| strpos( $src, "function_exists( '" . $fn . "' )" ) !== false, true );
}

echo "\nThe catalogue reaches the model every turn\n";
ok( 'the index can format it',
	strpos( $index, 'public function format_groups_for_ai(' ) !== false, true );
ok( 'the chatpack asks for it',
	strpos( $pack, 'format_groups_for_ai($flosc_group_flow, $flosc_group_tier)' ) !== false, true );
ok( '  under its own heading',
	strpos( $pack, '## 5c. GROUPS' ) !== false, true );
ok( 'and it is empty when the flow does not index groups',
	strpos( $index, "if ( empty( \$policy['enabled'] ) ) {\n\t\t\treturn '';" ) !== false, true );

echo "\nIt fails closed\n";
ok( "the person's tier must be in the row's list",
	strpos( $index, "if ( ! in_array( \$tier, \$allowed, true ) ) {" ) !== false, true );
ok( 'an excluded row is never shown',
	strpos( $index, "if ( ! empty( \$row['excluded'] ) ) {" ) !== false, true );
ok( 'a non-public group is only linked to an actual member',
	strpos( $index, 'self::viewer_is_group_member( (int) ( $row[\'group_id\'] ?? 0 ) )' ) !== false, true );
ok( '  otherwise it may be named and never described',
	strpos( $index, 'do not describe it and do not give a link' ) !== false, true );
ok( 'hidden groups are never fetched at all',
	strpos( $index, "'show_hidden' => false," ) !== false, true );
ok( 'and the model is told not to invent one',
	strpos( $index, 'Never invent a group, a URL, or terms of entry' ) !== false, true );

echo "\nPolicy is stored on the flow, not on the group\n";
ok( 'the flow setting is read',
	strpos( $index, "flosc_get_setting( 'buddyboss_index'" ) !== false, true );
ok( 'the page-wide Save writes it as a nested array',
	strpos( $settings, "'buddyboss_index' === \$flosc_setting_key" ) !== false, true );
ok( '  rather than flattening it with sanitize_text_field',
	strpos( $settings, "'vgm_rows'    => \$flosc_bb_rows," ) !== false, true );

echo "\nThe floscAdmin can see and set it\n";
ok( 'enable control',
	strpos( $panel, 'name="flow_buddyboss_index[enabled]"' ) !== false, true );
ok( 'which groups',
	strpos( $panel, 'name="flow_buddyboss_index[group_ids][]"' ) !== false, true );
ok( 'who may be told',
	strpos( $panel, 'name="flow_buddyboss_index[vgm_default][]"' ) !== false, true );
ok( 'and it says so plainly when BuddyBoss is absent',
	strpos( $panel, 'No BuddyBoss or BuddyPress group directory was found' ) !== false, true );

echo "\nThe companion knows it is on a group page\n";
ok( 'a group page sets a row id instead of a post id',
	strpos( $context, "\$eval_context['browsing_row_id'] = 'bb_group:' . \$flosc_group_id;" ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nGroups are visible to the model and closed to everyone else\n";
exit( $fail ? 1 : 0 );
