# FLOSC 8.0.0 — candidate v64

Built from v63. Version is 8.0.0 and does not move.

    artifact   flosc.zip
    sha256     6aa032b66bb3ec5a1f604156a8c024e3373ffe19c5628c16bad02c2589946404
    entries    277, single flosc/ root
    source     flosc-by-claude-opus-5/flosc

## Three classes of content

Access is not one scale. It is three different questions, and only the third
one is VGM.

**1. Yours.** The `internal` category — the rolodex. No flow, no personality,
no AI provider, ever. Not indexed, not retrieved, not named. It has no VGM
value because VGM does not reach it.

**2. FLOSC's plumbing.** Concierge entries and trajectories. The flow
personality and the provider *do* see these — that is what they are for — but
through their own readers, `admin/concierge.php` and
`includes/flosc-personality-library.php`, which take the bare category names
`concierge`, `trajectory` and `trajectories` alongside the prefixed ones. They
never enter the content index, so the same text can never come back out to a
visitor as retrieved content.

**3. Site content.** The table below.

`is_internal_post()` now matches `internal` as well as `flosc-internal*` and the
three bare aliases. Exact match on the aliases, so `internal-notes`,
`international` and `concierges` are somebody's own categories and stay indexed.

## What was leaking

`search()` and `format_map_for_ai()` ran `sanitize_key()` on a row's access
value before comparing it. That strips the space:

    "guest member"  →  sanitize_key()  →  "guestmember"

`vgm_list()` finds no level in that, returns an empty list, and `access_allows()`
reads an empty list as *nobody gated this row* — so it returns **allowed**. A
row restricted to guests and members was handed to a logged-out visitor, body
and all. v61 fixed the comparator and left both callers destroying the value on
the way in.

The comparator is the sanitizer now. Both call sites pass the stored value
through untouched, and the gate has a source guard so the call cannot come back.

## Access has two axes

**Who** — the tier, and the tier is a floor:

    Visitors    also covers guests and members
    Guests      excludes visitors
    Members     excludes both

**Available** — how much of the post that tier gets:

    Title only
    Title and excerpt
    Through the read-more break
    The whole post

A rule is one of each, attached to one scope. **Most specific wins**: a rule on
a post beats one on its tags, which beats one on its categories, which beats the
site default. Several rules on the *same* scope combine per tier, so one post can
be read-more for visitors and full for guests.

Deciding rather than merging is what makes both directions possible. A post rule
can open a post its category closed, and it can close one its category left
open. A merge could only ever do the first.

## Where a rule is written

Three screens, one meaning, one resolver:

- **Post and page** — the FLOSC visibility metabox, under *AI retrieval*. The
  radios above it still govern the page; these two govern what chat may quote.
- **Category and tag** — a *FLOSC AI retrieval* field on the term edit screen.
- **Content tab** — the site overview: the default row at the top, the rule
  table below it with the two new columns, and a read-only listing of every rule
  set on an object's own screen so the tab shows the whole picture.

Both halves must be set for a rule to count. A post carrying a tier and no depth
is somebody half-way through a thought, and treating it as a rule would silently
gate the post.

The site default ships as the whole post for everybody, because published is
public. `all titles VGM` and `all excerpts VGM` are that one row.

## What nothing changes

A rule stored before these two columns existed means exactly what it has always
meant — gated, members only, whole body once cleared — so no rule on dainis.net
changes meaning until somebody edits it. An index file written by an earlier
build still answers, at the two depths it could express. And the derived tier
still **clamps** the result: a rule can never hand out a body the page itself
would refuse to render.

## Verification

    test suite            0 failing gates
    php -l, whole tree    clean
    node --check          clean, builder and app
    zip gates             277 entries, single flosc/ root, no forbidden paths
    version               8.0.0 in header, FLOSC_VERSION and Stable tag

`tests/check_access_vgm.php` executes the real functions, lifted out of the
class by name: the tier floor, rule folding, scope precedence in both
directions, a rule written on the object vs in the table, the site default, the
read-more slice, legacy rows, and the call-site guard.

Deferred to a live install: WordPress Plugin Check, a rebuild, and reading the
Access column — `visitor` on the bees post and the songs, `member` on lesaep
lessons, and no `internal`, concierge or trajectory rows in the table at all.
