# FLOSC Active Development Guide

---

## ⚠️ AI TOOL INSTRUCTIONS — READ BEFORE EDITING

**This file uses the Michel Date Stamp Innovation format.**

**Rules:**
1. **ADDITIVE ONLY** — Never delete existing entries. Only add new entries above previous ones.
2. **REVERSE CHRONOLOGICAL ORDER** — Newest entries go at the TOP, immediately below this header section.
3. **DATE FORMAT** — Use Michel Date Stamp Innovation:
   - Standard: `YYYY-MMm-DDd` (e.g., `2026-01m-27d`)
   - With time: `YYYY-MMm-DDd-THHh:MMm:SSs` (e.g., `2026-01m-27d-T14h:30m:00s`)
   - Full: `YYYYy-MMm-DDd-THHh:MMm:SSs:MMMms`
4. **ENTRY FORMAT:**
   ```
   ---
   ### 2026-01m-27d | [BRIEF TITLE]
   **Author:** [Human/AI Tool Name]
   **Status:** [In Progress / Completed / Blocked / Review]
   
   [Content here]
   ```

**Reference:** See `/flosc_development_worknotes/michel-date-stamp-innovation.md` for full format specification.

---

### 2026-01m-30d | Pills & Cards Styling — PLANNING PHASE

**Author:** Claude Opus 4.5 (AI) + Human Direction
**Status:** Planning (Blocked on Design Decisions)

#### CONTEXT

Working on P0-3 (PromptPanel Pills for Guests/Members) from the styling roadmap. Need to define the difference between **pills** (compact) and **cards** (larger format).

#### RESEARCH COMPLETED

Found original card styling in v3–v5 archives (`flosc_v05_09/assets/css/flosc-app.css`):
- 2-column CSS grid (`repeat(2, 1fr)`)
- No fixed height (flex to content) ✅
- Equal widths via `1fr`
- Container capped at 500px (too narrow?)
- 20px padding, 8px icon/text gap, 12px card gap

#### HASTY CHANGES MADE (NEED REVIEW)

I moved too fast and made code changes before planning:
- Added `--flosc-card-*` CSS variables (probably fine to keep)
- Changed card CSS multiple times (current state unclear)
- Added carousel overflow detection in JS (may be broken)

#### BLOCKED ON

Need human direction on:
1. Container max-width (500px? 600px? 800px?)
2. Column count per breakpoint
3. Mobile behavior (1 col? 2 col? pills?)
4. Carousel logic (on overflow? on card count? never?)

#### PLANNING DOC

Full details in: `/flosc_development_worknotes/pills_and_cards_planning.md`

#### LESSON

**Plan first, code second.** I kept changing the card CSS without a clear spec. Need to define the requirements before implementation.

---

### 2026-01m-30d | Quiz Modal Simplified — KISS Principle Applied

**Author:** Claude Opus 4.5 (AI) + Human Review
**Status:** Completed

#### WHAT WAS FIXED

The Quiz Modal was over-engineered with dual class names (`quiz-*` and `flosc-quiz-*`), complex CSS visibility classes, and ID mismatches between PHP and JavaScript.

**The JavaScript expected these IDs:**
- `quizTextPanel`, `quizAudioPanel`, `quizResultPanel`
- `quizRecordBtn`, `quizStopBtn`

**But the PHP had:**
- `quizPanelText`, `quizPanelAudio`, `quizResult`
- `recordBtn`, `stopBtn`

**Result:** The JS couldn't find the elements. Nothing worked.

#### THE FIX: KEEP IT SIMPLE

1. **Aligned PHP IDs with what JS expects** — no need to rewrite JavaScript
2. **Simplified CSS** — removed redundant `flosc-*` prefixed selectors
3. **Used simple inline `style="display: none"` for JS-toggled elements** — JS already uses `el.style.display`, so match the pattern
4. **Clean, readable class names** — `.quiz-prompt`, `.quiz-tabs`, `.quiz-panel`, `.quiz-result-panel`

#### FILES MODIFIED

