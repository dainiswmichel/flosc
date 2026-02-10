# FLOSC v1.5.4 — Complete FLOSC Pipeline Research Prompt

## FOR: Claude Code Research Preview (with GREP MCP for GitHub access)

## REPO: https://github.com/dainiswmichel/flosc.git (branch: main)

## WORKING DIRECTORY: `mvp_sprint/flosc_1_5_4/`

---

## THE PHILOSOPHY: What Drives FLOSC

FLOSC is the bridge from AI to AGI. The chasm between AI and AGI is not compute — it's knowledge locked in human heads by bitterness. FLOSC solves the root problem — bitterness — with joy.

**The driver through every phase of F → L → O → S → C is ENCOURAGEMENT.** Not pressure, not urgency tricks, not scarcity manipulation. Encouragement. Joy. Warmth. Recognition.

- **Experts share knowledge and get compensated fairly** — reducing bitterness
- **Try-before-you-buy ensures the right humans self-select** and pay willingly
- **AI's role is to facilitate generous, warm, rewarding human exchanges**
- **Every FLOSC installation is a lane on the bridge**

When a visitor logs in and becomes a guest, they are **warmly and joyously greeted — treated like a REAL GUEST.** Think about what it means to be a guest in someone's home. You're welcomed. You're valued. You're offered something before you're asked for anything.

That's the free lesson: a genuine gift of knowledge, chosen specifically for what THEY need (the topics they missed in the quiz). Not a teaser. Not a bait-and-switch. A real lesson they can learn from right there in the chat.

The offer comes after the gift, naturally — "Here's what the full course looks like." The guest has already experienced the quality. They self-select. They pay willingly. They become a member — and now BOTH sides are joyful: the expert is fairly compensated, the learner has exactly what they need.

**This emotional architecture must be reflected in every IVR message, every transition, every UI element.** The code implements the cog that replaces human bitterness with joy through financial compensation and recognition (which drives gratitude).

---

## STATUS: Where We Are

**v1.5.3 has successfully taken us through Freeline → Login (F → L).** Here's what works:

- **Freeline (F):** Visitor takes quiz in the chatbot. Score is calculated and stored but NOT shown to the visitor — they must log in first to see it.
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
F (Freeline)     → Visitor takes quiz, does NOT get score until logged in as guest
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

Saved in `admin/lessons.php` lines 346-413 as per-flow settings (stored in `flosc_flows` option → `overrides.lessons`):
- `free_lesson_mode`: `'fixed'` or `'proportion'`
- `free_lesson_count`: integer 1-50 (default: 1)
- `free_lesson_proportion`: `'1/5'`, `'1/4'`, `'1/3'`, `'1/2'`
- `guest_access_days`: integer 0-365 (default: 0 = unlimited)

**BUG:** `FLOSC_Member_Access::calculate_free_lesson_count()` reads these via `get_option('flosc_free_lesson_mode')` (global), but the admin saves them per-flow. This needs to use `FLOSC_Flow_Manager::get_setting()` with the `lessons` override group.

### Frontend free lesson flow

- JS `requestFreeLesson()` at `flosc-app.js` line 4658 → `POST /flosc/v1/free-lesson`
- PHP `get_free_lesson()` at `flosc.php` line 4712
- After delivery, JS sets `ivr.phase = 'offer'`, calls `checkAutoMessages()` after 2s
- IVR action `open_free_lesson` triggers `openFreeLesson()` in JS

### What needs to happen

1. **Wire `FLOSC_Free_Lesson_Manager` to use admin-configured count** — not hardcoded ONE. Use `FLOSC_Flow_Manager::get_setting()` with `lessons` override group
2. **Read free lesson config from per-flow settings** — use `FLOSC_Flow_Manager::get_setting()` with `lessons` override group, not raw `get_option()`
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

1. **Offers should load per-flow** — currently `get_all_offers()` reads global `flosc_offers` option. Use `FLOSC_Flow_Manager::get_setting()` with `offers` override group
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
2. **Stripe credentials read globally** — `FLOSC_Payment_Provider::get_setting()` calls `get_option('flosc_stripe_...')`. Override `get_setting()` to use `FLOSC_Flow_Manager::get_setting()` with `payments` override group when `payments.use_global` is `false`
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
2. **Per-flow is the source of truth** — use `FLOSC_Flow_Manager::get_setting($option_name, $override_group, $key)` to respect the flow override system. Flows are in the `flosc_flows` option. SSO is the exception (`flosc_flow_<id>` separate option).
3. **The 3-layer CSS architecture must be respected** — layout in flosc-layout.css, theme/visuals in flosc-theme.css, chat presets in chat-style-*.css
4. **Keep existing function signatures** — don't break the REST API contract
5. **One-time login token system is working** — don't touch SSO or redirect logic
6. **Phase engine alignment** — PHP `determine_flosc_phase()` and JS `determinePhase()` must agree on phase logic
7. **Don't fire `do_action('wp_login')` in programmatic login** — WooCommerce/BuddyBoss will hijack the redirect

