<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="app">

  <header class="top">
    <div>
      <h1>floscPersonality Builder · v33</h1>
      <p>This page is the workshop. A <strong>workshop file</strong> saves every knob so you can open it later and keep designing. A <strong>personality profile</strong> is the written personality — paste it into an API or upload it in Claude, ChatGPT, or Grok.</p>
      <div class="meta">
        <span class="chip">v33</span>
        <span class="chip">workshop file · every knob</span>
        <span class="chip">personality profile · what the AI reads</span>
      </div>
    </div>
    <div class="toolbar">
      <select id="preset" class="btn" title="Library: templates and bundled personalities"></select>
      <span class="save-state" id="saveState" title="Always on. This browser only.">Saved</span>
      <button type="button" class="btn" id="btnImport">Import workshop file</button>
      <button type="button" class="btn" id="btnImportProfile">Import personality profile</button>
    </div>
    <p class="preset-where">Open/import files here. Downloads and copied outputs are kept at the bottom of the page.</p>
    <p class="preset-where" id="presetWhere"></p>
  </header>

  <div class="layout">
    <section class="panel">
      <h2>Wellsprings</h2>
      <div class="pad">
        <div class="density-label"><span>Wellspring families</span></div>
        <div class="note">Pick aspects here. You build them on the right, least dense at the top.</div>
        <label class="chip" style="display:inline-flex;align-items:center;gap:6px;margin-bottom:8px">
          <input type="checkbox" id="hideOff"> Hide inactive wellsprings
        </label>
        <button type="button" class="btn ghost" id="btnAddCategory">+ category</button>
        <span class="small-note">Categories are part of this workshop and can be renamed.</span>
        <div id="cols" class="cols"></div>
      </div>
    </section>

    <aside class="panel">
      <h2 id="editorTitle">Personality aspect sequence · least dense → most dense</h2>
      <div class="file-seq" id="editor"></div>
    </aside>
  </div>

  <div class="traj-pair">
  <section class="panel" id="trajPanel">
    <h2>Trajectories · desired outcome</h2>
    <div class="pad" id="trajMount"></div>
  </section>
  <section class="panel" id="spec" style="margin-top:0;border-radius:0">
    <h2>Spectrograph</h2>
    <div class="pad">
      <p class="note">Hue is a frequency tag — peaks stay themselves, they are not blended into one colour. Density (ink) is not hue.</p>
      <label class="field"><span style="font-family:var(--ui);font-size:0.78rem;font-weight:700">Content plate · the paper (not a hue)</span>
        <textarea class="spec-plate-in" id="contentPlate" placeholder="e.g. Expert information on hydropower: turbines, head, flow — not a personality, the subject matter."></textarea>
      </label>
      <div class="spec-views">
        <button type="button" class="btn primary" data-spec="cols">Spectrograph · columns</button>
        <button type="button" class="btn" data-spec="blend">Wash</button>
        <button type="button" class="btn" data-spec="calc">Wash only</button>
        <button type="button" class="btn" data-spec="paper">Paper (white)</button>
        <button type="button" class="btn" data-spec="together">Wash on paper</button>
      </div>
      <div class="spec-stage" id="specStage"></div>
      <div class="spec-excl" id="specExcl"></div>
    </div>
  </section>
  <section class="panel viz-below" id="vizBelow">
    <h2>Figure · morph <em>not a stack</em></h2>
    <div class="pad">
      <p class="note">Active 2D shapes morph by Gain into one outline. Active 3D shapes do the same as a volume silhouette. Hue stays a tag. Density is ink on that figure. Trajectory phrases are the intended impact on the future — not a geometric shape.</p>
      <div class="viz-grid">
        <div class="viz-card">
          <h3>2D morph</h3>
          <div id="viz2d"></div>
        </div>
        <div class="viz-card">
          <h3>3D morph</h3>
          <div id="viz3d"></div>
        </div>
      </div>
      <div class="density-label" style="margin-top:12px"><span>Ingredients</span><span>each shape stays itself until morph</span></div>
      <div class="viz-ings" id="vizIngredients"></div>
      <div class="viz-phrases" id="vizTrajectories"></div>
    </div>
  </section>
  </div>

  <section class="panel" style="margin-top:14px" id="savePanel">
    <h2>HTML AI personality preview and extracted outputs</h2>
    <div class="pad">
      <p class="note">This preview is the personality layer. The workshop stores every design control; the Markdown profile is generated from the same compiled personality. FLOSC chat uses that profile as system text.</p>
      <p class="figure-readout" id="flosc-provider-accommodation">
        <?php
        $flosc_pack_list = function_exists( 'flosc_personality_pack_label_list' )
            ? flosc_personality_pack_label_list()
            : 'Anthropic, OpenAI, xAI, Gemini, Mistral, Cohere, Together (Meta), Fireworks (Meta), AWS Bedrock, Azure OpenAI, OpenRouter, Perplexity';
        echo esc_html__( 'FLOSC chat APIs:', 'flosc' ) . ' ' . esc_html__( 'Anthropic, OpenAI, xAI, Gemini (or IVR scripted only).', 'flosc' ) . ' ';
        echo esc_html__( 'Speech-to-text:', 'flosc' ) . ' ' . esc_html__( 'AssemblyAI, OpenAI Whisper, custom endpoint.', 'flosc' ) . ' ';
        echo esc_html__( 'Compiled profile field maps (same genome):', 'flosc' ) . ' ' . esc_html( $flosc_pack_list ) . '.';
        ?>
      </p>
      <?php
      if ( function_exists( 'flosc_render_provider_intricacies_html' ) ) {
          flosc_render_provider_intricacies_html();
      }
      ?>
      <div class="tabs">
        <button type="button" class="btn primary" data-out="prompt">Markdown profile</button>
        <button type="button" class="btn" data-out="providers" hidden>Provider packs</button>
        <button type="button" class="btn" data-out="spec">Workshop state</button>
        <button type="button" class="btn" data-out="lint">Lint</button>
        <label class="chip" style="display:inline-flex;align-items:center;gap:6px">
          <input type="checkbox" id="includeComments" checked>
          Include authoring comments in exported profile
        </label>
      </div>
      <p class="figure-readout" style="margin:0 0 8px">Authoring comments are <code>&lt;!-- floscComment --&gt;</code> notes (works, character). They are not rules.</p>
      <div class="stats" id="stats"></div>
      <div id="lintMount"></div>
      <pre class="out" id="out"></pre>
      <div class="toolbar" style="justify-content:flex-start;margin-top:12px">
        <span class="small-note">Export / copy</span>
        <button type="button" class="btn primary" id="btnViewPreview">View HTML preview</button>
        <button type="button" class="btn" id="btnExportPreview">Download HTML preview</button>
        <button type="button" class="btn" id="btnExportWorkshop">Download workshop file</button>
        <button type="button" class="btn" id="btnExportMd">Download personality profile</button>
        <button type="button" class="btn" id="btnExportProviders" hidden>Download provider packs</button>
        <button type="button" class="btn primary" id="btnCopy">Copy this file</button>
      </div>
    </div>
  </section>

  <footer class="foot">
    Import at the top. Download / copy at the bottom.
  </footer>
