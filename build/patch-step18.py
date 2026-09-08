#!/usr/bin/env python3
"""
patch-step18.py  -  Publish-Step17.ps1 -> Publish-Step18.ps1

Anchored, byte-exact, asserts the input md5 and every anchor's uniqueness
before writing. Same shape as patch-step17.py.

WHAT MOVES, AND WHY

v0.0.21 touches only class.slp_avalon.php and slp_avalon.php. slp_avalon.js
is untouched, so negative control 1 - suite-v019.js against v0.0.18 - is
carried across unchanged and every JS total holds.

suite-v020.php IS RETIRED, and that is the substantive change here. It does
not merely score lower against the v0.0.21 class, it FATALS:

    PHP Fatal error: Call to undefined function get_post_field()

Its stubs predate the function v0.0.21 calls, and its D2a/D2b cases assert
cleanup and max_trash, which this release removed. A suite whose subject no
longer exists cannot be repaired into usefulness - suite-v021 supersedes it.

Retiring a suite means proving its surviving coverage lives somewhere else
first. suite-v020 D6a asserted that the floor boundary is >=, not >, and
suite-v021 did not cover it. Cases D6-D8 were added there before this patch
was written, which is why the v021 total is 50 and not 47.

Measured 2026-09-07 on PHP 8.3.6, Linux, and reproduced on Windows:

    suite-v021 vs build/out21   50/50
    suite-v021 vs v0.0.20       28/50   22 discriminators, tag audit clean
    suite-v012/15/16/17/18      68/33/19/22/32, all unmoved by this release

Grand total 434 -> 423: PHP 235 - 61 + 50 = 224, JS 199 unchanged.

ONE STRUCTURAL FIX. Step 17 lists 'Publish-Step16.ps1' in $CommitFiles - each
script commits its predecessor, because a file cannot assert its own md5
without the assertion changing the file. That leaves the newest publish
script untracked until the next release writes one, which is the gap
decision 74 was raised for. Step 18 commits BOTH Step 17 and itself. Naming
itself in $CommitFiles is safe; naming itself in $ToolFiles would not be,
and it does not.

Usage:  python build/patch-step18.py
"""

import hashlib
import os
import sys

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(REPO, "Publish-Step17.ps1")
DST = os.path.join(REPO, "Publish-Step18.ps1")

IN_MD5 = "43db0e5941d85fcf6d82e220995be3c4"
IN_BYTES = 49882

EDITS = []


def edit(label, old, new):
    EDITS.append((label, old, new))


# ------------------------------------------------------------------ header
edit("self name",
     "if (-not $Self) { $Self = 'Publish-Step17.ps1' }",
     "if (-not $Self) { $Self = 'Publish-Step18.ps1' }")

edit("tags",
     "$Tag      = 'v0.0.20'\n"
     "$PrevTag  = 'v0.0.19'\n"
     "$CtlJsTag = 'v0.0.18'    # last build without the Autocomplete gate",
     "$Tag      = 'v0.0.21'\n"
     "$PrevTag  = 'v0.0.20'\n"
     "$CtlJsTag = 'v0.0.18'    # last build without the Autocomplete gate\n"
     "#\n"
     "# CtlJsTag does not move. v0.0.21 does not touch slp_avalon.js, so\n"
     "# negative control 1 is carried across unchanged and every JS total\n"
     "# holds. A JS control that moved with a PHP-only release would be\n"
     "# measuring nothing.")

# ---------------------------------------------------------------- expected
edit("expected class + loader",
     "$Expected = @{\n"
     "    'slp_avalon/inc/class.slp_avalon.php' = @{\n"
     "        Md5 = 'd00964ee60539ba470ae6d657280aba3'; Bytes = 101893; Crlf = 2204\n"
     "    }\n"
     "    'slp_avalon/assets/js/slp_avalon.js'  = @{\n"
     "        Md5 = '61921f1feca574f9f4ba4b36b362d4a0'; Bytes = 71758;  Crlf = 1685\n"
     "    }\n"
     "    'slp_avalon/slp_avalon.php'           = @{\n"
     "        Md5 = '8d798809eafce52ed0b95d165aed4345'; Bytes = 1808;   Crlf = 59\n"
     "    }\n"
     "}",
     "$Expected = @{\n"
     "    'slp_avalon/inc/class.slp_avalon.php' = @{\n"
     "        Md5 = 'c8ef9f35c55f81c7f952dbf1e66e2aca'; Bytes = 101111; Crlf = 2187\n"
     "    }\n"
     "    'slp_avalon/assets/js/slp_avalon.js'  = @{\n"
     "        Md5 = '61921f1feca574f9f4ba4b36b362d4a0'; Bytes = 71758;  Crlf = 1685\n"
     "    }\n"
     "    'slp_avalon/slp_avalon.php'           = @{\n"
     "        Md5 = 'a728341b388687afc43a6c9bd09c2afc'; Bytes = 1808;   Crlf = 59\n"
     "    }\n"
     "}")

