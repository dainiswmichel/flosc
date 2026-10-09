<?php
/**
 * Verify that every shipped output buffer closes in its owning PHP scope.
 *
 * Source-only. tests/ is excluded from the distribution zip.
 * Exit 0 when the boundary holds, 1 with a list otherwise.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$flosc_root     = dirname( __DIR__ );
$flosc_closers  = array( 'ob_get_clean', 'ob_end_clean', 'ob_end_flush', 'ob_get_flush' );
$flosc_failures = array();
$flosc_pairs    = array();
$flosc_opens    = 0;
$flosc_closes   = 0;
$flosc_import   = array(
	'open'         => null,
	'close'        => null,
	'error_return' => null,
);

/**
 * Return shipped PHP files in stable path order.
 *
 * @param string $root Plugin root.
 * @return array<string,string>
 */
function flosc_buffer_source_files( $root ) {
	$files = array();
	$skip  = array( '/.git/', '/tests/', '/pre-release-candidates/', '/vendor/' );
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $items as $item ) {
		if ( ! $item->isFile() || 'php' !== strtolower( $item->getExtension() ) ) {
			continue;
		}

		$path     = $item->getPathname();
		$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( $root ) ) );
		foreach ( $skip as $fragment ) {
			if ( false !== strpos( $relative, $fragment ) ) {
				continue 2;
			}
		}
		$files[ ltrim( $relative, '/' ) ] = $path;
	}

	ksort( $files );
	return $files;
}

/**
 * Find the next non-whitespace, non-comment token.
 *
 * @param array $tokens Token stream.
 * @param int   $index  Current token index.
 * @return array|string|null
 */
function flosc_buffer_next_token( $tokens, $index ) {
	$count = count( $tokens );
	for ( $i = $index + 1; $i < $count; ++$i ) {
		$token = $tokens[ $i ];
		if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		return $token;
	}
	return null;
}

/**
 * Determine whether a named token is a function call.
 *
 * @param array $tokens Token stream.
 * @param int   $index  Current token index.
 * @return bool
 */
function flosc_buffer_is_call( $tokens, $index ) {
	return '(' === flosc_buffer_next_token( $tokens, $index );
}

/**
 * Return active control-region types.
 *
 * @param array $braces Brace stack.
 * @return array<int,string>
 */
function flosc_buffer_regions( $braces ) {
	$regions = array();
	foreach ( $braces as $brace ) {
		if ( isset( $brace['region'] ) && '' !== $brace['region'] ) {
			$regions[] = $brace['region'];
		}
	}
	return $regions;
}

/**
 * Record a control-flow statement that could bypass an open buffer.
 *
 * @param array  $open_buffers Buffers open in the current scope.
 * @param string $kind         Statement type.
 * @param int    $line         Source line.
 * @return array
 */
function flosc_buffer_add_hazard( $open_buffers, $kind, $line ) {
	foreach ( $open_buffers as $index => $buffer ) {
		$open_buffers[ $index ]['hazards'][] = $kind . '@' . $line;
	}
	return $open_buffers;
}

