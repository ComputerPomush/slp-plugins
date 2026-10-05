#!/usr/bin/env python3
"""
build-v027-part4b.py

slp_avalon v0.0.27, PART 4b - the info bubble and two colours.

WHAT THIS RELEASE DOES
----------------------
Asked for on 2026-10-04, after Part 4 went live on Aura DEV:

1. THE INFO BUBBLE SHOWS WHAT THE CARD SHOWS. The bubble a map pin opens on
   /find-a-dealer/ gets the card's lines in the card's order - Distance:,
   Address:, Phone: as a tel: link - then Email: and Hours:, folded with its
   caret like the card. The bubble is SLP's bubblelayout run through the same
   replace_shortcodes() as the card, on the same marker (slp_core.js
   createMarkerContent()), so Part 4's fields are already on every marker.
   New here: the Email: field on slp_results_marker_data at 25, and the
   bubble layout on slp_js_options at 100, after SLP Experience merges its
   stored settings at 90.

2. EMAIL. "Email:" bold, the address as the link, white at rest and the
   site's primary colour on hover and focus, opening the visitor's mail
   program as SLP's own mailto: link did.

3. COLOURS. Closed is the site's primary colour (--e-global-color-primary,
   #E7167C on Aura) instead of Google's red. The tel: link loses its
   underline and turns the primary colour on hover and focus.

4. THE BUBBLE'S HOURS ARE LIVE. avalon-hours.js also watches #map, so the
   hours block in a bubble gets its status and today-first order the moment
   the bubble opens; and when a bubble's week is opened, the map pans down
   if the bubble now reaches past the map's top edge - Google pans a bubble
   into view only when it opens.

The white frame around the bubble is NOT in this release: it is the theme's
dark-bubble design (style.css), and stays out of the plugin that travels to
Tahoe and Avalon.

WHAT IS PATCHED
---------------
class.slp_avalon.php: three registrations appended to add_actions() after
Part 4's, and one block of five methods inserted directly before
avalon_rest_protected_slugs() - after Part 4's block. Nothing else moves.
avalon-hours.css: three anchored edits. avalon-hours.js: five anchored edits.

ENCODING
--------
The class is CRLF and stays CRLF (ISO-8859-1, newline='' semantics, the CR
count printed). The two assets are LF ASCII and stay so. Every inserted line
is pure ASCII.

Usage:  python build-v027-part4b.py <src_dir> <out_dir>

        src_dir must hold the PART 4 OUTPUT:
            class.slp_avalon.php   d9e4b7eda90ead11e97211813847a42a  300222
            avalon-hours.css       a42a04fe921d8fbb800984903acb57c7    5535
            avalon-hours.js        abf650630474e77be40ea67b06fab3dd   12279
        and it writes the three files, or refuses to.
"""

import hashlib
import io
import os
import re
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('d9e4b7eda90ead11e97211813847a42a', 300222),
    'avalon-hours.css':     ('a42a04fe921d8fbb800984903acb57c7', 5535),
    'avalon-hours.js':      ('abf650630474e77be40ea67b06fab3dd', 12279),
}

# The outputs this script was reviewed against, measured 2026-10-04. A build
# that produces other bytes is refused before anything is written.
OUT_PINS = {
    'class.slp_avalon.php': ('da3bd46a6af3b34a9552f6b1119dc13f', 312186),
    'avalon-hours.css':     ('1fd4083289b335140a0196bf62e54605', 8374),
    'avalon-hours.js':      ('76a62b751b5556b96b79c2734b05dca8', 14683),
}


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def crlf(s):
    """Source blocks below are written with LF; the class is CRLF."""
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


# ===========================================================================
# 1. The class: three registrations
# ===========================================================================

WIRE_ANCHOR = crlf("""            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist'));
""")
WIRE_BLOCK = crlf("""            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist'));
            //
            // v0.0.27 Part 4b. The info bubble shows what the card shows.
            //
            // The Email: field onto every marker at 25, after Part 4's
            // labels at 20. The bubble layout on slp_js_options at 100: SLP
            // puts its options in at 10 and SLP Experience merges its stored
            // settings over them at 90, so whichever bubble layout won is the
            // one given the fields. Same priority as Part 4's results-layout
            // callback, registered after it; the two touch different keys.
            // The bubble's selectors onto WP Rocket's safelist, beside Part
            // 4's; a no-op where WP Rocket is not installed.
            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_email'), 25, 1);
            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_bubble'), 100, 1);
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));
""")

