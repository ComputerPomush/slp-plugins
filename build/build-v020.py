#!/usr/bin/env python3
"""
build-v020.py

SLP Dealer Guard v0.0.20.

Run from the repo root with no arguments:

    cd D:\\Temp\\Projects\\GitHub\\slp-plugins
    python build\\build-v020.py

Reads the working tree, writes build/out20/, prints md5s. Nothing is
copied into place; Publish-Step16.ps1 -Mode Stage does that.

SCOPE
  Part A, Issue 31 - orphan store_page reconciliation.
      P1  new avalon_orphan_config()
      P2  csv_processing_complete_func() rewritten, two-pass with rails
      P3  three new summary fields
  Part C, the JS half.
      J1  s0.77 comment corrected + threshold 3 -> 4 (decision 67)
      J2  s0.79 comment corrected
  Loader.
      L1  version 0.0.19 -> 0.0.20

MEASURED FACTS THIS RELEASE TURNS ON, 2026-09-05
  Aura LIVE  348 rewrite rules / 17 store-keyed / 7 single-store
             rows 308 = linked 308, posts 321, orphans 13
  Aura DEV   343 / 17 / 7
             rows 308 = linked 308, posts 320, orphans 12
  /store/donnie-march/ returns 200 cache-busted on both, so Issue 32's
  premise does not hold anywhere. The rewrite guard is deferred to
  v0.0.21 as a backstop; this release does not touch rewrite rules.

  s0.77: the widget DOES query text already in the field when it
  attaches. Threshold 3 costs 3 billed Places requests per five-digit
  ZIP; threshold 4 costs 2. The shipped comment says otherwise and
  recommends 2, which would cost 4. Both are corrected here.

CONVENTIONS
  Anchors are written LF and converted to CRLF for the two CRLF files,
  so the source below stays readable and the bytes stay exact. Every
  anchor is asserted unique BEFORE any edit is written - rev14 s8, where
  an 8-space anchor matched the last 8 of a 12-space indent, replaced
  cleanly, reported success and landed at the wrong depth.
"""

import hashlib
import os
import re
import sys

# ------------------------------------------------------------------ paths
REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(REPO, "build", "out20")

CLASS_REL = os.path.join("slp_avalon", "inc", "class.slp_avalon.php")
JS_REL = os.path.join("slp_avalon", "assets", "js", "slp_avalon.js")
LOADER_REL = os.path.join("slp_avalon", "slp_avalon.php")

# ------------------------------------------------------------- input pins
PINS = {
    CLASS_REL: ("4b1ee189381d0c111d0bc5c28c4b8822", 93499, 2038),
    JS_REL: ("528e14299d04fe073b0b970362dc6765", 70159, 1655),
    LOADER_REL: ("d7bc69081de0d5789eaae30d715e4ac7", 1808, 59),
}

FAILURES = []


def ok(msg):
    print("ok    " + msg)


def bad(msg):
    print("FAIL  " + msg)
    FAILURES.append(msg)


def die(msg):
    print("FAIL  " + msg)
    sys.exit(1)


def crlf(text):
    return text.replace("\n", "\r\n").encode("iso-8859-1")


def lf(text):
    return text.encode("iso-8859-1")


def read(rel):
    path = os.path.join(REPO, rel)
    if not os.path.isfile(path):
        die("missing input %s" % rel)
    data = open(path, "rb").read()
    want_md5, want_len, want_cr = PINS[rel]
    got = hashlib.md5(data).hexdigest()
    if got != want_md5:
        die("%s md5 %s, expected %s" % (rel, got, want_md5))
    if len(data) != want_len:
        die("%s length %d, expected %d" % (rel, len(data), want_len))
    cr = data.count(b"\r")
    if cr != want_cr:
        die("%s CR count %d, expected %d" % (rel, cr, want_cr))
    ok("input %s  %s  %d bytes  %d CRLF" % (rel, got, len(data), cr))
    return data


