#!/usr/bin/env python3
"""
build-v027-part4e.py

slp_avalon v0.0.27, PART 4e - the owner's review of Part 4d on Aura DEV.

WHAT THIS RELEASE DOES
----------------------
Part 4d went to Aura DEV on 2026-10-07 and the owner looked at it there, on
a desktop and in the device toolbar, beside a competitor's locator. What
came back, and the four decisions taken the same day:

1. A CARD WITH ITS WEEK OPEN TOOK NO CLICK. Part 4 kept every click in a
   card's hours block from the card, so with the week open most of the
   card chose nothing and showed no ring. Only the Hours line itself - the
   <summary> - and an attribution link keep their clicks now; a click on a
   day's row reaches the card, as one on its address does.

2. NO BUBBLE ON A PHONE ("No bubble"). On a phone - 767 px wide or less,
   or 500 px high or less - a pin opens no bubble: it covered most of the
   map to repeat the card beside or under it. The pin is lit, and its
   dealer's card is marked, brought into view, flashed and given focus.
   Desktops and tablets keep the bubble, and so does a map shown full
   screen, whatever the screen: no card can be seen behind it.

3. THE CARD IN VIEW. A dealer chosen on the map - on a phone or not - has
   its card brought where it can be seen: beside the map, the results
   scroll until the card is at the top of what shows of them; under the
   map - a phone upright - the card moves to the top of the list, and back
   when the choice ends. A dealer chosen on its own card is left where the
   pointer found it.

4. TODAY UNDER ITS HOURS ("Under the hours everywhere"), on the cards and
   in the bubble at every width, with 2 px more under it than Part 4d's
   narrow cards gave it, so that it reads with its own hours.

5. THE BUBBLE AS WIDE AS ITS CONTENT. The theme's fixed 376 px left dead
   space beside every dealer whose lines are short (the theme's half is
   patch-style-fad-part4e.py). Here: the bubble is no longer a size
   container - what is sized by its content cannot be one - and
   slp_avalon.js measures the week open once, as the bubble arrives, and
   keeps that width as its least, so opening the hours does not widen it
   under the pointer.

6. THE SHADE. Part 4d's fade at the foot of the bubble's body, and the cap
   that made the body scroll on a phone, are gone with the phone's bubble.

Open stays green ("Keep the green"). The map's height on a phone ("About
half the screen") is the theme's.

WHAT IS PATCHED
---------------
avalon-hours.js: three anchored edits - the header twice, keep().
avalon-hours.css: eight anchored edits - TODAY, the container rule, the
fade and the phone's cap out, the flash in. slp_avalon.js: nineteen
anchored edits, all inside Part 4c's avalon_map block.

class.slp_avalon.php is NOT an input and does not change: Part 4e is the
class of Part 4d, 778185553d663139707b9d23dff00407, 339,614 bytes. Two
lines of its Part 4d docblocks describe what Part 4e has since changed -
the fade, and TODAY wrapping under the hours - and are left as history.

ENCODING
--------
Every file is read and written as ISO-8859-1, so every byte round-trips;
slp_avalon.js stays pure CRLF, the stylesheet and avalon-hours.js pure LF.
Every edit is written LF here and turned CRLF where its file is. Every
anchor must match exactly once, in the file as the edits before it left it.

    python build-v027-part4e.py <src_dir> <out_dir>

src_dir holds Part 4d's avalon-hours.css, avalon-hours.js and
slp_avalon.js, by those names. Output pinned; nothing is written unless all
three match.
"""

import hashlib
import io
import os
import re
import sys

CRLF = "\r\n"

PINS = {
    'avalon-hours.css': ('ffe117b6c77e2dfa3d082b5bb7c8ca10', 26810),
    'avalon-hours.js':  ('6d4c084f963e68271b0194926663eecf', 17315),
    'slp_avalon.js':    ('00733408915197e40c78ec03eed61922', 103266),
}

OUT_PINS = {
    'avalon-hours.css': ('8e862ce347b040f329b77969c226165b', 26533),
    'avalon-hours.js': ('588939bf366c2760ba46127d6d9951a6', 18319),
    'slp_avalon.js': ('5df0fcc4ea5d403e897e99f49f869afa', 119874),
}

CLASS_PIN = ('778185553d663139707b9d23dff00407', 339614)


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

    ("avalon-hours.js: the header names Part 4e",
     " * avalon-hours.js\n"
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n"
     " *\n",
     " * avalon-hours.js\n"
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4e.\n"
     " *\n"),

    ("avalon-hours.js: the header says what Part 4e changes",
     " * and shuts the week.\n"
     " */\n",
     " * and shuts the week.\n"
     " *\n"
     " * Part 4e: on a card, only the Hours line itself - the <summary> - and an\n"
     " * attribution link keep their clicks from the card. A click on the opened\n"
     " * week reaches it: with the week open, most of the card took no click and\n"
     " * showed no ring (the owner, 2026-10-07).\n"
     " */\n"),

    ("avalon-hours.js: keep() - only the summary and a link keep their clicks from the card",
     "  /**\n"
     "   * On a result card, a click anywhere in the hours block - the Hours line,\n"
     "   * the opened week, an attribution link - does what it does there and\n"
     "   * nothing else. SLP binds a click on every result card that recentres\n"
     "   * the map and opens its bubble, and the theme marks the card active;\n"
     "   * neither should fire because a visitor wanted the hours. The native\n"
     "   * toggle is the click's default action on the summary, so it still\n"
     "   * happens. The store page has no such handler and is left alone.\n"
     "   */\n"
     "  function keep(e) {\n"
     "    e.stopPropagation();\n"
     "  }\n",
     "  /**\n"
     "   * On a result card, a click on the Hours line - the <summary> - or on an\n"
     "   * attribution link does what it does there and nothing else. SLP binds a\n"
     "   * click on every result card that chooses its dealer (slp_avalon.js), and\n"
     "   * the theme marks the card active; neither should fire because a visitor\n"
     "   * wanted the hours. The native toggle is the click's default action on\n"
     "   * the summary, so it still happens. The store page has no such handler\n"
     "   * and is left alone.\n"
     "   *\n"
     "   * Part 4e. The opened week is the card again. Until Part 4e a click\n"
     "   * anywhere in the block was kept, so a card with its week open took no\n"
     "   * click over most of its height and showed no ring (the owner,\n"
     "   * 2026-10-07). A click on a day's row now goes on to the card, as one on\n"
     "   * its address does. The walk up from the click stops at the block: a\n"
     "   * link the block itself might sit in is not this function's to judge.\n"
     "   */\n"
     "  function keep(e) {\n"
     "    var n = e && e.target;\n"
     "    if (n && n.nodeType === 3) {\n"
     "      n = n.parentNode;\n"
     "    }\n"
     "    for (; n && n.tagName; n = n.parentNode) {\n"
     "      if (n.tagName === \"SUMMARY\" || n.tagName === \"A\") {\n"
     "        e.stopPropagation();\n"
     "        return;\n"
     "      }\n"
     "      if ((\" \" + n.className + \" \").indexOf(\" avalon-hours \") >= 0) {\n"
     "        return;\n"
     "      }\n"
     "    }\n"
     "  }\n"),

]


# ===========================================================================
# avalon-hours.css (LF)
# ===========================================================================

