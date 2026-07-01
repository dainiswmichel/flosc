<?php
/**
 * FLOSC AI Configuration Guide
 *
 * Help documentation for FloscAdmins configuring AI for their specific content.
 * This guide explains how FLOSC works with AI providers and how to configure
 * personality, knowledge base, and phase instructions.
 *
 * v1.7.8: Initial documentation system
 * Fix 15: Moved from standalone tab to Documentation > AI Configuration Guide
 */

if (!defined('ABSPATH')) exit;

// Suppress tab header when included from documentation.php
if (empty($GLOBALS['flosc_suppress_tab_header'])) {
    flosc_tab_header('📖', 'AI Guide');
}
?>

<!-- Styles in assets/css/flosc-admin.css (AI Guide section) -->

<h2>How FLOSC Works with AI</h2>
<p class="description">
    Learn how to configure AI to become an intelligent instructor for YOUR content.
    FLOSC bridges the gap between general AI knowledge and your specialized expertise.
</p>

<!-- ============================================ -->
<!-- SECTION 1: THE AI TO AGI BRIDGE CONCEPT -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>How FLOSC Works with AI</h3>

    <p>General AI models like ChatGPT, Claude, and Grok have broad knowledge, but they don't know about <strong>your</strong> specific content:</p>

    <ul>
        <li>Your pronunciation course lessons and phoneme exercises</li>
        <li>Your music theory curriculum and solfeggio drills</li>
        <li>Your programming tutorials and coding challenges</li>
        <li>Your fitness program and workout routines</li>
    </ul>

    <p><strong>FLOSC solves this by loading your expert knowledge into the AI's context.</strong></p>

    <h4>How It Works:</h4>
    <ol>
        <li><strong>You upload your content</strong> — WordPress posts in a configured category, knowledge files, quiz questions</li>
        <li><strong>You configure the AI's personality</strong> — System prompts, role, mission, boundaries</li>
        <li><strong>FLOSC injects your content into AI context</strong> — Every time a user asks a question, AI receives:
            <ul>
                <li>Your system prompt and instructions</li>
                <li>Available lessons from your WordPress category</li>
                <li>User's current progress (quiz scores, lessons completed)</li>
                <li>Session history (Multipass — what they did in previous sessions)</li>
            </ul>
        </li>
        <li><strong>AI becomes YOUR intelligent instructor</strong> — Answers questions using YOUR content, guides users through YOUR curriculum</li>
    </ol>

    <div class="flosc-success">
        <strong>✓ Result:</strong> AI that knows your content, remembers user progress, and delivers personalized instruction for your specific domain.
    </div>
</div>

<!-- ============================================ -->
<!-- SECTION 2: WHAT AI NEEDS TO KNOW -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>🧠 What AI Needs to Know</h3>

    <p>For AI to effectively guide users through your content, it needs to understand:</p>

    <h4>1. WHO the AI Is (Identity & Personality)</h4>
    <p>Configure in: <strong>AI Configuration tab → Base System Prompt</strong></p>
    <ul>
        <li><strong>Name:</strong> What should users call the AI? ("Coach Sarah", "Professor Theory", "Your Fitness Guide")</li>
        <li><strong>Role:</strong> What is the AI's function? ("pronunciation coach", "music theory instructor", "programming tutor")</li>
        <li><strong>Personality traits:</strong> "encouraging and patient", "technical and precise", "motivating and energetic"</li>
        <li><strong>Mission:</strong> What is the AI helping users achieve?</li>
    </ul>

    <div class="flosc-code-example">
Example Base System Prompt:

You are Coach Sarah, an expert pronunciation coach specializing in American English.
Your mission is to help non-native speakers master challenging phonemes through
personalized practice and encouragement.

