#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.18 from the shipped v0.0.17 artefacts.

THREE EDITS ACROSS THREE FILES.

  1. class.slp_avalon.php  register the REST strip on rest_post_dispatch.
  2. class.slp_avalon.php  the three methods that do the stripping.
  3. slp_avalon.php        version header 0.0.17 -> 0.0.18.
  4. .gitattributes        cover the tooling.  rev17 s0.53.

slp_avalon.js is NOT touched.  It has not moved since v0.0.14 and the first
release that touches it is v0.0.19.

-----------------------------------------------------------------------------
WHAT LEAKS, MEASURED 2026-09-05 ON AURA DEV
-----------------------------------------------------------------------------
An anonymous GET of /wp-json/store-locator-plus/v1/options/all returns

    HTTP 201   10,539 bytes   md5 bfe3dbb49153e5046841e7e59006f300

carrying, at store-locator-le -> settings -> options, a flat map of 120 slugs.
Two of them are google_server_key and google_geocode_key and they hold the SAME
39-character value.  Every leaf in that payload sits at path depth four, there
are no lists, and the values are 118 strings and 2 ints.

/store-locator-plus/v2/options/all returns the identical bytes.  rev18 s5
recorded `AIza=1`; that was grep -c against single-line JSON, which counts
matching LINES.  The substring count is 2.

-----------------------------------------------------------------------------
EDIT 1 - the registration
-----------------------------------------------------------------------------
add_filter, not add_action: rest_post_dispatch passes a value through and
expects it back, and a callback registered with add_action would still run but
its return value would be discarded - the response would go out unstripped and
nothing would report a fault.  rev12's Layer 3 note makes the same point about
territory_gate.

Priority 999.  Same argument as territory_gate at 20: a strip that runs before
something else repopulates the payload is worth nothing.  999 is already this
file's number for 'last' - remove_old_csv_files_after_import uses it.
PHP_INT_MAX was rejected because it makes the ordering unreadable at the call
site and buys nothing over a number no other plugin on these six sites uses.

accepted_args 3.  The callback needs $request to read the route.  A
registration passing fewer leaves $request null, the callback bails on its own
guard, and the key keeps leaking silently.  That is the rev16 s0.38 failure
mode exactly - a wiring defect invisible to a callback test - so suite-v018
parses this line out of the artefact and asserts the hook name, the priority
and the accepted_args count, and the deploy checklist ends with an anonymous
curl rather than a green suite.

Anchored from the preceding newline through the closing brace of add_actions(),
per rev14 s8.  The twelve-space indent is load-bearing and an anchor starting
at the first non-space character would match the last eight of it.

-----------------------------------------------------------------------------
EDIT 2 - the three methods
-----------------------------------------------------------------------------
Appended after territory_gate(), the last method in the class, anchored on the
file tail.  The file ends `}\r\n    }\r\n}` with NO trailing newline and the
build asserts that it still does.

