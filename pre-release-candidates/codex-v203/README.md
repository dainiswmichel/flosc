# Home run candidate v203 coded by Codex

- Baseline v202
- Public version and stable tag 8.0.0
- Canonical source commit `9bbdad474d5a2628a1dbcc72bf622f1af7d840eb`
- ZIP SHA-256 `ec16c7242fe91d191258be3059356a122addfae1643796e42ffe5eab50e09614`

Companion, index search, and lesson retrieval share a live post access decision before reading the body. The decision checks WordPress publication and password state, flow-specific retrieval depth and entitlement, and the permitted preview. FLOSC renders eligible content through WordPress on demand. Extensions can provide a plain component description with `flosc_rendered_post_description`. Index ranking uses permitted source, and title-only replies do not carry body-derived keywords.

For page content limits, IVR auto messages can use `content_limited && is_visitor` or `content_limited && is_guest` in the relevant phase. Their text and actions come from the flow. Provider guidance encourages the configured next step without promising content outside what the flow says it opens. Explicit catalog requests and named-page navigation retain their v202 paths.

## Verification

- The local WordPress REST fixture passed rendered shortcode and component descriptions, off-page content questions, direct navigation, explicit catalog item retrieval, WordPress password depth, title-only index results, registration and offer invitations, and entitled guest and member delivery.
- DA1 routing and purchase intent, VGM retrieval tiers, IVR variable processing, and cross-flow IVR entitlement checks passed.
- The packaged source tree matches the exact ZIP file for file. Production configuration and release credentials are outside the candidate.
- The same REST cases passed on a fresh WordPress 7.1.5 burner with Plugin Check 2.2.0 and WP_DEBUG enabled. WordPress reported no pending core, plugin, or theme updates.
- The exact ZIP passed PHP lint and PHP 7.4 compatibility. The full PHP WPCS run and the static release gate reported zero errors and 22 existing nonce recommendations.
- The official Plugin Check CLI run timed out without a report. The exact-ZIP gate therefore has one unverified check for submission.
