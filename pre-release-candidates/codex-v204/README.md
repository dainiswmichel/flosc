# Home run candidate v204 coded by Codex

- Baseline v203
- Public version and stable tag 8.0.0
- Canonical live source commit `532e787d992a413e5670a808792c0dedce61a0c6`
- ZIP SHA-256 `395875db21d7c4d83c2559ccaf4028c4f406f6e9d40a234b40cbc07c6b51d695`

The readme links to the API terms for OpenAI, Anthropic, xAI, and Google Gemini. The plugin header identifies v204. These are the only two files that differ from the v203 package, so the v203 content rendering, permissions, registration, and sale journeys carry forward.

The complete packaged source under `flosc-by-codex-v204/flosc/` matches the supplied ZIP file for file.

## Verification

- ZIP integrity, the 271-file inventory, and the exact two-file diff against v203 were verified.
- PHP 7.4 compatibility passed on the exact ZIP contents.
- The deployment verified the installed file hashes and WordPress health on dainis.net and on the existing burner. The burner retained active Plugin Check and WP_DEBUG.
- The static release gate reported zero security-sniff errors and 22 existing nonce recommendations. The separate project ruleset reported one hook-prefix error for the inherited WordPress `the_content` call. v204 retains this rendering path.
- The exact-ZIP release gate passed every check. The official Plugin Repo scan on the verified burner reported zero errors and 23 warnings, consisting of the same 22 nonce recommendations and the inherited core-hook prefix warning.
- A WordPress fixture confirmed that the preserved `the_content` path applies registered content callbacks, whereas separate block and shortcode expansion omits them.
