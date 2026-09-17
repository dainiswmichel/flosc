<?php
/**
 * SOL-1 PHPDoc transplant, revision 2.
 *
 * This tool never edits executable tokens. It borrows documentation from the
 * Codex candidate only when a declaration has the same name and exact
 * executable signature in v85, then checks that the implementation still has
 * substantial token overlap before accepting the source contract. Every
 * resulting file is guarded by a whole-file executable-token fingerprint.
 *
 * @package FLOSC_Remediation
 */

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "usage: php sol1-doc-transplant-v2.php TARGET_ROOT REFERENCE_ROOT\n");
    exit(2);
}

$targetRoot = rtrim($argv[1], '/');
$sourceRoot = rtrim($argv[2], '/');

/** @return list<string> */
function sol_php_files(string $root): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $out[] = $path;
    }
    sort($out, SORT_STRING);
    return $out;
}

/** @return list<array{id:int|null,text:string,start:int,end:int}> */
function sol_tokens(string $code): array {
    $out = [];
    $offset = 0;
    foreach (token_get_all($code) as $tok) {
        if (is_array($tok)) {
            $id = $tok[0];
            $text = $tok[1];
        } else {
            $id = null;
            $text = $tok;
        }
        $start = $offset;
        $offset += strlen($text);
        $out[] = ['id' => $id, 'text' => $text, 'start' => $start, 'end' => $offset];
    }
    return $out;
}

