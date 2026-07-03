=== FLOSC ===
Contributors: dainismichel
Donate link: https://flosc.ai
Repository URI: https://flosc.ai
Tags: chatbot, quiz, ai, lead-generation, membership
Requires at least: 7.0
Requires PHP: 7.4
Tested up to: 7.0
Stable tag: 8.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build guided conversational journeys on WordPress that deliver value first, then unlock content and offers at exactly the right moment.

== Description ==

**FLOSC** (Freeline → Login → Offer → Sale → Content) is a WordPress plugin for building guided conversational journeys that move visitors through value-first experiences before presenting any offer.

Instead of "Are you interested in buying?" FLOSC asks "What should I help you with first?" Visitors complete quizzes, unlock personalized content, and engage with offers at the right moment-all inside your WordPress site, with your data staying in your database.

= What FLOSC Does =

* **Guided Journeys** - Build multi-step conversational flows that adapt based on visitor responses
* **Quiz-Based Engagement** - Create interactive quizzes that personalize visitor experience
* **Content Gating** - Unlock premium content only after quiz completion or specific actions
* **Offer Sequencing** - Show payment offers at the ideal moment in the visitor journey
* **Locally Stored** - All visitor data stays in your WordPress database by default
* **AI-Ready** - Integration with your own AI provider (OpenAI, Anthropic, Grok, etc.) for conversational responses via Bring-Your-Own-Key
* **WordPress Native** - Built as a standard WordPress plugin; no external platform required

= Free Version Includes =

* Create and manage unlimited FloscFlows — FLOSC chatbots served across unlimited domains
* Unlimited visitors and sessions
* Quiz builder with conditional branching
* Content unlock and offer gating
* Local storage in WordPress database
* Bring-Your-Own-Key AI provider setup
* Pre-configured example flows for pronunciation, music, and other use cases

= Use Cases =

* **Language Learning** - Guide learners through pronunciation assessment and personalized lessons
* **Online Courses** - Gate lesson modules based on quiz results before requesting payment
* **Lead Qualification** - Qualify prospects through conversational screening before routing to sales
* **Product Trials** - Let visitors experience product value (user-specific lessons, sample content) before sales pitch
* **Community Membership** - Build engagement journeys that lead to membership signup and renewals
* **Consulting Intake** - Conversational questionnaire that qualifies leads and captures intent before outreach
* **Sample Content First** - From listen to a few songs first here, to read some of my poems, to try these free recipes, FLOSC allows you to give samples before selling.  

= How It Works =

1. **Configure IVR Messages** - Expanding on interactive voice response (IVR) automated telephone system technology, in which callers receive and provide information by using voice or menu inputs, floscAdmins define chatbot conversational input-response flows: welcome messages, quiz questions, conditional branches, offers, and content unlock paths -- all while drawing from your WordPress content and configurable content database. 
2. **Set Up Your AI Provider** - Add your own provider API key (for example OpenAI, Anthropic, or xAI) for natural conversational responses.
3. **Build Your Flow** - Create quizzes, define conditions, add payment offers, and unlock content pages
4. **Publish** - Visitors access the flow and experience your full conversational sales journey on your WordPress site
5. **Review Results** - Track quiz completions, offer views, and conversions directly in WordPress

= Technical Details =

* Requires WordPress 7.0+
* No external services required for core functionality (flows run locally)
* BYOK AI integration (bring your own provider key, including OpenAI, Anthropic, and xAI)
* Payment integration
* REST API for programmatic access
* Unlimited FloscFlows; a single FLOSC install can serve chatbots across multiple domains

= Documentation & Support =

