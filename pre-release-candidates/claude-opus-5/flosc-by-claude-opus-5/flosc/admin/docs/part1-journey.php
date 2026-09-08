<?php if (!defined('ABSPATH')) exit; // Part 1: The Journey — FLOSC Documentation ?>

<h1 id="the-journey">Part 1: The Journey</h1>
<p>Where FLOSC came from, why it exists, and the decisions that shaped it. Not just what the code does, but <em>why</em> it ended up this way.</p>

<h2 id="the-problem">The Problem FLOSC Solves</h2>

<p>Anyone selling something online runs into the same wall: you have to convince a stranger to trust you — with money, or at least an email address — before they've experienced anything real. The usual answer is a "free trial" that still gates everything worth having behind a signup form, plus a marketing page full of promises.</p>

<p>FLOSC is built on a different bet: <strong>let the visitor experience the thing first, then ask.</strong> Give value before the form, before the offer, before the sale. If the experience is good, it does the convincing.</p>

<p>That bet doesn't care what you're selling. The structure is the same whether the visitor is sampling a poet's work, taking a lesson, getting a piece of analysis, or being qualified as an enterprise lead. FLOSC is a try-before-you-buy framework for everyone from your local poet to the world's largest corporations. The content is yours; FLOSC is the shape of the journey around it.</p>

<h3 id="content-agnostic">Content-Agnostic by Design</h3>

<p>FLOSC is deliberately indifferent to subject matter. It manages the path a visitor takes from first contact to purchase — what they see for free, when they're asked to sign in, when the offer appears, how the sale completes, and what unlocks afterward. What flows through that path is entirely up to the admin.</p>

<p>The same framework runs a songwriting course, a music-theory program, a pronunciation assessment, a paid newsletter, a consulting intake funnel, or a corporate product trial. None of those require a code change — only different content, copy, and configuration.</p>

<h3 id="first-deployment">The First Deployment Was Pronunciation</h3>

<p>The first thing actually built on FLOSC was <strong>LeSAEp</strong> (Learn Excellent Standard American English Pronunciation): a short quiz that identifies which sounds a speaker is missing, followed by a guided conversation about what to practice. It was a good first deployment because it exercises every part of the framework — a quiz, a result, conditional follow-ups, an offer, gated content.</p>

<p>But pronunciation didn't drive the design, and it isn't the point. Songwriting, solfeggio, or any number of other subjects could have been the first deployment just as easily. LeSAEp is one worked example of the framework, not its origin story. When you see pronunciation examples in this documentation, read them as "here is the framework doing its job," not "here is what FLOSC is for."</p>

<h3 id="flosc-acronym">The FLOSC Acronym — What Each Letter Means</h3>

<p>FLOSC names the five phases the system manages, in order:</p>

<h4 id="flosc-five-phases">Freeline → Login → Offer → Sale → Content</h4>

<dl>
    <dt><strong>F — Freeline</strong></dt>
    <dd>The visitor phase. They've arrived but haven't logged in. They get the free sample — a quiz, a taste of the content, a limited conversation. "Freeline" is the coined word for it: the line you can cast without a license.</dd>

    <dt><strong>L — Login</strong></dt>
    <dd>Account creation. A visitor who wants to keep their results or go further creates an account and becomes a Guest. Anything they did as a visitor — a quiz score, for example — carries over: it's held client-side as bridge data and attached to the new account on registration.</dd>

    <dt><strong>O — Offer</strong></dt>
    <dd>The Guest phase. Logged in, not yet a customer. They can see their results, continue the conversation, and sample more — but the paid content sits behind an upgrade. The offer is timed to appear after value has been delivered, not before.</dd>

    <dt><strong>S — Sale</strong></dt>
    <dd>The payment phase. Stripe or PayPal handles the transaction. On success the account's access level upgrades and the user moves straight into Content.</dd>

    <dt><strong>C — Content</strong></dt>
    <dd>The Member phase. Full access. Whatever was gated is now open, and the conversation shifts from selling to delivering.</dd>
