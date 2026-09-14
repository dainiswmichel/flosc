# FLOSC 8.0.0 — Home run candidate v66

Built by Codex from the deployed `claude-opus-5` v64 source, retaining the
audited v65 Content-tab and editor-control repairs. All work is contained in
`pre-release-candidates/codex-gpt5/`; the Claude candidate is unchanged. The
plugin version remains 8.0.0.

    artifact   flosc.zip
    sha256     e43774f4bc15597d2b576f242163f9105adae17485cc68733d923509ac660739
    bytes      2692630
    entries    258, single flosc/ root
    php files  148 in the artifact
    source     flosc-by-codex-gpt5/flosc

## What v65 exposed

The poor “Bites” answer was not proof that the v65 controls alone caused the
behavior. The same architectural weaknesses existed in v64:

- WordPress site retrieval was optional/provider-specific. A provider could be
  given the personality and conversation but no matching indexed post, then
  invent a plausible site-specific detail from model training.
- Settings and Attach used a legacy-compatible IVR-to-option resolver, while
  runtime loaders could reconstruct `flosc_flow_{stem}` independently. On an
  upgraded site with duplicate/legacy rows, the admin could save one row while
  an AI turn read another.
- The personality designer saved an export document—including its human-facing
  wrapper/footer—as the runtime prompt, omitted several sidecar fields on
  creation, and re-versioned unchanged work because export receipts contained
  volatile values.
- Create ignored a failed Attach response, Attach could report an attempted
  update as success, and the underlying library writer did not confirm that its
  normalized document was actually stored.

## What changed in v66

### Personality create, edit, attach, and runtime

- Create and edit now save the compiled runtime profile, not the downloadable
  export wrapper, and both send the complete authored sidecars.
- Volatile workshop receipt/provenance data is removed before fingerprinting,
  so an unchanged save remains the same version.
- Save returns the stored version/hash, updates the visible revision state, and
  reports success only after exact normalized option read-back.
- Create verifies Attach before reloading; Attach rejects unknown library IDs,
  writes and reads back the resolved flow row, and clears the flow-row cache.
- Designer reload, personality resolution, framework flow loading, and message
  runtime now share the same legacy-compatible IVR option-row resolver.

### Provider-neutral site grounding and content safety

- Every configured AI provider receives the same server-selected site-index
  evidence before dispatch; `/chat` and the legacy `/chat-rag` URL use the same
  conversational engine.
- Search applies the active flow's live VGM/depth rules. Title-only rows cannot
  be matched by or reveal body-derived keywords, and retrieved content is
  explicitly treated as evidence rather than instructions.
- Missing, draft, private, trashed, and password-protected WordPress rows are
  omitted using one bounded live-state query. Tightened FLOSC access is checked
  live, and an existing index refreshes after post saves.
- The old live-post RAG fallback—which had a separate, weaker access model—was
  removed. Each returned body is capped at 4,000 prompt characters.

Public posts with no VGM rule still inherit FLOSC's existing default of full
retrieval. That is intentional, not a promise that all published content should
be public to an AI: a floscAdmin can set the Content-tab site default to title,
excerpt, read-more, or full, and object rules can narrow it further.

### Retained v65 admin repairs

- Member Levels saves preserve `content_default_vgm` when that form did not
  submit the field.
- Retrieval controls are reachable on posts, pages, and configured indexed post
  types; protection radios remain conditional and save only when submitted.
- Content-tab term/post rules edit their original metadata in place.

### September 13 WordPress.org automated-review repairs

- `Requires at least` is the accepted `7.0` release line in both headers.
- Unsupported direct WordPress Importer loading and the hardcoded importer
  plugin path are gone; staged WXR files use Tools → Import.
- SSO redirect approval no longer trusts the request Host header, and OAuth
  state is transient-only and one-use.
- Reviewed request inputs are acquired through WordPress unslashing plus
  type-appropriate sanitization; `FILTER_UNSAFE_RAW`/`FILTER_DEFAULT` are absent
  from shipped PHP.
- Knowledge-base editor content is type-, size-, UTF-8-, and text-sanitized
  before writing. User-audio AJAX now requires login, capability/ownership, and
  a nonce.
- Unreferenced static HTML documentation is excluded; the ZIP rejects raw
  script/style HTML assets. External-service disclosures were expanded.

## Verification completed

    PHP regression/check scripts       38 passed, 0 failed
    JavaScript test scripts             2 passed, 0 failed
    PHP syntax                         189 files clean
    official Plugin Review PHPCS        0 errors, 0 warnings (runtime source)
    exact built-artifact PHPCS          exit 0
    artifact entries                   258
    artifact root                      flosc/
    artifact PHP files                 148
    reproducible ZIP                   yes; identical SHA-256 on rebuild
    plugin version                     8.0.0 in header, constant, Stable tag

## Not yet claimed

This candidate has **not** been deployed, has not touched a website database,
and has not yet completed a clean-install `WP_DEBUG` browser run or the human
create/edit/chat tests on dainis.net. The PHPCS invocation is the official
Plugin Check package's Plugin Review ruleset, not a claim that the full
database-backed Plugin Check runtime was executed. Those live gates remain
required before deployment or WordPress.org resubmission.
