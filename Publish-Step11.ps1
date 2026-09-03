<#
.SYNOPSIS
    SLP Dealer Guard - stage, verify, commit and tag v0.0.15.

.DESCRIPTION
    Import coordinate hygiene. Tier 1 (Issue 26, ten dealers at 0,0) and
    Tier 2 (Issue 27, coordinates disagreeing with their own address).

    FOUR differences from Publish-Step10.ps1, all deliberate.

    1. A -Mode Stage. Step10 assumed the working tree already held the built
       artefacts and never said how they got there. That copy was the one
       unscripted step in an otherwise fully verified chain, and it is exactly
       where byte-exactness goes missing. Stage verifies build\out15 against
       the pins, copies, and re-verifies in place.

    2. class.slp_avalon.php MOVES in this release. Step10 pinned it static
       because v0.0.15-as-Issue-22 was JS-only. Here it is the file that
       changes, and slp_avalon.js is the one that must NOT move.

    3. The suites are PHP, not JS. suite-v015.php carries 33 assertions and
       scores 0/33 against v0.0.14 - a total discriminator, no shared cases.
       suite-v012.php must be re-run because this is the first release to
       touch the class file it reads; it holds at 68/68.

    4. The Tag checklist is SPLIT. This release cannot be fully accepted at
       tag time: half of it is only observable after an import has run. Items
       1-8 are provable immediately. Items 9-20 need an import and gate the
       promotion to LIVE, not the tag.

    Verified totals, measured not estimated:

        suite-v008   24        suite-v012.php   68   (class file, re-run)
        suite-v009   13        suite-v015.php   33   (0/33 on v0.0.14)
        suite-v010   40
        suite-v011   15        JS subtotal     161
        suite-v013   35        PHP subtotal    101
        suite-v014   34        TOTAL           262

.PARAMETER Mode
    Stage   verify build\out15, copy into the working tree, re-verify
    Verify  hash the working tree and run all suites (default)
    Commit  stage the release files and commit
    Tag     create the annotated tag and print the acceptance checklist

.EXAMPLE
    .\Publish-Step11.ps1 -Mode Stage
    .\Publish-Step11.ps1 -Mode Verify
    .\Publish-Step11.ps1 -Mode Commit
    .\Publish-Step11.ps1 -Mode Tag
#>

[CmdletBinding()]
param(
    [ValidateSet('Stage', 'Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Tag = 'v0.0.15'

# Expected working-tree state AFTER staging. Update these together with
# build-v015.py, never independently.
$Expected = @{
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = 'c5ff85e089366ded99b7b7d8b083f537'; Bytes = 83198; Crlf = 1831
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = '6df1d53be2c28e3ca0e43b5f1bf31e7a'; Bytes = 1808;  Crlf = 59
    }
    # PHP-only release. This one must NOT move. Same md5 since v0.0.14.
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = '8c93719e41af3232c18773a104e8dedd'; Bytes = 67363; Crlf = 1604
    }
}

# What build-v015.py writes. Same hashes; different location.
$BuildOutput = @{
    'build/out15/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']
    'build/out15/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']
}

$StageMap = @{
    'build/out15/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'
    'build/out15/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'
}

$TestFiles = @(
    'test/harness.js', 'test/suite-core.js',
    'test/suite-v008.js', 'test/suite-v009.js', 'test/suite-v010.js',
    'test/suite-v011.js', 'test/suite-v012.php', 'test/suite-v013.js',
    'test/suite-v014.js', 'test/suite-v015.php', 'test/release-pins.csv',
    'build/build-v015.py'
)

$JsSuites = @{
    'test/suite-v008.js' = 24; 'test/suite-v009.js' = 13
    'test/suite-v010.js' = 40; 'test/suite-v011.js' = 15
    'test/suite-v013.js' = 35; 'test/suite-v014.js' = 34
}

# Publish-Step3 through Publish-Step9 are all tracked, so the publish script
# for this release belongs in the repository too.
#
# Two deliberate omissions. Publish-Step10.ps1 stays untracked because that
# release was built, deployed and fully reverted. Verify-AttributesRename.ps1
# stays out because it pins the v0.0.14 md5s and would FAIL against the tree it
# would be committed into - unlike Fix-SlpPluginsHistory*.ps1, which are also
# one-shot but assert nothing about current state. A verification script that
# cannot pass in its own repository is worse than no script.
$CommitFiles = @(
    'slp_avalon/inc/class.slp_avalon.php',
    'slp_avalon/slp_avalon.php',
    'build/build-v015.py',
    'test/suite-v015.php',
    'test/release-pins.csv',
    'Publish-Step11.ps1',
    '.gitignore'
)

