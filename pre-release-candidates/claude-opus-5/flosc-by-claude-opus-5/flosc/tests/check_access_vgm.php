<?php
/**
 * Access is a VGM list, and a row nobody gated is public.
 *
 * Br3nda told a visitor she had no information about a public post while that
 * post sat in the index with the right keywords on it. search() discards a row
 * before it reads a single keyword when the visitor's level does not clear the
 * row's access — and access_allows() read one token, so a real list like
 * "visitor guest member" came out of sanitize_key() as "visitorguestmember",
 * missed the hierarchy, and fell to the members-only default. Silently, for
 * every row that carried a list.
 *
 * These run the two real functions, lifted out of the class by name.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-flosc-site-content-index.php' );

function flosc_grab_method( $src, $name ) {
	if ( ! preg_match( '/\t(?:public|private|protected)(?: static)? function ' . preg_quote( $name, '/' ) . '\(.*?\n\t\}/s', $src, $m ) ) {
		fwrite( STDERR, "missing method: {$name}\n" );
		exit( 1 );
	}
	return $m[0];
}

/**
 * A method that references a class constant needs the constant lifted with it.
 *
 * is_internal_post() grew self::INTERNAL_CATEGORY_ALIASES, the probe class was
 * built from the method alone, and the gate died on an undefined constant
 * instead of testing anything.
 */
function flosc_grab_const( $src, $name ) {
	if ( ! preg_match( '/\tconst ' . preg_quote( $name, '/' ) . '\s*=.*?;/s', $src, $m ) ) {
		fwrite( STDERR, "missing const: {$name}\n" );
		exit( 1 );
	}
	return $m[0];
}

eval( 'class FLOSC_VGM_Probe { '
	. flosc_grab_method( $src, 'vgm_list' ) . "\n"
	. flosc_grab_method( $src, 'access_allows' ) . ' }' );

$probe = new FLOSC_VGM_Probe();
$fail  = 0;

