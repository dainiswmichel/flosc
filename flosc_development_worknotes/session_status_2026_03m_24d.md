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