| File | Changes |
|------|---------|
| [flosc-app.php](mvp_sprint/flosc_1_0_1/admin/flosc-app.php) | Fixed IDs to match JS, simplified class names, clean HTML structure |
| [flosc-layout.css](mvp_sprint/flosc_1_0_1/assets/css/flosc-layout.css) | Removed redundant dual selectors, simplified to single clean rules |

#### LESSON LEARNED

**KISS > Theory.** The "CSS-first visibility" pattern is theoretically better, but if the existing JavaScript uses `el.style.display`, match the pattern. Don't over-engineer.

---

### 2026-01m-30d | Quiz Modal CSS Extraction — PAST / PRESENT / FUTURE

**Author:** Claude Opus 4.5 (AI) + Human Review
**Status:** Superseded by above entry

#### THE STUPID MISTAKE (PAST)

The original implementation—and my first "fixed" version—used inline `style="display: none;"` scattered throughout the PHP:

```php
<!-- THE STUPID WAY (what was there, and what I initially kept) -->
<div id="quizPanelAudio" style="display: none;">
<button id="stopBtn" style="display: none;">
<div id="recordingPlayback" style="display: none;">
```

**Why this was stupid:**
1. **Inline styles have highest CSS specificity** — impossible to override with themes
2. **Scattered visibility logic** — hidden state controlled in HTML, not CSS
3. **JavaScript has to manipulate `el.style.display`** — messy, error-prone
4. **No semantic meaning** — "display: none" doesn't explain WHY it's hidden
5. **Debugging nightmare** — have to inspect each element to find what's hidden

I initially said "display: none for initially hidden elements is standard practice" — but the WAY it was implemented was the problem, not the concept.

#### THE CORRECT APPROACH (PRESENT)

**1. Utility class for generic hiding:**
```css
/* flosc-layout.css - near top of file */
.flosc-hidden {
    display: none !important;
}
```

**2. Component-specific initial states in CSS:**
```css
/* Audio panel hidden by default - Text panel shows first */
.flosc-quiz-audio-panel {
    display: none;
}

/* Stop button hidden by default until recording starts */
.flosc-quiz-stop-btn {
    display: none;
}

/* Playback hidden by default until recording complete */
.flosc-quiz-playback {
    display: none;
}

/* Error hidden by default */
.flosc-quiz-recording-error {
    display: none;
}
```

**3. State classes for showing elements:**
```css
.flosc-quiz-panel.flosc-active {
    display: flex;
}

.flosc-quiz-audio-panel.flosc-active {
    display: flex;
}

.flosc-quiz-stop-btn.flosc-visible {
    display: inline-flex;
}

.flosc-quiz-playback.flosc-visible {
    display: block;
}

.flosc-quiz-recording-error.flosc-visible {
    display: block;
}
```

**4. Clean PHP with no inline styles:**
```php
<!-- Audio panel hidden by default via CSS (.flosc-quiz-audio-panel) -->
<div class="flosc-quiz-panel flosc-quiz-audio-panel" id="quizPanelAudio">

<!-- Stop button hidden by default via CSS (.flosc-quiz-stop-btn) -->
<button class="flosc-quiz-stop-btn" id="stopBtn">

<!-- Playback hidden by default via CSS (.flosc-quiz-playback) -->
<div class="flosc-quiz-playback" id="recordingPlayback">
```

**5. JavaScript uses class toggling, not style manipulation:**
```javascript
// THE RIGHT WAY
document.getElementById('stopBtn').classList.add('flosc-visible');
document.getElementById('quizPanelAudio').classList.add('flosc-active');

// NOT THIS
document.getElementById('stopBtn').style.display = 'inline-flex';  // BAD
```

#### WHY CSS-CONTROLLED VISIBILITY IS BETTER

| Aspect | Inline `style="display:none"` | CSS Class |
|--------|-------------------------------|-----------|
| Specificity | Highest (hard to override) | Normal (themeable) |
| Location | Scattered in HTML | Centralized in CSS |
| Debugging | Hidden in style attr | Visible as class in DevTools |
| JavaScript | `el.style.display = 'x'` | `el.classList.toggle('x')` |
| Semantic | None | Class name explains state |
| Maintenance | Find/replace in PHP | Edit one CSS rule |

