<?php
/**
 * SOL-1 comment-only WPCS normalizer.
 *
 * This tool never changes executable PHP tokens. It repairs only comment and
 * surrounding indentation trivia introduced or exposed by the SOL-1 PHPDoc
 * pass: docblock indentation, tag spacing/alignment, WordPress capitalization,
 * sentence punctuation, class-property comments, and multiline block-comment
 * shape. A before/after executable-token fingerprint aborts the write on any
 * semantic change.
 *
 * @package FLOSC_Remediation
 */

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "usage: php sol1-comment-clean.php FILE.php [FILE.php ...]\n");
    exit(2);
}

function sol1_exec_fingerprint(string $code): string {
    $parts = array();
    foreach (token_get_all($code) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            $parts[] = token_name($token[0]) . ':' . $token[1];
        } else {
            $parts[] = 'C:' . $token;
        }
    }
    return hash('sha256', implode("\0", $parts));
}

function sol1_line_start(string $code, int $offset): int {
    $p = strrpos(substr($code, 0, $offset), "\n");
    return false === $p ? 0 : $p + 1;
}

function sol1_indent_at(string $code, int $offset): string {
    $start = sol1_line_start($code, $offset);
    preg_match('/^[\t ]*/', substr($code, $start, max(0, $offset - $start)), $m);
    return $m[0] ?? '';
}

function sol1_sentence(string $text): string {
    $text = trim($text);
    if ('' === $text) {
        return $text;
    }
    $text = preg_replace('/\bwordpress\b/i', 'WordPress', $text) ?? $text;
    if (preg_match('/^[a-z]/', $text)) {
        $text = strtoupper($text[0]) . substr($text, 1);
    }
    if (!preg_match('/[.!?:;`\)\]]$/', $text)) {
        $text .= '.';
    }
    return $text;
}

/**
 * Normalize a PHPDoc token using the indentation of the declaration it documents.
 */
function sol1_docblock(string $doc, string $indent): string {
    $raw = preg_split('/\R/', $doc) ?: array($doc);
    $body = array();
    foreach ($raw as $i => $line) {
        if (0 === $i || count($raw) - 1 === $i) {
            continue;
        }
        $line = preg_replace('/^\s*\*\s?/', '', $line) ?? $line;
        $body[] = rtrim($line);
    }

    while ($body && '' === trim($body[0])) {
        array_shift($body);
    }
    while ($body && '' === trim($body[count($body) - 1])) {
        array_pop($body);
    }

    // Capitalize WordPress correctly and punctuate prose lines. Preserve code,
    // URLs, identifiers, and tag continuations rather than pretending they are
    // English sentences.
    foreach ($body as $i => $line) {
        $trim = trim($line);
        $line = preg_replace('/\bwordpress\b/i', 'WordPress', $line) ?? $line;
        if ('' !== $trim && '@' !== $trim[0] && !preg_match('#^(https?://|[A-Za-z0-9_\\\\:-]+\(\)|[`<])#', $trim)) {
            $line = sol1_sentence($line);
        }
        $body[$i] = $line;
    }

    // Exactly one empty PHPDoc line before the first tag when prose exists.
    $first_tag = null;
    foreach ($body as $i => $line) {
        if (str_starts_with(ltrim($line), '@')) {
            $first_tag = $i;
            break;
        }
    }
    if (null !== $first_tag && $first_tag > 0) {
        while ($first_tag > 0 && '' === trim($body[$first_tag - 1])) {
            array_splice($body, $first_tag - 1, 1);
            --$first_tag;
        }
        array_splice($body, $first_tag, 0, array(''));
    }

    // Align contiguous @param tags to the longest type and variable columns,
    // while ensuring each description is a complete sentence.
    $param_indexes = array();
    $params = array();
    foreach ($body as $i => $line) {
        if (preg_match('/^\s*@param\s+(\S+)\s+(\$[A-Za-z_][A-Za-z0-9_]*)\s*(.*)$/', trim($line), $m)) {
            $param_indexes[] = $i;
            $params[] = array($m[1], $m[2], sol1_sentence($m[3] ?: 'Value consumed by this operation.'));
        }
    }
    if ($params) {
        $type_width = max(array_map(static fn(array $p): int => strlen($p[0]), $params));
        $name_width = max(array_map(static fn(array $p): int => strlen($p[1]), $params));
        foreach ($params as $n => $p) {
            $body[$param_indexes[$n]] = sprintf('@param %-'.$type_width.'s %-'.$name_width.'s %s', $p[0], $p[1], $p[2]);
        }
    }

    foreach ($body as $i => $line) {
        $trim = trim($line);
        if (preg_match('/^@(return|throws|throw)\b(.*)$/', $trim, $m)) {
            $tail = trim($m[2]);
            if ('' !== $tail) {
                $body[$i] = '@' . $m[1] . ' ' . sol1_sentence($tail);
            }
        }
    }

    $out = array($indent . '/**');
    foreach ($body as $line) {
        $out[] = $indent . ' *' . ('' === trim($line) ? '' : ' ' . ltrim($line));
    }
    $out[] = $indent . ' */';
    return implode("\n", $out);
}

