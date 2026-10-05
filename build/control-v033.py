#!/usr/bin/env python3
"""
control-v033.py  r1  2026-10-04
SLP Dealer Guard - negative controls for suite-v033 and suite-bubble
(slp_avalon v0.0.27 Part 4b).

WITHOUT THIS FILE IN THE REPO, A CLEAN SCORE ASSERTS NOTHING. A suite that
has only ever been run against a build that works has not been shown to be
capable of failing. Each control below removes exactly one load-bearing
decision from the good build and nothing else, and the release is gated on
the suites catching every one of them, by a pinned count.

    python3 control-v033.py --in <class.slp_avalon.php> --js <avalon-hours.js> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for each class control and
<dir>/js-<name>/avalon-hours.js for each script control. Use an out* name
for the output directory - build/out*/ is gitignored (s0.218).

Every substitution is byte-exact and asserted unique before it is applied.
A control that could not be built is a hard error, never a skipped control:
a missing control is a decision nobody is testing.

HOW TO READ A SCORE. Every control inside Part 4b's new code also changes
the code the suites pin (suite-v033's block pin, suite-bubble's functions
pin), so each fails that one check by construction. A behavioural control
that fails ONLY that check has not been caught by any behaviour; every one
below fails at least one more. The four touch_ controls change nothing Part
4b wrote and show that the identity assertions - the only things carrying
Part 4's evidence forward - can fail.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "da3bd46a6af3b34a9552f6b1119dc13f"
IN_LEN = 312186
JS_MD5 = "76a62b751b5556b96b79c2734b05dca8"
JS_LEN = 14683

N = "\r\n"

# ---------------------------------------------------------------------------
# Class controls, scored by suite-v033. (name, why it matters, [(old, new)])
# ---------------------------------------------------------------------------

CONTROLS = [

    # ---- the registrations ----------------------------------------------

    ("email_not_registered",
     "Without the filter no marker carries the Email: field; the bubble's "
     "email line prints nothing at all.",
     [("            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_email'), 25, 1);" + N, "")]),

    ("email_at_20",
     "At 25, after Part 4's labels at 20, so the two never depend on the "
     "order in which they were registered.",
     [("'avalon_marker_email'), 25, 1);", "'avalon_marker_email'), 20, 1);")]),

    ("bubble_not_registered",
     "Without it the bubble keeps SLP's layout: no Distance, no labels, no "
     "hours, the old mailto wrap.",
     [("            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_bubble'), 100, 1);" + N, "")]),

    ("bubble_at_90",
     "At 90 the rewrite can run before SLP Experience merges its stored "
     "bubble layout, which then replaces the rewritten one.",
     [("'avalon_js_options_bubble'), 100, 1);", "'avalon_js_options_bubble'), 90, 1);")]),

    ("rocket_bubble_not_registered",
     "Remove Unused CSS never sees a bubble - it exists only after a click - "
     "so the bubble's rules must be safelisted or they are stripped.",
     [("            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));" + N, "")]),

    # ---- the email field ------------------------------------------------

    ("email_no_url_guard",
     "is_email() accepts ? # % & / { } in the local part; in a mailto: URL "
     "they become headers, a fragment or an escape.",
     [("$to   = ( false === strpbrk( $raw, '?#%&/\\\\{}' ) && is_email( $raw ) ) ? $raw : '';",
       "$to   = ( is_email( $raw ) ) ? $raw : '';")]),

    ("email_no_is_email",
     "N/A, two addresses, markup: only an address is_email() accepts is "
     "linked.",
     [("$to   = ( false === strpbrk( $raw, '?#%&/\\\\{}' ) && is_email( $raw ) ) ? $raw : '';",
       "$to   = ( false === strpbrk( $raw, '?#%&/\\\\{}' ) ) ? $raw : '';")]),

    ("email_unescaped",
     "The field is inserted into the bubble raw; its text must be escaped "
     "here.",
     [("$text = esc_html( '' !== $to ? $to : $shown );",
       "$text = '' !== $to ? $to : $shown;")]),

    ("email_any_protocol",
     "esc_url() with the mailto protocol only: nothing else can become the "
     "link.",
     [("esc_url( 'mailto:' . $to, array( 'mailto' ) )",
       "esc_url( 'mailto:' . $to )")]),

    ("email_shown_wins",
     "The raw row wins over the displayed value, for the link and its "
     "text: the displayed value is SLP's, entity-encoded.",
     [("            if ( '' === $raw ) {" + N +
       "                $raw = $shown;" + N +
       "            }" + N,
       "            $raw = $shown;" + N)]),

    ("email_keeps_nbsp",
     "A no-break space round the displayed address counts as a space.",
     [("? trim( str_replace( \"\\xC2\\xA0\", ' '," + N +
       "                             html_entity_decode( (string) $marker['email'], ENT_QUOTES, 'UTF-8' ) ) ) : '';",
       "? trim( html_entity_decode( (string) $marker['email'], ENT_QUOTES, 'UTF-8' ) ) : '';")]),

    ("email_label_over_nothing",
     "Never \"Email:\" over nothing: invalid UTF-8 escapes to an empty "
     "string.",
     [("            if ( '' === $text ) {" + N +
       "                return $marker;" + N +
       "            }" + N, "")]),

    ("email_same_tab",
     "target=\"_blank\", as SLP's own mailto: wrap has it: a webmail "
     "handler opens beside the map, not over it.",
     [("'\" target=\"_blank\" rel=\"noopener\">' . $text . '</a>'",
       "'\">' . $text . '</a>'")]),

    ("email_label_plain",
     "Email: bold, as Address: and Phone: are.",
     [("'<b class=\"avalon-label\">Email:</b> '", "'Email: '")]),

    # ---- the bubble layout ----------------------------------------------

    ("layout_distance_twice",
     "A layout that already shows a distance, or a second pass, gets no "
     "second Distance line.",
     [("            if ( false === strpos( $layout, 'avalon-bubble-distance' )" + N +
       "                 && false === strpos( $layout, '[slp_location distance' )" + N +
       "                 && preg_match( $addr, $layout, $m, PREG_OFFSET_CAPTURE ) ) {",
       "            if ( preg_match( $addr, $layout, $m, PREG_OFFSET_CAPTURE ) ) {")]),

    ("layout_address_label_twice",
     "A second pass must not print Address: twice.",
     [("            if ( false === strpos( $layout, 'avalon_address_label' )" + N +
       "                 && preg_match( $addr, $layout, $m, PREG_OFFSET_CAPTURE ) ) {",
       "            if ( preg_match( $addr, $layout, $m, PREG_OFFSET_CAPTURE ) ) {")]),

    ("layout_id_in_data_id",
     "An id is matched only as an attribute of its own: data-id=\"...\" is "
     "not the address span.",
     [("$addr   = '/<span\\b[^>]*\\sid=\"slp_bubble_address\"[^>]*>/';",
       "$addr   = '/<span\\b[^>]*id=\"slp_bubble_address\"[^>]*>/';")]),

    ("layout_phone_runaway",
     "The phone step reads no further than its own span; a lazy match runs "
     "on into the next span's number and swallows what lies between.",
     [("preg_match( '/(<span\\b[^>]*\\sid=\"slp_bubble_phone\"[^>]*>)(?:<span\\b(?![^>]*\\sid=\"slp_bubble_)[^>]*>(?:(?!<\\/?span\\b)[\\s\\S])*<\\/span>|(?!<\\/?(?:span|div)\\b)[\\s\\S])*?\\[slp_location\\s+phone\\b[^\\]]*\\]\\s*<\\/span>/',",
       "preg_match( '/(<span\\b[^>]*\\sid=\"slp_bubble_phone\"[^>]*>)[\\s\\S]*?\\[slp_location\\s+phone\\b[^\\]]*\\]\\s*<\\/span>/',")]),

    ("layout_email_not_moved",
     "Order: the email beside the phone, the other way to reach the dealer.",
     [("                if ( preg_match( $phone, $layout, $p, PREG_OFFSET_CAPTURE ) ) {",
       "                if ( false ) {")]),

    ("layout_email_copied",
     "Moved, not copied: the old span goes.",
     [("                        $layout = substr_replace( $layout, $field, $end, 0 );" + N +
       "                        $layout = substr_replace( $layout, '', $at, $len );" + N,
       "                        $layout = substr_replace( $layout, $field, $end, 0 );" + N)]),

    ("layout_email_stale_offset",
     "Insert at the later offset first: cutting the email span first moves "
     "everything after it, and the insertion lands inside the wrong text.",
     [("                        $layout = substr_replace( $layout, $field, $end, 0 );" + N +
       "                        $layout = substr_replace( $layout, '', $at, $len );" + N,
       "                        $layout = substr_replace( $layout, '', $at, $len );" + N +
       "                        $layout = substr_replace( $layout, $field, $end, 0 );" + N)]),

    ("layout_email_nested_span",
     "An email span with a span inside is not SLP's; left as it was.",
     [("preg_match( '/(<span\\b[^>]*\\sid=\"slp_bubble_email\"[^>]*>)((?:(?!<\\/?span\\b)[\\s\\S])*)<\\/span>/',",
       "preg_match( '/(<span\\b[^>]*\\sid=\"slp_bubble_email\"[^>]*>)([\\s\\S]*?)<\\/span>/',")]),

    ("layout_hours_after_phone",
     "Hours last, after the email, so opening the week moves nothing above "
     "it.",
     [("                 && ( preg_match( '/<span\\b[^>]*\\sid=\"slp_bubble_email\"[^>]*>\\[slp_location avalon_email_html\\]<\\/span>/'," + N +
       "                                  $layout, $m, PREG_OFFSET_CAPTURE )" + N +
       "                      || preg_match( $phone, $layout, $m, PREG_OFFSET_CAPTURE ) ) ) {",
       "                 && preg_match( $phone, $layout, $m, PREG_OFFSET_CAPTURE ) ) {")]),

    ("jsopts_any_type",
     "A bubble layout that is not a string is passed through, never cast "
     "to \"Array\".",
     [("isset( $options['bubblelayout'] ) && is_string( $options['bubblelayout'] ) ) {",
       "isset( $options['bubblelayout'] ) ) {")]),

    ("rocket_bubble_short",
     "The bubble wrapper's own rule (weight 400, the line breaks) must "
     "survive Remove Unused CSS too.",
     [("            $list[] = '(.*).slp_info_bubble(.*)';" + N, "")]),

    ("rocket_bubble_unprefixed",
     "WP Rocket 3.11.0.2 and later read a safelist entry from the "
     "selector's start; '.avalon-email' alone matches no selector here.",
     [("$list[] = '(.*).avalon-email(.*)';", "$list[] = '.avalon-email';")]),

    # ---- regions Part 4b must not touch ----------------------------------

    ("touch_constant",
     "Part 3b's refresh margin, outside every Part 4b edit.",
     [("const HOURS_REFRESH_MARGIN_DAYS = 2;", "const HOURS_REFRESH_MARGIN_DAYS = 3;")]),

    ("touch_head",
     "The first line of the class, outside every Part 4b edit.",
     [("if (!class_exists('SLP_Avalon')){", "if (!class_exists('SLP_Avalon')) {")]),

    ("touch_part4_comment",
     "One word of Part 4's own block, which Part 4b must leave byte for "
     "byte.",
     [("         * by sl_id, which holds one of N." + N,
       "         * by sl_id, which holds one of many." + N)]),

    ("touch_part4_labels_priority",
     "Part 4's labels registration, beside the Part 4b ones.",
     [("'avalon_marker_labels'), 20, 1);", "'avalon_marker_labels'), 21, 1);")]),
]

# ---------------------------------------------------------------------------
# Script controls, against avalon-hours.js, scored by suite-bubble.
# ---------------------------------------------------------------------------

JS_CONTROLS = [
    ("scan_every_batch",
     "Google redraws map tiles inside #map all the time; only a batch that "
     "brought in an hours block is worth a scan.",
     [("        if (added(records)) {\n          scan(map);\n        }\n",
       "        scan(map);\n")]),
    ("added_any_element",
     "Any element is not an hours block.",
     [("(n.hasAttribute(\"data-avalon-hours\") || n.querySelector(\"[data-avalon-hours]\"))",
       "true")]),
    ("added_reads_text_nodes",
     "A text node has no attributes; asking it throws inside the observer.",
     [("        if (n && n.nodeType === 1 &&\n", "        if (n &&\n")]),
    ("added_self_only",
     "The bubble arrives as a wrapper round the block, never the block "
     "alone.",
     [("(n.hasAttribute(\"data-avalon-hours\") || n.querySelector(\"[data-avalon-hours]\"))",
       "(n.hasAttribute(\"data-avalon-hours\"))")]),
    ("toggle_not_capture",
     "toggle does not bubble: a listener on the document hears it only in "
     "the capture phase.",
     [("    }, true);\n    if (root.addEventListener) {\n",
       "    }, false);\n    if (root.addEventListener) {\n")]),
    ("lift_on_close",
     "Closing the week shrinks the bubble; nothing to bring into view.",
     [("    if (!d || d.open !== true || !up(d, \"avalon-hours\")) {\n",
       "    if (!d || !up(d, \"avalon-hours\")) {\n")]),
    ("lift_any_details",
     "Only an hours block's week moves the map.",
     [("    if (!d || d.open !== true || !up(d, \"avalon-hours\")) {\n",
       "    if (!d || d.open !== true) {\n")]),
    ("lift_no_margin",
     "The bubble's top comes to rest 8 px inside the map, not on its edge.",
     [("    var gap = iw.getBoundingClientRect().top - box.getBoundingClientRect().top - 8;\n",
       "    var gap = iw.getBoundingClientRect().top - box.getBoundingClientRect().top;\n")]),
    ("lift_rounds_in",
     "A fraction rounds away from the edge, never leaving less than 8 px.",
     [("      map.panBy(0, Math.floor(gap));\n", "      map.panBy(0, Math.ceil(gap));\n")]),
    ("lift_wrong_way",
     "panBy(0, negative) moves the map's content down.",
     [("      map.panBy(0, Math.floor(gap));\n", "      map.panBy(0, -Math.floor(gap));\n")]),
    ("lift_uncaught",
     "A layout read that throws must not escape the listener.",
     [("      try {\n        lift(e);\n      } catch (x) {\n        /* The week stays open; the map just does not move. */\n      }\n",
       "      lift(e);\n")]),
    ("lift_trusts_panby",
     "SLP's map object may be absent or changed: no panBy, no pan, no "
     "error.",
     [("    if (!box || !map || typeof map.panBy !== \"function\") {\n",
       "    if (!box || !map) {\n")]),
    ("lift_measures_bubble",
     "The gap is the bubble's top against the MAP's top (.gm-style), not "
     "against itself.",
     [("    var box = iw ? up(iw, \"gm-style\") : null;\n",
       "    var box = iw;\n")]),
    ("map_not_watched",
     "Without the watcher the bubble's block keeps the server's words: no "
     "status, no today first.",
     [("    var map = doc.getElementById(\"map\");\n"
       "    if (map && root.MutationObserver) {\n"
       "      new root.MutationObserver(function (records) {\n"
       "        if (added(records)) {\n"
       "          scan(map);\n"
       "        }\n"
       "      }).observe(map, { childList: true, subtree: true });\n"
       "    }\n", "")]),
    ("map_no_subtree",
     "Google inserts the bubble deep inside #map, never as its child.",
     [("      }).observe(map, { childList: true, subtree: true });\n",
       "      }).observe(map, { childList: true });\n")]),
    ("up_substring",
     "A class is a whole word: gm-style is not gm-style-iw-c, and an SVG "
     "element's className is not a string.",
     [("      if ((\" \" + n.className + \" \").indexOf(\" \" + name + \" \") >= 0) {\n",
       "      if (n.className.indexOf(name) >= 0) {\n")]),
    ("touch_part4",
     "One word of Part 4's own script, which Part 4b must leave byte for "
     "byte.",
     [("        /* SLP absent or changed: cards keep their week, without a status. */\n",
       "        /* SLP absent or changed: cards keep their week without a status. */\n")]),
]


def build(src, subs, name):
    out = src
    for old, new in subs:
        n = out.count(old)
        if n != 1:
            print("  REFUSED: control %s: anchor matched %d times, expected 1" % (name, n))
            print("    %r" % old[:120])
            sys.exit(4)
        out = out.replace(old, new, 1)
    if out == src:
        print("  REFUSED: control %s changes nothing" % name)
        sys.exit(4)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="src", required=True)
    ap.add_argument("--js", dest="js", required=True)
    ap.add_argument("--out", dest="out", required=True)
    args = ap.parse_args()

    print("control-v033.py  r1  2026-10-04")
    print("  negative controls for suite-v033 and suite-bubble")
    print()

    for label, path, want_md5, want_len in (("class", args.src, IN_MD5, IN_LEN), ("script", args.js, JS_MD5, JS_LEN)):
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

    names = [c[0] for c in CONTROLS] + ["js-" + c[0] for c in JS_CONTROLS]
    if len(set(names)) != len(names):
        print("  REFUSED: two controls share a name")
        return 3

    src = open(args.src, "rb").read().decode(ENC)
    js = open(args.js, "rb").read().decode("ascii")
    os.makedirs(args.out, exist_ok=True)

    print("  class controls")
    for name, why, subs in CONTROLS:
        broken = build(src, subs, name)
        d = os.path.join(args.out, name)
        os.makedirs(d, exist_ok=True)
        raw = broken.encode(ENC)
        open(os.path.join(d, "class.slp_avalon.php"), "wb").write(raw)
        print("    %-32s %s %7d" % (name, hashlib.md5(raw).hexdigest(), len(raw)))
    print()
    print("  script controls")
    for name, why, subs in JS_CONTROLS:
        broken = build(js, subs, "js-" + name)
        d = os.path.join(args.out, "js-" + name)
        os.makedirs(d, exist_ok=True)
        raw = broken.encode("ascii")
        open(os.path.join(d, "avalon-hours.js"), "wb").write(raw)
        print("    %-32s %s %7d" % ("js-" + name, hashlib.md5(raw).hexdigest(), len(raw)))
    print()
    print("  %d class controls, %d script controls written to %s" % (len(CONTROLS), len(JS_CONTROLS), args.out))
    return 0


if __name__ == "__main__":
    sys.exit(main())
