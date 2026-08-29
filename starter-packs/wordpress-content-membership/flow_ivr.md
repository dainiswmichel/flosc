# WordPress Content Membership Journey — IVR Configuration
# Purpose: the classic membership site, working end to end, in one install.
# Subject: a hundred-post library about running a membership site.
# Conversion: membership — the sixty member-only posts.
# The library is self-referential on purpose. Install it, read it, then replace
# it with your own subject. The journey does not care what the posts are about.

---

# Freeline Messages (visitors)

## Welcome
MessageName: membership_welcome
MessageType: auto
MessageStyle: card
MessageContent: This is a library of a hundred posts about running a membership site — and it is itself a membership site, so you are standing inside the example. Fifteen posts are open to you right now. Ask me what is in them. (Configure an AI provider under Settings → AI and I get considerably better at this.)
MessageConditions: is_visitor && first_show_session

---

## What is in here
MessageName: membership_whats_here
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is in this library?
MessageContent: A hundred posts on pricing, churn, onboarding, community, gating, tax, and the ways membership sites quietly fail. Fifteen are open to everyone, twenty-five more open when you register, and the remaining sixty are for members. Same library — what changes is how much of it I can show you.
MessageConditions: is_visitor || is_guest || is_member
Keywords: what is in here, library, posts, contents, about, topics

---

## What can I read now
MessageName: membership_visitor_reading
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What can I read right now?
MessageContent: The open fifteen are the foundations — what a membership actually sells, why three tiers is usually the right number, why most sites die in month four, and the arithmetic that shows you need far fewer members than you think. Say the word and I will point you at one.
MessageConditions: is_visitor
Keywords: read now, free, open, available, what can i read, start

---

## Why is some of it locked
MessageName: membership_why_locked
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Why is some of it locked?
MessageContent: Because that is the thing being demonstrated. The public posts make the argument, the registered posts extend it, and the member posts carry the detail — churn cohorts, dunning, VAT on digital goods, gating at the query rather than the template. If the wall annoys you slightly, good: that is the exact feeling this journey exists to handle well.
MessageConditions: is_visitor || is_guest
Keywords: locked, why locked, paywall, gate, wall, hidden, restricted

---

## Register
MessageName: membership_register_prompt
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How do I get the rest?
MessageContent: Registering is free and opens twenty-five more posts — cadence, onboarding sequences, dunning, excerpt writing, the metric worth watching weekly. Membership opens the remaining sixty. I can take you to either one.
MessageConditions: is_visitor
Keywords: register, sign up, join, account, get the rest, more

---

# Guest Messages

## Guest Welcome
MessageName: membership_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: You have forty posts now instead of fifteen. The new ones are the operational ones — publishing cadence, the content bank you want before launch, why failed cards are the cheapest revenue you will ever recover, and where the upgrade prompt actually belongs. Ask me for any of it.
MessageConditions: is_guest && first_show_session

---

## What did I unlock
MessageName: membership_guest_unlocked
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What did I just unlock?
MessageContent: Twenty-five posts about running the thing day to day: choosing a cadence you can keep, writing an excerpt that sells a locked post, setting support expectations, and testing your own signup on a phone you have not configured. Members reach a further sixty, which is where churn cohorts, tax, and the technical gating posts live.
MessageConditions: is_guest
Keywords: unlock, unlocked, what do i get, access, more

---

## What is behind the member wall
MessageName: membership_guest_member_preview
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is behind the member wall?
MessageContent: Sixty posts, in six groups. Retention and churn. Pricing and money, including VAT on digital goods and why chargebacks are worse than refunds. Content operations. Community and support. Growth. And the technical set — gating at the query rather than the template, caching, protecting media files, rate-limiting login. That last group is the one people tell us they wish they had read first.
MessageConditions: is_guest
Keywords: member, membership, behind the wall, whats in it, worth it, upgrade

---

# Member Messages

## Member Welcome
MessageName: membership_member_welcome
MessageType: auto
MessageStyle: card
MessageContent: All hundred posts are open to you. Ask for anything by name, or describe the problem you are actually having — a member who stopped logging in, a price you are afraid to raise, a PDF you suspect is not really locked — and I will find the post that covers it.
MessageConditions: is_member && first_show_session

---

## Where should I start
MessageName: membership_member_start
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Where should I start?
MessageContent: Three posts, in this order. Reading a churn number correctly, because five percent monthly is half your members a year. Gate at the query, not just the template, because that is the bug most sites are shipping right now. Then the ninety-day danger window, which explains why the cancellation you got today was decided in March.
MessageConditions: is_member
Keywords: start, where to start, first, recommend, begin, order

---

## Check my gating
MessageName: membership_member_gating
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How do I check my own gating?
MessageContent: Three tests, all of them free. Load a member URL in a private window. Hit your REST API logged out and search the response for a member title. Then copy a PDF link out of a locked post and open it with no session. Most sites pass the first and fail one of the other two. The technical posts walk through each fix.
MessageConditions: is_member
Keywords: gating, check, test, security, rest api, leak, locked

---

## How was this built
MessageName: membership_member_howbuilt
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How was this journey built?
MessageContent: A hundred posts in one category, each stamped visitor, guest or member. A flow that decides what I say at each stage. A login in the middle and an offer at the end. Nothing here is specific to membership advice — replace the posts with your own subject and the same journey runs unchanged. That is what this pack is really demonstrating.
MessageConditions: is_member
Keywords: how was this built, how does this work, flosc, journey, built
