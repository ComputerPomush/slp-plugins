#!/usr/bin/env python3
"""
build-v027-part2.py

slp_avalon v0.0.27, PART 2 OF 4.

WHAT THIS RELEASE DOES
----------------------
Part 2 is the fetch layer and nothing else. No database write, no cron
hook, no CLI subcommand, no shortcode, no rendering. One dispatcher,
two callers, one normaliser.

    avalon_hours_details( $place_id )        dispatcher
    avalon_hours_details_legacy( ... )       GET, key in the URL
    avalon_hours_details_new( ... )          GET, key in a header
    avalon_hours_normalise_legacy( ... )     legacy Place -> New-shaped
    avalon_hours_point( ... )                "0800" -> hour 8, minute 0

ONE SHAPE REACHES THE CALLER
----------------------------
The canonical internal shape is the NEW API's Place object, and the
legacy caller normalises into it. This is the same decision
resolve-placeids 1.4.0 took for Text Search - "the adapter, not a
second caller" - and it is taken here for one concrete reason: on
enablement day the switch must not require a data migration. Store
legacy's shape and 301 rows of cached payload have to be rewritten or
re-fetched at full cost. Store New's shape and flipping
AVALON_HOURS_API is the entire change.

THE MAPPING IS DOCUMENTED, NOT GUESSED
--------------------------------------
Taken from Google's own legacy-to-new response table, retrieved
2026-09-15 (page last updated 2026-09-10):

    business_status     -> businessStatus
    name                -> displayName        (text at displayName.text)
    opening_hours       -> regularOpeningHours
    place_id            -> id
    utc_offset          -> utcOffsetMinutes
    html_attributions   -> attributions       (top-level in legacy,
                                               inside Place in New)
    (none)              -> name               ("places/<PLACE_ID>",
                                               the resource name)

The INNER field names of regularOpeningHours - openNow,
weekdayDescriptions, periods[].open.hour / .minute - are from the
Place reference rather than that table and are the one part of this
mapping not yet confirmed against a live New response. See RAW below;
that is why it is stored.

RAW IS STORED ALONGSIDE
-----------------------
Every result carries the verbatim decoded response under 'raw' as well
as the normalised Place under 'place'. If an inner name above turns
out wrong, the fix is a re-normalisation over stored bytes rather than
301 billable calls. Both are Places content under the same 30-day cap,
so storing both adds no policy exposure, only longtext.

WHAT LEGACY CANNOT FILL
-----------------------
primary_type_display has no legacy equivalent. Google's table lists
primaryTypeDisplayName as New-only. That column stays NULL until
enablement day, which is correct and is why it is nullable.

locality and admin_area are reachable on both sides via
address_components / addressComponents, but that field is not in
either mask here. Part 2's scope is hours. Adding it later is a mask
edit, not a schema change.

WHY THE STATUS HANDLING DIFFERS BETWEEN THE TWO CALLERS
-------------------------------------------------------
Legacy answers HTTP 200 and puts the outcome in a status FIELD -
the same contract avalon_places_text_search() already documents. New
moved status and error_message onto the HTTP response itself and its
body IS the Place object, with no result wrapper and no status field.
So the two callers cannot share an error path, only an output shape.

ENCODING
--------
ISO-8859-1 throughout with newline='' so CRLF and any high bytes
survive byte-for-byte. The input is CRLF; the output must stay CRLF,
and the CR count is printed so a mangled write cannot pass quietly.

slp_avalon.php is NOT an input at Part 2. The version header already
reads 0.0.27 from Part 1 and does not move again inside the release.

Usage:  python build-v027-part2.py <src_dir> <out_dir>

        src_dir must hold the PART 1 OUTPUT:
            class.slp_avalon.php   6a25c409059f5c9c291f6d6b04ebf0d3
"""

import hashlib
import io
import os
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('6a25c409059f5c9c291f6d6b04ebf0d3', 195188),
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
    return CRLF.join(lines) + CRLF


# ===========================================================================
# 1. Endpoint and field-mask constants
# ===========================================================================

CONST_ANCHOR = "        const PLACES_LOG_MAX        = 50;"

