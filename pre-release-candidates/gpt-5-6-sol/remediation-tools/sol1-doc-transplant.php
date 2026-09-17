<?php
/**
 * SOL-1 documentation transplant.
 *
 * Copies or merges PHPDoc only when the target declaration and the reference
 * declaration have identical executable-token fingerprints. The reference is
 * the Codex v68 candidate, which measured clean under the WordPress rulesets.
 * No executable token is rewritten by this tool.
 *
 * @package FLOSC_Remediation
 */

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "usage: php sol1-doc-transplant.php TARGET_ROOT REFERENCE_ROOT\n");
    exit(2);
}

$targetRoot = rtrim($argv[1], '/');
$sourceRoot = rtrim($argv[2], '/');

if (!is_dir($targetRoot) || !is_dir($sourceRoot)) {
    fwrite(STDERR, "target/reference root missing\n");
    exit(2);
}

/** @return list<string> */
function php_files(string $root): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') {
            continue;
        }
        $path = $f->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $out[] = $path;
    }
    sort($out, SORT_STRING);
    return $out;
}

/**
 * Build byte offsets for token_get_all() output.
 *
 * @return list<array{id:int|null,text:string,start:int,end:int,line:int}>
 */
function tokens_with_offsets(string $code): array {
    $raw = token_get_all($code);
    $out = [];
    $offset = 0;
    $line = 1;
    foreach ($raw as $tok) {
        if (is_array($tok)) {
            [$id, $text, $tokLine] = $tok;
            $line = $tokLine;
        } else {
            $id = null;
            $text = $tok;
            $tokLine = $line;
        }
        $start = $offset;
        $offset += strlen($text);
        $out[] = ['id' => $id, 'text' => $text, 'start' => $start, 'end' => $offset, 'line' => $tokLine];
        $line += substr_count($text, "\n");
    }
    return $out;
}

