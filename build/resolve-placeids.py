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

TOOL_VERSION = "1.4.2"

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
    "NUNAVUT": "NU", "ONTARIO": "ON", "ONT": "ON",
    "PRINCE EDWARD ISLAND": "PE",
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
                "country_raw": get("country"),
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
        # The state is part of the key basis.  A value that resolves to
        # neither a US state nor a Canadian province enters the key
        # verbatim, so a later correction to the map re-keys the dealer
        # and orphans its stored place_id.  Report it while that is still
        # cheap.  "ONT" was found this way on 2026-09-14.
        st = rep["state"]
        if not (len(st) == 2 and (st in US_CODES or st in CA_CODES)):
            flags.append("state_unresolved")

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
                    "state_unresolved": " | ".join(sorted({r["state"] for r in rows})),
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
    #s0.222.  d['unit'] was split out of d['address'] by norm_street, and
    #d['address'] here is the RAW line, which still contains it.  Appending
    #unconditionally spelled it twice.
    if d["unit"] and d["unit"].upper() not in norm_text(d["address"]).upper():
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


LEGACY_ENDPOINT = "https://maps.googleapis.com/maps/api/place/textsearch/json"


def _scrub(text, api_key):
    """Never let the key reach failures.csv.

    On the legacy endpoint the key travels in the URL, and urllib puts the
    failing URL in HTTPError.filename and in the str() of several errors.
    Nothing below returns a message or a URL, so this should never fire -
    which is exactly why it is here rather than trusted to be unnecessary.
    """
    s = str(text)
    if api_key and api_key in s:
        s = s.replace(api_key, "<redacted>")
    return s


def _legacy_to_new(result):
    """One legacy result, in the shape the New API returns.

    The caller reads id, displayName.text, formattedAddress and
    location.latitude/longitude. Normalising here means the cache writer,
    the review writer and the failure handler never learn which API ran.
    """
    loc = ((result.get("geometry") or {}).get("location") or {})
    return {
        "id":               result.get("place_id", ""),
        "displayName":      {"text": result.get("name", "")},
        "formattedAddress": result.get("formatted_address", ""),
        "location":         {"latitude":  loc.get("lat", ""),
                             "longitude": loc.get("lng", "")},
    }


#Statuses that describe the key or the project rather than the dealer.
#One of these means every remaining call will fail the same way. s0.220.
LEGACY_FATAL = ("REQUEST_DENIED", "OVER_QUERY_LIMIT")


def call_text_search_legacy(query: str, api_key: str, bias=None, region=""):
    """Legacy Text Search, normalised to the New response shape.

    Returns (data, err) exactly as call_text_search does:
      ({"places": [...]}, "")   a hit
      ({"places": []},    "")   ZERO_RESULTS - a miss, NOT an error
      (None, "FATAL <status>")  key or project level; stop the run
      (None, "<detail>")        this query only
    """
    import urllib.parse
    import urllib.request
    import urllib.error

    params = {"query": query, "key": api_key}
    if bias:
        #Legacy biases with location+radius, not a locationBias circle.
        params["location"] = "%s,%s" % (bias[0], bias[1])
        params["radius"] = str(int(BIAS_RADIUS_M))
    if region in ("US", "CA"):
        #Legacy wants a lowercase ccTLD here, not the CLDR code the New
        #API takes in regionCode.
        params["region"] = region.lower()

    url = LEGACY_ENDPOINT + "?" + urllib.parse.urlencode(params)
    try:
        with urllib.request.urlopen(url, timeout=30) as resp:
            data = json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        #Never echo e.url or e.reason: the key is in the URL.
        return None, "HTTP %s" % e.code
    except Exception as e:                                    # noqa: BLE001
        return None, _scrub(type(e).__name__, api_key)

    #HTTP 200 AND AN ERROR IS THE LEGACY CONTRACT. The status line says
    #nothing; the status FIELD is the outcome.
    status = str(data.get("status", "")).upper()
    if status == "OK":
        results = data.get("results") or []
        if not results:
            return {"places": []}, ""
        top = results[0]
        #s0.223.  Text Search returns a STREET ADDRESS when it cannot find a
        #business at one, and that result is shaped exactly like a hit: a
        #place_id, a formatted_address, and a name which IS the address.
        #Rockingham Marina came back as '1900 W Nickerson St #112' - an id
        #with no business behind it, so Place Details would return no hours
        #and the row would sit in the table looking resolved.
        #
        #A loud miss beats a silent wrong id, so this is an error with a
        #named reason rather than a ZERO_RESULTS-style empty list: it lands
        #in failures.csv where somebody can hand-resolve the dealer.
        #
        #Deliberately NOT scanning results[1:] for the first establishment.
        #That finds A business near the address, which is not the same as
        #finding THE dealer, and it would fail silently in the other
        #direction.
        if "establishment" not in (top.get("types") or []):
            return None, _scrub("NOT_ESTABLISHMENT %s"
                                % (",".join(top.get("types") or []) or "no types"),
                                api_key)
        return {"places": [_legacy_to_new(top)]}, ""
    if status == "ZERO_RESULTS":
        #A miss, deliberately not an error. Google not knowing a dealer is
        #an outcome; calling it a fault would strike a valid address.
        return {"places": []}, ""
    if status in LEGACY_FATAL:
        return None, _scrub("FATAL %s" % status, api_key)
    return None, _scrub("STATUS %s" % (status or "no status"), api_key)


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


