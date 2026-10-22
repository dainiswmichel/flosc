# FLOSC Starter Packs — current state and revision roadmap

Measured against the shipped tree in `pre-release-candidates/claude-opus-5/flosc-by-claude-opus-5/flosc`,
which is canonical v184 plus the v185 code fixes plus Dainis's v184.2 DA1 pack work.

Every number below was measured, not estimated. Routing results come from running the real
`FLOSC_DA1_Catalogs` matcher against the real `catalog.txt` for each pack, at each access level.
Anything unverified is marked as such.

Work order, as set by Dainis: **DA1 → Membership Craft → Vegan Latvian Kitchen → WordPress Content
Membership.**

---

## Cross-pack findings

These apply to more than one pack and are worth deciding once.

| # | Finding | Affects |
|---|---|---|
| 1 | **Two of four packs ship no `catalog.txt`.** Membership Craft and WordPress Content Membership have no DA1 catalog at all, so 13 of their AutoPrompt pills cannot demonstrate catalog behaviour — the feature a reviewer is most likely to click. | mcraft, wcmj |
| 2 | **Only DA1 declares an offer.** `offer_*` keys appear in DA1's Settings YAML only. The other three packs' `pack.json` describes a conversion, but the flow file declares no offer, so nothing is priced or activated on install. | mcraft, vegan, wcmj |
| 3 | **Only Vegan ships an access code** (`garšīgs`). DA1, Membership Craft and WCMJ have none, so the only way through their paywall is real payment credentials — which a reviewer will not configure. | da1, mcraft, wcmj |
| 4 | **No pack sets `visitor_depleted_contact_mode`.** It defaults to `message`, so the in-chat contact form — a genuine FLOSC feature — never appears in any reviewer journey. | all four |
| 5 | **No pack sets `visitor_session_end_redirect_url`.** This is correct and should stay that way: a value there ejects the visitor from chat 1.2s after they submit the contact form. | all four |
| 6 | **Personality is declared in two places.** `pack.json` carries a `personality` field and the flow file carries `personality_library_id`. Vegan's flow file declares neither. Not verified which one the installer honours. | vegan |

---

## 1. DA1 Catalog Sales Journey

### Current state

| | |
|---|---|
| Flow installed as | `dcsj_ivr.md` |
| Personality | `dadjokedan` |
| Catalog | 50 rows, key `dcsj_extremely_ordinary_manuals` |
| Row visibility | 4 rows Visitor+Guest+Member · 4 rows Guest+Member · **42 rows Member only** |
| Posts | 10, in one category |
| Media | `UberManual.pdf` |
| Flow file | 255 lines, 20 message blocks, 16 AutoPrompt pills |
| Offer | `dcsj_ubermanual`, The UberManual, one-time, **$10 USD**, processor `paypal`, grants `member`, active |
| Guest token grant | `10000` — verified real, imported, applied once per flow as `visitor_remaining + grant` |
| Access code | **none** |
| Routing | **16 of 16 pills behave as intended** (14 reach the catalog; 2 deliberately do not — see below) |

The 10 posts are not the manuals. They are DadJokesDan's editorial layer about the collection:
*Fifty Manuals Nobody Asked For · The Jar Has Taken a Position · Step Four Is Just Giving Up ·
Introducing the UberManual · What the Guest Shelf Adds · The Road Map Problem · On Being Read to
Sleep · The Full Shelf · Manual 50 Is About Manual 50 · How This Journey Was Built.*

### What a reviewer sees today

1. Installs the pack. Picks an AI provider and pastes an API key.
2. As a **visitor**: Dan names Items 1, 11, 21, 35. Four pills reach the catalog; one asks about the offer.
3. Registers → **guest**: gains Items 2, 12, 22, 36 and 10,000 tokens. Four content pills plus the offer.
4. To reach **member**: must configure PayPal. **There is no other way through.**
5. As **member**: all 50 rows, Item 50 named in the copy.

### Already changed in this lane

- Two pills read `Show me the $10 UberManual`, which reached the catalog instead of the offer because
  "manual" matches the catalog's Item Type. Measured, a guest saw *"Here are 2 catalog items: 1.
  Household Three-Hole Punch User Manual for Beginners, 2. Advanced Techniques for Folding Fitted
  Sheets"* — and the offer copy never rendered, because the catalog reply overwrites the response.
  Both pills are now `What does $10 get me?`, measured to produce no catalog reply at visitor and
  guest level, so the offer answers.

