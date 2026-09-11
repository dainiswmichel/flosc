/* FLOSC admin bridge: same-page workshop, save to library. */
(function () {
  var wp = window.floscPersonalityWp;
  if (!wp || !wp.ajaxUrl) {
    return;
  }

  function builderApi() {
    return window.floscBuilder || null;
  }

  function workshopForSave(api) {
    if (!api || typeof api.workshopFile !== "function") {
      return {};
    }
    var shop = api.workshopFile();
    if (shop && shop.derived) {
      delete shop.derived.provider_packs;
    }
    return shop;
  }

  /*
   * The four fields the designer computes and used not to send.
   *
   * libraryEntry() has always built a complete entry — traits, mission,
   * boundaries and topic scope alongside name and role — and only the
   * downloadable builder state read it. So a personality saved here left those
   * four empty in the database, which is why {topic_scope} resolved to nothing
   * on every flow that had not hand-edited a flow_ivr.md.
   */
  function sidecarFields(api) {
    var entry = api && typeof api.libraryEntry === "function" ? api.libraryEntry() : null;
    if (!entry) { return {}; }
    return {
      ai_personality_traits: entry.ai_personality_traits || "",
      ai_mission: entry.ai_mission || "",
      ai_boundaries: entry.ai_boundaries || "",
      ai_topic_scope: entry.ai_topic_scope || ""
    };
  }

  function appendSidecar(body, api) {
    var extra = sidecarFields(api);
    Object.keys(extra).forEach(function (key) {
      body.append(key, extra[key]);
    });
  }

  function soulBits(api) {
    var soul = (api && api.state && api.state.soul) || {};
    return {
      name: soul.name || (wp.entry && wp.entry.name) || "",
      role: soul.role || (wp.entry && wp.entry.role) || "",
      label: soul.label || soul.name || (wp.entry && wp.entry.label) || wp.personaId
    };
  }

  function setStatus(text, ok) {
    var el = document.getElementById("flosc-personality-builder-status");
    if (!el) {
      return;
    }
    el.textContent = text;
    el.classList.remove("is-ok", "is-err");
    el.classList.add(ok ? "is-ok" : "is-err");
  }

  /* ---------------------------------------------------------------
     Autosave

     There was none: Save was bound to the button and to nothing else, so
     every change sat in the browser until someone remembered to press it.

     A change marks the personality dirty and starts a 30-second timer, which
     any further change restarts — so typing a sentence saves once, at the end,
     not once per keystroke. The button carries the state: blue while there is
     something to save, green once it is saved.
     --------------------------------------------------------------- */
  var AUTOSAVE_MS = 30000;
  var autosaveTimer = null;
  var dirty = false;
  var saving = false;

  function saveButton() {
    return document.getElementById("flosc-personality-builder-save");
  }
  function markState(next) {
    var btn = saveButton();
    if (!btn) { return; }
    btn.classList.remove("is-dirty", "is-saved", "is-saving");
    btn.classList.add(next);
  }
  function setSavedStamp(stamp) {
    var el = document.getElementById("flosc-personality-builder-mts");
    if (!el) { return; }
    el.textContent = stamp ? "Last saved " + stamp + " UTC" : "Not saved yet";
  }
  function markDirty() {
    if (saving) { return; }
    dirty = true;
    markState("is-dirty");
    if (autosaveTimer) { clearTimeout(autosaveTimer); }
    autosaveTimer = setTimeout(function () {
      autosaveTimer = null;
      if (!dirty) { return; }
      if (saveTargetMismatch(builderApi())) { return; }
      saveToLibrary();
    }, AUTOSAVE_MS);
  }
  function watchForChanges() {
    var root = document.querySelector(".flosc-personality-workshop");
    if (!root) { return; }
    ["input", "change", "drop"].forEach(function (evt) {
      root.addEventListener(evt, markDirty, true);
    });
    /* Only buttons that alter the design. The Save button itself, the export
       buttons and the accordion summaries change nothing worth saving. */
    root.addEventListener("click", function (e) {
      var el = e.target.closest("button, input[type=checkbox]");
      if (!el) { return; }
      if (el.id === "flosc-personality-builder-save") { return; }
      if (el.id && el.id.indexOf("btnExport") === 0) { return; }
      if (el.id === "btnViewPreview" || el.id === "btnImport" || el.id === "btnImportProfile") { return; }
      markDirty();
    }, true);
    /* A tab closed with unsaved work is work lost. */
    window.addEventListener("beforeunload", function (e) {
      if (!dirty) { return undefined; }
      e.preventDefault();
      e.returnValue = "";
      return "";
    });
  }

  /*
   * Refuse to write one personality into another's row.
   *
   * persona_id is fixed at page load — it is the personality attached to this
   * flow. state.soul.id is whatever is in the builder. They agree while you
   * edit the attached personality, and they diverge the moment New or New from
   * template loads something else. Saving in that state wrote the new content
   * into the attached row, keeping only its id: on 8 September the row named
   * bubblybetty ended up holding a complete SalesCloser, prompt and workshop
   * both. The 30-second autosave made that automatic rather than merely
   * possible.
   */
  function saveTargetMismatch(api) {
    var soulId = api && api.state && api.state.soul ? String(api.state.soul.id || "") : "";
    if (!soulId || !wp.personaId) { return ""; }
    return soulId === String(wp.personaId) ? "" : soulId;
  }

  function saveToLibrary() {
    if (!wp.nonce || !wp.personaId) {
      setStatus((wp.i18n && wp.i18n.error) || "Could not save. Try again.", false);
      return;
    }
    var api = builderApi();
    if (!api) {
      setStatus(wp.i18n.error, false);
      return;
    }
    var mismatch = saveTargetMismatch(api);
    if (mismatch) {
      dirty = false;
      if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
      markState("is-dirty");
      setStatus("Not saved. The builder is holding \u201c" + mismatch + "\u201d and this flow is attached to \u201c" +
        wp.personaId + "\u201d. Use New to create it as its own personality.", false);
      return;
    }
    var bits = soulBits(api);
    var profile = api.promptFile ? api.promptFile() : "";
    var body = new FormData();
    body.append("action", "flosc_save_personality_design");
    body.append("nonce", wp.nonce);
    body.append("persona_id", wp.personaId);
    body.append("label", bits.label);
    body.append("ai_personality_name", bits.name);
    body.append("ai_personality_role", bits.role);
    appendSidecar(body, api);
    body.append("ai_base_prompt", profile);
    body.append("workshop_json", JSON.stringify(workshopForSave(api)));
    saving = true;
    markState("is-saving");
    if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
    setStatus(wp.i18n.saving, true);
    fetch(wp.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: body
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        saving = false;
        if (json && json.success) {
          dirty = false;
          markState("is-saved");
          setSavedStamp(json.data && json.data.saved_at ? json.data.saved_at : "");
          setStatus((json.data && json.data.message) || wp.i18n.saved, true);
        } else {
          var msg = json && json.data && json.data.message ? json.data.message : wp.i18n.error;
          markState("is-dirty");
          setStatus(msg, false);
        }
      })
      .catch(function () {
        saving = false;
        markState("is-dirty");
        setStatus(wp.i18n.error, false);
      });
  }

  /* ---------------------------------------------------------------
     New

     A new personality is a new library row, named before it is written.
     Nothing touches the row that is open.
     --------------------------------------------------------------- */
  function slugify(text) {
    return String(text || "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").slice(0, 48);
  }
  function freeId(base) {
    var taken = {};
    (wp.existingIds || []).forEach(function (id) { taken[String(id)] = true; });
    var id = base || "personality";
    var n = 2;
    while (taken[id]) { id = base + "_" + n++; }
    return id;
  }

  window.floscCreatePersonality = function (label, profile, workshopJson, name, role, done) {
    var id = freeId(slugify(label));
    var body = new FormData();
    body.append("action", "flosc_save_personality_design");
    body.append("nonce", wp.nonce);
    body.append("persona_id", id);
    body.append("label", label);
    body.append("ai_personality_name", name || label);
    body.append("ai_personality_role", role || "");
    body.append("ai_base_prompt", profile);
    body.append("workshop_json", workshopJson);
    setStatus("Creating " + label + "\u2026", true);
    fetch(wp.ajaxUrl, { method: "POST", credentials: "same-origin", body: body })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json || !json.success) {
          setStatus((json && json.data && json.data.message) || wp.i18n.error, false);
          if (done) { done(false); }
          return;
        }
        /* Created. Attach it to this flow so the builder opens it on reload —
           the builder always edits the personality this flow is attached to. */
        if (!wp.attachNonce || !wp.ivr) {
          setStatus("Created " + label + ". Attach it on this tab to edit it.", true);
          if (done) { done(true, id); }
          return;
        }
        var att = new FormData();
        att.append("action", "flosc_attach_personality");
        att.append("nonce", wp.attachNonce);
        att.append("ivr", wp.ivr);
        att.append("persona", id);
        return fetch(wp.ajaxUrl, { method: "POST", credentials: "same-origin", body: att })
          .then(function (r) { return r.json(); })
          .then(function () {
            dirty = false;
            if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
            setStatus("Created " + label + ". Opening it\u2026", true);
            window.location.reload();
          });
      })
      .catch(function () {
        setStatus(wp.i18n.error, false);
        if (done) { done(false); }
      });
  };

  function hideProviderPacks() {
    if (!wp.hideProviderPacks) {
      return;
    }
    var btn = document.getElementById("btnExportProviders");
    if (btn) {
      btn.hidden = true;
    }
    document.querySelectorAll("[data-out=\"providers\"]").forEach(function (el) {
      el.hidden = true;
    });
  }

  function filterPalette() {
    var input = document.getElementById("paletteSearch");
    var query = input ? String(input.value || "").toLowerCase().trim() : "";
    document.querySelectorAll("#cols .trib").forEach(function (item) {
      item.hidden = !!query && item.textContent.toLowerCase().indexOf(query) === -1;
    });
    document.querySelectorAll("#cols .col").forEach(function (category) {
      var visible = category.querySelectorAll(".trib:not([hidden])").length;
      category.hidden = !!query && visible === 0;
    });
  }

  function pinLibraryRow(api) {
    if (!api || !api.state || !api.state.soul) {
      return;
    }
    if (wp.personaId) {
      api.state.soul.id = wp.personaId;
    }
    if (wp.entry && wp.entry.label) {
      api.state.soul.label = wp.entry.label;
    }
  }

  /* Loud diagnostics: the silent catch here once swallowed real failures and
     swapped them for a blank preset. Every bail-out now names itself in the
     console and, where present, in the on-page status line. */
  function bootDiag(kind, detail) {
    var msg = "[flosc designer] " + kind + (detail ? ": " + detail : "");
    if (window.console && console.error) console.error(msg);
    setStatus(msg, false);
  }
  window.floscDesignerDebug = function () {
    var cols = document.getElementById("cols");
    return {
      hasBoot: !!window.floscPersonalityWp,
      personaId: wp && wp.personaId,
      workshopTribCount: wp && wp.workshop && wp.workshop.tributaries ? wp.workshop.tributaries.length : null,
      apiType: typeof window.floscBuilder,
      importType: window.floscBuilder ? typeof window.floscBuilder.importSpec : null,
      colsDuplicates: document.querySelectorAll("#cols").length,
      cardsInCols: cols ? cols.querySelectorAll(".trib").length : -1
    };
  };

  function bootWorkshop() {
    var api = builderApi();
    if (!api) {
      bootDiag("builder API missing", "window.floscBuilder not found");
      return;
    }
    var hasProfile = !!(wp.entry && wp.entry.profile);
    if (wp.workshop && typeof api.importSpec === "function") {
      try {
        api.importSpec(wp.workshop);
        pinLibraryRow(api);
        if (typeof api.render === "function") {
          api.render();
        }
        var colsNow = document.getElementById("cols");
        var cardCount = colsNow ? colsNow.querySelectorAll(".trib").length : -1;
        var activeCount = wp.workshop.tributaries ? wp.workshop.tributaries.filter(function (t) { return t.on !== false && t.state !== "off"; }).length : -1;
        if (cardCount < 1) {
          bootDiag("import ran but canvas is empty", "cards=" + cardCount + " expected>=" + activeCount);
        } else {
          if (window.console && console.info) console.info("[flosc designer] imported " + wp.personaId + " — " + cardCount + " cards on canvas");
          setStatus("Loaded " + (wp.entry && wp.entry.label ? wp.entry.label : wp.personaId) + " (" + cardCount + " aspects)", true);
        }
        return;
      } catch (e) {
        bootDiag("importSpec threw", e && e.message ? e.message + " @ " + (e.stack || "").split("\n")[1] : String(e));
      }
    }
    if (typeof api.applyPreset === "function") {
      api.applyPreset("blank");
    }
    if (api.state && api.state.soul) {
      if (wp.entry && wp.entry.name) {
        api.state.soul.name = wp.entry.name;
      }
      if (wp.entry && wp.entry.role) {
        api.state.soul.role = wp.entry.role;
      }
      if (wp.entry && wp.entry.label) {
        api.state.soul.label = wp.entry.label;
      }
      if (wp.personaId) {
        api.state.soul.id = wp.personaId;
      }
    }
    if (wp.entry && wp.entry.profile && typeof api.importPersonalityProfile === "function") {
      try {
        api.importPersonalityProfile(wp.entry.profile, wp.personaId + ".flospersonality.md");
      } catch (e2) {
        /* keep blank + library name */
      }
    }
    pinLibraryRow(api);
    if (typeof api.render === "function") {
      api.render();
    }
    if (hasProfile) {
      setStatus("Loaded " + (wp.entry && wp.entry.label ? wp.entry.label : wp.personaId) + " profile", true);
    }
  }

  function guardHostedForm() {
    var root = document.querySelector(".flosc-personality-workshop");
    if (!root) {
      return;
    }
    function neutralize(scope) {
      (scope || root).querySelectorAll("button").forEach(function (btn) {
        if (!btn.getAttribute("type")) {
          btn.setAttribute("type", "button");
        }
      });
    }
    neutralize(root);
    root.addEventListener(
      "click",
      function (e) {
        var btn = e.target && e.target.closest ? e.target.closest("button") : null;
        if (btn && root.contains(btn) && !btn.getAttribute("type")) {
          btn.setAttribute("type", "button");
        }
      },
      true
    );
    root.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") {
        return;
      }
      var tag = e.target && e.target.tagName ? e.target.tagName.toLowerCase() : "";
      if (tag === "textarea" || tag === "button" || tag === "a") {
        return;
      }
      e.preventDefault();
    });
  }

  function openDesignerAccordion() {
    var acc = document.getElementById("flosc-personality-designer");
    if (!acc) {
      return;
    }
    if (window.location.hash === "#flosc-personality-designer") {
      acc.open = true;
    }
  }

  function hoistDialogs() {
    var root = document.querySelector(".flosc-personality-workshop");
    if (!root || !root.closest("#flosc-settings-form")) {
      return;
    }
    root.querySelectorAll("dialog").forEach(function (d) {
      document.body.appendChild(d);
    });
  }

  function start() {
    hoistDialogs();
    guardHostedForm();
    openDesignerAccordion();
    var save = document.getElementById("flosc-personality-builder-save");
    if (save) {
      save.addEventListener("click", saveToLibrary);
      markState("is-saved");
      watchForChanges();
    }
    hideProviderPacks();
    var paletteSearch = document.getElementById("paletteSearch");
    if (paletteSearch) {
      paletteSearch.addEventListener("input", filterPalette);
    }
    if (wp.personaId) {
      bootWorkshop();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