function ok( $label, $actual, $expected ) {
	global $fail;
	$pass = ( $actual === $expected );
	if ( ! $pass ) {
		$fail++;
	}
	printf(
		"%s %-54s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

/* ---- the parser ---- */
ok( 'a single level parses', FLOSC_VGM_Probe::vgm_list( 'member' ), array( 'member' ) );
ok( 'a spaced list parses', FLOSC_VGM_Probe::vgm_list( 'visitor guest member' ), array( 'visitor', 'guest', 'member' ) );
ok( 'a comma list parses', FLOSC_VGM_Probe::vgm_list( 'member,visitor' ), array( 'visitor', 'member' ) );
ok( 'order is always vgm', FLOSC_VGM_Probe::vgm_list( 'MEMBER Visitor' ), array( 'visitor', 'member' ) );
ok( 'junk is dropped', FLOSC_VGM_Probe::vgm_list( 'banana' ), array() );
ok( 'empty is empty', FLOSC_VGM_Probe::vgm_list( '' ), array() );

/* ---- the comparator ---- */
ok( 'visitor reaches visitor', $probe->access_allows( 'visitor', 'visitor' ), true );
ok( 'visitor reaches a full vgm list', $probe->access_allows( 'visitor', 'visitor guest member' ), true );
ok( 'visitor does not reach member', $probe->access_allows( 'visitor', 'member' ), false );
ok( 'visitor does not reach guest member', $probe->access_allows( 'visitor', 'guest member' ), false );
ok( 'a row nobody gated is public', $probe->access_allows( 'visitor', '' ), true );
ok( 'unparseable is public, not private', $probe->access_allows( 'visitor', 'banana' ), true );
ok( 'guest reaches guest', $probe->access_allows( 'guest', 'guest' ), true );
ok( 'member reaches member', $probe->access_allows( 'member', 'member' ), true );
ok( 'member reaches a visitor row', $probe->access_allows( 'member', 'visitor' ), true );

/* ---- FLOSC's own plumbing is never indexed ---- */
$GLOBALS['flosc_probe_slug'] = '';
if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post_id, $taxonomy ) {
		/* The internal-category cases below ask by slug; the resolver cases
		   further down ask by term_id, per taxonomy. */
		if ( isset( $GLOBALS['flosc_probe_terms'] ) ) {
			return $GLOBALS['flosc_probe_terms'][ $taxonomy ] ?? array();
		}
		return array( (object) array( 'slug' => $GLOBALS['flosc_probe_slug'] ) );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}
eval( 'class FLOSC_Internal_Probe { '
	. flosc_grab_const( $src, 'INTERNAL_CATEGORY_ALIASES' ) . "\n"
	. flosc_grab_method( $src, 'is_internal_post' ) . ' }' );

foreach ( array(
	/* The site owner's own material. Not site content at any tier. */
	array( 'internal', true ),
	array( 'flosc-internal', true ),
	array( 'flosc-internal-concierge', true ),
	array( 'flosc-internal-trajectories', true ),
	/* The bare aliases the trajectory and concierge readers accept. */
	array( 'trajectory', true ),
	array( 'trajectories', true ),
	array( 'concierge', true ),
	array( 'music', false ),
	array( 'dziesmu-sveetki', false ),
	/* The hyphen matters: a category that merely starts with the same letters
	   is somebody's own category and belongs in the index. */
	array( 'flosc-internally-nothing', false ),
	array( 'internal-notes', false ),
	array( 'international', false ),
	array( 'concierges', false ),
) as $case ) {
	$GLOBALS['flosc_probe_slug'] = $case[0];
	ok(
		( $case[1] ? 'internal, never indexed: ' : 'ordinary content: ' ) . $case[0],
		FLOSC_Internal_Probe::is_internal_post( 1 ),
		$case[1]
	);
}

/* ---- depth: what each tier gets of a post ---- */
/*
 * Access has two axes. The tier says WHO — and it is a floor, so V includes
 * VGM, G excludes V, M excludes VG. Depth says HOW MUCH. These run the real
 * folding and precedence, because the bug that reaches a visitor is never in
 * the vocabulary, it is in which rule won.
 */
eval( 'class FLOSC_Depth_Probe { '
	. flosc_grab_const( $src, 'DEPTHS' ) . "\n"
	. flosc_grab_const( $src, 'TIERS' ) . "\n"
	. flosc_grab_method( $src, 'depth_token' ) . "\n"
	. flosc_grab_method( $src, 'depth_rank' ) . "\n"
	. flosc_grab_method( $src, 'tier_token' ) . "\n"
	. flosc_grab_method( $src, 'tiers_from' ) . "\n"
	. flosc_grab_method( $src, 'depth_map' ) . "\n"
	. flosc_grab_method( $src, 'normalize_depth_map' ) . "\n"
	. flosc_grab_method( $src, 'fold_rules' ) . ' }' );

function flosc_fold( array $rules ) {
	return FLOSC_Depth_Probe::fold_rules( $rules );
}

ok( 'the tier is a floor: V covers VGM', FLOSC_Depth_Probe::tiers_from( 'visitor' ), array( 'visitor', 'guest', 'member' ) );
ok( 'G excludes V', FLOSC_Depth_Probe::tiers_from( 'guest' ), array( 'guest', 'member' ) );
ok( 'M excludes VG', FLOSC_Depth_Probe::tiers_from( 'member' ), array( 'member' ) );

ok(
	'a visitor rule opens the post to everybody',
	flosc_fold( array( array( 'vgm' => 'visitor', 'depth' => 'full' ) ) ),
	array( 'visitor' => 'full', 'guest' => 'full', 'member' => 'full' )
);
ok(
	'a member rule leaves the tiers below at title',
	flosc_fold( array( array( 'vgm' => 'member', 'depth' => 'full' ) ) ),
	array( 'visitor' => 'title', 'guest' => 'title', 'member' => 'full' )
);
ok(
	'two rules on one scope combine per tier',
	flosc_fold( array(
		array( 'vgm' => 'visitor', 'depth' => 'readmore' ),
		array( 'vgm' => 'guest', 'depth' => 'full' ),
	) ),
	array( 'visitor' => 'readmore', 'guest' => 'full', 'member' => 'full' )
);
ok(
	'a higher tier never gets less than a lower one',
	FLOSC_Depth_Probe::normalize_depth_map( array( 'visitor' => 'full', 'guest' => 'title', 'member' => 'excerpt' ) ),
	array( 'visitor' => 'full', 'guest' => 'full', 'member' => 'full' )
);
ok(
	'a rule stored before these columns existed still means members only',
	flosc_fold( array( array( 'type' => 'category', 'id' => 7, 'level' => 'lesaep_learners' ) ) ),
	array( 'visitor' => 'title', 'guest' => 'title', 'member' => 'full' )
);
ok( 'no rule at all is no answer, not a permissive one', flosc_fold( array() ), null );
ok( 'junk is not a depth', FLOSC_Depth_Probe::depth_token( 'everything' ), '' );
ok( 'junk is not a tier', FLOSC_Depth_Probe::tier_token( 'admin' ), '' );

/* ---- precedence: the most specific scope decides ---- */
/*
 * Merging every scope instead of deciding between them would make one thing
 * impossible: closing a post its category left open. The Captain's case is the
 * other direction — post 412 open while its category is shut — and both have to
 * work, so the resolver picks a scope and folds only that one.
 */
$GLOBALS['flosc_probe_terms']     = array( 'category' => array(), 'post_tag' => array() );
$GLOBALS['flosc_probe_term_meta'] = array();
$GLOBALS['flosc_probe_post_meta'] = array();
$GLOBALS['flosc_current_settings'] = array();

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $id, $key, $single = false ) {
		return $GLOBALS['flosc_probe_term_meta'][ (int) $id ][ $key ] ?? '';
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) {
		return $GLOBALS['flosc_probe_post_meta'][ (int) $id ][ $key ] ?? '';
	}
}

