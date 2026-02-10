# FLOSC v1.5.4 — Complete FLOSC Pipeline Research Prompt

## FOR: Claude Code Research Preview (with GREP MCP for GitHub access)

## REPO: https://github.com/dainiswmichel/flosc.git (branch: main)

## WORKING DIRECTORY: `mvp_sprint/flosc_1_5_4/`

---

## STATUS: Where We Are

**v1.5.3 has successfully taken us through Freeline → Login (F → L).** Here's what works:

- **Freeline (F):** Visitor takes quiz in the chatbot. Score is calculated and stored.
- **Login (L):** Both **Facebook SSO** and **Google SSO** work. The cross-domain redirect from `dainis.net` (WordPress) back to `flosc.ai` (custom domain chatbot) works correctly via a one-time login token system. After login, the user becomes a **guest** and sees their correct quiz score.

**What does NOT work yet:**
- After login, the guest is not yet receiving their randomly-selected free lesson(s) from the lessons they missed in the quiz
- The number of free lessons is configurable per-flow by the FLOSC admin (fixed count or proportion of missed)
- The offer is not being presented after the free lesson
- The sale/checkout flow is not connected
- The member content access after purchase is not wired up

**The goal of v1.5.4 is to take us all the way through the entire FLOSC pipeline: F → L → O → S → C.**

---

## THE FULL FLOSC PIPELINE

```
F (Freeline)     → Visitor takes quiz, gets score
L (Login)        → Visitor logs in via SSO, becomes guest, sees score
FREE LESSON      → Guest receives N randomly-selected free lessons from missed quiz topics
O (Offer)        → After free lesson, present the purchase offer  
S (Sale)         → Guest clicks buy → Stripe checkout → payment processed
C (Content)      → After purchase, guest becomes member → full content access
```

---

## PHASE 1: FREE LESSON DELIVERY (Post-Login → Offer)

### What exists but is disconnected

**Two separate free lesson systems exist and are NOT connected:**

1. **`includes/class-free-lesson-manager.php`** (268 lines)
   - `handle_quiz_completion()` hooks into `flosc_quiz_completed` action
   - `get_missed_lessons()` compares user answers vs correct answers
   - **Always picks exactly ONE** lesson via `array_rand()`
   - Stores `_flosc_free_lesson_number` in user meta
   - `deliver_free_lesson()` finds WP post with matching `_flosc_lesson_number` meta

2. **`includes/class-member-access.php`** (462 lines)
   - `calculate_free_lesson_count()` (lines 397-430) — reads admin config for mode/count/proportion
   - `grant_free_lessons()` (lines 432-451) — shuffles missed posts, picks N, grants guest access
   - **These methods are NEVER CALLED** by the free lesson manager

### Admin config for free lessons (per-flow)

Saved in `admin/lessons.php` lines 346-413 as per-flow settings (`flosc_flow_<flow_id>`):
- `free_lesson_mode`: `'fixed'` or `'proportion'`
- `free_lesson_count`: integer 1-50 (default: 1)
- `free_lesson_proportion`: `'1/5'`, `'1/4'`, `'1/3'`, `'1/2'`
- `guest_access_days`: integer 0-365 (default: 0 = unlimited)

**BUG:** `FLOSC_Member_Access::calculate_free_lesson_count()` reads these via `get_option('flosc_free_lesson_mode')` (global), but the admin saves them per-flow. This needs to read from the per-flow settings.

### Frontend free lesson flow

- JS `requestFreeLesson()` at `flosc-app.js` line 4658 → `POST /flosc/v1/free-lesson`
- PHP `get_free_lesson()` at `flosc.php` line 4712
- After delivery, JS sets `ivr.phase = 'offer'`, calls `checkAutoMessages()` after 2s
- IVR action `open_free_lesson` triggers `openFreeLesson()` in JS

### What needs to happen

1. **Wire `FLOSC_Free_Lesson_Manager` to use admin-configured count** — not hardcoded ONE
2. **Read free lesson config from per-flow settings** (`flosc_flow_<flow_id>`) not global `get_option()`
3. **After login, guest should automatically receive their free lesson(s)** — the missed lessons from the quiz, randomly selected, with the count determined by admin config
4. **After free lesson delivery, transition to offer phase** — this partially exists but needs verification
5. **The free lesson should be displayed in the chat** as content the guest can read right there

---

## PHASE 2: OFFER PRESENTATION (After Free Lesson)

### What exists

**Offer system is fully built but reads globally:**