CSS_EDITS = [

    ("css: the header names Part 4e",
     " * avalon-hours.css\n"
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n"
     " *\n",
     " * avalon-hours.css\n"
     " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4e.\n"
     " *\n"),

    ("css: the header says what Part 4e changes",
     " * 2026-10-07).\n"
     " */\n",
     " * 2026-10-07).\n"
     " *\n"
     " * PART 4e, in Part 4d's blocks and the last block of the file: the\n"
     " * owner's decisions of 2026-10-07, with Part 4d on Aura DEV. TODAY under\n"
     " * its hours at every width, on the cards and in the bubble, with more\n"
     " * room under it than over it. No bubble on a phone - slp_avalon.js brings\n"
     " * the dealer's card into view instead, and the card flashes - so the\n"
     " * bubble's body no longer scrolls anywhere: Part 4d's cap on it, its\n"
     " * scrollbar and the fade at its foot are gone, and Part 4c's 14 px email\n"
     " * with them. The bubble is as wide as its content (the theme), so it is no\n"
     " * longer a size container. Open stays green.\n"
     " */\n"),

    ("css: Part 4c's 14 px email under 375 px goes with the phone's bubble",
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
     "   link sets its own size, under the 15 px it would inherit. */\n"
     "@media (max-width: 374px) {\n"
     "  .slp_info_bubble #slp_bubble_email a.avalon-email {\n"
     "    font-size: 14px;\n"
     "  }\n"
     "}\n"
     "\n",
     "\n"
     "/* Part 4c's 15 px text and 20 px names are in Part 4d's block, further\n"
     "   down: at 1024 px and under on the cards, at every width in the bubble.\n"
     "   Its 14 px email under 375 px went with Part 4e: a phone has no bubble\n"
     "   to show it in. */\n"
     "\n"),

    ("css: the week's comment - TODAY under its hours at every width",
     "   days, today first (avalon-hours.js) with a dot before it and TODAY\n"
     "   after its hours, both in the primary colour; the other days indented by\n"
     "   the dot's width, so every name starts in one column. A table still -\n"
     "   its row headers are what a screen reader reads - pulled 15 px left, so\n"
     "   the dot sits in the gap and the day names line up with the status\n"
     "   above. TODAY is generated text with empty alternative text: the row\n"
     "   already says aria-current=\"date\". The hours never break here; TODAY\n"
     "   drops under them where a card is too narrow for both. None of this\n"
     "   reaches the store page: its week is as Part 4 drew it. */\n"
     "#map_sidebar .avalon-hours--card .avalon-hours__week,\n",
     "   days, today first (avalon-hours.js) with a dot before it and TODAY\n"
     "   under its hours, both in the primary colour; the other days indented by\n"
     "   the dot's width, so every name starts in one column. A table still -\n"
     "   its row headers are what a screen reader reads - pulled 15 px left, so\n"
     "   the dot sits in the gap and the day names line up with the status\n"
     "   above. TODAY is generated text with empty alternative text: the row\n"
     "   already says aria-current=\"date\". The hours never break here. None of\n"
     "   this reaches the store page: its week is as Part 4 drew it.\n"
     "\n"
     "   Part 4e. TODAY sits under its hours at every width (the owner,\n"
     "   2026-10-07): Part 4d put it beside them wherever both fitted, which\n"
     "   made the bubble wider than anything else in it needed. 3 px over it\n"
     "   and 4 px under - 2 px more under than Part 4d's narrow cards gave it,\n"
     "   so that it reads with its own hours and not with the next day's. */\n"
     "#map_sidebar .avalon-hours--card .avalon-hours__week,\n"),

    ("css: TODAY a block under its hours, 3 px over it and 4 px under",
     "  content: \"TODAY\" / \"\";\n"
     "  display: inline-block;\n"
     "  margin-left: 12px;\n"
     "  color: var(--avalon-hours-today);\n",
     "  content: \"TODAY\" / \"\";\n"
     "  display: block;\n"
     "  margin: 3px 0 4px;\n"
     "  color: var(--avalon-hours-today);\n"),

    ("css: the cards alone are size containers; the 320 px rule gone",
     "\n"
     "/* Narrow cards and bubbles. The card's grid and the bubble's are size\n"
     "   containers for these two rules alone; a browser without container\n"
     "   queries keeps the week as above, TODAY wrapping where it must.\n"
     "\n"
     "   Under 320 px of content - a phone upright, a phone held sideways, the\n"
     "   bubble on a phone - TODAY does not fit beside the hours, so it takes\n"
     "   the line under them, flush with them, rather than wrap with a 12 px\n"
     "   indent.\n"
     "\n"
     "   Under 250 px - the results column of a phone held sideways, 769 to\n"
     "   about 880 px wide, a 320 px phone upright, the bubble on one - the week\n"
     "   does not fit beside the labels either, so the opened week takes the\n"
     "   whole width under the Hours: line, its day names 15 px in, rather than\n"
     "   run into the padding or past the edge. Measured on Aura 2026-10-07: the\n"
     "   week needs 193 px, and has the content less 50 to 56 px beside the\n"
     "   labels. */\n"
     "#map_sidebar .results_wrapper .results_entry,\n"
     ".slp_info_bubble .avalon-bubble__info {\n"
     "  container-type: inline-size;\n"
     "}\n"
     "\n"
     "@container (max-width: 320px) {\n"
     "  #map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after,\n"
     "  .slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after {\n"
     "    display: block;\n"
     "    margin: 3px 0 2px;\n"
     "  }\n"
     "}\n"
     "\n"
     "@container (max-width: 250px) {\n"
     "  #map_sidebar .avalon-hours--card .avalon-hours__week,\n"
     "  .slp_info_bubble .avalon-hours--card .avalon-hours__week {\n"
     "    width: 100cqw;\n",
     "\n"
     "/* Narrow cards. The card's grid is a size container for this one rule; a\n"
     "   browser without container queries keeps the week as above.\n"
     "\n"
     "   Under 250 px of content - the results column of a phone held sideways,\n"
     "   769 to about 880 px wide, a 320 px phone upright - the week does not\n"
     "   fit beside the labels, so the opened week takes the whole width under\n"
     "   the Hours: line, its day names 15 px in, rather than run into the\n"
     "   padding or past the edge. Measured on Aura 2026-10-07, with TODAY\n"
     "   beside its hours as Part 4d had it: the week needed 193 px, and has\n"
     "   the content less 50 to 56 px beside the labels.\n"
     "\n"
     "   Part 4e. The cards alone. The bubble is as wide as its content now\n"
     "   (the theme), and what is sized by its content cannot be a size\n"
     "   container as well: contained, its lines would no longer count towards\n"
     "   the bubble's width. Nor does it need to be: the theme keeps the bubble\n"
     "   280 px wide at least, and a phone has none. Part 4d's 320 px rule went\n"
     "   when TODAY moved under its hours for good. */\n"
     "#map_sidebar .results_wrapper .results_entry {\n"
     "  container-type: inline-size;\n"
     "}\n"
     "\n"
     "@container (max-width: 250px) {\n"
     "  #map_sidebar .avalon-hours--card .avalon-hours__week {\n"
     "    width: 100cqw;\n"),

    ("css: the bubble's comment - no body that scrolls, no fade",
     "\n"
     "/* The name; the dealer's lines - the card's grid - in a body that\n"
     "   scrolls on a phone, with a fade at its foot while there is more below\n"
     "   (slp_avalon.js); then the two buttons in a row of their own under the\n"
     "   body, where they stay while it scrolls. slp_avalon wraps Avalon's fields\n"
     "   in .avalon-bubble__info and SLP's two button spans in\n"
     "   .avalon-bubble__actions (slp_js_options at 120), so SLP's own lines -\n"
     "   fax, description, its hours, image, tags, all empty on Aura - stay out\n"
     "   of the grid, and the spans keep the ids main.js and slp_avalon.js read.\n"
     "   15 px text and a 20 px name at every width; a long address takes a\n"
     "   third line 105 times of Aura's 313 at 320 px, 12 at 360, 7 at 375, 4\n"
     "   at 390 and once from 414 up, where Part 4c's icons gave 13, 10, 1 and\n"
     "   1 (2026-10-07). The frame - its colour, corners, width, shadow and\n"
     "   tail - is the theme's. */\n"
     ".slp_info_bubble #slp_bubble_name {\n",
     "\n"
     "/* The name; the dealer's lines - the card's grid - in the body; then the\n"
     "   two buttons in a row of their own under it. slp_avalon wraps Avalon's\n"
     "   fields in .avalon-bubble__info and SLP's two button spans in\n"
     "   .avalon-bubble__actions (slp_js_options at 120), so SLP's own lines -\n"
     "   fax, description, its hours, image, tags, all empty on Aura - stay out\n"
     "   of the grid, and the spans keep the ids main.js and slp_avalon.js read.\n"
     "   15 px text and a 20 px name at every width. The frame - its colour,\n"
     "   corners, width, shadow and tail - is the theme's.\n"
     "\n"
     "   Part 4e. A phone has no bubble (slp_avalon.js), so the body that\n"
     "   scrolled there, its scrollbar and the fade at its foot are gone, and\n"
     "   the count Part 4d took of long addresses in a phone's bubble no longer\n"
     "   describes anything. On a larger screen the bubble grows as it always\n"
     "   did, and Google's own box scrolls it where a map is too short. */\n"
     ".slp_info_bubble #slp_bubble_name {\n"),

    ("css: the fade and the phone's cap give way to the chosen card's flash",
     "\n"
     "/* The fade: the bubble's black over the body's last 28 px while there is\n"
     "   more below. slp_avalon.js sets .is-more; at the end of the scroll, and\n"
     "   wherever nothing scrolls, it is not drawn. */\n"
     ".slp_info_bubble .sl_popup_contact_info::after {\n"
     "  content: \"\";\n"
     "  display: block;\n"
     "  position: sticky;\n"
     "  bottom: 0;\n"
     "  height: 28px;\n"
     "  margin-top: -28px;\n"
     "  background: linear-gradient(transparent, var(--avalon-bubble-bg, #080808));\n"
     "  pointer-events: none;\n"
     "  visibility: hidden;\n"
     "}\n"
     "\n"
     ".slp_info_bubble .sl_popup_contact_info.is-more::after {\n"
     "  visibility: visible;\n"
     "}\n"
     "\n"
     "/* On a phone - upright, or sideways and short - the body is at most\n"
     "   250 px or 45% of the screen's height, and scrolls, with a thin visible\n"
     "   scrollbar; the buttons stay under it. On a larger screen the bubble\n"
     "   grows as it did. */\n"
     "@media (max-width: 767px), (max-height: 500px) {\n"
     "  .slp_info_bubble .sl_popup_contact_info {\n"
     "    max-height: min(250px, 45vh);\n"
     "    overflow-y: auto;\n"
     "    overscroll-behavior: contain;\n"
     "    scrollbar-width: thin;\n"
     "    scrollbar-color: rgba(255, 255, 255, 0.45) transparent;\n"
     "  }\n"
     "\n"
     "  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar {\n"
     "    width: 5px;\n"
     "  }\n"
     "\n"
     "  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar-thumb {\n"
     "    background: rgba(255, 255, 255, 0.45);\n"
     "    border-radius: 3px;\n"
     "  }\n"
     "\n"
     "  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar-track {\n"
     "    background: transparent;\n"
     "  }\n"
     "}\n",
     "\n"
     "/* ------------------------------------------ Part 4e: the chosen card */\n"
     "\n"
     "/* Part 4e. On a phone - upright, or sideways and short - a pin opens no\n"
     "   bubble: slp_avalon.js brings the dealer's card into view, marked\n"
     "   .active - the theme's ring - and puts .avalon-flash on it for a\n"
     "   second, so the eye finds it: the card's own background reached from a\n"
     "   lighter one, twice. The lighter one is a custom property, for a site\n"
     "   on a light background to set. Where the visitor has asked for less\n"
     "   motion, no flash: the ring alone. */\n"
     "@keyframes avalon-flash {\n"
     "  from {\n"
     "    background-color: var(--avalon-flash, rgba(255, 255, 255, 0.34));\n"
     "  }\n"
     "}\n"
     "\n"
     "#map_sidebar .results_wrapper.avalon-flash {\n"
     "  animation: avalon-flash 0.5s ease-out 2;\n"
     "}\n"
     "\n"
     "@media (prefers-reduced-motion: reduce) {\n"
     "  #map_sidebar .results_wrapper.avalon-flash {\n"
     "    animation: none;\n"
     "  }\n"
     "}\n"),

]