#### FILES MODIFIED (PRESENT)

| File | Changes |
|------|---------|
| [flosc-layout.css](mvp_sprint/flosc_1_0_1/assets/css/flosc-layout.css) | Added `.flosc-hidden` utility class, added initial hidden states for quiz components, added `.flosc-active` and `.flosc-visible` state classes |
| [flosc-app.php](mvp_sprint/flosc_1_0_1/admin/flosc-app.php) | Removed ALL inline `style="display: none;"`, using CSS classes for initial states, added HTML comments explaining which CSS class controls visibility |

#### FUTURE WORK

1. **Update JavaScript** — Ensure all quiz JS uses `classList.add/remove/toggle('flosc-visible')` instead of `el.style.display = '...'`
2. **Audit other modals** — Share Modal, Settings Modal likely have same pattern
3. **Document the pattern** — Add to FLOSC_STYLE_GUIDE.md as official best practice
4. **Test all presets** — Verify hidden/shown states work across light/dark themes

#### LESSON FOR AI TOOLS

When an AI (including me) says "this is standard practice," the human should push back on HOW it's implemented. The concept may be valid, but the implementation can still be stupid.

---

## Development Roadmap

**Last Updated:** 2026-01m-30d

### Current Sprint: Styling-First MVP Polish

The goal is a complete, themeable FLOSC plugin where every styling zone works across all user states and all chat-style presets.

---

### Phase 1: CSS Extraction — Quiz Modal (Priority: P0)

**Problem:** Quiz Modal has 24+ inline styles baked into PHP. Cannot be themed.

**Files to modify:**

| File | Lines | Action |
|------|-------|--------|
| [admin/flosc-app.php](mvp_sprint/flosc_1_0_1/admin/flosc-app.php) | 383-465 | Remove inline styles, add CSS classes |
| [assets/css/flosc-layout.css](mvp_sprint/flosc_1_0_1/assets/css/flosc-layout.css) | NEW ~1900+ | Add quiz layout rules |
| [assets/css/flosc-theme.css](mvp_sprint/flosc_1_0_1/assets/css/flosc-theme.css) | NEW ~650+ | Add quiz theme rules |

**Inline styles marked with TODO comments:**

| Location | Line | Zone |
|----------|------|------|
| flosc-app.php | 383 | Quiz Prompt (background, padding, border-radius) |
| flosc-app.php | 391 | Quiz Tabs (flex, gap, button states) |
| flosc-app.php | 402 | Quiz Text Input (input field, submit button) |
| flosc-app.php | 418 | Quiz Audio Input (waveform, timer, controls) |
| flosc-app.php | 455 | Quiz Result (score display, message, continue btn) |

**New CSS classes to create:**

```
.flosc-quiz-prompt          /* Prompt box with sequence */
.flosc-quiz-prompt-label    /* "Repeat this sequence:" */
.flosc-quiz-prompt-sequence /* The actual sequence text */
.flosc-quiz-tabs            /* Tab container */
.flosc-quiz-tab             /* Individual tab button */
.flosc-quiz-tab.active      /* Active tab state */
.flosc-quiz-panel           /* Panel container */
.flosc-quiz-input           /* Text input field */
.flosc-quiz-submit          /* Submit button */
.flosc-quiz-waveform        /* Audio waveform container */
.flosc-quiz-timer           /* Recording timer */
.flosc-quiz-record-btn      /* Record button */
.flosc-quiz-stop-btn        /* Stop button */
.flosc-quiz-playback        /* Playback container */
.flosc-quiz-error           /* Error message box */
.flosc-quiz-result          /* Result container */
.flosc-quiz-score           /* Score display (48px number) */
.flosc-quiz-message         /* Result message */
.flosc-quiz-continue        /* Continue button */
```

**New CSS variables to add (flosc-theme.css :root):**

