# Condensed reply to plugins@wordpress.org — for your review and revision

Reply in-thread to **Re: [WordPress Plugin Directory] Review in Progress: FLOSC**
Review ID: `AUTO flosc/dainismichel/2Jun26/T12 13Sep26/4.2.1 (P0TDX321123HGN)`

~250 words. The reviewer asked for concise and said explicitly not to list changes.
Everything below is either a justification they need, or a confirmation they asked for.

---

## Body — copy from here

Hello, and thank you for the report.

I have uploaded a corrected version. Every listed issue is addressed, so I won't
restate them. Three things are worth flagging, because a re-scan may surface them
and they are deliberate.

**Nonce warnings (22, all `NonceVerification.Recommended`).** Three groups, none of
which a nonce fits:

- `ajax_serve_user_audio()` — the browser consumes these URLs as `<audio src>`, where
  a nonce would expire mid-playback and leak via referrer. Authorization runs first and
  independently (`viewer_can_stream_member_audio()`), then an HMAC over user id, session,
  filename and expiry is compared with `hash_equals()` inside a 24-hour window. The
  filename is pinned to `/^phrase-\d+\.(webm|mp4|m4a|ogg|wav)$/` before any path is built.
- `flosc_nav_param()` and its two siblings — read-only accessors. They sanitize a query
  parameter and return a string, writing nothing. Every caller that writes verifies its
  own nonce and capability.
- Magic-link and email-verification callbacks — the token in the URL *is* the credential
  and is verified before any session exists. No nonce could have been issued: the link
  arrives from the user's own inbox.

**Script tags.** Shipped PHP now contains no inline `<script>`, `<style>` or event
handlers; admin confirmations moved to a delegated handler in an enqueued file. The only
remaining match is `flosc_documentation/`, standalone reference pages WordPress never
renders, so there is no hook on which `wp_enqueue_*` could run. Happy to drop that bundle
from the package if you would prefer.

**Input sanitization**, raised in June and July as well as September, is closed rather
than patched: no `FILTER_UNSAFE_RAW` or `FILTER_DEFAULT` calls remain anywhere in the
plugin.

External services are documented under `= External Services =` in readme.txt — sixteen
numbered entries, each naming the service, the data sent and when, and links to its Terms
and Privacy Policy.

Checklist: I fixed the reported issues and reviewed for other instances of the same
patterns; I tested on a clean WordPress install with `WP_DEBUG` on and the plugin adds no
notice, warning or deprecation; and I uploaded the corrected version through "Add your
plugin".

Thank you for your time.

Dainis W. Michel
dainis@dainis.net

## Body — copy to here

---

## Notes for you, not the email

- **Every claim is measured**, not recalled. The 22 warnings are the complete current set
  from phpcs/WPCS against the unzipped artifact, scanned from a neutral path: 12 in
  `class-flosc-framework.php`, 7 in `includes/flosc-request.php`, 3 in
  `class-flosc-magic-link-trait.php`. Zero errors.
- **The sanitization line is new** versus the longer draft. It's there because that finding
  appears in all three reviews (June 14, July 12, September 14). Saying it is *closed*
  rather than fixed-again is the single most useful sentence in the reply.
- **Cut from the long draft:** the per-file line numbers, the `hash_equals`/expiry detail
  repeated twice, and the explanation of what each group of warnings is for. The reviewer
  re-reads the code anyway; they need the reason, not the tour.
- **The offer to drop `flosc_documentation/`** is the only sentence that commits you to
  anything. It costs nothing and forecloses a second round trip if they disagree. Delete
  it if you would rather not open that door.
- **Not mentioned, deliberately:** the version number, the zip checksum, and the PHPStan
  and Semgrep findings. They run none of those, and volunteering extra surface invites
  extra reading.
