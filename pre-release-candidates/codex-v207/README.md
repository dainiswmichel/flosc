# FLOSC v207 resubmission candidate by Codex

- Public plugin version and stable tag 8.0.0
- Canonical source commit `75c753c8f932d1f982b60bff2d0ceb1c50032ea8`
- Submission ZIP SHA-256 `96e15b31652a46dcde83175d7d7ae4428d7bc4d323fe3c550267e35f1f7a8c76`

This is the corrected v207 submission artifact. A clean WordPress installation exposed a first-load crash while FLOSC wrote the default IVR. The filesystem fix falls back to local writes under uploads when the WordPress transport cannot connect, using 0644 when WordPress has not set a file mode.

## Verification

- All 271 packaged files match the canonical source and the source snapshot.
- The exact ZIP passed all automated checks in `flosc-gate.sh`.
- WordPress 7.1.5 on PHP 8.3.33 installed and activated the exact ZIP on a fresh database. The default IVR initialized and persisted. Admin login, Plugin Check 2.2.0, and WP_DEBUG were verified.
- The Membership Craft sample pack installed on the isolated test site. The default scripted IVR returned a visitor reply. The generated-response journey was exercised without a provider, so the model-dependent checks did not pass.
- The filesystem regression checks covered the fallback, configured modes, and uploads containment. The corrected PHP file passed project PHPCS and PHP 7.4 compatibility checks.
- The release gate and Plugin Check reported zero errors and the 22 documented nonce recommendations.
- The full manual acceptance checklist is awaiting a signed record.
