#!/usr/bin/env python3
"""
control-v035.py  r1  2026-10-07
SLP Dealer Guard - negative controls for suite-v035, suite-cards and
suite-map r2 (slp_avalon v0.0.27 Part 4d).

WITHOUT THIS FILE IN THE REPO, A CLEAN SCORE ASSERTS NOTHING. A suite that
has only ever been run against a build that works has not been shown to be
capable of failing. Each control below removes exactly one load-bearing
decision from the good build and nothing else, and the release is gated on
the suites catching every one of them, by a pinned count.

    python3 control-v035.py --in <class.slp_avalon.php> --css <avalon-hours.css>
                            --hjs <avalon-hours.js> --js <slp_avalon.js> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for each class control,
<dir>/css-<name>/avalon-hours.css for each stylesheet control,
<dir>/hjs-<name>/avalon-hours.js for each hours-script control and
<dir>/js-<name>/slp_avalon.js for each map-script control. A class control
is scored by suite-v035 with the good stylesheet; a stylesheet control by
suite-v035 with the good class; an hours-script control by suite-cards; a
map-script control by suite-map. Use an out* name for the output directory
- build/out*/ is gitignored (s0.218).

Every substitution is byte-exact and asserted unique before it is applied.
A control that could not be built is a hard error, never a skipped control:
a missing control is a decision nobody is testing.

HOW TO READ A SCORE. A control inside code Part 4d wrote also changes what
the suites pin - suite-v035's block pins and edit list, suite-cards' edit
list, suite-map's block pin and edit list - so it fails those checks by
construction. A behavioural control that fails ONLY those has not been
caught by any behaviour; every one below fails at least one more. The
touch_ controls change nothing Part 4d wrote and show that the identity
assertions - the only things carrying Part 4c's, Part 4b's and v0.0.25's
evidence forward - can fail.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "778185553d663139707b9d23dff00407"
IN_LEN = 339614
CSS_MD5 = "ffe117b6c77e2dfa3d082b5bb7c8ca10"
CSS_LEN = 26810
HJS_MD5 = "6d4c084f963e68271b0194926663eecf"
HJS_LEN = 17315
JS_MD5 = "00733408915197e40c78ec03eed61922"
JS_LEN = 103266

N = "\r\n"

# ---------------------------------------------------------------------------
# Class controls, scored by suite-v035 with the good stylesheet.
# (name, why it matters, [(old, new)])
# ---------------------------------------------------------------------------

CONTROLS = [

    # ---- the registration -----------------------------------------------

    ("frame_not_registered",
     "Without it the bubble layout reaches the browser unframed: no grid, "
     "no row for the buttons, which then sit outside the body.",
     [("            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_frame'), 120, 1);" + N, "")]),

    ("frame_at_100",
     "At 120, after Part 4c's callback at 110 has made the address one "
     "field; earlier, the frame would wrap the run 4c still rewrites.",
     [("'avalon_js_options_frame'), 120, 1);", "'avalon_js_options_frame'), 100, 1);")]),

    # ---- the hours markup -----------------------------------------------

    ("label_in_summary",
     "Hours: in the summary, as before Part 4d: no label column for it in "
     "the grid.",
     [("                     . ( $card ? '<span class=\"avalon-hours__sr\">Hours: </span>' : '' )",
       "                     . ( $card ? '<b class=\"avalon-label avalon-label--hours\">Hours:</b> ' : '' )"),
      ("                return '<b class=\"avalon-label avalon-label--hours\" aria-hidden=\"true\">Hours:</b>'" + N +
       "                     . '<div class=\"avalon-hours avalon-hours--card\" data-avalon-hours=\"' . $data . '\">'",
       "                return '<div class=\"avalon-hours avalon-hours--card\" data-avalon-hours=\"' . $data . '\">'")]),

    ("label_spoken",
     "The label stands beside the summary that says Hours: already; a "
     "screen reader would hear it twice.",
     [("'<b class=\"avalon-label avalon-label--hours\" aria-hidden=\"true\">Hours:</b>'",
       "'<b class=\"avalon-label avalon-label--hours\">Hours:</b>'")]),

    ("no_sr",
     "With the label hidden, the summary alone must say Hours: to a screen "
     "reader.",
     [("( $card ? '<span class=\"avalon-hours__sr\">Hours: </span>' : '' )", "''")]),

    ("caret_span",
     "SLP hides every empty span in a card as it inserts it: a span caret "
     "would never show.",
     [("'<i class=\"avalon-hours__caret\" aria-hidden=\"true\"></i></span></span></summary>'",
       "'<span class=\"avalon-hours__caret\" aria-hidden=\"true\"></span></span></span></summary>'")]),

    ("caret_loose",
     "The caret held to its word, or it starts a line alone.",
     [("'<span class=\"avalon-hours__status\">See <span class=\"avalon-hours__nowrap\">hours'" + N +
       "                     . '<i class=\"avalon-hours__caret\" aria-hidden=\"true\"></i></span></span></summary>'",
       "'<span class=\"avalon-hours__status\">See hours'" + N +
       "                     . '<i class=\"avalon-hours__caret\" aria-hidden=\"true\"></i></span></summary>'")]),

    ("no_time_span",
     "A day's hours in a span of their own, which a card keeps on one line "
     "so that TODAY wraps instead.",
     [("'</th><td><span class=\"avalon-hours__time\">' . esc_html( $d[2] ) . '</span></td></tr>'",
       "'</th><td>' . esc_html( $d[2] ) . '</td></tr>'")]),

    # ---- the bubble frame -----------------------------------------------

    ("info_past_block",
     "The hours field after the contact block's end: the wrapper would "
     "close outside it.",
     [("                if ( false !== $b && $b + strlen( $field ) <= $inner ) {",
       "                if ( false !== $b ) {")]),

    ("info_unwhole",
     "A run that does not close what it opens, wrapped, leaves the bubble's "
     "tags crossed.",
     [("                    if ( self::avalon_layout_whole( $run ) ) {",
       "                    if ( true ) {")]),

    ("actions_any_gap",
     "Only white space between the two buttons: anything else between them "
     "would be pulled into their row.",
     [("'/\\G\\s*<span\\b[^>]*\\sid=\"slp_bubble_website\"[^>]*>/'",
       "'/\\G[\\s\\S]*?<span\\b[^>]*\\sid=\"slp_bubble_website\"[^>]*>/'")]),

    ("info_twice",
     "A second pass - SLP runs slp_js_options more than once - would wrap "
     "the lines again.",
     [("            if ( false === strpos( $layout, 'avalon-bubble__info' ) ) {",
       "            if ( true ) {")]),

    ("actions_twice",
     "The same for the buttons' row.",
     [("            if ( false === strpos( $layout, 'avalon-bubble__actions' )" + N,
       "            if ( true" + N)]),

    ("depth_naive",
     "The contact block's end is found past any div inside it, by depth.",
     [("                $depth += ( '/' === $t[0][1] ) ? -1 : 1;",
       "                $depth = ( '/' === $t[0][1] ) ? 0 : 1;")]),

    ("regex_insert",
     "Inserted by offset, never through a regex replacement string, where "
     "$1 in a layout would be read as a group.",
     [("                        $layout = substr( $layout, 0, $a ) . '<div class=\"avalon-bubble__info\">' . $run . '</div>' . substr( $layout, $b );",
       "                        $layout = substr( $layout, 0, $a ) . preg_replace( '/^[\\s\\S]*$/', '<div class=\"avalon-bubble__info\">' . $run . '</div>', $run ) . substr( $layout, $b );")]),

    ("frame_any_type",
     "A bubble layout that is not a string is passed on, never cast.",
     [("isset( $options['bubblelayout'] ) && is_string( $options['bubblelayout'] ) ) {" + N +
       "                $options['bubblelayout'] = $this->avalon_bubble_layout_frame( $options['bubblelayout'] );",
       "isset( $options['bubblelayout'] ) ) {" + N +
       "                $options['bubblelayout'] = $this->avalon_bubble_layout_frame( $options['bubblelayout'] );")]),

    # ---- WP Rocket -------------------------------------------------------

    ("fa_kept",
     "The icons went with Part 4d; their safelist pattern with them.",
     [("            $list[] = '(.*).avalon-address(.*)';" + N,
       "            $list[] = '(.*).avalon-address(.*)';" + N + "            $list[] = '(.*).avalon-fa(.*)';" + N)]),

    # ---- identity --------------------------------------------------------

    ("touch_head",
     "The first line of the class, outside every Part 4d edit.",
     [("if (!class_exists('SLP_Avalon')){", "if (!class_exists('SLP_Avalon')) {")]),
]

# ---------------------------------------------------------------------------
# Stylesheet controls, scored by suite-v035 with the good class.
# ---------------------------------------------------------------------------

CSS_CONTROLS = [
    ("hover_ring",
     "Hover stays the theme's (the owner, 2026-10-07): the ring is for the "
     "keyboard alone.",
     [("#map_sidebar .results_wrapper .slp_result_contact a:focus-visible {\n",
       "#map_sidebar .results_wrapper .slp_result_contact a:focus-visible,\n#map_sidebar .results_wrapper .slp_result_contact a:hover {\n")]),

    ("ring_on_focus",
     "On :focus a mouse click would ring the button too.",
     [("#map_sidebar .results_wrapper .slp_result_contact a:focus-visible {\n",
       "#map_sidebar .results_wrapper .slp_result_contact a:focus {\n")]),

    ("buttons_stacked",
     "The two buttons side by side at equal widths, as drawn.",
     [("#map_sidebar .results_entry .slp_result_contact.slp_result_directions {\n  display: flex;\n  flex: 1 1 0;\n",
       "#map_sidebar .results_entry .slp_result_contact.slp_result_directions {\n  display: flex;\n  flex: 1 1 100%;\n")]),

    ("grid_important",
     "Ids outrank the theme's card rules; !important is never needed.",
     [("#map_sidebar .results_wrapper .results_entry {\n  display: grid;\n",
       "#map_sidebar .results_wrapper .results_entry {\n  display: grid !important;\n")]),

    ("week_unshifted",
     "The week pulled 15 px left, so its dot sits in the gap and the day "
     "names line up with the status.",
     [("  margin: 8px 0 0 -15px;\n", "  margin: 8px 0 0;\n")]),

    ("today_spoken",
     "TODAY is decoration with empty alternative text: the row already says "
     "aria-current=\"date\".",
     [("  content: \"TODAY\" / \"\";\n", "")]),

    ("time_everywhere",
     "The never-breaking hours belong to the cards and the bubble; the "
     "store page's narrow column may break them, as before.",
     [("#map_sidebar .avalon-hours--card .avalon-hours__time,\n.slp_info_bubble .avalon-hours--card .avalon-hours__time {\n",
       ".avalon-hours .avalon-hours__time {\n")]),

    ("no_container",
     "Without the size containers the narrow cards' week runs past the "
     "card's edge on a phone held sideways.",
     [("#map_sidebar .results_wrapper .results_entry,\n.slp_info_bubble .avalon-bubble__info {\n  container-type: inline-size;\n}\n", "")]),

    ("container_first",
     "The 250 px rule must come after the sideways one it overrides, at the "
     "same specificity.",
     [("\n@container (max-width: 250px) {\n  #map_sidebar .avalon-hours--card .avalon-hours__week,\n"
       "  .slp_info_bubble .avalon-hours--card .avalon-hours__week {\n    width: 100cqw;\n"
       "    margin-left: calc(100% - 100cqw);\n  }\n}\n", "\n"),
      ("/* A phone held sideways, a tablet: the week 20 px left, as approved. */\n",
       "@container (max-width: 250px) {\n  #map_sidebar .avalon-hours--card .avalon-hours__week,\n"
       "  .slp_info_bubble .avalon-hours--card .avalon-hours__week {\n    width: 100cqw;\n"
       "    margin-left: calc(100% - 100cqw);\n  }\n}\n\n"
       "/* A phone held sideways, a tablet: the week 20 px left, as approved. */\n")]),

    ("caret_after",
     "The caret is an element now; a generated one as well would draw two.",
     [(".avalon-hours .avalon-hours__caret {\n  display: none;\n}\n",
       ".avalon-hours .avalon-hours__summary::after {\n  content: \"\";\n}\n\n.avalon-hours .avalon-hours__caret {\n  display: none;\n}\n")]),

    ("bubble_name_tight",
     "The bubble's name carries its own spacing now that the black box has "
     "none.",
     [("  padding: 16px 16px 6px;\n", "")]),

    ("fade_always",
     "The fade shows only while there is more below.",
     [("  pointer-events: none;\n  visibility: hidden;\n}\n", "  pointer-events: none;\n}\n")]),

    ("body_scrolls_everywhere",
     "On a larger screen the bubble grows as it did; only a phone's body "
     "scrolls.",
     [("@media (max-width: 767px), (max-height: 500px) {\n  .slp_info_bubble .sl_popup_contact_info {\n",
       "@media all {\n  .slp_info_bubble .sl_popup_contact_info {\n")]),

    ("touch_part4c",
     "A word of Part 4c's Pegman comment, outside every Part 4d edit.",
     [("   inline rules, and the Street View control's Pegman has measured 0 px\n",
       "   inline rules, and the Street View control's Pegman has measured 0px \n")]),
]

# ---------------------------------------------------------------------------
# Hours-script controls, scored by suite-cards.
# ---------------------------------------------------------------------------

HJS_CONTROLS = [
    ("short_days",
     "The weekday in full, as the design has it.",
     [("  var DAYS = [\"Sunday\", \"Monday\", \"Tuesday\", \"Wednesday\", \"Thursday\", \"Friday\", \"Saturday\"];\n",
       "  var DAYS = [\"Sun\", \"Mon\", \"Tue\", \"Wed\", \"Thu\", \"Fri\", \"Sat\"];\n")]),

    ("last_splits_pm",
     "A time keeps its AM or PM: the line never ends \"Closes 5\".",
     [("/^([\\s\\S]*?)(\\S+(?: [AP]M)?)\\s*$/", "/^([\\s\\S]*?)(\\S+)\\s*$/")]),

    ("caret_span",
     "SLP hides a card's empty spans: the caret is an <i>.",
     [("    var c = doc.createElement(\"i\");\n", "    var c = doc.createElement(\"span\");\n")]),

    ("caret_in_word",
     "The caret keeps the line's colour, never the coloured word's.",
     [("      held.appendChild(caret());\n", "      (tail ? held : word).appendChild(caret());\n")]),

    ("no_nowrap",
     "The last word and the caret held on one line.",
     [("      held.className = \"avalon-hours__nowrap\";\n", "      held.className = \"avalon-hours__last\";\n")]),

    ("label_bubbles",
     "A click on Hours: goes no further, as a click in the block does: "
     "SLP's card click and main.js's .active never see it.",
     [("    l.addEventListener(\"click\", function (e) {\n      e.stopPropagation();\n",
       "    l.addEventListener(\"click\", function (e) {\n")]),

    ("label_every_pass",
     "Wired once, with the block: the minute timer enhances it again.",
     [("        el.addEventListener(\"click\", keep, false);\n        label(el);\n      }\n    }\n",
       "        el.addEventListener(\"click\", keep, false);\n      }\n    }\n"
       "    if ((\" \" + el.className + \" \").indexOf(\" avalon-hours--card \") >= 0) {\n      label(el);\n    }\n")]),

    ("label_any_sibling",
     "Only the Hours: label opens the week, never the line before it.",
     [("    if (!l || (\" \" + l.className + \" \").indexOf(\" avalon-label--hours \") < 0) {\n",
       "    if (!l) {\n")]),

    ("touch_part4b",
     "A word of Part 4b's lift() comment, outside every Part 4d edit.",
     [("   * Part 4b. An hours block's week opened inside the map's info bubble.\n",
       "   * Part 4b. An hours block's week, opened inside the map's info bubble.\n")]),
]

# ---------------------------------------------------------------------------
# Map-script controls, scored by suite-map.
# ---------------------------------------------------------------------------

JS_CONTROLS = [
    ("ring_on_hover",
     "A bubble opened by hovering marks nothing: only a choice does.",
     [("      if (!hover) {" + N + "        st.pinned = true;" + N + "        ring(e);" + N + "      }" + N,
       "      if (!hover) {" + N + "        st.pinned = true;" + N + "      }" + N + "      ring(e);" + N)]),

    ("ring_kept_on_close",
     "The chosen card is marked until its bubble closes.",
     [("      ring(null);" + N + "      restore();" + N, "      restore();" + N)]),

    ("ring_others_kept",
     "One card marked at a time, main.js's earlier mark included.",
     [("        if (on[i].id !== id) {" + N, "        if (false) {" + N)]),

    ("ring_classes_lost",
     "A card keeps every class it had when the mark goes on.",
     [("      n.className = on ? (n.className ? n.className + \" \" : \"\") + name" + N,
       "      n.className = on ? name" + N)]),

    ("click_no_ring",
     "A click inside a hovered bubble chooses it, and marks its card.",
     [("        c.addEventListener(\"click\", function (ev) {" + N + "          if (st.open) {" + N +
       "            st.pinned = true;" + N + "            ring(st.current);" + N,
       "        c.addEventListener(\"click\", function (ev) {" + N + "          if (st.open) {" + N +
       "            st.pinned = true;" + N)]),

    ("focusin_no_ring",
     "Focus moving in chooses it too.",
     [("          var from = ev && ev.relatedTarget;" + N + "          if (st.open) {" + N +
       "            st.pinned = true;" + N + "            ring(st.current);" + N,
       "          var from = ev && ev.relatedTarget;" + N + "          if (st.open) {" + N +
       "            st.pinned = true;" + N)]),

    ("fade_no_scroll",
     "The fade goes at the end of the scroll and comes back above it.",
     [("        s.addEventListener(\"scroll\", more, false);" + N, "")]),

    ("fade_toggle_bubbling",
     "toggle does not bubble: it is heard in the capture phase or not at "
     "all.",
     [("        s.addEventListener(\"toggle\", more, true);" + N, "        s.addEventListener(\"toggle\", more, false);" + N)]),

    ("fade_exact_end",
     "A phone scrolls in fractions of a pixel: within one of the end is "
     "the end.",
     [("s.scrollHeight - s.scrollTop - s.clientHeight > 1);", "s.scrollHeight - s.scrollTop - s.clientHeight > 0);")]),

    ("fade_no_timeout",
     "Looked at again once the opening is done and the content laid out.",
     [("      setTimeout(more, 0);" + N, "")]),

    ("fade_listeners_twice",
     "Google fires domready again for the same content: one set of "
     "listeners.",
     [("      if (s && !s.avalonMore) {" + N, "      if (s) {" + N)]),

    ("resize_no_more",
     "After a resize the body's height has changed: looked at again.",
     [("      st.rs = 0;" + N + "      more();" + N, "      st.rs = 0;" + N)]),

    ("placeholder_old",
     "The placeholder that fits the field (decision 4).",
     [("$(\"#addressInput\").attr('placeholder','City, State, or ZIP');", "$(\"#addressInput\").attr('placeholder','Enter City, State, or Zip Code');")]),

    ("touch_v025",
     "One word of v0.0.25's own code, which Part 4d must leave byte for byte.",
     [("    //Zoom map out by one, to fit infowindows" + N, "    //Zoom the map out by one, to fit infowindows" + N)]),
]


def build(src, subs, name):
    out = src
    for old, new in subs:
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
    ap.add_argument("--hjs", dest="hjs", required=True)
    ap.add_argument("--js", dest="js", required=True)
    ap.add_argument("--out", dest="out", required=True)
    args = ap.parse_args()

    print("control-v035.py  r1  2026-10-07")
    print("  negative controls for suite-v035, suite-cards and suite-map")
    print()

    for label, path, want_md5, want_len in (("class", args.src, IN_MD5, IN_LEN), ("css", args.css, CSS_MD5, CSS_LEN),
                                            ("hours", args.hjs, HJS_MD5, HJS_LEN), ("script", args.js, JS_MD5, JS_LEN)):
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

    names = ([c[0] for c in CONTROLS] + ["css-" + c[0] for c in CSS_CONTROLS]
             + ["hjs-" + c[0] for c in HJS_CONTROLS] + ["js-" + c[0] for c in JS_CONTROLS])
    if len(set(names)) != len(names):
        print("  REFUSED: two controls share a name")
        return 3

    src = open(args.src, "rb").read().decode(ENC)
    css = open(args.css, "rb").read().decode("ascii")
    hjs = open(args.hjs, "rb").read().decode("ascii")
    js = open(args.js, "rb").read().decode(ENC)
    os.makedirs(args.out, exist_ok=True)

    for title, items, text, prefix, fname, enc in (("class controls", CONTROLS, src, "", "class.slp_avalon.php", ENC),
                                                   ("stylesheet controls", CSS_CONTROLS, css, "css-", "avalon-hours.css", "ascii"),
                                                   ("hours-script controls", HJS_CONTROLS, hjs, "hjs-", "avalon-hours.js", "ascii"),
                                                   ("map-script controls", JS_CONTROLS, js, "js-", "slp_avalon.js", ENC)):
        print("  " + title)
        for name, why, subs in items:
            broken = build(text, subs, prefix + name)
            d = os.path.join(args.out, prefix + name)
            os.makedirs(d, exist_ok=True)
            raw = broken.encode(enc)
            open(os.path.join(d, fname), "wb").write(raw)
            print("    %-32s %s %7d" % (prefix + name, hashlib.md5(raw).hexdigest(), len(raw)))
        print()
    print("  %d class, %d stylesheet, %d hours-script and %d map-script controls written to %s"
          % (len(CONTROLS), len(CSS_CONTROLS), len(HJS_CONTROLS), len(JS_CONTROLS), args.out))
    return 0


if __name__ == "__main__":
    sys.exit(main())
