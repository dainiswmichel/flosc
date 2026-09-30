#!/bin/bash
# FLOSC resubmission preflight.
#
# Prints the measured number beside the expected one, and PASS or FAIL per line.
# Nothing here writes to a source file. Run it from the plugin root.
#
#   bash flosc-check.sh
#
# PHPCS is taken from $PHPCS if set, else the composer global path.

set -u

ROOT="$(pwd)"
PHPCS="${PHPCS:-$HOME/.composer/vendor/bin/phpcs}"
IGNORE='*/tests/*,*/vendor/*,*/node_modules/*,*/admin/docs/*,*/flosc_documentation/*'

PASS=0; FAIL=0; SKIP=0
line() { printf '%s\n' '-------------------------------------------------------------------------'; }
ok()   { if [ "$2" = "$3" ]; then printf '  PASS  %-44s %s\n' "$1" "$2"; PASS=$((PASS+1));
         else printf '  FAIL  %-44s %s   (expected %s)\n' "$1" "$2" "$3"; FAIL=$((FAIL+1)); fi; }
atleast() { if [ "$2" -ge "$3" ] 2>/dev/null; then printf '  PASS  %-44s %s (at least %s)\n' "$1" "$2" "$3"; PASS=$((PASS+1));
         else printf '  FAIL  %-44s %s   (expected at least %s)\n' "$1" "$2" "$3"; FAIL=$((FAIL+1)); fi; }
note() { printf '  ----  %-44s %s\n' "$1" "$2"; }
skip() { printf '  SKIP  %-44s %s\n' "$1" "$2"; SKIP=$((SKIP+1)); }

printf '\nFLOSC preflight   %s\n' "$(date '+%Y-%m-%d %H:%M')"
note "plugin root" "$ROOT"

# ------------------------------------------------------------- 1. which code
line; echo "1. Which code is this"
if [ -d .git ]; then
  note "git HEAD" "$(git log --oneline -1 2>/dev/null | cut -c1-60)"
  ok "uncommitted files" "$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')" "0"
else
  skip "git HEAD" "not a git checkout"
fi
note "plugin version" "$(grep -m1 '^ \* Version:' flosc.php | sed 's/.*Version: *//' | tr -d ' \r')"
note "php files (excl vendor)" "$(find . -name '*.php' -not -path './vendor/*' | wc -l | tr -d ' ')"
note "all files" "$(find . -type f -not -path './.git/*' | wc -l | tr -d ' ')"

# ---------------------------------------------------------------- 2. parses
line; echo "2. Does it parse"
ok "php -l failures" \
   "$(find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l 2>&1 | grep -cv 'No syntax errors' | tr -d ' ')" "0"

# ----------------------------------------------------------------- 3. gates
line; echo "3. The project's own gates"
GATEN=0; GATEF=0
for f in tests/check_*.php tests/test_*.php; do
  [ -f "$f" ] || continue
  GATEN=$((GATEN+1))
  php "$f" >/dev/null 2>&1 || { printf '        failing: %s\n' "$f"; GATEF=$((GATEF+1)); }
done
atleast "gates run" "$GATEN" "44"
ok "gate failures" "$GATEF" "0"

# ------------------------------------------------------------------ 4. WPCS
line; echo "4. WordPress Coding Standards"
if [ -x "$PHPCS" ]; then
  CS=$("$PHPCS" -d memory_limit=2G --standard=phpcs.xml.dist --extensions=php \
        --ignore="$IGNORE" --report=summary --no-colors -s . 2>/dev/null)
  # A scan that matched no files reports 0 errors. That is not a pass, it is a
  # scan that never happened -- phpcs.xml.dist excludes */pre-release-candidates/*,
  # so a tree sitting under that path silently measures nothing.
  SCANNED=$(printf '%s' "$CS" | grep -oE '[0-9]+ / [0-9]+ \(100%\)' | tail -1 | cut -d' ' -f1)
  [ -z "${SCANNED:-}" ] && SCANNED=0
  atleast "files actually scanned" "$SCANNED" "140"
  if [ "$SCANNED" -gt 0 ]; then
    CSE=$(printf '%s' "$CS" | grep -oE 'A TOTAL OF [0-9]+ ERROR' | grep -oE '[0-9]+'); [ -z "${CSE:-}" ] && CSE=0
    CSW=$(printf '%s' "$CS" | grep -oE 'AND [0-9]+ WARNING'    | grep -oE '[0-9]+'); [ -z "${CSW:-}" ] && CSW=0
    ok "WPCS errors" "$CSE" "0"
    ok "WPCS warnings" "$CSW" "22"
    printf '%s\n' "$CS" | grep -E '^[a-z].*\.php +[0-9]+ +[0-9]+' | sed 's/^/          /'
  fi
