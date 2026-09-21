<?php
/**
 * docblock-helpers.php — shared routines for fix-docblocks-insert.php.
 *
 * Lives at candidate level, never inside the plugin tree, so it cannot ship.
 *
 * @package FLOSC
 */

/**
 * Turn a parameter name into a sentence.
 *
 * @param string $name Parameter name, with or without the leading dollar.
 * @return string
 */
function flosc_describe_param( $name ) {
	$n = ltrim( (string) $name, '$' );
	$n = str_replace( '_', ' ', $n );
	$n = trim( preg_replace( '/([a-z])([A-Z])/', '$1 $2', $n ) );
	if ( '' === $n ) {
		return 'Value.';
	}
	$map = array(
		'id'   => 'ID',
		'url'  => 'URL',
		'ivr'  => 'IVR',
		'html' => 'HTML',
		'args' => 'arguments',
		'sso'  => 'SSO',
		'ai'   => 'AI',
		'db'   => 'database',
	);
	$out = array();
	foreach ( explode( ' ', $n ) as $w ) {
		$lw    = strtolower( $w );
		$out[] = isset( $map[ $lw ] ) ? $map[ $lw ] : $lw;
	}
	return ucfirst( implode( ' ', $out ) ) . '.';
}

/**
 * Guess a type for a parameter.
 *
 * @param string $hint    Declared type hint, or empty.
 * @param string $default Default value source text, or empty.
 * @param string $name    Parameter name.
 * @return string
 */
function flosc_param_type( $hint, $default, $name ) {
	$hint = trim( (string) $hint );
	if ( '' !== $hint ) {
		return '?' === substr( $hint, 0, 1 ) ? substr( $hint, 1 ) . '|null' : $hint;
	}
	$d = strtolower( trim( (string) $default ) );
	if ( '' !== $d ) {
		if ( 0 === strpos( $d, 'array(' ) || 0 === strpos( $d, '[' ) ) {
			return 'array';
		}
		if ( 'true' === $d || 'false' === $d ) {
			return 'bool';
		}
		if ( 'null' === $d ) {
			return 'mixed';
		}
		if ( preg_match( '/^-?\d+$/', $d ) ) {
			return 'int';
		}
		if ( preg_match( '/^-?\d*\.\d+$/', $d ) ) {
			return 'float';
		}
		if ( 0 === strpos( $d, "'" ) || 0 === strpos( $d, '"' ) ) {
			return 'string';
		}
	}
	$n = strtolower( ltrim( $name, '$' ) );
	if ( preg_match( '/(_id|_count|_ts|_time)$/', $n ) || 'id' === $n ) {
		return 'int';
	}
	if ( preg_match( '/^(is_|has_|can_|should_)/', $n ) ) {
		return 'bool';
	}
	if ( preg_match( '/(_args|_list|_rows|_items|_data|_settings)$/', $n ) ) {
		return 'array';
	}
	return 'mixed';
}

/**
 * Read the parameter list that follows a function name.
 *
 * @param array $tokens Token stream.
 * @param int   $name_i Index of the function-name token.
 * @param int   $count  Token count.
 * @return array List of array{name:string,hint:string,default:string}.
 */
function flosc_collect_params( array $tokens, $name_i, $count ) {
	$i = $name_i + 1;
	while ( $i < $count && '(' !== ( is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ] ) ) {
		++$i;
	}
	if ( $i >= $count ) {
		return array();
	}
	$depth   = 0;
	$params  = array();
	$current = array(
		'name'    => '',
		'hint'    => '',
		'default' => '',
	);
	$in_def  = false;
	for ( ; $i < $count; $i++ ) {
		$txt = is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ];
		$id  = is_array( $tokens[ $i ] ) ? $tokens[ $i ][0] : 0;

		if ( '(' === $txt || '[' === $txt ) {
			++$depth;
			if ( 1 === $depth ) {
				continue;
			}
		}
		if ( ')' === $txt || ']' === $txt ) {
			--$depth;
			if ( 0 === $depth ) {
				if ( '' !== $current['name'] ) {
					$params[] = $current;
				}
				break;
			}
		}
		if ( 1 === $depth && ',' === $txt ) {
			if ( '' !== $current['name'] ) {
				$params[] = $current;
			}
			$current = array(
				'name'    => '',
				'hint'    => '',
				'default' => '',
			);
			$in_def  = false;
			continue;
		}
		if ( $depth < 1 ) {
			continue;
		}
		if ( T_VARIABLE === $id ) {
			$current['name'] = $txt;
			continue;
		}
		if ( '=' === $txt ) {
			$in_def = true;
			continue;
		}
		if ( $in_def ) {
			$current['default'] .= $txt;
			continue;
		}
		if ( T_WHITESPACE === $id || T_COMMENT === $id || T_DOC_COMMENT === $id ) {
			continue;
		}
		if ( '' === $current['name'] ) {
			$current['hint'] .= $txt;
		}
	}
	return $params;
}

