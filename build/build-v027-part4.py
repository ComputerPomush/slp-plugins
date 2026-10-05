#!/usr/bin/env python3
"""
build-v027-part4.py

slp_avalon v0.0.27, PART 4 OF 4.

WHAT THIS RELEASE DOES
----------------------
Part 3b fetches and stores the hours. Part 4 prints them - Google-style, on
the two surfaces chosen on 2026-10-03 - and fixes the key swap found while
answering the Console question that day.

1. THE STORE PAGE. [avalon_store_hours], registered beside
   [avalon_map_location]. The locator row that links the post, its address
   key, the dealer-places row by that key (s0.270), the gate, the markup.
   Placed once in Elementor Theme Builder Single template 140017, beneath
   widget 4b33b2e7. The week is open on desktop and folds to one status line
   on phones, decided by CSS at Elementor's mobile breakpoint so nothing
   jumps whatever WP Rocket does with scripts.

2. THE RESULT CARDS. On slp_results_marker_data at priority 20 every
   marker gets Address: and Phone: labels as string fields, the phone as a
   tel: link - on the AJAX search and wherever SLP renders results on the
   server. On slp_ajax_find_locations_complete at priority 30, when the gate
   allows, the hours block: one IN () read for the whole response, as both
   marker builders carry the raw row (s0.275). The layout gets the matching
   [slp_location ...] fields through slp_javascript_results_string at 100
   (s0.276, s0.279), and again on slp_js_options at 100, after SLP
   Experience merges its stored settings at 90 - versioned here, not typed
   into three databases. Collapsed on every width.

3. THE GATE AND THE PAYLOAD. hours_status ok; not a disputed key; neither
   the row nor the cached Place closed; fetched within the positive TTL. A
   page carries the seven compacted weekday lines, the periods, the dealer's
   IANA zone with its offsets for the next 400 days, and the attributions.
   Never openNow, never raw.

4. THE TIME ZONE, ON THE SERVER. A candidate list per state and province;
   the first zone whose offset at fetched_at equals Google's offset at
   fetched_at wins. No match, no status - the week still prints. Nor in the
   hour neighbouring zones cross as clocks fall back, nor for Vancouver or
   Edmonton while the server's zone data lacks their 2026 rules.

5. s0.273, THE KEY SWAP. SLP labels google_server_key "Google Browser Key"
   and google_geocode_key "Google Geocoding Key". slp_avalon read them the
   other way round: its Maps loader printed google_geocode_key, and import
   geocoding, the hours fetch and the place resolver sent google_server_key.
   Harmless while one key fills both fields; fatal to the map and to every
   server call the day the keys are split as SLP's own settings advise.
   avalon_google_browser_key() - google_server_key, no fallback - feeds the
   loader. avalon_google_server_key() - google_geocode_key, else the Browser
   Key, SLP's own rule - feeds the three server calls.

6. ASSETS. avalon-hours.css and avalon-hours.js, new files, enqueued on the
   front end. WP Rocket: the script is excluded from Delay JavaScript and
   the stylesheet is safelisted for Remove Unused CSS, both by filter, so
   the settings travel with the plugin to Tahoe and Avalon.

WHAT IS PATCHED, AND WHAT IS ADDED
----------------------------------
Four key reads are replaced in place, each anchored on its exact bytes and
asserted unique. Seven registrations are appended to add_actions() and one to
register_shortcodes(). Twenty-seven methods and one private static property
are inserted as one block, directly before avalon_rest_protected_slugs().
Nothing else moves.

ENCODING
--------
ISO-8859-1 with newline='' so CRLF survives. Input is CRLF; output must stay
CRLF with no bare LF, and the CR count is printed. The inserted PHP is pure
ASCII; the en dash and the special spaces are written as escapes.

Usage:  python build-v027-part4.py <src_dir> <out_dir>

        src_dir must hold the PART 3b OUTPUT:
            class.slp_avalon.php   3bd2094189c58318a827ca01f990f4fc  246313
        and it writes, or refuses to:
            class.slp_avalon.php   d9e4b7eda90ead11e97211813847a42a  300222
"""

import hashlib
import io
import os
import re
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('3bd2094189c58318a827ca01f990f4fc', 246313),
}

# The output this script was reviewed against. A build that produces any
# other bytes is not the build suite-v032 scored and control-v032's controls
# were measured on, and is refused before it is written.
OUT_PIN = ('d9e4b7eda90ead11e97211813847a42a', 300222)


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def write_exact(path, text):
    raw = text.encode('iso-8859-1')
    io.open(path, 'wb').write(raw)
    return hashlib.md5(raw).hexdigest(), len(raw), raw.count(b'\r')


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
# 1. s0.273 - the four key reads
# ===========================================================================

LOADER_OLD = crlf("""            // Google JavaScript API server Key
            // $server_key = !empty($slplus->SmartOptions->google_server_key->value) ? '&key=' . $slplus->SmartOptions->google_server_key->value : '';
            $the_key = ! empty ( $slplus->SmartOptions->google_geocode_key->value ) ? $slplus->SmartOptions->google_geocode_key->value : '';
            if ( empty( $the_key ) ) {
                $the_key = ! empty ( $slplus->SmartOptions->google_server_key->value ) ? $slplus->SmartOptions->google_server_key->value : '';
            }
""")
LOADER_NEW = crlf("""            // v0.0.27 Part 4, s0.273. The Browser Key and only the Browser
            // Key - SLP's google_server_key. Until Part 4 this read
            // google_geocode_key first, SLP's server-side Geocoding Key.
            $the_key = $this->avalon_google_browser_key();
""")

GEOCODE_OLD = crlf("""            $server_key = !empty($slplus->SmartOptions->google_server_key->value) ? $slplus->SmartOptions->google_server_key->value : '';
""")
GEOCODE_NEW = crlf("""            //v0.0.27 Part 4, s0.273. SLP's Geocoding Key, else its Browser Key.
            $server_key = $this->avalon_google_server_key();
""")

HOURS_OLD = crlf("""            global $slplus;
            $api_key = '';
            if ( isset( $slplus ) && is_object( $slplus ) ) {
                $api_key = (string) $slplus->SmartOptions->google_server_key->value;
            }
""")
HOURS_NEW = crlf("""            //v0.0.27 Part 4, s0.273. SLP's Geocoding Key, else its Browser
            //Key - the server's key, never the one the page prints.
            $api_key = $this->avalon_google_server_key();
""")

RESOLVE_OLD = crlf("""            $key = '';
            if ( isset( $slplus->SmartOptions->google_server_key->value ) ) {
                $key = (string) $slplus->SmartOptions->google_server_key->value;
            }
""")
RESOLVE_NEW = crlf("""            //v0.0.27 Part 4, s0.273. SLP's Geocoding Key, else its Browser Key.
            $key = $this->avalon_google_server_key();
""")


# ===========================================================================
# 2. Registrations
# ===========================================================================

WIRE_ANCHOR = crlf("""            add_action(self::HOURS_CRON_HOOK, array(self::$instance,'avalon_hours_cron'));
""")
WIRE_BLOCK = crlf("""            add_action(self::HOURS_CRON_HOOK, array(self::$instance,'avalon_hours_cron'));
            //
            // v0.0.27 Part 4. Showing the hours.
            //
            // The stylesheet and the script on wp_enqueue_scripts, which
            // fires only on the front end. The phone and address labels onto
            // every marker at 20, after SLP Experience's marker filter at 15.
            // The hours onto each result at priority 30 - after the backfill
            // at 10 and territory_gate at 20, so exactly the markers that
            // will be sent are decorated, with one read for all of them. The
            // fields into the results layout at 100 - after SLP Experience,
            // whose filter at 90 starts again from the stored layout
            // (s0.279) - and into the script options at 100, after it merges
            // its stored settings at 90. The two WP Rocket filters are
            // no-ops where WP Rocket is not installed.
            add_action('wp_enqueue_scripts', array(self::$instance,'avalon_hours_enqueue'), 20);
            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_labels'), 20, 1);
            add_filter('slp_ajax_find_locations_complete', array(self::$instance,'avalon_hours_attach_markers'), 30, 1);
            add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout'), 100, 1);
            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_layout'), 100, 1);
            add_filter('rocket_delay_js_exclusions', array('SLP_Avalon','avalon_rocket_delay_exclusions'));
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist'));
""")

