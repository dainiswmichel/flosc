# FLOSC 8.0.0 — Codex candidate v180

Source tree: `flosc-by-codex-gpt5/flosc`. Installable artifact: `flosc.zip`.
The public plugin version and stable tag remain 8.0.0. The internal iteration is
v180.

## Scope

This candidate starts from GitHub `main` commit
`004f6e74eb039b0be3adc4d6209d9dec5fce80ab` and contains one bounded DA1
starter-pack repair:

- a valid, access-filtered DA1 catalog reply is returned directly instead of
  being handed to the configured AI provider for rewriting;
- a deterministic catalog reply does not reserve or charge AI tokens and does
  not retain provider billing/request metadata;
- the DA1 starter prompts explicitly request the complete catalog subset
  available to Visitor, Guest, or Member; and
- the DA1 starter README names the files that actually ship and accurately
  explains automatic pack installation and Media Library import.

The added CLI regression exercises the real starter catalog and verifies that
the visitor reply contains the four public titles, excludes the guest-only
title, and that Visitor and Guest receive 4 and 8 rows respectively.

This change does not redesign the separate mapping between the catalog's 50
metadata rows, the starter pack's 10 WordPress demonstration posts, and the
single paid UberManual PDF. That broader content-connection work remains a
post-submission starter-pack task.

Registration, login, SSO, flow editing, offers, payments, and purchased access
were not edited for v180.

## Provenance

- GitHub baseline and header `Github HashID`:
  `004f6e74eb039b0be3adc4d6209d9dec5fce80ab`
- Candidate artifact commit: `7420207987ea0ab595b129e14047285784cc7bda`
- Required commit subject: `Home run candidate v180 coded by Codex.`
- Michel date: `2026-10m-21d`

## Verification actually run

- Source PHP syntax: 213 files, 0 failures.
- PHPCompatibilityWP for PHP 7.4: passed across the complete candidate source.
- `tests/check-da1-chat-routing.php`: passed.
- `tests/check_da1_grid_save.php`: passed.
- `tests/test_starter_pack_install.php`: all checks passed.
- PHPCS on the changed production PHP and new regression: 0 errors; 1 inherited
  alignment warning in `trait-flosc-chat-turn.php`.
- ZIP build: passed; one `flosc/` root, 242 files, 153 PHP files, and no tests.

The raw `--standard=WordPress` scan across all source, CSS, JavaScript, and test
harnesses reported 76,314 errors and 7,532 warnings, overwhelmingly inherited
front-end/test formatting findings. The v180 shipping PHP edit itself has zero
PHPCS errors. The artifact release gate was stopped at Dainis's request rather
than spending more time on locally repeatable checks. Official Plugin Check and
browser/runtime behavior for this v180 patch remain unverified.

## Exact artifact

```text
25aeb53390a058d8670eedb7d89763d71df1cd042f8386c6d0707f1a5606a3ec  flosc.zip
```

- ZIP size: 2,836,149 bytes
- ZIP entries: 282
- Runtime files: 242
- Runtime PHP files: 153
- Runtime manifest SHA-256: `abeaee1a2e9b16b45e51efeb59de61778113fd692b1a96646c363f7370c501e1`
