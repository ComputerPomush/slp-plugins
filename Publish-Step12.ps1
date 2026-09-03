<#
.SYNOPSIS
    SLP Dealer Guard - stage, verify, commit and tag v0.0.16.

.DESCRIPTION
    The import summary was never written. add_action() was called with three
    arguments, so $accepted_args defaulted to 1, and do_action() substitutes an
    empty string when the caller supplies none. avalon_flush_import_log()
    received '' for $final, flushed the buffers, and took the early return
    before writing avalon_geocode_last_run. The fix is ",0" - two bytes.

    FIVE differences from Publish-Step11.ps1, all deliberate.

    1. The negative control is sourced from the TAG, not the working tree.
       Verify-v016.ps1 reads its control target from
       slp_avalon/inc/class.slp_avalon.php and therefore self-disables the
       moment build\out16 is staged over it - that is the guard at its line
       217, and it is correct for a pre-stage script. Step11 had already
       solved the same problem the other way, pinning to v0.0.14 rather than
       HEAD so that -Mode Tag could still run the control after -Mode Commit.
       Step12 does the same against v0.0.15. The control is therefore
       available at every Verify and every Tag run, permanently, and nothing
       depends on the working tree being held back.

    2. The control score is 9/19, not 0/19. suite-v016.php is a PARTIAL
       discriminator: nine [both] cases pass against either build by design,
       ten [v16] cases do not. Step11 asserted only that the numerator was 0.
       A partial control can drift in two directions and each means something
       different, so both numbers are asserted exactly. 19/19 means the suite
       is not testing this release. Anything under 9 means the suite is
       failing for the wrong reason, which looks identical to success at the
       exit-code level - handoff rev15 s9.

    3. Byte count and CRLF count are asserted independently of md5 and of the
       build's own printout. class.slp_avalon.php grows by exactly two bytes
       and does NOT gain a line: 83198 -> 83200, CRLF 1831 both sides. A
       line-ending accident would change both numbers together; this release
       is the case where only one of them may move.

    4. test/release-pins.csv is REWRITTEN, not carried forward. Two of its six
       rows change. It is LF-only with no BOM (471 bytes, 7 LF, 0 CR) and
       Set-Content or Add-Content would give it CRLF and, on Windows
       PowerShell, a BOM. It is written through System.IO with an explicit LF
       and then re-read and cross-checked against the same $Expected table
       that produced it.

    5. The upstream files are asserted, not merely left alone.
       store-locator-le/js/ belongs to the SLP plugin author and must never be
       modified. The two hashes were already in the pin file; this makes them
       a gate rather than a record.

    Verified totals, measured not estimated:

        suite-v008   24        suite-v012.php   68   (class file, re-run)
        suite-v009   13        suite-v015.php   33   (class file, re-run)
        suite-v010   40        suite-v016.php   19   (9/19 on v0.0.15)
        suite-v011   15
        suite-v013   35        JS subtotal     161
        suite-v014   34        PHP subtotal    120
                               TOTAL           281

    ONE JUDGEMENT CALL, recorded rather than hidden. Verify-v016.ps1 is in
    $CommitFiles because handoff rev15 s7 item 3 says to commit it. It sits
    awkwardly against Step11's rule at its own lines 106-114 - a verification
    script that cannot pass in its own repository is worse than no script -
    because once v0.0.16 is HEAD, Verify-v016.ps1 throws at its line 218 by
    design. The distinction being drawn is that Verify-AttributesRename.ps1
    pinned hashes that were simply WRONG for the tree, whereas Verify-v016.ps1
    refuses deliberately, with an accurate message, because it is a pre-stage
    build gate and not a repository-health assertion - the same category as
    build/build-v016.py, which also cannot be "run" against a staged tree.
    If that reading is rejected, drop it from $CommitFiles and leave it
    untracked alongside Publish-Step10.ps1. Nothing else changes.

.PARAMETER Mode
    Stage   verify build\out16, copy into the working tree, rewrite the pin
            file, re-verify in place
    Verify  hash the working tree, cross-check the pin file, run all suites
            plus the negative control (default)
    Commit  stage the release files and commit
    Tag     create the annotated tag and print the acceptance checklist

