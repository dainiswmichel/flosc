<?php
/**
 * Every $this->method() a trait calls must exist on the class that uses it.
 *
 * This exists because of a fatal that was invisible in three separate
 * deployment candidates at once. The chat-turn trait called
 * flosc_enforce_no_hedge_response() on the ordinary path and again on the RAG
 * path. No class defined it. Every public visitor turn that reached those
 * lines threw, handle_chat() caught the Throwable, and the visitor read
 * "Something went wrong on our side just then."
 *
 * Nothing about that message says "undefined method". The admin ten-question
 * test passed the whole time, because it does not take the same path. So the
 * defect survived a candidate build, a code review and a live deploy, and was
 * found by a human clicking chat on the public site.
 *
 * A trait is half a class. Composing it with a class that does not supply the
 * other half is a contract violation, and PHP will not tell you until the line
 * runs. This test tells you at build time instead.
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

/**
 * Method names defined in a file, read from the token stream rather than by
 * pattern, so a name inside a comment or a string is never mistaken for a
 * definition.
 */
function flosc_defined_methods( $path ) {
	$names  = array();
	$tokens = token_get_all( (string) file_get_contents( $path ) );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) {
			continue;
		}
		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				continue;
			}
			if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				$names[ $tokens[ $j ][1] ] = true;
			}
			break;
		}
	}

	return $names;
}

/**
 * $this->name( ... ) call sites, again from the token stream. A property read
 * ($this->thing) is not a call and is not collected.
 */
function flosc_this_calls( $path ) {
	$calls  = array();
	$tokens = token_get_all( (string) file_get_contents( $path ) );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count - 2; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_VARIABLE !== $tokens[ $i ][0] || '$this' !== $tokens[ $i ][1] ) {
			continue;
		}
		if ( ! is_array( $tokens[ $i + 1 ] ) || T_OBJECT_OPERATOR !== $tokens[ $i + 1 ][0] ) {
			continue;
		}
		if ( ! is_array( $tokens[ $i + 2 ] ) || T_STRING !== $tokens[ $i + 2 ][0] ) {
			continue;
		}

		// Only a call, not a property read: the next meaningful token is "(".
		for ( $j = $i + 3; $j < $count; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				continue;
			}
			if ( '(' === $tokens[ $j ] ) {
				$calls[ $tokens[ $i + 2 ][1] ][] = $tokens[ $i ][2];
			}
			break;
		}
	}

	return $calls;
}

// The class under test and every trait it composes. Read the use statements
// rather than hard-coding them, so a trait added later is covered without
// anyone remembering to come back here.
$class_file = $root . '/flosc.php';
$trait_files = array(
	'FLOSC_REST_Trait'          => $root . '/includes/flosc-rest.php',
	'FLOSC_Admin_Trait'         => $root . '/includes/flosc-admin.php',
	'FLOSC_Visitor_Token_Trait' => $root . '/includes/tokens/class-flosc-visitor-token-trait.php',
	'FLOSC_Magic_Link_Trait'    => $root . '/includes/magic-link/class-flosc-magic-link-trait.php',
	'FLOSC_Chat_Turn_Trait'     => $root . '/includes/chat-turn/trait-flosc-chat-turn.php',
);

echo "Every trait this class composes is declared in the map above\n";
preg_match_all( '/^\s*use\s+(FLOSC_[A-Za-z_]*Trait)\s*;/m', (string) file_get_contents( $class_file ), $used );
$unmapped = array_values( array_diff( $used[1], array_keys( $trait_files ) ) );
ok( 'no trait is composed that this test does not know about', $unmapped, array() );

// Composed surface: what the class itself defines, plus what every trait
// brings. A trait method may be called from another trait; that is legal and
// must not be reported.
$surface = flosc_defined_methods( $class_file );
foreach ( $trait_files as $trait_path ) {
	if ( ! is_file( $trait_path ) ) {
		continue;
	}
	$surface += flosc_defined_methods( $trait_path );
}

echo "\nEvery \$this->method() a trait calls exists on the composed class\n";
foreach ( $trait_files as $trait_name => $trait_path ) {
	if ( ! is_file( $trait_path ) ) {
		ok( $trait_name . ': file present', false, true );
		continue;
	}

	$missing = array();
	foreach ( flosc_this_calls( $trait_path ) as $method => $lines ) {
		if ( ! isset( $surface[ $method ] ) ) {
			$missing[] = $method . '() line ' . implode( ',', $lines );
		}
	}

	ok( $trait_name, $missing, array() );
}

// The pair the visitor route dies without. Named directly, because a generic
// pass would still be green if someone deleted both the call and the method
// and left the guard behind.
echo "\nThe reputation guard the public chat path calls is owned\n";
$class_src = (string) file_get_contents( $class_file );
ok( 'flosc_enforce_no_hedge_response() is defined',
	isset( $surface['flosc_enforce_no_hedge_response'] ), true );
ok( 'flosc_contains_forbidden_hedge() is defined',
	isset( $surface['flosc_contains_forbidden_hedge'] ), true );
ok( '  and it can still build a replacement to return',
	isset( $surface['flosc_build_professional_replacement'] ), true );

// A guard is not a substitute for the method, but it is what keeps a future
// tree from fataling if someone removes one. Both call sites carry it.
$turn_src = (string) file_get_contents( $trait_files['FLOSC_Chat_Turn_Trait'] );
ok( 'both call sites are guarded with method_exists',
	substr_count( $turn_src, "method_exists(\$this, 'flosc_enforce_no_hedge_response')" ), 2 );

echo $fail ? "\n$fail FAILURES\n" : "\nEvery trait call resolves on the class that uses it\n";
exit( $fail ? 1 : 0 );
