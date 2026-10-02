# FLOSC 8.0.0 — Grok candidate V121

Plugin header stays 8.0.0. This folder is candidate V121.

V121 is the verified local tree of V119 (`b8d8de86333c9d77843c20069fa48a5b9de71060`), plus one comment blank line. Runtime behavior is unchanged.

## What the 2026-10-02 local run showed

Run against `/Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc` before this candidate was packed.

Passed: PHP syntax, 44 PHP gates, 4 JS gates, PHP 7.4 compatibility, version headers (Requires at least 7.1, Requires PHP 7.4, Version and Stable tag 8.0.0), 16 external-service readme blocks, zip shape (one `flosc/` root, no `tests/`).

Project ruleset: 1 error, 22 warnings. The error was `Squiz.Commenting.BlockComment.NoEmptyLineBefore` at `includes/flosc-personality-library.php` in `flosc_ajax_attach_personality()`. V121 adds the blank line that sniff requires. Re-scan of that file against `phpcs.xml.dist` reports no findings.

The 22 warnings are `WordPress.Security.NonceVerification.Recommended` on the signed-audio GET, `flosc_nav_param` reads, and magic-link GET. Those requests are not WordPress form posts. No nonce was added.

Stripping `phpcs:ignore` surfaces 12 errors. Nine are `echo flosc_vgm_options_markup()`, which already returns `esc_attr` / `esc_html` option markup. Three are personality fields sanitized by `flosc_sanitize_personality_profile_text` and `flosc_sanitize_personality_workshop` rather than `sanitize_text_field`. Those ignores stay.

PHPStan reported 733 errors. That pile is not the WordPress.org review. Semgrep reported 8 blocking findings: `esc_url` output, `file_exists` on names already passed through `sanitize_file_name` or the audio filename pattern, and `password_matches()`, which compares text and does not hash with MD5. None of those were changed.

Plugin Check did not run. Colima’s Docker socket was absent, so wp-env, procedure 10, and procedure 11 did not start.

## Gate walkers

Six local gates skip `pre-release-candidates/` and `testing-environment/` so a check of the git root does not scan nested candidate trees. Those copies are in this candidate’s `tests/` directory. `tests/` is not in the zip.

## Exact artifact

```text
9537d8881ecdedcfcf544a2535b8249df74de42812b587ee35c4375ca973a232  flosc.zip
```

282 entries, 242 files, one `flosc/` root, `tests/` absent, `unzip -t` clean. Version and Stable tag 8.0.0. 2,819,151 bytes.

`zip-files/flosc.zip` was not rebuilt. Its sha256 remains `780b855215bfc67b71c8a22b02cea61e4204d4361334ef9a6c2b47c8de52f3fa`.

This candidate was not deployed.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
