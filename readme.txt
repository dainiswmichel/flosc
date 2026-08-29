=== FLOSC ===
Contributors: dainismichel
Donate link: https://dainis.net/donate/
Tags: leads, sales, access, ai, chatbot
Requires at least: 7.0.4
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 8.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

(F)reeline --> (L)ogin --> (O)ffer --> (S)ale --> (C)ontent: try-before-you-buy WordPress journeys.

== Description ==

**FLOSC** (Freeline → Login → Offer → Sale → Content) is a WordPress plugin for building guided conversational journeys that move visitors through value-first experiences before presenting any offer.

Instead of "Are you interested in buying?" FLOSC asks "What should I help you with first?" Visitors complete quizzes, unlock personalized content, and engage with offers at the right moment - all inside your WordPress site.

= What FLOSC Does =

* **Guided Journeys** - Build multi-step conversational flows that adapt based on visitor responses
* **Quiz-Based Engagement** - Create interactive quizzes that personalize visitor experience
* **Content Gating** - Unlock premium content only after quiz completion or specific actions
* **Offer Sequencing** - Show payment offers at the ideal moment in the visitor journey
* **DA1 Catalogs** - Attach structured TSV catalogs to flows so floscAdmins can serve curated, flow-scoped datasets without hard-coding project-specific content
* **Starter Packs** - Install a complete working journey in one click: a flow, the example posts it talks about, and the visitor / guest / member gating already wired
* **Locally Stored** - All visitor data stays in your WordPress database by default
* **AI-Ready** - Bring-your-own-key chat with Anthropic, OpenAI, xAI, or Gemini (or IVR scripted only). OpenAI, Anthropic, and Gemini chat use the WordPress 7.0 AI Client — install the official provider plugin for the agent this flow attaches. Speech-to-text: AssemblyAI, OpenAI Whisper, or a custom endpoint.
* **WordPress Native** - Built as a standard WordPress plugin; no external platform required

= Included Features =

* Create and manage unlimited FloscFlows — FLOSC chatbots served across unlimited domains
* Unlimited visitors and sessions
* Quiz builder with conditional branching
* Content unlock and offer gating
* Local storage in WordPress database
* Bring-Your-Own-Key AI provider setup
* Personality Designer — visual workshop for authoring a reusable voice (wellsprings, density, morph, spectrograph); save to the install library and attach one personality per flow
* Pre-configured example flows for pronunciation, music, and other use cases

= Use Cases =

* **Assessments & lessons** - Guide learners through quizzes, topic feedback, and gated content
* **Online Courses** - Gate lesson modules based on quiz results before requesting payment
* **Lead Qualification** - Qualify prospects through conversational screening before routing to sales
* **Product Trials** - Let visitors experience product value (user-specific lessons, sample content) before sales pitch
* **Community Membership** - Build engagement journeys that lead to membership signup and renewals
* **Consulting Intake** - Conversational questionnaire that qualifies leads and captures intent before outreach
* **Sample Content First** - From listen to a few songs first here, to read some of my poems, to try these free recipes, FLOSC allows you to give samples before selling.  

= Starter Packs =

A new install has nothing to say. A starter pack fixes that in one click, so you can see a complete journey work before you configure anything.

Installing a pack creates a flow file in the FLOSC configuration folder, its categories, its example posts each stamped with the access level it is gated at, and — where the pack has one — its DA1 catalog and product file. Nothing is written to the plugin folder. A pack refuses rather than overwrites: if a flow file, a flow's settings, or a category of the same name already exists, FLOSC tells you instead of replacing your work. Removing a pack deletes exactly what that pack created, found by its own stamp — never by title, date or category name.

Two packs ship with FLOSC:

* **WordPress Content Membership Journey** - 100 deliberately silly WordPress posts gated as a real membership library: visitors read items 1-10, guests reach 1-30, members reach all 100. Curated by BubblyBetty. The journey sells membership; you set the price.
* **DA1 Catalog Sales Journey** - 50 over-serious instruction manuals for ordinary household tasks, served as a content-agnostic DA1 catalog: 4 items for visitors, 8 for guests, all 50 for members. Curated by DadJokeDan. The journey sells the compiled UberManual PDF for $10.

