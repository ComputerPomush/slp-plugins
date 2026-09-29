#!/usr/bin/env python3
"""
control-v030.py  r1  2026-09-29
SLP Dealer Guard - negative controls for suite-v030 (slp_avalon v0.0.27).

WITHOUT THIS FILE IN THE REPO, suite-v030 SCORING 109/109 ASSERTS NOTHING.
A suite that has only ever been run against a build that works has not been
shown to be capable of failing. Each control below removes exactly one
load-bearing decision from the good build and nothing else, and the release
is gated on the suite catching every one of them, by a pinned count.

    python3 control-v030.py --in <class.slp_avalon.php> --out <dir>

Writes <dir>/<name>/class.slp_avalon.php for each control. Use an out* name
for the output directory - build/out*/ is gitignored, and untracked
directories in a repo that has been untracked=0 since rev36 is s0.218.

Every substitution is byte-exact on the CRLF file and asserted unique before
it is applied. A control that could not be built is a hard error, never a
skipped control: a missing control is a decision nobody is testing.

Two controls touch nothing v0.0.27 wrote. drift_region_a and drift_region_b
each change one number in code that v0.0.27 must NOT have changed, to show
that suite-v030's region pins can fail - a comparator that has only ever
agreed has not been shown capable of disagreeing.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "8bc1724a7e932fed0bfc7cb18e6cd39f"
IN_LEN = 220840


# ---------------------------------------------------------------------------
# The controls. (name, why it matters, [(old, new), ...])
# ---------------------------------------------------------------------------

CONTROLS = [

    ("db_version_not_bumped",
     "s0.255, Part 1. The deploy is an SFTP overwrite of an active plugin; "
     "nothing re-activates. Without the bump avalon_hours_maybe_install() "
     "sees '1' = '1' and returns, and business_status is never added on any "
     "site that already has the table.",
     [(
         "        const HOURS_DB_VERSION = '2';\r\n",
         "        const HOURS_DB_VERSION = '1';\r\n",
     )]),

    ("column_uppercase",
     "dbDelta compares the CREATE TABLE text against DESCRIBE, which MySQL "
     "returns lowercase. An uppercase type re-issues the same ALTER on every "
     "run, forever, and nothing reports it.",
     [(
         '                "  business_status varchar(24) null default null,",\r\n',
         '                "  business_status VARCHAR(24) null default null,",\r\n',
     )]),

    ("verdict_default_data",
     "s0.256. The default verdict is systemic because an unrecognised status "
     "is one this code has never seen. Default it to data and REQUEST_DENIED "
     "strikes all 301 dealers three times each and marks them failed.",
     [(
         "            return 'FATAL ' . $status;\r\n",
         "            return 'STATUS ' . $status;\r\n",
     )]),

    ("zero_results_systemic",
     "s0.256, the other end. ZERO_RESULTS and NOT_FOUND are Google saying no "
     "about THIS place. Read as systemic, one dealer with no listing aborts "
     "every sweep and the rows behind it are never reached.",
     [(
         "            $data = array( 'ZERO_RESULTS', 'NOT_FOUND' );\r\n",
         "            $data = array();\r\n",
     )]),

    ("transport_spaced",
     "s0.256, as Part 2 shipped it. 'TRANSPORT x' has a space and no FATAL "
     "prefix, so the shape rule reads it as dealer data: one bad network "
     "afternoon strikes the queue.",
     [(
         "            $res = wp_remote_get( $url, array(\r\n"
         "                'timeout'    => $timeout > 0 ? $timeout : 8,\r\n"
         "                'sslverify'  => true,\r\n"
         "                'user-agent' => 'slp_avalon/' . self::HOURS_DB_VERSION,\r\n"
         "            ) );\r\n"
         "\r\n"
         "            if ( is_wp_error( $res ) ) {\r\n"
         "                //FATAL prefix so avalon_places_is_systemic() reads this as\r\n"
         "                //systemic. A transport failure is never a fact about the\r\n"
         "                //dealer and must not cost the row a strike. s0.256.\r\n"
         "                $out['error'] = 'FATAL TRANSPORT ' . $res->get_error_code();\r\n",
         "            $res = wp_remote_get( $url, array(\r\n"
         "                'timeout'    => $timeout > 0 ? $timeout : 8,\r\n"
         "                'sslverify'  => true,\r\n"
         "                'user-agent' => 'slp_avalon/' . self::HOURS_DB_VERSION,\r\n"
         "            ) );\r\n"
         "\r\n"
         "            if ( is_wp_error( $res ) ) {\r\n"
         "                //FATAL prefix so avalon_places_is_systemic() reads this as\r\n"
         "                //systemic. A transport failure is never a fact about the\r\n"
         "                //dealer and must not cost the row a strike. s0.256.\r\n"
         "                $out['error'] = 'TRANSPORT ' . $res->get_error_code();\r\n",
     )]),

    ("blocked_clause_dropped",
     "THE GATE. No OR branch of the queue admits 'blocked' today, so this "
     "control changes no row - it is caught on the finished statement. The "
     "explicit exclusion is what keeps a disputed place ID out if a branch "
     "is ever added, and a wrong place ID publishes another dealer's hours.",
     [(
         '                . "   AND hours_status <> %s"\r\n',
         "",
     ), (
         "                self::HOURS_STATUS_BLOCKED,\r\n",
         "",
     )]),

    ("failed_on_positive_ttl",
     "Two TTLs. A failed row is retried on the 7-day negative TTL; on the "
     "30-day positive one a transient Google problem hides a dealer's hours "
     "for a month.",
     [(
         "                self::HOURS_STATUS_FAILED,\r\n"
         "                $neg_cut,\r\n",
         "                self::HOURS_STATUS_FAILED,\r\n"
         "                $pos_cut,\r\n",
     )]),

    ("systemic_continues",
     "A refused key refuses every remaining row the same way, and each one "
     "costs a billable call to learn it again. The sweep stops; it does not "
     "carry on.",
     [(
         "                        $out['systemic']++;\r\n"
         "                        $out['errors'][] = $err;\r\n"
         "                        break;\r\n",
         "                        $out['systemic']++;\r\n"
         "                        $out['errors'][] = $err;\r\n"
         "                        continue;\r\n",
     )]),

    ("ceiling_off_by_one",
     "HOURS_ERROR_CEILING is 3. With > instead of >= a row needs a fourth "
     "strike, so the ceiling silently means four.",
     [(
         "                    $state = ( $next >= self::HOURS_ERROR_CEILING )\r\n",
         "                    $state = ( $next > self::HOURS_ERROR_CEILING )\r\n",
     )]),

    ("now_in_loop",
     "$now is read once, before the loop, so every row a run writes carries "
     "one stamp and queue order can never be inferred from timestamps. "
     "Proven on Aura DEV 2026-09-29: two rows, one fetched_at.",
     [(
         "                $out['scanned']++;\r\n"
         "\r\n"
         "                if ( $dry ) {\r\n",
         "                $out['scanned']++;\r\n"
         "                $now = current_time( 'mysql', true );\r\n"
         "\r\n"
         "                if ( $dry ) {\r\n",
     )]),

    ("dry_run_spends",
     "A dry run spends nothing and writes nothing. It is how the queue is "
     "read before a single call is paid for.",
     [(
         "                if ( $dry ) {\r\n"
         "                    //A dry run spends nothing and writes nothing. It\r\n"
         "                    //answers which rows are due, which is the only\r\n"
         "                    //question worth asking without paying.\r\n"
         "                    continue;\r\n"
         "                }\r\n"
         "\r\n",
         "",
     )]),

    ("no_left_pad",
     "Legacy has been seen to write a time as three digits. Without the pad "
     "'930' reads as hour 93, minute 0.",
     [(
         "            $t = str_pad( $t, 4, '0', STR_PAD_LEFT );\r\n",
         "",
     )]),

    ("utc_offset_modern_only",
     "Measured 2026-09-15: legacy returned the offset under the DEPRECATED "
     "key utc_offset. Reading only utc_offset_minutes gives every dealer a "
     "null offset, and open/closed can never be computed at render.",
     [(
         "            } elseif ( isset( $r['utc_offset'] ) ) {\r\n"
         "                $place['utcOffsetMinutes'] = (int) $r['utc_offset'];\r\n",
         "",
     )]),

    ("business_status_dropped",
     "s0.255. business_status is a filter, not a payload - the renderer "
     "must refuse CLOSED_* without decoding longtext. A sweep that stops "
     "writing the column leaves a permanently closed dealer publishable.",
     [(
         '                    . " business_status = %s, fetched_at = %s, updated_at = %s,"\r\n',
         '                    . " fetched_at = %s, updated_at = %s,"\r\n',
     ), (
         "                    $payload, $state, $bstat, $now, $now, $row['address_key']\r\n",
         "                    $payload, $state, $now, $now, $row['address_key']\r\n",
     )]),

    ("empty_hours_ok",
     "An empty regularOpeningHours is not hours. Counted as ok, the row is "
     "published as having hours and the store page renders an empty block "
     "where 'none' would have rendered nothing.",
     [(
         "                $has = ( isset( $place['regularOpeningHours'] )\r\n"
         "                         && is_array( $place['regularOpeningHours'] )\r\n"
         "                         && ! empty( $place['regularOpeningHours'] ) );\r\n",
         "                $has = ( isset( $place['regularOpeningHours'] )\r\n"
         "                         && is_array( $place['regularOpeningHours'] ) );\r\n",
     )]),

    ("raw_not_stored",
     "The inner New field names are not yet confirmed against a live New "
     "response. raw is stored so a wrong name costs a re-normalisation over "
     "stored bytes, not 301 billable calls.",
     [(
         "                    'place'  => $place,\r\n"
         "                    'raw'    => $res['raw'],\r\n",
         "                    'place'  => $place,\r\n",
     )]),

    ("drift_region_a",
     "v0.0.27 must not have touched region A - the territory gate, the "
     "import guard, the reconcile, the redirects. One number changed there "
     "has to fail the suite.",
     [(
         "            array( 'CONUS + Canada',      24.4,    83.2,   -141.0,    -52.0 ),\r\n",
         "            array( 'CONUS + Canada',      24.4,    83.3,   -141.0,    -52.0 ),\r\n",
     )]),

    ("drift_region_b",
     "v0.0.27 must not have touched region B - the Part 3d resolver the "
     "unattended cron proved. suite-v029's evidence carries forward only by "
     "byte identity, so one changed operator there has to fail the suite.",
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

    print("control-v030.py  r1  2026-09-29")
    print("  negative controls for suite-v030")
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

        print("    %-24s %s  %7d  (%+d)"
              % (name, hashlib.md5(blob).hexdigest(), len(blob),
                 len(blob) - len(raw)))

    print()
    print("  %d control(s) written to %s" % (len(CONTROLS), args.out))
    print()
    print("  each must FAIL the suite. Score them:")
    print("    for d in %s/*/; do" % args.out)
    print("      php -d short_open_tag=1 test/suite-v030.php"
          " \"$d/class.slp_avalon.php\"")
    print("    done")
    return 0


if __name__ == "__main__":
    sys.exit(main())
