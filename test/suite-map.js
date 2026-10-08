/**
 * suite-map.js - validates slp_avalon/assets/js/slp_avalon.js, v0.0.27 Part 4c.
 * r2, v0.0.27 Part 4d: the chosen card, the bubble's fade, the placeholder;
 * fa() gone with the icons.
 *
 * Part 4c adds to slp_avalon.js the dealer bubble's behaviour on the map, in
 * one block - avalon_map - and two edits that wire it in:
 *
 *   controls()   the store page's map controls, set in cslmap_build_map()
 *                after SLP's map_options filter: zoom, Map and Satellite,
 *                Street View, full screen, no camera control
 *   show()       SLP's show_map_bubble() replaced, same contract: Google
 *                told shouldFocus: false; a choice (click, tap, key) moves
 *                focus to Contact Dealer once the content is in and the
 *                click is done, a hover never does, nor does a choice once
 *                focus has moved on - into the Contact Dealer form, or off
 *                the map; the dialog named for the dealer; on a phone a
 *                minimum width, changed by close(), setOptions(), open() -
 *                on the next bubble, or on the open one once the window
 *                has stopped resizing and the Contact Dealer form is not
 *                over it; focus Google moves on that close() undone
 *   the rules    the five the owner agreed: leaving the pin or the bubble
 *                closes it after 0.3 s unless the pointer is on the other;
 *                a click on a pin, a card or inside the bubble keeps it
 *                until another pin is chosen, Esc, or a click on the map;
 *                a card's hover opens and its leaving closes the same way;
 *                a tap is a click; never on its own while focus is inside,
 *                and focus moving in keeps it as a click does
 *   full screen  Contact Dealer on a map shown full screen leaves it first
 *   the pin      the hover icon and raised while hovered or open
 *   ring()       Part 4d: the chosen dealer's card marked .active, and no
 *                other, from the choice until the bubble closes; a hover
 *                marks nothing
 *   more()       Part 4d: .is-more on the bubble's body while there is more
 *                below - as it opens, as it scrolls, as its week opens or
 *                shuts, after a resize
 *
 * WHAT CARRIES FORWARD BY IDENTITY. The first assertions reverse the two
 * edits and require v0.0.25's slp_avalon.js byte for byte - 95c1ab24,
 * 76,976 bytes. Everything else in the file is v0.0.25's, and test/
 * suite-core.js and test/suite-v019.js still score it 63/63 and 38/38.
 *
 * r2. Part 4d's eleven edits inside the block are reversed first, and the
 * block must then be Part 4c's byte for byte - 040a111a, 25,525 bytes - so
 * what this suite proved of Part 4c carries forward; Part 4d's placeholder
 * edit, outside the block, is reversed with the other two. Font Awesome's
 * fa() and its six checks went with the icons; 18 checks came for the
 * chosen card and the fade.
 *
 * Runs the SHIPPED file in a vm context against fakes of Google Maps, the
 * page and jQuery - node alone, no npm. Timers are fake and advanced by
 * hand. As in a browser, focus in content taken out of the page falls to
 * <body>; Google's return of focus on close() ("Close an info window" in
 * its guide) is modelled, at its widest, where a section asks for it.
 *
 * Every section declares how many checks it makes, and a section that
 * throws fails every check it did not reach, so the total is the same
 * whatever script it is pointed at (s0.214). The test data is synthetic.
 *
 *   node test/suite-map.js <path-to-slp_avalon.js>
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");
const crypto = require("crypto");

const artefact = process.argv[2] || path.join(__dirname, "..", "slp_avalon", "assets", "js", "slp_avalon.js");
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
    console.error("suite-map: section " + name + " made " + (pass + fail - before) + " checks, declared " + count);
    process.exit(2);
  }
}
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

console.log("suite-map  " + artefact);
console.log("");

/* ------------------------------------------------------------ identity */

const V25 = { md5: "95c1ab2471359c2e080dc8477e1981fd", len: 76976 };
const crlf = (s) => s.replace(/\n/g, "\r\n");
/* [what Part 4c wrote, what v0.0.25 had] */
const MAP_EDIT = [crlf("    slp_Filter(\"map_options\").publish(avalon_cslmap.options);\n    //v0.0.27 Part 4c. The store page's controls, set after the filter so\n    //that nothing subscribed to it can take them away again (avalon_map).\n    avalon_map.controls(avalon_cslmap.options);\n  \n    avalon_cslmap.gmap = new google.maps.Map(map_div_id, avalon_cslmap.options);\n    //v0.0.27 Part 4c. The bubble's rules, once the map exists.\n    avalon_map.attach(avalon_cslmap);\n"), crlf("    slp_Filter(\"map_options\").publish(avalon_cslmap.options);\n  \n    avalon_cslmap.gmap = new google.maps.Map(map_div_id, avalon_cslmap.options);\n")];
const HOVER_OLD = crlf("  function enable_on_mouse_hover_for_markers() {\n    // Delegated so the handler survives SLP replacing the results markup on\n    // every search. Namespaced and cleared first: without the .off() these\n    // accumulate on document, one generation per search, and all of them fire.\n    jQuery(document).off(\"mouseenter.avalonHover\");\n    for (let i in avalon_cslmap.markers) {\n      let marker = avalon_cslmap.markers[i];\n      marker.__gmarker.addListener(\"mouseover\", function () {\n        avalon_cslmap.handle_location_result_click({\n          data: {\n            info: markers_list_natural[i],\n            marker: marker,\n          },\n        });\n      });\n      //Also add on mouse hover for the sidebar list\n      jQuery(document).on(\n        \"mouseenter.avalonHover\",\n        \"#slp_results_wrapper_\" + markers_list_natural[i].id,\n        {\n          info: markers_list_natural[i],\n          marker: marker,\n        },\n        avalon_cslmap.handle_location_result_click\n      );\n    }\n  }\n");
const BLOCK_START = "  function enable_on_mouse_hover_for_markers() {\r\n    //v0.0.27 Part 4c.";
const BLOCK_END = "  })();\r\n  \r\n  function get_short_address_from_geocode(address_components) {";
const BLOCK_PIN = { md5: "68201cb5f477826722607e97d6ffe4d4", len: 26839 };
const P4C_BLOCK = { md5: "040a111a23ad94957ea76d1019a9aeb6", len: 25525 };
/* Part 4d's edits, [what it is, what Part 4c had, what Part 4d wrote], in
   the order build-v027-part4d.py makes them; the first is the placeholder,
   outside the block. Written LF, read CRLF. */
