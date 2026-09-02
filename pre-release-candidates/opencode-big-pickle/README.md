# FLOSC Deployment Candidate — opencode / big-pickle

**Agent name:** opencode
**Exact agent/model:** opencode (powered by big-pickle)
**Agent slug:** `opencode-big-pickle`

This is a **complete, independently deployable FLOSC WordPress plugin candidate**, not a patch, code review, or proposal. It was generated mechanically from the pinned baseline Git object and modified entirely within this assigned directory.

As requested in the shared assignment brief:

> Other AI agents creating FLOSC deployments should model the shared candidate directory structure and place their installable artifact at the root of their own agent directory under the exact filename `flosc.zip`.

---

## Identity

| Field | Value |
|---|---|
| Agent name | opencode |
| Exact agent/model | opencode, powered by big-pickle |
| Agent slug | `opencode-big-pickle` |
| Baseline commit | `03f4b926c5502b7d2af0136fc1bac8359286b9f8` |
| Candidate status | `candidate` |

## Artifact

| Item | Value |
|---|---|
| Source directory | `pre-release-candidates/opencode-big-pickle/flosc-by-opencode-big-pickle/flosc` |
| Installable artifact | `pre-release-candidates/opencode-big-pickle/flosc.zip` |
| SHA-256 | `2b2fc16e3849b0fa0e66d9476dd21f5626645bbab0d34bd992b044f6b3befda3` (see `SHA256SUMS`) |
| ZIP entry count | 275 files |
| ZIP top-level | exactly one directory: `flosc/` |

The ZIP contains exactly one top-level directory named `flosc` (so WordPress installs it as `wp-content/plugins/flosc/`). Its internal structure is `flosc/flosc.php`, `flosc/readme.txt`, `flosc/admin/`, `flosc/includes/`, etc. It does **not** contain `flosc-by-opencode-big-pickle/flosc/` as internal nesting, nor `.git`, `.github`, logs, credentials, database exports, tests, composer files, sample-data, or canonical repo scaffolding — verified by inspecting the ZIP itself.

---

## What this candidate changes (relative to the pinned baseline)

The changes are deliberately small and surgical — the baseline already has the correct architecture (store personality ID in the flow bag, resolve fresh at call time, compose via chatpack, dispatch with `response_source`). This candidate hardens the three things that determine whether the WOW experience works and whether a failure is honest:

### 1. Deterministic personality compiler with atomic save — `includes/flosc-personality-library.php`

- New `flosc_personality_compile($genome)` — idempotent and **non-destructive**:
  - If the genome carries an explicit `ai_base_prompt` (bundled rich profiles like BubblyBetty / DadJokeDan, or an admin-authored profile), it is kept **verbatim** — never regenerated. This is essential: hand-written profiles encode voice, sales technique, and emoji rhythm that cannot be reproduced from the basic fields.
  - Only when `ai_base_prompt` is empty does it synthesize a deterministic profile from the structured fields (name, role, traits, mission, boundaries, scope, off-topic, fallback).
  - It **always** computes `profile_hash = SHA-256(final profile)`, so the fingerprint and the runtime profile can never drift apart.
- Added `profile_hash` to the personality-library field keys.
- `flosc_personality_library_save_all()` now runs the compile on the resolved entry (which preserves the prior `ai_base_prompt`), writing `ai_base_prompt` and `profile_hash` together — atomic. Upgrades/additions never overwrite an admin's saved personality.
- New `flosc_personality_profile_hash($flow_id)` accessor, with a live fallback that hashes the resolved profile when no stored hash exists yet.

### 2. Mid-chat switching actually works on follow-up turns — `includes/class-flosc-chatpack.php`

- New constant `FLOSC_Chatpack::FOLLOWUP_IDENTITY_BYTES = 1400` bounding the per-turn re-anchored voice.
- Rewrote the compact (follow-up) identity section. It previously said *"continue — same person as the opening turn"* and *"the opening-turn profile still applies"* — which actively told the model to **stay the old character** even after an admin switches the personality mid-conversation. It now:
  - Resolves the **current** attached personality fresh this turn (the flow stores only `personality_library_id`, so this reflects an admin switch immediately).
  - Re-anchors the current personality's voice (name, role, traits, mission + a bounded voice-profile block) so multi-turn stays vivid and a switch is honoured.
  - Drops the "same as opening turn" assumption, enabling Betty → Dan → Betty without a reload or new conversation.
