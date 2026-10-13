# FLOSC 8.0.0 — Codex home-run candidate v154

This directory contains the complete v154 review candidate. The complete plugin
source is in `flosc-by-codex-gpt5/flosc/`; `flosc.zip` is the exact distributable
artifact built from that source. The public plugin version and stable tag remain
8.0.0.

## Baseline and scope

v154 starts from the frozen v153 Codex candidate and makes the agreed chat-log
feedback repair:

- separates the REST feedback/praise deletion methods from the zero-argument
  admin-post handlers, restoring the admin deletion journey;
- lists non-zero-rated chat exchanges for the selected flow under **Memory &
  Feedback**, including the personality that produced the response;
- provides two editable, manually copyable drafts for each exchange: a
  personality Memory & Feedback item with recommended density 55, and a Flow
  Sticky sentence;
- keeps a rating as protected evidence only; rating a response does not inject
  the raw conversation into an AI prompt or mutate a flow or personality;
- sends the existing flow-specific Sticky aspects through the active Chatpack
  on each turn; and
- lets the existing copy action read the administrator's edited textarea value.

Legacy manual AI Feedback and Praise entries remain active below the new
workspace and continue to guide the AI as before.

## Verification performed for this increment

- PHP syntax passed on all 207 PHP files in the candidate source.
- PHPCS with `phpcs.xml.dist` on the six edited shipping PHP files reported 0
  errors and 12 standing signed-audio nonce warnings in the framework file.
- The same six files passed PHPCompatibilityWP for PHP 7.4.
- `tests/check_memory_feedback.php`, `tests/check_method_contract.php`,
  `tests/check_identity_every_turn.php`, and `tests/check_log_contract.php`
  passed.
- `node --check assets/js/flosc-admin-events.js` passed.
- A checksum-aware dry-run found local/candidate differences only in local
  `.DS_Store` files and the local-only `testing-environment/` directory.
- `unzip -t` passed. The ZIP contains 243 shipping files in 283 entries and no
  tests.
- The ZIP release gate passed metadata, readme disclosure, PHP syntax, and
  packaging checks, but reported five failed or unverified gates: two bare-
  suppression matches in explanatory comments, five security-sniff errors plus
  25 warnings, the known HTTP_HOST and FILTER_DEFAULT comment/landmine greps,
  and unavailable official Plugin Check. This candidate is therefore not being
  represented as WordPress.org submission-ready.

## Exact artifact

```text
b1b604adfeb5b07a9d6268d4c4b0c0b1ad89452c414ec2c8d7c033169c97c744  flosc.zip
```

Artifact inventory:

- ZIP size: 2,828,118 bytes
- ZIP entries: 283
- Shipping files: 243
- Runtime manifest SHA-256:
  `24a0e0b0f63d9c2c17b104db3dadcde0116465f5ba684ecef5d9c3814f5bc503`

## Repository identity

- Internal Iteration: v154
- Header Github HashID / candidate source commit:
  `4333777ef34591f05ce742bdf0d2e11550a14bb6`
- GitHub parent before v154: `f46a29b64cbea816548a0aa9ecb133a7900698b7`
- Candidate owner: Codex
