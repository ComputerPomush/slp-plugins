<#
.SYNOPSIS
    SLP Dealer Guard - stage, verify, commit and tag v0.0.20.

.DESCRIPTION
    Issue 31, the orphan store_page reconciliation, plus decision 67 and two
    comment corrections in the JavaScript. Three plugin files move and four
    test files move with them.

    1. class.slp_avalon.php   csv_processing_complete_func() rewritten
                              two-pass, plus a new avalon_orphan_config()
                              and three new summary fields.
    2. slp_avalon.js          avalon_autocomplete_min_chars 3 -> 4, and the
                              s0.77 and s0.79 comments corrected.
    3. slp_avalon.php         the version header. Nothing else.
    4. suite-v019.js          MIN hoisted, three threshold cases rewritten,
                              s0.80's companion added.
    5. suite-v017.php         one over-broad literal qualified.
    6. suite-v018.php         one non-discriminating assertion repaired.
    7. suite-v020.php         new.

    .gitattributes does NOT move this release. Same md5 since v0.0.19.

    WHY ISSUE 32 IS NOT IN THIS RELEASE. It was the planned headline. Measured
    2026-09-05 on both Aura environments:

        Aura LIVE   348 rewrite rules / 17 store-keyed / 7 single-store
        Aura DEV    343 / 17 / 7
        /store/donnie-march/ returns 200, cache-busted, on both

    The rules are healthy everywhere and the 404s are not reproducible, so the
    self-healing guard would defend against a state that is not present. It is
    deferred to v0.0.21 as a backstop. What the same measurements DID confirm
    is Issue 31: rows 308 = linked 308 on both, posts 321 LIVE and 320 DEV,
    so 13 and 12 orphans respectively, twelve of them sharing post IDs because
    DEV was cloned from LIVE. LIVE's thirteenth, the-power-garage-inc, was
    created during the 2026-08-22 rebuild and orphaned after it - the churn is
    still running.

    THE LATENT MASS DELETE. The pre-v0.0.20 loop had no rails. in_array()
    against an empty avalon_updated_slp_locations misses every hash, so an
    import that died before recording anything deleted all 308 rows. That
    shipped. Adding post trashing on top is what made it unacceptable, so the
    rail and the trashing land together. suite-v020 D4a is the case: 0 deletes
    against v0.0.20, 20 of 20 against v0.0.19.

    FOUR differences from Publish-Step15.ps1, all deliberate.

    1. $Self. s0.75: Publish-Step15's Stage mode closed with "Now run:
       .\Publish-Step14.ps1 -Mode Verify" - the previous release's filename,
       carried forward by copy. The name is now read from $MyInvocation once
       and printed from that, so it cannot go stale.

    2. BOTH interpreters are fatal. Step15 made node fatal and php a warning
       because the release was JavaScript and the class had not moved. v0.0.20
       moves both, so neither can be downgraded.

    3. TWO negative controls, against two different tags. suite-v019.js is
       scored against v0.0.18 because that is the last build without the gate;
       suite-v020.php is scored against v0.0.19. Pinning suite-v019 to v0.0.19
       would give 35/38 - a control that passes nearly everything, which is
       what a control must never do.

    4. The five carried PHP suites are RE-MEASURED, not assumed. The class
       moved 93,499 -> 101,893 bytes, and two assertions in suite-v017 and
       suite-v018 already broke on the new text before they were repaired.
       Both searched for the bare literal ': 25,' anywhere in the artefact and
       collided with the orphan cap default. suite-v018's was worse: it summed
       two counts and required 1, which scores 1 for [60 present, 25 absent]
       AND for [60 absent, 25 present], so it had never been able to detect
       the regression it was written for. It asserts the pair now.

    FIVE differences from Publish-Step16.ps1. Items 5-7 are carried out of
    rev22; items 8 and 9 were measured while testing them.

    5. $ToolFiles asserts BYTE LENGTH as well as md5. s0.94: rev21 recorded
       build-v020.py as 27,987 bytes when it is 25,729. The md5 was correct
       and identical in the document and in AllinlocalGithub.csv, so the
       wrong number sat there unchallenged - nothing checked it. Anything
       typed that nothing checks will drift again. Confirmed on 2026-09-06:
       a one-byte append to suite-v017.php failed on length, before the md5
       line was reached.

    6. The repo directory NAME is a precondition. s0.95: every row in
       release-pins.csv begins 'slp-plugins\', and Test-PinFile resolves
       those rows against the PARENT of the repo root. A verification clone
       at ...\Temp\slp-v020-check produced six 'pinned path does not exist'
       FAILs while everything of substance passed. Checked once now, at the
       top, naming the cause. Confirmed both ways on 2026-09-06: rejected
       'wrongname', and passed 434/434 from D:\Temp\slp-plugins. Only the
       LEAF name matters - that directory is nowhere near the GitHub root,
       and all six pins still resolved.

    7. The Verify failure epilogue is CONDITIONAL. s0.96: it printed the
       suite-total explanation on every failure, including the s0.95 one
       where all thirteen suites read full and no total had moved. It now
       fires only when a suite actually reported a different assertion
       count, and says how many did. Confirmed on 2026-09-06: a corrupted
       .gitattributes failed two checks and printed no suite-total text.

    8. The banner prints the RESOLVED TARGET. s0.106: on 2026-09-06 this
       script was run from D:\Temp\wrongname without -PluginRepo, took the
       hardcoded default, and verified the real repo while every visual cue
       named the copy. $Self fixed the stale FILENAME at s0.75; the target
       was still invisible. A Commit typed after that run would have
       committed somewhere the operator was not looking.

    9. CurrentDirectory is RESTORED on exit. s0.107: the line below that
       sets [System.Environment]::CurrentDirectory is load-bearing - node
       and php are native processes and inherit the process CWD, not the
       PowerShell location set by Push-Location. But it outlives the script,
       and on Windows the process CWD holds an open handle. On 2026-09-06 a
       Rename-Item on the verification clone failed with "being used by
       another process" after this script exited. In the release chain that
       becomes Remove-VerifyClone.ps1 failing at step 14, at the end of a
       release rather than during a test.

       The body is wrapped in try/finally WITHOUT reindentation, so the diff
       against Publish-Step16.ps1 shows only the inserted lines and every
       other line stays byte-identical. PowerShell does not care about the
       indentation; a reviewer comparing the two files does. exit inside a
       try still runs the finally, so all eight exits and all ten throws are
       covered.

    Verified totals, measured not estimated, 2026-09-06:

        suite-v008   24        suite-v012.php   68   (class MOVED, re-run)
        suite-v009   13        suite-v015.php   33   (class MOVED, re-run)
        suite-v010   40        suite-v016.php   19   (class MOVED, re-run)
        suite-v011   15        suite-v017.php   22   (class MOVED, re-run)
        suite-v013   35        suite-v018.php   32   (class MOVED, re-run)
        suite-v014   34        suite-v020.php   61   (new)
        suite-v019   38   (was 35, three cases added)

        JS subtotal 199        PHP subtotal    224        TOTAL 423

    NEGATIVE CONTROLS

        suite-v019.js vs v0.0.18 js    21/38, 17 DISCRIMINATOR, 0 GUARD
        suite-v020.php vs v0.0.19 class 30/61, 31 [v20], 0 [both]

    Fourteen cases in suite-v020 were tagged [v20] and passed against the
    control. The tag is load-bearing documentation, so they were retagged
    [both] rather than left claiming a discrimination they do not perform.
    Two cases added to suite-v019 were tagged [GUARD] for the same wrong
    reason and are now [DISCRIMINATOR]. In both files the tags were assigned
    by reasoning first and corrected by a control run - which is the only
    thing that can assign them.

    CARRIED TO THE WAVE, not fixable here.

      - assets/js/googlelocation.js still present on Aura LIVE, Tahoe DEV and
        Avalon DEV. initialize_autocomplete is a global; the ungated copy wins
        wherever it is enqueued later. Grep those three.
      - The hello-elementor-child avalon_log() PII fix was promoted to Aura
        LIVE on 2026-09-06 and its log tree deleted. Tahoe DEV measured 388K
        and Tahoe LIVE, Avalon DEV and Avalon LIVE were never measured. Code
        fix FIRST, then back up, then delete - Aura LIVE was done in the wrong
        order and the tree regrew until the code landed.
      - AVALON_IMPORT_GEOCODE_BUDGET is defined on Aura DEV only, at 400, and
        sits after require_once wp-settings.php. LIVE runs the 150 default.
      - Aura DEV has no object cache; $memcached_servers is empty there and
        populated on LIVE. A transient accepted on DEV has not exercised the
        path LIVE runs.

