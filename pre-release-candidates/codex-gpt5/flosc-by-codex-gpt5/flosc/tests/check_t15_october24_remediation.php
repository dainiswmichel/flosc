<?php
/**
 * Regression checks for the WordPress.org review dated 2026-10m-24d (T15).
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$flosc_root     = dirname( __DIR__ );
$flosc_failures = array();

/**
 * Record one T15 assertion.
 *
 * @param bool   $condition Whether the assertion passed.
 * @param string $message   Assertion description.
 * @return void
 */
function flosc_t15_assert( $condition, $message ) {
	global $flosc_failures;

	if ( $condition ) {
		echo "ok   {$message}\n";
		return;
	}

	$flosc_failures[] = $message;
	echo "FAIL {$message}\n";
}

/**
 * Read a project-relative text file.
 *
 * @param string $relative Relative path.
 * @return string
 */
function flosc_t15_read( $relative ) {
	global $flosc_root;

	$contents = file_get_contents( $flosc_root . '/' . $relative );
	return false === $contents ? '' : (string) $contents;
}

$flosc_personality_js   = flosc_t15_read( 'assets/js/flosc-personality-builder.js' );
$flosc_personality_docs = flosc_t15_read( 'flosc_documentation/chapters/personality-builder-reference-sources.md' );
$flosc_auth_docs        = flosc_t15_read( 'flosc_documentation/chapters/user-creation-and-authentication-security.md' );
$flosc_framework        = flosc_t15_read( 'includes/class-flosc-framework.php' );

flosc_t15_assert(
	0 === preg_match( '#https?://#i', $flosc_personality_js ),
	'personality runtime code contains no external reference URLs'
);
flosc_t15_assert(
	false !== strpos( $flosc_personality_docs, 'https://www.gutenberg.org/ebooks/31' )
		&& false !== strpos( $flosc_personality_docs, 'https://www.biblegateway.com/passage/?search=Genesis+6&version=KJV' )
		&& false !== strpos( $flosc_personality_docs, 'https://plato.stanford.edu/entries/plato/' ),
	'removed personality citations and their character context are retained in documentation'
);
flosc_t15_assert(
	false !== strpos( $flosc_auth_docs, 'wp_insert_user()' )
		&& false !== strpos( $flosc_auth_docs, 'wp_set_auth_cookie()' )
		&& false !== strpos( $flosc_auth_docs, 'MagicLink' )
		&& false !== strpos( $flosc_auth_docs, 'OAuth' ),
	'user creation and login necessity, core APIs, and safeguards are documented'
);
flosc_t15_assert(
	false !== strpos( $flosc_framework, "array( '=', '+', '-', '@' )" )
		&& false !== strpos( $flosc_framework, '$value = "\'" . $value;' ),
	'TSV exports neutralize spreadsheet-formula prefixes'
);

$flosc_filter_callback_files = array();
$flosc_inline_admin_files    = array();
$flosc_iterator              = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $flosc_root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $flosc_iterator as $flosc_file ) {
	if ( ! $flosc_file->isFile() || 'php' !== $flosc_file->getExtension() ) {
		continue;
	}

	$flosc_path = str_replace( '\\', '/', $flosc_file->getPathname() );
	if ( false !== strpos( $flosc_path, '/tests/' ) || false !== strpos( $flosc_path, '/vendor/' ) ) {
		continue;
	}

	$flosc_source   = (string) file_get_contents( $flosc_file->getPathname() );
	$flosc_relative = str_replace( $flosc_root . '/', '', $flosc_path );
	if ( false !== strpos( $flosc_source, 'FILTER_CALLBACK' ) ) {
		$flosc_filter_callback_files[] = $flosc_relative;
	}
	if ( 0 === strpos( $flosc_relative, 'admin/' ) && false !== strpos( $flosc_source, 'wp_add_inline_script' ) ) {
		$flosc_inline_admin_files[] = $flosc_relative;
	}
}

flosc_t15_assert(
	array() === $flosc_filter_callback_files,
	'no shipping PHP request boundary uses FILTER_CALLBACK'
);
flosc_t15_assert(
	array() === $flosc_inline_admin_files,
	'reviewer-cited admin JavaScript lives in enqueued asset files'
);

if ( $flosc_failures ) {
	echo "\n" . count( $flosc_failures ) . " T15 regression check(s) failed.\n";
	exit( 1 );
}

echo "\nAll T15 regression checks passed.\n";
