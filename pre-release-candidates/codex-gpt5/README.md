# FLOSC 8.0.0 — Codex home-run candidate v149.codex

This directory contains the complete resubmission candidate, not a stripped or
registration-disabled build. The public plugin version and stable tag remain
8.0.0. The complete reviewable source is in `flosc-by-codex-gpt5/flosc/`; the
exact WordPress.org distributable is `flosc.zip`.

## User journeys retained

- Registration: a visitor submits an email address, FLOSC creates the pending
  account, the visitor verifies it, FLOSC activates the account, sends the
  welcome/magic-link path, and permits login.
- Flow editing: each tab saves only the fields owned by that submitted form, so
  saving one tab does not erase values owned by another tab.
- Knowledge-base and account actions: the nonce action is bound to the exact
  file or user target and capability checks remain separate.

## Remediation scope

- Preserves the v148.codex flow-tab save guards and target-bound KB toggle, KB
  delete, and account-activation nonces.
- Sanitizes the reported AI, autoprompt, IVR, personality-editor, referral, and
  test-offer request inputs at ingestion without flattening structured or
  multiline values.
- Escapes VGM option values and labels at the output boundary and replaces the
  personality editor's inline JSON assignment with WordPress script data.
- Keeps the complete email registration, verification, activation, welcome,
  magic-link, and login journey.
- Removes a stale display-navigation `delete_flow` value; deletion continues
  through its capability- and nonce-protected `admin-post.php` action.
- Declares the registration-token-grant control property used at runtime, which
  avoids a PHP 8.2 dynamic-property deprecation without changing its default.

## Verification

- All 206 source PHP files passed `php -l` during candidate verification.
- All 51 standalone PHP regression procedures passed.
- Shipping source and the built ZIP passed the repository PHPCS ruleset with
  0 errors or warnings. A separate WordPress nonce sniff with suppressions
  disabled reported 0 errors and 25 `NonceVerification.Recommended` warnings.
- Shipping source and the built ZIP passed PHPCompatibilityWP for PHP 7.4.
- The sanitization and escaping sniffs passed with suppressions disabled.
- The exact ZIP passed `unzip -t` and contains 243 files in 283 entries. Tests,
  `vendor/`, Git metadata, and build tooling are absent from the ZIP.
- Official Plugin Check 2.1.0 in a disposable WordPress 7.1.3 installation
  completed with 0 errors and the same 25 nonce-recommendation warnings.
- A live WordPress registration test passed the pending-account, verification,
  activation, and login journey; the clean rerun wrote no WordPress debug log.

The release gate still reports its literal `HTTP_HOST` grep because the OAuth
handler retains the explicitly required login-token host comparison; that value
is not added to the SSO redirect allowlist. The gate's Plugin Check wrapper also
uses a command name not provided by Plugin Check 2.1.0, so Plugin Check was run
directly with its official `wp plugin check` command. These verifier defects are
reported rather than hidden and no full-gate-pass claim is made.

## Exact artifact

```text
6463827cc25e07b52908cfb9d53ffe83f16bc1aabac1a889da30952b9e2d85a3  flosc.zip
```

283 ZIP entries, 243 files, 2,826,566 bytes. The SHA-256 of the sorted runtime
file manifest (`<sha256>  <zip path>`) is:

```text
bc9380819a652cd1e96e944d9889d90cd1d6217b43612232fffb15fe6df60a1b
```
