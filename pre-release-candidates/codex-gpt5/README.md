# FLOSC 8.0.0 — Home run candidate v68

Built by Codex in `pre-release-candidates/codex-gpt5/`. Claude Opus 5 v67 was
used as a read-only reference and was not modified. The plugin version remains
8.0.0.

    artifact   flosc.zip
    sha256     5b0724a5111cd41e3be094d51a81e5a755286cf62076360066af975c0c593064
    bytes      2696623
    entries    259, single flosc/ root
    php files  149 in the artifact
    source     flosc-by-codex-gpt5/flosc

## Why v66 was not an adequate final candidate

v66 was declared ready before a complete semantic trace of request data to
storage and external-service sinks. That trace found real omissions: uploaded
knowledge text, nested Personality Workshop JSON, extensible model parameters,
quiz payloads, and the legacy visitor-audio route did not all cross equally
explicit, bounded boundaries. Several Settings API callbacks also unslashed
values a second time after WordPress had already done so, which could corrupt
legitimate backslashes.

The historical nine-rule checker still produces deliberate false positives for
WordPress core class files and for aliases whose individual fields are sanitized
after assignment. v68 does not alter correct filesystem or SSO allowlist code to
silence those patterns.

## What changed in v68

- Knowledge uploads and editor saves use one shared, bounded,
  Markdown-preserving sanitizer before disk writes.
- Personality Workshop and model-parameter JSON recursively sanitize strings
  and keys, retain scalar types, and reject excessive or unsupported shapes.
- Quiz request payloads are bounded and sanitized before hooks, transients,
  signed cookies, or user metadata; the direct bridge hook cannot bypass this.
- The legacy public audio-upload route checks actual byte length and WebM, Ogg,
  or MP4 container signatures instead of trusting labels or extensions.
- Nested quiz-map keys use Unicode-safe text sanitation, preserving multilingual
  words instead of erasing Latvian or German characters with `sanitize_key()`.
- Settings API callbacks no longer double-unslash values already prepared by
  WordPress. Secret values retain the tested exact-value policy.
- Public IVR offer-state input is allowlisted before forming metadata keys.
- Generic flat `flow_*` arrays ignore forged nested values and sanitize every
  accepted scalar without `Array to string` warnings.

All v66 personality create/edit/attach, provider-neutral grounding, live VGM,
Content-tab, editor-control, SSO, importer, and packaging repairs are retained.

## Verification completed

    PHP regression/check scripts                  39 passed, 0 failed
    JavaScript test scripts                        2 passed, 0 failed
    changed PHP and executed PHP tests             syntax clean
    official Plugin Review PHPCS, shipping source  0 errors, 0 warnings
    official Plugin Check PHPCS, shipping source   0 errors, 0 warnings
    both official rulesets on extracted ZIP        passed
    artifact entries                               259
    artifact root                                  flosc/
    artifact PHP files                             149
    forbidden test/vendor/.git ZIP entries         0
    plugin version                                 8.0.0

## Boundaries not falsely claimed

This candidate has not been deployed and has not touched a website database.
A clean WordPress `WP_DEBUG` browser pass, the database-backed Plugin Check UI,
and Dainis's human personality create/edit/chat tests have not been run in this
workspace. Those are live validation gates, not claims made by this build.