const P4D_EDITS = [
  ["slp_avalon.js: a placeholder that fits the field",
   "       //Add search placeholder\n" +
   "      $(\"#addressInput\").attr('placeholder','Enter City, State, or Zip Code');\n",
   "       //Add search placeholder. v0.0.27 Part 4d: short enough to show whole\n" +
   "       //at every width - the field keeps 200 px for Find Locations above\n" +
   "       //1024 px, and the long one was cut off on laptops.\n" +
   "      $(\"#addressInput\").attr('placeholder','City, State, or ZIP');\n"],
  ["slp_avalon.js: avalon_map's header - the chosen card and the fade, no icons",
   "   * ICONS ON PHONES. fa() puts .avalon-fa on <html> once Font Awesome 5's\n" +
   "   * solid face has loaded, on the locator's page only; avalon-hours.css\n" +
   "   * draws the labels as icons only under it, so without the font the words\n" +
   "   * stay.\n",
   "   * THE CHOSEN CARD (Part 4d). The dealer whose bubble was chosen - by a\n" +
   "   * click, a tap or a key on its pin or card, or into the bubble - has its\n" +
   "   * card marked .active, which the theme draws as the design's ring, until\n" +
   "   * that bubble closes. A bubble opened by hovering marks nothing. main.js\n" +
   "   * marks a clicked card the same way.\n" +
   "   *\n" +
   "   * THE FADE (Part 4d). On a phone the bubble's body scrolls; while there\n" +
   "   * is more below, avalon-hours.css fades its foot (.is-more on\n" +
   "   * .sl_popup_contact_info): looked at as the bubble opens, as it scrolls,\n" +
   "   * as its week opens or shuts, and after a resize.\n" +
   "   *\n" +
   "   * Part 4c's Font Awesome labels went with Part 4d: words on every\n" +
   "   * screen, the owner's decision of 2026-10-07.\n"],
  ["slp_avalon.js: no icon flag in the state",
   "      icon: null,         //the hover icon, resolved; \"\" for none\n" +
   "      fa: false\n" +
   "    };\n",
   "      icon: null          //the hover icon, resolved; \"\" for none\n" +
   "    };\n"],
  ["slp_avalon.js: cls(), ring() and more() after container()",
   "    function controls(o) {\n",
   "    //Part 4d. A class on or off by name, the others left as they are.\n" +
   "    function cls(n, name, on) {\n" +
   "      if (on === has_class(n, name)) {\n" +
   "        return;\n" +
   "      }\n" +
   "      n.className = on ? (n.className ? n.className + \" \" : \"\") + name\n" +
   "                       : (\" \" + n.className + \" \").replace(\" \" + name + \" \", \" \").replace(/^\\s+|\\s+$/g, \"\");\n" +
   "    }\n" +
   "\n" +
   "    //Part 4d. The chosen dealer's card marked .active, and no other; with\n" +
   "    //no dealer, none.\n" +
   "    function ring(e) {\n" +
   "      var id = e ? \"slp_results_wrapper_\" + e.id : \"\";\n" +
   "      var on = document.querySelectorAll(\"#map_sidebar .results_wrapper.active\");\n" +
   "      for (var i = 0; i < on.length; i++) {\n" +
   "        if (on[i].id !== id) {\n" +
   "          cls(on[i], \"active\", false);\n" +
   "        }\n" +
   "      }\n" +
   "      var c = id ? document.getElementById(id) : null;\n" +
   "      if (c) {\n" +
   "        cls(c, \"active\", true);\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4d. The fade at the foot of the bubble's body while there is\n" +
   "    //more below it; none at the end, none where nothing scrolls.\n" +
   "    function more() {\n" +
   "      var b = bubble();\n" +
   "      var s = b ? b.querySelector(\".sl_popup_contact_info\") : null;\n" +
   "      if (s) {\n" +
   "        cls(s, \"is-more\", s.scrollHeight - s.scrollTop - s.clientHeight > 1);\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    function controls(o) {\n"],
  ["slp_avalon.js: clear() takes the ring off",
   "      if (e) {\n" +
   "        lit(e, hovered(e));\n" +
   "      }\n" +
   "      restore();\n",
   "      if (e) {\n" +
   "        lit(e, hovered(e));\n" +
   "      }\n" +
   "      ring(null);\n" +
   "      restore();\n"],
  ["slp_avalon.js: show() rings the chosen dealer's card",
   "      if (!hover) {\n" +
   "        st.pinned = true;\n" +
   "      }\n" +
   "    }\n",
   "      if (!hover) {\n" +
   "        st.pinned = true;\n" +
   "        ring(e);\n" +
   "      }\n" +
   "    }\n"],
  ["slp_avalon.js: resized() looks at the fade",
   "    function resized() {\n" +
   "      st.rs = 0;\n",
   "    function resized() {\n" +
   "      st.rs = 0;\n" +
   "      more();\n"],
  ["slp_avalon.js: a click into the bubble rings its card",
   "        c.addEventListener(\"click\", function (ev) {\n" +
   "          if (st.open) {\n" +
   "            st.pinned = true;\n" +
   "            cancel();\n" +
   "          }\n",
   "        c.addEventListener(\"click\", function (ev) {\n" +
   "          if (st.open) {\n" +
   "            st.pinned = true;\n" +
   "            ring(st.current);\n" +
   "            cancel();\n" +
   "          }\n"],
  ["slp_avalon.js: focus into the bubble rings its card",
   "          var from = ev && ev.relatedTarget;\n" +
   "          if (st.open) {\n" +
   "            st.pinned = true;\n" +
   "            cancel();\n" +
   "          }\n",
   "          var from = ev && ev.relatedTarget;\n" +
   "          if (st.open) {\n" +
   "            st.pinned = true;\n" +
   "            ring(st.current);\n" +
   "            cancel();\n" +
   "          }\n"],
  ["slp_avalon.js: ready() wires the fade to the new content",
   "      if (st.wantFocus) {\n" +
   "        soon();\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    function enter(e, kind) {\n",
   "      var b = bubble();\n" +
   "      var s = b ? b.querySelector(\".sl_popup_contact_info\") : null;\n" +
   "      if (s && !s.avalonMore) {\n" +
   "        s.avalonMore = true;\n" +
   "        s.addEventListener(\"scroll\", more, false);\n" +
   "        s.addEventListener(\"toggle\", more, true);\n" +
   "      }\n" +
   "      more();\n" +
   "      setTimeout(more, 0);\n" +
   "      if (st.wantFocus) {\n" +
   "        soon();\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    function enter(e, kind) {\n"],
  ["slp_avalon.js: fa() taken out",
   "\n" +
   "    //On the locator's page, once: .avalon-fa when Font Awesome's solid face\n" +
   "    //loads. fonts.load() resolves with the faces that matched - none when\n" +
   "    //the page has no such face, which fonts.check() would call loaded.\n" +
   "    function fa() {\n" +
   "      var d = document;\n" +
   "      if (st.fa || !d.getElementById(\"map_sidebar\")) {\n" +
   "        return;\n" +
   "      }\n" +
   "      st.fa = true;\n" +
   "      if (!d.fonts || typeof d.fonts.load !== \"function\") {\n" +
   "        return;\n" +
   "      }\n" +
   "      try {\n" +
   "        d.fonts.load('900 16px \"Font Awesome 5 Free\"', \"\\uf3c5\").then(function (faces) {\n" +
   "          if (faces && faces.length) {\n" +
   "            d.documentElement.classList.add(\"avalon-fa\");\n" +
   "          }\n" +
   "        }, function () {\n" +
   "          //No icon font: the labels keep their words.\n" +
   "        });\n" +
   "      } catch (x) {\n" +
   "        //As above.\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    return {\n",
   "\n" +
   "    return {\n"],
  ["slp_avalon.js: the block exports ring() and more(), and no fa()",
   "      close: close,\n" +
   "      fa: fa,\n" +
   "      state: st\n" +
   "    };\n" +
   "  })();\n" +
   "  jQuery(function () {\n" +
   "    avalon_map.fa();\n" +
   "  });\n",
   "      close: close,\n" +
   "      ring: ring,\n" +
   "      more: more,\n" +
   "      state: st\n" +
   "    };\n" +
   "  })();\n"]
].map((e) => [e[0], crlf(e[1]), crlf(e[2])]);
const PH_EDIT = P4D_EDITS[0];
const BLOCK_EDITS = P4D_EDITS.slice(1);
const md5of = (s) => crypto.createHash("md5").update(s, "latin1").digest("hex");

