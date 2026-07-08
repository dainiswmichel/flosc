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
 *       title: 'Chat with us',
 *       subtitle: 'We reply instantly',
 *       avatar: '💬',
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
        container: null,
        iframe: null,

        init: function(config) {
            this.config = Object.assign({
                appUrl: '',
                title: l10n.chatWithUs || 'Chat with us',
                subtitle: l10n.weReplyInstantly || 'We reply instantly',
                avatar: '💬',
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
                assistantTitle: l10n.floscAssistant || 'FLOSC Assistant',
                fullscreenLabel: l10n.toggleFullscreen || 'Toggle fullscreen',
                rememberOpenState: false,
                stateStorage: 'session',
                triggerCooldownMs: 0,
                stateKey: 'flosc_companion_state',
                cooldownKey: 'flosc_companion_cooldown'
            }, config);

            this.pageStartMs = Date.now();

            if (!this.config.appUrl) {
                console.warn('[FLOSC Companion] appUrl is required');
                return;
            }

            this.render();
            this.applyMotionMode();
            this.bindEvents();
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

            // Header
            var header = document.createElement('div');
            header.className = 'flosc-companion-header';
            header.innerHTML =
                '<div class="flosc-companion-header-info">' +
                    '<div class="flosc-companion-avatar">' + this.config.avatar + '</div>' +
                    '<div>' +
                        '<div class="flosc-companion-title">' + this.escapeHtml(this.config.title) + '</div>' +
                        '<div class="flosc-companion-subtitle">' + this.escapeHtml(this.config.subtitle) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="flosc-companion-header-actions">' +
                    (this.config.allowFullscreen ? '<button class="flosc-companion-fullscreen" aria-label="' + this.escapeHtml(this.config.fullscreenLabel) + '" title="' + this.escapeHtml(this.config.fullscreenLabel) + '">⤢</button>' : '') +
                    '<button class="flosc-companion-close" aria-label="' + this.escapeHtml(this.config.closeAriaLabel) + '"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg></button>' +
                '</div>';

            // Iframe
            this.iframe = document.createElement('iframe');
            this.iframe.className = 'flosc-companion-body';
            this.iframe.setAttribute('loading', 'lazy');
            this.iframe.setAttribute('title', this.config.assistantTitle || 'FLOSC Assistant');

            window_el.appendChild(header);
            window_el.appendChild(this.iframe);

            // FAB button
            var fab = document.createElement('button');
            fab.className = 'flosc-companion-fab flosc-launcher';
            fab.setAttribute('aria-label', this.config.launcherAriaLabel);
            fab.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
                    '<path d="' + this.escapeHtml(this.config.launcherSvgPath) + '"/>' +
                '</svg>';

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
            var fullscreenBtn = this.container.querySelector('.flosc-companion-fullscreen');

            fab.addEventListener('click', function() {
                self.toggle();
            });

            closeBtn.addEventListener('click', function() {
                self.close();
            });

            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', function() {
                    self.toggleFullscreen();
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

            this.setFullscreen(shouldOpenFullscreen);
            this.isOpen = true;
            this.container.classList.add('is-open');
            this.updateLauncherA11y();

            // Lazy-load iframe on first open
            if (!this.iframe.src) {
                var separator = this.config.appUrl.indexOf('?') !== -1 ? '&' : '?';
                var query = ['flosc_companion=1'];

                if (this.config.contextParams && typeof this.config.contextParams === 'object') {
                    Object.keys(this.config.contextParams).forEach(function(key) {
                        var value = this.config.contextParams[key];
                        if (value === null || typeof value === 'undefined' || value === '') {
                            return;
                        }
                        query.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
                    }, this);
                }

                this.iframe.src = this.config.appUrl + separator + query.join('&');
            }

            if (this.config.focusOnOpen) {
                this.focusCompanion();
            }

            this.saveOpenState(true);
        },

        close: function() {
            this.isOpen = false;
            this.container.classList.remove('is-open');
            this.updateLauncherA11y();
            this.saveOpenState(false);
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

        scheduleAutoOpen: function() {
            var self = this;
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
            if (!this.config.rememberOpenState) {
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
            this.isFullscreen = !!enabled;
            this.container.classList.toggle('is-fullscreen', this.isFullscreen);
        },

        escapeHtml: function(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    window.FloscCompanion = FloscCompanion;
})(window, document);
