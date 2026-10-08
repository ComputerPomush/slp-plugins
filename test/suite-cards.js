/**
 * suite-cards.js - validates slp_avalon/assets/js/avalon-hours.js, v0.0.27 Part 4d.
 * r2, v0.0.27 Part 4e: keep() - which of a card's clicks stay off the card;
 * the identity through Part 4e's edits.
 *
 * Part 4d brings avalon-hours.js to the approved find-a-dealer design (the
 * owner's handoff of 2026-10-06, decisions of 2026-10-07):
 *
 *   words()   the weekday in full - "Closed \u00b7 Opens 9 AM Tuesday" - where
 *             Parts 4 to 4c wrote Google's "Tue"
 *   last()    a status's rest split before its last word; a time keeps its
 *             AM or PM, so "5 PM" is one word
 *   paint()   the last word and the caret held on one line in an
 *             avalon-hours__nowrap span; the caret an <i>, hidden from
 *             screen readers - SLP hides a card's empty spans; a status
 *             with no rest ("Open 24 hours") held whole, the caret never
 *             inside the coloured word
 *   label()   on a card and in the bubble, Hours: stands before the block,
 *             out of the <summary>: a click on it opens and shuts the week
 *             and goes no further, as a click in the block does; wired once
 *
 * r2, Part 4e (the owner's review of Part 4d on Aura DEV, 2026-10-07):
 *
 *   keep()    a card's hours block kept every click from the card, so a
 *             card with its week open took no click over most of its
 *             height and showed no ring. Only the Hours line itself - the
 *             <summary> and what is in it - and an attribution link keep
 *             their clicks now; a day's row, the table, the block reach
 *             the card. The walk up from the click stops at the block.
 *
 * WHAT CARRIES FORWARD BY IDENTITY. The first assertions reverse Part 4e's
 * three edits and require Part 4d's script byte for byte - 6d4c084f, 17,315
 * bytes - then Part 4d's and require Part 4b's - 76a62b75, 14,683 bytes.
 * Everything else in the file is Part 4b's: suite-hours.js r3 - Part 4's
 * suite, its fake DOM given what keep() reads - scores it 119/119, and
 * suite-bubble.js r2 - Part 4b's, unchanged, whose identity reads
 * reverse() from here, which takes Part 4e's edits out before Part 4d's -
 * 40/40.
 *
 * Runs the SHIPPED file in a vm context against a small fake DOM, as
 * suite-hours.js does. No npm dependencies - node alone. Intl is shadowed.
 *
 * Every section declares how many checks it makes, and a section that
 * throws fails every check it did not reach, so the total is the same
 * whatever script it is pointed at (s0.214). The test data is synthetic:
 * no dealer, address, phone or email.
 *
 *   node test/suite-cards.js <path-to-avalon-hours.js>
 *
 * suite-bubble.js require()s this file for reverse() only; nothing runs
 * then.
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");
const crypto = require("crypto");

/* Part 4b's script: what Part 4d's edits reverse to. */
const P4B = { md5: "76a62b751b5556b96b79c2734b05dca8", len: 14683 };
/* Part 4d's script: what Part 4e's edits reverse to. */
const P4D = { md5: "6d4c084f963e68271b0194926663eecf", len: 17315 };

/* r2. [what it is, what Part 4d had, what Part 4e wrote], in the order
   build-v027-part4e.py makes them. */
