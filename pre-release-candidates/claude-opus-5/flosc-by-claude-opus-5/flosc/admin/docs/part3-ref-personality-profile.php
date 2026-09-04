<?php
/**
 * Reference: what a personality profile is made of, and where each part goes.
 *
 * Written because the structure existed and nobody could see it. Someone
 * arriving with the soul.md pattern in mind had no way to know that hard
 * constraints belong in Soul · rules while forbidden phrases belong in
 * Behavior · language — or that the split is deliberate rather than an
 * oversight waiting to be tidied up.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2 id="personality-profile">Personality Profiles</h2>
<p class="flosc-doc-stamp">Written 2026y-09m-04d-UTC-09h-22m-10s-639ms</p>

<p>A personality profile is a document. The DA1 AI Personality Builder writes it, the library
stores it, and the chatpack sends it to the provider at the top of every turn. It is the file
that makes BubblyBetty bubbly and DadJokeDan tell dad jokes.</p>

<p>The builder downloads it as <code>soul.md</code>, by that name, because that is what it is.</p>

<h3 id="personality-density">Density is order, and order is priority</h3>

<p>Every heading in a profile sits at a density from 0 to 100. <strong>Density is position in the
document.</strong> Low density is the top; high density is the bottom. The sequence is the design.</p>

<ul>
	<li><strong>Soul</strong> ≈ 0–33 — least dense, first, most essential. What remains under probe.</li>
	<li><strong>Character</strong> ≈ 34–66 — how it speaks, orients, and adapts.</li>
	<li><strong>Behavior</strong> ≈ 67–100 — most dense, last, most specific. Decisions, phrasing, edge cases.</li>
</ul>

<p><strong>Gain</strong> is separate from density. It runs from −100, which excludes what an entry
describes entirely, to +100, which includes it fully. Density says <em>where</em>; gain says
<em>how much</em>.</p>

<p>No AI provider takes a density parameter. None needs to — ordering a document is something you
do to the document, and every provider reads a system prompt from the top down.</p>

<h3 id="personality-stations">The eleven stations</h3>

<table class="widefat striped">
	<thead>
		<tr><th>Station</th><th>Density</th><th>What belongs here</th></tr>
	</thead>
	<tbody>
		<tr><td>Soul · identity</td><td>6</td><td>Who remains, under probe</td></tr>
		<tr><td>Soul · purpose</td><td>12</td><td>What this conversation is for</td></tr>
		<tr><td>Soul · rules &amp; scope</td><td>18</td><td>Invariants, defaults, who is served</td></tr>
		<tr><td>Soul · knowing</td><td>24</td><td>How this personality knows, doubts, corrects</td></tr>
		<tr><td>Character · thought / feeling / will</td><td>40</td><td>Tone, cadence, conditionals</td></tr>
		<tr><td>Character · stance</td><td>48</td><td>How it orients toward this human</td></tr>
		<tr><td>Character · initiative</td><td>56</td><td>When to answer, ask, lead, stay quiet</td></tr>
		<tr><td>Character · adaptation</td><td>62</td><td>Same soul, fitting intensity</td></tr>
		<tr><td>Behavior · selection</td><td>74</td><td>Decisions, edge cases, recipes</td></tr>
		<tr><td>Behavior · language / planning</td><td>84</td><td>Length, examples, phrase banks</td></tr>
		<tr><td>Behavior · manifested action</td><td>94</td><td>Output now. Sampling parameters sit here.</td></tr>
	</tbody>
</table>

<h3 id="personality-soulmd">If you arrived with soul.md in mind</h3>

<p>The common soul.md pattern names six elements. Every one of them has a home here, and the
builder carries five more besides.</p>

<table class="widefat striped">
	<thead>
		<tr><th>soul.md element</th><th>Station</th></tr>
	</thead>
	<tbody>
		<tr><td>Name and Role</td><td>Soul · identity</td></tr>
		<tr><td>Permissions and Scope</td><td>Soul · rules &amp; scope</td></tr>
		<tr><td>Personality and Voice</td><td>Character · thought / feeling / will, and Character · stance</td></tr>
		<tr><td>Communication Boundaries</td><td>Character · initiative, and Behavior · language</td></tr>
		<tr><td>Behavioral Rules</td><td>Character · adaptation, and Behavior · selection</td></tr>
		<tr><td>Explicit Prohibitions</td><td>Soul · rules &amp; scope <em>and</em> Behavior · language — see below</td></tr>
	</tbody>
</table>

<p>The three with no soul.md equivalent are <strong>Soul · purpose</strong> (what this conversation
is for), <strong>Soul · knowing</strong> (how this personality knows, doubts and corrects itself),
and <strong>Behavior · manifested action</strong> (what it does now, and the sampling parameters).</p>

<h3 id="personality-prohibitions">Prohibitions live in two places, deliberately</h3>

<p>This is a design decision, not an oversight. Do not consolidate them.</p>

<ul>
	<li><strong>What the personality must never do</strong> is an invariant. It belongs in
	<strong>Soul · rules &amp; scope</strong>, near the top of the document, where it governs
	everything that follows.</li>
	<li><strong>What the personality must never say</strong> is a phrase-level constraint. It
	belongs in <strong>Behavior · language</strong>, near the bottom, with the rest of the
	phrasing.</li>
</ul>

<p>Same prohibition family, two different densities, because they answer different questions at
different points in the model's read. soul.md collapses them into one list; the density ordering
separates them, and the separation is the more useful structure.</p>

<h3 id="personality-storage">What is stored, and where</h3>

<p>The library lives in one WordPress option, <code>flosc_personality_library</code>. Each entry
carries these fields.</p>

<table class="widefat striped">
	<thead>
		<tr><th>Field</th><th>What it holds</th></tr>
	</thead>
	<tbody>
		<tr><td><code>ai_personality_name</code></td><td>The name the character answers to.</td></tr>
		<tr><td><code>ai_personality_role</code></td><td>One line: what this character is here to do.</td></tr>
		<tr><td><code>ai_personality_traits</code></td><td>Short descriptors, for admin screens.</td></tr>
		<tr><td><code>ai_base_prompt</code></td><td><strong>The compiled profile.</strong> The only field that reaches a provider.</td></tr>
		<tr><td><code>workshop_json</code></td><td><strong>The design.</strong> Every station, container, aspect, density and gain — the builder's full state.</td></tr>
		<tr><td><code>ai_mission</code></td><td>What the conversation is for.</td></tr>
		<tr><td><code>ai_boundaries</code></td><td>Hard limits, in the floscAdmin's own words.</td></tr>
		<tr><td><code>ai_topic_scope</code></td><td>What this character will talk about.</td></tr>
		<tr><td><code>ai_off_topic_message</code></td><td>What it says when asked outside that scope.</td></tr>
		<tr><td><code>ai_off_topic_links</code></td><td>Where it points instead.</td></tr>
		<tr><td><code>ai_fallback_phrase</code></td><td>Its own words when it has nothing else.</td></tr>
		<tr><td><code>profile_version</code></td><td>Counts <em>changes</em>, not saves. Saving without editing does not advance it.</td></tr>
		<tr><td><code>profile_hash</code></td><td>SHA-256 over the genome and the compiled profile together, as one deployment unit.</td></tr>
		<tr><td><code>profile_modified_gmt</code></td><td>When the profile last became something different.</td></tr>
	</tbody>
</table>

<p><strong>The two that matter most are easy to confuse.</strong> <code>workshop_json</code> is the
design — what you built. <code>ai_base_prompt</code> is the artifact — what the model reads. Editing
in the builder changes the design; saving recompiles the artifact from it.</p>

<h3 id="personality-attachment">How a flow gets a personality</h3>

<p>A floscFlow stores only <code>personality_library_id</code>. The runtime resolves the library row
by that id <strong>on every turn</strong>, so an admin changing the attached personality mid
conversation changes the next reply. Nothing about the character is cached into the session.</p>

<p>Exactly one personality per flow. Personalities do not chain; only API providers do.</p>

<h3 id="personality-exports">Exports</h3>

<ul>
	<li><strong>soul.md</strong> — the compiled profile, plus a footer saying how to read it and
	where it came from.</li>
	<li><strong>Design copy</strong> — the same, with the density and gain readings shown.</li>
	<li><strong>Builder state</strong> — <code>workshop.json</code>, the full design, re-importable.</li>
	<li><strong>Preview</strong> — a rendered HTML view.</li>
	<li><strong>Provider packs</strong> — the sampling parameters on their own.</li>
</ul>

<p>The footer names the builder, the edition, the version, and the two homes —
<a href="https://da1.fm" target="_blank" rel="noopener noreferrer">da1.fm</a> and
<a href="https://flosc.ai" target="_blank" rel="noopener noreferrer">flosc.ai</a> — so a profile
found anywhere can say what made it and how to make another. It is never part of what is saved to
the library or sent to a provider.</p>

<h3 id="personality-nothing-silenced">Nothing silenced goes into a personality</h3>

<p>If a section is compiled into the profile, it is part of the character. There is no text in a
FLOSC profile that is sent and then disowned — no heading that costs input tokens on every turn
only to tell the model to ignore what follows.</p>

<p>Influences are the case this rule was written for. Ticked, they compile in as character under
their own heading. Unticked, they stay in the builder state and the design copy and are never
sent. There is no third state.</p>

<h3 id="personality-what-is-not">What a personality is not</h3>

<p>Model parameters — temperature, top_p, max_tokens, stop sequences — are <strong>not</strong>
personality. They are controls on the request, they belong to the flow's AI settings, and a
personality cannot carry them. The builder's provider-parameter station documents intent; the
values that reach a provider come from Step 2b Model Tuning on the AI tab.</p>

<p>The selling trajectory is not personality either. The five FLOSC phases, the phase outcomes and
the floscAdmin's phase instructions are sent on every turn from the flow section, whatever
personality is attached. Repeating them inside a character profile does not reinforce them — it
crowds out the character. That is measurable: BubblyBetty went from recognisably herself to barely
present as that text was added across four shipped profiles.</p>
