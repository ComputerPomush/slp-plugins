#!/usr/bin/env python3
"""
patch-step19.py  -  Publish-Step18.ps1 -> Publish-Step19.ps1

Anchored, byte-exact, asserts the input md5 and every anchor's uniqueness
before writing. Same shape as patch-step18.py.

WHAT MOVES

v0.0.22 touches class.slp_avalon.php and slp_avalon.php only. The JS is
untouched, so negative control 1 carries across unchanged.

suite-v021.php IS MODIFIED, and that is worth reading before it looks like
drift. Its F5 asserted count($cfg) === 1 - a snapshot of how many keys
avalon_orphan_config() returned the day it was written. v0.0.22 adds
relink_max, a correct addition, and F5 failed: 49/50. The assertion was
wrong, not the release.

F5 now asserts the INVARIANT instead - that no key configuring post
disposal survives - which holds however many other keys are added later.
Measured 2026-09-08:

    suite-v021 vs v0.0.21   50/50   unchanged
    suite-v021 vs v0.0.22   50/50   was 49/50
    suite-v021 vs v0.0.20   28/50   unchanged, F5 still discriminates

So the pinned triple for suite-v021 is untouched; only its md5 moved. This
is the third assertion this session that was over-specific in the same
way - suite-v021 G6 and suite-v022 S3 both counted a constant once where
the defined() guard and the cast make two. Snapshots of the current shape
break on correct changes; invariants do not.

suite-v012, v015, v016, v017 and v018 were measured against both classes
and are unmoved at 68/33/19/22/32.

Grand total 423 -> 468: PHP 224 + 45 = 269, JS 199 unchanged.

PART B IS REWRITTEN, not adjusted. v0.0.21 removed a branch. This release
WRITES to wp_store_locator and INTERCEPTS public requests, and a checklist
describing a removal would be the wrong instrument for it.

Usage:  python build/patch-step19.py
"""

import hashlib
import os
import sys

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(REPO, "Publish-Step18.ps1")
DST = os.path.join(REPO, "Publish-Step19.ps1")

IN_MD5 = "418629f385fc961f36a6a45fd0d02a11"
IN_BYTES = 55299

EDITS = []


def edit(label, old, new):
    EDITS.append((label, old, new))


edit("self name",
     "if (-not $Self) { $Self = 'Publish-Step18.ps1' }",
     "if (-not $Self) { $Self = 'Publish-Step19.ps1' }")

edit("tags",
     "$Tag      = 'v0.0.21'\n$PrevTag  = 'v0.0.20'",
     "$Tag      = 'v0.0.22'\n$PrevTag  = 'v0.0.21'")

edit("expected class + loader",
     "        Md5 = 'c8ef9f35c55f81c7f952dbf1e66e2aca'; Bytes = 101111; Crlf = 2187\n"
     "    }\n"
     "    'slp_avalon/assets/js/slp_avalon.js'  = @{\n"
     "        Md5 = '61921f1feca574f9f4ba4b36b362d4a0'; Bytes = 71758;  Crlf = 1685\n"
     "    }\n"
     "    'slp_avalon/slp_avalon.php'           = @{\n"
     "        Md5 = 'a728341b388687afc43a6c9bd09c2afc'; Bytes = 1808;   Crlf = 59",
     "        Md5 = '9716901084fae752e831bafecd7db065'; Bytes = 113833; Crlf = 2467\n"
     "    }\n"
     "    'slp_avalon/assets/js/slp_avalon.js'  = @{\n"
     "        Md5 = '61921f1feca574f9f4ba4b36b362d4a0'; Bytes = 71758;  Crlf = 1685\n"
     "    }\n"
     "    'slp_avalon/slp_avalon.php'           = @{\n"
     "        Md5 = '68bd80e14278e547062246e6359d8fee'; Bytes = 1808;   Crlf = 59")

edit("control md5",
     "$PrevClassMd5 = 'd00964ee60539ba470ae6d657280aba3'",
     "$PrevClassMd5 = 'c8ef9f35c55f81c7f952dbf1e66e2aca'")

edit("build output map",
     "    'build/out21/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']\n"
     "    'build/out21/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']",
     "    'build/out22/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']\n"
     "    'build/out22/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']")

