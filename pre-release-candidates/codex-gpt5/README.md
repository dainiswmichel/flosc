# FLOSC 8.0.0 — Codex candidate V133

V133 starts from the exact Grok V132 candidate at commit
`0a5ac0935d8c5971e2736ded20327ede76f68569`. The plugin version and stable tag
remain 8.0.0. The internal iteration is V133, and the plugin header records the
V132 parent commit as its GitHub HashID.

## Remediation contract

The existing **Include influences** checkbox owns the provider boundary for
personality influence material. No second URL-only checkbox was added.

- Checked: active influence-card character notes, works, citation labels, and
  citation URLs compile into `ai_base_prompt` and are sent as prompt text to
  the floscAdmin's selected AI provider.
- Unchecked: those materials remain visible in the Personality Builder and
  remain in the workshop/design artifacts, but are absent from
  `ai_base_prompt` and are not sent to the provider.
- The active card's personality instruction remains part of the personality in
  both states.
- FLOSC does not fetch the cited websites or transmit WordPress, profile,
  visitor, or conversation data to them.
- The checkbox value is now persisted in `workshop_json` and restored on load.

The visible builder explanation, administration documentation, and
`readme.txt` now describe that behavior directly. The stale “never compiles”
character-note label was corrected.

## Exact V132 → V133 source delta

Six source entries differ:

1. `flosc.php` — V133 release stamp and V132 parent hash.
2. `assets/js/flosc-personality-builder.js` — provider-boundary gate,
   persistence, and matching UI wording.
3. `assets/personality-builder/flosc-personality-builder-markup.php` — concise
   behavior disclosure beside the checkbox.
4. `admin/docs/part3-ref-personality-profile.php` — complete influence and
   citation behavior documentation.
5. `readme.txt` — citation/reference disclosure for WordPress.org review.
6. `tests/check_personality_influence_boundary.js` — regression coverage; it
   is source-only and is intentionally excluded from the distribution ZIP.

The built ZIP differs from V132 in exactly the first five shipped files.

## Verification

- PHP functional checks: 44/44 passed.
- JavaScript checks: 6/6 passed.
- Influence-boundary regression assertions: 18/18 passed on V133; the same
  test detects all five original failures on untouched V132.
- PHP syntax: 200/200 source files passed.
- JavaScript syntax: 15/15 source files passed.
- Changed PHP: WordPress, WordPress-Docs, WordPress-Extra, and
  PHPCompatibilityWP 7.4 reported zero findings.
- ZIP integrity: passed; one `flosc/` root, 282 entries, 242 files, no tests.
- V133 ZIP gate results are identical to V132: both report the same inherited
  13 security errors, 22 warnings, two bare-suppression prose matches, four
  `HTTP_HOST` matches, two filter-token matches, and unavailable Plugin Check.
  V133 introduces no gate regression, but the whole plugin is not represented
  as WordPress.org submission-ready.

## Exact artifact

```text
718d9b1093d07c1d12f276b378a5b57449c46917110e97bf3ff45dd507654fe8  flosc.zip
```

The canonical 242-file runtime manifest SHA-256 is:

```text
082896dbfa28341564c39e3799f3f642e0e0706ef472fba0f975eeead7bddfda
```

## Live deployment

The exact V133 ZIP payload is deployed and active on `https://dainis.net`.
The live 242-file manifest matches the extracted ZIP with zero differences.
The pre-deployment V129 plugin is retained outside the webroot at:

```text
/home/dainisne/flosc-deploy-backups/flosc-pre-v133-2026y-10m-09d-UTC05h-01m-37s.tgz
```

Rollback archive SHA-256:

```text
df63f6585491875b1855737a9ebbe89b931cc5a25539d6b94bae0e37351735e9
```

Automated parity and compiler behavior are verified. The remaining acceptance
item is the focused Personality Builder interaction on dainis.net.

The older V115/V116 verification documents in this candidate folder are
retained as historical records; this README, manifest, source tree, and ZIP
describe the current V133 candidate.
