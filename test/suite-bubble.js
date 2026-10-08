/**
 * suite-bubble.js - validates slp_avalon/assets/js/avalon-hours.js, v0.0.27 Part 4b.
 * r2, v0.0.27 Part 4d: the identity through Part 4d's edits; the weekday in full.
 *
 * Part 4b adds to avalon-hours.js what the map's info bubble needs, and
 * nothing else:
 *
 *   added()    whether a batch of DOM changes brought in an hours block - so
 *              the watcher on #map scans when a bubble opens and not for
 *              every map tile Google draws
 *   lift()     an hours block's week opened inside the bubble: when the
 *              bubble now reaches above the map's top edge, pan the map
 *              down by that much and 8 px more, through SLP's map
 *              (cslmap.gmap.panBy); never on closing, never outside a
 *              bubble, never without the map
 *   boot()     a MutationObserver on #map beside the one on #map_sidebar,
 *              and a capturing 'toggle' listener on the document - toggle
 *              does not bubble
 *
 * WHAT CARRIES FORWARD BY IDENTITY. The first assertion reverses the five
 * edits and requires Part 4's script byte for byte - abf65063, 12,279
 * bytes. Everything else in the file is Part 4's, and suite-hours.js -
 * Part 4's suite, unchanged - scores it 119/119 as before.
 *
 * r2. From Part 4d the identity takes Part 4d's edits out first - the
 * list and the reversal are suite-cards.js's, which require() reads
 * without running it - and works on Part 4b's script from there; the
 * painted words carry the weekday in full. Still 40 assertions.
 *
 * Runs the SHIPPED file in a vm context, as suite-hours.js does, against a
 * small fake DOM. No npm dependencies - node alone. Intl is shadowed.
 *
 * 40 assertions in five sections. The total is the same whatever script it
 * is pointed at: a section that throws fails every check it did not reach,
 * so a broken build cannot shrink the tally (s0.214). The test data is
 * synthetic: no dealer, address, phone or email.
 *
 *   node test/suite-bubble.js <path-to-avalon-hours.js>
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");
const crypto = require("crypto");

const artefact = process.argv[2] || path.join(__dirname, "..", "slp_avalon", "assets", "js", "avalon-hours.js");
let src;
try {
  src = fs.readFileSync(artefact, "utf8");
} catch (e) {
  console.error("cannot read " + artefact);
  process.exit(2);
}

let pass = 0;
let fail = 0;
function check(okay, label) {
  if (okay) {
    pass++;
    console.log("    [PASS] " + label);
  } else {
    fail++;
    console.log("    [FAIL] " + label);
  }
}
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
/* A throw is a counted failure, never a lost tally: a section declares how
   many checks it makes, and a throw fails every check it did not reach, so
   the total is the same for every script this suite is pointed at. */
function section(name, count, fn) {
  const before = pass + fail;
  try {
    fn();
  } catch (e) {
    const left = count - (pass + fail - before);
    for (let i = 0; i < left; i++) {
      check(false, name + " threw before this check could run: " + e.message);
    }
  }
  if (pass + fail - before !== count) {
    console.error("suite-bubble: section " + name + " made " + (pass + fail - before) + " checks, declared " + count);
    process.exit(2);
  }
}
/* An expression that calls the script under test: a throw is a false. */
function ok(fn) {
  try {
    return !!fn();
  } catch (e) {
    return false;
  }
}

console.log("suite-bubble  " + artefact);
console.log("");

/* ------------------------------------------------------------ identity */

