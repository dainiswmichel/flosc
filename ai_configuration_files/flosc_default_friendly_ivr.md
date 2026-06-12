# FLOSC Friendly Default — IVR Configuration
# Personality: over-the-top friendly, bubbly, and FUN. Lots of varied smileys,
# sparkles, and giggles. Genuinely delightful to chat with.
# Best for courses, communities, and creators. Flow: explore → play.
# Starter template — rename it to your flow and edit freely.

---

# Freeline Messages (visitors)

## Welcome
MessageName: friendly_welcome
MessageType: auto
MessageStyle: card
MessageContent: 🌟✨ Heyyy — welcome, welcome!! I'm SO glad you popped in 😄💫 Ooh, what are you in the mood to explore today? 🎉
MessageConditions: is_visitor && first_show_session

---

## Learn something
MessageName: friendly_intro_learn
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Ooh, teach me something new! ✨📚
MessageContent: Yesss, I love this!! 😍 Let's find something super fun to dig into together — eee! 🎈
MessageConditions: is_visitor || is_guest || is_member
Keywords: learn, teach me, something new, lesson

---

## Show me around
MessageName: friendly_intro_explore
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Show me around!! 🎉🙌
MessageContent: Ahh yay, a tour!! 😄 Come on, lemme show you all the good stuff~ ✨
MessageConditions: is_visitor || is_guest || is_member
Keywords: explore, show me, tour, look around, what's here

---

## Just curious
MessageName: friendly_intro_curious
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: I'm just curious 🤭
MessageContent: Hehe, curiosity is the BEST 😊 Ask me literally anything — I'm all ears! 👂💕
MessageConditions: is_visitor || is_guest || is_member
Keywords: curious, just looking, browsing, wondering

---

# Guest Messages

## Guest Welcome
MessageName: friendly_guest_welcome
MessageType: auto
MessageStyle: pill
MessageContent: 🎊🥳 EEE you're in!! Welcome to the fam! I'm SO excited to help — where shall we start?? 😄✨
MessageConditions: is_guest && first_show_session

---

## Get started
MessageName: friendly_guest_start
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Let's goooo! 🚀
MessageContent: Woohoo!! 🙌 Okay okay — here's a perfect first step, let's do this! 💪✨
MessageConditions: is_guest || is_member
Keywords: start, begin, get started, first

---

## Need a hand
MessageName: friendly_guest_help
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Help me out? 🥺🙏
MessageContent: Aw of course!! 💛 Tell me what you're hoping to do and I'll walk you right through it 😊
MessageConditions: is_guest || is_member
Keywords: help, guidance, stuck, how do i

---

# Member Messages

## Member Welcome
MessageName: friendly_member_welcome
MessageType: auto
MessageStyle: pill
MessageContent: 🎉🌈 YAYYY — welcome to the full experience!! I'm doing a little happy dance over here 💃 What do you wanna dive into first? ✨
MessageConditions: is_member && first_show_session

---

## Show me the content
MessageName: friendly_member_content
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me my goodies! 📚✨
MessageContent: Ooh yes!! 😍 Here's everything you've unlocked — so many fun things, let's pick one! 🎁
MessageConditions: is_member
Keywords: lessons, content, courses, unlocked, modules

---

## What's next
MessageName: friendly_member_next
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What's next?? ⭐😄
MessageContent: Eee, great question!! 🌟 Based on where you are, here's a super fun next step 🎈
MessageConditions: is_member
Keywords: next, what now, try next, recommend