def substitute(data, old, new, label):
    n = data.count(old)
    if n != 1:
        die("%s anchor found %d times, expected exactly 1" % (label, n))
    ok("%s anchor unique" % label)
    return data.replace(old, new, 1)


def strip_php_comments(src):
    src = re.sub(rb"/\*.*?\*/", b"", src, flags=re.S)
    src = re.sub(rb"(?m)^\s*//.*$", b"", src)
    return src


def strip_js_comments(src):
    src = re.sub(rb"/\*.*?\*/", b"", src, flags=re.S)
    src = re.sub(rb"(?m)^\s*//.*$", b"", src)
    return src


# =====================================================================
# P1 - avalon_orphan_config()
# =====================================================================
P1_OLD = """                                     ? (int)   AVALON_IMPORT_GEOCODE_TIMEOUT : 8,
            );
        }

        /**
         * Rows Tier 2 must never move.
"""

P1_NEW = """                                     ? (int)   AVALON_IMPORT_GEOCODE_TIMEOUT : 8,
            );
        }

        /**
         * Issue 31 reconcile rails.
         *
         * floor_pct  The reconcile pass refuses to run at all when
         *            avalon_updated_slp_locations holds fewer hashes than
         *            this fraction of the location table. An import that
         *            died before recording anything leaves that option
         *            empty, and the pre-v0.0.20 loop would then delete
         *            every location on the site - 308 rows - because every
         *            hash misses. 0.5 means a feed that legitimately halved
         *            is also refused, which is correct: that wants a human.
         *
         * max_trash  Cap on store_page posts one import may dispose of.
         *            Aborts the whole pass rather than half-applying, the
         *            rail Tier 2 uses for corrections - but NOT that rail's
         *            number. max_corrections is 60 since v0.0.17, which is
         *            far too loose here: against 320 posts it would permit
         *            trashing a fifth of them. This is sized off its own
         *            measurement instead. The orphan set on 2026-09-05 is
         *            13 on Aura LIVE and 12 on Aura DEV, so 30 carries
         *            better than 2x headroom and stays under a tenth of
         *            the table.
         *
         * cleanup    Master switch. False leaves the pre-v0.0.20 behaviour
         *            exactly: rows deleted, posts left standing. That is
         *            the rollback - one wp-config.php line, no deploy.
         */
        public function avalon_orphan_config(){
            return array(
                'cleanup'   => defined('AVALON_ORPHAN_CLEANUP')
                               ? (bool)  AVALON_ORPHAN_CLEANUP        : true,
                'max_trash' => defined('AVALON_ORPHAN_MAX_TRASH')
                               ? (int)   AVALON_ORPHAN_MAX_TRASH      : 30,
                'floor_pct' => defined('AVALON_RECONCILE_FLOOR_PCT')
                               ? (float) AVALON_RECONCILE_FLOOR_PCT   : 0.5,
            );
        }

        /**
         * Rows Tier 2 must never move.
"""

# =====================================================================
# P2 - csv_processing_complete_func()
# =====================================================================
P2_OLD = """        public function csv_processing_complete_func()
        {
            global $slplus;
            if (!is_a($slplus, 'SLPlus')) {
                update_option('avalon_updated_slp_locations', array());
                return;
            }
            //Remove all the locations that are not in the saved updated locations option
            $updated_locations = get_option('avalon_updated_slp_locations');
            //Get Locations
            $locations = $this->slp_get_all_locations();
            foreach ($locations as $location) {
                //Get Identifier
                $location_hash = $this->create_location_hash(array('location' => $location));
                $identifier = $location['identifier'];
                if (!in_array($location_hash, $updated_locations)) {
                    $slplus->currentLocation->delete($location['sl_id']);
                }
            }
            //Clear the option
            update_option('avalon_updated_slp_locations', array());
        }
"""

