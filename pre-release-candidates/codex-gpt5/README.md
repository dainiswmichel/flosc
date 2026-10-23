# FLOSC 8.0.0 — Codex home-run candidate v189

Installable artifact: `flosc.zip`. Full source snapshot:
`flosc-by-codex-gpt5/flosc/`.

| Field | Value |
|---|---|
| Candidate iteration | v189 |
| Plugin version / stable tag | 8.0.0 |
| Release commit | `61f57e3` |
| ZIP SHA-256 | `b1546d4a62b39d85a7c15afbb4b748203c68c5b0518fc6b50eef02e960f7540f` |
| ZIP entries | 282 files; 153 PHP files |

## Scope

- Remember each floscAdmin's selected working flow separately from the initial
  default flow, including across FLOSC sidebar navigation.
- Use a working Gemini default while directing floscAdmins to Google's current
  model guide and preserving manual model selection.
- Report real accuracy-test input-token usage and expose provider failures as
  failures instead of displaying `(no response)` with a passing checkmark.
- Add public identity text to Membership Craft and the WordPress Content
  Membership Journey, and refine the DA1 journey's autoprompt wording.
- Keep all existing access conditions, login actions, offers, prices, catalog
  permissions, and starter-pack installation behavior unchanged.
- Correct the final six WPCS assignment-alignment warnings and update the two
  xAI help links to their current official documentation locations.
- Exclude private iteration and Git commit bookkeeping from the public plugin
  header and distributable ZIP.

## Verification

- Changed PHP files pass syntax checks and focused regression tests.
- Changed PHP paths have 0 PHPCS errors; existing documented warnings remain.
- Pack manifests are valid JSON; revised catalog rows carry availability labels.
- Built-ZIP WordPress.org gate passes every local gate: 0 security errors and
  the 22 documented nonce recommendations.
- Official Plugin Check 2.1.0 completed on the fresh WordPress 7.1.3 burner
  with 0 errors and the same 22 documented nonce recommendations.
- Built ZIP has one `flosc/` root and excludes tests, development tools,
  dependencies, and candidate trees.
- The burner was reconstructed from empty WordPress and database volumes with
  `WP_DEBUG` enabled and normal pretty permalinks working without `index.php`.
- Runtime starter-pack and AI-provider testing remains the operator acceptance
  step.
