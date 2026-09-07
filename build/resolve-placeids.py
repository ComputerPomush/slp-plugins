#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
resolve-placeids.py  --  SLP Dealer Guard, decision 80.

Resolves one Google Place ID per unique physical dealer across the three brand
feeds, offline and deterministically, then (only when explicitly asked) spends
Text Search calls to fill the gaps.

TWO CONSTRAINTS DRIVE THE DESIGN.  Neither is fixable at the source, because
the people who maintain the Tahoe and Avalon exports cannot change their
headers or populate their empty columns.  So the tool absorbs both:

  1. HEADER DRIFT.  Aura writes "ShipTo", Tahoe and Avalon write "Ship To".
     Every physical header is folded to a canonical key -- BOM stripped,
     trimmed, lowercased, non-alphanumerics removed -- so "ShipTo",
     "Ship To", "SHIP_TO" and "ship-to" all land on `shipto`.  A synonym
     table then maps canonical keys onto logical fields, so future drift
     ("Postal Code", "Website", "Telephone") is absorbed without a code
     change.  Every resolution is REPORTED, per file, in header-map.csv.
     Nothing is silent.

  2. EMPTY Identifier.  Populated 313/313 on Aura, 0/96 on Tahoe, 0/229 on
     Avalon.  It is therefore NEVER used as a join key.  Identity comes from
     a deterministic `dealer_key` derived from the address, which every feed
     populates.  Identifier is carried as metadata only, and a mismatch
     between two Aura rows sharing a dealer_key is reported, not obeyed.

Default mode is DRY RUN: it reads the feeds, builds the unique-dealer set,
writes the exact queries it WOULD send, and spends nothing.  Live mode needs
--live, an explicit --max-calls ceiling, and the key in the environment.

Usage
-----
  python build/resolve-placeids.py --feeds <dir> --out build/placeid
  python build/resolve-placeids.py --feeds <dir> --out build/placeid --live --max-calls 350

The API key is read from GOOGLE_PLACES_API_KEY only.  It is never accepted as
a command-line argument: argv is visible in process listings and lands in shell
history.  The key is never written to any output file or log line.

