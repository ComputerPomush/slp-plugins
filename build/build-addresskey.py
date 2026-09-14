#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
build-addresskey.py  --  SLP Dealer Guard, v0.0.26 Part 2, step 3.

Generates slp_avalon/inc/class.slp_avalon_addresskey.php: the PHP half of the
cross-language address-key contract described in rev34 s0.193.

NOTHING IN THE OUTPUT IS HAND-TYPED.  Every table the PHP uses is derived, at
build time, from one of exactly two sources:

  1. resolve-placeids.py itself, for US_STATES, CA_PROVINCES, DIRECTIONALS,
     SUFFIXES and UNIT_WORDS.  The maps are parsed out of the pinned resolver
     source and their sizes asserted.  A hand-maintained copy of a 22-entry
     suffix map is a second opinion about the contract, not a port of it --
     and it drifts silently the first time the resolver gains an entry.

  2. Python's own unicodedata, for the NFKD fold table.  The container has no
     ext-intl and the WP Engine server's intl availability is UNMEASURED, so
     the PHP cannot call Normalizer::normalize().  Instead the decomposition
     is precomputed here, per codepoint, by the same library that computes it
     inside the oracle.

WHY A COMBINED TABLE.  Python's norm_text runs NFKD, then strips combining
marks, then uppercases, then squashes whitespace.  For the covered ranges each
of those steps is per-codepoint and context-free, so the composition

    cp -> upper( strip_combining( NFKD( cp ) ) )

is exact, and one lookup reproduces three Python operations.  That is why the
table carries 'SS' for U+00DF and 'FI' for U+FB01: 1-to-N expansions fall out
of the composition rather than needing special cases in the PHP.

WHITESPACE IS PART OF IT.  Python's re \\s on a str matches NBSP, U+2028,
U+3000 and the U+2000-U+200A run; PCRE's \\s without /u matches none of them.
Every such codepoint is folded to a plain space here, so the PHP's ASCII-only
squash reproduces the Python result without needing a Unicode regex.

WHAT IS NOT COVERED IS COUNTED, NOT IGNORED.  The table spans Latin-1
Supplement through Latin Extended-B, Latin Extended Additional, General
Punctuation, Letterlike and Number Forms, the alphabetic presentation forms
and fullwidth ASCII.  A codepoint outside those ranges is passed through
unchanged AND counted.  SLP_Avalon_AddressKey::unmapped() returns the tally
and the distinct codepoints, so Part 2's cron can log a feed that has drifted
outside the tested region instead of quietly writing a divergent key.  Measured
2026-09-14: all three feeds contain ZERO non-ASCII bytes in any column, so the
counter reads 0 today.  That is a property of the current feeds, not of the
code -- the same shape of precondition as s0.198's WordPress version.

THE GATE IS NOT THIS SCRIPT.  test/diff-address-key.php asserts the generated
PHP against build/placeid/keyvectors.csv, 749 vectors across 9 columns.  This
script's self-checks are structural only; passing them proves nothing about
agreement with the oracle.

Destination of the file this script WRITES:
    repo-relative  slp-plugins\\slp_avalon\\inc\\class.slp_avalon_addresskey.php
    local          D:\\Temp\\Projects\\GitHub\\slp-plugins\\slp_avalon\\inc\\class.slp_avalon_addresskey.php

Destination of THIS script:
    repo-relative  slp-plugins\\build\\build-addresskey.py
    local          D:\\Temp\\Projects\\GitHub\\slp-plugins\\build\\build-addresskey.py

Run:
    python build/build-addresskey.py --resolver build/resolve-placeids.py \\
                                     --out slp_avalon/inc/class.slp_avalon_addresskey.php