const P4 = { md5: "abf650630474e77be40ea67b06fab3dd", len: 12279 };
/* [what Part 4b wrote, what Part 4 had] */
const EDITS = [
  [" * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4b.\n",
   " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4.\n"],
  [" * No dependencies. Works on whatever [data-avalon-hours] blocks are in the\n" +
   " * page when it starts (the store page), on those SLP inserts into\n" +
   " * #map_sidebar after each search (the result cards), and on the one a map\n" +
   " * pin's info bubble brings into #map (Part 4b). Recomputes on every\n",
   " * No dependencies. Works on whatever [data-avalon-hours] blocks are in the\n" +
   " * page when it starts (the store page) and on those SLP inserts into\n" +
   " * #map_sidebar after each search (the result cards). Recomputes on every\n"],
  null,   /* the functions block, below - the text is long */
  ["    var map = doc.getElementById(\"map\");\n" +
   "    if (map && root.MutationObserver) {\n" +
   "      new root.MutationObserver(function (records) {\n" +
   "        if (added(records)) {\n" +
   "          scan(map);\n" +
   "        }\n" +
   "      }).observe(map, { childList: true, subtree: true });\n" +
   "    }\n" +
   "    doc.addEventListener(\"toggle\", function (e) {\n" +
   "      try {\n" +
   "        lift(e);\n" +
   "      } catch (x) {\n" +
   "        /* The week stays open; the map just does not move. */\n" +
   "      }\n" +
   "    }, true);\n" +
   "    if (root.addEventListener) {\n" +
   "      root.addEventListener(\"pageshow\", wake, false);\n" +
   "    }\n",
   "    if (root.addEventListener) {\n" +
   "      root.addEventListener(\"pageshow\", wake, false);\n" +
   "    }\n"],
  ["    enhance: enhance,\n    scan: scan,\n    added: added,\n    lift: lift\n  };\n",
   "    enhance: enhance,\n    scan: scan\n  };\n"]
];
const FUNCS_START = "  /**\n   * Part 4b. Whether a batch of DOM changes brought in an hours block.";
const FUNCS_END = "  function boot() {\n";
/* added(), up() and lift() with their comments, as built by build-v027-part4b.py. */
const FUNCS_EXPECT = { md5: "9adb163caf2a866c584ca128b2cfadcb", len: 1858 };