SHORTCODE_ANCHOR = crlf("""            add_shortcode('avalon_map_location', array(self::$instance,'avalon_map_location_sc_func'));
""")
SHORTCODE_BLOCK = crlf("""            add_shortcode('avalon_map_location', array(self::$instance,'avalon_map_location_sc_func'));
            add_shortcode('avalon_store_hours', array(self::$instance,'avalon_store_hours_sc_func'));
""")


# ===========================================================================
# 3. The display block, inserted before avalon_rest_protected_slugs()
# ===========================================================================

INSERT_BEFORE = crlf("""        public function avalon_rest_protected_slugs(){
""")

DISPLAY_BLOCK = crlf(r"""        /**
         * v0.0.27 Part 4. Showing the hours.
         *
         * Part 3b fetches and stores. This prints, on the two surfaces
         * chosen on 2026-10-03: the store page, through [avalon_store_hours],
         * and the find-a-dealer result cards, through the marker data and
         * the results layout. Nothing here calls Google. Every read is the
         * dealer-places table by address key - its primary key - and never
         * by sl_id, which holds one of N.
         *
         * WHAT A PAGE MAY CARRY. The seven weekday lines, compacted; the
         * periods; the dealer's IANA time zone and, from the server's own
         * zone data, that zone's UTC offsets for the next 400 days; and the
         * attributions. Never openNow and never raw. openNow was true or
         * false at fetch time, up to 28 days ago, and raw is Google's whole
         * answer. Open or closed is a render-time question, answered in the
         * visitor's browser from the periods and the offsets, because every
         * one of these pages is cached.
         *
         * ONE ZONE DATABASE, NOT TWO. The browser adds the offset; it never
         * looks the zone up itself (s0.278). British Columbia (tzdata 2026b)
         * and Alberta (2026c) stopped changing their clocks in 2026, and a
         * browser whose own zone data predates that would put a Vancouver
         * dealer an hour out from 1 November. The server's data is the one
         * the zone was resolved with, and the one this release can measure.
         *
         * THE GATE. hours_status ok; the key is not on
         * avalon_hours_disputed_keys(); neither the row nor the cached
         * Place says CLOSED_PERMANENTLY or CLOSED_TEMPORARILY - NULL counts
         * as open; and fetched_at no older than the positive TTL, so the
         * 30-day cap holds at render time even for a row the purge cannot
         * reach (rev42 s D.3). Anything else prints nothing: no heading, no
         * placeholder, one rule for both surfaces.
         */

        /**
         * v0.0.27 Part 4, s0.273. The key the Maps loader prints.
         *
         * SLP labels google_server_key "Google Browser Key" - "used to draw
         * the map for the site visitor" - and SLP core prints it in its own
         * loader. Until this release splus_get_google_maps_url() read
         * google_geocode_key first: SLP's "Google Geocoding Key", the field
         * SLP says to restrict to the server's IP address.
         *
         * NO FALLBACK, deliberately. A Geocoding Key restricted the way SLP
         * advises must never be printed in a page, so an empty Browser Key
         * prints no key at all. The map then fails visibly instead of a
         * server key leaking quietly.
         */
        private function avalon_google_browser_key(){
            global $slplus;
            if ( ! isset( $slplus ) || ! is_object( $slplus )
                 || ! isset( $slplus->SmartOptions->google_server_key->value ) ) {
                return '';
            }
            return trim( (string) $slplus->SmartOptions->google_server_key->value );
        }

        /**
         * v0.0.27 Part 4, s0.273. The key the server sends to Google.
         *
         * SLP's own rule, from SLP_Google::get_google_geocoding_url(): the
         * Geocoding Key when it is set, otherwise the Browser Key. Read by
         * import geocoding, the hours fetch and the place resolver, which
         * until this release all read google_server_key - the Browser Key.
         *
         * Harmless while both fields hold the same key, which they do on
         * every environment today. The day the keys are split, the swap
         * would have broken both the map and every server call at once.
         */
        private function avalon_google_server_key(){
            global $slplus;
            if ( ! isset( $slplus ) || ! is_object( $slplus ) ) {
                return '';
            }
            $key = isset( $slplus->SmartOptions->google_geocode_key->value )
                   ? trim( (string) $slplus->SmartOptions->google_geocode_key->value )
                   : '';
            if ( '' === $key ) {
                $key = $this->avalon_google_browser_key();
            }
            return $key;
        }

        /**
         * v0.0.27 Part 4. The IANA zones each state or province can be in.
         *
         * One zone where the whole state keeps one clock, several where it
         * does not. The dealer's own UTC offset at fetch time picks among
         * them - avalon_hours_zone(). Where two candidates share that
         * offset, the first wins, and the zone most dealers are in comes
         * first: Arizona against the Navajo Nation (Phoenix; every Arizona
         * dealer in the feeds is in it - a Navajo dealer fetched in winter
         * would read an hour out the next summer), Saskatchewan against
         * Lloydminster, BC's coast against its Peace River towns, which
         * since tzdata 2026b keep the same offset all year anyway, and
         * Yellowknife against Inuvik. The exception is the hour clocks fall
         * back, when neighbours share an offset only because one has
         * changed and the other has not yet: avalon_hours_zone() refuses to
         * guess there.
         *
         * tzdata 2026c, measured 2026-10-04: America/Edmonton keeps -06 all
         * year; America/Inuvik and America/Cambridge_Bay still change. So
         * the western Northwest Territories are Inuvik and western Nunavut
         * is Cambridge_Bay - no longer Edmonton.
         *
         * Keys are what SLP_Avalon_AddressKey::vector() returns for country
         * and state, so a dealer's zone is looked up with the same
         * normalisation that keys its row.
         */
        public static function avalon_hours_zone_candidates(){
            return array(
                'US' => array(
                    'AL' => array( 'America/Chicago' ),
                    'AK' => array( 'America/Anchorage', 'America/Adak' ),
                    'AZ' => array( 'America/Phoenix', 'America/Denver' ),
                    'AR' => array( 'America/Chicago' ),
                    'CA' => array( 'America/Los_Angeles' ),
                    'CO' => array( 'America/Denver' ),
                    'CT' => array( 'America/New_York' ),
                    'DE' => array( 'America/New_York' ),
                    'DC' => array( 'America/New_York' ),
                    'FL' => array( 'America/New_York', 'America/Chicago' ),
                    'GA' => array( 'America/New_York' ),
                    'HI' => array( 'Pacific/Honolulu' ),
                    'ID' => array( 'America/Boise', 'America/Los_Angeles' ),
                    'IL' => array( 'America/Chicago' ),
                    'IN' => array( 'America/Indiana/Indianapolis', 'America/Chicago' ),
                    'IA' => array( 'America/Chicago' ),
                    'KS' => array( 'America/Chicago', 'America/Denver' ),
                    'KY' => array( 'America/New_York', 'America/Chicago' ),
                    'LA' => array( 'America/Chicago' ),
                    'ME' => array( 'America/New_York' ),
                    'MD' => array( 'America/New_York' ),
                    'MA' => array( 'America/New_York' ),
                    'MI' => array( 'America/Detroit', 'America/Menominee' ),
                    'MN' => array( 'America/Chicago' ),
                    'MS' => array( 'America/Chicago' ),
                    'MO' => array( 'America/Chicago' ),
                    'MT' => array( 'America/Denver' ),
                    'NE' => array( 'America/Chicago', 'America/Denver' ),
                    'NV' => array( 'America/Los_Angeles', 'America/Denver' ),
                    'NH' => array( 'America/New_York' ),
                    'NJ' => array( 'America/New_York' ),
                    'NM' => array( 'America/Denver' ),
                    'NY' => array( 'America/New_York' ),
                    'NC' => array( 'America/New_York' ),
                    'ND' => array( 'America/Chicago', 'America/Denver' ),
                    'OH' => array( 'America/New_York' ),
                    'OK' => array( 'America/Chicago' ),
                    'OR' => array( 'America/Los_Angeles', 'America/Boise' ),
                    'PA' => array( 'America/New_York' ),
                    'RI' => array( 'America/New_York' ),
                    'SC' => array( 'America/New_York' ),
                    'SD' => array( 'America/Chicago', 'America/Denver' ),
                    'TN' => array( 'America/Chicago', 'America/New_York' ),
                    'TX' => array( 'America/Chicago', 'America/Denver' ),
                    'UT' => array( 'America/Denver' ),
                    'VT' => array( 'America/New_York' ),
                    'VA' => array( 'America/New_York' ),
                    'WA' => array( 'America/Los_Angeles' ),
                    'WV' => array( 'America/New_York' ),
                    'WI' => array( 'America/Chicago' ),
                    'WY' => array( 'America/Denver' ),
                ),
                'CA' => array(
                    'AB' => array( 'America/Edmonton' ),
                    'BC' => array( 'America/Vancouver', 'America/Edmonton', 'America/Dawson_Creek' ),
                    'MB' => array( 'America/Winnipeg' ),
                    'NB' => array( 'America/Moncton' ),
                    'NL' => array( 'America/St_Johns', 'America/Goose_Bay' ),
                    'NS' => array( 'America/Halifax' ),
                    'NT' => array( 'America/Edmonton', 'America/Inuvik' ),
                    'NU' => array( 'America/Iqaluit', 'America/Rankin_Inlet', 'America/Cambridge_Bay' ),
                    'ON' => array( 'America/Toronto', 'America/Winnipeg' ),
                    'PE' => array( 'America/Halifax' ),
                    'QC' => array( 'America/Toronto', 'America/Halifax' ),
                    'SK' => array( 'America/Regina', 'America/Edmonton' ),
                    'YT' => array( 'America/Whitehorse' ),
                ),
            );
        }

        /**
         * v0.0.27 Part 4. One dealer's IANA zone, or '' when it cannot be told.
         *
         * The first candidate whose offset at fetched_at equals the offset
         * Google reported at fetched_at. Both sides are taken at the same
         * instant, so daylight saving cancels out. A single-zone state is
         * checked too: an offset that matches none of the state's zones
         * means the place, the state or the offset is wrong, and a status
         * computed in a wrong zone would be worse than no status. The week
         * still prints; only the Open/Closed line is withheld.
         *
         * Also '' in the hour clocks fall back, when two candidates match
         * only because they are crossing - avalon_hours_crossing() - and
         * for a zone the server's own data has not caught up with -
         * avalon_hours_rules_current().
         */
        public static function avalon_hours_zone( $country, $state, $offset_min, $at_gmt ){
            $all     = self::avalon_hours_zone_candidates();
            $country = strtoupper( (string) $country );
            $state   = strtoupper( (string) $state );
            if ( ! isset( $all[ $country ][ $state ] ) || ! is_numeric( $offset_min ) ) {
                return '';
            }
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $at_gmt ) ) {
                return '';
            }
            $at = date_create_immutable( (string) $at_gmt, new DateTimeZone( 'UTC' ) );
            if ( false === $at ) {
                return '';
            }
            $hit  = '';
            $hitz = null;
            foreach ( $all[ $country ][ $state ] as $name ) {
                try {
                    $zone = new DateTimeZone( $name );
                } catch ( Exception $e ) {
                    continue;
                }
                if ( intdiv( $zone->getOffset( $at ), 60 ) === (int) $offset_min ) {
                    if ( null === $hitz ) {
                        $hit  = $name;
                        $hitz = $zone;
                    } elseif ( self::avalon_hours_crossing( $hitz, $zone, $at ) ) {
                        return '';
                    }
                }
            }
            if ( '' !== $hit && ! self::avalon_hours_rules_current( $hit ) ) {
                return '';
            }
            return $hit;
        }

        /**
         * v0.0.27 Part 4. Whether two zones that agree at $at are crossing.
         *
         * Neighbouring zones fall back an hour apart, so for one hour each
         * November they share an offset: New York has gone to -05 at 06:00
         * UTC and Chicago leaves -05 at 07:00. A fetch in that hour cannot
         * tell them apart, and taking the first would put a Knoxville
         * dealer on Central time all winter. Crossing means they disagree
         * both two hours before and two hours after. Phoenix and Denver,
         * which come to agree when Denver falls back and then stay agreed
         * all winter, are not crossing, and Phoenix still wins.
         */
        public static function avalon_hours_crossing( $a, $b, $at ){
            $before = $at->modify( '-2 hours' );
            $after  = $at->modify( '+2 hours' );
            return $a->getOffset( $before ) !== $b->getOffset( $before )
                && $a->getOffset( $after ) !== $b->getOffset( $after );
        }

        /**
         * v0.0.27 Part 4. Measured once a request: zone => rules current.
         */
        private static $avalon_hours_rules = array();

        /**
         * v0.0.27 Part 4. Whether the server's zone data has 2026's rules
         * for a zone that changed them.
         *
         * British Columbia keeps -07 all year from tzdata 2026b, Alberta -06
         * from 2026c (s0.278). On a server whose zone data predates that, a
         * dealer resolved to America/Vancouver or America/Edmonton would be
         * sent a schedule an hour out from 1 November - the fault the
         * browser is kept away from. Such a dealer gets no zone, and so no
         * status, until the server's data catches up; then this lifts by
         * itself. Read on 15 January 2027, when the old rules and the new
         * differ for both.
         */
        public static function avalon_hours_rules_current( $name ){
            $need = array( 'America/Vancouver' => -420, 'America/Edmonton' => -360 );
            if ( ! isset( $need[ $name ] ) ) {
                return true;
            }
            if ( ! isset( self::$avalon_hours_rules[ $name ] ) ) {
                $at = date_create_immutable( '2027-01-15 12:00:00', new DateTimeZone( 'UTC' ) );
                $tz = new DateTimeZone( $name );
                self::$avalon_hours_rules[ $name ] = ( intdiv( $tz->getOffset( $at ), 60 ) === $need[ $name ] );
            }
            return self::$avalon_hours_rules[ $name ];
        }

        /**
         * v0.0.27 Part 4. One of Google's weekday lines, split and compacted.
         *
         * "Monday: 9:00 AM - 5:00 PM" (en dash, thin spaces) becomes
         * array( 'Monday', '9 AM-5 PM' ) with an unspaced en dash, the way
         * Google's own panel prints it. Google puts U+2009 around the dash
         * and U+202F before AM and PM; both, and U+00A0, are read as
         * spaces first. ":00" goes only where a time ends - before a space,
         * a dash, a comma or the end - so 12:30 stays 12:30. "Closed" and
         * "Open 24 hours" pass through untouched.
         *
         * A line with no ": " is not a weekday line, and the caller drops
         * the whole week rather than print six days.
         */
        public static function avalon_hours_line( $line ){
            $s = str_replace( array( "\xE2\x80\xAF", "\xE2\x80\x89", "\xC2\xA0" ), ' ', (string) $line );
            $parts = explode( ': ', $s, 2 );
            if ( 2 !== count( $parts ) ) {
                return array( '', '' );
            }
            $day   = trim( $parts[0] );
            $hours = trim( $parts[1] );
            $hours = preg_replace( '/\s*[\x{2013}\x{2014}-]\s*/u', "\xE2\x80\x93", $hours );
            $hours = preg_replace( '/:00(?=\s|\x{2013}|,|$)/u', '', $hours );
            $hours = trim( preg_replace( '/\s+/', ' ', $hours ) );
            return array( $day, $hours );
        }

        /**
         * v0.0.27 Part 4. An English weekday name -> Google's day number, or -1.
         *
         * Sunday is 0, as in Google's periods. The day of each line comes
         * from its name, not its position: Google orders weekday_text by
         * the request's language, and some languages start on Sunday. Our
         * requests send no language and the lines arrive Monday first in
         * English - but a week whose names are not the seven English days
         * is refused rather than guessed at.
         */
        public static function avalon_hours_day_number( $name ){
            $days = array( 'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                           'thursday' => 4, 'friday' => 5, 'saturday' => 6 );
            $k = strtolower( trim( (string) $name ) );
            return isset( $days[ $k ] ) ? $days[ $k ] : -1;
        }

        /**
         * v0.0.27 Part 4. One zone's UTC offsets, from the server's zone data.
         *
         * array( 'o' => array( array( utc, minutes ), ... ), 'u' => until ):
         * the offset in force a day before $now, then every change up to
         * 400 days after it. The browser takes the last entry at or before
         * now and adds it - no zone lookup of its own (s0.278). 400 days
         * outlives any page cache, and past 'u' the browser shows no
         * status rather than guess.
         */
        public static function avalon_hours_offsets( $tz, $now ){
            $out = array( 'o' => array(), 'u' => 0 );
            if ( '' === (string) $tz ) {
                return $out;
            }
            try {
                $zone = new DateTimeZone( (string) $tz );
            } catch ( Exception $e ) {
                return $out;
            }
            $from = (int) $now - 86400;
            $to   = (int) $now + 400 * 86400;
            $list = $zone->getTransitions( $from, $to );
            if ( ! is_array( $list ) ) {
                return $out;
            }
            foreach ( $list as $t ) {
                if ( is_array( $t ) && isset( $t['ts'], $t['offset'] ) ) {
                    $out['o'][] = array( (int) $t['ts'], intdiv( (int) $t['offset'], 60 ) );
                }
            }
            if ( ! empty( $out['o'] ) ) {
                $out['u'] = $to;
            }
            return $out;
        }

        /**
         * v0.0.27 Part 4. A stored period point -> array( day, hour, minute ).
         *
         * Points are what avalon_hours_point() and Places (New) both write:
         * day 0 (Sunday) to 6, hour 0 to 24, minute 0 to 59. Anything else
         * is null, and a period without a valid open point is dropped. The
         * browser only ever sees three small integers per point.
         */
        public static function avalon_hours_pt( $p ){
            if ( ! is_array( $p ) || ! isset( $p['day'], $p['hour'] ) ) {
                return null;
            }
            $d = (int) $p['day'];
            $h = (int) $p['hour'];
            $m = isset( $p['minute'] ) ? (int) $p['minute'] : 0;
            if ( $d < 0 || $d > 6 || $h < 0 || $h > 24 || $m < 0 || $m > 59 ) {
                return null;
            }
            return array( $d, $h, $m );
        }

        /**
         * v0.0.27 Part 4. Attributions -> array( array( text, url ), ... ).
         *
         * Two shapes reach here. Places (New) writes objects with provider
         * and providerUri. Legacy writes html_attributions: HTML strings,
         * normalised by Part 3b without change (s0.267). Only the first
         * anchor of a legacy string is kept - anchors only, as the starter
         * specified - and a URL that is not http or https is dropped, so
         * nothing from the cache is ever printed as markup.
         */
        public static function avalon_hours_attributions( $list ){
            $out = array();
            if ( ! is_array( $list ) ) {
                return $out;
            }
            foreach ( $list as $a ) {
                $text = '';
                $url  = '';
                if ( is_array( $a ) ) {
                    $text = isset( $a['provider'] ) ? (string) $a['provider'] : '';
                    $url  = isset( $a['providerUri'] ) ? (string) $a['providerUri'] : '';
                } elseif ( is_string( $a )
                           && preg_match( '/<a\s[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $a, $m ) ) {
                    $url  = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
                    $text = html_entity_decode( strip_tags( $m[2] ), ENT_QUOTES, 'UTF-8' );
                }
                $text = trim( preg_replace( '/\s+/', ' ', $text ) );
                $url  = trim( $url );
                if ( '' === $text ) {
                    continue;
                }
                if ( ! preg_match( '#^https?://#i', $url ) ) {
                    $url = '';
                }
                $out[] = array( $text, $url );
                if ( count( $out ) >= 5 ) {
                    break;
                }
            }
            return $out;
        }

        /**
         * v0.0.27 Part 4. A locator row -> its address-key vector, or null.
         *
         * The same five raw fields every other caller keys on; address2 is
         * not part of the key. Null when the row has neither a street nor
         * a city - such a row has no key worth reading by.
         */
        public static function avalon_hours_row_key( $row ){
            if ( ! is_array( $row ) || ! class_exists( 'SLP_Avalon_AddressKey' ) ) {
                return null;
            }
            $v = SLP_Avalon_AddressKey::vector( array(
                'raw_address' => isset( $row['sl_address'] ) ? (string) $row['sl_address'] : '',
                'raw_city'    => isset( $row['sl_city'] ) ? (string) $row['sl_city'] : '',
                'raw_state'   => isset( $row['sl_state'] ) ? (string) $row['sl_state'] : '',
                'raw_zip'     => isset( $row['sl_zip'] ) ? (string) $row['sl_zip'] : '',
                'raw_country' => isset( $row['sl_country'] ) ? (string) $row['sl_country'] : '',
            ) );
            if ( '' === (string) $v['street_key'] && '' === (string) $v['city_key'] ) {
                return null;
            }
            return $v;
        }

        /**
         * v0.0.27 Part 4. One dealer-places row -> what a page may carry, or null.
         *
         * Returns array( 'days', 'periods', 'tz', 'o', 'u', 'attr' ):
         *
         *   days     seven array( g, name, hours ) in Google's order; g is
         *            Google's day number from the name, 0 = Sunday
         *   periods  array( open[, close] ) of avalon_hours_pt() points,
         *            only when a zone was found - without a zone there is
         *            nothing to compute a status from
         *   tz       the IANA zone, or ''
         *   o, u     avalon_hours_offsets() for that zone, from $now
         *   attr     avalon_hours_attributions()
         *
         * Seven lines, one for each day, or nothing: a week with a day
         * missing or repeated is not printed. hours_status ok already means
         * there are weekday lines (s0.260); this also refuses a line that
         * does not split.
         *
         * $max_age_days is the positive TTL; a row fetched longer ago than
         * that, or with no readable fetched_at, prints nothing. $now is the
         * clock, passed in so a test can hold it still.
         */
        public static function avalon_hours_payload( $prow, $country, $state, $max_age_days = 30, $now = null ){
            if ( ! is_array( $prow ) ) {
                return null;
            }
            if ( self::HOURS_STATUS_OK !== ( isset( $prow['hours_status'] ) ? (string) $prow['hours_status'] : '' ) ) {
                return null;
            }
            // A disputed key shows nothing from the moment the release that
            // lists it lands, not from the next cron run that blocks it: the
            // row may hold another dealer's hours until then.
            $key = isset( $prow['address_key'] ) ? (string) $prow['address_key'] : '';
            if ( '' !== $key && array_key_exists( $key, self::avalon_hours_disputed_keys() ) ) {
                return null;
            }
            $closed = array( 'CLOSED_PERMANENTLY', 'CLOSED_TEMPORARILY' );
            $bstat  = strtoupper( isset( $prow['business_status'] ) ? (string) $prow['business_status'] : '' );
            if ( in_array( $bstat, $closed, true ) ) {
                return null;
            }

            $now = ( null === $now ) ? time() : (int) $now;
            $at  = isset( $prow['fetched_at'] ) ? (string) $prow['fetched_at'] : '';
            $ts  = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $at ) ? strtotime( $at . ' UTC' ) : false;
            if ( false === $ts || $ts > $now + 86400
                 || $now - $ts > max( 1, (int) $max_age_days ) * 86400 ) {
                return null;
            }

            $json = json_decode( isset( $prow['hours_json'] ) ? (string) $prow['hours_json'] : '', true );
            if ( ! is_array( $json ) || ! isset( $json['place'] ) || ! is_array( $json['place'] ) ) {
                return null;
            }
            $place = $json['place'];
            $pstat = strtoupper( isset( $place['businessStatus'] ) ? (string) $place['businessStatus'] : '' );
            if ( in_array( $pstat, $closed, true ) ) {
                return null;
            }

            $reg   = ( isset( $place['regularOpeningHours'] ) && is_array( $place['regularOpeningHours'] ) )
                     ? $place['regularOpeningHours'] : array();
            $lines = ( isset( $reg['weekdayDescriptions'] ) && is_array( $reg['weekdayDescriptions'] ) )
                     ? array_values( $reg['weekdayDescriptions'] ) : array();
            if ( 7 !== count( $lines ) ) {
                return null;
            }

            $days = array();
            $seen = array();
            foreach ( $lines as $line ) {
                list( $name, $hours ) = self::avalon_hours_line( $line );
                $g = self::avalon_hours_day_number( $name );
                if ( '' === $hours || $g < 0 || isset( $seen[ $g ] ) ) {
                    return null;
                }
                $seen[ $g ] = true;
                $days[]     = array( $g, $name, $hours );
            }

            $offset = ( isset( $place['utcOffsetMinutes'] ) && is_numeric( $place['utcOffsetMinutes'] ) )
                      ? (int) $place['utcOffsetMinutes'] : null;
            $tz     = ( null === $offset ) ? '' : self::avalon_hours_zone( $country, $state, $offset, $at );
            $sched  = self::avalon_hours_offsets( $tz, $now );
            if ( empty( $sched['o'] ) ) {
                $tz = '';
            }

            $periods = array();
            if ( '' !== $tz && isset( $reg['periods'] ) && is_array( $reg['periods'] ) ) {
                foreach ( $reg['periods'] as $p ) {
                    if ( ! is_array( $p ) ) {
                        continue;
                    }
                    $open = self::avalon_hours_pt( isset( $p['open'] ) ? $p['open'] : null );
                    if ( null === $open ) {
                        continue;
                    }
                    $close     = self::avalon_hours_pt( isset( $p['close'] ) ? $p['close'] : null );
                    $periods[] = ( null === $close ) ? array( $open ) : array( $open, $close );
                }
            }

            return array(
                'days'    => $days,
                'periods' => $periods,
                'tz'      => $tz,
                'o'       => $sched['o'],
                'u'       => $sched['u'],
                'attr'    => self::avalon_hours_attributions(
                                 isset( $place['attributions'] ) ? $place['attributions'] : array() ),
            );
        }

        /**
         * v0.0.27 Part 4. The hours block for one surface: 'store' or 'card'.
         *
         * THE SERVER MARKUP IS THE WHOLE WEEK, Monday first, and it works
         * with no JavaScript at all. avalon-hours.js only adds to it: the
         * Open/Closed line, today first and bold. No ids anywhere, so two
         * blocks on one page cannot collide.
         *
         * STORE PAGE. Two copies of the week. __wide is the open table with
         * a status line above it; __narrow is a native <details> that
         * starts closed. avalon-hours.css shows one or the other at
         * Elementor's mobile breakpoint, so a phone gets the collapsed line
         * on the very first frame - no waiting for a script, nothing
         * folding up as the visitor starts to scroll, whatever WP Rocket
         * does with scripts. The hidden copy is display:none and out of the
         * accessibility tree.
         *
         * CARD. The <details> only, labelled "Hours:", on every width.
         *
         * NO EMPTY <span>. SLP hides every empty span in a result as it
         * inserts it (slp_core.js 1424, s0.274), so a span this markup left
         * empty for the script to fill would stay hidden for good. Every
         * span below carries text from the start.
         *
         * The data attribute carries the zone, its offsets and the periods
         * and nothing else; the days are already in the rows.
         */
        public static function avalon_hours_markup( $payload, $surface ){
            if ( ! is_array( $payload ) || empty( $payload['days'] ) ) {
                return '';
            }
            $card = ( 'card' === $surface );

            $rows = '';
            foreach ( $payload['days'] as $d ) {
                $rows .= '<tr data-day="' . (int) $d[0] . '"><th scope="row">' . esc_html( $d[1] )
                       . '</th><td>' . esc_html( $d[2] ) . '</td></tr>';
            }
            $table = '<table class="avalon-hours__week"><tbody>' . $rows . '</tbody></table>';

            $attr = '';
            if ( ! empty( $payload['attr'] ) ) {
                $links = array();
                foreach ( $payload['attr'] as $a ) {
                    $links[] = ( '' !== $a[1] )
                        ? '<a href="' . esc_url( $a[1] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $a[0] ) . '</a>'
                        : esc_html( $a[0] );
                }
                $attr = '<p class="avalon-hours__attr">' . implode( ' &middot; ', $links ) . '</p>';
            }

            $data = esc_attr( wp_json_encode( array(
                'tz' => (string) $payload['tz'],
                'o'  => array_values( isset( $payload['o'] ) ? (array) $payload['o'] : array() ),
                'u'  => isset( $payload['u'] ) ? (int) $payload['u'] : 0,
                'p'  => array_values( (array) $payload['periods'] ),
            ) ) );

            $summary = '<summary class="avalon-hours__summary">'
                     . ( $card ? '<b class="avalon-label">Hours:</b> ' : '' )
                     . '<span class="avalon-hours__status">See hours</span></summary>';
            $narrow  = '<details class="avalon-hours__narrow">' . $summary . $table . $attr . '</details>';

            if ( $card ) {
                return '<div class="avalon-hours avalon-hours--card" data-avalon-hours="' . $data . '">'
                     . $narrow . '</div>';
            }

            return '<div class="storelocator_address_container avalon-hours avalon-hours--store" data-avalon-hours="' . $data . '">'
                 . '<div class="store_locator_single_hours">'
                 . '<h2>Hours</h2>'
                 . '<div class="avalon-hours__wide"><p class="avalon-hours__status">&nbsp;</p>' . $table . $attr . '</div>'
                 . $narrow
                 . '</div></div>';
        }

        /**
         * v0.0.27 Part 4. The payload for one store page, or null.
         *
         * The lookup proven on DEV on 2026-10-03 against a reference
         * dealer (s0.270): the locator row that links this post, its
         * address key, then the dealer-places row by that key. ORDER BY
         * sl_id so two rows linking one page - which should share an
         * address, and so a key - resolve the same way every time.
         */
        public function avalon_hours_for_post( $post_id ){
            global $wpdb;
            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                return null;
            }
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT sl_id, sl_address, sl_city, sl_state, sl_zip, sl_country"
                . " FROM {$wpdb->prefix}store_locator"
                . " WHERE sl_linked_postid = %d ORDER BY sl_id ASC LIMIT 1",
                $post_id
            ), ARRAY_A );
            $v = self::avalon_hours_row_key( $row );
            if ( null === $v ) {
                return null;
            }
            $table = self::avalon_hours_table();
            $prow  = $wpdb->get_row( $wpdb->prepare(
                "SELECT address_key, hours_status, business_status, fetched_at, hours_json"
                . " FROM {$table} WHERE address_key = %s",
                $v['dealer_key']
            ), ARRAY_A );
            $cfg = $this->avalon_hours_config();
            return self::avalon_hours_payload( $prow, $v['country'], $v['state'], (int) $cfg['positive_ttl_days'] );
        }

        /**
         * v0.0.27 Part 4. [avalon_store_hours]
         *
         * Placed once, in Elementor Theme Builder Single template 140017, in
         * a Shortcode widget beneath 4b33b2e7 (Address, Contact). Prints
         * nothing at all off a store page, or for a dealer the gate refuses,
         * so the heading never stands over an empty block. The wrapper
         * reuses the theme's storelocator_address_container class, which is
         * what colours Address and Contact; the heading matches them by
         * construction rather than by copied values.
         *
         * The queried store page first: inside a Theme Builder template
         * get_the_ID() can answer with the template itself. get_the_ID()
         * only when the request is not a store page - the_content()
         * fallback, or a template preview.
         */
        public function avalon_store_hours_sc_func( $atts, $content = '' ){
            $post_id = is_singular( 'store_page' ) ? (int) get_queried_object_id() : 0;
            if ( $post_id <= 0 || 'store_page' !== get_post_type( $post_id ) ) {
                $post_id = (int) get_the_ID();
            }
            if ( $post_id <= 0 || 'store_page' !== get_post_type( $post_id ) ) {
                return '';
            }
            $payload = $this->avalon_hours_for_post( $post_id );
            return ( null === $payload ) ? '' : self::avalon_hours_markup( $payload, 'store' );
        }

        /**
         * v0.0.27 Part 4. A feed phone number -> "+1" and ten digits, or ''.
         *
         * Every number in the three feeds (2026-10-03) is ten digits in one
         * of five punctuations, or eleven with a leading 1; 63 are empty.
         * North American only, so a number that is not exactly that - or a
         * dealer outside the US and Canada - gets no link and keeps its
         * text. A NANP area code never starts with 0 or 1.
         */
        public static function avalon_tel( $raw, $country = 'US' ){
            if ( ! in_array( strtoupper( (string) $country ), array( 'US', 'CA' ), true ) ) {
                return '';
            }
            $d = preg_replace( '/\D/', '', (string) $raw );
            if ( 11 === strlen( $d ) && '1' === $d[0] ) {
                $d = substr( $d, 1 );
            }
            if ( 10 !== strlen( $d ) || '0' === $d[0] || '1' === $d[0] ) {
                return '';
            }
            return '+1' . $d;
        }

        /**
         * v0.0.27 Part 4. The card's Address: and Phone: labels, as marker fields.
         *
         * [slp_location X] prints nothing when X is empty, so a field that
         * carries its own label takes the label away with it - a dealer
         * with no phone shows no "Phone:". The fields are strings: SLP's
         * replace_shortcodes() calls a String method on the value, and an
         * object or array there would throw (s0.277).
         *
         * The phone text is SLP's own marker value, already esc_attr()'d;
         * the link target is built from the raw row, never from that text.
         * A phone that already carries markup - SLP Experience's
         * add_tel_to_phone, off on Aura - keeps it, unwrapped, so a link is
         * never nested inside a link.
         */
        public static function avalon_card_fields( $marker, $country ){
            $raw   = ( isset( $marker['data'] ) && is_array( $marker['data'] ) ) ? $marker['data'] : array();
            $phone = isset( $marker['phone'] ) ? trim( (string) $marker['phone'] ) : '';
            if ( '' !== $phone ) {
                $tel = ( false !== strpos( $phone, '<' ) ) ? ''
                       : self::avalon_tel( isset( $raw['sl_phone'] ) ? $raw['sl_phone'] : '', $country );
                $marker['avalon_phone_html'] = '<b class="avalon-label">Phone:</b> '
                    . ( '' !== $tel ? '<a class="avalon-tel" href="tel:' . $tel . '">' . $phone . '</a>' : $phone );
            }
            if ( '' !== ( isset( $marker['address'] ) ? trim( (string) $marker['address'] ) : '' ) ) {
                $marker['avalon_address_label'] = '<b class="avalon-label">Address:</b> ';
            }
            return $marker;
        }

        /**
         * v0.0.27 Part 4. The country a row's phone is dialled in, for tel:.
         *
         * The row's own country when it has one - written as USA, Canada or
         * anything else - and the state's only when it is blank. Never a
         * postal hint: vector() lets a five-digit postal code make a row
         * American, and a five-digit Mexican code must not earn +1. Nor the
         * state over a written country: Baja California is BC too, and
         * Mexico must not become Canada.
         */
        public static function avalon_tel_country( $row, $state ){
            $raw = ( is_array( $row ) && isset( $row['sl_country'] ) ) ? (string) $row['sl_country'] : '';
            $cc  = SLP_Avalon_AddressKey::norm_country( $raw, '', '' );
            return ( '' !== $cc ) ? $cc : SLP_Avalon_AddressKey::norm_country( '', (string) $state, '' );
        }

        /**
         * v0.0.27 Part 4. The Address: and Phone: labels, onto every marker.
         *
         * On slp_results_marker_data, which both marker builders apply -
         * SLP's slp_add_marker(), for the AJAX search and for results SLP
         * renders on the server (an SLP Power directory page), and this
         * plugin's own - so wherever the filtered results layout prints
         * [slp_location avalon_phone_html], the field is there. Priority
         * 20: after SLP Experience's modify_marker() at 15, which can have
         * linked the phone already (add_tel_to_phone, off on Aura); that
         * link is then kept, not nested. No read: the raw row is the
         * marker's own 'data' (s0.275).
         */
        public function avalon_marker_labels( $marker ){
            if ( ! is_array( $marker ) ) {
                return $marker;
            }
            $raw = ( isset( $marker['data'] ) && is_array( $marker['data'] ) ) ? $marker['data'] : null;
            $v   = self::avalon_hours_row_key( $raw );
            $cc  = ( null === $v ) ? '' : self::avalon_tel_country( $raw, $v['state'] );
            return self::avalon_card_fields( $marker, $cc );
        }

        /**
         * v0.0.27 Part 4. The hours onto every result.
         *
         * On slp_ajax_find_locations_complete at priority 30: after the
         * backfill at 10 and territory_gate at 20, so it decorates exactly
         * the markers that will be sent. Both marker builders - SLP's and
         * slp_add_marker() - carry the raw locator row as 'data' (s0.275),
         * so every key is computed from raw columns with no extra read, and
         * the hours for the whole response come back in ONE IN () query.
         *
         * A refused read leaves every card without hours and changes
         * nothing else: the search itself must never fail over hours.
         */
        public function avalon_hours_attach_markers( $results ){
            global $wpdb;
            if ( ! is_array( $results ) || empty( $results['response'] ) || ! is_array( $results['response'] ) ) {
                return $results;
            }

            $vec = array();
            $at  = array();
            foreach ( $results['response'] as $i => $m ) {
                if ( ! is_array( $m ) ) {
                    continue;
                }
                $v = self::avalon_hours_row_key( isset( $m['data'] ) ? $m['data'] : null );
                if ( null === $v ) {
                    continue;
                }
                $vec[ $v['dealer_key'] ] = $v;
                $at[ $i ]                = $v['dealer_key'];
            }
            if ( empty( $vec ) ) {
                return $results;
            }

            $table = self::avalon_hours_table();
            $keys  = array_keys( $vec );
            $rows  = $wpdb->get_results( $wpdb->prepare(
                "SELECT address_key, hours_status, business_status, fetched_at, hours_json"
                . " FROM {$table} WHERE address_key IN ("
                . implode( ', ', array_fill( 0, count( $keys ), '%s' ) ) . ")",
                $keys
            ), ARRAY_A );
            if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
                return $results;
            }

            $by = array();
            foreach ( $rows as $r ) {
                if ( is_array( $r ) && isset( $r['address_key'] ) ) {
                    $by[ (string) $r['address_key'] ] = $r;
                }
            }
            $cfg = $this->avalon_hours_config();
            foreach ( $at as $i => $k ) {
                if ( ! isset( $by[ $k ] ) ) {
                    continue;
                }
                $p = self::avalon_hours_payload( $by[ $k ], $vec[ $k ]['country'], $vec[ $k ]['state'],
                                                 (int) $cfg['positive_ttl_days'] );
                if ( null !== $p ) {
                    $results['response'][ $i ]['avalon_hours_html'] = self::avalon_hours_markup( $p, 'card' );
                }
            }
            return $results;
        }

        /**
         * v0.0.27 Part 4. The labels and the hours slot, into the results layout.
         *
         * On slp_javascript_results_string, which SLP applies to the layout
         * before handing it to the browser raw - add_to_js_options() calls
         * set_ResultsLayout( false, true ), so markup added here arrives
         * intact (s0.276), and SLP's format() passes unknown field names
         * through unchanged. Versioned in the plugin rather than typed into
         * the results layout in three databases.
         *
         * PRIORITY 100, AFTER SLP EXPERIENCE. Experience hooks this filter
         * at 90 and its modify_results_layout() starts from the stored
         * layout, discarding whatever it was handed (s0.279). Anything
         * earlier than 90 is thrown away on Aura.
         *
         *   Address:  at the start of the street line
         *   Phone:    [slp_location phone] becomes the labelled tel: field
         *   Hours:    right after the phone line - Google's order
         *
         * Each step finds its own anchor and is skipped, not forced, when
         * the anchor is absent: a layout this does not recognise is left as
         * it was. Each step is also skipped when its own field is already
         * there, so a second pass changes nothing - SLP builds the layout
         * more than once a page, and avalon_js_options_layout() runs this
         * again on purpose.
         */
        public function avalon_results_layout( $layout ){
            $layout = (string) $layout;
            if ( false === strpos( $layout, 'avalon_address_label' ) ) {
                $layout = preg_replace(
                    '/(<span\b[^>]*\bclass="[^"]*\bslp_result_street\b[^"]*"[^>]*>)/',
                    '$1[slp_location avalon_address_label]',
                    $layout, 1 );
            }
            if ( false === strpos( $layout, 'avalon_hours_html' ) ) {
                $n = 0;
                $layout = preg_replace(
                    '/(<span\b[^>]*\bclass="[^"]*\bslp_result_phone\b[^"]*"[^>]*>)\s*\[slp_location phone\]\s*(<\/span>)/',
                    '$1[slp_location avalon_phone_html]$2[slp_location avalon_hours_html]',
                    $layout, 1, $n );
                if ( 0 === $n ) {
                    $layout = preg_replace(
                        '/(<span\b[^>]*\bclass="[^"]*\bslp_result_citystatezip\b[^"]*"[^>]*>.*?<\/span>)/s',
                        '$1[slp_location avalon_hours_html]',
                        $layout, 1 );
                }
            }
            return $layout;
        }

        /**
         * v0.0.27 Part 4. The same fields, on the layout the browser is given.
         *
         * SLP puts the results layout into its script options on
         * slp_js_options at 10 (add_to_js_options()), already through
         * avalon_results_layout(). SLP Experience merges its own stored
         * settings over those options at 90, and a results layout left in
         * them would replace ours whole: no labels, no tel: link, no hours.
         * Running the filter again here, at 100, puts the fields into
         * whichever layout won. On a site with no such leftover it changes
         * nothing.
         */
        public function avalon_js_options_layout( $options ){
            if ( is_array( $options ) && isset( $options['resultslayout'] ) && is_string( $options['resultslayout'] ) ) {
                $options['resultslayout'] = $this->avalon_results_layout( $options['resultslayout'] );
            }
            return $options;
        }

        /**
         * v0.0.27 Part 4. Elementor's mobile breakpoint, in px, or 767.
         *
         * The store page stacks its two columns at this width, so it is
         * where the hours fold away. avalon-hours.css carries 767px, which
         * is Elementor's default; avalon_hours_enqueue() adds an override
         * only when a site has moved its breakpoint.
         */
        public static function avalon_hours_breakpoint(){
            $bp = 767;
            if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) ) {
                try {
                    $mobile = \Elementor\Plugin::$instance->breakpoints->get_breakpoints( 'mobile' );
                    if ( is_object( $mobile ) && method_exists( $mobile, 'get_value' ) ) {
                        $v = (int) $mobile->get_value();
                        if ( $v >= 320 && $v <= 1200 ) {
                            $bp = $v;
                        }
                    }
                } catch ( \Throwable $e ) {
                    $bp = 767;
                }
            }
            return $bp;
        }

        /**
         * v0.0.27 Part 4. The hours stylesheet and script, on the front end.
         *
         * Everywhere slp_avalon.js already is, which today is every front
         * end page: the locator's cards can appear wherever SLP renders, and
         * both files do nothing on a page without an hours block. The
         * script has no dependencies and loads in the footer.
         */
        public function avalon_hours_enqueue(){
            if ( is_admin() ) {
                return;
            }
            wp_enqueue_style( 'avalon-hours', ASLP_URL . 'assets/css/avalon-hours.css', array(),
                              self::file_version( 'assets/css/avalon-hours.css' ) );
            $bp = self::avalon_hours_breakpoint();
            if ( 767 !== $bp ) {
                wp_add_inline_style( 'avalon-hours',
                    '@media (min-width:768px) and (max-width:' . $bp . 'px){'
                    . '.avalon-hours--store .avalon-hours__wide{display:none}'
                    . '.avalon-hours--store .avalon-hours__narrow{display:block}}'
                    . '@media (min-width:' . ( $bp + 1 ) . 'px) and (max-width:767px){'
                    . '.avalon-hours--store .avalon-hours__wide{display:block}'
                    . '.avalon-hours--store .avalon-hours__narrow{display:none}}' );
            }
            wp_enqueue_script( 'avalon-hours', ASLP_URL . 'assets/js/avalon-hours.js', array(),
                               self::file_version( 'assets/js/avalon-hours.js' ), true );
        }

        /**
         * v0.0.27 Part 4. WP Rocket: never delay the hours script.
         *
         * Delayed until the first tap or scroll, the Open/Closed line would
         * stay "See hours" on a page nobody has touched. The pattern is
         * matched against the script's src, and taken from the plugin's own
         * URL rather than assuming its folder name. A no-op where WP Rocket
         * is not installed: nothing applies the filter.
         */
        public static function avalon_rocket_delay_exclusions( $list ){
            $list   = is_array( $list ) ? $list : array();
            $list[] = wp_make_link_relative( ASLP_URL ) . 'assets/js/avalon-hours';
            return $list;
        }

        /**
         * v0.0.27 Part 4. WP Rocket: keep the hours styles through Remove Unused CSS.
         *
         * Result cards are built in the browser after an AJAX search, so
         * their classes are not in the HTML that Remove Unused CSS reads,
         * and their rules would be stripped - the reason the child theme's
         * style.css is already on Aura's safelist. Listing the file here
         * makes that travel with the plugin to Tahoe and Avalon. The
         * selectors are a second line, written the way WP Rocket 3.11.0.2
         * and later read them - from the start of a selector, so a leading
         * (.*) lets a.avalon-tel match.
         *
         * Used CSS already stored for a page is not rebuilt by this: after
         * a deploy, WP Rocket's Clear Used CSS.
         */
        public static function avalon_rocket_rucss_safelist( $list ){
            $list   = is_array( $list ) ? $list : array();
            $list[] = wp_make_link_relative( ASLP_URL ) . 'assets/css/avalon-hours.css';
            $list[] = '(.*).avalon-hours(.*)';
            $list[] = '(.*).avalon-label(.*)';
            $list[] = '(.*).avalon-tel(.*)';
            return $list;
        }

""")

