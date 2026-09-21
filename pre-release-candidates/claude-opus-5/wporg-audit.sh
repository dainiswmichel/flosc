#!/bin/bash
# wporg-audit.sh — check a FLOSC tree against every issue WordPress.org has
# raised across review rounds T7 (11 Jun 2026) through T13 (14 Sep 2026).
#
# The reviewer samples: it reports "out of a total of N incidences" and shows
# only a handful. So every check here looks for the PATTERN across the whole
# tree, not the specific lines that happened to be quoted in one email.
#
# Usage:   ./wporg-audit.sh [path-to-plugin-root]
# Default: the sibling flosc-by-claude-opus-5/flosc tree.
#
# Lives at candidate level, never inside the plugin tree, so it cannot ship.

set -uo pipefail

ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/flosc-by-claude-opus-5/flosc" && pwd)}"
cd "$ROOT" || { echo "FATAL: cannot enter $ROOT"; exit 1; }

PASS=0; FAIL=0; REVIEW=0

ok()     { printf '  \033[32mPASS\033[0m  %s\n' "$1"; PASS=$((PASS+1)); }
bad()    { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; FAIL=$((FAIL+1)); }
review() { printf '  \033[33mREAD\033[0m  %s\n' "$1"; REVIEW=$((REVIEW+1)); }
head2()  { printf '\n\033[1m%s\033[0m\n' "$1"; }

php_files() { find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -print0; }

echo "=============================================================="
echo " FLOSC WordPress.org audit"
echo " tree: $ROOT"
echo " date: $(date '+%Y-%mm-%dd %H:%M')"
echo "=============================================================="

head2 "1. Requires at least  (T12, T13 — hard ERROR both rounds)"
H=$(grep -m1 '^ \* Requires at least' flosc.php 2>/dev/null | sed 's/.*: *//' | tr -d '\r ')
R=$(grep -m1 '^Requires at least' readme.txt 2>/dev/null | sed 's/.*: *//' | tr -d '\r ')
echo "        flosc.php='$H'  readme.txt='$R'"
[ -n "$H" ] && [ "$H" = "$R" ] && ok "headers agree" || bad "headers disagree or missing"
echo "$H" | grep -qE '^[0-9]+\.[0-9]+$' \
  && ok "major.minor only (no patch segment)" \
  || bad "must be major.minor only — '7.0.4' is what got rejected"

head2 "2. register_setting() sanitize_callback  (T9,T10,T11 — CHANGESNOTMADE)"
TOTAL=$(grep -rc --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'register_setting(' --include='*.php' . 2>/dev/null | grep -v ':0$' | awk -F: '{s+=$2} END {print s+0}')
echo "        register_setting() calls: ${TOTAL:-0}"
grep -rA6 --exclude-dir=vendor --exclude-dir=node_modules 'register_setting(' --include='*.php' . 2>/dev/null | grep -q 'sanitize_callback' \
  && ok "sanitize_callback present" || bad "a register_setting() lacks sanitize_callback"
grep -q 'sanitize_secret_setting' includes/sso/class-sso-manager.php 2>/dev/null \
  && ok "password/secret fields have their own sanitizer (the T11 complaint)" \
  || bad "secret fields fall through to a text sanitizer — T11 named this exactly"

head2 "3. REST permission_callback  (T7,T10,T11 — CHANGESNOTMADE)"
WEAK=$(grep -rhoE --exclude-dir=vendor --exclude-dir=node_modules "permission_callback'[[:space:]]*=>[[:space:]]*'(is_user_logged_in|__return_true)'" --include='*.php' . 2>/dev/null | wc -l | tr -d ' ')
echo "        bare is_user_logged_in / __return_true: $WEAK"
[ "$WEAK" -eq 0 ] && ok "no bare weak callbacks" || review "$WEAK bare callback(s) — public ones are allowed, but each needs to be deliberate"
for EP in 'debug/funnel-state' "'/lessons'" 'admin-messages'; do
  grep -q "$EP" flosc.php 2>/dev/null && review "endpoint still present: $EP (named in T10/T11)" || ok "endpoint absent: $EP"
done

head2 "4. Callback return values escaped  (T7,T8 — CHANGESNOTMADE)"
grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives "add_filter( *'the_content'\|add_shortcode(" --include='*.php' . 2>/dev/null | sed 's/^/        /' | head -12
review "each callback above must escape everything it RETURNS — read them, a grep cannot decide this"

head2 "5. Core loading files included directly  (T12)"
N=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives "require.*wp-admin/includes/import\.php\|require.*wp-load\.php\|require.*wp-config\.php\|require.*wp-blog-header\.php" --include='*.php' . 2>/dev/null | wc -l | tr -d ' ')
[ "$N" -eq 0 ] && ok "no direct core-file includes" || { bad "$N direct core-file include(s)"; grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives "require.*wp-admin/includes/import\.php\|require.*wp-load\.php" --include='*.php' . | sed 's/^/        /'; }

head2 "6. File and directory locations  (T12)"
N=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'WP_PLUGIN_DIR' --include='*.php' . 2>/dev/null | wc -l | tr -d ' ')
[ "$N" -eq 0 ] && ok "no WP_PLUGIN_DIR references" || { bad "$N WP_PLUGIN_DIR reference(s) — use plugin_dir_path()/plugins_url()"; grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'WP_PLUGIN_DIR' --include='*.php' . | sed 's/^/        /'; }

