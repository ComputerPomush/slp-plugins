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
        Md5 = 'a42b762902ebaa01ae242483d0aa8d1e'; Bytes = 56727; Crlf = 1395
    }
    'slp_avalon/slp_avalon.php'          = @{
        Md5 = '567dbaa2fc9d9d5d5ac7fe226431a7ee'; Bytes = 1807;  Crlf = 59
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
    'build/build-v008.py',
    'build/build-v009.py'
)

$Tag        = 'v0.0.9'
$TagMessage = @'
v0.0.9 - spinner centring corrective

v0.0.8 tried to centre the spinner icon with
`#sl_loading_indicator i { transform: translate(-50%, -50%) }`. That rule
never applied. Font Awesome 5.15.3, loaded on this page by Elementor,
declares `.fa-spin{animation:fa-spin 2s linear infinite}` with keyframes that
set `transform: rotate(...)`, and a running animation outranks an author
normal declaration, so the translate was discarded every frame.

Confirmed on Aura DEV at a 1920 viewport: the icon's top-left sat at exactly
338+790 / 115.8+469.6, i.e. the scrim's centre, with no transform in effect.

The visible complaint was not the missing 23px anyway. The scrim covers all
of #sl_div - search column, results panel and map - so its centre lands near
the map's LEFT EDGE:

  scrim  #sl_div   338..1918   centre x 1128
  map    #map_box  907..1918   centre x 1412

center_spinner() now measures both at show time and puts the icon on the
map's centre, in left/top rather than transform. Falls back to the scrim when
the map is absent or zero-sized, which covers the page-load bootstrap and the
stacked layout at <=768px. Net shift on Aura DEV: +261px x, -24px y.

The broken CSS rule is removed rather than left in place, with a comment
saying why, so it is not re-attempted.

Tests: 100 assertions across suite-core (63), suite-v008 (24) and
suite-v009 (13). suite-v009 was run against v0.0.8 first and failed 8 of 13,
and suite-core failed 2 of 63, per decision 20.
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

        foreach ($suite in @('test/suite-core.js', 'test/suite-v008.js', 'test/suite-v009.js')) {
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

        & git commit -m 'v0.0.9: centre the spinner on the map, in left/top not transform' -m @'
v0.0.8 shipped `transform: translate(-50%,-50%)` on the spinner icon. .fa-spin
animates transform, and an animation beats an author normal declaration, so the
rule was discarded on every frame. Verified live: the icon's top-left sat at the
scrim's exact centre.

Centring on the scrim was not what was wanted either. The scrim covers the
search column as well as the map, so its centre falls near the map's left edge -
about 260px off. center_spinner() measures #map_box and #sl_loading_indicator at
show time and sets left/top, falling back to the scrim when the map has no size.

Removes the dead CSS rule and records why it cannot work.
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
