#!/usr/bin/env python3
"""
build-v027-part3b.py

slp_avalon v0.0.27, PART 3b OF 4.

WHAT THIS RELEASE DOES
----------------------
Part 3a built the sweep and nothing called it. Part 3b is what calls it,
plus the four defects found in the sweep and the purge on 2026-09-29 while
v0.0.27-part3a was being tagged and independently reviewed.

1. THE CRON. HOURS_CRON_HOOK ('avalon_hours_refresh'), a schedule gate on
   init priority 1, and avalon_hours_cron(). Twin of the places pair and
   for its reasons: activation never fires on a database-import site, so
   a schedule that exists only if activation ran does not exist.

   It runs on every environment - DEV and LIVE both, decided 2026-09-29.
   A DEV-to-LIVE push copies DEV's table over LIVE's, so each must keep
   itself fresh.

2. THE CLI. wp avalon hours status | sweep [--dry-run] [--max-calls=N] |
   release --key=<k>. A bare invocation is status; an unknown subcommand
   is an error (s0.232). A systemic stop exits through WP_CLI::error so
   the exit code tells the truth (s0.220).

3. THE BLOCK LIST, IN CODE. avalon_hours_disputed_keys(): the seven place
   ids the 2026-09-14 adjudication proved wrong, plus the two weaker-side
   rows nobody has looked at yet - decided 2026-09-29. The queue refuses
   to ask about a listed key; the cron and a real CLI sweep also mark
   listed rows blocked and clear anything already cached for them.

4. FOUR FIXES.

   s0.257  The purge retired ok and failed rows and never none rows, and
           never cleared business_status. none rows hold a cached Place.
           Both are Places content under the same 30-day cap.
   s0.258  The sweep's two writes matched on address_key alone. A row
           blocked by hand, or re-pointed by Part 3e, between the queue
           read and the write was overwritten with the answer for a place
           id it no longer holds. The WHERE now carries place_id and the
           block, and a refused write is counted as raced.
   s0.259  business_status was bound through %s, and wpdb::prepare() has
           no NULL: a missing value landed as ''. NULLIF(%s, '').
   s0.260  ok meant "regularOpeningHours is not empty". The renderer shows
           weekdayDescriptions verbatim and nothing else, so ok now means
           there are weekday lines to show.

   And one found while fixing the first. s0.262: the sweep re-asked ok
   and none rows AT the positive TTL and the purge cleared them AT the
   positive TTL, so whichever cron ran first decided whether a store page
   lost its hours for a day every month. The sweep now re-asks
   HOURS_REFRESH_MARGIN_DAYS early; the purge is the backstop for a sweep
   that is not running.

5. THREE MORE, from the independent review of this build, 2026-09-29.

   s0.263  A strike left the cached Place in the row, re-stamped
           fetched_at, and set the row pending - a status the purge never
           visited. A refresh answered NOT_FOUND kept its old content for
           37 days with the feature on and forever with it off. A strike
           now clears the content; the purge also clears content held
           under pending or blocked, keeping the status.
   s0.264  wpdb::get_results() returns an empty array, not null, when the
           database refuses the statement, so a missing table read as an
           empty queue and the cron logged a clean run. last_error is
           checked; the sweep reports BADQUERY and the CLI exits non-zero.
   s0.265  CLI hardening. --max-calls=0 (or a typo that casts to 0) spent
           the full details_ceiling, and 1e3 cast to 1000; anything but a
           plain whole number of 1 or more is refused. release of a key
           with no blocked row printed success, and a refused release read
           as "no blocked row"; both are errors that say which. status
           built IN () once the list is empty; it is guarded. status read
           a refused query as "queue empty"; it errors.

WHAT IS REPLACED WHOLE, AND WHY
-------------------------------
avalon_hours_sweep() and avalon_places_purge() are replaced whole, with
their docblocks, rather than patched in seven places. Each replaced span
is asserted by md5 and length before anything is written, so the build
cannot run against a sweep or a purge it has not seen.

ENCODING
--------
ISO-8859-1 with newline='' so CRLF survives. Input is CRLF; output must
stay CRLF with no bare LF, and the CR count is printed.

Usage:  python build-v027-part3b.py <src_dir> <out_dir>

        src_dir must hold the PART 3a OUTPUT:
            class.slp_avalon.php   8bc1724a7e932fed0bfc7cb18e6cd39f
        and it writes, or refuses to:
            class.slp_avalon.php   3bd2094189c58318a827ca01f990f4fc  246313
"""

import hashlib
import io
import os
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('8bc1724a7e932fed0bfc7cb18e6cd39f', 220840),
}

# The output this script was reviewed against. A build that produces any
# other bytes is not the build suite-v031 scored 114/114 and control-v031's
# 54 controls were measured on, and is refused before it is written.
OUT_PIN = ('3bd2094189c58318a827ca01f990f4fc', 246313)


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


def replace_span(text, start, end, want_md5, want_len, new, label):
    """Replace [start, end) - end excluded - after asserting the span is the
    one this script was written against."""
    if text.count(start) != 1 or text.count(end) != 1:
        sys.exit("ABORT {}: span anchors are not unique".format(label))
    i = text.find(start)
    j = text.find(end, i + 1)
    if j < 0:
        sys.exit("ABORT {}: end anchor before start".format(label))
    old = text[i:j]
    md5 = hashlib.md5(old.encode('iso-8859-1')).hexdigest()
    if md5 != want_md5 or len(old) != want_len:
        sys.exit("ABORT {}: span is {} / {} chars, expected {} / {}".format(
            label, md5, len(old), want_md5, want_len))
    print("  replaced  {}  (was {} / {} chars)".format(label, md5[:8], len(old)))
    return text[:i] + new + text[j:]


def check(cond, label):
    if not cond:
        sys.exit("ABORT self-check: {}".format(label))
    print("  check OK  {}".format(label))


