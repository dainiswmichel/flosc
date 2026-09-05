<?php
/**
 * No function in the profile-building chain may call itself back.
 *
 * The builder hung on a live site the day the provenance footer shipped.
 * profileFooter() read the builder's own state hash back off fullSpec(),
 * and the new provenance block in workshopFile() called profileFooter()'s
 * row builder — closing a loop:
 *
 *     fullSpec() -> workshopFile() -> provenanceRows() -> fullSpec()
 *
 * Every turn of that loop rebuilt the entire workshop object, so the page
 * became unresponsive long before the stack overflowed. There was no error
 * in the console and no failing test: `node --check` parses it happily, and
 * a grep sees five ordinary calls.
 *
 * What made it invisible to the check that should have caught it: the footer
 * WAS rendered and read before shipping — but rendered against a stubbed
 * fullSpec(). The stub was the bug's hiding place.
 *
 * So this is structural rather than textual. It parses the builder's own
 * function declarations, builds a call graph, and walks it for any cycle
 * among the functions that produce a profile, a workshop file or a footer.
 *
 * CLI only.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

/**
 * Report one assertion.
 *
 * @param string $label What is being asserted.
 * @param mixed  $got   Actual value.
 * @param mixed  $want  Expected value.
 * @return void
 */
function ok( $label, $got, $want = true ) {
	global $fail;
	$pass = ( $got === $want );
	if ( ! $pass ) {
		++$fail;
	}
	echo ( $pass ? 'ok   ' : 'FAIL ' ) . str_pad( $label, 58 ) . ' ' . var_export( $got, true );
	echo $pass ? "\n" : ' (want ' . var_export( $want, true ) . ")\n";
}

$src = (string) file_get_contents( $root . '/assets/js/flosc-personality-builder.js' );

/**
 * Body of every top-level function in the builder, by name.
 *
 * Brace-matched rather than regexed to a closing line: a function containing
 * an object literal would end early under a lazy pattern, and half a body is
 * a graph with edges missing.
 *
 * @param string $src JavaScript source.
 * @return array<string,string>
 */
function flosc_builder_function_bodies( $src ) {
	$bodies = array();
	if ( ! preg_match_all( '/^  function ([A-Za-z_$][\w$]*)\s*\(/m', $src, $found, PREG_OFFSET_CAPTURE ) ) {
		return $bodies;
	}

	foreach ( $found[1] as $index => $match ) {
		$name  = $match[0];
		$start = strpos( $src, '{', $found[0][ $index ][1] );
		if ( $start === false ) {
			continue;
		}
		$depth = 0;
		$len   = strlen( $src );
		for ( $i = $start; $i < $len; $i++ ) {
			if ( $src[ $i ] === '{' ) {
				++$depth;
			} elseif ( $src[ $i ] === '}' ) {
				--$depth;
				if ( $depth === 0 ) {
					$bodies[ $name ] = substr( $src, $start, $i - $start + 1 );
					break;
				}
			}
		}
	}

	return $bodies;
}

/**
 * Strip comments, so a note describing a call is not read as one.
 *
 * The first version of this check reported the very comment written to
 * explain the cycle it had just found.
 *
 * @param string $body Function body.
 * @return string
 */
function flosc_builder_strip_comments( $body ) {
	$body = preg_replace( '#/\*.*?\*/#s', '', $body );
	return preg_replace( '#//[^\n]*#', '', (string) $body );
}

$bodies = flosc_builder_function_bodies( $src );
echo "The builder's own functions parse\n";
ok( 'more than a hundred of them', count( $bodies ) > 100, true );

// The chain that turns builder state into a document. A cycle anywhere in it
// hangs the page on load, because renderOut() enters it on every redraw.
$chain = array(
	'provenanceRows',
	'profileFooter',
	'workshopFile',
	'fullSpec',
	'compilePrompt',
	'promptFile',
	'designFile',
	'providerPacks',
	'libraryEntry',
	'fileBase',
	'builderLine',
);

$present = array_values( array_intersect( $chain, array_keys( $bodies ) ) );
ok( 'every profile builder was found', count( $present ), count( $chain ) );

$graph = array();
foreach ( $present as $name ) {
	$body           = flosc_builder_strip_comments( $bodies[ $name ] );
	$graph[ $name ] = array();
	foreach ( $present as $other ) {
		if ( $other === $name ) {
			continue;
		}
		if ( preg_match( '/\b' . preg_quote( $other, '/' ) . '\s*\(/', $body ) ) {
			$graph[ $name ][] = $other;
		}
	}
}

$cycles = array();

/**
 * Walk the call graph looking for a way back to where the walk began.
 *
 * @param string   $start Function the walk began at.
 * @param string   $node  Function being expanded.
 * @param string[] $path  Route taken so far.
 * @param array    $seen  Functions already on this route.
 * @param array    $graph Call graph.
 * @param array    $out   Collected cycles, by reference.
 * @return void
 */
function flosc_walk_calls( $start, $node, $path, $seen, $graph, &$out ) {
	foreach ( $graph[ $node ] as $next ) {
		if ( $next === $start ) {
			$out[] = implode( ' -> ', array_merge( $path, array( $next ) ) );
			continue;
		}
		if ( isset( $seen[ $next ] ) ) {
			continue;
		}
		$seen[ $next ] = true;
		flosc_walk_calls( $start, $next, array_merge( $path, array( $next ) ), $seen, $graph, $out );
	}
}

foreach ( $present as $name ) {
	flosc_walk_calls( $name, $name, array( $name ), array( $name => true ), $graph, $cycles );
}

echo "\nNothing in the profile chain calls itself back\n";
ok( 'no cycle among the profile builders', array_values( array_unique( $cycles ) ), array() );

// The specific edge that caused it. provenanceRows() is called BY both of
// these, so it can never call either.
echo "\nThe row builder stays a leaf\n";
$rows_body = isset( $bodies['provenanceRows'] ) ? flosc_builder_strip_comments( $bodies['provenanceRows'] ) : '';
ok( 'it does not call fullSpec()', strpos( $rows_body, 'fullSpec(' ) !== false, false );
ok( 'it does not call workshopFile()', strpos( $rows_body, 'workshopFile(' ) !== false, false );
ok( 'it computes the state hash itself',
	strpos( $rows_body, 'hashText(compilePrompt())' ) !== false, true );

echo $fail ? "\n$fail FAILURES\n" : "\nThe builder cannot hang itself on a redraw\n";
exit( $fail ? 1 : 0 );
