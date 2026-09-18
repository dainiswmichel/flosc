# FLOSC — Grok 4.6 v87

**Agent:** Grok 4.6
**Plugin version:** 8.0.0
**Candidate:** v87
**Base:** Claude Opus 5 v86 (`146409c`)

Pass 1 of the Plugin Check nonce findings. Not a live hotfix.

## What this is

Claude v86 shipping tree, with:

- `includes/sso/class-oauth2-handler.php` — `handle_callback()` calls `verify_state()` once, immediately after `$state` is resolved. The unverified peek/delete is gone. Invalid or expired state redirects to `home_url()`. That is a named behaviour change: an abandoned login that previously resumed on the flow URL now lands on the WordPress site root.
- `includes/class-flosc-framework.php` — comment only. The false “a nonce cannot travel in an `<audio src>`” sentences are replaced with the HMAC/capability-URL reason.
- `tests/check_oauth_state_ordering.php` — new gate. Red on the v86 body; green after the repair. Does not ship in the zip.

`includes/flosc-request.php` and `includes/magic-link/class-flosc-magic-link-trait.php` are unchanged.

No `phpcs:ignore`, `phpcs:disable`, severity changes, or WordPress nonce added on OAuth / audio / magic / nav GET.

## Named behaviour change

Invalid or expired OAuth state → `home_url()`. Unverified state cannot name a trustworthy flow-domain redirect.

## Measured here

- `php -l` 195 files, 0 errors
- gates 42 of 42 (40 PHP + 2 JS)
- PHPCS `WordPress.Security.NonceVerification` on the four Plugin Check files: still 25 warnings/errors (expected; sniff does not treat `verify_state()` or HMAC as a nonce)
- Plugin Check: not run here

## Artifact

```text
pre-release-candidates/grok-4-6/flosc.zip
sha256  c106c84612bbb003d18485042e5bbe02265813d2135039342b6254bfd28f78e3
size    2,793,712
entries 281
root    flosc/
tests/  0
version 8.0.0
```

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
