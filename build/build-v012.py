#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.12 from the shipped v0.0.11 artefacts.

State and province name search. The first PHP change since Step 2, and the
first build in this series that does not touch slp_avalon.js - its md5 must
come out of this unchanged at a6237b4f2c006964710f4b5362437c66.

WHAT WAS ACTUALLY BROKEN, measured against Aura DEV admin-ajax.php rather
than read off the source:

    address=Michigan   ->  count 35
    address=MICHIGAN   ->  count 3

is_state() normalises with ucwords(), which upper-cases the first letter of
each word and leaves the rest alone, so ucwords("MICHIGAN") is "MICHIGAN" and
the in_array() against a table of "Michigan" fails. The search field renders
in caps - style.css .store_locator_plus input[type="text"]{text-transform:
uppercase} - so all-caps input is what the UI invites. Every state search
typed in caps has been silently returning 3 results instead of the state's
dealers.

That single defect is why the province work could not have landed on its own.
is_state() has TWO callers, not one:

    slp_ajaxsql_queryparams()                984-994, priority 999
        $parameters[4] = 50   - raises the SQL LIMIT from 3 to 50
    slp_ajax_find_locations_complete_filter() 172-190, priority 10
        narrows those rows to the searched state

The limit bump is the load-bearing half. At the Ontario centroid the three
nearest dealers are all in Michigan, so a filter applied to three rows has
nothing to keep; the fifty nearest are what contain Orono and Washago.
Correcting the comparison at 182 alone would have changed nothing, which is
also why "Ontario", "ONTARIO" and "Ontario, Canada" all returned an identical
payload before this build.

FOUR EDITS, one concern:

  1. normalize_search_address()   new. The munging at 174-177 and 986-989 was
     duplicated verbatim and had already diverged - only the second copy
     trimmed. Both feed is_state(), so a divergence means the limit goes to 50
     while the filter meant to narrow those 50 rows never runs. Now one
     function, called from both sites, with the Canada suffix added.

  2. get_states()                 + 13 Canadian provinces and territories.
     No key collides with the 51 existing entries; all 13 were checked.

  3. is_state() / get_state_initial()
     Case-insensitive lookup keyed on the lower-cased name. is_state() now
     delegates to get_state_initial() so the two cannot drift apart again -
     they previously duplicated the ucwords() call and would both have needed
     the same fix.

  4. The comparison at 182        $loc['state'] is stored inconsistently. Live
     values include MI, NH, and also NEW HAMPSHIRE, DELAWARE and ONTARIO. A
     code-only compare misses same-state dealers held under the full name; a
     "New Hampshire" search from New Hampshire coordinates returns a mix today
     because the full-name rows fail the filter and are only re-admitted by
     the distance-ranked backfill.

DELIBERATELY NOT DONE:

  - Bare two-letter codes are NOT accepted. IN, OR, OK, HI, ME, DE, LA, MA,
    MS, MT and CO are ordinary English words; "or" triggering an Oregon state
    query is worse than the gap it closes.
  - The five US territories (PR, VI, GU, MP, AS) are not added. Handoff s0.9
    established that none of them contains a dealer, so adding them changes no
    output and cannot be regression-tested. Backlog, with Issue 11's copy.
  - The backfill at 192-226 is untouched. Owner's call, consistent with
    Decision 24: a state search yielding fewer than three still fills to three
    with the nearest dealers.

DEVIATION FROM HANDOFF s7.6, flagged rather than worked around. s7.6 proposed

    if ( $state_name === $stateInitial || strcasecmp( $state_name, $address ) === 0 )

comparing the record against the visitor's typed string. This build compares
against the CANONICAL name from get_states() instead. Typing "Quebec" and
typing "Quebec" with the accent must both match a record stored as QUEBEC,
and strcasecmp() does not fold accents. Deriving the name from the table
removes the dependency on what was typed. s7.6's `(string)` cast is kept - it
is what stops PHP 8.4 deprecating strcasecmp(null, ...) on the 25 of 308
records with malformed state values, and handoff s10.6 notes error.log is
publicly reachable.

