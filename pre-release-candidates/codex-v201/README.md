# Home run candidate v201 coded by Codex

- Public version and stable tag: 8.0.0
- Internal iteration: v201
- Canonical source commit: `01f1c8b9861fd6135b5034927dccaa50ff0c8861`
- Production baseline: v184 (`a1d9c131` source)
- ZIP SHA-256: `0babf1a305e5444526d50635c0f4c9a5e582f80d83eece922bc5ab60e68b4bf9`

The candidate retains the DA1, Starter Pack, provider configuration, and chat changes through v190. It incorporates the v200 work on the five 2026-10m-24d findings, with corrections for form-scoped Settings and DA1 nonces, safe TSV encoding, and existing-account-only MagicLink issuance and sign-in. The packaged source is under `flosc-by-codex-v201/flosc/` and matches the ZIP file for file.

## Verification

- PHP lint, PHP 7.4 compatibility, JavaScript syntax, and all 68 regression checks passed. After the verification run, three JavaScript files received trailing whitespace removal and passed JavaScript syntax and Git whitespace checks again.
- The full PHP WPCS scan reported zero errors and 22 nonce recommendations for navigation reads, token callbacks, and signed audio URLs. The release gate reported the same numbers on its security sniffs with suppressions disabled.
- Local WordPress 7.1.5 with WP_DEBUG enabled: fresh activation, eleven admin page loads, a legitimate Settings save, and HTTP 403 for a wrong nonce. A separate v184-to-v201 fixture kept flow identity, stored IVR content, and the custom chat slug.
- Local MagicLink checks confirmed a valid subscriber can sign in, an unknown recipient does not become a user, and a missing account ID does not fall back to another account with the same email.
- ZIP integrity and the runtime file inventory were checked. Official Plugin Check did not produce a completed report in the local environment. The operator is running Plugin Check on the deployed copies. Third-party OAuth, payment, and AI-provider transactions were outside the runtime tests.