```css
/* Quiz Zone */
--flosc-quiz-prompt-bg
--flosc-quiz-prompt-text
--flosc-quiz-prompt-sequence-color
--flosc-quiz-tab-bg
--flosc-quiz-tab-text
--flosc-quiz-tab-border
--flosc-quiz-tab-active-bg
--flosc-quiz-tab-active-text
--flosc-quiz-input-bg
--flosc-quiz-input-border
--flosc-quiz-input-text
--flosc-quiz-waveform-bg
--flosc-quiz-timer-text
--flosc-quiz-record-bg
--flosc-quiz-stop-bg
--flosc-quiz-error-bg
--flosc-quiz-error-text
--flosc-quiz-score-success    /* green */
--flosc-quiz-score-warning    /* yellow */
--flosc-quiz-score-error      /* red */
```

---

### Phase 2: PromptPanel Pills (Priority: P0)

**Problem:** No suggested prompts shown to visitors or guests.

**Files to review/modify:**

| File | Lines | Purpose |
|------|-------|---------|
| [flosc-app.php](mvp_sprint/flosc_1_0_1/admin/flosc-app.php) | ~295 | `#flosc_output_chat_suggested_replies` container |
| [flosc-app.js](mvp_sprint/flosc_1_0_1/assets/js/flosc-app.js) | TBD | IVR rendering logic |
| [flosc-layout.css](mvp_sprint/flosc_1_0_1/assets/css/flosc-layout.css) | 789-826 | `.flosc-pill`, `.prompt-panel` layout |
| [flosc-theme.css](mvp_sprint/flosc_1_0_1/assets/css/flosc-theme.css) | 93-99 | `--flosc-pill-*` variables |
| [flosc-theme.css](mvp_sprint/flosc_1_0_1/assets/css/flosc-theme.css) | 517+ | `.flosc-pill` theming |

**Chat style presets already define pill variables:**

| Preset | File | Lines |
|--------|------|-------|
| Light | chat-style-light.css | 33-38 |
| Dark | chat-style-dark.css | 33-38 |
| Claude | chat-style-claude.css | 33-38 |
| ChatGPT | chat-style-chatgpt.css | 33-38 |
| Grok | chat-style-grok.css | 33-38 |

**Tasks:**
1. Verify IVR messages load for visitor state
2. Verify pills render in `#flosc_output_chat_suggested_replies`
3. Test pill styling across all 5 presets
4. Add cold-start IVR message if missing

---

### Phase 3: Message Content Formatting (Priority: P1)

**Problem:** AI responses may contain lists, code, links — need consistent styling.

**Current state (already exists, needs review):**

| File | Lines | Selector | Status |
|------|-------|----------|--------|
| flosc-layout.css | 653-660 | `.message-text` base | exists |
| flosc-layout.css | 668-677 | `.message-text p` | exists |
| flosc-layout.css | 679-688 | `.message-text ul, ol, li` | exists |
| flosc-layout.css | 691-710 | `.message-text code, pre` | exists |
| flosc-theme.css | 381-398 | `.message-text` colors | exists |
| flosc-theme.css | 400-420 | `.message-text code, pre` | exists |

**Needs verification/addition:**

| Content Type | Layout Line | Theme Line | Status |
|--------------|-------------|------------|--------|
| Paragraphs | 668 | 389 | review spacing |
| Bullet lists | 679 | TBD | needs theme colors |
| Numbered lists | 680 | TBD | needs theme colors |
| Inline code | 691 | 400 | review bg color |
| Code blocks | 699 | 410 | review bg color |
| Links | TBD | TBD | needs styling |
| Blockquotes | TBD | TBD | needs styling |
| Tables | TBD | TBD | needs styling |

---

### Phase 4: Polish (Priority: P2)

| Task | File | Lines | Notes |
|------|------|-------|-------|
| Loading skeleton | flosc-app.php | NEW | Before app renders |
| Landing state | flosc-layout.css | ~580 | `.landing-state` |
| Typing indicator | flosc-layout.css | ~560 | `.typing-indicator` |
| Session list | flosc-theme.css | ~170 | `.session-item` hover/active |

---

### Admin CSS (Priority: P3 — Lower)

**Not user-facing, but should be cleaned up eventually.**

| File | Line | Zone |
|------|------|------|
| lessons.php | 68 | Info box |
| lessons.php | 84 | HR separator |
| ai-configuration.php | 58 | Warning box |
| ai-configuration.php | 69 | Card + test UI |
| ai-configuration.php | 130 | Section headings |
| ai-configuration.php | 153, 204, 273 | Badges |

