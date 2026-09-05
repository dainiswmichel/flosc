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
// The shipped four all open the same way. A profile built here has to be the
// same kind of document, not a near-relative — and by the density rule the top
// of the document is the most privileged position in it.
echo "\nA built profile opens the way the shipped ones do\n";
ok( 'it carries the # Personality profile heading',
	strpos( (string) $compile, 'out.push("# DA1/FLOSC AI Personality Profile Name: " + s.name);' ) !== false, true );
ok( '  then the identity line as prose',
	strpos( (string) $compile, '"You are " + s.name + ", "' ) !== false, true );
ok( '  then the speak-as line',
	strpos( (string) $compile, 'out.push("Speak as this person. Do not discuss how you were made.");' ) !== false, true );

$library = (string) file_get_contents( $root . '/includes/flosc-personality-library.php' );
preg_match_all( "/'ai_base_prompt'\s*=>\s*<<<'PROMPT'\n(.*?)\nPROMPT,/s", $library, $shipped );
foreach ( $shipped[1] as $body ) {
	$lines = explode( "\n", $body );
	$who   = isset( $lines[0] ) ? trim( str_replace( '# Personality profile:', '', $lines[0] ) ) : '?';
	ok( $who . ': opens with the heading',
		strpos( (string) ( $lines[0] ?? '' ), '# DA1/FLOSC AI Personality Profile Name: ' ) === 0, true );
	ok( '  and its third line is the speak-as line',
		trim( (string) ( $lines[2] ?? '' ) ), 'Speak as this person. Do not discuss how you were made.' );
}

// The framing line is for a file that has left FLOSC. Inside it, the chatpack
// already frames the profile, and none of the shipped four carry it.
// state.soul.name was read in six places and assigned in none, so a personality
// could be redesigned station by station and never renamed. The role is half of
// the second line of every profile and had the same problem.
echo "\nA personality can be renamed\n";
$markup = (string) file_get_contents( $root . '/assets/personality-builder/flosc-personality-builder-markup.php' );
$bridge = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder-wp.js' );

ok( 'there is a name field',
	strpos( $markup, 'id="soulName"' ) !== false, true );
ok( '  and a role field',
	strpos( $markup, 'id="soulRole"' ) !== false, true );
ok( 'typing a name writes it to the state',
	strpos( $builder, 'state.soul.name = this.value;' ) !== false, true );
ok( '  and the label follows it, so the dropdown cannot disagree',
	strpos( $builder, 'state.soul.label = this.value;' ) !== false, true );
ok( 'typing a role writes it too',
	strpos( $builder, 'state.soul.role = this.value;' ) !== false, true );
ok( 'both are reflected back without stealing the caret',
	substr_count( $builder, 'document.activeElement !== nameIn' )
	+ substr_count( $builder, 'document.activeElement !== roleIn' ), 2 );

echo "\nAnd the rename survives the round trip\n";
ok( 'the save sends the name',
	strpos( $bridge, 'body.append("ai_personality_name", bits.name);' ) !== false, true );
ok( '  the role',
	strpos( $bridge, 'body.append("ai_personality_role", bits.role);' ) !== false, true );
ok( '  and the label',
	strpos( $bridge, 'body.append("label", bits.label);' ) !== false, true );
foreach ( array( 'label', 'ai_personality_name', 'ai_personality_role' ) as $field ) {
	ok( '  the server accepts ' . $field,
		strpos( $library, "\$fields['" . $field . "'] = sanitize_text_field( wp_unslash(" ) !== false, true );
}

// The id is the key a floscFlow attaches by. Renaming must never move it, or
// every flow pointing at that personality loses it.
ok( 'and the id is never rewritten from the name',
	(bool) preg_match( '/state\.soul\.id = this\.value/', $builder ), false );

echo "\nThe portability line is on the download, not in what is stored\n";
ok( 'compilePrompt() does not emit it',
	strpos( (string) $compile, 'If you are an AI reading this' ) !== false, false );
ok( 'the downloaded file does',
	strpos( $builder, 'const portable = "This is a personality profile.' ) !== false, true );

// Provenance was only ever inside a downloaded file, so a profile on screen
// could not say which build made it, which edit it was, or when it was written.
echo "\nProvenance is visible where the profile is\n";
ok( 'the chips carry it',
	strpos( $builder, 'provenanceChips();' ) !== false, true );
