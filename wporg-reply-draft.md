# Reply to plugins@wordpress.org — draft

Reply in-thread to: **Re: [WordPress Plugin Directory] Review in Progress: FLOSC**
Review ID: `AUTO flosc/dainismichel/2Jun26/T12 13Sep26/4.2.1 (P0TDX321123HGN)`

The reviewer asked for a concise reply with clarifications only — explicitly *not* a
changelog ("I didn't list all the changes, as the team will review the entire plugin
again"). So this covers only the four things an automated re-scan is likely to flag
that are correct by design. Everything else was fixed rather than explained.

---

## Email body — copy from here

Hello, and thank you for the detailed report.

I have uploaded a corrected version. Every listed issue has been addressed, so I
won't restate them. There are four places where a re-scan may flag something that
is intentional, and I'd rather explain them now than cost the team a round trip.

**1. Signed media URLs — `ajax_serve_user_audio()`**

PHPCS reports twelve `WordPress.Security.NonceVerification.Recommended` warnings in
this handler. A nonce is the wrong control here: these URLs are consumed by the
browser as `<audio src="...">`, where a nonce would expire mid-session and travel in
referrers. Authorization runs first and independently — `viewer_can_stream_member_audio()`
is checked before any other parameter is read, and the request is rejected if it
fails. Origin is then proven by an HMAC covering the user id, session, filename and
expiry, compared with `hash_equals()`, with expiry bounded to 24 hours. The filename
is additionally pinned to `/^phrase-\d+\.(webm|mp4|m4a|ogg|wav)$/` before any path is
built, so no traversal sequence can survive.

**2. Read-only navigation parameters — `includes/flosc-request.php`**

Seven warnings land on `flosc_nav_param()`, `flosc_nav_param_present()` and
`flosc_nav_params()`. These are accessors: they read a query parameter, sanitize it,
and return a string. They change no state and write nothing, so there is nothing for
a nonce to protect. Every caller that *does* write verifies its own nonce together
with a capability check.

**3. Magic-link and email-verification callbacks**

Three warnings, in `class-flosc-magic-link-trait.php`. On these requests the token in
the URL *is* the credential, and it is verified before any session is established. A
nonce cannot apply: the link arrives from the user's own inbox, on a request this site
did not compose and for which no nonce could have been issued. The values are read as
a bounded, sanitized, length-capped boundary and treated as untrusted until the token
verifies.

**4. Script tags in `flosc_documentation/`**

Shipped PHP now contains no inline `<script>` or `<style>` and no inline event
handlers; admin confirmations were moved to a delegated handler in an enqueued file.
The only remaining match is `flosc_documentation/index.html`, a bundle of standalone
reference pages opened directly from the plugin folder. WordPress never renders them,
so there is no hook on which `wp_enqueue_*` could run. If you would prefer this bundle
not ship at all, say so and I will remove it from the package.

External services are documented under `= External Services =` in readme.txt: sixteen
numbered entries, each naming the service, exactly what data is sent and under what
circumstances, and links to its Terms of Service and Privacy Policy.

Checklist: I fixed the reported issues and reviewed for other instances of the same
patterns; I tested on a clean WordPress install with `WP_DEBUG` enabled and the plugin
adds no notice, warning or deprecation; and I uploaded the corrected version through
"Add your plugin".

Thank you for your time on this.

Dainis W. Michel
dainis@dainis.net

## Email body — copy to here

---

## Notes for you, not for the email

- The four justifications are measured, not asserted. The 22 warnings are the complete
  current set from phpcs/WPCS run against the unzipped artifact from a neutral path:
  12 in `class-flosc-framework.php` (13710–13737), 7 in `includes/flosc-request.php`
  (70, 74, 122, 211, 216, 220), 3 in `class-flosc-magic-link-trait.php` (234–235).
  Zero errors.
- Point 4's closing offer to remove the docs bundle is the only sentence in the body
  that commits you to anything; everything else just describes code that already
  exists. It is there deliberately — it costs nothing and forecloses a second round
  trip if the reviewer rejects the reasoning. Delete it if you would rather not open
  that door.
