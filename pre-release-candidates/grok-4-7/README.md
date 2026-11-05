# FLOSC 8.0.0 — Grok candidate v200

Source tree: `flosc-by-grok-4-7/flosc`. Zip: `flosc.zip`.

Base is the committed Codex v190 plugin snapshot at origin/main `4ae041a3c9844b3ec79317361ae94c453888ccb9`. Version and stable tag remain 8.0.0. Requires at least remains 7.1. Requires PHP remains 7.4.

```
Internal Iteration: v200
Github HashID: 4ae041a3c9844b3ec79317361ae94c453888ccb9
```

The repository root plugin is not this copy.

## October 24, 2026 review (T15)

Review ID R flosc/dainismichel/2Jun26/T15 24Oct26/4.3 (P0TDX321123HGN).

- Reference addresses left `assets/js/flosc-personality-builder.js`. The builder keeps each reference label. Character notes, works, labels, and addresses are in `flosc_documentation/chapters/personality-builder-reference-sources.md` and the HTML chapter of the same name. `readme.txt` names the publisher terms and privacy pages. FLOSC does not call those publishers.
- Account creation and session issuance stay. The calls and the reason for each path are in `flosc_documentation/chapters/user-creation-and-authentication-security.md`. New accounts still go through `flosc_registration_role()`.
- Chat-log TSV cells whose value, after leading spaces or tabs, starts with `=`, `+`, `-`, or `@` receive one leading apostrophe in `$to_tsv_line`.
- Posted request reads that the review cited sit inside `sanitize_textarea_field`, `wp_kses_post`, or `wp_strip_all_tags` in the same statement. API keys still go through `flosc_sanitize_api_secret`. Personality Markdown still goes through `flosc_sanitize_personality_profile_text`. Workshop JSON still goes through `flosc_sanitize_personality_workshop`. `FILTER_CALLBACK` is not used on those reads.
- Admin inline scripts that `flosc_capture_output()` used to collect are returned from an immediately-invoked function to `wp_add_inline_script`. `includes/flosc-output-buffer.php` still has the one `ob_start()`.

## Verification

`php -l` on 217 PHP files in this source tree: no syntax errors. `node --check` on `assets/js/flosc-personality-builder.js` passed. `tests/check_personality_influence_boundary.js`, `tests/check-output-buffer-boundaries.php`, `tests/check_tab_scope_and_nonce_binding.php`, `tests/check_admin_response_rendering.php`, `tests/check_inline_js.php`, and `tests/check_newsletter_optin_shape.php` exited 0. Reconstructed admin JavaScript from the inline nowdocs parsed with `node --check`. The contact-form capture is HTML. The `:root` capture is CSS.

Plugin Check: pending.

The zip has 246 files, 286 entries, one `flosc/` root, and no `tests/` entries.

## Exact artifact

```text
5dff4a73e50bbbcc38d307ed173b14698ac9cbd50ced9ab8b5305950be58652c  flosc.zip
```

286 zip entries, 246 files, 2,861,904 bytes. Sorted `sha256  path` manifest of those files:

```text
77ed2eeb2112966d615e86968523ddb486d9ff3cf9141291106ef7558bcc13e5
```
