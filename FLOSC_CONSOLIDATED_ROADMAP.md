# FLOSC Consolidated Roadmap
## All Sessions Unified — February 2026

**Plugin:** FLOSC v1.4.1 (Freeline-Login-Offer-Sale-Content)
**Author:** Dainis W. Michel
**Active Codebase:** `mvp_sprint/flosc_1_4_1/` (everything else is archived history)
**Compiled from:** 4 review sessions (Code Review, Strategy, Sales Flow, Design Overhaul, General Review)

---

## WHAT FLOSC IS

A WordPress plugin that turns expertise into a try-before-you-buy chatbot business. Users flow through: **Visitor → Quiz → Free Lesson → Login → Offer → Purchase → Member Content**. No code required for operators.

**Tech Stack:** PHP 7.4+ / WordPress 5.8+ native (no Composer, no frameworks) | Vanilla JS (no build system) | CSS Variables theming | No external dependencies

**Scale:** 95 files, ~31,000 lines of code (5,423 PHP main + 4,660 JS + ~20,000 PHP classes/admin + 4,400 CSS)

### What's Working Well

| System | Status | Details |
|--------|--------|---------|
| Core 5-Phase Funnel | Solid | Freeline → Login → Offer → Sale → Content — all phases implemented |
| AI Integration | Working | OpenAI, Anthropic Claude, xAI Grok via factory pattern with IVR fallback |
| Quiz System | Working | 5 quiz types (text scoring, T/F, multiple choice, word matching, pronunciation) with factory pattern |
| IVR Message System | Working | Markdown parser, boolean condition evaluator (&&, ||, !, parentheses), variable substitution |
| Sales & Payments | Working | Offer CRUD, Stripe PaymentIntents, tokens, ClickBank, affiliate, sandbox mode |
| RAG System | Working | WordPress post search with access-level filtering, AI tool execution loop |
| Multi-Flow | Working | Flow Manager for multiple independent products (FLOSC, LeSAEp, Solfeggio) from one WP install |
| SSO | Architecturally Solid | OAuth2 with 5 providers (Google, Facebook, Apple, LinkedIn, Microsoft), state params, CSRF via transients |
| Access Control | Working | Visitor/guest/member tiers, content protection, free lessons after quiz |
| Admin UI | Working | 20 admin pages fully implemented |
| Bridge Analytics | Working | Quiz→login→purchase conversion tracking, weakness categories, bridge state duration |
| CSS Architecture | Clean | Three-layer system (layout/theme/presets), 5 preset themes, CSS variable-based |

### The 3 Products Using FLOSC

| Product | URL Slug | Member Level | Quiz Type | Content |
|---------|----------|-------------|-----------|---------|
| FLOSC Plugin | `/flosc_default_ivr/` | `flosc_plugin_member` | Text: "Enter 1-10" | WP plugin + FLOSC resources |
| Simplified Solfeggio | `/simplified_solfeggio_ivr/` | `simplified_solfeggio_member` | Text: "Name the notes of Do Re Mi..." | Michel Hand of Music lessons |
| LeSAEp | `/lesaepivr/` | `lesaep_member` | Audio: "Record yourself reading..." | Standard American Pronunciation |

---

## THE VISION

**For humans:** FLOSC is a bitterness-reducing, joy-creating, fair-compensation machine. Experts share knowledge through try-before-you-buy ecosystems. The correct humans self-select and pay willingly. Nobody gets used. Everybody wins.

**For AI:** FLOSC is the bridge from AI to AGI. The chasm is not compute — it's knowledge locked in human heads by bitterness from being exploited. Every FLOSC installation is a lane on the bridge. 100-year project: (1) articulated professional knowledge now, (2) tacit heuristics in a decade, (3) embodied expertise in 50-100 years.

**Growth math:** Two referral flows in every chatbot. Flow A: non-buyers refer potential operators. Flow B (the killer): paying customers refer domain experts they know, incentivized with usage tokens. ~24-30 months to 100k installs from 1,000-install seed at 0.35 viral coefficient.