# ===========================================================================
# 2. The class: the bubble block, inserted before avalon_rest_protected_slugs()
# ===========================================================================

INSERT_BEFORE = crlf("""        public function avalon_rest_protected_slugs(){
""")

BUBBLE_BLOCK = crlf(r"""        /**
         * v0.0.27 Part 4b. The info bubble shows what the card shows.
         *
         * Asked for on 2026-10-04: the bubble a map pin opens on
         * /find-a-dealer/ carries the card's lines - Distance:, Address:,
         * Phone: as a tel: link, Hours: folded with its caret - and the
         * dealer's email as "Email:", bold, then the address as the link.
         *
         * The bubble is SLP's bubblelayout run through the same
         * replace_shortcodes() as the card, on the same marker object
         * (slp_core.js createMarkerContent()), so Part 4's
         * avalon_address_label, avalon_phone_html and avalon_hours_html
         * are already on every marker. Only the email field is new.
         *
         * ORDER, on Aura's layout: Distance, Address, Phone, Email, Hours.
         * The card's lines in the card's order; the email beside the
         * phone, the other way to reach the dealer; the hours last, so
         * opening the week moves nothing above it. Nothing else is moved:
         * on SLP's default layout, where Directions and Website sit
         * between the address and the phone, they stay there.
         *
         * The white frame Google draws round the bubble is not here: it
         * belongs to the theme's dark bubble (style.css), and stays out of
         * a plugin that travels to Tahoe and Avalon.
         */

        /**
         * v0.0.27 Part 4b. The bubble's Email: field, as a marker field.
         *
         * Like Part 4's phone: [slp_location X] prints nothing when X is
         * empty, so a dealer with no email shows no "Email:"; and the field
         * is a string, as SLP's replace_shortcodes() needs (s0.277).
         *
         * The address comes from the raw row - the marker's 'data', the
         * locator row both marker builders carry (s0.275) - else from the
         * marker's own value. Only an address is_email() accepts, and with
         * none of ? # % & / \ { } - which a mailto: URL reads as headers,
         * a fragment or an escape, or which esc_url() strips - becomes a
         * link. The three feeds hold 201 addresses, every one a single
         * plain address of at most 34 characters (measured 2026-10-04);
         * anything else keeps its text, escaped, unlinked. A no-break
         * space counts as a space. target="_blank", as SLP's own mailto:
         * wrap has it, so a webmail handler opens beside the map rather
         * than over it.
         */
        public static function avalon_email_fields( $marker ){
            $shown = isset( $marker['email'] )
                     ? trim( str_replace( "\xC2\xA0", ' ',
                             html_entity_decode( (string) $marker['email'], ENT_QUOTES, 'UTF-8' ) ) ) : '';
            if ( '' === $shown ) {
                return $marker;
            }
            $raw = ( isset( $marker['data'] ) && is_array( $marker['data'] ) && isset( $marker['data']['sl_email'] ) )
                   ? trim( str_replace( "\xC2\xA0", ' ', (string) $marker['data']['sl_email'] ) ) : '';
            if ( '' === $raw ) {
                $raw = $shown;
            }
            $to   = ( false === strpbrk( $raw, '?#%&/\\{}' ) && is_email( $raw ) ) ? $raw : '';
            $text = esc_html( '' !== $to ? $to : $shown );
            if ( '' === $text ) {
                return $marker;
            }
            $marker['avalon_email_html'] = '<b class="avalon-label">Email:</b> '
                . ( '' !== $to
                    ? '<a class="avalon-email" href="' . esc_url( 'mailto:' . $to, array( 'mailto' ) )
                      . '" target="_blank" rel="noopener">' . $text . '</a>'
                    : $text );
            return $marker;
        }

        /**
         * v0.0.27 Part 4b. The Email: field, onto every marker.
         *
         * On slp_results_marker_data at 25, after Part 4's labels at 20,
         * for the reason they are there: both marker builders apply it, so
         * the field is wherever SLP renders a bubble. No read.
         */
        public function avalon_marker_email( $marker ){
            return is_array( $marker ) ? self::avalon_email_fields( $marker ) : $marker;
        }

        /**
         * v0.0.27 Part 4b. The card's fields, into the bubble layout.
         *
         *   Distance:  a line before the address, the card's own markup
         *   Address:   at the start of the address line
         *   Phone:     SLP's label and number become the labelled tel: field
         *   Email:     SLP's mailto: wrap becomes the labelled field, and
         *              moves to just after the phone line
         *   Hours:     right after the email line, else after the phone line
         *
         * Anchored on SLP's own span ids, which SLP's default bubble layout
         * and Aura's both carry. Each step is skipped, not forced, when its
         * anchor is absent, and when its own field is already there - so a
         * layout this does not recognise is left as it was, and a second
         * pass changes nothing. The name, the two buttons and the outer
         * div, whose id main.js reads for Contact Dealer, are not touched.
         *
         * Every insertion is made by offset, never through a regex
         * replacement string, so nothing in the layout is read as a
         * backreference. An id is matched only as an attribute of its own
         * (\sid=), never inside data-id=. The phone step reads no further
         * than its own span: past nothing but SLP's label span and plain
         * text, never a div or another span's end - a phone span with no
         * number of its own is left alone, not merged with what follows.
         */
        public function avalon_bubble_layout( $layout ){
            $layout = (string) $layout;
            $addr   = '/<span\b[^>]*\sid="slp_bubble_address"[^>]*>/';
            $phone  = '/<span\b[^>]*\sid="slp_bubble_phone"[^>]*>\[slp_location avalon_phone_html\]<\/span>/';

            if ( false === strpos( $layout, 'avalon-bubble-distance' )
                 && false === strpos( $layout, '[slp_location distance' )
                 && preg_match( $addr, $layout, $m, PREG_OFFSET_CAPTURE ) ) {
                $layout = substr_replace( $layout,
                    '<span class="avalon-bubble-distance">Distance: [slp_location distance format="decimal1"] [slp_option distance_unit]</span>',
                    $m[0][1], 0 );
            }

            if ( false === strpos( $layout, 'avalon_address_label' )
                 && preg_match( $addr, $layout, $m, PREG_OFFSET_CAPTURE ) ) {
                $layout = substr_replace( $layout, '[slp_location avalon_address_label]',
                                          $m[0][1] + strlen( $m[0][0] ), 0 );
            }

            if ( false === strpos( $layout, 'avalon_phone_html' )
                 && preg_match( '/(<span\b[^>]*\sid="slp_bubble_phone"[^>]*>)(?:<span\b(?![^>]*\sid="slp_bubble_)[^>]*>(?:(?!<\/?span\b)[\s\S])*<\/span>|(?!<\/?(?:span|div)\b)[\s\S])*?\[slp_location\s+phone\b[^\]]*\]\s*<\/span>/',
                                $layout, $m, PREG_OFFSET_CAPTURE ) ) {
                $layout = substr_replace( $layout, $m[1][0] . '[slp_location avalon_phone_html]</span>',
                                          $m[0][1], strlen( $m[0][0] ) );
            }

            if ( false === strpos( $layout, 'avalon_email_html' )
                 && preg_match( '/(<span\b[^>]*\sid="slp_bubble_email"[^>]*>)((?:(?!<\/?span\b)[\s\S])*)<\/span>/',
                                $layout, $m, PREG_OFFSET_CAPTURE )
                 && preg_match( '/\[slp_location\s+email\b/', $m[2][0] ) ) {
                $field = $m[1][0] . '[slp_location avalon_email_html]</span>';
                $at    = $m[0][1];
                $len   = strlen( $m[0][0] );
                if ( preg_match( $phone, $layout, $p, PREG_OFFSET_CAPTURE ) ) {
                    $end = $p[0][1] + strlen( $p[0][0] );
                    if ( $end > $at ) {
                        $layout = substr_replace( $layout, $field, $end, 0 );
                        $layout = substr_replace( $layout, '', $at, $len );
                    } else {
                        $layout = substr_replace( $layout, '', $at, $len );
                        $layout = substr_replace( $layout, $field, $end, 0 );
                    }
                } else {
                    $layout = substr_replace( $layout, $field, $at, $len );
                }
            }

            if ( false === strpos( $layout, 'avalon_hours_html' )
                 && ( preg_match( '/<span\b[^>]*\sid="slp_bubble_email"[^>]*>\[slp_location avalon_email_html\]<\/span>/',
                                  $layout, $m, PREG_OFFSET_CAPTURE )
                      || preg_match( $phone, $layout, $m, PREG_OFFSET_CAPTURE ) ) ) {
                $layout = substr_replace( $layout, '[slp_location avalon_hours_html]',
                                          $m[0][1] + strlen( $m[0][0] ), 0 );
            }

            return $layout;
        }

        /**
         * v0.0.27 Part 4b. The fields, on the bubble layout the browser is given.
         *
         * The bubble layout reaches the browser in the script options, as
         * slplus.options.bubblelayout. On slp_js_options at 100, after SLP
         * Experience merges its stored settings at 90, so whichever layout
         * won - SLP's own setting or one left in Experience's - is the one
         * given the fields. Anything that is not a string is left alone.
         */
        public function avalon_js_options_bubble( $options ){
            if ( is_array( $options ) && isset( $options['bubblelayout'] ) && is_string( $options['bubblelayout'] ) ) {
                $options['bubblelayout'] = $this->avalon_bubble_layout( $options['bubblelayout'] );
            }
            return $options;
        }

        /**
         * v0.0.27 Part 4b. WP Rocket: the bubble's selectors, beside Part 4's.
         *
         * A bubble exists only after a pin is clicked, so no rule for it is
         * in the HTML that Remove Unused CSS reads. Part 4 safelists the
         * whole stylesheet and, as a second line, its selectors; this adds
         * the bubble's to that second line, written from the selector's
         * start as WP Rocket 3.11.0.2 and later read them (s0.284).
         */
        public static function avalon_rocket_rucss_safelist_bubble( $list ){
            $list   = is_array( $list ) ? $list : array();
            $list[] = '(.*).avalon-email(.*)';
            $list[] = '(.*).avalon-bubble-distance(.*)';
            $list[] = '(.*).slp_info_bubble(.*)';
            return $list;
        }

""")

