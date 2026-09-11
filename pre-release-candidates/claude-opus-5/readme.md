# FLOSC 8.0.0 — candidate v36

Built from v35. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     c59124bdbead6b0e6d82409a8b4f137c26579e83373df828de4a5bc234650067
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

Rename, do not restructure. Three edits in the designer's editor and one CSS
class. No state key renamed, no storage column renamed, nothing added or
removed — only where a field sits and what its label says.

## Goals is Mission

The field under *Mission, Philosophy and Values* was labelled **Goals**, with
the placeholder *"mission, not the full law"* — the correction written into
the hint instead of the label, while the value saves as `ai_mission`. Three
names for one thing.

It now reads **Mission**, hint *"what this conversation is for"*. The state key
`goals` and the column `ai_mission` are unchanged; nobody sees either.

## Scope sits with Mission

Scope was seventh in a seven-field Boundaries panel, behind Core values,
Prohibitions, Interaction policy, Invariants and Defaults. It answers the
question its new heading asks, and it reaches the model on every turn as
**Topic Scope** — which it did not look like, buried in that list.

## Identity splits into three

That panel held three different kinds of thing in one column: filing details,
a nameplate, and who this personality remains under probe. *BubblyBetty* and
*"who you are when someone tests you"* are not the same altitude, and as one
list they read as equals.

Same fields, same keys, three labelled groups:

    FILING       Id (slug) · Library label · Profile version · Install-private
    NAMEPLATE    Name · Role
    UNDER PROBE  Identity lock · If asked "Is this [name]?" · If asked to describe yourself

The Name hint changed with it — from *"chat header; this personality
introduces itself as this name"* to *"what it is called — BubblyBetty, Tech
Agent"*, because the altitude was the confusion.

## One gate added

`check_attachment_save.php` now pins that every stored field a floscAdmin is
expected to fill has somewhere to fill it — name, role, goals, prohibitions,
scope — and that Scope sits with Mission under the label Mission.

`ai_topic_scope` reaches the model on every turn and spent this entire cycle
with no path from the designer to the database. A field read at runtime with
no input anywhere is that bug's shape, so the input is pinned rather than left
to be noticed.

## Verified

28 gates pass on the first run, PHP lint clean, JS clean, density nesting
clean, forbidden-path scan clean, version 8.0.0 in both files.

Deferred to a WordPress install: Plugin Check, and a look at the two panels —
Identity and Role should show three labelled groups, and Mission, Philosophy
and Values should hold both Mission and Scope.

## Not in this build

The four shipped personality profiles. Friendly Guide is drafted across all
fourteen headings and waiting on the Captain's edits.
