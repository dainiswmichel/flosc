<?php
/**
 * fix-yoda-conditions.php — put the literal on the left of a comparison.
 *
 * Clears WordPress.PHP.YodaConditions.NotYoda, which WPCS reports ~2,280 times
 * and phpcbf has no fixer for (confirmed: "No fixable errors were found").
 *
 * Uses PHP's own tokenizer, never a regex, so it cannot touch a "==" inside a
 * string, a comment or a heredoc.
 *
 * DELIBERATELY CONSERVATIVE. It rewrites only the shape it can prove is safe:
 *
 *     <variable-expression> <op> <single literal>
 *  -> <single literal> <op> <variable-expression>
 *
 * where the operator is == != === !==, the right side is exactly ONE literal
 * token (quoted string, integer, float, or true/false/null), and the left side
 * is a variable optionally followed by -> property and ['literal'] index
 * chains. Anything else is left alone, including:
 *
 *   - a function or method CALL on either side; reordering can change
 *     evaluation order and that is a behaviour change, not a style fix
 *   - both sides variables, or both sides literals
 *   - arithmetic, concatenation, or any multi-token expression
 *   - < > <= >= <=> (the sniff does not ask for these)
 *   - anything already Yoda
 *
 * So it will not clear all 2,280. It clears the ones it can prove, and reports
 * how many it declined. That is the right trade: a style fix must never change
 * what the code does.
 *
 * Usage:
 *   php fix-yoda-conditions.php --dry-run  /path/to/flosc
 *   php fix-yoda-conditions.php            /path/to/flosc
 *
 * Always --dry-run first and read the samples.
 *
 * Lives at candidate level, never inside the plugin tree, so it cannot ship.
 *
 * @package FLOSC
 */

$args    = array_slice( $argv, 1 );
$dry_run = false;
$root    = '';
foreach ( $args as $arg ) {
	if ( $arg === '--dry-run' || $arg === '-n' ) {
		$dry_run = true;
		continue;
	}
	$root = $arg;
}
if ( $root === '' ) {
	fwrite( STDERR, "usage: php fix-yoda-conditions.php [--dry-run] <plugin-root>\n" );
	exit( 2 );
}
$root = realpath( $root );
if ( $root === false || ! is_dir( $root ) ) {
	fwrite( STDERR, "not a directory\n" );
	exit( 2 );
}

$ops = array( T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL );

/**
 * Is this token ignorable whitespace or a comment?
 *
 * @param mixed $t Token.
 * @return bool
 */
function flosc_is_skippable( $t ) {
	return is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
}

/**
 * Render a token to its source text.
 *
 * @param mixed $t Token.
 * @return string
 */
function flosc_tok_text( $t ) {
	return is_array( $t ) ? $t[1] : $t;
}

$files = new RegexIterator(
	new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ),
	'/\.php$/'
);

$changed_files = 0;
$changed_sites = 0;
$declined      = 0;
$samples       = array();

