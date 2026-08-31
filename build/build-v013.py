#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.13 from the shipped v0.0.12 artefacts.

Issue 16, the sticky URL. Handoff rev 9 s7.7.

The defect. finish() writes pending_url to the address bar only on RESULTS,
so a failed search never DIRTIES the URL. It never CLEANS one the visitor
arrived on either. Land on ?place_lat=48.86&place_lng=2.35, get the territory
message, refresh, get it again. Forever. The parameters outlive the search
that failed.

The fix is a second branch in finish(): write on RESULTS, clean on every other
terminal state.

Three things shape the implementation.

(a) It cannot use pending_url. A Layer 0 rejection returns at slp_avalon.js
    882-916, and pending_url is not assigned until 938; start() nulls it at
    270. On the exact path this exists to fix, pending_url is always null. So
    the cleaned URL is measured from window.location.href, which is what
    add_url_param() already defaults to when called with one argument.

(b) It is a remove-list, not a keep-list. Constraint C1: UTM parameters arrive
    on a first-touch URL before any cookie exists, so a keep-list would
    discard attribution for precisely the visitors it exists to measure.
    add_url_param() deletes on a falsy value already (line 54), so the three
    keys go in as nulls and nothing else on the query string is touched.
    place_country is deliberately absent - it never reaches the URL, it lives
    only in jQuery .data() on #addressInput.

(c) It needs the no-op test. suite-core asserts that a REJECTED finish issues
    no replaceState, which is the regression net for Issue 1's rule (c).
    Comparing the cleaned href against the current one and returning early
    keeps that assertion green and avoids a pointless history write on every
    rejection from an already-clean URL. The alternative was to weaken an
    existing regression test to let a new feature through.

Decision 35, made by the owner this session: EMPTY cleans too. s7.7 listed
REJECTED, ERROR and TIMEOUT. Any narrower rule than "everything but RESULTS"
leaves a case where the bar lies - land on ?place_address=Detroit, search
somewhere with no dealers, and EMPTY would keep replaying Detroit. Two costs
were accepted with it: a genuine no-dealers result stops being shareable as a
link, and a TIMEOUT on a slow but valid search loses the query on refresh
instead of retrying it.

Verified before writing this, because the clean happens mid-cycle and a second
reader would make it racy: store-locator-le/js/slp_core.min.js (md5
7924dad949f851d90ade9118c8bd045a - the build that actually runs) contains no
reference to place_address, place_lat, place_lng or place_country, and no
history call of any kind. Its four window.location uses are protocol and
hostname comparisons. cslmap_build_map() at 1087 is the only reader on the
page, and it runs once at init, before any search can finish.

JS-only. class.slp_avalon.php is not an input here and must not move;
Publish-Step9.ps1 pins it unchanged the way Step8 pinned the JS.

Usage:  python3 build-v013.py <src_dir> <out_dir>
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "slp_avalon.js": "a6237b4f2c006964710f4b5362437c66",
    "slp_avalon.php": "aa3e5ba266959a413b4b2db4378167d0",
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

    The JS twin of build-v012.py's strip_php_comments(), and it exists for the
    same reason. Decision 33. An "is it gone" assertion that runs against raw
    bytes counts the replacement comment, because the replacement comment
    quotes the retired expression on purpose so the next reader knows not to
    put it back. v0.0.9, v0.0.10, v0.0.11 and both early runs of v0.0.12 each
    tripped their own self-check that way.

    Differences from the PHP version: no `#` line comments, and backticks are
    a string delimiter. slp_avalon.js has 30 of them. Interpolation inside a
    template literal is treated as ordinary string content, which is fine here
    - a `//` inside a ${} expression would confuse it, and there is none.

    The single regex literal in the file, AVALON_SEARCHABLE, is safe: the
    scanner only leaves code state on `//` or `/*`, and that literal opens
    `/[`. If a regex ever opens with `*` this needs revisiting.

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
        # inside a string literal
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
# The RESULTS branch in finish() becomes a two-way decision. The nested
# `if (this.pending_url)` is kept rather than folded into the outer test,
# because RESULTS-with-no-pending_url is a real case and it must fall through
# BOTH branches: get_user_current_address() reaches load_markers() directly
# without going through cslmap_searchLocations(), so no URL was ever composed
# and there is nothing to write and nothing to clean.
A1_OLD = (
    b"      // Issue 1, sticky bad URL: rule (c) - the address bar is rewritten only\r\n"
    b"      // once a search has actually succeeded, so a refresh cannot replay a\r\n"
    b"      // URL that hung. add_url_param() stays a remove-list (constraint C1);\r\n"
    b"      // the UTM parameters on the URL are untouched either way.\r\n"
    b"      if (terminal_state === this.RESULTS && this.pending_url) {\r\n"
    b'        window.history.replaceState(null, "", this.pending_url);\r\n'
    b"      }\r\n"
    b"      this.pending_url = null;\r\n"
)

