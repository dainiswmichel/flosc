# FLOSC 8.0.0 — Codex candidate V116

Codex owns this candidate end-to-end: implementation, packaging, publication,
deployment, and verification. Claude and Grok did not implement V116.

V116 is V115 plus one bounded handoff correction in the existing companion
implementation. It preserves the working full-page chat, companion UI,
navigation, personality, continuity, routing, logging, and V108 content edits.

## Runtime delta from V115

- `assets/js/flosc-companion.js`: snapshot `flosc_handoff_ref=1` before the
  outer hub URL is cleaned, forward it to the inner `/chat/` iframe, then
  remove the consumed marker from the hub address bar.

The runtime delta is one shipped JavaScript file, +13/−1. No PHP, CSS,
template, settings, navigation target, or visible UI was changed.

## What V116 repairs

The full-page chat already parked a visitor transcript in same-origin
`sessionStorage` before navigating to the configured companion hub. Its hub URL
carried `flosc_handoff_ref=1`, but the companion shell failed to pass that
marker into its inner `/chat/` iframe. The iframe therefore opened as a new,
empty visitor. Because companion mode intentionally hides the empty landing
state, the panel appeared blank.

The outer page after collapse is expected to be the Blog because floscAdmin's
Hub Companion URL is `https://dainis.net/`. That page is the configured hub;
the companion's inner frame must still load the FLOSC `/chat/` app with the
same conversation. V116 restores that missing connection. The reverse
companion-to-full-page path remains intact and is covered by the round-trip
test.

## Exact artifact

The release artifact is `flosc.zip`:

```text
6ff0788de10dcde8245f80b457742910910cad8e031b8008b87e026ab87a7002  flosc.zip
```

It contains 282 entries / 242 files under one `flosc/` root and passes
`unzip -t`. The plugin version remains 8.0.0.

## Verification completed before publication

- FLOSC gates: 44/44 PHP and 4/4 JavaScript passed.
- PHP lint: 200/200 files clean.
- Companion surface contract: 21/21 assertions passed.
- Actual-method handoff test: 5/5 assertions passed, covering full-page to
  companion and companion to full-page visitor continuity.
- Full extracted ZIP, WordPress Coding Standards: 0 errors and 22 warnings.
  The warnings are the existing nonce-verification recommendations in three
  files; none is hidden or waived.
- PHPCompatibilityWP at the declared PHP 7.4 floor: 0 findings.
- Official Plugin Check 2.1.0, including experimental checks, ran against the
  exact mounted ZIP: exit 0, 0 errors, the same 22 nonce recommendations.
- The V115 disposable-WordPress routing, starter-pack, personality, and PHP
  runtime evidence remains applicable because V116 changes only the companion
  JavaScript. The exact V116 ZIP was separately mounted in WordPress for
  checksum and official Plugin Check verification.

The exact artifact contains 282 entries / 242 files under one `flosc/` root;
`unzip -t` passes. Its canonical 242-file tree hash is
`e3ce48eb8b91671642f23d14272963dbfad648df346264e3663b3f1428d271ac`.

See `V116-VERIFICATION.md` for the evidence matrix and the explicit boundary
between automated verification and Dainis's visual Safari confirmation.