console.log("  IDENTITY");
section("identity", 7, () => {
  /* r2: Part 4d's edits out first (suite-cards.js), so what follows reads
     Part 4b's script, as this suite was written against. */
  const p4b = require("./suite-cards.js").reverse(src);
  const base = p4b === null ? src : p4b;
  let rev = base;
  let ok = p4b !== null;
  const fa = base.indexOf(FUNCS_START);
  const fb = base.indexOf(FUNCS_END, fa);
  check(fa > 0 && fb > fa && base.indexOf(FUNCS_START, fa + 1) < 0, "the Part 4b functions sit once, directly before boot()");
  const funcs = fa > 0 && fb > fa ? base.slice(fa, fb) : "";
  const fmd5 = crypto.createHash("md5").update(funcs, "latin1").digest("hex");
  check(fmd5 === FUNCS_EXPECT.md5 && funcs.length === FUNCS_EXPECT.len,
        "the functions block is the one this suite was written against (" + FUNCS_EXPECT.md5 + ", " + FUNCS_EXPECT.len + " bytes)");
  rev = rev.slice(0, fa) + rev.slice(fb);
  EDITS.forEach((e, i) => {
    if (!e) { return; }
    const n = rev.split(e[0]).length - 1;
    if (n !== 1) { ok = false; console.log("      edit " + i + " found " + n + " times"); return; }
    rev = rev.replace(e[0], () => e[1]);
  });
  check(ok, "Part 4d's edits reversed, each of the other four edits is present exactly once");
  const md5 = crypto.createHash("md5").update(rev, "latin1").digest("hex");
  check(md5 === P4.md5 && Buffer.byteLength(rev, "latin1") === P4.len,
        "Part 4d's and the five reversed, the file IS Part 4's avalon-hours.js (abf65063, 12,279 bytes)");
  check(/^[\x00-\x7f]*$/.test(src) && src.indexOf("\r") < 0, "pure ASCII, LF");
  const code = src.replace(/\/\*[\s\S]*?\*\//g, "").replace(/^\s*\/\/.*$/gm, "");
  check(!/\bIntl\b|toLocale|getTimezoneOffset|\.get(Hours|Minutes|Day|Date|FullYear)\(/.test(code) && code.indexOf("openNow") < 0,
        "still no Intl, no local-time getters, no openNow");
  check(!/\beval\(|new Function|innerHTML/.test(src), "no eval, no Function, no innerHTML");
});

/* ------------------------------------------------------------ fake DOM */

/* suite-hours.js's fake DOM, with what Part 4b's code reads: nodeType,
   parentNode, hasAttribute, querySelector, and a box for
   getBoundingClientRect. */
class El {
  constructor(tag, attrs) {
    this.tagName = tag.toUpperCase();
    this.nodeType = 1;
    this.attrs = Object.assign({}, attrs || {});
    this.children = [];
    this.parent = null;
    this.listeners = {};
    this.box = null;
    this.queries = 0;
  }
  get parentNode() { return this.parent; }
  get className() { return this.attrs["class"] || ""; }
  set className(v) { this.attrs["class"] = v; }
  getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; }
  hasAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k); }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  removeAttribute(k) { delete this.attrs[k]; }
  appendChild(c) {
    if (c.parent) { c.parent.children.splice(c.parent.children.indexOf(c), 1); }
    c.parent = this;
    this.children.push(c);
    return c;
  }
  removeChild(c) {
    this.children.splice(this.children.indexOf(c), 1);
    c.parent = null;
    return c;
  }
  get firstChild() { return this.children[0] || null; }
  get rows() { return this.children.filter((c) => c.tagName === "TR"); }
  set textContent(v) {
    this.children.forEach((c) => { c.parent = null; });
    this.children = [];
    if (String(v) !== "") { this.appendChild(new Txt(v)); }
  }
  get textContent() { return this.children.map((c) => c.textContent).join(""); }
  hasClass(n) { return (" " + this.className + " ").indexOf(" " + n + " ") >= 0; }
  all() {
    const out = [];
    const walk = (n) => { n.children.forEach((c) => { out.push(c); walk(c); }); };
    walk(this);
    return out;
  }
  querySelectorAll(sel) {
    this.queries++;
    const els = this.all().filter((n) => n instanceof El);
    switch (sel) {
      case ".avalon-hours__week tbody":
        return els.filter((n) => n.tagName === "TBODY" && n.parent && n.parent.hasClass("avalon-hours__week"));
      case ".avalon-hours__status":
        return els.filter((n) => n.hasClass("avalon-hours__status"));
      case "[data-avalon-hours]":
        return els.filter((n) => n.getAttribute("data-avalon-hours") !== null);
      case "[data-avalon-ready]":
        return els.filter((n) => n.getAttribute("data-avalon-ready") !== null);
      default:
        throw new Error("fake DOM: unsupported selector " + sel);
    }
  }
  querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
  addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); }
  getBoundingClientRect() {
    if (this.box === "throw") { throw new Error("no layout"); }
    return { top: this.box ? this.box.top : 0, left: 0, width: 0, height: 0 };
  }
}
class Txt {
  constructor(t) { this.t = String(t); this.parent = null; this.children = []; this.nodeType = 3; }
  get textContent() { return this.t; }
  get parentNode() { return this.parent; }
}

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
const hours = ["9 AM-5 PM", "9 AM-5 PM", "9 AM-5 PM", "9 AM-5 PM", "9 AM-5 PM", "9 AM-5 PM", "10 AM-3 PM"];
function week() {
  const tbody = new El("tbody");
  DAYS.forEach((name, i) => {
    const tr = new El("tr", { "data-day": String((i + 1) % 7) });
    const th = new El("th"); th.textContent = name;
    const td = new El("td"); td.textContent = hours[i];
    tr.appendChild(th); tr.appendChild(td);
    tbody.appendChild(tr);
  });
  const table = new El("table", { "class": "avalon-hours__week" });
  table.appendChild(tbody);
  return table;
}
/* New York's offsets as the server prints them (suite-hours.js's fixture). */
const NY = { o: [[1790992800, -240], [1793512800, -300], [1805007600, -240], [1825567200, -300]], u: 1825639200 };
const P = (d, oh, om, ch, cm) => [[d, oh, om], [d, ch, cm]];
const refw = [P(1, 9, 0, 17, 0), P(2, 9, 0, 17, 0), P(3, 9, 0, 17, 0), P(4, 9, 0, 17, 0),
              P(5, 9, 0, 17, 0), P(6, 9, 0, 17, 0), P(0, 10, 0, 15, 0)];
const D = " " + String.fromCharCode(0xb7) + " ";
function cardBlock() {
  const root = new El("div", { "class": "avalon-hours avalon-hours--card",
                               "data-avalon-hours": JSON.stringify({ tz: "America/New_York", o: NY.o, u: NY.u, p: refw }) });
  const det = new El("details", { "class": "avalon-hours__narrow" });
  det.open = false;
  const sum = new El("summary", { "class": "avalon-hours__summary" });
  const st = new El("span", { "class": "avalon-hours__status" }); st.textContent = "See hours";
  sum.appendChild(st); det.appendChild(sum); det.appendChild(week());
  root.appendChild(det);
  return { root: root, details: det };
}
const statusOf = (blk) => blk.querySelectorAll(".avalon-hours__status").map((s) => s.textContent);