---

### Future (Post-MVP)

- Upgrade banner redesign (Grok-style pill)
- Premium content containers
- Animation classes for premium themes
- Mobile-specific optimizations

---

## Development Log

---
### 2026-01m-30d | Styling Shell Plan + AI Anti-Pattern Identified
**Author:** GitHub Copilot (Claude Opus 4.5) + Dainis Michel
**Status:** In Progress

**Created:** `/flosc_styling_development/FLOSC_STYLING_SHELL_PLAN.md`

**Terminology Established:**
- **Styling Zones** — Discrete UI containers/areas that need styling (e.g., PromptPanel, Quiz Modal, Message Bubbles)
- **User State** — visitor / guest / member (matches existing `data-user-state` attribute in codebase)

**Critical Issue Discovered: AI-Generated Inline Styles**

During styling audit, found extensive inline styles in `/admin/flosc-app.php` (lines ~275-350), particularly in the Quiz Modal. This violates the established CSS architecture:

```
flosc-layout.css  → Structure only (flexbox, positioning)
flosc-theme.css   → Variable consumption
chat-style-*.css  → Variable definitions (presets)
```

**Example of the problem (Quiz Modal in PHP):**
```php
<div class="quiz-prompt" style="background: #f0f9ff; border-radius: 12px; padding: 20px;">
<button style="flex: 1; padding: 10px; border: 2px solid var(--flosc-primary);">
<div id="quizResult" style="display: none; text-align: center; padding: 20px;">
```

**⚠️ AI ANTI-PATTERN IDENTIFIED:**

AI tools (including this one) tend to:
1. Add inline styles for "quick fixes" instead of creating proper CSS classes
2. Create new files rather than working precisely within existing structure
3. Skip the harder work of understanding and extending existing patterns

**This is a coding guideline violation.** Inline styles:
- Cannot be themed (no CSS variable support)
- Cannot be overridden by presets
- Break separation of concerns
- Make maintenance harder

**Roadmap: Styling Zones to Address**

| Priority | Zone | Issue |
|----------|------|-------|
| P0 | Quiz Modal | All inline styles, no theming |
| P0 | Quiz Result Display | Inline styles, hardcoded colors |
| P0 | PromptPanel (Guest) | Missing — no pills shown after login |
| P1 | PromptPanel (Visitor) | Cold start — no prompts for new visitors |
| P1 | Message Content | Lists, code blocks, links unstyled inside bubbles |
| P2 | Loading State | No initial app load indicator |
| P2 | Landing State | Basic, needs polish |

**Proposed New CSS Files:**
- `flosc-quiz.css` — Extract all quiz styling from PHP
- `flosc-content.css` — AI response content formatting (lists, code, tables)
- `flosc-components.css` — Reusable pills, buttons, modals, cards

**Next Steps:**
1. Audit Quiz Modal HTML — document all inline styles
2. Create CSS classes with theme variables
3. Replace inline styles with class references
4. Test across all chat-style presets

**Reference:** See `/flosc_styling_development/FLOSC_STYLING_SHELL_PLAN.md` for full zone mapping.

---

## Workspace Index & Table of Contents

### Root: `/Users/dainismichel/2026/flosc/`

```
flosc/
├── .git/                              # Git repository
├── .gitignore
├── development_workflow.md
├── development_workflow_starting_2026_01m_25d.md
├── icon_visibility_diagnosis.md
│
├── mvp_sprint/                        # 🎯 ACTIVE DEVELOPMENT
│   ├── flosc_1_0_0/                   # Current MVP plugin (v1.0.0)
│   └── flosc_1_0_0.zip                # Deployable package
│
├── flosc_development_worknotes/       # 📝 Development documentation
│   ├── flosc_active_devguide.md       # THIS FILE
│   ├── michel-date-stamp-innovation.md
│   ├── development_workflow.md
│   ├── development_workflow_starting_2026_01m_25d.md
│   └── icon_visibility_diagnosis.md
│
├── flosc_styling_development/         # 🎨 CSS/UI experiments
│   ├── FLOSC_STYLE_GUIDE.md
│   └── flosc-styling-notes.txt
│
├── login_development/                 # 🔐 Login system experiments
│   ├── README-v9.3.3.md
│   ├── korboc-survey-plugin-v9.3.3.zip
│   └── korboc-v9.3.3/
│
├── quiz_development/                  # 📊 Quiz system experiments
│   ├── Wp-Pro-Quiz/
│   └── quizbit/
│
└── flosc_development_archives/        # 📦 Historical versions (NOT for GitHub)
    ├── flosc_v01_01 through flosc_v9_7_6
    ├── Documentation (changelogs, reviews, tasklists)
    └── ~100+ version iterations preserved
```

