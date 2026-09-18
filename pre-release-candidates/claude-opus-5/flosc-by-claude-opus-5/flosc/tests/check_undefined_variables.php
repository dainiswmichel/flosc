<?php
/**
 * No file reads a variable that nothing ever assigns.
 *
 * This is the check that would have caught two live bugs shipped in this tree,
 * both created the same way: a pass that added the flosc_ prefix renamed a
 * variable where it was assigned and missed where it was read.
 *
 *   admin/flow-edit.php    the Quiz Type dropdown looped over $flosc_type_id
 *                          and printed $type_id, so every option rendered with
 *                          an empty value and an empty label.
 *   admin/ai-feedback.php  four update_option() calls passed $settings_key,
 *                          which nothing assigns. settings.php, which includes
 *                          that file, calls it $flosc_settings_key. Every AI
 *                          feedback and praise save was lost.
 *
 * Neither is a syntax error, neither trips a coding-standards sniff, and neither
 * shows up until someone opens the screen. PHP 8 emits a warning and carries on
 * with null.
 *
 * HOW IT WORKS
 *
 * Token-based, never regex: a regex cannot tell a variable in code from the
 * same characters inside a string or a comment.
 *
 * For each file it collects every name that is WRITTEN -- assignments, foreach
 * bindings, function parameters, global and static declarations, catch blocks,
 * list() destructuring, reference arguments to preg_match() and its relatives --
 * and every name that is READ. A read with no write anywhere is reported.
 *
 * An included template shares its includer's scope, so the includer's writes
 * count too. The include graph is built from literal require/include paths and
 * followed transitively.
 *
 * DELIBERATELY CONSERVATIVE. Superglobals, $this, WordPress globals and any
 * name written anywhere in the file's own scope chain are accepted. A variable
 * variable ($$name) or an extract() call makes a file unanalysable, and such a
 * file is skipped and reported as skipped rather than guessed at.
 *
 * Exit 0 when every read resolves, 1 with a list otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$flosc_root = dirname( __DIR__ );

/**
 * Names that are always available and are never assigned in plugin code.
 *
 * @var string[]
 */
$flosc_always_defined = array(
	'this',
	'GLOBALS',
	'_SERVER',
	'_GET',
	'_POST',
	'_FILES',
	'_COOKIE',
	'_SESSION',
	'_REQUEST',
	'_ENV',
	'http_response_header',
	'argc',
	'argv',
	// WordPress globals, reachable without a global statement in template scope.
	'wpdb',
	'wp_query',
	'post',
	'wp_filesystem',
	'wp_version',
	'pagenow',
	'typenow',
	'current_screen',
	'wp_scripts',
	'wp_styles',
	'wp_rewrite',
	'wp',
	'authordata',
	'shortcode_tags',
	'menu',
	'submenu',
);

/**
 * Every PHP file that ships, relative to the plugin root.
 *
 * @param string $root Plugin root.
 * @return string[] Relative paths.
 */
function flosc_uv_files( $root ) {
	$found    = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		$path = $file->getPathname();
		if ( substr( $path, -4 ) !== '.php' ) {
			continue;
		}
		$rel = ltrim( str_replace( $root, '', $path ), '/' );
		if ( preg_match( '#^(node_modules|vendor|tests)/#', $rel ) ) {
			continue;
		}
		$found[] = $rel;
	}
	sort( $found );
	return $found;
}

/**
 * Walk a file's tokens and separate the names it writes from the names it reads.
 *
 * @param string $path Absolute file path.
 * @return array{writes:array<string,bool>,reads:array<string,int>,unanalysable:string}
 */