NEW_METHODS = (
    'public static function avalon_email_fields( $marker ){',
    'public function avalon_marker_email( $marker ){',
    'public function avalon_bubble_layout( $layout ){',
    'public function avalon_js_options_bubble( $options ){',
    'public static function avalon_rocket_rucss_safelist_bubble( $list ){',
)


# ===========================================================================
# 3. avalon-hours.css
# ===========================================================================

CSS_HEAD_OLD = """ * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4.
 *
 * Google-style opening hours on store pages and find-a-dealer result cards.
 * One stylesheet for both surfaces, shipped with the plugin so it travels to
 * Tahoe and Avalon; the child theme's store-pages.css loads only on store
 * pages and cannot reach /find-a-dealer/.
 *
 * COLOURS are Google's dark-theme status colours, measured against Aura's
 * backgrounds (WCAG contrast, 2026-10-03):
 *
 *                          store #000   card rest   card hover   #2a2a2a
 *   open    #81c995           10.72        8.89        8.41        7.33
 *   closed  #f28b82            8.79        7.29        6.90        6.01
 *   soon    #fcad70           11.35        9.41        8.91        7.76
 *
 * card rest = rgba(255,255,255,.1) over #000; card hover = rgba(222,139,13,.2)
 * over #000; #2a2a2a = a worst case for the store page's background image.
 * A site on a light background overrides the three custom properties.
 *
 * The brand pink is NOT used for the card's tel: link: 3.98 on a card at
 * rest and 3.77 on hover, under 4.5. The link is the text colour, underlined.
"""
CSS_HEAD_NEW = """ * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4b.
 *
 * Google-style opening hours on store pages, find-a-dealer result cards and
 * the map's info bubble. One stylesheet for every surface, shipped with the
 * plugin so it travels to Tahoe and Avalon; the child theme's store-pages.css
 * loads only on store pages and cannot reach /find-a-dealer/.
 *
 * COLOURS. Open and the two "soon" lines are Google's dark-theme status
 * colours. Closed is the site's own primary colour - Elementor's
 * --e-global-color-primary, #E7167C on Aura - by the owner's decision of
 * 2026-10-04, so each brand shows its own. WCAG contrast against Aura's
 * backgrounds, measured 2026-10-04:
 *
 *                     store #000   bubble #080808   card rest   card hover
 *   open    #81c995      10.72         10.23           8.89        8.41
 *   closed  #e7167c       4.80          4.58           3.98        3.77
 *   soon    #fcad70      11.35         10.82           9.41        8.91
 *
 * card rest = rgba(255,255,255,.1) over #000; card hover = rgba(222,139,13,.2)
 * over #000. On a card the pink is under 4.5 - the owner's choice; the word
 * is bold. #2a2a2a, a worst case for the store page's background image,
 * gives it 3.28. A site on a light background overrides the three custom
 * properties.
 *
 * LINKS. The tel: link on cards and in the bubble, and the bubble's mailto:
 * link, are the text colour with no underline, and turn the primary colour
 * on hover and on keyboard focus, with an outline (owner's decision,
 * 2026-10-04; why keyboard focus, below).
"""