Each pack references a personality from the FLOSC library rather than bundling one, so you can swap the voice curating the journey at any time and watch the whole experience change.

Both are example content. Delete them, or take them apart and replace the subject with your own.

= How It Works =

1. **Configure IVR Messages** - Expanding on interactive voice response (IVR) automated telephone system technology, in which callers receive and provide information by using voice or menu inputs, floscAdmins define chatbot conversational input-response flows: welcome messages, quiz questions, conditional branches, offers, and content unlock paths -- all while drawing from your WordPress content and configurable content database. 
2. **Set Up Your AI Provider** - Attach Anthropic, OpenAI, xAI, or Gemini, paste your own API key, and (for OpenAI / Anthropic / Gemini) install that provider's official WordPress plugin. IVR stays scripted with no plugin.
3. **Build Your Flow** - Create quizzes, define conditions, add payment offers, and unlock content pages
4. **Publish** - Visitors access the flow and experience your full conversational sales journey on your WordPress site
5. **Review Results** - Track quiz completions, offer views, and conversions directly in WordPress

= Technical Details =

* Requires WordPress 7.0.4+ (see header Requires at least)
* No external services required for core functionality (flows run locally)
* BYOK AI: one WordPress AI Client (`wp_ai_client_prompt()`), plus official provider plugins for OpenAI, Anthropic, and Google. xAI has no official plugin yet (FLOSC hop). IVR is scripted and calls none of them.
* Payment integration
* REST API for programmatic access
* Unlimited FloscFlows; a single FLOSC install can serve chatbots across multiple domains

= Documentation & Support =

