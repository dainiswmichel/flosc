# FLOSC 8.0.0 — Grok candidate V117

Plugin header stays 8.0.0. This folder is candidate V117.

V117 is the Codex V116 tree with the companion chat frame visible again. Personalities, flows, starter packs, conversations, logs, the v108 copy, and the v114.1 rewrite flush are unchanged. PHP is byte-identical to V116.

## What was broken

Fullscreen and companion already continued the same chat. Minimize on the full page and Open full chat page on the bubble are that round trip.

The frame was then hidden until `flosc_app_ready`, and it stayed `loading="lazy"`. A hidden lazy frame is not fetched, so the bubble had no chat to show and no chat to expand. V117 leaves the frame visible when the panel opens, which is how that round trip ran. The handoff marker is still copied onto the inner app URL. Nesting guards, in-frame navigation guards, the v108 copy, and the rewrite flush stay.

## Runtime delta from V116

`assets/js/flosc-companion.js`: the chat iframe is not hidden while it waits for the app. `loading="lazy"` is restored because the frame is on screen. The ready handler no longer toggles visibility. Health retry matches the previous working frame: one rebuild when the app never announces itself.

No PHP, CSS, template, setting, or navigation target changed.

## Exact artifact

```text
cbf7abdd68373200f495ca4fdba51a794b720113508ac726d2853092f4ed849b  flosc.zip
```

282 entries, 242 files, one `flosc/` root, `tests/` absent, `unzip -t` clean. Version and Stable tag 8.0.0. 2,818,026 bytes.

## Measured in this build

- Companion surface contract: 22/22.
- Handoff round-trip test: 5/5.
- `node --check` on `assets/js/flosc-companion.js`.
- Plugin Check was not run on this zip.
- A live expand/collapse click-through on dainis.net was not run.
- This candidate was not deployed.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
