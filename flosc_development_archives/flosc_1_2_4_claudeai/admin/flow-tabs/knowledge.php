<?php
/**
 * Flow Edit Tab: Knowledge
 * 
 * AI personality and proprietary content configuration
 * THIS IS THE PRODUCT ITSELF - the unique knowledge being sold
 */

if (!defined('ABSPATH')) exit;

$knowledge = $flow['knowledge'] ?? [];
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
        <strong>🔑 This is Your Product's Secret Sauce</strong>
        <p style="margin: 8px 0 0 0;">
            AI Knowledge defines what makes this flow unique - the proprietary methodology, content, and expertise 
            that users are paying for. Unlike generic AI, your flow knows specific information that creates real value.
        </p>
    </div>
    
    <h2 class="flosc-section-header">AI Identity & Personality</h2>
    <p class="description">Define who this flow's AI is and how it interacts with users.</p>
    
    <div class="flosc-form-field">
        <label for="knowledge_personality_name">AI Name</label>
        <input type="text" id="knowledge_personality_name" name="knowledge_personality_name" 
               value="<?php echo esc_attr($knowledge['personality_name'] ?? ''); ?>"
               placeholder="<?php echo esc_attr(flosc_global_placeholder('knowledge.personality_name', 'e.g., LeSAEp Coach, Solfeggio Guide')); ?>">
        <p class="description">What should users call this AI? Creates distinct identity per flow.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="knowledge_personality_role">AI Role</label>
        <input type="text" id="knowledge_personality_role" name="knowledge_personality_role" 
               value="<?php echo esc_attr($knowledge['personality_role'] ?? ''); ?>"
               placeholder="<?php echo esc_attr(flosc_global_placeholder('knowledge.personality_role', 'e.g., pronunciation coach, music theory tutor')); ?>">
        <p class="description">What is this AI's job/expertise?</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="knowledge_personality_traits">Personality Traits</label>
        <textarea id="knowledge_personality_traits" name="knowledge_personality_traits" rows="4"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('knowledge.personality_traits')); ?>"><?php echo esc_textarea($knowledge['personality_traits'] ?? ''); ?></textarea>
        <p class="description">How should this AI behave? Tone, approach, communication style.</p>
    </div>
    
    <h2 class="flosc-section-header">Mission & Purpose</h2>
    
    <div class="flosc-form-field">
        <label for="knowledge_mission">AI Mission Statement</label>
        <textarea id="knowledge_mission" name="knowledge_mission" rows="5"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('knowledge.mission')); ?>"><?php echo esc_textarea($knowledge['mission'] ?? ''); ?></textarea>
        <p class="description">What is this AI here to accomplish? Its core purpose and goals.</p>
    </div>
    
    <h2 class="flosc-section-header">Proprietary Knowledge</h2>
    <p class="description">
        This is what makes your product valuable. Tell the AI what unique knowledge it has access to 
        that regular AI doesn't have - your methodology, content catalog, specific expertise.
    </p>
    
    <div class="flosc-form-field">
        <label for="knowledge_context_awareness">AI Context Awareness</label>
        <textarea id="knowledge_context_awareness" name="knowledge_context_awareness" rows="8"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('knowledge.context_awareness')); ?>"><?php echo esc_textarea($knowledge['context_awareness'] ?? ''); ?></textarea>
        <p class="description">
            Tell the AI what unique knowledge files and content it has access to. 
            Example for LeSAEp: "You have access to the complete Standard American English phoneme catalog, 
            common pronunciation mistakes by language background, and our unique visual-phonetic teaching methodology."
        </p>
    </div>
    
    <h2 class="flosc-section-header">Access Control Rules</h2>
    <p class="description">Control what the AI can share based on user status.</p>
    
    <div class="flosc-form-field">
        <label for="knowledge_freeline_restrictions">Freeline (Visitor) Access Rules</label>
        <textarea id="knowledge_freeline_restrictions" name="knowledge_freeline_restrictions" rows="5"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('knowledge.freeline_restrictions')); ?>"><?php echo esc_textarea($knowledge['freeline_restrictions'] ?? ''); ?></textarea>
        <p class="description">What can the AI share with visitors who haven't taken the quiz or logged in?</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="knowledge_member_access">Member Access Rules</label>
        <textarea id="knowledge_member_access" name="knowledge_member_access" rows="5"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('knowledge.member_access')); ?>"><?php echo esc_textarea($knowledge['member_access'] ?? ''); ?></textarea>
        <p class="description">What additional information can paying members access?</p>
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
    
    <hr style="margin: 40px 0;">
    
    <h3>Knowledge Files</h3>
    <p class="description">
        Markdown files in <code>ai_configuration_files/</code> provide additional context to the AI.
        These can include lesson catalogs, FAQs, terminology guides, and product documentation.
    </p>
    <p>
        <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ai-knowledge'); ?>" class="button">
            Manage Knowledge Files →
        </a>
    </p>
    
    <div style="background: #f0f6ff; border: 1px solid #c3dafe; padding: 15px; border-radius: 8px; margin-top: 20px;">
        <strong>💡 Example Use Cases</strong>
        <ul style="margin: 10px 0 0 20px;">
            <li><strong>LeSAEp:</strong> Unique SAE pronunciation methodology, phoneme-specific teaching techniques, accent analysis patterns</li>
            <li><strong>Simplified Solfeggio:</strong> Simplified music theory framework, sight-reading exercises, ear training progression</li>
            <li><strong>FLOSC:</strong> Quiz-to-sale funnel strategies, IVR configuration best practices, conversion optimization</li>
        </ul>
    </div>
</form>
