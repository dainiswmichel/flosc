<?php
/**
 * On-demand page/post content for AI chat context.
 * Injects access-filtered post body only when a message needs it - never on navigation.
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Page_Context {

    private static $instance = null;

    const MAX_CONTENT_CHARS = 12000;
    const SESSION_TTL       = HOUR_IN_SECONDS;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resolve and persist browsing post ID for chat logs — never loads post body.
     *
     * @param array $eval_context By reference.
     */
    public function normalize_browsing_post_id(array &$eval_context) {
        if (!$this->is_page_context_handoff_enabled()) {
            return;
        }

        $current_id = $this->resolve_current_browsing_post_id($eval_context);
        if ($current_id <= 0) {
            return;
        }

        $eval_context['browsing_page_post_id'] = $current_id;

        $post = get_post($current_id);
        if ($post) {
            if (empty($eval_context['browsing_page_title'])) {
                $eval_context['browsing_page_title'] = sanitize_text_field((string) get_the_title($post));
            }
            if (empty($eval_context['browsing_page_url'])) {
                $permalink = get_permalink($post);
                if (is_string($permalink) && $permalink !== '') {
                    $eval_context['browsing_page_url'] = esc_url_raw($permalink);
                }
            }
        }
    }

    /**
     * Attach post content to eval_context when the user message warrants it.
     *
     * @param array  $eval_context By reference.
     * @param string $message      User message.
     * @param string $session_key  Visitor session id or server session id string.
     */
    public function maybe_attach_to_context(array &$eval_context, $message, $session_key = '') {
        if (!$this->is_page_context_handoff_enabled()) {
            return;
        }

        $message = trim((string) $message);
        if ($message === '') {
            return;
        }

        $this->normalize_browsing_post_id($eval_context);

        // Location-only questions need title/URL/post ID (already in chatpack) — not full body.
        if ($this->message_is_page_location_query($message)) {
            return;
        }

        $post_id = $this->resolve_post_id_for_content_injection($eval_context, $message, $session_key);
        if ($post_id <= 0) {
            return;
        }

        $access_level = sanitize_key((string) ($eval_context['access_level'] ?? 'visitor'));
        $user_id      = absint($eval_context['user_id'] ?? 0);
        $content      = $this->load_post_content($post_id, $access_level, $user_id);

        if ($content === '') {
            return;
        }

        $post = get_post($post_id);
        $eval_context['browsing_page_post_id'] = $post_id;
        $eval_context['browsing_page_content'] = $content;
        $eval_context['page_content_injected'] = true;

        if ($post && empty($eval_context['browsing_page_title'])) {
            $eval_context['browsing_page_title'] = sanitize_text_field((string) get_the_title($post));
        }

        if ($session_key !== '') {
            set_transient($this->session_transient_key($session_key), $post_id, self::SESSION_TTL);
        }
    }

    private function session_transient_key($session_key) {
        return 'flosc_page_focus_' . md5(sanitize_text_field((string) $session_key));
    }

    private function get_session_focus_post_id($session_key) {
        if ($session_key === '') {
            return 0;
        }
        return absint(get_transient($this->session_transient_key($session_key)));
    }

    private function is_page_context_handoff_enabled() {
        $pass_page_context = flosc_get_setting('companion_pass_page_context', '1');
        return filter_var($pass_page_context, FILTER_VALIDATE_BOOLEAN);
    }

    private function resolve_current_browsing_post_id(array $eval_context) {
        // Companion already hands off the post ID — trust it when valid.
        $explicit_id = absint($eval_context['browsing_page_post_id'] ?? 0);
        if ($explicit_id > 0) {
            $resolved = $this->resolve_post_id($explicit_id);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        $page_url = esc_url_raw((string) ($eval_context['browsing_page_url'] ?? ''));
        if ($page_url !== '') {
            return $this->resolve_post_id_from_url($page_url);
        }

        return 0;
    }

    private function resolve_post_id_for_content_injection(array $eval_context, $message, $session_key) {
        $current_id = absint($eval_context['browsing_page_post_id'] ?? 0);
        if ($current_id <= 0) {
            return 0;
        }

        $title         = sanitize_text_field((string) ($eval_context['browsing_page_title'] ?? ''));
        $session_focus = $this->get_session_focus_post_id($session_key);

        // Follow-up on same focused page (reuse cached focus, no re-ingest every turn).
        if ($session_focus > 0 && $current_id === $session_focus && $this->message_is_short_followup($message)) {
            return $session_focus;
        }

        // On-demand body load: only when the user message targets this page/post/product.
        if ($this->message_targets_current_page($message, $title)) {
            return $current_id;
        }

        return 0;
    }

    private function resolve_post_id($explicit_id) {
        if ($explicit_id > 0) {
            $post = get_post($explicit_id);
            if ($post && $post->post_status === 'publish') {
                return (int) $post->ID;
            }
        }

        return 0;
    }

    private function resolve_post_id_from_url($url) {
        $url = esc_url_raw((string) $url);
        if ($url === '') {
            return 0;
        }

        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        if ($site_host && $url_host && strtolower((string) $site_host) !== strtolower((string) $url_host)) {
            return 0;
        }

        $candidates = [$url];
        $parsed     = wp_parse_url($url);
        if (!empty($parsed['path'])) {
            $path_only = home_url($parsed['path']);
            $candidates[] = $path_only;
            $candidates[] = trailingslashit($path_only);
            $candidates[] = untrailingslashit($path_only);
        }

        foreach (array_unique($candidates) as $candidate) {
            $resolved = $this->resolve_post_id(absint(url_to_postid($candidate)));
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return 0;
    }

    private function message_is_page_location_query($message) {
        $msg = strtolower(trim((string) $message));
        if ($msg === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(what page|which page|where am i|page am i on|page i\'m on|page i am on|current page|am i on)\b/',
            $msg
        );
    }

    private function message_targets_current_page($message, $title) {
        $msg = strtolower(trim($message));

        $phrases = [
            'this page', 'this post', 'this product', 'this course',
            'this song', 'this article', 'this item', 'this listing',
            'more about this', 'about this', 'about the song', 'about the page',
            'about the post', 'about the product', 'about the article',
            'what i\'m looking at', 'what i am looking at', 'on this page',
            'tell me about this', 'what is this', 'explain this', 'describe this',
            'summarize this', 'summary of this', 'read this', 'page content',
            'product description', 'post content',
            'looking at this',
        ];
        foreach ($phrases as $phrase) {
            if (strpos($msg, $phrase) !== false) {
                return true;
            }
        }

        if ($title !== '' && strlen($title) > 4) {
            $title_clean = strtolower(preg_replace('/[^a-z0-9\s]/', ' ', $title));
            $words = array_filter(explode(' ', $title_clean), function ($w) {
                return strlen($w) > 3;
            });
            $hits = 0;
            foreach ($words as $word) {
                if (strpos($msg, $word) !== false) {
                    $hits++;
                }
            }
            if ($hits >= 2) {
                return true;
            }
        }

        return false;
    }

    private function message_is_short_followup($message) {
        $msg = strtolower(trim($message));

        if ($msg === '') {
            return false;
        }

        // Language/meta conversation choices are not page-content follow-ups.
        if (preg_match('/\b(english|latvian|german|spanish|french|language|prefer|servus|sveiks)\b/i', $msg)) {
            return false;
        }

        // Explicit continuation confirmations.
        if (in_array($msg, ['yes', 'yeah', 'yep', 'sure', 'please', 'ok', 'okay'], true)) {
            return true;
        }

        // Pronoun-based continuation of the current page/topic.
        if (preg_match('/\b(it|its|this|that|these|those|they|them|there)\b/', $msg)) {
            return true;
        }

        // Short question-style follow-ups are likely same-thread continuations.
        if (
            strlen($msg) <= 120
            && preg_match('/^(what|how|why|where|when|which|who|can|could|would|should|do|does|did|is|are|was|were)\b/', $msg)
            && !preg_match('/\b(testimonial|testimonials|review|reviews|another page|different page|new page|other page)\b/', $msg)
        ) {
            return true;
        }

        return false;
    }

    private function load_post_content($post_id, $access_level, $user_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return '';
        }

        $raw = $post->post_content;

        $protection = FLOSC_Content_Protection::instance();
        $check      = $protection->check_post_protection($post_id);

        if (!empty($check['protected'])) {
            if (!$protection->user_can_access($post_id)) {
                $visibility = $protection->get_post_visibility($post_id);
                $raw        = $protection->apply_visibility_tier($raw, $visibility, $post_id);
            }
        }

        $filter = flosc_content_filter::instance();
        $raw    = $filter->filter_post_content($raw, $access_level);

        $text = wp_strip_all_tags($raw);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (strlen($text) > self::MAX_CONTENT_CHARS) {
            $text = substr($text, 0, self::MAX_CONTENT_CHARS) . '...';
        }

        return $text;
    }
}