"""

import argparse
import hashlib
import os
import re
import shutil
import subprocess
import tempfile
import sys
import unicodedata

TOOL_VERSION = "1.0.0"

# resolve-placeids.py 1.3.0, the pinned oracle source.  Moved by
# patch-placeids-v13.py: CA_PROVINCES gained "ONT" and the resolver gained a
# state_unresolved anomaly flag.  The map size and the resolver hash move
# TOGETHER on purpose -- a generator that accepted one without the other
# would build a PHP table against vectors that no longer describe it.
RESOLVER_MD5 = "a29e6c7f2e5870b373ed214c306cd77a"
RESOLVER_BYTES = 63265

# The Unicode data version that produced the RELEASE artefact.  The fold table
# is derived from whatever unicodedata the running Python bundles, so it is a
# property of the BUILD MACHINE, not of the source.  Measured 2026-09-14: the
# workstation reports 16.0.0 and the assistant container 15.0.0, and the same
# script from the same pinned resolver produced two artefacts differing by 4
# bytes -- both of which passed the differential 749/749, because the drift is
# in codepoints no vector exercises.
#
# The workstation is canonical.  It is the machine that produces
# keyvectors.csv and that will produce placeids.json, and the PHP fold table
# must agree with the Python that computed those, not merely with some Python.
EXPECT_UNICODE = "16.0.0"

# Sizes asserted after parsing.  If the resolver gains an entry these move, the
# build fails, and the divergence is caught here rather than in production.
EXPECT_SIZES = {
    "US_STATES": 51, "CA_PROVINCES": 16,
    "DIRECTIONALS": 8, "SUFFIXES": 22, "UNIT_WORDS": 12,
}

# Codepoint ranges the fold table covers.  Outside these, input is passed
# through and counted by unmapped().
FOLD_RANGES = [
    (0x00A0, 0x024F),   # Latin-1 Supplement, Latin Extended-A, Latin Extended-B
    (0x0300, 0x036F),   # combining diacritical marks -- dropped, not folded
    (0x1E00, 0x1EFF),   # Latin Extended Additional
    (0x2000, 0x206F),   # General Punctuation
    (0x2100, 0x218F),   # Letterlike Symbols, Number Forms
    (0xFB00, 0xFB4F),   # Alphabetic Presentation Forms (ligatures)
    (0xFF01, 0xFF5E),   # Fullwidth ASCII
]

# Every codepoint Python's str-mode \s matches.  Folded to U+0020 so the PHP
# can use an ASCII-only \s+ collapse and still agree.
PY_WHITESPACE = (
    [0x001C, 0x001D, 0x001E, 0x001F, 0x0085, 0x00A0, 0x1680,
     0x2028, 0x2029, 0x202F, 0x205F, 0x3000]
    + list(range(0x2000, 0x200B))
)


def php_str(s):
    """Emit a PHP double-quoted literal that is pure ASCII in the source."""
    out = []
    for b in s.encode("utf-8"):
        if b == 0x5C:
            out.append("\\\\")
        elif b == 0x22:
            out.append('\\"')
        elif b == 0x24:
            out.append("\\$")
        elif 0x20 <= b < 0x7F:
            out.append(chr(b))
        else:
            out.append("\\x%02X" % b)
    return '"' + "".join(out) + '"'


def parse_dict(src, name):
    m = re.search(re.escape(name) + r"\s*=\s*\{(.*?)\n\}", src, re.S)
    if not m:
        raise SystemExit("FAIL: cannot find %s in the resolver source" % name)
    return re.findall(r'"([^"]+)"\s*:\s*"([^"]+)"', m.group(1))


def parse_tuple(src, name):
    m = re.search(re.escape(name) + r"\s*=\s*\((.*?)\)", src, re.S)
    if not m:
        raise SystemExit("FAIL: cannot find %s in the resolver source" % name)
    return re.findall(r'"([^"]+)"', m.group(1))


def build_fold():
    fold, combining = {}, []
    for a, b in FOLD_RANGES:
        for cp in range(a, b + 1):
            ch = chr(cp)
            if unicodedata.combining(ch):
                combining.append(cp)
                continue
            if cp in PY_WHITESPACE:
                fold[cp] = " "
                continue
            kd = unicodedata.normalize("NFKD", ch)
            kd = "".join(c for c in kd if not unicodedata.combining(c))
            fold[cp] = kd.upper()
    for cp in PY_WHITESPACE:
        if cp not in fold and cp not in combining:
            fold[cp] = " "
    return fold, sorted(combining)


def php_map(name, pairs, indent="        "):
    body, line = [], indent + "   "
    for k, v in pairs:
        tok = " %s=>%s," % (php_str(k), php_str(v))
        if len(line) + len(tok) > 100:
            body.append(line)
            line = indent + "   "
        line += tok
    body.append(line)
    return "%sprivate static $%s = array(\n%s\n%s);" % (
        indent, name, "\n".join(body), indent)


def php_list(name, items, indent="        "):
    body, line = [], indent + "   "
    for k in items:
        tok = " %s," % php_str(k)
        if len(line) + len(tok) > 100:
            body.append(line)
            line = indent + "   "
        line += tok
    body.append(line)
    return "%sprivate static $%s = array(\n%s\n%s);" % (
        indent, name, "\n".join(body), indent)


def php_intkeys(name, pairs, indent="        "):
    body, line = [], indent + "   "
    for cp, v in pairs:
        tok = " 0x%04X=>%s," % (cp, php_str(v)) if v is not None else " 0x%04X=>1," % cp
        if len(line) + len(tok) > 100:
            body.append(line)
            line = indent + "   "
        line += tok
    body.append(line)
    return "%sprivate static $%s = array(\n%s\n%s);" % (
        indent, name, "\n".join(body), indent)


TEMPLATE = r'''<?php
/**
 * class.slp_avalon_addresskey.php  --  GENERATED.  DO NOT HAND-EDIT.
 *
 * Produced by build/build-addresskey.py %(tool)s from:
 *   resolve-placeids.py  md5 %(rmd5)s  bytes %(rbytes)d
 *   Python unicodedata   %(udata)s
 *
 * The PHP half of the address-key contract.  dealer_key is
 * sha1(country|state|city_key|zip|street_key) truncated to 12 hex characters,
 * and it must agree with resolve-placeids.py on every input, because a key
 * that disagrees does not throw -- it writes a row into
 * wp_avalon_dealer_places that never joins to placeids.json.
 *
 * AGREEMENT IS ASSERTED, NOT CLAIMED.  test/diff-address-key.php compares this
 * class against build/placeid/keyvectors.csv: 749 vectors, 9 columns, feeding
 * the raw fields in and checking every intermediate, not just the final key.
 * Edits to this file that are not made in the generator will be overwritten by
 * the next build and are not covered by the maps' provenance.
 *
 * NO ext-intl, NO ext-mbstring, NO ext-dom.  The NFKD decomposition, the
 * combining-mark strip and the Unicode uppercase are precomputed per codepoint
 * into $fold, so no Unicode extension is called at runtime.
 *
 * PRECONDITION, not a guarantee.  $fold covers Latin-1 Supplement through
 * Latin Extended-B, Latin Extended Additional, General Punctuation, Letterlike
 * and Number Forms, the alphabetic presentation forms and fullwidth ASCII.  A
 * codepoint outside those ranges is passed through unchanged and COUNTED;
 * unmapped() returns the tally so a drifted feed is reported rather than
 * silently keyed.  All three feeds measured 2026-09-14 contain zero non-ASCII
 * bytes in any column, so this counter reads 0 on current data.
 *
 * %(counts)s
 */

if ( ! class_exists( 'SLP_Avalon_AddressKey' ) ) :

class SLP_Avalon_AddressKey {

%(maps)s

%(fold)s

%(combining)s

    /** @var int   non-ASCII codepoints seen outside $fold since the last reset. */
    private static $unmapped_count = 0;
    /** @var array codepoint => occurrences, for logging a drifted feed. */
    private static $unmapped_seen = array();

    /**
     * Codepoints this build could not fold.  Part 2's cron logs this after an
     * import: a non-zero tally means feed data has moved outside the region
     * the differential gate covers, and the key it produced is untested.
     */
    public static function unmapped() {
        return array( 'count' => self::$unmapped_count, 'codepoints' => self::$unmapped_seen );
    }

    public static function reset_unmapped() {
        self::$unmapped_count = 0;
        self::$unmapped_seen  = array();
    }

    /** UTF-8 to codepoints.  No mbstring. */
    private static function codepoints( $s ) {
        $cp = array();
        $n  = strlen( $s );
        $i  = 0;
        while ( $i < $n ) {
            $c = ord( $s[ $i ] );
            if ( $c < 0x80 ) {
                $cp[] = $c; $i += 1;
            } elseif ( ( $c & 0xE0 ) === 0xC0 && $i + 1 < $n ) {
                $cp[] = ( ( $c & 0x1F ) << 6 ) | ( ord( $s[ $i + 1 ] ) & 0x3F );
                $i += 2;
            } elseif ( ( $c & 0xF0 ) === 0xE0 && $i + 2 < $n ) {
                $cp[] = ( ( $c & 0x0F ) << 12 ) | ( ( ord( $s[ $i + 1 ] ) & 0x3F ) << 6 )
                      | ( ord( $s[ $i + 2 ] ) & 0x3F );
                $i += 3;
            } elseif ( ( $c & 0xF8 ) === 0xF0 && $i + 3 < $n ) {
                $cp[] = ( ( $c & 0x07 ) << 18 ) | ( ( ord( $s[ $i + 1 ] ) & 0x3F ) << 12 )
                      | ( ( ord( $s[ $i + 2 ] ) & 0x3F ) << 6 ) | ( ord( $s[ $i + 3 ] ) & 0x3F );
                $i += 4;
            } else {
                $cp[] = $c; $i += 1;   /* malformed lead byte: pass the byte through */
            }
        }
        return $cp;
    }

    private static function encode( $cp ) {
        if ( $cp < 0x80 )    { return chr( $cp ); }
        if ( $cp < 0x800 )   { return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) ); }
        if ( $cp < 0x10000 ) {
            return chr( 0xE0 | ( $cp >> 12 ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) )
                 . chr( 0x80 | ( $cp & 0x3F ) );
        }
        return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) )
             . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
    }

    /** Python: re.sub(r"\s+", " ", (s or "").strip()) */
    public static function squash( $s ) {
        return preg_replace( '/\s+/', ' ', trim( (string) $s ) );
    }

    /** Python: NFKD, drop combining marks, upper, squash. */
    public static function norm_text( $s ) {
        $out = '';
        foreach ( self::codepoints( (string) $s ) as $cp ) {
            if ( $cp < 0x80 ) {
                $out .= chr( $cp );
            } elseif ( isset( self::$combining[ $cp ] ) ) {
                continue;
            } elseif ( isset( self::$fold[ $cp ] ) ) {
                $out .= self::$fold[ $cp ];
            } else {
                self::$unmapped_count += 1;
                if ( isset( self::$unmapped_seen[ $cp ] ) ) {
                    self::$unmapped_seen[ $cp ] += 1;
                } else {
                    self::$unmapped_seen[ $cp ] = 1;
                }
                $out .= self::encode( $cp );
            }
        }
        return self::squash( strtoupper( $out ) );
    }

    /**
     * Python norm_street.  Returns array( street_key, unit_token ).
     * The unit is held OUT of the key deliberately: one building is one Google
     * place, and a suite number that drifts between feeds must not split a
     * dealer in two.
     */
    public static function norm_street( $s ) {
        $s = str_replace( '&', ' AND ', self::norm_text( $s ) );
        $s = preg_replace( '/[.,]/', ' ', $s );
        $s = preg_replace( '/\bU\s*S\s*HIGHWAY\b|\bUS\s*HWY\b|\bUS-\b/', 'US HWY ', $s );
        $s = preg_replace( '/\bSTATE\s+(HIGHWAY|HWY|ROUTE|RTE|RD)\b/', 'STATE HWY', $s );
        $s = preg_replace( '/\bCOUNTY\s+(ROAD|RD|ROUTE|RTE)\b/', 'COUNTY RD', $s );
        $s = preg_replace( '/[^A-Z0-9# ]+/', ' ', $s );

        $t      = self::squash( $s );
        $tokens = ( '' === $t ) ? array() : explode( ' ', $t );
        $unit   = array();
        $keep   = array();
        $n      = count( $tokens );
        $i      = 0;
        while ( $i < $n ) {
            $tok = $tokens[ $i ];
            if ( in_array( $tok, self::$UNIT_WORDS, true ) || 0 === strpos( $tok, '#' ) ) {
                $unit[] = $tok;
                if ( $i + 1 < $n ) {
                    $unit[] = $tokens[ $i + 1 ];
                    $i += 1;
                }
            } elseif ( isset( self::$DIRECTIONALS[ $tok ] ) ) {
                $keep[] = self::$DIRECTIONALS[ $tok ];
            } elseif ( isset( self::$SUFFIXES[ $tok ] ) ) {
                $keep[] = self::$SUFFIXES[ $tok ];
            } else {
                $keep[] = $tok;
            }
            $i += 1;
        }
        return array( self::squash( implode( ' ', $keep ) ), self::squash( implode( ' ', $unit ) ) );
    }

    public static function norm_state( $raw ) {
        $s = str_replace( '.', '', self::norm_text( $raw ) );
        if ( 2 === strlen( $s ) && ( isset( self::$US_CODES[ $s ] ) || isset( self::$CA_CODES[ $s ] ) ) ) {
            return $s;
        }
        if ( isset( self::$US_STATES[ $s ] ) )    { return self::$US_STATES[ $s ]; }
        if ( isset( self::$CA_PROVINCES[ $s ] ) ) { return self::$CA_PROVINCES[ $s ]; }
        return $s;
    }

    /** Returns array( normalised postal, inferred country or '' ). */
    public static function norm_postal( $raw ) {
        $t = self::norm_text( $raw );
        $s = str_replace( array( ' ', '-' ), '', $t );
        if ( preg_match( '/^([A-Z]\d[A-Z])\s*(\d[A-Z]\d)/', str_replace( '-', ' ', $t ), $m ) ) {
            return array( $m[1] . $m[2], 'CA' );
        }
        if ( preg_match( '/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $s ) ) { return array( $s, 'CA' ); }
        if ( preg_match( '/(\d{5})/', $s, $m ) )              { return array( $m[1], 'US' ); }
        return array( $s, '' );
    }

    public static function norm_country( $raw, $state, $postal_hint ) {
        $s = str_replace( '.', '', self::norm_text( $raw ) );
        if ( in_array( $s, array( 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA' ), true ) ) {
            return 'US';
        }
        if ( in_array( $s, array( 'CA', 'CAN', 'CANADA' ), true ) ) { return 'CA'; }
        if ( '' !== $postal_hint ) { return $postal_hint; }
        if ( isset( self::$CA_CODES[ $state ] ) && ! isset( self::$US_CODES[ $state ] ) ) { return 'CA'; }
        if ( isset( self::$US_CODES[ $state ] ) ) { return 'US'; }
        return ( '' === $s ) ? '' : $s;
    }

    /**
     * The contract test/diff-address-key.php asserts.
     *
     * @param array $raw keys raw_address, raw_city, raw_state, raw_zip, raw_country.
     * @return array keys street_key, unit, city_key, state, zip, postal_country,
     *               country, basis, dealer_key.
     */
    public static function vector( array $raw ) {
        $get = function ( $k ) use ( $raw ) {
            return isset( $raw[ $k ] ) ? (string) $raw[ $k ] : '';
        };

        list( $street, $unit ) = self::norm_street( $get( 'raw_address' ) );
        list( $postal, $pc )   = self::norm_postal( $get( 'raw_zip' ) );

        $state    = self::norm_state( $get( 'raw_state' ) );
        $country  = self::norm_country( $get( 'raw_country' ), $state, $pc );
        $city_key = str_replace( '.', '', self::norm_text( $get( 'raw_city' ) ) );
        $basis    = implode( '|', array( $country, $state, $city_key, $postal, $street ) );

        return array(
            'street_key'     => $street,
            'unit'           => $unit,
            'city_key'       => $city_key,
            'state'          => $state,
            'zip'            => $postal,
            'postal_country' => $pc,
            'country'        => $country,
            'basis'          => $basis,
            'dealer_key'     => substr( sha1( $basis ), 0, 12 ),
        );
    }

    /** Convenience: the key alone, for callers that need nothing else. */
    public static function dealer_key( array $raw ) {
        $v = self::vector( $raw );
        return $v['dealer_key'];
    }
}

endif;
'''


SMOKE = r"""<?php
/* Generated probe.  Any diagnostic at all is a failure. */
error_reporting( E_ALL );
set_error_handler( function ( $n, $s ) {
    fwrite( STDERR, "DIAGNOSTIC: " . $s . PHP_EOL );
    exit( 3 );
} );
require $argv[1];
$k = "SLP_Avalon_AddressKey";
if ( ! class_exists( $k ) ) { fwrite( STDERR, "no class" . PHP_EOL ); exit( 4 ); }

