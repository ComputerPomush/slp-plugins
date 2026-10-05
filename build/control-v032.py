#!/usr/bin/env python3
"""
control-v032.py  r3  2026-10-04
SLP Dealer Guard - negative controls for suite-v032 and suite-hours
(slp_avalon v0.0.27 Part 4).

WITHOUT THIS FILE IN THE REPO, A CLEAN SCORE ASSERTS NOTHING. A suite that
has only ever been run against a build that works has not been shown to be
capable of failing. Each control below removes exactly one load-bearing
decision from the good build and nothing else, and the release is gated on
the suites catching every one of them, by a pinned count.

    python3 control-v032.py --in <class.slp_avalon.php> --js <avalon-hours.js> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for each class control and
<dir>/js-<name>/avalon-hours.js for each script control. Use an out* name
for the output directory - build/out*/ is gitignored (s0.218).

Every substitution is byte-exact and asserted unique before it is applied.
A control that could not be built is a hard error, never a skipped control:
a missing control is a decision nobody is testing.

Four class controls touch nothing Part 4 wrote. Each changes one byte-level
thing in a region Part 4 must NOT have changed, to show that suite-v032's
identity assertion can fail - it is the only thing carrying suite-v031's
evidence forward.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "d9e4b7eda90ead11e97211813847a42a"
IN_LEN = 300222
JS_MD5 = "abf650630474e77be40ea67b06fab3dd"
JS_LEN = 12279

N = "\r\n"

# ---------------------------------------------------------------------------
# Class controls. (name, why it matters, [(old, new), ...])
# ---------------------------------------------------------------------------

CONTROLS = [

    # ---- s0.273, the key swap --------------------------------------------

    ("loader_prints_server_key",
     "The page must print the Browser Key. Printing the server key puts a "
     "key SLP says to restrict by IP into every page's source, and the map "
     "fails the day it is restricted.",
     [("            $the_key = $this->avalon_google_browser_key();",
       "            $the_key = $this->avalon_google_server_key();")]),

    ("browser_key_falls_back",
     "A 'helpful' fallback from an empty Browser Key to the Geocoding Key "
     "is exactly the leak s0.273 closes.",
     [("            return trim( (string) $slplus->SmartOptions->google_server_key->value );",
       "            $k = trim( (string) $slplus->SmartOptions->google_server_key->value );" + N +
       "            return ( '' === $k && isset( $slplus->SmartOptions->google_geocode_key->value ) )"
       " ? trim( (string) $slplus->SmartOptions->google_geocode_key->value ) : $k;")]),

    ("server_key_no_fallback",
     "SLP's own rule falls back to the Browser Key when the Geocoding Key is "
     "empty - today's state on every environment. Without it the hours, the "
     "resolver and import geocoding all stop with NO_KEY.",
     [("            if ( '' === $key ) {" + N +
       "                $key = $this->avalon_google_browser_key();" + N +
       "            }" + N, "")]),

    ("server_key_prefers_browser",
     "The server must prefer the Geocoding Key. Preferring the Browser Key "
     "sends a referrer-restricted key to a web service, which Google refuses.",
     [("            $key = isset( $slplus->SmartOptions->google_geocode_key->value )" + N +
       "                   ? trim( (string) $slplus->SmartOptions->google_geocode_key->value )" + N,
       "            $key = isset( $slplus->SmartOptions->google_server_key->value )" + N +
       "                   ? trim( (string) $slplus->SmartOptions->google_server_key->value )" + N)]),

    ("geocode_old_read",
     "Import geocoding back on google_server_key.",
     [("            $server_key = $this->avalon_google_server_key();",
       "            $server_key = !empty($slplus->SmartOptions->google_server_key->value) ? $slplus->SmartOptions->google_server_key->value : '';")]),

    ("hours_old_read",
     "The hours fetch back on google_server_key.",
     [("            $api_key = $this->avalon_google_server_key();",
       "            global $slplus; $api_key = (string) $slplus->SmartOptions->google_server_key->value;")]),

    ("resolve_old_read",
     "The place resolver back on google_server_key.",
     [("            $key = $this->avalon_google_server_key();",
       "            $key = (string) $slplus->SmartOptions->google_server_key->value;")]),

    # ---- the time zone ---------------------------------------------------

    ("zone_ignores_offset",
     "Without the offset test every split state is its first zone: the "
     "Florida panhandle on Eastern time, an hour wrong all year.",
     [("                if ( intdiv( $zone->getOffset( $at ), 60 ) === (int) $offset_min ) {",
       "                if ( true ) {")]),

    ("zone_single_state_unchecked",
     "A single-zone state is checked too: an offset that matches none of a "
     "state's zones means the place, the state or the offset is wrong.",
     [("            foreach ( $all[ $country ][ $state ] as $name ) {",
       "            if ( 1 === count( $all[ $country ][ $state ] ) ) {" + N +
       "                return $all[ $country ][ $state ][0];" + N +
       "            }" + N +
       "            foreach ( $all[ $country ][ $state ] as $name ) {")]),

    ("zone_az_order",
     "Phoenix keeps MST all year, so in winter it shares -420 with Denver. "
     "Order decides; most Arizona dealers are not in the Navajo Nation.",
     [("'AZ' => array( 'America/Phoenix', 'America/Denver' ),",
       "'AZ' => array( 'America/Denver', 'America/Phoenix' ),")]),

    ("zone_wrong_instant",
     "Google's offset is from fetch time. Compared with any other instant -"
     " today's, or a fixed one - it is wrong for half the year. A fixed winter"
     " instant keeps this control's score the same in every season.",
     [("            $at = date_create_immutable( (string) $at_gmt, new DateTimeZone( 'UTC' ) );",
       "            $at = date_create_immutable( '2026-01-15 12:00:00', new DateTimeZone( 'UTC' ) );")]),

    ("zone_no_menominee",
     "Four Upper Peninsula counties keep Central time.",
     [("'MI' => array( 'America/Detroit', 'America/Menominee' ),",
       "'MI' => array( 'America/Detroit' ),")]),

    ("zone_crossing_guessed",
     "In the hour clocks fall back, Eastern and Central share -05; taking "
     "the first puts a Knoxville dealer on Central time all winter.",
     [("                    } elseif ( self::avalon_hours_crossing( $hitz, $zone, $at ) ) {" + N +
       "                        return '';" + N +
       "                    }",
       "                    }")]),

    ("crossing_one_side",
     "Zones that come to agree and stay agreed - Phoenix and Denver in "
     "winter - are not crossing; only disagreement on both sides is.",
     [("            return $a->getOffset( $before ) !== $b->getOffset( $before )" + N +
       "                && $a->getOffset( $after ) !== $b->getOffset( $after );",
       "            return $a->getOffset( $before ) !== $b->getOffset( $before )" + N +
       "                || $a->getOffset( $after ) !== $b->getOffset( $after );")]),

    ("rules_ignored",
     "On zone data without 2026b/2026c, Vancouver and Edmonton dealers "
     "would be an hour out from 1 November.",
     [("            if ( '' !== $hit && ! self::avalon_hours_rules_current( $hit ) ) {",
       "            if ( false ) {")]),

    ("zone_nu_edmonton",
     "tzdata 2026c: Edmonton keeps -06 all year, Cambridge Bay still "
     "changes; western Nunavut is no longer Edmonton.",
     [("'NU' => array( 'America/Iqaluit', 'America/Rankin_Inlet', 'America/Cambridge_Bay' ),",
       "'NU' => array( 'America/Iqaluit', 'America/Winnipeg', 'America/Edmonton' ),")]),

    # ---- weekday lines ---------------------------------------------------

    ("line_keeps_00",
     "9:00 AM-5:00 PM is not how Google's panel prints it.",
     [("            $hours = preg_replace( '/:00(?=\\s|\\x{2013}|,|$)/u', '', $hours );" + N, "")]),

    ("line_spaced_dash",
     "Google's panel prints the en dash with no spaces.",
     [("            $hours = preg_replace( '/\\s*[\\x{2013}\\x{2014}-]\\s*/u', \"\\xE2\\x80\\x93\", $hours );",
       "            $hours = preg_replace( '/\\s*[\\x{2013}\\x{2014}-]\\s*/u', \" \\xE2\\x80\\x93 \", $hours );")]),

    ("line_keeps_special_spaces",
     "Google puts U+202F before AM and PM and U+2009 round the dash.",
     [("            $s = str_replace( array( \"\\xE2\\x80\\xAF\", \"\\xE2\\x80\\x89\", \"\\xC2\\xA0\" ), ' ', (string) $line );",
       "            $s = (string) $line;")]),

    # ---- the gate and the payload ---------------------------------------

    ("gate_lets_none",
     "none means Google holds no hours; it renders nothing.",
     [("            if ( self::HOURS_STATUS_OK !== ( isset( $prow['hours_status'] ) ? (string) $prow['hours_status'] : '' ) ) {",
       "            if ( ! in_array( ( isset( $prow['hours_status'] ) ? (string) $prow['hours_status'] : '' ),"
       " array( self::HOURS_STATUS_OK, 'none' ), true ) ) {")]),

    ("gate_ignores_row_closed",
     "A dealer Google reports CLOSED_PERMANENTLY must show nothing.",
     [("            if ( in_array( $bstat, $closed, true ) ) {", "            if ( false ) {")]),

    ("gate_ignores_place_closed",
     "The cached Place is checked as well as the column.",
     [("            if ( in_array( $pstat, $closed, true ) ) {", "            if ( false ) {")]),

    ("payload_any_line_count",
     "Seven lines or nothing: a week with a day missing is not printed.",
     [("            if ( 7 !== count( $lines ) ) {", "            if ( 0 === count( $lines ) ) {")]),

    ("payload_leaks_opennow",
     "openNow was right at fetch time, up to 28 days ago.",
     [("                'tz'      => $tz," + N,
       "                'tz'      => $tz," + N +
       "                'openNow' => isset( $reg['openNow'] ) ? $reg['openNow'] : null," + N)]),

    ("payload_periods_without_zone",
     "Periods without a zone compute a status in the visitor's zone.",
     [("            if ( '' !== $tz && isset( $reg['periods'] ) && is_array( $reg['periods'] ) ) {",
       "            if ( isset( $reg['periods'] ) && is_array( $reg['periods'] ) ) {")]),

    ("payload_day_numbers",
     "A day's number comes from its name. Numbered by position, a week that "
     "Google starts on Sunday puts every row one day out.",
     [("            foreach ( $lines as $line ) {" + N +
       "                list( $name, $hours ) = self::avalon_hours_line( $line );" + N +
       "                $g = self::avalon_hours_day_number( $name );",
       "            foreach ( $lines as $i => $line ) {" + N +
       "                list( $name, $hours ) = self::avalon_hours_line( $line );" + N +
       "                $g = ( $i + 1 ) % 7;")]),

    ("payload_repeated_day",
     "Seven lines with a day twice is a week with a day missing.",
     [("                if ( '' === $hours || $g < 0 || isset( $seen[ $g ] ) ) {",
       "                if ( '' === $hours || $g < 0 ) {")]),

    ("payload_no_ttl",
     "A row fetched months ago shows hours Google may no longer list; the "
     "sweep refreshes at 28 days and the page refuses past the TTL.",
     [("            if ( false === $ts || $ts > $now + 86400" + N +
       "                 || $now - $ts > max( 1, (int) $max_age_days ) * 86400 ) {",
       "            if ( false === $ts || $ts > $now + 86400 ) {")]),

    ("payload_future_ok",
     "A fetched_at in the future is a clock or data fault, not a fresh row.",
     [("            if ( false === $ts || $ts > $now + 86400" + N,
       "            if ( false === $ts" + N)]),

    ("payload_ignores_disputed",
     "A disputed row can hold another dealer's hours until the next cron "
     "run blocks it; the page must refuse it from the release on.",
     [("            if ( '' !== $key && array_key_exists( $key, self::avalon_hours_disputed_keys() ) ) {",
       "            if ( false ) {")]),

    ("offsets_first_only",
     "One offset - the one in force when the page was built - is an hour "
     "wrong from the next clock change until the page is rebuilt.",
     [("            $list = $zone->getTransitions( $from, $to );",
       "            $list = array_slice( (array) $zone->getTransitions( $from, $to ), 0, 1 );")]),

    ("offsets_short_window",
     "The window must outlive any page cache; past its end the browser shows "
     "no status at all.",
     [("            $to   = (int) $now + 400 * 86400;",
       "            $to   = (int) $now + 86400;")]),

    ("point_unchecked",
     "Out-of-range points reach the browser's arithmetic.",
     [("            if ( $d < 0 || $d > 6 || $h < 0 || $h > 24 || $m < 0 || $m > 59 ) {",
       "            if ( false ) {")]),

    ("attribution_any_scheme",
     "A javascript: URL from the cache would be printed as a link.",
     [("                if ( ! preg_match( '#^https?://#i', $url ) ) {", "                if ( false ) {")]),

    # ---- the markup ------------------------------------------------------

    ("markup_empty_span",
     "SLP hides every empty span in a result as it inserts it (s0.274).",
     [("                     . '<span class=\"avalon-hours__status\">See hours</span></summary>';",
       "                     . '<span class=\"avalon-hours__status\"></span></summary>';")]),

    ("markup_unescaped_day",
     "Cached text printed raw is markup injection.",
     [("<th scope=\"row\">' . esc_html( $d[1] )", "<th scope=\"row\">' . ( $d[1] )")]),

    ("markup_one_copy",
     "One copy means the phone fold waits on a script and jumps.",
     [("                 . $narrow" + N, "")]),

    ("markup_ids",
     "An id repeats when two blocks share a page.",
     [("            $table = '<table class=\"avalon-hours__week\"><tbody>' . $rows . '</tbody></table>';",
       "            $table = '<table id=\"avalon-week\" class=\"avalon-hours__week\"><tbody>' . $rows . '</tbody></table>';")]),

    # ---- [avalon_store_hours] -------------------------------------------

    ("store_reads_by_sl_id",
     "sl_id holds one of N. The table is keyed by address.",
     [("                . \" FROM {$table} WHERE address_key = %s\"," + N +
       "                $v['dealer_key']",
       "                . \" FROM {$table} WHERE sl_id = %d\"," + N +
       "                $row['sl_id']")]),

    ("store_no_order",
     "Two rows linking one page must resolve the same way every time.",
     [("                . \" WHERE sl_linked_postid = %d ORDER BY sl_id ASC LIMIT 1\",",
       "                . \" WHERE sl_linked_postid = %d\",")]),

    ("store_any_post_type",
     "Only a store page carries a dealer's hours.",
     [("                $post_id = (int) get_the_ID();" + N +
       "            }" + N +
       "            if ( $post_id <= 0 || 'store_page' !== get_post_type( $post_id ) ) {",
       "                $post_id = (int) get_the_ID();" + N +
       "            }" + N +
       "            if ( $post_id <= 0 ) {")]),

    ("store_template_id",
     "Inside a Theme Builder template get_the_ID() can be the template "
     "itself; the queried store page must win.",
     [("            $post_id = is_singular( 'store_page' ) ? (int) get_queried_object_id() : 0;",
       "            $post_id = (int) get_the_ID();")]),

    # ---- the result cards -----------------------------------------------

    ("markers_keys_repeated",
     "Two markers for one dealer share one key in the IN ().",
     [("            $keys  = array_keys( $vec );", "            $keys  = array_values( $at );")]),

    ("markers_at_priority_10",
     "At 10 the hours run before territory_gate and decorate markers it then drops.",
     [("add_filter('slp_ajax_find_locations_complete', array(self::$instance,'avalon_hours_attach_markers'), 30, 1);",
       "add_filter('slp_ajax_find_locations_complete', array(self::$instance,'avalon_hours_attach_markers'), 10, 1);")]),

    ("markers_tel_country_from_vector",
     "vector() lets a five-digit postal code make a row American; a "
     "five-digit Mexican code must not earn +1.",
     [("            $cc  = ( null === $v ) ? '' : self::avalon_tel_country( $raw, $v['state'] );",
       "            $cc  = ( null === $v ) ? '' : $v['country'];")]),

    ("tel_state_over_country",
     "Baja California is BC too: a written Mexico must not become Canada.",
     [("            $cc  = SLP_Avalon_AddressKey::norm_country( $raw, '', '' );" + N +
       "            return ( '' !== $cc ) ? $cc : SLP_Avalon_AddressKey::norm_country( '', (string) $state, '' );",
       "            return SLP_Avalon_AddressKey::norm_country( $raw, (string) $state, '' );")]),

    ("labels_not_registered",
     "Without slp_results_marker_data the layout prints an empty Phone "
     "field: the phone number disappears from every card.",
     [("            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_labels'), 20, 1);" + N, "")]),

    # ---- the layout ------------------------------------------------------

    ("layout_not_idempotent",
     "SLP builds the layout more than once a page, and the script-option "
     "filter runs it again on purpose: the hours field must go in once.",
     [("            if ( false === strpos( $layout, 'avalon_hours_html' ) ) {",
       "            if ( true ) {")]),

    ("layout_label_twice",
     "Address: Address: on a second pass.",
     [("            if ( false === strpos( $layout, 'avalon_address_label' ) ) {",
       "            if ( true ) {")]),

    ("layout_hours_before_phone",
     "Google's order is Address, Phone, Hours.",
     [("'$1[slp_location avalon_phone_html]$2[slp_location avalon_hours_html]',",
       "'[slp_location avalon_hours_html]$1[slp_location avalon_phone_html]$2',")]),

    ("jsopts_not_registered",
     "A results layout left in SLP Experience's stored settings replaces "
     "ours on slp_js_options at 90: no labels, no tel:, no hours.",
     [("            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_layout'), 100, 1);" + N, "")]),

    ("jsopts_before_experience",
     "Before 90, Experience's merge still has the last word.",
     [("add_filter('slp_js_options', array(self::$instance,'avalon_js_options_layout'), 100, 1);",
       "add_filter('slp_js_options', array(self::$instance,'avalon_js_options_layout'), 10, 1);")]),

    ("layout_at_20",
     "SLP Experience replaces the layout at 90 and discards what it was "
     "given (s0.279): at 20 every label and the hours field are lost.",
     [("add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout'), 100, 1);",
       "add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout'), 20, 1);")]),

    ("tel_wraps_link",
     "A phone that already carries a link must not be wrapped in a second.",
     [("                $tel = ( false !== strpos( $phone, '<' ) ) ? ''" + N +
       "                       : self::avalon_tel( isset( $raw['sl_phone'] ) ? $raw['sl_phone'] : '', $country );",
       "                $tel = self::avalon_tel( isset( $raw['sl_phone'] ) ? $raw['sl_phone'] : '', $country );")]),

    # ---- tel -------------------------------------------------------------

    ("tel_area_code_0_or_1",
     "A NANP area code never starts with 0 or 1.",
     [("            if ( 10 !== strlen( $d ) || '0' === $d[0] || '1' === $d[0] ) {",
       "            if ( 10 !== strlen( $d ) ) {")]),

    ("tel_any_country",
     "+1 is North America's code only.",
     [("            if ( ! in_array( strtoupper( (string) $country ), array( 'US', 'CA' ), true ) ) {",
       "            if ( false ) {")]),

    # ---- assets ----------------------------------------------------------

    ("enqueue_in_admin",
     "The hours assets have no business in wp-admin.",
     [("            if ( is_admin() ) {" + N +
       "                return;" + N +
       "            }" + N +
       "            wp_enqueue_style( 'avalon-hours'",
       "            wp_enqueue_style( 'avalon-hours'")]),

    ("script_in_header",
     "In the header the script runs before the blocks it enhances exist.",
     [("                               self::file_version( 'assets/js/avalon-hours.js' ), true );",
       "                               self::file_version( 'assets/js/avalon-hours.js' ), false );")]),

    ("breakpoint_ignores_elementor",
     "A site that moved Elementor's mobile breakpoint folds at the wrong width.",
     [("            if ( class_exists( '\\Elementor\\Plugin' ) && isset( \\Elementor\\Plugin::$instance->breakpoints ) ) {",
       "            if ( false ) {")]),

    ("rucss_no_file",
     "Card rules not in the HTML are stripped by Remove Unused CSS.",
     [("            $list[] = wp_make_link_relative( ASLP_URL ) . 'assets/css/avalon-hours.css';" + N, "")]),

    ("delay_js_no_exclusion",
     "Delayed, the status stays 'See hours' until the first tap.",
     [("            $list[] = wp_make_link_relative( ASLP_URL ) . 'assets/js/avalon-hours';" + N, "")]),

    ("rucss_selector_unprefixed",
     "WP Rocket 3.11.0.2+ reads a selector from its start: .avalon-tel "
     "does not match a.avalon-tel.",
     [("            $list[] = '(.*).avalon-tel(.*)';",
       "            $list[] = '.avalon-tel';")]),

    ("rocket_fixed_path",
     "A fixed path misses a subdirectory site or a renamed plugin folder.",
     [("            $list[] = wp_make_link_relative( ASLP_URL ) . 'assets/js/avalon-hours';",
       "            $list[] = '/wp-content/plugins/slp_avalon/assets/js/avalon-hours';")]),

    ("no_shortcode",
     "[avalon_store_hours] unregistered prints its own tag on the page.",
     [("            add_shortcode('avalon_store_hours', array(self::$instance,'avalon_store_hours_sc_func'));" + N, "")]),

    # ---- regions Part 4 must not touch ----------------------------------

    ("touch_constant",
     "Part 3b's refresh margin, outside every Part 4 edit.",
     [("const HOURS_REFRESH_MARGIN_DAYS = 2;", "const HOURS_REFRESH_MARGIN_DAYS = 3;")]),

    ("touch_head",
     "The first line of the class, outside every Part 4 edit.",
     [("if (!class_exists('SLP_Avalon')){", "if (!class_exists('SLP_Avalon')) {")]),

    ("touch_sweep",
     "One statement in the sweep, outside every Part 4 edit.",
     [("$pos_cut = gmdate( 'Y-m-d H:i:s', time() - ( $refresh * DAY_IN_SECONDS ) );",
       "$pos_cut = gmdate( 'Y-m-d H:i:s', time() - ( ( $refresh + 1 ) * DAY_IN_SECONDS ) );")]),

    ("touch_territory_priority",
     "A Part 3b registration beside the Part 4 ones.",
     [("add_filter('slp_ajax_find_locations_complete',array(self::$instance,'territory_gate'),20,1);",
       "add_filter('slp_ajax_find_locations_complete',array(self::$instance,'territory_gate'),21,1);")]),
]

# ---------------------------------------------------------------------------
# Script controls, against avalon-hours.js.
# ---------------------------------------------------------------------------

JS_CONTROLS = [
    ("soon_is_30",
     "Google's 'Closes soon' and 'Opens soon' start an hour out.",
     [("return { kind: s[i][1] - x <= 60 ? \"closing\" : \"open\"",
       "return { kind: s[i][1] - x <= 30 ? \"closing\" : \"open\"")]),
    ("click_reaches_card",
     "A click on Hours must not recentre the map or open a bubble.",
     [("    e.stopPropagation();\n", "")]),
    ("today_not_first",
     "Google's panel lists today first, bold.",
     [("        order(el, now);\n", "")]),
    ("utc_not_local",
     "The status is the dealer's local time, not UTC.",
     [("    var local = new Date((t + off * 60) * 1000);\n",
       "    var local = new Date(t * 1000);\n")]),
    ("first_offset_only",
     "The offset in force now, not the first in the schedule: the first is "
     "an hour wrong from the next clock change on.",
     [("        off = o[i][1];\n", "        off = o[0][1];\n")]),
    ("no_window_end",
     "Past the schedule's end the browser shows no status rather than guess.",
     [("    if (t < o[0][0] || t >= u) {\n", "    if (t < o[0][0]) {\n")]),
    ("weekday_always",
     "'Opens 9 AM' later today carries no weekday.",
     [("(day === now.d && st.left < DAY ? \"\" : \" \" + SHORT[day])",
       "(\" \" + SHORT[day])")]),
    ("no_week_wrap",
     "Saturday night to Sunday morning wraps the week.",
     [("      if (c < o) {\n        c += WEEK;\n      }\n", "")]),
    ("no_allday",
     "Google's open-24-hours period - one point, Sunday 00:00 - is the whole week.",
     [("          raw.push([0, WEEK]);\n", "          raw.push([0, DAY]);\n")]),
    ("allday_any_open",
     "Only Google's own shape is open 24 hours; a stray one-point period "
     "must not turn a week of hours into 24/7.",
     [("        if (o === 0) {\n          raw.push([0, WEEK]);\n        }\n",
       "        raw.push([o, o + WEEK]);\n")]),
    ("no_merge",
     "A week of midnight-to-midnight periods would read 'Closes soon' at "
     "every midnight.",
     [("      if (last && raw[i][0] <= last[1]) {\n", "      if (false) {\n")]),
    ("merge_needs_overlap",
     "Periods that only touch - 9 to 12 and 12 to 5 - are one span.",
     [("      if (last && raw[i][0] <= last[1]) {\n", "      if (last && raw[i][0] < last[1]) {\n")]),
    ("no_wrap_join",
     "Saturday to midnight and Sunday from midnight are one span.",
     [("    while (out.length > 1 && out[out.length - 1][1] >= out[0][0] + WEEK) {\n", "    while (false) {\n")]),
    ("wrap_once",
     "A Saturday-night span can swallow more than one Sunday-morning period.",
     [("    while (out.length > 1 && out[out.length - 1][1] >= out[0][0] + WEEK) {\n",
       "    if (out.length > 1 && out[out.length - 1][1] >= out[0][0] + WEEK) {\n")]),
    ("guard_summary_only",
     "A click on a row of the opened week must not recentre the map either.",
     [("        el.addEventListener(\"click\", keep, false);\n",
       "        var sm = el.querySelectorAll(\"summary\");\n"
       "        for (var k = 0; k < sm.length; k++) { sm[k].addEventListener(\"click\", keep, false); }\n")]),
    ("guard_store_too",
     "The store page has no card handler; its clicks stay visible to the page.",
     [("      if ((\" \" + el.className + \" \").indexOf(\" avalon-hours--card \") >= 0) {\n"
       "        el.addEventListener(\"click\", keep, false);\n"
       "      }\n",
       "      el.addEventListener(\"click\", keep, false);\n")]),
    ("rewrite_every_tick",
     "Rewriting an unchanged block every minute clears a text selection and "
     "moves a screen reader's place.",
     [("      if (el.getAttribute(\"data-avalon-state\") !== state) {\n", "      if (true) {\n")]),
    ("tick_not_aligned",
     "A minute timer off the boundary leaves 'Closes soon' up to a minute late.",
     [("    }, 60000 - (new Date().getTime() % 60000) + 20);\n", "    }, 60000);\n")]),
    ("no_wake",
     "Back from the back/forward cache, the status must not wait for the timer.",
     [("      root.addEventListener(\"pageshow\", wake, false);\n", "")]),
    ("wake_no_rearm",
     "Woken, the timer goes back on the minute boundary.",
     [("    if (timer) {\n      root.clearTimeout(timer);\n      arm();\n    }\n", "")]),
    ("hidden_wakes",
     "A hidden page does no work.",
     [("      if (doc.visibilityState === \"visible\") {\n        wake();\n      }\n", "      wake();\n")]),
    ("block_error_escapes",
     "One bad block must not stop the others or the observer.",
     [("        safe(list[i], date);\n", "        enhance(list[i], date);\n")]),
    ("no_fallback",
     "Without a MutationObserver, SLP's own event finds new cards.",
     [("    } else if (typeof root.slp_Filter === \"function\") {\n", "    } else if (false) {\n")]),
    ("no_sort",
     "Google does not promise its periods in order.",
     [("    raw.sort(function (a, b) {\n      return a[0] - b[0];\n    });\n", "")]),
    ("reads_opennow",
     "The cache's openNow is a fetch-time answer.",
     [("    var tz = data && typeof data.tz === \"string\" ? data.tz : \"\";\n",
       "    var tz = data && typeof data.tz === \"string\" ? data.tz : \"\";\n"
       "    if (data && data.openNow === false) { return; }\n")]),
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

    print("control-v032.py  r3  2026-10-04")
    print("  negative controls for suite-v032 and suite-hours")
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
