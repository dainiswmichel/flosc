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
MessageContent: Welcome to the **DA1 Catalog Sales Journey**. This DA1 catalog contains **50 extremely ordinary instruction manuals**. Freeline access includes Items **1, 11, 21, and 35**. I know. Four manuals at once. Please try to remain calm.
MessageConditions: is_visitor && first_show_session

---

## Browse the Freeline Catalog
MessageName: dcsj_browse_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the Freeline manuals
MessageContent: Freeline Items **1, 11, 21, and 35** are available. Tell me which ordinary household problem deserves a disproportionate amount of documentation.
MessageConditions: is_visitor || is_guest || is_member
Keywords: freeline, catalog, manuals, da1, public manuals

---

## Unlock Guest Samples
MessageName: dcsj_login_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Unlock the Guest catalog samples
Action: open_login_modal
MessageContent: Guest access adds Items **2, 12, 22, and 36**, giving you eight catalog samples in total before the sales offer.
MessageConditions: is_visitor
Keywords: guest, login, unlock, samples, catalog

---

# Login Messages

## Guest Welcome
MessageName: dcsj_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: Guest access confirmed. You now have **8 sample manuals**: the four Freeline items plus Items **2, 12, 22, and 36**. If that has created an urgent need for all fifty, the situation is functioning as designed.
MessageConditions: is_guest && first_show_session

---

## Browse Guest Samples
MessageName: dcsj_browse_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me another catalog sample
MessageContent: Tell me what you are dealing with: sheets, wastebaskets, toilet paper, light switch covers, or another household matter of grave procedural importance.
MessageConditions: is_guest
Keywords: guest samples, sample manual, browse catalog, manuals

---

# Offer Messages

## The UberManual Offer
MessageName: dcsj_ubermanual_offer
MessageType: offer
MessageStyle: card
OfferID: dcsj_ubermanual
Price: 10.00
DisplayFormat: card
MessageContent: **The UberManual - $10 USD.** Get the compiled PDF containing **all 50 Extremely Ordinary Instruction Manuals**, and unlock Member access to the complete DA1 catalog. Fifty manuals. One PDF. Considerably more procedural certainty than most households require.
MessageConditions: !purchased

---

## Show the UberManual
MessageName: dcsj_show_ubermanual
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the $10 UberManual
Action: show_offer_dcsj_ubermanual
MessageContent: The **$10 UberManual** compiles all 50 catalog items into one PDF and unlocks the complete Member catalog.
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
UserInput: Browse all 50 manuals
MessageContent: All fifty catalog items are available. Give me a title, identifier, subject, or ordinary household problem and I will find the relevant manual.
MessageConditions: is_member
Keywords: all manuals, full catalog, all 50, browse, da1

---

## Find a Particularly Serious Manual
MessageName: dcsj_featured_manual
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Find me a ridiculously serious manual
MessageContent: Excellent. I can search the full DA1 catalog by title, description, subject, or intent. We have procedures for fitted sheets, remote controls, crooked frames, hot beverages, and other areas of civilization that have gone dangerously under-documented.
MessageConditions: is_member
Keywords: serious manual, ridiculous, ordinary, find manual
