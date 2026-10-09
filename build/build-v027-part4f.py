#!/usr/bin/env python3
"""
build-v027-part4f.py

slp_avalon v0.0.27, PART 4f - Reset on the find-a-dealer map, numbered pins
and cards, and two fixes from Part 4e on Aura DEV.

WHAT THIS RELEASE DOES
----------------------
The owner's decisions of 2026-10-07 and 2026-10-08, with Part 4e on Aura DEV:

1. RESET ("Beside -, like Bennington"). One control of ours where Google's
   zoom stood, at the map's right foot: Reset, then our own + over -, their
   feet in line, in Google's look. Google's zoom is turned off - nothing can
   sit beside Google's own - and the Pegman stays Google's, above them. On a
   phone (767 px wide or less, or 500 px high or less, and not full screen)
   Reset stands alone in the top right corner, Google's full screen under
   it. Reset shows once a search has drawn a view and takes the map back to
   it, at once - the view as SLP and v0.0.25 drew it; on a map of another
   size since, the dealers fitted again by the same rules. The map's view
   only: what is chosen, lit, open or marked, the map type and the list
   stay as they are.

2. NUMBERED PINS AND CARDS. Where the option avalon_map_number_icon is set,
   each search's dealers are numbered 1 to n in SLP's order: the number on
   the pin's head, black, bold, 13 px (the owner, 2026-10-08: black, for
   WCAG AA - 4.80:1 on the pink pin, 21:1 on the white one), the pin lit
   as avalon_map_number_hover_icon with the same number, and the card's
   heading starting with the number in a disc. Screen readers hear "Number
   4, <dealer>" on the pin and in the heading (the owner, 2026-10-07).
   Without the option nothing is numbered: Tahoe and Avalon stay as they
   are until they have pins. Store pages are untouched.

3. FOCUS AFTER NARROWING (Part 4e's rows 78 and 80). A window narrowed to a
   phone's with focus in a chosen dealer's bubble gives that focus to the
   dealer's card once Google's close event has come, or 0.3 s on - so that
   nothing Google does with focus as its bubble goes comes after it.

4. THE WHITE BAR (the owner's screenshots 4803 to 4805). Part 4d's hidden
   "Hours: " in each card's summary had no positioned box nearer than the
   theme's #sl_div, so for a card below what the results box shows it sat
   outside the box's clipping and stretched the page up to 211 px below the
   footer. .avalon-hours is that box now, and the numbered disc is the box
   for its own hidden words. Measured on DEV's page: 208 px before, 0 after
   (the owner's console, 2026-10-08).

WHAT IS PATCHED
---------------
slp_avalon.js: seventeen anchored edits, all inside Part 4c's avalon_map
block. avalon-hours.css: four - the header twice, .avalon-hours positioned,
and the Part 4f block at the end. class.slp_avalon.php: four - a note among
the registrations, the two option readers and the check they share, and the
two options in avalon_js_options_map(), Part 4c's callback on slp_js_options
at 110: nothing new is registered, and every new selector names #map_sidebar
or .gm-style, on WP Rocket's safelist since Part 4c.

avalon-hours.js is NOT an input and does not change (588939bf, 18,319
bytes); nor do slp_avalon.php and the address-key class.

ENCODING
--------
Every file is read and written as ISO-8859-1, so every byte round-trips;
slp_avalon.js and the class stay pure CRLF, the stylesheet pure LF. Every
edit is written LF here and turned CRLF where its file is. Every anchor
must match exactly once, in the file as the edits before it left it.

    python build-v027-part4f.py <src_dir> <out_dir>

src_dir holds Part 4e's avalon-hours.css and slp_avalon.js and Part 4d's
class.slp_avalon.php - which Part 4e runs - by those names. Output pinned;
nothing is written unless all three match.
"""

import hashlib
import io
import os
import re
import sys

CRLF = "\r\n"

PINS = {
    'avalon-hours.css':     ('8e862ce347b040f329b77969c226165b', 26533),
    'slp_avalon.js':        ('5df0fcc4ea5d403e897e99f49f869afa', 119874),
    'class.slp_avalon.php': ('778185553d663139707b9d23dff00407', 339614),
}

OUT_PINS = {
    'avalon-hours.css':     ('04fd4a0034c9f8c8959454bca34ef14a', 32702),
    'slp_avalon.js':        ('7f419ecd4f21cf1b15cfa0656bb26eca', 136764),
    'class.slp_avalon.php': ('9d88d8e446fd02f1902e21866914ad0f', 342317),
}

HOURS_JS_PIN = ('588939bf366c2760ba46127d6d9951a6', 18319)


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def crlf(s):
    assert '\r' not in s
    return s.replace('\n', CRLF)


def sub_once(text, old, new, label):
    n = text.count(old)
    if n != 1:
        sys.exit("ABORT {}: anchor matched {} times, expected exactly 1".format(label, n))
    print("  patched   {}".format(label))
    return text.replace(old, new, 1)


def check(cond, label):
    if not cond:
        sys.exit("ABORT self-check: {}".format(label))
    print("  check OK  {}".format(label))


def bare_css(css):
    return re.sub(r'/\*.*?\*/', '', css, flags=re.S)


# ===========================================================================
# avalon-hours.css (LF)
# ===========================================================================