/* The map as Google builds it, as far as lift() and the watcher look:
   .gm-style > (panes) > .gm-style-iw-c > .gm-style-iw-d > div > bubble. */
function bubbleTree(mapTop, iwTop) {
  const map = new El("div", { "id": "map" });
  const gm = new El("div", { "class": "gm-style" }); gm.box = { top: mapTop };
  const pane = new El("div");
  const iwc = new El("div", { "class": "gm-style-iw gm-style-iw-c slp_bubble_level_3 slp_bubble_elements" }); iwc.box = { top: iwTop };
  const iwd = new El("div", { "class": "gm-style-iw-d" });
  const wrap = new El("div", { "class": "slp_bubble_level_1" });
  const bub = new El("div", { "class": "slp_info_bubble" });
  const c = cardBlock();
  bub.appendChild(c.root); wrap.appendChild(bub); iwd.appendChild(wrap); iwc.appendChild(iwd); pane.appendChild(iwc); gm.appendChild(pane); map.appendChild(gm);
  return { map: map, gm: gm, pane: pane, iwc: iwc, block: c.root, details: c.details };
}

/* A context with the fake document, timers and, when asked, an observer. */
function load(opts) {
  const env = { docL: {}, docCapture: {}, observers: [], pans: [], subs: [] };
  const page = opts.page || new El("div");
  const fdoc = {
    readyState: "complete",
    visibilityState: "visible",
    querySelectorAll: (sel) => page.querySelectorAll(sel),
    getElementById: (id) => (opts.ids && opts.ids[id]) || null,
    addEventListener: (t, fn, cap) => { (env.docL[t] = env.docL[t] || []).push(fn); (env.docCapture[t] = env.docCapture[t] || []).push(cap); },
    createElement: (t) => new El(t),
    createTextNode: (t) => new Txt(t)
  };
  const R = Date;
  const now = opts.now;
  function FakeDate() { return arguments.length ? new (Function.prototype.bind.apply(R, [null].concat([].slice.call(arguments))))() : new R(now); }
  FakeDate.now = () => now; FakeDate.UTC = R.UTC; FakeDate.prototype = R.prototype;
  const c = { document: fdoc, Intl: undefined, JSON: JSON, Math: Math, Date: FakeDate, console: console,
              setTimeout: () => 1, clearTimeout: () => {}, addEventListener: () => {} };
  if (opts.observer !== false) {
    c.MutationObserver = function (cb) { this.cb = cb; env.observers.push(this); };
    c.MutationObserver.prototype.observe = function (target, o) { this.target = target; this.opts = o; };
  }
  if (opts.slpFilter) {
    c.slp_Filter = (name) => ({ subscribe: (fn) => { env.subs.push([name, fn]); } });
  }
  if (opts.map !== false) {
    c.cslmap = opts.cslmap !== undefined ? opts.cslmap
             : { gmap: { panBy: (x, y) => { env.pans.push([x, y]); } } };
  }
  c.window = c;
  vm.runInNewContext(src, c, { filename: "avalon-hours.js" });
  env.ctx = c;
  env.H = c.AvalonHours;
  env.toggle = (target) => (env.docL.toggle || []).forEach((fn) => fn({ target: target }));
  return env;
}
const utc = (y, mo, d, h, mi) => Date.UTC(y, mo - 1, d, h, mi || 0, 0);

/* ------------------------------------------------------------- added() */

section("added()", 8, () => {
console.log("");
console.log("  added() - which DOM changes are worth a scan");
const H = load({ now: utc(2026, 10, 4, 2) }).H;
check(typeof H.added === "function" && typeof H.lift === "function", "AvalonHours exports added and lift");
const blk = cardBlock().root;
const holder = new El("div"); holder.appendChild(cardBlock().root);
const tile = new El("div", { "class": "gm-tile" }); tile.appendChild(new El("img"));
check(ok(() => H.added([{ addedNodes: [blk] }]) === true), "a batch that added an hours block itself: yes");
check(ok(() => H.added([{ addedNodes: [holder] }]) === true), "a batch that added something containing one - the bubble opening: yes");
check(ok(() => H.added([{ addedNodes: [tile, new El("img")] }, { addedNodes: [new El("div")] }]) === false), "map tiles and images: no");
check(ok(() => H.added([{ addedNodes: [new Txt("x")] }]) === false), "a text node: no, and no throw");
check(ok(() => H.added([{ addedNodes: [] }, { removedNodes: [blk] }]) === false), "nothing added, or only removed: no");
check(ok(() => H.added([{ addedNodes: [tile] }, { addedNodes: [holder] }]) === true), "a block in any record of the batch: yes");
check(ok(() => H.added([]) === false && H.added(null) === false && H.added([{}]) === false), "no records, or records without addedNodes: no, and no crash");
});

