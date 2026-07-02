# FLOSC Starter IVR Configuration
# Creation template for new flows. Copy and customize per flow.

---

# Freeline Messages

## Welcome
MessageName: starter_welcome
MessageType: auto
MessageStyle: card
MessageContent: Welcome. This is your starter IVR flow template.
MessageConditions: is_visitor && first_show_session

---

## Start
MessageName: starter_begin
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Let's begin
MessageContent: Great. I can guide you through this flow setup.
MessageConditions: is_visitor || is_guest || is_member
Keywords: start, begin, setup
