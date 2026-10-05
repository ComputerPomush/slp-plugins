#!/usr/bin/env python3
"""
build-v027-part4c.py

slp_avalon v0.0.27, PART 4c - cards and bubbles laid out for phones, and the
dealer bubble's behaviour on the map.

WHAT THIS RELEASE DOES
----------------------
Asked for on 2026-10-04 and settled on 2026-10-05, after Part 4b went live
on Aura DEV:

1. THE ADDRESS ON TWO LINES, on the result cards and in the bubble, at every
   width: the street, then "City, ST ZIP" - Canada in the same order - the
   second line starting under the first. No country for the United States
   or Canada. A state or province the feed spells out is shown as its
   two-letter code (on screen only). A new marker field,
   avalon_address_html, on slp_results_marker_data at 30; the address run
   of both layouts becomes that one field at 110, on
   slp_javascript_results_string and slp_js_options - and the Address:
   label Part 4's callback at 100 puts back in front of it is taken out.

2. PHONES. At Elementor's mobile breakpoint: 15 px text (the bubble's
   email line 14 px under 375 px), 20 px names, the bubble widened to the
   map's width less 24 px, at most 376 px - an open bubble widened again
   when the phone turns - and the five labels - Distance:, Address:,
   Phone:, Email:, Hours: - drawn as Font Awesome icons once the page's own
   Font Awesome has loaded. Each label names its kind in a modifier class
   (avalon-label--phone and so on). Everywhere, the Hours: line balanced
   where it has to wrap, so its caret never stands alone.

3. THE BUBBLE ON THE MAP (slp_avalon.js). The five hover rules the owner
   agreed; focus to Contact Dealer when a click, a tap or a key opens it,
   none when a hover does, and none when focus has moved on by then - into
   the Contact Dealer form, or anywhere else; the dialog named for the
   dealer; the hovered pin in the hover icon and raised; the map's controls
   as on a store page - zoom, Map and Satellite, Street View, full screen,
   no camera control; Contact Dealer on a map shown full screen leaves full
   screen first, so its form can be seen.

4. THE PEGMAN. Google's images inside the locator's map keep their own
   sizes; Elementor's img { max-width: 100% } squeezed the Street View
   control's Pegman to 0 px.

Not in this release, by the owner's decision: fitting the map to the
results, and a Map Reset button.

WHAT IS PATCHED
---------------
class.slp_avalon.php: four registrations appended after Part 4b's, four
labels given their modifier class (Part 4's Phone:, Address: and Hours:,
Part 4b's Email:), and one block of methods inserted directly before
avalon_rest_protected_slugs() - after Part 4b's block. Nothing else moves.
avalon-hours.css: the header, and one block appended at the end.
slp_avalon.js: two anchored edits - cslmap_build_map() and
enable_on_mouse_hover_for_markers(), which now carries the avalon_map
block after it. avalon-hours.js is not touched.

ENCODING
--------
The class and slp_avalon.js are CRLF and stay CRLF (ISO-8859-1, the CR
count printed). avalon-hours.css is LF ASCII and stays so. Every inserted
line is pure ASCII.

Usage:  python build-v027-part4c.py <src_dir> <out_dir>

        src_dir must hold:
            class.slp_avalon.php   da3bd46a6af3b34a9552f6b1119dc13f  312186  (Part 4b)
            avalon-hours.css       1fd4083289b335140a0196bf62e54605    8374  (Part 4b)
            slp_avalon.js          95c1ab2471359c2e080dc8477e1981fd   76976  (v0.0.25)
        and it writes the three files, or refuses to.
"""

import hashlib
import io
import os
import re
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('da3bd46a6af3b34a9552f6b1119dc13f', 312186),
    'avalon-hours.css':     ('1fd4083289b335140a0196bf62e54605', 8374),
    'slp_avalon.js':        ('95c1ab2471359c2e080dc8477e1981fd', 76976),
}

# The outputs this script was reviewed against. A build that produces other
# bytes is refused before anything is written.
OUT_PINS = {
    'class.slp_avalon.php': ('12cde985f071d0e77d2ca1acf935d426', 330544),
    'avalon-hours.css':     ('d24269039747a7d04c7ce931ef02c2ec', 15316),
    'slp_avalon.js':        ('717a21bdb5b8f416a73da69b70b6b8d3', 101783),
}


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def crlf(s):
    """Source blocks below are written with LF; the class and slp_avalon.js are CRLF."""
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
# 1. The class: four registrations, four labels, one block
# ===========================================================================

WIRE_ANCHOR = crlf("""            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));
""")
WIRE_BLOCK = crlf("""            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));
            //
            // v0.0.27 Part 4c. The address on two lines; labels that can be
            // icons; the hover pin's URL for slp_avalon.js.
            //
            // The address field onto every marker at 30, after Part 4's
            // labels at 20 and Part 4b's email at 25: it reads the marker's
            // own address values, as SLP Experience left them at 15. The
            // layouts at 110, after Part 4's and 4b's callbacks at 100 have
            // put their fields in: the address run becomes the one field,
            // and "Distance:" a label. The new selectors onto WP Rocket's
            // safelist; a no-op where WP Rocket is not installed.
            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_address'), 30, 1);
            add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout_address'), 110, 1);
            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_map'), 110, 1);
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_map'));
""")

# The four labels: [old, new, what]. Each anchor occurs once in Part 4b.
LABELS = [
    ("""                $marker['avalon_phone_html'] = '<b class="avalon-label">Phone:</b> '""",
     """                $marker['avalon_phone_html'] = '<b class="avalon-label avalon-label--phone">Phone:</b> '""",
     'Phone:'),
    ("""                $marker['avalon_address_label'] = '<b class="avalon-label">Address:</b> ';""",
     """                $marker['avalon_address_label'] = '<b class="avalon-label avalon-label--address">Address:</b> ';""",
     'Address:'),
    ("""                     . ( $card ? '<b class="avalon-label">Hours:</b> ' : '' )""",
     """                     . ( $card ? '<b class="avalon-label avalon-label--hours">Hours:</b> ' : '' )""",
     'Hours:'),
    ("""            $marker['avalon_email_html'] = '<b class="avalon-label">Email:</b> '""",
     """            $marker['avalon_email_html'] = '<b class="avalon-label avalon-label--email">Email:</b> '""",
     'Email:'),
]

INSERT_BEFORE = crlf("""        public function avalon_rest_protected_slugs(){
""")