### FLOSC 1.0.0 Plugin Structure: `/mvp_sprint/flosc_1_0_0/`

```
flosc_1_0_0/
├── flosc.php                          # Main plugin file (v1.0.0)
├── readme.md                          # Plugin documentation
│
├── admin/                             # WordPress admin pages
│   ├── flosc-app.php                  # Main chat application template
│   ├── settings.php                   # General settings
│   ├── product.php                    # Product configuration
│   ├── offers.php                     # Offer management
│   ├── payments.php                   # Payment settings
│   ├── quiz.php                       # Quiz configuration
│   ├── lessons.php                    # Lesson management
│   ├── chat-styling.php               # Chat UI styling
│   ├── ai-configuration.php           # AI provider settings
│   ├── ai-knowledge.php               # RAG knowledge base
│   ├── ivr-messages.php               # IVR message management
│   ├── ivr-settings.php               # IVR configuration
│   ├── ivr-message-form.php           # IVR message editor
│   ├── email.php                      # Email templates
│   └── create-sample-data.php         # Sample data generator
│
├── includes/                          # Core PHP classes
│   ├── class-ai-provider-factory.php  # AI provider abstraction
│   ├── class-stt-provider-factory.php # Speech-to-text providers
│   ├── class-session-manager.php      # User session handling
│   ├── class-ivr-parser.php           # IVR markdown parser
│   ├── class-condition-evaluator.php  # Condition logic engine
│   ├── class-quiz-manager.php         # Quiz orchestration
│   ├── class-quiz-type-factory.php    # Quiz type abstraction
│   ├── class-lesson-manager.php       # Lesson delivery
│   ├── class-free-lesson-manager.php  # Free lesson logic
│   ├── class-rag-manager.php          # RAG system
│   ├── class-rag-chat-handler.php     # RAG chat integration
│   ├── class-content-filter.php       # Content access control
│   ├── class-user-access-manager.php  # User permissions
│   ├── class-access-validator.php     # Access validation
│   ├── class-member-access.php        # Member features
│   ├── class-pronunciation-analyzer.php # Audio analysis
│   ├── class-bridge-data-manager.php  # Data bridging
│   │
│   ├── quiz-types/                    # Quiz type implementations
│   │   ├── abstract-quiz-type.php
│   │   ├── class-flosc-sample-text-based-quiz.php
│   │   ├── class-flosc-sample-audio-quiz.php
│   │   ├── class-multiplechoice-quiz.php
│   │   ├── class-truefalse-quiz.php
│   │   └── class-wordmatching-quiz.php
│   │
│   └── sale/                          # Payment & sales system
│       ├── class-sale-manager.php
│       ├── class-offer-manager.php
│       ├── class-access-manager.php
│       ├── class-payment-provider.php
│       ├── class-usage-tracker.php
│       └── providers/
│           ├── class-stripe-provider.php
│           ├── class-clickbank-provider.php
│           ├── class-token-provider.php
│           └── class-affiliate-provider.php
│
├── assets/
│   ├── css/
│   │   ├── flosc-layout.css           # Structure/positioning (no colors)
│   │   ├── flosc-theme.css            # Theme variable consumption
│   │   ├── chat-style-light.css       # Light theme variables
│   │   ├── chat-style-dark.css        # Dark theme variables
│   │   ├── chat-style-claude.css      # Claude-style theme
│   │   ├── chat-style-chatgpt.css     # ChatGPT-style theme
│   │   ├── chat-style-grok.css        # Grok-style theme
│   │   └── ivr-admin.css              # IVR admin styles
│   │
│   └── js/
│       ├── flosc-app.js               # Main application controller
│       └── ivr-admin.js               # IVR admin scripts
│
├── ai_configuration_files/            # AI knowledge & IVR content
│   ├── ivr.md                         # IVR message definitions
│   ├── lesson_catalog.md              # Lesson index
│   └── lesson_01.md - lesson_10.md    # Individual lessons
│
└── sample-data/
    ├── flosc-sample-lessons.xml       # WordPress import file
    └── sample-data-overview.md        # Sample data docs
```

