#!/usr/bin/env python3
"""
build-v027.py

slp_avalon v0.0.26 -> v0.0.27, PART 1 OF N.

WHAT THIS RELEASE DOES
----------------------
Part 1 is a schema bump and nothing else. No network call, no cron, no
CLI subcommand, no shortcode, no rendering. One column, one version
constant.

1. business_status varchar(24) null default null.

   Place Details returns business_status alongside opening_hours -
   measured on Aura DEV 2026-09-15 22:24:59 GMT, control place ID
   ChIJTUJR1je6rYkR-Tl154_fum8, value OPERATIONAL. The other two values
   are CLOSED_TEMPORARILY and CLOSED_PERMANENTLY, and a dealer carrying
   either must not have opening hours published.

   That is a FILTER, not a payload. Left inside hours_json it can only
   be applied by decoding longtext on every candidate row; as a column
   the sweep and the renderer both read it directly. s0.255.

2. HOURS_DB_VERSION '1' -> '2', which is what makes
   avalon_hours_maybe_install() run dbDelta again on the next init.

WHY NOW AND NOT AFTER THE FETCH LANDS
-------------------------------------
Measured on Aura DEV 2026-09-15: the table holds 301 rows and
hours_json is NULL in every one of them. dbDelta's ALTER therefore adds
a column to a table with no cached payload in it. Land this after the
first sweep and the same ALTER runs across 301 rows of live Places
content under a 30-day cap that cannot be refreshed for free.

WHAT PART 1 DELIBERATELY DOES NOT DO
------------------------------------
It does not touch a single KEY. The schema comment in
avalon_hours_install() records that dbDelta mishandles index prefix
lengths and can re-add the same index indefinitely; changing an
existing index is the neighbouring hazard and it is not worth taking
for this column. hours_sweep already narrows on hours_status, which is
indexed, and business_status is then a post-filter over at most
details_ceiling rows. An index would buy nothing and risk the loop.

It does not bump PLACES_DB anything. place_status, place_checked_at and
place_id are untouched, so the Part 3d verification of 2026-09-15
stands and does not need redoing.

ENCODING
--------
ISO-8859-1 throughout with newline='' so CRLF and any high bytes
survive byte-for-byte. Both inputs are CRLF; both outputs must stay
CRLF, and the CR count is printed for each so a mangled write cannot
pass quietly.

slp_avalon.js is NOT an input and NOT an output at Part 1. Its rev40
pin stands unchanged.

Usage:  python build-v027.py <src_dir> <out_dir>

        src_dir must hold the two v0.0.26 files, flat:
            class.slp_avalon.php
            slp_avalon.php
"""

import hashlib
import io
import os
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('c066b3f6227073fc0b939e7cdc0b81eb', 194852),
    'slp_avalon.php':       ('ece98000d2af9f9a77374bd240942001', 1808),
}


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def write_exact(path, text):
    raw = text.encode('iso-8859-1')
    io.open(path, 'wb').write(raw)
    return hashlib.md5(raw).hexdigest(), len(raw), raw.count(b'\r')


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


def block(lines):
    """Join source lines with CRLF and terminate with CRLF."""
    return CRLF.join(lines) + CRLF


# ===========================================================================
# 1. Schema version constant
# ===========================================================================

VERSION_ANCHOR = "        const HOURS_DB_VERSION = '1';"

VERSION_BLOCK = block([
    "        //v2, v0.0.27 Part 1, adds business_status. Bumped while",
    "        //hours_json was still NULL in all 301 rows, so dbDelta's",
    "        //ALTER ran against an empty column and not across a table",
    "        //of cached Places content under the 30-day cap. s0.255.",
    "        const HOURS_DB_VERSION = '2';",
]).rstrip(CRLF)


# ===========================================================================
# 2. The column
# ===========================================================================
#
# Placed immediately before primary_type_display so the four cached
# Place Details fields sit together in declaration order. Position is
# cosmetic for dbDelta, which adds a missing column wherever it likes;
# it is not cosmetic for the next person reading the CREATE TABLE.
#
# varchar(24) because the longest value Google documents is
# CLOSED_TEMPORARILY at 18 characters. varchar(n) is a storage length,
# not an integer display width, so the no-display-widths rule in the
# schema comment does not apply to it.

