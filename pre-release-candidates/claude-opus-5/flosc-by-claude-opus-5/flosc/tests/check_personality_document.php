<?php
/**
 * The personality document keeps the shape it ships with.
 *
 * This gate asserts SHIPPED DEFAULTS ONLY. A floscAdmin can rename every
 * station, reorder them, change every density and gain, and add situations the
 * shipped four never had — that is the whole point of the builder, and nothing
 * here may object to any of it. What is pinned is what FLOSC hands someone on
 * the first day: the eleven stations, the gain ladder, and which lines are
 * allowed to reach a provider.
 *
 * @package FLOSC
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
	printf( "%s %-64s %s%s\n", $pass ? 'ok  ' : 'FAIL', $label, var_export( $actual, true ), $pass ? '' : ' (want ' . var_export( $expected, true ) . ')' );
}

$builder = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder.js' );
$library = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
$markup  = (string) file_get_contents( $root . '/assets/personality-builder/flosc-personality-builder-markup.php' );

/*
 * Comments are stripped before any search. Two earlier gates in this suite
 * matched their own explanatory comment and reported it as the defect.
 */
$code = preg_replace( '#/\*.*?\*/#s', '', $builder );
$code = (string) preg_replace( '#(^|\s)//.*$#m', '$1', (string) $code );

// These are also the aspect palette's shelves. One list, not two: an aspect
// waits on the shelf it will be written under.
echo "The thirteen headings, in density order\n";
$stations = array(
	'Identity and Role'                    => 6,
	'Philosophy and Values'                => 12,
	'Boundaries and Prohibitions'          => 18,
	'Knowledge, Doubt and Correction'      => 24,
	'Opinions and Preferences'             => 30,
	'Tone and Communication Style'         => 40,
	'Stance Toward the Human'              => 48,
	'Behavior in Ambiguity'                => 56,
	'Adaptation'                           => 62,
	'Resourcefulness'                      => 68,
	'Decisions including Infrequent Cases' => 74,
	'Banned Words and Fillers to Avoid'    => 84,
	'Output and Delivery'                  => 94,
);
preg_match( '/const SOUL_LAYERS = \[(.*?)\n  \];/s', $code, $m );
$layers = isset( $m[1] ) ? $m[1] : '';
ok( 'SOUL_LAYERS located', strlen( $layers ) > 200, true );

$seen = array();
foreach ( $stations as $label => $density ) {
	$found = (bool) preg_match( '/label: "' . preg_quote( $label, '/' ) . '".*?density: ' . $density . ' \}/', $layers );
	ok( '  ' . $label . ' at ' . $density, $found, true );
	if ( $found ) { $seen[] = $label; }
}
ok( 'thirteen and no more', substr_count( $layers, 'label: "' ), 13 );

echo "\nThe palette's shelves are those headings\n";
ok( 'derived from the container list, not a second array',
	strpos( $code, 'return containersSorted()' ) !== false, true );
ok( '  a card filed under an old shelf resolves to a heading',
	strpos( $code, 'function headingForCard(t)' ) !== false, true );
ok( '  and never to a column that does not exist',
	strpos( $code, 'return categoryExists(mapped) ? mapped : firstCategoryId();' ) !== false, true );

echo "\nThe gain ladder\n";
$rungs = array(
	-100 => 'never',
	-75  => 'almost never',
	-50  => 'rarely',
	-25  => 'less often than not',
	0    => 'no preference',
	25   => 'more often than not',
	50   => 'often',
	75   => 'usually',
	100  => 'always',
);
foreach ( $rungs as $gain => $word ) {
	ok( sprintf( '  %+5d reads "%s"', $gain, $word ),
		strpos( $code, '{ g: ' . $gain . ', word: "' . $word . '" }' ) !== false, true );
}

echo "\nnever and always are reserved for the invariants\n";
// A value short of the extreme means an exception exists. A word that reads as
// absolute would hide it, so only exactly +/-100 may take one.
ok( 'gainWord returns "never" only at exactly -100',
	strpos( $code, 'if (n === -100) return "never";' ) !== false, true );
ok( 'gainWord returns "always" only at exactly +100',
	strpos( $code, 'if (n === 100) return "always";' ) !== false, true );
ok( '  and both absolutes are skipped when rounding inward',
	strpos( $code, 'if (r.g === -100 || r.g === 100) return;' ) !== false, true );
ok( 'a gain of zero never ships as +0',
	strpos( $code, 'return n > 0 ? "+" + n : String(n);' ) !== false, true );

echo "\nThe AI API profile carries only what a model can act on\n";
preg_match( '/function paramLines\(src, withMetrics, want\) \{(.*?)\n  \}/s', $code, $m );
$params = isset( $m[1] ) ? $m[1] : '';
ok( 'paramLines() located', strlen( $params ) > 100, true );
ok( 'shape is written only into the design document',
	strpos( $params, 'if (want.shape && src.shape && withMetrics) {' ) !== false, true );
ok( '  and a situation density likewise',
	strpos( $params, 'if (want.density && src.density != null && withMetrics) {' ) !== false, true );
ok( 'no "shape:" line can reach a provider',
	strpos( $params, '"shape: "' ) !== false, false );
ok( 'no "density:" line can reach a provider',
	strpos( $params, '"density: "' ) !== false, false );

echo "\nEach document says how often, in its own dialect\n";
ok( 'the design document names the parameter and reads it',
	strpos( $params, '"da1_gain: " + gainReading(src.gain)' ) !== false, true );
