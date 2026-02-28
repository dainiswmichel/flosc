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
MessageContent: 🎤 **Welcome to LeSAEp!** Learn Excellent Standard American English Pronunciation. Master the 44 sounds that make up clear, confident American English speech.
MessageConditions: is_visitor

---

## Returning Learner
MessageName: lesaep_return
MessageType: auto
MessageStyle: card
MessageContent: 👋 **Welcome back, pronunciation champion!** Ready to continue mastering Standard American English? Let's pick up where you left off.
MessageConditions: returning_user && !logged_in

---

## The 44 Sounds Overview
MessageName: sounds_overview
MessageType: auto
MessageStyle: card
MessageContent: 📚 Standard American English has **44 distinct sounds**: 24 consonants and 20 vowels. Each sound has specific mouth positions, tongue placements, and airflow patterns. Would you like to explore consonants or vowels first?
MessageConditions: is_visitor

---

## Consonant Categories
MessageName: consonant_intro
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔤
UserInput: Tell me about consonant sounds
MessageContent: Great choice! The 24 consonant sounds are organized into categories: **Stops** (p, b, t, d, k, g), **Fricatives** (f, v, θ, ð, s, z, ʃ, ʒ, h), **Affricates** (tʃ, dʒ), **Nasals** (m, n, ŋ), **Liquids** (l, r), and **Glides** (w, j). Which category interests you?
MessageConditions: always

---

## What Will I Learn - LeSAEp
MessageName: lesaep_learn_what
MessageType: suggested_user_autoprompt
MessageStyle: feature
Icon: 🎯
UserInput: What will I learn?
MessageContent: 🎤 **You'll learn Excellent Standard American English Pronunciation!** Master the 44 sounds that native speakers use every day. Perfect for non-native speakers, actors, broadcasters, or anyone wanting clearer, more confident speech. After this program, people will ask where you learned such beautiful English!
MessageConditions: always

---

## Vowel Categories
MessageName: vowel_intro
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🗣️
UserInput: Tell me about vowel sounds
MessageContent: Excellent! The 20 vowel sounds include: **Short vowels** (ɪ, ɛ, æ, ʌ, ʊ, ə), **Long vowels** (iː, eɪ, aɪ, oʊ, uː, ɔː, ɑː), **Diphthongs** (aʊ, ɔɪ), and **R-colored vowels** (ɝ, ɚ, ɑr, ɔr, ɪr). Let's explore how to produce each one perfectly!
MessageConditions: always

---

## Stop Consonants Lesson
MessageName: stop_consonants
MessageType: auto
MessageStyle: card
MessageContent: 🛑 **Stop Consonants** block airflow completely, then release it. Practice pairs: **p/b** (lips), **t/d** (tongue tip behind teeth), **k/g** (back of tongue). The difference? Voicing! Feel your throat vibrate on b, d, g but not on p, t, k.
MessageConditions: lesson_viewed

---

## Fricative Consonants Lesson
MessageName: fricative_consonants
MessageType: auto
MessageStyle: card
MessageContent: 💨 **Fricatives** create friction by narrowing the airflow. Key pairs: **f/v** (teeth on lip), **s/z** (tongue near ridge), **ʃ/ʒ** (like "sh" in "ship" and "zh" in "measure"), **θ/ð** (tongue between teeth - "think" vs "this").
MessageConditions: lesson_viewed && message_count >= 3

---

## The TH Sounds
MessageName: th_sounds
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👅
UserInput: Help me with TH sounds
MessageContent: The TH sounds are unique to English! **θ** (voiceless) as in "think, bath, method" - no vibration. **ð** (voiced) as in "this, brother, smooth" - feel the vibration! Tip: Place tongue between teeth, let air flow around it.
MessageConditions: always

---

## R-Colored Vowels
MessageName: r_colored
MessageType: auto
MessageStyle: card
MessageContent: 🔴 **R-colored vowels** are distinctly American! The tongue curls back slightly: **ɝ** (stressed "bird, learn"), **ɚ** (unstressed "mother, better"), **ɑr** ("car, star"), **ɔr** ("for, more"), **ɪr** ("near, fear"). This "rhotic R" is the signature of American English!
MessageConditions: lesson_viewed && message_count >= 5

---

## Schwa - The Most Common Sound
MessageName: schwa_lesson
MessageType: auto
MessageStyle: feature
MessageContent: ⭐ **The Schwa (ə)** is the most common sound in English! It's the relaxed "uh" in unstressed syllables: "**a**bout, sof**a**, comm**a**". Master the schwa and your English will instantly sound more natural and fluent!
MessageConditions: lesson_viewed && message_count >= 8

---

## Practice Minimal Pairs
MessageName: minimal_pairs
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎯
UserInput: Practice minimal pairs with me
MessageContent: Minimal pairs are words that differ by only one sound - perfect for training your ear and mouth! Try: **ship/sheep**, **bat/bet**, **cut/cat**, **think/sink**, **light/right**. Hear the difference? Now produce the difference!
MessageConditions: always