Usage:  python3 build-v012.py <src_dir> <out_dir>
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "class.slp_avalon.php": "defbb41312071472a7039da37651a0d4",
    "slp_avalon.php": "468bd00d86796abb8220fbf828b45f83",
}

# Not an input to any edit. Asserted present and unchanged so that a build run
# from the wrong directory cannot quietly ship a stale JS artefact alongside
# correct PHP.
JS_MD5 = "a6237b4f2c006964710f4b5362437c66"


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


def strip_php_comments(blob: bytes) -> bytes:
    """
    Return the file with // # and block comments removed, string literals kept.

    Exists because of a failure this series keeps repeating. Handoff s9 says
    build assertions must match exact code forms and never bare identifiers,
    because a replacement comment quotes the expression it retired so the next
    reader knows not to reintroduce it - and then the assertion counts the
    comment. v0.0.9, v0.0.10 and v0.0.11 each tripped their own self-check that
    way, and so did the first two runs of this one, on ucwords.

    Writing the rule down did not stop it happening, so this removes the
    ambiguity instead: an "is it gone" assertion runs against code only, and a
    comment can quote whatever it needs to.

    Used for assertions ONLY. The artefact is written from the untouched
    bytes, so a defect in this parser can fail a build but can never ship one.
    """
    out = bytearray()
    i, n, state = 0, len(blob), "code"
    while i < n:
        c, two = blob[i:i + 1], blob[i:i + 2]
        if state == "code":
            if two == b"//" or c == b"#":
                state = "line"
            elif two == b"/*":
                state = "block"
                i += 2
                continue
            elif c in (b"'", b'"'):
                state = "sq" if c == b"'" else "dq"
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
        if (state == "sq" and c == b"'") or (state == "dq" and c == b'"'):
            state = "code"
        i += 1
    return bytes(out)


# --------------------------------------------------------------- edit 1
# Munge site A, inside slp_ajax_find_locations_complete_filter(). The five
# lines are byte-identical to site B except that B also trims, so the anchor
# has to carry the following `if ($this->is_state` line to be unique.
A1_OLD = (
    b"                $address  = $_POST['address'];\r\n"
    b'                $address = str_replace(", USA","",$address);\r\n'
    b'                $address = str_replace(",USA","",$address);\r\n'
    b'                $address = str_replace(" USA","",$address);\r\n'
    b'                $address = str_replace(",","",$address);\r\n'
    b"                if ($this->is_state($address)){\r\n"
)
A1_NEW = (
    b"                //Was five str_replace lines duplicated verbatim in\r\n"
    b"                //slp_ajaxsql_queryparams(). The copies had diverged - only\r\n"
    b"                //that one trimmed - and both feed is_state(), so the\r\n"
    b"                //divergence could raise the SQL limit to 50 while this\r\n"
    b"                //filter, the thing meant to narrow those 50 rows, sat out.\r\n"
    b"                $address = $this->normalize_search_address($_POST['address']);\r\n"
    b"                if ($this->is_state($address)){\r\n"
)

# --------------------------------------------------------------- edit 2
# Munge site B, inside slp_ajaxsql_queryparams(). Unique by its trim() line.
A2_OLD = (
    b"                $address  = $_POST['address'];\r\n"
    b'                $address = str_replace(", USA","",$address);\r\n'
    b'                $address = str_replace(",USA","",$address);\r\n'
    b'                $address = str_replace(" USA","",$address);\r\n'
    b'                $address = str_replace(",","",$address);\r\n'
    b"                $address = trim($address);\r\n"
)
A2_NEW = (
    b"                //Same normalisation as the priority-10 filter, from the\r\n"
    b"                //same function on purpose. If these two ever disagree\r\n"
    b"                //about what counts as a state name, the limit and the\r\n"
    b"                //filter disagree with it.\r\n"
    b"                $address = $this->normalize_search_address($_POST['address']);\r\n"
)

