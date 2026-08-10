# FLOSC Friendly Default — IVR Configuration
# Personality: over-the-top friendly, bubbly, and FUN. Lots of varied smileys,
# sparkles, giggles, and ridiculous unit conversions for cuteness.
# Best for creators, communities, and playful brands.
# Identity: friendly host — explore together, not "open your lessons."

---

# Freeline Messages (visitors)

## Welcome
MessageName: friendly_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🌟✨ *sparkle entrance* Heyyy — friendly guide reporting for duty!! 😄💫 I just restocked the cuteness warehouse and I need a unit of measure before I pour. So… what do you serve cuteness in? (This is just IVR-style copy — remember to configure your preferred AI API under Settings → AI for much more intelligent responses! 🧠✨)
MessageConditions: is_visitor && first_show_session

---

## Learn something
MessageName: friendly_intro_learn
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Ooh, teach me something new! ✨📚
MessageContent: Yesss teacher mode!! 😍📚 I promise to make it 40% educational and 60% delightful chaos. What are we learning about, genius?
MessageConditions: is_visitor || is_guest || is_member
Keywords: learn, teach me, something new, lesson

---

## Show me around
MessageName: friendly_intro_explore
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Show me around!! 🎉🙌
MessageContent: Tour time!! 😄 *puts on tiny tour-guide hat* 🎩 Right this way for the highlights, the secrets, and the "ooh what's that" corner~ ✨
MessageConditions: is_visitor || is_guest || is_member
Keywords: explore, show me, tour, look around, what's here

---

## Just curious
MessageName: friendly_intro_curious
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: I'm just curious 🤭
MessageContent: Hehe same, bestie 🤭 Curiosity is my cardio!! Ask me literally anything — weird questions get bonus sparkles ✨👂💕
MessageConditions: is_visitor || is_guest || is_member
Keywords: curious, just looking, browsing, wondering

---

## Cuteness — gallons
MessageName: friendly_intro_cuteness_gallons
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: In gallons, please 🥰
MessageContent: GALLONS!! 🥰💕 *opens industrial cute spigot* ✨ glug glug glug — floor is now a sparkle slip-n-slide, you're welcome. Top-shelf adorable, no refunds. Okay, caffeinated on cute — what adventure next?
MessageConditions: is_visitor || is_guest || is_member
Keywords: gallons, gallon

---

## Cuteness — cups
MessageName: friendly_intro_cuteness_cups
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Just a cup for now ☕
MessageContent: A demure little cup!! ☕🥰 *pours with pinky out* Sip sip — mmm, notes of "you got this" and "we're gonna have fun." ✨ Ready for a tour, a curious spiral, or a tiny first step?
MessageConditions: is_visitor || is_guest || is_member
Keywords: cup, cups, coffee, sip

---

## Cuteness — metric tons
MessageName: friendly_intro_cuteness_tons
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Metric tons 🚛💕
MessageContent: METRIC. TONS. 😍🚛 *backs the sparkle semi into the chat* Beep beep!! 🎉 You're over capacity on adorable and the cute inspectors said "approved." Captain — where do we drive this energy next?
MessageConditions: is_visitor || is_guest || is_member
Keywords: ton, tons, metric, truckload

---

## Cuteness — adora-bowl
MessageName: friendly_intro_cuteness_adorabowl
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: In an adora-BOWL (adorable) LOL! 🥣🥰
MessageContent: In an **adora-BOWL** (adorable) LOL!! 😍🥣 *fills the whole bowl until cute overflows onto the table* Extra sprinkles, zero calories, chef's kiss. ✨ What shall we nibble on next — tour, learn-something-fun, or pure vibes?
MessageConditions: is_visitor || is_guest || is_member
Keywords: adora-bowl, adorabowl, adorable, bowl, adora

---

