#!/usr/bin/env python3
"""
score-placeid-matches.py  r2  2026-09-14
SLP Dealer Guard - score a resolved place_id against the feed row it was
resolved for, and adjudicate the collision pairs without a human eye.

THE QUESTION THIS ANSWERS

Fourteen place IDs are each held by two dealer_keys. That means one of two
opposite things and they want opposite actions:

  merge      the two rows are one business - the dealer count is wrong
  MIS-RESOLVE the two rows are two businesses and ONE place ID is wrong -
             that dealer will publish another marina's opening hours

The CSV feed cannot tell them apart. The CSV is what PRODUCED the wrong ID;
comparing it to itself only re-confirms two distinct dealers, which the
telephone numbers already said. The comparison that settles it is the feed
row against what Google returns FOR THAT PLACE ID.

ONE CALL PER PLACE ID, NOT PER DEALER. A collision pair shares an ID, so
both keys are scored against one response held in memory. Fourteen groups
is fourteen calls.

WHAT IS SCORED, AND WHY IN THIS ORDER

Ranked by discriminative power, not by column order in the feed.

  coords     <=500m +40   <=5km +15   <=25km 0   >25km VETO
             No formatting noise at all. This is the term that settles
             Ohio Valley Boats: forty miles is one subtraction.
  phone      exact +35, both present and different VETO
             Near-unique per business. It is what found all 14 groups.
  host       match +15, both present and different -10, never a veto
             A dealer chain shares one website across every location, so a
             mismatch is evidence and a match is weak. Premier Boating
             Centers has three locations in this feed.
  street+zip match +15
             The street NUMBER and the postcode are clean. The address TEXT
             is the noisy part - that is what NOT_ESTABLISHMENT is made of.
  name       token overlap >= 0.5 +10
             Feed names and Google names diverge constantly. "Rockingham
             Marina" against "Rockingham Marina Seattle".

  >= 60 ok      25-59 suspect      < 25 failed
  ANY VETO FORCES suspect, whatever the total.

Email is deliberately absent. Google does not return it. It is useful for
de-duplicating the feed against itself and for nothing here.

BAD COORDINATES ARE SKIPPED, NOT FAILED. Ten dealers sit at 0,0 and one
carries a longitude of -9,838,239. Feeding those into a distance test
manufactures eleven false mis-resolves out of a data-entry defect.
coord_state() is imported from the resolver and anything but "ok" drops the
distance term entirely - no credit, no veto, and the row is marked so the
weakened evidence is visible rather than silently averaged away.

NOTHING GOOGLE RETURNS IS EVER WRITTEN DOWN. place_id is the only Places
field exempt from the caching restriction - placeids.json says so in its
own note. So every response is compared, scored and DISCARDED. The CSV this
writes holds derived numbers and booleans only: the score, the verdict, which
terms fired, which vetoes fired. No name, no address, no phone, no website,
no coordinates. Read the writer at the bottom and confirm it for yourself.

THE KEY IS READ FROM GOOGLE_PLACES_API_KEY ONLY. Never a flag, never a file,
never echoed. Every error string passes through the resolver's _scrub().

    # stage 1 - dry run, spends nothing, prints what it would call
    python3 score-placeid-matches.py --placeids <f> --feeds <dir> --out-dir <dir>

    # stage 1 - live, 14 calls
    python3 score-placeid-matches.py --placeids <f> --feeds <dir> --out-dir <dir> \
        --live --max-calls 14

    # the controls. run this before trusting any verdict.
    python3 score-placeid-matches.py --selftest

STAGE 1 IS ALSO THE CALIBRATION SET. The telephone test already gave a free
verdict for all 14 groups. If this scorer does not reproduce those 14, the
weights are wrong and nothing should be spent on the other 288.
"""

import argparse
import csv
import hashlib
import importlib.util
import json
import math
import os
import re
import sys
import time

PLACEIDS_MD5 = "b7b0b5b4f030d4f546d1263331f5e5b3"
PLACEIDS_LEN = 37393

FEEDS = (("dlrloc.csv", "AURA"), ("DLAvalon.csv", "AVALON"), ("DLTahoe.csv", "TAHOE"))

DETAILS_ENDPOINT = "https://maps.googleapis.com/maps/api/place/details/json"

# Minimal field mask. Every field here is used by a scoring term; nothing is
# requested "in case". Basic + Contact Data only - no Atmosphere SKU.
DETAILS_FIELDS = ",".join((
    "name",
    "formatted_address",
    "geometry/location",
    "formatted_phone_number",
    "international_phone_number",
    "website",
    "business_status",
    "types",
))

# ---- the weights. Starting point, not a pin. Re-tune against stage 1. ----
W_COORD_NEAR   = 40      # <= 500 m
W_COORD_MID    = 15      # <= 5 km
W_COORD_FAR    = 0       # <= 25 km
D_NEAR_M       = 500
D_MID_M        = 5000
D_VETO_M       = 25000   # beyond this, veto

W_PHONE        = 35
W_HOST         = 15
W_HOST_MISS    = -10
W_STREET_ZIP   = 15
W_NAME         = 10
NAME_JACCARD   = 0.5

T_OK           = 60
T_SUSPECT      = 25

# a pair is adjudicated only when the winner leads by at least this much
PAIR_MARGIN    = 15

OK, SUSPECT, FAILED = "ok", "suspect", "failed"


# ---------------------------------------------------------------- plumbing

