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

process.exit(failures === 0 ? 0 : 1);
