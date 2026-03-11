<?php
/**
 * FLOSC Free Lesson Manager
 * Handles quiz results and free lesson selection
 * 
 * STATUS: ✅ FULLY FUNCTIONAL
 * 
 * v3.0.0: Quiz-aware lesson delivery via lesson_groups
 * - handle_quiz_completion() reads quiz_id from $quiz_result
 * - find_lesson_post() resolves category from lesson_groups[quiz_id]
 * - Backward compatible: falls back to lessons_category if lesson_groups absent
 * 
 * v1.5.4: Multiple free lessons
 * v9.1.8: Initial implementation
 * 
 * FLOW:
 * 1. User takes quiz "sae_pronunciation": "4,7,9" = 30%
 * 2. System checks lesson_groups for quiz → category mapping
 * 3. Finds category "lesaep" for quiz "sae_pronunciation"
 * 4. Calculates missed items: 1,2,3,5,6,8,10
 * 5. Picks random (e.g., #8)
 * 6. Delivers WordPress post with _flosc_lesson_number = 8 from category "lesaep"
 * 
 * @since 9.1.8
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Free_Lesson_Manager {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into quiz completion
        add_action('flosc_quiz_completed', [$this, 'handle_quiz_completion'], 10, 2);
    }
    
    /**
     * Handle quiz completion and select free lesson(s)
     * 
     * v3.0.0: Now quiz-aware — reads quiz_id from $quiz_result to resolve
     * the correct lesson category via the flow's lesson_groups config.
     * 
     * @param array $quiz_result Quiz results with score, answers, quiz_id
     * @param int   $user_id    User ID
     * @return array|void Selected lesson numbers, or void if no lessons needed
     */
    public function handle_quiz_completion($quiz_result, $user_id) {

        $score   = $quiz_result['score'] ?? 0;
        $quiz_id = $quiz_result['quiz_id'] ?? '';

        // Only offer free lesson if quiz incomplete (<100%)
        if ($score >= 100) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: User {$user_id} scored {$score}% — no free lesson needed");
            return;
        }

        // Get missed lessons
        $missed = $this->get_missed_lessons($quiz_result);

        if (empty($missed)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: No missed lessons found for user {$user_id}");
            return;
        }

        // v1.5.4: Use admin-configured count instead of hardcoded 1
        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
        $member_access = FLOSC_Member_Access::instance();
        $count = $member_access->calculate_free_lesson_count(count($missed));

        // v8.0.0: IPA audio quiz free lesson selection.
        // ranked_worst is sorted worst→best (lowest score = index 0).
        // Each entry has 'score' and 'lessons' (array of lesson numbers; [0] = primary).
        //
        // Logic — walk down score tiers, never give away a unique worst phoneme:
        //   Tier 1 (worst score): if >1 phoneme ties here → pick one at random → done
        //   Tier 1 is single → Tier 2 (2nd worst): if >1 ties here → pick one at random → done
        //   Tier 2 is single → Tier 3 (3rd worst): pick one at random → done
        // The single worst phoneme is the upsell hook — we don't give it away free.

        $ranked_worst = $quiz_result['ranked_worst_lessons'] ?? [];

        if (!empty($ranked_worst) && is_array($ranked_worst)) {
            // Group phonemes into tiers by score
            $tiers = [];
            foreach ($ranked_worst as $entry) {
                $score = $entry['score'] ?? 0;
                $tiers[$score][] = $entry;
            }
            // Sort tiers by score ascending (worst scores first)
            ksort($tiers);
            $tiers = array_values($tiers);

            // Walk tiers: pick from first tier that has >1 phoneme, or 3rd tier at latest
            $pick_from = null;
            for ($i = 0; $i < min(3, count($tiers)); $i++) {
                if (count($tiers[$i]) > 1 || $i === 2) {
                    $pick_from = $tiers[$i];
                    break;
                }
            }
            // Fallback: if fewer than 3 tiers exist, pick from the last available tier
            if ($pick_from === null) {
                $pick_from = end($tiers);
            }

            // Pick one phoneme at random from the chosen tier, take its primary lesson
            $chosen = $pick_from[array_rand($pick_from)];
            $all_lessons = array_map('intval', (array) ($chosen['lessons'] ?? []));
            $selected_lessons = !empty($all_lessons) ? [$all_lessons[0]] : [];
        } else {
            // Non-IPA quiz: original behavior — shuffle missed lessons, pick admin-configured count
            shuffle($missed);
            $selected_lessons = array_slice($missed, 0, $count);
        }

        // v8.0.0: Guaranteed free lesson — always granted to every guest who completes quiz.
        // Admin-configurable via free_lesson_guaranteed setting (defaults to 35).
        // This is the safety net: if the tier-walk produced nothing, the guest still gets a lesson.
        // If the tier-walk worked, the guest gets 2 lessons (guaranteed + personalized).
        $guaranteed = intval(function_exists('flosc_get_setting')
            ? flosc_get_setting('free_lesson_guaranteed', 35)
            : 35);
        if ($guaranteed > 0 && !in_array($guaranteed, $selected_lessons)) {
            array_unshift($selected_lessons, $guaranteed);
        }

        if (empty($selected_lessons)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: No lessons selected for user {$user_id} — tier-walk and guaranteed both empty");
            return;
        }

        update_user_meta($user_id, '_flosc_free_lesson_number', $selected_lessons[0]);
        update_user_meta($user_id, '_flosc_free_lesson_numbers', $selected_lessons);
        update_user_meta($user_id, '_flosc_free_lesson_quiz_id', $quiz_id);
        update_user_meta($user_id, '_flosc_free_lesson_offered', time());

        // Find and grant guest access to each selected lesson
        $granted_posts = [];
        foreach ($selected_lessons as $lesson_num) {
            $post = $this->find_lesson_post($lesson_num, $quiz_id);
            if ($post) {
                $member_access->grant_guest_access($user_id, $post->ID);
                $granted_posts[] = $post->ID;
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: Granted guest access to post {$post->ID} (lesson #{$lesson_num}, quiz: {$quiz_id}) for user {$user_id}");
            }
        }

        // Store granted post IDs for retrieval
        update_user_meta($user_id, '_flosc_free_lesson_post_ids', $granted_posts);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC: User {$user_id} offered " . count($selected_lessons) . " free lesson(s) (scored {$score}%, quiz: {$quiz_id})");

        return $selected_lessons;
    }
    
    /**
     * Get missed lesson numbers from quiz result
     * 
     * @param array $quiz_result
     * @return array Array of missed lesson numbers
     */
    private function get_missed_lessons($quiz_result) {
        // v1.8.2: Check structured incorrect/missed keys first (quiz types can provide these directly)
        $incorrect = $quiz_result['incorrect'] ?? $quiz_result['missed'] ?? [];
        if (!empty($incorrect)) {
            return array_map('intval', array_filter((array)$incorrect, 'is_numeric'));
        }

        // Fallback: comma-separated number parsing
        $user_answer   = $quiz_result['user_answer']   ?? '';
        $correct_answer = $quiz_result['correct_answer'] ?? '1,2,3,4,5,6,7,8,9,10';

        $user_numbers    = array_filter(array_map('trim', explode(',', $user_answer)), 'is_numeric');
        $correct_numbers = array_filter(array_map('trim', explode(',', $correct_answer)), 'is_numeric');

        $missed = [];
        foreach ($correct_numbers as $num) {
            if (!in_array($num, $user_numbers)) {
                $missed[] = intval($num);
            }
        }
        return $missed;
    }
    
    /**
     * Resolve the lesson category for a given quiz_id using lesson_groups
     * 
     * v3.0.0: Searches the current flow's lesson_groups array for a matching
     * quiz_id → category mapping. Falls back to legacy lessons_category.
     * 
     * @param string $quiz_id The quiz ID to look up (e.g., "flosc_sample_data_numbers_quiz")
     * @return string Category slug, or empty string if not found
     */
    private function resolve_category_for_quiz($quiz_id) {
        $flow = null;
        if (function_exists('flosc')) {
            $flow = flosc()->get_current_flow();
        }

        // v3.0.0: Check lesson_groups first
        if ($flow && !empty($flow['lesson_groups']) && is_array($flow['lesson_groups'])) {
            // First pass: exact quiz_id match
            foreach ($flow['lesson_groups'] as $group) {
                if (!empty($group['quiz_id']) && $group['quiz_id'] === $quiz_id && !empty($group['category'])) {
                    return $group['category'];
                }
            }
            // Second pass: if quiz_id is empty or not found, use the first group
            // that has no quiz (standalone) or just the first group as fallback
            foreach ($flow['lesson_groups'] as $group) {
                if (empty($group['quiz_id']) && !empty($group['category'])) {
                    return $group['category'];
                }
            }
            // Last resort: first group with any category
            foreach ($flow['lesson_groups'] as $group) {
                if (!empty($group['category'])) {
                    return $group['category'];
                }
            }
        }

        // v1.8.2 backward compat: single lessons_category
        if ($flow && !empty($flow['lessons_category'])) {
            return $flow['lessons_category'];
        }

        // Global fallback
        return get_option('flosc_lessons_category', '');
    }

    /**
     * Find a lesson post by lesson number
     * 
     * v3.0.0: Quiz-aware — resolves category from lesson_groups
     * v1.4.4: Fallback to common slug patterns
     * 
     * @param int    $lesson_num Lesson number to find
     * @param string $quiz_id    Optional quiz ID for category resolution
     * @return WP_Post|null
     */
    private function find_lesson_post($lesson_num, $quiz_id = '') {
        // v3.0.0: Resolve category through lesson_groups → legacy → global → scan
        $configured_cat = $this->resolve_category_for_quiz($quiz_id);

        // Scan all IVR flow settings if category still not resolved
        if (empty($configured_cat)) {
            $ivr_dir = defined('FLOSC_PLUGIN_DIR') ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/' : '';
            if ($ivr_dir && is_dir($ivr_dir)) {
                $files = array_merge(glob($ivr_dir . '*_ivr.md') ?: [], glob($ivr_dir . 'ivr*.md') ?: []);
                foreach (array_unique(array_map('basename', $files)) as $fn) {
                    $s = get_option('flosc_flow_' . sanitize_key(pathinfo($fn, PATHINFO_FILENAME)), []);
                    if (!empty($s['lesson_groups']) && is_array($s['lesson_groups'])) {
                        foreach ($s['lesson_groups'] as $g) {
                            if (!empty($g['category'])) { $configured_cat = $g['category']; break 2; }
                        }
                    }
                    if (!empty($s['lessons_category'])) { $configured_cat = $s['lessons_category']; break; }
                }
            }
        }

        // 1. Meta query — fast path, scoped to category if known
        $meta_args = [
            'meta_key'       => '_flosc_lesson_number',
            'meta_value'     => $lesson_num,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
        ];
        if (!empty($configured_cat)) {
            $meta_args[is_numeric($configured_cat) ? 'cat' : 'category_name'] =
                is_numeric($configured_cat) ? intval($configured_cat) : sanitize_title($configured_cat);
        }
        $posts = get_posts($meta_args);
        if (!empty($posts)) return $posts[0];

        // 2. Slug-based SQL lookup — works without _flosc_lesson_number meta.
        // LeSAEp posts follow the convention: lesson-{N}-description
        global $wpdb;
        $slug_prefix = 'lesson-' . intval($lesson_num) . '-';
        $post_id     = null;

        if (!empty($configured_cat)) {
            $cat_obj = is_numeric($configured_cat)
                ? get_term(intval($configured_cat), 'category')
                : get_term_by('slug', sanitize_title($configured_cat), 'category');
            if ($cat_obj && !is_wp_error($cat_obj)) {
                $post_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT p.ID FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                     WHERE tt.taxonomy = 'category'
                       AND tt.term_id = %d
                       AND p.post_status = 'publish'
                       AND p.post_name LIKE %s
                     LIMIT 1",
                    $cat_obj->term_id,
                    $wpdb->esc_like($slug_prefix) . '%'
                ));
            }
        } else {
            // No category configured — search all published posts by slug pattern
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_name LIKE %s
                 LIMIT 1",
                $wpdb->esc_like($slug_prefix) . '%'
            ));
        }

        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                // Auto-stamp so the faster meta query hits next time
                update_post_meta($post_id, '_flosc_lesson_number', intval($lesson_num));
                return $post;
            }
        }

        // 3. Title fallback — only viable when scoped to a category
        if (!empty($configured_cat)) {
            $cat_key   = is_numeric($configured_cat) ? 'cat' : 'category_name';
            $cat_val   = is_numeric($configured_cat) ? intval($configured_cat) : sanitize_title($configured_cat);
            $cat_posts = get_posts([
                $cat_key                 => $cat_val,
                'posts_per_page'         => -1,
                'post_status'            => 'publish',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);
            $pattern = '/^Lesson\s+' . preg_quote((string) intval($lesson_num), '/') . '\b/i';
            foreach ($cat_posts as $p) {
                if (preg_match($pattern, $p->post_title)) {
                    update_post_meta($p->ID, '_flosc_lesson_number', intval($lesson_num));
                    return $p;
                }
            }
        }

        return null;
    }
    
    /**
     * Get free lesson content for user (single — backward compatible)
     *
     * @param int $user_id
     * @return array|false Post data or false
     */
    public function get_free_lesson($user_id) {
        $lessons = $this->get_free_lessons($user_id);
        return !empty($lessons) ? $lessons[0] : false;
    }

    /**
     * v1.5.4: Get all free lessons for user
     * v3.0.0: Uses stored quiz_id to resolve the correct category
     *
     * @param int $user_id
     * @return array Array of lesson data arrays
     */
    public function get_free_lessons($user_id) {
        $lesson_nums = get_user_meta($user_id, '_flosc_free_lesson_numbers', true);

        // Backward compat: fall back to single lesson number
        if (empty($lesson_nums)) {
            $single = get_user_meta($user_id, '_flosc_free_lesson_number', true);
            $lesson_nums = $single ? [$single] : [];
        }

        if (empty($lesson_nums)) {
            return [];
        }

        // v3.0.0: Read the quiz_id that was stored at completion time
        $quiz_id = get_user_meta($user_id, '_flosc_free_lesson_quiz_id', true) ?: '';

        $lessons = [];
        foreach ($lesson_nums as $lesson_num) {
            $post = $this->find_lesson_post($lesson_num, $quiz_id);
            if ($post) {
                // v1.8.3: Run content through the_content filters so WordPress
                // renders shortcodes, wpautop, embeds, etc.
                $rendered_content = apply_filters('the_content', $post->post_content);
                $lessons[] = [
                    'post_id'       => $post->ID,
                    'lesson_number' => $lesson_num,
                    'title'         => $post->post_title,
                    'content'       => $rendered_content,
                    'url'           => get_permalink($post->ID),
                    'excerpt'       => wp_trim_words($post->post_content, 55),
                ];
            }
        }

        return $lessons;
    }
    
    /**
     * Check if user has already received free lesson
     * 
     * @param int $user_id
     * @return bool
     */
    public function has_received_free_lesson($user_id) {
        $offered = get_user_meta($user_id, '_flosc_free_lesson_offered', true);
        return !empty($offered);
    }
    
    /**
     * Deliver free lesson(s) via chat or redirect
     * v1.5.4: Supports multiple lessons
     *
     * @param int    $user_id
     * @param string $delivery_mode 'chat' or 'redirect'
     * @return array Response data
     */
    public function deliver_free_lesson($user_id, $delivery_mode = 'chat') {

        if ($this->has_received_free_lesson($user_id)) {
            $lessons = $this->get_free_lessons($user_id);
            return [
                'success' => true,
                'mode' => 'chat',
                'lessons' => $lessons,
                'already_delivered' => true,
                'message' => 'Here\'s your free lesson again!',
            ];
        }

        $lessons = $this->get_free_lessons($user_id);

        if (empty($lessons)) {
            return [
                'success' => false,
                'message' => 'No free lesson available.',
            ];
        }

        // Mark as delivered
        update_user_meta($user_id, '_flosc_free_lesson_delivered', time());

        $count = count($lessons);

        if ($delivery_mode === 'redirect') {
            return [
                'success' => true,
                'mode'    => 'redirect',
                'url'     => $lessons[0]['url'],
                'message' => "Redirecting to your free lesson: {$lessons[0]['title']}",
            ];
        }

        // Chat delivery — return all lessons
        return [
            'success' => true,
            'mode'    => 'chat',
            'lessons' => $lessons,
            'count'   => $count,
            // Backward compat: single lesson fields
            'lesson_number' => $lessons[0]['lesson_number'],
            'title'         => $lessons[0]['title'],
            'content'       => $lessons[0]['content'],
            'url'           => $lessons[0]['url'],
            'message'       => $count === 1
                ? "Here's your free lesson on {$lessons[0]['title']}!"
                : "Here are your {$count} free lessons!",
        ];
    }
}
