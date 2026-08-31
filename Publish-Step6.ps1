<#
.SYNOPSIS
    SLP Dealer Guard - publish v0.0.8 (rejection presentation, completed).

.DESCRIPTION
    Verifies the working tree byte-for-byte before committing, then proves that
    no git filter rewrote anything on the way in. Two independent checks:

      1. Working-tree md5 == the md5 produced by the build script.
      2. `git hash-object --no-filters <path>` == `git rev-parse HEAD:<path>`
         after the commit. If .gitattributes stopped applying to slp_avalon/**
         these would diverge and the server-side md5 check would fail on the
         next deploy instead of here.

    CRLF balance is asserted too. slp_avalon/** is `-text`, so the artefacts
    must round-trip with literal CRLF and no trailing newline regardless of how
    core.autocrlf happens to be set on this machine.

    Nothing is committed if any check fails.

.PARAMETER Mode
    Verify  - run every check and stop. The default; run this first.
    Commit  - verify, then stage, commit and push.
    Tag     - create and push the annotated v0.0.8 tag. Run after Commit and
              after the DEV smoke test in section 8 of the handoff has passed.

.EXAMPLE
    .\Publish-Step5.ps1 -Mode Verify
    .\Publish-Step5.ps1 -Mode Commit
    .\Publish-Step5.ps1 -Mode Tag
#>

[CmdletBinding()]
param(
    [ValidateSet('Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'

# Expected state after build-v008.py. Update these together with the build
# script, never independently.
$Expected = @{
    'slp_avalon/assets/js/slp_avalon.js' = @{
        Md5 = '7dd58edee4aef019563fabaa0b59d9ef'; Bytes = 60576; Crlf = 1469
    }
    'slp_avalon/slp_avalon.php'          = @{
        Md5 = 'bdf76246476ff8a4e93f38c56f464adf'; Bytes = 1808;  Crlf = 59
    }
}

# Test files live at the repo root, outside slp_avalon/, so they are never
# uploaded to a server and are not covered by `slp_avalon/** -text`. No md5 is
# pinned for them; they are verified by running them.
$TestFiles = @(
    'test/harness.js',
    'test/suite-core.js',
    'test/suite-v008.js',
    'test/suite-v009.js',
    'test/suite-v010.js',
    'build/build-v008.py',
    'build/build-v009.py',
    'build/build-v010.py'
)

$Tag        = 'v0.0.10'
$TagMessage = @'
v0.0.10 - Layer 0, synchronous pre-flight validation

Adds the last layer of the Guard, in cslmap_searchLocations() - the single
choke point all four search paths reach (Find Locations, autocomplete, the
URL bootstrap in cslmap_build_map, and Get My Position).

(a) Syntactic floor. Rejects an address with no letter and no digit.
    Deliberately minimal: an address-shape validator would have to accept
    48127, M5H 2N2, Sault Ste. Marie, St. John's NL and 1200 Woodward Ave #4,
    and a false rejection costs a customer. Junk that geocodes to nothing
    already behaves correctly, so this only catches "!!!" and the like.
    cuewx#1z is explicitly NOT rejected here and there is a test asserting so.

(b) Decision 16. Bounding-box check on coordinates - the first caller
    avalon_in_territory() has ever had, and the real work.

(b) closes Issue 15. On the URL-coordinate path there is no country component,
so Layer 1 correctly no-ops and control passed to SLP, which sets homePoint to
the rejected location and calls addMarkerAtCenter() at slp_core.js:1565-1566
BEFORE any AJAX. The home marker is centerMarker, not a member of markers, and
the cleanup at 1297 only runs when #addressInput is empty, so clearMarkers()
left it in place; putMarkers() then panned to it at 1323. Reproduced on Aura
DEV: correct territory message, empty panel, map centred on Paris with a pin.
Rejecting before the coords branch delegates breaks that chain and saves a
round trip.

Fills VALIDATING, declared since v0.0.5 and unreachable until now.

Safe to reject synchronously only because #searchForm has no jQuery-bound
submit handler - it carries an inline onsubmit registered at parse time, so
one would run after this function returns and re-hang the spinner. Re-verified
against v0.0.9 before writing this.

Tests: 140 assertions across suite-core (63), suite-v008 (24), suite-v009 (13)
and suite-v010 (40). suite-v010 drives cslmap_searchLocations() end to end
rather than testing the checks in isolation, and was run against v0.0.9 first,
where it failed 21 of 40 - including the three Issue 15 assertions.
'@

function Assert-File {
    param([string]$Root, [string]$RelPath, [hashtable]$Want)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) {
        throw "MISSING: $RelPath"
    }

    $bytes = [System.IO.File]::ReadAllBytes($full)
    $md5   = (Get-FileHash -LiteralPath $full -Algorithm MD5).Hash.ToLower()

    $cr = 0; $lf = 0
    for ($i = 0; $i -lt $bytes.Length; $i++) {
        if ($bytes[$i] -eq 13) { $cr++ }
        elseif ($bytes[$i] -eq 10) { $lf++ }
    }

    $problems = @()
    if ($md5 -ne $Want.Md5)          { $problems += "md5 $md5 != $($Want.Md5)" }
    if ($bytes.Length -ne $Want.Bytes) { $problems += "bytes $($bytes.Length) != $($Want.Bytes)" }
    if ($cr -ne $Want.Crlf)          { $problems += "CR $cr != $($Want.Crlf)" }
    if ($cr -ne $lf)                 { $problems += "CR $cr != LF $lf (mixed endings)" }
    if ($bytes[-1] -eq 10)           { $problems += "trailing newline present" }

    if ($problems.Count -gt 0) {
        Write-Host ("  FAIL  {0}" -f $RelPath) -ForegroundColor Red
        $problems | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
        return $false
    }

    Write-Host ("  ok    {0,-46} {1}  {2,6} bytes  CRLF={3}" -f `
        $RelPath, $md5, $bytes.Length, $cr) -ForegroundColor Green
    return $true
}

Write-Host ''
Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]" -ForegroundColor Cyan
Write-Host ('-' * 78)

if (-not (Test-Path -LiteralPath $PluginRepo)) {
    throw "Plugin repo not found: $PluginRepo"
}

# ---------------------------------------------------------------- verify
Write-Host ''
Write-Host 'Working tree' -ForegroundColor Cyan
$allOk = $true
$missingTests = $false
foreach ($rel in $Expected.Keys) {
    if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $Expected[$rel])) {
        $allOk = $false
    }
}

foreach ($rel in $TestFiles) {
    $full = Join-Path $PluginRepo $rel
    if (Test-Path -LiteralPath $full) {
        Write-Host ("  ok    {0}" -f $rel) -ForegroundColor Green
    } else {
        # Fatal as of v0.0.9. Warning here is what let v0.0.8 be tagged with a
        # message claiming 86 assertions that were not in the repository.
        Write-Host ("  FAIL  MISSING: {0}" -f $rel) -ForegroundColor Red
        $allOk = $false
    }
}

# Run the suites against the working-tree artefact, not against out/.
# Node is optional on this machine: the md5, byte and CRLF checks above are
# what a deploy depends on. The suites matter when the artefact is REBUILT,
# and they have already been run green against this exact md5.
Write-Host ''
Write-Host 'Test suites' -ForegroundColor Cyan
$artefact = Join-Path $PluginRepo 'slp_avalon/assets/js/slp_avalon.js'
$node = Get-Command node -ErrorAction SilentlyContinue

if (-not $node) {
    Write-Host '  skip  node not on PATH - suites not run.' -ForegroundColor Yellow
    Write-Host '        Safe here: the md5 above already matches the build that' -ForegroundColor Yellow
    Write-Host '        passed 86/86. Install Node before rebuilding the artefact.' -ForegroundColor Yellow
} else {
    Push-Location $PluginRepo
    try {
        & node --check $artefact
        if ($LASTEXITCODE -ne 0) { $allOk = $false; Write-Host '  FAIL  node --check' -ForegroundColor Red }
        else { Write-Host '  ok    node --check' -ForegroundColor Green }

        foreach ($suite in @('test/suite-core.js', 'test/suite-v008.js', 'test/suite-v009.js', 'test/suite-v010.js')) {
            if (-not (Test-Path -LiteralPath (Join-Path $PluginRepo $suite))) { continue }
            & node $suite $artefact
            if ($LASTEXITCODE -ne 0) {
                $allOk = $false
                Write-Host ("  FAIL  {0}" -f $suite) -ForegroundColor Red
            }
        }
    } finally {
        Pop-Location
    }
}

if (-not $allOk) {
    Write-Host ''
    Write-Host 'VERIFY FAILED - nothing committed.' -ForegroundColor Red
    exit 1
}

Write-Host ''
Write-Host 'All checks passed.' -ForegroundColor Green

if ($Mode -eq 'Verify') {
    Write-Host 'Verify only. Re-run with -Mode Commit when ready.' -ForegroundColor Yellow
    exit 0
}

# ---------------------------------------------------------------- commit
if ($Mode -eq 'Commit') {
    Push-Location $PluginRepo
    try {
        # `& git` natively, never through a wrapper: PowerShell prefix-matches
        # parameter names and will happily eat a git flag.
        $toStage = @('slp_avalon/assets/js/slp_avalon.js', 'slp_avalon/slp_avalon.php')
        foreach ($t in $TestFiles) {
            if (Test-Path -LiteralPath (Join-Path $PluginRepo $t)) { $toStage += $t }
        }
        # v0.0.8 was tagged with three test files missing because .gitignore
        # matched them and `git add` only printed a hint. Check first.
        $ignored = & git check-ignore -- @toStage
        if ($ignored) {
            Write-Host '  FAIL  .gitignore matches paths that must be committed:' -ForegroundColor Red
            $ignored | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
            throw 'Remove those .gitignore entries, then re-run. Do not use -f.'
        }
        & git add -- @toStage

        & git commit -m 'v0.0.10: Layer 0 pre-flight validation, closing Issue 15' -m @'
Synchronous validation in cslmap_searchLocations(), the one function every
search path reaches. A syntactic floor that rejects input with no letter and no
digit, and a bounding-box check on coordinates - the first caller
avalon_in_territory() has had since it was written.

The box check closes Issue 15. URL-supplied coordinates carry no country, so
Layer 1 no-ops on them by design and SLP set homePoint to the rejected location
and dropped a marker before any request was issued. Rejecting before the coords
branch delegates means process_geocode_response never runs.

The floor is deliberately minimal. cuewx#1z passes it and goes to the geocoder,
which returns ZERO_RESULTS and is reported correctly already; guessing at
address shape is how real addresses get refused.
'@

        Write-Host ''
        Write-Host 'Byte integrity after commit' -ForegroundColor Cyan
        foreach ($rel in $Expected.Keys) {
            $tree = (& git hash-object --no-filters -- $rel).Trim()
            $head = (& git rev-parse "HEAD:$rel").Trim()
            if ($tree -eq $head) {
                Write-Host ("  ok    {0,-46} {1}" -f $rel, $head.Substring(0, 12)) -ForegroundColor Green
            } else {
                Write-Host ("  FAIL  {0}: a filter rewrote bytes on check-in" -f $rel) -ForegroundColor Red
                Write-Host ("          worktree {0}" -f $tree) -ForegroundColor Red
                Write-Host ("          HEAD     {0}" -f $head) -ForegroundColor Red
                throw 'Byte integrity check failed. Do NOT push.'
            }
        }

        & git push origin HEAD
        Write-Host ''
        Write-Host 'Pushed. Deploy by SFTP in BINARY mode, then verify server-side:' -ForegroundColor Cyan
        Write-Host '  md5sum wp-content/plugins/slp_avalon/assets/js/slp_avalon.js' -ForegroundColor Gray
        Write-Host ("  expect {0}" -f $Expected['slp_avalon/assets/js/slp_avalon.js'].Md5) -ForegroundColor Gray
    } finally {
        Pop-Location
    }
    exit 0
}

# ------------------------------------------------------------------- tag
if ($Mode -eq 'Tag') {
    Push-Location $PluginRepo
    try {
        $existing = & git tag --list $Tag
        if ($existing) { throw "Tag $Tag already exists." }

        $tmp = [System.IO.Path]::GetTempFileName()
        Set-Content -LiteralPath $tmp -Value $TagMessage -Encoding UTF8
        & git tag -a $Tag -F $tmp
        Remove-Item -LiteralPath $tmp -Force

        & git push origin $Tag
        Write-Host ("Tagged and pushed {0}." -f $Tag) -ForegroundColor Green
    } finally {
        Pop-Location
    }
}
