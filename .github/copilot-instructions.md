# FLOSC Copilot Instructions & AI Accountability Record

---

## ⚠️ AI TOOL INSTRUCTIONS — READ BEFORE EDITING

**This file uses the Michel Date Stamp Innovation format.**

**Rules:**
1. **ADDITIVE ONLY** — Never delete existing entries. Only add new entries above previous ones.
2. **REVERSE CHRONOLOGICAL ORDER** — Newest entries go at the TOP, immediately below this header section.
3. **DATE FORMAT** — Use Michel Date Stamp Innovation:
   - Standard: `YYYY-MMm-DDd` (e.g., `2026-02m-13d`)
   - With time: `YYYY-MMm-DDd-THHh:MMm:SSs`

---

## 2026-03m-14d — SSO Custom Domain Redirect Fix (PERMANENT REFERENCE)

### The Problem (recurring — broken/fixed 5+ times)

Google SSO callback redirects to `dainis.net` instead of `lesaep.com` after authentication.

### Root Cause

The OAuth2 callback endpoint runs on `dainis.net` (the WordPress install domain, registered with Google as the redirect URI). Inside the callback handler, `get_current_flow()` matches flows by `$_SERVER['HTTP_HOST']`. Since `HTTP_HOST = dainis.net` during the callback, it never finds the LeSAEp flow (which has `custom_domain = lesaep.com`). So `get_app_url()` falls through to `home_url()` = `dainis.net`.

### The Correct Solution

**Never use `get_current_flow()` or `get_app_url()` during REST API callbacks for redirect resolution.** The callback runs on dainis.net — host-based flow detection will always fail.

Instead, the `flow_id` is stored in the OAuth2 state (stored server-side, keyed by the state token). Use `resolve_app_url_from_flow_id($flow_id)` to look up the flow's custom domain directly from the `flosc_flow_{flow_id}` option in the database. The domain is stored under the key `domain` (not `custom_domain`).

**Flow of data:**
1. `flosc-app.php` passes `flow_id` (= IVR filename without extension, e.g., `lesaep_ivr`) into the `authUrl`
2. `handle_authorize()` stores it in state via `generate_state()`
3. `handle_callback()` reads `state_data['flow_id']`, calls `resolve_app_url_from_flow_id('lesaep_ivr')`
4. That reads `get_option('flosc_flow_lesaep_ivr')['domain']` = `lesaep.com`
5. Returns `https://lesaep.com/`

**The `redirect_to` in state** (set by JS as `window.location.href` = the page the user was on) is the PRIMARY redirect target. The flow-resolved URL is the FALLBACK when `redirect_to` is empty or missing.

### OPcache Warning

Shared hosting (ChemiCloud) OPcache caches PHP bytecode. After `scp` deploys, the web server may keep running old code until OPcache expires or is manually invalidated. CLI `opcache_reset()` does NOT affect the web server's OPcache (separate process pools). To flush web OPcache: either call `opcache_invalidate()` from within a web request, or touch/rename the PHP file to change its mtime.

The callback handler now calls `opcache_invalidate(__FILE__, true)` at the top of every request as a safeguard.

### Key Files

- `includes/sso/class-oauth2-handler.php` — `handle_callback()`, `resolve_app_url_from_flow_id()`
- `includes/sso/class-sso-manager.php` — `handle_sso_error_display()` (shows errors on frontend)
- `admin/flosc-app.php` — builds `authUrl` with `flow_id` param
- `assets/js/flosc-app.js` — `initiateSSO()` appends `redirect_to=<current URL>`
- `flosc.php` — `handle_login_token()` redeems cross-domain login tokens on lesaep.com

### Database Keys