NEW_METHODS = (
    'private function avalon_google_browser_key(){',
    'private function avalon_google_server_key(){',
    'public static function avalon_hours_zone_candidates(){',
    'public static function avalon_hours_zone( $country, $state, $offset_min, $at_gmt ){',
    'public static function avalon_hours_crossing( $a, $b, $at ){',
    'public static function avalon_hours_rules_current( $name ){',
    'public static function avalon_hours_line( $line ){',
    'public static function avalon_hours_day_number( $name ){',
    'public static function avalon_hours_offsets( $tz, $now ){',
    'public static function avalon_hours_pt( $p ){',
    'public static function avalon_hours_attributions( $list ){',
    'public static function avalon_hours_row_key( $row ){',
    'public static function avalon_hours_payload( $prow, $country, $state, $max_age_days = 30, $now = null ){',
    'public static function avalon_hours_markup( $payload, $surface ){',
    'public function avalon_hours_for_post( $post_id ){',
    "public function avalon_store_hours_sc_func( $atts, $content = '' ){",
    "public static function avalon_tel( $raw, $country = 'US' ){",
    'public static function avalon_card_fields( $marker, $country ){',
    'public static function avalon_tel_country( $row, $state ){',
    'public function avalon_marker_labels( $marker ){',
    'public function avalon_hours_attach_markers( $results ){',
    'public function avalon_results_layout( $layout ){',
    'public function avalon_js_options_layout( $options ){',
    'public static function avalon_hours_breakpoint(){',
    'public function avalon_hours_enqueue(){',
    'public static function avalon_rocket_delay_exclusions( $list ){',
    'public static function avalon_rocket_rucss_safelist( $list ){',
)


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part4.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part4  slp_avalon 0.0.27  PART 4 of 4 (show the hours, s0.273 key swap)")
    print("")

    name = 'class.slp_avalon.php'
    md5, size = PINS[name]
    php, got_md5, got_size = read_exact(os.path.join(src_dir, name))
    if got_md5 != md5 or got_size != size:
        sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                 "       src_dir must hold the PART 3b OUTPUT.".format(
                     name, got_md5, got_size, md5, size))
    print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
    print("")

    braces_before = (php.count('{'), php.count('}'))
    check(all(ord(c) < 128 for c in DISPLAY_BLOCK), 'the inserted block is pure ASCII')
    check('\t' not in DISPLAY_BLOCK, 'the inserted block has no tabs')

    # ---- patches ----------------------------------------------------------
    php = sub_once(php, LOADER_OLD, LOADER_NEW, 's0.273 Maps loader prints the Browser Key only')
    php = sub_once(php, GEOCODE_OLD, GEOCODE_NEW, 's0.273 import geocoding sends the server key')
    php = sub_once(php, HOURS_OLD, HOURS_NEW, 's0.273 hours fetch sends the server key')
    php = sub_once(php, RESOLVE_OLD, RESOLVE_NEW, 's0.273 place resolver sends the server key')
    php = sub_once(php, WIRE_ANCHOR, WIRE_BLOCK, 'enqueue, marker, layout, script-option and WP Rocket registrations')
    php = sub_once(php, SHORTCODE_ANCHOR, SHORTCODE_BLOCK, '[avalon_store_hours] registration')
    php = sub_once(php, INSERT_BEFORE, DISPLAY_BLOCK + INSERT_BEFORE, 'the Part 4 display block')
    print("")

    # ---- self-checks ------------------------------------------------------
    check("\r\n" in php and php.count("\n") == php.count("\r\n") and php.count("\r") == php.count("\r\n"),
          'the file is pure CRLF - no bare LF, no bare CR')

    for m in NEW_METHODS:
        check(php.count(m) == 1, 'method declared once: ' + m.split('(')[0].split()[-1])

    # s0.273: the two option names are read only inside the two helpers.
    i = php.find('        private function avalon_google_browser_key(){')
    j = php.find('        /**\r\n         * v0.0.27 Part 4. The IANA zones each state or province can be in.')
    helpers, rest = php[i:j], php[:i] + php[j:]
    check(rest.count('SmartOptions->google_geocode_key') == 0,
          's0.273: google_geocode_key is read nowhere but avalon_google_server_key()')
    reads = [ln for ln in rest.split('\r\n')
             if 'SmartOptions->google_server_key' in ln and not ln.lstrip().startswith('//')]
    check(len(reads) == 0, 's0.273: google_server_key is read nowhere but avalon_google_browser_key()')
    check(helpers.count('SmartOptions->google_geocode_key->value') == 2
          and helpers.count('SmartOptions->google_server_key->value') == 2,
          's0.273: the two helpers read the two fields, each where SLP says it belongs')
    k = php.find('public function splus_get_google_maps_url(){')
    loader = php[k:php.find('public function slp_ajax_find_locations_complete_filter($results){', k)]
    check('$the_key = $this->avalon_google_browser_key();' in loader
          and 'avalon_google_server_key' not in loader,
          's0.273: the loader prints the Browser Key and never the server key')
    check(php.count('$this->avalon_google_server_key()') == 3,
          's0.273: exactly three callers send the server key')
    check(php.count('$this->avalon_google_browser_key()') == 2,
          's0.273: the loader and the server-key fallback read the Browser Key')

    for reg in ("add_action('wp_enqueue_scripts', array(self::$instance,'avalon_hours_enqueue'), 20);",
                "add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_labels'), 20, 1);",
                "add_filter('slp_ajax_find_locations_complete', array(self::$instance,'avalon_hours_attach_markers'), 30, 1);",
                "add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout'), 100, 1);",
                "add_filter('slp_js_options', array(self::$instance,'avalon_js_options_layout'), 100, 1);",
                "add_filter('rocket_delay_js_exclusions', array('SLP_Avalon','avalon_rocket_delay_exclusions'));",
                "add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist'));",
                "add_shortcode('avalon_store_hours', array(self::$instance,'avalon_store_hours_sc_func'));"):
        check(php.count(reg) == 1, 'registered once: ' + reg.split("'")[1])

    a = php.find('        /**\r\n         * v0.0.27 Part 4. Showing the hours.')
    b = php.find('        public function avalon_rest_protected_slugs(){')
    block = php[a:b]
    check(0 < a < b, 'the display block sits directly before avalon_rest_protected_slugs()')
    code = re.sub(r'/\*.*?\*/', '', block, flags=re.S)
    code = re.sub(r'(?m)^\s*//.*$', '', code)
    for banned in ('openNow', 'open_now', "['raw']", 'wp_remote_', 'googleapis'):
        check(banned not in code, 'display block code never mentions ' + banned)
    check(block.count('$wpdb->prepare(') == 3 and block.count('$wpdb->get_') == 3,
          'display block: three reads, every one through prepare()')
    check(block.count('WHERE address_key') == 2 and 'WHERE sl_id' not in block,
          'display block: dealer-places is read by address key, never by sl_id')

    # Regions Part 4 does not touch must still be there, once.
    for keep in ('public function avalon_hours_sweep( $limit = 0, $dry = false ){',
                 'public function avalon_places_purge(){',
                 'private static function avalon_hours_verdict( $status ){',
                 'public function avalon_hours_cli( $args, $assoc = array() ){',
                 'public function avalon_rest_strip_keys( $result, $server = null, $request = null ){',
                 'public function avalon_map_location_sc_func($atts, $content = "")'):
        check(php.count(keep) == 1, 'untouched and present once: ' + keep.split('(')[0].split()[-1])

    check(php.count('{') == php.count('}'), 'php braces balance')
    check(php.count('{') > braces_before[0], 'the braces grew, as twenty-seven methods were added')
    check(php.count('private static $avalon_hours_rules = array();') == 1,
          'the zone-rules cache is declared once')
    print("")

    raw = php.encode('iso-8859-1')
    got = (hashlib.md5(raw).hexdigest(), len(raw))
    if got != OUT_PIN:
        sys.exit("ABORT output is {} / {} bytes, expected {} / {} - nothing written".format(
            got[0], got[1], OUT_PIN[0], OUT_PIN[1]))
    out_md5, out_size, crs = write_exact(os.path.join(out_dir, name), php)
    print("  output    {:<24} {} {} bytes  CR={}  (pinned)".format(name, out_md5, out_size, crs))
    print("")
    print("  note      slp_avalon.php is not an input at Part 4; the version")
    print("            header already reads 0.0.27 from Part 1.")
    print("  note      no schema change. HOURS_DB_VERSION stays 2.")
    print("  note      avalon-hours.css and avalon-hours.js are new files,")
    print("            tracked as written and pinned in release-pins.csv.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
