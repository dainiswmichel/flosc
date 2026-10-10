# FLOSC 8.0.0 — Codex candidate V143

V143 starts from the published V142 candidate at commit
`b799a72507a55a9bf2a1250d5be8989da678149d`. The public plugin version and
stable tag remain 8.0.0. `Github HashID` records that V142 parent commit.

## Scope

- Removes the unused `WP_LOAD_IMPORTERS` definition from the WXR import path.
  FLOSC no longer mutates that request-global WordPress importer bootstrap flag.
- Keeps the existing active-importer requirement and calls the same public
  `WP_Import::import()` entry with the same staged WXR path.
- Corrects the attachment guard from `method_exists()` to `property_exists()`;
  the official WordPress Importer exposes `fetch_attachments` as a public
  property, so attachment fetching now follows the behavior the code intended.
- Adds a WordPress.org source rule that rejects any reintroduced definition of
  the importer bootstrap flag.
- Adds a runtime regression covering the import path, attachment flag, saved
  `imported` status, output capture, and unchanged global bootstrap state.

## Changing global behaviour compliance attestation

- MTS: `2026-10m-10d`
- Signature: **Codex (OpenAI)**
- Guarantee: I guarantee that V143 remediates the reported changing-global-
  behaviour issue in `admin/ivr-upload-handler.php` in WordPress Coding
  Standards-compliant shipping code. The request-global importer bootstrap flag
  is not defined or changed. The WXR import path retains its intended behavior,
  verified by the focused runtime regression described below.

## Verification

- `php -l` passed on all 204 PHP files in the source candidate.
- 48 PHP gates and 6 JavaScript gates passed.
- The focused WXR runtime regression passed: the importer received the staged
  path, attachment fetching was enabled, status was persisted as `imported`, and
  the request-global importer bootstrap flag remained undefined.
- The WordPress.org source rules scanned 146 shipping PHP files with 0 findings.
- Changed shipping PHP passed the project WPCS ruleset with 0 errors or warnings.
- All changed PHP passed PHPCompatibilityWP for PHP 7.4.
- `git diff --check` and `unzip -t` passed.
- The ZIP contains 243 runtime files and no tests, vendor tree, Composer files,
  Git metadata, or build scripts.

Plugin Check, PHPStan, and a live WordPress installation were not run.

## Exact artifact

```text
7e174e32344846e43d7720d822daede806b4ec73b92e7869d817b51613973b2f  flosc.zip
```

283 ZIP entries, 243 files, 2,824,709 bytes. Sorted `sha256  path` manifest of
those files:

```text
bbc047294a27567d8807e3093871a6b1bad50eb0b4322f3f2c382d6d73b44996
```