# ===========================================================================
# slp_avalon.js (CRLF; written LF here)
# ===========================================================================

MAP_JS_EDITS = [

    ("slp_avalon.js: avalon_map's header - no bubble on a phone, so no width to set there",
     "   *   phones  at Elementor's mobile breakpoint the bubble is at least the\n"
     "   *           map's width less 24 px, and never more than 376 px, the\n"
     "   *           theme's own width. Google reads minWidth only as a bubble\n"
     "   *           opens, so a change is close(), setOptions(), open(), as its\n"
     "   *           reference says - on the next bubble, and on the open one\n"
     "   *           0.2 s after the window stops resizing (a phone turned):\n"
     "   *           the same dealer, chosen or not as before, and focus, if it\n"
     "   *           was in the bubble, back on its Contact Dealer button. Not\n"
     "   *           while the Contact Dealer form is open over the page: once it\n"
     "   *           has closed. 767 px is Elementor's default and Aura's; Part\n"
     "   *           4's hours fold follows a site that moves it, this does not -\n"
     "   *           check before Tahoe or Avalon take Part 4c.\n",
     "   *   phones  none there at all, from Part 4e: PHONES, below. Part 4c\n"
     "   *           widened the bubble on a phone - minWidth, the map's width\n"
     "   *           less 24 px - and reopened it when the phone was turned;\n"
     "   *           both went with the bubble.\n"),

    ("slp_avalon.js: avalon_map's header - phones, the card in view, the bubble's width; the fade gone",
     "   * THE FADE (Part 4d). On a phone the bubble's body scrolls; while there\n"
     "   * is more below, avalon-hours.css fades its foot (.is-more on\n"
     "   * .sl_popup_contact_info): looked at as the bubble opens, as it scrolls,\n"
     "   * as its week opens or shuts, and after a resize.\n"
     "   *\n",
     "   * PHONES (Part 4e). On a phone - 767 px wide or less, or 500 px high or\n"
     "   * less: upright, or sideways and short - a pin opens no bubble (the\n"
     "   * owner, 2026-10-07): it covered most of the map to repeat the card\n"
     "   * beside or under it, and its week needed a scroller of its own. A pin\n"
     "   * chosen there is lit, and its dealer's card is marked, brought into\n"
     "   * view, flashed and given focus; a card chosen there is marked and its\n"
     "   * pin lit, and the map moves only when that pin is out of sight. Esc, a\n"
     "   * tap on the map, another choice or a new search ends it, as they close\n"
     "   * a bubble. A hover - a mouse on a narrow window - lights the pin and\n"
     "   * nothing more. A window narrowed to a phone's with a bubble open loses\n"
     "   * the bubble and keeps the choice; one widened with a dealer chosen\n"
     "   * gets that dealer's bubble. A map shown full screen is not a phone's,\n"
     "   * whatever the screen: no card can be seen behind it, so its pins open\n"
     "   * bubbles, and going in or out of full screen is taken as the window\n"
     "   * widening or narrowing. 767 px is Elementor's default and Aura's;\n"
     "   * Part 4's hours fold follows a site that moves it, this does not -\n"
     "   * check before Tahoe or Avalon take it.\n"
     "   *\n"
     "   * THE CARD IN VIEW (Part 4e). A dealer chosen on the map - on a phone\n"
     "   * or not, but not on its own card, which is under the pointer already -\n"
     "   * has its card brought where it can be seen. Beside the map, the\n"
     "   * results scroll until the card is at the top of what shows of them,\n"
     "   * with room made under the last cards where they could not otherwise\n"
     "   * get there. Under the map - a phone upright - the card moves to the\n"
     "   * top of the list, and back to its place when another dealer is chosen\n"
     "   * there or none is; on a phone the page then scrolls just far enough to\n"
     "   * show it, keeping the map's top in view where both fit. Which of the\n"
     "   * two it is, is read from where the list lies, not from a width. Where\n"
     "   * a bubble opens, the page itself is never scrolled.\n"
     "   *\n"
     "   * THE BUBBLE'S WIDTH (Part 4e). The bubble is as wide as its content\n"
     "   * (the theme), and a week that is shut is not content: opening it could\n"
     "   * widen the bubble under the pointer. So the week is measured open,\n"
     "   * once, as the bubble arrives, and that width kept as the bubble's\n"
     "   * least. Part 4d's fade went with the phone's bubble: nothing of ours\n"
     "   * scrolls in a bubble now.\n"
     "   *\n"),

    ("slp_avalon.js: the phone is upright or short; no widths; what a choice without a bubble needs kept",
     "    var CLOSE_MS = 300;\n"
     "    var RESIZE_MS = 200;\n"
     "    var PHONE = \"(max-width: 767px)\";\n"
     "    var WIDEST = 376;\n"
     "    var MARGIN = 24;\n"
     "\n"
     "    var st = {\n"
     "      cm: null,           //SLP's map object, cslmap\n"
     "      byId: {},           //location id -> { id, marker, info, lit }\n"
     "      current: null,      //the entry whose bubble is open\n"
     "      open: false,\n"
     "      pinned: false,      //chosen, by a click, a tap or a key\n"
     "      next: null,         //\"hover\" for the next show() only\n"
     "      timer: 0,           //the pending close\n"
     "      rs: 0,              //the pending look at the width, after a resize\n"
     "      overMarker: null,   //the location id of the pin under the pointer\n"
     "      overCard: null,     //the location id of the card under the pointer\n"
     "      overBubble: false,\n"
     "      minWidth: 0,        //the minWidth the InfoWindow was last given\n"
     "      quiet: false,       //closing only to reopen at another width\n",
     "    var CLOSE_MS = 300;\n"
     "    var RESIZE_MS = 200;\n"
     "    var FLASH_MS = 1100;\n"
     "    var GAP = 8;\n"
     "    var PHONE = \"(max-width: 767px), (max-height: 500px)\";\n"
     "\n"
     "    var st = {\n"
     "      cm: null,           //SLP's map object, cslmap\n"
     "      byId: {},           //location id -> { id, marker, info, lit }\n"
     "      current: null,      //the entry whose bubble is open; on a phone, the dealer chosen\n"
     "      open: false,        //a bubble is open\n"
     "      pinned: false,      //chosen, by a click, a tap or a key\n"
     "      next: null,         //\"hover\" for the next show() only\n"
     "      timer: 0,           //the pending close\n"
     "      rs: 0,              //the pending look at the window, after a resize\n"
     "      overMarker: null,   //the location id of the pin under the pointer\n"
     "      overCard: null,     //the location id of the card under the pointer\n"
     "      overBubble: false,\n"
     "      via: \"\",            //\"card\" while a click on a result card is under way\n"
     "      moved: null,        //{ el, next }: the card moved to the top of a list under the map\n"
     "      pad: null,          //{ el, card }: the results padded so that card can reach their top\n"
     "      fl: 0,              //the end of the flash\n"
     "      sent: null,         //what to_card() gave focus to\n"
     "      quiet: false,       //closing a bubble the visitor did not ask to close\n"),

    ("slp_avalon.js: more() gives way to the phone, the card in view, the flash, focus on the card",
     "    //Part 4d. The fade at the foot of the bubble's body while there is\n"
     "    //more below it; none at the end, none where nothing scrolls.\n"
     "    function more() {\n"
     "      var b = bubble();\n"
     "      var s = b ? b.querySelector(\".sl_popup_contact_info\") : null;\n"
     "      if (s) {\n"
     "        cls(s, \"is-more\", s.scrollHeight - s.scrollTop - s.clientHeight > 1);\n"
     "      }\n"
     "    }\n"
     "\n",
     "    //Part 4e. A phone: upright, or sideways and short. No bubble there -\n"
     "    //but for a map shown full screen, where no card can be seen.\n"
     "    function phone() {\n"
     "      var d = document;\n"
     "      return !(d.fullscreenElement || d.webkitFullscreenElement) &&\n"
     "             !!(window.matchMedia && window.matchMedia(PHONE).matches);\n"
     "    }\n"
     "\n"
     "    //Part 4e. A dealer's card in the results, or null.\n"
     "    function card_of(e) {\n"
     "      return e ? document.getElementById(\"slp_results_wrapper_\" + e.id) : null;\n"
     "    }\n"
     "\n"
     "    //Part 4e. On a result card.\n"
     "    function on_card(el) {\n"
     "      for (var n = el; n && n.nodeType === 1; n = n.parentNode) {\n"
     "        if (has_class(n, \"results_wrapper\")) {\n"
     "          return true;\n"
     "        }\n"
     "      }\n"
     "      return false;\n"
     "    }\n"
     "\n"
     "    //Part 4e. What scrolls a card: the nearest box above it that scrolls up\n"
     "    //and down - the theme's results box on Aura - or null where only the\n"
     "    //page does.\n"
     "    function scroller(c) {\n"
     "      if (typeof window.getComputedStyle !== \"function\") {\n"
     "        return null;\n"
     "      }\n"
     "      for (var n = c.parentNode; n && n.nodeType === 1 && n !== document.body && n !== document.documentElement; n = n.parentNode) {\n"
     "        var o = window.getComputedStyle(n).overflowY;\n"
     "        if (o === \"auto\" || o === \"scroll\") {\n"
     "          return n;\n"
     "        }\n"
     "      }\n"
     "      return null;\n"
     "    }\n"
     "\n"
     "    //Part 4e. Whether the results lie under the map - a phone upright - and\n"
     "    //not beside it: read from the page, so that the theme's own breakpoint\n"
     "    //decides. Their box, not the list in it, which moves as it scrolls.\n"
     "    function stacked(box) {\n"
     "      var g = st.cm && st.cm.gmap;\n"
     "      var m = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n"
     "      if (!m || !box || !m.getBoundingClientRect || !box.getBoundingClientRect) {\n"
     "        return false;\n"
     "      }\n"
     "      return box.getBoundingClientRect().top >= m.getBoundingClientRect().bottom - 1;\n"
     "    }\n"
     "\n"
     "    //Part 4e. How much of the window's top a fixed or sticky header covers:\n"
     "    //nothing on Aura, whose header scrolls away; an admin bar's 32 px.\n"
     "    function inset() {\n"
     "      var w = window.innerWidth || 0;\n"
     "      var h = window.innerHeight || 0;\n"
     "      if (typeof document.elementFromPoint !== \"function\" || typeof window.getComputedStyle !== \"function\") {\n"
     "        return 0;\n"
     "      }\n"
     "      for (var n = document.elementFromPoint(Math.floor(w / 2), 1); n && n.nodeType === 1 && n !== document.body && n !== document.documentElement; n = n.parentNode) {\n"
     "        var p = window.getComputedStyle(n).position;\n"
     "        if (p === \"fixed\" || p === \"sticky\") {\n"
     "          var b = n.getBoundingClientRect().bottom;\n"
     "          return b > 0 && b < h / 2 ? b : 0;\n"
     "        }\n"
     "      }\n"
     "      return 0;\n"
     "    }\n"
     "\n"
     "    //Part 4e. A box, or the page, scrolled down by dy px - up, when dy is\n"
     "    //less than nothing - smoothly, unless the visitor has asked for less\n"
     "    //motion: then at once, whatever the page's own scroll-behavior says\n"
     "    //(Aura's is smooth).\n"
     "    function scroll_by(el, dy) {\n"
     "      var calm = !!(window.matchMedia && window.matchMedia(\"(prefers-reduced-motion: reduce)\").matches);\n"
     "      if (!dy) {\n"
     "        return;\n"
     "      }\n"
     "      try {\n"
     "        (el || window).scrollBy({ top: dy, left: 0, behavior: calm ? \"instant\" : \"smooth\" });\n"
     "      } catch (x) {\n"
     "        //A browser that takes no options here, or not these: at once.\n"
     "        if (el) {\n"
     "          el.scrollTop += dy;\n"
     "        } else {\n"
     "          window.scrollBy(0, dy);\n"
     "        }\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. The room made under the results taken away again.\n"
     "    function unpad() {\n"
     "      var p = st.pad;\n"
     "      st.pad = null;\n"
     "      if (p && p.el && p.el.style) {\n"
     "        p.el.style.paddingBottom = \"\";\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. A card moved within its list, before another or to the end.\n"
     "    //Moving takes focus from whatever in the card had it: given back.\n"
     "    function move(c, p, before) {\n"
     "      var a = document.activeElement;\n"
     "      var had = !!a && a !== document.body && typeof c.contains === \"function\" && c.contains(a);\n"
     "      if (before) {\n"
     "        p.insertBefore(c, before);\n"
     "      } else {\n"
     "        p.appendChild(c);\n"
     "      }\n"
     "      if (had && document.activeElement !== a) {\n"
     "        try {\n"
     "          a.focus({ preventScroll: true });\n"
     "        } catch (x) {\n"
     "          //Refused: focus stays where moving the card left it.\n"
     "        }\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. A card moved to the top of its list put back: before the\n"
     "    //card it stood before, or last. A list SLP has since drawn again has\n"
     "    //nothing to put back.\n"
     "    function put_back() {\n"
     "      var m = st.moved;\n"
     "      st.moved = null;\n"
     "      var p = m && m.el ? m.el.parentNode : null;\n"
     "      if (!p) {\n"
     "        return;\n"
     "      }\n"
     "      if (m.next && m.next.parentNode === p) {\n"
     "        move(m.el, p, m.next);\n"
     "      } else if (!m.next) {\n"
     "        move(m.el, p, null);\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. What place() did for another dealer's card undone; for this\n"
     "    //one, left as it is.\n"
     "    function unplace(c) {\n"
     "      if (st.moved && st.moved.el !== c) {\n"
     "        put_back();\n"
     "      }\n"
     "      if (st.pad && st.pad.card !== c) {\n"
     "        unpad();\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. Beside the map: the results scrolled until the card is at the\n"
     "    //top of what shows of them - their box may reach past the window's\n"
     "    //foot, or start above its top. A card already in full view is left\n"
     "    //where it is. Near the end of the list the box cannot scroll that far:\n"
     "    //room is made under the last card for as long as this one is chosen.\n"
     "    //On a phone (far) the page scrolls as well, just far enough to show\n"
     "    //the card whole and never taking the box's top out of the window: a\n"
     "    //phone held sideways shows less than one card's height of a box that\n"
     "    //starts under the search form. Results with no box of their own\n"
     "    //scroll with the page, and only there. Where a bubble opens the page\n"
     "    //is left alone.\n"
     "    function box_to(c, s, far) {\n"
     "      var r = c.getBoundingClientRect();\n"
     "      var top = inset();\n"
     "      var foot = window.innerHeight || document.documentElement.clientHeight || 0;\n"
     "      var lo = top;\n"
     "      var hi = foot;\n"
     "      var b = s ? s.getBoundingClientRect() : null;\n"
     "      var edge = lo + GAP;\n"
     "      if (b) {\n"
     "        //The box's own top, where that shows - or nothing of the box does.\n"
     "        edge = b.top >= lo || b.bottom <= lo || b.top >= hi ? b.top : edge;\n"
     "        lo = Math.max(lo, b.top);\n"
     "        hi = Math.min(hi, b.bottom);\n"
     "      }\n"
     "      if (r.top >= lo - 1 && r.bottom <= hi + 1) {\n"
     "        return;\n"
     "      }\n"
     "      var dy = Math.round(r.top - edge);\n"
     "      if (!s) {\n"
     "        if (far) {\n"
     "          scroll_by(null, dy);\n"
     "        }\n"
     "        return;\n"
     "      }\n"
     "      var max = s.scrollHeight - s.clientHeight - s.scrollTop;\n"
     "      var side = document.getElementById(\"map_sidebar\");\n"
     "      if (dy > max && side && side.style && typeof window.getComputedStyle === \"function\") {\n"
     "        side.style.paddingBottom = Math.ceil((parseFloat(window.getComputedStyle(side).paddingBottom) || 0) + dy - max) + \"px\";\n"
     "        st.pad = { el: side, card: c };\n"
     "      }\n"
     "      scroll_by(s, dy);\n"
     "      if (far) {\n"
     "        scroll_by(null, Math.max(0, Math.round(Math.min(edge + r.bottom - r.top - (foot - GAP), b.top - top - GAP))));\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. Under the map, on a phone: the page scrolled just far enough\n"
     "    //to show the card - whole, where that leaves the map's top in view;\n"
     "    //otherwise the best part of it, 45% of the window or 140 px, and the\n"
     "    //map's top let go. Never scrolled back up.\n"
     "    function page_to(c) {\n"
     "      var g = st.cm && st.cm.gmap;\n"
     "      var m = g && typeof g.getDiv === \"function\" ? g.getDiv() : null;\n"
     "      var vh = window.innerHeight || document.documentElement.clientHeight || 0;\n"
     "      if (!m || !m.getBoundingClientRect || !vh) {\n"
     "        return;\n"
     "      }\n"
     "      var r = c.getBoundingClientRect();\n"
     "      var all = r.bottom - (vh - GAP);\n"
     "      var some = r.top + Math.min(r.bottom - r.top, Math.max(140, vh * 0.45)) - (vh - GAP);\n"
     "      var room = m.getBoundingClientRect().top - inset() - GAP;\n"
     "      var dy = Math.round(Math.max(some, Math.min(all, room)));\n"
     "      if (dy > 0) {\n"
     "        scroll_by(null, dy);\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. THE CARD IN VIEW, in the header above. far: on a phone,\n"
     "    //where the page may scroll as well.\n"
     "    function place(e, far) {\n"
     "      var c = card_of(e);\n"
     "      var p = c ? c.parentNode : null;\n"
     "      unplace(c);\n"
     "      if (!c || !p || !c.getBoundingClientRect) {\n"
     "        return;\n"
     "      }\n"
     "      var s = scroller(c);\n"
     "      if (!stacked(s || p)) {\n"
     "        if (st.moved) {\n"
     "          put_back();\n"
     "        }\n"
     "        box_to(c, s, far);\n"
     "        return;\n"
     "      }\n"
     "      unpad();\n"
     "      var first = typeof p.querySelector === \"function\" ? p.querySelector(\".results_wrapper\") : null;\n"
     "      if (first && first !== c) {\n"
     "        st.moved = { el: c, next: c.nextSibling };\n"
     "        move(c, p, first);\n"
     "      }\n"
     "      if (s) {\n"
     "        s.scrollTop = 0;\n"
     "      }\n"
     "      if (far) {\n"
     "        page_to(c);\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. The card flashed, so that the eye finds it: avalon-hours.css\n"
     "    //draws .avalon-flash, which comes off again when it is done - or at\n"
     "    //once, with no dealer, when the choice ends first.\n"
     "    function flash(e) {\n"
     "      var c = card_of(e);\n"
     "      var on = document.querySelectorAll(\"#map_sidebar .results_wrapper.avalon-flash\");\n"
     "      for (var i = 0; i < on.length; i++) {\n"
     "        cls(on[i], \"avalon-flash\", false);\n"
     "      }\n"
     "      clearTimeout(st.fl);\n"
     "      st.fl = 0;\n"
     "      if (!c) {\n"
     "        return;\n"
     "      }\n"
     "      //Read, so that a card flashed a moment ago starts over.\n"
     "      void c.offsetWidth;\n"
     "      cls(c, \"avalon-flash\", true);\n"
     "      st.fl = setTimeout(function () {\n"
     "        st.fl = 0;\n"
     "        cls(c, \"avalon-flash\", false);\n"
     "      }, FLASH_MS);\n"
     "    }\n"
     "\n"
     "    //Part 4e. A dealer chosen on its card, on a phone: no bubble will bring\n"
     "    //the map round to its pin, so the map goes there when the pin is out\n"
     "    //of sight - and stays where it is when it is not.\n"
     "    function seen(e) {\n"
     "      var g = e.marker && e.marker.__gmarker;\n"
     "      var m = st.cm && st.cm.gmap;\n"
     "      try {\n"
     "        var at = g.getPosition();\n"
     "        var b = m.getBounds();\n"
     "        if (at && b && !b.contains(at)) {\n"
     "          m.panTo(at);\n"
     "        }\n"
     "      } catch (x) {\n"
     "        //A map that cannot say where it is stays where it is.\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. Focus to the chosen dealer's card - its name, where that is a\n"
     "    //link, else the card itself - as it would have gone into a bubble:\n"
     "    //without scrolling the page, and remembering where it came from. Not\n"
     "    //while the Contact Dealer form is open.\n"
     "    function to_card(e) {\n"
     "      var c = card_of(e);\n"
     "      var a = document.activeElement;\n"
     "      if (!c || document.querySelector(\".contact-dealer--pop-up.open-modal\")) {\n"
     "        return;\n"
     "      }\n"
     "      var t = (typeof c.querySelector === \"function\" && c.querySelector(\".store_locator_name a\")) || c;\n"
     "      if (t === c && typeof c.setAttribute === \"function\") {\n"
     "        c.setAttribute(\"tabindex\", \"-1\");\n"
     "      }\n"
     "      if (a && a !== document.body && !inside(a) && !on_card(a)) {\n"
     "        st.back = a;\n"
     "      }\n"
     "      st.sent = t;\n"
     "      try {\n"
     "        t.focus({ preventScroll: true });\n"
     "      } catch (x) {\n"
     "        //A card that refuses focus leaves focus where it was.\n"
     "      }\n"
     "    }\n"
     "\n"
     "    //Part 4e. PHONES, in the header above: a dealer chosen, and no bubble.\n"
     "    //card: chosen on its own card, which is left where the finger found it.\n"
     "    function choose(e, card) {\n"
     "      var prev = st.current;\n"
     "      if (st.open && st.cm) {\n"
     "        reclose(st.cm.infowindow);\n"
     "      }\n"
     "      st.current = e;\n"
     "      st.open = false;\n"
     "      st.pinned = true;\n"
     "      st.overBubble = false;\n"
     "      st.wantFocus = false;\n"
     "      st.from = null;\n"
     "      if (prev && prev !== e) {\n"
     "        lit(prev, hovered(prev));\n"
     "      }\n"
     "      lit(e, true);\n"
     "      ring(e);\n"
     "      if (card) {\n"
     "        seen(e);\n"
     "        return;\n"
     "      }\n"
     "      place(e, true);\n"
     "      flash(e);\n"
     "      to_card(e);\n"
     "    }\n"
     "\n"
     "    //Part 4e. THE BUBBLE'S WIDTH, in the header above: the week measured\n"
     "    //open, once for each bubble's content, and that width kept as its least.\n"
     "    function hold() {\n"
     "      var b = bubble();\n"
     "      var d = b && typeof b.querySelector === \"function\" ? b.querySelector(\".avalon-hours__narrow\") : null;\n"
     "      if (!b || b.avalonHeld || !d || d.open || !b.style || !b.getBoundingClientRect) {\n"
     "        return;\n"
     "      }\n"
     "      b.avalonHeld = true;\n"
     "      var w = 0;\n"
     "      try {\n"
     "        d.open = true;\n"
     "        w = b.getBoundingClientRect().width;\n"
     "      } catch (x) {\n"
     "        //Not measured: the bubble keeps the width of its content.\n"
     "      }\n"
     "      d.open = false;\n"
     "      if (w > 0) {\n"
     "        b.style.minWidth = Math.ceil(w) + \"px\";\n"
     "      }\n"
     "    }\n"
     "\n"),

    ("slp_avalon.js: min_width() taken out",
     "      }, CLOSE_MS);\n"
     "    }\n"
     "\n"
     "    function min_width(cm) {\n"
     "      if (!window.matchMedia || !window.matchMedia(PHONE).matches) {\n"
     "        return 0;\n"
     "      }\n"
     "      var div = cm.gmap && typeof cm.gmap.getDiv === \"function\" ? cm.gmap.getDiv() : null;\n"
     "      var w = div ? div.clientWidth : 0;\n"
     "      return w > MARGIN ? Math.min(WIDEST, w - MARGIN) : 0;\n"
     "    }\n"
     "\n"
     "    //The dealer's name as text: SLP's marker carries it esc_attr()'d.\n",
     "      }, CLOSE_MS);\n"
     "    }\n"
     "\n"
     "    //The dealer's name as text: SLP's marker carries it esc_attr()'d.\n"),

    ("slp_avalon.js: restore() also from the card of a dealer no longer chosen",
     "    //Focus back where it came from - but only when it was lost with the\n"
     "    //bubble, never taken from wherever the visitor has put it since.\n"
     "    function restore() {\n"
     "      var back = st.back;\n"
     "      st.back = null;\n"
     "      var a = document.activeElement;\n"
     "      if (!back || (a && a !== document.body && !inside(a))) {\n"
     "        return;\n"
     "      }\n",
     "    //Focus back where it came from - but only when it was lost with the\n"
     "    //bubble, or is still where to_card() put it, on the card of a dealer\n"
     "    //no longer chosen (held, Part 4e): never taken from wherever the\n"
     "    //visitor has put it since.\n"
     "    function restore(held) {\n"
     "      var back = st.back;\n"
     "      st.back = null;\n"
     "      var a = document.activeElement;\n"
     "      if (!back || (a && a !== document.body && !inside(a) && !held)) {\n"
     "        return;\n"
     "      }\n"),

    ("slp_avalon.js: clear() - whether focus is still where to_card() put it",
     "    function clear() {\n"
     "      var e = st.current;\n"
     "      cancel();\n",
     "    function clear() {\n"
     "      var e = st.current;\n"
     "      var held = !!st.sent && st.sent === document.activeElement;\n"
     "      st.sent = null;\n"
     "      cancel();\n"),

    ("slp_avalon.js: clear() - the flash, the card's place and the focus it held",
     "      ring(null);\n"
     "      restore();\n",
     "      ring(null);\n"
     "      flash(null);\n"
     "      unplace(null);\n"
     "      restore(held);\n"),

    ("slp_avalon.js: closed() - a close that arrives once the bubble is down is nobody's",
     "    //Google's close event: Esc inside the bubble, its anchor removed, or\n"
     "    //close() above. Not the close that only reopens it at another width.\n"
     "    function closed() {\n"
     "      var iw = st.cm && st.cm.infowindow;\n"
     "      if (st.quiet || (iw && iw.isOpen === true)) {\n",
     "    //Google's close event: Esc inside the bubble, its anchor removed, or\n"
     "    //close() above. Not a close of ours that keeps the dealer chosen, nor\n"
     "    //(Part 4e) one that arrives when no bubble is up any more: Google may\n"
     "    //send it after the fact.\n"
     "    function closed() {\n"
     "      var iw = st.cm && st.cm.infowindow;\n"
     "      if (st.quiet || !st.open || (iw && iw.isOpen === true)) {\n"),

    ("slp_avalon.js: reclose() - what it is for now",
     "    //Google's close() before the bubble reopens at another width: not the\n"
     "    //visitor's, so closed() lets it pass. Google sends focus back to where\n"
     "    //it was before the bubble opened (its guide, \"Close an info window\"),\n"
     "    //and focusing can scroll the page. Focus that was in the bubble, or on\n"
     "    //nothing, is let go - for focus_in() to put in the bubble reopened, or\n"
     "    //to stay on nothing; focus that was elsewhere - the search box, say -\n"
     "    //is put back there; the page is put back where it was. Says whether\n"
     "    //focus was in the bubble.\n",
     "    //Google's close() when a window has narrowed to a phone's with a bubble\n"
     "    //open (Part 4e; before it, when a bubble reopened at another width):\n"
     "    //not the visitor's, so closed() lets it pass. Google sends focus back\n"
     "    //to where it was before the bubble opened (its guide, \"Close an info\n"
     "    //window\"), and focusing can scroll the page. Focus that was in the\n"
     "    //bubble, or on nothing, is let go - for to_card() to put on the\n"
     "    //dealer's card, or to stay on nothing; focus that was elsewhere - the\n"
     "    //search box, say - is put back there; the page is put back where it\n"
     "    //was. Says whether focus was in the bubble.\n"),

    ("slp_avalon.js: reclose() - the page put back at once, whatever its scroll-behavior",
     "        if (window.pageXOffset !== sx || window.pageYOffset !== sy) {\n"
     "          window.scrollTo(sx, sy);\n"
     "        }\n",
     "        //Part 4e. At once, and whether or not the page has moved yet:\n"
     "        //where the page's own scroll-behavior is smooth - Aura's is - the\n"
     "        //scroll a focus sets off only starts later, and this stops it.\n"
     "        try {\n"
     "          window.scrollTo({ left: sx, top: sy, behavior: \"instant\" });\n"
     "        } catch (y) {\n"
     "          if (window.pageXOffset !== sx || window.pageYOffset !== sy) {\n"
     "            window.scrollTo(sx, sy);\n"
     "          }\n"
     "        }\n"),

    ("slp_avalon.js: show() - on a phone the choice goes to the card; elsewhere no width to set, and the card comes into view",
     "      var e = entry(info, marker);\n"
     "      var iw = cm.infowindow;\n"
     "      cancel();\n"
     "      if (!hover) {\n"
     "        st.from = document.activeElement;\n"
     "      }\n"
     "      if (!(st.open && st.current === e)) {\n"
     "        var prev = st.current;\n"
     "        var width = min_width(cm);\n"
     "        var opts = { ariaLabel: name_of(info) };\n"
     "        if (width !== st.minWidth) {\n"
     "          if (st.open) {\n"
     "            reclose(iw);\n"
     "          }\n"
     "          opts.minWidth = width;\n"
     "          st.minWidth = width;\n"
     "        }\n"
     "        iw.setOptions(opts);\n"
     "        iw.setContent(cm.createMarkerContent(info));\n",
     "      var e = entry(info, marker);\n"
     "      var iw = cm.infowindow;\n"
     "      var card = st.via === \"card\";\n"
     "      cancel();\n"
     "      //Part 4e. A phone has no bubble: a choice goes to the card, and a\n"
     "      //hover - enter() has lit the pin - is nothing more.\n"
     "      if (phone()) {\n"
     "        if (!hover) {\n"
     "          choose(e, card);\n"
     "        }\n"
     "        return;\n"
     "      }\n"
     "      if (!hover) {\n"
     "        st.from = document.activeElement;\n"
     "      }\n"
     "      if (!(st.open && st.current === e)) {\n"
     "        var prev = st.current;\n"
     "        iw.setOptions({ ariaLabel: name_of(info) });\n"
     "        iw.setContent(cm.createMarkerContent(info));\n"),

    ("slp_avalon.js: show() - a dealer chosen on the map has its card brought into view",
     "      if (!hover) {\n"
     "        st.pinned = true;\n"
     "        ring(e);\n"
     "      }\n"
     "    }\n",
     "      if (!hover) {\n"
     "        st.pinned = true;\n"
     "        ring(e);\n"
     "        if (!card) {\n"
     "          place(e, false);\n"
     "        }\n"
     "      }\n"
     "    }\n"),

    ("slp_avalon.js: resized() - the bubble goes on a phone, the choice stays; no width to reopen at",
     "    //The window has stopped resizing - a phone turned, say. An open bubble\n"
     "    //whose minWidth no longer fits the map is reopened at the new one, as\n"
     "    //show() does: the same dealer, chosen or not as before. Focus that was\n"
     "    //in it - lost as Google takes the bubble out - goes back to its Contact\n"
     "    //Dealer once ready() has it in the page again. A map not laid out\n"
     "    //(0 px wide) is left alone. Under the Contact Dealer form it waits:\n"
     "    //dealer-popup-focus.js gives focus back, as the form closes, to the\n"
     "    //link that opened it, which a reopen would take out of the page - and\n"
     "    //it falls back to the search box. So it looks again until the form\n"
     "    //has closed.\n"
     "    function resized() {\n"
     "      st.rs = 0;\n"
     "      more();\n"
     "      var cm = st.cm;\n"
     "      var e = st.current;\n"
     "      if (!cm || !st.open || !e || !e.marker || !e.marker.__gmarker) {\n"
     "        return;\n"
     "      }\n"
     "      var div = cm.gmap && typeof cm.gmap.getDiv === \"function\" ? cm.gmap.getDiv() : null;\n"
     "      if (!div || !div.clientWidth) {\n"
     "        return;\n"
     "      }\n"
     "      var width = min_width(cm);\n"
     "      if (width === st.minWidth) {\n"
     "        return;\n"
     "      }\n"
     "      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {\n"
     "        st.rs = setTimeout(resized, RESIZE_MS);\n"
     "        return;\n"
     "      }\n"
     "      var iw = cm.infowindow;\n"
     "      if (reclose(iw)) {\n"
     "        st.wantFocus = true;\n"
     "      }\n"
     "      iw.setOptions({ minWidth: width });\n"
     "      st.minWidth = width;\n"
     "      iw.open({ map: cm.gmap, anchor: e.marker.__gmarker, shouldFocus: false });\n"
     "    }\n",
     "    //The window has stopped resizing - a phone turned, a window dragged\n"
     "    //narrower. Part 4e, where Part 4c reopened the bubble at another width:\n"
     "    //\n"
     "    //  a phone's now, a bubble open   the bubble goes. A dealer that was\n"
     "    //                                 chosen stays chosen, on its card,\n"
     "    //                                 which takes the focus the bubble\n"
     "    //                                 had; one only hovered is let go.\n"
     "    //  a phone's, a dealer chosen     its card where the layout now shows\n"
     "    //                                 it: the list is beside the map one\n"
     "    //                                 way up and under it the other.\n"
     "    //  wider now, a dealer chosen     that dealer's bubble, as a click\n"
     "    //    and no bubble                on its pin there would have opened\n"
     "    //                                 it - but no focus taken: nobody\n"
     "    //                                 chose anything just now.\n"
     "    //\n"
     "    //A map not laid out (0 px wide) is left alone. Under the Contact Dealer\n"
     "    //form it waits: dealer-popup-focus.js gives focus back, as the form\n"
     "    //closes, to the link that opened it, which taking the bubble out of\n"
     "    //the page would lose - and it falls back to the search box. So it\n"
     "    //looks again until the form has closed.\n"
     "    function resized() {\n"
     "      st.rs = 0;\n"
     "      var cm = st.cm;\n"
     "      var e = st.current;\n"
     "      if (!cm || !e || !e.marker || !e.marker.__gmarker) {\n"
     "        return;\n"
     "      }\n"
     "      var div = cm.gmap && typeof cm.gmap.getDiv === \"function\" ? cm.gmap.getDiv() : null;\n"
     "      if (!div || !div.clientWidth) {\n"
     "        return;\n"
     "      }\n"
     "      var small = phone();\n"
     "      if (small ? !st.open && !st.pinned : st.open || !st.pinned) {\n"
     "        return;\n"
     "      }\n"
     "      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {\n"
     "        st.rs = setTimeout(resized, RESIZE_MS);\n"
     "        return;\n"
     "      }\n"
     "      if (!small) {\n"
     "        show(e.info, e.marker);\n"
     "        st.wantFocus = false;\n"
     "        return;\n"
     "      }\n"
     "      if (!st.open) {\n"
     "        place(e, true);\n"
     "        return;\n"
     "      }\n"
     "      var chosen = st.pinned;\n"
     "      var had = reclose(cm.infowindow);\n"
     "      st.open = false;\n"
     "      st.overBubble = false;\n"
     "      st.wantFocus = false;\n"
     "      if (!chosen) {\n"
     "        clear();\n"
     "        return;\n"
     "      }\n"
     "      place(e, true);\n"
     "      if (had) {\n"
     "        to_card(e);\n"
     "      }\n"
     "    }\n"),

    ("slp_avalon.js: ready() - the bubble's width held; no fade to wire",
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
     "    function enter(e, kind) {\n",
     "      hold();\n"
     "      if (st.wantFocus) {\n"
     "        soon();\n"
     "      }\n"
     "    }\n"
     "\n"
     "    function enter(e, kind) {\n"),

    ("slp_avalon.js: leave() - a dealer chosen on a phone keeps its pin lit",
     "      if (st.open && st.current === e) {\n"
     "        later();\n"
     "      } else {\n"
     "        lit(e, hovered(e));\n"
     "      }\n"
     "    }\n",
     "      if (st.open && st.current === e) {\n"
     "        later();\n"
     "      } else {\n"
     "        //Part 4e. A dealer chosen on a phone has no bubble to keep its\n"
     "        //pin lit: the choice does.\n"
     "        lit(e, hovered(e) || (st.pinned && st.current === e));\n"
     "      }\n"
     "    }\n"),

    ("slp_avalon.js: Esc lets a dealer chosen on a phone go",
     "    //Esc closes the bubble - unless the Contact Dealer form is open over\n"
     "    //the page, whose own Esc (dealer-popup-focus.js) comes first.\n"
     "    function key(ev) {\n"
     "      if ((ev.key !== \"Escape\" && ev.key !== \"Esc\" && ev.keyCode !== 27) || !st.open || ev.defaultPrevented ||\n",
     "    //Esc closes the bubble - on a phone (Part 4e), lets the chosen dealer\n"
     "    //go - unless the Contact Dealer form is open over the page, whose own\n"
     "    //Esc (dealer-popup-focus.js) comes first.\n"
     "    function key(ev) {\n"
     "      if ((ev.key !== \"Escape\" && ev.key !== \"Esc\" && ev.keyCode !== 27) || !(st.open || st.pinned) || ev.defaultPrevented ||\n"),

    ("slp_avalon.js: attach() - a click that starts on a result card is known for one; full screen is a resize",
     "      document.addEventListener(\"keydown\", key, false);\n",
     "      document.addEventListener(\"keydown\", key, false);\n"
     "      //Part 4e. A click that starts on a result card is known for one\n"
     "      //before SLP's handler on the card reaches show() - the capture\n"
     "      //phase - and forgotten once the click is done.\n"
     "      document.addEventListener(\"click\", function (ev) {\n"
     "        if (!on_card(ev && ev.target)) {\n"
     "          return;\n"
     "        }\n"
     "        st.via = \"card\";\n"
     "        setTimeout(function () {\n"
     "          st.via = \"\";\n"
     "        }, 0);\n"
     "      }, true);\n"
     "      //Part 4e. Full screen coming or going changes what phone() says\n"
     "      //without the window always saying it has resized.\n"
     "      var full = function () {\n"
     "        clearTimeout(st.rs);\n"
     "        st.rs = setTimeout(resized, RESIZE_MS);\n"
     "      };\n"
     "      document.addEventListener(\"fullscreenchange\", full, false);\n"
     "      document.addEventListener(\"webkitfullscreenchange\", full, false);\n"),

    ("slp_avalon.js: the block no longer exports more()",
     "      ring: ring,\n"
     "      more: more,\n"
     "      state: st\n",
     "      ring: ring,\n"
     "      state: st\n"),

]


