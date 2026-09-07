<#
.SYNOPSIS
    SLP Dealer Guard - publish v0.0.15 (Issue 22, the two different searches).

.DESCRIPTION
    Derived from Publish-Step9.ps1. Same shape, same pinning direction:
    v0.0.15 is JS-only, so class.slp_avalon.php is pinned as UNCHANGED and is
    deliberately not staged. If its md5 moves, something was rebuilt or
    hand-edited that should not have been.

    slp_avalon.php still moves, because the version header lives in it. It is
    the same byte count either way - "0.0.14" and "0.0.15" are the same length
    - so the md5 is the only thing that catches a missed bump.

    Tests run in three tiers, all optional-if-absent but fatal-if-failing:
      node   - eight JS suites now, including the new suite-v015.js
      php    - suite-v012.php against the UNCHANGED class file, as a
               regression guard rather than as evidence for this version
      probe  - there is no probe-v015.ps1. A curl probe CAN see this defect -
               replaying the same POST twice with only `action` changed returns
               LIMIT 6 then LIMIT 3 - but it cannot see the fix, because the
               fix is which action the browser chooses. That is a DevTools
               observation, and it is item 9 of the checklist under -Mode Tag.

    NOT IN THIS SCRIPT, and deliberately so: initial_results_returned 6 -> 3.
    That value lives in the database on six environments, is excluded from WP
    Engine push deploys, and has no representation in this repository. It is a
    checklist item, not a build step. See -Mode Tag.

.PARAMETER Mode
    Verify - run every check and stop. The default; run this first.
    Commit - verify, then stage, commit and push.
    Tag    - create and push the annotated v0.0.15 tag. Run after Commit, after
             the SFTP deploy, and after the client checklist passes on DEV.

.EXAMPLE
    .\Publish-Step10.ps1 -Mode Verify
    .\Publish-Step10.ps1 -Mode Commit
    .\Publish-Step10.ps1 -Mode Tag
#>

