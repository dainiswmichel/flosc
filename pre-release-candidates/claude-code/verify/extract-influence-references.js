// Reads the CATALOG array from the v190 personality builder (passed as argv[2])
// and prints every influence that carries reference links, as JSON.
"use strict";
const fs = require("fs");
const src = fs.readFileSync(process.argv[2], "utf8");
const start = src.indexOf("const CATALOG = [");
const end = src.indexOf("\n  ];", start);
const body = src.slice(start + "const CATALOG = ".length, end + 4);
const repo = function (id) { return { id: id }; };
const catalog = new Function("repo", "return " + body + ";")(repo);
const out = catalog.filter(function (t) { return t.links && t.links.length; }).map(function (t) {
  return { id: t.id, label: t.label, short: t.short || "", character: t.character || "", works: t.works || [], links: t.links };
});
process.stdout.write(JSON.stringify(out, null, 2));