console.log("  IDENTITY");
section("identity", 9, () => {
  const a = src.indexOf(BLOCK_START);
  const b = src.indexOf(BLOCK_END, a);
  check(a > 0 && b > a && src.indexOf(BLOCK_START, a + 1) < 0,
        "the Part 4d block sits once, where enable_on_mouse_hover_for_markers() was");
  const blk = a > 0 && b > a ? src.slice(a, b + "  })();\r\n".length) : "";
  check(md5of(blk) === BLOCK_PIN.md5 && Buffer.byteLength(blk, "latin1") === BLOCK_PIN.len,
        "the block is the one this suite was written against (" + BLOCK_PIN.md5 + ", " + BLOCK_PIN.len + " bytes)");
  let p4c = blk;
  let inBlock = blk !== "";
  for (let i = BLOCK_EDITS.length - 1; i >= 0 && inBlock; i--) {
    if (p4c.split(BLOCK_EDITS[i][2]).length - 1 !== 1) { inBlock = false; break; }
    p4c = p4c.replace(BLOCK_EDITS[i][2], () => BLOCK_EDITS[i][1]);
  }
  check(inBlock && md5of(p4c) === P4C_BLOCK.md5 && Buffer.byteLength(p4c, "latin1") === P4C_BLOCK.len,
        "Part 4d's " + BLOCK_EDITS.length + " edits in it, each there once, reversed: Part 4c's block (040a111a, 25,525 bytes)");
  let rev = blk ? src.slice(0, a) + HOVER_OLD + src.slice(a + blk.length) : src;
  const ph = rev.split(PH_EDIT[2]).length - 1;
  check(ph === 1 && rev.indexOf("'City, State, or ZIP'") > 0 && rev.indexOf("'Enter City, State, or Zip Code'") < 0,
        "the placeholder edit is present exactly once, outside the block: City, State, or ZIP");
  if (ph === 1) {
    rev = rev.replace(PH_EDIT[2], () => PH_EDIT[1]);
  }
  const n = rev.split(MAP_EDIT[0]).length - 1;
  check(n === 1, "the cslmap_build_map() edit is present exactly once");
  if (n === 1) {
    rev = rev.replace(MAP_EDIT[0], () => MAP_EDIT[1]);
  }
  check(md5of(rev) === V25.md5 && Buffer.byteLength(rev, "latin1") === V25.len,
        "the block, the placeholder and the edit reversed, the file IS v0.0.25's slp_avalon.js (95c1ab24, 76,976 bytes)");
  check(/^[\x00-\x7f]*$/.test(src) && (src.match(/\r\n/g) || []).length === (src.match(/\n/g) || []).length
        && (src.match(/\r/g) || []).length === (src.match(/\n/g) || []).length,
        "pure ASCII, pure CRLF");
  const code = blk.replace(/\/\*[\s\S]*?\*\//g, "").replace(/^\s*\/\/.*$/gm, "");
  check(!/\beval\(|new Function|innerHTML|\.html\(|setInterval|document\.write/.test(code),
        "the block: no eval, no Function, no innerHTML or .html(), no setInterval, no document.write");
  check(code.indexOf("shouldFocus: false") > 0 && code.indexOf("handle_location_result_click") < 0 && code.indexOf("avalon-fa") < 0,
        "the block tells Google shouldFocus: false, never calls SLP's handle_location_result_click(), and has no icon code left");
});

/* ------------------------------------------------------------ the fakes */

class El {
  constructor(tag, attrs, env) {
    this.tagName = String(tag).toUpperCase();
    this.nodeType = 1;
    this.id = (attrs && attrs.id) || "";
    this.className = (attrs && attrs["class"]) || "";
    this.href = (attrs && attrs.href) || "";
    this.children = [];
    this.parentNode = null;
    this.listeners = {};
    this.env = env;
    this.focusCalls = [];
  }
  appendChild(c) { c.parentNode = this; this.children.push(c); return c; }
  addEventListener(t, fn, cap) { (this.listeners[t] = this.listeners[t] || []).push({ fn: fn, cap: !!cap }); }
  fire(t, ev) { (this.listeners[t] || []).forEach((l) => l.fn.call(this, ev || { type: t })); }
  all() {
    const out = [];
    const walk = (n) => n.children.forEach((c) => { out.push(c); walk(c); });
    walk(this);
    return out;
  }
  querySelector(sel) {
    if (sel === ".sl_popup_contact_info") {
      return this.all().filter((n) => /(^| )sl_popup_contact_info( |$)/.test(n.className))[0] || null;
    }
    const m = /^#([\w-]+) a$/.exec(sel);
    if (!m) { throw new Error("fake DOM: unsupported selector " + sel); }
    const host = this.all().filter((n) => n.id === m[1])[0];
    return host ? (host.all().filter((n) => n.tagName === "A")[0] || null) : null;
  }
  focus(opts) {
    this.focusCalls.push(opts);
    if (this.env) { this.env.doc.activeElement = this; this.env.focusLog.push([this.id || this.className || this.tagName, opts]); }
  }
  contains(n) { for (let x = n; x; x = x.parentNode) { if (x === this) { return true; } } return false; }
  blur() {
    this.blurCalls = (this.blurCalls || 0) + 1;
    if (this.env && this.env.doc.activeElement === this) { this.env.doc.activeElement = this.env.doc.body; }
  }
}

function makeEnv(opts) {
  opts = opts || {};
  const env = { timers: [], now: 0, focusLog: [], images: [], on: [], off: [], ready: [], filters: {}, winL: {}, scrolls: [],
                maps: [], iwLog: [], mapListeners: [], phone: !!opts.phone, mapW: opts.mapW || 1011,
                opens: 0, body: { scroll: 200, client: 200 } };
  /* timers */
  env.setTimeout = (fn, ms) => { const id = env.timers.length + 1; env.timers.push({ id: id, fn: fn, at: env.now + ms, done: false }); return id; };
  env.clearTimeout = (id) => { env.timers.forEach((t) => { if (t.id === id) { t.done = true; } }); };
  env.advance = (ms) => {
    const end = env.now + ms;
    for (;;) {
      const due = env.timers.filter((t) => !t.done && t.at <= end).sort((a, b) => a.at - b.at)[0];
      if (!due) { break; }
      env.now = due.at;
      due.done = true;
      due.fn();
    }
    env.now = end;
  };
  env.pending = () => env.timers.filter((t) => !t.done).length;

  /* the page */
  const body = new El("body", {}, env);
  const html = new El("html", {}, env);
  html.appendChild(body);
  const sidebar = new El("div", { id: "map_sidebar" }, env);
  const mapDiv = new El("div", { id: "map" }, env);
  mapDiv.clientWidth = env.mapW;
  body.appendChild(sidebar);
  body.appendChild(mapDiv);
  env.modalOpen = false;
  const doc = {
    body: body,
    documentElement: html,
    activeElement: body,
    listeners: {},
    getElementById: (id) => (id === "map_sidebar" && opts.noSidebar ? null : body.all().filter((n) => n.id === id)[0] || null),
    querySelector: (sel) => {
      if (sel === "#map .slp_info_bubble") { return mapDiv.all().filter((n) => /(^| )slp_info_bubble( |$)/.test(n.className))[0] || null; }
      if (sel === ".contact-dealer--pop-up.open-modal") { return env.modalOpen ? {} : null; }
      throw new Error("fake document: unsupported selector " + sel);
    },
    querySelectorAll: (sel) => {
      if (sel === "#map_sidebar .results_wrapper.active") {
        return sidebar.all().filter((n) => /(^| )results_wrapper( |$)/.test(n.className) && /(^| )active( |$)/.test(n.className));
      }
      throw new Error("fake document: unsupported selectorAll " + sel);
    },
    addEventListener: (t, fn) => { (doc.listeners[t] = doc.listeners[t] || []).push(fn); },
    createElement: (t) => {
      const e = new El(t, {}, env);
      if (t === "a") {
        let h = "";
        Object.defineProperty(e, "href", {
          get: () => h,
          set: (v) => { h = /^https?:/i.test(v) ? v : (v.charAt(0) === "/" ? "https://example.test" + v : "https://example.test/find-a-dealer/" + v); }
        });
      }
      return e;
    }
  };
  env.doc = doc;
  env.html = html;
  env.mapDiv = mapDiv;
  env.sidebar = sidebar;

  /* Google Maps */
  const listen = (obj, name, fn) => {
    obj.__l = obj.__l || {};
    (obj.__l[name] = obj.__l[name] || []).push(fn);
    return { remove: () => {} };
  };
  env.trigger = (obj, name, arg) => ((obj.__l && obj.__l[name]) || []).slice().forEach((fn) => fn(arg));
  const gmaps = {
    event: { addListener: listen, trigger: env.trigger },
    Marker: function () {},
    Map: function (div, o) { env.maps.push({ div: div, options: JSON.parse(JSON.stringify(o)) }); this.getZoom = () => 9; this.setZoom = () => {}; this.getDiv = () => mapDiv; },
    LatLng: function (a, b) { this.lat = a; this.lng = b; }
  };
  gmaps.Marker.MAX_ZINDEX = 1000000;
  env.google = { maps: gmaps };

  /* SLP's InfoWindow, as far as show() and the rules use it */
  const iw = {
    isOpen: false,
    opts: {},
    setOptions: (o) => { env.iwLog.push(["setOptions", JSON.parse(JSON.stringify(o))]); Object.assign(iw.opts, o); },
    setContent: (c) => { env.iwLog.push(["setContent", c.name]); iw.content = c; },
    open: (o) => {
      env.iwLog.push(["open", { anchor: o.anchor && o.anchor.label, shouldFocus: o.shouldFocus, map: o.map === env.gmap }]);
      env.opens++;
      iw.isOpen = true;
      iw.domPending = true;
      iw.prior = doc.activeElement;
    },
    close: () => {
      env.iwLog.push(["close"]);
      const was = iw.isOpen;
      iw.isOpen = false;
      env.empty();
      /* "focus moves back to the element that was in focus prior to the
         info window being opened" - Google's guide; modelled here at its
         widest, whether or not focus was in the bubble. */
      if (was && env.googleFocus) {
        /* "If that element is unavailable, focus is moved back to the map." */
        const to = iw.prior && iw.prior !== body && body.all().indexOf(iw.prior) >= 0 ? iw.prior : mapDiv;
        if (to !== doc.activeElement) {
          env.googleFocusMoves = (env.googleFocusMoves || 0) + 1;
          doc.activeElement = to;
          /* focus() without preventScroll brings the element into view */
          if (typeof to.top === "number") { env.ctx.pageYOffset = to.top; }
        }
      }
      if (was) { env.trigger(iw, "close"); }
    }
  };
  /* The bubble out of the page - only the bubble, not whatever else is in
     #map: as in a browser, focus in it falls to <body>. */
  env.empty = () => {
    const c = env.attached;
    if (!c) { return; }
    const lost = c.all().indexOf(doc.activeElement) >= 0;
    mapDiv.children = mapDiv.children.filter((n) => n !== c);
    c.parentNode = null;
    env.attached = null;
    if (lost) { doc.activeElement = body; }
  };
  env.iw = iw;
  /* Google puts the content in the page, then fires domready. */
  env.dom = () => {
    if (!iw.domPending) { return null; }
    iw.domPending = false;
    env.empty();
    const c = new El("div", { "class": "gm-style-iw gm-style-iw-c slp_bubble_level_3" }, env);
    const d = new El("div", { "class": "gm-style-iw-d" }, env);
    const b = new El("div", { "class": "slp_info_bubble", id: "slp_info_bubble_" + iw.content.id }, env);
    /* The body that scrolls on a phone (Part 4d): its heights from env.body. */
    const info = new El("div", { "class": "sl_popup_contact_info" }, env);
    info.scrollHeight = env.body.scroll;
    info.clientHeight = env.body.client;
    info.scrollTop = 0;
    const tel = new El("a", { "class": "avalon-tel" }, env);
    info.appendChild(tel);
    b.appendChild(info);
    env.info = info;
    if (iw.content.directions !== false) {
      const dir = new El("span", { id: "slp_bubble_directions" }, env);
      dir.appendChild(new El("a", { "class": "storelocatorlink" }, env));
      b.appendChild(dir);
    }
    if (iw.content.url) {
      const web = new El("span", { id: "slp_bubble_website" }, env);
      web.appendChild(new El("a", { "class": "storelocatorlink" }, env));
      b.appendChild(web);
    }
    d.appendChild(b);
    c.appendChild(d);
    mapDiv.appendChild(c);
    env.container = c;
    env.attached = c;
    env.bubble = b;
    env.trigger(iw, "domready");
    return c;
  };
  env.gmap = { getDiv: () => mapDiv, getZoom: () => 9, setZoom: (z) => { env.zoom = z; } };

  /* jQuery, as far as slp_avalon.js uses it at load and in these paths */
  function chain(target) {
    const handler = {
      get: (t, p) => {
        if (p === "length") { return 0; }
        if (p === "on") { return (...a) => { env.on.push({ target: target, args: a }); return proxy; }; }
        if (p === "off") { return (...a) => { env.off.push({ target: target, args: a }); return proxy; }; }
        if (p === "ready") { return (fn) => { env.ready.push(fn); return proxy; }; }
        if (p === "val" || p === "text" || p === "attr" || p === "data") { return () => ""; }
        return () => proxy;
      }
    };
    const proxy = new Proxy(function () {}, handler);
    return proxy;
  }
  const jq = function (x) {
    if (typeof x === "function") { env.ready.push(x); return chain(null); }
    return chain(x);
  };
  jq.extend = Object.assign;
  jq.trim = (s) => String(s).trim();
  jq.fn = {};
  env.jq = jq;

  const ctx = {
    jQuery: jq, $: jq, console: console, JSON: JSON, Math: Math, String: String, Object: Object, Array: Array,
    document: doc, google: env.google, setTimeout: env.setTimeout, clearTimeout: env.clearTimeout,
    addEventListener: (t, fn) => { (env.winL[t] = env.winL[t] || []).push(fn); },
    pageXOffset: 0,
    pageYOffset: 0,
    scrollTo: (x, y) => { env.scrolls.push([x, y]); env.ctx.pageXOffset = x; env.ctx.pageYOffset = y; },
    matchMedia: (q) => ({ matches: q === "(max-width: 767px)" && env.phone, media: q }),
    navigator: { geolocation: { getCurrentPosition: () => {} } },
    location: { href: "https://example.test/find-a-dealer/", hash: "" },
    URL: URL,
    Image: function () { env.images.push(this); },
    slplus: { options: Object.assign({ hide_bubble: "0", zoom_level: "12", immediately_show_locations: "0",
                                       avalon_map_hover_icon: "/wp-content/uploads/hover-pin.png" }, opts.options || {}) },
    slp_Filter: (name) => ({
      publish: (o) => { (env.filters[name] = env.filters[name] || []).forEach((fn) => fn(o)); (env.published = env.published || []).push([name, o && JSON.parse(JSON.stringify(o))]); },
      subscribe: (fn) => { (env.filters[name] = env.filters[name] || []).push(fn); }
    })
  };
  if (opts.noIcon) { delete ctx.slplus.options.avalon_map_hover_icon; }
  ctx.window = ctx;
  vm.runInNewContext(src, ctx, { filename: "slp_avalon.js" });
  env.ctx = ctx;
  env.M = ctx.avalon_map;
  return env;
}

/* One dealer's marker, as SLP's slp_Marker carries it. */
function gm(env, label, icon) {
  let ic = icon || "https://example.test/wp-content/uploads/pin.png";
  let z;
  const g = {
    label: label,
    sets: [],
    getIcon: () => ic,
    setIcon: (v) => { g.sets.push(["icon", v]); ic = v; },
    getZIndex: () => z,
    setZIndex: (v) => { g.sets.push(["z", v]); z = v; }
  };
  return g;
}
/* A map object like SLP's cslmap, with n dealers; the list is SLP's response. */
function cslmap(env, n, opts) {
  opts = opts || {};
  const markers = [];
  const list = [];
  for (let i = 0; i < n; i++) {
    const id = String(101 + i);
    const g = gm(env, "m" + id);
    markers.push({ __gmarker: g, __location_id: id });
    list.push({ id: id, name: "Dealer &amp; Sons " + id, url: opts.nourl ? "" : "https://dealer.test/" + id });
  }
  const cm = {
    gmap: env.gmap,
    infowindow: env.iw,
    markers: markers,
    options: null,
    createMarkerContent: (info) => ({ name: info.name, id: info.id, url: info.url, directions: !opts.nodirections })
  };
  if (opts.order) { list.reverse(); }
  env.cm = cm;
  env.list = list;
  return cm;
}
/* The sequence a page goes through: map built, then a search drawn. */
function boot(opts) {
  const env = makeEnv(opts);
  const cm = cslmap(env, (opts && opts.n) || 3, opts);
  env.M.attach(cm);
  env.M.bind(cm, env.list);
  return env;
}
const g = (env, i) => env.cm.markers[i].__gmarker;
const over = (env, i) => env.trigger(g(env, i), "mouseover");
const out = (env, i) => env.trigger(g(env, i), "mouseout");
const click = (env, i) => env.cm.show_map_bubble(env.list.filter((x) => x.id === env.cm.markers[i].__location_id)[0], env.cm.markers[i]);
const cardHandler = (env, type) => env.on.filter((o) => o.args[0] === type + ".avalonMap" && o.args[1] === "#map_sidebar .results_wrapper")[0];
const card = (env, type, i) => cardHandler(env, type).args[2].call({ id: "slp_results_wrapper_" + env.cm.markers[i].__location_id });
const key = (env, ev) => (env.doc.listeners.keydown || []).forEach((fn) => fn(Object.assign({ defaultPrevented: false }, ev)));
const st = (env) => env.M.state;
const cur = (env) => (st(env).current ? st(env).current.id : null);
const HOVER = "https://example.test/wp-content/uploads/hover-pin.png";

/* --------------------------------------------------------- the controls */

section("controls", 6, () => {
console.log("");
console.log("  THE MAP'S CONTROLS, AND THE MAP BUILT");
const env = makeEnv();
const o = { zoom: 5 };
const r = env.M.controls(o);
check(r === o && o.cameraControl === false && o.zoomControl === true && o.mapTypeControl === true &&
      o.streetViewControl === true && o.fullscreenControl === true && o.zoom === 5,
      "controls(): zoom, Map and Satellite, Street View, full screen on, camera off; nothing else touched");
check(env.M.controls(null) === null && env.M.controls("x") === "x", "controls(): anything not an object passes through");
/* cslmap_build_map() as SLP calls it, with SLP Experience's map_options
   subscriber taking Map and Satellite away as it does on Aura ("0"). */
env.filters.map_options = [(op) => { op.mapTypeControl = false; op.scaleControl = false; }];
const cm = cslmap(env, 2);
cm.mapType = "roadmap";
cm.show_home_marker = () => false;
env.ctx.avalon_cslmap = cm;
env.ctx.google.maps.Map = function (div, op) { env.maps.push({ div: div, options: JSON.parse(JSON.stringify(op)) }); return env.gmap; };
cm.gmap = null;
env.ctx.cslmap_build_map({ lat: 1, lng: 2 }, env.mapDiv);
const built = env.maps[0] || { options: {} };
check(env.maps.length === 1 && built.options.mapTypeControl === true && built.options.cameraControl === false &&
      built.options.zoomControl === true && built.options.streetViewControl === true && built.options.fullscreenControl === true,
      "cslmap_build_map(): the map is built with the controls, after the filter - Experience's mapTypeControl false is overridden");
check(built.options.scaleControl === false && built.options.zoom === 12 && built.options.minZoom === 1,
      "  ... and what the filter set otherwise, and SLP's own options, are kept");
check(cm.show_map_bubble === env.M.show && st(env).cm === cm, "  ... and avalon_map is attached: SLP's show_map_bubble() replaced");
check(((cm.infowindow.__l || {}).domready || []).length === 1 && ((cm.infowindow.__l || {}).close || []).length === 1 &&
      ((env.gmap.__l || {}).click || []).length === 1 && (env.doc.listeners.keydown || []).length === 1 &&
      (env.winL.resize || []).length === 1 && env.on.filter((x) => /\.avalonMap$/.test(x.args[0])).length === 2,
      "  ... with one domready, one close, one map click, one keydown, one window resize and the two card handlers");
});

/* -------------------------------------------------------------- opening */

section("opening", 14, () => {
console.log("");
console.log("  OPENING - SLP's show_map_bubble() replaced");
let env = boot();
click(env, 0);
const log = env.iwLog.slice();
check(same(log[0], ["setOptions", { ariaLabel: "Dealer & Sons 101" }]) && same(log[1], ["setContent", "Dealer &amp; Sons 101"]) &&
      same(log[2], ["open", { anchor: "m101", shouldFocus: false, map: true }]),
      "a click: ariaLabel the dealer's name as text, SLP's content, open() with shouldFocus: false - in that order");
const pub = (env.published || []).filter((p) => p[0] === "map_options");
check(pub.length === 1 && same(pub[0][1], { show_bubble: true }) && same(env.cm.options, { show_bubble: true }),
      "  ... SLP's map_options filter published with show_bubble, as SLP does, and kept on cslmap.options");
check(st(env).pinned === true && st(env).open === true && cur(env) === "101", "  ... chosen: kept open");
check(env.focusLog.length === 0, "  ... no focus before Google has put the content in");
env.dom();
const during = env.focusLog.length;
env.advance(0);
check(during === 0 && env.focusLog.length === 1 && env.focusLog[0][0] === "storelocatorlink" && same(env.focusLog[0][1], { preventScroll: true }) &&
      env.doc.activeElement.parentNode.id === "slp_bubble_website",
      "  ... then, once the event that put it in is done, focus on Contact Dealer, without scrolling the page");
env = boot({ nourl: true });
click(env, 1);
env.dom();
env.advance(0);
check(env.doc.activeElement.parentNode && env.doc.activeElement.parentNode.id === "slp_bubble_directions",
      "a dealer without Contact Dealer: focus on Get Directions");
env = boot({ nourl: true, nodirections: true });
click(env, 1);
env.dom();
env.advance(0);
check(env.focusLog.length === 0 && env.doc.activeElement === env.doc.body, "neither button: focus stays where it was");
env = boot();
over(env, 2);
env.dom();
env.advance(0);
check(same(env.iwLog[2], ["open", { anchor: "m103", shouldFocus: false, map: true }]) && st(env).pinned === false &&
      env.focusLog.length === 0 && cur(env) === "103",
      "a hover: opened, not chosen, and no focus at all");
click(env, 2);
const inClick = env.focusLog.length;
env.advance(0);
check(env.opens === 1 && st(env).pinned === true && inClick === 0 && env.focusLog.length === 1 && env.focusLog[0][0] === "storelocatorlink",
      "a click on the pin whose bubble a hover opened: not reopened - chosen, and focus moved as soon as the click is done, not during it");
env = boot();
over(env, 0);
env.dom();
over(env, 1);
click(env, 1);
env.advance(0);
check(env.focusLog.length === 0 && st(env).wantFocus === true,
      "a click on a bubble a hover has just opened, before Google has put its content in: the last dealer's buttons are not focused");
env.dom();
env.advance(0);
check(env.focusLog.length === 1 && env.doc.activeElement.parentNode.id === "slp_bubble_website" &&
      env.bubble.id === "slp_info_bubble_102" && env.bubble.contains(env.doc.activeElement),
      "  ... this dealer's Contact Dealer is, once it is there");
env = boot({ options: { hide_bubble: "1" } });
click(env, 0);
check(env.opens === 0 && st(env).open === false, "SLP's hide_bubble set: nothing opens");
env = boot();
const before = env.opens;
env.cm.show_map_bubble({ id: "999", name: "x" }, null);
env.cm.show_map_bubble({ id: "999", name: "x" }, {});
check(env.opens === before && st(env).open === false, "no marker, or a marker without its Google marker: nothing opens, no throw");
env = boot();
env.cm.show_map_bubble({ id: "555", name: "O&#039;Test &quot;Marine&quot;  &lt;x&gt;" }, { __gmarker: gm(env, "m555") });
check(same(env.iwLog[0], ["setOptions", { ariaLabel: "O'Test \"Marine\" <x>" }]) && cur(env) === "555",
      "a marker the search did not draw opens too; its name decoded, spaces folded, for the dialog's label");
});

/* --------------------------------------------------------------- phones */

section("phones", 8, () => {
console.log("");
console.log("  PHONES - the bubble's minimum width");
let env = boot({ phone: true, mapW: 360 });
click(env, 0);
check(same(env.iwLog[0], ["setOptions", { ariaLabel: "Dealer & Sons 101", minWidth: 336 }]),
      "767 px and under, a 360 px map: minWidth 336 - the map less 24 - set before open()");
env = boot({ phone: true, mapW: 600 });
click(env, 0);
check(env.iwLog[0][1].minWidth === 376, "a 600 px map: 376 at most, the theme's own width");
click(env, 1);
check(same(env.iwLog[3], ["setOptions", { ariaLabel: "Dealer & Sons 102" }]) && env.iwLog.filter((x) => x[0] === "close").length === 0,
      "the next bubble at the same width: minWidth not set again, nothing closed");
env.phone = false;
env.iwLog.length = 0;
click(env, 2);
check(same(env.iwLog.map((x) => x[0]), ["close", "setOptions", "setContent", "open"]) && env.iwLog[1][1].minWidth === 0,
      "the width changes while a bubble is open: close(), setOptions(), open(), as Google's reference says - back to 0 off the phone");
check(st(env).open === true && cur(env) === "103" && st(env).pinned === true,
      "  ... and that close() is not mistaken for the visitor's: the new bubble is open and chosen");
env = boot({ phone: true, mapW: 360 });
const pin = new El("div", { id: "pin-button" }, env);
env.doc.body.appendChild(pin);
env.doc.activeElement = pin;
click(env, 0);
env.dom();
env.advance(0);
env.phone = false;
click(env, 1);
env.dom();
env.advance(0);
check(pin.focusCalls.length === 0 && env.doc.activeElement.parentNode.id === "slp_bubble_website" && st(env).back === pin,
      "  ... nor is focus sent back to the pin in between: from the first bubble's Contact Dealer straight to the second's");
env = boot({ phone: true, mapW: 360 });
env.googleFocus = true;
const pinS = new El("div", { id: "pin-a" }, env);
env.doc.body.appendChild(pinS);
env.doc.activeElement = pinS;
click(env, 0);
env.dom();
env.advance(0);
env.mapDiv.clientWidth = 600;
click(env, 1);
env.dom();
env.advance(0);
check(env.googleFocusMoves === 1 && pinS.blurCalls === 1 && cur(env) === "102" && st(env).minWidth === 376 &&
      env.doc.activeElement.parentNode.id === "slp_bubble_website" && st(env).back === pinS,
      "  ... and where Google, closing the first, sends focus back to its pin: let go, onto the second's Contact Dealer - still bound back to the pin");
env = boot({ phone: true, mapW: 20 });
click(env, 0);
check(!("minWidth" in env.iwLog[0][1]), "a map too narrow to measure: no minimum");
});

/* --------------------------------------------------------------- resize */

section("resize", 21, () => {
console.log("");
console.log("  RESIZE - the open bubble's minimum width once the window stops resizing");
const resize = (env) => (env.winL.resize || []).forEach((fn) => fn({ type: "resize" }));
const inMap = (env, n) => env.mapDiv.all().indexOf(n) >= 0;
let env = boot({ phone: true, mapW: 360 });
const pin = new El("div", { id: "pin-button" }, env);
env.doc.body.appendChild(pin);
env.doc.activeElement = pin;
click(env, 0);
env.dom();
env.advance(0);
const cd = env.doc.activeElement;
env.iwLog.length = 0;
env.mapDiv.clientWidth = 600;
resize(env);
env.advance(100);
resize(env);
env.advance(199);
check(env.iwLog.length === 0, "a phone turned: nothing until the window has been still for 0.2 s - each resize starts the wait again");
env.advance(1);
check(same(env.iwLog, [["close"], ["setOptions", { minWidth: 376 }], ["open", { anchor: "m101", shouldFocus: false, map: true }]]),
      "  ... then the open bubble reopened wider, once: close(), setOptions() minWidth 376, open() at its pin, shouldFocus: false");
check(st(env).open === true && cur(env) === "101" && st(env).pinned === true && st(env).minWidth === 376 && env.pending() === 0,
      "  ... the same dealer, still chosen - that close() is not the visitor's - and nothing left pending");
env.dom();
env.advance(0);
const cd2 = env.doc.activeElement;
check(cd2 !== cd && !!cd2.parentNode && cd2.parentNode.id === "slp_bubble_website" && st(env).back === pin && pin.focusCalls.length === 0,
      "  ... and focus, which was in it, back on its Contact Dealer once it is in the page - and still bound back to the pin");
env.iwLog.length = 0;
resize(env);
env.advance(1000);
check(env.iwLog.length === 0 && env.doc.activeElement === cd2,
      "a resize that leaves the map's width alone - a phone's toolbar, its keyboard: nothing reopened, focus left alone");
env.phone = false;
env.mapDiv.clientWidth = 1011;
resize(env);
env.advance(200);
check(same(env.iwLog.map((x) => x[0]), ["close", "setOptions", "open"]) && env.iwLog[1][1].minWidth === 0 &&
      st(env).minWidth === 0 && st(env).open === true && cur(env) === "101",
      "wider than a phone: reopened with no minimum, still open");
env.iw.close();
check(st(env).open === false && cur(env) === null,
      "  ... and a close of Google's own after it - Esc in the bubble, its pin gone - is the visitor's: closed for the rules too");
env = boot({ mapW: 1011 });
over(env, 1);
env.dom();
env.advance(0);
env.iwLog.length = 0;
env.phone = true;
env.mapDiv.clientWidth = 700;
resize(env);
env.advance(200);
check(same(env.iwLog.map((x) => x[0]), ["close", "setOptions", "open"]) && env.iwLog[1][1].minWidth === 376 &&
      st(env).open === true && cur(env) === "102" && st(env).pinned === false,
      "a bubble a hover opened, the window narrowed to a phone's: reopened at 376, open and still not chosen");
env.dom();
env.advance(0);
check(env.doc.activeElement === env.doc.body && env.focusLog.length === 0,
      "  ... and focus, which was not in it, not moved into it");
out(env, 1);
env.advance(300);
check(st(env).open === false, "  ... and leaving its pin still closes it 0.3 s later");
env.iwLog.length = 0;
env.mapDiv.clientWidth = 360;
resize(env);
env.advance(200);
click(env, 0);
check(same(env.iwLog.map((x) => x[0]), ["setOptions", "setContent", "open"]) &&
      same(env.iwLog[0], ["setOptions", { ariaLabel: "Dealer & Sons 101", minWidth: 336 }]),
      "no bubble open: nothing reopened - the next bubble takes the new width as it opens");
env = boot({ phone: true, mapW: 360 });
const box = new El("input", { id: "addressInput" }, env);
env.doc.body.appendChild(box);
click(env, 0);
env.dom();
env.advance(0);
env.doc.activeElement = box;
env.mapDiv.clientWidth = 600;
resize(env);
env.advance(200);
env.dom();
env.advance(0);
check(st(env).minWidth === 376 && env.doc.activeElement === box && box.focusCalls.length === 0 && env.focusLog.length === 1,
      "a chosen bubble, focus moved on to the search box: reopened wider, focus left in the box");
env = boot({ phone: true, mapW: 360 });
const pinB = new El("div", { id: "pin-button" }, env);
env.doc.body.appendChild(pinB);
env.doc.activeElement = pinB;
click(env, 0);
env.dom();
env.advance(0);
env.doc.activeElement = pinB;
let calls = env.focusLog.length;
env.mapDiv.clientWidth = 600;
resize(env);
env.advance(200);
env.dom();
env.advance(0);
check(st(env).minWidth === 376 && env.doc.activeElement === pinB && env.focusLog.length === calls && pinB.blurCalls === undefined,
      "a chosen bubble, focus moved back to the pin it came from: reopened wider, focus left on the pin");
env = boot({ phone: true, mapW: 360 });
click(env, 0);
env.dom();
env.advance(0);
const cdA = env.doc.activeElement;
const field = new El("input", { id: "input_14_16_3" }, env);
env.doc.body.appendChild(field);
env.modalOpen = true;
env.doc.activeElement = field;
env.iwLog.length = 0;
env.mapDiv.clientWidth = 600;
resize(env);
env.advance(1000);
check(env.iwLog.length === 0 && inMap(env, cdA) && env.pending() === 1,
      "the phone turned under the Contact Dealer form: nothing reopened behind it - the link the form gives focus back to stays in the page - and a look pending");
env.modalOpen = false;
env.doc.activeElement = cdA;
env.advance(200);
check(same(env.iwLog.map((x) => x[0]), ["close", "setOptions", "open"]) && env.iwLog[1][1].minWidth === 376,
      "  ... the form closed and focus given back to that link: reopened wider within 0.2 s");
env.dom();
env.advance(0);
const cdA2 = env.doc.activeElement;
check(cdA2 !== cdA && !!cdA2.parentNode && cdA2.parentNode.id === "slp_bubble_website" && st(env).pinned === true && env.pending() === 0,
      "  ... and focus on the reopened bubble's Contact Dealer, nothing left pending");
env = boot({ phone: true, mapW: 360 });
env.googleFocus = true;
const pinG = new El("div", { id: "pin-button" }, env);
const boxG = new El("input", { id: "addressInput" }, env);
pinG.top = 900;
env.doc.body.appendChild(pinG);
env.doc.body.appendChild(boxG);
env.doc.activeElement = pinG;
click(env, 0);
env.dom();
env.advance(0);
env.doc.activeElement = boxG;
env.mapDiv.clientWidth = 600;
resize(env);
env.advance(200);
env.dom();
env.advance(0);
check(env.googleFocusMoves === 1 && env.doc.activeElement === boxG && same(boxG.focusCalls, [{ preventScroll: true }]) &&
      env.ctx.pageYOffset === 0 && same(env.scrolls, [[0, 0]]),
      "Google sending focus back to the pin as the bubble closes to reopen, scrolling to it: focus that was in the search box put back there, and the page where it was");
const inG = env.bubble.all().filter((n) => n.tagName === "A" && n.parentNode && n.parentNode.id === "slp_bubble_website")[0];
env.doc.activeElement = inG;
env.mapDiv.clientWidth = 360;
resize(env);
env.advance(200);
env.dom();
env.advance(0);
const cdG = env.doc.activeElement;
check(env.googleFocusMoves === 2 && boxG.blurCalls === 1 && cdG !== inG && !!cdG.parentNode && cdG.parentNode.id === "slp_bubble_website" &&
      st(env).back === pinG && st(env).minWidth === 336,
      "  ... and with focus in the bubble when it reopens: let go where Google put it - the search box - for the new Contact Dealer, still bound back to the pin");
env = boot({ phone: true, mapW: 360 });
env.googleFocus = true;
const boxS = new El("input", { id: "addressInput" }, env);
env.doc.body.appendChild(boxS);
env.doc.activeElement = boxS;
click(env, 0);
env.dom();
env.advance(0);
env.doc.activeElement = env.doc.body;
calls = env.focusLog.length;
env.mapDiv.clientWidth = 600;
resize(env);
env.advance(200);
env.dom();
env.advance(0);
check(env.googleFocusMoves === 1 && env.doc.activeElement === env.doc.body && boxS.blurCalls === 1 && env.focusLog.length === calls,
      "focus on nothing - a phone tapped off the bubble - when it reopens: Google's return of it to the search box let go, focus on nothing still");
env = boot({ phone: true, mapW: 360 });
click(env, 0);
env.dom();
env.advance(0);
env.modalOpen = true;
env.iwLog.length = 0;
resize(env);
env.advance(1000);
check(env.pending() === 0 && env.iwLog.length === 0,
      "the window resized under the Contact Dealer form with the map's width unchanged: nothing reopened, nothing left waiting");
env = boot({ phone: true, mapW: 360 });
click(env, 0);
env.dom();
env.advance(0);
env.iwLog.length = 0;
env.mapDiv.clientWidth = 0;
resize(env);
env.advance(1000);
check(env.iwLog.length === 0 && st(env).minWidth === 336 && st(env).open === true,
      "the map not laid out - 0 px wide: its bubble left as it was");
});

/* ---------------------------------------------------- rules 1, 3 and 5 */

section("hover rules", 13, () => {
console.log("");
console.log("  RULES 1, 3 AND 5 - closing what a hover opened");
let env = boot();
over(env, 0);
env.dom();
out(env, 0);
env.advance(299);
check(st(env).open === true, "rule 1: off the pin - still open at 299 ms");
env.advance(1);
check(st(env).open === false && env.iwLog.filter((x) => x[0] === "close").length === 1 && cur(env) === null,
      "  ... closed at 300 ms");
env = boot();
over(env, 0);
env.dom();
out(env, 0);
env.advance(150);
env.container.fire("mouseenter");
env.advance(1000);
check(st(env).open === true, "rule 1: off the pin and onto the bubble within 0.3 s: stays open");
env.container.fire("mouseleave");
env.advance(300);
check(st(env).open === false, "  ... and off the bubble: closed 0.3 s later");
env = boot();
over(env, 0);
env.dom();
out(env, 0);
env.advance(200);
over(env, 0);
env.advance(1000);
check(st(env).open === true && env.opens === 1, "back on the pin within 0.3 s: stays open, not reopened");
env = boot();
over(env, 0);
env.dom();
out(env, 0);
over(env, 1);
env.dom();
check(cur(env) === "102" && env.opens === 2, "from one pin straight to another: the other's bubble, by hover");
env.advance(1000);
check(st(env).open === true && cur(env) === "102", "  ... and the first one's pending close does not close the second");
env = boot();
card(env, "mouseenter", 1);
env.dom();
check(cur(env) === "102" && st(env).pinned === false && env.focusLog.length === 0, "rule 3: a card's hover opens its bubble, no focus");
card(env, "mouseleave", 1);
env.advance(300);
check(st(env).open === false, "  ... and leaving the card closes it 0.3 s later");
env = boot();
card(env, "mouseenter", 0);
env.dom();
card(env, "mouseleave", 0);
env.advance(100);
over(env, 0);
env.advance(1000);
check(st(env).open === true, "from the card onto its own pin within 0.3 s: stays open");
env = boot();
over(env, 0);
env.dom();
const link = env.bubble.children[0];
env.doc.activeElement = link;
out(env, 0);
env.advance(5000);
check(st(env).open === true, "rule 5: keyboard focus inside the bubble - it never closes on its own");
env.doc.activeElement = env.doc.body;
env.container.fire("mouseleave");
env.advance(300);
check(st(env).open === false, "  ... and once focus has left, leaving closes it again");
env = boot();
over(env, 0);
const c1 = env.dom();
env.trigger(env.iw, "domready");
env.iw.domPending = true;
env.dom();
check(Object.keys(c1.listeners).length === 4 && (c1.listeners.mouseenter || []).length === 1 && (c1.listeners.click || [])[0].cap === true &&
      (c1.listeners.focusin || []).length === 1 && env.container !== c1 && (env.container.listeners.mouseenter || []).length === 1,
      "each bubble container watched once, however often domready fires - mouseenter, mouseleave, click in the capture phase, focusin");
});

/* ---------------------------------------------------- rules 2 and 4 */

section("choice rules", 12, () => {
console.log("");
console.log("  RULES 2 AND 4 - what was chosen stays");
let env = boot();
click(env, 0);
env.dom();
env.advance(0);
env.doc.activeElement = env.doc.body;
const timers = env.timers.length;
out(env, 0);
env.advance(5000);
check(st(env).open === true && env.timers.length === timers,
      "rule 2: a clicked pin's bubble, focus gone elsewhere - leaving the pin closes nothing, no timer is even set");
env = boot();
over(env, 0);
env.dom();
out(env, 0);
env.container.fire("mouseenter");
env.container.fire("click");
env.container.fire("mouseleave");
env.advance(5000);
check(st(env).open === true && st(env).pinned === true,
      "rule 2: a click inside a hovered bubble - opening the hours, say - keeps it after the pointer leaves");
env = boot();
click(env, 0);
env.dom();
over(env, 1);
check(cur(env) === "101" && env.opens === 1 && g(env, 1).getZIndex() === 1000001,
      "a bubble kept open: hovering another pin opens nothing, only lights that pin");
out(env, 1);
check(g(env, 1).getZIndex() === undefined && g(env, 1).getIcon() === "https://example.test/wp-content/uploads/pin.png",
      "  ... and leaving it puts that pin back");
card(env, "mouseenter", 2);
check(cur(env) === "101" && env.opens === 1 && g(env, 2).getIcon() === HOVER, "  ... hovering another card: the same");
card(env, "mouseleave", 2);
click(env, 1);
env.dom();
check(cur(env) === "102" && st(env).pinned === true && g(env, 0).getIcon() !== HOVER && g(env, 1).getIcon() === HOVER,
      "another pin chosen: its bubble, kept; the first pin put back");
key(env, { key: "Escape" });
check(st(env).open === false && env.iwLog.filter((x) => x[0] === "close").length === 1, "Esc closes a kept bubble");
env = boot();
click(env, 0);
env.dom();
env.modalOpen = true;
key(env, { key: "Escape" });
key(env, { key: "Esc", defaultPrevented: true });
env.modalOpen = false;
key(env, { key: "Escape", defaultPrevented: true });
key(env, { key: "Enter" });
check(st(env).open === true, "Esc with the Contact Dealer form open, or already handled, or another key: the bubble stays");
key(env, { keyCode: 27 });
check(st(env).open === false, "  ... keyCode 27 alone is Esc too");
env = boot();
click(env, 0);
env.dom();
env.trigger(env.gmap, "click", {});
check(st(env).open === false, "rule 2: a click on the map closes a kept bubble");
env = boot();
over(env, 0);
click(env, 0);
env.dom();
out(env, 0);
env.advance(5000);
check(st(env).open === true && st(env).pinned === true && env.doc.activeElement.parentNode.id === "slp_bubble_website",
      "rule 4: a tap - a pointer-over, then a click - opens it, kept, focus on Contact Dealer");
env = boot();
card(env, "mouseenter", 0);
env.dom();
click(env, 0);
card(env, "mouseleave", 0);
env.advance(5000);
check(st(env).open === true && env.opens === 1, "a card clicked - SLP's own card handler - keeps the bubble its hover opened");
});

/* ------------------------------------------------------- the hover pin */

section("hover pin", 7, () => {
console.log("");
console.log("  THE HOVERED PIN");
let env = boot();
over(env, 0);
check(g(env, 0).getIcon() === HOVER && g(env, 0).getZIndex() === 1000001,
      "hovered: the hover icon, the site's root path resolved against the page, above every other pin");
check(env.images.length === 1 && env.images[0].src === HOVER, "the hover icon preloaded once per search");
env.dom();
out(env, 0);
check(g(env, 0).getIcon() === HOVER, "  ... still lit while its bubble is open");
env.advance(300);
check(g(env, 0).getIcon() === "https://example.test/wp-content/uploads/pin.png" && g(env, 0).getZIndex() === undefined,
      "  ... and put back as SLP drew it when the bubble closes");
env = boot({ noIcon: true });
over(env, 0);
check(g(env, 0).sets.filter((x) => x[0] === "icon").length === 0 && g(env, 0).getZIndex() === 1000001 && env.images.length === 0,
      "no hover icon set: the pin keeps its icon and is only raised; nothing preloaded");
env = boot({ options: { avalon_map_hover_icon: "https://cdn.example.test/h.png" } });
card(env, "mouseenter", 1);
check(g(env, 1).getIcon() === "https://cdn.example.test/h.png", "an absolute URL used as it is; a card's hover lights its pin");
env = boot();
over(env, 0);
over(env, 0);
out(env, 0);
out(env, 0);
env.advance(300);
check(g(env, 0).sets.filter((x) => x[0] === "icon").length === 2, "lit once and put back once, however often the events repeat");
});

/* ------------------------------------------------------ each search */

section("each search", 7, () => {
console.log("");
console.log("  EACH SEARCH - bind()");
let env = boot({ order: true });
over(env, 0);
check(cur(env) === "101" && env.iw.content.name === "Dealer &amp; Sons 101",
      "pins matched to SLP's results by location id, not by position");
env = makeEnv();
const cm = cslmap(env, 3);
env.list[1] = null;
cm.markers[2].__gmarker = null;
env.M.attach(cm);
env.M.bind(cm, env.list);
check(Object.keys(st(env).byId).sort().join() === "101" && ((cm.markers[1].__gmarker.__l || {}).mouseover || []).length === 0,
      "a pin without its result, or without its Google marker: skipped");
env = boot();
click(env, 0);
env.dom();
env.M.bind(env.cm, env.list);
check(st(env).open === false && st(env).pinned === false && env.iwLog.filter((x) => x[0] === "close").length === 1,
      "a new search closes the bubble that was open");
check(env.off.filter((x) => x.args[0] === ".avalonMap").length === 1 && env.on.filter((x) => /\.avalonMap$/.test(x.args[0])).length === 2,
      "the card handlers bound once, delegated, however many searches run");
env = makeEnv();
cslmap(env, 2);
env.ctx.avalon_cslmap = env.cm;
env.ctx.markers_list_natural = env.list;
const drop = env.on.filter((x) => x.target === "#map" && x.args[0] === "markers_dropped")[0];
drop.args[1]();
check(st(env).cm === env.cm && Object.keys(st(env).byId).length === 2 && env.zoom === 8,
      "SLP's markers_dropped: enable_on_mouse_hover_for_markers() binds avalon_map - and the zoom out by one is unchanged");
check(((g(env, 0).__l || {}).mouseover || []).length === 1 && ((g(env, 0).__l || {}).mouseout || []).length === 1,
      "  ... one mouseover and one mouseout per pin");
env = boot();
env.M.attach(env.cm);
check(((env.iw.__l || {}).domready || []).length === 1 && (env.doc.listeners.keydown || []).length === 1,
      "attach() twice with the same map: listeners once");
});

/* -------------------------------------------------------- focus back */

section("focus back", 5, () => {
console.log("");
console.log("  FOCUS - back where it came from");
let env = boot();
const pin = new El("div", { id: "pin-button" }, env);
env.doc.body.appendChild(pin);
env.doc.activeElement = pin;
click(env, 0);
env.dom();
env.advance(0);
check(env.doc.activeElement.parentNode.id === "slp_bubble_website" && st(env).back === pin,
      "a key on a focused pin: focus to Contact Dealer, and the pin remembered");
env.doc.activeElement = env.doc.body;
env.iw.isOpen = false;
env.trigger(env.iw, "close");
check(env.doc.activeElement === pin && st(env).open === false, "Google closes it - Esc inside it - and focus, lost, goes back to the pin");
env = boot();
const pin2 = new El("div", { id: "pin-button" }, env);
env.doc.body.appendChild(pin2);
env.doc.activeElement = pin2;
click(env, 0);
env.dom();
env.advance(0);
const input = new El("input", { id: "addressInput" }, env);
env.doc.body.appendChild(input);
env.doc.activeElement = input;
key(env, { key: "Escape" });
check(env.doc.activeElement === input, "focus the visitor has put somewhere else since stays there");
env = boot();
click(env, 0);
env.dom();
env.iw.isOpen = true;
env.trigger(env.iw, "close");
check(st(env).open === true && cur(env) === "101", "a close event while Google says the window is open - a late one - is ignored");
env = boot();
click(env, 0);
env.dom();
env.advance(0);
env.iw.isOpen = false;
env.doc.activeElement = env.doc.body;
env.trigger(env.iw, "close");
check(st(env).open === false && env.doc.activeElement === env.doc.body && st(env).back === null,
      "closed when focus came from nowhere in particular - a pointer: nothing to give back");
});

/* ------------------------------------------------------ focus guards */

section("focus guards", 14, () => {
console.log("");
console.log("  FOCUS - left where the visitor, or the Contact Dealer form, has it");
let env = boot();
click(env, 0);
env.modalOpen = true;
env.dom();
env.advance(0);
check(env.focusLog.length === 0 && env.doc.activeElement === env.doc.body && st(env).wantFocus === false && st(env).open === true,
      "the Contact Dealer form open by the time the bubble is there: focus is not pulled into the bubble behind it");
/* As the page runs it: the card's hover has opened the bubble; a click on
   the card's own Contact Dealer button focuses it, reaches SLP's handler on
   the card - show() - and only then main.js's, on the document, which opens
   the form. */
env = boot();
card(env, "mouseenter", 0);
env.dom();
const cd = new El("a", { id: "card-contact" }, env);
env.doc.body.appendChild(cd);
env.doc.activeElement = cd;
click(env, 0);
env.modalOpen = true;
env.advance(0);
check(env.focusLog.length === 0 && env.doc.activeElement === cd && st(env).pinned === true && st(env).back === null,
      "a card's own Contact Dealer button, its bubble already open from the hover: kept, and focus left for the form main.js opens on the same click");
env = boot();
click(env, 0);
const input = new El("input", { id: "addressInput" }, env);
env.doc.body.appendChild(input);
env.doc.activeElement = input;
env.dom();
env.advance(0);
check(env.focusLog.length === 0 && env.doc.activeElement === input && st(env).back === null,
      "focus moved on - to the search box - between the click and Google putting the content in: it stays there");
env = boot();
click(env, 0);
env.dom();
const mk = new El("div", { id: "marker-button" }, env);
env.mapDiv.appendChild(mk);
env.doc.activeElement = mk;
env.advance(0);
check(env.doc.activeElement.parentNode.id === "slp_bubble_website" && st(env).back === mk,
      "focus on the map - a pin, the map itself - is not somewhere else: moved in, and that is where it goes back to");
env = boot();
const link = new El("a", { id: "card-link" }, env);
env.doc.body.appendChild(link);
env.doc.activeElement = link;
click(env, 0);
env.dom();
env.advance(0);
check(env.doc.activeElement.parentNode.id === "slp_bubble_website" && st(env).back === link && st(env).from === link,
      "focus still where the choice left it: moved in as before, and that is where it goes back to");
key(env, { key: "Escape" });
check(st(env).from === null && st(env).open === false && env.doc.activeElement === link,
      "  ... and closed: focus back there, nothing of the choice kept for the next bubble");
env = boot();
over(env, 0);
env.dom();
env.container.fire("focusin");
over(env, 1);
out(env, 0);
out(env, 1);
env.advance(5000);
check(cur(env) === "101" && st(env).open === true && st(env).pinned === true && env.opens === 1,
      "rule 5: focus moving into a bubble a hover opened keeps it - another pin's hover opens nothing, leaving closes nothing");
env = boot();
over(env, 0);
env.dom();
const pinEl = new El("div", { id: "pin-button" }, env);
env.mapDiv.appendChild(pinEl);
env.container.fire("focusin", { type: "focusin", relatedTarget: env.doc.body });
env.container.fire("focusin", { type: "focusin", relatedTarget: pinEl });
env.container.fire("focusin", { type: "focusin", relatedTarget: env.bubble.children[0] });
env.doc.activeElement = env.bubble.children[0];
key(env, { key: "Escape" });
check(st(env).open === false && env.doc.activeElement === pinEl,
      "Tab from a pin into a bubble a hover opened, then Esc: focus back on that pin - not the page, not a link in the bubble");
env = boot();
click(env, 0);
env.dom();
env.advance(0);
const exits = [];
env.doc.fullscreenElement = env.mapDiv;
env.doc.exitFullscreen = () => { exits.push("std"); return { then: (ok, bad) => { bad(new Error("refused")); } }; };
const web = env.bubble.children.filter((n) => n.id === "slp_bubble_website")[0];
const dir = env.bubble.children.filter((n) => n.id === "slp_bubble_directions")[0];
const other = new El("a", { "class": "website" }, env);
web.appendChild(other);
env.container.fire("click", { type: "click", target: dir.children[0] });
env.container.fire("click", { type: "click", target: web });
env.container.fire("click", { type: "click", target: other });
check(exits.length === 0, "full screen: a click on Get Directions, beside the Contact Dealer link, or on another link there, leaves it");
const inner = new El("i", {}, env);
web.children[0].appendChild(inner);
env.container.fire("click", { type: "click", target: inner });
check(same(exits, ["std"]), "full screen: a click on Contact Dealer - on what is inside the link too - leaves full screen first");
check(st(env).open === true && st(env).pinned === true, "  ... the bubble kept, and a refusal to leave is swallowed, no throw");
env.doc.fullscreenElement = null;
env.container.fire("click", { type: "click", target: web.children[0] });
check(exits.length === 1, "not full screen: Contact Dealer leaves nothing");
env.doc.exitFullscreen = undefined;
env.doc.webkitFullscreenElement = env.mapDiv;
env.doc.webkitExitFullscreen = () => { exits.push("webkit"); };
env.container.fire("click", { type: "click", target: web.children[0] });
check(same(exits, ["std", "webkit"]), "Safari's prefixed full screen: left the same way");
env.container.fire("click", { type: "click" });
check(exits.length === 2 && st(env).open === true, "full screen, a click with no target: nothing left, nothing thrown");
});

/* ----------------------------------------------------- the chosen card */

section("chosen card", 10, () => {
console.log("");
console.log("  THE CHOSEN CARD (Part 4d) - .active on the open bubble's card, and no other");
/* The results as SLP draws them: one card per dealer, in #map_sidebar. */
const cards = (env) => env.cm.markers.map((m) => {
  const c = new El("div", { "class": "results_wrapper", id: "slp_results_wrapper_" + m.__location_id }, env);
  env.sidebar.appendChild(c);
  return c;
});
const marked = (env) => env.sidebar.all().filter((n) => /(^| )active( |$)/.test(n.className)).map((n) => n.id.replace("slp_results_wrapper_", ""));
let env = boot();
let cs = cards(env);
cs[0].className = "results_wrapper keep-me";
cs[2].className = "results_wrapper active";   /* main.js marked another card on an earlier click */
click(env, 0);
check(same(marked(env), ["101"]) && cs[0].className === "results_wrapper keep-me active" && cs[2].className === "results_wrapper",
      "a click on a pin: its dealer's card marked .active, the card marked before cleared, every other class kept");
click(env, 1);
check(same(marked(env), ["102"]), "another pin chosen: the mark moves with it");
env = boot();
cs = cards(env);
over(env, 2);
env.dom();
check(same(marked(env), []) && cur(env) === "103", "a hover opens the bubble and marks nothing");
env.container.fire("click", { type: "click", target: env.bubble });
check(same(marked(env), ["103"]) && st(env).pinned === true, "a click inside the hovered bubble chooses it: its card marked");
env = boot();
cs = cards(env);
over(env, 1);
env.dom();
env.container.fire("focusin", { type: "focusin", relatedTarget: env.doc.body });
check(same(marked(env), ["102"]) && st(env).pinned === true, "focus moving into a hovered bubble chooses it too: its card marked");
key(env, { key: "Escape" });
check(same(marked(env), []) && st(env).open === false, "Esc closes it: no card marked");
click(env, 0);
env.trigger(env.gmap, "click");
const afterMap = marked(env);
click(env, 1);
env.iw.close();
check(same(afterMap, []) && same(marked(env), []) && st(env).open === false,
      "a click on the map, and Google's own close of the bubble: no card marked");
click(env, 2);
env.M.bind(env.cm, env.list);
check(same(marked(env), []), "a new search: the mark goes with the old bubble");
env = boot({ phone: true, mapW: 360 });
cs = cards(env);
click(env, 0);
env.dom();
env.advance(0);
env.mapDiv.clientWidth = 600;
(env.winL.resize || []).forEach((fn) => fn({ type: "resize" }));
env.advance(200);
check(same(marked(env), ["101"]) && st(env).open === true && env.iwLog.filter((x) => x[0] === "close").length === 1,
      "a phone turned: the bubble closed and reopened wider, and its card stays marked - that close is not the visitor's");
env = boot();
let threw = false;
try {
  click(env, 0);
  env.dom();
  env.container.fire("click", { type: "click", target: env.bubble });
} catch (e) {
  threw = true;
}
check(!threw && same(marked(env), []), "a dealer with no card in the list: nothing marked, nothing thrown");
});

/* -------------------------------------------------------------- the fade */

section("fade", 8, () => {
console.log("");
console.log("  THE FADE (Part 4d) - .is-more on the bubble's body while there is more below");
const more = (env) => /(^| )is-more( |$)/.test(env.info.className);
let env = boot();
env.body = { scroll: 333, client: 250 };
click(env, 0);
env.dom();
check(more(env) === true && env.info.className === "sl_popup_contact_info is-more",
      "a body with more below than it shows: marked as the bubble opens, its own class kept");
env.info.scrollTop = 83;
env.info.fire("scroll", { type: "scroll" });
check(more(env) === false, "scrolled to the end: the fade goes");
env.info.scrollTop = 82.5;
env.info.fire("scroll", { type: "scroll" });
check(more(env) === false, "  ... and stays gone within a pixel of the end - a phone's fractional scroll");
env.info.scrollTop = 20;
env.info.fire("scroll", { type: "scroll" });
check(more(env) === true, "scrolled back up: the fade again");
env.info.scrollTop = 0;
env.info.scrollHeight = 250;
env.info.fire("toggle", { type: "toggle" });
check(more(env) === false && (env.info.listeners.toggle || []).length === 1 && env.info.listeners.toggle[0].cap === true,
      "the week shut, the body fits again: the fade goes - toggle heard in the capture phase, since it does not bubble");
env.trigger(env.iw, "domready");
check((env.info.listeners.scroll || []).length === 1 && (env.info.listeners.toggle || []).length === 1,
      "a second domready for the same content: its listeners not added twice");
env = boot();
env.body = { scroll: 200, client: 200 };
click(env, 0);
env.dom();
const before = more(env);
env.info.scrollHeight = 400;
env.advance(0);
check(before === false && more(env) === true, "a body that fits as it opens, longer once laid out: looked at again when the opening is done");
env.info.scrollHeight = 200;
env.mapDiv.clientWidth = 1011;
(env.winL.resize || []).forEach((fn) => fn({ type: "resize" }));
env.advance(200);
let threw = false;
try {
  env.M.close();
  env.M.more();
  env.M.ring(null);
} catch (e) {
  threw = true;
}
check(more(env) === false && !threw, "after a resize the fade is looked at again; with no bubble, more() and ring() do nothing and throw nothing");
});

console.log("");
console.log("  " + pass + " passed, " + fail + " failed, " + (pass + fail) + " total");
console.log("");
process.exit(fail ? 1 : 0);
