# FLOSC 8.0.0 — Codex candidate V142

V142 starts from the complete V141 source at commit
`ea02004a1c8afb5945296227198349d90ce717c5`. The public plugin version and
stable tag remain 8.0.0. `Github HashID` records that V141 parent commit.

## Scope

- Preserves V141's verified OAuth redirect behavior: post-verification redirects
  use the flow ID from verified OAuth state, and the pre-verification failure
  continues to use the install root without reading unverified state.
- Preserves V141's output-buffer stop when a non-removable child buffer prevents
  progress.
- Corrects the output-buffer helper documentation to describe that boundary.
- Adds executable regression coverage for the non-removable-buffer boundary and
  the OAuth state/redirect ordering contract.
- Makes the candidate test runner execute underscore-named tests, hyphenated
  tests, and the journey harness instead of silently skipping them.

## Verification

- `php -l` passed on all 203 PHP files in the source candidate.
- 47 PHP gates and 6 JavaScript gates passed.
- The OAuth ordering and non-removable output-buffer regressions passed.
- PHPCompatibilityWP 7.4 passed on all 203 PHP files.
- Shipping-code WPCS scanned 146 PHP files with 0 errors and the standing 22
  `NonceVerification.Recommended` warnings.
- `git diff --check`, `bash -n tests/flosc-check.sh`, and `unzip -t` passed.
- The ZIP contains 243 runtime files and no tests, vendor tree, Composer files,
  Git metadata, or build scripts.

The external release gate excluded this source tree because it is under
`pre-release-candidates`, so its zero-file PHPCS and lint results are not used as
evidence. Plugin Check, PHPStan, and a live WordPress runtime were not run.

## Exact artifact

```text
3f3605eb1e4be5ae7849a1dc0a4f86d4be1c7f9cced6af21e71f7787fd87dd7e  flosc.zip
```

283 ZIP entries, 243 files, 2,824,788 bytes. Sorted `sha256  path` manifest of
those files:

```text
b54f5c3ddbace6d6df5ea11f64a3040db57698b5b3986dfe9d2836c25aa0d957
```
