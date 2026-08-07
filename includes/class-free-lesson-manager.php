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
 * 3. Finds the configured lesson category for the quiz
 * 4. Calculates missed items: 1,2,3,5,6,8,10
 * 5. Picks random (e.g., #8)
 * 6. Delivers WordPress post with _flosc_lesson_number = 8 from the configured category
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
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: User {$user_id} scored {$score}% — no free lesson needed");
            return;
        }

        // Get missed lessons
        $missed = $this->get_missed_lessons($quiz_result);

        if (empty($missed)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: No missed lessons found for user {$user_id}");
            return;
        }

        // Admin-configured complimentary count (flow free_lesson_count / proportion).
        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php';
        $member_access = FLOSC_Member_Access::instance();
        $count = max(1, intval($member_access->calculate_free_lesson_count(count($missed))));

        // v8.0.0: IPA audio quiz free lesson selection.
        // ranked_worst is sorted worst→best (lowest score = index 0).
        // Walk tiers (prefer multi-phoneme tiers) and pick up to $count eligible pool lessons.
        // never_free list is excluded. Video is NOT required (pool membership is enough).

        $ranked_worst = $quiz_result['ranked_worst_lessons'] ?? [];

        if (!empty($ranked_worst) && is_array($ranked_worst)) {
            $tiers = [];
            foreach ($ranked_worst as $entry) {
                $score = $entry['score'] ?? 0;
                $tiers[$score][] = $entry;
            }
            ksort($tiers);
            $tiers = array_values($tiers);

            $selected_lessons = $this->pick_eligible_lessons_from_tiers($tiers, $quiz_id, $count);
        } else {
            // Non-IPA quiz: shuffle missed lessons, pick admin-configured count from pool
            shuffle($missed);
            $selected_lessons = [];
            foreach ($missed as $lesson_num) {
                $n = intval($lesson_num);
                if ($this->is_never_free_lesson($n)) {
                    continue;
                }
                if ($this->lesson_number_is_free_eligible($n, $quiz_id)) {
                    $selected_lessons[] = $n;
                    if (count($selected_lessons) >= $count) {
                        break;
                    }
                }
            }
        }

        // Bonus free lesson (admin: free_lesson_guaranteed) — in pool, not never-free
        $guaranteed = intval(function_exists('flosc_get_setting')
            ? flosc_get_setting('free_lesson_guaranteed', 35)
            : 35);
        if ($guaranteed > 0
            && !$this->is_never_free_lesson($guaranteed)
            && !in_array($guaranteed, $selected_lessons, true)
            && $this->lesson_number_is_free_eligible($guaranteed, $quiz_id)
        ) {
            array_unshift($selected_lessons, $guaranteed);
        }

        if (empty($selected_lessons)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log("FLOSC: No free-lesson candidates for user {$user_id} (quiz: {$quiz_id})");
            }
            return;
        }

        update_user_meta($user_id, '_flosc_free_lesson_number', $selected_lessons[0]);
        update_user_meta($user_id, '_flosc_free_lesson_numbers', $selected_lessons);
        update_user_meta($user_id, '_flosc_free_lesson_quiz_id', $quiz_id);
        update_user_meta($user_id, '_flosc_free_lesson_offered', time());

        $granted_posts = [];
        $granted_nums  = [];
        foreach ($selected_lessons as $lesson_num) {
            $post = $this->find_free_eligible_lesson_post($lesson_num, $quiz_id);
            if ($post) {
                $member_access->grant_guest_access($user_id, $post->ID);
                $granted_posts[] = $post->ID;
                $granted_nums[]  = intval($lesson_num);
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                    flosc_log("FLOSC: Granted guest access to post {$post->ID} (lesson #{$lesson_num}, quiz: {$quiz_id}) for user {$user_id}");
                }
            }
        }

        if (empty($granted_posts)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log("FLOSC: Free lesson numbers selected but none found in pool for user {$user_id}");
            }
            return;
        }

        update_user_meta($user_id, '_flosc_free_lesson_number', $granted_nums[0]);
        update_user_meta($user_id, '_flosc_free_lesson_numbers', $granted_nums);
        update_user_meta($user_id, '_flosc_free_lesson_post_ids', $granted_posts);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log("FLOSC: User {$user_id} offered " . count($granted_nums) . " free lesson(s) (scored {$score}%, quiz: {$quiz_id})");
        }

        return $granted_nums;
    }

    /**
     * Per-flow free-sample pool category slug (Lessons → free_lesson_pool_category).
     * Empty = use the normal lesson-group category (all mapped lessons).
     *
     * @return string
     */
    private function get_free_lesson_pool_category() {
        if (!function_exists('flosc_get_setting')) {
            return '';
        }
        return sanitize_title((string) flosc_get_setting('free_lesson_pool_category', ''));
    }

    /**
     * Lesson numbers that must never be given as complimentary guest content.
     * Admin: free_lesson_never_free (comma/space-separated numbers).
     *
     * @return int[]
     */
    private function get_never_free_lesson_numbers() {
        if (!function_exists('flosc_get_setting')) {
            return [];
        }
        $raw = (string) flosc_get_setting('free_lesson_never_free', '');
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $n = intval($p);
            if ($n > 0) {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param int $lesson_num Lesson number.
     * @return bool
     */
    private function is_never_free_lesson($lesson_num) {
        $n = intval($lesson_num);
        return $n > 0 && in_array($n, $this->get_never_free_lesson_numbers(), true);
    }

    /**
     * Find a published lesson post by number inside a specific category slug.
     *
     * @param int    $lesson_num
     * @param string $category_slug
     * @return WP_Post|null
     */
    private function find_lesson_post_in_category($lesson_num, $category_slug) {
        $lesson_num = intval($lesson_num);
        $category_slug = sanitize_title((string) $category_slug);
        if ($lesson_num <= 0 || $category_slug === '') {
            return null;
        }

        $pids = function_exists( 'flosc_get_post_ids_for_meta' )
            ? flosc_get_post_ids_for_meta( '_flosc_lesson_number', (string) $lesson_num, 5 )
            : array();
        if ( ! empty( $pids ) ) {
            foreach ( $pids as $pid ) {
                $post = get_post( (int) $pid );
                if ( $post && has_term( $category_slug, 'category', $post ) ) {
                    return $post;
                }
            }
        }

        // Title fallback within category (same as main finder)
        $cat_posts = get_posts([
            'category_name'          => $category_slug,
            'posts_per_page'         => -1,
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $pattern = '/^Lesson\s+' . preg_quote((string) $lesson_num, '/') . '\b/i';
        foreach ($cat_posts as $p) {
            if (preg_match($pattern, $p->post_title)) {
                update_post_meta($p->ID, '_flosc_lesson_number', $lesson_num);
                return $p;
            }
        }
        return null;
    }

    /**
     * Resolve free-sample post: only from free_lesson_pool_category when set.
     *
     * @param int    $lesson_num
     * @param string $quiz_id
     * @return WP_Post|null
     */
    private function find_free_eligible_lesson_post($lesson_num, $quiz_id = '') {
        $pool = $this->get_free_lesson_pool_category();
        if ($pool !== '') {
            return $this->find_lesson_post_in_category($lesson_num, $pool);
        }
        // No pool configured: any published post in the quiz's lesson group category
        return $this->find_lesson_post($lesson_num, $quiz_id);
    }

    /**
     * @param int    $lesson_num
     * @param string $quiz_id
     * @return bool
     */
    private function lesson_number_is_free_eligible($lesson_num, $quiz_id = '') {
        $n = intval($lesson_num);
        if ($n <= 0 || $this->is_never_free_lesson($n)) {
            return false;
        }
        // Published post in free-lesson pool (or lesson group if no pool). Video not required.
        return (bool) $this->find_free_eligible_lesson_post($n, $quiz_id);
    }

    /**
     * Collect eligible lesson numbers from a score tier (shuffled).
     *
     * @param array  $tier_entries
     * @param string $quiz_id
     * @param int[]  $exclude Already selected numbers.
     * @return int[]
     */
    private function collect_eligible_from_tier(array $tier_entries, $quiz_id, array $exclude = []) {
        $candidates = [];
        foreach ($tier_entries as $entry) {
            $lesson_nums = array_map('intval', (array) ($entry['lessons'] ?? []));
            foreach ($lesson_nums as $n) {
                if ($n <= 0 || in_array($n, $exclude, true) || in_array($n, $candidates, true)) {
                    continue;
                }
                if ($this->lesson_number_is_free_eligible($n, $quiz_id)) {
                    $candidates[] = $n;
                }
            }
        }
        shuffle($candidates);
        return $candidates;
    }

    /**
     * Tier-walk free-lesson pick limited to the free lesson pool + never-free rules.
     * Returns up to $count lesson numbers (admin free_lesson_count).
     *
     * @param array  $tiers
     * @param string $quiz_id
     * @param int    $count
     * @return int[]
     */
    private function pick_eligible_lessons_from_tiers(array $tiers, $quiz_id = '', $count = 1) {
        $count = max(1, intval($count));
        if (empty($tiers)) {
            return [];
        }

        $selected = [];

        // Prefer multi-phoneme tiers first (protect unique worst as upsell when possible).
        for ($i = 0; $i < count($tiers) && count($selected) < $count; $i++) {
            $prefer = (count($tiers[$i]) > 1) || ($i >= 2);
            if (!$prefer) {
                continue;
            }
            $candidates = $this->collect_eligible_from_tier($tiers[$i], $quiz_id, $selected);
            foreach ($candidates as $n) {
                $selected[] = $n;
                if (count($selected) >= $count) {
                    break;
                }
            }
        }

        // Fill from any remaining tiers (including single-phoneme worst if still short).
        if (count($selected) < $count) {
            foreach ($tiers as $tier) {
                $candidates = $this->collect_eligible_from_tier($tier, $quiz_id, $selected);
                foreach ($candidates as $n) {
                    $selected[] = $n;
                    if (count($selected) >= $count) {
                        break 2;
                    }
                }
            }
        }

        return $selected;
    }

    /**
     * @deprecated Use pick_eligible_lessons_from_tiers — kept for any external callers.
     *
     * @param array  $tiers
     * @param string $quiz_id
     * @return int[]
     */
    private function pick_eligible_lesson_from_tiers(array $tiers, $quiz_id = '') {
        return $this->pick_eligible_lessons_from_tiers($tiers, $quiz_id, 1);
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
            // §2: union shipped defaults with uploaded/edited IVR files (uploads wins).
            $files = function_exists('flosc_config_glob') ? flosc_config_glob(['*_ivr.md', 'ivr*.md']) : [];
            if (!empty($files)) {
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

        // 1. Lesson number meta → post IDs (cached prepared postmeta), then scope to category.
        $pids = function_exists( 'flosc_get_post_ids_for_meta' )
            ? flosc_get_post_ids_for_meta( '_flosc_lesson_number', (string) intval( $lesson_num ), 10 )
            : array();
        if ( ! empty( $pids ) ) {
            foreach ( $pids as $pid ) {
                $post = get_post( (int) $pid );
                if ( ! $post || 'publish' !== $post->post_status ) {
                    continue;
                }
                if ( ! empty( $configured_cat ) ) {
                    if ( is_numeric( $configured_cat ) ) {
                        if ( ! has_term( (int) $configured_cat, 'category', $post ) ) {
                            continue;
                        }
                    } elseif ( ! has_term( sanitize_title( $configured_cat ), 'category', $post ) ) {
                        continue;
                    }
                }
                return $post;
            }
        }

        // 2. Slug / title fallback via get_posts (no direct $wpdb).
        // lesson posts follow the convention: lesson-{N}-description
        $slug_prefix = 'lesson-' . intval( $lesson_num ) . '-';
        $list_args   = array(
            'posts_per_page'         => -1,
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        if ( ! empty( $configured_cat ) ) {
            $list_args[ is_numeric( $configured_cat ) ? 'cat' : 'category_name' ] =
                is_numeric( $configured_cat ) ? intval( $configured_cat ) : sanitize_title( $configured_cat );
        }
        $candidates = get_posts( $list_args );
        $title_pat  = '/^Lesson\s+' . preg_quote( (string) intval( $lesson_num ), '/' ) . '\b/i';
        foreach ( (array) $candidates as $p ) {
            $name = isset( $p->post_name ) ? (string) $p->post_name : '';
            if ( $name !== '' && 0 === strpos( $name, $slug_prefix ) ) {
                update_post_meta( $p->ID, '_flosc_lesson_number', intval( $lesson_num ) );
                return $p;
            }
        }
        foreach ( (array) $candidates as $p ) {
            if ( preg_match( $title_pat, $p->post_title ) ) {
                update_post_meta( $p->ID, '_flosc_lesson_number', intval( $lesson_num ) );
                return $p;
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
            // Published pool post only (never-free already filtered at grant time).
            // Video is not required — admins control the pool category + never-free list.
            if ($this->is_never_free_lesson(intval($lesson_num))) {
                continue;
            }
            $post = $this->find_free_eligible_lesson_post($lesson_num, $quiz_id);
            if ($post) {
                // WordPress content filters (shortcodes, embeds, blocks, etc.).
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WP content filter required for oEmbed/shortcodes
                $rendered_content = apply_filters( 'the_content', $post->post_content );
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
            if (empty($lessons)) {
                return [
                    'success' => false,
                    'message' => 'No free lesson available. Please take the quiz first.',
                ];
            }
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
