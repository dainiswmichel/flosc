# Home Run Candidate v33 — Codex

**Status:** Build complete and ready for the Captain's personality-variable testing.

**Built by:** Codex (OpenAI), GPT-6

**Build record:** `2026-09-11d-UTC09h:52m05s`

**Baseline:** Home Run Candidate v31 by Grok 4.6, commit `8a6b3f5`

**Reference reviewed:** Home Run Candidate v32 by Claude Opus 5, commit `fbc56c48`

v33 is built directly from v31. Claude's v32 was used only as a reference for the personality-variable feature. Grok's and Claude's candidate directories were not modified.

The four shipped personalities are byte-for-byte unchanged from v31. Their editorial revision remains a separate, Captain-guided task, one personality at a time.

## Personality variables

The DA1 Personality Designer now shows the variables that can be typed into personality cards. Flow values show their current value; visitor values state that they resolve from the current turn.

At runtime:

- the stored personality remains unchanged;
- the current profile is loaded on every AI turn;
- only recognized variables present in that profile are resolved;
- substitution runs once on the request-specific copy;
- a profile with no recognized variable causes no variable data lookup;
- visitor, page, quiz, and session values come from FLOSC's existing turn context;
- name fields reuse the `WP_User` object already loaded by the turn handler;
- `{current_url}` reads the real `browsing_page_url` context key;
- score and quiz aliases are normalized consistently;
- unavailable recognized values become `not available`;
- unrecognized braces remain unchanged;
- replacement values are bounded, stripped of control characters and braces, and cannot trigger a second substitution pass.

The catalog contains 31 variables backed by verified flow/site values or existing turn-context fields. Claude's speculative variables without a proven runtime source are not advertised.

## Verification

- 33 PHP regression scripts passed.
- All 184 PHP files passed syntax checks; the final edited PHP file was linted again after the last change.
- Personality-builder and FLOSC application JavaScript passed `node --check`.
- The exact ZIP activated under WordPress 7.1 in the isolated test site.
- A real Chatpack build expanded a Tim-shaped logged-in context, including URL and score, while the stored profile retained its tokens.
- WordPress Plugin Check 2.1.0 `plugin_review_phpcs` passed the exact ZIP with no errors.
- The ZIP passed the fail-closed distribution build and a second build matched byte for byte.
- The shipped-personality hash matches v31.

The installable artifact is `flosc.zip`; it contains one top-level `flosc/` directory. Plugin version remains `8.0.0` because this is a candidate, not a release.

The full Plugin Check aggregate scan was attempted but did not finish within the local review window. v31's aggregate scan was already clean, and v33's changed runtime files pass the WordPress.org review PHPCS profile. The aggregate scan should be rerun before the final resubmission assertion.
