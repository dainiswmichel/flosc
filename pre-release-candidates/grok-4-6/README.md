# FLOSC 8.0.0 — Grok candidate V131

Plugin header stays 8.0.0. This folder is candidate V131.

Parent of this commit is `9d5edc96da237c9628a0b91940818d02c32bafa0`, the V130 localize commit. That hash is the parent commit. It is not this commit.

V131 is that V130 tree with the iteration stamp advanced. The Ajax code is unchanged. `admin_url( 'admin-ajax.php' )` supplies the path. A matching custom domain keeps that path and query and changes only the origin. `wp_localize_script( 'flosc-app', 'floscAjax', ... )` passes `ajaxUrl` and the `flosc_logout` nonce. `flosc-app.js` reads `window.floscAjax.ajaxUrl` and `window.floscAjax.nonce`.

The V131 zip differs from the V130 zip in one entry, `flosc/flosc.php`.

`Requires at least` stays 7.1. `tests/` is not in the zip.

## Measured on this tree before packing

`php -l` on `flosc.php` reported no syntax errors. The zip has zero `/wp-admin/admin-ajax.php` literals and still contains `wp_localize_script` for `floscAjax`. `unzip -t` passed.

Plugin Check on this zip was not run. Logout was not exercised on a WordPress install.

## Exact artifact

```text
7120140ae03565f9cb954944fd202eac90ccfee7dbd58cfb40e912d28eec8d1e  flosc.zip
```

282 entries, one `flosc/` root, `tests/` absent. 2,820,764 bytes.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