MAP_BLOCK = crlf(r"""        /**
         * v0.0.27 Part 4c. Cards and bubbles laid out for phones.
         *
         * Asked for on 2026-10-04 and settled on 2026-10-05, after Part 4b
         * went live on Aura DEV (the owner's words are in the rev46
         * addendum):
         *
         *   - the address on two aligned lines, on the result cards and in
         *     the bubble, at every width: the street, then "City, ST ZIP";
         *     Canada in the same order (the owner's choice)
         *   - no country for the United States or Canada; any other
         *     country after the postal code
         *   - the state or province as its two-letter code where the feed
         *     spells it out (14 of Aura's 313 rows), on screen only: the
         *     feed, the locator row and the address key are untouched
         *   - on phones, the five labels as Font Awesome icons
         *     (avalon-hours.css), so each label now names its kind in a
         *     modifier class: avalon-label--distance, --address, --phone,
         *     --email, --hours
         *
         * How the bubble behaves on the map - opening, closing, where focus
         * goes, the hovered pin, the map's controls - is slp_avalon.js's.
         * What it needs from here is one setting: the hover pin's URL, in
         * the script options as avalon_map_hover_icon.
         *
         * Every layout step finds its own anchor and is skipped, not forced,
         * when the anchor is absent or its field is already there, so a
         * layout this does not recognise is left as it was and a second
         * pass changes nothing. Every insertion is made by offset, never
         * through a regex replacement string.
         */

        /**
         * v0.0.27 Part 4c. A state or province as shown: its two-letter
         * code when the address key knows the name, else as given.
         *
         * The address key's own normaliser (SLP_Avalon_AddressKey::
         * norm_state) maps the 51 US and 16 Canadian names it carries -
         * "Ontario" and "Ont" to ON, "Quebec" to QC - and passes a known
         * code through, in any case and with its dots and stray spaces
         * gone ("n.y. " is NY). A name it does not know, a Mexican state
         * say, comes back as the feed wrote it. Two letters it does not
         * know come back as those letters upper-cased - "Xx" as XX - the
         * form a state code takes.
         */
        public static function avalon_display_state( $state ){
            $state = (string) $state;
            if ( '' === $state || ! class_exists( 'SLP_Avalon_AddressKey' ) ) {
                return $state;
            }
            $code = SLP_Avalon_AddressKey::norm_state( $state );
            return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : $state;
        }

        /**
         * v0.0.27 Part 4c. The address as two lines, as a marker field.
         *
         *   line 1  the street, and address2 after a comma
         *   line 2  "City, ST ZIP" - SLP's own city_state_zip order and
         *           punctuation (SLP_Location_Utilities::
         *           create_city_state_zip()), with the state as
         *           avalon_display_state() shows it; then the country,
         *           unless it is the United States or Canada
         *
         * Read from the marker's own values, as SLP built them and SLP
         * Experience left them at 15 - its show_country empties the
         * country - decoded, then escaped once here. A no-break space
         * counts as a space. A line that comes out empty is left out, and
         * with no line at all there is no field: never "Address:" over
         * nothing. Like Part 4's fields it is a string, as SLP's
         * replace_shortcodes() needs (s0.277).
         */
        public static function avalon_address_fields( $marker ){
            $val = array();
            foreach ( array( 'address', 'address2', 'city', 'state', 'zip', 'country' ) as $k ) {
                $val[ $k ] = ( isset( $marker[ $k ] ) && is_scalar( $marker[ $k ] ) )
                    ? trim( str_replace( "\xC2\xA0", ' ',
                            html_entity_decode( (string) $marker[ $k ], ENT_QUOTES, 'UTF-8' ) ) )
                    : '';
            }
            $state = self::avalon_display_state( $val['state'] );

            $one = $val['address'];
            if ( '' !== $val['address2'] ) {
                $one .= ( '' !== $one ? ', ' : '' ) . $val['address2'];
            }

            $two = '';
            if ( '' !== $val['city'] ) {
                $two = $val['city'] . ( '' !== $state ? ',' : '' )
                     . ( ( '' !== $state || '' !== $val['zip'] ) ? ' ' : '' );
            }
            if ( '' !== $state ) {
                $two .= $state . ( '' !== $val['zip'] ? ' ' : '' );
            }
            $two .= $val['zip'];
            if ( '' !== $val['country'] && class_exists( 'SLP_Avalon_AddressKey' )
                 && ! in_array( SLP_Avalon_AddressKey::norm_country( $val['country'], '', '' ), array( 'US', 'CA' ), true ) ) {
                $two .= ( '' !== $two ? ' ' : '' ) . $val['country'];
            }

            $lines = '';
            foreach ( array( $one, $two ) as $line ) {
                $line = esc_html( $line );
                if ( '' !== $line ) {
                    $lines .= '<span class="avalon-address__line">' . $line . '</span>';
                }
            }
            if ( '' === $lines ) {
                return $marker;
            }
            $marker['avalon_address_html'] = '<b class="avalon-label avalon-label--address">Address:</b> '
                . '<span class="avalon-address__lines">' . $lines . '</span>';
            return $marker;
        }

        /**
         * v0.0.27 Part 4c. The two-line address, onto every marker.
         *
         * On slp_results_marker_data at 30, after Part 4's labels at 20 and
         * Part 4b's email at 25, for the reason they are there: both marker
         * builders apply it, so the field is wherever SLP renders a card or
         * a bubble. No read.
         */
        public function avalon_marker_address( $marker ){
            return is_array( $marker ) ? self::avalon_address_fields( $marker ) : $marker;
        }

        /**
         * v0.0.27 Part 4c. "Distance:" in a layout, made a label.
         *
         * The card's results layout and Part 4b's bubble line both write
         * "Distance:" as plain text at the start of their span; as a label
         * it can be an icon on a phone like the other four. Skipped when
         * the span is absent, when it does not start with exactly
         * "Distance:", or when the label is already there.
         */
        public static function avalon_layout_distance_label( $layout, $class ){
            $layout = (string) $layout;
            if ( false !== strpos( $layout, 'avalon-label--distance' )
                 || ! preg_match( '/<span\b[^>]*\sclass="[^"]*\b' . preg_quote( $class, '/' ) . '\b[^"]*"[^>]*>\s*(Distance:)/',
                                  $layout, $m, PREG_OFFSET_CAPTURE ) ) {
                return $layout;
            }
            return substr_replace( $layout, '<span class="avalon-label avalon-label--distance">Distance:</span>',
                                   $m[1][1], strlen( $m[1][0] ) );
        }

        /**
         * v0.0.27 Part 4c. One address span made the two-line field.
         *
         * $tag finds the span's opening tag. Its content must be the street
         * field alone - [slp_location address ...], with Part 4's Address:
         * label before it or not, and white space round either - or the
         * span is left as it was. The span keeps its attributes and gains
         * the class avalon-address.
         */
        private static function avalon_layout_address_span( $layout, $tag ){
            if ( ! preg_match( $tag, $layout, $t, PREG_OFFSET_CAPTURE )
                 || ! preg_match( '/\G\s*(?:\[slp_location avalon_address_label\]\s*)?\[slp_location\s+address\b[^\]]*\]\s*<\/span>/',
                                  $layout, $c, 0, $t[0][1] + strlen( $t[0][0] ) ) ) {
                return $layout;
            }
            $open = $t[0][0];
            if ( preg_match( '/\sclass="([^"]*)"/', $open, $k, PREG_OFFSET_CAPTURE ) ) {
                $open = substr_replace( $open, ' avalon-address', $k[1][1] + strlen( $k[1][0] ), 0 );
            } else {
                $open = substr( $open, 0, -1 ) . ' class="avalon-address">';
            }
            return substr_replace( $layout, $open . '[slp_location avalon_address_html]</span>',
                                   $t[0][1], strlen( $t[0][0] ) + strlen( $c[0] ) );
        }

        /**
         * v0.0.27 Part 4c. The first span $re finds, and the white space
         * before it, taken out of a layout; the layout as it was when $re
         * finds none.
         */
        private static function avalon_layout_drop_span( $layout, $re ){
            if ( preg_match( $re, $layout, $m, PREG_OFFSET_CAPTURE ) ) {
                $layout = substr_replace( $layout, '', $m[0][1], strlen( $m[0][0] ) );
            }
            return $layout;
        }

        /**
         * v0.0.27 Part 4c. The two-line address and the Distance: label,
         * into the results layout - the cards.
         *
         *   Distance:  wrapped as a label in the location_distance span
         *   Address:   the street span carries the two-line field
         *   the rest   the address2, city_state_zip and country spans, each
         *              holding nothing but its own field, go - but only
         *              once the field is in, so a layout whose street span
         *              this does not recognise keeps its city
         *
         * On slp_javascript_results_string and, through
         * avalon_js_options_map(), on slp_js_options, both at 110: after
         * Part 4's fields went in at 100, so its Address: label is there to
         * be replaced, and after SLP Experience at 90. A Part 4 hours slot
         * after the city line (SLP's default layout) stays where it is.
         *
         * ADDRESS: ONCE. SLP runs slp_javascript_results_string inside its
         * own slp_js_options callback at 10 (add_to_js_options() calls
         * set_ResultsLayout( false, true )), so the layout reaches Part 4's
         * slp_js_options callback at 100 with the field already in. That
         * callback finds no Address: label and puts one back, at the start
         * of the street span - straight before the field, which carries its
         * own. Wherever the field is, a label straight before it is taken
         * out again here.
         */
        public function avalon_results_layout_address( $layout ){
            $layout = self::avalon_layout_distance_label( (string) $layout, 'location_distance' );
            if ( false === strpos( $layout, 'avalon_address_html' ) ) {
                $layout = self::avalon_layout_address_span( $layout,
                    '/<span\b[^>]*\sclass="[^"]*\bslp_result_street\b[^"]*"[^>]*>/' );
            }
            if ( false !== strpos( $layout, 'avalon_address_html' ) ) {
                $layout = str_replace( '[slp_location avalon_address_label][slp_location avalon_address_html]',
                                       '[slp_location avalon_address_html]', $layout );
                foreach ( array( 'slp_result_street2' => 'address2', 'slp_result_citystatezip' => 'city_state_zip',
                                 'slp_result_country' => 'country' ) as $class => $field ) {
                    $layout = self::avalon_layout_drop_span( $layout,
                        '/\s*<span\b[^>]*\sclass="[^"]*\b' . $class . '\b[^"]*"[^>]*>\s*\[slp_location\s+'
                        . $field . '\b[^\]]*\]\s*<\/span>/' );
                }
            }
            return $layout;
        }

        /**
         * v0.0.27 Part 4c. The same, into the bubble layout.
         *
         * Part 4b's Distance: line and SLP's slp_bubble_address span - the
         * ids SLP's default layout and Aura's both carry. The address2,
         * city, state, zip and country spans go once the field is in; the
         * country span may be SLP's own pair, one inside the other. As on
         * the cards, an Address: label straight before the field - Part
         * 4b's, put back by a second pass over a layout that already has
         * the field - is taken out.
         */
        public function avalon_bubble_layout_address( $layout ){
            $layout = self::avalon_layout_distance_label( (string) $layout, 'avalon-bubble-distance' );
            if ( false === strpos( $layout, 'avalon_address_html' ) ) {
                $layout = self::avalon_layout_address_span( $layout, '/<span\b[^>]*\sid="slp_bubble_address"[^>]*>/' );
            }
            if ( false !== strpos( $layout, 'avalon_address_html' ) ) {
                $layout = str_replace( '[slp_location avalon_address_label][slp_location avalon_address_html]',
                                       '[slp_location avalon_address_html]', $layout );
                foreach ( array( 'address2', 'city', 'state', 'zip' ) as $field ) {
                    $layout = self::avalon_layout_drop_span( $layout,
                        '/\s*<span\b[^>]*\sid="slp_bubble_' . $field . '"[^>]*>\s*\[slp_location\s+'
                        . $field . '\b[^\]]*\]\s*<\/span>/' );
                }
                $layout = self::avalon_layout_drop_span( $layout,
                    '/\s*<span\b[^>]*\sid="slp_bubble_country"[^>]*>\s*(?:<span\b[^>]*\sid="slp_bubble_country"[^>]*>\s*'
                    . '\[slp_location\s+country\b[^\]]*\]\s*<\/span>|\[slp_location\s+country\b[^\]]*\])\s*<\/span>/' );
            }
            return $layout;
        }

        /**
         * v0.0.27 Part 4c. The hover pin's URL, or ''.
         *
         * The option avalon_map_hover_icon: an http(s) URL, or a path from
         * the site's root (/wp-content/uploads/...), which travels from DEV
         * to LIVE unchanged and which slp_avalon.js resolves against the
         * page. Anything else - empty, protocol-relative, another scheme -
         * is no hover pin: the pin keeps its icon and is only raised.
         */
        public static function avalon_map_hover_icon(){
            $v = trim( (string) get_option( 'avalon_map_hover_icon', '' ) );
            if ( '' === $v || 0 === strpos( $v, '//' )
                 || ( '/' !== $v[0] && ! preg_match( '#^https?://#i', $v ) ) ) {
                return '';
            }
            return (string) esc_url_raw( $v, array( 'http', 'https' ) );
        }

        /**
         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.
         *
         * On slp_js_options at 110, after Part 4's and Part 4b's callbacks
         * at 100 and SLP Experience at 90: whichever results and bubble
         * layouts won are the ones given the two-line address, and the
         * hover pin's URL rides along as avalon_map_hover_icon - always
         * set, '' when there is none. Layouts that are not strings, and
         * options that are not an array, pass through.
         */
        public function avalon_js_options_map( $options ){
            if ( ! is_array( $options ) ) {
                return $options;
            }
            if ( isset( $options['resultslayout'] ) && is_string( $options['resultslayout'] ) ) {
                $options['resultslayout'] = $this->avalon_results_layout_address( $options['resultslayout'] );
            }
            if ( isset( $options['bubblelayout'] ) && is_string( $options['bubblelayout'] ) ) {
                $options['bubblelayout'] = $this->avalon_bubble_layout_address( $options['bubblelayout'] );
            }
            $options['avalon_map_hover_icon'] = self::avalon_map_hover_icon();
            return $options;
        }

        /**
         * v0.0.27 Part 4c. WP Rocket: the new selectors, beside Part 4's and 4b's.
         *
         * Cards, bubbles and the .avalon-fa class all appear only after a
         * search, a click or a script, so Remove Unused CSS never sees
         * them; written from the selector's start as WP Rocket 3.11.0.2
         * and later read them (s0.284). .gm-style covers the map's own
         * images, #map_sidebar the cards' type sizes on a phone.
         */
        public static function avalon_rocket_rucss_safelist_map( $list ){
            $list   = is_array( $list ) ? $list : array();
            $list[] = '(.*).avalon-address(.*)';
            $list[] = '(.*).avalon-fa(.*)';
            $list[] = '(.*)#map_sidebar(.*)';
            $list[] = '(.*).gm-style(.*)';
            return $list;
        }

""")

