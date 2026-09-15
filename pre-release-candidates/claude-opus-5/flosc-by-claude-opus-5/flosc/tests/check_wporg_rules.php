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
/**
 * A line of prose is not a line of code.
 *
 * WPORG-03 fired on a comment that merely NAMED WP_PLUGIN_DIR while explaining
 * why the constant had been removed. A rule that cannot tell an explanation from
 * an instruction produces work that does nothing, which is the same cost as a
 * missed finding paid in the other direction.
 *
 * @param string $line
 * @return bool
 */
function flosc_is_comment_line( $line ) {
	$t = ltrim( $line );
	if ( $t === '' ) {
		return true;
	}
	/* A one-line PHP comment tag: <?php // ... ?> — the shape this codebase uses
	   to explain, right where it used to live, that a block was moved to
	   wp_enqueue_*(). Missing it made WPORG-09 fire on the very comments that
	   record the fix. */
	if ( preg_match( '/^<\?php\s*(?:\/\/|\/\*|#)/', $t ) ) {
		return true;
	}
	return $t[0] === '*' || strpos( $t, '//' ) === 0 || strpos( $t, '/*' ) === 0 || strpos( $t, '#' ) === 0;
}

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

/*
 * Class files are used with `new` or class_exists(), not by calling a function.
 *
 * The first cut of this rule looked only for function calls, so
 * class-wp-filesystem-direct.php loaded and then instantiated four lines later
 * read as "uses nothing from it" — eleven false alarms across three files, on
 * code that is correct. A false positive acted on is destructacoding, so the
 * symbol a file provides has to be matched the way that file is actually used.
 */
