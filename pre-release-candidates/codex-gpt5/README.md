# FLOSC 8.0.0 — Codex candidate V135

V135 starts from the exact completed V134 source commit
`ddb92fc1ae966728877442cd86ef4b2f4dd8f4b2`. The public plugin version and
stable tag remain 8.0.0.

## Scope

- Removed four known incorrect or unavailable URLs from the optional sample
  archetype catalog without removing their archetypes or personality text.
- Kept **Include influences** enabled by default for character descriptions
  and works.
- Added a separate **Include reference URLs** option that defaults off.
- A complete reference—description and URL—enters `ai_base_prompt` only after
  the floscAdmin enables that option. FLOSC never sends a description without
  its URL.
- Complete references remain in the builder, `workshop_json`, and design copy
  whether or not they are sent to the selected AI provider.
- FLOSC does not fetch the reference websites or transmit data to them.

## Verification

- PHP functional tests: 44/44 passed.
- JavaScript tests: 6/6 passed.
- Influence/reference-boundary assertions: 31/31 passed.
- PHP syntax: 200/200 source files passed.
- JavaScript syntax: 15/15 source files passed.
- Changed PHP files: zero findings under WordPress, WordPress-Docs,
  WordPress-Extra, and PHPCompatibilityWP 7.4.
- Official Plugin Check on dainis.net: zero errors and 22 inherited nonce
  warnings.
- The built-ZIP release gate retains five inherited failed or unavailable
  gates: two bare-suppression prose matches, 13 security errors and 22
  warnings, four `HTTP_HOST` matches, two filter-token matches, and locally
  unavailable Plugin Check. V135 introduces no new release-gate finding and is
  not represented as WordPress.org submission-ready.

## Exact artifact

```text
20ab4f8fd8cc2061a42dd995faa0f629446a812b452c7f20ab3390c9da8aa674  flosc.zip
```

The ZIP contains 242 files. Its sorted runtime file-checksum manifest SHA-256
is:

```text
1a6c66c82771c3e2b89c932ddc715a94d4db958e01b1edd82aa7ee3617d42077
```

## Live deployment

The exact V135 ZIP is deployed at
`/home/dainisne/public_html/wp-content/plugins/flosc` on dainis.net. FLOSC is
active, and both the homepage and `/chat/` return HTTP 200. The live 242-file
manifest matches the ZIP and local shipped source exactly.

Recoverable pre-V135 backups:

```text
/home/dainisne/flosc-deploy-backups/flosc-pre-v135-2026y-10m-09d-UTC07h-16m-55s.tgz
/home/dainisne/flosc-deploy-backups/flosc-dir-pre-v135-2026y-10m-09d-UTC07h-16m-55s
```

The remaining acceptance item is Dainis W. Michel's focused browser test of
the Personality Builder controls and compiled profile.
