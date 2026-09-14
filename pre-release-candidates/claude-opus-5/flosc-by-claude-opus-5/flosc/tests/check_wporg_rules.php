<?php
/**
 * check_wporg_rules.php — the rules WordPress.org actually rejected this plugin for.
 *
 * Plugin Check has been run on every submission since 31 May 2026 — through
 * wordpress.org's own hosted tool, then locally from 11 July — and came back
 * clean every time. The plugin was rejected anyway, on 14 June, 27 June,
 * 12 July and 13 September.
 *
 * So the findings in those four emails are, by definition, a list of things
 * Plugin Check does not detect. This file encodes them.
 *
 * Every rule below cites the review email that raised it. No rule asserts a
 * snapshot value: tests/check_packaging.php asserted "Requires at least: 7.0.4"
 * as correct for three rounds because somebody wrote down what the code said
 * instead of what the standard requires. A rule here describes the SHAPE a
 * value must have, never the value.
 *
 * WHAT THIS CANNOT DO: WordPress.org runs Plugin Check plus an AI pass that
 * reads intent across call paths ("loads the core importer bootstrap even
 * though no function from import.php is subsequently used by this import
 * path"). Pattern matching does not reach that. Of the five T12 findings this
 * file targets four; the SSO redirect-host finding is out of its reach and is
 * named as such at the end of the run.
 *
 * Usage, from the plugin root:
 *
 *     php tests/check_wporg_rules.php
 *     WP_CURRENT=6.9 php tests/check_wporg_rules.php
 *
 * Exit 0 only when no rule fired.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
chdir( $root );

/* ---------------------------------------------------------------------------
 * Which files are code that ships. Docs and tests are excluded because the
 * artifact excludes them (.distignore), so a rule firing there is noise.
 * ------------------------------------------------------------------------ */
function flosc_source_files( $root ) {
	$out  = array();
	$skip = array( '/.git/', '/tests/', '/admin/docs/', '/flosc_documentation/', '/node_modules/', '/vendor/' );
	$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$p = str_replace( $root, '', $f->getPathname() );
		if ( substr( $p, -4 ) !== '.php' ) {
			continue;
		}
		foreach ( $skip as $s ) {
			if ( strpos( $p, $s ) !== false ) {
				continue 2;
			}
		}
		$out[ ltrim( $p, '/' ) ] = file( $f->getPathname(), FILE_IGNORE_NEW_LINES );
	}
	ksort( $out );
	return $out;
}

/* ---------------------------------------------------------------------------
 * Reviewed exceptions.
 *
 * tests/wporg-rule-exceptions.txt, one per line:
 *
 *     path/to/file.php:123:RULE-ID:why this one is genuinely fine
 *
 * An exception with no reason is itself an error — an exceptions file is how a
 * gate quietly stops testing anything, so each one has to be argued in writing
 * and the count is printed on every run.
 * ------------------------------------------------------------------------ */