.PARAMETER PluginRepo
    Defaults to the real path. Overriding it is for dry runs only.

.EXAMPLE
    .\Publish-Step12.ps1 -Mode Stage
    .\Publish-Step12.ps1 -Mode Verify
    .\Publish-Step12.ps1 -Mode Commit
    .\Publish-Step12.ps1 -Mode Tag

.NOTES
    Unblock-File .\Publish-Step12.ps1   before the first run. Windows tags a
    downloaded .ps1 with a Zone.Identifier stream and RemoteSigned refuses it.
#>

[CmdletBinding()]
param(
    [ValidateSet('Stage', 'Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Tag     = 'v0.0.16'
$PrevTag = 'v0.0.15'

# Expected working-tree state AFTER staging. Update these together with
# build-v016.py, never independently.
$Expected = @{
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = '998e343bbe324656f8282c238f323441'; Bytes = 83200; Crlf = 1831
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = '5ff1a2b8f5a63d693587e994cbe947cb'; Bytes = 1808;  Crlf = 59
    }
    # PHP-only release. This one must NOT move. Same md5 since v0.0.14.
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = '8c93719e41af3232c18773a104e8dedd'; Bytes = 67363; Crlf = 1604
    }
}

# The v0.0.15 class file, which is what Stage expects to find in place before
# it copies, and what the negative control extracts from the tag afterwards.
$PrevClassMd5 = 'c5ff85e089366ded99b7b7d8b083f537'

# What build-v016.py writes. Same hashes; different location.
$BuildOutput = @{
    'build/out16/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']
    'build/out16/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']
}

$StageMap = @{
    'build/out16/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'
    'build/out16/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'
}

# Not ours. store-locator-le/js/ belongs to the SLP plugin author. Asserted by
# md5 only - byte and CRLF counts were never recorded for these and inventing
# them would be worse than not checking.
$Upstream = @{
    'store-locator-le/js/slp_core.js'     = 'a751bea043c19472ec6453aff93f84a9'
    'store-locator-le/js/slp_core.min.js' = '7924dad949f851d90ade9118c8bd045a'
    '.gitattributes'                      = '4ecda2243f179695cb31942fcbe9634d'
}

# test/release-pins.csv, in file order. Paths are relative to the GitHub root
# (D:\Temp\Projects\GitHub) because Inventory-LocalGitHub.ps1 -PinFile runs
# from there, not from inside this repository. Backslashes are load-bearing.
$PinRelPath = 'test/release-pins.csv'
$PinRows = @(
    @{ Path = 'slp-plugins\slp_avalon\assets\js\slp_avalon.js'  ; Md5 = '8c93719e41af3232c18773a104e8dedd' }
    @{ Path = 'slp-plugins\slp_avalon\inc\class.slp_avalon.php' ; Md5 = '998e343bbe324656f8282c238f323441' }
    @{ Path = 'slp-plugins\slp_avalon\slp_avalon.php'           ; Md5 = '5ff1a2b8f5a63d693587e994cbe947cb' }
    @{ Path = 'slp-plugins\.gitattributes'                      ; Md5 = '4ecda2243f179695cb31942fcbe9634d' }
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
    'test/release-pins.csv',
    'build/build-v015.py', 'build/build-v016.py',
    'Verify-v016.ps1', 'Publish-Step11.ps1'
)

$JsSuites = @{
    'test/suite-v008.js' = 24; 'test/suite-v009.js' = 13
    'test/suite-v010.js' = 40; 'test/suite-v011.js' = 15
    'test/suite-v013.js' = 35; 'test/suite-v014.js' = 34
}

# See the judgement call in .DESCRIPTION for Verify-v016.ps1.
# Publish-Step10.ps1 and Verify-AttributesRename.ps1 stay untracked, unchanged
# from Step11's reasoning. .gitignore is NOT here: build/out*/ went in with
# v0.0.15 and this release must not modify it - Verify asserts that.
$CommitFiles = @(
    'slp_avalon/inc/class.slp_avalon.php',
    'slp_avalon/slp_avalon.php',
    'build/build-v016.py',
    'test/suite-v016.php',
    'test/release-pins.csv',
    'Verify-v016.ps1',
    'Publish-Step12.ps1'
)

