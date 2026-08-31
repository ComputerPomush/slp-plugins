#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.10 from the shipped v0.0.9 artefacts.

Layer 0. Synchronous pre-flight validation in cslmap_searchLocations(), the
single choke point every search reaches: the Find Locations button via the
inline onsubmit, an autocomplete selection, the URL bootstrap in
cslmap_build_map(), and Get My Position all land there.

Two checks, in order:

  (a) Syntactic floor. Reject an address that contains no letter and no digit.
      Deliberately minimal. An address-SHAPE validator was considered and
      rejected: it would have to accept 48127, M5H 2N2, Sault Ste. Marie,
      St. John's NL, Trois-Rivieres and 1200 Woodward Ave #4, and every false
      rejection is a customer who cannot find a dealer. Junk that geocodes to
      nothing already behaves correctly - the proxy returns ZERO_RESULTS and
      the Guard shows "We couldn't find that location" - so this floor only
      catches input that could never resolve, like "!!!" or "###".

  (b) Decision 16. Bounding-box check on coordinates, which is the real work.
      This is the first caller avalon_in_territory() has ever had.

Why (b) matters, and why it belongs HERE rather than in Layer 1. On the
URL-coordinate path there is no country component, so avalon_country_of()
returns null and Layer 1 correctly no-ops (decision 18). Control then passes
to SLP, which at slp_core.js:1565-1566 sets homePoint to the rejected location
and calls addMarkerAtCenter() BEFORE any AJAX is issued. The home marker is
cslmap.centerMarker, not a member of cslmap.markers, and the cleanup at
slp_core.js:1297 only runs when #addressInput is empty - so clearMarkers()
does not remove it. putMarkers() then pans to homePoint at 1323. Net effect,
reproduced on Aura DEV with ?place_lat=48.86&place_lng=2.35: the correct
territory message above the field, an empty results panel, and a map centred
on Paris with a pin on it. Issue 15.

Rejecting before the coords branch delegates means process_geocode_response
never runs, so none of that chain fires. It also saves a pointless round trip.

Fills the VALIDATING state, declared since v0.0.5 and unreachable until now.

The v0.0.6 removal of the jQuery #searchForm submit handler is what makes a
synchronous rejection safe here. Re-verified against v0.0.9: sl_show_loading
has exactly one caller, inside show_spinner(), and nothing is bound to
#searchForm. Do not reintroduce either.

Usage:  python3 build-v010.py <src_dir> <out_dir>
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "slp_avalon.js": "a42b762902ebaa01ae242483d0aa8d1e",
    "slp_avalon.php": "567dbaa2fc9d9d5d5ac7fe226431a7ee",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


# --------------------------------------------------------------- edit 1
A1_OLD = (
    b"    territory:\r\n"
    b'      "We only have dealers in the United States and Canada. Please search " +\r\n'
    b'      "a U.S. or Canadian city, state, or ZIP.",\r\n'
    b"  };\r\n"
)
A1_NEW = (
    b"    territory:\r\n"
    b'      "We only have dealers in the United States and Canada. Please search " +\r\n'
    b'      "a U.S. or Canadian city, state, or ZIP.",\r\n'
    b"    //Layer 0 only. Deliberately not the territory copy: nothing about\r\n"
    b"    //\"!!!\" is out of territory, and telling the visitor it is would send\r\n"
    b"    //them looking for a problem that is not there.\r\n"
    b"    invalid_input: \"Please enter a city, state, ZIP or postal code.\",\r\n"
    b"  };\r\n"
)

