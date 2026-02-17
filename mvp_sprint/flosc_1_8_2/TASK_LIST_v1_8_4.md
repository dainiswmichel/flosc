# FLOSC v1.8.4 Task List — Comprehensive Bug & Fix Review

**Date:** 2026-02m-13d  
**Working directory:** `mvp_sprint/flosc_1_8_2/`  
**Live version:** v1.8.1 at flosc.ai / dainis.net  
**Local commits (not pushed):** v1.8.2, v1.8.3  
**Purpose:** Review document for peer coding assistants before any code is written  

---

## PART A: v1.8.3 Changes Already Committed (Peer Review Needed)

These were coded in commit `9285150`. Need review before pushing.

---

### A1. OTO Dropdown Shows "No OTO" Despite Active Offers

**File:** `admin/lessons.php`  
**Before (v1.8.1):** `$offers = flosc()->sale()->offers()->get_all_offers()` — called without `flow_id`, queries global offers which is typically empty because offers are stored per-flow in the `flosc_flow_flosc_default_ivr` option.  
**After (v1.8.3):** `$offers = flosc()->sale()->offers()->get_all_offers($flow_id_for_offers)` where `$flow_id_for_offers = str_replace('flosc_flow_', '', $settings_key)`.  
**User sees:** OTO dropdown now shows actual offers instead of "No OTO."  
**Risk:** Low. Same pattern used elsewhere. Verify `$settings_key` resolves correctly for the active flow.

---

### A2. Redundant "Protected Category" Selector Removed

**File:** `admin/lessons.php`  
**Before:** Two dropdowns — "Lessons Category" AND "Protected Category" — both controlling which WP category holds the lessons. The lessons category IS the protected category; having both was confusing and could cause mismatch.  
**After:** Only "Lessons Category" remains. Content protection derives its category from the same lessons_category setting.  
**Risk:** Low. One source of truth is correct. Verify that `class-content-protection.php` correctly reads the same `lessons_category` setting and doesn't expect a separate `protected_category` key.

---

### A3. STT Configuration Moved from Lessons Tab to AI Tab

**File (removed from):** `admin/lessons.php`  
**File (added to):** `admin/ai-configuration.php`  
**Before:** Speech-to-text provider config (AssemblyAI, Whisper, Deepgram, Custom) was displayed under the Lessons tab — wrong conceptual location.  
**After:** STT config appended to the AI Configuration tab.  
**Risk:** Low. Verify settings save/load correctly under the new tab — check that the form's `$settings_key` still maps to the correct option. If STT settings were saved under a different option key on the Lessons tab, they might not be found on the AI tab.

---

### A4. Per-Post Protection — Changed from Checkbox to 4-Tier Radio

**File:** `flosc.php` (meta box), `includes/class-content-protection.php`  
**Before:** Binary checkbox: "Make this post public" → `_flosc_public_post = yes`.  Since v1.4.3 this was the per-post override.  
**After:** 4-option radio in the post editor:
- **Protected** (default) — normal category protection
- **Title + Excerpt** — show title/excerpt, hide full content
- **Title + Content (Read More)** — show content up to `<!--more-->` tag
- **Full Post** — completely public

Stores `_flosc_protection_mode` in post meta. Backward compatible: `_flosc_public_post = yes` still honored as fallback.

**Changes in `class-content-protection.php`:**
- `get_post_visibility()` checks `_flosc_protection_mode` first, maps to teaser/preview/public
- `user_can_access()` checks `_flosc_protection_mode === 'full'` as always-accessible
- `hide_protected_from_public_queries()` updated meta_query to include `_flosc_protection_mode != 'protected'`

**Risk:** Medium. This changes existing behavior. Verify:
1. Existing posts with `_flosc_public_post = yes` still work (backward compat)
2. New posts default to "protected" (not accidentally public)
3. The `hide_protected_from_public_queries` meta_query handles posts that have NO `_flosc_protection_mode` meta (brand new posts) — a `!=` comparison on a non-existent meta key may behave unexpectedly in WP_Query

---

## PART B: Active Bugs — Free Lessons Not Working

---

### B1. CRITICAL: `flosc_quiz_completed` Never Fires After Visitor Login

**The root cause of "guests don't get their free lessons."**

**Files:**
- `flosc.php` L635-656 (`handle_user_login()`)
- `flosc.php` L741-774 (`store_quiz_score()`)
- `flosc.php` L5186-5217 (`process_prelogin_data_for_user()`)
- `includes/class-free-lesson-manager.php` L38 (hooks into `flosc_quiz_completed`)

**User journey (the intended flow):**
1. Visitor takes quiz → score = 70%
2. Score stored in signed cookie `flosc_prelogin_score` (flosc.php L291)
3. Login gate appears → visitor creates account or logs in
4. `handle_user_login()` fires (L635) → calls `store_quiz_score($user->ID, $score_data)` (L645)
5. Free Lesson Manager picks up `flosc_quiz_completed` → selects lessons → grants guest access
6. User clicks "View my free lesson!" → REST `/free-lesson` → returns lesson content

