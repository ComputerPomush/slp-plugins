#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.17 from the shipped v0.0.16 artefacts.

FOUR EDITS, ONE FILE. All in class.slp_avalon.php. slp_avalon.php takes the
version header and nothing else. slp_avalon.js is NOT touched.

  1. ROTATE avalon_geocode_overrides.  rev16 s6.
  2. Tier 2 correction cap default 25 -> 60.  rev16 s7.2, rev15 s5.
  3. isset() guard on $location['sl_address2'].  rev16 s7.3, rev14 s0.33.
  4. Correct the circuit-breaker comment.  rev16 s7.4, rev15 s5.

-----------------------------------------------------------------------------
EDIT 1 - the override log grew without bound
-----------------------------------------------------------------------------
Measured on Aura DEV before truncation: 405 entries, 86,712 bytes, ~214 bytes
per entry, 55 entries a night, ~4.3 MB/year. autoload is off, so there is no
page-load cost; the cost is inside the import. avalon_flush_import_log() does a
read-modify-write of the WHOLE option every 20 entries, three or four times a
run. At 20,000 entries that is a 4 MB unserialize into a 20,000-element array,
four times a night, under php-fpm. update_option never reset or rotated it.

The rotation fires on the FIRST flush of a run, gated by a new per-import state
key `overrides_rotated`. avalon_state() returns 0 for a key it has never seen,
so the first test is falsy for free and no initialisation is needed. The ten
keys already in use are cache_dirty, excluded, exclusion_hits, geocode_cache,
geocodes_spent, log_buffer, observed, tier1_written, tier2_aborted and
tier2_written - no collision.

Placing it here rather than on an import-start hook puts the rotation inside the
callback v0.0.16 repaired, so it travels on a hook path that now has a negative
control, while still giving the start-of-import semantic: between runs
avalon_geocode_overrides holds the most recent complete import and
avalon_geocode_overrides_prev the one before it. Both stay autoload = off.

Per-import state is per-REQUEST. If SLP respawned an import across requests each
request would rotate and split one run's log across the two options. It does
not: tier1_written 11 / tier2_written 17 / observed 27 are whole-file totals
read out of avalon_import_state, and they came back identical on the unattended
cron path (2026-09-04T05:15:39Z) and the WP-CLI path (2026-09-05T02:23:14Z). A
respawn would have reset those counters and produced partial totals.

The existing `count($stored) > 500` slice is deliberately KEPT. It stops being a
history bound and becomes a per-run ceiling for a catastrophic import.

-----------------------------------------------------------------------------
EDIT 2 - the cap inverted its own purpose
-----------------------------------------------------------------------------
The guard filters slp_csv_locationdata - the CSV row, not the database row. The
CSV still carries all seventeen wrong coordinates, so Tier 2 re-corrects the
same seventeen rows on EVERY import, permanently, until the upstream feed is
fixed. dlrloc.csv af4260321d3127a35c2d5853de93e4c6 still holds the TOONS/Grand
Lake transposition, the chained Germaine shift, the BAY OUTBOARD 46.48 typo and
the -9838239 longitude.

Standing state is 17 corrections against a cap of 25. Eight rows of headroom on
the only feed ever measured. Tahoe and Avalon are UNMEASURED and have never run
the guard. If either exceeds 25 the cap latches on their first import and the
whole Tier 2 pass aborts on a live site. 60 is roughly 20% of 295 evaluated
rows: 3.5x the measured baseline, an order of magnitude below the 50%+ that a
systemic break would look like.

-----------------------------------------------------------------------------
EDIT 3 - the guard must be hash-neutral, and '' is what makes it so
-----------------------------------------------------------------------------
create_location_hash() builds

    "{$name}_{$address}_{$address2}_{$city}_{$state}_{$zip}_{$country}_{$dealer_id}"

Today the undefined key raises E_WARNING and evaluates to null, and null
interpolates as the empty string. Substituting '' therefore produces a
BYTE-IDENTICAL hash for all 308 rows. Anything else re-hashes the whole file and
the reconcile at slp_csv_processing_complete priority 10 sees 308 changed rows.
rev14 s0.32: the hash covers name_address_address2_city_state_zip_country_
dealer_id and no coordinates, which is why a coordinate-only write cannot fall
off avalon_updated_slp_locations and trigger the nightly delete. That property
must survive this edit untouched.