$exceptions = array();
$exc_bad    = array();
$exc_path   = $root . '/tests/wporg-rule-exceptions.txt';
if ( is_readable( $exc_path ) ) {
	foreach ( file( $exc_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		if ( $line === '' || $line[0] === '#' ) {
			continue;
		}
		$parts = explode( ':', $line, 4 );
		if ( count( $parts ) < 4 || trim( $parts[3] ) === '' ) {
			$exc_bad[] = $line;
			continue;
		}
		$exceptions[ $parts[0] . ':' . $parts[1] . ':' . $parts[2] ] = trim( $parts[3] );
	}
}

$FINDINGS = array();
$RULES    = array();

function rule( $id, $title, $source ) {
	global $RULES;
	$RULES[ $id ] = array( 'title' => $title, 'source' => $source, 'hits' => 0 );
}

function finding( $id, $file, $line, $text ) {
	global $FINDINGS, $RULES, $exceptions;
	$key = $file . ':' . $line . ':' . $id;
	if ( isset( $exceptions[ $key ] ) ) {
		return;
	}
	$FINDINGS[ $id ][] = array( 'file' => $file, 'line' => $line, 'text' => trim( $text ) );
	$RULES[ $id ]['hits']++;
}

$src = flosc_source_files( $root );

/* =========================================================================
 * WPORG-01 — WordPress version headers
 *
 * T12, 13 Sep 2026: "Requires at least at readme.txt: '7.0.4' is expected to
 * be the lowest WordPress version... Please, include only the major WordPress
 * version, as the minor version is ignored."
 *
 * The shape, not the value: major.minor, no third digit. The readme and the
 * main file must agree. tests/check_packaging.php compared them to EACH OTHER
 * and they agreed while both were wrong, so agreement alone is not the test.
 * ====================================================================== */
rule( 'WPORG-01', 'WordPress version headers have the right shape', 'T12 13Sep26' );

$readme = is_readable( "$root/readme.txt" ) ? file_get_contents( "$root/readme.txt" ) : '';
$main   = is_readable( "$root/flosc.php" ) ? file_get_contents( "$root/flosc.php" ) : '';

$hdr = array();
preg_match( '/^Requires at least:\s*(\S+)/m', $readme, $m ) && $hdr['readme.txt Requires at least'] = $m[1];
preg_match( '/^Tested up to:\s*(\S+)/m', $readme, $m ) && $hdr['readme.txt Tested up to'] = $m[1];
preg_match( '/^ \* Requires at least:\s*(\S+)/m', $main, $m ) && $hdr['flosc.php Requires at least'] = $m[1];

foreach ( $hdr as $label => $value ) {
	if ( ! preg_match( '/^\d+\.\d+$/', $value ) ) {
		$why = preg_match( '/^\d+\.\d+\.\d+/', $value )
			? "carries a patch digit — WordPress.org ignores the minor version and returns this as an ERROR"
			: "is not a major.minor WordPress version";
		finding( 'WPORG-01', basename( strpos( $label, 'readme' ) === 0 ? 'readme.txt' : 'flosc.php' ), 0, "$label: \"$value\" $why" );
	}
}
if ( isset( $hdr['readme.txt Requires at least'], $hdr['flosc.php Requires at least'] )
	&& $hdr['readme.txt Requires at least'] !== $hdr['flosc.php Requires at least'] ) {
	finding( 'WPORG-01', 'readme.txt', 0, 'Requires at least disagrees between readme.txt and flosc.php' );
}

$wp_current = getenv( 'WP_CURRENT' );
if ( $wp_current && isset( $hdr['readme.txt Tested up to'] ) ) {
	if ( version_compare( $hdr['readme.txt Tested up to'], $wp_current, '<' ) ) {
		finding( 'WPORG-01', 'readme.txt', 0, "Tested up to {$hdr['readme.txt Tested up to']} is behind the current WordPress {$wp_current} — wp.org will not list it" );
	}
}

/* =========================================================================
 * WPORG-02 — core file loaded and then not used
 *
 * T12: "require_once ABSPATH . 'wp-admin/includes/import.php'; — Loads the
 * core importer bootstrap even though no function from import.php is
 * subsequently used by this import path."
 *
 * The guideline permits loading a core file when a function from it is used
 * immediately after. Twenty such includes in this tree were NOT flagged for
 * exactly that reason. Only the one that loads and then uses nothing was.
 * ====================================================================== */
rule( 'WPORG-02', 'A core file is loaded and then actually used', 'T12 13Sep26' );

$core_fns = array(
	'file.php'     => array( 'WP_Filesystem', 'request_filesystem_credentials', 'wp_handle_upload', 'wp_handle_sideload', 'download_url', 'unzip_file', 'wp_tempnam', 'validate_file_to_edit' ),
	'media.php'    => array( 'media_handle_upload', 'media_handle_sideload', 'wp_read_image_metadata', 'media_sideload_image' ),
	'image.php'    => array( 'wp_generate_attachment_metadata', 'wp_crop_image', 'wp_read_image_metadata' ),
	'import.php'   => array( 'get_importers', 'register_importer', 'wp_import_handle_upload', 'wp_import_cleanup' ),
	'upgrade.php'  => array( 'dbDelta', 'wp_install', 'make_db_current_silent', 'wp_upgrade' ),
	'plugin.php'   => array( 'get_plugins', 'activate_plugin', 'is_plugin_active', 'get_plugin_data' ),
	'template.php' => array( 'wp_admin_css', 'add_meta_box', 'do_meta_boxes' ),
);

foreach ( $src as $file => $lines ) {
	foreach ( $lines as $i => $line ) {
		if ( ! preg_match( "#(?:require|include)(?:_once)?\s+ABSPATH\s*\.\s*'[^']*/([a-z\-]+\.php)'#", $line, $m ) ) {
			continue;
		}
		$core = $m[1];
		if ( ! isset( $core_fns[ $core ] ) ) {
			finding( 'WPORG-02', $file, $i + 1, "loads core {$core} — unknown to this rule, verify a function from it is used right after" );
			continue;
		}
		/* Look ahead 30 lines for a call to something that file provides. */
		$window = implode( "\n", array_slice( $lines, $i + 1, 30 ) );
		$used   = false;
		foreach ( $core_fns[ $core ] as $fn ) {
			if ( preg_match( '/\b' . preg_quote( $fn, '/' ) . '\s*\(/', $window ) ) {
				$used = true;
				break;
			}
		}
		if ( ! $used ) {
			finding( 'WPORG-02', $file, $i + 1, "loads core {$core} and uses nothing from it within 30 lines" );
		}
	}
}

/* =========================================================================
 * WPORG-03 — file and directory locations built from constants
 *
 * T12: "$importer_path = WP_PLUGIN_DIR . '/wordpress-importer/...' — The
 * hardcoded wordpress-importer plugin subdirectory and main file can fail if
 * that plugin is installed in a differently named directory."
 * ====================================================================== */
rule( 'WPORG-03', 'Locations resolved, not built from WP_* constants', 'T12 13Sep26' );

foreach ( $src as $file => $lines ) {
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/\b(WP_PLUGIN_DIR|WP_PLUGIN_URL|WP_CONTENT_DIR|WP_CONTENT_URL)\b/', $line, $m ) ) {
			finding( 'WPORG-03', $file, $i + 1, "{$m[1]} — use plugin_dir_path(), plugin_dir_url(), plugins_url() or wp_upload_dir()" );
		}
	}
}