# --------------------------------------------------------------- edit 2
A2_OLD = b'  var AVALON_ALLOWED_COUNTRIES = ["US", "PR", "VI", "GU", "MP", "AS", "CA"];\r\n'
A2_NEW = (
    b'  var AVALON_ALLOWED_COUNTRIES = ["US", "PR", "VI", "GU", "MP", "AS", "CA"];\r\n'
    b"\r\n"
    b"  /**\r\n"
    b"   * Layer 0's syntactic floor: does this string contain anything that\r\n"
    b"   * could possibly be part of a place name, a street number, a ZIP or a\r\n"
    b"   * postal code?\r\n"
    b"   *\r\n"
    b"   * The Latin-1 Supplement and Latin Extended-A ranges are included so\r\n"
    b"   * that accented input cannot be rejected. No realistic Quebec place\r\n"
    b"   * name is composed entirely of accented characters, but the cost of\r\n"
    b"   * covering it is one range and the cost of getting it wrong is a\r\n"
    b"   * customer who cannot search for where they live.\r\n"
    b"   *\r\n"
    b"   * This is the whole test, on purpose. See build-v010.py for why an\r\n"
    b"   * address-shape validator was rejected.\r\n"
    b"   */\r\n"
    b"  var AVALON_SEARCHABLE = /[A-Za-z0-9\\u00C0-\\u024F]/;\r\n"
)

# --------------------------------------------------------------- edit 3
# Keep the raw field value separate: append_to_search is a settings-driven
# suffix and would smuggle letters into a string the floor is meant to judge.
A3_OLD = b'    let address = avalon_cslmap.saneValue("addressInput", "") + append_this;\r\n'
A3_NEW = (
    b"    //Held separately from `address` because append_to_search is a\r\n"
    b"    //settings-driven suffix: if it is ever set, it would smuggle letters\r\n"
    b"    //into the string Layer 0's floor is supposed to judge, and the floor\r\n"
    b"    //would silently stop working. saneValue() already trims.\r\n"
    b'    let raw_address = avalon_cslmap.saneValue("addressInput", "");\r\n'
    b"    let address = raw_address + append_this;\r\n"
)

# --------------------------------------------------------------- edit 4
A4_OLD = b"    }\r\n  \r\n    avalon_cslmap.unhide_map();\r\n"
A4_NEW = (
    b"    }\r\n"
    b"  \r\n"
    b"    /* ---------------------------------------------------------- Layer 0\r\n"
    b"     * Synchronous pre-flight. Runs before unhide_map(), before the URL is\r\n"
    b"     * composed and before either branch below, so a rejection touches\r\n"
    b"     * nothing: no geocode, no AJAX, no map movement, no marker.\r\n"
    b"     *\r\n"
    b"     * Safe to reject synchronously only because #searchForm has no\r\n"
    b"     * jQuery-bound submit handler. It carries an inline onsubmit attribute\r\n"
    b"     * registered at parse time, so any jQuery handler would run AFTER this\r\n"
    b"     * function returns and would switch the spinner back on with nothing\r\n"
    b"     * left to switch it off. The one that used to exist was removed in\r\n"
    b"     * v0.0.6; see the note in avalon_init_gmaps(). Do not reintroduce it.\r\n"
    b"     * ------------------------------------------------------------------ */\r\n"
    b"    avalon_guard.enter(avalon_guard.VALIDATING);\r\n"
    b"\r\n"
    b"    //(a) Syntactic floor. Only meaningful when the visitor actually typed\r\n"
    b"    //something: an empty field with no coordinates is a legitimate search\r\n"
    b"    //from the map centre and is handled in the else branch below.\r\n"
    b"    if (!coords && raw_address && !AVALON_SEARCHABLE.test(raw_address)) {\r\n"
    b"      avalon_guard.finish(avalon_guard.REJECTED, {\r\n"
    b"        message: AVALON_GUARD_MESSAGES.invalid_input,\r\n"
    b"        focus_input: avalon_guard.user_initiated,\r\n"
    b"      });\r\n"
    b"      return;\r\n"
    b"    }\r\n"
    b"\r\n"
    b"    //(b) Decision 16, and the reason Layer 0 exists. Coordinates arrive\r\n"
    b"    //here from three places: an autocomplete selection, the URL bootstrap\r\n"
    b"    //in cslmap_build_map(), and Get My Position. Only the first carries a\r\n"
    b"    //country, so Layer 1 no-ops on the other two and SLP would otherwise\r\n"
    b"    //pin and pan the map to a location we are about to reject. Issue 15.\r\n"
    b"    //\r\n"
    b"    //The boxes are coarse and admit Tijuana, Nassau and Road Town. That is\r\n"
    b"    //deliberate and unchanged: those carry a country, so Layer 1 catches\r\n"
    b"    //them precisely. This check only has to agree with Layer 3, which uses\r\n"
    b"    //the same eight boxes, so it rejects nothing the server would accept.\r\n"
    b"    //\r\n"
    b"    //avalon_in_territory() parseFloats, so the strings that\r\n"
    b"    //URLSearchParams hands back need no conversion here.\r\n"
    b"    if (coords && !avalon_in_territory(coords.lat, coords.lng)) {\r\n"
    b"      avalon_guard.finish(avalon_guard.REJECTED, {\r\n"
    b"        message: AVALON_GUARD_MESSAGES.territory,\r\n"
    b"        focus_input: avalon_guard.user_initiated,\r\n"
    b"      });\r\n"
    b"      return;\r\n"
    b"    }\r\n"
    b"\r\n"
    b"    //Past the gate. Everything from here is the pre-existing flow.\r\n"
    b"    avalon_guard.enter(avalon_guard.RESOLVING);\r\n"
    b"  \r\n"
    b"    avalon_cslmap.unhide_map();\r\n"
)

