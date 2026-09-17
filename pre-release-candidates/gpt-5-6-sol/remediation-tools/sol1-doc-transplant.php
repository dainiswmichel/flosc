<?php
/**
 * SOL-1 documentation remediation entry point.
 *
 * The reference merge is attempted first only where v85 and the reference have
 * matching signatures and implementation anchors. The second pass derives any
 * remaining missing contracts directly from v85's executable code. Both passes
 * retain a whole-file executable-token guard.
 *
 * @package FLOSC_Remediation
 */

require __DIR__ . '/sol1-doc-transplant-v2.php';

$generator = __DIR__ . '/sol1-generate-from-code.php';
$target    = $argv[1] ?? '';
if ('' !== $target) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($generator) . ' ' . escapeshellarg($target), $generatorExit);
    if (0 !== $generatorExit) {
        exit($generatorExit);
    }
}
