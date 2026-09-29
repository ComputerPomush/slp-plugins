#!/usr/bin/env python3
"""
build-v027-part3a.py

slp_avalon v0.0.27, PART 3a OF 4.

WHAT THIS RELEASE DOES
----------------------
Part 3a is the write layer and the queue. No cron hook and no CLI - both
are Part 3b, following the v0.0.26 precedent where Part 3 was split into
3, 3b, 3c and 3d rather than landing as one unverifiable lump.

1. A CORRECTION TO PART 2. s0.256.

   avalon_places_is_systemic() classifies by the SHAPE of the error
   string, and its own comment says the rule is load bearing:

       FATAL...  systemic     a refusal aimed at the key or project
       HTTP...   systemic     a transport or status-code failure
       ONEWORD   systemic     a bare exception or parse failure
       spaced    data         Google answered, and the answer was no

   Part 2 shipped five error strings that do not conform, and nothing
   caught it because nothing calls the fetch layer yet:

       'TRANSPORT eee'        spaced -> read as DATA. A bad network
                              afternoon would have struck the queue.
       'BAD_JSON http 500'    spaced -> read as DATA, same problem.
       'REQUEST_DENIED ...'   spaced -> read as DATA. An auth failure
                              would have struck all 301 dealers, three
                              times each, and then marked them failed.
       'ZERO_RESULTS'         one word -> read as SYSTEMIC. A real data
                              negative would have aborted the sweep.
       'NOT_FOUND'            one word -> same inversion.

   Both ends of the classifier were wrong, in opposite directions. The
   fix classifies by STATUS NAME at the emit site and spells the verdict
   into the prefix, so the shape rule and the explicit rule agree
   instead of one silently overriding the other.

2. Five status constants, mirroring the places side rather than
   inventing a parallel vocabulary. That side already declares
   PLACES_STATUS_PENDING / _OK / _FAILED, so the hours side uses
   'failed', not 'error' as earlier discussed, plus the two states
   hours needs and places does not:

       pending   never fetched, or struck below the ceiling
       ok        fetched, Google returned hours
       none      fetched, Google holds NO hours for this place
       blocked   disputed place ID, never enrolled
       failed    struck to the ceiling

   'none' and 'failed' both render nothing, and so does 'blocked'. One
   front-end behaviour, three causes, distinguishable in SQL.

3. avalon_hours_sweep( $limit, $dry ).

WHAT THE SWEEP WILL NOT DO
--------------------------
It will not touch a 'blocked' row, which is the gate agreed for the six
wrong place IDs out of fourteen collisions.

It will not continue after a systemic failure. If the key is refused,
every remaining row is refused for the same reason and each one costs a
billable call to learn it again. The places side counts systemic
failures; this one counts them and stops.

It will not stamp fetched_at or touch error_count on a systemic
failure, because a row must not lose its place in the queue over a
problem that was never about that row.

$now is computed ONCE before the loop, so every row written in a run
carries the same timestamp and queue order cannot be read off them.
That is the same property the places resolver documents.

ENCODING
--------
ISO-8859-1 with newline='' so CRLF survives. Input is CRLF; output must
stay CRLF and the CR count is printed.

Usage:  python build-v027-part3a.py <src_dir> <out_dir>

        src_dir must hold the PART 2 OUTPUT:
            class.slp_avalon.php   c372f4f230e3f895a0d3c55cb8235980
"""

import hashlib
import io
import os
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('c372f4f230e3f895a0d3c55cb8235980', 209652),
}


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def write_exact(path, text):
    raw = text.encode('iso-8859-1')
    io.open(path, 'wb').write(raw)
    return hashlib.md5(raw).hexdigest(), len(raw), raw.count(b'\r')


def sub_n(text, old, new, n, label):
    """Replace every occurrence, asserting the count first. Used where the
    same line appears once in each of the two callers by design."""
    got = text.count(old)
    if got != n:
        sys.exit("ABORT {}: anchor matched {} times, expected exactly {}".format(label, got, n))
    print("  patched   {} ({}x)".format(label, n))
    return text.replace(old, new)