edit("control md5s",
     "# Control targets. The class comes from v0.0.19, the JS from v0.0.18.\n"
     "$PrevClassMd5 = '4b1ee189381d0c111d0bc5c28c4b8822'\n"
     "$CtlJsMd5     = '8c93719e41af3232c18773a104e8dedd'",
     "# Control targets. The class comes from v0.0.20, the JS still from\n"
     "# v0.0.18 - the JS did not move this release.\n"
     "$PrevClassMd5 = 'd00964ee60539ba470ae6d657280aba3'\n"
     "$CtlJsMd5     = '8c93719e41af3232c18773a104e8dedd'")

# ------------------------------------------------------------ build output
edit("build output map",
     "# What build-v020.py writes. Same hashes, different location.\n"
     "$BuildOutput = @{\n"
     "    'build/out20/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']\n"
     "    'build/out20/slp_avalon.js'        = $Expected['slp_avalon/assets/js/slp_avalon.js']\n"
     "    'build/out20/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']\n"
     "}",
     "# What build-v021.py writes. Same hashes, different location. Two files,\n"
     "# not three: this release does not touch the JS, so build-v021 does not\n"
     "# emit it and out21 must not be asserted to contain it.\n"
     "$BuildOutput = @{\n"
     "    'build/out21/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']\n"
     "    'build/out21/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']\n"
     "}")

edit("stage map",
     "$StageMap = @{\n"
     "    'build/out20/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'\n"
     "    'build/out20/slp_avalon.js'        = 'slp_avalon/assets/js/slp_avalon.js'\n"
     "    'build/out20/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'\n"
     "}",
     "$StageMap = @{\n"
     "    'build/out21/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'\n"
     "    'build/out21/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'\n"
     "}")

# ------------------------------------------------------------- tool files
edit("tool files",
     "$ToolFiles = @{\n"
     "    'build/build-v020.py' = @{ Md5 = 'ddd1ba655b61ec8fce219305ae106a32'; Bytes = 25729 }\n"
     "    'test/suite-v020.php' = @{ Md5 = '6186c6182e774f01432467567358f5c3'; Bytes = 26390 }\n"
     "    'test/suite-v019.js'  = @{ Md5 = 'e4f1cc3021c6cda1275727e5aa2fb869'; Bytes = 18721 }\n"
     "    'test/suite-v017.php' = @{ Md5 = '0882d4e4e7bd275b521e3dfd68b04db3'; Bytes = 15987 }\n"
     "    'test/suite-v018.php' = @{ Md5 = 'fb03fd6d95fb6997c35e6419634559a6'; Bytes = 20265 }\n"
     "}",
     "# BUILD_V021_MD5 / SUITE_V021_MD5 / VERIFY_MD5 / ORPHAN_MD5 were measured on the delivered\n"
     "# files, not guessed. orphan-report.php landed on the server at 6,112\n"
     "# bytes, matching the source exactly, which is what confirms the\n"
     "# delivery path preserves bytes and makes these safe to pin at all.\n"
     ""
     "$ToolFiles = @{\n"
     "    'build/build-v020.py'       = @{ Md5 = 'ddd1ba655b61ec8fce219305ae106a32'; Bytes = 25729 }\n"
     "    'test/suite-v020.php'       = @{ Md5 = '6186c6182e774f01432467567358f5c3'; Bytes = 26390 }\n"
     "    'test/suite-v019.js'        = @{ Md5 = 'e4f1cc3021c6cda1275727e5aa2fb869'; Bytes = 18721 }\n"
     "    'test/suite-v017.php'       = @{ Md5 = '0882d4e4e7bd275b521e3dfd68b04db3'; Bytes = 15987 }\n"
     "    'test/suite-v018.php'       = @{ Md5 = 'fb03fd6d95fb6997c35e6419634559a6'; Bytes = 20265 }\n"
     "    'build/build-v021.py'       = @{ Md5 = 'e7a307b55c1897202186efd64d94c4ee'; Bytes = 22891 }\n"
     "    'test/suite-v021.php'       = @{ Md5 = '90282a3a36f272da3ac465a3d67c5bd1'; Bytes = 22993 }\n"
     "    'test/Verify-Suite021.ps1'  = @{ Md5 = 'e11ef74471e519ffde6ecc2e2daeab98';     Bytes = 7993 }\n"
     "    'test/orphan-report.php'    = @{ Md5 = '189041fd5bc51882d21203f139f5b1c4';     Bytes = 10766 }\n"
     "}")

