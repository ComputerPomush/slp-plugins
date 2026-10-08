#!/usr/bin/env python3
"""
build-v027-part4d.py

slp_avalon v0.0.27, PART 4d - the approved find-a-dealer design.

WHAT THIS RELEASE DOES
----------------------
The owner's design handoff of 2026-10-06 (find-a-dealer-HANDOFF.md and the
HTML it describes) and the decisions of 2026-10-07:

1. THE CARDS. A card's lines in one grid: the labels in a column of their
   own - Distance:, Address:, Phone:, Hours: - the values beside them, an
   address's second line under its first, an opened week under its status;
   the two buttons side by side at equal widths, with the bubble's white
   keyboard ring (decision 3b); 15 px text and 20 px names at 1024 px and
   under. SLP's cells and field spans step aside (display: contents), so
   nothing in the page moves. Labels as words on every screen: Part 4c's
   Font Awesome icons are gone (decision 1a).

2. THE HOURS. The weekday in full ("Opens 9 AM Tuesday"); the status's last
   word and the caret - an <i> now, SLP hides a card's empty spans - held on
   one line; Hours: in the label column, out of the summary, still opening
   the week on a click; the opened week with a rule, a dot before today and
   TODAY after its hours; narrow cards and bubbles by container queries.

3. THE BUBBLE. In three parts - the name, a body that scrolls on a phone
   with a fade at its foot while there is more, the buttons in a row under
   it. avalon_bubble_layout_frame() wraps Avalon's fields for the grid and
   SLP's two button spans for their row, on slp_js_options at 120 - every
   id kept for main.js and slp_avalon.js.

4. THE CHOSEN CARD. The dealer whose bubble was chosen has its card marked
   .active until that bubble closes; a hover marks nothing.

5. THE SEARCH FIELD. Placeholder "City, State, or ZIP" (decision 4).

The card's colours, box and ring, the buttons' shape and the bubble's frame
and tail are Aura's, in the child theme (patch-style-fad-design.py), not
here. Hover and keyboard focus of every link and button are as they were,
but for the card buttons' new keyboard ring.

WHAT IS PATCHED
---------------
class.slp_avalon.php: one registration after Part 4c's, six anchored edits
(avalon_hours_markup()'s docblock, a card's label and summary, the hours
spans, Part 4c's safelist losing .avalon-fa), one block of methods inserted
directly before avalon_rest_protected_slugs() - after Part 4c's block.
avalon-hours.css: seven anchored edits; Part 4c's icon block, the end of
the file, gives way to Part 4d's block. avalon-hours.js: ten anchored
edits. slp_avalon.js: twelve anchored edits - the placeholder, and eleven in
Part 4c's avalon_map block.

ENCODING
--------
Every file is read and written as ISO-8859-1, so every byte round-trips;
the class and slp_avalon.js stay pure CRLF, the stylesheet and
avalon-hours.js pure LF. Every edit is written LF here and turned CRLF where
its file is. Every anchor must match exactly once.

    python build-v027-part4d.py <src_dir> <out_dir>

src_dir holds Part 4c's class.slp_avalon.php, avalon-hours.css and
slp_avalon.js and Part 4b's avalon-hours.js (unchanged by Part 4c), by
those names. Output pinned; nothing is written unless all four match.
"""

import hashlib
import io
import os
import re
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('12cde985f071d0e77d2ca1acf935d426', 330544),
    'avalon-hours.css':     ('d24269039747a7d04c7ce931ef02c2ec', 15316),
    'avalon-hours.js':      ('76a62b751b5556b96b79c2734b05dca8', 14683),
    'slp_avalon.js':        ('717a21bdb5b8f416a73da69b70b6b8d3', 101783),
}

OUT_PINS = {
    'class.slp_avalon.php': ('778185553d663139707b9d23dff00407', 339614),
    'avalon-hours.css': ('ffe117b6c77e2dfa3d082b5bb7c8ca10', 26810),
    'avalon-hours.js': ('6d4c084f963e68271b0194926663eecf', 17315),
    'slp_avalon.js': ('00733408915197e40c78ec03eed61922', 103266),
}


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
# avalon-hours.js (LF)
# ===========================================================================