# --------------------------------------------------------------- edit 3
A3_OLD = (
    b"                    foreach ($results['response'] as $k=>$loc){\r\n"
    b"                        if ($loc['state'] == $stateInitial){\r\n"
)
A3_NEW = (
    b"                    //The canonical name for the code we matched. Derived\r\n"
    b"                    //from the table rather than from $address because\r\n"
    b"                    //strcasecmp() does not fold accents: a visitor typing\r\n"
    b"                    //Quebec with its accent and one typing it without must\r\n"
    b"                    //both match a record stored as QUEBEC.\r\n"
    b"                    $states     = $this->get_states();\r\n"
    b"                    $state_full = isset($states[$stateInitial])\r\n"
    b"                        ? $states[$stateInitial]\r\n"
    b"                        : '';\r\n"
    b"                    foreach ($results['response'] as $k=>$loc){\r\n"
    b"                        //sl_state is stored inconsistently. Live values on\r\n"
    b"                        //Aura DEV include MI and NH but also NEW HAMPSHIRE,\r\n"
    b"                        //DELAWARE and ONTARIO. A code-only compare drops a\r\n"
    b"                        //dealer that IS in the searched state, and the\r\n"
    b"                        //distance-ranked backfill then re-admits it in the\r\n"
    b"                        //wrong position or not at all.\r\n"
    b"                        //\r\n"
    b"                        //Cast before compare: 25 of 308 records carry a\r\n"
    b"                        //malformed state and strcasecmp(null, ...) is\r\n"
    b"                        //deprecated on PHP 8.4. error.log is publicly\r\n"
    b"                        //reachable, so deprecation spam is not free.\r\n"
    b"                        $state_name = (string) (isset($loc['state']) ? $loc['state'] : '');\r\n"
    b"                        if (\r\n"
    b"                            strcasecmp($state_name, (string) $stateInitial) === 0 ||\r\n"
    b"                            ($state_full !== '' && strcasecmp($state_name, $state_full) === 0)\r\n"
    b"                        ){\r\n"
)

