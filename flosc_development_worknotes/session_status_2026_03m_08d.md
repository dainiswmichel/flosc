# FLOSC v8.0.0 — Code Audit & Bug Fix Report
## 2026-03m-08d

---

## Summary

Three rounds of code-level auditing were performed across FLOSC v8.0.0, tracing the complete visitor journey: Freeline → Login → Offer → Sale → Content. Rounds 1 and 2 were deployed in prior sessions. Round 3 (this session) found 3 real bugs, all fixed and deployed.

---

## Bugs Found & Fixed (Round 3)

### Bug 1: Cascading Auto-Messages Not Firing

**File:** `assets/js/flosc-app.js` — `_checkAutoMessagesNow()`

**Problem:** After login, `lesaep_login_success` (the "Welcome back!" message) shows correctly, but `lesaep_offer` (the featured offer card) never appears until the 30-second inactivity timer fires. This is because `_checkAutoMessagesNow()` uses `break` after showing each message — only ONE auto-message fires per call.

**Root cause:** The function iterates all eligible auto-messages, shows the first match, then `break`s. There was no mechanism to re-check for the next matching message after the first one renders.

**Fix:** After each `showIVRMessage` call (both the `'always'` branch and the `evaluateCondition` branch), added:
```js
setTimeout(() => this.checkAutoMessages(), 1500);
```
This schedules a re-check 1.5 seconds after the first message renders. The re-check loop skips already-shown messages (via `this.ivr.shownThisSession[msg.name]`) and finds the next match.

**Why it can't infinite-loop:** Every shown message is marked in `shownThisSession`. The cascade re-check skips marked messages. The pool of eligible messages is finite (typically 2-4 per phase). Once all matches are shown, the loop exits with no `break` trigger and no new cascade scheduled. Additionally, `checkAutoMessages()` has a 50ms debounce — if something else calls it within the 1.5s window, the cascaded call is replaced, not duplicated.

**What the user sees differently:** After logging in, the welcome message appears, then ~1.5 seconds later the offer card slides in. Previously, the offer wouldn't appear for 30 seconds (or not at all if the user interacted first).

---

### Bug 2: guest_upgrade Pill Action Mismatch

**File:** `ai_configuration_files/lesaep_ivr.md` — `guest_upgrade` message

**Problem:** The "Upgrade to access all lessons" pill had `Action: show_offer_full_access`. When clicked, this triggered `performIVRAction('show_offer_full_access')`, which matched the `show_offer_` prefix handler at line 2340 of flosc-app.js, extracting offer ID `full_access`. But no offer with ID `full_access` exists — the actual offer is `lesaep_full`.

**Fix:** Changed `Action: show_offer_full_access` to `Action: checkout_lesaep_full`. This routes through the `checkout_` prefix handler at line 2333, extracting offer ID `lesaep_full`, and calls `openCheckout('lesaep_full')` which correctly loads the PayPal subscription modal.

**Verification chain:**
1. IVR: `Action: checkout_lesaep_full`
2. IVR parser (class-ivr-parser.php line 207): stores `action = 'checkout_lesaep_full'`
3. Migration re-parse: stores `action` in DB pill data
4. admin/flosc-app.php (line 840-843): reads `$p['action']`, sets `_trigger_type = 'action'`, `_trigger_value = 'checkout_lesaep_full'`
5. JS pill render (line 872): outputs `data-action="checkout_lesaep_full"`
6. JS click handler (line 932): calls `performIVRAction('checkout_lesaep_full')`
7. performIVRAction default case (line 2333): matches `checkout_` prefix, extracts `lesaep_full`, calls `openCheckout('lesaep_full')`
8. openCheckout (line 4660): finds offer, detects PayPal processor, calls `showPaymentModal('lesaep_full')`

---

### Bug 3: Pill Action Routing Defaulting to AI

**File:** `admin/flosc-app.php` — autoprompts config builder

**Problem:** Pills parsed from IVR with an `Action:` field but no explicit `TriggerType:` field were being assigned `trigger_type = 'ai'` (the default). This meant clicking the pill sent its label text ("Upgrade to access all lessons") to the AI chat handler instead of calling `performIVRAction()`.

**Root cause:** The autoprompt config builder defaulted every pill to `trigger_type = 'ai'` without checking whether the pill had an `action` field that implied it should be an action trigger.

**Fix:** Added logic at lines 837-843 of admin/flosc-app.php:
```php
$action = $p['action'] ?? '';
$explicit_trigger = $p['trigger_type'] ?? '';
$trigger_type  = $explicit_trigger ?: ($action ? 'action' : 'ai');
$trigger_value = $p['trigger_value'] ?? ($action ?: '');
```
If a pill has an `action` but no explicit `trigger_type`, it auto-routes as `trigger_type = 'action'` with `trigger_value` set to the action string.

---

### Supporting Fix: One-Time IVR Re-Parse Migration

**File:** `flosc.php` — new block at line 252

**Problem:** The DB-stored autoprompt pills still contained the old `show_offer_full_access` action from the pre-fix IVR file. The IVR file was updated, but the DB wasn't re-synced because FLOSC_VERSION (8.0.0) equals `flosc_last_flushed_version` (8.0.0), so the version-gated migration block doesn't fire.

**Fix:** Added a one-time migration OUTSIDE the version-gated block:
```php
if (!get_option('flosc_ivr_reparse_800')) {
    add_action('init', function() {
        // Re-parse all IVR files → rebuild autoprompt pills in DB
        // ...
        update_option('flosc_ivr_reparse_800', true);
    }, 5);
}
```
This runs once on the first page load after deploy, re-parses all IVR files, writes the corrected pills (with `checkout_lesaep_full`) to the WordPress options table, then sets a flag so it never runs again.

