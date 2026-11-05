<?php
/**
 * The field and the request line are one value in two places.
 *
 * This runs the shipped browser code, not a copy of it: the two functions are
 * cut out of admin/ai-configuration.php by brace counting and executed in node
 * against a stub of the two jQuery calls they make. A rewrite of that file that
 * quietly breaks the rule — change Max Tokens, rewrite that parameter only,
 * leave every other line exactly as the operator wrote it — fails here.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root   = dirname( __DIR__ );
$source = (string) file_get_contents( $root . '/admin/ai-configuration.php' );

/**
 * Cut one JavaScript function out of the file, braces balanced.
 */
function flosc_cut_js_function( $source, $signature ) {
	$start = strpos( $source, $signature );

	if ( false === $start ) {
		fwrite( STDERR, "FAIL could not find $signature\n" );
		exit( 1 );
	}

	// Braces inside quoted strings are not braces. The code being cut compares
	// a character against '{', which is exactly the case a naive count gets
	// wrong, so quotes are tracked and their contents skipped.
	$depth = 0;
	$i     = strpos( $source, '{', $start );
	$quote = '';
	$len   = strlen( $source );

	for ( $j = $i; $j < $len; $j++ ) {
		$ch = $source[ $j ];

		if ( '' !== $quote ) {
			if ( '\\' === $ch ) {
				$j++;
			} elseif ( $ch === $quote ) {
				$quote = '';
			}

			continue;
		}

		if ( "'" === $ch || '"' === $ch ) {
			$quote = $ch;
			continue;
		}

		if ( '{' === $ch ) {
			$depth++;
		} elseif ( '}' === $ch ) {
			$depth--;

			if ( 0 === $depth ) {
				return substr( $source, $start, $j - $start + 1 );
			}
		}
	}

	fwrite( STDERR, "FAIL unbalanced braces in $signature\n" );
	exit( 1 );
}

$js = flosc_cut_js_function( $source, 'function floscSplitParamLine(line) {' ) . "\n\n"
	. flosc_cut_js_function( $source, 'function floscSetParamValue(name, value) {' );

$harness = <<<'JS'
// The two jQuery calls the cut code makes, and nothing else.
var boxValue = '';
var checked = 0;
var floscSyncing = false;

function $(selector) {
    if (selector !== '#flow_ai_model_params') { throw new Error('unexpected selector ' + selector); }

    return {
        length: 1,
        val: function (v) {
            if (v === undefined) { return boxValue; }
            boxValue = v;
            return this;
        }
    };
}

function floscCheckParams() { checked++; }

__CUT__

var fail = 0;

function ok(label, actual, expected) {
    var pass = JSON.stringify(actual) === JSON.stringify(expected);
    if (!pass) { fail++; }
    console.log((pass ? 'ok   ' : 'FAIL ') + label.padEnd(54) + JSON.stringify(actual) +
        (pass ? '' : ' (want ' + JSON.stringify(expected) + ')'));
}

function withBox(text, fn) { boxValue = text; fn(); return boxValue; }

console.log('Changing a field rewrites that parameter and nothing else');
ok('Max Tokens 1200 -> 1500',
    withBox('temperature: 0.3\ntop_p: 0.9\nmax_tokens: 1200', function () { floscSetParamValue('max_tokens', '1500'); }),
    'temperature: 0.3\ntop_p: 0.9\nmax_tokens: 1500');

ok('  the operator’s own lines are untouched',
    withBox('top_p: 0.9\nstop_sequences: ["User:"]\nmax_tokens: 1200', function () { floscSetParamValue('max_tokens', '1500'); }),
    'top_p: 0.9\nstop_sequences: ["User:"]\nmax_tokens: 1500');

ok('  and their ordering is kept',
    withBox('max_tokens: 1200\ntop_k: 40', function () { floscSetParamValue('max_tokens', '1500'); }),
    'max_tokens: 1500\ntop_k: 40');