const P4E_EDITS = [
  ["avalon-hours.js: the header names Part 4e",
   " * avalon-hours.js\n" +
   " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n" +
   " *\n",
   " * avalon-hours.js\n" +
   " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4e.\n" +
   " *\n"],
  ["avalon-hours.js: the header says what Part 4e changes",
   " * and shuts the week.\n" +
   " */\n",
   " * and shuts the week.\n" +
   " *\n" +
   " * Part 4e: on a card, only the Hours line itself - the <summary> - and an\n" +
   " * attribution link keep their clicks from the card. A click on the opened\n" +
   " * week reaches it: with the week open, most of the card took no click and\n" +
   " * showed no ring (the owner, 2026-10-07).\n" +
   " */\n"],
  ["avalon-hours.js: keep() - only the summary and a link keep their clicks from the card",
   "  /**\n" +
   "   * On a result card, a click anywhere in the hours block - the Hours line,\n" +
   "   * the opened week, an attribution link - does what it does there and\n" +
   "   * nothing else. SLP binds a click on every result card that recentres\n" +
   "   * the map and opens its bubble, and the theme marks the card active;\n" +
   "   * neither should fire because a visitor wanted the hours. The native\n" +
   "   * toggle is the click's default action on the summary, so it still\n" +
   "   * happens. The store page has no such handler and is left alone.\n" +
   "   */\n" +
   "  function keep(e) {\n" +
   "    e.stopPropagation();\n" +
   "  }\n",
   "  /**\n" +
   "   * On a result card, a click on the Hours line - the <summary> - or on an\n" +
   "   * attribution link does what it does there and nothing else. SLP binds a\n" +
   "   * click on every result card that chooses its dealer (slp_avalon.js), and\n" +
   "   * the theme marks the card active; neither should fire because a visitor\n" +
   "   * wanted the hours. The native toggle is the click's default action on\n" +
   "   * the summary, so it still happens. The store page has no such handler\n" +
   "   * and is left alone.\n" +
   "   *\n" +
   "   * Part 4e. The opened week is the card again. Until Part 4e a click\n" +
   "   * anywhere in the block was kept, so a card with its week open took no\n" +
   "   * click over most of its height and showed no ring (the owner,\n" +
   "   * 2026-10-07). A click on a day's row now goes on to the card, as one on\n" +
   "   * its address does. The walk up from the click stops at the block: a\n" +
   "   * link the block itself might sit in is not this function's to judge.\n" +
   "   */\n" +
   "  function keep(e) {\n" +
   "    var n = e && e.target;\n" +
   "    if (n && n.nodeType === 3) {\n" +
   "      n = n.parentNode;\n" +
   "    }\n" +
   "    for (; n && n.tagName; n = n.parentNode) {\n" +
   "      if (n.tagName === \"SUMMARY\" || n.tagName === \"A\") {\n" +
   "        e.stopPropagation();\n" +
   "        return;\n" +
   "      }\n" +
   "      if ((\" \" + n.className + \" \").indexOf(\" avalon-hours \") >= 0) {\n" +
   "        return;\n" +
   "      }\n" +
   "    }\n" +
   "  }\n"]
];

/* [what it is, what Part 4b had, what Part 4d wrote], in the order
   build-v027-part4d.py makes them. */
