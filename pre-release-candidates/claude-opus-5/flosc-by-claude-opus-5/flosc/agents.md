# FLOSC — Project Rules for AI Coding Agents

Read this file before writing any code. Every AI agent working in this repository
(opencode, Claude Code, Cursor, Copilot, Codex, Grok) must load and obey these
rules. This file is the single always-on source of project context — the
equivalent of `.cursorrules` / `CLAUDE.md`. When a rule in a session prompt
conflicts with this file, this file wins.

---

## 1. Project Identity

- **FLOSC** (F)reeline → (L)ogin → (O)ffer → (S)ale → (C)ontent: a WordPress
  plugin for try-before-you-buy conversational journeys (quizzes → personalized
  content → offers → gated access).
- **Author:** Dainis W. Michel · Plugin URI `https://flosc.ai`
- **License:** GPLv3 or later. **Text Domain:** `flosc`.
- **Slug / folder name:** `flosc`. Main file: `flosc.php`.
- Upstream repo: `https://github.com/dainiswmichel/flosc` (branch `main`).

## 2. Compatibility Targets (from plugin header — do not contradict)

- **Requires PHP:** 7.4
- **Requires at least:** 7.1 (major-only) in both `flosc.php` AND `readme.txt`
- **Tested up to:** 7.1
- **Stable tag:** 8.0.0 (do not bump without Dainis requesting it)

## 3. Coding Standards — the minimum bar

Write **WordPress Coding Standards** (WPCS 3.x) compliant code, checked with
PHPCS. Run the real checks below before declaring anything done; "looks right"
is not verification.

Current standards set used across the plugin (security-relevant sniffs from the
release gate — treat all as non-optional):

- `WordPress.Security.EscapeOutput`
- `WordPress.Security.ValidatedSanitizedInput`
- `WordPress.Security.NonceVerification`
- `WordPress.Security.PluginMenuSlug`
- `WordPress.Security.SafeRedirect`
- `WordPress.DB.PreparedSQL`
- `WordPress.WP.GlobalVariablesOverride`
- `WordPress.PHP.NoSilencedErrors`

### Non-Negotiable Code Rules

1. **Sanitize input, escape output.** Every `$_GET`/`$_POST`/`$_SERVER` access is
   filtered (`sanitize_*`, `esc_*`, `absint`, `wp_verify_nonce`, `current_user_can`).
   Never echo raw data. Never use `FILTER_UNSAFE_RAW` or `FILTER_DEFAULT`.
2. **Prepared SQL only.** No string-interpolated queries; use `$wpdb->prepare()`.
3. **No direct DB pokes.** Module state lives in options (e.g. `flosc_flow_{id}`)
   and WP tables — never hand-rolled flat files for app state.
4. **No inline `<script>` / `<style>` blocks in PHP files.** All CSS goes in
   `assets/css/*`, all JS in `assets/js/*`. The ONLY exception is `admin/flosc-app.php`
   outputting dynamic PHP-generated CSS custom properties (`--flosc-primary`, etc.).
5. **CSS variables for visual properties** — no hardcoded hex in component CSS.
6. **No credentials, API keys, sandbox IDs, or test passwords in source.**
7. **No `error_log`, `var_dump`, `console.log`, or `print_r` debugging.** Logging
   only through the gated `flosc_log()` helper (writes under
   `uploads/flosc-logs/debug.log` when `FLOSC_DEBUG` is on). This is not a license
   to call `flosc_log()` everywhere — keep debug output minimal and conditional.
8. **No `exec` / `shell_exec` / `system` / backticks.** Never introduce process
   execution.
9. **`phpcs:ignore` / `phpcs:disable` only with a written reason** after a
   ` -- ` separator, e.g.
   `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary download body`.
   Bare suppressions are a release-gate failure.
10. **Spacing follows WPCS**: spaces inside parentheses, `if ( COND )`, function
    braces on their own line, etc. When unsure, run the gate — the gate is the
    referee, not your visual memory of WordPress style.
11. **PHP 7.4 syntax only** for new code (no `fn`, no constructor promotion, and
    PHPCompatibilityWP `testVersion 7.4` must stay clean). The plugin targets
    shared hosting behind a conservative PHP floor.

## 4. Architecture — Do Not Violate

