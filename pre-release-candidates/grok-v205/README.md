# Home run candidate v205 coded by Grok

Review copy only. The canonical plugin was not edited. Committed as `1941f8aa1304d71e21f729d102e3b5f97b8cdd3c`.

- Baseline: `6772558ac6463a7fe5eed81b1596f83c583ebeb2` (Complete the FLOSC v204 renderer correction)
- Public version and stable tag: 8.0.0
- Requires at least: 7.1
- Requires PHP: 7.4
- Internal Iteration in this copy: v205
- ZIP SHA-256: `0b8f5e51cde84bf4541dc12a42c3fbe102f8df68c0b38e33fe3bd4ca13fead95`
- 271 files, 155 PHP files, 2,879,560 bytes
- `flosc.zip` and `flosc-by-grok-v205/flosc/` are the same 271 files

Against the packaged `codex-v204` source tree, the only different file is `flosc.php`. That file changes `Internal Iteration` from v204 to v205. The renderer correction is already in the v204 source snapshot and in canonical commit `6772558`.

## The resubmission change

`includes/class-flosc-page-context.php`, `render_post_text()`, lines 734–735:

```php
$html = do_blocks( $source );
$html = do_shortcode( $html );
```

This replaces `apply_filters( 'the_content', $source )`. That call was the one project-PHPCS error, sniff `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound`. No `phpcs:ignore` was added.

The permitted source still comes from `post_source()`, which already applies `FLOSC_Content_Filter::filter_post_content()` for the server-resolved access level. After block and shortcode expansion, line 739 runs that same filter again. `rendered_text()` still reduces the result to plain text for the model. The `render_block_context` filter around the expansion is unchanged.

`FLOSC_Content_Filter` and `FLOSC_Content_Protection` stay registered on `the_content` for normal WordPress page rendering. The lesson, freeline, and checkout HTML responses still call `apply_filters( 'the_content', ... )` at:

- `includes/class-flosc-lesson-manager.php` line 438
- `includes/class-flosc-free-content-item-manager.php` line 665
- `includes/sale/class-flosc-checkout-rest.php` line 131

Those three calls already carry the existing core-hook annotation. They are not part of this diff.

Callbacks that exist only on `the_content` do not run inside `render_post_text()`. That includes a third-party `the_content` callback and `FLOSC_Content_Protection::filter_by_visibility()` when that method would have acted. `filter_by_visibility()` returns the content unchanged unless the request is a singular post on the front end. Chat retrieval does not take that branch. Depth and the permitted source are decided before this render.

The four External Services terms URLs from v204 are in this copy: OpenAI services agreement, Anthropic commercial terms, xAI enterprise terms, and the Google Gemini terms pair.

## Verification

Run from the canonical plugin, whose PHP matches this package except the v205 iteration line:

`vendor/bin/phpcs -d memory_limit=1024M --report=summary --standard=phpcs.xml.dist .`

156 files. 0 errors. 22 warnings. Exit 1 because warnings remain. The warnings are the standing `NonceVerification.Recommended` sites: `class-flosc-framework.php` 12, `flosc-request.php` 7, `class-flosc-magic-link-trait.php` 3.

Plugin Check on this zip: pending.

The WordPress.org upload copy removes the `Internal Iteration` line from `flosc.php`. This review zip still has `Internal Iteration: v205`.
