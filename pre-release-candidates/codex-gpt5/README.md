# FLOSC — Codex v26 candidate

**Status:** Deployed to the authorized live test site and ready for Captain acceptance testing.

This is the complete **v26 pre-release candidate** prepared by **Codex, the `/root` lead coder in the GPT-5.6 Sol session**. The WordPress plugin version remains `8.0.0`; `v26` counts candidate iterations and is not a plugin version bump.

## Remediation in this candidate

- Every IVR content entitlement decision now evaluates membership against the requested `flow_id`, including the permission callback and both message handlers. A global membership flag or membership in another flow cannot disclose the requested flow's content.
- Uploads writes and moves reject an existing destination symlink before invoking WordPress filesystem operations.
- The two secret-setting callbacks no longer unslash credential bytes a second time. Quotes, backslashes, percent signs, and multiline private keys survive the Settings API callback unchanged; blank submissions still preserve the stored value.
- The PayPal and IVR diagnostic panels render remote response values with `textContent`, text nodes, and created elements. They no longer place response data into `innerHTML`.
- All thirteen shipped PHP heredoc/NOWDOC declarations were converted to ordinary string arrays joined by newlines. The four shipped personality profiles retain their exact v25 bytes.

The runtime delta from the Claude Opus 5 v25 ZIP is exactly fourteen files: the files needed for these five remediation groups and no unrelated runtime files.

## Verification completed

- All 12 `test_*.php`, all 20 `check_*.php`, and `check_density_nesting.js` passed.
- All 148 PHP files and all 9 standalone JavaScript files in the release artifact passed syntax checks. The edited inline admin JavaScript blocks also passed `node --check` after PHP interpolation was isolated.
- Plugin Check 2.1.0 on WordPress 7.1 passed its complete check set with no errors, including `plugin_review_phpcs`.
- WordPress runtime probes passed all 15 assertions for REST permissions, cross-flow content isolation, settings registration, and filesystem containment. Secret-setting runtime probes also preserved both test credentials byte for byte and preserved stored values on blank input.
- The ZIP passed integrity, forbidden-path, single-root, packaging, provider-disclosure, and all four Starter Pack asset gates.
- A clean rebuild from the candidate tree matched all 277 ZIP entries by filename, uncompressed size, and CRC-32: zero missing, extra, or changed entries.
- The extracted artifact was mirrored to the authorized live test site, the remote hygiene check passed, and WP-CLI reports FLOSC active at version 8.0.0.

## Captain acceptance remaining

1. Install `flosc.zip` on a fresh WordPress installation.
2. Connect an AI provider and exercise the shipped Starter Packs.
3. Select one or more shipped personalities and confirm their voices and behavior in real conversations.
4. Build, save, select, and converse with an entirely new personality through the AI Personality Designer.
5. Resubmit only after those four product checks pass and the team closes any separately assigned documentation items.

## Candidate contents

```text
pre-release-candidates/codex-gpt5/
├── README.md
├── build-manifest.json
├── SHA256SUMS
├── flosc.zip
└── flosc-by-codex-gpt5/
    └── flosc/
        └── complete candidate source and non-shipping test suite
```

The ZIP contains one top-level `flosc/` directory and excludes tests, development tooling, internal handoffs, nested candidates, Composer dependencies, and sample data.

To verify the download:

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
