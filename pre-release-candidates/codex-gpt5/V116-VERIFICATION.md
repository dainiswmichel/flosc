# V116 verification record

Owner: Codex. Date: 2026-10m-01d. Artifact SHA-256:
`6ff0788de10dcde8245f80b457742910910cad8e031b8008b87e026ab87a7002`.

| Contract | Evidence | Result |
| :-- | :-- | :-- |
| Preserve all working V115 code | Runtime diff is one shipped file, `assets/js/flosc-companion.js`, +13/−1. No PHP, CSS, template, setting, destination, or visible UI changed. | Pass |
| Full-page → companion | The hub snapshots `flosc_handoff_ref=1`, the actual `buildIframeUrl()` passes it to `/chat/`, and the app consumes the parked same-origin transcript. | Pass: automated actual-method test |
| Companion → full-page | The actual `openFullPage()` requests the iframe payload, parks the visitor transcript, adds the marker, and opens the full surface. | Pass: automated actual-method test |
| Same conversation, not URL payload | Both directions keep the transcript in `sessionStorage`; the URL carries only session identifiers and `flosc_handoff_ref=1`. | Pass |
| Correct hub behavior | `https://dainis.net/` is the configured Hub Companion URL, so Blog is the expected outer page after collapse. The inner companion frame remains the `/chat/` FLOSC app. | Pass: configuration/code contract |
| Do not display website as chat | The existing fail-closed frame/app-identification protections remain unchanged; companion surface contract is 21/21. | Pass |
| No nested companion | The existing top-frame mount guard remains unchanged and passes the surface contract. | Pass |
| FLOSC regression gates | 44/44 PHP gates and 4/4 JavaScript gates passed. | Pass |
| PHP runtime | PHP lint passed on 200/200 files. V116 changes no PHP. | Pass |
| WordPress Coding Standards | Shipped PHP is byte-identical to V115: 0 errors and 22 previously disclosed nonce-recommendation warnings. | Pass with 22 disclosed warnings |
| PHP 7.4 floor | Shipped PHP is byte-identical to V115, whose PHPCompatibilityWP scan had 0 findings. | Pass |
| Official WordPress Plugin Check | Plugin Check 2.1.0 with experimental checks ran against the exact mounted V116 ZIP: exit 0, 0 errors, the same 22 nonce recommendations. | Pass with 22 disclosed warnings |
| Artifact integrity | 282 entries / 242 files, one `flosc/` root, `unzip -t` clean; mounted runtime JS checksum equals the packaged file. | Pass |
| Visual Safari round trip | No controllable browser was exposed to this Codex session. Dainis must confirm full page → companion → full page with the same visible conversation. | Not yet confirmed |

The 22 warnings are unchanged from V115 and are not represented as “zero
findings.” No additional live rollback backup is created for V116; v107 remains
the designated rollback target.