A1_NEW = (
    b"      // Issue 1, sticky bad URL: rule (c) - the address bar is rewritten only\r\n"
    b"      // once a search has actually succeeded, so a refresh cannot replay a\r\n"
    b"      // URL that hung. add_url_param() stays a remove-list (constraint C1);\r\n"
    b"      // the UTM parameters on the URL are untouched either way.\r\n"
    b"      //\r\n"
    b"      // Issue 16, v0.0.13. Rule (c) stopped a failure from DIRTYING the bar\r\n"
    b"      // but never CLEANED one the visitor arrived on, so a refresh replayed\r\n"
    b"      // it: land on ?place_lat=48.86&place_lng=2.35, get the territory\r\n"
    b"      // message, refresh, get it again, forever.\r\n"
    b"      //\r\n"
    b"      // Write on RESULTS, clean on every other terminal state. Decision 35.\r\n"
    b"      // Any narrower rule leaves a case where the parameters on the bar do\r\n"
    b"      // not describe what is on the screen: land on ?place_address=Detroit,\r\n"
    b"      // search somewhere with no dealers, and EMPTY would keep replaying\r\n"
    b"      // Detroit. Two costs were accepted with it - a genuine no-dealers\r\n"
    b"      // result stops being shareable as a link, and a TIMEOUT on a slow but\r\n"
    b"      // valid search loses the query on refresh rather than retrying it.\r\n"
    b"      if (terminal_state === this.RESULTS) {\r\n"
    b"        //Null on the get_user_current_address() bootstrap, which reaches\r\n"
    b"        //load_markers() directly and never composes a URL. Nothing to write\r\n"
    b"        //and nothing to clean - there were no place_ parameters to begin\r\n"
    b"        //with - so this case falls through both branches on purpose.\r\n"
    b"        if (this.pending_url) {\r\n"
    b'          window.history.replaceState(null, "", this.pending_url);\r\n'
    b"        }\r\n"
    b"      } else {\r\n"
    b"        this.clean_url();\r\n"
    b"      }\r\n"
    b"      this.pending_url = null;\r\n"
)

# --------------------------------------------------------------- edit 2
A2_OLD = b"    },\r\n\r\n    arm_timer: function () {\r\n"

