/**
 * suite-map.js - validates slp_avalon/assets/js/slp_avalon.js, v0.0.27 Part 4c.
 * r2, v0.0.27 Part 4d: the chosen card, the bubble's fade, the placeholder;
 * fa() gone with the icons.
 * r3, v0.0.27 Part 4e: no bubble on a phone - the choice goes to the card;
 * the card brought into view; the bubble's width held; the fade and the
 * phone's minimum width gone.
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
 *                the map; the dialog named for the dealer. Part 4c's
 *                minimum width on a phone went with Part 4e
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
 *   phones       Part 4e: 767 px wide or less, or 500 px high or less,
 *                and not full screen - a pin opens no bubble: it is lit,
 *                its dealer's card marked, brought into view, flashed and
 *                given focus; a card chosen there is marked and its pin
 *                lit, the map moved only when that pin is out of sight;
 *                Esc, a tap on the map, another choice or a search ends it
 *   the card     Part 4e: a dealer chosen on the map has its card brought
 *   in view      where it can be seen - beside the map the results scroll,
 *                with room made under the last cards; under the map the
 *                card moves to the top of the list and back; on a phone
 *                the page scrolls just far enough; where a bubble opens it
 *                never does; a dealer chosen on its card is left there
 *   resized()    Part 4e: a window narrowed to a phone's loses the bubble
 *                and keeps the choice; one widened gets the bubble; full
 *                screen coming or going is the same; not under the Contact
 *                Dealer form; Google's focus and scroll on that close undone
 *   hold()       Part 4e: the week measured open once as a bubble arrives,
 *                and that width kept as the bubble's least
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
 * r3. Part 4e's 19 edits, all inside the block, are reversed before Part
 * 4d's, and the block must then be Part 4d's byte for byte - 68201cb5,
 * 26,839 bytes - so r2's evidence carries forward for all that Part 4e did
 * not touch. The sections on the phone's minimum width, on reopening after
 * a resize and on the fade described what Part 4e took out, and went with
 * it; their place is taken by phones, the card in view, resize and the
 * bubble's width. The fake page gained arithmetic: a list of cards in a
 * box that scrolls, beside the map or under it, so that a check can ask
 * where a card ended up and not only what was called.
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
const BLOCK_PIN = { md5: "aa47533201ca436601f1a438be6b8d71", len: 43447 };
const P4D_BLOCK = { md5: "68201cb5f477826722607e97d6ffe4d4", len: 26839 };
const P4C_BLOCK = { md5: "040a111a23ad94957ea76d1019a9aeb6", len: 25525 };
/* r3. Part 4e's edits, [what it is, what Part 4d had, what Part 4e wrote],
   in the order build-v027-part4e.py makes them; all inside the block.
   Written LF, read CRLF. */