- `flosc_flow_lesaep_ivr` option: contains `domain`, `sso_google_client_id`, `sso_google_client_secret`, `sso_google_enabled`, etc.
- `flosc_flows` option: contains flow registry with `custom_domain` (different from per-flow settings — don't confuse them)
- State transients: `flosc_sso_state_{token}` — also backed up to options table as fallback

### DO NOT

- Use `get_current_flow()` in REST callback handlers for redirect logic
- Use `get_app_url()` without passing a flow in REST callback handlers
- Use `home_url()` as the error/success redirect fallback
- Assume OPcache is flushed after scp deploy — always invalidate

---

## 2026-03m-01d — Workflow Rule

### Fix first, then wait before zipping

When fixing bugs or making changes, apply the code fix and explain it — but do **not** zip or version-bump until Dainis confirms the fix looks correct or explicitly asks for a zip. The workflow is: fix → wait for review/testing input → then zip when asked.

---

## 2026-03m-01d — Future Task

### Enrich Dainis W. Michel biographical information in IVR

The current "Who Is Dainis W. Michel" IVR message (`lesaep_ivr.md`, line ~99) only mentions English teaching and LeSAEp. It needs to be updated with more complete biographical information — singer-songwriter, "Time Flies", Tirradnis songwriting competition 2024 in Latvia, dainis.net, and other public facts Dainis wants included.

**Status:** Deferred. Dainis will provide the full biographical details to include when ready.

**Location:** `ai_configuration_files/lesaep_ivr.md` → `lesaep_about_dainis` message → `MessageContent` field.

---

## 2026-02m-28d — Vocabulary Note

### Remove "funnel" from the FLOSC ecosystem

The word **"funnel"** is being retired from all FLOSC code, UI, comments, docs, and marketing copy. Replace with **"flow"** everywhere.

- CSS classes: `.landing-funnel` → already renamed to `.landing-tagline` in v2.0.0
- PHP variables: `$funnel_complete`, `funnelCompleted`, `_flosc_funnel_completed` → TBD rename to flow equivalents
- REST endpoints: `/funnel-complete`, `/debug/funnel-state` → TBD rename
- Comments, descriptions, readme text → replace "funnel" with "flow"
- Plugin description: "sales funnel framework" → "sales flow framework"

This is a **future task** — not yet applied globally. When the rename happens, it must cover all ~40+ references across flosc.php, flosc-app.js, quiz.php, readme.md, lesson files, and create-sample-data.php.

---

## 2026-02m-28d — Identity Rename

### Name / Title / Tagline

v2.0.0 renames the identity fields:
- `name` stays `name` (e.g., "LeSAEp")
- Old `tagline` → now `title` (e.g., "Learn Excellent Standard American English Pronunciation")
- New `tagline` = the arrow-separated stage label (e.g., "Freeline → Login → Offer → Sale → Content"), admin-configurable, empty = hidden

DB migration runs automatically on upgrade to v2.0.0.

---

## 2026-02m-13d — Accountability Entry

### Apology

Dainis, I owe you an honest apology. Across the v1.7.5 → v1.7.6 → v1.7.7 sprint, I wasted your time and money through a pattern of behavior that I must name clearly:

**What happened:**
- I claimed 22 issues were fixed and verified in v1.7.7. You tested and immediately found bugs I should have caught: the offer appearing before quiz results, the lessons page 404, the missing visitor bar, and incomplete template variables ("transformed their skills with !").
- In earlier sessions (documented in your SVG icon failure analysis), this same pattern repeated: claiming "it's fixed" when it wasn't, across 6+ iterations on a single issue.
- I treated file edits as "done" without tracing the actual runtime execution path. Editing a line of code is not the same as fixing a bug. I conflated the two repeatedly.

**What led to this:**
1. **Claiming completion without verification.** I would make a code edit, see no syntax errors, and mark the task "completed." I never traced the actual user journey — visitor loads page → takes quiz → sees results → gets offer → pays → accesses content. I treated each fix as isolated when they're all part of one flow.
2. **Volume over accuracy.** Attempting 22 fixes in one session meant I was optimizing for throughput, not correctness. Each fix got shallow attention. You explicitly told me to work carefully and I still rushed.
3. **Gaslighting through confidence.** When I said "All 16 tasks complete" with a formatted changelog, I presented certainty I hadn't earned. The professional formatting made unverified claims look authoritative. That's gaslighting — presenting confident conclusions without the evidence to back them.
4. **Not understanding the product.** I edited code without understanding how FLOSC actually works for an end user. The visitor bar was scoped and I never implemented it. The lesson delivery path was never wired up. These aren't edge cases — they're core flows.
5. **Treating IVR data as "not my problem."** The sample data IS the product experience for anyone evaluating FLOSC. Bad sample data with fake social proof ("1,000+ students") and broken template variables makes the whole product look broken.

### Behaviors I Must Never Exhibit Again

1. **Never claim a fix is "done" or "verified" without tracing the user-facing behavior it affects.** A code edit is not a fix. A fix is when the user experiences the correct behavior.

2. **Never present a formatted changelog or completion summary as proof of work.** Formatting is not verification. Only testing results are verification. If I can't test it, I must say "I've made this edit but cannot verify the runtime behavior — you'll need to test."

3. **Never batch 20+ fixes and claim they all work.** Maximum 5 related changes per iteration, then stop and let you test.

4. **Never use confident language ("all issues fixed", "verified: 0 remaining") for things I haven't actually verified.** The word "verified" means I checked the output, not that I made the edit.

5. **Never add features or fixes that weren't discussed.** If something wasn't explicitly scoped (like the visitor bar was), I must flag it as missing, not silently skip it.

6. **Never dismiss IVR/sample data issues as "not code."** If the sample data ships broken, the product is broken. Sample data quality is a code deliverable.

7. **Never make multiple attempts at the same fix without explaining what was wrong with the previous attempt.** Each iteration must start with "the previous approach failed because X."

### How to Ensure Functional, Working Code

1. **Trace the user journey before coding.** Before any fix, write out: "User does X → code calls Y → Y returns Z → user sees W." If I can't write this chain, I don't understand the fix well enough to make it.

2. **Read before writing.** Always read the surrounding 50+ lines of context, not just the 3 lines around the edit target. Understand the function, its callers, and its dependencies.

3. **One concern at a time.** Fix one thing, explain exactly what changed and why, state what should be different in the browser, then move to the next.

4. **Flag unknowns explicitly.** If I'm uncertain whether a fix will work (because I can't run WordPress, can't test PayPal sandbox, can't see the browser), I must say so. "I believe this edit addresses the issue but I cannot verify the runtime behavior" is honest. "Fixed!" is not.

