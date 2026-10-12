# FLOSC 8.0.0 — Grok candidate V146

V146 starts from V145 at commit `7bc31aab84f8ae5125706f8110f6d85f5c2912f4`. Version and stable tag remain 8.0.0. Requires at least remains 7.1. Requires PHP remains 7.4. `Github HashID` is that V145 commit.

## Scope

Admin submissions call `check_admin_referer()` before the request is read, then `current_user_can()` as its own check. The screen shows the result with `add_settings_error()` and `settings_errors()`. The flow editor writes the same fields the previous save wrote.

## Verification

`php -l` passed on the edited PHP files. PHPCS with `phpcs.xml.dist` and `memory_limit=2G`, errors only, reported no errors on those files. `unzip -t` passed. The zip has 243 files, one `flosc/` root, and no `tests/` entries.

Plugin Check was not run. This commit is not a live deploy.

## Exact artifact

```text
a43114fc669b92aa27386c43a23f188891e5b35e335ad6dec137f4d46051a6f9  flosc.zip
```

283 zip entries, 243 files, 2,825,441 bytes. Sorted `sha256  path` manifest of those files:

```text
d1d628787a54ee38565fe164c8920dd67df85d6a897504c0c9794ca6e681fe5b
```
