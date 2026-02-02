# FLOSC Styling Shell Plan
## From First Touch to Content Delivery

**Created:** January 30, 2026  
**Purpose:** Map all styling containers/zones, identify gaps, and establish styling-first development path for the default FLOSC plugin.

---

## 🎯 Guiding Principle

> "Lead with styling, enforce separation of concerns."

- **Layout** = Structure, flexbox, grid, positioning (`flosc-layout.css`)
- **Theme** = Variable consumption, applying colors to DOM (`flosc-theme.css`)  
- **Presets** = Variable definitions, light/dark/brand themes (`chat-style-*.css`)

---

## 📋 User Journey Phases & Styling Zones

### PHASE 0: Pre-App Load
| Zone | Current State | Needs Work |
|------|--------------|------------|
| WordPress page wrapper | ❓ Unaddressed | Yes - ensure FLOSC takes over cleanly |
| Loading state/skeleton | ❌ None | Yes - initial load spinner or skeleton |
| Font loading | ❓ System fonts only | Consider web font strategy |

---

### PHASE 1: Visitor Landing (First Impression)

**User State:** `visitor` (no cookies, no login)

| Container | Class/ID | Current State | Status |
|-----------|----------|---------------|--------|
| **App Shell** | `.flosc-app` | ✅ Styled | Good |
| **Landing State** | `#landingState`, `.landing-title`, `.landing-subtitle` | ⚠️ Basic | Needs polish |
| **Greeting Area** | `.greeting`, `.greeting-title` | ⚠️ Basic | Needs variants |
| **Prompt Panel / Pills** | `.flosc-suggested-replies-container` | ❌ **MISSING for guests** | **HIGH PRIORITY** |
| **Suggested Prompts Grid** | `.suggested-prompts`, `.prompt-card` | ⚠️ Exists but unused? | Review usage |
| **Intro Panel** | `.intro-panel`, `.intro-panel-inline` | ⚠️ Partial | Needs theming variables |

**GAP IDENTIFIED:** No pills/autoprompts shown to visitors in PromptPanel area. This is the "cold start" problem.

---

### PHASE 2: Visitor Engagement (Pre-Login Conversation)

**User State:** `visitor` → limited interactions before login gate

| Container | Class/ID | Current State | Status |
|-----------|----------|---------------|--------|
| **Messages Container** | `.messages`, `#flosc_output_chat_responses` | ✅ Styled | Good |
| **User Message** | `.message.user` | ✅ Styled | Good - bubble tails done |
| **Assistant Message** | `.message.assistant` | ✅ Styled | Good - bubble tails done |
| **Avatar (User)** | `.message.user .message-avatar` | ✅ Styled | Good |
| **Avatar (Assistant)** | `.message.assistant .message-avatar` | ✅ Styled | Good |
| **Message Text/Bubble** | `.message-text` | ⚠️ Basic | Needs content formatting |
| **Typing Indicator** | `.typing-indicator`, `.typing-dots` | ⚠️ Exists | Needs theme variables |
| **Input Composer** | `.flosc_input_composer` | ✅ Styled | Good |
| **Voice Button** | `.flosc_input_chat_voice_button` | ⚠️ Basic | Needs states |
| **Send Button** | `.flosc_input_chat_send_button` | ✅ Styled | Good |

---

### PHASE 3: Quiz Modal (Visitor Assessment)

**User State:** `visitor` taking quiz

| Container | Class/ID | Current State | Status |
|-----------|----------|---------------|--------|
| **Modal Overlay** | `#flosc_modal_recording`, `.modal-overlay` | ⚠️ Basic | Needs theme |
| **Modal Box** | `.modal`, `.recording-modal` | ⚠️ Basic | Needs theme |
| **Modal Header** | `.modal-header` | ⚠️ Basic | |
| **Quiz Prompt Box** | `.quiz-prompt` (inline styles!) | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Quiz Tabs** | `.quiz-tab` (inline styles!) | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Text Input Panel** | `#quizPanelText` | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Audio Input Panel** | `#quizPanelAudio` | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Waveform Canvas** | `#waveformContainer` | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Recording Controls** | `.record-btn`, `.stop-btn` | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Quiz Result** | `#quizResult` | ❌ **INLINE STYLES** | **HIGH PRIORITY** |
| **Score Display** | `#quizScoreDisplay` | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Result Message** | `#quizResultMessage` | ❌ **INLINE STYLES** | **MUST EXTRACT** |
| **Continue Button** | `#quizContinueBtn` | ⚠️ Uses `.btn-primary` | Verify theming |

**GAP IDENTIFIED:** Quiz modal has extensive inline styles in PHP. This is the "poorly formatted quiz response" problem you mentioned. ALL quiz styling must be extracted to CSS classes with theme variables.

---

### PHASE 4: Login Gate (Conversion Point)

**User State:** `visitor` → prompted to create account

| Container | Class/ID | Current State | Status |
|-----------|----------|---------------|--------|
| **Login Gate Modal** | `#flosc_modal_login_gate` | ⚠️ Basic | |
| **Login Gate Body** | `.login-gate-body` | ⚠️ Exists in CSS | Review |
| **Login Gate Buttons** | `.login-gate-buttons` | ⚠️ Exists in CSS | Review |
| **CTA Buttons** | `.btn-primary`, `.btn-secondary` | ⚠️ Need theme vars | Review |

---

### PHASE 5: Guest State (Post-Login, No Purchase)

**User State:** `guest` (logged in, free tier)