**Why it's safe:**
- `get_option()` is available at plugin-load time (same scope as the existing version-check migration at line 35)
- The `init` hook at priority 5 runs after WordPress core but before most plugins
- The flag option (`flosc_ivr_reparse_800`) is autoloaded, so subsequent page loads pay zero DB query cost
- The re-parse overwrites `autoprompts`, `ivr_messages`, and `ivr_phases` in the flow settings — this is the same data the v5.0.0 migration originally wrote

---

## Bugs Investigated & Disproved

The following potential issues were traced through the code and confirmed NOT to be bugs:

### first_message_after_login context flag
**Concern:** Does `first_message_after_login` get lost between login redirect and page load?
**Traced:** WordPress transient `flosc_just_logged_in_{user_id}` is set during `handle_user_login` (line 924), read in `enqueue_flosc_assets` (line 2432), passed as `justLoggedIn: true` to JS config (line 2473). The transient survives the redirect. JS reads `config.justLoggedIn` in `buildIVRContext()` and sets `first_message_after_login: true`. **Working correctly.**

### Quiz audio data loss during registration  
**Concern:** Do visitor audio recordings survive the registration process?
**Traced:** During quiz, audio files upload to WordPress via `/store-visitor-audio` REST endpoint → stored in `wp-content/uploads/flosc-temp/{visitor_temp_id}/`. Cookie `flosc_visitor_temp_id` persists through registration. `handle_user_registration` (line 943) reads cookie → calls `score_visitor_audio()` → API scores all phrases → `store_quiz_score()` saves to user meta → page reload reads quiz data from user meta. **Working correctly.**

### Post-login context flag overwrite
**Concern:** `checkPendingQuizResults()` runs before `buildIVRContext()` in the init sequence — does `buildIVRContext` overwrite the quiz flags?
**Traced:** The offer condition is `!is_member && quiz_taken`, not `quiz_results_shown`. `quiz_taken` is set from `config.justCompletedQuiz` which comes from the server transient, independent of `buildIVRContext`. **Working correctly.**

### Member content access after purchase
**Concern:** Does `openLessonLibrary()` work after subscription activation?
**Traced:** `onApprove` callback → REST `/paypal/activate-subscription` → `grant_from_offer()` merges features including `all_lessons` → `is_member()` returns true → `fetchAllLessons()` → REST `/lessons` → `user_can_access()` checks `has_feature('all_lessons')` → returns lessons. **Working correctly.**

### PayPal SDK availability check
**Concern:** What happens if PayPal SDK fails to load?
**Traced:** `showPaymentModal()` checks `if (typeof paypal === 'undefined')` before rendering buttons. If SDK is missing, it shows an error message asking the user to disable ad blockers. **Working correctly.**

---

## Previously Deployed Fixes (Rounds 1-2)

These were deployed in the prior session and are still in place:

1. **Post-purchase state update** (flosc-app.js): After PayPal `onApprove`, `this.state = 'member'` and `this.user.purchased = true` are set immediately so the UI updates without page reload.

2. **Featured offer IVR copy** (flosc-app.js): `showOfferFeatured()` now uses `msg.content` (the IVR MessageContent) rendered through markdown→HTML, instead of the generic offer description.

3. **Feature humanization** (flosc-app.js): Raw feature identifiers (`lesaep_lessons`, `pronunciation_exercises`) are now humanized to readable labels.

4. **Conditional guarantee** (flosc-app.js): The money-back guarantee line only appears if the offer has a `guarantee` field, not hardcoded on every offer.

---

## Files Modified (This Session, Deployed 2026-03m-08d)

| File | Change |
|------|--------|
| `assets/js/flosc-app.js` | Cascading auto-messages (2 `setTimeout` additions) |
| `ai_configuration_files/lesaep_ivr.md` | guest_upgrade action: `show_offer_full_access` → `checkout_lesaep_full`; offer block: `full_access` → `lesaep_full`, `card` → `featured`, new MessageContent |
| `admin/flosc-app.php` | Pill action routing: auto-detect `action` field → set `trigger_type = 'action'` |
| `flosc.php` | One-time IVR re-parse migration block (option-flagged, outside version gate) |

---

## What Cannot Be Verified Without Browser Testing

These were verified at the code level but require manual testing to confirm runtime behavior:

1. **Cascading timing feels right** — the 1.5s delay between login_success and offer card. If it feels too fast or too slow, adjust the `1500` in the `setTimeout`.

2. **Offer card renders correctly** — the `DisplayFormat: featured` with the new Dainis W. Michel copy. Need to confirm layout, PayPal buttons mount, plan selector works.

3. **PayPal subscription flow end-to-end** — `onApprove` → server activation → access grant → state update. The code is verified but the PayPal sandbox/live round-trip needs a real browser.

4. **IVR re-parse actually ran** — check `wp_options` table for `flosc_ivr_reparse_800 = 1` and `flosc_flow_lesaep_ivr` → `autoprompts` → guest pills should include `action: checkout_lesaep_full`.

---

## Deploy Command Used

```bash
rsync -avz --delete ~/2026/flosc/mvp_sprint/flosc_8_0_0/flosc/ chemicloud:~/public_html/wp-content/plugins/flosc/
```

Files transferred: `flosc.php`, `admin/flosc-app.php`, `ai_configuration_files/lesaep_ivr.md`, `assets/js/flosc-app.js`
