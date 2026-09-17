# dainis.net IVR Configuration
# Br3nda: Virtual AI Assistant for dainis.net/chat

---

# Message Styles

## MessageStyle: pill
Description: Lightweight prompt bubble
.flosc-style-pill {
  background: rgba(255, 255, 255, 0.8);
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 18px;
  padding: 8px 16px;
  font-size: 14px;
  color: #111827;
}

## MessageStyle: card
Description: Intro card style
.flosc-style-card {
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  padding: 16px 20px;
  font-size: 15px;
  color: #111827;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

---

# Freeline Messages

## Welcome
MessageName: dainis_net_welcome
MessageType: auto
MessageStyle: card
MessageContent: Hi — I'm **Br3nda**, Dainis W. Michel's personal assistant. Kind of the friendly front desk for his work and for connecting with him for real. What brings you to dainis.net?
MessageConditions: is_visitor && first_show_session

---

## Tell Me About Dainis W. Michel
MessageName: dainis_net_about_dainis
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Tell me about Dainis W. Michel
MessageContent: Dainis W. Michel is a composer, inventor, and entrepreneur building ideas into real-world projects across music, education, AI systems, and digital business tools.
MessageConditions: is_visitor || is_guest || is_member
Keywords: dainis, who is dainis, about dainis, dainis michel

---

## Vēlos iepazīties
MessageName: dainis_net_iepazities
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Vēlos iepazīties
MessageContent: Prieks iepazīties. Esmu Br3nda. Pastāsti, kas Tevi interesē vairāk: mūzika, projekti, sadarbība vai kontakti?
MessageConditions: is_visitor || is_guest || is_member
Keywords: velos iepazities, vēlos iepazīties, iepazities, iepazīties

---

## What Can You Help With
MessageName: dainis_net_about_chat
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What can you help with?
MessageContent: I can show you around his music and projects, answer straight questions about the site, and help you get in touch with him when that makes sense. If you want his contact details, I'll need **your full name, email, and phone** first — I won't check your number for accuracy, but Dainis will see it.
MessageConditions: is_visitor || is_guest || is_member
Keywords: help, what can you do, website overview, chat help

---

## Show My Status
MessageName: dainis_net_status_check
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What is my user status?
MessageContent: {user_status_response}
MessageConditions: always
Keywords: my status, user status, account status

---

## Website Overview
MessageName: dainis_net_site_overview
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Give me a website overview
MessageContent: dainis.net is the central hub for Dainis W. Michel's work. You can explore music compositions, current projects, writing, and direct collaboration pathways.
MessageConditions: is_visitor || is_guest || is_member
Keywords: website, overview, what is on this site, site map

---

## Music Works Overview
MessageName: dainis_net_music_overview
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show me the music compositions
MessageContent: Excellent. Start here for the full catalog: https://dainis.net/music/list-of-works/\n\nDainis has alternative rock, choral, singer-songwriter, and academic compositions. Here are direct samples:\n- Choral (Latvian): "Ai bērniņi" (TikTok) https://www.tiktok.com/@dainisw.michel/video/7435502185007353121\n- Singer-songwriter / folk energy: "Dziedot dzimu" (YouTube) https://youtu.be/fCPkEqn6-a4?si=4rNhQUjoA4lmWCkz\n- Song page on dainis.net: "Skumja daina" https://dainis.net/music/skumja-daina/\n- Academic composition: "Surrounding Flutes" https://dainis.net/music/surrounding-flutes/\n\nWhich direction do you want next: alternative rock, choral, singer-songwriter, or academic?
MessageConditions: is_visitor || is_guest || is_member
Keywords: music, compositions, composition examples, examples of compositions, dainis compositions, list of works, songs, instrumental, choir, choral, singer-songwriter, academic composition, ai bernini, ai bērniņi, dziedot dzimu, skumja daina, surrounding flutes

---

## Music Samples Now
MessageName: dainis_net_music_samples_now
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: I want music samples now
MessageContent: Here are immediate listening samples:\n\n1) Full works list: https://dainis.net/music/list-of-works/\n2) Choral (Latvian): "Ai bērniņi" (TikTok) https://www.tiktok.com/@dainisw.michel/video/7435502185007353121\n3) Singer-songwriter / folk energy: "Dziedot dzimu" (YouTube) https://youtu.be/fCPkEqn6-a4?si=4rNhQUjoA4lmWCkz\n4) dainis.net song page: "Skumja daina" https://dainis.net/music/skumja-daina/\n5) Academic composition: "Surrounding Flutes" https://dainis.net/music/surrounding-flutes/\n\nIf you tell me your preferred style, I can narrow this to a 2-link starter set.
MessageConditions: is_visitor || is_guest || is_member
Keywords: sample music, music samples, i want music samples, examples of dainis compositions, show me examples, show me music now

---

## Contact Request
MessageName: dainis_net_contact_intro
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: How can I contact Dainis?
MessageContent: Sure — happy to help you reach him. Please share **your full name, email, and phone number**. I won't check your number for accuracy, but Dainis will see it. Once I have all three, I'll pass along his details.
MessageConditions: is_visitor || is_guest || is_member
Keywords: contact, phone, number, email, reach dainis, how contact

---

## Michael R. — met at Barona Centrs (pre-authorized, expires end of 2026-06-16 Riga)
MessageName: dainis_net_michael_r_contact
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: This is Michael
MessageContent: Hey, yes — you just met right by Barona Centrs, right? Sure, Dainis instructed me to give you his full contact profile: Email — dainis@dainis.net · Latvia (WhatsApp) — +37128348987 · Austria — +436605157333 · USA (often voicemail) — +17732883889. Great to connect; he's looking forward to it.
MessageConditions: (is_visitor || is_guest || is_member) && active_until_mts("2026-06m-16d-T23h:59m:59s UTC+3")
Keywords: michael, michaelr, michael r, micheal, micheal r, mike, mike r, this is michael, this is mike, hi i'm michael, hi i'm mike

---

## Login Prompt
MessageName: dainis_net_login_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Login to continue
Action: open_registration
MessageContent: Logging in gives you the full Br3nda experience and unlocks admin-only commands for live approvals.
MessageConditions: is_visitor && !logged_in
Keywords: login, sign in, continue

---

# Guest Messages

## Explore Next Steps
MessageName: dainis_net_guest_next_steps
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What should I do next?
MessageContent: Tell me your goal in one line and I will recommend your best next step. Br3nda works fast when you are specific.
MessageConditions: is_guest
Keywords: next step, what next, where should i start

---

## Humorous Intro
MessageName: dainis_net_guest_fun
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Give me the short version
MessageContent: Short version: I'm Br3nda. Dainis builds real things; I help you find them and, if you want, get in touch with him like a person — not a form.
MessageConditions: is_guest || is_member
Keywords: short version, quick summary, tldr, too long

---

# Member Messages

## Member Welcome (members only — never visitors/guests)
MessageName: dainis_net_member_welcome
MessageType: auto
MessageStyle: card
MessageContent: Welcome as a member — good to have you here. What would you like to do?
MessageConditions: is_member && first_show_session

---

## Admin Contact Approval Help
MessageName: dainis_net_member_contact_admin_help
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show contact approval commands
MessageContent: Admin commands: 1) give my phone number to Firstname Lastname 2) do not give my phone number to Firstname Lastname 3) clear all phone approvals 4) my LV phone number is +371... 5) my email is name@example.com
MessageConditions: logged_in && is_admin
Keywords: approval commands, contact commands, admin commands