edit("stage map",
     "    'build/out21/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'\n"
     "    'build/out21/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'",
     "    'build/out22/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'\n"
     "    'build/out22/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'")

edit("tool files",
     "    'test/suite-v021.php'       = @{ Md5 = '90282a3a36f272da3ac465a3d67c5bd1'; Bytes = 22993 }",
     "    'test/suite-v021.php'       = @{ Md5 = '582da4387c1d527d8d9a200b70803761'; Bytes = 23491 }\n"
     "    'build/build-v022.py'       = @{ Md5 = 'd7b650b89aa47f90af403d8c5d9354f3'; Bytes = 26461 }\n"
     "    'test/suite-v022.php'       = @{ Md5 = '25e78085c54d2622858ecb224d82c4dd'; Bytes = 21362 }\n"
     "    'test/Verify-Suite022.ps1'  = @{ Md5 = 'daf388a2263e76e649ba3a322c1c69cc'; Bytes = 7993 }")

edit("pin rows",
     "    @{ Path = 'slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php' ; Md5 = 'c8ef9f35c55f81c7f952dbf1e66e2aca' }\n"
     "    @{ Path = 'slp-plugins\\slp_avalon\\slp_avalon.php'           ; Md5 = 'a728341b388687afc43a6c9bd09c2afc' }",
     "    @{ Path = 'slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php' ; Md5 = '9716901084fae752e831bafecd7db065' }\n"
     "    @{ Path = 'slp-plugins\\slp_avalon\\slp_avalon.php'           ; Md5 = '68bd80e14278e547062246e6359d8fee' }")

edit("test files",
     "    'test/suite-v020.php', 'test/suite-v021.php',\n"
     "    'test/Verify-Suite021.ps1', 'test/orphan-report.php',",
     "    'test/suite-v020.php', 'test/suite-v021.php', 'test/suite-v022.php',\n"
     "    'test/Verify-Suite021.ps1', 'test/Verify-Suite022.ps1',\n"
     "    'test/orphan-report.php',")

# 'build/build-v021.py', appears in BOTH $TestFiles and $CommitFiles, so
# the anchor has to carry its neighbour to be unique.
edit("build files list",
     "    'build/build-v021.py',\n"
     "    'Verify-v016.ps1',",
     "    'build/build-v021.py', 'build/build-v022.py',\n"
     "    'Verify-v016.ps1',")

edit("publish files list",
     "    'Publish-Step16.ps1', 'Publish-Step17.ps1'\n"
     ")",
     "    'Publish-Step16.ps1', 'Publish-Step17.ps1', 'Publish-Step18.ps1'\n"
     ")")

edit("php suites",
     "    'test/suite-v018.php' = 32; 'test/suite-v021.php' = 50\n"
     "}",
     "    'test/suite-v018.php' = 32; 'test/suite-v021.php' = 50\n"
     "    'test/suite-v022.php' = 45\n"
     "}\n"
     "#\n"
     "# suite-v021 stays at 50 against the v0.0.22 class, but only after its\n"
     "# F5 was rewritten. It asserted count($cfg) === 1 - a snapshot of the\n"
     "# config's shape - and v0.0.22's relink_max broke it at 49/50. The\n"
     "# assertion was wrong, not the release. It now asserts the invariant,\n"
     "# that no post-disposal key survives, and scores 50 against v0.0.21 and\n"
     "# v0.0.22 alike while still failing against v0.0.20. Only its md5 moved.")

edit("commit files",
     "$CommitFiles = @(\n"
     "    'slp_avalon/inc/class.slp_avalon.php',\n"
     "    'slp_avalon/slp_avalon.php',\n"
     "    'build/build-v021.py',\n"
     "    'test/suite-v021.php',\n"
     "    'test/Verify-Suite021.ps1',\n"
     "    'test/orphan-report.php',\n"
     "    'test/release-pins.csv',\n"
     "    'Publish-Step17.ps1',\n"
     "    'Publish-Step18.ps1'\n"
     ")",
     "$CommitFiles = @(\n"
     "    'slp_avalon/inc/class.slp_avalon.php',\n"
     "    'slp_avalon/slp_avalon.php',\n"
     "    'build/build-v022.py',\n"
     "    'test/suite-v021.php',\n"
     "    'test/suite-v022.php',\n"
     "    'test/Verify-Suite022.ps1',\n"
     "    'test/release-pins.csv',\n"
     "    'Publish-Step18.ps1',\n"
     "    'Publish-Step19.ps1'\n"
     ")")