Decision 62 - route test is the /store-locator-plus/ PREFIX, not the v1
namespace.  The REST index on Aura DEV lists three SLP namespaces:
store-locator-plus/v1, store-locator-plus/v2 and a bare store-locator-plus
carrying four anonymous report/location/* routes.  rev18 s5's route table was
read out of SLP_REST_Handler.php, which is the v1 handler; v2 was never in
view.  Scoping to v1 as decisions 58 and 61 word it would have shipped a
release that closed one of the two leaking routes and left the other open.
The prefix covers v1, v2, the report namespace, and a v3 whenever it arrives,
in one string that needs no re-edit.

Decision 63 - two limbs.  The payload limb unsets protected keys by NAME at any
depth, so a future route that serialises SmartOptions differently is covered
without another edit.  The route limb exists because /options/<slug> and
/options/filtered/<slug> name the option in the ROUTE and return it in a
generically named field: a name-keyed walk cannot see it.  Both routes return
HTTP 500 / 3,619 bytes today, on v1 and v2 alike, which is precisely why the
shape of a working response cannot be measured and the limb cannot be keyed on
field names.  When the slug being requested is protected the body is replaced
wholesale.

Decision 64 - unset(), not blank.  An empty string reads as 'the key is not
configured' and would send the next person debugging a phantom.  Absence is the
honest answer to a request that is not entitled to the value.

The capability is manage_slp_user, confirmed live at SLP_REST_Handler.php:731.
The check runs AFTER the route test so current_user_can() is not called on
every REST response the site serves.

-----------------------------------------------------------------------------
EDIT 4 - .gitattributes
-----------------------------------------------------------------------------
rev17 s0.53 called for three lines.  This ships FOUR.  build/**, test/** and
*.ps1 are the tooling that s0.53 identified.  The fourth, .gitattributes
itself, is added because that file's own md5 is asserted in release-pins.csv
and in every Publish script's table, and without a rule covering it a fresh
clone under core.autocrlf=true checks it out as CRLF and breaks its own pin.
gitattributes rules apply to the attributes file itself, so the line is
self-covering.

-text rather than `text eol=lf`.  Every one of these files is already pure LF
in the working tree and in the blob, so both settings produce the same bytes
today; -text is the literal-bytes choice, it matches the idiom already in the
file, and it cannot normalise anything by surprise later.

Upstream SLP plugin directories stay uncovered, deliberately, as the existing
comment says.

-----------------------------------------------------------------------------
EXPECTED OUTPUT
-----------------------------------------------------------------------------
Printed by this script and asserted by Publish-Step14.ps1.  Run:

    cd D:\\Temp\\Projects\\GitHub\\slp-plugins
    python build\\build-v018.py

Writes build/out18/.
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "class.slp_avalon.php": "d4cba5d011c248e3d1c3af9c7cfd8067",
    "slp_avalon.php": "aa55d35db27bbf5cb206f3e0570a505c",
    ".gitattributes": "4ecda2243f179695cb31942fcbe9634d",
}


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


# ---------------------------------------------------------------------------
# EDIT 1 - register the filter, last line of add_actions().
# ---------------------------------------------------------------------------

REG_OLD = (
    b"\r\n            add_action('slp_csv_processing_complete',"
    b"array(self::$instance,'avalon_flush_import_log'),500,0);\r\n"
    b"        }\r\n"
)

REG_NEW = crlf([
    b"",
    b"            add_action('slp_csv_processing_complete',"
    b"array(self::$instance,'avalon_flush_import_log'),500,0);",
    b"            // SLP Dealer Guard, REST disclosure. Issue 33.",
    b"            //",
    b"            // add_filter, not add_action: rest_post_dispatch passes the",
    b"            // response through and expects it back. Registered as an",
    b"            // action the callback would still run and its return value",
    b"            // would be discarded - the key would go out and nothing",
    b"            // would report a fault.",
    b"            //",
    b"            // Priority 999 for the reason territory_gate is at 20: the",
    b"            // strip must be the last thing to touch the payload.",
    b"            // accepted_args 3, because the callback reads the route off",
    b"            // $request; a registration passing fewer leaves it null and",
    b"            // strips nothing, silently. suite-v018 asserts all three.",
    b"            add_filter('rest_post_dispatch',"
    b"array(self::$instance,'avalon_rest_strip_keys'),999,3);",
    b"        }",
    b"",
])


# ---------------------------------------------------------------------------
# EDIT 2 - the methods, appended after territory_gate().
# ---------------------------------------------------------------------------

TAIL_OLD = (
    b"\r\n            return $results;\r\n"
    b"        }\r\n"
    b"    }\r\n"
    b"}"
)

METHODS = crlf([
    b"",
    b"            return $results;",
    b"        }",
    b"",
    b"        /**",
    b"         * The option slugs that never travel to an unentitled caller.",
    b"         *",
    b"         * Two, and closed at two: google_server_key and google_geocode_key",
    b"         * are the only SmartOptions key reads in this file, at lines 152,",
    b"         * 153, 155 and 879. On Aura DEV 2026-09-05 both slugs held the",
    b"         * SAME 39-character value - one unrestricted key wearing two",
    b"         * names, which is the /options/all face of the key-split item.",
    b"         *",
    b"         * Public so suite-v018 can read the list rather than restate it.",
    b"         *",
    b"         * @return array",
    b"         */",
    b"        public function avalon_rest_protected_slugs(){",
    b"            return array(",
    b"                'google_server_key',",
    b"                'google_geocode_key',",
    b"            );",
    b"        }",
    b"",
    b"        /**",
    b"         * Strip the Google API keys from Store Locator Plus REST output.",
    b"         *",
    b"         * Filter on rest_post_dispatch at priority 999. Measured on Aura",
    b"         * DEV 2026-09-05: an anonymous GET of",
    b"         * /wp-json/store-locator-plus/v1/options/all returns HTTP 201 and",
    b"         * 10,539 bytes carrying the Google key twice. The v2 namespace",
    b"         * returns the identical bytes.",
    b"         *",
    b"         * SCOPE, decision 62. The route test is the /store-locator-plus/",
    b"         * PREFIX, not the v1 namespace. This install registers three SLP",
    b"         * namespaces - v1, v2, and a bare store-locator-plus carrying the",
    b"         * report routes. A v1-only test closes one of the two leaking",
    b"         * routes and leaves the other open.",
    b"         *",
    b"         * TWO LIMBS, decision 63, because the secret arrives two ways.",
    b"         *",
    b"         *   Payload limb - unset any protected key wherever it appears in",
    b"         *   the data, at any depth. The measured payload is",
    b"         *   store-locator-le -> settings -> options -> a flat map of 120",
    b"         *   slugs, every leaf at depth four. Walking by NAME rather than",
    b"         *   by that path means a future route serialising SmartOptions",
    b"         *   differently is covered without another edit.",
    b"         *",
    b"         *   Route limb - /options/<slug> and /options/filtered/<slug> name",
    b"         *   the option in the ROUTE and return it in a generically named",
    b"         *   field, so a name-keyed walk cannot see it. Both return HTTP",
    b"         *   500 today, on v1 and v2 alike, which is exactly why the shape",
    b"         *   of a working response cannot be measured and the limb cannot",
    b"         *   be keyed on field names. Protected slug in, empty body out.",
    b"         *",
    b"         * WHY THIS HOOK. Read out of wp-includes/rest-api/",
    b"         * class-wp-rest-server.php: rest_post_dispatch fires in",
    b"         * serve_request() at 463, in embedded-resource resolution at 823,",
    b"         * and once per sub-request in the batch endpoint at 1893.",
    b"         * dispatch() applies only rest_pre_dispatch, so internal",
    b"         * rest_do_request() calls do NOT pass through here and",
    b"         * server-side consumers keep the key. The batch site is why this",
    b"         * hook beats rest_pre_echo_response: there the filter runs with",
    b"         * $single_request, so get_route() is still the SLP route, where",
    b"         * rest_pre_echo_response would see /batch/v1 and the namespace",
    b"         * test would miss. Batch is opt-in per route - allow_batch['v1'],",
    b"         * line 1801 - and SLP has not opted in, so that is coverage held",
    b"         * in reserve, not a live hole closed.",
    b"         *",
    b"         * $server and $request default to null so that a mis-wired",
    b"         * registration cannot fatal on every REST response the site",
    b"         * serves. It fails CLOSED on its own behaviour and OPEN on the",
    b"         * secret, which is the wrong way round for a security control -",
    b"         * so the wiring is asserted in suite-v018 against the artefact",
    b"         * text, and again after deploy by an anonymous curl. A green",
    b"         * suite is not evidence that this filter ran.",
    b"         *",
    b"         * @param  mixed $result  WP_REST_Response, or a WP_Error.",
    b"         * @param  mixed $server  WP_REST_Server. Unused.",
    b"         * @param  mixed $request WP_REST_Request.",
    b"         * @return mixed",
    b"         */",
    b"        public function avalon_rest_strip_keys( $result, $server = null, $request = null ){",
    b"            if ( ! ( $result instanceof WP_REST_Response ) ) {",
    b"                return $result;",
    b"            }",
    b"            if ( ! ( $request instanceof WP_REST_Request ) ) {",
    b"                return $result;",
    b"            }",
    b"",
    b"            $route = $request->get_route();",
    b"            if ( ! is_string( $route ) || strpos( $route, '/store-locator-plus/' ) !== 0 ) {",
    b"                return $result;",
    b"            }",
    b"",
    b"            // After the route test, never before it: current_user_can() is",
    b"            // not free and this callback sees every REST response.",
    b"            if ( current_user_can( 'manage_slp_user' ) ) {",
    b"                return $result;",
    b"            }",
    b"",
    b"            $slugs = $this->avalon_rest_protected_slugs();",
    b"",
    b"            // Route limb. 'all' and 'import' are slugs too and are not",
    b"            // protected, so they fall through to the payload limb.",
    b"            $matched = array();",
    b"            if ( preg_match( '#/options/(?:filtered/)?([A-Za-z0-9_]+)#', $route, $matched )",
    b"                 && in_array( $matched[1], $slugs, true ) ) {",
    b"                $result->set_data( array() );",
    b"                return $result;",
    b"            }",
    b"",
    b"            // Payload limb. set_data() only when something actually moved,",
    b"            // so the common case is a walk and no write.",
    b"            $removed = 0;",
    b"            $data    = $this->avalon_rest_strip_walk( $result->get_data(), $slugs, 0, $removed );",
    b"            if ( $removed > 0 ) {",
    b"                $result->set_data( $data );",
    b"            }",
    b"",
    b"            return $result;",
    b"        }",
    b"",
    b"        /**",
    b"         * Remove protected keys from a response body, by name, at any depth.",
    b"         *",
    b"         * Recurses into arrays and stdClass only. Any other object is left",
    b"         * exactly as it is - a REST payload can carry objects that are not",
    b"         * plain data, and walking into them is how a filter turns a",
    b"         * disclosure fix into an outage.",
    b"         *",
    b"         * Depth is capped at 10. The measured payload bottoms out at 4.",
    b"         *",
    b"         * @param  mixed $node",
    b"         * @param  array $slugs",
    b"         * @param  int   $depth",
    b"         * @param  int   $removed  By reference. Count of keys unset.",
    b"         * @return mixed",
    b"         */",
    b"        private function avalon_rest_strip_walk( $node, $slugs, $depth, &$removed ){",
    b"            if ( $depth > 10 ) {",
    b"                return $node;",
    b"            }",
    b"",
    b"            if ( is_array( $node ) ) {",
    b"                foreach ( $node as $key => $value ) {",
    b"                    if ( is_string( $key ) && in_array( $key, $slugs, true ) ) {",
    b"                        unset( $node[ $key ] );",
    b"                        $removed++;",
    b"                        continue;",
    b"                    }",
    b"                    if ( is_array( $value ) || ( $value instanceof stdClass ) ) {",
    b"                        $node[ $key ] = $this->avalon_rest_strip_walk( $value, $slugs, $depth + 1, $removed );",
    b"                    }",
    b"                }",
    b"                return $node;",
    b"            }",
    b"",
    b"            if ( $node instanceof stdClass ) {",
    b"                foreach ( get_object_vars( $node ) as $key => $value ) {",
    b"                    if ( in_array( $key, $slugs, true ) ) {",
    b"                        unset( $node->$key );",
    b"                        $removed++;",
    b"                        continue;",
    b"                    }",
    b"                    if ( is_array( $value ) || ( $value instanceof stdClass ) ) {",
    b"                        $node->$key = $this->avalon_rest_strip_walk( $value, $slugs, $depth + 1, $removed );",
    b"                    }",
    b"                }",
    b"                return $node;",
    b"            }",
    b"",
    b"            return $node;",
    b"        }",
    b"    }",
    b"}",
])


