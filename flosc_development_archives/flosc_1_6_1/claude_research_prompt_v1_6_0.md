# Claude Code Research Preview — FLOSC v1.6.0 Research Prompt

## Context & Role

You are working on **FLOSC v1.6.0**, a WordPress plugin that implements a conversational sales funnel framework: **Freeline → Login → Offer → Sale → Content**. The developer (Dainis Michel) is the architect; you are the coder. Never commit, push, or zip without explicit approval. Show diffs/changes for review before applying.

**Repository:** `https://github.com/dainiswmichel/flosc.git` (branch: `main`)
**Plugin root:** `mvp_sprint/flosc_1_6_0/`
**Live test:** `https://flosc.io/flosc/` (production)

---

## Mission: Flawless Default UX That Showcases FLOSC

The goal of v1.6.0 is to introduce **Companion Mode** — a floating chat companion widget that appears on WordPress pages alongside lesson content. This is the bridge from "everything in the chatbot" to "chatbot as a learning companion on your site." Members get two ways to experience content: in-chat or browsing WordPress posts with the companion at their side.

---

## Architecture Quick Reference

### Per-Flow Storage (CRITICAL)
- **Single option:** `flosc_flows` (wp_option) managed by `FLOSC_Flow_Manager::get_setting($option_name, $override_group, $key)`
- **SSO exception:** `flosc_flow_<id>` is a separate wp_option
- **Flow key construction:** `$flow_key = 'flosc_flow_' . sanitize_key(pathinfo($ivr_filename, PATHINFO_FILENAME))`
  - Example: `flosc_flow_flosc_default_ivr`
- **REST API:** `get_ivr_messages()` reads directly from parsed `.md` files on every request — DB is admin-side only
- **IVR DB storage:** Per-flow at `$fs['ivr_messages']`, `$fs['ivr_phases']`, `$fs['ivr_styles']` where `$fs = get_option($flow_key, [])`

### Version Locations (7 total, 4 files)
1. `flosc.php` line 6: `Version: 1.5.5`
2. `flosc.php` line 17: `define('FLOSC_VERSION', '1.5.5')`
3. `admin/flosc-app.php` line 6: `Version: 1.5.5`
4. `readme.md` line 1: `# FLOSC v1.5.5`
5. `readme.md` line 5: `**Version:** 1.5.5`
6. `assets/js/flosc-app.js` line 9: `Version: 1.5.5`
7. (if bumping) Update all 7 simultaneously

### Key Files
| File | Purpose | Lines |
|------|---------|-------|
| `flosc.php` | Main plugin orchestrator | ~6056 |
| `assets/js/flosc-app.js` | Frontend chatbot engine | ~4877 |
| `admin/settings.php` | Admin settings + Flow dropdown | ~851 |
| `admin/ivr-messages.php` | IVR admin: load/sync/clear/edit | ~1008 |
| `admin/offers.php` | Offer CRUD admin page | ~600 |
| `admin/lessons.php` | Lesson config admin page | ~400 |
| `admin/flosc-app.php` | App template, generates `FLOSC_CONFIG` | ~700 |
| `includes/class-companion-widget.php` | Companion floating widget (v1.6.0) | ~340 |
| `assets/js/flosc-companion.js` | Companion widget frontend | ~470 |
| `assets/css/flosc-companion.css` | Companion widget styles | ~530 |
| `admin/companion.php` | Companion admin tab | ~180 |
| `includes/class-free-lesson-manager.php` | Quiz→free lesson selection→delivery | 282 |
| `includes/class-condition-evaluator.php` | Server-side IVR condition eval | ~411 |
| `includes/class-member-access.php` | Member access tiers, free lesson count | 462 |
| `includes/sale/class-offer-manager.php` | Offer CRUD + 7 default offers | ~400 |
| `includes/sale/class-sale-manager.php` | Purchase orchestration | ~280 |
| `ai_configuration_files/flosc_default_ivr.md` | Default IVR flow messages | 580 |