/* =========================================================================
 * WPORG-04 — filter_input without a sanitizing filter
 *
 * T12: "Leaving the filter parameter empty, PHP by default will apply the
 * filter FILTER_DEFAULT which is not sanitizing at all."
 * ====================================================================== */
rule( 'WPORG-04', 'filter_input carries a sanitizing filter', 'T12 13Sep26' );

foreach ( $src as $file => $lines ) {
	foreach ( $lines as $i => $line ) {
		if ( strpos( $line, 'filter_input' ) === false ) {
			continue;
		}
		if ( preg_match( '/filter_input(?:_array)?\s*\([^)]*FILTER_(DEFAULT|UNSAFE_RAW)/', $line, $m ) ) {
			finding( 'WPORG-04', $file, $i + 1, "FILTER_{$m[1]} does not sanitize" );
		} elseif ( preg_match( '/filter_input\s*\(\s*INPUT_[A-Z]+\s*,\s*[^,()]+\s*\)/', $line ) ) {
			finding( 'WPORG-04', $file, $i + 1, 'no filter argument — PHP applies FILTER_DEFAULT, which does not sanitize' );
		}
	}
}

/* =========================================================================
 * WPORG-05 — a superglobal assigned without sanitizing on the same line
 *
 * T11 and T12: "$flosc_post = wp_unslash($_POST); ... later used to write
 * ivr_full_text directly to the IVR file without sanitization."
 *
 * wp_unslash() is not a sanitizer. It removes slashes.
 * ====================================================================== */
rule( 'WPORG-05', 'Request data sanitized where it is read', 'T11 12Jul26, T12 13Sep26' );

$sanitizers = 'sanitize_|esc_url_raw|absint|intval|\(int\)|\(float\)|floatval|wp_kses|wp_verify_nonce|check_admin_referer|check_ajax_referer|flosc_sanitize_';
foreach ( $src as $file => $lines ) {
	foreach ( $lines as $i => $line ) {
		if ( ! preg_match( '/=\s*(?:wp_unslash\s*\(\s*)?\$_(POST|GET|REQUEST|COOKIE|SERVER)\b/', $line, $m ) ) {
			continue;
		}
		if ( preg_match( '/' . $sanitizers . '/', $line ) ) {
			continue;
		}
		finding( 'WPORG-05', $file, $i + 1, "\$_{$m[1]} assigned with no sanitizer on this line (wp_unslash is not a sanitizer)" );
	}
}

/* =========================================================================
 * WPORG-06 — register_setting() without a sanitize_callback
 *
 * T10, 27 Jun 2026 and T11, 12 Jul 2026.
 * ====================================================================== */
rule( 'WPORG-06', 'register_setting() declares a sanitize_callback', 'T10 27Jun26, T11 12Jul26' );

