# FLOSC 8.0.0 — Grok candidate V130

Plugin header stays 8.0.0. This folder is candidate V130.

V130 is the v129 tree plus the WordPress.org Ajax-endpoint finding. Parent of this commit is `ccfc92f7ed1f62ca980044ca1572028574f27874`. That hash is the parent commit. It is not this commit.

Shipped difference from the v129 zip is three files:

- `flosc/admin/flosc-app.php` — on a flow custom domain the Ajax URL keeps the path from `admin_url( 'admin-ajax.php' )` and changes only the origin. The logout URL rebuild is unchanged.
- `flosc/assets/js/flosc-app.js` — logout reads `this.config.ajaxUrl`. An empty value uses `logoutUrl` from `wp_logout_url()`.
- `flosc/flosc.php` — `Internal Iteration: v130` and `Github HashID: ccfc92f7ed1f62ca980044ca1572028574f27874`.

`Requires at least` stays 7.1. `tests/` is not in the zip.

## Measured on this tree before packing

`php -l` on `admin/flosc-app.php` and `flosc.php` reported no syntax errors. `node --check` on `assets/js/flosc-app.js` passed. The shipped tree has zero `/wp-admin/admin-ajax.php` literals. The zip has the same 282 entries as v129, and the only differing entries are the three files above. `unzip -t` passed.

Plugin Check on this zip was not run.

## Exact artifact

```text
eab79e3ca93f2a457fdd7a0800a7ad10d233aa565914fa4a502c52cbbc97c95e  flosc.zip
```

282 entries, one `flosc/` root, `tests/` absent. 2,820,639 bytes.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
