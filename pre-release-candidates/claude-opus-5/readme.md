# FLOSC 8.0.0 — candidate v32

Built from the v31 candidate tree. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     dd647e8ba0f3cace069a56118ebd5bd24253fd346ea3a0bc235e1a8a7646a7b0
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

## What this adds

**Variables in the personality designer.** A floscAdmin can type `{flow_name}`
or `{score}` into any aspect card and it becomes the value when the
personality is sent to the model. Typing a token used to send the literal
seven characters.

This matters most on a cross-domain flow: a personality that says "this site"
names nothing, and now it can say `{site_name}` and be right on every domain
the flow serves.

Expansion happens on the request copy, every turn, because the attached
personality can be switched mid-session and the next turn must read the new
one. The stored `ai_base_prompt` keeps the tokens as written, so the designer
still shows what was typed.

Nothing opens a query of its own. `flosc_personality_variable_context()` maps
the evaluation context the chatpack and the dispatch already assembled. The
four tokens naming the visitor share one `get_userdata()`, cached, and only
when a document actually contains one of them. A document with no brace
returns before any work.

A substituted value is prompt text, not markup, so escaping is not the
protection that matters: braces are stripped so a value cannot introduce a
second token, control characters are stripped, and length is capped at 500.
An unresolved token substitutes empty.

**The designer shows them.** A Variables panel above the output pane lists
every token with what it resolves to for the flow being edited. Visitor
tokens say they resolve at chat time rather than showing a value invented for
a screen where no visitor exists.

## Carried forward from v31, unchanged

Sticky for User, the Personalization station at density 1, the oEmbed FAQ
entry, and the `flosc.php` work. The four shipped personalities are v31's,
untouched.

## One gate fixed

`tests/test_candidate_contract.php` was failing in v31. It pins the follow-up
chatpack call by exact string, and v31 added a third argument to
`build_identity_section()` for Sticky for User without updating the match.
v32 matches up to the comma. What the gate asserts — compact false, so the
whole profile goes every turn — is unchanged.

## Changed from v31

    includes/flosc-personality-library.php        catalog, resolver, context map, expander, boot
    includes/class-flosc-chatpack.php             passes the turn's context
    includes/class-ai-chat-dispatch.php           passes the turn's context
    assets/js/flosc-personality-builder.js        the Variables panel
    assets/personality-builder/…-markup.php       the panel mount
    tests/check_profile_variables.php             new gate
    tests/test_candidate_contract.php             match updated, intent unchanged

## Verified

All 28 gates pass, PHP lint clean across the tree, JS clean, density nesting
clean, readme within the wordpress.org budgets, starter-pack manifest checked
against the artifact, forbidden-path scan clean.

Deferred to a WordPress install: Plugin Check, and typing a token into a card
to watch it resolve in chat.

## Not in this build

The four shipped personality profiles are unchanged. Filling all thirteen
headings is separate work.
