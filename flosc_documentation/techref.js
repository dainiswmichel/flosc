/* ============================================================
   FLOSC Techref — SPA Navigation & Auto-TOC Generator
   2026-02m-24d
   
   Reads chapter HTML files, extracts H1-H4 headings,
   builds collapsible sidebar TOC, handles navigation.
   ============================================================ */

(function () {
    'use strict';

    // --- Chapter Registry ---
    // Each part maps to one or more chapter files.
    // The loader fetches these from the chapters/ directory.
    const CHAPTERS = [
        {
            id: 'part1-journey',
            part: 1,
            partTitle: 'The Journey',
            file: 'chapters/part1-journey.html'
        },
        {
            id: 'part2-architecture',
            part: 2,
            partTitle: 'Architecture',
            file: 'chapters/part2-architecture.html'
        },
        {
            id: 'part3-ref-core',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-core.html',
            subLabel: 'Core — flosc.php'
        },
        {
            id: 'part3-ref-ivr',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-ivr.html',
            subLabel: 'IVR System'
        },
        {
            id: 'part3-ref-ai',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-ai.html',
            subLabel: 'AI & RAG'
        },
        {
            id: 'part3-ref-quiz',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-quiz.html',
            subLabel: 'Quiz System'
        },
        {
            id: 'part3-ref-access',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-access.html',
            subLabel: 'Access & Sessions'
        },
        {
            id: 'part3-ref-payments',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-payments.html',
            subLabel: 'Payments & Offers'
        },
        {
            id: 'part3-ref-sso',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-sso.html',
            subLabel: 'SSO & OAuth'
        },
        {
            id: 'part3-ref-frontend',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-frontend.html',
            subLabel: 'Frontend — JS & CSS'
        },
        {
            id: 'part3-ref-admin',
            part: 3,
            partTitle: 'Reference',
            file: 'chapters/part3-ref-admin.html',
            subLabel: 'Admin Pages'
        },
        {
            id: 'part4-security',
            part: 4,
            partTitle: 'Security',
            file: 'chapters/part4-security.html'
        },
        {
            id: 'part5-glossary',
            part: 5,
            partTitle: 'Glossary',
            file: 'chapters/part5-glossary.html'
        }
    ];

    // --- State ---
    let currentChapterId = null;
    const chapterCache = {};  // id → { html, headings }

    // --- DOM refs ---
    const sidebar = document.getElementById('sidebar');
    const tocContainer = document.getElementById('toc-container');
    const contentEl = document.getElementById('chapter-content');
    const breadcrumb = document.getElementById('breadcrumb');
    const searchInput = document.getElementById('toc-search');
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebarToggle = document.getElementById('sidebar-toggle');

    // --- Init ---
    function init() {
        buildTOCShell();
        bindEvents();
        handleHashNavigation();
    }

    // --- Build the sidebar TOC structure (parts + chapter links) ---
    function buildTOCShell() {
        // Group chapters by part number
        const parts = {};
        CHAPTERS.forEach(ch => {
            if (!parts[ch.part]) {
                parts[ch.part] = {
                    partTitle: ch.partTitle,
                    chapters: []
                };
            }
            parts[ch.part].chapters.push(ch);
        });

        let html = '';
        Object.keys(parts).sort().forEach(partNum => {
            const part = parts[partNum];
            const partId = `toc-part-${partNum}`;
            html += `<div class="toc-part" id="${partId}">`;
            html += `<div class="toc-part-header" data-part="${partNum}">`;
            html += `<span class="expand-icon">▶</span>`;
            html += `Part ${partNum}: ${part.partTitle}`;
            html += `</div>`;
            html += `<div class="toc-part-children" id="${partId}-children">`;

            part.chapters.forEach(ch => {
                const label = ch.subLabel || ch.partTitle;
                html += `<a class="toc-item toc-chapter-link" data-level="2" data-chapter="${ch.id}" href="#${ch.id}">${label}</a>`;
            });

            html += `</div></div>`;
        });

        tocContainer.innerHTML = html;
    }

    // --- Load & display a chapter ---
    async function loadChapter(chapterId) {
        const chapter = CHAPTERS.find(c => c.id === chapterId);
        if (!chapter) return;

        currentChapterId = chapterId;

        // Update URL hash
        if (location.hash !== `#${chapterId}`) {
            history.pushState(null, '', `#${chapterId}`);
        }

        // Load HTML (cached)
        if (!chapterCache[chapterId]) {
            try {
                const resp = await fetch(chapter.file);
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                const html = await resp.text();
                chapterCache[chapterId] = { html };
            } catch (err) {
                contentEl.innerHTML = `<div class="skeleton-marker">Chapter file not found: ${chapter.file}<br>This chapter hasn't been written yet.</div>`;
                updateBreadcrumb(chapter);
                return;
            }
        }

        // Render
        contentEl.innerHTML = chapterCache[chapterId].html;

        // Add anchor links to headings
        addAnchorLinks();

        // Extract headings and inject into sidebar
        const headings = extractHeadings();
        chapterCache[chapterId].headings = headings;
        injectHeadingsIntoTOC(chapterId, headings);

        // Update breadcrumb
        updateBreadcrumb(chapter);

        // Highlight active chapter in sidebar
        highlightActiveTOC(chapterId);

        // Expand parent part
        expandPart(chapter.part);

        // Scroll content to top
        contentEl.scrollTo(0, 0);

        // Close mobile sidebar
        sidebar.classList.remove('open');
    }

    // --- Extract H1-H4 from rendered content ---
    function extractHeadings() {
        const headings = [];
        const elements = contentEl.querySelectorAll('h1, h2, h3, h4');
        elements.forEach(el => {
            const level = parseInt(el.tagName[1]);
            // Ensure heading has an id
            if (!el.id) {
                el.id = slugify(el.textContent);
            }
            headings.push({
                level,
                text: el.textContent.replace(/\s*#$/, ''),  // strip anchor char
                id: el.id
            });
        });
        return headings;
    }

    // --- Inject heading-level TOC entries under the chapter link ---
    function injectHeadingsIntoTOC(chapterId, headings) {
        // Remove any previously-injected heading items for this chapter
        const existing = tocContainer.querySelectorAll(`.toc-heading-item[data-owner="${chapterId}"]`);
        existing.forEach(el => el.remove());

        // Find the chapter link
        const chapterLink = tocContainer.querySelector(`.toc-chapter-link[data-chapter="${chapterId}"]`);
        if (!chapterLink) return;

        // Insert headings after the chapter link
        let insertAfter = chapterLink;
        headings.forEach(h => {
            if (h.level === 1) return;  // H1 is the chapter title, skip in TOC
            const item = document.createElement('a');
            item.className = 'toc-item toc-heading-item';
            item.dataset.level = h.level;
            item.dataset.owner = chapterId;
            item.dataset.headingId = h.id;
            item.href = `#${chapterId}::${h.id}`;
            item.textContent = h.text;
            item.addEventListener('click', (e) => {
                e.preventDefault();
                scrollToHeading(h.id);
                highlightTOCItem(item);
            });
            insertAfter.after(item);
            insertAfter = item;
        });
    }

    // --- Scroll to a specific heading within the loaded chapter ---
    function scrollToHeading(headingId) {
        const el = document.getElementById(headingId);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // --- Add anchor links (§) to headings ---
    function addAnchorLinks() {
        const headings = contentEl.querySelectorAll('h1, h2, h3, h4');
        headings.forEach(el => {
            if (!el.id) {
                el.id = slugify(el.textContent);
            }
            if (!el.querySelector('.anchor')) {
                const anchor = document.createElement('a');
                anchor.className = 'anchor';
                anchor.href = `#${currentChapterId}::${el.id}`;
                anchor.textContent = '§';
                anchor.title = 'Link to this section';
                el.appendChild(anchor);
            }
        });
    }

    // --- Breadcrumb ---
    function updateBreadcrumb(chapter) {
        breadcrumb.innerHTML = `<a href="#" class="breadcrumb-home">Techref</a>` +
            `<span class="sep">›</span>` +
            `Part ${chapter.part}: ${chapter.partTitle}` +
            (chapter.subLabel ? `<span class="sep">›</span>${chapter.subLabel}` : '');

        // Home link returns to landing page
        breadcrumb.querySelector('.breadcrumb-home').addEventListener('click', (e) => {
            e.preventDefault();
            showLandingPage();
        });
    }

    // --- Show landing page ---
    function showLandingPage() {
        currentChapterId = null;
        history.pushState(null, '', location.pathname);
        // Reload the page to show the welcome content
        // (Since it's embedded in index.html, simplest to reload)
        location.reload();
    }

    // --- Sidebar Part expand/collapse ---
    function expandPart(partNum) {
        const header = tocContainer.querySelector(`.toc-part-header[data-part="${partNum}"]`);
        if (header && !header.classList.contains('expanded')) {
            header.classList.add('expanded');
        }
    }

    function togglePart(partNum) {
        const header = tocContainer.querySelector(`.toc-part-header[data-part="${partNum}"]`);
        if (header) {
            header.classList.toggle('expanded');
        }
    }

    // --- Highlight active item in TOC ---
    function highlightActiveTOC(chapterId) {
        tocContainer.querySelectorAll('.toc-item').forEach(el => el.classList.remove('active'));
        const link = tocContainer.querySelector(`.toc-chapter-link[data-chapter="${chapterId}"]`);
        if (link) link.classList.add('active');
    }

    function highlightTOCItem(item) {
        tocContainer.querySelectorAll('.toc-heading-item').forEach(el => el.classList.remove('active'));
        item.classList.add('active');
    }

    // --- Search/filter TOC ---
    function filterTOC(query) {
        const q = query.toLowerCase().trim();
        const items = tocContainer.querySelectorAll('.toc-item');
        items.forEach(item => {
            if (!q || item.textContent.toLowerCase().includes(q)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        // Expand all parts if searching
        if (q) {
            tocContainer.querySelectorAll('.toc-part-header').forEach(h => h.classList.add('expanded'));
        }
    }

    // --- Hash navigation ---
    function handleHashNavigation() {
        const hash = location.hash.slice(1);  // remove #
        if (hash) {
            const parts = hash.split('::');
            const chapterId = parts[0];
            const headingId = parts[1];

            if (CHAPTERS.find(c => c.id === chapterId)) {
                loadChapter(chapterId).then(() => {
                    if (headingId) {
                        setTimeout(() => scrollToHeading(headingId), 100);
                    }
                });
            }
        }
    }

    // --- Bind events ---
    function bindEvents() {
        // Part header click → expand/collapse
        tocContainer.addEventListener('click', (e) => {
            const partHeader = e.target.closest('.toc-part-header');
            if (partHeader) {
                togglePart(partHeader.dataset.part);
                return;
            }

            const chapterLink = e.target.closest('.toc-chapter-link');
            if (chapterLink) {
                e.preventDefault();
                loadChapter(chapterLink.dataset.chapter);
                return;
            }
        });

        // Part card clicks on welcome page
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.part-card');
            if (card) {
                e.preventDefault();
                loadChapter(card.dataset.chapter);
            }
        });

        // Search
        searchInput.addEventListener('input', () => filterTOC(searchInput.value));

        // Mobile menu
        mobileMenuBtn.addEventListener('click', () => sidebar.classList.add('open'));
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => sidebar.classList.remove('open'));
        }

        // Back/forward
        window.addEventListener('popstate', handleHashNavigation);
    }

    // --- Utility: slugify text → id ---
    function slugify(text) {
        return text
            .toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();
    }

    // --- Boot ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