.PARAMETER Mode
    Stage   copy build/out21 into place, write the pin file, re-verify
    Verify  assert the working tree, run every suite and both controls
    Commit  stage the expected file set and commit
    Tag     create the annotated tag and print the acceptance checklist

.PARAMETER PluginRepo
    Defaults to the working clone. Point it at a fresh clone with -PluginRepo .
    to run checklist item 11.

.EXAMPLE
    .\Publish-Step16.ps1 -Mode Stage
    .\Publish-Step16.ps1 -Mode Verify
    .\Publish-Step16.ps1 -Mode Commit
    .\Publish-Step16.ps1 -Mode Tag

    One at a time. Pasting all four together fires Commit even when Verify has
    failed, because a failed Verify exits and the next pasted line starts a
    fresh process.
#>

[CmdletBinding()]
param(
    [ValidateSet('Stage', 'Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# s0.107. Captured before anything can change it, restored in the finally at
# the foot of the script. Setting CurrentDirectory to an empty string throws,
# so the restore is guarded rather than assumed.
$OrigCwd = [System.Environment]::CurrentDirectory

# s0.75. Read once from the invocation so no message can name a stale script.
$Self = Split-Path -Leaf $MyInvocation.MyCommand.Path
if (-not $Self) { $Self = 'Publish-Step18.ps1' }

$Tag      = 'v0.0.21'
$PrevTag  = 'v0.0.20'
$CtlJsTag = 'v0.0.18'    # last build without the Autocomplete gate
#
# CtlJsTag does not move. v0.0.21 does not touch slp_avalon.js, so
# negative control 1 is carried across unchanged and every JS total
# holds. A JS control that moved with a PHP-only release would be
# measuring nothing.

# Expected working-tree state AFTER staging. Update these together with
# build-v020.py, never independently.
$Expected = @{
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = 'c8ef9f35c55f81c7f952dbf1e66e2aca'; Bytes = 101111; Crlf = 2187
    }
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = '61921f1feca574f9f4ba4b36b362d4a0'; Bytes = 71758;  Crlf = 1685
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = 'a728341b388687afc43a6c9bd09c2afc'; Bytes = 1808;   Crlf = 59
    }
}

# Control targets. The class comes from v0.0.20, the JS still from
# v0.0.18 - the JS did not move this release.
$PrevClassMd5 = 'd00964ee60539ba470ae6d657280aba3'
$CtlJsMd5     = '8c93719e41af3232c18773a104e8dedd'

# What build-v021.py writes. Same hashes, different location. Two files,
# not three: this release does not touch the JS, so build-v021 does not
# emit it and out21 must not be asserted to contain it.
$BuildOutput = @{
    'build/out21/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']
    'build/out21/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']
}

$StageMap = @{
    'build/out21/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'
    'build/out21/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'
}

# The files delivered this session. A stale or truncated copy of any of them is
# caught here rather than as an unexplained score forty lines down. build-v020
# was itself run twice this session because the first download did not land on
# top of the old file, and the only tell was an md5 in the build output. This
# script is deliberately NOT in the table: a file cannot assert its own md5
# without the assertion changing the file. Its integrity is covered by the
# fresh-clone check, item 11 of the checklist.
#
# s0.94: byte length is asserted beside every md5. rev21 carried
# build-v020.py at 27,987 bytes when it is 25,729 - the md5 was right in
# both the handoff and AllinlocalGithub.csv, so identical bytes were
# certain and the length was simply never read by anything. Two
# independent quantities catch a truncated download that a single one can
# still miss on a partial write.
# BUILD_V021_MD5 / SUITE_V021_MD5 / VERIFY_MD5 / ORPHAN_MD5 were measured on the delivered
# files, not guessed. orphan-report.php landed on the server at 6,112
# bytes, matching the source exactly, which is what confirms the
# delivery path preserves bytes and makes these safe to pin at all.
$ToolFiles = @{
    'build/build-v020.py'       = @{ Md5 = 'ddd1ba655b61ec8fce219305ae106a32'; Bytes = 25729 }
    'test/suite-v020.php'       = @{ Md5 = '6186c6182e774f01432467567358f5c3'; Bytes = 26390 }
    'test/suite-v019.js'        = @{ Md5 = 'e4f1cc3021c6cda1275727e5aa2fb869'; Bytes = 18721 }
    'test/suite-v017.php'       = @{ Md5 = '0882d4e4e7bd275b521e3dfd68b04db3'; Bytes = 15987 }
    'test/suite-v018.php'       = @{ Md5 = 'fb03fd6d95fb6997c35e6419634559a6'; Bytes = 20265 }
    'build/build-v021.py'       = @{ Md5 = 'e7a307b55c1897202186efd64d94c4ee'; Bytes = 22891 }
    'test/suite-v021.php'       = @{ Md5 = '90282a3a36f272da3ac465a3d67c5bd1'; Bytes = 22993 }
    'test/Verify-Suite021.ps1'  = @{ Md5 = 'e11ef74471e519ffde6ecc2e2daeab98';     Bytes = 7993 }
    'test/orphan-report.php'    = @{ Md5 = 'a41e9925a7dc663eccadae17db051b51';     Bytes = 9314 }
}

# Unchanged this release. store-locator-le/js belongs to the SLP plugin author
# and is asserted by md5 only. .gitattributes is what keeps those two hashes
# surviving a fresh clone - Issue 34, v0.0.19.
$Upstream = @{
    'store-locator-le/js/slp_core.js'     = 'a751bea043c19472ec6453aff93f84a9'
    'store-locator-le/js/slp_core.min.js' = '7924dad949f851d90ade9118c8bd045a'
    '.gitattributes'                      = 'd6f1b5bc350f43a202b6abb91daa36ed'
}