</dl>

<p>Every scripted message, offer trigger, and AI instruction is tagged to one of these phases. The system always knows where in F→L→O→S→C the current user is, and behaves accordingly.</p>

<h2 id="the-hypothesis">The Hypothesis</h2>

<p>The central idea is that a conversational interface — one that responds to where the visitor is in the journey — moves people from curiosity to purchase better than a static landing page, and delivers content better than a traditional dashboard. The interface adapts; the visitor isn't navigating a menu, they're having an exchange.</p>

<h3 id="ai-as-guide">AI as Guide, Not as Gimmick</h3>

<p>The AI in FLOSC isn't a chat bubble bolted onto a content site. It's the primary interface — content, offers, results, and user state all flow through the conversation. The AI is given enough context to be specifically relevant rather than generic:</p>
<ul>
    <li>What the visitor has done so far (e.g. which quiz, which answers)</li>
    <li>What content is available and what they've already seen</li>
    <li>Which phase of the flow they're in</li>
    <li>The full text of every scripted message that could apply</li>
    <li>The deployment's identity: name, voice, and purpose</li>
</ul>

<p>In an educational deployment, the AI acts like a tutor; for a storefront it acts like a knowledgeable guide; for a sales funnel it qualifies and routes. Same mechanism, different content.</p>

<h3 id="ivr-architecture">The IVR System — Scripted First, AI Second</h3>

<p>FLOSC borrows the term IVR (Interactive Voice Response) from the phone trees you hit when you call a bank — scripted, deterministic, reliable. In FLOSC, the IVR is a library of admin-written responses that fire when their conditions match: user state, quiz score, what the visitor said. Most messages resolve against the IVR instantly, without ever calling an AI API. Only when nothing matches does the AI take over.</p>

<p>The reason for this order is control and cost. The admin scripts the moments that matter — the welcome, the result explanation, the offer, the post-purchase message — and knows they'll render as written rather than being rephrased by a model. The AI fills the space in between. That keeps API usage predictable and keeps the high-stakes moments off the model.</p>

<h2 id="roads-not-taken">Roads Not Taken</h2>

<h3 id="why-not-react">Why Not React or Vue?</h3>

<p>React and Vue bring build pipelines, dependency trees, bundlers, and a fast-moving JavaScript ecosystem. A WordPress plugin has to install on arbitrary hosting — including shared hosts with no Node.js — so that tooling is a liability, not an asset. The FLOSC frontend is a single vanilla ES6+ file (<code>flosc-app.js</code>) with no build step, loaded with a standard <code>wp_enqueue_scripts</code> call. It runs anywhere WordPress runs.</p>

<h3 id="why-not-lms">Why Not an Existing LMS?</h3>

<p>LearnDash, LifterLMS, and Tutor LMS are capable platforms, but they assume a particular shape: courses → lessons → quizzes, with a catalog and progress bars, optimized for self-paced study. FLOSC's model is a guided conversation, and it isn't limited to education at all. FLOSC can sit alongside those tools through optional hooks (LearnDash, WP Pro Quiz, Quiz and Survey Master), but it was built fresh because none of them had the primitives for a flow-driven, content-agnostic funnel.</p>

<h3 id="why-not-native-app">Why Not a Native App?</h3>

<p>A native app would get better microphone access and push notifications, but it would also mean App Store review, separate iOS and Android codebases, device-specific bugs, and a deployment pipeline divorced from the admin's existing site. Web-first means the framework ships the same way a blog post does. The companion widget embeds in any iframe; the interface adapts to mobile through responsive CSS.</p>

<h3 id="why-not-saas">Why Not SaaS From Day One?</h3>

<p>Multi-tenant SaaS — its own database, billing, and user management — was considered and set aside for the first version. The plan was to build a first flow, prove it on a real deployment, then generalize. The multi-flow architecture (v1.2.2) is the path toward multi-tenancy, but delivered inside WordPress, where the admin already has hosting, a domain, and a user table. No new infrastructure to stand up.</p>

