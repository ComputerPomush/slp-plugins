#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
probe-legacy-adapter.py  r3  2026-09-14

Exercises call_text_search_legacy() in resolve-placeids 1.4.0 against
RECORDED responses. No network, no key, no spend.

WHY RECORDED AND NOT MOCKED. The OK fixture below is the verbatim body Google
returned for 'Premier Boating Center Conroe TX 77301' on 2026-09-14, pasted
out of the live session. A fixture invented from the documentation would
agree with whatever the adapter believes; this one came from the API.

WHAT THIS IS FOR. The legacy contract is HTTP 200 with the outcome in a
status FIELD, so the failure paths cannot be reached by returning an error
status line. Each one has to be handed a 200 carrying the right body.

THE KEY PAIR. The key MUST appear in the URL - that is how legacy
authenticates - and MUST NOT appear in anything returned. Asserting only the
second half would pass against an adapter that never sends the key at all.

Usage: python3 probe-legacy-adapter.py <path-to-resolve-placeids.py>
"""

import importlib.util
import io
import json
import sys
import urllib.error

PATH = sys.argv[1] if len(sys.argv) > 1 else "build/out14/resolve-placeids.py"
spec = importlib.util.spec_from_file_location("rp", PATH)
rp = importlib.util.module_from_spec(spec)
spec.loader.exec_module(rp)

KEY = "AIzaSyTESTKEYTESTKEYTESTKEYTESTKEY1234"

OK_BODY = {
    "status": "OK",
    "results": [{
        "business_status": "OPERATIONAL",
        "formatted_address": "779 Interstate 45 S, Conroe, TX 77301, USA",
        "geometry": {"location": {"lat": 30.2985904, "lng": -95.4651568}},
        "name": "Premier Boating Centers - Conroe",
        "place_id": "ChIJeZWbAEXKQIYRgqNb27Tey_8",
        "rating": 4.8,
        "types": ["establishment", "point_of_interest", "store"],
    }],
}

PASS = FAIL = 0
SEEN_URLS = []


def check(ok, label, detail=""):
    global PASS, FAIL
    if ok:
        PASS += 1
        print("    [PASS] %s%s" % (label, (" " + detail) if detail else ""))
    else:
        FAIL += 1
        print("    [FAIL] %s%s" % (label, (" " + detail) if detail else ""))


def install(body=None, http_code=None, raise_exc=None):
    """Replace urlopen for one scenario and record the URL it was given."""
    def fake(url, timeout=None):
        SEEN_URLS.append(url)
        if raise_exc is not None:
            raise raise_exc
        if http_code is not None:
            raise urllib.error.HTTPError(url, http_code, "err", None, None)
        payload = json.dumps(body).encode("utf-8")

        class R(io.BytesIO):
            def __enter__(self):
                return self

            def __exit__(self, *a):
                return False
        return R(payload)
    rp.urllib_request_urlopen_backup = None
    import urllib.request
    urllib.request.urlopen = fake


def call(**kw):
    install(**kw)
    return rp.call_text_search_legacy(
        "Premier Boating Center, 779 IH-455, Conroe TX 77301, USA",
        KEY, bias=(30.305121297, -95.462976027), region="US")


print("\n  URL CONSTRUCTION")
data, err = call(body=OK_BODY)
url = SEEN_URLS[-1]
check(url.startswith(rp.LEGACY_ENDPOINT), "hits the legacy endpoint")
check("key=" + KEY in url, "the key IS sent, in the query string")
check("location=30.305121297%2C-95.462976027" in url, "location bias sent as lat,lng")
check("radius=50000" in url, "radius is the 50km ceiling")
check("region=us" in url, "region is a lowercase ccTLD, not the CLDR code")

print("\n  OK - NORMALISED TO THE NEW SHAPE")
check(err == "", "no error")
p = (data or {}).get("places", [None])[0]
check(p is not None, "one place returned")
check(p and p["id"] == "ChIJeZWbAEXKQIYRgqNb27Tey_8", "place_id -> id")
check(p and p["displayName"]["text"] == "Premier Boating Centers - Conroe",
      "name -> displayName.text")
check(p and p["formattedAddress"] == "779 Interstate 45 S, Conroe, TX 77301, USA",
      "formatted_address -> formattedAddress")
check(p and p["location"]["latitude"] == 30.2985904
      and p["location"]["longitude"] == -95.4651568,
      "geometry.location.lat/lng -> location.latitude/longitude")

print("\n  ZERO_RESULTS IS A MISS, NOT AN ERROR")
data, err = call(body={"status": "ZERO_RESULTS", "results": []})
check(err == "", "no error string")
check(data == {"places": []}, "empty places, so the caller records 'no result'")
check(not str(err).startswith("FATAL"), "does NOT abort the run")

print("\n  OK WITH AN EMPTY RESULTS ARRAY")
data, err = call(body={"status": "OK", "results": []})
check(err == "" and data == {"places": []}, "treated as a miss, not an IndexError")

print("\n  FATAL STATUSES STOP THE RUN")
for status in ("REQUEST_DENIED", "OVER_QUERY_LIMIT"):
    data, err = call(body={"status": status,
                           "error_message": "The provided API key is invalid."})
    check(data is None and err.startswith("FATAL"), "%s -> %s" % (status, err))

print("\n  PER-QUERY STATUSES DO NOT")
for status in ("INVALID_REQUEST", "UNKNOWN_ERROR"):
    data, err = call(body={"status": status})
    check(data is None and err == "STATUS " + status, "%s -> %s" % (status, err))
data, err = call(body={"results": []})
check(data is None and err == "STATUS no status", "missing status field named, not crashed")

print("\n  TRANSPORT")
data, err = call(http_code=503)
check(data is None and err == "HTTP 503", "HTTP error -> bare code")
data, err = call(raise_exc=TimeoutError("timed out"))
check(data is None and err == "TimeoutError", "exception -> class name only")

print("\n  THE KEY NEVER LEAVES IN AN ERROR")
leaked = []
for kw in ({"body": {"status": "REQUEST_DENIED"}},
           {"body": {"status": "INVALID_REQUEST"}},
           {"http_code": 403},
           {"raise_exc": urllib.error.URLError("failed reaching %s?key=%s"
                                               % (rp.LEGACY_ENDPOINT, KEY))}):
    _, err = call(**kw)
    if KEY in str(err):
        leaked.append(str(err))
check(not leaked, "no error string contains the key", "(%d leaks)" % len(leaked))
check(KEY in SEEN_URLS[-1], "and the key is still genuinely being sent")
check(rp._scrub("prefix %s suffix" % KEY, KEY) == "prefix <redacted> suffix",
      "_scrub redacts when handed the key directly")

print("\n  ESTABLISHMENT GUARD - s0.223")
# Recorded from the Rockingham Marina resolution on 2026-09-14: Google could
# not find the business, so it returned the STREET ADDRESS shaped exactly like
# a hit - place_id, formatted_address, and a name that IS the address.
ADDRESS_BODY = {
    "status": "OK",
    "results": [{
        "formatted_address": "1900 W Nickerson St #112, Seattle, WA 98119",
        "geometry": {"location": {"lat": 47.6541, "lng": -122.3745}},
        "name": "1900 W Nickerson St #112",
        "place_id": "ChIJd3EWG70VkFQRgEsT1Az-8a8",
        "types": ["subpremise"],
    }],
}
data, err = call(body=ADDRESS_BODY)
check(data is None, "an address-shaped result is NOT accepted as a place")
check(err.startswith("NOT_ESTABLISHMENT"), "named reason, so it lands in failures.csv", "| " + err)
check("subpremise" in err, "the reason carries the types Google returned")

# The good fixture must still pass - a guard that rejects everything is not a guard.
data, err = call(body=OK_BODY)
check(err == "" and data["places"][0]["id"] == "ChIJeZWbAEXKQIYRgqNb27Tey_8",
      "a real establishment is still accepted")

for types in (["street_address"], ["premise"], ["geocode"], []):
    b = {"status": "OK", "results": [dict(ADDRESS_BODY["results"][0], types=types)]}
    data, err = call(body=b)
    check(data is None and err.startswith("NOT_ESTABLISHMENT"),
          "types=%-18s rejected" % (types or "(none)"))

print("\n  QUERY CONSTRUCTION - s0.222")
# unit is split OUT of address by norm_street, and build_query uses the RAW
# address, so an unconditional append spelled it twice. These five are the
# only dealers in the three feeds that carry a unit at all.
for addr, city, state, zipc in (
        ("2015 Bassett Drive Suite 300", "Mankato", "MN", "56001"),
        ("1900 W Nickerson ST #112", "Seattle", "WA", "98119"),
        ("#2 SOUTH 48TH STREET WEST", "Prince Albert", "SK", "S6V5P9"),
        ("7094 N IH 35 Suite 200", "New Braunfels", "TX", "78130"),
        ("8724 Hickory Road, Unit B", "South Chesterfield", "VA", "23803")):
    street, unit = rp.norm_street(addr)
    q = rp.build_query({"name": "Test Marine", "address": addr, "unit": unit,
                        "city": city, "state": state, "zip": zipc, "country": "US"})
    twice = q.upper().count(unit.upper()) if unit else 0
    check(twice <= 1, "unit %-10s appears once, not twice" % ("'" + unit + "'"), "| " + q[:72])

q = rp.build_query({"name": "Test Marine", "address": "100 Main St", "unit": "",
                    "city": "Alpena", "state": "MI", "zip": "49707", "country": "US"})
check(q == "Test Marine, 100 Main St, Alpena MI 49707, USA",
      "a dealer with no unit is unchanged", "| " + q)

print("\n  %d passed, %d failed, %d total\n" % (PASS, FAIL, PASS + FAIL))
sys.exit(0 if FAIL == 0 else 1)
