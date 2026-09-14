#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
control-v028.py  r1  2026-09-14

Emits deliberately BROKEN builds of class.slp_avalon.php, one per
decision that suite-v028 claims to enforce. Each is a single targeted
mutation of the good artefact.

WHY THIS EXISTS. s0.195 and s0.214: a check labelled as a control is not
one until it has failed. A suite that scores full marks against the good
build has demonstrated that it runs, not that it can see anything. Every
assertion worth pinning must be shown to go red when the behaviour it
names is removed - and to go red for the behavioural reason, not because
the file stopped parsing, which is why each output is linted here.

WHAT EACH CONTROL DEFEATS

  never_overwrite  strips AND place_status = %s from the UPDATE's WHERE.
                   s0.231: this is only observable where the PHP branch
                   is not also true, because an already-ok row hits
                   already_ok++ and continues before the statement runs.
                   suite-v028 sees it through a snapshot/row divergence.

  stamp_now        binds $now to place_checked_at instead of the stamp
                   parsed from the file's resolved_utc.

  log_unguarded    moves the import-log write out of the apply guard, so
                   a dry run writes.

  cli_pin_optional makes --expect-md5 optional when writing.

The input is the OUTPUT of patch-import-placeids.py, pinned, so a
control can never be built from a file nobody verified.

Usage:
    python3 control-v028.py --in <built class.slp_avalon.php> --out <dir>
"""

import argparse
import hashlib
import os
import shutil
import subprocess
import sys

IN_MD5 = "b183d34355739ff219f788a66a8ea90b"
IN_LEN = 167037

CRLF = "\r\n"


def nl(*lines):
    return CRLF.join(lines)


CONTROLS = [
    (
        "never_overwrite",
        "the UPDATE no longer refuses a row that is not pending",
        nl(
            '                        . " WHERE address_key = %s AND place_status = %s",',
            "                        $row['place_id'], self::PLACES_STATUS_OK, $row['stamp'],",
            "                        $now, $key, self::PLACES_STATUS_PENDING",
        ),
        nl(
            '                        . " WHERE address_key = %s",',
            "                        $row['place_id'], self::PLACES_STATUS_OK, $row['stamp'],",
            "                        $now, $key",
        ),
    ),
    (
        "stamp_now",
        "place_checked_at records the import, not the resolution",
        nl(
            "                        $row['place_id'], self::PLACES_STATUS_OK, $row['stamp'],",
        ),
        nl(
            "                        $row['place_id'], self::PLACES_STATUS_OK, $now,",
        ),
    ),
    (
        "log_unguarded",
        "a dry run writes an import-log record",
        nl(
            "            if ( $apply ) {",
            "                $this->avalon_import_log( array(",
        ),
        nl(
            "            if ( true ) {",
            "                $this->avalon_import_log( array(",
        ),
    ),
    (
        "cli_pin_optional",
        "the CLI writes without --expect-md5",
        nl(
            "                if ( ! $dry && '' === $pin ) {",
        ),
        nl(
            "                if ( false && '' === $pin ) {",
        ),
    ),
]


def die(msg):
    sys.stderr.write("FAIL  %s\n" % msg)
    sys.exit(1)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="src", required=True)
    ap.add_argument("--out", dest="out", required=True)
    ap.add_argument("--skip-lint", action="store_true")
    args = ap.parse_args()

    with open(args.src, "r", encoding="iso-8859-1", newline="") as fh:
        good = fh.read()

    raw = good.encode("iso-8859-1")
    got = hashlib.md5(raw).hexdigest()
    print("INPUT (the good build)")
    print("  path   %s" % args.src)
    print("  md5    %s" % got)
    print("  bytes  %s" % format(len(raw), ","))
    if got != IN_MD5 or len(raw) != IN_LEN:
        die("input is not the pinned build: %s / %d" % (got, len(raw)))
    print("  pinned OK")
    print()

    os.makedirs(args.out, exist_ok=True)

    php = shutil.which("php") if not args.skip_lint else None

    print("CONTROLS")
    for name, what, find, repl in CONTROLS:
        n = good.count(find)
        if n != 1:
            die("control %s anchor matched %d times, expected exactly 1" % (name, n))
        text = good.replace(find, repl, 1)
        out = text.encode("iso-8859-1")

        # A mutation that changed nothing is not a control, it is a copy.
        if out == raw:
            die("control %s produced an identical file" % name)
        # Same shape as the good build in every respect but the behaviour.
        if out.count(b"\n") != out.count(b"\r\n"):
            die("control %s leaked a bare LF" % name)

        d = os.path.join(args.out, name)
        os.makedirs(d, exist_ok=True)
        dst = os.path.join(d, "class.slp_avalon.php")
        with open(dst, "wb") as fh:
            fh.write(out)

        lint = "skipped"
        if php:
            # A control that fails because the file will not parse proves
            # nothing about the assertion it is aimed at.
            rc = subprocess.run(
                [php, "-d", "short_open_tag=1", "-l", dst],
                stdout=subprocess.PIPE, stderr=subprocess.STDOUT
            )
            if rc.returncode != 0:
                die("control %s does not lint:\n%s"
                    % (name, rc.stdout.decode("utf-8", "replace")))
            lint = "clean"

        print("  %-17s %s" % (name, what))
        print("      md5 %s  bytes %s  delta %+d  lint %s"
              % (hashlib.md5(out).hexdigest(), format(len(out), ","),
                 len(out) - len(raw), lint))
    print()
    print("  %d control build(s) written to %s" % (len(CONTROLS), args.out))


if __name__ == "__main__":
    main()