| Container | Class/ID | Current State | Status |
|-----------|----------|---------------|--------|
| **Sidebar** | `.flosc-sidebar` | ✅ Styled | Good |
| **Session List** | `.flosc_app_session_list` | ⚠️ Basic | Needs theme |
| **Session Items** | `.session-item` | ⚠️ Basic | Needs hover/active |
| **User Profile Card** | `.user-profile-card` | ⚠️ Basic | |
| **Prompt Panel Pills** | ❌ **MISSING** | ❌ None | **HIGH PRIORITY** |
| **Upgrade Banner** | `.upgrade-banner` | ⚠️ Commented out | Redesign needed |

**GAP IDENTIFIED:** After login, guests see no pills/suggested actions in the PromptPanel. This is the "no pills after login for guests" problem.

---

### PHASE 6: Member State (Paid/Full Access)

**User State:** `member` (purchased)

| Container | Class/ID | Current State | Status |
|-----------|----------|---------------|--------|
| **All Guest Features** | - | Same as above | - |
| **Premium Content Display** | ❓ | Unknown | Need to map |
| **Lesson Content** | ❓ | Unknown | Need to map |

---

### PHASE 7: Content Display (AI Responses)

**Critical:** The AI response formatting within `.message-text`

| Content Type | Current State | Status |
|--------------|---------------|--------|
| **Plain Text Paragraphs** | ⚠️ Basic `<p>` styling | Needs line-height, spacing |
| **Bold/Emphasis** | ⚠️ Basic | |
| **Numbered Lists** | ❌ Unstyled? | **NEEDS WORK** |
| **Bullet Lists** | ❌ Unstyled? | **NEEDS WORK** |
| **Code Blocks** | ⚠️ Has `--flosc-code-bg` var | Verify application |
| **Inline Code** | ❌ Unknown | Check |
| **Blockquotes** | ❌ Unknown | Check |
| **Links** | ❌ Unknown | Check |
| **Tables** | ❌ Unknown | Check |
| **Markdown Rendering** | ❓ How is it processed? | Need to verify |

**GAP IDENTIFIED:** Content formatting inside message bubbles is not well-defined. Need comprehensive `.message-text` content styles.

---

## 🚨 Priority Gaps Summary

### P0 - Broken/Missing (Fix First)
1. **Quiz Result Display** - All inline styles, no theming
2. **Quiz Modal** - Extensive inline styles need extraction
3. **PromptPanel Pills for Guests** - Completely missing post-login

### P1 - Core Experience
4. **PromptPanel Pills for Visitors** - Cold start problem
5. **AI Response Content Formatting** - Lists, code, links inside messages
6. **Message Text Markdown Rendering** - Ensure proper HTML output

### P2 - Polish
7. **Loading State** - Initial app load
8. **Landing State** - First impression refinement
9. **Typing Indicator** - Theme variable integration
10. **Session List** - Sidebar polish

### P3 - Future
11. **Upgrade Banner Redesign** - Grok-style pill approach
12. **Premium Content Containers** - Map and style
13. **Animation Classes** - Reserved for premium themes

---

## 📁 Proposed CSS Architecture

```
assets/css/
├── flosc-layout.css        # Structure ONLY (exists, good shape)
├── flosc-theme.css         # Variable consumption (exists, needs expansion)
├── flosc-components.css    # NEW: Reusable component classes
│   ├── Pills / Buttons
│   ├── Modals
│   ├── Cards
│   └── Forms
├── flosc-content.css       # NEW: Message content formatting
│   ├── Typography
│   ├── Lists
│   ├── Code blocks
│   └── Tables
├── flosc-quiz.css          # NEW: All quiz-related styling
│   ├── Quiz modal
│   ├── Quiz tabs
│   ├── Quiz inputs
│   └── Quiz results
└── chat-style-*.css        # Presets (variable definitions)
    ├── chat-style-light.css
    ├── chat-style-dark.css
    ├── chat-style-claude.css
    ├── chat-style-chatgpt.css
    └── chat-style-grok.css
```

---

## 📐 New CSS Variables Needed

```css
/* Quiz */
--flosc-quiz-prompt-bg
--flosc-quiz-prompt-text
--flosc-quiz-tab-active-bg
--flosc-quiz-tab-active-text
--flosc-quiz-tab-inactive-bg
--flosc-quiz-tab-inactive-text
--flosc-quiz-input-bg
--flosc-quiz-input-border
--flosc-quiz-score-success
--flosc-quiz-score-warning
--flosc-quiz-score-error

/* Content Formatting */
--flosc-content-link-color
--flosc-content-link-hover
--flosc-content-list-marker
--flosc-content-blockquote-border
--flosc-content-blockquote-bg
--flosc-content-table-border
--flosc-content-table-header-bg

/* Prompt Panel */
--flosc-prompt-panel-bg
--flosc-prompt-panel-border
```

---

## ✅ Next Steps (Suggested Order)

1. **Audit Quiz HTML** - Extract all inline styles, document needed classes
2. **Create `flosc-quiz.css`** - New file with quiz-specific styling
3. **Create `flosc-content.css`** - Message content formatting
4. **Implement Prompt Panel** - Pills for visitors AND guests
5. **Add CSS variables** - Quiz and content to theme system
6. **Update `flosc-theme.css`** - Apply new variables
7. **Test across presets** - Verify light/dark/brand themes work

---

## 🔗 Reference Files

- Current latest: `flosc_v9_7_6/`
- Style guide: `flosc_styling_development/FLOSC_STYLE_GUIDE.md`
- Layout CSS: `flosc-layout.css` (1854 lines)
- Theme CSS: `flosc-theme.css` (642 lines)
- Main app PHP: `admin/flosc-app.php`
- Main app JS: `assets/js/flosc-app.js`

---

*This is the shell plan. No implementation yet - just mapping the territory.*
