<#
.SYNOPSIS
    SLP Dealer Guard - publish v0.0.12 (state and province name search).

.DESCRIPTION
    Derived from Publish-Step7.ps1 with three changes:

      1. class.slp_avalon.php joins $Expected. This is the first version since
         Step 2 to touch PHP, so it is the first time that file's md5 moves and
         the first time it needs pinning.

      2. slp_avalon.js is pinned as UNCHANGED. v0.0.12 is PHP-only; if the JS
         md5 moves, something was rebuilt that should not have been.

      3. Native exit codes are asserted. Step7 called `git check-ignore` and
         `git commit` and read $LASTEXITCODE after neither, which is why its
         second -Mode Commit run printed "no changes added to commit" and then
         continued into the integrity check and the push, reporting success.
         Note check-ignore exits 1 when NOTHING matched, which is the good
         case, so it is allowed explicitly rather than treated as failure.

    Tests run in three tiers, all optional-if-absent but fatal-if-failing:
      node   - the five JS suites, unchanged, proving the artefact still parses
               and the Guard is untouched
      php    - suite-v012.php against the built class file
      probe  - probe-v012.ps1, run manually against DEV after deploy; NOT run
               here, because it tests the server rather than the working tree

.PARAMETER Mode
    Verify - run every check and stop. The default; run this first.
    Commit - verify, then stage, commit and push.
    Tag    - create and push the annotated v0.0.12 tag. Run after Commit, after
             the SFTP deploy, and after probe-v012.ps1 passes against DEV.

.EXAMPLE
    .\Publish-Step8.ps1 -Mode Verify
    .\Publish-Step8.ps1 -Mode Commit
    .\Publish-Step8.ps1 -Mode Tag
#>

