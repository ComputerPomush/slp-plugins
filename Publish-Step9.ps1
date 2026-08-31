<#
.SYNOPSIS
    SLP Dealer Guard - publish v0.0.13 (Issue 16, the sticky URL).

.DESCRIPTION
    Derived from Publish-Step8.ps1. The pinning is inverted relative to that
    script: v0.0.12 was PHP-only and pinned the JS as unchanged, v0.0.13 is
    JS-only and pins class.slp_avalon.php as unchanged. If that file's md5
    moves, something was rebuilt or hand-edited that should not have been.

    slp_avalon.php still moves, because the version header lives in it. It is
    the same byte count either way - "0.0.12" and "0.0.13" are the same length
    - so the md5 is the only thing that catches a missed bump.

    Tests run in three tiers, all optional-if-absent but fatal-if-failing:
      node   - six JS suites now, including the new suite-v013.js
      php    - suite-v012.php against the UNCHANGED class file, as a
               regression guard rather than as evidence for this version
      probe  - there is no probe-v013.ps1, and deliberately so. Issue 16 is a
               history.replaceState change, which a curl probe cannot observe.
               probe-v012.ps1 still applies as a server-side regression guard;
               the client checklist under -Mode Tag is what covers this one.

.PARAMETER Mode
    Verify - run every check and stop. The default; run this first.
    Commit - verify, then stage, commit and push.
    Tag    - create and push the annotated v0.0.13 tag. Run after Commit, after
             the SFTP deploy, and after the client checklist passes on DEV.

.EXAMPLE
    .\Publish-Step9.ps1 -Mode Verify
    .\Publish-Step9.ps1 -Mode Commit
    .\Publish-Step9.ps1 -Mode Tag
#>

[CmdletBinding()]
param(
    [ValidateSet('Verify', 'Commit', 'Tag')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'

# Expected state after build-v013.py. Update these together with the build
# script, never independently.
$Expected = @{
    'slp_avalon/assets/js/slp_avalon.js'  = @{
        Md5 = 'de3216467ac8e95a84448846e2ce7032'; Bytes = 64863; Crlf = 1554
    }
    'slp_avalon/slp_avalon.php'           = @{
        Md5 = '2abf1b6145d8206bdda977b9eaa765d1'; Bytes = 1808;  Crlf = 59
    }
    # JS-only version. This one must NOT move. Same md5 as v0.0.12.
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
    'test/probe-v012.ps1',
    'build/build-v008.py',
    'build/build-v009.py',
    'build/build-v010.py',
    'build/build-v011.py',
    'build/build-v012.py',
    'build/build-v013.py'
)

$JsSuites = @(
    'test/suite-core.js',
    'test/suite-v008.js',
    'test/suite-v009.js',
    'test/suite-v010.js',
    'test/suite-v011.js',
    'test/suite-v013.js'
)

$Tag        = 'v0.0.13'
$TagMessage = @'
v0.0.13 - clean the search parameters off a failed URL

Issue 16. finish() has always written pending_url on RESULTS and never on
anything else, so a failed search could not dirty the address bar. It never
cleaned one the visitor arrived on either: land on
?place_lat=48.86&place_lng=2.35, get the territory message, refresh, get it
again. Forever.

Adds the other half. Write on RESULTS, clean on every other terminal state.

Decision 35. s7.7 proposed REJECTED, ERROR and TIMEOUT; EMPTY was added on the
owner's call because any narrower rule leaves a case where the parameters do
not describe what is on the screen - land on ?place_address=Detroit, search
somewhere with no dealers, and EMPTY would keep replaying Detroit. Two costs
accepted with it: a genuine no-dealers result stops being shareable as a link,
and a TIMEOUT on a slow but valid search loses the query on refresh rather
than retrying it.

Three things shaped the implementation:

  - It cannot use pending_url. Layer 0 returns before pending_url is assigned
    and start() nulls it every cycle, so on the exact path this fixes it is
    always null. The cleaned URL is measured from window.location.href.

  - It is a remove-list, not a keep-list. Constraint C1: UTMs arrive on a
    first-touch URL before any cookie exists. add_url_param() already deletes
    on a falsy value, so the three keys go in as nulls and nothing else on the
    query string is touched. place_country is not among them - it never
    reaches the URL, only jQuery .data().

  - clean_url() returns early when the URL is already clean. Without that,
    suite-core's "a rejected search does not rewrite the URL" - the regression
    net for Issue 1 rule (c) - would have had to be weakened to let this
    through.

Verified before writing, because the clean runs mid-cycle: slp_core.min.js
(7924dad949f851d90ade9118c8bd045a, the build that actually runs) has no
reference to place_address, place_lat, place_lng or place_country and no
history call of any kind. cslmap_build_map() is the only reader on the page
and it runs once at init.

Tests: suite-v013.js, 35 assertions, run against v0.0.12 first where it scored
11/35 - the 11 being the guards that must hold on both builds. Five earlier JS
suites unchanged and still green at 155, suite-v012.php still 68 against an
untouched class file. 258 total.
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
        # More serious here than in Step8: v0.0.13 is the JS. The md5 pin above
        # is still real evidence - it proves the tree holds the exact artefact
        # that passed 258 - but nothing is being re-proved on this machine.
        Write-Host '  WARN  node not on PATH - the six JS suites were NOT run.' -ForegroundColor Yellow
        Write-Host '        This version IS the JS. The md5 above matches the build' -ForegroundColor Yellow
        Write-Host '        that passed 35/35 on suite-v013 and 155 on the rest, so' -ForegroundColor Yellow
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
        # Expected on this machine, and less consequential than in Step8: the
        # class file is pinned UNCHANGED above, so suite-v012 is a regression
        # guard here rather than evidence for anything v0.0.13 does.
        Write-Host '  WARN  php not on PATH - suite-v012.php NOT run.' -ForegroundColor Yellow
        Write-Host '        Lower stakes this time: class.slp_avalon.php is pinned' -ForegroundColor Yellow
        Write-Host '        unchanged above, so the file that passed 68/68 at v0.0.12' -ForegroundColor Yellow
        Write-Host '        is byte-for-byte the file in the tree.' -ForegroundColor Yellow
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

        & git commit -m 'v0.0.13: clean the search parameters off a failed URL' -m @'
Issue 16. finish() wrote pending_url on RESULTS and never dirtied the address
bar on a failure, but it never cleaned one the visitor arrived on either, so
?place_lat=48.86&place_lng=2.35 was re-rejected on every refresh.

Write on RESULTS, clean on every other terminal state including EMPTY
(decision 35). Measured from window.location.href rather than pending_url,
which is always null on the Layer 0 path this fixes. Remove-list, so UTMs on a
first-touch URL survive (constraint C1). clean_url() returns early when the
URL is already clean, which is what keeps suite-core green.

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
        Write-Host 'Then run the client checklist before tagging. No probe for this' -ForegroundColor Yellow
        Write-Host 'version - replaceState is not observable from curl.' -ForegroundColor Yellow
    } finally {
        Pop-Location
    }
    exit 0
}

