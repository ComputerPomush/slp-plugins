#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.16 from the shipped v0.0.15 artefacts.

ONE DEFECT, TWO BYTES.

v0.0.15 registered the import-log flush like this:

    add_action('slp_csv_processing_complete',
               array(self::$instance,'avalon_flush_import_log'),500);

add_action() called with three arguments leaves $accepted_args at its default
of 1. WordPress's do_action() substitutes an empty string when the caller
supplies no argument:

    function do_action( $hook_name, ...$arg ) {
        ...
        if ( empty( $arg ) ) {
            $arg[] = '';
        }

and WP_Hook::apply_filters() then dispatches with `1 >= 1`, so the callback
receives ''. The callback is

    public function avalon_flush_import_log($final = true){

so $final is bound to '' rather than defaulting to true. SLP Power fires the
hook bare at SLP_Power_Locations_Import.php:773. The flushes at the top of the
method still ran - avalon_geocode_overrides and avalon_geocode_cache were both
written - and then `if (! $final) { return; }` took the early exit, so
avalon_geocode_last_run was never written and avalon_import_state was never
reset.

A defaulted parameter only takes its default when ZERO arguments arrive.
add_action() with an unspecified $accepted_args guarantees exactly one does.

Proven on Aura DEV, 2026-09-03:

    wp eval 'global $wp_filter; ...'
      500  SLP_Avalon::avalon_flush_import_log   accepted_args=1

    function avalon_probe($final = true){ echo var_export($final, true); }
    add_action("probe_a", "avalon_probe", 500);     do_action("probe_a");  -> ''
    add_action("probe_b", "avalon_probe", 500, 0);  do_action("probe_b");  -> true

Corroborated arithmetically: avalon_geocode_overrides held 185 entries after
the first import. avalon_import_log() flushes only at count($buf) >= 20, so a
mid-import flush can leave only a multiple of 20. 185 is not one. The
completion callback ran, wrote the buffer, and returned before the summary.

DO NOT "fix" this by loosening the guard to `if ($final === false)`. The
signature would still be lying about its contract and the next hook this
method is attached to would pass null or 0.

INPUTS   class.slp_avalon.php   c5ff85e089366ded99b7b7d8b083f537   83,198 bytes
         slp_avalon.php         6df1d53be2c28e3ca0e43b5f1bf31e7a    1,808 bytes
slp_avalon.js is NOT touched by this release.

EXPECTED OUTPUT
         class.slp_avalon.php   83,200 bytes, CRLF=1831   (+2 bytes, +0 lines)
         slp_avalon.php          1,808 bytes, CRLF=59     (0.0.15 -> 0.0.16)

USAGE    Run from the repo root:

             cd D:\\Temp\\Projects\\GitHub\\slp-plugins
             python build\\build-v016.py

             in   slp_avalon\\inc\\class.slp_avalon.php
             in   slp_avalon\\slp_avalon.php
             out  build\\out16\\

         Optional first argument overrides the repo root, second the output
         directory. build/out*/ is already in .gitignore as of v0.0.15.

NOT IN THIS RELEASE
         create_location_hash() reads $location['sl_address2'] unguarded
         (rev14 s0.33). It warns on every row of every import - roughly 300
         lines a night into the WP Engine error log on the web/cron path. It
         is a one-line isset() guard, but its anchor must be grep-derived from
         the artefact rather than estimated (rev14 s8), and that has not been
         done. It goes in v0.0.17 with a measured anchor, not here on a guess.
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "class.slp_avalon.php": "c5ff85e089366ded99b7b7d8b083f537",
    "slp_avalon.php": "6df1d53be2c28e3ca0e43b5f1bf31e7a",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


# ---------------------------------------------------------------------------
# EDIT 1 - accepted_args = 0 on the completion hook.
# ---------------------------------------------------------------------------
#
# Anchored from the preceding newline, per rev14 s8. The body of add_actions()
# is indented twelve spaces; an eight-space anchor matches the last eight of
# the twelve, replaces cleanly, reports success, and silently relocates the
# line.
#
# This anchor is additionally unique on its own because v0.0.15 wrote the
# registration with no space after the first comma, unlike lines 56 and 62
# which read "'slp_csv_processing_complete', array(". Do not normalise that
# spacing here - it is what keeps the byte string unambiguous.

HOOK_OLD = (
    b"\r\n            add_action('slp_csv_processing_complete',"
    b"array(self::$instance,'avalon_flush_import_log'),500);\r\n"
)

HOOK_NEW = (
    b"\r\n            add_action('slp_csv_processing_complete',"
    b"array(self::$instance,'avalon_flush_import_log'),500,0);\r\n"
)


# ---------------------------------------------------------------------------
# EDIT 2 - version header.
# ---------------------------------------------------------------------------

PHP_OLD = b" * Version: 0.0.15\r\n"
PHP_NEW = b" * Version: 0.0.16\r\n"


# Real repo-relative locations, from the local GitHub inventory:
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\slp_avalon.php
IN_PATHS = {
    "class.slp_avalon.php": Path("slp_avalon") / "inc" / "class.slp_avalon.php",
    "slp_avalon.php": Path("slp_avalon") / "slp_avalon.php",
}

EXPECT_CLS_BYTES = 83200
EXPECT_CLS_CRLF = 1831
EXPECT_PHP_BYTES = 1808
EXPECT_PHP_CRLF = 59


def main() -> None:
    repo = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
    out = Path(sys.argv[2]) if len(sys.argv) > 2 else repo / "build" / "out16"

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

    cls = sub_once(cls, HOOK_OLD, HOOK_NEW, "accepted_args=0 on completion hook")
    print("  edit   ok  accepted_args=0 on completion hook")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  version header 0.0.15 -> 0.0.16")

    # --- exactly two bytes, no line added --------------------------------
    assert len(cls) - before == 2, f"expected +2 bytes, got {len(cls) - before}"

    # --- line endings ----------------------------------------------------
    assert cls.count(b"\r\n") == cls.count(b"\n"), "stray bare LF in class file"
    assert php.count(b"\r\n") == php.count(b"\n"), "stray bare LF in plugin file"
    assert not cls.endswith(b"\n"), "trailing newline introduced in class file"

    # --- the defect is gone, in both directions --------------------------
    assert cls.count(b"'avalon_flush_import_log'),500,0);") == 1, \
        "accepted_args=0 not present exactly once"
    assert cls.count(b"'avalon_flush_import_log'),500);") == 0, \
        "the three-argument registration survives"

    # --- registration is still at twelve spaces --------------------------
    # rev14 s8: the anchor cannot silently relocate the line, but assert it
    # anyway - this is the assertion that would have caught the original
    # eight-space mismatch.
    for line in cls.split(b"\r\n"):
        if b"avalon_flush_import_log'),500,0);" in line \
           or b"avalon_import_coordinate_guard'),20,1);" in line:
            assert line.startswith(b"            add_"), \
                f"registration at wrong indent: {line!r}"

    # --- the callback signature is unchanged -----------------------------
    # The whole point of accepted_args=0 is that this default becomes
    # reachable. If the signature ever loses its default, the fix is inert.
    assert cls.count(b"public function avalon_flush_import_log($final = true){") == 1, \
        "the defaulted signature this release exists to reach"
    assert cls.count(b"if (! $final) {") == 1, "the early-return guard"
    assert cls.count(b"update_option('avalon_geocode_last_run', $summary, 'no');") == 1, \
        "the write that never happened under v0.0.15"

    # --- nothing else in add_actions() moved -----------------------------
    assert cls.count(
        b"\r\n            add_filter('slp_csv_locationdata',"
        b"array(self::$instance,'avalon_import_coordinate_guard'),20,1);\r\n"
    ) == 1, "coordinate guard registration disturbed"
    assert cls.count(
        b"            add_action('slp_csv_processing_complete', "
        b"array(self::$instance,'remove_old_csv_files_after_import'), 999);\r\n"
    ) == 1, "the 999 cleanup registration disturbed"
    assert cls.count(
        b"            add_filter('slp_ajaxsql_queryparams',"
        b"array(self::$instance,'slp_ajaxsql_queryparams'),999,2);\r\n"
    ) == 1, "the queryparams registration disturbed"
    assert cls.count(b"add_filter('slp_ajax_find_locations_complete',"
                     b"array(self::$instance,'territory_gate'),20,1);") == 1, \
        "the Layer 3 territory gate registration disturbed"

    # --- v0.0.15 machinery intact ----------------------------------------
    for fn in (
        b"public function avalon_import_coordinate_guard($location_data){",
        b"private function avalon_geocode_cached($address){",
        b"private function avalon_state($key){",
        b"private function avalon_import_log($record){",
        b"public function avalon_tier2_exclusions(){",
    ):
        assert cls.count(fn) == 1, f"one definition expected: {fn.decode()}"

    assert cls.count(b"'ABORTED_correction_cap'") == 1, "correction cap"
    assert cls.count(b"'error' => 'geocode budget exhausted'") == 1, "geocode budget"
    assert cls.count(b"'DONNIE MARCH|HOWELL|MI',") == 1, "DONNIE MARCH excluded"
    assert cls.count(b"'C/O COLE INTERNATIONAL USA|PEMBINA|ND',") == 1, "Cole excluded"
    assert cls.count(b"update_option('avalon_geocode_overrides', $stored, 'no');") == 1
    assert cls.count(b"update_option('avalon_geocode_cache', $cache, 'no');") == 1
    assert cls.count(b"public function territory_boxes(){") == 1
    assert cls.count(b"public function is_in_territory( $lat, $lng ){") == 1
    assert cls.count(b"public function vincentyGreatCircleDistance(") == 1

    assert php.count(b"0.0.16") == 1
    assert php.count(b"0.0.15") == 0

    # --- byte-exact expectations ------------------------------------------
    assert len(cls) == EXPECT_CLS_BYTES, \
        f"class file {len(cls)} bytes, expected {EXPECT_CLS_BYTES}"
    assert cls.count(b"\r\n") == EXPECT_CLS_CRLF, \
        f"class file CRLF={cls.count(chr(13).encode() + chr(10).encode())}, " \
        f"expected {EXPECT_CLS_CRLF}"
    assert len(php) == EXPECT_PHP_BYTES, \
        f"plugin file {len(php)} bytes, expected {EXPECT_PHP_BYTES}"
    assert php.count(b"\r\n") == EXPECT_PHP_CRLF, \
        f"plugin file CRLF mismatch, expected {EXPECT_PHP_CRLF}"

    print("  self-check ok")

    (out / "class.slp_avalon.php").write_bytes(cls)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("class.slp_avalon.php", cls), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:22} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