const EDITS = [
  ["avalon-hours.js: the header names Part 4d",
   " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4b.\n",
   " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n"],
  ["avalon-hours.js: the header says what Part 4d changes",
   " * shown again, so \"Closes soon\" turns into \"Closed\" on time. Writes to the\n" +
   " * page only when the day or the words change.\n" +
   " */\n",
   " * shown again, so \"Closes soon\" turns into \"Closed\" on time. Writes to the\n" +
   " * page only when the day or the words change.\n" +
   " *\n" +
   " * Part 4d, the approved find-a-dealer design: the weekday in full (\"Opens\n" +
   " * 9 AM Tuesday\"); the status's last word and the caret held on one line,\n" +
   " * so the caret never starts a line alone - an element now, hidden from\n" +
   " * screen readers; and on a card and in the bubble, where Hours: has moved\n" +
   " * out of the <summary> into the label column, a click on it still opens\n" +
   " * and shuts the week.\n" +
   " */\n"],
  ["avalon-hours.js: the weekday in full",
   "  var SHORT = [\"Sun\", \"Mon\", \"Tue\", \"Wed\", \"Thu\", \"Fri\", \"Sat\"];\n",
   "  var DAYS = [\"Sunday\", \"Monday\", \"Tuesday\", \"Wednesday\", \"Thursday\", \"Friday\", \"Saturday\"];\n"],
  ["avalon-hours.js: words() documents the full weekday",
   "   *   Open \\u00b7 Closes 5 PM        Closes soon \\u00b7 5 PM      Open 24 hours\n" +
   "   *   Closed \\u00b7 Opens 9 AM       Closed \\u00b7 Opens 10 AM Sun    Opens soon \\u00b7 9 AM\n" +
   "   * The weekday is added to an opening that is not later today, and to a\n" +
   "   * closing a day or more away. Tone is open, closed or soon.\n",
   "   *   Open \\u00b7 Closes 5 PM        Closes soon \\u00b7 5 PM      Open 24 hours\n" +
   "   *   Closed \\u00b7 Opens 9 AM       Opens soon \\u00b7 9 AM\n" +
   "   *   Closed \\u00b7 Opens 10 AM Sunday\n" +
   "   * The weekday - in full from Part 4d, as the design has it - is added to\n" +
   "   * an opening that is not later today, and to a closing a day or more\n" +
   "   * away. Tone is open, closed or soon.\n"],
  ["avalon-hours.js: words() - the closing's weekday in full",
   "        return [\"Open\", \"open\", DOT + \"Closes \" + t + (st.left >= DAY ? \" \" + SHORT[day] : \"\")];\n",
   "        return [\"Open\", \"open\", DOT + \"Closes \" + t + (st.left >= DAY ? \" \" + DAYS[day] : \"\")];\n"],
  ["avalon-hours.js: words() - the opening's weekday in full",
   "        return [\"Closed\", \"closed\", DOT + \"Opens \" + t + (day === now.d && st.left < DAY ? \"\" : \" \" + SHORT[day])];\n",
   "        return [\"Closed\", \"closed\", DOT + \"Opens \" + t + (day === now.d && st.left < DAY ? \"\" : \" \" + DAYS[day])];\n"],
  ["avalon-hours.js: paint() holds the last word and the caret together",
   "  /** The status into every status slot of a block. No status, no change. */\n" +
   "  function paint(el, w) {\n" +
   "    if (!w) {\n" +
   "      return;\n" +
   "    }\n" +
   "    var slots = el.querySelectorAll(\".avalon-hours__status\");\n" +
   "    for (var i = 0; i < slots.length; i++) {\n" +
   "      var slot = slots[i];\n" +
   "      while (slot.firstChild) {\n" +
   "        slot.removeChild(slot.firstChild);\n" +
   "      }\n" +
   "      var word = doc.createElement(\"span\");\n" +
   "      word.className = \"avalon-hours__word avalon-hours__word--\" + w[1];\n" +
   "      word.textContent = w[0];\n" +
   "      slot.appendChild(word);\n" +
   "      if (w[2]) {\n" +
   "        slot.appendChild(doc.createTextNode(w[2]));\n" +
   "      }\n" +
   "    }\n" +
   "  }\n",
   "  /**\n" +
   "   * Part 4d. A status's rest split before its last word: [what comes\n" +
   "   * before it, the word], or null when the rest is empty. A time keeps\n" +
   "   * its AM or PM - \"5 PM\" is one word here - so the line never ends\n" +
   "   * \"Closes 5\" with \"PM\" and the caret under it.\n" +
   "   */\n" +
   "  function last(rest) {\n" +
   "    var m = /^([\\s\\S]*?)(\\S+(?: [AP]M)?)\\s*$/.exec(rest || \"\");\n" +
   "    return m ? [m[1], m[2]] : null;\n" +
   "  }\n" +
   "\n" +
   "  /**\n" +
   "   * Part 4d. The caret: drawn by avalon-hours.css, unseen by screen\n" +
   "   * readers. An <i>, as the PHP writes it: SLP hides a card's empty spans.\n" +
   "   */\n" +
   "  function caret() {\n" +
   "    var c = doc.createElement(\"i\");\n" +
   "    c.className = \"avalon-hours__caret\";\n" +
   "    c.setAttribute(\"aria-hidden\", \"true\");\n" +
   "    return c;\n" +
   "  }\n" +
   "\n" +
   "  /**\n" +
   "   * The status into every status slot of a block. No status, no change.\n" +
   "   * Part 4d: the last word and the caret after it in one\n" +
   "   * avalon-hours__nowrap span - the weekday, \"5 PM\", or a status with no\n" +
   "   * rest (\"Open 24 hours\") whole - so the caret never wraps alone. The\n" +
   "   * caret is never inside the coloured word: it keeps the line's colour.\n" +
   "   */\n" +
   "  function paint(el, w) {\n" +
   "    if (!w) {\n" +
   "      return;\n" +
   "    }\n" +
   "    var slots = el.querySelectorAll(\".avalon-hours__status\");\n" +
   "    for (var i = 0; i < slots.length; i++) {\n" +
   "      var slot = slots[i];\n" +
   "      while (slot.firstChild) {\n" +
   "        slot.removeChild(slot.firstChild);\n" +
   "      }\n" +
   "      var word = doc.createElement(\"span\");\n" +
   "      word.className = \"avalon-hours__word avalon-hours__word--\" + w[1];\n" +
   "      word.textContent = w[0];\n" +
   "      var held = doc.createElement(\"span\");\n" +
   "      held.className = \"avalon-hours__nowrap\";\n" +
   "      var tail = last(w[2]);\n" +
   "      if (tail) {\n" +
   "        slot.appendChild(word);\n" +
   "        if (tail[0]) {\n" +
   "          slot.appendChild(doc.createTextNode(tail[0]));\n" +
   "        }\n" +
   "        held.appendChild(doc.createTextNode(tail[1]));\n" +
   "      } else {\n" +
   "        held.appendChild(word);\n" +
   "      }\n" +
   "      held.appendChild(caret());\n" +
   "      slot.appendChild(held);\n" +
   "    }\n" +
   "  }\n"],
  ["avalon-hours.js: a click on the moved Hours: label opens the week",
   "  function keep(e) {\n" +
   "    e.stopPropagation();\n" +
   "  }\n",
   "  function keep(e) {\n" +
   "    e.stopPropagation();\n" +
   "  }\n" +
   "\n" +
   "  /**\n" +
   "   * Part 4d. On a card and in the bubble, Hours: sits in the label\n" +
   "   * column, just before the block and outside the <summary> it used to\n" +
   "   * be part of. A click on it still opens and shuts the week, and - like\n" +
   "   * a click in the block - goes no further. Mouse and touch only: the\n" +
   "   * label is hidden from screen readers, and the summary, which says\n" +
   "   * \"Hours:\" to them, is the control a keyboard reaches.\n" +
   "   */\n" +
   "  function label(el) {\n" +
   "    var l = el.previousElementSibling;\n" +
   "    if (!l || (\" \" + l.className + \" \").indexOf(\" avalon-label--hours \") < 0) {\n" +
   "      return;\n" +
   "    }\n" +
   "    l.addEventListener(\"click\", function (e) {\n" +
   "      e.stopPropagation();\n" +
   "      var d = el.querySelector(\".avalon-hours__narrow\");\n" +
   "      if (d) {\n" +
   "        d.open = !d.open;\n" +
   "      }\n" +
   "    }, false);\n" +
   "  }\n"],
  ["avalon-hours.js: enhance() wires the label once, with the block",
   "      if ((\" \" + el.className + \" \").indexOf(\" avalon-hours--card \") >= 0) {\n" +
   "        el.addEventListener(\"click\", keep, false);\n" +
   "      }\n",
   "      if ((\" \" + el.className + \" \").indexOf(\" avalon-hours--card \") >= 0) {\n" +
   "        el.addEventListener(\"click\", keep, false);\n" +
   "        label(el);\n" +
   "      }\n"],
  ["avalon-hours.js: last() exported, for its suite",
   "    words: words,\n",
   "    words: words,\n" +
   "    last: last,\n"]
];

