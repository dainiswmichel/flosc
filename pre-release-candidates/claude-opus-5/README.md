# FLOSC candidate — claude-opus-5

**Iteration:** v166
**Baseline:** v164, commit `f051f94abd9f8768b51544f3d60d925d3ac8a522` (Codex)
**Source tree:** `flosc-by-claude-opus-5/flosc/`
**ZIP:** `flosc.zip`, SHA-256 `28248f35fb5004234d091d663b71b49c68cb6a73d9b0ae8430c99eb4a52fa7a5`
**Source commit:** `fd00517470507e20caa35fbe3ae006a1842bafcd`

## Scope

Issue 10 only, the `sanitize_file_name()` class. Four handler entry conditions in
`admin/ivr-messages.php` now require their file-name field to be one text value:

| line | field | handler | iteration |
|---|---|---|---|
| 765 | `import_ivr_file` | import selected IVR file | v166 |
| 806 | `ivr_file_select` | change active IVR file | v165 |
| 903 | `duplicate_ivr_file` | duplicate IVR file | v165 |
| 940 | `delete_ivr_file` | delete IVR file | v165 |

`sanitize_file_name()` opens at `remove_accents()`, which calls `preg_match()`. A posted
list raised `preg_match(): Argument #2 ($subject) must be of type string, array given` —
a fatal on PHP 8, in three of the four cases after the nonce and capability checks had
already passed. A list now means the handler does not run and the stored state is
unchanged. A normal file name still works.

No new helper: these use `flosc_submitted_fields_are_text()`, added in v164, and match the
pattern already at `admin/ivr-messages.php:1250`. No suppression was added. The handler
bodies were not re-indented.

## Retained journeys

Registration, login, flow editing, IVR, SSO, offers and content access are untouched.
Only the four entry conditions and the iteration header changed.

## Tests actually run

- `php -l` on all 154 shipping PHP files — 0 failures.
- PHPCS 3.13.6 / WPCS 3.4.1, stock `WordPress` standard, `--ignore-annotations`, sniffs
  `WordPress.Security.ValidatedSanitizedInput` and `WordPress.Security.EscapeOutput` —
  0 errors, 0 warnings, 154 files, exit 0.
- PHPCompatibilityWP, `testVersion 7.4` — 0 findings, exit 0.
- Executed against WordPress 7.1.3's real `sanitize_file_name()`: for all four handlers an
  array payload would have raised the TypeError before the change and does not run after
  it; a normal file name still runs and resolves to `flosc_default_ivr.md`.
- Repository suite — 53 of 55.

## Failures and unverified items

- `tests/check_provider_identity.php` and `tests/check_request_protection.php` fail. They
  fail identically on v164 and reference no changed file, so they pre-date this work and
  are not addressed here.
- **Plugin Check has not run.** No ZIP, no checksum, no manifest, no WordPress page load.
  This candidate carries no artifact-specific guarantee.
- The `Github HashID` header still carries v164's value. It was not authored here.

## Full-codebase enumerations behind the claim

Both run mechanically, then every hit opened and read:

- **Issue 10** — 387 request-to-sanitizer call sites. 0 fatal-on-array paths, 0 unguarded
  paths that write a wrong value. Guards come from named-field checks, the whole-request
  check, inline `is_string()`/`is_scalar()`, and `flosc_nav_params()`
  (`includes/flosc-request.php:215-216`), which drops any `$_GET` key that is an array.
- **Issue 11** — every `echo`/`print`/`<?=` emitting a non-literal. 0 unescaped sites.

Behaviour measured against real WordPress 7.1.3, not assumed: `sanitize_file_name`,
`sanitize_title`, `sanitize_email`, `sanitize_hex_color`, `esc_url_raw`, `esc_url`, `trim`,
`strtolower` and `strtoupper` are fatal on an array; `sanitize_text_field`,
`sanitize_textarea_field` and `sanitize_key` return `''`; `intval` returns `1`;
`wp_kses_post` and `sanitize_html_class` return the array unchanged.

Selection of a winning candidate, promotion and deployment remain solely with
Dainis W. Michel.