- The re-anchored voice is byte-bounded to keep per-turn input-token cost controlled (the baseline's known bloat was ~6.3KB/turn; this caps the re-anchored portion).

### 3. A provider failure can no longer masquerade as a personality response — `includes/chat-turn/trait-flosc-chat-turn.php`

- In the non-RAG dispatch path, `get_response()` can return a `WP_Error` on provider rejection/timeout/empty response. `WP_Error` is **truthy**, so the old `if (!$ai_response)` / `$ai_response ? 'ai' : 'fallback'` logic treated a failed provider call as a *successful* AI response and returned apology copy labeled as `ai` — exactly the "failed request masquerades as a weak personality response" class of bug.
- Now a `WP_Error` is unwrapped and recorded as a provider failure; a quiz fallback is attempted; the visitor receives an honest, visitor-safe "unable to reach assistant" message; and `response_source` is correctly `fallback`, not `ai`.

---

## Phases attempted (P0–P8 mapping)

| Phase | Status | Notes |
|---|---|---|
| P0 | not-applicable-to-code-candidate | P0 is a capture/reproduce phase, not a code task |
| P1 | partial | reliable dispatch (WP_Error unmasking) done; full fixture suite deferred to runtime |
| P2 | implemented | deterministic compiler, atomic save, profile_hash, preview-of-truth path |
| P3 | partial | chatpack re-anchor per turn + bounded; shared-policy-blob split not done (see known defects) |
| P4 | implemented | resolve fresh + re-anchor current personality; admin-driven switch honored |
| P5 | not-started | model-aware tuning untouched by this candidate; deferred |
| P6 | deferred-runtime-verification | Starter Pack lifecycle code unchanged; runtime install/repair/remove test deferred |
| P7 | not-mutated | Br3nda distribution untouched; the contract still supports bundled/optional/private |
| P8 | partial | packaging + ZIP inspection done; security/privacy/accessibility audits deferred |

---

## Checks run / passed

- `php -l` syntax check passed on all three changed files.
- Isolated deterministic-compiler test (mocks WP sanitizers) passed:
  - Rich profile preserved verbatim and hashed correctly.
  - Empty profile synthesizes deterministically (same bytes, same hash across calls).
  - Distinct genomes produce distinct hashes.
- ZIP structure inspection passed: single top-level `flosc/`, no forbidden/nesting/repo artifacts, and the artifact provably contains all three source changes.

## Checks failed

- None.

## Checks deferred — AUTHORIZED REMOTE TEST REQUIRED

- WordPress installation and activation
- Plugin Check (requires WordPress)
- `WP_DEBUG` runtime behavior
- Live AI provider requests
- Real database persistence
- Browser behavior / administrator–visitor parity
- Betty → Dan → Betty mid-conversation interaction
- RAG and provider failure simulations
- Accessibility interaction testing
- Starter Pack install/repair/remove/reinstall on a fresh database

None of the above is claimed to have passed.

---

## Known risks and limitations

- **Shared sales policy is duplicated inside the bundled profiles.** BubblyBetty (~6.3KB) and DadJokeDan (~6.4KB) each inline the generic FLOSC "Always" sales/truth policy that belongs in a single shared Layer-2 policy. This is the baseline's known bloat and is not refactored out of the heredocs in this pass (splitting is higher-risk than the surgical changes here, and doing it wrong would regress the first-turn voice). The follow-up re-anchor cap mitigates the token cost.
- **Follow-up re-anchor truncation.** When a profile exceeds 1400 bytes the follow-up voice is cut at a byte boundary. Verified the two bundled profiles remain unmistakably distinct within the first 1400 bytes, but a custom long profile could be truncated mid-instruction.
- **Pre-existing saved rows lack a stored `profile_hash`** until next save; the resolver hashes at read time as a fallback, so behaviour is correct but the stored fingerprint appears after the next save.
- Runtime behaviour (switching, dispatch, persistence) is not proven without a WordPress environment; see deferred list.

## Confirmations

- **Canonical FLOSC was not modified.** All work lives inside `pre-release-candidates/opencode-big-pickle/`.
- **Other candidates were not modified.**
- The `flosc.zip` contains exactly one top-level directory named `flosc/`.

## Reproducibility

```text
Prepared from baseline git object: 03f4b926c5502b7d2af0136fc1bac8359286b9f8
(zip -X -r -9 flosc.zip flosc from the staged runtime tree, exactly as
 build-dist-zip.sh does, with the WordPress.org-safe deny/exclude lists)
```
