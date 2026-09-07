#!/usr/bin/env python3
"""
build-v021.py  -  SLP Dealer Guard v0.0.20 -> v0.0.21

Reads the working tree, writes build/out21/, prints md5s. Nothing is
written unless every input pin and every structural check passes.

WHAT THIS RELEASE DOES

Removes the orphan-trash apparatus added in v0.0.20. It is provably
unreachable, not merely untested:

  store-locator-le/include/unit/SLPlus_Location.php  (2311.17.01)
    771  delete($id)               get_location($id) first, so linked_postid
                                   is always loaded fresh for the id passed
    794  delete_store_pages()      wp_delete_post($postid, true) behind a
                                   pre_delete_post filter
    848  only_delete_store_pages() vetoes any post whose type is not
                                   store_page
  include/SLPlus.php:81            const locationPostType = 'store_page'

  class.slp_avalon.php v0.0.20
    1371  currentLocation->delete() destroys the post iff store_page
    1379  guard continues           unless the post IS a store_page
    1389  wp_trash_post()           needs a store_page that survived a delete
                                    which removes exactly store_pages

1379 and 1371 are exact complements, so 1389 is unreachable for every
input. Measured 2026-09-07: three unattended runs, orphans_trashed 0 every
time, orphan_skipped logged instead.

Replaced with a log line naming each store_page before SLP destroys it.
The disposal is a force delete - no trash, no undo - and a store_page may
carry Elementor postmeta that the feed cannot rebuild. v0.0.20 produced no
record of what was destroyed.

KEPT DELIBERATELY
  floor_pct / Rail 1   independent of the trash feature and load-bearing.
                       It matters more now: a runaway pass does not orphan
                       308 pages, it destroys them.
  reconcile_aborted    still set by Rail 1
  rows_removed         still meaningful
  two-pass structure   pass 1 now also supplies the disposal log

Usage:  python build/build-v021.py
"""

import hashlib
import os
import sys

# ------------------------------------------------------------------ paths
REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(REPO, "build", "out21")

CLASS_REL = os.path.join("slp_avalon", "inc", "class.slp_avalon.php")
LOADER_REL = os.path.join("slp_avalon", "slp_avalon.php")

# ------------------------------------------------------------- input pins
#            md5                                bytes   CRLF
PINS = {
    CLASS_REL: ("d00964ee60539ba470ae6d657280aba3", 101893, 2204),
    LOADER_REL: ("8d798809eafce52ed0b95d165aed4345", 1808, 59),
}

EOL = b"\r\n"
FAILURES = []


def ok(msg):
    print("  ok    %s" % msg)


def bad(msg):
    FAILURES.append(msg)
    print("  FAIL  %s" % msg)


def read_pinned(rel):
    path = os.path.join(REPO, rel)
    if not os.path.exists(path):
        print("FAIL  missing input %s" % rel)
        sys.exit(1)
    data = open(path, "rb").read()
    want_md5, want_len, want_crlf = PINS[rel]
    got_md5 = hashlib.md5(data).hexdigest()
    if got_md5 != want_md5:
        print("FAIL  %s md5 %s expected %s" % (rel, got_md5, want_md5))
        sys.exit(1)
    if len(data) != want_len:
        print("FAIL  %s bytes %d expected %d" % (rel, len(data), want_len))
        sys.exit(1)
    if data.count(EOL) != want_crlf:
        print("FAIL  %s CRLF %d expected %d"
              % (rel, data.count(EOL), want_crlf))
        sys.exit(1)
    print("input   %-40s %s  %7d bytes  %5d CRLF"
          % (rel, got_md5, len(data), data.count(EOL)))
    return data


def j(*lines):
    """Join source lines with CRLF, matching the files' endings."""
    return EOL.join(lines)


def sub(blob, label, old, new, expect=1):
    """Byte-exact substitution against an anchor asserted unique."""
    c = blob.count(old)
    if c != expect:
        print("FAIL  %s: %d occurrences, want %d" % (label, c, expect))
        sys.exit(1)
    out = blob.replace(old, new, expect)
    print("  %-26s %6d -> %-6d bytes" % (label, len(old), len(new)))
    return out


