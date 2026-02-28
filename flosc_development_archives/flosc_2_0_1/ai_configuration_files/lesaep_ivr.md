# LeSAEp IVR Configuration
# Learn Excellent Standard American English Pronunciation
# 44 Sounds of Standard American English

---

# Message Styles

## MessageStyle: pill
Description: Light pronunciation tip bubble
.flosc-style-pill {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
  border-radius: 18px;
  padding: 8px 16px;
  font-size: 14px;
  color: #1e40af;
}

## MessageStyle: card
Description: Pronunciation lesson card
.flosc-style-card {
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  border: 1px solid #93c5fd;
  border-radius: 12px;
  padding: 16px 20px;
  font-size: 15px;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
}

## MessageStyle: feature
Description: Sound category highlight
.flosc-style-feature {
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  color: white;
  border-radius: 16px;
  padding: 20px 24px;
  font-size: 16px;
  box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
}

---

# Freeline Messages

## Welcome to LeSAEp
MessageName: lesaep_welcome
MessageType: auto
MessageStyle: feature
MessageContent: ✨ **Welcome to LeSAEp!** Learn Excellent Standard American English Pronunciation. Master the sounds that make up clear, confident American English speech.

<div style="text-align: center; margin: 16px 0;"><img src="https://dainis.net/wp-content/uploads/2026/02/lesaep-logo-progress-02.png" alt="LeSAEp Badge" style="max-width: 200px; border-radius: 12px;"></div>

MessageConditions: is_visitor

---

## Returning Learner
MessageName: lesaep_return
MessageType: auto
MessageStyle: card
MessageContent: 👋 **Welcome back.** Ready to continue mastering Standard American English? Let's pick up where you left off.
MessageConditions: returning_user && !logged_in

---

## v2.0.1: Visitor AutoPrompt Pills

## What's My User Status
MessageName: user_status_check
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👤
UserInput: What's my user status?
MessageContent: {user_status_response}
MessageConditions: always

---

## What Will I Learn
MessageName: lesaep_learn_what
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎯
UserInput: What will I learn?
MessageContent: 🎤 **You'll learn Excellent Standard American English Pronunciation.** Master the sounds that native speakers use every day. Perfect for non-native speakers, actors, broadcasters, or anyone wanting clearer, more confident speech. After this program, people will ask where you learned such beautiful English.
MessageConditions: always

---