# ---------------------------------------------------------------- pin rows
edit("pin rows",
     "    @{ Path = 'slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php' ; Md5 = 'd00964ee60539ba470ae6d657280aba3' }\n"
     "    @{ Path = 'slp-plugins\\slp_avalon\\slp_avalon.php'           ; Md5 = '8d798809eafce52ed0b95d165aed4345' }",
     "    @{ Path = 'slp-plugins\\slp_avalon\\inc\\class.slp_avalon.php' ; Md5 = 'c8ef9f35c55f81c7f952dbf1e66e2aca' }\n"
     "    @{ Path = 'slp-plugins\\slp_avalon\\slp_avalon.php'           ; Md5 = 'a728341b388687afc43a6c9bd09c2afc' }")

# -------------------------------------------------------------- test files
edit("test files",
     "    'test/suite-v020.php', 'test/release-pins.csv',\n"
     "    'build/build-v015.py', 'build/build-v016.py', 'build/build-v017.py',\n"
     "    'build/build-v018.py', 'build/build-v019.py', 'build/build-v020.py',\n"
     "    'Verify-v016.ps1', 'Publish-Step11.ps1', 'Publish-Step12.ps1',\n"
     "    'Publish-Step13.ps1', 'Publish-Step14.ps1', 'Publish-Step15.ps1'\n"
     ")",
     "    'test/suite-v020.php', 'test/suite-v021.php',\n"
     "    'test/Verify-Suite021.ps1', 'test/orphan-report.php',\n"
     "    'test/release-pins.csv',\n"
     "    'build/build-v015.py', 'build/build-v016.py', 'build/build-v017.py',\n"
     "    'build/build-v018.py', 'build/build-v019.py', 'build/build-v020.py',\n"
     "    'build/build-v021.py',\n"
     "    'Verify-v016.ps1', 'Publish-Step11.ps1', 'Publish-Step12.ps1',\n"
     "    'Publish-Step13.ps1', 'Publish-Step14.ps1', 'Publish-Step15.ps1',\n"
     "    'Publish-Step16.ps1', 'Publish-Step17.ps1'\n"
     ")")

# ------------------------------------------------------------- php suites
edit("php suites",
     "# Every one of these reads the class, and the class MOVED. These are the totals\n"
     "# Publish-Step15 recorded against the v0.0.19 class; a difference is a finding\n"
     "# to take back to the handoff, not a number to edit here.\n"
     "$PhpSuites = @{\n"
     "    'test/suite-v012.php' = 68; 'test/suite-v015.php' = 33\n"
     "    'test/suite-v016.php' = 19; 'test/suite-v017.php' = 22\n"
     "    'test/suite-v018.php' = 32; 'test/suite-v020.php' = 61\n"
     "}",
     "# Every one of these reads the class, and the class MOVED. A difference is\n"
     "# a finding to take back to the handoff, not a number to edit here.\n"
     "#\n"
     "# suite-v020.php IS ABSENT AND THAT IS DELIBERATE. Measured against the\n"
     "# v0.0.21 class it does not score lower, it fatals - Call to undefined\n"
     "# function get_post_field() - because its stubs predate the function this\n"
     "# release calls, and its D2a/D2b cases assert cleanup and max_trash,\n"
     "# which are gone. The file stays in the repository and keeps its pin;\n"
     "# only its expectation is retired. Its one piece of surviving coverage,\n"
     "# D6a's >= floor boundary, moved to suite-v021 D6-D8 BEFORE it was\n"
     "# dropped here.\n"
     "#\n"
     "# The other five were measured unmoved against both classes on\n"
     "# 2026-09-07, so this release did not disturb them.\n"
     "$PhpSuites = @{\n"
     "    'test/suite-v012.php' = 68; 'test/suite-v015.php' = 33\n"
     "    'test/suite-v016.php' = 19; 'test/suite-v017.php' = 22\n"
     "    'test/suite-v018.php' = 32; 'test/suite-v021.php' = 50\n"
     "}")