def load_resolver(path):
    """Import resolve-placeids.py by path. The hyphen blocks a normal import."""
    if not os.path.isfile(path):
        sys.exit("cannot find the resolver at %s" % path)
    spec = importlib.util.spec_from_file_location("rp", path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    need = ("load_feed", "build_dealers", "norm_phone", "norm_host",
            "norm_name", "norm_street", "norm_postal", "parse_float",
            "coord_state", "_scrub")
    for fn in need:
        if not hasattr(mod, fn):
            sys.exit("the resolver at %s has no %s()" % (path, fn))
    return mod


def md5_of(path):
    with open(path, "rb") as fh:
        data = fh.read()
    return hashlib.md5(data).hexdigest(), len(data)


def haversine_m(lat1, lng1, lat2, lng2):
    r = 6371008.8
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp = math.radians(lat2 - lat1)
    dl = math.radians(lng2 - lng1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(math.sqrt(a))


def street_number(s):
    """Leading house number only. '4131 Deer Road SW.' -> '4131'."""
    m = re.match(r"\s*(\d+)", s or "")
    return m.group(1) if m else ""


def postal_from_text(s):
    """US 5-digit or Canadian A1A 1A1, wherever it sits in the string."""
    s = (s or "").upper()
    m = re.search(r"\b(\d{5})(?:-\d{4})?\b", s)
    if m:
        return m.group(1)
    m = re.search(r"\b([A-Z]\d[A-Z])\s*(\d[A-Z]\d)\b", s)
    if m:
        return m.group(1) + m.group(2)
    return ""


# ------------------------------------------------------------- the scoring

def score_one(rp, dealer, g):
    """Score one feed row against one Place Details response.

    dealer: the feed record from build_dealers().
    g:      a dict with keys name, formatted_address, lat, lng, phone,
            website, business_status. All of it is discarded by the caller.

    Returns a dict of derived values only.
    """
    terms, vetoes, notes = {}, [], []
    total = 0

    # ---- phone and postcode first: they can veto the distance veto ----
    # CALIBRATED AGAINST THE 14 KNOWN PAIRS, NOT CHOSEN. Two of them -
    # Germaine Marine at 771 km and BAY OUTBOARD MARINE at 334 km - share a
    # city, a postcode AND a telephone number while their feed coordinates
    # disagree by hundreds of kilometres. That is a geocoding defect in the
    # feed, not a wrong place ID, and an unqualified distance veto would
    # have called both of them mis-resolves. So: an exact phone match or an
    # exact postcode match suppresses the distance veto and records the
    # coordinate defect instead. Neither suppressor fires on Ohio Valley
    # (61 km, different phone, different postcode) or on OVERBY (26 km,
    # no phone, different postcode), which are the two the veto is for.
    _fph = rp.norm_phone(dealer.get("phone", ""))
    _gph = rp.norm_phone(g.get("phone", "") or "")
    phone_exact = bool(_fph and _gph and _fph == _gph)

    _fzip = (dealer.get("zip") or "").upper().replace(" ", "")
    _gzip = postal_from_text(g.get("formatted_address", "") or "")
    zip_exact = bool(_fzip and _gzip and _fzip == _gzip)

    # ---- coordinates -------------------------------------------------
    flat = rp.parse_float(dealer.get("lat"))
    flng = rp.parse_float(dealer.get("lng"))
    cstate = rp.coord_state(flat, flng)
    dist = None
    if cstate != "ok":
        notes.append("feed_coords_" + cstate)
        terms["coord"] = 0
    elif g.get("lat") is None or g.get("lng") is None:
        notes.append("google_coords_missing")
        terms["coord"] = 0
    else:
        dist = haversine_m(flat, flng, g["lat"], g["lng"])
        if dist <= D_NEAR_M:
            terms["coord"] = W_COORD_NEAR
        elif dist <= D_MID_M:
            terms["coord"] = W_COORD_MID
        elif dist <= D_VETO_M:
            terms["coord"] = W_COORD_FAR
        elif phone_exact or zip_exact:
            terms["coord"] = 0
            notes.append("feed_coord_defect_%dkm" % int(round(dist / 1000.0)))
            notes.append("distance_veto_suppressed_by_" +
                         ("phone" if phone_exact else "postcode"))
        else:
            terms["coord"] = 0
            vetoes.append("DISTANCE")
    total += terms["coord"]

    # ---- phone -------------------------------------------------------
    fph, gph = _fph, _gph
    if fph and gph:
        if fph == gph:
            terms["phone"] = W_PHONE
        else:
            terms["phone"] = 0
            vetoes.append("PHONE")
    else:
        terms["phone"] = 0
        notes.append("phone_absent_one_side")
    total += terms["phone"]

    # ---- website host ------------------------------------------------
    fh = rp.norm_host(dealer.get("url", ""))
    gh = rp.norm_host(g.get("website", "") or "")
    if fh and gh:
        terms["host"] = W_HOST if fh == gh else W_HOST_MISS
    else:
        terms["host"] = 0
        notes.append("host_absent_one_side")
    total += terms["host"]

    # ---- street number + postcode ------------------------------------
    fnum = street_number(dealer.get("address", ""))
    fzip, gzip = _fzip, _gzip
    gaddr = g.get("formatted_address", "") or ""
    gnum = street_number(gaddr)
    if fnum and gnum and fzip and gzip:
        terms["street_zip"] = W_STREET_ZIP if (fnum == gnum and fzip == gzip) else 0
    else:
        terms["street_zip"] = 0
        notes.append("street_or_zip_unparsed")
    total += terms["street_zip"]

    # ---- name token overlap ------------------------------------------
    ft = set(rp.norm_name(dealer.get("name", "")).split())
    gt = set(rp.norm_name(g.get("name", "") or "").split())
    if ft and gt:
        jac = len(ft & gt) / float(len(ft | gt))
        terms["name"] = W_NAME if jac >= NAME_JACCARD else 0
    else:
        jac = 0.0
        terms["name"] = 0
        notes.append("name_absent_one_side")
    total += terms["name"]

    # ---- verdict -----------------------------------------------------
    if vetoes:
        verdict = SUSPECT                       # a veto forces suspect
    elif total >= T_OK:
        verdict = OK
    elif total >= T_SUSPECT:
        verdict = SUSPECT
    else:
        verdict = FAILED

    if (g.get("business_status") or "").upper() not in ("", "OPERATIONAL"):
        notes.append("business_status_" + (g.get("business_status") or "").lower())

    return {
        "score": total,
        "verdict": verdict,
        "vetoes": vetoes,
        "terms": terms,
        "notes": notes,
        "distance_m": None if dist is None else int(round(dist)),
        "name_jaccard": round(jac, 2),
        "coord_state": cstate,
    }


# ------------------------------------------------------------------ the call

def call_details(place_id, api_key, timeout=20):
    """One legacy Place Details call. Returns (fields, err).

    The key travels in the URL on this endpoint, so no exception text, URL
    or reason is ever returned unscrubbed.
    """
    import urllib.parse
    import urllib.request
    import urllib.error

    params = {"place_id": place_id, "fields": DETAILS_FIELDS, "key": api_key}
    url = DETAILS_ENDPOINT + "?" + urllib.parse.urlencode(params)
    try:
        with urllib.request.urlopen(url, timeout=timeout) as resp:
            data = json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        return None, "HTTP %s" % e.code
    except Exception as e:                                    # noqa: BLE001
        return None, "TRANSPORT " + type(e).__name__

    status = str(data.get("status", "")).upper()
    if status in ("REQUEST_DENIED", "OVER_QUERY_LIMIT"):
        return None, "FATAL " + status
    if status != "OK":
        return None, "STATUS " + (status or "none")

    r = data.get("result") or {}
    loc = ((r.get("geometry") or {}).get("location") or {})
    return {
        "name": r.get("name", ""),
        "formatted_address": r.get("formatted_address", ""),
        "lat": loc.get("lat"),
        "lng": loc.get("lng"),
        "phone": r.get("formatted_phone_number")
                 or r.get("international_phone_number") or "",
        "website": r.get("website", ""),
        "business_status": r.get("business_status", ""),
    }, ""


def masked_url(place_id):
    import urllib.parse
    return DETAILS_ENDPOINT + "?" + urllib.parse.urlencode(
        {"place_id": place_id, "fields": DETAILS_FIELDS, "key": "<from-env>"})


# ------------------------------------------------------- pair adjudication

def adjudicate(a, b, pair_gap_m=None, zip_differs=None):
    """Two scored keys sharing one place_id. Say only what is supported.

    r2 FIXED THREE THINGS, EACH FOUND BY MEASUREMENT AFTER r1 SHIPPED.

    1. THE CLEAN BRANCH SHORT-CIRCUITED THE MARGIN. r1 returned SHARED
       PREMISES the moment both rows scored ok with no veto, so a 40-point
       gap was never looked at. Kettle Marine scored 100 and 60 - one row
       11 m from the Google place, the other 14.2 km - and r1 called it one
       premises. The margin test now runs first.

    2. THERE WAS NO ZERO-COST PRE-FLIGHT. Two feed rows in different
       postcodes more than 500 m apart are two street addresses, and two
       street addresses cannot both be one Google place. That test needs no
       API call and it catches Kettle, which the scores alone did not.

    3. BOTH WRONG WAS UNCORROBORATED. A phone veto on both sides has two
       readings and r1 asserted the worse one. Measured:

         LAKE DELTON   both 157 m away, name overlap 0.00, host MISMATCH
                       -> three fields disagree. The place is a different
                          business at the same address. Genuinely wrong.
         SPEND-A-DAY   both ~1 km away, host MATCHES on both rows, street
                       number and postcode match on one
                       -> the place is the dealer. The telephone number on
                          one side is simply stale. Not a wrong ID.

       So a phone-only veto that sits alongside a matching host or a
       matching street+postcode is reported as a phone disagreement, not as
       a wrong place. Asserting BOTH WRONG there would have sent somebody
       to re-resolve a correct ID.

    pair_gap_m / zip_differs are the pre-flight inputs and may be None when
    either row has unusable feed coordinates. None means the test does not
    run, never that it passed.
    """
    va, vb = a["verdict"], b["verdict"]
    sa, sb = a["score"], b["score"]
    da, db = a["distance_m"], b["distance_m"]

    def corroborated(r):
        return r["terms"]["host"] > 0 or r["terms"]["street_zip"] > 0

    def coord_defect(r):
        """The distance term already refused to veto on this row because a
        phone or a postcode said it is the same business. A second rule that
        reads the same distance as evidence contradicts the first one. r2
        shipped with exactly that bug and it called BAY OUTBOARD and Germaine
        mis-resolves on a 334 km and a 772 km FEED COORDINATE ERROR."""
        return any(n.startswith("feed_coord_defect") for n in r.get("notes", []))

    # ---- 1. vetoes first -------------------------------------------------
    if a["vetoes"] and b["vetoes"]:
        only_phone = (set(a["vetoes"]) | set(b["vetoes"])) == {"PHONE"}
        if only_phone and (corroborated(a) or corroborated(b)):
            hi = "A" if sa >= sb else "B"
            return ("PHONE DISAGREES",
                    "both rows veto on the telephone number, but the website "
                    "host and/or the street number and postcode still match. "
                    "The place is the dealer; a phone number is stale - "
                    "Google's or the feed's. %s is the stronger row. Do not "
                    "re-resolve on this evidence." % hi)
        return ("BOTH WRONG",
                "both rows veto and nothing else corroborates the match. The "
                "place_id belongs to neither. Clear both and re-resolve.")

    if a["vetoes"] and not b["vetoes"] and vb == OK:
        return ("MIS-RESOLVE, A IS WRONG",
                "A vetoes on %s, B matches cleanly. The ID belongs to B."
                % ",".join(a["vetoes"]))
    if b["vetoes"] and not a["vetoes"] and va == OK:
        return ("MIS-RESOLVE, B IS WRONG",
                "B vetoes on %s, A matches cleanly. The ID belongs to A."
                % ",".join(b["vetoes"]))

    # ---- 2. the zero-cost pre-flight -------------------------------------
    two_locations = bool(zip_differs and pair_gap_m is not None
                         and pair_gap_m > 500)

    # ---- 3. the per-row distances, which the scores can hide -------------
    defect_a, defect_b = coord_defect(a), coord_defect(b)
    dist_split = (da is not None and db is not None
                  and not defect_a and not defect_b
                  and abs(da - db) > 1000)

    if two_locations or dist_split:
        if da is not None and db is not None:
            lo, hi = ("A", "B") if da > db else ("B", "A")
            far, near = (da, db) if da > db else (db, da)
            why = ("the Google place is %d m from %s and %d m from %s"
                   % (near, hi, far, lo))
        elif abs(sa - sb) >= PAIR_MARGIN:
            lo, hi = ("A", "B") if sa < sb else ("B", "A")
            why = "%s scores %d against %s at %d" % (hi, max(sa, sb), lo, min(sa, sb))
        else:
            return ("TWO LOCATIONS, SIDE UNKNOWN",
                    "different postcodes %d m apart, so one ID must be wrong, "
                    "but nothing here says which. A human has to look."
                    % pair_gap_m)
        lead = ("TWO LOCATIONS" if two_locations else "LOCATION MISMATCH")
        return ("%s, %s IS WRONG" % (lead, lo),
                "%s. Two rows at two addresses cannot both be one place. "
                "Clear %s and re-resolve." % (why.capitalize(), lo))

    # ---- 3b. name the coordinate defect rather than calling it weak ------
    if defect_a != defect_b:
        side = "A" if defect_a else "B"
        other = b if defect_a else a
        km = 0
        for n in (a if defect_a else b).get("notes", []):
            if n.startswith("feed_coord_defect_"):
                km = n.rsplit("_", 1)[-1]
        return ("FEED COORD DEFECT, %s" % side,
                "%s agrees with the place on telephone or postcode but its "
                "feed latitude and longitude are %s away. The place_id is "
                "not the problem - the coordinate is. This is Option 4 data "
                "hygiene, NOT a re-resolve. %s scores %d and is unaffected."
                % (side, km, "B" if defect_a else "A", other["score"]))

    # ---- 4. the margin, before the clean branch --------------------------
    if abs(sa - sb) >= PAIR_MARGIN:
        lo, hi = ("A", "B") if sa < sb else ("B", "A")
        return ("WEAKER SIDE, %s" % lo,
                "%s scores %d against %s at %d, a margin of %d, with no veto "
                "and no location split. Look before acting."
                % (hi, max(sa, sb), lo, min(sa, sb), abs(sa - sb)))

    # ---- 5. only now is it one premises ----------------------------------
    if va == OK and vb == OK:
        return ("SHARED PREMISES",
                "both rows match the same business on every term, within %s. "
                "Two dealer accounts at one location - a merge question, not "
                "a defect."
                % ("%d m" % pair_gap_m if pair_gap_m is not None else "an unmeasurable gap"))

    return ("UNDETERMINED",
            "scores %d and %d, no veto, no margin and no location split. The "
            "evidence available does not separate these two." % (sa, sb))


# ------------------------------------------------------------------ output

CSV_FIELDS = [
    "dealer_key", "place_id", "score", "verdict", "vetoes",
    "t_coord", "t_phone", "t_host", "t_street_zip", "t_name",
    "distance_m", "name_jaccard", "coord_state", "notes",
]


def write_scores(path, rows):
    """Derived values only. No Google-returned text ever reaches this file.

    Compare CSV_FIELDS above against score_one()'s return: every column is a
    number, a verdict word, or a term name. That is the caching position.
    """
    with open(path, "w", encoding="utf-8", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=CSV_FIELDS, lineterminator="\n")
        w.writeheader()
        for r in rows:
            w.writerow({
                "dealer_key": r["dealer_key"],
                "place_id": r["place_id"],
                "score": r["score"],
                "verdict": r["verdict"],
                "vetoes": "|".join(r["vetoes"]),
                "t_coord": r["terms"]["coord"],
                "t_phone": r["terms"]["phone"],
                "t_host": r["terms"]["host"],
                "t_street_zip": r["terms"]["street_zip"],
                "t_name": r["terms"]["name"],
                "distance_m": "" if r["distance_m"] is None else r["distance_m"],
                "name_jaccard": r["name_jaccard"],
                "coord_state": r["coord_state"],
                "notes": "|".join(r["notes"]),
            })


# ------------------------------------------------- the zero-cost pre-flight

def pair_preflight(rp, da, db):
    """Feed row against feed row. No API call, no Google.

    Returns (gap_metres_or_None, zip_differs_or_None). None means the test
    could not run - a row with unusable coordinates - never that it passed.
    """
    la, lo = rp.parse_float(da.get("lat")), rp.parse_float(da.get("lng"))
    lb, lc = rp.parse_float(db.get("lat")), rp.parse_float(db.get("lng"))
    usable = (rp.coord_state(la, lo) == "ok" and rp.coord_state(lb, lc) == "ok")
    gap = int(round(haversine_m(la, lo, lb, lc))) if usable else None
    za = (da.get("zip") or "").upper().replace(" ", "")
    zb = (db.get("zip") or "").upper().replace(" ", "")
    zdiff = (za != zb) if (za and zb) else None
    return gap, zdiff


def rows_from_csv(path):
    """Rebuild scored rows from match-scores.csv. Nothing is re-fetched."""
    out = []
    for r in csv.DictReader(open(path, encoding="utf-8-sig")):
        out.append({
            "dealer_key": r["dealer_key"],
            "place_id": r["place_id"],
            "score": int(r["score"]),
            "verdict": r["verdict"],
            "vetoes": [v for v in (r["vetoes"] or "").split("|") if v],
            "terms": {
                "coord": int(r["t_coord"]), "phone": int(r["t_phone"]),
                "host": int(r["t_host"]), "street_zip": int(r["t_street_zip"]),
                "name": int(r["t_name"]),
            },
            "notes": [n for n in (r["notes"] or "").split("|") if n],
            "distance_m": int(r["distance_m"]) if r["distance_m"] else None,
            "name_jaccard": float(r["name_jaccard"]),
            "coord_state": r["coord_state"],
        })
    return out


# --------------------------------------------------------------- self-test

def selftest(rp):
    """A check labelled a control is not one until it has failed.

    Six scenarios with a known answer. Each must land on its expected
    verdict, and the two vetoes must each be shown firing and not firing.
    """
    def dealer(**kw):
        d = {"dealer_key": "test", "name": "", "address": "", "city": "",
             "state": "", "zip": "", "phone": "", "url": "",
             "lat": None, "lng": None, "unit": "", "brands": "",
             "source_rows": ""}
        d.update(kw)
        return d

    cases = []

    # 1 - a clean match
    cases.append(("clean match", dealer(
        name="Ohio Valley Boats, Leesville Lake", address="4131 Deer Road SW.",
        zip="44695", phone="740-269-5371", url="http://ohiovalleyboats.com",
        lat=40.4650, lng=-81.2200), {
        "name": "Ohio Valley Boats", "formatted_address": "4131 Deer Rd SW, Bowerston, OH 44695, USA",
        "lat": 40.4651, "lng": -81.2201, "phone": "(740) 269-5371",
        "website": "https://www.ohiovalleyboats.com/", "business_status": "OPERATIONAL",
    }, OK, []))

    # 2 - the Ohio Valley shape: the OTHER row against the same ID
    cases.append(("mis-resolve, 40 mi + wrong phone", dealer(
        name="Ohio Valley Boats, Seneca Lake", address="16592 Lashley Rd.",
        zip="43780", phone="740-685-2333", url="http://ohiovalleyboats.com",
        lat=39.9300, lng=-81.4500), {
        "name": "Ohio Valley Boats", "formatted_address": "4131 Deer Rd SW, Bowerston, OH 44695, USA",
        "lat": 40.4651, "lng": -81.2201, "phone": "(740) 269-5371",
        "website": "https://www.ohiovalleyboats.com/", "business_status": "OPERATIONAL",
    }, SUSPECT, ["DISTANCE", "PHONE"]))

    # 3 - two accounts at one marina: identical everything, must NOT veto
    cases.append(("shared premises", dealer(
        name="Premier Boating Centers", address="1234 Marina Way",
        zip="77301", phone="936-588-1214", url="http://premierboating.com",
        lat=30.3120, lng=-95.4560), {
        "name": "Premier Boating Center", "formatted_address": "1234 Marina Way, Conroe, TX 77301, USA",
        "lat": 30.3120, "lng": -95.4560, "phone": "(936) 588-1214",
        "website": "https://premierboating.com", "business_status": "OPERATIONAL",
    }, OK, []))

    # 4 - feed coords at 0,0: distance must be SKIPPED, never vetoed
    cases.append(("zero coords are skipped not vetoed", dealer(
        name="Kettle Marine", address="7518 State Rd. 60", zip="53012",
        phone="(262) 665-1111", url="http://kettlemarine.com",
        lat=0.0, lng=0.0), {
        "name": "Kettle Marine Inc", "formatted_address": "7518 State Rd 60, Cedarburg, WI 53012, USA",
        "lat": 43.3000, "lng": -87.9800, "phone": "262-665-1111",
        "website": "http://www.kettlemarine.com", "business_status": "OPERATIONAL",
    }, OK, []))

    # 5 - a chain: host matches, location does not. host must not rescue it.
    cases.append(("chain host cannot rescue a distance veto", dealer(
        name="Premier Boating Centers", address="99 Gulf Rd", zip="77707",
        phone="", url="http://premierboating.com",
        lat=30.0800, lng=-94.1400), {
        "name": "Premier Boating Center", "formatted_address": "1234 Marina Way, Conroe, TX 77301, USA",
        "lat": 30.3120, "lng": -95.4560, "phone": "(936) 588-1214",
        "website": "https://premierboating.com", "business_status": "OPERATIONAL",
    }, SUSPECT, ["DISTANCE"]))

    # 6 - OVERBY shape at 23 km: inside the veto line, no phone, no host.
    #     No veto is possible, and the score must collapse on its own to
    #     failed. Expecting suspect here was MY error, not the scorer's:
    #     name agreement between two branches of one dealer is the weakest
    #     evidence in the set and 10 points is the right answer.
    cases.append(("no phone, no host, 23 km -> failed on score alone", dealer(
        name="Overby Marine Sales and Service", address="472 US HWY 1",
        zip="27596", phone="", url="",
        lat=36.0300, lng=-78.4600), {
        "name": "Overby Marine Sales & Service", "formatted_address": "480 Bobbitt Rd, Kittrell, NC 27544, USA",
        "lat": 36.2400, "lng": -78.4300, "phone": "252-438-5338",
        "website": "", "business_status": "OPERATIONAL",
    }, FAILED, []))

    # 7 - Germaine shape. 771 km apart, same phone, same postcode.
    #     The distance veto MUST be suppressed: this is a feed coordinate
    #     defect, not a wrong place ID. Without this case the scorer would
    #     report two of the fourteen pairs as mis-resolves that are not.
    cases.append(("coord defect: phone matches, veto suppressed", dealer(
        name="Germaine Marine AZ", address="7315 E Main St", zip="85207",
        phone="480-655-9055", url="http://germainemarine.com",
        lat=33.4150, lng=-111.5900), {
        "name": "Germaine Marine", "formatted_address": "7315 E Main St, Mesa, AZ 85207, USA",
        "lat": 40.1000, "lng": -105.1000, "phone": "(480) 655-9055",
        "website": "https://germainemarine.com", "business_status": "OPERATIONAL",
    }, OK, []))

    print("  SELF-TEST - the verdicts and both vetoes must be shown firing")
    bad = 0
    for label, d, g, want_verdict, want_vetoes in cases:
        r = score_one(rp, d, g)
        okv = (r["verdict"] == want_verdict)
        okz = (sorted(r["vetoes"]) == sorted(want_vetoes))
        mark = "[ OK ]" if (okv and okz) else "[FAIL]"
        if not (okv and okz):
            bad += 1
        print("    %s %-42s score %3d  %-8s vetoes %-14s"
              % (mark, label, r["score"], r["verdict"], ",".join(r["vetoes"]) or "-"))
        if not okv:
            print("           expected verdict %s" % want_verdict)
        if not okz:
            print("           expected vetoes  %s" % (",".join(want_vetoes) or "-"))

    # the adjudicator itself
    print()
    print("  SELF-TEST - pair adjudication")
    a = score_one(rp, cases[0][1], cases[0][2])
    b = score_one(rp, cases[1][1], cases[1][2])
    v, _why = adjudicate(a, b)
    want = "MIS-RESOLVE, B IS WRONG"
    mark = "[ OK ]" if v == want else "[FAIL]"
    if v != want:
        bad += 1
    print("    %s ohio valley pair -> %s" % (mark, v))

    a2 = score_one(rp, cases[2][1], cases[2][2])
    v2, _ = adjudicate(a2, a2, pair_gap_m=0, zip_differs=False)
    mark = "[ OK ]" if v2 == "SHARED PREMISES" else "[FAIL]"
    if v2 != "SHARED PREMISES":
        bad += 1
    print("    %s two clean rows    -> %s" % (mark, v2))

    # ---- r2 CONTROLS. Each of these was a WRONG ANSWER in r1. ----
    def fake(score, vetoes=(), host=0, street=0, dist=None):
        return {"score": score, "verdict": OK if score >= T_OK else SUSPECT,
                "vetoes": list(vetoes),
                "terms": {"coord": 0, "phone": 0, "host": host,
                          "street_zip": street, "name": 0},
                "notes": [], "distance_m": dist, "name_jaccard": 0.0,
                "coord_state": "ok", "dealer_key": "x", "place_id": "y"}

    r2cases = [
        # Kettle Marine: r1 said SHARED PREMISES. 11 m against 14,230 m.
        ("kettle: clean scores, 14 km apart, different zip",
         fake(100, dist=11), fake(60, dist=14230), 14230, True,
         "TWO LOCATIONS, B IS WRONG"),
        # the margin must not be swallowed by the clean branch
        ("clean scores, 40-point margin, same zip",
         fake(100, dist=20), fake(60, dist=25), 30, False,
         "WEAKER SIDE, B"),
        # Lake Delton: phone veto both sides, nothing corroborates
        ("phone veto both sides, no host, no street",
         fake(30, ("PHONE",), host=-10, dist=157),
         fake(30, ("PHONE",), host=-10, dist=157), 0, False,
         "BOTH WRONG"),
        # Spend-A-Day: phone veto both sides, host AND street corroborate
        ("phone veto both sides, host+street match",
         fake(45, ("PHONE",), host=15, street=15, dist=1064),
         fake(30, ("PHONE",), host=15, dist=914), 150, True,
         "PHONE DISAGREES"),
        # BAY OUTBOARD / Germaine: a suppressed distance must not come back
        # as evidence through the distance-split rule
        ("suppressed distance is not a location split",
         {"score": 60, "verdict": OK, "vetoes": [],
          "terms": {"coord": 0, "phone": 35, "host": 15, "street_zip": 0, "name": 10},
          "notes": ["feed_coord_defect_334km", "distance_veto_suppressed_by_phone"],
          "distance_m": 333601, "name_jaccard": 1.0, "coord_state": "ok"},
         fake(115, dist=16), None, False,
         "FEED COORD DEFECT, A"),
        # a pre-flight that cannot run must not be treated as a pass
        ("unusable coords: pre-flight does not run",
         fake(65, dist=None), fake(60, dist=None), None, None,
         "SHARED PREMISES"),
    ]
    print()
    print("  SELF-TEST - r2 fixes (each of these was wrong in r1)")
    for label, ra, rb, gap, zd, want in r2cases:
        got, _ = adjudicate(ra, rb, pair_gap_m=gap, zip_differs=zd)
        mk = "[ OK ]" if got == want else "[FAIL]"
        if got != want:
            bad += 1
        print("    %s %-44s -> %s" % (mk, label, got))
        if got != want:
            print("           expected %s" % want)

    print()
    if bad:
        print("  SELF-TEST FAILED on %d case(s). Do not spend a call." % bad)
        return 2
    print("  self-test passed. the scorer has been shown capable of failing.")
    return 0


# -------------------------------------------------------------------- main

def main():
    ap = argparse.ArgumentParser(
        description="Score resolved place IDs against their feed rows.")
    ap.add_argument("--placeids", default="")
    ap.add_argument("--feeds", default="")
    ap.add_argument("--out-dir", default="")
    ap.add_argument("--resolver", default="")
    ap.add_argument("--all", action="store_true",
                    help="score every resolved key, not only the collisions")
    ap.add_argument("--live", action="store_true", help="actually spend calls")
    ap.add_argument("--max-calls", type=int, default=0)
    ap.add_argument("--sleep", type=float, default=0.12)
    ap.add_argument("--no-pin", action="store_true")
    ap.add_argument("--selftest", action="store_true")
    ap.add_argument("--from-scores", default="",
                    help="re-adjudicate an existing match-scores.csv. No "
                         "network, no key, nothing spent.")
    a = ap.parse_args()

    here = os.path.dirname(os.path.abspath(__file__))
    rp = load_resolver(a.resolver or os.path.join(here, "resolve-placeids.py"))

    print("score-placeid-matches.py  r2")
    print()

    if a.selftest:
        return selftest(rp)

    if a.from_scores:
        rc = selftest(rp)
        if rc:
            return rc
        print()
        if not a.feeds or not a.out_dir:
            sys.exit("--from-scores still needs --feeds (for the pre-flight) "
                     "and --out-dir")
        rows = []
        for fname, label in FEEDS:
            fp = os.path.join(a.feeds, fname)
            if not os.path.isfile(fp):
                sys.exit("missing feed %s" % fp)
            rows.extend(rp.load_feed(fp, label)[0])
        byk = {d["dealer_key"]: d for d in rp.build_dealers(rows)[0]}

        m, n = md5_of(a.from_scores)
        print("  scores    %s" % a.from_scores)
        print("    md5     %s" % m)
        print("    bytes   %d" % n)
        scored = rows_from_csv(a.from_scores)
        print("  %d scored row(s). NO NETWORK, NO KEY, NOTHING SPENT." % len(scored))
        print()

        bypid = {}
        for r in scored:
            bypid.setdefault(r["place_id"], []).append(r)
        pairs = []
        for pid, rs in sorted(bypid.items()):
            if len(rs) != 2:
                continue
            rs.sort(key=lambda r: r["dealer_key"])
            gap, zdiff = pair_preflight(rp, byk[rs[0]["dealer_key"]],
                                            byk[rs[1]["dealer_key"]])
            verdict, why = adjudicate(rs[0], rs[1], gap, zdiff)
            pairs.append((pid, rs[0], rs[1], verdict, why))
            print("    %-28s  %-7s  %s"
                  % (pid[:28],
                     "-" if gap is None else "%dm" % gap, verdict))
        print()
        counts = {}
        for _, _, _, v, _w in pairs:
            head = v.split(",")[0]
            counts[head] = counts.get(head, 0) + 1
        for k in sorted(counts):
            print("    %-24s %d" % (k, counts[k]))
        print()
        os.makedirs(a.out_dir, exist_ok=True)
        write_adjudication(a.out_dir, pairs,
                           "Re-adjudicated offline from `%s` (%s). No calls."
                           % (os.path.basename(a.from_scores), m))
        return 0

    for req in ("placeids", "feeds", "out_dir"):
        if not getattr(a, req):
            sys.exit("--%s is required (or pass --selftest)" % req.replace("_", "-"))

    rc = selftest(rp)
    if rc:
        return rc
    print()

    pid_md5, pid_len = md5_of(a.placeids)
    print("  placeids  %s" % a.placeids)
    print("    md5     %s" % pid_md5)
    print("    bytes   %d" % pid_len)
    if not a.no_pin and (pid_md5 != PLACEIDS_MD5 or pid_len != PLACEIDS_LEN):
        sys.exit("    PIN FAIL - expected %s / %d" % (PLACEIDS_MD5, PLACEIDS_LEN))
    print("    pinned  OK" if not a.no_pin else "    pin     skipped")

    doc = json.load(open(a.placeids, encoding="utf-8"))
    resolved = doc["dealers"]

    rows = []
    for fname, label in FEEDS:
        p = os.path.join(a.feeds, fname)
        if not os.path.isfile(p):
            sys.exit("missing feed %s" % p)
        rows.extend(rp.load_feed(p, label)[0])
    dealers = rp.build_dealers(rows)[0]
    byk = {d["dealer_key"]: d for d in dealers}
    print("  feeds     %d rows  ->  %d unique dealer_keys" % (len(rows), len(byk)))

    groups = {}
    for k, rec in resolved.items():
        groups.setdefault(rec["place_id"], []).append(k)
    colliding = {p: sorted(v) for p, v in groups.items() if len(v) > 1}

    if a.all:
        scope = sorted(groups.items())
        print("  scope     ALL - %d place_id(s), %d dealer key(s)"
              % (len(groups), len(resolved)))
    else:
        scope = sorted(colliding.items())
        print("  scope     COLLISIONS ONLY - %d place_id(s), %d dealer key(s)"
              % (len(colliding), sum(len(v) for v in colliding.values())))
    print("  calls     one per place_id = %d" % len(scope))
    print()

    api_key = ""
    if a.live:
        api_key = os.environ.get("GOOGLE_PLACES_API_KEY", "").strip()
        if not api_key:
            sys.exit("--live needs GOOGLE_PLACES_API_KEY in the environment. "
                     "It is never accepted as a flag.")
        if a.max_calls <= 0:
            sys.exit("--live requires an explicit --max-calls ceiling.")
        if a.max_calls < len(scope):
            print("  NOTE: --max-calls %d is below the %d place IDs in scope. "
                  "The run will stop early." % (a.max_calls, len(scope)))
    else:
        print("  DRY RUN - nothing is spent. The calls that would be made:")
        for i, (pid, keys) in enumerate(scope[:6], 1):
            print("    %d. %s   (%d key(s))" % (i, pid, len(keys)))
        if len(scope) > 6:
            print("    ... and %d more" % (len(scope) - 6))
        print()
        print("  request shape (key comes from the environment, never printed):")
        print("    " + masked_url(scope[0][0] if scope else "PLACE_ID"))
        print()
        print("  re-run with --live --max-calls %d to score." % len(scope))
        return 0

    os.makedirs(a.out_dir, exist_ok=True)
    scored, pairs, spent, fatal = [], [], 0, ""

    for pid, keys in scope:
        if spent >= a.max_calls:
            print("  ceiling reached at %d call(s), stopping." % spent)
            break
        g, err = call_details(pid, api_key, timeout=20)
        spent += 1
        time.sleep(a.sleep)
        if err:
            err = rp._scrub(err, api_key)
            print("    %s  %s" % (pid, err))
            if err.startswith("FATAL"):
                fatal = err
                break
            continue

        here_rows = []
        for k in keys:
            d = byk.get(k)
            if not d:
                print("    %s  key %s not in the feeds" % (pid, k))
                continue
            r = score_one(rp, d, g)
            r["dealer_key"] = k
            r["place_id"] = pid
            scored.append(r)
            here_rows.append(r)
            print("    %-14s %-28s score %3d  %-8s %s"
                  % (k, pid[:28], r["score"], r["verdict"],
                     ("veto " + ",".join(r["vetoes"])) if r["vetoes"] else ""))

        if len(here_rows) == 2:
            gap, zdiff = pair_preflight(rp, byk[here_rows[0]["dealer_key"]],
                                            byk[here_rows[1]["dealer_key"]])
            verdict, why = adjudicate(here_rows[0], here_rows[1], gap, zdiff)
            pairs.append((pid, here_rows[0], here_rows[1], verdict, why))
            print("       -> %s" % verdict)
        # g goes out of scope here. Nothing from it is retained.
        g = None

    print()
    print("  %d call(s) spent" % spent)
    if fatal:
        print("  stopped on %s" % fatal)

    csv_path = os.path.join(a.out_dir, "match-scores.csv")
    write_scores(csv_path, scored)
    m, n = md5_of(csv_path)
    print("  wrote %s   %s   %d bytes" % (csv_path, m, n))

    write_adjudication(a.out_dir, pairs, "%d call(s), one per place_id." % spent)
    return 1 if fatal else 0


def write_adjudication(out_dir, pairs, provenance):
    if pairs:
        md = [ "# Collision adjudication",
               "",
               "Adjudicated by `build/score-placeid-matches.py` r2. " + provenance,
               "",
               "No value Google returned is recorded here or in "
               "`match-scores.csv` - only derived scores and verdicts.",
               "",
               "| place_id | verdict | key A | score A | key B | score B |",
               "|---|---|---|---|---|---|" ]
        for pid, ra, rb, verdict, _why in pairs:
            md.append("| `%s` | **%s** | `%s` | %d | %s | `%s` | %d | %s |"
                      % (pid, verdict, ra["dealer_key"], ra["score"],
                         "-" if ra["distance_m"] is None else "%d m" % ra["distance_m"],
                         rb["dealer_key"], rb["score"],
                         "-" if rb["distance_m"] is None else "%d m" % rb["distance_m"]))
        md.append("")
        for pid, ra, rb, verdict, why in pairs:
            md.append("### `%s` - %s" % (pid, verdict))
            md.append("")
            md.append(why)
            md.append("")
            for tag, r in (("A", ra), ("B", rb)):
                md.append("- **%s** `%s` score **%d** %s%s%s"
                          % (tag, r["dealer_key"], r["score"], r["verdict"],
                             ("  vetoes: " + ",".join(r["vetoes"])) if r["vetoes"] else "",
                             ("  distance %d m" % r["distance_m"]) if r["distance_m"] is not None else ""))
            if verdict.startswith("WEAKER SIDE"):
                md.append("")
                md.append("No command. This verdict is a score gap with no veto "
                          "and no location split - it is a prompt to look, not a "
                          "finding. Check the two addresses against the place "
                          "before touching the row.")
            if "IS WRONG" in verdict:
                tail = verdict.split(",")[-1].strip()
                loser = ra if tail.startswith("A") else rb
                md.append("")
                md.append("s0.241: `places reset` cannot clear this. It matches "
                          "only `place_status = failed` and it never touches "
                          "`place_id`. Until Part 3e ships a correction path:")
                md.append("")
                md.append("```")
                md.append("wp --skip-plugins=revslider db query \"UPDATE wp_avalon_dealer_places "
                          "SET place_id = NULL, place_status = 'pending', error_count = 0, "
                          "last_error = NULL, place_checked_at = NULL "
                          "WHERE address_key = '%s'\"" % loser["dealer_key"])
                md.append("```")
            if verdict == "BOTH WRONG":
                md.append("")
                md.append("```")
                for r in (ra, rb):
                    md.append("wp --skip-plugins=revslider db query \"UPDATE wp_avalon_dealer_places "
                              "SET place_id = NULL, place_status = 'pending', error_count = 0, "
                              "last_error = NULL, place_checked_at = NULL "
                              "WHERE address_key = '%s'\"" % r["dealer_key"])
                md.append("```")
            md.append("")
        md_path = os.path.join(out_dir, "collision-adjudication.md")
        with open(md_path, "w", encoding="utf-8", newline="\n") as fh:
            fh.write("\n".join(md))
        m, n = md5_of(md_path)
        print("  wrote %s   %s   %d bytes" % (md_path, m, n))


if __name__ == "__main__":
    sys.exit(main())
