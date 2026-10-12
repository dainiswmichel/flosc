# FLOSC 8.0.0 — Grok candidate v150

v150 starts from origin/main `697e1e9404956f4b94f426ca008b51865cb2f383`. The plugin source it changes is the v149.grok tree. Version and stable tag remain 8.0.0. Requires at least remains 7.1. Requires PHP remains 7.4. `Github HashID` is that origin/main commit.

## Scope

Personality boot data is passed with `wp_localize_script( 'flosc-personality-builder', 'floscPersonalityWp', $boot )`. The builder scripts already read `window.floscPersonalityWp`. Starter-pack submission runs in `flosc_starter_packs_handle_post()`. The IVR form body is read in `flosc_ivr_messages_posted_body()`. Both functions call `check_admin_referer()` before the posted values are read, then `current_user_can()` as its own statement.

The v149.grok repairs stay: a flow-editor tab writes only the fields that tab posts; knowledge-base toggle, knowledge-base delete, and administrative email activation keep a general nonce and then a nonce bound to the file or user; `save_term_vgm()` verifies `flosc_term_vgm` on its own line; the cited API-key, model, parameter, autoprompt, IVR, and app-request reads are sanitized at the read; the nine VGM option lists are escaped with `wp_kses()` at the echo.

`admin/settings.php` still assigns `wp_unslash( $_POST )` at file scope on POST. Moving that read into a function makes `WordPress.Security.NonceVerification.Missing` on the project ruleset, because the six later nonce checks share the file scope. One sanitizer on that array would change stored values across 251 keys.

## Verification

`php -l` passed on the edited PHP files. The flow-tab and nonce regression test passed, 15 assertions. PHPCS with `phpcs.xml.dist` and `memory_limit=2G` reported no errors on `flosc.php`, `includes/flosc-personality-library.php`, `admin/starter-packs.php`, and `admin/ivr-messages.php`. `unzip -t` passed. The zip has 243 files, one `flosc/` root, and no `tests/` entries.

Plugin Check was not run. A clean WordPress install with `WP_DEBUG` was not run. This commit is not a live deploy.

## Exact artifact

```text
c0ae7d2a2f7c816ba5a4e45f68568fc689dfe7467f57c50ec13645dba648db4a  flosc.zip
```

283 zip entries, 243 files, 2,826,361 bytes. Sorted `sha256  path` manifest of those files:

```text
68f782c54421b5194631bb945c48c47e951b1cf9685eefcc77885e8e6aef81f7
```
