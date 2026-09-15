<?php
/**
 * Guard the issue classes called out in the 13 September WordPress.org review.
 *
 * These checks deliberately inspect source instead of loading WordPress. They
 * protect review-sensitive boundaries that a happy-path runtime test will not
 * exercise, and they exclude tests and other files omitted from the plugin ZIP.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

/**
 * Print one strict assertion.
 *
 * @param string $label    Human-readable assertion label.
 * @param mixed  $actual   Observed value.
 * @param mixed  $expected Required value.
 * @return void
 */
function flosc_wporg_check( $label, $actual, $expected ) {
	global $fail;

	$pass = $actual === $expected;
	if ( ! $pass ) {
		$fail++;
	}

	printf(
		"%s %-68s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

/**
 * Read PHP files that can be included in the runtime distribution.
 *
 * @param string $root Plugin root.
 * @return array<string,string>
 */
function flosc_wporg_runtime_php( $root ) {
	$files = array();
	$skip  = array(
		'.git/',
		'.github/',
		'.venv/',
		'node_modules/',
		'pre-release-candidates/',
		'tests/',
		'vendor/',
	);

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		$path = $file->getPathname();
		$rel  = str_replace( $root . '/', '', $path );
		if ( substr( $rel, -4 ) !== '.php' || 'admin/create-sample-data.php' === $rel ) {
			continue;
		}
		foreach ( $skip as $prefix ) {
			if ( 0 === strpos( $rel, $prefix ) ) {
				continue 2;
			}
		}
		$files[ $rel ] = (string) file_get_contents( $path );
	}

	ksort( $files );
	return $files;
}

/**
 * Find source lines matching a review regression pattern.
 *
 * @param array<string,string> $files   Relative path to source.
 * @param string               $pattern PCRE pattern.
 * @return string[]
 */
function flosc_wporg_regex_hits( $files, $pattern ) {
	$hits = array();

	foreach ( $files as $rel => $source ) {
		foreach ( preg_split( '/\R/', $source ) as $index => $line ) {
			if ( preg_match( $pattern, $line ) ) {
				$hits[] = $rel . ':' . ( $index + 1 );
			}
		}
	}

	return $hits;
}

/**
 * Remove PHP comments while preserving executable tokens.
 *
 * @param string $source PHP source.
 * @return string
 */
function flosc_wporg_without_comments( $source ) {
	$code = '';
	foreach ( token_get_all( $source ) as $token ) {
		if ( is_array( $token ) ) {
			if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}
			$code .= $token[1];
		} else {
			$code .= $token;
		}
	}
	return $code;
}

/**
 * Extract one named PHP function or method using PHP's tokenizer.
 *
 * @param string $source Source containing a PHP opening tag.
 * @param string $name   Function or method name.
 * @return string
 */
function flosc_wporg_function_source( $source, $name ) {
	$looking    = false;
	$matched    = false;
	$collecting = false;
	$depth      = 0;
	$result     = '';

	foreach ( token_get_all( $source ) as $token ) {
		$text = is_array( $token ) ? $token[1] : $token;

		if ( ! $matched ) {
			if ( is_array( $token ) && T_FUNCTION === $token[0] ) {
				$looking = true;
				continue;
			}
			if ( $looking && is_array( $token ) && T_STRING === $token[0] ) {
				$matched = $name === $token[1];
				$looking = false;
			}
			if ( ! $matched ) {
				continue;
			}
		}

		if ( ! $collecting ) {
			if ( '{' === $text ) {
				$collecting = true;
				$depth      = 1;
				$result     = '{';
			}
			continue;
		}

		$result .= $text;
		if ( '{' === $text ) {
			$depth++;
		} elseif ( '}' === $text ) {
			$depth--;
			if ( 0 === $depth ) {
				return $result;
			}
		}
	}

	return '';
}

$runtime = flosc_wporg_runtime_php( $root );
$main    = (string) file_get_contents( $root . '/flosc.php' );
$readme  = (string) file_get_contents( $root . '/readme.txt' );

echo "Review gate inspected the runtime tree\n";
flosc_wporg_check( 'runtime PHP files were found', count( $runtime ) > 100, true );

echo "\nWordPress version headers use the accepted release line\n";
preg_match( '/^ \* Requires at least:\s*(\S+)/m', $main, $plugin_minimum );
preg_match( '/^Requires at least:\s*(\S+)/m', $readme, $readme_minimum );
preg_match( '/^Tested up to:\s*(\S+)/m', $readme, $readme_tested );
flosc_wporg_check( 'flosc.php Requires at least', $plugin_minimum[1] ?? '', '7.0' );
flosc_wporg_check( 'readme.txt Requires at least', $readme_minimum[1] ?? '', '7.0' );
flosc_wporg_check( 'readme.txt Tested up to', $readme_tested[1] ?? '', '7.1' );

echo "\nImporter loading uses no rejected bootstrap or hardcoded path\n";
$importer_hits = flosc_wporg_regex_hits(
	$runtime,
	'~wp-admin/includes/import\.php|wordpress-importer[/\\\\]|\bWP_LOAD_IMPORTERS\b~i'
);
flosc_wporg_check( 'no direct or hardcoded WordPress Importer loading', $importer_hits, array() );