def strip_php_comments(src):
    """Crude comment stripper, used only so self-checks cannot match their
    own explanatory text. Not a parser; adequate for assertion scoping."""
    out, i, n = bytearray(), 0, len(src)
    while i < n:
        if src[i:i + 2] == b"/*":
            k = src.find(b"*/", i + 2)
            i = n if k == -1 else k + 2
            continue
        if src[i:i + 2] == b"//":
            k = src.find(b"\n", i)
            i = n if k == -1 else k
            continue
        out.append(src[i])
        i += 1
    return bytes(out)


# ============================================================ class edits

E1_OLD = j(
    b"        /**",
    b"         * Issue 31 reconcile rails.",
    b"         *",
    b"         * floor_pct  The reconcile pass refuses to run at all when",
    b"         *            avalon_updated_slp_locations holds fewer hashes than",
    b"         *            this fraction of the location table. An import that",
    b"         *            died before recording anything leaves that option",
    b"         *            empty, and the pre-v0.0.20 loop would then delete",
    b"         *            every location on the site - 308 rows - because every",
    b"         *            hash misses. 0.5 means a feed that legitimately halved",
    b"         *            is also refused, which is correct: that wants a human.",
    b"         *",
    b"         * max_trash  Cap on store_page posts one import may dispose of.",
    b"         *            Aborts the whole pass rather than half-applying, the",
    b"         *            rail Tier 2 uses for corrections - but NOT that rail's",
    b"         *            number. max_corrections is 60 since v0.0.17, which is",
    b"         *            far too loose here: against 320 posts it would permit",
    b"         *            trashing a fifth of them. This is sized off its own",
    b"         *            measurement instead. The orphan set on 2026-09-05 is",
    b"         *            13 on Aura LIVE and 12 on Aura DEV, so 30 carries",
    b"         *            better than 2x headroom and stays under a tenth of",
    b"         *            the table.",
    b"         *",
    b"         * cleanup    Master switch. False leaves the pre-v0.0.20 behaviour",
    b"         *            exactly: rows deleted, posts left standing. That is",
    b"         *            the rollback - one wp-config.php line, no deploy.",
    b"         */",
)

E1_NEW = j(
    b"        /**",
    b"         * Issue 31 reconcile rail.",
    b"         *",
    b"         * floor_pct  The reconcile pass refuses to run at all when",
    b"         *            avalon_updated_slp_locations holds fewer hashes than",
    b"         *            this fraction of the location table. An import that",
    b"         *            died before recording anything leaves that option",
    b"         *            empty, and an unrailed loop would then delete every",
    b"         *            location on the site - 308 rows - because every hash",
    b"         *            misses. 0.5 means a feed that legitimately halved is",
    b"         *            also refused, which is correct: that wants a human.",
    b"         *",
    b"         *            This rail matters more, not less, now that the",
    b"         *            disposal is understood. SLP force-deletes each",
    b"         *            removed location's store_page. A runaway pass does",
    b"         *            not orphan 308 pages, it destroys them, along with",
    b"         *            any Elementor content they carry.",
    b"         *",
    b"         * v0.0.21 removed cleanup and max_trash. They configured a",
    b"         * wp_trash_post() branch that could never execute - SLP disposes",
    b"         * of the post one line earlier. See csv_processing_complete_func().",
    b"         */",
)

E2_OLD = j(
    b"            return array(",
    b"                'cleanup'   => defined('AVALON_ORPHAN_CLEANUP')",
    b"                               ? (bool)  AVALON_ORPHAN_CLEANUP        : true,",
    b"                'max_trash' => defined('AVALON_ORPHAN_MAX_TRASH')",
    b"                               ? (int)   AVALON_ORPHAN_MAX_TRASH      : 30,",
    b"                'floor_pct' => defined('AVALON_RECONCILE_FLOOR_PCT')",
    b"                               ? (float) AVALON_RECONCILE_FLOOR_PCT   : 0.5,",
    b"            );",
)

E2_NEW = j(
    b"            return array(",
    b"                'floor_pct' => defined('AVALON_RECONCILE_FLOOR_PCT')",
    b"                               ? (float) AVALON_RECONCILE_FLOOR_PCT   : 0.5,",
    b"            );",
)

# orphans_trashed can only ever report 0. Reporting a constant as though it
# were a measurement is what let three runs read as inconclusive.
E3_OLD = j(
    b"                'rows_removed'      => (int)  $this->avalon_state('rows_removed'),",
    b"                'orphans_trashed'   => (int)  $this->avalon_state('orphans_trashed'),",
    b"                'reconcile_aborted' => (bool) $this->avalon_state('reconcile_aborted'),",
)