def sub_once(text, old, new, label):
    return sub_n(text, old, new, 1, label)


def check(cond, label):
    if not cond:
        sys.exit("ABORT self-check: {}".format(label))
    print("  check OK  {}".format(label))


def block(lines):
    return CRLF.join(lines) + CRLF


# ===========================================================================
# 1. Part 2 error-vocabulary correction. s0.256.
# ===========================================================================

TRANSPORT_OLD = "                $out['error'] = 'TRANSPORT ' . $res->get_error_code();"
TRANSPORT_NEW = "                //FATAL prefix so avalon_places_is_systemic() reads this as"
TRANSPORT_NEW = block([
    TRANSPORT_NEW,
    "                //systemic. A transport failure is never a fact about the",
    "                //dealer and must not cost the row a strike. s0.256.",
    "                $out['error'] = 'FATAL TRANSPORT ' . $res->get_error_code();",
]).rstrip(CRLF)

BADJSON_OLD = "                $out['error'] = 'BAD_JSON http ' . $code;"
BADJSON_NEW = block([
    "                //One word, no space: systemic by construction, which is",
    "                //what the shape rule expects a parse failure to be.",
    "                $out['error'] = 'BADJSON';",
]).rstrip(CRLF)

# --- legacy: classify by status name -------------------------------------

LEGACY_OLD = block([
    "            if ( $status !== 'OK' || ! isset( $json['result'] ) || ! is_array( $json['result'] ) ) {",
    "                $msg = isset( $json['error_message'] ) ? ' ' . (string) $json['error_message'] : '';",
    "                $out['error'] = $status . $msg;",
    "                return $out;",
    "            }",
]).rstrip(CRLF)

LEGACY_NEW = block([
    "            if ( $status !== 'OK' || ! isset( $json['result'] ) || ! is_array( $json['result'] ) ) {",
    "                $msg = isset( $json['error_message'] ) ? ' ' . (string) $json['error_message'] : '';",
    "                $out['error'] = self::avalon_hours_verdict( $status ) . $msg;",
    "                return $out;",
    "            }",
]).rstrip(CRLF)

# --- new: same treatment --------------------------------------------------

NEWCALL_OLD = block([
    "                $st  = isset( $json['error']['status'] )  ? (string) $json['error']['status']  : 'HTTP_' . $code;",
    "                $msg = isset( $json['error']['message'] ) ? ' ' . (string) $json['error']['message'] : '';",
    "                $out['error'] = $st . $msg;",
    "                return $out;",
]).rstrip(CRLF)

NEWCALL_NEW = block([
    "                $st  = isset( $json['error']['status'] )  ? (string) $json['error']['status']  : 'HTTP ' . $code;",
    "                $msg = isset( $json['error']['message'] ) ? ' ' . (string) $json['error']['message'] : '';",
    "                //HTTP prefixes are already systemic under the shape rule;",
    "                //a named status still goes through the verdict map so the",
    "                //two callers cannot disagree about REQUEST_DENIED.",
    "                $out['error'] = ( 0 === strpos( $st, 'HTTP ' ) )",
    "                                ? $st . $msg",
    "                                : self::avalon_hours_verdict( $st ) . $msg;",
    "                return $out;",
]).rstrip(CRLF)


# ===========================================================================
# 2. Status constants
# ===========================================================================

CONST_ANCHOR = "        const HOURS_ENDPOINT_LEGACY = 'https://maps.googleapis.com/maps/api/place/details/json';"

