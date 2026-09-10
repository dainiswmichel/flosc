# FLOSC — Grok 4.6 v28 candidate

**Status:** Packed from the local working tree and shipped to ChemiCloud for Captain testing at dainis.net.

**Agent:** Grok 4.6  
**Plugin version:** 8.0.0 (not bumped)  
**Baseline:** v26 Codex `7cdda1c` + local A4 + MagicLink small set.

## In this candidate

- FAQ External Services item 17: WordPress core oEmbed (YouTube, TikTok, Spotify, SoundCloud, Apple Music, Vimeo).
- MagicLink small set in `includes/magic-link/class-flosc-magic-link-trait.php`:
  1. Consume rate-limit via existing `FLOSC_Request_Guard` (`magic_consume`, 30 / 15 min).
  2. Two-places fuse (`last_ip` + `last_at`, 15 min). No geo vendor.
  3. Guest use cap unchanged. Members not one-timed.
  4. Hashed transient key + `hash_equals`. Legacy raw key still loads.
  5. Kind fallback (HTTP 200): log in the regular way. Not a 403. Not a ban.
- `handoff-resubmission.md` not in the zip.

## Artifact

```text
pre-release-candidates/grok-4-6/flosc.zip
sha256  79eaab383aa132e472af6e6b4e270205b9de3b36a51ed1d01a6cbf0e641678f5
entries 277 (237 files)
root    flosc/
```

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```

## Ship

```bash
cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0
FLOSC_SHIP_YES=1 ./flosc-ship-candidate.sh grok-4-6
```
