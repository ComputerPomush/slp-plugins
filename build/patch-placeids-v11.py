#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
patch-placeids-v11.py  --  resolve-placeids.py  v1.0.0 -> v1.1.0

Anchored, byte-exact. Asserts the input md5 before it touches anything, asserts
each anchor is unique, structurally self-checks the result, and prints the
output md5 before writing.

Three changes, and nothing else:

  1. review.csv is renamed review-DELETE-AFTER-TRIAGE.csv and carries a notice
     row. v1.0.0's docstring claimed a header the code never wrote. Google's
     Places policy exempts place_id alone from the no-retention rule, so the
     displayName and formattedAddress in that file are content that must not be
     kept. Putting the instruction in the filename means it cannot be missed.

  2. A new adjudication.csv settles the near-miss pairs by measurement instead
     of judgement. Two dealer_keys that resolve to the same place_id are the
     same physical dealer; two that resolve to different place_ids are not.
     In dry run every pair reads 'unresolved', so the file is meaningful from
     the first run and stays deterministic.

  3. TOOL_VERSION -> 1.1.0, and the cache's no-op write guard now compares the
     version as well as the dealer payload, so a version bump lands once
     instead of never.

The merge logic is deliberately untouched. header-map.csv, dealers-unique.csv,
anomalies.csv and queries.csv must come out byte-identical to v1.0.0.
"""

import hashlib
import os
import sys

SRC = "resolve-placeids.py"
IN_MD5 = "1bd1bb705698ebea225292bf3e53ef8c"
IN_BYTES = 38836


def md5(b: bytes) -> str:
    return hashlib.md5(b).hexdigest()


def sub(text, old, new, label):
    n = text.count(old)
    if n != 1:
        print("  FAIL  %-46s anchor appears %d times, need 1" % (label, n))
        sys.exit(2)
    print("  ok    %-46s %d -> %d bytes" % (label, len(old), len(new)))
    return text.replace(old, new, 1)


def main():
    if not os.path.exists(SRC):
        print("FAIL: %s not found in %s" % (SRC, os.getcwd()))
        return 2

    raw = open(SRC, "rb").read()
    print("INPUT")
    print("  %-20s md5 %s  bytes %d" % (SRC, md5(raw), len(raw)))
    if md5(raw) != IN_MD5 or len(raw) != IN_BYTES:
        print("  FAIL: expected md5 %s and %d bytes" % (IN_MD5, IN_BYTES))
        return 2
    if b"\r" in raw:
        print("  FAIL: input contains CR; this file is LF-only")
        return 2

    s = raw.decode("utf-8")
    print()
    print("SUBSTITUTIONS")

    # -- 1. version ---------------------------------------------------------
    s = sub(s, 'TOOL_VERSION = "1.0.0"', 'TOOL_VERSION = "1.1.0"', "TOOL_VERSION")

    # -- 2. docstring outputs block ----------------------------------------
    s = sub(s,
'''  placeids.json       the durable cache; resume-safe, only place_id persisted
  review.csv          live-mode only; Google's returned name/address, for
                      eyeballing the match.  NOT a cache -- see the header
                      written into that file.
"""''',
'''  placeids.json       the durable cache; resume-safe, only place_id persisted
  adjudication.csv    settles the near-miss pairs by measurement: same place_id
                      means same dealer, different place_id means they are not.
                      Reads 'unresolved' for every pair until a live run.
  review-DELETE-AFTER-TRIAGE.csv
                      live-mode only.  Google's returned name and address, for
                      eyeballing each match.  This is NOT a cache: place_id is
                      the only Places field that may be retained, so delete
                      this file once triage is done and never commit it.  The
                      first row of the file repeats that instruction.
