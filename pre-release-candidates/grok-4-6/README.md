# FLOSC 8.0.0 — Grok candidate v149.grok

v149.grok starts from V146 at commit `b339e64042fdcba8d4b0fb1cb465d7b41fa7fa55`. Version and stable tag remain 8.0.0. Requires at least remains 7.1. Requires PHP remains 7.4. `Github HashID` is that V146 commit.

## Scope

Each flow-editor tab writes only the fields that tab posts. Knowledge-base toggle, knowledge-base delete, and administrative email activation keep a general `check_admin_referer()` before the request is read, then a second nonce bound to the file or user. The cited API-key, model, parameter, autoprompt, IVR, and app-request reads are sanitized at the read. The nine VGM option lists are escaped with `wp_kses()` at the echo. The personality boot object is passed to `wp_json_encode()` with no extra flags.

## Verification

`php -l` passed on the edited PHP files. The flow-tab and nonce regression test passed, 11 assertions. PHPCS with `phpcs.xml.dist` and `memory_limit=2G`, errors only, reported no errors on the edited shipping files. `unzip -t` passed. The zip has 243 files, one `flosc/` root, and no `tests/` entries.

Plugin Check was not run. A clean WordPress install with `WP_DEBUG` was not run. This commit is not a live deploy.

## Exact artifact

```text
74e428c4e668dbe6d66f3b9e0bc83dc4dbb3637a982a647637597759153be7ee  flosc.zip
```

283 zip entries, 243 files, 2,826,247 bytes. Sorted `sha256  path` manifest of those files:

```text
b14599ae39ba966b2e4daf038a824d9fd0d5db1c004159af6abe0a5df95bc790
```