### IVR Message Structure
```
# Freeline Messages    → Visitor → IntroPanel (cards)
# Guest Messages       → Guest → PromptPanel (pills)  [covers Login + Offer phases]
# Offer Messages       → Guest → PromptPanel (pills)  [offer-specific messages]
# Sale Messages        → Post-purchase onboarding
# Content Messages     → Member → MemberPromptPanel (pills)
```

### Condition System
Conditions like `is_visitor`, `is_guest`, `is_member`, `quiz_taken`, `lesson_viewed`, `score >= 70`, `free_lessons_count == 1`, `offer_shown_oto_main`, `first_message_after_quiz`, etc. — evaluated server-side by `class-condition-evaluator.php`.

### Action System (JS)
| Action | Handler |
|--------|---------|
| `open_quiz` | Opens quiz UI |
| `open_registration` | Opens SSO login modal |
| `open_free_lesson` | `requestFreeLesson()` — delivers WP post content inline in chat |
| `show_offer_{offerId}` | Displays offer card/pill in chat |
| `checkout_{offerId}` | `openCheckout(offerId)` → Stripe modal or redirect |
| `sandbox_purchase_{productId}` | Demo purchase without real payment |
| `open_lesson_library` | Opens lesson browser |
| `open_quiz_library` | Opens quiz browser |
| `open_last_lesson` | Resumes last incomplete lesson |
| `open_support` | Opens support interface |

---

## What's Already Done in v1.5.5

### ✅ IVR Per-Flow Storage Fix (from v1.5.4)
- `flosc_import_ivr_to_database($preview_only, $flow_key)` — accepts flow_key, reads `$GLOBALS['flosc_current_ivr']`
- `flosc_export_ivr_backup($flow_key)` — per-flow storage, `bckp_NN_basename.md` naming
- `flosc_auto_export_ivr_to_file($flow_key)` — writes to current IVR file (not hardcoded)
- All call sites in `ivr-messages.php` pass `$flow_key`

### ✅ Backup System
- New naming: `bckp_01_flosc_default_ivr.md`, `bckp_02_flosc_default_ivr.md`, etc.
- Auto-increment: finds highest existing `bckp_NN_` and adds 1
- Admin UI: backups visible in Flow dropdown via `<optgroup>` (hidden by default)
- "📂 Backups (N)" toggle button next to "View App" button
- `toggleBackups()` JS function with auto-show when backup is selected

### ✅ Score Pill Wording
- Changed "Review my score" → "See my score" in default IVR

### ✅ Clear DB Safety
- Updated success message: "Click Load MD → FLOSC DB to restore from .md file"
- Backup is created before clear
- .md file is never touched by clear

### ✅ Free Lesson Pills (verified existing)
- `view_free_lesson_single_001` — `free_lessons_count == 1` → "View my free lesson!"
- `view_free_lessons_plural_001` — `free_lessons_count > 1` → "View my free lessons!"
- `view_free_lesson_fallback_001` — no count condition → "View my free lesson!"
- `free_lessons_count` wired in `class-condition-evaluator.php` (build_context + evaluate_single)

### ✅ Offer System Fixes (v1.5.5)
- **Save/read mismatch fixed:** `get_all_offers()` reads per-flow first via `$GLOBALS['flosc_settings_key']`, falls back to global
- **`oto_main` default offer added:** $25 was $100, featured format, 1-hour timer, 30-day guarantee
- **`full_access` pricing updated:** $49 was $99 (real demo pricing instead of "Configure in Stripe")
- **`checkAutoMessages()` type filter fixed:** Now processes `type === 'offer'` IVR messages (were silently skipped)
- **Frontend flow key:** `$GLOBALS['flosc_settings_key']` set during `render_flosc_app()` so offers load from per-flow storage

