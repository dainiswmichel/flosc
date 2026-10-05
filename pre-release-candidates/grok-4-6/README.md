# FLOSC 8.0.0 — Grok candidate V127

Plugin header stays 8.0.0. This folder is candidate V127.

V127 is the v126 tree plus the two defects the v126 verification's PHPStan section reported. Parent of this commit is v126 `26e9199b8864412809974c47b17ce78df8befc4a`. That hash is the parent commit. It is not this commit.

Shipped difference from the v126 zip is three files:

- `flosc/includes/class-flosc-trajectory.php` — `flow_from_deployment()` strips the path, query, and fragment with `~[/?#].*$~`. The v126 pattern `#[/?#].*$#` ended at the `#` inside the character class, so `preg_replace()` returned `NULL`.
- `flosc/admin/settings.php` — `$flosc_can_view_administration` is `current_user_can( 'manage_options' )`. The v126 assignment repeated `can_access_flow_admin()` after that call had already run `wp_die()`, so the Administration tab never hid.
- `flosc/flosc.php` — `Internal Iteration: v127` and `Github HashID: 26e9199b8864412809974c47b17ce78df8befc4a`.

`.distignore` keeps the v126 bench excludes and also excludes `testing-environment/`. `tests/` is not in the zip.

## Measured on this tree before packing

`php -l` on the three changed PHP files reported no syntax errors. The host strip returns `example_com` for `https://Example.COM/path?q=1#x`, `example.com`, and `www.example.com/chat`. The 44 PHP gates exited 0.

Plugin Check on this zip recorded 0 ERROR rows and 22 WARNING rows. The clean install was not run.

## Exact artifact

```text
26d1dbd092e8694de498e95812fa7d32bcaf7f2fb72650775bf914ae1a9bc10c  flosc.zip
```

282 entries, one `flosc/` root, `tests/` absent, `unzip -t` clean. 2,820,227 bytes. Against the v126 zip, the only differing entries are the three files above. `$flosc_active_tab` uses one space before `=`.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
