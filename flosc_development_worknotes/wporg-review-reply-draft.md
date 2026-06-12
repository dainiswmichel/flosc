# WordPress.org review reply — working draft

Status: drafting (slowly, on purpose). Voice = Dainis, first person, friendly.
This file is a dev note; it is export-ignored and does not ship in the plugin zip.

Review ID: AUTO flosc/dainismichel/2Jun26/T2 2Jun26/4.0.1 (P0TDX321123HGN)
Plugin: FLOSC 8.0.0

---

## Section — the flosc.ai URL (addresses the "URL is invalid / 404" item)

> Hi again, Dainis here.
>
> One quick clarification on the repository/plugin URL: it now points to
> **https://flosc.ai**, which is live. flosc.ai is a presentation site that
> explains what FLOSC stands for — **F**reeline, **L**ogin, **O**ffer,
> **S**ale, **C**ontent.
>
> Here's the fun part: flosc.ai is itself served *as a floscFlow from
> dainis.net*. So the presentation site for the plugin is built with the
> plugin, on a different domain than the WordPress install that powers it.
> That's the multi-domain capability of FLOSC in action — one WordPress
> install can host multiple chatbots across multiple domains, each with its
> own content, without spinning up a separate WordPress site per domain.
>
> Slightly humorously: flosc.ai doesn't really have anything to "pitch."
> No upsell, no checkout — I genuinely can't think of one I'd want to build
> into it right now. It may share success stories over time, but at heart
> it's just a presentation chatbot for the FLOSC WordPress plugin. So if the
> reviewer visits and notices the Offer/Sale steps are quiet there, that's
> why — it's a demo of the framework, not a storefront.

---

## Section — items resolved since the June 2 review (DRAFT — to refine together)

Short, plain-language list of what changed. Keep it light; the team re-checks
the whole plugin anyway, so we don't need an exhaustive changelog.

- **External services** — added a full `== External Services ==` section to
  readme.txt covering every outbound service (AI providers, payment
  providers, OAuth/SSO providers) with purpose + terms + privacy links.
- **Custom CSS** — removed arbitrary CSS entry; chat styling is now generated
  from typed controls (color / scale / font) into CSS custom properties.
- **Writable data location** — all runtime writes (IVR/knowledge files) now go
  to the uploads directory (`uploads/flosc/…`) with an `.htaccess` deny guard,
  not the plugin folder.
- **REST permission callbacks** — payment/purchase endpoints now require a
  valid `wp_rest` nonce and bind the grant to the verified buyer; admin-only
  endpoints require `manage_options`; the member-content endpoint gates
  sale/content phases to entitled users.
- **File/path resolution** — uploads paths now use `wp_upload_dir()`.
- **Escaping** — shortcode and `the_content` filter returns are wrapped in
  `wp_kses_post()`.
- **register_setting()** — every registered setting passes a `sanitize_callback`
  (this was flagged but is in place; happy to point to specific lines).

---

## Section — on payments & the plugin being free (DRAFT)

Important framing for the team, before the payment-related items:

> A note on payments, because I think it frames everything below: **we are
> not selling FLOSC here.** The plugin is free, and it will always be free —
> that's a deliberate commitment to the WordPress community philosophy I
> care about being part of. The payment integrations in FLOSC aren't ours to
> profit from; they exist so that *anyone* — a solopreneur, a teacher, a
> small creator — gets the chance to build try-before-you-buy user journeys
> that introduce their audience to their own products and creations.
>
> FLOSC integrates with — or will integrate with — any and all payment
> methods that our floscAdmins (the site owners) ask for. The provider
> configuration is theirs to set up with their own credentials and accounts;
> FLOSC just routes through it. That's also why the External Services section
> lists the payment providers: they're optional, owner-configured outbound
> connections, not something FLOSC phones home with.

---

## Section — user creation & post-purchase login (DRAFT — the one that needs care)

Explain the *why* and the *controls*, plainly:

- FLOSC is a try-before-you-buy membership/content framework. The site owner
  (floscAdmin) configures the funnel; account creation is a deliberate,
  owner-chosen part of it, the same as any membership/LMS plugin.
- Payment is verified server-side before anything is granted (Stripe
  PaymentIntent retrieved + `status === succeeded`; PayPal captured/queried
  via the API).
- The access grant is bound to the verified buyer — Stripe matches the
  PaymentIntent's `metadata.user_id`; PayPal creates the account from the
  email PayPal returns, not from a client-supplied value.
- Login is issued only after the verified grant.
- Cross-domain login uses a single-use, short-lived server-issued token
  (5-minute transient, deleted on use). Tokens are signed with a dedicated
  server-only secret, not `wp_salt()`.
- The welcome magic-link is email-bound, high-entropy, and time-bounded
  (admin-configurable window + use cap), signed with the dedicated server
  secret. We keep it reusable on purpose — see the note just below.

> And a word on the magic-link, because it's a deliberate design choice, not
> an oversight: people are living with severe authorization fatigue. Someone
> should not have to clear a 2FA challenge just to read their local poet's
> inner-circle poems — they won't do it, and they'll quietly resent the
> creator for locking the content down to the point of inaccessibility. Once
> a visitor has gone to the trouble of registering for a FLOSC site's
> content, our job is to make reaching that content as effortless as
> possible. The magic-link does that. It is email-bound (it only reaches the
> address that registered), high-entropy, server-issued, and bounded by an
> admin-configurable window and use cap — so it is convenient *and*
> controlled. The result is happy, grateful, relieved readers, which is
> exactly the try-before-you-buy experience FLOSC exists to enable.

---

## Section — closing (DRAFT)

Friendly sign-off; thank the team; offer to point to exact lines for any
item they'd like to see.