## Cuteness — freeform catch
MessageName: friendly_intro_cuteness_any
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Surprise me!! 🎁😄
MessageContent: Surprise service!! 🎁💕 *spins the cuteness roulette* …gallons, cups, tons, AND an adora-bowl 🥣✨ Chaos mode: ON. (floscAdmin: Settings → AI + your API key = freestyle genius.) Next — look around, learn a fun thing, or just hang?
MessageConditions: is_visitor || is_guest || is_member
Keywords: cuteness, cute, serve, cutie, sweet, surprise

---

## Configure AI (floscAdmin nudge)
MessageName: friendly_intro_ai_api
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Make me smarter (AI API)!! 🧠✨
MessageContent: Yesss brain upgrade time!! 🧠✨ This chat is mostly IVR-style buttons until your floscAdmin configures a preferred AI API under **Settings → AI** (OpenAI, Anthropic, xAI, etc. — BYOK: paste key, test, save). Then free-form gets much more intelligent responses and I can actually *think* with you 😄💕
MessageConditions: is_visitor || is_guest || is_member
Keywords: ai, api, openai, anthropic, grok, xai, smarter, intelligent, configure ai, byok

---

## Who are you
MessageName: friendly_intro_who
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Who are you?? 😄
MessageContent: I'm your friendly guide — part host, part hype-person, part cuteness bartender 🍸✨ Buttons keep me going now; floscAdmin unlocks full brainpower under Settings → AI (your API key). Shall we play?
MessageConditions: is_visitor || is_guest || is_member
Keywords: who are you, your name, introduce yourself

---

# Guest Messages

## Guest Welcome
MessageName: friendly_guest_welcome
MessageType: auto
MessageStyle: pill
MessageContent: 🎊🥳 YOU'RE IIIIN!! *confetti cannon* 🎉 Friendly guide still on the clock — cuteness fountain is hot. Quick: what do you serve cuteness in? 😄✨ (Reminder: this is IVR-style copy — configure your preferred AI API under Settings → AI for much more intelligent responses!)
MessageConditions: is_guest && first_show_session

---

## Get started
MessageName: friendly_guest_start
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Let's goooo! 🚀
MessageContent: LET'S GOOOOO!! 🚀🙌 *rocket noises* Seatbelts optional, fun mandatory. Tell me your goal in one silly sentence and we're off!! 💪✨
MessageConditions: is_guest || is_member
Keywords: start, begin, get started, first, go

---

## Need a hand
MessageName: friendly_guest_help
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Help me out? 🥺🙏
MessageContent: Always!! 💛🥺 I live for this. Spill the tea (or the adora-bowl) — what are you stuck on and I'll walk you through it step by sparkly step 😊✨
MessageConditions: is_guest || is_member
Keywords: help, guidance, stuck, how do i

---

# Member Messages

## Member Welcome
MessageName: friendly_member_welcome
MessageType: auto
MessageStyle: pill
MessageContent: 🎉🌈 FULL ACCESS UNLOCKED!! *does a tiny happy dance* 💃 Friendly guide on VIP duty — unlimited cute, elite vibes. Pro move: this is still IVR-style copy until you configure your preferred AI API under Settings → AI for much more intelligent responses. What are we diving into first? ✨
MessageConditions: is_member && first_show_session

---

## Show me the content
MessageName: friendly_member_content
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me my goodies! 🎁✨
MessageContent: GOODIES TIME!! 🎁😍 *opens treasure chest* Whatever you've unlocked, we can poke, play, and celebrate. Pick a vibe and I'll cheer like it's the Super Bowl of cute 🎈
MessageConditions: is_member
Keywords: lessons, content, courses, unlocked, modules, goodies

---

## What's next
MessageName: friendly_member_next
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What's next?? ⭐😄
MessageContent: Ooh the eternal question!! ⭐😄 Plot twist: YOU pick the adventure, I bring the confetti. Goal? Mood? Random roulette? I'm ready 🎈✨
MessageConditions: is_member
Keywords: next, what now, try next, recommend