$TagMessage = @'
v0.0.16: pass accepted_args to the completion hook so the import summary is
actually written

add_action('slp_csv_processing_complete', ..., 500) leaves $accepted_args at
its default of 1. do_action() appends an empty string when the caller passes
no argument, WP_Hook::apply_filters() dispatches on 1 >= 1, and
avalon_flush_import_log($final = true) was therefore bound to '' rather than
to its default. The two flushes at the top of the method ran -
avalon_geocode_overrides and avalon_geocode_cache were both written - and then
if (! $final) { return; } took the early exit. avalon_geocode_last_run was
never written and avalon_import_state was never reset. SLP Power fires the
hook bare at SLP_Power_Locations_Import.php:773.

The fix is ",0" on one line. A defaulted parameter takes its default only when
ZERO arguments arrive, and add_action() with an unspecified $accepted_args
guarantees exactly one does.

Proven three ways before the fix was written: the runtime hook table reported
accepted_args=1; a two-line probe showed final = '' at three arguments and
final = true at four; and avalon_geocode_overrides held 185 entries, which is
not a multiple of the buffer threshold of 20, so the completion flush
demonstrably ran and returned before the summary.

Deliberately NOT fixed by loosening the guard to if ($final === false). That
would work and the signature would still be lying about its contract.

The first complete import measurement is now on record. A warm-cache run took
137.894s with zero geocode failures and the budget never exhausted, so every
non-excluded row was compared against its own address: 11 Tier 1 writes -
exactly the ten rows at 0,0 plus the -9838239 longitude, so all eleven Tier 1
defects are closed - 17 Tier 2 corrections, 27 observations in the 2-10 mile
band, and roughly 249 rows agreeing within 2 miles.

The threshold holds. Highest observation 8.32 miles, lowest correction 10.27,
a 1.95-mile gap with 10 inside it. Handoff rev14 s3.4 concluded from the first
150 rows that no such gap existed; the full file says otherwise.

The damage in the feed is row misalignment, not noise. TOONS TABLE ROCK and
ToonsUSA Grand Lake each held the other's coordinates, and un-swapped to
within 0.1 miles of each other's targets. The Germaine cluster is the same
fault chained across three rows. No coordinate-plausibility check could ever
have caught this - both values are valid coordinates in the right country -
which is why the design compares against the address rather than against a
bounding box, and why Tier 2 is permanent infrastructure rather than one-time
cleanup.

Tests: suite-v016.php, 19 assertions, scoring 9/19 against v0.0.15. Nine are
[both] cases that hold either way by design and ten are [v16] discriminators.
suite-v015.php re-run at 33/33 and suite-v012.php at 68/68 because the class
file moved. Six JS suites unchanged at 161 against an untouched
slp_avalon.js. 281 total.

suite-v015.php scored 33/33 against a build in which this hook was broken,
because its flush_log() helper calls the method directly and PHP applies the
parameter default. Testing a callback in isolation cannot see a wiring defect.
suite-v016.php reproduces WordPress's dispatch instead of calling the
callback. Same lesson as the v0.0.6 Get My Position miss.
'@

# ------------------------------------------------------------------ helpers

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

    # Byte count and CRLF count are re-derived here, independently of the
    # build's printout. This release moves bytes without moving lines, so the
    # two numbers must be checked separately rather than trusted together.
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
    Write-Host ("  ok    {0,-40} {1}  {2,6} bytes  CRLF={3}" -f `
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
    Write-Host ("  ok    {0,-40} {1}  {2,6} bytes  LF={3}" -f `
        $RelPath, $s.Md5, $s.Bytes, $s.Lf) -ForegroundColor Green
}

