# FLOSC 8.0.0 — candidate v37

Built from v36. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     79962075f7ab530da9d6d08bab208987488472a5d574650dff9c9e2b05e9a95c
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

## The gain ladder

Gain is a frequency in disguise — `frequency = (gain + 100) / 2`. The old
ladder had nine evenly spaced rungs, and four of its words named a comparison
or an attitude rather than a frequency: *less often than not*, *more often
than not*, *no preference*. A floscAdmin could not rank them, and they did not
mean what they sounded like.

Thirteen rungs, paired around the hinge:

    gain   freq   word
    -100     0%   never
     -90     5%   rarely
     -80    10%   infrequently
     -60    20%   seldom
     -50    25%   sporadically
     -30    35%   occasionally
       0    50%   sometimes
      30    65%   typically
      50    75%   usually
      60    80%   regularly
      80    90%   frequently
      90    95%   consistently
     100   100%   always

The uneven spacing is deliberate. `gainWord()` takes the nearest rung, so a
word owns the band to the midpoint of its neighbours — the rungs sit where a
word actually lives rather than where arithmetic put them.

**On the hinge.** A new card defaults to gain 50, not 0, and a card at 0 still
emits its frequency line. So 0 is a value someone chose and is being sent to
the model, not an absence — which is why *no preference* was the wrong word
for it, and *sometimes* is the right one.

## What that did to the four profiles

Twenty-two frequency lines changed, each recomputed from its own card's gain
in the workshop template rather than find-and-replaced.

The old ladder rounded every gain from 65 to 95 down to `usually`. That is why
the four documents said "usually" twenty-one times — for eleven different
authored values. The thirteen rungs tell them apart:

    before                  after
    21 × usually            6 × consistently
     7 × always            13 × frequently
     1 × often              3 × regularly
                            7 × always

Dad Joke Dan carried one hand-written `frequency: often`. That word is not on
the ladder, so the designer would never produce it and a Save would have
silently rewritten the document. It is now `regularly`, computed from that
card's gain of 60.

## Gates

`check_personality_document.php` holds the thirteen rungs, and now also
asserts that **every frequency word in a shipped profile is one of them** — so
a hand-written word the designer cannot produce fails the suite rather than
drifting until someone hits Save.

`check_php_string_literals.php` pins the four profiles by hash. All four moved
with the recomputed words, in this commit, per the rule in that file.

## Verified

28 gates pass, PHP lint clean, JS clean, density nesting clean,
forbidden-path scan clean, version 8.0.0 in both files.

Deferred to a WordPress install: Plugin Check, and dragging a card's gain in
the designer to watch the word track the number across the new rungs.

## Not in this build

The personality revisions themselves. Friendly Guide is settled and Dad Joke
Dan is drafted with five new jokes; neither is written to the file yet.
