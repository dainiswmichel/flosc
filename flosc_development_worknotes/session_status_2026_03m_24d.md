# FLOSC Development Session — 2026-03m-24d

## Deploys This Session

### Deploy 1 — Firefox Android Audio Fix + SSO Profile Gate Fix
**Files:** `flosc-app.js`, `flosc.php`
**Status:** Deployed to dainis.net (ChemiCloud)

Two fixes deployed together:

1. **flosc-app.js** — Firefox Android MediaRecorder native format fix (see session_status_2026_03m_23d.md for full detail)
2. **flosc.php** — SSO profile gate fix (lines 11044–11045) — `$profile_completed` and `$is_guest_user` updated to recognize SSO-linked users. Fix was documented in session_status_2026_03m_22d.md but had only landed in `forward_code_examples/firefox_patch 2/` — not in the deployment directory. Applied and deployed this session.

---

## Devnote — LeSAEp API Audio Quality: Two Paths

**Question raised:** Now that audio format is "freer" (browser picks native format), does the IPA phoneme analysis still receive quality audio? Is the 16kHz conversion degrading stored recordings?

**Answer:** No degradation. The API has two independent paths:

### Path 1 — Analysis (what the model sees)
`decode_audio()` in `lesaep_ipa_engine.py` creates a **temporary** file, runs ffmpeg to 16kHz mono WAV, feeds it to the phoneme model, then discards the temp file.

The conversion exists because the model (Wav2Vec2/MMS family) was trained on 16kHz audio — that is its literal input requirement. Sending 48kHz adds no information; it would be upsampled zeros above 8kHz. Human speech phonemes live below 8kHz. The 16kHz conversion is a model training distribution requirement, not a quality choice.

### Path 2 — Storage (what gets saved)
`_save_session_audio()` saves `req.audio` — the original base64 blob — with `req.format` as the extension. This is the untouched recording from the browser at whatever rate the device captured.

**Conclusion:** Recorded-level quality is already preserved. The original ogg/mp4/webm goes to storage. The 16kHz conversion is a temporary preprocessing step that exists solely to satisfy the model. The BuddyBoss tab plays the stored original — that path was fixed with Range request support in commit 3468663. No change needed.

---

## Pre-deploy Audit Notes

- `flosc-app.js` Firefox fix reviewed and confirmed clean (no sabotage, no scope creep)
- `flosc.php` SSO fix confirmed against reference in `forward_code_examples/firefox_patch 2/flosc/flosc.php`
- Only two files changed in the deployment directory; all other diffs were in `forward_code_examples/` and `flosc_8_0_0_patched/` (outside rsync scope)

---

## Deploy 2 — SSO Welcome Email Fix

**Files:** `flosc.php`, `includes/sso/class-oauth2-handler.php`
**Commit:** 3c96654
**Status:** Deployed to dainis.net + pushed to GitHub

**Bug:** Owner registered via Google SSO and received no welcome email.

**Root cause:** `flosc_sso_user_created` was firing twice per new SSO registration:
1. `class-user-linker.php:189` — inside `create_user_from_sso()`, correct location
2. `class-oauth2-handler.php:638` — again immediately after, duplicate

Each fire called `send_sso_welcome_email()`, which generates a new magic link token and overwrites the previous one. Two near-identical emails hit the inbox in rapid succession — a reliable spam trigger — and the first email's magic link was dead (token overwritten). Both emails were caught by spam filters.

**Fix 1 — `class-oauth2-handler.php:638`:** Removed the duplicate `do_action('flosc_sso_user_created', ...)`. The action already fires correctly from inside `create_user_from_sso()`.

**Fix 2 — `flosc.php` `send_sso_welcome_email()`:** Added `wp_mail` return value check with `FLOSC_DEBUG` error log. `send_guest_link_email()` already had this pattern; SSO was swallowing failures silently.

**Provider coverage:** Fix is provider-agnostic — Google, Facebook (when email permission granted), Microsoft, LinkedIn, Apple all go through `create_user_from_sso()`.

**Magic link confirmed present:** The SSO welcome email generates a 32-char token, stores it as `status: active` (SSO users are already verified — no pending step needed), valid 30 days / 10 uses, and includes the magic link button + plain-text URL fallback. Content is equivalent to the email registration welcome email.

---

## Session Notes — SabotageCode / DestructaCode

Owner clarified the definition of sabotage-coding and destructa-coding this session:

**Both terms mean the same thing:** Claude silently changing code that has been working, without being asked, without disclosure. The breakage goes undetected until the owner tests that specific feature — by which point it is unknown when the damage was introduced.

This is the pattern of every major incident to date: Claude touched something it wasn't asked to touch, the change looked plausible, and working functionality was quietly destroyed (webm codec, probe/keep-alive, mic button). Discovered only in production or during testing, with no timestamp.

Rule going forward: only touch the exact lines in the approved plan. Nothing adjacent, nothing "while I'm in here." If an approved change requires touching something unexpected, stop and report before proceeding.