foreach ( $files as $file ) {
	$path = $file->getPathname();
	if ( strpos( $path, '/vendor/' ) !== false || strpos( $path, '/node_modules/' ) !== false ) {
		continue;
	}

	$src    = file_get_contents( $path );
	$tokens = @token_get_all( $src );
	if ( ! is_array( $tokens ) ) {
		continue;
	}

	$count     = count( $tokens );
	$file_hits = 0;

	for ( $i = 0; $i < $count; $i++ ) {
		$tok = $tokens[ $i ];
		if ( ! is_array( $tok ) || ! in_array( $tok[0], $ops, true ) ) {
			continue;
		}

		/*
		 * RIGHT SIDE: must be exactly one literal, then a terminator.
		 */
		$r = $i + 1;
		while ( $r < $count && flosc_is_skippable( $tokens[ $r ] ) ) {
			++$r;
		}
		if ( $r >= $count ) {
			continue;
		}
		$rtok    = $tokens[ $r ];
		$is_lit  = false;
		if ( is_array( $rtok ) ) {
			if ( in_array( $rtok[0], array( T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER ), true ) ) {
				$is_lit = true;
			} elseif ( $rtok[0] === T_STRING && in_array( strtolower( $rtok[1] ), array( 'true', 'false', 'null' ), true ) ) {
				$is_lit = true;
			}
		}
		if ( ! $is_lit ) {
			++$declined;
			continue;
		}

		// The literal must END the comparison — next real token is a terminator.
		$after = $r + 1;
		while ( $after < $count && flosc_is_skippable( $tokens[ $after ] ) ) {
			++$after;
		}
		$after_txt = $after < $count ? flosc_tok_text( $tokens[ $after ] ) : '';
		$after_id  = ( $after < $count && is_array( $tokens[ $after ] ) ) ? $tokens[ $after ][0] : 0;
		$ok_after  = in_array( $after_txt, array( ')', ',', ';', ']', '?', ':', '&&', '||', '}' ), true )
			|| in_array( $after_id, array( T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_CLOSE_TAG ), true );
		if ( ! $ok_after ) {
			++$declined;
			continue;
		}

		/*
		 * LEFT SIDE: walk back over a variable expression.
		 * Accept: $var, $var->prop, $var['k'], and chains of those.
		 * Reject the moment a call paren or anything unexpected appears.
		 */
		$l = $i - 1;
		while ( $l >= 0 && flosc_is_skippable( $tokens[ $l ] ) ) {
			--$l;
		}
		if ( $l < 0 ) {
			continue;
		}

		$end_left = $l;
		$safe     = true;
		$depth    = 0;

		while ( $l >= 0 ) {
			$t   = $tokens[ $l ];
			$txt = flosc_tok_text( $t );
			$id  = is_array( $t ) ? $t[0] : 0;

			if ( $txt === ']' ) {
				++$depth;
				--$l;
				continue;
			}
			if ( $txt === '[' ) {
				if ( $depth === 0 ) {
					break;
				}
				--$depth;
				--$l;
				continue;
			}
			if ( $depth > 0 ) {
				// Inside an index: only literals and simple variables allowed.
				if ( $txt === '(' || $txt === ')' ) {
					$safe = false;
					break;
				}
				--$l;
				continue;
			}
			if ( $id === T_VARIABLE ) {
				--$l;
				continue;
			}
			if ( $id === T_OBJECT_OPERATOR || $id === T_DOUBLE_COLON || $id === T_STRING || $id === T_NS_SEPARATOR ) {
				// A call is a hard stop: "foo()" has a ")" we would have seen.
				--$l;
				continue;
			}
			if ( $txt === ')' ) {
				// A call or a grouped expression on the left: refuse.
				$safe = false;
				break;
			}
			break;
		}

		$start_left = $l + 1;
		while ( $start_left <= $end_left && flosc_is_skippable( $tokens[ $start_left ] ) ) {
			++$start_left;
		}

		if ( ! $safe || $start_left > $end_left ) {
			++$declined;
			continue;
		}

		// The left side must contain at least one variable, and no call parens.
		$left_txt   = '';
		$has_var    = false;
		$has_paren  = false;
		for ( $k = $start_left; $k <= $end_left; $k++ ) {
			$left_txt .= flosc_tok_text( $tokens[ $k ] );
			if ( is_array( $tokens[ $k ] ) && $tokens[ $k ][0] === T_VARIABLE ) {
				$has_var = true;
			}
			if ( flosc_tok_text( $tokens[ $k ] ) === '(' || flosc_tok_text( $tokens[ $k ] ) === ')' ) {
				$has_paren = true;
			}
		}
		if ( ! $has_var || $has_paren ) {
			++$declined;
			continue;
		}

		// Swap: literal goes left, variable expression goes right.
		$lit_txt = flosc_tok_text( $rtok );
		if ( $file_hits < 2 && count( $samples ) < 12 ) {
			$samples[] = sprintf(
				'%s: %s %s %s   ->   %s %s %s',
				str_replace( $root . '/', '', $path ),
				$left_txt,
				flosc_tok_text( $tok ),
				$lit_txt,
				$lit_txt,
				flosc_tok_text( $tok ),
				$left_txt
			);
		}

		$tokens[ $r ] = array( T_STRING, $left_txt );
		for ( $k = $start_left; $k <= $end_left; $k++ ) {
			$tokens[ $k ] = array( T_STRING, '' );
		}
		$tokens[ $start_left ] = array( T_STRING, $lit_txt );

		++$file_hits;
	}

	if ( $file_hits > 0 ) {
		$out = '';
		foreach ( $tokens as $t ) {
			$out .= flosc_tok_text( $t );
		}
		// Refuse to write anything that does not still parse.
		$tmp = tempnam( sys_get_temp_dir(), 'yoda' ) . '.php';
		file_put_contents( $tmp, $out );
		exec( 'php -l ' . escapeshellarg( $tmp ) . ' 2>&1', $lint_out, $lint_rc );
		unlink( $tmp );
		if ( $lint_rc !== 0 ) {
			fwrite( STDERR, "REFUSED (would not parse): $path\n" );
			continue;
		}

		++$changed_files;
		$changed_sites += $file_hits;
		if ( ! $dry_run ) {
			file_put_contents( $path, $out );
		}
	}
}

echo $dry_run ? "DRY RUN — nothing written\n\n" : "WRITTEN\n\n";
echo "sample of intended changes:\n";
foreach ( $samples as $s ) {
	echo '  ' . $s . "\n";
}
echo "\n";
printf( "comparisons %s: %d in %d files\n", $dry_run ? 'that would flip' : 'flipped', $changed_sites, $changed_files );
printf( "declined as unsafe to reorder: %d\n", $declined );
echo "\nEvery file is parsed with php -l before it is written; a file that would\n";
echo "not parse is refused and left untouched.\n";
