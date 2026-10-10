# FLOSC 8.0.0 — Codex candidate V144

V144 starts from the published V143 candidate at commit
`64c7a0ee6843209403c859957c36c29e0bb480c4`. The public plugin version and
stable tag remain 8.0.0. `Github HashID` records that V143 parent commit.

## Scope

- Removes all six direct and indirect `home_url()` destination fallbacks from
  the OAuth callback path.
- Introduces one explicit same-origin install fallback, the relative URL `/`,
  which cannot select an ambient flow or attacker-named host.
- Keeps verified-flow resolution first: post-verification redirects still use
  `resolve_app_url_from_flow_id()` with the flow ID returned by `verify_state()`.
- Keeps failed-state handling isolated: it does not read a flow ID from
  unverified state and uses only the relative same-origin fallback.
- Leaves the remaining `home_url()` calls intact because they read the
  configured site host/scheme for redirect validation or add the configured
  same-site host to the allowlist; they do not choose a redirect destination.
- Extends the OAuth source regression to require the six scoped fallback calls,
  the relative-root helper contract, verified-flow resolution, and flow-scoped
  allowlist checks.

## Verification

- `php -l` passed on all 204 PHP files in the source candidate.
- 48 PHP gates and 6 JavaScript gates passed.
- The OAuth regression passed all ordering, flow-resolution, fallback, and
  allowlist assertions.
- No redirect destination or redirect fallback in the OAuth handler calls
  `home_url()`.
- Changed shipping PHP passed the full WordPress PHPCS standard with 0 errors
  or warnings; PHPCS reported 2 / 2 files scanned.
- All changed PHP passed PHPCompatibilityWP for PHP 7.4.
- `git diff --check` and `unzip -t` passed.
- The ZIP contains 243 runtime files and no tests, vendor tree, Composer files,
  Git metadata, or build scripts.

Plugin Check, PHPStan, and a live WordPress installation were not run.

## Exact artifact

```text
ab54fd6b35e39d207c313ca2b16d0c4e4b3201ec4a2cd58e9a0fb507d21c5e5a  flosc.zip
```

283 ZIP entries, 243 files, 2,824,821 bytes. Sorted `sha256  path` manifest of
those files:

```text
96451c22cee6aaa37a02f0bc250be619d1f7bfc5747ed509b0f4dd0e5d39a906
```