foreach ( array(
	'b.edition ? b.edition + " edition" : ""' => 'builder, edition and version',
	'>profile v\' + esc(String(e.version))'   => 'which edit this is',
	'>sha256 \' + esc(String(e.hash).slice(0, 12))' => 'the deployment fingerprint',
	'>saved \' + esc(String(e.modifiedGmt))'  => 'when it was last written',
) as $needle => $what ) {
	ok( '  ' . $what, strpos( $builder, $needle ) !== false, true );
}

echo "\nThe provenance footer travels with downloads only\n";
ok( 'soul.md downloads carry it',
	(bool) preg_match( '/function promptFile\(\) \{.*?profileFooter\(\)/s', $builder ), true );
// The design copy puts the block at the TOP: that file is explicitly for a
// human reading the design, and a human wants the key before the material.
ok( 'the design copy carries it, at the top',
	strpos( $builder, 'return profileFooter() + "\n\n" + compilePrompt(true);' ) !== false, true );
ok( 'and compilePrompt() itself does not',
	strpos( (string) $compile, 'profileFooter' ) !== false, false );

echo "\nThe footer says how to read the file and where it came from\n";
foreach ( array(
	'from lightest and most essential' => 'the ordering is stated',
	'never sent to a model and never billed' => 'it says it is not the personality',
	'https://da1.fm'       => 'the builder is reachable',
	'https://flosc.ai'     => 'so is the framework',
	'builder_version'      => 'the builder version is named',
	'exported'             => 'and when the file was written',
) as $needle => $what ) {
	ok( $what, strpos( $builder, $needle ) !== false, true );
}

// Five sources write the first line of a profile. A built personality and a
// shipped one have to be the same kind of document at the top, which by the
// density rule is the most privileged position in it.
echo "\nOne title line, in every source that writes one\n";
$title = '# DA1/FLOSC AI Personality Profile Name: ';
ok( 'the compiler writes it',
	strpos( $builder, 'out.push("' . $title . '" + s.name);' ) !== false, true );
ok( '  and all four shipped profiles open with it',
	substr_count( $library, $title ), 4 );
// The travel copy injects the AI-reader paragraph after the heading by
// matching it. A heading the pattern no longer matches does not error — the
// paragraph silently moves to the top of the file instead.
ok( '  and the download injector still matches it',
	strpos( $builder, '/^(# DA1\/FLOSC AI Personality Profile Name: [^\n]*\n)/' ) !== false, true );

// Five exports used to name themselves five different ways, including one
// with two dots in it.
echo "\nEvery download is named the same way\n";
ok( 'one helper builds the stem',
	strpos( $builder, 'function fileBase()' ) !== false, true );
ok( '  used by all five downloads', substr_count( $builder, 'downloadBlob(fileBase() + "' ), 5 );
ok( '  and no export builds its own name',
	(bool) preg_match( '/downloadBlob\(\s*id \+/', $builder ), false );
$multi_dot = array();
foreach ( array( '_soul.md', '_soul_design.md', '_workshop.json', '_preview.html', '_provider_packs.json' ) as $suffix ) {
	if ( strpos( $builder, '"' . $suffix . '"' ) === false ) {
		$multi_dot[] = $suffix . ' missing';
	}
	if ( substr_count( $suffix, '.' ) !== 1 ) {
		$multi_dot[] = $suffix . ' has more than one dot';
	}
}
ok( '  one dot per filename, before the extension', $multi_dot, array() );

// "Copy this file" sits in the Export row, so it is for sending a personality
// somewhere — and a personality that arrives somewhere with nothing saying
// what made it is the thing the footer exists to prevent. It used to copy the
// panel, which shows the runtime profile and carries no footer.
// The four output views are four different documents and the panel said which
// only by the tab it was on. That matters most on the profile tab, which shows
// the runtime profile and deliberately carries no footer — the absence read as
// something missing rather than as a decision. A floscAdmin deciding what
// reaches their AI provider has to be able to see which of these does.
echo "\nThe panel says what it is showing\n";
ok( 'there is a line for each of the four views',
	count( array_filter( array( 'prompt:', 'spec:', 'lint:', 'providers:' ), function ( $key ) use ( $builder ) {
		return strpos( $builder, "\n    " . $key . ' "' ) !== false;
	} ) ), 4 );