# ------------------------------------------------------------ commit files
edit("commit files",
     "$CommitFiles = @(\n"
     "    'slp_avalon/inc/class.slp_avalon.php',\n"
     "    'slp_avalon/assets/js/slp_avalon.js',\n"
     "    'slp_avalon/slp_avalon.php',\n"
     "    'build/build-v020.py',\n"
     "    'test/suite-v020.php',\n"
     "    'test/suite-v019.js',\n"
     "    'test/suite-v017.php',\n"
     "    'test/suite-v018.php',\n"
     "    'test/release-pins.csv',\n"
     "    'Publish-Step16.ps1'\n"
     ")",
     "# slp_avalon.js is NOT here. It did not change this release, and staging\n"
     "# an unchanged file invites a diff nobody can explain later.\n"
     "#\n"
     "# Both Publish-Step17.ps1 and this script are listed. Step 17 named only\n"
     "# its predecessor, which leaves the newest publish script untracked until\n"
     "# the next release writes one - the gap decision 74 was raised for. A\n"
     "# script naming itself in $CommitFiles is safe; naming itself in\n"
     "# $ToolFiles would not be, because a file cannot assert its own md5\n"
     "# without the assertion changing the file. It does not appear there.\n"
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
     ")")

# --------------------------------------------------------- negative ctl 2
edit("negative control 2",
     "        Write-Host 'Negative control 2 - suite-v020.php vs v0.0.19' -ForegroundColor Cyan\n"
     "        $tmpCls = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v019-negctl.php'\n"
     "        $got = Get-TagBlob -Ref \"${PrevTag}:slp_avalon/inc/class.slp_avalon.php\" `\n"
     "                           -Dest $tmpCls -WantMd5 $PrevClassMd5 -What 'v0.0.19 class.slp_avalon.php'\n"
     "        if (-not $got) { $allOk = $false } else {\n"
     "            $ctlOut  = & php 'test/suite-v020.php' $tmpCls 2>&1\n"
     "            $ctlLine = ($ctlOut | Select-String 'assertions PASS' | Select-Object -Last 1)\n"
     "            $ctlDisc = @($ctlOut | Select-String 'FAIL  \\[v20\\]').Count\n"
     "            $ctlBoth = @($ctlOut | Select-String 'FAIL  \\[both\\]').Count\n"
     "            $m = [regex]::Match(\"$ctlLine\", '(\\d+)\\s*/\\s*(\\d+)\\s+assertions PASS')\n"
     "            if ($m.Success -and [int]$m.Groups[1].Value -eq 30 -and [int]$m.Groups[2].Value -eq 61 `\n"
     "                    -and $ctlDisc -eq 31 -and $ctlBoth -eq 0) {\n"
     "                Write-Host '  ok    suite-v020 vs v0.0.19                       30/61, 31 [v20], 0 [both]' -ForegroundColor Green\n"
     "            } else {\n"
     "                Write-Host (\"  FAIL  control: {0}  ([v20] {1}, [both] {2}); wanted 30/61, 31, 0\" -f `\n"
     "                    $ctlLine, $ctlDisc, $ctlBoth) -ForegroundColor Red\n"
     "                $allOk = $false\n"
     "            }\n"
     "        }",
     "        Write-Host 'Negative control 2 - suite-v021.php vs v0.0.20' -ForegroundColor Cyan\n"
     "        $tmpCls = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v020-negctl.php'\n"
     "        $got = Get-TagBlob -Ref \"${PrevTag}:slp_avalon/inc/class.slp_avalon.php\" `\n"
     "                           -Dest $tmpCls -WantMd5 $PrevClassMd5 -What 'v0.0.20 class.slp_avalon.php'\n"
     "        if (-not $got) { $allOk = $false } else {\n"
     "            $ctlOut  = & php 'test/suite-v021.php' $tmpCls 2>&1\n"
     "            $ctlLine = ($ctlOut | Select-String 'assertions PASS' | Select-Object -Last 1)\n"
     "            $ctlDisc = @($ctlOut | Select-String 'FAIL \\[v21\\]').Count\n"
     "            $ctlBoth = @($ctlOut | Select-String 'FAIL \\[both\\]').Count\n"
     "            $m = [regex]::Match(\"$ctlLine\", '(\\d+)\\s*/\\s*(\\d+)\\s+assertions PASS')\n"
     "            if ($m.Success -and [int]$m.Groups[1].Value -eq 28 -and [int]$m.Groups[2].Value -eq 50 `\n"
     "                    -and $ctlDisc -eq 22 -and $ctlBoth -eq 0) {\n"
     "                Write-Host '  ok    suite-v021 vs v0.0.20                       28/50, 22 [v21], 0 [both]' -ForegroundColor Green\n"
     "            } else {\n"
     "                Write-Host (\"  FAIL  control: {0}  ([v21] {1}, [both] {2}); wanted 28/50, 22, 0\" -f `\n"
     "                    $ctlLine, $ctlDisc, $ctlBoth) -ForegroundColor Red\n"
     "                $allOk = $false\n"
     "            }\n"
     "        }")