Outputs (all LF; build/** is -text in .gitattributes so bytes round-trip)
  header-map.csv      physical header -> logical field, per feed, plus gaps
  dealers-unique.csv  one row per unique physical dealer, with brand set
  queries.csv         the dry-run artefact: exactly what would be sent
  anomalies.csv       coordinate, name-variant, conflict and near-miss flags
  placeids.json       the durable cache; resume-safe, only place_id persisted
  adjudication.csv    settles the near-miss pairs by measurement: same place_id
                      means same dealer, different place_id means they are not.
                      Reads 'unresolved' for every pair until a live run.
  review-DELETE-AFTER-TRIAGE.csv
                      live-mode only.  Google's returned name and address, for
                      eyeballing each match.  This is NOT a cache: place_id is
                      the only Places field that may be retained, so delete
                      this file once triage is done and never commit it.  The
                      first row of the file repeats that instruction.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import os
import re
import sys
import time
import unicodedata
from collections import Counter, defaultdict, OrderedDict

TOOL_VERSION = "1.1.0"

# --------------------------------------------------------------------------
# 1.  HEADER RESOLUTION
# --------------------------------------------------------------------------

# Logical field -> the canonical header keys that may carry it.  Order matters
# only for reporting; the first physical header that folds to any of these
# wins.  Add to this table, never to the call sites.
FIELD_SYNONYMS: "OrderedDict[str, tuple]" = OrderedDict([
    ("name",       ("name", "dealername", "storename", "companyname", "business", "businessname")),
    ("address",    ("address", "address1", "addressline1", "street", "streetaddress", "addr")),
    ("address2",   ("address2", "addressline2", "suite", "unit", "addr2")),
    ("city",       ("city", "town", "municipality")),
    ("state",      ("state", "province", "stateprovince", "prov", "region")),
    ("zip",        ("zip", "zipcode", "postal", "postalcode", "postcode", "zip5")),
    ("country",    ("country", "countrycode", "nation")),
    ("email",      ("email", "emailaddress", "contactemail")),
    ("url",        ("url", "website", "weburl", "websiteurl", "web", "homepage")),
    ("phone",      ("phone", "telephone", "phonenumber", "tel", "contactphone")),
    ("lat",        ("latitude", "lat")),
    ("lng",        ("longitude", "lng", "long", "lon")),
    ("brand",      ("brand", "brands", "make")),
    ("identifier", ("identifier", "id", "dealerid", "dealercode", "accountnumber")),
    ("customer",   ("customer", "customername", "customerno", "customernumber")),
    ("shipto",     ("shipto", "shiptoname", "shiptolocation")),
    ("checkbox01", ("checkbox01", "featured", "cb01")),
])

REQUIRED_FIELDS = ("name", "address", "city", "state", "zip")

# All three feeds ship the same column ORDER.  If a required field cannot be
# resolved by name, fall back to this position -- and shout about it.
POSITIONAL_FALLBACK = [
    "name", "address", "address2", "city", "state", "zip", "country", "email",
    "url", "checkbox01", "lat", "lng", "brand", "identifier", "customer",
    "shipto", "phone",
]


def fold_header(raw: str) -> str:
    """Fold a physical header to a canonical key.

    Strips the UTF-8 BOM and any zero-width characters, trims, lowercases and
    removes every non-alphanumeric.  This is the single line that makes
    'ShipTo' and 'Ship To' the same column.
    """
    s = raw.replace("\ufeff", "").replace("\u200b", "")
    s = unicodedata.normalize("NFKD", s)
    s = s.strip().lower()
    return re.sub(r"[^a-z0-9]+", "", s)


def resolve_headers(physical: list) -> tuple:
    """Return (field -> column index, report rows, list of hard problems)."""
    folded = [fold_header(h) for h in physical]
    lookup = {}
    report = []
    problems = []

    claimed = set()
    for field, syns in FIELD_SYNONYMS.items():
        idx = None
        via = ""
        for syn in syns:
            for i, f in enumerate(folded):
                if f == syn and i not in claimed:
                    idx, via = i, syn
                    break
            if idx is not None:
                break
        if idx is not None:
            lookup[field] = idx
            claimed.add(idx)
            report.append({
                "field": field,
                "physical_header": physical[idx],
                "folded": folded[idx],
                "matched_synonym": via,
                "column_index": idx,
                "how": "by-name",
            })

    # Positional rescue for required fields only, loudly reported.
    for field in REQUIRED_FIELDS:
        if field in lookup:
            continue
        try:
            idx = POSITIONAL_FALLBACK.index(field)
        except ValueError:
            idx = None
        if idx is not None and idx < len(physical) and idx not in claimed:
            lookup[field] = idx
            claimed.add(idx)
            report.append({
                "field": field,
                "physical_header": physical[idx],
                "folded": folded[idx],
                "matched_synonym": "",
                "column_index": idx,
                "how": "POSITIONAL-FALLBACK",
            })
            problems.append(
                "%s resolved by POSITION %d ('%s'), not by name -- verify this"
                % (field, idx, physical[idx])
            )
        else:
            problems.append("REQUIRED field '%s' could not be resolved" % field)

    for i, h in enumerate(physical):
        if i not in claimed:
            report.append({
                "field": "",
                "physical_header": h,
                "folded": folded[i],
                "matched_synonym": "",
                "column_index": i,
                "how": "unmapped",
            })

    return lookup, report, problems


# --------------------------------------------------------------------------
# 2.  VALUE NORMALISATION
# --------------------------------------------------------------------------

US_STATES = {
    "ALABAMA": "AL", "ALASKA": "AK", "ARIZONA": "AZ", "ARKANSAS": "AR",
    "CALIFORNIA": "CA", "COLORADO": "CO", "CONNECTICUT": "CT", "DELAWARE": "DE",
    "DISTRICT OF COLUMBIA": "DC", "FLORIDA": "FL", "GEORGIA": "GA",
    "HAWAII": "HI", "IDAHO": "ID", "ILLINOIS": "IL", "INDIANA": "IN",
    "IOWA": "IA", "KANSAS": "KS", "KENTUCKY": "KY", "LOUISIANA": "LA",
    "MAINE": "ME", "MARYLAND": "MD", "MASSACHUSETTS": "MA", "MICHIGAN": "MI",
    "MINNESOTA": "MN", "MISSISSIPPI": "MS", "MISSOURI": "MO", "MONTANA": "MT",
    "NEBRASKA": "NE", "NEVADA": "NV", "NEW HAMPSHIRE": "NH", "NEW JERSEY": "NJ",
    "NEW MEXICO": "NM", "NEW YORK": "NY", "NORTH CAROLINA": "NC",
    "NORTH DAKOTA": "ND", "OHIO": "OH", "OKLAHOMA": "OK", "OREGON": "OR",
    "PENNSYLVANIA": "PA", "RHODE ISLAND": "RI", "SOUTH CAROLINA": "SC",
    "SOUTH DAKOTA": "SD", "TENNESSEE": "TN", "TEXAS": "TX", "UTAH": "UT",
    "VERMONT": "VT", "VIRGINIA": "VA", "WASHINGTON": "WA",
    "WEST VIRGINIA": "WV", "WISCONSIN": "WI", "WYOMING": "WY",
}
CA_PROVINCES = {
    "ALBERTA": "AB", "BRITISH COLUMBIA": "BC", "MANITOBA": "MB",
    "NEW BRUNSWICK": "NB", "NEWFOUNDLAND AND LABRADOR": "NL",
    "NEWFOUNDLAND": "NL", "NORTHWEST TERRITORIES": "NT", "NOVA SCOTIA": "NS",
    "NUNAVUT": "NU", "ONTARIO": "ON", "PRINCE EDWARD ISLAND": "PE",
    "QUEBEC": "QC", "QUÉBEC": "QC", "SASKATCHEWAN": "SK", "YUKON": "YT",
}
CA_CODES = set(CA_PROVINCES.values())
US_CODES = set(US_STATES.values())

DIRECTIONALS = {
    "NORTH": "N", "SOUTH": "S", "EAST": "E", "WEST": "W",
    "NORTHEAST": "NE", "NORTHWEST": "NW", "SOUTHEAST": "SE", "SOUTHWEST": "SW",
}
SUFFIXES = {
    "STREET": "ST", "ROAD": "RD", "AVENUE": "AVE", "AV": "AVE",
    "DRIVE": "DR", "HIGHWAY": "HWY", "HIWAY": "HWY", "BOULEVARD": "BLVD",
    "LANE": "LN", "COURT": "CT", "PLACE": "PL", "PARKWAY": "PKWY",
    "CIRCLE": "CIR", "TERRACE": "TER", "TRAIL": "TRL", "ROUTE": "RTE",
    "TURNPIKE": "TPKE", "EXPRESSWAY": "EXPY", "SQUARE": "SQ",
    "POINT": "PT", "CROSSING": "XING", "EXTENSION": "EXT",
}
UNIT_WORDS = ("STE", "SUITE", "UNIT", "APT", "APARTMENT", "BLDG", "BUILDING",
              "RM", "ROOM", "FL", "FLOOR", "#")

ZIP5 = re.compile(r"(\d{5})")
CA_POSTAL = re.compile(r"([A-Z]\d[A-Z])\s*(\d[A-Z]\d)")


def squash(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())


def norm_text(s: str) -> str:
    s = unicodedata.normalize("NFKD", s or "")
    s = "".join(c for c in s if not unicodedata.combining(c))
    return squash(s.upper())


def norm_name(s: str) -> str:
    s = norm_text(s).replace("&", " AND ")
    s = re.sub(r"[.,'\"]", "", s)
    s = re.sub(r"\b(INC|LLC|LLP|LTD|CO|CORP|COMPANY|INCORPORATED)\b", "", s)
    return squash(re.sub(r"[^A-Z0-9 ]+", " ", s))


def norm_street(s: str) -> tuple:
    """Return (street_key, unit_token).  Unit is held out of the key: one
    building is one Google place, and a suite number that drifts between feeds
    must not split a dealer in two."""
    s = norm_text(s).replace("&", " AND ")
    s = re.sub(r"[.,]", " ", s)
    s = re.sub(r"\bU\s*S\s*HIGHWAY\b|\bUS\s*HWY\b|\bUS-\b", "US HWY ", s)
    s = re.sub(r"\bSTATE\s+(HIGHWAY|HWY|ROUTE|RTE|RD)\b", "STATE HWY", s)
    s = re.sub(r"\bCOUNTY\s+(ROAD|RD|ROUTE|RTE)\b", "COUNTY RD", s)
    s = re.sub(r"[^A-Z0-9# ]+", " ", s)

    tokens = squash(s).split()
    unit_bits, keep = [], []
    i = 0
    while i < len(tokens):
        t = tokens[i]
        if t in UNIT_WORDS or t.startswith("#"):
            unit_bits.append(t)
            if i + 1 < len(tokens):
                unit_bits.append(tokens[i + 1])
                i += 1
        else:
            keep.append(DIRECTIONALS.get(t, SUFFIXES.get(t, t)))
        i += 1
    return squash(" ".join(keep)), squash(" ".join(unit_bits))


def norm_state(raw: str, country_hint: str = "") -> str:
    s = norm_text(raw).replace(".", "")
    if len(s) == 2 and (s in US_CODES or s in CA_CODES):
        return s
    if s in US_STATES:
        return US_STATES[s]
    if s in CA_PROVINCES:
        return CA_PROVINCES[s]
    return s


def norm_postal(raw: str) -> tuple:
    """Return (normalised postal, inferred country or '')."""
    s = norm_text(raw).replace(" ", "").replace("-", "")
    m = CA_POSTAL.match(norm_text(raw).replace("-", " "))
    if m:
        return m.group(1) + m.group(2), "CA"
    if re.fullmatch(r"[A-Z]\d[A-Z]\d[A-Z]\d", s):
        return s, "CA"
    m = ZIP5.search(s)
    if m:
        return m.group(1), "US"
    return s, ""


def norm_country(raw: str, state: str, postal_hint: str) -> str:
    s = norm_text(raw).replace(".", "")
    if s in ("US", "USA", "UNITED STATES", "UNITED STATES OF AMERICA"):
        return "US"
    if s in ("CA", "CAN", "CANADA"):
        return "CA"
    if postal_hint:
        return postal_hint
    if state in CA_CODES and state not in US_CODES:
        return "CA"
    if state in US_CODES:
        return "US"
    return s or ""


def norm_phone(raw: str) -> str:
    d = re.sub(r"\D", "", raw or "")
    if len(d) == 11 and d.startswith("1"):
        d = d[1:]
    return d if len(d) == 10 else ""


def norm_host(raw: str) -> str:
    s = (raw or "").strip().lower()
    if not s:
        return ""
    s = re.sub(r"^https?://", "", s)
    s = s.split("/")[0].split("?")[0]
    s = re.sub(r"^www\d?\.", "", s)
    return s


def parse_float(raw: str):
    try:
        return float(str(raw).strip())
    except (TypeError, ValueError):
        return None


def coord_state(lat, lng) -> str:
    if lat is None or lng is None:
        return "missing"
    if lat == 0.0 and lng == 0.0:
        return "zero"
    if not (-90.0 <= lat <= 90.0) or not (-180.0 <= lng <= 180.0):
        return "out_of_range"
    return "ok"


# --------------------------------------------------------------------------
# 3.  LOAD
# --------------------------------------------------------------------------

def md5_of(path: str) -> str:
    h = hashlib.md5()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 16), b""):
            h.update(chunk)
    return h.hexdigest()


def load_feed(path: str, brand_label: str):
    """Read one feed.  Returns (rows, header_report, problems, physical)."""
    with open(path, newline="", encoding="utf-8-sig") as fh:
        reader = csv.reader(fh)
        physical = next(reader)
        lookup, report, problems = resolve_headers(physical)
        rows = []
        for n, raw in enumerate(reader, start=2):
            if not any((c or "").strip() for c in raw):
                continue

            def get(field):
                i = lookup.get(field)
                if i is None or i >= len(raw):
                    return ""
                return (raw[i] or "").strip()

            street, unit = norm_street(get("address"))
            postal, postal_country = norm_postal(get("zip"))
            state = norm_state(get("state"))
            country = norm_country(get("country"), state, postal_country)
            lat, lng = parse_float(get("lat")), parse_float(get("lng"))

            rows.append({
                "feed": os.path.basename(path),
                "feed_brand": brand_label,
                "row": n,
                "name": get("name"),
                "name_key": norm_name(get("name")),
                "address": get("address"),
                "address2": get("address2"),
                "street_key": street,
                "unit": unit,
                "city": get("city"),
                "city_key": norm_text(get("city")).replace(".", ""),
                "state": state,
                "zip": postal,
                "zip_raw": get("zip"),
                "country": country,
                "url": get("url"),
                "host": norm_host(get("url")),
                "phone": get("phone"),
                "phone_key": norm_phone(get("phone")),
                "email": get("email"),
                "brand": get("brand"),
                "identifier": get("identifier"),
                "customer": get("customer"),
                "shipto": get("shipto"),
                "lat": lat,
                "lng": lng,
                "coord": coord_state(lat, lng),
            })
    return rows, report, problems, physical


def dealer_key(r: dict) -> str:
    """Deterministic identity for one physical dealer.

    Built only from fields every feed populates.  Identifier is deliberately
    absent: it is empty in two of the three feeds, so keying on it would split
    the same dealer into three unlinkable records.
    """
    basis = "|".join([r["country"], r["state"], r["city_key"], r["zip"], r["street_key"]])
    return hashlib.sha1(basis.encode("utf-8")).hexdigest()[:12]


# --------------------------------------------------------------------------
# 4.  MERGE
# --------------------------------------------------------------------------

def build_dealers(all_rows: list):
    groups = defaultdict(list)
    for r in all_rows:
        r["dealer_key"] = dealer_key(r)
        groups[r["dealer_key"]].append(r)

    dealers, anomalies = [], []
    for key, rows in sorted(groups.items()):
        names = sorted({r["name_key"] for r in rows if r["name_key"]})
        phones = sorted({r["phone_key"] for r in rows if r["phone_key"]})
        hosts = sorted({r["host"] for r in rows if r["host"]})
        ids = sorted({r["identifier"] for r in rows if r["identifier"]})
        feeds = sorted({r["feed"] for r in rows})
        # Two independent signals. The Brand column is free text and uses BOTH
        # as a value; feed membership is structural, because DLTahoe.csv and
        # DLAvalon.csv are filtered views of the Aura superset.
        brands = set()
        for r in rows:
            b = r["brand"].strip().upper()
            if b == "BOTH":
                brands |= {"AVALON", "TAHOE"}
            elif b:
                brands.add(b)
            if r["feed_brand"] in ("TAHOE", "AVALON"):
                brands.add(r["feed_brand"])
        brands = sorted(brands)

        # Prefer a row with usable coordinates as the representative.
        rep = next((r for r in rows if r["coord"] == "ok"), rows[0])

        flags = []
        if len(names) > 1:
            flags.append("name_variant")
        if len(phones) > 1:
            flags.append("phone_conflict")
        if len(hosts) > 1:
            flags.append("host_conflict")
        if len(ids) > 1:
            flags.append("identifier_conflict")
        if rep["coord"] != "ok":
            flags.append("coord_" + rep["coord"])
        if not hosts:
            flags.append("no_url")
        if not phones:
            flags.append("no_phone")
        if len({r["unit"] for r in rows if r["unit"]}) > 1:
            flags.append("unit_variant")

        d = {
            "dealer_key": key,
            "name": rep["name"],
            "name_variants": " | ".join(names) if len(names) > 1 else "",
            "address": rep["address"],
            "unit": rep["unit"],
            "city": rep["city"],
            "state": rep["state"],
            "zip": rep["zip"],
            "country": rep["country"],
            "phone": rep["phone"],
            "url": rep["url"],
            "lat": rep["lat"] if rep["coord"] == "ok" else "",
            "lng": rep["lng"] if rep["coord"] == "ok" else "",
            "coord": rep["coord"],
            "brands": ",".join(brands),
            "in_aura_feed": "Y" if any(r["feed_brand"] == "AURA" for r in rows) else "N",
            "feeds": ",".join(feeds),
            "identifiers": ",".join(ids),
            "row_count": len(rows),
            "source_rows": ";".join("%s:%d" % (r["feed"], r["row"]) for r in rows),
            "flags": ",".join(flags),
        }
        dealers.append(d)

        for f in flags:
            if f in ("no_url", "no_phone"):
                continue
            anomalies.append({
                "dealer_key": key, "flag": f, "confidence": "", "signals": "",
                "name": rep["name"],
                "address": rep["address"], "city": rep["city"],
                "state": rep["state"], "zip": rep["zip"],
                "detail": {
                    "name_variant": " | ".join(names),
                    "phone_conflict": " | ".join(phones),
                    "host_conflict": " | ".join(hosts),
                    "identifier_conflict": " | ".join(ids),
                    "unit_variant": " | ".join(sorted({r["unit"] for r in rows if r["unit"]})),
                }.get(f, ""),
                "source_rows": d["source_rows"],
            })

    return dealers, anomalies


def near_misses(dealers: list):
    """Rows that did NOT merge but probably should have.  Reported, never
    merged automatically -- an over-merge silently deletes a dealer."""
    out, pairs = [], []
    by_zone = defaultdict(list)
    for d in dealers:
        by_zone[(d["country"], d["state"], d["zip"])].append(d)

    for zone, group in by_zone.items():
        if len(group) < 2:
            continue
        for i in range(len(group)):
            for j in range(i + 1, len(group)):
                a, b = group[i], group[j]
                pa = norm_phone(a["phone"])
                pb = norm_phone(b["phone"])
                ha, hb = norm_host(a["url"]), norm_host(b["url"])
                reasons = []
                if pa and pa == pb:
                    reasons.append("same_phone")
                if ha and ha == hb:
                    reasons.append("same_host")
                sa, sb = norm_street(a["address"])[0], norm_street(b["address"])[0]
                ta, tb = set(sa.split()), set(sb.split())
                if ta and tb:
                    jac = len(ta & tb) / float(len(ta | tb))
                    if jac >= 0.6:
                        reasons.append("street_similarity_%.2f" % jac)
                # Same house number inside one postal code is a strong signal on
                # its own: '779 IH-455' and '779 Interstate 45 South' share no
                # other token, and 'Premier Boating Center, Conroe' is one place.
                ha_num = re.match(r"^(\d+)\b", sa)
                hb_num = re.match(r"^(\d+)\b", sb)
                if ha_num and hb_num and ha_num.group(1) == hb_num.group(1):
                    reasons.append("same_house_number")
                # Same normalised trading name in one postal code.
                if norm_name(a["name"]) and norm_name(a["name"]) == norm_name(b["name"]):
                    reasons.append("same_name")
                if reasons:
                    rs = set(reasons)
                    strong = ("same_name" in rs
                              and ("same_phone" in rs or "same_host" in rs)
                              and "same_house_number" in rs)
                    conf = "high" if strong else ("medium" if len(rs) >= 2 else "low")
                    out.append({
                        "dealer_key": a["dealer_key"], "flag": "possible_duplicate",
                        "confidence": conf, "signals": len(rs),
                        "name": a["name"], "address": a["address"],
                        "city": a["city"], "state": a["state"], "zip": a["zip"],
                        "detail": "%s <-> %s [%s] (%s)" % (
                            a["dealer_key"], b["dealer_key"],
                            ",".join(sorted(rs)), b["name"]),
                        "source_rows": a["source_rows"] + " || " + b["source_rows"],
                    })
                    pairs.append((a["dealer_key"], b["dealer_key"], sorted(rs)))
    # The report rows are unchanged from v1.0.0 so anomalies.csv still matches
    # byte for byte; the pair list is a second return value, never written here.
    return out, pairs


def build_adjudication(dealers, cache, nm_pairs):
    """Settle duplicate questions with the resolver's own output.

    Google returns one place_id per physical business.  Two dealer_keys that
    come back with the same place_id are the same dealer; two that come back
    with different ones are not.  That is a measurement, where picking through
    street strings by hand is a guess.
    """
    by_key = {d["dealer_key"]: d for d in dealers}
    pid_of = {k: v.get("place_id", "") for k, v in cache.items()}

    def corroboration(a, b):
        hits = []
        pa, pb = norm_phone(a["phone"]), norm_phone(b["phone"])
        ha, hb = norm_host(a["url"]), norm_host(b["url"])
        na, nb = norm_name(a["name"]), norm_name(b["name"])
        if pa and pa == pb:
            hits.append("same_phone")
        if ha and ha == hb:
            hits.append("same_host")
        if na and na == nb:
            hits.append("same_name")
        return hits

    flagged = {frozenset((x, y)) for x, y, _ in nm_pairs}
    rows, seen = [], set()

    def emit(ka, kb, verdict):
        pair = frozenset((ka, kb))
        if pair in seen or ka == kb:
            return
        seen.add(pair)
        a, b = by_key[ka], by_key[kb]
        hits = corroboration(a, b)
        if verdict == "same_place" and not hits:
            verdict = "same_place_uncorroborated"
        rows.append({
            "verdict": verdict,
            "place_id_a": pid_of.get(ka, ""), "place_id_b": pid_of.get(kb, ""),
            "dealer_key_a": ka, "dealer_key_b": kb,
            "name_a": a["name"], "name_b": b["name"],
            "address_a": a["address"], "address_b": b["address"],
            "city": a["city"], "state": a["state"], "zip": a["zip"],
            "corroborating": ",".join(hits),
            "was_flagged_near_miss": "Y" if pair in flagged else "N",
        })

    # Same place_id across two dealer_keys, whether or not it was ever flagged.
    groups = defaultdict(list)
    for k, pid in sorted(pid_of.items()):
        if pid and k in by_key:
            groups[pid].append(k)
    for pid, ks in sorted(groups.items()):
        if len(ks) < 2:
            continue
        for i in range(len(ks)):
            for j in range(i + 1, len(ks)):
                emit(ks[i], ks[j], "same_place")

    # Every remaining flagged pair: resolved and different, or not yet resolved.
    for ka, kb, _ in sorted(nm_pairs):
        if ka not in by_key or kb not in by_key:
            continue
        pa, pb = pid_of.get(ka, ""), pid_of.get(kb, "")
        emit(ka, kb, "distinct_confirmed" if (pa and pb) else "unresolved")

    return rows


# --------------------------------------------------------------------------
# 5.  QUERIES
# --------------------------------------------------------------------------

def build_query(d: dict) -> str:
    bits = [d["name"], d["address"]]
    if d["unit"]:
        bits.append(d["unit"])
    tail = squash("%s %s %s" % (d["city"], d["state"], d["zip"]))
    bits.append(tail)
    if d["country"]:
        bits.append("Canada" if d["country"] == "CA" else "USA")
    return squash(", ".join(b for b in bits if squash(b)))


BIAS_RADIUS_M = 50000


def build_queries(dealers: list, cache: dict) -> list:
    rows = []
    for d in dealers:
        cached = cache.get(d["dealer_key"], {}).get("place_id", "")
        rows.append({
            "dealer_key": d["dealer_key"],
            "status": "cached" if cached else "to_resolve",
            "place_id": cached,
            "query": build_query(d),
            "bias_lat": d["lat"],
            "bias_lng": d["lng"],
            "bias_radius_m": BIAS_RADIUS_M if d["lat"] != "" else "",
            "region_code": d["country"],
            "brands": d["brands"],
            "flags": d["flags"],
            "source_rows": d["source_rows"],
        })
    return rows


# --------------------------------------------------------------------------
# 6.  LIVE
# --------------------------------------------------------------------------

REVIEW_FILENAME = "review-DELETE-AFTER-TRIAGE.csv"
REVIEW_NOTICE = ("DO NOT COMMIT OR RETAIN. Google's Places policy permits "
                 "storing place_id and nothing else. Delete this file once the "
                 "matches have been eyeballed.")

ENDPOINT = "https://places.googleapis.com/v1/places:searchText"
FIELD_MASK = "places.id,places.displayName,places.formattedAddress,places.location"


def call_text_search(query: str, api_key: str, bias=None, region=""):
    import urllib.request
    import urllib.error

    body = {"textQuery": query, "maxResultCount": 1}
    if region in ("US", "CA"):
        body["regionCode"] = region
    if bias:
        body["locationBias"] = {"circle": {
            "center": {"latitude": bias[0], "longitude": bias[1]},
            "radius": float(BIAS_RADIUS_M)}}

    req = urllib.request.Request(
        ENDPOINT,
        data=json.dumps(body).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "X-Goog-Api-Key": api_key,
            "X-Goog-FieldMask": FIELD_MASK,
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.loads(resp.read().decode("utf-8")), ""
    except urllib.error.HTTPError as e:
        # Never echo the request headers: the key lives there.
        return None, "HTTP %s" % e.code
    except Exception as e:                                    # noqa: BLE001
        return None, type(e).__name__


# --------------------------------------------------------------------------
# 7.  IO
# --------------------------------------------------------------------------

def write_csv(path: str, rows: list, fields: list):
    with open(path, "w", newline="\n", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=fields, lineterminator="\n")
        w.writeheader()
        for r in rows:
            w.writerow({k: r.get(k, "") for k in fields})
    return md5_of(path), os.path.getsize(path)


def main() -> int:
    ap = argparse.ArgumentParser(description="Resolve one Google Place ID per unique dealer.")
    ap.add_argument("--feeds", required=True, help="directory holding the three CSVs")
    ap.add_argument("--out", required=True, help="output directory")
    ap.add_argument("--live", action="store_true", help="actually spend Text Search calls")
    ap.add_argument("--max-calls", type=int, default=0, help="hard ceiling on live calls")
    ap.add_argument("--sleep", type=float, default=0.12, help="seconds between live calls")
    ap.add_argument("--expect-md5", action="append", default=[],
                    help="pin an input as FILE=MD5; repeatable")
    args = ap.parse_args()

    feeds = [("dlrloc.csv", "AURA"), ("DLTahoe.csv", "TAHOE"), ("DLAvalon.csv", "AVALON")]
    os.makedirs(args.out, exist_ok=True)

    pins = {}
    for spec in args.expect_md5:
        if "=" not in spec:
            print("FAIL: --expect-md5 needs FILE=MD5, got %r" % spec)
            return 2
        f, m = spec.split("=", 1)
        pins[f.strip()] = m.strip().lower()

    print("resolve-placeids %s" % TOOL_VERSION)
    print("=" * 72)
    print("INPUTS")
    all_rows, header_rows, hard_problems = [], [], []
    for fname, brand in feeds:
        path = os.path.join(args.feeds, fname)
        if not os.path.exists(path):
            print("  FAIL: missing feed %s" % path)
            return 2
        m = md5_of(path)
        if fname in pins and pins[fname] != m:
            print("  FAIL: %s md5 %s, pinned %s" % (fname, m, pins[fname]))
            return 2
        rows, rep, probs, physical = load_feed(path, brand)
        for r in rep:
            r["feed"] = fname
        header_rows.extend(rep)
        for p in probs:
            hard_problems.append("%s: %s" % (fname, p))
        all_rows.extend(rows)
        print("  %-14s md5 %s  bytes %-8d rows %d" % (fname, m, os.path.getsize(path), len(rows)))

    print()
    print("HEADER RESOLUTION")
    for fname, _ in feeds:
        mapped = [r for r in header_rows if r["feed"] == fname and r["field"]]
        unmapped = [r for r in header_rows if r["feed"] == fname and not r["field"]]
        pos = [r for r in mapped if r["how"] == "POSITIONAL-FALLBACK"]
        print("  %-14s %d mapped, %d unmapped, %d positional"
              % (fname, len(mapped), len(unmapped), len(pos)))
        for r in mapped:
            if r["physical_header"].replace("\ufeff", "") != r["field"].title().replace("_", " "):
                pass
        for r in unmapped:
            print("      unmapped column %d: %r" % (r["column_index"], r["physical_header"]))

    drift = defaultdict(set)
    for r in header_rows:
        if r["field"]:
            drift[r["field"]].add(r["physical_header"].replace("\ufeff", ""))
    for field, variants in sorted(drift.items()):
        if len(variants) > 1:
            print("  DRIFT ABSORBED  %-11s <- %s" % (field, " / ".join(sorted(variants))))

    if hard_problems:
        print()
        print("  HEADER PROBLEMS")
        for p in hard_problems:
            print("    " + p)

    print()
    print("COVERAGE (why Identifier is not the key)")
    for fname, _ in feeds:
        rows = [r for r in all_rows if r["feed"] == fname]
        n = len(rows) or 1
        def pct(field):
            c = sum(1 for r in rows if r[field])
            return "%d (%d%%)" % (c, round(100.0 * c / n))
        print("  %-14s rows %-4d identifier %-12s url %-12s phone %-12s"
              % (fname, len(rows), pct("identifier"), pct("url"), pct("phone")))

    dealers, anomalies = build_dealers(all_rows)
    nm_rows, nm_pairs = near_misses(dealers)
    anomalies.extend(nm_rows)
    rank = {"high": 0, "medium": 1, "low": 2, "": 3}
    anomalies.sort(key=lambda a: (rank.get(a.get("confidence", ""), 3),
                                  a.get("flag", ""), a.get("name", "")))

    cache_path = os.path.join(args.out, "placeids.json")
    cache = {}
    if os.path.exists(cache_path):
        with open(cache_path, encoding="utf-8") as fh:
            cache = json.load(fh).get("dealers", {})

    print()
    print("MERGE")
    print("  feed rows total          %d" % len(all_rows))
    print("  unique physical dealers  %d" % len(dealers))
    print("  already in cache         %d" % sum(1 for d in dealers if d["dealer_key"] in cache))
    fc = Counter(f for d in dealers for f in d["flags"].split(",") if f)
    for f, c in sorted(fc.items()):
        print("    flag %-22s %d" % (f, c))
    print("  coordinate states        %s"
          % dict(Counter(d["coord"] for d in dealers)))
    print("  brand membership         %s"
          % dict(Counter(d["brands"] or "(blank)" for d in dealers)))

    queries = build_queries(dealers, cache)
    to_resolve = [q for q in queries if q["status"] == "to_resolve"]

    outs = []
    outs.append(("header-map.csv", *write_csv(
        os.path.join(args.out, "header-map.csv"), header_rows,
        ["feed", "field", "physical_header", "folded", "matched_synonym",
         "column_index", "how"])))
    outs.append(("dealers-unique.csv", *write_csv(
        os.path.join(args.out, "dealers-unique.csv"), dealers,
        ["dealer_key", "name", "name_variants", "address", "unit", "city",
         "state", "zip", "country", "phone", "url", "lat", "lng", "coord",
         "brands", "in_aura_feed", "feeds", "identifiers", "row_count",
         "source_rows", "flags"])))
    outs.append(("anomalies.csv", *write_csv(
        os.path.join(args.out, "anomalies.csv"), anomalies,
        ["dealer_key", "flag", "confidence", "signals", "name", "address",
         "city", "state", "zip", "detail", "source_rows"])))

    resolved_now, failures = 0, []
    if args.live:
        api_key = os.environ.get("GOOGLE_PLACES_API_KEY", "").strip()
        if not api_key:
            print()
            print("FAIL: --live needs GOOGLE_PLACES_API_KEY in the environment.")
            print("      Never pass a key on the command line.")
            return 2
        if args.max_calls <= 0:
            print()
            print("FAIL: --live needs an explicit --max-calls ceiling above zero.")
            return 2

        budget = min(args.max_calls, len(to_resolve))
        print()
        print("LIVE  field mask %s" % FIELD_MASK)
        print("      %d to resolve, ceiling %d, spending %d"
              % (len(to_resolve), args.max_calls, budget))
        review = []
        for q in to_resolve[:budget]:
            bias = None
            if q["bias_lat"] != "" and q["bias_lng"] != "":
                bias = (q["bias_lat"], q["bias_lng"])
            data, err = call_text_search(q["query"], api_key, bias, q["region_code"])
            if err or not data or not data.get("places"):
                failures.append({"dealer_key": q["dealer_key"],
                                 "query": q["query"], "error": err or "no result"})
            else:
                p = data["places"][0]
                cache[q["dealer_key"]] = {"place_id": p.get("id", ""),
                                          "resolved_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ",
                                                                        time.gmtime())}
                review.append({
                    "dealer_key": q["dealer_key"],
                    "query": q["query"],
                    "place_id": p.get("id", ""),
                    "google_name": (p.get("displayName") or {}).get("text", ""),
                    "google_address": p.get("formattedAddress", ""),
                    "google_lat": (p.get("location") or {}).get("latitude", ""),
                    "google_lng": (p.get("location") or {}).get("longitude", ""),
                })
                resolved_now += 1
            time.sleep(args.sleep)

        review.insert(0, {"dealer_key": REVIEW_NOTICE})
        outs.append((REVIEW_FILENAME, *write_csv(
            os.path.join(args.out, REVIEW_FILENAME), review,
            ["dealer_key", "query", "place_id", "google_name", "google_address",
             "google_lat", "google_lng"])))
        print()
        print("  NOTICE: %s" % REVIEW_NOTICE)
        if failures:
            outs.append(("failures.csv", *write_csv(
                os.path.join(args.out, "failures.csv"), failures,
                ["dealer_key", "query", "error"])))
        queries = build_queries(dealers, cache)

    outs.append(("queries.csv", *write_csv(
        os.path.join(args.out, "queries.csv"), queries,
        ["dealer_key", "status", "place_id", "query", "bias_lat", "bias_lng",
         "bias_radius_m", "region_code", "brands", "flags", "source_rows"])))

    adjudication = build_adjudication(dealers, cache, nm_pairs)
    outs.append(("adjudication.csv", *write_csv(
        os.path.join(args.out, "adjudication.csv"), adjudication,
        ["verdict", "place_id_a", "place_id_b", "dealer_key_a", "dealer_key_b",
         "name_a", "name_b", "address_a", "address_b", "city", "state", "zip",
         "corroborating", "was_flagged_near_miss"])))

    # The cache is a tracked artefact and this project pins artefacts by md5, so
    # a no-op run must not change a byte. The write is skipped unless the dealer
    # payload actually moved; only then does written_utc advance.
    prior_dealers = None
    prior_stamp = ""
    if os.path.exists(cache_path):
        with open(cache_path, encoding="utf-8") as fh:
            prior = json.load(fh)
        prior_dealers = prior.get("dealers")
        prior_stamp = prior.get("written_utc", "")

    prior_version = prior.get("version", "") if os.path.exists(cache_path) else ""
    if prior_dealers == cache and prior_stamp and prior_version == TOOL_VERSION:
        cache_note = "unchanged"
    else:
        payload = {
            "tool": "resolve-placeids", "version": TOOL_VERSION,
            "written_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "note": ("place_id only. Google's Places policy exempts place_id from "
                     "the no-caching rule; no other Places field is stored here."),
            "dealers": cache,
        }
        with open(cache_path, "w", newline="\n", encoding="utf-8") as fh:
            json.dump(payload, fh, indent=2, sort_keys=True)
            fh.write("\n")
        cache_note = "written"
    outs.append(("placeids.json (%s)" % cache_note,
                 md5_of(cache_path), os.path.getsize(cache_path)))

    print()
    print("ADJUDICATION")
    av = Counter(r["verdict"] for r in adjudication)
    for v, c in sorted(av.items()):
        print("  %-28s %d" % (v, c))
    if not adjudication:
        print("  (nothing to settle)")

    print()
    print("OUTPUTS")
    for name, m, n in outs:
        print("  %-24s md5 %s  bytes %d" % (name, m, n))

    # ---- structural self-checks -------------------------------------------
    print()
    print("SELF-CHECK")
    ok = True

    def check(label, cond, detail=""):
        nonlocal ok
        print("  %-46s %s %s" % (label, "PASS" if cond else "FAIL", detail))
        if not cond:
            ok = False

    keys = [d["dealer_key"] for d in dealers]
    check("dealer_key unique", len(keys) == len(set(keys)))

    # Invariant 1: blanking Identifier must not move a single key.  This is the
    # negative control for the empty-Identifier workaround -- if the key ever
    # picked up Identifier, Tahoe and Avalon would split away from Aura.
    moved = 0
    for r in all_rows:
        probe = dict(r)
        probe["identifier"] = ""
        probe["customer"] = ""
        probe["shipto"] = ""
        if dealer_key(probe) != r["dealer_key"]:
            moved += 1
    check("key unchanged when Identifier blanked", moved == 0, "%d moved" % moved)

    # Invariant 2: the header fold collapses the known drift, and would also
    # collapse casing and separator variants we have not seen yet.
    variants = ["ShipTo", "Ship To", "SHIP_TO", "ship-to", " Ship  To "]
    folds = {fold_header(v) for v in variants}
    check("ShipTo / 'Ship To' fold to one key", folds == {"shipto"}, str(sorted(folds)))
    check("BOM stripped from first header", fold_header("\ufeffName") == "name")

    check("every feed row assigned to a dealer",
          sum(d["row_count"] for d in dealers) == len(all_rows),
          "%d rows / %d assigned" % (len(all_rows), sum(d["row_count"] for d in dealers)))
    check("shipto resolved in all three feeds",
          len({r["feed"] for r in header_rows
               if r["field"] == "shipto"}) == 3)
    check("no required field resolved positionally",
          not any(r["how"] == "POSITIONAL-FALLBACK" for r in header_rows))
    check("no required field unresolved", not hard_problems)
    check("every query non-empty", all(q["query"] for q in queries))
    check("cache holds place_id only",
          all(set(v) <= {"place_id", "resolved_utc"} for v in cache.values()))
    check("dry run spent nothing", args.live or resolved_now == 0)
    adj_pairs = {frozenset((r["dealer_key_a"], r["dealer_key_b"]))
                 for r in adjudication}
    check("adjudication covers every near-miss pair",
          all(frozenset((x, y)) in adj_pairs for x, y, _ in nm_pairs),
          "%d pairs / %d rows" % (len(nm_pairs), len(adjudication)))
    check("adjudication rows are unique pairs",
          len(adj_pairs) == len(adjudication))
    check("review filename carries the delete instruction",
          "DELETE" in REVIEW_FILENAME and "RETAIN" in REVIEW_NOTICE)
    check("no unresolved verdict once every pair has a place_id",
          args.live or all(r["verdict"] == "unresolved" for r in adjudication)
          or not adjudication)

    print()
    print("  to resolve: %d    cached: %d    resolved this run: %d    failures: %d"
          % (len(to_resolve), len(dealers) - len(to_resolve), resolved_now, len(failures)))
    print()
    print("self-check ok" if ok else "SELF-CHECK FAILED")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
