#!/usr/bin/env python3
"""
control-v029.py  r1  2026-09-14
SLP Dealer Guard - negative controls for suite-v029.

WITHOUT THIS FILE IN THE REPO, suite-v029 SCORING 106/106 ASSERTS NOTHING.
A suite that has only ever been run against a build that works has not been
shown to be capable of failing. Each control below removes exactly one
load-bearing decision from the good build and nothing else, and the release
is gated on the suite catching every one of them.

    python3 control-v029.py --in <class.slp_avalon.php> --out <dir>

Writes <dir>/<n>/class.slp_avalon.php for each control. Use an out* name
for the output directory - build/out*/ is gitignored, and four untracked
directories in a repo that has been untracked=0 is s0.218.

Every substitution is byte-exact and asserted unique before it is applied.
A control that could not be built is a hard error, never a skipped control:
a missing control is a decision nobody is testing.
"""

import argparse
import hashlib
import os
import sys

ENC = "iso-8859-1"

IN_MD5 = "c066b3f6227073fc0b939e7cdc0b81eb"
IN_LEN = 194852


# ---------------------------------------------------------------------------
# The controls. (name, why it matters, [(old, new), ...])
# ---------------------------------------------------------------------------

CONTROLS = [

    ("no_establishment_guard",
     "s0.223. Text Search answers a street address when it cannot find a "
     "business, shaped exactly like a hit. Without the guard, Rockingham "
     "resolves to an id with no business behind it and the row sits in the "
     "table looking resolved forever.",
     [(
         "                if ( ! in_array( 'establishment', $types, true ) ) {\r\n"
         "                    $out['err'] = $this->avalon_places_scrub(\r\n"
         "                        'NOT_ESTABLISHMENT ' . ( empty( $types )\r\n"
         "                            ? 'no types' : implode( ',', $types ) ), $key );\r\n"
         "                    return $out;\r\n"
         "                }\r\n",
         "                if ( false ) {\r\n"
         "                    $out['err'] = 'NOT_ESTABLISHMENT';\r\n"
         "                    return $out;\r\n"
         "                }\r\n",
     )]),

    ("strike_on_systemic",
     "s0.224. A transport error is not a fact about the dealer. If systemic "
     "failures strike, one bad network afternoon fails half the queue "
     "permanently and reset only returns failed to pending by hand.",
     [(
         "            if ( 0 === strpos( $err, 'HTTP' ) ) {\r\n"
         "                return true;\r\n"
         "            }\r\n"
         "            return ( false === strpos( $err, ' ' ) );\r\n",
         "            if ( 0 === strpos( $err, 'HTTP' ) ) {\r\n"
         "                return false;\r\n"
         "            }\r\n"
         "            return false;\r\n",
     )]),

    ("no_race_guard",
     "Never-re-resolve lives in the WHERE, not in a branch. Without AND "
     "place_id IS NULL a row that resolved between the queue read and the "
     "write is overwritten with a second, later answer.",
     [(
         "                        . \" WHERE address_key = %s AND place_status = %s\"\r\n"
         "                        . \" AND place_id IS NULL\",\r\n",
         "                        . \" WHERE address_key = %s AND place_status = %s\",\r\n",
     )]),

    ("ceiling_off_by_one",
     "PLACES_ERROR_CEILING is 3. With > instead of >= the row needs a "
     "fourth strike, so every unresolvable dealer is asked one extra time "
     "forever and the ceiling silently means four.",
     [(
         "                    . \" AND error_count >= %d\",\r\n",
         "                    . \" AND error_count > %d\",\r\n",
     )]),

    ("status_fallthrough",
     "s0.232. Without the guard an unknown subcommand becomes status, "
     "prints a plausible table and exits 0. That is how a missing deploy "
     "once looked like a successful dry run.",
     [(
         "            if ( 'status' !== $sub ) {\r\n"
         "                WP_CLI::error( sprintf(\r\n"
         "                    'unknown subcommand \"%s\". Valid: %s',\r\n"
         "                    $sub,\r\n"
         "                    implode( ', ', self::avalon_places_subcommands() )\r\n"
         "                ) );\r\n"
         "                return;\r\n"
         "            }\r\n"
         "\r\n",
         "",
     )]),

    ("systemic_stamps_checked",
     "place_checked_at means Google answered about THIS dealer. Stamping "
     "it on a refusal pushes a never-checked row to the back of KEY "
     "place_sweep for a reason that has nothing to do with the row.",
     [(
         "                \"UPDATE {$table} SET last_error = %s, updated_at = %s\"\r\n"
         "                . \" WHERE address_key = %s AND place_status = %s\",\r\n"
         "                substr( (string) $err, 0, 190 ),\r\n"
         "                $now,\r\n",
         "                \"UPDATE {$table} SET last_error = %s, updated_at = %s,\"\r\n"
         "                . \" place_checked_at = %s\"\r\n"
         "                . \" WHERE address_key = %s AND place_status = %s\",\r\n"
         "                substr( (string) $err, 0, 190 ),\r\n"
         "                $now,\r\n"
         "                $now,\r\n",
     )]),

    ("cli_rotates_log",
     "s0.233. Reaching for avalon_flush_import_log() from the CLI rotates "
     "the CSV import cycle's override log: the last import's overrides are "
     "displaced by CLI noise and the generation before that is destroyed.",
     [(
         "                $this->avalon_places_log_flush();\r\n"
         "                WP_CLI::log( sprintf(\r\n"
         "                    'rows %d  keys %d  inserted %d  updated %d"
         "  skipped %d  unmapped %d',\r\n",
         "                $this->avalon_flush_import_log( false );\r\n"
         "                WP_CLI::log( sprintf(\r\n"
         "                    'rows %d  keys %d  inserted %d  updated %d"
         "  skipped %d  unmapped %d',\r\n",
     )]),
]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="src", required=True)
    ap.add_argument("--out", dest="out", required=True)
    ap.add_argument("--allow-md5", default="")
    args = ap.parse_args()

    print("control-v029.py  r1  2026-09-14")
    print("  negative controls for suite-v029")
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
    print("      php -d short_open_tag=1 test/suite-v029.php"
          " \"$d/class.slp_avalon.php\"")
    print("    done")
    return 0


if __name__ == "__main__":
    sys.exit(main())