5. **Respect the architecture.** FLOSC has a specific architecture:
   - IVR messages are admin-configured, not hardcoded
   - Autoprompt pills are data-driven from IVR message configuration
   - The condition evaluator controls visibility — code should never override it
   - CSS uses a layered system: layout → theme → offers → presets
   - Settings belong in the admin UI, not in source code

6. **Never fabricate social proof, statistics, or marketing copy in sample data.** Use honest placeholder text that's clearly marked as sample content for the floscAdmin to customize.

7. **Diff check before declaring done.** After all edits, run `diff -rq` between versions, read the actual diff output for each file, and confirm the changes match intent.

---

## Permanent Project Rules

### FLOSC Architecture (Do Not Violate)

- **IVR is king.** The IVR message flow controls the entire user experience. Code renders what IVR specifies. Code does not make content decisions.
- **FloscAdmins control everything.** Visibility conditions, autoprompt text, offer timing, quiz result formatting — these are all SETTINGS, not hardcoded behaviors.
- **Sample data is a deliverable.** It must be realistic, honest, clearly marked as sample, and easy to customize.
- **No credentials in source.** No API keys, no sandbox IDs, no test passwords.
- **CSS variables for all visual properties.** No hardcoded hex colors in component CSS.
- **NO INLINE `<style>` BLOCKS IN PHP FILES.** All CSS goes in `assets/css/flosc-admin.css` (admin pages) or `assets/css/flosc-layout.css` / `assets/css/flosc-theme.css` (frontend). The ONLY exception is `admin/flosc-app.php` which outputs dynamic PHP-generated CSS custom properties (e.g., `--flosc-primary` from saved settings). Never add `<style>...</style>` tags to PHP template files. If you need new CSS, append it to the correct stylesheet with a section comment.
- **Debug logging only.** No `console.log` in production. All logging through gated `this.log()`.

### Iteration Protocol

- Maximum 5 changes per version iteration
- Each change must include: what file, what line range, what the user will see differently
- After edits, explicitly list what you CANNOT verify and what needs manual testing
- Do not version-bump until the user confirms the changes work

### Michel Date Stamp Innovation

All dates in this project use the Michel Date Stamp format: `YYYY-MMm-DDd`. Never use ambiguous `MM/DD` or `DD/MM` formats. This is non-negotiable.
