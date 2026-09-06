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

    Verified totals, measured not estimated, 2026-09-06:

        suite-v008   24        suite-v012.php   68   (class MOVED, re-run)
        suite-v009   13        suite-v015.php   33   (class MOVED, re-run)
        suite-v010   40        suite-v016.php   19   (class MOVED, re-run)
        suite-v011   15        suite-v017.php   22   (class MOVED, re-run)
        suite-v013   35        suite-v018.php   32   (class MOVED, re-run)
        suite-v014   34        suite-v020.php   61   (new)
        suite-v019   38   (was 35, three cases added)

        JS subtotal 199        PHP subtotal    235        TOTAL 434

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
    Stage   copy build/out20 into place, write the pin file, re-verify
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

# s0.75. Read once from the invocation so no message can name a stale script.
$Self = Split-Path -Leaf $MyInvocation.MyCommand.Path
if (-not $Self) { $Self = 'Publish-Step16.ps1' }

$Tag      = 'v0.0.20'
$PrevTag  = 'v0.0.19'
$CtlJsTag = 'v0.0.18'    # last build without the Autocomplete gate

# Expected working-tree state AFTER staging. Update these together with
# build-v020.py, never independently.
$Expected = @{
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = 'd00964ee60539ba470ae6d657280aba3'; Bytes = 101893; Crlf = 2204
    }
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = '61921f1feca574f9f4ba4b36b362d4a0'; Bytes = 71758;  Crlf = 1685
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = '8d798809eafce52ed0b95d165aed4345'; Bytes = 1808;   Crlf = 59
    }
}

# Control targets. The class comes from v0.0.19, the JS from v0.0.18.
$PrevClassMd5 = '4b1ee189381d0c111d0bc5c28c4b8822'
$CtlJsMd5     = '8c93719e41af3232c18773a104e8dedd'

# What build-v020.py writes. Same hashes, different location.
$BuildOutput = @{
    'build/out20/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']
    'build/out20/slp_avalon.js'        = $Expected['slp_avalon/assets/js/slp_avalon.js']
    'build/out20/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']
}

$StageMap = @{
    'build/out20/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'
    'build/out20/slp_avalon.js'        = 'slp_avalon/assets/js/slp_avalon.js'
    'build/out20/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'
}

# The files delivered this session. A stale or truncated copy of any of them is
# caught here rather than as an unexplained score forty lines down. build-v020
# was itself run twice this session because the first download did not land on
# top of the old file, and the only tell was an md5 in the build output. This
# script is deliberately NOT in the table: a file cannot assert its own md5
# without the assertion changing the file. Its integrity is covered by the
# fresh-clone check, item 11 of the checklist.
$ToolFiles = @{
    'build/build-v020.py' = 'ddd1ba655b61ec8fce219305ae106a32'
    'test/suite-v020.php' = '6186c6182e774f01432467567358f5c3'
    'test/suite-v019.js'  = 'e4f1cc3021c6cda1275727e5aa2fb869'
    'test/suite-v017.php' = '0882d4e4e7bd275b521e3dfd68b04db3'
    'test/suite-v018.php' = 'fb03fd6d95fb6997c35e6419634559a6'
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
    @{ Path = 'slp-plugins\slp_avalon\inc\class.slp_avalon.php' ; Md5 = 'd00964ee60539ba470ae6d657280aba3' }
    @{ Path = 'slp-plugins\slp_avalon\slp_avalon.php'           ; Md5 = '8d798809eafce52ed0b95d165aed4345' }
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
    'test/suite-v020.php', 'test/release-pins.csv',
    'build/build-v015.py', 'build/build-v016.py', 'build/build-v017.py',
    'build/build-v018.py', 'build/build-v019.py', 'build/build-v020.py',
    'Verify-v016.ps1', 'Publish-Step11.ps1', 'Publish-Step12.ps1',
    'Publish-Step13.ps1', 'Publish-Step14.ps1', 'Publish-Step15.ps1'
)