A2_NEW = (
    b"    },\r\n"
    b"\r\n"
    b"    /**\r\n"
    b"     * Issue 16. Strip the three search parameters from the address bar and\r\n"
    b"     * leave everything else on it alone.\r\n"
    b"     *\r\n"
    b"     * A remove-list, not a keep-list. Constraint C1: UTM parameters arrive\r\n"
    b"     * on a first-touch URL before any cookie exists, so a keep-list would\r\n"
    b"     * discard attribution for exactly the visitors it is there to measure.\r\n"
    b"     * add_url_param() already deletes on a falsy value, and these are the\r\n"
    b"     * same three keys cslmap_searchLocations() composes. place_country is\r\n"
    b"     * deliberately not among them: it never reaches the URL, it lives only\r\n"
    b"     * in jQuery .data() on #addressInput.\r\n"
    b"     *\r\n"
    b"     * Measured from window.location.href, NEVER from pending_url. On the\r\n"
    b"     * path this exists to fix, pending_url is always null - a Layer 0\r\n"
    b"     * rejection returns before pending_url is assigned, and start() nulls\r\n"
    b"     * it at the top of every cycle.\r\n"
    b"     *\r\n"
    b"     * Safe to run mid-cycle because nothing else reads these parameters or\r\n"
    b"     * touches history. cslmap_build_map() reads them once at init, before\r\n"
    b"     * any search can finish, and slp_core.min.js - the build that actually\r\n"
    b"     * runs - has no reference to place_address, place_lat or place_lng and\r\n"
    b"     * no history call at all.\r\n"
    b"     *\r\n"
    b"     * The no-op test is load-bearing, not tidiness. Without it every\r\n"
    b"     * rejection from an already-clean URL issues a pointless replaceState,\r\n"
    b"     * and suite-core's assertion that a rejected search does not rewrite\r\n"
    b"     * the URL - the regression net for rule (c) - would have to be weakened\r\n"
    b"     * to let this feature through.\r\n"
    b"     */\r\n"
    b"    clean_url: function () {\r\n"
    b"      var cleaned = add_url_param({\r\n"
    b"        place_address: null,\r\n"
    b"        place_lat: null,\r\n"
    b"        place_lng: null,\r\n"
    b"      });\r\n"
    b"      if (cleaned.href === window.location.href) return;\r\n"
    b'      window.history.replaceState(null, "", cleaned.href);\r\n'
    b"    },\r\n"
    b"\r\n"
    b"    arm_timer: function () {\r\n"
)

EDITS = [
    ("finish(): clean on every non-RESULTS terminal state", A1_OLD, A1_NEW),
    ("clean_url()",                                        A2_OLD, A2_NEW),
]

PHP_OLD = b" * Version: 0.0.12\r\n"
PHP_NEW = b" * Version: 0.0.13\r\n"


def main() -> None:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "out13")
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
    print("  edit   ok  version header 0.0.12 -> 0.0.13")

    # Presence assertions against real bytes, absence assertions against the
    # comment-stripped view. Decision 33.
    code = strip_js_comments(js)

    assert js.count(b"\r\n") == js.count(b"\n"), "stray bare LF introduced"
    assert not js.endswith(b"\n"), "trailing newline introduced"

    assert js.count(b"    clean_url: function () {") == 1, "one definition"
    assert js.count(b"        this.clean_url();") == 1, "one call site, in finish()"
    assert js.count(b"var cleaned = add_url_param({") == 1, "built by the existing helper"
    assert js.count(b"if (cleaned.href === window.location.href) return;") == 1, \
        "the no-op test that keeps suite-core green"
    assert js.count(b"window.history.replaceState(") == 2, \
        "two writers: the RESULTS write and the clean"

    # Three keys in clean_url plus the same three in cslmap_searchLocations.
    for key in (b"place_address: null,", b"place_lat: null,", b"place_lng: null,"):
        assert js.count(key) == 2, f"{key!r} in both the composer and the cleaner"
    assert js.count(b"place_country: null,") == 0, \
        "place_country never reaches the URL - only jQuery .data()"

    # The old single-branch form is gone. Checked against stripped code because
    # nothing stops a future comment from quoting it.
    assert code.count(b"if (terminal_state === this.RESULTS && this.pending_url) {") == 0, \
        "the v0.0.12 single-branch form survives"
    assert code.count(b"if (terminal_state === this.RESULTS) {") == 1, "the outer test"
    assert code.count(b"        if (this.pending_url) {") == 1, "the nested write test"

    # Nothing that earlier versions established may drift.
    assert js.count(b"function add_url_param(params, url) {") == 1
    assert js.count(b"avalon_guard.pending_url = new_url;") == 1
    assert js.count(b"center_spinner: function") == 1
    assert js.count(b"var is_search = named") == 1
    assert js.count(b"!avalon_in_territory(coords.lat, coords.lng)") == 1
    assert js.count(b"var AVALON_SEARCHABLE = ") == 1

    assert php.count(b"0.0.13") == 1
    print("  self-check ok")

    (out / "slp_avalon.js").write_bytes(js)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:16} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
