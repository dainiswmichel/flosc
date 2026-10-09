# FLOSC 8.0.0 — Grok candidate V139

V139 is the local plugin tree. It starts from the V138 source at commit `753bdc74204b61ba42bd5a3a6f1fdefca259a7f4`. The public plugin version and stable tag remain 8.0.0. `Github HashID` is that V138 commit.

This commit replaces the V138 `grok-4-6` folder.

## Scope

The shipped plugin has one `ob_start()`, inside `flosc_capture_output()` in `includes/flosc-output-buffer.php`. The 36 fragment captures call that function.

`exit()`, `die()`, and `wp_die()` skip `finally`. Each capture registers a shutdown callback. When `finally` did not run, that callback flushes buffers above the level saved before `ob_start()` and stops there. Flushed bytes, including a `wp_die()` message written into the capture buffer, stay in the parent buffer. The callback does not close that parent. A finished capture sets a flag so a later shutdown does not flush the caller's buffer.

A nested `ob_end_clean()` that returns false stops the cleanup loop.

This is not a signed full remediation of the WordPress.org "Unclosed ob_start()" note. The collect call still runs only when `ob_get_level()` is the level this call opened. A hook that has already closed that buffer skips the collect call. That guard is what keeps the parent buffer open. The shutdown callback is not `flosc_capture_output()`, so it is not the same-function closer the review asks for.

## Verification

`php -l` passed on `includes/flosc-output-buffer.php` and `tests/check-output-buffer-boundaries.php`. `tests/check-output-buffer-boundaries.php` passed, including the exit, nested-exit, finished-capture shutdown, and non-removable nested-buffer probes. `unzip -t` passed. The zip has 243 files, one `flosc/` root, and no `tests/` entries. The zip differs from the V138 zip only in `flosc/flosc.php` and `flosc/includes/flosc-output-buffer.php`.

Plugin Check was not run. This commit is not a live deploy.

## Exact artifact

```text
49b0b756a71fa7da26f0b64fd75f0ae2e938047bffea539da0326766caa4245d  flosc.zip
```

283 zip entries, 243 files, 2,824,378 bytes. Sorted `sha256  path` manifest of those files:

```text
93d7694e6a0c79f2d382356c3ada014626ee9843d3d3200c000a1d02d739720f
```