HOURS_JS_EDITS = [

    ("avalon-hours.js: the header names Part 4d",
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4b.\n",
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n"),

    ("avalon-hours.js: the header says what Part 4d changes",
     " * shown again, so \"Closes soon\" turns into \"Closed\" on time. Writes to the\n"
     " * page only when the day or the words change.\n"
     " */\n",
     " * shown again, so \"Closes soon\" turns into \"Closed\" on time. Writes to the\n"
     " * page only when the day or the words change.\n"
     " *\n"
     " * Part 4d, the approved find-a-dealer design: the weekday in full (\"Opens\n"
     " * 9 AM Tuesday\"); the status's last word and the caret held on one line,\n"
     " * so the caret never starts a line alone - an element now, hidden from\n"
     " * screen readers; and on a card and in the bubble, where Hours: has moved\n"
     " * out of the <summary> into the label column, a click on it still opens\n"
     " * and shuts the week.\n"
     " */\n"),

    ("avalon-hours.js: the weekday in full",
     "  var SHORT = [\"Sun\", \"Mon\", \"Tue\", \"Wed\", \"Thu\", \"Fri\", \"Sat\"];\n",
     "  var DAYS = [\"Sunday\", \"Monday\", \"Tuesday\", \"Wednesday\", \"Thursday\", \"Friday\", \"Saturday\"];\n"),

    ("avalon-hours.js: words() documents the full weekday",
     "   *   Open \\u00b7 Closes 5 PM        Closes soon \\u00b7 5 PM      Open 24 hours\n"
     "   *   Closed \\u00b7 Opens 9 AM       Closed \\u00b7 Opens 10 AM Sun    Opens soon \\u00b7 9 AM\n"
     "   * The weekday is added to an opening that is not later today, and to a\n"
     "   * closing a day or more away. Tone is open, closed or soon.\n",
     "   *   Open \\u00b7 Closes 5 PM        Closes soon \\u00b7 5 PM      Open 24 hours\n"
     "   *   Closed \\u00b7 Opens 9 AM       Opens soon \\u00b7 9 AM\n"
     "   *   Closed \\u00b7 Opens 10 AM Sunday\n"
     "   * The weekday - in full from Part 4d, as the design has it - is added to\n"
     "   * an opening that is not later today, and to a closing a day or more\n"
     "   * away. Tone is open, closed or soon.\n"),

    ("avalon-hours.js: words() - the closing's weekday in full",
     "        return [\"Open\", \"open\", DOT + \"Closes \" + t + (st.left >= DAY ? \" \" + SHORT[day] : \"\")];\n",
     "        return [\"Open\", \"open\", DOT + \"Closes \" + t + (st.left >= DAY ? \" \" + DAYS[day] : \"\")];\n"),

    ("avalon-hours.js: words() - the opening's weekday in full",
     "        return [\"Closed\", \"closed\", DOT + \"Opens \" + t + (day === now.d && st.left < DAY ? \"\" : \" \" + SHORT[day])];\n",
     "        return [\"Closed\", \"closed\", DOT + \"Opens \" + t + (day === now.d && st.left < DAY ? \"\" : \" \" + DAYS[day])];\n"),

    ("avalon-hours.js: paint() holds the last word and the caret together",
     "  /** The status into every status slot of a block. No status, no change. */\n"
     "  function paint(el, w) {\n"
     "    if (!w) {\n"
     "      return;\n"
     "    }\n"
     "    var slots = el.querySelectorAll(\".avalon-hours__status\");\n"
     "    for (var i = 0; i < slots.length; i++) {\n"
     "      var slot = slots[i];\n"
     "      while (slot.firstChild) {\n"
     "        slot.removeChild(slot.firstChild);\n"
     "      }\n"
     "      var word = doc.createElement(\"span\");\n"
     "      word.className = \"avalon-hours__word avalon-hours__word--\" + w[1];\n"
     "      word.textContent = w[0];\n"
     "      slot.appendChild(word);\n"
     "      if (w[2]) {\n"
     "        slot.appendChild(doc.createTextNode(w[2]));\n"
     "      }\n"
     "    }\n"
     "  }\n",
     "  /**\n"
     "   * Part 4d. A status's rest split before its last word: [what comes\n"
     "   * before it, the word], or null when the rest is empty. A time keeps\n"
     "   * its AM or PM - \"5 PM\" is one word here - so the line never ends\n"
     "   * \"Closes 5\" with \"PM\" and the caret under it.\n"
     "   */\n"
     "  function last(rest) {\n"
     "    var m = /^([\\s\\S]*?)(\\S+(?: [AP]M)?)\\s*$/.exec(rest || \"\");\n"
     "    return m ? [m[1], m[2]] : null;\n"
     "  }\n"
     "\n"
     "  /**\n"
     "   * Part 4d. The caret: drawn by avalon-hours.css, unseen by screen\n"
     "   * readers. An <i>, as the PHP writes it: SLP hides a card's empty spans.\n"
     "   */\n"
     "  function caret() {\n"
     "    var c = doc.createElement(\"i\");\n"
     "    c.className = \"avalon-hours__caret\";\n"
     "    c.setAttribute(\"aria-hidden\", \"true\");\n"
     "    return c;\n"
     "  }\n"
     "\n"
     "  /**\n"
     "   * The status into every status slot of a block. No status, no change.\n"
     "   * Part 4d: the last word and the caret after it in one\n"
     "   * avalon-hours__nowrap span - the weekday, \"5 PM\", or a status with no\n"
     "   * rest (\"Open 24 hours\") whole - so the caret never wraps alone. The\n"
     "   * caret is never inside the coloured word: it keeps the line's colour.\n"
     "   */\n"
     "  function paint(el, w) {\n"
     "    if (!w) {\n"
     "      return;\n"
     "    }\n"
     "    var slots = el.querySelectorAll(\".avalon-hours__status\");\n"
     "    for (var i = 0; i < slots.length; i++) {\n"
     "      var slot = slots[i];\n"
     "      while (slot.firstChild) {\n"
     "        slot.removeChild(slot.firstChild);\n"
     "      }\n"
     "      var word = doc.createElement(\"span\");\n"
     "      word.className = \"avalon-hours__word avalon-hours__word--\" + w[1];\n"
     "      word.textContent = w[0];\n"
     "      var held = doc.createElement(\"span\");\n"
     "      held.className = \"avalon-hours__nowrap\";\n"
     "      var tail = last(w[2]);\n"
     "      if (tail) {\n"
     "        slot.appendChild(word);\n"
     "        if (tail[0]) {\n"
     "          slot.appendChild(doc.createTextNode(tail[0]));\n"
     "        }\n"
     "        held.appendChild(doc.createTextNode(tail[1]));\n"
     "      } else {\n"
     "        held.appendChild(word);\n"
     "      }\n"
     "      held.appendChild(caret());\n"
     "      slot.appendChild(held);\n"
     "    }\n"
     "  }\n"),

    ("avalon-hours.js: a click on the moved Hours: label opens the week",
     "  function keep(e) {\n"
     "    e.stopPropagation();\n"
     "  }\n",
     "  function keep(e) {\n"
     "    e.stopPropagation();\n"
     "  }\n"
     "\n"
     "  /**\n"
     "   * Part 4d. On a card and in the bubble, Hours: sits in the label\n"
     "   * column, just before the block and outside the <summary> it used to\n"
     "   * be part of. A click on it still opens and shuts the week, and - like\n"
     "   * a click in the block - goes no further. Mouse and touch only: the\n"
     "   * label is hidden from screen readers, and the summary, which says\n"
     "   * \"Hours:\" to them, is the control a keyboard reaches.\n"
     "   */\n"
     "  function label(el) {\n"
     "    var l = el.previousElementSibling;\n"
     "    if (!l || (\" \" + l.className + \" \").indexOf(\" avalon-label--hours \") < 0) {\n"
     "      return;\n"
     "    }\n"
     "    l.addEventListener(\"click\", function (e) {\n"
     "      e.stopPropagation();\n"
     "      var d = el.querySelector(\".avalon-hours__narrow\");\n"
     "      if (d) {\n"
     "        d.open = !d.open;\n"
     "      }\n"
     "    }, false);\n"
     "  }\n"),

    ("avalon-hours.js: enhance() wires the label once, with the block",
     "      if ((\" \" + el.className + \" \").indexOf(\" avalon-hours--card \") >= 0) {\n"
     "        el.addEventListener(\"click\", keep, false);\n"
     "      }\n",
     "      if ((\" \" + el.className + \" \").indexOf(\" avalon-hours--card \") >= 0) {\n"
     "        el.addEventListener(\"click\", keep, false);\n"
     "        label(el);\n"
     "      }\n"),

    ("avalon-hours.js: last() exported, for its suite",
     "    words: words,\n",
     "    words: words,\n"
     "    last: last,\n"),
]

# ===========================================================================
# avalon-hours.css (LF)
# ===========================================================================

CSS_EDITS = [

    ("css: the header names Part 4d",
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4c.\n",
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n"),

    ("css: the header's card hover is Part 4d's grey",
     " * card rest = rgba(255,255,255,.1) over #000; card hover = rgba(222,139,13,.2)\n"
     " * over #000. On a card the pink is under 4.5 - the owner's choice; the word\n"
     " * is bold. #2a2a2a, a worst case for the store page's background image,\n",
     " * card rest = rgba(255,255,255,.1) over #000; card hover, and the chosen\n"
     " * card, = rgba(255,255,255,.12) over #000 from Part 4d (the warm\n"
     " * rgba(222,139,13,.2) before it measured the same to 0.01). On a card the\n"
     " * pink is under 4.5 - the owner's choice; the word is bold, and Part 4d's\n"
     " * TODAY, 11 px, is the same pink at the same 3.98 and 3.77 (4.58 in the\n"
     " * bubble). #2a2a2a, a worst case for the store page's background image,\n"),

    ("css: the header says what Part 4c and Part 4d add",
     " * PART 4c, at the end of the file. The address on two lines on the cards\n"
     " * and in the bubble; on a phone, 15 px text, 20 px names and the labels as\n"
     " * Font Awesome icons; the map's own images - the Street View Pegman - at\n"
     " * their own sizes; a keyboard focus ring on the bubble's buttons; the\n"
     " * Hours: line balanced where it has to wrap.\n"
     " */\n",
     " * PART 4c, after Part 4b's bubble rules. The address on two lines on the\n"
     " * cards and in the bubble; the map's own images - the Street View Pegman -\n"
     " * at their own sizes; a keyboard focus ring on the bubble's buttons; the\n"
     " * Hours: line balanced where it has to wrap; under 375 px a 14 px email.\n"
     " *\n"
     " * PART 4d, at the end of the file. The approved find-a-dealer design: a\n"
     " * card's lines in one grid, its buttons side by side, with the bubble's\n"
     " * keyboard ring; the bubble in three parts - the name, a body that scrolls\n"
     " * on a phone, the buttons; Hours: in the label column; the opened week\n"
     " * with a rule, a dot and TODAY; 15 px text and 20 px names at 1024 px and\n"
     " * under on the cards, at every width in the bubble. Words for the labels\n"
     " * on every screen: Part 4c's Font Awesome icons are gone (the owner,\n"
     " * 2026-10-07).\n"
     " */\n"),

    ("css: TODAY's colour, beside the status colours",
     "  --avalon-hours-soon: #fcad70;\n"
     "}\n",
     "  --avalon-hours-soon: #fcad70;\n"
     "  --avalon-hours-today: var(--e-global-color-primary, var(--primary-color, #e7167c));\n"
     "}\n"),

    ("css: the caret is an element, held to the last word",
     "/* The caret is drawn, not typed: a character in generated content becomes\n"
     "   part of the summary's spoken name in some screen readers. */\n"
     ".avalon-hours .avalon-hours__summary::after {\n"
     "  content: \"\";\n"
     "  display: inline-block;\n"
     "  width: 0.4em;\n"
     "  height: 0.4em;\n"
     "  margin: 0 0 0.2em 0.5em;\n"
     "  border: solid currentColor;\n"
     "  border-width: 0 2px 2px 0;\n"
     "  vertical-align: middle;\n"
     "  transform: rotate(45deg);\n"
     "  transition: transform 0.2s;\n"
     "}\n"
     "\n"
     ".avalon-hours .avalon-hours__narrow[open] > .avalon-hours__summary::after {\n"
     "  margin-bottom: -0.1em;\n"
     "  transform: rotate(-135deg);\n"
     "}\n",
     "/* The caret is drawn, not typed: a character in generated content becomes\n"
     "   part of the summary's spoken name in some screen readers. From Part 4d\n"
     "   an element of its own, the last thing in the status, hidden from screen\n"
     "   readers; avalon-hours.js holds it on one line with the status's last\n"
     "   word, so it never starts a line alone. 7 px, as the design draws it.\n"
     "   Only a summary shows it: the store page's wide status line has none. */\n"
     ".avalon-hours .avalon-hours__caret {\n"
     "  display: none;\n"
     "}\n"
     "\n"
     ".avalon-hours .avalon-hours__summary .avalon-hours__caret {\n"
     "  display: inline-block;\n"
     "  width: 7px;\n"
     "  height: 7px;\n"
     "  margin: 0 2px 2px 8px;\n"
     "  border: solid currentColor;\n"
     "  border-width: 0 2px 2px 0;\n"
     "  transform: rotate(45deg);\n"
     "  transition: transform 0.2s;\n"
     "}\n"
     "\n"
     ".avalon-hours .avalon-hours__narrow[open] > .avalon-hours__summary .avalon-hours__caret {\n"
     "  margin-bottom: -2px;\n"
     "  transform: rotate(225deg);\n"
     "}\n"
     "\n"
     ".avalon-hours .avalon-hours__summary .avalon-hours__nowrap {\n"
     "  white-space: nowrap;\n"
     "}\n"),

    ("css: reduced motion stills the element caret",
     "@media (prefers-reduced-motion: reduce) {\n"
     "  .avalon-hours .avalon-hours__summary::after {\n"
     "    transition: none;\n"
     "  }\n"
     "}\n",
     "@media (prefers-reduced-motion: reduce) {\n"
     "  .avalon-hours .avalon-hours__summary .avalon-hours__caret {\n"
     "    transition: none;\n"
     "  }\n"
     "}\n"),

    ("css: Part 4c's phone sizes move into Part 4d's block",
     "/* ------------------------------------------ Part 4c: phones, portrait */\n"
     "\n"
     "/* At Elementor's mobile breakpoint the card's and the bubble's text is\n"
     "   15 px and the dealer's name 20 px. slp_avalon.js widens the bubble there\n"
     "   to the map's width less 24 px, and at 15 px every one of Aura's 313\n"
     "   addresses fits its two lines from 320 px up, on the cards and in the\n"
     "   bubble, and every one of its 97 emails its one line from 375 px up\n"
     "   (measured 2026-10-05, icons and words). The theme sets 16 px and 24 px\n"
     "   at 0-4-0 and 1-0-0; these are 1-2-0 and 1-1-0.\n"
     "\n"
     "   767 px is Elementor's default mobile breakpoint, and Aura's: Part 4's\n"
     "   hours fold follows a site that moves it (avalon_hours_breakpoint());\n"
     "   Part 4c's two 767 px blocks here, and slp_avalon.js's, do not. Check the\n"
     "   breakpoint before Tahoe or Avalon take Part 4c. */\n"
     "@media (max-width: 767px) {\n"
     "  #map_sidebar .results_wrapper .location_distance,\n"
     "  #map_sidebar .results_wrapper .sl_contact__info,\n"
     "  .slp_info_bubble .sl_popup_contact_info {\n"
     "    font-size: 15px;\n"
     "    line-height: 1.5;\n"
     "  }\n"
     "\n"
     "  #map_sidebar .results_wrapper .store_locator_name,\n"
     "  .slp_info_bubble #slp_bubble_name {\n"
     "    font-size: 20px;\n"
     "    line-height: 1.2;\n"
     "  }\n"
     "}\n"
     "\n"
     "/* Part 4c. Under 375 px the email address in the bubble is 14 px; its\n"
     "   label, icon or word, stays as the others. At 15 px on a 360 px phone\n"
     "   one of Aura's 97 emails wraps with the icons and five with the words;\n"
     "   at 14 px none do. At 320 px six still wrap, and break inside the\n"
     "   address (Part 4b) rather than being cut. Measured 2026-10-05. 1-2-1:\n"
     "   the link sets its own size, under the 15 px it would inherit. */\n",
     "/* ------------------------------------------ Part 4c: phones, portrait */\n"
     "\n"
     "/* Part 4c's 15 px text and 20 px names are in Part 4d's block, at the end\n"
     "   of the file: at 1024 px and under on the cards, at every width in the\n"
     "   bubble.\n"
     "\n"
     "   Part 4c. Under 375 px the email address in the bubble is 14 px; its\n"
     "   label stays as the others. Measured again with Part 4d's labels in a\n"
     "   column of their own, at the bubble's width on each phone (2026-10-07):\n"
     "   none of Aura's 97 emails wraps, from 320 px up; one that did would\n"
     "   break inside the address (Part 4b) rather than be cut. 1-2-1: the\n"
     "   link sets its own size, under the 15 px it would inherit. */\n"),
]

# Part 4c's icon block, the end of its file, gives way to Part 4d's block.
ICONS_START = ("/* Part 4c. On a phone, Font Awesome icons in place of the five labels, the\n"
               "   owner's request of 2026-10-05: map-marker-alt for Address:, phone-alt\n")

# Part 4d's block, appended where Part 4c's icon block began.
CSS_BLOCK = """
/* ------------------------------------------- Part 4d: the cards, one grid */

/* Part 4d. The approved find-a-dealer design (the owner's handoff of
   2026-10-06, decisions of 2026-10-07): a card's lines in one grid - the
   labels in a column of their own, Distance: the widest, the values beside
   them, an address's second line under its first and an opened week under
   its status - then the two buttons side by side at equal widths. SLP's
   left and centre cells and the field spans step aside (display:
   contents), so their children are the grid's items and nothing in the
   page moves: SLP, main.js and slp_avalon.js find every class and id where
   it was. A dealer's fax, should one have it, takes a row of its own, as
   does SLP's own hours span among the buttons. The card's box - colour,
   ring, corners, padding - and the buttons' colours and shapes are the
   theme's.

   Ids beat the theme's 0-4-0 and 0-5-0 card rules without !important.
   Every selector here names #map_sidebar, .slp_info_bubble, .avalon-hours
   or .avalon-label, which Parts 4 to 4c already put on WP Rocket's
   safelist. */
#map_sidebar .results_wrapper .results_entry {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  column-gap: 10px;
  row-gap: 4px;
  align-items: baseline;
}

#map_sidebar .results_entry > [id^="slp_left_cell_"],
#map_sidebar .results_entry > .sl_contact__info,
#map_sidebar .results_entry .location_distance,
#map_sidebar .results_entry .slp_result_street,
#map_sidebar .results_entry .slp_result_phone {
  display: contents;
}

#map_sidebar .results_entry .store_locator_name {
  grid-column: 1 / -1;
  margin: 0 0 8px;
  font-size: 24px;
  line-height: 1.2;
}

#map_sidebar .results_entry .slp_result_fax {
  grid-column: 1 / -1;
}

#map_sidebar .results_entry .avalon-hours--card {
  min-width: 0;
}

#map_sidebar .results_entry > [id^="slp_right_cell_"] {
  grid-column: 1 / -1;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

#map_sidebar .results_entry .slp_result_contact {
  flex: 1 1 100%;
  margin: 0;
}

#map_sidebar .results_entry .slp_result_contact.slp_result_directions {
  display: flex;
  flex: 1 1 0;
  min-width: 0;
}

#map_sidebar .results_entry .slp_result_contact > a {
  flex: 1 1 auto;
  min-width: 0;
}

/* The card's two buttons get the bubble's keyboard ring (the owner,
   2026-10-07): 2 px white, 2 px out, on :focus-visible only - a pointer
   click draws none, and hover stays the theme's. The theme takes the
   outline off their :focus at 0-5-1; this is 1-3-1. */
#map_sidebar .results_wrapper .slp_result_contact a:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}

/* At 1024 px and under - a phone's sideways two columns, a tablet's -
   and on the stacked phone layout: 15 px text, a 20 px name, the grid a
   little tighter. With the labels as words in a column of their own, a
   long address takes a third line on a narrow card. Of Aura's 313, on
   the cards: 45 at 320 px, 5 at 360, 3 at 375, 1 at 390 and 414, none
   from 430 to 667 - where Part 4c's icons gave 5, 4, 1 and 0 at 320 to
   390; sideways, 206 at 769, 73 at 844, 10 at 932, 3 at 1024 - where
   Part 4c's 16 px gave 244, 104, 25 and 5. Measured 2026-10-07 in
   Chromium, with the page's own fonts. */
@media (max-width: 1024px) {
  #map_sidebar .results_wrapper .results_entry {
    column-gap: 8px;
    row-gap: 3px;
  }

  #map_sidebar .results_entry .location_distance,
  #map_sidebar .results_entry > .sl_contact__info {
    font-size: 15px;
    line-height: 1.5;
  }

  #map_sidebar .results_entry .store_locator_name {
    margin-bottom: 5px;
    font-size: 20px;
  }

  #map_sidebar .results_entry > [id^="slp_right_cell_"] {
    margin-top: 11px;
  }
}

/* Stacked, on a screen wider than a phone - a tablet upright, a small
   phone sideways - a card runs up to 728 px: the two buttons keep about
   their desktop width instead of stretching to the card's. */
@media (min-width: 576px) and (max-width: 768px) {
  #map_sidebar .results_entry > [id^="slp_right_cell_"] {
    max-width: 456px;
  }
}

/* ------------------------------------- Part 4d: the hours, in the design */

/* Hours: in the label column, outside the <summary> it used to sit in: on
   screen only - the summary keeps the word for screen readers, in
   .avalon-hours__sr - and a click on it opens and shuts the week as
   before (avalon-hours.js). */
.avalon-label--hours {
  cursor: pointer;
}

.avalon-hours .avalon-hours__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0 0 0 0);
  clip-path: inset(50%);
  white-space: nowrap;
  border: 0;
}

/* The opened week on a card and in the bubble: a rule across, then the
   days, today first (avalon-hours.js) with a dot before it and TODAY
   after its hours, both in the primary colour; the other days indented by
   the dot's width, so every name starts in one column. A table still -
   its row headers are what a screen reader reads - pulled 15 px left, so
   the dot sits in the gap and the day names line up with the status
   above. TODAY is generated text with empty alternative text: the row
   already says aria-current="date". The hours never break here; TODAY
   drops under them where a card is too narrow for both. None of this
   reaches the store page: its week is as Part 4 drew it. */
#map_sidebar .avalon-hours--card .avalon-hours__week,
.slp_info_bubble .avalon-hours--card .avalon-hours__week {
  width: calc(100% + 15px);
  margin: 8px 0 0 -15px;
  padding-top: 8px;
  border-top: 1px solid var(--avalon-hours-rule, rgba(255, 255, 255, 0.15));
  border-collapse: separate;
  border-spacing: 0;
}

#map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr > th,
#map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr > td,
.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr > th,
.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr > td {
  padding: 0 0 3px;
  vertical-align: baseline;
}

#map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr > th,
.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr > th {
  width: 1%;
  padding-right: 20px;
  padding-left: 15px;
  white-space: nowrap;
}

.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr > th {
  padding-right: 16px;
}

#map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr.is-today > th,
.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr.is-today > th {
  padding-left: 0;
  font-weight: 800;
}

#map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td,
.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td {
  font-weight: 800;
}

#map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr.is-today > th::before,
.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr.is-today > th::before {
  content: "";
  display: inline-block;
  width: 7px;
  height: 7px;
  margin-right: 8px;
  border-radius: 50%;
  background: var(--avalon-hours-today);
  vertical-align: middle;
}

#map_sidebar .avalon-hours--card .avalon-hours__time,
.slp_info_bubble .avalon-hours--card .avalon-hours__time {
  white-space: nowrap;
}

#map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after,
.slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after {
  content: "TODAY";
  content: "TODAY" / "";
  display: inline-block;
  margin-left: 12px;
  color: var(--avalon-hours-today);
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  letter-spacing: 1px;
}

@media (max-width: 1024px) {
  #map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr > th {
    padding-right: 16px;
  }
}

/* A phone held sideways, a tablet: the week 20 px left, as approved. */
@media (min-width: 769px) and (max-width: 1024px) {
  #map_sidebar .avalon-hours--card .avalon-hours__week {
    width: calc(100% + 20px);
    margin-left: -20px;
  }
}

/* Narrow cards and bubbles. The card's grid and the bubble's are size
   containers for these two rules alone; a browser without container
   queries keeps the week as above, TODAY wrapping where it must.

   Under 320 px of content - a phone upright, a phone held sideways, the
   bubble on a phone - TODAY does not fit beside the hours, so it takes
   the line under them, flush with them, rather than wrap with a 12 px
   indent.

   Under 250 px - the results column of a phone held sideways, 769 to
   about 880 px wide, a 320 px phone upright, the bubble on one - the week
   does not fit beside the labels either, so the opened week takes the
   whole width under the Hours: line, its day names 15 px in, rather than
   run into the padding or past the edge. Measured on Aura 2026-10-07: the
   week needs 193 px, and has the content less 50 to 56 px beside the
   labels. */
#map_sidebar .results_wrapper .results_entry,
.slp_info_bubble .avalon-bubble__info {
  container-type: inline-size;
}

@container (max-width: 320px) {
  #map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after,
  .slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after {
    display: block;
    margin: 3px 0 2px;
  }
}

@container (max-width: 250px) {
  #map_sidebar .avalon-hours--card .avalon-hours__week,
  .slp_info_bubble .avalon-hours--card .avalon-hours__week {
    width: 100cqw;
    margin-left: calc(100% - 100cqw);
  }
}

/* ----------------------------------- Part 4d: the bubble, in three parts */

/* The name; the dealer's lines - the card's grid - in a body that
   scrolls on a phone, with a fade at its foot while there is more below
   (slp_avalon.js); then the two buttons in a row of their own under the
   body, where they stay while it scrolls. slp_avalon wraps Avalon's fields
   in .avalon-bubble__info and SLP's two button spans in
   .avalon-bubble__actions (slp_js_options at 120), so SLP's own lines -
   fax, description, its hours, image, tags, all empty on Aura - stay out
   of the grid, and the spans keep the ids main.js and slp_avalon.js read.
   15 px text and a 20 px name at every width; a long address takes a
   third line 105 times of Aura's 313 at 320 px, 12 at 360, 7 at 375, 4
   at 390 and once from 414 up, where Part 4c's icons gave 13, 10, 1 and
   1 (2026-10-07). The frame - its colour, corners, width, shadow and
   tail - is the theme's. */
.slp_info_bubble #slp_bubble_name {
  display: block;
  margin: 0;
  padding: 16px 16px 6px;
  font-size: 20px;
  line-height: 1.2;
}

.slp_info_bubble .sl_popup_contact_info {
  margin: 0;
  padding: 0 12px 16px 16px;
  font-size: 15px;
  line-height: 1.5;
}

.slp_info_bubble .avalon-bubble__info {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  column-gap: 8px;
  row-gap: 3px;
  align-items: baseline;
}

.slp_info_bubble .avalon-bubble__info > .avalon-bubble-distance,
.slp_info_bubble .avalon-bubble__info > #slp_bubble_address,
.slp_info_bubble .avalon-bubble__info > #slp_bubble_phone,
.slp_info_bubble .avalon-bubble__info > #slp_bubble_email {
  display: contents;
}

.slp_info_bubble .avalon-bubble__info > .avalon-hours--card {
  min-width: 0;
}

.slp_info_bubble .avalon-bubble__actions {
  display: flex;
  gap: 8px;
  padding: 12px 16px 16px;
  border-top: 1px solid var(--avalon-bubble-rule, rgba(255, 255, 255, 0.12));
}

.slp_info_bubble .avalon-bubble__actions > #slp_bubble_directions,
.slp_info_bubble .avalon-bubble__actions > #slp_bubble_website {
  display: flex;
  flex: 1 1 0;
  min-width: 0;
  margin: 0;
}

.slp_info_bubble .avalon-bubble__actions a {
  flex: 1 1 auto;
  min-width: 0;
}

.slp_info_bubble .avalon-bubble__actions br {
  display: none;
}

/* The fade: the bubble's black over the body's last 28 px while there is
   more below. slp_avalon.js sets .is-more; at the end of the scroll, and
   wherever nothing scrolls, it is not drawn. */
.slp_info_bubble .sl_popup_contact_info::after {
  content: "";
  display: block;
  position: sticky;
  bottom: 0;
  height: 28px;
  margin-top: -28px;
  background: linear-gradient(transparent, var(--avalon-bubble-bg, #080808));
  pointer-events: none;
  visibility: hidden;
}

.slp_info_bubble .sl_popup_contact_info.is-more::after {
  visibility: visible;
}

/* On a phone - upright, or sideways and short - the body is at most
   250 px or 45% of the screen's height, and scrolls, with a thin visible
   scrollbar; the buttons stay under it. On a larger screen the bubble
   grows as it did. */
@media (max-width: 767px), (max-height: 500px) {
  .slp_info_bubble .sl_popup_contact_info {
    max-height: min(250px, 45vh);
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.45) transparent;
  }

  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar {
    width: 5px;
  }

  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.45);
    border-radius: 3px;
  }

  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar-track {
    background: transparent;
  }
}
"""

# ===========================================================================
# class.slp_avalon.php (CRLF)
# ===========================================================================

CLASS_EDITS = [

    ("class: avalon_hours_markup() - the docblock says where Hours: is now",
     "         * CARD. The <details> only, labelled \"Hours:\", on every width.\n"
     "         *\n"
     "         * NO EMPTY <span>. SLP hides every empty span in a result as it\n"
     "         * inserts it (slp_core.js 1424, s0.274), so a span this markup left\n"
     "         * empty for the script to fill would stay hidden for good. Every\n"
     "         * span below carries text from the start.\n",
     "         * CARD. The <details> only, on every width. From Part 4d its label\n"
     "         * stands in front of the block, in the card's label column - on\n"
     "         * screen only, aria-hidden; the summary keeps \"Hours:\" for screen\n"
     "         * readers (avalon-hours__sr), and a click on the label still opens\n"
     "         * the week (avalon-hours.js). Each day's hours sit in a span of\n"
     "         * their own, which on a card never breaks, so TODAY wraps under\n"
     "         * them instead (avalon-hours.css); the store page's may, as before.\n"
     "         *\n"
     "         * NO EMPTY <span>. SLP hides every empty span in a result as it\n"
     "         * inserts it (slp_core.js 1424, s0.274), so a span this markup left\n"
     "         * empty for the script to fill would stay hidden for good. Every\n"
     "         * span below carries text from the start, and the caret - empty by\n"
     "         * nature - is an <i> (Part 4d).\n"),

    ("class: avalon_hours_markup() - each day's hours in a span that never breaks",
     "                       . '</th><td>' . esc_html( $d[2] ) . '</td></tr>';\n",
     "                       . '</th><td><span class=\"avalon-hours__time\">' . esc_html( $d[2] ) . '</span></td></tr>';\n"),

    ("class: avalon_hours_markup() - the summary: Hours: for screen readers, the caret held to its word",
     "            $summary = '<summary class=\"avalon-hours__summary\">'\n"
     "                     . ( $card ? '<b class=\"avalon-label avalon-label--hours\">Hours:</b> ' : '' )\n"
     "                     . '<span class=\"avalon-hours__status\">See hours</span></summary>';\n",
     "            $summary = '<summary class=\"avalon-hours__summary\">'\n"
     "                     . ( $card ? '<span class=\"avalon-hours__sr\">Hours: </span>' : '' )\n"
     "                     . '<span class=\"avalon-hours__status\">See <span class=\"avalon-hours__nowrap\">hours'\n"
     "                     . '<i class=\"avalon-hours__caret\" aria-hidden=\"true\"></i></span></span></summary>';\n"),

    ("class: avalon_hours_markup() - a card's Hours: in front of its block",
     "            if ( $card ) {\n"
     "                return '<div class=\"avalon-hours avalon-hours--card\" data-avalon-hours=\"' . $data . '\">'\n"
     "                     . $narrow . '</div>';\n"
     "            }\n",
     "            if ( $card ) {\n"
     "                return '<b class=\"avalon-label avalon-label--hours\" aria-hidden=\"true\">Hours:</b>'\n"
     "                     . '<div class=\"avalon-hours avalon-hours--card\" data-avalon-hours=\"' . $data . '\">'\n"
     "                     . $narrow . '</div>';\n"
     "            }\n"),

    ("class: Part 4c's safelist no longer names the icons' class",
     "         * Cards, bubbles and the .avalon-fa class all appear only after a\n"
     "         * search, a click or a script, so Remove Unused CSS never sees\n"
     "         * them; written from the selector's start as WP Rocket 3.11.0.2\n"
     "         * and later read them (s0.284). .gm-style covers the map's own\n"
     "         * images, #map_sidebar the cards' type sizes on a phone.\n",
     "         * Cards and bubbles appear only after a search or a click, so\n"
     "         * Remove Unused CSS never sees them; written from the selector's\n"
     "         * start as WP Rocket 3.11.0.2 and later read them (s0.284).\n"
     "         * .gm-style covers the map's own images, #map_sidebar the cards.\n"
     "         * Part 4d took out .avalon-fa with the icons it named.\n"),

    ("class: Part 4c's safelist - the icons' pattern out",
     "            $list[] = '(.*).avalon-address(.*)';\n"
     "            $list[] = '(.*).avalon-fa(.*)';\n",
     "            $list[] = '(.*).avalon-address(.*)';\n"),
]

WIRE_ANCHOR = (
    "            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_map'));\n")
WIRE_BLOCK = WIRE_ANCHOR + (
    "            //\n"
    "            // v0.0.27 Part 4d. The approved find-a-dealer design: the bubble\n"
    "            // in three parts.\n"
    "            //\n"
    "            // The bubble layout on slp_js_options at 120, after Part 4c's\n"
    "            // callback at 110 has made the address one field: Avalon's\n"
    "            // fields wrapped for the grid, SLP's two button spans for the\n"
    "            // row, the spans' ids kept. The cards need no new layout -\n"
    "            // avalon-hours.css lays SLP's own cells out as one grid - and\n"
    "            // nothing new goes to WP Rocket: every new selector names\n"
    "            // #map_sidebar, .slp_info_bubble or .avalon-hours, safelisted\n"
    "            // already.\n"
    "            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_frame'), 120, 1);\n")

INSERT_BEFORE = "        public function avalon_rest_protected_slugs(){\n"

FRAME_BLOCK = r"""        /**
         * v0.0.27 Part 4d. The approved find-a-dealer design.
         *
         * The owner's handoff of 2026-10-06 (find-a-dealer-HANDOFF.md, and
         * the HTML it describes) and the decisions of 2026-10-07: labels as
         * words on every screen - Part 4c's icons gone; a hovered card in the
         * chosen card's grey, without its ring; the bubble's keyboard ring on
         * the card's buttons; the hover and focus of every link and button
         * otherwise as they were.
         *
         *   avalon-hours.css   the card's lines in one grid, the buttons side
         *                      by side; the bubble in three parts; the hours
         *                      as drawn - a rule, a dot, TODAY
         *   avalon-hours.js    the weekday in full; the caret held to the
         *                      status's last word; Hours:, moved out of the
         *                      summary, still opens the week
         *   slp_avalon.js      the chosen dealer's card ringed; the bubble's
         *                      fade while there is more to scroll
         *   here               Hours: in front of a card's hours block
         *                      (avalon_hours_markup(), Part 4's); and the
         *                      bubble layout below
         *
         * The cards need nothing here beyond that: SLP's three cells are
         * laid out as one grid where they are. The bubble's lines are not
         * SLP's cells but loose spans, among them SLP's own empty fields, so
         * Avalon's are wrapped for the grid, and the two buttons for their
         * row - wrapped, never moved or renamed: main.js opens the Contact
         * Dealer form for #slp_bubble_website .storelocatorlink, and
         * slp_avalon.js focuses it and leaves full screen for it.
         */

        /**
         * v0.0.27 Part 4d. The first tag of kind $tag at offset $at, and
         * everything it holds: the offset just past its closing tag, counted
         * through any of its own kind nested inside; false when it does not
         * close.
         */
        private static function avalon_layout_tag_end( $layout, $at, $tag ){
            if ( ! preg_match_all( '/<' . $tag . '\b[^>]*>|<\/' . $tag . '\s*>/i', $layout, $m, PREG_OFFSET_CAPTURE, $at ) ) {
                return false;
            }
            $depth = 0;
            foreach ( $m[0] as $t ) {
                $depth += ( '/' === $t[0][1] ) ? -1 : 1;
                if ( 0 === $depth ) {
                    return $t[1] + strlen( $t[0] );
                }
            }
            return false;
        }

        /**
         * v0.0.27 Part 4d. Whether a run of layout is whole: as many <span>
         * and <div> opened as closed, never more closed than open.
         */
        private static function avalon_layout_whole( $run ){
            foreach ( array( 'span', 'div' ) as $tag ) {
                if ( ! preg_match_all( '/<' . $tag . '\b[^>]*>|<\/' . $tag . '\s*>/i', $run, $m ) ) {
                    continue;
                }
                $depth = 0;
                foreach ( $m[0] as $t ) {
                    $depth += ( '/' === $t[1] ) ? -1 : 1;
                    if ( $depth < 0 ) {
                        return false;
                    }
                }
                if ( 0 !== $depth ) {
                    return false;
                }
            }
            return true;
        }

        /**
         * v0.0.27 Part 4d. The bubble layout in three parts.
         *
         *   info     from Part 4b's Distance: line to Part 4's hours field,
         *            inside SLP's sl_popup_contact_info, wrapped in
         *            <div class="avalon-bubble__info"> - the grid; SLP's
         *            other lines (fax, description, its own hours, image,
         *            tags) stay after it, out of the grid
         *   actions  SLP's Directions and Website spans, after the contact
         *            block and side by side with nothing but white space
         *            between, wrapped in <div class="avalon-bubble__actions">
         *
         * Each wrap is skipped, not forced: when its anchors are absent, out
         * of order or outside the contact block; when what it would hold is
         * not whole; when its wrapper is already there - so a second pass
         * changes nothing and a layout this does not recognise, SLP's own
         * default among them, is left as it was. Insertions by offset, never
         * through a regex replacement string.
         */
        public function avalon_bubble_layout_frame( $layout ){
            $layout = (string) $layout;
            if ( ! preg_match( '/<div\b[^>]*\sclass="sl_popup_contact_info"[^>]*>/', $layout, $c, PREG_OFFSET_CAPTURE ) ) {
                return $layout;
            }
            $open  = $c[0][1] + strlen( $c[0][0] );
            $close = self::avalon_layout_tag_end( $layout, $c[0][1], 'div' );
            if ( false === $close ) {
                return $layout;
            }
            $inner = $close - strlen( '</div>' );

            if ( false === strpos( $layout, 'avalon-bubble__info' ) ) {
                $field = '[slp_location avalon_hours_html]';
                $a = strpos( $layout, '<span class="avalon-bubble-distance">', $open );
                $b = ( false !== $a ) ? strpos( $layout, $field, $a ) : false;
                if ( false !== $b && $b + strlen( $field ) <= $inner ) {
                    $b += strlen( $field );
                    $run = substr( $layout, $a, $b - $a );
                    if ( self::avalon_layout_whole( $run ) ) {
                        $layout = substr( $layout, 0, $a ) . '<div class="avalon-bubble__info">' . $run . '</div>' . substr( $layout, $b );
                        $close += strlen( '<div class="avalon-bubble__info"></div>' );
                    }
                }
            }

            if ( false === strpos( $layout, 'avalon-bubble__actions' )
                 && preg_match( '/<span\b[^>]*\sid="slp_bubble_directions"[^>]*>/', $layout, $d, PREG_OFFSET_CAPTURE, $close ) ) {
                $a = $d[0][1];
                $e = self::avalon_layout_tag_end( $layout, $a, 'span' );
                if ( false !== $e && preg_match( '/\G\s*<span\b[^>]*\sid="slp_bubble_website"[^>]*>/', $layout, $w, 0, $e ) ) {
                    $e = self::avalon_layout_tag_end( $layout, $e + strlen( $w[0] ) - strlen( ltrim( $w[0] ) ), 'span' );
                    if ( false !== $e ) {
                        $layout = substr( $layout, 0, $a ) . '<div class="avalon-bubble__actions">'
                                . substr( $layout, $a, $e - $a ) . '</div>' . substr( $layout, $e );
                    }
                }
            }

            return $layout;
        }

        /**
         * v0.0.27 Part 4d. The bubble layout the browser is given, in three
         * parts. On slp_js_options at 120, after Part 4c's callback at 110.
         * Anything that is not a string is left alone.
         */
        public function avalon_js_options_frame( $options ){
            if ( is_array( $options ) && isset( $options['bubblelayout'] ) && is_string( $options['bubblelayout'] ) ) {
                $options['bubblelayout'] = $this->avalon_bubble_layout_frame( $options['bubblelayout'] );
            }
            return $options;
        }

"""

# ===========================================================================
# slp_avalon.js (CRLF)
# ===========================================================================

MAP_JS_EDITS = [

    ("slp_avalon.js: a placeholder that fits the field",
     "       //Add search placeholder\n"
     "      $(\"#addressInput\").attr('placeholder','Enter City, State, or Zip Code');\n",
     "       //Add search placeholder. v0.0.27 Part 4d: short enough to show whole\n"
     "       //at every width - the field keeps 200 px for Find Locations above\n"
     "       //1024 px, and the long one was cut off on laptops.\n"
     "      $(\"#addressInput\").attr('placeholder','City, State, or ZIP');\n"),

    ("slp_avalon.js: avalon_map's header - the chosen card and the fade, no icons",
     "   * ICONS ON PHONES. fa() puts .avalon-fa on <html> once Font Awesome 5's\n"
     "   * solid face has loaded, on the locator's page only; avalon-hours.css\n"
     "   * draws the labels as icons only under it, so without the font the words\n"
     "   * stay.\n",
     "   * THE CHOSEN CARD (Part 4d). The dealer whose bubble was chosen - by a\n"
     "   * click, a tap or a key on its pin or card, or into the bubble - has its\n"
     "   * card marked .active, which the theme draws as the design's ring, until\n"
     "   * that bubble closes. A bubble opened by hovering marks nothing. main.js\n"
     "   * marks a clicked card the same way.\n"
     "   *\n"
     "   * THE FADE (Part 4d). On a phone the bubble's body scrolls; while there\n"
     "   * is more below, avalon-hours.css fades its foot (.is-more on\n"
     "   * .sl_popup_contact_info): looked at as the bubble opens, as it scrolls,\n"
     "   * as its week opens or shuts, and after a resize.\n"
     "   *\n"
     "   * Part 4c's Font Awesome labels went with Part 4d: words on every\n"
     "   * screen, the owner's decision of 2026-10-07.\n"),

    ("slp_avalon.js: no icon flag in the state",
     "      icon: null,         //the hover icon, resolved; \"\" for none\n"
     "      fa: false\n"
     "    };\n",
     "      icon: null          //the hover icon, resolved; \"\" for none\n"
     "    };\n"),

    ("slp_avalon.js: cls(), ring() and more() after container()",
     "    function controls(o) {\n",
     "    //Part 4d. A class on or off by name, the others left as they are.\n"
     "    function cls(n, name, on) {\n"
     "      if (on === has_class(n, name)) {\n"
     "        return;\n"
     "      }\n"
     "      n.className = on ? (n.className ? n.className + \" \" : \"\") + name\n"
     "                       : (\" \" + n.className + \" \").replace(\" \" + name + \" \", \" \").replace(/^\\s+|\\s+$/g, \"\");\n"
     "    }\n"
     "\n"
     "    //Part 4d. The chosen dealer's card marked .active, and no other; with\n"
     "    //no dealer, none.\n"
     "    function ring(e) {\n"
     "      var id = e ? \"slp_results_wrapper_\" + e.id : \"\";\n"
     "      var on = document.querySelectorAll(\"#map_sidebar .results_wrapper.active\");\n"
     "      for (var i = 0; i < on.length; i++) {\n"
     "        if (on[i].id !== id) {\n"
     "          cls(on[i], \"active\", false);\n"
     "        }\n"
     "      }\n"
     "      var c = id ? document.getElementById(id) : null;\n"
     "      if (c) {\n"
     "        cls(c, \"active\", true);\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4d. The fade at the foot of the bubble's body while there is\n"
     "    //more below it; none at the end, none where nothing scrolls.\n"
     "    function more() {\n"
     "      var b = bubble();\n"
     "      var s = b ? b.querySelector(\".sl_popup_contact_info\") : null;\n"
     "      if (s) {\n"
     "        cls(s, \"is-more\", s.scrollHeight - s.scrollTop - s.clientHeight > 1);\n"
     "      }\n"
     "    }\n"
     "\n"
     "    function controls(o) {\n"),

    ("slp_avalon.js: clear() takes the ring off",
     "      if (e) {\n"
     "        lit(e, hovered(e));\n"
     "      }\n"
     "      restore();\n",
     "      if (e) {\n"
     "        lit(e, hovered(e));\n"
     "      }\n"
     "      ring(null);\n"
     "      restore();\n"),

    ("slp_avalon.js: show() rings the chosen dealer's card",
     "      if (!hover) {\n"
     "        st.pinned = true;\n"
     "      }\n"
     "    }\n",
     "      if (!hover) {\n"
     "        st.pinned = true;\n"
     "        ring(e);\n"
     "      }\n"
     "    }\n"),

    ("slp_avalon.js: resized() looks at the fade",
     "    function resized() {\n"
     "      st.rs = 0;\n",
     "    function resized() {\n"
     "      st.rs = 0;\n"
     "      more();\n"),

    ("slp_avalon.js: a click into the bubble rings its card",
     "        c.addEventListener(\"click\", function (ev) {\n"
     "          if (st.open) {\n"
     "            st.pinned = true;\n"
     "            cancel();\n"
     "          }\n",
     "        c.addEventListener(\"click\", function (ev) {\n"
     "          if (st.open) {\n"
     "            st.pinned = true;\n"
     "            ring(st.current);\n"
     "            cancel();\n"
     "          }\n"),

    ("slp_avalon.js: focus into the bubble rings its card",
     "          var from = ev && ev.relatedTarget;\n"
     "          if (st.open) {\n"
     "            st.pinned = true;\n"
     "            cancel();\n"
     "          }\n",
     "          var from = ev && ev.relatedTarget;\n"
     "          if (st.open) {\n"
     "            st.pinned = true;\n"
     "            ring(st.current);\n"
     "            cancel();\n"
     "          }\n"),

    ("slp_avalon.js: ready() wires the fade to the new content",
     "      if (st.wantFocus) {\n"
     "        soon();\n"
     "      }\n"
     "    }\n"
     "\n"
     "    function enter(e, kind) {\n",
     "      var b = bubble();\n"
     "      var s = b ? b.querySelector(\".sl_popup_contact_info\") : null;\n"
     "      if (s && !s.avalonMore) {\n"
     "        s.avalonMore = true;\n"
     "        s.addEventListener(\"scroll\", more, false);\n"
     "        s.addEventListener(\"toggle\", more, true);\n"
     "      }\n"
     "      more();\n"
     "      setTimeout(more, 0);\n"
     "      if (st.wantFocus) {\n"
     "        soon();\n"
     "      }\n"
     "    }\n"
     "\n"
     "    function enter(e, kind) {\n"),

    ("slp_avalon.js: fa() taken out",
     "\n"
     "    //On the locator's page, once: .avalon-fa when Font Awesome's solid face\n"
     "    //loads. fonts.load() resolves with the faces that matched - none when\n"
     "    //the page has no such face, which fonts.check() would call loaded.\n"
     "    function fa() {\n"
     "      var d = document;\n"
     "      if (st.fa || !d.getElementById(\"map_sidebar\")) {\n"
     "        return;\n"
     "      }\n"
     "      st.fa = true;\n"
     "      if (!d.fonts || typeof d.fonts.load !== \"function\") {\n"
     "        return;\n"
     "      }\n"
     "      try {\n"
     "        d.fonts.load('900 16px \"Font Awesome 5 Free\"', \"\\uf3c5\").then(function (faces) {\n"
     "          if (faces && faces.length) {\n"
     "            d.documentElement.classList.add(\"avalon-fa\");\n"
     "          }\n"
     "        }, function () {\n"
     "          //No icon font: the labels keep their words.\n"
     "        });\n"
     "      } catch (x) {\n"
     "        //As above.\n"
     "      }\n"
     "    }\n"
     "\n"
     "    return {\n",
     "\n"
     "    return {\n"),

    ("slp_avalon.js: the block exports ring() and more(), and no fa()",
     "      close: close,\n"
     "      fa: fa,\n"
     "      state: st\n"
     "    };\n"
     "  })();\n"
     "  jQuery(function () {\n"
     "    avalon_map.fa();\n"
     "  });\n",
     "      close: close,\n"
     "      ring: ring,\n"
     "      more: more,\n"
     "      state: st\n"
     "    };\n"
     "  })();\n"),
]

# ===========================================================================
# The build
# ===========================================================================

def build_hours_js(js):
    for label, old, new in HOURS_JS_EDITS:
        js = sub_once(js, old, new, label)
    check('\r' not in js and all(ord(c) < 128 for c in js), 'avalon-hours.js: LF and ASCII')
    check('SHORT' not in js and js.count('DAYS[day]') == 2, 'avalon-hours.js: the weekday in full, both places')
    check(js.count('doc.createElement("i")') == 1 and js.count('held.className = "avalon-hours__nowrap";') == 1,
          'avalon-hours.js: one caret element, an <i>, and one nowrap span')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')'), 'avalon-hours.js: braces and parentheses balance')
    return js