The anchor is grep-derived from the artefact, per rev16 s7.3 and rev14 s8 - line
1147 in v0.0.15 and v0.0.16 alike, which is exactly why the line number cannot
identify a build and the byte string must.

The else branch at 1153-1161 reads $data['address2'] just as unguardedly. It is
NOT touched: create_location_hash has exactly two call sites, 1173 and 1200,
and both pass array('location' => ...). The else branch is unreachable.

rev16 s0.48: docroot warning suppression does not work, so those ~300 warnings a
night reach the WP Engine error log on the web and cron paths and compete for
the same 1500-row window as the guard's own output.

-----------------------------------------------------------------------------
EDIT 4 - the comment described behaviour the code does not have
-----------------------------------------------------------------------------
It claimed the breaker stops a systemic failure "half way". It does not. The cap
latches and stops further comparison; corrections 1..N are already written into
$location_data row by row and are committed. rev14 s2 repeats the same
overstatement. rev15 s5 and rev16 s0.37 record the correction.

-----------------------------------------------------------------------------
INPUTS   class.slp_avalon.php   998e343bbe324656f8282c238f323441   83,200 bytes
         slp_avalon.php         5ff1a2b8f5a63d693587e994cbe947cb    1,808 bytes

EXPECTED OUTPUT
         class.slp_avalon.php   84,684 bytes, CRLF=1853  (+1,484 bytes, +22 lines)
         slp_avalon.php          1,808 bytes, CRLF=59    (0.0.16 -> 0.0.17)

USAGE    Run from the repo root:

             cd D:\\Temp\\Projects\\GitHub\\slp-plugins
             python build\\build-v017.py

             in   slp_avalon\\inc\\class.slp_avalon.php
             in   slp_avalon\\slp_avalon.php
             out  build\\out17\\

         Optional first argument overrides the repo root, second the output
         directory. build/out*/ has been in .gitignore since v0.0.15.

NOT IN THIS RELEASE
         Removing 'C/O COLE INTERNATIONAL USA|PEMBINA|ND' from
         avalon_tier2_exclusions(). rev14 s3.2 called it stale; rev16 s0.44
         proves it is not. 104782 C/O Cole (Pembina ND) carries 104783
         WATERTOWN's Manitoba coordinates to within 0.3 metres, and the fresh
         feed still shows both rows. excluded:2 and stale_exclusions:[] are both
         correct. Whether to keep excluding it is a decision, not cleanup.
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "class.slp_avalon.php": "998e343bbe324656f8282c238f323441",
    "slp_avalon.php": "5ff1a2b8f5a63d693587e994cbe947cb",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


# ---------------------------------------------------------------------------
# EDIT 1 - rotate avalon_geocode_overrides on the first flush of a run.
# ---------------------------------------------------------------------------
#
# Anchored from the preceding newline, per rev14 s8. The body of
# avalon_flush_import_log() sits at sixteen spaces inside the buffer guard; an
# anchor that started at the first non-space character would match a shorter
# indent elsewhere, replace cleanly, report success, and silently relocate the
# block. All five anchors in this build are newline-anchored and each was
# confirmed to occur exactly once in the v0.0.16 bytes before it was written.

ROTATE_OLD = (
    b"\r\n                $stored = get_option('avalon_geocode_overrides');\r\n"
    b"                if (! is_array($stored)) {\r\n"
    b"                    $stored = array();\r\n"
    b"                }\r\n"
)

ROTATE_NEW = (
    b"\r\n                if ($this->avalon_state('overrides_rotated')) {\r\n"
    b"                    $stored = get_option('avalon_geocode_overrides');\r\n"
    b"                    if (! is_array($stored)) {\r\n"
    b"                        $stored = array();\r\n"
    b"                    }\r\n"
    b"                } else {\r\n"
    b"                    $prev = get_option('avalon_geocode_overrides');\r\n"
    b"                    if (is_array($prev) && ! empty($prev)) {\r\n"
    b"                        update_option('avalon_geocode_overrides_prev', $prev, 'no');\r\n"
    b"                    }\r\n"
    b"                    $stored = array();\r\n"
    b"                    $this->avalon_state_set('overrides_rotated', true);\r\n"
    b"                }\r\n"
)


# ---------------------------------------------------------------------------
# EDIT 1b - say so in the docblock.
# ---------------------------------------------------------------------------
#
# An undocumented rotation inside a method whose docblock says only "flush the
# override log" is how the next reader concludes the option still accumulates.
# Anchored on the @param line, which is unique in the file.