</div>

<dialog id="tribDialog">
  <form method="dialog" id="tribForm">
    <h3 style="margin:0 0 10px">Add wellspring</h3>
    <div class="field"><label for="newColInput">Category</label><input type="text" id="newColInput" list="categoryOptions" required placeholder="Choose or write a category"><datalist id="categoryOptions"></datalist></div>
    <div class="field"><label>Name</label><input type="text" id="newName" required placeholder="e.g. Christian world-view"></div>
    <div class="field"><label>Instruction (compiles when this source is on)</label><textarea id="newInject" required placeholder="e.g. Frequently quote the New Testament, KJV."></textarea></div>
    <div class="toolbar">
      <button class="btn ghost" type="button" id="cancelTrib">Cancel</button>
      <button class="btn primary" value="ok">Add</button>
    </div>
  </form>
</dialog>
<input type="file" id="fileIn" accept="application/json,.json,.workshop.json,.flosc-workshop.json" hidden>
<input type="file" id="fileInProfile" accept=".md,.txt,text/markdown,text/plain" hidden>
<dialog id="categoryDialog">
  <form method="dialog" id="categoryForm">
    <h3 style="margin:0 0 10px">Add wellspring category</h3>
    <div class="field"><label for="categoryLabel">Category name</label><input id="categoryLabel" required placeholder="e.g. Craft, Memory, Ethics"></div>
    <div class="field"><label for="categoryHint">Short description</label><input id="categoryHint" placeholder="What belongs here?"></div>
    <div class="toolbar"><button class="btn ghost" value="cancel">Cancel</button><button class="btn primary" value="ok">Add category</button></div>
  </form>
</dialog>
