#!/usr/bin/env bash
#
# testing-bench.sh — test every FLOSC candidate against the WordPress.org
# review emails, using the real tools wherever a real tool exists.
#
# Run from the directory that contains pre-release-candidates/:
#
#     ./testing-bench.sh                 fast checks only
#     ./testing-bench.sh --stan          also run PHPStan (slow)
#     ./testing-bench.sh --plugincheck   also run Plugin Check in WordPress Playground (slowest)
#     ./testing-bench.sh --all           everything
#     ./testing-bench.sh --steps         run NOTHING; print the commands to run by hand
#     ./testing-bench.sh --no-log        do not write a transcript
#
# Every run is logged to testing-logs/<MTS>.txt unless --no-log is given.
#
# READ-ONLY. Each candidate is copied to a temp dir before anything touches it.
#
# WHOSE CODE IS WHOSE:
#   sec, php74, enq, i18n, strict   PHPCS + WordPress Coding Standards. Real tool.
#   stan                            PHPStan. Real tool.
#   pcheck                          Plugin Check. WordPress.org's OWN tool.
#   hdr, inline                     grep and a regex.
#   rules                           check_wporg_rules.php -- written by Claude Opus 5.
#                                   Encodes the review emails. Has produced false
#                                   positives. Read its findings, never sweep them.
#
# THE ONE RULE THIS FILE EXISTS FOR:
#   A check that did not run prints NORUN and makes the exit code non-zero.
#   It NEVER prints 0. tests/check_packaging.php printed "0 failing gates"
#   for four review rounds while asserting a value WordPress.org rejects.
#   A zero you cannot trace to a tool that actually ran is worth nothing.

set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CANDS="$HERE/pre-release-candidates"
RULES="$CANDS/claude-opus-5/flosc-by-claude-opus-5/flosc/tests/check_wporg_rules.php"
IGN='*/tests/*,*/vendor/*,*/node_modules/*,*/admin/docs/*,*/flosc_documentation/*'
STAMP=$(date -u +"%Yy-%mm-%dd-UTC-%Hh-%Mm-%Ss")

# ------------------------------------------------------------------ the log
# Every run writes its full transcript to testing-logs/<MTS>.txt, unedited,
# before anyone gets a chance to summarise it. A claim about this plugin can
# then be checked against a file with a date on it instead of against an
# agent's memory of what it saw. --no-log turns it off.
case " $* " in
  *" --no-log "*) BENCH_LOGGING=1 ;;
esac
if [ "${BENCH_LOGGING:-0}" != "1" ]; then
  mkdir -p "$HERE/testing-logs"
  LOGFILE="$HERE/testing-logs/$STAMP.txt"
  BENCH_LOGGING=1 "$0" "$@" 2>&1 | tee "$LOGFILE"
  rc=${PIPESTATUS[0]}
  echo
  echo "transcript written to: $LOGFILE"
  exit "$rc"
fi

TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
NORUN=""            # names of tools that did not run; any entry => exit 1

note_norun() { case " $NORUN " in *" $1 "*) ;; *) NORUN="$NORUN $1" ;; esac; }

# find a tool: $PHPCS/$PHPSTAN env override, then the two composer-global
# layouts, then PATH. Empty result means not found, which means NORUN.
find_tool() {
  local name="$1" override="$2" c
  if [ -n "$override" ]; then [ -x "$override" ] && echo "$override"; return; fi
  for c in "$HOME/.composer/vendor/bin/$name" "$HOME/.config/composer/vendor/bin/$name" \
           "$HERE/vendor/bin/$name" "$(command -v "$name" 2>/dev/null || true)"; do
    if [ -n "$c" ] && [ -x "$c" ]; then echo "$c"; return; fi
  done
}

PHPCS=$(find_tool phpcs "${PHPCS:-}")
PHPSTAN=$(find_tool phpstan "${PHPSTAN:-}")
PHP=$(command -v php 2>/dev/null || true)

RUN_STAN=0; RUN_PC=0; STEPS=0
for a in "$@"; do
  case "$a" in
    --stan) RUN_STAN=1 ;;
    --plugincheck) RUN_PC=1 ;;
    --all) RUN_STAN=1; RUN_PC=1 ;;
    --steps) STEPS=1 ;;
  esac
done