function Test-PinFile {
    <#
        Reads the pin file back off disk and checks three things: that it
        parses, that every hash in it matches the corresponding file on disk,
        and that the two files this release moves carry the NEW hashes. The
        pin file is generated from $Expected, so re-reading it is the only
        thing that turns it from a copy into a check.
    #>
    param([string]$Root, [string]$RelPath, [hashtable]$Expected, [hashtable]$Upstream)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) {
        Write-Host ("  FAIL  MISSING: {0}" -f $RelPath) -ForegroundColor Red
        return $false
    }

    $ok   = $true
    $seen = @{}
    $rows = @(Import-Csv -LiteralPath $full)

    if ($rows.Count -eq 0) {
        Write-Host '  FAIL  release-pins.csv parsed to zero rows' -ForegroundColor Red
        return $false
    }

    # StrictMode throws on a missing property, so check the header before the
    # loop rather than crashing inside it.
    $cols = $rows[0].PSObject.Properties.Name
    foreach ($need in @('RelativePath', 'MD5Hash')) {
        if ($cols -notcontains $need) {
            Write-Host ("  FAIL  release-pins.csv has no {0} column" -f $need) -ForegroundColor Red
            return $false
        }
    }

    foreach ($row in $rows) {
        # 'slp-plugins\a\b.php'  ->  'a/b.php', relative to THIS repo.
        $rel = $row.RelativePath
        if ($rel -notlike 'slp-plugins\*') {
            Write-Host ("  FAIL  pin row outside this repo: {0}" -f $rel) -ForegroundColor Red
            $ok = $false
            continue
        }
        $repoRel = ($rel -replace '^slp-plugins\\', '') -replace '\\', '/'
        $seen[$repoRel] = $row.MD5Hash.ToLower()

        $onDisk = Join-Path $Root $repoRel
        if (-not (Test-Path -LiteralPath $onDisk)) {
            Write-Host ("  FAIL  pinned file missing on disk: {0}" -f $repoRel) -ForegroundColor Red
            $ok = $false
            continue
        }
        $md5 = (Get-FileHash -LiteralPath $onDisk -Algorithm MD5).Hash.ToLower()
        if ($md5 -ne $row.MD5Hash.ToLower()) {
            Write-Host ("  FAIL  pin mismatch {0}" -f $repoRel) -ForegroundColor Red
            Write-Host ("          disk {0}  pin {1}" -f $md5, $row.MD5Hash.ToLower()) -ForegroundColor Red
            $ok = $false
        }
    }

    # The two files this release moves must be pinned to the NEW hashes. A pin
    # file that still reads v0.0.15 would agree with a tree that was never
    # staged, and both would be self-consistently wrong.
    foreach ($k in $Expected.Keys) {
        if (-not $seen.ContainsKey($k)) {
            Write-Host ("  FAIL  release-pins.csv does not pin {0}" -f $k) -ForegroundColor Red
            $ok = $false
        } elseif ($seen[$k] -ne $Expected[$k].Md5) {
            Write-Host ("  FAIL  release-pins.csv pins {0} to {1}, expected {2}" -f `
                $k, $seen[$k], $Expected[$k].Md5) -ForegroundColor Red
            $ok = $false
        }
    }
    foreach ($k in $Upstream.Keys) {
        if ($seen.ContainsKey($k) -and $seen[$k] -ne $Upstream[$k]) {
            Write-Host ("  FAIL  release-pins.csv pins upstream {0} to {1}" -f $k, $seen[$k]) -ForegroundColor Red
            $ok = $false
        }
    }

    if ($ok) {
        Write-Host ("  ok    {0}  {1} rows, all hashes match disk" -f $RelPath, $rows.Count) -ForegroundColor Green
    }
    return $ok
}

function Invoke-PhpSuite {
    <#
        Runs one PHP suite against one artefact and asserts the SCORE, not
        just the exit code. A suite that fails for the wrong reason looks
        identical to one that fails for the right reason at the exit-code
        level, which is exactly the failure mode a partial negative control
        can hide.
    #>
    param(
        [string]$SuiteRel,
        [string]$ArtefactPath,
        [int]$ExpectPass,
        [int]$ExpectTotal,
        [string]$Label
    )

    $out  = & php $SuiteRel $ArtefactPath 2>&1
    $code = $LASTEXITCODE
    $line = ($out | Select-String 'assertions PASS' | Select-Object -Last 1)

    # [regex]::Match rather than -notmatch. -notmatch does populate $Matches on
    # a successful match, but reading a capture group out of an operator that
    # returned $false is the kind of thing that survives review and then breaks
    # under a StrictMode bump.
    $m = [regex]::Match("$line", '(\d+)\s*/\s*(\d+)\s+assertions PASS')
    if (-not $m.Success) {
        Write-Host ("  FAIL  {0}: no score line" -f $Label) -ForegroundColor Red
        $out | Select-Object -Last 12 | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        return $false
    }

    $got = [int]$m.Groups[1].Value
    $tot = [int]$m.Groups[2].Value
    $wantCode = if ($ExpectPass -eq $ExpectTotal) { 0 } else { 1 }

    $problems = @()
    if ($tot  -ne $ExpectTotal) { $problems += "suite has $tot assertions, expected $ExpectTotal" }
    if ($got  -ne $ExpectPass)  { $problems += "scored $got/$tot, expected $ExpectPass/$ExpectTotal" }
    if ($code -ne $wantCode)    { $problems += "exit code $code, expected $wantCode" }

    if ($problems.Count -gt 0) {
        Write-Host ("  FAIL  {0}" -f $Label) -ForegroundColor Red
        $problems | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        $out | Select-Object -Last 12 | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        return $false
    }

    Write-Host ("  ok    {0,-46} {1}/{2}" -f $Label, $got, $tot) -ForegroundColor Green
    return $true
}

# --------------------------------------------------------------------- head

Write-Host ''
Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]" -ForegroundColor Cyan
Write-Host ('-' * 78)

if (-not (Test-Path -LiteralPath $PluginRepo)) { throw "Plugin repo not found: $PluginRepo" }
$PluginRepo = (Resolve-Path -LiteralPath $PluginRepo).Path
[System.Environment]::CurrentDirectory = $PluginRepo   # PS location != .NET CWD

# -------------------------------------------------------------------- stage

if ($Mode -eq 'Stage') {

    # Establish what we are staging ONTO. Staging v0.0.16 over something that
    # is neither v0.0.15 nor v0.0.16 means the tree is in an unknown state and
    # the copy would erase the evidence of how it got there.
    Write-Host ''
    Write-Host 'Base state' -ForegroundColor Cyan
    $classRel  = 'slp_avalon/inc/class.slp_avalon.php'
    $classFull = Join-Path $PluginRepo $classRel
    if (-not (Test-Path -LiteralPath $classFull)) { throw "missing $classRel" }
    $baseMd5 = (Get-FileHash -LiteralPath $classFull -Algorithm MD5).Hash.ToLower()

    if ($baseMd5 -eq $PrevClassMd5) {
        Write-Host ("  ok    working tree holds {0}  {1}" -f $PrevTag, $baseMd5) -ForegroundColor Green
    } elseif ($baseMd5 -eq $Expected[$classRel].Md5) {
        Write-Host ("  note  working tree ALREADY holds {0}. Re-staging is idempotent." -f $Tag) -ForegroundColor Yellow
    } else {
        Write-Host ("  FAIL  working tree class file is {0}" -f $baseMd5) -ForegroundColor Red
        Write-Host ("          expected {0} ({1}) or {2} ({3})" -f `
            $PrevClassMd5, $PrevTag, $Expected[$classRel].Md5, $Tag) -ForegroundColor Red
        Write-Host '          The tree is in an unknown state. Do not stage over it.' -ForegroundColor Red
        exit 1
    }

    Write-Host ''
    Write-Host 'Build output' -ForegroundColor Cyan
    $ok = $true
    foreach ($rel in ($BuildOutput.Keys | Sort-Object)) {
        if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $BuildOutput[$rel])) { $ok = $false }
    }
    if (-not $ok) {
        Write-Host ''
        Write-Host 'Build output does not match. Re-run: python build\build-v016.py' -ForegroundColor Red
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
    Write-Host 'Rewriting test/release-pins.csv' -ForegroundColor Cyan
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
    Write-Host 'Staged. Now run: .\Publish-Step12.ps1 -Mode Verify' -ForegroundColor Green
    exit 0
}