# --------------------------------------------------------------- edit 4
# Table tail plus both helpers, replaced as one block so the provinces and the
# lookup that reads them cannot land separately.
A4_OLD = (
    b"                'WY'=>\"Wyoming\"\r\n"
    b"            );\r\n"
    b"            return $state_list;\r\n"
    b"        }\r\n"
    b"        public function is_state($string){\r\n"
    b"            $states = $this->get_states();\r\n"
    b"            return in_array(ucwords($string),$states);\r\n"
    b"        }\r\n"
    b"        public function get_state_initial($state_name){\r\n"
    b"            $key = array_search(ucwords($state_name),$this->get_states());\r\n"
    b"            if ($key !== false){\r\n"
    b"                return $key;\r\n"
    b"            }\r\n"
    b"            return false;\r\n"
    b"        }\r\n"
)
A4_NEW = (
    b"                'WY'=>\"Wyoming\",\r\n"
    b"                /* Canadian provinces and territories, v0.0.12. Issue 10.\r\n"
    b"                 *\r\n"
    b"                 * Confirmed necessary by live data, not assumed: the\r\n"
    b"                 * nearest dealer to Toronto stores its state as ONTARIO,\r\n"
    b"                 * full name and upper case, and before this build a search\r\n"
    b"                 * for the province was not recognised at all - so the SQL\r\n"
    b"                 * limit stayed at 3 and the three nearest dealers to the\r\n"
    b"                 * provincial centroid, all in Michigan, were the answer.\r\n"
    b"                 *\r\n"
    b"                 * None of these two-letter keys collides with the 51 US\r\n"
    b"                 * entries above. All 13 were checked against that list. */\r\n"
    b"                'AB'=>\"Alberta\",\r\n"
    b"                'BC'=>\"British Columbia\",\r\n"
    b"                'MB'=>\"Manitoba\",\r\n"
    b"                'NB'=>\"New Brunswick\",\r\n"
    b"                'NL'=>\"Newfoundland and Labrador\",\r\n"
    b"                'NS'=>\"Nova Scotia\",\r\n"
    b"                'NT'=>\"Northwest Territories\",\r\n"
    b"                'NU'=>\"Nunavut\",\r\n"
    b"                'ON'=>\"Ontario\",\r\n"
    b"                'PE'=>\"Prince Edward Island\",\r\n"
    b"                'QC'=>\"Quebec\",\r\n"
    b"                'SK'=>\"Saskatchewan\",\r\n"
    b"                'YT'=>\"Yukon\"\r\n"
    b"            );\r\n"
    b"            return $state_list;\r\n"
    b"        }\r\n"
    b"\r\n"
    b"        /**\r\n"
    b"         * Spellings that are not the canonical name but mean one.\r\n"
    b"         *\r\n"
    b"         * Kept separate from get_states() because that array is code =>\r\n"
    b"         * name and must stay one entry per code - get_state_initial()\r\n"
    b"         * reverses it, and a second Quebec would make which code wins\r\n"
    b"         * depend on insertion order.\r\n"
    b"         *\r\n"
    b"         * Deliberately does NOT include bare two-letter codes. IN, OR, OK,\r\n"
    b"         * HI, ME, DE, LA, MA, MS, MT and CO are ordinary English words, and\r\n"
    b"         * a visitor typing \"or\" being sent to Oregon is a worse failure\r\n"
    b"         * than not recognising \"OR\" as a state.\r\n"
    b"         *\r\n"
    b"         * @return array  lower-cased spelling => code\r\n"
    b"         */\r\n"
    b"        public function get_state_aliases(){\r\n"
    b"            return array(\r\n"
    b"                //Google returns the accented form under a French locale.\r\n"
    b"                \"qu\\xc3\\xa9bec\"          => 'QC',\r\n"
    b"                'newfoundland'      => 'NL',\r\n"
    b"                'yukon territory'   => 'YT',\r\n"
    b"            );\r\n"
    b"        }\r\n"
    b"\r\n"
    b"        /**\r\n"
    b"         * Lower-cased name => code, built once per request.\r\n"
    b"         *\r\n"
    b"         * @return array\r\n"
    b"         */\r\n"
    b"        public function get_state_lookup(){\r\n"
    b"            static $lookup = null;\r\n"
    b"            if ( $lookup === null ) {\r\n"
    b"                $lookup = array();\r\n"
    b"                foreach ( $this->get_states() as $code => $name ) {\r\n"
    b"                    $lookup[ strtolower( $name ) ] = $code;\r\n"
    b"                }\r\n"
    b"                foreach ( $this->get_state_aliases() as $spelling => $code ) {\r\n"
    b"                    $lookup[ strtolower( $spelling ) ] = $code;\r\n"
    b"                }\r\n"
    b"            }\r\n"
    b"            return $lookup;\r\n"
    b"        }\r\n"
    b"\r\n"
    b"        /**\r\n"
    b"         * Strip the country suffix and punctuation from a search string.\r\n"
    b"         *\r\n"
    b"         * One function because the five str_replace lines it replaces were\r\n"
    b"         * duplicated in slp_ajax_find_locations_complete_filter() and\r\n"
    b"         * slp_ajaxsql_queryparams(), and had already diverged.\r\n"
    b"         *\r\n"
    b"         * The suffix is ANCHORED to the end of the string. The old\r\n"
    b"         * str_replace(\" USA\",...) matched anywhere, which was harmless for\r\n"
    b"         * USA but would turn \"La Canada Flintridge\" into \"La Flintridge\"\r\n"
    b"         * once Canada joined it. Case-insensitive because the field renders\r\n"
    b"         * in caps and Google writes \"Ontario, Canada\" into it on an\r\n"
    b"         * autocomplete selection.\r\n"
    b"         *\r\n"
    b"         * @param  mixed $raw  $_POST['address']. Always set: slp_core.js\r\n"
    b"         *                     1809 posts saneValue(\"addressInput\",\r\n"
    b"         *                     \"no address entered\").\r\n"
    b"         * @return string\r\n"
    b"         */\r\n"
    b"        public function normalize_search_address( $raw ){\r\n"
    b"            $address  = (string) $raw;\r\n"
    b"            $stripped = preg_replace(\r\n"
    b"                '/\\\\s*,?\\\\s*(?:USA|U\\\\.S\\\\.A\\\\.|United States|Canada)\\\\s*$/i',\r\n"
    b"                '',\r\n"
    b"                $address\r\n"
    b"            );\r\n"
    b"            //preg_replace returns null only on a PCRE error. Falling back to\r\n"
    b"            //the unstripped string keeps a pathological input searchable\r\n"
    b"            //instead of turning it into an empty query.\r\n"
    b"            if ( $stripped !== null ) {\r\n"
    b"                $address = $stripped;\r\n"
    b"            }\r\n"
    b"            return trim( str_replace( ',', '', $address ) );\r\n"
    b"        }\r\n"
    b"\r\n"
    b"        /**\r\n"
    b"         * @param  string $string\r\n"
    b"         * @return bool\r\n"
    b"         */\r\n"
    b"        public function is_state($string){\r\n"
    b"            //Delegates rather than repeating the lookup. The previous pair\r\n"
    b"            //each called ucwords() separately, so the case defect had to be\r\n"
    b"            //fixed in two places or not at all.\r\n"
    b"            return $this->get_state_initial( $string ) !== false;\r\n"
    b"        }\r\n"
    b"\r\n"
    b"        /**\r\n"
    b"         * @param  string $state_name\r\n"
    b"         * @return string|false  the two-letter code, or false\r\n"
    b"         */\r\n"
    b"        public function get_state_initial($state_name){\r\n"
    b"            //Was array_search(ucwords($state_name), ...). ucwords() upper-\r\n"
    b"            //cases the first letter of each word and leaves the rest, so\r\n"
    b"            //ucwords(\"MICHIGAN\") is \"MICHIGAN\" and never matched the table.\r\n"
    b"            //Measured on Aura DEV: address=Michigan returned 35 results,\r\n"
    b"            //address=MICHIGAN returned 3.\r\n"
    b"            $lookup = $this->get_state_lookup();\r\n"
    b"            $key    = strtolower( trim( (string) $state_name ) );\r\n"
    b"            return isset( $lookup[ $key ] ) ? $lookup[ $key ] : false;\r\n"
    b"        }\r\n"
)