/* -------------------------------------------------------------- lift() */

section("lift()", 11, () => {
console.log("");
console.log("  lift() - the opened week brought into view");
/* lift() on one event; true when it returned without throwing. */
const fire = (e, ev) => ok(() => { e.H.lift(ev); return true; });
const t1 = bubbleTree(100, 60);
const e1 = load({ now: utc(2026, 10, 4, 2) });
t1.details.open = true;
check(fire(e1, { target: t1.details }) && same(e1.pans, [[0, -48]]), "top 40 px above the map's: panned down by 40 + 8 - panBy(0, -48)");
const t2 = bubbleTree(100, 59.5);
const e2 = load({ now: utc(2026, 10, 4, 2) });
t2.details.open = true;
check(fire(e2, { target: t2.details }) && same(e2.pans, [[0, -49]]), "a fraction of a pixel rounds away from the edge: (0, -49), never less than 8 px");
const t3 = bubbleTree(100, 108);
const e3 = load({ now: utc(2026, 10, 4, 2) });
t3.details.open = true;
check(fire(e3, { target: t3.details }) && e3.pans.length === 0, "already 8 px inside the map: no pan");
const t3b = bubbleTree(100, 107.5);
const e3b = load({ now: utc(2026, 10, 4, 2) });
t3b.details.open = true;
check(fire(e3b, { target: t3b.details }) && same(e3b.pans, [[0, -1]]), "half a pixel short of the margin: (0, -1)");
const t4 = bubbleTree(100, 20);
const e4 = load({ now: utc(2026, 10, 4, 2) });
t4.details.open = false;
check(fire(e4, { target: t4.details }) && e4.pans.length === 0, "closing the week: no pan");
const t5 = bubbleTree(100, 20);
const e5 = load({ now: utc(2026, 10, 4, 2) });
const other = new El("details"); other.open = true;
t5.iwc.appendChild(other);
check(fire(e5, { target: other }) && e5.pans.length === 0, "a <details> in the bubble that is not an hours block: no pan");
const card = cardBlock();
const side = new El("div", { "id": "map_sidebar" }); side.appendChild(card.root);
const e6 = load({ now: utc(2026, 10, 4, 2) });
card.details.open = true;
check(fire(e6, { target: card.details }) && e6.pans.length === 0, "a result card's week: no pan - it is not in a bubble");
const t7 = bubbleTree(100, 20);
t7.gm.attrs["class"] = "not-the-map";
const e7 = load({ now: utc(2026, 10, 4, 2) });
t7.details.open = true;
check(fire(e7, { target: t7.details }) && e7.pans.length === 0, "an InfoWindow with no .gm-style above it: no pan");
let threw = false;
[{ map: false }, { cslmap: {} }, { cslmap: { gmap: {} } }, { cslmap: { gmap: { panBy: "no" } } }, { cslmap: null }].forEach((o) => {
  const t = bubbleTree(100, 20);
  const e = load(Object.assign({ now: utc(2026, 10, 4, 2) }, o));
  t.details.open = true;
  try { e.H.lift({ target: t.details }); } catch (x) { threw = true; }
});
check(!threw, "no cslmap, no gmap, no panBy, or panBy not a function: no pan and no throw");
let threw2 = false;
const e8 = load({ now: utc(2026, 10, 4, 2) });
try { e8.H.lift(null); e8.H.lift({}); e8.H.lift({ target: new Txt("x") }); } catch (x) { threw2 = true; }
check(!threw2 && e8.pans.length === 0, "no event, no target, a text node: nothing, no throw");
const t9 = bubbleTree(100, 20);
t9.block.attrs["class"] = { toString: () => "[object SVGAnimatedString]" };
const e9 = load({ now: utc(2026, 10, 4, 2) });
t9.details.open = true;
let threw3 = false;
try { e9.H.lift({ target: t9.details }); } catch (x) { threw3 = true; }
check(!threw3 && e9.pans.length === 0, "an ancestor whose className is not a string: no match, no throw");
});

