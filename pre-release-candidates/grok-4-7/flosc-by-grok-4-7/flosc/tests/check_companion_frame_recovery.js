#!/usr/bin/env node
'use strict';

/**
 * Executable journey test for the companion frame-health state machine.
 *
 * A source-string assertion cannot catch the defect this file exists for.
 * V122's first attempt made showFrameFailure() reachable and every string
 * assertion passed, while the panel became permanently stuck on the error:
 * open() rebuilt the frame but never cleared _frameFailed, never unhid the
 * iframe and never removed the error node, so watchFrameHealth() returned at
 * once and a live app could not reappear.
 *
 * So this runs the real method bodies out of assets/js/flosc-companion.js
 * against a fake window, iframe and container, and drives the journey:
 *
 *     load -> silence -> retry -> silence -> failure
 *          -> close -> reopen -> app ready -> working chat
 *
 * The methods are extracted by name and compiled with the Function
 * constructor. They are the shipped bodies, not copies kept in this file.
 */

const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
    path.join(__dirname, '..', 'assets', 'js', 'flosc-companion.js'),
    'utf8'
);

let failures = 0;
function ok(condition, label) {
    if (condition) {
        console.log(`PASS ${label}`);
    } else {
        console.error(`FAIL ${label}`);
        failures += 1;
    }
}

/**
 * Pull one `name: function(args) { ... }` body out of the source by brace
 * matching, and compile it. Throws if the method is missing, so a rename or a
 * deletion fails this test rather than silently skipping it.
 */
function extractMethod(name) {
    const header = new RegExp(`\\n\\s*${name}:\\s*function\\s*\\(([^)]*)\\)\\s*\\{`);
    const m = source.match(header);
    if (!m) {
        throw new Error(`method not found in flosc-companion.js: ${name}`);
    }
    const args = m[1].trim();
    const open = source.indexOf('{', m.index + m[0].length - 1);
    let depth = 0;
    let end = -1;
    for (let i = open; i < source.length; i += 1) {
        const ch = source[i];
        if (ch === '{') { depth += 1; }
        else if (ch === '}') {
            depth -= 1;
            if (depth === 0) { end = i; break; }
        }
    }
    if (end === -1) {
        throw new Error(`unbalanced braces reading ${name}`);
    }
    const body = source.slice(open + 1, end);
    // `window` is injected so the body's window.setTimeout / clearTimeout
    // reach the fake clock below rather than node's real timers.
    return new Function('window', `return function(${args}) {\n${body}\n};`);
}

// ---- fake clock -----------------------------------------------------------
let now = 0;
let seq = 0;
const timers = new Map();
const fakeWindow = {
    setTimeout(fn, delay) {
        seq += 1;
        timers.set(seq, { fn: fn, at: now + (delay || 0) });
        return seq;
    },
    clearTimeout(id) {
        timers.delete(id);
    },
};
function advance(ms) {
    now += ms;
    // Re-read each pass: a fired timer may arm another one.
    for (;;) {
        let dueId = null;
        for (const [id, t] of timers) {
            if (t.at <= now) { dueId = id; break; }
        }
        if (dueId === null) { return; }
        const due = timers.get(dueId);
        timers.delete(dueId);
        due.fn();
    }
}

// ---- fake DOM -------------------------------------------------------------
function makeNode(className) {
    return {
        className: className || '',
        hidden: false,
        src: '',
        children: [],
        parentNode: null,
        setAttribute(k, v) { this[k] = v; },
        removeAttribute(k) {
            if (k === 'src') { this.src = ''; } else { delete this[k]; }
        },
        insertBefore(node) { node.parentNode = this; this.children.push(node); },
        remove() {
            if (!this.parentNode) { return; }
            const i = this.parentNode.children.indexOf(this);
            if (i !== -1) { this.parentNode.children.splice(i, 1); }
            this.parentNode = null;
        },
    };
}

global.document = {
    createElement() { return makeNode(); },
};

// ---- the object under test ------------------------------------------------
const container = makeNode('flosc-companion');
const iframe = makeNode('flosc-companion-body');
iframe.parentNode = container;
container.querySelector = function (sel) {
    if (sel !== '.flosc-companion-frame-error') { return null; }
    return this.children.find((c) => c.className === 'flosc-companion-frame-error') || null;
};