$cases = array(
    array( "raw_address" => "100 US HWY 31 STE 4", "raw_city" => "ST. CLAIR",
           "raw_state" => "MICHIGAN", "raw_zip" => "49001-1234", "raw_country" => "USA",
           "expect_street" => "100 US HWY 31", "expect_unit" => "STE 4" ),
    array( "raw_address" => "59 STATE ROUTE 66", "raw_city" => "COLUMBIA",
           "raw_state" => "ON", "raw_zip" => "P0M 3E0", "raw_country" => "CANADA",
           "expect_street" => "59 STATE HWY 66", "expect_unit" => "" ),
);

foreach ( $cases as $c ) {
    $v = $k::vector( $c );
    foreach ( array( "street_key", "city_key", "state", "zip", "country", "basis", "dealer_key" ) as $f ) {
        if ( ! isset( $v[ $f ] ) || "" === $v[ $f ] ) {
            fwrite( STDERR, "EMPTY FIELD: " . $f . PHP_EOL );
            exit( 5 );
        }
    }
    if ( $v["street_key"] !== $c["expect_street"] ) {
        fwrite( STDERR, "street_key was " . var_export( $v["street_key"], true )
                      . ", expected " . var_export( $c["expect_street"], true ) . PHP_EOL );
        exit( 6 );
    }
    if ( $v["unit"] !== $c["expect_unit"] ) {
        fwrite( STDERR, "unit was " . var_export( $v["unit"], true ) . PHP_EOL );
        exit( 7 );
    }
}
echo "smoke ok" . PHP_EOL;
"""


def main():
    ap = argparse.ArgumentParser(description="Generate the PHP address-key port.")
    ap.add_argument("--resolver", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--expect-unicode", default=EXPECT_UNICODE,
                    help="unicodedata version of the release machine; "
                         "a mismatch is reported, never silently built around")
    ap.add_argument("--skip-lint", action="store_true",
                    help="do not run php -l even if php is on PATH")
    args = ap.parse_args()

    print("build-addresskey %s" % TOOL_VERSION)
    print("=" * 72)

    if not os.path.exists(args.resolver):
        print("FAIL: resolver not found: %s" % args.resolver)
        return 2
    raw = open(args.resolver, "rb").read()
    m, n = hashlib.md5(raw).hexdigest(), len(raw)
    print("INPUT   %s" % args.resolver)
    print("        md5   %s  %s" % (m, "ok" if m == RESOLVER_MD5 else "EXPECTED " + RESOLVER_MD5))
    print("        bytes %-8d %s" % (n, "ok" if n == RESOLVER_BYTES else "EXPECTED %d" % RESOLVER_BYTES))
    if m != RESOLVER_MD5 or n != RESOLVER_BYTES:
        print("\nFAIL: resolver is not the pinned 1.2.0. Nothing written.")
        return 2

    src = raw.decode("iso-8859-1")

    us = parse_dict(src, "US_STATES")
    ca = parse_dict(src, "CA_PROVINCES")
    di = parse_dict(src, "DIRECTIONALS")
    sf = parse_dict(src, "SUFFIXES")
    uw = parse_tuple(src, "UNIT_WORDS")

    print()
    print("MAPS EXTRACTED FROM THE RESOLVER")
    sizes = {"US_STATES": len(us), "CA_PROVINCES": len(ca), "DIRECTIONALS": len(di),
             "SUFFIXES": len(sf), "UNIT_WORDS": len(uw)}
    bad = False
    for k, v in sizes.items():
        want = EXPECT_SIZES[k]
        flag = "ok" if v == want else "EXPECTED %d" % want
        if v != want:
            bad = True
        print("  %-14s %3d  %s" % (k, v, flag))
    if bad:
        print("\nFAIL: a map changed size. Regenerate the vectors and re-pin before")
        print("      building, or the port will be gated against a stale oracle.")
        return 2

    us_codes = sorted({v for _, v in us})
    ca_codes = sorted({v for _, v in ca})

    fold, combining = build_fold()
    print()
    print("FOLD TABLE FROM unicodedata %s" % unicodedata.unidata_version)
    print("  ranges           %s" % ", ".join("U+%04X-U+%04X" % r for r in FOLD_RANGES))
    print("  fold entries     %d" % len(fold))
    print("  combining marks  %d" % len(combining))
    print("  1-to-N expansions %d   (U+00DF->%r, U+FB01->%r)"
          % (sum(1 for v in fold.values() if len(v) > 1), fold[0x00DF], fold[0xFB01]))
    print("  whitespace folded to U+0020  %d" % sum(1 for v in fold.values() if v == " "))

    maps = "\n\n".join([
        php_map("US_STATES", us),
        php_map("CA_PROVINCES", ca),
        php_map("DIRECTIONALS", di),
        php_map("SUFFIXES", sf),
        php_list("UNIT_WORDS", uw),
        php_map("US_CODES", [(c, "1") for c in us_codes]),
        php_map("CA_CODES", [(c, "1") for c in ca_codes]),
    ])

    counts = ("Tables: %d US states, %d CA provinces, %d directionals, %d suffixes,\n"
              " * %d unit words, %d fold entries, %d combining marks."
              % (len(us), len(ca), len(di), len(sf), len(uw), len(fold), len(combining)))

    fold_php = php_intkeys("fold", sorted(fold.items()))
    fold_md5 = hashlib.md5(fold_php.encode("ascii")).hexdigest()
    print("  fold table md5    %s" % fold_md5)
    counts = counts + ("\n * Fold table md5 %s, unicodedata %s."
                       % (fold_md5, unicodedata.unidata_version))
    php = TEMPLATE % {
        "tool": TOOL_VERSION, "rmd5": m, "rbytes": n,
        "udata": unicodedata.unidata_version, "counts": counts,
        "maps": maps,
        "fold": fold_php,
        "combining": php_intkeys("combining", [(cp, None) for cp in combining]),
    }

    out_bytes = php.encode("ascii")          # the emitter guarantees pure ASCII
    os.makedirs(os.path.dirname(os.path.abspath(args.out)) or ".", exist_ok=True)
    open(args.out, "wb").write(out_bytes)

    om, on = hashlib.md5(out_bytes).hexdigest(), len(out_bytes)
    print()
    print("OUTPUT  %s" % args.out)
    print("        md5   %s" % om)
    print("        bytes %d" % on)
    print("        CR    %d  LF %d" % (out_bytes.count(b"\r"), out_bytes.count(b"\n")))

    print()
    print("SELF-CHECK")
    ok = True

    def check(label, cond, detail=""):
        nonlocal ok
        print("  %-52s %s %s" % (label, "PASS" if cond else "FAIL", detail))
        if not cond:
            ok = False

    text = php
    check("unicodedata matches the release machine",
          unicodedata.unidata_version == args.expect_unicode,
          "this build %s, expected %s -- the artefact md5 WILL differ"
          % (unicodedata.unidata_version, args.expect_unicode))
    check("class declared", "class SLP_Avalon_AddressKey {" in text)
    check("redeclaration guarded", "if ( ! class_exists( 'SLP_Avalon_AddressKey' ) ) :" in text
          and "\nendif;\n" in text)
    check("vector() declared", "public static function vector( array $raw )" in text)
    check("dealer_key() declared", "public static function dealer_key( array $raw )" in text)
    check("unmapped() declared", "public static function unmapped()" in text)
    check("output is pure ASCII", all(b < 128 for b in out_bytes))
    check("no BOM", not out_bytes.startswith(b"\xef\xbb\xbf"))
    check("no short open tag", "<?" not in text.replace("<?php", ""))

    # Absence assertions scoped to the CODE, not the docblock.  The docblock
    # names ext-intl and ext-mbstring in order to say it does not use them --
    # a file-wide check would fail on its own explanation.  rev34 s0.194.
    code = text[text.index("if ( ! class_exists("):]
    check("no intl call in the code span",
          "Normalizer::" not in code and "Transliterator::" not in code
          and "normalizer_normalize" not in code)
    check("no mbstring call in the code span", not re.search(r"\bmb_[a-z_]+\s*\(", code))
    check("no iconv call in the code span", not re.search(r"\biconv[a-z_]*\s*\(", code))
    check("no /u regex modifier in the code span", not re.search(r"'/.*?/[a-z]*u[a-z]*'", code))
    check("no WordPress function in the code span",
          not re.search(r"\b(add_action|apply_filters|get_option|\$wpdb)\b", code))

    # Sizes survived into the artefact.
    check("every suffix present in the output",
          all(php_str(k) + "=>" + php_str(v) in text for k, v in sf),
          "%d" % len(sf))
    check("every unit word present in the output",
          all(php_str(k) in text for k in uw), "%d" % len(uw))
    check("fold carries the 1-to-N cases",
          php_str("SS") in text and php_str("FI") in text)

    php_bin = shutil.which("php")
    if args.skip_lint or not php_bin:
        print("  %-52s %s %s" % ("php -l", "SKIP", "php not on PATH" if not php_bin else "--skip-lint"))
    else:
        for flag in ("1", "0"):
            r = subprocess.run([php_bin, "-d", "short_open_tag=" + flag, "-l", args.out],
                               capture_output=True, text=True)
            check("php -l short_open_tag=%s" % flag, r.returncode == 0,
                  (r.stdout or r.stderr).strip().splitlines()[0] if r.returncode else "")

    # ---- pattern well-formedness.  A missing delimiter is a runtime warning,
    # not a parse error, so php -l above cannot see it and neither can any
    # "string is present" assertion.  Measured 2026-09-14: it did not.
    pats = re.findall(r"preg_(?:replace|match)\(\s*'([^']*)'", code)
    bad = [p for p in pats
           if not (p.startswith("/") and p.rstrip("imsxuADSUXJn").endswith("/")
                   and len(p.rstrip("imsxuADSUXJn")) > 1)]
    check("every preg pattern is delimiter-balanced", not bad,
          "%d pattern(s); bad: %s" % (len(pats), bad))
    check("no doubled backslash inside a preg pattern",
          not any(chr(92) + chr(92) in p for p in pats))

    # ---- runtime smoke test.  A signal from outside the text.
    if php_bin and not args.skip_lint:
        probe = os.path.join(tempfile.gettempdir(), "slp_addresskey_probe.php")
        with open(probe, "w", encoding="ascii", newline="\n") as fh:
            fh.write(SMOKE)
        r = subprocess.run([php_bin, probe, os.path.abspath(args.out)],
                           capture_output=True, text=True)
        detail = (r.stderr or r.stdout).strip().splitlines()
        check("runtime smoke test (E_ALL, diagnostics fatal)", r.returncode == 0,
              detail[0] if r.returncode else "")
    else:
        print("  %-52s %s" % ("runtime smoke test", "SKIP"))

    print()
    print("self-check ok" if ok else "SELF-CHECK FAILED")
    print()
    print("  Structural only.  Agreement with the oracle is asserted by:")
    print("    php test/diff-address-key.php")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