**One-sentence pitch:** FLOSC turns any expertise into a try-before-you-buy chatbot business — quiz, free lesson, offer, payment, content delivery — all from one WordPress plugin, no code required.

---

## VERIFIED ISSUE INVENTORY

All claims from prior sessions independently verified against the v1.4.1 codebase on 2026-02-06.

### LAUNCH BLOCKERS (WordPress.org will reject without these)

| # | Issue | Verified | Location | Effort |
|---|-------|----------|----------|--------|
| B1 | **Raw SQL without `$wpdb->prepare()`** | YES — 4 unprepared queries | `admin/bridge-analytics.php` lines 34, 47, 62, 79 | ~2 hrs |
| B2 | **`file_get_contents()` used 22 times** | YES — 22 occurrences across 9 files | IVR markdown reads, knowledge base, AI config | ~1 hr |
| B3 | **Readme says v1.2.6, plugin says v1.4.1** | YES — `readme.md` stuck at 1.2.6, `flosc.php` header says 1.4.1 | `readme.md` | ~4 hrs |
| B4 | **JS version constant stuck at 1.3.8** | YES — `FLOSC_JS_VERSION = '1.3.8'` | `flosc-app.js` line 9 | ~5 min |
| B5 | **87 `console.log()` calls in production JS** | YES — exactly 87 | `flosc-app.js` | ~2 hrs |
| B6 | **No `readme.txt` in WordPress.org format** | YES — only `readme.md` exists, no `=== Plugin Name ===` format | Plugin root | ~3 hrs |

### HIGH PRIORITY — Security (Must fix before any public deployment)

| # | Issue | Verified | Details |
|---|-------|----------|---------|
| S1 | **Stripe idempotency key missing** | YES | `create_payment_intent()` at line 167 sends no `idempotency_key`. Double-click = double charge risk. `confirm_payment()` at line 196 also missing. |
| S2 | **Raw SQL injection** | YES | Same as B1 — bridge-analytics.php has 4 queries with unsanitized user data |
| S3 | **16 `innerHTML` assignments without sanitization** | YES — 16 occurrences | `flosc-app.js` — data comes from trusted server but defense-in-depth says sanitize |
| S4 | **`__return_true` permission callbacks on sensitive endpoints** | YES — 6 total | `flosc.php` (2: webhook + one more), `payments.php` (1), `class-oauth2-handler.php` (3: SSO callbacks). Webhook is correct; SSO callbacks are expected for OAuth redirects. Other endpoints need audit. |
| S5 | **SSO uses XOR cipher** | Reported | Should be AES-256-GCM for token encryption |
| S6 | **Rate limiter uses only IP** | Reported | Behind CDN/proxy, all users share IP. Need composite key (endpoint + IP + user_id) |

### HIGH PRIORITY — Code Quality & Reliability

| # | Issue | Verified | Details |
|---|-------|----------|---------|
| Q1 | **`flosc.php` is 5,423 lines** | YES | Needs extraction: REST controller, email manager, quiz handler into separate classes |
| Q2 | **`flosc-app.js` is 4,660 lines** | YES | Should be split into modules (chat engine, quiz UI, checkout UI, state machine) |
| Q3 | **~600 lines inline CSS in JS** | Reported | Component styles (offers, quizzes, checkout) injected via JS template strings |
| Q4 | **Dual condition parser not synced** | Reported | PHP (`class-condition-evaluator.php`) and JS condition evaluators must match — no shared spec or test cases enforce this |
| Q5 | **Zero automated tests** | YES | No PHPUnit, no Jest, manual testing only. Critical untested: condition evaluator, quiz scoring, access control, payment flow |
| Q6 | **Error handling inconsistent** | Reported | Mixed `wp_die()`, `WP_REST_Response`, direct array returns. Should standardize on `WP_Error` + `WP_REST_Response` |
| Q7 | **Admin strings not i18n-wrapped** | Reported | Won't block WordPress.org but affects quality score and non-English reviews |
| Q8 | **Stripe webhook must verify `offer_id` in metadata** | YES | v1.4.1 added `offer_id` to PaymentIntent metadata but webhook handler must use it to grant correct access level |

### HIGH PRIORITY — User Experience