---

## EXPECTED DELIVERABLES

The deliverable is working code in `mvp_sprint/flosc_1_5_4/`. When we pull it, zip it, and deploy it, the following test should pass end-to-end:

1. I visit **flosc.ai** as a visitor
2. I take the **quiz** in the chatbot
3. I **create my account** (via Facebook or Google SSO) to see my score
4. The account is created and **redirects me back to flosc.ai**
5. My **score is shown clearly and beautifully** — much more visible and polished than the current presentation
6. I am **greeted warmly as a guest** with my nickname, with instructions for how to customize my profile and access my free exclusive lessons based on the quiz questions I missed
7. I receive my **free lesson(s)** — the admin-configured number, randomly selected from what I missed
8. I see an **immediate OTO and other offers** to upgrade and access all of the FLOSC member-level content — the CORRECT content defined by the FLOSC admin for this flow
9. I **purchase in sandbox mode** (Stripe test card `4242 4242 4242 4242`)
10. My account gets **access to the correct flow-specific member content** — which is also easily visible on my **my-account page** in WordPress

That is FLOSC. The deliverable is fully functioning code that takes users from visitor to member in-chat — cleanly, kindly, and beautifully.

**This is NOT a plan. This is NOT a report. This is working code in the v1.5.4 directory.**

---

## HOW TO READ THE CODE

The plugin follows a specific pattern:
- **`flosc.php`** is the main orchestrator — hooks, REST endpoints, phase detection, flow resolution
- **`admin/flosc-app.php`** is the frontend template that generates `FLOSC_CONFIG` and `FLOSC_USER` JavaScript objects
- **`assets/js/flosc-app.js`** is the chatbot frontend — handles quiz, login, offers, checkout, content display
- **`includes/`** contains modular PHP classes for each concern (SSO, sale, content, lessons, etc.)
- **`admin/`** pages render WordPress admin UI for configuring each module
- **IVR .md files** in `ai_configuration_files/` define phase-specific chatbot messages with actions

### Per-Flow Settings Architecture

**All flows are stored in a SINGLE `flosc_flows` wp_option** (not one option per flow). This is managed by `includes/class-flow-manager.php`.

Each flow has an `overrides` array that controls per-flow vs global settings:

```php
'overrides' => [
    'style' => ['use_global' => true],
    'ai' => ['use_global' => true],
    'email' => ['use_global' => true],
    'ai_knowledge' => ['use_global' => true],
    'offers' => ['use_global' => true],
    'payments' => ['use_global' => true],
    'lessons' => ['use_global' => true],
],
```

The `FLOSC_Flow_Manager::get_setting()` method handles the resolution:
- If `$flow['overrides'][$group]['use_global']` is `true` → reads from global `get_option()`
- If `false` → reads from `$flow['overrides'][$group][$key]`
- Always falls back to global if the per-flow key doesn't exist

**Exception: SSO settings** use a separate `flosc_flow_<flow_id>` wp_option with keys like `sso_google_enabled`, `sso_google_client_id`, etc.

### Flow Resolution

`get_current_flow()` in `flosc.php` resolves the active flow in this order:
1. `flosc_ivr` query var (WP rewrite rule)
2. Custom domain match against `$_SERVER['HTTP_HOST']`
3. Slug match against `$_SERVER['REQUEST_URI']`
4. Returns `null` if nothing matches

### Payment Provider Settings

`includes/sale/class-payment-provider.php` is the abstract base. Its `get_setting($key)` reads `get_option('flosc_' . $this->get_id() . '_' . $key)` — e.g. `flosc_stripe_publishable_key`. This needs to be overridden to use the flow manager's override system when `payments.use_global` is `false`.

### FLOSC_CONFIG Generation

`admin/flosc-app.php` lines ~610-687 builds the `window.FLOSC_CONFIG` object. Currently:
- `stripeKey` → `$providers['stripe']['config']['publishableKey']` (from provider's `get_client_config()`)
- `offers` → `array_values($offers)` (from `OfferManager::get_active_offers()`)
- `flowId` → `$current_flow['id']` if flow exists
- Other globals: `quizType`, `lessonsCategory`, `otoOfferId`, `tokenName`

### Bridge Data Manager

`includes/class-bridge-data-manager.php` hooks into both `flosc_quiz_completed` (priority 5) and `flosc_purchase_completed` (priority 10). It manages the bridge data state: quiz done → profile exists → paid. On quiz completion, it stores scoring results in user meta. On purchase, it calls `clear_bridge_state()`.

### Files NOT in the File Map but relevant

| File | Lines | Role |
|------|-------|------|
| `includes/class-flow-manager.php` | 628 | Flow CRUD, override resolution via `get_setting()` |
| `includes/sale/class-payment-provider.php` | 134 | Abstract base — `get_setting()` reads global options |
| `includes/class-bridge-data-manager.php` | 646 | Bridge state management, hooks quiz + purchase |