let rebuilds = 0;
const app = {
    container: container,
    iframe: iframe,
    continuityParams: { flosc_session_id: 'abc' },
    lastIframeContextSignature: 'sig',
    _frameAlive: false,
    _frameRecovered: false,
    _frameFailed: false,
    _frameHealthTimer: null,
    buildIframeUrl() { rebuilds += 1; return 'https://example.test/chat/?flosc_companion=1'; },
    watchFrameHealth: extractMethod('watchFrameHealth')(fakeWindow),
    showFrameFailure: extractMethod('showFrameFailure')(fakeWindow),
    clearFrameFailure: extractMethod('clearFrameFailure')(fakeWindow),
    resetFrameHealth: extractMethod('resetFrameHealth')(fakeWindow),
};

// sessionStorage is touched inside the retry; make it harmless and present.
fakeWindow.sessionStorage = { removeItem() {} };

function errorNode() {
    return container.children.find((c) => c.className === 'flosc-companion-frame-error') || null;
}

// ---- the journey ----------------------------------------------------------

// 1. Frame loads something. The app says nothing.
iframe.src = 'https://example.test/some-blog-post/';
app.watchFrameHealth();
advance(3000);
ok(!app._frameFailed && errorNode() === null && rebuilds === 0,
    'before the first window elapses nothing is torn down');

// 2. First silence: one retry, frame still shown.
advance(1500);
ok(rebuilds === 1, 'first silence rebuilds the frame exactly once');
ok(app._frameRecovered === true, 'the retry is recorded');
ok(iframe.hidden === false, 'the frame stays visible during the retry');
ok(errorNode() === null, 'no error is shown yet');

// 3. Second silence: the wrong document is taken away.
advance(4500);
ok(app._frameFailed === true, 'second silence marks the frame failed');
ok(iframe.hidden === true, 'the failed frame is hidden');
ok(iframe.src === '', 'the failed frame has its src removed');
ok(errorNode() !== null, 'an error node is shown');
ok(errorNode() && errorNode().role === 'alert', 'the error node is announced to assistive tech');
ok(rebuilds === 1, 'it does not keep rebuilding after failing');

// 4. Reader closes and reopens. This is the step V122's first attempt missed.
app.resetFrameHealth();
ok(app._frameFailed === false, 'reopen clears the failed flag');
ok(iframe.hidden === false, 'reopen makes the frame visible again');
ok(errorNode() === null, 'reopen removes the error node');
ok(app._frameAlive === false && app._frameRecovered === false,
    'reopen resets the health state so a fresh load can be judged');

// 5. The reopened frame loads and the app announces itself.
iframe.src = app.buildIframeUrl();
app.watchFrameHealth();
app._frameAlive = true;            // what the flosc_app_ready handler sets
fakeWindow.clearTimeout(app._frameHealthTimer);
advance(10000);
ok(iframe.hidden === false, 'after recovery a live app is visible');
ok(errorNode() === null, 'after recovery no error is left on screen');
ok(app._frameFailed === false, 'after recovery the panel is not stuck failed');

// 6. A healthy frame is never torn down.
ok(iframe.src !== '', 'a healthy frame keeps its src');


// ---------------------------------------------------------------------------
// Part two: drive the REAL open() and close().
//
// Part one proved resetFrameHealth() is correct by calling it directly. That is
// not the same as proving open() reaches it, and it says nothing about close().
// The v122 defect was exactly this shape -- a correct method with no proof that
// the path through open() arrived at it -- so the bodies of open() and close()
// now run here too, against stubs for everything they touch that is not frame
// health.
//
// Two defects this part exists to catch:
//
//   1. close() leaving the watchdog armed. The retry then rebuilds a frame
//      nobody is looking at and the second silence builds a failure screen
//      behind a closed panel.
//   2. open() not re-arming when iframe.src survived the close. src is kept on
//      purpose (reassigning it aborts in-flight turns), so this branch is the
//      common one -- and an unwatched frame that never announces itself is the
//      original "a website is sitting where my chat should be", with no way out.
// ---------------------------------------------------------------------------

const container2 = makeNode('flosc-companion');
const iframe2 = makeNode('flosc-companion-body');
iframe2.parentNode = container2;
container2.classList = { add() {}, remove() {} };
container2.querySelector = function (sel) {
    if (sel !== '.flosc-companion-frame-error') { return null; }
    return this.children.find((c) => c.className === 'flosc-companion-frame-error') || null;
};