foreach ( $src as $file => $lines ) {
	$joined = implode( "\n", $lines );
	if ( ! preg_match_all( '/register_setting\s*\(/', $joined, $mm, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}
	foreach ( $mm[0] as $hit ) {
		$line_no = substr_count( substr( $joined, 0, $hit[1] ), "\n" ) + 1;
		$window  = substr( $joined, $hit[1], 600 );
		if ( strpos( $window, 'sanitize_callback' ) === false ) {
			finding( 'WPORG-06', $file, $line_no, 'register_setting() with no sanitize_callback within 600 chars' );
		}
	}
}

/* =========================================================================
 * WPORG-07 — register_rest_route() permission_callback
 *
 * T10: "__return_true ... the handler returns admin-authored conversation
 * messages without any authentication"; "is_user_logged_in lets guests
 * enumerate premium lesson information."
 *
 * A public endpoint is legitimate. This rule reports every route and its
 * callback so each one is looked at, rather than guessing which are fine.
 * ====================================================================== */
rule( 'WPORG-07', 'Every REST route has a permission_callback that fits it', 'T10 27Jun26' );

foreach ( $src as $file => $lines ) {
	$joined = implode( "\n", $lines );
	if ( ! preg_match_all( '/register_rest_route\s*\(/', $joined, $mm, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}
	foreach ( $mm[0] as $hit ) {
		$line_no = substr_count( substr( $joined, 0, $hit[1] ), "\n" ) + 1;
		$window  = substr( $joined, $hit[1], 800 );
		if ( strpos( $window, 'permission_callback' ) === false ) {
			finding( 'WPORG-07', $file, $line_no, 'REST route with NO permission_callback' );
		} elseif ( strpos( $window, '__return_true' ) !== false ) {
			finding( 'WPORG-07', $file, $line_no, "permission_callback is __return_true — intentional only if the data is genuinely public" );
		} elseif ( preg_match( "/'permission_callback'\s*=>\s*'is_user_logged_in'/", $window ) ) {
			finding( 'WPORG-07', $file, $line_no, "permission_callback is is_user_logged_in — too weak if the route returns gated or admin data" );
		}
	}
}

/* =========================================================================
 * WPORG-08 — json_decode on request data
 *
 * T11: "json_decode() ... does not sanitize the input. Any potentially
 * malicious data or scripts may persist after json_decode()."
 * ====================================================================== */
rule( 'WPORG-08', 'json_decode output from a request is field-sanitized', 'T11 12Jul26' );

foreach ( $src as $file => $lines ) {
	foreach ( $lines as $i => $line ) {
		if ( strpos( $line, 'json_decode' ) === false ) {
			continue;
		}
		if ( preg_match( '/json_decode\s*\([^;]*(\$_(POST|GET|REQUEST|COOKIE)|\$post\[|\$request\[|\$raw|_raw)/', $line ) ) {
			finding( 'WPORG-08', $file, $i + 1, 'json_decode on request-derived data — decode is not sanitizing; each field must be sanitized after' );
		}
	}
}

/* =========================================================================
 * WPORG-09 — inline <script> and <style>
 *
 * T10, T11 and T12, closing section every time: "admin screens are not
 * considered an exception, and neither are inline styles or scripts."
 * ====================================================================== */
rule( 'WPORG-09', 'No inline script or style tags', 'T10, T11, T12' );

foreach ( $src as $file => $lines ) {
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/<(script|style)[\s>]/i', $line, $m ) ) {
			finding( 'WPORG-09', $file, $i + 1, "inline <{$m[1]}> — enqueue it with wp_enqueue_script() / wp_enqueue_style()" );
		}
	}
}

/* =========================================================================
 * Report. Raw counts and every file:line. No summary verdict beyond the total.
 * ====================================================================== */
$total = 0;
foreach ( $RULES as $id => $r ) {
	$total += $r['hits'];
}

echo "===========================================================================\n";
echo " FLOSC — the rules WordPress.org rejected this plugin for\n";
echo " Encoded from the review emails of 14 Jun, 27 Jun, 12 Jul and 13 Sep 2026.\n";
echo " Plugin Check was clean on every one of those submissions.\n";
echo "===========================================================================\n\n";

if ( ! empty( $exc_bad ) ) {
	echo "MALFORMED EXCEPTIONS (an exception with no written reason is not an exception):\n";
	foreach ( $exc_bad as $b ) {
		echo "  $b\n";
	}
	echo "\n";
	$total += count( $exc_bad );
}

foreach ( $RULES as $id => $r ) {
	printf( "%-10s %-52s %s\n", $id, $r['title'], $r['hits'] === 0 ? 'clear' : $r['hits'] . ' FOUND' );
	printf( "%-10s source: %s\n", '', $r['source'] );
	if ( ! empty( $FINDINGS[ $id ] ) ) {
		foreach ( $FINDINGS[ $id ] as $f ) {
			printf( "           %s:%d\n             %s\n", $f['file'], $f['line'], $f['text'] );
		}
	}
	echo "\n";
}

echo "---------------------------------------------------------------------------\n";
printf( "  active exceptions : %d  (tests/wporg-rule-exceptions.txt)\n", count( $exceptions ) );
printf( "  files scanned     : %d\n", count( $src ) );
printf( "  findings          : %d\n", $total );
echo "---------------------------------------------------------------------------\n\n";

echo "OUT OF REACH OF THIS FILE — do not read a clear run as coverage of these:\n";
echo "  * The T12 SSO redirect-host finding. WordPress.org's AI traced an\n";
echo "    untrusted host into an auth-flow allowlist across call paths.\n";
echo "    Pattern matching does not do that. It stays a human read.\n";
echo "  * Anything their AI flags that has not been flagged before. This file\n";
echo "    knows four emails. It cannot know the fifth.\n\n";

exit( $total > 0 ? 1 : 0 );