# ===========================================================================
# 1. Constants
# ===========================================================================

CONST_ANCHOR = crlf("        const HOURS_STATUS_FAILED   = 'failed';\n")

CONST_BLOCK = crlf(r"""        const HOURS_STATUS_FAILED   = 'failed';

        /**
         * v0.0.27 Part 3b. The hours cron.
         *
         * HOURS_CRON_HOOK is named once, for the reason PLACES_CRON_HOOK
         * is: the gate, the registration and the status line all reach
         * for it. It is named for what the callback does - refresh - and
         * NOT after avalon_hours_sweep(), so a search for one does not
         * land on the other.
         *
         * HOURS_REFRESH_MARGIN_DAYS. The sweep re-asks ok and none rows
         * this many days BEFORE the positive TTL, so a row is refreshed
         * before the purge's hard cap clears it and a store page does not
         * lose its hours for a day every month. s0.262.
         */
        const HOURS_CRON_HOOK           = 'avalon_hours_refresh';
        const HOURS_REFRESH_MARGIN_DAYS = 2;
""")


# ===========================================================================
# 2. Registration in add_actions()
# ===========================================================================

WIRE_ANCHOR = crlf(r"""            add_action(self::PLACES_CRON_HOOK, array(self::$instance,'avalon_places_cron'));
            //
            // WP-CLI, inline and guarded - deliberately NOT a new file.
            // A require_once of a file that has not landed yet is fatal,
            // and Part 2 already paid that deploy-ordering tax once.
            if ( defined('WP_CLI') && WP_CLI ) {
                WP_CLI::add_command( 'avalon places', array(self::$instance,'avalon_places_cli') );
            }
""")

WIRE_BLOCK = crlf(r"""            add_action(self::PLACES_CRON_HOOK, array(self::$instance,'avalon_places_cron'));
            //
            // v0.0.27 Part 3b. The hours sweep: its own schedule gate on
            // init priority 1 and its own hook, registered unconditionally,
            // for the reasons given for the places pair above.
            add_action('init', array(self::$instance,'avalon_hours_maybe_schedule'), 1);
            add_action(self::HOURS_CRON_HOOK, array(self::$instance,'avalon_hours_cron'));
            //
            // WP-CLI, inline and guarded - deliberately NOT a new file.
            // A require_once of a file that has not landed yet is fatal,
            // and Part 2 already paid that deploy-ordering tax once.
            if ( defined('WP_CLI') && WP_CLI ) {
                WP_CLI::add_command( 'avalon places', array(self::$instance,'avalon_places_cli') );
                WP_CLI::add_command( 'avalon hours', array(self::$instance,'avalon_hours_cli') );
            }
""")


# ===========================================================================
# 3. The sweep, replaced whole, and the Part 3b methods after it
# ===========================================================================

SWEEP_START = crlf("        /**\n         * v0.0.27 Part 3a. One pass of the hours queue.")
SWEEP_END   = "        public function avalon_rest_protected_slugs(){"
SWEEP_MD5   = ('faf71539f080408c139ead7da7983931', 7592)

