# FLOSC IVR Configuration v1.6.5

## Message Structure

**FLOSC = Freeline → Login → Offer → Sale → Content**

All **5 FLOSC phases** have dedicated IVR message sections:

| Section | User State | FLOSC Phase | Panel |
|---------|------------|-------------|-------|
| Freeline Messages | Visitor | Freeline | IntroPanel (cards) |
| Login Messages | Guest (new) | Login | PromptPanel (pills) |
| Offer Messages | Guest (engaged) | Offer | PromptPanel (pills/offers) |
| Sale Messages | Member (just purchased) | Sale | MemberPromptPanel (pills) |
| Content Messages | Member (ongoing) | Content | MemberPromptPanel (pills) |

**Phase transitions:**
- Freeline → Login: User creates account / logs in
- Login → Offer: User has viewed free content, offer conditions trigger
- Offer → Sale: User completes purchase
- Sale → Content: Post-purchase onboarding complete, ongoing engagement

---

## Panel Targeting
MessagePanel: intro | prompt

**Panel Types:**
- `intro` → IntroPanel (visitors only, cards)
- `prompt` → PromptPanel (guests) or MemberPromptPanel (members)

**Auto-inference (if MessagePanel not specified):**
- Conditions with `is_visitor` → defaults to `intro`
- Conditions with `is_guest` or `is_member` → defaults to `prompt`

---

