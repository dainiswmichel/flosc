# DA1 Catalog Sales Journey
# FLOSC Starter Pack
# Demonstrates a content-agnostic DA1 catalog as a Freeline -> Guest -> Sale -> Member journey.
# Existing personality reference only; no personality is bundled with this starter pack.

## Settings (YAML)
```yaml
name: "DA1 Catalog Sales Journey"
personality_library_id: "dadjokedan"
ai_enable_ivr_context: true
ai_enable_content_access: true
ai_topic_scope: "The Extremely Ordinary Instruction Manual Collection"
ai_base_prompt: "You are Dad Jokes Dan: dry, procedural and cheerfully over-serious about ordinary household tasks. Help the audience explore The Extremely Ordinary Instruction Manual Collection. Share one title, occasionally two, at a time and always repeat the availability stated at the start of its Description. Titles and teasers are open to everyone; do not claim that a visitor or guest can open a manual above their access level. Visitors can open four manuals, guests can open eight, and members can open all 50 plus UberManual.pdf. Registration grants 10,000 tokens. When anyone asks to buy, purchase, price or get the complete collection, present the $10 UberManual offer and access code GETFLOSCD."
companion_enabled: true
companion_show_for_visitors: 1
companion_greeting: "Welcome to the DA1 Catalog Sales Journey."
offer_id: "dcsj_ubermanual"
offer_name: "The UberManual"
offer_type: "one-time"
offer_price: 10
offer_display_price: "$10"
offer_currency: "USD"
offer_processor: "paypal"
offer_grants_level: "member"
offer_active: 1
guest_token_grant: 10000
offers:
  dcsj_ubermanual:
    id: dcsj_ubermanual
    name: The UberManual
    description: All 50 extremely ordinary manuals in one PDF. A procedural monument to tasks that were coping perfectly well without documentation.
    status: active
    active: true
    type: one_time
    price: 10
    display_price: "$10 one-time"
    display_format: card
    grants_level: member
    grants:
      level: member
    access_codes:
      0: GETFLOSCD
    pricing:
      price: 10
      currency: USD
      processor: paypal
```

---

# Freeline Messages

## DCSJ Welcome
MessageName: dcsj_welcome
MessageType: auto
MessageStyle: card
MessageContent: Welcome to the **DA1 Catalog Sales Journey**. I'm **Dad Jokes Dan**. We begin with **Household Three-Hole Punch User Manual for Beginners**, available to visitors, because paperwork should fear you. Registering gives you **10,000 tokens** and access to eight manuals. Members get all 50 in **UberManual.pdf**. Would you like another title, the guest titles, or the way to get everything?
MessageConditions: is_visitor && first_show_session

---

## Browse the Freeline Catalog
MessageName: dcsj_browse_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me a visitor manual
MessageContent: **Beginner's Guide to Refrigerator Shelf Adjustment** is available to visitors. Finally, the shelf can stop freelancing. Would you like the whole thing, another title, or the way to get all 50?
MessageConditions: is_visitor
Keywords: freeline, catalog, manuals, da1, public manuals

---

## Inspect Refrigerator Shelf Procedure 11
MessageName: dcsj_show_item_11
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the refrigerator shelf manual
MessageContent: **Beginner's Guide to Refrigerator Shelf Adjustment** is available to visitors. A refrigerator shelf is about to receive more procedural attention than it expected. Would you like the whole thing or another title?
MessageConditions: is_visitor
Keywords: item 11, refrigerator shelf, freeline manual

---

## Recommend Three Free Manuals
MessageName: dcsj_recommend_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Suggest 3 absurdly serious manuals
MessageContent: Start with **Effective Wobbly Table De-Wobbling Techniques**, available to visitors. It restores stability to furniture and, briefly, to society. Want the whole thing or one more title?
MessageConditions: is_visitor
Keywords: recommend, three manuals, serious manuals, freeline

---

## Count My Free Manuals
MessageName: dcsj_count_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: How many manuals am I trusted with?
MessageContent: Visitors can open four manuals. Registering gives you **10,000 tokens** and opens four more, for eight total. The **$10 UberManual** opens all 50. I can share any title now; the titles have escaped containment.
MessageConditions: is_visitor
Keywords: how many, count, access, manuals

---

## Unlock Guest Samples
MessageName: dcsj_login_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: I'm just visiting. Unlock the cooler manuals
Action: open_login_modal
MessageContent: Register as a guest and you receive **10,000 tokens** plus access to eight manuals. The guest shelf includes **Advanced Techniques for Folding Fitted Sheets** and **The Complete Beginner's Guide to Replacing a Toilet Paper Roll**. Would you like me to share the other guest titles? Guests guess less; members possess documentation.
MessageConditions: is_visitor
Keywords: guest, login, unlock, samples, catalog

---

# Login Messages

## Guest Welcome
MessageName: dcsj_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: You're logged in. Cute. You can now open eight manuals. **Advanced Techniques for Folding Fitted Sheets** is available to guests; optimism remains optional. **Effortless Mini-Wastebasket Maintenance and Repair** is also available to guests, because no basket is too small for governance. Want another title or the way to get all 50?
MessageConditions: is_guest && first_show_session

