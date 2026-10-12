# FLOSC 8.0.0 — Grok candidate v151

v151 starts from origin/main `86a7c51bd02afb7ffd05e86e24e52a870829f5af`. Version and stable tag remain 8.0.0. Requires at least remains 7.1. Requires PHP remains 7.4. `Github HashID` is that origin/main commit.

## Scope

`flosc_starter_packs_handle_post()` is defined after the file-scope `current_user_can( 'manage_options' )` return. Inside the function, a request that posts neither `flosc_sp_action` nor `flosc_sp_slug` still returns before `check_admin_referer()`. The slug and action are still read after that nonce check, and the capability check stays its own statement.

`'delete_flow'` is removed from `flosc_nav_param_keys()`. No admin screen reads `$flosc_get['delete_flow']`. The flows screen still posts `admin-post.php?action=flosc_delete_flow`.

The v150 repairs stay: personality boot data uses `wp_localize_script()`, and the IVR form body is read in `flosc_ivr_messages_posted_body()`. The v149.grok repairs stay: a flow-editor tab writes only the fields that tab posts; knowledge-base toggle, knowledge-base delete, and administrative email activation keep a general nonce and then a nonce bound to the file or user; `save_term_vgm()` verifies `flosc_term_vgm` on its own line; the cited API-key, model, parameter, autoprompt, IVR, and app-request reads are sanitized at the read; the nine VGM option lists are escaped with `wp_kses()` at the echo.

`admin/settings.php` still assigns `wp_unslash( $_POST )` at file scope on POST. `flosc_sanitize_posted_text()` still returns the original string when `flosc_sanitize_ivr_markdown()` returns `WP_Error`.

## Verification

`php -l` passed on the edited PHP files. `tests/check_wporg_rules.php` exited 0 (146 files, 0 findings). `tests/check_nav_param_keys.php` exited 0 (27 keys read, 27 declared). The flow-tab and nonce regression test passed, 15 assertions. PHPCS with `phpcs.xml.dist` and `memory_limit=2G` reported 0 errors on `flosc.php`, `admin/starter-packs.php`, and `includes/flosc-request.php` after those three files were copied out of `pre-release-candidates`. That scan reported 7 `WordPress.Security.NonceVerification.Recommended` warnings on `includes/flosc-request.php`. It was not a scan of the shipping tree. `unzip -t` passed. The zip has 243 files, one `flosc/` root, and no `tests/` entries.

Plugin Check was not run. A clean WordPress install with `WP_DEBUG` was not run. The live deploy of this zip is the next step of this same release, after this commit.

## Exact artifact

```text
d6267e4ce4f49d5a2c1468272fd30435d8d26667c8b9fda0f25a138dc0e39ef1  flosc.zip
```

283 zip entries, 243 files, 2,826,354 bytes. Sorted `sha256  path` manifest of those files:

```text
21338d1cb24013f49da3b4533d35b62efd73f6a2fe0424f1d5caa8010e296e19
```
