# FLOSC 8.0.0 — candidate v65

Built by Codex from the deployed `claude-opus-5` v64 source. All work is
contained in `pre-release-candidates/codex-gpt5/`; the Claude candidate is
unchanged. The plugin version remains 8.0.0.

    artifact   flosc.zip
    sha256     b4fd6fe1af4e86657960024b634dc29cbd8990ccf074239db98cbd5a9f7e0433
    bytes      2751019
    entries    277, single flosc/ root
    source     flosc-by-codex-gpt5/flosc

## What was wrong in v64

1. Saving Member Levels posted no `content_default_vgm` field, but the shared
   save branch still replaced every configured site-default depth with `full`.
2. The AI-retrieval metabox was registered only for posts in a category already
   marked FLOSC-protected. Pages and ordinary indexed posts could not reach it,
   and rendering the metabox everywhere would have made the absent protection
   radios write a default `protected` mode.
3. Rules stored on category, tag, post, and page objects appeared read-only on
   the Content tab.

## What changed in v65

- `content_default_vgm` is written only when the submitted form actually
  contains that array, so Member Levels saves preserve the configured default.
- The metabox is registered unconditionally for posts, pages, and configured
  indexed post types. Existing page-protection notice and radios render only
  for posts in a protected category; AI-retrieval tier and depth controls render
  for every supported object.
- The save handler writes protection mode only when its radios were submitted.
- The Content tab edits object rules in place using `term:<id>` and `post:<id>`
  keys. Values pass through the index class's shared tier/depth token validators
  and update the original term or post metadata. Clearing either half removes
  both halves of the rule; no duplicate `protected_content` writer is created.
- Regression guards cover the default-save condition, post/page metabox reach,
  absent-radio behavior, editable object fields, and shared metadata vocabulary.

## Verification

    PHP regression scripts   34 passed, 0 failed
    JavaScript test scripts   2 passed, 0 failed
    PHP syntax               185 files clean
    artifact entries         277
    artifact root            flosc/
    artifact PHP files       148
    reproducible ZIP         byte-for-byte match
    plugin version           8.0.0 in header, FLOSC_VERSION, Stable tag

The exact built ZIP passed the packaging gate. No deployment or database work
was performed for this candidate.