- **`includes/sale/class-offer-manager.php`** (590 lines) — Full CRUD for offers, 7 display formats (card, pill, compact, banner, featured, text, inline-checkout), active filtering
- **IVR integration:** `showOfferMessage()` in `flosc-app.js` line 1635+ routes offers to correct display format
- **IVR .md files** define messages with `type: 'offer'` and `offer_id`
- **`FLOSC_CONFIG.offers`** — Offers ARE passed to the frontend
- **`FLOSC_CONFIG.otoOfferId`** — One-time-offer ID is passed
- **Offer triggers** include `lesson_complete` ("First Free Lesson Completed")

### What needs to happen

1. **Offers should load per-flow** — currently `get_all_offers()` reads global `flosc_offers` option, but admin saves per-flow
2. **After free lesson delivery, the IVR should auto-present the offer** — verify the `ivr.phase = 'offer'` transition triggers the right IVR messages
3. **If no IVR messages exist for offer phase yet,** create a sensible default flow that presents the offer after the free lesson

---

## PHASE 3: SALE / CHECKOUT (Stripe)

### What exists

**Stripe integration is thorough:**

- **`includes/sale/providers/class-stripe-provider.php`** (550 lines) — PaymentIntents, subscriptions, customer management, webhook handling with signature verification + replay protection + idempotency
- **`includes/sale/class-sale-manager.php`** (~280 lines) — Orchestrator: validates offer → validates provider → processes payment → grants access → logs purchase
- **REST endpoints** in `flosc.php`: `create-payment-intent` (L4739), `complete-purchase` (L4773), `webhooks/{provider}` (L4851)
- **JS checkout:** `openCheckout()` at `flosc-app.js` line 3587-3635

### What's missing

1. **Stripe.js is never loaded** — the frontend can't create a payment element without it. Need to enqueue `https://js.stripe.com/v3/` when offers exist
2. **Stripe credentials read globally** — `FLOSC_Payment_Provider::get_setting()` reads from global options, but admin saves per-flow Stripe keys (test + live modes)
3. **`FLOSC_CONFIG.stripeKey`** is generated at `flosc-app.php` line ~622 — verify it reads from the correct per-flow source
4. **`grants_level` path inconsistency** — `class-sale-manager.php` line 159 sends `$offer['grants_level']` but the offer structure stores it at `$offer['grants']['level']`

### What needs to happen

1. **Enqueue Stripe.js** when offers exist and Stripe is the payment provider
2. **Load Stripe credentials from per-flow settings**
3. **Fix the `grants_level` path** in sale manager
4. **Verify the complete flow:** click buy → `create-payment-intent` → Stripe card form → `complete-purchase` → access granted → phase transitions to `content`
5. **Provide a sandbox/test path** — Stripe test mode with test card `4242 4242 4242 4242`

---

## PHASE 4: CONTENT ACCESS (After Purchase)

### What exists

- **`includes/class-content-protection.php`** (637 lines) — Category-level protection, 4 visibility tiers (hidden/teaser/preview/public), post-level overrides
- **`includes/class-member-access.php`** (462 lines) — 3-tier hierarchy (visitor/guest/member), level-based access, `grant_member_access()` hooks into `flosc_purchase_completed`
- **`FLOSC_USER.purchased`** and **`FLOSC_USER.memberLevels`** are passed to frontend

### What needs to happen

1. **After purchase, `flosc_purchase_completed` action must fire** with correct offer data → `grant_member_access()` sets `_flosc_member_access` user meta + specific level
2. **Phase engine must detect member status** — `determine_flosc_phase()` checks `$this->sale_manager->access()->can_access($user_id, 'full')` → returns `'content'`
3. **Member should see full lesson content in the chatbot** — not just external links
4. **Per-flow content:** Each flow defines its specific member content. The content protection should know which flow the user purchased from. Currently it checks globally.

### Per-flow member content concept

Each flow has its own set of lessons/content. When a member purchases through Flow A, they should only get access to Flow A's content, not Flow B's. This means:
- Store `_flosc_flow_id` with the purchase grant
- Content protection checks which flow(s) the user has purchased
- Lesson delivery respects flow boundaries

---

## PHASE 5: STYLING CLEANUP

### Problem

The quiz and chatbot styling has accumulated inline styles that make it hard to maintain and override. The CSS architecture is well-designed (3-layer: layout, theme, chat-style) but inline styles bypass it.

### Inline styles to move to CSS classes

