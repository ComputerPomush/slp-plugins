#!/usr/bin/env python3
"""
report-placeid-collisions.py  r2  2026-09-14
SLP Dealer Guard - the duplicate-dealer evidence that was already paid for,
and the mis-resolve risk hiding inside it.

WHY THIS EXISTS

placeids.json holds one Google place_id per unique dealer_key. Where two
dealer_keys carry the SAME place_id, Google answered two different derived
queries with one business. That is evidence produced by a third party, from
a different direction than any local heuristic, and it cost nothing extra -
the calls were already spent.

r2 ADDS THE CLASSIFICATION, AND IT IS THE POINT OF THE FILE.

A shared place_id has TWO possible meanings and they want opposite actions:

  1. The two rows really are one business. Merge them. The dealer count
     drops. This is the Option 4 duplicate audit.

  2. The two rows are two real, separate businesses, and ONE of them was
     resolved to the other one's place_id. Nothing is duplicated; a place
     ID is simply WRONG. When Part 4 fetches hours, that dealer will show
     another marina's opening times, confidently, with no error anywhere.

Meaning 2 is the dangerous one because it looks exactly like meaning 1 in
a count. The telephone number separates them: the three merges already
accepted (Grove OK, Aransas Pass TX, Columbus GA) were confirmed on a
shared phone. A pair holding TWO DIFFERENT phone numbers is not a
duplicate - it is a bad resolve.

NOTHING HERE CALLS GOOGLE. load_feed(), build_dealers() and norm_phone()
are imported from resolve-placeids.py so dealer identity and phone
normalisation are derived by the same code that resolved the IDs. A
hand-written re-implementation would assert agreement with a belief about
the normaliser - s0.235.

    python3 report-placeid-collisions.py \
        --placeids <placeids.json> \
        --feeds <dir holding the three CSVs> \
        --anomalies <anomalies.csv>  (optional) \
        --out <report.md>

The input file is pinned. A different placeids.json is a different report
and must not be written under this name by accident.
"""

import argparse
import collections
import csv
import hashlib
import importlib.util
import json
import os
import sys

PLACEIDS_MD5 = "b7b0b5b4f030d4f546d1263331f5e5b3"
PLACEIDS_LEN = 37393

FEEDS = (("dlrloc.csv", "AURA"), ("DLAvalon.csv", "AVALON"), ("DLTahoe.csv", "TAHOE"))

MERGE = "merge candidate"
MISRESOLVE = "MIS-RESOLVE RISK"
UNKNOWN = "undetermined"