def build_css(css):
    for label, old, new in CSS_EDITS:
        css = sub_once(css, old, new, label)
    i = css.find(ICONS_START)
    check(i > 0 and css.count(ICONS_START) == 1, 'css: Part 4c\'s icon block found once, at the end')
    css = css[:i] + CSS_BLOCK.lstrip('\n')
    print("  patched   css: the icon block gives way to Part 4d's block")
    b = bare_css(css)
    check('\r' not in css and all(ord(c) < 128 for c in css), 'css: LF and ASCII')
    check(b.count('{') == b.count('}'), 'css: braces balance')
    check('avalon-fa' not in css, 'css: no icon rule left')
    check('!important' not in b, 'css: no !important in any rule')
    check(b.count('@container') == 2 and b.count('container-type: inline-size;') == 1,
          'css: two container queries, one container declaration')
    check('avalon-hours__summary::after' not in css, 'css: no generated caret left')
    return css


def build_class(php):
    for label, old, new in CLASS_EDITS:
        php = sub_once(php, crlf(old), crlf(new), label)
    php = sub_once(php, crlf(WIRE_ANCHOR), crlf(WIRE_BLOCK), 'class: the Part 4d registration')
    php = sub_once(php, crlf(INSERT_BEFORE), crlf(FRAME_BLOCK) + crlf(INSERT_BEFORE), 'class: the Part 4d block')
    check(php.count("\n") == php.count("\r\n") and php.count("\r") == php.count("\r\n"), 'class: pure CRLF')
    check(all(ord(c) < 128 for c in FRAME_BLOCK + WIRE_BLOCK) and '\t' not in FRAME_BLOCK + WIRE_BLOCK,
          'class: the inserted PHP is ASCII, no tabs')
    check(php.count("'avalon_js_options_frame'") == 1 and php.count('public function avalon_bubble_layout_frame(') == 1,
          'class: the frame registered once and defined once')
    check("avalon-fa(.*)" not in php, 'class: the icons\' safelist pattern gone')
    return php


