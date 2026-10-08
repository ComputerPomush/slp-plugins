/**
 * suite-hours.js - validates slp_avalon/assets/js/avalon-hours.js, v0.0.27 Part 4.
 * r2, v0.0.27 Part 4d: the weekday in full, as the approved design has it.
 *
 * Runs the SHIPPED file in a vm context, as harness.js does for slp_avalon.js:
 * the suite exercises the artefact, not a copy of its logic. No npm
 * dependencies - node alone.
 *
 *   clock()     24-hour -> Google's "9 AM", "9:30 PM", "12 PM", "12 AM"
 *   localNow()  the dealer's own weekday and minute from the offset schedule
 *               the server sends (s0.278): both changes, the repeated hour,
 *               Arizona, British Columbia and Alberta on their new permanent
 *               offsets, and nothing at all outside the schedule's window
 *   spans()     periods joined where they touch or overlap, across Saturday
 *               night too, however many the join swallows; a period with no
 *               close, or a close equal to its open, is "open 24 hours" only
 *               when it opens Sunday 00:00, as Google sends it
 *   status()    open / closing / opening / closed / allday from the periods
 *   words()     the strings Google prints, middle dot included; the
 *               reference case is the dealer checked against Google's own
 *               panel on a Saturday evening, "Closed · Opens 10 AM Sun"
 *               (addendum rev43 s K) - from Part 4d (r2) with the weekday
 *               in full, "Closed · Opens 10 AM Sunday", the one change;
 *               suite-cards.js holds Part 4d's own checks
 *   enhance()   against a small fake DOM: today first and bold, the status
 *               painted into every slot, a card's clicks kept off the card,
 *               nothing rewritten while nothing changed, one bad block kept
 *               from stopping the rest, a block with no zone or an expired
 *               schedule left alone
 *   the clock   in a second context with a fake clock and timer queue: the
 *               refresh lands on the minute boundary, and a page shown again
 *               is brought up to date at once
 *   boot()      the MutationObserver on #map_sidebar, and SLP's
 *               location_search_processed where there is none
 *
 * The script runs with Intl removed from its global: it must never ask the
 * browser's own zone data.
 *
 *   node test/suite-hours.js <path-to-avalon-hours.js>
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");

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

/* ------------------------------------------------------------ fake DOM */

/* Just enough DOM for the script: attributes, children, the five selectors
   it queries, tbody.rows, and click dispatch with bubbling. appendChild is
   counted, so a pass that rewrites nothing can be told from one that does. */