P2_NEW = """        /**
         * Reconcile the location table against the feed.
         *
         * Every location whose hash is absent from
         * avalon_updated_slp_locations is removed. Issue 31:
         * currentLocation->delete() drops the wp_store_locator row and
         * leaves the linked store_page post standing. That is where the
         * orphans come from - measured 2026-09-05 as 13 on Aura LIVE and
         * 12 on Aura DEV, the twelve shared by post ID because DEV was
         * cloned from LIVE. rows 308 = linked 308 on both, so the orphan
         * count is exactly posts minus rows with no third case hiding.
         *
         * The post is TRASHED, not deleted. The URL 404s immediately, the
         * trash empties itself after EMPTY_TRASH_DAYS, and the window
         * stays recoverable - this runs unattended every night.
         *
         * Two passes, so a cap aborts cleanly instead of half-applying,
         * the rail Tier 2 already uses for corrections. Pass 1 identifies
         * and mutates nothing. The rails then run against the complete
         * candidate set. Pass 2 acts.
         */
        public function csv_processing_complete_func()
        {
            global $slplus, $wpdb;
            if (!is_a($slplus, 'SLPlus')) {
                update_option('avalon_updated_slp_locations', array());
                return;
            }

            $cfg = $this->avalon_orphan_config();

            //Remove all the locations that are not in the saved updated locations option
            $updated_locations = get_option('avalon_updated_slp_locations');
            if (! is_array($updated_locations)) {
                $updated_locations = array();
            }
            //Get Locations
            $locations = $this->slp_get_all_locations();
            if (! is_array($locations)) {
                $locations = array();
            }

            //Rail 1. Refuse the whole pass when the feed record is missing
            //or implausible. in_array() against an empty set misses every
            //hash, so without this an import that died early deletes the
            //entire table. Pre-existing risk; the post trashing below is
            //what makes it unacceptable to leave in place.
            $floor = (int) ceil(count($locations) * $cfg['floor_pct']);
            if (count($locations) > 0 && count($updated_locations) < $floor) {
                $this->avalon_state_set('reconcile_aborted', true);
                $this->avalon_import_log(array(
                    'stage'   => 'reconcile',
                    'action'  => 'aborted',
                    'reason'  => 'updated_locations below floor',
                    'updated' => count($updated_locations),
                    'floor'   => $floor,
                    'total'   => count($locations),
                ));
                update_option('avalon_updated_slp_locations', array());
                return;
            }

            //Pass 1 - identify. Nothing is mutated here.
            //
            //slp_get_all_locations() does not select sl_linked_postid and
            //is shared with create_location_hash(), so the post id is read
            //per candidate rather than by widening that SELECT.
            $table = $wpdb->prefix . 'store_locator';
            $stale = array();
            foreach ($locations as $location) {
                $location_hash = $this->create_location_hash(array('location' => $location));
                if (in_array($location_hash, $updated_locations)) {
                    continue;
                }
                $post_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT sl_linked_postid FROM {$table} WHERE sl_id = %d",
                        $location['sl_id']
                    )
                );
                $stale[] = array(
                    'sl_id'   => (int) $location['sl_id'],
                    'store'   => isset($location['sl_store']) ? $location['sl_store'] : '',
                    'post_id' => $post_id,
                );
            }

            //Rail 2. Cap the disposal, on the whole candidate set, before
            //anything is touched. Exceeding it leaves the pre-v0.0.20
            //behaviour - rows go, posts stay - and logs the count so the
            //next session sees it. Breaking out of the loop instead would
            //silently change row-deletion behaviour, which the cap is not
            //for.
            $trash_ok = $cfg['cleanup'];
            if ($trash_ok && count($stale) > $cfg['max_trash']) {
                $trash_ok = false;
                $this->avalon_state_set('reconcile_aborted', true);
                $this->avalon_import_log(array(
                    'stage'  => 'reconcile',
                    'action' => 'orphan_cap_exceeded',
                    'stale'  => count($stale),
                    'cap'    => (int) $cfg['max_trash'],
                ));
            }

            //Pass 2 - act.
            foreach ($stale as $row) {
                $slplus->currentLocation->delete($row['sl_id']);
                $this->avalon_state_bump('rows_removed');

                if (! $trash_ok || $row['post_id'] <= 0) {
                    continue;
                }
                //Never trash an arbitrary id. sl_linked_postid can be stale,
                //and a wrong value here would trash a page or a boat model.
                if (get_post_type($row['post_id']) !== 'store_page') {
                    $this->avalon_import_log(array(
                        'stage'   => 'reconcile',
                        'action'  => 'orphan_skipped',
                        'store'   => $row['store'],
                        'post_id' => $row['post_id'],
                        'reason'  => 'post absent or not a store_page',
                    ));
                    continue;
                }
                if (wp_trash_post($row['post_id'])) {
                    $this->avalon_state_bump('orphans_trashed');
                    $this->avalon_import_log(array(
                        'stage'   => 'reconcile',
                        'action'  => 'orphan_trashed',
                        'store'   => $row['store'],
                        'sl_id'   => $row['sl_id'],
                        'post_id' => $row['post_id'],
                    ));
                } else {
                    $this->avalon_import_log(array(
                        'stage'   => 'reconcile',
                        'action'  => 'orphan_trash_failed',
                        'store'   => $row['store'],
                        'post_id' => $row['post_id'],
                    ));
                }
            }

            //Clear the option
            update_option('avalon_updated_slp_locations', array());
        }
"""