function flosc_uv_scan( $path ) {
	$tokens = token_get_all( (string) file_get_contents( $path ) );
	$count  = count( $tokens );

	$writes       = array();
	$reads        = array();
	$unanalysable = '';

	// Functions whose by-reference parameter is written, not read.
	$by_ref_arg = array(
		'preg_match'     => 2,
		'preg_match_all' => 2,
		'str_replace'    => 3,
		'str_ireplace'   => 3,
		'preg_replace'   => 4,
		'similar_text'   => 2,
		'parse_str'      => 1,
		'settype'        => 0,
		'array_push'     => 0,
		'array_pop'      => 0,
		'array_shift'    => 0,
		'array_unshift'  => 0,
		'sort'           => 0,
		'rsort'          => 0,
		'usort'          => 0,
		'uasort'         => 0,
		'uksort'         => 0,
		'ksort'          => 0,
		'krsort'         => 0,
		'asort'          => 0,
		'arsort'         => 0,
		'shuffle'        => 0,
		'array_splice'   => 0,
		'array_multisort' => 0,
		'end'            => 0,
		'reset'          => 0,
		'next'           => 0,
		'prev'           => 0,
		'each'           => 0,
	);

	/**
	 * The index of the next meaningful token after $i, or -1.
	 *
	 * @param array $tokens Token list.
	 * @param int   $i      Current index.
	 * @param int   $count  Token count.
	 * @return int
	 */
	$next_real = function ( $tokens, $i, $count ) {
		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			return $j;
		}
		return -1;
	};

	/**
	 * The index of the previous meaningful token before $i, or -1.
	 *
	 * @param array $tokens Token list.
	 * @param int   $i      Current index.
	 * @return int
	 */
	$prev_real = function ( $tokens, $i ) {
		for ( $j = $i - 1; $j >= 0; $j-- ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			return $j;
		}
		return -1;
	};

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( is_array( $token ) && T_VARIABLE !== $token[0] ) {
			// A variable variable or extract() means the scope cannot be read
			// from the source, so the whole file is set aside.
			if ( T_DOLLAR_OPEN_CURLY_BRACES === $token[0] ) {
				$unanalysable = 'variable variable';
			}
			if ( T_STRING === $token[0] && in_array( strtolower( $token[1] ), array( 'extract', 'compact', 'get_defined_vars' ), true ) ) {
				$unanalysable = $token[1] . '()';
			}
			continue;
		}
		if ( '$' === $token ) {
			$unanalysable = 'variable variable';
			continue;
		}
		if ( ! is_array( $token ) ) {
			continue;
		}

		$name = ltrim( $token[1], '$' );
		$prev = $prev_real( $tokens, $i );
		$next = $next_real( $tokens, $i, $count );

		$prev_code = ( $prev >= 0 ) ? ( is_array( $tokens[ $prev ] ) ? $tokens[ $prev ][0] : $tokens[ $prev ] ) : null;
		$next_code = ( $next >= 0 ) ? ( is_array( $tokens[ $next ] ) ? $tokens[ $next ][0] : $tokens[ $next ] ) : null;

		// self::$instance, static::$x, Some_Class::$x -- a static property, not a
		// variable in any scope. Both its reads and its writes go through the
		// class, so it is skipped outright.
		if ( T_DOUBLE_COLON === $prev_code ) {
			continue;
		}

		// $obj->$name: the name after the arrow IS a scope variable being read.
		if ( T_OBJECT_OPERATOR === $prev_code || T_NULLSAFE_OBJECT_OPERATOR === $prev_code ) {
			if ( ! isset( $reads[ $name ] ) ) {
				$reads[ $name ] = $token[2];
			}
			continue;
		}

		// A class property declaration is not a scope variable at all:
		// `private $filesystem;` names a property, and every use of it goes
		// through $this. Skipped outright.
		$is_property = false;
		for ( $j = $prev; $j >= 0; $j-- ) {
			if ( ! is_array( $tokens[ $j ] ) ) {
				break;
			}
			if ( in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			if ( in_array( $tokens[ $j ][0], array( T_PUBLIC, T_PRIVATE, T_PROTECTED, T_VAR, T_READONLY ), true ) ) {
				$is_property = true;
				break;
			}
			if ( T_STATIC === $tokens[ $j ][0] ) {
				// static $x inside a function is a declaration; static $x after a
				// visibility keyword is a property. Keep looking back.
				continue;
			}
			if ( in_array( $tokens[ $j ][0], array( T_STRING, T_ARRAY, T_CALLABLE, T_NS_SEPARATOR ), true ) ) {
				// A type on a typed property or parameter.
				continue;
			}
			break;
		}
		if ( $is_property ) {
			continue;
		}

		// catch ( SomeException $e ) binds $e.
		$in_catch = false;
		$depth    = 0;
		for ( $j = $i - 1; $j >= 0 && $j > $i - 60; $j-- ) {
			$t = $tokens[ $j ];
			if ( is_array( $t ) ) {
				continue;
			}
			if ( ')' === $t ) {
				++$depth;
			} elseif ( '(' === $t ) {
				if ( 0 === $depth ) {
					$opener = $prev_real( $tokens, $j );
					if ( $opener >= 0 && is_array( $tokens[ $opener ] ) && T_CATCH === $tokens[ $opener ][0] ) {
						$in_catch = true;
					}
					break;
				}
				--$depth;
			} elseif ( ';' === $t || '{' === $t || '}' === $t ) {
				break;
			}
		}
		if ( $in_catch ) {
			$writes[ $name ] = true;
			continue;
		}

		// A declaration: global $x; static $x; function ( $x ).
		$declaring = false;
		for ( $j = $prev; $j >= 0; $j-- ) {
			if ( ! is_array( $tokens[ $j ] ) ) {
				if ( in_array( $tokens[ $j ], array( ';', '{', '}' ), true ) ) {
					break;
				}
				if ( ',' === $tokens[ $j ] ) {
					continue;
				}
				break;
			}
			if ( in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			if ( in_array( $tokens[ $j ][0], array( T_GLOBAL, T_STATIC ), true ) ) {
				$declaring = true;
			}
			break;
		}
		if ( $declaring ) {
			$writes[ $name ] = true;
			continue;
		}

		// foreach ( $list as $key => $value ): everything after `as` is bound.
		$in_foreach_binding = false;
		$depth              = 0;
		for ( $j = $i - 1; $j >= 0 && $j > $i - 200; $j-- ) {
			$t = $tokens[ $j ];
			if ( ! is_array( $t ) ) {
				if ( ')' === $t ) {
					++$depth;
				} elseif ( '(' === $t ) {
					if ( 0 === $depth ) {
						$opener = $prev_real( $tokens, $j );
						if ( $opener >= 0 && is_array( $tokens[ $opener ] ) && T_FOREACH === $tokens[ $opener ][0] ) {
							// Only a binding if an `as` sits between here and us.
							for ( $k = $j; $k < $i; $k++ ) {
								if ( is_array( $tokens[ $k ] ) && T_AS === $tokens[ $k ][0] ) {
									$in_foreach_binding = true;
									break;
								}
							}
						}
						break;
					}
					--$depth;
				} elseif ( ';' === $t || '{' === $t || '}' === $t ) {
					break;
				}
			}
		}
		if ( $in_foreach_binding ) {
			$writes[ $name ] = true;
			continue;
		}

		// A function or closure parameter list.
		$in_param_list = false;
		$depth         = 0;
		for ( $j = $i - 1; $j >= 0 && $j > $i - 400; $j-- ) {
			$t = $tokens[ $j ];
			if ( is_array( $t ) ) {
				continue;
			}
			if ( ')' === $t ) {
				++$depth;
			} elseif ( '(' === $t ) {
				if ( 0 === $depth ) {
					$opener = $prev_real( $tokens, $j );
					if ( $opener >= 0 && is_array( $tokens[ $opener ] ) ) {
						if ( in_array( $tokens[ $opener ][0], array( T_FUNCTION, T_FN, T_STRING ), true ) ) {
							$before = $prev_real( $tokens, $opener );
							if ( in_array( $tokens[ $opener ][0], array( T_FUNCTION, T_FN ), true )
								|| ( $before >= 0 && is_array( $tokens[ $before ] ) && in_array( $tokens[ $before ][0], array( T_FUNCTION, T_FN ), true ) ) ) {
								$in_param_list = true;
							}
						}
					} elseif ( $opener >= 0 && ')' === $tokens[ $opener ] ) {
						// use ( $x ) on a closure.
						$use = $prev_real( $tokens, $j );
						if ( $use >= 0 && is_array( $tokens[ $use ] ) && T_USE === $tokens[ $use ][0] ) {
							$in_param_list = true;
						}
					}
					if ( ! $in_param_list ) {
						$use = $prev_real( $tokens, $j );
						if ( $use >= 0 && is_array( $tokens[ $use ] ) && T_USE === $tokens[ $use ][0] ) {
							$in_param_list = true;
						}
					}
					break;
				}
				--$depth;
			} elseif ( ';' === $t || '{' === $t || '}' === $t ) {
				break;
			}
		}
		if ( $in_param_list ) {
			$writes[ $name ] = true;
			continue;
		}

		// Assignment, including compound and the target of list() / [ ] =.
		// Skip any [...] or ->prop that follows the variable before the operator.
		$j     = $next;
		$guard = 0;
		while ( $j >= 0 && $guard++ < 100 ) {
			$c = is_array( $tokens[ $j ] ) ? $tokens[ $j ][0] : $tokens[ $j ];
			if ( '[' === $c ) {
				$bracket = 1;
				for ( $k = $j + 1; $k < $count && $bracket > 0; $k++ ) {
					$cc = is_array( $tokens[ $k ] ) ? $tokens[ $k ][0] : $tokens[ $k ];
					if ( '[' === $cc ) {
						++$bracket;
					} elseif ( ']' === $cc ) {
						--$bracket;
					}
				}
				$j = $next_real( $tokens, $k - 1, $count );
				continue;
			}
			if ( T_OBJECT_OPERATOR === $c || T_NULLSAFE_OBJECT_OPERATOR === $c || T_DOUBLE_COLON === $c || T_STRING === $c ) {
				// A property write still reads the object, so stop here.
				break;
			}
			break;
		}
		$after = ( $j >= 0 ) ? ( is_array( $tokens[ $j ] ) ? $tokens[ $j ][0] : $tokens[ $j ] ) : null;

		$is_plain_assign = ( '=' === $after && $j === $next );
		$is_compound     = in_array(
			$after,
			array( T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_MOD_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_COALESCE_EQUAL, T_POW_EQUAL ),
			true
		);
		// $x[...] = and $x->p = are writes to $x's contents, which still requires
		// $x to exist -- except that PHP autovivifies, so treat as a write.
		$is_offset_write = ( '=' === $after && $j !== $next );

		if ( $is_plain_assign || $is_compound || $is_offset_write ) {
			$writes[ $name ] = true;
			if ( $is_compound ) {
				// $x .= reads $x too, but PHP allows it on an undefined name
				// with a warning; not reported, to stay conservative.
				continue;
			}
			continue;
		}

		// list( $a, $b ) = ... and [ $a, $b ] = ... bind every name inside.
		$in_destructure = false;
		$depth          = 0;
		for ( $j = $i - 1; $j >= 0 && $j > $i - 300; $j-- ) {
			$t = $tokens[ $j ];
			$c = is_array( $t ) ? $t[0] : $t;
			if ( ')' === $c || ']' === $c ) {
				++$depth;
				continue;
			}
			if ( '(' === $c || '[' === $c ) {
				if ( 0 > --$depth ) {
					// This is our enclosing bracket. It is a destructuring
					// target only if it is a list() or a bare [ and the matching
					// close is immediately followed by an assignment.
					$opener    = $prev_real( $tokens, $j );
					$is_list   = ( $opener >= 0 && is_array( $tokens[ $opener ] ) && T_LIST === $tokens[ $opener ][0] );
					$is_square = ( '[' === $c );
					if ( ! $is_list && ! $is_square ) {
						break;
					}
					// Walk to the matching close.
					$open_at = $is_list ? $j : $j;
					$bal     = 0;
					for ( $k = $open_at; $k < $count; $k++ ) {
						$cc = is_array( $tokens[ $k ] ) ? $tokens[ $k ][0] : $tokens[ $k ];
						if ( '(' === $cc || '[' === $cc ) {
							++$bal;
						} elseif ( ')' === $cc || ']' === $cc ) {
							--$bal;
							if ( 0 === $bal ) {
								break;
							}
						}
					}
					$after_close = $next_real( $tokens, $k, $count );
					if ( $after_close >= 0 && '=' === $tokens[ $after_close ] ) {
						$in_destructure = true;
					}
					break;
				}
				continue;
			}
			if ( ';' === $c || '{' === $c || '}' === $c ) {
				break;
			}
		}
		if ( $in_destructure ) {
			$writes[ $name ] = true;
			continue;
		}

		// A by-reference argument position.
		$depth = 0;
		$arg   = 0;
		for ( $j = $i - 1; $j >= 0 && $j > $i - 300; $j-- ) {
			$t = $tokens[ $j ];
			if ( is_array( $t ) ) {
				continue;
			}
			if ( ')' === $t ) {
				++$depth;
			} elseif ( ',' === $t && 0 === $depth ) {
				++$arg;
			} elseif ( '(' === $t ) {
				if ( 0 === $depth ) {
					$fn = $prev_real( $tokens, $j );
					if ( $fn >= 0 && is_array( $tokens[ $fn ] ) && T_STRING === $tokens[ $fn ][0] ) {
						$lower = strtolower( $tokens[ $fn ][1] );
						if ( isset( $by_ref_arg[ $lower ] ) && $by_ref_arg[ $lower ] === $arg ) {
							$writes[ $name ] = true;
							continue 2;
						}
					}
					break;
				}
				--$depth;
			} elseif ( ';' === $t || '{' === $t || '}' === $t ) {
				break;
			}
		}

		if ( ! isset( $reads[ $name ] ) ) {
			$reads[ $name ] = $token[2];
		}
	}

	return array(
		'writes'       => $writes,
		'reads'        => $reads,
		'unanalysable' => $unanalysable,
	);
}

