#!/usr/bin/env bash
#
# testing-bench.sh — test every FLOSC candidate against the WordPress.org
# review emails, using the real tools wherever a real tool exists.
#
# Run from the directory that contains pre-release-candidates/:
#
#     ./testing-bench.sh                 EVERY tool. This is the default.
#     ./testing-bench.sh --fast          skip PHPStan and Plugin Check
#     ./testing-bench.sh --no-stan       skip PHPStan only
#     ./testing-bench.sh --no-plugincheck  skip Plugin Check only
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

# Every tool runs by default. Confidence is the default state; skipping is the
# deliberate choice, and it says so on the line where it skipped.
RUN_STAN=1; RUN_PC=1; STEPS=0; RUN_RULES=0; VERIFY=0
for a in "$@"; do
  case "$a" in
    --fast)  RUN_STAN=0; RUN_PC=0 ;;
    --no-stan) RUN_STAN=0 ;;
    --no-plugincheck) RUN_PC=0 ;;
    --rules) RUN_RULES=1 ;;
    --verify) VERIFY=1 ;;
    --steps) STEPS=1 ;;
  esac
done

# ---------------------------------------------------------------- --verify
# Answers one question: is anything in the default run written by an AI agent?
# Prints, for every default check, the binary that decides it, its version, and
# the composer package it came from -- each one checkable without trusting this
# script. Run it whenever you want to re-establish that.
if [ "$VERIFY" = "1" ]; then
  echo "WHO DECIDES EACH CHECK IN THE DEFAULT RUN"
  echo
  printf '%-8s %-46s %s\n' COLUMN 'DECIDED BY' 'VERIFY IT YOURSELF'
  printf '%-8s %-46s %s\n' ------ '--------------------------------------------' '------------------'
  pc_ver=$([ -n "$PHPCS" ] && "$PHPCS" --version 2>/dev/null || echo 'NOT INSTALLED')
  st_ver=$([ -n "$PHPSTAN" ] && "$PHPSTAN" --version 2>/dev/null | head -1 || echo 'NOT INSTALLED')
  printf '%-8s %-46s %s\n' sec    "$pc_ver" 'composer global show squizlabs/php_codesniffer'
  printf '%-8s %-46s %s\n' php74  "$pc_ver" 'composer global show phpcompatibility/phpcompatibility-wp'
  printf '%-8s %-46s %s\n' enq    "$pc_ver" 'composer global show wp-coding-standards/wpcs'
  printf '%-8s %-46s %s\n' i18n   "$pc_ver" 'composer global show wp-coding-standards/wpcs'
  printf '%-8s %-46s %s\n' strict "$pc_ver" 'composer global show wp-coding-standards/wpcs'
  printf '%-8s %-46s %s\n' wporg  "$pc_ver" 'composer global show wp-coding-standards/wpcs'
  printf '%-8s %-46s %s\n' stan   "$st_ver" 'composer global show phpstan/phpstan'
  printf '%-8s %-46s %s\n' pcheck 'Plugin Check (WordPress.org)' 'wordpress.org/plugins/plugin-check/'
  echo
  echo "  binaries actually invoked:"
  echo "    phpcs   ${PHPCS:-NOT FOUND}"
  echo "    phpstan ${PHPSTAN:-NOT FOUND}"
  echo "    php     ${PHP:-NOT FOUND}"
  echo
  echo "  hdr    two greps of 'Requires at least:' in readme.txt and flosc.php."
  echo "         Written by Claude Opus 5. Read it in full:  grep -n \"Requires at least\" testing-bench.sh"
  echo "  inline one grep for 'style=\"...\"' in .php files."
  echo "         Written by Claude Opus 5. Read it in full:  grep -n 'style=' testing-bench.sh"
  echo
  echo "  NOT IN THE DEFAULT RUN:"
  echo "    rules  tests/check_wporg_rules.php -- 597 lines written by Claude Opus 5."
  echo "           It encodes the review emails and has produced false positives."
  echo "           It runs only when you pass --rules."
  if [ -f "$RULES" ]; then
    echo "           sha256 $(shasum -a 256 "$RULES" 2>/dev/null | cut -d' ' -f1 || sha256sum "$RULES" 2>/dev/null | cut -d' ' -f1)"
  fi
  echo
  echo "So: the default run is seven real-tool columns plus two greps you can read"
  echo "in one line each. No agent-written analysis decides any default number."
  exit 0
fi

# --------------------------------------------------------------- --steps
# Runs NOTHING. Prints the commands, numbered, one per line, so the whole
# bench can be done by hand with no script of mine in the middle.
if [ "$STEPS" = "1" ]; then
  P="${PHPCS:-phpcs}"; SP="${PHPSTAN:-phpstan}"
  # A quoted heredoc: nothing in here is expanded by this script, so what you
  # read is exactly what your shell will see. The four __TOKENS__ are filled in
  # with this machine's real paths by the sed below.
  cat <<'STEPS_EOF' | sed -e "s|__PHPCS__|$P|g" -e "s|__PHPSTAN__|$SP|g" -e "s|__IGN__|$IGN|g" -e "s|__CANDS__|$CANDS|g" -e "s|__RULES__|$RULES|g"