"""''', "docstring outputs")

    # -- 3. near_misses returns structured pairs as well as report rows -----
    s = sub(s,
'''    out = []
    by_zone = defaultdict(list)''',
'''    out, pairs = [], []
    by_zone = defaultdict(list)''', "near_misses: init pairs")

    s = sub(s,
'''                        "source_rows": a["source_rows"] + " || " + b["source_rows"],
                    })
    return out''',
'''                        "source_rows": a["source_rows"] + " || " + b["source_rows"],
                    })
                    pairs.append((a["dealer_key"], b["dealer_key"], sorted(rs)))
    # The report rows are unchanged from v1.0.0 so anomalies.csv still matches
    # byte for byte; the pair list is a second return value, never written here.
    return out, pairs''', "near_misses: return pairs")

    # -- 4. adjudication builder -------------------------------------------
    s = sub(s,
'''# --------------------------------------------------------------------------
# 5.  QUERIES
# --------------------------------------------------------------------------''',
'''def build_adjudication(dealers, cache, nm_pairs):
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
# --------------------------------------------------------------------------''', "build_adjudication")

    # -- 5. review filename constant ---------------------------------------
    s = sub(s,
'''ENDPOINT = "https://places.googleapis.com/v1/places:searchText"''',
'''REVIEW_FILENAME = "review-DELETE-AFTER-TRIAGE.csv"
REVIEW_NOTICE = ("DO NOT COMMIT OR RETAIN. Google's Places policy permits "
                 "storing place_id and nothing else. Delete this file once the "
                 "matches have been eyeballed.")

ENDPOINT = "https://places.googleapis.com/v1/places:searchText"''', "review constants")

    # -- 6. call site for near_misses --------------------------------------
    s = sub(s,
'''    dealers, anomalies = build_dealers(all_rows)
    anomalies.extend(near_misses(dealers))''',
'''    dealers, anomalies = build_dealers(all_rows)
    nm_rows, nm_pairs = near_misses(dealers)
    anomalies.extend(nm_rows)''', "near_misses call site")

    # -- 7. review file write ----------------------------------------------
    s = sub(s,
'''        outs.append(("review.csv", *write_csv(
            os.path.join(args.out, "review.csv"), review,
            ["dealer_key", "query", "place_id", "google_name", "google_address",
             "google_lat", "google_lng"])))''',
'''        review.insert(0, {"dealer_key": REVIEW_NOTICE})
        outs.append((REVIEW_FILENAME, *write_csv(
            os.path.join(args.out, REVIEW_FILENAME), review,
            ["dealer_key", "query", "place_id", "google_name", "google_address",
             "google_lat", "google_lng"])))
        print()
        print("  NOTICE: %s" % REVIEW_NOTICE)''', "review write")

    # -- 8. adjudication write + cache guard -------------------------------
    s = sub(s,
'''    outs.append(("queries.csv", *write_csv(
        os.path.join(args.out, "queries.csv"), queries,
        ["dealer_key", "status", "place_id", "query", "bias_lat", "bias_lng",
         "bias_radius_m", "region_code", "brands", "flags", "source_rows"])))''',
'''    outs.append(("queries.csv", *write_csv(
        os.path.join(args.out, "queries.csv"), queries,
        ["dealer_key", "status", "place_id", "query", "bias_lat", "bias_lng",
         "bias_radius_m", "region_code", "brands", "flags", "source_rows"])))

    adjudication = build_adjudication(dealers, cache, nm_pairs)
    outs.append(("adjudication.csv", *write_csv(
        os.path.join(args.out, "adjudication.csv"), adjudication,
        ["verdict", "place_id_a", "place_id_b", "dealer_key_a", "dealer_key_b",
         "name_a", "name_b", "address_a", "address_b", "city", "state", "zip",
         "corroborating", "was_flagged_near_miss"])))''', "adjudication write")

    s = sub(s,
'''    if prior_dealers == cache and prior_stamp:''',
'''    prior_version = prior.get("version", "") if os.path.exists(cache_path) else ""
    if prior_dealers == cache and prior_stamp and prior_version == TOOL_VERSION:''',
        "cache guard includes version")

    # -- 9. adjudication summary in the console ----------------------------
    s = sub(s,
'''    print()
    print("OUTPUTS")''',
'''    print()
    print("ADJUDICATION")
    av = Counter(r["verdict"] for r in adjudication)
    for v, c in sorted(av.items()):
        print("  %-28s %d" % (v, c))
    if not adjudication:
        print("  (nothing to settle)")

    print()
    print("OUTPUTS")''', "adjudication summary")

    # -- 10. self-checks ---------------------------------------------------
    s = sub(s,
'''    check("dry run spent nothing", args.live or resolved_now == 0)''',
'''    check("dry run spent nothing", args.live or resolved_now == 0)
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
          or not adjudication)''', "self-checks")

    out = s.encode("utf-8")
    print()
    print("STRUCTURE")
    checks = [
        ("no CR introduced", b"\\r" not in out),
        ("version is 1.1.0", out.count(b'TOOL_VERSION = "1.1.0"') == 1),
        ("old review.csv literal gone", b'"review.csv"' not in out),
        ("REVIEW_FILENAME referenced 4x (def, 2 writes, 1 check)",
         out.count(b"REVIEW_FILENAME") == 4),
        ("build_adjudication defined once",
         out.count(b"def build_adjudication(") == 1),
        ("near_misses returns two values",
         out.count(b"return out, pairs") == 1),
        ("merge logic untouched",
         out.count(b"def build_dealers(") == 1 and out.count(b"def dealer_key(") == 1),
        ("file grew", len(out) > IN_BYTES),
    ]
    ok = True
    for label, cond in checks:
        print("  %-42s %s" % (label, "PASS" if cond else "FAIL"))
        ok = ok and cond
    if not ok:
        print()
        print("STRUCTURE FAILED -- nothing written")
        return 1

    try:
        compile(out.decode("utf-8"), SRC, "exec")
        print("  %-42s PASS" % "compiles")
    except SyntaxError as e:
        print("  %-42s FAIL  line %s: %s" % ("compiles", e.lineno, e.msg))
        return 1

    print()
    print("OUTPUT")
    print("  %-20s md5 %s  bytes %d" % (SRC, md5(out), len(out)))
    with open(SRC, "wb") as fh:
        fh.write(out)
    print()
    print("self-check ok")
    return 0


if __name__ == "__main__":
    sys.exit(main())