CSS_VAR_OLD = """  --avalon-hours-closed: #f28b82;
"""
CSS_VAR_NEW = """  --avalon-hours-closed: var(--e-global-color-primary, var(--primary-color, #e7167c));
"""

CSS_LINK_OLD = """.sl_contact__info a.avalon-tel,
a.avalon-tel {
  color: inherit;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.sl_contact__info a.avalon-tel:hover,
.sl_contact__info a.avalon-tel:focus-visible,
a.avalon-tel:hover,
a.avalon-tel:focus-visible {
  text-decoration-thickness: 2px;
}
"""
CSS_LINK_NEW = """/* The tel: and mailto: links: the text colour at rest, no underline; the
   site's primary colour on hover and on keyboard focus. The card and bubble
   scopes (0-2-1, 0-3-1) sit above the theme's a:hover and a:focus (0-1-1).

   FOCUS-VISIBLE, NOT FOCUS. Google moves focus into an info bubble as it
   opens, onto its first link - with the close button hidden, the phone.
   Coloured on :focus, the phone would open pink on every click of a pin;
   that is why SLP's old "Email" link opened pink (the theme's a:focus). A
   pointer click on a pin does not match :focus-visible; a Tab does. */
.sl_contact__info a.avalon-tel,
.slp_info_bubble a.avalon-tel,
.slp_info_bubble a.avalon-email,
a.avalon-tel,
a.avalon-email {
  color: inherit;
  text-decoration: none;
}

.sl_contact__info a.avalon-tel:hover,
.slp_info_bubble a.avalon-tel:hover,
.slp_info_bubble a.avalon-email:hover,
a.avalon-tel:hover,
a.avalon-email:hover {
  color: var(--e-global-color-primary, var(--primary-color, #e7167c));
  text-decoration: none;
}

/* A rule of its own, so a browser without :focus-visible drops only this
   one and keeps the hover colour above. */
.sl_contact__info a.avalon-tel:focus-visible,
.slp_info_bubble a.avalon-tel:focus-visible,
.slp_info_bubble a.avalon-email:focus-visible,
a.avalon-tel:focus-visible,
a.avalon-email:focus-visible {
  color: var(--e-global-color-primary, var(--primary-color, #e7167c));
  text-decoration: none;
  outline: 2px solid currentColor;
  outline-offset: 2px;
  border-radius: 2px;
}

/* ----------------------------------------------------------- info bubble */

/* Part 4b. The bubble a map pin opens carries the card's lines, in the
   card's order - Distance:, Address:, Phone: - then Email: and Hours:, from
   the bubble layout slp_avalon rewrites. Each starts its own line; the
   address keeps SLP's run of spans on one. The frame Google draws round the
   bubble is the theme's to style (style.css), not this file's.

   The card's weight, 400: Google's InfoWindow sets 300 on .gm-style-iw,
   which the bubble's text would inherit, and on Aura Figtree is a variable
   font whose 300 is real - the address would be lighter than the links
   beside it and than the card. Labels, the name and the buttons set their
   own weights. An email address has no break point, and the bubble does
   not scroll sideways: a long one may break anywhere rather than be cut. */
.slp_info_bubble {
  font-weight: 400;
}

.slp_info_bubble .avalon-bubble-distance,
.slp_info_bubble #slp_bubble_phone,
.slp_info_bubble #slp_bubble_email {
  display: block;
}

.slp_info_bubble a.avalon-email {
  overflow-wrap: anywhere;
}
"""


