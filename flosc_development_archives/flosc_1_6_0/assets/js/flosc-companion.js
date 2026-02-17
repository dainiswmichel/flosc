/**
 * FLOSC Companion Widget
 * 
 * Self-contained floating chat companion for WordPress pages.
 * This module is completely independent from flosc-app.js — it has its own
 * UI, state management, and REST API communication.
 * 
 * Reads: window.FLOSC_COMPANION (injected by class-companion-widget.php)
 * 
 * Features:
 * - Floating widget with expand/collapse toggle
 * - Page-context-aware: knows which lesson the user is reading
 * - Contextual suggestions: "Ask about this lesson", "Next lesson", etc.
 * - Message input with AI-powered responses via existing REST API
 * - Lesson table of contents panel
 * - Minimal, warm UI that complements (not replaces) the WordPress theme
 * 
 * Architecture:
 * - IIFE — no globals except the widget root DOM node
 * - All state is local (no localStorage dependency for core functionality)
 * - REST calls go to the same /flosc/v1/ endpoints as the main app
 * - CSS classes follow BEM convention: .flosc-companion__[element]--[modifier]
 * 
 * @package FLOSC
 * @since   1.6.0
 */

(function () {
    'use strict';

    // ──────────────────────────────────────────────────────────────
    // Bootstrap — wait for config
    // ──────────────────────────────────────────────────────────────

    const config = window.FLOSC_COMPANION;
    if (!config) {
        console.warn('[FLOSC Companion] Missing FLOSC_COMPANION config — widget will not load.');
        return;
    }

    const { restUrl, nonce, user, page, settings } = config;

    // ──────────────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────────────

    const state = {
        expanded:    false,
        activePanel: 'chat',   // 'chat' | 'toc'
        messages:    [],
        loading:     false,
        lessons:     null,     // cached lesson list
        initialized: false,
    };

    // ──────────────────────────────────────────────────────────────
    // DOM References (populated in init)
    // ──────────────────────────────────────────────────────────────

    let root         = null;   // #flosc-companion-root
    let toggleBtn    = null;
    let panel        = null;   // main panel container
    let chatBody     = null;   // scrollable message area
    let chatInput    = null;   // text input
    let sendBtn      = null;
    let tocPanel     = null;   // lessons table of contents

    // ──────────────────────────────────────────────────────────────
    // REST API Helper
    // ──────────────────────────────────────────────────────────────

    /**
     * Make a REST API request to the FLOSC endpoints
     * 
     * @param {string} endpoint  Path relative to restUrl (e.g. 'chat', 'lessons')
     * @param {Object} options   fetch options override
     * @returns {Promise<Object>}
     */
    async function api(endpoint, options = {}) {
        const url = restUrl + endpoint;
        const resp = await fetch(url, {
            headers: {
                'Content-Type':  'application/json',
                'X-WP-Nonce':    nonce,
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
            ...options,
        });

        if (!resp.ok) {
            const errorText = await resp.text().catch(() => resp.statusText);
            throw new Error(`[FLOSC Companion] API ${resp.status}: ${errorText}`);
        }

        return resp.json();
    }

    // ──────────────────────────────────────────────────────────────
    // UI Builder
    // ──────────────────────────────────────────────────────────────

    /**
     * Build the complete widget DOM structure
     * 
     * Layout:
     * ┌─────────────────────────────────┐
     * │  Header (title + nav tabs)      │
     * ├─────────────────────────────────┤
     * │  Body   (chat messages / TOC)   │
     * ├─────────────────────────────────┤
     * │  Footer (input + send button)   │
     * └─────────────────────────────────┘
     * ┌──────┐
     * │ FAB  │  ← floating action button (toggle)
     * └──────┘
     */
    function buildUI() {
        root = document.getElementById('flosc-companion-root');
        if (!root) {
            console.warn('[FLOSC Companion] #flosc-companion-root not found.');
            return false;
        }

        // Toggle button (FAB)
        toggleBtn = el('button', {
            className: 'flosc-companion__toggle',
            'aria-label': 'Open learning companion',
            onclick: toggleWidget,
        }, [
            el('span', { className: 'flosc-companion__toggle-icon flosc-companion__toggle-icon--chat' }, '💬'),
            el('span', { className: 'flosc-companion__toggle-icon flosc-companion__toggle-icon--close' }, '✕'),
        ]);

        // Header
        const headerNav = el('div', { className: 'flosc-companion__nav' }, [
            el('button', {
                className: 'flosc-companion__nav-btn flosc-companion__nav-btn--active',
                'data-panel': 'chat',
                onclick: () => switchPanel('chat'),
            }, 'Chat'),
            el('button', {
                className: 'flosc-companion__nav-btn',
                'data-panel': 'toc',
                onclick: () => switchPanel('toc'),
            }, 'Lessons'),
        ]);

        const header = el('div', { className: 'flosc-companion__header' }, [
            el('div', { className: 'flosc-companion__title' }, [
                el('span', { className: 'flosc-companion__title-icon' }, '🎓'),
                el('span', {}, 'Companion'),
            ]),
            headerNav,
            el('button', {
                className: 'flosc-companion__close',
                'aria-label': 'Close companion',
                onclick: toggleWidget,
            }, '✕'),
        ]);

        // Chat panel
        chatBody = el('div', { className: 'flosc-companion__chat-body' });

        const chatFooter = el('div', { className: 'flosc-companion__chat-footer' }, [
            chatInput = el('input', {
                type: 'text',
                className: 'flosc-companion__input',
                placeholder: 'Ask about this lesson…',
                onkeydown: handleInputKeydown,
            }),
            sendBtn = el('button', {
                className: 'flosc-companion__send',
                'aria-label': 'Send message',
                onclick: sendMessage,
            }, '→'),
        ]);

        const chatPanel = el('div', {
            className: 'flosc-companion__panel flosc-companion__panel--chat flosc-companion__panel--active',
            'data-panel': 'chat',
        }, [chatBody, chatFooter]);

        // TOC panel (lessons)
        tocPanel = el('div', {
            className: 'flosc-companion__panel flosc-companion__panel--toc',
            'data-panel': 'toc',
        }, [
            el('div', { className: 'flosc-companion__toc-loading' }, 'Loading lessons…'),
        ]);

        // Main panel
        panel = el('div', { className: 'flosc-companion__panel-container' }, [
            header,
            chatPanel,
            tocPanel,
        ]);

        // Assemble
        root.innerHTML = '';
        root.appendChild(panel);
        root.appendChild(toggleBtn);

        return true;
    }

    // ──────────────────────────────────────────────────────────────
    // Widget Toggle
    // ──────────────────────────────────────────────────────────────

    function toggleWidget() {
        state.expanded = !state.expanded;
        root.classList.toggle('flosc-companion--expanded', state.expanded);
        toggleBtn.setAttribute('aria-label',
            state.expanded ? 'Close learning companion' : 'Open learning companion'
        );

        if (state.expanded && !state.initialized) {
            initialize();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Panel Switching (Chat ↔ TOC)
    // ──────────────────────────────────────────────────────────────

    function switchPanel(panelName) {
        state.activePanel = panelName;

        // Update nav buttons
        root.querySelectorAll('.flosc-companion__nav-btn').forEach(btn => {
            btn.classList.toggle(
                'flosc-companion__nav-btn--active',
                btn.dataset.panel === panelName
            );
        });

        // Update panels
        root.querySelectorAll('.flosc-companion__panel').forEach(p => {
            p.classList.toggle(
                'flosc-companion__panel--active',
                p.dataset.panel === panelName
            );
        });

        // Lazy-load TOC on first switch
        if (panelName === 'toc' && !state.lessons) {
            loadLessonsTOC();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Initialization (on first expand)
    // ──────────────────────────────────────────────────────────────

    function initialize() {
        state.initialized = true;

        // Add greeting
        addAssistantMessage(settings.greeting);

        // Context-aware suggestions
        if (page.type === 'lesson') {
            addAssistantMessage(
                `I see you're reading <strong>${escHtml(page.title)}</strong>. ` +
                `Feel free to ask me anything about this lesson!`
            );
            addSuggestionChips([
                { label: 'Summarize this lesson',    query: `Summarize the lesson "${page.title}"` },
                { label: 'Quiz me on this',          query: `Quiz me on the key concepts from "${page.title}"` },
                { label: 'What\'s next?',            query: 'What lesson should I study next?' },
            ]);
        } else if (page.type === 'lesson_archive') {
            addAssistantMessage(
                'You\'re browsing the lesson library. Tap any lesson to start reading, ' +
                'or ask me for a recommendation!'
            );
            addSuggestionChips([
                { label: 'Recommend a lesson',    query: 'Which lesson should I start with?' },
                { label: 'My progress',           query: 'Show me my learning progress' },
            ]);
        } else {
            // Generic pages
            if (user.loggedIn && user.isMember) {
                addSuggestionChips([
                    { label: 'Continue learning',  query: 'Where did I leave off?' },
                    { label: 'Browse lessons',     query: 'Show me available lessons' },
                ]);
            } else if (user.loggedIn) {
                addSuggestionChips([
                    { label: 'My free lessons',    query: 'Show me my free lessons' },
                    { label: 'See all courses',    query: 'What courses are available?' },
                ]);
            }
        }

        chatBody.scrollTop = chatBody.scrollHeight;
    }

    // ──────────────────────────────────────────────────────────────
    // Chat Messages
    // ──────────────────────────────────────────────────────────────

    function addAssistantMessage(html) {
        const msg = el('div', { className: 'flosc-companion__message flosc-companion__message--assistant' }, [
            el('div', { className: 'flosc-companion__message-avatar' }, '🎓'),
            el('div', { className: 'flosc-companion__message-content' }),
        ]);
        msg.querySelector('.flosc-companion__message-content').innerHTML = html;
        chatBody.appendChild(msg);
        state.messages.push({ role: 'assistant', content: html });
        scrollChatToBottom();
    }

    function addUserMessage(text) {
        const msg = el('div', { className: 'flosc-companion__message flosc-companion__message--user' }, [
            el('div', { className: 'flosc-companion__message-content' }, text),
        ]);
        chatBody.appendChild(msg);
        state.messages.push({ role: 'user', content: text });
        scrollChatToBottom();
    }

    function addSuggestionChips(chips) {
        const container = el('div', { className: 'flosc-companion__suggestions' },
            chips.map(chip =>
                el('button', {
                    className: 'flosc-companion__chip',
                    onclick: () => {
                        // Remove chip container after click
                        container.remove();
                        submitQuery(chip.query);
                    },
                }, chip.label)
            )
        );
        chatBody.appendChild(container);
        scrollChatToBottom();
    }

    function showTypingIndicator() {
        const indicator = el('div', {
            className: 'flosc-companion__message flosc-companion__message--assistant flosc-companion__typing',
        }, [
            el('div', { className: 'flosc-companion__message-avatar' }, '🎓'),
            el('div', { className: 'flosc-companion__message-content' }, [
                el('span', { className: 'flosc-companion__dot' }),
                el('span', { className: 'flosc-companion__dot' }),
                el('span', { className: 'flosc-companion__dot' }),
            ]),
        ]);
        chatBody.appendChild(indicator);
        scrollChatToBottom();
        return indicator;
    }

    function scrollChatToBottom() {
        requestAnimationFrame(() => {
            chatBody.scrollTop = chatBody.scrollHeight;
        });
    }

    // ──────────────────────────────────────────────────────────────
    // Chat Input Handling
    // ──────────────────────────────────────────────────────────────

    function handleInputKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    function sendMessage() {
        const text = chatInput.value.trim();
        if (!text || state.loading) return;

        chatInput.value = '';
        submitQuery(text);
    }

    /**
     * Submit a query to the AI and display the response
     * 
     * Builds context from the current page so the AI knows what
     * the user is looking at. Uses the existing /flosc/v1/chat endpoint.
     * 
     * @param {string} query User's question
     */
    async function submitQuery(query) {
        if (state.loading) return;
        state.loading = true;
        sendBtn.disabled = true;

        addUserMessage(query);
        const typing = showTypingIndicator();

        try {
            // Build context-enriched message
            const contextPrefix = page.type === 'lesson' && page.title
                ? `[User is currently reading the lesson: "${page.title}"] `
                : '';

            const response = await api('chat', {
                method: 'POST',
                body: JSON.stringify({
                    message: contextPrefix + query,
                    context: {
                        source:   'companion',
                        pageType: page.type,
                        postId:   page.postId,
                        title:    page.title,
                        tags:     page.tags,
                    },
                }),
            });

            typing.remove();

            const reply = response.reply || response.message || response.content || 'I\'m not sure how to answer that.';
            addAssistantMessage(reply);

        } catch (err) {
            typing.remove();
            console.error('[FLOSC Companion]', err);
            addAssistantMessage(
                '<em>Sorry, I couldn\'t process that right now. Please try again.</em>'
            );
        } finally {
            state.loading = false;
            sendBtn.disabled = false;
            chatInput.focus();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Lessons TOC Panel
    // ──────────────────────────────────────────────────────────────

    /**
     * Load lesson table of contents from REST API
     * Uses the existing /flosc/v1/lessons endpoint
     */
    async function loadLessonsTOC() {
        try {
            const lessons = await api('lessons');
            state.lessons = Array.isArray(lessons) ? lessons : (lessons.lessons || []);

            tocPanel.innerHTML = '';

            if (state.lessons.length === 0) {
                tocPanel.appendChild(
                    el('div', { className: 'flosc-companion__toc-empty' },
                        'No lessons available yet.')
                );
                return;
            }

            const list = el('div', { className: 'flosc-companion__toc-list' },
                state.lessons.map((lesson, index) => {
                    const isCurrent = page.type === 'lesson' && page.postId === lesson.id;
                    const item = el('a', {
                        className: 'flosc-companion__toc-item' +
                            (isCurrent ? ' flosc-companion__toc-item--current' : ''),
                        href: lesson.url || '#',
                        target: '_self',
                    }, [
                        el('span', { className: 'flosc-companion__toc-number' }, String(index + 1)),
                        el('div', { className: 'flosc-companion__toc-meta' }, [
                            el('span', { className: 'flosc-companion__toc-title' }, lesson.title || `Lesson ${index + 1}`),
                            lesson.excerpt
                                ? el('span', { className: 'flosc-companion__toc-excerpt' }, lesson.excerpt)
                                : null,
                        ]),
                        isCurrent
                            ? el('span', { className: 'flosc-companion__toc-badge' }, 'Reading')
                            : null,
                    ].filter(Boolean));
                    return item;
                })
            );

            tocPanel.appendChild(list);

        } catch (err) {
            console.error('[FLOSC Companion] Failed to load lessons:', err);
            tocPanel.innerHTML = '';
            tocPanel.appendChild(
                el('div', { className: 'flosc-companion__toc-empty' },
                    'Could not load lessons. Please try again.')
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    // DOM Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Create a DOM element with properties and children
     * 
     * @param {string}                tag        HTML tag name
     * @param {Object}                props      Attributes/properties
     * @param {Array|string|null}     children   Child elements or text content
     * @returns {HTMLElement}
     */
    function el(tag, props = {}, children = null) {
        const element = document.createElement(tag);

        for (const [key, value] of Object.entries(props)) {
            if (key === 'className') {
                element.className = value;
            } else if (key.startsWith('on') && typeof value === 'function') {
                element.addEventListener(key.slice(2).toLowerCase(), value);
            } else if (key === 'dataset') {
                Object.assign(element.dataset, value);
            } else if (key.startsWith('data-')) {
                element.setAttribute(key, value);
            } else {
                element.setAttribute(key, value);
            }
        }

        if (children !== null) {
            if (typeof children === 'string') {
                element.textContent = children;
            } else if (Array.isArray(children)) {
                children.forEach(child => {
                    if (typeof child === 'string') {
                        element.appendChild(document.createTextNode(child));
                    } else if (child instanceof HTMLElement) {
                        element.appendChild(child);
                    }
                });
            } else if (children instanceof HTMLElement) {
                element.appendChild(children);
            }
        }

        return element;
    }

    /**
     * Escape HTML to prevent XSS in user-provided content
     * @param {string} str
     * @returns {string}
     */
    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ──────────────────────────────────────────────────────────────
    // Keyboard Accessibility
    // ──────────────────────────────────────────────────────────────

    function handleKeyboard(e) {
        // Escape closes the widget
        if (e.key === 'Escape' && state.expanded) {
            toggleWidget();
            toggleBtn.focus();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Init
    // ──────────────────────────────────────────────────────────────

    function boot() {
        if (!buildUI()) return;

        // Global keyboard handler
        document.addEventListener('keydown', handleKeyboard);

        // Persist expand state across page navigations (optional UX)
        try {
            const wasExpanded = sessionStorage.getItem('flosc_companion_expanded');
            if (wasExpanded === 'true') {
                toggleWidget();
            }
        } catch (_) { /* sessionStorage blocked */ }

        // Save state on toggle for cross-page persistence
        const origToggle = toggleWidget;
        // We already handle state in toggleWidget, just add persistence:
        root.addEventListener('click', () => {
            try {
                sessionStorage.setItem('flosc_companion_expanded', String(state.expanded));
            } catch (_) { /* ignore */ }
        });
    }

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
