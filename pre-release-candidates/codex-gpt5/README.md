# FLOSC 8.0.0 — Codex candidate v94.1

Source base: the latest Claude Opus 5 v94 commit available during this pass,
`922882317158cf1a558c1a7ac4cdcd53c2faa269`. The Claude candidate was not
modified. The Codex plugin source differs from it in exactly one file:
`includes/flosc-request.php`. The plugin version remains 8.0.0.

Claude's v94 code had already removed the five file-scope bulk `$_GET` reads.
Its central `flosc_nav_param_keys()` list omitted `catalog` and `da1_export`,
although `admin/da1.php` reads both through the inherited `$flosc_get` array.
This candidate adds those two literal keys. Without them, selecting a DA1
catalog by URL and the nonce-protected DA1 export silently fall back. No POST
read, endpoint, or other runtime file was changed.

## Measured checks

Commands run from `flosc-by-codex-gpt5/flosc/`:

```text
find admin includes -type f -name '*.php' -print0 | xargs -0 grep -HnE '^\$[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=[[:space:]]*wp_unslash[[:space:]]*\([[:space:]]*\$_GET[[:space:]]*\)'
output: none (0 file-scope GET bulk reads)

find admin includes -type f -name '*.php' -print0 | xargs -0 grep -HnE '^\$[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=[[:space:]]*wp_unslash[[:space:]]*\([[:space:]]*\$_POST[[:space:]]*\)'
admin/settings.php:370:$flosc_post = wp_unslash( $_POST );
admin/flow.php:39:$flosc_post                    = wp_unslash( $_POST );

find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l | awk '/^No syntax errors detected/{ok++} !/^No syntax errors detected/{print; fail++} END{printf "PHP lint clean: %d; failures: %d\n", ok, fail}'
PHP lint clean: 195; failures: 0

flosc_pass=0; flosc_fail=0; for flosc_test in tests/check_*.php tests/test_*.php; do if php "$flosc_test" > /tmp/flosc-v94-latest-php-test.log 2>&1; then flosc_pass=$((flosc_pass+1)); else flosc_fail=$((flosc_fail+1)); printf 'FAIL %s\n' "$flosc_test"; fi; done; printf 'PHP tests: %d passed; %d failed\n' "$flosc_pass" "$flosc_fail"
PHP tests: 40 passed; 0 failed

flosc_pass=0; flosc_fail=0; for flosc_test in tests/check_*.js; do if node "$flosc_test" > /tmp/flosc-v94-latest-js-test.log 2>&1; then flosc_pass=$((flosc_pass+1)); else flosc_fail=$((flosc_fail+1)); printf 'FAIL %s\n' "$flosc_test"; fi; done; printf 'JS tests: %d passed; %d failed\n' "$flosc_pass" "$flosc_fail"
JS tests: 2 passed; 0 failed

php -d memory_limit=2G /Users/dainismichel/.composer/vendor/bin/phpcs --standard=WordPress --extensions=php --report=json . > /tmp/flosc-v94-latest-codex-wpcs.json
python3 -c 'import json; print(json.load(open("/tmp/flosc-v94-latest-codex-wpcs.json"))["totals"])'
{'errors': 5125, 'warnings': 557, 'fixable': 3030}
Same scan on unchanged v94 source:
{'errors': 5125, 'warnings': 557, 'fixable': 3030}

php -d memory_limit=2G /Users/dainismichel/.composer/vendor/bin/phpcs --standard=PHPCompatibilityWP --extensions=php --runtime-set testVersion 7.4-7.4 --report=json . > /tmp/flosc-v94-latest-codex-php74.json
errors: 0; warnings: 0; fixable: 0
```

Static GET-key inventory: 29 consumed keys, 32 declared keys. Before this
change, `catalog` and `da1_export` were the two consumer keys missing from the
declaration; after it, the only undeclared consumer key is `deleted`, which
`admin/flows.php` reads directly and does not use the helper. A direct helper
execution with `catalog=membership`, `da1_export=1`, and an undeclared key
returned `{"catalog":"membership","da1_export":"1"}`.

Source inventory before and after: 308 files, 195 PHP files, 2,447 textual
named-function matches, 171 textual class matches. All four Starter Pack
manifests and the five required UI strings remain.

The two-line source diff against v94 passes `git diff --no-index --check`.
The staged whole-candidate diff against the older Codex v68 snapshot produces
738 `git diff --cached --check` warnings from inherited v94 whitespace in
other files. This pass did not run a formatting sweep or claim that whole-tree
diff check passes.

## Artifact and limits

`./build-dist-zip.sh` produced `flosc.zip`: 281 entries, 2,795,691 bytes, one
`flosc/` root, and `unzip -t` clean. The edited helper in the ZIP matches the
source. The ZIP's SHA-256 is recorded in `SHA256SUMS` and the manifest.

The inherited v94 build script excludes `admin/create-sample-data.php` even
though the source contains it. Whether that exclusion preserves the intended
sample-data workflow needs a separate functional review. This candidate was
not deployed or tested in a booting WordPress installation, and the WPCS
findings remain; it is not submission-ready.
