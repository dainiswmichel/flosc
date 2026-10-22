# Membership Craft — IVR Configuration
# FLOSC Starter Pack
# A hundred short pieces on running a membership site, gated as one.
# The library is about the thing it is doing, so installing it is also reading it.
# Existing personality reference only; no personality is bundled with this starter pack.

## Settings (YAML)
```yaml
name: "Membership Craft"
personality_library_id: "friendly"
ai_enable_ivr_context: true
ai_enable_content_access: true
ai_topic_scope: "Running a membership site: pricing, churn, onboarding, content operations, community, growth, gating and the legal edges"
ai_base_prompt: "Help the reader find the piece that answers the problem they actually have. Share any article title freely, one or two at a time, and state whether it is available to visitors, guests or members. Visitors may read 15 articles, guests may read 40, and members may read all 100. Never quote or summarize the body of an article above the reader's access level. Visitors who register receive 10,000 tokens and 25 additional articles. When the next useful article is locked, offer registration or the $10 membership and access code GETFLOSCD."
content_item_groups:
  0:
    quiz_id: ""
    category: "flosc-membership-craft-open"
  1:
    quiz_id: ""
    category: "flosc-membership-craft-registered"
  2:
    quiz_id: ""
    category: "flosc-membership-craft-members"
content_item_category: "flosc-membership-craft-open"
companion_enabled: true
companion_show_for_visitors: 1
companion_greeting: "Ask me about running a membership site."
guest_token_grant: 10000
offers:
  mcraft_membership:
    id: mcraft_membership
    name: The full library
    description: All 100 articles, including retention, pricing, tax and technical gating. One-time $10 or access code GETFLOSCD.
    status: active
    active: true
    type: one_time
    display_format: card
    price: 10
    display_price: "$10 one-time"
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

## Welcome
MessageName: mcraft_welcome
MessageType: auto
MessageStyle: card
MessageContent: This is a library about running a membership site, and you are standing inside the example. **What a Membership Site Actually Sells** is available to visitors. **Choosing Your Publishing Cadence** is available to guests. **Reading a Churn Number Correctly** is available to members. You can read 15 now, registration adds 25 articles and **10,000 tokens**, and membership opens all 100.
MessageConditions: is_visitor && first_show_session

---

## What is in here
MessageName: mcraft_whats_here
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is in this library?
MessageContent: Tell me the problem you actually have and I will find one or two titles. For example, **Pricing Is Positioning** is available to visitors, while **VAT, Sales Tax, and Digital Goods** is available to members. The library demonstrates its own advice, which is either elegant or suspiciously convenient.
MessageConditions: is_visitor || is_guest || is_member
Keywords: what is in here, library, contents, about, topics, index

---

## What can I read now
MessageName: mcraft_open_shelf
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What can I read right now?
MessageContent: Start with **Why Most Membership Sites Die in Month Four** and **You Need Fewer Members Than You Think**. Both are available to visitors. One brings existential dread; the other brings arithmetic. Would you like either article?
MessageConditions: is_visitor
Keywords: read now, free, open, available, start, foundations

---

## What registration gets me
MessageName: mcraft_guest_tease
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What would registering get me?
MessageContent: Registration adds **25 articles** and **10,000 tokens**. **The Content Bank You Should Have Before Launch** and **The Upgrade Prompt Belongs at the Moment of Value** are available to guests. I can share more guest titles, or you can step directly to the full library.
MessageConditions: is_visitor
Keywords: register, registration, guest, guest titles, tokens, more articles

---

## Why is some of it locked
MessageName: mcraft_why_locked
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Why is some of it locked?
MessageContent: Because that is the thing being demonstrated. The open pieces make the argument, the registered pieces extend it, and the member pieces carry the detail — churn cohorts, dunning, VAT on digital goods, gating at the query rather than the template. If the wall annoys you slightly, that is the exact feeling this journey exists to handle well.
MessageConditions: is_visitor || is_guest
Keywords: locked, why locked, paywall, gate, wall, hidden, restricted

---

# Login Messages

## Guest Welcome
MessageName: mcraft_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: Forty articles now instead of fifteen. **Choosing Your Publishing Cadence** and **Dunning: The Money You Are Already Owed** are available to guests. The first helps you keep publishing; the second politely points at revenue hiding under the sofa.
MessageConditions: is_guest && first_show_session

---

## What did I unlock
MessageName: mcraft_guest_unlocked
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What did I just unlock?
MessageContent: You unlocked 25 more. **Writing an Excerpt That Sells the Locked Post** and **Test the Signup on a Phone You Have Not Configured** are available to guests. Would you like one of those or another title?
MessageConditions: is_guest
Keywords: unlock, unlocked, what do i get, access, more

---

# Offer Messages

## The full library
MessageName: mcraft_offer
MessageType: offer
MessageStyle: card
OfferID: mcraft_membership
DisplayFormat: card
MessageContent: **The full library — all 100 articles for $10.** Retention, pricing, content operations, community, growth and the technical set people wish they had read first. For this demonstration, access code **GETFLOSCD** grants the same member access.
MessageConditions: (is_visitor || is_guest) && !purchased

---

## Show me membership
MessageName: mcraft_show_offer
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What does full membership include?
Action: show_offer_mcraft_membership
MessageContent: Membership opens all 100 articles for **$10 one-time**. Access code **GETFLOSCD** grants the same access for this demonstration. It is a library about membership demonstrating membership while discussing membership. We have checked: that is still legal.
MessageConditions: (is_visitor || is_guest) && !purchased
Keywords: membership, upgrade, full library, all hundred, buy, purchase

---

# Sale Messages

## Members
MessageName: mcraft_member_welcome_sale
MessageType: auto
MessageStyle: card
MessageContent: All one hundred are open. Start with **Reading a churn number correctly** — five percent monthly is half your members a year — then **Gate at the query, not just the template**, which is the bug most sites are shipping right now.
MessageConditions: is_member && first_message_after_purchase

---

# Content Messages

## Member Welcome
MessageName: mcraft_member_welcome
MessageType: auto
MessageStyle: card
MessageContent: All one hundred pieces are open to you. Ask for anything by name, or describe the problem you are actually having — a member who stopped logging in, a price you are afraid to raise, a PDF you suspect is not really locked — and I will find the piece that covers it.
MessageConditions: is_member && first_show_session

---

## Check my gating
MessageName: mcraft_gating
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How do I check my own gating?
MessageContent: Three tests, all free. Load a member URL in a private window. Hit your REST API logged out and search the response for a member title. Then copy a file link out of a locked post and open it with no session. Most sites pass the first and fail one of the other two.
MessageConditions: is_member
Keywords: gating, check, test, security, rest api, leak, locked

---

## Where should I start
MessageName: mcraft_start
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Where should I start?
MessageContent: Reading a churn number correctly, then the ninety-day danger window, then Gate at the query not just the template. Churn maths, why the cancellation you got today was decided in March, and the leak most sites have shipped without knowing.
MessageConditions: is_member
Keywords: start, where to start, first, recommend, begin, order
