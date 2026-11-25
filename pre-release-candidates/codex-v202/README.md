# Home run candidate v202 coded by Codex

- Public version and stable tag 8.0.0
- Canonical source commit `be5df82fb65f6a257809276f1fab232eb0c27c60`
- Baseline v201
- ZIP SHA-256 `9334ec6246d4ecc99ba532197960995e3af687b9694909d8606411278ad71980`

A complete multiword WordPress title or slug can answer a chat turn with the canonical page link when a generic DA1 catalog reply would otherwise intercept it. FLOSC checks the current page first and uses the existing site index for a unique accessible match from elsewhere. Explicit catalog requests retain their route. The only shipped files changed from v201 are the internal iteration header, the chat turn trait, the page context class, and the catalog class. The package retains the v201 login, MagicLink, SSO, IVR, offers, Starter Packs, and provider code.

## Verification

- Local WordPress REST fixtures reproduced the question and follow-up on and off the List of Works page. They also exercised slug matching, explicit catalog browsing and item selection, ambiguous results, exclusion, disabled page context, and access checks against unpublished and password-protected posts.
- DA1 chat routing and purchase intent regression scripts passed. PHP lint and the PHP 7.4 compatibility scan passed.
- Full PHP WPCS reported zero errors and 22 existing nonce recommendations. The release gate on the exact ZIP reported the same counts with security suppressions disabled. All other static release checks passed.
- Plugin Check remains unverified. The release gate reported one unverified item and did not pass as a submission gate.
- The complete packaged source under `flosc-by-codex-v202/flosc/` matches the ZIP inventory file for file.
