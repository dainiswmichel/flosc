<?php
/**
 * FLOSC DA1 composition catalog replies (TSV-backed).
 *
 * Domain folder: includes/da1/ — catalog/composition answers only, not the chat pipeline.
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_DA1_Compositions {

    public function build_composition_reply($message, $flow_id, $ivr_file) {
        if (!$this->is_composition_query($message)) {
            return '';
        }

        $rows = $this->load_rows_for_flow($flow_id, $ivr_file);
        if (empty($rows)) {
            return '';
        }

        $items = $this->extract_composition_items($rows);
        if (empty($items)) {
            return '';
        }

        if ($this->is_count_request($message)) {
            $works_url = $this->get_works_list_url();
            $reply = 'Dainis currently has ' . count($items) . ' compositions in this catalog.'
                . "\nComplete works list: " . $works_url
                . "\nIf you want, I can suggest 1 to 3 compositions by style or mood.";
            return $this->limit_chat_response_length($reply);
        }

        if ($this->is_full_list_request($message)) {
            $works_url = $this->get_works_list_url();
            $reply = 'The complete compositions list is available here: ' . $works_url
                . "\nIf you want suggestions in chat, tell me a style or mood and I will present 1 to 3 matches at a time.";
            return $this->limit_chat_response_length($reply);
        }

        // Title lookup: if the visitor names a specific composition, serve that
        // exact one (with its real link) instead of the generic opening list.
        $message_norm = trim((string) preg_replace('/\s+/', ' ', strtolower((string) preg_replace('/[^a-z0-9 ]+/iu', ' ', (string) $message))));
        $title_matches = [];
        foreach ($items as $item) {
            $title_norm = trim((string) preg_replace('/\s+/', ' ', strtolower((string) preg_replace('/[^a-z0-9 ]+/iu', ' ', (string) $item['title']))));
            if ($title_norm !== '' && strpos($message_norm, $title_norm) !== false) {
                $title_matches[] = $item;
            }
        }
        if (!empty($title_matches)) {
            $lines = [count($title_matches) === 1 ? 'Here it is:' : 'Here are the matches:'];
            foreach (array_slice($title_matches, 0, 3) as $idx => $item) {
                $line = ($idx + 1) . '. ' . $item['title'];
                if ($item['description'] !== '') {
                    $line .= ' - ' . $this->shorten_text($item['description'], 120);
                }
                $lines[] = $line;
                if ($item['media'] !== '') {
                    $lines[] = 'Link: ' . $item['media'];
                }
            }
            return $this->limit_chat_response_length(implode("\n", $lines));
        }

        $max_items = $this->detect_batch_size($message);
        $slice = array_slice($items, 0, $max_items);
        $lines = [
            $max_items === 1
                ? 'Here is one composition to start:'
                : 'Here are ' . count($slice) . ' compositions to start:'
        ];
        foreach ($slice as $idx => $item) {
            $line = ($idx + 1) . '. ' . $item['title'];
            if ($item['description'] !== '') {
                $line .= ' - ' . $this->shorten_text($item['description'], 120);
            }
            $lines[] = $line;
            if ($item['media'] !== '') {
                $lines[] = 'Link: ' . $item['media'];
            }
        }
        $lines[] = 'Ask for another 1 to 3 suggestions, or ask for the complete list.';

        return $this->limit_chat_response_length(implode("\n", $lines));
    }

    public function is_composition_query($message) {
        $text = strtolower((string) $message);
        return (bool) preg_match('/\b(composition|compositions|song|songs|works|track|tracks|list of works|my works|your works|music works|dziesm|daina|dainas|dainu|skaņdarb|kompoz|melodij)\b/u', $text);
    }

    public function is_count_request($message) {
        $text = strtolower((string) $message);
        return (bool) preg_match('/\b(how many|number of|count|total|cik)\b/u', $text);
    }

    public function is_full_list_request($message) {
        $text = strtolower((string) $message);
        return (bool) preg_match('/\b(full list|complete list|all compositions|all songs|all works|entire catalog|show all|everything)\b/u', $text);
    }

    public function detect_batch_size($message) {
        $text = strtolower((string) $message);
        if (preg_match('/\b(one|1|single)\b/u', $text)) {
            return 1;
        }
        if (preg_match('/\b(three|3|few|some|several|options|suggestions)\b/u', $text)) {
            return 3;
        }
        return 2;
    }

    public function get_works_list_url() {
        $configured = trim((string) get_option('flosc_da1_works_list_url', ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
            return $configured;
        }
        return trailingslashit(home_url('/music/list-of-works/'));
    }

    public function load_rows_for_flow($flow_id, $ivr_file) {
        $upload_dir = wp_upload_dir();
        $catalog_dir = trailingslashit((string) ($upload_dir['basedir'] ?? '')) . 'flosc-catalogs';
        if (!is_dir($catalog_dir)) {
            return [];
        }

        $assignments = get_option('flosc_da1_flow_catalogs', []);
        $catalog_keys = ['default'];
        if (is_array($assignments) && $ivr_file !== '' && !empty($assignments[$ivr_file]) && is_array($assignments[$ivr_file])) {
            $catalog_keys = array_values(array_unique(array_filter(array_map(function($key) {
                return preg_replace('/[^a-z0-9._-]/i', '', strtolower(trim((string) $key)));
            }, $assignments[$ivr_file]))));
        }
        if (empty($catalog_keys)) {
            $catalog_keys = ['default'];
        }

        $flow_scope_tokens = [];
        if ($flow_id !== '') {
            $flow_scope_tokens[] = strtolower(trim((string) $flow_id));
        }
        if ($ivr_file !== '') {
            $flow_scope_tokens[] = strtolower(trim((string) pathinfo($ivr_file, PATHINFO_FILENAME)));
        }
        $flow_scope_tokens = array_values(array_unique(array_filter($flow_scope_tokens)));

        $rows_out = [];
        $fs       = class_exists( 'FLOSC_Filesystem' ) ? new FLOSC_Filesystem() : null;
        foreach ($catalog_keys as $catalog_key) {
            // Keys already restricted to [a-z0-9._-]; path matches admin flosc_da1_catalog_file().
            $path = trailingslashit( $catalog_dir ) . 'flosc_da1_catalog_' . $catalog_key . '.tsv';
            if ( ! file_exists( $path ) ) {
                continue;
            }
            // Uploads-bound TSV only — same policy as admin/da1.php (no direct file_get_contents).
            $content = $fs ? $fs->read_file_safely( $path ) : false;
            if ( false === $content || trim( (string) $content ) === '' ) {
                continue;
            }

            $parsed = $this->parse_tsv_content($content);
            if (count($parsed) < 2) {
                continue;
            }

            $header = array_map('trim', (array) $parsed[0]);
            foreach (array_slice($parsed, 1) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $assoc = [];
                foreach ($header as $i => $col) {
                    if ($col === '') {
                        continue;
                    }
                    $assoc[$col] = isset($row[$i]) ? trim((string) $row[$i]) : '';
                }

                $status = strtolower((string) ($assoc['Status'] ?? 'active'));
                if ($status !== '' && $status !== 'active') {
                    continue;
                }

                $scope = strtolower((string) ($assoc['Flow Scope'] ?? 'all'));
                if ($scope !== '' && $scope !== 'all' && !empty($flow_scope_tokens)) {
                    $allowed_scopes = array_filter(array_map('trim', explode(',', $scope)));
                    $scope_match = false;
                    foreach ($allowed_scopes as $allowed_scope) {
                        if (in_array($allowed_scope, $flow_scope_tokens, true)) {
                            $scope_match = true;
                            break;
                        }
                    }
                    if (!$scope_match) {
                        continue;
                    }
                }

                $rows_out[] = $assoc;
            }
        }

        return $rows_out;
    }

    public function extract_composition_items($rows) {
        $children_by_parent = [];
        foreach ($rows as $row) {
            $parent_key = trim((string) ($row['Parent Key'] ?? ''));
            if ($parent_key === '') {
                continue;
            }
            if (!isset($children_by_parent[$parent_key])) {
                $children_by_parent[$parent_key] = [];
            }
            $children_by_parent[$parent_key][] = $row;
        }

        $items = [];
        $seen_titles = [];
        foreach ($rows as $row) {
            $parent_key = trim((string) ($row['Parent Key'] ?? ''));
            if ($parent_key !== '') {
                continue;
            }

            $title = trim((string) ($row['Title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $title_key = strtolower($title);
            if (isset($seen_titles[$title_key])) {
                continue;
            }
            $seen_titles[$title_key] = true;

            $row_key = trim((string) ($row['Row Key'] ?? ''));
            $media = $this->extract_primary_media_url(trim((string) ($row['Media'] ?? '')));
            if ($media === '' && $row_key !== '' && !empty($children_by_parent[$row_key])) {
                foreach ($children_by_parent[$row_key] as $child) {
                    $child_media = $this->extract_primary_media_url(trim((string) ($child['Media'] ?? '')));
                    if ($child_media !== '') {
                        $media = $child_media;
                        break;
                    }
                }
            }

            $items[] = [
                'title' => $title,
                'description' => trim((string) ($row['Description'] ?? '')),
                'media' => $media,
            ];
        }

        return $items;
    }

    public function extract_primary_media_url($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        if (preg_match('/https?:\/\/[^\s"<>]+/i', $text, $m)) {
            return rtrim((string) $m[0], '.,;!?)');
        }
        return '';
    }

    public function parse_tsv_content($content) {
        $rows = [];
        $row = [];
        $field = '';
        $in_quotes = false;
        $len = strlen((string) $content);

        for ($i = 0; $i < $len; $i++) {
            $ch = $content[$i];
            if ($in_quotes) {
                if ($ch === '"') {
                    if ($i + 1 < $len && $content[$i + 1] === '"') {
                        $field .= '"';
                        $i++;
                    } else {
                        $in_quotes = false;
                    }
                } else {
                    $field .= $ch;
                }
            } elseif ($ch === '"') {
                $in_quotes = true;
            } elseif ($ch === "\t") {
                $row[] = $field;
                $field = '';
            } elseif ($ch === "\n") {
                $row[] = $field;
                $rows[] = $row;
                $row = [];
                $field = '';
            } elseif ($ch !== "\r") {
                $field .= $ch;
            }
        }

        if ($field !== '' || !empty($row)) {
            $row[] = $field;
            $rows[] = $row;
        }

        return $rows;
    }

    public function shorten_text($text, $limit) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $limit) {
                return $text;
            }
            return rtrim(mb_substr($text, 0, max(1, $limit - 1), 'UTF-8')) . '...';
        }
        if (strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(substr($text, 0, max(1, $limit - 1))) . '...';
    }

    public function limit_chat_response_length($text) {
        $raw_limit = (string) flosc_get_setting('ai_max_response_length', '');
        $numeric = preg_replace('/[^0-9]/', '', $raw_limit);
        $max = intval($numeric);
        if ($max < 240 || $max > 4000) {
            $max = 900;
        }
        return $this->shorten_text($text, $max);
    }

}
