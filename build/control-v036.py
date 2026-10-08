#!/usr/bin/env python3
"""
control-v036.py  r1  2026-10-07
SLP Dealer Guard - negative controls for suite-v036, suite-cards r2 and
suite-map r3 (slp_avalon v0.0.27 Part 4e).

WITHOUT THIS FILE IN THE REPO, A CLEAN SCORE ASSERTS NOTHING. A suite that
has only ever been run against a build that works has not been shown to be
capable of failing. Each control below removes exactly one load-bearing
decision from the good build and nothing else, and the release is gated on
the suites catching every one of them, by a pinned count.

    python3 control-v036.py --in <class.slp_avalon.php> --css <avalon-hours.css>
                            --hjs <avalon-hours.js> --js <slp_avalon.js> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for the class control,
<dir>/css-<name>/avalon-hours.css for each stylesheet control,
<dir>/hjs-<name>/avalon-hours.js for each hours-script control and
<dir>/js-<name>/slp_avalon.js for each map-script control. The class
control is scored by suite-v036 with the good stylesheet; a stylesheet
control by suite-v036 with the good class; an hours-script control by
suite-cards; a map-script control by suite-map. Use an out* name for the
output directory - build/out*/ is gitignored (s0.218).

Every substitution is byte-exact and asserted unique before it is applied.
A control that could not be built is a hard error, never a skipped control:
a missing control is a decision nobody is testing.

HOW TO READ A SCORE. A control inside text Part 4e wrote also changes what
the suites pin - suite-v036's edit list and block pin, suite-cards' edit
list, suite-map's block pin and edit list - so it fails those checks by
construction. A behavioural control that fails ONLY those has not been
caught by any behaviour; every one below fails at least one more. The
touch_ controls change nothing Part 4e wrote and show that the identity
assertions - the only things carrying Part 4d's and every earlier part's
evidence forward - can fail.

THE CLASS. Part 4e does not change class.slp_avalon.php, so Part 4d's
eighteen class controls (control-v035.py) have nothing new to say: one
control here, touch_class, shows that suite-v036 notices a class that is
not Part 4d's.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "778185553d663139707b9d23dff00407"
IN_LEN = 339614
CSS_MD5 = "8e862ce347b040f329b77969c226165b"
CSS_LEN = 26533
HJS_MD5 = "588939bf366c2760ba46127d6d9951a6"
HJS_LEN = 18319
JS_MD5 = "5df0fcc4ea5d403e897e99f49f869afa"
JS_LEN = 119874

N = "\r\n"

# ---------------------------------------------------------------------------
# The class control, scored by suite-v036 with the good stylesheet.
# (name, why it matters, [(old, new)])
# ---------------------------------------------------------------------------

CONTROLS = [
    ("touch_class",
     "Part 4e ships Part 4d's class byte for byte; one word of a comment "
     "changed, and it is no longer the file suite-v035 gated.",
     [("         * v0.0.27 Part 4d. The bubble layout in three parts.", "         * v0.0.27 Part 4d: the bubble layout in three parts.")]),
]

# ---------------------------------------------------------------------------
# Stylesheet controls, scored by suite-v036 with the good class.
# ---------------------------------------------------------------------------

CSS_CONTROLS = [
    ("today_beside",
     "TODAY under its hours at every width (the owner, 2026-10-07): beside "
     "them it made the bubble wider than anything else in it needed.",
     [("  content: \"TODAY\" / \"\";\n  display: block;\n  margin: 3px 0 4px;\n",
       "  content: \"TODAY\" / \"\";\n  display: inline-block;\n  margin-left: 12px;\n")]),

    ("today_tight",
     "More room under TODAY than over it, so that it reads with its own "
     "hours and not with the next day's.",
     [("  display: block;\n  margin: 3px 0 4px;\n", "  display: block;\n  margin: 3px 0 2px;\n")]),

    ("bubble_container",
     "What is sized by its content cannot be a size container: contained, "
     "the bubble's lines would no longer count towards its width.",
     [("#map_sidebar .results_wrapper .results_entry {\n  container-type: inline-size;\n}\n",
       "#map_sidebar .results_wrapper .results_entry,\n.slp_info_bubble .avalon-bubble__info {\n  container-type: inline-size;\n}\n")]),

    ("bubble_week_query",
     "No container query names the bubble: it is no container any more.",
     [("@container (max-width: 250px) {\n  #map_sidebar .avalon-hours--card .avalon-hours__week {\n",
       "@container (max-width: 250px) {\n  #map_sidebar .avalon-hours--card .avalon-hours__week,\n"
       "  .slp_info_bubble .avalon-hours--card .avalon-hours__week {\n")]),

    ("fade_back",
     "The shade over the last hours shown (screenshots 4789 to 4791): gone "
     "with the body that scrolled.",
     [("/* ------------------------------------------ Part 4e: the chosen card */\n",
       ".slp_info_bubble .sl_popup_contact_info::after {\n  content: \"\";\n  display: block;\n  position: sticky;\n  bottom: 0;\n"
       "  height: 28px;\n  margin-top: -28px;\n  background: linear-gradient(transparent, #080808);\n  pointer-events: none;\n}\n\n"
       "/* ------------------------------------------ Part 4e: the chosen card */\n")]),

    ("body_capped",
     "Nothing of ours scrolls in a bubble: a phone has none, and a larger "
     "screen's grows as it always did.",
     [("/* ------------------------------------------ Part 4e: the chosen card */\n",
       "@media (max-width: 767px), (max-height: 500px) {\n  .slp_info_bubble .sl_popup_contact_info {\n"
       "    max-height: min(250px, 45vh);\n    overflow-y: auto;\n  }\n}\n\n"
       "/* ------------------------------------------ Part 4e: the chosen card */\n")]),

    ("email_14_back",
     "Part 4c's 14 px email was for a phone's bubble; there is none.",
     [("/* ------------------------------------------- Part 4d: the cards, one grid */\n",
       "@media (max-width: 374px) {\n  .slp_info_bubble #slp_bubble_email a.avalon-email {\n    font-size: 14px;\n  }\n}\n\n"
       "/* ------------------------------------------- Part 4d: the cards, one grid */\n")]),

    ("flash_forever",
     "The flash is there for the eye to find the card: twice, then still.",
     [("  animation: avalon-flash 0.5s ease-out 2;\n", "  animation: avalon-flash 0.5s ease-out infinite;\n")]),

    ("flash_moving",
     "Where the visitor has asked for less motion, no flash: the ring "
     "alone.",
     [("    animation: none;\n", "    animation-duration: 1s;\n")]),

    ("flash_to_fixed",
     "The flash ends on the card's own background, whatever that is - "
     "resting, hovered or chosen: only its start is named.",
     [("  from {\n    background-color: var(--avalon-flash, rgba(255, 255, 255, 0.34));\n  }\n}\n",
       "  from {\n    background-color: var(--avalon-flash, rgba(255, 255, 255, 0.34));\n  }\n"
       "  to {\n    background-color: transparent;\n  }\n}\n")]),

    ("flash_unsafelisted",
     "A card is drawn after a search, so WP Rocket never sees it: the "
     "flash's selector must start with what the safelist names.",
     [("#map_sidebar .results_wrapper.avalon-flash {\n  animation: avalon-flash 0.5s ease-out 2;\n",
       ".results_wrapper.avalon-flash {\n  animation: avalon-flash 0.5s ease-out 2;\n")]),

    ("flash_important",
     "No !important: a site must be able to restyle its own flash.",
     [("    animation: none;\n", "    animation: none !important;\n")]),

    ("touch_css",
     "One word of Part 4's own comment, which Part 4e must leave byte for "
     "byte.",
     [(" * Google-style opening hours on store pages, find-a-dealer result cards and\n",
       " * Google-style opening hours on store pages, find-a-dealer result cards, and\n")]),
]

# ---------------------------------------------------------------------------
# Hours-script controls, scored by suite-cards.
# ---------------------------------------------------------------------------

HJS_CONTROLS = [
    ("keep_everything",
     "The fault itself: every click in a card's hours block kept from the "
     "card, so a card with its week open took no click and showed no ring.",
     [("      if (n.tagName === \"SUMMARY\" || n.tagName === \"A\") {\n", "      if (n.tagName) {\n")]),

    ("keep_nothing",
     "The Hours line still does only what it does: a visitor who wanted "
     "the hours has not chosen the dealer.",
     [("      if (n.tagName === \"SUMMARY\" || n.tagName === \"A\") {\n        e.stopPropagation();\n        return;\n",
       "      if (n.tagName === \"SUMMARY\" || n.tagName === \"A\") {\n        return;\n")]),

    ("keep_no_link",
     "An attribution link opens its page; it does not choose the dealer "
     "as well.",
     [("n.tagName === \"SUMMARY\" || n.tagName === \"A\"", "n.tagName === \"SUMMARY\"")]),

    ("keep_no_summary",
     "The summary, not only a link, keeps its click.",
     [("n.tagName === \"SUMMARY\" || n.tagName === \"A\"", "n.tagName === \"A\"")]),

    ("keep_past_block",
     "The walk up from a click stops at the block: a link the card itself "
     "sits in is not keep()'s to judge.",
     [("      if ((\" \" + n.className + \" \").indexOf(\" avalon-hours \") >= 0) {\n        return;\n      }\n    }\n  }\n",
       "    }\n  }\n")]),

    ("keep_text_target",
     "A click that lands on text is its element's.",
     [("    if (n && n.nodeType === 3) {\n      n = n.parentNode;\n    }\n", "")]),

    ("keep_needs_event",
     "An event with no target - or no event - stops nothing and throws "
     "nothing.",
     [("    var n = e && e.target;\n", "    var n = e.target;\n")]),

    ("touch_hours_js",
     "One word of Part 4's own comment, which Part 4e must leave byte for "
     "byte.",
     [(" * Google-style opening hours on store pages and find-a-dealer result cards.\n",
       " * Google-style opening hours on store pages and on find-a-dealer result cards.\n")]),
]

# ---------------------------------------------------------------------------
# Map-script controls, scored by suite-map. The file is CRLF.
# ---------------------------------------------------------------------------

JS_CONTROLS = [

    # ---- phones ---------------------------------------------------------

    ("phone_never",
     "On a phone a pin opens no bubble (the owner, 2026-10-07).",
     [("             !!(window.matchMedia && window.matchMedia(PHONE).matches);" + N, "             false;" + N)]),

    ("phone_upright_only",
     "A phone held sideways is short, not narrow: 500 px high or less is a "
     "phone too.",
     [("    var PHONE = \"(max-width: 767px), (max-height: 500px)\";" + N, "    var PHONE = \"(max-width: 767px)\";" + N)]),

    ("fullscreen_is_a_phone",
     "A map shown full screen keeps its bubble: no card can be seen "
     "behind it.",
     [("      return !(d.fullscreenElement || d.webkitFullscreenElement) &&" + N +
       "             !!(window.matchMedia && window.matchMedia(PHONE).matches);" + N,
       "      return !!(window.matchMedia && window.matchMedia(PHONE).matches);" + N)]),

    ("fullscreen_unprefixed_only",
     "Safari's full screen is prefixed.",
     [("      return !(d.fullscreenElement || d.webkitFullscreenElement) &&" + N, "      return !d.fullscreenElement &&" + N)]),

    ("fullscreen_unheard",
     "Going out of full screen does not always resize the window: its own "
     "events are heard, the prefixed one too.",
     [("      document.addEventListener(\"webkitfullscreenchange\", full, false);" + N, "")]),

    ("esc_keeps_choice",
     "Esc lets a dealer chosen on a phone go, as it closes a bubble.",
     [("!(st.open || st.pinned) || ev.defaultPrevented ||", "!st.open || ev.defaultPrevented ||")]),

    ("chosen_pin_unlit",
     "A dealer chosen on a phone has no bubble to keep its pin lit: the "
     "choice does.",
     [("        lit(e, hovered(e) || (st.pinned && st.current === e));" + N, "        lit(e, hovered(e));" + N)]),

    ("no_flash",
     "The card flashes, so that the eye finds it.",
     [("      place(e, true);" + N + "      flash(e);" + N + "      to_card(e);" + N,
       "      place(e, true);" + N + "      to_card(e);" + N)]),

    ("flash_stays",
     "The flash comes off when it is done.",
     [("        st.fl = 0;" + N + "        cls(c, \"avalon-flash\", false);" + N + "      }, FLASH_MS);" + N,
       "        st.fl = 0;" + N + "      }, FLASH_MS);" + N)]),

    ("flash_not_restarted",
     "A card flashed a moment ago starts over: it is read first.",
     [("      void c.offsetWidth;" + N, "")]),

    ("flash_outlives_choice",
     "The choice ended, the flash ends with it.",
     [("      ring(null);" + N + "      flash(null);" + N, "      ring(null);" + N)]),

    ("no_focus_to_card",
     "Focus goes to the chosen dealer's card, as it would have gone into "
     "a bubble.",
     [("      flash(e);" + N + "      to_card(e);" + N + "    }" + N, "      flash(e);" + N + "    }" + N)]),

    ("focus_scrolls",
     "Focus is moved without scrolling the page: the page is scrolled on "
     "purpose, just far enough.",
     [("        t.focus({ preventScroll: true });" + N + "      } catch (x) {" + N +
       "        //A card that refuses focus leaves focus where it was." + N,
       "        t.focus();" + N + "      } catch (x) {" + N +
       "        //A card that refuses focus leaves focus where it was." + N)]),

    ("focus_under_form",
     "Not while the Contact Dealer form is open over the page.",
     [("      if (!c || document.querySelector(\".contact-dealer--pop-up.open-modal\")) {" + N + "        return;" + N + "      }" + N +
       "      var t = ",
       "      if (!c) {" + N + "        return;" + N + "      }" + N + "      var t = ")]),

    ("focus_origin_forgotten",
     "Where focus came from is remembered, and Esc gives it back.",
     [("      if (a && a !== document.body && !inside(a) && !on_card(a)) {" + N + "        st.back = a;" + N + "      }" + N +
       "      st.sent = t;" + N,
       "      st.sent = t;" + N)]),

    ("focus_not_given_back",
     "Focus still where to_card() put it goes back where it came from when "
     "the choice ends.",
     [("      if (!back || (a && a !== document.body && !inside(a) && !held)) {" + N,
       "      if (!back || (a && a !== document.body && !inside(a))) {" + N)]),

    ("card_without_link",
     "A card whose name is not a link takes focus itself.",
     [("      if (t === c && typeof c.setAttribute === \"function\") {" + N + "        c.setAttribute(\"tabindex\", \"-1\");" + N + "      }" + N, "")]),

    ("card_choice_as_pin",
     "A dealer chosen on its own card is left where the pointer found it: "
     "not moved, not scrolled, not flashed.",
     [("      var card = st.via === \"card\";" + N, "      var card = false;" + N)]),

    ("via_never_reset",
     "A click on a card is a card's for that click only.",
     [("        st.via = \"card\";" + N + "        setTimeout(function () {" + N + "          st.via = \"\";" + N + "        }, 0);" + N,
       "        st.via = \"card\";" + N)]),

    ("via_bubbling",
     "The click is heard before SLP's handler on the card reaches show(): "
     "in the capture phase.",
     [("          st.via = \"\";" + N + "        }, 0);" + N + "      }, true);" + N,
       "          st.via = \"\";" + N + "        }, 0);" + N + "      }, false);" + N)]),

    ("map_always_moves",
     "A card chosen on a phone moves the map only when its pin is out of "
     "sight.",
     [("        if (at && b && !b.contains(at)) {" + N, "        if (at && b) {" + N)]),

    # ---- the card in view -----------------------------------------------

    ("pin_leaves_card",
     "A dealer chosen on the map has its card brought where it can be "
     "seen, where a bubble opens too.",
     [("        if (!card) {" + N + "          place(e, false);" + N + "        }" + N, "")]),

    ("page_scrolls_under_bubble",
     "Where a bubble opens, the page itself is never scrolled.",
     [("        if (!card) {" + N + "          place(e, false);" + N, "        if (!card) {" + N + "          place(e, true);" + N)]),

    ("in_view_scrolled",
     "A card already in full view is left where it is.",
     [("      if (r.top >= lo - 1 && r.bottom <= hi + 1) {" + N + "        return;" + N + "      }" + N, "")]),

    ("no_room_made",
     "The last cards cannot scroll to the top: room is made under them.",
     [("      if (dy > max && side && side.style && typeof window.getComputedStyle === \"function\") {" + N, "      if (false) {" + N)]),

    ("room_never_taken_away",
     "The room made for one card goes when another is chosen, or none.",
     [("      if (st.pad && st.pad.card !== c) {" + N + "        unpad();" + N + "      }" + N, "")]),

    ("edge_ignores_window",
     "A box whose top is above the window's: the card goes under the "
     "window's top, not under the box's.",
     [("        edge = b.top >= lo || b.bottom <= lo || b.top >= hi ? b.top : edge;" + N, "        edge = b.top;" + N)]),

    ("header_ignored",
     "A fixed bar that covers the window's top is not where a card can be "
     "seen.",
     [("          return b > 0 && b < h / 2 ? b : 0;" + N, "          return 0;" + N)]),

    ("smooth_regardless",
     "Less motion asked for: at once.",
     [("behavior: calm ? \"instant\" : \"smooth\" });", "behavior: \"smooth\" });")]),

    ("no_scroll_fallback",
     "A browser whose scrollBy() takes no options still scrolls.",
     [("        if (el) {" + N + "          el.scrollTop += dy;" + N + "        } else {" + N,
       "        if (!el) {" + N + "          el.scrollTop += dy;" + N + "        } else {" + N)]),

    ("phone_page_stays",
     "A phone held sideways shows less than a card's height of a box that "
     "starts low: the page scrolls just far enough.",
     [("      scroll_by(s, dy);" + N + "      if (far) {" + N, "      scroll_by(s, dy);" + N + "      if (false) {" + N)]),

    ("phone_page_past_box",
     "The page never takes the box's top out of the window.",
     [("Math.min(edge + r.bottom - r.top - (foot - GAP), b.top - top - GAP)", "edge + r.bottom - r.top - (foot - GAP)")]),

    ("stacked_by_phone",
     "Beside the map or under it is read from where the list lies, not "
     "from the screen: a tablet upright has it under too.",
     [("      return box.getBoundingClientRect().top >= m.getBoundingClientRect().bottom - 1;" + N, "      return phone();" + N)]),

    ("card_never_put_back",
     "A card moved to the top of the list goes back to its place.",
     [("      if (st.moved && st.moved.el !== c) {" + N + "        put_back();" + N + "      }" + N, "")]),

    ("last_card_lost",
     "The last card has no next: it goes back to the end.",
     [("      } else if (!m.next) {" + N + "        move(m.el, p, null);" + N + "      }" + N, "      }" + N)]),

    ("move_drops_focus",
     "Moving a card takes focus from its name: given back.",
     [("      if (had && document.activeElement !== a) {" + N, "      if (false) {" + N)]),

    ("list_not_rewound",
     "Under the map the list itself goes back to its top, where the card "
     "now is.",
     [("      if (s) {" + N + "        s.scrollTop = 0;" + N + "      }" + N, "")]),

    ("page_scrolled_back_up",
     "The page is never scrolled back up.",
     [("      if (dy > 0) {" + N + "        scroll_by(null, dy);" + N, "      if (dy) {" + N + "        scroll_by(null, dy);" + N)]),

    ("tall_card_hidden",
     "A card too tall to show whole with the map still shows its best "
     "part: the map's top is let go.",
     [("      var dy = Math.round(Math.max(some, Math.min(all, room)));" + N, "      var dy = Math.round(Math.min(all, room));" + N)]),

    ("choice_end_leaves_card",
     "The choice ended: the card back in its place, the room gone.",
     [("      flash(null);" + N + "      unplace(null);" + N, "      flash(null);" + N)]),

    # ---- resize ---------------------------------------------------------

    ("narrowed_keeps_bubble",
     "A window narrowed to a phone's with a bubble open loses the bubble.",
     [("      if (small ? !st.open && !st.pinned : st.open || !st.pinned) {" + N, "      if (small || st.open || !st.pinned) {" + N)]),

    ("narrowed_drops_choice",
     "... and keeps the choice: that close is not the visitor's.",
     [("      if (!chosen) {" + N + "        clear();" + N, "      if (true) {" + N + "        clear();" + N)]),

    ("narrowed_hover_kept",
     "A bubble only hovered is let go when it cannot stay.",
     [("      if (!chosen) {" + N + "        clear();" + N + "        return;" + N + "      }" + N, "")]),

    ("narrowed_focus_lost",
     "Focus that was in the bubble goes to the card.",
     [("      place(e, true);" + N + "      if (had) {" + N + "        to_card(e);" + N + "      }" + N, "      place(e, true);" + N)]),

    ("widened_no_bubble",
     "A window widened with a dealer chosen gets that dealer's bubble.",
     [("      if (!small) {" + N + "        show(e.info, e.marker);" + N + "        st.wantFocus = false;" + N + "        return;" + N,
       "      if (!small) {" + N + "        return;" + N)]),

    ("widened_takes_focus",
     "... and takes no focus: nobody chose anything just now.",
     [("        show(e.info, e.marker);" + N + "        st.wantFocus = false;" + N, "        show(e.info, e.marker);" + N)]),

    ("turned_card_left",
     "A phone turned with a dealer chosen: its card where the layout now "
     "shows it.",
     [("      if (!st.open) {" + N + "        place(e, true);" + N + "        return;" + N + "      }" + N,
       "      if (!st.open) {" + N + "        return;" + N + "      }" + N)]),

    ("resize_under_form",
     "Under the Contact Dealer form it waits: the form gives focus back to "
     "a link in the bubble.",
     [("      if (document.querySelector(\".contact-dealer--pop-up.open-modal\")) {" + N + "        st.rs = setTimeout(resized, RESIZE_MS);" + N +
       "        return;" + N + "      }" + N, "")]),

    ("late_close_clears",
     "A close event that arrives when no bubble is up is nobody's.",
     [("      if (st.quiet || !st.open || (iw && iw.isOpen === true)) {" + N, "      if (st.quiet || (iw && iw.isOpen === true)) {" + N)]),

    ("page_put_back_smoothly",
     "The page is put back at once: with a smooth scroll-behavior, what "
     "follows would measure a page still moving.",
     [("          window.scrollTo({ left: sx, top: sy, behavior: \"instant\" });" + N, "          window.scrollTo({ left: sx, top: sy });" + N)]),

    ("page_not_put_back",
     "A browser whose scrollTo() takes no options still gets its page "
     "back.",
     [("          if (window.pageXOffset !== sx || window.pageYOffset !== sy) {" + N + "            window.scrollTo(sx, sy);" + N + "          }" + N, "")]),

    ("stale_bubble_left",
     "A pin chosen on a window just narrowed: the last bubble goes.",
     [("      if (st.open && st.cm) {" + N + "        reclose(st.cm.infowindow);" + N + "      }" + N + "      st.current = e;" + N,
       "      st.current = e;" + N)]),

    # ---- the bubble's width ---------------------------------------------

    ("width_not_held",
     "Opening the hours must not widen the bubble under the pointer: the "
     "week is measured open as the bubble arrives.",
     [("      hold();" + N + "      if (st.wantFocus) {" + N, "      if (st.wantFocus) {" + N)]),

    ("width_measured_again",
     "Measured once for each bubble's content, however often domready "
     "fires.",
     [("      b.avalonHeld = true;" + N, "")]),

    ("week_left_open",
     "The week is shut again after it is measured.",
     [("      d.open = false;" + N + "      if (w > 0) {" + N, "      if (w > 0) {" + N)]),

    ("open_week_shut",
     "A week the visitor has open is not shut to be measured.",
     [("      if (!b || b.avalonHeld || !d || d.open || !b.style || !b.getBoundingClientRect) {" + N,
       "      if (!b || b.avalonHeld || !d || !b.style || !b.getBoundingClientRect) {" + N)]),

    ("width_of_nothing",
     "A bubble not laid out measures nothing: no width is set from it.",
     [("      if (w > 0) {" + N + "        b.style.minWidth = Math.ceil(w) + \"px\";" + N, "      if (true) {" + N + "        b.style.minWidth = Math.ceil(w) + \"px\";" + N)]),

    ("width_to_google",
     "Google is given no width any more: on a phone it widened the bubble "
     "to the map's, and there is no bubble there.",
     [("        iw.setOptions({ ariaLabel: name_of(info) });" + N, "        iw.setOptions({ ariaLabel: name_of(info), minWidth: 0 });" + N)]),

    # ---- identity -------------------------------------------------------

    ("touch_block",
     "One word of Part 4c's own comment inside the block, which Part 4e "
     "must leave byte for byte.",
     [("   * Asked for on 2026-10-04 and settled on 2026-10-05; the owner's words" + N,
       "   * Asked for on 2026-10-04 and settled on 2026-10-05: the owner's words" + N)]),

    ("touch_v025",
     "One word of v0.0.25's own code, which Part 4e must leave byte for byte.",
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

    print("control-v036.py  r1  2026-10-07")
    print("  negative controls for suite-v036, suite-cards and suite-map")
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

    for title, items, text, prefix, fname, enc in (("class control", CONTROLS, src, "", "class.slp_avalon.php", ENC),
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
