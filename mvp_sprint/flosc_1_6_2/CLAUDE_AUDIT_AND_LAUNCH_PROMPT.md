# FLOSC v1.6.2 — Audit, Fix, and Launch Acceleration

**Date:** 2025-02-11  
**Repo:** https://github.com/dainiswmichel/flosc.git  
**Branch:** main  
**Plugin path:** `mvp_sprint/flosc_1_6_2/`  
**Purpose:** FLOSC is a WordPress plugin that powers **LESAEP** — an AI-driven language learning platform with quiz → lesson → offer sales funnel, powered by an IVR (Interactive Voice Response) message pipeline and real-time chat.

---

## YOUR MISSION

You are auditing FLOSC v1.6.2 and then driving it to **launch-ready** status. This is NOT a code review for the sake of reviewing — this is about finding blocking bugs, dead code, incomplete wiring, and getting the plugin to the point where it can be deployed to production on a WordPress site and actually sell products to real users.

FLOSC releases the LESAEP product. We need to launch. Every session must produce shippable progress.

---

## PHASE 1: AUDIT (find real problems only)

Read every file. Focus on these questions:

### 1. Does the full user journey work end-to-end?

Trace the complete flow:
1. **Visitor lands on page** → FLOSC chat loads → freeline IVR messages appear (welcome, quiz prompt)
2. **User takes quiz** → score calculated → user redirected to login/register → quiz results stored
3. **Logged-in user** → login-phase IVR messages → free lesson delivered → offer triggered
4. **Offer displayed** → `showOfferMessage()` → `renderOfferByFormat()` → card/pill/banner shown
5. **User clicks CTA** → payment flow (Stripe/ClickBank) → `process_payment()` → access granted
6. **Post-purchase** → sale-phase messages → content delivery → ongoing member support

For each step, verify the code actually connects. Find any broken wires.

### 2. Are there dead/orphaned functions?

Check for:
- Functions defined but never called
- Event listeners attached to elements that don't exist
- PHP endpoints registered but never hit from JS
- CSS classes styled but never used in HTML/JS

### 3. Does the offer pipeline work?

This was heavily refactored in v1.6.2. Verify:
- `getOfferData(offerId)` correctly merges IVR message data + admin config.offers
- `showOfferMessage()` handles content sources (HtmlFile, WooProduct, PostID) before falling back to inline content
- `renderOfferByFormat()` switch covers all 7 formats and each format actually renders valid HTML
- Timer countdown works and respects per-format timer overrides
- `_loadOfferStates()` / `_saveOfferStates()` correctly persist to localStorage
- Error boundary in `renderOfferByFormat()` catches failures and shows plain-text fallback
- The REST endpoint `/wp-json/flosc/v1/offer-content` works for html/woo/post sources

### 4. Does the admin UI save and load correctly?

- **IVR Messages tab**: accordion editing, Save to DB, Save & Resync to File, Add new message per phase
- **Offers tab**: multi-format checkboxes (`display_formats` array), per-format condition/timer/overrides, save preserves all fields
- Do saved `display_formats` data round-trip correctly? (save → reload page → same checkboxes checked, same fields populated)
- Does backward compatibility work for old offers that only have `display_format` (singular)?

### 5. Security check

- All `$_POST`/`$_GET` inputs sanitized?
- Nonce verification on all form submissions?
- Capability checks (`current_user_can`) on all admin actions?
- File path traversal protection on HtmlFile content source?
- XSS in rendered offer HTML?

---

## PHASE 2: FIX (ship the fixes)

