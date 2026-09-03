#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.15 from the shipped v0.0.14 artefacts.

Issue 26 (ten dealers at 0,0) and Issue 27 (coordinates that disagree with
their own address by up to 570 miles). Handoff rev 13 ADDENDUM s15, corrected
by this session's measurements - see the section headed CORRECTIONS below.

INPUTS   class.slp_avalon.php   f6a07b929ceed4de0d6bd5fa034eda6b
         slp_avalon.php         e61ce5ea8e64e5b5a6f89649fa623afd
slp_avalon.js is NOT touched by this release.

USAGE    Run from the repo root:

             cd D:\\Temp\\Projects\\GitHub\\slp-plugins
             python build\\build-v015.py

         Unlike build-v008 through build-v014, which required the sources
         copied flat into a scratch directory, this build reads them where
         they actually live and writes beside itself:

             in   slp_avalon\\inc\\class.slp_avalon.php
             in   slp_avalon\\slp_avalon.php
             out  build\\out15\\

         Optional first argument overrides the repo root, second the output
         directory. Add build/out*/ to .gitignore with this release.

---------------------------------------------------------------------------
CORRECTIONS to rev13 s15, all measured this session
---------------------------------------------------------------------------

s15 said "uncommenting line 376 fixes Issue 26". Line 376 sits at brace depth
2, directly in the body of class SLP_Avalon, between two method definitions.
A bare add_filter() call there is a PHP parse error - it would have taken down
all six sites on load. The hook goes in add_actions() with the other twelve
registrations, and this build retires the misleading comment.

Priority is 20, and the number is forced. SLP Power's prepare_for_import()
registers add_sl_to_base_fieldnames at 8, which is what creates the sl_* keys
we read; strip_extra_spaces_from_csv_location_data at 10; and
create_categories_from_location_data at 30. Earlier than 8 and there are no
sl_* keys. Later than 30 and we would be fighting the category manager.

s15's diagnosis of Issue 26 is not the operative cause. is_valid_lat() really
does return true for "0.000000000" - the regex at SLPlus_Location.php:1393 was
executed against that exact string - but SLP_Power_Locations_Import::import()
hard-codes $this->skip_geocoding = true at line 338 before the row loop ever
starts, and set_options_from_meta() at 671 then overwrites it from the FROZEN
cron parameter. The is_valid_lat() clause is the second operand of a || that
may never be evaluated. It does not change the fix: this filter geocodes for
itself at priority 20, before add_to_database() at 864 is reached, so whatever
skip_geocoding holds is irrelevant to us.

process_File() at 901 is dead code - private, zero callers, the same shape as
load_directly_into_mysql() at 426 - so open_file_and_start_importing() has
exactly one live call site at 343, not the two s15 lists. Both the manual
upload and the nightly cron funnel through import() at 307, which is why a
manual CSV upload on Aura DEV is a valid acceptance test for this release and
we do not have to wait for 04:47:22Z to see the log.

---------------------------------------------------------------------------
WHAT SHIPS
---------------------------------------------------------------------------

Tier 1, Issue 26. A row whose coordinates are absent, blank, non-numeric,
out of range, or exactly 0,0 is geocoded from its own address and written.
The existing add_lat_lng_before_csv_import() was never wired up and carried
six defects, all of which this build fixes:

  1  (int) cast     (int)"-9838239.000000000" is truthy, so the one genuinely
                    out-of-range row in the Aura feed - Premier Boating
                    Centers, longitude -9838239 - walked straight through the
                    gate. Replaced with is_numeric() plus a range test.
  2  undefined idx  add_sl_to_base_fieldnames only copies a value when
                    ! empty(), so a blank Latitude cell means sl_latitude
                    never exists. SLP backfills it with '' at line 850 - one
                    line AFTER our filter. Reads are isset()-guarded.
  3  no timeout     bare curl_exec with no CURLOPT_TIMEOUT could hang a cron
                    run indefinitely. Replaced with wp_remote_get and an
                    explicit, configurable timeout.
  4  0,0 accepted   the old code wrote whatever Google returned, including
                    0,0, which is how a zero row stays a zero row.
  5  no validation  a geocode landing outside served territory was written
                    without complaint. Now gated through is_in_territory().
  6  no logging     nothing recorded what was changed or why.

Tier 2, Issue 27. A row whose coordinates ARE sane is geocoded from its
address and the two points compared with vincentyGreatCircleDistance(). At or
beyond the correction threshold the geocode wins; between the observation
floor and the threshold nothing is written and the disagreement is logged.

