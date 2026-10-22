# FLOSC 8.0.0 — Codex home-run candidate v186

Installable artifact: `flosc.zip`. Full source snapshot:
`flosc-by-codex-gpt5/flosc/`.

| Field | Value |
|---|---|
| Internal iteration | v186 |
| Plugin version / stable tag | 8.0.0 |
| Code commit | `dd8116a4cf572a4282e1607157a0ff43e8c57eb7` |
| Source identity commit | `e11e8fe16a34906ce098d6667e56961bba008ba3` |
| ZIP SHA-256 | `6cbb793b0e9a665d08d9521360fe9e1124d2006cabf77d38e4f2a255e94c2475` |
| ZIP entries | 282 files; 153 PHP files |

## Scope

- Make all four starter-pack journeys enjoyable and explicit about the
  Visitor → Guest → Member progression.
- Add the 10,000-token guest grant and `GETFLOSCD` reviewer access route where
  required.
- Preserve article and recipe body gating while allowing the audience to hear
  real titles with their availability.
- Give DA1 and Vegan catalog rows public title/teaser visibility while retaining
  their original access level in the description.
- Route genuine purchase intent to an available offer before DA1 catalog
  matching, without changing ordinary catalog searches.
- Place each bundled PDF in the local Media Library and expose its link from
  member content; standard WordPress Media Library URLs remain ordinary URLs.

## Verification

- Changed PHP files pass syntax checks and focused regression tests.
- Changed PHP paths have 0 PHPCS errors; existing documented warnings remain.
- Pack manifests are valid JSON; revised catalog rows carry availability labels.
- Built-ZIP WordPress.org gate passes every local gate: 0 security errors and
  the 22 documented nonce recommendations. Official Plugin Check is verified
  separately on the burner installation.
- Built ZIP has one `flosc/` root and excludes tests, development tools,
  dependencies, and candidate trees.
- Runtime starter-pack testing remains the operator acceptance step.
