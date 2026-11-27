# FLOSC v207 resubmission candidate by Codex

- Public plugin version and stable tag 8.0.0
- Canonical source commit `02baa73da140f61da861f910cf96bd455b845d55`
- Submission ZIP SHA-256 `7db6c70fd09ac973b4710879eb7cf8d6a2aefcf945930fc421fe035d868e1e25`

This is the v207 ZIP prepared for WordPress.org resubmission and used to update FLOSC on dainis.net. The 271 packaged files match the canonical source. Compared with the verified v204 ZIP, the only payload change is the internal iteration label in `flosc.php`.

## Verification

- The exact ZIP passed all automated checks in `flosc-gate.sh`.
- Project PHPCS and official Plugin Check each reported zero errors and 22 `NonceVerification.Recommended` warnings, documented in the repository's release policy.
- PHP 7.4 compatibility and PHP syntax checks passed.
- The production deployment verified v207 activation, the chat page, and rendering of the public Bookshelf shortcode for a visitor.
- The full manual acceptance checklist is awaiting a signed record.
