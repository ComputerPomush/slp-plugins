#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.14 from the shipped v0.0.13 artefacts.

Issue 25, the truncated geocode. Handoff rev 10 s10.12, promoted from
"cosmetic" once the failure mode was actually traced end to end.

The defect. slp_core.js:806 builds the geocode proxy URL with

    encodeURI(slplus.rest_url + "geocode/" + apikey + "/" + region + "/" + address)

encodeURI() deliberately leaves the URL-reserved set unescaped, and that set
includes "#" and "?". Both are structural: "#" opens a fragment, which the
browser strips before the request is sent, and "?" opens a query string, which
ends the path segment the proxy reads the address from. So a customer typing a
suite number the ordinary way gets silently truncated:

    typed   1200 Woodward Ave #4, Detroit, MI
    built   .../geocode/KEY/us/1200%20Woodward%20Ave%20#4,%20Detroit,%20MI
    sent    .../geocode/KEY/us/1200%20Woodward%20Ave%20
    lost    #4, Detroit, MI

Google receives a street address with no suite, no city and no state. The
visitor either lands on the wrong Woodward Avenue or is told we could not find
their address - for an address that is entirely valid. It looks like our search
is broken, which is why this stopped being cosmetic.

Reachability, checked rather than assumed. Layer 0's floor is
/[A-Za-z0-9\\u00C0-\\u024F]/, so "1200 Woodward Ave #4" passes it - suite-v010
asserts exactly that string is accepted. A bare "#" or "?" is rejected by the
floor for having no letter or digit, so avalon_geocode_safe() can never be
handed a string that cleans down to nothing.

Where the fix goes. store-locator-le/ is the hard constraint and is never
edited, so slp_core.js:806 is out of reach. SLP publishes its own extension
point immediately before geocoding:

    slp_Filter("geocoder_request").publish(_this.geocoder_request);   // 1647
    _this.geocoder.geocode(_this.geocoder_request, ...);              // 1649

A subscriber that mutates request.address is therefore the last thing to touch
the string before it goes out, and it is SLP's documented mechanism rather than
another monkey-patch. Our fork already carried a commented-out example of this
exact subscription at slp_avalon.js 1193-1200 - dead ZIP-handling code aimed at
a Romanian component filter - which this build replaces with the real thing.

The alternative was wrapping slp.geocoder.geocode the way install_transport_hook
wraps slp.send_ajax. Rejected: more machinery, one more global to keep
idempotent, and no benefit over the published filter.

Two properties the implementation is deliberately strict about.

(a) It is a no-op on clean input. The early return mirrors clean_url()'s in
    v0.0.13 and exists for the same reason - the overwhelming majority of
    searches contain neither character, and a function that rewrote every
    address would be far harder to reason about when a geocode goes wrong.

(b) It changes only the string sent to Google. #addressInput keeps what the
    visitor typed, and the place_address parameter composed at
    cslmap_searchLocations() is untouched, so a successful search still shares
    as the address the visitor actually entered. Constraint C1 is unaffected -
    no URL parameter is added, removed or reordered by this build.

Characters are replaced with a space rather than deleted. "#4" becoming "4"
keeps the token; "#4" becoming "" would silently drop information Google may
use for the interpolated result. Google geocodes to the building and ignores a
suite number either way, so neither is lossy in practice - the space is simply
the smaller change.

JS-only. class.slp_avalon.php is not an input here and must not move.

Usage:  python3 build-v014.py <src_dir> <out_dir>
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "slp_avalon.js": "de3216467ac8e95a84448846e2ce7032",
    "slp_avalon.php": "2abf1b6145d8206bdda977b9eaa765d1",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


