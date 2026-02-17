# FLOSC v1.7.9 — Testing & Verification Checklist

**Date:** 2026-02m-14d  
**Location:** `mvp_sprint/flosc_1_7_9/`  
**Zip:** `mvp_sprint/flosc_1_7_9.zip` (455 KB)

---

## What Changed in v1.7.9

1. **IVR pricing** — Hardcoded `$25`/`$100` replaced with `{price}` and `{discount_price}` template variables
2. **Offer timing** — New `quiz_results_shown` context flag ensures offers appear AFTER quiz results
3. **Visitor bar** — Dismissible engagement banner for non-logged-in users with admin-configurable text
4. **Autoprompt conditions** — Purchase CTA (line 440 in IVR) gated by `!user_has_access` so it hides post-purchase

---

## Quick File Checks (No Browser Needed)

Run these from the `flosc_1_7_9/` directory:

```bash
# 1. Version bumped correctly
grep "Version:" flosc.php | head -1
# Expected: Version: 1.7.9

# 2. No hardcoded $25/$100 in IVR (should return 0 lines)
grep -n '\$25\|\$100' ai_configuration_files/flosc_default_ivr.md
# Expected: NO output (all replaced with {price}/{discount_price})

# 3. Template variables present in IVR
grep -n '{price}\|{discount_price}' ai_configuration_files/flosc_default_ivr.md
# Expected: Lines 110, 364, 365, 371, 377, 379, 408

# 4. Visitor bar HTML exists
grep -n 'flosc-visitor-bar' admin/flosc-app.php
# Expected: Lines around 309-315

# 5. Quiz results flag exists in JS
grep -n 'quiz_results_shown' assets/js/flosc-app.js
# Expected: 2 references — line 58 (context array) and line 1106 (flag set to true)

# 6. Offer condition uses quiz_results_shown
grep -n 'quiz_results_shown' ai_configuration_files/flosc_default_ivr.md
# Expected: Line 391 — "MessageConditions: is_guest && quiz_taken && quiz_results_shown"

# 7. Post-purchase CTA gated
grep -n 'user_has_access' ai_configuration_files/flosc_default_ivr.md
# Expected: Line 440 — "MessageConditions: is_guest && !user_has_access"
```

---

## Browser Tests (Priority Order)

### Test 1: Visitor Bar (5 min)
- Load FLOSC page while **logged out**
- Visitor bar should slide down after ~2 seconds
- Default text: "Try our free quiz to assess your skills! 🎯"
- Click dismiss (×) — bar disappears
- Refresh page — bar should NOT reappear (sessionStorage)
- Close browser, reopen — bar should show again (new session)
- Click "Start Quiz" button — should trigger quiz start

### Test 2: Quiz → Offer Timing (5 min)
- Start quiz as guest (not logged in)
- Complete all 10 questions
- **CRITICAL:** Quiz results (score percentage, breakdown) display FIRST
- **CRITICAL:** Offer card appears AFTER results, not before or simultaneously
- Open browser console: look for flag being set after results render

### Test 3: Pricing Variables (3 min)
- Go to WordPress admin → FLOSC → Product settings
- Set price to any value (e.g., `$199`) and discount price (e.g., `$49`)
- Load chat as guest, trigger the offer (take quiz first)
- **VERIFY:** Offer displays YOUR configured prices, not hardcoded `$25`/`$100`

### Test 4: Post-Purchase Autoprompts (3 min)
- Log in as a member (or complete purchase)
- **VERIFY:** No "Get full access now" pill/CTA shows for members
- **VERIFY:** Member-appropriate pills appear instead (e.g., "Browse all lessons", "Continue where I left off")

### Test 5: Lessons (2 min)
- As logged-in member, click "Browse all lessons" autoprompt
- **VERIFY:** Lessons render inline in chat (NOT navigating to `/lessons/` URL)
- **VERIFY:** No 404 errors
- Click a lesson title — content displays inline

---

## Expected Behavior Summary

| Feature | Before v1.7.9 | After v1.7.9 |
|---|---|---|
| Offer pricing | Hardcoded $25/$100 | Dynamic `{price}`/`{discount_price}` from admin settings |
| Quiz → Offer order | Offer could appear before results | Results ALWAYS render first (gated by `quiz_results_shown`) |
| Visitor engagement | Nothing for logged-out users | Dismissible banner with quiz CTA |
| Post-purchase CTAs | "Get full access" still visible to buyers | Hidden via `!user_has_access` condition |
| Lessons | Works (inline in chat) | Works (no changes in v1.7.9) |

---

## Red Flags — If You See These, Something Is Wrong

- ❌ Offer appears BEFORE quiz results → `quiz_results_shown` flag not being checked
- ❌ Offer shows `$25`/`$100` → template variable substitution failing in PHP
- ❌ Visitor bar never appears → JS `initVisitorBar()` not firing or element missing
- ❌ Visitor bar reappears after dismiss on same page refresh → sessionStorage key broken
- ❌ "Get full access" pill shows for members → condition evaluator not reading `user_has_access`
- ❌ Clicking lessons navigates to `/lessons/` URL → inline rendering method not connected
- ❌ Console shows uncaught errors → JS regression

---

## Pass Criteria

- ✅ All 7 file checks return expected output
- ✅ All 5 browser tests pass
- ✅ No JavaScript console errors
- ✅ No PHP warnings in `wp-content/debug.log`
- ✅ Pricing reflects admin-configured values, not hardcoded amounts
- ✅ Quiz results always precede offers
- ✅ Visitor bar dismisses and stays dismissed within session

---

## Deployment

```
File: mvp_sprint/flosc_1_7_9.zip (455 KB)
Install: WordPress → Plugins → Add New → Upload Plugin
Changelog: mvp_sprint/flosc_1_7_9/CHANGELOG_1_7_9.md
```