Personality: Patient, encouraging, detail-oriented
Approach: Break down complex sounds into manageable steps
Style: Use specific examples, provide immediate feedback, celebrate progress
    </div>

    <h4>2. WHAT Content Exists (Your Knowledge Base)</h4>
    <p>Configure in: <strong>Flow Settings → Content → WordPress Category</strong></p>
    <p>FLOSC automatically tells the AI about:</p>
    <ul>
        <li><strong>All lessons in your configured WordPress category</strong> — Titles, excerpts, tags</li>
        <li><strong>Quiz questions and scoring logic</strong> — So AI can discuss quiz results intelligently</li>
        <li><strong>Offers and pricing</strong> — AI can explain what's included, when to upgrade</li>
        <li><strong>Knowledge base files</strong> — Upload markdown files with reference material</li>
    </ul>

    <div class="flosc-tip">
        <strong>💡 Tip:</strong> Keep your WordPress category focused. If you have 100 lessons, consider using subcategories or tags so AI can reference relevant content more efficiently.
    </div>

    <h4>3. WHERE the User Is (Context Variables)</h4>
    <p>FLOSC automatically injects user state into every AI request:</p>

    <dl class="flosc-variable-list">
        <dt>{user_name}</dt>
        <dd>User's display name (or "Visitor" if not logged in)</dd>

        <dt>{phase}</dt>
        <dd>Current FLOSC phase: freeline, login, offer, sale, content</dd>

        <dt>{state}</dt>
        <dd>User status: visitor, guest, member</dd>

        <dt>{quiz_taken}</dt>
        <dd>true/false — Has user taken the quiz?</dd>

        <dt>{score}</dt>
        <dd>Quiz score percentage (e.g., 85)</dd>

        <dt>{purchased}</dt>
        <dd>true/false — Has user purchased?</dd>

        <dt>{access_level}</dt>
        <dd>basic, pro, premium — User's access tier</dd>

        <dt>{completed_quizzes}</dt>
        <dd>Array of completed quiz IDs</dd>

        <dt>{lessons_viewed}</dt>
        <dd>Array of lesson IDs user has viewed</dd>
    </dl>

    <h4>4. WHAT User Did Before (Multipass Session History)</h4>
    <p>AI receives summary of previous sessions:</p>
    <ul>
        <li>What quizzes they took and how they scored</li>
        <li>What lessons they viewed</li>
        <li>What questions they asked</li>
        <li>What challenges they mentioned</li>
    </ul>

    <div class="flosc-success">
        <strong>✓ Multipass means AI remembers:</strong> "Last time you struggled with the /θ/ sound. Have you practiced the exercises from Lesson 5?"
    </div>
</div>

<!-- ============================================ -->
<!-- SECTION 3: SYSTEM PROMPT TEMPLATES -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>📝 System Prompt Templates</h3>

    <p>Copy/paste these templates and customize for your domain:</p>

    <!-- Template 1: Pronunciation Coach -->
    <div class="flosc-template-card">
        <h5>Pronunciation Coach (LeSAEP-style)</h5>
        <div class="template-meta">Domain: Language learning, phonetics, accent reduction</div>
        <div class="template-prompt">
You are an expert pronunciation coach specializing in American English phonemes.
Your mission is to help non-native speakers master challenging sounds through
personalized practice and encouragement.

Personality: Patient, encouraging, detail-oriented
Approach: Break down complex sounds into manageable steps, use minimal pairs,
provide tongue/mouth position guidance
Style: Use IPA notation when helpful, celebrate small wins, relate sounds to
familiar words from the user's native language when possible

Remember: Each user has taken a quiz that reveals which phonemes they struggle with.
Tailor your recommendations to their specific challenges.
        </div>
    </div>

    <!-- Template 2: Music Theory Instructor -->
    <div class="flosc-template-card">
        <h5>Music Theory Instructor (Solfeggio)</h5>
        <div class="template-meta">Domain: Music education, ear training, sight-singing</div>
        <div class="template-prompt">
You are a music theory instructor specializing in solfeggio and ear training.
Your mission is to help students develop perfect pitch recognition and sight-singing
skills through systematic practice.

Personality: Enthusiastic, precise, musically minded
Approach: Build from simple intervals to complex melodies, use movable-do solfeggio,
connect theory to practical singing/playing
Style: Use musical notation examples, provide audio references when available,
encourage daily practice routines

Remember: Track each student's progress through interval recognition and melody
dictation exercises. Adjust difficulty based on their quiz scores.
        </div>
    </div>

    <!-- Template 3: Programming Tutor -->
    <div class="flosc-template-card">
        <h5>Programming Tutor</h5>
        <div class="template-meta">Domain: Software development, coding challenges</div>
        <div class="template-prompt">
You are a programming tutor specializing in [Python/JavaScript/etc.].
Your mission is to help beginners learn fundamental programming concepts through
hands-on exercises and clear explanations.

Personality: Patient, technically precise, encouraging
Approach: Start with concepts, provide code examples, explain errors clearly,
encourage experimentation
Style: Use simple analogies for complex concepts, format code properly, break down
problems into smaller steps