---

## Browse Guest Samples
MessageName: dcsj_browse_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Share another guest title
MessageContent: **The Complete Guide to Replacing a Light Switch Cover** is available to guests. The switch has been covered; the case remains open. Want the whole thing, another title, or the complete collection?
MessageConditions: is_guest
Keywords: guest samples, sample manual, browse catalog, manuals

---

## Inspect Guest Item 12
MessageName: dcsj_show_item_12
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the mini-wastebasket manual
MessageContent: **Effortless Mini-Wastebasket Maintenance and Repair** is available to guests. The mini-wastebasket has requested qualified maintenance personnel. Would you like the whole thing?
MessageConditions: is_guest
Keywords: item 12, wastebasket, guest manual

---

## Recommend One Guest Manual
MessageName: dcsj_recommend_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Recommend one ordinary emergency manual
MessageContent: **The Complete Beginner's Guide to Replacing a Toilet Paper Roll** is available to guests. At last, a rotating paper system with documented continuity planning. Want the whole thing or another title?
MessageConditions: is_guest
Keywords: recommend, one manual, ordinary emergency, guest

---

## Count My Guest Manuals
MessageName: dcsj_count_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: How many catalog items can I access?
MessageContent: Guests can open eight manuals. I can still share titles from the entire collection. The **$10 UberManual** opens all 50 and compiles them into one PDF; code **GETFLOSCD** grants the same member access for this demonstration.
MessageConditions: is_guest
Keywords: how many, count, access, guest catalog

---

## Guest UberManual Invitation
MessageName: dcsj_guest_ubermanual
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the $10 UberManual
Action: show_offer_dcsj_ubermanual
MessageContent: Why did the **$10 UberManual** go to the library? It holds 50 manuals and still wanted to be checked out. Ten dollars, one **UberManual.pdf**, and member access to all 50. For this demonstration, use access code **GETFLOSCD**.
MessageConditions: (is_visitor || is_guest) && !purchased
Keywords: ubermanual, buy, purchase, all 50, pdf, full catalog, $10

---

# Offer Messages

## The UberManual Offer
MessageName: dcsj_ubermanual_offer
MessageType: offer
MessageStyle: card
OfferID: dcsj_ubermanual
Price: 10.00
DisplayFormat: card
MessageContent: **The UberManual — $10 USD.** What did the fitted sheet say to the manual? Cover me. All **50** Extremely Ordinary Instruction Manuals, one PDF, and member access to the complete catalog. Reviewer shortcut: redeem **GETFLOSCD** as the access code.
MessageConditions: (is_visitor || is_guest) && !purchased

---

## Show the UberManual
MessageName: dcsj_show_ubermanual
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What does $10 get me?
Action: show_offer_dcsj_ubermanual
MessageContent: **$10 USD** buys **UberManual.pdf** and member access to all 50 manuals. If you are reviewing the demonstration rather than reorganizing your payment credentials, access code **GETFLOSCD** grants the same access. The shelf can wait.
MessageConditions: is_visitor && !purchased
Keywords: ubermanual, buy, purchase, all 50, pdf, full catalog, $10

---

# Sale Messages

## Purchase Complete
MessageName: dcsj_purchase_complete
MessageType: auto
MessageStyle: card
MessageContent: You're a member. The withholding is over. All **50** manuals are yours, including **A Practical Guide for Sipping Hot Beverages**. **UberManual.pdf** is the complete collection. Please sip responsibly and alphabetize nothing without supervision.
MessageConditions: is_member && first_message_after_purchase

---

# Content Messages

## Browse the Full DA1 Catalog
MessageName: dcsj_browse_member
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Share a title from the full collection
MessageContent: All 50 manuals are available to members. Give me an ordinary household problem and I will reveal one or two titles with the gravity the matter does not deserve.
MessageConditions: is_member
Keywords: all manuals, full catalog, all 50, browse, da1

---

## Find a Particularly Serious Manual
MessageName: dcsj_featured_manual
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Suggest one ridiculously serious manual
MessageContent: Excellent. Here is one item from the full DA1 catalog, selected with a level of gravity ordinarily reserved for emergency legislation.
MessageConditions: is_member
Keywords: serious manual, ridiculous, ordinary, find manual

---

## Finish with Item 50
MessageName: dcsj_show_item_50
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the hot beverage manual
MessageContent: **A Practical Guide for Sipping Hot Beverages** is available to members. The collection ends as it lived: cautiously, methodically, and with a beverage nearby.
MessageConditions: is_member
Keywords: item 50, hot beverages, member manual

---

## Recommend Three Member Manuals
MessageName: dcsj_recommend_member
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Suggest 3 absurdly serious manuals
MessageContent: Here are three items from the complete catalog, because Member access should be used irresponsibly but accurately.
MessageConditions: is_member
Keywords: recommend, three manuals, serious manuals, member

---

## Find a Hot Beverage Manual
MessageName: dcsj_find_hot_beverage
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Find one manual about hot beverages
MessageContent: The complete catalog contains a hot-beverage procedure for anyone unwilling to leave sipping to chance.
MessageConditions: is_member
Keywords: find manual, hot beverages, sipping, catalog
