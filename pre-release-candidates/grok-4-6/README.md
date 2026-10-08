# FLOSC 8.0.0 — Grok candidate V132

Plugin header stays 8.0.0. This folder is candidate V132.

Parent of this commit is `9a90912e1d8f897e3685f736a45f97b56774062b`, the V131 stamp commit. That hash is the parent commit. It is not this commit.

V132 keeps the V131 Ajax code. The enqueue finding is two edits. Eight PHP comments no longer contain the character sequences `<style` or `<script`. In `admin/flosc-app.php`, the companion CSS, the `:root` variables, and the `window.marked` shim attach on `wp_enqueue_scripts` at priority 10000, after `enqueue_assets()` queues `flosc-layout` and `flosc-app`.

`Requires at least` stays 7.1. `tests/` is not in the zip.

## Measured on this tree before packing

`php -l` passed on the six edited PHP files. The plugin PHPCS ruleset (WordPress, WordPress-Docs, WordPress-Extra, PHPCompatibilityWP 7.4) reported no errors and no warnings on those files. Shipped PHP and the zip contain no `<style` or `<script` character sequence. `unzip -t` passed.

A WordPress page load was not run. Plugin Check on this zip was not run.

## Exact artifact

```text
7bc4354b217ba733f1e12dfabbe27cb347e4780f6bd3257b618bfeacc67643cd  flosc.zip
```

282 entries, one `flosc/` root, `tests/` absent. 2,820,800 bytes.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