# ===========================================================================
# The builds
# ===========================================================================

def build_hours_js(js):
    for label, old, new in HOURS_JS_EDITS:
        js = sub_once(js, old, new, label)
    check('\r' not in js and all(ord(c) < 128 for c in js), 'avalon-hours.js: LF and ASCII')
    check(js.count('n.tagName === "SUMMARY" || n.tagName === "A"') == 1,
          'avalon-hours.js: keep() names the summary and a link, once')
    check(js.count('e.stopPropagation();') == 2 and js.count('function keep(e) {\n    e.stopPropagation();') == 0,
          'avalon-hours.js: two stopPropagation() calls - keep()\'s, on a summary or a link, and the label\'s - and none unconditional in keep()')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')'), 'avalon-hours.js: braces and parentheses balance')
    return js


def build_css(css):
    for label, old, new in CSS_EDITS:
        css = sub_once(css, old, new, label)
    b = bare_css(css)
    check('\r' not in css and all(ord(c) < 128 for c in css), 'css: LF and ASCII')
    check(b.count('{') == b.count('}'), 'css: braces balance')
    check('!important' not in b, 'css: no !important in any rule')
    check(b.count('@container') == 1 and b.count('container-type: inline-size;') == 1
          and '.slp_info_bubble .avalon-bubble__info {\n  container-type' not in b,
          'css: one container query and one container declaration - the cards\'')
    check('is-more' not in css and 'max-height: min(250px, 45vh)' not in css and 'position: sticky' not in b,
          'css: no fade and no cap on the bubble\'s body left')
    check(b.count('  content: "TODAY" / "";\n  display: block;\n  margin: 3px 0 4px;\n') == 1 and 'margin-left: 12px' not in b,
          'css: TODAY a block under its hours, once, and nowhere beside them')
    check('@media (max-width: 374px)' not in b, 'css: the 14 px email rule gone')
    check(b.count('@keyframes avalon-flash') == 1 and b.count('animation: avalon-flash 0.5s ease-out 2;') == 1
          and b.count('animation: none;') == 1,
          'css: the flash - one keyframes rule, used once, stilled once under reduced motion')
    return css