edit("negative control 2 preamble",
     "    # 30 is not slack either. Thirty-one [v20] cases must fail - v0.0.19 has\n"
     "    # no avalon_orphan_config() at all - and thirty [both] cases must hold.\n"
     "    # Fourteen cases were tagged [v20] and passed against this control; they\n"
     "    # were retagged [both] rather than left claiming a discrimination they do\n"
     "    # not perform, so 30 is a measured number and not a target.",
     "    # 28 is not slack either. Twenty-two [v21] cases must fail and\n"
     "    # twenty-eight [both] cases must hold.\n"
     "    #\n"
     "    # Those tags are no longer audited by eye. suite-v020's fourteen false\n"
     "    # [v20] tags were found by hand, three revisions late.\n"
     "    # test/Verify-Suite021.ps1 diffs the two runs case by case and reports\n"
     "    # any [v21] that passes against the control or any [both] that fails,\n"
     "    # and it refuses to print a pinnable triple until that audit is clean.\n"
     "    # Run it before trusting the numbers below.\n"
     "    #\n"
     "    # Its first run found three: a cap fixture that tripped the floor rail\n"
     "    # before it ever reached the cap, and an expected count of 1 for a\n"
     "    # constant that appears twice. Neither was a tag error - both were the\n"
     "    # suite being wrong - which is why the audit reports rather than\n"
     "    # retags.")

# ----------------------------------------------------------- grand total
edit("grand total",
     "if ($grand -ne 434 -and $node -and $php) {\n"
     "    Write-Host (\"  FAIL  expected 434, got {0}\" -f $grand) -ForegroundColor Red",
     "if ($grand -ne 423 -and $node -and $php) {\n"
     "    Write-Host (\"  FAIL  expected 423, got {0}\" -f $grand) -ForegroundColor Red")

