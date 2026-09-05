<#
.SYNOPSIS
    SLP Dealer Guard - stage, verify, commit and tag v0.0.17.

.DESCRIPTION
    Four edits in class.slp_avalon.php, plus the version header.

    1. avalon_geocode_overrides ROTATES on the first flush of a run. It held
       405 entries and 86,712 bytes on Aura DEV after four imports, growing 55
       a night forever, and avalon_flush_import_log() read-modify-wrote the
       whole option every 20 entries. Now the previous run moves to
       avalon_geocode_overrides_prev and the current one starts empty: two
       options, one import each, bounded permanently, both autoload off.
    2. AVALON_TIER2_MAX_CORRECTIONS default 25 -> 60.
    3. isset() guard on $location['sl_address2'], line 1147.
    4. The circuit-breaker comment now describes what the code does.

    FOUR differences from Publish-Step12.ps1, all deliberate.

    1. The control score is 10/22, and suite-v017.php is a partial
       discriminator in the same sense suite-v016.php was: ten [both] cases
       hold against either build by design, twelve [v17] cases do not. Both
       numbers are asserted. 22/22 against the control means the suite is not
       testing this release; under 10 means it is failing for the wrong
       reason, which looks identical to success at the exit-code level.

    2. test/suite-v015.php IS PART OF THIS RELEASE. Raising the cap to 60
       breaks three of its assertions by construction: its rails test feeds 30
       synthetic rows expecting exactly 25 to move and the breaker to latch,
       and its defaults block asserts max_corrections 25. Unamended it scores
       30/33 against v0.0.17. The suite's own header states the rule - define
       no constants, feed enough rows to trip the real defaults - so the
       amendment keeps the rule and moves the arithmetic to 70 rows for a cap
       of 60. It is in $CommitFiles and its md5 is asserted in $ToolFiles.

       CONSEQUENCE, and the reason this is called out rather than left to be
       discovered: the amended suite-v015.php is now version-bound to v0.0.17
       and scores 31/33 against the v0.0.16 tag. It must NEVER be pointed at
       the control target. Only suite-v017.php runs against the control.

    3. There is no Verify-v017.ps1. Verify-v016.ps1 existed because the
       v0.0.16 control target lived in the working tree and was consumed by
       staging, so the control had to run before Stage. This release pins its
       control to the v0.0.16 TAG from the start, so the control is available
       at every Verify and every Tag run and a separate pre-stage script would
       assert nothing that is not asserted here. Verify-v016.ps1 stays in the
       repository as the v0.0.16 build gate; it is not touched.

    5. The node lint reports itself accurately. Step12 wrote
       `Assert-Git 'node --check'`, which renders as "git node --check exited
       1" when slp_avalon.js is absent or unparseable. The check was right;
       only the message came from the wrong helper.

    4. $ToolFiles asserts the md5 of the three files delivered this session.
       A partially-downloaded or stale suite-v015.php would surface as a
       mystery 30/33 four screens later. This turns it into one line.

    Verified totals, measured not estimated:

        suite-v008   24        suite-v012.php   68   (class file, re-run)
        suite-v009   13        suite-v015.php   33   (amended: 70 rows, cap 60)
        suite-v010   40        suite-v016.php   19   (class file, re-run)
        suite-v011   15        suite-v017.php   22   (10/22 on v0.0.16)
        suite-v013   35
        suite-v014   34        JS subtotal     161
                               PHP subtotal    142
                               TOTAL           303

    WHY v0.0.17 SHIPS BEFORE THE SIX-ENVIRONMENT PROMOTION. The load-bearing
    reason is the cap, not the growth. Aura runs 17 Tier 2 corrections against
    a cap of 25 - eight rows of headroom on the only feed ever measured. Tahoe
    and Avalon have never run the guard and are completely unmeasured. If
    either exceeds 25 the cap latches, aborts the whole Tier 2 pass, and the
    first import on a brand-new environment fails on a live site in a way that
    looks exactly like a defect. Secondary: promoting v0.0.17 rather than
    v0.0.16 is one deploy wave across six environments instead of two, and the
    five environments that have never run the guard never accumulate the
    unbounded option at all.

    The cost is real and stated rather than hidden: Aura LIVE has been showing
    ten dealers at 0,0 and the Germaine and Toons coordinate damage to
    customers throughout. One session's delay is the price of a single wave.

