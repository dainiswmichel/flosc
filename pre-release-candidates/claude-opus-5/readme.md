# FLOSC 8.0.0 — candidate v34

Built from the v33 candidate tree. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     fe3767b731d28108e22f7b7c4966e6b2e80a2feb0ed63093495229b191f6a7db
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

## What this changes

**An unresolved variable now leaves nothing behind.**

v33 substituted the literal string `not available` when a recognized variable
had no value, so a card reading *"You are helping them with {topic_scope}"*
reached the model as *"You are helping them with not available"* — which the
model reads aloud as though it were the answer. Empty leaves the sentence to
carry itself.

Three stand-in strings are gone: `not available`, `not provided`, and
`not signed in`. Four stay, because they are answers rather than unknowns — a
logged-out person really is a `Visitor`, `message_count` really is `0`, and
`guest` / `visitor` really are access levels.

**A quiz variable can name its quiz.**

    {score:ipa_basics}     that quiz
    {score}                the most recent quiz this person took

Any of the six quiz tokens takes the qualifier, and the same base token can
name two quizzes in one document. Two new tokens, `{quiz_id}` and
`{quiz_title}`, name which quiz the unqualified values came from.

The backend was already there: `FLOSC_Bridge_Data_Manager::get_flosc_bridge_data()`
does per-quiz when named and most-recent when not. A qualified token costs one
lookup per distinct quiz per request, cached, and is only reached when such a
token is actually in the document.

`{score:}` is malformed and is left exactly as written — the same rule already
applied to any unrecognized brace.

## Carried forward from v33, unchanged

The variable expander and token scanner, `current_url` from
`browsing_page_url`, the designer's variables panel, Sticky for User, the
Personalization station, and all four shipped personality profiles.

## Changed from v33

    includes/flosc-personality-library.php   the empty fallback, the qualifier, the quiz resolver
    assets/js/flosc-personality-builder.js   panel help text: the new rule and the qualifier
    tests/check_profile_variables.php        the contract, plus seven qualifier cases

## Verified

28 gates pass, PHP lint clean across the tree, JS clean, density nesting
clean, forbidden-path scan clean, version held at 8.0.0 in both files.

Deferred to a WordPress install: Plugin Check, and a flow with two quizzes to
watch the qualifier pick the right one.

## Not in this build

The four shipped personality profiles are unchanged. The fourteen-slot heading
set is agreed and not yet coded.