# test/release-pins.csv, in file order. Paths are relative to the GitHub root
# because Inventory-LocalGitHub.ps1 -PinFile runs from there. Backslashes are
# load-bearing.
$PinRelPath = 'test/release-pins.csv'
$PinRows = @(
    @{ Path = 'slp-plugins\slp_avalon\assets\js\slp_avalon.js'  ; Md5 = '61921f1feca574f9f4ba4b36b362d4a0' }
    @{ Path = 'slp-plugins\slp_avalon\inc\class.slp_avalon.php' ; Md5 = 'c8ef9f35c55f81c7f952dbf1e66e2aca' }
    @{ Path = 'slp-plugins\slp_avalon\slp_avalon.php'           ; Md5 = 'a728341b388687afc43a6c9bd09c2afc' }
    @{ Path = 'slp-plugins\.gitattributes'                      ; Md5 = 'd6f1b5bc350f43a202b6abb91daa36ed' }
    @{ Path = 'slp-plugins\store-locator-le\js\slp_core.js'     ; Md5 = 'a751bea043c19472ec6453aff93f84a9' }
    @{ Path = 'slp-plugins\store-locator-le\js\slp_core.min.js' ; Md5 = '7924dad949f851d90ade9118c8bd045a' }
)

# Existence is fatal, not a warning. A warning here is what let v0.0.8 be
# tagged with a message claiming assertions that were not in the repository.
$TestFiles = @(
    'test/harness.js', 'test/suite-core.js',
    'test/suite-v008.js', 'test/suite-v009.js', 'test/suite-v010.js',
    'test/suite-v011.js', 'test/suite-v012.php', 'test/suite-v013.js',
    'test/suite-v014.js', 'test/suite-v015.php', 'test/suite-v016.php',
    'test/suite-v017.php', 'test/suite-v018.php', 'test/suite-v019.js',
    'test/suite-v020.php', 'test/suite-v021.php',
    'test/Verify-Suite021.ps1', 'test/orphan-report.php',
    'test/release-pins.csv',
    'build/build-v015.py', 'build/build-v016.py', 'build/build-v017.py',
    'build/build-v018.py', 'build/build-v019.py', 'build/build-v020.py',
    'build/build-v021.py',
    'Verify-v016.ps1', 'Publish-Step11.ps1', 'Publish-Step12.ps1',
    'Publish-Step13.ps1', 'Publish-Step14.ps1', 'Publish-Step15.ps1',
    'Publish-Step16.ps1', 'Publish-Step17.ps1'
)

# suite-v019 gains three cases: 35 -> 38.
$JsSuites = @{
    'test/suite-v008.js' = 24; 'test/suite-v009.js' = 13
    'test/suite-v010.js' = 40; 'test/suite-v011.js' = 15
    'test/suite-v013.js' = 35; 'test/suite-v014.js' = 34
    'test/suite-v019.js' = 38
}

# Every one of these reads the class, and the class MOVED. A difference is
# a finding to take back to the handoff, not a number to edit here.
#
# suite-v020.php IS ABSENT AND THAT IS DELIBERATE. Measured against the
# v0.0.21 class it does not score lower, it fatals - Call to undefined
# function get_post_field() - because its stubs predate the function this
# release calls, and its D2a/D2b cases assert cleanup and max_trash,
# which are gone. The file stays in the repository and keeps its pin;
# only its expectation is retired. Its one piece of surviving coverage,
# D6a's >= floor boundary, moved to suite-v021 D6-D8 BEFORE it was
# dropped here.
#
# The other five were measured unmoved against both classes on
# 2026-09-07, so this release did not disturb them.
$PhpSuites = @{
    'test/suite-v012.php' = 68; 'test/suite-v015.php' = 33
    'test/suite-v016.php' = 19; 'test/suite-v017.php' = 22
    'test/suite-v018.php' = 32; 'test/suite-v021.php' = 50
}

# slp_avalon.js is NOT here. It did not change this release, and staging
# an unchanged file invites a diff nobody can explain later.
#
# Both Publish-Step17.ps1 and this script are listed. Step 17 named only
# its predecessor, which leaves the newest publish script untracked until
# the next release writes one - the gap decision 74 was raised for. A
# script naming itself in $CommitFiles is safe; naming itself in
# $ToolFiles would not be, because a file cannot assert its own md5
# without the assertion changing the file. It does not appear there.
$CommitFiles = @(
    'slp_avalon/inc/class.slp_avalon.php',
    'slp_avalon/slp_avalon.php',
    'build/build-v021.py',
    'test/suite-v021.php',
    'test/Verify-Suite021.ps1',
    'test/orphan-report.php',
    'test/release-pins.csv',
    'Publish-Step17.ps1',
    'Publish-Step18.ps1'
)

$TagMessage = @'
v0.0.21: remove the orphan-trash apparatus, which could never execute

ISSUE 31 - class.slp_avalon.php

v0.0.20 added a wp_trash_post() branch to pass 2 of the reconcile, on the
belief - stated in comments at lines 691 and 1269 - that
currentLocation->delete() drops the wp_store_locator row and leaves the
linked store_page standing. That belief was never measured and it is
false.

Read at store-locator-le 2311.17.01 on 2026-09-07:

    SLPlus_Location.php:771  delete($id) calls get_location($id) first
                       :794  then delete_store_pages()
                       :843  wp_delete_post($postid, true) behind a
                             pre_delete_post filter
                       :848  which vetoes any post that is not a
                             store_page
    SLPlus.php:81            const locationPostType = 'store_page'

So SLP force-deletes the linked post if and only if it is a store_page.
The guard at line 1379 continued unless the post WAS a store_page, and
line 1389 therefore needed a store_page that had survived a delete which
removes exactly store_pages. Those are exact complements: the branch was
unreachable for every input, not merely untested. Three unattended cron
runs logged orphan_skipped with orphans_trashed 0 every time.

Removed: the trash branch, its two failure branches, the disposal cap,
AVALON_ORPHAN_CLEANUP, AVALON_ORPHAN_MAX_TRASH, and the orphans_trashed
summary field, which could only ever report 0.

Kept: floor_pct and Rail 1, which are independent of all of it and now
matter more - a runaway pass does not orphan 308 pages, it destroys them.

Added: a log line naming each store_page BEFORE SLP destroys it, with its
slug, plus a pages_destroyed counter. The disposal is a force delete with
no trash and no undo, and a store_page can carry Elementor postmeta the
feed cannot rebuild. v0.0.20 produced no record of what it destroyed.

THE ORPHANS ARE A SEPARATE DEFECT. Measured on Aura DEV 2026-09-07: all
12 orphaned pages predate the 2026-08-22 rebuild, all 308 pages created
since are linked, and post_modified equals post_date on 11 of the 12.
They were orphaned at or near creation, by a path this reconcile cannot
see and never could. Disposition is adjudicated but not yet applied.

Measured 2026-09-05, both Aura environments:

    rows 308 = linked 308      every row carries sl_linked_postid
    posts 321 LIVE / 320 DEV   orphans 13 / 12

Twelve orphans share post IDs across the two, because DEV was cloned from
LIVE. LIVE's thirteenth was created during the 2026-08-22 rebuild and orphaned
after it, so the churn is still running rather than historical.

