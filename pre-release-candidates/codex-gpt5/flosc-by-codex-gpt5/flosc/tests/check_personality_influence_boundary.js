/* The Include influences switch owns the provider boundary. */
const fs = require("fs");
const path = require("path");

const builderPath = process.env.FLOSC_BUILDER_SOURCE ||
  path.join(__dirname, "..", "assets", "js", "flosc-personality-builder.js");
const src = fs.readFileSync(builderPath, "utf8");

function grab(name) {
  const start = src.indexOf("\n  function " + name + "(");
  if (start < 0) throw new Error("missing " + name);
  let i = src.indexOf("{", start);
  let depth = 0;
  let j = i;
  for (; j < src.length; j++) {
    if (src[j] === "{") depth++;
    else if (src[j] === "}") {
      depth--;
      if (!depth) break;
    }
  }
  return src.slice(start, j + 1);
}

let failures = 0;
function check(label, condition) {
  if (!condition) failures++;
  process.stdout.write((condition ? "ok   " : "FAIL ") + label + "\n");
}

const fixture = {
  id: "influence_boundary_fixture",
  col: "epistemics",
  label: "Influence boundary fixture",
  short: "SHORT_SENTINEL",
  inject: "RUNTIME_INSTRUCTION_SENTINEL",
  character: "INFLUENCE_CHARACTER_SENTINEL",
  works: ["INFLUENCE_WORK_SENTINEL"],
  links: [{
    label: "INFLUENCE_LINK_SENTINEL",
    url: "https://references.invalid/influence-sentinel"
  }],
  repo: null
};
const fixtureState = {
  on: true,
  mode: "on",
  condition: "",
  weight: 50,
  density: 24,
  binding: "",
  shape2: "",
  shape3: "",
  starPoints: null,
  branches: [],
  merge: "",
  role: "",
  trajectory: "",
  groupNoun: "",
  soulSection: "",
  cloud: ""
};
const state = { includeComments: true };

function tribState() { return fixtureState; }
function tribInject(t) { return t.inject || ""; }
function yamlish(value) { return String(value || "").replace(/\s+$/, ""); }
function formatDensity(value) { return String(value); }
function paramLines() { return []; }
function shapeLabel() { return ""; }
function trajectoryReading(value) { return value; }
function rungOf() { return { n: 1, of: 1 }; }
function tribColOf(t) { return t.col; }
function tribColor() { return ""; }
function tribRole() { return ""; }

eval(grab("topicBody"));
eval(grab("workshopTributary"));

const checked = topicBody(fixture, false);
check("checked runtime keeps character note", checked.includes(fixture.character));
check("checked runtime keeps work", checked.includes(fixture.works[0]));
check("checked runtime keeps citation label", checked.includes(fixture.links[0].label));
check("checked runtime keeps citation URL", checked.includes(fixture.links[0].url));

state.includeComments = false;
const unchecked = topicBody(fixture, false);
check("unchecked runtime removes character note", !unchecked.includes(fixture.character));
check("unchecked runtime removes work", !unchecked.includes(fixture.works[0]));
check("unchecked runtime removes citation label", !unchecked.includes(fixture.links[0].label));
check("unchecked runtime removes citation URL", !unchecked.includes(fixture.links[0].url));
check("unchecked runtime keeps personality instruction", unchecked.includes(fixture.inject));

const designCopy = topicBody(fixture, true);
check("unchecked design copy keeps character note", designCopy.includes(fixture.character));
check("unchecked design copy keeps work", designCopy.includes(fixture.works[0]));
check("unchecked design copy keeps citation label", designCopy.includes(fixture.links[0].label));
check("unchecked design copy keeps citation URL", designCopy.includes(fixture.links[0].url));

const workshopRow = workshopTributary(fixture);
check("workshop retains character note", workshopRow.comments.character === fixture.character);
check("workshop retains works", JSON.stringify(workshopRow.comments.works) === JSON.stringify(fixture.works));
check("workshop retains links", JSON.stringify(workshopRow.comments.links) === JSON.stringify(fixture.links));

const workshopBody = grab("workshopFile");
check("workshop persists Include influences choice",
  workshopBody.includes("includeComments: state.includeComments"));
check("workshop runtime derivative uses compiler",
  workshopBody.includes("const md = compilePrompt();"));

[
  "https://archive.org/details/hildegard-of-bingen-scivias",
  "https://www.gutenberg.org/ebooks/8120",
  "https://www.gutenberg.org/ebooks/15121",
  "https://archive.org/details/sayings-of-the-desert-fathers"
].forEach(function (url) {
  check("catalog omits bad reference " + url, !src.includes(url));
});

if (failures) process.exit(1);