$core_classes = array(
	'class-wp-filesystem-base.php'   => 'WP_Filesystem_Base',
	'class-wp-filesystem-direct.php' => 'WP_Filesystem_Direct',
	'class-wp-filesystem-ftpext.php' => 'WP_Filesystem_FTPext',
	'class-wp-filesystem-ssh2.php'   => 'WP_Filesystem_SSH2',
	'class-wp-upgrader.php'          => 'WP_Upgrader',
	'class-wp-list-table.php'        => 'WP_List_Table',
);

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
	$n = count( $lines );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( flosc_is_comment_line( $lines[ $i ] )
			|| ! preg_match( "#(?:require|include)(?:_once)?\s+ABSPATH\s*\.\s*'[^']*/([a-z\-]+\.php)'#", $lines[ $i ] ) ) {
			continue;
		}

		/*
		 * Core includes arrive in BLOCKS, and one symbol satisfies the block.
		 *
		 * ivr-upload-handler.php loads file.php, media.php and image.php inside
		 * a single `if ( ! function_exists( 'media_handle_sideload' ) )` guard
		 * and calls media_handle_sideload() eight lines down. Judged one file at
		 * a time against only its OWN symbols, file.php and image.php read as
		 * unused — a false alarm on correct code, and acting on it would break
		 * an upload path. The same shape appears wherever file.php is loaded
		 * beside the WP_Filesystem_Direct class files.
		 *
		 * So: collect the contiguous run of includes, take the UNION of what
		 * they provide, and ask once whether anything in that union is used.
		 */
		$block   = array();
		$first   = $i;
		while ( $i < $n
			&& preg_match( "#(?:require|include)(?:_once)?\s+ABSPATH\s*\.\s*'[^']*/([a-z\-]+\.php)'#", $lines[ $i ], $bm ) ) {
			$block[ $bm[1] ] = $i + 1;
			$i++;
		}
		$i--; /* the for-loop increments past the last include */

		$window   = implode( "\n", array_slice( $lines, $i + 1, 30 ) );
		$used     = false;
		$unknown  = array();

		foreach ( array_keys( $block ) as $core ) {
			if ( isset( $core_classes[ $core ] ) ) {
				$cls = preg_quote( $core_classes[ $core ], '/' );
				if ( preg_match( '/(?:new\s+' . $cls . '\b|class_exists\s*\(\s*[\x27"]' . $cls . '|extends\s+' . $cls . '\b|' . $cls . '::)/', $window ) ) {
					$used = true;
				}
			} elseif ( isset( $core_fns[ $core ] ) ) {
				foreach ( $core_fns[ $core ] as $fn ) {
					if ( preg_match( '/\b' . preg_quote( $fn, '/' ) . '\s*\(/', $window ) ) {
						$used = true;
						break;
					}
				}
			} else {
				$unknown[] = $core;
			}
		}

		if ( $used ) {
			continue;
		}

		$names = implode( ', ', array_keys( $block ) );
		if ( ! empty( $unknown ) ) {
			finding( 'WPORG-02', $file, $first + 1, "loads {$names} — " . implode( ', ', $unknown ) . " unknown to this rule, verify a symbol from the block is used right after" );
		} else {
			finding( 'WPORG-02', $file, $first + 1, "loads {$names} and uses nothing from any of them within 30 lines" );
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
		if ( flosc_is_comment_line( $line ) ) {
			continue;
		}
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
		if ( strpos( $line, 'filter_input' ) === false || flosc_is_comment_line( $line ) ) {
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
 * WPORG-09 — inline <script> and <style>
 *
 * T10, T11 and T12, closing section every time: "admin screens are not
 * considered an exception, and neither are inline styles or scripts."
 * ====================================================================== */
rule( 'WPORG-09', 'No inline script or style tags', 'T10, T11, T12' );

foreach ( $src as $file => $lines ) {
	foreach ( $lines as $i => $line ) {
		if ( flosc_is_comment_line( $line ) ) {
			continue;
		}
		if ( preg_match( '/<(script|style)[\s>]/i', $line, $m ) ) {
			finding( 'WPORG-09', $file, $i + 1, "inline <{$m[1]}> — enqueue it with wp_enqueue_script() / wp_enqueue_style()" );
		}
	}
}

/* =========================================================================
 * WPORG-10 — origin or identity established before request data is read
 *
 * T13, 14 Sep 2026, a category that had never appeared before: "No nonce check
 * found validating input origin on lines 1-116", "The settings POST processor
 * stores administrative settings without validating a nonce before processing
 * the request", "The AJAX audio-serving endpoint lacks a request-origin check
 * and authorization before serving user-associated audio."
 *
 * Note what their scanner measures: not whether a check EXISTS in the function,
 * but whether one appears BEFORE the request is read. Several sites in this
 * tree verified correctly and verified late, and late did not count -- an
 * unauthorized request still walked through the whole parser before being
 * refused.
 *
 * So this rule asks the same question. For every wp_ajax_ handler and every
 * admin tab file, does a nonce or capability check appear before the first
 * superglobal read?
 * ====================================================================== */
rule( 'WPORG-10', 'Origin or identity checked BEFORE request data is read', 'T13 14Sep26' );

$gates = '/\b(?:check_ajax_referer|check_admin_referer|wp_verify_nonce|current_user_can|is_super_admin|can_access_flow_admin|can_manage_flow_chat_logs|viewer_can_stream_member_audio)\s*\(/';
$reads = '/\$_(?:POST|GET|REQUEST|COOKIE)\b/';

/* --- admin tab files: the check must precede the first read in the file --- */
foreach ( $src as $file => $lines ) {
	if ( strpos( $file, 'admin/' ) !== 0 ) {
		continue;
	}
	$first_read = null;
	$first_gate = null;
	foreach ( $lines as $i => $line ) {
		if ( flosc_is_comment_line( $line ) ) {
			continue;
		}
		if ( null === $first_gate && preg_match( $gates, $line ) ) {
			$first_gate = $i;
		}
		if ( null === $first_read && preg_match( $reads, $line ) ) {
			$first_read = $i;
		}
	}
	if ( null === $first_read ) {
		continue;
	}
	if ( null === $first_gate || $first_gate > $first_read ) {
		finding( 'WPORG-10', $file, $first_read + 1, 'first request read at this line; no nonce or capability check before it' );
	}
}

/* --- wp_ajax_ handlers: same question, inside the method --- */
foreach ( $src as $file => $lines ) {
	$joined = implode( "\n", $lines );
	if ( ! preg_match_all( '/add_action\\s*\\(\\s*[\\x27"]wp_ajax(?:_nopriv)?_[a-z0-9_]+[\\x27"]\\s*,\\s*(?:array\\s*\\(\\s*\\$this\\s*,\\s*)?[\\x27"]?([A-Za-z0-9_]+)/', $joined, $am ) ) {
		continue;
	}
	foreach ( array_unique( $am[1] ) as $method ) {
		if ( ! preg_match( '/function\s+' . preg_quote( $method, '/' ) . '\s*\(/', $joined, $fm, PREG_OFFSET_CAPTURE ) ) {
			continue;
		}
		$start = substr_count( substr( $joined, 0, $fm[0][1] ), "\n" );
		$body  = array_slice( $lines, $start, 40 );
		$g = null;
		$r = null;
		foreach ( $body as $k => $line ) {
			if ( flosc_is_comment_line( $line ) ) {
				continue;
			}
			if ( null === $g && preg_match( $gates, $line ) ) {
				$g = $k;
			}
			if ( null === $r && preg_match( $reads, $line ) ) {
				$r = $k;
			}
		}
		if ( null === $r ) {
			continue;
		}
		if ( null === $g || $g > $r ) {
			finding( 'WPORG-10', $file, $start + $r + 1, "wp_ajax handler {$method}() reads the request before any nonce or capability check" );
		}
	}
}

/* =========================================================================
 * Report. Raw counts and every file:line. No summary verdict beyond the total.
 * ====================================================================== */
$total = 0;
foreach ( $RULES as $r ) {
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
printf( "  findings          : %d  (exit code counts these)\n", $total );
echo "---------------------------------------------------------------------------\n\n";

echo "OUT OF REACH OF THIS FILE — do not read a clear run as coverage of these:\n";
echo "  * The T12 SSO redirect-host finding. CLOSED on 15 Sep 2026 by removing the\n";
echo "    HTTP_HOST entry from get_allowed_sso_redirect_hosts() in\n";
echo "    includes/sso/class-oauth2-handler.php. The Host header is client-supplied,\n";
echo "    so it put an attacker-named host into the one allowlist that\n";
echo "    flosc_safe_external_redirect() consults. Closed by reading the call paths,\n";
echo "    not by this file -- pattern matching cannot follow taint across functions,\n";
echo "    and it still cannot. Do not read a clear run as proof this stayed fixed.\n";
echo "  * Anything their AI flags that has not been flagged before. This file\n";
echo "    knows four emails. It cannot know the fifth.\n\n";

exit( $total > 0 ? 1 : 0 );
