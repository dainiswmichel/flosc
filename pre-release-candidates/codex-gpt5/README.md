# FLOSC 8.0.0 — Codex home-run candidate v188

Installable artifact: `flosc.zip`. Full source snapshot:
`flosc-by-codex-gpt5/flosc/`.

| Field | Value |
|---|---|
| Internal iteration | v188 |
| Plugin version / stable tag | 8.0.0 |
| Code commit | `8365fe94536334ce17a3627c1284205d444614ca` |
| Source identity commit | `cd3ddffd58889d9e64ed18744aa6fceece4d9939` |
| ZIP SHA-256 | `03f1e2e6c1ee7beac8c46d8d0f7d478eb4ffbabba095f7ce48dd9cb08224721a` |
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

## Verification

- Changed PHP files pass syntax checks and focused regression tests.
- Changed PHP paths have 0 PHPCS errors; existing documented warnings remain.
- Pack manifests are valid JSON; revised catalog rows carry availability labels.
- Built-ZIP WordPress.org gate passes every local gate: 0 security errors and
  the 22 documented nonce recommendations.
- Official Plugin Check 2.1.0 passes on a fresh WordPress 7.1.3 burner with 0
  errors and the same 22 documented nonce recommendations.
- Built ZIP has one `flosc/` root and excludes tests, development tools,
  dependencies, and candidate trees.
- The burner was rebuilt from empty WordPress and database volumes, with
  `WP_DEBUG` enabled and normal pretty permalinks working without `index.php`.
- Runtime starter-pack and AI-provider testing remains the operator acceptance
  step.
