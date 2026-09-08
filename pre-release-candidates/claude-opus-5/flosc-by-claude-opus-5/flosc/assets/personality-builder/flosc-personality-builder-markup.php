<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="app flosc-admin-builder">

  <header class="top flosc-admin-builder__header">
    <div>
      <h1>Personality profile</h1>
      <p class="builder-attribution">DA1 AI Personality Builder · FLOSC edition · <?php echo esc_html( defined( 'FLOSC_DA1_BUILDER_VERSION' ) ? FLOSC_DA1_BUILDER_VERSION : '3.1.2' ); ?></p>
      <p>Build the personality by selecting aspects, placing them on the density sequence, and defining how the AI expresses them.</p>
      <div class="identity-row">
        <div class="field">
          <label for="soulName">Name</label>
          <input id="soulName" type="text" placeholder="e.g. DadJokeDan" autocomplete="off">
        </div>
        <div class="field field--wide">
          <label for="soulRole">Role</label>
          <input id="soulRole" type="text" placeholder="e.g. a pun-powered dad who always has a joke at the ready" autocomplete="off">
        </div>
        <div class="field">
          <label for="soulFilename">Filename</label>
          <input id="soulFilename" type="text" autocomplete="off" spellcheck="false">
        </div>
      </div>
      <p class="figure-readout identity-note" id="filenameNote"></p>
    </div>
    <div class="toolbar flosc-admin-builder__tools">
      <button type="button" class="btn" id="btnImport">Import workshop state</button>
      <button type="button" class="btn" id="btnImportProfile">Import profile</button>
    </div>
  </header>

  <p id="builderNotice" class="builder-notice" role="status" aria-live="polite" hidden></p>

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
        <p class="small-note">Tick an aspect to add it to the personality, or drag it into the sequence. <strong>+ Aspect</strong> makes a new card; <strong>+ Category</strong> makes a card that other aspects go inside. A category is a group of aspects — drop aspects onto any card and that card becomes the heading they sit under.</p>
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

  <?php
  /*
   * The Trajectories panel that stood here is gone. FLOSC already has
   * trajectories — WordPress posts in the trajectory category, managed on the
   * Trajectories tab and keyword-matched per turn by FLOSC_Trajectory. A
   * trajectory is a parameter of an aspect, and the aspect card carries it.
   */
  ?>
  <div class="traj-pair">
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
      <?php
      /*
       * Sampling is set on the flow's AI tab, not here — but it decides how
       * much of the personality survives the trip. A character designed at
       * one temperature and run at another is a different character, and
       * nothing on either page said so.
       */
      ?>
      <p class="note"><strong><?php echo esc_html__( 'Model settings and this personality', 'flosc' ); ?></strong><br>
        <?php echo esc_html__( 'Temperature above about 0.9 loosens what you designed here: gain and binding still reach the model, but it wanders further from them. Below about 0.3 it flattens — the character reads as correct and lifeless. Between 0.6 and 0.8 is where a designed personality holds. Top-P is a second loosening knob; move one or the other, not both, and leave it at 1.0 while you tune temperature. Top-K is offered by some providers only, and 40 is a sane value where it exists. These are set on the flow AI tab, not in the builder.', 'flosc' ); ?></p>
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
      <p class="figure-readout output-view-note" id="outViewNote"></p>
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
        <label class="chip export-toggle" for="includeSourceSite">
          <input type="checkbox" id="includeSourceSite"> Name this site in downloads
        </label>
      </div>
    </div>
  </section>

  <footer class="foot">Save the personality to the FLOSC library after reviewing the profile and validation output.</footer>
</div>

<input type="file" id="fileIn" accept="application/json,.json,.workshop.json,.flosc-workshop.json" hidden>
<input type="file" id="fileInProfile" accept=".md,.txt,text/markdown,text/plain" hidden>