[CmdletBinding()]
param(
    [ValidateSet('Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'

# Expected state after build-v015.py. Update these together with the build
# script, never independently.
$Expected = @{
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = '6cfdff5525bd119fa4711fc63391dc5e'; Bytes = 69984; Crlf = 1652
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = '6df1d53be2c28e3ca0e43b5f1bf31e7a'; Bytes = 1808;  Crlf = 59
    }
    # JS-only version. This one must NOT move. Same md5 since v0.0.12.
    'slp_avalon/inc/class.slp_avalon.php' = @{
        Md5 = 'f6a07b929ceed4de0d6bd5fa034eda6b'; Bytes = 61807; Crlf = 1353
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
    'test/suite-v013.js',
    'test/suite-v014.js',
    'test/suite-v015.js',
    'test/probe-v012.ps1',
    'build/build-v008.py',
    'build/build-v009.py',
    'build/build-v010.py',
    'build/build-v011.py',
    'build/build-v012.py',
    'build/build-v013.py',
    'build/build-v014.py',
    'build/build-v015.py',
    'build/patch-hygiene.py'
)

$JsSuites = @(
    'test/suite-core.js',
    'test/suite-v008.js',
    'test/suite-v009.js',
    'test/suite-v010.js',
    'test/suite-v011.js',
    'test/suite-v013.js',
    'test/suite-v014.js',
    'test/suite-v015.js'
)

$Tag        = 'v0.0.15'
$TagMessage = @'
v0.0.15 - make every search on the page the same search

Issue 22. slp_core.js:1841-1845 rewrites the first search of a page load to
csl_ajax_onload and latches immediately_show_locations to "0" on the way past,
so every later search goes out as csl_ajax_search. The server reads the row
limit from initial_results_returned for the first and max_results_returned for
the second, and builds different SQL for each.

Measured on Aura DEV. Two POSTs captured from the browser, identical in every
field - address, lat, lng, radius, formdata, all eleven options[] keys, nonce -
differing only in `action`:

  csl_ajax_onload  ... HAVING (sl_distance < 40000.000000) OR
                       (sl_distance IS NULL) ORDER BY sl_distance asc LIMIT 6
  csl_ajax_search  ... ORDER BY sl_distance asc LIMIT 3

Two differences, not one. The limits are 6 and 3 because those are the stored
settings; both options carry use_in_javascript:false, so neither reaches
slplus.options and neither is sent, and the stored values win. The radius bound
appears only on the onload path because ignore_radius=1 in the formdata is
honoured only on the search path - inert today only because the radius is the
placeholder 40000.

What the visitor saw: allow location, six pins and six rows; click Find
Locations without touching the field, three pins and three rows. Decoded from
two screenshots by fitting a Web-Mercator transform to the marker pixels - the
six and the three are exactly the six and three nearest dealers, residuals
0-2 px.

The fix. Three paths reach the first search. cslmap_build_map()'s
URL-parameter branch and get_user_current_address() on a granted permission
both get there by triggering the real submit button, so they are ordinary
searches wearing the onload budget. The third - geolocation denied, fail_cb,
load_markers() called directly - is the one case initial_results_returned is
named for. Spending the latch at the top of cslmap_searchLocations() draws the
line exactly there, in one edit, and keeps covering it for Get My Position and
for whatever path is added next.

Before Layer 0, not after: placed after the gate, a rejected first search would
leave the latch armed and the next successful search would still be an onload.
Safe to spend on a rejection because the latch has two readers left and neither
can move - the bootstrap gate in cslmap_build_map() has already been evaluated
by then, and show_home_marker() returns usingSensor once no_homeicon_at_start
is "1", measured "1" with use_sensor false on all six environments. That closes
the open half of handoff rev 11 s0.6 with numbers rather than a source reading.

store-locator-le/ untouched. Constraint C1 untouched - no URL parameter is
added, removed or reordered.

Tests: suite-v015.js, 30 assertions, run against v0.0.14 first where it scored
23/30 - the seven failures all [DISCRIMINATOR], the eight [GUARD] cases green
on both. The first draft scored 25/30 there; two assertions were labelled
discriminators and were not, and were rewritten rather than shipped. Seven
earlier JS suites unchanged and still green at 224, suite-v012.php still 68
against an untouched class file. 322 total.
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
        # Same weight as Step9: v0.0.15 IS the JS. The md5 pin above is still
        # real evidence - it proves the tree holds the exact artefact that
        # passed 254 - but nothing is being re-proved on this machine.
        Write-Host '  WARN  node not on PATH - the eight JS suites were NOT run.' -ForegroundColor Yellow
        Write-Host '        This version IS the JS. The md5 above matches the build' -ForegroundColor Yellow
        Write-Host '        that passed 30/30 on suite-v015 and 224 on the rest, so' -ForegroundColor Yellow
        Write-Host '        the artefact is right; nothing was re-verified here.' -ForegroundColor Yellow
        Write-Host '        Install node, or run the client checklist on DEV before' -ForegroundColor Yellow
        Write-Host '        tagging.' -ForegroundColor Yellow
    } else {
        & node --check $artefact
        if ($LASTEXITCODE -ne 0) { $allOk = $false; Write-Host '  FAIL  node --check' -ForegroundColor Red }
        else { Write-Host '  ok    node --check' -ForegroundColor Green }

        foreach ($suite in $JsSuites) {
            if (-not (Test-Path -LiteralPath (Join-Path $PluginRepo $suite))) { continue }
            & node $suite $artefact
            if ($LASTEXITCODE -ne 0) { $allOk = $false; Write-Host ("  FAIL  {0}" -f $suite) -ForegroundColor Red }
        }
    }

    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) {
        # Expected on this machine, and low stakes: the class file is pinned
        # UNCHANGED above, so suite-v012 is a regression guard here rather than
        # evidence for anything v0.0.15 does.
        Write-Host '  WARN  php not on PATH - suite-v012.php NOT run.' -ForegroundColor Yellow
        Write-Host '        Lower stakes: class.slp_avalon.php is pinned unchanged' -ForegroundColor Yellow
        Write-Host '        above, so the file that passed 68/68 at v0.0.12 is' -ForegroundColor Yellow
        Write-Host '        byte-for-byte the file in the tree.' -ForegroundColor Yellow
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
        #
        # class.slp_avalon.php is deliberately NOT staged. It did not change,
        # and the byte-integrity loop below still checks it against HEAD, so an
        # accidental edit is caught rather than quietly committed.
        $toStage = @(
            'slp_avalon/assets/js/slp_avalon.js',
            'slp_avalon/slp_avalon.php'
        )
        foreach ($t in $TestFiles) {
            if (Test-Path -LiteralPath (Join-Path $PluginRepo $t)) { $toStage += $t }
        }
        if (Test-Path -LiteralPath (Join-Path $PluginRepo 'Publish-Step10.ps1')) {
            $toStage += 'Publish-Step10.ps1'
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

        & git commit -m 'v0.0.15: make every search on the page the same search' -m @'
Issue 22. slp_core.js:1841-1845 rewrites the first search of a page load to
csl_ajax_onload and latches immediately_show_locations to "0", so the first
search is limited by initial_results_returned (6) and every later one by
max_results_returned (3). Two browser POSTs identical in every field but
`action` returned LIMIT 6 and LIMIT 3 on Aura DEV.

Both bootstraps reach cslmap_searchLocations() by triggering the real submit
button, so they are ordinary searches wearing the onload budget. Spending the
latch at the top of that function leaves the denied-geolocation fallback - which
calls load_markers() directly - as the only onload, which is the one case
initial_results_returned is named for.

Before Layer 0, so a rejected first search cannot leave the latch armed for the
next one. Safe because show_home_marker() already returns usingSensor with
no_homeicon_at_start = "1", measured on all six environments.

JS only. class.slp_avalon.php is unchanged and pinned as such.
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
                Write-Host ("  FAIL  {0}: working tree differs from HEAD" -f $rel) -ForegroundColor Red
                throw 'Byte integrity check failed. Do NOT push.'
            }
        }

        & git push origin HEAD
        Assert-Git 'push'

        Write-Host ''
        Write-Host 'Pushed. Deploy TWO files by SFTP in BINARY mode:' -ForegroundColor Cyan
        Write-Host '  wp-content/plugins/slp_avalon/assets/js/slp_avalon.js' -ForegroundColor Gray
        Write-Host ("    expect {0}" -f $Expected['slp_avalon/assets/js/slp_avalon.js'].Md5) -ForegroundColor Gray
        Write-Host '  wp-content/plugins/slp_avalon/slp_avalon.php' -ForegroundColor Gray
        Write-Host ("    expect {0}" -f $Expected['slp_avalon/slp_avalon.php'].Md5) -ForegroundColor Gray
        Write-Host ''
        Write-Host '  Do NOT upload inc/class.slp_avalon.php - it is unchanged.' -ForegroundColor Yellow
        Write-Host '  Verify by SSH md5sum, not from a browser: the bare asset URL is' -ForegroundColor Yellow
        Write-Host '  cached at the WP Engine edge and will serve the old JS. The' -ForegroundColor Yellow
        Write-Host '  enqueue itself is fine - file_version() uses filemtime(), so the' -ForegroundColor Yellow
        Write-Host '  ?ver= string moves the moment the upload lands.' -ForegroundColor Yellow
        Write-Host ''
        Write-Host 'Then set initial_results_returned to 3 in WP Admin, then run the' -ForegroundColor Yellow
        Write-Host 'client checklist. Order matters: the settings change alters what' -ForegroundColor Yellow
        Write-Host 'checklist item 10 should show.' -ForegroundColor Yellow
    } finally {
        Pop-Location
    }
    exit 0
}

