#!/usr/bin/env bash
#
# parity-gate.sh — prove a candidate tree can do everything v87 could.
#
# The textual diff between v87 and the candidate is large and will stay large,
# because the compliance work rewrites comments, flips comparisons and inserts
# docblocks across every file. A diff therefore cannot answer "did we lose
# anything". This gate answers it by inventory instead: it counts the things
# the plugin is made of and reports anything present in the reference tree and
# absent from the candidate.
#
# It deliberately says nothing about coding standards. WPCS and Plugin Check
# passing is not evidence that the product is complete — that mistake is how
# a stripped tree reached five review rounds looking clean.
#
# Usage: parity-gate.sh <reference-tree> <candidate-tree>

set -uo pipefail

REF="${1:-}"
CAND="${2:-}"

if [ -z "$REF" ] || [ -z "$CAND" ] || [ ! -d "$REF" ] || [ ! -d "$CAND" ]; then
	echo "usage: $0 <reference-tree> <candidate-tree>" >&2
	exit 2
fi

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; BOLD=$'\033[1m'; OFF=$'\033[0m'
FAILED=0
PASSED=0

# Files that never ship and never need parity.
PRUNE=( -path '*/vendor/*' -o -path '*/node_modules/*' -o -path '*/.git/*' )

# register_rest_route() is normally split across lines, with the namespace and
# the route on the ones after the call, so the call site alone is not greppable.
# Read the three following lines and keep the quoted strings that look like a
# namespace or a route. Defined as a function because the quoting does not
# survive being passed through eval as a string.
flosc_rest_routes() {
	grep -rhA3 --include='*.php' 'register_rest_route' . 2>/dev/null \
		| grep -oE "'[^']+'" \
		| tr -d "'" \
		| grep -E '^(flosc|[a-z-]+/v[0-9]+$)|^/|^[a-z0-9_-]+/' \
		| sort -u
}

say_pass() { PASSED=$((PASSED + 1)); printf '  %sPASS%s  %s\n' "$GREEN" "$OFF" "$1"; }
say_fail() { FAILED=$((FAILED + 1)); printf '  %sFAIL%s  %s\n' "$RED" "$OFF" "$1"; }

# compare <label> <producer-command>
# The producer is run against each tree; anything in the reference and not in
# the candidate is a failure, and every missing entry is printed.
compare() {
	local label="$1"; shift
	local producer="$1"
	local a b missing count
	a="$(mktemp)"; b="$(mktemp)"
	( cd "$REF"  && eval "$producer" ) | sort -u > "$a"
	( cd "$CAND" && eval "$producer" ) | sort -u > "$b"
	missing="$(comm -23 "$a" "$b")"
	count="$(wc -l < "$a" | tr -d ' ')"
	if [ -z "$missing" ]; then
		say_pass "$(printf '%-44s %s in reference, all present' "$label" "$count")"
	else
		say_fail "$(printf '%-44s %s missing of %s' "$label" "$(printf '%s' "$missing" | wc -l | tr -d ' ')" "$count")"
		printf '%s\n' "$missing" | sed 's/^/          /'
	fi
	rm -f "$a" "$b"
}

printf '%s\n' "=============================================================="
printf '%s v87 functional parity gate%s\n' "$BOLD" "$OFF"
printf '  reference: %s\n' "$REF"
printf '  candidate: %s\n' "$CAND"
printf '%s\n' "=============================================================="
echo

printf '%s1. Runtime files%s\n' "$BOLD" "$OFF"
# Basenames rather than paths: the compliance work is allowed to move a file,
# but it is not allowed to lose one. tests/ is excluded — .distignore keeps it
# out of the artifact, so it is not part of what the plugin can do.
compare "runtime PHP files (by basename)" \
	"find . \\( ${PRUNE[*]} \\) -prune -o -name '*.php' -not -path './tests/*' -print | xargs -n1 basename"
compare "JS and CSS assets (by basename)" \
	"find . \\( ${PRUNE[*]} \\) -prune -o \\( -name '*.js' -o -name '*.css' \\) -not -path './tests/*' -print | xargs -n1 basename"
echo