edit("negative control 2",
     "        Write-Host 'Negative control 2 - suite-v021.php vs v0.0.20' -ForegroundColor Cyan\n"
     "        $tmpCls = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v020-negctl.php'",
     "        Write-Host 'Negative control 2 - suite-v022.php vs v0.0.21' -ForegroundColor Cyan\n"
     "        $tmpCls = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v021-negctl.php'")

edit("negative control 2 what",
     "-Dest $tmpCls -WantMd5 $PrevClassMd5 -What 'v0.0.20 class.slp_avalon.php'",
     "-Dest $tmpCls -WantMd5 $PrevClassMd5 -What 'v0.0.21 class.slp_avalon.php'")

edit("negative control 2 suite",
     "            $ctlOut  = & php 'test/suite-v021.php' $tmpCls 2>&1",
     "            $ctlOut  = & php 'test/suite-v022.php' $tmpCls 2>&1")

edit("negative control 2 tags",
     "            $ctlDisc = @($ctlOut | Select-String 'FAIL \\[v21\\]').Count",
     "            $ctlDisc = @($ctlOut | Select-String 'FAIL \\[v22\\]').Count")

edit("negative control 2 numbers",
     "            if ($m.Success -and [int]$m.Groups[1].Value -eq 28 -and [int]$m.Groups[2].Value -eq 50 `\n"
     "                    -and $ctlDisc -eq 22 -and $ctlBoth -eq 0) {\n"
     "                Write-Host '  ok    suite-v021 vs v0.0.20                       28/50, 22 [v21], 0 [both]' -ForegroundColor Green\n"
     "            } else {\n"
     "                Write-Host (\"  FAIL  control: {0}  ([v21] {1}, [both] {2}); wanted 28/50, 22, 0\" -f `",
     "            if ($m.Success -and [int]$m.Groups[1].Value -eq 21 -and [int]$m.Groups[2].Value -eq 45 `\n"
     "                    -and $ctlDisc -eq 24 -and $ctlBoth -eq 0) {\n"
     "                Write-Host '  ok    suite-v022 vs v0.0.21                       21/45, 24 [v22], 0 [both]' -ForegroundColor Green\n"
     "            } else {\n"
     "                Write-Host (\"  FAIL  control: {0}  ([v22] {1}, [both] {2}); wanted 21/45, 24, 0\" -f `")

edit("grand total",
     "if ($grand -ne 423 -and $node -and $php) {\n"
     "    Write-Host (\"  FAIL  expected 423, got {0}\" -f $grand) -ForegroundColor Red",
     "if ($grand -ne 468 -and $node -and $php) {\n"
     "    Write-Host (\"  FAIL  expected 468, got {0}\" -f $grand) -ForegroundColor Red")

edit("doc totals",
     "        JS subtotal 199        PHP subtotal    224        TOTAL 423",
     "        JS subtotal 199        PHP subtotal    269        TOTAL 468")

edit("mode help",
     "    Stage   copy build/out21 into place, write the pin file, re-verify",
     "    Stage   copy build/out22 into place, write the pin file, re-verify")

edit("build missing message",
     "        Write-Host 'build/out21 is missing or does not match. Re-run:' -ForegroundColor Red\n"
     "        Write-Host '    python build\\build-v021.py' -ForegroundColor Red",
     "        Write-Host 'build/out22 is missing or does not match. Re-run:' -ForegroundColor Red\n"
     "        Write-Host '    python build\\build-v022.py' -ForegroundColor Red")

edit("gitignore comment",
     "# build/out*/ went into .gitignore with v0.0.15 and covers build/out21. If it",
     "# build/out*/ went into .gitignore with v0.0.15 and covers build/out22. If it")

