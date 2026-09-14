<?php
/**
 * Regression gate for request acquisition called out by WordPress.org review.
 *
 * FILTER_UNSAFE_RAW, FILTER_DEFAULT, and filter_input() without a sanitizing
 * filter all return unsanitized values. The reviewed call sites now acquire
 * scalar request values through WordPress's wp_unslash() plus a sanitizer or
 * a strict allowlist. Keeping this as a source gate prevents those unsafe
 * acquisition patterns from quietly returning in the same surfaces.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;

/**
 * Print one TAP-like assertion.
 *
 * @param string $label Assertion label.
 * @param bool   $pass  Whether it passed.
 * @return void
 */
function flosc_input_check( $label, $pass ) {
	global $fail;
	if ( ! $pass ) {
		$fail++;
	}
	printf( "%s %s\n", $pass ? 'ok  ' : 'FAIL', $label );
}

$files = array(
	'admin/flosc-app.php',
	'admin/token-management.php',
	'includes/email/class-flosc-email.php',
	'includes/flosc-admin.php',
	'includes/flosc-personality-library.php',
	'includes/companion-mode/class-flosc-companion-mode.php',
	'includes/sso/class-sso-manager.php',
	'includes/sso/providers/class-apple-provider.php',
	'includes/magic-link/class-flosc-magic-link-trait.php',
	'includes/tokens/class-flosc-visitor-token-trait.php',
);

echo "Reviewed request-acquisition surfaces\n";
foreach ( $files as $relative ) {
	$source = file_get_contents( $root . '/' . $relative );
	flosc_input_check( $relative . ' is readable', false !== $source );
	if ( false === $source ) {
		continue;
	}

	flosc_input_check( $relative . ' has no FILTER_UNSAFE_RAW', false === strpos( $source, 'FILTER_UNSAFE_RAW' ) );
	flosc_input_check( $relative . ' has no FILTER_DEFAULT', false === strpos( $source, 'FILTER_DEFAULT' ) );
	flosc_input_check( $relative . ' has no filter_input() acquisition', 0 === preg_match( '/\bfilter_input\s*\(/', $source ) );
}

echo "\nCase-sensitive authentication values\n";
$magic_link = (string) file_get_contents( $root . '/includes/magic-link/class-flosc-magic-link-trait.php' );
flosc_input_check(
	'magic-link query tokens use sanitize_text_field()',
	false !== strpos( $magic_link, 'sanitize_text_field( wp_unslash( $_GET[ $flosc_qk ] ) )' )
);
flosc_input_check(
	'magic-link query tokens are not lowercased with sanitize_key()',
	false === strpos( $magic_link, 'sanitize_key( wp_unslash( $_GET[ $flosc_qk ] ) )' )
);

echo "\nWhole shipped PHP tree\n";
$remaining = array();
$iterator  = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$path = $file->getPathname();
	if ( strpos( $path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR ) !== false ) {
		continue;
	}
	$source = (string) file_get_contents( $path );
	if ( preg_match( '/\bfilter_input\s*\(/', $source ) || strpos( $source, 'FILTER_UNSAFE_RAW' ) !== false || strpos( $source, 'FILTER_DEFAULT' ) !== false ) {
		$remaining[] = substr( $path, strlen( $root ) + 1 );
	}
}
flosc_input_check( 'no shipped PHP file uses the rejected acquisition patterns', array() === $remaining );
if ( $remaining ) {
	echo '     remaining: ' . implode( ', ', $remaining ) . "\n";
}

echo $fail ? "\n{$fail} FAILURES\n" : "\nAll shipped request surfaces use WordPress sanitization\n";
exit( $fail ? 1 : 0 );