.PARAMETER Mode
    Stage   verify build\out17, copy into the working tree, rewrite the pin
            file, re-verify in place
    Verify  hash the working tree, cross-check the pin file, run all suites
            plus the negative control (default)
    Commit  stage the release files and commit
    Tag     create the annotated tag and print the acceptance checklist

.PARAMETER PluginRepo
    Defaults to the real path. Overriding it is for dry runs only.

.EXAMPLE
    .\Publish-Step13.ps1 -Mode Stage
    .\Publish-Step13.ps1 -Mode Verify
    .\Publish-Step13.ps1 -Mode Commit
    .\Publish-Step13.ps1 -Mode Tag

.NOTES
    Unblock-File .\Publish-Step13.ps1   before the first run. Windows tags a
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

$Tag     = 'v0.0.17'
$PrevTag = 'v0.0.16'

# Expected working-tree state AFTER staging. Update these together with
# build-v017.py, never independently.
$Expected = @{
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = 'd4cba5d011c248e3d1c3af9c7cfd8067'; Bytes = 84684; Crlf = 1853
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = 'aa55d35db27bbf5cb206f3e0570a505c'; Bytes = 1808;  Crlf = 59
    }
    # PHP-only release. This one must NOT move. Same md5 since v0.0.14.
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = '8c93719e41af3232c18773a104e8dedd'; Bytes = 67363; Crlf = 1604
    }
}

# The v0.0.16 class file: what Stage expects to find in place before it copies,
# and what the negative control extracts from the tag afterwards.
$PrevClassMd5 = '998e343bbe324656f8282c238f323441'

# What build-v017.py writes. Same hashes; different location.
$BuildOutput = @{
    'build/out17/class.slp_avalon.php' = $Expected['slp_avalon/inc/class.slp_avalon.php']
    'build/out17/slp_avalon.php'       = $Expected['slp_avalon/slp_avalon.php']
}

$StageMap = @{
    'build/out17/class.slp_avalon.php' = 'slp_avalon/inc/class.slp_avalon.php'
    'build/out17/slp_avalon.php'       = 'slp_avalon/slp_avalon.php'
}

# The three files delivered this session. Difference 4 in .DESCRIPTION: a stale
# or truncated copy of any of these is caught here rather than as an unexplained
# score forty lines further down.
$ToolFiles = @{
    'build/build-v017.py' = 'f1a873dfb8687ba0ed4e522b18969bc3'
    'test/suite-v017.php' = '0d7d5c193a22385444d5da162eea035d'
    'test/suite-v015.php' = '324039e9d7dd41082337b7983ae9b74d'
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
    @{ Path = 'slp-plugins\slp_avalon\inc\class.slp_avalon.php' ; Md5 = 'd4cba5d011c248e3d1c3af9c7cfd8067' }
    @{ Path = 'slp-plugins\slp_avalon\slp_avalon.php'           ; Md5 = 'aa55d35db27bbf5cb206f3e0570a505c' }
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
    'test/suite-v017.php', 'test/release-pins.csv',
    'build/build-v015.py', 'build/build-v016.py', 'build/build-v017.py',
    'Verify-v016.ps1', 'Publish-Step11.ps1', 'Publish-Step12.ps1'
)

$JsSuites = @{
    'test/suite-v008.js' = 24; 'test/suite-v009.js' = 13
    'test/suite-v010.js' = 40; 'test/suite-v011.js' = 15
    'test/suite-v013.js' = 35; 'test/suite-v014.js' = 34
}