# ------------------------------------------------------------- working tree

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
    if (Test-Path -LiteralPath (Join-Path $PluginRepo $rel)) {
        Write-Host ("  ok    {0}" -f $rel) -ForegroundColor Green
    } else {
        Write-Host ("  FAIL  MISSING: {0}" -f $rel) -ForegroundColor Red
        $allOk = $false
    }
}

# build/out*/ went into .gitignore with v0.0.15. If it is not there, the build
# directory will be swept into this commit.
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
$newClass   = Join-Path $PluginRepo 'slp_avalon/inc/class.slp_avalon.php'

# Pinned to the TAG, not HEAD and not the working tree.
#
# Verify-v016.ps1 read the control target out of the working tree, which is
# correct for a script that runs BEFORE staging and refuses to run after. This
# one has to work after staging and after commit, so it reads v0.0.15 out of
# git. The control is therefore not consumed by staging and does not need the
# tree held back - handoff rev15 s7 item 1.
$oldClassRef = "${PrevTag}:slp_avalon/inc/class.slp_avalon.php"
$grand       = 0

Push-Location $PluginRepo
try {
    # --- PHP. This release IS the PHP, so a missing interpreter is fatal.
    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) {
        Write-Host '  FAIL  php not on PATH. v0.0.16 IS the PHP - nothing can be' -ForegroundColor Red
        Write-Host '        verified on this machine. Install PHP and re-run.' -ForegroundColor Red
        $allOk = $false
    } else {
        & php -l $newClass | Out-Null
        if ($LASTEXITCODE -ne 0) {
            Write-Host '  FAIL  php -l on the class file' -ForegroundColor Red
            Write-Host '        The authoritative lint is still the one on the server, on 8.4.' -ForegroundColor Red
            $allOk = $false
        } else {
            $phpVer = ((& php -r "echo PHP_VERSION;" 2>&1) -join '').Trim()
            Write-Host ('  ok    php -l  (local {0}; server lint on 8.4 is still required)' -f `
                $phpVer) -ForegroundColor Green
        }

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v016.php' -ArtefactPath $newClass `
                -ExpectPass 19 -ExpectTotal 19 -Label 'suite-v016.php') { $grand += 19 }
        else { $allOk = $false }

        # --- NEGATIVE CONTROL. Extract the v0.0.15 class file from the tag and
        # confirm the suite scores EXACTLY 9/19 against it. Nine [both] cases
        # hold either way by design; ten [v16] cases must not. Without this the
        # run above proves nothing about what the suite is measuring.
        $tmpOld = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v015-negctl.php'
        Remove-Item -LiteralPath $tmpOld -ErrorAction SilentlyContinue
        # cmd redirection rather than a PowerShell pipe: piping git output
        # through PowerShell re-encodes it, and -Encoding Byte was removed in
        # PowerShell 7. This is byte-faithful on both.
        & cmd /c "git show $oldClassRef > `"$tmpOld`""
        if (-not (Test-Path -LiteralPath $tmpOld)) {
            throw "could not extract $oldClassRef for the negative control"
        }
        $ctlMd5 = (Get-FileHash -LiteralPath $tmpOld -Algorithm MD5).Hash.ToLower()
        if ($ctlMd5 -ne $PrevClassMd5) {
            Write-Host ("  FAIL  control target extracted as {0}, expected {1}" -f $ctlMd5, $PrevClassMd5) -ForegroundColor Red
            Write-Host '        git show returned something other than the v0.0.15 class file.' -ForegroundColor Red
            $allOk = $false
        } else {
            Write-Host ("  ok    control target {0} = {1}" -f $oldClassRef, $ctlMd5) -ForegroundColor Green
            if (-not (Invoke-PhpSuite -SuiteRel 'test/suite-v016.php' -ArtefactPath $tmpOld `
                        -ExpectPass 9 -ExpectTotal 19 -Label 'NEGATIVE CONTROL vs v0.0.15 (must be 9/19)')) {
                Write-Host '        19/19 means the suite is not testing this release.' -ForegroundColor Red
                Write-Host '        Under 9 means it is failing for the wrong reason.' -ForegroundColor Red
                Write-Host '        Either way: do not commit.' -ForegroundColor Red
                $allOk = $false
            }
        }
        Remove-Item -LiteralPath $tmpOld -ErrorAction SilentlyContinue

        # Regressions. Both read the class file, which moved this release.
        if (Invoke-PhpSuite -SuiteRel 'test/suite-v015.php' -ArtefactPath $newClass `
                -ExpectPass 33 -ExpectTotal 33 -Label 'suite-v015.php regression') { $grand += 33 }
        else { $allOk = $false }

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v012.php' -ArtefactPath $newClass `
                -ExpectPass 68 -ExpectTotal 68 -Label 'suite-v012.php regression') { $grand += 68 }
        else { $allOk = $false }
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
Write-Host ("  total assertions: {0}  (expected 281 with php + node present)" -f $grand)

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
        '1.  SFTP in BINARY mode: build\out16\class.slp_avalon.php  ->  slp_avalon/inc/'
        '2.  SFTP in BINARY mode: build\out16\slp_avalon.php        ->  slp_avalon/'
        '3.  SSH: md5sum both. Must read 998e343b... (83200) and 5ff1a2b8... (1808).'
        '4.  SSH: php -l on both, on the server, on PHP 8.4. The local lint above'
        '    ran on whatever this machine has and is not the authority.'
        '5.  /find-a-dealer/ loads. No white screen - a PHP parse error in this file'
        '    takes down the whole site.'
        '6.  Search a known ZIP. Results return, count unchanged from before deploy.'
        '7.  Browser console clean. No new errors.'
        '8.  wp-admin loads; SLP settings screens render.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'PART B - needs an import. Gates promotion to LIVE, not this tag.' -ForegroundColor Yellow
    @(
        '9.  Run the import FROM THE DOCROOT, not from ~. wp-cli.yml is only read'
        '    from the working directory or an ancestor, and it is what loads'
        '    suppress-warnings.php. From ~ the ~300 sl_address2 warnings stream.'
        '      cd /nas/content/live/aurapontoonstg'
        '      wp --skip-plugins=revslider cron event run cron_csv_import'
        '10. wp --skip-plugins=revslider option get avalon_geocode_last_run --format=json'
        '    This is the assertion this release exists for. If it is still "not set",'
        '    the fix did not take - STOP.'
        '11. RECORD the summary and its timestamp NOW, before 21:42 local. It is the'
        '    only way to tell this WP-CLI write apart from the cron write in item 16.'
        '12. Warm-cache expectations: tier1_written 0, tier2_written 17, observed 27,'
        '    excluded 2, geocodes_spent 0, tier2_aborted false.'
        '13. stale_exclusions should name C/O COLE INTERNATIONAL USA|PEMBINA|ND for the'
        '    first time. The feed renamed that dealer to WATERTOWN, INC. in Lac Du'
        '    Bonnet MB and its coordinates are now self-consistent. Expected, not a'
        '    fault - the self-monitoring working. It comes out in v0.0.17.'
        '14. tier2_aborted MUST be false. Standing state is 17 corrections against a'
        '    cap of 25 - eight rows of headroom, and the cap LATCHES rather than'
        '    rolling back. Raising the default to 60 is a v0.0.17 item.'
        '15. count(avalon_geocode_cache) ~300. Record count still 308. Nothing deleted.'
        '    DONNIE MARCH still at 42.220530 / -83.466000, unmoved.'
        '16. Watch one real cron run at 01:42:41 UTC - that is 21:42:41 EDT, the SAME'
        '    EVENING, not the next morning. Hook cron_csv_import, recurrence 1 day.'
        '    (04:47:22Z is the frozen-params timestamp on attachment 139933 and has'
        '    nothing to do with scheduling - handoff rev15 s0.40.)'
        '    Confirm avalon_geocode_last_run was rewritten by the cron and not left'
        '    over from item 10. Under cron, error_log() goes to the WP Engine php-fpm'
        '    pool log in the User Portal; there is no SSH-reachable PHP error log.'
        ''
        'Then, and only then: Aura LIVE -> Tahoe DEV/LIVE -> Avalon DEV/LIVE.'
        'Full completion on each before the next.'
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
