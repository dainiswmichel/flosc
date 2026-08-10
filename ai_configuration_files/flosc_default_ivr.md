# FLOSC Starter Template — IVR Configuration
# Purpose: neutral template for floscAdmins to duplicate and customize.
# Identity: "FLOSC starter" — clearly a blank you rewrite, not a live product persona.
# Character defaults (use these as live examples instead):
# - flosc_default_technical_ivr.md
# - flosc_default_friendly_ivr.md
# - flosc_default_br3nda_emotional_support_ivr.md

---

# Freeline Messages (visitors)

## Welcome
MessageName: template_welcome
MessageType: auto
MessageStyle: card
MessageContent: Welcome — this is the **FLOSC starter template**. Swap this welcome for your product voice. This is just IVR-style copy — remember to configure your preferred AI API under Settings → AI for much more intelligent responses. What would you like to try first?
MessageConditions: is_visitor && first_show_session

---

## Intro Prompt
MessageName: template_intro_prompt
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Show me what this is
MessageContent: FLOSC runs guided chat journeys (freeline → login → offer → sale → content). Replace this copy with your own positioning and first step. Without AI configured, free text falls back to buttons and short defaults.
MessageConditions: is_visitor || is_guest || is_member
Keywords: intro, overview, what is this, help, flosc

---

## Customize me
MessageName: template_intro_customize
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How do I customize this?
MessageContent: floscAdmin checklist: (1) Identity — name, slug, color; (2) IVR Management — your messages; (3) Settings → AI — configure your preferred AI API (BYOK) for much more intelligent free-form responses. Until then, this is just IVR-style copy.
MessageConditions: is_visitor || is_guest || is_member
Keywords: customize, edit, configure, admin, setup, ai, api

---

# Guest Messages

## Guest Welcome
MessageName: template_guest_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Guest mode (starter template). Replace this with your real guest onboarding.
MessageConditions: is_guest && first_show_session

---

## Guest Prompt
MessageName: template_guest_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What should I do first?
MessageContent: Starter guidance — replace with your real first action for guests.
MessageConditions: is_guest || is_member
Keywords: first step, start, onboarding

---

# Member Messages

## Member Welcome
MessageName: template_member_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Member access (starter template). Replace this with your member welcome and next steps.
MessageConditions: is_member && first_show_session

---

## Member Prompt
MessageName: template_member_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show my next step
MessageContent: Starter member copy — replace with your real progression.
MessageConditions: is_member
Keywords: next step, continue, member
