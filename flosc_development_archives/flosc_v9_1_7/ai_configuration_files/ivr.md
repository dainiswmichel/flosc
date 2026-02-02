# FLOSC IVR Configuration

## MessageStyle: pill
Description: Rounded ChatGPT/Claude style
.flosc-style-pill {
  background: #f0f0f0;
  border: 1px solid #d0d0d0;
  border-radius: 20px;
  padding: 8px 16px;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: background 0.2s;
}
.flosc-style-pill:hover {
  background: #e0e0e0;
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
Description: Small compact chip
.flosc-style-chip {
  background: #e5e7eb;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  padding: 4px 12px;
  font-size: 12px;
  cursor: pointer;
  transition: background 0.2s;
}
.flosc-style-chip:hover {
  background: #d1d5db;
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
{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}

## Available Conditions
- Scores: score > X, score < X, score >= X, score <= X, score == X, initial_score > X
- Boolean: quiz_taken, !quiz_taken, logged_in, !logged_in, purchased, !purchased, lesson_viewed, returning_user, onboarded, has_incomplete_lesson, has_profile, !has_profile
- Quiz State: quiz_id == "quiz_1", completed_quiz_[quiz_id], !completed_quiz_[quiz_id]
- Email/Marketing: email == "value@example.com", email != empty
- Counters: message_count >= X, lessons_completed >= X
- Time: inactive_seconds > X, session_seconds > X, session_minutes > X
- Events: first_message_after_quiz, first_message_after_login, first_message_after_purchase, first_message_after_free_lesson, first_show_session
- Offers: offer_shown_[id], offer_dismissed_[id], offer_purchased_[id]
- Commands: command == "hide_intropanel", command == "show_intropanel"
- Logic: &&, ||, !, ()

---

# Freeline Messages
Visitor (not logged in) → Take quiz → MUST login to see score.

## Welcome Message
MessageName: welcome_freeline_001
MessageType: auto
MessageContent: Hi! I'm your {product_name} assistant. Ready to discover exactly where you can improve? Take our free 30-second quiz!
MessageConditions: is_visitor && first_show_session

## Get Started
MessageName: get_started_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 🚀
UserInput: Get started
MessageContent: Great! The best way to start is our free quiz. It takes just 30 seconds and shows exactly what you need to work on.
MessageConditions: is_visitor

## Start Free Quiz
MessageName: start_quiz_001
MessageType: suggested_reply
MessageStyle: button
Icon: 📝
UserInput: Start free quiz
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
MessageType: suggested_reply
MessageStyle: button
Icon: 🔐
UserInput: Login to see my score
Action: open_registration
MessageContent: Creating your account now...
MessageConditions: is_visitor && quiz_taken

## How It Works
MessageName: how_it_works_001
MessageType: suggested_reply
MessageStyle: pill
Icon: ❓
UserInput: How does it work?
MessageContent: Simple: (1) Take free quiz, (2) Create account to see your score, (3) Get a FREE lesson based on what you missed, (4) Upgrade for full access to all lessons.
MessageConditions: is_visitor

## What Will I Learn
MessageName: what_will_i_learn_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 📚
UserInput: What will I learn?
MessageContent: You'll master all the essential skills step-by-step through interactive lessons and quizzes. Start with the free quiz to see where you are now!
MessageConditions: is_visitor

## PURCHASE Now
MessageName: purchase_now_visitor_001
MessageType: suggested_reply
MessageStyle: button
Icon: 🎉
UserInput: PURCHASE Now!
Action: checkout_oto_main
MessageContent: Great! Let me show you what's included in full access...
MessageConditions: is_visitor

## Presence Check
MessageName: are_you_there_001
MessageType: suggested_reply
MessageStyle: pill
Icon: ✅
UserInput: Are you there?
MessageContent: Yes, I'm here! How can I help?
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
Guest (logged in, not purchased) → See score → 1 free lesson → Offers → No quiz retake.

## Welcome Back Guest
MessageName: welcome_back_guest_001
MessageType: auto
MessageContent: Welcome back, {name}! 
MessageConditions: is_guest && returning_user && first_show_session

## Quiz Results - High Score
MessageName: quiz_results_high_001
MessageType: auto
MessageContent: Excellent, {name}! You scored {score}%. I've unlocked a FREE lesson based on areas where you can improve even more!
MessageConditions: is_guest && first_message_after_quiz && score >= 70

## Quiz Results - Medium Score
MessageName: quiz_results_medium_001
MessageType: auto
MessageContent: Good job, {name}! You scored {score}%. I've prepared a FREE lesson to help you improve!
MessageConditions: is_guest && first_message_after_quiz && score >= 40 && score < 70

## Quiz Results - Low Score
MessageName: quiz_results_low_001
MessageType: auto
MessageContent: Thanks for completing the quiz, {name}! You scored {score}%. Don't worry - everyone starts somewhere! I've prepared a FREE lesson to help you.
MessageConditions: is_guest && first_message_after_quiz && score < 40

## View My Free Lesson
MessageName: view_free_lesson_001
MessageType: suggested_reply
MessageStyle: button
Icon: 📖
UserInput: View my free lesson
Action: open_free_lesson
MessageContent: Opening your personalized lesson now...
MessageConditions: is_guest && quiz_taken

## Review My Score
MessageName: review_score_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 📊
UserInput: Review my score
MessageContent: You scored {score}% on the quiz. {missed_items}
MessageConditions: is_guest && quiz_taken

## Retake Free Lesson
MessageName: retake_free_lesson_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 🔄
UserInput: Retake my free lesson
Action: open_free_lesson
MessageContent: Opening your lesson again...
MessageConditions: is_guest && lesson_viewed

## Upgrade for Full Access
MessageName: upgrade_full_access_guest_001
MessageType: suggested_reply
MessageStyle: button
Icon: 🎉
UserInput: Upgrade for full access
Action: checkout_oto_main
MessageContent: Excellent choice! You'll get access to 50+ lessons, all quizzes, and lifetime updates. Let me show you the options...
MessageConditions: is_guest

## After Free Lesson
MessageName: after_lesson_001
MessageType: auto
MessageContent: How was that lesson? Questions about what we covered?
MessageConditions: is_guest && lesson_viewed && message_count >= 1

## Want More
MessageName: want_more_001
MessageType: auto
MessageContent: This is just the beginning. The full course has 50+ lessons covering everything you need to master.
MessageConditions: is_guest && lesson_viewed && message_count >= 3

---

# Sale Messages
Member (purchased) → Full access → Retake quiz with timestamps → All lessons/quizzes.

## Main OTO
MessageName: oto_main_001
MessageType: offer
OfferID: oto_main
Price: $100
DiscountPrice: $25
Timer: 3600
Icon: 🚀
MessageContent: You've experienced the power of personalized learning. **Ready to master everything?**

Get **{product_name}** for just **$25**

✓ 50+ targeted lessons
✓ Unlimited practice exercises  
✓ 3-month full access

~~$100~~ **$25** - Save 75% for the next {timer_remaining}!

This is your lowest price. After the timer expires, price goes to $100.

Ready to unlock your full potential?
MessageConditions: lesson_viewed && !purchased

## Get Full Access Button
MessageName: get_access_001
MessageType: suggested_reply
MessageStyle: button
Icon: 🔓
UserInput: Get full access now
Action: checkout_oto_main
MessageContent: Click here to unlock your complete learning path at this special price!
MessageConditions: offer_shown_oto_main && !purchased

## Limited Time Push
MessageName: oto_push_001
MessageType: auto
MessageContent: This is a limited-time offer. Are you ready to unlock your full potential?
MessageConditions: offer_shown_oto_main && !purchased && session_minutes >= 1

## What's Included Question
MessageName: oto_questions_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 💬
UserInput: What's included?
MessageContent: Full access includes 50+ lessons, unlimited practice, personalized feedback, and 3 months of premium content. Any other questions?
MessageConditions: offer_shown_oto_main && !purchased

## Social Proof
MessageName: oto_social_001
MessageType: auto
MessageContent: Join {customer_count}+ students who have already transformed their skills with {product_name}!
MessageConditions: offer_shown_oto_main && !purchased && message_count >= 4

## Exit Intent
MessageName: oto_exit_001
MessageType: auto
MessageContent: Before you go - this special price won't last. Ready to start today?
MessageConditions: offer_shown_oto_main && !purchased && inactive_seconds > 180

---

# Sale Messages
Member (purchased full access) → See complete score → Retake quiz with timestamps → All lessons/quizzes

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

## Welcome Back Member
MessageName: welcome_member_001
MessageType: auto
MessageContent: Welcome back, {name}! Ready to continue your learning?
MessageConditions: is_member && returning_user && first_show_session

## Browse All Lessons
MessageName: browse_lessons_001
MessageType: suggested_reply
MessageStyle: button
Icon: 📚
UserInput: Browse all lessons
Action: open_lesson_library
MessageContent: Opening your full lesson library...
MessageConditions: is_member

## View Complete Score
MessageName: view_complete_score_001
MessageType: suggested_reply
MessageStyle: button
Icon: 📊
UserInput: View my complete score
MessageContent: Here's your complete score breakdown: {score}%. Areas to focus on: {missed_items}
MessageConditions: is_member && quiz_taken

## Retake Quiz
MessageName: retake_quiz_001
MessageType: suggested_reply
MessageStyle: button
Icon: 🔄
UserInput: Retake quiz
Action: open_quiz
MessageContent: Great! This attempt will be timestamped so you can track your progress over time.
MessageConditions: is_member

## View Other Quizzes
MessageName: other_quizzes_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 📝
UserInput: See other quizzes
Action: open_quiz_library
MessageContent: Opening all available quizzes...
MessageConditions: is_member

## Continue Learning
MessageName: continue_learning_001
MessageType: suggested_reply
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

---

# Offer Messages
Guests (completed quiz, not purchased) → See quiz results → Free preview lesson → Upgrade offer

## Quiz Complete Welcome
MessageName: quiz_complete_welcome_001
MessageType: auto
MessageContent: Congrats, {name}! 🎉 Your quiz score: {score}%. Let me show you exactly where you need to focus.
MessageConditions: has_profile && !purchased && first_message_after_quiz

## Review Correct Items
MessageName: review_correct_001
MessageType: auto
MessageContent: You nailed these topics: {correct_items}. Great job mastering these fundamentals!
MessageConditions: has_profile && !purchased && score > 50

## Free Preview Offer
MessageName: free_preview_offer_001
MessageType: auto
MessageContent: Here's a free lesson on your weakest area. Check it out below. Then upgrade to access the complete learning path.
MessageConditions: has_profile && !purchased && first_message_after_quiz

## Upgrade Call to Action
MessageName: upgrade_cta_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 🚀
UserInput: Unlock full course
Action: show_upgrade_offer
MessageContent: Ready to master all topics? Upgrade now to access the full course.
MessageConditions: has_profile && !purchased

## Thank You for Quiz
MessageName: thank_quiz_001
MessageType: auto
MessageContent: Thanks for taking the quiz! Your results help me personalize your learning experience.
MessageConditions: has_profile && !purchased

## Back Soon Reminder
MessageName: back_soon_001
MessageType: auto
MessageContent: Come back anytime to continue learning! We'll pick up right where you left off.
MessageConditions: has_profile && !purchased && inactive_seconds > 600

---

# Content Messages
Ongoing users - Support, encouragement, engagement

## Welcome Back Content User
MessageName: welcome_back_content_001
MessageType: auto
MessageContent: Welcome back, {name}! Ready to continue your learning journey?
MessageConditions: purchased && returning_user && first_show_session

## Continue Lesson
MessageName: continue_lesson_001
MessageType: suggested_reply
MessageStyle: pill
Icon: ▶️
UserInput: Continue where I left off
Action: resume_last_lesson
MessageContent: Great! Let's pick up where you left off.
MessageConditions: purchased && has_incomplete_lesson

## Progress Encouragement
MessageName: progress_encouragement_001
MessageType: auto
MessageContent: Great progress, {name}! You've completed {lessons_completed} lessons. Keep up the excellent work!
MessageConditions: purchased && lessons_completed >= 3 && first_show_session

## Engagement Check
MessageName: engagement_check_001
MessageType: auto
MessageContent: How are you finding the lessons? Any topics you'd like to explore more?
MessageConditions: purchased && session_minutes >= 10

## Need Help
MessageName: need_help_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 🆘
UserInput: I need help
Action: open_support
MessageContent: I'm here to help! What do you need assistance with?
MessageConditions: purchased

## Support Available
MessageName: support_available_001
MessageType: auto
MessageContent: I'm here if you need any help or have questions about the content!
MessageConditions: purchased && inactive_seconds > 180

## Milestone Celebration
MessageName: milestone_celebration_001
MessageType: auto
MessageContent: 🎉 Amazing milestone, {name}! You've completed {lessons_completed} lessons. Your dedication is paying off!
MessageConditions: purchased && lessons_completed >= 10 && first_show_session