CONST_BLOCK = block([
    "        /**",
    "         * v0.0.27 Part 3a. The hours state set.",
    "         *",
    "         * pending / ok / failed are spelled to match",
    "         * PLACES_STATUS_PENDING / _OK / _FAILED, because one class with",
    "         * two vocabularies for the same three ideas is how a future",
    "         * query ends up filtering on a status that never gets written.",
    "         *",
    "         * none and blocked are hours-only and are NOT failures:",
    "         *",
    "         *   none     the fetch worked and Google holds no hours for",
    "         *            this place. Common for dealerships that never set",
    "         *            them. Re-asked on the positive TTL, not the",
    "         *            negative one, because nothing went wrong.",
    "         *   blocked  the place ID is disputed and must never be asked.",
    "         *            Set by hand or by import, never by the sweep, and",
    "         *            the sweep never clears it.",
    "         *",
    "         * All three of none, blocked and failed render nothing. One",
    "         * front-end behaviour, three causes, told apart in SQL.",
    "         */",
    "        const HOURS_ERROR_CEILING   = 3;",
    "        const HOURS_STATUS_PENDING  = 'pending';",
    "        const HOURS_STATUS_OK       = 'ok';",
    "        const HOURS_STATUS_NONE     = 'none';",
    "        const HOURS_STATUS_BLOCKED  = 'blocked';",
    "        const HOURS_STATUS_FAILED   = 'failed';",
    "",
    "        const HOURS_ENDPOINT_LEGACY = 'https://maps.googleapis.com/maps/api/place/details/json';",
]).rstrip(CRLF)


# ===========================================================================
# 3. The verdict map and the sweep
# ===========================================================================

METHODS_ANCHOR = "        public function avalon_rest_protected_slugs(){"

