#!/usr/bin/env bash
#
# verify-standards.sh — the tools report, not the agent.
#
# 242 remediation roadmaps. 279 builds. 29 uploads to WordPress.org. Still not
# compliant. Every one of those uploads happened because an AI agent promised
# WordPress.org standards and submissions compliant code, and the promise was
# broken each time, and the breach took a review cycle to surface instead of a
# command.
#
# This script exists so that the next status claim can be checked in one
# command. It runs the real tools and prints their bytes. It does not
# summarise, characterise, interpret, or conclude. There is no agent between
# the tool and the person reading.
#
# THE RULE THAT MATTERS: a check that did not run is a FAILURE, never a pass.
# tests/check_packaging.php has been asserting "Requires at least: 7.0.4" as
# correct — the exact value WordPress.org returns as an ERROR — while the suite
# printed "0 failing gates". A green suite meant "we agreed with ourselves",
# not "we met the standard". A missing tool must never be able to say green.
#
# Usage, from the plugin root:
#
#     bash tests/verify-standards.sh ; echo "exit=$?"
#
# Exit 0 only when every tool RAN and every tool PASSED.
#
# @package FLOSC

set -uo pipefail

# ---------------------------------------------------------------------------
# Locate the plugin root from this script, not from the caller's cwd.
# ---------------------------------------------------------------------------
SCRIPT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PLUGIN_ROOT="$( cd -- "${SCRIPT_DIR}/.." &> /dev/null && pwd )"
cd "${PLUGIN_ROOT}" || { echo "cannot cd to plugin root"; exit 2; }

# Michel Time Stamp, same shape as flosc_mts_utc().
MTS="$( date -u +"%Yy-%mm-%dd-UTC-%Hh-%Mm-%Ss-000ms" )"
LOG="${PLUGIN_ROOT}/tests/verify-standards-${MTS}.log"

# name<TAB>state<TAB>exit — the only thing this script says at the end.
RESULTS=()

# ---------------------------------------------------------------------------
# run <NAME> <command...>
#
# Prints the exact command, then the tool's stdout and stderr verbatim, then
# the exit code. Nothing is filtered, trimmed, coloured or reworded — if the
# tool printed it, it appears here, and if it did not, nothing appears.
# ---------------------------------------------------------------------------
run() {
	local name="$1"; shift
	echo
	echo "===== ${name} ====="
	echo "\$ $*"
	echo
	"$@" 2>&1
	local code=$?
	echo
	echo "----- exit ${code} -----"
	RESULTS+=( "${name}	$( [ "${code}" -eq 0 ] && echo RAN-PASS || echo RAN-FAIL )	${code}" )
	return "${code}"
}

# ---------------------------------------------------------------------------
# not_run <NAME> <reason> <how to install it>
#
# The load-bearing function in this file.
#
# A tool that is absent tells you NOTHING about the code. It must therefore be
# impossible to mistake for a pass: it prints its own block, it says NOT RUN in
# the results table, and it makes the script exit non-zero. "Clean" may never
# again mean "we did not check".
# ---------------------------------------------------------------------------
not_run() {
	local name="$1" reason="$2" remedy="$3"
	echo
	echo "===== ${name} ====="
	echo
	echo "  *** NOT RUN — ${reason}"
	echo
	echo "  This is a FAILURE, not a pass. A tool that did not run has told you"
	echo "  nothing whatsoever about this code."
	echo
	echo "  To make it run:"
	echo "      ${remedy}"
	echo
	echo "----- exit NOT RUN -----"
	RESULTS+=( "${name}	NOT-RUN	-" )
}

