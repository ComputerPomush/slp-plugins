/**
 * suite-map.js - validates slp_avalon/assets/js/slp_avalon.js, v0.0.27 Part 4c.
 * r2, v0.0.27 Part 4d: the chosen card, the bubble's fade, the placeholder;
 * fa() gone with the icons.
 * r3, v0.0.27 Part 4e: no bubble on a phone - the choice goes to the card;
 * the card brought into view; the bubble's width held; the fade and the
 * phone's minimum width gone.
 * r4, v0.0.27 Part 4f: the map's buttons - Reset, + and - - where Google's
 * zoom stood; numbered pins and cards; focus to the card after Google's
 * close event when a window narrows.
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
 *   buttons      Part 4f: Google's zoom off; one control of ours at the
 *                map's right foot - Reset, then + over -; on a phone Reset
 *                alone in the top right corner; moved as the window or
 *                full screen changes, focus kept; none of ours, and
 *                Google's zoom back, where the map has no controls
 *   reset        Part 4f: the view the latest search drew, read once
 *                markers_dropped has been handled; back to it at once, or
 *                the dealers fitted again by SLP's rules and v0.0.25's
 *                zoom out on a map of another size; the view only
 *   + and -      Part 4f: one zoom each, dimmed (aria-disabled) at either
 *                end - SLP's minZoom, the map type's most
 *   numbers      Part 4f: where avalon_map_number_icon is set, pins and
 *                cards 1 to n in SLP's order - the numbered pin with a
 *                black number at (15, 15), lit as the numbered pin lit,
 *                titled "Number n, <dealer>"; the card's heading "Number
 *                n, " before the name, the word and comma hidden
 *   focus        Part 4f: a window narrowed with focus in a chosen
 *                dealer's bubble - to the card once Google's close event
 *                has come, or 0.3 s on
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
 * r4. Part 4f's 17 edits, all inside the block, are reversed before Part
 * 4e's, and the block must then be Part 4e's byte for byte - aa475332,
 * 43,447 bytes - so r3's evidence carries forward for all that Part 4f did
 * not touch. Three of r3's checks wait 0.3 s more for the card's focus
 * (r3's fake sends Google's close event inside close(), before Part 4f
 * listens for it); the controls' checks now see Google's zoom off. The
 * fake map gained what Part 4f uses: corners to put controls in, with
 * index; a zoom that changes and says so; a centre; fitBounds(); the map
 * type's most; labels and titles on pins; a close event 23 ms late, and
 * Google's focus back with it, where a section asks for them.
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
const BLOCK_PIN = { md5: "c1971822d5d456658c18cc0db0d99898", len: 60337 };
const P4E_BLOCK = { md5: "aa47533201ca436601f1a438be6b8d71", len: 43447 };
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
/* r4. Part 4f's edits, [what it is, what Part 4e had, what Part 4f wrote],
   in the order build-v027-part4f.py makes them; all inside the block.
   Written LF, read CRLF. */
