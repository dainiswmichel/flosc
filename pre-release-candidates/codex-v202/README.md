# Home run candidate v202 coded by Codex

- Public version and stable tag 8.0.0
- Canonical source commit `bf3158d652908ea98e97cab152beacfd489fce0c`
- Baseline v201
- ZIP SHA-256 `87d12a7c6839bc8635590252f49cea76b6a9b023b3bc1eeaf4e5f27a86ec0725`

A complete multiword WordPress title or slug can answer a chat turn with the canonical page link when a generic DA1 catalog reply would otherwise intercept it. FLOSC checks the current page first and uses the existing site index for a unique accessible match from elsewhere. Explicit catalog requests retain their route. Public title-only index entries can supply their title and URL. FLOSC still checks access to the destination. The only shipped files changed from v201 are the internal iteration header, the chat turn trait, the page context class, and the catalog class. The package retains the v201 login, MagicLink, SSO, IVR, offers, Starter Packs, and provider code.

## Verification

- Local WordPress REST fixtures reproduced the question and follow-up on and off the List of Works page. They also exercised slug matching, explicit catalog browsing and item selection, ambiguous results, exclusion, disabled page context, and access checks against unpublished and password-protected posts.
- A fresh WordPress 7.1.1 burner with Plugin Check 2.1.0 and WP_DEBUG verified the page link from the current page and Activity, Item 106, and the difference between a public title-only entry and a protected post.
- DA1 chat routing and purchase intent regression scripts passed. PHP lint and the PHP 7.4 compatibility scan passed.
- Full PHP WPCS reported zero errors and 22 existing nonce recommendations. The release gate on the exact ZIP reported the same counts with security suppressions disabled. All other static release checks passed.
- Plugin Check remains unverified. The release gate reported one unverified item and did not pass as a submission gate.
- The complete packaged source under `flosc-by-codex-v202/flosc/` matches the ZIP inventory file for file.