**What actually happens:**
- Step 4: `store_quiz_score()` (L741) **ONLY** stores user meta (`_flosc_last_quiz_score`, `_flosc_last_quiz_data`, etc.) — it **NEVER fires** `do_action('flosc_quiz_completed', ...)`.
- Step 5: **Never happens.** Free Lesson Manager's `handle_quiz_completion()` is never called.
- Step 6: `/free-lesson` endpoint calls `deliver_free_lesson()` → `get_free_lessons()` → reads `_flosc_free_lesson_numbers` user meta → **EMPTY** → returns 404 "No free lesson available."

**The same gap exists in** `process_prelogin_data_for_user()` (L5186) — also stores score meta without firing the action.

**Fix needed:** After `store_quiz_score()` in `handle_user_login()`, fire:
```php
do_action('flosc_quiz_completed', $score_data, $user->ID);
```

**Note:** The `$score_data` from the cookie may have `correct: []` and `incorrect: []` as empty arrays (see B2 below), so the fix must also account for the data format mismatch.

---

### B2. `get_missed_lessons()` Ignores `incorrect` Key — Breaks for Pronunciation Quiz

**File:** `includes/class-free-lesson-manager.php` L100-130

**What `get_missed_lessons()` expects:**
```php
$user_answer = $quiz_result['user_answer'] ?? '';    // "4,7,9" (comma-separated numbers)
$correct_answer = $quiz_result['correct_answer'] ?? '1,2,3,4,5,6,7,8,9,10';
```
It parses both as comma-separated numbers and finds which numbers are missing.

**What quiz paths actually send:**

| Quiz Path | `user_answer` | `correct_answer` | `incorrect` | Result |
|---|---|---|---|---|
| External quiz (L280) | NOT SET | NOT SET | `[]` (empty) | Defaults correct_answer to 1-10, user_answer to '' → all 10 missed. **Works but inaccurate.** |
| REST quiz submit (L4218) | NOT SET | NOT SET | Array of numbers | Same as above — all 10 missed. **Works but inaccurate.** |
| Pronunciation quiz (L4762) | Raw text (speech) | Raw text (expected) | Array of missed items | `is_numeric` filter empties both → `$correct_numbers = []` → `$missed = []` → **NO LESSON ASSIGNED.** |
| Cookie replay after login (B1) | NOT SET | NOT SET | `[]` (empty) | Same as external quiz — all 10 missed. **Would work IF B1 is fixed.** |

**Critical failure:** Pronunciation quiz users NEVER get free lessons because `get_missed_lessons()` returns empty. The `incorrect` and `missed` keys ARE populated in the quiz result but are completely ignored.

**Fix needed:** `get_missed_lessons()` should check `$quiz_result['incorrect']` or `$quiz_result['missed']` first. Only fall back to the comma-separated parsing if those keys are empty.

---

### B3. Latent Fatal Error: `$access_manager->get_level()` Does Not Exist

**Files:**
- `includes/class-lesson-manager.php` L258: `$level = $access_manager->get_level($user_id);`
- `includes/sale/class-access-manager.php` — has NO `get_level()` method

**What happens:**
```php
// lesson_manager->user_can_access():
$access_manager = flosc()->sale()->access();  // FLOSC_Access_Manager instance

if ($access_manager->has_feature($user_id, 'all_lessons')) {
    return true;  // ← Members with 'all_lessons' feature hit this, never reach get_level()
}

$level = $access_manager->get_level($user_id);  // ← FATAL: undefined method
```

**Impact:** Any user who does NOT have the `all_lessons` feature (e.g., misconfigured custom offer, imported user, or edge case) would trigger a PHP fatal error when attempting to access a lesson via `/lessons/{id}`.

**Why it's masked:** All sample offers include `'features' => ['all_lessons', ...]`, so sandbox and properly configured purchases grant this feature. The `has_feature()` check returns true before reaching `get_level()`.

**Fix needed:** Either:
- Add `get_level()` to `FLOSC_Access_Manager` (reading level from `_flosc_access` meta)
- Or replace the call with `FLOSC_Member_Access::instance()->has_level()` / `is_member()` which DO exist

---

## PART C: Profile Bar Still Smushed/Clipped

---

### C1. Profile Bar Icon Clipping

**File:** `assets/css/flosc-layout.css`

**Layout structure:**
```
.flosc-sidebar (height: 100%, overflow: hidden, flex-direction: column)
  ├── .sidebar-header
  ├── .flosc_app_session_list (flex: 1, min-height: 0, overflow-y: auto)
  └── .user-profile-bar (margin-top: auto, flex-shrink: 0, padding: 12px)
        └── .profile-button (padding: 10px)
              ├── .profile-avatar (36x36px)
              ├── .profile-info (flex: 1)
              └── .dropdown-icon (16x16px)
```