## What Is Unique About LeSAEp
MessageName: lesaep_unique
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ✨
UserInput: What is unique about LeSAEp?
MessageContent: **LeSAEp lessons are the most condensed pronunciation lessons in the world.** Created by Dainis W. Michel (the World's 2nd Best English Teacher), each lesson pinpoints exactly how to produce each of the approximately 45-50 sounds in Standard American English. No conversational filler — precise mouth position, tongue placement, and airflow for every sound. LeSAEp is simultaneously the most condensed, informative, and precise pronunciation course available. Plus, we're building a teacher marketplace where you'll be able to book online and in-person lessons with certified pronunciation coaches.
MessageConditions: always

---

## Who Is Dainis W. Michel
MessageName: lesaep_about_dainis
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎓
UserInput: Who is Dainis W. Michel?
MessageContent: **Dainis W. Michel** is the creator of LeSAEp and proudly calls himself the **World's 2nd Best English Teacher.** He has dedicated his career to making pronunciation instruction precise, condensed, and accessible. His approach: instead of long conversational lessons, pinpoint exactly how each sound is produced — mouth position, tongue placement, airflow — so learners can master Standard American English efficiently.
MessageConditions: always

---

## Take the Quiz
MessageName: lesaep_quiz_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Take the accent assessment quiz
Action: open_quiz
MessageContent: Great choice — let's see where you are. This accent assessment will evaluate your understanding of American English sounds and help us create a personalized learning path just for you.
MessageConditions: always

---

## v2.0.1: Hidden IVR guidance — world's best English teacher
MessageName: best_teacher_response
MessageType: auto
MessageStyle: pill
MessageContent: **Who is the world's best English teacher?** There is plenty of room at the top.
MessageConditions: never
Keywords: world's best english teacher, best english teacher, who is the best, number one teacher

---

## v2.0.1: Guest AutoPrompt Pills

## Show Me My Free Lesson
MessageName: guest_free_lesson
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎁
UserInput: Show me my free lesson
Action: open_free_lesson
MessageContent: Here's your free lesson — let's work on mastering one of the most important sounds in American English. Follow along and practice the mouth positions shown.
MessageConditions: is_guest

---

## Upgrade to Access All Lessons
MessageName: guest_upgrade
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🌟
UserInput: Upgrade to access all lessons
Action: show_offer
MessageContent: Ready to go all-in on your pronunciation? Upgrading gives you access to every lesson covering all the sounds of Standard American English — plus personalized learning paths and progress tracking.
MessageConditions: is_guest

---

## Review My Quiz Score
MessageName: guest_quiz_review
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📊
UserInput: Review my quiz score
Action: show_quiz_results
MessageContent: Let's look at your accent assessment results and see which sounds you're already strong on and which ones need the most attention.
MessageConditions: is_guest && quiz_taken

---

## What's My User Status (Guest)
MessageName: guest_status_check
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👤
UserInput: What's my user status?
MessageContent: {user_status_response}
MessageConditions: is_guest

---

## What Does the Full Course Include
MessageName: guest_full_course
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📚
UserInput: What does the full course include?
MessageContent: The full LeSAEp course covers all the sounds of Standard American English — approximately 45-50 distinct sounds including consonants, vowels, diphthongs, and r-colored vowels. Each lesson pinpoints the exact mouth position, tongue placement, and airflow. You also get progress tracking, personalized learning paths, and access to future features like the teacher marketplace.
MessageConditions: is_guest

---

## v2.0.1: Member AutoPrompt Pills — First Login

## Start My First Lesson
MessageName: member_first_lesson
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ▶️
UserInput: Start my first lesson
Action: start_first_lesson
MessageContent: Let's begin. Your first lesson will introduce you to the sound system of Standard American English and get you producing sounds right away.
MessageConditions: is_member && login_count <= 1

---

## What Does My Membership Include
MessageName: member_whats_included
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📦
UserInput: What does my membership include?
MessageContent: Your LeSAEp membership gives you access to every pronunciation lesson — all the consonants, vowels, diphthongs, and r-colored vowels of Standard American English. You also get progress tracking, the accent assessment quiz, personalized recommendations, and access to future features as they launch.
MessageConditions: is_member && login_count <= 1

---

## How Does the Learning Path Work
MessageName: member_learning_path
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🗺️
UserInput: How does the learning path work?
MessageContent: Your learning path guides you through the sounds of Standard American English in a logical order. Start with the foundational sounds, build up to more challenging ones, and track your progress as you go. The path adapts based on your quiz results and which sounds need the most attention.
MessageConditions: is_member && login_count <= 1

---

## What's My User Status (New Member)
MessageName: member_status_check_new
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👤
UserInput: What's my user status?
MessageContent: {user_status_response}
MessageConditions: is_member && login_count <= 1

---

## Take the Accent Assessment Quiz (New Member)
MessageName: member_quiz_new
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Take the accent assessment quiz
Action: open_quiz
MessageContent: Great idea — taking the quiz first helps us understand your current level and build a learning path tailored to the sounds you need most.
MessageConditions: is_member && login_count <= 1 && !quiz_taken

---

## v2.0.1: Member AutoPrompt Pills — Returning (login count 2-5)

## Continue Where I Left Off
MessageName: member_continue
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ▶️
UserInput: Continue where I left off
Action: resume_last_lesson
MessageContent: Let's pick up right where you stopped. Consistent practice is the key to mastering these sounds.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## Repeat Last Lesson Taken
MessageName: member_repeat_lesson
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔁
UserInput: Repeat last lesson taken
Action: repeat_last_lesson
MessageContent: Repetition builds muscle memory. Let's go through that lesson again — you'll notice sounds you missed the first time.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## Try the Quiz Again
MessageName: member_retake_quiz
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Try the quiz again
Action: open_quiz
MessageContent: Let's see how you've improved. Retaking the quiz shows your progress and highlights which sounds still need work.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## What Sound Should I Work on Next
MessageName: member_next_sound
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎯
UserInput: What sound should I work on next?
MessageContent: Based on your progress so far, let me recommend the next sound to focus on. Each sound builds on what you've already learned.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## Show Me the Sound Library
MessageName: member_sound_library
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📖
UserInput: Show me the sound library
Action: open_lesson_library
MessageContent: Browse all the sounds organized by category — consonants, vowels, diphthongs, and r-colored vowels. Each includes mouth position guidance, audio examples, and practice words.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## v2.0.1: Member AutoPrompt Pills — Active (login count 6+)

## Repeat Last Lesson Taken (Active)
MessageName: member_repeat_active
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔁
UserInput: Repeat last lesson taken
Action: repeat_last_lesson
MessageContent: Good instinct — repetition locks in muscle memory. Let's go through it again.
MessageConditions: is_member && login_count >= 6

---

## Try the Quiz Again (Active)
MessageName: member_quiz_active
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Try the quiz again
Action: open_quiz
MessageContent: Let's measure your progress. You've been putting in the work — the quiz will show how far you've come.
MessageConditions: is_member && login_count >= 6

---

## What's My Progress So Far
MessageName: member_progress
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📊
UserInput: What's my progress so far?
MessageContent: {user_progress_response}
MessageConditions: is_member && login_count >= 6

---

## What Sound Should I Work on Next (Active)
MessageName: member_next_sound_active
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎯
UserInput: What sound should I work on next?
MessageContent: Based on your progress and quiz results, let me point you to the sound that will make the biggest difference right now.
MessageConditions: is_member && login_count >= 6

---

## Review Sounds I've Struggled With
MessageName: member_review_struggles
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔍
UserInput: Review sounds I've struggled with
MessageContent: Let's revisit the sounds that gave you the most trouble. Targeted review of difficult sounds is the fastest path to improvement.
MessageConditions: is_member && login_count >= 6

---

# Login Messages

## Login Prompt
MessageName: lesaep_login_prompt
MessageType: auto
MessageStyle: card
MessageContent: 🔐 **Track Your Progress.** Log in to save your pronunciation journey, access all lessons, and get personalized practice recommendations.
MessageConditions: !logged_in && quiz_taken

---

## Login Success
MessageName: lesaep_login_success
MessageType: auto
MessageStyle: feature
MessageContent: **Welcome back, {name}.** Your pronunciation journey continues. You've completed {lessons_completed} lessons. Ready for the next one?
MessageConditions: logged_in && first_message_after_login

---

# Offer Messages

## LeSAEp Prelaunch Offer
MessageName: lesaep_offer
MessageType: offer
MessageStyle: feature
MessageContent: 🌟 **Become a LeSAEp Learner.** We're in prelaunch — pay whatever you want (minimum $100/year) to get full access to the complete American English pronunciation course. Early supporters shape what we build next.
MessageConditions: !is_member && quiz_taken

---

## Sandbox Purchase Button (Testing)
MessageName: sandbox_purchase_lesaep
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎮
UserInput: Test purchase (sandbox)
Action: sandbox_purchase_lesaep
MessageContent: Opening sandbox purchase for LeSAEp — test the full flow with fake money.
MessageConditions: !is_member

---

# Sale Messages

## Purchase Thank You
MessageName: lesaep_purchase_thanks
MessageType: auto
MessageStyle: feature
MessageContent: **Congratulations, {name}.** You now have full access to all pronunciation lessons, video demonstrations, practice recordings, and assessments. Your journey to excellent American English pronunciation starts now.
MessageConditions: is_member && first_message_after_purchase

---

# Content Messages

## Member Welcome
MessageName: lesaep_member_welcome
MessageType: auto
MessageStyle: feature
MessageContent: **Ready to practice, {name}?** You have access to all pronunciation resources. Choose a sound category to focus on, or continue with your personalized learning path.
MessageConditions: is_member && !first_message_after_purchase

---