else
  skip "WPCS" "phpcs not found at $PHPCS"
fi

# ----------------------------------------------------------- 5. PHP 7.4 floor
line; echo "5. PHP 7.4 compatibility"
if [ -x "$PHPCS" ] && "$PHPCS" -i 2>/dev/null | grep -q PHPCompatibilityWP; then
  PCN=$("$PHPCS" -d memory_limit=2G --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- \
        --extensions=php --ignore="$IGNORE" --report=summary --no-colors . 2>/dev/null \
        | grep -oE 'A TOTAL OF [0-9]+ ERROR' | grep -oE '[0-9]+')
  [ -z "${PCN:-}" ] && PCN=0
  ok "PHP 7.4 incompatibilities" "$PCN" "0"
else
  skip "PHPCompatibilityWP" "standard not installed"
fi

# ----------------------------------------------------- 6. nothing is silenced
line; echo "6. Nothing is silenced"
ok "phpcs suppressions in tree" \
   "$(grep -rhE '(//|#|/\*) *phpcs:(ignore|disable)' --include='*.php' . 2>/dev/null | grep -c . | tr -d ' ')" "88"
ok "FILTER_UNSAFE_RAW in code" \
   "$(grep -rhE '^[^*]*FILTER_UNSAFE_RAW' --include='*.php' includes admin 2>/dev/null | grep -vc '^\s*\*' | tr -d ' ')" "0"
ok "error_log() calls" \
   "$(grep -rhE '^[^*]*[^_a-z]error_log *\(' --include='*.php' includes admin flosc.php 2>/dev/null | grep -c . | tr -d ' ')" "0"

# ------------------------------------------------------- 7. the functionality
line; echo "7. The functionality that got destroyed once"
ok "starter packs with pack.json" "$(ls -1 starter-packs/*/pack.json 2>/dev/null | wc -l | tr -d ' ')" "4"
for d in starter-packs/*/; do [ -d "$d" ] && printf '          %s\n' "$(basename "$d")"; done
TABS=$(awk '/\$flosc_settings_tabs = array\(/,/^\t\t\);/' admin/settings.php | grep -cE "^\s+'[a-z0-9_-]+'\s+=>" | tr -d ' ')
ok "settings tabs registered" "$TABS" "25"
ok "Starter Packs is one of them" \
   "$(awk '/\$flosc_settings_tabs = array\(/,/^\t\t\);/' admin/settings.php | grep -c "'starter-packs'" | tr -d ' ')" "1"
ok "AI is one of them" \
   "$(awk '/\$flosc_settings_tabs = array\(/,/^\t\t\);/' admin/settings.php | grep -c "'ai' " | tr -d ' ')" "1"
note "REST routes"   "$(grep -rho 'register_rest_route' --include='*.php' includes admin | wc -l | tr -d ' ')"
note "shortcodes"    "$(grep -rho 'add_shortcode' --include='*.php' includes admin | wc -l | tr -d ' ')"
note "ajax actions"  "$(grep -rho 'wp_ajax_[a-z0-9_]*' --include='*.php' includes admin flosc.php | sort -u | wc -l | tr -d ' ')"
note "classes"       "$(grep -rhoE '^ *(final |abstract )?class [A-Za-z_]+' --include='*.php' includes | wc -l | tr -d ' ')"
for f in includes/flosc-request.php includes/flosc-post-queries.php \
         includes/starter-packs/class-flosc-starter-packs.php \
         includes/class-flosc-wp-ai-client.php includes/flosc-personality-library.php \
         includes/class-flosc-framework.php admin/flow.php admin/settings.php uninstall.php; do
  if [ -f "$f" ]; then printf '  PASS  %-44s present\n' "$(basename "$f")"; PASS=$((PASS+1));
  else printf '  FAIL  %-44s MISSING\n' "$(basename "$f")"; FAIL=$((FAIL+1)); fi
done

# ----------------------------------------------------------------- 8. readme
line; echo "8. readme.txt, as wordpress.org parses it"
ok "Requires at least" "$(grep -m1 '^Requires at least:' readme.txt | sed 's/.*: *//' | tr -d ' \r')" "7.1"
ok "Requires PHP"      "$(grep -m1 '^Requires PHP:'      readme.txt | sed 's/.*: *//' | tr -d ' \r')" "7.4"
ok "Stable tag"        "$(grep -m1 '^Stable tag:'        readme.txt | sed 's/.*: *//' | tr -d ' \r')" "8.0.0"
ok "no 'WordPress 7.0' left" "$(grep -c 'WordPress 7\.0' readme.txt | tr -d ' ')" "0"
# description + other_notes are concatenated by wordpress.org and cut at 2500 WORDS
W=$(awk '/^== Description ==/{f=1} /^== Installation ==/{f=0} f' readme.txt | wc -w | tr -d ' ')
if [ "$W" -lt 2500 ]; then printf '  PASS  %-44s %s words (limit 2500)\n' "description + other notes" "$W"; PASS=$((PASS+1));
else printf '  FAIL  %-44s %s words — WILL BE TRUNCATED\n' "description + other notes" "$W"; FAIL=$((FAIL+1)); fi
# The external-services disclosure is a numbered list inside the FAQ.
SVC=$(awk '/^1\. OpenAI/,/^= FLOSC Site Policies =/' readme.txt | grep -cE '^[0-9]+\. ' | tr -d ' ')
ok "external service entries" "$SVC" "16"
LASTN=$(awk '/^1\. OpenAI/,/^= FLOSC Site Policies =/' readme.txt | grep -oE '^[0-9]+\. ' | tail -1 | tr -d '. ')
ok "they are numbered 1..16 with no gap" "$LASTN" "16"
ok "Amazon is not in the list" "$(grep -ci amazon readme.txt | tr -d ' ')" "0"

