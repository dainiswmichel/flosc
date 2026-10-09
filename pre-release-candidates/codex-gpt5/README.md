# FLOSC 8.0.0 — Codex candidate V137

V137 starts from the complete V136 source at commit `cb6333a495786596ab1fca3d6f39bfe0ed7f2abc`. The public plugin version and stable tag remain 8.0.0. `Github HashID` records that V136 baseline commit.

## Scope

WordPress.org asked FLOSC to close every output buffer within the same logical flow.

- All 36 shipped `ob_start()` calls are paired: 35 existing fragment captures remain unchanged.
- The WXR importer buffer now opens inside `try` and restores the preceding buffer level in `finally`.
- The existing `flosc_wxr_api` error is returned only after cleanup.
- Successful imports still update the staged pack status and return `true`.
- No JavaScript, CSS, enqueue call, template variable, login path, personality behavior, or other runtime file changed.

## Verification

- `tests/check-output-buffer-boundaries.php` reports 36 opens, 36 paired, 0 unclosed.
- An isolated runtime harness passed successful import, missing importer API, thrown exception, and importer callback closing FLOSC's buffer early.
- `php -l` passed on every PHP file.
- The three changed source files passed the project PHPCS ruleset and PHPCompatibilityWP 7.4 with no errors or warnings.
- The V135 personality-influence regression, V136 login-action regression, and candidate contract tests passed.
- `unzip -t` passed. The ZIP has 242 files, no `tests/` entries, and differs from the V136 ZIP only in `flosc.php` and `admin/ivr-upload-handler.php`.

Plugin Check and a live WordPress WXR import were not run.

## Exact artifact

```text
b925540d0aa83e6fa82d59392d4b4dc0bb5231e15b6950ec3e40fec017c2c92f  flosc.zip
```

282 ZIP entries, 242 files, 2,822,948 bytes. Sorted `sha256  path` manifest of those files:

```text
6d9612a91c93cfd4c666cd5a691ab5b33177d748b3edcb0965221be5ee89908f
```
