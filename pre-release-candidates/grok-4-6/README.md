# FLOSC 8.0.0 — Grok candidate V119

Plugin header stays 8.0.0. This folder is candidate V119.

V119 is the V118 tree. A same-site link in the companion chat loads in the tab that already has the panel open.

## What was broken

Assistant messages render links with `target="_blank"`. In the companion iframe that opens a second tab. That tab's companion starts closed, so the article appears with only the launcher button. Following the link inside the iframe would put the website in the chat.

## Runtime delta from V118

`assets/js/flosc-app.js`: a plain click on a same-origin link in the framed chat asks the parent to navigate, with `keepCompanion: true`. Modifier-clicks, downloads, `data-action` controls, in-page anchors, and other origins are unchanged.

`assets/js/flosc-companion.js`: `navigateTopLevel` with `keepCompanion` stores the open panel, adds `flosc_flow_id` and `flosc_companion_handoff=1`, and assigns the parent location. The next page opens that same flow's panel over the linked page.

Checkout and end-of-session redirects still use `leavePanel` without `keepCompanion`.

## Exact artifact

```text
06eee73667db2f40a0562cda94f50eb5ac1e6fd9681e1646b667243523c27a47  flosc.zip
```

282 entries, 242 files, one `flosc/` root, `tests/` absent, `unzip -t` clean. Version and Stable tag 8.0.0. 2,819,081 bytes.

## Measured in this build

- Companion surface contract passed, including the same-site link assertions.
- Handoff round-trip test passed.
- `node --check` on `flosc-app.js` and `flosc-companion.js`.
- Plugin Check was not run on this zip.
- A companion link click-through on dainis.net was not run.
- This candidate was not deployed. Live dainis.net remains V118.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