head2 "7. Writing into the plugin folder  (T7)"
N=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'file_put_contents\|[^_a-z]fwrite(\|[^_a-z]copy(' --include='*.php' . 2>/dev/null | grep -v flosc_documentation | wc -l | tr -d ' ')
[ "$N" -eq 0 ] && ok "no raw write calls (writes go through WP_Filesystem / uploads)" || { bad "$N raw write call(s)"; grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'file_put_contents\|[^_a-z]fwrite(\|[^_a-z]copy(' --include='*.php' . | grep -v flosc_documentation | sed 's/^/        /' | head; }

head2 "8. FILTER_UNSAFE_RAW / FILTER_DEFAULT  (T12 — 31 incidences)"
N=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'FILTER_UNSAFE_RAW\|FILTER_DEFAULT' --include='*.php' . 2>/dev/null | wc -l | tr -d ' ')
[ "$N" -eq 0 ] && ok "zero — these sanitize nothing and AGENTS.md forbids them" || { bad "$N use(s)"; grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'FILTER_UNSAFE_RAW\|FILTER_DEFAULT' --include='*.php' . | sed 's/^/        /' | head; }

head2 "9. HTTP_HOST in the SSO redirect allowlist  (T12)"
N=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'HTTP_HOST' includes/sso/ 2>/dev/null | wc -l | tr -d ' ')
[ "$N" -eq 0 ] && ok "no HTTP_HOST in SSO" || { bad "$N HTTP_HOST use(s) in SSO — attacker-controllable"; grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'HTTP_HOST' includes/sso/ | sed 's/^/        /'; }

head2 "10. json_decode( stripslashes( ... ) )  (T7, T11)"
N=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'json_decode( *stripslashes\|json_decode(stripslashes' --include='*.php' . 2>/dev/null | wc -l | tr -d ' ')
[ "$N" -eq 0 ] && ok "pattern absent" || { review "$N site(s) — json_decode does not sanitize; validate the decoded structure"; grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'json_decode( *stripslashes\|json_decode(stripslashes' --include='*.php' . | sed 's/^/        /'; }

head2 "11. Inline <script> / <style> in PHP  (every round)"
HITS=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives -E '<script[ >]|<style[ >]' --include='*.php' . 2>/dev/null | grep -vE ':[[:space:]]*(//|\*|/\*|<\?php //)' | wc -l | tr -d ' ')
[ "$HITS" -eq 0 ] && ok "zero real tags (comments mentioning them do not count)" || { bad "$HITS real inline tag(s)"; grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives -E '<script[ >]|<style[ >]' --include='*.php' . | grep -vE ':[[:space:]]*(//|\*|/\*|<\?php //)' | sed 's/^/        /'; }

head2 "12. External services documented  (every round)"
DECL=$(sed -n '/^== External Services ==/,/^== [A-Z]/p' readme.txt 2>/dev/null | grep -cE '^[0-9]+\. ')
echo "        entries declared in readme.txt: $DECL"
HOSTS=$(grep -rhoE --exclude-dir=vendor --exclude-dir=node_modules 'https://[a-z0-9.-]+' --include='*.php' includes/ admin/ flosc.php 2>/dev/null \
  | sort -u | grep -vE 'dainis\.net|flosc\.ai|example\.com|w3\.org|wordpress\.org|gnu\.org|schema\.org')
echo "        distinct external hosts called by code:"
echo "$HOSTS" | sed 's/^/          /'
[ "$DECL" -gt 0 ] && ok "External Services section exists with $DECL entries" || bad "no External Services section"
review "every host listed above must map to one of those entries"

head2 "13. Creating / logging in users  (T7, T11 — 16 incidences)"
N=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives 'wp_set_auth_cookie\|wp_create_user\|wp_set_password' --include='*.php' . 2>/dev/null | wc -l | tr -d ' ')
echo "        sites: $N"
review "not a bug — a design decision (magic links, SSO, post-purchase). The reviewer"
review "accepts these ONLY with a written justification in your reply email."

head2 "14. Bulk superglobal reads at FILE SCOPE  (T7, T13 — the live blocker)"
echo "        The reviewer flags these twice over: CSRF, and performance"
echo "        (\"don't check for post submission outside of functions\")."
FS=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives -E '^\$[a-z_]+ *= *(isset\( *\$_(GET|POST) *\).*)?wp_unslash\( *\$_(GET|POST|REQUEST) *\)' --include='*.php' . 2>/dev/null)
FSN=$(echo "$FS" | grep -c . )
[ -z "$FS" ] && FSN=0
echo "        file-scope slurps: $FSN"
[ "$FSN" -eq 0 ] && ok "none at file scope" || { bad "$FSN file-scope slurp(s)"; echo "$FS" | sed 's/^/        /'; }
INF=$(grep -rn --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=flosc_development_archives -E '^[[:space:]]+\$[a-z_]+ *= *wp_unslash\( *\$_(GET|POST|REQUEST) *\)' --include='*.php' . 2>/dev/null | wc -l | tr -d ' ')
echo "        inside functions (fine when a nonce is verified first): $INF"

head2 "15. PHP syntax"
ERR=$(php_files -print0 | xargs -0 -n1 php -l 2>&1 | grep -v '^No syntax errors' | head -5)
[ -z "$ERR" ] && ok "all files parse" || { bad "parse errors"; echo "$ERR" | sed 's/^/        /'; }

echo
echo "=============================================================="
printf ' PASS %s   FAIL %s   NEEDS READING %s\n' "$PASS" "$FAIL" "$REVIEW"
echo "=============================================================="
echo
echo "FAIL means the reviewer's tool will flag it."
echo "READ means no script can judge it — a human has to look, or it belongs"
echo "in the reply email as a justification rather than a code change."
echo
echo "This script does NOT replace Plugin Check. Run that too:"
echo "    wp plugin install plugin-check --activate && wp plugin check flosc"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
