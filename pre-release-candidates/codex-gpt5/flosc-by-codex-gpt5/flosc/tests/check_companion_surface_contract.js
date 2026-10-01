#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const app = read('assets/js/flosc-app.js');
const companion = read('assets/js/flosc-companion.js');
const template = read('admin/flosc-app.php');
const framework = read('includes/class-flosc-framework.php');
const fullPage = read('includes/full-page-mode/class-flosc-full-page-mode.php');
const packs = read('includes/starter-packs/class-flosc-starter-packs.php');
const upload = read('admin/ivr-upload-handler.php');

let failures = 0;
function ok(condition, label) {
    if (condition) {
        console.log(`PASS ${label}`);
    } else {
        console.error(`FAIL ${label}`);
        failures += 1;
    }
}

const frameGuard = companion.indexOf('if (window.self !== window.top)');
const bootFlag = companion.indexOf('if (window.__FLOSC_COMPANION_BOOTED__)');
ok(frameGuard !== -1 && bootFlag !== -1 && frameGuard < bootFlag,
    'a framed document exits before companion boot');

ok(app.includes("case 'view_profile':\n                this.navigateToHostPage("),
    'profile navigation cannot replace the panel');
ok(app.includes("case 'view_dashboard':\n                this.navigateToHostPage("),
    'dashboard navigation cannot replace the panel');
ok(app.includes('openPersonalizedPath() {\n        this.navigateToHostPage('),
    'personalized-path navigation cannot replace the panel');
ok(app.includes('setTimeout(() => { this.leavePanel(redirectUrl); }, 800);'),
    'external checkout leaves through the parent');
ok(app.includes('this.leavePanel(safeRedirect);'),
    'visitor depletion redirect leaves through the parent');
ok(app.includes("type: 'flosc_companion_navigate_top'"),
    'the app emits the top-navigation message');
ok(companion.includes("data.type === 'flosc_companion_navigate_top'"),
    'the companion receives the top-navigation message');
ok(companion.includes('this.iframe.hidden = true;')
    && companion.includes('self.iframe.hidden = false;'),
    'the iframe remains hidden until the FLOSC app announces readiness');
ok(companion.includes('if (self._frameRecovered) {\n                    self.showFrameFailure();'),
    'a failed retry closes the frame instead of displaying a website or error page');

const companionIframeUrlStart = companion.indexOf('buildIframeUrl: function()');
const companionIframeUrlEnd = companion.indexOf('normalizeUrl: function(value)', companionIframeUrlStart);
const companionIframeUrl = companion.slice(companionIframeUrlStart, companionIframeUrlEnd);
ok(companionIframeUrl.includes("cont.flosc_handoff_ref || parentParams.get('flosc_handoff_ref')")
    && companionIframeUrl.includes("url.searchParams.set('flosc_handoff_ref', '1')"),
    'full-page to companion forwards the parked visitor handoff into the iframe');

const captureContinuityStart = companion.indexOf('captureContinuityParamsFromPage: function()');
const captureContinuityEnd = companion.indexOf('consumeHandoffRequest: function()', captureContinuityStart);
const captureContinuity = companion.slice(captureContinuityStart, captureContinuityEnd);
ok(captureContinuity.includes("'flosc_handoff_ref'"),
    'the hub snapshots the handoff marker before cleaning its address bar');

const consumeHandoffStart = companion.indexOf('consumeHandoffRequest: function()');
const consumeHandoffEnd = companion.indexOf('forceMinimizedStateForHandoff: function()', consumeHandoffStart);
const consumeHandoff = companion.slice(consumeHandoffStart, consumeHandoffEnd);
ok(consumeHandoff.includes("url.searchParams.delete('flosc_handoff_ref')"),
    'the hub removes the consumed visitor handoff marker from its own URL');

const fullPageExpandStart = companion.indexOf('openFullPage: function()');
const fullPageExpand = companion.slice(fullPageExpandStart);
ok(fullPageExpand.includes("target.searchParams.set('flosc_handoff_ref', '1')")
    && fullPageExpand.includes('self.stashHandoffPack(packed)'),
    'companion to full-page parks the visitor transcript and forwards its marker');

const handoffStart = app.indexOf('async handoffToCompanion()');
const handoffEnd = app.indexOf('restoreSidebarCollapsedState()', handoffStart);
const handoff = app.slice(handoffStart, handoffEnd);
ok(handoff.indexOf('if (this.isFramed())') !== -1
    && handoff.indexOf('if (this.isFramed())') < handoff.indexOf('this.forceCompanionMinimizedState()'),
    'minimize refuses before mutating or navigating a framed app');

ok(template.includes('wp_login_url( $flosc_login_return_url )'),
    'login links share the companion-aware return URL');
ok((template.match(/wp_login_url\( \$flosc_login_return_url \)/g) || []).length === 2,
    'both login entry points use the companion-aware return URL');

ok(framework.includes("add_action( 'template_redirect', array( $this, 'handle_app_route' ), 1 );"),
    'the app route runs before canonical 404 guessing');
const routeGuard = fullPage.indexOf("if ( ! $this->is_flosc_request() )");
const clear404 = fullPage.indexOf('$wp_query->is_404 = false;');
ok(routeGuard !== -1 && clear404 > routeGuard,
    '404 state is cleared only after the request is identified as FLOSC');
function registersBeforeFlush(source) {
    const register = source.indexOf('flosc()->add_rewrite_rules();');
    const flush = source.indexOf('flush_rewrite_rules( false );', register);
    return register !== -1 && flush > register;
}

ok(registersBeforeFlush(packs),
    'starter-pack flow registration adds the new rule before flushing');
ok(registersBeforeFlush(upload),
    'uploaded flow registration adds the new rule before flushing');

process.exit(failures === 0 ? 0 : 1);