$TagMessage = @'
v0.0.15: geocode the ten dealers stranded at 0,0, and correct coordinates
that disagree with their own address

Issue 26 and Issue 27. A filter on slp_csv_locationdata at priority 20,
running once per CSV row on both the manual upload and the nightly cron -
they converge on SLP_Power_Locations_Import::import() at 307, so there is
one code path, not two.

Tier 1. Coordinates absent, blank, non-numeric, out of range or exactly 0,0
are geocoded from the row's own address. The pre-existing
add_lat_lng_before_csv_import() was never wired up and carried six defects,
all fixed: an (int) cast that read -9838239 as truthy and let the one
genuinely broken longitude in the Aura feed walk through; an unguarded index
on a key SLP does not backfill until one line after this filter; no
CURLOPT_TIMEOUT at all; a 0,0 answer written back as though it were a
result; no territory validation; no logging.

Tier 2. Coordinates that ARE sane are compared against a geocode of their
own address with vincentyGreatCircleDistance(). At or beyond 10 miles the
geocode wins. Between 2 and 10 miles nothing is written and the disagreement
is logged with its distance, so the next threshold decision is made from the
real distribution across all three feeds rather than from eleven rows
measured on one day.

Three rails, because Tier 2 writes over data we do not own. A correction cap
of 25 per import aborts the whole pass rather than half-rewriting the table
when something systemic goes wrong. A geocode budget of 150 per import
bounds what a cold cache can add to a cron run; Aura's 308 addresses warm
over two or three nights. Every corrected coordinate must pass
is_in_territory(), the same predicate Layer 3 applies to search results.

DONNIE MARCH and C/O Cole International USA are excluded by name. Correcting
the first would publish a private residence as a dealer location;
suppressing it would delete the record that night, because
csv_processing_complete_func() removes every location whose hash is absent
from avalon_updated_slp_locations. I-94 Marine already carries three
store_page permalinks from exactly that churn, against one CSV row.

Everything is switchable from wp-config.php without a deploy. Nothing here
changes what the CSV supplies, so disabling the constants and letting one
import run restores the previous state within 24 hours.

Corrections to handoff rev13 s15, all measured: uncommenting line 376 as s15
instructs is a PHP parse error - it sits at class-body scope, and a negative
control confirmed "syntax error, unexpected identifier add_filter" on load;
process_File() at 901 is dead code so line 923 is unreachable; and
is_valid_lat("0.000000000") returning true, while real, is the second operand
of a || that skip_geocoding may short-circuit before it is ever evaluated.

Tests: suite-v015.php, 33 assertions, run against v0.0.14 first where it
scored 0/33 - every case a discriminator, no shared passes. The first draft
scored 3/33 there; all three were "nothing happened" assertions that pass
trivially when the code under test is absent, and were rewritten rather than
shipped. suite-v012.php re-run at 68/68 because this is the first release to
modify the class file it reads. Six JS suites unchanged at 161 against an
untouched slp_avalon.js. 262 total.
'@

# ------------------------------------------------------------------ helpers

function Assert-Git {
    param([string]$What, [int[]]$Allow = @(0))
    if ($Allow -notcontains $LASTEXITCODE) { throw "git $What exited $LASTEXITCODE" }
}