SWEEP_BLOCK = crlf(r"""        /**
         * v0.0.27 Part 3a. One pass of the hours queue.
         *
         * Returns counts; writes rows. Part 3b's cron and CLI call it; so
         * can a person, from wp eval.
         *
         * THE QUEUE, in one statement rather than a status scan:
         *
         *   place_status must be ok and place_id must be present. A row
         *   with no resolved place cannot be asked about, and asking
         *   would spend a call to be told so.
         *
         *   blocked is excluded outright, and so is every key in
         *   avalon_hours_disputed_keys(), whether or not it has been
         *   marked blocked yet. No caller can ask about a disputed place.
         *
         *   pending is always due.
         *   ok and none are due HOURS_REFRESH_MARGIN_DAYS before the
         *   POSITIVE ttl, so they are refreshed before the purge's hard
         *   30-day cap clears them. s0.262.
         *   failed is due on the NEGATIVE ttl - 7 days.
         *
         * hours_sweep (hours_status, fetched_at) is the index this was
         * written against, which is why the filter leads on status and
         * the order leads on fetched_at.
         *
         * NULLS FIRST, then oldest, then address_key. Identical to the
         * places queue so the two cannot drift into different notions
         * of fair.
         *
         * v0.0.27 Part 3b. NEVER-OVERWRITE IS IN THE STATEMENT. Both
         * writes carry AND place_id = <the id that was asked about> AND
         * hours_status <> blocked. A row blocked by hand, or re-pointed
         * by Part 3e, between the queue read and the write is refused by
         * the WHERE and counted as raced - never retried, which would be
         * the branch arguing with the statement that refused it. s0.258.
         *
         * ok MEANS THERE ARE WEEKDAY LINES TO SHOW. The renderer prints
         * weekdayDescriptions verbatim and nothing else, so a Place whose
         * hours carry only openNow is none. s0.260.
         *
         * A STRIKE CLEARS THE CACHED PLACE. The struck row is no longer ok
         * or none, so nothing renders from it, and the strike re-stamps
         * fetched_at - content left behind would outlive the 30-day cap
         * with nothing counting its age. s0.263.
         *
         * A FAILED READ IS NOT AN EMPTY QUEUE. wpdb::get_results() returns
         * an empty array, not null, when the database refuses the
         * statement; only last_error tells the two apart. s0.264.
         */
        public function avalon_hours_sweep( $limit = 0, $dry = false ){

            global $wpdb;

            $out = array(
                'scanned'  => 0,
                'ok'       => 0,
                'none'     => 0,
                'struck'   => 0,
                'failed'   => 0,
                'systemic' => 0,
                'raced'    => 0,
                'dry'      => (bool) $dry,
                'due'      => array(),
                'errors'   => array(),
            );

            $cfg = $this->avalon_hours_config();
            if ( empty( $cfg['enabled'] ) ) {
                $out['errors'][] = 'DISABLED';
                return $out;
            }

            $limit = ( (int) $limit > 0 ) ? (int) $limit : (int) $cfg['details_ceiling'];
            $table = self::avalon_hours_table();

            //Computed ONCE, before the loop. Every row written in this
            //run carries the same stamp, so queue order can never be
            //inferred from the timestamps afterwards.
            $now = current_time( 'mysql', true );

            $refresh = max( 1, (int) $cfg['positive_ttl_days'] - self::HOURS_REFRESH_MARGIN_DAYS );
            $pos_cut = gmdate( 'Y-m-d H:i:s', time() - ( $refresh * DAY_IN_SECONDS ) );
            $neg_cut = gmdate( 'Y-m-d H:i:s',
                               time() - ( (int) $cfg['negative_ttl_days'] * DAY_IN_SECONDS ) );

            $disputed = array_keys( self::avalon_hours_disputed_keys() );
            $not_in   = '';
            if ( ! empty( $disputed ) ) {
                $not_in = '   AND address_key NOT IN ('
                        . implode( ', ', array_fill( 0, count( $disputed ), '%s' ) ) . ')';
            }

            $args = array_merge(
                array( self::PLACES_STATUS_OK, self::HOURS_STATUS_BLOCKED ),
                $disputed,
                array(
                    self::HOURS_STATUS_PENDING,
                    self::HOURS_STATUS_OK,
                    self::HOURS_STATUS_NONE,
                    $pos_cut,
                    self::HOURS_STATUS_FAILED,
                    $neg_cut,
                    $limit,
                )
            );

            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT address_key, place_id, hours_status, error_count"
                . " FROM {$table}"
                . " WHERE place_status = %s"
                . "   AND place_id IS NOT NULL AND place_id <> ''"
                . "   AND hours_status <> %s"
                . $not_in
                . "   AND ("
                . "        hours_status = %s"
                . "     OR ( hours_status IN (%s, %s) AND ( fetched_at IS NULL OR fetched_at < %s ) )"
                . "     OR ( hours_status = %s        AND ( fetched_at IS NULL OR fetched_at < %s ) )"
                . "   )"
                . " ORDER BY fetched_at IS NULL DESC, fetched_at ASC, address_key ASC"
                . " LIMIT %d",
                $args
            ), ARRAY_A );

            //s0.264. A missing table or a lost connection comes back as an
            //empty array. Reported as nothing due, the cron would log a
            //clean run every day and never sweep again.
            if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
                $out['errors'][] = 'BADQUERY';
                return $out;
            }

            foreach ( $rows as $row ) {

                $out['scanned']++;

                if ( $dry ) {
                    //A dry run spends nothing and writes nothing. It
                    //answers which rows are due, which is the only
                    //question worth asking without paying.
                    $out['due'][] = (string) $row['address_key'];
                    continue;
                }

                $res = $this->avalon_hours_details( $row['place_id'] );

                if ( empty( $res['ok'] ) ) {

                    $err = (string) $res['error'];

                    if ( $this->avalon_places_is_systemic( $err ) ) {
                        //error_count and fetched_at are deliberately NOT
                        //touched. The row did nothing wrong and must not
                        //lose its place in the queue. And we stop: every
                        //remaining row would buy the same refusal.
                        $out['systemic']++;
                        $out['errors'][] = $err;
                        break;
                    }

                    $next  = (int) $row['error_count'] + 1;
                    $state = ( $next >= self::HOURS_ERROR_CEILING )
                             ? self::HOURS_STATUS_FAILED
                             : self::HOURS_STATUS_PENDING;

                    //s0.263. The cached Place goes with the strike.
                    $n = $wpdb->query( $wpdb->prepare(
                        "UPDATE {$table} SET error_count = error_count + 1,"
                        . " last_error = %s, hours_status = %s,"
                        . " hours_json = NULL, attribution_json = NULL,"
                        . " business_status = NULL, primary_type_display = NULL,"
                        . " locality = NULL, admin_area = NULL,"
                        . " fetched_at = %s, updated_at = %s"
                        . " WHERE address_key = %s AND place_id = %s"
                        . " AND hours_status <> %s",
                        substr( $err, 0, 190 ), $state, $now, $now,
                        $row['address_key'], $row['place_id'], self::HOURS_STATUS_BLOCKED
                    ) );

                    if ( false === $n ) {
                        //The database refused a write. Not a fact about the
                        //dealer, and every later row would buy a call that
                        //cannot be recorded.
                        $out['systemic']++;
                        $out['errors'][] = 'BADWRITE';
                        break;
                    }
                    if ( (int) $n < 1 ) {
                        $out['raced']++;
                        continue;
                    }

                    $out['struck']++;
                    if ( $state === self::HOURS_STATUS_FAILED ) {
                        $out['failed']++;
                    }
                    continue;
                }

                $place = is_array( $res['place'] ) ? $res['place'] : array();

                //s0.260. Weekday lines, or nothing to show.
                $has = ( isset( $place['regularOpeningHours'] )
                         && is_array( $place['regularOpeningHours'] )
                         && isset( $place['regularOpeningHours']['weekdayDescriptions'] )
                         && is_array( $place['regularOpeningHours']['weekdayDescriptions'] )
                         && ! empty( $place['regularOpeningHours']['weekdayDescriptions'] ) );

                $state = $has ? self::HOURS_STATUS_OK : self::HOURS_STATUS_NONE;

                //raw is stored beside place on purpose. If an inner New
                //field name turns out wrong, re-normalising stored bytes
                //is free and re-fetching 301 dealers is not.
                $payload = wp_json_encode( array(
                    'shape'  => 'new',
                    'source' => $res['api'],
                    'at'     => $now,
                    'place'  => $place,
                    'raw'    => $res['raw'],
                ) );

                $bstat = isset( $place['businessStatus'] )
                         ? substr( (string) $place['businessStatus'], 0, 24 )
                         : '';

                //s0.259. NULLIF, because wpdb::prepare() has no NULL: a
                //null bound through %s is escaped as '' and stored as ''.
                $n = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table} SET hours_json = %s, hours_status = %s,"
                    . " business_status = NULLIF(%s, ''), fetched_at = %s, updated_at = %s,"
                    . " error_count = 0, last_error = NULL"
                    . " WHERE address_key = %s AND place_id = %s"
                    . " AND hours_status <> %s",
                    $payload, $state, $bstat, $now, $now,
                    $row['address_key'], $row['place_id'], self::HOURS_STATUS_BLOCKED
                ) );

                if ( false === $n ) {
                    $out['systemic']++;
                    $out['errors'][] = 'BADWRITE';
                    break;
                }
                if ( (int) $n < 1 ) {
                    $out['raced']++;
                    continue;
                }

                if ( $has ) {
                    $out['ok']++;
                } else {
                    $out['none']++;
                }
            }

            return $out;
        }

        /**
         * v0.0.27 Part 3b. Place ids the hours sweep must never ask about.
         *
         * Adjudicated 2026-09-14 by build/score-placeid-matches.py r2 from
         * match-scores.csv, with no calls. A wrong place id publishes
         * ANOTHER dealer's opening hours on a real dealer's store page,
         * which is worse than showing nothing - a customer drives there
         * on a Sunday. s0.254 proved clearing a row cannot fix a
         * mis-resolve, so until Part 3e ships a correction path these
         * keys are blocked.
         *
         * THIS LIST IS CODE, NOT AN OPTION, on the precedent of
         * avalon_orphan_redirect_map(): it is nine rows, it is reviewable
         * in a diff, it travels to every environment with the plugin, and
         * a key comes off it in the release that corrects its place id.
         * placeids.json is one file for all three brand feeds, so a key
         * disputed on Aura is disputed wherever it appears.
         *
         * The two weaker-side rows are blocked because nobody has looked
         * at them yet, decided 2026-09-29. The third weaker-side row,
         * 0021b0d78410, was fetched that day and is the right place.
         *
         * Public so the suite reads the list rather than restating it.
         *
         * @return array address_key => why
         */
        public static function avalon_hours_disputed_keys(){
            return array(
                'b391a6d50f59' => 'MIS-RESOLVE: the place is 25.8 km away; it is 8f827b55e6c6',
                'e0487241edc4' => 'MIS-RESOLVE: the place is 63.2 km away; it is ea5de0bcdfe3',
                '88cd11dcda38' => 'BOTH WRONG: shared with aae51e47328a; the phone vetoes both',
                'aae51e47328a' => 'BOTH WRONG: shared with 88cd11dcda38; the phone vetoes both',
                'bd1658e3580d' => 'TWO LOCATIONS: the place is 14.2 km away; it is 6ff0d20c0500',
                '369e2ae4210a' => 'LOCATION MISMATCH: the place is 5.9 km away; it is 8e63db05c22b',
                'c0ebc2093268' => 'LOCATION MISMATCH: the place is 5.7 km away; it is 1b49e035fcd9',
                '3664a8c7ba9e' => 'WEAKER SIDE, unchecked: 604 m off; 343e3d465648 scores 105 to 65',
                '670217f109e9' => 'WEAKER SIDE, unchecked: 82e66db527bd scores 80 to 60',
            );
        }

        /**
         * v0.0.27 Part 3b. Mark every disputed key blocked, and clear
         * anything already cached for it.
         *
         * Clearing is the point, not a tidy-up. A disputed row fetched
         * before its key was listed holds another dealer's hours, and the
         * renderer must find nothing. The queue already refuses to ASK
         * about a listed key; this makes sure nothing already ASKED is
         * shown.
         *
         * One-way. It never releases a block: a key taken off the list
         * stays blocked until `wp avalon hours release` says otherwise, so
         * a block set by hand in an emergency is not silently undone by
         * the next cron run.
         *
         * @return int rows changed.
         */
        private function avalon_hours_sync_blocks( $now ){
            global $wpdb;

            $keys = array_keys( self::avalon_hours_disputed_keys() );
            if ( empty( $keys ) ) {
                return 0;
            }
            $table = self::avalon_hours_table();

            $n = $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET hours_status = %s, hours_json = NULL,"
                . " business_status = NULL, attribution_json = NULL,"
                . " fetched_at = NULL, error_count = 0, last_error = %s,"
                . " updated_at = %s"
                . " WHERE address_key IN ("
                . implode( ', ', array_fill( 0, count( $keys ), '%s' ) ) . ")"
                . " AND ( hours_status <> %s OR hours_json IS NOT NULL )",
                array_merge(
                    array( self::HOURS_STATUS_BLOCKED, 'DISPUTED place id', $now ),
                    $keys,
                    array( self::HOURS_STATUS_BLOCKED )
                )
            ) );
            return is_numeric( $n ) ? (int) $n : 0;
        }

        /**
         * v0.0.27 Part 3b. Return one blocked key to the queue.
         *
         * Refused while the key is still listed: the list is the
         * authority, and a release the next run would undo is not a
         * release. Part 3e calls this for a key whose place id it has
         * corrected, in the release that takes the key off the list.
         *
         * @return int|string rows released, or the reason nothing was.
         */
        public function avalon_hours_release( $key ){
            global $wpdb;

            $key = strtolower( trim( (string) $key ) );
            if ( ! preg_match( '/^[0-9a-f]{12}$/', $key ) ) {
                return 'not an address key: ' . $key;
            }
            if ( array_key_exists( $key, self::avalon_hours_disputed_keys() ) ) {
                return 'still listed in avalon_hours_disputed_keys(): ' . $key;
            }
            $table = self::avalon_hours_table();

            $n = $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET hours_status = %s, error_count = 0,"
                . " last_error = NULL, updated_at = %s"
                . " WHERE address_key = %s AND hours_status = %s",
                self::HOURS_STATUS_PENDING,
                current_time( 'mysql', true ),
                $key,
                self::HOURS_STATUS_BLOCKED
            ) );
            if ( false === $n ) {
                //s0.265. A refused write is not "no blocked row".
                return 'the database refused the release: ' . (string) $wpdb->last_error;
            }
            return (int) $n;
        }

        /**
         * v0.0.27 Part 3b. The hours schedule gate.
         *
         * The places gate's twin, for its reasons: init priority 1 because
         * activation never fires on a database-import site; the first run
         * an hour out so a deploy cannot sweep inside the request that
         * installed it; no unschedule branch, because a gate that also
         * removes fights an administrator who cleared the event on
         * purpose.
         *
         * It runs on every environment that runs the plugin - DEV and
         * LIVE both, decided 2026-09-29. A DEV-to-LIVE push copies DEV's
         * table over LIVE's, so each has to keep itself fresh.
         */
        public function avalon_hours_maybe_schedule(){
            if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
                return;
            }
            if ( wp_next_scheduled( self::HOURS_CRON_HOOK ) ) {
                return;
            }
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOURS_CRON_HOOK );
        }

        /**
         * v0.0.27 Part 3b. The hours cron callback.
         *
         * Blocks first and unconditionally: a disputed place id must never
         * render, whether or not the feature is switched on. Then one
         * sweep at details_ceiling, if enabled.
         *
         * The expiry of cached content is NOT here. avalon_places_purge()
         * owns it, on the places cron, deliberately not gated on enabled.
         * Two hooks running it would add nothing but a second place for
         * it to be wrong.
         *
         * The record goes through the places log, never through
         * avalon_flush_import_log(), which owns the CSV import cycle's
         * override-log rotation. s0.233.
         */
        public function avalon_hours_cron(){
            $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );

            $cfg = $this->avalon_hours_config();
            if ( empty( $cfg['enabled'] ) ) {
                return;
            }

            $out = $this->avalon_hours_sweep( (int) $cfg['details_ceiling'], false );

            $this->avalon_import_log( array(
                'stage'    => 'hours_sweep',
                'action'   => 'swept',
                'blocked'  => $blocked,
                'scanned'  => $out['scanned'],
                'ok'       => $out['ok'],
                'none'     => $out['none'],
                'struck'   => $out['struck'],
                'failed'   => $out['failed'],
                'systemic' => $out['systemic'],
                'raced'    => $out['raced'],
                'errors'   => implode( '; ', $out['errors'] ),
            ) );
            $this->avalon_places_log_flush();
        }

        /**
         * v0.0.27 Part 3b. The valid hours subcommands, named once, for
         * the reason avalon_places_subcommands() is.
         */
        public static function avalon_hours_subcommands(){
            return array( 'sweep', 'release', 'status' );
        }

        /**
         * v0.0.27 Part 3b. WP-CLI: wp avalon hours <subcommand>
         *
         *   status   counts by hours_status, closed dealers, the disputed
         *            list against the table, and the next scheduled run.
         *            Spends nothing.
         *   sweep    one pass of the queue. --dry-run lists what is due
         *            and spends nothing; --max-calls=<n> caps the pass at
         *            n (1 or more), default details_ceiling. A real pass
         *            blocks the disputed keys first, exactly as the cron
         *            does.
         *   release  --key=<address key> returns one blocked key to the
         *            queue. Refused while the key is still listed; an
         *            error when there was no blocked row to release.
         *
         * A bare `wp avalon hours` means status. An unknown subcommand is
         * an error, never a status table - s0.232. A sweep that stopped on
         * a systemic error exits through WP_CLI::error, because a refusal
         * aimed at the project is not a successful run - s0.220.
         *
         * NO CAPABILITY CHECK, for the reason avalon_places_cli() gives.
         * Invoke with --skip-plugins=revslider, never a bare
         * --skip-plugins.
         */
        public function avalon_hours_cli( $args, $assoc = array() ){
            global $wpdb;

            $sub   = isset( $args[0] ) ? (string) $args[0] : 'status';
            $table = self::avalon_hours_table();

            if ( 'sweep' === $sub ) {
                $dry = ! empty( $assoc['dry-run'] );
                $max = 0;
                if ( isset( $assoc['max-calls'] ) ) {
                    //s0.265. The sweep reads 0 as "use details_ceiling", so
                    //--max-calls=0, or a typo that casts to 0, would spend
                    //the full ceiling. Refused before anything is spent.
                    $raw = trim( (string) $assoc['max-calls'] );
                    $max = (int) $raw;
                    if ( $max < 1 || (string) $max !== $raw ) {
                        WP_CLI::error( '--max-calls must be a whole number, 1 or more. Nothing was spent.' );
                        return;
                    }
                }

                $blocked = 0;
                if ( ! $dry ) {
                    $blocked = $this->avalon_hours_sync_blocks( current_time( 'mysql', true ) );
                }

                $out = $this->avalon_hours_sweep( $max, $dry );

                WP_CLI::log( sprintf(
                    'scanned %d  ok %d  none %d  struck %d  failed %d  systemic %d  raced %d',
                    $out['scanned'], $out['ok'], $out['none'], $out['struck'],
                    $out['failed'], $out['systemic'], $out['raced']
                ) );

                if ( $dry && ! empty( $out['due'] ) ) {
                    WP_CLI::log( '' );
                    WP_CLI::log( 'due, in the order they would be asked:' );
                    foreach ( $out['due'] as $k ) {
                        WP_CLI::log( '  ' . $k );
                    }
                }

                if ( ! $dry ) {
                    WP_CLI::log( sprintf( 'disputed rows newly blocked %d', $blocked ) );
                    $this->avalon_import_log( array(
                        'stage'    => 'hours_cli',
                        'action'   => 'swept',
                        'blocked'  => $blocked,
                        'scanned'  => $out['scanned'],
                        'ok'       => $out['ok'],
                        'none'     => $out['none'],
                        'struck'   => $out['struck'],
                        'failed'   => $out['failed'],
                        'systemic' => $out['systemic'],
                        'raced'    => $out['raced'],
                        'errors'   => implode( '; ', $out['errors'] ),
                    ) );
                    $this->avalon_places_log_flush();
                }

                if ( ! empty( $out['errors'] ) ) {
                    WP_CLI::error( 'aborted on ' . $out['errors'][0] );
                    return;
                }

                WP_CLI::success( $dry
                    ? 'dry run, nothing spent and nothing written'
                    : 'sweep complete' );
                return;
            }

            if ( 'release' === $sub ) {
                $key = isset( $assoc['key'] ) ? (string) $assoc['key'] : '';
                if ( '' === $key ) {
                    WP_CLI::error( '--key=<address key> is required.' );
                    return;
                }
                $r = $this->avalon_hours_release( $key );
                if ( ! is_int( $r ) ) {
                    WP_CLI::error( $r );
                    return;
                }
                if ( $r < 1 ) {
                    //s0.265. A key that is absent, or not blocked, released
                    //nothing. Success here would read as done.
                    WP_CLI::error( 'nothing released: no blocked row for '
                        . strtolower( trim( $key ) ) . ' in this table.' );
                    return;
                }
                WP_CLI::success( sprintf( '%d key(s) returned to pending', $r ) );
                return;
            }

            if ( 'status' !== $sub ) {
                WP_CLI::error( sprintf(
                    'unknown subcommand "%s". Valid: %s',
                    $sub,
                    implode( ', ', self::avalon_hours_subcommands() )
                ) );
                return;
            }

            $counts = $wpdb->get_results(
                "SELECT hours_status, COUNT(*) AS n FROM {$table} GROUP BY hours_status",
                ARRAY_A
            );
            //s0.264, here too. A refused read is not an empty table.
            if ( '' !== (string) $wpdb->last_error ) {
                WP_CLI::error( 'status could not read the table: ' . $wpdb->last_error );
                return;
            }
            if ( ! is_array( $counts ) || empty( $counts ) ) {
                WP_CLI::log( 'queue empty' );
                return;
            }
            foreach ( $counts as $r ) {
                WP_CLI::log( sprintf( '%-8s %d', $r['hours_status'], (int) $r['n'] ) );
            }

            $closed = $wpdb->get_results(
                "SELECT business_status, COUNT(*) AS n FROM {$table}"
                . " WHERE business_status IS NOT NULL AND business_status <> 'OPERATIONAL'"
                . " GROUP BY business_status",
                ARRAY_A
            );
            if ( is_array( $closed ) ) {
                foreach ( $closed as $r ) {
                    WP_CLI::log( sprintf( '%-8s %d  (never rendered)', $r['business_status'], (int) $r['n'] ) );
                }
            }

            //s0.265. The list empties when Part 3e corrects the last key, and
            //IN () is a syntax error. The sweep and sync already guard it.
            $keys    = array_keys( self::avalon_hours_disputed_keys() );
            $held    = array();
            $marked  = 0;
            $present = 0;
            if ( ! empty( $keys ) ) {
                $held = $wpdb->get_results( $wpdb->prepare(
                    "SELECT hours_status, COUNT(*) AS n FROM {$table}"
                    . " WHERE address_key IN ("
                    . implode( ', ', array_fill( 0, count( $keys ), '%s' ) ) . ")"
                    . " GROUP BY hours_status",
                    $keys
                ), ARRAY_A );
            }
            if ( is_array( $held ) ) {
                foreach ( $held as $r ) {
                    $present += (int) $r['n'];
                    if ( self::HOURS_STATUS_BLOCKED === $r['hours_status'] ) {
                        $marked += (int) $r['n'];
                    }
                }
            }
            WP_CLI::log( sprintf( 'disputed listed %d  in this table %d  marked blocked %d',
                count( $keys ), $present, $marked ) );

            $next = wp_next_scheduled( self::HOURS_CRON_HOOK );
            WP_CLI::log( 'next sweep ' . ( $next
                ? gmdate( 'Y-m-d H:i:s', (int) $next ) . ' UTC'
                : 'NOT SCHEDULED' ) );
        }

""")