# --------------------------------------------------------------- 9. the zip
line; echo "9. What actually ships"
if [ -f ./build-dist-zip.sh ]; then
  BUILD=$(bash ./build-dist-zip.sh 2>&1); BRC=$?
  # build-dist-zip.sh prints the path it wrote; take it from there rather
  # than guessing, since OUT_DIR is derived from the script's own location.
  ZIP=$(printf '%s' "$BUILD" | grep -oE '(/|\./)[^ ]*flosc\.zip' | tail -1)
  if [ "$BRC" -ne 0 ] || [ -z "${ZIP:-}" ]; then
    printf '  FAIL  %-44s build refused\n' "zip build"; FAIL=$((FAIL+1))
    printf '%s\n' "$BUILD" | tail -6 | sed 's/^/          /'
  else
    note "zip" "$ZIP"
    note "files in zip" "$(unzip -l "$ZIP" | tail -1 | awk '{print $2}')"
    if command -v shasum >/dev/null 2>&1; then note "sha256" "$(shasum -a 256 "$ZIP" | cut -d' ' -f1)";
    else note "sha256" "$(sha256sum "$ZIP" | cut -d' ' -f1)"; fi
    ok "packs inside zip" "$(unzip -l "$ZIP" | grep -c 'starter-packs/.*/pack\.json' | tr -d ' ')" "4"
    for x in 'tests/' 'phpcs.xml.dist' 'CLAUDE.md' 'agents.md' '.cursorrules' 'build-dist-zip.sh' 'vendor/'; do
      ok "absent from zip: $x" "$(unzip -l "$ZIP" | grep -c -- "$x" | tr -d ' ')" "0"
    done
  fi
else
  skip "zip" "build-dist-zip.sh not present"
fi

# ---------------------------------------------------- 10. the official tool
line; echo "10. Plugin Check — what wordpress.org actually runs"
if command -v wp >/dev/null 2>&1; then
  wp plugin check "$(basename "$ROOT")" --format=table 2>&1 | tail -40
else
  skip "wp plugin check" "wp-cli not on PATH"
fi

line
printf '\n  PASS %s    FAIL %s    SKIP %s\n\n' "$PASS" "$FAIL" "$SKIP"
[ "$FAIL" -eq 0 ] || exit 1