def load_resolver(path):
    """Import resolve-placeids.py by path. The hyphen blocks a normal import."""
    if not os.path.isfile(path):
        sys.exit("cannot find the resolver at %s" % path)
    spec = importlib.util.spec_from_file_location("rp", path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    for fn in ("load_feed", "build_dealers", "norm_phone"):
        if not hasattr(mod, fn):
            sys.exit("the resolver at %s has no %s()" % (path, fn))
    return mod


def md5_of(path):
    with open(path, "rb") as fh:
        data = fh.read()
    return hashlib.md5(data).hexdigest(), len(data)


def classify(rp, recs):
    """Two different phones is not a duplicate. It is a wrong place ID."""
    phones = set()
    blank = 0
    for r in recs:
        p = rp.norm_phone(r["phone"])
        if p:
            phones.add(p)
        else:
            blank += 1
    if len(phones) > 1:
        return MISRESOLVE
    if len(phones) == 1 and blank == 0:
        return MERGE
    return UNKNOWN


def main():
    ap = argparse.ArgumentParser(
        description="Report dealer_keys that share one Google place_id.")
    ap.add_argument("--placeids", required=True)
    ap.add_argument("--feeds", required=True, help="directory holding the three CSVs")
    ap.add_argument("--resolver", default="", help="path to resolve-placeids.py")
    ap.add_argument("--anomalies", default="",
                    help="anomalies.csv, to mark what was already flagged")
    ap.add_argument("--out", required=True)
    ap.add_argument("--no-pin", action="store_true",
                    help="skip the placeids.json md5 assertion")
    a = ap.parse_args()

    resolver = a.resolver or os.path.join(os.path.dirname(os.path.abspath(__file__)),
                                          "resolve-placeids.py")
    rp = load_resolver(resolver)

    pid_md5, pid_len = md5_of(a.placeids)
    print("report-placeid-collisions.py  r2   (r2 classifies every group)")
    print("  placeids  %s" % a.placeids)
    print("    md5     %s" % pid_md5)
    print("    bytes   %d" % pid_len)
    if not a.no_pin:
        if pid_md5 != PLACEIDS_MD5 or pid_len != PLACEIDS_LEN:
            sys.exit("    PIN FAIL - expected %s / %d. Pass --no-pin only on purpose."
                     % (PLACEIDS_MD5, PLACEIDS_LEN))
        print("    pinned  OK")

    doc = json.load(open(a.placeids, encoding="utf-8"))
    dealers_json = doc["dealers"]

    rows = []
    for fname, label in FEEDS:
        p = os.path.join(a.feeds, fname)
        if not os.path.isfile(p):
            sys.exit("missing feed %s" % p)
        rows.extend(rp.load_feed(p, label)[0])
    dealers = rp.build_dealers(rows)[0]
    byk = {d["dealer_key"]: d for d in dealers}
    print("  feeds     %d rows  ->  %d unique dealer_keys" % (len(rows), len(byk)))

    flagged = set()
    if a.anomalies and os.path.isfile(a.anomalies):
        for r in csv.DictReader(open(a.anomalies, encoding="utf-8-sig")):
            if r.get("flag") == "possible_duplicate":
                flagged.add(r["dealer_key"])
        print("  anomalies %d keys already carry possible_duplicate" % len(flagged))

    groups = collections.defaultdict(list)
    for key, rec_ in dealers_json.items():
        groups[rec_["place_id"]].append(key)
    colliding = {p: sorted(k) for p, k in groups.items() if len(k) > 1}

    print("  keys %d  ->  distinct place_ids %d  ->  collision groups %d"
          % (len(dealers_json), len(groups), len(colliding)))

    missing = [k for ks in colliding.values() for k in ks if k not in byk]
    if missing:
        sys.exit("dealer_key(s) in placeids.json absent from the feeds: %s" % missing)

    def rec(key):
        d = byk[key]
        return {
            "key": key,
            "name": d["name"],
            "street": " ".join(x for x in (d["address"], d["unit"]) if x).strip(),
            "city": d["city"],
            "state": d["state"],
            "zip": d["zip"],
            "phone": d["phone"],
            "brands": d["brands"],
            "rows": d["source_rows"],
        }

    built = []
    for p, ks in colliding.items():
        recs = [rec(k) for k in ks]
        built.append({
            "place_id": p,
            "recs": recs,
            "verdict": classify(rp, recs),
            "flagged": any(k in flagged for k in ks),
        })
    built.sort(key=lambda g: (g["verdict"] != MISRESOLVE,
                              g["flagged"],
                              g["recs"][0]["name"].upper()))

    n_mis = sum(1 for g in built if g["verdict"] == MISRESOLVE)
    n_mer = sum(1 for g in built if g["verdict"] == MERGE)
    n_unk = sum(1 for g in built if g["verdict"] == UNKNOWN)
    n_new = sum(1 for g in built if not g["flagged"])

    def table(recs):
        out = ["| dealer_key | name | address | phone | brands | feed rows |",
               "|---|---|---|---|---|---|"]
        for r in recs:
            out.append("| `%s` | %s | %s, %s %s %s | %s | %s | %s |"
                       % (r["key"], r["name"], r["street"], r["city"], r["state"],
                          r["zip"], r["phone"] or "-", r["brands"] or "-", r["rows"]))
        return out

    o = []
    o.append("# Place ID collisions - what Google says is one business")
    o.append("")
    o.append("Derived by `build/report-placeid-collisions.py` r2 from a "
             "`placeids.json` written %s." % doc.get("written_utc", "unknown"))
    o.append("")
    o.append("Source pin `%s` / %d bytes / %d dealer keys."
             % (pid_md5, pid_len, len(dealers_json)))
    o.append("")
    o.append("**%d keys resolve to %d distinct place IDs. %d collision groups, all pairs.**"
             % (len(dealers_json), len(groups), len(colliding)))
    o.append("")
    o.append("`avalon_places_import()` already counts these groups at import time. "
             "This file names them, so the finding survives the CLI run.")
    o.append("")
    o.append("## A shared place ID means one of two opposite things")
    o.append("")
    o.append("Both look identical in a count. They want opposite actions.")
    o.append("")
    o.append("1. **The two rows are one business.** Merge them; the dealer count drops.")
    o.append("2. **The two rows are two businesses and one place ID is wrong.** "
             "Nothing is duplicated. A dealer is pointing at somebody else's marina.")
    o.append("")
    o.append("The telephone number separates them. The three merges already accepted "
             "- Grove OK, Aransas Pass TX, Columbus GA - were confirmed on a shared "
             "phone. **A pair holding two different phone numbers is not a duplicate. "
             "It is a bad resolve.**")
    o.append("")
    o.append("| verdict | groups | what it means |")
    o.append("|---|---|---|")
    o.append("| %s | %d | two different phone numbers - one place ID is wrong |"
             % (MISRESOLVE, n_mis))
    o.append("| %s | %d | one shared phone number across the pair |" % (MERGE, n_mer))
    o.append("| %s | %d | at least one row has no phone - needs a human |"
             % (UNKNOWN, n_unk))
    o.append("")
    o.append("%d of the %d groups carry no `possible_duplicate` flag in "
             "`anomalies.csv`. The near-miss detector compares within city and "
             "postcode, and those pairs straddle both." % (n_new, len(colliding)))
    o.append("")

    o.append("## %s (%d) - defects, not duplicates" % (MISRESOLVE, n_mis))
    o.append("")
    if n_mis:
        o.append("Two live phone numbers means two live businesses. One of the two "
                 "place IDs in each group below resolves to the other row's marina. "
                 "Part 4 will fetch that marina's opening hours and publish them on "
                 "the wrong dealer page, confidently, with no error raised anywhere.")
        o.append("")
        o.append("Once the wrong side has been identified by hand, the fix is a reset "
                 "followed by a fresh resolve:")
        o.append("")
        o.append("    wp --skip-plugins=revslider avalon places reset --key=<address_key>")
        o.append("")
    for g in [x for x in built if x["verdict"] == MISRESOLVE]:
        o.append("### `%s`" % g["place_id"])
        o.append("")
        o.extend(table(g["recs"]))
        o.append("")

    for want in (UNKNOWN, MERGE):
        sel = [x for x in built if x["verdict"] == want]
        o.append("## %s (%d)" % (want, len(sel)))
        o.append("")
        if want == UNKNOWN:
            o.append("One side carries no phone number, so the cheap test cannot run. "
                     "Check each by hand before merging or resetting.")
        else:
            o.append("One phone number across both rows. These are the genuine merge "
                     "candidates, the same shape as the three already accepted.")
        o.append("")
        for g in sel:
            o.append("### `%s`%s"
                     % (g["place_id"],
                        "" if g["flagged"] else "  - **not previously flagged**"))
            o.append("")
            o.extend(table(g["recs"]))
            o.append("")

    o.append("## What to do with this")
    o.append("")
    o.append("Nothing automatic. A merge changes the dealer count, which is a "
             "product decision, not a data one.")
    o.append("")
    o.append("Order of work:")
    o.append("")
    o.append("1. The %d %s group(s) first. They are wrong data, not surplus data, "
             "and they become public the moment hours ship." % (n_mis, MISRESOLVE))
    o.append("2. The %d %s group(s) into the Option 4 audit, beside the 10 dealers "
             "at 0,0 and the US-only `get_states()`." % (n_unk, UNKNOWN))
    o.append("3. The %d %s group(s) last. They are real, but they cost nothing "
             "until somebody wants an accurate dealer count." % (n_mer, MERGE))
    o.append("")

    with open(a.out, "w", encoding="utf-8", newline="\n") as fh:
        fh.write("\n".join(o))
    m, n = md5_of(a.out)
    print("  wrote     %s" % a.out)
    print("    md5     %s" % m)
    print("    bytes   %d" % n)
    print("  verdicts  %s %d   %s %d   %s %d"
          % (MISRESOLVE, n_mis, MERGE, n_mer, UNKNOWN, n_unk))
    print("  %d group(s) carry no existing possible_duplicate flag" % n_new)
    return 0


if __name__ == "__main__":
    sys.exit(main())
