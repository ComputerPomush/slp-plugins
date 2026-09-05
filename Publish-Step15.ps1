<#
.SYNOPSIS
    SLP Dealer Guard - stage, verify, commit and tag v0.0.19.

.DESCRIPTION
    Two unrelated changes, three files, and the first JS release since v0.0.14.

    1. slp_avalon.js gains the Autocomplete gate. Issue 35.
       initialize_autocomplete() no longer constructs
       google.maps.places.Autocomplete on sight. It registers a delegated,
       namespaced input listener and constructs the widget only once
       #addressInput holds avalon_autocomplete_min_chars characters, then
       unbinds itself. A five-digit ZIP costs two billed Places requests
       instead of five.
    2. slp_avalon.php takes the version header. Nothing else.
    3. .gitattributes gains store-locator-le/js/** -text. Issue 34.

    class.slp_avalon.php is NOT touched. Same md5 as v0.0.18.

    WHY THE GATE EXISTS. Places Autocomplete requests go from the browser
    straight to Google. They never reach WP Engine, so no WordPress rate
    limiter, WAF rule, nonce or page cache can see them - the only controls
    that exist are a Cloud Console daily quota and how many requests our own
    code lets the widget make. Constructing the widget is free; only queries
    bill. rev13 put locator traffic at roughly 2,500-2,650 views a month
    across the three live brands, with Autocomplete the largest SKU by share.

    KNOWN AND ACCEPTED. The widget does not query text already sitting in the
    field when it attaches, so predictions first appear on the keystroke AFTER
    the threshold is crossed - at 3, from the fourth character. Lowering
    avalon_autocomplete_min_chars to 2 restores them at the third and costs
    one more request per visitor. That is a one-token change precisely because
    decision 65 put the number in our own file.

    FIVE differences from Publish-Step14.ps1, all deliberate.

    1. THE RELEASE IS JAVASCRIPT, so node is fatal and php is not. Step14 had
       it the other way round. A missing php here downgrades the five PHP
       suites to a warning, because class.slp_avalon.php did not move and its
       md5 above already identifies it as the artefact that passed 174.

    2. The control score is 21/35, not a clean zero, and that is correct.
       suite-v019 carries both halves of the release: fourteen
       [DISCRIMINATOR] cases that must fail against v0.0.18 and twenty-one
       [GUARD] cases that must pass against both. A control that scored 0/35
       would mean the guards were broken.

    3. THE DISCRIMINATORS ARE TRANSITIONS, NOT ENDPOINTS. An assertion that
       one widget exists after the third character is true of v0.0.18 as well
       - it built one before a key was ever pressed. The suite asserts the
       sequence 0,0,1,1 instead. Seven cases were written as endpoints first
       and passed against the control; they were rewritten rather than
       relabelled. This is rev14 s8 inverted: there, nothing-happened
       assertions passed because the code was absent; here,
       something-happened assertions passed because the old code was eager.

    4. .gitattributes moves again, one release after it first moved. rev19
       s6.1: Publish-Step14 -Mode Verify was run inside a fresh clone for the
       first time and failed on store-locator-le/js/slp_core.js and
       slp_core.min.js. Both are stored LF, both were checked out CRLF under
       core.autocrlf=true, and converting the repo copies to CRLF reproduces
       the clone hashes exactly. The v0.0.18 file excluded store-locator-le on
       the grounds that upstream is never edited. Never EDIT and never PIN are
       different rules, and the moment those two md5s went into
       release-pins.csv the project took responsibility for their bytes.

    5. ISSUE 22 IS CLOSED, NOT FIXED. Decision 66. initial_results_returned
       and max_results_returned are both registered base-plugin options
       (slp_core.js:1720-1721) and the csl_ajax_onload latch at 1841 is the
       mechanism by which the first search of a page load reads one and every
       later search reads the other. That is the feature. The abandoned
       suite-v015.js asserted the opposite; its guard half is inherited by
       suite-v019 so a future release cannot quietly delete the setting, and
       its discriminator half is gone with it. The manual 6 to 3 change to
       Number To Show Initially, carried on the wave checklist since rev15,
       drops off with it - it only ever hid the symptom.

    CARRIED TO THE WAVE, not fixable here. rev18 s9 records that
    assets/js/googlelocation.js redefines globals slp_avalon.js owns and is
    still present on Aura LIVE, Tahoe DEV and Avalon DEV.
    initialize_autocomplete is a global. If the theme copy defines it too, the
    ungated version wins wherever it is enqueued later and this gate does
    nothing there. Grep those three before concluding the release works.

    Verified totals, measured not estimated:

        suite-v008   24        suite-v012.php   68   (class unchanged, re-run)
        suite-v009   13        suite-v015.php   33   (class unchanged, re-run)
        suite-v010   40        suite-v016.php   19   (class unchanged, re-run)
        suite-v011   15        suite-v017.php   22   (class unchanged, re-run)
        suite-v013   35        suite-v018.php   32   (class unchanged, re-run)
        suite-v014   34
        suite-v019   35        JS subtotal     196
                               PHP subtotal    174
                               TOTAL           370

        suite-v019 scores 21/35 against the v0.0.18 tag blob.

.PARAMETER Mode
    Stage   - assert the build output, copy it into the working tree, rewrite
              release-pins.csv, re-verify in place.
    Verify  - working tree, pin file, test files, full suite run, negative
              control. Changes nothing. The default.
    Commit  - git add the exact file set and commit.
    Tag     - annotated tag, then print the acceptance checklist.

.EXAMPLE
    python build\build-v019.py
    .\Publish-Step15.ps1 -Mode Stage
    .\Publish-Step15.ps1 -Mode Verify
    .\Publish-Step15.ps1 -Mode Commit
    .\Publish-Step15.ps1 -Mode Tag
    git push origin main --follow-tags

.NOTES
    Unblock-File .\Publish-Step15.ps1 before the first run. RemoteSigned
    blocks a downloaded script until it is unblocked.
#>

[CmdletBinding()]
param(
    [ValidateSet('Stage', 'Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Tag     = 'v0.0.19'
$PrevTag = 'v0.0.18'

# Expected working-tree state AFTER staging. Update these together with
# build-v019.py, never independently.
$Expected = @{
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = '528e14299d04fe073b0b970362dc6765'; Bytes = 70159; Crlf = 1655
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = 'd7bc69081de0d5789eaae30d715e4ac7'; Bytes = 1808;  Crlf = 59
    }
    # JS-only release on the plugin side. This one must NOT move. Same md5
    # since v0.0.18.
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = '4b1ee189381d0c111d0bc5c28c4b8822'; Bytes = 93499; Crlf = 2038
    }
}

# The v0.0.18 slp_avalon.js: what Stage expects to find in place before it
# copies, and what the negative control extracts from the tag afterwards.
# Unchanged from v0.0.14 through v0.0.18 - five releases on one hash.
$PrevJsMd5 = '8c93719e41af3232c18773a104e8dedd'

# What build-v019.py writes. Same hashes; different location.
$BuildOutput = @{
    'build/out19/slp_avalon.js'  = $Expected['slp_avalon/assets/js/slp_avalon.js']
    'build/out19/slp_avalon.php' = $Expected['slp_avalon/slp_avalon.php']
}

# LF file with a trailing newline - md5 only. Difference 4 in .DESCRIPTION.
$BuildOutputMd5Only = @{
    'build/out19/.gitattributes' = 'd6f1b5bc350f43a202b6abb91daa36ed'
}

$StageMap = @{
    'build/out19/slp_avalon.js'  = 'slp_avalon/assets/js/slp_avalon.js'
    'build/out19/slp_avalon.php' = 'slp_avalon/slp_avalon.php'
    'build/out19/.gitattributes' = '.gitattributes'
}

# The files delivered this session. A stale or truncated copy of either is
# caught here rather than as an unexplained score forty lines down. This
# script is deliberately NOT in the table: a file cannot assert its own md5
# without the assertion changing the file. Its integrity is covered by the
# fresh-clone check, item 8 of the checklist.
$ToolFiles = @{
    'build/build-v019.py' = '51a73d4ff04abb8f1a1a9d9bd9cc95b5'
    'test/suite-v019.js'  = '2827226bc103415993fa31dd029d145f'
}

# store-locator-le/js/ belongs to the SLP plugin author and is asserted by md5
# only because byte and CRLF counts were never recorded for those two and
# inventing them would be worse than not checking. As of this release both are
# covered by a -text rule, which is what makes these two hashes survive a
# fresh clone at all - see Issue 34. .gitattributes sits in this table for a
# different reason and its hash MOVES this release.
$Upstream = @{
    'store-locator-le/js/slp_core.js'     = 'a751bea043c19472ec6453aff93f84a9'
    'store-locator-le/js/slp_core.min.js' = '7924dad949f851d90ade9118c8bd045a'
    '.gitattributes'                      = 'd6f1b5bc350f43a202b6abb91daa36ed'
}

# test/release-pins.csv, in file order. Paths are relative to the GitHub root
# (D:\Temp\Projects\GitHub) because Inventory-LocalGitHub.ps1 -PinFile runs
# from there, not from inside this repository. Backslashes are load-bearing.
$PinRelPath = 'test/release-pins.csv'
$PinRows = @(
    @{ Path = 'slp-plugins\slp_avalon\assets\js\slp_avalon.js'  ; Md5 = '528e14299d04fe073b0b970362dc6765' }
    @{ Path = 'slp-plugins\slp_avalon\inc\class.slp_avalon.php' ; Md5 = '4b1ee189381d0c111d0bc5c28c4b8822' }
    @{ Path = 'slp-plugins\slp_avalon\slp_avalon.php'           ; Md5 = 'd7bc69081de0d5789eaae30d715e4ac7' }
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
    'test/release-pins.csv',
    'build/build-v015.py', 'build/build-v016.py', 'build/build-v017.py',
    'build/build-v018.py', 'build/build-v019.py',
    'Verify-v016.ps1', 'Publish-Step11.ps1', 'Publish-Step12.ps1',
    'Publish-Step13.ps1', 'Publish-Step14.ps1'
)

# suite-v019.js joins the chain and the release IS the artefact they all read.
$JsSuites = @{
    'test/suite-v008.js' = 24; 'test/suite-v009.js' = 13
    'test/suite-v010.js' = 40; 'test/suite-v011.js' = 15
    'test/suite-v013.js' = 35; 'test/suite-v014.js' = 34
    'test/suite-v019.js' = 35
}

# test/suite-v015.js is NOT here and is NOT committed. It is the abandoned
# Issue 22 suite, untracked since v0.0.15 and referenced only by the reverted
# Publish-Step10.ps1. Decision 66 closed the issue it was written for; its
# guard cases are inherited by suite-v019 and the file itself should be
# deleted from the working tree, not committed. rev19 s6.2.
$CommitFiles = @(
    'slp_avalon/assets/js/slp_avalon.js',
    'slp_avalon/slp_avalon.php',
    '.gitattributes',
    'build/build-v019.py',
    'test/suite-v019.js',
    'test/release-pins.csv',
    'Publish-Step15.ps1'
)

$TagMessage = @'
v0.0.19: gate Google Places Autocomplete behind a character threshold, and
stop git converting the two pinned upstream JS files

THE GATE - Issue 35, slp_avalon.js

google.maps.places.Autocomplete bills per request, and its requests go from
the browser straight to Google. They never reach WP Engine, so no WordPress
rate limiter, WAF rule, nonce or page cache can see or shape them. The only
controls that exist are a Cloud Console daily quota and how many requests our
own code lets the widget make.

The widget queried on every keystroke from the first. With
address_autocomplete = zipcode a visitor types a five-digit ZIP and the
opening characters cannot match anything they want, so those requests were
billed and wasted.

initialize_autocomplete() now registers a delegated, namespaced input listener
on #addressInput and constructs the widget only once the trimmed value reaches
avalon_autocomplete_min_chars, then unbinds itself. A field already seeded by
the URL bootstrap attaches at once, because it did not get there by typing.
avalon_attach_autocomplete() is idempotent, and returns without constructing
when Google Maps never loaded.

    before   5 keystrokes -> 5 requests
    after    5 keystrokes -> 2 requests

Predictions now appear on the fourth character rather than the third: the
widget does not query text already in the field when it attaches. Set the
constant to 2 to trade one request per visitor for that character back.

The middle of the old function - setFields, the place_changed listener, the
place_country write that closes the v0.0.6 autocomplete bypass - is in neither
build anchor and did not move. build-v019.py asserts that separately.

DECISIONS TAKEN THIS RELEASE

  65. The threshold is a constant in slp_avalon.js, NOT
      slplus.options.address_autocomplete_min. That option exists and reads 3
      but drives SLP own jQuery-UI zip suggester; inheriting it would move
      this gate silently whenever someone tuned the suggester. A wp-config.php
      constant was the third option and was rejected because JS cannot read
      one without a localize step, which would pull class.slp_avalon.php into
      a single-file release.
  66. Issue 22 is CLOSED, working as designed. initial_results_returned and
      max_results_returned are both registered base-plugin options
      (slp_core.js:1720-1721) and the csl_ajax_onload latch at 1841 is how the
      first search of a page load reads one and later searches read the other.
      Fixing the latch would have deleted a documented setting. The guard half
      of the abandoned suite-v015.js is inherited by suite-v019 so that a
      later release cannot do it by accident; the discriminator half is gone.
      The manual 6 to 3 change to Number To Show Initially drops off the wave
      checklist with it.

ISSUE 34 - store-locator-le/js/** -text

Publish-Step14 -Mode Verify was run inside a fresh clone for the first time
and failed on exactly two files: store-locator-le/js/slp_core.js and
slp_core.min.js. Both are stored LF, both were checked out CRLF under
core.autocrlf=true, and converting the repo copies to CRLF reproduces the
clone hashes exactly. The v0.0.18 rules excluded store-locator-le because
upstream is never edited. Never EDIT and never PIN are different rules: the
moment those two md5s went into release-pins.csv the project took
responsibility for their bytes, and telling git not to convert a file is not
an edit to it. The pins stay - slp_avalon.js cites slp_core.js lines 1720,
1808 and 1842 by number and an SLP auto-update moving them is what the pins
exist to catch.

ONE-TIME HAZARD: with -text there is no normalisation, so any of those files
sitting in a working tree as CRLF now reports as modified. Fix with
git checkout -- <paths>. Never git add them and never git add --renormalize.

  slp_avalon.js         528e14299d04fe073b0b970362dc6765  70,159 B  1,655 CRLF
  slp_avalon.php        d7bc69081de0d5789eaae30d715e4ac7   1,808 B     59 CRLF
  .gitattributes        d6f1b5bc350f43a202b6abb91daa36ed   1,353 B     LF
  class.slp_avalon.php  4b1ee189381d0c111d0bc5c28c4b8822  UNCHANGED since v0.0.18

suite-v019.js 35/35 against the build, 21/35 against the v0.0.18 tag - all
fourteen discriminators fail, all twenty-one guards hold. Full chain 370:
v008 24, v009 13, v010 40, v011 15, v013 35, v014 34, v019 35, v012 68,
v015 33, v016 19, v017 22, v018 32.

NOT in this release: the rewrite-rule self-healing guard (Issue 32) and the
Issue 31 orphan cleanup, both v0.0.20; the Google key restructuring, still one
unrestricted key across six sites and still in page source as slplus.apikey;
and the geocode route, guarded only by a forgeable Referer prefix match with
no rate limit in front of a billed API.

CARRIED TO THE WAVE: assets/js/googlelocation.js redefines globals
slp_avalon.js owns and is still present on Aura LIVE, Tahoe DEV and Avalon
DEV. initialize_autocomplete is a global. Grep those three for it before
concluding this gate does anything there.
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
    # build's printout. v0.0.17 moved bytes without moving lines; this release
    # moves both on the JS. Checking them separately is what tells a line-ending accident
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

    # The files this release moves must be pinned to the NEW hashes. A pin
    # file that still reads v0.0.18 would agree with a tree that was never
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
    Write-Host 'One of the two files from this session is missing or stale.' -ForegroundColor Red
    Write-Host 'A truncated suite-v019.js surfaces as a mystery score four screens' -ForegroundColor Red
    Write-Host 'down; a stale build-v019.py writes an artefact that fails its own pins.' -ForegroundColor Red
    exit 1
}

# -------------------------------------------------------------------- stage

if ($Mode -eq 'Stage') {

    # Establish what we are staging ONTO. Staging v0.0.19 over something that
    # is neither v0.0.18 nor v0.0.19 means the tree is in an unknown state and
    # the copy would erase the evidence of how it got there.
    #
    # The baseline file is slp_avalon.js this release, not the class file:
    # this is the artefact that moves, so it is the one whose prior state has
    # to be known. Note that 8c93719e... has been the answer since v0.0.14,
    # so this check confirms only that the tree is un-staged, not which of the
    # five releases it came from.
    Write-Host ''
    Write-Host 'Base state' -ForegroundColor Cyan
    $jsRel  = 'slp_avalon/assets/js/slp_avalon.js'
    $jsFull = Join-Path $PluginRepo $jsRel
    if (-not (Test-Path -LiteralPath $jsFull)) { throw "missing $jsRel" }
    $baseMd5 = (Get-FileHash -LiteralPath $jsFull -Algorithm MD5).Hash.ToLower()

    if ($baseMd5 -eq $PrevJsMd5) {
        Write-Host ("  ok    working tree holds {0}  {1}" -f $PrevTag, $baseMd5) -ForegroundColor Green
    } elseif ($baseMd5 -eq $Expected[$jsRel].Md5) {
        Write-Host ("  note  working tree ALREADY holds {0}. Re-staging is idempotent." -f $Tag) -ForegroundColor Yellow
    } else {
        Write-Host ("  FAIL  working tree slp_avalon.js is {0}" -f $baseMd5) -ForegroundColor Red
        Write-Host ("          expected {0} ({1}) or {2} ({3})" -f `
            $PrevJsMd5, $PrevTag, $Expected[$jsRel].Md5, $Tag) -ForegroundColor Red
        Write-Host '          The tree is in an unknown state. Do not stage over it.' -ForegroundColor Red
        exit 1
    }

    Write-Host ''
    Write-Host 'Build output' -ForegroundColor Cyan
    $ok = $true
    foreach ($rel in ($BuildOutput.Keys | Sort-Object)) {
        if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $BuildOutput[$rel])) { $ok = $false }
    }
    # .gitattributes is an LF file WITH a trailing newline, so Assert-File -
    # which requires CR = LF and refuses a trailing newline - is the wrong
    # instrument for it. md5 only, and build-v019.py asserts the LF-ness and
    # the trailing newline at build time where the bytes are still in hand.
    foreach ($rel in ($BuildOutputMd5Only.Keys | Sort-Object)) {
        if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel -Want $BuildOutputMd5Only[$rel])) { $ok = $false }
    }
    if (-not $ok) {
        Write-Host ''
        Write-Host 'Build output does not match. Re-run: python build\build-v019.py' -ForegroundColor Red
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
    Write-Host 'Staged. Now run: .\Publish-Step14.ps1 -Mode Verify' -ForegroundColor Green
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

# build/out*/ went into .gitignore with v0.0.15 and covers build/out19. If it
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
$phpLoader  = Join-Path $PluginRepo 'slp_avalon/slp_avalon.php'
$newClass   = Join-Path $PluginRepo 'slp_avalon/inc/class.slp_avalon.php'

