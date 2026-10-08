# FLOSC 8.0.0 — Grok candidate V130

Plugin header stays 8.0.0. This folder is candidate V130.

Parent of this commit is `0e6380e232f200e576c0cb23a590dcf39a5fb368`. That hash is the parent commit. It is not this commit.

The Ajax endpoint is `admin_url( 'admin-ajax.php' )`. On a matching flow custom domain the path and query of that result are kept and only the origin changes. The resolved URL and the `flosc_logout` nonce are passed to `flosc-app.js` with `wp_localize_script( 'flosc-app', 'floscAjax', ... )`. Logout reads `window.floscAjax.ajaxUrl` and `window.floscAjax.nonce`. An empty URL uses `logoutUrl` from `wp_logout_url()`.

`Requires at least` stays 7.1. `tests/` is not in the zip.

## Measured on this tree before packing

`php -l` on `admin/flosc-app.php` and `flosc.php` reported no syntax errors. `node --check` on `assets/js/flosc-app.js` passed. The shipped tree and the zip have zero `/wp-admin/admin-ajax.php` literals. The zip contains `wp_localize_script` for handle `flosc-app` and object `floscAjax`. `unzip -t` passed.

Plugin Check on this zip was not run.

## Exact artifact

```text
94e246c036e3506757b096eb5ced9a15440e2514b15dac232ffd81c243f52463  flosc.zip
```

282 entries, one `flosc/` root, `tests/` absent. 2,820,766 bytes.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
