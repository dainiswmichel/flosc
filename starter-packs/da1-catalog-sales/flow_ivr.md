# DA1 Catalog Sales Journey — IVR Configuration
# Purpose: a complete, working journey you can watch end to end, then take apart.
# Subject: fifty instruction manuals for things that do not have instruction manuals.
# Conversion: the UberManual — all fifty compiled into one PDF, $10.
# Nothing here is specific to manuals. Swap the catalog and the journey still works.

---

# Freeline Messages (visitors)

## Welcome
MessageName: da1_sales_welcome
MessageType: auto
MessageStyle: card
MessageContent: We wrote instruction manuals for fifty things that do not have instruction manuals. Opening a jar that has decided otherwise. Locating the end of the sticky tape. Four of them are open to you right now — ask me for one. (Configure an AI provider under Settings → AI and I get considerably better at this.)
MessageConditions: is_visitor && first_show_session

---

## Show me a manual
MessageName: da1_sales_show_manual
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Show me one of these manuals
MessageContent: Happily. Four are open to visitors — opening a stubborn jar, silencing a smoke alarm at 3 AM, explaining a board game rule you half remember, and one about a duvet cover. Which sounds most like your week?
MessageConditions: is_visitor || is_guest || is_member
Keywords: manual, manuals, show me, catalog, browse, list

---

## How many are there
MessageName: da1_sales_count
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How many manuals are there?
MessageContent: Fifty in total. You can reach four. Guests reach eight. Members reach all fifty. It is the same catalog either way — what changes is how much of it I am allowed to show you.
MessageConditions: is_visitor
Keywords: how many, count, total, number

---

## What is the UberManual
MessageName: da1_sales_ubermanual_visitor
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is the UberManual?
MessageContent: All fifty manuals compiled into one PDF. Four hundred pages of procedure for things that need no procedure. We believe it is the most boring document ever assembled, and we mean that as a specification. Several people have an AI voice read it to them at bedtime. Nobody has finished chapter two.
MessageConditions: is_visitor || is_guest || is_member
Keywords: ubermanual, uber manual, pdf, buy, purchase, price

---

# Guest Messages

## Guest Welcome
MessageName: da1_sales_guest_welcome
MessageType: auto
MessageStyle: card
MessageContent: You have eight manuals now instead of four. The new ones include emergency procedures for a jammed zipper, and refolding a road map — which we rate Advanced and mean it. Ask me for either.
MessageConditions: is_guest && first_show_session

---

## What did I unlock
MessageName: da1_sales_guest_unlocked
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What did I just unlock?
MessageContent: Four more manuals. Jammed zippers, parallel parking without an audience, refolding a road map, and getting a fitted bin bag into a bin. Members reach the remaining forty-two, including the one about carrying a mattress upstairs alone, which ends by recommending you ask a friend.
MessageConditions: is_guest
Keywords: unlock, unlocked, what do i get, access, more

---

# Member Messages

## Member Welcome
MessageName: da1_sales_member_welcome
MessageType: auto
MessageStyle: card
MessageContent: All fifty are open to you. Ask for any of them by name, or tell me what went wrong today and I will find the one that covers it.
MessageConditions: is_member && first_show_session

---

## Read me the boring one
MessageName: da1_sales_member_ubermanual
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Tell me about the UberManual
MessageContent: Four hundred pages, fifty manuals, one PDF. Chapter one is about a jar. There is no narrative tension anywhere in it, which is the entire point — it is designed to be read aloud until you stop listening. If you want it, I can take you to the offer.
MessageConditions: is_member || is_guest
Keywords: ubermanual, uber manual, boring, sleep, audio, read to me

---

## How was this built
MessageName: da1_sales_member_howbuilt
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How was this journey built?
MessageContent: A DA1 catalog of fifty rows, each tagged Freeline, Guest or Member. A flow that decides what I say at each stage. An offer at the end. Nothing in it is specific to manuals — swap the catalog for recipes, properties or courses and the same journey runs. That is what this pack is really demonstrating.
MessageConditions: is_member
Keywords: how was this built, how does this work, flosc, journey, built