# suite-v019 gains three cases: 35 -> 38.
$JsSuites = @{
    'test/suite-v008.js' = 24; 'test/suite-v009.js' = 13
    'test/suite-v010.js' = 40; 'test/suite-v011.js' = 15
    'test/suite-v013.js' = 35; 'test/suite-v014.js' = 34
    'test/suite-v019.js' = 38
}

# Every one of these reads the class, and the class MOVED. These are the totals
# Publish-Step15 recorded against the v0.0.19 class; a difference is a finding
# to take back to the handoff, not a number to edit here.
$PhpSuites = @{
    'test/suite-v012.php' = 68; 'test/suite-v015.php' = 33
    'test/suite-v016.php' = 19; 'test/suite-v017.php' = 22
    'test/suite-v018.php' = 32; 'test/suite-v020.php' = 61
}

$CommitFiles = @(
    'slp_avalon/inc/class.slp_avalon.php',
    'slp_avalon/assets/js/slp_avalon.js',
    'slp_avalon/slp_avalon.php',
    'build/build-v020.py',
    'test/suite-v020.php',
    'test/suite-v019.js',
    'test/suite-v017.php',
    'test/suite-v018.php',
    'test/release-pins.csv',
    'Publish-Step16.ps1'
)

$TagMessage = @'
v0.0.20: stop the reconcile loop orphaning store_page posts, and raise the
Autocomplete threshold to four

ISSUE 31 - class.slp_avalon.php

csv_processing_complete_func() removes every location whose hash is absent
from avalon_updated_slp_locations. currentLocation->delete() drops the
wp_store_locator row and leaves the linked store_page post standing, so every
delete-and-recreate cycle left a permalink behind.

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

TOTALS      JS 199    PHP 235    434
CONTROLS    suite-v019 vs v0.0.18: 21/38, 17 DISCRIMINATOR, 0 GUARD
            suite-v020 vs v0.0.19: 30/61, 31 [v20], 0 [both]
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
    param([string]$Root, [string]$RelPath, [string]$Want)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) {
        Write-Host ("  FAIL  MISSING: {0}" -f $RelPath) -ForegroundColor Red
        return $false
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
    if ($tot -ne $ExpectTotal) { $problems += "suite has $tot assertions, expected $ExpectTotal" }
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
Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]   ($Self)" -ForegroundColor Cyan
Write-Host ('-' * 78)

if (-not (Test-Path -LiteralPath $PluginRepo)) { throw "Plugin repo not found: $PluginRepo" }
$PluginRepo = (Resolve-Path -LiteralPath $PluginRepo).Path
[System.Environment]::CurrentDirectory = $PluginRepo   # PS location != .NET CWD

