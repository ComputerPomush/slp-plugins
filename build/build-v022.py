#!/usr/bin/env python3
"""
build-v022.py  -  SLP Dealer Guard v0.0.21 -> v0.0.22

Reads the working tree, writes build/out22/, prints md5s. Nothing is
written unless every input pin and every structural check passes.

TWO PARTS, AND THEY ARE NOT THE SAME JOB.

PART 1 - avalon_relink_orphaned_pages(). The DEFECT FIX.

Measured 2026-09-08 on Aura DEV. store-locator-le 2311.17.01,
SLPlus_Location::crupdate_Page() does three things in this order:

    509  wp_insert_post()                  the page exists
    526  foreach dbFields as property      26 separate add_post_meta calls
    531  MakePersistentIfChanged()         the ROW learns its page id

The one write that prevents orphaning is LAST. Die anywhere in the middle
and the page exists while no row claims it.

That is not a hypothesis. Seven of the twelve orphans carry a partial
slp_location_* set, at depths 6, 9, 10, 20, 21, 22 and 24 - and every one
is an exact PREFIX of dbFields in declaration order. 26 positions checked
against the observed per-key counts, ZERO mismatches. The other five got
zero keys: they died between the insert and the first add_post_meta.

The trigger - timeout, memory, or a fatal on one dealer's row - is not
recoverable. Those runs were December to February and the php-fpm logs are
long gone. It does not matter. A page that got even ONE field carries
slp_location_id, which is the sl_id it belongs to, so the link that failed
to be written is reconstructable from the page itself. This pass makes the
damage self-healing regardless of what caused it.

Priority 5, BEFORE the reconcile at 10. A row repaired now is visible to
the reconcile, which can then dispose of a page it previously could not
see. The reverse order would leave the same blind spot.

Two limits, stated rather than hidden. It cannot recover a page that got
zero fields - no slp_location_id, nothing to match on. And it cannot help
retroactively on Aura DEV: the 2026-08-22 rebuild renumbered the table, so
the existing orphans carry ids like 91314 while live rows are 104xxx.
All 308 rows are currently linked. This is PREVENTIVE.

PART 2 - avalon_orphan_redirect(). The CLEANUP.

Eleven 301s and one 410, adjudicated by measurement rather than by slug
similarity. Ten resolved by matching the orphan's own postmeta address
against the live location table; beltzville, swinging-bridge, jolleys and
ocean-marine have no postmeta and were forced by having exactly one live
page in their slug family. ashley-marine-llc-3 is a CLOSED location at
621 Columbus Pkwy, Opelika AL - absent from all three feeds - and goes to
the Columbus GA store by product decision, not by measurement.
firefish-industries-ltd has zero feed rows and zero siblings: 410.

TWO GUARDS, BOTH LOAD-BEARING.

  1. SELF-DISABLING. Deleting an orphan frees its base slug. If SLP later
     creates a page at /store/victory-marine/, an unguarded map would
     hijack it. So a store_page that exists at the requested slug AND has
     an owning row always wins. This also means a slug repaired by Part 1
     stops redirecting by itself.

  2. NO DEAD ENDS. The target is resolved before redirecting. If it does
     not resolve to a live owned store_page, the 404 is allowed to happen.
     An honest 404 beats a 301 into another 404.

Cost is nil for the other 320 store URLs: the guards only run for the
twelve slugs in the table.

ROLLOUT IS TWO PHASES AND THE ORDER MATTERS. Ship with the orphan posts
still present - the handler fires on template_redirect before render, so
all twelve URLs can be verified while nothing has been lost. Only then
delete the posts, at which point the same handler serves them from the 404
path and the sitemap drops from 321 to 309.

Usage:  python build/build-v022.py
"""

import hashlib
import os
import sys

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(REPO, "build", "out22")

CLASS_REL = os.path.join("slp_avalon", "inc", "class.slp_avalon.php")
LOADER_REL = os.path.join("slp_avalon", "slp_avalon.php")