CONST_BLOCK = block([
    "        const PLACES_LOG_MAX        = 50;",
    "",
    "        /**",
    "         * v0.0.27 Part 2. Place Details, both endpoints.",
    "         *",
    "         * Both are declared even though only one can be reached today.",
    "         * Places API (New) is not enabled on GCP project 1038304488249",
    "         * and enabling it needs IAM nobody on this side holds; the New",
    "         * endpoint answers 403 SERVICE_DISABLED. Legacy Place Details",
    "         * answers OK on the same key with real opening hours, measured",
    "         * on Aura DEV 2026-09-15 22:24:59 GMT against the control place",
    "         * ID ChIJTUJR1je6rYkR-Tl154_fum8.",
    "         *",
    "         * Declaring the unreachable one costs nothing and means",
    "         * enablement day is a constant flip rather than an edit to a",
    "         * pinned file.",
    "         *",
    "         * THE NEW ENDPOINT TAKES A TRAILING PLACE ID, not a query",
    "         * parameter. places/<PLACE_ID> is the resource name.",
    "         *",
    "         * primaryTypeDisplayName appears in the New mask only. It has",
    "         * no legacy equivalent, so primary_type_display stays NULL",
    "         * while the legacy path is in use.",
    "         */",
    "        const HOURS_ENDPOINT_LEGACY = 'https://maps.googleapis.com/maps/api/place/details/json';",
    "        const HOURS_ENDPOINT_NEW    = 'https://places.googleapis.com/v1/places/';",
    "        const HOURS_FIELDS_LEGACY   = 'place_id,name,business_status,opening_hours,utc_offset';",
    "        const HOURS_FIELDS_NEW      = 'id,name,displayName,businessStatus,regularOpeningHours,utcOffsetMinutes,primaryTypeDisplayName,attributions';",
]).rstrip(CRLF)


# ===========================================================================
# 2. The api selector in avalon_hours_config()
# ===========================================================================

CONFIG_ANCHOR = block([
    "                'timeout'           => defined('AVALON_HOURS_TIMEOUT')",
    "                                       ? (int) AVALON_HOURS_TIMEOUT    : 8,",
    "            );",
]).rstrip(CRLF)

CONFIG_BLOCK = block([
    "                'timeout'           => defined('AVALON_HOURS_TIMEOUT')",
    "                                       ? (int) AVALON_HOURS_TIMEOUT    : 8,",
    "",
    "                //v0.0.27 Part 2. Clamped to the two known values rather",
    "                //than passed through, and anything that is not exactly",
    "                //'new' resolves to 'legacy'. A typo in wp-config must",
    "                //land on the endpoint that works, not on the one that",
    "                //returns 403 for every dealer in the queue.",
    "                'api'               => ( defined('AVALON_HOURS_API')",
    "                                         && strtolower( (string) AVALON_HOURS_API ) === 'new' )",
    "                                       ? 'new' : 'legacy',",
    "            );",
]).rstrip(CRLF)


# ===========================================================================
# 3. The methods
# ===========================================================================

METHODS_ANCHOR = "        public function avalon_rest_protected_slugs(){"