NEW_METHODS = (
    'public static function avalon_display_state( $state ){',
    'public static function avalon_address_fields( $marker ){',
    'public function avalon_marker_address( $marker ){',
    'public static function avalon_layout_distance_label( $layout, $class ){',
    'private static function avalon_layout_address_span( $layout, $tag ){',
    'private static function avalon_layout_drop_span( $layout, $re ){',
    'public function avalon_results_layout_address( $layout ){',
    'public function avalon_bubble_layout_address( $layout ){',
    'public static function avalon_map_hover_icon(){',
    'public function avalon_js_options_map( $options ){',
    'public static function avalon_rocket_rucss_safelist_map( $list ){',
)


# ===========================================================================
# 2. avalon-hours.css: the header, and one block at the end
# ===========================================================================

CSS_HEAD_OLD = """ * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4b.
"""
CSS_HEAD_NEW = """ * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4c.
"""

CSS_TABLES_OLD = """ * TABLES. Hello Elementor stripes and borders every table, at specificity
 * 0-1-4 (table tbody>tr:nth-child(odd)>td). The rules below are 0-2-3 and
 * up, so they win without !important.
 */
"""
CSS_TABLES_NEW = """ * TABLES. Hello Elementor stripes and borders every table, at specificity
 * 0-1-4 (table tbody>tr:nth-child(odd)>td). The rules below are 0-2-3 and
 * up, so they win without !important.
 *
 * PART 4c, at the end of the file. The address on two lines on the cards
 * and in the bubble; on a phone, 15 px text, 20 px names and the labels as
 * Font Awesome icons; the map's own images - the Street View Pegman - at
 * their own sizes; a keyboard focus ring on the bubble's buttons; the
 * Hours: line balanced where it has to wrap.
 */
"""