# ------------------------------------------------------------ tag message
edit("tag message head",
     "v0.0.20: stop the reconcile loop orphaning store_page posts, and raise the\n"
     "Autocomplete threshold to four\n"
     "\n"
     "ISSUE 31 - class.slp_avalon.php\n"
     "\n"
     "csv_processing_complete_func() removes every location whose hash is absent\n"
     "from avalon_updated_slp_locations. currentLocation->delete() drops the\n"
     "wp_store_locator row and leaves the linked store_page post standing, so every\n"
     "delete-and-recreate cycle left a permalink behind.",
     "v0.0.21: remove the orphan-trash apparatus, which could never execute\n"
     "\n"
     "ISSUE 31 - class.slp_avalon.php\n"
     "\n"
     "v0.0.20 added a wp_trash_post() branch to pass 2 of the reconcile, on the\n"
     "belief - stated in comments at lines 691 and 1269 - that\n"
     "currentLocation->delete() drops the wp_store_locator row and leaves the\n"
     "linked store_page standing. That belief was never measured and it is\n"
     "false.\n"
     "\n"
     "Read at store-locator-le 2311.17.01 on 2026-09-07:\n"
     "\n"
     "    SLPlus_Location.php:771  delete($id) calls get_location($id) first\n"
     "                       :794  then delete_store_pages()\n"
     "                       :843  wp_delete_post($postid, true) behind a\n"
     "                             pre_delete_post filter\n"
     "                       :848  which vetoes any post that is not a\n"
     "                             store_page\n"
     "    SLPlus.php:81            const locationPostType = 'store_page'\n"
     "\n"
     "So SLP force-deletes the linked post if and only if it is a store_page.\n"
     "The guard at line 1379 continued unless the post WAS a store_page, and\n"
     "line 1389 therefore needed a store_page that had survived a delete which\n"
     "removes exactly store_pages. Those are exact complements: the branch was\n"
     "unreachable for every input, not merely untested. Three unattended cron\n"
     "runs logged orphan_skipped with orphans_trashed 0 every time.\n"
     "\n"
     "Removed: the trash branch, its two failure branches, the disposal cap,\n"
     "AVALON_ORPHAN_CLEANUP, AVALON_ORPHAN_MAX_TRASH, and the orphans_trashed\n"
     "summary field, which could only ever report 0.\n"
     "\n"
     "Kept: floor_pct and Rail 1, which are independent of all of it and now\n"
     "matter more - a runaway pass does not orphan 308 pages, it destroys them.\n"
     "\n"
     "Added: a log line naming each store_page BEFORE SLP destroys it, with its\n"
     "slug, plus a pages_destroyed counter. The disposal is a force delete with\n"
     "no trash and no undo, and a store_page can carry Elementor postmeta the\n"
     "feed cannot rebuild. v0.0.20 produced no record of what it destroyed.\n"
     "\n"
     "THE ORPHANS ARE A SEPARATE DEFECT. Measured on Aura DEV 2026-09-07: all\n"
     "12 orphaned pages predate the 2026-08-22 rebuild, all 308 pages created\n"
     "since are linked, and post_modified equals post_date on 11 of the 12.\n"
     "They were orphaned at or near creation, by a path this reconcile cannot\n"
     "see and never could. Disposition is adjudicated but not yet applied.")

edit("tag message totals",
     "TOTALS      JS 199    PHP 235    434\n"
     "CONTROLS    suite-v019 vs v0.0.18: 21/38, 17 DISCRIMINATOR, 0 GUARD\n"
     "            suite-v020 vs v0.0.19: 30/61, 31 [v20], 0 [both]",
     "TOTALS      JS 199    PHP 224    423\n"
     "CONTROLS    suite-v019 vs v0.0.18: 21/38, 17 DISCRIMINATOR, 0 GUARD\n"
     "            suite-v021 vs v0.0.20: 28/50, 22 [v21], 0 [both]\n"
     "\n"
     "suite-v020.php is retired from the expectation table. Against the\n"
     "v0.0.21 class it fatals on an undefined get_post_field(), and its\n"
     "cleanup and max_trash cases assert a feature this release removed. Its\n"
     "surviving coverage - the >= floor boundary - moved to suite-v021 D6-D8\n"
     "before it was dropped. The file and its pin remain.")


# --------------------------------------------- sites the first cut missed
# These are live output and live comments, not narrative. The historical
# mentions of 434 at the head of this file and in the fresh-clone section
# describe what happened AT v0.0.20 and must not be rewritten.
edit("doc totals",
     "        JS subtotal 199        PHP subtotal    235        TOTAL 434",
     "        JS subtotal 199        PHP subtotal    224        TOTAL 423")

edit("mode help",
     "    Stage   copy build/out20 into place, write the pin file, re-verify",
     "    Stage   copy build/out21 into place, write the pin file, re-verify")