# --------------------------------------------------------------------------
# 7.  KEY VECTORS  --  the oracle for the PHP address-key port (rev34 s0.193)
# --------------------------------------------------------------------------
#
# Each entry is (vid, note, address, city, state, zip, country).  These are the
# five raw fields that feed dealer_key and nothing else; name, phone and url do
# not enter the key and are not modelled here.
#
# COVERAGE IS ASSERTED, NOT ASSUMED.  emit_key_vectors() fails if any suffix,
# directional, unit word, highway regex or postal branch is left undriven by
# the real rows plus these vectors combined.

SYNTH_VECTORS = (
    # -- every SUFFIXES entry, including the eleven the feeds never use ------
    ("sfx-street",   "STREET->ST",      "100 MAPLE STREET",       "ALPHA", "MI", "49001", "USA"),
    ("sfx-road",     "ROAD->RD",        "100 MAPLE ROAD",         "ALPHA", "MI", "49001", "USA"),
    ("sfx-avenue",   "AVENUE->AVE",     "100 MAPLE AVENUE",       "ALPHA", "MI", "49001", "USA"),
    ("sfx-av",       "AV->AVE",         "100 MAPLE AV",           "ALPHA", "MI", "49001", "USA"),
    ("sfx-drive",    "DRIVE->DR",       "100 MAPLE DRIVE",        "ALPHA", "MI", "49001", "USA"),
    ("sfx-highway",  "HIGHWAY->HWY",    "100 MAPLE HIGHWAY",      "ALPHA", "MI", "49001", "USA"),
    ("sfx-hiway",    "HIWAY->HWY",      "100 MAPLE HIWAY",        "ALPHA", "MI", "49001", "USA"),
    ("sfx-boulevard","BOULEVARD->BLVD", "100 MAPLE BOULEVARD",    "ALPHA", "MI", "49001", "USA"),
    ("sfx-lane",     "LANE->LN",        "100 MAPLE LANE",         "ALPHA", "MI", "49001", "USA"),
    ("sfx-court",    "COURT->CT",       "100 MAPLE COURT",        "ALPHA", "MI", "49001", "USA"),
    ("sfx-place",    "PLACE->PL",       "100 MAPLE PLACE",        "ALPHA", "MI", "49001", "USA"),
    ("sfx-parkway",  "PARKWAY->PKWY",   "100 MAPLE PARKWAY",      "ALPHA", "MI", "49001", "USA"),
    ("sfx-circle",   "CIRCLE->CIR",     "100 MAPLE CIRCLE",       "ALPHA", "MI", "49001", "USA"),
    ("sfx-terrace",  "TERRACE->TER",    "100 MAPLE TERRACE",      "ALPHA", "MI", "49001", "USA"),
    ("sfx-trail",    "TRAIL->TRL",      "100 MAPLE TRAIL",        "ALPHA", "MI", "49001", "USA"),
    ("sfx-route",    "ROUTE->RTE",      "100 MAPLE ROUTE",        "ALPHA", "MI", "49001", "USA"),
    ("sfx-turnpike", "TURNPIKE->TPKE",  "100 MAPLE TURNPIKE",     "ALPHA", "MI", "49001", "USA"),
    ("sfx-expwy",    "EXPRESSWAY->EXPY","100 MAPLE EXPRESSWAY",   "ALPHA", "MI", "49001", "USA"),
    ("sfx-square",   "SQUARE->SQ",      "100 MAPLE SQUARE",       "ALPHA", "MI", "49001", "USA"),
    ("sfx-point",    "POINT->PT",       "100 MAPLE POINT",        "ALPHA", "MI", "49001", "USA"),
    ("sfx-crossing", "CROSSING->XING",  "100 MAPLE CROSSING",     "ALPHA", "MI", "49001", "USA"),
    ("sfx-extension","EXTENSION->EXT",  "100 MAPLE EXTENSION",    "ALPHA", "MI", "49001", "USA"),

    # -- every DIRECTIONALS entry, including the four the feeds never use ----
    ("dir-n",  "NORTH->N",      "100 NORTH MAPLE ST",     "ALPHA", "MI", "49001", "USA"),
    ("dir-s",  "SOUTH->S",      "100 SOUTH MAPLE ST",     "ALPHA", "MI", "49001", "USA"),
    ("dir-e",  "EAST->E",       "100 EAST MAPLE ST",      "ALPHA", "MI", "49001", "USA"),
    ("dir-w",  "WEST->W",       "100 WEST MAPLE ST",      "ALPHA", "MI", "49001", "USA"),
    ("dir-ne", "NORTHEAST->NE", "100 NORTHEAST MAPLE ST", "ALPHA", "MI", "49001", "USA"),
    ("dir-nw", "NORTHWEST->NW", "100 NORTHWEST MAPLE ST", "ALPHA", "MI", "49001", "USA"),
    ("dir-se", "SOUTHEAST->SE", "100 SOUTHEAST MAPLE ST", "ALPHA", "MI", "49001", "USA"),
    ("dir-sw", "SOUTHWEST->SW", "100 SOUTHWEST MAPLE ST", "ALPHA", "MI", "49001", "USA"),

    # -- every UNIT_WORDS entry.  The unit is held OUT of street_key, so each
    #    of these must produce the SAME street_key as unit-none below.
    ("unit-none",      "no unit",        "100 MAPLE ST",              "ALPHA", "MI", "49001", "USA"),
    ("unit-ste",       "STE + lookahead","100 MAPLE ST STE 4",        "ALPHA", "MI", "49001", "USA"),
    ("unit-suite",     "SUITE",          "100 MAPLE ST SUITE 4",      "ALPHA", "MI", "49001", "USA"),
    ("unit-unit",      "UNIT",           "100 MAPLE ST UNIT 4",       "ALPHA", "MI", "49001", "USA"),
    ("unit-apt",       "APT",            "100 MAPLE ST APT 4",        "ALPHA", "MI", "49001", "USA"),
    ("unit-apartment", "APARTMENT",      "100 MAPLE ST APARTMENT 4",  "ALPHA", "MI", "49001", "USA"),
    ("unit-bldg",      "BLDG",           "100 MAPLE ST BLDG 4",       "ALPHA", "MI", "49001", "USA"),
    ("unit-building",  "BUILDING",       "100 MAPLE ST BUILDING 4",   "ALPHA", "MI", "49001", "USA"),
    ("unit-rm",        "RM",             "100 MAPLE ST RM 4",         "ALPHA", "MI", "49001", "USA"),
    ("unit-room",      "ROOM",           "100 MAPLE ST ROOM 4",       "ALPHA", "MI", "49001", "USA"),
    ("unit-fl",        "FL",             "100 MAPLE ST FL 4",         "ALPHA", "MI", "49001", "USA"),
    ("unit-floor",     "FLOOR",          "100 MAPLE ST FLOOR 4",      "ALPHA", "MI", "49001", "USA"),
    ("unit-hash-sep",  "# as own token", "100 MAPLE ST # 4",          "ALPHA", "MI", "49001", "USA"),
    ("unit-hash-join", "#4 one token",   "100 MAPLE ST #4",           "ALPHA", "MI", "49001", "USA"),
    # Terminal unit word with NOTHING after it: exercises the i+1 bounds guard.
    ("unit-terminal",  "trailing STE",   "100 MAPLE ST STE",          "ALPHA", "MI", "49001", "USA"),
    # Two units in one line: both consumed, street_key still bare.
    ("unit-double",    "STE then #",     "100 MAPLE ST STE 4 # 9",    "ALPHA", "MI", "49001", "USA"),
    # A unit word LEADING the line, before the house number.
    ("unit-leading",   "STE first",      "STE 4 100 MAPLE ST",        "ALPHA", "MI", "49001", "USA"),

    # -- the three highway regexes, each alternation arm -------------------
    ("hwy-us-spaced",  "U S HIGHWAY",    "100 U S HIGHWAY 31",     "ALPHA", "MI", "49001", "USA"),
    ("hwy-us-hwy",     "US HWY",         "100 US HWY 31",          "ALPHA", "MI", "49001", "USA"),
    ("hwy-us-dash",    "US-",            "100 US-31",              "ALPHA", "MI", "49001", "USA"),
    ("hwy-st-highway", "STATE HIGHWAY",  "100 STATE HIGHWAY 55",   "ALPHA", "MI", "49001", "USA"),
    ("hwy-st-hwy",     "STATE HWY",      "100 STATE HWY 55",       "ALPHA", "MI", "49001", "USA"),
    ("hwy-st-route",   "STATE ROUTE",    "100 STATE ROUTE 55",     "ALPHA", "MI", "49001", "USA"),
    ("hwy-st-rte",     "STATE RTE",      "100 STATE RTE 55",       "ALPHA", "MI", "49001", "USA"),
    ("hwy-st-rd",      "STATE RD",       "100 STATE RD 55",        "ALPHA", "MI", "49001", "USA"),
    ("hwy-co-road",    "COUNTY ROAD",    "100 COUNTY ROAD 7",      "ALPHA", "MI", "49001", "USA"),
    ("hwy-co-rd",      "COUNTY RD",      "100 COUNTY RD 7",        "ALPHA", "MI", "49001", "USA"),
    ("hwy-co-route",   "COUNTY ROUTE",   "100 COUNTY ROUTE 7",     "ALPHA", "MI", "49001", "USA"),
    ("hwy-co-rte",     "COUNTY RTE",     "100 COUNTY RTE 7",       "ALPHA", "MI", "49001", "USA"),

    # -- punctuation and the ampersand expansion ---------------------------
    ("punct-amp",    "& -> AND",        "100 MAPLE & OAK RD",     "ALPHA", "MI", "49001", "USA"),
    ("punct-dots",   "periods stripped","100 N. MAPLE ST.",       "ALPHA", "MI", "49001", "USA"),
    ("punct-commas", "commas stripped", "100 MAPLE ST, REAR",     "ALPHA", "MI", "49001", "USA"),
    ("punct-slash",  "non-alnum class", "100 MAPLE ST / REAR",    "ALPHA", "MI", "49001", "USA"),
    ("punct-squash", "runs of space",   "100    MAPLE     ST",    "ALPHA", "MI", "49001", "USA"),
    ("punct-tab",    "tab is space",    "100\tMAPLE\tST",         "ALPHA", "MI", "49001", "USA"),

    # -- NFKD.  ZERO non-ASCII bytes exist in any feed, so nothing below is
    #    reachable from production data.  It is reachable from a future feed,
    #    and a PHP port without ext-intl will silently disagree here.
    ("nfkd-acute",   "E ACUTE precomposed", "100 CAF\u00c9 RD",       "MONTR\u00c9AL", "QC", "H3Z 2Y7", "CANADA"),
    ("nfkd-combine", "E + U+0301",          "100 CAFE\u0301 RD",      "MONTREAL",   "QC", "H3Z 2Y7", "CANADA"),
    ("nfkd-umlaut",  "U UMLAUT",            "100 M\u00dcLLER RD",     "ALPHA",      "MI", "49001",   "USA"),
    ("nfkd-tilde",   "N TILDE",             "100 PE\u00d1A RD",       "ALPHA",      "MI", "49001",   "USA"),
    ("nfkd-cedilla", "C CEDILLA",           "100 FRAN\u00c7OIS RD",   "ALPHA",      "MI", "49001",   "USA"),
    ("nfkd-ligature","FI LIGATURE -> FI",   "100 \ufb01SHER RD",      "ALPHA",      "MI", "49001",   "USA"),
    ("nfkd-fullwid", "FULLWIDTH DIGITS",    "\uff11\uff10\uff10 MAPLE RD",  "ALPHA", "MI", "49001", "USA"),
    ("nfkd-nbsp",    "NBSP is not \\s in PHP","100\u00a0MAPLE RD",      "ALPHA",      "MI", "49001",   "USA"),
    ("nfkd-quebec",  "QUEBEC accented",     "100 MAPLE RD",         "QUEBEC CITY","QU\u00c9BEC", "G1R 5P3", "CANADA"),

    # -- postal forms -------------------------------------------------------
    ("zip-us5",      "plain ZIP5",      "100 MAPLE RD", "ALPHA", "MI", "49001",      "USA"),
    ("zip-us9",      "ZIP+4 hyphen",    "100 MAPLE RD", "ALPHA", "MI", "49001-1234", "USA"),
    ("zip-us9nodash","ZIP+4 no hyphen", "100 MAPLE RD", "ALPHA", "MI", "490011234",  "USA"),
    ("zip-uspad",    "leading space",   "100 MAPLE RD", "ALPHA", "MI", " 49001 ",    "USA"),
    ("zip-ca-space", "CA with space",   "100 MAPLE RD", "BETA",  "ON", "P0M 3E0",    "CANADA"),
    ("zip-ca-tight", "CA no space",     "100 MAPLE RD", "BETA",  "ON", "P0M3E0",     "CANADA"),
    ("zip-ca-dash",  "CA hyphenated",   "100 MAPLE RD", "BETA",  "ON", "P0M-3E0",    "CANADA"),
    ("zip-ca-lower", "CA lowercase",    "100 MAPLE RD", "BETA",  "ON", "p0m 3e0",    "CANADA"),
    ("zip-junk",     "unparseable",     "100 MAPLE RD", "ALPHA", "MI", "N/A",        "USA"),
    ("zip-empty",    "empty postal",    "100 MAPLE RD", "ALPHA", "MI", "",           "USA"),

    # -- state forms.  ONT is the live one: it is in neither map, so it falls
    #    through norm_state unchanged and enters the key AS 'ONT'.  Two real
    #    rows do this today (dlrloc.csv:89, DLAvalon.csv:226).
    ("st-code-us",   "2-letter US",     "100 MAPLE RD", "ALPHA", "MI",            "49001",   "USA"),
    ("st-code-ca",   "2-letter CA",     "100 MAPLE RD", "BETA",  "ON",            "P0M 3E0", "CANADA"),
    ("st-spelled-us","spelled US",      "100 MAPLE RD", "ALPHA", "MICHIGAN",      "49001",   "USA"),
    ("st-spelled-ca","spelled CA",      "100 MAPLE RD", "BETA",  "ONTARIO",       "P0M 3E0", "CANADA"),
    ("st-ont",       "ONT resolves to ON","100 MAPLE RD","BETA", "ONT",           "P0M 3E0", "CANADA"),
    ("st-bogus",     "no such code",    "100 MAPLE RD", "BETA",  "ZZZ",           "P0M 3E0", "CANADA"),
    ("st-lower",     "lowercase code",  "100 MAPLE RD", "ALPHA", "mi",            "49001",   "USA"),
    ("st-dotted",    "M.I. dotted",     "100 MAPLE RD", "ALPHA", "M.I.",          "49001",   "USA"),
    ("st-spaced",    "padded",          "100 MAPLE RD", "ALPHA", "  MI  ",        "49001",   "USA"),
    ("st-empty",     "empty state",     "100 MAPLE RD", "ALPHA", "",              "49001",   "USA"),
    ("st-dc",        "DISTRICT OF COLUMBIA", "100 MAPLE RD", "ALPHA", "DISTRICT OF COLUMBIA", "20001", "USA"),

    # -- country inference --------------------------------------------------
    ("ctry-usa",     "USA",                "100 MAPLE RD", "ALPHA", "MI", "49001",   "USA"),
    ("ctry-us",      "US",                 "100 MAPLE RD", "ALPHA", "MI", "49001",   "US"),
    ("ctry-long",    "UNITED STATES",      "100 MAPLE RD", "ALPHA", "MI", "49001",   "UNITED STATES"),
    ("ctry-canada",  "CANADA",             "100 MAPLE RD", "BETA",  "ON", "P0M 3E0", "CANADA"),
    ("ctry-can",     "CAN",                "100 MAPLE RD", "BETA",  "ON", "P0M 3E0", "CAN"),
    ("ctry-frompost","blank, CA postal",   "100 MAPLE RD", "BETA",  "ON", "P0M 3E0", ""),
    ("ctry-fromstate","blank, CA-only code","100 MAPLE RD","BETA",  "AB", "",        ""),
    ("ctry-usstate", "blank, US code",     "100 MAPLE RD", "ALPHA", "MI", "",        ""),
    ("ctry-unknown", "blank everything",   "100 MAPLE RD", "ALPHA", "",   "",        ""),

    # -- city normalisation -------------------------------------------------
    ("city-dots",    "ST. CLAIR",       "100 MAPLE RD", "ST. CLAIR",   "MI", "49001", "USA"),
    ("city-lower",   "lowercase",       "100 MAPLE RD", "st clair",    "MI", "49001", "USA"),
    ("city-spaced",  "runs of space",   "100 MAPLE RD", "ST   CLAIR",  "MI", "49001", "USA"),
    ("city-hyphen",  "hyphen retained", "100 MAPLE RD", "WINSTON-SALEM","NC","27101", "USA"),

    # -- degenerate ---------------------------------------------------------
    ("deg-empty",    "all blank",       "",             "",      "",   "",        ""),
    ("deg-addr-only","address only",    "100 MAPLE RD", "",      "",   "",        ""),
    ("deg-numeric",  "digits only",     "100",          "ALPHA", "MI", "49001",   "USA"),
    ("deg-punct",    "punctuation only","...",          "ALPHA", "MI", "49001",   "USA"),
)