E3_NEW = j(
    b"                'rows_removed'      => (int)  $this->avalon_state('rows_removed'),",
    b"                'pages_destroyed'   => (int)  $this->avalon_state('pages_destroyed'),",
    b"                'reconcile_aborted' => (bool) $this->avalon_state('reconcile_aborted'),",
)

E4_OLD = j(
    b"            //Rail 2. Cap the disposal, on the whole candidate set, before",
    b"            //anything is touched. Exceeding it leaves the pre-v0.0.20",
    b"            //behaviour - rows go, posts stay - and logs the count so the",
    b"            //next session sees it. Breaking out of the loop instead would",
    b"            //silently change row-deletion behaviour, which the cap is not",
    b"            //for.",
    b"            $trash_ok = $cfg['cleanup'];",
    b"            if ($trash_ok && count($stale) > $cfg['max_trash']) {",
    b"                $trash_ok = false;",
    b"                $this->avalon_state_set('reconcile_aborted', true);",
    b"                $this->avalon_import_log(array(",
    b"                    'stage'  => 'reconcile',",
    b"                    'action' => 'orphan_cap_exceeded',",
    b"                    'stale'  => count($stale),",
    b"                    'cap'    => (int) $cfg['max_trash'],",
    b"                ));",
    b"            }",
)

E5_OLD = j(
    b"            //Pass 2 - act.",
    b"            foreach ($stale as $row) {",
    b"                $slplus->currentLocation->delete($row['sl_id']);",
    b"                $this->avalon_state_bump('rows_removed');",
    b"",
    b"                if (! $trash_ok || $row['post_id'] <= 0) {",
    b"                    continue;",
    b"                }",
    b"                //Never trash an arbitrary id. sl_linked_postid can be stale,",
    b"                //and a wrong value here would trash a page or a boat model.",
    b"                if (get_post_type($row['post_id']) !== 'store_page') {",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'   => 'reconcile',",
    b"                        'action'  => 'orphan_skipped',",
    b"                        'store'   => $row['store'],",
    b"                        'post_id' => $row['post_id'],",
    b"                        'reason'  => 'post absent or not a store_page',",
    b"                    ));",
    b"                    continue;",
    b"                }",
    b"                if (wp_trash_post($row['post_id'])) {",
    b"                    $this->avalon_state_bump('orphans_trashed');",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'   => 'reconcile',",
    b"                        'action'  => 'orphan_trashed',",
    b"                        'store'   => $row['store'],",
    b"                        'sl_id'   => $row['sl_id'],",
    b"                        'post_id' => $row['post_id'],",
    b"                    ));",
    b"                } else {",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'   => 'reconcile',",
    b"                        'action'  => 'orphan_trash_failed',",
    b"                        'store'   => $row['store'],",
    b"                        'post_id' => $row['post_id'],",
    b"                    ));",
    b"                }",
    b"            }",
)

E5_NEW = j(
    b"            //Pass 2 - act.",
    b"            //",
    b"            //SLP disposes of the linked store_page itself.",
    b"            //currentLocation->delete() calls delete_store_pages(), which",
    b"            //force-deletes the post behind a pre_delete_post filter that",
    b"            //vetoes anything that is not a store_page. Read at",
    b"            //store-locator-le/include/unit/SLPlus_Location.php:771-846",
    b"            //against 2311.17.01 on 2026-09-07, not inferred from a comment.",
    b"            //",
    b"            //v0.0.20 carried a wp_trash_post() branch here, on the belief",
    b"            //that SLP left the post standing. It could not execute: the",
    b"            //guard demanded a store_page and SLP had already destroyed",
    b"            //exactly that. Three unattended runs logged orphan_skipped and",
    b"            //orphans_trashed 0. Removed in v0.0.21.",
    b"            //",
    b"            //What is recorded instead is what SLP destroyed. The disposal",
    b"            //is a force delete - no trash, no undo - and a store_page can",
    b"            //carry Elementor content and postmeta that the feed cannot",
    b"            //rebuild. Logged BEFORE the delete, because afterwards there",
    b"            //is nothing left to name.",
    b"            foreach ($stale as $row) {",
    b"                if ($row['post_id'] > 0) {",
    b"                    $slug = get_post_field('post_name', $row['post_id']);",
    b"                    $type = get_post_type($row['post_id']);",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'   => 'reconcile',",
    b"                        'action'  => ($type === 'store_page')",
    b"                                     ? 'page_destroyed_by_slp'",
    b"                                     : 'page_retained_not_store_page',",
    b"                        'store'   => $row['store'],",
    b"                        'sl_id'   => $row['sl_id'],",
    b"                        'post_id' => $row['post_id'],",
    b"                        'slug'    => is_string($slug) ? $slug : '',",
    b"                        'type'    => is_string($type) ? $type : '',",
    b"                    ));",
    b"                    if ($type === 'store_page') {",
    b"                        $this->avalon_state_bump('pages_destroyed');",
    b"                    }",
    b"                }",
    b"",
    b"                $slplus->currentLocation->delete($row['sl_id']);",
    b"                $this->avalon_state_bump('rows_removed');",
    b"            }",
)

