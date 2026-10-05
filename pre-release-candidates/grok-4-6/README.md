# FLOSC 8.0.0 — Grok candidate V124

Plugin header stays 8.0.0. This folder is candidate V124.

V124 is the v123 tree, commit `30a78319631de1ee80d389b6b1e28925ea2eeac8`, placed in the canonical plugin and stamped v124. The shipped zip differs from the v123 zip in `flosc/flosc.php` only: `Internal Iteration: v124` and `Github HashID: 30a78319631de1ee80d389b6b1e28925ea2eeac8`.

`assets/js/flosc-companion.js` is the v123 file. `resetFrameHealth()` clears the timer, `_frameAlive`, `_frameRecovered`, `_frameFailed`, the error node, and `iframe.hidden`. `open()` calls it when `src` is empty. `tests/check_companion_frame_recovery.js` is in this candidate's source tree. `tests/` is not in the zip.

## Measured on the v123 bytes

PHP syntax pass. 44 PHP gates pass. 5 JS gates pass, including the 19 recovery assertions. PHP 7.4 exit 0. `phpcs.xml.dist` reports 0 errors and 22 `NonceVerification.Recommended` warnings in `includes/class-flosc-framework.php` (12), `includes/flosc-request.php` (7), and `includes/magic-link/class-flosc-magic-link-trait.php` (3).

Plugin Check has not been run on this zip. Colima's socket was absent. This candidate was not deployed.

## Exact artifact

```text
bc76377fef3fef6758d5613135e1ac838d442814a5649133e8105d3378e4bff1  flosc.zip
```

282 entries, 242 files, one `flosc/` root, `tests/` absent, `unzip -t` clean. 2,819,966 bytes.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