CSS_TAIL_OLD = """.slp_info_bubble a.avalon-email {
  overflow-wrap: anywhere;
}
"""
CSS_TAIL_NEW = CSS_TAIL_OLD + r"""
/* --------------------------------------------------- Part 4c: the address */

/* Part 4c. The address on two lines under its label, on the cards and in
   the bubble, at every width: the street, then "City, ST ZIP" (the
   avalon_address_html field). The label and the lines are flex items, so
   the second line starts under the first, not under the label, and a long
   line wraps inside its own column. The gap is about one space of the
   body font, so the address's text lines up with the phone's and the
   email's, which follow their labels after a space. */
.avalon-address {
  display: flex;
  align-items: baseline;
  column-gap: 0.25em;
}

.avalon-address > .avalon-label {
  flex: none;
}

.avalon-address__lines {
  display: block;
  min-width: 0;
}

.avalon-address__line {
  display: block;
}

/* "Distance:" is a label too, for its icon on a phone, but keeps the
   card's plain weight (Part 4b, choice 2). */
.avalon-label--distance {
  font-weight: inherit;
}

/* Hours:, its status and the caret: where the line must wrap - a 320 px
   phone, a tablet's narrow results column - its lines are balanced, so the
   caret never wraps alone onto a line of its own, and the longest status
   (Closed, Opens 12:30 PM Wed) never runs past the card's edge. Where it
   fits, one line as before. Measured 2026-10-05 in Chromium, 128 status
   shapes at 17 widths from 320 to 1024 px, on cards and in the bubble: the
   caret alone 0 times, past the edge 0 times. Wrapping as before, the
   caret stood alone after up to 39 of the 128 on one 860 px card; held to
   one line (nowrap), the longest ran 71 px past a 780 px tablet's card. A
   browser without text-wrap: balance (Safari before 17.5) wraps as
   before. */
.avalon-hours--card .avalon-hours__summary {
  text-wrap: balance;
}

/* ----------------------------------------------- Part 4c: the map itself */

/* Part 4c. Google's images inside the locator's map at their own sizes.
   Elementor's .elementor img { max-width: 100% } (0-1-1) reaches every
   image in the map. SLP's inline div#map img { max-width: none } used to
   answer it, until slp_avalon.js removed that stylesheet with SLP's other
   inline rules, and the Street View control's Pegman has measured 0 px
   wide since (Aura DEV, 2026-10-05; 30 px on a store page's map). This is
   1-1-1. An image in the bubble's content keeps the bubble's width. */
#map .gm-style img {
  max-width: none;
}

#map .gm-style .slp_info_bubble img {
  max-width: 100%;
}

/* Part 4c. A keyboard focus ring on the bubble's two buttons. The theme
   takes the outline off their :focus (style.css: #slp_bubble_website
   a:focus, 1-1-1); this is 1-2-1, on :focus-visible only, so a pointer
   click draws no ring. White, on the black bubble. */
.slp_info_bubble #slp_bubble_directions a:focus-visible,
.slp_info_bubble #slp_bubble_website a:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}

/* ------------------------------------------ Part 4c: phones, portrait */

/* At Elementor's mobile breakpoint the card's and the bubble's text is
   15 px and the dealer's name 20 px. slp_avalon.js widens the bubble there
   to the map's width less 24 px, and at 15 px every one of Aura's 313
   addresses fits its two lines from 320 px up, on the cards and in the
   bubble, and every one of its 97 emails its one line from 375 px up
   (measured 2026-10-05, icons and words). The theme sets 16 px and 24 px
   at 0-4-0 and 1-0-0; these are 1-2-0 and 1-1-0.

   767 px is Elementor's default mobile breakpoint, and Aura's: Part 4's
   hours fold follows a site that moves it (avalon_hours_breakpoint());
   Part 4c's two 767 px blocks here, and slp_avalon.js's, do not. Check the
   breakpoint before Tahoe or Avalon take Part 4c. */
@media (max-width: 767px) {
  #map_sidebar .results_wrapper .location_distance,
  #map_sidebar .results_wrapper .sl_contact__info,
  .slp_info_bubble .sl_popup_contact_info {
    font-size: 15px;
    line-height: 1.5;
  }

  #map_sidebar .results_wrapper .store_locator_name,
  .slp_info_bubble #slp_bubble_name {
    font-size: 20px;
    line-height: 1.2;
  }
}

/* Part 4c. Under 375 px the email address in the bubble is 14 px; its
   label, icon or word, stays as the others. At 15 px on a 360 px phone
   one of Aura's 97 emails wraps with the icons and five with the words;
   at 14 px none do. At 320 px six still wrap, and break inside the
   address (Part 4b) rather than being cut. Measured 2026-10-05. 1-2-1:
   the link sets its own size, under the 15 px it would inherit. */
@media (max-width: 374px) {
  .slp_info_bubble #slp_bubble_email a.avalon-email {
    font-size: 14px;
  }
}

/* Part 4c. On a phone, Font Awesome icons in place of the five labels, the
   owner's request of 2026-10-05: map-marker-alt for Address:, phone-alt
   for Phone:, envelope for Email:, with route for Distance: and clock for
   Hours:. Only under .avalon-fa, which slp_avalon.js puts on <html> once
   the page's own Font Awesome 5 (Elementor's, solid) has loaded; without
   it the words stay. The word stays in the page at font-size 0, for screen
   readers; the icon is generated content with empty alternative text
   (/ ""), and a browser that does not know that syntax drops the second
   content declaration and keeps the first. */
@media (max-width: 767px) {
  .avalon-fa .avalon-label--distance,
  .avalon-fa .avalon-label--address,
  .avalon-fa .avalon-label--phone,
  .avalon-fa .avalon-label--email,
  .avalon-fa .avalon-label--hours {
    display: inline-block;
    width: 24px;
    font-size: 0;
    vertical-align: baseline;
  }

  .avalon-fa .avalon-label--distance::before,
  .avalon-fa .avalon-label--address::before,
  .avalon-fa .avalon-label--phone::before,
  .avalon-fa .avalon-label--email::before,
  .avalon-fa .avalon-label--hours::before {
    display: inline-block;
    width: 24px;
    color: var(--e-global-color-primary, var(--primary-color, #e7167c));
    font: 900 15px/1 "Font Awesome 5 Free";
    text-align: center;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }

  .avalon-fa .avalon-label--distance::before {
    content: "\f4d7";
    content: "\f4d7" / "";
  }

  .avalon-fa .avalon-label--address::before {
    content: "\f3c5";
    content: "\f3c5" / "";
  }

  .avalon-fa .avalon-label--phone::before {
    content: "\f879";
    content: "\f879" / "";
  }

  .avalon-fa .avalon-label--email::before {
    content: "\f0e0";
    content: "\f0e0" / "";
  }

  .avalon-fa .avalon-label--hours::before {
    content: "\f017";
    content: "\f017" / "";
  }
}
"""


# ===========================================================================
# 3. slp_avalon.js: the map's controls and the bubble's rules
# ===========================================================================

