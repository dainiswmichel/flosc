# Home Run Candidate v29 — Codex GPT-5.6 Sol

**Status:** Build complete; ready for the Captain's pre-resubmission acceptance tests.

**Built by:** Codex (OpenAI), GPT-5.6 Sol  
**Build record:** `2026-09-10d-UTC12h:58m27s`  
**Baseline:** Home Run Candidate v28 by Grok 4.6, commit `c3711af`

This candidate is isolated under `pre-release-candidates/codex-gpt5`. Grok's v28 candidate and the local v28 baseline were not modified.

The installable artifact is `flosc.zip`. It contains one top-level `flosc/` directory and installs as `wp-content/plugins/flosc/`.

## Proven defect and repair

Tim's LeSAEp quiz data and entitlements were present, but no LeSAEp Chat Logs row existed. Quiz completion did not pass through the chat endpoints, and the successful quiz storage paths never wrote a Chat Logs activity row. The admin's displayed user filter also was not applied to grouped sessions and was lost while navigating between views and flows.

v29 makes these bounded repairs:

- Future successful quiz completions write a flow-scoped `quiz_completion` activity marker after bridge data and complimentary lessons are processed.
- Browser quiz storage, signed-cookie recovery, email registration, and SSO recovery retain the originating flow, journey, completion ID, and completion time.
- The two multiple-choice storage requests run sequentially with one completion ID; deterministic server-side identity prevents a retry from creating a second activity row.
- Nested multiple-choice answer rows are parsed as structured data instead of being passed to string functions.
- A selected user is filtered in SQL before the grouped-session row cap. Their anonymous pre-login rows from the same journey remain visible, while rows belonging to another signed-in user do not.
- The user filter persists across grouped/flat views, active/archived scopes, and flow switching.
- Quiz result copy uses `textContent` at the two remediated DOM sinks.

This repair records future completions. It does not invent Tim's missing historical log row. Any one-time backfill and account change will be reviewed jointly from his stored quiz data.

## Exact-artifact verification

The final `flosc.zip` passed:

- all 184 source/test PHP files parsed by PHP 8.4;
- all 34 PHP regression scripts;
- JavaScript syntax and density-nesting checks;
- WordPress 7.1 installation and activation in an isolated SQLite-backed site;
- an isolated database behavior test covering quiz activity insertion, deterministic retry deduplication, flow/journey persistence, SQL-before-LIMIT user filtering, pre-login context, cross-user exclusion, and activity turn counts;
- WordPress Plugin Check 2.1.0, both the full correctly initialized run and an explicit `plugin_review_phpcs` run, with no errors;
- PHPCompatibility 9.3.5 for PHP 7.4 and newer across all 148 PHP files in the artifact, with zero errors and zero warnings;
- ZIP integrity, one-root validation, forbidden-path scanning, duplicate and symlink scanning, and source-byte CRC comparison;
- an independent rebuild that matched all 277 ZIP entries and the final archive byte for byte.

SHA-256: `544f39cd6f9c2f367d47fff8c68331e8b1e573fcebb9b671d8f121a336de6126`

Machine-readable results and logs are in `evidence/`.

## Verification boundary

Plugin Check's WordPress.org review profile is clean. A broader raw whole-tree WordPress PHPCS scan still reports substantial inherited formatting debt, so this candidate must not be described as universally WPCS-clean. Formatting the entire legacy tree was outside this targeted repair and would create high-risk churn.

The remaining acceptance work is the Captain's fresh-install test, real AI API connection, shipped-personality test, AI Personality Designer test, and final resubmission decision. No live AI-provider call is represented as passed here.

No production account, membership, post, token balance, or email was changed while building and verifying this candidate.