# --------------------------------------------------------------- --steps
# Runs NOTHING. Prints the commands, numbered, one per line, so the whole
# bench can be done by hand with no script of mine in the middle.
if [ "$STEPS" = "1" ]; then
  P="${PHPCS:-phpcs}"
  echo "Every check this bench performs, as a command you run yourself."
  echo "Pick a candidate folder for CAND. To see them:  ls pre-release-candidates"
  echo
  echo "STEP 0  set the candidate once, then paste the rest as they are"
  echo "        CAND=\$(ls -d $CANDS/*/flosc-by-*/flosc | head -1)"
  echo "        echo \$CAND"
  echo
  echo "STEP 1  the version header — the first ERROR in the T12 and T13 emails"
  echo "        grep -m1 '^Requires at least:' \$CAND/readme.txt"
  echo "        grep -m1 'Requires at least:' \$CAND/flosc.php"
  echo "        the two must match and must be major.minor, e.g. 7.0 — never 7.0.4"
  echo
  echo "STEP 2  WordPress security sniffs (escaping, sanitizing, nonces, SQL)"
  echo "        $P --standard=WordPress --warning-severity=0 \\"
  echo "          --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification,WordPress.Security.SafeRedirect,WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery \\"
  echo "          --extensions=php --ignore='$IGN' \$CAND"
  echo
  echo "STEP 3  PHP 7.4 compatibility — WordPress.org's minimum"
  echo "        $P --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- \\"
  echo "          --extensions=php --ignore='$IGN' \$CAND"
  echo
  echo "STEP 4  scripts and styles must be enqueued, never printed inline"
  echo "        $P --standard=WordPress --sniffs=WordPress.WP.EnqueuedResources \\"
  echo "          --extensions=php --ignore='$IGN' \$CAND"
  echo
  echo "STEP 5  translation function calls"
  echo "        $P --standard=WordPress --sniffs=WordPress.WP.I18n \\"
  echo "          --extensions=php --ignore='$IGN' \$CAND"
  echo
  echo "STEP 6  in_array / array_search without strict comparison"
  echo "        $P --standard=WordPress --sniffs=WordPress.PHP.StrictInArray \\"
  echo "          --extensions=php --ignore='$IGN' \$CAND"
  echo
  echo "STEP 7  inline style=\"...\" attributes. No sniff catches these — it is a grep."
  echo "        grep -rnE 'style=\"[^\"]+\"' \$CAND --include=*.php | grep -v '/tests/'"
  echo
  echo "STEP 8  PHPStan"
  echo "        ${PHPSTAN:-phpstan} analyse --level=5 --no-progress \$CAND"
  echo
  echo "STEP 9  Plugin Check — WordPress.org's OWN tool. UNVERIFIED, never completed a run."
  echo "        npx --yes @wp-playground/cli@latest run-blueprint \\"
  echo "          --blueprint=pc-blueprint.json \\"
  echo "          --mount=\$CAND:/wordpress/wp-content/plugins/flosc --php=8.3"
  echo
  echo "STEP 10 the rules gate. THIS ONE IS MY CODE, not a real tool."
  echo "        It encodes the review emails and has produced false positives."
  echo "        Read what it prints. Never sweep it."
  echo "        cp $RULES \$CAND/tests/ && (cd \$CAND && php tests/check_wporg_rules.php)"
  echo
  echo "Exit code on every phpcs step: 0 = clean, 1 or 2 = findings, 3 or more = it did not run."
  echo "A step that did not run is not a pass. Check with:  echo \$?"
  exit 0
fi

# Count ERRORS from a phpcs standard/sniff on a tree.
# phpcs exit codes: 0 none, 1 errors found, 2 fixable, >=3 could not run.
# >=3, or an unparseable report, prints NORUN -- never 0.
pc_err() {
  local std="$1" extra="$2" root="$3" out rc n
  if [ -z "$PHPCS" ]; then note_norun "phpcs"; echo "NORUN"; return; fi
  # shellcheck disable=SC2086
  out=$("$PHPCS" --standard="$std" $extra --extensions=php --ignore="$IGN" \
        --report=summary "$root" 2>&1); rc=$?
  # The exit code is the authority, not the report text: a clean run prints
  # NOTHING AT ALL and exits 0. Parsing the text for "A TOTAL OF" would call
  # that silence a failure to run.
  #   0    ran, no errors
  #   1,2  ran, found errors (2 = some are auto-fixable)
  #   >=3  did not run: standard not installed, bad config, internal error
  if [ "$rc" -ge 3 ]; then
    note_norun "phpcs:$std"; echo "NORUN"; return
  fi
  if [ "$rc" -eq 0 ]; then echo 0; return; fi
  n=$(printf '%s' "$out" | grep -oE 'A TOTAL OF [0-9]+ ERROR' | grep -oE '[0-9]+')
  if [ -z "$n" ]; then note_norun "phpcs:$std"; echo "NORUN"; return; fi
  echo "$n"
}