edit("deploy checklist",
     "        '      build\\out21\\class.slp_avalon.php  ->  slp_avalon/inc/'\n"
     "        '      build\\out21\\slp_avalon.php        ->  slp_avalon/'\n"
     "        '2.  SSH, then md5sum both. They must read'\n"
     "        '      c8ef9f35c55f81c7f952dbf1e66e2aca   class.slp_avalon.php'\n"
     "        '      a728341b388687afc43a6c9bd09c2afc   slp_avalon.php'",
     "        '      build\\out22\\class.slp_avalon.php  ->  slp_avalon/inc/'\n"
     "        '      build\\out22\\slp_avalon.php        ->  slp_avalon/'\n"
     "        '2.  SSH, then md5sum both. They must read'\n"
     "        '      9716901084fae752e831bafecd7db065   class.slp_avalon.php'\n"
     "        '      68bd80e14278e547062246e6359d8fee   slp_avalon.php'")

edit("part B header",
     "    Write-Host 'PART B - browser and database. This release REMOVES a branch and adds logging.' -ForegroundColor Yellow",
     "    Write-Host 'PART B - v0.0.22 WRITES to wp_store_locator and INTERCEPTS public URLs.' -ForegroundColor Yellow")

edit("part B body",
     "        '7.  BEFORE the first import, record the baseline on Aura DEV:'\n"
     "        '      wp post list --post_type=store_page --post_status=any --format=count'\n"
     "        '    It read 320 on 2026-09-07, against 308 rows and 12 orphans.'\n"
     "        '8.  Run one import. Then read the summary:'\n"
     "        '      wp option get avalon_geocode_last_run --format=json'\n"
     "        '    orphans_trashed IS GONE. It could only ever report 0, which is'\n"
     "        '    why it went. The field to read now is pages_destroyed: the'\n"
     "        '    count of store_page posts SLP force-deleted this run. On an'\n"
     "        '    import with no departing dealers it should be 0.'\n"
     "        '9.  The twelve existing orphans are NOT touched and CANNOT be.'\n"
     "        '    They have no stale row behind them and this pass is row-driven.'\n"
     "        '    Disposition is adjudicated separately - 10 redirects, 1 removal,'\n"
     "        '    1 open - and is a manual job, not an unattended one.'",
     "        '7.  PHASE 1. The orphan posts STAY PUBLISHED for now. The handler'\n"
     "        '    runs on template_redirect before render, so every URL can be'\n"
     "        '    proved while nothing has been lost. Do not delete anything'\n"
     "        '    until step 11 passes.'\n"
     "        '8.  Curl each of the twelve, following no redirects:'\n"
     "        '      curl -s -o /dev/null -w \"%{http_code} %{redirect_url}\\n\" \\'\n"
     "        '        https://aurapontoonstg.wpenginepowered.com/store/victory-marine/'\n"
     "        '    Eleven must read 301 with the target URL. firefish-industries-ltd'\n"
     "        '    must read 410. A 200 means the handler did not fire.'\n"
     "        '9.  GUARD 1, the one that matters. Curl a LIVE dealer page that'\n"
     "        '    shares a slug family - /store/victory-marine-2/ - and confirm'\n"
     "        '    200, not a redirect. A live owned page must never be hijacked.'\n"
     "        '10. Run one import. Then read the summary:'\n"
     "        '      wp option get avalon_geocode_last_run --format=json'\n"
     "        '    pages_relinked and relink_aborted are new. Both should be 0 and'\n"
     "        '    false: all 308 rows are already linked, so the relink pass has'\n"
     "        '    nothing to repair. A NON-ZERO pages_relinked on a clean import'\n"
     "        '    means a page was orphaned during that run - read the relink'\n"
     "        '    records in avalon_geocode_overrides before doing anything else.'\n"
     "        '11. Confirm row count is still 308 and every row still carries a'\n"
     "        '    linked post id:'\n"
     "        '      wp db query \"SELECT COUNT(*) FROM wp_store_locator'\n"
     "        '        WHERE sl_linked_postid > 0\" --skip-column-names'\n"
     "        '12. PHASE 2, only after 8, 9 and 11 pass. Delete the twelve orphan'\n"
     "        '    posts. The same handler then serves them from the 404 path.'\n"
     "        '    Re-run step 8 - the results must be identical - and confirm'\n"
     "        '    the sitemap drops from 321 to 309.'")

