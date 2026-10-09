# FLOSC 8.0.0 — Codex candidate V140

V140 starts from V139 commit `026576ea6d39b00a04822aae6762ef006190ba86`. The public plugin version and stable tag remain 8.0.0. The plugin header records that V139 commit as `Github HashID`.

## Scope

V140 completes the WordPress.org `Unclosed ob_start()` remediation without changing any of the 36 rendering call sites or their closure captures.

- `includes/flosc-output-buffer.php` restores the V138 helper: the plugin's sole shipped `ob_start()` opens inside `flosc_capture_output()` and is closed in that same function's `finally` block.
- The helper removes nested buffers only above FLOSC's saved level and collects only while FLOSC's own buffer remains current, so it does not close a caller's parent buffer.
- V139's per-capture shutdown callback and `ob_end_flush()` loop are removed because that loop could flush a buffer opened by another component after FLOSC's buffer.
- `tests/check-output-buffer-boundaries.php` is source-only and adds negative assertions preventing shutdown cleanup from returning.
- `flosc.php` changes only `Internal Iteration: v140` and `Github HashID: 026576ea6d39b00a04822aae6762ef006190ba86`.

## Verification

- Output-buffer boundary: 36 captures, one owner, zero leaked buffers.
- Undefined-variable audit and admin-response rendering checks passed.
- Login-action and personality-influence regressions passed.
- PHP syntax passed on all 203 PHP files in the canonical source tree.
- The 26 affected shipped PHP files passed the project WordPress ruleset and PHPCompatibilityWP 7.4.
- ZIP integrity passed; 243 shipped files; `tests/` excluded; source-to-ZIP parity passed.
- The ZIP gate still reports five inherited/unverified items outside this remediation: two prose matches for suppression directives, four `HTTP_HOST` matches, two filter-token matches, unavailable security-PHPCS discovery in the gate, and unavailable Plugin Check.

## Exact artifact

```text
7b397150b14e7b2f4ae00b477ae0ccfee690379330cc8663fe4a529baa1beaa4  flosc.zip
```

283 ZIP entries, 243 files, 2,824,170 bytes. Runtime manifest SHA-256:

```text
172200c7a947a8a11a59554663d108f8e8a1029e24a98d7f3822af3017705a61
```
