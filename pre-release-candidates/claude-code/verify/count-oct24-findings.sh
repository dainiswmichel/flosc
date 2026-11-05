#!/usr/bin/env bash
# Count, in one built flosc.zip, the code patterns the WordPress.org review of
# 24 October 2026 (R flosc/dainismichel/2Jun26/T15 24Oct26/4.3) quoted.
#
# Usage: count-oct24-findings.sh path/to/flosc.zip
#
# Each line prints a count and the result that count must have. This script
# counts patterns; it is not Plugin Check and does not stand in for it.
set -uo pipefail

zip="${1:?usage: count-oct24-findings.sh path/to/flosc.zip}"
work="$(mktemp -d "${TMPDIR:-/tmp}/flosc-oct24.XXXXXX")"
trap 'rm -rf "$work"' EXIT
unzip -q "$zip" -d "$work"
root="$work/flosc"

# One pass: write a comment-free copy of every PHP file to $code, so a count
# sees calls, not prose that names a function.
code="$work/code"
php -r '
	$root = $argv[1]; $dest = $argv[2];
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( "php" !== $f->getExtension() ) { continue; }
		$out = "";
		foreach ( token_get_all( file_get_contents( $f->getPathname() ) ) as $t ) {
			if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
			$out .= is_array( $t ) ? $t[1] : $t;
		}
		$target = $dest . substr( $f->getPathname(), strlen( $root ) );
		@mkdir( dirname( $target ), 0777, true );
		file_put_contents( $target, $out );
	}
' "$root" "$code"

code_only() { cat "$code${1#"$root"}"; }

count_php() { # $1 = extended regex, counted in comment-free PHP
	grep -rhoE "$1" "$code" | wc -l | tr -d ' '
}

list_php() { # $1 = extended regex; prints file:count for non-zero files
	grep -rcE "$1" "$code" | grep -v ':0$' | sed "s#^$code/#    #" || true
}

echo "zip: $zip"
echo "sha256: $(shasum -a 256 "$zip" | awk '{print $1}')"
echo "entries: $(unzip -Z1 "$zip" | wc -l | tr -d ' ')"
echo

echo "Finding 1 — third-party URLs in shipped JavaScript"
n=$(grep -rhoE 'https?://(en\.wikipedia\.org|www\.gutenberg\.org|www\.biblegateway\.com|plato\.stanford\.edu|archive\.org|rsarchive\.org|www\.bdkamerica\.org|www\.nobelprize\.org|www\.viktorfrankl\.org|etcsl\.orinst\.ox\.ac\.uk)' "$root" --include='*.js' | wc -l || true | tr -d ' ')
echo "  citation-host URLs in *.js: $n (required 0)"
n=$(grep -rhoE 'https?://(en\.wikipedia\.org|www\.gutenberg\.org|www\.biblegateway\.com|plato\.stanford\.edu|rsarchive\.org|www\.bdkamerica\.org|www\.nobelprize\.org|www\.viktorfrankl\.org|etcsl\.orinst\.ox\.ac\.uk)' "$root" | wc -l || true | tr -d ' ')
echo "  citation-host URLs anywhere in the zip: $n (required 0)"
n=$(grep -cE 'url: "https?://' "$root/assets/js/flosc-personality-builder.js" || true)
echo "  url fields in personality builder: $n (required 0)"
echo

echo "Finding 2 — creating / logging in users (calls in comment-free PHP)"
echo "  wp_insert_user(:     $(count_php 'wp_insert_user\(') (expected 1, in includes/flosc-accounts.php)"
list_php 'wp_insert_user\('
echo "  wp_create_user(:     $(count_php 'wp_create_user\(') (expected 0)"
echo "  wp_set_password(:    $(count_php 'wp_set_password\(') (expected 0)"
echo "  wp_set_auth_cookie(: $(count_php 'wp_set_auth_cookie\(') (expected 1, in includes/flosc-accounts.php)"
list_php 'wp_set_auth_cookie\('
echo "  wp_set_current_user(:$(count_php 'wp_set_current_user\(') (expected 2: session helper + per-request REST token)"
list_php 'wp_set_current_user\('
echo "  wp_update_user(:     $(count_php 'wp_update_user\(') (expected 1: signed-in user edits own profile)"
list_php 'wp_update_user\('
echo

echo "Finding 3 — chat-log TSV export"
if grep -q "array_map( 'flosc_spreadsheet_text_cell', \$columns )" "$root/includes/class-flosc-framework.php"; then
	echo "  export encodes every cell with flosc_spreadsheet_text_cell(): yes (required yes)"
else
	echo "  export encodes every cell with flosc_spreadsheet_text_cell(): NO (required yes)"
fi
echo "  behaviour test: see tests/check_spreadsheet_export_cells.php in the candidate source"
echo

echo "Finding 4 — sanitize at read"
echo "  whole-superglobal wp_unslash( \$_POST|\$_GET|\$_REQUEST ): $(count_php 'wp_unslash\( *\$_(POST|GET|REQUEST) *\)') (required 0)"
list_php 'wp_unslash\( *\$_(POST|GET|REQUEST) *\)'
echo "  FILTER_CALLBACK: $(count_php 'FILTER_CALLBACK') (required 0)"
list_php 'FILTER_CALLBACK'
echo "  FILTER_UNSAFE_RAW / FILTER_DEFAULT: $(count_php 'FILTER_UNSAFE_RAW|FILTER_DEFAULT') (required 0)"
echo

echo "Finding 5 — PHP-built inline script / style"
echo "  flosc_capture_output( calls outside its definition: $(( $(count_php 'flosc_capture_output\(') - $(code_only "$root/includes/flosc-output-buffer.php" | grep -cE 'flosc_capture_output\(' || true) )) (expected 2, neither carries JavaScript: contact-form shortcode HTML in includes/flosc-admin.php, WordPress Importer HTML silenced in admin/ivr-upload-handler.php)"
list_php 'flosc_capture_output\('
echo "  wp_json_encode concatenated into a script string ('= ' . wp_json_encode): $(count_php "= ' *\. *wp_json_encode\(") (required 0)"
list_php "= ' *\. *wp_json_encode\("
echo "  <?php echo inside shipped .js files: $(grep -rl '<?php' "$root" --include='*.js' | wc -l || true | tr -d ' ') (required 0)"
