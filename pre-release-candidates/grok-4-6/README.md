# FLOSC — Grok 4.6 v27 candidate

**Status:** Deployed to the authorized live test site after packaging.

This is the complete **v27 pre-release candidate** prepared by **Grok 4.6**. Baseline is Codex GPT-5.6 Sol **v26** at `main` commit `7cdda1c`. Plugin version remains `8.0.0`; `v27` counts candidate iterations and is not a plugin version bump.

## Remediation in this candidate

- WordPress core oEmbed / in-chat media players are disclosed under FAQ External Services item 17: YouTube, TikTok, Spotify, SoundCloud, Apple Music, Vimeo. Purpose, media URL sent, visitor browser loads the provider player, terms and privacy links. No new runtime feature.
- `handoff-resubmission.md` is not in this candidate tree and is not in the zip (already matched by `.distignore` `handoff*.md`).

v26 repairs are unchanged: requested-flow IVR entitlement, destination-symlink rejection, byte-preserving secret callbacks, PayPal/IVR diagnostic `textContent`, shipped HEREDOC/NOWDOC conversion.

## Verification completed

- `tests/check_provider_identity.php` passed (FAQ word budget, no accidental `==` heading, identity disclosure intact).
- Zip integrity: 277 entries, 237 files, single top-level `flosc/`. No `tests/`, no `handoff*.md`.
- Plugin version 8.0.0 in `flosc.php` and `readme.txt` Stable tag.

## Captain acceptance remaining

1. Install `flosc.zip` on a fresh WordPress installation.
2. Connect an AI provider and exercise the shipped Starter Packs.
3. Select shipped personalities and confirm voices in real conversations.
4. Build, save, select, and converse with a new personality in the AI Personality Designer.
5. Resubmit only after those checks pass.

## Candidate contents

```text
pre-release-candidates/grok-4-6/
├── README.md
├── build-manifest.json
├── SHA256SUMS
├── flosc.zip
└── flosc-by-grok-4-6/
    └── flosc/
        └── complete candidate source and non-shipping test suite
```

To verify the download:

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```

## Ship to ChemiCloud

```bash
cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0
FLOSC_SHIP_YES=1 ./flosc-ship-candidate.sh grok-4-6
```