E6_OLD = j(
    b"         * removes every location whose hash is absent from",
    b"         * avalon_updated_slp_locations, and currentLocation->delete() leaves",
    b"         * the store_page post orphaned: the Aura DEV sitemap carries 321",
    b"         * entries against 308 records, and I-94 Marine alone holds three",
    b"         * permalinks from exactly that delete-and-recreate churn. So it stays.",
)

E6_NEW = j(
    b"         * removes every location whose hash is absent from",
    b"         * avalon_updated_slp_locations, and currentLocation->delete()",
    b"         * force-deletes the row's store_page with it. Suppressing this row",
    b"         * would destroy a live dealer page, not merely orphan it. So it",
    b"         * stays.",
    b"         *",
    b"         * The 321-against-308 sitemap gap is real but unrelated: measured",
    b"         * 2026-09-07, all 12 orphaned pages predate the 2026-08-22 rebuild",
    b"         * and every page created since is linked. They are not produced by",
    b"         * this path.",
)

E7_OLD = j(
    b"         *",
    b"         * Every location whose hash is absent from",
    b"         * avalon_updated_slp_locations is removed. Issue 31:",
    b"         * currentLocation->delete() drops the wp_store_locator row and",
    b"         * leaves the linked store_page post standing. That is where the",
    b"         * orphans come from - measured 2026-09-05 as 13 on Aura LIVE and",
    b"         * 12 on Aura DEV, the twelve shared by post ID because DEV was",
    b"         * cloned from LIVE. rows 308 = linked 308 on both, so the orphan",
    b"         * count is exactly posts minus rows with no third case hiding.",
    b"         *",
    b"         * The post is TRASHED, not deleted. The URL 404s immediately, the",
    b"         * trash empties itself after EMPTY_TRASH_DAYS, and the window",
    b"         * stays recoverable - this runs unattended every night.",
    b"         *",
    b"         * Two passes, so a cap aborts cleanly instead of half-applying,",
    b"         * the rail Tier 2 already uses for corrections. Pass 1 identifies",
    b"         * and mutates nothing. The rails then run against the complete",
    b"         * candidate set. Pass 2 acts.",
)

E7_NEW = j(
    b"         *",
    b"         * Every location whose hash is absent from",
    b"         * avalon_updated_slp_locations is removed, and SLP force-deletes",
    b"         * that row's store_page along with it.",
    b"         *",
    b"         * CORRECTION, v0.0.21. Through v0.0.20 this comment claimed the",
    b"         * post was left standing, and the orphan population was attributed",
    b"         * to this path. Both were wrong, and a release was built on them.",
    b"         * Measured 2026-09-07 on Aura DEV: all 12 orphaned pages predate",
    b"         * the 2026-08-22 rebuild, all 308 pages created since are linked,",
    b"         * and post_modified equals post_date on 11 of the 12 - they were",
    b"         * never updated after creation, so they were orphaned at or near",
    b"         * creation, not by any disposal. This pass is row-driven and",
    b"         * cannot see a page that has no row. It never could.",
    b"         *",
    b"         * Two passes. Pass 1 identifies and mutates nothing, so the floor",
    b"         * rail runs against the complete candidate set. Pass 2 acts, and",
    b"         * records each store_page destroyed before it goes.",
)

LOADER_OLD = b" * Version: 0.0.20"
LOADER_NEW = b" * Version: 0.0.21"