/**
 * The files a given file includes, by literal path.
 *
 * @param string $root Plugin root.
 * @param string $rel  Relative path of the including file.
 * @return string[] Relative paths of included files.
 */
function flosc_uv_includes( $root, $rel ) {
	$src = (string) file_get_contents( $root . '/' . $rel );
	$out = array();
	if ( ! preg_match_all(
		'/(?:require|include)(?:_once)?\s+(?<base>FLOSC_PLUGIN_DIR|__DIR__)\s*\.\s*\'(?<path>[^\']+)\'/',
		$src,
		$hits,
		PREG_SET_ORDER
	) ) {
		return $out;
	}
	foreach ( $hits as $hit ) {
		if ( 'FLOSC_PLUGIN_DIR' === $hit['base'] ) {
			$target = ltrim( $hit['path'], '/' );
		} else {
			$target = ltrim( dirname( $rel ) . '/' . $hit['path'], '/' );
		}
		$target = preg_replace( '#/\./#', '/', $target );
		while ( preg_match( '#[^/]+/\.\./#', $target ) ) {
			$target = preg_replace( '#[^/]+/\.\./#', '', $target );
		}
		if ( file_exists( $root . '/' . $target ) ) {
			$out[] = $target;
		}
	}
	return array_values( array_unique( $out ) );
}