**In `flosc-app.js`:**
- Line 1982: Payment error — full inline `color, padding, text-align, font-size` → use `.flosc-payment-error` class
- Line 3444: Sandbox text — `font-size: 13px; opacity: 0.8` → use `.flosc-sandbox-text` class
- Line 3460: Sandbox subtext — `font-size: 12px; opacity: 0.8; margin-top: 10px` → use `.flosc-sandbox-subtext` class
- Line 3538: Success detail — `font-size: 14px; margin-top: 10px` → use `.flosc-success-detail` class
- Line 3541: Celebration — `font-size: 13px; margin-top: 15px` → use `.flosc-celebration` class

**In `includes/class-content-protection.php`:**
- Line ~399-404: CTA box has inline gradient/colors/padding → use `.flosc-cta-box` class

**Acceptable inline styles (leave as-is):**
- `display: none` for JS-toggled panels (standard pattern)
- CSS custom properties (`--score-percent`, `--flosc-scale`, `--sso-bg`) set dynamically
- Dynamic `width: N%` for progress bars

### Where to add CSS classes

Add new classes to `assets/css/flosc-theme.css` — this is the visual polish layer.

---

## FILE MAP — What to modify

| File | Lines | What to do |
|------|-------|------------|
| `flosc.php` | 5,980 | Wire per-flow free lesson config into REST endpoint, enqueue Stripe.js, fix FLOSC_USER data |
| `includes/class-free-lesson-manager.php` | 268 | Use admin-configured count (not hardcoded 1), read per-flow settings |
| `includes/class-member-access.php` | 462 | Read free lesson config from per-flow settings, fix `calculate_free_lesson_count()` |
| `includes/sale/class-offer-manager.php` | 590 | Load offers per-flow |
| `includes/sale/class-sale-manager.php` | ~280 | Fix `grants_level` path |
| `includes/sale/providers/class-stripe-provider.php` | 550 | Load credentials per-flow |
| `includes/class-content-protection.php` | 637 | Per-flow content awareness, move CTA inline styles to CSS |
| `admin/flosc-app.php` | 688 | Verify FLOSC_CONFIG has correct per-flow Stripe key |
| `assets/js/flosc-app.js` | 4,732 | Verify checkout flow connects, move inline styles to CSS classes |
| `assets/css/flosc-theme.css` | 1,423 | Add new CSS classes for moved inline styles |

---

## CONSTRAINTS

1. **Never use ALL-CAPS filenames** — always lowercase with hyphens
2. **Per-flow is the source of truth** — all settings come from `flosc_flow_<flow_id>` option, not global `get_option()`
3. **The 3-layer CSS architecture must be respected** — layout in flosc-layout.css, theme/visuals in flosc-theme.css, chat presets in chat-style-*.css
4. **Keep existing function signatures** — don't break the REST API contract
5. **One-time login token system is working** — don't touch SSO or redirect logic
6. **Phase engine alignment** — PHP `determine_flosc_phase()` and JS `determinePhase()` must agree on phase logic
7. **Don't fire `do_action('wp_login')` in programmatic login** — WooCommerce/BuddyBoss will hijack the redirect

---

## EXPECTED DELIVERABLES

After implementing v1.5.4, the complete flow should work:

1. **Visitor** arrives at flosc.ai chatbot
2. **Takes quiz** → score calculated → stored in cookie
3. **Logs in** via Facebook or Google SSO → redirected back to flosc.ai with correct score
4. **Receives N free lessons** randomly selected from missed quiz topics (N = admin-configured per-flow)
5. **Sees purchase offer** presented in chat (via IVR auto-message)
6. **Clicks buy** → Stripe checkout form appears in chat
7. **Pays** with card → payment processed → access granted
8. **Becomes member** → full content access for that flow's lessons
9. **Phase engine** correctly identifies member status on return visits

Each step should work end-to-end without manual intervention. The admin should be able to configure per-flow: free lesson count, offer details, Stripe credentials, and protected content.

---

## HOW TO READ THE CODE

The plugin follows a specific pattern:
- **`flosc.php`** is the main orchestrator — hooks, REST endpoints, phase detection, flow resolution
- **`admin/flosc-app.php`** is the frontend template that generates `FLOSC_CONFIG` and `FLOSC_USER` JavaScript objects
- **`assets/js/flosc-app.js`** is the chatbot frontend — handles quiz, login, offers, checkout, content display
- **`includes/`** contains modular PHP classes for each concern (SSO, sale, content, lessons, etc.)
- **`admin/`** pages render WordPress admin UI for configuring each module
- **IVR .md files** in `ai_configuration_files/` define phase-specific chatbot messages with actions

Per-flow settings are stored as serialized arrays in `wp_options` with key `flosc_flow_<flow_id>`. The current flow is determined by `get_current_flow()` in `flosc.php` lines 1464-1524.