# =====================================================================
# P3 - summary fields
# =====================================================================
P3_OLD = """                'tier2_aborted'   => (bool) $this->avalon_state('tier2_aborted'),
                'stale_exclusions'=> $missing
"""

P3_NEW = """                'tier2_aborted'   => (bool) $this->avalon_state('tier2_aborted'),
                'rows_removed'      => (int)  $this->avalon_state('rows_removed'),
                'orphans_trashed'   => (int)  $this->avalon_state('orphans_trashed'),
                'reconcile_aborted' => (bool) $this->avalon_state('reconcile_aborted'),
                'stale_exclusions'=> $missing
"""

# =====================================================================
# J1 - s0.77 comment + threshold 3 -> 4
# =====================================================================
J1_OLD = """  //The widget does not query text already sitting in the field when it
  //attaches, so predictions first appear on the keystroke AFTER the
  //threshold is crossed - at 3, from the fourth character. Set this to 2
  //to put them back at the third and spend one more request per visitor.
  var avalon_autocomplete_min_chars = 3;
"""

J1_NEW = """  //MEASURED 2026-09-04, and the reverse of what this comment said in
  //v0.0.19: the widget DOES query the text already in the field when it
  //attaches. The first request after the gate fires reads
  //AutocompletionService.GetPredictions?1s488. So a query is billed on
  //the threshold keystroke itself, not on the one after it.
  //
  //Cost of a five-digit ZIP, measured, not inferred:
  //    no gate   queries on 1,2,3,4,5   5 requests
  //    3         queries on 3,4,5       3 requests
  //    4         queries on 4,5         2 requests   <- decision 67
  //
  //Do NOT set this to 2. That queries on 2,3,4,5 - four requests, worse
  //than 3 and worse than 4. The v0.0.19 comment recommended exactly that
  //and was wrong.
  //
  //The cost of 4 over 3 is one keystroke of delay before predictions
  //paint. On the ZIP path that is nothing, because the ZIP is complete
  //by then anyway. On the city and street path it is one character.
  var avalon_autocomplete_min_chars = 4;
"""

# =====================================================================
# J2 - s0.79 comment
# =====================================================================
J2_OLD = """    //A field that already holds a value did not get there by typing - the
    //URL bootstrap in cslmap_build_map() fills it - so there are no
    //keystrokes left to save and attaching now preserves the edit path.
"""

