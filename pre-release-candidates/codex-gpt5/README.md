# FLOSC 8.0.0 — Codex candidate v114

Built 2026-09m-30d from the last companion implementation verified on the live
site, v107 (`d28003f1`), plus the bounded content work from v108 (`17338160`).
The plugin version remains 8.0.0. Nothing was deployed or submitted.

## What happened in v108–v113

v108 was the requested light edit: neutral FLOSC quiz templates and default
copy in four files. v109–v113 were a repair cascade after the panel stopped
obeying its basic contract.

- v109 patched selected navigation call sites after a page loaded inside the
  chat frame and mounted another companion.
- v110 prevented a companion from mounting inside any frame, but its acceptance
  check proved only that nesting stopped. It still passed when the panel held a
  WordPress page instead of the chat.
- v111 found two additional causes: a missing flow rewrite could let WordPress
  redirect the requested app route to a similarly named post, and several
  remaining app call sites could still navigate the frame away from chat.
- v112 rearranged app-template execution and added a helper in another file
  while guessing at an undiagnosed wp-admin fatal.
- v113 removed that cross-file helper because a partial live unzip could expose
  the caller before the definition. The fatal itself never had an error trace
  and therefore remains undiagnosed.

The quality failure was the acceptance criterion: “no nested frame” was treated
as equivalent to “the panel contains chat.” It is not. A one-level iframe
containing a blog post satisfies the former and violates the latter.

## v114 implementation

v114 does not copy the v109–v113 implementation. It reimplements the required
outcomes on the v107/live companion base:

- Companion chrome returns before boot in every iframe.
- The chat iframe stays hidden until `flosc-app.js` identifies the document as
  the FLOSC app. A normal WordPress page, redirect target, or server error is
  never exposed as chat. One clean retry is allowed; a second failure closes
  the frame and shows an error instead of the wrong document.
- Profile, dashboard, and personalized-path actions open outside the panel.
- Checkout, visitor-depletion, and SSO moves are handed to the verified parent
  window; the iframe never performs those navigations itself.
- “Minimize to companion” refuses before any state mutation when the app is
  already framed.
- Both login entry points return to the companion app surface.
- FLOSC handles its app route before WordPress canonical 404 guessing, clears
  404 state only after identifying a FLOSC request, and refreshes rewrite rules
  after a starter-pack flow is registered.
- The v108 neutral quiz/default-copy edits remain intact.

Against v107 this is 11 source files, +299/−75. Against v108 it is eight source
files, +243/−19; one of those is a non-shipping regression test. No v109–v113
file was accepted wholesale.

## Verification

- Live ChemiCloud: 242 files. All six companion-critical live files matched
  v107 byte-for-byte by SHA-256 on 2026-09m-30d.
- `tests/check_companion_surface_contract.js`: 16/16 assertions pass.
- Existing suite: 44 PHP tests and 3 JavaScript tests pass.
- PHP lint: 200/200 files clean.
- `node --check`: both changed runtime JavaScript files clean.
- WordPress PHPCS on the four changed PHP files, measured from the extracted
  artifact: 0 errors, 12 inherited warnings in `class-flosc-framework.php`.
- PHPCompatibilityWP 7.4 on those files: 0 findings.
- Zip: 282 entries / 242 files, `unzip -t` clean, ten changed shipped files
  checksum-match their source counterparts.

Artifact SHA-256:

```
51680c09cee4580bb41d720efba98201411e07a8bba941c0f1b4ab2af69a77a2  flosc.zip
```

## Not yet established

v114 has not been installed in a browser-running WordPress environment. The
required next gate is the real companion matrix: normal post, flow route with
healthy and deliberately stale rewrite rules, login return, profile/dashboard,
external checkout, depletion redirect, full-page minimize, and a forced
non-app iframe response. The panel must contain the app and chat input or fail
closed; frame depth alone is not evidence.

Official Plugin Check and the full WordPress.org release gate have not been run.
This is a test candidate, not authorization to deploy or resubmit.