### ✅ `otoOfferId` Per-Flow Fix (v1.5.5)
- `FLOSC_CONFIG.otoOfferId` now reads from per-flow `$fs['oto_offer_id']` first, falls back to global `get_option('flosc_oto_offer_id')`
- Matches how `admin/lessons.php` saves the OTO selection per-flow

### ✅ `grants_level` Fallback Fix (v1.5.5)
- `class-member-access.php` `grant_member_access()` fallback path now uses `flosc()->sale()->offers()->get_offer()` (per-flow) instead of `get_option('flosc_offers')` (global)
- Reads `$offer['grants']['level']` (correct nested key) with fallback to `$offer['grants_level']` (flat key)

### ✅ Read-More Marker Standardization (v1.5.5)
- `create-sample-data.php` now uses `<!--flosc_read_more-->` (was `<!--more-->`)
- `class-content-filter.php` now falls back to `<!--more-->` if no `<!--flosc_read_more-->` found (WordPress content compatibility)
- `class-content-protection.php` already had both-marker support

### ✅ IVR Messages Admin Redesign (v1.5.5)
- Removed tabbed phase navigation (Freeline/Login/Offer/Sale/Content tabs)
- All 5 phases visible on one scrollable page with sticky header rows
- Inline-editable message cards (expand/collapse without page navigation)
- Per-message save buttons: [Save to DB], [Save & Sync to File], [Delete]
- Phase reassignment bug fixed (moving a message between phases no longer duplicates it)

### ✅ SSO System (5 providers)
- Google, Facebook, Apple, Microsoft, LinkedIn — all implemented with per-flow credentials

### ✅ In-Chat Lesson Browser (v1.5.5)
- **`openLessonLibrary()`** now fetches from REST `/flosc/v1/lessons` and renders an in-chat TOC card (was hard redirect to `/lessons/`)
- **`loadLessonInChat(id, title)`** new function — fetches single lesson from `/flosc/v1/lessons/{id}` and renders full content inline in chat
- **`resumeLastLesson()`** now works — reads last lesson from `localStorage` and reloads it, or falls back to showing TOC
- **`view_lesson_{id}`** action added to `performIVRAction()` switch — IVR messages can now open specific lessons
- **Lesson tracking:** Each viewed lesson saved to `localStorage` (`flosc_last_lesson_id`, `flosc_last_lesson_title`)
- **Access control:** 403 responses from API trigger upgrade message + offer check
- **CSS:** Full TOC and inline lesson styles in `flosc-theme.css` — gradient headers, clickable lesson rows with thumbnails/numbers, scrollable list, back-to-lessons button

### ✅ Purchase → Member → Content Flow (v1.5.5 verification)
- **Sandbox purchase:** `processSandboxPayment()` → `/sandbox-purchase` → `grant_member_access()` → `flosc_purchase_completed` action → `location.reload()` — **fully working**
- **Stripe purchase:** `openCheckout()` → Stripe modal → `create-payment-intent` → `confirmCardPayment` → `complete-purchase` → same grant chain → `location.reload()` — **fully working**
- **Post-reload state:** `FLOSC_USER.purchased = ($user_state === 'member')` → `determinePhase()` returns `'content'` → MemberPromptPanel shows with "Browse my lessons" button → `openLessonLibrary()` now shows in-chat TOC

---

## What Needs Work — Research & Implement

### 1. GUEST PROMPT PANEL — "See My Offers" Pill (NEW)

**Current state:** Guest PromptPanel has: "View my free lesson!", "See my score", "Retake my free lesson", generic offer pills.

**Needed:** Add a "Check out my offers" or "See my offers" pill that shows the guest a summary of available offers. This should:
- Be a `suggested_user_autoprompt` with `MessageStyle: pill`
- Use `Action: show_offers` or similar
- Condition: `is_guest && quiz_taken`
- Show MULTIPLE offers (not just the main OTO)

**Implementation approach:**
1. Add the IVR message to `flosc_default_ivr.md` in the Guest Messages section
2. Ensure JS `flosc-app.js` handles the `show_offers` action (or use existing `show_offer_{id}` for each)
3. Consider showing a mini offer gallery/carousel in the chat