edit("build missing message",
     "        Write-Host 'build/out20 is missing or does not match. Re-run:' -ForegroundColor Red\n"
     "        Write-Host '    python build\\build-v020.py' -ForegroundColor Red",
     "        Write-Host 'build/out21 is missing or does not match. Re-run:' -ForegroundColor Red\n"
     "        Write-Host '    python build\\build-v021.py' -ForegroundColor Red")

edit("gitignore comment",
     "# build/out*/ went into .gitignore with v0.0.15 and covers build/out20. If it",
     "# build/out*/ went into .gitignore with v0.0.15 and covers build/out21. If it")

edit("deploy checklist",
     "        '1.  SFTP in BINARY mode. THREE files deploy:'\n"
     "        '      build\\out20\\class.slp_avalon.php  ->  slp_avalon/inc/'\n"
     "        '      build\\out20\\slp_avalon.js         ->  slp_avalon/assets/js/'\n"
     "        '      build\\out20\\slp_avalon.php        ->  slp_avalon/'\n"
     "        '2.  SSH, then md5sum each of the three. They must read'\n"
     "        '      d00964ee60539ba470ae6d657280aba3   class.slp_avalon.php'\n"
     "        '      61921f1feca574f9f4ba4b36b362d4a0   slp_avalon.js'\n"
     "        '      8d798809eafce52ed0b95d165aed4345   slp_avalon.php'",
     "        '1.  SFTP in BINARY mode. TWO files deploy - the JS did not change'\n"
     "        '    this release, so do not re-upload it:'\n"
     "        '      build\\out21\\class.slp_avalon.php  ->  slp_avalon/inc/'\n"
     "        '      build\\out21\\slp_avalon.php        ->  slp_avalon/'\n"
     "        '2.  SSH, then md5sum both. They must read'\n"
     "        '      c8ef9f35c55f81c7f952dbf1e66e2aca   class.slp_avalon.php'\n"
     "        '      a728341b388687afc43a6c9bd09c2afc   slp_avalon.php'")


# ------------------------------------------- checklist, r2. Step 17's Part B
# and rollback text came across verbatim and describes a release this one
# deletes: orphans_trashed no longer exists, and AVALON_ORPHAN_CLEANUP is
# gone, so the offered rollback would do nothing while appearing to work.
# The tag message is load-bearing documentation; it cannot describe a
# feature that was removed in the same commit.
edit("part B header",
     "    Write-Host 'PART B - browser and database. Issue 31 is a DESTRUCTIVE change.' -ForegroundColor Yellow",
     "    Write-Host 'PART B - browser and database. This release REMOVES a branch and adds logging.' -ForegroundColor Yellow")

edit("part B body",
     "        '    It read 320 on 2026-09-05, with 12 orphans.'\n"
     "        '8.  Run one import. Then read the summary:'\n"
     "        '      wp option get avalon_geocode_last_run --format=json'\n"
     "        '    rows_removed, orphans_trashed and reconcile_aborted are new.'\n"
     "        '    orphans_trashed should be 0 on a clean run - the twelve existing'\n"
     "        '    orphans have no stale ROW behind them, so this release stops new'\n"
     "        '    ones rather than clearing the backlog. Clearing it is a separate,'\n"
     "        '    manual step and must not be folded into an unattended import.'",
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
     "        '    1 open - and is a manual job, not an unattended one.'")