KEYVECTOR_FIELDS = [
    "vid", "class", "note", "feed", "row",
    "raw_address", "raw_city", "raw_state", "raw_zip", "raw_country",
    "street_key", "unit", "city_key", "state", "zip", "postal_country",
    "country", "basis", "dealer_key",
]


def key_vector(vid, klass, note, feed, row, address, city, state, zipc, country):
    """Run the five raw fields through the exact path load_feed() uses."""
    street, unit = norm_street(address)
    postal, postal_country = norm_postal(zipc)
    st = norm_state(state)
    ctry = norm_country(country, st, postal_country)
    city_key = norm_text(city).replace(".", "")
    basis = "|".join([ctry, st, city_key, postal, street])
    return {
        "vid": vid, "class": klass, "note": note, "feed": feed, "row": row,
        "raw_address": address, "raw_city": city, "raw_state": state,
        "raw_zip": zipc, "raw_country": country,
        "street_key": street, "unit": unit, "city_key": city_key,
        "state": st, "zip": postal, "postal_country": postal_country,
        "country": ctry, "basis": basis,
        "dealer_key": hashlib.sha1(basis.encode("utf-8")).hexdigest()[:12],
    }


def emit_key_vectors(all_rows, out_dir):
    """Write keyvectors.csv and assert branch coverage.  Returns (rows, ok)."""
    vectors = []
    for r in all_rows:
        vectors.append(key_vector(
            "real-%s-%d" % (r["feed_brand"].lower(), r["row"]), "real", "",
            r["feed"], r["row"],
            r["address"], r["city"], r["state"], r["zip_raw"], r["country_raw"]))
    for vid, note, a, c, s, z, k in SYNTH_VECTORS:
        vectors.append(key_vector(vid, "synth", note, "", 0, a, c, s, z, k))

    path = os.path.join(out_dir, "keyvectors.csv")
    m, n = write_csv(path, vectors, KEYVECTOR_FIELDS)

    # ---- coverage.  Absence of a driven branch is a FAILURE, not a note. ---
    seen_sfx, seen_dir, seen_unit = set(), set(), set()
    seen_re, seen_postal, seen_state = set(), set(), set()
    for v in vectors:
        pre = norm_text(v["raw_address"]).replace("&", " AND ")
        pre = re.sub(r"[.,]", " ", pre)
        if re.search(r"\bU\s*S\s*HIGHWAY\b|\bUS\s*HWY\b|\bUS-\b", pre):
            seen_re.add("us")
        if re.search(r"\bSTATE\s+(HIGHWAY|HWY|ROUTE|RTE|RD)\b", pre):
            seen_re.add("state")
        if re.search(r"\bCOUNTY\s+(ROAD|RD|ROUTE|RTE)\b", pre):
            seen_re.add("county")
        for t in squash(re.sub(r"[^A-Z0-9# ]+", " ", pre)).split():
            if t in SUFFIXES:
                seen_sfx.add(t)
            if t in DIRECTIONALS:
                seen_dir.add(t)
            if t in UNIT_WORDS:
                seen_unit.add(t)
            if t.startswith("#"):
                seen_unit.add("#")
        if v["postal_country"] == "CA":
            seen_postal.add("ca")
        elif v["postal_country"] == "US":
            seen_postal.add("us")
        else:
            seen_postal.add("none")
        if v["raw_state"] and not v["state"]:
            seen_state.add("blanked")
        if len(v["state"]) == 2 and v["state"] in US_CODES:
            seen_state.add("us")
        elif len(v["state"]) == 2 and v["state"] in CA_CODES:
            seen_state.add("ca")
        elif v["state"]:
            seen_state.add("fallthrough")
        if any(ord(ch) > 127 for ch in (v["raw_address"] + v["raw_city"] + v["raw_state"])):
            seen_state.add("nonascii")

    missing = []
    if set(SUFFIXES) - seen_sfx:
        missing.append("SUFFIXES %s" % sorted(set(SUFFIXES) - seen_sfx))
    if set(DIRECTIONALS) - seen_dir:
        missing.append("DIRECTIONALS %s" % sorted(set(DIRECTIONALS) - seen_dir))
    if set(UNIT_WORDS) - seen_unit:
        missing.append("UNIT_WORDS %s" % sorted(set(UNIT_WORDS) - seen_unit))
    if {"us", "state", "county"} - seen_re:
        missing.append("highway regexes %s" % sorted({"us", "state", "county"} - seen_re))
    if {"us", "ca", "none"} - seen_postal:
        missing.append("postal branches %s" % sorted({"us", "ca", "none"} - seen_postal))
    if {"us", "ca", "fallthrough", "nonascii"} - seen_state:
        missing.append("state branches %s"
                       % sorted({"us", "ca", "fallthrough", "nonascii"} - seen_state))
    return vectors, path, m, n, missing