def strip_js_comments(blob: bytes) -> bytes:
    """
    Return the file with // and block comments removed, literals kept.

    Carried verbatim from build-v013.py. Decision 33: presence assertions run
    against real bytes, absence assertions against this view, because the
    replacement comments quote the retired code on purpose and a raw-byte
    count picks up the prose. v0.0.9 through v0.0.12 each tripped their own
    self-check that way.

    Used for assertions ONLY. The artefact is written from untouched bytes, so
    a defect in this parser can fail a build but can never ship one.
    """
    out = bytearray()
    i, n, state = 0, len(blob), "code"
    while i < n:
        c, two = blob[i:i + 1], blob[i:i + 2]
        if state == "code":
            if two == b"//":
                state = "line"
            elif two == b"/*":
                state = "block"
                i += 2
                continue
            elif c in (b"'", b'"', b"`"):
                state = {b"'": "sq", b'"': "dq", b"`": "tq"}[c]
                out += c
            else:
                out += c
            i += 1
            continue
        if state == "line":
            if c == b"\n":
                state = "code"
                out += c
            i += 1
            continue
        if state == "block":
            if two == b"*/":
                state = "code"
                i += 2
                continue
            i += 1
            continue
        if c == b"\\":
            out += blob[i:i + 2]
            i += 2
            continue
        out += c
        if ((state == "sq" and c == b"'")
                or (state == "dq" and c == b'"')
                or (state == "tq" and c == b"`")):
            state = "code"
        i += 1
    return bytes(out)


# --------------------------------------------------------------- edit 1
# The helper lands beside AVALON_SEARCHABLE on purpose: both judge the raw
# address string before anything downstream sees it, and a reader looking for
# "what do we do to what the visitor typed" should find them together.
A1_OLD = (
    b"  var AVALON_SEARCHABLE = /[A-Za-z0-9\\u00C0-\\u024F]/;\r\n"
    b"\r\n"
)

A1_NEW = (
    b"  var AVALON_SEARCHABLE = /[A-Za-z0-9\\u00C0-\\u024F]/;\r\n"
    b"\r\n"
    b"  /**\r\n"
    b"   * Issue 25. Remove the two characters that would truncate the geocode\r\n"
    b"   * proxy URL before it leaves the browser.\r\n"
    b"   *\r\n"
    b"   * slp_core.js:806 builds that URL with encodeURI(), which does not\r\n"
    b'   * escape "#" or "?" - both are in the reserved set it leaves alone on\r\n'
    b"   * purpose. Both are structural in a URL: \"#\" opens a fragment, which\r\n"
    b'   * the browser never sends, and "?" opens a query string, which ends the\r\n'
    b"   * path segment the proxy reads the address out of. So\r\n"
    b'   * "1200 Woodward Ave #4, Detroit, MI" reaches Google as\r\n'
    b'   * "1200 Woodward Ave " - no suite, no city, no state.\r\n'
    b"   *\r\n"
    b"   * Replaced with a space rather than deleted, so \"#4\" becomes \"4\"\r\n"
    b"   * instead of vanishing. Google geocodes to the building and ignores a\r\n"
    b"   * suite number either way; the space is simply the smaller change.\r\n"
    b"   *\r\n"
    b"   * The early return is not tidiness. Almost every search contains\r\n"
    b"   * neither character, and a function that rewrote every address would be\r\n"
    b"   * much harder to reason about the next time a geocode returns something\r\n"
    b"   * unexpected. Same shape as clean_url()'s no-op test, same reason.\r\n"
    b"   *\r\n"
    b"   * Cannot be handed a string that cleans down to nothing: a bare \"#\" or\r\n"
    b"   * \"?\" has no letter or digit, so AVALON_SEARCHABLE rejects it at Layer\r\n"
    b"   * 0 and it never reaches a geocode.\r\n"
    b"   */\r\n"
    b"  function avalon_geocode_safe(address) {\r\n"
    b'    if (typeof address !== "string") return address;\r\n'
    b'    if (address.indexOf("#") === -1 && address.indexOf("?") === -1) {\r\n'
    b"      return address;\r\n"
    b"    }\r\n"
    b"    return address\r\n"
    b'      .replace(/[#?]/g, " ")\r\n'
    b'      .replace(/\\s{2,}/g, " ")\r\n'
    b"      .trim();\r\n"
    b"  }\r\n"
    b"\r\n"
)

# --------------------------------------------------------------- edit 2
# Replaces the dead ZIP/Romania example that has sat commented out here since
# before Phase 0. It subscribed to the very filter this fix needs, so leaving
# it beside the real subscriber would read as two competing attempts.
A2_OLD = (
    b"  function avalon_init_gmaps() {\r\n"
    b"    //Search by ZIP if search text is only numbers\r\n"
    b'    // slp_Filter("geocoder_request").subscribe(function (request) {\r\n'
    b"    //   console.log({ request });\r\n"
    b'    //   if (request["address"].isNumber()) {\r\n'
    b'    //     let zip = request["address"];\r\n'
    b'    //     request["address"] = `${zip}&components=country:RO`;\r\n'
    b'    //     // request["components"] = `postal_code:${zip}`;\r\n'
    b"    //   }\r\n"
    b"    // });\r\n"
)