# Pinned to the TAG, not HEAD and not the working tree, for the reason Step12
# recorded: the control must survive staging and commit so that -Mode Tag can
# still run it.
$oldJsRef = "${PrevTag}:slp_avalon/assets/js/slp_avalon.js"
$grand    = 0

Push-Location $PluginRepo
try {
    # --- JS. This release IS the JavaScript, so a missing node is fatal.
    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) {
        Write-Host '  FAIL  node not on PATH. v0.0.19 IS the JavaScript - nothing can be' -ForegroundColor Red
        Write-Host '        verified on this machine. Install Node and re-run.' -ForegroundColor Red
        $allOk = $false
    } else {
        & node --check $jsArtefact
        if ($LASTEXITCODE -ne 0) {
            throw "node --check failed on slp_avalon/assets/js/slp_avalon.js (exit $LASTEXITCODE)"
        }
        Write-Host '  ok    node --check on slp_avalon.js' -ForegroundColor Green

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

        # --- NEGATIVE CONTROL. Extract slp_avalon.js from the v0.0.18 tag and
        # confirm suite-v019 scores EXACTLY 21/35 against it.
        #
        # 21 is not a slack figure. suite-v019 carries fourteen
        # [DISCRIMINATOR] cases, which must all fail because v0.0.18 has no
        # gate, and twenty-one [GUARD] cases - the decision 66 latch
        # behaviour, the bootstrap, the C1 rejection - which must all hold on
        # both builds. 35/35 means the suite is not testing this release.
        # Under 21 means a guard broke, which is a regression wearing the
        # costume of a good control. Either way: do not commit.
        $tmpOldJs = Join-Path ([System.IO.Path]::GetTempPath()) 'slp-v018-negctl.js'
        Remove-Item -LiteralPath $tmpOldJs -ErrorAction SilentlyContinue
        # cmd redirection rather than a PowerShell pipe: piping git output
        # through PowerShell re-encodes it, and -Encoding Byte was removed in
        # PowerShell 7. This is byte-faithful on both.
        & cmd /c "git show $oldJsRef > `"$tmpOldJs`""
        if (-not (Test-Path -LiteralPath $tmpOldJs)) {
            throw "could not extract $oldJsRef for the negative control"
        }
        $ctlMd5 = (Get-FileHash -LiteralPath $tmpOldJs -Algorithm MD5).Hash.ToLower()
        if ($ctlMd5 -ne $PrevJsMd5) {
            Write-Host ("  FAIL  control target extracted as {0}, expected {1}" -f $ctlMd5, $PrevJsMd5) -ForegroundColor Red
            Write-Host '        git show returned something other than the v0.0.18 slp_avalon.js.' -ForegroundColor Red
            $allOk = $false
        } else {
            Write-Host ("  ok    control target {0} = {1}" -f $oldJsRef, $ctlMd5) -ForegroundColor Green
            $ctlOut  = & node 'test/suite-v019.js' $tmpOldJs 2>&1
            $ctlLine = ($ctlOut | Select-String 'PASS' | Select-Object -Last 1)
            $ctlDisc = @($ctlOut | Select-String 'FAIL  \[DISCRIMINATOR\]').Count
            $ctlGuard = @($ctlOut | Select-String 'FAIL  \[GUARD\]').Count
            if ("$ctlLine" -match '(\d+)/(\d+) PASS' `
                -and $Matches[1] -eq '21' -and $Matches[2] -eq '35' `
                -and $ctlDisc -eq 14 -and $ctlGuard -eq 0) {
                Write-Host '  ok    NEGATIVE CONTROL vs v0.0.18  21/35, 14 discriminators fail, 0 guards' -ForegroundColor Green
            } else {
                Write-Host ("  FAIL  NEGATIVE CONTROL vs v0.0.18: {0}" -f $ctlLine) -ForegroundColor Red
                Write-Host ("        discriminators failing {0} (want 14), guards failing {1} (want 0)" -f `
                    $ctlDisc, $ctlGuard) -ForegroundColor Red
                Write-Host '        35/35 means the suite is not testing this release.' -ForegroundColor Red
                Write-Host '        A failing GUARD means a regression, not a good control.' -ForegroundColor Red
                Write-Host '        Either way: do not commit.' -ForegroundColor Red
                $allOk = $false
            }
        }
        Remove-Item -LiteralPath $tmpOldJs -ErrorAction SilentlyContinue
    }

    # --- PHP. class.slp_avalon.php does NOT move this release. These are pure
    # regression against an unchanged file, which is why a missing interpreter
    # is a warning here and was fatal in Step14. The loader DOES move, so it
    # is linted either way when php is present.
    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) {
        Write-Host '  WARN  php not on PATH - the five PHP suites were NOT run.' -ForegroundColor Yellow
        Write-Host '        class.slp_avalon.php is unchanged this release and its md5' -ForegroundColor Yellow
        Write-Host '        above matches the artefact that passed 174, so the file is' -ForegroundColor Yellow
        Write-Host '        right; nothing was re-proved here. slp_avalon.php DID move' -ForegroundColor Yellow
        Write-Host '        and was not linted - the server lint in item 3 is mandatory.' -ForegroundColor Yellow
    } else {
        $lintOk = $true
        foreach ($target in @($newClass, $phpLoader)) {
            & php -l $target | Out-Null
            if ($LASTEXITCODE -ne 0) {
                Write-Host ("  FAIL  php -l on {0}" -f (Split-Path $target -Leaf)) -ForegroundColor Red
                $lintOk = $false
                $allOk = $false
            }
        }
        if ($lintOk) {
            $phpVer = ((& php -r "echo PHP_VERSION;" 2>&1) -join '').Trim()
            Write-Host ('  ok    php -l on both  (local {0}; server lint on 8.4 is still required)' -f `
                $phpVer) -ForegroundColor Green
        }

        # No PHP negative control this release. suite-v018.php discriminates
        # v0.0.18 from v0.0.17, and both sides of that comparison are behind
        # us - pointing it at the v0.0.18 tag would score 32/32 and prove
        # nothing. The control that matters here is the JS one above.
        if (Invoke-PhpSuite -SuiteRel 'test/suite-v018.php' -ArtefactPath $newClass `
                -ExpectPass 32 -ExpectTotal 32 -Label 'suite-v018.php regression') { $grand += 32 }
        else { $allOk = $false }

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v017.php' -ArtefactPath $newClass `
                -ExpectPass 22 -ExpectTotal 22 -Label 'suite-v017.php regression') { $grand += 22 }
        else { $allOk = $false }

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v016.php' -ArtefactPath $newClass `
                -ExpectPass 19 -ExpectTotal 19 -Label 'suite-v016.php regression') { $grand += 19 }
        else { $allOk = $false }

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v015.php' -ArtefactPath $newClass `
                -ExpectPass 33 -ExpectTotal 33 -Label 'suite-v015.php regression') { $grand += 33 }
        else { $allOk = $false }

        if (Invoke-PhpSuite -SuiteRel 'test/suite-v012.php' -ArtefactPath $newClass `
                -ExpectPass 68 -ExpectTotal 68 -Label 'suite-v012.php regression') { $grand += 68 }
        else { $allOk = $false }
    }
}
finally { Pop-Location }

Write-Host ''
Write-Host ("  total assertions: {0}  (expected 370 with node + php present)" -f $grand)

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
    Write-Host 'PART A - the deploy. Complete before leaving the machine.' -ForegroundColor Yellow
    @(
        '1.  SFTP in BINARY mode. TWO files deploy:'
        '      build\out19\slp_avalon.js   ->  slp_avalon/assets/js/'
        '      build\out19\slp_avalon.php  ->  slp_avalon/'
        '    .gitattributes is a REPO file. It does not deploy anywhere. Step14'
        '    item 1 said three files and then listed two; this is that corrected.'
        '2.  SSH: md5sum both. Must read 528e1429... (70159) and d7bc6908... (1808).'
        '    The md5 on the server is the only statement about what is running.'
        '      cd /nas/content/live/aurapontoonstg/wp-content/plugins/slp_avalon'
        '      md5sum assets/js/slp_avalon.js slp_avalon.php'
        '3.  SSH: php -l slp_avalon.php, on the server, on PHP 8.4. The class file'
        '    did not move this release and does not need re-linting.'
        '4.  PURGE THE WP ENGINE CACHE and hard-reload. This is a JS release and the'
        '    first since v0.0.14. A stale slp_avalon.js in the CDN will make every'
        '    check in Part B report the OLD behaviour, which reads as a failed'
        '    deploy rather than a cached one.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'PART B - THE ACCEPTANCE TEST. The browser is the only proof.' -ForegroundColor Yellow
    @(
        'A green suite run shows the artefact behaves in a vm with a stubbed jQuery'
        'and a stubbed google.maps. It does not show that the real widget attached'
        'to the real field on the real page. Only the network panel settles that.'
        ''
        '5.  THE GATE, MEASURED. /find-a-dealer/, DevTools open, Network tab'
        '    filtered to  places.googleapis.com  or  maps.googleapis.com, then'
        '    clear it. Click into the address field and type a five-digit ZIP one'
        '    character at a time.'
        ''
        '      BEFORE this release: a request on each of the 5 keystrokes.'
        '      AFTER  this release: NOTHING on keystrokes 1, 2 and 3, then a'
        '                           request on 4 and on 5. Two, not five.'
        ''
        '    Nothing at all across five keystrokes is a FAILURE, not a success:'
        '    it means the widget never attached and the field has no autocomplete.'
        '    Confirm the dropdown appears from the fourth character.'
        ''
        '6.  THE ONE-CHARACTER LAG IS EXPECTED. Predictions start at character 4,'
        '    not 3, because the widget does not query text already in the field'
        '    when it attaches. If that reads as sluggish in practice, change'
        '    avalon_autocomplete_min_chars to 2 and rebuild - it is one token and'
        '    it costs one more request per visitor. Do not patch it on the server.'
        ''
        '7.  SELECTING A PREDICTION STILL SEARCHES. Type enough to get a dropdown,'
        '    pick an entry, and confirm the form submits and returns dealers. That'
        '    path is the v0.0.6 autocomplete bypass fix - place_lat, place_lng and'
        '    place_country are written to the field and carried into Layer 1. The'
        '    build asserts those lines did not move, but only this proves they run.'
        ''
        '8.  A SEEDED FIELD STILL WORKS. Load a URL carrying place_address, for'
        '    example  /find-a-dealer/?place_address=48843  and confirm the search'
        '    fires on load as it always has, AND that editing the field afterwards'
        '    still offers predictions. That is the branch that attaches at once'
        '    because the field did not get its value from typing.'
        ''
        '9.  DECISION 66 STILL HOLDS. Load the page and let the first search run,'
        '    then search again for the same place. The first returns Number To Show'
        '    Initially and the second returns the max-results count. THEY ARE'
        '    SUPPOSED TO DIFFER. That is Issue 22, closed as working-as-designed,'
        '    and suite-v019 carries twenty-one guards to keep it that way.'
        ''
        '10. THE FRONT END IS OTHERWISE UNCHANGED. Known ZIP returns its usual'
        '    count, the radius circle draws, the sidebar populates, browser console'
        '    clean. slp_core.js still calls options/<slug> and'
        '    options/filtered/<slug> and both still return HTTP 500 - upstream,'
        '    unchanged by this release, and NOT a regression.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'PART C - the repo, once. Then never again.' -ForegroundColor Yellow
    @(
        '11. ISSUE 34 ONLY PROVES ITSELF ON A FRESH CLONE. This is the check that'
        '    failed on its first outing last release. In a scratch directory, not'
        '    over the working repo:'
        '      git clone https://github.com/ComputerPomush/slp-plugins.git fresh19'
        '      cd fresh19'
        '      git checkout v0.0.19'
        '      .\Publish-Step15.ps1 -Mode Verify -PluginRepo .'
        '    store-locator-le/js/slp_core.js and slp_core.min.js must now match'
        '    a751bea0... and 7924dad9... in the clone. Before this release both'
        '    came out CRLF and both hashes were wrong.'
        '12. ONE-TIME: the -text rules mean git no longer normalises these paths,'
        '    so a working copy holding any of them as CRLF now reports as modified.'
        '    If git status shows store-locator-le/js/ files as changed:'
        '      git checkout -- store-locator-le/js/'
        '    NEVER git add them and NEVER git add --renormalize. Either stores the'
        '    CRLF bytes verbatim and changes the fresh-clone hash of both pins.'
        '13. Byte identity of the committed blobs, because the autocrlf filter can'
        '    rewrite on the way in:'
        '      git hash-object --no-filters slp_avalon/assets/js/slp_avalon.js'
        '      git rev-parse HEAD:slp_avalon/assets/js/slp_avalon.js'
        '    The two must agree. Same for .gitattributes.'
        '14. DELETE test/suite-v015.js from the working tree. It is untracked, it'
        '    is the abandoned Issue 22 suite, and decision 66 closed the issue it'
        '    was written for. Its guard cases now live in suite-v019. rev19 s6.2'
        '    warned that an untracked file in test/ is either a release artefact'
        '    or a decision nobody has made - this is the decision.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'STILL OPEN after this release - do not mistake it for closed:' -ForegroundColor Cyan
    @(
        '  - THE GATE MAY DO NOTHING ON THREE ENVIRONMENTS. googlelocation.js in the'
        '    child theme redefines globals slp_avalon.js owns and is still present on'
        '    Aura LIVE, Tahoe DEV and Avalon DEV. initialize_autocomplete is a'
        '    global. Grep those three for it during the decision 60 visit; if the'
        '    theme copy defines it, whichever enqueues later wins.'
        '  - The Google key is still one unrestricted key across six sites, still in'
        '    page source as slplus.apikey. This release reduces how often the'
        '    browser spends it; it does not restrict or rotate it. The daily quota'
        '    in Cloud Console remains the only control that cannot be bypassed.'
        '  - Issue 32, the rewrite-rule self-healing guard, and Issue 31, the orphan'
        '    store pages. Both v0.0.20. Every dealer-name link in the results panel'
        '    is still a 404 on Aura DEV and Aura LIVE.'
        '  - The geocode route is guarded by a prefix match on a client-supplied'
        '    Referer header, with no rate limit, and it bills the key.'
        '  - The upstream feed alignment bug. Tier 1 writes 11 and Tier 2 writes 17'
        '    every night, permanently.'
    ) | ForEach-Object { Write-Host "  $_" }

    Write-Host ''
    Write-Host 'If anything in Part B looks wrong, the rollback is a file copy:' -ForegroundColor Cyan
    Write-Host '  git show v0.0.18:slp_avalon/assets/js/slp_avalon.js, SFTP it back,'
    Write-Host '  confirm md5 8c93719e41af3232c18773a104e8dedd, purge the cache. There'
    Write-Host '  is no database state and no option to unwind - this release writes'
    Write-Host '  nothing and reads nothing but the contents of a text field.'
    Write-Host ''
    Write-Host 'Promotion stays FROZEN per decision 57. v0.0.20 comes first.' -ForegroundColor Yellow
    Write-Host ''
    exit 0
}
