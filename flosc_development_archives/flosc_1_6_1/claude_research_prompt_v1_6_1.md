# FLOSC v1.6.1 — Complete Styling & Companion Research Prompt

## FOR: Claude Code Research Preview (with GREP MCP for GitHub access)

## REPO: https://github.com/dainiswmichel/flosc.git (branch: main)

## WORKING DIRECTORY: `mvp_sprint/flosc_1_6_1/`

---

## THE PHILOSOPHY: What Drives FLOSC

FLOSC is the bridge from AI to AGI. The chasm between AI and AGI is not compute — it's knowledge locked in human heads by bitterness. FLOSC solves the root problem — bitterness — with joy.

**The driver through every phase of F → L → O → S → C is ENCOURAGEMENT.** Not pressure, not urgency tricks, not scarcity manipulation. Encouragement. Joy. Warmth. Recognition.

- **Experts share knowledge and get compensated fairly** — reducing bitterness
- **Try-before-you-buy ensures the right humans self-select** and pay willingly
- **AI's role is to facilitate generous, warm, rewarding human exchanges**
- **Every FLOSC installation is a lane on the bridge**

When a visitor logs in and becomes a guest, they are **warmly and joyously greeted — treated like a REAL GUEST.** Think about what it means to be a guest in someone's home. You're welcomed. You're valued. You're offered something before you're asked for anything.

**This emotional architecture must be reflected in every IVR message, every transition, every UI element.** The code implements the cog that replaces human bitterness with joy through financial compensation and recognition (which drives gratitude).

---

## STATUS: Where We Are

**v1.6.1 includes everything from v1.5.5 + companion mode + a major styling upgrade.** Here's what's been built:

### ✅ Complete FLOSC Pipeline (F → L → O → S → C) — Working since v1.5.5
- Freeline: quiz, score stored but NOT shown until login
- Login: Facebook + Google SSO with cross-domain redirect
- Free lesson delivery: admin-configured count, randomly selected from missed topics, rendered inline in chat
- Offers: 8 default offers, 7 display formats, per-flow storage, OTO with timer
- Sale: Stripe integration + sandbox purchase
- Content: member access with lesson browser

### ✅ Companion Mode — NEW in v1.6.0
- Floating widget on WordPress pages (non-app routes)
- Chat + Lessons TOC tabs with context-aware suggestions
- Completely independent from main chatbot (separate JS/CSS/config)
- Admin tab with content display mode, position, greeting, accent color settings
- Per-flow configuration via `companion` override group

### ✅ Styling Upgrade — NEW in v1.6.0/v1.6.1
- Admin CSS rewritten: 30+ design tokens with `--flosc-admin-*` namespace
- Quiz results now use premium gradient card CSS with score tiers (high/medium/low)
- Quiz answer selection and offer dismiss animations moved from inline styles to CSS classes
- Settings.php header/footer/IVR selector/flow blocks converted from inline styles to CSS classes
- `STYLING_UPGRADE_v1.6.0.md` documents all changes

---

## MISSION: Ultra-Professional Styling — Admin-Configurable Everywhere

The v1.6.1 mission is to make FLOSC's styling **ultra-professional** AND **admin-configurable**. FLOSC admins must be able to style their chatbots through the Chat Styling admin tab at:

```
/wp-admin/admin.php?page=flosc-settings&tab=style
```

This means: **NO HARDCODED COLORS in components.** Every visual element must be driven by CSS custom properties that are:
1. Defined in `:root` in `flosc-theme.css` (default values)
2. Overridden by preset files (`chat-style-*.css`) when a preset is selected
3. Dynamically overridden by admin settings (accent color, scale, font, custom CSS)
4. Resolved per-flow via `FLOSC_Flow_Manager::get_setting()`

The styling system has 3 layers — this separation MUST be respected:

