<?php
/**
 * FLOSC Sample Data Creator
 * Creates 10 WordPress posts for testing the funnel
 * 
 * Run this once via: wp eval-file admin/create-sample-data.php
 * Or add a button in admin UI
 * 
 * @since 9.1.8
 */

if (!defined('ABSPATH')) {
    // Allow running from WP-CLI
    if (defined('WP_CLI') && WP_CLI) {
        // Running from CLI is OK
    } else {
        exit('Direct access not allowed');
    }
}

/**
 * Create flosc_sample_data category if it doesn't exist
 */
function flosc_create_sample_category() {
    $cat = get_category_by_slug('flosc_sample_data');
    
    if (!$cat) {
        $cat_id = wp_create_category('flosc_sample_data');
        WP_CLI::success("Created category 'flosc_sample_data' (ID: {$cat_id})");
        return $cat_id;
    }
    
    WP_CLI::line("Category 'flosc_sample_data' already exists (ID: {$cat->term_id})");
    return $cat->term_id;
}

/**
 * Create the 10 sample posts
 */
function flosc_create_sample_posts() {
    
    $cat_id = flosc_create_sample_category();
    
    $number_words = [
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten'
    ];
    
    $created = 0;
    $skipped = 0;
    
    foreach ($number_words as $num => $word) {
        
        // Check if post already exists
        $existing = get_posts([
            'category' => $cat_id,
            'meta_key' => '_flosc_lesson_number',
            'meta_value' => $num,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        if (!empty($existing)) {
            WP_CLI::line("Post {$num} already exists, skipping...");
            $skipped++;
            continue;
        }
        
        // Create the post
        $post_data = [
            'post_title' => "{$num}: Flosc Sample Data Post " . ucfirst($word),
            'post_content' => flosc_generate_post_content($num, $word),
            'post_status' => 'publish',
            'post_category' => [$cat_id],
            'post_type' => 'post',
            'post_author' => 1 // Admin user
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Add custom meta
            update_post_meta($post_id, '_flosc_lesson_number', $num);
            update_post_meta($post_id, '_flosc_access_level', 'member'); // Default: member-only
            
            WP_CLI::success("Created post {$num}: ID {$post_id}");
            $created++;
        } else {
            $error = is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error';
            WP_CLI::error("Failed to create post {$num}: {$error}");
        }
    }
    
    WP_CLI::success("Sample data creation complete! Created: {$created}, Skipped: {$skipped}");
}

/**
 * Generate post content with <!--more--> tag
 */
function flosc_generate_post_content($num, $word) {
    
    $uppercase = strtoupper($word);
    
    $content = "Welcome to lesson {$num}! This lesson is about the number **{$num}** ({$word}).\n\n";
    $content .= "This is a sample post to demonstrate FLOSC's content delivery system.\n\n";
    $content .= "<!--more-->\n\n";
    $content .= "## Full Lesson Content (Member Access)\n\n";
    $content .= "The number **{$num}** is written as **\"{$word}\"** in English.\n\n";
    $content .= "### Pronunciation Guide\n\n";
    $content .= "In Standard American English (SAE), \"{$word}\" is pronounced:\n\n";
    $content .= "- IPA: /{$word}/ (simplified)\n";
    $content .= "- Phonetic: {$uppercase}\n\n";
    $content .= "### Example Sentences\n\n";
    $content .= "1. I have {$word} apple" . ($num > 1 ? 's' : '') . ".\n";
    $content .= "2. Count to {$num}: " . implode(', ', array_map(function($n) use ($number_words) {
        return $number_words[$n] ?? $n;
    }, range(1, $num))) . ".\n";
    $content .= "3. The answer is {$word}.\n\n";
    $content .= "### Practice Exercise\n\n";
    $content .= "Write three sentences using the number {$num}.\n\n";
    $content .= "---\n\n";
    $content .= "*This is FLOSC sample content. Replace with your actual curriculum.*\n";
    
    return $content;
}

// Run if called from WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    flosc_create_sample_posts();
}

// Provide admin UI button (future enhancement)
function flosc_sample_data_admin_ui() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_POST['flosc_create_sample_data']) && check_admin_referer('flosc_sample_data')) {
        flosc_create_sample_posts();
    }
    
    ?>
    <div class="wrap">
        <h1>FLOSC Sample Data</h1>
        <p>Create 10 sample WordPress posts for testing the sales funnel.</p>
        <form method="post">
            <?php wp_nonce_field('flosc_sample_data'); ?>
            <p>
                <button type="submit" name="flosc_create_sample_data" class="button button-primary">
                    Create Sample Posts (1-10)
                </button>
            </p>
        </form>
    </div>
    <?php
}