The observation band is the point of the design. The correction threshold was
chosen from eleven rows measured on one day: false positives under a mile,
true positives at 5, 20, 47, 70, 70, 80, 140, 160, 207, 250 and 570 miles.
Ten miles has no measured false positive but skips the 5-mile row (Midwest
Assets, Russells Point vs Lakeview OH - adjacent villages on Indian Lake).
Rather than guess again next release, every row between 2 and 10 miles is
logged with its distance, so the next threshold decision is made from the
real distribution across all three feeds.

Tier 2 writes over data we do not own, so it carries three rails:

  correction cap   25 per import. Steady state is 11 rows on Aura, 6 on
                   Avalon, 2 on Tahoe. Tripping this means something
                   systemic - revoked key, degraded results, a shifted CSV
                   column - and the whole Tier 2 pass aborts rather than
                   rewriting the dealer table on a live site.
  geocode budget   150 per import. A cold cache on Aura needs 308 geocodes;
                   at an 8-second timeout that is a 40-minute worst case
                   bolted onto a cron run. The budget bounds the added
                   runtime and warms the cache over two or three nights.
  territory gate   a corrected coordinate must pass is_in_territory(), the
                   same predicate Layer 3 applies to search results. Reused,
                   not reimplemented.

Two rows are excluded from Tier 2 by name, and neither exclusion is
cosmetic. See avalon_tier2_exclusions() for the reasoning.

Everything is switchable from wp-config.php without a deploy. Nothing in this
release changes what the CSV supplies, so disabling the constants and letting
one import run restores the previous state within 24 hours.
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "class.slp_avalon.php": "f6a07b929ceed4de0d6bd5fa034eda6b",
    "slp_avalon.php": "e61ce5ea8e64e5b5a6f89649fa623afd",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


# ---------------------------------------------------------------------------
# EDIT 1 - register the hooks in add_actions(), where statements are legal.
# ---------------------------------------------------------------------------

# Anchored from the newline so the indent cannot be partially matched. The
# body of add_actions() is indented twelve spaces, not eight; an eight-space
# anchor matches the last eight of the twelve, replaces cleanly, and silently
# inserts the new block at the wrong depth.
HOOK_OLD = (
    b"\r\n            add_filter('slp_ajax_find_locations_complete',"
    b"array(self::$instance,'territory_gate'),20,1);\r\n"
)

HOOK_NEW = HOOK_OLD + (
    b"            // SLP Dealer Guard, import hygiene. Issue 26 and Issue 27.\r\n"
    b"            //\r\n"
    b"            // Priority 20 is forced, not chosen. SLP Power's\r\n"
    b"            // prepare_for_import() puts add_sl_to_base_fieldnames on this\r\n"
    b"            // hook at 8 - that callback is what creates the sl_* keys we\r\n"
    b"            // read - strip_extra_spaces_from_csv_location_data at 10, and\r\n"
    b"            // create_categories_from_location_data at 30. Run before 8 and\r\n"
    b"            // there is nothing to read; run after 30 and we are fighting\r\n"
    b"            // the category manager.\r\n"
    b"            add_filter('slp_csv_locationdata',"
    b"array(self::$instance,'avalon_import_coordinate_guard'),20,1);\r\n"
    b"            //\r\n"
    b"            // Priority 500 on completion: after csv_processing_complete_func\r\n"
    b"            // at 10 has finished reconciling the table against the CSV, and\r\n"
    b"            // before remove_old_csv_files_after_import at 999 clears the\r\n"
    b"            // working directory out from under us.\r\n"
    b"            add_action('slp_csv_processing_complete',"
    b"array(self::$instance,'avalon_flush_import_log'),500);\r\n"
)


# ---------------------------------------------------------------------------
# EDIT 2 - replace the never-wired function and the comment that would have
#          fataled the site if anyone had followed it.
# ---------------------------------------------------------------------------

GUARD_OLD = (
    b"        //This function should geocode a vendor before it is added to the database\r\n"
    b"        //If the default geocoding doesn't work, uncomment the line below to activate the custom geocoding\r\n"
    b"        //add_filter('slp_csv_locationdata', array(self::$instance,'add_lat_lng_before_csv_import'));\r\n"
    b"        public function add_lat_lng_before_csv_import($location_data)\r\n"
    b"        {\r\n"
    b"            // return $location_data;\r\n"
    b"            $lat = (int)$location_data['sl_latitude'];\r\n"
    b"            $lng = (int)$location_data['sl_longitude'];\r\n"
    b"            if (!$lat || !$lng) {\r\n"
    b"                //We need to geolocate\r\n"
    b"                $address = implode(\",\", array_filter(array($location_data['sl_address'], "
    b"$location_data['sl_city'], $location_data['sl_state'], $location_data['sl_zip'], "
    b"$location_data['sl_country'])));\r\n"
    b"                $geocode_response = $this->geocode_from_address($address);\r\n"
    b"                if ($geocode_response['success']) {\r\n"
    b"                    $location_data['sl_latitude'] = $geocode_response['lat'];\r\n"
    b"                    $location_data['sl_longitude'] = $geocode_response['lng'];\r\n"
    b"                }\r\n"
    b"            }\r\n"
    b"            return $location_data;\r\n"
    b"        }\r\n"
)

