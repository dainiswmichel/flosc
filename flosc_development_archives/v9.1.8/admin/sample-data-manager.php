<?php
/**
 * Sample Data Manager Admin Page
 * Allows installation and removal of sample lessons 1-10
 * 
 * @since 9.1.7
 */

if (!defined('ABSPATH')) exit;

// Handle install action
if (isset($_POST['flosc_install_sample_data']) && check_admin_referer('flosc_sample_data_action')) {
    FLOSC_Sample_Data::install_sample_lessons();
    echo '<div class="notice notice-success"><p><strong>Sample data installed!</strong> Check your Posts to see lessons 1-10 in the "FLOSC Default Data" category.</p></div>';
}

// Handle remove action
if (isset($_POST['flosc_remove_sample_data']) && check_admin_referer('flosc_sample_data_action')) {
    FLOSC_Sample_Data::remove_sample_lessons();
    echo '<div class="notice notice-success"><p><strong>Sample data removed!</strong> All seeded lessons have been deleted.</p></div>';
}

// Check if sample data exists
$sample_posts = get_posts([
    'post_type' => 'post',
    'meta_key' => '_flosc_seeded',
    'meta_value' => '1',
    'posts_per_page' => 1,
]);
$has_sample_data = !empty($sample_posts);

?>

<div class="wrap">
    <h1>📚 FLOSC Sample Data Manager</h1>
    
    <div class="card" style="max-width: 800px;">
        <h2>Magnificent Pronunciation Lessons 1-10</h2>
        
        <p>Install or remove the <strong>hilarious, entertaining, and educational</strong> sample lessons for numbers 1-10.</p>
        
        <h3>📖 What You Get:</h3>
        <ul>
            <li>✅ <strong>10 Complete Lessons</strong> - One for each number (1-10)</li>
            <li>✅ <strong>Full IPA Transcriptions</strong> - Real phonetic breakdowns</li>
            <li>✅ <strong>Step-by-step pronunciation guides</strong> - How to make each sound</li>
            <li>✅ <strong>Historical linguistics</strong> - Etymology and language evolution</li>
            <li>✅ <strong>Common mistakes</strong> - And how to fix them!</li>
            <li>✅ <strong>Practice sentences</strong> - Real-world usage</li>
            <li>✅ <strong>Cultural notes</strong> - Why these numbers matter</li>
            <li>✅ <strong>Regional variations</strong> - American, British, Australian, etc.</li>
        </ul>
        
        <h3>🎯 Lesson Titles:</h3>
        <ol>
            <li>🎯 The Lonely Number: Why "ONE" Has Identity Issues</li>
            <li>👯‍♀️ TWO: The Word That Conquered 100+ Languages</li>
            <li>🎭 THREE: The Trickster Sound That Drives Learners Mad</li>
            <li>🚪 FOUR: The Word With A Silent Letter Identity Crisis</li>
            <li>✋ FIVE: The Number That Can't Decide If It's Alive or Dead</li>
            <li>🎪 SIX: The Circus Act That Happens In Your Mouth</li>
            <li>🎰 SEVEN: The Lucky Number With An Unlucky Pronunciation</li>
            <li>🎢 EIGHT: The Rollercoaster Your Tongue Takes</li>
            <li>🌙 NINE: The Shapeshifter That Changed Everything</li>
            <li>🔟 TEN: The Simplest Number With The Weirdest History</li>
        </ol>
        
        <h3>🔒 Access Control Testing:</h3>
        <p>Each lesson has:</p>
        <ul>
            <li><strong>Before <code>&lt;!--more--&gt;</code>:</strong> Engaging teaser (VISITOR-safe)</li>
            <li><strong>After <code>&lt;!--more--&gt;</code>:</strong> Full IPA + pronunciation details (MEMBER-only)</li>
        </ul>
        
        <p><strong>Category:</strong> All lessons are tagged with <code>flosc-default-data</code> for easy filtering.</p>
        
        <hr>
        
        <?php if ($has_sample_data): ?>
            <div class="notice notice-info inline">
                <p><strong>✓ Sample data is currently installed.</strong> You have <?php echo count(get_posts(['post_type' => 'post', 'meta_key' => '_flosc_seeded', 'meta_value' => '1', 'posts_per_page' => -1])); ?> sample lesson(s).</p>
            </div>
            
            <form method="post" onsubmit="return confirm('Remove all sample lessons? This cannot be undone!');">
                <?php wp_nonce_field('flosc_sample_data_action'); ?>
                <button type="submit" name="flosc_remove_sample_data" class="button button-secondary">
                    🗑️ Remove Sample Data
                </button>
            </form>
            
        <?php else: ?>
            <div class="notice notice-warning inline">
                <p><strong>No sample data installed.</strong> Click below to install 10 magnificent lessons!</p>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('flosc_sample_data_action'); ?>
                <button type="submit" name="flosc_install_sample_data" class="button button-primary">
                    📥 Install Sample Data (Lessons 1-10)
                </button>
            </form>
        <?php endif; ?>
        
        <hr>
        
        <h3>🧪 How to Test RAG Access Control:</h3>
        <ol>
            <li><strong>Install sample data</strong> (button above)</li>
            <li><strong>Log out</strong> (become a VISITOR)</li>
            <li><strong>Ask the AI:</strong> "What's the IPA for 'one'?"</li>
            <li><strong>Expected result:</strong> AI should say "Take the quiz first!" (not leak IPA)</li>
            <li><strong>Take the quiz</strong> (become a GUEST)</li>
            <li><strong>Ask again:</strong> "What's the IPA for 'one'?"</li>
            <li><strong>Expected result:</strong> AI should offer pricing, not full content</li>
            <li><strong>Complete quiz</strong> (become a MEMBER)</li>
            <li><strong>Ask again:</strong> "What's the IPA for 'one'?"</li>
            <li><strong>Expected result:</strong> AI should now provide full IPA: /wʌn/</li>
        </ol>
        
        <p><em>All lessons follow the <code>_flosc_lesson_number</code> meta pattern for RAG searching.</em></p>
        
    </div>
</div>

<style>
.card h3 {
    margin-top: 20px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}
.notice.inline {
    padding: 10px;
    margin: 20px 0;
}
</style>