---

## Development Log

---
### 2026-01m-27d | FLOSC Plugin Isolation Fixes (v1.0.0)
**Author:** GitHub Copilot (AI)
**Status:** Completed

**Issue:** FLOSC plugin was breaking WordPress site — posts not showing when logged in.

**Root Causes Identified:**
1. `class-content-filter.php` was filtering ALL WordPress content via `the_content` hook
2. `login_redirect` hook (priority 999) was hijacking ALL logins and redirecting to FLOSC app
3. `woocommerce_login_redirect` was always redirecting to FLOSC app

**Fixes Applied:**

**1. Content Filter ([class-content-filter.php](mvp_sprint/flosc_1_0_0/includes/class-content-filter.php))**
- Added SAFEGUARD 1: Skip admin area (`is_admin()`)
- Added SAFEGUARD 2: Skip non-FLOSC REST requests
- Added SAFEGUARD 3: Only process content with FLOSC markers (`<!--flosc_read_more-->` or `### ACCESS LEVEL:`)
- Result: Regular WordPress posts are now returned unchanged

**2. Login Redirect ([flosc.php](mvp_sprint/flosc_1_0_0/flosc.php) lines 551-596)**
- `handle_login_redirect`: Now only redirects to FLOSC app when:
  - User explicitly requested FLOSC app URL
  - User has pre-login quiz score cookie
  - Referrer was the FLOSC app
- Otherwise respects WordPress default redirect behavior

**3. WooCommerce Login Redirect ([flosc.php](mvp_sprint/flosc_1_0_0/flosc.php))**
- `handle_woocommerce_login_redirect`: Only redirects to FLOSC app if referrer was FLOSC
- Otherwise lets WooCommerce handle redirect normally

**Verification Needed:**
- [ ] Test login from regular WordPress pages → should stay on page
- [ ] Test login from FLOSC app → should redirect to FLOSC app
- [ ] Test regular post display when logged in → should show normally
- [ ] Test FLOSC-tagged content filtering → should still work

---
### 2026-01m-27d | MVP Sprint To-Do List Defined
**Author:** Dainis Michel
**Status:** In Progress

**Highest Priority:** Complete the full FLOSC funnel flow end-to-end, including sandboxed sales and real sales testing.

**MVP Sprint Tasks:**

**1. User Authentication Flow**
- [ ] Seamless registration and login
- [ ] Return user to exact position in FLOSC process after auth
- [ ] No disruption to user journey

**2. Styling**
- [ ] Verify all UI styling is acceptable
- [ ] Test across different themes/presets

**3. Quiz System**
- [ ] Currently have two sample quizzes
- [ ] Enhance quiz capabilities
- [ ] Evaluate/integrate existing WordPress quiz plugins

**4. FLOSC Funnel Flow**
- [ ] User completes quiz
- [ ] User encouraged to log in to receive score
- [ ] Account creation triggers offer display
- [ ] User sees FLOSC admin's offers

**5. Purchase & Access Flow (NOT YET FULLY DEVELOPED/TESTED)**
- [ ] User accepts offer
- [ ] Payment processing (Stripe sandbox first)
- [ ] User receives WordPress member level on purchase
- [ ] User gains access to gated content

**6. Sales Testing**
- [ ] Sandboxed/test mode sales working
- [ ] Real sales with actual credit cards
- [ ] Test product at low price point (few dollars)

**7. First Content Activation**
- [ ] Standard American English Pronunciation Course
- [ ] This is the first real content to go live with FLOSC

