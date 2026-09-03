<?php
/**
 * The admin panel's setup block must run, not merely parse.
 *
 * This exists because of a regression it would have caught on the spot. A
 * helper read a `var` map that is assigned further down the same block, while
 * being called from setup code near the top. Hoisting made the function
 * callable and left its data undefined, so reading a property off it threw —
 * and a throw during setup means every handler registered after that line is
 * never bound. Two unrelated controls, Edit the request and Max Tokens, went
 * dead together with nothing wrong in either of them.
 *
 * php -l saw valid PHP. node --check saw valid JavaScript. Every unit test
 * passed, because each tested a function in isolation and none of them ran the
 * wiring. So this runs the wiring: the block is executed against a jQuery stub
 * that records what binds, and the controls an operator actually presses are
 * named here by id. A throw, or a missing handler, fails the build.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "-- node not available; admin JavaScript binding not checked\n";
	exit( 0 );
}

// Controls that must end up with at least one handler for the panel to work.
$required = array(
	'#flosc-params-edit'     => 'Edit the request',
	'#flosc-save-tuning'     => 'Save Model Tuning',
	'#flow_ai_temperature'   => 'Temperature',
	'#flow_ai_max_tokens'    => 'Max Tokens',
	'#flow_ai_model_params'  => 'The request FLOSC sends',
	'#flow_ai_provider'      => 'the provider picker',
);

$src = (string) file_get_contents( $root . '/admin/ai-configuration.php' );

if ( ! preg_match( '/jQuery\(document\)\.ready\(function\s*\(\$\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
	echo "FAIL no inline jQuery ready block found\n";
	exit( 1 );
}

$start = (int) $m[0][1];
$end   = strpos( $src, 'wp_add_inline_script', $start );
$end   = ( false === $end ) ? strlen( $src ) : $end;
$block = substr( $src, $start, $end - $start );
$close = strrpos( $block, '});' );
$block = ( false === $close ) ? $block : substr( $block, 0, $close + 3 );

// PHP islands stand in as an empty object: valid as a value, valid inside a
// string literal, and safe to index into.
$block = preg_replace( '/<\?php.*?\?>/s', '{}', $block );

$dir = sys_get_temp_dir() . '/flosc-bindcheck-' . getmypid();
@mkdir( $dir );
file_put_contents( $dir . '/block.js', $block );
file_put_contents( $dir . '/required.json', (string) wp_json_encode_fallback( array_keys( $required ) ) );

$harness = <<<'JS'
// Enough jQuery to let the block run and say what it bound. Every method is
// chainable and inert; nothing here decides anything, it only records.
var BOUND = [];

function makeEl(sel) {
    var el = {
        0: { offsetWidth: 0, id: String(sel).replace('#', '') },
        length: 1,
        on: function (ev) { BOUND.push(sel + ' :: ' + ev); return el; }
    };
    var inert = ['off','val','text','html','attr','removeAttr','addClass','removeClass','prop',
        'data','empty','append','appendTo','trigger','each','find','closest','hide','show',
        'focus','filter','map','first','remove','after','before','css','wrap','parent','children'];
    inert.forEach(function (name) { el[name] = function () { return el; }; });
    el.val = function () { return ''; };
    el.data = function () { return ''; };
    el.is = function () { return false; };
    return el;
}

var $ = function (sel) {
    if (typeof sel === 'function') { sel(); return makeEl('ready'); }
    return makeEl(typeof sel === 'string' ? sel : '<node>');
};
$.ajax = function () { return {}; };
$.post = $.ajax;
$.each = function () {};
$.extend = Object.assign;

var jQuery = function (fn) { if (typeof fn === 'function') { fn($); } return { ready: function (f) { f($); } }; };
jQuery.ready = function (f) { f($); };

global.jQuery = jQuery;
global.$ = $;
global.ajaxurl = '/wp-admin/admin-ajax.php';
global.window = { setTimeout: setTimeout, clearTimeout: clearTimeout, location: { href: '' }, open: function () {} };
global.document = { activeElement: null, createTextNode: function (t) { return { t: t }; }, getElementById: function () { return null; } };
global.alert = function () {};
global.confirm = function () { return true; };

var required = require('./required.json');
var fail = 0;

try {
    require('./block.js');
    console.log('ok   the setup block runs to the end');
} catch (e) {
    fail++;
    var frame = (e.stack || '').split('\n').filter(function (l) { return l.indexOf('block.js') !== -1; })[0];
    console.log('FAIL the setup block threw, so everything after it never bound');
    console.log('     ' + e.name + ': ' + e.message);
    console.log('     ' + (frame || '').trim());
}

required.forEach(function (sel) {
    // Handlers are often bound to several controls at once, so the id is
    // looked for anywhere in the selector rather than only at its start.
    var hits = BOUND.filter(function (b) {
        return b.split(' :: ')[0].split(',').some(function (one) { return one.trim() === sel; });
    });

    if (hits.length) {
        console.log('ok     ' + sel + ' has ' + hits.length + ' handler' + (hits.length === 1 ? '' : 's'));
    } else {
        fail++;
        console.log('FAIL   ' + sel + ' bound nothing — that control is dead on the page');
    }
});

console.log(fail ? '\n' + fail + ' FAILURES' : '\nAdmin JavaScript binds every control: all checks passed');
process.exit(fail ? 1 : 0);
JS;

file_put_contents( $dir . '/harness.js', $harness );

passthru( escapeshellarg( $node ) . ' ' . escapeshellarg( $dir . '/harness.js' ), $status );

array_map( 'unlink', glob( $dir . '/*' ) );
@rmdir( $dir );

exit( $status );

/**
 * json_encode without WordPress loaded.
 */
function wp_json_encode_fallback( $data ) {
	return json_encode( $data );
}