| Layer | File | Purpose | Contains |
|-------|------|---------|----------|
| **Structure** | `flosc-layout.css` (2,166 lines) | Display, flex, grid, padding, position | Zero colors, zero shadows |
| **Theme** | `flosc-theme.css` (1,715 lines) | All visual polish: colors, shadows, gradients, animations | 97 CSS custom properties in `:root` |
| **Preset** | `chat-style-*.css` (5 files, ~54 lines each) | Admin-selected theme override | Overrides `:root` variables |
| **Admin CSS** | `flosc-admin.css` (1,131 lines) | WordPress admin pages only | `--flosc-admin-*` namespace (separate) |
| **Companion** | `flosc-companion.css` (733 lines) | Companion widget only | Self-contained, BEM naming |

---

## CRITICAL ISSUES TO FIX

### Issue 1: Preset Files Don't Cover All Variable Groups

**The Problem:** The 5 preset files (`chat-style-light.css`, `chat-style-dark.css`, `chat-style-claude.css`, `chat-style-chatgpt.css`, `chat-style-grok.css`) only override **41 out of 97** CSS custom properties. They cover:

✅ Global (bg, text, accent, border)
✅ User Message (bg, text, radius, avatar)
✅ Assistant Message (bg, text, border, radius, avatar)
✅ Input (bg, field, placeholder, buttons)
✅ Sidebar (bg, text, hover, active, border)
✅ Content (link, code, blockquote)
✅ Scrollbar

❌ **NOT covered by presets:**
- **Pills** (6 vars): `--flosc-pill-bg`, `--flosc-pill-text`, `--flosc-pill-border`, `--flosc-pill-hover-bg`, `--flosc-pill-hover-text`, `--flosc-pill-hover-border`
- **Cards** (8 vars): `--flosc-card-bg`, `--flosc-card-text`, `--flosc-card-border`, `--flosc-card-hover-bg`, etc.
- **Panels** (10 vars): `--flosc-panel-bg`, `--flosc-panel-border`, `--flosc-panel-shadow`, `--flosc-panel-header-text`, etc.
- **Quiz Modal** (16 vars): prompt, tabs, inputs, recording, waveform, success/warning/error
- **Quiz Results** (5 vars): correct-bg/text, incorrect-bg/text, transcript-text
- **Quiz Option** (2 vars): accent-bg, accent-ring

**Result:** On the dark preset, pill colors are still light-theme defaults. Quiz modal on the Claude preset still has blue accent instead of Claude's orange. Cards on the Grok preset don't match Grok's styling.

**Fix Required:** Add the missing variable groups to ALL 5 preset files with theme-appropriate values. Each preset needs to define values for ALL 97 variables. This is the #1 priority — without it, the Chat Styling admin tab is incomplete.

### Issue 2: `enqueue_chat_style()` Per-Flow Disconnect

**The Problem:** `enqueue_chat_style()` in `flosc.php` (L2407–L2530) reads chat styling settings from `get_option('flosc_chat_style_preset')` etc. — GLOBAL options. But the admin UI in `chat-styling.php` saves these as per-flow settings via the flow manager.

**The Fix:** Replace all `get_option('flosc_chat_style_...')` calls in `enqueue_chat_style()` with `FLOSC_Flow_Manager::get_setting('flosc_chat_style_preset', 'style', 'preset')` pattern, using the `style` override group. The flow manager handles global fallback automatically when `style.use_global` is `true`.

**Current code (BROKEN):**
```php
$preset = get_option('flosc_chat_style_preset', 'auto');
$bubble = get_option('flosc_chat_style_bubble', 'subtle-notch');
$accent = get_option('flosc_chat_style_accent', '');
$font   = get_option('flosc_chat_style_font', '');
$scale  = get_option('flosc_chat_style_scale', '');
$custom = get_option('flosc_chat_style_custom_css', '');
```