$flosc_files    = flosc_uv_files( $flosc_root );
$flosc_scans    = array();
$flosc_includes = array();

foreach ( $flosc_files as $flosc_rel ) {
	$flosc_scans[ $flosc_rel ]    = flosc_uv_scan( $flosc_root . '/' . $flosc_rel );
	$flosc_includes[ $flosc_rel ] = flosc_uv_includes( $flosc_root, $flosc_rel );
}

// Who includes whom, so an included template inherits its includer's scope.
$flosc_included_by = array();
foreach ( $flosc_includes as $flosc_parent => $flosc_children ) {
	foreach ( $flosc_children as $flosc_child ) {
		$flosc_included_by[ $flosc_child ][] = $flosc_parent;
	}
}

/**
 * Every name written in this file or in any file that includes it.
 *
 * @param string $rel     Relative path.
 * @param array  $scans   Per-file scan results.
 * @param array  $parents Reverse include map.
 * @param array  $seen    Guard against include cycles.
 * @return array<string,bool>
 */
function flosc_uv_scope( $rel, $scans, $parents, $seen = array() ) {
	if ( isset( $seen[ $rel ] ) ) {
		return array();
	}
	$seen[ $rel ] = true;

	$names = isset( $scans[ $rel ] ) ? $scans[ $rel ]['writes'] : array();
	foreach ( $parents[ $rel ] ?? array() as $parent ) {
		$names += flosc_uv_scope( $parent, $scans, $parents, $seen );
	}
	return $names;
}

