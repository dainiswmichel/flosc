# FLOSC Deployment Candidate - GitHub Copilot

**Agent:** GitHub Copilot (Coding Engineer)  
**Candidate slug:** `github-copilot`  
**Status:** ZIP candidate complete; remote WordPress validation deferred.

## Baseline

- Pinned baseline: `a27db3b9f90cf72cf5bd965c52557828c396b98b`
- Candidate source: `flosc-by-github-copilot/flosc/`
- Canonical source was not modified.
- Other candidates were not modified.

## Implemented behavior

### Complete current personality on every turn

The follow-up chatpack resolves the current flow ID and calls the full identity builder. That identity builder resolves the attached `personality_library_id` from the WordPress personality library, retrieves the current `ai_base_prompt`, and places it in the provider system instruction.

This preserves the required runtime path:

```text
flow personality_library_id
-> current flosc_personality_library row
-> complete ai_base_prompt identity section
-> provider system instruction
-> current personality response
```

An administrator changing the attached personality between turns changes the personality resolved for the next request. No personality profile is stored in session state.

### Versioned profile fingerprint

Each library save now stores these fields with the sanitized `workshop_json` genome and final `ai_base_prompt`:

- `profile_version`
- `profile_hash`
- `profile_modified_gmt`

`profile_hash` is SHA-256 over the saved genome and deployed runtime profile. This gives previews, diagnostics, and later regression fixtures a stable identifier for the exact personality configuration being served. An explicitly authored `ai_base_prompt` remains unchanged.

### Truthful AI delivery and fallback

Production dispatch now returns an explicit result with content, source, provider, error code, and internal error detail. The chat turn treats a failed RAG retrieval as a reason to try ordinary AI before any fallback. A provider error or empty provider response is recorded as `fallback`, not mislabeled as an AI personality response.

## Checks run

- `php -l includes/class-flosc-chatpack.php`: passed.
- Focused call-path check: follow-up invokes `build_identity_section()` with the current flow ID: passed.
- `php -l includes/flosc-personality-library.php`: passed.
- Isolated save-contract check with WordPress-compatible stubs: passed.
  - authored profile preserved
  - SHA-256 profile hash generated
  - repeated save produced the same hash
- `php -l includes/class-ai-chat-dispatch.php`: passed.
- `php -l includes/chat-turn/trait-flosc-chat-turn.php`: passed.
- Focused dispatch-path check: RAG failure falls through to ordinary AI and provider failures are explicitly labeled: passed.

## Checks deferred

These require an authorized WordPress runtime and are not represented as passed:

- clean installation and activation
- database persistence through a real WordPress option store
- BubblyBetty -> DadJokeDan -> BubblyBetty mid-chat switching
- first and follow-up visitor turns against a live provider
- provider/RAG failure behavior
- Plugin Check, WP_DEBUG, accessibility, and Starter Pack lifecycle checks

## Packaging status

The installable artifact is [flosc.zip](flosc.zip). It contains 241 entries under one top-level `flosc/` directory.

- SHA-256: `50f27baa6faac8524e4fad35394a16833239785c1b4b7233d94ce10d111d5938`
- Checksum file: [SHA256SUMS](SHA256SUMS)
- Build metadata: [build-manifest.json](build-manifest.json)

The archive passed integrity and prohibited-path scans. It excludes source-control, logs, credentials, database exports, tests, and development artifacts.
