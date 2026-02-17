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

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];

// AI Knowledge Files Manager
$orientation_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
$files = [];
if (is_dir($orientation_dir)) {
    $scan = scandir($orientation_dir);
    foreach ($scan as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'md') {
            $files[] = $file;
        }
    }
}

$editing_file = isset($_GET['edit']) ? sanitize_file_name($_GET['edit']) : '';
$editing_content = '';
if ($editing_file) {
    $filepath = $orientation_dir . $editing_file;
    if (file_exists($filepath)) {
        $editing_content = file_get_contents($filepath);
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
                   value="<?php echo esc_attr($flow_settings['ai_personality_name'] ?? ($flow_settings['name'] ?? 'FLOSC')); ?>" 
                   class="regular-text">
            <p class="description">What should users call your AI? (e.g., "LeSAEp Coach", "Pronunciation Buddy")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_personality_role">AI Role</label></th>
        <td>
            <input type="text" id="flow_ai_personality_role" name="flow_ai_personality_role" 
                   value="<?php echo esc_attr($flow_settings['ai_personality_role'] ?? 'pronunciation coach and learning assistant'); ?>" 
                   class="large-text">
            <p class="description">What is the AI's job? (e.g., "pronunciation coach", "learning guide", "language tutor")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_personality_traits">Personality Traits</label></th>
        <td>
            <textarea id="flow_ai_personality_traits" name="flow_ai_personality_traits" rows="3" class="large-text"><?php 
                echo esc_textarea($flow_settings['ai_personality_traits'] ?? 'Encouraging, patient, specific, action-oriented. You celebrate small wins and make learning feel achievable. You break down complex concepts into simple steps.'); 
            ?></textarea>
            <p class="description">How should the AI behave? What's its tone and approach?</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_ai_mission">AI Mission Statement</label></th>
        <td>
            <textarea id="flow_ai_mission" name="flow_ai_mission" rows="4" class="large-text"><?php 
                echo esc_textarea($flow_settings['ai_mission'] ?? 'Your mission is to help users improve their pronunciation through personalized guidance, instant feedback, and encouragement. You assess their current level, identify specific areas for improvement, recommend appropriate lessons, and keep them motivated on their learning journey.'); 
            ?></textarea>
            <p class="description">What is the AI here to accomplish? What's its core purpose?</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- KNOWLEDGE ACCESS RULES -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>Knowledge Access & Context Rules</h3>
<p class="description">Configure what information the AI has access to and when it can use it.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_ai_context_awareness">AI Context Awareness</label></th>
        <td>
            <textarea id="flow_ai_context_awareness" name="flow_ai_context_awareness" rows="5" class="large-text code"><?php 
                echo esc_textarea($flow_settings['ai_context_awareness'] ?? 'You have access to special knowledge files that contain:
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
                echo esc_textarea($flow_settings['ai_freeline_restrictions'] ?? 'For visitors (not logged in):
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
                echo esc_textarea($flow_settings['ai_member_access'] ?? 'For logged-in members:
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
                echo esc_textarea($flow_settings['ai_boundaries'] ?? 'What you should NOT do:
- Don\'t diagnose medical conditions (speech impediments, etc.)
- Don\'t guarantee specific results or timelines
- Don\'t provide refunds or make purchasing decisions
- Don\'t share other users\' information
- For billing, technical issues, or account problems, direct users to contact support'); 
            ?></textarea>
            <p class="description">What should the AI refuse to do or topics to avoid?</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- KNOWLEDGE FILE MANAGER -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>Knowledge Files</h3>
<p class="description">Upload markdown files containing lesson catalogs, FAQs, product info, and teaching guidelines. Mark files as "Public" (freeline access) or "Members Only".</p>

<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
    <strong>💡 Access Control:</strong> Public files are available to ALL users (including visitors). Member-only files are only accessible after login. Use this to control what information the AI can share with different user types.
</div>

<!-- ============================================ -->
<!-- UPLOAD NEW KNOWLEDGE FILE -->
<!-- ============================================ -->
<div class="card" style="max-width: 800px; margin-bottom: 20px;">
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
<div class="card" style="max-width: 100%;">
    <h3>Existing Knowledge Files (<?php echo count($files); ?>)</h3>
    <?php if (empty($files)): ?>
        <p style="color: #667; font-style: italic;">No knowledge files yet. Upload your first file above to get started.</p>
        
        <div style="background: #f9f9f9; padding: 15px; margin-top: 15px; border-radius: 4px;">
            <h4 style="margin-top: 0;">Example: lesson_catalog.md (Public)</h4>
            <p class="description">Public files let visitors know WHAT you offer without revealing the actual lesson content.</p>
            <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 12px;"># Lesson Catalog

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
            
            <h4 style="margin-top: 20px;">Example: lesson_content.md (Members Only)</h4>
            <p class="description">Member-only files contain detailed lesson instructions, exercises, and answers.</p>
            <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 12px;"># Lesson Content - The "S" Sound (MEMBERS ONLY)

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
        <table class="widefat" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th style="width: 30%;">Filename</th>
                    <th style="width: 15%;">Access Level</th>
                    <th style="width: 10%;">Size</th>
                    <th style="width: 15%;">Last Modified</th>
                    <th style="width: 30%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <?php
                    $filepath = $orientation_dir . $file;
                    $size = filesize($filepath);
                    $modified = filemtime($filepath);
                    // Get access level from option (default to public for existing files)
                    $access_level = $flow_settings['knowledge_access_' . md5($file)] ?? 'public';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($file); ?></strong>
                            <?php if ($editing_file === $file): ?>
                                <span style="color: #2196f3; margin-left: 8px;">← Editing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($access_level === 'members'): ?>
                                <span style="background: #8b5cf6; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                    🔒 MEMBERS ONLY
                                </span>
                            <?php else: ?>
                                <span style="background: #10b981; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                    🌐 PUBLIC
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo size_format($size); ?></td>
                        <td><?php echo human_time_diff($modified, current_time('timestamp')) . ' ago'; ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&edit=' . urlencode($file)); ?>" class="button button-small">
                                <?php echo $editing_file === $file ? 'Editing...' : 'Edit'; ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&toggle_access=' . urlencode($file)); ?>" 
                               class="button button-small">
                                Toggle Access
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&delete=' . urlencode($file)); ?>" 
                               class="button button-small" 
                               onclick="return confirm('Delete <?php echo esc_js($file); ?>? This cannot be undone.');"
                               style="color: #d63638;">
                                Delete
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
<?php if ($editing_file): ?>
    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h3>Edit: <?php echo esc_html($editing_file); ?></h3>
        <form method="post">
            <?php wp_nonce_field('flosc_ai_knowledge_edit', 'flosc_ai_knowledge_edit_nonce'); ?>
            <input type="hidden" name="editing_file" value="<?php echo esc_attr($editing_file); ?>">
            
            <textarea name="file_content" rows="25" class="large-text code" style="font-family: monospace; width: 100%; font-size: 13px;"><?php echo esc_textarea($editing_content); ?></textarea>
            
            <p class="description">Markdown formatting supported. Use this editor to update knowledge file content.</p>
            
            <div style="margin-top: 15px;">
                <?php submit_button('Save Changes', 'primary', 'save_knowledge_file', false); ?>
                <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ai-knowledge'); ?>" class="button" style="margin-left: 10px;">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- KNOWLEDGE FILE TEMPLATES & EXAMPLES -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>Knowledge File Templates & Best Practices</h3>
<p class="description">Copy these templates as starting points for your knowledge files.</p>

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #9c27b0;">
    <h4 style="margin-top: 0;">📚 Template: lesson_catalog.md</h4>
    <p><strong>Purpose:</strong> Provide AI with structured information about available lessons</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-lesson-catalog').textContent); alert('Template copied to clipboard!');">Copy Template</button>
    <pre id="template-lesson-catalog" style="background: white; padding: 10px; overflow-x: auto; font-size: 12px; margin-top: 10px;"># Lesson Catalog

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #ff9800;">
    <h4 style="margin-top: 0;">❓ Template: faq.md</h4>
    <p><strong>Purpose:</strong> Common questions and answers the AI can reference</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-faq').textContent); alert('Template copied to clipboard!');">Copy Template</button>
    <pre id="template-faq" style="background: white; padding: 10px; overflow-x: auto; font-size: 12px; margin-top: 10px;"># Frequently Asked Questions

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #4caf50;">
    <h4 style="margin-top: 0;">🎯 Template: teaching_methodology.md</h4>
    <p><strong>Purpose:</strong> Guide AI on teaching approach and pedagogical principles</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-methodology').textContent); alert('Template copied to clipboard!');">Copy Template</button>
    <pre id="template-methodology" style="background: white; padding: 10px; overflow-x: auto; font-size: 12px; margin-top: 10px;"># Teaching Methodology & AI Guidelines

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #e91e63;">
    <h4 style="margin-top: 0;">💼 Template: product_info.md</h4>
    <p><strong>Purpose:</strong> Product details, pricing, features for sales conversations</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-product').textContent); alert('Template copied to clipboard!');">Copy Template</button>
    <pre id="template-product" style="background: white; padding: 10px; overflow-x: auto; font-size: 12px; margin-top: 10px;"># Product Information

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

<!-- ============================================ -->
<!-- BACKEND INTEGRATION NOTES -->
<!-- ============================================ -->
<!--
BACKEND IMPLEMENTATION NOTES:
=============================

AI Knowledge Base configures the AI's personality, mission, and access to information.

The configuration is injected into the AI system prompt to shape its behavior.

PERSONALITY INJECTION:

function flosc_inject_ai_personality($flow_settings) {
    $personality = "# WHO YOU ARE\n\n";
    $personality .= "Name: " . ($flow_settings['ai_personality_name'] ?? 'FLOSC') . "\n";
    $personality .= "Role: " . ($flow_settings['ai_personality_role'] ?? 'pronunciation coach') . "\n\n";
    $personality .= "## Personality Traits\n";
    $personality .= ($flow_settings['ai_personality_traits'] ?? '') . "\n\n";
    $personality .= "## Your Mission\n";
    $personality .= ($flow_settings['ai_mission'] ?? '') . "\n\n";
    
    return $personality;
}

KNOWLEDGE ACCESS CONTROL:

function flosc_inject_knowledge_context($flow_settings, $user_phase) {
    $context = "# YOUR KNOWLEDGE BASE\n\n";
    
    // Context awareness (what makes this knowledge special)
    $context .= ($flow_settings['ai_context_awareness'] ?? '') . "\n\n";
    
    // Access rules based on user phase
    if ($user_phase === 'freeline') {
        $context .= "## CURRENT USER: VISITOR (Not logged in)\n\n";
        $context .= ($flow_settings['ai_freeline_restrictions'] ?? '') . "\n\n";
        
        // Only include PUBLIC knowledge files
        $context .= "## Available Knowledge Files (Public Only):\n\n";
        $files = flosc_get_knowledge_files($flow_settings, 'public');
    } else {
        $context .= "## CURRENT USER: MEMBER (Logged in)\n\n";
        $context .= ($flow_settings['ai_member_access'] ?? '') . "\n\n";
        
        // Include ALL knowledge files (public + members only)
        $context .= "## Available Knowledge Files (Full Access):\n\n";
        $files = flosc_get_knowledge_files($flow_settings, 'all');
    }
    
    // Inject actual file contents
    foreach ($files as $file) {
        $filepath = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $file;
        if (file_exists($filepath)) {
            $access = $flow_settings['knowledge_access_' . md5($file)] ?? 'public';
            $context .= "### " . $file . " [" . strtoupper($access) . "]\n\n";
            $context .= file_get_contents($filepath) . "\n\n";
        }
    }
    
    // Add boundaries
    $context .= "## IMPORTANT BOUNDARIES\n\n";
    $context .= ($flow_settings['ai_boundaries'] ?? '') . "\n\n";
    
    return $context;
}

function flosc_get_knowledge_files($flow_settings, $access_filter = 'all') {
    $dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
    $files = [];
    
    if (!is_dir($dir)) return $files;
    
    $scan = scandir($dir);
    foreach ($scan as $file) {
        if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'md') {
            continue;
        }
        
        $access_level = $flow_settings['knowledge_access_' . md5($file)] ?? 'public';
        
        if ($access_filter === 'all' || $access_filter === $access_level) {
            $files[] = $file;
        }
    }
    
    return $files;
}

COMPLETE SYSTEM PROMPT ASSEMBLY:

add_filter('flosc_ai_system_prompt', function($base_prompt, $user_data) {
    // Get flow settings for the active flow
    $flow_settings = flosc_get_flow_settings($user_data);
    $prompt = $base_prompt; // Base prompt from AI Configuration tab
    
    // Add personality
    $prompt .= "\n\n" . flosc_inject_ai_personality($flow_settings);
    
    // Add knowledge based on user phase
    $user_phase = flosc_get_user_phase($user_data);
    $prompt .= "\n\n" . flosc_inject_knowledge_context($flow_settings, $user_phase);
    
    // Add IVR context if enabled (from AI Configuration tab)
    if ($flow_settings['ai_enable_ivr_context'] ?? true) {
        $prompt .= "\n\n" . flosc_inject_ivr_context($user_phase);
    }
    
    return $prompt;
}, 10, 2);

FILE UPLOAD HANDLER (needs update for access level):

if (isset($_FILES['orientation_file']) && wp_verify_nonce($_POST['flosc_ai_knowledge_nonce'], 'flosc_ai_knowledge')) {
    $file = $_FILES['orientation_file'];
    $access_level = sanitize_text_field($_POST['file_access_level'] ?? 'public');
    
    // Validate
    if ($file['error'] === UPLOAD_ERR_OK && pathinfo($file['name'], PATHINFO_EXTENSION) === 'md') {
        $filename = sanitize_file_name($file['name']);
        $destination = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Store access level in flow settings
            $flow_key = $GLOBALS['flosc_settings_key'] ?? '';
            if ($flow_key) {
                $fs = get_option($flow_key, []);
                $fs['knowledge_access_' . md5($filename)] = $access_level;
                update_option($flow_key, $fs);
            }
            
            wp_redirect(admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&uploaded=1'));
            exit;
        }
    }
}

TOGGLE ACCESS HANDLER:

if (isset($_GET['toggle_access'])) {
    $filename = sanitize_file_name($_GET['toggle_access']);
    $flow_key = $GLOBALS['flosc_settings_key'] ?? '';
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $current = $fs['knowledge_access_' . md5($filename)] ?? 'public';
        $new = $current === 'public' ? 'members' : 'public';
        $fs['knowledge_access_' . md5($filename)] = $new;
        update_option($flow_key, $fs);
    }
    
    wp_redirect(admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&toggled=1'));
    exit;
}
-->

<!-- Per-flow: personality/knowledge fields are now inside the main settings form above -->