J2_NEW = """    //A field that already holds a value did not get there by typing, so
    //there are no keystrokes left to save and attaching now preserves the
    //edit path. The premise is right; v0.0.19's comment named the wrong
    //mechanism. MEASURED order on /find-a-dealer/?place_address=48843:
    //
    //    avalon_init_gmaps()          Maps callback
    //      initialize_autocomplete()    field EMPTY -> deferred branch
    //    cslmap_build_map()           fills #addressInput via .val()
    //                                 -> fires NO input event
    //
    //A URL bootstrap therefore never reaches this branch. What does reach
    //it is browser autofill and bfcache value restoration on a back-button
    //return - real paths, just not the one previously named.
    //
    //Do not "fix" cslmap_build_map() to fire a synthetic input event. It
    //would attach the widget and immediately bill a query for a search
    //that already ran server-side. A deep-link visitor who does not touch
    //the field currently costs zero, and editing still works because the
    //delegated listener below is armed.
"""

# =====================================================================
# L1 - version
# =====================================================================
L1_OLD = " * Version: 0.0.19\n"
L1_NEW = " * Version: 0.0.20\n"


def main():
    print("build-v020.py")
    print("repo  %s" % REPO)
    print()

    # ---------------------------------------------------------- class file
    src = read(CLASS_REL)
    out = src
    out = substitute(out, crlf(P1_OLD), crlf(P1_NEW), "P1 avalon_orphan_config")
    out = substitute(out, crlf(P2_OLD), crlf(P2_NEW), "P2 csv_processing_complete_func")
    out = substitute(out, crlf(P3_OLD), crlf(P3_NEW), "P3 summary fields")
    class_out = out
    print()

    # ------------------------------------------------------------ js file
    js = read(JS_REL)
    jout = js
    jout = substitute(jout, crlf(J1_OLD), crlf(J1_NEW), "J1 threshold + s0.77")
    jout = substitute(jout, crlf(J2_OLD), crlf(J2_NEW), "J2 s0.79")
    print()

    # -------------------------------------------------------- loader file
    loader = read(LOADER_REL)
    lout = substitute(loader, crlf(L1_OLD), crlf(L1_NEW), "L1 version")
    print()

    # --------------------------------------------- structural self-checks
    print("structural self-checks")
    cbare = strip_php_comments(class_out)
    jbare = strip_js_comments(jout)

    checks = [
        # --- P1
        (class_out.count(b"public function avalon_orphan_config(){") == 1,
         "avalon_orphan_config declared once"),
        (cbare.count(b"AVALON_ORPHAN_CLEANUP") == 2,
         "AVALON_ORPHAN_CLEANUP read via defined() and value, in code"),
        (cbare.count(b"AVALON_ORPHAN_MAX_TRASH") == 2,
         "AVALON_ORPHAN_MAX_TRASH read twice in code"),
        (cbare.count(b"AVALON_RECONCILE_FLOOR_PCT") == 2,
         "AVALON_RECONCILE_FLOOR_PCT read twice in code"),
        (class_out.count(b"public function avalon_import_config(){") == 1,
         "avalon_import_config still declared once"),

        # --- P2
        (class_out.count(b"public function csv_processing_complete_func()") == 1,
         "csv_processing_complete_func declared once"),
        (b"$identifier = $location['identifier'];" not in class_out,
         "dead $identifier assignment removed"),
        (cbare.count(b"wp_trash_post(") == 1,
         "wp_trash_post called exactly once"),
        (b"wp_delete_post(" not in cbare,
         "wp_delete_post never used"),
        (cbare.count(b"currentLocation->delete(") == 1,
         "currentLocation->delete still called exactly once"),
        (b"get_post_type($row['post_id']) !== 'store_page'" in class_out,
         "post type guarded before every disposal"),
        (b"sl_linked_postid" in class_out,
         "sl_linked_postid read"),
        (b"$wpdb->prepare(" in class_out,
         "the post id lookup is prepared, not interpolated"),
        (cbare.count(b"global $slplus, $wpdb;") == 1,
         "wpdb pulled into scope once"),
        (class_out.count(b"global $slplus;") ==
         src.count(b"global $slplus;") - 1,
         "exactly one 'global $slplus;' converted; the other 8 untouched"),
        (cbare.count(b"avalon_state_bump('rows_removed')") == 1,
         "rows_removed bumped"),
        (cbare.count(b"avalon_state_bump('orphans_trashed')") == 1,
         "orphans_trashed bumped"),
        (cbare.count(b"update_option('avalon_updated_slp_locations', array());") == 3,
         "option cleared on all three exit paths"),

        # --- P3
        (b"'rows_removed'      => (int)" in class_out,
         "summary carries rows_removed"),
        (b"'orphans_trashed'   => (int)" in class_out,
         "summary carries orphans_trashed"),
        (b"'reconcile_aborted' => (bool)" in class_out,
         "summary carries reconcile_aborted"),
        (class_out.count(b"'stale_exclusions'=> $missing") == 1,
         "stale_exclusions still last and unduplicated"),

        # --- J1 / J2
        (jbare.count(b"var avalon_autocomplete_min_chars = 4;") == 1,
         "threshold is 4"),
        (b"var avalon_autocomplete_min_chars = 3;" not in jout,
         "threshold 3 departed"),
        (jbare.count(b"avalon_autocomplete_min_chars") == 3,
         "threshold read at both gates plus its declaration"),
        (b"Set this to 2" not in jout,
         "the wrong recommendation is gone"),
        (b"URL bootstrap in cslmap_build_map() fills it" not in jout,
         "the wrong mechanism is gone"),
        (jout.count(b"function initialize_autocomplete()") == 1,
         "initialize_autocomplete declared once"),

        # --- L1
        (b"Version: 0.0.20" in lout,
         "loader version bumped"),
        (b"0.0.19" not in lout,
         "no 0.0.19 left in the loader"),

        # --- endings and balance
        (class_out.count(b"\r\n") == class_out.count(b"\n"),
         "class output is wholly CRLF"),
        (jout.count(b"\r\n") == jout.count(b"\n"),
         "js output is wholly CRLF"),
        (lout.count(b"\r\n") == lout.count(b"\n"),
         "loader output is wholly CRLF"),
        (class_out.count(b"{") - class_out.count(b"}") ==
         src.count(b"{") - src.count(b"}"),
         "class brace balance unchanged from input"),
        (jout.count(b"{") - jout.count(b"}") ==
         js.count(b"{") - js.count(b"}"),
         "js brace balance unchanged from input"),

        # --- the ': 25,' collision, measured 2026-09-05
        #
        # suite-v017:293 and suite-v018:490 both count the BARE literal
        # ': 25,' anywhere in the artefact, to catch the Tier 2 cap
        # reverting from 60. An orphan default of 25 collided with both.
        # The cap is 30 for its own reasons; this asserts the collision
        # cannot come back silently.
        (b": 25," not in class_out,
         "no bare ': 25,' anywhere - suite-v017:293 and suite-v018:490"),
        (class_out.count(b"AVALON_TIER2_MAX_CORRECTIONS  : 60,") == 1,
         "v0.0.17 Tier 2 cap of 60 survives untouched"),
        (class_out.count(b"AVALON_ORPHAN_MAX_TRASH      : 30,") == 1,
         "orphan cap default is 30, sized off the measured set of 13"),

        # --- upstream boundary
        (class_out.count(b"store-locator-le") ==
         src.count(b"store-locator-le") == 1,
         "the one pre-existing store-locator-le mention is neither"
         " duplicated nor removed"),
    ]

    for c, d in checks:
        (ok if c else bad)(d)

    if FAILURES:
        print()
        print("FAIL  %d structural check(s) failed; nothing written" % len(FAILURES))
        sys.exit(1)

    # --------------------------------------------------------- write out
    os.makedirs(OUT, exist_ok=True)
    targets = [
        ("class.slp_avalon.php", class_out, src),
        ("slp_avalon.js", jout, js),
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
