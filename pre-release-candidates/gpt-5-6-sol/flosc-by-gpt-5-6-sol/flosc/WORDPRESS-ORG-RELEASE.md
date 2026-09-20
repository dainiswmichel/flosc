# WORDPRESS-ORG-RELEASE.md — FLOSC submission policy and evidence

Companion to `AGENTS.md`. This is the project's WordPress.org release policy:
the standards we commit to, the exceptions we allow, and where the evidence
lives. Any exception to a rule set here is NARROW and must be listed below with
a reason — broad ruleset exclusions are prohibited.

## Release bar (non-negotiable)

A candidate is "submission-ready" only when ALL of the following are green on
the **exact built zip** that will be uploaded:

1. `flosc-gate.sh` reports `RESULT: all gates PASS` (phpcs security sniffs with
   suppressions disabled, review-defect greps, readme disclosure, `php -l`,
   Plugin Check). An UNVERIFIED gate is a failure, never a pass.
2. `vendor/bin/phpcs .` (per `phpcs.xml.dist`) reports zero errors. Warnings
   must be reviewed; each accepted warning gets a written reason either inline
   (`-- reason`) or in the Exceptions table below.
3. `vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4-`
   reports zero errors.
4. `readme.txt` and `flosc.php` headers agree on Requires at least / Requires
   PHP, and match `phpcs.xml.dist` and `composer.json` floors.
5. A disposable WordPress install activates the zip, runs the sample flow, and
   the manual test matrix below is recorded with dates and a human signature.

## Exceptions table

| Finding/file | Why it stays | Reviewed by | Date |
|---|---|---|---|
| (add rows only with an explicit reason) | | | |

Every exception must be re-checked at each submission cycle; none are permanent.

## Manual test matrix (evidence to record before every submission)

- Fresh install + activation on the minimum declared WordPress and PHP.
- Upgrade from the previous shipped version (options migrate without loss).
- Visitor journey: Freeline → Login → Offer → Sale → gated Content on the sample data.
- Offer appears only at the correct stage; quiz result gates content; visitor bar shows.
- Admin: every settings screen saves, reloads, and reflects IVR/flow config.
- SSO callback lands on the flow's custom domain (not the install domain).
- Access control: non-member, member, and floscAdmin roles see only their content.
- Frontend accessible + no browser console errors; mobile width checked.

## Evidence

- This cycle's gate output, phpcs reports, and zip sha256 live in
  `build/evidence/` (created by `build-dist-zip.sh` / `flosc-gate.sh` runs).
- Long-form remediation work lives in
  `mvp_sprint/wordpress-remediation-plan/` (this project folder).
- Every historical Plugin Review Team finding maps to a regression grep in
  `flosc-gate.sh` (Gate 4) or a row in that plan.

## Date stamp

All dates use the Michel Date Stamp format: `YYYY-MMm-DDd`. Non-negotiable.