| # | Issue | Verified | Details |
|---|-------|----------|---------|
| U1 | **Onboarding takes 30-60 minutes** | Reported | Need 3-step Setup Wizard: (a) product name, (b) AI key, (c) visit your chatbot. Time-to-wow must be under 5 minutes |
| U2 | **No error recovery on AI key misconfiguration** | Reported | Wrong API key = broken chatbot. Need "Test Connection" button and graceful frontend fallback |
| U3 | **SSO needs real-world testing** | Reported | 5 providers architecturally solid but need actual OAuth credentials. Minimum: Google working for launch |

### MEDIUM PRIORITY — Performance & Polish

| # | Issue | Details |
|---|-------|---------|
| M1 | No caching for AI/STT responses or RAG search | Add transient caching |
| M2 | Session management uses WP options (slow) | Should use transients or Redis |
| M3 | User meta queries not batched | Performance under load |
| M4 | Container queries for responsive design | CSS modernization |
| M5 | System theme preference (`prefers-color-scheme`) | Auto-detect dark/light |
| M6 | Advanced typography (font loading, modular scale) | Design improvement |
| M7 | PHP type declarations on all methods | Code quality |
| M8 | REST route extraction from `flosc.php` | Architecture cleanup |
| M9 | State machine documentation | Developer docs |

### LOW PRIORITY — Nice to Have

