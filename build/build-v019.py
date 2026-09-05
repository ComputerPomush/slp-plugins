#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.19 from the shipped v0.0.18 artefacts.

Two things ship here. They are unrelated in the code and related in the
process: both were named in handoff rev19 SS7 as competing for one release,
and both move a file the release machinery pins.

-----------------------------------------------------------------------------
1. THE AUTOCOMPLETE GATE.  Issue 35.  slp_avalon.js
-----------------------------------------------------------------------------

google.maps.places.Autocomplete bills per request, and its requests go from
the browser straight to Google. They never reach WP Engine, so no WordPress
rate limiter, WAF rule, nonce or page cache can see or shape them (rev13). The
only controls that exist are a Cloud Console daily quota and how many requests
our own code lets the widget make.

The widget queries on every keystroke from the first. With
address_autocomplete = zipcode a visitor types a five-digit ZIP, and the first
characters of a ZIP cannot match anything they want. Those requests are billed
and wasted.

The fix is not to throttle the widget - there is no supported hook for that -
but to defer ATTACHING it until the field already holds enough characters to
be worth a query. Constructing the widget costs nothing; only requests bill.

    before   5 keystrokes -> 5 requests
    after    5 keystrokes -> 2 requests   (attach at 3, first query on the 4th)

KNOWN AND ACCEPTED: the widget does not query text that is already sitting in
the field when it attaches, so predictions first appear on the keystroke AFTER
the threshold is crossed. At 3 that means predictions from the fourth
character. Lowering avalon_autocomplete_min_chars to 2 restores them at the
third and costs one more request per visitor. That is a one-token change
precisely because decision 65 put the number in our own file.

DECISION 65 - the threshold is a constant here, deliberately NOT
slplus.options.address_autocomplete_min. That option exists and reads 3, but
it drives SLP's own jQuery-UI zip suggester. Inheriting it would move this
gate silently whenever somebody tuned the suggester - the same two-consumers-
one-value coupling that decision 66 closed on the other side of the file. A
wp-config.php constant was the third option and was rejected because JS cannot
read one without a localize step, which would pull class.slp_avalon.php into
what is otherwise a single-file release.

MEASURED BEFORE WRITING, not assumed:

  * initialize_autocomplete() is at GLOBAL scope. The jQuery IIFE opens at
    line 1 and closes at line 43; line 1104 sits at brace depth 0, beside
    cslmap_searchLocations and cslmap_build_map. So the constant, the flag and
    the helper are all reachable as ctx.* in the vm harness and suite-v019
    asserts them directly, with no change to harness.js and therefore no
    disturbance to the six JS suites that share it.

  * The availability guard must be `typeof google === "undefined"`, NOT
    `window.google`. harness.js defines google as a bare context global
    (line 185) and window as a separate object (line 158) carrying no google
    property. A window.google guard returns early under test, the widget is
    never constructed, and the negative case passes for the wrong reason -
    rev14 SS8's trap, which is exactly how three earlier builds tripped.

  * The harness's jQuery on() and off() are no-ops in the chain list, so
    suite-v019 patches ctx.jQuery.fn.on after load to capture the delegated
    handler and invokes it itself. initialize_autocomplete() is called from
    avalon_init_gmaps() at line 1270 and not at load, so patching after load
    is early enough.

The listener is delegated on document and namespaced .avalon_ac, matching the
"change" handler thirty lines above it: the search form is re-rendered on some
templates and a directly bound listener would not survive it. It unbinds
itself on the crossing, and avalon_attach_autocomplete() is idempotent anyway,
because a handler can fire more than once before off() takes effect and
avalon_init_gmaps() is not guaranteed to run exactly once.

The middle of the old function - setFields(["address_components","geometry"])
and the place_changed listener that writes place_lat, place_lng and
place_country and clicks the submit button - is NOT in either anchor and does
not move. That is deliberate. It is the code that closes the v0.0.6
autocomplete bypass, and a release about billing should not be able to touch
it. The two edits bracket it instead.

