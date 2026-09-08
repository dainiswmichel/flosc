/* Pull the real functions out of the builder and exercise them. */
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

const names = ["clampDensity", "formatDensity", "cardAncestors", "densityChain",
               "composedDensity", "compareCards", "bandOfDensity", "defaultGroupNoun",
               "layerForDensity", "trajectoryPosts", "postFromTrajectory", "trajectoryReading"];
const SOUL_LAYERS = [
  { id: "identity", density: 6, label: "Name and Core Role" },
  { id: "goals", density: 12, label: "Philosophy and Values" },
  { id: "rules", density: 18, label: "Hard Boundaries and Prohibitions" },
  { id: "epistemics", density: 24, label: "Knowledge, Doubt and Correction" },
  { id: "expression", density: 40, label: "Tone and Communication Style" },
  { id: "relation", density: 48, label: "Stance Toward the Human" },
  { id: "initiative", density: 56, label: "Behavior in Ambiguity" },
  { id: "adaptation", density: 62, label: "Adaptation" },
  { id: "behavior", density: 74, label: "Decisions including Infrequent Cases" },
  { id: "language", density: 84, label: "Banned Words and Fillers to Avoid" },
  { id: "action", density: 94, label: "Output and Delivery" }
];
const DENSITY_BANDS = { soul: 0, character: 34, behavior: 67 };
const state = { tribParent: {}, trib: {} };
function containersSorted() { return SOUL_LAYERS.map(function (L) { return { id: L.id, kind: "layer", density: L.density, label: L.label }; }); }
function containerById(id) { return containersSorted().find(function (l) { return l.id === id; }) || null; }
function soulSectionForDensity(d) { return containerById(layerForDensity(d)) || SOUL_LAYERS[0]; }
function tribState(id) { return state.trib[id] || { density: 0 }; }
const window = { floscPersonalityWp: { trajectoryPosts: [
  { id: 412, type: "post", title: "How head and flow combine", excerpt: "Head is the vertical drop.", url: "https://example.com/head-and-flow/" }
] } };

eval(names.map(grab).join("\n"));

let fails = 0;
function is(label, got, want) {
  const ok = String(got) === String(want);
  if (!ok) fails++;
  console.log((ok ? "ok   " : "FAIL ") + label.padEnd(56) + String(got) + (ok ? "" : "   (want " + want + ")"));
}

/* The Captain's own worked example. */
state.trib = { kindness: { density: 95 }, warmth: { density: 16 }, direct: { density: 100 }, patience: { density: 25 } };
state.tribParent = { warmth: { kind: "trib", id: "kindness" }, direct: { kind: "trib", id: "kindness" }, patience: { kind: "trib", id: "kindness" } };
is("16 dropped into 95", composedDensity("warmth"), "95.016");
is("100 dropped into 95", composedDensity("direct"), "95.100");
is("95.100 sorts after 95.016", compareCards({ id: "direct", label: "D" }, { id: "warmth", label: "W" }) > 0, "true");
is("the group sorts before its members", compareCards({ id: "kindness", label: "K" }, { id: "warmth", label: "W" }) < 0, "true");

/* Arbitrary depth, IP-address style. */
state.trib.deep = { density: 100 };
state.tribParent.deep = { kind: "trib", id: "patience" };
is("arbitrary depth, IP-address style", composedDensity("deep"), "95.025.100");

/* Drag out and it is 16 again — nothing stored, nothing to restore. */
delete state.tribParent.warmth;
is("dragged out of the group", composedDensity("warmth"), "16");

/* A fractional root density is not a nesting level. */
state.trib.half = { density: 95.5 };
state.trib.tiny = { density: 1 };
is("fractional root keeps its decimals", composedDensity("half"), "95.5");
is("  and still sorts above a card at 1", compareCards({ id: "half", label: "H" }, { id: "tiny", label: "T" }) > 0, "true");

/* Ties sort alphabetically. */
state.trib.a = { density: 40 }; state.trib.b = { density: 40 };
is("same density, alphabetical", compareCards({ id: "b", label: "Zeal" }, { id: "a", label: "Anchor" }) > 0, "true");

/* A cycle cannot hang the reader. */
state.tribParent.a = { kind: "trib", id: "b" };
state.tribParent.b = { kind: "trib", id: "a" };
is("a cycle terminates", composedDensity("a").length > 0, "true");

/* Group nouns and soul sections come off the same axis. */
is("soul band is a cloud", defaultGroupNoun(20), "cloud");
is("character band is a rain cloud", defaultGroupNoun(50), "rain cloud");
is("behavior band is a pool", defaultGroupNoun(80), "pool");
is("density 44 lands in Tone", soulSectionForDensity(44).label, "Tone and Communication Style");
is("density 70 lands in Adaptation", soulSectionForDensity(70).label, "Adaptation");
is("density 20 lands in Hard Boundaries", soulSectionForDensity(20).label, "Hard Boundaries and Prohibitions");
is("density 95 lands in Output and Delivery", soulSectionForDensity(95).label, "Output and Delivery");
is("density 0 lands in Name and Core Role", soulSectionForDensity(0).label, "Name and Core Role");

/* WordPress-native trajectories. */
is("a bare id", trajectoryReading("412"), "post 412 — How head and flow combine. Head is the vertical drop.");
is("the editor's own address", trajectoryReading("post.php?post=412&action=edit"), "post 412 — How head and flow combine. Head is the vertical drop.");
is("a permalink", trajectoryReading("https://example.com/head-and-flow/"), "post 412 — How head and flow combine. Head is the vertical drop.");
is("an id we cannot look up", trajectoryReading("999"), "post 999");
is("free text is left alone", trajectoryReading("leave them feeling heard"), "leave them feeling heard");

console.log(fails ? "\n" + fails + " FAILURES" : "\nall green");
process.exit(fails ? 1 : 0);