<h3 id="why-wordpress">Why WordPress</h3>

<p>The honest answer isn't that WordPress is the best possible platform for this. It's that the target users — independent creators, educators, small businesses — overwhelmingly already have WordPress sites. They know the admin, they understand posts and categories, and their content is already there. Meeting users where they are is a product decision, not a technical one.</p>

<h4 id="wp-tradeoffs">Tradeoffs Accepted</h4>
<ul>
    <li><strong>No build step</strong> means no tree-shaking and no TypeScript. Accepted — the code stays readable to anyone who knows PHP and vanilla JS.</li>
    <li><strong>WordPress nonces expire,</strong> which requires client-side retry logic. Accepted, documented, and handled.</li>
    <li><strong>The options table</strong> is used for settings rather than a custom schema. Accepted — WordPress's update APIs are well-tested.</li>
    <li><strong>PHP version diversity.</strong> Accepted — the code targets PHP 7.4+ and avoids features that break on common hosting.</li>
</ul>

<h4 id="wp-advantages">Advantages That Came for Free</h4>
<ul>
    <li><strong>The content API</strong> (<code>wp_insert_post</code>, <code>wp_create_category</code>, <code>get_posts</code>) makes content-as-posts a natural fit, with taxonomy and search built in.</li>
    <li><strong>User management</strong> — registration, login, password reset, roles — is handled by WordPress core.</li>
    <li><strong>WXR import</strong> ships sample data as a standard WordPress import file, so a fresh install can have a working demo in a few minutes.</li>
    <li><strong>Category protection</strong> maps cleanly onto access control: mark a category as FLOSC-protected and only paid members see those posts.</li>
</ul>

<h2 id="evolution-timeline">How It Got Here</h2>

<h3 id="era-static-quiz">Era 1: Static Quiz Page (v0.1–v0.3)</h3>
<p>A static HTML quiz — questions in JavaScript arrays, scoring client-side, results shown inline. No server, no AI, no WordPress. It proved the assessment concept worked.</p>

<h3 id="era-chat-interface">Era 2: The Chat Interface (v0.4–v1.0)</h3>
<p>A conversation replaced the static results page. Instead of showing a score, the chat explained what it meant — in dialogue. WordPress involvement was still minimal; the chat was embedded as a shortcode.</p>

<h3 id="era-ivr-system">Era 3: The IVR System (v1.1–v1.4)</h3>
<p>The IVR parser gave admins control over what the assistant says. Rather than hardcoding responses, the admin writes a Markdown file of message conditions that the parser evaluates at runtime. This separated "what the assistant says" from "how the system works," a split that persists through every later version.</p>

<h3 id="era-ai-integration">Era 4: AI Integration (v1.5–v1.7)</h3>
<p>An AI model was added as the fallback for anything the IVR didn't cover. Because the IVR handles scripted moments, the AI only deals with the gaps — which keeps cost predictable and keeps key messages reliable. Multiple providers are supported on a bring-your-own-key basis.</p>

<h3 id="era-payment-access">Era 5: Payment and Access Control (v1.8–v1.9)</h3>
<p>The Sale system landed: payment providers (Stripe, PayPal), offer management, token-based credit, and affiliate support. Access control tied purchase records to content visibility, the Visitor / Guest / Member model was formalized, and chat logging with admin feedback arrived in v1.9.</p>

<h3 id="era-multi-flow">Era 6: Multi-Flow Architecture (v1.9.5+)</h3>
<p>The Flow system turned single-product FLOSC into a multi-product one. A single WordPress install can run several independent flows, each with its own branding, IVR file, AI configuration, quiz, offers, and domain mapping — for example a publisher running separate funnels for a poetry collection, a workshop, and a membership, all from one plugin.</p>

<h4 id="version-archaeology">What the Version Jumps Mean</h4>
<p>Major version increments correspond to architectural additions, not rewrites. v1.x is the core chat + IVR + AI stack; v2.x introduced identity migration (tagline → title); v3.x added the multi-lesson-group model and cross-domain auth tokens; v4.x added the admin test panel and structured quiz management.</p>