# ------------------------------------------------------------------- tag
if ($Mode -eq 'Tag') {
    Write-Host ''
    Write-Host 'Client checklist - all of these on Aura DEV before tagging:' -ForegroundColor Cyan
    Write-Host '  1. Load ?place_lat=48.86&place_lng=2.35 -> territory message, and' -ForegroundColor Gray
    Write-Host '     the bar drops to the bare page URL. Refresh: clean load, no' -ForegroundColor Gray
    Write-Host '     message, no Paris pin, no AJAX.' -ForegroundColor Gray
    Write-Host '  2. Load the same plus &utm_source=test&utm_medium=cpc&gclid=abc123' -ForegroundColor Gray
    Write-Host '     -> after the rejection the bar reads /find-a-dealer/ with all' -ForegroundColor Gray
    Write-Host '     three attribution keys still on it. Constraint C1.' -ForegroundColor Gray
    Write-Host '  3. Search Detroit, MI -> results, and place_address IS on the bar.' -ForegroundColor Gray
    Write-Host '  4. Copy that URL, open it fresh -> the same PAGE STATE: field' -ForegroundColor Gray
    Write-Host '     populated, results rendered, no error. NOT the same count.' -ForegroundColor Gray
    Write-Host '     A fresh load and a manual re-search take different paths' -ForegroundColor Gray
    Write-Host '     through SLP by design - Issue 22.' -ForegroundColor Gray
    Write-Host '  5. From the URL left by 3, type Tijuana -> territory message, the' -ForegroundColor Gray
    Write-Host '     place_* keys drop and every attribution key stays.' -ForegroundColor Gray
    Write-Host '  6. Type MICHIGAN in caps -> Michigan dealers. The state branch is' -ForegroundColor Gray
    Write-Host '     live and case-insensitive since v0.0.12.' -ForegroundColor Gray
    Write-Host '  7. Back button leaves the page in one press. replaceState does not' -ForegroundColor Gray
    Write-Host '     stack.' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Do NOT ask for a dealerless search. EMPTY is unreachable: the' -ForegroundColor Yellow
    Write-Host '  Decision 29 backfill tops any in-territory response up to three' -ForegroundColor Yellow
    Write-Host '  with no distance ceiling, and a zeroed count only ever comes from' -ForegroundColor Yellow
    Write-Host '  the territory gate, which takes the REJECTED branch instead.' -ForegroundColor Yellow
    Write-Host ''
    Write-Host '  Expect two pins for three Detroit results. DONNIE MARCH and I-94' -ForegroundColor Yellow
    Write-Host '  Marine are stored half a metre apart, so their markers coincide.' -ForegroundColor Yellow
    Write-Host ''
    $ok = Read-Host 'All seven passed? (type YES to tag)'
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