**Should be:**
```php
$fm = FLOSC_Flow_Manager::get_instance();
$preset = $fm->get_setting('flosc_chat_style_preset', 'style', 'preset') ?: 'auto';
$bubble = $fm->get_setting('flosc_chat_style_bubble', 'style', 'bubble') ?: 'subtle-notch';
$accent = $fm->get_setting('flosc_chat_style_accent', 'style', 'accent') ?: '';
$font   = $fm->get_setting('flosc_chat_style_font', 'style', 'font') ?: '';
$scale  = $fm->get_setting('flosc_chat_style_scale', 'style', 'scale') ?: '';
$custom = $fm->get_setting('flosc_chat_style_custom_css', 'style', 'custom_css') ?: '';
```

Verify `chat-styling.php` saves match these keys. Check that `class-flow-manager.php` has `'style'` in the override group defaults.

### Issue 3: ~113 Unprefixed CSS Selectors in flosc-layout.css

**The Problem:** `flosc-layout.css` contains selectors like `.message`, `.modal`, `.greeting`, `.product-name`, `.chat-header`, `.message-text` that could collide with WordPress theme styles.

**Mitigation:** FLOSC dequeues ALL theme styles on its app route (`is_flosc_request()`), so collisions only happen if:
1. A theme uses `!important` on these class names
2. The companion widget runs on normal WP pages (it uses its own CSS so this is fine)

**Fix (OPTIONAL but RECOMMENDED):** Prefix the most collision-prone selectors with `.flosc-app` ancestor scope. For example:
- `.message` → `.flosc-app .message`
- `.modal` → `.flosc-app .modal`
- `.greeting` → `.flosc-app .greeting`

This is a large refactor. The alternative is documenting the risk and accepting it since styles are dequeued. Decision: Document which selectors are risky and scope the top 20 most collision-prone ones.

### Issue 4: Remaining Inline Styles

**In `admin/settings.php` (~25 remaining):** Deeper form fields, toggle wrappers, and minor elements still use inline styles. Convert to CSS classes in `flosc-admin.css`.

**In other admin tabs (~75 total):**
- `admin/ai-configuration.php` — textarea heights, section spacing
- `admin/ai-knowledge.php` — card layouts
- `admin/offers.php` — offer form fields, format selector
- `admin/lessons.php` — lesson config fields
- `admin/chat-styling.php` — preview box, preset cards
- `admin/quiz.php` — quiz type selector

**In `assets/js/flosc-app.js` (~5–8 remaining):**
- Payment error styling (L~1982)
- Sandbox text (L~3444, L~3460)
- Success detail / celebration (L~3538, L~3541)

**In `includes/class-content-protection.php`:**
- CTA box (L~399–404) has inline gradient/colors/padding

Move ALL to CSS classes. Add classes to the appropriate CSS file (`flosc-admin.css` for admin, `flosc-theme.css` for frontend).

### Issue 5: Accent Color Cascade Is Incomplete

**The Problem:** When admin picks an accent color in Chat Styling, `enqueue_chat_style()` overrides these variables:
- `--flosc-accent`
- `--flosc-accent-hover` (darkened by 15%)
- `--flosc-user-message-bg`
- `--flosc-user-avatar-bg`
- `--flosc-send-btn-bg`

**Missing from the accent cascade:**
- `--flosc-accent-subtle` — should be `rgba(accent, 0.06)`
- `--flosc-pill-hover-bg` — should be `rgba(accent, 0.06)`
- `--flosc-pill-hover-text` — should be accent
- `--flosc-pill-hover-border` — should be `rgba(accent, 0.15)`
- `--flosc-card-hover-text` — should be accent
- `--flosc-card-hover-border` — should be `rgba(accent, 0.15)`
- `--flosc-quiz-tab-active-bg` — should be accent
- `--flosc-content-link` — should be accent
- `--flosc-content-link-hover` — should be accent-hover
- `--flosc-accent-bg` — should be `rgba(accent, 0.08)`
- `--flosc-accent-ring` — should be `rgba(accent, 0.15)`

When a FLOSC admin picks a red accent, pills/cards/quiz tabs should ALSO turn red — not stay blue.

---

## Architecture Quick Reference