# ---------------------------------------------------------------------------
# EDIT 3 - version header.
# ---------------------------------------------------------------------------

PHP_OLD = b" * Version: 0.0.17\r\n"
PHP_NEW = b" * Version: 0.0.18\r\n"


# ---------------------------------------------------------------------------
# EDIT 4 - .gitattributes. LF file, not CRLF. rev17 s0.53.
# ---------------------------------------------------------------------------

GITATTR_OLD = b"slp_avalon/** -text\n"

GITATTR_NEW = (
    b"slp_avalon/** -text\n"
    b"#\n"
    b"# The build, test and publish tooling is verified by md5 the same way, and\n"
    b"# release-pins.csv pins .gitattributes itself. Without these four lines a\n"
    b"# fresh clone under core.autocrlf=true checks all of them out as CRLF and\n"
    b"# every hash in Publish-Step14's tables is wrong before it starts.\n"
    b"build/** -text\n"
    b"test/** -text\n"
    b"*.ps1 -text\n"
    b".gitattributes -text\n"
)


# Real repo-relative locations, from the local GitHub inventory:
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\slp_avalon.php
#   D:\\Temp\\Projects\\GitHub\\slp-plugins\\.gitattributes
IN_PATHS = {
    "class.slp_avalon.php": Path("slp_avalon") / "inc" / "class.slp_avalon.php",
    "slp_avalon.php": Path("slp_avalon") / "slp_avalon.php",
    ".gitattributes": Path(".gitattributes"),
}