Write-Host ''
Write-Host 'Session artefacts' -ForegroundColor Cyan
$toolsOk = $true
foreach ($rel in ($ToolFiles.Keys | Sort-Object)) {
    if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel -Want $ToolFiles[$rel])) { $toolsOk = $false }
}
if (-not $toolsOk) {
    Write-Host ''
    Write-Host 'One of this session''s five files is missing or stale.' -ForegroundColor Red
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
        Write-Host 'build/out20 is missing or does not match. Re-run:' -ForegroundColor Red
        Write-Host '    python build\build-v020.py' -ForegroundColor Red
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

# build/out*/ went into .gitignore with v0.0.15 and covers build/out20. If it
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
    # 30 is not slack either. Thirty-one [v20] cases must fail - v0.0.19 has
    # no avalon_orphan_config() at all - and thirty [both] cases must hold.
    # Fourteen cases were tagged [v20] and passed against this control; they
    # were retagged [both] rather than left claiming a discrimination they do
    # not perform, so 30 is a measured number and not a target.
    if ($php) {
        Write-Host ''
        Write-Host 'Negative control 2 - suite-v020.php vs v0.0.19' -ForegroundColor Cyan
        $tmpCls = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v019-negctl.php'
        $got = Get-TagBlob -Ref "${PrevTag}:slp_avalon/inc/class.slp_avalon.php" `
                           -Dest $tmpCls -WantMd5 $PrevClassMd5 -What 'v0.0.19 class.slp_avalon.php'
        if (-not $got) { $allOk = $false } else {
            $ctlOut  = & php 'test/suite-v020.php' $tmpCls 2>&1
            $ctlLine = ($ctlOut | Select-String 'assertions PASS' | Select-Object -Last 1)
            $ctlDisc = @($ctlOut | Select-String 'FAIL  \[v20\]').Count
            $ctlBoth = @($ctlOut | Select-String 'FAIL  \[both\]').Count
            $m = [regex]::Match("$ctlLine", '(\d+)\s*/\s*(\d+)\s+assertions PASS')
            if ($m.Success -and [int]$m.Groups[1].Value -eq 30 -and [int]$m.Groups[2].Value -eq 61 `
                    -and $ctlDisc -eq 31 -and $ctlBoth -eq 0) {
                Write-Host '  ok    suite-v020 vs v0.0.19                       30/61, 31 [v20], 0 [both]' -ForegroundColor Green
            } else {
                Write-Host ("  FAIL  control: {0}  ([v20] {1}, [both] {2}); wanted 30/61, 31, 0" -f `
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
if ($grand -ne 434 -and $node -and $php) {
    Write-Host ("  FAIL  expected 434, got {0}" -f $grand) -ForegroundColor Red
    $allOk = $false
}

Write-Host ''
Write-Host ('-' * 78)
if (-not $allOk) {
    Write-Host 'VERIFY FAILED. Do not commit.' -ForegroundColor Red
    Write-Host 'A PHP suite total that moved is a FINDING, not a number to edit here:' -ForegroundColor Red
    Write-Host 'all five carried suites read the class, and the class grew 8,394 bytes.' -ForegroundColor Red
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
        '1.  SFTP in BINARY mode. THREE files deploy:'
        '      build\out20\class.slp_avalon.php  ->  slp_avalon/inc/'
        '      build\out20\slp_avalon.js         ->  slp_avalon/assets/js/'
        '      build\out20\slp_avalon.php        ->  slp_avalon/'
        '2.  SSH, then md5sum each of the three. They must read'
        '      d00964ee60539ba470ae6d657280aba3   class.slp_avalon.php'
        '      61921f1feca574f9f4ba4b36b362d4a0   slp_avalon.js'
        '      8d798809eafce52ed0b95d165aed4345   slp_avalon.php'
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
    Write-Host 'PART B - browser and database. Issue 31 is a DESTRUCTIVE change.' -ForegroundColor Yellow
    @(
        '7.  BEFORE the first import, record the baseline on Aura DEV:'
        '      wp post list --post_type=store_page --post_status=any --format=count'
        '    It read 320 on 2026-09-05, with 12 orphans.'
        '8.  Run one import. Then read the summary:'
        '      wp option get avalon_geocode_last_run --format=json'
        '    rows_removed, orphans_trashed and reconcile_aborted are new.'
        '    orphans_trashed should be 0 on a clean run - the twelve existing'
        '    orphans have no stale ROW behind them, so this release stops new'
        '    ones rather than clearing the backlog. Clearing it is a separate,'
        '    manual step and must not be folded into an unattended import.'
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
    Write-Host 'ROLLBACK, without a deploy:' -ForegroundColor Yellow
    Write-Host "    define('AVALON_ORPHAN_CLEANUP', false);   // in wp-config.php"
    Write-Host '    Rows are still reconciled; no post is trashed. That is exactly'
    Write-Host '    v0.0.19 behaviour, and suite-v020 D14a-d assert it.'
    Write-Host ''
    exit 0
}