### Per-Flow Storage (CRITICAL)
- **Single option:** `flosc_flows` (wp_option) managed by `FLOSC_Flow_Manager::get_setting($option_name, $override_group, $key)`
- **SSO exception:** `flosc_flow_<id>` is a separate wp_option
- **Flow key construction:** `$flow_key = 'flosc_flow_' . sanitize_key(pathinfo($ivr_filename, PATHINFO_FILENAME))`
- **REST API:** `get_ivr_messages()` reads directly from parsed `.md` files on every request — DB is admin-side only

### Override Groups in class-flow-manager.php
```php
'overrides' => [
    'style'        => ['use_global' => true],  // Chat Styling tab
    'ai'           => ['use_global' => true],  // AI Configuration tab
    'email'        => ['use_global' => true],  // Email tab
    'ai_knowledge' => ['use_global' => true],  // AI Knowledge tab
    'offers'       => ['use_global' => true],  // Offers tab
    'payments'     => ['use_global' => true],  // Payments tab
    'lessons'      => ['use_global' => true],  // Lessons tab
    'companion'    => ['use_global' => true],  // Companion tab (v1.6.0)
]
```

### Version Locations (7 total, 4 files)
1. `flosc.php` line 6: `Version: 1.6.1`
2. `flosc.php` line 17: `define('FLOSC_VERSION', '1.6.1')`
3. `admin/flosc-app.php` line 6: `Version: 1.6.1`
4. `readme.md` line 1: `# FLOSC v1.6.1`
5. `readme.md` line 5: `**Version:** 1.6.1`
6. `assets/js/flosc-app.js` line 9: `Version: 1.6.1`
7. (if bumping) Update all 7 simultaneously

### CSS Custom Properties Architecture (97 total in :root)

| Group | Count | Preset Covered? | Admin Accent Cascade? |
|-------|-------|-----------------|----------------------|
| Global | 7 | ✅ Yes | ✅ Partial (accent, accent-hover) |
| User Message | 5 | ✅ Yes | ✅ Yes (bg, avatar-bg) |
| Assistant Message | 6 | ✅ Yes | ❌ No |
| Input | 7 | ✅ Yes | ✅ Yes (send-btn-bg) |
| Sidebar | 6 | ✅ Yes | ❌ No |
| Pills | 6 | ❌ No | ❌ No |
| Cards | 8 | ❌ No | ❌ No |
| Panels | 10 | ❌ No | ❌ No |
| Content | 10 | ✅ Yes | ❌ No |
| Code Blocks | 2 | ✅ Yes | ❌ No |
| Quiz Modal | 16 | ❌ No | ❌ No |
| Quiz Results | 5 | ❌ No | ❌ No |
| Quiz Option | 2 | ❌ No | ❌ No |
| Scrollbar | 2 | ✅ Yes | ❌ No |

**The goal: ALL 97 variables defined in presets, accent cascade covers ALL accent-derived variables.**

---

## Key Files

| File | Lines | Purpose |
|------|-------|---------|
| `flosc.php` | 6,076 | Main plugin orchestrator, `enqueue_chat_style()` at L2407 |
| `assets/js/flosc-app.js` | 4,948 | Frontend chatbot engine |
| `assets/css/flosc-theme.css` | 1,715 | Theme visuals + 97 CSS custom properties in `:root` |
| `assets/css/flosc-layout.css` | 2,166 | Structural CSS (no colors) |
| `assets/css/flosc-admin.css` | 1,131 | Admin panel design system |
| `assets/css/flosc-companion.css` | 733 | Companion widget (self-contained, BEM) |
| `assets/js/flosc-companion.js` | 602 | Companion widget frontend |
| `includes/class-companion-widget.php` | 458 | Companion PHP singleton |
| `admin/companion.php` | 193 | Companion admin tab |
| `admin/settings.php` | 861 | Admin settings + flow dropdown |
| `admin/chat-styling.php` | 262 | Chat Styling admin tab — preset/bubble/accent/font/scale/custom CSS |
| `admin/flosc-app.php` | 695 | App template, `FLOSC_CONFIG` generation |
| `includes/class-flow-manager.php` | 628 | Per-flow override resolution |
| `includes/sale/class-offer-manager.php` | 651 | Offer CRUD + 8 default offers |
| `assets/css/chat-style-light.css` | 63 | Light preset (41 of 97 vars) |
| `assets/css/chat-style-dark.css` | 54 | Dark preset (41 of 97 vars) |
| `assets/css/chat-style-claude.css` | 54 | Claude preset (41 of 97 vars) |
| `assets/css/chat-style-chatgpt.css` | 54 | ChatGPT preset (41 of 97 vars) |
| `assets/css/chat-style-grok.css` | 54 | Grok preset (41 of 97 vars) |