# ===========================================================================
# 4. avalon-hours.js
# ===========================================================================

JS_HEAD_OLD = """ * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4.
"""
JS_HEAD_NEW = """ * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4b.
"""

JS_WORKS_OLD = """ * No dependencies. Works on whatever [data-avalon-hours] blocks are in the
 * page when it starts (the store page) and on those SLP inserts into
 * #map_sidebar after each search (the result cards). Recomputes on every
"""
JS_WORKS_NEW = """ * No dependencies. Works on whatever [data-avalon-hours] blocks are in the
 * page when it starts (the store page), on those SLP inserts into
 * #map_sidebar after each search (the result cards), and on the one a map
 * pin's info bubble brings into #map (Part 4b). Recomputes on every
"""

JS_FUNCS_ANCHOR = """  function boot() {
"""
JS_FUNCS_NEW = """  /**
   * Part 4b. Whether a batch of DOM changes brought in an hours block.
   * Google redraws map tiles inside #map all the time; only a batch that
   * added an hours block - the info bubble opening - is worth a scan.
   */
  function added(records) {
    for (var i = 0; records && i < records.length; i++) {
      var nodes = records[i].addedNodes;
      for (var j = 0; nodes && j < nodes.length; j++) {
        var n = nodes[j];
        if (n && n.nodeType === 1 &&
            (n.hasAttribute("data-avalon-hours") || n.querySelector("[data-avalon-hours]"))) {
          return true;
        }
      }
    }
    return false;
  }

  /** `el` or its nearest ancestor carrying class `name`, or null. */
  function up(el, name) {
    for (var n = el; n && n.nodeType === 1; n = n.parentNode) {
      if ((" " + n.className + " ").indexOf(" " + name + " ") >= 0) {
        return n;
      }
    }
    return null;
  }

  /**
   * Part 4b. An hours block's week opened inside the map's info bubble.
   * Google pans a bubble into view when it opens, not when its content
   * grows, and the bubble grows upward from its pin: the opened week can
   * reach past the map's top edge. Pan the map down by that much and 8 px
   * more. Only on opening, only for an hours block in a bubble, and only
   * when SLP's map is there to pan (cslmap.gmap, Google's panBy).
   */
  function lift(e) {
    var d = e && e.target;
    if (!d || d.open !== true || !up(d, "avalon-hours")) {
      return;
    }
    var iw = up(d, "gm-style-iw-c");
    var box = iw ? up(iw, "gm-style") : null;
    var map = root.cslmap && root.cslmap.gmap;
    if (!box || !map || typeof map.panBy !== "function") {
      return;
    }
    var gap = iw.getBoundingClientRect().top - box.getBoundingClientRect().top - 8;
    if (gap < 0) {
      map.panBy(0, Math.floor(gap));
    }
  }

  function boot() {
"""

