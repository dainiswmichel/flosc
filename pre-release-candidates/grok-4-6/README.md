# FLOSC 8.0.0 — Grok candidate V136

V136 starts from the V135 source at commit `55495807edcf21841eb0c81bda1fc6ceda6c2c02`. The public plugin version and stable tag remain 8.0.0. `Github HashID` is that V135 commit.

## Scope

WordPress.org asked why FLOSC creates or logs in users, and that a custom login must not hide itself from security plugins.

- Email verification, MagicLink, cross-domain SSO arrival, and same-domain SSO arrival each fire `wp_login` once, after FLOSC has finished its own bookkeeping and immediately before the redirect.
- The MagicLink wp-sync hop reissues the cookie and does not fire `wp_login` again.
- The OAuth callback still does not fire `wp_login`.
- An authenticated password change still reissues the cookie and does not fire `wp_login`.
- Email verification, the login-token arrival, and the wp-sync hop use the existing rate limiter. A login-token limit failure removes the token from the URL and redirects.
- `readme.txt` states why a subscriber account is required, which paths issue a session, and that `wp_login` does not run `authenticate`.

Payment account creation, profile updates, and signed-token REST authentication are unchanged. The shared request-guard proxy-header trust is unchanged and is not claimed to be spoof-proof.

## Verification

`tests/check_login_action_boundary.php` passed 12/12. `php -l` passed on the trait, `flosc.php`, and that test. PHPCS with the plugin ruleset reported no findings on those three files. `unzip -t` passed. The zip has 242 files and no `tests/` entries.

Plugin Check and a browser login journey were not run.

## Exact artifact

```text
c38c67d49bc28f9b8cf22744612eabf77b747f6014bc033e50656de96d0e0022  flosc.zip
```

282 zip entries, 242 files, 2,822,830 bytes. Sorted `sha256  path` manifest of those files:

```text
47539d12fbcbcc0d64cbb52843545f0af3c3168c537c39c4720bb9b61f588392
```