---

## What's Already Done in v1.5.5 + v1.6.0 (DO NOT REDO)

### ✅ IVR Per-Flow Storage Fix (v1.5.4)
All `flosc_import_ivr_to_database`, `flosc_export_ivr_backup`, `flosc_auto_export_ivr_to_file` accept `$flow_key`, reference `$GLOBALS['flosc_current_ivr']`.

### ✅ Backup System (v1.5.4)
`bckp_NN_basename.md` naming, auto-increment, admin toggle in flow dropdown.

### ✅ Offer System Fixes (v1.5.5)
- Save/read mismatch fixed: `get_all_offers()` reads per-flow first
- `oto_main` default offer: $25 was $100, featured format, 1-hour timer
- `checkAutoMessages()` processes `type === 'offer'` messages
- Frontend `$GLOBALS['flosc_settings_key']` set during render

### ✅ In-Chat Lesson Browser (v1.5.5)
`openLessonLibrary()` → REST → in-chat TOC. `loadLessonInChat()` for full content inline. `resumeLastLesson()` with localStorage.

### ✅ IVR Messages Admin Redesign (v1.5.5)
All 5 phases on one scrollable page with sticky headers. Inline-editable message cards. Per-message save with phase reassignment fix.

### ✅ Purchase → Member → Content Flow (v1.5.5)
Sandbox and Stripe both grant member access. Post-reload phase detection → MemberPromptPanel → lesson library.

### ✅ SSO System (5 providers)
Google, Facebook, Apple, Microsoft, LinkedIn — all per-flow.

### ✅ Companion Mode (v1.6.0)
4 new files, floating widget, Chat + TOC tabs, page context detection, per-flow settings. Fully wired.

### ✅ Quiz Results Premium CSS (v1.6.0)
`showQuizResults()` and `displayAudioQuizResult()` use gradient card CSS with score tiers (`high-score`/`medium-score`/`low-score`). Entrance animations, confetti for high scores.

### ✅ Quiz/Offer Inline Styles → CSS Classes (v1.6.0)
`handleQuizAnswer()` → `.flosc-quiz-option--disabled`/`--selected`. `dismissOffer()` → `.flosc-offer--dismissing`. Clickable cards → `.flosc-offer--clickable`. CTA price → `.flosc-offer-cta-price`.

### ✅ Admin CSS Rewrite (v1.6.0)
Complete rewrite from flat 2015-era to modern design system. 30+ CSS custom properties as `--flosc-admin-*` tokens. 20+ component classes.

### ✅ Settings.php CSS Migration (v1.6.0)
`flosc_tab_header()`, `flosc_tab_footer()` use CSS classes. Page header, IVR selector, flow blocks, status badges all converted.

---

## What Needs Work — Research & Implement

### 1. COMPLETE PRESET FILES (HIGHEST PRIORITY)

Each of the 5 preset files currently defines 41 variables. They need to define ALL 97, covering these missing groups:

**Pills (6 vars):**
```css
--flosc-pill-bg: ...;
--flosc-pill-text: ...;
--flosc-pill-border: ...;
--flosc-pill-hover-bg: ...;
--flosc-pill-hover-text: ...;
--flosc-pill-hover-border: ...;
```

