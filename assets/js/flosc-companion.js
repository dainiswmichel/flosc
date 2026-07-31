/**
 * FLOSC Companion Widget (v1.6.1)
 * ================================
 * Floating chat widget for non-app WordPress pages.
 * Loads the FLOSC chat interface in an iframe.
 *
 * Usage (PHP enqueues this on non-app pages when companion mode is enabled):
 *   <script>
 *     FloscCompanion.init({
 *       appUrl: 'https://example.com/your-flow-slug/',
 *       title: 'Product Companion',
 *       subtitle: 'We reply instantly',
 *       headerIconUrl: 'https://example.com/logo.png',
 *       accentColor: '#2563eb'
 *     });
 *   </script>
 *
 * Reference: mesolitica/nous-chat-widget
 */
(function(window, document) {
    'use strict';

    var l10n = window.floscCompanionL10n || {};

    var FloscCompanion = {
        isOpen: false,
        isFullscreen: false,
        panelMode: 'panel',
        container: null,
        iframe: null,
        browsingContext: null,
        lastIframeContextSignature: '',
        lastTokenUpdateTs: 0,

        init: function(config) {
            this.config = Object.assign({
                appUrl: '',
                productName: '',
                title: l10n.chatWithUs || 'Chat with us',
                subtitle: l10n.weReplyInstantly || 'We reply instantly',
                showHeaderTitle: true,
                showHeaderSubtitle: true,
                showOpenFullpage: true,
                showHeaderTokens: false,
                headerIconUrl: '',
                avatar: '',
                accentColor: '#2563eb',
                position: 'bottom-right',
                width: '380px',
                height: '560px',
                allowFullscreen: true,
                defaultFullscreen: false,
                mobileBehavior: 'fullscreen',
                autoOpenEnabled: false,
                autoOpenDelayMs: 1500,
                autoOpenOncePerSession: true,
                sessionKey: 'flosc_companion_auto_open',
                launchOnExitIntent: false,
                launchOnScrollThreshold: false,
                launchOnScrollPercent: 0,
                launcherSvgPath: 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z',
                launcherIconUrl: '',
                launcherUsesProductLogo: false,
                triggerDesktopOnly: true,
                triggerMinPageTimeMs: 0,
                triggerSuppressOnAuthCheckout: true,
                triggerSuppressPathPatterns: [],
                currentPath: '/',
                motionMode: 'system',
                focusOnOpen: true,
                allowEscapeClose: true,
                enableKeyboardShortcut: false,
                keyboardShortcutKey: 'k',
                launcherAriaLabel: l10n.openChat || 'Open Chat',
                closeAriaLabel: l10n.collapseChat || 'Collapse Chat',
                launcherOpenAriaLabel: l10n.openChat || 'Open Chat',
                launcherCollapseAriaLabel: l10n.collapseChat || 'Collapse Chat',
                assistantTitle: l10n.floscAssistant || 'Assistant',
                fullscreenLabel: l10n.toggleFullscreen || 'Toggle fullscreen',
                rememberOpenState: false,
                stateStorage: 'session',
                triggerCooldownMs: 0,
                stateKey: 'flosc_companion_state',
                cooldownKey: 'flosc_companion_cooldown',
                skipBehaviorTriggers: false,
                fullPageUrl: '',
                returnUrl: ''
            }, config);

            // Wallet/username render under the chat input inside the iframe
            // (companion-embed session strip). Header stays product chrome only.
            this.config.headerTokenText = '';

            this.pageStartMs = Date.now();

            if (!this.config.appUrl) {
                console.warn('[FLOSC Companion] appUrl is required');
                return;
            }

            this.handoffRequested = this.detectHandoffRequest();
            if (this.handoffRequested) {
                this.config.skipBehaviorTriggers = true;
            }
            // Capture session continuity from the hub URL before anything strips the query string.
            // Guest/member: flosc_session_id. Visitor: flosc_visitor_session + optional flosc_handoff.
            this.continuityParams = this.captureContinuityParamsFromPage();

            this.captureCurrentSiteContext();

            this.render();
            this.applyMotionMode();
            this.syncViewportCssVars();
            this.bindEvents();
            this.applyHandoffRequest();
            this.restoreNavigationStateIfNeeded();
            this.restoreOpenStateIfNeeded();
            this.scheduleAutoOpen();
            this.bindBehaviorTriggers();
        },

        applyMotionMode: function() {
            this.container.classList.remove('flosc-companion--motion-reduce', 'flosc-companion--motion-full');
            if (this.config.motionMode === 'reduce') {
                this.container.classList.add('flosc-companion--motion-reduce');
            } else if (this.config.motionMode === 'full') {
                this.container.classList.add('flosc-companion--motion-full');
            }
        },

        render: function() {
            // Container
            this.container = document.createElement('div');
            this.container.className = 'flosc-companion';

            // Position class keeps placement behavior deterministic from admin settings.
            if (this.config.position === 'bottom-left') {
                this.container.classList.add('flosc-companion--left');
            } else {
                this.container.classList.add('flosc-companion--right');
            }

            if (this.config.mobileBehavior === 'panel') {
                this.container.classList.add('flosc-companion--mobile-panel');
            }

            // Chat window
            var window_el = document.createElement('div');
            window_el.className = 'flosc-companion-window';

            // Header — product chrome only (parameterized title + brand icon).
            // Wallet lives under the input in the iframe profile row.
            var header = document.createElement('div');
            header.className = 'flosc-companion-header';
            var subtitleHtml = (this.config.showHeaderSubtitle === false || !this.config.subtitle)
                ? ''
                : '<div class="flosc-companion-subtitle">' + this.escapeHtml(this.config.subtitle) + '</div>';
            var titleHtml = (this.config.showHeaderTitle === false)
                ? ''
                : '<div class="flosc-companion-title">' + this.escapeHtml(this.config.title) + '</div>';
            var avatarHtml = this.buildHeaderAvatarHtml();
            header.innerHTML =
                '<div class="flosc-companion-header-info">' +
                    avatarHtml +
                    '<div class="flosc-companion-header-text">' +
                        titleHtml +
                        subtitleHtml +
                    '</div>' +
                '</div>' +
                '<div class="flosc-companion-header-actions">' +
                    // The full-chat-page link is admin-optional; some flows keep chat docked-only.
                    ((this.config.showOpenFullpage === false) ? '' : '<button class="flosc-companion-open-fullpage" aria-label="Open full chat page" title="Open full chat page"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" d="M8 16L16 8M10 8h6v6"/></svg></button>') +
                    '<button class="flosc-companion-close" aria-label="' + this.escapeHtml(this.config.closeAriaLabel) + '"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg></button>' +
                '</div>';

            // Iframe
            this.iframe = document.createElement('iframe');
            this.iframe.className = 'flosc-companion-body';
            this.iframe.setAttribute('loading', 'lazy');
            this.iframe.setAttribute('title', this.config.assistantTitle || this.config.productName || 'Assistant');

            window_el.appendChild(header);
            window_el.appendChild(this.iframe);

            // FAB button — product logo when configured, else path SVG glyph.
            var fab = document.createElement('button');
            fab.className = 'flosc-companion-fab flosc-launcher';
            fab.setAttribute('aria-label', this.config.launcherAriaLabel);
            fab.innerHTML = this.buildLauncherHtml();

            // Badge (for future notification count)
            var badge = document.createElement('span');
            badge.className = 'flosc-companion-badge';
            fab.appendChild(badge);

            this.container.appendChild(window_el);
            this.container.appendChild(fab);
            document.body.appendChild(this.container);
            this.updateLauncherA11y();
        },

        bindEvents: function() {
            var self = this;
            var fab = this.container.querySelector('.flosc-companion-fab');
            var closeBtn = this.container.querySelector('.flosc-companion-close');
            var fullPageBtn = this.container.querySelector('.flosc-companion-open-fullpage');

            fab.addEventListener('click', function() {
                self.toggle();
            });

            closeBtn.addEventListener('click', function() {
                self.close();
            });

            if (fullPageBtn) {
                fullPageBtn.addEventListener('click', function() {
                    self.openFullPage();
                });
            }

            if (this.iframe) {
                this.iframe.addEventListener('load', function() {
                    self.deliverBrowsingContextToIframe();
                });
            }

            // Keyboard: Escape to close
            document.addEventListener('keydown', function(e) {
                if (self.config.allowEscapeClose && e.key === 'Escape' && self.isOpen) {
                    self.close();
                    return;
                }

                if (self.config.enableKeyboardShortcut && e.altKey && e.shiftKey) {
                    var key = String(e.key || '').toLowerCase();
                    if (key === String(self.config.keyboardShortcutKey || 'k').toLowerCase() && !self.isTypingTarget(e.target)) {
                        e.preventDefault();
                        self.toggle();
                    }
                }
            });

            window.addEventListener('popstate', function() {
                self.captureCurrentSiteContext();
                self.refreshIframeContextIfNeeded();
            });

            window.addEventListener('hashchange', function() {
                self.captureCurrentSiteContext();
                self.refreshIframeContextIfNeeded();
            });

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    self.captureCurrentSiteContext();
                    self.refreshIframeContextIfNeeded();
                } else if (document.visibilityState === 'hidden') {
                    self.saveNavigationState();
                }
            });

            window.addEventListener('pagehide', function() {
                self.saveNavigationState();
            });

            var syncViewport = function() {
                self.syncViewportCssVars();
                self.scheduleViewportClamp();
            };

            window.addEventListener('resize', syncViewport, { passive: true });
            window.addEventListener('orientationchange', syncViewport, { passive: true });

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', syncViewport, { passive: true });
                window.visualViewport.addEventListener('scroll', syncViewport, { passive: true });
            }

            window.addEventListener('message', function(event) {
                if (!self.iframe || event.source !== self.iframe.contentWindow) {
                    return;
                }

                try {
                    var appOrigin = new URL(self.config.appUrl, window.location.origin).origin;
                    if (String(event.origin || '') !== appOrigin) {
                        return;
                    }
                } catch (e) {
                    return;
                }

                var data = event.data || {};
                if (data.type === 'flosc_companion_token_update') {
                    // Tokens/username render in the iframe session strip under the input.
                    // Cache only for continuity; do not paint wallet into the purple header.
                    self.cacheTokenUpdate(data.payload);
                    return;
                }
                if (data.type === 'flosc_companion_context_request') {
                    self.deliverBrowsingContextToIframe();
                }
            });
        },

        getTokenCacheKey: function() {
            var flowId = String(this.config.flowId || 'default');
            var appScope = '';
            try {
                appScope = new URL(this.config.appUrl, window.location.origin).pathname || '';
            } catch (e) {
                appScope = String(this.config.appUrl || '');
            }
            appScope = appScope.replace(/[^a-z0-9]+/gi, '_').toLowerCase() || 'app';
            return 'flosc_companion_token_cache_' + flowId + '_' + appScope;
        },

        readTokenCache: function() {
            try {
                var raw = window.sessionStorage.getItem(this.getTokenCacheKey());
                if (!raw) {
                    return null;
                }
                var parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : null;
            } catch (e) {
                return null;
            }
        },

        persistTokenCache: function(payload) {
            if (!payload || typeof payload !== 'object') {
                return;
            }

            var formatted = String(payload.formatted || '').trim();
            if (!formatted) {
                return;
            }

            var ts = Math.max(0, parseInt(payload.ts, 10) || 0);
            if (!ts) {
                ts = Date.now();
            }

            try {
                window.sessionStorage.setItem(this.getTokenCacheKey(), JSON.stringify({
                    formatted: formatted,
                    value: Math.max(0, parseInt(payload.value, 10) || 0),
                    ts: ts
                }));
            } catch (e) {
                // Ignore storage failures.
            }
        },

        /**
         * Header brand mark: flow Chat Logo / companion_header_icon_url.
         * No hard-coded chat emoji — empty when no icon is parameterized.
         */
        buildHeaderAvatarHtml: function() {
            var iconUrl = String(this.config.headerIconUrl || '').trim();
            var product = String(this.config.productName || this.config.title || 'Brand').trim();
            if (iconUrl) {
                return '<div class="flosc-companion-avatar flosc-companion-avatar--image">' +
                    '<img src="' + this.escapeHtml(iconUrl) + '" alt="' + this.escapeHtml(product) + '" class="flosc-companion-avatar-img">' +
                    '</div>';
            }
            var emoji = String(this.config.avatar || '').trim();
            if (emoji) {
                return '<div class="flosc-companion-avatar flosc-companion-avatar--emoji">' +
                    this.escapeHtml(emoji) +
                    '</div>';
            }
            return '';
        },

        /**
         * FAB content: product logo image or SVG path from admin launcher icon choice.
         */
        buildLauncherHtml: function() {
            var logoUrl = String(this.config.launcherIconUrl || '').trim();
            if (this.config.launcherUsesProductLogo && logoUrl) {
                return '<img src="' + this.escapeHtml(logoUrl) + '" alt="" class="flosc-companion-fab-logo">';
            }
            // Prefer header brand icon for FAB when no SVG path and logo URL exists.
            if (!this.config.launcherSvgPath && logoUrl) {
                return '<img src="' + this.escapeHtml(logoUrl) + '" alt="" class="flosc-companion-fab-logo">';
            }
            var path = String(this.config.launcherSvgPath || '').trim();
            if (!path) {
                path = 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z';
            }
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">' +
                '<path d="' + this.escapeHtml(path) + '"/>' +
                '</svg>';
        },

        formatTokenCountForHeader: function(tokenValue) {
            var n = Math.max(0, parseInt(tokenValue, 10) || 0);
            try {
                return n.toLocaleString();
            } catch (e) {
                return String(n);
            }
        },

        getInitialHeaderTokenText: function() {
            // Header never shows wallet; profile row under input is authoritative.
            return '';
        },

        /**
         * Persist token postMessage from the iframe (session continuity only).
         * Does not mutate the companion chrome header.
         */
        cacheTokenUpdate: function(payload) {
            if (!payload || typeof payload !== 'object') {
                return;
            }

            var formatted = String(payload.formatted || '').trim();
            if (!formatted) {
                return;
            }

            var ts = Math.max(0, parseInt(payload.ts, 10) || 0);
            if (!ts) {
                ts = Date.now();
                payload = Object.assign({}, payload, { ts: ts });
            }
            if (this.lastTokenUpdateTs > 0 && ts < this.lastTokenUpdateTs) {
                return;
            }

            this.lastTokenUpdateTs = ts;
            this.persistTokenCache(payload);
            this.config.headerTokenText = formatted;
        },

        /** @deprecated Header no longer displays tokens; use cacheTokenUpdate. */
        updateHeaderTokenText: function(payload) {
            this.cacheTokenUpdate(payload);
        },

        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        open: function() {
            var shouldOpenFullscreen = !!(this.config.allowFullscreen && this.config.defaultFullscreen);

            this.captureCurrentSiteContext();
            this.syncViewportCssVars();

            this.setPanelMode(shouldOpenFullscreen ? 'fullscreen' : 'panel');
            this.isOpen = true;
            this.container.classList.add('is-open');
            this.updateLauncherA11y();
            this.scheduleViewportClamp();

            var payload = this.getBrowsingContextPayload();
            var signature = this.getContextSignature(payload);

            // Lazy-load iframe on first open; reload src when parent page context changes.
            if (!this.iframe.src) {
                this.lastIframeContextSignature = signature;
                var iframeSrc = this.buildIframeUrl();
                if (iframeSrc) { this.iframe.src = iframeSrc; }
            } else if (signature !== this.lastIframeContextSignature) {
                this.lastIframeContextSignature = signature;
                var iframeSrc = this.buildIframeUrl();
                if (iframeSrc) { this.iframe.src = iframeSrc; }
            } else {
                this.deliverBrowsingContextToIframe();
            }

            if (this.config.focusOnOpen) {
                this.focusCompanion();
            }

            this.saveOpenState(true);
            this.saveNavigationState();
        },

        close: function() {
            this.isOpen = false;
            this.container.classList.remove('is-open');
            this.updateLauncherA11y();
            this.saveOpenState(false);
            this.saveNavigationState();
        },

        getNavigationStateKey: function() {
            var originScope = '';
            try {
                originScope = String(window.location.origin || '').toLowerCase().replace(/[^a-z0-9]+/g, '_');
            } catch (e) {
                originScope = 'site';
            }
            if (!originScope) {
                originScope = 'site';
            }
            return 'flosc_companion_nav_state_' + originScope;
        },

        getLegacyNavigationStateKey: function() {
            return String(this.config.stateKey || 'flosc_companion_state') + '_nav';
        },

        saveNavigationState: function() {
            var payload = {
                isOpen: !!this.isOpen,
                panelMode: String(this.panelMode || 'panel'),
                ts: Date.now()
            };
            try {
                window.sessionStorage.setItem(this.getNavigationStateKey(), JSON.stringify(payload));
                // Backward-compatible write to previous key shape.
                window.sessionStorage.setItem(this.getLegacyNavigationStateKey(), JSON.stringify(payload));
            } catch (e) {
                // Ignore storage failures.
            }
        },

        restoreNavigationStateIfNeeded: function() {
            if (this.handoffRequested) {
                return;
            }

            try {
                var raw = window.sessionStorage.getItem(this.getNavigationStateKey());
                if (!raw) {
                    raw = window.sessionStorage.getItem(this.getLegacyNavigationStateKey());
                }
                if (!raw) {
                    return;
                }

                var state = JSON.parse(raw);
                if (!state || !state.isOpen) {
                    return;
                }

                this.open();

                var mode = String(state.panelMode || 'panel').toLowerCase();
                if (mode === 'expanded' || mode === 'fullscreen') {
                    this.setPanelMode(mode);
                }
            } catch (e) {
                // Ignore malformed nav state.
            }
        },

        /**
         * Iframe chat URL must stay on the configured FLOSC app origin only.
         * Never load arbitrary site pages inside the companion iframe.
         */
        resolveAllowedAppUrl: function() {
            try {
                if (!this.config.appUrl) {
                    return null;
                }
                var app = new URL(this.config.appUrl, window.location.origin);
                // Same origin as the page, or same origin as the configured app URL host.
                // Reject protocol-relative / javascript: / data: and off-app hosts.
                if (app.protocol !== 'http:' && app.protocol !== 'https:') {
                    return null;
                }
                return app;
            } catch (e) {
                return null;
            }
        },

        buildIframeUrl: function() {
            try {
                var url = this.resolveAllowedAppUrl();
                if (!url) {
                    return '';
                }
                url.searchParams.set('flosc_surface', 'companion');
                url.searchParams.set('flosc_companion', '1');

                if (this.config.returnUrl) {
                    url.searchParams.set('flosc_context_url', String(this.config.returnUrl));
                }

                if (this.config.contextParams && typeof this.config.contextParams === 'object') {
                    Object.keys(this.config.contextParams).forEach(function(key) {
                        var value = this.config.contextParams[key];
                        if (value === null || typeof value === 'undefined' || value === '') {
                            return;
                        }
                        url.searchParams.set(key, String(value));
                    }, this);
                }

                // Forward collapse/expand continuity into the chat iframe
                // (parent may be dainis.net; iframe is the chat host e.g. lesaep.com).
                // Prefer snapshotted params (survive address-bar cleanup), then live query.
                try {
                    var cont = (this.continuityParams && typeof this.continuityParams === 'object')
                        ? this.continuityParams
                        : {};
                    var parentParams = new URLSearchParams(window.location.search || '');
                    ['flosc_session_id', 'flosc_visitor_session', 'flosc_handoff'].forEach(function(key) {
                        var val = cont[key] || parentParams.get(key);
                        if (val) {
                            url.searchParams.set(key, String(val).slice(0, key === 'flosc_handoff' ? 8000 : 120));
                        }
                    });
                } catch (eFwd) {
                    // Ignore parent URL parse failures.
                }

                var payload = this.getBrowsingContextPayload();
                if (payload.page_url) {
                    url.searchParams.set('flosc_context_url', payload.page_url);
                }
                if (payload.page_title) {
                    url.searchParams.set('flosc_context_title', payload.page_title);
                }
                if (payload.page_path) {
                    url.searchParams.set('flosc_context_path', payload.page_path);
                }
                if (payload.page_referrer) {
                    url.searchParams.set('flosc_context_referrer', payload.page_referrer);
                }
                if (payload.surface) {
                    url.searchParams.set('flosc_context_surface', payload.surface);
                }
                if (payload.page_post_id) {
                    url.searchParams.set('flosc_context_post_id', String(payload.page_post_id));
                } else if (this.config.currentPagePostId) {
                    url.searchParams.set('flosc_context_post_id', String(this.config.currentPagePostId));
                } else if (this.config.contextParams && this.config.contextParams.flosc_context_post_id) {
                    url.searchParams.set('flosc_context_post_id', String(this.config.contextParams.flosc_context_post_id));
                }

                return url.toString();
            } catch (e) {
                return '';
            }
        },

        normalizeUrl: function(value) {
            var raw = String(value || '').trim();
            if (!raw) {
                return '';
            }
            try {
                var parsed = new URL(raw, window.location.origin);
                if (!/^https?:$/.test(parsed.protocol)) {
                    return '';
                }
                return parsed.toString();
            } catch (e) {
                return '';
            }
        },

        resolvePostIdFromPage: function() {
            var configured = parseInt(this.config.currentPagePostId, 10);
            if (Number.isFinite(configured) && configured > 0) {
                return configured;
            }

            var body = document.body;
            if (body) {
                var dataId = parseInt(body.getAttribute('data-flosc-post-id') || body.dataset.floscPostId || '', 10);
                if (Number.isFinite(dataId) && dataId > 0) {
                    return dataId;
                }
            }

            var className = String((body && body.className) || '');
            // Support common WP/body class variants across themes:
            // postid-123, postid123, page-id-456, pageid456.
            var match = className.match(/\bpostid-?(\d+)\b/);
            if (match) {
                return parseInt(match[1], 10) || 0;
            }
            match = className.match(/\bpage-id-?(\d+)\b/);
            if (match) {
                return parseInt(match[1], 10) || 0;
            }
            match = className.match(/\bsingle-post-(\d+)\b/);
            if (match) {
                return parseInt(match[1], 10) || 0;
            }
            return 0;
        },

        readTrail: function() {
            try {
                var raw = window.sessionStorage.getItem('flosc_companion_surf_trail') || '[]';
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        },

        writeTrail: function(trail) {
            try {
                window.sessionStorage.setItem('flosc_companion_surf_trail', JSON.stringify(trail || []));
            } catch (e) {
                // Ignore storage failures.
            }
        },

        upsertTrailEntry: function(entry) {
            var next = this.readTrail();
            var url = this.normalizeUrl(entry && entry.url ? entry.url : '');
            if (!url) {
                return next;
            }

            next = next.filter(function(item) {
                return item && String(item.url || '') !== url;
            });

            next.push({
                url: url,
                title: String((entry && entry.title) || '').slice(0, 180),
                path: String((entry && entry.path) || '').slice(0, 240)
            });

            if (next.length > 10) {
                next = next.slice(next.length - 10);
            }

            this.writeTrail(next);
            return next;
        },

        isAppUrl: function(url) {
            try {
                var current = new URL(url, window.location.origin);
                var app = new URL(this.config.appUrl, window.location.origin);
                var clean = function(pathname) {
                    return String(pathname || '/').replace(/\/+$/, '') || '/';
                };
                return clean(current.pathname) === clean(app.pathname);
            } catch (e) {
                return false;
            }
        },

        captureCurrentSiteContext: function() {
            var pageUrl = this.normalizeUrl(window.location.href);
            var title = String(document.title || '').trim().slice(0, 180);
            var path = String(window.location.pathname || '/').trim().slice(0, 240);
            var referrer = this.normalizeUrl(document.referrer || '');
            var postId = this.resolvePostIdFromPage();
            if (!postId && this.config.contextParams && this.config.contextParams.flosc_context_post_id) {
                postId = parseInt(this.config.contextParams.flosc_context_post_id, 10) || 0;
            }
            if (!postId && this.config.currentPagePostId) {
                postId = parseInt(this.config.currentPagePostId, 10) || 0;
            }

            var trail = this.upsertTrailEntry({
                url: pageUrl,
                title: title,
                path: path
            });

            if (pageUrl && !this.isAppUrl(pageUrl)) {
                try {
                    window.sessionStorage.setItem('flosc_last_site_page', pageUrl);
                } catch (e) {
                    // Ignore storage failures.
                }
            }

            this.browsingContext = {
                page_url: pageUrl,
                page_title: title,
                page_path: path,
                page_referrer: referrer,
                page_post_id: postId,
                surface: 'companion',
                trail: trail
            };
        },

        getBrowsingContextPayload: function() {
            return this.browsingContext || {
                page_url: '',
                page_title: '',
                page_path: '',
                page_referrer: '',
                page_post_id: 0,
                surface: 'companion',
                trail: []
            };
        },

        getContextSignature: function(payload) {
            try {
                return JSON.stringify(payload || {});
            } catch (e) {
                return '';
            }
        },

        postBrowsingContextToIframe: function() {
            if (!this.iframe || !this.iframe.contentWindow) {
                return;
            }

            var payload = this.getBrowsingContextPayload();
            try {
                var targetOrigin = new URL(this.iframe.src || this.config.appUrl, window.location.origin).origin;
                this.iframe.contentWindow.postMessage({
                    type: 'flosc_companion_context',
                    payload: payload
                }, targetOrigin);
            } catch (e) {
                // Ignore cross-window messaging failures.
            }
        },

        deliverBrowsingContextToIframe: function() {
            var self = this;
            var delays = [0, 120, 500, 1200];
            delays.forEach(function(delayMs) {
                window.setTimeout(function() {
                    self.captureCurrentSiteContext();
                    self.postBrowsingContextToIframe();
                }, delayMs);
            });
        },

        requestHandoffPayloadFromIframe: function() {
            var self = this;
            return new Promise(function(resolve) {
                if (!self.iframe || !self.iframe.contentWindow) {
                    resolve({});
                    return;
                }

                var settled = false;
                var done = function(payload) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    window.removeEventListener('message', onMessage);
                    resolve(payload && typeof payload === 'object' ? payload : {});
                };

                var onMessage = function(event) {
                    if (!self.iframe || event.source !== self.iframe.contentWindow) {
                        return;
                    }

                    try {
                        var appOrigin = new URL(self.config.appUrl, window.location.origin).origin;
                        if (String(event.origin || '') !== appOrigin) {
                            return;
                        }
                    } catch (e) {
                        return;
                    }

                    var data = event.data || {};
                    if (data.type !== 'flosc_companion_session_handoff' || !data.payload || typeof data.payload !== 'object') {
                        return;
                    }

                    done(data.payload);
                };

                window.addEventListener('message', onMessage);

                var targetOrigin = '*';
                try {
                    targetOrigin = new URL(self.iframe.src || self.config.appUrl, window.location.origin).origin;
                } catch (e) {
                    targetOrigin = '*';
                }

                try {
                    self.iframe.contentWindow.postMessage({ type: 'flosc_companion_request_session_handoff' }, targetOrigin);
                } catch (e) {
                    done({});
                    return;
                }

                window.setTimeout(function() {
                    done({});
                }, 450);
            });
        },

        refreshIframeContextIfNeeded: function() {
            if (!this.iframe || !this.iframe.src) {
                return;
            }

            var payload = this.getBrowsingContextPayload();
            var signature = this.getContextSignature(payload);
            if (signature === this.lastIframeContextSignature) {
                this.postBrowsingContextToIframe();
                return;
            }

            this.lastIframeContextSignature = signature;
            this.postBrowsingContextToIframe();
        },

        updateLauncherA11y: function() {
            var fab = this.container ? this.container.querySelector('.flosc-companion-fab') : null;
            if (!fab) {
                return;
            }
            var openLabel = this.config.launcherOpenAriaLabel || this.config.launcherAriaLabel || 'Open Chat';
            var collapseLabel = this.config.launcherCollapseAriaLabel || this.config.closeAriaLabel || 'Collapse Chat';
            fab.setAttribute('aria-label', this.isOpen ? collapseLabel : openLabel);
            fab.setAttribute('aria-expanded', this.isOpen ? 'true' : 'false');
        },

        syncViewportCssVars: function() {
            if (!this.container) {
                return;
            }

            var viewport = window.visualViewport;
            var vh = viewport && viewport.height ? viewport.height : window.innerHeight;
            var vw = viewport && viewport.width ? viewport.width : window.innerWidth;

            if (!vh || !vw) {
                return;
            }

            this.container.style.setProperty('--flosc-companion-viewport-height', Math.max(1, Math.round(vh)) + 'px');
            this.container.style.setProperty('--flosc-companion-viewport-width', Math.max(1, Math.round(vw)) + 'px');

            this.clampWindowToViewport(vh, vw);
        },

        scheduleViewportClamp: function() {
            if (!this.container) {
                return;
            }

            var self = this;
            if (this._viewportClampTimer) {
                window.clearTimeout(this._viewportClampTimer);
                this._viewportClampTimer = null;
            }

            var delays = [0, 120, 300, 650];
            delays.forEach(function(delay) {
                window.setTimeout(function() {
                    self.syncViewportCssVars();
                }, delay);
            });

            this._viewportClampTimer = window.setTimeout(function() {
                self._viewportClampTimer = null;
            }, 700);
        },

        clampWindowToViewport: function(viewportHeight, viewportWidth) {
            if (!this.container || !this.isOpen || this.isFullscreen) {
                return;
            }

            var windowEl = this.container.querySelector('.flosc-companion-window');
            if (!windowEl) {
                return;
            }

            var panelMode = String(this.panelMode || 'panel').toLowerCase();
            var configuredHeight = parseInt(this.config && this.config.height, 10);
            if (!Number.isFinite(configuredHeight) || configuredHeight <= 0) {
                configuredHeight = 560;
            }

            var desiredHeight = panelMode === 'expanded' ? 820 : configuredHeight;
            var edgeGap = 16;
            var launcherSize = parseInt(this.config && this.config.launcherSize, 10);
            if (!Number.isFinite(launcherSize) || launcherSize <= 0) {
                launcherSize = 60;
            }

            var visual = window.visualViewport;
            var offsetTop = visual && Number.isFinite(visual.offsetTop) ? Math.max(0, visual.offsetTop) : 0;
            var availableHeight = Math.max(260, viewportHeight - edgeGap - (launcherSize + 16));
            var targetHeight = Math.min(desiredHeight, Math.floor(availableHeight));

            windowEl.style.maxHeight = targetHeight + 'px';
            windowEl.style.height = targetHeight + 'px';

            var self = this;
            window.requestAnimationFrame(function() {
                var rect = windowEl.getBoundingClientRect();
                var minTop = Math.max(0, Math.floor(offsetTop + edgeGap));
                if (rect.top >= minTop) {
                    return;
                }

                // Final guard: if browser chrome/toolbar animation changed after
                // resize, shrink just enough so header always remains visible.
                var overflow = Math.ceil(minTop - rect.top);
                var corrected = Math.max(260, Math.floor(targetHeight - overflow));
                windowEl.style.maxHeight = corrected + 'px';
                windowEl.style.height = corrected + 'px';
                self.container.style.setProperty('--flosc-companion-viewport-width', Math.max(1, Math.round(viewportWidth)) + 'px');
            });
        },

        scheduleAutoOpen: function() {
            var self = this;
            if (this.config.skipBehaviorTriggers) {
                return;
            }
            if (!this.config.autoOpenEnabled) {
                return;
            }

            if (this.config.autoOpenOncePerSession && this.getAutoOpenSessionFlag()) {
                return;
            }

            var delay = Number(this.config.autoOpenDelayMs);
            if (!isFinite(delay) || delay < 0) {
                delay = 0;
            }
            delay = Math.max(delay, Number(this.config.triggerMinPageTimeMs) || 0);

            window.setTimeout(function() {
                self.tryOpenFromBehavior('auto_open_timer');
            }, delay);
        },

        bindBehaviorTriggers: function() {
            var self = this;

            if (this.config.skipBehaviorTriggers) {
                return;
            }

            if (this.config.launchOnExitIntent) {
                document.addEventListener('mouseout', function(event) {
                    if (!event || typeof event.clientY !== 'number') {
                        return;
                    }
                    var leavingWindow = !event.relatedTarget && !event.toElement;
                    if (leavingWindow && event.clientY <= 12) {
                        self.tryOpenFromBehavior('exit_intent');
                    }
                });
            }

            if (this.config.launchOnScrollThreshold) {
                var threshold = Number(this.config.launchOnScrollPercent);
                if (!isFinite(threshold)) {
                    threshold = 0;
                }
                threshold = Math.max(0, Math.min(100, threshold));

                window.addEventListener('scroll', function onScroll() {
                    var doc = document.documentElement;
                    var maxScroll = Math.max(1, (doc.scrollHeight || 0) - (window.innerHeight || 0));
                    var current = Math.max(0, window.scrollY || window.pageYOffset || 0);
                    var percent = (current / maxScroll) * 100;

                    if (percent >= threshold) {
                        self.tryOpenFromBehavior('scroll_threshold');
                        window.removeEventListener('scroll', onScroll);
                    }
                });
            }
        },

        tryOpenFromBehavior: function(source) {
            var isAutoOpenTimer = source === 'auto_open_timer';

            if (this.isOpen) {
                return;
            }
            if (!this.canRunBehaviorTrigger()) {
                return;
            }
            if (isAutoOpenTimer && this.config.autoOpenOncePerSession && this.getAutoOpenSessionFlag()) {
                return;
            }
            if (this.isTriggerCoolingDown()) {
                return;
            }

            this.open();
            this.markTriggerFired();

            if (isAutoOpenTimer && this.config.autoOpenOncePerSession) {
                this.setAutoOpenSessionFlag();
            }
        },

        canRunBehaviorTrigger: function() {
            if (this.config.triggerDesktopOnly && window.matchMedia && !window.matchMedia('(min-width: 768px)').matches) {
                return false;
            }

            var minMs = Number(this.config.triggerMinPageTimeMs) || 0;
            if (minMs > 0) {
                var elapsed = Date.now() - this.pageStartMs;
                if (elapsed < minMs) {
                    return false;
                }
            }

            var path = String(this.config.currentPath || window.location.pathname || '/');
            if (this.config.triggerSuppressOnAuthCheckout && this.isAuthOrCheckoutPath(path)) {
                return false;
            }

            if (Array.isArray(this.config.triggerSuppressPathPatterns)) {
                for (var i = 0; i < this.config.triggerSuppressPathPatterns.length; i++) {
                    var pattern = String(this.config.triggerSuppressPathPatterns[i] || '');
                    if (pattern && this.pathStartsWith(path, pattern)) {
                        return false;
                    }
                }
            }

            return true;
        },

        isAuthOrCheckoutPath: function(path) {
            var p = path.toLowerCase();
            var keywords = ['/checkout', '/cart', '/login', '/register', '/my-account', '/account', '/password-reset', '/wp-login'];
            for (var i = 0; i < keywords.length; i++) {
                if (this.pathStartsWith(p, keywords[i])) {
                    return true;
                }
            }
            return false;
        },

        pathStartsWith: function(path, prefix) {
            var a = '/' + String(path || '').replace(/^\/+/, '');
            var b = '/' + String(prefix || '').replace(/^\/+/, '');
            return a === b || (a + '/').indexOf(b + '/') === 0;
        },

        focusCompanion: function() {
            var self = this;
            window.setTimeout(function() {
                if (!self.iframe) {
                    return;
                }
                try {
                    self.iframe.setAttribute('tabindex', '-1');
                    self.iframe.focus();
                } catch (e) {
                    // Ignore focus failures.
                }
            }, 0);
        },

        isTypingTarget: function(target) {
            if (!target || !target.tagName) {
                return false;
            }
            var tag = String(target.tagName).toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                return true;
            }
            return !!target.isContentEditable;
        },

        getStorageBackend: function() {
            try {
                return this.config.stateStorage === 'local' ? window.localStorage : window.sessionStorage;
            } catch (e) {
                return null;
            }
        },

        saveOpenState: function(isOpen) {
            if (!this.config.rememberOpenState) {
                return;
            }
            var storage = this.getStorageBackend();
            if (!storage) {
                return;
            }
            try {
                storage.setItem(this.config.stateKey, isOpen ? 'open' : 'closed');
            } catch (e) {
                // Ignore storage failures.
            }
        },

        restoreOpenStateIfNeeded: function() {
            if (!this.config.rememberOpenState || this.isOpen) {
                return;
            }
            var storage = this.getStorageBackend();
            if (!storage) {
                return;
            }
            try {
                if (storage.getItem(this.config.stateKey) === 'open') {
                    this.open();
                }
            } catch (e) {
                // Ignore storage failures.
            }
        },

        isTriggerCoolingDown: function() {
            var cooldownMs = Number(this.config.triggerCooldownMs) || 0;
            if (cooldownMs <= 0) {
                return false;
            }
            var storage = this.getStorageBackend();
            if (!storage) {
                return false;
            }
            try {
                var raw = storage.getItem(this.config.cooldownKey);
                if (!raw) {
                    return false;
                }
                var lastTs = Number(raw);
                if (!isFinite(lastTs) || lastTs <= 0) {
                    return false;
                }
                return (Date.now() - lastTs) < cooldownMs;
            } catch (e) {
                return false;
            }
        },

        markTriggerFired: function() {
            var storage = this.getStorageBackend();
            if (!storage) {
                return;
            }
            try {
                storage.setItem(this.config.cooldownKey, String(Date.now()));
            } catch (e) {
                // Ignore storage failures.
            }
        },

        getAutoOpenSessionFlag: function() {
            try {
                return window.sessionStorage.getItem(this.config.sessionKey) === '1';
            } catch (e) {
                return false;
            }
        },

        setAutoOpenSessionFlag: function() {
            try {
                window.sessionStorage.setItem(this.config.sessionKey, '1');
            } catch (e) {
                // Ignore storage availability failures.
            }
        },

        toggleFullscreen: function() {
            this.setFullscreen(!this.isFullscreen);
        },

        setFullscreen: function(enabled) {
            this.setPanelMode(enabled ? 'fullscreen' : 'panel');
        },

        setPanelMode: function(mode) {
            mode = String(mode || 'panel').toLowerCase();

            this.container.classList.remove('is-panel', 'is-expanded', 'is-fullscreen');

            if (mode === 'fullscreen') {
                this.isFullscreen = true;
                this.panelMode = 'fullscreen';
                this.container.classList.add('is-fullscreen');
                this.updateExpandA11y();
                this.saveNavigationState();
                return;
            }

            this.isFullscreen = false;
            if (mode === 'expanded') {
                this.panelMode = 'expanded';
                this.container.classList.add('is-expanded');
                this.updateExpandA11y();
                this.saveNavigationState();
                return;
            }

            this.panelMode = 'panel';
            this.container.classList.add('is-panel');
            this.updateExpandA11y();
            this.saveNavigationState();
        },

        updateExpandA11y: function() {
            var expandBtn = this.container ? this.container.querySelector('.flosc-companion-expand') : null;
            if (!expandBtn) {
                return;
            }
            var expanded = this.container.classList.contains('is-expanded');
            var label = expanded ? 'Collapse chat' : 'Expand chat';
            expandBtn.setAttribute('aria-label', label);
            expandBtn.setAttribute('title', label);
        },

        detectHandoffRequest: function() {
            try {
                if (this.config.skipBehaviorTriggers) {
                    return true;
                }
                var params = new URLSearchParams(window.location.search || '');
                return params.get('flosc_companion_handoff') === '1';
            } catch (e) {
                return false;
            }
        },

        /**
         * Read continuity query args once at shell init (before consumeHandoffRequest).
         * @return {Object}
         */
        captureContinuityParamsFromPage: function() {
            var out = {};
            try {
                var params = new URLSearchParams(window.location.search || '');
                ['flosc_session_id', 'flosc_visitor_session', 'flosc_handoff'].forEach(function(key) {
                    var val = params.get(key);
                    if (val) {
                        out[key] = String(val);
                    }
                });
            } catch (e) {
                // Ignore.
            }
            return out;
        },

        consumeHandoffRequest: function() {
            try {
                var url = new URL(window.location.href);
                if (!url.searchParams.has('flosc_companion_handoff')) {
                    return;
                }
                url.searchParams.delete('flosc_companion_handoff');
                url.searchParams.delete('flosc_companion_open');
                url.searchParams.delete('flosc_companion_mode');
                url.searchParams.delete('flosc_companion_expand_target');
                // Continuity params were snapshotted into this.continuityParams and
                // applied to the iframe src — safe to clean the hub address bar.
                url.searchParams.delete('flosc_session_id');
                url.searchParams.delete('flosc_visitor_session');
                url.searchParams.delete('flosc_handoff');
                window.history.replaceState({}, document.title, url.toString());
            } catch (e) {
                // Ignore URL update failures.
            }
        },

        forceMinimizedStateForHandoff: function() {
            var storage = this.getStorageBackend();
            if (!storage) {
                return;
            }
            try {
                storage.setItem(this.config.stateKey, 'closed');
            } catch (e) {
                // Ignore storage failures.
            }
        },

        applyHandoffRequest: function() {
            try {
                var params = new URLSearchParams(window.location.search || '');
                if (params.get('flosc_companion_handoff') !== '1') {
                    return;
                }

                var mode = String(params.get('flosc_companion_mode') || 'panel').toLowerCase();
                this.open();

                if (mode === 'expanded') {
                    this.setPanelMode('expanded');
                } else if (mode === 'fullscreen') {
                    this.setPanelMode('fullscreen');
                } else {
                    this.setPanelMode('panel');
                }

                this.consumeHandoffRequest();
            } catch (e) {
                // Ignore malformed query params.
            }
        },

        openFullPage: function() {
            var self = this;

            var navigate = function(handoffPayload) {
                try {
                    var target = new URL(self.config.fullPageUrl || self.config.appUrl, window.location.origin);
                    target.searchParams.delete('flosc_companion');
                    target.searchParams.set('flosc_surface', 'full');
                    if (window.location.href) {
                        target.searchParams.set('flosc_context_url', window.location.href);
                    }

                    if (handoffPayload && typeof handoffPayload === 'object') {
                        var sid = String(handoffPayload.sessionId || handoffPayload.session_id || '').trim();
                        var kind = String(handoffPayload.kind || '').toLowerCase();
                        // Guest/member: server session id. Visitor: client session + optional message pack.
                        if (!sid) {
                            // nothing to attach
                        } else if (kind === 'visitor') {
                            target.searchParams.set('flosc_visitor_session', sid.slice(0, 80));
                            var handoffObj = {
                                kind: 'visitor',
                                sessionId: sid.slice(0, 80),
                                messages: Array.isArray(handoffPayload.messages) ? handoffPayload.messages.slice(-50) : []
                            };
                            try {
                                var packed = btoa(unescape(encodeURIComponent(JSON.stringify(handoffObj))));
                                if (packed && packed.length <= 6000) {
                                    target.searchParams.set('flosc_handoff', packed);
                                }
                            } catch (e) {
                                // If encoding fails, continue with session id only.
                            }
                        } else {
                            // Logged-in (or unknown-kind with a server session id from iframe).
                            target.searchParams.set('flosc_session_id', sid.slice(0, 80));
                        }
                    }

                    window.location.assign(target.toString());
                } catch (e) {
                    window.location.assign(self.config.fullPageUrl || self.config.appUrl);
                }
            };

            this.requestHandoffPayloadFromIframe()
                .then(navigate)
                .catch(function() { navigate({}); });
        },

        escapeHtml: function(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    window.FloscCompanion = FloscCompanion;
})(window, document);
