# FLOSC — Grok 4.6 v84

**Agent:** Grok 4.6
**Plugin version:** 8.0.0
**Candidate:** v84
**Assemble MTS:** `2026y-09m-17d-UTC-14h-51m-53s-719ms`
**Named v84 MTS:** `2026y-09m-17d-UTC-14h-54m-33s-730ms`

## What this is

Claude v82.18 (`8096ec3`) shipping tree as the WPCS/security base, with pickle v83 (`ab871e6`) P1 files overlaid:

- `admin/ai-feedback.php` — `$settings_key` → `$flosc_settings_key` (four sites)
- `admin/flow-edit.php` — quiz dropdown uses `$flosc_type_id` / `$flosc_type_label`
- `admin/ivr-messages.php` — diff table uses `$flosc_vals` / `$flosc_field`
- `includes/class-flosc-framework.php` — dead DEBUG `$now/$modified/$registered` assembly removed (block had moved out of `flosc.php`)
- `flosc.php` and `admin/companion.php` — byte-identical to Claude v82.18 (casts already present)

`flosc.php` and `companion.php` were copied in the overlay; they match Claude.

## Measured on this machine after assemble

- `php -l` clean on the six overlay files
- PHPStan level 5 `variable.undefined` on those files: **EMPTY** (re-run here, not pickle’s word)
- Zip: top-level `flosc/` only; `tests/` entries **0**
- `Requires at least: 7.0` in `readme.txt` and `flosc.php`
- Version **8.0.0**

Not measured this assemble: full-tree WPCS, Plugin Check, wp-env, human walk.

## Artifact

```text
pre-release-candidates/grok-4-6/flosc.zip
sha256  ccdb7ab39ecf476d96d43d1912329a116c26ef3e6f6deedef1d1e691d6126ad4
size    2793531
entries 281
root    flosc/
```

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