edit("rollback",
     "    Write-Host 'ROLLBACK, without a deploy:' -ForegroundColor Yellow\n"
     "    Write-Host \"    define('AVALON_ORPHAN_CLEANUP', false);   // in wp-config.php\"\n"
     "    Write-Host '    Rows are still reconciled; no post is trashed. That is exactly'\n"
     "    Write-Host '    v0.0.19 behaviour, and suite-v020 D14a-d assert it.'",
     "    Write-Host 'ROLLBACK:' -ForegroundColor Yellow\n"
     "    Write-Host '    THERE IS NO wp-config ROLLBACK FOR THIS RELEASE.'\n"
     "    Write-Host '    AVALON_ORPHAN_CLEANUP and AVALON_ORPHAN_MAX_TRASH were removed;'\n"
     "    Write-Host '    they configured a branch that could never execute, so there is'\n"
     "    Write-Host '    nothing left to switch off. Setting them now does nothing while'\n"
     "    Write-Host '    looking like it worked. Rolling back means redeploying v0.0.20:'\n"
     "    Write-Host '      git show v0.0.20:slp_avalon/inc/class.slp_avalon.php'\n"
     "    Write-Host '      git show v0.0.20:slp_avalon/slp_avalon.php'\n"
     "    Write-Host '    Note that v0.0.21 is strictly LESS destructive than v0.0.20,'\n"
     "    Write-Host '    so a rollback restores code that measurably does nothing.'")


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
    print("input   Publish-Step17.ps1  %s  %d bytes" % (got, len(data)))
    print()

    text = data.decode("utf-8")
    for label, old, new in EDITS:
        c = text.count(old)
        if c != 1:
            print("FAIL  %s: %d occurrences, want 1" % (label, c))
            sys.exit(1)
        text = text.replace(old, new, 1)
        print("  %-26s %6d -> %-6d chars" % (label, len(old), len(new)))

    out = text.encode("utf-8")

    print()
    print("self-checks")
    fails = []

    def ck(cond, msg):
        print("  %s  %s" % ("ok  " if cond else "FAIL", msg))
        if not cond:
            fails.append(msg)

    ck(out.count(b"out20") == 0, "no out20 references remain")
    ck(out.count(b"build/out21/") == 4, "out21 named in BuildOutput and StageMap")
    ck(out.count(b"'v0.0.21'") == 1, "Tag is v0.0.21")
    ck(out.count(b"'v0.0.20'") == 1, "PrevTag is v0.0.20")
    ck(out.count(b"d00964ee60539ba470ae6d657280aba3") == 1,
       "v0.0.20 class md5 appears once, as the control")
    # Three sites each: $Expected, $PinRows, and the Part A deploy checklist
    # the operator md5sums against on the server.
    ck(out.count(b"c8ef9f35c55f81c7f952dbf1e66e2aca") == 3,
       "v0.0.21 class md5 in Expected, PinRows and the deploy checklist")
    ck(out.count(b"a728341b388687afc43a6c9bd09c2afc") == 3,
       "v0.0.21 loader md5 in Expected, PinRows and the deploy checklist")
    ck(out.count(b"61921f1feca574f9f4ba4b36b362d4a0") == 2,
       "JS md5 kept in Expected and PinRows, dropped from the deploy list")
    ck(b"'test/suite-v020.php' = 61" not in out,
       "suite-v020 retired from PhpSuites")
    ck(out.count(b"'test/suite-v021.php' = 50") == 1,
       "suite-v021 expected at 50")
    ck(out.count(b"423") >= 2, "grand total moved to 423")
    # 434 survives at three sites that narrate what happened AT v0.0.20 -
    # the wrongname clone and the slp-v020-check incident. Those are history
    # and must not be rewritten. Only the live assertion is checked.
    ck(out.count(b"$grand -ne 423") == 1, "grand-total assertion is 423")
    ck(out.count(b"$grand -ne 434") == 0, "no live 434 assertion remains")
    ck(out.count(b"TOTAL 423") == 1, "doc subtotal line updated")
    ck(out.count(b"'Publish-Step18.ps1'") == 2,
       "Step18 named in Self fallback and CommitFiles")
    ck(out.count(b"\r\n") == 0, "LF endings preserved")
    ck(out.count(b"AVALON_ORPHAN_CLEANUP', false") == 0,
       "no rollback offered via a constant this release deleted")
    ck(out.count(b"orphans_trashed and reconcile_aborted are new") == 0,
       "Part B no longer tells the operator to read a removed field")
    ck(out.count(b"pages_destroyed") >= 2,
       "Part B names the field that replaced it")
    ck(out.count(b"@'") == data.count(b"@'") and
       out.count(b"'@") == data.count(b"'@"),
       "here-string delimiters balanced")

    if fails:
        print()
        print("FAIL  %d self-check(s); nothing written" % len(fails))
        sys.exit(1)

    open(DST, "wb").write(out)
    print()
    print("output  Publish-Step18.ps1  %s  %d bytes  (was %d, %+d)"
          % (hashlib.md5(out).hexdigest(), len(out), len(data),
             len(out) - len(data)))
    print()
    print("NEXT: .\\Publish-Step18.ps1 -Mode Stage")


if __name__ == "__main__":
    main()
