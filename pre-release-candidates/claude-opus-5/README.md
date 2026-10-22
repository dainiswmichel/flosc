# FLOSC home run candidate v185 — coded by ClaudeCode

Built from canonical v184 (`a1d9c13`). Five code defects fixed, one test list
corrected. No feature change, no rename, no refactor beyond the named lines.

## Artifact

- `flosc.zip` — 242 files, 153 PHP, one top-level `flosc/` directory
- `SHA256SUMS` — digest of the zip above
- `build-manifest.json` — per-file SHA-256 of every shipped file
- `flosc-by-claude-opus-5/flosc/` — the shipped tree, byte-identical to the zip

## What changed

1. **`includes/class-flosc-framework.php`** — a call to
   `FLOSC_Bridge_Data_Manager::update_flosc_bridge_data()`, a method that does not
   exist on that class, was a guaranteed fatal on the one path that reaches it
   (`process_prelogin_data_for_user()`, called from the magic-link login). It now
   calls `flosc_create_bridge_data( $user_id, $quiz_id, ... )`, the method that
   exists and that the surrounding `if ( ! $existing )` guard already intends.
   `$quiz_id` was already in scope.

2. **`includes/class-flosc-framework.php`** — added `request_guard()`, a public
   accessor. The guard was private, so no class outside the framework could
   rate-limit, which is why item 3 below was possible.

3. **`includes/sso/class-oauth2-handler.php`** — `check_public_sso_permission()`
   returned bare `true`, so both public OAuth routes had no rate limit at all.
   It now applies the same 30-per-15-minutes budget already used by
   `magic_consume` and `login_token_consume`, returning HTTP 429 over it.
   Reachable without a login is not the same as unmetered.

4. **`includes/request-guard/class-flosc-request-guard.php`** — `get_client_ip()`
   trusted `HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR` and `HTTP_X_REAL_IP`
   with no validation, and `check_rate_limit()` keyed its counter on the result.
   Any client can send those headers on a request no proxy rewrote, so every
   rate limit in the plugin could be bypassed by rotating one header per
   request. A header value is now honoured only when `FILTER_VALIDATE_IP`
   accepts it, and the counter key also carries `REMOTE_ADDR`, so a rotated
   header subdivides the sender's own allowance instead of escaping it.
   Sites behind a real proxy keep per-visitor counting; no configuration is
   required and no existing deployment changes behaviour.

5. **`admin/da1.php`** — the catalog key read cast to `(string)` before calling
   `sanitize_text_field()`, which turned a posted list into the five-character
   string `Array`. The cast is gone; `sanitize_text_field()` refuses a list on
   its own and the existing fallback picks a real catalog.

6. **`includes/sale/providers/class-flosc-token-provider.php`** — four
   space-indented lines, corrected with `phpcbf`. Whitespace only.

7. **`tests/check_provider_identity.php`** — `$intentional` now lists the three
   readme headings added since v169 (`Code standard`, `Support & Contribution`,
   `Stay Connected`). Their words were already inside the passing 2500-word
   Description budget; the assertion exists so they are counted deliberately
   rather than by accident. Not shipped.

## Verification

Every file above: `php -l` clean, and 0 errors under the project ruleset's
`WordPress` standard with `--ignore-annotations`.

Not verified here, and not claimed: official Plugin Check, and the reviewer
journeys on a booting WordPress install. Those are Dainis's runtime steps.