/* A script with a list of edits taken out, the last made first; null when
   one of them is not there exactly once. */
function undo(text, edits) {
  let t = text;
  for (let i = edits.length - 1; i >= 0 && t !== null; i--) {
    const e = edits[i];
    if (t.split(e[2]).length - 1 !== 1) {
      return null;
    }
    t = t.replace(e[2], () => e[1]);
  }
  return t;
}
/* r2. The shipped script with Part 4e's edits taken out: Part 4d's. */
function reverse4e(text) {
  return undo(text, P4E_EDITS);
}
/* The shipped script with Part 4e's edits, then Part 4d's, taken out:
   Part 4b's. Before r2 the shipped script was Part 4d's. */
function reverse(text) {
  const p4d = reverse4e(text);
  return p4d === null ? null : undo(p4d, EDITS);
}

module.exports = { EDITS: EDITS, P4B: P4B, P4D: P4D, P4E_EDITS: P4E_EDITS, reverse: reverse, reverse4e: reverse4e };

if (require.main === module) {
  main();
}

function main() {
  const artefact = process.argv[2] || path.join(__dirname, "..", "slp_avalon", "assets", "js", "avalon-hours.js");
  let src;
  try {
    src = fs.readFileSync(artefact, "latin1");
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
      console.error("suite-cards: section " + name + " made " + (pass + fail - before) + " checks, declared " + count);
      process.exit(2);
    }
  }
  const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

  console.log("suite-cards  " + artefact);
  console.log("");

  /* ---------------------------------------------------------- identity */

  console.log("  IDENTITY");
  section("identity", 7, () => {
    const md5of = (t) => (t === null ? "" : crypto.createHash("md5").update(t, "latin1").digest("hex"));
    check(P4E_EDITS.every((e) => src.split(e[2]).length - 1 === 1),
          "each of Part 4e's " + P4E_EDITS.length + " edits is present exactly once");
    const p4d = reverse4e(src);
    check(p4d !== null && md5of(p4d) === P4D.md5 && Buffer.byteLength(p4d, "latin1") === P4D.len,
          "Part 4e's edits reversed, the file IS Part 4d's avalon-hours.js (6d4c084f, 17,315 bytes)");
    const present = p4d !== null && EDITS.every((e) => p4d.split(e[2]).length - 1 === 1);
    check(present, "  ... in which each of Part 4d's " + EDITS.length + " edits is present exactly once");
    const rev = reverse(src);
    const md5 = md5of(rev);
    check(rev !== null && md5 === P4B.md5 && Buffer.byteLength(rev, "latin1") === P4B.len,
          "those reversed too, the file IS Part 4b's avalon-hours.js (76a62b75, 14,683 bytes)");
    check(/^[\x00-\x7f]*$/.test(src) && src.indexOf("\r") < 0, "pure ASCII, LF");
    const code = src.replace(/\/\*[\s\S]*?\*\//g, "").replace(/^\s*\/\/.*$/gm, "");
    check(!/\bIntl\b|toLocale|getTimezoneOffset|\.get(Hours|Minutes|Day|Date|FullYear)\(/.test(code) && code.indexOf("openNow") < 0,
          "still no Intl, no local-time getters, no openNow");
    check(!/\beval\(|new Function|innerHTML|insertAdjacentHTML|outerHTML/.test(code),
          "no eval, no Function, no markup written as text - elements and text nodes only");
  });

  /* ---------------------------------------------------------- fake DOM */

  /* Enough DOM for the script: attributes, children, the selectors it
     queries, tbody.rows, previousElementSibling, and click dispatch with
     bubbling and stopPropagation. appendChild is counted, so a pass that
     rewrites nothing can be told from one that does. */
  let APPENDS = 0;
  class Txt {
    constructor(t) { this.t = String(t); this.parent = null; this.children = []; this.nodeType = 3; }
    get textContent() { return this.t; }
    get parentNode() { return this.parent; }
  }
  class El {
    constructor(tag, attrs) {
      this.tagName = String(tag).toUpperCase();
      this.nodeType = 1;
      this.attrs = Object.assign({}, attrs || {});
      this.children = [];
      this.parent = null;
      this.listeners = {};
      this.open = false;
    }
    get className() { return this.attrs["class"] || ""; }
    set className(v) { this.attrs["class"] = v; }
    getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; }
    hasAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k); }
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
    get parentNode() { return this.parent; }
    get firstChild() { return this.children[0] || null; }
    get previousElementSibling() {
      if (!this.parent) { return null; }
      const sibs = this.parent.children;
      for (let i = sibs.indexOf(this) - 1; i >= 0; i--) {
        if (sibs[i] instanceof El) { return sibs[i]; }
      }
      return null;
    }
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
        case ".avalon-hours__narrow":
          return els.filter((n) => n.hasClass("avalon-hours__narrow"));
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
    click() {
      const ev = { stopped: false, target: this, stopPropagation() { this.stopped = true; } };
      for (let n = this; n && !ev.stopped; n = n.parent) {
        (n.listeners.click || []).slice().forEach((fn) => fn(ev));
      }
      return ev;
    }
  }
  const T = (tag, attrs, kids) => {
    const e = new El(tag, attrs);
    (kids || []).forEach((k) => e.appendChild(typeof k === "string" ? new Txt(k) : k));
    return e;
  };
  /* What a node is, for comparing a painted slot: tag.class[attr=..]{text} */
  const shape = (n) => (n instanceof Txt ? JSON.stringify(n.t)
    : n.tagName.toLowerCase() + (n.className ? "." + n.className.split(" ").join(".") : "")
      + (n.getAttribute("aria-hidden") !== null ? "[aria-hidden=" + n.getAttribute("aria-hidden") + "]" : "")
      + (n.children.length ? "(" + n.children.map(shape).join(",") + ")" : ""));

  /* New York's offsets as the server prints them (suite-hours.js's fixture). */
  const NY = { o: [[1790992800, -240], [1793512800, -300], [1805007600, -240], [1825567200, -300]], u: 1825639200 };
  const P = (d, oh, om, ch, cm) => [[d, oh, om], [d, ch, cm]];
  const refw = [P(1, 9, 0, 17, 0), P(2, 9, 0, 17, 0), P(3, 9, 0, 17, 0), P(4, 9, 0, 17, 0),
                P(5, 9, 0, 17, 0), P(6, 9, 0, 17, 0), P(0, 10, 0, 15, 0)];
  const allday = [[[0, 0, 0]]];
  const D = " " + String.fromCharCode(0xb7) + " ";
  /* An instant, from New York's wall clock in EDT (UTC-4) - the window's
     first months. */
  const edt = (y, mo, d, h, mi) => new Date(Date.UTC(y, mo - 1, d, h + 4, mi || 0));
  const NAMES = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
  function week() {
    const tb = T("tbody");
    NAMES.forEach((name, i) => {
      tb.appendChild(T("tr", { "data-day": String((i + 1) % 7) }, [T("th", { scope: "row" }, [name]),
        T("td", {}, [T("span", { "class": "avalon-hours__time" }, [i === 6 ? "10 AM-3 PM" : "9 AM-5 PM"])])]));
    });
    return T("table", { "class": "avalon-hours__week" }, [tb]);
  }
  /* A card's hours as avalon_hours_markup() prints them from Part 4d: the
     label, then the block; in a cell that stands in for SLP's. */
  function card(periods) {
    const data = JSON.stringify({ tz: "America/New_York", o: NY.o, u: NY.u, p: periods || refw });
    const label = T("b", { "class": "avalon-label avalon-label--hours", "aria-hidden": "true" }, ["Hours:"]);
    const det = T("details", { "class": "avalon-hours__narrow" }, [
      T("summary", { "class": "avalon-hours__summary" }, [
        T("span", { "class": "avalon-hours__sr" }, ["Hours: "]),
        T("span", { "class": "avalon-hours__status" }, ["See ",
          T("span", { "class": "avalon-hours__nowrap" }, ["hours", T("i", { "class": "avalon-hours__caret", "aria-hidden": "true" })])])]),
      week()]);
    const block = T("div", { "class": "avalon-hours avalon-hours--card", "data-avalon-hours": data }, [det]);
    const phone = T("span", { "class": "slp_result_address slp_result_phone" }, ["Phone: 212-555-0100"]);
    const cell = T("div", { "class": "results_row_full_column sl_contact__info" }, [phone, label, block]);
    const entry = T("div", { "class": "results_wrapper", id: "slp_results_wrapper_7" }, [cell]);
    return { entry: entry, cell: cell, label: label, block: block, details: det, phone: phone };
  }
  function store() {
    const data = JSON.stringify({ tz: "America/New_York", o: NY.o, u: NY.u, p: refw });
    return T("div", { "class": "storelocator_address_container avalon-hours avalon-hours--store", "data-avalon-hours": data }, [
      T("div", { "class": "store_locator_single_hours" }, [
        T("h2", {}, ["Hours"]),
        T("div", { "class": "avalon-hours__wide" }, [T("p", { "class": "avalon-hours__status" }, [" "]), week()]),
        T("details", { "class": "avalon-hours__narrow" }, [
          T("summary", { "class": "avalon-hours__summary" }, [
            T("span", { "class": "avalon-hours__status" }, ["See ",
              T("span", { "class": "avalon-hours__nowrap" }, ["hours", T("i", { "class": "avalon-hours__caret", "aria-hidden": "true" })])])]),
          week()])])]);
  }
  const slots = (blk) => blk.querySelectorAll(".avalon-hours__status");

  /* A context with a fake document; no observer, no timers worth having. */
  function load() {
    const page = T("div");
    const fdoc = {
      readyState: "complete",
      visibilityState: "visible",
      querySelectorAll: (sel) => page.querySelectorAll(sel),
      getElementById: () => null,
      addEventListener: () => {},
      createElement: (t) => new El(t),
      createTextNode: (t) => new Txt(t)
    };
    const c = { document: fdoc, Intl: undefined, JSON: JSON, Math: Math, Date: Date, console: console,
                setTimeout: () => 1, clearTimeout: () => {}, addEventListener: () => {} };
    c.window = c;
    vm.runInNewContext(src, c, { filename: "avalon-hours.js" });
    return c.AvalonHours;
  }

  /* ------------------------------------------------------------- words */

  console.log("");
  console.log("  words() - the weekday in full");
  section("words", 6, () => {
    const H = load();
    check(same(Object.keys(H), ["clock", "localNow", "spans", "status", "words", "last", "enhance", "scan", "added", "lift"]),
          "AvalonHours: Part 4b's ten names, last() added between words() and enhance()");
    const say = (p, d, h, m) => { const now = { d: d, m: h * 60 + m }; const w = H.words(H.status(p, now), now); return w ? w[0] + w[2] : null; };
    check(say(refw, 1, 22, 0) === "Closed" + D + "Opens 9 AM Tuesday",
          "Mon 22:00 - Closed \u00b7 Opens 9 AM Tuesday, as the design has it");
    check(say(refw, 6, 22, 0) === "Closed" + D + "Opens 10 AM Sunday",
          "Sat 22:00 - the reference dealer: Closed \u00b7 Opens 10 AM Sunday (Google's own panel says Sun)");
    const seen = [];
    for (let d = 0; d < 7; d++) {
      const only = [P(d, 9, 0, 17, 0)];
      const w = H.words(H.status(only, { d: (d + 3) % 7, m: 600 }), { d: (d + 3) % 7, m: 600 });
      seen.push(w && w[2].slice((D + "Opens 9 AM ").length));
    }
    check(same(seen, ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]),
          "every weekday in full, Sunday to Saturday, after an opening on another day");
    const wd24 = [[[1, 0, 0], [6, 0, 0]]];
    check(say(wd24, 1, 12, 0) === "Open" + D + "Closes 12 AM Saturday" && say(wd24, 6, 12, 0) === "Closed" + D + "Opens 12 AM Monday",
          "a closing a day or more away, and an opening, carry the weekday in full");
    check(say(refw, 0, 7, 0) === "Closed" + D + "Opens 10 AM" && say(refw, 6, 16, 30) === "Closes soon" + D + "5 PM" &&
          say(refw, 0, 9, 15) === "Opens soon" + D + "10 AM" && say(allday, 3, 12, 0) === "Open 24 hours",
          "later today, the soon lines and Open 24 hours carry no weekday, as before");
  });

  /* -------------------------------------------------------------- last */

  console.log("");
  console.log("  last() - the status's last word");
  section("last", 3, () => {
    const H = load();
    check(same(H.last(D + "Opens 9 AM Tuesday"), [D + "Opens 9 AM ", "Tuesday"]) &&
          same(H.last(D + "Closes 12 AM Saturday"), [D + "Closes 12 AM ", "Saturday"]),
          "a weekday is the last word");
    check(same(H.last(D + "Closes 5 PM"), [D + "Closes ", "5 PM"]) && same(H.last(D + "12:30 PM"), [D, "12:30 PM"]) &&
          same(H.last(D + "Opens 9 AM"), [D + "Opens ", "9 AM"]) && same(H.last("Tuesday"), ["", "Tuesday"]),
          "a time keeps its AM or PM: \"5 PM\" and \"12:30 PM\" are one word each");
    check(H.last("") === null && H.last(undefined) === null && H.last(null) === null && H.last("   ") === null,
          "no rest - empty, missing, blank: null");
  });

  /* ------------------------------------------------------------- paint */

  console.log("");
  console.log("  paint() - the last word and the caret on one line");
  section("paint", 8, () => {
    const H = load();
    const CARET = "i.avalon-hours__caret[aria-hidden=true]";
    let c = card();
    H.enhance(c.block, edt(2026, 10, 5, 22, 0));
    check(slots(c.block).length === 1 && shape(slots(c.block)[0]) ===
          "span.avalon-hours__status(span.avalon-hours__word.avalon-hours__word--closed(\"Closed\")," +
          JSON.stringify(D + "Opens 9 AM ") + ",span.avalon-hours__nowrap(\"Tuesday\"," + CARET + "))",
          "a card: the coloured word, the rest, then Tuesday and the caret held in one nowrap span");
    check(slots(c.block)[0].textContent === "Closed" + D + "Opens 9 AM Tuesday",
          "  ... reading Closed \u00b7 Opens 9 AM Tuesday - the caret adds no text");
    const caret = c.block.all().filter((n) => n instanceof El && n.hasClass("avalon-hours__caret"));
    check(caret.length === 1 && caret[0].tagName === "I" && caret[0].children.length === 0,
          "  ... one caret, an <i> and empty: SLP hides a card's empty spans, never an <i>");
    c = card(allday);
    H.enhance(c.block, edt(2026, 10, 7, 12, 0));
    check(shape(slots(c.block)[0]) === "span.avalon-hours__status(span.avalon-hours__nowrap(span.avalon-hours__word.avalon-hours__word--open(\"Open 24 hours\")," + CARET + "))",
          "Open 24 hours, no rest: the coloured word held whole with the caret, which stays outside the colour");
    c = card();
    H.enhance(c.block, edt(2026, 10, 10, 16, 30));
    check(shape(slots(c.block)[0]) === "span.avalon-hours__status(span.avalon-hours__word.avalon-hours__word--soon(\"Closes soon\")," +
          JSON.stringify(D) + ",span.avalon-hours__nowrap(\"5 PM\"," + CARET + "))",
          "Closes soon \u00b7 5 PM: the time and its PM held with the caret");
    const s = store();
    H.enhance(s, edt(2026, 10, 5, 22, 0));
    const ss = slots(s);
    check(ss.length === 2 && ss.every((x) => x.textContent === "Closed" + D + "Opens 9 AM Tuesday" &&
          x.all().filter((n) => n instanceof El && n.hasClass("avalon-hours__caret")).length === 1),
          "the store page: both slots, the wide line and the phone fold, painted alike, one caret each (the stylesheet shows the fold's alone)");
    c = card();
    H.enhance(c.block, edt(2026, 10, 5, 22, 0));
    H.enhance(c.block, edt(2026, 10, 6, 8, 15));
    const one = slots(c.block)[0];
    check(one.textContent === "Opens soon" + D + "9 AM" &&
          one.all().filter((n) => n instanceof El && n.hasClass("avalon-hours__caret")).length === 1 &&
          one.all().filter((n) => n instanceof El && n.hasClass("avalon-hours__nowrap")).length === 1,
          "painted again when the words change: one nowrap span and one caret, nothing left over");
    const before = APPENDS;
    H.enhance(c.block, edt(2026, 10, 6, 8, 16));
    check(APPENDS === before, "the same words a minute later: nothing written");
  });

  /* ------------------------------------------------------------- label */

  console.log("");
  console.log("  label() - Hours:, out of the summary, still opens the week");
  section("label", 6, () => {
    const H = load();
    let c = card();
    let reached = 0;
    c.entry.addEventListener("click", () => { reached++; });   /* SLP's card handler, main.js's .active */
    H.enhance(c.block, edt(2026, 10, 5, 22, 0));
    c.label.click();
    const first = c.details.open;
    c.label.click();
    check(first === true && c.details.open === false, "a click on Hours: opens the week, a second shuts it");
    check(reached === 0, "  ... and goes no further: SLP's card click and main.js's .active never see it");
    c.phone.click();
    check(reached === 1 && c.details.open === false, "a click elsewhere on the card still reaches the card, and leaves the week alone");
    H.enhance(c.block, edt(2026, 10, 5, 22, 1));
    H.enhance(c.block, edt(2026, 10, 6, 8, 15));
    c.label.click();
    check(c.details.open === true && (c.label.listeners.click || []).length === 1,
          "enhanced again by the minute timer: still one listener - a click opens once, not twice");
    check(same(c.label.attrs, { "class": "avalon-label avalon-label--hours", "aria-hidden": "true" }),
          "  ... the label gains no attribute: it stays out of the keyboard's way; the summary is the control");
    /* Blocks whose neighbour is not their label: nothing wired, nothing thrown. */
    const s = store();
    const loose = T("b", { "class": "avalon-label avalon-label--hours" }, ["Hours:"]);
    T("div", {}, [loose, s]);
    H.enhance(s, edt(2026, 10, 5, 22, 0));
    c = card();
    c.cell.removeChild(c.label);
    H.enhance(c.block, edt(2026, 10, 5, 22, 0));
    const bare = card();
    bare.cell.children = [bare.block];
    H.enhance(bare.block, edt(2026, 10, 5, 22, 0));
    const odd = card();
    odd.block.children = [];
    let threw = false;
    try {
      H.enhance(odd.block, edt(2026, 10, 5, 22, 0));
      odd.label.click();
    } catch (e) {
      threw = true;
    }
    check(!(loose.listeners.click || []).length && !(c.phone.listeners.click || []).length && !threw && odd.details.open === false,
          "no label wired beside a store block, or for a card block after its phone line or alone; a label whose block has no week: its click throws nothing");
  });

  /* -------------------------------------------------------------- keep */

  console.log("");
  console.log("  keep() - Part 4e: which of a card's clicks stay off the card");
  section("keep", 9, () => {
    const H = load();
    const c = card();
    let reached = 0;
    c.entry.addEventListener("click", () => { reached++; });   /* SLP's card handler, main.js's .active */
    const link = T("a", { href: "https://example.test/attr" }, ["Test Source"]);
    const attr = T("p", { "class": "avalon-hours__attr" }, ["Hours from ", link]);
    c.details.appendChild(attr);
    H.enhance(c.block, edt(2026, 10, 5, 22, 0));
    const summary = c.details.children[0];
    const ev = summary.click();
    check(summary.tagName === "SUMMARY" && ev.stopped === true && reached === 0,
          "a click on the Hours line - the <summary> - goes no further: the card is not chosen for wanting the hours");
    const inner = summary.all().filter((n) => n instanceof El);
    const stops = inner.map((n) => n.click().stopped);
    check(inner.length >= 4 && stops.every((s) => s === true) && reached === 0,
          "  ... nor does one on anything inside it - the status, its coloured word, the caret (" + inner.length + " elements)");
    const tb = c.block.querySelectorAll(".avalon-hours__week tbody")[0];
    const row = tb.rows[3];
    row.click();
    row.children[0].click();
    row.children[1].click();
    row.children[1].children[0].click();
    check(reached === 4, "a click on a day's row of the opened week reaches the card - the row, its day, its hours, the span round them");
    tb.rows[0].click();
    tb.parent.click();
    c.details.click();
    c.block.click();
    check(reached === 8, "  ... as does one on today's row, the table, the <details> outside its summary, the block itself");
    const before = reached;
    const lev = link.click();
    check(lev.stopped === true && reached === before, "a click on an attribution link is kept from the card: the link is what was wanted");
    attr.click();
    check(reached === before + 1, "  ... one beside the link, on its paragraph, is the card's");
    /* A click whose target is a text node is its element's. */
    const fromText = (txt) => {
      const e = { stopped: false, target: txt, stopPropagation() { this.stopped = true; } };
      for (let n = txt.parent; n && !e.stopped; n = n.parent) {
        (n.listeners.click || []).slice().forEach((fn) => fn(e));
      }
      return e;
    };
    const sumText = summary.all().filter((n) => n instanceof Txt)[0];
    const rowText = row.all().filter((n) => n instanceof Txt)[0];
    const r0 = reached;
    check(fromText(sumText).stopped === true && fromText(rowText).stopped === false && reached === r0 + 1,
          "a click that lands on text: the summary's is kept, a row's goes on");
    let threw = false;
    let stoppedBare = false;
    try {
      (c.block.listeners.click || []).forEach((fn) => {
        fn({ stopPropagation() { stoppedBare = true; } });
        fn({ target: null, stopPropagation() { stoppedBare = true; } });
        fn(null);
      });
    } catch (e) {
      threw = true;
    }
    check(!threw && !stoppedBare && (c.block.listeners.click || []).length === 1,
          "an event with no target stops nothing and throws nothing; one guard on the block");
    /* The walk up stops at the block. */
    const wrapped = card();
    const outer = T("a", { href: "https://example.test/store" }, [wrapped.entry]);
    let outerReached = 0;
    outer.addEventListener("click", () => { outerReached++; });
    H.enhance(wrapped.block, edt(2026, 10, 5, 22, 0));
    const wev = wrapped.block.querySelectorAll(".avalon-hours__week tbody")[0].rows[2].click();
    const s = store();
    H.enhance(s, edt(2026, 10, 5, 22, 0));
    check(outerReached === 1 && wev.stopped === false && !(s.listeners.click || []).length,
          "the walk up from a click stops at the block - a link round the whole card does not keep a row's click; the store page's block has no guard at all");
  });

  console.log("");
  console.log("  " + pass + " passed, " + fail + " failed, " + (pass + fail) + " total");
  console.log("");
  process.exit(fail ? 1 : 0);
}
