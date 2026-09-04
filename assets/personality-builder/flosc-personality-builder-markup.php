<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="app flosc-admin-builder">

  <header class="top flosc-admin-builder__header">
    <div>
      <h1>Personality profile</h1>
      <p>Build the personality by selecting aspects, placing them on the density sequence, and defining how the AI expresses them.</p>
      <div class="meta">
        <span class="chip">Aspect palette</span>
        <span class="chip">Density-ordered profile</span>
        <span class="chip">Provider-ready output</span>
      </div>
    </div>
    <div class="toolbar flosc-admin-builder__tools">
      <select id="preset" class="btn" title="Choose a starting personality profile"></select>
      <span class="save-state" id="saveState" title="Saved in this browser until saved to the FLOSC library.">Saved</span>
      <button type="button" class="btn" id="btnImport">Import workshop state</button>
      <button type="button" class="btn" id="btnImportProfile">Import profile</button>
    </div>
    <p class="preset-where">Use a palette aspect as an ingredient. Included aspects define this personality.</p>
    <p class="preset-where" id="presetWhere"></p>
  </header>

  <section class="builder-workspace" aria-label="Personality builder">
    <section class="panel palette-panel" aria-labelledby="palette-title">
      <div class="panel-heading">
        <div>
          <h2 id="palette-title">Aspect palette</h2>
          <p class="panel-subtitle">Available directions to add to this personality.</p>
        </div>
        <button type="button" class="btn ghost" id="btnAddCategory">+ Category</button>
      </div>
      <div class="pad">
        <label class="palette-filter"><span class="screen-reader-text">Filter palette</span><input type="search" id="paletteSearch" placeholder="Search aspects"></label>
        <label class="chip palette-toggle"><input type="checkbox" id="hideOff"> Hide inactive aspects</label>
        <p class="small-note">Add an aspect with its Add control, or drag it into the sequence. Categories can be renamed.</p>
        <div id="cols" class="cols"></div>
      </div>
    </section>

    <section class="panel sequence-panel" aria-labelledby="sequence-title">
      <div class="panel-heading">
        <div>
          <h2 id="sequence-title">Included aspects</h2>
          <p class="panel-subtitle">The personality being built, from least dense to most dense.</p>
        </div>
        <span class="sequence-key">Drag to place · expand to edit</span>
      </div>
      <h3 id="editorTitle" class="screen-reader-text"></h3>
      <div class="file-seq" id="editor"></div>
    </section>
  </section>

  <div class="traj-pair">
  <section class="panel" id="trajPanel">
    <h2>Trajectories · desired outcome</h2>
    <div class="pad" id="trajMount"></div>
  </section>
  <section class="panel spec-panel" id="spec">
    <h2>Spectrograph</h2>
    <div class="pad">
      <p class="note">Hue is a frequency tag — peaks stay themselves, they are not blended into one colour. Density (ink) is not hue.</p>
      <label class="field"><span class="field-label">Content plate · the paper (not a hue)</span>
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
    <h2>Visual summary</h2>
    <div class="pad">
      <p class="note">Read-only interpretation of the configured aspects. It shows density, gain, and hue; it does not replace the builder above.</p>
      <div class="viz-grid viz-grid--2d">
        <div class="viz-card">
          <h3>2D aspect form</h3>
          <div id="viz2d"></div>
        </div>
      </div>
      <div class="density-label viz-ingredients-heading"><span>Included ingredients</span><span>shape identity remains visible</span></div>
      <div class="viz-ings" id="vizIngredients"></div>
      <div class="viz-phrases" id="vizTrajectories"></div>
    </div>
  </section>
  </div>

  <section class="panel save-panel" id="savePanel">
    <h2>Provider output</h2>
    <div class="pad">
      <p class="note">The canonical personality stays the same while FLOSC prepares provider-appropriate output. IVR supplies content and access constraints; this profile supplies expression.</p>
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
      <div class="tabs output-tabs">
        <button type="button" class="btn primary" data-out="prompt">Canonical profile</button>
        <button type="button" class="btn" data-out="providers" hidden>Provider output</button>
        <button type="button" class="btn" data-out="spec">Builder state</button>
        <button type="button" class="btn" data-out="lint">Validation</button>
        <label class="chip">
          <input type="checkbox" id="includeComments" checked>
          Include influences
        </label>
      </div>
      <p class="figure-readout output-note">Influences name the works and sources this character draws on. Included, they are part of the personality like anything else here. Unchecked, they stay in the builder state and the design copy and are never sent.</p>
      <div class="stats" id="stats"></div>
      <div id="lintMount"></div>
      <pre class="out" id="out"></pre>
      <div class="toolbar export-toolbar">
        <span class="small-note">Export</span>
        <button type="button" class="btn primary" id="btnViewPreview">View profile preview</button>
        <button type="button" class="btn" id="btnExportPreview">Download preview</button>
        <button type="button" class="btn" id="btnExportWorkshop">Download builder state</button>
        <button type="button" class="btn" id="btnExportMd">Download soul.md</button>
        <button type="button" class="btn" id="btnExportMdDesign" title="Same document plus a legend explaining density, gain, bands, and clouds.">Download design copy</button>
        <button type="button" class="btn" id="btnExportProviders" hidden>Download provider packs</button>
        <button type="button" class="btn primary" id="btnCopy">Copy this file</button>
      </div>
    </div>
  </section>

  <footer class="foot">Save the personality to the FLOSC library after reviewing the profile and validation output.</footer>
</div>

<dialog id="tribDialog">
  <form method="dialog" id="tribForm">
    <h3 class="dialog-title">Add wellspring</h3>
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
    <h3 class="dialog-title">Add wellspring category</h3>
    <div class="field"><label for="categoryLabel">Category name</label><input id="categoryLabel" required placeholder="e.g. Craft, Memory, Ethics"></div>
    <div class="field"><label for="categoryHint">Short description</label><input id="categoryHint" placeholder="What belongs here?"></div>
    <div class="toolbar"><button class="btn ghost" value="cancel">Cancel</button><button class="btn primary" value="ok">Add category</button></div>
  </form>
</dialog>