CSS_EDITS = [

    ("css: the header names Part 4f",
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4e.\n",
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4f.\n"),

    ("css: the header says what Part 4f changes",
     " * longer a size container. Open stays green.\n"
     " */\n",
     " * longer a size container. Open stays green.\n"
     " *\n"
     " * PART 4f, after the first rule and at the end of the file: the owner's\n"
     " * decisions of 2026-10-07 and 2026-10-08. .avalon-hours made the box its\n"
     " * hidden \"Hours: \" is placed in - left to the page, the span sat where an\n"
     " * unscrolled list would put it and stretched the page below its footer\n"
     " * (the white bar on DEV, 2026-10-08). The numbered cards' disc, in the\n"
     " * primary colour with a black number, as on the map's pins. Reset and\n"
     " * the map's own + and -, in Google's look: slp_avalon.js turns Google's\n"
     " * zoom off and puts these where it stood.\n"
     " */\n"),

    ("css: .avalon-hours the box its hidden Hours: is placed in - the white bar under the footer",
     "  --avalon-hours-today: var(--e-global-color-primary, var(--primary-color, #e7167c));\n"
     "}\n",
     "  --avalon-hours-today: var(--e-global-color-primary, var(--primary-color, #e7167c));\n"
     "}\n"
     "\n"
     "/* Part 4f. The box the hidden \"Hours: \" (.avalon-hours__sr, in Part 4d's\n"
     "   block) is placed in. Without it the nearest was the theme's #sl_div, and\n"
     "   for a card below what the results box shows, the span sat outside the\n"
     "   box's clipping, where the unscrolled list would put it - up to 211 px\n"
     "   below the footer on DEV (1707 x 839, 2026-10-08), where nothing paints.\n"
     "   A box of its own for every surface: a card, the bubble, a store page. */\n"
     ".avalon-hours {\n"
     "  position: relative;\n"
     "}\n"),

    ("css: the numbered cards' disc and the map's buttons, at the end of the file",
     "    animation: none;\n"
     "  }\n"
     "}\n",
     "    animation: none;\n"
     "  }\n"
     "}\n"
     "\n"
     "/* ----------------------------------------- Part 4f: the numbered cards */\n"
     "\n"
     "/* Part 4f. Where the map's pins are numbered (slp_avalon.js, NUMBERS),\n"
     "   each card's heading starts with the same number, in a disc of the\n"
     "   site's primary colour with the number in black, bold - 4.80:1 on Aura's\n"
     "   pink, as on the pins (the owner, 2026-10-08) - round, and a pill for\n"
     "   two digits; before the name, on its first line. Screen readers hear\n"
     "   \"Number 4, \" before the name: the word and the comma are in the\n"
     "   markup, hidden on screen as the hidden \"Hours: \" is, and the disc is\n"
     "   the box they are placed in, so that they cannot stretch the page\n"
     "   either. Two custom properties for a site on another background.\n"
     "\n"
     "   The disc's middle on the middle of the name's capitals, and the line\n"
     "   no taller for it: measured on DEV's cards in Chromium (2026-10-08),\n"
     "   with the page's Figtree - the capitals' middle 13.6 px under the\n"
     "   line's top at 24 px, 12 px at 20 px (1024 px and under), and the\n"
     "   disc's middle 14 and 12; the heading 28.8 and 24 px high with the\n"
     "   disc or without it. The gap after it is the card's column gap. */\n"
     "#map_sidebar .results_wrapper .avalon-num {\n"
     "  position: relative;\n"
     "  display: inline-block;\n"
     "  box-sizing: border-box;\n"
     "  min-width: 26px;\n"
     "  height: 26px;\n"
     "  margin: 1px 10px -1px 0;\n"
     "  padding: 0 7px;\n"
     "  border-radius: 13px;\n"
     "  background-color: var(--avalon-num-bg, var(--e-global-color-primary, var(--primary-color, #e7167c)));\n"
     "  color: var(--avalon-num-text, #000);\n"
     "  font-size: 14px;\n"
     "  font-weight: 700;\n"
     "  line-height: 26px;\n"
     "  text-align: center;\n"
     "  vertical-align: top;\n"
     "}\n"
     "\n"
     "@media (max-width: 1024px) {\n"
     "  #map_sidebar .results_wrapper .avalon-num {\n"
     "    margin: -1px 8px -3px 0;\n"
     "  }\n"
     "}\n"
     "\n"
     "#map_sidebar .results_wrapper .avalon-num__sr {\n"
     "  position: absolute;\n"
     "  width: 1px;\n"
     "  height: 1px;\n"
     "  margin: -1px;\n"
     "  padding: 0;\n"
     "  overflow: hidden;\n"
     "  clip: rect(0 0 0 0);\n"
     "  clip-path: inset(50%);\n"
     "  white-space: nowrap;\n"
     "  border: 0;\n"
     "}\n"
     "\n"
     "/* ----------------------------------------- Part 4f: the map's buttons */\n"
     "\n"
     "/* Part 4f. Reset, and + and -, where Google's zoom stood (slp_avalon.js,\n"
     "   THE MAP'S BUTTONS), in Google's look as the probe read it on DEV on\n"
     "   2026-10-08: white, 2 px corners, Google's shadow, 40 px buttons, a 1 px\n"
     "   rule 30 px wide between + and -, Reset in the Map and Satellite\n"
     "   buttons' type - Roboto 500, 18 px - and 10 px from the map's edges and\n"
     "   from each other, as Google's own controls stand. Each button sets its\n"
     "   colours for every state: the theme's button:hover and button:focus\n"
     "   (0-1-1) paint any button pink, and these are 1-3-1 and up. A keyboard\n"
     "   ring, black, inside the white button, so that it shows over any map;\n"
     "   none for a pointer. + and - dimmed at either end of the zoom. */\n"
     "#map .gm-style .avalon-mapctl {\n"
     "  display: flex;\n"
     "  align-items: flex-end;\n"
     "  gap: 10px;\n"
     "  margin: 10px;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl__zoom {\n"
     "  display: flex;\n"
     "  flex-direction: column;\n"
     "  align-items: center;\n"
     "  border-radius: 2px;\n"
     "  background-color: #fff;\n"
     "  box-shadow: rgba(0, 0, 0, 0.3) 0 1px 4px -1px;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl__rule {\n"
     "  width: 30px;\n"
     "  height: 1px;\n"
     "  background-color: #e6e6e6;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl button {\n"
     "  position: relative;\n"
     "  display: block;\n"
     "  box-sizing: border-box;\n"
     "  width: 40px;\n"
     "  height: 40px;\n"
     "  margin: 0;\n"
     "  padding: 0;\n"
     "  border: 0;\n"
     "  border-radius: 2px;\n"
     "  background-color: #fff;\n"
     "  box-shadow: none;\n"
     "  color: #666;\n"
     "  cursor: pointer;\n"
     "  font-family: Roboto, Arial, sans-serif;\n"
     "  font-size: 18px;\n"
     "  font-weight: 500;\n"
     "  line-height: 40px;\n"
     "  letter-spacing: normal;\n"
     "  text-align: center;\n"
     "  text-transform: none;\n"
     "  white-space: nowrap;\n"
     "  user-select: none;\n"
     "  transition: none;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl .avalon-mapctl__reset {\n"
     "  width: auto;\n"
     "  padding: 0 17px;\n"
     "  box-shadow: rgba(0, 0, 0, 0.3) 0 1px 4px -1px;\n"
     "  color: #565656;\n"
     "}\n"
     "\n"
     "/* On a phone, Reset alone in the top right corner: a little narrower, so\n"
     "   that it clears Map and Satellite on the narrowest maps. */\n"
     "#map .gm-style .avalon-mapctl--corner .avalon-mapctl__reset {\n"
     "  padding: 0 12px;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl button:focus {\n"
     "  outline: none;\n"
     "  background-color: #fff;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl button:hover {\n"
     "  background-color: #fff;\n"
     "  color: #333;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl .avalon-mapctl__reset:hover {\n"
     "  background-color: #ebebeb;\n"
     "  color: #000;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl button:active {\n"
     "  color: #111;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl button[aria-disabled=\"true\"],\n"
     "#map .gm-style .avalon-mapctl button[aria-disabled=\"true\"]:hover {\n"
     "  background-color: #fff;\n"
     "  color: #d1d1d1;\n"
     "  cursor: default;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl button:focus-visible {\n"
     "  outline: 2px solid #000;\n"
     "  outline-offset: -4px;\n"
     "}\n"
     "\n"
     "/* + and -, drawn: 16 px bars, 2 px thick, in the button's colour. */\n"
     "#map .gm-style .avalon-mapctl__in::before,\n"
     "#map .gm-style .avalon-mapctl__in::after,\n"
     "#map .gm-style .avalon-mapctl__out::before {\n"
     "  content: \"\";\n"
     "  position: absolute;\n"
     "  top: 50%;\n"
     "  left: 50%;\n"
     "  width: 16px;\n"
     "  height: 2px;\n"
     "  margin: -1px 0 0 -8px;\n"
     "  background-color: currentColor;\n"
     "}\n"
     "\n"
     "#map .gm-style .avalon-mapctl__in::after {\n"
     "  transform: rotate(90deg);\n"
     "}\n"),

]


# ===========================================================================
# slp_avalon.js (CRLF; written LF here)
# ===========================================================================

MAP_JS_EDITS = [

    ("slp_avalon.js: avalon_map's header - a numbered pin lights as NUMBERS says",
     "   * only raised.\n",
     "   * only raised. A numbered pin (Part 4f) lights as NUMBERS, below, says.\n"),

    ("slp_avalon.js: avalon_map's header - the zoom is ours from Part 4f",
     "   * Satellite.\n",
     "   * Satellite. From Part 4f the zoom is ours: THE MAP'S BUTTONS, below.\n"),

    ("slp_avalon.js: avalon_map's header - the map's buttons, numbers, focus after narrowing",
     "   * Part 4c's Font Awesome labels went with Part 4d: words on every\n",
     "   * THE MAP'S BUTTONS (Part 4f). Reset, and our own + and -, where\n"
     "   * Google's zoom stood. Google's zoom is off (controls()): nothing can\n"
     "   * sit beside Google's own, and Reset belongs beside - (the owner,\n"
     "   * 2026-10-07). The Pegman stays Google's, above them. On a desktop or a\n"
     "   * tablet they are one control at the right foot of the map: Reset, then\n"
     "   * + over -, their feet in line, in Google's look (avalon-hours.css). On\n"
     "   * a phone Reset stands alone in the top right corner, Google's full\n"
     "   * screen under it, and + and - stay at the foot; a window resized\n"
     "   * across a phone's size, or full screen coming or going, moves it. Reset\n"
     "   * shows once a search has drawn a view, and takes the map back to it at\n"
     "   * once: the view as SLP and v0.0.25 drew it - the dealers and the\n"
     "   * search's point fitted, then one zoom out - read as soon as\n"
     "   * markers_dropped has been handled (the probe read it there and at the\n"
     "   * map's idle: the same). On a map of another size since, the dealers\n"
     "   * are fitted again by the same rules, so that every one shows. The\n"
     "   * map's view only: a dealer chosen, a lit pin, an open bubble, the map\n"
     "   * type and the list stay as they are; a search refused, or one that\n"
     "   * failed, keeps the last view. + and - zoom by one, between the least\n"
     "   * zoom the map allows (SLP's 1) and the most its map type does; at\n"
     "   * either end the button is dimmed - aria-disabled, so focus stays on it.\n"
     "   * Their names are Google's, Zoom in and Zoom out; Reset's is \"Reset map\n"
     "   * view\". Where the map has no controls to add to, Google's zoom comes\n"
     "   * back and there is no Reset.\n"
     "   *\n"
     "   * NUMBERS (Part 4f). Where slp_avalon sets avalon_map_number_icon, each\n"
     "   * search's dealers are numbered 1 to n in SLP's order - the cards' as\n"
     "   * drawn - and a number stays with its dealer when Part 4e moves a card.\n"
     "   * A pin becomes that icon with its number on the head, at (15, 15) of\n"
     "   * the 30 x 40 art: black, the page's own font, bold, 13 px - 4.80:1 on\n"
     "   * the pink pin, 21:1 on the white one (black: the owner, 2026-10-08,\n"
     "   * for WCAG AA). Lit, it is avalon_map_number_hover_icon with the same\n"
     "   * number; without that option, only raised. Its title, which Google\n"
     "   * makes its name, is \"Number 4, <dealer>\". The card's heading starts\n"
     "   * with the number in a disc, and reads \"Number 4, \" before the name:\n"
     "   * \"Number \" and the comma are hidden on screen; the link is as it was.\n"
     "   * The bubble and the search's own pin have no number. Without the\n"
     "   * option nothing is numbered and the pins are SLP's.\n"
     "   *\n"
     "   * FOCUS AFTER NARROWING (Part 4f). A window narrowed to a phone's with\n"
     "   * focus in a chosen dealer's bubble gives that focus to the dealer's\n"
     "   * card once Google's close event has come - after close() has returned,\n"
     "   * 23 ms later on DEV - or 0.3 s on, whichever is first: so that\n"
     "   * nothing Google does with focus as its bubble goes comes after it.\n"
     "   *\n"
     "   * Part 4c's Font Awesome labels went with Part 4d: words on every\n"),

    ("slp_avalon.js: HEAD - where a numbered pin's number sits",
     "    var PHONE = \"(max-width: 767px), (max-height: 500px)\";\n"
     "\n",
     "    var PHONE = \"(max-width: 767px), (max-height: 500px)\";\n"
     "    //Part 4f. Where a numbered pin's number sits: the middle of the head\n"
     "    //of the 30 x 40 art, x and y, in px from its top left. Measured on DEV\n"
     "    //(the probe, 2026-10-08): the number's centre 0.2 px from it.\n"
     "    var HEAD = 15;\n"
     "\n"),

    ("slp_avalon.js: what Part 4f keeps - the numbered pins, their font, the view, the buttons, the pending focus",
     "      icon: null          //the hover icon, resolved; \"\" for none\n",
     "      icon: null,         //the hover icon, resolved; \"\" for none\n"
     "      nicon: null,        //Part 4f. the numbered pin, resolved; \"\" for none, and no numbers\n"
     "      nlit: null,         //Part 4f. the numbered pin lit, resolved; \"\" for none\n"
     "      font: null,         //Part 4f. the numbers' font: the page's\n"
     "      view: null,         //Part 4f. { c, z, w, h, fit, n }: the view the latest search drew\n"
     "      vt: 0,              //Part 4f. the pending read of that view\n"
     "      ctl: null,          //Part 4f. the map's buttons: { group, zoom, reset, zin, zout, corner, at }\n"
     "      closing: 0          //Part 4f. the pending focus to the card, after Google's close event\n"),

    ("slp_avalon.js: the view, Reset, + and -, the buttons made and placed; to_card() after Google's close",
     "    function controls(o) {\n",
     "    //Part 4f. THE MAP'S BUTTONS, in the header above: the view the latest\n"
     "    //search drew, read once markers_dropped has been handled - SLP's\n"
     "    //fitBounds() and zoom tweak, then v0.0.25's zoom out by one. fit: the\n"
     "    //search found dealers, so SLP's bounds are its own.\n"
     "    function take() {\n"
     "      st.vt = 0;\n"
     "      var cm = st.cm;\n"
     "      var g = cm && cm.gmap;\n"
     "      var d = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n"
     "      var c = g && typeof g.getCenter === \"function\" ? g.getCenter() : null;\n"
     "      var z = g && typeof g.getZoom === \"function\" ? g.getZoom() : NaN;\n"
     "      if (!d || !c || typeof z !== \"number\" || isNaN(z)) {\n"
     "        return;\n"
     "      }\n"
     "      var n = (cm.markers || []).length;\n"
     "      st.view = { c: c, z: z, w: d.clientWidth, h: d.clientHeight, fit: n > 0 && !!cm.bounds, n: n };\n"
     "      place_reset();\n"
     "    }\n"
     "\n"
     "    //Part 4f. The dealers fitted again, by SLP's rules (slp_core.js\n"
     "    //putMarkers()) and then v0.0.25's zoom out by one.\n"
     "    function refit(cm, n) {\n"
     "      var g = cm.gmap;\n"
     "      var o = (typeof slplus !== \"undefined\" && slplus && slplus.options) || {};\n"
     "      var z;\n"
     "      g.fitBounds(cm.bounds);\n"
     "      if (o.no_autozoom === \"1\") {\n"
     "        z = parseInt(o.zoom_level, 10);\n"
     "      } else {\n"
     "        z = g.getZoom() - (parseInt(o.zoom_tweak, 10) || 0);\n"
     "        if (n < 2) {\n"
     "          z = Math.min(z, 15);\n"
     "        }\n"
     "      }\n"
     "      if (!isNaN(z)) {\n"
     "        g.setZoom(z);\n"
     "      }\n"
     "      g.setZoom(g.getZoom() - 1);\n"
     "    }\n"
     "\n"
     "    //Part 4f. Reset: back to that view, at once - on a map of another size\n"
     "    //since, the dealers fitted again, and that view kept from then on.\n"
     "    function reset() {\n"
     "      var v = st.view;\n"
     "      var cm = st.cm;\n"
     "      var g = cm && cm.gmap;\n"
     "      var d = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n"
     "      if (!v || !d) {\n"
     "        return;\n"
     "      }\n"
     "      if (v.fit && (d.clientWidth !== v.w || d.clientHeight !== v.h)) {\n"
     "        refit(cm, v.n);\n"
     "        take();\n"
     "        return;\n"
     "      }\n"
     "      g.setCenter(v.c);\n"
     "      g.setZoom(v.z);\n"
     "    }\n"
     "\n"
     "    //Part 4f. The least zoom the map allows, and the most its map type\n"
     "    //does: SLP builds the map with minZoom 1; a type says its own most.\n"
     "    function limits(g) {\n"
     "      var lo = 0;\n"
     "      var hi = 22;\n"
     "      try {\n"
     "        var t = g.mapTypes && typeof g.mapTypes.get === \"function\" ? g.mapTypes.get(g.getMapTypeId()) : null;\n"
     "        var mn = typeof g.get === \"function\" ? g.get(\"minZoom\") : undefined;\n"
     "        var mx = typeof g.get === \"function\" ? g.get(\"maxZoom\") : undefined;\n"
     "        lo = typeof mn === \"number\" ? mn : (t && typeof t.minZoom === \"number\" ? t.minZoom : 0);\n"
     "        hi = typeof mx === \"number\" ? mx : (t && typeof t.maxZoom === \"number\" ? t.maxZoom : 22);\n"
     "      } catch (x) {\n"
     "        //A map that cannot say: 0 to 22, Google's own range.\n"
     "      }\n"
     "      return { min: lo, max: hi };\n"
     "    }\n"
     "\n"
     "    //Part 4f. + and -: one zoom in or out, never past either end.\n"
     "    function zoom_by(dz) {\n"
     "      var g = st.cm && st.cm.gmap;\n"
     "      var z = g && typeof g.getZoom === \"function\" ? g.getZoom() : NaN;\n"
     "      if (typeof z !== \"number\" || isNaN(z)) {\n"
     "        return;\n"
     "      }\n"
     "      var lim = limits(g);\n"
     "      var to = Math.max(lim.min, Math.min(lim.max, Math.round(z) + dz));\n"
     "      if (to !== z) {\n"
     "        g.setZoom(to);\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4f. A button dimmed at its end of the zoom - or not.\n"
     "    function dim(b, off) {\n"
     "      if (off) {\n"
     "        b.setAttribute(\"aria-disabled\", \"true\");\n"
     "      } else if (typeof b.removeAttribute === \"function\") {\n"
     "        b.removeAttribute(\"aria-disabled\");\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4f. + and - dimmed at either end, as the zoom or the map type\n"
     "    //changes.\n"
     "    function ends() {\n"
     "      var c = st.ctl;\n"
     "      var g = st.cm && st.cm.gmap;\n"
     "      var z = g && typeof g.getZoom === \"function\" ? g.getZoom() : NaN;\n"
     "      if (!c || typeof z !== \"number\" || isNaN(z)) {\n"
     "        return;\n"
     "      }\n"
     "      var lim = limits(g);\n"
     "      dim(c.zin, z >= lim.max);\n"
     "      dim(c.zout, z <= lim.min);\n"
     "    }\n"
     "\n"
     "    //Part 4f. An element of ours, by tag and class.\n"
     "    function make(tag, name) {\n"
     "      var n = document.createElement(tag);\n"
     "      n.className = name;\n"
     "      return n;\n"
     "    }\n"
     "\n"
     "    //Part 4f. A button of ours: its name, which is its tooltip too, and\n"
     "    //the text it shows, if any. type=\"button\": nothing here submits.\n"
     "    function button(name, label, text) {\n"
     "      var b = make(\"button\", name);\n"
     "      b.setAttribute(\"type\", \"button\");\n"
     "      b.setAttribute(\"aria-label\", label);\n"
     "      b.setAttribute(\"title\", label);\n"
     "      if (text) {\n"
     "        b.appendChild(document.createTextNode(text));\n"
     "      }\n"
     "      return b;\n"
     "    }\n"
     "\n"
     "    //Part 4f. One of our controls into the list Google lays a corner of\n"
     "    //the map out from, or out of it - and out and in again when it is\n"
     "    //there already, so that Google lays it out at its new size. Focus in\n"
     "    //it, which the move takes away, is given back.\n"
     "    function dock(pos, n, on) {\n"
     "      var g = st.cm.gmap;\n"
     "      var list = g.controls[pos];\n"
     "      var a = document.activeElement;\n"
     "      var had = !!a && a !== document.body && typeof n.contains === \"function\" && n.contains(a);\n"
     "      var all = list.getArray();\n"
     "      for (var i = all.length - 1; i >= 0; i--) {\n"
     "        if (all[i] === n) {\n"
     "          list.removeAt(i);\n"
     "        }\n"
     "      }\n"
     "      if (on) {\n"
     "        list.push(n);\n"
     "      }\n"
     "      if (had && document.activeElement !== a && document.body.contains(a)) {\n"
     "        try {\n"
     "          a.focus({ preventScroll: true });\n"
     "        } catch (x) {\n"
     "          //Refused: focus stays where the move left it.\n"
     "        }\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4f. Reset where the layout wants it: in our group at the foot,\n"
     "    //before + and -; on a phone alone, top right; nowhere until a search\n"
     "    //has drawn a view to go back to.\n"
     "    function place_reset() {\n"
     "      var c = st.ctl;\n"
     "      if (!c) {\n"
     "        return;\n"
     "      }\n"
     "      var at = !st.view ? \"\" : phone() ? \"corner\" : \"group\";\n"
     "      if (at === c.at) {\n"
     "        return;\n"
     "      }\n"
     "      var P = google.maps.ControlPosition;\n"
     "      var was = c.at;\n"
     "      var a = document.activeElement;\n"
     "      var had = a === c.reset;\n"
     "      if (c.reset.parentNode) {\n"
     "        c.reset.parentNode.removeChild(c.reset);\n"
     "      }\n"
     "      if (at === \"group\") {\n"
     "        c.group.insertBefore(c.reset, c.zoom);\n"
     "      } else if (at === \"corner\") {\n"
     "        c.corner.appendChild(c.reset);\n"
     "      }\n"
     "      c.at = at;\n"
     "      if (was === \"corner\" || at === \"corner\") {\n"
     "        dock(P.TOP_RIGHT, c.corner, at === \"corner\");\n"
     "      }\n"
     "      if (was === \"group\" || at === \"group\") {\n"
     "        dock(P.RIGHT_BOTTOM, c.group, true);\n"
     "      }\n"
     "      if (had && at && document.activeElement !== c.reset) {\n"
     "        try {\n"
     "          c.reset.focus({ preventScroll: true });\n"
     "        } catch (x) {\n"
     "          //As above.\n"
     "        }\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4f. The buttons made, once, as the map is built: + and - at the\n"
     "    //map's right foot, where Google's zoom stood - index -1 puts them under\n"
     "    //Google's other controls there, the Pegman - with Reset to come.\n"
     "    function buttons(cm) {\n"
     "      var g = cm.gmap;\n"
     "      var P = google.maps.ControlPosition;\n"
     "      if (!P || !g.controls || !g.controls[P.RIGHT_BOTTOM] || !g.controls[P.TOP_RIGHT] ||\n"
     "          typeof g.controls[P.RIGHT_BOTTOM].getArray !== \"function\") {\n"
     "        if (typeof g.setOptions === \"function\") {\n"
     "          g.setOptions({ zoomControl: true });\n"
     "        }\n"
     "        return;\n"
     "      }\n"
     "      var c = {\n"
     "        group: make(\"div\", \"avalon-mapctl\"),\n"
     "        zoom: make(\"div\", \"avalon-mapctl__zoom\"),\n"
     "        corner: make(\"div\", \"avalon-mapctl avalon-mapctl--corner\"),\n"
     "        reset: button(\"avalon-mapctl__reset\", \"Reset map view\", \"Reset\"),\n"
     "        zin: button(\"avalon-mapctl__in\", \"Zoom in\", \"\"),\n"
     "        zout: button(\"avalon-mapctl__out\", \"Zoom out\", \"\"),\n"
     "        at: \"\"\n"
     "      };\n"
     "      c.zoom.appendChild(c.zin);\n"
     "      c.zoom.appendChild(make(\"div\", \"avalon-mapctl__rule\"));\n"
     "      c.zoom.appendChild(c.zout);\n"
     "      c.group.appendChild(c.zoom);\n"
     "      c.group.index = -1;\n"
     "      c.reset.addEventListener(\"click\", reset, false);\n"
     "      c.zin.addEventListener(\"click\", function () {\n"
     "        zoom_by(1);\n"
     "      }, false);\n"
     "      c.zout.addEventListener(\"click\", function () {\n"
     "        zoom_by(-1);\n"
     "      }, false);\n"
     "      st.ctl = c;\n"
     "      g.controls[P.RIGHT_BOTTOM].push(c.group);\n"
     "      google.maps.event.addListener(g, \"zoom_changed\", ends);\n"
     "      google.maps.event.addListener(g, \"maptypeid_changed\", ends);\n"
     "      ends();\n"
     "      place_reset();\n"
     "    }\n"
     "\n"
     "    //Part 4f. FOCUS AFTER NARROWING, in the header above: to_card() once\n"
     "    //Google's close event has come, or CLOSE_MS on - and only while that\n"
     "    //dealer is still the one chosen, with no bubble.\n"
     "    function to_card_after(iw, e) {\n"
     "      var done = false;\n"
     "      var h = null;\n"
     "      var go = function () {\n"
     "        if (done) {\n"
     "          return;\n"
     "        }\n"
     "        done = true;\n"
     "        clearTimeout(st.closing);\n"
     "        st.closing = 0;\n"
     "        if (h) {\n"
     "          google.maps.event.removeListener(h);\n"
     "        }\n"
     "        if (st.current === e && st.pinned && !st.open) {\n"
     "          to_card(e);\n"
     "        }\n"
     "      };\n"
     "      clearTimeout(st.closing);\n"
     "      try {\n"
     "        h = google.maps.event.addListenerOnce(iw, \"close\", function () {\n"
     "          setTimeout(go, 0);\n"
     "        });\n"
     "      } catch (x) {\n"
     "        h = null;\n"
     "      }\n"
     "      st.closing = setTimeout(go, CLOSE_MS);\n"
     "    }\n"
     "\n"
     "    function controls(o) {\n"),

    ("slp_avalon.js: controls() - Google's zoom off: ours stands where it stood",
     "        o.zoomControl = true;\n",
     "        //Part 4f. Ours instead: THE MAP'S BUTTONS, in the header above.\n"
     "        o.zoomControl = false;\n"),

    ("slp_avalon.js: the icon options read in one place; the numbered pins, their font, a dealer numbered",
     "    function hover_icon() {\n"
     "      if (st.icon === null) {\n"
     "        var v = \"\";\n"
     "        try {\n"
     "          v = String((slplus.options && slplus.options.avalon_map_hover_icon) || \"\");\n"
     "        } catch (x) {\n"
     "          v = \"\";\n"
     "        }\n"
     "        if (v) {\n"
     "          //A path from the site's root, resolved against the page.\n"
     "          var a = document.createElement(\"a\");\n"
     "          a.href = v;\n"
     "          v = a.href;\n"
     "        }\n"
     "        st.icon = v;\n"
     "      }\n"
     "      return st.icon;\n",
     "    //Part 4f. One of slp_avalon's icon options, resolved; \"\" for none.\n"
     "    function option_icon(name) {\n"
     "      var v = \"\";\n"
     "      try {\n"
     "        v = String((slplus.options && slplus.options[name]) || \"\");\n"
     "      } catch (x) {\n"
     "        v = \"\";\n"
     "      }\n"
     "      if (v) {\n"
     "        //A path from the site's root, resolved against the page.\n"
     "        var a = document.createElement(\"a\");\n"
     "        a.href = v;\n"
     "        v = a.href;\n"
     "      }\n"
     "      return v;\n"
     "    }\n"
     "\n"
     "    function hover_icon() {\n"
     "      if (st.icon === null) {\n"
     "        st.icon = option_icon(\"avalon_map_hover_icon\");\n"
     "      }\n"
     "      return st.icon;\n"
     "    }\n"
     "\n"
     "    //Part 4f. NUMBERS, in the header above: the numbered pin, or \"\" - and\n"
     "    //then no numbers at all.\n"
     "    function numbered() {\n"
     "      if (st.nicon === null) {\n"
     "        st.nicon = option_icon(\"avalon_map_number_icon\");\n"
     "        st.nlit = st.nicon ? option_icon(\"avalon_map_number_hover_icon\") : \"\";\n"
     "      }\n"
     "      return st.nicon;\n"
     "    }\n"
     "\n"
     "    //Part 4f. A numbered pin's icon: the art, with its number's centre in\n"
     "    //the middle of the head.\n"
     "    function pin(url) {\n"
     "      var o = { url: url };\n"
     "      try {\n"
     "        o.labelOrigin = new google.maps.Point(HEAD, HEAD);\n"
     "      } catch (x) {\n"
     "        //No Point: the number where Google centres a label.\n"
     "      }\n"
     "      return o;\n"
     "    }\n"
     "\n"
     "    //Part 4f. The page's own font - its body's - for the numbers.\n"
     "    function font() {\n"
     "      if (st.font === null) {\n"
     "        var f = \"\";\n"
     "        try {\n"
     "          f = String(window.getComputedStyle(document.body).fontFamily || \"\");\n"
     "        } catch (x) {\n"
     "          f = \"\";\n"
     "        }\n"
     "        st.font = (f ? f + \", \" : \"\") + \"Arial, sans-serif\";\n"
     "      }\n"
     "      return st.font;\n"
     "    }\n"
     "\n"
     "    //Part 4f. Hidden on screen, read by a screen reader.\n"
     "    function unseen(t) {\n"
     "      var s = make(\"span\", \"avalon-num__sr\");\n"
     "      s.appendChild(document.createTextNode(t));\n"
     "      return s;\n"
     "    }\n"
     "\n"
     "    //Part 4f. A dealer numbered: its pin, then its card. A card numbered\n"
     "    //before - the same results bound again - is numbered afresh.\n"
     "    function number(e, n) {\n"
     "      var g = e.marker.__gmarker;\n"
     "      var name = name_of(e.info);\n"
     "      e.n = n;\n"
     "      try {\n"
     "        g.setIcon(pin(st.nicon));\n"
     "        g.setLabel({ text: String(n), color: \"#000000\", fontFamily: font(), fontSize: \"13px\", fontWeight: \"700\",\n"
     "                     className: \"avalon-pin-num\" });\n"
     "        g.setTitle(\"Number \" + n + (name ? \", \" + name : \"\"));\n"
     "      } catch (x) {\n"
     "        //A pin that takes none of it stays as SLP drew it.\n"
     "      }\n"
     "      var c = card_of(e);\n"
     "      var h = c && typeof c.querySelector === \"function\" ? c.querySelector(\".store_locator_name\") : null;\n"
     "      if (!h || typeof h.insertBefore !== \"function\") {\n"
     "        return;\n"
     "      }\n"
     "      var old = h.querySelector(\".avalon-num\");\n"
     "      if (old && old.parentNode) {\n"
     "        old.parentNode.removeChild(old);\n"
     "      }\n"
     "      var s = make(\"span\", \"avalon-num\");\n"
     "      s.appendChild(unseen(\"Number \"));\n"
     "      s.appendChild(document.createTextNode(String(n)));\n"
     "      s.appendChild(unseen(\", \"));\n"
     "      h.insertBefore(s, h.firstChild);\n"),

    ("slp_avalon.js: lit()'s comment - a numbered pin lit keeps its number",
     "    function lit(e, on) {\n",
     "    //Part 4f: a numbered pin, the numbered pin lit, its number kept.\n"
     "    function lit(e, on) {\n"),

    ("slp_avalon.js: lit() - a numbered pin lights as the numbered pin lit",
     "      var url = hover_icon();\n",
     "      var url = e.n ? st.nlit : hover_icon();\n"),

    ("slp_avalon.js: lit() - the numbered pin lit, its number where it was",
     "          g.setIcon(url);\n",
     "          g.setIcon(e.n ? pin(url) : url);\n"),

    ("slp_avalon.js: resized() - Reset placed first",
     "    function resized() {\n"
     "      st.rs = 0;\n",
     "    //\n"
     "    //Part 4f. Reset goes where the layout now wants it first, whatever\n"
     "    //else there is to do; the focus the bubble had goes to the card once\n"
     "    //Google's close event has come (FOCUS AFTER NARROWING, above).\n"
     "    function resized() {\n"
     "      st.rs = 0;\n"
     "      place_reset();\n"),

    ("slp_avalon.js: resized() - focus to the card once Google's close event has come",
     "        to_card(e);\n"
     "      }\n",
     "        to_card_after(cm.infowindow, e);\n"
     "      }\n"),

    ("slp_avalon.js: attach() - the map's buttons, once",
     "        });\n"
     "    }\n",
     "        });\n"
     "      //Part 4f. The map's buttons, once.\n"
     "      buttons(cm);\n"
     "    }\n"),

    ("slp_avalon.js: bind()'s comment - numbers, and the view to reset to",
     "    //id - SLP's own order only when an id is missing.\n",
     "    //id - SLP's own order only when an id is missing. Part 4f: numbered,\n"
     "    //in SLP's order, where there are numbers; the view to reset to read\n"
     "    //once markers_dropped has been handled.\n"),

    ("slp_avalon.js: bind() - a count for the numbers",
     "      for (var i = 0; i < markers.length; i++) {\n",
     "      var n = 0;\n"
     "      for (var i = 0; i < markers.length; i++) {\n"),

    ("slp_avalon.js: bind() - each dealer numbered; the pin it lights to preloaded; the view read after markers_dropped",
     "      }\n"
     "      if (hover_icon() && typeof Image === \"function\") {\n"
     "        new Image().src = hover_icon();\n"
     "      }\n",
     "        if (numbered()) {\n"
     "          number(e, ++n);\n"
     "        }\n"
     "      }\n"
     "      var icon = numbered() ? st.nlit : hover_icon();\n"
     "      if (icon && typeof Image === \"function\") {\n"
     "        new Image().src = icon;\n"
     "      }\n"
     "      clearTimeout(st.vt);\n"
     "      st.vt = setTimeout(take, 0);\n"),

]


# ===========================================================================
# class.slp_avalon.php (CRLF; written LF here)
# ===========================================================================

CLASS_EDITS = [

    ("class: the Part 4f note among the registrations - nothing new registered",
     "            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_frame'), 120, 1);\n"
     "            //\n",
     "            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_frame'), 120, 1);\n"
     "            //\n"
     "            // v0.0.27 Part 4f. Reset and numbered pins on the find-a-dealer\n"
     "            // map, all slp_avalon.js's: the numbered pins' two URLs ride in\n"
     "            // the script options beside the hover pin's, from Part 4c's\n"
     "            // avalon_js_options_map() at 110, so nothing new is registered,\n"
     "            // and every new selector names #map_sidebar or .gm-style, which\n"
     "            // Part 4c put on WP Rocket's safelist.\n"
     "            //\n"),

    ("class: avalon_map_number_icon(), avalon_map_number_hover_icon() and the check they share",
     "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n",
     "         * v0.0.27 Part 4f. The numbered pin's URL, or ''.\n"
     "         *\n"
     "         * The option avalon_map_number_icon: the dealer's pin at rest on the\n"
     "         * find-a-dealer map, with its number drawn on its head by\n"
     "         * slp_avalon.js (30 x 40 art, the number at 15, 15). Set, the\n"
     "         * dealers of each search are numbered 1 to n on the pins and the\n"
     "         * cards; empty or not a URL, there are no numbers and the pins are\n"
     "         * SLP's. Read as avalon_map_hover_icon() reads its own. Store\n"
     "         * pages are untouched: their map prints map_end_icon.\n"
     "         */\n"
     "        public static function avalon_map_number_icon(){\n"
     "            return self::avalon_map_icon_url( 'avalon_map_number_icon' );\n"
     "        }\n"
     "\n"
     "        /**\n"
     "         * v0.0.27 Part 4f. The numbered pin lit - hovered, chosen, its\n"
     "         * bubble open - or ''. The option avalon_map_number_hover_icon,\n"
     "         * read the same way. Without it a numbered pin lit is only raised.\n"
     "         */\n"
     "        public static function avalon_map_number_hover_icon(){\n"
     "            return self::avalon_map_icon_url( 'avalon_map_number_hover_icon' );\n"
     "        }\n"
     "\n"
     "        /**\n"
     "         * v0.0.27 Part 4f. An icon option as a URL, or '': an http(s) URL,\n"
     "         * or a path from the site's root, which slp_avalon.js resolves\n"
     "         * against the page. Anything else, and an option that is not a\n"
     "         * string, is none.\n"
     "         */\n"
     "        private static function avalon_map_icon_url( $name ){\n"
     "            $v = get_option( $name, '' );\n"
     "            if ( ! is_string( $v ) ) {\n"
     "                return '';\n"
     "            }\n"
     "            $v = trim( $v );\n"
     "            if ( '' === $v || 0 === strpos( $v, '//' )\n"
     "                 || ( '/' !== $v[0] && ! preg_match( '#^https?://#i', $v ) ) ) {\n"
     "                return '';\n"
     "            }\n"
     "            return (string) esc_url_raw( $v, array( 'http', 'https' ) );\n"
     "        }\n"
     "\n"
     "        /**\n"
     "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n"),

    ("class: avalon_js_options_map()'s docblock - the numbered pins ride along",
     "         * options that are not an array, pass through.\n"
     "         */\n",
     "         * options that are not an array, pass through.\n"
     "         *\n"
     "         * Part 4f. The numbered pins ride along the same way, as\n"
     "         * avalon_map_number_icon and avalon_map_number_hover_icon.\n"
     "         */\n"),

    ("class: avalon_js_options_map() - the numbered pins in the script options",
     "            $options['avalon_map_hover_icon'] = self::avalon_map_hover_icon();\n"
     "            return $options;\n",
     "            $options['avalon_map_hover_icon'] = self::avalon_map_hover_icon();\n"
     "            $options['avalon_map_number_icon'] = self::avalon_map_number_icon();\n"
     "            $options['avalon_map_number_hover_icon'] = self::avalon_map_number_hover_icon();\n"
     "            return $options;\n"),

]


# ===========================================================================
# The builds
# ===========================================================================

P4F_MARK = "/* ----------------------------------------- Part 4f: the numbered cards */"


def build_css(css):
    for label, old, new in CSS_EDITS:
        css = sub_once(css, old, new, label)
    b = bare_css(css)
    check('\r' not in css and all(ord(c) < 128 for c in css), 'css: LF and ASCII')
    check(b.count('{') == b.count('}'), 'css: braces balance')
    check('!important' not in b, 'css: no !important in any rule')
    check(b.count('@container') == 1 and b.count('container-type: inline-size;') == 1,
          'css: still one container query and one container declaration - the cards\'')
    check(b.count('.avalon-hours {\n  position: relative;\n}\n') == 1,
          'css: .avalon-hours positioned, once - the box its hidden Hours: is placed in')
    check(css.count(P4F_MARK) == 1 and css.index(P4F_MARK) > css.index('/* ------------------------------------------ Part 4e: the chosen card */'),
          'css: the Part 4f block once, after Part 4e\'s, ending the file')
    blk = bare_css(css[css.index(P4F_MARK):])
    sels = []
    for group in re.findall(r'([^{}@]+)\{[^{}]*\}', re.sub(r'@media[^{]*\{', '', blk)):
        sels += [s.strip() for s in group.split(',') if s.strip()]
    check(len(sels) >= 20 and all(s.startswith('#map_sidebar ') or s.startswith('#map .gm-style ') for s in sels),
          'css: every Part 4f selector starts #map_sidebar or #map .gm-style - on WP Rocket\'s safelist ({})'.format(len(sels)))
    check(b.count('position: absolute;') == 3 and b.count('position: relative;') == 3,
          'css: three absolute rules - the two hidden words\' and the bars of + and - - and the three boxes they are placed in')
    check(blk.count('color: var(--avalon-num-text, #000);') == 1 and blk.count('outline: 2px solid #000;') == 1,
          'css: the disc\'s number black; the buttons\' keyboard ring black')
    return css


def build_map_js(js):
    for label, old, new in MAP_JS_EDITS:
        js = sub_once(js, crlf(old), crlf(new), label)
    check(js.count("\n") == js.count("\r\n") and js.count("\r") == js.count("\r\n"), 'slp_avalon.js: pure CRLF')
    check(all(ord(c) < 128 for c in js), 'slp_avalon.js: ASCII')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')') and js.count('[') == js.count(']'),
          'slp_avalon.js: braces, parentheses and brackets balance')
    blk = js.split('var avalon_map = (function () {')[1].split('  })();')[0]
    for banned in ('innerHTML', 'eval(', 'new Function', '.html(', 'setInterval', 'document.write'):
        check(banned not in blk, 'slp_avalon.js: the block never uses ' + banned)
    check(blk.count('o.zoomControl = false;') == 1 and blk.count('zoomControl: true') == 1,
          'slp_avalon.js: Google\'s zoom off in controls(), and back only where the map has no controls to add to')
    check(blk.count('var PHONE = "(max-width: 767px), (max-height: 500px)";') == 1 and blk.count('var HEAD = 15;') == 1,
          'slp_avalon.js: one phone query; the number at (15, 15)')
    check(blk.count('color: "#000000"') == 1 and blk.count('"Number " + n') == 1 and blk.count('unseen("Number ")') == 1,
          'slp_avalon.js: black numbers; "Number n, " on the pin and in the heading')
    check(blk.count('st.vt = setTimeout(take, 0);') == 1 and blk.count('to_card_after(cm.infowindow, e);') == 1
          and blk.count('addListenerOnce(iw, "close"') == 1,
          'slp_avalon.js: the view read once markers_dropped is handled; focus to the card after Google\'s close')
    check(blk.count('iw.setOptions({ ariaLabel: name_of(info) });') == 1 and blk.count('b.style.minWidth = ') == 1,
          'slp_avalon.js: Part 4e\'s bubble untouched - Google given no width')
    check(js.count("'City, State, or ZIP'") == 1, 'slp_avalon.js: Part 4d\'s placeholder untouched')
    return js