# suite-v015.php is here because this release amends it - difference 2 in
# .DESCRIPTION. Publish-Step10.ps1 and Verify-AttributesRename.ps1 stay
# untracked, unchanged from Step11's reasoning. .gitignore is NOT here:
# build/out*/ went in with v0.0.15 and covers build/out17 - Verify asserts it.
$CommitFiles = @(
    'slp_avalon/inc/class.slp_avalon.php',
    'slp_avalon/slp_avalon.php',
    'build/build-v017.py',
    'test/suite-v017.php',
    'test/suite-v015.php',
    'test/release-pins.csv',
    'Publish-Step13.ps1'
)

$TagMessage = @'
v0.0.17: bound the override log, raise the correction cap to 60, guard
sl_address2, and correct the circuit-breaker comment

ROTATION. avalon_geocode_overrides accumulated every import forever. Measured
on Aura DEV before truncation: 405 entries, 86,712 bytes, roughly 214 bytes per
entry and 55 entries a night, about 4.3 MB a year. autoload is off so there is
no page-load cost; the cost is inside the import, where avalon_flush_import_log
read-modify-wrote the whole option every 20 entries, three or four times a run.
At 20,000 entries that is a 4 MB unserialize into a 20,000-element array, four
times a night, under php-fpm.

The first flush of a run now moves the option to avalon_geocode_overrides_prev
and starts the current one empty, gated by a new per-import state key
overrides_rotated. Two options, one import each, bounded permanently, both
autoload off. Between runs the current option holds the most recent complete
import and _prev the one before it.

The rotation lives in the completion callback v0.0.16 repaired rather than on
an import-start hook, so it travels on a hook path that now has a negative
control. Per-import state is per-request, and a respawned import would split
one run across the two options - it does not: tier1_written 11, tier2_written
17 and observed 27 are whole-file totals read out of avalon_import_state and
came back identical on the unattended cron path at 2026-09-04T05:15:39Z and the
WP-CLI path at 2026-09-05T02:23:14Z. A respawn would have produced partial
totals. The count($stored) > 500 slice is kept deliberately; it stops being a
history bound and becomes a per-run ceiling.

THE CAP. The guard filters slp_csv_locationdata - the CSV row, not the database
row - so the CSV still supplies all seventeen wrong coordinates and Tier 2
re-corrects the same seventeen rows on every import, permanently, until the
upstream feed is fixed. A freshly pulled dlrloc.csv still hashes to
af4260321d3127a35c2d5853de93e4c6 and still carries the TOONS TABLE ROCK /
ToonsUSA Grand Lake transposition, the chained Germaine shift, the BAY OUTBOARD
46.48 typo, the -9838239 longitude and ten rows at 0,0.

Standing state was 17 corrections against a cap of 25. That inverts the
breaker's purpose: it was designed assuming corrections are exceptional, and
they are a permanent 5.8% baseline. Nine more rows from a modest feed
regression would latch the cap and silently stop correcting everything after
the 25th row in processing order. 60 is roughly 20% of the 295 rows evaluated -
3.5x the measured baseline, an order of magnitude below the 50%+ a systemic
break would look like. Tahoe and Avalon have never run the guard and are
unmeasured, which is why this ships before the promotion rather than after it.

SL_ADDRESS2. create_location_hash() read $location['sl_address2'] unguarded,
warning on every row of every import since v0.0.14 - roughly 300 lines a night
into the WP Engine error log, competing with the guard's own 56 lines for a
1500-row window. Docroot suppression via wp-cli.yml does not reach the web or
cron path. The guard substitutes '' rather than anything else because the
unguarded read yielded null and null interpolates as the empty string: the
hashes for all 308 rows are byte-identical across this change. They have to be.
The hash covers name_address_address2_city_state_zip_country_dealer_id and no
coordinates, which is what keeps a coordinate-only write from falling off
avalon_updated_slp_locations and triggering the nightly delete.

The unreachable else branch at 1153-1161 reads $data['address2'] just as
unguardedly and is deliberately untouched: create_location_hash has exactly two
call sites and both pass array('location' => ...).

THE COMMENT. It claimed the breaker stops a systemic failure "half way". It
does not. The cap latches and stops further comparison; corrections already
made were written into $location_data row by row and are committed. rev14 s2
repeated the same overstatement and so did suite-v015's rails comment. Both are
corrected here.