function sol1_property_description(string $name): string {
    $n = ltrim($name, '$');
    if (str_contains($n, 'flow')) {
        return 'Flow-scoped state retained by this object between related operations.';
    }
    if (str_contains($n, 'provider')) {
        return 'Provider collaborator used when this object dispatches the corresponding integration.';
    }
    if (str_contains($n, 'logger') || str_contains($n, 'log')) {
        return 'Logging collaborator used to record this component\'s operational events.';
    }
    if (str_contains($n, 'filesystem') || str_contains($n, 'fs')) {
        return 'Filesystem collaborator used for WordPress-managed reads and writes.';
    }
    if (str_contains($n, 'cache')) {
        return 'Cached state retained to avoid repeating the same resolution work during one request.';
    }
    if (str_contains($n, 'token')) {
        return 'Token-related state retained for authentication or request correlation.';
    }
    if (str_contains($n, 'session')) {
        return 'Session-scoped state retained while this object serves the current visitor.';
    }
    if (str_contains($n, 'manager')) {
        return 'Manager collaborator that owns the related subsystem behavior delegated by this class.';
    }
    if (str_contains($n, 'config') || str_contains($n, 'settings')) {
        return 'Configuration state used to control this class\'s behavior.';
    }
    return 'Internal state retained by this class for the operations that consume this property.';
}

function sol1_clean_file(string $path): bool {
    $code = file_get_contents($path);
    if (false === $code) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $before = sol1_exec_fingerprint($code);
    $tokens = token_get_all($code);
    $items = array();
    $offset = 0;
    foreach ($tokens as $idx => $token) {
        $id = is_array($token) ? $token[0] : null;
        $text = is_array($token) ? $token[1] : $token;
        $items[] = array('id' => $id, 'text' => $text, 'start' => $offset, 'end' => $offset + strlen($text), 'idx' => $idx);
        $offset += strlen($text);
    }

    $replacements = array();
    $count = count($items);
    for ($i = 0; $i < $count; ++$i) {
        if (T_DOC_COMMENT !== $items[$i]['id']) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && in_array($items[$j]['id'], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
            ++$j;
        }
        $decl_indent = $j < $count ? sol1_indent_at($code, $items[$j]['start']) : sol1_indent_at($code, $items[$i]['start']);
        $line_start = sol1_line_start($code, $items[$i]['start']);
        $prefix = substr($code, $line_start, $items[$i]['start'] - $line_start);
        if ('' !== trim($prefix)) {
            // Inline PHPDoc is unusual and should not be moved by this normalizer.
            $line_start = $items[$i]['start'];
            $decl_indent = '';
        }
        $new = sol1_docblock($items[$i]['text'], $decl_indent);
        $replacements[] = array($line_start, $items[$i]['end'], $new);
    }

    // Punctuate standalone // comments only when they are prose. Parsed
    // directives and code-like comments remain byte-identical.
    foreach ($items as $item) {
        if (T_COMMENT !== $item['id'] || !str_starts_with(ltrim($item['text']), '//')) {
            continue;
        }
        $raw = $item['text'];
        $trim = trim(substr(ltrim($raw), 2));
        if ('' === $trim || preg_match('/^(phpcs|phpstan|translators|noinspection|@|https?:\/\/|[A-Za-z0-9_$]+\s*[=({\[])/i', $trim)) {
            continue;
        }
        if (!preg_match('/[.!?:;`\)\]]$/', $trim)) {
            $lead = substr($raw, 0, strpos($raw, '//') + 2);
            $replacements[] = array($item['start'], $item['end'], $lead . ' ' . sol1_sentence($trim));
        }
    }

    if (!$replacements) {
        return false;
    }
    usort($replacements, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
    foreach ($replacements as $r) {
        $code = substr($code, 0, $r[0]) . $r[2] . substr($code, $r[1]);
    }

    $after = sol1_exec_fingerprint($code);
    if ($before !== $after) {
        throw new RuntimeException('Executable-token mismatch: ' . $path);
    }
    file_put_contents($path, $code);
    return true;
}

$changed = 0;
foreach (array_slice($argv, 1) as $path) {
    if (!is_file($path) || '.php' !== strtolower(substr($path, -4))) {
        fwrite(STDERR, "SKIP $path\n");
        continue;
    }
    if (sol1_clean_file($path)) {
        ++$changed;
        echo "COMMENT_CLEANED $path\n";
    } else {
        echo "COMMENT_UNCHANGED $path\n";
    }
}
echo "COMMENT_CLEAN_SUMMARY changed=$changed\n";