CARRIED TO THE WAVE, not fixable here: rev18 SS9 records that
assets/js/googlelocation.js in the child theme redefines globals slp_avalon.js
owns, and it is still present on Aura LIVE, Tahoe DEV and Avalon DEV.
initialize_autocomplete is a global. If the theme copy defines it too, the
ungated version wins wherever it is enqueued later and this gate does nothing
on that environment. Grep those three for initialize_autocomplete during the
decision 60 visit before concluding the release works there.

-----------------------------------------------------------------------------
2. ISSUE 34.  store-locator-le/js/** -text   .gitattributes
-----------------------------------------------------------------------------

rev19 SS6.1. Publish-Step14 -Mode Verify was run inside a fresh clone for the
first time and failed on exactly two files, both upstream SLP:

    store-locator-le/js/slp_core.js       LF in the repo, CRLF in the clone
    store-locator-le/js/slp_core.min.js   LF in the repo, CRLF in the clone

Converting the repo copies to CRLF reproduces the clone's hashes exactly, so
the cause is core.autocrlf=true on checkout and nothing else.

The v0.0.18 .gitattributes deliberately excluded store-locator-le on the
grounds that upstream is never edited. That was the right rule aimed at the
wrong target. "Never edit upstream" and "never pin upstream" are different
rules, and the moment those two md5s went into release-pins.csv and Publish's
$Upstream table the project took responsibility for their bytes. Telling git
not to convert a file is not an edit to it.

The pins stay, because slp_avalon.js cites slp_core.js lines 1720, 1808 and
1842 by number and an SLP auto-update moving them is the thing the pins exist
to catch.

ONE-TIME HAZARD after this lands, rev19 SS6.3: with -text there is no
normalisation, so any of these files sitting in a working tree as CRLF now
reports as modified. Fix with `git checkout -- <paths>`. NEVER `git add` them
and NEVER `git add --renormalize` - either stores the CRLF bytes verbatim and
changes the fresh-clone hash of every pinned upstream file.

-----------------------------------------------------------------------------
NOT IN THIS RELEASE
-----------------------------------------------------------------------------

Issue 22 is CLOSED, working as designed (decision 66). initial_results_returned
and max_results_returned are both registered base-plugin options at
slp_core.js:1720-1721, and the csl_ajax_onload latch at 1841 is the mechanism
by which the first search reads one and later searches read the other. That is
the feature, not a defect leaking through. Nothing in this build touches it,
and suite-v019 carries the guards that prove it still holds.

class.slp_avalon.php is NOT an input here and must not move.

Usage:  python3 build-v019.py [repo_root] [out_dir]
        run from D:\\Temp\\Projects\\GitHub\\slp-plugins with no arguments
"""

import hashlib
import sys
from pathlib import Path

IN_PATHS = {
    "slp_avalon.js": Path("slp_avalon") / "assets" / "js" / "slp_avalon.js",
    "slp_avalon.php": Path("slp_avalon") / "slp_avalon.php",
    ".gitattributes": Path(".gitattributes"),
}

SRC_MD5 = {
    "slp_avalon.js": "8c93719e41af3232c18773a104e8dedd",
    "slp_avalon.php": "62be97c1514cb8278a4c3b416e55d119",
    ".gitattributes": "9eb08f72cce56ade5ec1a423d12a1549",
}

EXPECT_JS_BYTES = 70159
EXPECT_JS_CRLF = 1655
EXPECT_JS_DELTA = 2796
EXPECT_PHP_BYTES = 1808
EXPECT_PHP_CRLF = 59
EXPECT_ATTR_BYTES = 1353


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


def crlf(lines) -> bytes:
    """Join source lines with CRLF. No trailing terminator is added."""
    return b"\r\n".join(lines)


def strip_js_comments(blob: bytes) -> bytes:
    """
    Return the file with // and block comments removed, literals kept.

    Carried verbatim from build-v013.py through build-v014.py. Decision 33:
    presence assertions run against real bytes, absence assertions against
    this view, because the replacement comments quote retired code on purpose
    and a raw-byte count picks up the prose.

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


# ---------------------------------------------------------------------------
# EDIT 1 - the constants and the attach helper.
#
# Anchored from the newline that terminates the line ABOVE, per rev14 SS8: an
# anchor beginning at the first non-space character can match the tail of a
# deeper indent and insert at the wrong depth. Everything from setFields()
# onward is outside this anchor and does not move.
# ---------------------------------------------------------------------------

A1_OLD = crlf([
    b"",
    b"  function initialize_autocomplete() {",
    b'    let input = document.getElementById("addressInput");',
    b"    if (!input) return;",
    b"    let places_autocomplete = new google.maps.places.Autocomplete(input);",
    b"",
])

A1_NEW = crlf([
    b"",
    b"  //Issue 35, v0.0.19. Places Autocomplete bills per request and the",
    b"  //requests go browser -> Google directly, so no WordPress rate limiter,",
    b"  //WAF rule, nonce or page cache can see them. The widget queries on every",
    b"  //keystroke from the first, and with address_autocomplete = zipcode the",
    b"  //opening characters of a ZIP cannot match anything the visitor wants.",
    b"  //Deferring the ATTACH until the field holds this many characters skips",
    b"  //those requests. Constructing the widget is free; only queries bill.",
    b"  //",
    b"  //Decision 65: the number lives here and is deliberately NOT",
    b"  //slplus.options.address_autocomplete_min. That option exists and reads 3",
    b"  //but drives SLP's own jQuery-UI zip suggester, and inheriting it would",
    b"  //move this gate silently whenever someone tuned the suggester.",
    b"  //",
    b"  //The widget does not query text already sitting in the field when it",
    b"  //attaches, so predictions first appear on the keystroke AFTER the",
    b"  //threshold is crossed - at 3, from the fourth character. Set this to 2",
    b"  //to put them back at the third and spend one more request per visitor.",
    b"  var avalon_autocomplete_min_chars = 3;",
    b"  var avalon_autocomplete_attached = false;",
    b"  function avalon_attach_autocomplete(input) {",
    b"    //Idempotent. The delegated handler can fire more than once before",
    b"    //off() takes effect, and avalon_init_gmaps() is not guaranteed to run",
    b"    //exactly once.",
    b"    if (avalon_autocomplete_attached || !input) return;",
    b"    //typeof, not window.google. google is a bare global in the browser and",
    b"    //in the vm harness, where window carries no google property at all - a",
    b"    //window.google test would return early under test, never construct the",
    b"    //widget, and let the negative case pass for the wrong reason.",
    b'    if (typeof google === "undefined" || !google.maps || !google.maps.places) {',
    b"      return;",
    b"    }",
    b"    avalon_autocomplete_attached = true;",
    b"    let places_autocomplete = new google.maps.places.Autocomplete(input);",
    b"",
])


# ---------------------------------------------------------------------------
# EDIT 2 - the gate itself, reusing the original function name so the call
# site at avalon_init_gmaps() is untouched.
#
# The following line is carried in the anchor because "  }" alone is not
# unique. String.prototype.isNumber is the next declaration in the file.
# ---------------------------------------------------------------------------

A2_OLD = crlf([
    b'        jQuery("#searchForm").find("input[type=submit]").trigger("click");',
    b"      }",
    b"    });",
    b"  }",
    b"  String.prototype.isNumber = function () {",
])

A2_NEW = crlf([
    b'        jQuery("#searchForm").find("input[type=submit]").trigger("click");',
    b"      }",
    b"    });",
    b"  }",
    b"  function initialize_autocomplete() {",
    b'    let input = document.getElementById("addressInput");',
    b"    if (!input) return;",
    b"    //A field that already holds a value did not get there by typing - the",
    b"    //URL bootstrap in cslmap_build_map() fills it - so there are no",
    b"    //keystrokes left to save and attaching now preserves the edit path.",
    b"    let seeded = jQuery(input).val();",
    b"    if (seeded && seeded.trim().length >= avalon_autocomplete_min_chars) {",
    b"      avalon_attach_autocomplete(input);",
    b"      return;",
    b"    }",
    b'    //Delegated and namespaced, matching the "change" handler above: the',
    b"    //search form is re-rendered on some templates and a directly bound",
    b"    //listener would not survive it.",
    b'    jQuery(document).on("input.avalon_ac", "#addressInput", function () {',
    b"      if (jQuery(this).val().trim().length < avalon_autocomplete_min_chars) {",
    b"        return;",
    b"      }",
    b'      jQuery(document).off("input.avalon_ac", "#addressInput");',
    b"      avalon_attach_autocomplete(this);",
    b"    });",
    b"  }",
    b"  String.prototype.isNumber = function () {",
])

EDITS = [
    ("constants + avalon_attach_autocomplete()", A1_OLD, A1_NEW),
    ("initialize_autocomplete() becomes the gate", A2_OLD, A2_NEW),
]

PHP_OLD = b" * Version: 0.0.18\r\n"
PHP_NEW = b" * Version: 0.0.19\r\n"

# .gitattributes is an LF file. The comment moves with the rule because the
# old comment asserts the opposite of what the new rule does.
GITATTR_OLD = (
    b"# Upstream SLP plugins are never edited, so they are deliberately left alone.\n"
    b"slp_avalon/** -text\n"
)

GITATTR_NEW = (
    b"# Upstream SLP plugins are never edited. Two of them are PINNED, and that is\n"
    b"# a different rule: release-pins.csv and Publish's $Upstream table carry md5s\n"
    b"# for store-locator-le/js/slp_core.js and slp_core.min.js, because\n"
    b"# slp_avalon.js cites lines 1720, 1808 and 1842 of that file by number and an\n"
    b"# SLP auto-update moving them is what the pins exist to catch. Both files are\n"
    b"# stored LF; without the rule below a fresh clone under core.autocrlf=true\n"
    b"# checks them out CRLF and both pins fail before Verify starts. Issue 34.\n"
    b"# Stopping git converting a file is not editing that file.\n"
    b"slp_avalon/** -text\n"
    b"store-locator-le/js/** -text\n"
)


def main() -> None:
    repo = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
    out = Path(sys.argv[2]) if len(sys.argv) > 2 else repo / "build" / "out19"

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

    js = (repo / IN_PATHS["slp_avalon.js"]).read_bytes()
    php = (repo / IN_PATHS["slp_avalon.php"]).read_bytes()
    attr = (repo / IN_PATHS[".gitattributes"]).read_bytes()

    for name, blob in (("slp_avalon.js", js),
                       ("slp_avalon.php", php),
                       (".gitattributes", attr)):
        got = md5(blob)
        if got != SRC_MD5[name]:
            raise SystemExit(f"INPUT MD5 FAIL {name}: {got} != {SRC_MD5[name]}")
        print(f"  input  ok  {name:16} {got}")

    before = len(js)

    for label, old, new in EDITS:
        js = sub_once(js, old, new, label)
        print(f"  edit   ok  {label}")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  version header 0.0.18 -> 0.0.19")

    attr = sub_once(attr, GITATTR_OLD, GITATTR_NEW, "Issue 34 upstream js rule")
    print("  edit   ok  .gitattributes covers store-locator-le/js/")

    code = strip_js_comments(js)

    # --- line endings ------------------------------------------------------
    assert js.count(b"\r\n") == js.count(b"\n"), "stray bare LF in slp_avalon.js"
    assert php.count(b"\r\n") == php.count(b"\n"), "stray bare LF in slp_avalon.php"
    assert not js.endswith(b"\n"), "trailing newline introduced in slp_avalon.js"
    assert not php.endswith(b"\n"), "trailing newline introduced in slp_avalon.php"
    assert attr.count(b"\r") == 0, "CR introduced into .gitattributes"
    assert attr.endswith(b"\n"), ".gitattributes lost its trailing newline"

    # --- EDIT 1, the gate's moving parts -----------------------------------
    assert js.count(b"  var avalon_autocomplete_min_chars = 3;") == 1, \
        "the threshold constant is not defined exactly once"
    assert js.count(b"  var avalon_autocomplete_attached = false;") == 1, \
        "the attached flag is not defined exactly once"
    assert js.count(b"  function avalon_attach_autocomplete(input) {") == 1, \
        "the attach helper is not defined exactly once"
    assert js.count(b"    if (avalon_autocomplete_attached || !input) return;") == 1, \
        "the idempotence guard is missing"
    assert js.count(b"    avalon_autocomplete_attached = true;") == 1, \
        "the flag is never set, so the helper is not idempotent"

    # Decision 65, stated as an assertion rather than a comment. The SLP option
    # must not be read anywhere in the artefact.
    assert code.count(b"address_autocomplete_min") == 0, \
        "the SLP zip-suggester option is being read - decision 65 says it is not"

    # The availability guard. window.google is the wrong test and would make
    # the suite structurally unable to observe the feature.
    assert js.count(
        b'    if (typeof google === "undefined" || !google.maps || !google.maps.places) {'
    ) == 1, "the availability guard is missing or reworded"
    assert code.count(b"window.google") == 0, \
        "a window.google guard survives - undefined in the harness, passes for the wrong reason"

    # --- EDIT 2, the gate --------------------------------------------------
    assert js.count(b"  function initialize_autocomplete() {") == 1, \
        "the entry point is not defined exactly once"
    assert js.count(b"    initialize_autocomplete();") == 1, \
        "the call site in avalon_init_gmaps() moved or was duplicated"
    assert js.count(
        b'    jQuery(document).on("input.avalon_ac", "#addressInput", function () {'
    ) == 1, "the delegated input listener is missing"
    assert js.count(
        b'      jQuery(document).off("input.avalon_ac", "#addressInput");'
    ) == 1, "the listener never unbinds itself"
    assert js.count(
        b"      if (jQuery(this).val().trim().length < avalon_autocomplete_min_chars) {"
    ) == 1, "the threshold test is missing or does not trim"
    assert js.count(
        b"    if (seeded && seeded.trim().length >= avalon_autocomplete_min_chars) {"
    ) == 1, "the seeded-field path is missing"
    assert js.count(b"      avalon_attach_autocomplete(input);") == 1, \
        "the seeded path does not attach"
    assert js.count(b"      avalon_attach_autocomplete(this);") == 1, \
        "the delegated path does not attach"

    # Ordering: the constant must be declared before both readers, and the
    # helper before the gate that calls it.
    assert js.index(b"var avalon_autocomplete_min_chars") \
        < js.index(b"  function initialize_autocomplete() {"), \
        "the threshold is declared after the gate that reads it"
    assert js.index(b"  function avalon_attach_autocomplete(input) {") \
        < js.index(b"  function initialize_autocomplete() {"), \
        "the helper is declared after the gate that calls it"

    # --- the untouched middle ----------------------------------------------
    # Neither anchor contains these. They are asserted because they are the
    # v0.0.6 autocomplete-bypass fix, and a billing release must not move it.
    assert js.count(
        b'    places_autocomplete.setFields(["address_components", "geometry"]);'
    ) == 1, "setFields moved - Places Details SKU would come back"
    assert js.count(
        b'    places_autocomplete.addListener("place_changed", function () {'
    ) == 1, "the place_changed listener moved"
    assert js.count(
        b'        jQuery(input).data("place_country", avalon_country_of(selected));'
    ) == 1, "the place_country write moved - this is the v0.0.6 bypass fix"
    assert js.count(b"    let places_autocomplete = new google.maps.places.Autocomplete(input);") == 1, \
        "the widget is constructed more or less than once in the source"

    # --- decision 66, Issue 22 stays closed --------------------------------
    # Nothing here may SPEND SLP's latch. initial_results_returned and
    # max_results_returned are both base-plugin options (slp_core.js:1720-1721)
    # and the latch at 1841 is how the first search reads one and later
    # searches read the other. A write here would collapse them into one and
    # silently delete a documented setting.
    #
    # There is exactly one pre-existing READ, at cslmap_build_map(): the URL
    # bootstrap decides whether to fire on a place_* link. That is a read of
    # the setting, not a spend of the latch, and it is pinned rather than
    # forbidden - suite-v019's salvaged guards drive precisely that branch.
    assert code.count(b'slplus.options.immediately_show_locations !== "0"') == 1, \
        "the URL bootstrap's read of the latch moved or was duplicated"
    assert code.count(b"immediately_show_locations") == 1, \
        "a second reference to SLP's onload latch appeared"
    for write in (b"immediately_show_locations =", b"immediately_show_locations="):
        assert code.count(write) == 0, \
            "something now writes SLP's onload latch - decision 66 says nothing does"

    # --- nothing established by earlier versions may drift -----------------
    assert js.count(b"    clean_url: function () {") == 1
    assert js.count(b"        this.clean_url();") == 1
    assert js.count(b"window.history.replaceState(") == 2
    assert js.count(b"function add_url_param(params, url) {") == 1
    assert js.count(b"avalon_guard.pending_url = new_url;") == 1
    assert js.count(b"center_spinner: function") == 1
    assert js.count(b"var is_search = named") == 1
    assert js.count(b"!avalon_in_territory(coords.lat, coords.lng)") == 1
    assert js.count(b"var AVALON_SEARCHABLE = ") == 1
    assert js.count(b"  function avalon_geocode_safe(address) {") == 1
    assert js.count(b"      request.address = avalon_geocode_safe(request.address);") == 1
    assert js.count(b"place_country: null,") == 0

    # --- PHP ---------------------------------------------------------------
    assert php.count(b"0.0.19") == 1
    assert php.count(b"0.0.18") == 0

    # --- .gitattributes, Issue 34 ------------------------------------------
    assert attr.count(b"\nstore-locator-le/js/** -text\n") == 1, \
        "the Issue 34 rule is missing or duplicated"
    assert attr.count(b"\nslp_avalon/** -text\n") == 1, \
        "the original slp_avalon rule was disturbed"
    for rule in (b"\nbuild/** -text\n", b"\ntest/** -text\n",
                 b"\n*.ps1 -text\n", b"\n.gitattributes -text\n"):
        assert attr.count(rule) == 1, f"v0.0.18 rule missing or duplicated: {rule!r}"
    # The rule is scoped to js/ only. Widening it to the whole plugin would put
    # every upstream file under our responsibility, which is not the decision.
    assert attr.count(b"store-locator-le/** -text") == 0, \
        "the rule was widened past js/ - only the two pinned files are ours"

    # --- byte-exact expectations -------------------------------------------
    js_crlf = js.count(b"\r\n")
    php_crlf = php.count(b"\r\n")
    delta = len(js) - before

    if EXPECT_JS_BYTES:
        assert delta == EXPECT_JS_DELTA, \
            f"slp_avalon.js grew {delta} bytes, expected {EXPECT_JS_DELTA}"
        assert len(js) == EXPECT_JS_BYTES, \
            f"slp_avalon.js {len(js)} bytes, expected {EXPECT_JS_BYTES}"
        assert js_crlf == EXPECT_JS_CRLF, \
            f"slp_avalon.js CRLF={js_crlf}, expected {EXPECT_JS_CRLF}"
        assert len(attr) == EXPECT_ATTR_BYTES, \
            f".gitattributes {len(attr)} bytes, expected {EXPECT_ATTR_BYTES}"
    else:
        print(f"  MEASURE    delta={delta}  js={len(js)}  crlf={js_crlf}  "
              f"attr={len(attr)}")

    assert len(php) == EXPECT_PHP_BYTES, \
        f"slp_avalon.php {len(php)} bytes, expected {EXPECT_PHP_BYTES}"
    assert php_crlf == EXPECT_PHP_CRLF, \
        f"slp_avalon.php CRLF={php_crlf}, expected {EXPECT_PHP_CRLF}"

    print("  self-check ok")

    (out / "slp_avalon.js").write_bytes(js)
    (out / "slp_avalon.php").write_bytes(php)
    (out / ".gitattributes").write_bytes(attr)

    for name, blob in (("slp_avalon.js", js),
                       ("slp_avalon.php", php),
                       (".gitattributes", attr)):
        n = blob.count(b"\r\n")
        print(f"  output     {name:16} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={n}")


if __name__ == "__main__":
    main()