METHODS_BLOCK = block([
    "        /**",
    "         * v0.0.27 Part 2. One Place Details call, either endpoint.",
    "         *",
    "         * Returns, always, an array of this shape:",
    "         *",
    "         *   ok     bool    a Place was obtained",
    "         *   place  array   New-shaped Place object, or null",
    "         *   raw    array   verbatim decoded response, or null",
    "         *   api    string  which endpoint answered",
    "         *   error  string  '' when ok, else a reason, <= 190 chars",
    "         *",
    "         * The 190 is not arbitrary. last_error is varchar(190) and a",
    "         * caller that writes an untruncated reason gets a silent",
    "         * truncation from MySQL instead of a decision made here.",
    "         *",
    "         * NO DATABASE WRITE HAPPENS IN PART 2. This method fetches and",
    "         * returns. Deciding what that means for hours_status, and",
    "         * writing it, is Part 3.",
    "         */",
    "        public function avalon_hours_details( $place_id ){",
    "",
    "            $out = array(",
    "                'ok'    => false,",
    "                'place' => null,",
    "                'raw'   => null,",
    "                'api'   => 'legacy',",
    "                'error' => '',",
    "            );",
    "",
    "            $place_id = trim( (string) $place_id );",
    "            if ( $place_id === '' ) {",
    "                $out['error'] = 'EMPTY_PLACE_ID';",
    "                return $out;",
    "            }",
    "",
    "            $cfg          = $this->avalon_hours_config();",
    "            $out['api']   = $cfg['api'];",
    "",
    "            if ( empty( $cfg['enabled'] ) ) {",
    "                $out['error'] = 'DISABLED';",
    "                return $out;",
    "            }",
    "",
    "            global $slplus;",
    "            $api_key = '';",
    "            if ( isset( $slplus ) && is_object( $slplus ) ) {",
    "                $api_key = (string) $slplus->SmartOptions->google_server_key->value;",
    "            }",
    "            if ( $api_key === '' ) {",
    "                $out['error'] = 'NO_KEY';",
    "                return $out;",
    "            }",
    "",
    "            if ( $cfg['api'] === 'new' ) {",
    "                $res = $this->avalon_hours_details_new( $place_id, $api_key, (int) $cfg['timeout'] );",
    "            } else {",
    "                $res = $this->avalon_hours_details_legacy( $place_id, $api_key, (int) $cfg['timeout'] );",
    "            }",
    "",
    "            $res['api']   = $out['api'];",
    "            $res['error'] = substr( (string) $res['error'], 0, 190 );",
    "            return $res;",
    "        }",
    "",
    "        /**",
    "         * v0.0.27 Part 2. Legacy Place Details.",
    "         *",
    "         * HTTP 200 AND AN ERROR IS THE LEGACY CONTRACT, exactly as",
    "         * avalon_places_text_search() already records for Text Search.",
    "         * The transport succeeding says nothing; the status field in",
    "         * the body is the outcome. A caller that tests only the HTTP",
    "         * code treats REQUEST_DENIED as a success.",
    "         *",
    "         * The key travels in the URL because that is how legacy",
    "         * authenticates. It is never logged and never returned.",
    "         */",
    "        private function avalon_hours_details_legacy( $place_id, $api_key, $timeout ){",
    "",
    "            $out = array( 'ok' => false, 'place' => null, 'raw' => null,",
    "                          'api' => 'legacy', 'error' => '' );",
    "",
    "            $url = add_query_arg(",
    "                array(",
    "                    'place_id' => $place_id,",
    "                    'fields'   => self::HOURS_FIELDS_LEGACY,",
    "                    'key'      => $api_key,",
    "                ),",
    "                self::HOURS_ENDPOINT_LEGACY",
    "            );",
    "",
    "            $res = wp_remote_get( $url, array(",
    "                'timeout'    => $timeout > 0 ? $timeout : 8,",
    "                'sslverify'  => true,",
    "                'user-agent' => 'slp_avalon/' . self::HOURS_DB_VERSION,",
    "            ) );",
    "",
    "            if ( is_wp_error( $res ) ) {",
    "                $out['error'] = 'TRANSPORT ' . $res->get_error_code();",
    "                return $out;",
    "            }",
    "",
    "            $code = (int) wp_remote_retrieve_response_code( $res );",
    "            $json = json_decode( (string) wp_remote_retrieve_body( $res ), true );",
    "",
    "            if ( ! is_array( $json ) ) {",
    "                $out['error'] = 'BAD_JSON http ' . $code;",
    "                return $out;",
    "            }",
    "",
    "            $out['raw'] = $json;",
    "            $status     = isset( $json['status'] ) ? (string) $json['status'] : 'NO_STATUS';",
    "",
    "            if ( $status !== 'OK' || ! isset( $json['result'] ) || ! is_array( $json['result'] ) ) {",
    "                $msg = isset( $json['error_message'] ) ? ' ' . (string) $json['error_message'] : '';",
    "                $out['error'] = $status . $msg;",
    "                return $out;",
    "            }",
    "",
    "            $attrib = ( isset( $json['html_attributions'] ) && is_array( $json['html_attributions'] ) )",
    "                      ? $json['html_attributions'] : array();",
    "",
    "            $out['place'] = $this->avalon_hours_normalise_legacy( $json['result'], $attrib );",
    "            $out['ok']    = true;",
    "            return $out;",
    "        }",
    "",
    "        /**",
    "         * v0.0.27 Part 2. Place Details (New).",
    "         *",
    "         * UNREACHABLE TODAY and written anyway. Three things differ and",
    "         * none of them is a URL swap:",
    "         *",
    "         *   the place ID is a path segment, not a query parameter",
    "         *   the key is a header, X-Goog-Api-Key",
    "         *   the field mask is REQUIRED - omit it and the call errors",
    "         *",
    "         * And the contract inverts: status and error_message moved onto",
    "         * the HTTP response, and the body IS the Place object with no",
    "         * result wrapper. So HTTP 200 here really does mean success.",
    "         */",
    "        private function avalon_hours_details_new( $place_id, $api_key, $timeout ){",
    "",
    "            $out = array( 'ok' => false, 'place' => null, 'raw' => null,",
    "                          'api' => 'new', 'error' => '' );",
    "",
    "            $res = wp_remote_get( self::HOURS_ENDPOINT_NEW . rawurlencode( $place_id ), array(",
    "                'timeout'    => $timeout > 0 ? $timeout : 8,",
    "                'sslverify'  => true,",
    "                'user-agent' => 'slp_avalon/' . self::HOURS_DB_VERSION,",
    "                'headers'    => array(",
    "                    'X-Goog-Api-Key'    => $api_key,",
    "                    'X-Goog-FieldMask'  => self::HOURS_FIELDS_NEW,",
    "                    'Content-Type'      => 'application/json',",
    "                ),",
    "            ) );",
    "",
    "            if ( is_wp_error( $res ) ) {",
    "                $out['error'] = 'TRANSPORT ' . $res->get_error_code();",
    "                return $out;",
    "            }",
    "",
    "            $code = (int) wp_remote_retrieve_response_code( $res );",
    "            $json = json_decode( (string) wp_remote_retrieve_body( $res ), true );",
    "",
    "            if ( ! is_array( $json ) ) {",
    "                $out['error'] = 'BAD_JSON http ' . $code;",
    "                return $out;",
    "            }",
    "",
    "            $out['raw'] = $json;",
    "",
    "            if ( $code !== 200 ) {",
    "                $st  = isset( $json['error']['status'] )  ? (string) $json['error']['status']  : 'HTTP_' . $code;",
    "                $msg = isset( $json['error']['message'] ) ? ' ' . (string) $json['error']['message'] : '';",
    "                $out['error'] = $st . $msg;",
    "                return $out;",
    "            }",
    "",
    "            //Already the canonical shape. Nothing to normalise.",
    "            $out['place'] = $json;",
    "            $out['ok']    = true;",
    "            return $out;",
    "        }",
    "",
    "        /**",
    "         * v0.0.27 Part 2. Legacy Place -> New-shaped Place.",
    "         *",
    "         * Only the fields the two masks ask for are mapped. Anything",
    "         * else legacy happens to return stays in 'raw' and is not",
    "         * invented a New name here.",
    "         *",
    "         * utc_offset is read under both spellings. The measurement on",
    "         * 2026-09-15 came back under the DEPRECATED key, utc_offset,",
    "         * not utc_offset_minutes, so reading only the modern one would",
    "         * have produced a null offset on every dealer.",
    "         */",
    "        private function avalon_hours_normalise_legacy( $r, $attrib ){",
    "",
    "            $id = isset( $r['place_id'] ) ? (string) $r['place_id'] : '';",
    "",
    "            $place = array(",
    "                'id'           => $id,",
    "                'name'         => $id !== '' ? 'places/' . $id : '',",
    "                'displayName'  => array(",
    "                    'text'         => isset( $r['name'] ) ? (string) $r['name'] : '',",
    "                    'languageCode' => '',",
    "                ),",
    "                'attributions' => is_array( $attrib ) ? array_values( $attrib ) : array(),",
    "            );",
    "",
    "            if ( isset( $r['business_status'] ) ) {",
    "                $place['businessStatus'] = (string) $r['business_status'];",
    "            }",
    "",
    "            if ( isset( $r['utc_offset_minutes'] ) ) {",
    "                $place['utcOffsetMinutes'] = (int) $r['utc_offset_minutes'];",
    "            } elseif ( isset( $r['utc_offset'] ) ) {",
    "                $place['utcOffsetMinutes'] = (int) $r['utc_offset'];",
    "            }",
    "",
    "            if ( isset( $r['opening_hours'] ) && is_array( $r['opening_hours'] ) ) {",
    "",
    "                $oh  = $r['opening_hours'];",
    "                $reg = array();",
    "",
    "                if ( array_key_exists( 'open_now', $oh ) ) {",
    "                    //Carried across but NEVER to be rendered from cache.",
    "                    //It is computed at fetch time and the positive TTL",
    "                    //is 30 days. Open/closed is a render-time question,",
    "                    //answered from periods and utcOffsetMinutes.",
    "                    $reg['openNow'] = (bool) $oh['open_now'];",
    "                }",
    "",
    "                if ( isset( $oh['weekday_text'] ) && is_array( $oh['weekday_text'] ) ) {",
    "                    $reg['weekdayDescriptions'] = array_values( $oh['weekday_text'] );",
    "                }",
    "",
    "                if ( isset( $oh['periods'] ) && is_array( $oh['periods'] ) ) {",
    "                    $periods = array();",
    "                    foreach ( $oh['periods'] as $p ) {",
    "                        if ( ! is_array( $p ) ) {",
    "                            continue;",
    "                        }",
    "                        $one  = array();",
    "                        $open = $this->avalon_hours_point( isset( $p['open'] ) ? $p['open'] : null );",
    "                        if ( $open === null ) {",
    "                            //No open point is not a period. Legacy emits",
    "                            //no period at all for a closed day, which is",
    "                            //why a seven-line weekday_text can arrive",
    "                            //beside five periods.",
    "                            continue;",
    "                        }",
    "                        $one['open'] = $open;",
    "                        //A 24-hour place has an open point and NO close",
    "                        //point. Emitting a null close would be a lie;",
    "                        //omitting the key is what New does too.",
    "                        $close = $this->avalon_hours_point( isset( $p['close'] ) ? $p['close'] : null );",
    "                        if ( $close !== null ) {",
    "                            $one['close'] = $close;",
    "                        }",
    "                        $periods[] = $one;",
    "                    }",
    "                    $reg['periods'] = $periods;",
    "                }",
    "",
    "                $place['regularOpeningHours'] = $reg;",
    "            }",
    "",
    "            return $place;",
    "        }",
    "",
    "        /**",
    "         * v0.0.27 Part 2. Legacy \"0800\" -> array( day, hour, minute ).",
    "         *",
    "         * Left-padded before splitting because legacy writes midnight as",
    "         * \"0000\" but has been seen to write it as \"000\" when a period",
    "         * is synthesised. substr on a three-character string would read",
    "         * hour 00 and minute 0, which is right by luck at midnight and",
    "         * wrong everywhere else.",
    "         */",
    "        private function avalon_hours_point( $p ){",
    "",
    "            if ( ! is_array( $p ) || ! isset( $p['time'] ) ) {",
    "                return null;",
    "            }",
    "",
    "            $t = preg_replace( '/\\D/', '', (string) $p['time'] );",
    "            if ( $t === '' ) {",
    "                return null;",
    "            }",
    "            $t = str_pad( $t, 4, '0', STR_PAD_LEFT );",
    "",
    "            return array(",
    "                'day'    => isset( $p['day'] ) ? (int) $p['day'] : 0,",
    "                'hour'   => (int) substr( $t, 0, 2 ),",
    "                'minute' => (int) substr( $t, 2, 2 ),",
    "            );",
    "        }",
    "",
    "        public function avalon_rest_protected_slugs(){",
]).rstrip(CRLF)


