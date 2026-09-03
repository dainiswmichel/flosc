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

  function soulBits(api) {
    var soul = (api && api.state && api.state.soul) || {};
    return {
      name: soul.name || (wp.entry && wp.entry.name) || "",
      role: soul.role || (wp.entry && wp.entry.role) || "",
      label: soul.label || soul.name || (wp.entry && wp.entry.label) || wp.personaId,
      traits: soul.traits || "",
      mission: soul.goals || soul.mission || "",
      boundaries: soul.prohibitions || soul.boundaries || "",
      scope: soul.scope || ""
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
    var bits = soulBits(api);
    var profile = api.promptFile ? api.promptFile() : "";
    var body = new FormData();
    body.append("action", "flosc_save_personality_design");
    body.append("nonce", wp.nonce);
    body.append("persona_id", wp.personaId);
    body.append("label", bits.label);
    body.append("ai_personality_name", bits.name);
    body.append("ai_personality_role", bits.role);
    body.append("ai_personality_traits", bits.traits);
    body.append("ai_mission", bits.mission);
    body.append("ai_boundaries", bits.boundaries);
    body.append("ai_topic_scope", bits.scope);
    body.append("ai_base_prompt", profile);
    body.append("workshop_json", JSON.stringify(workshopForSave(api)));
    if (wp.ivr) {
      body.append("ivr", wp.ivr);
    }
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
        if (json && json.success) {
          var msg = (json.data && json.data.message) || wp.i18n.saved;
          if (json.data && json.data.profile_bytes) {
            msg += " · " + json.data.profile_bytes + " bytes";
          }
          if (json.data && json.data.profile_hash) {
            msg += " · " + String(json.data.profile_hash).slice(0, 12);
          }
          setStatus(msg, true);
        } else {
          var msg = json && json.data && json.data.message ? json.data.message : wp.i18n.error;
          setStatus(msg, false);
        }
      })
      .catch(function () {
        setStatus(wp.i18n.error, false);
      });
  }

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
