# FLOSC 8.0.0 — handoff to a fresh session

Written 2026-09-03 by the session that broke the thing described in §2 and fixed it.
Read this before touching anything. It is written to be obeyed, not admired.

## 0. The constraint that outranks everything

**Resubmitting as 8.0.0. No version bumps. Ever.** `flosc.php` header and
`readme.txt` Stable tag both stay `8.0.0`.

The operator is nine months and five figures into this, is out of money, and
ships with:

    cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0 && ./flosc-ship.sh

Branch: `claude/ready-to-help-jsw2li`. Other agents push here too — **fetch before
you push, rebase your own commits on top, never force-push.**

## 1. The acceptance test (his words)

> dainis uploads flosc.zip to a new WordPress site, activates it, goes to
> StarterPacks and tries a few out, makes sure the AI API connections are
> working and tries the personalities on the flows — switching them live —
> WOW says Dainis — this is ready for re-submission!

The WOW is specifically: BubblyBetty answers, admin changes the Personality
dropdown mid-conversation, the next reply is unmistakably DadJokeDan. No reload.

## 2. What broke, and why it looked like something else

Step 2b (Model Tuning) lets an operator write a free-text request:

    temperature: 0.25
    top_p: 0.9
    stop_sequences: ["User:"]

**Anthropic answers 400 to temperature and top_p on the same request.** The AI
call failed, the flow fell back to scripted IVR copy, and the chat bubble still
showed the right personality name. So it read as "the personality designer is
broken" when the personality was fine and the HTTP request was dead.

Fixed in `9489de7`, `9e44d83` (runtime holds one back) and `6061f7e` (the panel
now says which one is held back). `flosc_sampling_conflicts_with_applied()` in
`includes/ai/flosc-provider-profiles.php` is the rule; `sampling_exclusive` on a
provider row is the data.

**Generalise the lesson: a visible identity being correct is not evidence the AI
received anything.** Check `response_source` before blaming a prompt.

## 3. The two laws of this codebase (learned expensively)

**A. The stored request text is the request.** A save may refuse invalid input.
A save that succeeds may not rewrite, reorder, renumber or delete one character
of what the operator typed. `flosc_reconcile_model_parameters()` mirrors
temperature/max_tokens into their fields and touches nothing else. Breaking this
looks like "Save ate my work" and costs days.

**B. Report only what you measured.** This session shipped nine green test
suites on a panel where 2 of 24 event handlers were bound, because every test
exercised a function in isolation and none ran the wiring. `php -l` passed.
`node --check` passed. The page was dead.

## 4. The gates. Run all of them. They are cheap.

    for f in tests/test_*.php tests/check_*.php; do php "$f"; done
    find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
    grep -rn 'style="[^"]*"' --include=*.php admin/ includes/ | grep -v 'esc_attr\|\$'   # must be 0

`tests/check_admin_js_binds.php` is the important one. It **executes** the admin
setup block against a jQuery stub and fails if it throws or if any of six named
controls binds nothing. It exists because a `var` map read during setup — but
assigned 700 lines later — threw and silently unbound everything after it.
A `typeof fn === 'function'` guard does not help: the function hoists, its data
does not.

`tests/test_param_sync.php` and `tests/test_tuning_queue.php` cut real functions
out of `admin/ai-configuration.php` by brace counting and run them in node. When
you change those functions, those tests are testing your new code, not a copy.

## 5. Known-good, do not redesign

Live provider/model lookup; per-parameter provider documentation links (Anthropic
deep-links `#create.<name>`, verified off the live page — the other three hosts
were unreachable, so they link to the page and say so); measured per-model notes;
sample setups; "ask the model what this parameter does"; the canonical visible
request; Michel Time Stamps (`flosc_mts_utc()` →
`2026y-08m-30d-UTC-11h-53m-29s-616ms`); Saved vs Autosaved labelling.

## 6. Open, in priority order

1. **Prove the WOW end to end on a fresh site.** Never yet demonstrated:
   install a Starter Pack → connect a provider → two live turns as Betty →
   admin switches the dropdown → next turn is Dan. Everything else is secondary.
2. `pre-release-candidates/opencode-big-pickle/` and
   `pre-release-candidates/flosc-by-codex-gpt5/flosc` are two rival builds. **They
   are not in this repo** — ask for them on a branch before claiming to have read
   them.
3. WordPress.org: custom auth (`determine_current_user`,
   `rest_authentication_errors`) is the reviewer's standing concern. Tokens are
   now revocable per-user (generation in the signature, bumped on logout and
   password change) — see `tests/test_auth_token.php`. Keep the hooks; they solve
   real cross-domain identity.
4. Plugin Check has never been run locally.
5. Stale `tests/` mirrored to live hosts by `deploy-live` (harmless — CLI-guarded
   — but untidy): `ssh chemicloud "rm -rf public_html/wp-content/plugins/flosc/tests"`

## 7. How to work with him

Captain / Boss / Maestro / Sir. Never issue him instructions — no "run this",
no "go do that". Offer; he decides.

He wants condensed first, then expanded, anchored to the exact symbol on the
line. He will catch a claim you cannot support, every time, and he is usually
right about his own product. When he says a thing does not work, **believe the
symptom and go measure it** — do not reason about your own code from memory.
A dead button means no submit; go look at the form, not at your last commit.

Every message in a long session re-reads its whole history. Keep sessions short.
Ask before spending.