edit("rollback",
     "    Write-Host '    THERE IS NO wp-config ROLLBACK FOR THIS RELEASE.'\n"
     "    Write-Host '    AVALON_ORPHAN_CLEANUP and AVALON_ORPHAN_MAX_TRASH were removed;'\n"
     "    Write-Host '    they configured a branch that could never execute, so there is'\n"
     "    Write-Host '    nothing left to switch off. Setting them now does nothing while'\n"
     "    Write-Host '    looking like it worked. Rolling back means redeploying v0.0.20:'\n"
     "    Write-Host '      git show v0.0.20:slp_avalon/inc/class.slp_avalon.php'\n"
     "    Write-Host '      git show v0.0.20:slp_avalon/slp_avalon.php'\n"
     "    Write-Host '    Note that v0.0.21 is strictly LESS destructive than v0.0.20,'\n"
     "    Write-Host '    so a rollback restores code that measurably does nothing.'",
     "    Write-Host '    PARTIAL, without a deploy:'\n"
     "    Write-Host \"      define('AVALON_RELINK_MAX', 0);   // in wp-config.php\"\n"
     "    Write-Host '    Any unlinked row then exceeds the cap, so the relink pass'\n"
     "    Write-Host '    aborts and writes nothing. It records relink_cap_exceeded so'\n"
     "    Write-Host '    the disable is visible rather than silent.'\n"
     "    Write-Host ''\n"
     "    Write-Host '    THE REDIRECT MAP HAS NO CONSTANT. It is deliberately code:'\n"
     "    Write-Host '    twelve slugs, reviewable in a diff, dying with the release'\n"
     "    Write-Host '    that stops needing them. Disabling it means redeploying:'\n"
     "    Write-Host '      git show v0.0.21:slp_avalon/inc/class.slp_avalon.php'\n"
     "    Write-Host '      git show v0.0.21:slp_avalon/slp_avalon.php'\n"
     "    Write-Host '    It is also self-disabling per slug: a live owned store_page'\n"
     "    Write-Host '    at any mapped slug wins over the map, with no intervention.'")

# The out21 in this comment is live text, not narrative - it describes the
# build map directly above it.
edit("build output comment",
     "# emit it and out21 must not be asserted to contain it.",
     "# emit it and out22 must not be asserted to contain it.")

# THE WHOLE TAG MESSAGE IS REPLACED, not patched at its edges. The first
# cut swapped only the header line and the TOTALS block, which left 110
# lines of v0.0.21 body in place - a v0.0.22 tag explaining a removal it
# did not perform. A here-string is one object and has to be treated as one.
TAG_OPEN  = "$TagMessage = @\'\n"
TAG_CLOSE = "\n\'@\n"

