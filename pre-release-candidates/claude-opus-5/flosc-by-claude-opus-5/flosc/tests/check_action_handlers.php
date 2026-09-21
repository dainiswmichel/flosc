<?php
/**
 * The shared admin delegate owns its selectors. Nothing may listen twice.
 *
 * assets/js/flosc-admin-events.js is enqueued on every FLOSC admin screen and
 * delegates from document for [data-flosc-action] clicks and changes, and for
 * submits of form[data-confirm-message]. Individual admin pages had printed
 * their own listeners for the same selectors. A second listener on document
 * does not replace the first — both run.
 *
 * Three of those were live:
 *
 *   toggle-msg-card       one click called floscToggleMsg() twice, so the IVR
 *                         accordion card opened and closed in the same frame
 *                         and read as a dead control
 *   toggle-offer-fields   doubled the same way, invisible only because that
 *                         function sets its class from an explicit boolean
 *   form confirm          two confirm() dialogs for one Delete, on both the
 *                         IVR page and the Flow page
 *
 * None of that is visible in review. Each listener is correct read alone; the
 * defect exists only in the pair.
 *
 * Page-specific listeners over page-specific selectors are fine and expected —
 * this only guards the selectors the shared delegate already owns.
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

$shared_path = $root . '/assets/js/flosc-admin-events.js';
$shared      = (string) file_get_contents( $shared_path );

echo "The shared delegate is present and still owns its selectors\n";
ok( 'flosc-admin-events.js was read', $shared !== '', true );
foreach ( array( 'toggle-msg-card', 'toggle-new-editor', 'test-api-endpoint', 'toggle-offer-fields' ) as $action ) {
	ok( $action . ' is handled there',
		strpos( $shared, "action === '" . $action . "'" ) !== false, true );
}
ok( 'and so is the generic form confirm',
	strpos( $shared, 'form.dataset.confirmMessage' ) !== false, true );

// Every admin page, plus any admin-only script. The front-end app is a
// different document and is deliberately not compared against these.
$pages = array();
foreach ( array( glob( $root . '/admin/*.php' ), glob( $root . '/admin/docs/*.php' ), array( $root . '/assets/js/ivr-admin.js' ) ) as $set ) {
	foreach ( (array) $set as $file ) {
		if ( is_file( $file ) ) {
			$pages[ str_replace( $root . '/', '', $file ) ] = (string) file_get_contents( $file );
		}
	}
}
ksort( $pages );

// Selectors the shared delegate claims. A page that reaches for one of these
// from its own document listener is the collision.
$owned = array(
	'[data-flosc-action]'          => 'the delegated action attribute',
	'form[data-confirm-message]'   => 'the form confirm attribute',
	'data-flosc-action="'          => 'a specific delegated action',
);

echo "\nNo admin page listens on document for a selector the delegate owns\n";
$collisions = array();
foreach ( $pages as $file => $src ) {
	if ( ! preg_match_all( "/document\.addEventListener\(\s*'(click|change|submit)'.*?(?=\n\s*(?:document\.addEventListener|\}\)\(\);|<\?php))/s", $src, $blocks ) ) {
		continue;
	}
	foreach ( $blocks[0] as $block ) {
		foreach ( $owned as $selector => $what ) {
			if ( strpos( $block, $selector ) !== false ) {
				$collisions[] = $file . ' reaches for ' . $what;
			}
		}
	}
}
ok( 'no page duplicates the shared delegate', array_values( array_unique( $collisions ) ), array() );

// The two pages that had them. Named, so a re-add fails loudly rather than
// blending into a large file.
echo "\nThe two pages that carried duplicates stay clean\n";
foreach ( array( 'admin/ivr-messages.php', 'admin/flow.php' ) as $file ) {
	ok( $file . ' declares no document click/change/submit listener',
		(bool) preg_match( "/document\.addEventListener\(\s*'(click|change|submit)'/", $pages[ $file ] ), false );
}

echo $fail ? "\n$fail FAILURES\n" : "\nOne delegate, one handler per selector\n";
exit( $fail ? 1 : 0 );