function sol_is_trivia(array $tok): bool {
    return in_array($tok['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function sol_exec_fp(string $code): string {
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
 * Locate a declaration's opening brace/semicolon and matching body end.
 *
 * @param list<array{id:int|null,text:string,start:int,end:int}> $toks
 * @return array{head_end:int,body_end:int}
 */
function sol_bounds(array $toks, int $start): array {
    $n = count($toks);
    $paren = 0;
    $bracket = 0;
    $headEnd = $start;
    $bodyEnd = $start;
    for ($i = $start; $i < $n; $i++) {
        if (sol_is_trivia($toks[$i])) {
            continue;
        }
        $x = $toks[$i]['text'];
        if ($x === '(') { $paren++; }
        elseif ($x === ')') { $paren--; }
        elseif ($x === '[') { $bracket++; }
        elseif ($x === ']') { $bracket--; }
        elseif ($paren === 0 && $bracket === 0 && ($x === '{' || $x === ';')) {
            $headEnd = $i;
            if ($x === ';') {
                return ['head_end' => $i, 'body_end' => $i];
            }
            $depth = 1;
            for ($j = $i + 1; $j < $n; $j++) {
                if (sol_is_trivia($toks[$j])) {
                    continue;
                }
                if ($toks[$j]['text'] === '{') { $depth++; }
                elseif ($toks[$j]['text'] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return ['head_end' => $headEnd, 'body_end' => $j];
                    }
                }
            }
            return ['head_end' => $headEnd, 'body_end' => $n - 1];
        }
    }
    return ['head_end' => $headEnd, 'body_end' => $bodyEnd];
}

/** @param list<array{id:int|null,text:string,start:int,end:int}> $toks */
function sol_signature(array $toks, int $a, int $b): string {
    $parts = [];
    for ($i = $a; $i <= $b; $i++) {
        if (sol_is_trivia($toks[$i])) {
            continue;
        }
        $id = $toks[$i]['id'];
        $parts[] = ($id === null ? 'C' : token_name($id)) . ':' . $toks[$i]['text'];
    }
    return hash('sha256', implode("\0", $parts));
}

/**
 * Extract implementation anchors for conservative similarity testing.
 *
 * @param list<array{id:int|null,text:string,start:int,end:int}> $toks
 * @return array<string,true>
 */
function sol_anchors(array $toks, int $a, int $b): array {
    $set = [];
    $interesting = [T_STRING, T_VARIABLE, T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER];
    for ($i = $a; $i <= $b; $i++) {
        $id = $toks[$i]['id'];
        if ($id !== null && in_array($id, $interesting, true)) {
            $text = strtolower($toks[$i]['text']);
            if (strlen($text) > 2 && !in_array($text, ['$this', 'true', 'false', 'null'], true)) {
                $set[$text] = true;
            }
        }
    }
    return $set;
}

function sol_similarity(array $a, array $b): float {
    if (!$a && !$b) {
        return 1.0;
    }
    if (!$a || !$b) {
        return 0.0;
    }
    $intersection = count(array_intersect_key($a, $b));
    $union = count($a + $b);
    return $union ? $intersection / $union : 0.0;
}

/**
 * Find declaration prefix start and any immediately associated docblock.
 *
 * @param list<array{id:int|null,text:string,start:int,end:int}> $toks
 * @return array{decl_start:int,doc:?array{start:int,end:int,text:string}}
 */
function sol_prefix(array $toks, int $index): array {
    $mods = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT];
    if (defined('T_READONLY')) {
        $mods[] = constant('T_READONLY');
    }
    $declStart = $index;
    $i = $index - 1;
    while ($i >= 0) {
        if ($toks[$i]['id'] === T_WHITESPACE) {
            $i--;
            continue;
        }
        if ($toks[$i]['id'] !== null && in_array($toks[$i]['id'], $mods, true)) {
            $declStart = $i;
            $i--;
            continue;
        }
        break;
    }
    while ($i >= 0 && $toks[$i]['id'] === T_WHITESPACE) {
        $i--;
    }
    $doc = null;
    if ($i >= 0 && $toks[$i]['id'] === T_DOC_COMMENT) {
        $doc = ['start' => $toks[$i]['start'], 'end' => $toks[$i]['end'], 'text' => $toks[$i]['text']];
    }
    return ['decl_start' => $declStart, 'doc' => $doc];
}

/**
 * Read classes/functions/methods without trying to infer properties.
 *
 * @return array<string,array{key:string,kind:string,start:int,head_end:int,body_end:int,insert:int,doc:?array,signature:string,anchors:array}>
 */
function sol_declarations(string $code): array {
    $toks = sol_tokens($code);
    $out = [];
    $classStack = [];
    $pendingClass = null;
    $braceDepth = 0;
    $n = count($toks);

    for ($i = 0; $i < $n; $i++) {
        $id = $toks[$i]['id'];
        $text = $toks[$i]['text'];

        $classTokens = [T_CLASS, T_TRAIT, T_INTERFACE];
        if (defined('T_ENUM')) { $classTokens[] = constant('T_ENUM'); }
        if ($id !== null && in_array($id, $classTokens, true)) {
            // Exclude ::class.
            $prev = $i - 1;
            while ($prev >= 0 && $toks[$prev]['id'] === T_WHITESPACE) { $prev--; }
            if ($prev >= 0 && $toks[$prev]['id'] === T_DOUBLE_COLON) {
                continue;
            }
            $j = $i + 1;
            while ($j < $n && $toks[$j]['id'] !== T_STRING) { $j++; }
            if ($j < $n) {
                $name = $toks[$j]['text'];
                $bounds = sol_bounds($toks, $i);
                $prefix = sol_prefix($toks, $i);
                $kind = $id === T_CLASS ? 'class' : ($id === T_TRAIT ? 'trait' : ($id === T_INTERFACE ? 'interface' : 'enum'));
                $key = $kind . ':' . $name;
                $out[$key] = [
                    'key'=>$key,'kind'=>$kind,'start'=>$i,'head_end'=>$bounds['head_end'],'body_end'=>$bounds['body_end'],
                    'insert'=>$toks[$prefix['decl_start']]['start'],'doc'=>$prefix['doc'],
                    'signature'=>sol_signature($toks,$i,$bounds['head_end']),
                    'anchors'=>sol_anchors($toks,$bounds['head_end']+1,$bounds['body_end']-1),
                ];
                $pendingClass = ['name'=>$name,'open_index'=>$bounds['head_end']];
            }
        }

        if ($id === T_FUNCTION) {
            $j = $i + 1;
            while ($j < $n && ($toks[$j]['id'] === T_WHITESPACE || $toks[$j]['text'] === '&')) { $j++; }
            if ($j < $n && $toks[$j]['id'] === T_STRING) {
                $name = $toks[$j]['text'];
                $class = $classStack ? $classStack[array_key_last($classStack)]['name'] : null;
                $key = $class ? $class . '::' . $name : 'function:' . $name;
                $bounds = sol_bounds($toks, $i);
                $prefix = sol_prefix($toks, $i);
                $out[$key] = [
                    'key'=>$key,'kind'=>$class?'method':'function','start'=>$i,'head_end'=>$bounds['head_end'],'body_end'=>$bounds['body_end'],
                    'insert'=>$toks[$prefix['decl_start']]['start'],'doc'=>$prefix['doc'],
                    'signature'=>sol_signature($toks,$i,$bounds['head_end']),
                    'anchors'=>sol_anchors($toks,$bounds['head_end']+1,$bounds['body_end']-1),
                ];
            }
        }

        if ($text === '{') {
            $braceDepth++;
            if ($pendingClass !== null && $pendingClass['open_index'] === $i) {
                $classStack[] = ['name'=>$pendingClass['name'],'depth'=>$braceDepth];
                $pendingClass = null;
            }
        } elseif ($text === '}') {
            if ($classStack && $classStack[array_key_last($classStack)]['depth'] === $braceDepth) {
                array_pop($classStack);
            }
            $braceDepth--;
        }
    }
    return $out;
}

function sol_indent(string $code, int $offset): string {
    $before = substr($code, 0, $offset);
    $p = strrpos($before, "\n");
    $start = $p === false ? 0 : $p + 1;
    $prefix = substr($code, $start, $offset - $start);
    preg_match('/^[\t ]*/', $prefix, $m);
    return $m[0] ?? '';
}

function sol_reindent(string $doc, string $indent): string {
    $lines = preg_split('/\R/', $doc) ?: [$doc];
    return implode("\n", array_map(static fn($line) => $indent . ltrim($line, "\t "), $lines));
}

function sol_strip_since(string $doc, bool $allowed): string {
    if ($allowed) { return $doc; }
    $lines = preg_split('/\R/', $doc) ?: [$doc];
    $lines = array_values(array_filter($lines, static fn($line) => !preg_match('/^\s*\*\s*@since\b/', $line)));
    return implode("\n", $lines);
}

/** @return array<string,string> */
function sol_params(string $doc): array {
    $out = [];
    if (preg_match_all('/^\s*\*\s*@param\s+\S+\s+(?:&\s*)?(?:\.\.\.\s*)?(\$[A-Za-z_][A-Za-z0-9_]*)\s*(.*)$/m', $doc, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) { $out[$row[1]] = trim($row[0]); }
    }
    return $out;
}

function sol_useful_param(string $line): bool {
    if (!preg_match('/@param\s+\S+\s+(?:&\s*)?(?:\.\.\.\s*)?\$[A-Za-z_][A-Za-z0-9_]*\s+(.+)$/', $line, $m)) { return false; }
    $d = trim($m[1]);
    return strlen($d) >= 12 && !preg_match('/^(The )?(value|parameter|argument|key|id|name|input)\.?$/i', $d);
}

function sol_merge(string $target, string $source, bool $sinceAllowed): string {
    $source = sol_strip_since($source, $sinceAllowed);
    if (str_contains($source, 'phpcs:')) { return $target; }
    $tp = sol_params($target);
    $sp = sol_params($source);
    $append = [];
    foreach ($sp as $name=>$line) {
        if (!isset($tp[$name])) {
            $append[] = $line;
        } elseif (!sol_useful_param($tp[$name]) && sol_useful_param($line)) {
            $target = str_replace($tp[$name], $line, $target);
        }
    }
    foreach (['return','throws','var'] as $tag) {
        if (!preg_match('/^\s*\*\s*@'.preg_quote($tag,'/').'\b/m', $target)
            && preg_match('/^\s*\*\s*@'.preg_quote($tag,'/').'\b.*$/m', $source, $m)) {
            $append[] = trim($m[0]);
        }
    }
    if (!$append) { return $target; }
    $p = strrpos($target, '*/');
    if ($p === false) { return $target; }
    $head = rtrim(substr($target, 0, $p));
    foreach ($append as $line) {
        $line = preg_replace('/^\s*\*/', '*', $line) ?? $line;
        $head .= "\n " . $line;
    }
    return $head . "\n */";
}

function sol_doc_quality(string $doc): int {
    $plain = preg_replace('/[@*\/\-]/', ' ', $doc) ?? $doc;
    return substr_count($doc,'@param')*12 + substr_count($doc,'@return')*8 + min(80,str_word_count($plain));
}

$total=0;$changed=0;$inserted=0;$merged=0;$sigSkip=0;$simSkip=0;$noRef=0;
foreach (sol_php_files($targetRoot) as $targetPath) {
    $rel = ltrim(substr($targetPath, strlen($targetRoot)), DIRECTORY_SEPARATOR);
    $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . $rel;
    if (!is_file($sourcePath)) { continue; }
    $total++;
    $target = file_get_contents($targetPath);
    $source = file_get_contents($sourcePath);
    if ($target === false || $source === false) { continue; }
    $beforeFp = sol_exec_fp($target);
    $tds = sol_declarations($target);
    $sds = sol_declarations($source);
    $sinceAllowed = str_contains($target, '@since');
    $repl = [];

    foreach ($tds as $key=>$td) {
        if (!isset($sds[$key])) { $noRef++; continue; }
        $sd = $sds[$key];
        if ($td['signature'] !== $sd['signature']) { $sigSkip++; continue; }
        $similarity = sol_similarity($td['anchors'], $sd['anchors']);
        $threshold = in_array($td['kind'], ['class','trait','interface','enum'], true) ? 0.35 : 0.50;
        if ($similarity < $threshold) { $simSkip++; continue; }
        if ($sd['doc'] === null || str_contains($sd['doc']['text'],'phpcs:')) { continue; }
        $indent = sol_indent($target, $td['insert']);
        $sourceDoc = sol_reindent(sol_strip_since($sd['doc']['text'],$sinceAllowed),$indent);
        if ($td['doc'] === null) {
            if (sol_doc_quality($sourceDoc) < 15) { continue; }
            $repl[] = [$td['insert'],$td['insert'],$sourceDoc."\n"];
            $inserted++;
        } else {
            $newDoc = sol_merge($td['doc']['text'],$sourceDoc,$sinceAllowed);
            if ($newDoc !== $td['doc']['text']) {
                $repl[] = [$td['doc']['start'],$td['doc']['end'],sol_reindent($newDoc,$indent)];
                $merged++;
            }
        }
    }

    if (!$repl) { continue; }
    // No overlapping edits; if two docs resolve to the same span, keep the first.
    $unique=[];
    foreach($repl as $r){$unique[$r[0].':'.$r[1]]=$r;}
    $repl=array_values($unique);
    usort($repl, static fn($a,$b)=>$b[0]<=>$a[0]);
    $new=$target;
    foreach($repl as [$a,$b,$text]){$new=substr($new,0,$a).$text.substr($new,$b);}
    if(sol_exec_fp($new)!==$beforeFp){
        fwrite(STDERR,"EXECUTABLE TOKEN CHANGE REFUSED: {$rel}\n");
        continue;
    }
    if($new!==$target){file_put_contents($targetPath,$new);$changed++;echo "SOL1_DOC_CHANGED {$rel}\n";}
}
echo "SOL1_DOC_SUMMARY files_seen={$total} files_changed={$changed} inserted={$inserted} merged={$merged} signature_skips={$sigSkip} similarity_skips={$simSkip} no_reference={$noRef}\n";