TAG_BODY = """v0.0.22: repair pages orphaned by an interrupted write, and dispose of
the twelve that already exist

PART 1 - THE DEFECT FIX - class.slp_avalon.php

store-locator-le 2311.17.01, SLPlus_Location::crupdate_Page():

    509  wp_insert_post()               the page exists
    526  foreach dbFields as property   26 separate add_post_meta calls
    531  MakePersistentIfChanged()      the ROW learns its page id

The one write that prevents orphaning is LAST. Die anywhere in the middle
and the page exists while no row claims it.

Measured, not inferred. The seven orphans on Aura DEV that carry any
slp_location_* postmeta hold 6, 9, 10, 20, 21, 22 and 24 keys, and every
set is an exact PREFIX of dbFields in declaration order. 26 positions
checked against observed per-key counts, ZERO mismatches. The other five
died between the insert and the first add_post_meta.

The trigger - timeout, memory, or a fatal on one row - is not recoverable;
those runs were December to February and the logs are gone. It does not
matter. slp_location_id is written FIRST, so any page that got even one
key names its own sl_id and the missing link is reconstructable from the
page itself.

avalon_relink_orphaned_pages() runs on slp_csv_processing_complete at
priority 5, BEFORE the reconcile at 10, so a row repaired now is visible
to the reconcile and a page it previously could not see becomes
disposable. It links only when exactly one page carries the sl_id and no
other row already owns it. Zero or two candidates log and skip - never
guess. A cap of 25 aborts the whole pass and records why.

Two limits, stated rather than hidden: a page that got zero keys is
unrecoverable, and this cannot help retroactively on Aura DEV because the
2026-08-22 rebuild renumbered the table. All 308 rows are currently
linked. This is PREVENTIVE.

PART 2 - THE CLEANUP

Eleven 301s and one 410 for the twelve existing orphans, adjudicated by
matching each orphan's own slp_location_address postmeta against the live
location table - not by slug similarity, which was wrong twice in
testing. ashley-marine-llc-3 is a CLOSED branch at 621 Columbus Pkwy,
Opelika AL, absent from all three feeds; it goes to the Columbus GA store
by product decision. firefish-industries-ltd has zero feed rows and zero
live siblings: 410.

Two guards, both load-bearing:

  1. SELF-DISABLING. Deleting an orphan frees its base slug. A live
     store_page that OWNS a row always wins over the map, so a future
     page at a mapped slug cannot be hijacked - and a slug repaired by
     Part 1 stops redirecting with no intervention.
  2. NO DEAD ENDS. The target is resolved first. If it does not resolve,
     the 404 is allowed to happen. An honest 404 beats a redirect chain.

ROLLOUT IS TWO PHASES. Ship with the orphan posts still published: the
handler runs on template_redirect before render, so every URL is provable
while nothing has been lost. Only then delete the posts, at which point
the same handler serves them from the 404 path.

TOTALS      JS 199    PHP 269    468
CONTROLS    suite-v019 vs v0.0.18: 21/38, 17 DISCRIMINATOR, 0 GUARD
            suite-v022 vs v0.0.21: 21/45, 24 [v22], 0 [both]

suite-v021's F5 was rewritten. It asserted count($cfg) === 1 - a snapshot
of how many keys the config held - and v0.0.22's relink_max broke it at
49/50. The assertion was wrong, not the release. It now asserts the
invariant, that no post-disposal key survives, and scores 50 against
v0.0.21 and v0.0.22 alike while still failing against v0.0.20. Only its
md5 moved."""


# The five items below survived the Part B replacement above, which swapped
# 7-9 and left the old 9-13 sitting under the new 7-12. The result numbered
# 7,8,9,10,11,12,9,10,11,12,13. Their CONTENT is still valid regression
# work - reconcile_aborted, DONNIE MARCH, the Autocomplete gate - so they
# are renumbered rather than dropped. The self-check below is what would
# have caught it: "PHASE 1 is present" says nothing about what follows it.
edit("part B renumber tail",
     "        '9.  If reconcile_aborted is true, STOP. Either the feed halved or the'",
     "        '13. If reconcile_aborted is true, STOP. Either the feed halved or the'")
edit("part B renumber 10",
     "        '10. Confirm the record count is still 308 and DONNIE MARCH is'",
     "        '14. Confirm the record count is still 308 and DONNIE MARCH is'")
edit("part B renumber 11",
     "        '11. Type a five-digit ZIP into the locator with the Network tab open.'",
     "        '15. Type a five-digit ZIP into the locator with the Network tab open.'")
edit("part B renumber 12",
     "        '12. Load /find-a-dealer/?place_address=48843 and touch nothing.'",
     "        '16. Load /find-a-dealer/?place_address=48843 and touch nothing.'")
edit("part B renumber 13",
     "        '13. Click a dealer name in the results panel. It must resolve, not'",
     "        '17. Click a dealer name in the results panel. It must resolve, not'")