ok('a parameter not yet in the request is added',
    withBox('top_p: 0.9', function () { floscSetParamValue('max_tokens', '800'); }),
    'top_p: 0.9\nmax_tokens: 800');

ok('  into an empty request too',
    withBox('', function () { floscSetParamValue('max_tokens', '800'); }),
    'max_tokens: 800');

ok('emptying the field removes its line',
    withBox('temperature: 0.3\nmax_tokens: 1200', function () { floscSetParamValue('max_tokens', ''); }),
    'temperature: 0.3');

ok('  and never leaves a blank line behind',
    withBox('max_tokens: 1200', function () { floscSetParamValue('max_tokens', ''); }),
    '');

ok('emptying one that was never there changes nothing',
    withBox('top_p: 0.9', function () { floscSetParamValue('max_tokens', ''); }),
    'top_p: 0.9');

ok('temperature is the same deal',
    withBox('temperature: 0.3\ntop_p: 0.9', function () { floscSetParamValue('temperature', '0.7'); }),
    'temperature: 0.7\ntop_p: 0.9');

ok('a name that merely contains another is not confused',
    withBox('max_tokens_hint: 1\nmax_tokens: 1200', function () { floscSetParamValue('max_tokens', '1500'); }),
    'max_tokens_hint: 1\nmax_tokens: 1500');

ok('a value carrying a colon survives',
    withBox('stop_sequences: ["User:"]\nmax_tokens: 1200', function () { floscSetParamValue('max_tokens', '1500'); }),
    'stop_sequences: ["User:"]\nmax_tokens: 1500');

console.log('JSON pasted from a provider’s docs is edited as JSON');
ok('the key is set, not appended as a line',
    withBox('{"top_p":0.9,"max_tokens":1200}', function () { floscSetParamValue('max_tokens', '1500'); }),
    '{\n  "top_p": 0.9,\n  "max_tokens": 1500\n}');

ok('  a new key is added inside the object',
    withBox('{"top_p":0.9}', function () { floscSetParamValue('max_tokens', '800'); }),
    '{\n  "top_p": 0.9,\n  "max_tokens": 800\n}');

ok('  an emptied field deletes the key',
    withBox('{"top_p":0.9,"max_tokens":1200}', function () { floscSetParamValue('max_tokens', ''); }),
    '{\n  "top_p": 0.9\n}');

ok('  and the number stays a number',
    withBox('{"max_tokens":1200}', function () { floscSetParamValue('max_tokens', '1500'); }).indexOf('"max_tokens": 1500') !== -1,
    true);

ok('JSON that does not parse is left as typed, not mangled',
    withBox('{"max_tokens":}', function () { floscSetParamValue('max_tokens', '1500'); }).indexOf('{"max_tokens":}') === 0,
    true);

console.log('The sync never runs away with itself');
boxValue = 'max_tokens: 1200';
floscSyncing = true;
floscSetParamValue('max_tokens', '9999');
ok('a write already in flight is not answered', boxValue, 'max_tokens: 1200');
floscSyncing = false;

checked = 0;
withBox('max_tokens: 1200', function () { floscSetParamValue('max_tokens', '1500'); });
ok('the request is re-read once per change', checked, 1);
ok('  and the guard is left down afterwards', floscSyncing, false);

console.log(fail ? '\n' + fail + ' FAILURES' : '\nField and request stay one value: all checks passed');
process.exit(fail ? 1 : 0);
JS;

$script = str_replace( '__CUT__', $js, $harness );
$tmp    = sys_get_temp_dir() . '/flosc-param-sync-' . getmypid() . '.js';

file_put_contents( $tmp, $script );

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "-- node not available; parameter sync behaviour not checked\n";
	unlink( $tmp );
	exit( 0 );
}

passthru( escapeshellarg( $node ) . ' ' . escapeshellarg( $tmp ), $status );
unlink( $tmp );
exit( $status );
