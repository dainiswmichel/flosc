<?php
require_once dirname(__FILE__) . '/../../public_html/wp-load.php';
$user_id = 20198;
$post_ids = get_user_meta($user_id, '_flosc_free_lesson_post_ids', true);
$lesson_nums = get_user_meta($user_id, '_flosc_free_lesson_numbers', true);
$delivered = get_user_meta($user_id, '_flosc_free_lesson_delivered', true);

echo "=== Tina (user 20198) free lesson meta ===\n";
echo "_flosc_free_lesson_post_ids: "; var_export($post_ids); echo "\n";
echo "_flosc_free_lesson_numbers: "; var_export($lesson_nums); echo "\n";
echo "_flosc_free_lesson_delivered: "; var_export($delivered); echo "\n";

echo "\n=== Post existence check ===\n";
if (is_array($post_ids)) {
    foreach ($post_ids as $pid) {
        $p = get_post((int)$pid);
        echo "Post $pid: " . ($p ? $p->post_title . " (status: " . $p->post_status . ")" : "NOT FOUND") . "\n";
    }
} else {
    echo "No post IDs stored\n";
}

echo "\n=== Quiz data ===\n";
$quiz_data = get_user_meta($user_id, '_flosc_last_quiz_data', true);
echo "Has quiz data: " . (is_array($quiz_data) ? "YES, keys: " . implode(", ", array_keys($quiz_data)) : "NO") . "\n";
echo "Quiz score: " . get_user_meta($user_id, '_flosc_last_quiz_score', true) . "\n";
echo "Quiz ID: " . get_user_meta($user_id, '_flosc_last_quiz_id', true) . "\n";

echo "\n=== freeLessons IIFE simulation ===\n";
$user = get_user_by('id', $user_id);
$freeLessons = (function() use ($user) {
    $post_ids = get_user_meta($user->ID, '_flosc_free_lesson_post_ids', true);
    $lesson_numbers = get_user_meta($user->ID, '_flosc_free_lesson_numbers', true) ?: [];
    if (empty($post_ids) || !is_array($post_ids)) return [];
    $lessons = [];
    foreach ($post_ids as $i => $post_id) {
        $post = get_post((int) $post_id);
        if (!$post || $post->post_status !== 'publish') continue;
        $lessons[] = [
            'title' => $post->post_title,
            'content' => substr(strip_tags(apply_filters('the_content', $post->post_content)), 0, 200) . '...',
            'url' => get_permalink($post_id),
            'lesson_number' => isset($lesson_numbers[$i]) ? $lesson_numbers[$i] : null,
        ];
    }
    return $lessons;
})();
echo "freeLessons count: " . count($freeLessons) . "\n";
foreach ($freeLessons as $fl) {
    echo "  - Lesson #{$fl['lesson_number']}: {$fl['title']} → {$fl['url']}\n";
}