JS_BOOT_OLD = """    if (root.addEventListener) {
      root.addEventListener("pageshow", wake, false);
    }
"""
JS_BOOT_NEW = """    var map = doc.getElementById("map");
    if (map && root.MutationObserver) {
      new root.MutationObserver(function (records) {
        if (added(records)) {
          scan(map);
        }
      }).observe(map, { childList: true, subtree: true });
    }
    doc.addEventListener("toggle", function (e) {
      try {
        lift(e);
      } catch (x) {
        /* The week stays open; the map just does not move. */
      }
    }, true);
    if (root.addEventListener) {
      root.addEventListener("pageshow", wake, false);
    }
"""

JS_EXPORT_OLD = """    enhance: enhance,
    scan: scan
  };
"""
JS_EXPORT_NEW = """    enhance: enhance,
    scan: scan,
    added: added,
    lift: lift
  };
"""


def build_class(php):
    braces = (php.count('{'), php.count('}'))
    check(all(ord(c) < 128 for c in BUBBLE_BLOCK + WIRE_BLOCK), 'the inserted PHP is pure ASCII')
    check('\t' not in BUBBLE_BLOCK + WIRE_BLOCK, 'the inserted PHP has no tabs')
    php = sub_once(php, WIRE_ANCHOR, WIRE_BLOCK, 'class: email and bubble-layout registrations')
    php = sub_once(php, INSERT_BEFORE, BUBBLE_BLOCK + INSERT_BEFORE, 'class: the Part 4b bubble block')
    check("\r\n" in php and php.count("\n") == php.count("\r\n") and php.count("\r") == php.count("\r\n"),
          'class: pure CRLF - no bare LF, no bare CR')
    for m in NEW_METHODS:
        check(php.count(m) == 1, 'class: method declared once: ' + m.split('(')[0].split()[-1])
    for reg in ("add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_email'), 25, 1);",
                "add_filter('slp_js_options', array(self::$instance,'avalon_js_options_bubble'), 100, 1);",
                "add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));"):
        check(php.count(reg) == 1, 'class: registered once: ' + reg.split("'")[3])
    # Part 4's own registrations are still there, once each.
    for reg in ("add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_labels'), 20, 1);",
                "add_filter('slp_js_options', array(self::$instance,'avalon_js_options_layout'), 100, 1);",
                "add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout'), 100, 1);"):
        check(php.count(reg) == 1, 'class: Part 4 still registers ' + reg.split("'")[3])
    a = php.find('        /**\r\n         * v0.0.27 Part 4b. The info bubble shows what the card shows.')
    p4 = php.find('        /**\r\n         * v0.0.27 Part 4. Showing the hours.')
    b = php.find('        public function avalon_rest_protected_slugs(){')
    check(0 < p4 < a < b, "class: Part 4b's block sits after Part 4's, directly before avalon_rest_protected_slugs()")
    block = php[a:b]
    code = re.sub(r'/\*.*?\*/', '', block, flags=re.S)
    for banned in ('$wpdb', 'wp_remote_', 'googleapis', 'openNow'):
        check(banned not in code, 'class: the bubble block code never mentions ' + banned)
    check('preg_replace' not in code, 'class: the bubble block inserts by offset, never through a replacement string')
    check(php.count('{') - braces[0] == php.count('}') - braces[1], 'class: the braces added balance')
    return php