def main() -> int:
    ap = argparse.ArgumentParser(description="Resolve one Google Place ID per unique dealer.")
    ap.add_argument("--feeds", required=True, help="directory holding the three CSVs")
    ap.add_argument("--out", required=True, help="output directory")
    ap.add_argument("--live", action="store_true", help="actually spend Text Search calls")
    ap.add_argument("--max-calls", type=int, default=0, help="hard ceiling on live calls")
    ap.add_argument("--sleep", type=float, default=0.12, help="seconds between live calls")
    ap.add_argument("--emit-keys", action="store_true",
                    help="write keyvectors.csv, the oracle for the PHP "
                         "address-key port (rev34 s0.193)")
    ap.add_argument("--expect-md5", action="append", default=[],
                    help="pin an input as FILE=MD5; repeatable")
    #1.4.0.  Defaults to legacy because that is the endpoint that answers
    #today: Places API (New) returns 403 SERVICE_DISABLED on project
    #1038304488249.  Switching back is --api new, with no edit to this file.
    ap.add_argument("--api", choices=("legacy", "new"), default="legacy",
                    help="which Text Search to call. legacy (default) is "
                         "maps.googleapis.com; new is places.googleapis.com "
                         "and needs Places API (New) enabled.")
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
        if args.api == "legacy":
            search = call_text_search_legacy
            print("LIVE  legacy  %s" % LEGACY_ENDPOINT)
            print("      Places API (New) is disabled on this project; place_id "
                  "is one namespace, so these IDs carry forward.")
        else:
            search = call_text_search
            print("LIVE  new     %s" % ENDPOINT)
            print("      field mask %s" % FIELD_MASK)
        print("      %d to resolve, ceiling %d, spending %d"
              % (len(to_resolve), args.max_calls, budget))
        review = []
        fatal = ""
        for q in to_resolve[:budget]:
            bias = None
            if q["bias_lat"] != "" and q["bias_lng"] != "":
                bias = (q["bias_lat"], q["bias_lng"])
            data, err = search(q["query"], api_key, bias, q["region_code"])
            #s0.220. A refusal aimed at the key or the project is not a
            #property of this dealer, and every remaining call will fail
            #the same way. Two runs spent 328 and 303 requests learning
            #that one at a time.
            if err and err.startswith("FATAL"):
                fatal = err
                failures.append({"dealer_key": q["dealer_key"],
                                 "query": q["query"], "error": err})
                break
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

        if fatal:
            print()
            print("  ABORTED after %d call(s): %s" % (resolved_now + len(failures), fatal))
            print("  That status describes the key or the project, not the dealer.")
            print("  Nothing further was attempted.")

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

    if args.emit_keys:
        kv, kv_path, kv_md5, kv_bytes, kv_missing = emit_key_vectors(
            all_rows, args.out)
        print("  %-46s %s %s" % ("keyvectors.csv written",
                                 "PASS",
                                 "%d vectors  md5 %s  bytes %d"
                                 % (len(kv), kv_md5, kv_bytes)))
        check("key vectors drive every normaliser branch",
              not kv_missing, "; ".join(kv_missing))
        real = [v for v in kv if v["class"] == "real"]
        check("emitted real vectors match the feed row count",
              len(real) == len(all_rows),
              "%d vectors / %d rows" % (len(real), len(all_rows)))
        by_row = {(r["feed"], r["row"]): r["dealer_key"] for r in all_rows}
        drift = sum(1 for v in real
                    if by_row.get((v["feed"], v["row"])) != v["dealer_key"])
        check("replay from raw inputs reproduces the loader key",
              drift == 0, "%d of %d diverged" % (drift, len(real)))

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
    #s0.221.  The old form was
    #    args.live or all(verdict == unresolved) or not adjudication
    #which conflates 'this run was not live' with 'nothing is resolved'.
    #Once the cache holds place ids, a dry run legitimately produces real
    #verdicts, is not live, and FAILED - every dry run, forever.  s0.207
    #inverted twice in one day: silent when everything failed, screaming
    #when nothing did.
    #
    #This is the invariant the label always claimed: a pair whose BOTH
    #sides resolved must not still be sitting at 'unresolved'.  Vacuously
    #true on an empty cache, exact on a full one, and independent of --live.
    _both = [r for r in adjudication if r.get("place_id_a") and r.get("place_id_b")]
    check("no unresolved verdict once both places are resolved",
          all(r["verdict"] != "unresolved" for r in _both),
          "%d of %d pair(s) fully resolved" % (len(_both), len(adjudication)))
    #s0.207 INVERTED. Every check above is structural, so all of them hold
    #perfectly while a live run resolves nothing and fails everything -
    #which is what happened twice on 2026-09-14, both times exiting 0. The
    #run summary already said so; the exit code did not.
    #s0.224.  The r1 form of this guard was
    #    (not live) or resolved_now > 0 or not failures
    #which fires on a run whose only remaining dealer is genuinely not
    #listed by Google - 302 cached, one NOT_ESTABLISHMENT, exit 1 forever.
    #That is s0.221 again in the check written to fix s0.207.  Three times
    #in one day a check has been wrong about WHICH condition it cared about.
    #
    #The guard exists to catch a broken API, not an unfindable dealer, so
    #it sorts failures by KIND.  Systemic: a project or key refusal, an
    #HTTP status, or a bare exception class name.  Data: no result,
    #NOT_ESTABLISHMENT, a per-query status - all of which say Google
    #answered and the answer was no.
    _fatal = [f for f in failures if f["error"].startswith("FATAL")]
    _systemic = [f for f in failures
                 if f["error"].startswith("FATAL")
                 or f["error"].startswith("HTTP")
                 or " " not in f["error"]]
    check("no fatal abort",
          (not args.live) or not _fatal,
          ("aborted on %s" % _fatal[0]["error"]) if _fatal else "")
    check("live failures are dealer data, not a broken API",
          (not args.live) or resolved_now > 0 or not _systemic,
          "resolved %d, %d data failure(s), %d systemic"
          % (resolved_now, len(failures) - len(_systemic), len(_systemic)))

    print()
    print("  to resolve: %d    cached: %d    resolved this run: %d    failures: %d"
          % (len(to_resolve), len(dealers) - len(to_resolve), resolved_now, len(failures)))
    print()
    print("self-check ok" if ok else "SELF-CHECK FAILED")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
