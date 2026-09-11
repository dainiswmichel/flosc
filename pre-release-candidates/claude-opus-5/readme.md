# FLOSC 8.0.0 — candidate v35

Built from v34. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     63afe4ec8e2535f1e54314b3bbb45fd317eb5877301521cebb237142c21cd2b8
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

Three tasks closed.

## The fourteen headings

    SOUL       6   Identity and Role
               12  Mission, Philosophy and Values
               18  Boundaries and Prohibitions
               24  Knowledge, Doubt and Correction
               30  Opinions, Traits and Preferences

    CHARACTER  40  Tone and Communication Style
               48  Stance Toward the Human
               56  Decisions and Behavior in Ambiguity
               62  Adaptation, Exceptions and Infrequent Cases

    BEHAVIOR   68  Workflow and Resourcefulness
               74  Banned Words and Fillers to Avoid
               84  Prosody and Syntax
               94  Output and Delivery

Plus Personalization at 1, the reserved Sticky for User slot.

Five relabels and one density swap. No id retires, so `ensureContainers()`
carries the labels to every saved personality on next open — no migration.

`AI Provider Parameters` moved from 94 to 98. At 94 it tied with Output and
Delivery, and a tie sorts on the alphabet, which put the knobs above the last
thing the model reads.

## One name per value

`{title}`, `{product_name}` and `{app_name}` all resolve to the public title.
All three keep resolving — flow files, IVR greetings and the accuracy-test
templates documented in `admin/docs` use them — but the catalog marks them as
aliases and the designer's variables panel lists one name per value instead of
four names for one.

## The designer saves what it builds

`libraryEntry()` has always built a complete entry: traits, mission,
boundaries and topic scope alongside name and role. Only the downloadable
builder state read it. The save sent four keys and those four were not among
them, so every personality the designer ever made left them empty.

`ai_boundaries` and `ai_topic_scope` reach the model on every turn. So a
floscAdmin had no way to set two values the AI was already being given, and
`{topic_scope}` resolved to nothing on any flow that had not hand-edited a
`flow_ivr.md`.

The builder now exposes `libraryEntry` on its api, the bridge reads it rather
than rebuilding it, and the save handler stores all four. The Scope input
already existed — under Boundaries and Prohibitions in the designer — it just
had nowhere to go.

## The profiles, touched only where the headings forced it

This is not the full revision. Three profiles had `Philosophy and Values`
renamed to `Mission, Philosophy and Values`, label only, densities untouched.

Dad Joke Dan's three jokes followed their workshop cards from Decisions to
Tone at 45, 46 and 47, because the id they were filed under is Prosody and
Syntax now. His two parked joke slots moved to 43 and 44, so switching one on
puts it in the run it belongs to.

## Verified

28 gates pass, PHP lint clean, JS clean, density nesting clean, forbidden-path
scan clean, version 8.0.0 in both files.

Three gates gained assertions: the fourteen labels and densities with no two
sharing a density; an alias resolves and is not advertised; every field
`libraryEntry()` computes is sent, read and storable.

One gate moved deliberately. `check_php_string_literals.php` pins the four
profiles by hash — a drift guard, not a freeze. Three hashes moved with the
approved rename, in the same commit as the text.

Deferred to a WordPress install: Plugin Check, and a designer round trip —
open a personality, set Scope under Boundaries and Prohibitions, save, and
confirm `{topic_scope}` resolves in a card.

## Not in this build

The four profiles still carry densities that predate the heading map. Filling
all fourteen headings, and reseating those densities, is the revision pass.
