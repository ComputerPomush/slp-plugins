#!/usr/bin/env python3
"""
control-v034.py  r1  2026-10-05
SLP Dealer Guard - negative controls for suite-v034 and suite-map
(slp_avalon v0.0.27 Part 4c).

WITHOUT THIS FILE IN THE REPO, A CLEAN SCORE ASSERTS NOTHING. A suite that
has only ever been run against a build that works has not been shown to be
capable of failing. Each control below removes exactly one load-bearing
decision from the good build and nothing else, and the release is gated on
the suites catching every one of them, by a pinned count.

    python3 control-v034.py --in <class.slp_avalon.php> --css <avalon-hours.css> --js <slp_avalon.js> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for each class control,
<dir>/css-<name>/avalon-hours.css for each stylesheet control and
<dir>/js-<name>/slp_avalon.js for each script control. A class control is
scored by suite-v034 with the good stylesheet; a stylesheet control by
suite-v034 with the good class; a script control by suite-map. Use an out*
name for the output directory - build/out*/ is gitignored (s0.218).

Every substitution is byte-exact and asserted unique before it is applied.
A control that could not be built is a hard error, never a skipped control:
a missing control is a decision nobody is testing.

HOW TO READ A SCORE. Every control inside Part 4c's new code also changes
the code the suites pin (suite-v034's two block pins, suite-map's block
pin), so each fails that one check by construction. A behavioural control
that fails ONLY that check has not been caught by any behaviour; every one
below fails at least one more. The touch_ controls change nothing Part 4c
wrote and show that the identity assertions - the only things carrying
Part 4b's and v0.0.25's evidence forward - can fail.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "12cde985f071d0e77d2ca1acf935d426"
IN_LEN = 330544
CSS_MD5 = "d24269039747a7d04c7ce931ef02c2ec"
CSS_LEN = 15316
JS_MD5 = "717a21bdb5b8f416a73da69b70b6b8d3"
JS_LEN = 101783

N = "\r\n"

# Part 4c's own fix for Part 4's Address: label, put back at 100.
LABEL_OUT = ("                $layout = str_replace( '[slp_location avalon_address_label][slp_location avalon_address_html]'," + N +
             "                                       '[slp_location avalon_address_html]', $layout );" + N)

# ---------------------------------------------------------------------------
# Class controls, scored by suite-v034 with the good stylesheet.
# (name, why it matters, [(old, new)])
# ---------------------------------------------------------------------------

CONTROLS = [

    # ---- the registrations ----------------------------------------------

    ("address_not_registered",
     "Without the filter no marker carries the two-line address; the cards "
     "and the bubble print nothing where the address was.",
     [("            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_address'), 30, 1);" + N, "")]),

    ("address_at_10",
     "At 30, after SLP Experience at 15, whose show_country empties the "
     "country the field reads.",
     [("'avalon_marker_address'), 30, 1);", "'avalon_marker_address'), 10, 1);")]),

    ("jsopts_not_registered",
     "Without it the layouts the browser is given keep the address run and "
     "the plain Distance:, and no hover pin reaches the script.",
     [("            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_map'), 110, 1);" + N, "")]),

    ("jsopts_at_90",
     "At 90 Part 4's callback at 100 still follows and puts its Address: "
     "label in again, in front of the field that has its own.",
     [("'avalon_js_options_map'), 110, 1);", "'avalon_js_options_map'), 90, 1);")]),

    ("results_string_not_registered",
     "The results layout SLP renders on the server goes through "
     "slp_javascript_results_string, not the script options.",
     [("            add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout_address'), 110, 1);" + N, "")]),

    ("rocket_map_not_registered",
     "Remove Unused CSS never sees a card, a bubble or .avalon-fa; their "
     "rules must be safelisted or they are stripped.",
     [("            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_map'));" + N, "")]),

    # ---- the address field ----------------------------------------------

    ("state_as_given",
     "ONTARIO, QUEBEC, Ont, NEW HAMPSHIRE: the owner asked for the "
     "two-letter code.",
     [("            return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : $state;",
       "            return $state;")]),

    ("state_upper_unknown",
     "A state the address key does not know keeps the feed's spelling, not "
     "the normaliser's upper case.",
     [("            return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : $state;",
       "            return $code;")]),

    ("country_always",
     "USA and CANADA are not shown - the owner's decision.",
     [("            if ( '' !== $val['country'] && class_exists( 'SLP_Avalon_AddressKey' )" + N +
       "                 && ! in_array( SLP_Avalon_AddressKey::norm_country( $val['country'], '', '' ), array( 'US', 'CA' ), true ) ) {",
       "            if ( '' !== $val['country'] ) {")]),

    ("country_never",
     "Any other country is shown, after the postal code.",
     [("                $two .= ( '' !== $two ? ' ' : '' ) . $val['country'];" + N, "")]),

    ("country_by_state",
     "The country text alone decides: Mexico with a state coded BC is "
     "Mexico, never Canada.",
     [("SLP_Avalon_AddressKey::norm_country( $val['country'], '', '' )",
       "SLP_Avalon_AddressKey::norm_country( $val['country'], $state, '' )")]),

    ("no_decode",
     "SLP's values arrive esc_attr()'d; &nbsp; must be read as a space "
     "before it is trimmed.",
     [("html_entity_decode( (string) $marker[ $k ], ENT_QUOTES, 'UTF-8' )", "(string) $marker[ $k ]")]),

    ("no_escape",
     "The field is inserted into the page raw; its text must be escaped "
     "here.",
     [("                $line = esc_html( $line );" + N, "")]),

    ("nbsp_kept",
     "A no-break space round a value counts as a space.",
     [("? trim( str_replace( \"\\xC2\\xA0\", ' '," + N +
       "                            html_entity_decode( (string) $marker[ $k ], ENT_QUOTES, 'UTF-8' ) ) )",
       "? trim( html_entity_decode( (string) $marker[ $k ], ENT_QUOTES, 'UTF-8' ) )")]),

    ("label_over_nothing",
     "Never \"Address:\" over nothing.",
     [("            if ( '' === $lines ) {" + N +
       "                return $marker;" + N +
       "            }" + N, "")]),

    ("unit_no_comma",
     "address2 follows the street after a comma.",
     [("                $one .= ( '' !== $one ? ', ' : '' ) . $val['address2'];",
       "                $one .= ( '' !== $one ? ' ' : '' ) . $val['address2'];")]),

    ("comma_always",
     "SLP's own punctuation: a comma after the city only when a state "
     "follows.",
     [("$two = $val['city'] . ( '' !== $state ? ',' : '' )", "$two = $val['city'] . ','")]),

    ("address_label_kindless",
     "The address label names its kind, or a phone has no icon to show.",
     [("            $marker['avalon_address_html'] = '<b class=\"avalon-label avalon-label--address\">Address:</b> '",
       "            $marker['avalon_address_html'] = '<b class=\"avalon-label\">Address:</b> '")]),

    ("phone_label_kindless",
     "Part 4's Phone: label names its kind (Part 4c's edit to Part 4).",
     [("'<b class=\"avalon-label avalon-label--phone\">Phone:</b> '", "'<b class=\"avalon-label\">Phone:</b> '")]),

    # ---- the layouts ----------------------------------------------------

    ("layout_class_lost",
     "The span that holds the field must carry avalon-address, or nothing "
     "lays the two lines out.",
     [("                $open = substr_replace( $open, ' avalon-address', $k[1][1] + strlen( $k[1][0] ), 0 );",
       "                $open = $open;")]),

    ("layout_drops_without_field",
     "The city, state and zip spans go only once the field is in; a layout "
     "this does not recognise keeps its city.",
     [("            if ( false !== strpos( $layout, 'avalon_address_html' ) ) {" + N + LABEL_OUT +
       "                foreach ( array( 'slp_result_street2' => 'address2',",
       "            if ( true ) {" + N + LABEL_OUT +
       "                foreach ( array( 'slp_result_street2' => 'address2',")]),

    ("label_kept",
     "SLP builds the results layout through slp_javascript_results_string "
     "inside its own slp_js_options callback at 10, so Part 4's callback at "
     "100 finds the field already in and puts its Address: label back in "
     "front of it: Address: twice on every card.",
     [(LABEL_OUT + "                foreach ( array( 'slp_result_street2' => 'address2',",
       "                foreach ( array( 'slp_result_street2' => 'address2',")]),

    ("bubble_label_kept",
     "Through the script options a second time, Part 4b's Address: label "
     "goes back in front of the bubble's field.",
     [(LABEL_OUT + "                foreach ( array( 'address2', 'city', 'state', 'zip' ) as $field ) {",
       "                foreach ( array( 'address2', 'city', 'state', 'zip' ) as $field ) {")]),

    ("layout_drop_greedy",
     "A span goes only when it holds nothing but its own field.",
     [("'/\\s*<span\\b[^>]*\\sclass=\"[^\"]*\\b' . $class . '\\b[^\"]*\"[^>]*>\\s*\\[slp_location\\s+'" + N +
       "                        . $field . '\\b[^\\]]*\\]\\s*<\\/span>/'",
       "'/\\s*<span\\b[^>]*\\sclass=\"[^\"]*\\b' . $class . '\\b[^\"]*\"[^>]*>[\\s\\S]*?<\\/span>/'")]),

    ("layout_country_flat_only",
     "SLP's country span is a pair, one inside the other.",
     [("(?:<span\\b[^>]*\\sid=\"slp_bubble_country\"[^>]*>\\s*'" + N +
       "                    . '\\[slp_location\\s+country\\b[^\\]]*\\]\\s*<\\/span>|\\[slp_location\\s+country\\b[^\\]]*\\])",
       "(?:\\[slp_location\\s+country\\b[^\\]]*\\])")]),

    ("layout_id_loose",
     "An id is matched only as an attribute of its own: data-id=\"...\" is "
     "not the address span.",
     [("self::avalon_layout_address_span( $layout, '/<span\\b[^>]*\\sid=\"slp_bubble_address\"[^>]*>/' );",
       "self::avalon_layout_address_span( $layout, '/<span\\b[^>]*id=\"slp_bubble_address\"[^>]*>/' );")]),

    ("layout_address_prefix",
     "[slp_location address2] is not the street field.",
     [("(?:\\[slp_location avalon_address_label\\]\\s*)?\\[slp_location\\s+address\\b[^\\]]*\\]",
       "(?:\\[slp_location avalon_address_label\\]\\s*)?\\[slp_location\\s+address[^\\]]*\\]")]),

    ("address_span_tight",
     "A stored layout may put the field on a line of its own; Part 4's "
     "label then goes before the line break.",
     [("(?:\\[slp_location avalon_address_label\\]\\s*)?", "(?:\\[slp_location avalon_address_label\\])?")]),

    ("distance_any_class",
     "location_distance2 is not location_distance.",
     [("preg_quote( $class, '/' ) . '\\b[^\"]*\"[^>]*>\\s*(Distance:)/'", "preg_quote( $class, '/' ) . '[^\"]*\"[^>]*>\\s*(Distance:)/'")]),

    ("distance_twice",
     "A second pass changes nothing, even with a second Distance: span "
     "further on.",
     [("            if ( false !== strpos( $layout, 'avalon-label--distance' )" + N +
       "                 || ! preg_match(",
       "            if ( ! preg_match(")]),

    # ---- the script options ---------------------------------------------

    ("icon_any_url",
     "A path from the root or an http(s) URL; nothing else becomes the "
     "hover pin.",
     [("            if ( '' === $v || 0 === strpos( $v, '//' )" + N +
       "                 || ( '/' !== $v[0] && ! preg_match( '#^https?://#i', $v ) ) ) {",
       "            if ( '' === $v ) {")]),

    ("icon_untrimmed",
     "An option typed with spaces round it still works.",
     [("            $v = trim( (string) get_option( 'avalon_map_hover_icon', '' ) );",
       "            $v = (string) get_option( 'avalon_map_hover_icon', '' );")]),

    ("icon_key_optional",
     "avalon_map_hover_icon is always set, '' when there is none.",
     [("            $options['avalon_map_hover_icon'] = self::avalon_map_hover_icon();",
       "            if ( '' !== self::avalon_map_hover_icon() ) {" + N +
       "                $options['avalon_map_hover_icon'] = self::avalon_map_hover_icon();" + N +
       "            }")]),

    ("jsopts_any_type",
     "A layout that is not a string is passed through, never cast.",
     [("isset( $options['resultslayout'] ) && is_string( $options['resultslayout'] ) ) {" + N +
       "                $options['resultslayout'] = $this->avalon_results_layout_address(",
       "isset( $options['resultslayout'] ) ) {" + N +
       "                $options['resultslayout'] = $this->avalon_results_layout_address(")]),

    ("rocket_no_sidebar",
     "The cards' phone sizes are #map_sidebar rules; they must survive "
     "Remove Unused CSS.",
     [("            $list[] = '(.*)#map_sidebar(.*)';" + N, "")]),

    # ---- regions Part 4c must not touch ----------------------------------

    ("touch_part4b_comment",
     "One word of Part 4b's own block, which Part 4c must leave byte for "
     "byte.",
     [("         * The address comes from the raw row - the marker's 'data', the" + N,
       "         * The address comes from the raw row - the marker's data, the" + N)]),

    ("touch_head",
     "The first line of the class, outside every Part 4c edit.",
     [("if (!class_exists('SLP_Avalon')){", "if (!class_exists('SLP_Avalon')) {")]),
]

# ---------------------------------------------------------------------------
# Stylesheet controls, scored by suite-v034 with the good class.
# ---------------------------------------------------------------------------

CSS_CONTROLS = [
    ("no_pegman",
     "Without it Elementor's img { max-width: 100% } squeezes the Pegman to "
     "0 px.",
     [("#map .gm-style img {\n  max-width: none;\n}\n\n", "")]),
    ("pegman_weak",
     "0-1-1 against Elementor's 0-1-1: the order of two stylesheets decides, "
     "and on Aura Elementor's loads later.",
     [("#map .gm-style img {\n", ".gm-style img {\n")]),
    ("ring_on_focus",
     "On :focus a pointer click on a button would draw the ring too.",
     [(".slp_info_bubble #slp_bubble_website a:focus-visible {", ".slp_info_bubble #slp_bubble_website a:focus {")]),
    ("icons_at_tablet",
     "Icons on a phone in portrait only - Elementor's mobile breakpoint.",
     [("   content declaration and keeps the first. */\n@media (max-width: 767px) {",
       "   content declaration and keeps the first. */\n@media (max-width: 1024px) {")]),
    ("icons_spoken",
     "Without the empty alternative text a screen reader may read the icon's "
     "private-use character after the word.",
     [("    content: \"\\f879\" / \"\";\n", "")]),
    ("sizes_by_order",
     "0-1-0 loses to the theme's 0-4-0 whatever the order.",
     [("  #map_sidebar .results_wrapper .sl_contact__info,\n", "  .sl_contact__info,\n")]),
    ("address_block",
     "A block, not a flex row: the second line runs back under the label.",
     [(".avalon-address {\n  display: flex;", ".avalon-address {\n  display: block;")]),
    ("caret_one_line",
     "Held to one line, the longest status runs up to 71 px past a tablet's "
     "results column; balanced, the caret never stands alone.",
     [("  text-wrap: balance;\n", "  white-space: nowrap;\n")]),
    ("email_15_narrow",
     "At 15 px on a 360 px phone some emails wrap; under 375 px the line is "
     "14 px.",
     [("  .slp_info_bubble #slp_bubble_email a.avalon-email {\n    font-size: 14px;\n",
       "  .slp_info_bubble #slp_bubble_email a.avalon-email {\n    font-size: 15px;\n")]),
    ("touch_part4b",
     "One word of Part 4b's stylesheet, which Part 4c must leave byte for "
     "byte.",
     [("   card's order - Distance:, Address:, Phone: - then Email: and Hours:, from\n",
       "   card's order - Distance:, Address:, Phone: - and Email: and Hours:, from\n")]),
]

# ---------------------------------------------------------------------------
# Script controls, against slp_avalon.js, scored by suite-map.
# ---------------------------------------------------------------------------

JS_CONTROLS = [
    ("focus_on_hover",
     "A hover never moves focus; only a choice does.",
     [("        st.wantFocus = !hover;" + N, "        st.wantFocus = true;" + N)]),
    ("google_focuses",
     "Google's own guess focused the first link, the phone.",
     [("        iw.open({ map: cm.gmap, anchor: marker.__gmarker, shouldFocus: false });",
       "        iw.open({ map: cm.gmap, anchor: marker.__gmarker });")]),
    ("no_min_width",
     "On a phone the bubble is as wide as the map allows, less 24 px.",
     [("      return w > MARGIN ? Math.min(WIDEST, w - MARGIN) : 0;", "      return 0;")]),
    ("min_width_no_close",
     "Google reads minWidth only as a bubble opens: close(), setOptions(), "
     "open().",
     [("          if (st.open) {" + N +
       "            reclose(iw);" + N +
       "          }" + N, "")]),
    ("quiet_stuck",
     "Only the close that reopens the bubble is quiet: Google's own closes "
     "after it are the visitor's.",
     [("      } finally {" + N +
       "        st.quiet = false;" + N +
       "      }" + N +
       "      var now = document.activeElement;",
       "      } finally {" + N +
       "      }" + N +
       "      var now = document.activeElement;")]),
    ("focus_not_put_back",
     "Google sends focus back on close(): focus that was not in the bubble "
     "is put back where it was.",
     [("        } else if (now !== a && document.body.contains(a)) {",
       "        } else if (false) {")]),
    ("focus_held_by_google",
     "Focus that was in the bubble, which Google sends back where it was "
     "before the bubble opened, is let go - for the bubble reopened.",
     [("        if (had || !a || a === document.body) {",
       "        if (!a || a === document.body) {")]),
    ("body_focus_moved",
     "Focus that was on nothing - a phone tapped off the bubble - stays on "
     "nothing: Google's return of it to the search box is let go.",
     [("        if (had || !a || a === document.body) {",
       "        if (had) {")]),
    ("scroll_not_kept",
     "Google's focus() can scroll the page to a card or the search box; the "
     "page is put back where it was.",
     [("        if (window.pageXOffset !== sx || window.pageYOffset !== sy) {" + N +
       "          window.scrollTo(sx, sy);" + N +
       "        }" + N, "")]),
    ("show_inline_close",
     "A bubble switch at a new width closes the first bubble the same way: "
     "focus Google sends back to the first's pin is let go.",
     [("          if (st.open) {" + N +
       "            reclose(iw);" + N +
       "          }" + N,
       "          if (st.open) {" + N +
       "            st.quiet = true;" + N +
       "            try {" + N +
       "              iw.close();" + N +
       "            } finally {" + N +
       "              st.quiet = false;" + N +
       "            }" + N +
       "          }" + N)]),
    ("had_after_close",
     "Whether focus was in the bubble is read before Google takes the "
     "bubble out.",
     [("      var a = document.activeElement;" + N +
       "      var had = !!a && a !== document.body && inside(a);" + N +
       "      var sx = window.pageXOffset;" + N +
       "      var sy = window.pageYOffset;" + N +
       "      st.quiet = true;" + N +
       "      try {" + N +
       "        iw.close();" + N +
       "      } finally {" + N +
       "        st.quiet = false;" + N +
       "      }" + N,
       "      var sx = window.pageXOffset;" + N +
       "      var sy = window.pageYOffset;" + N +
       "      st.quiet = true;" + N +
       "      try {" + N +
       "        iw.close();" + N +
       "      } finally {" + N +
       "        st.quiet = false;" + N +
       "      }" + N +
       "      var a = document.activeElement;" + N +
       "      var had = !!a && a !== document.body && inside(a);" + N)]),
    ("had_not_inside",
     "Focus on the chosen pin, outside the bubble, is not pulled into it.",
     [("      var had = !!a && a !== document.body && inside(a);",
       "      var had = !!a && a !== document.body;")]),
    ("quiet_not_quiet",
     "The close that only reopens the bubble wider is not the visitor's.",
     [("      if (st.quiet || (iw && iw.isOpen === true)) {", "      if (iw && iw.isOpen === true) {")]),
    ("close_at_once",
     "0.3 s, so the pointer can cross from the pin to the bubble.",
     [("    var CLOSE_MS = 300;", "    var CLOSE_MS = 0;")]),
    ("chosen_closes",
     "A chosen bubble stays until another pin, Esc or the map.",
     [("      if (st.pinned || !st.open) {" + N +
       "        return;" + N +
       "      }" + N +
       "      cancel();" + N +
       "      st.timer = setTimeout(",
       "      if (!st.open) {" + N +
       "        return;" + N +
       "      }" + N +
       "      cancel();" + N +
       "      st.timer = setTimeout("),
      ("        if (st.pinned || !st.open || st.overBubble",
       "        if (!st.open || st.overBubble")]),
    ("click_bubbles",
     "In the capture phase: avalon-hours.js stops the hours' own clicks "
     "before they bubble.",
     [("          windowed(ev.target);" + N +
       "        }, true);", "          windowed(ev.target);" + N +
       "        }, false);")]),
    ("hover_replaces_chosen",
     "Hovering another pin lights it; the chosen bubble stays.",
     [("      } else if (!st.pinned) {" + N +
       "        cancel();" + N +
       "        st.next = \"hover\";",
       "      } else {" + N +
       "        cancel();" + N +
       "        st.next = \"hover\";")]),
    ("focus_inside_ignored",
     "Rule 5: never on its own while keyboard focus is inside it.",
     [("        if (st.pinned || !st.open || st.overBubble || (st.current && hovered(st.current)) ||" + N +
       "            inside(document.activeElement)) {",
       "        if (st.pinned || !st.open || st.overBubble || (st.current && hovered(st.current))) {")]),
    ("bubble_hover_ignored",
     "Rule 1: the pointer on the bubble keeps it.",
     [("        c.addEventListener(\"mouseenter\", function () {" + N +
       "          st.overBubble = true;" + N +
       "          cancel();",
       "        c.addEventListener(\"mouseenter\", function () {" + N +
       "          st.overBubble = false;")]),
    ("esc_over_modal",
     "The Contact Dealer form's Esc closes the form, not the bubble behind "
     "it.",
     [(" ||" + N + "          document.querySelector(\".contact-dealer--pop-up.open-modal\")) {", ") {")]),
    ("esc_twice",
     "An Esc another handler has used is not used again.",
     [("!st.open || ev.defaultPrevented ||", "!st.open ||")]),
    ("map_click_ignored",
     "Rule 2: a click on the map closes the bubble.",
     [("      google.maps.event.addListener(cm.gmap, \"click\", function () {" + N +
       "        close();" + N +
       "      });" + N, "")]),
    ("no_focus_back",
     "Focus lost with the bubble goes back where it came from.",
     [("      if (document.body.contains(back)) {" + N +
       "        try {" + N +
       "          back.focus({ preventScroll: true });",
       "      if (false) {" + N +
       "        try {" + N +
       "          back.focus({ preventScroll: true });")]),
    ("focus_back_always",
     "Never taken from wherever the visitor has put it since.",
     [("      if (!back || (a && a !== document.body && !inside(a))) {", "      if (!back) {")]),
    ("focus_stale",
     "The last dealer's buttons, still in the page, are not this one's.",
     [("      if (!b || (st.current && /^slp_info_bubble_/.test(b.id || \"\") && b.id !== \"slp_info_bubble_\" + st.current.id)) {",
       "      if (!b) {")]),
    ("late_close_obeyed",
     "A close event that arrives after the bubble reopened is not a close.",
     [("      if (st.quiet || (iw && iw.isOpen === true)) {", "      if (st.quiet) {")]),
    ("pin_not_restored",
     "Unlit, a pin is as SLP drew it.",
     [("        if (url) {" + N +
       "          g.setIcon(e.icon);" + N +
       "        }" + N, "")]),
    ("pin_not_raised",
     "The hovered pin sits above the others.",
     [("        g.setZIndex((google.maps.Marker.MAX_ZINDEX || 1000000) + 1);" + N, "")]),
    ("match_by_position",
     "Pins are matched to SLP's results by location id.",
     [("      for (var j = 0; j < list.length; j++) {" + N +
       "        if (list[j] && String(list[j].id) === String(m.__location_id)) {" + N +
       "          return list[j];" + N +
       "        }" + N +
       "      }" + N, "")]),
    ("fa_any_answer",
     "fonts.load() resolving with no face means no such font: no icons.",
     [("          if (faces && faces.length) {", "          if (faces) {")]),
    ("fa_everywhere",
     "Font Awesome is asked for on the locator's page only.",
     [("      if (st.fa || !d.getElementById(\"map_sidebar\")) {", "      if (st.fa) {")]),
    ("bubble_survives_search",
     "A new search closes the bubble that was open.",
     [("      cancel();" + N +
       "      if (st.open && st.cm) {" + N +
       "        st.cm.infowindow.close();" + N +
       "      }" + N +
       "      clear();" + N +
       "      st.overMarker = null;",
       "      cancel();" + N +
       "      st.overMarker = null;")]),
    ("camera_on",
     "No camera control, as on a store page.",
     [("        o.cameraControl = false;", "        o.cameraControl = true;")]),
    ("controls_before_filter",
     "After the map_options filter, or SLP Experience's \"0\" hides Map and "
     "Satellite again.",
     [("    slp_Filter(\"map_options\").publish(avalon_cslmap.options);" + N +
       "    //v0.0.27 Part 4c. The store page's controls, set after the filter so" + N +
       "    //that nothing subscribed to it can take them away again (avalon_map)." + N +
       "    avalon_map.controls(avalon_cslmap.options);" + N,
       "    avalon_map.controls(avalon_cslmap.options);" + N +
       "    slp_Filter(\"map_options\").publish(avalon_cslmap.options);" + N +
       "    //v0.0.27 Part 4c. The store page's controls, set after the filter so" + N +
       "    //that nothing subscribed to it can take them away again (avalon_map)." + N)]),
    ("name_raw",
     "The dialog's name is text: SLP's &amp; read as &.",
     [("        .replace(/&(amp|lt|gt|quot|#0?39);/g, function (m, k) {", "        .replace(/&(xxamp|xxlt);/g, function (m, k) {")]),
    ("focus_over_form",
     "main.js opens the Contact Dealer form on the same click as a card's "
     "own button: focus is not pulled into the bubble behind it.",
     [("      if (document.querySelector(\".contact-dealer--pop-up.open-modal\") ||" + N +
       "          (a && a !== document.body && a !== st.from && !on_map(a))) {",
       "      if (a && a !== document.body && a !== st.from && !on_map(a)) {")]),
    ("focus_taken_back",
     "Focus the visitor has moved on since the choice stays where it is.",
     [("          (a && a !== document.body && a !== st.from && !on_map(a))) {",
       "          false) {")]),
    ("map_focus_elsewhere",
     "A click on a pin may leave focus on the pin or the map: that is not "
     "somewhere else, and Contact Dealer still takes it.",
     [("          (a && a !== document.body && a !== st.from && !on_map(a))) {",
       "          (a && a !== document.body && a !== st.from && !inside(a))) {")]),
    ("focus_during_click",
     "A card's own Contact Dealer button reaches show() before main.js opens "
     "the form; focus moves only once the click is done.",
     [("        st.wantFocus = true;" + N +
       "        soon();",
       "        st.wantFocus = true;" + N +
       "        focus_in();")]),
    ("from_not_kept",
     "Where focus was at the choice is what focus_in() measures a move "
     "against.",
     [("      if (!hover) {" + N +
       "        st.from = document.activeElement;" + N +
       "      }" + N, "")]),
    ("focusin_ignored",
     "Rule 5: focus moving into a bubble keeps it, as a click does.",
     [("          if (st.open) {" + N +
       "            st.pinned = true;" + N +
       "            cancel();" + N +
       "          }" + N +
       "          if (!st.back && from", "          if (!st.back && from")]),
    ("tab_in_unrecorded",
     "Focus Tab brought into the bubble goes back where it came from when "
     "the bubble closes.",
     [("          if (!st.back && from && from.nodeType === 1 && from !== document.body && !inside(from)) {" + N +
       "            st.back = from;" + N +
       "          }" + N, "")]),
    ("full_screen_kept",
     "Contact Dealer's form opens over the page, which full screen hides.",
     [("          windowed(ev.target);" + N, "")]),
    ("full_screen_any_link",
     "Only main.js's link - .storelocatorlink - opens the form.",
     [("      if (!link || !has_class(link, \"storelocatorlink\") || !n || n.nodeType !== 1) {",
       "      if (!link || !n || n.nodeType !== 1) {")]),
    ("full_screen_any_button",
     "Only Contact Dealer opens the form; Get Directions leaves full screen "
     "alone.",
     [("        if (n.id === \"slp_bubble_website\") {",
       "        if (n.id === \"slp_bubble_website\" || n.id === \"slp_bubble_directions\") {")]),
    ("resize_ignored",
     "Google reads minWidth only as a bubble opens: the open bubble is "
     "reopened once the window has resized - a phone turned.",
     [("      if (window.addEventListener) {" + N +
       "        window.addEventListener(\"resize\", function () {" + N +
       "          clearTimeout(st.rs);" + N +
       "          st.rs = setTimeout(resized, RESIZE_MS);" + N +
       "        }, false);" + N +
       "      }" + N, "")]),
    ("resize_at_once",
     "0.2 s after the last resize: once a turn of the phone, not once an "
     "event.",
     [("          clearTimeout(st.rs);" + N +
       "          st.rs = setTimeout(resized, RESIZE_MS);",
       "          resized();")]),
    ("resize_reopens_always",
     "A resize that leaves the map's width alone - a phone's toolbar or "
     "keyboard - reopens nothing.",
     [("      var width = min_width(cm);" + N +
       "      if (width === st.minWidth) {" + N +
       "        return;" + N +
       "      }" + N +
       "      if (document.querySelector(",
       "      var width = min_width(cm);" + N +
       "      if (document.querySelector(")]),
    ("resize_not_quiet",
     "The close that reopens the bubble at its new width is not the "
     "visitor's.",
     [("      st.quiet = true;" + N +
       "      try {" + N +
       "        iw.close();" + N +
       "      } finally {" + N +
       "        st.quiet = false;" + N +
       "      }" + N,
       "      iw.close();" + N)]),
    ("resize_drops_focus",
     "Focus lost as Google takes the bubble out goes back to its Contact "
     "Dealer.",
     [("      if (reclose(iw)) {" + N +
       "        st.wantFocus = true;" + N +
       "      }" + N,
       "      reclose(iw);" + N)]),
    ("resize_takes_focus",
     "Focus that was not in the bubble is not pulled into it.",
     [("      if (reclose(iw)) {" + N +
       "        st.wantFocus = true;",
       "      if (reclose(iw) || true) {" + N +
       "        st.wantFocus = true;")]),
    ("minwidth_unrecorded",
     "The minWidth the bubble was reopened at is the one the next resize and "
     "the next bubble are measured against.",
     [("      iw.setOptions({ minWidth: width });" + N +
       "      st.minWidth = width;" + N,
       "      iw.setOptions({ minWidth: width });" + N)]),
    ("resize_under_form",
     "Not under the Contact Dealer form: its focus goes back, as it closes, "
     "to the link that opened it, which a reopen would take out.",
     [("      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {" + N +
       "        st.rs = setTimeout(resized, RESIZE_MS);" + N +
       "        return;" + N +
       "      }" + N, "")]),
    ("form_check_first",
     "A resize that leaves the map's width alone is nothing to wait for, "
     "under the Contact Dealer form or not.",
     [("      var width = min_width(cm);" + N +
       "      if (width === st.minWidth) {" + N +
       "        return;" + N +
       "      }" + N +
       "      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {" + N +
       "        st.rs = setTimeout(resized, RESIZE_MS);" + N +
       "        return;" + N +
       "      }" + N,
       "      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {" + N +
       "        st.rs = setTimeout(resized, RESIZE_MS);" + N +
       "        return;" + N +
       "      }" + N +
       "      var width = min_width(cm);" + N +
       "      if (width === st.minWidth) {" + N +
       "        return;" + N +
       "      }" + N)]),
    ("resize_form_forgotten",
     "Once the Contact Dealer form has closed, the bubble still takes its "
     "new width.",
     [("        st.rs = setTimeout(resized, RESIZE_MS);" + N +
       "        return;",
       "        return;")]),
    ("resize_hidden_map",
     "A map not laid out - 0 px wide - is left alone: its width is not "
     "known.",
     [("      if (!div || !div.clientWidth) {" + N +
       "        return;" + N +
       "      }" + N, "")]),
    ("touch_v025",
     "One word of v0.0.25's own code, which Part 4c must leave byte for byte.",
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
    ap.add_argument("--js", dest="js", required=True)
    ap.add_argument("--out", dest="out", required=True)
    args = ap.parse_args()

    print("control-v034.py  r1  2026-10-05")
    print("  negative controls for suite-v034 and suite-map")
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

    for title, items, text, prefix, fname, enc in (("class controls", CONTROLS, src, "", "class.slp_avalon.php", ENC),
                                                   ("stylesheet controls", CSS_CONTROLS, css, "css-", "avalon-hours.css", "ascii"),
                                                   ("script controls", JS_CONTROLS, js, "js-", "slp_avalon.js", ENC)):
        print("  " + title)
        for name, why, subs in items:
            broken = build(text, subs, prefix + name)
            d = os.path.join(args.out, prefix + name)
            os.makedirs(d, exist_ok=True)
            raw = broken.encode(enc)
            open(os.path.join(d, fname), "wb").write(raw)
            print("    %-32s %s %7d" % (prefix + name, hashlib.md5(raw).hexdigest(), len(raw)))
        print()
    print("  %d class controls, %d stylesheet controls, %d script controls written to %s"
          % (len(CONTROLS), len(CSS_CONTROLS), len(JS_CONTROLS), args.out))
    return 0


if __name__ == "__main__":
    sys.exit(main())