def build_css(css):
    css = sub_once(css, CSS_HEAD_OLD, CSS_HEAD_NEW, 'css: the header - Part 4b, colours, links')
    css = sub_once(css, CSS_VAR_OLD, CSS_VAR_NEW, 'css: Closed takes the primary colour')
    css = sub_once(css, CSS_LINK_OLD, CSS_LINK_NEW, 'css: tel: and mailto: links; the bubble lines')
    check('\r' not in css and all(ord(c) < 128 for c in css), 'css: LF and ASCII')
    check(css.count('{') == css.count('}'), 'css: braces balance')
    check('#f28b82' not in css, "css: Google's red is gone")
    check(css.count('var(--e-global-color-primary, var(--primary-color, #e7167c))') == 3,
          'css: the primary colour, with its two fallbacks, three times - Closed, hover, keyboard focus')
    check('text-decoration: underline;' in css and css.count('text-decoration: underline;') == 1,
          'css: one underline left - the attribution link, not the tel: link')
    return css


def build_js(js):
    js = sub_once(js, JS_HEAD_OLD, JS_HEAD_NEW, 'js: the header names Part 4b')
    js = sub_once(js, JS_WORKS_OLD, JS_WORKS_NEW, 'js: the header names the bubble')
    js = sub_once(js, JS_FUNCS_ANCHOR, JS_FUNCS_NEW, 'js: added(), up(), lift() before boot()')
    js = sub_once(js, JS_BOOT_OLD, JS_BOOT_NEW, 'js: boot() watches #map and listens for toggle')
    js = sub_once(js, JS_EXPORT_OLD, JS_EXPORT_NEW, 'js: added and lift exported for the suite')
    check('\r' not in js and all(ord(c) < 128 for c in js), 'js: LF and ASCII')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')'), 'js: braces and parentheses balance')
    code = re.sub(r'/\*.*?\*/', '', js, flags=re.S)
    code = re.sub(r'(?m)^\s*//.*$', '', code)
    for banned in ('Intl.', 'openNow', '.getHours(', '.getDay(', '.getMinutes(', 'toLocale'):
        check(banned not in code, 'js: the code (comments aside) still never uses ' + banned)
    return js


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part4b.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part4b  slp_avalon 0.0.27  PART 4b (the info bubble, two colours)")
    print("")

    texts = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                     "       src_dir must hold the PART 4 OUTPUT.".format(name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        texts[name] = text
    print("")

    out = {
        'class.slp_avalon.php': build_class(texts['class.slp_avalon.php']),
        'avalon-hours.css':     build_css(texts['avalon-hours.css']),
        'avalon-hours.js':      build_js(texts['avalon-hours.js']),
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
    print("  note      slp_avalon.php is not an input; the header reads 0.0.27.")
    print("  note      no schema change. HOURS_DB_VERSION stays 2.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