# ===========================================================================
# Everything below is written to the terminal and to the log at once, so the
# log is byte-identical to what was read on screen.
# ===========================================================================
{

echo "==========================================================================="
echo " FLOSC — WordPress.org standards verification"
echo " The tools report. This script says nothing of its own."
echo "==========================================================================="
echo
echo "  stamp        ${MTS}"
echo "  plugin root  ${PLUGIN_ROOT}"
echo "  git commit   $( git rev-parse HEAD 2>/dev/null || echo 'not a git checkout' )"
echo "  git status   $( [ -n "$( git status --porcelain 2>/dev/null )" ] && echo 'DIRTY — uncommitted changes present' || echo 'clean' )"
echo "  header       $( grep -m1 '^ \* Version:' flosc.php 2>/dev/null | sed 's/^ \* //' )"
echo "  constant     $( grep -m1 "define('FLOSC_VERSION'" flosc.php 2>/dev/null | tr -d " \t" )"
echo "  stable tag   $( grep -m1 '^Stable tag:' readme.txt 2>/dev/null )"
echo "  log          ${LOG}"
echo

# ---------------------------------------------------------------------------
# 1. php -l over the whole tree.
# ---------------------------------------------------------------------------
if command -v php > /dev/null 2>&1; then
	run "PHP LINT (php -l, whole tree)" \
		bash -c 'find . -name "*.php" -not -path "./.git/*" -print0 | xargs -0 -n1 php -l'
else
	not_run "PHP LINT (php -l, whole tree)" \
		"php is not on PATH" \
		"install PHP, or run this script where php exists"
fi

# ---------------------------------------------------------------------------
# 2. PHPCS with the WordPress rulesets.
#
# This is the tool that encodes WordPress's published coding standards. Its
# absence is exactly the gap that let 29 uploads go out.
# ---------------------------------------------------------------------------
PHPCS=""
for candidate in "./vendor/bin/phpcs" "phpcs"; do
	if command -v "${candidate}" > /dev/null 2>&1 || [ -x "${candidate}" ]; then
		PHPCS="${candidate}"
		break
	fi
done

if [ -n "${PHPCS}" ]; then
	run "PHPCS (WordPress-Extra, WordPress-Docs)" \
		"${PHPCS}" --standard=WordPress-Extra,WordPress-Docs \
		--extensions=php --ignore=*/vendor/*,*/node_modules/* --report=full .
else
	not_run "PHPCS (WordPress-Extra, WordPress-Docs)" \
		"phpcs is not installed (looked for ./vendor/bin/phpcs and phpcs on PATH)" \
		"composer global require squizlabs/php_codesniffer wp-coding-standards/wpcs"
fi

# ---------------------------------------------------------------------------
# 3. WordPress Plugin Check — the reviewers' own tool.
#
# Needs wp-cli AND a real WordPress install. Neither can be faked, which is
# the point: this block cannot print a pass unless a WordPress actually ran it.
# ---------------------------------------------------------------------------
if ! command -v wp > /dev/null 2>&1; then
	not_run "WORDPRESS PLUGIN CHECK" \
		"wp-cli is not on PATH" \
		"https://wp-cli.org/#installing — then: wp plugin install plugin-check --activate"
elif ! wp core is-installed > /dev/null 2>&1; then
	not_run "WORDPRESS PLUGIN CHECK" \
		"wp-cli is present but no WordPress install is reachable from here" \
		"run this from a WordPress root, or add --path=/path/to/wordpress to the wp calls"
elif ! wp plugin is-installed plugin-check > /dev/null 2>&1; then
	not_run "WORDPRESS PLUGIN CHECK" \
		"the plugin-check plugin is not installed in this WordPress" \
		"wp plugin install plugin-check --activate"
else
	run "WORDPRESS PLUGIN CHECK" \
		wp plugin check flosc --format=table
fi

# ---------------------------------------------------------------------------
# 4. The repository's own gates.
#
# Printed LAST among the code checks and labelled, because these are the gates
# that have been reporting "0 failing" for months while the plugin was being
# rejected. They test FLOSC's own logic. They are not evidence of compliance
# with anything WordPress.org publishes.
# ---------------------------------------------------------------------------
echo
echo "==========================================================================="
echo " The next two blocks are FLOSC's OWN gates."
echo
echo " They test this plugin's internal logic. They have printed 0 failures"
echo " while WordPress.org rejected the upload. A green result here is NOT"
echo " evidence of compliance and must never be reported as such."
echo "==========================================================================="

if command -v php > /dev/null 2>&1; then
	run "FLOSC PHP GATES (tests/*.php)" \
		bash -c 'fail=0
			for f in tests/test_*.php tests/check_*.php; do
				[ -e "$f" ] || continue
				echo "--- $f"
				php "$f" 2>&1 | tail -3
				status=${PIPESTATUS[0]}
				[ "$status" -ne 0 ] && { echo "    FAILED ($status)"; fail=1; }
			done
			exit $fail'
else
	not_run "FLOSC PHP GATES (tests/*.php)" "php is not on PATH" "install PHP"
fi

if command -v node > /dev/null 2>&1; then
	run "FLOSC JS GATES (tests/*.js)" \
		bash -c 'fail=0
			for j in tests/*.js; do
				[ -e "$j" ] || continue
				echo "--- $j"
				node "$j" 2>&1 | tail -3
				status=${PIPESTATUS[0]}
				[ "$status" -ne 0 ] && { echo "    FAILED ($status)"; fail=1; }
			done
			exit $fail'
else
	not_run "FLOSC JS GATES (tests/*.js)" "node is not on PATH" "install Node.js"
fi

# ---------------------------------------------------------------------------
# 5. The WordPress version headers, against the live release list.
#
# Printed side by side and compared by the reader, not by this script.
#
# This is the check that did not exist, and its absence is why
# "Requires at least: 7.0.4" survived review after review: the packaging gate
# compared readme.txt against flosc.php. They agreed. They were both wrong.
# Agreement with yourself is not a standard.
# ---------------------------------------------------------------------------
echo
echo "===== WORDPRESS VERSION HEADERS vs THE LIVE RELEASE LIST ====="
echo "\$ curl -sS --max-time 20 https://api.wordpress.org/core/stable-check/1.0/"
echo

WP_API_RAW="$( curl -sS --max-time 20 "https://api.wordpress.org/core/stable-check/1.0/" 2>&1 )"
WP_API_CODE=$?

echo "  readme.txt  Requires at least : $( grep -m1 '^Requires at least:' readme.txt 2>/dev/null | sed 's/^Requires at least:[[:space:]]*//' )"
echo "  readme.txt  Tested up to      : $( grep -m1 '^Tested up to:' readme.txt 2>/dev/null | sed 's/^Tested up to:[[:space:]]*//' )"
echo "  flosc.php   Requires at least : $( grep -m1 '^ \* Requires at least:' flosc.php 2>/dev/null | sed 's/^ \* Requires at least:[[:space:]]*//' )"
echo

if [ "${WP_API_CODE}" -ne 0 ] || [ -z "${WP_API_RAW}" ]; then
	echo "  *** NOT RUN — could not reach api.wordpress.org"
	echo
	echo "  curl said: ${WP_API_RAW}"
	echo
	echo "  This is a FAILURE, not a pass. Without the live release list the"
	echo "  headers above are unverified, and NOBODY — human or agent — may"
	echo "  state what they should be. Read the current version off"
	echo "  https://wordpress.org/download/ and do not guess it."
	echo
	echo "----- exit NOT RUN -----"
	RESULTS+=( "WORDPRESS VERSION HEADERS	NOT-RUN	-" )
else
	echo "  api.wordpress.org, latest release:"
	echo "${WP_API_RAW}" | tr ',' '\n' | grep '"latest"' | tr -d '{}" ' | sed 's/^/      /'
	echo
	echo "  Read the three header values above against that number yourself."
	echo "  WordPress.org rejects a Tested up to that is behind the current"
	echo "  release, and rejects a Requires at least carrying a patch digit."
	echo
	echo "----- exit RAN (read the values above) -----"
	RESULTS+=( "WORDPRESS VERSION HEADERS	RAN-READ	0" )
fi

# ---------------------------------------------------------------------------
# The results table. The only thing this script asserts.
# ---------------------------------------------------------------------------
echo
echo "==========================================================================="
echo " RESULTS"
echo "==========================================================================="
echo
printf '  %-44s %-10s %s\n' "TOOL" "STATE" "EXIT"
printf '  %-44s %-10s %s\n' "----" "-----" "----"

OVERALL=0
NOT_RUN_COUNT=0
for row in "${RESULTS[@]}"; do
	IFS=$'\t' read -r rname rstate rcode <<< "${row}"
	printf '  %-44s %-10s %s\n' "${rname}" "${rstate}" "${rcode}"
	case "${rstate}" in
		RAN-FAIL) OVERALL=1 ;;
		NOT-RUN)  OVERALL=1; NOT_RUN_COUNT=$(( NOT_RUN_COUNT + 1 )) ;;
	esac
done

echo
if [ "${NOT_RUN_COUNT}" -gt 0 ]; then
	echo "  ${NOT_RUN_COUNT} tool(s) DID NOT RUN."
	echo
	echo "  A tool that did not run has told you nothing about this code."
	echo "  This run cannot be cited as evidence of anything, by anyone."
fi
echo "  stamp ${MTS}"
echo "  log   ${LOG}"
echo

# The block's LAST command is what PIPESTATUS[0] reports, so the verdict has to
# be the last thing in it. Without this line the script exits on the preceding
# echo — always 0, always "pass" — which is the exact failure this file exists
# to prevent.
exit "${OVERALL}"

} 2>&1 | tee "${LOG}"

# tee is last in the pipe, so read the exit from the block, not from tee.
exit "${PIPESTATUS[0]}"