---
### 2026-01m-27d | FLOSC 1.0.0 MVP Initialized
**Author:** GitHub Copilot (Claude)
**Status:** Completed

**Summary:**
FLOSC v9.7.6 promoted to v1.0.0 as the official MVP base.

**Actions Completed:**
- Copied flosc_v9_7_6 → flosc_1_0_0 in `/mvp_sprint/`
- Updated version references to 1.0.0:
  - `flosc.php` — plugin header + FLOSC_VERSION constant
  - `readme.md` — title + version
  - `assets/js/flosc-app.js` — FLOSC_JS_VERSION constant
- Created `flosc_1_0_0.zip` for deployment testing
- Archived flosc_v9_7_6 (directory + zip) to `/flosc_development_archives/`

**Current State:**
- ✅ Plugin activates in WordPress
- ✅ Chat UI renders with visible icons
- ✅ IVR message system functional
- ✅ Quiz engine operational
- ✅ Session management working
- ✅ CSS architecture clean (layout + theme + presets)

**Known Technical Debt:**
- readme.md contains outdated v9.5.7 changelog
- Some JS console.log messages reference old versions
- Admin settings pages need verification
- End-to-end user flow needs testing

---
### 2026-01m-27d | Pre-MVP Decision: v9.7.6 Selected as 1.0.0 Base
**Author:** Dainis Michel + GitHub Copilot
**Status:** Completed

**Decision Rationale:**
After ~100 iterations (v01.01 through v9.7.6), version 9.7.6 selected for 1.0.0 because:
- Icons visible and functional in all buttons/overlays
- CSS architecture clean and modular
- No known critical bugs
- All core FLOSC features operational

**Archive Strategy:**
- All previous versions preserved in `/flosc_development_archives/`
- Archives are local reference only — NOT pushed to GitHub
- Only `/mvp_sprint/` and forward tracked in version control

---

## PAST: Development History Summary

**Origin:** FLOSC = Freeline-Login-Offer-Sale-Content

**Version Evolution:**
- **v01.x - v02.x** — Initial plugin scaffolding, basic WordPress integration
- **v03.x - v04.x** — Quiz system development, AI provider abstraction
- **v05.x - v06.x** — IVR message system, session management, RAG integration
- **v07.x - v08.x** — Payment providers (Stripe, ClickBank, tokens), access control
- **v09.x** — CSS architecture overhaul, chat styling presets, icon visibility fixes
- **v9.7.6 → v1.0.0** — MVP stabilization

**Key Architectural Decisions:**
- Modular CSS: `flosc-layout.css` (structure) + `flosc-theme.css` (variables) + `chat-style-*.css` (presets)
- IVR messages stored in markdown files, parsed at runtime
- Quiz types as abstract factory pattern
- Payment providers as pluggable abstraction

---

## PRESENT: Current MVP State (v1.0.0)

**What Works:**
- WordPress plugin activation/deactivation
- Chat UI with sidebar, composer, message display
- IVR-driven conversation flow
- Quiz system (text-based, audio, multiple choice, true/false, word matching)
- Session management (visitor → guest → member states)
- Payment provider framework
- RAG knowledge base integration
- 5 chat style presets (light, dark, Claude, ChatGPT, Grok)

**What Needs Testing:**
- Complete user journey: visitor → quiz → login → purchase → content access
- Payment processing (Stripe live mode)
- Email notifications
- Mobile responsiveness
- Cross-browser compatibility

---

## FUTURE: MVP Sprint Priorities

**Immediate (This Sprint):**
1. [ ] Verify all admin settings pages load without errors
2. [ ] Test complete FLOSC funnel flow end-to-end
3. [ ] Clean up readme.md for 1.0.0 release
4. [ ] Set up GitHub repo for `/mvp_sprint/` only
5. [ ] Document minimum viable deployment instructions

**Next Sprint:**
- [ ] Production payment testing
- [ ] Performance optimization
- [ ] Security audit
- [ ] User documentation

**Future Roadmap:**
- Premium theme marketplace (Discord Nitro model per styling notes)
- Additional quiz types
- Multi-language support
- Analytics dashboard

---
