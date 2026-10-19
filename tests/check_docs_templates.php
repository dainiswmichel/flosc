<?php
/**
 * Render every admin documentation template and check what comes out.
 *
 * The nine files under admin/docs are included by admin/documentation.php into
 * the Documentation tab. They are mostly prose, but three of them build admin
 * URLs, and those URLs are built inside href="" attributes -- a place where a
 * formatting pass can put a newline without anything failing loudly. v82.9 hit
 * exactly that: twenty-three links rendered as href="\n...\n" and still worked,
 * which is the kind of change no error message reports.
 *
 * So this runs them. WordPress is not loaded; the handful of functions these
 * templates call are stubbed to return something recognisable, which is enough
 * to prove each file parses, executes, emits markup, and produces attribute
 * values with no newlines in them.
 *
 * Exit 0 when every template renders clean, 1 with a report otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'FLOSC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

/**
 * Stand in for WordPress's add_query_arg() closely enough to compare output.
 *
 * @param array  $args Query arguments.
 * @param string $url  Base URL.
 * @return string URL with the arguments appended.
 */
function add_query_arg( $args, $url ) {
	return $url . '?' . http_build_query( $args );
}

/**
 * Stand in for admin_url().
 *
 * @param string $path Path below wp-admin.
 * @return string Absolute admin URL.
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

/**
 * Stand in for esc_url(). Deliberately does not trim, so a newline that reached
 * an attribute value survives to be caught below.
 *
 * @param string $url URL to escape.
 * @return string Escaped URL.
 */
function esc_url( $url ) {
	return str_replace( array( '&', '"', '<' ), array( '&amp;', '&quot;', '&lt;' ), (string) $url );
}

/**
 * Stand in for esc_html().
 *
 * @param string $text Text to escape.
 * @return string Escaped text.
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

/**
 * Stand in for esc_attr().
 *
 * @param string $text Text to escape.
 * @return string Escaped attribute value.
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

/**
 * Stand in for sanitize_file_name(): keeps the characters a flow id may use.
 *
 * @param string $name Candidate file name.
 * @return string Sanitized name.
 */
function sanitize_file_name( $name ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name );
}

/**
 * Stand in for sanitize_key().
 *
 * @param string $key Candidate key.
 * @return string Lowercase key with only word characters and hyphens.
 */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/**
 * Stand in for sanitize_text_field().
 *
 * @param string $text Candidate text.
 * @return string Sanitized text.
 */
function sanitize_text_field( $text ) {
	return trim( wp_strip_all_tags( (string) $text ) );
}

/**
 * Stand in for wp_strip_all_tags().
 *
 * @param string $text Text to strip.
 * @return string Text without markup.
 */
function wp_strip_all_tags( $text ) {
	return preg_replace( '/<[^>]*>/', '', (string) $text );
}

/**
 * Stand in for __(): translation is not what this gate is testing.
 *
 * @param string $text   Text to translate.
 * @param string $domain Text domain, ignored.
 * @return string The text unchanged.
 */
function __( $text, $domain = 'flosc' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound -- matches the WordPress signature this stands in for.
	unset( $domain );
	return $text;
}

$flosc_docs_dir = dirname( __DIR__ ) . '/admin/docs';
$flosc_files    = glob( $flosc_docs_dir . '/*.php' );
sort( $flosc_files );

$flosc_failures = array();
$flosc_rendered = 0;

foreach ( $flosc_files as $flosc_file ) {
	$flosc_name = basename( $flosc_file );

	// What admin/documentation.php has in scope when it includes these.
	$selected_ivr = 'sample-flow';

	ob_start();
	try {
		include $flosc_file;
		$flosc_html = ob_get_clean();
	} catch ( Throwable $flosc_error ) {
		ob_end_clean();
		$flosc_failures[] = $flosc_name . ': threw ' . get_class( $flosc_error ) . ' -- ' . $flosc_error->getMessage();
		continue;
	}

	++$flosc_rendered;

	if ( '' === trim( $flosc_html ) ) {
		$flosc_failures[] = $flosc_name . ': rendered nothing';
		continue;
	}

	// An attribute value must not contain a newline. Catches a URL that a
	// formatting pass pushed onto its own line inside href="" or src="".
	if ( preg_match_all( '/\b(?:href|src|action)="([^"]*)"/', $flosc_html, $flosc_matches ) ) {
		foreach ( $flosc_matches[1] as $flosc_value ) {
			if ( false !== strpos( $flosc_value, "\n" ) ) {
				$flosc_failures[] = $flosc_name . ': attribute value contains a newline -- ' . str_replace( "\n", '\n', $flosc_value );
				break;
			}
		}
	}

	// A link the page means to build must not come out empty.
	if ( preg_match( '/<a href="">\s*Open (?:admin tab|tab|docs section)/', $flosc_html ) ) {
		$flosc_failures[] = $flosc_name . ': an "Open ..." link rendered with an empty href';
	}

	// An unresolved PHP tag in the output means a block boundary was mangled.
	if ( false !== strpos( $flosc_html, '<?php' ) ) {
		$flosc_failures[] = $flosc_name . ': "<?php" appears in the rendered output';
	}
}

printf( "check_docs_templates: rendered %d of %d templates\n", $flosc_rendered, count( $flosc_files ) );

if ( $flosc_failures ) {
	foreach ( $flosc_failures as $flosc_failure ) {
		echo '  FAIL  ' . $flosc_failure . "\n";
	}
	exit( 1 );
}

echo "  PASS  every template renders, every attribute value is on one line\n";
exit( 0 );
