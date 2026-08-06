<?php
/**
 * FLOSC AI Knowledge Base Tab
 * 
 * Manage markdown files that provide context and knowledge to AI assistant:
 * - Lesson catalogs (structured content listings)
 * - Knowledge base articles (FAQs, documentation)
 * - AI instructions and guidelines
 * - Product information and context
 * - Domain-specific terminology and concepts
 * 
 * These files are injected into AI context when content access is enabled
 * (see AI Configuration tab -> Content Access section).
 * 
 * FILE FORMAT: Markdown (.md)
 * LOCATION: ai_configuration_files/
 * USAGE: AI reads these to understand available content, answer questions, and provide context-aware responses
 * 
 * EXAMPLES OF KNOWLEDGE FILES:
 * - lesson_catalog.md: List of all lessons with descriptions, tags, prerequisites
 * - faq.md: Frequently asked questions and answers
 * - pronunciation_guide.md: Phoneme rules, common mistakes, teaching methodology
 * - product_info.md: Product features, pricing, value propositions
 * - terminology.md: Domain-specific terms and definitions
 * 
 * v1.2.9: Added tab header for flow context
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('🧠', 'Knowledge');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];

// AI Knowledge Files Manager
// §2: union uploaded/edited KB files with shipped defaults (uploads wins).
// flosc_config_glob() returns paths in uploads-first order.
$flosc_files = array_values(array_unique(array_map('basename', flosc_config_glob('*.md'))));

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view-state parameter for file editor UI
$flosc_get = wp_unslash($_GET);
$flosc_editing_file = isset($flosc_get['edit']) ? sanitize_file_name($flosc_get['edit']) : '';
$flosc_editing_content = '';
if ($flosc_editing_file) {
    $flosc_filepath = flosc_config_file($flosc_editing_file);
    if (file_exists($flosc_filepath)) {
        $flosc_editing_content = file_get_contents($flosc_filepath);
    }
}
?>

<h2>AI Knowledge & Personality Configuration</h2>
<p>Configure your AI assistant's identity, purpose, and access to knowledge files. Tell the AI who it is, what it knows, and how to help different types of users.</p>

<!-- ============================================ -->
<!-- AI PERSONALITY & IDENTITY -->
<!-- ============================================ -->
<h3>AI Identity & Personality</h3>
<p class="description">Define who your AI is and how it should interact with users. This shapes every conversation.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_personality_name">AI Name</label></th>
        <td>
            <input type="text" id="flow_ai_personality_name" name="flow_ai_personality_name" 
                   value="<?php echo esc_attr($flosc_flow_settings['ai_personality_name'] ?? ($flosc_flow_settings['name'] ?? 'FLOSC')); ?>" 
                   class="regular-text">
            <p class="description">What should users call your AI? (e.g., "LeSAEp Coach", "Pronunciation Buddy")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_personality_role">AI Role</label></th>
        <td>
            <input type="text" id="flow_ai_personality_role" name="flow_ai_personality_role" 
                   value="<?php echo esc_attr($flosc_flow_settings['ai_personality_role'] ?? 'pronunciation coach and learning assistant'); ?>" 
                   class="large-text">
            <p class="description">What is the AI's job? (e.g., "pronunciation coach", "learning guide", "language tutor")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_personality_traits">Personality Traits</label></th>
        <td>
            <textarea id="flow_ai_personality_traits" name="flow_ai_personality_traits" rows="3" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_personality_traits'] ?? 'Encouraging, patient, specific, action-oriented. You celebrate small wins and make learning feel achievable. You break down complex concepts into simple steps.'); 
            ?></textarea>
            <p class="description">How should the AI behave? What's its tone and approach?</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_mission">AI Mission Statement</label></th>
        <td>
            <textarea id="flow_ai_mission" name="flow_ai_mission" rows="4" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_mission'] ?? 'Your mission is to help users improve their pronunciation through personalized guidance, instant feedback, and encouragement. You assess their current level, identify specific areas for improvement, recommend appropriate lessons, and keep them motivated on their learning journey.'); 
            ?></textarea>
            <p class="description">What is the AI here to accomplish? What's its core purpose?</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- KNOWLEDGE ACCESS RULES -->
<!-- ============================================ -->
<hr class="flosc-ai-knowledge-divider">
<h3>Knowledge Access & Context Rules</h3>
<p class="description">Configure what information the AI has access to and when it can use it.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_context_awareness">AI Context Awareness</label></th>
        <td>
            <textarea id="flow_ai_context_awareness" name="flow_ai_context_awareness" rows="5" class="large-text code"><?php 
                echo esc_textarea($flosc_flow_settings['ai_context_awareness'] ?? 'You have access to special knowledge files that contain:
- Complete lesson catalog with detailed descriptions
- FAQ answers specific to our methodology
- Product information and pricing
- Teaching guidelines and best practices

This information is NOT in your training data. Always reference these files when answering questions about our content, approach, or offerings.'); 
            ?></textarea>
            <p class="description">Tell the AI what unique knowledge it has access to that's NOT in its training data.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_freeline_restrictions">Freeline (Visitor) Access Rules</label></th>
        <td>
            <textarea id="flow_ai_freeline_restrictions" name="flow_ai_freeline_restrictions" rows="4" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_freeline_restrictions'] ?? 'For visitors (not logged in):
- You can answer general questions about pronunciation and learning
- You can describe what our program offers (use public knowledge files)
- You can encourage them to take the quiz
- DO NOT reveal specific lesson content or member-only materials
- Guide them toward taking the quiz to get personalized help'); 
            ?></textarea>
            <p class="description">What can the AI share with visitors who haven't taken the quiz or logged in?</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_member_access">Member Access Rules</label></th>
        <td>
            <textarea id="flow_ai_member_access" name="flow_ai_member_access" rows="4" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_member_access'] ?? 'For logged-in members:
- Full access to lesson catalog and recommendations
- Can provide specific lesson previews and summaries
- Can reference member-only knowledge files (marked "Members Only")
- Can discuss their quiz results and personalized learning path
- Can guide them through lessons they have access to'); 
            ?></textarea>
            <p class="description">What additional information can the AI share with logged-in members?</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_boundaries">AI Boundaries & Limitations</label></th>
        <td>
            <textarea id="flow_ai_boundaries" name="flow_ai_boundaries" rows="4" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_boundaries'] ?? 'What you should NOT do:
- Don\'t diagnose medical conditions (speech impediments, etc.)
- Don\'t guarantee specific results or timelines
- Don\'t provide refunds or make purchasing decisions
- Don\'t share other users\' information
- For billing, technical issues, or account problems, direct users to contact support'); 
            ?></textarea>
            <p class="description">What should the AI refuse to do or topics to avoid?</p>
        </td>
    </tr>
    <!-- ============================================ -->
    <!-- TOPIC SCOPE & OFF-TOPIC HANDLING -->
    <!-- ============================================ -->
    <tr>
        <td colspan="2"><hr class="flosc-ai-knowledge-sub-divider"><h3 class="flosc-ai-knowledge-sub-title">Topic Scope & Off-Topic Handling</h3>
        <p class="description flosc-ai-knowledge-sub-copy">Control what your AI discusses and how it handles questions outside your product's scope.</p></td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_topic_scope">Topic Scope</label></th>
        <td>
            <textarea id="flow_ai_topic_scope" name="flow_ai_topic_scope" rows="3" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_topic_scope'] ?? ''); 
            ?></textarea>
            <p class="description">What topics IS your AI allowed to discuss? (e.g., "You are focused on English pronunciation, phonetics, and language learning. Stay within this topic area.")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_off_topic_message">Off-Topic Response</label></th>
        <td>
            <textarea id="flow_ai_off_topic_message" name="flow_ai_off_topic_message" rows="4" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_off_topic_message'] ?? ''); 
            ?></textarea>
            <p class="description">How should the AI respond when users ask off-topic questions? (e.g., "Briefly acknowledge, let them know this isn't your area, suggest an external tool, then steer back to the flow.")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_off_topic_links">External Tool Recommendations</label></th>
        <td>
            <textarea id="flow_ai_off_topic_links" name="flow_ai_off_topic_links" rows="4" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['ai_off_topic_links'] ?? ''); 
            ?></textarea>
            <p class="description">Links to recommend when users need help outside your scope. One per line. (e.g., "For general questions: ChatGPT https://chat.openai.com"). Use affiliate links where possible.</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- KNOWLEDGE FILE MANAGER -->
<!-- ============================================ -->
<hr class="flosc-ai-knowledge-divider">
<h3>Knowledge Files</h3>
<p class="description">Upload markdown files containing lesson catalogs, FAQs, product info, and teaching guidelines. Mark files as "Public" (freeline access) or "Members Only".</p>

<div class="flosc-ai-knowledge-access-note">
    <strong>💡 Access Control:</strong> Public files are available to ALL users (including visitors). Member-only files are only accessible after login. Use this to control what information the AI can share with different user types.
</div>

<!-- ============================================ -->
<!-- UPLOAD NEW KNOWLEDGE FILE -->
<!-- ============================================ -->
<div class="card flosc-ai-knowledge-upload-card">
    <h3>Upload New Knowledge File</h3>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('flosc_ai_knowledge', 'flosc_ai_knowledge_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="orientation_file">Markdown File</label></th>
                <td>
                    <input type="file" name="orientation_file" id="orientation_file" accept=".md" required>
                    <p class="description">Upload a .md file containing lesson catalogs, FAQs, product info, or AI guidelines.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="file_access_level">Access Level</label></th>
                <td>
                    <select name="file_access_level" id="file_access_level">
                        <option value="public">Public (All users including visitors)</option>
                        <option value="members">Members Only (Logged-in users)</option>
                    </select>
                    <p class="description">Who can the AI reference this file for?</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Upload File', 'secondary'); ?>
    </form>
</div>

<!-- ============================================ -->
<!-- EXISTING KNOWLEDGE FILES -->
<!-- ============================================ -->
<div class="card flosc-ai-knowledge-files-card">
    <h3>Existing Knowledge Files (<?php echo count($flosc_files); ?>)</h3>
    <?php if (empty($flosc_files)): ?>
        <p class="flosc-ai-knowledge-empty">No knowledge files yet. Upload your first file above to get started.</p>
        
        <div class="flosc-ai-knowledge-example-box">
            <h4 class="flosc-ai-knowledge-h4-zero">Example: lesson_catalog.md (Public)</h4>
            <p class="description">Public files let visitors know WHAT you offer without revealing the actual lesson content.</p>
            <pre class="flosc-ai-knowledge-pre"># Lesson Catalog

## Phoneme Lessons

### Lesson 1: The "S" Sound
**Tags:** phoneme-5, consonant, sibilant
**Difficulty:** Beginner
**Duration:** 10 minutes
**Description:** Learn to pronounce the "S" sound correctly. Common in words like "sun", "pass", "sister".

### Lesson 2: The "TH" Sound
**Tags:** phoneme-7, consonant, fricative
**Difficulty:** Intermediate
**Duration:** 15 minutes
**Description:** Master the challenging "TH" sound (as in "think" and "this").</pre>
            
            <h4 class="flosc-ai-knowledge-h4-20">Example: lesson_content.md (Members Only)</h4>
            <p class="description">Member-only files contain detailed lesson instructions, exercises, and answers.</p>
            <pre class="flosc-ai-knowledge-pre"># Lesson Content - The "S" Sound (MEMBERS ONLY)

## Step-by-Step Instructions

1. **Tongue Position**: Place tongue tip behind upper teeth, NOT touching
2. **Air Flow**: Create narrow channel for air to pass through
3. **Vocal Cords**: Keep relaxed (voiceless sound)

## Practice Words
- sun, sit, sister, pass, class, miss
- Contrast with "Z": sun/zone, peace/peas, sink/zinc

## Common Mistakes
- Tongue too far forward (whistling "S")
- Tongue too far back (slushy "SH" sound)
- Adding voice (making it sound like "Z")

## Exercise Audio Files
[Links to practice recordings and feedback]</pre>
        </div>
        
    <?php else: ?>
        <table class="widefat flosc-ai-knowledge-table">
            <thead>
                <tr>
                    <th class="flosc-ai-col-file">Filename</th>
                    <th class="flosc-ai-col-access">Access Level</th>
                    <th class="flosc-ai-col-size">Size</th>
                    <th class="flosc-ai-col-modified">Last Modified</th>
                    <th class="flosc-ai-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($flosc_files as $flosc_file): ?>
                    <?php
                    $flosc_filepath = function_exists('flosc_config_file') ? flosc_config_file($flosc_file) : '';
                    $flosc_size = ('' !== $flosc_filepath && file_exists($flosc_filepath)) ? filesize($flosc_filepath) : 0;
                    $flosc_modified = ('' !== $flosc_filepath && file_exists($flosc_filepath)) ? filemtime($flosc_filepath) : 0;
                    // Get access level from option (default to public for existing files)
                    $flosc_access_level = $flosc_flow_settings['knowledge_access_' . md5($flosc_file)] ?? 'public';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($flosc_file); ?></strong>
                            <?php if ($flosc_editing_file === $flosc_file): ?>
                                <span class="flosc-ai-editing-flag">← Editing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($flosc_access_level === 'members'): ?>
                                <span class="flosc-ai-badge flosc-ai-badge-members">
                                    🔒 MEMBERS ONLY
                                </span>
                            <?php else: ?>
                                <span class="flosc-ai-badge flosc-ai-badge-public">
                                    🌐 PUBLIC
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( size_format($flosc_size) ); ?></td>
                        <td><?php echo esc_html( human_time_diff($flosc_modified, current_time('timestamp')) . ' ago' ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&edit=' . urlencode($flosc_file)) ); ?>" class="button button-small">
                                <?php echo esc_html( $flosc_editing_file === $flosc_file ? 'Editing...' : 'Edit' ); ?>
                            </a>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&toggle_access=' . urlencode($flosc_file)) ); ?>" 
                               class="button button-small">
                                Toggle Access
                            </a>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&delete=' . urlencode($flosc_file)) ); ?>" 
                               class="button button-small" 
                                         data-confirm-message="Delete <?php echo esc_attr($flosc_file); ?>? This cannot be undone."
                               >
                                <span class="flosc-ai-delete-label">Delete</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- FILE EDITOR (if editing) -->
<!-- ============================================ -->
<?php if ($flosc_editing_file): ?>
    <div class="card flosc-ai-knowledge-editor-card">
        <h3>Edit: <?php echo esc_html($flosc_editing_file); ?></h3>
        <form method="post">
            <?php wp_nonce_field('flosc_ai_knowledge_edit', 'flosc_ai_knowledge_edit_nonce'); ?>
            <input type="hidden" name="editing_file" value="<?php echo esc_attr($flosc_editing_file); ?>">
            
            <textarea name="file_content" rows="25" class="large-text code flosc-ai-editor-textarea"><?php echo esc_textarea($flosc_editing_content); ?></textarea>
            
            <p class="description">Markdown formatting supported. Use this editor to update knowledge file content.</p>
            
            <div class="flosc-ai-editor-actions">
                <?php submit_button('Save Changes', 'primary', 'save_knowledge_file', false); ?>
                <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-settings&tab=ai-knowledge') ); ?>" class="button flosc-ai-editor-cancel">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- KNOWLEDGE FILE TEMPLATES & EXAMPLES -->
<!-- ============================================ -->
<hr class="flosc-ai-knowledge-divider">
<h3>Knowledge File Templates & Best Practices</h3>
<p class="description">Copy these templates as starting points for your knowledge files.</p>

<div class="flosc-ai-template-box flosc-ai-template-box--catalog">
    <h4 class="flosc-ai-knowledge-h4-zero">📚 Template: lesson_catalog.md</h4>
    <p><strong>Purpose:</strong> Provide AI with structured information about available lessons</p>
    <button type="button" class="button button-small" data-flosc-action="copy-element-text" data-source-id="template-lesson-catalog" data-alert-message="Template copied to clipboard!">Copy Template</button>
    <pre id="template-lesson-catalog" class="flosc-ai-template-pre"># Lesson Catalog

This file helps the AI understand what lessons are available and recommend appropriate content to users.

## Phoneme Lessons

### Lesson: The "S" Sound
- **ID:** phoneme-5
- **Tags:** consonant, sibilant, beginner
- **Duration:** 10 minutes
- **Free:** Yes
- **Prerequisites:** None
- **Description:** Learn to pronounce the "S" sound correctly. Common in words like "sun", "pass", "sister".

### Lesson: The "TH" Sound  
- **ID:** phoneme-7
- **Tags:** consonant, fricative, intermediate
- **Duration:** 15 minutes
- **Free:** No (Premium)
- **Prerequisites:** Basic consonants
- **Description:** Master the challenging "TH" sound. Covers both voiced (this) and voiceless (think) variants.

## Add more lessons following this format...</pre>
</div>

<div class="flosc-ai-template-box flosc-ai-template-box--faq">
    <h4 class="flosc-ai-knowledge-h4-zero">❓ Template: faq.md</h4>
    <p><strong>Purpose:</strong> Common questions and answers the AI can reference</p>
    <button type="button" class="button button-small" data-flosc-action="copy-element-text" data-source-id="template-faq" data-alert-message="Template copied to clipboard!">Copy Template</button>
    <pre id="template-faq" class="flosc-ai-template-pre"># Frequently Asked Questions

## General Questions

**Q: How long does it take to complete the program?**
A: Most students complete the core program in 4-6 weeks with 15-20 minutes of daily practice. However, you can go at your own pace.

**Q: Do I need any special equipment?**
A: No special equipment needed! Just a computer or smartphone with a microphone for the pronunciation exercises.

**Q: Is there a money-back guarantee?**
A: Yes! We offer a 30-day money-back guarantee. If you're not satisfied, contact support for a full refund.

## Technical Questions

**Q: Which browsers are supported?**
A: We support Chrome, Firefox, Safari, and Edge (latest versions). Chrome is recommended for the best experience.

**Q: Can I use this on my phone?**
A: Yes! Our platform is fully mobile-responsive and works on iOS and Android devices.

## Add more FAQs following this format...</pre>
</div>

<div class="flosc-ai-template-box flosc-ai-template-box--methodology">
    <h4 class="flosc-ai-knowledge-h4-zero">🎯 Template: teaching_methodology.md</h4>
    <p><strong>Purpose:</strong> Guide AI on teaching approach and pedagogical principles</p>
    <button type="button" class="button button-small" data-flosc-action="copy-element-text" data-source-id="template-methodology" data-alert-message="Template copied to clipboard!">Copy Template</button>
    <pre id="template-methodology" class="flosc-ai-template-pre"># Teaching Methodology & AI Guidelines

## Our Teaching Philosophy

We use a **scaffolded learning approach** that builds from simple to complex:
1. Introduce concept with clear examples
2. Provide guided practice with feedback
3. Encourage independent application
4. Reinforce through spaced repetition

## How to Respond to Common Situations

### When a student struggles:
- Be encouraging and patient
- Break down the concept into smaller steps
- Provide additional examples
- Suggest the relevant lesson from our catalog
- Never make them feel inadequate

### When a student excels:
- Celebrate their success specifically
- Challenge them with more advanced concepts
- Recommend next-level content
- Encourage them to help others

### When a student asks off-topic questions:
- Acknowledge the question politely
- Redirect to on-topic content when possible
- If completely off-topic, suggest they contact support

## Pronunciation Coaching Tips

- Always provide the phonetic transcription
- Give physical cues (tongue position, lip shape)
- Use minimal pairs (words that differ by one sound)
- Encourage repetition and recording
- Provide immediate, specific feedback</pre>
</div>

<div class="flosc-ai-template-box flosc-ai-template-box--product">
    <h4 class="flosc-ai-knowledge-h4-zero">💼 Template: product_info.md</h4>
    <p><strong>Purpose:</strong> Product details, pricing, features for sales conversations</p>
    <button type="button" class="button button-small" data-flosc-action="copy-element-text" data-source-id="template-product" data-alert-message="Template copied to clipboard!">Copy Template</button>
    <pre id="template-product" class="flosc-ai-template-pre"># Product Information

## What We Offer

**Free Tier:**
- Quiz assessment
- 1 personalized lesson based on quiz results
- Basic pronunciation feedback
- Progress tracking

**Premium Tier ($49/month or $399/year):**
- Complete access to all 50+ lessons
- Advanced AI pronunciation analysis
- Personalized learning path
- Certificate of completion
- Priority support
- Lifetime access to new content

## Key Features

### AI-Powered Pronunciation Analysis
Our advanced AI listens to your pronunciation and provides instant, specific feedback on:
- Individual phoneme accuracy
- Intonation patterns
- Speaking pace
- Common mistakes

### Personalized Learning Paths
Based on your quiz results and progress, we create a custom curriculum that:
- Focuses on your specific challenges
- Builds on your strengths
- Adapts as you improve
- Keeps you motivated with achievable goals

### Spaced Repetition System
Our algorithm ensures you review concepts at optimal intervals for long-term retention.

## Value Proposition

"Master pronunciation in weeks, not years. Our AI-powered platform gives you instant feedback and personalized lessons that traditional courses can't match - at a fraction of the cost of a private tutor."</pre>
</div>


<!-- Per-flow: personality/knowledge fields are now inside the main settings form above -->