printf '%s2. Code surface%s\n' "$BOLD" "$OFF"
compare "classes" \
	"grep -rhoE '^[[:space:]]*(final |abstract )?class [A-Za-z0-9_]+' --include='*.php' . | sed 's/.*class //' | grep -v '^\$'"
compare "functions and methods" \
	"grep -rhoE 'function [a-zA-Z_][a-zA-Z0-9_]*' --include='*.php' . | sed 's/function //'"
echo

printf '%s3. Admin surface%s\n' "$BOLD" "$OFF"
compare "form field names" \
	"grep -rho 'name=\"[^\"]*\"' --include='*.php' . | sed 's/name=\"//; s/\"\$//' | grep -v '<?php'"
compare "admin menu and submenu pages" \
	"grep -rhoE \"add_(menu|submenu|options|management)_page\\(\" --include='*.php' . ; grep -rhoE \"'page'[[:space:]]*=>[[:space:]]*'[a-z0-9-]+'\" --include='*.php' . | sed \"s/.*=>[[:space:]]*'//; s/'\$//\""
compare "settings tabs" \
	"grep -rhoE \"'[a-z0-9-]+'[[:space:]]*=>[[:space:]]*'[A-Z][^']*'\" --include='*.php' admin/settings.php 2>/dev/null | sed \"s/'[[:space:]]*=>.*//; s/^'//\""
echo

printf '%s4. Behaviour hooks%s\n' "$BOLD" "$OFF"
compare "AJAX actions" \
	"grep -rhoE 'wp_ajax_(nopriv_)?[a-z0-9_]+' --include='*.php' ."
compare "admin_post actions" \
	"grep -rhoE 'admin_post_(nopriv_)?[a-z0-9_]+' --include='*.php' ."
compare "REST route paths" "flosc_rest_routes"
compare "shortcodes" \
	"grep -rhoE \"add_shortcode\\([[:space:]]*['\\\"][a-z0-9_-]+\" --include='*.php' . | sed \"s/.*['\\\"]//\""
compare "option names read or written" \
	"grep -rhoE \"(get|update|add|delete)_option\\([[:space:]]*['\\\"][A-Za-z0-9_]+\" --include='*.php' . | sed \"s/.*['\\\"]//\""
compare "user meta keys" \
	"grep -rhoE \"(get|update|add|delete)_user_meta\\([^,]+,[[:space:]]*['\\\"][A-Za-z0-9_]+\" --include='*.php' . | sed \"s/.*['\\\"]//\""
compare "cron hooks" \
	"grep -rhoE \"(wp_schedule_event|wp_schedule_single_event|wp_next_scheduled|wp_clear_scheduled_hook)\\([^)]*['\\\"][a-z0-9_]+\" --include='*.php' . | sed \"s/.*['\\\"]//\""
echo

printf '%s5. Shipped payload%s\n' "$BOLD" "$OFF"
compare "starter pack files" \
	"find starter-packs -type f 2>/dev/null | sed 's#^starter-packs/##'"
compare "shipped IVR configurations" \
	"find ai_configuration_files -type f 2>/dev/null | xargs -n1 basename 2>/dev/null"
compare "CSS selectors" \
	"grep -rhoE '^[[:space:]]*[.#][a-zA-Z0-9_-]+' assets/css 2>/dev/null | sed 's/^[[:space:]]*//'"
echo

printf '%s\n' "=============================================================="
if [ "$FAILED" -eq 0 ]; then
	printf ' %sPARITY HOLDS%s — %s checks, nothing in the reference is missing\n' "$GREEN" "$OFF" "$PASSED"
else
	printf ' %sPARITY BROKEN%s — %s of %s checks found missing entries\n' "$RED" "$OFF" "$FAILED" "$((PASSED + FAILED))"
fi
printf '%s\n' "=============================================================="
echo
printf '%sThis gate says nothing about coding standards.%s A tree can pass every\n' "$YELLOW" "$OFF"
printf 'check here and still fail review, and it can pass WPCS and Plugin Check\n'
printf 'while missing half the product. Run both, and read them separately.\n'

exit $(( FAILED > 0 ))