Every check this bench performs, as a command you run yourself.
The same procedure with copy buttons is in FLOSC-TESTING-PROCEDURE.html.

STEP 0  set the candidate once, then paste the rest as they are
        CAND=$(ls -d __CANDS__/*/flosc-by-*/flosc | head -1)
        echo $CAND

STEP 1  the version header - the first ERROR in the T12 and T13 emails
        grep -m1 '^Requires at least:' $CAND/readme.txt
        grep -m1 'Requires at least:' $CAND/flosc.php
        The two must match and must be major.minor, e.g. 7.0 - never 7.0.4

STEP 2  WordPress security sniffs: escaping, sanitizing, nonces, redirects, SQL
        __PHPCS__ --standard=WordPress --warning-severity=0 \
          --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification,WordPress.Security.SafeRedirect,WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery \
          --extensions=php --ignore='__IGN__' $CAND

STEP 3  PHP 7.4 compatibility - the WordPress.org minimum
        __PHPCS__ --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- \
          --extensions=php --ignore='__IGN__' $CAND

STEP 4  scripts and styles must be enqueued
        __PHPCS__ --standard=WordPress --sniffs=WordPress.WP.EnqueuedResources \
          --extensions=php --ignore='__IGN__' $CAND

STEP 5  translation function calls
        __PHPCS__ --standard=WordPress --sniffs=WordPress.WP.I18n \
          --extensions=php --ignore='__IGN__' $CAND

STEP 6  in_array / array_search without strict comparison
        __PHPCS__ --standard=WordPress --sniffs=WordPress.PHP.StrictInArray \
          --extensions=php --ignore='__IGN__' $CAND

STEP 7  seven sniffs for documented WordPress.org review rejections
        __PHPCS__ --standard=WordPress \
          --sniffs=WordPress.NamingConventions.PrefixAllGlobals,WordPress.WP.Capabilities,WordPress.WP.GlobalVariablesOverride,WordPress.WP.AlternativeFunctions,WordPress.PHP.DevelopmentFunctions,WordPress.PHP.NoSilencedErrors,WordPress.Security.PluginMenuSlug \
          --extensions=php --ignore='__IGN__' $CAND

STEP 8  PHPStan level 5. It MUST load the WordPress extension, or it reports
        thousands of phantom undefined-function errors. Write the config first.
        printf '%s\n' \
          'includes:' \
          "    - $HOME/.composer/vendor/szepeviktor/phpstan-wordpress/extension.neon" \
          'parameters:' \
          '    level: 5' \
          '    paths:' \
          "        - $CAND" \
          '    excludePaths:' \
          "        - $CAND/tests" > /tmp/flosc-phpstan.neon
        __PHPSTAN__ analyse -c /tmp/flosc-phpstan.neon --no-progress --memory-limit=1G

STEP 9  inline style="..." attributes. No sniff catches these. It is a grep.
        grep -rnE 'style="[^"]+"' $CAND --include=*.php | grep -v '/tests/'

STEP 10 Plugin Check - WordPress.org's OWN tool. UNVERIFIED: this command has
        never completed a run anywhere. Whatever it prints is the first evidence.
        npx --yes @wp-playground/cli@latest run-blueprint \
          --blueprint=pc-blueprint.json \
          --mount=$CAND:/wordpress/wp-content/plugins/flosc --php=8.3

STEP 11 the rules gate. THIS ONE IS MY CODE, not a real tool.
        Seven rules encoding the review emails. It has produced false positives.
        Read what it prints. Never sweep it.
        cp __RULES__ $CAND/tests/ && (cd $CAND && php tests/check_wporg_rules.php)

Exit code on every phpcs step: 0 clean, 1 or 2 findings, 3 or more it did not run.
A step that did not run is not a pass. Check it with:  echo $?
STEPS_EOF
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

