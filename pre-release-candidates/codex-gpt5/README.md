# FLOSC 8.0.0 — Codex candidate v175

Source tree: `flosc-by-codex-gpt5/flosc`. Installable artifact: `flosc.zip`.
The public plugin version and stable tag remain 8.0.0. The internal iteration is
v175.

## Scope

This candidate fully remediates WordPress.org review Issue 06 without changing
purchased member-level assignment or login behavior:

- every FLOSC account-creation path supplies an explicitly validated role to
  `wp_insert_user()`;
- SSO once again starts with `subscriber`, then applies the existing SSO role
  and user-data filters before final role validation;
- roles with user, plugin, theme, core, network, options, file-editing, or
  unfiltered-HTML administration capabilities cannot be assigned at account
  creation;
- an unavailable or privileged fallback `subscriber` role stops account
  creation instead of silently creating an account; and
- the regression test executes the resolver and verifies all five account-
  creation paths, rather than certifying source strings.

## Provenance

- Base v174 commit: `5afca498bb60ea4b042c2673845f39fddc41b576`
- v175 code commit: `6b16801ba85bc91a483bbc6e99a12c35ca77fa91`
- Commit name: `Home run candidate v175 coded by Codex.`
- Michel date: `2026-10m-21d`

## Verification

- Source PHP syntax: 213 files, 0 failures.
- ZIP/live PHP syntax: 154 files, 0 failures.
- `tests/check_registration_role_boundary.php`: passed all assertions.
- `tests/check_login_action_boundary.php`: passed all 12 assertions.
- PHPCompatibilityWP, PHP 7.4: passed on all changed shipping files.
- WordPress PHPCS on changed shipping files: 0 errors; 23 inherited warnings
  (15 nonce recommendations and 8 unrelated formatting warnings).
- Exact ZIP release gate: all available metadata, security, review-defect,
  disclosure, and syntax checks passed. Official Plugin Check is explicitly
  unverified because this shell has no booting `WP_PATH`.
- ZIP integrity: passed; one `flosc/` root; no tests, vendor, candidates, or
  development tooling.
- Live deployment: exact extracted ZIP synchronized to the FLOSC plugin
  directory; checksum comparison found no differing or stale runtime files.
- Live HTTP smoke check: `https://dainis.net/chat/` returned HTTP 200.

## Exact artifact

```text
57a669602fceab73b19be584d1a7d87df67aaf51b276897fa95b0e3bca1926bd  flosc.zip
```

- ZIP size: 2,842,613 bytes
- ZIP entries: 283
- Runtime files: 243
- Runtime PHP files: 154
- Runtime manifest SHA-256: `f72b7e8dcaaa180fe97d439282c425756f5ad10d0d438f62f8bbbce934db0c31`