- **IVR is king.** The IVR message flow (admin-configured `ai_configuration_files/*.md`)
  drives the entire user experience. Code *renders what IVR specifies*; code never
  makes content decisions.
- **floscAdmin controls everything.** Visibility conditions, autoprompt pills,
  offer timing, quiz result formatting — these are **settings**, not hardcoded
  behaviors. New behavior should come from a setting, not a constant.
- **Sample data is a deliverable.** It must be realistic, honest, clearly marked
  as sample content, and easy for a floscAdmin to customize. Never fabricate
  social proof or statistics.
- **"flow", not "funnel."** The word *funnel* is retired project-wide
  (code, UI, comments, docs, marketing). Use *flow*.
- **Canonical sources.** Working codebase: `mvp_sprint/flosc_8_0_0/flosc`.
  Archive-only (never edit): `flosc_development_archives/`, `tmp/`, any other
  `flosc_*` folder outside the canonical path, and `pre-release-candidates/`
  (other agents' outputs — reference only).
- **Submitting to WordPress.org** means the build passes `flosc-gate.sh` on the
  *built zip*, not just on a source tree.

## 5. Verification — how to prove work is done

Always verify; never claim "fixed/verified/done" without running the checks.

```bash
# PHP syntax on every file (fast, always run)
find . -name '*.php' -type f -exec php -l {} \;

# PHPCS full WordPress standard (from the plugin root, where vendor/ lives)
vendor/bin/phpcs -p . --standard=WordPress

# PHP 7.4 compatibility
vendor/bin/phpcs -p . --standard=PHPCompatibilityWP --extensions=php --runtime-set testVersion 7.4-7.4

# The release gate (the referee). Path is inside the project workspace:
PHPCS_VENDOR=/Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc/vendor
"/Users/dainismichel/2026/flosc_project_folder/mvp_sprint/wordpress-remediation-plan/flosc-gate.sh" "$PWD"
```

The gate includes WPCS security sniffs with suppressions **disabled**, the
13-Sep-2026 review defect greps, readme external-service disclosure, `php -l`,
and Plugin Check (needs a booting WP; set `WP_PATH`). Any FAIL or UNVERIFIED
gate means *not submission ready* — include the gate output in your summary.

## 6. Iteration Protocol (from the AI Accountability Record)

1. **Trace the user journey first.** Write out *"User does X → code calls Y → Y
   returns Z → user sees W"* before editing. If you can't write that chain, you
   don't understand the fix yet.
2. **Read before writing.** Read 50+ lines of surrounding context — function,
   callers, dependencies — not just the edit target.
3. **Max 5 related changes per iteration.** Stop and let Dainis test.
4. **Report what you cannot verify.** If you can't run WordPress / check the
   browser / hit a remote service, say so explicitly: *"I made this edit but
   cannot verify runtime behavior."* Never say "Fixed!" on an unverified edit.
5. **No version bump, no zip, no deploy** until Dainis confirms the change
   works. Fix → explain → WAIT.
6. **Be declarative about scope.** If something is missing/not implemented, flag
   it — never silently skip it.
7. **Repeat failures must begin with *"the previous approach failed because…"***.

## 7. Known Landmines — DO NOT Repeat

- SSO redirects during REST callbacks: never use `get_current_flow()` /
  `get_app_url()` / `home_url()` for redirect resolution. Use
  `resolve_app_url_from_flow_id()` from OAuth2 state. Never put `HTTP_HOST` in
  an SSO allowlist.
- Don't hardcode `wordpress-importer` plugin paths or directly include
  `wp-admin/includes/import.php` — never.
- `Requires at least` must be identical in `flosc.php` and `readme.txt`.
- Inline `<script>`/`<style>` in PHP is a gate violation, not a convenience.
- `flosc.php` is a large monolithic file with heavy legacy weight — prefer
  adding code in `includes/` as a class/trait, not growing the main file.
- Michel Date Stamp format for all dates: `YYYY-MMm-DDd` (e.g. `2026-09m-15d`).
  Never `MM/DD` or `DD/MM`. Non-negotiable.

## 8. Build & Ship

- `./build-dist-zip.sh` builds the distributable zip from the plugin root; the
  resulting artifact is what WordPress.org receives.
- `.distignore` controls what enters the zip. `vendor/` must never ship.
- Keep app state in WP options/DB (`flosc_flow_*`), and keep `flosc.php` header +
  `readme.txt` metadata in lockstep.