Tests: suite-v017.php, 22 assertions, scoring 10/22 against v0.0.16. Ten are
[both] cases that hold either way by design and twelve are [v17]
discriminators. The one assertion of the form "nothing happened" - no _prev is
created when there is nothing to rotate - is paired with the overrides_rotated
state flag, which requires the rotation branch to have executed; without the
pairing it would pass against an artefact containing no rotation code at all.

suite-v015.php is amended in this release, from 30 synthetic rows for a cap of
25 to 70 for a cap of 60. Raising the default breaks three of its assertions by
construction and it scored 30/33 unamended. It keeps its own rule of defining
no constants and tripping the real defaults instead. It is now version-bound to
v0.0.17 and scores 31/33 against the v0.0.16 tag, so it must never be pointed
at the control target.

suite-v016.php re-run at 19/19 and suite-v012.php at 68/68 because the class
file moved. Six JS suites unchanged at 161 against an untouched slp_avalon.js.
303 total, up from 281.

NOT in this release: removing the C/O Cole exclusion. rev14 s3.2 called it
stale on the strength of an adjacent feed row; rev16 s0.44 shows there are two
distinct records - 104782 C/O Cole in Pembina ND and 104783 WATERTOWN, INC. in
Lac Du Bonnet MB - and C/O Cole is carrying WATERTOWN's Manitoba coordinates to
within 0.3 metres. excluded:2 and stale_exclusions:[] are both correct. A live
defect stays masked behind that exclusion: a US dealer plotted about 100 miles
away in Canada. Whether to keep excluding it is a decision, not cleanup.
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
    # build's printout. v0.0.16 moved bytes without moving lines; this release
    # moves both. Checking them separately is what tells a line-ending accident
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
    # file that still reads v0.0.16 would agree with a tree that was never
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

# The three files delivered this session, checked in every mode. A truncated
# download or a suite-v015.php that was never overwritten is the difference
# between 33/33 and 30/33, and this says so in one line rather than forty.
Write-Host ''
Write-Host 'Session artefacts' -ForegroundColor Cyan
$toolsOk = $true
foreach ($rel in ($ToolFiles.Keys | Sort-Object)) {
    if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel -Want $ToolFiles[$rel])) { $toolsOk = $false }
}
if (-not $toolsOk) {
    Write-Host ''
    Write-Host 'One of the three files from this session is missing or stale.' -ForegroundColor Red
    Write-Host 'test/suite-v015.php in particular is AMENDED by this release: the' -ForegroundColor Red
    Write-Host 'unamended copy scores 30/33 against v0.0.17 because the cap moved.' -ForegroundColor Red
    exit 1
}

# -------------------------------------------------------------------- stage

