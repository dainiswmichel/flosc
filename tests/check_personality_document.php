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

/*
 * Comments are stripped before any search. Two earlier gates in this suite
 * matched their own explanatory comment and reported it as the defect.
 */
$code = preg_replace( '#/\*.*?\*/#s', '', $builder );
$code = (string) preg_replace( '#(^|\s)//.*$#m', '$1', (string) $code );

echo "The eleven stations, in density order\n";
$stations = array(
	'Name and Core Role'                   => 6,
	'Philosophy and Values'                => 12,
	'Hard Boundaries and Prohibitions'     => 18,
	'Knowledge, Doubt and Correction'      => 24,
	'Tone and Communication Style'         => 40,
	'Stance Toward the Human'              => 48,
	'Behavior in Ambiguity'                => 56,
	'Adaptation'                           => 62,
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
ok( 'eleven and no more', substr_count( $layers, 'label: "' ), 11 );

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
	strpos( $code, '"## " + formatDensity(d) + " " + label' ) !== false, true );

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

echo "\nThe shipped four use station headings and nothing else\n";
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
	ok( $who . ': every heading is one of the eleven', $bad, array() );
}

echo $fail ? "\n$fail FAILURES\n" : "\nThe document keeps the shape it ships with\n";
exit( $fail ? 1 : 0 );