# PHPStan, with szepeviktor/phpstan-wordpress actually loaded. Without that
# extension PHPStan does not know a single WordPress function and reports
# thousands of phantom "undefined function" errors — a number that looks like
# analysis and is nothing but a missing config. If the extension cannot be
# found, this reports NORUN rather than that phantom number.
stan_err() {
  local root="$1" name="$2" cfg ext out rc n
  if [ -z "$PHPSTAN" ]; then note_norun "phpstan"; echo "NORUN"; return; fi
  ext=""
  for c in "$HOME/.composer/vendor/szepeviktor/phpstan-wordpress/extension.neon" \
           "$HOME/.config/composer/vendor/szepeviktor/phpstan-wordpress/extension.neon" \
           "$HERE/vendor/szepeviktor/phpstan-wordpress/extension.neon"; do
    [ -f "$c" ] && ext="$c" && break
  done
  if [ -z "$ext" ]; then
    note_norun "phpstan-wordpress-extension"; echo "NORUN"; return
  fi
  cfg="$TMP/phpstan-$name.neon"
  {
    echo "includes:"
    echo "    - $ext"
    echo "parameters:"
    echo "    level: 5"
    echo "    paths:"
    echo "        - $root"
    echo "    excludePaths:"
    echo "        - $root/tests"
    echo "        - $root/vendor"
    echo "        - $root/node_modules"
  } > "$cfg"
  out=$("$PHPSTAN" analyse -c "$cfg" --no-progress --error-format=raw --memory-limit=1G 2>&1); rc=$?
  # PHPStan: 0 no errors, 1 errors found, anything else could not run.
  if [ "$rc" -eq 0 ]; then echo 0; return; fi
  if [ "$rc" -ne 1 ]; then
    note_norun "phpstan:$name"; echo "NORUN"; return
  fi
  n=$(printf '%s' "$out" | grep -c ':[0-9]*:')
  echo "${n:-0}"
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
printf '%-20s %-9s %-6s %-6s %-6s %-6s %-6s %-6s %-6s %-6s %s\n' \
  CANDIDATE hdr sec php74 enq i18n strict wporg stan inline rules
printf '%-20s %-9s %-6s %-6s %-6s %-6s %-6s %-6s %-6s %-6s %s\n' \
  -------------------- --------- ------ ------ ------ ------ ------ ------ ------ ------ -----

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

  # Seven sniffs that map to documented WordPress.org plugin-review rejections
  # and that nothing else in this bench was checking.
  wpo=$(pc_err WordPress "--sniffs=WordPress.NamingConventions.PrefixAllGlobals,WordPress.WP.Capabilities,WordPress.WP.GlobalVariablesOverride,WordPress.WP.AlternativeFunctions,WordPress.PHP.DevelopmentFunctions,WordPress.PHP.NoSilencedErrors,WordPress.Security.PluginMenuSlug" "$root")

  if [ "$RUN_STAN" = "1" ]; then stan=$(stan_err "$root" "$name"); else stan="skip"; fi

  inl=$(grep -rnoE 'style="[^"]+"' "$root" --include=*.php 2>/dev/null \
        | grep -v '/tests/\|/admin/docs/\|/flosc_documentation/' | wc -l | tr -d ' ')

  if [ "$RUN_RULES" != "1" ]; then
    rules="off"
  else
  rules="NORUN"
  rules_out="NOT RUN — $( [ -f "$RULES" ] || echo 'check_wporg_rules.php not found'; [ -n "$PHP" ] || echo 'php not installed' )"
  if [ -f "$RULES" ] && [ -n "$PHP" ]; then
    work="$TMP/$name"; mkdir -p "$work/tests"; cp -R "$root/." "$work/" 2>/dev/null; cp "$RULES" "$work/tests/"
    rules_out=$( (cd "$work" && "$PHP" tests/check_wporg_rules.php 2>&1) )
    rn=$(printf '%s' "$rules_out" | grep -oE 'findings *: *[0-9]+' | grep -oE '[0-9]+$')
    if [ -n "$rn" ]; then rules="$rn"; else rules="NORUN"; note_norun "rules:$name"; fi
  fi
  fi

  printf '%-20s %-9s %-6s %-6s %-6s %-6s %-6s %-6s %-6s %-6s %s\n' \
    "$name" "$hdr" "$sec" "$p74" "$enq" "$i18" "$stc" "$wpo" "$stan" "$inl" "$rules"

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
echo "  REAL TOOLS, not my code:"
echo "    sec     PHPCS WordPress — escaping, sanitizing, nonces, redirects, SQL"
echo "    php74   PHPCompatibilityWP at the WordPress.org PHP 7.4 minimum"
echo "    enq     scripts/styles must be enqueued (matches src= and rel=stylesheet only)"
echo "    i18n    translation function calls"
echo "    strict  in_array / array_search without strict comparison"
echo "    wporg   7 sniffs for documented .org review rejections: unprefixed globals,"
echo "            bad capabilities, WP global overrides, curl instead of the HTTP API,"
echo "            error_log/var_dump left in, @ suppression, __FILE__ menu slugs"
echo "    stan    PHPStan level 5 with the WordPress extension loaded"
echo
echo "  MINE, not a tool:"
echo "    hdr     a grep of the two Requires-at-least headers"
echo "    inline  a grep for inline style=\"\" — nothing else in this bench catches those"
echo "    rules   check_wporg_rules.php. 7 rules encoding the review emails. It has"
echo "            produced false positives. Read its findings. Never sweep them."
echo
echo "  NORUN = the tool did not run. It is not a zero and it is not a pass."
echo "  skip  = you asked for it to be skipped (--fast / --no-stan / --no-plugincheck)."

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