JS_MAP_OLD = crlf("""    slp_Filter("map_options").publish(avalon_cslmap.options);
  
    avalon_cslmap.gmap = new google.maps.Map(map_div_id, avalon_cslmap.options);
""")
JS_MAP_NEW = crlf("""    slp_Filter("map_options").publish(avalon_cslmap.options);
    //v0.0.27 Part 4c. The store page's controls, set after the filter so
    //that nothing subscribed to it can take them away again (avalon_map).
    avalon_map.controls(avalon_cslmap.options);
  
    avalon_cslmap.gmap = new google.maps.Map(map_div_id, avalon_cslmap.options);
    //v0.0.27 Part 4c. The bubble's rules, once the map exists.
    avalon_map.attach(avalon_cslmap);
""")

JS_HOVER_OLD = crlf("""  function enable_on_mouse_hover_for_markers() {
    // Delegated so the handler survives SLP replacing the results markup on
    // every search. Namespaced and cleared first: without the .off() these
    // accumulate on document, one generation per search, and all of them fire.
    jQuery(document).off("mouseenter.avalonHover");
    for (let i in avalon_cslmap.markers) {
      let marker = avalon_cslmap.markers[i];
      marker.__gmarker.addListener("mouseover", function () {
        avalon_cslmap.handle_location_result_click({
          data: {
            info: markers_list_natural[i],
            marker: marker,
          },
        });
      });
      //Also add on mouse hover for the sidebar list
      jQuery(document).on(
        "mouseenter.avalonHover",
        "#slp_results_wrapper_" + markers_list_natural[i].id,
        {
          info: markers_list_natural[i],
          marker: marker,
        },
        avalon_cslmap.handle_location_result_click
      );
    }
  }
""")
JS_HOVER_NEW = crlf(r"""  function enable_on_mouse_hover_for_markers() {
    //v0.0.27 Part 4c. The hover rules, the hovered pin and the focus rules
    //live in avalon_map below; this binds them to the markers of the
    //search that has just been drawn. The delegated card handlers are bound
    //once, by avalon_map.attach(), and survive SLP replacing the results
    //markup on every search.
    avalon_map.bind(avalon_cslmap, markers_list_natural);
  }

  /* ==================================================================
   * v0.0.27 Part 4c. The dealer bubble on the map: how it opens and
   * closes, where focus goes, the hovered pin, and the map's controls.
   *
   * Asked for on 2026-10-04 and settled on 2026-10-05; the owner's words
   * are in the rev46 addendum. The five rules, as agreed:
   *
   *   1. Leaving the pin or the bubble closes it after 0.3 s, unless the
   *      pointer has moved onto the other.
   *   2. A click on a pin, a card or anything inside the bubble keeps it
   *      open until another pin is chosen, Esc is pressed, or the map is
   *      clicked - so opening the hours, which moves the map, cannot
   *      close it.
   *   3. Hovering a card opens its bubble, and leaving the card closes it
   *      the same way.
   *   4. On a touch screen a tap opens the bubble and it stays: a tap is a
   *      click.
   *   5. It never closes on its own while keyboard focus is inside it -
   *      and focus moving into it keeps it, as a click inside does.
   *
   * While one bubble is kept open, hovering another pin or card lights that
   * pin and opens nothing: the kept bubble was chosen.
   *
   * OPENING. SLP's show_map_bubble() (slp_core.js 1499-1517) is replaced by
   * show() below, with the same contract - the map_options filter, then
   * setContent(createMarkerContent()) and open() - and three changes:
   *
   *   focus   Google is told shouldFocus: false. A bubble opened by a click,
   *           a tap or a key then moves focus to its Contact Dealer button
   *           (Get Directions when it has none), without scrolling the page,
   *           once that click is done - after the page's own handlers for
   *           it have run; a bubble opened by hovering takes no focus at
   *           all. Google's own guess focused the first link, the phone
   *           (screenshot 4770). Focus stays put when by then it has gone
   *           somewhere else: into the Contact Dealer form, which main.js
   *           opens on that same click when the choice was a card's own
   *           Contact Dealer button, or to anything off the map the visitor
   *           has moved on to. When the bubble closes and focus went with
   *           it, focus returns to where it was - the pin a keyboard opened
   *           it from, or wherever it came into the bubble from.
   *   name    the InfoWindow is a dialog named for the dealer (ariaLabel)
   *   phones  at Elementor's mobile breakpoint the bubble is at least the
   *           map's width less 24 px, and never more than 376 px, the
   *           theme's own width. Google reads minWidth only as a bubble
   *           opens, so a change is close(), setOptions(), open(), as its
   *           reference says - on the next bubble, and on the open one
   *           0.2 s after the window stops resizing (a phone turned):
   *           the same dealer, chosen or not as before, and focus, if it
   *           was in the bubble, back on its Contact Dealer button. Not
   *           while the Contact Dealer form is open over the page: once it
   *           has closed. 767 px is Elementor's default and Aura's; Part
   *           4's hours fold follows a site that moves it, this does not -
   *           check before Tahoe or Avalon take Part 4c.
   *
   * FULL SCREEN. Contact Dealer in a bubble on a map shown full screen
   * leaves full screen first, on the same click: its form opens over the
   * page (main.js, #slp_bubble_website .storelocatorlink), which full
   * screen hides. Google shows no full-screen control on iOS.
   *
   * Every click on a pin or a card reaches show() through SLP's own
   * handlers; only the hover handlers here say otherwise, through st.next.
   * So anything that is not a hover is a choice.
   *
   * THE HOVERED PIN. A pin under the pointer, or whose bubble is open,
   * shows the hover icon - slplus.options.avalon_map_hover_icon, set by
   * slp_avalon - and sits above the other pins. Without the option it is
   * only raised.
   *
   * THE CONTROLS. As on a store page's map (avalon_map_location): zoom, Map
   * and Satellite, Street View, full screen, and no camera control. Set
   * after SLP's map_options filter, so SLP Experience's
   * map_options_mapTypeControl ("0" on Aura) no longer hides Map and
   * Satellite.
   *
   * ICONS ON PHONES. fa() puts .avalon-fa on <html> once Font Awesome 5's
   * solid face has loaded, on the locator's page only; avalon-hours.css
   * draws the labels as icons only under it, so without the font the words
   * stay.
   * ================================================================== */
  var avalon_map = (function () {
    var CLOSE_MS = 300;
    var RESIZE_MS = 200;
    var PHONE = "(max-width: 767px)";
    var WIDEST = 376;
    var MARGIN = 24;

    var st = {
      cm: null,           //SLP's map object, cslmap
      byId: {},           //location id -> { id, marker, info, lit }
      current: null,      //the entry whose bubble is open
      open: false,
      pinned: false,      //chosen, by a click, a tap or a key
      next: null,         //"hover" for the next show() only
      timer: 0,           //the pending close
      rs: 0,              //the pending look at the width, after a resize
      overMarker: null,   //the location id of the pin under the pointer
      overCard: null,     //the location id of the card under the pointer
      overBubble: false,
      minWidth: 0,        //the minWidth the InfoWindow was last given
      quiet: false,       //closing only to reopen at another width
      wantFocus: false,   //move focus in once the content is in the page
      from: null,         //where focus was when the bubble was chosen
      back: null,         //where focus was before it moved in
      icon: null,         //the hover icon, resolved; "" for none
      fa: false
    };

    function has_class(n, name) {
      return (" " + n.className + " ").indexOf(" " + name + " ") >= 0;
    }

    //Inside an InfoWindow - the locator has one, SLP's.
    function inside(el) {
      for (var n = el; n && n.nodeType === 1; n = n.parentNode) {
        if (has_class(n, "gm-style-iw-c")) {
          return true;
        }
      }
      return false;
    }

    function bubble() {
      return document.querySelector("#map .slp_info_bubble");
    }

    function container() {
      for (var n = bubble(); n && n.nodeType === 1; n = n.parentNode) {
        if (has_class(n, "gm-style-iw-c")) {
          return n;
        }
      }
      return null;
    }

    function controls(o) {
      if (o && typeof o === "object") {
        o.cameraControl = false;
        o.zoomControl = true;
        o.mapTypeControl = true;
        o.streetViewControl = true;
        o.fullscreenControl = true;
      }
      return o;
    }

    function hover_icon() {
      if (st.icon === null) {
        var v = "";
        try {
          v = String((slplus.options && slplus.options.avalon_map_hover_icon) || "");
        } catch (x) {
          v = "";
        }
        if (v) {
          //A path from the site's root, resolved against the page.
          var a = document.createElement("a");
          a.href = v;
          v = a.href;
        }
        st.icon = v;
      }
      return st.icon;
    }

    function hovered(e) {
      return st.overMarker === e.id || st.overCard === e.id;
    }

    //A pin lit: the hover icon, above the other pins; unlit: as SLP drew it.
    function lit(e, on) {
      var g = e && e.marker && e.marker.__gmarker;
      if (!g || !!e.lit === !!on) {
        return;
      }
      var url = hover_icon();
      if (on) {
        e.icon = g.getIcon();
        e.z = g.getZIndex();
        if (url) {
          g.setIcon(url);
        }
        g.setZIndex((google.maps.Marker.MAX_ZINDEX || 1000000) + 1);
      } else {
        if (url) {
          g.setIcon(e.icon);
        }
        g.setZIndex(e.z);
      }
      e.lit = !!on;
    }

    function cancel() {
      if (st.timer) {
        clearTimeout(st.timer);
        st.timer = 0;
      }
    }

    //Rules 1, 3 and 5: close a bubble that was not chosen, unless the
    //pointer is back on it, its pin or its card, or focus is inside it.
    function later() {
      if (st.pinned || !st.open) {
        return;
      }
      cancel();
      st.timer = setTimeout(function () {
        st.timer = 0;
        if (st.pinned || !st.open || st.overBubble || (st.current && hovered(st.current)) ||
            inside(document.activeElement)) {
          return;
        }
        close();
      }, CLOSE_MS);
    }

    function min_width(cm) {
      if (!window.matchMedia || !window.matchMedia(PHONE).matches) {
        return 0;
      }
      var div = cm.gmap && typeof cm.gmap.getDiv === "function" ? cm.gmap.getDiv() : null;
      var w = div ? div.clientWidth : 0;
      return w > MARGIN ? Math.min(WIDEST, w - MARGIN) : 0;
    }

    //The dealer's name as text: SLP's marker carries it esc_attr()'d.
    function name_of(info) {
      return String((info && info.name) || "")
        .replace(/&(amp|lt|gt|quot|#0?39);/g, function (m, k) {
          return k === "amp" ? "&" : k === "lt" ? "<" : k === "gt" ? ">" : k === "quot" ? "\"" : "'";
        })
        .replace(/\s+/g, " ")
        .replace(/^ | $/g, "");
    }

    function entry(info, marker) {
      for (var id in st.byId) {
        if (st.byId.hasOwnProperty(id) && st.byId[id].marker === marker) {
          return st.byId[id];
        }
      }
      var e = { id: String(info && info.id !== undefined ? info.id : ""), marker: marker, info: info, lit: false };
      if (e.id !== "") {
        st.byId[e.id] = e;
      }
      return e;
    }

    //On the map: in the bubble, on a pin, the map itself - where a click on
    //a pin may leave focus. Not somewhere else.
    function on_map(el) {
      var g = st.cm && st.cm.gmap;
      var div = g && typeof g.getDiv === "function" ? g.getDiv() : null;
      return inside(el) || !!(div && typeof div.contains === "function" && div.contains(el));
    }

    //Focus to Contact Dealer, else Get Directions. Before Google has put
    //this dealer's content in the page - nothing there yet, or the last
    //dealer's still (slp_info_bubble_<id>) - there is nothing to focus:
    //ready() comes back. Not at all when the Contact Dealer form is open, or
    //when focus has moved on since the choice (st.from) to anything off the
    //map.
    function focus_in() {
      var b = bubble();
      if (!b || (st.current && /^slp_info_bubble_/.test(b.id || "") && b.id !== "slp_info_bubble_" + st.current.id)) {
        return;
      }
      st.wantFocus = false;
      var a = document.activeElement;
      if (document.querySelector(".contact-dealer--pop-up.open-modal") ||
          (a && a !== document.body && a !== st.from && !on_map(a))) {
        return;
      }
      var t = b.querySelector("#slp_bubble_website a") || b.querySelector("#slp_bubble_directions a");
      if (!t) {
        return;
      }
      if (a && a !== document.body && !inside(a)) {
        st.back = a;
      }
      try {
        t.focus({ preventScroll: true });
      } catch (x) {
        //A button that refuses focus leaves focus where it was.
      }
    }

    //Focus moves once the click that chose the bubble is done: after the
    //page's own handlers for it - main.js's Contact Dealer among them - have
    //run, so focus_in() sees the form they opened.
    function soon() {
      setTimeout(function () {
        if (st.wantFocus) {
          focus_in();
        }
      }, 0);
    }

    //Focus back where it came from - but only when it was lost with the
    //bubble, never taken from wherever the visitor has put it since.
    function restore() {
      var back = st.back;
      st.back = null;
      var a = document.activeElement;
      if (!back || (a && a !== document.body && !inside(a))) {
        return;
      }
      if (document.body.contains(back)) {
        try {
          back.focus({ preventScroll: true });
        } catch (x) {
          //As above.
        }
      }
    }

    function clear() {
      var e = st.current;
      cancel();
      st.current = null;
      st.open = false;
      st.pinned = false;
      st.wantFocus = false;
      st.from = null;
      st.overBubble = false;
      if (e) {
        lit(e, hovered(e));
      }
      restore();
    }

    function close() {
      cancel();
      if (st.cm && st.open) {
        st.cm.infowindow.close();
      }
      clear();
    }

    //Google's close event: Esc inside the bubble, its anchor removed, or
    //close() above. Not the close that only reopens it at another width.
    function closed() {
      var iw = st.cm && st.cm.infowindow;
      if (st.quiet || (iw && iw.isOpen === true)) {
        return;
      }
      clear();
    }

    //Google's close() before the bubble reopens at another width: not the
    //visitor's, so closed() lets it pass. Google sends focus back to where
    //it was before the bubble opened (its guide, "Close an info window"),
    //and focusing can scroll the page. Focus that was in the bubble, or on
    //nothing, is let go - for focus_in() to put in the bubble reopened, or
    //to stay on nothing; focus that was elsewhere - the search box, say -
    //is put back there; the page is put back where it was. Says whether
    //focus was in the bubble.
    function reclose(iw) {
      var a = document.activeElement;
      var had = !!a && a !== document.body && inside(a);
      var sx = window.pageXOffset;
      var sy = window.pageYOffset;
      st.quiet = true;
      try {
        iw.close();
      } finally {
        st.quiet = false;
      }
      var now = document.activeElement;
      try {
        if (had || !a || a === document.body) {
          if (now && now !== document.body && typeof now.blur === "function") {
            now.blur();
          }
        } else if (now !== a && document.body.contains(a)) {
          a.focus({ preventScroll: true });
        }
        if (window.pageXOffset !== sx || window.pageYOffset !== sy) {
          window.scrollTo(sx, sy);
        }
      } catch (x) {
        //Refused: focus stays where Google put it.
      }
      return had;
    }

    //SLP's show_map_bubble(), replaced: see the header above.
    function show(info, marker) {
      var cm = st.cm || this;
      var hover = st.next === "hover";
      st.next = null;
      cm.options = { show_bubble: slplus.options.hide_bubble !== "1" };
      slp_Filter("map_options").publish(cm.options);
      if (!cm.options.show_bubble || !marker || !marker.__gmarker) {
        return;
      }
      var e = entry(info, marker);
      var iw = cm.infowindow;
      cancel();
      if (!hover) {
        st.from = document.activeElement;
      }
      if (!(st.open && st.current === e)) {
        var prev = st.current;
        var width = min_width(cm);
        var opts = { ariaLabel: name_of(info) };
        if (width !== st.minWidth) {
          if (st.open) {
            reclose(iw);
          }
          opts.minWidth = width;
          st.minWidth = width;
        }
        iw.setOptions(opts);
        iw.setContent(cm.createMarkerContent(info));
        iw.open({ map: cm.gmap, anchor: marker.__gmarker, shouldFocus: false });
        st.current = e;
        st.open = true;
        st.pinned = false;
        st.overBubble = false;
        if (prev && prev !== e) {
          lit(prev, hovered(prev));
        }
        lit(e, true);
        st.wantFocus = !hover;
      } else if (!hover) {
        st.wantFocus = true;
        soon();
      }
      if (!hover) {
        st.pinned = true;
      }
    }

    //The window has stopped resizing - a phone turned, say. An open bubble
    //whose minWidth no longer fits the map is reopened at the new one, as
    //show() does: the same dealer, chosen or not as before. Focus that was
    //in it - lost as Google takes the bubble out - goes back to its Contact
    //Dealer once ready() has it in the page again. A map not laid out
    //(0 px wide) is left alone. Under the Contact Dealer form it waits:
    //dealer-popup-focus.js gives focus back, as the form closes, to the
    //link that opened it, which a reopen would take out of the page - and
    //it falls back to the search box. So it looks again until the form
    //has closed.
    function resized() {
      st.rs = 0;
      var cm = st.cm;
      var e = st.current;
      if (!cm || !st.open || !e || !e.marker || !e.marker.__gmarker) {
        return;
      }
      var div = cm.gmap && typeof cm.gmap.getDiv === "function" ? cm.gmap.getDiv() : null;
      if (!div || !div.clientWidth) {
        return;
      }
      var width = min_width(cm);
      if (width === st.minWidth) {
        return;
      }
      if (document.querySelector(".contact-dealer--pop-up.open-modal")) {
        st.rs = setTimeout(resized, RESIZE_MS);
        return;
      }
      var iw = cm.infowindow;
      if (reclose(iw)) {
        st.wantFocus = true;
      }
      iw.setOptions({ minWidth: width });
      st.minWidth = width;
      iw.open({ map: cm.gmap, anchor: e.marker.__gmarker, shouldFocus: false });
    }

    //Contact Dealer - main.js's #slp_bubble_website .storelocatorlink -
    //clicked on a map shown full screen: full screen ends first, or the form
    //main.js opens over the page stays hidden behind it.
    function windowed(t) {
      var d = document;
      var link = null;
      var n = t;
      if (!(d.fullscreenElement || d.webkitFullscreenElement)) {
        return;
      }
      for (; n && n.nodeType === 1; n = n.parentNode) {
        if (!link && n.tagName === "A") {
          link = n;
        }
        if (n.id === "slp_bubble_website") {
          break;
        }
      }
      if (!link || !has_class(link, "storelocatorlink") || !n || n.nodeType !== 1) {
        return;
      }
      try {
        var p = d.exitFullscreen ? d.exitFullscreen() : d.webkitExitFullscreen();
        if (p && typeof p.then === "function") {
          p.then(null, function () {
            //Still full screen: the form opens behind it, as before.
          });
        }
      } catch (x) {
        //As above.
      }
    }

    //The InfoWindow's content is in the page: watch the pointer on the
    //bubble, keep it open on any click inside it - in the capture phase,
    //before avalon-hours.js stops the hours' own clicks - or on focus
    //moving into it, noting where focus came in from; and move focus in if
    //a choice opened it.
    function ready() {
      var c = container();
      if (c && !c.avalonMap) {
        c.avalonMap = true;
        c.addEventListener("mouseenter", function () {
          st.overBubble = true;
          cancel();
        }, false);
        c.addEventListener("mouseleave", function () {
          st.overBubble = false;
          later();
        }, false);
        c.addEventListener("click", function (ev) {
          if (st.open) {
            st.pinned = true;
            cancel();
          }
          windowed(ev.target);
        }, true);
        c.addEventListener("focusin", function (ev) {
          var from = ev && ev.relatedTarget;
          if (st.open) {
            st.pinned = true;
            cancel();
          }
          if (!st.back && from && from.nodeType === 1 && from !== document.body && !inside(from)) {
            st.back = from;
          }
        }, false);
      }
      if (st.wantFocus) {
        soon();
      }
    }

    function enter(e, kind) {
      if (!e) {
        return;
      }
      if (kind === "card") {
        st.overCard = e.id;
      } else {
        st.overMarker = e.id;
      }
      lit(e, true);
      if (st.open && st.current === e) {
        cancel();
      } else if (!st.pinned) {
        cancel();
        st.next = "hover";
        show(e.info, e.marker);
      }
    }

    function leave(e, kind) {
      if (!e) {
        return;
      }
      if (kind === "card") {
        if (st.overCard === e.id) {
          st.overCard = null;
        }
      } else if (st.overMarker === e.id) {
        st.overMarker = null;
      }
      if (st.open && st.current === e) {
        later();
      } else {
        lit(e, hovered(e));
      }
    }

    function card(el) {
      var id = String((el && el.id) || "").replace(/^slp_results_wrapper_/, "");
      return id !== "" && st.byId.hasOwnProperty(id) ? st.byId[id] : null;
    }

    //Esc closes the bubble - unless the Contact Dealer form is open over
    //the page, whose own Esc (dealer-popup-focus.js) comes first.
    function key(ev) {
      if ((ev.key !== "Escape" && ev.key !== "Esc" && ev.keyCode !== 27) || !st.open || ev.defaultPrevented ||
          document.querySelector(".contact-dealer--pop-up.open-modal")) {
        return;
      }
      close();
    }

    //Once, when the map is built: SLP's bubble replaced, and the listeners
    //that outlive every search.
    function attach(cm) {
      if (!cm || !cm.gmap || !cm.infowindow || st.cm === cm) {
        return;
      }
      st.cm = cm;
      cm.show_map_bubble = show;
      google.maps.event.addListener(cm.infowindow, "domready", ready);
      google.maps.event.addListener(cm.infowindow, "close", closed);
      google.maps.event.addListener(cm.gmap, "click", function () {
        close();
      });
      document.addEventListener("keydown", key, false);
      if (window.addEventListener) {
        window.addEventListener("resize", function () {
          clearTimeout(st.rs);
          st.rs = setTimeout(resized, RESIZE_MS);
        }, false);
      }
      jQuery(document)
        .off(".avalonMap")
        .on("mouseenter.avalonMap", "#map_sidebar .results_wrapper", function () {
          enter(card(this), "card");
        })
        .on("mouseleave.avalonMap", "#map_sidebar .results_wrapper", function () {
          leave(card(this), "card");
        });
    }

    function info_of(list, m, i) {
      if (!list || !m) {
        return null;
      }
      for (var j = 0; j < list.length; j++) {
        if (list[j] && String(list[j].id) === String(m.__location_id)) {
          return list[j];
        }
      }
      return list[i] || null;
    }

    function hook(e) {
      google.maps.event.addListener(e.marker.__gmarker, "mouseover", function () {
        enter(e, "marker");
      });
      google.maps.event.addListener(e.marker.__gmarker, "mouseout", function () {
        leave(e, "marker");
      });
    }

    //Each search: the old bubble closed, the new pins hooked, by location
    //id - SLP's own order only when an id is missing.
    function bind(cm, list) {
      attach(cm);
      cancel();
      if (st.open && st.cm) {
        st.cm.infowindow.close();
      }
      clear();
      st.overMarker = null;
      st.overCard = null;
      st.byId = {};
      var markers = (cm && cm.markers) || [];
      for (var i = 0; i < markers.length; i++) {
        var m = markers[i];
        var info = info_of(list, m, i);
        if (!m || !m.__gmarker || !info) {
          continue;
        }
        var e = { id: String(info.id), marker: m, info: info, lit: false };
        st.byId[e.id] = e;
        hook(e);
      }
      if (hover_icon() && typeof Image === "function") {
        new Image().src = hover_icon();
      }
    }

    //On the locator's page, once: .avalon-fa when Font Awesome's solid face
    //loads. fonts.load() resolves with the faces that matched - none when
    //the page has no such face, which fonts.check() would call loaded.
    function fa() {
      var d = document;
      if (st.fa || !d.getElementById("map_sidebar")) {
        return;
      }
      st.fa = true;
      if (!d.fonts || typeof d.fonts.load !== "function") {
        return;
      }
      try {
        d.fonts.load('900 16px "Font Awesome 5 Free"', "\uf3c5").then(function (faces) {
          if (faces && faces.length) {
            d.documentElement.classList.add("avalon-fa");
          }
        }, function () {
          //No icon font: the labels keep their words.
        });
      } catch (x) {
        //As above.
      }
    }

    return {
      controls: controls,
      attach: attach,
      bind: bind,
      show: show,
      close: close,
      fa: fa,
      state: st
    };
  })();
  jQuery(function () {
    avalon_map.fa();
  });
""")


