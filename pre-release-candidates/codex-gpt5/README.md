# FLOSC 8.0.0 — Codex home-run candidate v160

This is the complete v160 review candidate built from the local v159 source.
The public plugin version and stable tag remain 8.0.0.

## Scope

v160 changes PayPal SDK delivery only:

- removes the PayPal JavaScript SDK from the initial `/chat/` page dependencies;
- loads the SDK asynchronously when a PayPal checkout modal opens;
- reuses one loader promise so repeated clicks do not inject duplicate scripts;
- preserves capture and subscription SDK parameters; and
- shows a loading state and a retry control if the SDK cannot load.

Stripe and the server-side payment endpoints are unchanged.

## Verification performed

- PHP syntax passed for every PHP file in the local source.
- PHPCompatibilityWP for PHP 7.4 passed.
- 52 PHP regression scripts and 6 JavaScript regression scripts passed.
- The PayPal lazy-load assertions in `check_companion_surface_contract.js` passed.
- `unzip -t` passed for the exact artifact.
- Live deployment completed on 2026-10m-18d. The live plugin reports internal
  iteration v160, the four changed runtime files match local SHA-256 values,
  `/chat/` returns HTTP 200, and its initial HTML contains no PayPal SDK URL.
- WP-CLI activation-status verification was blocked because the active Contact
  Form 7 package requires PHP 8.3 while the server CLI is PHP 8.0. The public
  web request remained HTTP 200.
- Full WordPress PHPCS and the release gate retain existing v159 findings. The
  release gate reported seven failed or unverified checks, including unavailable
  Plugin Check. This candidate is not represented as WordPress.org submission-ready.

## Artifact

```text
0458c0eb4b25f54fa17709b175add8a88c15c8ad9619f7255a01966bb86cfc22  flosc.zip
```

- ZIP size: 2,829,326 bytes
- ZIP entries: 283
- Shipping files: 243
- Shipping PHP files: 154
- Artifact root: `flosc/`
- Tests in artifact: none

## Repository identity

- Internal iteration: v160
- Baseline: local v159 source
- GitHub parent before publication: `2e34f2a1b0b7059930502c4bc8be70691faf11c6`
- Candidate owner: Codex
- Candidate source commit: `3e7234be7903cf4c5b0c890b736eab70f88cf944`