**Cards (8 vars):**
```css
--flosc-card-bg: ...;
--flosc-card-text: ...;
--flosc-card-border: ...;
--flosc-card-hover-bg: ...;
--flosc-card-hover-text: ...;
--flosc-card-hover-border: ...;
--flosc-card-shadow: ...;
--flosc-card-hover-shadow: ...;
```

**Panels (10 vars):**
```css
--flosc-panel-bg: ...;
--flosc-panel-border: ...;
--flosc-panel-shadow: ...;
--flosc-panel-header-text: ...;
--flosc-panel-eyebrow-text: ...;
--flosc-panel-close-bg: ...;
--flosc-panel-close-border: ...;
--flosc-panel-close-text: ...;
--flosc-panel-close-hover-bg: ...;
--flosc-panel-close-hover-border: ...;
```

**Quiz Modal (16 vars):**
```css
--flosc-quiz-prompt-bg: ...;
--flosc-quiz-prompt-text: ...;
--flosc-quiz-tab-bg: ...;
--flosc-quiz-tab-text: ...;
--flosc-quiz-tab-active-bg: ...;
--flosc-quiz-tab-active-text: ...;
--flosc-quiz-input-bg: ...;
--flosc-quiz-input-border: ...;
--flosc-quiz-input-text: ...;
--flosc-quiz-input-focus-border: ...;
--flosc-quiz-record-bg: ...;
--flosc-quiz-waveform: ...;
--flosc-quiz-success-bg: ...;
--flosc-quiz-warning-bg: ...;
--flosc-quiz-error-bg: ...;
--flosc-quiz-error-text: ...;
```

**Quiz Results (5 vars):**
```css
--flosc-quiz-result-correct-bg: ...;
--flosc-quiz-result-correct-text: ...;
--flosc-quiz-result-incorrect-bg: ...;
--flosc-quiz-result-incorrect-text: ...;
--flosc-quiz-result-transcript-text: ...;
```

**Quiz Option (2 vars):**
```css
--flosc-accent-bg: ...;
--flosc-accent-ring: ...;
```

**Scrollbar (already covered)** ✅