GUARD_NEW = b"""        /**
         * Import coordinate hygiene. Filter on slp_csv_locationdata, prio 20.
         *
         * Runs once per CSV row, on both the manual upload and the nightly
         * cron - they converge on SLP_Power_Locations_Import::import() at 307,
         * so there is one code path here, not two.
         *
         * Tier 1  coordinates absent, blank, non-numeric, out of range or
         *         exactly 0,0     -> geocode from the address and write.
         * Tier 2  coordinates sane but disagreeing with their own address by
         *         at least the correction threshold -> geocode wins.
         *         Between the observation floor and the threshold, nothing is
         *         written and the disagreement is logged.
         *
         * This runs BEFORE add_to_database() at 864, so it does not matter
         * what skip_geocoding holds - we geocode for ourselves.
         *
         * @param  mixed[] $location_data
         * @return mixed[]
         */
        public function avalon_import_coordinate_guard($location_data){
            $cfg = $this->avalon_import_config();

            $store = $this->avalon_field($location_data, 'sl_store');
            if ($store === '') {
                $store = $this->avalon_field($location_data, 'name');
            }
            $city  = $this->avalon_field($location_data, 'sl_city');
            $state = $this->avalon_field($location_data, 'sl_state');

            // SLP backfills a missing sl_latitude with '' at line 850, one
            // line after this filter, so at priority 20 the key can genuinely
            // be absent. Never index it unguarded.
            $lat_raw = $this->avalon_field($location_data, 'sl_latitude');
            $lng_raw = $this->avalon_field($location_data, 'sl_longitude');

            $address = implode(', ', array_filter(array(
                $this->avalon_field($location_data, 'sl_address'),
                $this->avalon_field($location_data, 'sl_address2'),
                $city,
                $state,
                $this->avalon_field($location_data, 'sl_zip'),
                $this->avalon_field($location_data, 'sl_country')
            )));

            // Nothing to geocode against. Leave the row exactly as it came in.
            if ($address === '') {
                return $location_data;
            }

            $sane = $this->avalon_coord_is_sane($lat_raw, $lng_raw);
            $tier = $sane ? 2 : 1;

            if ($tier === 1 && ! $cfg['tier1']) {
                return $location_data;
            }
            if ($tier === 2 && ! $cfg['tier2']) {
                return $location_data;
            }

            // The correction cap is a circuit breaker, not a quota. Once it
            // trips, Tier 2 is done for this import - a systemic failure that
            // wants to move 200 rows must not be allowed to move the first 25
            // and then stop half way.
            if ($tier === 2 && $this->avalon_state('tier2_aborted')) {
                return $location_data;
            }

            if ($tier === 2 && $this->avalon_tier2_is_excluded($store, $city, $state)) {
                $this->avalon_state_bump('excluded');
                $this->avalon_note_exclusion_hit($store, $city, $state);
                return $location_data;
            }

            $geo = $this->avalon_geocode_cached($address);
            if (! $geo['success']) {
                $this->avalon_import_log(array(
                    'tier'   => $tier,
                    'action' => 'geocode_failed',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'reason' => $geo['error']
                ));
                return $location_data;
            }

            // A geocode that lands outside served territory is rejected on the
            // same predicate Layer 3 applies to search results. 0,0 is the
            // Gulf of Guinea and is refused here, which is defect 4.
            if (! $this->is_in_territory($geo['lat'], $geo['lng'])) {
                $this->avalon_import_log(array(
                    'tier'   => $tier,
                    'action' => 'rejected_out_of_territory',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'to'     => $geo['lat'] . ',' . $geo['lng']
                ));
                return $location_data;
            }

            if ($tier === 1) {
                $location_data['sl_latitude']  = $geo['lat'];
                $location_data['sl_longitude'] = $geo['lng'];
                $this->avalon_state_bump('tier1_written');
                $this->avalon_import_log(array(
                    'tier'   => 1,
                    'action' => 'geocoded',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'from'   => $lat_raw . ',' . $lng_raw,
                    'to'     => $geo['lat'] . ',' . $geo['lng']
                ));
                return $location_data;
            }

            $miles = $this->vincentyGreatCircleDistance(
                (float) $lat_raw, (float) $lng_raw,
                (float) $geo['lat'], (float) $geo['lng']
            ) / 1609.344;

            if ($miles < $cfg['observe_mi']) {
                return $location_data;   // ordinary geocoder disagreement
            }

            if ($miles < $cfg['correct_mi']) {
                $this->avalon_state_bump('observed');
                $this->avalon_import_log(array(
                    'tier'   => 2,
                    'action' => 'observed_not_corrected',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'miles'  => round($miles, 2),
                    'from'   => $lat_raw . ',' . $lng_raw,
                    'to'     => $geo['lat'] . ',' . $geo['lng']
                ));
                return $location_data;
            }

            if ($this->avalon_state('tier2_written') >= $cfg['max_corrections']) {
                $this->avalon_state_set('tier2_aborted', true);
                $this->avalon_import_log(array(
                    'tier'   => 2,
                    'action' => 'ABORTED_correction_cap',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'reason' => 'cap of ' . $cfg['max_corrections'] . ' reached; '
                                . 'no further Tier 2 writes this import'
                ));
                return $location_data;
            }

            $location_data['sl_latitude']  = $geo['lat'];
            $location_data['sl_longitude'] = $geo['lng'];
            $this->avalon_state_bump('tier2_written');
            $this->avalon_import_log(array(
                'tier'   => 2,
                'action' => 'corrected',
                'store'  => $store,
                'where'  => $city . ', ' . $state,
                'miles'  => round($miles, 2),
                'from'   => $lat_raw . ',' . $lng_raw,
                'to'     => $geo['lat'] . ',' . $geo['lng']
            ));

            return $location_data;
        }

        /**
         * Read a key that may not exist yet, trimmed, as a string.
         *
         * add_sl_to_base_fieldnames copies a CSV value to its sl_ key only
         * when ! empty(), and empty('0') is true in PHP, so both a blank cell
         * and a cell holding a bare 0 leave the sl_ key undefined.
         */
        private function avalon_field($location_data, $key){
            if (! isset($location_data[$key])) {
                return '';
            }
            if (is_array($location_data[$key]) || is_object($location_data[$key])) {
                return '';
            }
            return trim((string) $location_data[$key]);
        }

        /**
         * Are these coordinates usable as they stand?
         *
         * Deliberately NOT the (int) cast the previous revision used.
         * (int)"-9838239.000000000" is -9838239, which is truthy, so the one
         * genuinely broken longitude in the Aura feed passed the old gate
         * untouched. A blank, a non-number, anything outside the physical
         * range, and exactly 0,0 all count as unusable.
         */
        private function avalon_coord_is_sane($lat, $lng){
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return false;
            }
            $lat = (float) $lat;
            $lng = (float) $lng;
            if (! is_finite($lat) || ! is_finite($lng)) {
                return false;
            }
            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                return false;
            }
            if ($lat === 0.0 && $lng === 0.0) {
                return false;
            }
            return true;
        }

        /**
         * Import hygiene configuration.
         *
         * Every value is overridable from wp-config.php, so a single site can
         * be changed without a deploy and without touching the other five.
         * The defaults are the shipped behaviour.
         */
        public function avalon_import_config(){
            return array(
                'tier1'           => defined('AVALON_IMPORT_GEOCODE_TIER1')
                                     ? (bool)  AVALON_IMPORT_GEOCODE_TIER1   : true,
                'tier2'           => defined('AVALON_IMPORT_GEOCODE_TIER2')
                                     ? (bool)  AVALON_IMPORT_GEOCODE_TIER2   : true,
                'correct_mi'      => defined('AVALON_TIER2_CORRECT_MI')
                                     ? (float) AVALON_TIER2_CORRECT_MI       : 10.0,
                'observe_mi'      => defined('AVALON_TIER2_OBSERVE_MI')
                                     ? (float) AVALON_TIER2_OBSERVE_MI       : 2.0,
                'max_corrections' => defined('AVALON_TIER2_MAX_CORRECTIONS')
                                     ? (int)   AVALON_TIER2_MAX_CORRECTIONS  : 25,
                'geocode_budget'  => defined('AVALON_IMPORT_GEOCODE_BUDGET')
                                     ? (int)   AVALON_IMPORT_GEOCODE_BUDGET  : 150,
                'timeout'         => defined('AVALON_IMPORT_GEOCODE_TIMEOUT')
                                     ? (int)   AVALON_IMPORT_GEOCODE_TIMEOUT : 8,
            );
        }

        /**
         * Rows Tier 2 must never move.
         *
         * DONNIE MARCH, Howell MI, carries I-94 Marine's Belleville
         * coordinates - the two rows are 0.52 metres apart and share
         * identifier CDMII9114. Correcting it would publish a private
         * residence as a dealer location. Suppressing it instead would delete
         * the record that same night, because csv_processing_complete_func()
         * removes every location whose hash is absent from
         * avalon_updated_slp_locations, and currentLocation->delete() leaves
         * the store_page post orphaned: the Aura DEV sitemap carries 321
         * entries against 308 records, and I-94 Marine alone holds three
         * permalinks from exactly that delete-and-recreate churn. So it stays.
         *
         * C/O Cole International USA is a customs broker in Pembina ND acting
         * for a dealer in Lac Du Bonnet MB. The stored coordinates are the
         * dealer's, the address is the broker's, and neither is wrong enough
         * to overwrite the other.
         *
         * Matched on store + city + state so a zip or whitespace fix in the
         * feed cannot silently un-exclude a row. avalon_flush_import_log()
         * warns when an entry stops matching anything at all, which is the
         * signal that a dealer was renamed and the decision needs revisiting.
         */
        public function avalon_tier2_exclusions(){
            return array(
                'DONNIE MARCH|HOWELL|MI',
                'C/O COLE INTERNATIONAL USA|PEMBINA|ND',
            );
        }

        private function avalon_exclusion_key($store, $city, $state){
            $norm = function ($v) {
                return preg_replace('/\\s+/', ' ', strtoupper(trim((string) $v)));
            };
            return $norm($store) . '|' . $norm($city) . '|' . $norm($state);
        }

        private function avalon_tier2_is_excluded($store, $city, $state){
            return in_array(
                $this->avalon_exclusion_key($store, $city, $state),
                $this->avalon_tier2_exclusions(),
                true
            );
        }

        private function avalon_note_exclusion_hit($store, $city, $state){
            $key = $this->avalon_exclusion_key($store, $city, $state);
            $hit = $this->avalon_state('exclusion_hits');
            if (! is_array($hit)) {
                $hit = array();
            }
            $hit[$key] = true;
            $this->avalon_state_set('exclusion_hits', $hit);
        }

        /**
         * Geocode an address, cached, inside a per-import budget.
         *
         * The cache is a single non-autoloaded option keyed on the md5 of the
         * normalised address, so a steady-state import performs zero network
         * calls and a single delete_option() clears it. The budget bounds how
         * much a cold cache can add to one import: Aura needs 308 geocodes
         * from cold, which at the configured timeout is a worst case far
         * longer than any cron run should take. Rows beyond the budget are
         * simply not corrected tonight; the cache warms over two or three
         * imports and completes itself.
         */
        private function avalon_geocode_cached($address){
            $key   = md5(preg_replace('/\\s+/', ' ', strtoupper(trim($address))));
            $cache = $this->avalon_state('geocode_cache');

            if (! is_array($cache)) {
                $cache = get_option('avalon_geocode_cache');
                if (! is_array($cache)) {
                    $cache = array();
                }
                $this->avalon_state_set('geocode_cache', $cache);
            }

            if (isset($cache[$key]['lat'], $cache[$key]['lng'])) {
                return array(
                    'success' => true,
                    'lat'     => $cache[$key]['lat'],
                    'lng'     => $cache[$key]['lng']
                );
            }

            $cfg = $this->avalon_import_config();
            if ($this->avalon_state('geocodes_spent') >= $cfg['geocode_budget']) {
                return array('success' => false, 'error' => 'geocode budget exhausted');
            }

            $this->avalon_state_bump('geocodes_spent');
            $geo = $this->geocode_from_address($address);

            if (! empty($geo['success'])) {
                $cache[$key] = array(
                    'lat' => $geo['lat'],
                    'lng' => $geo['lng'],
                    'ts'  => time()
                );
                $this->avalon_state_set('geocode_cache', $cache);
                $this->avalon_state_set('cache_dirty', true);
            }

            return $geo;
        }

        /**
         * Per-import scratch state. Held on the instance, never in a global.
         */
        private $avalon_import_state = null;

        private function avalon_state($key){
            if (! is_array($this->avalon_import_state)) {
                $this->avalon_import_state = array();
            }
            if (! isset($this->avalon_import_state[$key])) {
                return 0;
            }
            return $this->avalon_import_state[$key];
        }

        private function avalon_state_set($key, $value){
            if (! is_array($this->avalon_import_state)) {
                $this->avalon_import_state = array();
            }
            $this->avalon_import_state[$key] = $value;
        }

        private function avalon_state_bump($key){
            $this->avalon_state_set($key, ((int) $this->avalon_state($key)) + 1);
        }

        /**
         * Record one override.
         *
         * error_log() goes to WP Engine's PHP log, outside the web root -
         * rev12 s10.6 records what happened the last time this plugin wrote a
         * log under get_stylesheet_directory(). The bounded copy in an option
         * is what the acceptance test reads, since the PHP log rotates and is
         * noisy. Written immediately to the log, batched to the option, so a
         * respawned import cannot lose the whole record.
         */
        private function avalon_import_log($record){
            $line = 'SLP Dealer Guard import: ' . wp_json_encode($record);
            error_log($line);

            $buf = $this->avalon_state('log_buffer');
            if (! is_array($buf)) {
                $buf = array();
            }
            $buf[] = $record;
            $this->avalon_state_set('log_buffer', $buf);

            if (count($buf) >= 20) {
                $this->avalon_flush_import_log(false);
            }
        }

        /**
         * Flush the override log and the geocode cache.
         *
         * Hooked to slp_csv_processing_complete at 500 - after the reconcile
         * at 10, before the working directory is cleared at 999. Also called
         * mid-import when the buffer fills.
         *
         * @param bool $final True at end of import: writes the run summary and
         *                    warns about exclusions that matched nothing.
         */
        public function avalon_flush_import_log($final = true){
            $buf = $this->avalon_state('log_buffer');
            if (is_array($buf) && ! empty($buf)) {
                $stored = get_option('avalon_geocode_overrides');
                if (! is_array($stored)) {
                    $stored = array();
                }
                $stored = array_merge($stored, $buf);
                if (count($stored) > 500) {
                    $stored = array_slice($stored, -500);
                }
                update_option('avalon_geocode_overrides', $stored, 'no');
                $this->avalon_state_set('log_buffer', array());
            }

            if ($this->avalon_state('cache_dirty')) {
                $cache = $this->avalon_state('geocode_cache');
                if (is_array($cache)) {
                    update_option('avalon_geocode_cache', $cache, 'no');
                }
                $this->avalon_state_set('cache_dirty', false);
            }

            if (! $final) {
                return;
            }

            $hits    = $this->avalon_state('exclusion_hits');
            $missing = array();
            foreach ($this->avalon_tier2_exclusions() as $entry) {
                if (! is_array($hits) || ! isset($hits[$entry])) {
                    $missing[] = $entry;
                }
            }

            $summary = array(
                'finished_utc'    => gmdate('c'),
                'tier1_written'   => (int) $this->avalon_state('tier1_written'),
                'tier2_written'   => (int) $this->avalon_state('tier2_written'),
                'observed'        => (int) $this->avalon_state('observed'),
                'excluded'        => (int) $this->avalon_state('excluded'),
                'geocodes_spent'  => (int) $this->avalon_state('geocodes_spent'),
                'tier2_aborted'   => (bool) $this->avalon_state('tier2_aborted'),
                'stale_exclusions'=> $missing
            );

            error_log('SLP Dealer Guard import summary: ' . wp_json_encode($summary));
            update_option('avalon_geocode_last_run', $summary, 'no');

            $this->avalon_import_state = null;
        }
"""


