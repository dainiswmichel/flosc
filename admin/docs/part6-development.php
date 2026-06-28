<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<h1 id="development">Part 6: Development</h1>
<p>Development operations, team conventions, internal worknotes, and planned future features.</p>

<h2 id="team">Team</h2>
<ul>
    <li>Plugin maintainer: Dainis W. Michel.</li>
    <li>WordPress.org profile: https://profiles.wordpress.org/dainismichel/</li>
    <li>Readme contributor slug: dainismichel.</li>
    <li>Release ownership: maintainer approves acceptance-critical scope, changelog, and submission package.</li>
    <li>Support ownership: user support and roadmap intake are managed through official FLOSC channels and WordPress.org-facing documentation.</li>
</ul>

<h2 id="devnotes">Devnotes</h2>
<ul>
    <li>Use Michel Date Stamp Innovation format for notes and references: YYYY-MMm-DDd.</li>
    <li>Scope diagnostics and searches to active plugin paths to avoid archive noise.</li>
    <li>When refactoring repeated UI render paths, keep static markup and JS builders synchronized.</li>
    <li>After deployment, verify local and live parity using checksums for each touched file.</li>
    <li>Record any runtime behavior that cannot be verified locally and list manual tests required.</li>
</ul>

<h2 id="future-features">Future Features</h2>
<ul>
    <li>Improved user state and phase color coding (planned v8.0.1).</li>
    <li>User Feedback Center (deferred until approximately 1,000 users): support options, wishlist submission, and structured feedback intake.</li>
</ul>

<h2 id="user-wishlist">User Wishlist</h2>
<p>Community-requested ideas under consideration and not committed to a release version.</p>
<ul>
    <li>Bottom-right assistant styling mode (compact launcher and widget presentation).</li>
    <li>Banner refresh and softer pastel icon-card treatment.</li>
    <li>Bi-directional docs linking between in-app features and exact docs sections.</li>
</ul>
