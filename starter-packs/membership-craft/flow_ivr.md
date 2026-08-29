# Membership Craft — IVR Configuration
# FLOSC Starter Pack
# A hundred short pieces on running a membership site, gated as one.
# The library is about the thing it is doing, so installing it is also reading it.
# Existing personality reference only; no personality is bundled with this starter pack.

## Settings (YAML)
```yaml
name: "Membership Craft"
personality_library_id: "tech"
ai_enable_ivr_context: true
ai_enable_content_access: true
ai_topic_scope: "Running a membership site: pricing, churn, onboarding, content operations, community, growth, gating and the legal edges"
ai_base_prompt: "Help the reader find the piece that answers the problem they actually have. Respect FLOSC access at all times. Visitors may read the fifteen open pieces. Guests may read forty. Members may read all one hundred. Never reveal a protected piece to a lower tier. When someone asks about churn cohorts, dunning, tax on digital goods, or how to check their own gating, those are member pieces — say what the piece covers and offer membership rather than quoting it."
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
offers:
  mcraft_membership:
    id: mcraft_membership
    name: The full library
    description: All one hundred pieces, including the retention, pricing, tax and technical gating sets. Set your own price under Offers before activating.
    status: active
    type: one_time
    display_format: card
    grants_level: member
    grants:
      level: member
```

---

# Freeline Messages

## Welcome
MessageName: mcraft_welcome
MessageType: auto
MessageStyle: card
MessageContent: This is a library of **one hundred short pieces on running a membership site** — and it is itself a membership site, so you are standing inside the example. **Fifteen** are open to you now. Registering opens **twenty-five more**. Members reach all **one hundred**.
MessageConditions: is_visitor && first_show_session

---

## What is in here
MessageName: mcraft_whats_here
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is in this library?
MessageContent: Pricing, churn, onboarding, content operations, community, growth, gating, and the legal edges nobody warns you about. Tell me the problem you actually have and I will find the piece.
MessageConditions: is_visitor || is_guest || is_member
Keywords: what is in here, library, contents, about, topics, index

---

## What can I read now
MessageName: mcraft_open_shelf
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What can I read right now?
MessageContent: The open fifteen are the foundations — what a membership actually sells, why three tiers is usually right, why most sites die in month four, and the arithmetic showing you need far fewer members than you think. Say the word and I will point you at one.
MessageConditions: is_visitor
Keywords: read now, free, open, available, start, foundations

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
MessageContent: Forty pieces now instead of fifteen. The new ones are the operational ones — publishing cadence, the content bank you want before launch, why failed cards are the cheapest revenue you will ever recover, and where the upgrade prompt actually belongs.
MessageConditions: is_guest && first_show_session

---

## What did I unlock
MessageName: mcraft_guest_unlocked
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What did I just unlock?
MessageContent: Twenty-five pieces on running the thing day to day: choosing a cadence you can keep, writing an excerpt that sells a locked post, setting support expectations, and testing your own signup on a phone you have not configured.
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
MessageContent: **The full library — all one hundred pieces.** Six groups: retention and churn, pricing and money, content operations, community and support, growth, and the technical set on gating, caching, protecting media files and rate-limiting login. That last group is the one people say they wish they had read first.
MessageConditions: is_guest && !purchased

---

## Show me membership
MessageName: mcraft_show_offer
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Show me the full library
Action: show_offer_mcraft_membership
MessageContent: Membership is the step from the forty-piece registered shelf to the complete hundred.
MessageConditions: is_guest && !purchased
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