function significant(array $tok): bool {
    return !in_array($tok['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function executable_fingerprint(string $code): string {
    $parts = [];
    foreach (token_get_all($code) as $tok) {
        if (is_array($tok)) {
            if (in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $parts[] = token_name($tok[0]) . ':' . $tok[1];
        } else {
            $parts[] = 'C:' . $tok;
        }
    }
    return hash('sha256', implode("\0", $parts));
}

/**
 * Fingerprint a token slice while ignoring comments and whitespace.
 *
 * @param list<array{id:int|null,text:string,start:int,end:int,line:int}> $tokens
 */
function slice_fingerprint(array $tokens, int $a, int $b): string {
    $parts = [];
    for ($i = $a; $i <= $b; $i++) {
        $t = $tokens[$i];
        if (!$t || !significant($t)) {
            continue;
        }
        $parts[] = ($t['id'] === null ? 'C' : token_name($t['id'])) . ':' . $t['text'];
    }
    return hash('sha256', implode("\0", $parts));
}

/**
 * Find the closest docblock belonging to a declaration.
 *
 * @param list<array{id:int|null,text:string,start:int,end:int,line:int}> $tokens
 * @return array{index:int,start:int,end:int,text:string}|null
 */
function preceding_doc(array $tokens, int $declIndex): ?array {
    $modifiers = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT];
    if (defined('T_READONLY')) {
        $modifiers[] = constant('T_READONLY');
    }
    for ($i = $declIndex - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if ($t['id'] === T_WHITESPACE) {
            continue;
        }
        if ($t['id'] === T_DOC_COMMENT) {
            return ['index' => $i, 'start' => $t['start'], 'end' => $t['end'], 'text' => $t['text']];
        }
        if ($t['id'] !== null && in_array($t['id'], $modifiers, true)) {
            continue;
        }
        return null;
    }
    return null;
}

/**
 * Find the first modifier token belonging to a declaration.
 *
 * @param list<array{id:int|null,text:string,start:int,end:int,line:int}> $tokens
 */
function declaration_start(array $tokens, int $index): int {
    $modifiers = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT];
    if (defined('T_READONLY')) {
        $modifiers[] = constant('T_READONLY');
    }
    $start = $index;
    for ($i = $index - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if ($t['id'] === T_WHITESPACE) {
            continue;
        }
        if ($t['id'] !== null && in_array($t['id'], $modifiers, true)) {
            $start = $i;
            continue;
        }
        break;
    }
    return $start;
}

/**
 * Find the end token for a function/class body or declaration statement.
 *
 * @param list<array{id:int|null,text:string,start:int,end:int,line:int}> $tokens
 */
function declaration_end(array $tokens, int $start): int {
    $brace = 0;
    $seenBrace = false;
    $paren = 0;
    $bracket = 0;
    $n = count($tokens);
    for ($i = $start; $i < $n; $i++) {
        $text = $tokens[$i]['text'];
        if ($tokens[$i]['id'] !== null && in_array($tokens[$i]['id'], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) {
            continue;
        }
        if ($text === '(') { $paren++; }
        elseif ($text === ')') { $paren--; }
        elseif ($text === '[') { $bracket++; }
        elseif ($text === ']') { $bracket--; }
        elseif ($text === '{') { $brace++; $seenBrace = true; }
        elseif ($text === '}') {
            $brace--;
            if ($seenBrace && $brace === 0) {
                return $i;
            }
        } elseif ($text === ';' && !$seenBrace && $paren === 0 && $bracket === 0) {
            return $i;
        }
    }
    return $n - 1;
}

/**
 * Parse named declarations and their executable fingerprints.
 *
 * @return array<string,array{key:string,kind:string,index:int,decl_start:int,decl_end:int,insert:int,doc:?array,fingerprint:string}>
 */
function declarations(string $code): array {
    $tokens = tokens_with_offsets($code);
    $out = [];
    $classStack = [];
    $pendingClass = null;
    $braceDepth = 0;
    $functionDepths = [];
    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        $id = $t['id'];

        if (in_array($id, [T_CLASS, T_TRAIT, T_INTERFACE], true) || (defined('T_ENUM') && $id === constant('T_ENUM'))) {
            $j = $i + 1;
            while ($j < $n && !($tokens[$j]['id'] === T_STRING)) { $j++; }
            if ($j < $n) {
                $name = $tokens[$j]['text'];
                $kind = $id === T_CLASS ? 'class' : ($id === T_TRAIT ? 'trait' : ($id === T_INTERFACE ? 'interface' : 'enum'));
                $ds = declaration_start($tokens, $i);
                $de = declaration_end($tokens, $i);
                $doc = preceding_doc($tokens, $ds);
                $key = $kind . ':' . $name;
                $out[$key] = [
                    'key' => $key, 'kind' => $kind, 'index' => $i,
                    'decl_start' => $ds, 'decl_end' => $de,
                    'insert' => $tokens[$ds]['start'], 'doc' => $doc,
                    'fingerprint' => slice_fingerprint($tokens, $i, $de),
                ];
                $pendingClass = $name;
            }
        }

        if ($id === T_FUNCTION) {
            $j = $i + 1;
            while ($j < $n && ($tokens[$j]['id'] === T_WHITESPACE || $tokens[$j]['text'] === '&')) { $j++; }
            if ($j < $n && $tokens[$j]['id'] === T_STRING) {
                $name = $tokens[$j]['text'];
                $class = $classStack ? $classStack[array_key_last($classStack)]['name'] : null;
                $kind = $class ? 'method' : 'function';
                $key = $class ? $class . '::' . $name : 'function:' . $name;
                $ds = declaration_start($tokens, $i);
                $de = declaration_end($tokens, $i);
                $doc = preceding_doc($tokens, $ds);
                $out[$key] = [
                    'key' => $key, 'kind' => $kind, 'index' => $i,
                    'decl_start' => $ds, 'decl_end' => $de,
                    'insert' => $tokens[$ds]['start'], 'doc' => $doc,
                    'fingerprint' => slice_fingerprint($tokens, $i, $de),
                ];
                // Function body depth is discovered when its opening brace arrives.
                $functionDepths[] = ['end_index' => $de, 'start_index' => $i];
            }
        }

        // Class properties and constants: only at class body depth and outside methods.
        if ($classStack) {
            $classInfo = $classStack[array_key_last($classStack)];
            $insideMethod = false;
            foreach ($functionDepths as $fd) {
                if ($i > $fd['start_index'] && $i <= $fd['end_index']) {
                    $insideMethod = true;
                    break;
                }
            }
            if (!$insideMethod && $braceDepth === $classInfo['body_depth']) {
                if ($id === T_VARIABLE) {
                    $class = $classInfo['name'];
                    $key = $class . '::$' . ltrim($t['text'], '$');
                    $ds = declaration_start($tokens, $i);
                    $de = declaration_end($tokens, $ds);
                    $doc = preceding_doc($tokens, $ds);
                    $out[$key] = [
                        'key' => $key, 'kind' => 'property', 'index' => $i,
                        'decl_start' => $ds, 'decl_end' => $de,
                        'insert' => $tokens[$ds]['start'], 'doc' => $doc,
                        'fingerprint' => slice_fingerprint($tokens, $ds, $de),
                    ];
                } elseif ($id === T_CONST) {
                    $j = $i + 1;
                    while ($j < $n && $tokens[$j]['id'] === T_WHITESPACE) { $j++; }
                    if ($j < $n && $tokens[$j]['id'] === T_STRING) {
                        $class = $classInfo['name'];
                        $key = $class . '::const:' . $tokens[$j]['text'];
                        $ds = declaration_start($tokens, $i);
                        $de = declaration_end($tokens, $ds);
                        $doc = preceding_doc($tokens, $ds);
                        $out[$key] = [
                            'key' => $key, 'kind' => 'const', 'index' => $i,
                            'decl_start' => $ds, 'decl_end' => $de,
                            'insert' => $tokens[$ds]['start'], 'doc' => $doc,
                            'fingerprint' => slice_fingerprint($tokens, $ds, $de),
                        ];
                    }
                }
            }
        }

        if ($t['text'] === '{') {
            $braceDepth++;
            if ($pendingClass !== null) {
                $classStack[] = ['name' => $pendingClass, 'body_depth' => $braceDepth];
                $pendingClass = null;
            }
        } elseif ($t['text'] === '}') {
            if ($classStack && $braceDepth === $classStack[array_key_last($classStack)]['body_depth']) {
                array_pop($classStack);
            }
            $braceDepth--;
        }
    }

    return $out;
}

function line_indent(string $code, int $offset): string {
    $lineStart = strrpos(substr($code, 0, $offset), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;
    $prefix = substr($code, $lineStart, $offset - $lineStart);
    return preg_match('/^[\t ]*/', $prefix, $m) ? $m[0] : '';
}

function normalize_doc_indent(string $doc, string $indent): string {
    $lines = preg_split('/\R/', $doc) ?: [$doc];
    $trimmed = [];
    foreach ($lines as $line) {
        $trimmed[] = ltrim($line, "\t ");
    }
    return implode("\n", array_map(static fn($line) => $indent . $line, $trimmed));
}

function strip_since_if_forbidden(string $doc, bool $fileAlreadyUsesSince): string {
    if ($fileAlreadyUsesSince) {
        return $doc;
    }
    $lines = preg_split('/\R/', $doc) ?: [$doc];
    $lines = array_values(array_filter($lines, static fn($line) => !preg_match('/^\s*\*\s*@since\b/', $line)));
    return implode("\n", $lines);
}

/** @return array<string,string> */
function param_tags(string $doc): array {
    $out = [];
    if (preg_match_all('/^\s*\*\s*@param\s+\S+\s+(?:&\s*)?(?:\.\.\.\s*)?(\$[A-Za-z_][A-Za-z0-9_]*)\s*(.*)$/m', $doc, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $out[$row[1]] = trim($row[0]);
        }
    }
    return $out;
}

function has_return_tag(string $doc): bool {
    return (bool) preg_match('/^\s*\*\s*@return\b/m', $doc);
}

function has_var_tag(string $doc): bool {
    return (bool) preg_match('/^\s*\*\s*@var\b/m', $doc);
}

function tag_description_is_useful(string $line): bool {
    if (!preg_match('/@param\s+\S+\s+(?:&\s*)?(?:\.\.\.\s*)?\$[A-Za-z_][A-Za-z0-9_]*\s+(.+)$/', $line, $m)) {
        return false;
    }
    $desc = trim($m[1]);
    return strlen($desc) >= 8 && !preg_match('/^(The )?(value|parameter|argument|key|id|name)\.?$/i', $desc);
}

function merge_docblocks(string $target, string $source, bool $fileAlreadyUsesSince): string {
    $source = strip_since_if_forbidden($source, $fileAlreadyUsesSince);
    if (str_contains($source, 'phpcs:')) {
        return $target;
    }

    $targetParams = param_tags($target);
    $sourceParams = param_tags($source);
    $append = [];

    foreach ($sourceParams as $var => $line) {
        if (!isset($targetParams[$var])) {
            $append[] = $line;
        } elseif (!tag_description_is_useful($targetParams[$var]) && tag_description_is_useful($line)) {
            $target = str_replace($targetParams[$var], $line, $target);
        }
    }

    if (!has_return_tag($target) && has_return_tag($source)) {
        if (preg_match('/^\s*\*\s*@return\b.*$/m', $source, $m)) {
            $append[] = trim($m[0]);
        }
    }
    if (!has_var_tag($target) && has_var_tag($source)) {
        if (preg_match('/^\s*\*\s*@var\b.*$/m', $source, $m)) {
            $append[] = trim($m[0]);
        }
    }

    if (!$append) {
        return $target;
    }
    $pos = strrpos($target, '*/');
    if ($pos === false) {
        return $target;
    }
    $head = rtrim(substr($target, 0, $pos));
    foreach ($append as $line) {
        $line = preg_replace('/^\s*\*/', '*', $line) ?? $line;
        $head .= "\n " . $line;
    }
    return $head . "\n */";
}

function doc_score(string $doc): int {
    $score = 0;
    $score += substr_count($doc, '@param') * 10;
    $score += substr_count($doc, '@return') * 6;
    $score += substr_count($doc, '@var') * 6;
    $plain = preg_replace('/[\/*@\-]/', ' ', $doc) ?? $doc;
    $score += min(50, str_word_count($plain));
    return $score;
}

$totalFiles = 0;
$changedFiles = 0;
$inserted = 0;
$merged = 0;
$skippedFingerprint = 0;
$skippedNoReference = 0;

foreach (php_files($targetRoot) as $targetPath) {
    $rel = ltrim(substr($targetPath, strlen($targetRoot)), DIRECTORY_SEPARATOR);
    $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . $rel;
    if (!is_file($sourcePath)) {
        continue;
    }
    $totalFiles++;
    $target = file_get_contents($targetPath);
    $source = file_get_contents($sourcePath);
    if ($target === false || $source === false) {
        continue;
    }
    $targetDecl = declarations($target);
    $sourceDecl = declarations($source);
    $fileAlreadyUsesSince = str_contains($target, '@since');
    $replacements = [];

    foreach ($targetDecl as $key => $td) {
        if (!isset($sourceDecl[$key])) {
            $skippedNoReference++;
            continue;
        }
        $sd = $sourceDecl[$key];
        if ($td['fingerprint'] !== $sd['fingerprint']) {
            $skippedFingerprint++;
            continue;
        }
        if ($sd['doc'] === null || str_contains($sd['doc']['text'], 'phpcs:')) {
            continue;
        }
        $indent = line_indent($target, $td['insert']);
        $sourceDoc = normalize_doc_indent(strip_since_if_forbidden($sd['doc']['text'], $fileAlreadyUsesSince), $indent);
        if ($td['doc'] === null) {
            if (doc_score($sourceDoc) < 8) {
                continue;
            }
            $replacements[] = [$td['insert'], $td['insert'], $sourceDoc . "\n"];
            $inserted++;
        } else {
            $targetDoc = $td['doc']['text'];
            $mergedDoc = merge_docblocks($targetDoc, $sourceDoc, $fileAlreadyUsesSince);
            if ($mergedDoc !== $targetDoc) {
                $mergedDoc = normalize_doc_indent($mergedDoc, $indent);
                $replacements[] = [$td['doc']['start'], $td['doc']['end'], $mergedDoc];
                $merged++;
            }
        }
    }

    // File header: only transplant if the entire executable token stream matches.
    if (executable_fingerprint($target) === executable_fingerprint($source)) {
        $tt = tokens_with_offsets($target);
        $st = tokens_with_offsets($source);
        $targetHeader = null;
        $sourceHeader = null;
        foreach ($tt as $t) {
            if ($t['id'] === T_OPEN_TAG || $t['id'] === T_WHITESPACE || $t['id'] === T_COMMENT) {
                continue;
            }
            if ($t['id'] === T_DOC_COMMENT) {
                $targetHeader = $t;
            }
            break;
        }
        foreach ($st as $t) {
            if ($t['id'] === T_OPEN_TAG || $t['id'] === T_WHITESPACE || $t['id'] === T_COMMENT) {
                continue;
            }
            if ($t['id'] === T_DOC_COMMENT) {
                $sourceHeader = $t;
            }
            break;
        }
        if ($sourceHeader && !str_contains($sourceHeader['text'], 'phpcs:')) {
            $sd = strip_since_if_forbidden($sourceHeader['text'], $fileAlreadyUsesSince);
            if ($targetHeader === null) {
                $openEnd = strpos($target, "\n");
                if ($openEnd !== false) {
                    $replacements[] = [$openEnd + 1, $openEnd + 1, $sd . "\n"];
                    $inserted++;
                }
            } elseif (doc_score($sd) > doc_score($targetHeader['text']) + 8) {
                $replacements[] = [$targetHeader['start'], $targetHeader['end'], $sd];
                $merged++;
            }
        }
    }

    if (!$replacements) {
        continue;
    }

    usort($replacements, static fn($a, $b) => $b[0] <=> $a[0]);
    $new = $target;
    foreach ($replacements as [$a, $b, $text]) {
        $new = substr($new, 0, $a) . $text . substr($new, $b);
    }

    // Absolute guard: executable token stream must remain byte-identical after normalization.
    if (executable_fingerprint($new) !== executable_fingerprint($target)) {
        fwrite(STDERR, "EXECUTABLE TOKEN CHANGE REFUSED: {$rel}\n");
        exit(1);
    }

    if ($new !== $target) {
        file_put_contents($targetPath, $new);
        $changedFiles++;
        echo "SOL1_DOC_CHANGED {$rel}\n";
    }
}

echo "SOL1_DOC_SUMMARY files_seen={$totalFiles} files_changed={$changedFiles} inserted={$inserted} merged={$merged} skipped_fingerprint={$skippedFingerprint} skipped_no_reference={$skippedNoReference}\n";