ok( '  and it is redrawn with the panel',
	strpos( $builder, 'viewNote.textContent = OUT_VIEW_NOTES[state.outTab]' ) !== false, true );
ok( '  with somewhere to put it',
	strpos( $markup, 'id="outViewNote"' ) !== false, true );
// The two facts a floscAdmin needs from the profile view.
ok( 'the profile view says it is sent every turn',
	strpos( $builder, 'sent to your AI provider on every turn' ) !== false, true );
ok( '  and says where the footer went instead',
	strpos( $builder, 'Download soul.md and Copy this file carry it' ) !== false, true );

echo "\nCopying the file copies the file, not the panel\n";
ok( 'the profile tab copies the travelling copy',
	strpos( $builder, 'const text = (state.outTab === "prompt")' ) !== false, true );
ok( '  which is the same bytes as the soul.md download',
	substr_count( $builder, 'promptFile()' ) >= 3, true );
// Builder state and Validation show something else; copying the panel is right
// there.
ok( '  and the other tabs still copy what they show',
	strpos( $builder, 'pane && pane.textContent ? pane.textContent : promptFile()' ) !== false, true );

echo "\nThe footer and the workshop file cannot disagree\n";
// Both are built from provenanceRows(), so a field added to one is in the
// other. Two hand-maintained lists would drift the first time one was edited.
ok( 'both read the same rows',
	substr_count( $builder, 'provenanceRows()' ) >= 3, true );
ok( 'the two hashes are named apart',
	strpos( $builder, '"builder_state_hash"' ) !== false && strpos( $builder, '"profile_hash"' ) !== false, true );
ok( 'the workshop format is unchanged',
	strpos( $builder, 'format: "flosc_workshop/2"' ) !== false, true );
ok( 'the chain from file to provider is stated',
	strpos( $builder, 'the turns it produced' ) !== false, true );

echo "\nA download names this site only when asked to\n";
ok( 'off by default', strpos( $builder, 'include_source_site: false' ) !== false, true );
ok( '  and written only under the flag',
	strpos( $builder, 'if (state.include_source_site && wp.siteHost)' ) !== false, true );
ok( '  with a control to turn it on',
	strpos( $markup, 'id="includeSourceSite"' ) !== false, true );

echo "\nThe trajectory wellsprings carry a placeholder, not a URL\n";
preg_match_all( '/trajectoryAspect\(\s*"([a-z_]+)"/', $builder, $traj );
ok( 'seven of them', count( $traj[1] ), 7 );
// A personality shipping with a plausible-looking URL gets sent to visitors
// with that URL in it.
preg_match_all( '/trajectoryAspect\((.*?)\n\n/s', $builder, $traj_bodies );
$hardcoded = array();
foreach ( $traj_bodies[1] as $body ) {
	if ( preg_match( '#https?://#', $body ) ) {
		$hardcoded[] = trim( substr( $body, 0, 40 ) );
	}
}
ok( '  and none of them names a real address', $hardcoded, array() );

// A version printed into an exported artefact is exactly the kind that goes
// stale unnoticed: the preview claimed "v33" long after that was true.
echo "\nThe preview names the real builder version\n";
ok( 'read from the boot data, not typed in',
	strpos( $builder, 'function builderLine()' ) !== false, true );
ok( '  and no hardcoded version remains',
	strpos( $builder, 'FLOSC Personality Builder v33' ) !== false, false );

echo "\nEdition is a label beside the version, never part of it\n";
ok( 'the builder version is a plain number',
	(bool) preg_match( "/'version' => defined\( 'FLOSC_DA1_BUILDER_VERSION' \)/", $library ), true );
ok( "  and it is a comparable one",
	(bool) preg_match( "/define\('FLOSC_DA1_BUILDER_VERSION', '\d+\.\d+\.\d+'\);/", (string) file_get_contents( $root . '/flosc.php' ) ), true );
ok( 'the edition is its own field',
	strpos( $library, "'edition' => 'FLOSC'," ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nA profile carries the character and nothing about the tool\n";
exit( $fail ? 1 : 0 );
