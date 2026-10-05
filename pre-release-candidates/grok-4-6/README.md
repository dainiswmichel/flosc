# FLOSC 8.0.0 — Grok candidate V126

Plugin header stays 8.0.0. This folder is candidate V126.

V126 is the v124 tree, commit `ae51cd51ef4f1d100ed33b50fdddd62a865804a0`, plus the companion close/reopen watchdog. Parent of this commit is v125 `a7134d88ef7b0e2d0be6a859ee6de87057860d07`.

Shipped difference from the v124 zip is two files:

- `flosc/assets/js/flosc-companion.js` — `close()` clears `_frameHealthTimer`. `open()`, in the branch where `iframe.src` already exists, calls `watchFrameHealth()` when `!_frameAlive`.
- `flosc/flosc.php` — `Internal Iteration: v126` and `Github HashID: a7134d88ef7b0e2d0be6a859ee6de87057860d07`. That hash is the parent commit. It is not this commit.

`.distignore`, `.gitignore`, and `.github/push-allowlist.txt` are the v124 files. `tests/check_companion_frame_recovery.js`, `tests/check_companion_surface_contract.js`, and `tests/check_nav_param_keys.php` are the v125 files. `tests/` is not in the zip.

## Measured on this tree before packing

`php -l flosc.php` reported no syntax errors. `node --check assets/js/flosc-companion.js` exited 0. `node tests/check_companion_frame_recovery.js` printed 33 PASS lines and exited 0. `node tests/check_companion_surface_contract.js` exited 0. `php tests/check_nav_param_keys.php` exited 0 with 28 keys read and 28 keys declared.

The 44 PHP gates, the project phpcs run, PHPCompatibilityWP, and Plugin Check were not run on this zip.

## Exact artifact

```text
67acdcadc6413a1e7f73ffaf37f0238ef94822d4b67857fe9396063e0e881de5  flosc.zip
```

282 entries, 242 files, one `flosc/` root, `tests/` absent, `unzip -t` clean. 2,820,237 bytes. Against the v124 zip, the only differing entries are `flosc/assets/js/flosc-companion.js` and `flosc/flosc.php`.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