# ===========================================================================
# 4. The purge, replaced whole
# ===========================================================================

PURGE_START = crlf("        /**\n         * v0.0.26 Part 3. The TTL purge.")
PURGE_END   = crlf("        /**\n         * v0.0.26 Part 3d. Build the Text Search query for one dealer.")
PURGE_MD5   = ('0c4fccccef7f0daaaa2af225d3ce8c00', 2580)

PURGE_BLOCK = crlf(r"""        /**
         * v0.0.26 Part 3. The TTL purge.
         *
         * NOT GATED ON enabled, deliberately. Google's Places terms permit
         * place_id to be held indefinitely and cap every other field at 30
         * days. Setting AVALON_HOURS_ENABLED false to turn the feature off
         * must not leave cached hours sitting past their TTL forever - the
         * expiry is a licence obligation, not a feature.
         *
         * place_id, place_status and place_checked_at are never touched
         * here, for the same reason: they are the one thing the terms let
         * us keep.
         *
         * Two TTLs. A negative result is cheap to refetch and worth
         * retiring sooner; one cutoff for both would hold whichever is
         * longer against each.
         *
         * Driven off KEY hours_sweep (hours_status, fetched_at), which
         * exists for exactly this query.
         *
         * v0.0.27 Part 3b, s0.257. Two gaps closed. none rows hold a cached
         * Place too - the payload and its raw response - and were never
         * retired; they now go on the positive TTL with ok rows. And
         * business_status, with the three Place columns nothing fills yet,
         * is cleared with the payload: it is Places content under the same
         * cap, and a stale CLOSED_* would go on hiding a dealer that
         * reopened.
         *
         * s0.263. Content held under any OTHER status is under the same
         * cap. A strike clears its own row, so this should find nothing;
         * it is here for a row blocked by hand with its payload in place,
         * or released from one. Those keep their status - clearing a hand
         * block would ask about a place someone decided must not be asked
         * about - and lose only the content.
         *
         * In steady state this clears nothing. The sweep re-asks ok and
         * none rows HOURS_REFRESH_MARGIN_DAYS before the cap (s0.262), so
         * the purge acts only when the sweep is not running - disabled,
         * refused or unscheduled - which is exactly when the licence still
         * has to be honoured.
         *
         * @return int rows cleared.
         */
        public function avalon_places_purge(){
            global $wpdb;

            $cfg    = $this->avalon_hours_config();
            $table  = self::avalon_hours_table();
            $now    = current_time( 'mysql', true );
            $purged = 0;

            $sweeps = array(
                self::HOURS_STATUS_OK     => (int) $cfg['positive_ttl_days'],
                self::HOURS_STATUS_NONE   => (int) $cfg['positive_ttl_days'],
                self::HOURS_STATUS_FAILED => (int) $cfg['negative_ttl_days'],
            );

            foreach ( $sweeps as $status => $days ) {
                $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
                $n = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET hours_json = NULL, attribution_json = NULL,"
                        . " business_status = NULL, primary_type_display = NULL,"
                        . " locality = NULL, admin_area = NULL,"
                        . " hours_status = %s, fetched_at = NULL, updated_at = %s"
                        . " WHERE hours_status = %s AND fetched_at IS NOT NULL AND fetched_at < %s",
                        self::HOURS_STATUS_PENDING,
                        $now,
                        $status,
                        $cutoff
                    )
                );
                if ( is_numeric( $n ) ) {
                    $purged += (int) $n;
                }
            }

            //s0.263. Content under pending or blocked: cleared, status kept.
            //A NULL fetched_at is content of unknown age, and goes too.
            $cutoff = gmdate( 'Y-m-d H:i:s',
                              time() - ( (int) $cfg['positive_ttl_days'] * DAY_IN_SECONDS ) );
            $n = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET hours_json = NULL, attribution_json = NULL,"
                    . " business_status = NULL, primary_type_display = NULL,"
                    . " locality = NULL, admin_area = NULL, updated_at = %s"
                    . " WHERE hours_status IN (%s, %s)"
                    . " AND ( hours_json IS NOT NULL OR attribution_json IS NOT NULL"
                    . " OR business_status IS NOT NULL OR primary_type_display IS NOT NULL"
                    . " OR locality IS NOT NULL OR admin_area IS NOT NULL )"
                    . " AND ( fetched_at IS NULL OR fetched_at < %s )",
                    $now,
                    self::HOURS_STATUS_PENDING,
                    self::HOURS_STATUS_BLOCKED,
                    $cutoff
                )
            );
            if ( is_numeric( $n ) ) {
                $purged += (int) $n;
            }

            if ( $purged > 0 ) {
                self::log( 'places purge: ' . $purged . ' row(s) past TTL cleared' );
            }
            return $purged;
        }

""")