#            md5                                bytes   CRLF
PINS = {
    CLASS_REL: ("c8ef9f35c55f81c7f952dbf1e66e2aca", 101111, 2187),
    LOADER_REL: ("a728341b388687afc43a6c9bd09c2afc", 1808, 59),
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
    got = hashlib.md5(data).hexdigest()
    if got != want_md5:
        print("FAIL  %s md5 %s expected %s" % (rel, got, want_md5))
        sys.exit(1)
    if len(data) != want_len:
        print("FAIL  %s bytes %d expected %d" % (rel, len(data), want_len))
        sys.exit(1)
    if data.count(EOL) != want_crlf:
        print("FAIL  %s CRLF %d expected %d"
              % (rel, data.count(EOL), want_crlf))
        sys.exit(1)
    print("input   %-40s %s  %7d bytes  %5d CRLF"
          % (rel, got, len(data), data.count(EOL)))
    return data


def j(*lines):
    return EOL.join(lines)


def sub(blob, label, old, new):
    c = blob.count(old)
    if c != 1:
        print("FAIL  %s: %d occurrences, want 1" % (label, c))
        sys.exit(1)
    print("  %-26s %6d -> %-6d bytes" % (label, len(old), len(new)))
    return blob.replace(old, new, 1)


def strip_php_comments(src):
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


# =========================================================== E1 hooks
E1_OLD = j(
    b"            add_action('slp_csv_processing_complete', array(self::$instance,'remove_old_csv_files_after_import'), 999);",
    b"            add_filter('posts_where', array(self::$instance,'attachments_posts_where'), 10, 2);",
)

E1_NEW = j(
    b"            add_action('slp_csv_processing_complete', array(self::$instance,'remove_old_csv_files_after_import'), 999);",
    b"            //v0.0.22 Part 1. Priority 5, BEFORE the reconcile at 10. A row",
    b"            //repaired now is visible to the reconcile, which can then",
    b"            //dispose of a page it previously could not see. The reverse",
    b"            //order leaves that blind spot in place.",
    b"            add_action('slp_csv_processing_complete', array(self::$instance,'avalon_relink_orphaned_pages'), 5);",
    b"            //v0.0.22 Part 2. Priority 1 so the map is consulted before any",
    b"            //other redirect handler can claim the request.",
    b"            add_action('template_redirect', array(self::$instance,'avalon_orphan_redirect'), 1);",
    b"            add_filter('posts_where', array(self::$instance,'attachments_posts_where'), 10, 2);",
)

# =========================================================== E2 config
E2_OLD = j(
    b"            return array(",
    b"                'floor_pct' => defined('AVALON_RECONCILE_FLOOR_PCT')",
    b"                               ? (float) AVALON_RECONCILE_FLOOR_PCT   : 0.5,",
    b"            );",
)

E2_NEW = j(
    b"            return array(",
    b"                'floor_pct'  => defined('AVALON_RECONCILE_FLOOR_PCT')",
    b"                                ? (float) AVALON_RECONCILE_FLOOR_PCT  : 0.5,",
    b"                //v0.0.22. A handful of unlinked rows is the defect the",
    b"                //relink pass repairs. Hundreds means something systemic",
    b"                //happened, and a mass write would compound it rather",
    b"                //than fix it. 25 sits above the 12 ever observed and",
    b"                //well under a tenth of the table.",
    b"                'relink_max' => defined('AVALON_RELINK_MAX')",
    b"                                ? (int)   AVALON_RELINK_MAX           : 25,",
    b"            );",
)

# =========================================================== E3 summary
E3_OLD = b"                'pages_destroyed'   => (int)  $this->avalon_state('pages_destroyed'),"
E3_NEW = j(
    b"                'pages_destroyed'   => (int)  $this->avalon_state('pages_destroyed'),",
    b"                'pages_relinked'    => (int)  $this->avalon_state('pages_relinked'),",
    b"                'relink_aborted'    => (bool) $this->avalon_state('relink_aborted'),",
)

# =========================================================== E4 methods
E4_OLD = j(
    b"        }",
    b"",
    b"        /**",
    b"         * Rows Tier 2 must never move.",
)

E4_NEW = j(
    b"        }",
    b"",
    b"        /**",
    b"         * v0.0.22 Part 1. Repair pages orphaned by an interrupted write.",
    b"         *",
    b"         * SLPlus_Location::crupdate_Page() inserts the page, then writes",
    b"         * 26 slp_location_* postmeta keys one at a time, and only THEN",
    b"         * calls MakePersistentIfChanged() to tell the row which page is",
    b"         * its own. The write that prevents orphaning is last. Die",
    b"         * anywhere in the middle and the page exists while no row claims",
    b"         * it.",
    b"         *",
    b"         * Measured, not inferred: the seven orphans that carry any",
    b"         * postmeta hold 6, 9, 10, 20, 21, 22 and 24 keys, and each set is",
    b"         * an exact PREFIX of dbFields in declaration order. 26 positions",
    b"         * checked, zero mismatches. The other five died before the first",
    b"         * add_post_meta and are unrecoverable here - nothing identifies",
    b"         * which row they belonged to.",
    b"         *",
    b"         * The trigger is not known and does not need to be. slp_location_id",
    b"         * is written FIRST, so any page that got even one key names its own",
    b"         * sl_id, and the missing link is reconstructable from the page.",
    b"         */",
    b"        public function avalon_relink_orphaned_pages()",
    b"        {",
    b"            global $wpdb;",
    b"            $table = $wpdb->prefix . 'store_locator';",
    b"            $cfg   = $this->avalon_orphan_config();",
    b"",
    b"            $unlinked = $wpdb->get_col(",
    b'                "SELECT sl_id FROM {$table}',
    b'                  WHERE sl_linked_postid IS NULL OR sl_linked_postid = 0"',
    b"            );",
    b"            if (! is_array($unlinked) || count($unlinked) === 0) {",
    b"                return;",
    b"            }",
    b"",
    b"            if (count($unlinked) > $cfg['relink_max']) {",
    b"                $this->avalon_state_set('relink_aborted', true);",
    b"                $this->avalon_import_log(array(",
    b"                    'stage'    => 'relink',",
    b"                    'action'   => 'relink_cap_exceeded',",
    b"                    'unlinked' => count($unlinked),",
    b"                    'cap'      => (int) $cfg['relink_max'],",
    b"                ));",
    b"                return;",
    b"            }",
    b"",
    b"            foreach ($unlinked as $sl_id) {",
    b"                $sl_id = (int) $sl_id;",
    b"                if ($sl_id <= 0) {",
    b"                    continue;",
    b"                }",
    b"",
    b"                $candidates = $wpdb->get_col($wpdb->prepare(",
    b'                    "SELECT p.ID',
    b"                       FROM {$wpdb->postmeta} pm",
    b"                       JOIN {$wpdb->posts} p ON p.ID = pm.post_id",
    b"                      WHERE pm.meta_key   = 'slp_location_id'",
    b"                        AND pm.meta_value = %s",
    b"                        AND p.post_type   = 'store_page'",
    b'                        AND p.post_status = \'publish\'",',
    b"                    (string) $sl_id",
    b"                ));",
    b"                if (! is_array($candidates)) {",
    b"                    $candidates = array();",
    b"                }",
    b"",
    b"                //Never guess. Zero means the page died before its first",
    b"                //meta write; more than one means two pages claim the same",
    b"                //row and a human decides which.",
    b"                if (count($candidates) !== 1) {",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'      => 'relink',",
    b"                        'action'     => 'relink_skipped',",
    b"                        'sl_id'      => $sl_id,",
    b"                        'candidates' => count($candidates),",
    b"                        'reason'     => (count($candidates) === 0)",
    b"                                        ? 'no store_page carries this sl_id'",
    b"                                        : 'more than one store_page claims this sl_id',",
    b"                    ));",
    b"                    continue;",
    b"                }",
    b"",
    b"                $post_id = (int) $candidates[0];",
    b"",
    b"                //Never steal a page another row already owns.",
    b"                $owner = (int) $wpdb->get_var($wpdb->prepare(",
    b'                    "SELECT sl_id FROM {$table} WHERE sl_linked_postid = %d LIMIT 1",',
    b"                    $post_id",
    b"                ));",
    b"                if ($owner > 0) {",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'   => 'relink',",
    b"                        'action'  => 'relink_skipped',",
    b"                        'sl_id'   => $sl_id,",
    b"                        'post_id' => $post_id,",
    b"                        'reason'  => 'page already owned by sl_id ' . $owner,",
    b"                    ));",
    b"                    continue;",
    b"                }",
    b"",
    b"                $slug = get_post_field('post_name', $post_id);",
    b"                $done = $wpdb->update(",
    b"                    $table,",
    b"                    array('sl_linked_postid' => $post_id),",
    b"                    array('sl_id' => $sl_id),",
    b"                    array('%d'),",
    b"                    array('%d')",
    b"                );",
    b"",
    b"                if ($done) {",
    b"                    $this->avalon_state_bump('pages_relinked');",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'   => 'relink',",
    b"                        'action'  => 'page_relinked',",
    b"                        'sl_id'   => $sl_id,",
    b"                        'post_id' => $post_id,",
    b"                        'slug'    => is_string($slug) ? $slug : '',",
    b"                    ));",
    b"                } else {",
    b"                    $this->avalon_import_log(array(",
    b"                        'stage'   => 'relink',",
    b"                        'action'  => 'relink_failed',",
    b"                        'sl_id'   => $sl_id,",
    b"                        'post_id' => $post_id,",
    b"                    ));",
    b"                }",
    b"            }",
    b"        }",
    b"",
    b"        /**",
    b"         * v0.0.22 Part 2. Disposition of the twelve orphaned store pages.",
    b"         *",
    b"         * Adjudicated 2026-09-08 by measurement, not slug similarity. Eight",
    b"         * resolved by matching the orphan's own slp_location_address",
    b"         * postmeta against the live location table; beltzville,",
    b"         * swinging-bridge, jolleys and ocean-marine carry no postmeta and",
    b"         * were forced by having exactly one live page in their slug family.",
    b"         *",
    b"         * ashley-marine-llc-3 is the one product decision here. Its",
    b"         * postmeta reads 621 Columbus Pkwy, Opelika AL, which appears zero",
    b"         * times across all three feeds - a CLOSED location, not a surplus",
    b"         * page for a surviving one. It goes to the Columbus GA store,",
    b"         * roughly thirty miles away, so a visitor stays with the same",
    b"         * dealer. A 410 would also have been defensible.",
    b"         *",
    b"         * This table is deliberately code, not an option. It is twelve",
    b"         * rows, it is reviewable in a diff, and it dies with the release",
    b"         * that stops needing it.",
    b"         */",
    b"        public function avalon_orphan_redirect_map()",
    b"        {",
    b"            return array(",
    b"                'beltzville-manor-marine'       => 'beltzville-manor-marine-2',",
    b"                'swinging-bridge-marina'        => 'swinging-bridge-marina-2',",
    b"                'jolleys-marine-rv-ctr-inc'     => 'jolleys-marine-rv-ctr-inc-2',",
    b"                'seven-winds-marina-inc'        => 'seven-winds-marina-inc-2',",
    b"                'ashley-marine-llc-3'           => 'ashley-marine-llc',",
    b"                'salty-boats'                   => 'salty-boats-2',",
    b"                'ocean-marine'                  => 'ocean-marine-2',",
    b"                'i-94-marine-watersports-llc'   => 'i-94-marine-watersports-llc-3',",
    b"                'victory-marine'                => 'victory-marine-2',",
    b"                'i-94-marine-watersports-llc-2' => 'i-94-marine-watersports-llc-3',",
    b"                'premier-boating-centers-6'     => 'premier-boating-centers-7',",
    b"            );",
    b"        }",
    b"",
    b"        /**",
    b"         * Departed with no survivor to point at. firefish-industries-ltd",
    b"         * has zero rows across all three feeds and zero live pages in its",
    b"         * slug family - two independent signals agreeing, which is what",
    b"         * this list requires before it will 410 anything.",
    b"         */",
    b"        public function avalon_orphan_gone_list()",
    b"        {",
    b"            return array('firefish-industries-ltd');",
    b"        }",
    b"",
    b"        /**",
    b"         * Is this slug a LIVE store page - one that a location row owns?",
    b"         *",
    b"         * Both guards below turn on this. A page with no owning row is an",
    b"         * orphan and does not count as live, which is exactly the",
    b"         * distinction the whole map exists to make.",
    b"         */",
    b"        private function avalon_slug_is_owned($slug)",
    b"        {",
    b"            global $wpdb;",
    b"            $table = $wpdb->prefix . 'store_locator';",
    b"            $id = (int) $wpdb->get_var($wpdb->prepare(",
    b'                "SELECT p.ID',
    b"                   FROM {$wpdb->posts} p",
    b"                   JOIN {$table} s ON s.sl_linked_postid = p.ID",
    b"                  WHERE p.post_name   = %s",
    b"                    AND p.post_type   = 'store_page'",
    b"                    AND p.post_status = 'publish'",
    b'                  LIMIT 1",',
    b"                $slug",
    b"            ));",
    b"            return ($id > 0);",
    b"        }",
    b"",
    b"        /**",
    b"         * Serve the disposition. template_redirect, priority 1.",
    b"         *",
    b"         * Fires whether the request resolved to a post or 404ed, so the",
    b"         * same code works before the orphan posts are deleted and after.",
    b"         * That is what makes the two-phase rollout possible: prove the map",
    b"         * while the posts still exist, then delete them.",
    b"         */",
    b"        public function avalon_orphan_redirect()",
    b"        {",
    b"            if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {",
    b"                return;",
    b"            }",
    b"            if (empty($_SERVER['REQUEST_URI'])) {",
    b"                return;",
    b"            }",
    b"",
    b"            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);",
    b"            if (! is_string($path)) {",
    b"                return;",
    b"            }",
    b"            if (! preg_match('#^/store/([a-z0-9\\-]+)/?$#', $path, $m)) {",
    b"                return;",
    b"            }",
    b"            $slug = $m[1];",
    b"",
    b"            $map  = $this->avalon_orphan_redirect_map();",
    b"            $gone = $this->avalon_orphan_gone_list();",
    b"            if (! isset($map[$slug]) && ! in_array($slug, $gone, true)) {",
    b"                return;",
    b"            }",
    b"",
    b"            //GUARD 1. SELF-DISABLING, and the reason this is safe to leave",
    b"            //in place. Deleting an orphan frees its base slug; if SLP later",
    b"            //creates a real page there, an unguarded map would hijack it.",
    b"            //A live owned page always wins. This also means a slug repaired",
    b"            //by avalon_relink_orphaned_pages() stops redirecting by itself.",
    b"            if ($this->avalon_slug_is_owned($slug)) {",
    b"                return;",
    b"            }",
    b"",
    b"            if (in_array($slug, $gone, true)) {",
    b"                global $wp_query;",
    b"                status_header(410);",
    b"                nocache_headers();",
    b"                if (isset($wp_query) && is_a($wp_query, 'WP_Query')) {",
    b"                    $wp_query->set_404();",
    b"                }",
    b"                return;",
    b"            }",
    b"",
    b"            //GUARD 2. Never redirect into a dead end. If the target has",
    b"            //itself been removed, let the 404 happen - an honest 404 beats",
    b"            //a 301 into another 404.",
    b"            if (! $this->avalon_slug_is_owned($map[$slug])) {",
    b"                return;",
    b"            }",
    b"",
    b"            wp_safe_redirect(home_url('/store/' . $map[$slug] . '/'), 301);",
    b"            exit;",
    b"        }",
    b"",
    b"        /**",
    b"         * Rows Tier 2 must never move.",
)

LOADER_OLD = b" * Version: 0.0.21"
LOADER_NEW = b" * Version: 0.0.22"


def main():
    print("SLP Dealer Guard  v0.0.21 -> v0.0.22")
    print("repo  %s" % REPO)
    print()

    src = read_pinned(CLASS_REL)
    loader = read_pinned(LOADER_REL)
    print()

    print("class.slp_avalon.php")
    out = src
    out = sub(out, "E1 hook registrations", E1_OLD, E1_NEW)
    out = sub(out, "E2 relink cap", E2_OLD, E2_NEW)
    out = sub(out, "E3 summary fields", E3_OLD, E3_NEW)
    out = sub(out, "E4 the two features", E4_OLD, E4_NEW)

    print()
    print("slp_avalon.php")
    lout = sub(loader, "E5 version bump", LOADER_OLD, LOADER_NEW)

    print()
    print("self-checks")
    code = strip_php_comments(out)

    present = [
        b"public function avalon_relink_orphaned_pages()",
        b"public function avalon_orphan_redirect_map()",
        b"public function avalon_orphan_gone_list()",
        b"private function avalon_slug_is_owned($slug)",
        b"public function avalon_orphan_redirect()",
        b"'avalon_relink_orphaned_pages'), 5)",
        b"'avalon_orphan_redirect'), 1)",
        b"avalon_state_bump('pages_relinked')",
        b"AVALON_RELINK_MAX",
        b"wp_safe_redirect(",
        b"status_header(410)",
    ]
    for tok in present:
        (ok if tok in code else bad)("%s present" % tok.decode())

    # Retained from v0.0.21 - a feature release must not quietly undo one.
    for tok in [b"AVALON_RECONCILE_FLOOR_PCT", b"page_destroyed_by_slp",
                b"avalon_state_bump('rows_removed')",
                b"$slplus->currentLocation->delete("]:
        (ok if tok in code else bad)("%s retained" % tok.decode())
    for tok in [b"wp_trash_post", b"orphans_trashed", b"AVALON_ORPHAN_CLEANUP"]:
        (bad if tok in code else ok)("%s still absent" % tok.decode())

    # The map and the gone list must not overlap: a slug cannot both
    # redirect and be gone, and whichever ran first would silently win.
    import re as _re
    m = _re.search(rb"avalon_orphan_redirect_map\(\)\s*\{\s*return array\((.*?)\);",
                   out, _re.S)
    keys = _re.findall(rb"'([a-z0-9\-]+)'\s*=>", m.group(1)) if m else []
    g = _re.search(rb"avalon_orphan_gone_list\(\)\s*\{\s*return array\((.*?)\);",
                   out, _re.S)
    gone = _re.findall(rb"'([a-z0-9\-]+)'", g.group(1)) if g else []
    (ok if len(keys) == 11 else bad)("redirect map holds 11 slugs, got %d" % len(keys))
    (ok if len(gone) == 1 else bad)("gone list holds 1 slug, got %d" % len(gone))
    (ok if not (set(keys) & set(gone)) else bad)("map and gone list do not overlap")
    (ok if len(set(keys)) == len(keys) else bad)("no duplicate source slugs")

    # No orphan may point at another orphan - that is a redirect chain.
    targets = _re.findall(rb"=>\s*'([a-z0-9\-]+)'", m.group(1)) if m else []
    (ok if not (set(targets) & set(keys)) else bad)(
        "no target is itself a redirect source")

    base = strip_php_comments(src)
    d0 = base.count(b"{") - base.count(b"}")
    d1 = code.count(b"{") - code.count(b"}")
    (ok if d1 == d0 else bad)("brace delta %d, input baseline %d" % (d1, d0))
    (ok if out.count(b"\n") == out.count(EOL) else bad)("no bare LF introduced")
    (ok if out.count(EOL * 3) == src.count(EOL * 3) else bad)(
        "blank-line runs %d, input baseline %d"
        % (out.count(EOL * 3), src.count(EOL * 3)))
    (ok if lout.count(b"0.0.22") == 1 and b"0.0.21" not in lout else bad)(
        "loader reads 0.0.22 exactly once")

    if FAILURES:
        print()
        print("FAIL  %d structural check(s) failed; nothing written"
              % len(FAILURES))
        sys.exit(1)

    os.makedirs(OUT, exist_ok=True)
    print()
    print("self-check ok")
    print()
    for name, blob, before in (("class.slp_avalon.php", out, src),
                               ("slp_avalon.php", lout, loader)):
        open(os.path.join(OUT, name), "wb").write(blob)
        print("%-24s %s  %7d bytes  %5d CRLF   (was %d, %+d)"
              % (name, hashlib.md5(blob).hexdigest(), len(blob),
                 blob.count(b"\r"), len(before), len(blob) - len(before)))
    print()
    print("output dir  %s" % OUT)


if __name__ == "__main__":
    main()