if ($Mode -eq 'Stage') {

    # Establish what we are staging ONTO. Staging v0.0.17 over something that
    # is neither v0.0.16 nor v0.0.17 means the tree is in an unknown state and
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
        Write-Host 'Build output does not match. Re-run: python build\build-v017.py' -ForegroundColor Red
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
    Write-Host 'Staged. Now run: .\Publish-Step13.ps1 -Mode Verify' -ForegroundColor Green
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

# build/out*/ went into .gitignore with v0.0.15 and covers build/out17. If it
# is not there, the build directory will be swept into this commit.
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

# Pinned to the TAG, not HEAD and not the working tree, for the reason Step12
# recorded: the control must survive staging and commit so that -Mode Tag can
# still run it.
$oldClassRef = "${PrevTag}:slp_avalon/inc/class.slp_avalon.php"
$grand       = 0

Push-Location $PluginRepo
try {
    # --- PHP. This release IS the PHP, so a missing interpreter is fatal.
    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) {
        Write-Host '  FAIL  php not on PATH. v0.0.17 IS the PHP - nothing can be' -ForegroundColor Red
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

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v017.php' -ArtefactPath $newClass `
                -ExpectPass 22 -ExpectTotal 22 -Label 'suite-v017.php') { $grand += 22 }
        else { $allOk = $false }

        # --- NEGATIVE CONTROL. Extract the v0.0.16 class file from the tag and
        # confirm the suite scores EXACTLY 10/22 against it. Ten [both] cases
        # hold either way by design; twelve [v17] cases must not. Without this
        # the run above proves nothing about what the suite is measuring.
        #
        # ONLY suite-v017.php runs here. The amended suite-v015.php is
        # version-bound to v0.0.17 and scores 31/33 against this target - see
        # difference 2 in .DESCRIPTION.
        $tmpOld = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v016-negctl.php'
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
            Write-Host '        git show returned something other than the v0.0.16 class file.' -ForegroundColor Red
            $allOk = $false
        } else {
            Write-Host ("  ok    control target {0} = {1}" -f $oldClassRef, $ctlMd5) -ForegroundColor Green
            if (-not (Invoke-PhpSuite -SuiteRel 'test/suite-v017.php' -ArtefactPath $tmpOld `
                        -ExpectPass 10 -ExpectTotal 22 -Label 'NEGATIVE CONTROL vs v0.0.16 (must be 10/22)')) {
                Write-Host '        22/22 means the suite is not testing this release.' -ForegroundColor Red
                Write-Host '        Under 10 means it is failing for the wrong reason.' -ForegroundColor Red
                Write-Host '        Either way: do not commit.' -ForegroundColor Red
                $allOk = $false
            }
        }
        Remove-Item -LiteralPath $tmpOld -ErrorAction SilentlyContinue

        # Regressions. All three read the class file, which moved this release.
        # suite-v015.php is itself amended - 70 rows for a cap of 60.
        if (Invoke-PhpSuite -SuiteRel 'test/suite-v015.php' -ArtefactPath $newClass `
                -ExpectPass 33 -ExpectTotal 33 -Label 'suite-v015.php (amended) regression') { $grand += 33 }
        else { $allOk = $false }

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v016.php' -ArtefactPath $newClass `
                -ExpectPass 19 -ExpectTotal 19 -Label 'suite-v016.php regression') { $grand += 19 }
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
        # Step12 wrote `Assert-Git 'node --check'` here, which renders as
        # "git node --check exited 1" when slp_avalon.js is absent or broken.
        # The check is right; only the message was borrowed from the wrong
        # helper. Difference 5 from Step12.
        & node --check $jsArtefact
        if ($LASTEXITCODE -ne 0) {
            throw "node --check failed on slp_avalon/assets/js/slp_avalon.js (exit $LASTEXITCODE)"
        }
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
Write-Host ("  total assertions: {0}  (expected 303 with php + node present)" -f $grand)

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
        '1.  SFTP in BINARY mode: build\out17\class.slp_avalon.php  ->  slp_avalon/inc/'
        '2.  SFTP in BINARY mode: build\out17\slp_avalon.php        ->  slp_avalon/'
        '3.  SSH: md5sum both. Must read d4cba5d0... (84684) and aa55d35d... (1808).'
        '    v0.0.16 changed bytes without changing lines, so a line number cannot'
        '    identify a build here. The md5 on the server is the only statement'
        '    about what is running.'
        '4.  SSH: php -l on both, on the server, on PHP 8.4. The local lint above'
        '    ran on whatever this machine has and is not the authority.'
        '5.  /find-a-dealer/ loads. No white screen - a PHP parse error in this file'
        '    takes down the whole site.'
        '6.  Search a known ZIP. Results return, count unchanged from before deploy.'
        '7.  Browser console clean. No new errors.'
        '8.  wp-admin loads; SLP settings screens render.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'PART B - needs an import. Gates promotion to Aura LIVE, not this tag.' -ForegroundColor Yellow
    @(
        '9.  Run the import. Redirect stderr - the docroot wp-cli.yml trick does NOT'
        '    suppress the warnings (rev16 s0.48), and after this release there are'
        '    ~300 fewer of them to suppress anyway.'
        '      cd /nas/content/live/aurapontoonstg'
        '      wp --skip-plugins=revslider cron event run cron_csv_import 2> ~/import.log'
        '    Record next_run_gmt BEFORE and AFTER: wp cron event run reschedules to'
        '    time()+interval, so every manual run moves the slot (rev16 s0.45).'
        '10. wp --skip-plugins=revslider option get avalon_geocode_last_run --format=json'
        '    Warm-cache expectations: tier1_written 11 - NOT 0, the CSV resupplies'
        '    the ten 0,0 rows and the -9838239 longitude every night - tier2_written'
        '    17, observed 27, excluded 2, geocodes_spent 0, tier2_aborted false,'
        '    stale_exclusions [] (EMPTY - the C/O Cole exclusion is live and'
        '    necessary, rev16 s0.44).'
        '11. THE ROTATION, first import. avalon_geocode_overrides was deleted from'
        '    Aura DEV on 2026-09-05, so there is nothing to rotate on this run:'
        '      wp --skip-plugins=revslider option get avalon_geocode_overrides --format=json | jq length'
        '      -> 55'
        '      wp --skip-plugins=revslider option get avalon_geocode_overrides_prev'
        '      -> must report NOT SET. An empty _prev is not created.'
        '12. THE ROTATION, second import. Run the import once more, then:'
        '      avalon_geocode_overrides       -> 55   (this run only)'
        '      avalon_geocode_overrides_prev  -> 55   (the run from item 11)'
        '    If the current option reads 110, the rotation did not fire. STOP.'
        '13. The option sizes are now bounded. Both must stay autoload off:'
        '      PFX=$(wp --skip-plugins=revslider db prefix)'
        '      wp --skip-plugins=revslider db query "SELECT option_name, autoload,'
        '        LENGTH(option_value) AS bytes FROM ${PFX}options'
        '        WHERE option_name LIKE (SELECT CONCAT(CHAR(97),CHAR(37)))" ;'
        '    or simply read the three avalon_geocode_* rows. ~12 KB each, autoload no.'
        '14. THE WARNINGS ARE GONE. ~ /import.log should no longer carry ~300'
        '    "Undefined array key sl_address2" lines. That also frees the WP Engine'
        '    1500-row log window, which the guard output was competing with.'
        '15. THE HASHES DID NOT MOVE. Record count still 308, nothing deleted,'
        '    DONNIE MARCH still at 42.220530 / -83.466000. If create_location_hash'
        '    had changed, the reconcile would see 308 changed rows.'
        '16. tier2_aborted MUST be false, and the cap now reads 60 rather than 25.'
        '    17 corrections against 60 is 3.5x headroom instead of 1.5x.'
        '17. Watch one unattended cron run and read the summary from the WP Engine'
        '    User Portal -> Logs -> Error tab. Filter on "SLP Dealer Guard" and'
        '    EXPORT IMMEDIATELY: retention is 24 hours and 1500 rows. There is no'
        '    SSH-reachable PHP error log on this install.'
        ''
        'Then, and only then, ONE promotion wave:'
        '  Aura LIVE -> Tahoe DEV -> Tahoe LIVE -> Avalon DEV -> Avalon LIVE.'
        'Full completion on each before the next. Tahoe and Avalon are COLD CACHE:'
        '150 geocodes per import, so expect 2-3 nights each to warm. Record Tier 1'
        'count, Tier 2 count and the observation distribution per brand - that is the'
        'first data on whether the 10-mile threshold holds outside Aura.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'If anything in Part B looks wrong, the kill switches need no deploy:' -ForegroundColor Cyan
    Write-Host "  define('AVALON_IMPORT_GEOCODE_TIER2', false);   // in wp-config.php"
    Write-Host "  define('AVALON_IMPORT_GEOCODE_TIER1', false);"
    Write-Host "  define('AVALON_TIER2_MAX_CORRECTIONS', 25);     // back to the old cap"
    Write-Host '  One import later the CSV values are back. Nothing here changes what'
    Write-Host '  the feed supplies.'
    Write-Host ''
    exit 0
}
