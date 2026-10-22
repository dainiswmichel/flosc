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
ai_base_prompt: "Help users explore the 50-item DA1 catalog called The Extremely Ordinary Instruction Manual Collection. Use the catalog's Dublin Core-compatible Title, Description, Subject, Identifier, Type, and Relation metadata to understand and recommend items. Respect VGM access at all times. Visitors can access Items 1, 11, 21, and 35. Guests can additionally access Items 2, 12, 22, and 36. Members can access all 50. Do not reveal protected catalog content to a lower access tier. When a visitor or Guest wants the complete collection, present the $10 USD UberManual offer. A successful purchase grants Member access to the full catalog; the compiled product is UberManual.pdf."
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
```

---

# Freeline Messages

## DCSJ Welcome
MessageName: dcsj_welcome
MessageType: auto
MessageStyle: card
MessageContent: Welcome to the **DA1 Catalog Sales Journey**. I'm DadJokesDan. I told Item 11 a joke. The refrigerator shelf said it does not do standup. It does shelf. Freeline access is Items **1, 11, 21, and 35**. The **$10 UberManual** has all fifty. Four manuals are free. The groan is free too.
MessageConditions: is_visitor && first_show_session

---

## Browse the Freeline Catalog
MessageName: dcsj_browse_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show all Freeline manuals
MessageContent: Freeline Items **1, 11, 21, and 35** are available. Tell me which ordinary household problem deserves a disproportionate amount of documentation.
MessageConditions: is_visitor
Keywords: freeline, catalog, manuals, da1, public manuals

---

## Inspect Refrigerator Shelf Procedure 11
MessageName: dcsj_show_item_11
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me Item 11
MessageContent: Item 11 is available on the Freeline. A refrigerator shelf is about to receive more procedural attention than it expected.
MessageConditions: is_visitor
Keywords: item 11, refrigerator shelf, freeline manual

---

## Recommend Three Free Manuals
MessageName: dcsj_recommend_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Suggest 3 absurdly serious manuals
MessageContent: Here are three Freeline catalog items selected with entirely disproportionate gravity.
MessageConditions: is_visitor
Keywords: recommend, three manuals, serious manuals, freeline

---

## Count My Free Manuals
MessageName: dcsj_count_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: How many manuals am I trusted with?
MessageContent: Visitors can access four catalog items. Guest registration adds four more, and the $10 UberManual opens the complete collection.
MessageConditions: is_visitor
Keywords: how many, count, access, manuals

---

## Unlock Guest Samples
MessageName: dcsj_login_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Unlock four more suspiciously useful manuals
Action: open_login_modal
MessageContent: Guest access adds Items **2, 12, 22, and 36**, giving you eight catalog samples before the **$10 UberManual** attempts to become the most thoroughly documented impulse purchase of your day.
MessageConditions: is_visitor
Keywords: guest, login, unlock, samples, catalog

---

# Login Messages

## Guest Welcome
MessageName: dcsj_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: Guest access confirmed. You now have **8 sample manuals**: Items **1, 11, 21, 35, 2, 12, 22, and 36**. What do eight manuals and a dad make? A shelf-help group. The **$10 UberManual** is **UberManual.pdf**, plus Member access to all fifty.
MessageConditions: is_guest && first_show_session

---

## Browse Guest Samples
MessageName: dcsj_browse_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show all eight catalog samples
MessageContent: Tell me what you are dealing with: sheets, wastebaskets, toilet paper, light switch covers, or another household matter of grave procedural importance.
MessageConditions: is_guest
Keywords: guest samples, sample manual, browse catalog, manuals

---

## Inspect Guest Item 12
MessageName: dcsj_show_item_12
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me Item 12
MessageContent: Item 12 is one of the four additional Guest samples. The mini-wastebasket has requested qualified maintenance personnel.
MessageConditions: is_guest
Keywords: item 12, wastebasket, guest manual

---

## Recommend One Guest Manual
MessageName: dcsj_recommend_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Recommend one ordinary emergency manual
MessageContent: Here is one accessible catalog item for an emergency that probably did not require a manual until now.
MessageConditions: is_guest
Keywords: recommend, one manual, ordinary emergency, guest

---

## Count My Guest Manuals
MessageName: dcsj_count_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: How many catalog items can I access?
MessageContent: Guests can access eight catalog items. The $10 UberManual opens all fifty and compiles them into one PDF.
MessageConditions: is_guest
Keywords: how many, count, access, guest catalog

---

## Guest UberManual Invitation
MessageName: dcsj_guest_ubermanual
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the $10 UberManual
Action: show_offer_dcsj_ubermanual
MessageContent: Why did the **$10 UberManual** go to the library? It already holds fifty extremely ordinary manuals, and it still wanted to be checked out. Ten dollars. One PDF: **UberManual.pdf**. Member access to all **50**.
MessageConditions: is_guest && !purchased
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
MessageContent: **The UberManual - $10 USD.** What did the fitted sheet say to the manual? Cover me. All **50** Extremely Ordinary Instruction Manuals, one PDF, and Member access to the complete DA1 catalog.
MessageConditions: !purchased

---

## Show the UberManual
MessageName: dcsj_show_ubermanual
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the $10 UberManual
Action: show_offer_dcsj_ubermanual
MessageContent: What do you call fifty household manuals in one PDF? A dad's idea of a vacation. **$10 USD**. The file is **UberManual.pdf**. Purchase grants Member access to all fifty.
MessageConditions: !purchased
Keywords: ubermanual, buy, purchase, all 50, pdf, full catalog, $10

---

# Sale Messages

## Purchase Complete
MessageName: dcsj_purchase_complete
MessageType: auto
MessageStyle: card
MessageContent: Purchase complete. **Member access is now active for all 50 DA1 catalog items.** The product for this starter journey is **UberManual.pdf**, the compiled collection of all fifty manuals.
MessageConditions: is_member && first_message_after_purchase

---

# Content Messages

## Browse the Full DA1 Catalog
MessageName: dcsj_browse_member
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show all 50 manuals
MessageContent: All fifty catalog items are available. Give me a title, identifier, subject, or ordinary household problem and I will find the relevant manual.
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
UserInput: Show me Item 50
MessageContent: Item 50 concerns sipping hot beverages. The collection ends as it lived: cautiously, methodically, and with a beverage nearby.
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