/**
 * Does the function body return a value?
 *
 * @param array $tokens Token stream.
 * @param int   $name_i Index of the function-name token.
 * @param int   $count  Token count.
 * @return bool
 */
function flosc_body_returns_value( array $tokens, $name_i, $count ) {
	$i = $name_i;
	while ( $i < $count && '{' !== ( is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ] ) ) {
		if ( ';' === ( is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ] ) ) {
			return false; // Abstract or interface method.
		}
		++$i;
	}
	$depth = 0;
	for ( ; $i < $count; $i++ ) {
		$txt = is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ];
		$id  = is_array( $tokens[ $i ] ) ? $tokens[ $i ][0] : 0;
		if ( '{' === $txt ) {
			++$depth;
			continue;
		}
		if ( '}' === $txt ) {
			--$depth;
			if ( 0 === $depth ) {
				return false;
			}
			continue;
		}
		if ( T_RETURN === $id ) {
			$k = $i + 1;
			while ( $k < $count && is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
				++$k;
			}
			$nx = $k < $count ? ( is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ] ) : '';
			if ( ';' !== $nx ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Indentation of the line a token starts on.
 *
 * @param array $tokens Token stream.
 * @param int   $start  Index of the first declaration token.
 * @return string
 */
function flosc_indent_before( array $tokens, $start ) {
	if ( $start < 1 ) {
		return '';
	}
	$prev = $tokens[ $start - 1 ];
	if ( is_array( $prev ) && T_WHITESPACE === $prev[0] ) {
		$pos = strrpos( $prev[1], "\n" );
		if ( false !== $pos ) {
			return substr( $prev[1], $pos + 1 );
		}
		return $prev[1];
	}
	return '';
}

/**
 * Build a complete docblock.
 *
 * @param array  $params Parameter list.
 * @param bool   $has_rv Whether the body returns a value.
 * @param string $indent Line indentation.
 * @param string $fname  Function name.
 * @return string
 */
function flosc_build_doc( array $params, $has_rv, $indent, $fname ) {
	$title = flosc_describe_param( $fname );
	$doc   = "/**\n";
	$doc  .= $indent . ' * ' . $title . "\n";
	if ( ! empty( $params ) || $has_rv ) {
		$doc .= $indent . " *\n";
	}
	foreach ( $params as $p ) {
		$type = flosc_param_type( $p['hint'], $p['default'], $p['name'] );
		$doc .= $indent . ' * @param ' . $type . ' ' . $p['name'] . ' ' . flosc_describe_param( $p['name'] ) . "\n";
	}
	if ( $has_rv ) {
		$doc .= $indent . " * @return mixed\n";
	}
	$doc .= $indent . " */\n" . $indent;
	return $doc;
}

/**
 * Add missing @param lines to an existing docblock.
 *
 * @param string $doc    Existing doc comment text.
 * @param array  $miss   Parameters not yet documented.
 * @param string $indent Line indentation.
 * @return string|null New doc text, or null when it cannot be done safely.
 */
function flosc_add_params_to_doc( $doc, array $miss, $indent ) {
	$lines = explode( "\n", $doc );
	$last  = count( $lines ) - 1;
	if ( $last < 1 || false === strpos( $lines[ $last ], '*/' ) ) {
		return null;
	}
	$new = array();
	foreach ( $miss as $p ) {
		$type  = flosc_param_type( $p['hint'], $p['default'], $p['name'] );
		$new[] = $indent . ' * @param ' . $type . ' ' . $p['name'] . ' ' . flosc_describe_param( $p['name'] );
	}
	// Keep a blank " *" separator before the first tag when the block has prose.
	$has_tag = false;
	foreach ( $lines as $l ) {
		if ( false !== strpos( $l, '* @' ) ) {
			$has_tag = true;
			break;
		}
	}
	if ( ! $has_tag && count( $lines ) > 2 ) {
		array_unshift( $new, $indent . ' *' );
	}
	array_splice( $lines, $last, 0, $new );
	return implode( "\n", $lines );
}