def build_class(php):
    before = [php.count(w) for w in ('add_filter(', 'add_action(', 'add_shortcode(')]
    for label, old, new in CLASS_EDITS:
        php = sub_once(php, crlf(old), crlf(new), label)
    check(php.count("\n") == php.count("\r\n") and php.count("\r") == php.count("\r\n"), 'class: pure CRLF')
    added = ''.join(new for _, _, new in CLASS_EDITS)
    check(all(ord(c) < 128 for c in added) and '\t' not in added, 'class: the inserted PHP is ASCII, no tabs')
    check(php.count('public static function avalon_map_number_icon(){') == 1
          and php.count('public static function avalon_map_number_hover_icon(){') == 1
          and php.count('private static function avalon_map_icon_url( $name ){') == 1,
          'class: the two readers and their check, each defined once')
    check(php.count("$options['avalon_map_number_icon'] = self::avalon_map_number_icon();") == 1
          and php.count("$options['avalon_map_number_hover_icon'] = self::avalon_map_number_hover_icon();") == 1,
          'class: both options into the script options, once each')
    check([php.count(w) for w in ('add_filter(', 'add_action(', 'add_shortcode(')] == before
          and php.count("add_filter('slp_js_options'") == 4 and php.count("add_filter('rocket_rucss_safelist'") == 3,
          'class: no new registration - as many filters, actions and shortcodes as Part 4d\'s; four slp_js_options callbacks, three safelists')
    return php


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part4f.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part4f  slp_avalon 0.0.27  PART 4f (Reset, numbered pins and cards, focus, the white bar)")
    print("")

    texts = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                     "       src_dir must hold Part 4e's stylesheet and slp_avalon.js and Part 4d's class."
                     .format(name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        texts[name] = text
    hjs = os.path.join(src_dir, 'avalon-hours.js')
    if os.path.isfile(hjs):
        _, got_md5, got_size = read_exact(hjs)
        if (got_md5, got_size) != HOURS_JS_PIN:
            sys.exit("ABORT avalon-hours.js in src_dir is {} / {} bytes: Part 4f runs Part 4e's, {} / {} bytes"
                     .format(got_md5, got_size, HOURS_JS_PIN[0], HOURS_JS_PIN[1]))
        print("  input OK  {:<24} {} {} bytes  (not patched)".format('avalon-hours.js', got_md5, got_size))
    print("")

    out = {
        'avalon-hours.css':     build_css(texts['avalon-hours.css']),
        'slp_avalon.js':        build_map_js(texts['slp_avalon.js']),
        'class.slp_avalon.php': build_class(texts['class.slp_avalon.php']),
    }
    print("")

    raws = {}
    for name, text in out.items():
        raw = text.encode('iso-8859-1')
        got = (hashlib.md5(raw).hexdigest(), len(raw))
        pin = OUT_PINS[name]
        if pin is not None and got != pin:
            sys.exit("ABORT {} is {} / {} bytes, expected {} / {} - nothing written".format(
                name, got[0], got[1], pin[0], pin[1]))
        raws[name] = raw
    for name, raw in raws.items():
        io.open(os.path.join(out_dir, name), 'wb').write(raw)
        print("  output    {:<24} {} {:>6} bytes  CR={}{}".format(
            name, hashlib.md5(raw).hexdigest(), len(raw), raw.count(b'\r'),
            '  (pinned)' if OUT_PINS[name] is not None else '  (NOT YET PINNED)'))
    print("")
    print("  note      avalon-hours.js is not an input: it does not change (588939bf, 18,319 bytes).")
    print("  note      slp_avalon.php is not an input: it does not change.")
    print("  note      two new options, read by the class: avalon_map_number_icon, avalon_map_number_hover_icon.")
    print("  note      no schema change. HOURS_DB_VERSION stays 2.")
    print("")
    if any(p is None for p in OUT_PINS.values()):
        print("output NOT pinned - not a release build")
        sys.exit(1)
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