# ---------------------------------------------------------------------------
# EDIT 3 - geocode_from_address(): timeout, and refuse a 0,0 answer.
# ---------------------------------------------------------------------------

CURL_OLD = (
    b"            $address = urlencode($address);\r\n"
    b"            $api_url = \"https://maps.googleapis.com/maps/api/geocode/json?"
    b"address={$address}&key={$server_key}\";\r\n"
    b"            $ch = curl_init();\r\n"
    b"            curl_setopt($ch, CURLOPT_URL, $api_url);\r\n"
    b"            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);\r\n"
    b"            $output = curl_exec($ch);\r\n"
    b"            curl_close($ch);\r\n"
    b"            $json = json_decode($output, true);\r\n"
)

CURL_NEW = b"""            $address = urlencode($address);
            $api_url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$server_key}";
            // wp_remote_get, not bare curl_exec. The previous revision set no
            // CURLOPT_TIMEOUT at all, so a stalled Google connection could
            // hang a cron import indefinitely with nothing in the log.
            $timeout = defined('AVALON_IMPORT_GEOCODE_TIMEOUT')
                ? (int) AVALON_IMPORT_GEOCODE_TIMEOUT
                : 8;
            $response = wp_remote_get($api_url, array(
                'timeout'     => $timeout,
                'redirection' => 2,
                'sslverify'   => true
            ));
            if (is_wp_error($response)) {
                $result['error'] = 'HTTP: ' . $response->get_error_message();
                return $result;
            }
            $output = wp_remote_retrieve_body($response);
            $json = json_decode($output, true);
"""

