# FLOSC — GPT-5.6-sol v91 candidate

**Agent:** GPT-5.6-sol

**Candidate:** v91

**Plugin version:** 8.0.0

**Published candidate path:** `pre-release-candidates/gpt-5-6-sol/`

**Source snapshot commit:** `2bf7a63364475a08b252cddab780c45dab1df7a5` plus the current 19-file local working-tree cleanup

**Candidate parent on main:** `ea786bbd4a1a3cb78ab8d2313c563911615d094a`

## What changed

- Replaced broad file-scope request reads in `admin/ivr-messages.php` with explicit, type-checked, per-key reads and field-appropriate sanitization.
- Preserved IVR Markdown by using FLOSC's existing UTF-8, control-byte, PHP-tag, and length validator after nonce and capability checks. Registered that real sanitizer with PHPCS; no suppression was added.
- Replaced broad file-scope GET reads in `admin/offers.php` with exact sanitized reads. The dynamic offer-save POST is read only after its nonce and `manage_options` checks.
- Excluded lowercase `agents.md` from distributions while retaining the complete `flosc_documentation/` tree.
- Kept plugin version `8.0.0` and built `flosc.zip` only with `build-dist-zip.sh`.

## Artifact

```text
sha256                       db55f753fd5f62ab0a61b0e6282635ad227fb077cf04e4ddcb7a9c86652e0ff2
size_bytes                   2129283
zip_entries                  240
candidate_source_files       226
plugin_documentation_entries 19
root                         flosc/
```

## Measured results

```console
$ find "$source_dir" -name '*.php' -type f -print0 | xargs -0 -n1 php -l
PHP_FILES=139
PHP_L_FAILURES=0
```

```console
$ vendor/bin/phpcs -q --no-colors --standard=phpcs.xml.dist --sniffs=WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput admin/ivr-messages.php admin/offers.php
[no output; exit 0]
```

```console
$ php -d memory_limit=2G vendor/bin/phpcs -q --no-colors --standard=WordPress --extensions=php --ignore='*/tests/*,*/vendor/*,*/node_modules/*,*/admin/docs/*,*/flosc_documentation/*' --report=json .
TOTAL: 6669
FIXABLE: 0
NON-FIXABLE: 6669
```

The one remaining broad request read in these templates is function-scoped and occurs only after authorization:

```console
$ rg -n 'wp_unslash\( \$_(GET|POST|REQUEST) \)' admin/ivr-messages.php admin/offers.php
admin/offers.php:135:\t$flosc_post = wp_unslash( $_POST );
```

```console
$ unzip -t flosc.zip | tail -1
No errors detected in compressed data of flosc.zip.
$ unzip -Z1 flosc.zip | rg '(^|/)(tests|vendor|pre-release-candidates|sample-data)/|admin/create-sample-data\.php$|/(AGENTS|agents|CLAUDE)\.md$|/phpcs\.xml\.dist$|/WORDPRESS-ORG-RELEASE\.md$|/\.cursorrules$'
[no output]
$ unzip -Z1 flosc.zip | rg '^flosc/flosc_documentation/' | wc -l
19
```

This is a measured candidate snapshot, not a claim of WordPress.org readiness. No live deployment or browser/runtime test was performed.