def build_map_js(js):
    for label, old, new in MAP_JS_EDITS:
        js = sub_once(js, crlf(old), crlf(new), label)
    check(js.count("\n") == js.count("\r\n") and js.count("\r") == js.count("\r\n"), 'slp_avalon.js: pure CRLF')
    check(all(ord(c) < 128 for c in js), 'slp_avalon.js: ASCII')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')') and js.count('[') == js.count(']'),
          'slp_avalon.js: braces, parentheses and brackets balance')
    blk = js.split('var avalon_map = (function () {')[1].split('  })();')[0]
    for banned in ('innerHTML', 'eval(', 'new Function', '.html(', 'setInterval'):
        check(banned not in blk, 'slp_avalon.js: the block never uses ' + banned)
    for gone in ('min_width', 'more(', 'MARGIN', 'WIDEST', 'is-more', 'avalonMore', 'minWidth: '):
        check(gone not in blk, 'slp_avalon.js: no ' + gone.strip() + ' left in the block')
    check(blk.count('var PHONE = "(max-width: 767px), (max-height: 500px)";') == 1,
          'slp_avalon.js: one phone query - upright, or sideways and short')
    check(blk.count('iw.setOptions({ ariaLabel: name_of(info) });') == 1 and blk.count('b.style.minWidth = ') == 1,
          'slp_avalon.js: Google is given no width; the bubble\'s own least width is set in one place')
    check(js.count("'City, State, or ZIP'") == 1, 'slp_avalon.js: Part 4d\'s placeholder untouched')
    return js


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part4e.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part4e  slp_avalon 0.0.27  PART 4e (the owner's review of Part 4d on DEV)")
    print("")

    texts = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                     "       src_dir must hold Part 4d's stylesheet, avalon-hours.js and slp_avalon.js."
                     .format(name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        texts[name] = text
    cls = os.path.join(src_dir, 'class.slp_avalon.php')
    if os.path.isfile(cls):
        _, got_md5, got_size = read_exact(cls)
        if (got_md5, got_size) != CLASS_PIN:
            sys.exit("ABORT class.slp_avalon.php in src_dir is {} / {} bytes: Part 4e is built on Part 4d's class, {} / {} bytes"
                     .format(got_md5, got_size, CLASS_PIN[0], CLASS_PIN[1]))
        print("  input OK  {:<24} {} {} bytes  (not patched)".format('class.slp_avalon.php', got_md5, got_size))
    print("")

    out = {
        'avalon-hours.css': build_css(texts['avalon-hours.css']),
        'avalon-hours.js':  build_hours_js(texts['avalon-hours.js']),
        'slp_avalon.js':    build_map_js(texts['slp_avalon.js']),
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
    print("  note      class.slp_avalon.php is not an input: it does not change (77818555, 339,614 bytes).")
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