function Assert-File {
    param([string]$Root, [string]$RelPath, [hashtable]$Want)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) {
        Write-Host ("  FAIL  MISSING: {0}" -f $RelPath) -ForegroundColor Red
        return $false
    }

    $bytes = [System.IO.File]::ReadAllBytes($full)
    $md5   = (Get-FileHash -LiteralPath $full -Algorithm MD5).Hash.ToLower()

    $cr = 0; $lf = 0
    for ($i = 0; $i -lt $bytes.Length; $i++) {
        if ($bytes[$i] -eq 13) { $cr++ } elseif ($bytes[$i] -eq 10) { $lf++ }
    }

    $problems = @()
    if ($md5 -ne $Want.Md5)            { $problems += "md5 $md5 != $($Want.Md5)" }
    if ($bytes.Length -ne $Want.Bytes) { $problems += "bytes $($bytes.Length) != $($Want.Bytes)" }
    if ($cr -ne $Want.Crlf)            { $problems += "CR $cr != $($Want.Crlf)" }
    if ($cr -ne $lf)                   { $problems += "CR $cr != LF $lf (mixed endings)" }
    if ($bytes[-1] -eq 10)             { $problems += "trailing newline present" }

    if ($problems.Count -gt 0) {
        Write-Host ("  FAIL  {0}" -f $RelPath) -ForegroundColor Red
        $problems | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        return $false
    }
    Write-Host ("  ok    {0,-40} {1}  {2,6} bytes  CRLF={3}" -f `
        $RelPath, $md5, $bytes.Length, $cr) -ForegroundColor Green
    return $true
}

Write-Host ''
Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]" -ForegroundColor Cyan
Write-Host ('-' * 78)

if (-not (Test-Path -LiteralPath $PluginRepo)) { throw "Plugin repo not found: $PluginRepo" }
$PluginRepo = (Resolve-Path -LiteralPath $PluginRepo).Path
[System.Environment]::CurrentDirectory = $PluginRepo   # PS location != .NET CWD

# -------------------------------------------------------------------- stage

if ($Mode -eq 'Stage') {
    Write-Host ''
    Write-Host 'Build output' -ForegroundColor Cyan
    $ok = $true
    foreach ($rel in ($BuildOutput.Keys | Sort-Object)) {
        if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $BuildOutput[$rel])) { $ok = $false }
    }
    if (-not $ok) {
        Write-Host ''
        Write-Host 'Build output does not match. Re-run: python build\build-v015.py' -ForegroundColor Red
        exit 1
    }

    Write-Host ''
    Write-Host 'Copying into the working tree' -ForegroundColor Cyan
    foreach ($src in ($StageMap.Keys | Sort-Object)) {
        $from = Join-Path $PluginRepo $src
        $to   = Join-Path $PluginRepo $StageMap[$src]
        Copy-Item -LiteralPath $from -Destination $to -Force
        Write-Host ("  copied {0}  ->  {1}" -f $src, $StageMap[$src]) -ForegroundColor Green
    }

    Write-Host ''
    Write-Host 'Re-verifying in place' -ForegroundColor Cyan
    $ok = $true
    foreach ($rel in ($Expected.Keys | Sort-Object)) {
        if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $Expected[$rel])) { $ok = $false }
    }
    Write-Host ''
    if (-not $ok) { Write-Host 'Staging FAILED.' -ForegroundColor Red; exit 1 }
    Write-Host 'Staged. Now run: .\Publish-Step11.ps1 -Mode Verify' -ForegroundColor Green
    exit 0
}

# ------------------------------------------------------------- working tree

Write-Host ''
Write-Host 'Working tree' -ForegroundColor Cyan
$allOk = $true
foreach ($rel in ($Expected.Keys | Sort-Object)) {
    if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $Expected[$rel])) { $allOk = $false }
}
foreach ($rel in $TestFiles) {
    if (Test-Path -LiteralPath (Join-Path $PluginRepo $rel)) {
        Write-Host ("  ok    {0}" -f $rel) -ForegroundColor Green
    } else {
        # Fatal since v0.0.9. A warning here is what let v0.0.8 be tagged with
        # a message claiming assertions that were not in the repository.
        Write-Host ("  FAIL  MISSING: {0}" -f $rel) -ForegroundColor Red
        $allOk = $false
    }
}

# ------------------------------------------------------------- test suites

Write-Host ''
Write-Host 'Test suites' -ForegroundColor Cyan

$jsArtefact  = Join-Path $PluginRepo 'slp_avalon/assets/js/slp_avalon.js'
$newClass    = Join-Path $PluginRepo 'slp_avalon/inc/class.slp_avalon.php'
# Pinned to the TAG, not HEAD. -Mode Tag runs after -Mode Commit, at which
# point HEAD is already v0.0.15; referencing HEAD would make the negative
# control score 33/33 and block its own tag.
$oldClassRef = 'v0.0.14:slp_avalon/inc/class.slp_avalon.php'
$grand       = 0

Push-Location $PluginRepo
try {
    # --- PHP. This release IS the PHP, so a missing interpreter is fatal.
    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) {
        Write-Host '  FAIL  php not on PATH. v0.0.15 IS the PHP - nothing can be' -ForegroundColor Red
        Write-Host '        verified on this machine. Install PHP and re-run.' -ForegroundColor Red
        $allOk = $false
    } else {
        # suite-v015 against the new artefact.
        $out = & php 'test/suite-v015.php' $newClass 2>&1
        $line = ($out | Select-String 'assertions PASS' | Select-Object -Last 1)
        if ($LASTEXITCODE -eq 0 -and "$line" -match '(\d+)/(\d+) assertions PASS' `
            -and $Matches[1] -eq '33' -and $Matches[2] -eq '33') {
            Write-Host '  ok    suite-v015.php  33/33' -ForegroundColor Green
            $grand += 33
        } else {
            Write-Host '  FAIL  suite-v015.php did not score 33/33' -ForegroundColor Red
            $out | Select-Object -Last 12 | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
            $allOk = $false
        }

        # NEGATIVE CONTROL. Extract the committed class file and confirm the
        # suite scores ZERO against it. Without this the run above proves
        # nothing about what the suite is actually measuring.
        $tmpOld = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v014-negctl.php'
        Remove-Item -LiteralPath $tmpOld -ErrorAction SilentlyContinue
        # cmd redirection rather than a PowerShell pipe: piping git output
        # through PowerShell re-encodes it, and -Encoding Byte was removed in
        # PowerShell 7. This is byte-faithful on both.
        & cmd /c "git show $oldClassRef > `"$tmpOld`""
        if (-not (Test-Path -LiteralPath $tmpOld)) {
            throw "could not extract $oldClassRef for the negative control"
        }
        $neg = & php 'test/suite-v015.php' $tmpOld 2>&1
        $negLine = ($neg | Select-String 'assertions PASS' | Select-Object -Last 1)
        if ("$negLine" -match '(\d+)/(\d+) assertions PASS' -and $Matches[1] -eq '0') {
            Write-Host ("  ok    negative control  0/33 against {0}" -f $oldClassRef) -ForegroundColor Green
        } else {
            Write-Host "  FAIL  negative control scored: $negLine" -ForegroundColor Red
            Write-Host '        A suite that passes against the OLD file is not testing' -ForegroundColor Red
            Write-Host '        this release. Do not commit.' -ForegroundColor Red
            $allOk = $false
        }
        Remove-Item -LiteralPath $tmpOld -ErrorAction SilentlyContinue

        # suite-v012 regression. First release to modify the class file it reads.
        $out = & php 'test/suite-v012.php' $newClass 2>&1
        $line = ($out | Select-String 'assertions PASS' | Select-Object -Last 1)
        if ($LASTEXITCODE -eq 0 -and "$line" -match '(\d+)/(\d+) assertions PASS' `
            -and $Matches[1] -eq '68') {
            Write-Host '  ok    suite-v012.php  68/68  (class file changed this release)' -ForegroundColor Green
            $grand += 68
        } else {
            Write-Host "  FAIL  suite-v012.php regression: $line" -ForegroundColor Red
            $allOk = $false
        }
    }

    # --- JS. Untouched this release; these are pure regression.
    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) {
        Write-Host '  WARN  node not on PATH - the six JS suites were NOT run.' -ForegroundColor Yellow
        Write-Host '        slp_avalon.js is unchanged this release and its md5 above' -ForegroundColor Yellow
        Write-Host '        matches the artefact that passed 161, so the file is right;' -ForegroundColor Yellow
        Write-Host '        nothing was re-proved here.' -ForegroundColor Yellow
    } else {
        & node --check $jsArtefact
        Assert-Git 'node --check'
        foreach ($suite in ($JsSuites.Keys | Sort-Object)) {
            $want = $JsSuites[$suite]
            $out  = & node $suite $jsArtefact 2>&1
            $line = ($out | Select-String 'assertions PASS' | Select-Object -Last 1)
            if ($LASTEXITCODE -eq 0 -and "$line" -match '(\d+)/(\d+) assertions PASS' `
                -and $Matches[1] -eq "$want") {
                Write-Host ("  ok    {0,-22} {1}/{1}" -f (Split-Path $suite -Leaf), $want) -ForegroundColor Green
                $grand += $want
            } else {
                Write-Host ("  FAIL  {0}: {1}" -f (Split-Path $suite -Leaf), $line) -ForegroundColor Red
                $allOk = $false
            }
        }
    }
}
finally { Pop-Location }

Write-Host ''
Write-Host ("  total assertions: {0}  (expected 262 with php + node present)" -f $grand)

Write-Host ''
if (-not $allOk) {
    Write-Host 'FAILED. Nothing was committed or tagged.' -ForegroundColor Red
    exit 1
}
Write-Host 'All checks passed.' -ForegroundColor Green

if ($Mode -eq 'Verify') {
    Write-Host 'Verify only. Re-run with -Mode Commit when ready.' -ForegroundColor Yellow
    exit 0
}

# ------------------------------------------------------------------ commit

if ($Mode -eq 'Commit') {
    Push-Location $PluginRepo
    try {
        Write-Host ''
        Write-Host 'Staging for commit' -ForegroundColor Cyan

        # build/out*/ must be ignored before the commit, or the build output
        # lands in the repository.
        $gi = Join-Path $PluginRepo '.gitignore'
        $giText = [System.IO.File]::ReadAllText($gi)
        if ($giText -notmatch 'build/out') {
            # Byte append with an explicit LF. Add-Content would write CRLF and
            # leave .gitignore with mixed endings.
            if (-not $giText.EndsWith("`n")) { $giText += "`n" }
            [System.IO.File]::WriteAllText($gi, $giText + "build/out*/`n")
            Write-Host '  added build/out*/ to .gitignore (LF)' -ForegroundColor Green
        }

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
        Write-Host 'Committed. Re-run with -Mode Tag.' -ForegroundColor Green
    }
    finally { Pop-Location }
    exit 0
}

