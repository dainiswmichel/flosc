# FLOSC 8.0.0 — handoff to a fresh session

Written 2026-09-03 by the session that broke the thing described in §2 and fixed it.
Extended 2026-09-04 by the session that assembled the home-run candidate (§8).
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
    node --check assets/js/flosc-app.js
    php tests/check_packaging.php                              # inline styles, guards, version
    php tests/check_starter_pack_assets.php <built>/flosc.zip  # the artifact, not the tree

`tests/check_packaging.php` covers what Plugin Check looks at — inline style
attributes, hand-written script and stylesheet tags, direct-request guards, and
that the version still reads 8.0.0 in both files and agrees.

`tests/check_admin_js_binds.php` is the important one. It **executes** the admin
setup block against a jQuery stub and fails if it throws or if any of six named
controls binds nothing. It exists because a `var` map read during setup — but
assigned 700 lines later — threw and silently unbound everything after it.
A `typeof fn === 'function'` guard does not help: the function hoists, its data
does not.

`tests/test_param_sync.php` and `tests/test_tuning_queue.php` cut real functions
out of `admin/ai-configuration.php` by brace counting and run them in node. When
you change those functions, those tests are testing your new code, not a copy.

`tests/check_method_contract.php` reads the token stream of every trait the main
class composes and fails on any `$this->method()` with nothing behind it. It
exists because three deployment candidates shipped a public chat path that
called a method no class defined; `handle_chat()`'s `Throwable` catch turned the
fatal into "Something went wrong on our side just then", and the admin
ten-question test passed the whole time because it does not take that path.

`tests/check_action_handlers.php` fails if an admin page reaches from its own
document listener for a selector `assets/js/flosc-admin-events.js` already owns.
Two listeners on `document` both run; that is why the IVR accordion opened and
closed on one click, and why a Delete asked for confirmation twice.

`tests/journey-harness.php` is WP-CLI, loaded with `--require`, never shipped.
It drives every starter pack through visitor, guest and member on the **public**
route and checks what each pack declares about itself. See §8.

## 5. Known-good, do not redesign

Live provider/model lookup; per-parameter provider documentation links (Anthropic
deep-links `#create.<name>`, verified off the live page — the other three hosts
were unreachable, so they link to the page and say so); measured per-model notes;
sample setups; "ask the model what this parameter does"; the canonical visible
request; Michel Time Stamps (`flosc_mts_utc()` →
`2026y-08m-30d-UTC-11h-53m-29s-616ms`); Saved vs Autosaved labelling.

## 5b. Frozen. Do not touch without a named failure first.

- the page-wide Save, its label, and the last-save MTS line
- the Step 2b Model Tuning localised Save and its state machine
- the model catalog and parameter surfaces (Fetch models, Describe this model)
- the full-page → companion handoff, which keeps `session_id`/`journey_id`
  across the transition — do not "fix" it by resetting the session
- prohibitions living in two places by design: **Soul · rules** for what the
  personality must never do, **Behavior · language** for what it must never
  say. Someone reading soul.md will see one "Explicit Prohibitions" heading and
  have a plausible reason to consolidate them. It is deliberate.

The rule behind the list: every change needs a stated failing condition and a
way to observe it fixed. A cosmetic ask has neither, so it does not get made.
That is how a request to shorten a button label turns into a broken save.

## 6. Open, in priority order

1. **Prove the WOW end to end on a fresh site.** Never yet demonstrated:
   install a Starter Pack → connect a provider → two live turns as Betty →
   admin switches the dropdown → next turn is Dan. Everything else is secondary.
2. All four candidates **are** in this repo, on `main` under
   `pre-release-candidates/` — codex-gpt5, opencode-big-pickle, grok-4-6,
   github-copilot, with the Captain's live repair commits on top. A stale
   `origin/main` will hide them; fetch before concluding they are absent.
3. WordPress.org: custom auth (`determine_current_user`,
   `rest_authentication_errors`) is the reviewer's standing concern. Tokens are
   now revocable per-user (generation in the signature, bumped on logout and
   password change) — see `tests/test_auth_token.php`. Keep the hooks; they solve
   real cross-domain identity.
4. Plugin Check has never been run locally. `tests/check_packaging.php` covers
   the items it flags most, which is not the same thing as having run it.
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

## 8. The home-run assembly (2026-09-04)

Fourteen commits on `claude/ready-to-help-jsw2li`, from `477f252` to the branch
head. Record in `pre-release-candidates/claude-opus-5/`. **No ZIP and no copied
tree** — the candidate is the plugin at the repository root on this branch, and
`pre-release-candidates/` is excluded from the artifact by `.distignore` and by
the build's hard deny list.

Taken from the tested candidates: Codex GPT-5 (9/10) for the personality and
dispatch trunk; OpenCode big-pickle (8/10) for the no-hedge pair and its guards;
Grok 4.6 (5/10) for request protection, the IVR greeting variables, the three
line-break forms, and Public Title placement. GitHub Copilot (3/10) contributed
nothing — it lacks `flosc-model-catalog.php` and `flosc-model-parameters.php`
outright.

Three things had to work, and each has a gate:

- **Starter packs extract and work.** Each `pack.json` declares a `ships` list;
  the gate holds the build to it in both directions. That covers the two WXR
  files the READMEs tell a floscAdmin to import by hand, which nothing
  referenced and nothing protected.
- **Personalities work.** Full compiled profile on every turn, resolved fresh
  by id. The four shipped profiles went from ~6.3 KB to ~1 KB each by returning
  the sales trajectory to the flow section that already sends it — cheaper per
  turn than the short anchor was, and it keeps the character. The chat log now
  records which personality answered, so a switch is provable from the record.
- **A refresh mid-answer does not break the chat.** The browser mints a turn id
  before the request leaves; the next page load asks what became of it.
  Recovered, and the visitor is shown the answer they reloaded away from; not
  recovered, and the orphaned message is dropped so the next request carries a
  clean history. The same id makes a resend idempotent.

**What is still unproven, and only the Captain can prove it:** install on a
fresh WordPress, extract the packs, connect a provider, **build a personality
from scratch in the DA1 AI Personality Builder**, attach it to a starter pack,
and watch it carry that flow to its outcome — the PDF sale, the membership, the
contact exchange — in character. That is the delivery contract. Nothing in this
repository demonstrates it; the gates only remove the reasons it could not
happen.

The mechanical half of that matrix runs without him:

    wp --require=wp-content/plugins/flosc/tests/journey-harness.php \
       flosc-journey --transcript=/tmp/journey.txt
    wp --require=wp-content/plugins/flosc/tests/journey-harness.php \
       flosc-journey --pack=vegan-latvian-kitchen --personality=<the one he built>

It reads each pack's own declared content model, drives the public route, and
prints a grid. It cannot answer whether it sells gracefully or whether the
character is alive. Those are his to read.

### Two mistakes from this session, so they are not repeated

A commit went out with a failing test because a gate hardcoded a column count
that a new column made stale. Count things; do not assert a number.

Fifteen files got a redundant `ABSPATH` guard because the scan looked at the
first twenty lines instead of the whole file. Every one already had one, below a
longer docblock. Reverted in full. **Verify the failing condition before fixing
it** — that is the same rule as §5b, and it applies to the person applying it.