const P4F_EDITS = [
  ["slp_avalon.js: avalon_map's header - a numbered pin lights as NUMBERS says",
   "   * only raised.\n",
   "   * only raised. A numbered pin (Part 4f) lights as NUMBERS, below, says.\n"],
  ["slp_avalon.js: avalon_map's header - the zoom is ours from Part 4f",
   "   * Satellite.\n",
   "   * Satellite. From Part 4f the zoom is ours: THE MAP'S BUTTONS, below.\n"],
  ["slp_avalon.js: avalon_map's header - the map's buttons, numbers, focus after narrowing",
   "   * Part 4c's Font Awesome labels went with Part 4d: words on every\n",
   "   * THE MAP'S BUTTONS (Part 4f). Reset, and our own + and -, where\n" +
   "   * Google's zoom stood. Google's zoom is off (controls()): nothing can\n" +
   "   * sit beside Google's own, and Reset belongs beside - (the owner,\n" +
   "   * 2026-10-07). The Pegman stays Google's, above them. On a desktop or a\n" +
   "   * tablet they are one control at the right foot of the map: Reset, then\n" +
   "   * + over -, their feet in line, in Google's look (avalon-hours.css). On\n" +
   "   * a phone Reset stands alone in the top right corner, Google's full\n" +
   "   * screen under it, and + and - stay at the foot; a window resized\n" +
   "   * across a phone's size, or full screen coming or going, moves it. Reset\n" +
   "   * shows once a search has drawn a view, and takes the map back to it at\n" +
   "   * once: the view as SLP and v0.0.25 drew it - the dealers and the\n" +
   "   * search's point fitted, then one zoom out - read as soon as\n" +
   "   * markers_dropped has been handled (the probe read it there and at the\n" +
   "   * map's idle: the same). On a map of another size since, the dealers\n" +
   "   * are fitted again by the same rules, so that every one shows. The\n" +
   "   * map's view only: a dealer chosen, a lit pin, an open bubble, the map\n" +
   "   * type and the list stay as they are; a search refused, or one that\n" +
   "   * failed, keeps the last view. + and - zoom by one, between the least\n" +
   "   * zoom the map allows (SLP's 1) and the most its map type does; at\n" +
   "   * either end the button is dimmed - aria-disabled, so focus stays on it.\n" +
   "   * Their names are Google's, Zoom in and Zoom out; Reset's is \"Reset map\n" +
   "   * view\". Where the map has no controls to add to, Google's zoom comes\n" +
   "   * back and there is no Reset.\n" +
   "   *\n" +
   "   * NUMBERS (Part 4f). Where slp_avalon sets avalon_map_number_icon, each\n" +
   "   * search's dealers are numbered 1 to n in SLP's order - the cards' as\n" +
   "   * drawn - and a number stays with its dealer when Part 4e moves a card.\n" +
   "   * A pin becomes that icon with its number on the head, at (15, 15) of\n" +
   "   * the 30 x 40 art: black, the page's own font, bold, 13 px - 4.80:1 on\n" +
   "   * the pink pin, 21:1 on the white one (black: the owner, 2026-10-08,\n" +
   "   * for WCAG AA). Lit, it is avalon_map_number_hover_icon with the same\n" +
   "   * number; without that option, only raised. Its title, which Google\n" +
   "   * makes its name, is \"Number 4, <dealer>\". The card's heading starts\n" +
   "   * with the number in a disc, and reads \"Number 4, \" before the name:\n" +
   "   * \"Number \" and the comma are hidden on screen; the link is as it was.\n" +
   "   * The bubble and the search's own pin have no number. Without the\n" +
   "   * option nothing is numbered and the pins are SLP's.\n" +
   "   *\n" +
   "   * FOCUS AFTER NARROWING (Part 4f). A window narrowed to a phone's with\n" +
   "   * focus in a chosen dealer's bubble gives that focus to the dealer's\n" +
   "   * card once Google's close event has come - after close() has returned,\n" +
   "   * 23 ms later on DEV - or 0.3 s on, whichever is first: so that\n" +
   "   * nothing Google does with focus as its bubble goes comes after it.\n" +
   "   *\n" +
   "   * Part 4c's Font Awesome labels went with Part 4d: words on every\n"],
  ["slp_avalon.js: HEAD - where a numbered pin's number sits",
   "    var PHONE = \"(max-width: 767px), (max-height: 500px)\";\n" +
   "\n",
   "    var PHONE = \"(max-width: 767px), (max-height: 500px)\";\n" +
   "    //Part 4f. Where a numbered pin's number sits: the middle of the head\n" +
   "    //of the 30 x 40 art, x and y, in px from its top left. Measured on DEV\n" +
   "    //(the probe, 2026-10-08): the number's centre 0.2 px from it.\n" +
   "    var HEAD = 15;\n" +
   "\n"],
  ["slp_avalon.js: what Part 4f keeps - the numbered pins, their font, the view, the buttons, the pending focus",
   "      icon: null          //the hover icon, resolved; \"\" for none\n",
   "      icon: null,         //the hover icon, resolved; \"\" for none\n" +
   "      nicon: null,        //Part 4f. the numbered pin, resolved; \"\" for none, and no numbers\n" +
   "      nlit: null,         //Part 4f. the numbered pin lit, resolved; \"\" for none\n" +
   "      font: null,         //Part 4f. the numbers' font: the page's\n" +
   "      view: null,         //Part 4f. { c, z, w, h, fit, n }: the view the latest search drew\n" +
   "      vt: 0,              //Part 4f. the pending read of that view\n" +
   "      ctl: null,          //Part 4f. the map's buttons: { group, zoom, reset, zin, zout, corner, at }\n" +
   "      closing: 0          //Part 4f. the pending focus to the card, after Google's close event\n"],
  ["slp_avalon.js: the view, Reset, + and -, the buttons made and placed; to_card() after Google's close",
   "    function controls(o) {\n",
   "    //Part 4f. THE MAP'S BUTTONS, in the header above: the view the latest\n" +
   "    //search drew, read once markers_dropped has been handled - SLP's\n" +
   "    //fitBounds() and zoom tweak, then v0.0.25's zoom out by one. fit: the\n" +
   "    //search found dealers, so SLP's bounds are its own.\n" +
   "    function take() {\n" +
   "      st.vt = 0;\n" +
   "      var cm = st.cm;\n" +
   "      var g = cm && cm.gmap;\n" +
   "      var d = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n" +
   "      var c = g && typeof g.getCenter === \"function\" ? g.getCenter() : null;\n" +
   "      var z = g && typeof g.getZoom === \"function\" ? g.getZoom() : NaN;\n" +
   "      if (!d || !c || typeof z !== \"number\" || isNaN(z)) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var n = (cm.markers || []).length;\n" +
   "      st.view = { c: c, z: z, w: d.clientWidth, h: d.clientHeight, fit: n > 0 && !!cm.bounds, n: n };\n" +
   "      place_reset();\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. The dealers fitted again, by SLP's rules (slp_core.js\n" +
   "    //putMarkers()) and then v0.0.25's zoom out by one.\n" +
   "    function refit(cm, n) {\n" +
   "      var g = cm.gmap;\n" +
   "      var o = (typeof slplus !== \"undefined\" && slplus && slplus.options) || {};\n" +
   "      var z;\n" +
   "      g.fitBounds(cm.bounds);\n" +
   "      if (o.no_autozoom === \"1\") {\n" +
   "        z = parseInt(o.zoom_level, 10);\n" +
   "      } else {\n" +
   "        z = g.getZoom() - (parseInt(o.zoom_tweak, 10) || 0);\n" +
   "        if (n < 2) {\n" +
   "          z = Math.min(z, 15);\n" +
   "        }\n" +
   "      }\n" +
   "      if (!isNaN(z)) {\n" +
   "        g.setZoom(z);\n" +
   "      }\n" +
   "      g.setZoom(g.getZoom() - 1);\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. Reset: back to that view, at once - on a map of another size\n" +
   "    //since, the dealers fitted again, and that view kept from then on.\n" +
   "    function reset() {\n" +
   "      var v = st.view;\n" +
   "      var cm = st.cm;\n" +
   "      var g = cm && cm.gmap;\n" +
   "      var d = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n" +
   "      if (!v || !d) {\n" +
   "        return;\n" +
   "      }\n" +
   "      if (v.fit && (d.clientWidth !== v.w || d.clientHeight !== v.h)) {\n" +
   "        refit(cm, v.n);\n" +
   "        take();\n" +
   "        return;\n" +
   "      }\n" +
   "      g.setCenter(v.c);\n" +
   "      g.setZoom(v.z);\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. The least zoom the map allows, and the most its map type\n" +
   "    //does: SLP builds the map with minZoom 1; a type says its own most.\n" +
   "    function limits(g) {\n" +
   "      var lo = 0;\n" +
   "      var hi = 22;\n" +
   "      try {\n" +
   "        var t = g.mapTypes && typeof g.mapTypes.get === \"function\" ? g.mapTypes.get(g.getMapTypeId()) : null;\n" +
   "        var mn = typeof g.get === \"function\" ? g.get(\"minZoom\") : undefined;\n" +
   "        var mx = typeof g.get === \"function\" ? g.get(\"maxZoom\") : undefined;\n" +
   "        lo = typeof mn === \"number\" ? mn : (t && typeof t.minZoom === \"number\" ? t.minZoom : 0);\n" +
   "        hi = typeof mx === \"number\" ? mx : (t && typeof t.maxZoom === \"number\" ? t.maxZoom : 22);\n" +
   "      } catch (x) {\n" +
   "        //A map that cannot say: 0 to 22, Google's own range.\n" +
   "      }\n" +
   "      return { min: lo, max: hi };\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. + and -: one zoom in or out, never past either end.\n" +
   "    function zoom_by(dz) {\n" +
   "      var g = st.cm && st.cm.gmap;\n" +
   "      var z = g && typeof g.getZoom === \"function\" ? g.getZoom() : NaN;\n" +
   "      if (typeof z !== \"number\" || isNaN(z)) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var lim = limits(g);\n" +
   "      var to = Math.max(lim.min, Math.min(lim.max, Math.round(z) + dz));\n" +
   "      if (to !== z) {\n" +
   "        g.setZoom(to);\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. A button dimmed at its end of the zoom - or not.\n" +
   "    function dim(b, off) {\n" +
   "      if (off) {\n" +
   "        b.setAttribute(\"aria-disabled\", \"true\");\n" +
   "      } else if (typeof b.removeAttribute === \"function\") {\n" +
   "        b.removeAttribute(\"aria-disabled\");\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. + and - dimmed at either end, as the zoom or the map type\n" +
   "    //changes.\n" +
   "    function ends() {\n" +
   "      var c = st.ctl;\n" +
   "      var g = st.cm && st.cm.gmap;\n" +
   "      var z = g && typeof g.getZoom === \"function\" ? g.getZoom() : NaN;\n" +
   "      if (!c || typeof z !== \"number\" || isNaN(z)) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var lim = limits(g);\n" +
   "      dim(c.zin, z >= lim.max);\n" +
   "      dim(c.zout, z <= lim.min);\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. An element of ours, by tag and class.\n" +
   "    function make(tag, name) {\n" +
   "      var n = document.createElement(tag);\n" +
   "      n.className = name;\n" +
   "      return n;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. A button of ours: its name, which is its tooltip too, and\n" +
   "    //the text it shows, if any. type=\"button\": nothing here submits.\n" +
   "    function button(name, label, text) {\n" +
   "      var b = make(\"button\", name);\n" +
   "      b.setAttribute(\"type\", \"button\");\n" +
   "      b.setAttribute(\"aria-label\", label);\n" +
   "      b.setAttribute(\"title\", label);\n" +
   "      if (text) {\n" +
   "        b.appendChild(document.createTextNode(text));\n" +
   "      }\n" +
   "      return b;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. One of our controls into the list Google lays a corner of\n" +
   "    //the map out from, or out of it - and out and in again when it is\n" +
   "    //there already, so that Google lays it out at its new size. Focus in\n" +
   "    //it, which the move takes away, is given back.\n" +
   "    function dock(pos, n, on) {\n" +
   "      var g = st.cm.gmap;\n" +
   "      var list = g.controls[pos];\n" +
   "      var a = document.activeElement;\n" +
   "      var had = !!a && a !== document.body && typeof n.contains === \"function\" && n.contains(a);\n" +
   "      var all = list.getArray();\n" +
   "      for (var i = all.length - 1; i >= 0; i--) {\n" +
   "        if (all[i] === n) {\n" +
   "          list.removeAt(i);\n" +
   "        }\n" +
   "      }\n" +
   "      if (on) {\n" +
   "        list.push(n);\n" +
   "      }\n" +
   "      if (had && document.activeElement !== a && document.body.contains(a)) {\n" +
   "        try {\n" +
   "          a.focus({ preventScroll: true });\n" +
   "        } catch (x) {\n" +
   "          //Refused: focus stays where the move left it.\n" +
   "        }\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. Reset where the layout wants it: in our group at the foot,\n" +
   "    //before + and -; on a phone alone, top right; nowhere until a search\n" +
   "    //has drawn a view to go back to.\n" +
   "    function place_reset() {\n" +
   "      var c = st.ctl;\n" +
   "      if (!c) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var at = !st.view ? \"\" : phone() ? \"corner\" : \"group\";\n" +
   "      if (at === c.at) {\n" +
   "        return;\n" +
   "      }\n" +
   "      var P = google.maps.ControlPosition;\n" +
   "      var was = c.at;\n" +
   "      var a = document.activeElement;\n" +
   "      var had = a === c.reset;\n" +
   "      if (c.reset.parentNode) {\n" +
   "        c.reset.parentNode.removeChild(c.reset);\n" +
   "      }\n" +
   "      if (at === \"group\") {\n" +
   "        c.group.insertBefore(c.reset, c.zoom);\n" +
   "      } else if (at === \"corner\") {\n" +
   "        c.corner.appendChild(c.reset);\n" +
   "      }\n" +
   "      c.at = at;\n" +
   "      if (was === \"corner\" || at === \"corner\") {\n" +
   "        dock(P.TOP_RIGHT, c.corner, at === \"corner\");\n" +
   "      }\n" +
   "      if (was === \"group\" || at === \"group\") {\n" +
   "        dock(P.RIGHT_BOTTOM, c.group, true);\n" +
   "      }\n" +
   "      if (had && at && document.activeElement !== c.reset) {\n" +
   "        try {\n" +
   "          c.reset.focus({ preventScroll: true });\n" +
   "        } catch (x) {\n" +
   "          //As above.\n" +
   "        }\n" +
   "      }\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. The buttons made, once, as the map is built: + and - at the\n" +
   "    //map's right foot, where Google's zoom stood - index -1 puts them under\n" +
   "    //Google's other controls there, the Pegman - with Reset to come.\n" +
   "    function buttons(cm) {\n" +
   "      var g = cm.gmap;\n" +
   "      var P = google.maps.ControlPosition;\n" +
   "      if (!P || !g.controls || !g.controls[P.RIGHT_BOTTOM] || !g.controls[P.TOP_RIGHT] ||\n" +
   "          typeof g.controls[P.RIGHT_BOTTOM].getArray !== \"function\") {\n" +
   "        if (typeof g.setOptions === \"function\") {\n" +
   "          g.setOptions({ zoomControl: true });\n" +
   "        }\n" +
   "        return;\n" +
   "      }\n" +
   "      var c = {\n" +
   "        group: make(\"div\", \"avalon-mapctl\"),\n" +
   "        zoom: make(\"div\", \"avalon-mapctl__zoom\"),\n" +
   "        corner: make(\"div\", \"avalon-mapctl avalon-mapctl--corner\"),\n" +
   "        reset: button(\"avalon-mapctl__reset\", \"Reset map view\", \"Reset\"),\n" +
   "        zin: button(\"avalon-mapctl__in\", \"Zoom in\", \"\"),\n" +
   "        zout: button(\"avalon-mapctl__out\", \"Zoom out\", \"\"),\n" +
   "        at: \"\"\n" +
   "      };\n" +
   "      c.zoom.appendChild(c.zin);\n" +
   "      c.zoom.appendChild(make(\"div\", \"avalon-mapctl__rule\"));\n" +
   "      c.zoom.appendChild(c.zout);\n" +
   "      c.group.appendChild(c.zoom);\n" +
   "      c.group.index = -1;\n" +
   "      c.reset.addEventListener(\"click\", reset, false);\n" +
   "      c.zin.addEventListener(\"click\", function () {\n" +
   "        zoom_by(1);\n" +
   "      }, false);\n" +
   "      c.zout.addEventListener(\"click\", function () {\n" +
   "        zoom_by(-1);\n" +
   "      }, false);\n" +
   "      st.ctl = c;\n" +
   "      g.controls[P.RIGHT_BOTTOM].push(c.group);\n" +
   "      google.maps.event.addListener(g, \"zoom_changed\", ends);\n" +
   "      google.maps.event.addListener(g, \"maptypeid_changed\", ends);\n" +
   "      ends();\n" +
   "      place_reset();\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. FOCUS AFTER NARROWING, in the header above: to_card() once\n" +
   "    //Google's close event has come, or CLOSE_MS on - and only while that\n" +
   "    //dealer is still the one chosen, with no bubble.\n" +
   "    function to_card_after(iw, e) {\n" +
   "      var done = false;\n" +
   "      var h = null;\n" +
   "      var go = function () {\n" +
   "        if (done) {\n" +
   "          return;\n" +
   "        }\n" +
   "        done = true;\n" +
   "        clearTimeout(st.closing);\n" +
   "        st.closing = 0;\n" +
   "        if (h) {\n" +
   "          google.maps.event.removeListener(h);\n" +
   "        }\n" +
   "        if (st.current === e && st.pinned && !st.open) {\n" +
   "          to_card(e);\n" +
   "        }\n" +
   "      };\n" +
   "      clearTimeout(st.closing);\n" +
   "      try {\n" +
   "        h = google.maps.event.addListenerOnce(iw, \"close\", function () {\n" +
   "          setTimeout(go, 0);\n" +
   "        });\n" +
   "      } catch (x) {\n" +
   "        h = null;\n" +
   "      }\n" +
   "      st.closing = setTimeout(go, CLOSE_MS);\n" +
   "    }\n" +
   "\n" +
   "    function controls(o) {\n"],
  ["slp_avalon.js: controls() - Google's zoom off: ours stands where it stood",
   "        o.zoomControl = true;\n",
   "        //Part 4f. Ours instead: THE MAP'S BUTTONS, in the header above.\n" +
   "        o.zoomControl = false;\n"],
  ["slp_avalon.js: the icon options read in one place; the numbered pins, their font, a dealer numbered",
   "    function hover_icon() {\n" +
   "      if (st.icon === null) {\n" +
   "        var v = \"\";\n" +
   "        try {\n" +
   "          v = String((slplus.options && slplus.options.avalon_map_hover_icon) || \"\");\n" +
   "        } catch (x) {\n" +
   "          v = \"\";\n" +
   "        }\n" +
   "        if (v) {\n" +
   "          //A path from the site's root, resolved against the page.\n" +
   "          var a = document.createElement(\"a\");\n" +
   "          a.href = v;\n" +
   "          v = a.href;\n" +
   "        }\n" +
   "        st.icon = v;\n" +
   "      }\n" +
   "      return st.icon;\n",
   "    //Part 4f. One of slp_avalon's icon options, resolved; \"\" for none.\n" +
   "    function option_icon(name) {\n" +
   "      var v = \"\";\n" +
   "      try {\n" +
   "        v = String((slplus.options && slplus.options[name]) || \"\");\n" +
   "      } catch (x) {\n" +
   "        v = \"\";\n" +
   "      }\n" +
   "      if (v) {\n" +
   "        //A path from the site's root, resolved against the page.\n" +
   "        var a = document.createElement(\"a\");\n" +
   "        a.href = v;\n" +
   "        v = a.href;\n" +
   "      }\n" +
   "      return v;\n" +
   "    }\n" +
   "\n" +
   "    function hover_icon() {\n" +
   "      if (st.icon === null) {\n" +
   "        st.icon = option_icon(\"avalon_map_hover_icon\");\n" +
   "      }\n" +
   "      return st.icon;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. NUMBERS, in the header above: the numbered pin, or \"\" - and\n" +
   "    //then no numbers at all.\n" +
   "    function numbered() {\n" +
   "      if (st.nicon === null) {\n" +
   "        st.nicon = option_icon(\"avalon_map_number_icon\");\n" +
   "        st.nlit = st.nicon ? option_icon(\"avalon_map_number_hover_icon\") : \"\";\n" +
   "      }\n" +
   "      return st.nicon;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. A numbered pin's icon: the art, with its number's centre in\n" +
   "    //the middle of the head.\n" +
   "    function pin(url) {\n" +
   "      var o = { url: url };\n" +
   "      try {\n" +
   "        o.labelOrigin = new google.maps.Point(HEAD, HEAD);\n" +
   "      } catch (x) {\n" +
   "        //No Point: the number where Google centres a label.\n" +
   "      }\n" +
   "      return o;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. The page's own font - its body's - for the numbers.\n" +
   "    function font() {\n" +
   "      if (st.font === null) {\n" +
   "        var f = \"\";\n" +
   "        try {\n" +
   "          f = String(window.getComputedStyle(document.body).fontFamily || \"\");\n" +
   "        } catch (x) {\n" +
   "          f = \"\";\n" +
   "        }\n" +
   "        st.font = (f ? f + \", \" : \"\") + \"Arial, sans-serif\";\n" +
   "      }\n" +
   "      return st.font;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. Hidden on screen, read by a screen reader.\n" +
   "    function unseen(t) {\n" +
   "      var s = make(\"span\", \"avalon-num__sr\");\n" +
   "      s.appendChild(document.createTextNode(t));\n" +
   "      return s;\n" +
   "    }\n" +
   "\n" +
   "    //Part 4f. A dealer numbered: its pin, then its card. A card numbered\n" +
   "    //before - the same results bound again - is numbered afresh.\n" +
   "    function number(e, n) {\n" +
   "      var g = e.marker.__gmarker;\n" +
   "      var name = name_of(e.info);\n" +
   "      e.n = n;\n" +
   "      try {\n" +
   "        g.setIcon(pin(st.nicon));\n" +
   "        g.setLabel({ text: String(n), color: \"#000000\", fontFamily: font(), fontSize: \"13px\", fontWeight: \"700\",\n" +
   "                     className: \"avalon-pin-num\" });\n" +
   "        g.setTitle(\"Number \" + n + (name ? \", \" + name : \"\"));\n" +
   "      } catch (x) {\n" +
   "        //A pin that takes none of it stays as SLP drew it.\n" +
   "      }\n" +
   "      var c = card_of(e);\n" +
   "      var h = c && typeof c.querySelector === \"function\" ? c.querySelector(\".store_locator_name\") : null;\n" +
   "      if (!h || typeof h.insertBefore !== \"function\") {\n" +
   "        return;\n" +
   "      }\n" +
   "      var old = h.querySelector(\".avalon-num\");\n" +
   "      if (old && old.parentNode) {\n" +
   "        old.parentNode.removeChild(old);\n" +
   "      }\n" +
   "      var s = make(\"span\", \"avalon-num\");\n" +
   "      s.appendChild(unseen(\"Number \"));\n" +
   "      s.appendChild(document.createTextNode(String(n)));\n" +
   "      s.appendChild(unseen(\", \"));\n" +
   "      h.insertBefore(s, h.firstChild);\n"],
  ["slp_avalon.js: lit()'s comment - a numbered pin lit keeps its number",
   "    function lit(e, on) {\n",
   "    //Part 4f: a numbered pin, the numbered pin lit, its number kept.\n" +
   "    function lit(e, on) {\n"],
  ["slp_avalon.js: lit() - a numbered pin lights as the numbered pin lit",
   "      var url = hover_icon();\n",
   "      var url = e.n ? st.nlit : hover_icon();\n"],
  ["slp_avalon.js: lit() - the numbered pin lit, its number where it was",
   "          g.setIcon(url);\n",
   "          g.setIcon(e.n ? pin(url) : url);\n"],
  ["slp_avalon.js: resized() - Reset placed first",
   "    function resized() {\n" +
   "      st.rs = 0;\n",
   "    //\n" +
   "    //Part 4f. Reset goes where the layout now wants it first, whatever\n" +
   "    //else there is to do; the focus the bubble had goes to the card once\n" +
   "    //Google's close event has come (FOCUS AFTER NARROWING, above).\n" +
   "    function resized() {\n" +
   "      st.rs = 0;\n" +
   "      place_reset();\n"],
  ["slp_avalon.js: resized() - focus to the card once Google's close event has come",
   "        to_card(e);\n" +
   "      }\n",
   "        to_card_after(cm.infowindow, e);\n" +
   "      }\n"],
  ["slp_avalon.js: attach() - the map's buttons, once",
   "        });\n" +
   "    }\n",
   "        });\n" +
   "      //Part 4f. The map's buttons, once.\n" +
   "      buttons(cm);\n" +
   "    }\n"],
  ["slp_avalon.js: bind()'s comment - numbers, and the view to reset to",
   "    //id - SLP's own order only when an id is missing.\n",
   "    //id - SLP's own order only when an id is missing. Part 4f: numbered,\n" +
   "    //in SLP's order, where there are numbers; the view to reset to read\n" +
   "    //once markers_dropped has been handled.\n"],
  ["slp_avalon.js: bind() - a count for the numbers",
   "      for (var i = 0; i < markers.length; i++) {\n",
   "      var n = 0;\n" +
   "      for (var i = 0; i < markers.length; i++) {\n"],
  ["slp_avalon.js: bind() - each dealer numbered; the pin it lights to preloaded; the view read after markers_dropped",
   "      }\n" +
   "      if (hover_icon() && typeof Image === \"function\") {\n" +
   "        new Image().src = hover_icon();\n" +
   "      }\n",
   "        if (numbered()) {\n" +
   "          number(e, ++n);\n" +
   "        }\n" +
   "      }\n" +
   "      var icon = numbered() ? st.nlit : hover_icon();\n" +
   "      if (icon && typeof Image === \"function\") {\n" +
   "        new Image().src = icon;\n" +
   "      }\n" +
   "      clearTimeout(st.vt);\n" +
   "      st.vt = setTimeout(take, 0);\n"]
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
section("identity", 12, () => {
  const a = src.indexOf(BLOCK_START);
  const b = src.indexOf(BLOCK_END, a);
  check(a > 0 && b > a && src.indexOf(BLOCK_START, a + 1) < 0,
        "the Part 4f block sits once, where enable_on_mouse_hover_for_markers() was");
  const blk = a > 0 && b > a ? src.slice(a, b + "  })();\r\n".length) : "";
  check(md5of(blk) === BLOCK_PIN.md5 && Buffer.byteLength(blk, "latin1") === BLOCK_PIN.len,
        "the block is the one this suite was written against (" + BLOCK_PIN.md5 + ", " + BLOCK_PIN.len + " bytes)");
  /* r4: Part 4f's edits out first, the last made first. */
  let p4e = blk;
  let in4f = blk !== "";
  for (let i = P4F_EDITS.length - 1; i >= 0 && in4f; i--) {
    if (p4e.split(P4F_EDITS[i][2]).length - 1 !== 1) { in4f = false; break; }
    p4e = p4e.replace(P4F_EDITS[i][2], () => P4F_EDITS[i][1]);
  }
  check(in4f && md5of(p4e) === P4E_BLOCK.md5 && Buffer.byteLength(p4e, "latin1") === P4E_BLOCK.len,
        "Part 4f's " + P4F_EDITS.length + " edits in it, each there once when its turn comes, reversed: Part 4e's block (aa475332, 43,447 bytes)");
  /* r3: then Part 4e's, the last made first. */
  let p4d = in4f ? p4e : "";
  let in4e = in4f;
  for (let i = P4E_EDITS.length - 1; i >= 0 && in4e; i--) {
    if (p4d.split(P4E_EDITS[i][2]).length - 1 !== 1) { in4e = false; break; }
    p4d = p4d.replace(P4E_EDITS[i][2], () => P4E_EDITS[i][1]);
  }
  check(in4e && md5of(p4d) === P4D_BLOCK.md5 && Buffer.byteLength(p4d, "latin1") === P4D_BLOCK.len,
        "  ... and Part 4e's " + P4E_EDITS.length + " edits in that, each there once when its turn comes, reversed: Part 4d's block (68201cb5, 26,839 bytes)");
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

/* r4. Text, as the page has it: in an element, read through textContent. */
class Txt {
  constructor(t) { this.nodeType = 3; this.data = String(t); this.children = []; this.parentNode = null; }
  get textContent() { return this.data; }
  all() { return []; }
  contains() { return false; }
}

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
    if (ref === null || ref === undefined) { return this.appendChild(c); }
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
  /* r4 */
  removeAttribute(k) { delete this.attrs[k]; }
  removeChild(c) { this.take(c); return c; }
  get firstChild() { return this.children[0] || null; }
  get textContent() { return this.children.map((c) => c.textContent).join(""); }
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
    if (sel === ".sl_popup_contact_info" || sel === ".results_wrapper" || sel === ".avalon-hours__narrow" ||
        sel === ".store_locator_name" || sel === ".avalon-num") {
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
    createTextNode: (t) => new Txt(t),
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
  /* r4. Once, and taken off again: Part 4f waits for Google's close event. */
  gmaps.event.addListenerOnce = (obj, name, fn) => {
    const h = { fn: null, remove: () => { const l = (obj.__l || {})[name] || []; const i = l.indexOf(h.fn); if (i >= 0) { l.splice(i, 1); } } };
    h.fn = (a) => { h.remove(); fn(a); };
    listen(obj, name, h.fn);
    return h;
  };
  gmaps.event.removeListener = (h) => { if (h && typeof h.remove === "function") { h.remove(); } };
  gmaps.Point = function (x, y) { this.x = x; this.y = y; };
  /* r4. Where controls stand: Google's lists for two corners, and what
     goes in and out of them. A control put in is in the map, as Google
     puts it; taken out, out of the page - and focus in it with it. */
  if (!opts.noControls) {
    gmaps.ControlPosition = { TOP_RIGHT: 3, RIGHT_BOTTOM: 9 };
  }
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
      /* r4. Google's close event 23 ms late, as on DEV (the probe) - with
         Google's focus back where it was before the bubble opened, just
         before the event or just after it, where a section asks for that;
         or no event at all. */
      if (was && env.asyncClose) {
        if (env.asyncClose !== "never") {
          const prior = iw.prior;
          const back = () => {
            if (prior && prior !== body && body.all().indexOf(prior) >= 0 && doc.activeElement !== prior) {
              doc.activeElement = prior;
              env.lateFocusMoves = (env.lateFocusMoves || 0) + 1;
            }
          };
          env.setTimeout(() => {
            if (env.lateFocus) { back(); }
            env.trigger(iw, "close");
            if (env.lateFocusAfter) { back(); }
          }, 23);
        }
      } else if (was) { env.trigger(iw, "close"); }
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
  /* r4. A zoom that changes and says so; a centre; fitBounds() and the
     zoom it lands on; SLP's minZoom; the map types' most; setOptions(). */
  env.zoom = 9;
  env.center = { of: "start" };
  env.fits = [];
  env.setOpts = [];
  const list = () => {
    const a = [];
    const l = { log: [], getArray: () => a,
                push: (n) => { a.push(n); l.log.push("push"); mapDiv.appendChild(n); return a.length; },
                removeAt: (i) => { const n = a.splice(i, 1)[0]; l.log.push("removeAt"); if (n && n.parentNode) { n.parentNode.removeChild(n); } return n; } };
    return l;
  };
  env.gmap = { getDiv: () => mapDiv, getZoom: () => env.zoom, setZoom: (z) => { env.zoom = z; env.trigger(env.gmap, "zoom_changed"); },
               getCenter: () => env.center, setCenter: (c) => { env.center = c; },
               fitBounds: (b) => { env.fits.push(b); env.center = { of: "fitted" }; env.zoom = env.fitZoom === undefined ? 9 : env.fitZoom; },
               get: (k) => (k === "minZoom" ? 1 : undefined),
               mapTypes: { get: (id) => ({ roadmap: { minZoom: 0, maxZoom: 22 }, satellite: { minZoom: 0, maxZoom: 20 } })[id] },
               getMapTypeId: () => env.mapType || "roadmap",
               setOptions: (o) => { env.setOpts.push(JSON.parse(JSON.stringify(o))); },
               controls: opts.noControls ? undefined : { 3: list(), 9: list() },
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
                               paddingBottom: (n.style && n.style.paddingBottom) || "0px", fontFamily: n.fontFamily || "" }),
    matchMedia: (q) => {
      env.queries.push(q);
      return { matches: q === PHONE_Q ? env.phone : (q === "(prefers-reduced-motion: reduce)" ? env.calm : false), media: q };
    },
    navigator: { geolocation: { getCurrentPosition: () => {} } },
    location: { href: "https://example.test/find-a-dealer/", hash: "" },
    URL: URL,
    Image: function () { env.images.push(this); },
    slplus: { options: Object.assign({ hide_bubble: "0", zoom_level: "12", immediately_show_locations: "0", zoom_tweak: "0", no_autozoom: "0",
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
  let lab = null;
  let title = "as SLP gave it";
  const g = {
    label: label,
    sets: [],
    getPosition: () => ({ of: label }),
    getIcon: () => ic,
    setIcon: (v) => { g.sets.push(["icon", v]); ic = v; },
    getZIndex: () => z,
    setZIndex: (v) => { g.sets.push(["z", v]); z = v; },
    /* r4 */
    getLabel: () => lab,
    setLabel: (v) => { g.sets.push(["label", v]); lab = v; },
    getTitle: () => title,
    setTitle: (v) => { g.sets.push(["title", v]); title = v; }
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
    createMarkerContent: (info) => ({ name: info.name, id: info.id, url: info.url, directions: !opts.nodirections, hours: !opts.nohours }),
    bounds: { of: "SLP's bounds" }
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
  const a = new El("a", { "class": "name-link" }, env);
  a.appendChild(new Txt("Dealer & Sons " + m.__location_id));
  h.appendChild(a);
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
check(r === o && o.cameraControl === false && o.zoomControl === false && o.mapTypeControl === true &&
      o.streetViewControl === true && o.fullscreenControl === true && o.zoom === 5,
      "controls(): Map and Satellite, Street View, full screen on; camera off, and r4, Google's zoom off - ours stands there; nothing else touched");
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
      built.options.zoomControl === false && built.options.streetViewControl === true && built.options.fullscreenControl === true,
      "cslmap_build_map(): the map is built with the controls, after the filter - Experience's mapTypeControl false is overridden; Google's zoom off");
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
const notYet = env.doc.activeElement !== nameOf(cs[0]);
env.advance(300);
check(notYet && env.doc.activeElement === nameOf(cs[0]) && st(env).back === pin && pin.focusCalls.length === 0 && env.pending() === 0,
      "  ... and focus, which was in the bubble, on the card's name - r4: after Google's close event, here 0.3 s on (this fake sends it inside close()); still bound back to the pin; nothing left pending");
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
const goneIn = same(env.iwLog, [["close"]]) && st(env).open === false;
env.advance(300);
check(goneIn && env.doc.activeElement === nameOf(cs[0]) && env.pending() === 0,
      "  ... the form closed, focus given back to that link: the bubble goes within 0.2 s, and focus moves on to the card's name - r4: 0.3 s on at most");
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
env.advance(500);
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


/* ------------------------------------------------- r4: the map's buttons */

/* r4. Our controls, and Google's lists for the two corners Part 4f uses. */
const ctl = (env) => st(env).ctl;
const rb = (env) => env.gmap.controls[9];
const tr = (env) => env.gmap.controls[3];
const kids = (n) => n.children.map((x) => x.className);

section("buttons", 13, () => {
console.log("");
console.log("  THE MAP'S BUTTONS (Part 4f) - Reset, + and -, where Google's zoom stood");
let env = boot({ n: 3 });
let c = ctl(env);
check(!!c && rb(env).getArray().length === 1 && rb(env).getArray()[0] === c.group && c.group.index === -1 &&
      c.group.className === "avalon-mapctl" && c.group.parentNode === env.mapDiv,
      "made with the map: one control in Google's list for the right foot, index -1 - under Google's own there, the Pegman");
check(same(kids(c.group), ["avalon-mapctl__zoom"]) && same(kids(c.zoom), ["avalon-mapctl__in", "avalon-mapctl__rule", "avalon-mapctl__out"]) &&
      tr(env).getArray().length === 0 && !c.reset.parentNode,
      "  ... + over -, a rule between; no Reset anywhere yet - no search has drawn a view");
check(c.zin.tagName === "BUTTON" && c.zin.attrs.type === "button" && c.zin.attrs["aria-label"] === "Zoom in" && c.zin.attrs.title === "Zoom in" &&
      c.zout.tagName === "BUTTON" && c.zout.attrs.type === "button" && c.zout.attrs["aria-label"] === "Zoom out" && c.zout.attrs.title === "Zoom out" &&
      c.zin.children.length === 0 && c.zout.children.length === 0,
      "+ and -: buttons, type=button, named Zoom in and Zoom out - Google's names - their tooltips the same; no text, the bars are drawn");
check(c.reset.tagName === "BUTTON" && c.reset.attrs.type === "button" && c.reset.attrs["aria-label"] === "Reset map view" &&
      c.reset.attrs.title === "Reset map view" && c.reset.textContent === "Reset",
      "Reset: a button, type=button, showing Reset and named Reset map view - the words it shows first in its name");
env.advance(0);
check(c.reset.parentNode === c.group && same(kids(c.group), ["avalon-mapctl__reset", "avalon-mapctl__zoom"]) &&
      same(rb(env).log, ["push", "removeAt", "push"]) && rb(env).getArray().length === 1 && tr(env).getArray().length === 0,
      "markers_dropped handled: Reset before + and -, in the same control, which goes out of Google's list and in again so that Google lays it out at its new size; nothing at the top right");
env = boot({ phone: true, n: 3 });
c = ctl(env);
env.advance(0);
check(c.reset.parentNode === c.corner && c.corner.className === "avalon-mapctl avalon-mapctl--corner" && tr(env).getArray().length === 1 && tr(env).getArray()[0] === c.corner &&
      same(kids(c.group), ["avalon-mapctl__zoom"]) && same(rb(env).log, ["push"]),
      "on a phone: Reset alone in the top right corner - Google's full screen falls under it - and + and - at the foot, left where they were");
env.phone = false;
resize(env);
env.advance(200);
check(c.reset.parentNode === c.group && c.group.children[0] === c.reset && tr(env).getArray().length === 0 &&
      rb(env).getArray().filter((n) => n === c.group).length === 1 && same(tr(env).log, ["push", "removeAt"]) && st(env).current === null,
      "  ... widened, no dealer chosen: Reset back before + and -, the corner taken out of Google's list, the group in its own list once - Reset moves whatever else a resize finds to do");
env.doc.activeElement = c.reset;
env.phone = true;
resize(env);
env.advance(200);
check(c.reset.parentNode === c.corner && env.doc.activeElement === c.reset && env.moveBlurs >= 1,
      "  ... narrowed with focus on Reset: moved to the corner - which takes focus from it - and focus given back");
env.doc.activeElement = c.zin;
env.doc.fullscreenElement = env.mapDiv;
(env.doc.listeners.fullscreenchange || []).forEach((fn) => fn({ type: "fullscreenchange" }));
env.advance(200);
check(c.reset.parentNode === c.group && tr(env).getArray().length === 0 && env.doc.activeElement === c.zin,
      "a phone's map taken full screen: the desktop's layout - full screen is not a phone's - and focus on + kept as its control is laid out again");
env = makeEnv();
cslmap(env, 2);
env.M.attach(env.cm);
env.advance(1000);
check(!!ctl(env) && !ctl(env).reset.parentNode && st(env).view === null && rb(env).getArray().length === 1,
      "a map with no search yet: + and -, and no Reset - there is no view to go back to");
env = boot({ noControls: true });
check(st(env).ctl === null && same(env.setOpts, [{ zoomControl: true }]),
      "a map with no controls to add to: no buttons of ours, and Google's zoom turned back on");
env = boot();
env.M.attach(env.cm);
env.M.bind(env.cm, env.list);
env.advance(0);
check(rb(env).getArray().length === 1 && ((env.gmap.__l || {}).zoom_changed || []).length === 1 &&
      ((env.gmap.__l || {}).maptypeid_changed || []).length === 1,
      "attach() again and another search: the buttons made once, and heard once");
env = boot({ n: 2 });
c = ctl(env);
c.reset.fire("click");
check(env.fits.length === 0 && env.center.of === "start" && env.zoom === 9,
      "Reset pressed before there is a view to go back to - it is not in the page, but if it were: nothing");
});

/* ------------------------------------------------------------ r4: Reset */

section("reset", 12, () => {
console.log("");
console.log("  RESET (Part 4f) - back to the view the latest search drew");
let env = boot({ n: 3 });
env.mapDiv.clientHeight = 867;
check(st(env).view === null, "the view is not read as the pins are bound: SLP's fit and v0.0.25's zoom out by one come after");
env.center = { of: "drawn" };
env.zoom = 8;
env.advance(0);
const v = st(env).view;
check(!!v && v.c.of === "drawn" && v.z === 8 && v.w === 1011 && v.h === 867 && v.fit === true && v.n === 3,
      "  ... read once they have run: its centre and zoom, the map's size, and that SLP fitted dealers");
env.center = { of: "panned" };
env.zoom = 4;
ctl(env).reset.fire("click");
check(env.center.of === "drawn" && env.zoom === 8 && env.fits.length === 0,
      "panned and zoomed out, Reset: that centre and that zoom, at once - the map the same size, nothing fitted again");
let cs = cards(env);
click(env, 1);
env.dom();
env.advance(0);
const before = env.iwLog.length;
env.zoom = 5;
ctl(env).reset.fire("click");
check(env.zoom === 8 && st(env).open === true && st(env).pinned === true && cur(env) === "102" && same(marked(env), ["102"]) &&
      g(env, 1).getIcon() === HOVER && env.iwLog.length === before,
      "a dealer chosen, its bubble open: the view only - the bubble, the choice, its card's mark and its lit pin as they were");
env.mapDiv.clientWidth = 678;
env.mapDiv.clientHeight = 859;
env.fitZoom = 10;
env.zoom = 3;
ctl(env).reset.fire("click");
check(same(env.fits, [env.cm.bounds]) && env.zoom === 9 && env.center.of === "fitted" &&
      st(env).view.w === 678 && st(env).view.h === 859 && st(env).view.z === 9,
      "the map another size since: SLP's own bounds fitted again, its tweak (0), then one out - 10 to 9 - so every dealer shows; that view kept");
env.zoom = 2;
ctl(env).reset.fire("click");
check(env.fits.length === 1 && env.zoom === 9, "  ... and Reset again: back to it, nothing fitted a second time");
env = boot({ n: 2, options: { zoom_tweak: "2" } });
env.advance(0);
env.mapDiv.clientWidth = 500;
env.fitZoom = 14;
ctl(env).reset.fire("click");
const tweaked = env.zoom;
env = boot({ n: 1 });
env.advance(0);
env.mapDiv.clientWidth = 500;
env.fitZoom = 19;
ctl(env).reset.fire("click");
check(tweaked === 11 && env.zoom === 14,
      "SLP's rules: the fit less its zoom tweak - 14 less 2 - then one out, 11; one dealer held to 15 first - 19 to 15 - then one out, 14");
env = boot({ n: 2, options: { no_autozoom: "1", zoom_level: "12" } });
env.advance(0);
env.mapDiv.clientWidth = 500;
env.fitZoom = 4;
ctl(env).reset.fire("click");
check(env.zoom === 11, "SLP's no-autozoom: its zoom level, 12, whatever the fit gave, then one out: 11");
env = boot({ n: 2, options: { zoom_tweak: "x" } });
env.advance(0);
env.mapDiv.clientWidth = 500;
env.fitZoom = 7;
ctl(env).reset.fire("click");
check(env.zoom === 6, "a tweak that is not a number: none - 7, then one out: 6");
env = makeEnv();
cslmap(env, 0);
env.M.attach(env.cm);
env.M.bind(env.cm, env.list);
env.center = { of: "home" };
env.zoom = 6;
env.advance(0);
env.mapDiv.clientWidth = 500;
env.zoom = 3;
ctl(env).reset.fire("click");
check(st(env).view.fit === false && env.fits.length === 0 && env.center.of === "home" && env.zoom === 6 && ctl(env).reset.parentNode === ctl(env).group,
      "a search that found no dealer: Reset shows, and goes back to where SLP put the map - nothing of its own to fit, whatever the size");
env = boot({ n: 3 });
env.center = { of: "first" };
env.zoom = 8;
env.advance(0);
env.center = { of: "second" };
env.zoom = 6;
env.M.bind(env.cm, env.list);
const kept = st(env).view.c.of;
env.advance(0);
check(kept === "first" && st(env).view.c.of === "second" && st(env).view.z === 6,
      "a new search: its own view, read once its markers_dropped has been handled - the last one's until then");
env.center = { of: "elsewhere" };
env.zoom = 3;
ctl(env).reset.fire("click");
check(env.center.of === "second" && env.zoom === 6,
      "a search refused, or one that failed - no markers_dropped - leaves the last view to go back to");
});

/* -------------------------------------------------------- r4: + and - */

section("zoom", 6, () => {
console.log("");
console.log("  + AND - (Part 4f) - one zoom each, never past either end");
let env = boot();
const c = ctl(env);
env.zoom = 8;
c.zin.fire("click");
const a = env.zoom;
c.zout.fire("click");
c.zout.fire("click");
check(a === 9 && env.zoom === 7, "+ one in, - one out");
env.gmap.setZoom(22);
check(c.zin.attrs["aria-disabled"] === "true" && c.zout.attrs["aria-disabled"] === undefined,
      "at the street map's most, 22: + dimmed - aria-disabled, so a keyboard keeps its place on it - and - not");
c.zin.fire("click");
check(env.zoom === 22, "  ... and + does nothing there");
env.gmap.setZoom(1);
c.zout.fire("click");
check(env.zoom === 1 && c.zout.attrs["aria-disabled"] === "true" && c.zin.attrs["aria-disabled"] === undefined,
      "at the least the map allows, SLP's minZoom 1: - dimmed and doing nothing, + not");
env.gmap.setZoom(20);
env.mapType = "satellite";
env.trigger(env.gmap, "maptypeid_changed");
check(c.zin.attrs["aria-disabled"] === "true", "Satellite, whose most is 20 here: + dimmed at 20 as the map type changes");
env.zoom = 7.6;
c.zin.fire("click");
check(env.zoom === 9, "a zoom between two levels: from the nearest, 8, one in");
});

/* ---------------------------------------------------------- r4: numbers */

section("numbers", 14, () => {
console.log("");
console.log("  NUMBERS (Part 4f) - pins and cards numbered 1 to n, where slp_avalon sets the numbered pin");
const NUM = { avalon_map_number_icon: "/wp-content/uploads/2026/10/pink-marker.png",
              avalon_map_number_hover_icon: "/wp-content/uploads/2026/10/white-marker.png" };
const PINK = "https://example.test/wp-content/uploads/2026/10/pink-marker.png";
const WHITE = "https://example.test/wp-content/uploads/2026/10/white-marker.png";
const numbered = (opts, n) => {
  const env = makeEnv(opts);
  env.doc.body.fontFamily = "Figtree";
  cslmap(env, n || 3, opts);
  const cs = opts.lay ? lay(env, opts.lay) : cards(env);
  env.M.attach(env.cm);
  env.M.bind(env.cm, env.list);
  return { env: env, cs: cs };
};
let r = numbered({ options: NUM });
let env = r.env;
let cs = r.cs;
const ic = g(env, 0).getIcon();
check(!!ic && ic.url === PINK && !!ic.labelOrigin && ic.labelOrigin.x === 15 && ic.labelOrigin.y === 15,
      "each pin the numbered pin - a root path, resolved against the page - with its number's centre at (15, 15), the middle of the head");
check(same(g(env, 0).getLabel(), { text: "1", color: "#000000", fontFamily: "Figtree, Arial, sans-serif", fontSize: "13px", fontWeight: "700",
                                   className: "avalon-pin-num" }) &&
      g(env, 1).getLabel().text === "2" && g(env, 2).getLabel().text === "3",
      "  ... numbered 1, 2, 3 in SLP's order: black, in the page's own font, bold, 13 px");
check(g(env, 0).getTitle() === "Number 1, Dealer & Sons 101" && g(env, 2).getTitle() === "Number 3, Dealer & Sons 103",
      "  ... its title - which Google makes its name - \"Number 1, \" and the dealer's name, as text");
const h = cs[0].children[0];
const sp = h.children[0];
check(sp.className === "avalon-num" && same(sp.children.map((x) => x.className || "#text"), ["avalon-num__sr", "#text", "avalon-num__sr"]) &&
      sp.textContent === "Number 1, " && h.textContent === "Number 1, Dealer & Sons 101" &&
      h.children.length === 2 && h.children[1].tagName === "A" && h.children[1].textContent === "Dealer & Sons 101",
      "the card's heading: \"Number 1, \" before the name - the word and the comma in spans hidden on screen - and its link as it was");
over(env, 1);
const lit = g(env, 1).getIcon();
check(lit.url === WHITE && lit.labelOrigin.x === 15 && lit.labelOrigin.y === 15 && g(env, 1).getLabel().text === "2" &&
      g(env, 1).getZIndex() === 1000001,
      "a pin lit: the numbered pin lit, its number where it was, above the other pins");
env.dom();
out(env, 1);
env.advance(300);
check(g(env, 1).getIcon().url === PINK && g(env, 1).getLabel().text === "2" && g(env, 1).sets.filter((x) => x[0] === "label").length === 1,
      "  ... and at rest again, as it was; its number set once, never touched by the lighting");
check(env.images.length === 1 && env.images[0].src === WHITE,
      "the numbered pin lit preloaded once per search - the hover icon, which a numbered pin never shows, not at all");
env.M.bind(env.cm, env.list);
check(h.children.filter((x) => x.className === "avalon-num").length === 1 && h.children[0].textContent === "Number 1, " &&
      g(env, 0).getLabel().text === "1",
      "the same results bound again: numbered afresh - one number in each heading, never two");
r = numbered({ options: NUM, phone: true, lay: UPRIGHT }, 6);
env = r.env;
click(env, 4);
check(order(env) === "105,101,102,103,104,106" && r.cs[4].children[0].children[0].textContent === "Number 5, ",
      "a card moved to the top of the list under the map (Part 4e) keeps its number, 5");
r = numbered({ options: { avalon_map_number_icon: NUM.avalon_map_number_icon } }, 2);
env = r.env;
over(env, 0);
check(g(env, 0).getIcon().url === PINK && g(env, 0).getZIndex() === 1000001 && env.images.length === 0,
      "no numbered pin lit set: a numbered pin lit is only raised; nothing preloaded");
env = boot();
cs = cards(env);
env.M.bind(env.cm, env.list);
over(env, 0);
check(g(env, 1).getLabel() === null && g(env, 1).getIcon() === "https://example.test/wp-content/uploads/pin.png" &&
      g(env, 0).getIcon() === HOVER && cs[0].children[0].children.length === 1 && g(env, 0).sets.filter((x) => x[0] === "title").length === 0,
      "no numbered pin set: nothing numbered, on pins or cards - SLP's pins, lit with the hover icon as before");
env = makeEnv({ options: NUM });
cslmap(env, 2);
env.M.attach(env.cm);
let threw = false;
try {
  env.M.bind(env.cm, env.list);
} catch (e) {
  threw = true;
}
check(!threw && g(env, 1).getLabel().text === "2" && g(env, 1).getLabel().fontFamily === "Arial, sans-serif",
      "no cards in the page, and no font read from it: the pins numbered all the same, in Arial; nothing thrown");
env = makeEnv({ options: NUM });
cslmap(env, 3);
cs = cards(env);
env.list[1] = null;
env.M.attach(env.cm);
env.M.bind(env.cm, env.list);
check(g(env, 0).getLabel().text === "1" && g(env, 1).getLabel() === null && g(env, 2).getLabel().text === "2" &&
      cs[1].children[0].children.length === 1,
      "a pin without its result is skipped, and takes no number: the next is 2");
env = makeEnv({ options: Object.assign({}, NUM, { avalon_map_number_icon: "" }) });
cslmap(env, 2);
cards(env);
env.M.attach(env.cm);
env.M.bind(env.cm, env.list);
check(g(env, 0).getLabel() === null && env.images.length === 1 && env.images[0].src === HOVER,
      "the numbered pin set empty: no numbers - and the numbered pin lit, set on its own, not used either");
});

/* ------------------------------------------- r4: focus after narrowing */

section("focus after narrowing", 7, () => {
console.log("");
console.log("  FOCUS AFTER NARROWING (Part 4f) - to the card once Google's close event has come");
let env = boot({ n: 4 });
let cs = cards(env);
env.asyncClose = true;
env.lateFocus = true;
const cd = new El("a", { id: "card-contact" }, env);
env.doc.body.appendChild(cd);
env.doc.activeElement = cd;
click(env, 2);
env.dom();
env.advance(0);
const inBubble = env.doc.activeElement.parentNode.id === "slp_bubble_website";
env.phone = true;
resize(env);
env.advance(200);
check(inBubble && st(env).open === false && st(env).pinned === true && env.doc.activeElement !== nameOf(cs[2]) && !env.lateFocusMoves,
      "a chosen bubble with focus in it - from a card's Contact Dealer - and the window narrowed: the bubble goes, and focus waits for Google's close event");
env.advance(23);
check(env.lateFocusMoves === 1 && env.doc.activeElement === nameOf(cs[2]) && env.pending() === 0,
      "  ... which comes 23 ms on, Google's focus back to that Contact Dealer with it - then focus to the chosen card's name, the last word; the fallback cleared");
env = boot({ n: 4 });
cs = cards(env);
env.asyncClose = "never";
click(env, 0);
env.dom();
env.advance(0);
env.phone = true;
resize(env);
env.advance(200);
env.advance(299);
const early = env.doc.activeElement === nameOf(cs[0]);
env.advance(1);
check(!early && env.doc.activeElement === nameOf(cs[0]), "no close event from Google at all: focus to the card 0.3 s on, not before");
env = boot({ n: 4 });
cs = cards(env);
env.asyncClose = true;
env.lateFocusAfter = true;
const cd2 = new El("a", { id: "card-contact-2" }, env);
env.doc.body.appendChild(cd2);
env.doc.activeElement = cd2;
click(env, 3);
env.dom();
env.advance(0);
env.phone = true;
resize(env);
env.advance(223);
check(env.lateFocusMoves === 1 && env.doc.activeElement === nameOf(cs[3]),
      "Google's focus back only once its close event has been sent: the card's name still the last word - to_card() waits for the event to be done");
env = boot({ n: 4 });
cs = cards(env);
env.asyncClose = true;
click(env, 0);
env.dom();
env.advance(0);
env.phone = true;
resize(env);
env.advance(200);
esc(env);
env.advance(500);
check(st(env).pinned === false && env.doc.activeElement !== nameOf(cs[0]) && env.pending() === 0,
      "Esc before Google's close event: the choice let go, and focus not sent to its card after all");
env = boot({ n: 4 });
cs = cards(env);
env.asyncClose = "never";
click(env, 0);
env.dom();
env.advance(0);
env.phone = true;
resize(env);
env.advance(200);
env.phone = false;
resize(env);
env.advance(400);
check(st(env).open === true && env.doc.activeElement !== nameOf(cs[0]) && env.opens === 2,
      "widened again before then: that dealer's bubble back, and no focus taken - nobody chose anything just now");
env = boot({ n: 4 });
cs = cards(env);
env.asyncClose = true;
const box = new El("input", { id: "addressInput" }, env);
env.doc.body.appendChild(box);
click(env, 1);
env.dom();
env.advance(0);
env.doc.activeElement = box;
env.phone = true;
resize(env);
env.advance(500);
check(env.doc.activeElement === box && nameOf(cs[1]).focusCalls.length === 0,
      "focus that was not in the bubble - in the search box: left there, nothing sent to the card");
});

console.log("");
console.log("  " + pass + " passed, " + fail + " failed, " + (pass + fail) + " total");
console.log("");
process.exit(fail ? 1 : 0);
