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

    var FloscCompanion = {
        isOpen: false,
        container: null,
        iframe: null,

        init: function(config) {
            this.config = Object.assign({
                appUrl: '',
                title: 'Chat with us',
                subtitle: 'We reply instantly',
                avatar: '💬',
                accentColor: '#2563eb',
                position: 'bottom-right',
                width: '380px',
                height: '560px'
            }, config);

            if (!this.config.appUrl) {
                console.warn('[FLOSC Companion] appUrl is required');
                return;
            }

            this.render();
            this.bindEvents();
        },

        render: function() {
            // Container
            this.container = document.createElement('div');
            this.container.className = 'flosc-companion';
            this.container.style.setProperty('--flosc-accent', this.config.accentColor);

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
                '<button class="flosc-companion-close" aria-label="Close chat">&times;</button>';

            // Iframe
            this.iframe = document.createElement('iframe');
            this.iframe.className = 'flosc-companion-body';
            this.iframe.setAttribute('loading', 'lazy');
            this.iframe.setAttribute('title', 'FLOSC Chat');

            window_el.appendChild(header);
            window_el.appendChild(this.iframe);

            // FAB button
            var fab = document.createElement('button');
            fab.className = 'flosc-companion-fab';
            fab.setAttribute('aria-label', 'Open chat');
            fab.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
                    '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>' +
                '</svg>';

            // Badge (for future notification count)
            var badge = document.createElement('span');
            badge.className = 'flosc-companion-badge';
            fab.appendChild(badge);

            this.container.appendChild(window_el);
            this.container.appendChild(fab);
            document.body.appendChild(this.container);
        },

        bindEvents: function() {
            var self = this;
            var fab = this.container.querySelector('.flosc-companion-fab');
            var closeBtn = this.container.querySelector('.flosc-companion-close');

            fab.addEventListener('click', function() {
                self.toggle();
            });

            closeBtn.addEventListener('click', function() {
                self.close();
            });

            // Keyboard: Escape to close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
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
            this.isOpen = true;
            this.container.classList.add('is-open');

            // Lazy-load iframe on first open
            if (!this.iframe.src) {
                var separator = this.config.appUrl.indexOf('?') !== -1 ? '&' : '?';
                this.iframe.src = this.config.appUrl + separator + 'flosc_companion=1';
            }
        },

        close: function() {
            this.isOpen = false;
            this.container.classList.remove('is-open');
        },

        escapeHtml: function(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    window.FloscCompanion = FloscCompanion;
})(window, document);