DOC_OLD = (
    b"\r\n         * @param bool $final True at end of import: writes the run summary and\r\n"
)

DOC_NEW = (
    b"\r\n         * The first flush of a run ROTATES the override log: whatever\r\n"
    b"         * avalon_geocode_overrides held from the previous import is moved to\r\n"
    b"         * avalon_geocode_overrides_prev and the current option starts empty.\r\n"
    b"         * Before v0.0.17 it accumulated every import forever - 405 entries and\r\n"
    b"         * 86,712 bytes on Aura DEV after four runs - and every 20-entry flush\r\n"
    b"         * read, unserialised and rewrote the whole history. Two options, one\r\n"
    b"         * import each, bounded permanently. The rotation is keyed on the\r\n"
    b"         * per-import state flag overrides_rotated, so the three or four flushes\r\n"
    b"         * inside a single import rotate exactly once between them.\r\n"
    b"         *\r\n"
    b"         * @param bool $final True at end of import: writes the run summary and\r\n"
)


# ---------------------------------------------------------------------------
# EDIT 2 - correction cap default 25 -> 60.
# ---------------------------------------------------------------------------
#
# Two characters for two, so the column alignment of avalon_import_config()
# survives untouched and the file gains no bytes here.

CAP_OLD = b"\r\n                                     ? (int)   AVALON_TIER2_MAX_CORRECTIONS  : 25,\r\n"
CAP_NEW = b"\r\n                                     ? (int)   AVALON_TIER2_MAX_CORRECTIONS  : 60,\r\n"


# ---------------------------------------------------------------------------
# EDIT 3 - isset() guard on sl_address2.
# ---------------------------------------------------------------------------
#
# isset(...) ? ... : '' rather than ?? ''. The null-coalescing operator appears
# ZERO times in this file; isset( appears 25. House idiom wins over brevity.

ADDR2_OLD = b"\r\n                $address2 = $location['sl_address2'];\r\n"
ADDR2_NEW = (
    b"\r\n                $address2 = isset($location['sl_address2']) "
    b"? $location['sl_address2'] : '';\r\n"
)


# ---------------------------------------------------------------------------
# EDIT 4 - the circuit-breaker comment.
# ---------------------------------------------------------------------------

COMMENT_OLD = (
    b"\r\n            // The correction cap is a circuit breaker, not a quota. Once it\r\n"
    b"            // trips, Tier 2 is done for this import - a systemic failure that\r\n"
    b"            // wants to move 200 rows must not be allowed to move the first 25\r\n"
    b"            // and then stop half way.\r\n"
)

COMMENT_NEW = (
    b"\r\n            // The correction cap is a circuit breaker, not a quota, and it\r\n"
    b"            // LATCHES: once tier2_written reaches the cap, tier2_aborted is\r\n"
    b"            // set and no further Tier 2 row is even compared for the rest of\r\n"
    b"            // this import. It does NOT roll back. Corrections already made\r\n"
    b"            // were written into $location_data row by row and are committed.\r\n"
    b"            // The cap bounds how far a systemic geocode failure can get, not\r\n"
    b"            // whether the pass is all-or-nothing - it never was.\r\n"
)


# ---------------------------------------------------------------------------
# EDIT 5 - version header.
# ---------------------------------------------------------------------------

PHP_OLD = b" * Version: 0.0.16\r\n"
PHP_NEW = b" * Version: 0.0.17\r\n"


# Real repo-relative locations, from the local GitHub inventory:
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\slp_avalon.php
IN_PATHS = {
    "class.slp_avalon.php": Path("slp_avalon") / "inc" / "class.slp_avalon.php",
    "slp_avalon.php": Path("slp_avalon") / "slp_avalon.php",
}

EXPECT_CLS_BYTES = 84684
EXPECT_CLS_CRLF = 1853
EXPECT_PHP_BYTES = 1808
EXPECT_PHP_CRLF = 59