For each preset, choose values that match the preset's design language:
- **Light:** Clean whites, soft grays, blue accent (#2563eb)
- **Dark:** Deep grays (#1a1a2e, #16213e), light text, blue accent
- **Claude:** Warm beige/cream (#faf9f6), brown sidebars (#2d2117), orange accent (#d97706)
- **ChatGPT:** Neutral grays (#343541, #444654), green accent (#10a37f)
- **Grok:** Near-black (#0d0d0d, #1a1a1a), blue-purple accent (#5b6eef)

### 2. FIX `enqueue_chat_style()` PER-FLOW SETTINGS

Replace the 6 `get_option()` calls at the top of `enqueue_chat_style()` (flosc.php L2407) with `FLOSC_Flow_Manager::get_setting()` calls using the `style` override group. See the "Issue 2" section above for exact before/after code.

### 3. COMPLETE ACCENT COLOR CASCADE

In the `enqueue_chat_style()` function, after the accent color is set, expand the dynamic overrides to include ALL accent-derived variables. Currently overrides 5 variables, should override ~15. See "Issue 5" for the full list.

**Implementation:** In the accent color section of `enqueue_chat_style()`, add CSS variable overrides for every variable that derives from the accent color. Use PHP `list($r, $g, $b) = sscanf($accent, "#%02x%02x%02x")` to extract RGB, then generate `rgba()` values at various opacities.

### 4. ELIMINATE ALL REMAINING INLINE STYLES

**JS inline styles (flosc-app.js) — add CSS classes to `flosc-theme.css`:**
- Payment error → `.flosc-payment-error`
- Sandbox text → `.flosc-sandbox-text`
- Sandbox subtext → `.flosc-sandbox-subtext`
- Success detail → `.flosc-success-detail`
- Celebration → `.flosc-celebration`

**PHP CTA box (class-content-protection.php) — add CSS class to `flosc-theme.css`:**
- CTA box → `.flosc-cta-box`

**Admin inline styles (~75 across admin tabs) — add CSS classes to `flosc-admin.css`:**
Audit each admin PHP file. For every `style="..."` attribute:
1. If it's `display:none` for JS toggle → **KEEP** (standard pattern)
2. If it sets dynamic CSS properties (`--score-percent`, `--flosc-scale`) → **KEEP**
3. Everything else → **CONVERT** to a CSS class in `flosc-admin.css`

### 5. SCOPE COLLISION-PRONE CSS SELECTORS

In `flosc-layout.css`, identify the 20 most collision-prone selectors (`.message`, `.modal`, `.greeting`, `.product-name`, `.chat-header`, `.sidebar`, etc.) and scope them under `.flosc-app`:

```css
/* Before */
.message { ... }

/* After */
.flosc-app .message { ... }
```

Also update corresponding JS in `flosc-app.js` if any `querySelector()` calls need updating (likely not — they're scoped to the FLOSC container already).

### 6. COMPANION MODE — VERIFY & POLISH

The companion mode was built but never tested on a live site. Verify:
1. `class-companion-widget.php` `should_load()` returns `false` on FLOSC app routes
2. `FLOSC_COMPANION` JS config object is correctly injected into the page
3. Widget renders with the correct position and style settings
4. REST API calls work (the companion uses the same `/flosc/v1/` endpoints)
5. Page context detection works for lesson posts, archives, pages, home
6. The companion tab in admin settings saves and loads correctly
7. The companion CSS doesn't conflict with any major WordPress themes (it's BEM-scoped so should be safe)

### 7. EXISTING TASKS FROM v1.6.0 PROMPT (Still Relevant)

These tasks from the previous prompt still apply:

1. **"See My Offers" pill** — Guest PromptPanel needs a pill that shows all available offers
2. **Offer display format variety** — Verify all 7 display formats render correctly
3. **Sample Data admin UI** — Create admin button to install/remove sample data
4. **Timer verification** — Confirm OTO countdown timer works, persists across reloads
5. **Sandbox purchase E2E** — Complete flow test from purchase to member content access

---

## Files to Read First

Before making changes, read these files in this order:

1. `assets/css/flosc-theme.css` lines 1–140 — All 97 CSS custom properties in `:root`
2. `assets/css/chat-style-dark.css` — Full preset file (only 54 lines) to see what's covered
3. `flosc.php` lines 2407–2560 — `enqueue_chat_style()` + `extract_css_variables()`
4. `admin/chat-styling.php` — Full file (262 lines) — what the admin configures
5. `includes/class-flow-manager.php` — `get_setting()` method + override group defaults
6. `STYLING_UPGRADE_v1.6.0.md` — What was already changed and what's remaining
7. `assets/css/flosc-admin.css` lines 1–60 — `--flosc-admin-*` design tokens

---

## Rules of Engagement

1. **Show changes before applying** — Present diffs for review
2. **Never commit/push/zip without explicit approval**
3. **Never overwrite files without showing what will change**
4. **BridgeFile content is NOT PUBLIC** — Do not decode, discuss, or expose base64 content
5. **Test incrementally** — Make one change, verify it works, move to the next
6. **Preserve existing functionality** — Don't break what's already working
7. **Per-flow architecture** — All storage operations must use flow manager, never raw `get_option()` for configurable settings
8. **3-layer CSS is sacred** — Layout = structure (no colors). Theme = visuals (CSS custom properties). Presets = overrides.
9. **No hardcoded colors** — Every color must trace back to a CSS custom property
10. **Version bumps** — All 7 locations, all 4 files, simultaneously
11. **Naming conventions** — `--flosc-*` for frontend vars, `--flosc-admin-*` for admin vars, `.flosc-*` for CSS classes, `FLOSC_*` for PHP constants/classes

---

## Priority Order

1. **Complete preset files** — Add missing 56 variables to all 5 presets → every component themed
2. **Fix `enqueue_chat_style()` per-flow** — Use flow manager instead of raw `get_option()`
3. **Expand accent color cascade** — Admin accent choice propagates to ALL accent-derived vars
4. **Eliminate inline styles** — JS, PHP admin tabs, content protection CTA
5. **Scope collision-prone selectors** — Top 20 in `flosc-layout.css` under `.flosc-app`
6. **Verify companion mode** — Works on live site, no CSS conflicts
7. **"See My Offers" pill** — Guest prompt panel enhancement
8. **Verify offer display formats** — All 7 render correctly
9. **Sample data admin UI** — Install/remove button
10. **Timer + sandbox E2E** — Countdown timer + full purchase flow test

---

## Success Criteria

When v1.6.1 is complete:

1. ✅ Switching presets in Chat Styling tab changes EVERYTHING — pills, cards, panels, quiz, offers, scrollbar — not just messages and sidebar
2. ✅ Picking a red accent color turns ALL accent-derived elements red (pills, cards, links, quiz tabs, send button, avatars)
3. ✅ Per-flow style overrides work: Flow A can be dark theme with green accent, Flow B can be Claude preset with orange accent
4. ✅ Zero hardcoded hex colors in CSS component rules — everything traces to `var(--flosc-*)`
5. ✅ Zero inline styles in JS except `display:none` toggles and dynamic CSS property assignments
6. ✅ Zero inline styles in admin PHP except `display:none` toggles
7. ✅ Companion widget works on WordPress pages without CSS conflicts
8. ✅ All 5 presets (Light, Dark, Claude, ChatGPT, Grok) look polished and professionally distinct
9. ✅ The Chat Styling admin tab is the single source of truth for all visual customization
10. ✅ A FLOSC admin thinks: "This styling system is professional-grade. I can make my chatbot look exactly how I want."

**This is NOT a plan. This is NOT a report. This is working code in the v1.6.1 directory.**

---

## How to Read the Code

### Plugin Pattern
- **`flosc.php`** — Main orchestrator: hooks, REST endpoints, phase detection, `enqueue_chat_style()`
- **`admin/flosc-app.php`** — Frontend template: generates `FLOSC_CONFIG` and `FLOSC_USER` JS objects
- **`assets/js/flosc-app.js`** — Chatbot frontend: quiz, login, offers, checkout, content
- **`includes/`** — Modular PHP classes per concern
- **`admin/`** — WordPress admin UI tabs
- **`ai_configuration_files/`** — IVR `.md` files defining phase-specific messages

### Per-Flow Settings Resolution
```php
FLOSC_Flow_Manager::get_setting($option_name, $override_group, $key)
```
- If `$flow['overrides'][$group]['use_global']` is `true` → reads `get_option($option_name)`
- If `false` → reads `$flow['overrides'][$group][$key]`
- Falls back to global if per-flow key is missing

### Flow Resolution (flosc.php)
1. `flosc_ivr` query var (WP rewrite rule)
2. Custom domain match (`HTTP_HOST`)
3. Slug match (`REQUEST_URI`)
4. `null` if no match

### `enqueue_chat_style()` Flow (flosc.php L2407)
```
Read settings → Determine preset → Load preset CSS (inline or external)
→ Apply bubble radius overrides → Apply accent color overrides
→ Apply scale → Apply font → Append custom CSS
→ All injected as inline CSS on 'flosc-theme' handle
```

### Companion Separation
- `flosc-companion.js` reads `window.FLOSC_COMPANION` (NOT `FLOSC_CONFIG`)
- `flosc-companion.css` is self-contained (NOT dependent on `flosc-layout.css` or `flosc-theme.css`)
- Widget loads on non-app routes only (`!is_flosc_request()`)
- App loads on app routes only (`is_flosc_request()`)
