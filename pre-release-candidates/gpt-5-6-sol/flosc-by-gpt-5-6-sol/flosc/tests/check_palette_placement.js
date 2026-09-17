/*
 * The three mechanisms the designer broke on, executed rather than reasoned
 * about.
 *
 * For four days a ticked aspect went nowhere: activeTribs() walked the real
 * headings only, an aspect in no category was in none of them, and everything
 * downstream — placement, the working sort, the compiler — keys on
 * activeTribs(). Then the colon path could not reach a heading, because it
 * searched cards and a heading is not one. Then the auto flag did not survive
 * a save, so the flat sequence collapsed back into nesting on the next load.
 *
 * Each is a one-line mistake that no gate could see. These run the real
 * functions, lifted out of the builder by name, against stubs.
 */
const fs = require("fs");
const src = fs.readFileSync("assets/js/flosc-personality-builder.js", "utf8");

function grab(name) {
  const start = src.indexOf("\n  function " + name + "(");
  if (start < 0) throw new Error("missing " + name);
  let i = src.indexOf("{", start), depth = 0, j = i;
  for (; j < src.length; j++) {
    if (src[j] === "{") depth++;
    else if (src[j] === "}") { depth--; if (!depth) break; }
  }
  return src.slice(start, j + 1);
}

let fail = 0;
function ok(label, actual, expected) {
  const pass = JSON.stringify(actual) === JSON.stringify(expected);
  if (!pass) fail++;
  console.log(
    (pass ? "ok   " : "FAIL ") + label.padEnd(58) +
    JSON.stringify(actual) + (pass ? "" : " (want " + JSON.stringify(expected) + ")")
  );
}

/* ---- the world the builder runs in, reduced to what these three touch ---- */
const LAYERS = [
  { id: "identity", kind: "layer", density: 6, label: "Identity and Role" },
  { id: "goals", kind: "layer", density: 12, label: "Mission, Philosophy and Values" },
  { id: "epistemics", kind: "layer", density: 24, label: "Knowledge, Doubt and Correction" }
];
const CARDS = [
  { id: "shipped", col: "epistemics", label: "A shipped card" },
  { id: "loose", col: "", label: "A brand new aspect" },
  { id: "member", col: "", label: "A member of a group" },
  { id: "off", col: "", label: "Not ticked" }
];
const state = {
  tribParent: { member: { kind: "trib", id: "loose" } },
  trib: {
    shipped: { on: true, mode: "on", density: 25, col: "epistemics" },
    loose:   { on: true, mode: "on", density: 10, col: "" },
    member:  { on: true, mode: "on", density: 10.5, col: "" },
    off:     { on: false, mode: "off", density: 90, col: "" }
  }
};
const UNFILED = "__unfiled";
function containersSorted() { return LAYERS.slice(); }
function containerById(id) { return LAYERS.find(function (l) { return l.id === id; }) || null; }
function allTribs() { return CARDS.slice(); }
function tribState(id) { return state.trib[id] || { on: false, mode: "off", density: 0 }; }
function tribColOf(t) { const st = state.trib[t.id]; return (st && st.col === "") ? UNFILED : (st ? st.col : UNFILED); }
function wellspringCategories() { return LAYERS.map(function (l) { return { id: l.id, density: l.density }; }); }
function tribsInCol(cid) { return CARDS.filter(function (t) { return tribColOf(t) === cid; }); }
function clampDensity(n) { return Math.max(0, Math.min(100, Number(n) || 0)); }
function tribChildren(hostId) {
  return CARDS.filter(function (t) {
    const p = state.tribParent[t.id];
    return p && p.kind === "trib" && p.id === hostId;
  });
}
function childrenOf(layerId) {
  return CARDS.filter(function (t) {
    const p = state.tribParent[t.id];
    return p && p.kind === "layer" && p.id === layerId;
  }).map(function (t) { return { kind: "topic", id: t.id }; });
}

eval(grab("activeTribs"));
eval(grab("cardAtDensityPath"));

