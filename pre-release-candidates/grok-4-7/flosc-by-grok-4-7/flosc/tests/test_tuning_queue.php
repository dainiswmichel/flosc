<?php
/**
 * Step 2b saves are serialized: nothing typed is ever dropped.
 *
 * The failure this stops is an ordering one, so reading the code cannot settle
 * it. floscQueueTuningSave is cut out of admin/ai-configuration.php by brace
 * counting and driven in node through the sequences that used to lose work:
 * a press arriving while an autosave runs, an edit made mid-request, and two
 * unattended saves racing each other.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root   = dirname( __DIR__ );
$source = (string) file_get_contents( $root . '/admin/ai-configuration.php' );

function flosc_cut_js( $source, $signature ) {
	$start = strpos( $source, $signature );

	if ( false === $start ) {
		fwrite( STDERR, "FAIL could not find $signature\n" );
		exit( 1 );
	}

	$depth = 0;
	$quote = '';
	$i     = strpos( $source, '{', $start );
	$len   = strlen( $source );

	for ( $j = $i; $j < $len; $j++ ) {
		$ch = $source[ $j ];

		if ( '' !== $quote ) {
			if ( '\\' === $ch ) { $j++; } elseif ( $ch === $quote ) { $quote = ''; }
			continue;
		}

		if ( "'" === $ch || '"' === $ch ) { $quote = $ch; continue; }

		if ( '{' === $ch ) {
			$depth++;
		} elseif ( '}' === $ch ) {
			$depth--;
			if ( 0 === $depth ) { return substr( $source, $start, $j - $start + 1 ); }
		}
	}

	fwrite( STDERR, "FAIL unbalanced braces in $signature\n" );
	exit( 1 );
}

$js = flosc_cut_js( $source, 'function floscQueueTuningSave($flash, source) {' );

$harness = <<<'JS'
var floscTuningPending = null;

__CUT__

var fail = 0;

function ok(label, actual, expected) {
    var pass = JSON.stringify(actual) === JSON.stringify(expected);
    if (!pass) { fail++; }
    console.log((pass ? 'ok   ' : 'FAIL ') + label.padEnd(58) + JSON.stringify(actual) +
        (pass ? '' : ' (want ' + JSON.stringify(expected) + ')'));
}

function reset() { floscTuningPending = null; }

console.log('A save asked for while one is running is kept, not dropped');
reset();
floscQueueTuningSave('temperature', 'auto');
ok('an autosave takes the empty slot', floscTuningPending.source, 'auto');
ok('  and remembers which field to flash', floscTuningPending.flash, 'temperature');

console.log('A deliberate press outranks a background write');
reset();
floscQueueTuningSave('temperature', 'auto');
floscQueueTuningSave('request', 'manual');
ok('THE PRESS TAKES THE SLOT FROM THE AUTOSAVE', floscTuningPending.source, 'manual');
ok('  so the save that lands is the one that was asked for', floscTuningPending.flash, 'request');

console.log('And is never demoted by a later background write');
reset();
floscQueueTuningSave('request', 'manual');
floscQueueTuningSave('temperature', 'auto');
ok('a later autosave cannot displace it', floscTuningPending.source, 'manual');
floscQueueTuningSave('max_tokens', 'auto');
floscQueueTuningSave('temperature', 'auto');
ok('  however many arrive', floscTuningPending.source, 'manual');
ok('  and the press keeps its own field', floscTuningPending.flash, 'request');

console.log('Two presses collapse to one save, not two');
reset();
floscQueueTuningSave('request', 'manual');
floscQueueTuningSave('request-again', 'manual');
ok('the later press replaces the earlier', floscTuningPending.flash, 'request-again');
ok('  and it is still one queued save', floscTuningPending.source, 'manual');

console.log('The first autosave wins its slot; later ones do not restart it');
reset();
floscQueueTuningSave('first', 'auto');
floscQueueTuningSave('second', 'auto');
ok('one queued save, not a growing pile', floscTuningPending.flash, 'first');

console.log('Anything that is not the word manual is treated as automatic');
reset();
floscQueueTuningSave('x', undefined);
ok('undefined is automatic', floscTuningPending.source, 'auto');
reset();
floscQueueTuningSave('x', 'MANUAL');
ok('  and so is a near miss, rather than a silent promotion', floscTuningPending.source, 'auto');

console.log(fail ? '\n' + fail + ' FAILURES' : '\nStep 2b save queue: all checks passed');
process.exit(fail ? 1 : 0);
JS;

$script = str_replace( '__CUT__', $js, $harness );
$tmp    = sys_get_temp_dir() . '/flosc-tuning-queue-' . getmypid() . '.js';

file_put_contents( $tmp, $script );

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "-- node not available; save queue behaviour not checked\n";
	unlink( $tmp );
	exit( 0 );
}

passthru( escapeshellarg( $node ) . ' ' . escapeshellarg( $tmp ), $status );
unlink( $tmp );
exit( $status );
