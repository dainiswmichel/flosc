# FLOSC 8.0.0 — candidate v46

Built from v45. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     d61dd4fc7a34234547a9b754212dcb13e48bfce6c6315d5f599d1c9a2e482363
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

## What went wrong

A visitor asked Br3nda about a song, and was shown this:

> Thanks for your interest! Try one of the suggestions above. This is just
> IVR-style copy — remember to configure your preferred AI API for much more
> intelligent responses!

The AI provider was configured and working the entire time. An internal
ten-question test passed 10/10 on the same flow.

The provider answered. The plugin threw the answer away.

`trait-flosc-chat-turn.php` runs a reputation guard right after the provider
call. The guard matches the reply against a hedge pattern — `i don't have`
followed within 160 characters by `context`, `details`, `information` and
friends — discards the whole reply, and hands off to a replacement chain. That
chain tried a catalog reply, tried a bio reply, and then fell through to the
IVR phase defaults, which append a note written for the site owner.

**The cause was upstream of the guard.** Three of the four shipped profiles
instructed the model to narrate the gap:

    Friendly Guide   "If you do not know, say so and point to the next place…"
    BubblyBetty      "Say you do not have it, then help with what you do."
    Tech Agent       "Do not narrate gaps" — right intent, weaker wording

The profile asked for the sentence, the model produced it, the guard killed it,
and the visitor was told the site was unconfigured. The profile and the guard
were fighting each other.

## The card

The Captain's text, verbatim, in all four personalities at density 24:

    ## 24 Never narrate a gap
    short: Never spend a sentence explaining what you do not have. Say what you
    can do, then ask what they are looking for. Do not make excuses for what you
    don't have or don't know, instead, seek to understand and provide.
    frequency: frequently

Gain 75, which resolves to **frequently** on the ladder — the nearest rung is
80, and 60 is further away.

It is in both representations, the `ai_base_prompt` block and the workshop
card, so the designer shows what chats receive.

The three contradicting cards are replaced rather than kept alongside. Dad Joke
Dan's "No false facts in a gag" is a different concern — not inventing product
facts inside a joke — so it stays, moved from density 24 to 25.

## The IVR leak

Two changes in `flosc.php`, independent of the personalities:

**Canned copy can never displace a real reply.** The replacement chain takes an
`allow_phase_default` flag. When the provider already answered, the chain stops
before the phase defaults: a catalog or bio reply may still stand in, because
those are real answers, but when neither matches the provider's own words ship
instead of being discarded. The genuine IVR-only path and a provider that
returned nothing at all both still reach the phase defaults, which is what they
were written for.

**The API-key note is admin-only.** `$ai_hint` is now behind
`current_user_can('manage_options')`. It is addressed to the floscAdmin, so
only the floscAdmin sees it.

## The designer UI

Clicking **+ Aspect** put an untitled aspect in the palette column, and trying
to select its title — or click into any field in "Edit this aspect" — started
dragging the card instead.

The palette card carried `draggable="true"` on its outer container, wrapping
the label, the checkbox and the whole edit panel. A draggable container
swallows every mousedown inside it. The density row renderer never did this;
it put `draggable` only on its handle. The two renderers disagreed and the
palette one was wrong.

`draggable` is off the container now, so only the two drag handles are
draggable and both renderers behave identically. `dragstart` additionally
refuses to begin inside an input, textarea, select, option, label or
contenteditable, so a stray draggable ancestor can never eat a click again.

## Read this before testing on a live site

**The shipped defaults do not overwrite an existing library.**
`flosc_personality_library_get_all()` seeds them only when the `wp_options` row
does not exist. dainis.net already has that row, so installing this zip will
**not** change the four personalities there.

To force a reseed — this destroys any personality edited on that site, which is
why it is a manual step and not code:

    wp option delete flosc_personality_library --path=/home/dainisne/public_html

then load any FLOSC admin page.

**Br3nda is not in this zip.** She is site-local and carries her own heading 24,
so the new card reaches her only when she is edited in the designer. The IVR
leak fix is code and reaches her the moment the plugin is deployed.

## Verification

    test suite            0 failing gates
    php -l, whole tree    clean
    node --check          clean, builder and app
    density nesting       all green
    zip gates             277 entries, single flosc/ root, no forbidden paths
    version               8.0.0 in header, FLOSC_VERSION and Stable tag

Deferred to a live install: WordPress Plugin Check, and asking Br3nda something
she does not have — she should say what she can do and ask what you are looking
for, with no IVR copy and no API-key note.