foreach ( flosc_buffer_source_files( $flosc_root ) as $flosc_relative => $flosc_path ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local PHP source in a CLI-only audit; no URL is accepted.
	$flosc_tokens           = token_get_all( (string) file_get_contents( $flosc_path ) );
	$flosc_scope            = $flosc_relative . ':file';
	$flosc_braces           = array();
	$flosc_open_by_scope    = array( $flosc_scope => array() );
	$flosc_pending_function = null;
	$flosc_pending_region   = '';
	$flosc_pending_return   = array();
	$flosc_finally          = array();
	$flosc_finally_sequence = 0;
	$flosc_function_seq     = 0;
	$flosc_line             = 1;

	foreach ( $flosc_tokens as $flosc_index => $flosc_token ) {
		if ( is_array( $flosc_token ) ) {
			$flosc_line = $flosc_token[2];
			$flosc_id   = $flosc_token[0];
			$flosc_text = $flosc_token[1];

			if ( T_FUNCTION === $flosc_id ) {
				$flosc_pending_function = array(
					'line' => $flosc_line,
					'name' => '',
				);
				continue;
			}

			if ( null !== $flosc_pending_function && T_STRING === $flosc_id && '' === $flosc_pending_function['name'] ) {
				$flosc_pending_function['name'] = $flosc_text;
			}

			if ( T_TRY === $flosc_id ) {
				$flosc_pending_region = 'try';
				continue;
			}
			if ( T_FINALLY === $flosc_id ) {
				$flosc_pending_region = 'finally';
				continue;
			}
			if ( T_CATCH === $flosc_id ) {
				$flosc_pending_region = 'catch';
				continue;
			}

			if ( T_RETURN === $flosc_id ) {
				$flosc_pending_return[ $flosc_scope ] = array(
					'line'      => $flosc_line,
					'has_close' => false,
					'api_error' => false,
				);
				foreach ( $flosc_braces as $flosc_brace ) {
					if ( isset( $flosc_brace['finally_id'] ) ) {
						$flosc_finally[ $flosc_brace['finally_id'] ]['return'] = $flosc_line;
					}
				}
				continue;
			}

			if ( T_CONSTANT_ENCAPSED_STRING === $flosc_id && isset( $flosc_pending_return[ $flosc_scope ] ) ) {
				if ( 'flosc_wxr_api' === trim( $flosc_text, "'\"" ) ) {
					$flosc_pending_return[ $flosc_scope ]['api_error'] = true;
				}
			}

			if ( in_array( $flosc_id, array( T_EXIT, T_THROW ), true ) ) {
				$flosc_open_by_scope[ $flosc_scope ] = flosc_buffer_add_hazard(
					$flosc_open_by_scope[ $flosc_scope ],
					token_name( $flosc_id ),
					$flosc_line
				);
			}

			if ( T_STRING !== $flosc_id || ! flosc_buffer_is_call( $flosc_tokens, $flosc_index ) ) {
				continue;
			}

			$flosc_call = strtolower( $flosc_text );
			if ( 'wp_die' === $flosc_call ) {
				$flosc_open_by_scope[ $flosc_scope ] = flosc_buffer_add_hazard(
					$flosc_open_by_scope[ $flosc_scope ],
					'wp_die',
					$flosc_line
				);
			}

			if ( 0 === strpos( $flosc_call, 'ob_' ) ) {
				foreach ( $flosc_braces as $flosc_brace ) {
					if ( isset( $flosc_brace['finally_id'] ) ) {
						$flosc_finally[ $flosc_brace['finally_id'] ]['ob_call'] = $flosc_line;
					}
				}
			}

			if ( 'ob_start' === $flosc_call ) {
				++$flosc_opens;
				$flosc_open_by_scope[ $flosc_scope ][] = array(
					'file'    => $flosc_relative,
					'line'    => $flosc_line,
					'scope'   => $flosc_scope,
					'regions' => flosc_buffer_regions( $flosc_braces ),
					'hazards' => array(),
				);
				continue;
			}

			if ( ! in_array( $flosc_call, $flosc_closers, true ) ) {
				continue;
			}

			++$flosc_closes;
			if ( empty( $flosc_open_by_scope[ $flosc_scope ] ) ) {
				$flosc_failures[] = $flosc_relative . ':' . $flosc_line . ' closes a buffer not opened in this PHP scope';
				continue;
			}

			$flosc_open    = array_pop( $flosc_open_by_scope[ $flosc_scope ] );
			$flosc_pair    = array(
				'open'          => $flosc_open,
				'close_line'    => $flosc_line,
				'close_regions' => flosc_buffer_regions( $flosc_braces ),
			);
			$flosc_pairs[] = $flosc_pair;
			if ( isset( $flosc_pending_return[ $flosc_scope ] ) ) {
				$flosc_pending_return[ $flosc_scope ]['has_close'] = true;
			}

			if ( false !== strpos( $flosc_scope, ':flosc_portability_run_wxr_import@' ) ) {
				$flosc_import['open']  = $flosc_open;
				$flosc_import['close'] = $flosc_pair;
			}
			continue;
		}

		if ( '{' === $flosc_token ) {
			$flosc_brace = array(
				'region'      => $flosc_pending_region,
				'prior_scope' => null,
			);
			if ( 'finally' === $flosc_pending_region ) {
				++$flosc_finally_sequence;
				$flosc_brace['finally_id']                = $flosc_finally_sequence;
				$flosc_finally[ $flosc_finally_sequence ] = array(
					'file'    => $flosc_relative,
					'line'    => $flosc_line,
					'ob_call' => null,
					'return'  => null,
				);
			}
			$flosc_pending_region = '';

			if ( null !== $flosc_pending_function ) {
				++$flosc_function_seq;
				$flosc_name                          = '' !== $flosc_pending_function['name'] ? $flosc_pending_function['name'] : 'closure';
				$flosc_new_scope                     = $flosc_relative . ':' . $flosc_name . '@' . $flosc_pending_function['line'] . '#' . $flosc_function_seq;
				$flosc_brace['prior_scope']          = $flosc_scope;
				$flosc_scope                         = $flosc_new_scope;
				$flosc_open_by_scope[ $flosc_scope ] = array();
				$flosc_pending_function              = null;
			}
			$flosc_braces[] = $flosc_brace;
			continue;
		}

		if ( '}' === $flosc_token ) {
			$flosc_brace = array_pop( $flosc_braces );
			if ( isset( $flosc_brace['finally_id'] ) ) {
				$flosc_finally_block = $flosc_finally[ $flosc_brace['finally_id'] ];
				if ( null !== $flosc_finally_block['ob_call'] && null !== $flosc_finally_block['return'] ) {
					$flosc_failures[] = $flosc_relative . ':' . $flosc_finally_block['return'] . ' returns from a finally block that manages output buffering';
				}
			}
			if ( isset( $flosc_brace['prior_scope'] ) && null !== $flosc_brace['prior_scope'] ) {
				if ( ! empty( $flosc_open_by_scope[ $flosc_scope ] ) ) {
					foreach ( $flosc_open_by_scope[ $flosc_scope ] as $flosc_unclosed ) {
						$flosc_failures[] = $flosc_unclosed['file'] . ':' . $flosc_unclosed['line'] . ' remains open when its function scope ends';
					}
				}
				$flosc_scope = $flosc_brace['prior_scope'];
			}
			continue;
		}

		if ( ';' === $flosc_token && isset( $flosc_pending_return[ $flosc_scope ] ) ) {
			$flosc_return = $flosc_pending_return[ $flosc_scope ];
			if ( ! $flosc_return['has_close'] ) {
				$flosc_open_by_scope[ $flosc_scope ] = flosc_buffer_add_hazard(
					$flosc_open_by_scope[ $flosc_scope ],
					'T_RETURN',
					$flosc_return['line']
				);
			}
			if ( $flosc_return['api_error'] && false !== strpos( $flosc_scope, ':flosc_portability_run_wxr_import@' ) ) {
				$flosc_import['error_return'] = $flosc_return['line'];
			}
			unset( $flosc_pending_return[ $flosc_scope ] );
		}
	}

	foreach ( $flosc_open_by_scope as $flosc_scope_buffers ) {
		foreach ( $flosc_scope_buffers as $flosc_unclosed ) {
			$flosc_failures[] = $flosc_unclosed['file'] . ':' . $flosc_unclosed['line'] . ' has no closer in its PHP scope';
		}
	}
}