### 2. MULTIPLE OFFERS IN DEFAULT DATA

**Current state:** `class-offer-manager.php` has 8 default offers — 6 active, 2 draft:
- `oto_main` — **$25 was $100 (active, NEW in v1.5.5)** — "Full Course Access — Limited Offer", display_format: featured, timer: 3600s
- `free_trial` — Free (active)
- `full_access` — **$49 was $99 (active, UPDATED in v1.5.5)** — now has real demo pricing
- `flosc_plugin_full` — $197 was $297 (active)
- `simplified_solfeggio_full` — $47 was $97 (active)
- `lesaep_full` — $97 was $197 (active)
- `token_pack_small` — draft (correctly filtered out of frontend)
- `monthly_sub` — draft (correctly filtered out of frontend)

**Note:** `get_active_offers()` correctly filters drafts — they don't reach the frontend. The Offers admin tab has full CRUD with: Name, Type, Status (Active checkbox), Price, Display Price, Original Price, Trigger (manual/quiz_complete/lesson_complete/login_phase/inactivity), Condition (IVR-style), Display Format (pill/card/compact/banner/featured/text/inline-checkout), CTA, Timer, Guarantee, Grants level, Icon, Badge, Savings.

**✅ FIXED in v1.5.5:**
- `oto_main` default offer now exists — IVR messages referencing `OfferID: oto_main` will find their data
- `full_access` has real demo pricing ($49 was $99) instead of "Configure in Stripe"
- Offer save/read mismatch fixed (see item #5) — admin edits now persist correctly
- `checkAutoMessages()` now processes `type: 'offer'` messages (they were silently filtered out)
- `$GLOBALS['flosc_settings_key']` is set on frontend render path so offers load from per-flow storage

**Verify:** All 7 display formats (card, pill, compact, banner, featured, text, inline-checkout) render correctly with the new `oto_main` offer data.

### 3. OFFER DISPLAY FORMAT VARIETY

**Current IVR has these offer displayformats demonstrated:**
- `featured` (oto_main_001) — large card with timer, features list
- `banner` (oto_banner_001) — full-width promo banner
- `pill` (offer_pill_001) — compact pill in PromptPanel
- `inline-checkout` (checkout_inline_001) — Stripe form in chat

**Needed:** Make sure ALL these display formats render correctly in the frontend. Check `flosc-app.js` for each format's rendering code and verify they work with actual offer data.

### 4. SAMPLE DATA INSTALLER — ADMIN UI BUTTON

**Current state:** Sample data can only be installed via WP-CLI (`wp eval-file admin/create-sample-data.php`). The overview document describes an admin UI button but:
- `includes/class-sample-data.php` does NOT exist
- `admin/sample-data-manager.php` does NOT exist
- `create-sample-data.php` has a partial `flosc_sample_data_admin_ui()` function but it's not hooked
- `_flosc_seeded` meta is described but not set in the WP-CLI script

**Needed:** Create a proper admin UI for sample data:
1. Create a "📚 Sample Data" admin submenu page
2. "Install Sample Data" button — creates 10 lesson posts with proper meta
3. "Remove Sample Data" button — deletes posts with `_flosc_seeded=1`
4. Show status: "10 sample lessons installed" or "No sample data"
5. Set `_flosc_seeded=1` on all created posts

### 5. OFFER SYSTEM — VERIFY FIXES WORK END-TO-END

**✅ FIXED in v1.5.5 — 4 bugs resolved:**

1. **Save/read mismatch (FIXED):** `get_all_offers()` now reads from per-flow storage (`$GLOBALS['flosc_settings_key']`) first, matching where `admin/offers.php` saves. Falls back to global `flosc_offers` if no per-flow data exists.

2. **`checkAutoMessages()` type filter (FIXED):** Now processes both `type === 'auto'` AND `type === 'offer'` messages. Previously, IVR messages with `MessageType: offer` were silently filtered out and never triggered.

3. **`oto_main` offer missing (FIXED):** Added `oto_main` as a default offer with $25 pricing (was $100), featured display format, 1-hour timer, features list, and proper grants.

4. **Frontend flow key (FIXED):** `$GLOBALS['flosc_settings_key']` is now set on the frontend render path (`flosc.php` render_flosc_app) so `get_all_offers()` can read per-flow offers for both admin and frontend contexts.

**Verify the complete chain works:**
1. Admin configures offers in Offers tab → saves to per-flow storage ✅
2. Frontend renders → `FLOSC_CONFIG.offers` includes `oto_main` and all active offers ✅  
3. IVR messages with `type: 'offer'` are processed by `checkAutoMessages()` ✅
4. `getOfferData('oto_main')` finds the offer in `FLOSC_CONFIG.offers` ✅
5. Display format routing (featured/card/pill/banner/etc.) renders correctly ← NEEDS TESTING
6. Offer triggers & conditions on the offer object itself are still dead code (trigger/condition fields saved by admin but never evaluated) ← OPTIONAL ENHANCEMENT

### 6. ~~OTO OFFER ID MISMATCH~~ ✅ DONE

**FIXED in v1.5.5:** `FLOSC_CONFIG.otoOfferId` now reads from per-flow `$fs['oto_offer_id']` first via `$GLOBALS['flosc_settings_key']`, falls back to global `get_option('flosc_oto_offer_id')`. Matches how `admin/lessons.php` saves the selection.

### 7. DEFAULT IVR ENHANCEMENTS

The current IVR is comprehensive but could be more engaging for a demo. Consider:

**Freeline (Visitor) additions:**
- More personality in the welcome message
- "What others are saying" social proof pill with fake demo testimonials
- "How long does it take?" FAQ pill

**Guest additions:**
- "🛍️ See my offers" pill (as described in #1)
- Post-free-lesson encouragement with specific benefit statements
- "Ask me anything about the course" conversational pill

**Offer additions:**
- Multiple offer tiers in the IVR (not just `oto_main`)
  - A budget option (e.g., `monthly_sub` at $9.99/mo)
  - The main OTO ($25 one-time, was $100)
  - A premium bundle ($99, includes everything)
- "Compare plans" pill that shows all options
- Guarantee messaging ("30-day money-back guarantee")

**Member additions:**
- More celebratory first-purchase message
- "Share with a friend" pill
- Achievement messaging based on lessons_completed milestones

### 8. TIMED OFFER CARD — VERIFY TIMER WORKS

The featured OTO uses `Timer: 3600` (1 hour). Verify:
1. JS renders the countdown timer correctly
2. Timer persists across page reloads (localStorage/sessionStorage)
3. Timer variable `{timer_remaining}` substitutes correctly
4. What happens when timer expires (price changes? offer disappears? nothing?)

### 9. SANDBOX PURCHASE FLOW — VERIFY END-TO-END

The IVR has a sandbox purchase button (`sandbox_purchase_flosc_plugin`). Verify:
1. `openSandboxPurchase()` in JS works without Stripe configured
2. The sandbox flow grants member access
3. Post-purchase messages appear (`first_message_after_purchase`)
4. Member pills appear in MemberPromptPanel
5. Lesson library opens

### 10. ~~`<!--more-->` vs `<!--flosc_read_more-->` INCONSISTENCY~~ ✅ DONE

**FIXED in v1.5.5:**
- `create-sample-data.php` now uses `<!--flosc_read_more-->` (was `<!--more-->`)
- `class-content-filter.php` now falls back to `<!--more-->` if `<!--flosc_read_more-->` not found
- `class-content-protection.php` already handles both markers
- `flosc-sample-lessons.xml` already uses `<!--flosc_read_more-->`

---

## Files to Read First

Before making changes, read these files to understand the current state:

1. `ai_configuration_files/flosc_default_ivr.md` — Full IVR message set (580 lines)
2. `includes/sale/class-offer-manager.php` — `get_default_offers()` method
3. `admin/flosc-app.php` lines 610-700 — `FLOSC_CONFIG` generation
4. `assets/js/flosc-app.js` — Search for `openCheckout`, `showPaymentModal`, `openSandboxPurchase`, `showOffer`, `requestFreeLesson`
5. `includes/class-free-lesson-manager.php` — Free lesson delivery flow
6. `includes/class-condition-evaluator.php` — Available conditions
7. `admin/ivr-messages.php` — IVR admin tab
8. `admin/settings.php` — Flow dropdown and backup toggle

---

## Rules of Engagement

1. **Show changes before applying** — Present diffs for review
2. **Never commit/push/zip without explicit approval**
3. **Never overwrite files without showing what will change**
4. **BridgeFile content is NOT PUBLIC** — Do not decode, discuss, or expose base64 content in readme.md
5. **Test incrementally** — Make one change, verify it works, move to the next
6. **Preserve existing functionality** — Don't break what's already working
7. **Per-flow architecture** — All storage operations must use `$flow_key`, never global `flosc_ivr_*` options
8. **REST API reads from .md files** — Frontend reads IVR from parsed markdown, not from DB
9. **Version bumps** — All 7 locations, all 4 files, simultaneously

---

## Priority Order

1. **~~Fix oto_main offer mismatch~~ ✅ DONE** — `oto_main` default offer created with $25 demo pricing
2. **~~Fix offer admin save/read mismatch~~ ✅ DONE** — `get_all_offers()` reads per-flow, `checkAutoMessages()` includes offer-type messages, frontend flow key set
3. **Add "See my offers" pill** — Guest prompt panel enhancement
4. **Enhance default IVR messages** — More personality, multiple offer tiers
5. **Create sample data admin UI** — "Install Sample Data" button in admin
6. **~~Standardize read-more marker~~ ✅ DONE** — `<!--flosc_read_more-->` is primary, `<!--more-->` is fallback
7. **Verify sandbox purchase E2E** — Complete flow test
8. **Verify timer functionality** — Countdown timer in offer cards
9. **~~Fix OTO offer ID source~~ ✅ DONE** — Per-flow first, global fallback
10. **~~Fix grants_level fallback~~ ✅ DONE** — Now reads via offer manager, correct nested key

---

## Success Criteria

When v1.5.5 is complete, a fresh FLOSC install should:

1. ✅ Show engaging visitor cards with quiz CTA and personality
2. ✅ After quiz + login, show score, free lesson pill(s), and offer pills
3. ✅ Free lesson renders inline in chat with real WP post content
4. ✅ "See my offers" pill shows available purchase options
5. ✅ Featured OTO card with countdown timer appears at the right moment
6. ✅ Multiple offer formats visible (featured card, banner, pill, inline-checkout)
7. ✅ Sandbox purchase works end-to-end without Stripe config
8. ✅ Post-purchase congratulation message appears
9. ✅ Member prompt panel shows lesson browsing, quiz retake, progress
10. ✅ Admin can install/remove sample data with one click
11. ✅ Backups are accessible via toggle in Flow dropdown
12. ✅ Clearing IVR DB is safe and reversible

The visitor should think: "This is polished and fun. I want this for my business."

---

## Architecture Vision: v1.6.0 — Companion Mode ✅ BUILT

### The Insight

Since FLOSC lives inside a WordPress install, the chatbot doesn't have to contain everything. Once a user is a **guest** or **member**, we can offer them a choice:

- **Mode 1: In-Chat** (existing) — everything happens inside the chatbot fullscreen app
- **Mode 2: Companion** (NEW in v1.6.0) — floating widget on WordPress pages, lessons are normal WP posts, chatbot is always there as a **companion** — knowing their score, what they've completed, what they struggle with
- **Mode 3: Both** — both modes available, admin chooses per-flow

### What Was Built in v1.6.0

| Component | File | Purpose |
|-----------|------|--------|
| `FLOSC_Companion_Widget` | `includes/class-companion-widget.php` | Singleton class. Hooks `wp_footer` to inject widget HTML + `FLOSC_COMPANION` config on non-app WP pages. Enqueues companion-specific JS/CSS. Detects page context. Respects per-flow settings. |
| `flosc-companion.js` | `assets/js/flosc-companion.js` | Self-contained IIFE. Reads `FLOSC_COMPANION`. Renders floating widget with Chat + Lessons TOC tabs. Context-aware suggestions based on current page. REST API communication to existing `/flosc/v1/` endpoints. |
| `flosc-companion.css` | `assets/css/flosc-companion.css` | Complete widget styles. CSS custom properties for theming. BEM naming. Responsive (mobile). Dark mode support. Print hidden. Reduced motion support. |
| Companion admin tab | `admin/companion.php` | New "Companion" tab in FLOSC Settings. Content display mode radio cards (In-Chat / Companion / Both). Widget settings: position, greeting, accent color, visitor visibility. Live preview. |
| `companion` override group | `includes/class-flow-manager.php` | Added to per-flow `overrides` defaults. Allows per-flow companion configuration. |

### Separation of Concerns

- `flosc-companion.js` is **completely independent** from `flosc-app.js` — different IIFE, different config object (`FLOSC_COMPANION` vs `FLOSC_CONFIG`), no shared code
- `flosc-companion.css` does **NOT** depend on `flosc-layout.css` or `flosc-theme.css` — it lives alongside the WP theme
- The companion widget does **NOT** load on the full FLOSC app route — `should_load()` checks `!flosc()->is_flosc_request()`
- The full FLOSC app does **NOT** load companion assets — `enqueue_assets()` only runs on app routes

### Admin Settings (per-flow)

- **Content Display Mode:** `in_chat` (default), `companion`, `both`
- **Enable Widget:** checkbox — master toggle
- **Position:** bottom-right (default) or bottom-left
- **Greeting:** customizable warm greeting message
- **Accent Color:** color picker with default FLOSC indigo (#6366f1)
- **Visitor Visibility:** whether non-logged-in visitors see the widget

### Page Context Detection

`FLOSC_Companion_Widget::detect_page_context()` tells the JS what the user is viewing:
- `type: 'lesson'` — on a lesson post (in configured category), includes `postId`, `title`, `tags`
- `type: 'lesson_archive'` — on the lessons category archive page
- `type: 'page'` — on a regular WordPress page
- `type: 'home'` — on the homepage
- `type: 'other'` — everything else

The JS uses this to provide contextual suggestions — e.g., on a lesson page: "Summarize this lesson", "Quiz me on this", "What's next?"

### The Flow in Companion Mode

```
Member opens WordPress site → companion widget appears as floating 💬 button
↓
Member clicks button → panel expands with greeting + contextual suggestions
↓
Member navigates to a lesson post → companion detects page context
↓
Companion: "I see you're reading [Lesson Title]. Ask me anything about this lesson!"
↓
Chips: "Summarize this lesson" | "Quiz me on this" | "What's next?"
↓
Member asks question → REST API call to /flosc/v1/chat with page context
↓
AI responds with context-aware help → member continues reading
↓
Member switches to Lessons tab → sees full TOC with current lesson highlighted
↓
Member clicks another lesson → navigates to that WP post, companion follows
```

### What's Next (v1.7.0+)

1. **AI activation:** Replace IVR scripts with AI-driven conversation using FLOSC_USER context + lesson catalog + condition system as guardrails
2. **Lesson progress tracking:** Mark lessons as read/completed, show progress in companion TOC
3. **Mini-quiz generation:** AI generates quiz questions based on lesson content
4. **Reading time estimation:** Show estimated reading time for each lesson
5. **Cross-page state persistence:** Companion remembers conversation across page navigations via sessionStorage