ZERO_OLD = (
    b"                    if (isset($json['results'][0]['geometry'])) {\r\n"
    b"                        $result['success'] = true;\r\n"
    b"                        $result['lat'] = $json['results'][0]['geometry']['location']['lat'];\r\n"
    b"                        $result['lng'] = $json['results'][0]['geometry']['location']['lng'];\r\n"
    b"                        return $result;\r\n"
    b"                    } else {\r\n"
)

ZERO_NEW = b"""                    if (isset($json['results'][0]['geometry']['location']['lat'],
                              $json['results'][0]['geometry']['location']['lng'])) {
                        $lat = $json['results'][0]['geometry']['location']['lat'];
                        $lng = $json['results'][0]['geometry']['location']['lng'];
                        // A geocoder that answers 0,0 has not found anything;
                        // that is the Gulf of Guinea. Writing it back is how a
                        // zero-coordinate row stays a zero-coordinate row.
                        if (! is_numeric($lat) || ! is_numeric($lng)
                            || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
                            $result['error'] = 'Geocoder returned 0,0 or a non-number';
                            return $result;
                        }
                        $result['success'] = true;
                        $result['lat'] = $lat;
                        $result['lng'] = $lng;
                        return $result;
                    } else {
"""


PHP_OLD = b" * Version: 0.0.14\r\n"
PHP_NEW = b" * Version: 0.0.15\r\n"