echo "\nSSO redirect hosts come from canonical configuration\n";
$oauth_source = (string) file_get_contents( $root . '/includes/sso/class-oauth2-handler.php' );
$allowlist    = flosc_wporg_function_source(
	flosc_wporg_without_comments( $oauth_source ),
	'get_allowed_sso_redirect_hosts'
);
flosc_wporg_check( 'SSO allowlist builder was found', '' !== $allowlist, true );
flosc_wporg_check( 'SSO allowlist does not read HTTP_HOST', false !== strpos( $allowlist, 'HTTP_HOST' ), false );

echo "\nKnowledge-base edits validate before writing\n";
$main_code = flosc_wporg_without_comments( $main );
$kb_save   = flosc_wporg_function_source( $main_code, 'handle_kb_save_edit' );
flosc_wporg_check( 'knowledge-base save handler was found', '' !== $kb_save, true );

$input_match = array();
$write_match = array();
preg_match(
	'/\$(?<raw>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*\$post\s*\[\s*["\']file_content["\']\s*\]\s*\?\?/',
	$kb_save,
	$input_match,
	PREG_OFFSET_CAPTURE
);
preg_match(
	'/flosc_write_data_file\s*\(\s*\$target\s*,\s*\$(?<clean>[A-Za-z_][A-Za-z0-9_]*)\s*\)/',
	$kb_save,
	$write_match,
	PREG_OFFSET_CAPTURE
);

$raw_name   = isset( $input_match['raw'][0] ) ? $input_match['raw'][0] : '';
$clean_name = isset( $write_match['clean'][0] ) ? $write_match['clean'][0] : '';
$input_at   = isset( $input_match[0][1] ) ? $input_match[0][1] : -1;
$write_at   = isset( $write_match[0][1] ) ? $write_match[0][1] : -1;
flosc_wporg_check( 'file_content input is located', '' !== $raw_name, true );
flosc_wporg_check( 'knowledge-base writer content argument is located', '' !== $clean_name, true );

$scalar_at   = -1;
$size_at     = -1;
$sanitize_at = -1;
if ( '' !== $raw_name && '' !== $clean_name ) {
	$raw_token   = '\\$' . preg_quote( $raw_name, '/' );
	$clean_token = '\\$' . preg_quote( $clean_name, '/' );
	$match       = array();

	preg_match(
		'/if\s*\(\s*!\s*is_(?:string|scalar)\s*\(\s*' . $raw_token . '\s*\)\s*\)/i',
		$kb_save,
		$match,
		PREG_OFFSET_CAPTURE
	);
	$scalar_at = isset( $match[0][1] ) ? $match[0][1] : -1;

	$match = array();
	preg_match(
		'/if\s*\(\s*(?:mb_)?strlen\s*\(\s*' . $raw_token . '\s*\)\s*(?:>=|>)\s*(?:\d+|[A-Z_][A-Z0-9_]*)/i',
		$kb_save,
		$match,
		PREG_OFFSET_CAPTURE
	);
	$size_at = isset( $match[0][1] ) ? $match[0][1] : -1;
	if ( $size_at < 0 ) {
		$match = array();
		preg_match(
			'/flosc_sanitize_[A-Za-z0-9_]+\s*\(\s*' . $raw_token . '\s*,\s*(?:\d+|[A-Z_][A-Z0-9_]*)\s*\)/i',
			$kb_save,
			$match,
			PREG_OFFSET_CAPTURE
		);
		$size_at = isset( $match[0][1] ) ? $match[0][1] : -1;
	}

	$match = array();
	preg_match(
		'/' . $clean_token . '\s*=\s*(?:sanitize_[A-Za-z0-9_]+|flosc_sanitize_[A-Za-z0-9_]+|wp_kses(?:_post)?)\s*\([^;]*' . $raw_token . '[^;]*\)\s*;/s',
		$kb_save,
		$match,
		PREG_OFFSET_CAPTURE
	);
	$sanitize_at = isset( $match[0][1] ) ? $match[0][1] : -1;
}

flosc_wporg_check(
	'file_content has a string/scalar rejection guard before write',
	$scalar_at > $input_at && $scalar_at < $write_at,
	true
);
flosc_wporg_check(
	'file_content has a byte-size rejection guard before write',
	$size_at > $input_at && $size_at < $write_at,
	true
);
flosc_wporg_check(
	'only sanitized file_content reaches the writer',
	$sanitize_at > $input_at && $sanitize_at < $write_at,
	true
);

echo "\nRuntime PHP contains no raw script/style markup\n";
$tag_hits = flosc_wporg_regex_hits( $runtime, '~<(?:script|style)\b~i' );
flosc_wporg_check( 'no literal script or style opening tags', $tag_hits, array() );

echo "\nRuntime input filters never request unfiltered data\n";
$unsafe_filter_hits = flosc_wporg_regex_hits(
	$runtime,
	'/\bFILTER_(?:UNSAFE_RAW|DEFAULT)\b/'
);
flosc_wporg_check( 'no FILTER_UNSAFE_RAW or FILTER_DEFAULT flags', $unsafe_filter_hits, array() );

echo $fail ? "\n$fail FAILURES\n" : "\nThe September 13 WordPress.org regressions are closed\n";
exit( $fail ? 1 : 0 );