Remember: Users have taken a quiz that reveals their current skill level (beginner,
intermediate, advanced). Tailor explanations and examples to their experience.
        </div>
    </div>

    <!-- Template 4: Fitness Coach -->
    <div class="flosc-template-card">
        <h5>Fitness Coach</h5>
        <div class="template-meta">Domain: Exercise, nutrition, wellness</div>
        <div class="template-prompt">
You are a certified fitness coach specializing in [strength training/yoga/HIIT/etc.].
Your mission is to help clients achieve their fitness goals through personalized
workout plans and motivational support.

Personality: Energetic, motivating, results-focused
Approach: Assess current fitness level, set achievable milestones, provide form
feedback, track progress
Style: Use encouraging language, provide video/image references when available,
adapt workouts based on equipment availability and time constraints

Remember: Users have completed a fitness assessment quiz. Use their results to
recommend appropriate exercises and intensity levels. Safety first — never push
beyond their stated limitations.
        </div>
    </div>

    <div class="flosc-tip">
        <strong>💡 Customization Tips:</strong>
        <ul>
            <li>Replace [bracketed placeholders] with your specifics</li>
            <li>Add boundaries: "Never provide medical advice" or "Always cite sources for factual claims"</li>
            <li>Include your teaching philosophy or methodology</li>
            <li>Reference specific frameworks you use (e.g., "Use the 5-3-1 method for strength programming")</li>
        </ul>
    </div>
</div>

<!-- ============================================ -->
<!-- SECTION 4: KNOWLEDGE BASE CONFIGURATION -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>📚 Knowledge Base Configuration</h3>

    <h4>How FLOSC Indexes Your Content</h4>

    <p><strong>1. WordPress Posts = Lessons</strong></p>
    <p>FLOSC treats all posts in your configured category as "lessons":</p>
    <ul>
        <li>Post title becomes lesson title</li>
        <li>Post excerpt becomes lesson preview (shown in lesson list)</li>
        <li>Post content is rendered when user views lesson</li>
        <li>Post tags can map to quiz questions (e.g., tag "phoneme-r" for /r/ sound questions)</li>
    </ul>

    <div class="flosc-code-example">
Example WordPress Post Setup:

Title: Lesson 5: Mastering the /r/ Sound
Category: Pronunciation Lessons (your configured category)
Tags: phoneme-r, consonants, intermediate
Excerpt: Learn tongue positioning and practice minimal pairs for the American R sound.

Content: [Your full lesson text, images, audio embeds, etc.]
    </div>

    <p><strong>2. Knowledge Files (Optional)</strong></p>
    <p>Upload markdown files to <code>wp-content/uploads/flosc/ai_configuration_files/</code>:</p>
    <ul>
        <li><code>lesson_catalog.md</code> — Overview of all lessons (auto-generated)</li>
        <li><code>reference_guide.md</code> — Quick reference material (e.g., IPA chart)</li>
        <li><code>methodology.md</code> — Your teaching approach explained</li>
        <li><code>faqs.md</code> — Common questions and answers</li>
    </ul>

    <p><strong>3. RAG (Retrieval-Augmented Generation)</strong></p>
    <p>When a user asks a question, FLOSC's RAG system:</p>
    <ol>
        <li>Searches your WordPress lessons for relevant content</li>
        <li>Extracts matching passages</li>
        <li>Injects them into AI context</li>
        <li>AI responds using YOUR actual content</li>
    </ol>

    <div class="flosc-success">
        <strong>✓ Result:</strong> User asks "How do I pronounce the /r/ sound?" → AI quotes directly from your Lesson 5 content.
    </div>
</div>

<!-- ============================================ -->
<!-- SECTION 5: TESTING YOUR CONFIGURATION -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>🧪 Testing Your Configuration</h3>

    <h4>Test Console (Coming Soon)</h4>
    <p>The test console will let you simulate different user states and see how AI responds:</p>
    <ul>
        <li>Set user state: visitor, guest, member</li>
        <li>Set quiz score: 0-100%</li>
        <li>Set phase: freeline, login, offer, sale, content</li>
        <li>Enter test message</li>
        <li>See AI response with full system prompt used</li>
    </ul>

    <h4>Manual Testing Steps (Now)</h4>
    <ol>
        <li><strong>Test as visitor:</strong> Open your site in incognito mode, start chat</li>
        <li><strong>Test quiz flow:</strong> Take quiz, verify AI mentions your score in next response</li>
        <li><strong>Test lesson access:</strong> Purchase, verify "Browse all lessons" appears, click lesson</li>
        <li><strong>Test multipass:</strong> Have a conversation, leave, come back → AI should reference previous session</li>
    </ol>

    <div class="flosc-tip">
        <strong>💡 Debugging Tip:</strong> Check browser console for <code>[FLOSC]</code> logs. Enable debug mode in Flow Settings → Advanced → Debug Logging.
    </div>
