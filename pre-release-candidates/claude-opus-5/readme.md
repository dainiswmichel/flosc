# FLOSC 8.0.0 — candidate v38

Built from v37. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     088d18b6a3addd5fa8dcbf9d5b36fc7fb060a161d93f70726033af847972c3ee
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

**All four personalities are revised.** Coverage went from 3, 3, 3 and 5 of
thirteen headings to fourteen of fourteen each.

    Friendly Guide   22 cards
    Tech Agent       23 cards
    BubblyBetty      20 cards
    Dad Joke Dan     27 cards, eight of them jokes

## Read this before testing on a live site

**The shipped defaults do not overwrite an existing library.**

```php
$raw = get_option( $key, false );
if ( false === $raw ) {          // only when the option does not exist
    $raw = flosc_personality_library_defaults();
```

dainis.net already has that option, so installing this zip will **not** change
the four personalities there. To force a reseed:

    wp option delete flosc_personality_library --path=/home/dainisne/public_html

then load any FLOSC admin page. That destroys any personality edited on that
site, which is why it is a manual step and not code — auto-overwrite would
wipe a floscAdmin's work on every install.

## Nothing was deleted

Ten cards existed in the workshop and never reached their documents — seven in
Tech Agent (`popper`, `one_reality`, `tell_the_truth`, `kind`,
`open_continue`), three in BubblyBetty (`nervous_system`, `relax`,
`sales_host`). All are in the documents now, filed under the heading that fits
them.

## Document and cards are generated from one source

Both come from the same data, so the designer shows exactly what chats
receive. Before this, a personality whose document carried cards the workshop
did not would lose them on the first Save — which is what would have happened
to anyone testing the old profiles in the designer.

Every `frequency:` line is computed from its own card's gain, never typed.

## Absolutes are rare now

Two cards per personality carry `always` — identity under probe, and the one
real prohibition. Nothing carries `never`.

## The jokes

Eight, on the Tone shelf at densities 45.1 through 45.8, gain 70 —
`regularly`, 85%. Five of them new. Format is the Captain's:

    instruction: Joke set up — "What's the funniest preposition?"
    Punchline — "Over and PUNder."
    short: Direction, position, or the follow-up when PreposishPUNS lands.
    frequency: regularly

## Clouds emptied

The cloud groupings named cards that no longer exist after the rewrite, and
they compile as their own sections. The fourteen headings are the grouping
now.

## Verified

28 gates pass, PHP lint clean, JS clean, density nesting clean,
forbidden-path scan clean, version 8.0.0 in both files.

Deferred to a WordPress install: Plugin Check, and a designer round trip —
open each personality, confirm fourteen shelves with cards on them, Save, and
confirm the document does not change.