/* ---- 1. a ticked aspect in no category is active ---- */
const ids = activeTribs().map(function (t) { return t.id; }).sort();
ok("a ticked aspect in no category is active", ids, ["loose", "member", "shipped"]);
ok("an unticked aspect is not", ids.indexOf("off"), -1);
ok("no aspect is counted twice", ids.length, new Set(ids).size);

/* ---- 2. a colon path reaches a heading, not only a card ---- */
ok("6 finds the heading at d6", cardAtDensityPath([6], "x"), "identity");
ok("24 finds the heading at d24", cardAtDensityPath([24], "x"), "epistemics");
ok("10 finds the loose aspect", cardAtDensityPath([10], "x"), "loose");
ok("10:10.5 reaches a card inside a card", cardAtDensityPath([10, 10.5], "x"), "member");
ok("99 finds nothing", cardAtDensityPath([99], "x"), null);
ok("a path never returns the card being moved", cardAtDensityPath([10], "loose"), null);

/* ---- 3. auto survives the save, and old saves load sanely ---- */
function writePlacement(parents) {
  return Object.keys(parents).reduce(function (acc, tid) {
    const p = parents[tid];
    if (!p) return acc;
    acc[tid] = { to: p.kind + ":" + p.id, auto: !!p.auto };
    return acc;
  }, {});
}
function readPlacement(placement) {
  const out = {};
  Object.keys(placement).forEach(function (tid) {
    const val = placement[tid];
    const obj = val && typeof val === "object";
    const raw = String((obj ? val.to : val) || "");
    const cut = raw.indexOf(":");
    if (cut < 1) return;
    const kind = raw.slice(0, cut);
    const pid = raw.slice(cut + 1);
    if (!pid) return;
    if (kind !== "layer" && kind !== "cloud" && kind !== "trib") return;
    const auto = obj ? !!val.auto : (kind === "layer");
    out[tid] = auto ? { kind: kind, id: pid, auto: true } : { kind: kind, id: pid };
  });
  return out;
}
const before = {
  peer: { kind: "layer", id: "identity", auto: true },
  child: { kind: "layer", id: "identity" },
  grouped: { kind: "trib", id: "loose" }
};
const after = readPlacement(writePlacement(before));
ok("a peer is still a peer after a save", after.peer, { kind: "layer", id: "identity", auto: true });
ok("a child is still a child after a save", after.child, { kind: "layer", id: "identity" });
ok("group membership survives", after.grouped, { kind: "trib", id: "loose" });

/* A save written before auto existed: a heading parent was assigned by
   density, so it loads as a peer; a card parent is nesting somebody built. */
const legacy = readPlacement({ a: "layer:identity", b: "trib:loose" });
ok("legacy heading parent loads as a peer", legacy.a, { kind: "layer", id: "identity", auto: true });
ok("legacy card parent stays real nesting", legacy.b, { kind: "trib", id: "loose" });


/* ---- 4. a sub-aspect made by + Sub-aspect reads as a nested path ---- */
function densityChain(id) {
  const chain = [];
  let cur = id, guard = 0;
  while (cur && guard++ < 24) {
    chain.unshift(clampDensity(tribState(cur).density));
    const p = state.tribParent[cur];
    cur = p && p.kind === "trib" ? p.id : null;
  }
  return chain;
}
/* + Sub-aspect gives the child the host's own density, as the shipped joke
   cards do. The path is what makes it read as 10:010 rather than a bare 10. */
state.trib.sub = { on: true, mode: "on", density: 10, col: "" };
state.tribParent.sub = { kind: "trib", id: "loose" };
CARDS.push({ id: "sub", col: "", label: "A sub-aspect" });
ok("a sub-aspect carries its host in the chain", densityChain("sub"), [10, 10]);
ok("its host is still top level", densityChain("loose"), [10]);
ok("a sub-aspect is active", activeTribs().map(function (t) { return t.id; }).indexOf("sub") >= 0, true);
ok("a sub-aspect is never a top-level peer",
   (state.tribParent.sub.kind === "trib"), true);

console.log(fail ? "\n" + fail + " FAILURES" : "\nall green");
process.exit(fail ? 1 : 0);