echo "FLOSC testing bench — $STAMP"
if [ -d "$HERE/.flosc-mirror/.git" ]; then
  echo "  candidates: $(git -C "$HERE/.flosc-mirror" rev-parse --short HEAD 2>/dev/null || echo unknown) (.flosc-mirror)"
elif [ -d "$HERE/.git" ]; then
  echo "  candidates: $(git -C "$HERE" rev-parse --short HEAD 2>/dev/null || echo unknown)"
else
  echo "  candidates: NO GIT — cannot say which commit these numbers describe"
fi
echo "  phpcs   : $([ -n "$PHPCS" ] && "$PHPCS" --version 2>/dev/null || echo 'NOT INSTALLED')"
echo "  phpstan : $([ -n "$PHPSTAN" ] && "$PHPSTAN" --version 2>/dev/null | head -1 || echo 'NOT INSTALLED')"
echo "  php     : $([ -n "$PHP" ] && "$PHP" -r 'echo PHP_VERSION;' 2>/dev/null || echo 'NOT INSTALLED')"
echo "  rules   : $([ -f "$RULES" ] && echo 'present (Claude Opus 5 code)' || echo 'MISSING')"
[ -n "$PHPCS" ] || note_norun "phpcs"
[ -n "$PHP" ]   || note_norun "php"
[ -f "$RULES" ] || note_norun "check_wporg_rules.php"
echo
printf '%-20s %-9s %-6s %-6s %-6s %-6s %-7s %-7s %s\n' \
  CANDIDATE hdr sec php74 enq i18n strict inline rules
printf '%-20s %-9s %-6s %-6s %-6s %-6s %-7s %-7s %s\n' \
  -------------------- --------- ------ ------ ------ ------ ------- ------- -----

DETAIL=""
for d in "$CANDS"/*/; do
  name=$(basename "$d")
  root=$(find "$d" -maxdepth 3 -name flosc.php -not -path '*/tests/*' 2>/dev/null | head -1)
  [ -z "$root" ] && continue
  root=$(dirname "$root")

  # --- version headers: the first ERROR in the T12 and T13 emails ---
  a=$(grep -m1 '^Requires at least:' "$root/readme.txt" 2>/dev/null | sed 's/.*: *//' | tr -d '\r')
  b=$(grep -m1 '^ \* Requires at least:' "$root/flosc.php" 2>/dev/null | sed 's/.*: *//' | tr -d '\r')
  if [ -z "$a" ] || [ -z "$b" ]; then hdr="NORUN"; note_norun "hdr:$name"
  elif [ "$a" = "$b" ] && printf '%s' "$a" | grep -qE '^[0-9]+\.[0-9]+$'; then hdr="ok $a"
  else hdr="FAIL"; fi

  sec=$(pc_err WordPress "--warning-severity=0 --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification,WordPress.Security.SafeRedirect,WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery" "$root")
  p74=$(pc_err PHPCompatibilityWP "--runtime-set testVersion 7.4-" "$root")
  enq=$(pc_err WordPress "--sniffs=WordPress.WP.EnqueuedResources" "$root")
  i18=$(pc_err WordPress "--sniffs=WordPress.WP.I18n" "$root")
  stc=$(pc_err WordPress "--sniffs=WordPress.PHP.StrictInArray" "$root")

  inl=$(grep -rnoE 'style="[^"]+"' "$root" --include=*.php 2>/dev/null \
        | grep -v '/tests/\|/admin/docs/\|/flosc_documentation/' | wc -l | tr -d ' ')

  rules="NORUN"
  rules_out="NOT RUN — $( [ -f "$RULES" ] || echo 'check_wporg_rules.php not found'; [ -n "$PHP" ] || echo 'php not installed' )"
  if [ -f "$RULES" ] && [ -n "$PHP" ]; then
    work="$TMP/$name"; mkdir -p "$work/tests"; cp -R "$root/." "$work/" 2>/dev/null; cp "$RULES" "$work/tests/"
    rules_out=$( (cd "$work" && "$PHP" tests/check_wporg_rules.php 2>&1) )
    rn=$(printf '%s' "$rules_out" | grep -oE 'findings *: *[0-9]+' | grep -oE '[0-9]+$')
    if [ -n "$rn" ]; then rules="$rn"; else rules="NORUN"; note_norun "rules:$name"; fi
  fi

  printf '%-20s %-9s %-6s %-6s %-6s %-6s %-7s %-7s %s\n' \
    "$name" "$hdr" "$sec" "$p74" "$enq" "$i18" "$stc" "$inl" "$rules"

  DETAIL="$DETAIL
