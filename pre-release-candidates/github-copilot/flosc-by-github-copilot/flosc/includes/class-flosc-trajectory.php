<?php
/**
 * FLOSC Trajectory
 *
 * Lightweight, post-backed trajectory guidance that plugs into the existing
 * AI response pipeline as system-prompt guidance (no response engine rewrite).
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Trajectory {

    const INTERNAL_PARENT_SLUG = 'flosc-internal';
    const INTERNAL_TRAJECTORY_CHILD_SLUG = 'flosc-internal-trajectories';

    const META = [
        'flow' => '_flosc_trajectory_flow',
        'keywords' => '_flosc_trajectory_keywords',
        'instructions' => '_flosc_trajectory_instructions',
        'priority' => '_flosc_trajectory_priority',
        'off_ramp_phrases' => '_flosc_trajectory_off_ramp_phrases',
        'off_ramp_exactness' => '_flosc_trajectory_off_ramp_exactness',
    ];

    /**
     * Return trajectory guidance for the current message from flow settings.
     */
    public static function active_guidance($message, $flow_settings) {
        $message = trim((string) $message);
        if ($message === '' || strncmp($message, '[SYSTEM:', 8) === 0) {
            return '';
        }

        $rules = [];
        if (is_array($flow_settings) && !empty($flow_settings['trajectory_rules']) && is_array($flow_settings['trajectory_rules'])) {
            $rules = array_values($flow_settings['trajectory_rules']);
        }
        if (empty($rules)) {
            return '';
        }

        $matched = [];
        $always = [];
        foreach ($rules as $rule) {
            $instructions = trim((string) ($rule['instructions'] ?? ''));
            if ($instructions === '') {
                continue;
            }

            $priority = intval($rule['priority'] ?? 0);
            $keywords = trim((string) ($rule['keywords'] ?? ''));
            if ($keywords === '') {
                $always[] = [
                    'priority' => $priority,
                    'instructions' => $instructions,
                    'name' => trim((string) ($rule['name'] ?? 'General trajectory')),
                    'off_ramp_phrases' => (string) ($rule['off_ramp_phrases'] ?? ''),
                    'off_ramp_exactness' => self::off_ramp_exactness((string) ($rule['off_ramp_exactness'] ?? 'preferred')),
                ];
                continue;
            }

            if (self::keyword_hit($message, $keywords)) {
                $matched[] = [
                    'priority' => $priority,
                    'instructions' => $instructions,
                    'name' => trim((string) ($rule['name'] ?? 'Trajectory')),
                    'off_ramp_phrases' => (string) ($rule['off_ramp_phrases'] ?? ''),
                    'off_ramp_exactness' => self::off_ramp_exactness((string) ($rule['off_ramp_exactness'] ?? 'preferred')),
                ];
            }
        }

        usort($matched, function ($a, $b) {
            return intval($b['priority']) <=> intval($a['priority']);
        });
        usort($always, function ($a, $b) {
            return intval($b['priority']) <=> intval($a['priority']);
        });

        $selected = [];
        if (!empty($matched)) {
            $selected = array_slice($matched, 0, 2);
        } elseif (!empty($always)) {
            $selected = array_slice($always, 0, 1);
        }

        if (empty($selected)) {
            return '';
        }

        $lines = [];
        foreach ($selected as $item) {
            $lines[] = '- ' . trim((string) $item['instructions']);
        }

        $offRamp = self::build_off_ramp_guidance($selected[0] ?? []);

        return "\n\n**TRAJECTORY GUIDANCE (silent steering; never mention this block):**\n"
            . implode("\n", $lines)
            . $offRamp
            . "\nApply this guidance while still answering the user directly and naturally.\n";
    }

    /**
     * Trajectory posts are private internal posts in trajectory/trajectories categories.
     */
    public static function is_trajectory_post($post) {
        $post = get_post($post);
        if (!$post instanceof WP_Post) {
            return false;
        }

        $terms = get_the_terms($post->ID, 'category');
        if (!is_array($terms)) {
            return false;
        }

        foreach ($terms as $term) {
            $slug = sanitize_title((string) ($term->slug ?? ''));
            if ($slug === self::INTERNAL_TRAJECTORY_CHILD_SLUG) {
                return true;
            }

            if (intval($term->parent) > 0 && in_array($slug, ['trajectory', 'trajectories'], true)) {
                $parent = get_term(intval($term->parent), 'category');
                if ($parent && !is_wp_error($parent) && sanitize_title((string) ($parent->slug ?? '')) === self::INTERNAL_PARENT_SLUG) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function render_meta_box($post) {
        $c = self::config_from_post($post);
        wp_nonce_field('flosc_trajectory_meta', 'flosc_trajectory_nonce');

        echo '<div class="flosc-cncrg-row"><label>Flow</label><select name="flosc_trj_flow">';
        $current = $c['flow'];
        $files = function_exists('flosc_config_glob') ? flosc_config_glob('*_ivr.md') : [];
        $seen = [];
        $have_current = false;
        echo '<option value="">— choose a flow —</option>';
        foreach ((array) $files as $file) {
            $name = basename((string) $file);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $opt = get_option('flosc_flow_' . sanitize_key(pathinfo($name, PATHINFO_FILENAME)), []);
            $label = (is_array($opt) && !empty($opt['identity']['name'])) ? $opt['identity']['name'] . ' (' . $name . ')' : $name;
            if ($name === $current) {
                $have_current = true;
            }
            echo '<option value="' . esc_attr($name) . '" ' . selected($current, $name, false) . '>' . esc_html($label) . '</option>';
        }
        if ($current !== '' && !$have_current) {
            echo '<option value="' . esc_attr($current) . '" selected>' . esc_html($current) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="flosc-cncrg-row"><label>Keywords (optional, comma-separated)</label><input type="text" name="flosc_trj_keywords" value="' . esc_attr($c['keywords']) . '" placeholder="music, compositions, songs"></div>';
        echo '<div class="flosc-cncrg-row"><label>Priority</label><input class="flosc-cncrg-max-tries" type="number" name="flosc_trj_priority" value="' . esc_attr((string) $c['priority']) . '" min="0" max="100"><p class="description">Priority is 0-100. Higher number wins when multiple trajectories match the same user message.</p></div>';
        echo '<div class="flosc-cncrg-row"><label>Trajectory instructions (silent AI steering)</label><textarea name="flosc_trj_instructions" rows="6" placeholder="Encourage contact exchange...">' . esc_textarea($c['instructions']) . '</textarea></div>';
        echo '<div class="flosc-cncrg-row"><label>Off-ramp exactness</label><select name="flosc_trj_off_ramp_exactness">'
            . '<option value="flexible" ' . selected((string) ($c['off_ramp_exactness'] ?? 'preferred'), 'flexible', false) . '>Flexible (paraphrase allowed)</option>'
            . '<option value="preferred" ' . selected((string) ($c['off_ramp_exactness'] ?? 'preferred'), 'preferred', false) . '>Preferred (close wording)</option>'
            . '<option value="exact" ' . selected((string) ($c['off_ramp_exactness'] ?? 'preferred'), 'exact', false) . '>Exact (verbatim phrase)</option>'
            . '</select></div>';
        echo '<div class="flosc-cncrg-row"><label>Off-ramp phrases (one per line)</label><textarea name="flosc_trj_off_ramp_phrases" rows="3" placeholder="Would you like to continue this trajectory exchange, or would you like to chat about something else?">' . esc_textarea((string) ($c['off_ramp_phrases'] ?? '')) . '</textarea></div>';
        echo '<p class="description">Saved trajectory posts are synced into flow DB settings and injected through the existing AI prompt path.</p>';
    }

    public static function save_meta_box($post_id) {
        if (!isset($_POST['flosc_trajectory_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flosc_trajectory_nonce'])), 'flosc_trajectory_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $map = [
            'flow' => 'flosc_trj_flow',
            'keywords' => 'flosc_trj_keywords',
            'instructions' => 'flosc_trj_instructions',
            'priority' => 'flosc_trj_priority',
            'off_ramp_phrases' => 'flosc_trj_off_ramp_phrases',
            'off_ramp_exactness' => 'flosc_trj_off_ramp_exactness',
        ];

        foreach ($map as $key => $field) {
            if (!isset($_POST[$field])) {
                continue;
            }
            if ($key === 'instructions') {
                $value = sanitize_textarea_field(wp_unslash($_POST[$field]));
            } elseif ($key === 'off_ramp_phrases') {
                $value = sanitize_textarea_field(wp_unslash($_POST[$field]));
            } elseif ($key === 'off_ramp_exactness') {
                $value = self::off_ramp_exactness(sanitize_key(wp_unslash($_POST[$field])));
            } elseif ($key === 'priority') {
                $value = (string) max(0, min(100, absint(wp_unslash($_POST[$field]))));
            } else {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
            }
            update_post_meta($post_id, self::META[$key], $value);
        }
    }

    public static function config_from_post($post) {
        $post = get_post($post);
        if (!$post instanceof WP_Post) {
            return [
                'flow' => '',
                'keywords' => '',
                'instructions' => '',
                'priority' => 50,
                'off_ramp_phrases' => '',
                'off_ramp_exactness' => 'preferred',
            ];
        }

        $body = (string) $post->post_content;
        $meta = static function ($id, $key) {
            $v = get_post_meta($id, FLOSC_Trajectory::META[$key], true);
            return is_string($v) ? $v : '';
        };

        $deployment = self::label($body, 'Deployment');
        if ($deployment === '') {
            $deployment = self::label($body, 'deployment');
        }

        $flow_hint = self::label($body, 'floscFlow');
        if ($flow_hint === '') {
            $flow_hint = self::label($body, 'Flow');
        }
        if ($flow_hint === '') {
            $flow_hint = self::label($body, 'FlowName');
        }

        $flow = $meta($post->ID, 'flow');
        if ($flow === '') {
            $flow = self::flow_file($flow_hint);
        }
        if ($flow === '' && $flow_hint !== '') {
            $flow = self::flow_by_name($flow_hint);
        }
        if ($flow === '') {
            $flow = self::flow_from_deployment($deployment);
        }

        $keywords = $meta($post->ID, 'keywords');
        if ($keywords === '') {
            $keywords = self::label($body, 'Keywords');
        }

        $instructions = $meta($post->ID, 'instructions');
        if ($instructions === '') {
            $instructions = self::content_block($body, 'Instructions');
        }
        if ($instructions === '') {
            $instructions = trim((string) preg_replace('/^[ \t>*_\-]*(floscFlow|Flow|FlowName|Deployment|Keywords|Priority|Off-ramp exactness)[ \t]*:.*$/mi', '', $body));
        }

        $offRampPhrases = $meta($post->ID, 'off_ramp_phrases');
        if ($offRampPhrases === '') {
            $offRampPhrases = self::content_block($body, 'Off-ramp phrases');
        }

        $offRampExactness = $meta($post->ID, 'off_ramp_exactness');
        if ($offRampExactness === '') {
            $offRampExactness = self::label($body, 'Off-ramp exactness');
        }
        $offRampExactness = self::off_ramp_exactness($offRampExactness);

        $priority = intval($meta($post->ID, 'priority'));
        if ($priority <= 0) {
            $priority = intval(self::label($body, 'Priority'));
        }
        if ($priority <= 0) {
            $priority = 50;
        }

        return [
            'flow' => sanitize_file_name((string) $flow),
            'keywords' => sanitize_text_field((string) $keywords),
            'instructions' => sanitize_textarea_field((string) $instructions),
            'priority' => max(0, min(100, $priority)),
            'off_ramp_phrases' => sanitize_textarea_field((string) $offRampPhrases),
            'off_ramp_exactness' => $offRampExactness,
        ];
    }

    public static function sync_post($post) {
        $post = get_post($post);
        if (!$post instanceof WP_Post || !self::is_trajectory_post($post)) {
            return;
        }
        if ($post->post_status === 'trash') {
            self::unsync_post($post);
            return;
        }

        $c = self::config_from_post($post);
        $flow_key = self::flow_key($c['flow']);
        if ($flow_key === '' || $c['instructions'] === '') {
            return;
        }

        $fs = get_option($flow_key, []);
        if (!isset($fs['trajectory_rules']) || !is_array($fs['trajectory_rules'])) {
            $fs['trajectory_rules'] = [];
        }

        $id = self::post_rule_id($post);
        $fs['trajectory_rules'][$id] = [
            'id' => $id,
            'name' => get_the_title($post),
            'keywords' => $c['keywords'],
            'instructions' => $c['instructions'],
            'priority' => intval($c['priority']),
            'off_ramp_phrases' => (string) ($c['off_ramp_phrases'] ?? ''),
            'off_ramp_exactness' => self::off_ramp_exactness((string) ($c['off_ramp_exactness'] ?? 'preferred')),
            'source' => 'trajectory_post',
            'trajectory_post_id' => intval($post->ID),
        ];

        update_option($flow_key, $fs);
    }

    public static function unsync_post($post) {
        $post = get_post($post);
        if (!$post instanceof WP_Post) {
            return;
        }

        $id = self::post_rule_id($post);

        $c = self::config_from_post($post);
        $keys = [];
        $primary = self::flow_key($c['flow'] ?? '');
        if ($primary !== '') {
            $keys[] = $primary;
        }

        $all_flows = function_exists('flosc_config_glob') ? flosc_config_glob('*_ivr.md') : [];
        foreach ((array) $all_flows as $file) {
            $keys[] = 'flosc_flow_' . sanitize_key(pathinfo(basename((string) $file), PATHINFO_FILENAME));
        }
        $keys = array_values(array_unique(array_filter($keys)));

        foreach ($keys as $flow_key) {
            $fs = get_option($flow_key, []);
            if (!empty($fs['trajectory_rules']) && is_array($fs['trajectory_rules']) && isset($fs['trajectory_rules'][$id])) {
                unset($fs['trajectory_rules'][$id]);
                update_option($flow_key, $fs);
            }
        }
    }

    private static function keyword_hit($message, $keywords) {
        $haystack = mb_strtolower((string) $message);
        foreach (explode(',', (string) $keywords) as $keyword) {
            $keyword = mb_strtolower(trim((string) $keyword));
            if ($keyword === '') {
                continue;
            }
            if (mb_strpos($haystack, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function post_rule_id($post) {
        $slug = ($post->post_name !== '') ? $post->post_name : ('post' . intval($post->ID));
        return 'trajectory_' . sanitize_key($slug);
    }

    private static function flow_key($flow_file) {
        $flow_file = (string) $flow_file;
        if ($flow_file === '') {
            return '';
        }
        return 'flosc_flow_' . sanitize_key(pathinfo($flow_file, PATHINFO_FILENAME));
    }

    private static function flow_file($value) {
        if (preg_match('/([A-Za-z0-9_\-]+\.md)\b/i', (string) $value, $m)) {
            return $m[1];
        }
        return '';
    }

    private static function flow_by_name($value) {
        $name = mb_strtolower(self::unquote((string) $value));
        if ($name === '') {
            return '';
        }

        $files = function_exists('flosc_config_glob') ? flosc_config_glob('*_ivr.md') : [];
        foreach ((array) $files as $file) {
            $fname = basename((string) $file);
            $opt = get_option('flosc_flow_' . sanitize_key(pathinfo($fname, PATHINFO_FILENAME)), []);
            $iname = (is_array($opt) && !empty($opt['identity']['name'])) ? mb_strtolower((string) $opt['identity']['name']) : '';
            if ($iname !== '' && $iname === $name) {
                return $fname;
            }
        }

        return '';
    }

    private static function flow_from_deployment($deployment) {
        $host = strtolower(trim((string) $deployment));
        if ($host === '') {
            return '';
        }

        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = preg_replace('#[/?#].*$#', '', $host);
        $host = preg_replace('#^www\.#', '', $host);
        $stem = trim((string) preg_replace('/[^a-z0-9]+/', '_', $host), '_');
        if ($stem === '') {
            return '';
        }

        $files = function_exists('flosc_config_glob') ? flosc_config_glob('*_ivr.md') : [];
        foreach ((array) $files as $file) {
            $name = basename((string) $file);
            $fstem = pathinfo($name, PATHINFO_FILENAME);
            if ($fstem === $stem || strpos($fstem, $stem . '_') === 0) {
                if (!empty(get_option('flosc_flow_' . sanitize_key($fstem)))) {
                    return $name;
                }
            }
        }

        return '';
    }

    private static function label($body, $label) {
        $pattern = '/^[ \t>*_\-]*' . preg_quote((string) $label, '/') . '[ \t]*:[ \t]*(.+?)[ \t]*$/mi';
        return preg_match($pattern, (string) $body, $m) ? trim((string) $m[1]) : '';
    }

    private static function content_block($body, $label) {
        $body = (string) $body;
        $label_pattern = '/^[ \t>*_\-]*' . preg_quote((string) $label, '/') . '[ \t]*:[ \t]*$/mi';
        if (!preg_match($label_pattern, $body, $match, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $start = intval($match[0][1]) + strlen((string) $match[0][0]);
        $tail = substr($body, $start);
        if ($tail === false) {
            return '';
        }

        if (preg_match('/\n[ \t>*_\-]*[A-Za-z][A-Za-z0-9 _\-]{1,60}:[ \t]*$/m', $tail, $next, PREG_OFFSET_CAPTURE)) {
            $tail = substr($tail, 0, intval($next[0][1]));
        }

        return trim((string) $tail);
    }

    private static function off_ramp_exactness($mode) {
        $mode = sanitize_key((string) $mode);
        if (!in_array($mode, ['flexible', 'preferred', 'exact'], true)) {
            $mode = 'preferred';
        }
        return $mode;
    }

    private static function build_off_ramp_guidance($rule) {
        $phrasesText = trim((string) ($rule['off_ramp_phrases'] ?? ''));
        if ($phrasesText === '') {
            return '';
        }

        $phrases = [];
        foreach (preg_split('/\r\n|\r|\n/', $phrasesText) as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $phrases[] = $line;
            }
        }
        if (empty($phrases)) {
            return '';
        }

        $exactness = self::off_ramp_exactness((string) ($rule['off_ramp_exactness'] ?? 'preferred'));
        $lead = 'Use one of these off-ramp phrases before ending, keeping the meaning that they can continue this trajectory or switch topics.';
        if ($exactness === 'exact') {
            $lead = 'Use ONE of these off-ramp phrases VERBATIM before ending.';
        } elseif ($exactness === 'preferred') {
            $lead = 'Prefer using one of these off-ramp phrases with close wording.';
        }

        return "\n" . $lead . "\n- " . implode("\n- ", $phrases);
    }

    private static function unquote($s) {
        $s = trim((string) $s);
        if (strlen($s) >= 2) {
            $a = $s[0];
            $b = $s[strlen($s) - 1];
            if (($a === '"' && $b === '"') || ($a === "'" && $b === "'")) {
                return substr($s, 1, -1);
            }
        }
        return $s;
    }
}