</div>

<!-- ============================================ -->
<!-- SECTION 6: PHASE-SPECIFIC BEHAVIOR -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>🎯 Phase-Specific Behavior</h3>

    <p>AI behavior changes based on FLOSC phase:</p>

    <h4>Freeline Phase (Visitor)</h4>
    <ul>
        <li>Goal: Encourage quiz participation</li>
        <li>AI should: Build rapport, explain quiz value, answer pre-quiz questions</li>
        <li>Don't: Reveal premium content, give away answers</li>
    </ul>

    <h4>Login Phase (Post-Quiz)</h4>
    <ul>
        <li>Goal: Deliver free lesson, build trust</li>
        <li>AI should: Discuss quiz results, recommend free lesson, provide value</li>
        <li>Don't: Hard-sell immediately, pressure user</li>
    </ul>

    <h4>Offer Phase (Sales)</h4>
    <ul>
        <li>Goal: Present personalized offer</li>
        <li>AI should: Address objections, explain value specific to quiz results, answer pricing questions</li>
        <li>Don't: Be pushy, make false claims</li>
    </ul>

    <h4>Content Phase (Post-Purchase)</h4>
    <ul>
        <li>Goal: Support learning journey</li>
        <li>AI should: Answer content questions, recommend next lessons, celebrate progress, provide encouragement</li>
        <li>Don't: Upsell (they already purchased), restrict access</li>
    </ul>

    <p>Configure phase-specific prompts in: <strong>AI Configuration tab → Phase-Specific Instructions</strong></p>
</div>

<!-- ============================================ -->
<!-- SECTION 7: BEST PRACTICES -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>✅ Best Practices</h3>

    <h4>System Prompt Writing</h4>
    <ul>
        <li><strong>Be specific about role and mission</strong> — "You are a pronunciation coach" not "You are helpful"</li>
        <li><strong>Define personality traits</strong> — "patient and encouraging" vs. "direct and efficient"</li>
        <li><strong>Set boundaries</strong> — "Never provide medical advice", "Always cite lesson numbers when referencing content"</li>
        <li><strong>Explain your methodology</strong> — How do you teach? What's your approach?</li>
    </ul>

    <h4>Content Organization</h4>
    <ul>
        <li><strong>Use clear lesson titles</strong> — "Lesson 5: The /r/ Sound" not "Chapter Five"</li>
        <li><strong>Write good excerpts</strong> — 1-2 sentences that preview the lesson content</li>
        <li><strong>Tag lessons consistently</strong> — Use tags to map lessons to quiz questions</li>
        <li><strong>Keep category focused</strong> — Don't mix blog posts with lessons</li>
    </ul>

    <h4>Quiz Design</h4>
    <ul>
        <li><strong>Quiz reveals user needs</strong> — AI uses results to personalize recommendations</li>
        <li><strong>Tag questions</strong> — Match quiz question tags to lesson tags</li>
        <li><strong>Provide feedback</strong> — Let AI explain why answers were right/wrong</li>
    </ul>

    <h4>Testing</h4>
    <ul>
        <li><strong>Test all phases</strong> — Don't just test as member, test visitor → quiz → offer → purchase flow</li>
        <li><strong>Test edge cases</strong> — What if user scores 0%? 100%? What if they ask off-topic questions?</li>
        <li><strong>Monitor conversations</strong> — Check actual user conversations in session logs</li>
    </ul>
</div>