A2_NEW = (
    b"  function avalon_init_gmaps() {\r\n"
    b"    //Issue 25. SLP publishes the geocoder request at slp_core.js:1647 and\r\n"
    b"    //geocodes it at 1649, so a subscriber that mutates .address here is\r\n"
    b"    //the last thing to touch the string before it goes out. This is SLP's\r\n"
    b"    //own extension point; the alternative was wrapping\r\n"
    b"    //slp.geocoder.geocode the way install_transport_hook wraps\r\n"
    b"    //slp.send_ajax, which is more machinery for the same result.\r\n"
    b"    //\r\n"
    b"    //store-locator-le/js/slp_core.js is the hard constraint and is never\r\n"
    b"    //edited, so the encodeURI() call at 806 cannot be corrected in place.\r\n"
    b"    //\r\n"
    b"    //Only the string sent to Google changes. #addressInput keeps what the\r\n"
    b"    //visitor typed and the place_address parameter composed in\r\n"
    b"    //cslmap_searchLocations() is untouched, so a successful search still\r\n"
    b"    //shares as the address they actually entered.\r\n"
    b"    //\r\n"
    b"    //A commented-out ZIP/Romania example subscribed to this same filter\r\n"
    b"    //here for years without ever being enabled. Removed in v0.0.14 rather\r\n"
    b"    //than left beside this, where it would read as a second attempt at\r\n"
    b"    //the same job.\r\n"
    b'    slp_Filter("geocoder_request").subscribe(function (request) {\r\n'
    b"      if (!request) return;\r\n"
    b"      request.address = avalon_geocode_safe(request.address);\r\n"
    b"    });\r\n"
)

EDITS = [
    ("avalon_geocode_safe()",              A1_OLD, A1_NEW),
    ("geocoder_request subscriber",        A2_OLD, A2_NEW),
]

PHP_OLD = b" * Version: 0.0.13\r\n"
PHP_NEW = b" * Version: 0.0.14\r\n"


def main() -> None:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "out14")
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
    print("  edit   ok  version header 0.0.13 -> 0.0.14")

    code = strip_js_comments(js)

    assert js.count(b"\r\n") == js.count(b"\n"), "stray bare LF introduced"
    assert not js.endswith(b"\n"), "trailing newline introduced"

    # Presence, exact code constructs only - never a bare identifier, which is
    # how three earlier builds tripped their own self-check on their own prose.
    assert js.count(b"  function avalon_geocode_safe(address) {") == 1, "one definition"
    assert js.count(b"      request.address = avalon_geocode_safe(request.address);") == 1, \
        "one call site, inside the subscriber"
    assert js.count(b'    slp_Filter("geocoder_request").subscribe(function (request) {') == 1, \
        "subscribed exactly once"
    assert js.count(b'    if (address.indexOf("#") === -1 && address.indexOf("?") === -1) {') == 1, \
        "the no-op early return"
    assert js.count(b'      .replace(/[#?]/g, " ")') == 1, "both characters handled together"

    # Absence, against the comment-stripped view. The replacement comment names
    # the retired example on purpose, so a raw count would find it.
    assert code.count(b"components=country:RO") == 0, "the dead ZIP example survives"
    assert code.count(b"isNumber()") == 0, "so does its helper call"
    assert code.count(b"console.log({ request })") == 0, "and its console.log"

    # Nothing established by earlier versions may drift.
    assert js.count(b"    clean_url: function () {") == 1
    assert js.count(b"        this.clean_url();") == 1
    assert js.count(b"window.history.replaceState(") == 2
    assert js.count(b"function add_url_param(params, url) {") == 1
    assert js.count(b"avalon_guard.pending_url = new_url;") == 1
    assert js.count(b"center_spinner: function") == 1
    assert js.count(b"var is_search = named") == 1
    assert js.count(b"!avalon_in_territory(coords.lat, coords.lng)") == 1
    assert js.count(b"var AVALON_SEARCHABLE = ") == 1
    assert js.count(b"place_country: null,") == 0

    assert php.count(b"0.0.14") == 1
    print("  self-check ok")

    (out / "slp_avalon.js").write_bytes(js)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:16} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
