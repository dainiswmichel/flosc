# FLOSC — GPT-5.6 Sol v85 candidate

**Agent:** GPT-5.6 Sol  
**Candidate:** v85  
**Plugin version:** 8.0.0  
**Published candidate path:** `pre-release-candidates/gpt-5-6-sol/`  
**Working branch:** `gpt-5-6-sol/wporg-remediation`  
**Base:** Grok 4.6 v84 (`5cc4b9f`) — Claude v82.18 base + Pickle v83 P1 overlay

## Artifact

The initial v85 artifact is intentionally byte-identical to Grok v84 before SOL remediation begins.

```text
pre-release-candidates/gpt-5-6-sol/flosc.zip
sha256  ccdb7ab39ecf476d96d43d1912329a116c26ef3e6f6deedef1d1e691d6126ad4
size    2793531
entries 281
root    flosc/
```

Plugin source tree:

`pre-release-candidates/gpt-5-6-sol/flosc-by-gpt-5-6-sol/flosc/`

## Delegated remediation

### SOL-1 — PHP WPCS documentation remediation
Comments/docblocks only. No executable-token changes. One file at a time. Contracts come from the code, never a restated signature. No `@since` unless the file already uses it. No whole-standard `phpcbf`.

Required tollgate: `--report=source` on each edited file, then the shipping-PHP tree TOTAL after the final SOL-1 edit.

### SOL-2 — narrow PHPStan correctness
One proven `variable.undefined` repair or one behavior-identical `(string)` cast at an `esc_*` boundary at a time. Unproven intent is skipped and recorded.

### SOL-3 — external-service/readme reconciliation
Documentation only. Compare the actual v85 shipping artifact's outbound integrations with `readme.txt`.

### SOL-4 — T7–T13 historical rejection audit
Audit first; repair only a proven resurrection, one site at a time. No remodel.

### SOL-5 — package/source parity
Verify source tree versus distribution ZIP, headers, `.distignore`, and `tests/` exclusion. No runtime changes unless packaging is wrong.

## Hard walls

- GPT-5.6 Sol work remains inside `pre-release-candidates/gpt-5-6-sol/`.
- No writes into Claude, Pickle, Grok, Copilot, Codex, or canonical plugin candidate trees.
- No scanner-hiding rewrite counts as remediation.
- No broad executable search/replace.
- No gate is claimed unless it was actually run after the last relevant edit.
- WordPress.org readiness is not claimed by this baseline publication.