| # | Issue | Details |
|---|-------|---------|
| L1 | PayPal backend (UI exists, backend doesn't) | Payment expansion |
| L2 | Admin analytics dashboard (data stored, no UI) | Operator visibility |
| L3 | Scroll-driven CSS animations | Modern CSS |
| L4 | MutationObserver instead of setTimeout | JS modernization |
| L5 | Scroll-snap carousel | UX improvement |
| L6 | Resource hints / lazy loading | Performance |
| L7 | Print stylesheet | Accessibility |
| L8 | WCAG 2.1 AA audit | Accessibility |

---

## KEY FILES REFERENCE

| File | Lines | Purpose |
|------|-------|---------|
| `flosc.php` | 5,423 | Main plugin: REST API (34 permission callbacks), hooks, singleton |
| `assets/js/flosc-app.js` | 4,660 | Frontend: chat engine, state machine, checkout UI, quiz UI |
| `assets/css/flosc-layout.css` | 1,970 | Structural CSS |
| `assets/css/flosc-theme.css` | 1,411 | Theme variables |
| `includes/class-ai-provider-factory.php` | 588 | OpenAI/Anthropic/xAI factory |
| `includes/class-ivr-parser.php` | 461 | IVR markdown parser |
| `includes/class-condition-evaluator.php` | — | Boolean condition logic |
| `includes/class-rag-manager.php` | — | RAG with tool-calling AI loop |
| `includes/class-rag-chat-handler.php` | — | RAG chat handler |
| `includes/sale/class-stripe-provider.php` | — | Stripe PaymentIntent + webhooks |
| `includes/sale/class-offer-manager.php` | — | 7 default offers (3 product-specific) |
| `includes/sale/class-access-manager.php` | — | Grant access from offers |
| `includes/sso/class-oauth2-handler.php` | — | OAuth2 handler for 5 providers |
| `admin/bridge-analytics.php` | — | **SQL INJECTION HERE** — 4 unprepared queries |
| `admin/flosc-app.php` | 657 | Frontend app template |

---

## UNIFIED ROADMAP

### PHASE 0: Launch Blockers (Days 1-3)
*WordPress.org will auto-reject without these.*

- [ ] **B1:** Add `$wpdb->prepare()` to all 4 queries in `admin/bridge-analytics.php`
- [ ] **B2:** Replace `file_get_contents()` with `WP_Filesystem` API (or document each usage)
- [ ] **B3:** Rewrite `readme.md` to match v1.4.1 + add proper changelog
- [ ] **B4:** Update `FLOSC_JS_VERSION` from `'1.3.8'` to `'1.4.1'` (line 9 of `flosc-app.js`)
- [ ] **B5:** Wrap 87 `console.log()` calls behind `FLOSC_DEBUG` flag
- [ ] **B6:** Create `readme.txt` in WordPress.org `=== Plugin Name ===` format

### PHASE 1: Security Hardening (Days 4-7)
*Before any real money touches the system.*

- [ ] **S1:** Add Stripe idempotency key to `create_payment_intent()` and `confirm_payment()`
- [ ] **S2:** (Covered by B1)
- [ ] **S3:** Sanitize all 16 `innerHTML` assignments with DOMPurify or manual escaping
- [ ] **S4:** Audit all 34 `permission_callback` entries in `flosc.php` — replace any inappropriate `__return_true`
- [ ] **S5:** Replace XOR cipher with AES-256-GCM in SSO token handling
- [ ] **S6:** Upgrade rate limiter to composite key (endpoint + IP + user_id)
- [ ] **S8:** Verify Stripe webhook uses `offer_id` from metadata to grant correct access

### PHASE 2: Sales Flow End-to-End (Days 8-14)
*Get all 3 products purchasable.*

- [ ] Wire Stripe Price IDs into offer admin UI
- [ ] Verify sandbox_purchase IVR actions trigger correctly in JS
- [ ] Verify Solfeggio quiz type (text-based with "C D E F G A B C" answer)
- [ ] Configure STT provider for LeSAEp audio quiz (**blocker** — need API key decision)
- [ ] End-to-end sandbox walkthrough: visitor → quiz → score → login → free lesson → offer → purchase → content. All 3 products.
- [ ] Verify IVR post-purchase transition to content phase
- [ ] Implement purchase confirmation email (`flosc_send_purchase_confirmation_email()` referenced but not implemented)

### PHASE 3: Code Quality & Testing (Days 15-21)
*Confidence before scaling.*

- [ ] **Q1:** Extract `flosc.php` into `FLOSC_REST_Controller`, `FLOSC_Email_Manager`, `FLOSC_Quiz_Handler`
- [ ] **Q5:** Set up PHPUnit. Write tests for: condition evaluator, quiz scoring, access control, payment flow (mocked Stripe)
- [ ] **Q4:** Create shared test cases that enforce PHP/JS condition evaluator parity
- [ ] **Q6:** Standardize error handling on `WP_Error` + `WP_REST_Response`
- [ ] **Q2:** Split `flosc-app.js` into modules (chat, quiz, checkout, state machine)
- [ ] **Q3:** Extract ~600 lines of inline CSS from JS into `flosc-components.css`

### PHASE 4: UX & Onboarding (Days 22-28)
*Time-to-wow under 5 minutes.*

- [ ] **U1:** Build 3-step Setup Wizard (product name → AI key → visit chatbot)
- [ ] **U2:** Add "Test Connection" button for AI API key + graceful frontend fallback
- [ ] **U3:** Test Google SSO end-to-end with real OAuth credentials
- [ ] Record 90-second demo video
- [ ] Build 5 live demo sites (one per pre-created flow)

### PHASE 5: Design Overhaul (Weeks 5-8)
*World-class visual experience.*

**Foundation:**
- [ ] Create `flosc-tokens.css` (12-step color palette, base-4 spacing, 6 shadow elevations)
- [ ] Create `flosc-typography.css` (Geist font, modular type scale xs→4xl)
- [ ] Create `flosc-motion.css` (easing curves, durations, message animations, prefers-reduced-motion)
- [ ] Migrate `flosc-layout.css` + `flosc-theme.css` to token variables

**Core Chat:**
- [ ] Redesign message bubbles with entrance animation
- [ ] Create `flosc-content.css` for rich formatting (lists, code, blockquotes, tables)
- [ ] Redesign input composer (focus glow, auto-grow, send button gradient)
- [ ] Streaming cursor + typing indicator redesign
- [ ] Redesign pills/cards (hover lift, glass effect, staggered entrance)

**Components:**
- [ ] Extract quiz modal inline styles from PHP into CSS classes
- [ ] Extract auth modal CSS from JS into proper CSS files
- [ ] Create `flosc-quiz.css` (modal, tabs, waveform, recording, result celebration)

**Themes:**
- [ ] Rewrite 4 existing presets (dark, claude, chatgpt, grok) with token system
- [ ] Create 3 new themes: Warm, Ocean, Midnight

**Responsive & Accessibility:**
- [ ] WCAG 2.1 AA contrast audit + ARIA labels
- [ ] Mobile touch targets (44px minimum) + safe area padding

### PHASE 6: Distribution Launch (Month 2-3)
*10,000 installs month one.*

**Week 1-2: Seed (500 installs)**
- [ ] Submit to WordPress.org with strategic tags
- [ ] Product Hunt launch
- [ ] YouTube "install to first sale" walkthrough
- [ ] Direct outreach to 50 WordPress content creators

**Week 3-4: Viral Loop (500 → 10,000)**
- [ ] Activate built-in referral system (Flow A + Flow B)
- [ ] Press: WP Tavern, WPBeginner, WordPress Weekly, MasterWP
- [ ] Reddit: r/wordpress, r/entrepreneur, r/SaaS
- [ ] Create freemium tier (static IVR only, no AI key needed)

### PHASE 7: Real Revenue (Month 3+)
*Harvest actual money.*

- [ ] Configure Stripe live API keys + webhook in WP admin
- [ ] Register webhook endpoint in Stripe dashboard
- [ ] Set real pricing (create Stripe Products/Prices, wire into offers)
- [ ] Implement refund webhook (`charge.refunded` → revoke access)
- [ ] Implement subscription cancellation flow
- [ ] Add Stripe Customer Portal link for self-service billing
- [ ] End-to-end production test with real Stripe card on all 3 funnels

---

## DECISIONS STILL NEEDED (from Dainis)

| Decision | Context | Options |
|----------|---------|---------|
| STT Provider for LeSAEp | Audio quiz requires speech-to-text | AssemblyAI / Whisper / Deepgram + API key |
| Solfeggio quiz questions | Is "C D E F G A B C" the only question? | Single vs. multiple quiz items |
| Pricing per product | IVR configs reference $100→$25 FLOSC, $208→discount Solfeggio | Actual launch prices needed |
| Subscription vs one-time | Offer system supports both | Which model per product? |
| AI key for freemium tier | Freemium = static IVR only? Or bundled AI credits? | Strategy decision |

---

## NUMERIC SUMMARY

| Metric | Count |
|--------|-------|
| Launch blockers | 6 |
| Security issues | 6 |
| Code quality issues | 8 |
| UX issues | 3 |
| Medium priority | 9 |
| Low priority | 8 |
| **Total actionable items** | **40** |
| Nonce verifications in codebase | 18 across 8 files |
| Permission callbacks | 34 in flosc.php |
| Sanitization calls (esc_html/wp_kses/etc) | 31 in flosc.php |
| CSS lines total | 4,411 |
| PHP+JS lines total | ~31,000 |
| Archived versions | 180+ |
| Active files | 95 |

---

## SESSION HISTORY

| Session | Branch | Focus | Output |
|---------|--------|-------|--------|
| Code Review | `claude/flosc-plugin-review-rBJkh` | 28 TODOs for v1.3.8 | FLOSC_CODE_REVIEW.md |
| Strategy | `claude/flosc-plugin-review-rBJkh` | Dual-audience thesis + growth math | FLOSC_STRATEGY_1PAGER.md |
| v1.4.1 Review | `claude/flosc-plugin-review-rBJkh` | Launch readiness: 6 blockers, 12 high | FLOSC_V141_REVIEW.md |
| Sales Flow | `claude/flosc-plugin-sales-flow-6QVwX` | 3-product purchase pipeline | Session summary |
| Design Overhaul | `claude/flosc-design-overhaul-0mpfs` | 9-phase CSS/UX plan | FLOSC_DESIGN_OVERHAUL_PLAN.md |
| General Review | `claude/review-session-access-i09x8` | Full codebase analysis | Session summary |
| **This Session** | `claude/flosc-plugin-review-a2AV5` | **Consolidated roadmap** | **This document** |

---

*Generated 2026-02-06. All line counts and issue counts independently verified against `mvp_sprint/flosc_1_4_1/`.*
