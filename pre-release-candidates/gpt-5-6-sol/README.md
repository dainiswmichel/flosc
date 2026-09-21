# FLOSC — GPT-5.6-sol v93.1 candidate

Agent: GPT-5.6-sol

Candidate: v93.1

Plugin version: 8.0.0

Source base: v93 commit `9ce0c0a5e6ffaed48590434c93c98b02554613ac`

## What changed from v93

- Removed the obsolete Amazon external-service disclosure and renumbered the WordPress oEmbed declaration.
- Read OAuth callback query and form fields through `WP_REST_Request`, retaining POST-over-GET precedence, Apple `user` support, ChemiCloud query-string fallbacks, and OAuth `state` verification before authentication.
- Restored bounded callback input lengths from the earlier security work without restoring `FILTER_UNSAFE_RAW` or nonce suppressions.
- Restored `admin/create-sample-data.php` to the installable artifact.
- Excluded internal agent, editor, release, and PHPCS files from the artifact.

The candidate source differs from v93 in exactly five files:

```text
.distignore
build-dist-zip.sh
includes/magic-link/class-flosc-magic-link-trait.php
includes/sso/class-oauth2-handler.php
readme.txt
```

## Artifact

```text
sha256       44ccd005669e072e0e7510fb829f92fe04a3e602a07bb92ccf40dca6b404b2d6
size_bytes   2801120
zip_entries  282
root         flosc/
```

## Measured results

```console
$ find "$source_dir" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
PHP_FILES=195
PHP_L_FAILURES=0
```

```console
$ php -d memory_limit=2G "$HOME/.composer/vendor/bin/phpcs" --standard=WordPress --extensions=php --ignore='*/tests/*,*/vendor/*,*/node_modules/*,*/admin/docs/*,*/flosc_documentation/*' --sniffs='WordPress.Security.NonceVerification,WordPress.WP.Capabilities' --report=summary -s "$source_dir"
A TOTAL OF 0 ERRORS AND 19 WARNINGS WERE FOUND IN 3 FILES
```

The 19 warnings are the known architectural reads: 12 in the HMAC-signed user-audio endpoint, four in read-only navigation helpers, and three in capability-token login callbacks. The OAuth callback now produces zero findings in this scan.

```console
$ php tests/check_wporg_rules.php
active exceptions : 3
files scanned     : 144
findings          : 0

$ php tests/check_packaging.php
Shipped PHP files: 153
The tree is shippable

$ php tests/check_inline_js.php
Inline admin JavaScript parses cleanly

$ php tests/check_oauth_state_ordering.php
check_oauth_state_ordering: ok
```

```console
$ unzip -t flosc.zip | tail -1
No errors detected in compressed data of flosc.zip.

$ unzip -l flosc.zip | grep 'admin/create-sample-data.php'
19010  09-21-2026 14:56   flosc/admin/create-sample-data.php

$ unzip -l flosc.zip | grep -E '\.cursorrules|agents\.md|AGENTS\.md|CLAUDE\.md|WORDPRESS-ORG-RELEASE|phpcs\.xml'
[no output]
```

The complete WPCS suite and Plugin Check were not completed as part of this packaging step. This is a live-testing candidate, not a WordPress.org-readiness claim.