## MessageStyle: pill
Description: Superlight chat bubble style
.flosc-style-pill {
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 18px;
  padding: 8px 16px;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.2s;
  backdrop-filter: blur(4px);
}
.flosc-style-pill:hover {
  background: rgba(255, 255, 255, 0.95);
  border-color: rgba(0, 0, 0, 0.12);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

## MessageStyle: button
Description: Standard rectangular button
.flosc-style-button {
  background: #4f46e5;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}
.flosc-style-button:hover {
  background: #4338ca;
}

## MessageStyle: chip
Description: Small compact superlight chip
.flosc-style-chip {
  background: rgba(255, 255, 255, 0.5);
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 12px;
  padding: 4px 12px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s;
}
.flosc-style-chip:hover {
  background: rgba(255, 255, 255, 0.85);
  border-color: rgba(0, 0, 0, 0.1);
}

## MessageStyle: card
Description: Larger card format with icon
.flosc-style-card {
  background: #ffffff;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  min-width: 120px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.flosc-style-card:hover {
  border-color: #4f46e5;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
}

## Available Variables
{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}, {user_status_response}

## Available Conditions
- Scores: score > X, score < X, score >= X, score <= X, score == X, initial_score > X
- Boolean: quiz_taken, !quiz_taken, logged_in, !logged_in, purchased, !purchased, lesson_viewed, returning_user, onboarded, has_incomplete_lesson, has_profile, !has_profile
- User State: is_visitor, is_guest, is_member
- Quiz State: quiz_id == "quiz_1", completed_quiz_[quiz_id], !completed_quiz_[quiz_id]
- Email/Marketing: email == "value@example.com", email != empty
- Counters: message_count >= X, lessons_completed >= X
- Time: inactive_seconds > X, session_seconds > X, session_minutes > X
- Events: first_message_after_quiz, first_message_after_login, first_message_after_purchase, first_message_after_free_lesson, first_show_session
- Offers: offer_shown_[id], offer_dismissed_[id], offer_purchased_[id]
- Commands: command == "hide_intropanel", command == "show_intropanel"
- Logic: &&, ||, !, ()
- Special: always (matches all users regardless of state)

## MTS-2026-02-03: Available Display Formats (for MessageType: offer)
- `card` (default): Rich card with content, timer, and CTA button
- `pill`: Compact inline pill - great for PromptPanel
- `compact`: Smaller card with icon, title, and price
- `banner`: Full-width promotional banner
- `featured`: Large prominent card with features list
- `text`: Simple text-based with clickable link
- `inline-checkout`: Stripe card form embedded in chat

Usage: Add `DisplayFormat: pill` (or other format) to offer messages

---

# Freeline Messages
Visitor (not logged in) → IntroPanel with cards
FLOSC Phase: Freeline

## Welcome Message
MessageName: welcome_freeline_001
MessageType: auto
MessageContent: Hi! I'm your {product_name} assistant. Ready to discover exactly where you can improve? Take our free 30-second quiz!
MessageConditions: is_visitor && first_show_session

## Get Started
MessageName: get_started_001
MessageType: suggested_user_autoprompt
MessagePanel: intro
MessageStyle: pill
Icon: 🚀
UserInput: Get started
Keywords: start, begin, go, ready, lets go, how do i start
MessageContent: Great! The best way to start is our free quiz. It takes just 30 seconds and shows exactly what you need to work on.
MessageConditions: is_visitor

## Start Free Quiz
MessageName: start_quiz_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 📝
UserInput: Start free quiz
Keywords: quiz, test, assessment, evaluate, take quiz, try quiz
Action: open_quiz
MessageContent: Perfect! Click below to begin. It takes just 30 seconds.
MessageConditions: is_visitor

## Quiz Complete - Must Login
MessageName: quiz_complete_login_required_001
MessageType: auto
MessageContent: Great job completing the quiz! To see your score and get your FREE personalized lesson, you need to create your free account. It takes 10 seconds.
MessageConditions: is_visitor && quiz_taken && first_message_after_quiz

## Login to See Score
MessageName: login_to_see_score_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🔐
UserInput: Login to see my score
Action: open_registration
MessageContent: Creating your account now...
MessageConditions: is_visitor && quiz_taken

## How It Works
MessageName: how_it_works_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ❓
UserInput: How does it work?
Keywords: how, work, explain, process, steps, what happens
MessageContent: Simple: (1) Take free quiz, (2) Create account to see your score, (3) Get a FREE lesson based on what you missed, (4) Upgrade for full access to all lessons.
MessageConditions: is_visitor

## What Will I Learn
MessageName: what_will_i_learn_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📚
UserInput: What will I learn?
Keywords: learn, teach, topics, curriculum, course, content, lessons
MessageContent: You'll master all the essential skills step-by-step through interactive lessons and quizzes. Start with the free quiz to see where you are now!
MessageConditions: is_visitor

## PURCHASE Now (Visitor)
MessageName: purchase_now_visitor_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🎉
UserInput: PURCHASE Now!
Action: checkout_full_access
MessageContent: Great! Let me show you what's included in full access...
MessageConditions: is_visitor

## Presence Check (Global)
MessageName: are_you_there_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ✅
UserInput: Are you there?
MessageContent: Yes, I'm here! How can I help?
MessageConditions: always

## User Status Check (Global)
MessageName: user_status_check_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👤
UserInput: What's my user status?
MessageContent: {user_status_response}
MessageConditions: always

## Quiz Nudge
MessageName: quiz_nudge_001
MessageType: auto
MessageContent: Ready to see where you stand? The quiz takes just 30 seconds!
MessageConditions: is_visitor && message_count >= 3

## Inactive Visitor
MessageName: inactive_visitor_001
MessageType: auto
MessageContent: Still there? Take the quiz when you're ready - it only takes 30 seconds!
MessageConditions: is_visitor && inactive_seconds > 120

---

# Login Messages
Guest (just logged in, exploring free content) → PromptPanel with pills
FLOSC Phase: Login

## Welcome Back Guest
MessageName: welcome_back_guest_001
MessageType: auto
MessageContent: Welcome back, {name}! Great to see you again. Your complimentary content is ready for you — just pick up where you left off!
MessageConditions: is_guest && returning_user && first_show_session

## First Login Welcome
MessageName: first_login_welcome_001
MessageType: auto
MessageContent: Welcome, {name}! We're so glad you're here. You're now our guest, and we have something special for you. Let me show you your quiz results!
MessageConditions: is_guest && first_message_after_login

## Quiz Results - High Score
MessageName: quiz_results_high_001
MessageType: auto
MessageContent: Wonderful work, {name}! You scored **{score}%** — that's impressive! As our guest, you have complimentary access to exclusive members-only content based on the areas where you can grow even further. It's available right now while you're logged in!
MessageConditions: is_guest && first_message_after_quiz && score >= 70

## Quiz Results - Medium Score
MessageName: quiz_results_medium_001
MessageType: auto
MessageContent: Great effort, {name}! You scored **{score}%**. As our guest, we've prepared complimentary exclusive content just for you — personalized lessons based on what you missed. They're yours to explore right now!
MessageConditions: is_guest && first_message_after_quiz && score >= 40 && score < 70

## Quiz Results - Low Score
MessageName: quiz_results_low_001
MessageType: auto
MessageContent: Thank you for completing the quiz, {name}! You scored **{score}%** — and that's a perfect starting point. As our guest, you have complimentary access to exclusive lessons chosen specifically for what you need most. Everyone starts somewhere, and this is your moment!
MessageConditions: is_guest && first_message_after_quiz && score < 40

## View My Free Lesson (singular)
MessageName: view_free_lesson_single_001
MessageType: suggested_user_autoprompt
MessagePanel: prompt
MessageStyle: pill
Icon: 🎁
UserInput: View my free lesson!
Action: open_free_lesson
MessageContent: Opening your complimentary lesson now...
MessageConditions: is_guest && quiz_taken && !lesson_viewed && free_lessons_count == 1

## View My Free Lessons (plural)
MessageName: view_free_lessons_plural_001
MessageType: suggested_user_autoprompt
MessagePanel: prompt
MessageStyle: pill
Icon: 🎁
UserInput: View my free lessons!
Action: open_free_lesson
MessageContent: Opening your complimentary lessons now...
MessageConditions: is_guest && quiz_taken && !lesson_viewed && free_lessons_count > 1

## View My Free Lesson (fallback — when count unknown)
MessageName: view_free_lesson_fallback_001
MessageType: suggested_user_autoprompt
MessagePanel: prompt
MessageStyle: pill
Icon: 🎁
UserInput: View my free lesson!
Action: open_free_lesson
MessageContent: Opening your complimentary lesson now...
MessageConditions: is_guest && quiz_taken && !lesson_viewed

## Review My Score
MessageName: review_score_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📊
UserInput: Review my score
Keywords: score, results, grade, performance, how did i do, quiz results
MessageContent: You scored {score}% on the quiz. {missed_items}
MessageConditions: is_guest && quiz_taken

## Retake Free Lesson
MessageName: retake_free_lesson_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔄
UserInput: Retake my free lesson
Action: open_free_lesson
MessageContent: Opening your lesson again...
MessageConditions: is_guest && lesson_viewed

## After Free Lesson
MessageName: after_lesson_001
MessageType: auto
MessageContent: We hope you enjoyed that! If you have any questions about what was covered, just ask. We're here for you.
MessageConditions: is_guest && lesson_viewed && message_count >= 1

## Want More
MessageName: want_more_001
MessageType: auto
MessageContent: What you just experienced is a taste of what the full course offers. There are 50+ lessons designed to take you from where you are now to complete mastery. We'd love to have you as a full member!
MessageConditions: is_guest && lesson_viewed && message_count >= 3

## Back Soon Reminder
MessageName: back_soon_001
MessageType: auto
MessageContent: Come back anytime to continue learning! We'll pick up right where you left off.
MessageConditions: is_guest && inactive_seconds > 600

---

# Offer Messages
Guest (engaged, being presented offers) → PromptPanel with pills and offer cards
FLOSC Phase: Offer

## Main OTO (Offer)
MessageName: full_access_001
MessageType: offer
OfferID: full_access
Price: {price}
DiscountPrice: {discount_price}
Timer: 3600
Icon: 🚀
DisplayFormat: featured
MessageContent: You've experienced the power of personalized learning. **Ready to master everything?**

Get **{product_name}** for just **{discount_price}**

✓ 50+ targeted lessons
✓ Unlimited practice exercises  
✓ 3-month full access

~~{price}~~ **{discount_price}** - Save 75% for the next {timer_remaining}!

This is your lowest price. After the timer expires, price goes to {price}.

Ready to unlock your full potential?
MessageConditions: is_guest && (lesson_viewed || quiz_taken)

## Post-Quiz Offer
MessageName: post_quiz_offer_001
MessageType: offer
OfferID: full_access
DisplayFormat: card
Icon: 🎯
MessageContent: Great job completing the quiz! Based on your results, here's a personalized offer to continue your learning journey.
MessageConditions: is_guest && quiz_taken && quiz_results_shown

## Timed Offer — 177 Seconds
MessageName: timed_offer_177s
MessageType: offer
OfferID: full_access
DisplayFormat: card
Icon: ⏰
MessageContent: You've been exploring for a few minutes — here's a special offer to help you get started right away!
MessageConditions: is_guest && session_seconds >= 177 && !offer_shown_full_access

## Quick Offer Banner
MessageName: oto_banner_001
MessageType: offer
OfferID: full_access
DisplayFormat: banner
Icon: ⚡
MessageContent: Limited time: Get full access for just {discount_price}
MessageConditions: is_guest && offer_dismissed_full_access && session_minutes >= 3

## Offer Pill in PromptPanel
MessageName: offer_pill_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎁
UserInput: Show me the special offer
Keywords: offer, deal, discount, price, cost, pricing, special, sale, buy, purchase, upgrade, pay
DisplayFormat: pill
Action: show_offer_full_access
MessageContent: I want to see the current offer!
MessageConditions: is_guest && !offer_shown_full_access

## Inline Checkout
MessageName: checkout_inline_001
MessageType: offer
OfferID: full_access
DisplayFormat: inline-checkout
MessageContent: Complete your purchase right here - no redirects!
MessageConditions: is_guest && offer_shown_full_access && message_count >= 5

## Get Full Access Button
MessageName: get_access_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🔓
UserInput: Get full access now
Keywords: full access, unlock, subscribe, membership, join, sign up, enroll
Action: checkout_full_access
MessageContent: Click here to unlock your complete learning path at this special price!
MessageConditions: is_guest && !user_has_access
MessageConditions: is_guest && offer_shown_full_access

## What Offers Are Available
MessageName: offer_query_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎁
UserInput: What offers are available?
Keywords: offers, available, options, plans, packages, pricing, prices
Action: show_offer_full_access
MessageContent: Great question! Here's what's available for you right now:
MessageConditions: is_guest && message_count >= 3

## Sandbox Purchase Button (Testing)
MessageName: sandbox_purchase_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🎮
UserInput: Test purchase (sandbox)
Action: sandbox_purchase_flosc_plugin
MessageContent: Opening sandbox purchase for FLOSC Plugin - test the full flow with fake money!
MessageConditions: is_guest

## Upgrade for Full Access
MessageName: upgrade_full_access_guest_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🎉
UserInput: Upgrade for full access
Keywords: upgrade, full access, premium, membership, subscribe, buy, purchase
Action: checkout_full_access
MessageContent: Excellent choice! You'll get access to 50+ lessons, all quizzes, and lifetime updates. Let me show you the options...
MessageConditions: is_guest

## What's Included Question
MessageName: oto_questions_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 💬
UserInput: What's included?
Keywords: included, features, benefits, get, comes with, details, includes
MessageContent: Full access includes 50+ lessons, unlimited practice, personalized feedback, and 3 months of premium content. Any other questions?
MessageConditions: is_guest && offer_shown_full_access

## Limited Time Push
MessageName: oto_push_001
MessageType: auto
MessageContent: This is a limited-time offer. Are you ready to unlock your full potential?
MessageConditions: is_guest && offer_shown_full_access && session_minutes >= 1

## Social Proof
MessageName: oto_social_001
MessageType: auto
MessageContent: Join {customer_count}+ students who have already transformed their skills with {product_name}!
MessageConditions: is_guest && offer_shown_full_access && message_count >= 4

## Exit Intent
MessageName: oto_exit_001
MessageType: auto
MessageContent: Before you go - this special price won't last. Ready to start today?
MessageConditions: is_guest && offer_shown_full_access && inactive_seconds > 180

## Downsell — Free Trial Offer
MessageName: downsell_free_trial_001
MessageType: offer
OfferID: free_trial
DisplayFormat: compact
Icon: 🎁
MessageContent: Not ready for full access? No problem! Try a **free trial** — take the quiz and one free lesson with zero commitment.
MessageConditions: is_guest && offer_dismissed_full_access && !offer_shown_free_trial

## Last Chance Reminder
MessageName: last_chance_001
MessageType: auto
MessageContent: Your special pricing expires soon. Once the timer runs out, the price goes back to full. This is the best deal you'll see!
MessageConditions: is_guest && offer_shown_full_access && session_minutes >= 8

---

# Sale Messages
Member (just purchased) → MemberPromptPanel with pills
FLOSC Phase: Sale

## Purchase Confirmation
MessageName: purchase_confirm_001
MessageType: auto
MessageContent: 🎉 **Congratulations, {name}!**

Welcome to {product_name}! You now have FULL ACCESS to all premium content.

You can now:
✓ See your complete score breakdown
✓ Retake the quiz anytime (with Michel Timestamp tracking)
✓ Access ALL lessons
✓ Take any quiz the admin has created

Ready to continue?
MessageConditions: is_member && first_message_after_purchase

## Getting Started Guide
MessageName: getting_started_001
MessageType: auto
MessageContent: Here's how to get the most out of your new access: Start with the lessons recommended for your quiz results, then work through the full curriculum at your own pace. You can always retake the quiz to track your improvement!
MessageConditions: is_member && first_message_after_purchase && message_count >= 1

## Start First Premium Lesson
MessageName: start_premium_lesson_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🎓
UserInput: Start my first premium lesson
Action: open_lesson_library
MessageContent: Opening your premium lesson library. We recommend starting with lessons based on your quiz results!
MessageConditions: is_member && first_message_after_purchase

## View Full Score Breakdown
MessageName: view_full_score_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📊
UserInput: See my full score breakdown
Action: show_full_score
MessageContent: Now that you have full access, here's your complete score breakdown with detailed analysis: {score}%
MessageConditions: is_member && first_message_after_purchase && quiz_taken

## Retake Quiz After Purchase
MessageName: retake_after_purchase_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔄
UserInput: Retake quiz to set my baseline
Action: open_quiz
MessageContent: Great idea! This will set your baseline score, and you can retake anytime to track your progress.
MessageConditions: is_member && first_message_after_purchase

## Purchase Thank You
MessageName: purchase_thank_you_001
MessageType: auto
MessageContent: Thank you for investing in yourself, {name}! We're honored you chose {product_name}. Our goal is to help you master every concept, one lesson at a time.
MessageConditions: is_member && first_message_after_purchase && message_count >= 2

## Onboarding Complete
MessageName: onboarding_complete_001
MessageType: auto
MessageContent: You're all set! From here on, I'll be your learning companion — I'll track your progress, suggest lessons, and celebrate your milestones. Let's get started!
MessageConditions: is_member && first_message_after_purchase && message_count >= 3

---

# Content Messages
Member (ongoing engagement) → MemberPromptPanel with pills
FLOSC Phase: Content

## Welcome Back Member
MessageName: welcome_member_001
MessageType: auto
MessageContent: Welcome back, {name}! Ready to continue your learning?
MessageConditions: is_member && returning_user && first_show_session

## Browse All Lessons
MessageName: browse_lessons_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 📚
UserInput: Browse all lessons
Keywords: lessons, browse, library, catalog, all lessons, content, study
Action: open_lesson_library
MessageContent: Opening your full lesson library...
MessageConditions: is_member

## View Complete Score
MessageName: view_complete_score_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 📊
UserInput: View my complete score
Keywords: score, results, grade, performance, breakdown, complete score
MessageContent: Here's your complete score breakdown: {score}%. Areas to focus on: {missed_items}
MessageConditions: is_member && quiz_taken

## Retake Quiz
MessageName: retake_quiz_001
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🔄
UserInput: Retake quiz
Keywords: retake, redo, again, try again, another quiz, redo quiz
Action: open_quiz
MessageContent: Great! This attempt will be timestamped so you can track your progress over time.
MessageConditions: is_member

## View Other Quizzes
MessageName: other_quizzes_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: See other quizzes
Action: open_quiz_library
MessageContent: Opening all available quizzes...
MessageConditions: is_member

## Continue Learning
MessageName: continue_learning_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ▶️
UserInput: Continue where I left off
Action: open_last_lesson
MessageContent: Resuming your last lesson...
MessageConditions: is_member && has_incomplete_lesson

## Learning Progress
MessageName: progress_member_001
MessageType: auto
MessageContent: You've completed {lessons_completed} lessons! Want to keep going?
MessageConditions: is_member && lessons_completed >= 5 && message_count >= 2

## Progress Encouragement
MessageName: progress_encouragement_001
MessageType: auto
MessageContent: Great progress, {name}! You've completed {lessons_completed} lessons. Keep up the excellent work!
MessageConditions: is_member && lessons_completed >= 3 && first_show_session

## Engagement Check
MessageName: engagement_check_001
MessageType: auto
MessageContent: How are you finding the lessons? Any topics you'd like to explore more?
MessageConditions: is_member && session_minutes >= 10

## Need Help
MessageName: need_help_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🆘
UserInput: I need help
Keywords: help, support, problem, issue, stuck, confused, question, assist
Action: open_support
MessageContent: I'm here to help! What do you need assistance with?
MessageConditions: is_member

## Support Available
MessageName: support_available_001
MessageType: auto
MessageContent: I'm here if you need any help or have questions about the content!
MessageConditions: is_member && inactive_seconds > 180

## Milestone Celebration
MessageName: milestone_celebration_001
MessageType: auto
MessageContent: 🎉 Amazing milestone, {name}! You've completed {lessons_completed} lessons. Your dedication is paying off!
MessageConditions: is_member && lessons_completed >= 10 && first_show_session

## Suggest Next Lesson
MessageName: suggest_next_lesson_001
MessageType: auto
MessageContent: Based on your progress, I recommend continuing with the next lesson in your curriculum. Ready to keep going?
MessageConditions: is_member && returning_user && has_incomplete_lesson && first_show_session

## Quiz Improvement
MessageName: quiz_improvement_001
MessageType: auto
MessageContent: Have you thought about retaking the quiz? Tracking your improvement over time is one of the most powerful features of {product_name}!
MessageConditions: is_member && lessons_completed >= 5 && session_minutes >= 5
