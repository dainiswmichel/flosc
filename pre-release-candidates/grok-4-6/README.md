# FLOSC 8.0.0 — Grok candidate V138

V138 is the local plugin tree. It starts from the V137 source at commit `490939b6a34ffc22de3f104b001da883c8556646`. The public plugin version and stable tag remain 8.0.0. `Github HashID` is that V137 commit.

The previous `grok-4-6` folder on main was V136. This commit replaces that folder with the local V138 tree.

## Scope

The shipped plugin has one `ob_start()`, inside `flosc_capture_output()` in `includes/flosc-output-buffer.php`. `flosc.php` loads that file. The 36 fragment captures call `flosc_capture_output()`. Each callback imports or assigns the variables it reads. `$flosc_ajax_url` and `$flosc_import_api` are imported by reference.

This is not a signed full remediation of the WordPress.org "Unclosed ob_start()" note. `ob_get_clean()` runs only when `ob_get_level()` is still one above the level saved before `ob_start()`. A hook that closes that buffer makes the function return an empty string and skip `ob_get_clean()`. `die()` and `wp_die()` skip `finally`, so the buffer stays open.

## Verification

`php -l` passed on `includes/flosc-output-buffer.php`. `tests/check-output-buffer-boundaries.php` passed: 36 captures, one owner, zero leaked buffers. `unzip -t` passed. The zip has 243 files, one `flosc/` root, and no `tests/` entries.

Plugin Check was not run. This commit is not a live deploy.

## Exact artifact

```text
e4cff1090c0fbda665158d88b02ef36dc7b2fcad101c68008bd0b76d086dcf73  flosc.zip
```

283 zip entries, 243 files, 2,824,172 bytes. Sorted `sha256  path` manifest of those files:

```text
b9e40c8b1b53ade2ba0f0940a2a6f20abbda01484e8c0f4769c2c60fa2f8b1df
```
