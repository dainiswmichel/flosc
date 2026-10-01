# FLOSC 8.0.0 — Codex candidate V115

Codex owns this candidate end-to-end: implementation, packaging, publication,
deployment, and verification. Claude and Grok did not implement V115.

V115 is the verified V114.1 recovery candidate plus a four-file,
standards-only cleanup. It preserves the v107/live companion implementation,
the bounded v108 neutral quiz/default-copy edits, and the V114.1 routing and
fail-closed protections. It does not adopt the v109–v113 repair cascade.

## Runtime delta from V114.1

- `admin/settings.php`: assignment alignment only.
- `includes/chat-turn/trait-flosc-chat-turn.php`: one duplicate blank line
  removed.
- `includes/class-flosc-chatpack.php`: one assignment aligned and the missing
  method documentation added.
- `includes/flosc-admin.php`: one assignment aligned and the missing method
  documentation added.

The delta is 4 shipped PHP files, +24/−12. No executable behavior was added,
removed, or reordered in V115.

## Why the companion recovery remains in this candidate

The v108–v113 failure was not merely “nested chat.” A requested FLOSC route
could miss its rewrite rule, WordPress could canonical-redirect it to an
ordinary post, and the panel could display that post as though it were chat.
The post then mounted its own companion. V109–v113 changed several working
layers while chasing that symptom.

V114.1 returned to v107 and implemented bounded outcomes: register a newly
created flow before flushing rewrite rules, handle identified FLOSC requests
before canonical 404 guessing, keep top-level navigation out of the iframe,
and refuse to reveal an iframe document until it identifies itself as the
FLOSC app. A wrong page therefore fails closed instead of appearing as chat.

## Exact artifact

The release artifact is `flosc.zip`:

```text
6862a7f61f8a5356af6fc7061fcbaaf643ae5594a37ea38773840eacb952fc02  flosc.zip
```

It contains 282 entries / 242 files under one `flosc/` root and passes
`unzip -t`. The plugin version remains 8.0.0.

## Verification completed before publication

- FLOSC gates: 44/44 PHP and 3/3 JavaScript passed.
- PHP lint: 200/200 files clean.
- Companion contract: 17/17 assertions passed.
- Full extracted ZIP, WordPress Coding Standards: 0 errors and 22 warnings.
  The warnings are the existing nonce-verification recommendations in three
  files; none is hidden or waived.
- PHPCompatibilityWP at the declared PHP 7.4 floor: 0 findings.
- Official Plugin Check 2.1.0, including experimental checks, ran against the
  exact mounted ZIP: exit 0, 0 errors, the same 22 nonce recommendations.
- Disposable WordPress 7.1.2 / PHP 8.3: plugin active; all four starter packs
  installed; a new personality was saved and assigned to all four packs; all
  four routes were present immediately and returned HTTP 200 with FLOSC app
  markup; a normal post remained a normal post; an unknown route remained a
  404; no PHP debug log was produced.

## Live deployment

The exact artifact above is deployed and active on `https://dainis.net`.
The canonical SHA-256 of the 242-file live tree matches the canonical hash of
the extracted ZIP:

```text
7ed8eec7f5f8de679d65f44edc5e8045445f3d8439c86935b679ba62e51de4ff
```

The live companion URL returned HTTP 200 with no redirect, FLOSC app and chat
input markup, no ordinary-post content, and no website chrome. A real post
returned HTTP 200 with website chrome and no app marker. An unknown route
returned 404. `debug.log` remained absent. No browser instance was exposed to
this Codex session, so no visual click-through is claimed.

See `V115-VERIFICATION.md` for the full evidence matrix.
