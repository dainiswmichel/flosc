#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const companion = fs.readFileSync(path.join(root, 'assets/js/flosc-companion.js'), 'utf8');
const app = fs.readFileSync(path.join(root, 'assets/js/flosc-app.js'), 'utf8');

function objectMethod(source, name, nextName) {
    const start = source.indexOf(`${name}: function`);
    const end = source.indexOf(`${nextName}: function`, start);
    if (start < 0 || end < 0) {
        throw new Error(`missing ${name}`);
    }
    const segment = source.slice(start, end);
    const fnStart = segment.indexOf('function');
    const fnEnd = segment.lastIndexOf('},');
    return (0, eval)(`(${segment.slice(fnStart, fnEnd + 1)})`); // eslint-disable-line no-eval
}

function classMethod(source, name, nextName) {
    const start = source.indexOf(`\n    ${name}(`);
    const end = source.indexOf(`\n    ${nextName}(`, start);
    if (start < 0 || end < 0) {
        throw new Error(`missing ${name}`);
    }
    const segment = source.slice(start + 5, end).trim();
    return (0, eval)(`(function${segment.slice(name.length)})`); // eslint-disable-line no-eval
}

let failures = 0;
function ok(condition, label) {
    if (condition) {
        console.log(`PASS ${label}`);
    } else {
        console.error(`FAIL ${label}`);
        failures += 1;
    }
}

function storage() {
    const values = new Map();
    return {
        getItem: (key) => values.has(key) ? values.get(key) : null,
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key)
    };
}

async function run() {
    const captureContinuity = objectMethod(companion, 'captureContinuityParamsFromPage', 'consumeHandoffRequest');
    const consumeHandoff = objectMethod(companion, 'consumeHandoffRequest', 'forceMinimizedStateForHandoff');
    const buildIframeUrl = objectMethod(companion, 'buildIframeUrl', 'normalizeUrl');
    const openFullPage = objectMethod(companion, 'openFullPage', 'escapeHtml');
    const appendContinuity = classMethod(app, 'appendSessionContinuityParams', 'applyBrowsingContext');

    global.document = { title: 'Hub' };
    global.window = {
        location: {
            origin: 'https://dainis.net',
            href: 'https://dainis.net/?flosc_flow_id=dainis_net_ivr&flosc_companion_handoff=1&flosc_handoff_ref=1',
            search: '?flosc_flow_id=dainis_net_ivr&flosc_companion_handoff=1&flosc_handoff_ref=1'
        },
        history: {
            replaceState: (_state, _title, url) => { window.cleanedUrl = String(url); }
        },
        sessionStorage: storage()
    };

    const captured = captureContinuity.call({});
    ok(captured.flosc_handoff_ref === '1',
        'full-page to companion snapshots the parked-transcript marker');

    const iframeUrl = buildIframeUrl.call({
        config: {
            appUrl: 'https://dainis.net/chat/',
            returnUrl: 'https://dainis.net/',
            contextParams: {}
        },
        continuityParams: captured,
        resolveAllowedAppUrl: () => new URL('https://dainis.net/chat/'),
        stashHandoffPack: () => true,
        getBrowsingContextPayload: () => ({})
    });
    const parsedIframe = new URL(iframeUrl);
    ok(parsedIframe.searchParams.get('flosc_handoff_ref') === '1'
        && parsedIframe.searchParams.get('flosc_surface') === 'companion',
        'full-page to companion gives the iframe the marker and companion surface');

    consumeHandoff.call({});
    const cleanedHub = new URL(window.cleanedUrl);
    ok(!cleanedHub.searchParams.has('flosc_handoff_ref')
        && !cleanedHub.searchParams.has('flosc_companion_handoff')
        && cleanedHub.searchParams.get('flosc_flow_id') === 'dainis_net_ivr',
        'the hub cleans consumed handoff controls without losing the flow');

    const returnTarget = new URL('https://dainis.net/');
    appendContinuity.call({
        state: 'visitor',
        buildSessionHandoffPayload: () => ({
            kind: 'visitor',
            sessionId: 'visitor-116',
            journeyId: 'journey-116',
            messages: [{ role: 'user', content: 'round trip' }]
        }),
        encodeSessionHandoffPayload: (payload) => Buffer.from(JSON.stringify(payload)).toString('base64'),
        logWarn: () => {}
    }, returnTarget);
    ok(returnTarget.searchParams.get('flosc_handoff_ref') === '1'
        && returnTarget.searchParams.get('flosc_visitor_session') === 'visitor-116'
        && !returnTarget.searchParams.has('flosc_handoff')
        && !!window.sessionStorage.getItem('flosc_handoff_pack'),
        'full-page minimize parks the transcript and keeps it out of the URL');

    let assigned = '';
    window.location = {
        origin: 'https://dainis.net',
        href: 'https://dainis.net/',
        assign: (url) => { assigned = String(url); }
    };
    window.sessionStorage = storage();
    openFullPage.call({
        config: {
            appUrl: 'https://dainis.net/chat/',
            fullPageUrl: 'https://dainis.net/chat/'
        },
        requestHandoffPayloadFromIframe: () => Promise.resolve({
            kind: 'visitor',
            sessionId: 'visitor-116',
            journeyId: 'journey-116',
            messages: [{ role: 'assistant', content: 'still here' }]
        }),
        isUsableHandoffPayload: () => true,
        continuityFallbackPayload: () => ({}),
        stashHandoffPack: (packed) => {
            window.sessionStorage.setItem('flosc_handoff_pack', packed);
            return true;
        },
        lastHandoffPayload: null
    });
    await new Promise((resolve) => setTimeout(resolve, 0));
    const parsedFull = new URL(assigned);
    ok(parsedFull.searchParams.get('flosc_surface') === 'full'
        && parsedFull.searchParams.get('flosc_handoff_ref') === '1'
        && parsedFull.searchParams.get('flosc_visitor_session') === 'visitor-116'
        && !parsedFull.searchParams.has('flosc_handoff')
        && !!window.sessionStorage.getItem('flosc_handoff_pack'),
        'companion expand parks the transcript and opens the full surface');

    const watchFrameHealth = objectMethod(companion, 'watchFrameHealth', 'clearFrameFailure');
    window.sessionStorage = storage();
    window.sessionStorage.setItem('flosc_handoff_pack', 'packed-transcript');
    let scheduled = null;
    window.setTimeout = (fn) => {
        scheduled = fn;
        return 1;
    };
    window.clearTimeout = () => {};
    const retryIframe = {
        hidden: true,
        src: 'https://dainis.net/chat/?flosc_handoff_ref=1'
    };
    const shell = {
        _frameAlive: false,
        _frameFailed: false,
        _frameRecovered: false,
        iframe: retryIframe,
        continuityParams: captured,
        lastIframeContextSignature: 'sig',
        buildIframeUrl: () => 'https://dainis.net/chat/?flosc_surface=companion&flosc_companion=1&flosc_handoff_ref=1',
        showFrameFailure: () => { shell.failed = true; }
    };
    watchFrameHealth.call(shell);
    ok(typeof scheduled === 'function', 'a silent frame schedules one rebuild');
    scheduled();
    ok(window.sessionStorage.getItem('flosc_handoff_pack') === 'packed-transcript'
        && shell.continuityParams.flosc_handoff_ref === '1'
        && retryIframe.src.includes('flosc_handoff_ref=1')
        && !shell.failed,
        'a silent-frame rebuild keeps the parked transcript and the marker');

    process.exit(failures === 0 ? 0 : 1);
}

run().catch((error) => {
    console.error(error);
    process.exit(1);
});