/* -------------------------------------------------------------- boot() */

section("boot()", 11, () => {
console.log("");
console.log("  boot() - the bubble's block found and kept live");
const page = new El("div");
const side = new El("div", { "id": "map_sidebar" });
const tree = bubbleTree(100, 60);
const bubbleBits = tree.pane.children.slice();
bubbleBits.forEach((b) => tree.pane.removeChild(b));      /* the bubble is not open yet */
page.appendChild(side); page.appendChild(tree.map);
const env = load({ page: page, ids: { map_sidebar: side, map: tree.map }, now: utc(2026, 10, 4, 2) });   /* Sat 22:00 EDT */
const byTarget = (t) => env.observers.filter((o) => o.target === t);
check(byTarget(side).length === 1 && byTarget(tree.map).length === 1 && env.observers.length === 2,
      "two MutationObservers: #map_sidebar, as in Part 4, and #map");
const mo = byTarget(tree.map)[0] || { cb: () => {}, opts: null };
check(same(mo.opts, { childList: true, subtree: true }), "#map is watched for children, in the whole subtree");
check((env.docL.toggle || []).length === 1 && (env.docCapture.toggle || [])[0] === true,
      "one 'toggle' listener on the document, in the CAPTURE phase - toggle does not bubble");
tree.map.queries = 0;
const tile = new El("div", { "class": "gm-tile" });
tree.pane.appendChild(tile);
check(ok(() => { mo.cb([{ addedNodes: [tile] }]); return true; }) && tree.map.queries === 0, "a map tile drawn: no scan of #map");
bubbleBits.forEach((b) => tree.pane.appendChild(b));      /* Google opens the bubble */
check(ok(() => { mo.cb([{ addedNodes: bubbleBits }]); return true; }) && tree.map.queries === 1, "the bubble opening: one scan of #map");
check(tree.block.getAttribute("data-avalon-ready") === "1", "the bubble's hours block is taken in");
check(statusOf(tree.block)[0] === "Closed" + D + "Opens 10 AM Sunday", "  ... and painted: Closed" + D + "Opens 10 AM Sunday");
const rows = tree.block.querySelectorAll(".avalon-hours__week tbody")[0].rows;
check(rows[0].getAttribute("data-day") === "6" && rows[0].className === "is-today", "  ... today, Saturday, first and bold");
check(ok(() => { mo.cb([{ addedNodes: bubbleBits }]); return true; }) && tree.map.queries === 2
      && statusOf(tree.block)[0] === "Closed" + D + "Opens 10 AM Sunday",
      "the same block again: scanned, not enhanced twice");
tree.details.open = true;
check(ok(() => { env.toggle(tree.details); return true; }) && same(env.pans, [[0, -48]]),
      "opening the week through the document listener pans the map: (0, -48)");
tree.iwc.box = "throw";
let threw = false;
try { env.toggle(tree.details); } catch (x) { threw = true; }
check(!threw, "a layout read that throws inside lift(): caught - the week stays open");
});

section("boot() without #map", 3, () => {
console.log("");
console.log("  boot() - pages without the map");
const page = new El("div");
const side = new El("div", { "id": "map_sidebar" });
page.appendChild(side);
const env = load({ page: page, ids: { map_sidebar: side }, now: utc(2026, 10, 4, 2) });
check(env.observers.length === 1 && env.observers[0].target === side, "no #map - a store page: only #map_sidebar is watched, as before");
check((env.docL.toggle || []).length === 1, "  ... and the toggle listener is there, harmless");
const store = new El("div");
const env2 = load({ page: store, observer: false, slpFilter: true, now: utc(2026, 10, 4, 2) });
check(env2.observers.length === 0 && env2.subs.length === 1 && env2.subs[0][0] === "location_search_processed",
      "no MutationObserver at all: SLP's location_search_processed, as in Part 4");
});

console.log("");
console.log("  " + pass + " passed, " + fail + " failed, " + (pass + fail) + " total");
console.log("");
process.exit(fail ? 1 : 0);