EXPECT_CLS_BYTES = 93499
EXPECT_CLS_CRLF = 2038
EXPECT_PHP_BYTES = 1808
EXPECT_PHP_CRLF = 59
EXPECT_ATTR_BYTES = 816
EXPECT_CLS_DELTA = 8815


def main() -> None:
    repo = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
    out = Path(sys.argv[2]) if len(sys.argv) > 2 else repo / "build" / "out18"

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
    attr = (repo / IN_PATHS[".gitattributes"]).read_bytes()

    for name, blob in (("class.slp_avalon.php", cls),
                       ("slp_avalon.php", php),
                       (".gitattributes", attr)):
        got = md5(blob)
        if got != SRC_MD5[name]:
            raise SystemExit(f"INPUT MD5 FAIL {name}: {got} != {SRC_MD5[name]}")
        print(f"  input  ok  {name:22} {got}")

    before = len(cls)

    cls = sub_once(cls, REG_OLD, REG_NEW, "register rest_post_dispatch")
    print("  edit   ok  1  filter registered at 999, accepted_args 3")

    cls = sub_once(cls, TAIL_OLD, METHODS, "append the strip methods")
    print("  edit   ok  2  avalon_rest_strip_keys + walk + slug list appended")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  3  version header 0.0.17 -> 0.0.18")

    attr = sub_once(attr, GITATTR_OLD, GITATTR_NEW, "gitattributes tooling cover")
    print("  edit   ok  4  .gitattributes covers build/, test/, *.ps1, itself")

    # --- line endings ----------------------------------------------------
    assert cls.count(b"\r\n") == cls.count(b"\n"), "stray bare LF in class file"
    assert php.count(b"\r\n") == php.count(b"\n"), "stray bare LF in plugin file"
    assert not cls.endswith(b"\n"), "trailing newline introduced in class file"
    # .gitattributes is an LF file and must stay one. A CRLF slipping in here
    # would be invisible in an editor and would change the pinned md5.
    assert attr.count(b"\r") == 0, "CR introduced into .gitattributes"
    assert attr.endswith(b"\n"), ".gitattributes lost its trailing newline"

    # --- EDIT 1, the registration ----------------------------------------
    # The whole point of this release is a filter that runs. rev16 s0.38: a
    # wiring defect is invisible to a callback test, so it is asserted here as
    # literal bytes and again in suite-v018 against the artefact.
    assert cls.count(
        b"add_filter('rest_post_dispatch',"
        b"array(self::$instance,'avalon_rest_strip_keys'),999,3);"
    ) == 1, "the filter is not registered exactly once at 999 with 3 args"
    assert cls.count(b"add_action('rest_post_dispatch'") == 0, \
        "registered as an action - the return value would be discarded"
    assert cls.count(b"'rest_post_dispatch'") == 1, \
        "the hook name appears somewhere other than the registration"

    # --- EDIT 2, the methods ---------------------------------------------
    for fn in (
        b"public function avalon_rest_protected_slugs(){",
        b"public function avalon_rest_strip_keys( $result, $server = null, $request = null ){",
        b"private function avalon_rest_strip_walk( $node, $slugs, $depth, &$removed ){",
    ):
        assert cls.count(fn) == 1, f"one definition expected: {fn.decode()}"

    # Both slugs, once each in the list. Twice more in the docblocks would be a
    # different count, so the list is asserted as a whole block instead.
    assert cls.count(
        b"            return array(\r\n"
        b"                'google_server_key',\r\n"
        b"                'google_geocode_key',\r\n"
        b"            );\r\n"
    ) == 1, "the protected slug list is not present exactly once"

    # Decision 62. The prefix, not the namespace.
    assert cls.count(b"strpos( $route, '/store-locator-plus/' ) !== 0") == 1, \
        "the route test is not the /store-locator-plus/ prefix"
    assert cls.count(b"'/store-locator-plus/v1'") == 0, \
        "a v1-only route test survives - v2 leaks the identical payload"

    # Decision 63. Both limbs present.
    assert cls.count(
        b"preg_match( '#/options/(?:filtered/)?([A-Za-z0-9_]+)#', $route, $matched )"
    ) == 1, "the route limb is missing"
    assert cls.count(b"$result->set_data( array() );") == 1, \
        "the route limb does not blank the body"

    # Decision 64. unset(), and no blanking of the two slugs anywhere.
    assert cls.count(b"unset( $node[ $key ] );") == 1
    assert cls.count(b"unset( $node->$key );") == 1
    assert cls.count(b"'google_server_key' => ''") == 0, "a blanking write survives"

    # The capability, and its ordering. current_user_can must be inside the
    # method and after the route test, so it is not called on every REST
    # response the site serves.
    assert cls.count(b"current_user_can( 'manage_slp_user' )") == 1
    assert cls.index(b"strpos( $route, '/store-locator-plus/' ) !== 0") \
        < cls.index(b"current_user_can( 'manage_slp_user' )"), \
        "the capability check runs before the route test"

    # Recursion is fenced. Walking an arbitrary object is how a disclosure fix
    # becomes an outage.
    assert cls.count(b"if ( $depth > 10 ) {") == 1, "the depth cap is gone"
    assert cls.count(b"$value instanceof stdClass") == 2, \
        "the array and object branches must each fence their recursion"

    # --- nothing from v0.0.17 or earlier moved ---------------------------
    assert cls.count(b"'avalon_flush_import_log'),500,0);") == 1, \
        "v0.0.16's accepted_args=0 was lost"
    assert cls.count(b"'avalon_import_coordinate_guard'),20,1);") == 1, \
        "the coordinate guard registration disturbed"
    assert cls.count(b"'territory_gate'),20,1);") == 1, \
        "the Layer 3 territory gate registration disturbed"
    assert cls.count(b"AVALON_TIER2_MAX_CORRECTIONS  : 60,") == 1, \
        "v0.0.17's cap of 60 was lost"
    assert cls.count(b": 25,") == 0, "a `: 25,` default came back"
    assert cls.count(b"overrides_rotated") == 3, \
        "v0.0.17's rotation state key: read, write, docblock"
    assert cls.count(
        b"$address2 = isset($location['sl_address2']) ? $location['sl_address2'] : '';"
    ) == 1, "v0.0.17's guarded sl_address2 read was lost"
    assert cls.count(b"'DONNIE MARCH|HOWELL|MI',") == 1, "DONNIE MARCH excluded"
    assert cls.count(b"'C/O COLE INTERNATIONAL USA|PEMBINA|ND',") == 1, "Cole excluded"
    assert cls.count(b"public function territory_gate( $results ){") == 1
    assert cls.count(b"public function is_in_territory( $lat, $lng ){") == 1

    # --- registrations are still at twelve spaces -------------------------
    for line in cls.split(b"\r\n"):
        if b"avalon_rest_strip_keys'),999,3);" in line \
           or b"avalon_flush_import_log'),500,0);" in line:
            assert line.startswith(b"            add_"), \
                f"registration at wrong indent: {line!r}"

    # --- EDIT 3 ------------------------------------------------------------
    assert php.count(b"0.0.18") == 1
    assert php.count(b"0.0.17") == 0

    # --- EDIT 4 ------------------------------------------------------------
    for rule in (b"\nbuild/** -text\n", b"\ntest/** -text\n",
                 b"\n*.ps1 -text\n", b"\n.gitattributes -text\n"):
        assert attr.count(rule) == 1, f"missing or duplicated rule: {rule!r}"
    assert attr.count(b"slp_avalon/** -text\n") == 1, \
        "the original slp_avalon rule was disturbed"
    assert b"store-locator-le" not in attr, \
        "upstream SLP directories must stay uncovered"

    # --- byte-exact expectations ------------------------------------------
    cls_crlf = cls.count(b"\r\n")
    php_crlf = php.count(b"\r\n")
    delta = len(cls) - before

    if EXPECT_CLS_BYTES:
        assert delta == EXPECT_CLS_DELTA, \
            f"class file grew {delta} bytes, expected {EXPECT_CLS_DELTA}"
        assert len(cls) == EXPECT_CLS_BYTES, \
            f"class file {len(cls)} bytes, expected {EXPECT_CLS_BYTES}"
        assert cls_crlf == EXPECT_CLS_CRLF, \
            f"class file CRLF={cls_crlf}, expected {EXPECT_CLS_CRLF}"
        assert len(attr) == EXPECT_ATTR_BYTES, \
            f".gitattributes {len(attr)} bytes, expected {EXPECT_ATTR_BYTES}"
    else:
        print(f"  MEASURE    delta={delta}  cls={len(cls)}  crlf={cls_crlf}  "
              f"attr={len(attr)}")

    assert len(php) == EXPECT_PHP_BYTES, \
        f"plugin file {len(php)} bytes, expected {EXPECT_PHP_BYTES}"
    assert php_crlf == EXPECT_PHP_CRLF, \
        f"plugin file CRLF={php_crlf}, expected {EXPECT_PHP_CRLF}"

    print("  self-check ok")

    (out / "class.slp_avalon.php").write_bytes(cls)
    (out / "slp_avalon.php").write_bytes(php)
    (out / ".gitattributes").write_bytes(attr)

    for name, blob in (("class.slp_avalon.php", cls),
                       ("slp_avalon.php", php),
                       (".gitattributes", attr)):
        n = blob.count(b"\r\n")
        print(f"  output     {name:22} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={n}")


if __name__ == "__main__":
    main()