[CmdletBinding()]
param(
    [ValidateSet('Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'

# Expected state after build-v012.py. Update these together with the build
# script, never independently.
$Expected = @{
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = 'f6a07b929ceed4de0d6bd5fa034eda6b'; Bytes = 61807; Crlf = 1353
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = 'aa3e5ba266959a413b4b2db4378167d0'; Bytes = 1808;  Crlf = 59
    }
    # PHP-only version. This one must NOT move.
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = 'a6237b4f2c006964710f4b5362437c66'; Bytes = 61761; Crlf = 1494
    }
}

$TestFiles = @(
    'test/harness.js',
    'test/suite-core.js',
    'test/suite-v008.js',
    'test/suite-v009.js',
    'test/suite-v010.js',
    'test/suite-v011.js',
    'test/suite-v012.php',
    'test/probe-v012.ps1',
    'build/build-v008.py',
    'build/build-v009.py',
    'build/build-v010.py',
    'build/build-v011.py',
    'build/build-v012.py'
)

$Tag        = 'v0.0.12'
$TagMessage = @'
v0.0.12 - state and province name search

Issue 10. Adds the 13 Canadian provinces and territories, and fixes the case
defect that would have made adding them pointless.

is_state() normalised with ucwords(), which upper-cases the first letter of
each word and leaves the rest, so ucwords("MICHIGAN") is "MICHIGAN" and never
matched a table of "Michigan". Measured on Aura DEV before this build:

    address=Michigan  ->  count 35
    address=MICHIGAN  ->  count 3

The search field renders in caps (style.css .store_locator_plus
input[type="text"]), so all-caps input is what the UI invites. Every state
search typed that way had been returning three nearest dealers instead of the
state's.

is_state() has two callers. slp_ajaxsql_queryparams() at priority 999 raises
the SQL limit from 3 to 50 when the address is a recognised state;
slp_ajax_find_locations_complete_filter() at priority 10 then narrows those
rows. The limit bump is the load-bearing half - at the Ontario centroid the
three nearest dealers are all in Michigan, so a filter over three rows has
nothing to keep.

Four edits, one concern:
  - normalize_search_address(), new. The munging was duplicated in both
    callers and had already diverged; only one trimmed. Canada suffix added,
    anchored to end of string so "La Canada Flintridge" survives.
  - get_states() + 13 provinces. No key collides with the 51 US entries.
  - is_state()/get_state_initial() case-insensitive; is_state() now delegates
    so the two cannot drift apart again.
  - The comparison in the filter matches the code OR the canonical name.
    sl_state is stored inconsistently: MI and NH but also NEW HAMPSHIRE,
    DELAWARE and ONTARIO.

Deliberately excluded: bare two-letter codes (IN, OR, OK, ME, DE and others
are English words); the five US territories (s0.9 - no dealers in any, so
nothing to regression-test); the backfill, unchanged per Decision 24.

Deviates from handoff s7.6, which proposed comparing the record against the
visitor's typed string. Compares against the canonical name from get_states()
instead, because strcasecmp() does not fold accents and Quebec must match
whether or not it was typed with one.

Tests: suite-v012.php, 68 assertions, run against v0.0.11 first where it
scored 25/68. The five JS suites are unchanged and still green at 155.
probe-v012.ps1 covers the live endpoint.
'@

function Assert-Git {
    param([string]$What, [int[]]$Allow = @(0))
    if ($Allow -notcontains $LASTEXITCODE) {
        throw "git $What exited $LASTEXITCODE"
    }
}

function Assert-File {
    param([string]$Root, [string]$RelPath, [hashtable]$Want)

    $full = Join-Path $Root $RelPath
    if (-not (Test-Path -LiteralPath $full)) { throw "MISSING: $RelPath" }

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
    Write-Host ("  ok    {0,-46} {1}  {2,6} bytes  CRLF={3}" -f `
        $RelPath, $md5, $bytes.Length, $cr) -ForegroundColor Green
    return $true
}

Write-Host ''
Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]" -ForegroundColor Cyan
Write-Host ('-' * 78)

if (-not (Test-Path -LiteralPath $PluginRepo)) { throw "Plugin repo not found: $PluginRepo" }

Write-Host ''
Write-Host 'Working tree' -ForegroundColor Cyan
$allOk = $true
foreach ($rel in $Expected.Keys | Sort-Object) {
    if (-not (Assert-File -Root $PluginRepo -RelPath $rel -Want $Expected[$rel])) { $allOk = $false }
}
foreach ($rel in $TestFiles) {
    if (Test-Path -LiteralPath (Join-Path $PluginRepo $rel)) {
        Write-Host ("  ok    {0}" -f $rel) -ForegroundColor Green
    } else {
        # Fatal as of v0.0.9. A warning here is what let v0.0.8 be tagged with
        # a message claiming 86 assertions that were not in the repository.
        Write-Host ("  FAIL  MISSING: {0}" -f $rel) -ForegroundColor Red
        $allOk = $false
    }
}

Write-Host ''
Write-Host 'Test suites' -ForegroundColor Cyan
$artefact = Join-Path $PluginRepo 'slp_avalon/assets/js/slp_avalon.js'
$classPhp = Join-Path $PluginRepo 'slp_avalon/inc/class.slp_avalon.php'

Push-Location $PluginRepo
try {
    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) {
        Write-Host '  skip  node not on PATH - JS suites not run.' -ForegroundColor Yellow
        Write-Host '        Safe here: v0.0.12 is PHP-only and the JS md5 is pinned' -ForegroundColor Yellow
        Write-Host '        unchanged above, so the artefact that passed 155 is the' -ForegroundColor Yellow
        Write-Host '        artefact in the tree.' -ForegroundColor Yellow
    } else {
        & node --check $artefact
        if ($LASTEXITCODE -ne 0) { $allOk = $false; Write-Host '  FAIL  node --check' -ForegroundColor Red }
        else { Write-Host '  ok    node --check' -ForegroundColor Green }

        foreach ($suite in @('test/suite-core.js', 'test/suite-v008.js', 'test/suite-v009.js',
                             'test/suite-v010.js', 'test/suite-v011.js')) {
            if (-not (Test-Path -LiteralPath (Join-Path $PluginRepo $suite))) { continue }
            & node $suite $artefact
            if ($LASTEXITCODE -ne 0) { $allOk = $false; Write-Host ("  FAIL  {0}" -f $suite) -ForegroundColor Red }
        }
    }

    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) {
        # Expected on this machine. The PHP suite is what proves the state
        # helpers behave; without it the md5 pin is the only evidence, so say
        # so loudly rather than printing a quiet skip.
        Write-Host '  WARN  php not on PATH - suite-v012.php NOT run.' -ForegroundColor Yellow
        Write-Host '        The md5 above matches the build that passed 68/68, but' -ForegroundColor Yellow
        Write-Host '        run probe-v012.ps1 against DEV after deploy before tagging.' -ForegroundColor Yellow
    } else {
        & php -l $classPhp
        if ($LASTEXITCODE -ne 0) { $allOk = $false; Write-Host '  FAIL  php -l' -ForegroundColor Red }
        else { Write-Host '  ok    php -l' -ForegroundColor Green }

        if (Test-Path -LiteralPath (Join-Path $PluginRepo 'test/suite-v012.php')) {
            & php 'test/suite-v012.php' $classPhp
            if ($LASTEXITCODE -ne 0) { $allOk = $false; Write-Host '  FAIL  test/suite-v012.php' -ForegroundColor Red }
        }
    }
} finally {
    Pop-Location
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
        $toStage = @(
            'slp_avalon/inc/class.slp_avalon.php',
            'slp_avalon/slp_avalon.php'
        )
        foreach ($t in $TestFiles) {
            if (Test-Path -LiteralPath (Join-Path $PluginRepo $t)) { $toStage += $t }
        }

        $ignored = & git check-ignore -- @toStage
        Assert-Git 'check-ignore' -Allow @(0, 1)   # 1 = nothing matched = good
        if ($ignored) {
            Write-Host '  FAIL  .gitignore matches paths that must be committed:' -ForegroundColor Red
            $ignored | ForEach-Object { Write-Host "          $_" -ForegroundColor Red }
            throw 'Remove those .gitignore entries, then re-run. Do not use -f.'
        }

        & git add -- @toStage
        Assert-Git 'add'

        $staged = & git diff --cached --name-only
        Assert-Git 'diff --cached'
        if (-not $staged) { throw 'Nothing staged. Did the build output land in the repo?' }

        & git commit -m 'v0.0.12: state and province name search' -m @'
Adds the 13 Canadian provinces and fixes the ucwords() case defect that would
have made adding them pointless: address=Michigan returned 35 results,
address=MICHIGAN returned 3, and the field renders in caps.

is_state() gates both the SQL limit bump (3 -> 50, priority 999) and the
result filter (priority 10), so the one fix reaches both. The comparison now
matches the state code or its canonical name, because sl_state is stored as
MI and NH but also NEW HAMPSHIRE, DELAWARE and ONTARIO.

The backfill is unchanged: a state search yielding fewer than three still
fills to three with the nearest dealers.
'@
        Assert-Git 'commit'

        Write-Host ''
        Write-Host 'Byte integrity after commit' -ForegroundColor Cyan
        foreach ($rel in $Expected.Keys | Sort-Object) {
            $tree = (& git hash-object --no-filters -- $rel).Trim()
            Assert-Git 'hash-object'
            $head = (& git rev-parse "HEAD:$rel").Trim()
            Assert-Git 'rev-parse'
            if ($tree -eq $head) {
                Write-Host ("  ok    {0,-46} {1}" -f $rel, $head.Substring(0, 12)) -ForegroundColor Green
            } else {
                Write-Host ("  FAIL  {0}: a filter rewrote bytes on check-in" -f $rel) -ForegroundColor Red
                throw 'Byte integrity check failed. Do NOT push.'
            }
        }

        & git push origin HEAD
        Assert-Git 'push'

        Write-Host ''
        Write-Host 'Pushed. Deploy by SFTP in BINARY mode, then verify server-side:' -ForegroundColor Cyan
        Write-Host '  md5sum wp-content/plugins/slp_avalon/inc/class.slp_avalon.php' -ForegroundColor Gray
        Write-Host ("  expect {0}" -f $Expected['slp_avalon/inc/class.slp_avalon.php'].Md5) -ForegroundColor Gray
        Write-Host '  md5sum wp-content/plugins/slp_avalon/slp_avalon.php' -ForegroundColor Gray
        Write-Host ("  expect {0}" -f $Expected['slp_avalon/slp_avalon.php'].Md5) -ForegroundColor Gray
        Write-Host ''
        Write-Host 'Then: .\test\probe-v012.ps1   before tagging.' -ForegroundColor Yellow
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
        Assert-Git 'tag --list'
        if ($existing) { throw "Tag $Tag already exists." }

        $tmp = [System.IO.Path]::GetTempFileName()
        Set-Content -LiteralPath $tmp -Value $TagMessage -Encoding UTF8
        & git tag -a $Tag -F $tmp
        Assert-Git 'tag -a'
        Remove-Item -LiteralPath $tmp -Force

        & git push origin $Tag
        Assert-Git 'push tag'
        Write-Host ("Tagged and pushed {0}." -f $Tag) -ForegroundColor Green
    } finally {
        Pop-Location
    }
}