===================================================================
 $name
===================================================================
"
  if [ "$inl" != "0" ]; then
    DETAIL="$DETAIL
-- inline style=\"...\" attributes (no sniff catches these) --
$(grep -rnE 'style="[^"]+"' "$root" --include=*.php 2>/dev/null | grep -v '/tests/\|/admin/docs/\|/flosc_documentation/' | sed "s|$root/||")
"
  fi
  DETAIL="$DETAIL
-- check_wporg_rules.php (Claude Opus 5 code -- read, do not sweep) --
$rules_out
"
done

echo
echo "  hdr=Requires at least   sec/php74/enq/i18n/strict = PHPCS errors (real tool)"
echo "  inline = inline style=\"\" attributes   rules = check_wporg_rules.php findings (Claude Opus 5 code)"
echo "  NORUN = the tool did not run. It is not a zero and it is not a pass."

# ---------------------------------------------------------------- PHPStan
if [ "$RUN_STAN" = "1" ]; then
  echo
  echo "=== PHPStan level 5 (real tool) ==="
  if [ -z "$PHPSTAN" ]; then
    echo "  NORUN — composer global require phpstan/phpstan szepeviktor/phpstan-wordpress"
    note_norun "phpstan"
  else
    for d in "$CANDS"/*/; do
      name=$(basename "$d")
      root=$(find "$d" -maxdepth 3 -name flosc.php -not -path '*/tests/*' 2>/dev/null | head -1)
      [ -z "$root" ] && continue
      root=$(dirname "$root")
      out=$("$PHPSTAN" analyse --level=5 --no-progress --error-format=raw "$root" 2>&1); rc=$?
      if [ "$rc" -ge 2 ]; then
        printf '  %-20s NORUN\n' "$name"; note_norun "phpstan:$name"
        printf '%s\n' "$out" | head -5 | sed 's/^/      /'
      else
        printf '  %-20s %s findings\n' "$name" "$(printf '%s' "$out" | grep -c .)"
      fi
    done
  fi
fi

# ------------------------------------------------- Plugin Check (Playground)
if [ "$RUN_PC" = "1" ]; then
  echo
  echo "=== Plugin Check, WordPress.org's own tool, via WordPress Playground ==="
  echo "    UNVERIFIED PATH: this block has never completed a run. It was written"
  echo "    in a container whose proxy returns 000 for playground.wordpress.net,"
  echo "    downloads.wordpress.org and api.wordpress.org. Whatever it prints"
  echo "    below is the first real evidence anyone has of whether it works."
  cat > "$TMP/pc-blueprint.json" <<'EOB'
{
  "$schema": "https://playground.wordpress.net/blueprint-schema.json",
  "login": true,
  "steps": [
    { "step": "installPlugin",
      "pluginData": { "resource": "wordpress.org/plugins", "slug": "plugin-check" },
      "options": { "activate": true } },
    { "step": "wp-cli", "command": "wp plugin check flosc --format=csv" }
  ]
}
EOB
  if ! command -v npx >/dev/null 2>&1; then
    echo "  NORUN — npx not found (install Node)"; note_norun "plugincheck"
  else
    for d in "$CANDS"/*/; do
      name=$(basename "$d")
      root=$(find "$d" -maxdepth 3 -name flosc.php -not -path '*/tests/*' 2>/dev/null | head -1)
      [ -z "$root" ] && continue
      root=$(dirname "$root")
      echo "--- $name"
      out=$(npx --yes @wp-playground/cli@latest run-blueprint \
            --blueprint="$TMP/pc-blueprint.json" \
            --mount="$root:/wordpress/wp-content/plugins/flosc" \
            --php=8.3 --verbosity=quiet 2>&1); rc=$?
      printf '%s\n' "$out" | tail -25
      [ "$rc" -eq 0 ] || { echo "  (exit $rc)"; note_norun "plugincheck:$name"; }
    done
  fi
fi

echo
echo "per-candidate detail follows"
echo "$DETAIL"

if [ -n "$NORUN" ]; then
  echo
  echo "DID NOT RUN:$NORUN"
  echo "Nothing above that reads NORUN is a pass. Exit 1."
  exit 1
fi
exit 0