METHODS_BLOCK = block([
    "        /**",
    "         * v0.0.27 Part 3a. Status name -> verdict prefix. s0.256.",
    "         *",
    "         * avalon_places_is_systemic() reads the SHAPE of the string.",
    "         * This decides the shape from the NAME, so the two agree by",
    "         * construction instead of by luck. Classifying a status only",
    "         * by whether its message happens to contain a space is how",
    "         * REQUEST_DENIED came to be read as a fact about a dealer.",
    "         *",
    "         * DEFAULT IS SYSTEMIC. An unrecognised status is a status this",
    "         * code has never seen, and striking 301 dealers over one is",
    "         * worse than stopping and being told about it.",
    "         */",
    "        private static function avalon_hours_verdict( $status ){",
    "",
    "            $status = (string) $status;",
    "",
    "            //Google answered, and the answer was no. A fact about this",
    "            //place, so this row takes the strike.",
    "            $data = array( 'ZERO_RESULTS', 'NOT_FOUND' );",
    "            if ( in_array( $status, $data, true ) ) {",
    "                return 'STATUS ' . $status;",
    "            }",
    "",
    "            return 'FATAL ' . $status;",
    "        }",
    "",
    "        /**",
    "         * v0.0.27 Part 3a. One pass of the hours queue.",
    "         *",
    "         * Returns counts; writes rows. Nothing schedules it yet - the",
    "         * cron hook and the CLI are Part 3b, so this runs only when a",
    "         * person calls it.",
    "         *",
    "         * THE QUEUE, in one statement rather than a status scan:",
    "         *",
    "         *   place_status must be ok and place_id must be present. A row",
    "         *   with no resolved place cannot be asked about, and asking",
    "         *   would spend a call to be told so.",
    "         *",
    "         *   blocked is excluded outright.",
    "         *",
    "         *   pending is always due.",
    "         *   ok and none are due on the POSITIVE ttl - 30 days, the cap.",
    "         *   failed is due on the NEGATIVE ttl - 7 days.",
    "         *",
    "         * hours_sweep (hours_status, fetched_at) is the index this was",
    "         * written against, which is why the filter leads on status and",
    "         * the order leads on fetched_at.",
    "         *",
    "         * NULLS FIRST, then oldest, then address_key. Identical to the",
    "         * places queue so the two cannot drift into different notions",
    "         * of fair.",
    "         */",
    "        public function avalon_hours_sweep( $limit = 0, $dry = false ){",
    "",
    "            global $wpdb;",
    "",
    "            $out = array(",
    "                'scanned'  => 0,",
    "                'ok'       => 0,",
    "                'none'     => 0,",
    "                'struck'   => 0,",
    "                'failed'   => 0,",
    "                'systemic' => 0,",
    "                'dry'      => (bool) $dry,",
    "                'errors'   => array(),",
    "            );",
    "",
    "            $cfg = $this->avalon_hours_config();",
    "            if ( empty( $cfg['enabled'] ) ) {",
    "                $out['errors'][] = 'DISABLED';",
    "                return $out;",
    "            }",
    "",
    "            $limit = ( (int) $limit > 0 ) ? (int) $limit : (int) $cfg['details_ceiling'];",
    "            $table = self::avalon_hours_table();",
    "",
    "            //Computed ONCE, before the loop. Every row written in this",
    "            //run carries the same stamp, so queue order can never be",
    "            //inferred from the timestamps afterwards.",
    "            $now = current_time( 'mysql', true );",
    "",
    "            $pos_cut = gmdate( 'Y-m-d H:i:s',",
    "                               time() - ( (int) $cfg['positive_ttl_days'] * DAY_IN_SECONDS ) );",
    "            $neg_cut = gmdate( 'Y-m-d H:i:s',",
    "                               time() - ( (int) $cfg['negative_ttl_days'] * DAY_IN_SECONDS ) );",
    "",
    "            $rows = $wpdb->get_results( $wpdb->prepare(",
    "                \"SELECT address_key, place_id, hours_status, error_count\"",
    "                . \" FROM {$table}\"",
    "                . \" WHERE place_status = %s\"",
    "                . \"   AND place_id IS NOT NULL AND place_id <> ''\"",
    "                . \"   AND hours_status <> %s\"",
    "                . \"   AND (\"",
    "                . \"        hours_status = %s\"",
    "                . \"     OR ( hours_status IN (%s, %s) AND ( fetched_at IS NULL OR fetched_at < %s ) )\"",
    "                . \"     OR ( hours_status = %s        AND ( fetched_at IS NULL OR fetched_at < %s ) )\"",
    "                . \"   )\"",
    "                . \" ORDER BY fetched_at IS NULL DESC, fetched_at ASC, address_key ASC\"",
    "                . \" LIMIT %d\",",
    "                self::PLACES_STATUS_OK,",
    "                self::HOURS_STATUS_BLOCKED,",
    "                self::HOURS_STATUS_PENDING,",
    "                self::HOURS_STATUS_OK,",
    "                self::HOURS_STATUS_NONE,",
    "                $pos_cut,",
    "                self::HOURS_STATUS_FAILED,",
    "                $neg_cut,",
    "                $limit",
    "            ), ARRAY_A );",
    "",
    "            if ( ! is_array( $rows ) ) {",
    "                $out['errors'][] = 'BADQUERY';",
    "                return $out;",
    "            }",
    "",
    "            foreach ( $rows as $row ) {",
    "",
    "                $out['scanned']++;",
    "",
    "                if ( $dry ) {",
    "                    //A dry run spends nothing and writes nothing. It",
    "                    //answers which rows are due, which is the only",
    "                    //question worth asking without paying.",
    "                    continue;",
    "                }",
    "",
    "                $res = $this->avalon_hours_details( $row['place_id'] );",
    "",
    "                if ( empty( $res['ok'] ) ) {",
    "",
    "                    $err = (string) $res['error'];",
    "",
    "                    if ( $this->avalon_places_is_systemic( $err ) ) {",
    "                        //error_count and fetched_at are deliberately NOT",
    "                        //touched. The row did nothing wrong and must not",
    "                        //lose its place in the queue. And we stop: every",
    "                        //remaining row would buy the same refusal.",
    "                        $out['systemic']++;",
    "                        $out['errors'][] = $err;",
    "                        break;",
    "                    }",
    "",
    "                    $next  = (int) $row['error_count'] + 1;",
    "                    $state = ( $next >= self::HOURS_ERROR_CEILING )",
    "                             ? self::HOURS_STATUS_FAILED",
    "                             : self::HOURS_STATUS_PENDING;",
    "",
    "                    $wpdb->query( $wpdb->prepare(",
    "                        \"UPDATE {$table} SET error_count = error_count + 1,\"",
    "                        . \" last_error = %s, hours_status = %s,\"",
    "                        . \" fetched_at = %s, updated_at = %s\"",
    "                        . \" WHERE address_key = %s\",",
    "                        substr( $err, 0, 190 ), $state, $now, $now, $row['address_key']",
    "                    ) );",
    "",
    "                    $out['struck']++;",
    "                    if ( $state === self::HOURS_STATUS_FAILED ) {",
    "                        $out['failed']++;",
    "                    }",
    "                    continue;",
    "                }",
    "",
    "                $place = is_array( $res['place'] ) ? $res['place'] : array();",
    "",
    "                //An empty regularOpeningHours is not the same as a missing",
    "                //one, but both mean the same thing to a reader: Google has",
    "                //no hours here. Neither is an error.",
    "                $has = ( isset( $place['regularOpeningHours'] )",
    "                         && is_array( $place['regularOpeningHours'] )",
    "                         && ! empty( $place['regularOpeningHours'] ) );",
    "",
    "                $state = $has ? self::HOURS_STATUS_OK : self::HOURS_STATUS_NONE;",
    "",
    "                //raw is stored beside place on purpose. If an inner New",
    "                //field name turns out wrong, re-normalising stored bytes",
    "                //is free and re-fetching 301 dealers is not.",
    "                $payload = wp_json_encode( array(",
    "                    'shape'  => 'new',",
    "                    'source' => $res['api'],",
    "                    'at'     => $now,",
    "                    'place'  => $place,",
    "                    'raw'    => $res['raw'],",
    "                ) );",
    "",
    "                $bstat = isset( $place['businessStatus'] )",
    "                         ? substr( (string) $place['businessStatus'], 0, 24 )",
    "                         : null;",
    "",
    "                $wpdb->query( $wpdb->prepare(",
    "                    \"UPDATE {$table} SET hours_json = %s, hours_status = %s,\"",
    "                    . \" business_status = %s, fetched_at = %s, updated_at = %s,\"",
    "                    . \" error_count = 0, last_error = NULL\"",
    "                    . \" WHERE address_key = %s\",",
    "                    $payload, $state, $bstat, $now, $now, $row['address_key']",
    "                ) );",
    "",
    "                if ( $has ) {",
    "                    $out['ok']++;",
    "                } else {",
    "                    $out['none']++;",
    "                }",
    "            }",
    "",
    "            return $out;",
    "        }",
    "",
    "        public function avalon_rest_protected_slugs(){",
]).rstrip(CRLF)


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part3a.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part3a  slp_avalon 0.0.27  PART 3a of 4 (write layer + queue)")
    print("")

    name = 'class.slp_avalon.php'
    md5, size = PINS[name]
    php, got_md5, got_size = read_exact(os.path.join(src_dir, name))
    if got_md5 != md5 or got_size != size:
        sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                 "       src_dir must hold the PART 2 OUTPUT.".format(
                     name, got_md5, got_size, md5, size))
    print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
    print("")

    # ---- patches ----------------------------------------------------------
    php = sub_n(php, TRANSPORT_OLD, TRANSPORT_NEW, 2, 's0.256 transport -> FATAL')
    php = sub_n(php, BADJSON_OLD, BADJSON_NEW, 2, 's0.256 BAD_JSON -> BADJSON')
    php = sub_once(php, LEGACY_OLD, LEGACY_NEW, 's0.256 legacy status -> verdict map')
    php = sub_once(php, NEWCALL_OLD, NEWCALL_NEW, 's0.256 New status -> verdict map')
    php = sub_once(php, CONST_ANCHOR, CONST_BLOCK, 'hours status constants')
    php = sub_once(php, METHODS_ANCHOR, METHODS_BLOCK, 'verdict map + sweep')
    print("")

    # ---- self-checks ------------------------------------------------------
    for c in ('HOURS_ERROR_CEILING', 'HOURS_STATUS_PENDING', 'HOURS_STATUS_OK',
              'HOURS_STATUS_NONE', 'HOURS_STATUS_BLOCKED', 'HOURS_STATUS_FAILED'):
        check(php.count('const ' + c) == 1, 'constant declared once: ' + c)

    check(php.count('private static function avalon_hours_verdict( $status ){') == 1,
          'verdict map declared once')
    check(php.count('public function avalon_hours_sweep( $limit = 0, $dry = false ){') == 1,
          'sweep declared once')
    check(php.count(METHODS_ANCHOR) == 1, 'the anchor method was moved, not duplicated')

    # s0.256: no non-conforming error string may survive anywhere.
    for gone in ("'TRANSPORT ' .", "'BAD_JSON http '", "$out['error'] = $status . $msg;",
                 "$out['error'] = $st . $msg;", "'HTTP_' . $code"):
        check(php.count(gone) == 0, 's0.256 non-conforming string removed: ' + repr(gone))

    check(php.count("$out['error'] = 'FATAL TRANSPORT ' . $res->get_error_code();") == 2,
          'both callers emit FATAL on transport failure')
    check(php.count("$out['error'] = 'BADJSON';") == 2,
          'both callers emit a one-word parse failure')
    check(php.count('self::avalon_hours_verdict(') == 2,
          'both callers route named statuses through the verdict map')

    # Every literal the verdict map can emit must classify as intended.
    def is_systemic(e):
        return e.startswith('FATAL') or e.startswith('HTTP') or (' ' not in e)
    for e in ('FATAL REQUEST_DENIED some message', 'FATAL OVER_QUERY_LIMIT',
              'FATAL TRANSPORT http_request_failed', 'BADJSON', 'DISABLED',
              'NO_KEY', 'EMPTY_PLACE_ID', 'HTTP 403 denied'):
        check(is_systemic(e), 'classifies systemic: ' + repr(e))
    for e in ('STATUS ZERO_RESULTS', 'STATUS NOT_FOUND'):
        check(not is_systemic(e), 'classifies as data, takes the strike: ' + repr(e))

    # The sweep must respect the gate and must stop on systemic.
    i = php.find('public function avalon_hours_sweep')
    j = php.find('public function avalon_rest_protected_slugs(){')
    seg = php[i:j]
    check('self::HOURS_STATUS_BLOCKED,' in seg, 'the queue excludes blocked rows')
    check('$out[\'systemic\']++;' in seg and 'break;' in seg,
          'a systemic failure stops the sweep')
    check(seg.count('$now = current_time( \'mysql\', true );') == 1
          and seg.find('$now = current_time') < seg.find('foreach ( $rows'),
          '$now is computed once, before the loop')
    # The systemic branch must write NOTHING - not a strike, not a stamp.
    # Testing for a column name matches the comment that explains the rule,
    # so the property asserted is the absence of any statement at all.
    sys_branch = seg.split('avalon_places_is_systemic')[1].split('break;')[0]
    check('$wpdb->' not in sys_branch, 'the systemic branch issues no query')
    check('error_count =' not in sys_branch, 'the systemic branch sets no error_count')
    check(seg.count('$wpdb->prepare(') == 3, 'every statement goes through prepare()')
    check('$dry' in seg and seg.count('continue;') >= 2, 'a dry run writes nothing')

    check(php.count('{') == php.count('}'), 'php braces balance')
    print("")

    out_md5, out_size, crs = write_exact(os.path.join(out_dir, name), php)
    print("  output    {:<24} {} {} bytes  CR={}".format(name, out_md5, out_size, crs))
    print("")
    print("  note      no cron hook and no CLI. Part 3b.")
    print("  note      nothing calls avalon_hours_sweep() automatically yet.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
