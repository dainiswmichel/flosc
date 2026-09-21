<?php
/**
 * fix-comment-punctuation.php — end inline comments with a full stop.
 *
 * Clears Squiz.Commenting.InlineComment.InvalidEndChar, which WPCS reports
 * ~1,700 times and phpcbf has no fixer for.
 *
 * Uses PHP's own tokenizer rather than a regex, so it cannot touch a "//"
 * inside a string, a URL, or a heredoc. Only T_COMMENT tokens are considered,
 * and only the ones that are genuinely prose.
 *
 * Skipped on purpose:
 *   - phpcs: and @ directives     changing these changes behaviour
 *   - block comments /* ... *​/    a different sniff, different rules
 *   - commented-out code          a line ending in ; { } ) , or =
 *   - separators                  // ----- , // ===== , // #####
 *   - comments already ending in . ! ? :
 *   - anything containing no letter at all
 *
 * Usage:
 *   php fix-comment-punctuation.php --dry-run  /path/to/flosc
 *   php fix-comment-punctuation.php            /path/to/flosc
 *
 * Always run --dry-run first and read what it intends to change.
 *
 * Lives at candidate level, never inside the plugin tree, so it cannot ship.
 *
 * @package FLOSC
 */

$argv_in = $argv;
array_shift( $argv_in );
$dry_run = false;
$root    = '';
foreach ( $argv_in as $arg ) {
	if ( $arg === '--dry-run' || $arg === '-n' ) {
		$dry_run = true;
		continue;
	}
	$root = $arg;
}
if ( $root === '' ) {
	fwrite( STDERR, "usage: php fix-comment-punctuation.php [--dry-run] <plugin-root>\n" );
	exit( 2 );
}
$root = realpath( $root );
if ( $root === false || ! is_dir( $root ) ) {
	fwrite( STDERR, "not a directory\n" );
	exit( 2 );
}

/**
 * Decide whether a single-line comment should gain a full stop.
 *
 * @param string $text Comment text, without its leading marker or newline.
 * @return bool
 */
function flosc_comment_needs_stop( $text ) {
	$t = trim( $text );

	if ( $t === '' ) {
		return false;
	}
	// Directives: phpcs:ignore, phpcs:disable, @codingStandardsIgnore, @todo and friends.
	if ( preg_match( '/^(phpcs:|@|\$|translators:)/i', $t ) ) {
		return false;
	}
	if ( stripos( $t, 'phpcs:' ) !== false ) {
		return false;
	}
	// Already terminated.
	if ( preg_match( '/[.!?:]$/', $t ) ) {
		return false;
	}
	// Separator rules: ----- , ===== , ##### , ***** , and the box-drawing kind.
	if ( preg_match( '/^[-=#*_~\s\x{2500}-\x{257F}\x{2013}\x{2014}]+$/u', $t ) ) {
		return false;
	}
	// A banner comment that TRAILS a rule: "// -- Gather live data ----------".
	// A full stop after a run of rule characters reads as a typo, so leave it.
	if ( preg_match( '/[-=#*_~\x{2500}-\x{257F}\x{2013}\x{2014}]{3,}\s*$/u', $t ) ) {
		return false;
	}
	// Commented-out code, not prose.
	if ( preg_match( '/[;{}\)\],=]$/', $t ) ) {
		return false;
	}
	if ( preg_match( '/^(if|for|foreach|while|switch|return|echo|function|class|public|private|protected|var_dump|print_r|\/\/)\b/i', $t ) ) {
		return false;
	}
	// Must actually contain words.
	if ( ! preg_match( '/[A-Za-z]/', $t ) ) {
		return false;
	}
	// A trailing URL would read oddly with a full stop appended.
	if ( preg_match( '#https?://\S+$#', $t ) ) {
		return false;
	}
	return true;
}

$files = new RegexIterator(
	new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ),
	'/\.php$/'
);

$changed_files = 0;
$changed_lines = 0;
$samples       = array();

foreach ( $files as $file ) {
	$path = $file->getPathname();
	if ( strpos( $path, '/vendor/' ) !== false || strpos( $path, '/node_modules/' ) !== false ) {
		continue;
	}

	$src    = file_get_contents( $path );
	$tokens = @token_get_all( $src );
	if ( ! is_array( $tokens ) ) {
		continue;
	}

	$out         = '';
	$file_hits   = 0;
	foreach ( $tokens as $token ) {
		if ( ! is_array( $token ) || $token[0] !== T_COMMENT ) {
			$out .= is_array( $token ) ? $token[1] : $token;
			continue;
		}

		$raw = $token[1];
		// Only "//" and "#" single-line comments; block comments are a different sniff.
		if ( ! preg_match( '#^(\s*)(//|\#)(?!\[)(.*?)(\r?\n?)$#s', $raw, $m ) ) {
			$out .= $raw;
			continue;
		}
		list( , $lead, $marker, $body, $eol ) = $m;

		if ( ! flosc_comment_needs_stop( $body ) ) {
			$out .= $raw;
			continue;
		}

		$new = $lead . $marker . rtrim( $body ) . '.' . $eol;
		if ( $file_hits < 2 && count( $samples ) < 12 ) {
			$samples[] = sprintf( '%s: %s   ->   %s', str_replace( $root . '/', '', $path ), trim( $raw ), trim( $new ) );
		}
		$out .= $new;
		++$file_hits;
	}

	if ( $file_hits > 0 ) {
		++$changed_files;
		$changed_lines += $file_hits;
		if ( ! $dry_run ) {
			file_put_contents( $path, $out );
		}
	}
}

echo $dry_run ? "DRY RUN — nothing written\n\n" : "WRITTEN\n\n";
echo "sample of intended changes:\n";
foreach ( $samples as $s ) {
	echo '  ' . $s . "\n";
}
echo "\n";
printf( "comments %s: %d in %d files\n", $dry_run ? 'that would change' : 'changed', $changed_lines, $changed_files );
echo "\nRun php -l over the tree afterwards. This script only ever appends a\n";
echo "'.' to the end of a comment, so a parse error would mean a bug here —\n";
echo "report it rather than working around it.\n";