const P4E_EDITS = [
  ["slp_avalon.js: avalon_map's header - no bubble on a phone, so no width to set there",
   "   *   phones  at Elementor's mobile breakpoint the bubble is at least the\n" +
   "   *           map's width less 24 px, and never more than 376 px, the\n" +
   "   *           theme's own width. Google reads minWidth only as a bubble\n" +
   "   *           opens, so a change is close(), setOptions(), open(), as its\n" +
   "   *           reference says - on the next bubble, and on the open one\n" +
   "   *           0.2 s after the window stops resizing (a phone turned):\n" +
   "   *           the same dealer, chosen or not as before, and focus, if it\n" +
   "   *           was in the bubble, back on its Contact Dealer button. Not\n" +
   "   *           while the Contact Dealer form is open over the page: once it\n" +
   "   *           has closed. 767 px is Elementor's default and Aura's; Part\n" +
   "   *           4's hours fold follows a site that moves it, this does not -\n" +
   "   *           check before Tahoe or Avalon take Part 4c.\n",
   "   *   phones  none there at all, from Part 4e: PHONES, below. Part 4c\n" +
   "   *           widened the bubble on a phone - minWidth, the map's width\n" +
   "   *           less 24 px - and reopened it when the phone was turned;\n" +
   "   *           both went with the bubble.\n"],
  ["slp_avalon.js: avalon_map's header - phones, the card in view, the bubble's width; the fade gone",
   "   * THE FADE (Part 4d). On a phone the bubble's body scrolls; while there\n" +
   "   * is more below, avalon-hours.css fades its foot (.is-more on\n" +
   "   * .sl_popup_contact_info): looked at as the bubble opens, as it scrolls,\n" +
   "   * as its week opens or shuts, and after a resize.\n" +
   "   *\n",
   "   * PHONES (Part 4e). On a phone - 767 px wide or less, or 500 px high or\n" +
   "   * less: upright, or sideways and short - a pin opens no bubble (the\n" +
   "   * owner, 2026-10-07): it covered most of the map to repeat the card\n" +
   "   * beside or under it, and its week needed a scroller of its own. A pin\n" +
   "   * chosen there is lit, and its dealer's card is marked, brought into\n" +
   "   * view, flashed and given focus; a card chosen there is marked and its\n" +
   "   * pin lit, and the map moves only when that pin is out of sight. Esc, a\n" +
   "   * tap on the map, another choice or a new search ends it, as they close\n" +
   "   * a bubble. A hover - a mouse on a narrow window - lights the pin and\n" +
   "   * nothing more. A window narrowed to a phone's with a bubble open loses\n" +
   "   * the bubble and keeps the choice; one widened with a dealer chosen\n" +
   "   * gets that dealer's bubble. A map shown full screen is not a phone's,\n" +
   "   * whatever the screen: no card can be seen behind it, so its pins open\n" +
   "   * bubbles, and going in or out of full screen is taken as the window\n" +
   "   * widening or narrowing. 767 px is Elementor's default and Aura's;\n" +
   "   * Part 4's hours fold follows a site that moves it, this does not -\n" +
   "   * check before Tahoe or Avalon take it.\n" +
   "   *\n" +
   "   * THE CARD IN VIEW (Part 4e). A dealer chosen on the map - on a phone\n" +
   "   * or not, but not on its own card, which is under the pointer already -\n" +
   "   * has its card brought where it can be seen. Beside the map, the\n" +
   "   * results scroll until the card is at the top of what shows of them,\n" +
   "   * with room made under the last cards where they could not otherwise\n" +
   "   * get there. Under the map - a phone upright - the card moves to the\n" +
   "   * top of the list, and back to its place when another dealer is chosen\n" +
   "   * there or none is; on a phone the page then scrolls just far enough to\n" +
   "   * show it, keeping the map's top in view where both fit. Which of the\n" +
   "   * two it is, is read from where the list lies, not from a width. Where\n" +
   "   * a bubble opens, the page itself is never scrolled.\n" +
   "   *\n" +
   "   * THE BUBBLE'S WIDTH (Part 4e). The bubble is as wide as its content\n" +
   "   * (the theme), and a week that is shut is not content: opening it could\n" +
   "   * widen the bubble under the pointer. So the week is measured open,\n" +
   "   * once, as the bubble arrives, and that width kept as the bubble's\n" +
   "   * least. Part 4d's fade went with the phone's bubble: nothing of ours\n" +
   "   * scrolls in a bubble now.\n" +
   "   *\n"],
  ["slp_avalon.js: the phone is upright or short; no widths; what a choice without a bubble needs kept",
   "    var CLOSE_MS = 300;\n" +
   "    var RESIZE_MS = 200;\n" +
   "    var PHONE = \"(max-width: 767px)\";\n" +
   "    var WIDEST = 376;\n" +
   "    var MARGIN = 24;\n" +
   "\n" +
   "    var st = {\n" +
   "      cm: null,           //SLP's map object, cslmap\n" +
   "      byId: {},           //location id -> { id, marker, info, lit }\n" +
   "      current: null,      //the entry whose bubble is open\n" +
   "      open: false,\n" +
   "      pinned: false,      //chosen, by a click, a tap or a key\n" +
   "      next: null,         //\"hover\" for the next show() only\n" +
   "      timer: 0,           //the pending close\n" +
   "      rs: 0,              //the pending look at the width, after a resize\n" +
   "      overMarker: null,   //the location id of the pin under the pointer\n" +
   "      overCard: null,     //the location id of the card under the pointer\n" +
   "      overBubble: false,\n" +
   "      minWidth: 0,        //the minWidth the InfoWindow was last given\n" +
   "      quiet: false,       //closing only to reopen at another width\n",
   "    var CLOSE_MS = 300;\n" +
   "    var RESIZE_MS = 200;\n" +
   "    var FLASH_MS = 1100;\n" +
   "    var GAP = 8;\n" +
   "    var PHONE = \"(max-width: 767px), (max-height: 500px)\";\n" +
   "\n" +
   "    var st = {\n" +
   "      cm: null,           //SLP's map object, cslmap\n" +
   "      byId: {},           //location id -> { id, marker, info, lit }\n" +
   "      current: null,      //the entry whose bubble is open; on a phone, the dealer chosen\n" +
   "      open: false,        //a bubble is open\n" +
   "      pinned: false,      //chosen, by a click, a tap or a key\n" +
   "      next: null,         //\"hover\" for the next show() only\n" +
   "      timer: 0,           //the pending close\n" +
   "      rs: 0,              //the pending look at the window, after a resize\n" +
   "      overMarker: null,   //the location id of the pin under the pointer\n" +
   "      overCard: null,     //the location id of the card under the pointer\n" +
   "      overBubble: false,\n" +
   "      via: \"\",            //\"card\" while a click on a result card is under way\n" +
   "      moved: null,        //{ el, next }: the card moved to the top of a list under the map\n" +
   "      pad: null,          //{ el, card }: the results padded so that card can reach their top\n" +
   "      fl: 0,              //the end of the flash\n" +
   "      sent: null,         //what to_card() gave focus to\n" +
   "      quiet: false,       //closing a bubble the visitor did not ask to close\n"],
  ["slp_avalon.js: more() gives way to the phone, the card in view, the flash, focus on the card",
   "    //Part 4d. The fade at the foot of the bubble's body while there is\n" +
   "    //more below it; none at the end, none where nothing scrolls.\n" +
   "    function more() {\n" +
   "      var b = bubble();\n" +
   "      var s = b ? b.querySelector(\".sl_popup_contact_info\") : null;\n" +
   "      if (s) {\n" +
   "        cls(s, \"is-more\", s.scrollHeight - s.scrollTop - s.clientHeight > 1);\n" +
   "      }\n" +
   "    }\n" +
   "\n",
   "    //Part 4e. A phone: upright, or sideways and short. No bubble there -\n" +
   "    //but for a map shown full screen, where no card can be seen.\n" +
   "    function phone() {\n" +
   "      var d = document;\n" +
   "      return !(d.fullscreenElement || d.webkitFullscreenElement) &&\n" +
   "             !!(window.matchMedia && window.matchMedia(PHONE).matches);\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. A dealer's card in the results, or null.\n" +
   "    function card_of(e) {\n" +
   "      return e ? document.getElementById(\"slp_results_wrapper_\" + e.id) : null;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. On a result card.\n" +
   "    function on_card(el) {\n" +
   "      for (var n = el; n && n.nodeType === 1; n = n.parentNode) {\n" +
   "        if (has_class(n, \"results_wrapper\")) {\n" +
   "          return true;\n" +
   "        }\n" +
   "      }\n" +
   "      return false;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. What scrolls a card: the nearest box above it that scrolls up\n" +
   "    //and down - the theme's results box on Aura - or null where only the\n" +
   "    //page does.\n" +
   "    function scroller(c) {\n" +
   "      if (typeof window.getComputedStyle !== \"function\") {\n" +
   "        return null;\n" +
   "      }\n" +
   "      for (var n = c.parentNode; n && n.nodeType === 1 && n !== document.body && n !== document.documentElement; n = n.parentNode) {\n" +
   "        var o = window.getComputedStyle(n).overflowY;\n" +
   "        if (o === \"auto\" || o === \"scroll\") {\n" +
   "          return n;\n" +
   "        }\n" +
   "      }\n" +
   "      return null;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. Whether the results lie under the map - a phone upright - and\n" +
   "    //not beside it: read from the page, so that the theme's own breakpoint\n" +
   "    //decides. Their box, not the list in it, which moves as it scrolls.\n" +
   "    function stacked(box) {\n" +
   "      var g = st.cm && st.cm.gmap;\n" +
   "      var m = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n" +
   "      if (!m || !box || !m.getBoundingClientRect || !box.getBoundingClientRect) {\n" +
   "        return false;\n" +
   "      }\n" +
   "      return box.getBoundingClientRect().top >= m.getBoundingClientRect().bottom - 1;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. How much of the window's top a fixed or sticky header covers:\n" +
   "    //nothing on Aura, whose header scrolls away; an admin bar's 32 px.\n" +
   "    function inset() {\n" +
   "      var w = window.innerWidth || 0;\n" +
   "      var h = window.innerHeight || 0;\n" +
   "      if (typeof document.elementFromPoint !== \"function\" || typeof window.getComputedStyle !== \"function\") {\n" +
   "        return 0;\n" +
   "      }\n" +
   "      for (var n = document.elementFromPoint(Math.floor(w / 2), 1); n && n.nodeType === 1 && n !== document.body && n !== document.documentElement; n = n.parentNode) {\n" +
   "        var p = window.getComputedStyle(n).position;\n" +
   "        if (p === \"fixed\" || p === \"sticky\") {\n" +
   "          var b = n.getBoundingClientRect().bottom;\n" +
   "          return b > 0 && b < h / 2 ? b : 0;\n" +
   "        }\n" +
   "      }\n" +
   "      return 0;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. A box, or the page, scrolled down by dy px - up, when dy is\n" +
   "    //less than nothing - smoothly, unless the visitor has asked for less\n" +
   "    //motion: then at once, whatever the page's own scroll-behavior says\n" +
   "    //(Aura's is smooth).\n" +
   "    function scroll_by(el, dy) {\n" +
   "      var calm = !!(window.matchMedia && window.matchMedia(\"(prefers-reduced-motion: reduce)\").matches);\n" +
   "      if (!dy) {\n" +
   "        return;\n" +
   "      }\n" +
   "      try {\n" +
   "        (el || window).scrollBy({ top: dy, left: 0, behavior: calm ? \"instant\" : \"smooth\" });\n" +
   "      } catch (x) {\n" +
   "        //A browser that takes no options here, or not these: at once.\n" +
   "        if (el) {\n" +
   "          el.scrollTop += dy;\n" +
   "        } else {\n" +
   "          window.scrollBy(0, dy);\n" +
   "        }\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. The room made under the results taken away again.\n" +
   "    function unpad() {\n" +
   "      var p = st.pad;\n" +
   "      st.pad = null;\n" +
   "      if (p && p.el && p.el.style) {\n" +
   "        p.el.style.paddingBottom = \"\";\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. A card moved within its list, before another or to the end.\n" +
   "    //Moving takes focus from whatever in the card had it: given back.\n" +
   "    function move(c, p, before) {\n" +
   "      var a = document.activeElement;\n" +
   "      var had = !!a && a !== document.body && typeof c.contains === \"function\" && c.contains(a);\n" +
   "      if (before) {\n" +
   "        p.insertBefore(c, before);\n" +
   "      } else {\n" +
   "        p.appendChild(c);\n" +
   "      }\n" +
   "      if (had && document.activeElement !== a) {\n" +
   "        try {\n" +
   "          a.focus({ preventScroll: true });\n" +
   "        } catch (x) {\n" +
   "          //Refused: focus stays where moving the card left it.\n" +
   "        }\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. A card moved to the top of its list put back: before the\n" +
   "    //card it stood before, or last. A list SLP has since drawn again has\n" +
   "    //nothing to put back.\n" +
   "    function put_back() {\n" +
   "      var m = st.moved;\n" +
   "      st.moved = null;\n" +
   "      var p = m && m.el ? m.el.parentNode : null;\n" +
   "      if (!p) {\n" +
   "        return;\n" +
   "      }\n" +
   "      if (m.next && m.next.parentNode === p) {\n" +
   "        move(m.el, p, m.next);\n" +
   "      } else if (!m.next) {\n" +
   "        move(m.el, p, null);\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. What place() did for another dealer's card undone; for this\n" +
   "    //one, left as it is.\n" +
   "    function unplace(c) {\n" +
   "      if (st.moved && st.moved.el !== c) {\n" +
   "        put_back();\n" +
   "      }\n" +
   "      if (st.pad && st.pad.card !== c) {\n" +
   "        unpad();\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. Beside the map: the results scrolled until the card is at the\n" +
   "    //top of what shows of them - their box may reach past the window's\n" +
   "    //foot, or start above its top. A card already in full view is left\n" +
   "    //where it is. Near the end of the list the box cannot scroll that far:\n" +
   "    //room is made under the last card for as long as this one is chosen.\n" +
   "    //On a phone (far) the page scrolls as well, just far enough to show\n" +
   "    //the card whole and never taking the box's top out of the window: a\n" +
   "    //phone held sideways shows less than one card's height of a box that\n" +
   "    //starts under the search form. Results with no box of their own\n" +
   "    //scroll with the page, and only there. Where a bubble opens the page\n" +
   "    //is left alone.\n" +
   "    function box_to(c, s, far) {\n" +
   "      var r = c.getBoundingClientRect();\n" +
   "      var top = inset();\n" +
   "      var foot = window.innerHeight || document.documentElement.clientHeight || 0;\n" +
   "      var lo = top;\n" +
   "      var hi = foot;\n" +
   "      var b = s ? s.getBoundingClientRect() : null;\n" +
   "      var edge = lo + GAP;\n" +
   "      if (b) {\n" +
   "        //The box's own top, where that shows - or nothing of the box does.\n" +
   "        edge = b.top >= lo || b.bottom <= lo || b.top >= hi ? b.top : edge;\n" +
   "        lo = Math.max(lo, b.top);\n" +
   "        hi = Math.min(hi, b.bottom);\n" +
   "      }\n" +
   "      if (r.top >= lo - 1 && r.bottom <= hi + 1) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var dy = Math.round(r.top - edge);\n" +
   "      if (!s) {\n" +
   "        if (far) {\n" +
   "          scroll_by(null, dy);\n" +
   "        }\n" +
   "        return;\n" +
   "      }\n" +
   "      var max = s.scrollHeight - s.clientHeight - s.scrollTop;\n" +
   "      var side = document.getElementById(\"map_sidebar\");\n" +
   "      if (dy > max && side && side.style && typeof window.getComputedStyle === \"function\") {\n" +
   "        side.style.paddingBottom = Math.ceil((parseFloat(window.getComputedStyle(side).paddingBottom) || 0) + dy - max) + \"px\";\n" +
   "        st.pad = { el: side, card: c };\n" +
   "      }\n" +
   "      scroll_by(s, dy);\n" +
   "      if (far) {\n" +
   "        scroll_by(null, Math.max(0, Math.round(Math.min(edge + r.bottom - r.top - (foot - GAP), b.top - top - GAP))));\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. Under the map, on a phone: the page scrolled just far enough\n" +
   "    //to show the card - whole, where that leaves the map's top in view;\n" +
   "    //otherwise the best part of it, 45% of the window or 140 px, and the\n" +
   "    //map's top let go. Never scrolled back up.\n" +
   "    function page_to(c) {\n" +
   "      var g = st.cm && st.cm.gmap;\n" +
   "      var m = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n" +
   "      var vh = window.innerHeight || document.documentElement.clientHeight || 0;\n" +
   "      if (!m || !m.getBoundingClientRect || !vh) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var r = c.getBoundingClientRect();\n" +
   "      var all = r.bottom - (vh - GAP);\n" +
   "      var some = r.top + Math.min(r.bottom - r.top, Math.max(140, vh * 0.45)) - (vh - GAP);\n" +
   "      var room = m.getBoundingClientRect().top - inset() - GAP;\n" +
   "      var dy = Math.round(Math.max(some, Math.min(all, room)));\n" +
   "      if (dy > 0) {\n" +
   "        scroll_by(null, dy);\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. THE CARD IN VIEW, in the header above. far: on a phone,\n" +
   "    //where the page may scroll as well.\n" +
   "    function place(e, far) {\n" +
   "      var c = card_of(e);\n" +
   "      var p = c ? c.parentNode : null;\n" +
   "      unplace(c);\n" +
   "      if (!c || !p || !c.getBoundingClientRect) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var s = scroller(c);\n" +
   "      if (!stacked(s || p)) {\n" +
   "        if (st.moved) {\n" +
   "          put_back();\n" +
   "        }\n" +
   "        box_to(c, s, far);\n" +
   "        return;\n" +
   "      }\n" +
   "      unpad();\n" +
   "      var first = typeof p.querySelector === \"function\" ? p.querySelector(\".results_wrapper\") : null;\n" +
   "      if (first && first !== c) {\n" +
   "        st.moved = { el: c, next: c.nextSibling };\n" +
   "        move(c, p, first);\n" +
   "      }\n" +
   "      if (s) {\n" +
   "        s.scrollTop = 0;\n" +
   "      }\n" +
   "      if (far) {\n" +
   "        page_to(c);\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. The card flashed, so that the eye finds it: avalon-hours.css\n" +
   "    //draws .avalon-flash, which comes off again when it is done - or at\n" +
   "    //once, with no dealer, when the choice ends first.\n" +
   "    function flash(e) {\n" +
   "      var c = card_of(e);\n" +
   "      var on = document.querySelectorAll(\"#map_sidebar .results_wrapper.avalon-flash\");\n" +
   "      for (var i = 0; i < on.length; i++) {\n" +
   "        cls(on[i], \"avalon-flash\", false);\n" +
   "      }\n" +
   "      clearTimeout(st.fl);\n" +
   "      st.fl = 0;\n" +
   "      if (!c) {\n" +
   "        return;\n" +
   "      }\n" +
   "      //Read, so that a card flashed a moment ago starts over.\n" +
   "      void c.offsetWidth;\n" +
   "      cls(c, \"avalon-flash\", true);\n" +
   "      st.fl = setTimeout(function () {\n" +
   "        st.fl = 0;\n" +
   "        cls(c, \"avalon-flash\", false);\n" +
   "      }, FLASH_MS);\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. A dealer chosen on its card, on a phone: no bubble will bring\n" +
   "    //the map round to its pin, so the map goes there when the pin is out\n" +
   "    //of sight - and stays where it is when it is not.\n" +
   "    function seen(e) {\n" +
   "      var g = e.marker && e.marker.__gmarker;\n" +
   "      var m = st.cm && st.cm.gmap;\n" +
   "      try {\n" +
   "        var at = g.getPosition();\n" +
   "        var b = m.getBounds();\n" +
   "        if (at && b && !b.contains(at)) {\n" +
   "          m.panTo(at);\n" +
   "        }\n" +
   "      } catch (x) {\n" +
   "        //A map that cannot say where it is stays where it is.\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. Focus to the chosen dealer's card - its name, where that is a\n" +
   "    //link, else the card itself - as it would have gone into a bubble:\n" +
   "    //without scrolling the page, and remembering where it came from. Not\n" +
   "    //while the Contact Dealer form is open.\n" +
   "    function to_card(e) {\n" +
   "      var c = card_of(e);\n" +
   "      var a = document.activeElement;\n" +
   "      if (!c || document.querySelector(\".contact-dealer--pop-up.open-modal\")) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var t = (typeof c.querySelector === \"function\" && c.querySelector(\".store_locator_name a\")) || c;\n" +
   "      if (t === c && typeof c.setAttribute === \"function\") {\n" +
   "        c.setAttribute(\"tabindex\", \"-1\");\n" +
   "      }\n" +
   "      if (a && a !== document.body && !inside(a) && !on_card(a)) {\n" +
   "        st.back = a;\n" +
   "      }\n" +
   "      st.sent = t;\n" +
   "      try {\n" +
   "        t.focus({ preventScroll: true });\n" +
   "      } catch (x) {\n" +
   "        //A card that refuses focus leaves focus where it was.\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. PHONES, in the header above: a dealer chosen, and no bubble.\n" +
   "    //card: chosen on its own card, which is left where the finger found it.\n" +
   "    function choose(e, card) {\n" +
   "      var prev = st.current;\n" +
   "      if (st.open && st.cm) {\n" +
   "        reclose(st.cm.infowindow);\n" +
   "      }\n" +
   "      st.current = e;\n" +
   "      st.open = false;\n" +
   "      st.pinned = true;\n" +
   "      st.overBubble = false;\n" +
   "      st.wantFocus = false;\n" +
   "      st.from = null;\n" +
   "      if (prev && prev !== e) {\n" +
   "        lit(prev, hovered(prev));\n" +
   "      }\n" +
   "      lit(e, true);\n" +
   "      ring(e);\n" +
   "      if (card) {\n" +
   "        seen(e);\n" +
   "        return;\n" +
   "      }\n" +
   "      place(e, true);\n" +
   "      flash(e);\n" +
   "      to_card(e);\n" +
   "    }\n" +
   "\n" +
   "    //Part 4e. THE BUBBLE'S WIDTH, in the header above: the week measured\n" +
   "    //open, once for each bubble's content, and that width kept as its least.\n" +
   "    function hold() {\n" +
   "      var b = bubble();\n" +
   "      var d = b && typeof b.querySelector === \"function\" ? b.querySelector(\".avalon-hours__narrow\") : null;\n" +
   "      if (!b || b.avalonHeld || !d || d.open || !b.style || !b.getBoundingClientRect) {\n" +
   "        return;\n" +
   "      }\n" +
   "      b.avalonHeld = true;\n" +
   "      var w = 0;\n" +
   "      try {\n" +
   "        d.open = true;\n" +
   "        w = b.getBoundingClientRect().width;\n" +
   "      } catch (x) {\n" +
   "        //Not measured: the bubble keeps the width of its content.\n" +
   "      }\n" +
   "      d.open = false;\n" +
   "      if (w > 0) {\n" +
   "        b.style.minWidth = Math.ceil(w) + \"px\";\n" +
   "      }\n" +
   "    }\n" +
   "\n"],
  ["slp_avalon.js: min_width() taken out",
   "      }, CLOSE_MS);\n" +
   "    }\n" +
   "\n" +
   "    function min_width(cm) {\n" +
   "      if (!window.matchMedia || !window.matchMedia(PHONE).matches) {\n" +
   "        return 0;\n" +
   "      }\n" +
   "      var div = cm.gmap && typeof cm.gmap.getDiv === \"function\" ? cm.gmap.getDiv() : null;\n" +
   "      var w = div ? div.clientWidth : 0;\n" +
   "      return w > MARGIN ? Math.min(WIDEST, w - MARGIN) : 0;\n" +
   "    }\n" +
   "\n" +
   "    //The dealer's name as text: SLP's marker carries it esc_attr()'d.\n",
   "      }, CLOSE_MS);\n" +
   "    }\n" +
   "\n" +
   "    //The dealer's name as text: SLP's marker carries it esc_attr()'d.\n"],
  ["slp_avalon.js: restore() also from the card of a dealer no longer chosen",
   "    //Focus back where it came from - but only when it was lost with the\n" +
   "    //bubble, never taken from wherever the visitor has put it since.\n" +
   "    function restore() {\n" +
   "      var back = st.back;\n" +
   "      st.back = null;\n" +
   "      var a = document.activeElement;\n" +
   "      if (!back || (a && a !== document.body && !inside(a))) {\n" +
   "        return;\n" +
   "      }\n",
   "    //Focus back where it came from - but only when it was lost with the\n" +
   "    //bubble, or is still where to_card() put it, on the card of a dealer\n" +
   "    //no longer chosen (held, Part 4e): never taken from wherever the\n" +
   "    //visitor has put it since.\n" +
   "    function restore(held) {\n" +
   "      var back = st.back;\n" +
   "      st.back = null;\n" +
   "      var a = document.activeElement;\n" +
   "      if (!back || (a && a !== document.body && !inside(a) && !held)) {\n" +
   "        return;\n" +
   "      }\n"],
  ["slp_avalon.js: clear() - whether focus is still where to_card() put it",
   "    function clear() {\n" +
   "      var e = st.current;\n" +
   "      cancel();\n",
   "    function clear() {\n" +
   "      var e = st.current;\n" +
   "      var held = !!st.sent && st.sent === document.activeElement;\n" +
   "      st.sent = null;\n" +
   "      cancel();\n"],
  ["slp_avalon.js: clear() - the flash, the card's place and the focus it held",
   "      ring(null);\n" +
   "      restore();\n",
   "      ring(null);\n" +
   "      flash(null);\n" +
   "      unplace(null);\n" +
   "      restore(held);\n"],
  ["slp_avalon.js: closed() - a close that arrives once the bubble is down is nobody's",
   "    //Google's close event: Esc inside the bubble, its anchor removed, or\n" +
   "    //close() above. Not the close that only reopens it at another width.\n" +
   "    function closed() {\n" +
   "      var iw = st.cm && st.cm.infowindow;\n" +
   "      if (st.quiet || (iw && iw.isOpen === true)) {\n",
   "    //Google's close event: Esc inside the bubble, its anchor removed, or\n" +
   "    //close() above. Not a close of ours that keeps the dealer chosen, nor\n" +
   "    //(Part 4e) one that arrives when no bubble is up any more: Google may\n" +
   "    //send it after the fact.\n" +
   "    function closed() {\n" +
   "      var iw = st.cm && st.cm.infowindow;\n" +
   "      if (st.quiet || !st.open || (iw && iw.isOpen === true)) {\n"],
  ["slp_avalon.js: reclose() - what it is for now",
   "    //Google's close() before the bubble reopens at another width: not the\n" +
   "    //visitor's, so closed() lets it pass. Google sends focus back to where\n" +
   "    //it was before the bubble opened (its guide, \"Close an info window\"),\n" +
   "    //and focusing can scroll the page. Focus that was in the bubble, or on\n" +
   "    //nothing, is let go - for focus_in() to put in the bubble reopened, or\n" +
   "    //to stay on nothing; focus that was elsewhere - the search box, say -\n" +
   "    //is put back there; the page is put back where it was. Says whether\n" +
   "    //focus was in the bubble.\n",
   "    //Google's close() when a window has narrowed to a phone's with a bubble\n" +
   "    //open (Part 4e; before it, when a bubble reopened at another width):\n" +
   "    //not the visitor's, so closed() lets it pass. Google sends focus back\n" +
   "    //to where it was before the bubble opened (its guide, \"Close an info\n" +
   "    //window\"), and focusing can scroll the page. Focus that was in the\n" +
   "    //bubble, or on nothing, is let go - for to_card() to put on the\n" +
   "    //dealer's card, or to stay on nothing; focus that was elsewhere - the\n" +
   "    //search box, say - is put back there; the page is put back where it\n" +
   "    //was. Says whether focus was in the bubble.\n"],
  ["slp_avalon.js: reclose() - the page put back at once, whatever its scroll-behavior",
   "        if (window.pageXOffset !== sx || window.pageYOffset !== sy) {\n" +
   "          window.scrollTo(sx, sy);\n" +
   "        }\n",
   "        //Part 4e. At once, and whether or not the page has moved yet:\n" +
   "        //where the page's own scroll-behavior is smooth - Aura's is - the\n" +
   "        //scroll a focus sets off only starts later, and this stops it.\n" +
   "        try {\n" +
   "          window.scrollTo({ left: sx, top: sy, behavior: \"instant\" });\n" +
   "        } catch (y) {\n" +
   "          if (window.pageXOffset !== sx || window.pageYOffset !== sy) {\n" +
   "            window.scrollTo(sx, sy);\n" +
   "          }\n" +
   "        }\n"],
  ["slp_avalon.js: show() - on a phone the choice goes to the card; elsewhere no width to set, and the card comes into view",
   "      var e = entry(info, marker);\n" +
   "      var iw = cm.infowindow;\n" +
   "      cancel();\n" +
   "      if (!hover) {\n" +
   "        st.from = document.activeElement;\n" +
   "      }\n" +
   "      if (!(st.open && st.current === e)) {\n" +
   "        var prev = st.current;\n" +
   "        var width = min_width(cm);\n" +
   "        var opts = { ariaLabel: name_of(info) };\n" +
   "        if (width !== st.minWidth) {\n" +
   "          if (st.open) {\n" +
   "            reclose(iw);\n" +
   "          }\n" +
   "          opts.minWidth = width;\n" +
   "          st.minWidth = width;\n" +
   "        }\n" +
   "        iw.setOptions(opts);\n" +
   "        iw.setContent(cm.createMarkerContent(info));\n",
   "      var e = entry(info, marker);\n" +
   "      var iw = cm.infowindow;\n" +
   "      var card = st.via === \"card\";\n" +
   "      cancel();\n" +
   "      //Part 4e. A phone has no bubble: a choice goes to the card, and a\n" +
   "      //hover - enter() has lit the pin - is nothing more.\n" +
   "      if (phone()) {\n" +
   "        if (!hover) {\n" +
   "          choose(e, card);\n" +
   "        }\n" +
   "        return;\n" +
   "      }\n" +
   "      if (!hover) {\n" +
   "        st.from = document.activeElement;\n" +
   "      }\n" +
   "      if (!(st.open && st.current === e)) {\n" +
   "        var prev = st.current;\n" +
   "        iw.setOptions({ ariaLabel: name_of(info) });\n" +
   "        iw.setContent(cm.createMarkerContent(info));\n"],
  ["slp_avalon.js: show() - a dealer chosen on the map has its card brought into view",
   "      if (!hover) {\n" +
   "        st.pinned = true;\n" +
   "        ring(e);\n" +
   "      }\n" +
   "    }\n",
   "      if (!hover) {\n" +
   "        st.pinned = true;\n" +
   "        ring(e);\n" +
   "        if (!card) {\n" +
   "          place(e, false);\n" +
   "        }\n" +
   "      }\n" +
   "    }\n"],
  ["slp_avalon.js: resized() - the bubble goes on a phone, the choice stays; no width to reopen at",
   "    //The window has stopped resizing - a phone turned, say. An open bubble\n" +
   "    //whose minWidth no longer fits the map is reopened at the new one, as\n" +
   "    //show() does: the same dealer, chosen or not as before. Focus that was\n" +
   "    //in it - lost as Google takes the bubble out - goes back to its Contact\n" +
   "    //Dealer once ready() has it in the page again. A map not laid out\n" +
   "    //(0 px wide) is left alone. Under the Contact Dealer form it waits:\n" +
   "    //dealer-popup-focus.js gives focus back, as the form closes, to the\n" +
   "    //link that opened it, which a reopen would take out of the page - and\n" +
   "    //it falls back to the search box. So it looks again until the form\n" +
   "    //has closed.\n" +
   "    function resized() {\n" +
   "      st.rs = 0;\n" +
   "      more();\n" +
   "      var cm = st.cm;\n" +
   "      var e = st.current;\n" +
   "      if (!cm || !st.open || !e || !e.marker || !e.marker.__gmarker) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var div = cm.gmap && typeof cm.gmap.getDiv === \"function\" ? cm.gmap.getDiv() : null;\n" +
   "      if (!div || !div.clientWidth) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var width = min_width(cm);\n" +
   "      if (width === st.minWidth) {\n" +
   "        return;\n" +
   "      }\n" +
   "      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {\n" +
   "        st.rs = setTimeout(resized, RESIZE_MS);\n" +
   "        return;\n" +
   "      }\n" +
   "      var iw = cm.infowindow;\n" +
   "      if (reclose(iw)) {\n" +
   "        st.wantFocus = true;\n" +
   "      }\n" +
   "      iw.setOptions({ minWidth: width });\n" +
   "      st.minWidth = width;\n" +
   "      iw.open({ map: cm.gmap, anchor: e.marker.__gmarker, shouldFocus: false });\n" +
   "    }\n",
   "    //The window has stopped resizing - a phone turned, a window dragged\n" +
   "    //narrower. Part 4e, where Part 4c reopened the bubble at another width:\n" +
   "    //\n" +
   "    //  a phone's now, a bubble open   the bubble goes. A dealer that was\n" +
   "    //                                 chosen stays chosen, on its card,\n" +
   "    //                                 which takes the focus the bubble\n" +
   "    //                                 had; one only hovered is let go.\n" +
   "    //  a phone's, a dealer chosen     its card where the layout now shows\n" +
   "    //                                 it: the list is beside the map one\n" +
   "    //                                 way up and under it the other.\n" +
   "    //  wider now, a dealer chosen     that dealer's bubble, as a click\n" +
   "    //    and no bubble                on its pin there would have opened\n" +
   "    //                                 it - but no focus taken: nobody\n" +
   "    //                                 chose anything just now.\n" +
   "    //\n" +
   "    //A map not laid out (0 px wide) is left alone. Under the Contact Dealer\n" +
   "    //form it waits: dealer-popup-focus.js gives focus back, as the form\n" +
   "    //closes, to the link that opened it, which taking the bubble out of\n" +
   "    //the page would lose - and it falls back to the search box. So it\n" +
   "    //looks again until the form has closed.\n" +
   "    function resized() {\n" +
   "      st.rs = 0;\n" +
   "      var cm = st.cm;\n" +
   "      var e = st.current;\n" +
   "      if (!cm || !e || !e.marker || !e.marker.__gmarker) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var div = cm.gmap && typeof cm.gmap.getDiv === \"function\" ? cm.gmap.getDiv() : null;\n" +
   "      if (!div || !div.clientWidth) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var small = phone();\n" +
   "      if (small ? !st.open && !st.pinned : st.open || !st.pinned) {\n" +
   "        return;\n" +
   "      }\n" +
   "      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {\n" +
   "        st.rs = setTimeout(resized, RESIZE_MS);\n" +
   "        return;\n" +
   "      }\n" +
   "      if (!small) {\n" +
   "        show(e.info, e.marker);\n" +
   "        st.wantFocus = false;\n" +
   "        return;\n" +
   "      }\n" +
   "      if (!st.open) {\n" +
   "        place(e, true);\n" +
   "        return;\n" +
   "      }\n" +
   "      var chosen = st.pinned;\n" +
   "      var had = reclose(cm.infowindow);\n" +
   "      st.open = false;\n" +
   "      st.overBubble = false;\n" +
   "      st.wantFocus = false;\n" +
   "      if (!chosen) {\n" +
   "        clear();\n" +
   "        return;\n" +
   "      }\n" +
   "      place(e, true);\n" +
   "      if (had) {\n" +
   "        to_card(e);\n" +
   "      }\n" +
   "    }\n"],
  ["slp_avalon.js: ready() - the bubble's width held; no fade to wire",
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
   "    function enter(e, kind) {\n",
   "      hold();\n" +
   "      if (st.wantFocus) {\n" +
   "        soon();\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    function enter(e, kind) {\n"],
  ["slp_avalon.js: leave() - a dealer chosen on a phone keeps its pin lit",
   "      if (st.open && st.current === e) {\n" +
   "        later();\n" +
   "      } else {\n" +
   "        lit(e, hovered(e));\n" +
   "      }\n" +
   "    }\n",
   "      if (st.open && st.current === e) {\n" +
   "        later();\n" +
   "      } else {\n" +
   "        //Part 4e. A dealer chosen on a phone has no bubble to keep its\n" +
   "        //pin lit: the choice does.\n" +
   "        lit(e, hovered(e) || (st.pinned && st.current === e));\n" +
   "      }\n" +
   "    }\n"],
  ["slp_avalon.js: Esc lets a dealer chosen on a phone go",
   "    //Esc closes the bubble - unless the Contact Dealer form is open over\n" +
   "    //the page, whose own Esc (dealer-popup-focus.js) comes first.\n" +
   "    function key(ev) {\n" +
   "      if ((ev.key !== \"Escape\" && ev.key !== \"Esc\" && ev.keyCode !== 27) || !st.open || ev.defaultPrevented ||\n",
   "    //Esc closes the bubble - on a phone (Part 4e), lets the chosen dealer\n" +
   "    //go - unless the Contact Dealer form is open over the page, whose own\n" +
   "    //Esc (dealer-popup-focus.js) comes first.\n" +
   "    function key(ev) {\n" +
   "      if ((ev.key !== \"Escape\" && ev.key !== \"Esc\" && ev.keyCode !== 27) || !(st.open || st.pinned) || ev.defaultPrevented ||\n"],
  ["slp_avalon.js: attach() - a click that starts on a result card is known for one; full screen is a resize",
   "      document.addEventListener(\"keydown\", key, false);\n",
   "      document.addEventListener(\"keydown\", key, false);\n" +
   "      //Part 4e. A click that starts on a result card is known for one\n" +
   "      //before SLP's handler on the card reaches show() - the capture\n" +
   "      //phase - and forgotten once the click is done.\n" +
   "      document.addEventListener(\"click\", function (ev) {\n" +
   "        if (!on_card(ev && ev.target)) {\n" +
   "          return;\n" +
   "        }\n" +
   "        st.via = \"card\";\n" +
   "        setTimeout(function () {\n" +
   "          st.via = \"\";\n" +
   "        }, 0);\n" +
   "      }, true);\n" +
   "      //Part 4e. Full screen coming or going changes what phone() says\n" +
   "      //without the window always saying it has resized.\n" +
   "      var full = function () {\n" +
   "        clearTimeout(st.rs);\n" +
   "        st.rs = setTimeout(resized, RESIZE_MS);\n" +
   "      };\n" +
   "      document.addEventListener(\"fullscreenchange\", full, false);\n" +
   "      document.addEventListener(\"webkitfullscreenchange\", full, false);\n"],
  ["slp_avalon.js: the block no longer exports more()",
   "      ring: ring,\n" +
   "      more: more,\n" +
   "      state: st\n",
   "      ring: ring,\n" +
   "      state: st\n"]
].map((e) => [e[0], crlf(e[1]), crlf(e[2])]);
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
section("identity", 11, () => {
  const a = src.indexOf(BLOCK_START);
  const b = src.indexOf(BLOCK_END, a);
  check(a > 0 && b > a && src.indexOf(BLOCK_START, a + 1) < 0,
        "the Part 4e block sits once, where enable_on_mouse_hover_for_markers() was");
  const blk = a > 0 && b > a ? src.slice(a, b + "  })();\r\n".length) : "";
  check(md5of(blk) === BLOCK_PIN.md5 && Buffer.byteLength(blk, "latin1") === BLOCK_PIN.len,
        "the block is the one this suite was written against (" + BLOCK_PIN.md5 + ", " + BLOCK_PIN.len + " bytes)");
  /* r3: Part 4e's edits out first, the last made first. */
  let p4d = blk;
  let in4e = blk !== "";
  for (let i = P4E_EDITS.length - 1; i >= 0 && in4e; i--) {
    if (p4d.split(P4E_EDITS[i][2]).length - 1 !== 1) { in4e = false; break; }
    p4d = p4d.replace(P4E_EDITS[i][2], () => P4E_EDITS[i][1]);
  }
  check(in4e && md5of(p4d) === P4D_BLOCK.md5 && Buffer.byteLength(p4d, "latin1") === P4D_BLOCK.len,
        "Part 4e's " + P4E_EDITS.length + " edits in it, each there once when its turn comes, reversed: Part 4d's block (68201cb5, 26,839 bytes)");
  let p4c = in4e ? p4d : "";
  let inBlock = in4e;
  for (let i = BLOCK_EDITS.length - 1; i >= 0 && inBlock; i--) {
    if (p4c.split(BLOCK_EDITS[i][2]).length - 1 !== 1) { inBlock = false; break; }
    p4c = p4c.replace(BLOCK_EDITS[i][2], () => BLOCK_EDITS[i][1]);
  }
  check(inBlock && md5of(p4c) === P4C_BLOCK.md5 && Buffer.byteLength(p4c, "latin1") === P4C_BLOCK.len,
        "  ... and Part 4d's " + BLOCK_EDITS.length + " edits in that, each there once, reversed: Part 4c's block (040a111a, 25,525 bytes)");
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
  check(!/minWidth:|min_width|\bmore\(|is-more|avalonMore|MARGIN|WIDEST/.test(code) && code.split("b.style.minWidth = ").length === 2 &&
        code.split("\"(max-width: 767px), (max-height: 500px)\"").length === 2,
        "r3: Google is given no width and nothing fades - the bubble's own least width set in one place; one phone query, upright or short");
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
    this.style = {};
    this.attrs = {};
  }
  /* r3. A node already in the page is moved, as in a browser - and, as
     there, moving takes focus from whatever inside it had it. */
  take(c) {
    const p = c.parentNode;
    if (!p) { return; }
    p.children = p.children.filter((n) => n !== c);
    c.parentNode = null;
    const d = this.env && this.env.doc;
    if (d && (d.activeElement === c || c.all().indexOf(d.activeElement) >= 0)) {
      d.activeElement = d.body;
      this.env.moveBlurs = (this.env.moveBlurs || 0) + 1;
    }
  }
  appendChild(c) { this.take(c); c.parentNode = this; this.children.push(c); return c; }
  insertBefore(c, ref) {
    this.take(c);
    const i = this.children.indexOf(ref);
    if (i < 0) { throw new Error("fake DOM: insertBefore a node that is not a child"); }
    c.parentNode = this;
    this.children.splice(i, 0, c);
    return c;
  }
  get nextSibling() {
    const p = this.parentNode;
    return p ? (p.children[p.children.indexOf(this) + 1] || null) : null;
  }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  /* r3. Where the element is: from the page's arithmetic (lay(), below)
     where there is one, else a rectangle set by hand, else nowhere. */
  getBoundingClientRect() {
    const r = this.geo ? this.geo() : (this.rect || { top: 0, bottom: 0 });
    return { top: r.top, bottom: r.bottom, left: 0, right: r.width || 0, width: r.width || 0, height: r.bottom - r.top };
  }
  get offsetWidth() { this.reads = (this.reads || 0) + 1; return 0; }
  addEventListener(t, fn, cap) { (this.listeners[t] = this.listeners[t] || []).push({ fn: fn, cap: !!cap }); }
  fire(t, ev) { (this.listeners[t] || []).forEach((l) => l.fn.call(this, ev || { type: t })); }
  all() {
    const out = [];
    const walk = (n) => n.children.forEach((c) => { out.push(c); walk(c); });
    walk(this);
    return out;
  }
  querySelector(sel) {
    const cls = (name) => this.all().filter((n) => (" " + n.className + " ").indexOf(" " + name + " ") >= 0)[0] || null;
    if (sel === ".sl_popup_contact_info" || sel === ".results_wrapper" || sel === ".avalon-hours__narrow") {
      return cls(sel.slice(1));
    }
    if (sel === ".store_locator_name a") {
      const h = cls("store_locator_name");
      return h ? (h.all().filter((n) => n.tagName === "A")[0] || null) : null;
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
                opens: 0, calm: false, queries: [], pans: [], pageScrolls: [], boxScrolls: [], boxSets: [], scrollToArgs: [],
                wShut: 255.3, wOpen: 280.1, opened: 0 };
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
  /* r3. Until a section lays the page out, the map is 600 px high at the
     window's top and the list beside it, not under it. */
  mapDiv.rect = { top: 0, bottom: 600 };
  body.appendChild(sidebar);
  body.appendChild(mapDiv);
  env.modalOpen = false;
  const doc = {
    body: body,
    documentElement: html,
    activeElement: body,
    listeners: {},
    caps: {},
    elementFromPoint: () => env.topEl || body,
    getElementById: (id) => (id === "map_sidebar" && opts.noSidebar ? null : body.all().filter((n) => n.id === id)[0] || null),
    querySelector: (sel) => {
      if (sel === "#map .slp_info_bubble") { return mapDiv.all().filter((n) => /(^| )slp_info_bubble( |$)/.test(n.className))[0] || null; }
      if (sel === ".contact-dealer--pop-up.open-modal") { return env.modalOpen ? {} : null; }
      throw new Error("fake document: unsupported selector " + sel);
    },
    querySelectorAll: (sel) => {
      const m = /^#map_sidebar \.results_wrapper\.(active|avalon-flash)$/.exec(sel);
      if (m) {
        return sidebar.all().filter((n) => /(^| )results_wrapper( |$)/.test(n.className) && (" " + n.className + " ").indexOf(" " + m[1] + " ") >= 0);
      }
      throw new Error("fake document: unsupported selectorAll " + sel);
    },
    addEventListener: (t, fn, cap) => { (doc.listeners[t] = doc.listeners[t] || []).push(fn); (doc.caps[t] = doc.caps[t] || []).push(!!cap); },
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
    const info = new El("div", { "class": "sl_popup_contact_info" }, env);
    const tel = new El("a", { "class": "avalon-tel" }, env);
    info.appendChild(tel);
    /* r3. The hours' fold, where the dealer has hours: the bubble is as wide
       as its content, env.wShut with the week shut and env.wOpen with it
       open; every time the fold is opened is counted. */
    env.det = null;
    if (iw.content.hours !== false) {
      const det = new El("details", { "class": "avalon-hours__narrow" }, env);
      let isOpen = !!env.weekOpen;
      Object.defineProperty(det, "open", { get: () => isOpen, set: (v) => { isOpen = !!v; if (isOpen) { env.opened++; } } });
      info.appendChild(det);
      env.det = det;
      b.geo = () => {
        if (env.noMeasure) { throw new Error("no layout"); }
        return { top: 0, bottom: 0, width: isOpen ? env.wOpen : env.wShut };
      };
    }
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
  env.gmap = { getDiv: () => mapDiv, getZoom: () => 9, setZoom: (z) => { env.zoom = z; },
               /* r3. What the map shows: every pin, unless a section says otherwise. */
               getBounds: () => (env.noBounds ? undefined : { contains: (at) => !(env.outside && env.outside.indexOf(at.of) >= 0) }),
               panTo: (at) => { env.pans.push(at.of); } };

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
    /* r3. scrollTo() with options, as Part 4e calls it, or with two numbers;
       scrollBy() for the page; the window's size; what a style resolves to. */
    scrollTo: (a, b) => {
      const o = a !== null && typeof a === "object";
      env.scrollToArgs.push(o ? a : [a, b]);
      if (o && env.noScrollOptions) { throw new TypeError("no options"); }
      const x = o ? a.left : a;
      const y = o ? a.top : b;
      env.scrolls.push([x, y]);
      env.ctx.pageXOffset = x;
      env.ctx.pageYOffset = y;
      if (env.L) { env.L.y = y; }
    },
    scrollBy: (a, b) => {
      const o = a !== null && typeof a === "object";
      env.pageScrolls.push(o ? a : [a, b]);
      if (o && env.noScrollOptions) { throw new TypeError("no options"); }
      const y = Math.max(0, env.ctx.pageYOffset + (o ? a.top : b));
      env.ctx.pageYOffset = y;
      if (env.L) { env.L.y = y; }
    },
    innerWidth: 1440,
    innerHeight: 900,
    getComputedStyle: (n) => ({ overflowY: n.overflowY || "visible", position: n.position || "static",
                               paddingBottom: (n.style && n.style.paddingBottom) || "0px" }),
    matchMedia: (q) => {
      env.queries.push(q);
      return { matches: q === PHONE_Q ? env.phone : (q === "(prefers-reduced-motion: reduce)" ? env.calm : false), media: q };
    },
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

/* r3. Part 4e's phone: upright, or sideways and short. */
const PHONE_Q = "(max-width: 767px), (max-height: 500px)";

/* One dealer's marker, as SLP's slp_Marker carries it. */
function gm(env, label, icon) {
  let ic = icon || "https://example.test/wp-content/uploads/pin.png";
  let z;
  const g = {
    label: label,
    sets: [],
    getPosition: () => ({ of: label }),
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
    createMarkerContent: (info) => ({ name: info.name, id: info.id, url: info.url, directions: !opts.nodirections, hours: !opts.nohours })
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
/* r3. The results as SLP draws them: one card per dealer, in #map_sidebar,
   its name a link; which cards are marked .active, which flashed, and the
   order they stand in. */
const cards = (env) => env.cm.markers.map((m) => {
  const c = new El("div", { "class": "results_wrapper", id: "slp_results_wrapper_" + m.__location_id }, env);
  const h = new El("h3", { "class": "store_locator_name" }, env);
  h.appendChild(new El("a", { "class": "name-link" }, env));
  c.appendChild(h);
  env.sidebar.appendChild(c);
  return c;
});
const withClass = (env, name) => env.sidebar.children.filter((n) => (" " + n.className + " ").indexOf(" " + name + " ") >= 0)
  .map((n) => n.id.replace("slp_results_wrapper_", ""));
const marked = (env) => withClass(env, "active");
const flashed = (env) => withClass(env, "avalon-flash");
const nameOf = (c) => c.querySelector(".store_locator_name a");
const order = (env) => env.sidebar.children.map((c) => c.id.replace("slp_results_wrapper_", "")).join();
/* r3. A click on a dealer's card, as the page runs it: the document hears
   it first, in the capture phase; SLP's handler on the card then calls
   show(); then the click is done. on: what in the card was clicked. */
const cardClick = (env, i, on) => {
  const c = env.doc.getElementById("slp_results_wrapper_" + env.cm.markers[i].__location_id);
  (env.doc.listeners.click || []).forEach((fn) => fn({ type: "click", target: on || c }));
  click(env, i);
  env.advance(0);
};
const esc = (env) => key(env, { key: "Escape" });
const resize = (env) => (env.winL.resize || []).forEach((fn) => fn({ type: "resize" }));
const top = (el) => el.getBoundingClientRect().top;
const bottom = (el) => el.getBoundingClientRect().bottom;

/* r3. A page laid out, with arithmetic behind every rectangle: the results
   in a box that scrolls (the theme's #results_box), beside the map or under
   it; cards one under another, 8 px apart; the page scrolled by L.y. All
   in px from the page's top; a rectangle is where the window shows it.
   Scrolls land at once - what is asserted is where things end up. The
   three layouts are Aura's, measured on DEV on 2026-10-07. */
const DESK = { winH: 900, mapTop: 116, mapH: 867, boxTop: 313, boxH: 670 };
const UPRIGHT = { winH: 844, mapTop: 287, mapH: 464, boxTop: 791, boxH: 670 };
const SIDEWAYS = { winH: 390, mapTop: 60, mapH: 897, boxTop: 287, boxH: 670 };
function lay(env, o) {
  const L = env.L = Object.assign({ y: 0, gap: 8, cardH: 241, box: true }, o || {});
  const side = env.sidebar;
  env.ctx.innerHeight = L.winH;
  env.ctx.pageYOffset = L.y;
  env.mapDiv.geo = () => ({ top: L.mapTop - L.y, bottom: L.mapTop - L.y + L.mapH });
  const content = () => side.children.reduce((s, c, i) => s + c.h + (i ? L.gap : 0), 0) + (parseFloat(side.style.paddingBottom) || 0);
  let box = null;
  if (L.box) {
    box = new El("div", { id: "results_box" }, env);
    box.overflowY = "auto";
    env.doc.body.appendChild(box);
    box.appendChild(side);
    box.clientHeight = L.boxH;
    box.at = 0;
    Object.defineProperty(box, "scrollHeight", { get: () => Math.max(L.boxH, content()) });
    /* As a browser: never past the end, however the content shrinks. */
    Object.defineProperty(box, "scrollTop", {
      get: () => Math.max(0, Math.min(box.at, box.scrollHeight - L.boxH)),
      set: (v) => { env.boxSets.push(v); box.at = v; }
    });
    box.geo = () => ({ top: L.boxTop - L.y, bottom: L.boxTop - L.y + L.boxH });
    box.scrollBy = (a) => {
      env.boxScrolls.push(a);
      if (env.noScrollOptions) { throw new TypeError("no options"); }
      box.at = box.scrollTop + a.top;
    };
  }
  const listTop = () => L.boxTop - L.y - (box ? box.scrollTop : 0);
  side.geo = () => ({ top: listTop(), bottom: listTop() + content() });
  const cs = cards(env);
  cs.forEach((c, i) => {
    c.h = Array.isArray(L.cardH) ? L.cardH[i] : L.cardH;
    c.geo = () => {
      let off = 0;
      for (let k = 0; k < side.children.indexOf(c); k++) { off += side.children[k].h + L.gap; }
      return { top: listTop() + off, bottom: listTop() + off + c.h };
    };
  });
  env.box = box;
  return cs;
}
/* The same page in another layout - a phone turned. */
function relay(env, o) {
  Object.assign(env.L, o);
  env.ctx.innerHeight = env.L.winH;
  if (env.box) { env.box.clientHeight = env.L.boxH; }
}

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
      (env.winL.resize || []).length === 1 && env.on.filter((x) => /\.avalonMap$/.test(x.args[0])).length === 2 &&
      same(env.doc.caps.click, [true]) && (env.doc.listeners.fullscreenchange || []).length === 1 &&
      (env.doc.listeners.webkitfullscreenchange || []).length === 1,
      "  ... with one domready, one close, one map click, one keydown, one window resize and the two card handlers - and, r3, one click heard in the capture phase and full screen's two events");
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

section("phones", 23, () => {
console.log("");
console.log("  PHONES (Part 4e) - no bubble: the choice goes to the card");
let env = boot({ phone: true, n: 4 });
let cs = cards(env);
const pin = new El("div", { id: "pin-button" }, env);
env.mapDiv.appendChild(pin);
env.doc.activeElement = pin;
click(env, 0);
check(env.iwLog.length === 0 && env.opens === 0 && st(env).open === false && env.queries.indexOf(PHONE_Q) >= 0,
      "a pin chosen on a phone - 767 px wide or less, or 500 px high or less: Google's bubble is not touched - no content set, nothing opened");
check(st(env).pinned === true && cur(env) === "101" && g(env, 0).getIcon() === HOVER && g(env, 0).getZIndex() === 1000001,
      "  ... the dealer is chosen all the same, and its pin lit");
check(same(marked(env), ["101"]) && same(flashed(env), ["101"]) && cs[0].reads === 1,
      "  ... its card marked .active and flashed - .avalon-flash, put on after the card has been read, so that a flash starts over");
check(env.doc.activeElement === nameOf(cs[0]) && same(nameOf(cs[0]).focusCalls, [{ preventScroll: true }]) && st(env).back === pin &&
      st(env).sent === nameOf(cs[0]),
      "  ... and focus on its name, without scrolling the page - where it came from, the pin, remembered");
env.advance(1099);
const still = flashed(env).join();
env.advance(1);
check(still === "101" && same(flashed(env), []) && same(marked(env), ["101"]), "the flash comes off after 1.1 s; the mark stays");
click(env, 1);
check(same(marked(env), ["102"]) && same(flashed(env), ["102"]) && cur(env) === "102" && g(env, 0).getIcon() !== HOVER &&
      g(env, 1).getIcon() === HOVER && env.doc.activeElement === nameOf(cs[1]) && st(env).back === pin && env.opens === 0,
      "another pin chosen: the mark, the flash, the lit pin and focus move with it - still bound back to the first pin");
env.advance(600);
click(env, 1);
env.advance(1099);
check(same(flashed(env), ["102"]) && cs[1].reads === 2, "the same pin again: its card flashes again, for a whole 1.1 s from then");
env.advance(1);
esc(env);
check(st(env).pinned === false && cur(env) === null && same(marked(env), []) && same(flashed(env), []) && g(env, 1).getIcon() !== HOVER &&
      env.doc.activeElement === pin && same(pin.focusCalls, [{ preventScroll: true }]) && env.iwLog.length === 0,
      "Esc lets the dealer go: no mark, the pin put back, focus - still on the card's name - back on the pin it came from; Google's bubble still untouched");
env = boot({ phone: true, n: 4 });
cs = cards(env);
click(env, 2);
env.trigger(env.gmap, "click", {});
check(st(env).pinned === false && same(marked(env), []) && g(env, 2).getIcon() !== HOVER, "a tap on the map lets the dealer go too");
over(env, 1);
check(st(env).current === null && st(env).pinned === false && env.opens === 0 && same(marked(env), []) && same(flashed(env), []) &&
      g(env, 1).getIcon() === HOVER,
      "a hover on a phone - a mouse on a narrow window: the pin lit and nothing more");
out(env, 1);
click(env, 0);
over(env, 0);
out(env, 0);
const keptLit = g(env, 0).getIcon() === HOVER;
over(env, 3);
const otherLit = g(env, 3).getIcon() === HOVER;
out(env, 3);
check(g(env, 1).getIcon() !== HOVER && keptLit && otherLit && g(env, 3).getIcon() !== HOVER && cur(env) === "101" &&
      env.doc.activeElement === nameOf(cs[0]),
      "a chosen dealer's pin stays lit when the pointer leaves it; another pin hovered meanwhile lights and goes out, and takes nothing");
env = boot({ phone: true, n: 4 });
cs = cards(env);
const input = new El("input", { id: "addressInput" }, env);
env.doc.body.appendChild(input);
env.doc.activeElement = input;
cardClick(env, 2);
check(same(marked(env), ["103"]) && same(flashed(env), []) && g(env, 2).getIcon() === HOVER && st(env).pinned === true && env.opens === 0 &&
      env.doc.activeElement === input && nameOf(cs[2]).focusCalls.length === 0 && order(env) === "101,102,103,104" && same(env.pans, []),
      "a card chosen on a phone: marked, its pin lit - no flash, no focus taken, the card left where the finger found it, the map left alone while the pin is in sight");
env.outside = ["m102"];
cardClick(env, 1);
check(same(env.pans, ["m102"]) && same(marked(env), ["102"]), "  ... and the map brought round to a pin that is out of sight, once");
env.noBounds = true;
env.outside = ["m104"];
let threw = false;
try {
  cardClick(env, 3);
} catch (e) {
  threw = true;
}
check(!threw && same(env.pans, ["m102"]) && same(marked(env), ["104"]), "  ... a map that cannot say what it shows stays where it is, and nothing is thrown");
check(st(env).via === "", "  ... and once the click is done, the next choice is a pin's again");
click(env, 0);
check(same(flashed(env), ["101"]) && env.doc.activeElement === nameOf(cs[0]) && st(env).back === input,
      "a pin chosen after a card: flashed and focused as a pin's choice is - bound back to the search box focus was in");
env = boot({ phone: true, n: 4 });
cs = cards(env);
env.modalOpen = true;
click(env, 1);
check(same(marked(env), ["102"]) && same(flashed(env), ["102"]) && env.doc.activeElement === env.doc.body && nameOf(cs[1]).focusCalls.length === 0 &&
      st(env).sent === null,
      "the Contact Dealer form open over the page: the card marked and flashed, focus not pulled from the form");
esc(env);
check(st(env).pinned === true && cur(env) === "102", "  ... and Esc is the form's, not ours, while it is open");
env = boot({ phone: true, n: 4 });
cs = cards(env);
env.doc.fullscreenElement = env.mapDiv;
click(env, 0);
const fsOpen = env.opens === 1 && st(env).open === true;
env.doc.fullscreenElement = null;
env.doc.webkitFullscreenElement = env.mapDiv;
click(env, 1);
check(fsOpen && env.opens === 2 && cur(env) === "102" && same(flashed(env), []),
      "a phone's map shown full screen - Safari's prefixed full screen too: its pins open bubbles, since no card can be seen behind it");
env = boot({ phone: true, n: 4 });
cs = cards(env);
cs[1].children = [];
click(env, 1);
check(env.doc.activeElement === cs[1] && cs[1].attrs.tabindex === "-1", "a card whose name is not a link takes focus itself, made focusable for it");
env = boot({ phone: true, n: 4 });
threw = false;
let lone = false;
try {
  click(env, 0);
  lone = st(env).pinned === true && g(env, 0).getIcon() === HOVER;
  env.advance(2000);
  esc(env);
} catch (e) {
  threw = true;
}
check(!threw && lone && st(env).pinned === false && g(env, 0).getIcon() !== HOVER, "a dealer with no card in the list: chosen, lit and let go, nothing thrown");
env = boot({ phone: true, n: 4 });
cs = cards(env);
click(env, 3);
env.M.bind(env.cm, env.list);
check(st(env).pinned === false && cur(env) === null && same(marked(env), []) && env.iwLog.length === 0,
      "a new search lets the chosen dealer go; there is no bubble to close");
env = boot({ phone: true, n: 4, options: { hide_bubble: "1" } });
cs = cards(env);
click(env, 0);
check(st(env).pinned === false && same(marked(env), []) && g(env, 0).getIcon() !== HOVER, "SLP's hide_bubble set: nothing is chosen on a phone either");
});

/* ------------------------------------------------------ the card in view */

section("card in view", 28, () => {
console.log("");
console.log("  THE CARD IN VIEW (Part 4e) - a dealer chosen on the map has its card brought where it can be seen");
const D265 = Object.assign({}, DESK, { cardH: 265 });
/* Beside the map, where a bubble opens. */
let env = boot({ n: 6 });
let cs = lay(env, D265);
click(env, 2);
check(top(cs[2]) === top(env.box) && same(env.boxScrolls, [{ top: 546, left: 0, behavior: "smooth" }]) && env.opens === 1,
      "a pin chosen beside the map: the results scroll, smoothly, until its card is at the top of what shows of them - 546 px here - and its bubble opens");
check(same(env.pageScrolls, []) && !env.sidebar.style.paddingBottom && st(env).pad === null && order(env) === "101,102,103,104,105,106",
      "  ... the page itself is not scrolled, no room is made, no card moved");
click(env, 3);
check(env.boxScrolls.length === 1 && top(cs[3]) === 586 && bottom(cs[3]) <= 900,
      "the next pin's card is in full view already, under the first: left where it is");
click(env, 0);
check(top(cs[0]) === top(env.box) && env.boxScrolls.length === 2 && env.boxScrolls[1].top === -546,
      "a card above what shows: the results scroll back up to it");
click(env, 5);
check(top(cs[5]) === top(env.box) && env.sidebar.style.paddingBottom === "405px" && st(env).pad !== null && st(env).pad.card === cs[5],
      "the last card cannot scroll that far: room is made under it - 405 px - and it reaches the top all the same");
click(env, 4);
check(!env.sidebar.style.paddingBottom && st(env).pad === null && top(cs[4]) >= top(env.box) && bottom(cs[4]) <= 900 && env.boxScrolls.length === 3,
      "another pin chosen: the room is taken away again, and that card - in full view once it is - left where it is");
click(env, 5);
const padded = env.sidebar.style.paddingBottom;
esc(env);
check(/^\d+px$/.test(padded) && !env.sidebar.style.paddingBottom && st(env).pad === null, "the choice ended - Esc: the room goes");
env = boot({ n: 6 });
cs = lay(env, D265);
cardClick(env, 5);
check(env.opens === 1 && same(marked(env), ["106"]) && same(env.boxScrolls, []) && same(env.pageScrolls, []) && !env.sidebar.style.paddingBottom,
      "a dealer chosen on its own card: its bubble opens, and nothing scrolls - the card is under the pointer already");
cardClick(env, 4, nameOf(cs[4]));
check(same(env.boxScrolls, []) && cur(env) === "105", "  ... wherever in the card the click started - on its name, say");
env = boot({ n: 6 });
cs = lay(env, D265);
over(env, 5);
check(env.opens === 1 && same(env.boxScrolls, []) && same(marked(env), []), "a hover opens the bubble and scrolls nothing");
env = boot({ n: 6 });
cs = lay(env, Object.assign({}, D265, { winH: 700 }));
click(env, 1);
check(top(cs[1]) === 313 && bottom(cs[1]) <= 700 && same(env.pageScrolls, []),
      "a box that reaches past the window's foot: a card under the foot is scrolled to the box's top, into sight");
env = boot({ n: 6 });
cs = lay(env, Object.assign({}, D265, { y: 400 }));
click(env, 3);
check(top(cs[3]) === 8 && same(env.pageScrolls, []), "a box whose top is above the window's: the card 8 px under the window's top");
const bar = new El("div", { id: "wpadminbar" }, env);
bar.position = "fixed";
bar.rect = { top: 0, bottom: 32 };
env.doc.body.appendChild(bar);
env.topEl = bar;
click(env, 5);
check(top(cs[5]) === 40, "  ... or 8 px under a fixed bar that covers the window's top - an admin bar's 32 px");
env = boot({ n: 6 });
cs = lay(env, D265);
env.calm = true;
click(env, 2);
check(same(env.boxScrolls, [{ top: 546, left: 0, behavior: "instant" }]) && env.queries.indexOf("(prefers-reduced-motion: reduce)") >= 0,
      "less motion asked for: the same scroll, at once - whatever the page's own scroll-behavior says");
env = boot({ n: 6 });
cs = lay(env, D265);
env.noScrollOptions = true;
click(env, 2);
check(top(cs[2]) === 313 && same(env.boxSets, [546]), "a browser whose scrollBy() takes no options: the box scrolled by its scrollTop, at once");
env = boot({ n: 6 });
cs = lay(env, Object.assign({}, D265, { box: false }));
click(env, 5);
check(same(env.pageScrolls, []) && env.opens === 1 && order(env) === "101,102,103,104,105,106",
      "results with no box of their own, beside the map, where a bubble opens: nothing scrolls");
/* A phone held sideways: the list beside the map, and no bubble. */
env = boot({ phone: true, n: 6 });
cs = lay(env, SIDEWAYS);
click(env, 0);
check(env.opens === 0 && same(env.boxScrolls, []) && same(env.pageScrolls, [{ top: 146, left: 0, behavior: "smooth" }]) &&
      top(cs[0]) === 141 && bottom(cs[0]) === 382,
      "a phone held sideways, the page at its top: the first card is at the box's top already, but the box starts low - the page scrolls 146 px, just far enough to show the card whole, 8 px clear of the foot");
click(env, 3);
check(top(cs[3]) === top(env.box) && bottom(cs[3]) === 382 && env.pageScrolls.length === 1 && env.boxScrolls.length === 1,
      "  ... the next pin: the results scroll, the page stays");
env = boot({ phone: true, n: 6 });
cs = lay(env, Object.assign({}, SIDEWAYS, { y: 300 }));
click(env, 1);
check(top(cs[1]) === 8 && same(env.pageScrolls, []), "  ... the box's top above the window's: the card 8 px under the window's top, the page left alone");
env = boot({ phone: true, n: 6 });
cs = lay(env, Object.assign({}, SIDEWAYS, { cardH: 455 }));
click(env, 0);
check(env.pageScrolls.length === 1 && env.pageScrolls[0].top === 279 && top(env.box) === 8,
      "  ... a card taller than the window - its week open: the page scrolls until the box's top is 8 px under the window's, and no further");
env = boot({ phone: true, n: 6 });
cs = lay(env, Object.assign({}, SIDEWAYS, { box: false }));
click(env, 2);
check(top(cs[2]) === 8 && env.pageScrolls.length === 1,
      "results with no box of their own, on a phone: the page scrolls the card to 8 px under the window's top");
/* A phone upright: the list under the map. */
env = boot({ phone: true, n: 6 });
cs = lay(env, UPRIGHT);
const pinB = new El("div", { id: "pin-button" }, env);
env.mapDiv.appendChild(pinB);
env.doc.activeElement = pinB;
click(env, 3);
check(order(env) === "104,101,102,103,105,106" && st(env).moved !== null && st(env).moved.el === cs[3] && st(env).moved.next === cs[4] &&
      same(env.boxSets, [0]),
      "a pin chosen with the list under the map: its card moves to the top of the list, and the list to its own top");
check(same(env.pageScrolls, [{ top: 196, left: 0, behavior: "smooth" }]) && bottom(cs[3]) === 836 && top(env.mapDiv) === 91 && same(env.boxScrolls, []),
      "  ... and the page scrolls 196 px - just far enough to show the card whole, 8 px clear of the foot, the map's top still in view");
click(env, 1);
check(order(env) === "102,101,103,104,105,106" && st(env).moved.el === cs[1] && env.doc.activeElement === nameOf(cs[1]) && st(env).back === pinB &&
      env.moveBlurs === 1 && nameOf(cs[3]).focusCalls.length === 2,
      "another pin: the first card back in its place, this one at the top - and focus, which moving a card takes from its name, given back before it goes on to the new card's");
esc(env);
check(order(env) === "101,102,103,104,105,106" && st(env).moved === null && env.doc.activeElement === pinB,
      "Esc: the card back in its place, focus back on the pin");
click(env, 0);
const first = order(env) === "101,102,103,104,105,106" && st(env).moved === null;
click(env, 5);
const lastUp = order(env);
esc(env);
const scrolls = env.pageScrolls.length;
cardClick(env, 2);
check(first && lastUp === "106,101,102,103,104,105" && order(env) === "101,102,103,104,105,106" && env.pageScrolls.length === scrolls &&
      same(marked(env), ["103"]),
      "the first card has nowhere to move; the last moves up and goes back to the end; a card chosen on itself is marked where it stands, the page not scrolled");
env = boot({ phone: true, n: 6 });
cs = lay(env, Object.assign({}, UPRIGHT, { winH: 600, mapTop: 100, mapH: 330, boxTop: 470, cardH: 455 }));
click(env, 1);
const tall = env.pageScrolls.length === 1 && env.pageScrolls[0].top === 148 && 600 - top(cs[1]) >= 270 && top(env.mapDiv) < 0;
env = boot({ phone: true, n: 6 });
cs = lay(env, Object.assign({}, UPRIGHT, { y: 600 }));
click(env, 2);
check(tall && same(env.pageScrolls, []) && order(env).indexOf("103") === 0,
      "a card too tall to show whole with the map: the page scrolls until 45% of the window shows of it and lets the map's top go; a card in sight already moves to the top and the page stays - never scrolled back up");
/* The list under the map on a screen that keeps its bubble - a tablet upright. */
env = boot({ n: 6 });
cs = lay(env, { winH: 1024, mapTop: 287, mapH: 570, boxTop: 897, boxH: 670 });
click(env, 4);
const tablet = order(env) === "105,101,102,103,104,106" && env.opens === 1 && same(env.pageScrolls, []) && same(flashed(env), []);
env.sidebar.children.forEach((c) => { c.parentNode = null; });
env.sidebar.children = [];
let threw = false;
try {
  env.M.bind(env.cm, env.list);
} catch (e) {
  threw = true;
}
check(tablet && !threw && st(env).moved === null,
      "a tablet upright - the list under the map, and a bubble: the card moves up, the bubble opens, the page is not scrolled, nothing flashes; and a search that draws the list again leaves nothing to put back");
});

/* --------------------------------------------------------------- resize */

section("resize", 23, () => {
console.log("");
console.log("  RESIZE (Part 4e) - a window narrowed loses the bubble and keeps the choice; one widened gets the bubble");
const inMap = (env, n) => env.mapDiv.all().indexOf(n) >= 0;
let env = boot({ n: 4 });
let cs = cards(env);
const pin = new El("div", { id: "pin-button" }, env);
env.mapDiv.appendChild(pin);
env.doc.activeElement = pin;
click(env, 0);
env.dom();
env.advance(0);
const cd = env.doc.activeElement;
env.iwLog.length = 0;
env.phone = true;
resize(env);
env.advance(100);
resize(env);
env.advance(199);
check(env.iwLog.length === 0 && st(env).open === true && cd.parentNode.id === "slp_bubble_website",
      "a window narrowed to a phone's with a bubble open: nothing until it has been still for 0.2 s - each resize starts the wait again");
env.advance(1);
check(same(env.iwLog, [["close"]]) && st(env).open === false && !inMap(env, cd), "  ... then the bubble goes: close(), once, and nothing reopened");
check(st(env).pinned === true && cur(env) === "101" && same(marked(env), ["101"]) && g(env, 0).getIcon() === HOVER && same(flashed(env), []),
      "  ... the dealer stays chosen - that close is not the visitor's: its card marked, its pin lit, no flash");
check(env.doc.activeElement === nameOf(cs[0]) && st(env).back === pin && pin.focusCalls.length === 0 && env.pending() === 0,
      "  ... and focus, which was in the bubble, on the card's name - still bound back to the pin; nothing left pending");
esc(env);
check(st(env).pinned === false && same(marked(env), []) && env.doc.activeElement === pin, "Esc then lets it go, and focus returns to the pin");
env = boot({ n: 4 });
cs = cards(env);
over(env, 1);
env.dom();
env.advance(0);
out(env, 1);
env.phone = true;
resize(env);
env.advance(200);
check(same(env.iwLog.map((x) => x[0]).slice(-1), ["close"]) && st(env).open === false && cur(env) === null && st(env).pinned === false &&
      same(marked(env), []) && g(env, 1).getIcon() !== HOVER,
      "a bubble only hovered, the window narrowed: the bubble goes and nothing is kept");
env.advance(1000);
check(env.iwLog.filter((x) => x[0] === "close").length === 1 && env.pending() === 0, "  ... and the close its pin's leaving had pending closes nothing twice");
env = boot({ phone: true, n: 4 });
cs = cards(env);
click(env, 2);
env.advance(2000);
const nameFocus = env.focusLog.length;
env.phone = false;
resize(env);
env.advance(200);
check(same(env.iwLog.map((x) => x[0]), ["setOptions", "setContent", "open"]) && same(env.iwLog[2], ["open", { anchor: "m103", shouldFocus: false, map: true }]) &&
      st(env).open === true && st(env).pinned === true && cur(env) === "103" && same(marked(env), ["103"]),
      "a window widened with a dealer chosen: that dealer's bubble opens, chosen, its card still marked");
env.dom();
env.advance(0);
check(env.focusLog.length === nameFocus && env.doc.activeElement === nameOf(cs[2]) && st(env).wantFocus === false,
      "  ... and takes no focus: nobody chose anything just now");
/* A phone turned with a dealer chosen: its card where the layout now shows it. */
env = boot({ phone: true, n: 6 });
cs = lay(env, UPRIGHT);
click(env, 5);
const up = order(env);
relay(env, SIDEWAYS);
resize(env);
env.advance(200);
check(up === "106,101,102,103,104,105" && order(env) === "101,102,103,104,105,106" && st(env).moved === null,
      "a phone turned sideways with a dealer chosen: its card back in its place in the list, now beside the map");
check(top(cs[5]) === top(env.box) && st(env).pad !== null && bottom(cs[5]) <= 390 - 8 && env.opens === 0 &&
      env.doc.activeElement === nameOf(cs[5]),
      "  ... the results scrolled to it, with room made under it; focus kept on its name through the move; still no bubble");
relay(env, UPRIGHT);
resize(env);
env.advance(200);
check(order(env) === "106,101,102,103,104,105" && !env.sidebar.style.paddingBottom && st(env).pad === null && env.box.scrollTop === 0,
      "  ... and upright again: at the top of the list once more, the room made beside the map taken away");
env = boot({ n: 4 });
cs = cards(env);
env.phone = true;
resize(env);
env.advance(1000);
env.phone = false;
resize(env);
env.advance(1000);
const idle = env.iwLog.length === 0 && env.pending() === 0 && same(env.scrollToArgs, []);
click(env, 0);
env.dom();
env.advance(0);
env.iwLog.length = 0;
resize(env);
env.advance(1000);
check(idle && env.iwLog.length === 0 && st(env).open === true,
      "nothing chosen: a resize either way does nothing; a bubble open on a window that stays wide is left as it is");
const field = new El("input", { id: "input_14_16_3" }, env);
env.doc.body.appendChild(field);
const cdA = env.doc.activeElement;
env.modalOpen = true;
env.doc.activeElement = field;
env.phone = true;
resize(env);
env.advance(1000);
check(env.iwLog.length === 0 && inMap(env, cdA) && env.pending() === 1 && st(env).open === true,
      "narrowed under the Contact Dealer form: the bubble is left in the page - the link the form gives focus back to with it - and a look is pending");
env.modalOpen = false;
env.doc.activeElement = cdA;
env.advance(200);
check(same(env.iwLog, [["close"]]) && st(env).open === false && env.doc.activeElement === nameOf(cs[0]) && env.pending() === 0,
      "  ... the form closed, focus given back to that link: the bubble goes within 0.2 s, and focus moves on to the card's name");
env = boot({ n: 4 });
cs = cards(env);
click(env, 0);
env.dom();
env.advance(0);
env.iwLog.length = 0;
env.mapDiv.clientWidth = 0;
env.phone = true;
resize(env);
env.advance(1000);
check(env.iwLog.length === 0 && st(env).open === true, "the map not laid out - 0 px wide: its bubble left as it was");
env = boot({ n: 4 });
cs = cards(env);
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
env.phone = true;
resize(env);
env.advance(200);
check(env.googleFocusMoves === 1 && env.doc.activeElement === boxG && same(boxG.focusCalls, [{ preventScroll: true }]) && env.ctx.pageYOffset === 0 &&
      same(env.scrollToArgs, [{ left: 0, top: 0, behavior: "instant" }]) && nameOf(cs[0]).focusCalls.length === 0 && same(marked(env), ["101"]),
      "Google sending focus back to the pin as the bubble goes, scrolling to it: focus that was in the search box put back there - not taken for the card - and the page put back at once, whatever its own scroll-behavior");
env = boot({ n: 4 });
cs = cards(env);
env.googleFocus = true;
const boxS = new El("input", { id: "addressInput" }, env);
boxS.top = 700;
env.doc.body.appendChild(boxS);
env.doc.activeElement = boxS;
click(env, 1);
env.dom();
env.advance(0);
env.phone = true;
resize(env);
env.advance(200);
check(env.googleFocusMoves === 1 && boxS.blurCalls === 1 && env.doc.activeElement === nameOf(cs[1]) && st(env).back === boxS && env.ctx.pageYOffset === 0,
      "  ... and with focus in the bubble as it goes: let go where Google put it - the search box - for the card's name, still bound back to the box; the page where it was");
env = boot({ n: 4 });
cs = cards(env);
env.googleFocus = true;
env.noScrollOptions = true;
const pinO = new El("div", { id: "pin-button" }, env);
pinO.top = 500;
env.doc.body.appendChild(pinO);
env.doc.activeElement = pinO;
click(env, 0);
env.dom();
env.advance(0);
env.phone = true;
resize(env);
env.advance(200);
check(same(env.scrollToArgs, [{ left: 0, top: 0, behavior: "instant" }, [0, 0]]) && env.ctx.pageYOffset === 0,
      "a browser whose scrollTo() takes no options: the page, moved, put back with two numbers");
env = boot({ phone: true, n: 4 });
cs = cards(env);
click(env, 1);
env.advance(2000);
env.doc.fullscreenElement = env.mapDiv;
(env.doc.listeners.fullscreenchange || []).forEach((fn) => fn({ type: "fullscreenchange" }));
env.advance(199);
const early = env.opens;
env.advance(1);
check(early === 0 && env.opens === 1 && st(env).open === true && cur(env) === "102",
      "a phone's map taken full screen with a dealer chosen: 0.2 s later that dealer's bubble opens there - full screen is not a phone's");
env.dom();
env.advance(0);
env.doc.fullscreenElement = null;
(env.doc.listeners.webkitfullscreenchange || []).forEach((fn) => fn({ type: "webkitfullscreenchange" }));
env.advance(200);
check(st(env).open === false && st(env).pinned === true && same(marked(env), ["102"]) && env.iwLog.filter((x) => x[0] === "close").length === 1,
      "  ... and out of full screen again - Safari's event: the bubble goes, the choice stays on its card");
env.iw.isOpen = false;
env.trigger(env.iw, "close");
check(st(env).pinned === true && cur(env) === "102" && same(marked(env), ["102"]),
      "a close event from Google that arrives when no bubble is up any more is nobody's: the choice stays");
env = boot({ n: 4 });
cs = cards(env);
click(env, 0);
env.dom();
env.advance(0);
env.iwLog.length = 0;
env.phone = true;
click(env, 1);
check(same(env.iwLog, [["close"]]) && st(env).open === false && cur(env) === "102" && same(marked(env), ["102"]) && same(flashed(env), ["102"]) &&
      g(env, 0).getIcon() !== HOVER && g(env, 1).getIcon() === HOVER,
      "a pin chosen on a window just narrowed, the last bubble still up: that bubble goes quietly, and the choice is the new pin's, on its card");
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
/* The results as SLP draws them: one card per dealer, in #map_sidebar -
   cards() and marked() are above, with the other helpers, from r3. */
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
env = boot();
cs = cards(env);
click(env, 0);
env.dom();
env.advance(0);
env.phone = true;
(env.winL.resize || []).forEach((fn) => fn({ type: "resize" }));
env.advance(200);
check(same(marked(env), ["101"]) && st(env).open === false && st(env).pinned === true &&
      env.iwLog.filter((x) => x[0] === "close").length === 1 && env.opens === 1,
      "r3: a window narrowed to a phone's - the bubble closed, not reopened, and its card stays marked: that close is not the visitor's");
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

/* ------------------------------------------------------ the bubble's width */

section("bubble width", 7, () => {
console.log("");
console.log("  THE BUBBLE'S WIDTH (Part 4e) - the week measured open once, and that width kept as the bubble's least");
let env = boot();
click(env, 0);
env.dom();
check(env.bubble.style.minWidth === "281px" && env.opened === 1 && env.det.open === false && env.bubble.avalonHeld === true,
      "as a bubble arrives: its week opened once to measure - 280.1 px - shut again, and 281 px kept as the bubble's least width");
env.trigger(env.iw, "domready");
check(env.opened === 1, "a second domready for the same content: not measured again");
click(env, 1);
env.dom();
check(env.opened === 2 && env.bubble.style.minWidth === "281px" && env.bubble.id === "slp_info_bubble_102",
      "the next dealer's bubble is measured for itself");
env = boot({ nohours: true });
click(env, 0);
let threw = false;
try {
  env.dom();
} catch (e) {
  threw = true;
}
check(!threw && env.det === null && env.bubble.style.minWidth === undefined && env.opened === 0,
      "a dealer with no hours: nothing to measure, no width set, nothing thrown");
env = boot();
env.weekOpen = true;
click(env, 0);
env.dom();
check(env.det.open === true && env.bubble.style.minWidth === undefined && env.bubble.avalonHeld === undefined && env.opened === 0,
      "a week that is open when the content arrives: left open, and not measured over");
env = boot();
env.noMeasure = true;
click(env, 0);
threw = false;
try {
  env.dom();
} catch (e) {
  threw = true;
}
check(!threw && env.det.open === false && env.bubble.style.minWidth === undefined,
      "a bubble that cannot be measured: its week shut again, no width set, nothing thrown");
env = boot();
env.wOpen = 0;
click(env, 0);
env.dom();
check(env.bubble.style.minWidth === undefined && same(Object.keys(env.M), ["controls", "attach", "bind", "show", "close", "ring", "state"]),
      "a width of nothing - a bubble not laid out - sets none; and the block exports no more(): the fade is gone");
});

console.log("");
console.log("  " + pass + " passed, " + fail + " failed, " + (pass + fail) + " total");
console.log("");
process.exit(fail ? 1 : 0);