ok( '  as number = frequency: word;',
	strpos( $code, 'return gainSigned(g) + " = frequency: " + gainWord(g) + ";";' ) !== false, true );
ok( 'the AI API profile carries the word alone',
	strpos( $params, '"frequency: " + gainWord(src.gain)' ) !== false, true );

echo "\nDensity is sequence, and each document shows it its own way\n";
ok( 'the design document gives it its own line under the heading',
	strpos( $code, '"# " + label + "\\nda1_density " + formatDensity(d)' ) !== false, true );
ok( 'the AI API profile leads the heading with it',
	strpos( $code, '"# " + formatDensity(d) + " " + label' ) !== false, true );
ok( '  and aspects the same way',
	strpos( $code, 'return hashes + " " + formatDensity(d) + " " + label;' ) !== false, true );
ok( '  an aspect heading starts at ## and deepens with nesting',
	strpos( $code, '"#".repeat(Math.min(6, 2 + (Number(depth) || 0)))' ) !== false, true );
ok( '  a card inside another card leads with its composed density',
	strpos( $code, '" " + composedDensity(t.id) + " " + t.label' ) !== false, true );

echo "\nNested density: a colon for levels, a period for decimals\n";
ok( 'levels join with a colon, never a period',
	strpos( $code, 'const DENSITY_NEST = ":";' ) !== false, true );
ok( '  the member segment pads its whole part to three digits',
	strpos( $code, 'String(Math.floor(n)).padStart(3, "0")' ) !== false, true );
ok( '  and keeps its decimals, trimmed the way the root shows them',
	strpos( $code, 'dot >= 0 ? text.slice(dot) : ""' ) !== false, true );
ok( 'the compiled document says what the colon means',
	strpos( $builder, 'A colon means nesting, a period means decimals.' ) !== false, true );
ok( '  never stored — the chain is read from the parent map',
	strpos( $code, 'function densityChain(id)' ) !== false, true );
ok( '  and segments compare as numbers, not as text',
	strpos( $code, 'if (x !== y) return x - y;' ) !== false, true );
ok( 'same density sorts alphabetically',
	strpos( $code, 'return String(a.label || a.id).localeCompare(String(b.label || b.id));' ) !== false, true );

echo "\nOne card: a group is an aspect with aspects inside it\n";
ok( 'a card can be the parent of another card',
	strpos( $code, 'state.tribParent[id] = { kind: "trib", id: host };' ) !== false, true );
ok( '  placement is read back on import, so a group survives a save',
	strpos( $code, 'if (spec.placement && typeof spec.placement === "object")' ) !== false, true );
ok( '  and what to call the group is stored with the card',
	strpos( $code, 'group_noun: st.groupNoun' ) !== false, true );
ok( 'no browser prompt boxes remain in the builder',
	strpos( $code, 'window.prompt' ) !== false, false );
ok( 'no dialog opens a form inside WordPress\'s settings form',
	strpos( $markup, '<dialog' ) !== false, false );

echo "\nA situation is written, not implied\n";
ok( 'stage one opens with the situational context',
	strpos( $code, '"situational context: " + b.situation' ) !== false, true );
ok( '  a later stage with a count of it holding',
	strpos( $code, '"after: " + b.after' ) !== false, true );
ok( '  and each carries its own response',
	strpos( $code, '"response: " + b.response' ) !== false, true );
ok( 'a stage overrides only what it states',
	strpos( $code, 'if (String(value).trim() === "") delete arr[idx][field];' ) !== false, true );

echo "\nWhat the builder renders, it also saves\n";
// Situations rendered on screen and nowhere else: branches and the star's point
// count were written into the editor and left out of the workshop file, so a
// save and reload silently dropped them.
ok( 'the workshop row carries the branches',
	strpos( $code, 'branches: (st.branches || []).map(' ) !== false, true );
ok( '  and the star point count',
	strpos( $code, 'star_points: st.starPoints || null,' ) !== false, true );
ok( 'import reads the branches back',
	strpos( $code, 'branches: Array.isArray(t.branches) ? t.branches : null,' ) !== false, true );
ok( '  and the point count',
	strpos( $code, 'starPoints: t.star_points || t.starPoints || null,' ) !== false, true );

echo "\nThe shipped four use the document's headings and nothing else\n";
preg_match_all( "/'ai_base_prompt'\s*=>\s*<<<'PROMPT'\n(.*?)\nPROMPT,/s", $library, $shipped );
ok( 'four profiles found', count( $shipped[1] ), 4 );
foreach ( $shipped[1] as $body ) {
	$lines = explode( "\n", $body );
	$who   = trim( str_replace( '# DA1/FLOSC AI Personality Profile Name:', '', (string) $lines[0] ) );
	$bad   = array();
	foreach ( $lines as $i => $line ) {
		if ( 0 === $i || strpos( $line, '# ' ) !== 0 ) {
			continue;
		}
		// "# 40 Tone and Communication Style" — the density leads the heading.
		if ( ! preg_match( '/^# [0-9]+ (.+)$/', $line, $h ) || ! isset( $stations[ trim( $h[1] ) ] ) ) {
			$bad[] = $line;
		}
	}
	ok( $who . ': every heading is one of the thirteen', $bad, array() );
}

echo $fail ? "\n$fail FAILURES\n" : "\nThe document keeps the shape it ships with\n";
exit( $fail ? 1 : 0 );
