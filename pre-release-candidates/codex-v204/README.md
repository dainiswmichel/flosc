# Home run candidate v204 coded by Codex

- Public version and stable tag 8.0.0
- Canonical source commit `6772558ac6463a7fe5eed81b1596f83c583ebeb2`
- ZIP SHA-256 `842b6ef9c3a8665600b1041a5c2af566499806a8ab89a868513d4c988319a27e`

This v204 revision carries the approved API terms links for OpenAI, Anthropic, xAI, and Google Gemini. The page-context renderer uses `do_blocks()` followed by `do_shortcode()`, within the existing permission and block context handling. The rendering path now expands blocks and shortcodes directly. Callbacks attached only to `the_content`, including bare URL oEmbed handling, are outside this path.

## Verification

- The packaged source matches all 271 files in the ZIP. The only file changed since the earlier v204 ZIP is `includes/class-flosc-page-context.php`.
- The project PHPCS ruleset reported zero errors and 22 existing nonce recommendations. PHP lint and all static release-gate checks passed.
- A WordPress fixture verified dynamic block rendering with the correct post ID and expansion of registered shortcodes. DA1 chat routing, VGM, and inline JavaScript checks passed.
- The completed official Plugin Repo report belongs to the earlier v204 ZIP. This revision has not received its own official Plugin Check report.
