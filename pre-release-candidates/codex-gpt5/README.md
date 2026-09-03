# Codex GPT-5 FLOSC deployment candidate

**Status:** Candidate build — WordPress runtime testing is deferred to the authorized remote test site.

This is a complete, independently deployable FLOSC plugin created by **Codex, the `/root` primary agent (GPT-5)** from baseline commit `03f4b926c5502b7d2af0136fc1bac8359286b9f8`.

Other AI agents creating FLOSC deployments are to model this directory structure and the location of the installable ZIP:

```text
pre-release-candidates/{agent-slug}/
├── README.md
├── build-manifest.json
├── SHA256SUMS
├── flosc.zip
└── flosc-by-{agent-slug}/
    └── flosc/
        └── complete independent plugin source
```

The artifact is always named **`flosc.zip`**, never an agent-specific ZIP name. Its only top-level directory is exactly `flosc/`, so WordPress installs it at `wp-content/plugins/flosc/`.

The source under `flosc-by-codex-gpt5/flosc/` is this candidate's complete owned deployment codebase. The canonical source at `mvp_sprint/flosc_8_0_0/flosc/` was not modified or copied from its dirty working tree; this candidate was initialized mechanically from the pinned Git commit above.

## Implemented candidate changes

- Full attached personality profile is resolved and placed in authoritative system context on every turn, enabling personality continuity and immediate mid-chat switching.
- BubblyBetty and DadJokeDan defaults restore concise, character-specific profiles; shared journey/sales policy is no longer duplicated inside those two identities.
- RAG failure falls through to ordinary AI before scripted fallback.
- Production dispatch exposes a structured internal result without repurposing public requests as test mode.
- Provider errors remain internal while visitors receive safe fallback copy and listeners receive accurate failure events.
- Personality genome and compiled runtime profile are fingerprinted together with version and modification metadata on save.
- Existing model-aware tuning remains optional expression control; personality behavior does not depend on non-default sampling settings.
- Restored `flosc_enforce_no_hedge_response()` and `flosc_contains_forbidden_hedge()` on `FLOSC_Framework`; the public chat trait requires both methods before it can return a visitor response.

## Verification boundary

Static and packaging checks are recorded in `build-manifest.json`. Installation, activation, live provider calls, WordPress Plugin Check, Starter Pack lifecycle, database persistence, and Betty → Dan → Betty browser tests are marked **DEFERRED — AUTHORIZED REMOTE TEST REQUIRED**. They are not represented as passed.

Repair worknote: `flosc_development_worknotes/codex-gpt5-public-chat-repair-2026-09m-03d.md`.