The method is now two-pass. Pass 1 identifies stale rows and reads
sl_linked_postid per candidate - slp_get_all_locations() does not select it and
is shared with create_location_hash(), so widening that SELECT would reach
past this change. Two rails then run against the complete candidate set. Pass 2
deletes rows and trashes posts.

The post is TRASHED, not deleted. The URL 404s immediately, the trash empties
itself after EMPTY_TRASH_DAYS, and the window stays recoverable for something
that runs unattended every night. get_post_type() is checked before every
disposal, because a stale sl_linked_postid would otherwise trash a page.

RAIL 1 IS A PRE-EXISTING DEFECT, FIXED HERE. in_array() against an empty
avalon_updated_slp_locations misses every hash, so an import that died before
recording anything deleted the entire table. The pass now refuses when the
recorded set is below AVALON_RECONCILE_FLOOR_PCT of the table.

RAIL 2 caps disposals at AVALON_ORPHAN_MAX_TRASH and aborts the whole pass
rather than half-applying, the rail Tier 2 uses for corrections. Exceeding it
leaves the pre-v0.0.20 behaviour and logs the count.

DECISIONS TAKEN THIS RELEASE

  67. avalon_autocomplete_min_chars moves 3 -> 4. MEASURED, s0.77: the widget
      DOES query text already in the field when it attaches, so a query bills
      on the threshold keystroke itself. A five-digit ZIP costs 5 requests
      ungated, 3 at a threshold of 3, and 2 at 4. The v0.0.19 comment said the
      opposite and recommended 2, which costs 4.
  68. Issue 36, SLP's jQuery-UI zip suggester, is scoped but not shipped. The
      preferred fix is removal conditional on SLP's own settings surface. It
      does not belong in a release headlined by the reconcile loop.
  69. AVALON_ORPHAN_MAX_TRASH defaults to 30, sized off the measured orphan
      set of 13, NOT mirrored from AVALON_TIER2_MAX_CORRECTIONS. That is 60
      since v0.0.17 and far too loose here: against 320 posts it would permit
      trashing a fifth of them.

ISSUE 32 IS DEFERRED, NOT DONE

It was the planned headline. 348 rules / 17 store-keyed / 7 single-store on
LIVE, 343 / 17 / 7 on DEV, and /store/donnie-march/ returns 200 cache-busted
on both. The rewrite rules are healthy everywhere and the 404s are not
reproducible, so a self-healing guard would defend a state that is not
present. v0.0.21 carries it as a backstop.

THREE TEST FILES MOVE WITH THE CODE

suite-v017.php and suite-v018.php both counted the bare literal ': 25,'
anywhere in the artefact, to catch the Tier 2 cap reverting from 60. The
orphan cap default collided with both. suite-v018's was worse than
over-broad: it summed two counts and required 1, scoring 1 for
[60 present, 25 absent] AND for [60 absent, 25 present], so it had never been
able to detect the regression it was written for. It asserts the pair now.

suite-v019.js hoists the threshold to one constant, rewrites the three cases
that were keyed to 3, corrects the s0.79 comment - a URL bootstrap fills
#addressInput via .val() and fires no input event, so it never reaches the
seeded branch - and adds s0.80's companion case for the order that actually
occurs.

TOTALS      JS 199    PHP 224    423
CONTROLS    suite-v019 vs v0.0.18: 21/38, 17 DISCRIMINATOR, 0 GUARD
            suite-v021 vs v0.0.20: 28/50, 22 [v21], 0 [both]

suite-v020.php is retired from the expectation table. Against the
v0.0.21 class it fatals on an undefined get_post_field(), and its
cleanup and max_trash cases assert a feature this release removed. Its
surviving coverage - the >= floor boundary - moved to suite-v021 D6-D8
before it was dropped. The file and its pin remain.
'@

# --------------------------------------------------------------- helpers

function Assert-Git {
    param([string]$What, [int[]]$Allow = @(0))
    if ($Allow -notcontains $LASTEXITCODE) { throw "git $What exited $LASTEXITCODE" }
}

function Get-FileStat {
    param([string]$FullPath)

    $bytes = [System.IO.File]::ReadAllBytes($FullPath)
    if ($bytes.Length -eq 0) { throw "zero-length file: $FullPath" }
    $cr = 0; $lf = 0
    for ($i = 0; $i -lt $bytes.Length; $i++) {
        if ($bytes[$i] -eq 13) { $cr++ } elseif ($bytes[$i] -eq 10) { $lf++ }
    }
    [pscustomobject]@{
        Md5   = (Get-FileHash -LiteralPath $FullPath -Algorithm MD5).Hash.ToLower()
        Bytes = $bytes.Length
        Cr    = $cr
        Lf    = $lf
        Last  = $bytes[-1]
    }
}

