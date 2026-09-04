<?php
/**
 * A compiled personality contains what the floscAdmin wrote. Nothing else.
 *
 * The builder was leaking its own vocabulary into the artifact. A profile
 * saved with an empty name opened "You are [name]. [role]". A section with
 * nothing in it announced "(no provider parameters set)". An unnamed group
 * printed "# Untitled cloud". None of that is character, and a model reading a
 * personality profile has no way to tell builder scaffolding from instruction.
 *
 * Influences were worse than scaffolding: they were sent and then silenced.
 * "NOT ACTIVE PERSONALITY. Do not treat as rules…" cost input tokens on every
 * single turn to tell the model to ignore text that had just been paid for.
 * The rule now is simple — nothing silenced goes into a personality. Included
 * because the floscAdmin ticked the box means included as character, under a
 * heading the character owns. Unticked means not compiled in at all.
 *
 * "Comments" was the builder's word for them. A profile that says "## Comments"
 * is showing its scaffolding.
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

$builder = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder.js' );
$markup  = (string) file_get_contents( $root . '/assets/personality-builder/flosc-personality-builder-markup.php' );
$library = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );

// The compiler only. What the rest of the file says about these strings in a
// comment or a form label is not what reaches a provider.
preg_match( '/function compilePrompt\(withMetrics\) \{(.*?)\n  \}/s', $builder, $m );
$compile = isset( $m[1] ) ? $m[1] : '';
$compile = preg_replace( '#/\*.*?\*/#s', '', $compile );
$compile = preg_replace( '#(^|\s)//.*$#m', '$1', (string) $compile );

echo "The compiler was found\n";
ok( 'compilePrompt() body located', strlen( (string) $compile ) > 500, true );

echo "\nNo builder scaffolding can reach a compiled profile\n";
$scaffolding = array(
	'[name]'                        => 'a placeholder name',
	'[role]'                        => 'a placeholder role',
	'(no provider parameters set)'  => 'a status message about an empty section',
	'Untitled cloud'                => 'a placeholder heading',
	'NOT ACTIVE PERSONALITY'        => 'text sent and then disowned',
	'## Comments'                   => "the builder's own word for influences",
);
foreach ( $scaffolding as $needle => $what ) {
	ok( 'no ' . $what, strpos( (string) $compile, $needle ) !== false, false );
}

echo "\nAn empty field produces no line, rather than a placeholder\n";
ok( 'the identity line is written only from what exists',
	strpos( (string) $compile, 'if (s.name && s.role) {' ) !== false, true );
ok( 'an empty parameter section is dropped, heading and all',
	strpos( (string) $compile, 'if (!set.length) { out.pop(); return; }' ) !== false, true );
ok( 'an unnamed group keeps its members and loses its heading',
	strpos( (string) $compile, 'if (cl.name) out.push("# " + cl.name);' ) !== false, true );

echo "\nIncluded influences are character, not disowned text\n";
ok( 'they compile under a heading the character owns',
	strpos( (string) $compile, 'out.push("# Influences' ) !== false, true );
ok( 'and only when the floscAdmin asked for them',
	strpos( (string) $compile, 'if (state.includeComments) {' ) !== false, true );
ok( 'the control says what including them means',
	strpos( $markup, 'they are part of the personality like anything else here' ) !== false, true );
ok( '  and what leaving them out means',
	strpos( $markup, 'are never sent' ) !== false, true );

// The footer explains the file to whoever finds it. It must never be part of
// what is saved to the library or sent to a provider.
echo "\nThe provenance footer travels with downloads only\n";
ok( 'soul.md downloads carry it',
	strpos( $builder, 'return compilePrompt() + "\n\n" + profileFooter();' ) !== false, true );
ok( 'the design copy carries it',
	strpos( $builder, 'compilePrompt(true) + "\n\n" + profileFooter()' ) !== false, true );
ok( 'and compilePrompt() itself does not',
	strpos( (string) $compile, 'profileFooter' ) !== false, false );

echo "\nThe footer says how to read the file and where it came from\n";
foreach ( array(
	'ordered by density'   => 'the ordering is stated',
	'not part of the'      => 'it says it is not the personality',
	'https://da1.fm'       => 'the builder is reachable',
	'https://flosc.ai'     => 'so is the framework',
	'builder_version'      => 'the builder version is named',
	'exported'             => 'and when the file was written',
) as $needle => $what ) {
	ok( $what, strpos( $builder, $needle ) !== false, true );
}

echo "\nEdition is a label beside the version, never part of it\n";
ok( 'the builder version is a plain number',
	(bool) preg_match( "/'version' => defined\( 'FLOSC_DA1_BUILDER_VERSION' \)/", $library ), true );
ok( "  and it is a comparable one",
	(bool) preg_match( "/define\('FLOSC_DA1_BUILDER_VERSION', '\d+\.\d+\.\d+'\);/", (string) file_get_contents( $root . '/flosc.php' ) ), true );
ok( 'the edition is its own field',
	strpos( $library, "'edition' => 'FLOSC'," ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nA profile carries the character and nothing about the tool\n";
exit( $fail ? 1 : 0 );