NEW_METHODS = (
    'public function avalon_hours_details( $place_id ){',
    'private function avalon_hours_details_legacy( $place_id, $api_key, $timeout ){',
    'private function avalon_hours_details_new( $place_id, $api_key, $timeout ){',
    'private function avalon_hours_normalise_legacy( $r, $attrib ){',
    'private function avalon_hours_point( $p ){',
)


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v027-part2.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v027-part2  slp_avalon 0.0.27  PART 2 of 4 (fetch layer)")
    print("")

    name = 'class.slp_avalon.php'
    md5, size = PINS[name]
    php, got_md5, got_size = read_exact(os.path.join(src_dir, name))
    if got_md5 != md5 or got_size != size:
        sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes\n"
                 "       src_dir must hold the PART 1 OUTPUT, not the v0.0.26 input.".format(
                     name, got_md5, got_size, md5, size))
    print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
    print("")

    places_endpoint_before = php.count(
        "const PLACES_ENDPOINT       = 'https://maps.googleapis.com/maps/api/place/textsearch/json';")

    # ---- patches ----------------------------------------------------------
    php = sub_once(php, CONST_ANCHOR, CONST_BLOCK, 'Place Details constants')
    php = sub_once(php, CONFIG_ANCHOR, CONFIG_BLOCK, "api selector in avalon_hours_config()")
    php = sub_once(php, METHODS_ANCHOR, METHODS_BLOCK, 'five fetch-layer methods')
    print("")

    # ---- self-checks ------------------------------------------------------
    for c in ('HOURS_ENDPOINT_LEGACY', 'HOURS_ENDPOINT_NEW',
              'HOURS_FIELDS_LEGACY', 'HOURS_FIELDS_NEW'):
        check(php.count('const ' + c) == 1, 'constant declared once: ' + c)

    check(php.count("'api'               => ( defined('AVALON_HOURS_API')") == 1,
          'api selector present in the config')

    for m in NEW_METHODS:
        check(php.count(m) == 1, 'method declared once: ' + m.split('(')[0].split()[-1])

    check(php.count(METHODS_ANCHOR) == 1,
          'the anchor method was moved, not duplicated')

    check(php.count(
        "const PLACES_ENDPOINT       = 'https://maps.googleapis.com/maps/api/place/textsearch/json';")
        == places_endpoint_before == 1,
        'PLACES_ENDPOINT untouched, the resolver is not in scope')

    check(php.count('{') == php.count('}'), 'php braces balance')

    # The key reaches exactly two places and no others: legacy's query
    # parameter and New's auth header. Asserting only that it is absent from
    # logs would pass just as happily against a caller that never sends it
    # at all, so the count is pinned AND both sites are located by name.
    seg = php[php.find('public function avalon_hours_details( $place_id ){'):
              php.find('public function avalon_rest_protected_slugs(){')]
    check(seg.count('=> $api_key,') == 2 and php.count('=> $api_key,') == 2,
          'the key is assigned in exactly two places, both inside the new block')
    check("'key'      => $api_key," in php,
          'site 1 is the legacy query parameter')
    check("'X-Goog-Api-Key'    => $api_key," in php,
          'site 2 is the New auth header')

    # And it reaches no log, no error string and no returned value.
    for bad in ("self::log( $api_key", "$out['error'] = $api_key", "'error' => $api_key",
                "$out['raw'] = $api_key", "$out['place'] = $api_key"):
        check(php.count(bad) == 0, 'key never reaches: ' + repr(bad))

    # New must send the mask. Omitting it is an error at the API, silently
    # producing a fetch layer that can never succeed once enabled.
    i = php.find('private function avalon_hours_details_new')
    j = php.find('private function avalon_hours_normalise_legacy')
    check("'X-Goog-FieldMask'  => self::HOURS_FIELDS_NEW," in php[i:j],
          'the New caller sends a field mask')
    check("'X-Goog-Api-Key'    => $api_key," in php[i:j],
          'the New caller sends the key as a header, not a URL parameter')

    # Legacy must test the status FIELD, not only the HTTP code.
    i = php.find('private function avalon_hours_details_legacy')
    j = php.find('private function avalon_hours_details_new')
    check("$status !== 'OK'" in php[i:j],
          'the legacy caller tests the status field')

    check(php.count("$res['error'] = substr( (string) $res['error'], 0, 190 );") == 1,
          'errors are truncated to the last_error column width')
    print("")

    # ---- output -----------------------------------------------------------
    out_md5, out_size, crs = write_exact(os.path.join(out_dir, name), php)
    print("  output    {:<24} {} {} bytes  CR={}".format(name, out_md5, out_size, crs))

    print("")
    print("  note      no database write, no cron hook, no CLI, no shortcode.")
    print("  note      slp_avalon.php is not an input at Part 2; the version")
    print("            header already reads 0.0.27 from Part 1.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