### Revision roadmap

| # | Change | Kind | Status |
|---|---|---|---|
| 1.1 | `offer_access_codes: "GETFLOSCD"` in the Settings YAML. The mechanism exists (`admin/offers.php:115`, in the import whitelist) and `handle_redeem_access_code()` grants the level, so a reviewer reaches member with **no payment credentials**. | 1 YAML line | queued |
| 1.2 | Set all 50 `VGM` cells to `Visitor Guest Member`. Row visibility is all-or-nothing on that one column, and the row carries only Title plus a 180-character teaser — the product is `UberManual.pdf`, which stays gated. This makes every title a teaser at every tier, which is the drip-feed mechanic. | 50 data cells | queued |
| 1.3 | Rewrite Dan's tier lines. Once titles are open, *"Freeline trust is Items 1, 11, 21, and 35"* is false. Dan should name specific titles he cannot hand over yet, and the matcher's 20-item cap prints *"Showing 20 of 50 available items"* for free. | content | queued |
| 1.4 | Decide `visitor_depleted_contact_mode`. Left unset it is `message`; set to `in_chat_form` the reviewer sees the in-chat contact form, which is a FLOSC feature worth demonstrating. | 1 YAML line | not started |
| 1.5 | 40 of 50 rows carry a `WP Slug` with no matching post, since only 10 posts exist. Harmless today — nothing renders a link without a payload URL — but a loose end. | data | not started |

### Known and not fixable from pack content

- No on-demand in-chat contact form. `renderVisitorDepletedContactForm()` has two callers
  (`flosc-app.js:11443`, `:12366`), both depletion-driven. No IVR `Action` reaches it. Adding one is
  runtime work, and the input-locking at `:11443` must be separated from the rendering first or
  asking for a contact form would lock the visitor's chat.
- Dan's voice cannot be written into the membership email. Coupon redemption does fire
  `flosc_member_access_granted` → `dispatch_member_welcome_email()`, so an email sends — but its copy
  comes from `{prefix}_welcome_subject` / `_welcome_body`, and those keys are **not** in the pack
  import whitelist.

---

## 2. Membership Craft

### Current state

| | |
|---|---|
| Flow installed as | `mcraft_ivr.md` |
| Personality | `friendly` (from `pack.json`) |
| Catalog | **none** |
| Posts | 100, in four categories |
| Gating | 15 visitor / 40 guest / 100 member, per `pack.json` — **not verified against `content.json`** |
| Flow file | 175 lines, 12 message blocks, 7 AutoPrompt pills |
| Offer | **none declared.** `pack.json` says *"The library offer ships without a price. Set price, currency and processor under Offers, then activate it."* |
| Access code | none |
| Routing | not applicable — no catalog |

The concept is good and self-referential: a hundred articles about running a membership site, gated
like one, so the reviewer reads it from inside the example.

### Revision roadmap

| # | Change | Kind |
|---|---|---|
| 2.1 | Verify the 15/40/100 gating actually matches `content.json`. Claimed in `pack.json`, unverified. | check |
| 2.2 | Decide whether this pack should declare an offer at all. Shipping a conversion journey with no priced offer means the reviewer sees a paywall with no way through and no price. | decision |
| 2.3 | If it keeps an offer, it needs an access code for the same reason DA1 does. | 1 YAML line |
| 2.4 | 7 pills for a 100-article library is thin, and none can demonstrate catalog search because there is no catalog. Decide whether that is the intent — a post-gated pack rather than a catalog pack — or whether it should ship a catalog too. | decision |
| 2.5 | Audit the 7 pills by hand. The routing auditor cannot help here; without a catalog every pill is answered by its own `MessageContent`, so each one needs reading. | content |

---

## 3. Vegan Latvian Kitchen

### Current state

