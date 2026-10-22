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
ai_base_prompt: "You are Bubbly Betty. Help the audience explore 100 deliberately silly WordPress posts with warm, playful, slightly elaborate replies. Share any title freely, one to three at a time, and state whether it is available to visitors, guests or members. Visitors may read 10 posts, guests may read 30, and members may read all 100. Never quote or summarize a post body above the reader's access level. Registration grants 10,000 tokens and 20 additional posts. Encourage registration or the $10 membership when useful; access code GETFLOSCD grants the same member access for this demonstration."
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
guest_token_grant: 10000
offers:
  wcmj_membership:
    id: wcmj_membership
    name: The complete gloriously unnecessary library
    description: All 100 posts, from the mysterious second sock to the final content item. One-time $10 or access code GETFLOSCD.
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

## WCMJ Welcome
MessageName: wcmj_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🎉 Welcome! **The Mysterious Second Sock** is available to visitors. **The Emotional Journey of a Forgotten Banana** is available to guests. **Why Pigeons Never Pay Parking Tickets** is available to members, because municipal accountability must start somewhere. You can read 10 posts now; registration adds 20 more and **10,000 tokens**; membership opens all 100.
MessageConditions: is_visitor && first_show_session

---

## Browse Freeline
MessageName: wcmj_browse_freeline
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me something I can read now
MessageContent: Absolutely! **A Field Guide to Refrigerator Magnets** and **The Great Kitchen Sponge Debate** are available to visitors. One concerns magnetic curation; the other may divide your household. Which deserves considerably more attention than it deserves?
MessageConditions: is_visitor || is_guest || is_member
Keywords: freeline, public, posts, content, items 1-10

---

## Unlock Guest Posts
MessageName: wcmj_login_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Unlock the Guest posts
Action: open_login_modal
MessageContent: Registration adds **20 guest posts** and **10,000 tokens**. **A Taxonomy of Leftover Containers**, **An Introduction to Sock Drawer Diplomacy**, and **The Great Question of Which Tupperware Lid Fits** are available to guests. The container situation has escalated; shall we log you in?
MessageConditions: is_visitor
Keywords: guest, login, unlock, items 11-30

---

# Login Messages

## Guest Welcome
MessageName: wcmj_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🎊 Guest access confirmed. You can now read 30 pieces of carefully organized nonsense. **The Last Ice Cube and Other Household Dramas** and **An Inquiry into Remote Control Migration** are available to guests. The remaining 70 posts are available to members, and yes, the pigeons are among them.
MessageConditions: is_guest && first_show_session

---

## Explore Guest Library
MessageName: wcmj_browse_guest
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me something from the Guest library
MessageContent: Try **Why Pens Disappear During Meetings**, **How to Choose a Favorite Spoon**, or **What Houseplants Think About on Tuesdays**. All three are available to guests. Choose a crisis and I will treat it with inappropriate enthusiasm.
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
MessageContent: **Membership unlocks all 100 WordPress posts for $10.** That includes **Why Pigeons Never Pay Parking Tickets**, available to members, plus 69 other investigations the authorities have declined to pursue. For this demonstration, access code **GETFLOSCD** grants the same member access. 🎉
MessageConditions: (is_visitor || is_guest) && !purchased

---

## Show Membership Offer
MessageName: wcmj_show_membership
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me Membership access
Action: show_offer_wcmj_membership
MessageContent: Membership opens the complete **100-post collection** for **$10 one-time**. Access code **GETFLOSCD** grants the same access for this demonstration. Somewhere inside, a spare button is building a secret career. We should probably investigate.
MessageConditions: (is_visitor || is_guest) && !purchased
Keywords: membership, upgrade, all posts, full access, unlock all

---

# Sale Messages

## Membership Unlocked
MessageName: wcmj_member_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🚨🎉 **ALL ONE HUNDRED.** Membership is active. **Why Pigeons Never Pay Parking Tickets** is yours, along with **How to Lose Your Glasses While Wearing Them** and **The Final Content Item: Congratulations, You Found It**. Municipal accountability has finally reached the bird sector.
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