* Visit [flosc.ai](https://flosc.ai) for documentation and guides
* Review included example flows for pronunciation, music, and other guided experiences
* Author: [Dainis W. Michel](https://dainis.net)

= License & Attribution =

FLOSC is free software, released under the GNU General Public License, version 3 or later (GPLv3+). You're free to use, modify, and redistribute it — including commercially — as long as derivative works stay under the same license and the original copyright and license notices are preserved. Full text: https://www.gnu.org/licenses/gpl-3.0.html

== Installation ==

1. Download the FLOSC plugin and extract it to `/wp-content/plugins/` directory
2. Activate FLOSC from the **Plugins** menu in WordPress admin
3. Navigate to **Settings → FLOSC** to configure your first flow
4. Review the pre-loaded example flows to see how flows are structured
5. Create your first visitor page and embed your flow
6. If a flow uses OpenAI, Anthropic, or Gemini chat, install only that provider's official plugin: [AI Provider for OpenAI](https://wordpress.org/plugins/ai-provider-for-openai/), [AI Provider for Anthropic](https://wordpress.org/plugins/ai-provider-for-anthropic/), or [AI Provider for Google](https://wordpress.org/plugins/ai-provider-for-google/). IVR-only and xAI flows do not need those plugins.

= First Run Setup =

After activating:
1. Go to **Settings → FLOSC → IVR Messages** to review example conversations
2. Create a new page and select a flow from the page editor
3. Publish the page and visit it as a visitor to test your flow
4. Add your own AI provider key in **Settings → FLOSC → AI**. For OpenAI, Anthropic, or Gemini, also activate the matching official AI Provider plugin. Paste the key in FLOSC (this flow or All Flows), not in Settings → Connectors.

== Frequently Asked Questions ==

= Do I need an external service to run FLOSC? =

No. FLOSC flows run entirely on your WordPress site by default. Your visitor data stays in your database.

Optional: You can bring your own AI provider key (Anthropic, OpenAI, xAI, or Gemini) for conversational responses. OpenAI, Anthropic, and Gemini chat go through the WordPress AI Client and the matching official AI Provider plugin. Speech-to-text uses AssemblyAI, OpenAI Whisper, or a custom endpoint.

Without an AI API, the chatbot is a lot like a phone Interactive Voice Response (IVR) system. Instead of "callers," the chatbot has visitors, provides info, and users can input info that the IVR structure knows how to respond to. 

= What payment methods does FLOSC support? =

FLOSC works with the payment setup you already use. If your shop is configured (for example, WooCommerce), FLOSC routes offers through whatever gateway that shop uses, so you're not tied to any single provider. FLOSC also includes built-in provider configuration for Stripe, PayPal, and ClickBank (when configured), with test/sandbox workflows where available.

= Can I use FLOSC with my WordPress theme? =

Yes. FLOSC is theme-agnostic and works with any WordPress theme. Flows are embedded as regular WordPress shortcodes and adapt to your site's styling.

= How many flows can I create? =

As many as you like. FLOSC has no flow limit — the full plugin is free and runs unlimited FloscFlows (FLOSC chatbots), and a single FLOSC install can serve those chatbots across multiple domains. Install FLOSC on as many WordPress sites as you wish. Paid options cover services like human support, installation, profitability consulting, and managed AI credits — not the number of flows.

= What is the Personality Designer? =

An admin workshop for authoring an AI personality (wellsprings, density sequence, morph, spectrograph, trajectories). Saving writes a compiled profile and a workshop file into the install personality library. Each flow attaches exactly one library personality, then attaches that flow's chat API. Personalities are not chained. There is no provider-pack picker in FLOSC.

FLOSC chat APIs that receive the compiled profile as system text: Anthropic (`system`), OpenAI (`system` / `instructions`), xAI (`system`), Gemini (`systemInstruction`). IVR is scripted and does not call an AI API.

The same compiled profile is also mapped for these API field shapes (export/accommodation, not extra FLOSC HTTP adapters): Anthropic, OpenAI, xAI, Gemini, Mistral, Cohere, Together (Meta), Fireworks (Meta), AWS Bedrock, Azure OpenAI, OpenRouter, Perplexity.

Current per-provider pocket rules are dated MTS 26_08m_20d on the Personality Designer (What each API wants for this personality). Re-date when a vendor moves the field. Sampling is flow policy, not personality.

= What are DA1 Catalogs? =

DA1 provides a content-agnostic catalog structure with native compatibility for Dublin Core metadata and unrestricted catalog-specific parameters. A FLOSC flow can expose selected catalog items to Visitors, Guests, or Members and use the assigned catalog during the conversational journey without hard-coding project-specific content into plugin PHP. Catalogs are stored as TSV datasets in the WordPress uploads directory and can be assigned to specific flows.

= Where does visitor data get stored? =

By default, all visitor data (quiz responses, completion status, payment records) is stored in your WordPress database. You can export or view it directly in WordPress admin.

= Can I customize the appearance of the flow? =

Yes. The FLOSC admin includes structured theming options for colors, fonts, bubble shapes, and layout, with CSS variables available for child-theme or Additional CSS overrides.

= Is FLOSC GDPR-compliant? =

FLOSC stores data in your WordPress database, giving you full control. You're responsible for maintaining GDPR compliance, privacy policies, and data handling according to your jurisdiction. FLOSC does not transmit visitor data to external services by default.

= What happens if I deactivate FLOSC? =

Deactivating FLOSC leaves your flow settings, messages, and related data in the WordPress database so you can reactivate later.

= What happens if I delete FLOSC? =

Deleting the plugin from Plugins runs uninstall.php. That removes FLOSC options, FLOSC user/post/term meta, FLOSC custom tables, and FLOSC data under uploads (flosc, flosc-users, flosc-temp, flosc-catalogs including DA1 TSV catalogs). WordPress then removes the plugin files.

= How can I integrate my own AI provider? =

In Settings → FLOSC → AI:
1. Choose your chat provider: Anthropic, OpenAI, xAI, Gemini, or IVR (scripted only)
2. If you chose OpenAI, Anthropic, or Gemini, install and activate only that provider's official WordPress plugin (AI Provider for OpenAI, AI Provider for Anthropic, or AI Provider for Google). You do not need all three.
3. Paste your API key (BYOK — you maintain your own account), or save it under All Flows AI API Management
4. Test the connection
5. FLOSC routes OpenAI, Anthropic, and Gemini through the one WordPress AI Client (the matching official plugin owns vendor HTTP), and xAI through FLOSC’s own hop, using this flow’s attached personality as system text

= Is there one WordPress AI Client or three? =

One client. WordPress 7.0 ships a single AI Client (`wp_ai_client_prompt()`). It does not bundle vendors. Three official plugins register with that one client: AI Provider for OpenAI, AI Provider for Anthropic, and AI Provider for Google. FLOSC calls the client; those plugins own the vendor HTTP. A flow attaches one provider. A developer testing all three activates all three plugins. IVR uses none. xAI is still a FLOSC hop because WordPress has no official xAI plugin.

= Do I have to install all three official AI Provider plugins? =

No. Install only the plugin for the provider this flow attaches. IVR-only sites need none of them. xAI has no official WordPress provider plugin yet.

= Where do I put the API key — FLOSC or Settings → Connectors? =

Put it in FLOSC (this flow's AI tab, or All Flows AI API Management). FLOSC binds that key onto the WordPress AI Client for the prompt. Do not rely on Settings → Connectors for FLOSC chat.


== External Services ==

FLOSC core flow logic runs locally in WordPress. The services below power specific FLOSC features. When those features are enabled, calling these services is intentional and required for full functionality.

1. OpenAI (via WordPress AI Client + AI Provider for OpenAI, and FLOSC Whisper STT)
Chat: when this flow attaches OpenAI, FLOSC sends prompts through `wp_ai_client_prompt()` to the official AI Provider for OpenAI plugin, which communicates with OpenAI. FLOSC does not call OpenAI chat endpoints itself.
Whisper: when OpenAI Whisper is selected as the STT provider, FLOSC transcribes audio at https://api.openai.com/v1/audio/transcriptions (the official OpenAI provider plugin does not implement transcription).
Data sent: visitor prompt text, conversation context, and model parameters for chat; uploaded audio payloads for Whisper.
Service terms: https://openai.com/policies/terms-of-use
Privacy policy: https://openai.com/policies/privacy-policy

2. Anthropic (via WordPress AI Client + AI Provider for Anthropic)
When this flow attaches Anthropic, FLOSC sends prompts (including RAG tool declarations) through `wp_ai_client_prompt()` to the official AI Provider for Anthropic plugin, which communicates with Anthropic. FLOSC does not call Anthropic endpoints itself.
Data sent: visitor prompt text, conversation context, model parameters, and tool results when RAG is active.
Service terms: https://www.anthropic.com/legal/consumer-terms
Privacy policy: https://www.anthropic.com/legal/privacy

3. xAI (for AI chat responses)
Endpoint: https://api.x.ai/v1/chat/completions
Purpose: generate real-time AI chat responses when xAI/Grok is selected as the AI provider. There is no official WordPress xAI provider plugin yet, so this hop is FLOSC-owned.
Data sent: visitor prompt text, conversation context, and model parameters.
Service terms: https://x.ai/legal/terms-of-service
Privacy policy: https://x.ai/legal/privacy-policy

4. Google Gemini (via WordPress AI Client + AI Provider for Google)
When this flow attaches Gemini, FLOSC sends prompts through `wp_ai_client_prompt()` to the official AI Provider for Google plugin, which communicates with Google. FLOSC does not call Gemini generateContent itself. The compiled personality profile is sent as the system instruction.
Data sent: visitor prompt text, conversation context, and model parameters.
Service terms: https://developers.google.com/terms
Privacy policy: https://policies.google.com/privacy

5. AssemblyAI (for speech-to-text in audio quiz flows)
Endpoint examples: https://api.assemblyai.com/v2/upload, https://api.assemblyai.com/v2/transcript
Purpose: transcribe visitor audio so pronunciation and audio quiz logic can run.
Data sent: uploaded audio and transcription request metadata.
Service terms: https://www.assemblyai.com/terms
Privacy policy: https://www.assemblyai.com/privacy-policy

6. Custom STT endpoint (for self-hosted or third-party speech-to-text)
Endpoint: user-configured in FLOSC settings
Purpose: send audio transcription requests to an endpoint specified by the site administrator.
Data sent: uploaded audio payload.
Service terms: determined by the configured endpoint provider.
Privacy policy: determined by the configured endpoint provider.

7. PayPal (for checkout and subscription flows)
Endpoint examples: https://api-m.paypal.com, https://api-m.sandbox.paypal.com, https://www.paypal.com/sdk/js (browser PayPal JS SDK when a PayPal client ID is configured)
Purpose: create and validate payment and subscription transactions; load the PayPal JS SDK only on checkout screens that need it.
Data sent: order/subscription identifiers, amount/currency, and payment status data needed to complete transactions. Card/wallet data is handled by PayPal when used — not stored by FLOSC.
Service terms: https://www.paypal.com/us/legalhub/paypal/useragreement-full
Privacy policy: https://www.paypal.com/us/legalhub/privacy-full

8. Stripe (for checkout and subscription flows)
Endpoint examples: https://api.stripe.com/v1, https://js.stripe.com (browser Elements/Checkout SDK when Stripe is enabled)
Purpose: process payment intents, subscriptions, and payment-related webhook events; load Stripe.js only on checkout screens that need it.
Data sent: payment metadata (amount, currency, customer and transaction identifiers) required to complete transactions. Card data is handled by Stripe.js / Stripe servers when used — not stored by FLOSC.
Service terms: https://stripe.com/legal
Privacy policy: https://stripe.com/privacy

9. ClickBank (for redirect checkout and INS/IPN fulfillment)
Endpoint examples: https://VENDOR.pay.clickbank.net/?cbitems=ITEM (live seller payment link), https://sandbox.clickbank.net/checkout/order/hop.php (sandbox)
Purpose: route buyers to ClickBank checkout; grant access only after Instant Notification Service (INS v6+/v8 encrypted JSON) or verified legacy IPN — never on redirect alone.
Data sent: transaction identifiers, product item numbers (cbitems), receipt fields, and customer identity fields provided by ClickBank INS/IPN.
Service terms: https://support.clickbank.com/en/articles/10535340-clickbank-terms-of-sale
Privacy policy: https://support.clickbank.com/en/articles/10535346-clickbank-privacy-policy

10. Google OAuth (for social login)
Endpoint examples: https://accounts.google.com/o/oauth2/v2/auth, https://oauth2.googleapis.com/token, https://www.googleapis.com/oauth2/v2/userinfo
Purpose: authenticate users who choose Google single sign-on.
Data sent: OAuth authorization data and account profile fields returned by Google for authentication.
Service terms: https://policies.google.com/terms
Privacy policy: https://policies.google.com/privacy

11. Facebook OAuth (for social login)
Endpoint examples: https://www.facebook.com/v19.0/dialog/oauth, https://graph.facebook.com/v19.0/oauth/access_token, https://graph.facebook.com/v19.0/me
Purpose: authenticate users who choose Facebook single sign-on.
Data sent: OAuth authorization data and profile fields returned by Meta Graph API for authentication.
Service terms: https://www.facebook.com/terms.php
Privacy policy: https://www.facebook.com/privacy/policy/

12. Apple Sign In (for social login)
Endpoint examples: https://appleid.apple.com/auth/authorize, https://appleid.apple.com/auth/token
Purpose: authenticate users who choose Apple single sign-on.
Data sent: OAuth/OpenID authorization data and account profile fields returned by Apple for authentication.
Service terms: https://developer.apple.com/support/terms/
Privacy policy: https://www.apple.com/legal/privacy/

13. Microsoft OAuth (for social login)
Endpoint examples: https://login.microsoftonline.com/common/oauth2/v2.0/authorize, https://login.microsoftonline.com/common/oauth2/v2.0/token, https://graph.microsoft.com/v1.0/me
Purpose: authenticate users who choose Microsoft single sign-on.
Data sent: OAuth authorization data and account profile fields returned by Microsoft Graph for authentication.
Service terms: https://www.microsoft.com/servicesagreement
Privacy policy: https://privacy.microsoft.com/privacystatement

14. LinkedIn OAuth (for social login)
Endpoint examples: https://www.linkedin.com/oauth/v2/authorization, https://www.linkedin.com/oauth/v2/accessToken, https://api.linkedin.com/v2/userinfo
Purpose: authenticate users who choose LinkedIn single sign-on.
Data sent: OAuth/OpenID authorization data and account profile fields returned by LinkedIn for authentication.
Service terms: https://www.linkedin.com/legal/user-agreement
Privacy policy: https://www.linkedin.com/legal/privacy-policy

15. Flow-configured external quiz or pronunciation scoring provider
Endpoint examples: https://api.yourdomain.tld/analyze, https://api.yourdomain.tld/analyze-phrase, https://api.yourdomain.tld/finalize-session, https://api.yourdomain.tld/session/{id}
Purpose: score quiz submissions and finalize/retrieve session scoring data for flows that use an external scoring provider.
Data sent: quiz audio, answer payloads, and session-finalization data required by the configured provider. FLOSC also sends request-signing headers: X-FLOSC-Site, X-FLOSC-MTS (UTC Michel timestamp), and X-FLOSC-Signature (HMAC-SHA256 over payload_json + newline + mts + newline + site).
Configuration note: floscAdmins can configure a per-flow external scoring endpoint. If a flow uses an external scoring provider, quiz audio and related scoring payloads may be sent to that provider. Audio playback conversion dispatch is optional and flow-scoped through the Audio Conversion Provider setting (none|external).

16. Amazon product search links (optional affiliate offers)
Endpoint examples: https://www.amazon.com/s (search results URL with affiliate tag when Amazon affiliate is enabled)
Purpose: generate outbound search links so visitors can find products; FLOSC does not call Amazon Product Advertising API by default.
Data sent: search keywords and the site's Amazon associate tag in the query string when the visitor follows the link.
Service terms: https://affiliate-program.amazon.com/help/operating/agreement
Privacy policy: https://www.amazon.com/gp/help/customer/display.html?nodeId=GX7NJQ4ZB8MHFRNJ

= FLOSC Site Policies =

These are the public policy pages for the FLOSC install. They are first-party pages and should be listed in the plugin disclosure block so WordPress can review them directly.

Terms of Service: https://flosc.ai/terms-of-service/
Privacy Policy: https://flosc.ai/privacy/
Data Deletion: https://flosc.ai/data-deletion/
Platform Compliance: https://flosc.ai/platform-compliance/

== Upgrade Notice ==

= 8.0.0 =
Production-ready 8.x release with guided IVR flows, offer gating, BYOK AI support, and optional social sign-in providers.

== Changelog ==

= 8.0.0 =
* Initial stable 8.0.0 release for WordPress 7.0.4+ and PHP 7.4+
* Guided flow architecture with IVR routes, quiz branching, and offer/content gating
* Optional BYOK chat: one WordPress AI Client; official provider plugins for OpenAI, Anthropic, and Google; FLOSC hop for xAI; IVR scripted
* Payment providers (including Stripe, PayPal, and ClickBank) and social sign-in
* Included admin documentation updates for WordPress.org submission

== Code standard ==

FLOSC follows WordPress coding and security practices. Inputs are sanitized at read points using type-appropriate sanitizers, and output is escaped at render points. Runtime file writes are designed for uploads-based storage rather than the plugin directory. State-changing admin actions use nonce and capability checks. REST endpoints use explicit permission callbacks aligned to login state, capability, or metered public access. The codebase is sectioned and commented for maintainability.

== Support & Contribution ==

For support, feature requests, or bug reports, visit:
* [FLOSC.ai](https://flosc.ai)
* [Project Site](https://flosc.ai)

FLOSC is an open-source project. Contributions welcome!

== Stay Connected ==

* Author: [solopreneur Dainis W. Michel](https://dainis.net)
* Project: [FLOSC.ai](https://flosc.ai)