eval( 'class FLOSC_Resolve_Probe { '
	. flosc_grab_const( $src, 'DEPTHS' ) . "\n"
	. flosc_grab_const( $src, 'TIERS' ) . "\n"
	. flosc_grab_method( $src, 'depth_token' ) . "\n"
	. flosc_grab_method( $src, 'depth_rank' ) . "\n"
	. flosc_grab_method( $src, 'tier_token' ) . "\n"
	. flosc_grab_method( $src, 'tiers_from' ) . "\n"
	. flosc_grab_method( $src, 'depth_map' ) . "\n"
	. flosc_grab_method( $src, 'normalize_depth_map' ) . "\n"
	. flosc_grab_method( $src, 'default_depth_map' ) . "\n"
	. flosc_grab_method( $src, 'protection_rules' ) . "\n"
	. flosc_grab_method( $src, 'fold_rules' ) . "\n"
	. flosc_grab_method( $src, 'meta_rules' ) . "\n"
	. flosc_grab_method( $src, 'resolve_vgm' ) . ' }' );

/* The probe's post 5 sits in category 10 and carries tag 20. */
$GLOBALS['flosc_probe_terms'] = array(
	'category' => array( (object) array( 'term_id' => 10 ) ),
	'post_tag' => array( (object) array( 'term_id' => 20 ) ),
);

ok(
	'no rule anywhere: the site default, which ships open',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'full', 'guest' => 'full', 'member' => 'full' )
);

$GLOBALS['flosc_current_settings']['protected_content'] = array(
	array( 'type' => 'category', 'id' => 10, 'vgm' => 'guest', 'depth' => 'readmore' ),
);
ok(
	'a category rule reaches its posts',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'title', 'guest' => 'readmore', 'member' => 'readmore' )
);

$GLOBALS['flosc_current_settings']['protected_content'][] =
	array( 'type' => 'tag', 'id' => 20, 'vgm' => 'visitor', 'depth' => 'excerpt' );
ok(
	'a tag rule beats the category rule outright',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'excerpt', 'guest' => 'excerpt', 'member' => 'excerpt' )
);

$GLOBALS['flosc_current_settings']['protected_content'][] =
	array( 'type' => 'post', 'id' => 5, 'vgm' => 'visitor', 'depth' => 'full' );
ok(
	'a post rule opens a post its category closed',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'full', 'guest' => 'full', 'member' => 'full' )
);

$GLOBALS['flosc_current_settings']['protected_content'] = array(
	array( 'type' => 'category', 'id' => 10, 'vgm' => 'visitor', 'depth' => 'full' ),
	array( 'type' => 'post', 'id' => 5, 'vgm' => 'member', 'depth' => 'full' ),
);
ok(
	'and closes one its category left open',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'title', 'guest' => 'title', 'member' => 'full' )
);

/* A rule written on the object itself, rather than in the table. */
$GLOBALS['flosc_current_settings']['protected_content'] = array();
$GLOBALS['flosc_probe_term_meta'][10] = array( '_flosc_vgm' => 'member', '_flosc_depth' => 'full' );
ok(
	'a rule set on the category screen counts',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'title', 'guest' => 'title', 'member' => 'full' )
);

$GLOBALS['flosc_probe_post_meta'][5] = array( '_flosc_vgm' => 'visitor' );
ok(
	'half a rule on the post is not a rule',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'title', 'guest' => 'title', 'member' => 'full' )
);

$GLOBALS['flosc_probe_post_meta'][5]['_flosc_depth'] = 'excerpt';
ok(
	'both halves on the post, and the post wins',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'excerpt', 'guest' => 'excerpt', 'member' => 'excerpt' )
);