<!-- ============================================ -->
<!-- SECTION 8: VARIABLES REFERENCE -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>📋 Available Variables Reference</h3>

    <p>Use these variables in your system prompts, IVR messages, and phase-specific instructions:</p>

    <h4>User Variables</h4>
    <dl class="flosc-variable-list">
        <dt>{user_id}</dt>
        <dd>WordPress user ID (0 for visitors)</dd>

        <dt>{user_name}</dt>
        <dd>Display name or "Visitor"</dd>

        <dt>{user_email}</dt>
        <dd>Email address (empty for visitors)</dd>

        <dt>{logged_in}</dt>
        <dd>true/false</dd>
    </dl>

    <h4>State Variables</h4>
    <dl class="flosc-variable-list">
        <dt>{state}</dt>
        <dd>visitor | guest | member</dd>

        <dt>{phase}</dt>
        <dd>freeline | login | offer | sale | content</dd>

        <dt>{hasAccess}</dt>
        <dd>true/false — Does user have content access?</dd>

        <dt>{purchased}</dt>
        <dd>true/false — Has user purchased?</dd>

        <dt>{access_level}</dt>
        <dd>basic | pro | premium</dd>
    </dl>

    <h4>Quiz Variables</h4>
    <dl class="flosc-variable-list">
        <dt>{quiz_taken}</dt>
        <dd>true/false</dd>

        <dt>{score}</dt>
        <dd>Percentage (0-100)</dd>

        <dt>{quiz_id}</dt>
        <dd>ID of quiz taken</dd>

        <dt>{completed_quizzes}</dt>
        <dd>Array of quiz IDs</dd>

        <dt>{quiz_answers}</dt>
        <dd>User's answers for current quiz</dd>
    </dl>

    <h4>Content Variables</h4>
    <dl class="flosc-variable-list">
        <dt>{lessons_viewed}</dt>
        <dd>Array of lesson IDs user has viewed</dd>

        <dt>{current_lesson}</dt>
        <dd>ID of lesson currently being viewed (if any)</dd>

        <dt>{lesson_progress}</dt>
        <dd>Percentage of lessons completed</dd>
    </dl>

    <h4>Session Variables</h4>
    <dl class="flosc-variable-list">
        <dt>{session_id}</dt>
        <dd>Current session ID</dd>

        <dt>{message_count}</dt>
        <dd>Number of messages in current session</dd>

        <dt>{first_message}</dt>
        <dd>true/false — Is this user's first message?</dd>

        <dt>{first_message_after_quiz}</dt>
        <dd>true/false — Is this the first message after completing quiz?</dd>
    </dl>
</div>

<!-- ============================================ -->
<!-- SECTION 9: TROUBLESHOOTING -->
<!-- ============================================ -->
<div class="flosc-guide-section">
    <h3>🔧 Troubleshooting</h3>

    <h4>AI doesn't know about my lessons</h4>
    <ul>
        <li>✓ Check: Flow Settings → Content → WordPress Category is set correctly</li>
        <li>✓ Check: Your lessons are published (not draft) and in the correct category</li>
        <li>✓ Check: AI Configuration → Content Access is enabled</li>
    </ul>

    <h4>AI gives generic responses, not personalized</h4>
    <ul>
        <li>✓ Check: Base System Prompt is specific about domain and role</li>
        <li>✓ Check: Phase-Specific Instructions are configured</li>
        <li>✓ Check: AI Response Mode is set to "Enhanced" not "Strict IVR"</li>
    </ul>

    <h4>AI doesn't remember previous sessions (Multipass not working)</h4>
    <ul>
        <li>✓ Check: User is logged in (Multipass requires user ID)</li>
        <li>✓ Check: Session history is being saved (check wp_flosc_sessions table)</li>
        <li>✓ Note: Multipass integration is currently in development</li>
    </ul>

    <h4>AI mentions content that doesn't exist</h4>
    <ul>
        <li>✓ Problem: AI is hallucinating or using outdated knowledge base</li>
        <li>✓ Solution: Set clear boundaries in system prompt: "Only reference lessons that exist in the provided catalog"</li>
        <li>✓ Solution: Use RAG search instead of AI memory for content references</li>
    </ul>
</div>

<!-- ============================================ -->
<!-- FOOTER: NEXT STEPS -->
<!-- ============================================ -->
<div class="flosc-guide-section flosc-guide-section--next-steps">
    <h3>🚀 Next Steps</h3>

    <ol class="flosc-guide-next-steps-list">
        <li><strong>Configure your WordPress category</strong> — Flow Settings → Content → Select category with your lessons</li>
        <li><strong>Write your system prompt</strong> — Use templates above, customize for your domain</li>
        <li><strong>Set up API keys</strong> — AI Configuration → Provider Connection → Add your OpenAI/Anthropic/xAI key</li>
        <li><strong>Configure phase-specific prompts</strong> — AI Configuration → Phase-Specific Instructions</li>
        <li><strong>Test the flow</strong> — Open site in incognito, take quiz, verify AI responses</li>
        <li><strong>Monitor and refine</strong> — Check actual conversations, adjust prompts based on user experience</li>
    </ol>

    <p class="flosc-guide-next-steps-note">
        <strong>Remember:</strong> FLOSC provides the infrastructure — you provide the expertise.
        The AI is only as good as the content and instructions you give it.
    </p>
</div>
