# FLOSC Development Session — 2026-03m-22d

## Context

Real-world investor test. Cousin (potential investor contact) tested LeSAEp
on Firefox mobile (recording failed silently) then Chrome mobile (quiz completed).
Post-quiz, two bugs surfaced when showing results to family.

---

## Fixes This Session

### 1. SSO Full Members Seeing Profile-Completion Warning on BuddyBoss Tab
**Commit:** 5272cad
**File:** flosc.php

**Root cause:** `$is_guest_user` included any user with `_flosc_sso_linked_providers`
set — including SSO users who had since upgraded to `lesaep_learners` via access
code. Combined with `$profile_completed` always false for SSO users (they never
go through email credential setup), the yellow "complete your profile to access
recordings" banner fired for full paid members.

**Fix:** One line. Removed `_flosc_sso_linked_providers` from `$is_guest_user`.
SSO users — guest or member — are never subject to the email credential-setup
gate. Rule: nickname/password completion only applies to email-registered users.

---

### 2. Audio Files Stored as WebM — Unplayable on iOS
**Commits:** 6c41f9f, 3468663
**Files:** flosc-app.js, flosc.php

**Root cause (AI shitcoding incident):** An AI agent had placed `audio/webm;codecs=opus`
first in the MediaRecorder MIME type priority list. Chrome (desktop and Android)
supports WebM and selected it every time, storing all recordings as .webm files.
iOS (including Chrome on iPad, which uses WebKit) cannot reliably play WebM audio.
Result: "Error" in the audio player on all Apple devices.

This was introduced without the owner's knowledge or approval. The original intent
was mp4.

**Fix A — JS (flosc-app.js line 3397):**
Restored `audio/mp4` as first priority. iOS records in mp4 (plays everywhere).
Chrome falls back to webm (mp4 not supported by Chrome's MediaRecorder).

**Fix B — PHP (flosc.php line ~11072):**
Changed BuddyBoss tab file-extension search order from `['webm','mp4','ogg']`
to `['mp4','webm','ogg']` so the player serves mp4 when available.

**Fix C — PHP `ajax_serve_user_audio` handler:**
Added HTTP Range request support (206 Partial Content / Accept-Ranges: bytes).
iOS Safari/WebKit sends `Range: bytes=0-1` to probe seekability before playing
audio. Without a 206 response, iOS refuses to play regardless of file format.
This was the actual final blocker — the mp4 files were on the server but iOS
still showed "Error" until Range support was added.

**Manual conversion:** Cousin's 5 existing .webm recordings were downloaded,
converted locally via ffmpeg (`-q:a 1` VBR AAC, no normalization, originals
preserved) and reuploaded to ChemiCloud alongside the originals.

---

## AI Shitcoding Incident — Documented

The webm format change was introduced by an AI coding agent at an unknown point
in the March 20 session cluster. It was not requested, not announced, and not
detectable without iOS testing. It broke audio playback on every Apple device.

This follows the same pattern as the March 14–16 and March 20 incidents:
speculative changes introduced silently, discovered only in production.

---

## Trust Status

Owner has explicitly withdrawn trust from Claude Code as a coding collaborator.
Repeated incidents of undisclosed, unsolicited, and damaging code changes
have made it impossible to rely on Claude to touch the codebase safely without
strict oversight. Mechanical operations (git push, rsync, file creation) may remain
acceptable. Autonomous code decisions do not.

---

## Git Log — This Session

```
5272cad  Fix SSO users seeing profile-completion warning on BuddyBoss tab
6c41f9f  Restore mp4 as primary audio format — fix iOS playback
3468663  Fix audio playback on iOS — add HTTP Range request support
```

---

## Deploy Command

```bash
rsync -avz --delete -e "ssh -p 1988 -i ~/.ssh/chemicloud_key" \
  /Users/dainismichel/2026/flosc/mvp_sprint/flosc_8_0_0/flosc/ \
  dainisne@51.81.55.106:public_html/wp-content/plugins/flosc/
```
