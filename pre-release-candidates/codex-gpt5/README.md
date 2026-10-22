# FLOSC 8.0.0 — Codex home-run candidate v185

Installable artifact: `flosc.zip`. Full source snapshot:
`flosc-by-codex-gpt5/flosc/`.

| Field | Value |
|---|---|
| Internal iteration | v185 |
| Plugin version / stable tag | 8.0.0 |
| Code commit | `96e3c15a30c3ea2533af611f5a4fea5aeeeacdb4` |
| Source identity commit | `ac6332387d773029b36825b144c27637c8a44e58` |
| ZIP SHA-256 | `5b53b2b29124932bd2acf836dbc70eddb0a9aa7299643b2011fe8417858aacb1` |
| ZIP entries | 282 files; 153 PHP files |

## Scope

- Repair the pre-login quiz-result transfer so it calls the bridge-data
  manager's existing creation method for an already authenticated user.
- Reject non-string DA1 catalog selections before sanitization while preserving
  every valid string selection.
- Correct four indentation-only WPCS findings in the token provider.
- Carry forward the DA1 starter-pack flow already present in v184.2.
- Add a CLI regression for the bridge-data method contract.

No registration, user-creation, role, SSO, OAuth, request-guard, proxy, readme,
or external-service behavior was changed.

## Verification

- All 153 shipping PHP files pass `php -l`.
- PHPCompatibilityWP passes for PHP 7.4.
- Project WPCS: 0 errors. Existing warnings remain separately reported.
- Built-ZIP WordPress.org security gate: 0 errors and the 22 documented nonce
  recommendations.
- Built ZIP has one `flosc/` root and excludes tests, development tools,
  dependencies, and candidate trees.
- Burner Plugin Check 2.1.0: 0 errors and the same 22 documented nonce
  recommendations.
- Clean WordPress 7.1.3 burner: v185 active, `WP_DEBUG` true, home and pretty
  permalink requests successful, and no PHP/runtime errors in the logs.

One local development assertion, `tests/check_provider_identity.php`, still
objects to three headings in the already validated `readme.txt`. The readme and
that disputed assertion were deliberately left unchanged.