def main():
    if not os.path.exists(SRC):
        print("FAIL  missing %s" % SRC)
        sys.exit(1)

    data = open(SRC, "rb").read()
    got = hashlib.md5(data).hexdigest()
    if got != IN_MD5:
        print("FAIL  input md5 %s expected %s" % (got, IN_MD5))
        sys.exit(1)
    if len(data) != IN_BYTES:
        print("FAIL  input bytes %d expected %d" % (len(data), IN_BYTES))
        sys.exit(1)
    print("input   Publish-Step18.ps1  %s  %d bytes" % (got, len(data)))
    print()

    text = data.decode("utf-8")
    for label, old, new in EDITS:
        c = text.count(old)
        if c != 1:
            print("FAIL  %s: %d occurrences, want 1" % (label, c))
            sys.exit(1)
        text = text.replace(old, new, 1)
        print("  %-28s %6d -> %-6d chars" % (label, len(old), len(new)))

    a = text.find(TAG_OPEN)
    b = text.find(TAG_CLOSE, a)
    if text.count(TAG_OPEN) != 1 or a == -1 or b == -1:
        print("FAIL  tag message here-string not found exactly once")
        sys.exit(1)
    old_len = b - (a + len(TAG_OPEN))
    text = text[:a + len(TAG_OPEN)] + TAG_BODY + text[b:]
    print("  %-28s %6d -> %-6d chars" % ("tag message (whole body)",
                                         old_len, len(TAG_BODY)))

    out = text.encode("utf-8")

    print()
    print("self-checks")
    fails = []

    def ck(cond, msg):
        print("  %s  %s" % ("ok  " if cond else "FAIL", msg))
        if not cond:
            fails.append(msg)

    ck(out.count(b"out21") == 0, "no out21 references remain")
    ck(out.count(b"build/out22/") == 4, "out22 named in BuildOutput and StageMap")
    ck(out.count(b"'v0.0.22'") == 1, "Tag is v0.0.22")
    ck(out.count(b"'v0.0.21'") == 1, "PrevTag is v0.0.21")
    ck(out.count(b"c8ef9f35c55f81c7f952dbf1e66e2aca") == 1,
       "v0.0.21 class md5 appears once, as the control")
    ck(out.count(b"9716901084fae752e831bafecd7db065") == 3,
       "v0.0.22 class md5 in Expected, PinRows and the deploy checklist")
    ck(out.count(b"68bd80e14278e547062246e6359d8fee") == 3,
       "v0.0.22 loader md5 in Expected, PinRows and the deploy checklist")
    ck(out.count(b"'test/suite-v022.php' = 45") == 1, "suite-v022 expected at 45")
    ck(out.count(b"'test/suite-v021.php' = 50") == 1, "suite-v021 still at 50")
    ck(out.count(b"582da4387c1d527d8d9a200b70803761") == 1,
       "suite-v021 repinned to its rewritten md5")
    ck(out.count(b"$grand -ne 468") == 1, "grand-total assertion is 468")
    ck(out.count(b"$grand -ne 423") == 0, "no live 423 assertion remains")
    ck(out.count(b"TOTAL 468") == 1, "doc subtotal line updated")
    ck(out.count(b"PHP 269    468") == 1, "tag message totals updated")
    ck(out.count(b"orphan-trash apparatus") == 0,
       "no v0.0.21 tag-message body survives")
    ck(out.count(b"crupdate_Page()") >= 1, "tag message describes THIS release")
    ck(out.count(b"'Publish-Step19.ps1'") == 2,
       "Step19 named in Self fallback and CommitFiles")
    ck(out.count(b"AVALON_RELINK_MAX', 0") == 1,
       "rollback offers the cap, which this release actually has")
    ck(out.count(b"AVALON_ORPHAN_CLEANUP") == 0,
       "no rollback via a constant removed two releases ago")
    ck(b"PHASE 1" in out and b"PHASE 2" in out,
       "Part B carries the two-phase rollout")

    # A replaced block leaves a tail. "PHASE 1 is present" says nothing
    # about what sits under it, which is how 7..12 ended up followed by a
    # second 9..13. Read the numbers and require one clean run.
    import re as _re
    nums = [int(x) for x in _re.findall(rb"^        '(\d+)\.", out, _re.M)]
    partb = [n for n in nums if n >= 7]
    ck(partb == list(range(7, 18)),
       "Part B numbers run 7..17 exactly once each, got %s" % partb)
    ck(out.count(b"\r\n") == 0, "LF endings preserved")
    ck(out.count(b"@'") == data.count(b"@'") and
       out.count(b"'@") == data.count(b"'@"),
       "here-string delimiters balanced")

    if fails:
        print()
        print("FAIL  %d self-check(s); nothing written" % len(fails))
        sys.exit(1)

    open(DST, "wb").write(out)
    print()
    print("output  Publish-Step19.ps1  %s  %d bytes  (was %d, %+d)"
          % (hashlib.md5(out).hexdigest(), len(out), len(data),
             len(out) - len(data)))
    print()
    print("NEXT: .\\Publish-Step19.ps1 -Mode Stage")


if __name__ == "__main__":
    main()