| | |
|---|---|
| Flow installed as | `vlkit_ivr.md` |
| Personality | `friendly` in `pack.json`; the flow file declares **no** `personality_library_id` |
| Catalog | 15 rows, key `vegan_latvian_kitchen` |
| Row visibility | 2 visitor · 2 guest · 11 member |
| Posts | 15, in one category, plus a WXR import file |
| Media | `vegan-latvian-kitchen-cookbook.pdf` |
| Flow file | 306 lines, 21 message blocks, 17 AutoPrompt pills |
| Offer | **no `offer_*` keys**, although `pack.json` advertises *"$10 one-time or access code garšīgs"* |
| Access code | `garšīgs` → `member` |
| Routing | **12 of 17 route.** The 5 that do not are conversational by design |

Row 15 is the Cookbook PDF itself, carried as a catalog row rather than only as a post — which is
why `Suggest the catalog item that is just a PDF` can resolve to it and print the offer line.

### Already changed in this lane

Two pills reached no catalog and answered from hardcoded prose instead:

- `I want pīrāgi!` carried one title token, under the matcher's two-token threshold, so it fell
  through to generic chat while row 6 sat unused. Now `Recommend one recipe involving suspicious
  dough`, measured to resolve to exactly **Vegānie pīrāgi**.
- `Download the PDF` missed row 15 and hardcoded the slug `vegan-latvian-kitchen-pdf` in its own
  copy, which goes stale silently. Now `Suggest the catalog item that is just a PDF`, measured to
  resolve to exactly **Cookbook PDF — full pack**.

Both `MessageContent` lines were trimmed to stop reciting what the catalog now answers.

*This work was done out of the agreed pack order and is isolated in commit `853a5f7`, which can be
dropped without affecting anything else.*

### Revision roadmap

| # | Change | Kind |
|---|---|---|
| 3.1 | Resolve the offer. `pack.json` advertises $10 but the flow declares no `offer_*` keys. Either the offer is created somewhere I have not found, or the advertised price is unbacked. **Verify before shipping** — a reviewer reading "$10 one-time" with no offer installed is a defect. | check |
| 3.2 | Declare `personality_library_id` in the flow file, or confirm the installer honours `pack.json`'s `personality`. | check |
| 3.3 | Apply the same titles-as-teasers decision as DA1, if that is the house pattern: 15 `VGM` cells. | data |
| 3.4 | The 5 conversational pills are correct as they are. No change. | none |

---

## 4. WordPress Content Membership Journey

### Current state

| | |
|---|---|
| Flow installed as | `wcmj_ivr.md` |
| Personality | `bubblybetty` |
| Catalog | **none** |
| Posts | 100, in four categories, plus `100-content-items.xml` |
| Gating | 10 visitor / 30 guest / 100 member, per `pack.json` — **not verified** |
| Flow file | 149 lines, 10 message blocks, 6 AutoPrompt pills |
| Offer | **none declared.** Same wording as Membership Craft |
| Access code | none |
| Routing | not applicable — no catalog |

`pack.json` singles out item 46, *Why Pigeons Never Pay Parking Tickets*, which suggests the content
is the comedy here rather than the mechanism.

### Revision roadmap

| # | Change | Kind |
|---|---|---|
| 4.1 | Verify 10/30/100 gating against `content.json`. | check |
| 4.2 | Same offer decision as Membership Craft. | decision |
| 4.3 | Access code, if it keeps an offer. | 1 YAML line |
| 4.4 | 6 pills is the thinnest of the four packs, for the largest content set. | content |
| 4.5 | This pack and Membership Craft are structurally the same shape — 100 posts, four categories, no catalog, no offer. Worth deciding deliberately how they differ, or a reviewer installing both will not see why both exist. | decision |

---

## What is verified and what is not

**Verified by measurement:** every row count, VGM distribution, post count, line count, message-block
and pill count; every routing result, by running the real matcher against the real catalog;
`guest_token_grant` semantics; the DA1 offer-pill defect and its fix; the two Vegan pill defects and
their fixes; the contact-form call graph and the redirect setting's default.

**Not verified:** the claimed gating ratios in Membership Craft and WCMJ; whether Vegan's $10 offer
exists anywhere; which personality field the installer honours; whether
`contact_form_success_message` renders in chat; whether the 10 DA1 posts are content-protected
independently of the catalog rows.

**Not attempted:** anything requiring a running WordPress. Plugin Check, the install simulation and
the browser journeys are Dainis's steps.