For every problem found in Phase 1:
1. State the problem clearly (file, line, what's wrong)
2. Fix it immediately — don't just list it
3. Run `php -l` and `node --check` after each fix
4. If a problem is cosmetic or low-priority, mark it as such and skip it

Priority order: **security > broken user journey > dead code > cosmetic**

---

## PHASE 3: LAUNCH READINESS CHECKLIST

After fixes, evaluate against this checklist and implement anything missing:

### Must-Have for Launch
- [ ] Chat loads without JS errors on a clean WordPress page
- [ ] Quiz flow completes (start → questions → score → results stored)
- [ ] User registration/login works after quiz
- [ ] At least ONE offer displays correctly after quiz completion
- [ ] Payment processing works for at least ONE provider (Stripe or ClickBank)
- [ ] Access level upgrades after successful payment
- [ ] Post-purchase content (lessons) is accessible
- [ ] Admin can create/edit/delete IVR messages
- [ ] Admin can create/edit offers with display formats
- [ ] Plugin activates without errors on WordPress 6.x
- [ ] No PHP warnings/notices in production (error_reporting check)
- [ ] REST API endpoints return valid JSON
- [ ] FLOSC_CONFIG passes correctly from PHP → JavaScript

### Nice-to-Have (don't block launch)
- [ ] All 7 offer display formats render correctly
- [ ] SSO providers work
- [ ] Email automation triggers
- [ ] Analytics/bridge tracking
- [ ] Companion widget

---

## KEY FILES (read these first)

| File | Lines | What It Does |
|------|-------|-------------|
| `flosc.php` | 6232 | Main plugin: REST API, IVR engine, chat handler, offer content endpoint |
| `assets/js/flosc-app.js` | 4340 | Frontend: chat UI, offer rendering, IVR message display, quiz integration |
| `admin/flosc-app.php` | 687 | Frontend HTML template, passes FLOSC_CONFIG to JS |
| `includes/class-ivr-parser.php` | 474 | Parses IVR markdown into structured config |
| `includes/sale/class-offer-manager.php` | 602 | Offer CRUD, display format registry, pricing |
| `admin/ivr-messages.php` | 1146 | IVR Messages admin — single scrollable page, accordion editors |
| `admin/offers.php` | 611 | Offers admin — multi-format config per offer |
| `admin/settings.php` | 819 | Main settings page, tab routing |
| `assets/css/flosc-offers.css` | ~620 | Offer display styles (card, pill, compact, banner, featured, text, checkout) |
| `ai_configuration_files/flosc_default_ivr.md` | ~600 | Default IVR message definitions |

### Architecture Overview

```
User Browser                 WordPress Server
─────────────                ─────────────────
flosc-app.js ←──REST API──→ flosc.php
  ├─ Chat UI                   ├─ handle_chat() → OpenAI
  ├─ IVR Engine                ├─ search_ivr_match()
  │  ├─ checkAutoMessages()    ├─ flosc_import_ivr_to_database()
  │  └─ showOfferMessage()     ├─ flosc_auto_export_ivr_to_file()
  ├─ renderOfferByFormat()     ├─ get_offer_content() REST endpoint
  └─ Quiz Integration          └─ process_payment()
                                   └─ grant_access_level()
```

### Data Flow: Offer Pipeline

```
Admin creates offer (offers.php)
  → saved to $flow_settings['offers'] in wp_options
  → display_formats: {card: {enabled:true, condition:'...'}, pill: {enabled:true, label:'...'}, ...}

IVR entries reference OfferID (ivr-messages.php or .md file)
  → MessageType: offer
  → OfferID: full_access_001
  → DisplayFormat: card (one IVR entry per format)

Frontend loads:
  → FLOSC_CONFIG.offers (from flosc-app.php line 662)
  → this.ivr.messages (from REST API /ivr-messages)

showOfferMessage(msgId) called:
  → getOfferData(offerId) merges IVR msg + config.offers
  → checks for content sources (HtmlFile/WooProduct/PostID)
  → calls renderOfferByFormat(offerData, format)
  → renders card/pill/banner/etc in chat
```

---

## WHAT NOT TO DO

- Don't refactor for style preferences. Ship what works.
- Don't add features. We're launching, not expanding.
- Don't rewrite working code. Only fix broken code.
- Don't create documentation files. Write code.
- Don't suggest changes — implement them.
- If something works but is ugly, leave it alone.

---

## OUTPUT FORMAT

Structure your work as:

1. **AUDIT FINDINGS** — numbered list of real problems (not style complaints)
2. **FIXES APPLIED** — for each finding, what you changed and in which file
3. **LAUNCH CHECKLIST STATUS** — go through each item, mark ✅ or ❌ with explanation
4. **REMAINING BLOCKERS** — what still needs to happen before this plugin can go live
5. **RECOMMENDED NEXT SESSION** — prioritized list of what the next coding session should tackle

Remember: the goal is LAUNCHING LESAEP. Every line of code should move us closer to users paying money for lessons.