COLUMN_ANCHOR = '                "  primary_type_display varchar(190) null default null,",'

COLUMN_BLOCK = block([
    '                "  business_status varchar(24) null default null,",',
    '                "  primary_type_display varchar(190) null default null,",',
]).rstrip(CRLF)


# ===========================================================================
# main
# ===========================================================================

SCHEMA_KEYS = (
    '"  PRIMARY KEY  (address_key),",',
    '"  KEY sl_id (sl_id),",',
    '"  KEY place_id (place_id),",',
    '"  KEY hours_sweep (hours_status, fetched_at),",',
    '"  KEY place_sweep (place_status, place_checked_at)"',
)


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027  slp_avalon 0.0.26 -> 0.0.27  PART 1 of N (schema)")
    print("")

    # ---- inputs -----------------------------------------------------------
    blobs = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes".format(
                name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        blobs[name] = text
    print("")

    php = blobs['class.slp_avalon.php']
    boot = blobs['slp_avalon.php']

    keys_before = [php.count(k) for k in SCHEMA_KEYS]
    braces_before = (php.count('{'), php.count('}'))

    # ---- patches ----------------------------------------------------------
    php = sub_once(php, VERSION_ANCHOR, VERSION_BLOCK, 'HOURS_DB_VERSION 1 -> 2')
    php = sub_once(php, COLUMN_ANCHOR, COLUMN_BLOCK, 'business_status column')
    boot = sub_once(boot, 'Version: 0.0.26', 'Version: 0.0.27', 'version header')
    print("")

    # ---- self-checks ------------------------------------------------------
    check(php.count("const HOURS_DB_VERSION = '2';") == 1
          and php.count("const HOURS_DB_VERSION = '1';") == 0,
          'schema version arrived and the old one departed')

    check(php.count('"  business_status varchar(24) null default null,",') == 1,
          'business_status declared exactly once')

    check(php.count('"  primary_type_display varchar(190) null default null,",') == 1,
          'primary_type_display was moved, not duplicated')

    # dbDelta parses by line. Every declared field must still be its own
    # source line inside the implode, and the new one must be lowercase.
    col_line = '"  business_status varchar(24) null default null,",'
    i = php.find(col_line)
    line_start = php.rfind(CRLF, 0, i) + len(CRLF)
    line_end = php.find(CRLF, i)
    whole = php[line_start:line_end]
    check(whole.strip() == col_line,
          'the new column is alone on its source line')
    check('varchar(24)' in whole and whole == whole.replace('VARCHAR', 'varchar'),
          'the new column type is lowercase')

    # Indexes must be untouched. dbDelta re-adds indexes it mis-parses,
    # forever, and nothing reports it.
    keys_after = [php.count(k) for k in SCHEMA_KEYS]
    check(keys_after == keys_before and keys_after == [1, 1, 1, 1, 1],
          'all five keys present and unchanged')

    check(php.count('PRIMARY KEY  (address_key)') == 1,
          'PRIMARY KEY still carries its two spaces')

    check((php.count('{'), php.count('}')) == braces_before,
          'php braces balance unchanged')

    # The places side of the table is not in scope for Part 1.
    for untouched in ('place_status varchar(16)',
                      'place_checked_at datetime',
                      'place_id varchar(191)'):
        check(php.count('"  {} null default null,",'.format(untouched)) == 1
              or php.count(untouched) >= 1,
              'places column untouched: {}'.format(untouched))

    check(boot.count('Version: 0.0.27') == 1 and boot.count('Version: 0.0.26') == 0,
          'plugin version arrived and the old one departed')
    print("")

    # ---- outputs ----------------------------------------------------------
    for name, text in (('class.slp_avalon.php', php),
                       ('slp_avalon.php', boot)):
        md5, size, crs = write_exact(os.path.join(out_dir, name), text)
        print("  output    {:<24} {} {} bytes  CR={}".format(name, md5, size, crs))

    print("")
    print("  note      slp_avalon.js is not an input and not an output at")
    print("            Part 1. Its rev40 pin stands unchanged.")
    print("  note      no KEY was added, changed or removed.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
