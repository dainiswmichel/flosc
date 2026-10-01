# FLOSC 8.0.0 — Grok candidate V117

Plugin header stays 8.0.0. This folder is candidate V117.

V117 is the Codex V116 tree plus one companion-frame correction. Personalities, flows, starter packs, conversations, logs, the v108 copy, the v114 frame contract, and the v114.1 rewrite flush are unchanged. PHP is byte-identical to V116.

## What was broken

Full-page chat at `/chat` kept the conversation. Collapse landed on the configured hub, `https://dainis.net/?flosc_flow_id=dainis_net_ivr&flosc_handoff_ref=1`, with the companion chrome open and a white panel body.

The chat iframe is created with the `hidden` attribute and stays that way until the app posts `flosc_app_ready`. V116 still set `loading="lazy"` on that iframe. A local Safari check on 2026-10-01 fetched a hidden eager iframe and did not fetch a hidden lazy iframe. The app therefore never announced itself, the health timer (armed only on `load`) never started, and the panel stayed white. There was no error line and no composer.

V116 already forwards `flosc_handoff_ref=1` onto the inner `/chat/` URL and leaves the transcript in same-origin `sessionStorage`. That forward remains. It can restore the thread only after the iframe is actually fetched.

## Runtime delta from V116

`assets/js/flosc-companion.js`:

- The chat iframe is no longer `loading="lazy"`. Its URL is still assigned only when the panel opens.
- `watchFrameHealth()` starts when that URL is assigned, so a frame that never loads does not sit on a white panel.
- A single retry keeps `continuityParams` and `flosc_handoff_pack`. Logout still clears the pack.

No PHP, CSS, template, setting, or navigation target changed.

## Exact artifact

```text
f22a5612479454b948d2ed80539179cd36cd884579f92f76dbc6be64aaf77709  flosc.zip
```

282 entries, 242 files, one `flosc/` root, `tests/` absent, `unzip -t` clean. Version and Stable tag 8.0.0. 2,818,312 bytes.

## Measured in this build

- Companion surface contract: 24/24.
- Handoff round-trip test: 7/7, including a silent-frame rebuild that keeps the parked transcript and the marker.
- `node --check` on `assets/js/flosc-companion.js`.
- Safari, local fixture only: hidden + `loading=lazy` produced no request; hidden + default eager loading posted its ready message. This was not a click-through on dainis.net.
- Plugin Check was not run on this zip.
- This candidate was not deployed.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