EDITS = [
    ("invalid_input message", A1_OLD, A1_NEW),
    ("AVALON_SEARCHABLE",     A2_OLD, A2_NEW),
    ("raw_address",           A3_OLD, A3_NEW),
    ("Layer 0 gate",          A4_OLD, A4_NEW),
]

PHP_OLD = b" * Version: 0.0.9\r\n"
PHP_NEW = b" * Version: 0.0.10\r\n"


def main() -> None:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "out10")
    out.mkdir(parents=True, exist_ok=True)

    js = (src / "slp_avalon.js").read_bytes()
    php = (src / "slp_avalon.php").read_bytes()

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        got = md5(blob)
        if got != SRC_MD5[name]:
            raise SystemExit(f"INPUT MD5 FAIL {name}: {got} != {SRC_MD5[name]}")
        print(f"  input  ok  {name:16} {got}")

    for label, old, new in EDITS:
        js = sub_once(js, old, new, label)
        print(f"  edit   ok  {label}")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  version header 0.0.9 -> 0.0.10")

    assert js.count(b"\r\n") == js.count(b"\n"), "stray bare LF introduced"
    assert not js.endswith(b"\n"), "trailing newline introduced"
    # Match exact CODE forms, never bare identifiers: the surrounding comments
    # name these symbols on purpose, and a substring count catches the prose.
    # This assertion has now been wrong three builds running for that reason.
    assert js.count(b"function avalon_in_territory(") == 1, "helper definition"
    assert js.count(b"!avalon_in_territory(coords.lat, coords.lng)") == 1, \
        "exactly one Layer 0 caller - the first this helper has ever had"
    assert js.count(b"var AVALON_SEARCHABLE = ") == 1, "floor definition"
    assert js.count(b"!AVALON_SEARCHABLE.test(raw_address)") == 1, "floor applied once"
    assert js.count(b"avalon_guard.enter(avalon_guard.VALIDATING);") == 1, "enters VALIDATING"
    assert js.count(b"avalon_guard.enter(avalon_guard.RESOLVING);") == 1, "exits to RESOLVING"
    assert js.count(b'let raw_address = avalon_cslmap.saneValue') == 1, "raw value captured"
    assert js.count(b"let address = raw_address + append_this;") == 1, "address still appends"
    assert js.count(b"AVALON_GUARD_MESSAGES.invalid_input") == 1, "new message used once"
    assert js.count(b"    invalid_input: ") == 1, "new message declared once"
    assert php.count(b"0.0.10") == 1
    print("  self-check ok")

    (out / "slp_avalon.js").write_bytes(js)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:16} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