function Assert-File {
    param([string]$Root, [string]$RelPath, [hashtable]$Want)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) {
        Write-Host ("  FAIL  MISSING: {0}" -f $RelPath) -ForegroundColor Red
        return $false
    }

    $s = Get-FileStat $full

    # Byte and CRLF counts are re-derived here, independently of the build's
    # printout. Checking them separately is what tells a line-ending accident
    # apart from a content change.
    $problems = @()
    if ($s.Md5   -ne $Want.Md5)   { $problems += "md5 $($s.Md5) != $($Want.Md5)" }
    if ($s.Bytes -ne $Want.Bytes) { $problems += "bytes $($s.Bytes) != $($Want.Bytes)" }
    if ($s.Cr    -ne $Want.Crlf)  { $problems += "CR $($s.Cr) != $($Want.Crlf)" }
    if ($s.Cr    -ne $s.Lf)       { $problems += "CR $($s.Cr) != LF $($s.Lf) (mixed endings)" }
    if ($s.Last  -eq 10)          { $problems += "trailing newline present" }

    if ($problems.Count -gt 0) {
        Write-Host ("  FAIL  {0}" -f $RelPath) -ForegroundColor Red
        $problems | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        return $false
    }
    Write-Host ("  ok    {0,-40} {1}  {2,7} bytes  CRLF={3}" -f `
        $RelPath, $s.Md5, $s.Bytes, $s.Cr) -ForegroundColor Green
    return $true
}

function Assert-Md5Only {
    # $WantBytes defaults to -1, meaning "do not check". $Upstream calls this
    # with md5 only and is unchanged; $ToolFiles passes a length as well.
    param([string]$Root, [string]$RelPath, [string]$Want, [int]$WantBytes = -1)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) {
        Write-Host ("  FAIL  MISSING: {0}" -f $RelPath) -ForegroundColor Red
        return $false
    }
    if ($WantBytes -ge 0) {
        $len = (Get-Item -LiteralPath $full).Length
        if ($len -ne $WantBytes) {
            Write-Host ("  FAIL  {0}" -f $RelPath) -ForegroundColor Red
            Write-Host ("          {0} bytes != {1}" -f $len, $WantBytes) -ForegroundColor Red
            return $false
        }
    }
    $md5 = (Get-FileHash -LiteralPath $full -Algorithm MD5).Hash.ToLower()
    if ($md5 -ne $Want) {
        Write-Host ("  FAIL  {0}" -f $RelPath) -ForegroundColor Red
        Write-Host ("          md5 {0} != {1}" -f $md5, $Want) -ForegroundColor Red
        return $false
    }
    Write-Host ("  ok    {0,-40} {1}" -f $RelPath, $md5) -ForegroundColor Green
    return $true
}

function Write-PinFile {
    param([string]$Root, [string]$RelPath, [array]$Rows)

    # LF only, no BOM. Set-Content writes CRLF; Windows PowerShell's default
    # UTF8 encoding writes a BOM. Both would corrupt a file that six rows of
    # tooling parse by exact string match.
    $text = "RelativePath,MD5Hash`n"
    foreach ($r in $Rows) { $text += ('{0},{1}' -f $r.Path, $r.Md5) + "`n" }

    $full = Join-Path $Root $RelPath
    [System.IO.File]::WriteAllText($full, $text, (New-Object System.Text.UTF8Encoding($false)))

    $s = Get-FileStat $full
    $wantLf = $Rows.Count + 1
    if ($s.Cr -ne 0)       { throw "release-pins.csv written with $($s.Cr) CR bytes; must be LF only" }
    if ($s.Lf -ne $wantLf) { throw "release-pins.csv has $($s.Lf) LF, expected $wantLf" }
    Write-Host ("  ok    {0,-40} {1}  {2,7} bytes  LF={3}" -f `
        $RelPath, $s.Md5, $s.Bytes, $s.Lf) -ForegroundColor Green
}

function Test-PinFile {
    <#
        Reads the pin file back off disk and checks that it parses, that every
        hash in it matches the file on disk, and that the three files this
        release moves carry the NEW hashes. The pin file is generated from
        $Expected, so re-reading it is the only thing that turns it from a
        copy into a check.
    #>
    param([string]$Root, [string]$RelPath, [hashtable]$Expected, [hashtable]$Upstream)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) {
        Write-Host ("  FAIL  MISSING: {0}" -f $RelPath) -ForegroundColor Red
        return $false
    }

    $rows = @(Import-Csv -LiteralPath $full)
    if ($rows.Count -ne 6) {
        Write-Host ("  FAIL  {0} has {1} rows, expected 6" -f $RelPath, $rows.Count) -ForegroundColor Red
        return $false
    }

    $ok = $true
    # GitHub-root-relative by design - see s0.95 at the top of the script,
    # where the repo directory name is checked as a precondition so that a
    # misnamed clone cannot surface here as six unexplained FAILs.
    $ghRoot = Split-Path -Parent $Root
    foreach ($r in $rows) {
        $disk = Join-Path $ghRoot $r.RelativePath
        if (-not (Test-Path -LiteralPath $disk)) {
            Write-Host ("  FAIL  pinned path does not exist: {0}" -f $r.RelativePath) -ForegroundColor Red
            $ok = $false
            continue
        }
        $md5 = (Get-FileHash -LiteralPath $disk -Algorithm MD5).Hash.ToLower()
        if ($md5 -ne $r.MD5Hash.ToLower()) {
            Write-Host ("  FAIL  pin mismatch: {0}" -f $r.RelativePath) -ForegroundColor Red
            Write-Host ("          disk {0} != pin {1}" -f $md5, $r.MD5Hash) -ForegroundColor Red
            $ok = $false
        }
    }

    # The three moving files must carry THIS release's hashes, not the last
    # release's. A pin file left untouched would pass every check above.
    $mustHave = @(
        $Expected['slp_avalon/inc/class.slp_avalon.php'].Md5,
        $Expected['slp_avalon/assets/js/slp_avalon.js'].Md5,
        $Expected['slp_avalon/slp_avalon.php'].Md5
    )
    $inPin = $rows | ForEach-Object { $_.MD5Hash.ToLower() }
    foreach ($m in $mustHave) {
        if ($inPin -notcontains $m) {
            Write-Host ("  FAIL  {0} does not carry {1} - stale pin file" -f $RelPath, $m) -ForegroundColor Red
            $ok = $false
        }
    }

    if ($ok) { Write-Host ("  ok    {0,-40} 6 rows, all matching disk" -f $RelPath) -ForegroundColor Green }
    return $ok
}

function Invoke-Suite {
    <#
        Runs one suite against one artefact and asserts the SCORE, not just the
        exit code. A suite that fails for the wrong reason looks identical to
        one that fails for the right reason at the exit-code level, which is
        exactly the failure mode a partial negative control can hide.
    #>
    param(
        [ValidateSet('php', 'node')][string]$Runner,
        [string]$SuiteRel,
        [string]$ArtefactPath,
        [int]$ExpectPass,
        [int]$ExpectTotal,
        [string]$Label
    )

    $out  = & $Runner $SuiteRel $ArtefactPath 2>&1
    $code = $LASTEXITCODE
    $line = ($out | Select-String 'PASS' | Select-Object -Last 1)

    # [regex]::Match rather than -match. -match does populate $Matches on a
    # successful match, but reading a capture group out of an operator that
    # returned $false is the kind of thing that survives review and then breaks
    # under a StrictMode bump.
    $m = [regex]::Match("$line", '(\d+)\s*/\s*(\d+)\s+(?:assertions\s+)?PASS')
    if (-not $m.Success) {
        Write-Host ("  FAIL  {0}: no score line" -f $Label) -ForegroundColor Red
        $out | Select-Object -Last 12 | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        return $false
    }

    $got = [int]$m.Groups[1].Value
    $tot = [int]$m.Groups[2].Value

    $problems = @()
    if ($tot -ne $ExpectTotal) {
        $problems += "suite has $tot assertions, expected $ExpectTotal"
        # Explicit assignment rather than $script:X++ : scoped increment is
        # valid PowerShell but was not parse-checkable where this was written,
        # and this form cannot be wrong.
        $script:SuiteTotalMoved = $script:SuiteTotalMoved + 1
    }
    if ($got -ne $ExpectPass)  { $problems += "scored $got/$tot, expected $ExpectPass/$ExpectTotal" }

    if ($problems.Count -gt 0) {
        Write-Host ("  FAIL  {0}" -f $Label) -ForegroundColor Red
        $problems | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        $out | Select-Object -Last 12 | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        return $false
    }

    Write-Host ("  ok    {0,-46} {1}/{2}" -f $Label, $got, $tot) -ForegroundColor Green
    return $true
}

function Get-TagBlob {
    <#
        Extract one path at one tag to a temp file, byte-faithfully.

        cmd redirection rather than a PowerShell pipe: piping git output
        through PowerShell re-encodes it, and -Encoding Byte was removed in
        PowerShell 7.
    #>
    param([string]$Ref, [string]$Dest, [string]$WantMd5, [string]$What)

    Remove-Item -LiteralPath $Dest -ErrorAction SilentlyContinue
    & cmd /c "git show $Ref > `"$Dest`""
    if (-not (Test-Path -LiteralPath $Dest)) { throw "could not extract $Ref" }

    $md5 = (Get-FileHash -LiteralPath $Dest -Algorithm MD5).Hash.ToLower()
    if ($md5 -ne $WantMd5) {
        Write-Host ("  FAIL  {0} extracted as {1}, expected {2}" -f $What, $md5, $WantMd5) -ForegroundColor Red
        Write-Host "        git show returned something other than the expected blob." -ForegroundColor Red
        return $null
    }
    Write-Host ("  ok    control target {0}" -f $Ref) -ForegroundColor Green
    return $Dest
}

# ------------------------------------------------------------------- head

Write-Host ''
# s0.107. Body wrapped without reindentation, deliberately - see item 9 in
# the header. The matching finally is the last block in this file.
try {
Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]   ($Self)" -ForegroundColor Cyan
Write-Host ('-' * 78)

if (-not (Test-Path -LiteralPath $PluginRepo)) { throw "Plugin repo not found: $PluginRepo" }
$PluginRepo = (Resolve-Path -LiteralPath $PluginRepo).Path
[System.Environment]::CurrentDirectory = $PluginRepo   # PS location != .NET CWD

# s0.106. The banner names the SCRIPT. This names the REPOSITORY it is about
# to read, write, commit and tag. Printed after Resolve-Path so it is the
# real target and not the argument, and before the name check below so a
# rejected run still shows what it resolved to.
Write-Host ("target  {0}" -f $PluginRepo) -ForegroundColor Cyan

# s0.95. Every row in release-pins.csv begins 'slp-plugins\', because the
# file is GitHub-root-relative - Inventory-LocalGitHub.ps1 -PinFile runs
# from there. Test-PinFile therefore resolves each row against the PARENT
# of the repo root, and a clone in a directory named anything else fails
# all six pin checks for a reason that has nothing to do with the release.
# That happened at ...\Temp\slp-v020-check: six FAILs, 434 assertions and
# both control triples clean.
#
# Measured 2026-09-06: only the LEAF name matters. D:\Temp\slp-plugins is
# nowhere near the GitHub root and passed 434/434 with all six pins
# resolving, because $ghRoot is simply the parent of wherever this sits.
#
# Fail once here, naming the cause, rather than six times naming symptoms.
$repoLeaf = Split-Path -Leaf $PluginRepo
if ($repoLeaf -ne 'slp-plugins') {
    Write-Host ''
    Write-Host ("FAIL  repo directory is named '{0}', not 'slp-plugins'." -f $repoLeaf) -ForegroundColor Red
    Write-Host '      release-pins.csv rows are GitHub-root-relative and resolve against' -ForegroundColor Red
    Write-Host '      the PARENT of this directory, so all six pin checks would fail for' -ForegroundColor Red
    Write-Host '      a reason unrelated to the release. Rename the clone and re-run.' -ForegroundColor Red
    exit 1
}

Write-Host ''
Write-Host 'Session artefacts' -ForegroundColor Cyan
$toolsOk = $true
foreach ($rel in ($ToolFiles.Keys | Sort-Object)) {
    if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel `
            -Want $ToolFiles[$rel].Md5 -WantBytes $ToolFiles[$rel].Bytes)) { $toolsOk = $false }
}
if (-not $toolsOk) {
    Write-Host ''
    Write-Host ("One of this session's {0} files is missing or stale." -f $ToolFiles.Count) -ForegroundColor Red
    Write-Host 'build-v020.py was downloaded twice this session because the first copy' -ForegroundColor Red
    Write-Host 'did not land on top of the old one, and the only tell was an md5 in the' -ForegroundColor Red
    Write-Host 'build output. That is what this table exists to catch.' -ForegroundColor Red
    exit 1
}

# ------------------------------------------------------------------ stage

if ($Mode -eq 'Stage') {
    Write-Host ''
    Write-Host 'Build output' -ForegroundColor Cyan
    $ok = $true
    foreach ($rel in ($BuildOutput.Keys | Sort-Object)) {
        if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $BuildOutput[$rel])) { $ok = $false }
    }
    if (-not $ok) {
        Write-Host ''
        Write-Host 'build/out21 is missing or does not match. Re-run:' -ForegroundColor Red
        Write-Host '    python build\build-v021.py' -ForegroundColor Red
        exit 1
    }

    Write-Host ''
    Write-Host 'Copying into place' -ForegroundColor Cyan
    foreach ($src in ($StageMap.Keys | Sort-Object)) {
        $from = Join-Path $PluginRepo $src
        $to   = Join-Path $PluginRepo $StageMap[$src]
        Copy-Item -LiteralPath $from -Destination $to -Force
        Write-Host ("  copied {0}" -f $StageMap[$src]) -ForegroundColor Green
    }

    Write-Host ''
    Write-Host 'Pin file' -ForegroundColor Cyan
    Write-PinFile -Root $PluginRepo -RelPath $PinRelPath -Rows $PinRows

    Write-Host ''
    Write-Host 'Re-verifying in place' -ForegroundColor Cyan
    $ok = $true
    foreach ($rel in ($Expected.Keys | Sort-Object)) {
        if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $Expected[$rel])) { $ok = $false }
    }
    foreach ($rel in ($Upstream.Keys | Sort-Object)) {
        if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel -Want $Upstream[$rel])) { $ok = $false }
    }
    if (-not (Test-PinFile -Root $PluginRepo -RelPath $PinRelPath -Expected $Expected -Upstream $Upstream)) { $ok = $false }

    Write-Host ''
    if (-not $ok) { Write-Host 'Staging FAILED.' -ForegroundColor Red; exit 1 }
    Write-Host "Staged. Now run: .\$Self -Mode Verify" -ForegroundColor Green
    exit 0
}

# ------------------------------------------------------------ working tree

Write-Host ''
Write-Host 'Working tree' -ForegroundColor Cyan
$allOk = $true

# s0.96. Bumped by Invoke-Suite when a suite reports a different ASSERTION
# COUNT from the one this script expects. That is the only condition the
# failure epilogue at the foot of the script should speak to. A dropped
# score, a missed negative control and a wrong pin path are three other
# failures that were all being handed the suite-total explanation.
$script:SuiteTotalMoved = 0
foreach ($rel in ($Expected.Keys | Sort-Object)) {
    if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $Expected[$rel])) { $allOk = $false }
}
foreach ($rel in ($Upstream.Keys | Sort-Object)) {
    if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel -Want $Upstream[$rel])) { $allOk = $false }
}
if (-not (Test-PinFile -Root $PluginRepo -RelPath $PinRelPath -Expected $Expected -Upstream $Upstream)) { $allOk = $false }

foreach ($rel in $TestFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $PluginRepo $rel))) {
        Write-Host ("  FAIL  MISSING: {0}" -f $rel) -ForegroundColor Red
        $allOk = $false
    }
}

# build/out*/ went into .gitignore with v0.0.15 and covers build/out21. If it
# is not there, the build directory is swept into this commit.
$gi = Join-Path $PluginRepo '.gitignore'
if (-not (Test-Path -LiteralPath $gi)) {
    Write-Host '  FAIL  MISSING: .gitignore' -ForegroundColor Red
    $allOk = $false
} elseif ([System.IO.File]::ReadAllText($gi) -notmatch 'build/out') {
    Write-Host '  FAIL  .gitignore does not ignore build/out*/ - it went in with v0.0.15' -ForegroundColor Red
    $allOk = $false
} else {
    Write-Host '  ok    .gitignore covers build/out*/' -ForegroundColor Green
}

# ------------------------------------------------------------- test suites

Write-Host ''
Write-Host 'Test suites' -ForegroundColor Cyan

$jsArtefact = Join-Path $PluginRepo 'slp_avalon/assets/js/slp_avalon.js'
$clsArtefact = Join-Path $PluginRepo 'slp_avalon/inc/class.slp_avalon.php'
$grand = 0

Push-Location $PluginRepo
try {
    # --- both interpreters are fatal this release. Difference 2.
    $node = Get-Command node -ErrorAction SilentlyContinue
    $php  = Get-Command php  -ErrorAction SilentlyContinue
    if (-not $node) {
        Write-Host '  FAIL  node not on PATH. v0.0.20 moves the JavaScript.' -ForegroundColor Red
        $allOk = $false
    }
    if (-not $php) {
        Write-Host '  FAIL  php not on PATH. v0.0.20 moves the class.' -ForegroundColor Red
        $allOk = $false
    }

    if ($node) {
        & node --check $jsArtefact
        if ($LASTEXITCODE -ne 0) { throw "node --check failed on slp_avalon.js (exit $LASTEXITCODE)" }
        Write-Host '  ok    node --check on slp_avalon.js' -ForegroundColor Green

        foreach ($suite in ($JsSuites.Keys | Sort-Object)) {
            $want = $JsSuites[$suite]
            if (Invoke-Suite -Runner 'node' -SuiteRel $suite -ArtefactPath $jsArtefact `
                    -ExpectPass $want -ExpectTotal $want -Label (Split-Path $suite -Leaf)) {
                $grand += $want
            } else { $allOk = $false }
        }
    }

    if ($php) {
        & php -l $clsArtefact | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "php -l failed on class.slp_avalon.php (exit $LASTEXITCODE)" }
        Write-Host '  ok    php -l on class.slp_avalon.php' -ForegroundColor Green

        foreach ($suite in ($PhpSuites.Keys | Sort-Object)) {
            $want = $PhpSuites[$suite]
            if (Invoke-Suite -Runner 'php' -SuiteRel $suite -ArtefactPath $clsArtefact `
                    -ExpectPass $want -ExpectTotal $want -Label (Split-Path $suite -Leaf)) {
                $grand += $want
            } else { $allOk = $false }
        }
    }

    # --- NEGATIVE CONTROL 1. suite-v019.js against the v0.0.18 JavaScript.
    #
    # v0.0.18 not v0.0.19, because v0.0.19 already has the gate and would
    # score 35/38 - a control that passes nearly everything is not a control.
    # 21 is not a slack figure: seventeen DISCRIMINATOR cases must fail and
    # twenty-one GUARD cases must hold. 38/38 means the suite is not testing
    # this release; under 21 means a guard broke, which is a regression
    # wearing the costume of a good control.
    if ($node) {
        Write-Host ''
        Write-Host 'Negative control 1 - suite-v019.js vs v0.0.18' -ForegroundColor Cyan
        $tmpJs = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v018-negctl.js'
        $got = Get-TagBlob -Ref "${CtlJsTag}:slp_avalon/assets/js/slp_avalon.js" `
                           -Dest $tmpJs -WantMd5 $CtlJsMd5 -What 'v0.0.18 slp_avalon.js'
        if (-not $got) { $allOk = $false } else {
            $ctlOut   = & node 'test/suite-v019.js' $tmpJs 2>&1
            $ctlLine  = ($ctlOut | Select-String 'PASS' | Select-Object -Last 1)
            $ctlDisc  = @($ctlOut | Select-String 'FAIL  \[DISCRIMINATOR\]').Count
            $ctlGuard = @($ctlOut | Select-String 'FAIL  \[GUARD\]').Count
            $m = [regex]::Match("$ctlLine", '(\d+)\s*/\s*(\d+)\s+PASS')
            if ($m.Success -and [int]$m.Groups[1].Value -eq 21 -and [int]$m.Groups[2].Value -eq 38 `
                    -and $ctlDisc -eq 17 -and $ctlGuard -eq 0) {
                Write-Host '  ok    suite-v019 vs v0.0.18                       21/38, 17 disc, 0 guard' -ForegroundColor Green
            } else {
                Write-Host ("  FAIL  control: {0}  (disc {1}, guard {2}); wanted 21/38, 17, 0" -f `
                    $ctlLine, $ctlDisc, $ctlGuard) -ForegroundColor Red
                $allOk = $false
            }
        }
        Remove-Item -LiteralPath $tmpJs -ErrorAction SilentlyContinue
    }

    # --- NEGATIVE CONTROL 2. suite-v020.php against the v0.0.19 class.
    #
    # 28 is not slack either. Twenty-two [v21] cases must fail and
    # twenty-eight [both] cases must hold.
    #
    # Those tags are no longer audited by eye. suite-v020's fourteen false
    # [v20] tags were found by hand, three revisions late.
    # test/Verify-Suite021.ps1 diffs the two runs case by case and reports
    # any [v21] that passes against the control or any [both] that fails,
    # and it refuses to print a pinnable triple until that audit is clean.
    # Run it before trusting the numbers below.
    #
    # Its first run found three: a cap fixture that tripped the floor rail
    # before it ever reached the cap, and an expected count of 1 for a
    # constant that appears twice. Neither was a tag error - both were the
    # suite being wrong - which is why the audit reports rather than
    # retags.
    if ($php) {
        Write-Host ''
        Write-Host 'Negative control 2 - suite-v021.php vs v0.0.20' -ForegroundColor Cyan
        $tmpCls = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v020-negctl.php'
        $got = Get-TagBlob -Ref "${PrevTag}:slp_avalon/inc/class.slp_avalon.php" `
                           -Dest $tmpCls -WantMd5 $PrevClassMd5 -What 'v0.0.20 class.slp_avalon.php'
        if (-not $got) { $allOk = $false } else {
            $ctlOut  = & php 'test/suite-v021.php' $tmpCls 2>&1
            $ctlLine = ($ctlOut | Select-String 'assertions PASS' | Select-Object -Last 1)
            $ctlDisc = @($ctlOut | Select-String 'FAIL \[v21\]').Count
            $ctlBoth = @($ctlOut | Select-String 'FAIL \[both\]').Count
            $m = [regex]::Match("$ctlLine", '(\d+)\s*/\s*(\d+)\s+assertions PASS')
            if ($m.Success -and [int]$m.Groups[1].Value -eq 28 -and [int]$m.Groups[2].Value -eq 50 `
                    -and $ctlDisc -eq 22 -and $ctlBoth -eq 0) {
                Write-Host '  ok    suite-v021 vs v0.0.20                       28/50, 22 [v21], 0 [both]' -ForegroundColor Green
            } else {
                Write-Host ("  FAIL  control: {0}  ([v21] {1}, [both] {2}); wanted 28/50, 22, 0" -f `
                    $ctlLine, $ctlDisc, $ctlBoth) -ForegroundColor Red
                $allOk = $false
            }
        }
        Remove-Item -LiteralPath $tmpCls -ErrorAction SilentlyContinue
    }
}
finally { Pop-Location }

Write-Host ''
Write-Host ("Grand total: {0} assertions" -f $grand) -ForegroundColor Cyan
if ($grand -ne 423 -and $node -and $php) {
    Write-Host ("  FAIL  expected 423, got {0}" -f $grand) -ForegroundColor Red
    $allOk = $false
}

Write-Host ''
Write-Host ('-' * 78)
if (-not $allOk) {
    Write-Host 'VERIFY FAILED. Do not commit.' -ForegroundColor Red
    if ($script:SuiteTotalMoved -gt 0) {
        Write-Host ("{0} suite(s) reported a different assertion count." -f $script:SuiteTotalMoved) -ForegroundColor Red
        Write-Host 'A suite total that moved is a FINDING, not a number to edit here.' -ForegroundColor Red
        Write-Host 'Every carried suite reads an artefact that this release changes.' -ForegroundColor Red
    }
    exit 1
}
Write-Host 'ALL CHECKS PASSED.' -ForegroundColor Green

if ($Mode -eq 'Verify') {
    Write-Host "Verify only. Re-run with .\$Self -Mode Commit when ready." -ForegroundColor Yellow
    exit 0
}

# ----------------------------------------------------------------- commit

if ($Mode -eq 'Commit') {
    Push-Location $PluginRepo
    try {
        Write-Host ''
        Write-Host 'Staging for commit' -ForegroundColor Cyan

        foreach ($f in $CommitFiles) {
            & git add -- $f
            Assert-Git "add $f"
            Write-Host ("  staged {0}" -f $f) -ForegroundColor Green
        }

        $staged = @(& git diff --cached --name-only)
        $extra  = @($staged | Where-Object { $CommitFiles -notcontains $_ })
        if ($extra.Count -gt 0) {
            Write-Host ''
            Write-Host 'Unexpected files staged:' -ForegroundColor Red
            $extra | ForEach-Object { Write-Host "    $_" -ForegroundColor Red }
            throw 'Refusing to commit an unexpected file set.'
        }

        & git commit --message $TagMessage
        Assert-Git 'commit'
        Write-Host ''
        Write-Host "Committed. Re-run with .\$Self -Mode Tag." -ForegroundColor Green
    }
    finally { Pop-Location }
    exit 0
}

# -------------------------------------------------------------------- tag

if ($Mode -eq 'Tag') {
    Push-Location $PluginRepo
    try {
        $existing = & git tag --list $Tag
        if ($existing) { throw "$Tag already exists." }

        & git tag -a $Tag -m $TagMessage
        Assert-Git 'tag'
        Write-Host ''
        Write-Host "Annotated tag $Tag created." -ForegroundColor Green
        Write-Host 'Push with: git push origin main --follow-tags' -ForegroundColor Cyan
    }
    finally { Pop-Location }

    Write-Host ''
    Write-Host ('=' * 78)
    Write-Host 'ACCEPTANCE CHECKLIST - Aura DEV' -ForegroundColor Cyan
    Write-Host ('=' * 78)
    Write-Host ''
    Write-Host 'PART A - the deploy. Complete before leaving the machine.' -ForegroundColor Yellow
    @(
        '1.  SFTP in BINARY mode. TWO files deploy - the JS did not change'
        '    this release, so do not re-upload it:'
        '      build\out21\class.slp_avalon.php  ->  slp_avalon/inc/'
        '      build\out21\slp_avalon.php        ->  slp_avalon/'
        '2.  SSH, then md5sum both. They must read'
        '      c8ef9f35c55f81c7f952dbf1e66e2aca   class.slp_avalon.php'
        '      a728341b388687afc43a6c9bd09c2afc   slp_avalon.php'
        '3.  php -l on both PHP files, on 8.4. Run it from /sites/aurapontoonstg'
        '    so the project wp-cli.yml trims the revslider noise.'
        '4.  Purge the WP Engine cache.'
        '5.  Cache-busted curl of the public slp_avalon.js piped to md5sum.'
        '    It must match the build, not the previous release.'
        '6.  Fresh clone at the tag, then:'
        "      .\$Self -Mode Verify -PluginRepo ."
        '    Delete the clone once it passes. Do not run releases from it.'
    ) | ForEach-Object { Write-Host "    $_" }

    Write-Host ''
    Write-Host 'PART B - browser and database. This release REMOVES a branch and adds logging.' -ForegroundColor Yellow
    @(
        '7.  BEFORE the first import, record the baseline on Aura DEV:'
        '      wp post list --post_type=store_page --post_status=any --format=count'
        '    It read 320 on 2026-09-07, against 308 rows and 12 orphans.'
        '8.  Run one import. Then read the summary:'
        '      wp option get avalon_geocode_last_run --format=json'
        '    orphans_trashed IS GONE. It could only ever report 0, which is'
        '    why it went. The field to read now is pages_destroyed: the'
        '    count of store_page posts SLP force-deleted this run. On an'
        '    import with no departing dealers it should be 0.'
        '9.  The twelve existing orphans are NOT touched and CANNOT be.'
        '    They have no stale row behind them and this pass is row-driven.'
        '    Disposition is adjudicated separately - 10 redirects, 1 removal,'
        '    1 open - and is a manual job, not an unattended one.'
        '9.  If reconcile_aborted is true, STOP. Either the feed halved or the'
        '    import died before recording. Read the aborted record in'
        '      wp option get avalon_geocode_overrides --format=json'
        '10. Confirm the record count is still 308 and DONNIE MARCH is'
        '    unmoved at 42.220530 / -83.466000.'
        '11. Type a five-digit ZIP into the locator with the Network tab open.'
        '    Count AutocompletionService.GetPredictions requests. Expect TWO,'
        '    on the fourth and fifth characters. Three means the constant did'
        '    not move; five means the gate is not running at all.'
        '12. Load /find-a-dealer/?place_address=48843 and touch nothing.'
        '    Expect ZERO Places requests. Then type one character into the'
        '    field and confirm the widget attaches.'
        '13. Click a dealer name in the results panel. It must resolve, not'
        '    404 - both environments returned 200 on 2026-09-05 and this'
        '    release must not change that.'
    ) | ForEach-Object { Write-Host "    $_" }

    Write-Host ''
    Write-Host 'ROLLBACK:' -ForegroundColor Yellow
    Write-Host '    THERE IS NO wp-config ROLLBACK FOR THIS RELEASE.'
    Write-Host '    AVALON_ORPHAN_CLEANUP and AVALON_ORPHAN_MAX_TRASH were removed;'
    Write-Host '    they configured a branch that could never execute, so there is'
    Write-Host '    nothing left to switch off. Setting them now does nothing while'
    Write-Host '    looking like it worked. Rolling back means redeploying v0.0.20:'
    Write-Host '      git show v0.0.20:slp_avalon/inc/class.slp_avalon.php'
    Write-Host '      git show v0.0.20:slp_avalon/slp_avalon.php'
    Write-Host '    Note that v0.0.21 is strictly LESS destructive than v0.0.20,'
    Write-Host '    so a rollback restores code that measurably does nothing.'
    Write-Host ''
    exit 0
}

}
finally {
    # s0.107. Runs on every path out of the body: all eight exit statements,
    # all ten throws, and the fall-through. Without this the process keeps an
    # open handle on $PluginRepo after the script ends, which blocks a rename
    # or a delete of a verification clone - measured 2026-09-06.
    #
    # Guarded because assigning an empty string to CurrentDirectory throws,
    # and a throw inside finally would mask whatever sent us here.
    if ($OrigCwd) { [System.Environment]::CurrentDirectory = $OrigCwd }
}
