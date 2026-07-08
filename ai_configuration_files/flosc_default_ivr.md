# FLOSC Starter Template — IVR Configuration
# Purpose: non-active template for floscAdmins to activate, duplicate, and customize.
# This file is intentionally neutral and should not be treated as the out-of-box live default.
# Out-of-box shipped defaults are purpose-built files such as:
# - flosc_default_technical_ivr.md
# - flosc_default_friendly_ivr.md
# - flosc_default_br3nda_emotional_support_ivr.md

---

# Freeline Messages (visitors)

## Welcome
MessageName: template_welcome
MessageType: auto
MessageStyle: card
MessageContent: Welcome. This is the FLOSC starter template. Replace this text with your product-specific welcome.
MessageConditions: is_visitor && first_show_session

---

## Intro Prompt
MessageName: template_intro_prompt
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Show me what this is
MessageContent: This is starter copy. Replace with your own positioning, audience language, and first-step guidance.
MessageConditions: is_visitor || is_guest || is_member
Keywords: intro, overview, what is this, help

---

# Guest Messages

## Guest Welcome
MessageName: template_guest_welcome
MessageType: auto
MessageStyle: pill
MessageContent: You are in guest mode. Replace this with your guest onboarding message.
MessageConditions: is_guest && first_show_session

---

## Guest Prompt
MessageName: template_guest_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What should I do first?
MessageContent: This is starter guidance. Replace with your real first action.
MessageConditions: is_guest || is_member
Keywords: first step, start, onboarding

---

# Member Messages

## Member Welcome
MessageName: template_member_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Full access detected. Replace this with your member welcome and next-step instructions.
MessageConditions: is_member && first_show_session

---

## Member Prompt
MessageName: template_member_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Show my next step
MessageContent: This is starter member copy. Replace with your real member progression logic.
MessageConditions: is_member
Keywords: next step, continue, member
