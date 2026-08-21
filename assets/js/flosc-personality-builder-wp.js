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
    body.append("ai_base_prompt", profile);
    body.append("workshop_json", JSON.stringify(workshopForSave(api)));
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
          setStatus((json.data && json.data.message) || wp.i18n.saved, true);
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

  function bootWorkshop() {
    var api = builderApi();
    if (!api) {
      return;
    }
    if (wp.workshop && typeof api.importSpec === "function") {
      try {
        api.importSpec(wp.workshop);
        pinLibraryRow(api);
        if (typeof api.render === "function") {
          api.render();
        }
        return;
      } catch (e) {
        /* fall through */
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