def build_class(php):
    braces = (php.count('{'), php.count('}'))
    check(all(ord(c) < 128 for c in MAP_BLOCK + WIRE_BLOCK), 'the inserted PHP is pure ASCII')
    check('\t' not in MAP_BLOCK + WIRE_BLOCK, 'the inserted PHP has no tabs')
    php = sub_once(php, WIRE_ANCHOR, WIRE_BLOCK, 'class: the four Part 4c registrations')
    for old, new, what in LABELS:
        php = sub_once(php, crlf(old), crlf(new), 'class: ' + what + ' names its kind')
    php = sub_once(php, INSERT_BEFORE, MAP_BLOCK + INSERT_BEFORE, 'class: the Part 4c block')
    check("\r\n" in php and php.count("\n") == php.count("\r\n") and php.count("\r") == php.count("\r\n"),
          'class: pure CRLF - no bare LF, no bare CR')
    for m in NEW_METHODS:
        check(php.count(m) == 1, 'class: method declared once: ' + m.split('(')[0].split()[-1])
    for reg in ("add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_address'), 30, 1);",
                "add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout_address'), 110, 1);",
                "add_filter('slp_js_options', array(self::$instance,'avalon_js_options_map'), 110, 1);",
                "add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_map'));"):
        check(php.count(reg) == 1, 'class: registered once: ' + reg.split("'")[-2 if 'rocket' in reg else 3])
    for reg in ("add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_labels'), 20, 1);",
                "add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_email'), 25, 1);",
                "add_filter('slp_js_options', array(self::$instance,'avalon_js_options_layout'), 100, 1);",
                "add_filter('slp_js_options', array(self::$instance,'avalon_js_options_bubble'), 100, 1);"):
        check(php.count(reg) == 1, 'class: Part 4 and 4b still register ' + reg.split("'")[3])
    check(php.count('<b class="avalon-label">') == 0, 'class: no label is left without its kind')
    a = php.find('        /**\r\n         * v0.0.27 Part 4c. Cards and bubbles laid out for phones.')
    p4b = php.find('        /**\r\n         * v0.0.27 Part 4b. The info bubble shows what the card shows.')
    b = php.find('        public function avalon_rest_protected_slugs(){')
    check(0 < p4b < a < b, "class: Part 4c's block sits after Part 4b's, directly before avalon_rest_protected_slugs()")
    block = php[a:b]
    code = re.sub(r'/\*.*?\*/', '', block, flags=re.S)
    for banned in ('$wpdb', 'wp_remote_', 'googleapis', 'openNow', 'preg_replace'):
        check(banned not in code, 'class: the block code never mentions ' + banned)
    check(php.count('{') - braces[0] == php.count('}') - braces[1], 'class: the braces added balance')
    return php