def main() -> None:
    repo = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
    out = Path(sys.argv[2]) if len(sys.argv) > 2 else repo / "build" / "out17"

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

    before = len(cls)

    cls = sub_once(cls, ROTATE_OLD, ROTATE_NEW, "rotate avalon_geocode_overrides")
    print("  edit   ok  1  rotate avalon_geocode_overrides on first flush")

    cls = sub_once(cls, DOC_OLD, DOC_NEW, "flush docblock records the rotation")
    print("  edit   ok  1b docblock records the rotation")

    cls = sub_once(cls, CAP_OLD, CAP_NEW, "correction cap 25 -> 60")
    print("  edit   ok  2  correction cap default 25 -> 60")

    cls = sub_once(cls, ADDR2_OLD, ADDR2_NEW, "isset() guard on sl_address2")
    print("  edit   ok  3  isset() guard on sl_address2")

    cls = sub_once(cls, COMMENT_OLD, COMMENT_NEW, "circuit-breaker comment")
    print("  edit   ok  4  circuit-breaker comment corrected")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  5  version header 0.0.16 -> 0.0.17")

    assert len(cls) - before == 1484, f"expected +1484 bytes, got {len(cls) - before}"

    # --- line endings ----------------------------------------------------
    assert cls.count(b"\r\n") == cls.count(b"\n"), "stray bare LF in class file"
    assert php.count(b"\r\n") == php.count(b"\n"), "stray bare LF in plugin file"
    assert not cls.endswith(b"\n"), "trailing newline introduced in class file"

    # --- EDIT 2, both numbers -------------------------------------------
    # rev16 s0.52. Asserting only that 60 arrived would pass on a file that
    # still had 25 somewhere else; asserting only that 25 left would pass on a
    # file where the whole config block had been deleted.
    assert cls.count(b"AVALON_TIER2_MAX_CORRECTIONS  : 60,") == 1, \
        "the cap default of 60 is not present exactly once"
    assert cls.count(b": 25,") == 0, "a `: 25,` default survives somewhere"
    assert cls.count(b"first 25") == 0, \
        "the old comment's 'first 25' survives - edit 4 did not land"
    # Twice, not once: the guard clause and the cast. Measured, not assumed -
    # the first draft of this line said 1 and the assertion caught it.
    assert cls.count(b"AVALON_TIER2_MAX_CORRECTIONS") == 2, \
        "the wp-config override is no longer read as defined()+cast"
    assert cls.count(b"defined('AVALON_TIER2_MAX_CORRECTIONS')") == 1, \
        "the wp-config override guard disturbed"

    # --- EDIT 1, the rotation ---------------------------------------------
    # Twice for the option name: once in the docblock, once in the code. Three
    # for the state key: read, write, docblock. Measured after the edits, not
    # estimated - the first draft said 1 for each and both assertions caught it.
    assert cls.count(b"avalon_geocode_overrides_prev") == 2, \
        "the previous-run option is named in the code and the docblock"
    assert cls.count(b"update_option('avalon_geocode_overrides_prev', $prev, 'no');") == 1, \
        "the previous-run option must be written exactly once, autoload off"
    assert cls.count(b"overrides_rotated") == 3, \
        "the rotation state key: read, write, docblock"
    assert cls.count(b"$this->avalon_state_set('overrides_rotated', true);") == 1
    assert cls.count(b"$this->avalon_state('overrides_rotated')") == 1
    assert cls.count(b"update_option('avalon_geocode_overrides', $stored, 'no');") == 1, \
        "the current-run write disturbed"
    # The per-run ceiling stays. It is no longer a history bound.
    assert cls.count(b"if (count($stored) > 500) {") == 1, \
        "the 500-entry per-run ceiling was removed"
    # The state helpers the rotation leans on.
    assert cls.count(b"private function avalon_state($key){") == 1
    assert cls.count(b"private function avalon_state_set($key, $value){") == 1

    # --- EDIT 3, hash neutrality ------------------------------------------
    assert cls.count(
        b"$address2 = isset($location['sl_address2']) ? $location['sl_address2'] : '';"
    ) == 1, "the guarded read is not present exactly once"
    assert cls.count(b"$address2 = $location['sl_address2'];") == 0, \
        "the unguarded read survives"
    # The interpolation the hash depends on must not have moved. If this string
    # changes, every one of the 308 location hashes changes with it.
    assert cls.count(
        b'$string = "{$name}_{$address}_{$address2}_{$city}_{$state}_{$zip}'
        b'_{$country}_{$dealer_id}";'
    ) == 1, "the hash input string moved - all 308 hashes would change"
    # The unreachable else branch is deliberately untouched.
    assert cls.count(b"$address2 = $data['address2'];") == 1, \
        "the dead else branch was edited; it has no callers and is not in scope"

    # --- EDIT 4, the comment ----------------------------------------------
    assert cls.count(b"// The correction cap is a circuit breaker, not a quota, and it") == 1
    assert cls.count(b"// this import. It does NOT roll back. Corrections already made") == 1
    assert cls.count(b"and then stop half way.") == 0, \
        "the overstatement rev15 s5 and rev16 s0.37 corrected survives"

    # --- v0.0.16 must not have been undone --------------------------------
    # This build reopens the file that carried the two-byte hook fix. The whole
    # reason v0.0.17 exists as a release rather than a patch is that a wiring
    # defect here is invisible to a callback test - rev16 s0.38.
    assert cls.count(b"'avalon_flush_import_log'),500,0);") == 1, \
        "v0.0.16's accepted_args=0 was lost"
    assert cls.count(b"'avalon_flush_import_log'),500);") == 0, \
        "the three-argument registration came back"
    assert cls.count(b"public function avalon_flush_import_log($final = true){") == 1
    assert cls.count(b"if (! $final) {") == 1
    assert cls.count(b"update_option('avalon_geocode_last_run', $summary, 'no');") == 1

    # --- registrations are still at twelve spaces -------------------------
    for line in cls.split(b"\r\n"):
        if b"avalon_flush_import_log'),500,0);" in line \
           or b"avalon_import_coordinate_guard'),20,1);" in line:
            assert line.startswith(b"            add_"), \
                f"registration at wrong indent: {line!r}"

    # --- nothing else in add_actions() moved -----------------------------
    assert cls.count(
        b"\r\n            add_filter('slp_csv_locationdata',"
        b"array(self::$instance,'avalon_import_coordinate_guard'),20,1);\r\n"
    ) == 1, "coordinate guard registration disturbed"
    assert cls.count(
        b"            add_action('slp_csv_processing_complete', "
        b"array(self::$instance,'remove_old_csv_files_after_import'), 999);\r\n"
    ) == 1, "the 999 cleanup registration disturbed"
    assert cls.count(b"add_filter('slp_ajax_find_locations_complete',"
                     b"array(self::$instance,'territory_gate'),20,1);") == 1, \
        "the Layer 3 territory gate registration disturbed"

    # --- v0.0.15 machinery intact ----------------------------------------
    for fn in (
        b"public function avalon_import_coordinate_guard($location_data){",
        b"public function avalon_import_config(){",
        b"private function avalon_geocode_cached($address){",
        b"private function avalon_import_log($record){",
        b"public function avalon_tier2_exclusions(){",
        b"public function create_location_hash($data)",
    ):
        assert cls.count(fn) == 1, f"one definition expected: {fn.decode()}"

    assert cls.count(b"'ABORTED_correction_cap'") == 1, "correction cap"
    assert cls.count(b"'error' => 'geocode budget exhausted'") == 1, "geocode budget"
    assert cls.count(b"'DONNIE MARCH|HOWELL|MI',") == 1, "DONNIE MARCH excluded"
    # rev16 s0.44 - NOT stale, NOT removed this release.
    assert cls.count(b"'C/O COLE INTERNATIONAL USA|PEMBINA|ND',") == 1, "Cole still excluded"
    assert cls.count(b"update_option('avalon_geocode_cache', $cache, 'no');") == 1
    assert cls.count(b"public function territory_boxes(){") == 1
    assert cls.count(b"public function is_in_territory( $lat, $lng ){") == 1
    assert cls.count(b"public function vincentyGreatCircleDistance(") == 1

    assert php.count(b"0.0.17") == 1
    assert php.count(b"0.0.16") == 0

    # --- byte-exact expectations ------------------------------------------
    # f-strings could not contain a backslash before Python 3.12, so the counts
    # are taken into locals first rather than escaped inside the message.
    cls_crlf = cls.count(b"\r\n")
    php_crlf = php.count(b"\r\n")

    assert len(cls) == EXPECT_CLS_BYTES, \
        f"class file {len(cls)} bytes, expected {EXPECT_CLS_BYTES}"
    assert cls_crlf == EXPECT_CLS_CRLF, \
        f"class file CRLF={cls_crlf}, expected {EXPECT_CLS_CRLF}"
    assert len(php) == EXPECT_PHP_BYTES, \
        f"plugin file {len(php)} bytes, expected {EXPECT_PHP_BYTES}"
    assert php_crlf == EXPECT_PHP_CRLF, \
        f"plugin file CRLF={php_crlf}, expected {EXPECT_PHP_CRLF}"

    print("  self-check ok")

    (out / "class.slp_avalon.php").write_bytes(cls)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("class.slp_avalon.php", cls), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:22} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