let rebuilds2 = 0;
let contextDeliveries = 0;
const app2 = {
    container: container2,
    iframe: iframe2,
    config: { focusOnOpen: false, allowFullscreen: false, defaultFullscreen: false },
    continuityParams: {},
    isOpen: false,
    lastIframeContextSignature: '',
    _frameAlive: false,
    _frameRecovered: false,
    _frameFailed: false,
    _frameHealthTimer: null,

    // Real bodies, read out of the shipped file.
    open: extractMethod('open')(fakeWindow),
    close: extractMethod('close')(fakeWindow),
    watchFrameHealth: extractMethod('watchFrameHealth')(fakeWindow),
    showFrameFailure: extractMethod('showFrameFailure')(fakeWindow),
    clearFrameFailure: extractMethod('clearFrameFailure')(fakeWindow),
    resetFrameHealth: extractMethod('resetFrameHealth')(fakeWindow),

    // Everything open() touches that is not frame health.
    buildIframeUrl() { rebuilds2 += 1; return 'https://example.test/chat/?flosc_companion=1'; },
    deliverBrowsingContextToIframe() { contextDeliveries += 1; },
    captureCurrentSiteContext() {},
    syncViewportCssVars() {},
    setPanelMode() {},
    updateLauncherA11y() {},
    scheduleViewportClamp() {},
    getBrowsingContextPayload() { return {}; },
    getContextSignature() { return 'sig2'; },
    focusCompanion() {},
    saveOpenState() {},
    saveNavigationState() {},
};

function errorNode2() {
    return container2.children.find((c) => c.className === 'flosc-companion-frame-error') || null;
}

/*
 * The watchdog is armed by the iframe's own 'load' listener, not by open().
 * Part two first missed the close() defect entirely for that reason: it called
 * open(), no timer was ever armed, and advancing the clock proved nothing.
 *
 * So fireLoad() stands in for the browser firing 'load', and the assertion
 * below pins it to the listener that actually ships. If the listener changes,
 * this fails rather than quietly simulating something the app no longer does.
 */
ok(/addEventListener\('load', function\(\) \{\s*self\.deliverBrowsingContextToIframe\(\);\s*self\.watchFrameHealth\(\);\s*\}\);/.test(source),
    "the frame's load listener delivers context and arms the watch");

function fireLoad(target) {
    target.deliverBrowsingContextToIframe();
    target.watchFrameHealth();
}

// 7. First real open(): builds the frame and arms the watch.
app2.open();
ok(rebuilds2 === 1 && iframe2.src !== '', 'real open() builds the frame on first open');
ok(app2.isOpen === true, 'real open() marks the panel open');
fireLoad(app2);                    // the browser loads whatever is at that src
ok(app2._frameHealthTimer !== null, 'the loaded frame is under watch');

// 8. Reader closes while the frame is still silent. Nothing may happen after.
app2.close();
ok(app2.isOpen === false, 'real close() marks the panel closed');
const rebuildsAtClose = rebuilds2;
advance(20000);
ok(rebuilds2 === rebuildsAtClose,
    'a closed panel does not rebuild its frame in the background');
ok(errorNode2() === null,
    'a closed panel does not build a failure screen behind the reader');
ok(app2._frameFailed === false,
    'a closed panel is not marked failed while nobody is watching');

// 9. Reopen with the src intact: the watch must come back.
const deliveriesBeforeReopen = contextDeliveries;
app2.open();
ok(rebuilds2 === rebuildsAtClose,
    'reopen keeps the surviving frame rather than aborting in-flight turns');
ok(contextDeliveries > deliveriesBeforeReopen,
    'reopen delivers context to the surviving frame');
advance(4500);
ok(rebuilds2 === rebuildsAtClose + 1,
    'the reopened silent frame is watched again and retried once');
advance(4500);
ok(app2._frameFailed === true,
    'a frame that still never announces itself is taken out of the panel');
ok(errorNode2() !== null, 'and the reader is told, instead of being shown a website');

// 10. And a frame that did announce itself is left alone on reopen.
app2.resetFrameHealth();
iframe2.src = app2.buildIframeUrl();
fireLoad(app2);
app2._frameAlive = true;           // what the flosc_app_ready handler sets
const rebuildsAlive = rebuilds2;
app2.close();
app2.open();
advance(20000);
ok(rebuilds2 === rebuildsAlive && app2._frameFailed === false && errorNode2() === null,
    'a live app survives close and reopen untouched');


process.exit(failures === 0 ? 0 : 1);