def main():
    print("SLP Dealer Guard  v0.0.20 -> v0.0.21")
    print("repo  %s" % REPO)
    print()

    src = read_pinned(CLASS_REL)
    loader = read_pinned(LOADER_REL)
    print()

    # ------------------------------------------------- class substitutions
    print("class.slp_avalon.php")
    out = src

    # E4 has no replacement. Take its trailing blank line with it, or the
    # removal leaves a doubled gap that no functional check catches and
    # every md5 comparison does.
    if out.count(E4_OLD) != 1:
        print("FAIL  E4 cap rail: %d occurrences, want 1" % out.count(E4_OLD))
        sys.exit(1)
    out = out.replace(E4_OLD + EOL + EOL, b"", 1)
    print("  %-26s %6d -> %-6d bytes" % ("E4 cap rail", len(E4_OLD), 0))

    out = sub(out, "E1 config doc block", E1_OLD, E1_NEW)
    out = sub(out, "E2 config array", E2_OLD, E2_NEW)
    out = sub(out, "E3 state report", E3_OLD, E3_NEW)
    out = sub(out, "E5 pass 2 loop", E5_OLD, E5_NEW)
    out = sub(out, "E6 tier2-hold comment", E6_OLD, E6_NEW)
    out = sub(out, "E7 reconcile doc block", E7_OLD, E7_NEW)

    print()
    print("slp_avalon.php")
    lout = sub(loader, "E8 version bump", LOADER_OLD, LOADER_NEW)

    # ------------------------------------------------------- self-checks
    print()
    print("self-checks")
    code = strip_php_comments(out)

    absent = [
        b"wp_trash_post", b"orphans_trashed", b"orphan_skipped",
        b"orphan_trashed", b"orphan_trash_failed", b"orphan_cap_exceeded",
        b"AVALON_ORPHAN_CLEANUP", b"AVALON_ORPHAN_MAX_TRASH",
        b"max_trash", b"trash_ok",
    ]
    present = [
        b"AVALON_RECONCILE_FLOOR_PCT", b"floor_pct",
        b"avalon_state_set('reconcile_aborted', true)",
        b"avalon_state_bump('rows_removed')",
        b"avalon_state_bump('pages_destroyed')",
        b"page_destroyed_by_slp", b"page_retained_not_store_page",
        b"$slplus->currentLocation->delete(",
    ]
    for tok in absent:
        (bad if code.count(tok) else ok)(
            "%s removed from code" % tok.decode())
    for tok in present:
        (ok if tok in code else bad)("%s retained" % tok.decode())

    # Brace balance. The crude stripper leaves braces inside string
    # literals, so the unmodified file does NOT balance - it reads 284/285.
    # Absolute equality is not a valid invariant and asserting it fails a
    # correct edit. What must hold is zero change to the imbalance.
    base = strip_php_comments(src)
    d0 = base.count(b"{") - base.count(b"}")
    d1 = code.count(b"{") - code.count(b"}")
    (ok if d1 == d0 else bad)(
        "brace delta %d, input baseline %d" % (d1, d0))

    (ok if out.count(b"\n") == out.count(EOL) else bad)(
        "no bare LF introduced")
    (ok if out.count(EOL * 3) == src.count(EOL * 3) else bad)(
        "blank-line runs %d, input baseline %d"
        % (out.count(EOL * 3), src.count(EOL * 3)))

    a = out.find(b"page_destroyed_by_slp")
    b = out.find(b"$slplus->currentLocation->delete($row['sl_id'])")
    (ok if 0 < a < b else bad)("disposal log precedes the delete")

    (ok if lout.count(b"0.0.21") == 1 and b"0.0.20" not in lout else bad)(
        "loader reads 0.0.21 exactly once")

    if FAILURES:
        print()
        print("FAIL  %d structural check(s) failed; nothing written"
              % len(FAILURES))
        sys.exit(1)

    # ------------------------------------------------------------ write
    os.makedirs(OUT, exist_ok=True)
    targets = [
        ("class.slp_avalon.php", out, src),
        ("slp_avalon.php", lout, loader),
    ]
    print()
    print("self-check ok")
    print()
    for name, blob, before in targets:
        open(os.path.join(OUT, name), "wb").write(blob)
        print("%-24s %s  %7d bytes  %5d CRLF   (was %d, %+d)"
              % (name, hashlib.md5(blob).hexdigest(), len(blob),
                 blob.count(b"\r"), len(before), len(blob) - len(before)))
    print()
    print("output dir  %s" % OUT)


if __name__ == "__main__":
    main()