EDITS = [
    ("normalize call site A (filter)", A1_OLD, A1_NEW),
    ("normalize call site B (sql)",    A2_OLD, A2_NEW),
    ("tolerant state compare",         A3_OLD, A3_NEW),
    ("provinces + helpers",            A4_OLD, A4_NEW),
]

PHP_OLD = b" * Version: 0.0.11\r\n"
PHP_NEW = b" * Version: 0.0.12\r\n"


def main() -> None:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "out12")
    out.mkdir(parents=True, exist_ok=True)

    cls = (src / "class.slp_avalon.php").read_bytes()
    php = (src / "slp_avalon.php").read_bytes()

    for name, blob in (("class.slp_avalon.php", cls), ("slp_avalon.php", php)):
        got = md5(blob)
        if got != SRC_MD5[name]:
            raise SystemExit(f"INPUT MD5 FAIL {name}: {got} != {SRC_MD5[name]}")
        print(f"  input  ok  {name:22} {got}")

    js = src / "slp_avalon.js"
    if js.exists():
        got = md5(js.read_bytes())
        if got != JS_MD5:
            raise SystemExit(f"JS DRIFT: {got} != {JS_MD5} - wrong source tree?")
        print(f"  input  ok  {'slp_avalon.js':22} {got}  (untouched by this build)")
    else:
        print("  input  --  slp_avalon.js not in src_dir; skipped drift check")

    for label, old, new in EDITS:
        cls = sub_once(cls, old, new, label)
        print(f"  edit   ok  {label}")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  version header 0.0.11 -> 0.0.12")

    # --------------------------------------------------------- self-check
    # Presence assertions run against the real bytes. ABSENCE assertions run
    # against `code`, the comment-stripped view, so that a comment quoting the
    # expression it retired cannot fail the build. See strip_php_comments().
    code = strip_php_comments(cls)

    assert cls.count(b"\r\n") == cls.count(b"\n"), "stray bare LF introduced"
    assert not cls.endswith(b"\n"), "trailing newline introduced"

    assert cls.count(b"public function normalize_search_address( $raw ){") == 1
    assert cls.count(b"$address = $this->normalize_search_address($_POST['address']);") == 2, \
        "both call sites use the one normaliser"
    assert code.count(b'str_replace(", USA"') == 0, "the duplicated munging is gone"
    assert code.count(b"$address = trim($address);") == 0, "the diverged trim is gone"

    # Both ucwords() call sites, by their exact code form. A bare
    # `cls.count(b"ucwords(")` fails here: the replacement comments quote the
    # old expression on purpose so the next reader knows what was wrong and
    # does not reintroduce it. This is the fourth build in the series to trip
    # its own self-check on a bare identifier; handoff s9 names the rule.
    assert code.count(b"in_array(ucwords($string),$states)") == 0, \
        "is_state's ucwords lookup is gone"
    assert code.count(b"array_search(ucwords($state_name),$this->get_states())") == 0, \
        "get_state_initial's ucwords lookup is gone"
    assert code.count(b"ucwords(") == 0, "no ucwords call survives anywhere in the file"
    assert cls.count(b"public function get_state_lookup(){") == 1
    assert cls.count(b"public function get_state_aliases(){") == 1
    assert cls.count(b"return $this->get_state_initial( $string ) !== false;") == 1, \
        "is_state delegates; it must not carry its own lookup"

    assert cls.count(b"'ON'=>\"Ontario\",") == 1
    for prov in (b"AB", b"BC", b"MB", b"NB", b"NL", b"NS", b"NT",
                 b"NU", b"ON", b"PE", b"QC", b"SK", b"YT"):
        assert cls.count(b"'" + prov + b"'=>\"") == 1, f"province {prov!r} once"
    # The five territories are deliberately absent - handoff s0.9, no dealers.
    for terr in (b"PR", b"VI", b"GU", b"MP", b"AS"):
        assert cls.count(b"'" + terr + b"'=>\"") == 0, f"territory {terr!r} must NOT be added"

    assert cls.count(b"strcasecmp($state_name, (string) $stateInitial) === 0") == 1
    assert cls.count(b"strcasecmp($state_name, $state_full) === 0") == 1
    assert code.count(b"if ($loc['state'] == $stateInitial){") == 0, "old compare gone"

    # The backfill is untouched: owner's call, Decision 24.
    assert cls.count(b"if ($results['count'] >= 3) return $results;") == 1
    assert cls.count(b"if (count($results['response']) == 3) break;") == 1
    # The limit bump is the load-bearing half and must survive.
    assert cls.count(b"$parameters[4] = 50;") == 1

    assert php.count(b"0.0.12") == 1
    print("  self-check ok")

    (out / "class.slp_avalon.php").write_bytes(cls)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("class.slp_avalon.php", cls), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:22} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
