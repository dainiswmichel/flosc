# V115 verification record

Owner: Codex. Date: 2026-10-01. Artifact SHA-256:
`6862a7f61f8a5356af6fc7061fcbaaf643ae5594a37ea38773840eacb952fc02`.

| Contract | Evidence before publication | Result |
| :-- | :-- | :-- |
| Preserve the working companion and bounded UI changes | V115 differs from V114.1 in four PHP files; the diff is documentation, whitespace, and assignment alignment only. V114.1 itself is based on the v107 companion plus the four bounded v108 copy/template edits. | Pass |
| Do not display the website as chat | `check_companion_surface_contract.js` checks app identification, fail-closed handling, top-level navigation, and frame guards: 17/17. In disposable WordPress the ordinary post contained no app marker while every FLOSC route did. | Pass |
| No nested companion | Companion chrome returns before boot inside every iframe; contract assertions passed. | Pass |
| Starter-pack installation | All four shipped packs installed successfully in real disposable WordPress: DA1 Catalog Sales, Membership Craft, Vegan Latvian Kitchen, and WordPress Content Membership. | Pass |
| Fresh rewrite registration | After those installs, all four flow rules were present without a second request or manual permalink save. Their routes returned HTTP 200 with FLOSC app markup. | Pass |
| New personality | A `codex-v115-qa` personality was saved through the production library functions and read back successfully. | Pass |
| Personality switching | Each of the four installed packs was assigned to `codex-v115-qa`; all four production calls returned `ok: true`. | Pass |
| Identity every turn | `check_identity_every_turn.php`, personality document, and profile-variable gates passed as part of 44/44 PHP gates. | Pass |
| Continuity and recovery | Turn-recovery, attachment-save, log-contract, auth-token, and request-protection gates passed as part of 44/44 PHP gates. | Pass |
| Ordinary WordPress behavior | Home and login returned 200, a published fixture post returned 200 without FLOSC app markup, and an unknown route returned 404. | Pass |
| PHP runtime | PHP lint passed on 200/200 files. No `debug.log` was created during installation, personality, routing, and HTTP tests with `WP_DEBUG` and `WP_DEBUG_LOG` enabled. | Pass |
| WordPress Coding Standards | Full extracted release ZIP: 144 PHP files scanned, 0 errors, 22 nonce-recommendation warnings in three files. | Pass with 22 disclosed warnings |
| PHP 7.4 floor | PHPCompatibilityWP: 0 findings. | Pass |
| Official WordPress Plugin Check | Plugin Check 2.1.0 with experimental checks, exact ZIP mounted in WordPress: exit 0, 0 errors, the same 22 nonce recommendations. | Pass with 22 disclosed warnings |
| Artifact integrity | 282 entries / 242 files, one `flosc/` root, `unzip -t` clean, all promised starter-pack assets present. | Pass |
| Live ChemiCloud | Pending publication and byte-identical deployment. | Pending |

The 22 warnings are not represented as “zero findings.” They are
`WordPress.Security.NonceVerification.Recommended` findings in
`includes/class-flosc-framework.php` (12), `includes/flosc-request.php` (7),
and `includes/magic-link/class-flosc-magic-link-trait.php` (3).