# ------------------------------------------------------------------- tag
if ($Mode -eq 'Tag') {
    Write-Host ''
    Write-Host 'Settings, before the checklist:' -ForegroundColor Cyan
    Write-Host '  SLP | Experience | Results on Aura DEV -' -ForegroundColor Gray
    Write-Host '    initial_results_returned = 3   (was 6)' -ForegroundColor Gray
    Write-Host '    max_results_returned     = 3   (unchanged)' -ForegroundColor Gray
    Write-Host '  Database, not files. It does not travel by SFTP and is excluded' -ForegroundColor Yellow
    Write-Host '  from WP Engine push deploys, so each of the six environments needs' -ForegroundColor Yellow
    Write-Host '  it done by hand.' -ForegroundColor Yellow
    Write-Host ''
    Write-Host 'Client checklist - all of these on Aura DEV before tagging:' -ForegroundColor Cyan
    Write-Host '  1. Load ?place_lat=48.86&place_lng=2.35 -> territory message, and' -ForegroundColor Gray
    Write-Host '     the bar drops to the bare page URL. Refresh: clean load, no' -ForegroundColor Gray
    Write-Host '     message, no Paris pin, no AJAX.' -ForegroundColor Gray
    Write-Host '  2. Load the same plus &utm_source=test&utm_medium=cpc&gclid=abc123' -ForegroundColor Gray
    Write-Host '     -> after the rejection the bar reads /find-a-dealer/ with all' -ForegroundColor Gray
    Write-Host '     three attribution keys still on it. Constraint C1.' -ForegroundColor Gray
    Write-Host '  3. Search Detroit, MI -> results, and place_address IS on the bar.' -ForegroundColor Gray
    Write-Host '  4. Copy that URL, open it fresh -> the same page state AND the' -ForegroundColor Gray
    Write-Host '     SAME COUNT. Changed at v0.0.15: through v0.0.14 a fresh load' -ForegroundColor Gray
    Write-Host '     and a manual re-search took different paths through SLP and' -ForegroundColor Gray
    Write-Host '     the checklist said so. That was Issue 22, and it is fixed.' -ForegroundColor Gray
    Write-Host '  5. From the URL left by 3, type Tijuana -> territory message, the' -ForegroundColor Gray
    Write-Host '     place_* keys drop and every attribution key stays.' -ForegroundColor Gray
    Write-Host '  6. Type MICHIGAN in caps -> Michigan dealers. The state branch is' -ForegroundColor Gray
    Write-Host '     live and case-insensitive since v0.0.12.' -ForegroundColor Gray
    Write-Host '  7. Back button leaves the page in one press. replaceState does not' -ForegroundColor Gray
    Write-Host '     stack.' -ForegroundColor Gray
    Write-Host '  8. Type 1200 Woodward Ave #4, Detroit, MI and press Find Locations' -ForegroundColor Gray
    Write-Host '     - do NOT take an autocomplete suggestion, or Google returns a' -ForegroundColor Gray
    Write-Host '     clean address and the bug never fires. Detroit dealers come' -ForegroundColor Gray
    Write-Host '     back. The bar still shows %234. Issue 25, v0.0.14.' -ForegroundColor Gray
    Write-Host '  9. THE ONE THIS VERSION IS FOR. Fresh incognito tab, allow' -ForegroundColor Gray
    Write-Host '     location, count the pins. Click Find Locations without' -ForegroundColor Gray
    Write-Host '     touching the field -> the same count. In DevTools, Network,' -ForegroundColor Gray
    Write-Host '     filter admin-ajax: BOTH POSTs must show action=csl_ajax_search.' -ForegroundColor Gray
    Write-Host ' 10. The exception, and it must still work. Fresh incognito tab,' -ForegroundColor Gray
    Write-Host '     BLOCK location -> the landing list still renders, and that one' -ForegroundColor Gray
    Write-Host '     POST is action=csl_ajax_onload. It never passes through' -ForegroundColor Gray
    Write-Host '     cslmap_searchLocations(), which is the whole design.' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Do NOT ask for a dealerless search. EMPTY is unreachable: the' -ForegroundColor Yellow
    Write-Host '  Decision 29 backfill tops any in-territory response up to three' -ForegroundColor Yellow
    Write-Host '  with no distance ceiling, and a zeroed count only ever comes from' -ForegroundColor Yellow
    Write-Host '  the territory gate, which takes the REJECTED branch instead.' -ForegroundColor Yellow
    Write-Host ''
    Write-Host '  Expect two pins for three Detroit results. DONNIE MARCH and I-94' -ForegroundColor Yellow
    Write-Host '  Marine are stored half a metre apart, so their markers coincide.' -ForegroundColor Yellow
    Write-Host ''
    $ok = Read-Host 'All ten passed? (type YES to tag)'
    if ($ok -cne 'YES') {
        Write-Host ("Expected YES in capitals, got '{0}'. Not tagged." -f $ok) -ForegroundColor Yellow
        exit 1
    }

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