<h4 id="version-count">On the Version Count</h4>
<p>FLOSC went through 90+ point releases before reaching v2.0. That reflects a ship-often pace — versioning every meaningful change rather than batching. A date-stamp convention (YYYY-MMm-DDd) runs through the code comments, so it's easy to trace when each piece was added without a separate changelog.</p>

<h2 id="design-principles">Design Principles</h2>

<h3 id="principle-ivr-is-king">Content Drives the Experience, Not Code</h3>
<p>The most important rule: the admin's IVR content shapes what users experience, and the code's job is to evaluate that content quickly, reliably, and expressively. If changing a response requires editing PHP, that's a bug in the design.</p>

<h3 id="principle-admin-controls">Configurable From the Admin</h3>
<p>Every part of the experience is set from the WordPress admin without touching code: AI voice, scripted messages, offer timing, chat style, content assignments, quiz content, email templates, navigation. Keeping it that way took discipline — every feature had to answer "should this be hardcoded or configured?" with "configured." The current style model leans into structured controls and template presets so the result feels designed, not improvised.</p>

<h3 id="principle-no-hardcode">Never Hardcode What Can Be Configured</h3>
<p>Name, tagline, emoji, colors, AI persona, quiz questions, content category, offer price — none of it lives in the source. It's all in the database, editable from the admin. That's what lets the same plugin run a poet's storefront, a pronunciation school, a math tutor, or a fitness program without a code change.</p>

<h3 id="principle-sample-data">Sample Data Is a Deliverable</h3>
<p>The plugin ships with working sample data: an example quiz, sample content, a default offer, sample scripted messages, sample prompt pills. It isn't documentation — it's a functioning starting point. Getting from "installed" to "something works" in a few minutes is a requirement, not a nicety.</p>

<h3 id="principle-guest-first">Value Before Signup</h3>
<p>The visitor who experiences value before creating an account is the most valuable user, and requiring login first loses most of them. FLOSC holds early activity client-side (bridge data) and transfers it to the new account on registration, so the path is seamless: sample the thing, see the result, create an account, keep going without starting over.</p>

<h4 id="visitor-guest-member">Visitor → Guest → Member</h4>
<p>Three user states, each with different access, scripted messages, AI context, and prompt pills:</p>
<ul>
    <li><strong>Visitor:</strong> not logged in. Gets the free sample and a limited conversation; must create an account to see full results.</li>
    <li><strong>Guest:</strong> logged in, no purchase. Gets full results, free content, and the upgrade offer.</li>
    <li><strong>Member:</strong> logged in, purchased. Gets everything that was gated and the full delivery experience.</li>
</ul>

<h3 id="principle-no-build-step">No Build Step, No Dependencies</h3>
<p>The plugin directory has no <code>node_modules</code>, no <code>package.json</code>, no <code>webpack.config.js</code>. Install the ZIP and it runs — a hard requirement for hosting environments that aren't developer machines.</p>

<h3 id="principle-css-layers">CSS in Layers</h3>
<p>The stylesheets split by responsibility: <code>flosc-chat.css</code> (chat frame and interaction UI), <code>chat-style-*.css</code> (selectable presets), <code>flosc-offers.css</code> (offer cards and checkout surfaces), <code>flosc-frontend.css</code> (non-chat frontend surfaces), and <code>flosc-access.css</code> (access-gate views). They don't bleed into each other — a visual preset change won't break layout, and an offer restyle won't touch access pages.</p>

<h3 id="principle-debug-logging">Debug Output Stays Off in Production</h3>
<p>Debug output is intended to be gated behind <code>FLOSC_DEBUG</code> (PHP) or <code>this.debug</code> (JS) so production installs stay quiet. Because this is maintained by discipline rather than enforced by a build step or CI check, an occasional ungated call can slip in — a periodic grep for <code>error_log</code> and <code>console.log</code> outside a debug conditional keeps it honest.</p>