**Relevant CSS:**
- `.flosc-sidebar` → `overflow: hidden` (L155)
- `.user-profile-bar` → `flex-shrink: 0`, `padding: 12px`, `padding-bottom: max(12px, env(safe-area-inset-bottom))` (L442-455)
- `.profile-avatar` → `width: 36px; height: 36px; flex-shrink: 0` (L486-494)
- Admin bar offset: `.admin-bar .flosc-app` → `height: calc(100dvh - 32px)` (L126-131)

**Possible causes (need browser inspection to confirm):**
1. The sidebar's `overflow: hidden` clips the bottom of the profile bar even though `flex-shrink: 0` should prevent compression. On short viewports or with admin bar, the available height decreases and the flex container may not have room for everything.
2. Missing `min-height` on `.user-profile-bar` — `flex-shrink: 0` prevents the flex item from shrinking below its content size, but if the content's intrinsic size is computed smaller than expected, the avatar could clip.
3. The `padding-bottom: max(12px, env(safe-area-inset-bottom))` CSS function — some browsers may not support `max()` inside `padding-bottom`, causing 0 bottom padding → avatar partially hidden.

**Needs:** Browser DevTools inspection to see exactly which box is clipped and whether it's a flex, overflow, or padding issue. Screenshots from the user showed the avatar circle being cut off at the bottom of the sidebar.

---

## PART D: Architecture Observations for Reviewers

---

### D1. Dual Access Systems — `FLOSC_Member_Access` vs `FLOSC_Access_Manager`

The codebase has TWO separate systems tracking member access:

**`FLOSC_Member_Access`** (`includes/class-member-access.php`):
- Uses `_flosc_member_access` user meta (true/false)
- Uses `_flosc_memberlevel_{level}` user meta
- Has `is_member()`, `has_level()`, `grant_member_access()`

**`FLOSC_Access_Manager`** (`includes/sale/class-access-manager.php`):
- Uses `_flosc_access` user meta (structured array with features, offers, subscription)
- Has `is_member()`, `has_feature()`, `grant_from_offer()`

**Both are used simultaneously.** The sandbox purchase calls BOTH:
```php
$member_access->grant_member_access($user_id, [...]);     // sets _flosc_member_access
$access_manager->grant_from_offer($user_id, $offer, ...); // sets _flosc_access.features
```

**Different parts of the codebase check different systems:**
- `lesson_manager->user_can_access()` → uses `FLOSC_Access_Manager` (has_feature, get_level)
- `content_protection->user_can_access()` → uses `FLOSC_Member_Access` (has_guest_access, has_level, is_member)
- `determine_flosc_phase()` → uses `FLOSC_Access_Manager` (is_member)

This is fragile. If one system is updated and the other isn't, access checks silently fail.

---

### D2. `resolve_category()` Exists in TWO Places with Different Logic

**`class-lesson-manager.php` L30-62** — `resolve_category()`:
1. `get_option('flosc_lessons_category', '')` (global, likely empty)
2. IVR file scan → construct option key from filename → read `lessons_category`

**`class-free-lesson-manager.php` L140-150** — `find_lesson_post()`:
1. `flosc()->get_current_flow()['lessons_category']` (runtime flow, most reliable)
2. `get_option('flosc_lessons_category', '')` (global)
3. IVR file scan (same as lesson_manager)
4. Hardcoded slug fallbacks (`flosc-sample-data`, etc.)
5. Last resort: all posts with `_flosc_lesson_number` meta

The free-lesson-manager was fixed in v1.8.2 to try `get_current_flow()` first. But `lesson_manager->resolve_category()` was NOT updated — it still doesn't try `get_current_flow()`. This means `get_all_lessons()` (used by the `/lessons` endpoint for members) relies on the IVR-scan fallback, which is less reliable than the runtime flow.

---

## Summary: Priority Order

| # | Issue | Severity | User Impact |
|---|---|---|---|
| B1 | `flosc_quiz_completed` never fires after login | **CRITICAL** | No visitor ever gets free lessons → entire funnel broken |
| B2 | `get_missed_lessons()` ignores `incorrect` key | **HIGH** | Pronunciation quiz users never get free lessons |
| C1 | Profile bar avatar clipped | **MEDIUM** | Visual: avatar cut off in sidebar |
| B3 | `get_level()` undefined method | **LOW (latent)** | Fatal crash only for users without `all_lessons` feature |
| D2 | `resolve_category()` inconsistency | **LOW** | Members might not load lesson list if IVR scan fails |
| A1-A4 | v1.8.3 changes | **Review needed** | Already committed, need verification |