---

## Quiz Prompt
MessageName: lesaep_quiz_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Test my pronunciation knowledge
Action: open_quiz
MessageContent: Let's test what you've learned! This quiz will check your understanding of American English sounds, mouth positions, and sound distinctions.
MessageConditions: !quiz_taken

---

## Free Lesson Offer
MessageName: lesaep_free_lesson
MessageType: suggested_user_autoprompt
MessageStyle: card
Icon: 🎁
UserInput: Show me a free pronunciation lesson
Action: open_free_lesson
MessageContent: 🎁 **Free Lesson Available!** Master the "R" sound - the signature of American English. Learn the tongue position, practice exercises, and hear examples. This sound alone will transform your accent!
MessageConditions: !is_member

---

# Login Messages

## Login Prompt
MessageName: lesaep_login_prompt
MessageType: auto
MessageStyle: card
MessageContent: 🔐 **Track Your Progress!** Log in to save your pronunciation journey, access all 44 sound lessons, and get personalized practice recommendations based on your native language.
MessageConditions: !logged_in && quiz_taken

---

## Login Success
MessageName: lesaep_login_success
MessageType: auto
MessageStyle: feature
MessageContent: 🎉 **Welcome back, {name}!** Your pronunciation journey continues. You've completed {lessons_completed} lessons. Let's work on the next sound category!
MessageConditions: logged_in && first_message_after_login

---

# Offer Messages

## LeSAEp Prelaunch Offer
MessageName: lesaep_offer
MessageType: offer
MessageStyle: feature
MessageContent: 🌟 **Become a LeSAEp Learner!** We're in prelaunch — pay whatever you want (minimum $100/year) to get full access to the complete 44-sound American English pronunciation course. Early supporters shape what we build next.
MessageConditions: !is_member && quiz_taken

---

## v1.4.0: Sandbox Purchase Button (Testing)
MessageName: sandbox_purchase_lesaep
MessageType: suggested_user_autoprompt
MessageStyle: button
Icon: 🎮
UserInput: Test purchase (sandbox)
Action: sandbox_purchase_lesaep
MessageContent: Opening sandbox purchase for LeSAEp - test the full flow with fake money!
MessageConditions: !is_member

---

## Accent Reduction Benefits
MessageName: accent_benefits
MessageType: auto
MessageStyle: card
MessageContent: 💼 Clear pronunciation opens doors: Better job interviews, confident presentations, clearer phone calls, and being understood the first time. LeSAEp gives you the tools for professional-level American English.
MessageConditions: !is_member && lesson_viewed

---

# Sale Messages

## Purchase Thank You
MessageName: lesaep_purchase_thanks
MessageType: auto
MessageStyle: feature
MessageContent: 🎊 **Congratulations, {name}!** You now have full access to all 44 sound lessons, video demonstrations, practice recordings, and pronunciation assessments. Your journey to excellent American English pronunciation starts now!
MessageConditions: is_member && first_message_after_purchase

---

# Content Messages

## Member Welcome
MessageName: lesaep_member_welcome
MessageType: auto
MessageStyle: feature
MessageContent: 🎤 **Ready to practice, {name}?** You have access to all pronunciation resources. Choose a sound category to focus on, or continue with your personalized learning path.
MessageConditions: is_member && !first_message_after_purchase

---

## Continue Learning
MessageName: lesaep_continue
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ▶️
UserInput: Continue my pronunciation lessons
Action: resume_last_lesson
MessageContent: Let's pick up where you left off! You're making great progress through the American English sound system. Consistent practice is the key to natural pronunciation.
MessageConditions: is_member

---

## Sound Library
MessageName: sound_library
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📖
UserInput: Show me the sound library
Action: open_lesson_library
MessageContent: Browse all 44 sounds organized by category: Consonants (stops, fricatives, affricates, nasals, liquids, glides) and Vowels (short, long, diphthongs, r-colored). Each includes mouth diagrams, audio examples, and practice words.
MessageConditions: is_member

---

## IPA Training
MessageName: ipa_training
MessageType: auto
MessageStyle: card
MessageContent: 🔤 **IPA (International Phonetic Alphabet)** is your secret weapon! Each sound has one symbol. Once you learn IPA, you can pronounce any English word correctly just by reading the dictionary. LeSAEp teaches you practical IPA for American English.
MessageConditions: is_member && lessons_completed >= 5

---

## Daily Practice Tip
MessageName: daily_practice
MessageType: auto
MessageStyle: pill
MessageContent: 💡 **Daily Tip:** Record yourself and compare to native speakers. Your ears adjust faster when you can hear the difference. Even 5 minutes of focused practice daily beats hours of passive listening!
MessageConditions: is_member && returning_user

---
