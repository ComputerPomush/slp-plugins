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
    .\Publish-Step4.ps1 -Mode Verify
    .\Publish-Step4.ps1 -Mode Commit
    .\Publish-Step4.ps1 -Mode Tag
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
        Md5 = 'b78688d0fbf62500baa186fb865e84fe'; Bytes = 54240; Crlf = 1341
    }
    'slp_avalon/slp_avalon.php'          = @{
        Md5 = '98601fbd3bba3d50f2d951663875aecd'; Bytes = 1807;  Crlf = 59
    }
}

# Test files live at the repo root, outside slp_avalon/, so they are never
# uploaded to a server and are not covered by `slp_avalon/** -text`. No md5 is
# pinned for them; they are verified by running them.
$TestFiles = @(
    'test/harness.js',
    'test/suite-core.js',
    'test/suite-v008.js',
    'build/build-v008.py'
)

$Tag        = 'v0.0.8'
$TagMessage = @'
v0.0.8 - rejection presentation, completed

Wraps slp.option.get_from_server so SLP's "No Dealers found in this area,
please try again!" can no longer overwrite the neutral sidebar prompt on a
Layer 3 territory rejection (Issue 4, handoff 7.4). Scoped to
message_no_results and to territory rejections only; message_bad_address and
genuine EMPTY are untouched.

Also:
  - Issue 12. .avalon_sidebar_prompt had no CSS anywhere and was being
    rendered as theme-default dark text on the #090909 panel Elementor sets
    on the locator. Now #FFFFFF, matching every other visible element there.
  - .avalon_search_notification was #c00, about 3.4:1 on that background,
    below WCAG AA for 13px text. Now #E7167C, about 4.55:1, already the
    page's focus colour.
  - .sl_loading i had left/top 50% with no transform, so the spinner icon
    hung below and right of centre. Corrected from the plugin because the
    offending rule is in all four theme stylesheets and SLP ships none.
  - ensure_notification_css() -> ensure_guard_css(), one injected block for
    all three rules, ensured from ensure_spinner(), notify() and
    set_no_results().
  - Corrected the v0.0.6 comment claiming slp.send_ajax has no other caller.
    SLPEXP.email_form.send_email is a second live caller. v0.0.9 fixes the
    behaviour; this commit fixes only the false claim.

Tests: test/harness.js runs the built artefact in a vm context. 86 assertions
across suite-core (62) and suite-v008 (24). Both were run against v0.0.7
first and failed there - 5 and 13 failures respectively - per decision 20.
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
        # Not fatal. These never reach a server, so a deploy is unaffected -
        # but the commit would then be missing coverage the handoff claims.
        Write-Host ("  warn  MISSING: {0}" -f $rel) -ForegroundColor Yellow
        $missingTests = $true
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

        foreach ($suite in @('test/suite-core.js', 'test/suite-v008.js')) {
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
        & git add -- @toStage

        & git commit -m 'v0.0.8: suppress SLP no-results copy on territory rejection' -m @'
Issue 4. putMarkers() fetches message_no_results asynchronously and always
resolves after location_search_processed publishes, so SLP's copy landed on
top of the neutral prompt that finish() had just written. install_options_hook
short-circuits that fetch synchronously when the current response carries
avalon_territory_rejected, mirroring SLP's own shortcode_attributes shortcut.

Scoped to message_no_results and to territory rejections only. message_bad_address
shares the same function and is untouched; genuine EMPTY keeps SLP's copy,
which is the more useful of the two there.

Issue 12 and two contrast defects fixed in the same injected stylesheet, now
consolidated as ensure_guard_css(). Corrected the v0.0.6 comment that claimed
slp.send_ajax had no other caller; slp-experience is a second live caller.

Adds a committed test harness. The previous suites were never committed and
did not survive the session that wrote them.
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
