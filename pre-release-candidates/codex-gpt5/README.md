# FLOSC 8.0.0 — Codex home-run candidate v153

This directory contains the complete v153 review candidate. The complete plugin
source is in `flosc-by-codex-gpt5/flosc/`; `flosc.zip` is the exact distributable
artifact built from that source. The public plugin version and stable tag remain
8.0.0.

## Baseline and scope

v153 starts from the frozen v152 Codex candidate and completes the three
remaining Issue 10 input-boundary corrections identified after v152:

- validates API keys with `flosc_provider_key_is_plausible()` before model-list
  or model-details requests can construct authentication headers or make an
  HTTP request;
- sanitizes every custom companion include/exclude chunk before storing it; and
- rejects non-string IVR filename request values before `sanitize_file_name()`
  in the two knowledge-base handlers, five model-settings handlers, and four
  account-management handlers.

Normal string IVR values are still sanitized and used as before. Valid API keys
still reach the provider, and the registration/account-management functions
were not removed or fragmented.

## Verification performed for this increment

- PHP syntax passed on the five changed shipping PHP files and the new
  regression test.
- Repository PHPCS on the five changed shipping PHP files reported 0 errors and
  15 standing `WordPress.Security.NonceVerification.Recommended` warnings on
  unrelated lines.
- The five changed shipping PHP files passed PHPCompatibilityWP for PHP 7.4.
- `tests/check-issue10-v153-boundaries.php` passed all ten assertions, including
  refusal before HTTP for malformed keys and successful provider calls for a
  valid key.
- A checksum-aware dry-run confirmed the canonical local source and candidate
  source were identical after synchronization.
- `unzip -t` passed on the exact artifact. The ZIP contains 243 shipping files
  in 283 entries and contains no tests.
- The Git commit contains only the `codex-gpt5` candidate path.

Per the current 11-to-1 remediation process, unchanged source was not repeatedly
rescanned. The full release gate, official Plugin Check, WordPress runtime, and
browser journeys were not run for this increment. Live was not changed.

## Exact artifact

```text
4a4bd60c415becf3e1f2cbccb875b4eaffd9f17fa6ffd9d269a6b8e3a2a96725  flosc.zip
```

Artifact inventory:

- ZIP size: 2,827,590 bytes
- ZIP entries: 283
- Shipping files: 243
- Runtime manifest SHA-256:
  `872e3783b596425a6b0c3826ea799083c9ac2a5fab5d49dc3d39276db44b3123`

## Repository identity

- Internal Iteration: v153
- Candidate source/artifact commit:
  `fa4a684cefc0b739e3426477f8b9e89a2bcc8c49`
- GitHub parent at packaging:
  `5a499154e231ab06a34373bf75b650e4cf0544d6`
- Candidate owner: Codex