foreach ( $flosc_pairs as $flosc_pair ) {
	$flosc_open = $flosc_pair['open'];
	if ( ! empty( $flosc_open['hazards'] ) ) {
		$flosc_failures[] = $flosc_open['file'] . ':' . $flosc_open['line'] . ' can be bypassed by ' . implode( ', ', $flosc_open['hazards'] );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes the CLI audit ledger to standard output, not a filesystem path.
	fwrite(
		STDOUT,
		sprintf(
			"pair %s:%d -> %d\n",
			$flosc_open['file'],
			$flosc_open['line'],
			$flosc_pair['close_line']
		)
	);
}

if ( 36 !== $flosc_opens ) {
	$flosc_failures[] = 'expected 36 ob_start() calls, found ' . $flosc_opens;
}
if ( 36 !== $flosc_closes ) {
	$flosc_failures[] = 'expected 36 closing calls, found ' . $flosc_closes;
}
if ( 36 !== count( $flosc_pairs ) ) {
	$flosc_failures[] = 'expected 36 paired buffers, found ' . count( $flosc_pairs );
}

if ( null === $flosc_import['open'] || ! in_array( 'try', $flosc_import['open']['regions'], true ) ) {
	$flosc_failures[] = 'WXR importer ob_start() is not inside try';
}
if ( null === $flosc_import['close'] || ! in_array( 'finally', $flosc_import['close']['close_regions'], true ) ) {
	$flosc_failures[] = 'WXR importer buffer close is not inside finally';
}
if (
	null === $flosc_import['error_return']
	|| null === $flosc_import['close']
	|| $flosc_import['error_return'] <= $flosc_import['close']['close_line']
) {
	$flosc_failures[] = 'flosc_wxr_api return does not follow importer buffer cleanup';
}

if ( ! empty( $flosc_failures ) ) {
	foreach ( $flosc_failures as $flosc_failure ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes CLI failure details to standard error, not a filesystem path.
		fwrite( STDERR, 'FAIL ' . $flosc_failure . "\n" );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes the CLI failure count to standard error, not a filesystem path.
	fwrite( STDERR, count( $flosc_failures ) . " failed\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes the CLI success summary to standard output, not a filesystem path.
fwrite( STDOUT, "output buffer boundary passed: 36 opens, 36 paired, 0 unclosed\n" );
exit( 0 );