$GLOBALS['flosc_probe_post_meta']  = array();
$GLOBALS['flosc_probe_term_meta']  = array();
$GLOBALS['flosc_current_settings'] = array( 'content_default_vgm' => array( 'visitor' => 'title', 'guest' => 'excerpt', 'member' => 'full' ) );
ok(
	'"all titles VGM" as a site default',
	FLOSC_Resolve_Probe::resolve_vgm( 5 ),
	array( 'visitor' => 'title', 'guest' => 'excerpt', 'member' => 'full' )
);
$GLOBALS['flosc_current_settings'] = array();

/* ---- the slice a row hands back at each depth ---- */
eval( 'class FLOSC_Slice_Probe { '
	. flosc_grab_const( $src, 'DEPTHS' ) . "\n"
	. flosc_grab_const( $src, 'TIERS' ) . "\n"
	. flosc_grab_method( $src, 'depth_token' ) . "\n"
	. flosc_grab_method( $src, 'depth_rank' ) . "\n"
	. flosc_grab_method( $src, 'tier_token' ) . "\n"
	. flosc_grab_method( $src, 'normalize_depth_map' ) . "\n"
	. flosc_grab_method( $src, 'vgm_list' ) . "\n"
	. flosc_grab_method( $src, 'access_allows' ) . "\n"
	. flosc_grab_method( $src, 'row_depth' ) . "\n"
	. flosc_grab_method( $src, 'row_body_at' ) . ' }' );

$slice = new FLOSC_Slice_Probe();
$row   = array(
	'content'     => 'The opening section. And then the rest of the post.',
	'more_offset' => strlen( 'The opening section.' ),
	'excerpt'     => 'A hand-written excerpt.',
	'snippet'     => 'The opening sec',
	'vgm'         => array( 'visitor' => 'title', 'guest' => 'readmore', 'member' => 'full' ),
);

ok( 'title depth returns no body at all', $slice->row_body_at( $row, 'title' ), '' );
ok( 'readmore stops at the break', $slice->row_body_at( $row, 'readmore' ), 'The opening section.' );
ok( 'excerpt returns the excerpt', $slice->row_body_at( $row, 'excerpt' ), 'A hand-written excerpt.' );
ok( 'full returns the post', $slice->row_body_at( $row, 'full' ), 'The opening section. And then the rest of the post.' );

$no_excerpt = $row;
$no_excerpt['excerpt'] = '';
ok( 'no excerpt written falls back to the snippet', $slice->row_body_at( $no_excerpt, 'excerpt' ), 'The opening sec' );

$no_more = $row;
$no_more['more_offset'] = 0;
ok( 'no read-more break: the teaser is the post', $slice->row_body_at( $no_more, 'readmore' ), 'The opening section. And then the rest of the post.' );

ok( 'the row map answers per tier', $slice->row_depth( $row, 'guest' ), 'readmore' );
ok( 'and a visitor gets the title', $slice->row_depth( $row, 'visitor' ), 'title' );

/* A row written before depth existed still answers, at the two depths it can. */
$legacy = array( 'content' => 'body', 'access' => 'member' );
ok( 'a legacy row locks a visitor', $slice->row_depth( $legacy, 'visitor' ), 'title' );
ok( 'a legacy row opens to a member', $slice->row_depth( $legacy, 'member' ), 'full' );

/* ---- the row value must reach the comparator unmangled ---- */
/*
 * search() and format_map_for_ai() ran sanitize_key() on the row's access
 * before comparing it. That strips the space, so "guest member" arrived as
 * "guestmember", vgm_list() found no level in it, and access_allows() read the
 * empty list as nobody having gated the row — handing a guest-and-member row to
 * a logged-out visitor, body and all. The comparator was right; its two callers
 * destroyed the value on the way in. A source guard, because the bug lives at
 * the call site rather than inside any function this file can lift.
 */
ok(
	'no caller sanitize_key()s the access value',
	(bool) preg_match( "/sanitize_key\\(\\s*\\(string\\)\\s*\\(\\s*\\\$row\\['access'\\]/", $src ),
	false
);
ok( 'and the mangled form would have leaked', $probe->access_allows( 'visitor', 'guestmember' ), true );
ok( 'while the real value locks', $probe->access_allows( 'visitor', 'guest member' ), false );

echo $fail ? "\n{$fail} FAILURES\n" : "\nall green\n";
exit( $fail ? 1 : 0 );
