#!/usr/bin/env python3
"""
control-v031.py  r1  2026-09-29
SLP Dealer Guard - negative controls for suite-v031 (slp_avalon v0.0.27
Part 3b).

WITHOUT THIS FILE IN THE REPO, suite-v031 SCORING 114/114 ASSERTS NOTHING.
A suite that has only ever been run against a build that works has not been
shown to be capable of failing. Each control below removes exactly one
load-bearing decision from the good build and nothing else, and the release
is gated on the suite catching every one of them, by a pinned count.

    python3 control-v031.py --in <class.slp_avalon.php> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for each control. Use an out* name
for the output directory - build/out*/ is gitignored, and untracked
directories in a repo that has been untracked=0 since rev36 is s0.218.

Every substitution is byte-exact on the CRLF file and asserted unique before
it is applied. A control that could not be built is a hard error, never a
skipped control: a missing control is a decision nobody is testing.

Seven controls touch nothing Part 3b wrote. Each changes one thing in a
region Part 3b must NOT have changed - or, for C and WIRE, in the Part 3a
bytes around the Part 3b insertion - to show that suite-v031's pins can
fail. A comparator that has only ever agreed has not been shown capable of
disagreeing, and suite-v030's 109 and suite-v029's 107 carry forward only
through those pins.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "3bd2094189c58318a827ca01f990f4fc"
IN_LEN = 246313


# ---------------------------------------------------------------------------
# The controls. (name, why it matters, [(old, new), ...])
# ---------------------------------------------------------------------------

CONTROLS = [

    # ---- the block list --------------------------------------------------

    ("list_not_excluded",
     "The queue refuses a listed key by name, whether or not anything has "
     "marked it blocked yet. Without the NOT IN, a key listed in a release "
     "is asked about on a site where sync has not run - a CLI sweep with "
     "the cron unscheduled - and a wrong place id publishes another "
     "dealer's hours.",
     [(
         "                . $not_in\r\n",
         "",
     ), (
         "                $disputed,\r\n",
         "",
     )]),

    ("list_short",
     "The list is the nine keys decided 2026-09-29. One dropped - here the "
     "second unchecked weaker-side row - is a dealer nobody has looked at "
     "going live with hours that may be someone else's.",
     [(
         "                '670217f109e9' => 'WEAKER SIDE, unchecked: 82e66db527bd scores 80 to 60',\r\n",
         "",
     )]),

    ("blocks_not_synced",
     "Only sync clears a payload fetched before its key was listed. A cron "
     "that sweeps without syncing leaves 3664a8c7ba9e's stale hours in the "
     "table for the renderer to find.",
     [(
         "        public function avalon_hours_cron(){\r\n"
         "            $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );\r\n",
         "        public function avalon_hours_cron(){\r\n"
         "            $blocked = 0;\r\n",
     )]),

    ("sync_gated_on_enabled",
     "Blocks first and unconditionally. Switching the feature off must not "
     "leave a disputed payload in place - that is exactly the state an "
     "administrator reaches for in an emergency.",
     [(
         "            $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );\r\n"
         "\r\n"
         "            $cfg = $this->avalon_hours_config();\r\n"
         "            if ( empty( $cfg['enabled'] ) ) {\r\n"
         "                return;\r\n"
         "            }\r\n",
         "            $cfg = $this->avalon_hours_config();\r\n"
         "            if ( empty( $cfg['enabled'] ) ) {\r\n"
         "                return;\r\n"
         "            }\r\n"
         "\r\n"
         "            $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );\r\n",
     )]),

    ("sync_keeps_payload",
     "Marking a row blocked is not enough. The renderer reads hours_json; a "
     "blocked row that still holds another dealer's hours is one renderer "
     "bug away from publishing them.",
     [(
         '                "UPDATE {$table} SET hours_status = %s, hours_json = NULL,"\r\n',
         '                "UPDATE {$table} SET hours_status = %s,"\r\n',
     )]),

    ("sync_where_status_only",
     "A row an administrator blocked by hand, with its payload still in "
     "place, is exactly the row that needs clearing. Matching on status "
     "alone skips it forever.",
     [(
         '                . " AND ( hours_status <> %s OR hours_json IS NOT NULL )",\r\n',
         '                . " AND hours_status <> %s",\r\n',
     )]),

    ("dry_run_syncs",
     "A dry run spends nothing and WRITES nothing - not even the blocks. It "
     "is how the queue is read on LIVE before anything is changed there.",
     [(
         "                $blocked = 0;\r\n"
         "                if ( ! $dry ) {\r\n"
         "                    $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );\r\n"
         "                }\r\n",
         "                $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );\r\n",
     )]),

    ("release_ignores_list",
     "The list is the authority. A release the next cron run undoes is not "
     "a release, and in the hours between it the key is asked about.",
     [(
         "            if ( array_key_exists( $key, self::avalon_hours_disputed_keys() ) ) {\r\n"
         "                return 'still listed in avalon_hours_disputed_keys(): ' . $key;\r\n"
         "            }\r\n",
         "",
     )]),

    # ---- the sweep -------------------------------------------------------

    ("no_refresh_margin",
     "s0.262. Refreshed on the same 30 days the purge caps at, a row is "
     "cleared before it is re-asked whenever the cron runs late, and the "
     "store page loses its hours for a day every month.",
     [(
         "            $refresh = max( 1, (int) $cfg['positive_ttl_days'] - self::HOURS_REFRESH_MARGIN_DAYS );\r\n",
         "            $refresh = max( 1, (int) $cfg['positive_ttl_days'] );\r\n",
     )]),

    ("race_guard_ok_dropped",
     "s0.258. A row blocked by hand, or re-pointed by Part 3e, between the "
     "queue read and the write must not be overwritten with the hours of "
     "the place that was asked about.",
     [(
         '                    . " error_count = 0, last_error = NULL"\r\n'
         '                    . " WHERE address_key = %s AND place_id = %s"\r\n'
         '                    . " AND hours_status <> %s",\r\n'
         "                    $payload, $state, $bstat, $now, $now,\r\n"
         "                    $row['address_key'], $row['place_id'], self::HOURS_STATUS_BLOCKED\r\n",
         '                    . " error_count = 0, last_error = NULL"\r\n'
         '                    . " WHERE address_key = %s",\r\n'
         "                    $payload, $state, $bstat, $now, $now,\r\n"
         "                    $row['address_key']\r\n",
     )]),

    ("race_guard_strike_dropped",
     "s0.258, the other write. A strike on a row blocked underfoot turns it "
     "back into pending, and the next run asks about a disputed place.",
     [(
         '                        . " fetched_at = %s, updated_at = %s"\r\n'
         '                        . " WHERE address_key = %s AND place_id = %s"\r\n'
         '                        . " AND hours_status <> %s",\r\n'
         "                        substr( $err, 0, 190 ), $state, $now, $now,\r\n"
         "                        $row['address_key'], $row['place_id'], self::HOURS_STATUS_BLOCKED\r\n",
         '                        . " fetched_at = %s, updated_at = %s"\r\n'
         '                        . " WHERE address_key = %s",\r\n'
         "                        substr( $err, 0, 190 ), $state, $now, $now,\r\n"
         "                        $row['address_key']\r\n",
     )]),

    ("badwrite_is_race",
     "A write the database refuses is not a fact about the dealer. Read as "
     "a race, the sweep carries on buying calls it cannot record.",
     [(
         "                if ( false === $n ) {\r\n"
         "                    $out['systemic']++;\r\n"
         "                    $out['errors'][] = 'BADWRITE';\r\n"
         "                    break;\r\n"
         "                }\r\n",
         "",
     )]),

    ("badwrite_strike_is_race",
     "The strike write can be refused too. Read as a race, a dead database "
     "turns every NOT_FOUND into a raced row and the sweep keeps buying "
     "calls it cannot record.",
     [(
         "                    if ( false === $n ) {\r\n"
         "                        //The database refused a write. Not a fact about the\r\n"
         "                        //dealer, and every later row would buy a call that\r\n"
         "                        //cannot be recorded.\r\n"
         "                        $out['systemic']++;\r\n"
         "                        $out['errors'][] = 'BADWRITE';\r\n"
         "                        break;\r\n"
         "                    }\r\n",
         "",
     )]),

    ("strike_placeid_guard_dropped",
     "s0.258 on the strike alone. A row Part 3e re-points mid-sweep takes a "
     "strike - possibly its third - for the place it no longer holds.",
     [(
         '                        . " WHERE address_key = %s AND place_id = %s"\r\n'
         '                        . " AND hours_status <> %s",\r\n'
         "                        substr( $err, 0, 190 ), $state, $now, $now,\r\n"
         "                        $row['address_key'], $row['place_id'], self::HOURS_STATUS_BLOCKED\r\n",
         '                        . " WHERE address_key = %s"\r\n'
         '                        . " AND hours_status <> %s",\r\n'
         "                        substr( $err, 0, 190 ), $state, $now, $now,\r\n"
         "                        $row['address_key'], self::HOURS_STATUS_BLOCKED\r\n",
     )]),

    ("raced_strike_stops",
     "A raced row is skipped, never a reason to stop. One hand block "
     "mid-sweep must not end the run for every row behind it.",
     [(
         "                    if ( (int) $n < 1 ) {\r\n"
         "                        $out['raced']++;\r\n"
         "                        continue;\r\n"
         "                    }\r\n",
         "                    if ( (int) $n < 1 ) {\r\n"
         "                        $out['raced']++;\r\n"
         "                        break;\r\n"
         "                    }\r\n",
     )]),

    ("strike_keeps_content",
     "s0.263. A strike re-stamps fetched_at and sets pending, a status the "
     "TTL sweeps never visit. Content left behind outlives the 30-day cap.",
     [(
         '                        . " hours_json = NULL, attribution_json = NULL,"\r\n'
         '                        . " business_status = NULL, primary_type_display = NULL,"\r\n'
         '                        . " locality = NULL, admin_area = NULL,"\r\n',
         "",
     )]),

    ("badquery_ignored",
     "s0.264. get_results() returns an empty array when the database "
     "refuses the read. Without last_error a missing table is an empty "
     "queue, and the cron logs a clean run every day, forever.",
     [(
         "            if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {\r\n",
         "            if ( ! is_array( $rows ) ) {\r\n",
     )]),

    ("none_never_refreshed",
     "none rows are re-asked on the positive TTL too - a dealer that "
     "publishes hours next month must get them.",
     [(
         "                    self::HOURS_STATUS_OK,\r\n"
         "                    self::HOURS_STATUS_NONE,\r\n"
         "                    $pos_cut,\r\n",
         "                    self::HOURS_STATUS_OK,\r\n"
         "                    self::HOURS_STATUS_OK,\r\n"
         "                    $pos_cut,\r\n",
     )]),

    ("ok_on_periods",
     "s0.260, the other way round. periods are machine-readable and the "
     "renderer does not print them; a Place with periods and no weekday "
     "lines has nothing to show.",
     [(
         "                         && isset( $place['regularOpeningHours']['weekdayDescriptions'] )\r\n"
         "                         && is_array( $place['regularOpeningHours']['weekdayDescriptions'] )\r\n"
         "                         && ! empty( $place['regularOpeningHours']['weekdayDescriptions'] ) );\r\n",
         "                         && isset( $place['regularOpeningHours']['periods'] )\r\n"
         "                         && is_array( $place['regularOpeningHours']['periods'] )\r\n"
         "                         && ! empty( $place['regularOpeningHours']['periods'] ) );\r\n",
     )]),

    ("business_status_blank",
     "s0.259. wpdb::prepare() has no NULL: a null bound through %s is "
     "stored as ''. Without NULLIF, 'no status from Google' and 'status "
     "known' are no longer told apart by IS NULL.",
     [(
         "                    . \" business_status = NULLIF(%s, ''), fetched_at = %s, updated_at = %s,\"\r\n",
         '                    . " business_status = %s, fetched_at = %s, updated_at = %s,"\r\n',
     )]),

    ("ok_without_lines",
     "s0.260. The renderer prints weekdayDescriptions and nothing else. A "
     "Place with only openNow counted as ok renders an empty hours block.",
     [(
         "                         && isset( $place['regularOpeningHours']['weekdayDescriptions'] )\r\n"
         "                         && is_array( $place['regularOpeningHours']['weekdayDescriptions'] )\r\n"
         "                         && ! empty( $place['regularOpeningHours']['weekdayDescriptions'] ) );\r\n",
         "                         && ! empty( $place['regularOpeningHours'] ) );\r\n",
     )]),

    # ---- the purge -------------------------------------------------------

    ("purge_skips_none",
     "s0.257. A none row holds a cached Place too - the payload and its raw "
     "response - and Google's terms cap it at 30 days like any other.",
     [(
         "                self::HOURS_STATUS_NONE   => (int) $cfg['positive_ttl_days'],\r\n",
         "",
     )]),

    ("purge_keeps_business_status",
     "s0.257. business_status is Places content under the same cap, and a "
     "stale CLOSED_PERMANENTLY goes on hiding a dealer that reopened.",
     [(
         '                        "UPDATE {$table} SET hours_json = NULL, attribution_json = NULL,"\r\n'
         '                        . " business_status = NULL, primary_type_display = NULL,"\r\n',
         '                        "UPDATE {$table} SET hours_json = NULL, attribution_json = NULL,"\r\n'
         '                        . " primary_type_display = NULL,"\r\n',
     )]),

    ("purge_gated",
     "The purge is not gated on enabled. Switching the feature off must not "
     "leave cached content sitting past its TTL - expiry is a licence "
     "obligation, not a feature.",
     [(
         "            $cfg    = $this->avalon_hours_config();\r\n"
         "            $table  = self::avalon_hours_table();\r\n",
         "            $cfg    = $this->avalon_hours_config();\r\n"
         "            if ( empty( $cfg['enabled'] ) ) {\r\n"
         "                return 0;\r\n"
         "            }\r\n"
         "            $table  = self::avalon_hours_table();\r\n",
     )]),

    ("purge_held_skips_blocked",
     "s0.263. A row blocked by hand with its payload in place is exactly "
     "the content nothing else will ever clear.",
     [(
         "                    self::HOURS_STATUS_PENDING,\r\n"
         "                    self::HOURS_STATUS_BLOCKED,\r\n"
         "                    $cutoff\r\n",
         "                    self::HOURS_STATUS_PENDING,\r\n"
         "                    self::HOURS_STATUS_PENDING,\r\n"
         "                    $cutoff\r\n",
     )]),

    ("purge_held_skips_pending",
     "s0.263. A row released from a hand block keeps whatever it held.",
     [(
         "                    self::HOURS_STATUS_PENDING,\r\n"
         "                    self::HOURS_STATUS_BLOCKED,\r\n"
         "                    $cutoff\r\n",
         "                    self::HOURS_STATUS_BLOCKED,\r\n"
         "                    self::HOURS_STATUS_BLOCKED,\r\n"
         "                    $cutoff\r\n",
     )]),

    ("purge_held_null_age_kept",
     "Content with no fetch stamp is of unknown age. Kept, it is kept "
     "forever: fetched_at < cutoff is never true of NULL.",
     [(
         '                    . " AND ( fetched_at IS NULL OR fetched_at < %s )",\r\n',
         '                    . " AND fetched_at < %s",\r\n',
     )]),

    ("held_purge_on_negative_ttl",
     "s0.263. Held content is capped at the POSITIVE 30 days, like the "
     "content it is; on the 7-day cutoff a hand block's content goes three "
     "weeks early and nothing says why.",
     [(
         "            $cutoff = gmdate( 'Y-m-d H:i:s',\r\n"
         "                              time() - ( (int) $cfg['positive_ttl_days'] * DAY_IN_SECONDS ) );\r\n",
         "            $cutoff = gmdate( 'Y-m-d H:i:s',\r\n"
         "                              time() - ( (int) $cfg['negative_ttl_days'] * DAY_IN_SECONDS ) );\r\n",
     )]),

    ("held_purge_hours_json_only",
     "s0.263. One column of content is content. A lone stale CLOSED_* "
     "under a hand block goes on hiding the dealer.",
     [(
         '                    . " AND ( hours_json IS NOT NULL OR attribution_json IS NOT NULL"\r\n'
         '                    . " OR business_status IS NOT NULL OR primary_type_display IS NOT NULL"\r\n'
         '                    . " OR locality IS NOT NULL OR admin_area IS NOT NULL )"\r\n',
         '                    . " AND hours_json IS NOT NULL"\r\n',
     )]),

    ("purge_releases_blocks",
     "The naive fix - blocked on the positive TTL with ok and none - sets "
     "a hand-blocked row back to pending, and the next sweep asks about "
     "the place someone decided must not be asked about.",
     [(
         "                self::HOURS_STATUS_FAILED => (int) $cfg['negative_ttl_days'],\r\n",
         "                self::HOURS_STATUS_FAILED => (int) $cfg['negative_ttl_days'],\r\n"
         "                self::HOURS_STATUS_BLOCKED => (int) $cfg['positive_ttl_days'],\r\n",
     )]),

    # ---- the schedule and the hook ----------------------------------------

    ("cron_hook_unregistered",
     "A scheduled event whose hook has no listener still consumes its slot "
     "and reports nothing. The cron would 'run' daily and do nothing.",
     [(
         "            add_action(self::HOURS_CRON_HOOK, array(self::$instance,'avalon_hours_cron'));\r\n",
         "",
     )]),

    ("schedule_unguarded",
     "Without the wp_next_scheduled() check every request on init adds "
     "another daily event, and the sweep runs as many times a day as the "
     "site has had page views.",
     [(
         "            if ( wp_next_scheduled( self::HOURS_CRON_HOOK ) ) {\r\n"
         "                return;\r\n"
         "            }\r\n",
         "",
     )]),

    ("schedule_immediate",
     "The first run is an hour out so a deploy cannot sweep inside the "
     "request that installed it - and so a bad build can be pulled before "
     "it spends anything.",
     [(
         "            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOURS_CRON_HOOK );\r\n",
         "            wp_schedule_event( time(), 'daily', self::HOURS_CRON_HOOK );\r\n",
     )]),

    # ---- the CLI -----------------------------------------------------------

    ("cli_unknown_is_status",
     "s0.232. A typo must not print a status table that looks like an "
     "answer. 'wp avalon hours sweeep' reporting counts is a sweep the "
     "operator believes ran.",
     [(
         "            if ( 'status' !== $sub ) {\r\n"
         "                WP_CLI::error( sprintf(\r\n"
         "                    'unknown subcommand \"%s\". Valid: %s',\r\n"
         "                    $sub,\r\n"
         "                    implode( ', ', self::avalon_hours_subcommands() )\r\n",
         "            if ( false ) {\r\n"
         "                WP_CLI::error( sprintf(\r\n"
         "                    'unknown subcommand \"%s\". Valid: %s',\r\n"
         "                    $sub,\r\n"
         "                    implode( ', ', self::avalon_hours_subcommands() )\r\n",
     )]),

    ("cli_systemic_succeeds",
     "s0.220. A refusal aimed at the project is not a successful run. A CLI "
     "sweep that stopped on REQUEST_DENIED must exit non-zero.",
     [(
         "                if ( ! empty( $out['errors'] ) ) {\r\n"
         "                    WP_CLI::error( 'aborted on ' . $out['errors'][0] );\r\n"
         "                    return;\r\n"
         "                }\r\n",
         "",
     )]),

    ("max_calls_ignored",
     "--max-calls is how a first LIVE sweep is kept to two calls. Ignored, "
     "the pass runs at details_ceiling.",
     [(
         "                $out = $this->avalon_hours_sweep( $max, $dry );\r\n",
         "                $out = $this->avalon_hours_sweep( 0, $dry );\r\n",
     )]),

    ("max_calls_zero_spends",
     "s0.265. The sweep reads 0 as 'use details_ceiling'. --max-calls=0, "
     "typed to mean 'spend nothing', would spend fifty calls.",
     [(
         "                    if ( $max < 1 || (string) $max !== $raw ) {\r\n"
         "                        WP_CLI::error( '--max-calls must be a whole number, 1 or more. Nothing was spent.' );\r\n"
         "                        return;\r\n"
         "                    }\r\n",
         "",
     )]),

    ("max_calls_no_string_check",
     "s0.265. (int) '1e3' is 1000 in PHP 8. Without the round-trip check "
     "a typo sweeps every due row.",
     [(
         "                    if ( $max < 1 || (string) $max !== $raw ) {\r\n",
         "                    if ( $max < 1 ) {\r\n",
     )]),

    ("release_badwrite_is_none",
     "s0.265. A refused release read as 'no blocked row' sends the operator "
     "looking for a typo in a key that is fine.",
     [(
         "            if ( false === $n ) {\r\n"
         "                //s0.265. A refused write is not \"no blocked row\".\r\n"
         "                return 'the database refused the release: ' . (string) $wpdb->last_error;\r\n"
         "            }\r\n",
         "",
     )]),

    ("status_badquery_is_empty",
     "s0.264 in status. A refused read printed as 'queue empty' is the one "
     "answer that makes an operator stop looking.",
     [(
         "            if ( '' !== (string) $wpdb->last_error ) {\r\n"
         "                WP_CLI::error( 'status could not read the table: ' . $wpdb->last_error );\r\n"
         "                return;\r\n"
         "            }\r\n",
         "",
     )]),

    ("release_nothing_succeeds",
     "s0.265. A release of a key with no blocked row changed nothing. "
     "Reported as success, a typo in the key reads as done.",
     [(
         "                if ( $r < 1 ) {\r\n"
         "                    //s0.265. A key that is absent, or not blocked, released\r\n"
         "                    //nothing. Success here would read as done.\r\n"
         "                    WP_CLI::error( 'nothing released: no blocked row for '\r\n"
         "                        . strtolower( trim( $key ) ) . ' in this table.' );\r\n"
         "                    return;\r\n"
         "                }\r\n",
         "",
     )]),

    ("failed_cli_sweep_unlogged",
     "A CLI sweep that stopped on a refusal has usually written strikes "
     "before it. Unrecorded, the places log shows nothing happened.",
     [(
         "                if ( ! $dry ) {\r\n"
         "                    WP_CLI::log( sprintf( 'disputed rows newly blocked %d', $blocked ) );\r\n",
         "                if ( ! $dry && empty( $out['errors'] ) ) {\r\n"
         "                    WP_CLI::log( sprintf( 'disputed rows newly blocked %d', $blocked ) );\r\n",
     )]),

    ("cli_sync_after_sweep",
     "Blocks first. Synced after the sweep, a stale disputed payload is "
     "still in the table while the calls are being made - and a sweep that "
     "stops on a refusal never clears it at all.",
     [(
         "                $blocked = 0;\r\n"
         "                if ( ! $dry ) {\r\n"
         "                    $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );\r\n"
         "                }\r\n"
         "\r\n"
         "                $out = $this->avalon_hours_sweep( $max, $dry );\r\n",
         "                $out = $this->avalon_hours_sweep( $max, $dry );\r\n"
         "\r\n"
         "                $blocked = 0;\r\n"
         "                if ( ! $dry ) {\r\n"
         "                    $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );\r\n"
         "                }\r\n",
     )]),

    ("status_marked_dropped",
     "status is how an operator confirms the blocks landed on LIVE. A "
     "'marked blocked' that never counts reads 0 after a successful sync.",
     [(
         "                        $marked += (int) $r['n'];\r\n",
         "",
     )]),

    ("status_closed_dropped",
     "status lists closed dealers so an operator can see which store pages "
     "will show no hours, and why.",
     [(
         "                    WP_CLI::log( sprintf( '%-8s %d  (never rendered)', $r['business_status'], (int) $r['n'] ) );\r\n",
         "",
     )]),

    ("release_keeps_strikes",
     "A released row keeps its old strikes; with three, its first miss "
     "after release fails it outright.",
     [(
         '                "UPDATE {$table} SET hours_status = %s, error_count = 0,"\r\n',
         '                "UPDATE {$table} SET hours_status = %s,"\r\n',
     )]),

    ("sync_keeps_strikes",
     "A blocked row carries no strikes, so the row Part 3e releases starts "
     "clean.",
     [(
         '                . " fetched_at = NULL, error_count = 0, last_error = %s,"\r\n',
         '                . " fetched_at = NULL, last_error = %s,"\r\n',
     )]),

    # ---- drift: bytes Part 3b must NOT have changed -------------------------

    ("drift_constant",
     "C was added to, not edited. One Part 3a constant changed beside the "
     "insertion has to fail the suite.",
     [(
         "        const PLACES_LOG_MAX        = 50;\r\n",
         "        const PLACES_LOG_MAX        = 51;\r\n",
     )]),

    ("drift_wiring",
     "add_actions() was added to, not edited. The places seed at 20 is "
     "forced, for the reasons in the comment above it; moved to 21 it has to fail the suite.",
     [(
         "            add_action('slp_csv_processing_complete', array(self::$instance,'avalon_places_seed'), 20);\r\n",
         "            add_action('slp_csv_processing_complete', array(self::$instance,'avalon_places_seed'), 21);\r\n",
     )]),

    ("drift_territory",
     "Region A2 - the territory gate, the import guard, the reconcile, the "
     "redirects. One number changed there has to fail the suite.",
     [(
         "            array( 'CONUS + Canada',      24.4,    83.2,   -141.0,    -52.0 ),\r\n",
         "            array( 'CONUS + Canada',      24.4,    83.3,   -141.0,    -52.0 ),\r\n",
     )]),

    ("drift_table",
     "Region T. An uppercase column type re-issues the same ALTER on every "
     "dbDelta run; the pin is the only thing in suite-v031 that sees T.",
     [(
         '                "  business_status varchar(24) null default null,",\r\n',
         '                "  business_status VARCHAR(24) null default null,",\r\n',
     )]),

    ("drift_fetch_layer",
     "Region F, the Part 2 fetch layer suite-v030 scored 109/109. That "
     "evidence carries forward only by byte identity.",
     [(
         "                                       ? (int) AVALON_HOURS_TIMEOUT    : 8,\r\n",
         "                                       ? (int) AVALON_HOURS_TIMEOUT    : 9,\r\n",
     )]),

    ("drift_places_gate",
     "Region B1, the places schedule gate the unattended cron proved.",
     [(
         "            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PLACES_CRON_HOOK );\r\n",
         "            wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::PLACES_CRON_HOOK );\r\n",
     )]),

    ("drift_resolver",
     "Region B2, the Part 3d resolver suite-v029 scored 107/107. One changed "
     "operator there has to fail the suite.",
     [(
         '                    . " AND error_count >= %d",\r\n',
         '                    . " AND error_count > %d",\r\n',
     )]),
]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="src", required=True)
    ap.add_argument("--out", dest="out", required=True)
    ap.add_argument("--allow-md5", default="")
    args = ap.parse_args()

    print("control-v031.py  r1  2026-09-29")
    print("  negative controls for suite-v031")
    print()

    raw = open(args.src, "rb").read()
    got = hashlib.md5(raw).hexdigest()
    want = args.allow_md5 or IN_MD5

    print("  input  %s" % args.src)
    print("    md5    %s" % got)
    print("    bytes  %d" % len(raw))
    if got != want:
        print()
        print("  REFUSED: input md5 %s, expected %s" % (got, want))
        return 2
    if not args.allow_md5 and len(raw) != IN_LEN:
        print()
        print("  REFUSED: input bytes %d, expected %d" % (len(raw), IN_LEN))
        return 2
    print("    pinned OK")
    print()

    src = raw.decode(ENC)
    os.makedirs(args.out, exist_ok=True)

    names = [c[0] for c in CONTROLS]
    if len(set(names)) != len(names):
        print("  REFUSED: two controls share a name")
        return 3

    print("  controls")
    for name, why, subs in CONTROLS:
        broken = src
        for old, new in subs:
            n = broken.count(old)
            if n != 1:
                print()
                print("  REFUSED: control '%s' anchor appears %d time(s), need 1"
                      % (name, n))
                return 3
            broken = broken.replace(old, new, 1)

        if broken == src:
            print()
            print("  REFUSED: control '%s' changed nothing" % name)
            return 4

        d = os.path.join(args.out, name)
        os.makedirs(d, exist_ok=True)
        blob = broken.encode(ENC)
        with open(os.path.join(d, "class.slp_avalon.php"), "wb") as fh:
            fh.write(blob)

        print("    %-28s %s  %7d  (%+d)"
              % (name, hashlib.md5(blob).hexdigest(), len(blob),
                 len(blob) - len(raw)))

    print()
    print("  %d control(s) written to %s" % (len(CONTROLS), args.out))
    print()
    print("  each must FAIL the suite. Score them:")
    print("    for d in %s/*/; do" % args.out)
    print("      php -d short_open_tag=1 test/suite-v031.php"
          " \"$d/class.slp_avalon.php\"")
    print("    done")
    return 0


if __name__ == "__main__":
    sys.exit(main())
