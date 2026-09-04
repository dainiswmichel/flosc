# WordPress Content Membership Journey
# FLOSC Starter Pack
# Demonstrates WordPress posts as progressive Freeline -> Guest -> Member content.
# Existing personality reference only; no personality is bundled with this starter pack.

## Settings (YAML)
```yaml
name: "WordPress Content Membership Journey"
personality_library_id: "bubblybetty"
ai_enable_ivr_context: true
ai_enable_content_access: true
ai_topic_scope: "FLOSC WordPress Content Membership Journey starter library"
ai_base_prompt: "Help visitors explore the 100 deliberately silly WordPress posts in this starter journey. Respect FLOSC access at all times. Visitors may access Items 1-10. Guests may access Items 1-30. Members may access all 100. Never reveal protected post content to a lower access tier. When a Guest wants more than the Guest collection, explain that Membership unlocks the complete 100-post library and surface the configured membership offer when appropriate."
content_types:
  0:
    singular: "Content Item"
    plural: "Content Items"
content_item_groups:
  0:
    quiz_id: ""
    category: "flosc-starter-freeline"
  1:
    quiz_id: ""
    category: "flosc-starter-guests"
  2:
    quiz_id: ""
    category: "flosc-starter-members"
content_item_category: "flosc-starter-freeline"
companion_enabled: true
companion_show_for_visitors: 1
companion_greeting: "Welcome to the WordPress Content Membership Journey."
guest_access_days: 0
```

---

# Freeline Messages

## WCMJ Welcome
MessageName: wcmj_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🎉 Welcome to the **WordPress Content Membership Journey**. This starter library contains **100 gloriously unnecessary WordPress posts**. Items **1-10** are open in the Freeline. Create or use a Guest login to unlock Items **11-30**. Membership unlocks the complete collection, Items **1-100**.
MessageConditions: is_visitor && first_show_session

---

## Browse Freeline
MessageName: wcmj_browse_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the Freeline posts
MessageContent: Absolutely. Items **1-10** are public. Pick a title and we can give it considerably more attention than it deserves.
MessageConditions: is_visitor || is_guest || is_member
Keywords: freeline, public, posts, content, items 1-10

---

## Unlock Guest Posts
MessageName: wcmj_login_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Unlock the Guest posts
Action: open_login_modal
MessageContent: Guest access opens Items **11-30** while keeping the ten Freeline posts available too.
MessageConditions: is_visitor
Keywords: guest, login, unlock, items 11-30

---

# Login Messages

## Guest Welcome
MessageName: wcmj_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🎊 Guest access confirmed. You can now explore **Items 1-30**. That is thirty pieces of carefully organized nonsense. The remaining **70 posts** are reserved for Members.
MessageConditions: is_guest && first_show_session

---

## Explore Guest Library
MessageName: wcmj_browse_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me something from the Guest library
MessageContent: You now have the Freeline plus Guest Items **11-30**. Tell me what sort of unnecessary topic sounds promising and I will find something suitably ridiculous.
MessageConditions: is_guest
Keywords: guest library, guest posts, items 11-30, browse

---

# Offer Messages

## Unlock All 100 Posts
MessageName: wcmj_membership_offer
MessageType: offer
MessageStyle: card
OfferID: wcmj_membership
DisplayFormat: card
MessageContent: **Membership unlocks all 100 WordPress posts.** You already have a sample of the library; Membership opens Items **31-100** as well, including the municipal mystery known as **Item 46 - Why Pigeons Never Pay Parking Tickets**.
MessageConditions: is_guest && !purchased

---

## Show Membership Offer
MessageName: wcmj_show_membership
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me Membership access
Action: show_offer_wcmj_membership
MessageContent: Membership is the step from the 30-post Guest library to the complete **100-post collection**.
MessageConditions: is_guest && !purchased
Keywords: membership, upgrade, all posts, full access, unlock all

---

# Sale Messages

## Membership Unlocked
MessageName: wcmj_member_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🚨🎉 **ALL ONE HUNDRED.** Membership is active, so Items **1-100** are available. Somewhere in there is Item 46: **Why Pigeons Never Pay Parking Tickets**. Municipal accountability has finally reached the bird sector.
MessageConditions: is_member && first_message_after_purchase

---

# Content Messages

## Find the Pigeons
MessageName: wcmj_pigeons
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Take me to the pigeon investigation 🐦
MessageContent: ITEM 46. 🐦🚗 Ask for **Content Item 046 - Why Pigeons Never Pay Parking Tickets**.
MessageConditions: is_member
Keywords: pigeons, parking tickets, item 46

---

## Browse Full Library
MessageName: wcmj_browse_member
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Browse all 100 posts
MessageContent: The complete collection is open. Give me a number, a title, or an everyday subject and I will help you find a post.
MessageConditions: is_member
Keywords: all posts, full library, all 100, browse
