# verify-flosc.sh / flosc-gate.sh — exit propagation

`testing-environment/flosc-test` is fixed in this commit. The two scripts below
live outside the repository (`wordpress-remediation-plan/`), so the edits are
written out rather than patched.

Evidence this is needed: in the v140 run, `05-phpcs-project.txt` ends with
`phpcs exit: 1`, `14-phpstan.txt` with `phpstan exit: 1`, `21-playground.txt`
with `playground exit: 1`, and `20-flosc-test.txt` with
`SOMETHING FAILED OR DID NOT RUN. Read the blocks above; exit 1.` —
and `00-summary.txt` records all four blocks as `exit 0`.

## 1. Each block script must return its own status

Two wrapper shapes appear in the generated `.NN-*.sh` files. Both end on a
command that always succeeds, so the block's status is `tee`'s or `echo`'s.

Shape A — `{ … } | tee`, as in `.05-phpcs-project.sh`, `.06`, `.08`, `.09`,
`.21`:

    {
      "$PHPCS" … .
      rc=$?
      echo "phpcs exit: $rc"
      exit "$rc"
    } | tee "$REPORTS/05-phpcs-project.txt"
    exit "${PIPESTATUS[0]}"

Shape B — pipe then echo, as in `.07-nonce-full.sh`, `.14-phpstan.sh`,
`.15-semgrep.sh`:

    "$PHPSTAN" analyse … | tee "$REPORTS/14-phpstan.txt"
    rc=${PIPESTATUS[0]}
    echo "phpstan exit: $rc"
    exit "$rc"

The blocks that count their own failures (`.03-php-gates.sh`,
`.04-js-gates.sh`) print `Gate failures: $FAIL` and stop. They need
`exit $(( FAIL > 0 ? 1 : 0 ))` on the same subshell-carried basis.

## 2. verify-flosc.sh must aggregate and exit non-zero

The script records each block and ends on `echo`. It has no failure total and
no final non-zero exit:

    FAILED=0
    run_block() {                      # $1 = block name
      bash ".$1.sh"; rc=$?
      echo "$1 exit $rc"
      [ "$rc" -ne 0 ] && FAILED=$(( FAILED + 1 ))
      return 0
    }
    …
    echo "blocks failed: $FAILED"
    [ "$FAILED" -ne 0 ] && exit 1
    exit 0

## 3. "Did not run" must fail

`17-wp-env.txt` printed three empty Plugin Check headers and `home: 000`,
`login: 000`, `admin: 000`, `missing: 000` — connection refused — and the block
recorded `exit 0`. A block whose runtime is unavailable must exit non-zero:

    if ! curl -fsS -o /dev/null --max-time 10 http://localhost:8888/; then
      echo "NOT RUN: wp-env is not serving on 8888 — this is a failure, not a pass"
      exit 1
    fi

## 4. flosc-gate.sh reads the wrong variable

`agents.md` sets `PHPCS_VENDOR` to `flosc/vendor`. `flosc-gate.sh` reads `PHPCS`
and defaults to `flosc/vendor/bin/phpcs`, which is not present; the binary that
ran in the v140 log is `/Users/dainismichel/.composer/vendor/bin/phpcs`. Make
the script read the documented name and fail when the binary is absent:

    PHPCS="${PHPCS:-${PHPCS_VENDOR:+$PHPCS_VENDOR/bin/phpcs}}"
    PHPCS="${PHPCS:-$(command -v phpcs)}"
    [ -x "$PHPCS" ] || { echo "phpcs not found (PHPCS_VENDOR=$PHPCS_VENDOR)"; exit 1; }

## Not changed

Comment-blind matching in the gate greps (`FILTER_UNSAFE_RAW`,
`phpcs:ignore`-without-reason, `HTTP_HOST`, `10-headers`' `7.0.4`,
`18-external-url-status`' trailing punctuation) is real but is a separate
change to scripts outside this repository, and `HTTP_HOST` has a live read at
`includes/sso/class-oauth2-handler.php` that any rewrite must keep matching.