def build_css(css):
    css = sub_once(css, CSS_HEAD_OLD, CSS_HEAD_NEW, 'css: the header names Part 4c')
    css = sub_once(css, CSS_TABLES_OLD, CSS_TABLES_NEW, 'css: the header says what Part 4c adds')
    check(css.endswith(CSS_TAIL_OLD), 'css: Part 4b ends with the email rule')
    css = sub_once(css, CSS_TAIL_OLD, CSS_TAIL_NEW, 'css: the Part 4c block at the end')
    check('\r' not in css and all(ord(c) < 128 for c in css), 'css: LF and ASCII')
    check(css.count('{') == css.count('}'), 'css: braces balance')
    check(css.count('@media (max-width: 767px)') == 3, "css: three phone blocks - Part 4's fold and Part 4c's two")
    check(css.count('content: "\\') == 10 and css.count('/ "";') == 5,
          'css: five icons, each with plain content and empty alternative text')
    check('!important' not in re.sub(r'/\*.*?\*/', '', css, flags=re.S), 'css: no !important in any rule')
    return css


def build_js(js):
    js = sub_once(js, JS_MAP_OLD, JS_MAP_NEW, 'slp_avalon.js: the controls and the bubble in cslmap_build_map()')
    js = sub_once(js, JS_HOVER_OLD, JS_HOVER_NEW, 'slp_avalon.js: the hover binding, and the avalon_map block after it')
    check("\r\n" in js and js.count("\n") == js.count("\r\n") and js.count("\r") == js.count("\r\n"),
          'slp_avalon.js: pure CRLF - no bare LF, no bare CR')
    check(all(ord(c) < 128 for c in js), 'slp_avalon.js: ASCII')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')') and js.count('[') == js.count(']'),
          'slp_avalon.js: braces, parentheses and brackets balance')
    check(js.count('var avalon_map = (function () {') == 1, 'slp_avalon.js: avalon_map declared once')
    check('handle_location_result_click' not in js.split('var avalon_map = (function () {')[1],
          'slp_avalon.js: nothing after the block calls SLP\'s handle_location_result_click()')
    blk = JS_HOVER_NEW
    for banned in ('innerHTML', 'eval(', 'new Function', '.html(', 'setInterval'):
        check(banned not in blk, 'slp_avalon.js: the block never uses ' + banned)
    check('shouldFocus: false' in blk, 'slp_avalon.js: Google is told not to move focus')
    return js


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part4c.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part4c  slp_avalon 0.0.27  PART 4c (cards and bubbles for phones, the bubble on the map)")
    print("")

    texts = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                     "       src_dir must hold Part 4b's class and stylesheet and v0.0.25's slp_avalon.js."
                     .format(name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        texts[name] = text
    print("")

    out = {
        'class.slp_avalon.php': build_class(texts['class.slp_avalon.php']),
        'avalon-hours.css':     build_css(texts['avalon-hours.css']),
        'slp_avalon.js':        build_js(texts['slp_avalon.js']),
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
    print("  note      avalon-hours.js and slp_avalon.php are not inputs: neither changes.")
    print("  note      no schema change. HOURS_DB_VERSION stays 2.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