let APPENDS = 0;
class El {
  constructor(tag, attrs) {
    this.tagName = tag.toUpperCase();
    this.attrs = Object.assign({}, attrs || {});
    this.children = [];
    this.parent = null;
    this.listeners = {};
  }
  get className() { return this.attrs["class"] || ""; }
  set className(v) { this.attrs["class"] = v; }
  getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  removeAttribute(k) { delete this.attrs[k]; }
  appendChild(c) {
    APPENDS++;
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
    const els = this.all().filter((n) => n instanceof El);
    switch (sel) {
      case ".avalon-hours__week tbody":
        return els.filter((n) => n.tagName === "TBODY" && n.parent && n.parent.hasClass("avalon-hours__week"));
      case ".avalon-hours__status":
        return els.filter((n) => n.hasClass("avalon-hours__status"));
      case "summary":
        return els.filter((n) => n.tagName === "SUMMARY");
      case "[data-avalon-hours]":
        return els.filter((n) => n.getAttribute("data-avalon-hours") !== null);
      case "[data-avalon-ready]":
        return els.filter((n) => n.getAttribute("data-avalon-ready") !== null);
      default:
        throw new Error("fake DOM: unsupported selector " + sel);
    }
  }
  addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); }
  click() {
    const ev = { stopped: false, stopPropagation() { this.stopped = true; } };
    for (let n = this; n && !ev.stopped; n = n.parent) {
      (n.listeners.click || []).forEach((fn) => fn(ev));
    }
    return ev;
  }
}
class Txt {
  constructor(t) { this.t = String(t); this.parent = null; this.children = []; }
  get textContent() { return this.t; }
}

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
function week(hours) {
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
function storeBlock(data, hours) {
  const root = new El("div", { "class": "storelocator_address_container avalon-hours avalon-hours--store", "data-avalon-hours": JSON.stringify(data) });
  const wide = new El("div", { "class": "avalon-hours__wide" });
  const p = new El("p", { "class": "avalon-hours__status" }); p.textContent = " ";
  wide.appendChild(p); wide.appendChild(week(hours));
  const det = new El("details", { "class": "avalon-hours__narrow" });
  const sum = new El("summary", { "class": "avalon-hours__summary" });
  const st = new El("span", { "class": "avalon-hours__status" }); st.textContent = "See hours";
  sum.appendChild(st); det.appendChild(sum); det.appendChild(week(hours));
  root.appendChild(wide); root.appendChild(det);
  return root;
}
function cardBlock(data, hours) {
  const root = new El("div", { "class": "avalon-hours avalon-hours--card", "data-avalon-hours": JSON.stringify(data) });
  const det = new El("details", { "class": "avalon-hours__narrow" });
  const sum = new El("summary", { "class": "avalon-hours__summary" });
  const b = new El("b", { "class": "avalon-label" }); b.textContent = "Hours:";
  const st = new El("span", { "class": "avalon-hours__status" }); st.textContent = "See hours";
  sum.appendChild(b); sum.appendChild(new Txt(" ")); sum.appendChild(st);
  det.appendChild(sum); det.appendChild(week(hours));
  root.appendChild(det);
  return root;
}
const statusOf = (blk) => blk.querySelectorAll(".avalon-hours__status").map((s) => s.textContent);

/* ------------------------------------------------------------- load it */

const doc = {
  readyState: "complete",
  querySelectorAll: () => [],
  getElementById: () => null,
  addEventListener: () => {},
  createElement: (t) => new El(t),
  createTextNode: (t) => new Txt(t)
};
/* Intl is shadowed with undefined: a script that reached for the browser's
   zone data would throw on load or answer wrong below. */
const ctx = { document: doc, Intl: undefined, JSON: JSON, Math: Math,
              setTimeout: () => 1, clearTimeout: () => {}, console: console };
ctx.window = ctx;
try {
  vm.runInNewContext(src, ctx, { filename: "avalon-hours.js" });
} catch (e) {
  console.error("the artefact threw on load: " + e.message);
  process.exit(2);
}
const H = ctx.AvalonHours;
if (!H || typeof H.status !== "function") {
  console.error("window.AvalonHours was not published");
  process.exit(2);
}

console.log("suite-hours  " + artefact);
console.log("");

/* ----------------------------------------------------------- artefact */

console.log("  ARTEFACT");
check(/^[\x00-\x7f]*$/.test(src), "pure ASCII - the middle dot is an escape");
const code = src.replace(/\/\*[\s\S]*?\*\//g, "").replace(/^\s*\/\/.*$/gm, "");
check(code.indexOf("openNow") < 0 && code.indexOf("open_now") < 0,
      "never reads openNow - the word appears only in comments");
check(!/\beval\(|new Function|innerHTML/.test(src), "no eval, no Function, no innerHTML");
check(!/\bIntl\b|toLocale|getTimezoneOffset|\.get(Hours|Minutes|Day|Date|FullYear)\(/.test(code),
      "s0.278: no Intl, no toLocale*, no local-time getters - only the UTC getters on a shifted clock");
check(code.indexOf("setInterval") < 0, "no setInterval - one timeout at a time, re-armed on each minute boundary");

/* -------------------------------------------------------------- clock */

console.log("");
console.log("  clock()");
check(H.clock(0, 0) === "12 AM", "midnight is 12 AM");
check(H.clock(24, 0) === "12 AM", "24:00 is 12 AM");
check(H.clock(12, 0) === "12 PM", "noon is 12 PM");
check(H.clock(9, 0) === "9 AM", "9:00 is 9 AM - no :00");
check(H.clock(9, 5) === "9:05 AM", "minutes are two digits");
check(H.clock(17, 30) === "5:30 PM", "17:30 is 5:30 PM");

/* ----------------------------------------------------------- localNow */

/* The schedules are what the server printed for these zones:
   SLP_Avalon::avalon_hours_offsets( $tz, 1791079200 ) - 2026-10-04 02:00 UTC,
   the PHP suite's T_NOW - on PHP 8.3.6 whose zone data already has tzdata
   2026b (British Columbia) and 2026c (Alberta). Measured 2026-10-04. */
const SCHED = {
  ny:  { o: [[1790992800, -240], [1793512800, -300], [1805007600, -240], [1825567200, -300]], u: 1825639200 },
  chi: { o: [[1790992800, -300], [1793516400, -360], [1805011200, -300], [1825570800, -360]], u: 1825639200 },
  phx: { o: [[1790992800, -420]], u: 1825639200 },
  van: { o: [[1790992800, -420], [1793523600, -420]], u: 1825639200 },
  edm: { o: [[1790992800, -360], [1793520000, -360]], u: 1825639200 }
};
const utc = (y, mo, d, h, mi, s) => Date.UTC(y, mo - 1, d, h, mi || 0, s || 0) / 1000;

console.log("");
console.log("  localNow() - the server's offset schedule");
check(SCHED.ny.o[0][0] === utc(2026, 10, 3, 2) && SCHED.ny.o[1][0] === utc(2026, 11, 1, 6)
      && SCHED.ny.o[2][0] === utc(2027, 3, 14, 7) && SCHED.ny.u === utc(2026, 10, 4, 2) + 400 * 86400,
      "fixture: New York's window opens a day before T_NOW, changes 1 Nov 06:00 and 14 Mar 07:00 UTC, ends 400 days on");
const at = (s) => new Date(s * 1000);
const ln = (k, s) => H.localNow(SCHED[k].o, SCHED[k].u, at(s));
const is = (n, d, h, m) => !!n && n.d === d && n.m === h * 60 + m;
check(is(ln("ny", utc(2026, 10, 4, 2)), 6, 22, 0), "Sat 22:00 EDT from 02:00 UTC Sunday");
check(is(ln("chi", utc(2026, 10, 4, 2)), 6, 21, 0), "Sat 21:00 CDT, same instant");
check(is(ln("phx", utc(2027, 7, 1, 17)), 4, 10, 0), "Phoenix keeps MST in July 2027: Thursday 10:00");
check(is(ln("ny", utc(2026, 11, 1, 5, 30)), 0, 1, 30), "1 November 05:30 UTC: 01:30 EDT");
check(is(ln("ny", utc(2026, 11, 1, 5, 59, 59)), 0, 1, 59) && is(ln("ny", utc(2026, 11, 1, 6)), 0, 1, 0),
      "the change is at 06:00:00 UTC exactly: 01:59, then 01:00");
check(is(ln("ny", utc(2026, 11, 1, 6, 30)), 0, 1, 30), "06:30 UTC: 01:30 again, now EST - the repeated hour");
check(is(ln("ny", utc(2026, 11, 1, 14)), 0, 9, 0), "after the November change: 09:00 EST");
check(is(ln("ny", utc(2027, 3, 14, 14)), 0, 10, 0), "after the March 2027 change: 10:00 EDT");
check(is(ln("ny", utc(2026, 10, 5, 4)), 1, 0, 0), "midnight reads as Monday minute 0, not Sunday 24:00");
check(is(ln("van", utc(2026, 12, 15, 20)), 2, 13, 0),
      "s0.278: Vancouver stays at -07 after 1 Nov 2026 - 13:00, where a browser on older zone data says 12:00");
check(is(ln("edm", utc(2026, 12, 15, 20)), 2, 14, 0),
      "s0.278: Edmonton stays at -06 - 14:00, where a browser on older zone data says 13:00");
check(ln("ny", SCHED.ny.o[0][0] - 1) === null, "a second before the window: null");
check(ln("ny", SCHED.ny.u) === null, "at the end of the window: null - no guessing past 400 days");
check(is(ln("ny", SCHED.ny.u - 60), 0, 20, 59), "a minute before the end: still read (Sun 7 Nov 2027, 20:59 EST)");
check(H.localNow([], SCHED.ny.u, at(utc(2026, 10, 4, 2))) === null, "an empty schedule: null");
check(H.localNow(SCHED.ny.o, undefined, at(utc(2026, 10, 4, 2))) === null, "no end of window: null");
check(H.localNow(null, SCHED.ny.u, at(utc(2026, 10, 4, 2))) === null, "no schedule at all: null");
check(H.localNow([[1790992800, "x"]], SCHED.ny.u, at(utc(2026, 10, 4, 2))) === null, "an offset that is not a number: null");

/* ------------------------------------ status + words, the reference week */

console.log("");
console.log("  status() and words() - the reference week (Mon-Sat 9-5, Sun 10-3)");
const P = (d, oh, om, ch, cm) => [[d, oh, om], [d, ch, cm]];
const refw = [P(1, 9, 0, 17, 0), P(2, 9, 0, 17, 0), P(3, 9, 0, 17, 0), P(4, 9, 0, 17, 0),
              P(5, 9, 0, 17, 0), P(6, 9, 0, 17, 0), P(0, 10, 0, 15, 0)];
const say = (periods, d, h, m) => {
  const now = { d: d, m: h * 60 + m };
  const w = H.words(H.status(periods, now), now);
  return w ? w[0] + w[2] : null;
};
const tone = (periods, d, h, m) => {
  const now = { d: d, m: h * 60 + m };
  const w = H.words(H.status(periods, now), now);
  return w ? w[1] : null;
};
const D = " · ";
check(say(refw, 6, 22, 0) === "Closed" + D + "Opens 10 AM Sunday", "Sat 22:00 - the reference dealer, Google's own panel's words, the weekday in full: Closed · Opens 10 AM Sunday");
check(tone(refw, 6, 22, 0) === "closed", "  ... in the closed tone");
check(say(refw, 6, 10, 0) === "Open" + D + "Closes 5 PM", "Sat 10:00 - Open · Closes 5 PM");
check(tone(refw, 6, 10, 0) === "open", "  ... in the open tone");
check(say(refw, 6, 16, 0) === "Closes soon" + D + "5 PM", "Sat 16:00 - Closes soon · 5 PM (exactly an hour)");
check(say(refw, 6, 15, 59) === "Open" + D + "Closes 5 PM", "Sat 15:59 - still Open (61 minutes)");
check(tone(refw, 6, 16, 30) === "soon", "Sat 16:30 - the soon tone");
check(say(refw, 6, 17, 0) === "Closed" + D + "Opens 10 AM Sunday", "Sat 17:00 - closed at the closing minute");
check(say(refw, 0, 9, 15) === "Opens soon" + D + "10 AM", "Sun 09:15 - Opens soon · 10 AM");
check(say(refw, 0, 7, 0) === "Closed" + D + "Opens 10 AM", "Sun 07:00 - later today, no weekday");
check(say(refw, 0, 16, 0) === "Closed" + D + "Opens 9 AM Monday", "Sun 16:00 - Closed · Opens 9 AM Monday");

console.log("");
console.log("  status() and words() - other shapes Google sends");
const weekdays = [P(1, 9, 0, 17, 0), P(2, 9, 0, 17, 0), P(3, 9, 0, 17, 0), P(4, 9, 0, 17, 0), P(5, 9, 0, 17, 0)];
check(say(weekdays, 6, 12, 0) === "Closed" + D + "Opens 9 AM Monday", "closed weekend (legacy sends no period): Opens 9 AM Monday");
check(say(weekdays, 5, 18, 0) === "Closed" + D + "Opens 9 AM Monday", "Friday evening, same answer");
const allday = [[[0, 0, 0]]];
check(say(allday, 3, 3, 0) === "Open 24 hours", "one period with no close, opening Sunday 00:00: Open 24 hours");
check(tone(allday, 3, 3, 0) === "open", "  ... in the open tone");
const late = [[[5, 18, 0], [6, 2, 0]]];
check(say(late, 6, 1, 30) === "Closes soon" + D + "2 AM", "past midnight: Closes soon · 2 AM");
check(say(late, 5, 23, 0) === "Open" + D + "Closes 2 AM", "Friday 23:00: Open · Closes 2 AM - no weekday");
const satnight = [[[6, 22, 0], [0, 3, 0]]];
check(say(satnight, 0, 1, 0) === "Open" + D + "Closes 3 AM", "Saturday into Sunday wraps the week");
check(say(satnight, 6, 21, 30) === "Opens soon" + D + "10 PM", "and opens soon before it");
const split = [[[1, 9, 0], [1, 12, 0]], [[1, 13, 0], [1, 17, 0]]];
check(say(split, 1, 12, 30) === "Opens soon" + D + "1 PM", "lunch break: Opens soon · 1 PM");
check(say(split, 1, 10, 0) === "Open" + D + "Closes 12 PM", "morning: Closes 12 PM");
const halfhour = [[[2, 8, 30], [2, 16, 30]]];
check(say(halfhour, 2, 7, 0) === "Closed" + D + "Opens 8:30 AM", "half hours print as 8:30 AM");
check(say([[[6, 9, 0], [6, 24, 0]]], 6, 23, 30) === "Closes soon" + D + "12 AM", "a close at hour 24 is midnight: Closes soon · 12 AM");
check(H.status([], { d: 1, m: 600 }) === null, "no periods: no status");
check(H.status(refw, null) === null, "no clock: no status");
check(H.words(null, { d: 1, m: 0 }) === null, "no status: no words");

/* -------------------------------------------------------------- spans */

console.log("");
console.log("  spans() - periods that touch, overlap or wrap");
const daily24 = [0, 1, 2, 3, 4, 5, 6].map((d) => [[d, 0, 0], [(d + 1) % 7, 0, 0]]);
check(same(H.spans(daily24), [[0, 10080]]), "seven midnight-to-midnight periods are one span, the whole week");
check(say(daily24, 3, 3, 0) === "Open 24 hours" && say(daily24, 6, 23, 30) === "Open 24 hours",
      "  ... so it reads Open 24 hours - never Closes soon at midnight");
const weekdays24 = [1, 2, 3, 4, 5].map((d) => [[d, 0, 0], [d + 1, 0, 0]]);
check(same(H.spans(weekdays24), [[1440, 8640]]), "Monday to Friday, round the clock: one span");
check(say(weekdays24, 5, 23, 30) === "Closes soon" + D + "12 AM", "  Fri 23:30 - Closes soon · 12 AM");
check(say(weekdays24, 1, 12, 0) === "Open" + D + "Closes 12 AM Saturday", "  Mon 12:00 - Open · Closes 12 AM Saturday (a day or more away)");
check(say(weekdays24, 6, 12, 0) === "Closed" + D + "Opens 12 AM Monday", "  Sat 12:00 - Closed · Opens 12 AM Monday");
check(say(weekdays24, 0, 23, 30) === "Opens soon" + D + "12 AM", "  Sun 23:30 - Opens soon · 12 AM");
const touching = [[[1, 9, 0], [1, 12, 0]], [[1, 12, 0], [1, 17, 0]]];
check(say(touching, 1, 11, 30) === "Open" + D + "Closes 5 PM", "two periods meeting at noon: Open · Closes 5 PM, not Closes soon · 12 PM");
const overlapping = [[[1, 9, 0], [1, 13, 0]], [[1, 12, 0], [1, 17, 0]]];
check(say(overlapping, 1, 12, 30) === "Open" + D + "Closes 5 PM", "overlapping periods: the later close wins");
const inside = [[[1, 9, 0], [1, 17, 0]], [[1, 10, 0], [1, 12, 0]]];
check(say(inside, 1, 11, 30) === "Open" + D + "Closes 5 PM", "a period inside another does not shorten it");
const wrap = [[[6, 20, 0], [0, 0, 0]], [[0, 0, 0], [0, 2, 0]]];
check(same(H.spans(wrap), [[9840, 10200]]), "Saturday to midnight plus Sunday 00:00-02:00: one span across the week's end");
check(say(wrap, 6, 23, 30) === "Open" + D + "Closes 2 AM", "  Sat 23:30 - Open · Closes 2 AM");
check(say(wrap, 0, 1, 30) === "Closes soon" + D + "2 AM", "  Sun 01:30 - Closes soon · 2 AM");
const wrap2 = [[[6, 22, 0], [0, 3, 0]], [[0, 0, 0], [0, 1, 0]], [[0, 2, 0], [0, 5, 0]]];
check(same(H.spans(wrap2), [[9960, 10380]]), "a Saturday-night span that swallows two Sunday-morning periods takes both");
check(say(wrap2, 6, 23, 0) === "Open" + D + "Closes 5 AM", "  Sat 23:00 - Open · Closes 5 AM, not 3 AM");
check(same(H.spans([P(3, 9, 0, 17, 0), P(1, 9, 0, 17, 0)]), [[1980, 2460], [4860, 5340]]), "periods out of order are sorted");
check(H.status([[[3, 9, 0]]], { d: 3, m: 600 }) === null, "a period with no close that does not open Sunday 00:00 is dropped");
check(say([[[3, 9, 0]], P(1, 9, 0, 17, 0)], 3, 12, 0) === "Closed" + D + "Opens 9 AM Monday",
      "  ... and does not turn the rest of the week into Open 24 hours");
check(say([P(1, 9, 0, 17, 0), [[3, 0, 0], [3, 0, 0]]], 0, 12, 0) === "Closed" + D + "Opens 9 AM Monday",
      "a period whose close equals its open, away from Sunday 00:00, is dropped too");
check(say([[[0, 0, 0], [0, 0, 0]]], 4, 4, 0) === "Open 24 hours", "  ... and at Sunday 00:00 it is Google's open 24 hours");
check(same(H.spans([[[1, 9], [1, 17, 0]], P(2, 9, 0, 17, 0)]), [[3420, 3900]]), "a malformed point drops its period");

/* ----------------------------------------------------------- enhance */

/* A throw below is a counted failure, never a lost tally: each section runs
   in section(), which records it by name. */
function section(name, fn) {
  try {
    fn();
  } catch (e) {
    check(false, name + " ran to the end - it threw: " + e.message);
  }
}

/* Fixtures every section below shares. */
const hours = ["9 AM–5 PM", "9 AM–5 PM", "9 AM–5 PM", "9 AM–5 PM", "9 AM–5 PM", "9 AM–5 PM", "10 AM–3 PM"];
const ny = (p) => ({ tz: "America/New_York", o: SCHED.ny.o, u: SCHED.ny.u, p: p });

section("enhance()", () => {
console.log("");
console.log("  enhance() - fake DOM");
const blk = storeBlock(ny(refw), hours);
H.enhance(blk, at(utc(2026, 10, 4, 2)));   /* Sat 22:00 EDT */
const bodies = blk.querySelectorAll(".avalon-hours__week tbody");
check(bodies.length === 2, "store page: both copies of the week are found");
check(bodies.every((b) => b.rows[0].getAttribute("data-day") === "6"), "today (Saturday) is first in both");
check(bodies.every((b) => b.rows.map((r) => r.getAttribute("data-day")).join("") === "6012345"), "then Sunday, Monday ... Friday");
check(bodies.every((b) => b.rows[0].className === "is-today" && b.rows[0].getAttribute("aria-current") === "date"),
      "today is bold (is-today) and aria-current=date");
check(bodies.every((b) => b.rows.slice(1).every((r) => r.className === "" && r.getAttribute("aria-current") === null)),
      "no other row is marked");
const slots = blk.querySelectorAll(".avalon-hours__status");
check(slots.length === 2 && slots.every((s) => s.textContent === "Closed" + D + "Opens 10 AM Sunday"),
      "both status slots read Closed · Opens 10 AM Sunday");
check(slots.every((s) => s.children[0].className === "avalon-hours__word avalon-hours__word--closed"),
      "the word carries the closed tone class");
check(blk.getAttribute("data-avalon-ready") === "1", "the block is marked ready");
check(!blk.listeners.click, "a store block gets no click guard - nothing on the store page needs one");
const word0 = slots[0].children[0];
let before = APPENDS;
H.enhance(blk, at(utc(2026, 10, 4, 2, 5)));   /* Sat 22:05 EDT - same day, same words */
check(APPENDS === before && slots[0].children[0] === word0,
      "five minutes on, nothing to change: no row moved, no node replaced");
H.enhance(blk, at(utc(2026, 10, 4, 14, 30)));   /* Sun 10:30 EDT */
check(bodies.every((b) => b.rows[0].getAttribute("data-day") === "0"), "a refresh on Sunday moves Sunday first");
check(slots.every((s) => s.textContent === "Open" + D + "Closes 3 PM"), "and repaints: Open · Closes 3 PM");
H.enhance(blk, at(utc(2026, 11, 1, 14, 30)));   /* Sun 1 Nov 09:30 EST */
check(slots.every((s) => s.textContent === "Opens soon" + D + "10 AM"),
      "a week later, after the change: 09:30 EST - Opens soon · 10 AM (an hour out on the summer offset)");

});

section("enhance() on a card", () => {
console.log("");
console.log("  enhance() - a result card");
const card = new El("div", { "id": "slp_results_wrapper_1", "class": "results_wrapper" });
let cardClicks = 0;
card.addEventListener("click", () => { cardClicks++; });
const cb = cardBlock(ny(refw), hours);
card.appendChild(cb);
H.enhance(cb, at(utc(2026, 10, 4, 2)));
check(statusOf(cb)[0] === "Closed" + D + "Opens 10 AM Sunday" && cb.querySelectorAll("summary")[0].textContent.indexOf("Hours: ") === 0,
      "the card's summary reads Hours: Closed · Opens 10 AM Sunday");
const ev = cb.querySelectorAll("summary")[0].click();
check(ev.stopped === true && cardClicks === 0, "a click on Hours: does not reach the card's click handler");
cb.querySelectorAll(".avalon-hours__week tbody")[0].rows[3].click();
check(cardClicks === 0, "nor does a click on a row of the opened week");
H.enhance(cb, at(utc(2026, 10, 4, 14, 30)));
H.enhance(cb, at(utc(2026, 10, 4, 15, 30)));
cb.querySelectorAll("summary")[0].click();
check(cardClicks === 0 && cb.listeners.click.length === 1, "the guard is bound once, not once per refresh");
const outside = new El("span");
card.appendChild(outside);
outside.click();
check(cardClicks === 1, "a click elsewhere on the card still reaches it");

});

section("enhance() on blocks to leave alone", () => {
console.log("");
console.log("  enhance() - blocks it must leave alone");
const nozone = storeBlock({ tz: "", o: [], u: 0, p: [] }, hours);
H.enhance(nozone, at(utc(2026, 10, 4, 2)));
check(nozone.querySelectorAll(".avalon-hours__week tbody")[0].rows[0].getAttribute("data-day") === "1",
      "no zone: the week stays Monday first");
check(statusOf(nozone)[1] === "See hours", "no zone: the slot keeps See hours");
check(nozone.getAttribute("data-avalon-ready") === "1", "no zone: still marked ready");
const noSched = storeBlock({ tz: "America/New_York", p: refw }, hours);
H.enhance(noSched, at(utc(2026, 10, 4, 2)));
check(statusOf(noSched)[1] === "See hours",
      "a zone without a schedule: left alone - the browser's own zone data is never the fallback");
const expired = storeBlock(ny(refw), hours);
H.enhance(expired, at(SCHED.ny.u + 86400));
check(expired.querySelectorAll(".avalon-hours__week tbody")[0].rows[0].getAttribute("data-day") === "1"
      && statusOf(expired)[1] === "See hours",
      "a page cached past its schedule's end: the week as printed, no status");
const broken = storeBlock({}, hours);
broken.setAttribute("data-avalon-hours", "{not json");
let threw = false;
try { H.enhance(broken, at(utc(2026, 10, 4, 2))); } catch (e) { threw = true; }
check(!threw && statusOf(broken)[1] === "See hours", "a broken data attribute is ignored, not thrown");

const page = new El("div");
const chi = { tz: "America/Chicago", o: SCHED.chi.o, u: SCHED.chi.u, p: refw };
const bad = storeBlock({ tz: "America/Chicago", o: [null], u: SCHED.chi.u, p: refw }, hours);
const a = storeBlock(chi, hours);
const b = storeBlock(chi, hours);
page.appendChild(bad); page.appendChild(a); page.appendChild(b);
b.setAttribute("data-avalon-ready", "1");
let scanThrew = false;
try { H.scan(page, at(utc(2026, 10, 4, 2))); } catch (e) { scanThrew = true; }
check(!scanThrew && statusOf(a)[1] === "Closed" + D + "Opens 10 AM Sunday",
      "a block whose schedule throws does not stop the next one");
check(statusOf(bad)[1] === "See hours" && bad.getAttribute("data-avalon-ready") === "1",
      "  ... it keeps its week as printed, and is not retried on every scan");
check(statusOf(b)[1] === "See hours", "scan() leaves a block already marked ready");

});

/* ------------------------------------------------- the clock, the boot */

/* A second context with a clock and a timer queue the suite controls. */
function boot(opts) {
  const env = { now: opts.now, timers: [], cleared: [], winL: {}, docL: {}, observers: [], subs: [] };
  const R = Date;
  function FakeDate() {
    return arguments.length ? new (Function.prototype.bind.apply(R, [null].concat([].slice.call(arguments))))() : new R(env.now);
  }
  FakeDate.now = () => env.now;
  FakeDate.UTC = R.UTC;
  FakeDate.prototype = R.prototype;
  const root = opts.page;
  const fdoc = {
    readyState: "complete",
    visibilityState: "visible",
    querySelectorAll: (sel) => root.querySelectorAll(sel),
    getElementById: (id) => (id === "map_sidebar" && opts.side) ? opts.side : null,
    addEventListener: (t, fn) => { (env.docL[t] = env.docL[t] || []).push(fn); },
    createElement: (t) => new El(t),
    createTextNode: (t) => new Txt(t)
  };
  const c = { document: fdoc, Intl: undefined, JSON: JSON, Math: Math, Date: FakeDate, console: console,
              setTimeout: (fn, ms) => { env.timers.push({ fn: fn, ms: ms, id: env.timers.length + 1 }); return env.timers.length; },
              clearTimeout: (id) => { env.cleared.push(id); },
              addEventListener: (t, fn) => { (env.winL[t] = env.winL[t] || []).push(fn); } };
  if (opts.observer) {
    c.MutationObserver = function (cb) { this.cb = cb; env.observers.push(this); };
    c.MutationObserver.prototype.observe = function (target, o) { this.target = target; this.opts = o; };
  }
  if (opts.slpFilter) {
    c.slp_Filter = (name) => ({ subscribe: (fn) => { env.subs.push([name, fn]); } });
  }
  if (opts.slpFilterThrows) {
    c.slp_Filter = () => { throw new Error("SLP changed"); };
  }
  c.window = c;
  env.doc = fdoc;
  vm.runInNewContext(src, c, { filename: "avalon-hours.js" });
  env.ctx = c;
  return env;
}
const live = (env) => env.timers.filter((t) => !t.fired && env.cleared.indexOf(t.id) < 0);
const fire = (env, i) => { env.timers[i].fired = true; env.timers[i].fn(); };

section("the clock", () => {
console.log("");
console.log("  the clock - a fake clock and timer queue");
const cpage = new El("div");
const cblk = storeBlock(ny(refw), hours);
cpage.appendChild(cblk);
const env = boot({ page: cpage, now: utc(2026, 10, 3, 20, 59, 59) * 1000 });   /* Sat 16:59:59 EDT */
check(statusOf(cblk)[0] === "Closes soon" + D + "5 PM", "loaded at 16:59:59: Closes soon · 5 PM");
check(env.timers.length === 1 && env.timers[0].ms === 1020, "the first refresh is armed for the minute boundary, 1.02 s away");
env.now += 1020;
if (env.timers[0]) { fire(env, 0); }
check(statusOf(cblk)[0] === "Closed" + D + "Opens 10 AM Sunday", "at 17:00:00 it reads Closed · Opens 10 AM Sunday - not a minute late");
check(env.timers.length === 2 && env.timers[1].ms === 60000, "and the next is armed a full minute on, still on the boundary");
env.now = utc(2026, 10, 4, 14, 30, 30) * 1000;   /* Sun 10:30:30 EDT, a day later */
(env.winL.pageshow || []).forEach((fn) => fn({}));
check(statusOf(cblk)[0] === "Open" + D + "Closes 3 PM", "a page shown again - back/forward cache - is brought up to date at once");
check(env.cleared.indexOf(2) >= 0 && env.timers.length === 3 && env.timers[2].ms === 30020 && live(env).length === 1,
      "  ... and its timer re-armed for the next boundary, one timer only");
env.now = utc(2026, 10, 4, 19, 30) * 1000;   /* Sun 15:30 EDT */
env.doc.visibilityState = "hidden";
(env.docL.visibilitychange || []).forEach((fn) => fn({}));
check(statusOf(cblk)[0] === "Open" + D + "Closes 3 PM", "hidden: no work");
env.doc.visibilityState = "visible";
(env.docL.visibilitychange || []).forEach((fn) => fn({}));
check(statusOf(cblk)[0] === "Closed" + D + "Opens 9 AM Monday" && live(env).length === 1,
      "visible again: brought up to date at once, still one timer");

});

section("boot()", () => {
console.log("");
console.log("  boot() - how new result cards are found");
const side = new El("div", { "id": "map_sidebar" });
const opage = new El("div");
opage.appendChild(side);
const oenv = boot({ page: opage, side: side, observer: true, now: utc(2026, 10, 4, 2) * 1000 });
const obs = oenv.observers[0] || { cb: () => {}, target: null, opts: null };
check(oenv.observers.length === 1 && obs.target === side && same(obs.opts, { childList: true, subtree: true }),
      "a MutationObserver watches #map_sidebar, children and subtree");
check(oenv.timers.length === 0, "no block yet, no timer");
const oc = cardBlock(ny(refw), hours);
side.appendChild(oc);
obs.cb([]);
check(statusOf(oc)[0] === "Closed" + D + "Opens 10 AM Sunday" && oenv.timers.length === 1,
      "a card SLP inserts after a search is enhanced, and the timer starts");
side.children.slice().forEach((ch) => side.removeChild(ch));
const oc2 = cardBlock(ny(refw), hours);
side.appendChild(oc2);
obs.cb([]);
check(statusOf(oc2)[0] === "Closed" + D + "Opens 10 AM Sunday" && oenv.timers.length === 1,
      "a second search's cards too, with the same single timer");
const fpage = new El("div");
const fenv = boot({ page: fpage, slpFilter: true, now: utc(2026, 10, 4, 2) * 1000 });
check(fenv.subs.length === 1 && fenv.subs[0][0] === "location_search_processed",
      "no MutationObserver: SLP's location_search_processed is subscribed instead");
const fc = cardBlock(ny(refw), hours);
fpage.appendChild(fc);
if (fenv.subs.length) { fenv.subs[0][1](); }
check(statusOf(fc)[0] === "Closed" + D + "Opens 10 AM Sunday", "  ... and a card it reports is enhanced");
let loadThrew = false;
const tpage = new El("div");
const tblk = storeBlock(ny(refw), hours);
tpage.appendChild(tblk);
try {
  boot({ page: tpage, now: utc(2026, 10, 4, 2) * 1000, slpFilterThrows: true });
} catch (e) { loadThrew = true; }
check(!loadThrew && statusOf(tblk)[0] === "Closed" + D + "Opens 10 AM Sunday",
      "no observer and an slp_Filter that throws: the script still loads, and the store page still works");
});

console.log("");
console.log("  " + pass + " passed, " + fail + " failed, " + (pass + fail) + " total");
console.log("");
process.exit(fail ? 1 : 0);