NEW_METHODS = (
    'public static function avalon_hours_disputed_keys(){',
    'private function avalon_hours_sync_blocks( $now ){',
    'public function avalon_hours_release( $key ){',
    'public function avalon_hours_maybe_schedule(){',
    'public function avalon_hours_cron(){',
    'public static function avalon_hours_subcommands(){',
    'public function avalon_hours_cli( $args, $assoc = array() ){',
)


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part3b.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part3b  slp_avalon 0.0.27  PART 3b of 4 (cron, CLI, block list, s0.257-s0.265)")
    print("")

    name = 'class.slp_avalon.php'
    md5, size = PINS[name]
    php, got_md5, got_size = read_exact(os.path.join(src_dir, name))
    if got_md5 != md5 or got_size != size:
        sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                 "       src_dir must hold the PART 3a OUTPUT.".format(
                     name, got_md5, got_size, md5, size))
    print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
    print("")

    braces_before = (php.count('{'), php.count('}'))

    # ---- patches ----------------------------------------------------------
    php = sub_once(php, CONST_ANCHOR, CONST_BLOCK, 'hours cron constants')
    php = sub_once(php, WIRE_ANCHOR, WIRE_BLOCK, 'cron hook, schedule gate and CLI registration')
    php = replace_span(php, SWEEP_START, SWEEP_END, SWEEP_MD5[0], SWEEP_MD5[1],
                       SWEEP_BLOCK, 'avalon_hours_sweep() + seven Part 3b methods')
    php = replace_span(php, PURGE_START, PURGE_END, PURGE_MD5[0], PURGE_MD5[1],
                       PURGE_BLOCK, 'avalon_places_purge()')
    print("")

    # ---- self-checks ------------------------------------------------------
    check("\r\n" in php and php.count("\n") == php.count("\r\n") and php.count("\r") == php.count("\r\n"),
          'the file is pure CRLF - no bare LF, no bare CR')

    for c in ('HOURS_CRON_HOOK', 'HOURS_REFRESH_MARGIN_DAYS'):
        check(php.count('const ' + c + ' ') == 1, 'constant declared once: ' + c)

    for m in NEW_METHODS:
        check(php.count(m) == 1, 'method declared once: ' + m.split('(')[0].split()[-1])
    check(php.count('public function avalon_hours_sweep( $limit = 0, $dry = false ){') == 1,
          'the sweep is declared once')
    check(php.count('public function avalon_places_purge(){') == 1,
          'the purge is declared once')

    check(php.count("add_action(self::HOURS_CRON_HOOK, array(self::$instance,'avalon_hours_cron'));") == 1,
          'the cron callback is registered on HOURS_CRON_HOOK, once')
    check(php.count("add_action('init', array(self::$instance,'avalon_hours_maybe_schedule'), 1);") == 1,
          'the schedule gate is registered on init priority 1, once')
    check(php.count("WP_CLI::add_command( 'avalon hours', array(self::$instance,'avalon_hours_cli') );") == 1,
          'wp avalon hours is registered, once')
    check(php.count("WP_CLI::add_command( 'avalon places', array(self::$instance,'avalon_places_cli') );") == 1,
          'wp avalon places is still registered, once')

    i = php.find('public function avalon_hours_sweep(')
    j = php.find('public static function avalon_hours_disputed_keys(){')
    sweep = php[i:j]
    check(sweep.count(" AND place_id = %s") == 2 and sweep.count(" AND hours_status <> %s\",") == 2,
          's0.258: both writes carry the place id and the block in their WHERE')
    check(sweep.count("business_status = NULLIF(%s, '')") == 1,
          's0.259: business_status is bound through NULLIF')
    check("['weekdayDescriptions'] )\n".replace('\n', '\r\n') in sweep
          and sweep.count("! empty( $place['regularOpeningHours']['weekdayDescriptions'] )") == 1,
          's0.260: ok is decided by weekday lines')
    check(sweep.count('self::HOURS_REFRESH_MARGIN_DAYS') == 1
          and '$pos_cut = gmdate( \'Y-m-d H:i:s\', time() - ( $refresh * DAY_IN_SECONDS ) );' in sweep,
          's0.262: the positive cutoff is taken early by the margin')
    check('NOT IN (' in sweep and 'self::avalon_hours_disputed_keys()' in sweep,
          'the queue excludes the disputed keys itself')
    check(sweep.count('$now = current_time( \'mysql\', true );') == 1
          and sweep.find('$now = current_time') < sweep.find('foreach ( $rows'),
          '$now is still computed once, before the loop')
    check(sweep.count('$wpdb->prepare(') == 3, 'every sweep statement goes through prepare()')
    strike = sweep.split('"UPDATE {$table} SET error_count = error_count + 1,"')[1].split('self::HOURS_STATUS_BLOCKED')[0]
    check(all(c + ' = NULL' in strike for c in ('hours_json', 'attribution_json', 'business_status',
                                                'primary_type_display', 'locality', 'admin_area')),
          's0.263: a strike clears the cached Place')
    check("if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {" in sweep,
          's0.264: a refused queue read is BADQUERY, not an empty queue')
    check(php.count("WP_CLI::error( '--max-calls must be a whole number, 1 or more. Nothing was spent.' );") == 1,
          's0.265: --max-calls below 1 is refused')
    check(php.count("WP_CLI::error( 'nothing released: no blocked row for '") == 1,
          's0.265: a release that released nothing is an error')
    check(php.count("return 'the database refused the release: ' . (string) $wpdb->last_error;") == 1,
          's0.265: a refused release says so')
    check(php.count("WP_CLI::error( 'status could not read the table: ' . $wpdb->last_error );") == 1,
          's0.264: status does not read a refused query as an empty table')
    sys_branch = sweep.split('avalon_places_is_systemic')[1].split('break;')[0]
    check('$wpdb->' not in sys_branch, 'the systemic branch still issues no query')

    k = php.find('public function avalon_places_purge(){')
    purge = php[k:php.find('Build the Text Search query for one dealer.', k)]
    check('self::HOURS_STATUS_NONE   => (int) $cfg[\'positive_ttl_days\'],' in purge,
          's0.257: none rows are retired on the positive TTL')
    check(purge.count(' business_status = NULL, primary_type_display = NULL,') == 2,
          's0.257: business_status is cleared with the payload')
    check(' WHERE hours_status IN (%s, %s)' in purge
          and 'self::HOURS_STATUS_PENDING,\r\n                    self::HOURS_STATUS_BLOCKED,' in purge,
          's0.263: content under pending or blocked is cleared, status kept')
    check(purge.count('$wpdb->prepare(') == 2, 'every purge statement goes through prepare()')

    # The resolver is out of scope. It must be byte-for-byte what Part 3a had.
    for keep in ('private function avalon_places_query( $row ){',
                 'public function avalon_places_resolve( $limit = 0, $dry = false ){',
                 'public function avalon_places_cli( $args, $assoc = array() ){',
                 'private static function avalon_hours_verdict( $status ){',
                 'public function avalon_hours_details( $place_id ){'):
        check(php.count(keep) == 1, 'untouched and present once: ' + keep.split('(')[0].split()[-1])

    check(php.count('{') == php.count('}'), 'php braces balance')
    check(php.count('{') > braces_before[0], 'the braces grew, as seven methods were added')
    print("")

    raw = php.encode('iso-8859-1')
    got = (hashlib.md5(raw).hexdigest(), len(raw))
    if got != OUT_PIN:
        sys.exit("ABORT output is {} / {} bytes, expected {} / {} - nothing written".format(
            got[0], got[1], OUT_PIN[0], OUT_PIN[1]))
    out_md5, out_size, crs = write_exact(os.path.join(out_dir, name), php)
    print("  output    {:<24} {} {} bytes  CR={}  (pinned)".format(name, out_md5, out_size, crs))
    print("")
    print("  note      slp_avalon.php is not an input at Part 3b; the version")
    print("            header already reads 0.0.27 from Part 1.")
    print("  note      no schema change. HOURS_DB_VERSION stays 2.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