$flosc_findings = array();
$flosc_skipped  = array();

foreach ( $flosc_files as $flosc_rel ) {
	if ( '' !== $flosc_scans[ $flosc_rel ]['unanalysable'] ) {
		$flosc_skipped[] = $flosc_rel . ' (' . $flosc_scans[ $flosc_rel ]['unanalysable'] . ')';
		continue;
	}

	$flosc_scope = flosc_uv_scope( $flosc_rel, $flosc_scans, $flosc_included_by );

	foreach ( $flosc_scans[ $flosc_rel ]['reads'] as $flosc_name => $flosc_line ) {
		if ( isset( $flosc_scope[ $flosc_name ] ) ) {
			continue;
		}
		if ( in_array( $flosc_name, $flosc_always_defined, true ) ) {
			continue;
		}
		$flosc_findings[] = sprintf( '%s:%d  $%s is read and never assigned', $flosc_rel, $flosc_line, $flosc_name );
	}
}

printf(
	"check_undefined_variables: %d files scanned, %d set aside as unanalysable\n",
	count( $flosc_files ),
	count( $flosc_skipped )
);
foreach ( $flosc_skipped as $flosc_line ) {
	echo '  skipped  ' . $flosc_line . "\n";
}

if ( $flosc_findings ) {
	sort( $flosc_findings );
	foreach ( $flosc_findings as $flosc_line ) {
		echo '  FAIL  ' . $flosc_line . "\n";
	}
	exit( 1 );
}

echo "  PASS  every variable read resolves to something that assigns it\n";
exit( 0 );
