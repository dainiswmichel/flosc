# FLOSC 8.0.0 — Codex home-run candidate v152

This directory contains the complete v152 review candidate. The complete plugin
source is in `flosc-by-codex-gpt5/flosc/`; `flosc.zip` is the exact distributable
artifact built from that source. The public plugin version and stable tag remain
8.0.0.

## Baseline and scope

v152 starts from the v151 candidate and contains the Issue 11 and Issue 10
remediation increment discussed with Dainis W. Michel:

- corrects the `External Services` readme heading level;
- rejects invalid personality-profile encoding instead of silently replacing a
  previously stored profile;
- validates and rebuilds personality workshop JSON and reports failed writes;
- preserves complete large personality profiles and workshops, while returning
  visible size and estimated-token warnings instead of truncating them;
- verifies personality-library persistence by reading back the stored option;
- makes the IVR posted-text sanitizer fail closed instead of returning its
  original untrusted input after sanitizer failure;
- validates knowledge-base editor field types and UTF-8 before writing;
- sanitizes personality-library form arrays and guards malformed autoprompt
  arrays;
- uses the validated contact email alone in the `Reply-To` header; and
- documents the three API-secret sanitizer suppressions with the required
  written reasons.

The subsequently discussed **Blocked Input Events** feature is not implemented
in v152 and is not claimed by this candidate.

## User journeys retained

- Registration, verification, account activation, magic-link delivery, and
  login remain present.
- Flow editing retains the tab-specific save guards from the preceding
  candidates.
- Knowledge-base toggle/delete and pending-account activation retain their
  capability checks and target-bound nonces.
- Personality profiles and valid workshop JSON remain editable and saveable;
  large valid values are retained in full and produce warnings.

## Verification performed for this increment

- PHP syntax passed on all 205 shipping PHP files.
- The seven touched PHP files passed the repository PHPCS ruleset with 0
  errors. PHPCS reported 37 warnings: 12 standing
  `WordPress.Security.NonceVerification.Recommended` warnings and 25 alignment
  warnings.
- The seven touched PHP files passed PHPCompatibilityWP for PHP 7.4.
- `unzip -t` passed on the exact artifact.
- Candidate source and canonical local source matched by checksum-aware rsync.
- The ZIP contains 243 files in 283 entries and contains no tests.

The full-tree PHPCS attempt exhausted its default 128 MB memory limit and was
not rerun because the current 11-to-1 remediation process defers repeated
untouched-code scans until the final pre-resubmission pass. The release gate,
official Plugin Check, clean-WordPress runtime, and browser journeys were not
run for v152. This candidate therefore does not claim final submission
readiness. It was not deployed to live; live remained v151 at publication time.

## Exact artifact

```text
9c7f4f3d52e8549970b27b30d71d363b96e6df6e2a6b33ea63979e80132899e2  flosc.zip
```

Artifact inventory:

- ZIP size: 2,827,597 bytes
- ZIP entries: 283
- Shipping files: 243
- Runtime manifest SHA-256:
  `5241307042bd5991285aa6fdb09faae2b7b784a2343d9db55bfeb6d716d959fa`

## Repository identity

- Internal Iteration: v152
- GitHub parent at packaging:
  `be67a108a72dee233d6b43ffc3917d2caf3b68bf`
- Candidate owner: Codex