# Real repo-relative locations, from the local GitHub inventory:
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\slp_avalon.php
IN_PATHS = {
    "class.slp_avalon.php": Path("slp_avalon") / "inc" / "class.slp_avalon.php",
    "slp_avalon.php": Path("slp_avalon") / "slp_avalon.php",
}


def main() -> None:
    repo = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
    out = Path(sys.argv[2]) if len(sys.argv) > 2 else repo / "build" / "out15"

    missing = [str(p) for p in IN_PATHS.values() if not (repo / p).is_file()]
    if missing:
        raise SystemExit(
            "Not a slp-plugins repo root: " + str(repo) + "\n"
            "  expected " + ", ".join(missing) + "\n"
            "  run this from D:\\Temp\\Projects\\GitHub\\slp-plugins"
        )

    out.mkdir(parents=True, exist_ok=True)
    print(f"  repo       {repo}")
    print(f"  out        {out}")

    cls = (repo / IN_PATHS["class.slp_avalon.php"]).read_bytes()
    php = (repo / IN_PATHS["slp_avalon.php"]).read_bytes()

    for name, blob in (("class.slp_avalon.php", cls), ("slp_avalon.php", php)):
        got = md5(blob)
        if got != SRC_MD5[name]:
            raise SystemExit(f"INPUT MD5 FAIL {name}: {got} != {SRC_MD5[name]}")
        print(f"  input  ok  {name:22} {got}")

    # The new block is authored with bare LF for readability; the file is CRLF
    # throughout and must stay that way, so normalise on the way in.
    guard_new = GUARD_NEW.replace(b"\r\n", b"\n").replace(b"\n", b"\r\n")
    curl_new = CURL_NEW.replace(b"\r\n", b"\n").replace(b"\n", b"\r\n")
    zero_new = ZERO_NEW.replace(b"\r\n", b"\n").replace(b"\n", b"\r\n")

    cls = sub_once(cls, HOOK_OLD, HOOK_NEW, "register hooks in add_actions()")
    print("  edit   ok  register hooks in add_actions()")

    cls = sub_once(cls, GUARD_OLD, guard_new, "replace add_lat_lng_before_csv_import")
    print("  edit   ok  replace add_lat_lng_before_csv_import")

    cls = sub_once(cls, CURL_OLD, curl_new, "geocode timeout via wp_remote_get")
    print("  edit   ok  geocode timeout via wp_remote_get")

    cls = sub_once(cls, ZERO_OLD, zero_new, "refuse a 0,0 geocode answer")
    print("  edit   ok  refuse a 0,0 geocode answer")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  version header 0.0.14 -> 0.0.15")

    # --- line endings ----------------------------------------------------
    assert cls.count(b"\r\n") == cls.count(b"\n"), "stray bare LF introduced in class file"
    assert php.count(b"\r\n") == php.count(b"\n"), "stray bare LF introduced in plugin file"
    assert not cls.endswith(b"\n"), "trailing newline introduced in class file"

    # --- the parse error that would have shipped is gone -----------------
    assert cls.count(b"//add_filter('slp_csv_locationdata'") == 0, \
        "the class-body add_filter comment survives"
    assert cls.count(b"add_lat_lng_before_csv_import") == 0, \
        "the retired function name survives"

    # --- hooks registered exactly once, at the measured priorities -------
    assert cls.count(
        b"\r\n            add_filter('slp_csv_locationdata',"
        b"array(self::$instance,'avalon_import_coordinate_guard'),20,1);\r\n"
    ) == 1, "coordinate guard registered once at priority 20, indented twelve"
    assert cls.count(
        b"\r\n            add_action('slp_csv_processing_complete',"
        b"array(self::$instance,'avalon_flush_import_log'),500);\r\n"
    ) == 1, "log flush registered once at priority 500, indented twelve"
    # Every inserted line inside add_actions() sits at twelve spaces. An
    # eight-space anchor would match and insert at the wrong depth silently.
    for line in cls.split(b"\r\n"):
        if b"avalon_import_coordinate_guard'),20,1);" in line \
           or b"avalon_flush_import_log'),500);" in line:
            assert line.startswith(b"            add_"), \
                f"registration at wrong indent: {line!r}"

    # --- one definition each ---------------------------------------------
    for fn in (
        b"public function avalon_import_coordinate_guard($location_data){",
        b"private function avalon_field($location_data, $key){",
        b"private function avalon_coord_is_sane($lat, $lng){",
        b"public function avalon_import_config(){",
        b"public function avalon_tier2_exclusions(){",
        b"private function avalon_exclusion_key($store, $city, $state){",
        b"private function avalon_tier2_is_excluded($store, $city, $state){",
        b"private function avalon_note_exclusion_hit($store, $city, $state){",
        b"private function avalon_geocode_cached($address){",
        b"private function avalon_state($key){",
        b"private function avalon_state_set($key, $value){",
        b"private function avalon_state_bump($key){",
        b"private function avalon_import_log($record){",
        b"public function avalon_flush_import_log($final = true){",
    ):
        assert cls.count(fn) == 1, f"one definition expected: {fn.decode()}"

    # --- the six Tier 1 defects, each provably addressed ------------------
    assert cls.count(b"(int)$location_data['sl_latitude']") == 0, "defect 1: the int cast"
    assert cls.count(b"if (! isset($location_data[$key])) {") == 1, "defect 2: guarded read"
    assert cls.count(b"'timeout'     => $timeout,") == 1, "defect 3: explicit timeout"
    assert cls.count(b"$result['error'] = 'Geocoder returned 0,0 or a non-number';") == 1, \
        "defect 4: a 0,0 answer is refused"
    assert cls.count(b"if (! $this->is_in_territory($geo['lat'], $geo['lng'])) {") == 1, \
        "defect 5: territory gate on every write"
    assert cls.count(b"error_log($line);") == 1, "defect 6: logging"

    # --- curl is gone from the geocoder ----------------------------------
    assert cls.count(b"curl_init()") == 0, "curl_init survives in the geocoder"
    assert cls.count(b"curl_exec(") == 0, "curl_exec survives in the geocoder"
    assert cls.count(b"wp_remote_get($api_url, array(") == 1, "one wp_remote_get call"

    # --- both rails present, and both exclusions ---------------------------
    assert cls.count(b"'ABORTED_correction_cap'") == 1, "correction cap"
    assert cls.count(b"'error' => 'geocode budget exhausted'") == 1, "geocode budget"
    assert cls.count(b"'DONNIE MARCH|HOWELL|MI',") == 1, "DONNIE MARCH excluded"
    assert cls.count(b"'C/O COLE INTERNATIONAL USA|PEMBINA|ND',") == 1, "Cole excluded"

    # --- options are never autoloaded --------------------------------------
    assert cls.count(b"update_option('avalon_geocode_overrides', $stored, 'no');") == 1
    assert cls.count(b"update_option('avalon_geocode_cache', $cache, 'no');") == 1
    assert cls.count(b"update_option('avalon_geocode_last_run', $summary, 'no');") == 1

    # --- nothing established by earlier versions may drift -----------------
    assert cls.count(b"public function territory_boxes(){") == 1
    assert cls.count(b"public function is_in_territory( $lat, $lng ){") == 1
    assert cls.count(b"public function vincentyGreatCircleDistance(") == 1
    assert cls.count(b"public function csv_processing_complete_func()") == 1
    assert cls.count(b"public function csv_locationdata_added_func($location_data, $result_of_add)") == 1
    assert cls.count(b"add_filter('slp_ajax_find_locations_complete',"
                     b"array(self::$instance,'territory_gate'),20,1);") == 1
    assert cls.count(b"            add_filter('slp_ajaxsql_queryparams',"
                     b"array(self::$instance,'slp_ajaxsql_queryparams'),999,2);\r\n") == 1
    assert cls.count(b"            add_action('slp_csv_processing_complete', "
                     b"array(self::$instance,'remove_old_csv_files_after_import'), 999);\r\n") == 1

    assert php.count(b"0.0.15") == 1
    assert php.count(b"0.0.14") == 0
    print("  self-check ok")

    (out / "class.slp_avalon.php").write_bytes(cls)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("class.slp_avalon.php", cls), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:22} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