def build_map_js(js):
    for label, old, new in MAP_JS_EDITS:
        js = sub_once(js, crlf(old), crlf(new), label)
    check(js.count("\n") == js.count("\r\n") and js.count("\r") == js.count("\r\n"), 'slp_avalon.js: pure CRLF')
    check(all(ord(c) < 128 for c in js), 'slp_avalon.js: ASCII')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')') and js.count('[') == js.count(']'),
          'slp_avalon.js: braces, parentheses and brackets balance')
    check('avalon-fa' not in js and 'fa()' not in js and 'fonts.load' not in js, 'slp_avalon.js: no icon code left')
    check(js.count("'City, State, or ZIP'") == 1, 'slp_avalon.js: the placeholder, once')
    blk = js.split('var avalon_map = (function () {')[1].split('  })();')[0]
    for banned in ('innerHTML', 'eval(', 'new Function', '.html(', 'setInterval'):
        check(banned not in blk, 'slp_avalon.js: the block never uses ' + banned)
    return js


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part4d.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part4d  slp_avalon 0.0.27  PART 4d (the approved find-a-dealer design)")
    print("")

    texts = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                     "       src_dir must hold Part 4c's class, stylesheet and slp_avalon.js and Part 4b's avalon-hours.js."
                     .format(name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        texts[name] = text
    print("")

    out = {
        'class.slp_avalon.php': build_class(texts['class.slp_avalon.php']),
        'avalon-hours.css':     build_css(texts['avalon-hours.css']),
        'avalon-hours.js':      build_hours_js(texts['avalon-hours.js']),
        'slp_avalon.js':        build_map_js(texts['slp_avalon.js']),
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
    print("  note      slp_avalon.php is not an input: it does not change.")
    print("  note      no schema change. HOURS_DB_VERSION stays 2.")
    print("")
    if any(p is None for p in OUT_PINS.values()):
        print("output NOT pinned - not a release build")
        sys.exit(1)
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