* Visit [flosc.ai](https://flosc.ai) for documentation and guides
* Review included example flows for pronunciation, music, and other guided experiences
* Author: [Dainis W. Michel](https://dainis.net)

= License & Attribution =

FLOSC is free software, released under the GNU General Public License, version 2 or later (GPLv2+). You're free to use, modify, and redistribute it — including commercially — as long as derivative works stay under the same license and the original copyright and license notices are preserved. Full text: https://www.gnu.org/licenses/gpl-2.0.html

== Installation ==

1. Download the FLOSC plugin and extract it to `/wp-content/plugins/` directory
2. Activate FLOSC from the **Plugins** menu in WordPress admin
3. Navigate to **Settings → FLOSC** to configure your first flow
4. Review the pre-loaded example flows to see how flows are structured
5. Create your first visitor page and embed your flow

= First Run Setup =

After activating:
1. Go to **Settings → FLOSC → IVR Messages** to review example conversations
2. Create a new page and select a flow from the page editor
3. Publish the page and visit it as a visitor to test your flow
4. Add your own AI provider key in **Settings → FLOSC → AI Provider** for conversational responses

== Frequently Asked Questions ==

= Do I need an external service to run FLOSC? =

No. FLOSC flows run entirely on your WordPress site by default. Your visitor data stays in your database.

Optional: You can bring your own AI provider key (for example OpenAI, Anthropic, or xAI) for conversational responses.

Without an AI API, the chatbot is a lot like a phone Interactive Voice Response (IVR) system. Instead of "callers," the chatbot has visitors, provides info, and users can input info that the IVR structure knows how to respond to. 

= What payment methods does FLOSC support? =

FLOSC works with the payment setup you already use. If your shop is configured (for example, WooCommerce), FLOSC routes offers through whatever gateway that shop uses, so you're not tied to any single provider. FLOSC also includes built-in provider configuration for Stripe, PayPal, and ClickBank (when configured), with test/sandbox workflows where available.

= Can I use FLOSC with my WordPress theme? =

Yes. FLOSC is theme-agnostic and works with any WordPress theme. Flows are embedded as regular WordPress shortcodes and adapt to your site's styling.

= How many flows can I create? =

As many as you like. FLOSC has no flow limit — the full plugin is free and runs unlimited FloscFlows (FLOSC chatbots), and a single FLOSC install can serve those chatbots across multiple domains. Install FLOSC on as many WordPress sites as you wish. Paid options cover services like human support, installation, profitability consulting, and managed AI credits — not the number of flows.

= Where does visitor data get stored? =

By default, all visitor data (quiz responses, completion status, payment records) is stored in your WordPress database. You can export or view it directly in WordPress admin.

= Can I customize the appearance of the flow? =

Yes. The FLOSC admin includes structured theming options for colors, fonts, bubble shapes, and layout, with CSS variables available for child-theme or Additional CSS overrides.

= Is FLOSC GDPR-compliant? =

FLOSC stores data in your WordPress database, giving you full control. You're responsible for maintaining GDPR compliance, privacy policies, and data handling according to your jurisdiction. FLOSC does not transmit visitor data to external services by default.

= What happens if I deactivate FLOSC? =

Your flow data and visitor records remain safely stored in WordPress. Simply reactivate FLOSC to resume operations. To permanently delete data, use the uninstall hook in plugin settings.

= How can I integrate my own AI provider? =

In Settings → FLOSC → AI Provider:
1. Choose your provider (for example OpenAI, Anthropic, or xAI)
2. Paste your API key (BYOK - you maintain your own account)
3. Test the connection
4. FLOSC will route conversational responses through your provider

== External Services ==

FLOSC core flow logic runs locally in WordPress. The services below power specific FLOSC features. When those features are enabled, calling these services is intentional and required for full functionality.

1. OpenAI (for AI chat and OpenAI Whisper speech-to-text)
Endpoint examples: https://api.openai.com/v1/chat/completions, https://api.openai.com/v1/responses, https://api.openai.com/v1/audio/transcriptions
Purpose: generate real-time AI chat responses and transcribe audio when OpenAI Whisper is selected as the STT provider.
Data sent: visitor prompt text, conversation context, model parameters, and uploaded audio payloads for transcription.
Service terms: https://openai.com/policies/terms-of-use
Privacy policy: https://openai.com/policies/privacy-policy

2. Anthropic (for AI chat responses)
Endpoint: https://api.anthropic.com/v1/messages
Purpose: generate real-time AI chat responses when Anthropic is selected as the AI provider.
Data sent: visitor prompt text, conversation context, and model parameters.
Service terms: https://www.anthropic.com/legal/consumer-terms
Privacy policy: https://www.anthropic.com/legal/privacy

3. xAI (for AI chat responses)
Endpoint: https://api.x.ai/v1/chat/completions
Purpose: generate real-time AI chat responses when xAI/Grok is selected as the AI provider.
Data sent: visitor prompt text, conversation context, and model parameters.
Service terms: https://x.ai/legal/terms-of-service
Privacy policy: https://x.ai/legal/privacy-policy

4. AssemblyAI (for speech-to-text in audio quiz flows)
Endpoint examples: https://api.assemblyai.com/v2/upload, https://api.assemblyai.com/v2/transcript
Purpose: transcribe visitor audio so pronunciation and audio quiz logic can run.
Data sent: uploaded audio and transcription request metadata.
Service terms: https://www.assemblyai.com/terms
Privacy policy: https://www.assemblyai.com/privacy-policy

5. Custom STT endpoint (for self-hosted or third-party speech-to-text)
Endpoint: user-configured in FLOSC settings
Purpose: send audio transcription requests to an endpoint specified by the site administrator.
Data sent: uploaded audio payload.
Service terms: determined by the configured endpoint provider.
Privacy policy: determined by the configured endpoint provider.

6. PayPal (for checkout and subscription flows)
Endpoint examples: https://api-m.paypal.com, https://api-m.sandbox.paypal.com
Purpose: create and validate payment and subscription transactions.
Data sent: order/subscription identifiers, amount/currency, and payment status data needed to complete transactions.
Service terms: https://www.paypal.com/us/legalhub/paypal/useragreement-full
Privacy policy: https://www.paypal.com/us/legalhub/privacy-full

7. Stripe (for checkout and subscription flows)
Endpoint: https://api.stripe.com/v1
Purpose: process payment intents, subscriptions, and payment-related webhook events.
Data sent: payment metadata (amount, currency, customer and transaction identifiers) required to complete transactions.
Service terms: https://stripe.com/legal
Privacy policy: https://stripe.com/privacy

8. ClickBank (for redirect checkout and IPN fulfillment)
Endpoint examples: https://sandbox.clickbank.net/checkout/order/hop.php, http://*.hop.clickbank.net/
Purpose: route buyers to ClickBank checkout and process purchase/refund/rebill events through IPN.
Data sent: transaction identifiers, product identifiers, receipt fields, and customer identity fields provided by ClickBank IPN.
Service terms: https://www.clickbank.com/legal/
Privacy policy: https://www.clickbank.com/privacy-policy/

9. Google OAuth (for social login)
Endpoint examples: https://accounts.google.com/o/oauth2/v2/auth, https://oauth2.googleapis.com/token, https://www.googleapis.com/oauth2/v2/userinfo
Purpose: authenticate users who choose Google single sign-on.
Data sent: OAuth authorization data and account profile fields returned by Google for authentication.
Service terms: https://policies.google.com/terms
Privacy policy: https://policies.google.com/privacy

10. Facebook OAuth (for social login)
Endpoint examples: https://www.facebook.com/v19.0/dialog/oauth, https://graph.facebook.com/v19.0/oauth/access_token, https://graph.facebook.com/v19.0/me
Purpose: authenticate users who choose Facebook single sign-on.
Data sent: OAuth authorization data and profile fields returned by Meta Graph API for authentication.
Service terms: https://www.facebook.com/terms.php
Privacy policy: https://www.facebook.com/privacy/policy/

11. Apple Sign In (for social login)
Endpoint examples: https://appleid.apple.com/auth/authorize, https://appleid.apple.com/auth/token
Purpose: authenticate users who choose Apple single sign-on.
Data sent: OAuth/OpenID authorization data and account profile fields returned by Apple for authentication.
Service terms: https://developer.apple.com/support/terms/
Privacy policy: https://www.apple.com/legal/privacy/

12. Microsoft OAuth (for social login)
Endpoint examples: https://login.microsoftonline.com/common/oauth2/v2.0/authorize, https://login.microsoftonline.com/common/oauth2/v2.0/token, https://graph.microsoft.com/v1.0/me
Purpose: authenticate users who choose Microsoft single sign-on.
Data sent: OAuth authorization data and account profile fields returned by Microsoft Graph for authentication.
Service terms: https://www.microsoft.com/servicesagreement
Privacy policy: https://privacy.microsoft.com/privacystatement

13. LinkedIn OAuth (for social login)
Endpoint examples: https://www.linkedin.com/oauth/v2/authorization, https://www.linkedin.com/oauth/v2/accessToken, https://api.linkedin.com/v2/userinfo
Purpose: authenticate users who choose LinkedIn single sign-on.
Data sent: OAuth/OpenID authorization data and account profile fields returned by LinkedIn for authentication.
Service terms: https://www.linkedin.com/legal/user-agreement
Privacy policy: https://www.linkedin.com/legal/privacy-policy

14. Flow-configured external quiz or pronunciation scoring provider
Endpoint examples: https://api.yourdomain.tld/analyze, https://api.yourdomain.tld/analyze-phrase, https://api.yourdomain.tld/finalize-session, https://api.yourdomain.tld/session/{id}
Purpose: score quiz submissions and finalize/retrieve session scoring data for flows that use an external scoring provider.
Data sent: quiz audio, answer payloads, and session-finalization data required by the configured provider. FLOSC also sends request-signing headers: X-FLOSC-Site, X-FLOSC-MTS (UTC Michel timestamp), and X-FLOSC-Signature (HMAC-SHA256 over payload_json + newline + mts + newline + site).
Configuration note: floscAdmins can configure a per-flow external scoring endpoint. If a flow uses an external scoring provider, quiz audio and related scoring payloads may be sent to that provider. Audio playback conversion dispatch is optional and flow-scoped through the Audio Conversion Provider setting (none|lesaep).

15. LeSAEp API (for LeSAEp pronunciation scoring and optional playback conversion)
Endpoint examples: https://api.lesaep.com/analyze, https://api.lesaep.com/analyze-phrase, https://api.lesaep.com/finalize-session, https://api.lesaep.com/session/{id}
Purpose: process LeSAEp pronunciation scoring requests when a flow is configured to use LeSAEp as its external scoring provider; optionally dispatch playback conversion jobs when Audio Conversion Provider is set to lesaep.
Data sent: quiz audio, phrase/answer payloads, session identifiers, and signed request headers (X-FLOSC-Site, X-FLOSC-MTS, X-FLOSC-Signature).
Service terms: https://lesaep.com/terms-of-service/
Privacy policy: https://lesaep.com/privacy/

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
* Initial stable 8.0.0 release for WordPress 7.0+ and PHP 7.4+
* Guided flow architecture with IVR routes, quiz branching, and offer/content gating
* Optional integrations for AI providers (BYOK), payment providers (including Stripe, PayPal, and ClickBank), and social sign-in providers
* Included admin documentation updates and required WordPress.org submission assets

== Screenshots ==

1. screenshot-1.png - SSO Settings: Configure single sign-on options and authentication behavior.
2. screenshot-2.png - Offers Settings: Manage offer configuration and sale-stage settings.
3. screenshot-3.png - Member Levels Settings: Configure membership-level access and progression settings.
4. screenshot-4.png - AutoPrompt Panel Settings: Configure quick prompts and response shortcuts.
5. screenshot-5.png - IVR Management Settings: Manage IVR message routes and conversational structure.
6. screenshot-6.png - Identity Settings: Set plugin name, title, and brand identity values.
7. screenshot-7.png - Flow Settings: Configure the core flow sequence and behavior.

== Code standard ==

FLOSC is written to exceed the WordPress plugin guidelines. Inputs are sanitized at the read site using the narrowest type-appropriate sanitizer; output is escaped at the point where FLOSC constructs markup. All file writes go to the uploads directory only, never to the plugin folder, because the plugin folder is replaced on upgrade and is publicly accessible. Every admin request that mutates state verifies a nonce and a capability before reading its parameters. REST endpoint access is entitlement-based: the permission_callback expresses login state or a named capability, not just a rate limit. The codebase is sectioned and commented to be readable as teaching material.

== Support & Contribution ==

For support, feature requests, or bug reports, visit:
* [FLOSC.ai](https://flosc.ai)
* [Project Site](https://flosc.ai)

FLOSC is an open-source project. Contributions welcome!

== Stay Connected ==

* Author: [solopreneur Dainis W. Michel](https://dainis.net)
* Project: [FLOSC.ai](https://flosc.ai)