# --------------------------------------------------------------------- tag

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
    Write-Host 'PART A - provable now. Complete before leaving the machine.' -ForegroundColor Yellow
    @(
        '1.  SFTP in BINARY mode: build\out15\class.slp_avalon.php  ->  slp_avalon/inc/'
        '2.  SFTP in BINARY mode: build\out15\slp_avalon.php        ->  slp_avalon/'
        '3.  SSH: md5sum both. Must read c5ff85e0... (83198) and 6df1d53b... (1808).'
        '4.  /find-a-dealer/ loads. No white screen - a PHP parse error in this file'
        '    takes down the whole site, which is what the negative control proved.'
        '5.  Search a known ZIP. Results return, count unchanged from before deploy.'
        '6.  Browser console clean. No new errors.'
        '7.  wp-admin loads; SLP settings screens render.'
        '8.  SSH: wp option get avalon_geocode_cache   -> expect "not set" pre-import.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'PART B - needs an import. Gates promotion to LIVE, not this tag.' -ForegroundColor Yellow
    @(
        '9.  Read the FROZEN cron parameter first, so we know whether SLP''s own'
        '    geocode is a live backstop or absent:'
        '      wp post meta get <attachment_id> _wp_attachment_metadata | grep skip'
        '10. Trigger a MANUAL CSV import of dlrloc.csv from wp-admin. Same code path'
        '    as the cron - both converge on import() at 307 - so this is a valid'
        '    acceptance test and does not require waiting for 04:47:22Z.'
        '11. wp option get avalon_geocode_last_run   -> read the summary.'
        '12. tier1_written  expect 11  (ten rows at 0,0 plus the -9838239 longitude)'
        '13. tier2_written  expect <= 9  (11 flagged, minus the 2 exclusions)'
        '14. tier2_aborted  MUST be false. True means the cap tripped - STOP, do not'
        '    promote, and read the override log before anything else.'
        '15. stale_exclusions  MUST be empty. Non-empty means a dealer was renamed'
        '    and the DONNIE MARCH decision needs revisiting.'
        '16. observed  RECORD THIS NUMBER. It is the 2-10 mile distribution and it'
        '    is the whole reason the observation band exists. The next threshold'
        '    decision is made from it.'
        '17. Spot-check: BAY OUTBOARD MARINE now sits in Saginaw MI, not 207 mi away.'
        '18. Spot-check: DONNIE MARCH still at 42.220530 / -83.466000. Unmoved.'
        '19. Confirm ZERO dealers remain at 0,0.'
        '20. Confirm the record count is still 308. Nothing was deleted.'
        ''
        'Then, and only then: watch one real 04:47:22Z cron run before Aura LIVE.'
        'Deploy order stays Aura DEV -> Aura LIVE -> Tahoe DEV/LIVE -> Avalon DEV/LIVE.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'If anything in Part B looks wrong, the kill switches need no deploy:' -ForegroundColor Cyan
    Write-Host "  define('AVALON_IMPORT_GEOCODE_TIER2', false);   // in wp-config.php"
    Write-Host "  define('AVALON_IMPORT_GEOCODE_TIER1', false);"
    Write-Host '  One import later the CSV values are back. Nothing here changes what'
    Write-Host '  the feed supplies.'
    Write-Host ''
    exit 0
}
