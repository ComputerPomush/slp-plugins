#!/usr/bin/env python3
"""
control-v037.py  r1  2026-10-08
SLP Dealer Guard - negative controls for suite-v037 and suite-map r4
(slp_avalon v0.0.27 Part 4f).

WITHOUT THIS FILE IN THE REPO, A CLEAN SCORE ASSERTS NOTHING. A suite that
has only ever been run against a build that works has not been shown to be
capable of failing. Each control below removes exactly one load-bearing
decision from the good build and nothing else, and the release is gated on
the suites catching every one of them, by a pinned count.

    python3 control-v037.py --in <class.slp_avalon.php> --css <avalon-hours.css>
                            --js <slp_avalon.js> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for each class control,
<dir>/css-<name>/avalon-hours.css for each stylesheet control and
<dir>/js-<name>/slp_avalon.js for each map-script control. A class control
is scored by suite-v037 with the good stylesheet; a stylesheet control by
suite-v037 with the good class; a map-script control by suite-map. Use an
out* name for the output directory - build/out*/ is gitignored (s0.218).

Every substitution is byte-exact and asserted unique before it is applied;
each anchor is written LF here and turned CRLF for the class and
slp_avalon.js. A control that could not be built is a hard error, never a
skipped control: a missing control is a decision nobody is testing.

HOW TO READ A SCORE. A control inside text Part 4f wrote also changes what
the suites pin - suite-v037's edit lists and its Part 4f block pin,
suite-map's block pin and edit list - so it fails those checks by
construction. A behavioural control that fails ONLY those has not been
caught by any behaviour; every one below fails at least one more. The
touch_ controls change nothing Part 4f wrote and show that the identity
assertions - the only things carrying Part 4e's and every earlier part's
evidence forward - can fail.

avalon-hours.js does not change in Part 4f, so it has no controls here:
control-v036.py's eight still describe it, and suite-cards, suite-hours
and suite-bubble still score it, unchanged.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "9d88d8e446fd02f1902e21866914ad0f"
IN_LEN = 342317
CSS_MD5 = "04fd4a0034c9f8c8959454bca34ef14a"
CSS_LEN = 32702
JS_MD5 = "7f419ecd4f21cf1b15cfa0656bb26eca"
JS_LEN = 136764

# ---------------------------------------------------------------------------
# Class controls, scored by suite-v037 with the good stylesheet. Written LF.
# (name, why it matters, [(old, new)])
# ---------------------------------------------------------------------------

CONTROLS = [
    ("number_relative_kept",
     "A pin is an http(s) URL or a path from the site's root: anything else - a "
     "relative name, javascript: - is no pin.",
     [("            if ( '' === $v || 0 === strpos( $v, '//' )\n"
       "                 || ( '/' !== $v[0] && ! preg_match( '#^https?://#i', $v ) ) ) {\n"
       "                return '';\n"
       "            }\n"
       "            return (string) esc_url_raw( $v, array( 'http', 'https' ) );\n"
       "        }\n"
       "\n"
       "        /**\n"
       "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n",
       "            if ( '' === $v || 0 === strpos( $v, '//' ) ) {\n"
       "                return '';\n"
       "            }\n"
       "            return (string) esc_url_raw( $v, array( 'http', 'https' ) );\n"
       "        }\n"
       "\n"
       "        /**\n"
       "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n")]),

    ("number_protocol_relative",
     "//host/pin.png takes the page's scheme and another site's file: no pin.",
     [("            if ( '' === $v || 0 === strpos( $v, '//' )\n"
       "                 || ( '/' !== $v[0] && ! preg_match( '#^https?://#i', $v ) ) ) {\n"
       "                return '';\n"
       "            }\n"
       "            return (string) esc_url_raw( $v, array( 'http', 'https' ) );\n"
       "        }\n"
       "\n"
       "        /**\n"
       "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n",
       "            if ( '' === $v\n"
       "                 || ( '/' !== $v[0] && ! preg_match( '#^https?://#i', $v ) ) ) {\n"
       "                return '';\n"
       "            }\n"
       "            return (string) esc_url_raw( $v, array( 'http', 'https' ) );\n"
       "        }\n"
       "\n"
       "        /**\n"
       "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n")]),

    ("number_not_string",
     "An option that is not a string is none - and raises no warning.",
     [("            $v = get_option( $name, '' );\n"
       "            if ( ! is_string( $v ) ) {\n"
       "                return '';\n"
       "            }\n"
       "            $v = trim( $v );\n",
       "            $v = trim( (string) get_option( $name, '' ) );\n")]),

    ("number_untrimmed",
     "A path pasted with spaces round it is still the path.",
     [("            $v = trim( $v );\n", "")]),

    ("number_unescaped",
     "What goes to the page goes through esc_url_raw(), http and https alone.",
     [("            return (string) esc_url_raw( $v, array( 'http', 'https' ) );\n"
       "        }\n"
       "\n"
       "        /**\n"
       "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n",
       "            return $v;\n"
       "        }\n"
       "\n"
       "        /**\n"
       "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\n")]),

    ("number_hover_wrong_option",
     "Each reader reads its own option.",
     [("            return self::avalon_map_icon_url( 'avalon_map_number_hover_icon' );\n",
       "            return self::avalon_map_icon_url( 'avalon_map_number_icon' );\n")]),

    ("options_no_lit_pin",
     "The numbered pin lit rides in the script options beside the numbered pin.",
     [("            $options['avalon_map_number_hover_icon'] = self::avalon_map_number_hover_icon();\n", "")]),

    ("options_unset_when_empty",
     "Always set - '' when there is none - so the script never reads a missing key.",
     [("            $options['avalon_map_number_icon'] = self::avalon_map_number_icon();\n",
       "            if ( self::avalon_map_number_icon() ) {\n"
       "                $options['avalon_map_number_icon'] = self::avalon_map_number_icon();\n"
       "            }\n")]),

    ("registered_twice",
     "Part 4f registers nothing: the options ride in Part 4c's callback.",
     [("            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_frame'), 120, 1);\n",
       "            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_frame'), 120, 1);\n"
       "            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_map'), 130, 1);\n")]),

    ("touch_class",
     "One word of Part 4d's own comment, which Part 4f must leave byte for byte.",
     [("         * v0.0.27 Part 4d. The bubble layout in three parts.\n",
       "         * v0.0.27 Part 4d: the bubble layout in three parts.\n")]),
]

# ---------------------------------------------------------------------------
# Stylesheet controls, scored by suite-v037 with the good class.
# ---------------------------------------------------------------------------

CSS_CONTROLS = [
    ("white_bar_back",
     "The white bar under the footer (the owner's screenshots 4803 to 4805): "
     ".avalon-hours is the box its hidden Hours: is placed in.",
     [(".avalon-hours {\n  position: relative;\n}\n", "")]),

    ("disc_unpositioned",
     "The disc is the box its own hidden words are placed in - or they "
     "stretch the page as the hidden Hours: did.",
     [("#map_sidebar .results_wrapper .avalon-num {\n  position: relative;\n",
       "#map_sidebar .results_wrapper .avalon-num {\n")]),

    ("disc_white_number",
     "Black numbers (the owner, 2026-10-08): 4.80:1 on the pink, where white "
     "is 4.37.",
     [("  color: var(--avalon-num-text, #000);\n", "  color: var(--avalon-num-text, #fff);\n")]),

    ("disc_grows_line",
     "The disc makes the name's line no taller: its margins take back what "
     "it adds.",
     [("  margin: 1px 10px -1px 0;\n", "  margin: 1px 10px 0 0;\n")]),

    ("disc_words_shown",
     "\"Number \" and the comma are for screen readers alone.",
     [("#map_sidebar .results_wrapper .avalon-num__sr {\n"
       "  position: absolute;\n"
       "  width: 1px;\n"
       "  height: 1px;\n"
       "  margin: -1px;\n"
       "  padding: 0;\n"
       "  overflow: hidden;\n"
       "  clip: rect(0 0 0 0);\n"
       "  clip-path: inset(50%);\n",
       "#map_sidebar .results_wrapper .avalon-num__sr {\n"
       "  position: absolute;\n"
       "  width: 1px;\n"
       "  height: 1px;\n"
       "  margin: -1px;\n"
       "  padding: 0;\n"
       "  overflow: hidden;\n")]),

    ("ring_gone",
     "A keyboard ring on every button of ours.",
     [("#map .gm-style .avalon-mapctl button:focus-visible {\n"
       "  outline: 2px solid #000;\n"
       "  outline-offset: -4px;\n"
       "}\n\n", "")]),

    ("ring_before_focus",
     "The ring comes after the pointer's no-outline: the same weight, so "
     "the later rule wins.",
     [("#map .gm-style .avalon-mapctl button:focus {\n"
       "  outline: none;\n"
       "  background-color: #fff;\n"
       "}\n\n",
       "#map .gm-style .avalon-mapctl button:focus-visible {\n"
       "  outline: 2px solid #000;\n"
       "  outline-offset: -4px;\n"
       "}\n\n"
       "#map .gm-style .avalon-mapctl button:focus {\n"
       "  outline: none;\n"
       "  background-color: #fff;\n"
       "}\n\n"),
      ("#map .gm-style .avalon-mapctl button:focus-visible {\n"
       "  outline: 2px solid #000;\n"
       "  outline-offset: -4px;\n"
       "}\n\n"
       "/* + and -, drawn", "/* + and -, drawn")]),

    ("hover_pink",
     "The theme's button:hover paints a button pink: ours stay white.",
     [("#map .gm-style .avalon-mapctl button:hover {\n"
       "  background-color: #fff;\n"
       "  color: #333;\n",
       "#map .gm-style .avalon-mapctl button:hover {\n"
       "  color: #333;\n")]),

    ("buttons_unscoped",
     "Every selector starts #map .gm-style: WP Rocket's safelist, and the "
     "weight over the theme's button rules.",
     [("#map .gm-style .avalon-mapctl button {\n", ".avalon-mapctl button {\n")]),

    ("disabled_lit",
     "A dimmed button is not brightened by a hover.",
     [("#map .gm-style .avalon-mapctl button[aria-disabled=\"true\"],\n"
       "#map .gm-style .avalon-mapctl button[aria-disabled=\"true\"]:hover {\n",
       "#map .gm-style .avalon-mapctl button[aria-disabled=\"true\"] {\n")]),

    ("important_used",
     "No !important: a site must be able to restyle its own map.",
     [("  outline: none;\n  background-color: #fff;\n", "  outline: none !important;\n  background-color: #fff;\n")]),

    ("minus_crossed",
     "- is one bar; + two.",
     [("#map .gm-style .avalon-mapctl__in::after {\n", "#map .gm-style .avalon-mapctl__in::after,\n#map .gm-style .avalon-mapctl__out::after {\n")]),

    ("group_on_the_edge",
     "10 px from the map's edges, where Google's zoom stood (the probe).",
     [("  gap: 10px;\n  margin: 10px;\n", "  gap: 10px;\n")]),

    ("corner_wide",
     "On a phone, Reset alone in the corner clears Map and Satellite.",
     [("#map .gm-style .avalon-mapctl--corner .avalon-mapctl__reset {\n  padding: 0 12px;\n}\n\n", "")]),

    ("touch_css",
     "One word of Part 4e's own comment, which Part 4f must leave byte for "
     "byte.",
     [("   second, so the eye finds it: the card's own background reached from a\n",
       "   second, so that the eye finds it: the card's own background reached from a\n")]),
]

# ---------------------------------------------------------------------------
# Map-script controls, scored by suite-map. Written LF; the file is CRLF.
# ---------------------------------------------------------------------------

JS_CONTROLS = [

    # ---- the map's buttons ----------------------------------------------

    ("zoom_left_on",
     "Google's zoom off: nothing can sit beside it, so ours stands there.",
     [("        o.zoomControl = false;\n", "        o.zoomControl = true;\n")]),

    ("no_buttons",
     "The buttons are made with the map.",
     [("      //Part 4f. The map's buttons, once.\n      buttons(cm);\n", "")]),

    ("no_fallback",
     "A map with no controls to add to gets Google's zoom back.",
     [("        if (typeof g.setOptions === \"function\") {\n"
       "          g.setOptions({ zoomControl: true });\n"
       "        }\n", "")]),

    ("group_on_top",
     "index -1: under Google's own controls at the right foot, the Pegman.",
     [("      c.group.index = -1;\n", "")]),

    ("reset_before_view",
     "No Reset until a search has drawn a view to go back to.",
     [("      var at = !st.view ? \"\" : phone() ? \"corner\" : \"group\";\n",
       "      var at = phone() ? \"corner\" : \"group\";\n")]),

    ("reset_never_corner",
     "On a phone, Reset alone in the top right corner.",
     [("      var at = !st.view ? \"\" : phone() ? \"corner\" : \"group\";\n",
       "      var at = !st.view ? \"\" : \"group\";\n")]),

    ("group_not_relaid",
     "A control that changes size goes out of Google's list and in again, so "
     "that Google lays it out at its new size.",
     [("      if (was === \"group\" || at === \"group\") {\n"
       "        dock(P.RIGHT_BOTTOM, c.group, true);\n"
       "      }\n", "")]),

    ("corner_left_in",
     "Widened, the corner leaves Google's list: an empty box there would push "
     "full screen down.",
     [("      if (was === \"corner\" || at === \"corner\") {\n", "      if (at === \"corner\") {\n")]),

    ("reset_focus_lost",
     "Moving Reset takes focus from it: given back.",
     [("      if (had && at && document.activeElement !== c.reset) {\n", "      if (false) {\n")]),

    ("dock_focus_lost",
     "Laying a control out again takes focus from what is in it: given back.",
     [("      if (had && document.activeElement !== a && document.body.contains(a)) {\n"
       "        try {\n"
       "          a.focus({ preventScroll: true });\n"
       "        } catch (x) {\n"
       "          //Refused: focus stays where the move left it.\n",
       "      if (false) {\n"
       "        try {\n"
       "          a.focus({ preventScroll: true });\n"
       "        } catch (x) {\n"
       "          //Refused: focus stays where the move left it.\n")]),

    ("resize_leaves_reset",
     "A resize - or full screen - moves Reset, whatever else it finds to do.",
     [("      st.rs = 0;\n      place_reset();\n", "      st.rs = 0;\n")]),

    ("reset_never_shown",
     "The view read, Reset shows.",
     [("      st.view = { c: c, z: z, w: d.clientWidth, h: d.clientHeight, fit: n > 0 && !!cm.bounds, n: n };\n"
       "      place_reset();\n",
       "      st.view = { c: c, z: z, w: d.clientWidth, h: d.clientHeight, fit: n > 0 && !!cm.bounds, n: n };\n")]),

    # ---- Reset ----------------------------------------------------------

    ("view_at_bind",
     "The view is read once markers_dropped has been handled - SLP's fit and "
     "v0.0.25's zoom out come after the pins are bound.",
     [("      st.vt = setTimeout(take, 0);\n", "      take();\n")]),

    ("always_refit",
     "The map the same size: that centre and zoom, nothing fitted again.",
     [("      if (v.fit && (d.clientWidth !== v.w || d.clientHeight !== v.h)) {\n", "      if (v.fit) {\n")]),

    ("never_refit",
     "The map another size: the dealers fitted again, so every one shows.",
     [("      if (v.fit && (d.clientWidth !== v.w || d.clientHeight !== v.h)) {\n", "      if (false) {\n")]),

    ("refit_no_tweak",
     "SLP's zoom tweak, as SLP applies it.",
     [("        z = g.getZoom() - (parseInt(o.zoom_tweak, 10) || 0);\n", "        z = g.getZoom();\n")]),

    ("refit_no_cap",
     "One dealer: no closer than 15, as SLP holds it.",
     [("        if (n < 2) {\n          z = Math.min(z, 15);\n        }\n", "")]),

    ("refit_no_zoom_out",
     "v0.0.25's zoom out by one, after SLP's.",
     [("      g.setZoom(g.getZoom() - 1);\n    }\n", "    }\n")]),

    ("refit_ignores_no_autozoom",
     "SLP's no-autozoom: its zoom level.",
     [("      if (o.no_autozoom === \"1\") {\n        z = parseInt(o.zoom_level, 10);\n      } else {\n",
       "      if (false) {\n        z = parseInt(o.zoom_level, 10);\n      } else {\n")]),

    ("refit_view_not_kept",
     "The refitted view is the one to go back to from then on.",
     [("        refit(cm, v.n);\n        take();\n", "        refit(cm, v.n);\n")]),

    ("nothing_found_refits",
     "A search that found no dealer has nothing of its own to fit: SLP's "
     "bounds are the last search's.",
     [("fit: n > 0 && !!cm.bounds, n: n };", "fit: !!cm.bounds, n: n };")]),

    ("reset_closes",
     "The map's view only: what is chosen and open stays.",
     [("      if (v.fit && (d.clientWidth !== v.w || d.clientHeight !== v.h)) {\n",
       "      close();\n      if (v.fit && (d.clientWidth !== v.w || d.clientHeight !== v.h)) {\n")]),

    # ---- + and - --------------------------------------------------------

    ("zoom_unbounded",
     "Never past either end.",
     [("      var to = Math.max(lim.min, Math.min(lim.max, Math.round(z) + dz));\n",
       "      var to = Math.round(z) + dz;\n")]),

    ("zoom_unrounded",
     "From the nearest level.",
     [("      var to = Math.max(lim.min, Math.min(lim.max, Math.round(z) + dz));\n",
       "      var to = Math.max(lim.min, Math.min(lim.max, z + dz));\n")]),

    ("dim_disabled",
     "aria-disabled, not disabled: a keyboard keeps its place on the button.",
     [("        b.setAttribute(\"aria-disabled\", \"true\");\n", "        b.setAttribute(\"disabled\", \"true\");\n")]),

    ("type_unheard",
     "The map type changes the most zoom: heard.",
     [("      google.maps.event.addListener(g, \"maptypeid_changed\", ends);\n", "")]),

    ("type_ignored",
     "Each map type its own most.",
     [("        hi = typeof mx === \"number\" ? mx : (t && typeof t.maxZoom === \"number\" ? t.maxZoom : 22);\n",
       "        hi = typeof mx === \"number\" ? mx : 22;\n")]),

    # ---- numbers --------------------------------------------------------

    ("numbers_always",
     "Numbers only where slp_avalon sets the numbered pin.",
     [("        if (numbered()) {\n          number(e, ++n);\n        }\n", "        number(e, ++n);\n")]),

    ("numbers_white",
     "Black numbers (the owner, 2026-10-08).",
     [("color: \"#000000\"", "color: \"#ffffff\"")]),

    ("numbers_off_centre",
     "The number on the middle of the head, (15, 15).",
     [("    var HEAD = 15;\n", "    var HEAD = 20;\n")]),

    ("pin_untitled",
     "The pin's title is its name: \"Number n, <dealer>\".",
     [("        g.setTitle(\"Number \" + n + (name ? \", \" + name : \"\"));\n", "")]),

    ("title_bare_number",
     "\"Number 4\" and then the name (the owner, 2026-10-07).",
     [("        g.setTitle(\"Number \" + n + (name ? \", \" + name : \"\"));\n",
       "        g.setTitle(n + (name ? \", \" + name : \"\"));\n")]),

    ("card_unnumbered",
     "The card's heading starts with the number.",
     [("      h.insertBefore(s, h.firstChild);\n", "")]),

    ("card_words_shown",
     "\"Number \" hidden on screen: the disc shows the number alone.",
     [("      s.appendChild(unseen(\"Number \"));\n", "      s.appendChild(document.createTextNode(\"Number \"));\n")]),

    ("card_numbered_twice",
     "The same results bound again: one number in a heading, never two.",
     [("      if (old && old.parentNode) {\n        old.parentNode.removeChild(old);\n      }\n", "")]),

    ("lit_bare_url",
     "The numbered pin lit keeps its number where it was: the icon carries "
     "the label's origin.",
     [("          g.setIcon(e.n ? pin(url) : url);\n", "          g.setIcon(url);\n")]),

    ("lit_hover_icon",
     "A numbered pin lights as the numbered pin lit, not the hover icon.",
     [("      var url = e.n ? st.nlit : hover_icon();\n", "      var url = hover_icon();\n")]),

    ("preload_hover_icon",
     "Preload the pin that will show.",
     [("      var icon = numbered() ? st.nlit : hover_icon();\n", "      var icon = hover_icon();\n")]),

    ("skipped_takes_number",
     "A pin without its result takes no number: the numbers stay 1 to n.",
     [("          number(e, ++n);\n", "          number(e, i + 1);\n")]),

    ("font_fixed",
     "The page's own font for the numbers.",
     [("        st.font = (f ? f + \", \" : \"\") + \"Arial, sans-serif\";\n", "        st.font = \"Arial, sans-serif\";\n")]),

    # ---- focus after narrowing -------------------------------------------

    ("focus_no_wait",
     "Rows 78 and 80: to the card after Google's close event, not before it.",
     [("        to_card_after(cm.infowindow, e);\n", "        to_card(e);\n")]),

    ("focus_no_fallback",
     "No close event from Google: to the card 0.3 s on all the same.",
     [("      st.closing = setTimeout(go, CLOSE_MS);\n", "")]),

    ("focus_during_close",
     "After the close event is done - whatever Google does with focus as it "
     "sends it.",
     [("          setTimeout(go, 0);\n", "          go();\n")]),

    ("focus_after_choice_ended",
     "Only while that dealer is still the one chosen, with no bubble.",
     [("        if (st.current === e && st.pinned && !st.open) {\n          to_card(e);\n", "        if (true) {\n          to_card(e);\n")]),

    # ---- identity -------------------------------------------------------

    ("touch_block",
     "One word of Part 4e's own comment inside the block, which Part 4f must "
     "leave byte for byte.",
     [("    //Part 4e. A dealer's card in the results, or null.\n",
       "    //Part 4e: a dealer's card in the results, or null.\n")]),

    ("touch_v025",
     "One word of v0.0.25's own code, which Part 4f must leave byte for byte.",
     [("    //Zoom map out by one, to fit infowindows\n", "    //Zoom the map out by one, to fit infowindows\n")]),
]


def crlf(s):
    assert "\r" not in s
    return s.replace("\n", "\r\n")


def build(src, subs, name, wide):
    out = src
    for old, new in subs:
        if wide:
            old, new = crlf(old), crlf(new)
        n = out.count(old)
        if n != 1:
            print("  REFUSED: control %s: anchor matched %d times, expected 1" % (name, n))
            print("    %r" % old[:140])
            sys.exit(4)
        out = out.replace(old, new, 1)
    if out == src:
        print("  REFUSED: control %s changes nothing" % name)
        sys.exit(4)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="src", required=True)
    ap.add_argument("--css", dest="css", required=True)
    ap.add_argument("--js", dest="js", required=True)
    ap.add_argument("--out", dest="out", required=True)
    args = ap.parse_args()

    print("control-v037.py  r1  2026-10-08")
    print("  negative controls for suite-v037 and suite-map r4")
    print()

    for label, path, want_md5, want_len in (("class", args.src, IN_MD5, IN_LEN), ("css", args.css, CSS_MD5, CSS_LEN),
                                            ("script", args.js, JS_MD5, JS_LEN)):
        raw = open(path, "rb").read()
        got = hashlib.md5(raw).hexdigest()
        print("  %-6s %s" % (label, path))
        print("    md5    %s" % got)
        print("    bytes  %d" % len(raw))
        if got != want_md5 or len(raw) != want_len:
            print()
            print("  REFUSED: %s is %s / %d, expected %s / %d" % (label, got, len(raw), want_md5, want_len))
            return 2
        print("    pinned OK")
    print()

    names = [c[0] for c in CONTROLS] + ["css-" + c[0] for c in CSS_CONTROLS] + ["js-" + c[0] for c in JS_CONTROLS]
    if len(set(names)) != len(names):
        print("  REFUSED: two controls share a name")
        return 3

    src = open(args.src, "rb").read().decode(ENC)
    css = open(args.css, "rb").read().decode("ascii")
    js = open(args.js, "rb").read().decode(ENC)
    os.makedirs(args.out, exist_ok=True)

    for title, items, text, prefix, fname, enc, wide in (("class controls", CONTROLS, src, "", "class.slp_avalon.php", ENC, True),
                                                         ("stylesheet controls", CSS_CONTROLS, css, "css-", "avalon-hours.css", "ascii", False),
                                                         ("map-script controls", JS_CONTROLS, js, "js-", "slp_avalon.js", ENC, True)):
        print("  " + title)
        for name, why, subs in items:
            broken = build(text, subs, prefix + name, wide)
            d = os.path.join(args.out, prefix + name)
            os.makedirs(d, exist_ok=True)
            raw = broken.encode(enc)
            open(os.path.join(d, fname), "wb").write(raw)
            print("    %-32s %s %7d" % (prefix + name, hashlib.md5(raw).hexdigest(), len(raw)))
        print()
    print("  %d class, %d stylesheet and %d map-script controls written to %s"
          % (len(CONTROLS), len(CSS_CONTROLS), len(JS_CONTROLS), args.out))
    return 0


if __name__ == "__main__":
    sys.exit(main())
